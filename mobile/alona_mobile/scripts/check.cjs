'use strict';
const {spawnSync} = require('node:child_process');
const path = require('node:path');
const options = {
  analyze: ['analyze','--no-pub'],
  test: ['test','--no-pub'],
  'build-web': ['build','web','--profile','--no-pub','--no-wasm-dry-run','--no-web-resources-cdn','--dart-define=API_BASE_URL=http://127.0.0.1:18973','--base-href=/alona-app/'],
};
const args = options[process.argv[2]];
if (!args || process.argv.length !== 3) throw Error('Usage: node scripts/check.cjs analyze|test|build-web');
const windows = process.platform === 'win32';
const result = spawnSync(windows ? 'cmd.exe' : 'flutter', windows ? ['/d','/c','flutter '+args.join(' ')] : args,
  {cwd:path.resolve(__dirname,'..'),env:process.env,stdio:'inherit',windowsHide:true});
if (result.error) console.error(result.error.message);
process.exitCode = result.status === 0 ? 0 : 1;
