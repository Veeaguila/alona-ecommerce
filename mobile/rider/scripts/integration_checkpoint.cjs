'use strict';
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const {execFileSync,spawnSync} = require('child_process');
const RIDER = path.resolve(__dirname, '..');
const ALONA = path.resolve(RIDER, '../..');
const sha = value => crypto.createHash('sha256').update(value).digest('hex');
const parse = value => JSON.parse(value.replace(/^\uFEFF/,''));

function atomicWrite(target, content, beforeReplace) {
  fs.mkdirSync(path.dirname(target), {recursive: true});
  const temporary = target + '.tmp-' + process.pid;
  const fd = fs.openSync(temporary, 'w');
  try { fs.writeFileSync(fd, content, 'utf8'); fs.fsyncSync(fd); }
  finally { fs.closeSync(fd); }
  if (fs.readFileSync(temporary, 'utf8') !== content) throw Error('Temporary write verification failed');
  if (beforeReplace) beforeReplace();
  // Same-directory rename replaces the target atomically where supported.
  // Failure leaves the old target and the complete temporary file intact.
  fs.renameSync(temporary, target);
  if (fs.readFileSync(target, 'utf8') !== content) throw Error('Final write verification failed');
}

function git(root) {
  const run = args => execFileSync('git', ['-c', 'safe.directory='+root, '-C', root, ...args], {encoding:'utf8', stdio:['ignore','pipe','pipe']}).trim();
  try {
    return {tracked:true, branch:run(['branch','--show-current']) || '(detached HEAD)',
      head:(()=>{try{return run(['rev-parse','--short','HEAD']);}catch(_){return '(no commits)';}})(), status:run(['status','--short']) || '(clean)'};
  } catch (_) {
    if(fs.existsSync(path.join(root,'.git')))throw Error('Git metadata is unavailable for '+root+'; stop cross-project changes and inspect access.');
    return {tracked:false, branch:'N/A', head:'N/A', status:'Not a Git repository; source changes exist only on disk.'};
  }
}

function fingerprint(files, root) {
  const result = {};
  for (const relative of files) {
    if (/\.env|credential|secret|token-store-backup|storage\/|identity-doc/i.test(relative)) throw Error('Sensitive path refused');
    const full = path.resolve(root, relative);
    if (!full.startsWith(root + path.sep)) throw Error('Path outside project refused');
    result[relative] = fs.existsSync(full) ? sha(fs.readFileSync(full)) : null;
  }
  return result;
}

function bullet(items) { return items?.length ? items.map(item => '- ' + item).join('\n') : '- None recorded.'; }
function repository(label, repo) {
  return `### ${label}\n\nGit tracked: ${repo.tracked}; branch: ${repo.branch}; HEAD: ${repo.head}\n\n\`\`\`text\n${repo.status}\n\`\`\`\n`;
}

