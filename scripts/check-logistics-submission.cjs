// Real Vue/Inertia/Axios transport against an isolated HTTP fixture, never Laravel/MySQL.
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { spawn } = require('node:child_process');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const artifacts = path.join(root, '.local/logistics-submit', String(Date.now()));
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
const geography = JSON.parse(fs.readFileSync(path.join(root, 'resources/data/psgc.json')));
const registrationProps = { requestKey: '00000000-0000-4000-8000-000000000001', regions: geography.regions, errors: {}, auth: {}, flash: {} };
let mode = 'success', requests = [], bundle, server;
const page = (component, props, url) => ({ component, props, url, version: 'fixture', clearHistory: false, encryptHistory: false });
const entry = `
import {createApp,h} from 'vue';
import {createInertiaApp,router} from '@inertiajs/vue3';
import axios from 'axios';
import Registration from '@/Pages/Auth/LogisticsRegistration.vue';
import Status from '@/Pages/Auth/ApplicationStatus.vue';
window.route=(name)=>({'register.logistics':'/register/logistics','application.status':'/application/status','logistics.otp.verify':'/logistics/verify-email','logistics.otp.resend':'/logistics/resend-code'}[name]||'/fixture/'+name);
window.failures=[];window.addEventListener('unhandledrejection',event=>window.failures.push(String(event.reason?.stack||event.reason)));
window.testRouter=router;window.testAxios=axios;
createInertiaApp({resolve:name=>name==='Auth/LogisticsRegistration'?Registration:Status,setup:({el,App,props,plugin})=>{const app=createApp({render:()=>h(App,props)}).use(plugin);app.config.globalProperties.route=window.route;app.mount(el);window.ready=true;}});
`;
async function main() {
    fs.mkdirSync(artifacts, { recursive: true });
    const { build } = await import('vite');
    const { default: vue } = await import('@vitejs/plugin-vue');
    const result = await build({ configFile: false, logLevel: 'error', plugins: [vue(), {
        name: 'isolated-registration-entry', resolveId: id => id === 'virtual:registration-test' ? '\0registration-test' : undefined,
        load: id => id === '\0registration-test' ? entry : undefined,
    }], resolve: { alias: { '@': path.join(root, 'resources/js') } }, build: { write: false, minify: false,
        rollupOptions: { input: 'virtual:registration-test', output: { format: 'iife' } } } });
    bundle = result.output.find(file => file.type === 'chunk').code;
    server = http.createServer(async (req, res) => {
        const url = new URL(req.url, 'http://localhost');
        if (url.pathname === '/app.js') { res.setHeader('Content-Type', 'application/javascript'); res.end(bundle); return; }
        if (url.pathname.startsWith('/images/')) { res.statusCode = 204; res.end(); return; }
        if (url.pathname.startsWith('/api/addresses/')) {
            const level = url.pathname.split('/').pop(), parent = url.searchParams.get('parent'), province = url.searchParams.get('province');
            const key = level === 'barangays' ? 'city_code' : 'region_code';
            const data = (geography[level] || []).filter(row => (!parent || row[key] === parent) && (province === null || (row.province_code || '') === province));
            res.setHeader('Content-Type', 'application/json'); res.end(JSON.stringify({ data })); return;
        }
        if (req.method === 'POST') {
            const chunks = []; for await (const chunk of req) chunks.push(chunk);
            requests.push({ url: url.pathname, headers: req.headers, body: Buffer.concat(chunks).toString() });
            if (mode === 'hang') return; // Intentionally pending; the page must cancel and leave loading.
            if (mode === 'disconnect') {
                // Start a response before disconnecting so Chromium does not transparently retry a stale socket.
                res.writeHead(200, { 'Content-Type': 'text/plain', 'Content-Length': '1000' });
                res.write('interrupted'); setTimeout(() => res.destroy(), 20); return;
            }
            if (['expired', 'oversized', 'server-error', 'throttled'].includes(mode)) {
                res.statusCode = {expired:419,oversized:413,'server-error':500,throttled:429}[mode];
                res.setHeader('Content-Type', 'text/html'); res.end('<h1>Fixture server error</h1>'); return;
            }
            if (mode === 'validation') {
                res.writeHead(303, { Location: '/register/logistics?invalid=1' }); res.end(); return;
            }
            res.writeHead(303, { Location: '/application/status' }); res.end(); return;
        }
        const data = url.pathname === '/application/status'
            ? page('Auth/ApplicationStatus', { ...registrationProps, name: 'Example Logistics', email: 'fixture@example.com', role: 'sorting_center', status: 'unverified', approvalStatus: 'pending', verified: false, usesOtp: true }, url.pathname)
            : page('Auth/LogisticsRegistration', { ...registrationProps, errors: url.searchParams.has('invalid') ? { tin: 'The TIN has already been taken.', 'service_region_codes.0': 'Choose a valid service region.' } : {} }, url.pathname);
        if (req.headers['x-inertia']) { res.writeHead(200, { 'Content-Type': 'application/json', 'X-Inertia': 'true' }); res.end(JSON.stringify(data)); return; }
        res.setHeader('Content-Type', 'text/html; charset=utf-8');
        // Fixture CSRF cookie is exercised by the real Axios adapter. PHP tests cover Laravel CSRF.
        res.setHeader('Set-Cookie', 'XSRF-TOKEN=fixture-token; Path=/; SameSite=Lax');
        res.end(`<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><div id="app" data-page="${JSON.stringify(data).replace(/&/g,'&amp;').replace(/"/g,'&quot;')}"></div><script src="/app.js"></script></body></html>`);
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const base = `http://127.0.0.1:${server.address().port}`;
    const browser = spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', ['--headless=new','--disable-extensions','--no-first-run','--remote-debugging-port=0','--user-data-dir='+path.join(artifacts,'edge-profile'),'about:blank'], { windowsHide: true, stdio: ['ignore','ignore','pipe'] });
    let stderr = '', ws;
    browser.stderr.on('data', chunk => { stderr += chunk; });
    try {
        for(let n=0;n<100&&!stderr.includes('DevTools listening on ');n++) await sleep(100);
        const endpoint=stderr.match(/DevTools listening on (ws:\/\/\S+)/)?.[1]; assert.ok(endpoint,'Edge did not start');
        const targets=await fetch(`http://${new URL(endpoint).host}/json/list`).then(r=>r.json());
        ws=new WebSocket(targets.find(row=>row.type==='page').webSocketDebuggerUrl);
        await new Promise((resolve,reject)=>{ws.addEventListener('open',resolve,{once:true});ws.addEventListener('error',reject,{once:true})});
        let id=0; const pending=new Map(),exceptions=[];
        ws.addEventListener('message',event=>{const data=JSON.parse(event.data);if(pending.has(data.id)){const p=pending.get(data.id);pending.delete(data.id);data.error?p.reject(Error(data.error.message)):p.resolve(data.result)}if(data.method==='Runtime.exceptionThrown')exceptions.push(data.params.exceptionDetails.exception?.description||data.params.exceptionDetails.text)});
        const call=(method,params={})=>new Promise((resolve,reject)=>{const key=++id;pending.set(key,{resolve,reject});ws.send(JSON.stringify({id:key,method,params}));setTimeout(()=>{if(pending.delete(key))reject(Error('CDP timeout: '+method))},10000).unref()});
        const evaluate=async expression=>{const r=await call('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description);return r.result.value};
        const until=async (expression,label)=>{for(let n=0;n<100;n++){if(await evaluate(expression))return;await sleep(50)}throw Error(label+'; '+JSON.stringify({exceptions,failures:await evaluate('window.failures'),requests:requests.length,state:await evaluate('document.querySelector("main button[type=submit]")?.textContent')}))};
        await call('Page.enable');await call('Runtime.enable');
        const navigate=async()=>{await evaluate('window.ready=false');await call('Page.navigate',{url:base+'/register/logistics?run='+Date.now()});await until('window.ready && !!document.querySelector("#business_name")','mount')};
        const set=async(selector,value,event='input')=>evaluate(`(()=>{const el=document.querySelector(${JSON.stringify(selector)});el.value=${JSON.stringify(value)};el.dispatchEvent(new Event(${JSON.stringify(event)},{bubbles:true}))})()`);
        const file=path.join(artifacts,'sample.pdf');fs.writeFileSync(file,'%PDF-1.4\n% Browser test fixture\n%%EOF');
        const fill=async()=>{
            for(const [name,value]of Object.entries({business_name:'Example Logistics',registration_number:'CS123456789',tin:'123-456-789-000',first_name:'Ana',last_name:'Cruz',position:'Manager',email:'fixture@example.com',contact_no:'09171234567',street:'123 Example Street',postal_code:'1600',password:'StrongPass1!',password_confirmation:'StrongPass1!'})) await set('#'+name,value);
            await set('#region_code','1300000000','change');await until('document.querySelector("#province_selection option[value=independent]")','province options');
            await set('#province_selection','independent','change');const city=geography.cities.find(row=>row.region_code==='1300000000'&&row.name.includes('Pasig'));
            await until(`document.querySelector('#city_code option[value="${city.code}"]')`,'city options');await set('#city_code',city.code,'change');
            const barangay=geography.barangays.find(row=>row.city_code===city.code);await until(`document.querySelector('#barangay_code option[value="${barangay.code}"]')`,'barangay options');await set('#barangay_code',barangay.code,'change');
            await evaluate(`document.querySelector('input[type=checkbox][value="1300000000"]').click();[...document.querySelectorAll('main input[type=checkbox][required]')].forEach(el=>el.click())`);
            const dom=await call('DOM.getDocument');for(const name of ['sec_doc','bir_doc','permit_doc','id_doc']){const node=await call('DOM.querySelector',{nodeId:dom.root.nodeId,selector:'#'+name});await call('DOM.setFileInputFiles',{nodeId:node.nodeId,files:[file]})}
            assert.equal(await evaluate('document.querySelector("main form").checkValidity()'),true,'Fixture must satisfy native validation');
        };
        const checks=[];
        await navigate();
        await evaluate('document.querySelector("main form").requestSubmit()');
        assert.equal(requests.length,0,'Native invalid form must not post');
        assert.match(await evaluate('document.querySelector("main [role=alert]").textContent'),/required fields/);
        assert.equal(await evaluate('document.querySelector("main button[type=submit]").disabled'),false);
        checks.push('native invalid fields show feedback without loading or POST');
        await fill();
        await evaluate('document.querySelector("main form").requestSubmit()');
        await until('location.pathname==="/application/status" && !!document.querySelector("#otp")','valid submit must open OTP');
        assert.equal(requests.length,1);assert.equal(requests[0].url,'/register/logistics');
        assert.match(requests[0].headers['content-type'],/multipart\/form-data; boundary=/);assert.equal(requests[0].headers['x-xsrf-token'],'fixture-token');
        for(const field of ['request_key','tin','service_region_codes[0]','sec_doc','bir_doc','permit_doc','id_doc','terms','privacy']) assert.ok(requests[0].body.includes(`name="${field}"`),'Missing multipart field '+field);
        console.log('PASS real multipart POST, CSRF header, correct endpoint and OTP redirect');
        checks.push('real multipart POST, CSRF header, four files, correct route and OTP redirect');
        for(const [scenario,message]of [['validation',/TIN has already been taken/],['expired',/session expired/],['oversized',/upload limits/],['throttled',/Too many attempts/],['server-error',/server could not complete/],['disconnect',/Check your connection/]]) {
            mode=scenario;await navigate();await fill();
            const before=requests.length;
            await evaluate('document.querySelector("main form").requestSubmit();document.querySelector("main form").requestSubmit()');
            await until('!!document.querySelector("main [role=alert]") && !document.querySelector("main button[type=submit]").disabled',scenario+' error and loading reset');
            assert.match(await evaluate('document.querySelector("main [role=alert]").textContent'),message);
            assert.equal(requests.length,before+1,'Double submit created multiple requests: '+scenario);
            assert.equal(await evaluate('document.activeElement === document.querySelector("main [role=alert]")'),true,'Error summary was not focused');
            if(scenario==='validation') {
                assert.equal(await evaluate('document.querySelector("#tin").getAttribute("aria-invalid")'),'true');
                assert.ok(await evaluate('document.querySelector("main").textContent.includes("Choose a valid service region.")'));
                assert.equal(await evaluate('document.querySelector("#permit_doc").files.length'),0);
            }
            checks.push(scenario+' displays error, resets loading, guards duplicate clicks');
        }
        mode='success';await navigate();await fill();
        const before=requests.length;
        await evaluate(`window.testAxios.interceptors.request.use(()=>{throw Error('Injected pre-network adapter failure')});document.querySelector('main form').requestSubmit()`);
        await until('!!document.querySelector("main [role=alert]") && !document.querySelector("main button[type=submit]").disabled','pre-network exception');
        assert.equal(requests.length,before,'Injected pre-network failure unexpectedly sent a POST');
        assert.match(await evaluate('document.querySelector("main [role=alert]").textContent'),/Check your connection/);
        checks.push('actual Axios pre-network exception shows feedback and resets loading');
        await navigate();await fill();
        await evaluate(`window.route=()=>{throw Error('Injected route failure')};document.querySelector('main form').requestSubmit()`);
        await until('!!document.querySelector("main [role=alert]") && !document.querySelector("main button[type=submit]").disabled','synchronous start exception');
        assert.match(await evaluate('document.querySelector("main [role=alert]").textContent'),/could not start/);
        checks.push('synchronous route exception shows feedback without stuck loading');
        mode='hang';await navigate();await fill();
        const beforeHang=requests.length;
        await evaluate('document.querySelector("main form").requestSubmit()');
        for(let n=0;n<100&&requests.length===beforeHang;n++)await sleep(20);
        assert.equal(requests.length,beforeHang+1);
        await call('Emulation.setVirtualTimePolicy',{policy:'advance',budget:61000});
        await until('!!document.querySelector("main [role=alert]") && !document.querySelector("main button[type=submit]").disabled','stalled-request recovery');
        assert.match(await evaluate('document.querySelector("main [role=alert]").textContent'),/taking longer than expected/);
        checks.push('pending request times out, cancels, resets loading and explains how to recover');
        fs.writeFileSync(path.join(artifacts,'evidence.json'),JSON.stringify({checks,exceptions,environment:'real Inertia/Axios browser; isolated HTTP fixture; no application database'},null,2));
        assert.deepEqual(exceptions,[]);assert.deepEqual(await evaluate('window.failures'),[]);
        console.log(JSON.stringify({result:'PASS',checks:checks.length,artifacts:path.relative(root,artifacts),environment:'isolated HTTP fixture; real Vue/Inertia/Axios; no Laravel database or SMTP'}));
        await call('Browser.close').catch(()=>{});
    } finally {ws?.close();browser.kill();server.closeAllConnections();await new Promise(resolve=>server.close(resolve))}
}
main().catch(error=>{console.error(error.stack);process.exitCode=1;server?.closeAllConnections();server?.close()});
