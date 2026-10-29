'use strict';
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

// Run the Logistics upgrade regressions without connecting to the developer's database.
const root = path.resolve(__dirname, '..');
const filter = process.argv[2];
if (process.argv.length > 3 || (filter && !/^--filter=[A-Za-z0-9_]+$/.test(filter))) {
    throw Error('Usage: node scripts/verify-logistics.cjs [--filter=TestClassName]');
}
const artifacts = path.join(root, '.local/logistics-verification');
const configCache = path.join(root, 'bootstrap/cache/config.php');
if (fs.existsSync(configCache) || (process.env.APP_CONFIG_CACHE && fs.existsSync(process.env.APP_CONFIG_CACHE))) {
    throw Error('Cached application configuration detected. Inspect it separately; this verifier will not clear or use it.');
}
fs.mkdirSync(artifacts, { recursive: true });
const unusedCache = path.join(artifacts, 'unused-config-cache.php');
if (fs.existsSync(unusedCache)) throw Error('Unexpected verifier config cache. Inspect it before running tests.');
const xml = value => value.replaceAll('&', '&amp;').replaceAll('"', '&quot;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
const relativeFiles = ['tests/Feature/PartnerOnboardingTest.php', 'tests/Feature/Auth/AuthenticationTest.php',
    'tests/Feature/Auth/LocalSessionCookieTest.php', 'tests/Feature/Auth/GoogleOnboardingTest.php',
    'tests/Feature/SellerFulfillmentWaybillAndPickupTest.php'];
const suites = '<testsuites><testsuite name="Logistics and shared workflow regressions">'
    + ['tests/Feature/Logistics', 'tests/Unit/Logistics'].map(folder => `<directory>${xml(path.join(root, folder))}</directory>`).join('')
    + relativeFiles.map(file => `<file>${xml(path.join(root, file))}</file>`).join('') + '</testsuite></testsuites>';
const config = fs.readFileSync(path.join(root, 'phpunit.xml'), 'utf8')
    .replace(/<testsuites>[\s\S]*?<\/testsuites>/, suites)
    .replace('bootstrap="vendor/autoload.php"', `bootstrap="${xml(path.join(root, 'vendor/autoload.php'))}"`)
    .replace('<directory>app</directory>', `<directory>${xml(path.join(root, 'app'))}</directory>`);
const configFile = path.join(artifacts, 'phpunit.xml');
fs.writeFileSync(configFile, config);
const result = spawnSync('php', ['-d', 'memory_limit=1024M', 'vendor/phpunit/phpunit/phpunit',
    '--configuration', configFile, '--do-not-cache-result', '--log-junit', path.join(artifacts, 'results.xml'), ...(filter ? [filter] : [])], {
    cwd: root, stdio: 'inherit', windowsHide: true,
    env: { ...process.env, APP_ENV: 'testing', APP_CONFIG_CACHE: unusedCache,
        DB_CONNECTION: 'sqlite', DB_DATABASE: ':memory:', DB_URL: '',
        CACHE_STORE: 'array', SESSION_DRIVER: 'array', MAIL_MAILER: 'array', QUEUE_CONNECTION: 'sync' },
});
if (result.error) throw result.error;
process.exitCode = result.status ?? 1;