function save(update) {
  if (!fs.existsSync(RIDER) || !fs.existsSync(ALONA)) throw Error('Both projects must be accessible before cross-project checkpointing');
  const directory = path.join(RIDER, 'docs/integration');
  const stateFile = path.join(directory, 'checkpoint-state.json');
  const previous = fs.existsSync(stateFile) ? parse(fs.readFileSync(stateFile,'utf8')) : {};
  const state = {...previous, ...update};
  const id = new Date().toISOString();
  state.id = id;
  state.projectPaths = {rider:RIDER, alona:ALONA};
  state.repositories = {rider:git(RIDER), alona:git(ALONA)};
  state.hashes = {
    rider:fingerprint(state.flutterFiles || [], RIDER),
    alona:fingerprint(state.laravelFiles || [], ALONA),
  };
  const docs = {};
  docs['PROGRESS.md'] = `# Integration progress\n\nCheckpoint: ${id}\n\nALONA is the single backend and database. Phase definitions below supersede the earlier six-phase outline.\n\n| Phase | Scope | Status |\n|---|---|---|\n` +
    state.phases.map(p=>`| ${p.number} | ${p.name} | ${p.status} |`).join('\n') +
    `\n\nCompleted\n\n${bullet(state.completed)}\n\nRemaining\n\n${bullet(state.remaining)}\n\nEvidence and limitations: see CHECKPOINT.md and existing ../CURRENT_STATE.md.\n`;
  docs['CHECKPOINT.md'] = `# Integration checkpoint\n\nCheckpoint: ${id}\nSaved synchronously; timestamps are UTC.\n\nCURRENT PHASE: ${state.phase}\nCURRENT TASK: ${state.task}\nLAST COMPLETED TASK: ${state.lastCompleted}\nLAST VERIFIED TEST: ${state.lastVerified}\n\nCOMPLETED TASKS:\n\n${bullet(state.completed)}\n\nREMAINING TASKS:\n\n${bullet(state.remaining)}\n\nMODIFIED FLUTTER FILES:\n\n${bullet(state.flutterFiles)}\n\nMODIFIED LARAVEL FILES:\n\n${bullet(state.laravelFiles)}\n\nRELEVANT API ENDPOINTS:\n\n${bullet(state.endpoints)}\n\nTEST RESULTS:\n\n${bullet(state.tests)}\n\nUNCOMMITTED CHANGES:\n\n${repository('ALONA',state.repositories.alona)}\n${repository('Rider',state.repositories.rider)}\nNo automatic commit or push. ALONA tracked status above includes all existing changes; ownership must be checked before committing. ${state.repositories.rider.tracked ? "Rider changes are tracked by Git; HEAD and working-tree status above distinguish committed/local work." : "Rider has no Git recovery history."}\n\nKNOWN ISSUES:\n\n${bullet(state.issues)}\n\nINTERRUPTED OR RUNNING OPERATIONS:\n\n${bullet(state.operations)}\n\nNEXT ACTION: ${state.next}\n\nRECOVERY NOTES:\n\nVerify source hashes with --verify and inspect changed files before continuing. A running operation has UNKNOWN results until its confirmed outcome is saved. Preserve .previous files and orphan .tmp-* files until their content is checked. CHECKPOINT.md is written last as the completed bundle marker; mismatched IDs mean an interrupted bundle. State JSON is a recovery aid, not proof of a successful command. Never reset/clean uncommitted files or repeat migrations automatically.\n`;
  docs['RESUME.md'] = `# Resume ALONA / Rider integration\n\nProjects: Rider \`${RIDER}\`; ALONA \`${ALONA}\`.\nCurrent phase: ${state.phase}. Objective: shared ALONA API integration with safe disk checkpoints.\nLast successful checkpoint: ${id}.\n\n## NEXT CODEX SESSION\n\n1. Verify both directories; read applicable AGENTS.md, this file, CHECKPOINT.md, PROGRESS.md, then existing project memory.\n2. Check Git branch/status in both projects. ${state.repositories.rider.tracked ? "Rider is Git-tracked; preserve existing commits and local changes." : "Rider is not Git-tracked; compare recorded file hashes instead."}\n3. Run \`node scripts/integration_checkpoint.cjs --verify\` from Rider. Inspect only changed/incomplete files; trust verified source over notes.\n4. Treat commands listed as running/interrupted as UNKNOWN. Rerun only affected focused checks; never repeat migrations or destructive actions automatically.\n5. Next exact task: ${state.next}\n6. Save a checkpoint before each phase, meaningful edit batch, risky edit, long test/build, and after each phase. Use --update with a JSON update file; complete content is flushed to a same-directory temporary file, atomically replaced and verified. Use --run for test/build commands; running and confirmed outcomes are saved automatically. If bundle IDs disagree, use --recover to verify and restore the complete previous bundle while archiving interrupted notes. Inspect RECOVERY_NOTES.md and changed source before continuing. Do not edit checkpoint documents manually.\n\nDependencies: existing ALONA Sanctum, partner registration/address/coverage contracts, Rider approval and parcel authorization. No extra backend/database.\nRelevant files: use the scoped lists in CHECKPOINT.md and ../ARCHITECTURE.md.\nVerification commands: \`node --test scripts/integration_checkpoint.test.cjs\`; \`flutter test --no-pub <affected-tests>\`; \`flutter analyze --no-pub --no-fatal-infos <affected-files>\`; ALONA \`php vendor/phpunit/phpunit/phpunit --do-not-cache-result <affected-tests>\` with DB_CONNECTION=sqlite, DB_DATABASE=:memory:, DB_URL empty. Use the --run alona wrapper for PHPUnit: it refuses cached application configuration and forces an in-memory database. Never clear shared config automatically or test against development/production records.\n\nBlockers / decisions:\n\n${bullet(state.issues)}\n\nAuthorization: continue Phases 4-10 where safe per the latest user request. Phase 3 is completed; do not reimplement it. Commits require explicit permission. No permission to invent rider earnings or payout rules.\nLimitations: no automatic Codex launch after reboot, unsaved edit/process recovery, protection against disk corruption/deletion, or off-device backup. Existing valid files are preserved if atomic replacement fails.\n`;
  const history = previous.history || [];
  if (update.event) history.push({id,event:update.event});
  state.history = history;
  docs['CHANGELOG.md'] = '# Integration changelog\n\nCheckpoint: '+id+'\n\n'+history.map(e=>`- ${e.id}: ${e.event}`).join('\n')+'\n';
  // Save previous completed documents without deleting the valid originals.
  for(const name of [...Object.keys(docs),'checkpoint-state.json']) {
    const p=path.join(directory,name);
    if(fs.existsSync(p)) atomicWrite(p+'.previous',fs.readFileSync(p,'utf8'));
  }
  atomicWrite(stateFile,JSON.stringify(state,null,2)+'\n');
  for(const name of ['PROGRESS.md','RESUME.md','CHANGELOG.md','CHECKPOINT.md']) atomicWrite(path.join(directory,name),docs[name]);
  console.log('Verified checkpoint saved: '+id+' (phase '+state.phase+')');
}

