'use strict';
const fs=require('fs'),path=require('path');
const names=['PROGRESS.md','RESUME.md','CHANGELOG.md','CHECKPOINT.md','checkpoint-state.json'];
function coherent(bundle){
 const state=JSON.parse(bundle['checkpoint-state.json'].replace(/^\uFEFF/,''));
 if(!state.id||!names.slice(0,4).every(name=>bundle[name]?.includes('Checkpoint: '+state.id)||bundle[name]?.includes('Last successful checkpoint: '+state.id)))throw Error('Checkpoint bundle IDs do not match');
 return state;
}
function recoverBundle(directory,atomicWrite){
 try{const current=Object.fromEntries(names.map(name=>[name,fs.readFileSync(path.join(directory,name),'utf8')]));coherent(current);return false;}catch(_){/* Restore only after a complete previous bundle is verified. */}
 const previous=Object.fromEntries(names.map(name=>[name,fs.readFileSync(path.join(directory,name+'.previous'),'utf8')]));
 coherent(previous);
 const archive=path.join(directory,'interrupted',new Date().toISOString().replace(/[:.]/g,'-'));
 fs.mkdirSync(archive,{recursive:true});
 for(const name of names){const p=path.join(directory,name);if(fs.existsSync(p))atomicWrite(path.join(archive,name),fs.readFileSync(p,'utf8'));}
 atomicWrite(path.join(directory,'checkpoint-state.json'),previous['checkpoint-state.json']);
 for(const name of names.slice(0,4))atomicWrite(path.join(directory,name),previous[name]);
 coherent(Object.fromEntries(names.map(name=>[name,fs.readFileSync(path.join(directory,name),'utf8')])));
 atomicWrite(path.join(directory,'RECOVERY_NOTES.md'),'Restored the last complete previous documentation bundle. Inspect '+path.relative(directory,archive)+' before resuming. Source files, Git history and database records were untouched. Interrupted command results remain UNKNOWN. Run --verify and inspect changed source.\n');
 return true;
}
module.exports={recoverBundle,coherent};
