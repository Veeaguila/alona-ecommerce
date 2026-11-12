// Real Vue/Inertia category clicks against isolated fixtures; no application database.
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { spawn } = require('node:child_process');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
const names = ['Action Figures & Collectibles', 'Audio', 'Auto Replacement & Parts', 'Babies & Kids',
    'Baby & Kids Clothing', 'Baby Gear & Nursery', 'Bedding', 'Beverages', 'Board Games & Puzzles',
    'Books & Magazines', 'Cameras', 'Camping & Hiking', 'Speakers', 'Wireless Speakers', 'Headphones'];
const categories = names.map((name, i) => ({ id: i + 1, name, slug: `category-${i}`, parent_id: ({ 12: 2, 13: 13, 14: 2 })[i] ?? null }));
let server, browser, socket;
const entry = `
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import Buyer from '@/Pages/Buyer/Products.vue';
import Guest from '@/Pages/Guest/Products.vue';
import '${path.join(root, 'resources/css/app.css').replaceAll('\\', '/')}';
window.route = (name, id) => '/' + name.replaceAll('.', '/') + (id ? '/' + id : '');
createInertiaApp({ resolve: name => name === 'Buyer/Products' ? Buyer : Guest,
    setup: ({ el, App, props, plugin }) => {
        const app = createApp({ render: () => h(App, props) }).use(plugin);
        app.config.globalProperties.route = window.route;
        app.mount(el); window.ready = true;
    }
});`;

