import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import http from 'node:http';
import { fileURLToPath } from 'node:url';
import { createServer } from 'vite';

test('Vite blocks local recovery files and SQL dumps through direct and /@fs/ URLs', async () => {
    const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
    const scratch = fs.mkdtempSync(path.join(os.tmpdir(), 'alona-vite-policy-'));
    const probeId = path.basename(scratch);
    const folders = ['.local', '.recovery', '.checkpoints', 'tests'].map(parent => path.join(root, parent, probeId));
    const marker = 'Synthetic policy fixture; contains no application data.\n';
    const files = folders.map((folder, index) => path.join(folder, index === 3 ? 'probe.sql' : 'probe.txt'));
    files.push(path.join(folders[3], 'probe.sql.gz'), path.join(folders[3], '.env.backup'));
    let vite, server;
    const previous = { VITEST: process.env.VITEST, LARAVEL_BYPASS_ENV_CHECK: process.env.LARAVEL_BYPASS_ENV_CHECK };
    try {
        for (const file of files) { fs.mkdirSync(path.dirname(file), { recursive: true }); fs.writeFileSync(file, marker); }
        fs.writeFileSync(path.join(folders[3], 'public.txt'), marker);
        // Disable hot-file side effects and environment reads, retaining the actual source configuration.
        process.env.VITEST = '1';
        process.env.LARAVEL_BYPASS_ENV_CHECK = '1';
        vite = await createServer({ root, configFile: path.join(root, 'vite.config.js'), configLoader: 'native',
            envFile: false, cacheDir: path.join(scratch, 'cache'), publicDir: false, logLevel: 'silent',
            optimizeDeps: { noDiscovery: true, include: [] }, server: { middlewareMode: true, hmr: false, watch: null } });
        server = http.createServer((request, response) => vite.middlewares(request, response, () => { response.statusCode = 404; response.end(); }));
        await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
        const base = `http://127.0.0.1:${server.address().port}`;
        for (const file of files) {
            const relative = path.relative(root, file).replaceAll('\\', '/');
            for (const url of ['/' + relative, '/@fs/' + file.replaceAll('\\', '/')]) {
                const response = await fetch(base + url, { signal: AbortSignal.timeout(10000) });
                assert.equal(response.status, 403, `Private fixture must be denied: ${relative}`);
                assert.ok(!(await response.text()).includes(marker));
            }
        }
        const normal = await fetch(base + '/tests/' + probeId + '/public.txt', { signal: AbortSignal.timeout(10000) });
        assert.equal(normal.status, 200, 'Application modules must still be served');
    } finally {
        if (server) { server.closeAllConnections(); await new Promise(resolve => server.close(resolve)); }
        if (vite) await vite.close();
        for (const [key, value] of Object.entries(previous)) value === undefined ? delete process.env[key] : process.env[key] = value;
        for (const folder of [...folders, scratch]) {
            const resolved = path.resolve(folder);
            assert.ok(path.basename(resolved) === probeId && (resolved.startsWith(root + path.sep) || resolved.startsWith(path.resolve(os.tmpdir()) + path.sep)), 'Cleanup is restricted to generated fixture directories');
            fs.rmSync(resolved, { recursive: true, force: true });
        }
    }
});