function verify() {
  const directory=path.join(RIDER,'docs/integration');
  const state=parse(fs.readFileSync(path.join(directory,'checkpoint-state.json'),'utf8'));
  for(const name of ['PROGRESS.md','RESUME.md','CHANGELOG.md','CHECKPOINT.md']) {
    if(!fs.readFileSync(path.join(directory,name),'utf8').includes('Checkpoint: '+state.id) &&
       !fs.readFileSync(path.join(directory,name),'utf8').includes('Last successful checkpoint: '+state.id)) throw Error('Interrupted checkpoint bundle: '+name);
  }
  const changes=[];
  for(const [label,root] of [['rider',RIDER],['alona',ALONA]]) {
    if(!fs.existsSync(root)) throw Error('Project unavailable: '+label);
    const hashes=fingerprint(Object.keys(state.hashes[label]),root);
    for(const [file,hash] of Object.entries(hashes)) if(hash!==state.hashes[label][file]) changes.push(label+': '+file);
    console.log(label+' Git: '+JSON.stringify(git(root)));
  }
  console.log(changes.length ? 'Changed since checkpoint (inspect before resume):\n'+changes.join('\n') : 'Checkpoint bundle and recorded source hashes verified.');
  if(changes.length) process.exitCode=2;
}
function assertNoCachedConfig(root) {
  if(fs.existsSync(path.join(root,'bootstrap/cache/config.php')))throw Error('Refusing Laravel tests with cached configuration; inspect it without clearing or changing production configuration automatically.');
}

function runCheck(project, command, args) {
  const root = project==='rider' ? RIDER : project==='alona' ? ALONA : null;
  if(!root) throw Error('Choose rider or alona');
  if(project==='alona')assertNoCachedConfig(root);
  if(!['flutter','dart','php','node'].includes(command) || args.some(arg=>!/^[-a-zA-Z0-9_:/\\=.]+$/.test(arg))) throw Error('Unsupported check command/argument');
  if(command==='php' && !args[0]?.includes('phpunit')) throw Error('PHP wrapper supports isolated PHPUnit checks only');
  if(command==='flutter' && !['test','analyze','build'].includes(args[0])) throw Error('Flutter wrapper supports test/analyze/build only');
  const statePath=path.join(RIDER,'docs/integration/checkpoint-state.json');
  const state=parse(fs.readFileSync(statePath,'utf8'));
  const label=project+': '+command+' '+args.join(' ');
  save({task:'Validation running',operations:[label+'; result UNKNOWN until completion'],next:'Inspect command completion/log; rerun only if results remain unknown'});
  const env={...process.env};
  if(project==='alona') Object.assign(env,{APP_ENV:'testing',DB_CONNECTION:'sqlite',DB_DATABASE:':memory:',DB_URL:''});
  const batch=process.platform==='win32' && ['flutter','dart'].includes(command);
  const result=spawnSync(batch?'cmd.exe':command,batch?['/d','/c',[command,...args].join(' ')]:args,{cwd:root,env,encoding:'utf8',shell:false,maxBuffer:16*1024*1024});
  const output=(result.stdout||'')+(result.stderr||'');
  const logDir=path.join(RIDER,'.recovery/logs');fs.mkdirSync(logDir,{recursive:true});
  const log=path.join(logDir,Date.now()+'-'+project+'.txt');atomicWrite(log,output);
  const passed=result.status===0 && !result.error;
  save({task:passed?'Validation completed':'Validation failed; inspect log',operations:[],
    tests:[...(state.tests||[]),label+' => '+(passed?'PASS':'FAIL/UNKNOWN')+'; log '+path.relative(RIDER,log)],
    lastVerified:passed?label+' passed':state.lastVerified,
    next:passed?state.next:'Inspect '+path.relative(RIDER,log)+' and fix only the affected code',
    event:label+' '+(passed?'passed':'failed/unknown')});
  process.stdout.write(output);process.exitCode=passed?0:1;
}
module.exports={atomicWrite,sha,parse,assertNoCachedConfig};
if(require.main===module) {
  try {
    if(process.argv[2]==='--verify') verify();
    else if(process.argv[2]==='--recover') {
      const restored=require('./checkpoint_recovery.cjs').recoverBundle(path.join(RIDER,'docs/integration'),atomicWrite);
      console.log(restored?'Recovered previous complete bundle; inspect RECOVERY_NOTES.md.':'Current bundle is coherent; nothing restored.');
      verify();
    }
    else if(process.argv[2]==='--update' && process.argv[3]) save(parse(fs.readFileSync(process.argv[3],'utf8')));
    else if(process.argv[2]==='--run') runCheck(process.argv[3],process.argv[4],process.argv.slice(5));
    else throw Error('Use --update <JSON-file>, --verify, --recover, or --run rider|alona <check-command> <args>');
  } catch(error) { console.error(error.message);process.exitCode=1; }
}
