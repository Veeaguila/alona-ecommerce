// Isolated component/browser checks: serves only compiled Vue fixtures, never Laravel or a database.
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { spawn } = require('node:child_process');
const assert = require('node:assert/strict');
const { parse, compileScript } = require('@vue/compiler-sfc');
const root = path.resolve(__dirname, '..');
const artifacts = path.join(root, '.local/logistics-ui', String(Date.now()));
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
function component(file, name) {
    const { descriptor, errors } = parse(fs.readFileSync(path.join(root, 'resources/js', file), 'utf8'));
    assert.equal(errors.length, 0);
    let code = compileScript(descriptor, { id: name, inlineTemplate: true }).content;
    code = code.replace(/import\s+([\s\S]*?)\s+from\s+['"]([^'"]+)['"];?/g, (_, bindings, source) => {
        if (source === 'vue') return 'const ' + bindings.replace(/\bas\b/g, ':') + ' = Vue;';
        if (source === '@inertiajs/vue3') return 'const ' + bindings + ' = mock;';
        return '';
    }).replace('export default', 'return');
    return `const ${name} = (()=>{${code}})();`;
}
const files = [['Components/PartnerField.vue', 'PartnerField'], ['Components/LogisticsAddressSelector.vue', 'LogisticsAddressSelector'],
    ['Pages/Auth/LogisticsRegistration.vue', 'LogisticsRegistration'], ['Pages/Auth/ApplicationStatus.vue', 'ApplicationStatus']];
