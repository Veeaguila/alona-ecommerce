'use strict';
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

// Existing-database safety: PHPUnit gets a fresh SQLite :memory: database only.
const root = path.resolve(__dirname, '..');
const options = process.argv.slice(2);
if (options.some(arg => arg !== '--mobile' && !/^--backend-batches=[1-9]\d*(,[1-9]\d*)*$/.test(arg)) ||
    options.filter(arg => arg.startsWith('--backend-batches=')).length > 1) {
    throw Error('Usage: node scripts/verify-system.cjs [--mobile] [--backend-batches=4,9]');
}
const selection = options.find(arg => arg.startsWith('--backend-batches='));
const batches = selection ? new Set(selection.split('=')[1].split(',').map(Number)) : null;
if (fs.existsSync(path.join(root, 'bootstrap/cache/config.php')) ||
    (process.env.APP_CONFIG_CACHE && fs.existsSync(process.env.APP_CONFIG_CACHE))) {
    throw Error('Cached Laravel configuration detected. Inspect it separately; this check will not clear it.');
}
const artifacts = path.join(root, '.local/system-verification');
fs.mkdirSync(artifacts, { recursive: true });
const configCache = path.join(artifacts, 'unused-config-cache.php');
if (fs.existsSync(configCache)) throw Error('Unexpected verification config cache; inspect before running.');
const env = { ...process.env, APP_ENV: 'testing', APP_CONFIG_CACHE: configCache,
    DB_CONNECTION: 'sqlite', DB_DATABASE: ':memory:', DB_URL: '',
    MAIL_MAILER: 'array', QUEUE_CONNECTION: 'sync', CACHE_STORE: 'array', SESSION_DRIVER: 'array' };
const results = [];
function run(label, command, args, cwd = root) {
    const operation = { label, command: [command, ...args], cwd, result: 'RUNNING; outcome UNKNOWN' };
    fs.writeFileSync(path.join(artifacts, 'operation.json'), JSON.stringify({ operation, results }, null, 2));
    console.log('Checking ' + label);
    const result = spawnSync(command, args, { cwd, env, windowsHide: true, encoding: 'utf8', maxBuffer: 24 * 1024 * 1024 });
    const output = (result.stdout || '') + (result.stderr || '') + (result.error?.message || '');
    fs.writeFileSync(path.join(artifacts, label + '.txt'), output);
    results.push({ ...operation, result: result.status === 0 ? 'PASS' : 'FAIL/UNKNOWN', status: result.status });
    fs.writeFileSync(path.join(artifacts, 'operation.json'), JSON.stringify({ operation: null, results }, null, 2));
    console.log(output.slice(-2500));
}
const files = [];
function walk(dir) {
    for (const entry of fs.readdirSync(path.join(root, dir), { withFileTypes: true })) {
        const file = dir + '/' + entry.name;
        if (entry.isDirectory()) walk(file);
        else if (file.endsWith('Test.php')) files.push(file);
    }
}
walk('tests/Unit'); walk('tests/Feature');
if (batches && [...batches].some(batch => batch > Math.ceil(files.length / 12))) throw Error('Unknown backend batch.');
const xml = value => value.replaceAll('&', '&amp;').replaceAll('"', '&quot;');
const original = fs.readFileSync(path.join(root, 'phpunit.xml'), 'utf8');
// Some AI HTTP handlers set a process-wide PHP timer. Separate batches keep that
// request timer from terminating unrelated later tests in one very long process.
for (let i = 0; i < files.length; i += 12) {
    if (batches && !batches.has(i / 12 + 1)) continue;
    const label = 'backend-' + (i / 12 + 1);
    const suite = '<testsuites><testsuite name="Memory-only system check">' + files.slice(i, i + 12)
        .map(file => '<file>' + xml(path.join(root, file)) + '</file>').join('') + '</testsuite></testsuites>';
    const config = original.replace(/<testsuites>[\s\S]*?<\/testsuites>/, suite)
        .replace('bootstrap="vendor/autoload.php"', 'bootstrap="' + xml(path.join(root, 'vendor/autoload.php')) + '"')
        .replace('<directory>app</directory>', '<directory>' + xml(path.join(root, 'app')) + '</directory>');
    const target = path.join(artifacts, label + '.xml');
    fs.writeFileSync(target, config);
    run(label, 'php', ['-d', 'memory_limit=1024M', 'vendor/phpunit/phpunit/phpunit', '--configuration', target,
        '--do-not-cache-result', '--colors=never', '--log-junit', path.join(artifacts, label + '-results.xml')]);
}
// Bound worker load: concurrent Vite fixtures timed out on Windows while the same
// security assertions and complete suite passed serially. Keep every check enabled.
run('frontend', 'node', ['--test', '--test-concurrency=1', ...fs.readdirSync(path.join(root, 'tests/frontend'))
    .filter(file => file.endsWith('.test.mjs')).map(file => 'tests/frontend/' + file)]);
run('checkpoint-helper', 'node', ['--test', 'mobile/rider/scripts/integration_checkpoint.test.cjs']);
if (process.argv.includes('--mobile')) {
    const app = path.join(root, 'mobile/rider');
    const flutter = (label, args) => process.platform === 'win32'
        ? run(label, 'cmd.exe', ['/d', '/c', 'flutter ' + args.join(' ')], app)
        : run(label, 'flutter', args, app);
    flutter('mobile-tests', ['test', '--no-pub', '--dart-define=API_BASE_URL=http://localhost:8000/api']);
    flutter('mobile-analysis', ['analyze', '--no-pub']);
    const combined = path.join(root, 'mobile/alona_mobile');
    run('buyer-logistics-tests', 'node', ['scripts/check.cjs','test'], combined);
    run('buyer-logistics-analysis', 'node', ['scripts/check.cjs','analyze'], combined);
}
process.exitCode = results.every(result => result.status === 0) ? 0 : 1;