async function main() {
    const { build } = await import('vite');
    const { default: vue } = await import('@vitejs/plugin-vue');
    const result = await build({ configFile: false, logLevel: 'error', plugins: [{
        name: 'category-test-fixtures', enforce: 'pre',
        resolveId(id) {
            if (id === 'virtual:category-test') return '\0category-test';
            if (/\/Layouts\/(Buyer|Guest)Layout\.vue$/.test(id)) return '\0layout';
        },
        load(id) {
            if (id === '\0category-test') return entry;
            if (id === '\0layout') return "import { h } from 'vue'; export default { setup: (_, { slots }) => () => h('div', slots.default?.()) };";
        },
    }, vue()], resolve: { alias: { '@': path.join(root, 'resources/js').replaceAll('\\', '/') } },
    build: { write: false, minify: false, rollupOptions: { input: 'virtual:category-test', output: { format: 'iife' } } } });
    const bundle = result.output.find(file => file.type === 'chunk').code;
    const css = result.output.filter(file => file.type === 'asset' && file.fileName.endsWith('.css')).map(file => file.source).join('\n');
    const requests = [];
    server = http.createServer((req, res) => {
        const url = new URL(req.url, 'http://localhost');
        if (url.pathname === '/app.js') { res.setHeader('Content-Type', 'text/javascript'); res.end(bundle); return; }
        if (url.pathname === '/app.css') { res.setHeader('Content-Type', 'text/css'); res.end(css); return; }
        const category = url.searchParams.get('category') || 'all';
        if (req.headers['x-inertia']) requests.push(url);
        const selected = categories.find(item => item.slug === category);
        const ids = new Set(selected ? [selected.id] : []);
        for (let pass = 0; pass < categories.length; pass++) {
            for (const item of categories) if (ids.has(item.parent_id)) ids.add(item.id);
        }
        const products = categories.filter(item => item.slug !== 'category-6' && (category === 'all' || ids.has(item.id)))
            .map(item => ({ id: item.id, name: `Product for ${item.slug}`, price: 12000, rating: 5, reviews_count: 1, stock: 10 }));
        const page = { component: url.pathname.startsWith('/buyer') ? 'Buyer/Products' : 'Guest/Products',
            props: { categories, products: { data: products, links: [], total: products.length },
                filters: { category, search: url.searchParams.get('search') || '', max_price: url.searchParams.get('max_price'), sort: url.searchParams.get('sort') || 'featured' } },
            url: req.url, version: 'fixture', clearHistory: false, encryptHistory: false };
        if (req.headers['x-inertia']) {
            res.setHeader('X-Inertia', 'true'); res.setHeader('Content-Type', 'application/json'); res.end(JSON.stringify(page)); return;
        }
        res.setHeader('Content-Type', 'text/html');
        res.end(`<html><head><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/app.css"></head><body><div id="app" data-page="${JSON.stringify(page).replaceAll('&', '&amp;').replaceAll('"', '&quot;')}"></div><script src="/app.js"></script></body></html>`);
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const base = `http://127.0.0.1:${server.address().port}`;
    const executable = process.env.CATALOG_TEST_BROWSER || ['C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', 'C:/Program Files/Microsoft/Edge/Application/msedge.exe', '/usr/bin/chromium'].find(file => fs.existsSync(file));
    assert.ok(executable, 'Set CATALOG_TEST_BROWSER to Edge or Chromium');
    const profile = fs.mkdtempSync(path.join(require('node:os').tmpdir(), 'alona-category-browser-'));
    browser = spawn(executable, ['--headless=new', '--disable-extensions', '--no-first-run', '--remote-debugging-port=0', '--user-data-dir=' + profile, 'about:blank'], { windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
    let stderr = '';
    browser.stderr.on('data', data => { stderr += data; });
    for (let i = 0; i < 100 && !stderr.includes('DevTools listening on '); i++) await sleep(100);
    const endpoint = stderr.match(/DevTools listening on (ws:\/\/\S+)/)?.[1];
    assert.ok(endpoint, 'Browser did not start');
    const targets = await fetch(`http://${new URL(endpoint).host}/json/list`).then(r => r.json());
    socket = new WebSocket(targets.find(target => target.type === 'page').webSocketDebuggerUrl);
    await new Promise((resolve, reject) => { socket.addEventListener('open', resolve, { once: true }); socket.addEventListener('error', reject, { once: true }); });
    let id = 0;
    const pending = new Map(), exceptions = [];
    socket.addEventListener('message', event => {
        const data = JSON.parse(event.data);
        if (pending.has(data.id)) { const task = pending.get(data.id); pending.delete(data.id); data.error ? task.reject(Error(data.error.message)) : task.resolve(data.result); }
        if (data.method === 'Runtime.exceptionThrown') exceptions.push(data.params.exceptionDetails.text);
    });
    const call = (method, params = {}) => new Promise((resolve, reject) => {
        const key = ++id; pending.set(key, { resolve, reject }); socket.send(JSON.stringify({ id: key, method, params }));
        setTimeout(() => { if (pending.delete(key)) reject(Error('Timeout: ' + method)); }, 10000).unref();
    });
    const evaluate = async expression => {
        const response = await call('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
        if (response.exceptionDetails) throw Error(response.exceptionDetails.exception?.description);
        return response.result.value;
    };
    const until = async expression => { for (let i = 0; i < 100; i++) { if (await evaluate(expression)) return; await sleep(50); } throw Error('Condition timed out: ' + expression); };
    await call('Runtime.enable');
    await call('Page.bringToFront');
    await call('Emulation.setFocusEmulationEnabled', { enabled: true });
    const bar = 'document.querySelector(\'[aria-label="Filter products by category"]\')';
    let clicks = 0;
    for (const width of [1440, 390]) {
        await call('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width === 390 });
        for (const role of ['guest', 'buyer']) {
            await call('Page.navigate', { url: `${base}/${role}/products?search=Product&sort=price-low` });
            await until(`window.ready && location.pathname === '/${role}/products' && !location.search.includes('category=') && ${bar}?.querySelectorAll('button').length === ${categories.length + 1}`);
            for (let index = 0; index < categories.length; index++) {
                const slug = categories[index].slug;
                await evaluate(`${bar}.querySelectorAll('button')[${index + 1}].click()`);
                await until(`location.search.includes('category=${slug}') && ${bar}.querySelectorAll('button')[${index + 1}].getAttribute('aria-pressed') === 'true'`);
                assert.equal(await evaluate(`document.body.textContent.includes(${JSON.stringify(index === 6 ? 'No products available.' : `Product for ${slug}`)})`), true);
                assert.equal(await evaluate(`(()=>{const b=${bar}, s=b.querySelector('[aria-pressed="true"]');const x=b.getBoundingClientRect(), y=s.getBoundingClientRect();return y.left>=x.left-1 && y.right<=x.right+1})()`), true, `${role}: selected tab remains visible at ${width}px`);
                const query = requests.at(-1).searchParams;
                assert.equal(requests.at(-1).pathname, `/${role}/products`);
                assert.equal(query.get('search'), 'Product'); assert.equal(query.get('sort'), 'price-low'); assert.equal(query.has('max_price'), false);
                clicks++;
            }
            await evaluate(`${bar}.querySelector('button').click()`);
            await until(`location.search.includes('category=all') && ${bar}.querySelector('button').getAttribute('aria-pressed') === 'true'`);
            assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true, 'No page-wide horizontal overflow');
            await evaluate(`${bar}.querySelectorAll('button')[2].focus()`);
            assert.equal(await evaluate(`document.activeElement === ${bar}.querySelectorAll('button')[2]`), true);
            await call('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Enter', code: 'Enter', text: '\r', unmodifiedText: '\r', windowsVirtualKeyCode: 13 });
            await call('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13 });
            await until("location.search.includes('category=category-1')");
            assert.equal(await evaluate("document.body.textContent.includes('Product for category-13')"), true, 'Parent includes nested subcategory');
            await evaluate(`${bar}.querySelectorAll('button')[13].click()`);
            await until(`${bar}.querySelectorAll('button')[13].getAttribute('aria-pressed') === 'true'`);
            assert.equal(await evaluate("document.body.textContent.includes('Product for category-13')"), true, 'Subcategory includes its child');
            assert.equal(await evaluate("document.body.textContent.includes('Product for category-14')"), false, 'Sibling products excluded');
        }
    }
    assert.deepEqual(exceptions, []);
    console.log(JSON.stringify({ result: 'passed', categoryClicks: clicks, audiences: ['guest', 'buyer'], widths: [1440, 390], checks: ['All reset', 'empty category', 'selected tab visibility', 'keyboard Enter', 'search/sort retained', 'no hidden price cap', 'no horizontal page overflow', 'nested subcategory selection', 'sibling exclusion'], environment: 'isolated HTTP fixtures; no application database' }));
    await call('Browser.close').catch(() => {});
}
main().catch(error => { console.error(error.stack); process.exitCode = 1; }).finally(async () => {
    socket?.close(); browser?.kill(); server?.closeAllConnections();
    if (server) await new Promise(resolve => server.close(resolve));
});