const compiled = files.map(([file, name]) => component(file, name)).join('\n');
const geography = JSON.parse(fs.readFileSync(path.join(root, 'resources/data/psgc.json')));
const assets = path.join(root, 'public/build/assets');
const css = fs.existsSync(assets) ? fs.readdirSync(assets).filter(name => name.endsWith('.css')).map(name => fs.readFileSync(path.join(assets, name), 'utf8')).join('\n') : '';
const fieldCss = parse(fs.readFileSync(path.join(root, 'resources/js/Components/PartnerField.vue'), 'utf8')).descriptor.styles.map(row => row.content).join('\n');
const server = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://localhost');
    if (url.pathname === '/vue.js') { res.setHeader('Content-Type', 'application/javascript'); res.end(fs.readFileSync(require.resolve('vue/dist/vue.global.js'))); return; }
    if (url.pathname === '/images/Alogo.png') { res.setHeader('Content-Type', 'image/png'); res.end(fs.readFileSync(path.join(root, 'public/images/Alogo.png'))); return; }
    if (url.pathname.startsWith('/api/addresses/')) {
        const level = url.pathname.split('/').pop(), parent = url.searchParams.get('parent'), province = url.searchParams.get('province');
        const key = level === 'barangays' ? 'city_code' : 'region_code';
        const rows = (geography[level] || []).filter(row => (!parent || row[key] === parent) && (province === null || (row.province_code || '') === province));
        res.setHeader('Content-Type', 'application/json'); res.end(JSON.stringify({ data: rows })); return;
    }
    res.setHeader('Content-Type', 'text/html; charset=utf-8');
    res.end(`<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}\n${fieldCss}</style></head><body><div id="app"></div><script src="/vue.js"></script><script>
    const {h,reactive,ref} = Vue; window.posts=[]; window.forms=[];
    window.route = name => '/mock/' + name;
    const Head = {setup:()=>()=>null}; const Link = {props:['href'],setup:(props,{slots})=>()=>h('a',{href:props.href},slots.default?.())};
    const GuestLayout = {setup:(_,{slots})=>()=>h('div',{class:'min-h-screen bg-slate-50'},slots.default?.())};
    const mock = {Head,Link,router:{post:()=>{},on:()=>()=>{}},usePage:()=>({props:{flash:{},errors:{}}}),useForm:initial=>{
      const form=reactive({...initial,errors:{},processing:false,post(url,options){window.posts.push({url});form.processing=true},patch(){},reset(...keys){for(const key of (keys.length?keys:Object.keys(initial)))form[key]=initial[key]}});window.forms.push(form);return form;
    }};
    ${compiled}
    const screen=new URLSearchParams(location.search).get('screen');
    const registrationProps={requestKey:'00000000-0000-4000-8000-000000000001',regions:${JSON.stringify(geography.regions)}};
    const props=screen==='otp'?{name:'Sample Logistics',email:'sample@example.com',role:'sorting_center',status:'unverified',approvalStatus:'pending',verified:false,usesOtp:true}:screen==='resubmit'?{name:'Sample Logistics',email:'sample@example.com',role:'sorting_center',status:'rejected',approvalStatus:'rejected',verified:true,usesOtp:true,reason:'Replace the expired business permit.'}:{...registrationProps,...(screen==='google'?{google:{name:'Sample Google Applicant',email:'google-company@example.com'}}:{})};
    const app=Vue.createApp(['otp','resubmit'].includes(screen)?ApplicationStatus:LogisticsRegistration,props);app.config.globalProperties.route=window.route;app.mount('#app');window.ready=new URLSearchParams(location.search).get('run');
    </script></body></html>`);
});
async function main() {
    fs.mkdirSync(artifacts, { recursive: true });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const base = `http://127.0.0.1:${server.address().port}`;
    const browser = spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', ['--headless=new', '--disable-extensions', '--no-first-run', '--remote-debugging-port=0', '--user-data-dir=' + path.join(artifacts, 'edge-profile'), 'about:blank'], { windowsHide: true, stdio: ['ignore', 'ignore', 'pipe'] });
    let stderr = '', ws;
    browser.stderr.on('data', chunk => { stderr += chunk; });
    try {
        for (let n=0;n<100 && !stderr.includes('DevTools listening on ');n++) await sleep(100);
        const endpoint = stderr.match(/DevTools listening on (ws:\/\/\S+)/)?.[1];
        assert.ok(endpoint, 'Edge debug endpoint unavailable');
        const targets = await fetch(`http://${new URL(endpoint).host}/json/list`).then(r=>r.json());
        ws = new WebSocket(targets.find(row=>row.type==='page').webSocketDebuggerUrl);
        await new Promise((resolve,reject)=>{ws.addEventListener('open',resolve,{once:true});ws.addEventListener('error',reject,{once:true});});
        let id=0; const pending=new Map(), exceptions=[];
        ws.addEventListener('message',event=>{const data=JSON.parse(event.data);if(data.id && pending.has(data.id)){const p=pending.get(data.id);pending.delete(data.id);data.error?p.reject(Error(data.error.message)):p.resolve(data.result)}if(data.method==='Runtime.exceptionThrown')exceptions.push(data.params.exceptionDetails.exception?.description || data.params.exceptionDetails.text)});
        const call=(method,params={})=>new Promise((resolve,reject)=>{const current=++id;pending.set(current,{resolve,reject});ws.send(JSON.stringify({id:current,method,params}));setTimeout(()=>{if(pending.delete(current))reject(Error('CDP timeout: '+method))},10000).unref()});
        const evaluate=async expression=>{const result=await call('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(result.exceptionDetails)throw Error(result.exceptionDetails.exception?.description);return result.result.value};
        await call('Page.enable');await call('Runtime.enable');
        let navigation=0;
        const navigate=async suffix=>{const url=new URL(base+suffix),token=String(++navigation);url.searchParams.set('run',token);await call('Page.navigate',{url:url.href});for(let n=0;n<100;n++){if(await evaluate('window.ready==='+JSON.stringify(token)))return;await sleep(50)}throw Error('Component did not mount: '+JSON.stringify(exceptions))};
        const choose=async (selector,value)=>{await evaluate(`(()=>{const e=document.querySelector(${JSON.stringify(selector)});e.value=${JSON.stringify(value)};e.dispatchEvent(new Event('change',{bubbles:true}));})()`);await sleep(150)};
        await navigate('/');
        assert.equal(await evaluate("document.querySelector('#password').type"), 'password');
        await evaluate("document.querySelector('[aria-controls=password]').click()");
        assert.equal(await evaluate("document.querySelector('#password').type"), 'text');
        await evaluate("document.querySelector('[aria-controls=password]').click()");
        assert.equal(await evaluate("document.querySelector('#password').type"), 'password');
        // TC-LOG-027: actual select events, dependent clearing and filtered region results.
        await choose('#region_code','1300000000');await choose('#province_selection','independent');
        const pasig=geography.cities.find(row=>row.region_code==='1300000000' && row.name.includes('Pasig'));
        await choose('#city_code',pasig.code);
        await choose('#barangay_code',geography.barangays.find(row=>row.city_code===pasig.code).code);
        await choose('#region_code','0700000000');
        assert.deepEqual(await evaluate("['province_selection','city_code','barangay_code'].map(id=>document.getElementById(id).value)"),['','','']);
        const provinceNames=await evaluate("[...document.querySelector('#province_selection').options].map(o=>o.textContent)");
        assert.ok(provinceNames.some(name=>name.includes('Cebu')));assert.ok(provinceNames.some(name=>name.includes('Bohol')));
        assert.ok(!await evaluate(`document.querySelector('#city_code option[value="${pasig.code}"]')`));
        // TC-LOG-003/029: a selected native file must remain available until explicitly cleared.
        const sample=path.join(artifacts,'sample.pdf');fs.writeFileSync(sample,'%PDF-1.4\n% Synthetic browser fixture\n%%EOF');
        const documentNode=await call('DOM.getDocument');
        const fileNode=await call('DOM.querySelector',{nodeId:documentNode.root.nodeId,selector:'#permit_doc'});
        await call('DOM.setFileInputFiles',{nodeId:fileNode.nodeId,files:[sample]});
        assert.equal(await evaluate("document.querySelector('#permit_doc').files.length"),1,'Native selected file was cleared during render');
        assert.equal(await evaluate("window.forms[0].permit_doc?.name"),'sample.pdf');
        await evaluate("window.forms[0].reset('permit_doc')");
        assert.equal(await evaluate("document.querySelector('#permit_doc').files.length"),0,'Reset left the native upload populated');
        // TC-LOG-020: repeated submit events while processing create one outgoing request.
        await evaluate("document.querySelector('form').dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));document.querySelector('form').dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}))");
        assert.equal(await evaluate('window.posts.length'),1);
        await navigate('/?screen=google');
        assert.equal(await evaluate("document.querySelector('#email').value"),'google-company@example.com');
        assert.equal(await evaluate("document.querySelector('#email').disabled"),true);
        assert.equal(await evaluate("document.querySelector('#password') === null"),true);
        const evidence=[];
        for(const width of [320,390,1440]) {
            await call('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
            for(const screen of ['','otp','resubmit']) {
                await navigate(screen?'/?screen='+screen:'/');
                assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'),true, 'Horizontal overflow: '+screen+' '+width);
                const png=await call('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
                const file=`${screen||'registration'}-${width}.png`;fs.writeFileSync(path.join(artifacts,file),Buffer.from(png.data,'base64'));evidence.push({screen:screen||'registration',width,screenshot:file});
                if(screen==='otp'){assert.equal(await evaluate("document.querySelector('#otp').getAttribute('autocomplete')"),'one-time-code');await evaluate("document.querySelector('#otp').value='123456';document.querySelector('#otp').dispatchEvent(new Event('input',{bubbles:true}));document.querySelector('form').dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}))");assert.equal(await evaluate('window.posts[0].url'),'/mock/logistics.otp.verify')}
                if(screen==='resubmit')assert.equal(await evaluate("document.querySelectorAll('input[type=file]').length"),4);
            }
        }
        assert.deepEqual(exceptions,[]);
        fs.writeFileSync(path.join(artifacts,'evidence.json'),JSON.stringify({checks:['TC-LOG-003/029 native upload select/reset','TC-LOG-018','TC-LOG-020','TC-LOG-027','OTP form','resubmission form','Google continuation','responsive 320/390/1440'],evidence,exceptions},null,2));
        console.log(JSON.stringify({result:'PASS',checks:8,screenshots:evidence.length,exceptions:0,artifacts:path.relative(root,artifacts),environment:'isolated Vue component fixture; no application/database/SMTP'}));
        await call('Browser.close').catch(()=>{});
    } finally {ws?.close();browser.kill();server.closeAllConnections();await new Promise(resolve=>server.close(resolve));}
}
main().catch(error=>{console.error(error.stack);process.exitCode=1;server.close();});
