const {test}=require('node:test');const assert=require('node:assert/strict');
const fs=require('fs');const os=require('os');const path=require('path');
const {atomicWrite,parse}=require('./integration_checkpoint.cjs');
test('PowerShell UTF-8 BOM JSON updates are accepted',()=>assert.deepEqual(parse('\uFEFF{"phase":4}'),{phase:4}));
function cleanup(dir) {
 const target=path.resolve(dir),base=path.resolve(os.tmpdir())+path.sep;
 if(!target.startsWith(base)||!path.basename(target).startsWith('rider-checkpoint-')) throw Error('Unsafe test cleanup path');
 fs.rmSync(target,{recursive:true,force:true});
}
test('complete atomic replacement verifies content',()=> {
 const dir=fs.mkdtempSync(path.join(os.tmpdir(),'rider-checkpoint-'));
 const file=path.join(dir,'CHECKPOINT.md');
 try{atomicWrite(file,'old valid checkpoint');atomicWrite(file,'new complete checkpoint');assert.equal(fs.readFileSync(file,'utf8'),'new complete checkpoint');}
 finally{cleanup(dir);}
});
test('interruption before replacement preserves original and complete temporary file',()=> {
 const dir=fs.mkdtempSync(path.join(os.tmpdir(),'rider-checkpoint-'));
 const file=path.join(dir,'CHECKPOINT.md');
 try{atomicWrite(file,'old valid checkpoint');assert.throws(()=>atomicWrite(file,'complete replacement',()=>{throw Error('simulated interruption');}));
 assert.equal(fs.readFileSync(file,'utf8'),'old valid checkpoint');assert.equal(fs.readFileSync(file+'.tmp-'+process.pid,'utf8'),'complete replacement');}
 finally{cleanup(dir);}
});

const {recoverBundle}=require('./checkpoint_recovery.cjs');
function fixture(dir,suffix,id){
 for(const name of ['PROGRESS.md','RESUME.md','CHANGELOG.md','CHECKPOINT.md'])fs.writeFileSync(path.join(dir,name+suffix),'Checkpoint: '+id+'\n');
 fs.writeFileSync(path.join(dir,'checkpoint-state.json'+suffix),JSON.stringify({id}));
}
test('an interrupted bundle restores only coherent previous docs and archives current notes',()=>{
 const dir=fs.mkdtempSync(path.join(os.tmpdir(),'rider-checkpoint-'));
 try{fixture(dir,'.previous','old');fixture(dir,'','new');fs.writeFileSync(path.join(dir,'CHECKPOINT.md'),'incomplete');
 assert.equal(recoverBundle(dir,atomicWrite),true);assert.equal(JSON.parse(fs.readFileSync(path.join(dir,'checkpoint-state.json'),'utf8')).id,'old');
 assert.match(fs.readFileSync(path.join(dir,'RECOVERY_NOTES.md'),'utf8'),/UNKNOWN/);assert.equal(fs.readdirSync(path.join(dir,'interrupted')).length,1);
 }finally{cleanup(dir);}
});
test('invalid previous bundle never replaces current notes',()=>{
 const dir=fs.mkdtempSync(path.join(os.tmpdir(),'rider-checkpoint-'));
 try{fixture(dir,'.previous','old');fs.writeFileSync(path.join(dir,'CHECKPOINT.md.previous'),'mismatch');fs.writeFileSync(path.join(dir,'CHECKPOINT.md'),'keep original');
 assert.throws(()=>recoverBundle(dir,atomicWrite));assert.equal(fs.readFileSync(path.join(dir,'CHECKPOINT.md'),'utf8'),'keep original');
 }finally{cleanup(dir);}
});
test('coherent current bundle is never downgraded to previous notes',()=>{
 const dir=fs.mkdtempSync(path.join(os.tmpdir(),'rider-checkpoint-'));
 try{fixture(dir,'','current');assert.equal(recoverBundle(dir,atomicWrite),false);assert.match(fs.readFileSync(path.join(dir,'CHECKPOINT.md'),'utf8'),/current/);}
 finally{cleanup(dir);}
});

test('cached Laravel configuration blocks tests before any database action',()=>{
 const dir=fs.mkdtempSync(path.join(os.tmpdir(),'rider-checkpoint-'));
 try{const target=path.join(dir,'bootstrap/cache');fs.mkdirSync(target,{recursive:true});fs.writeFileSync(path.join(target,'config.php'),'test fixture');
 assert.throws(()=>require('./integration_checkpoint.cjs').assertNoCachedConfig(dir),/cached configuration/);}
 finally{cleanup(dir);}
});
