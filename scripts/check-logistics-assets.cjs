const { spawnSync } = require('node:child_process');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
for (const [command, args] of [
    [process.execPath, ['--test', 'tests/frontend/data-flow.test.mjs', 'tests/frontend/onboarding.test.mjs']],
    [process.platform === 'win32' ? 'cmd.exe' : 'npm', process.platform === 'win32' ? ['/d', '/c', 'npm run build'] : ['run', 'build']],
]) {
    const result = spawnSync(command, args, { cwd: root, encoding: 'utf8', windowsHide: true, maxBuffer: 8 * 1024 * 1024 });
    process.stdout.write(result.stdout || ''); process.stderr.write(result.stderr || '');
    if (result.error || result.status !== 0) { process.exitCode = 1; break; }
}
