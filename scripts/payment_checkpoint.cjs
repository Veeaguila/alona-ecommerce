const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const cp = require('node:child_process');
const root = path.resolve(__dirname, '..');
const message = process.argv.slice(2).join(' ');
if (!message) throw new Error('A phase, running command, or confirmed outcome is required.');
const paths = cp.execFileSync('git', ['ls-files', '--modified', '--others', '--exclude-standard'], { cwd: root, encoding: 'utf8' }).trim().split(/\r?\n/).filter(Boolean);
const hashes = {};
for (const file of paths) {
  if (file.startsWith('docs/payment-checkpoints/')) continue;
  const target = path.join(root, file);
  if (fs.existsSync(target) && fs.statSync(target).isFile()) hashes[file] = crypto.createHash('sha256').update(fs.readFileSync(target)).digest('hex');
}
const dir = path.join(root, 'docs/payment-checkpoints');
fs.mkdirSync(dir, { recursive: true });
const record = { time: new Date().toISOString(), message, hashes };
fs.appendFileSync(path.join(dir, 'history.jsonl'), JSON.stringify(record) + '\n');
fs.writeFileSync(path.join(dir, 'CURRENT.json'), JSON.stringify(record, null, 2) + '\n');
console.log(message);
