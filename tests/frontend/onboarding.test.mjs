import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { compileScript, parse } from '@vue/compiler-sfc';
import * as Vue from 'vue';

function setup(file, props, dependencies = {}) {
    const source = readFileSync(new URL(`../../resources/js/${file}`, import.meta.url), 'utf8');
    const { descriptor } = parse(source);
    const script = compileScript(descriptor, { id: 'test-onboarding' }).content.replace(/^import .*\r?\n/gm, '').replace('export default', 'return');
    const mounted = [];
    const scope = Vue.effectScope();
    const globals = { computed: Vue.computed, reactive: Vue.reactive, ref: Vue.ref, watch: Vue.watch, nextTick: Vue.nextTick, useId: () => 'test-address', onMounted: fn => mounted.push(fn), onUnmounted: () => {}, ...dependencies };
    const component = new Function(...Object.keys(globals), script)(...Object.values(globals));
    const state = scope.run(() => component.setup(props, { expose: () => {}, emit: () => {} }));
    return { state, mounted, stop: () => scope.stop() };
}
function deferred() { let resolve; const promise = new Promise(done => { resolve = done; }); return { promise, resolve }; }
const response = rows => ({ ok: true, json: async () => ({ data: rows }) });

function address(fetch) {
    const form = Vue.reactive({ address_group: '', region_code: '', province_code: '', city_code: '', barangay_code: '', errors: {} });
    return { ...setup('Components/PhilippineAddressSelector.vue', { form }, { fetch }), form };
}
test('address advances through all tabs, closes, and clears dependent choices', async () => {
    const rows = { groups: [{ code: 'south-luzon', name: 'South Luzon' }], provinces: [{ code: 'p', name: 'Laguna', region_code: 'r' }], cities: [{ code: 'c', name: 'Calamba', region_code: 'r' }], barangays: [{ code: 'b', name: 'Barangay' }] };
    const { state, form, stop } = address(async url => response(url.includes('province=') && !url.includes('province=p') ? [] : rows[url.split('/').pop().split('?')[0]]));
    await state.load('groups'); state.open.value = true;
    await state.choose(rows.groups[0]); assert.equal(state.active.value, 'provinces');
    await state.choose(rows.provinces[0]); assert.equal(state.active.value, 'cities');
    await state.choose(rows.cities[0]); assert.equal(state.active.value, 'barangays');
    await state.choose(rows.barangays[0]); assert.equal(state.open.value, false);
    assert.equal(form.region_code, 'r'); assert.equal(form.barangay_code, 'b'); assert.match(state.summary.value, /South Luzon, Laguna, Calamba, Barangay/);
    state.active.value = 'groups'; await state.choose(rows.groups[0]);
    assert.equal(form.city_code, ''); assert.equal(form.barangay_code, ''); assert.equal(form.province_code, ''); stop();
});
test('Metro Manila skips Province and ignores stale group responses', async () => {
    const requests = [];
    const { state, form, stop } = address(url => { const request = deferred(); requests.push({ url, ...request }); return request.promise; });
    state.lists.groups = [{ code: 'north-luzon', name: 'North Luzon' }, { code: 'metro-manila', name: 'Metro Manila' }];
    const old = state.choose(state.lists.groups[0]); state.active.value = 'groups';
    const current = state.choose(state.lists.groups[1]);
    requests.filter(r => r.url.includes('metro-manila')).forEach(r => r.resolve(response([{ code: 'manila', name: 'Manila', region_code: '1300000000' }]))); await current;
    requests.filter(r => r.url.includes('north-luzon')).forEach(r => r.resolve(response([{ code: 'old', name: 'Old location' }]))); await old;
    assert.equal(state.active.value, 'cities'); assert.equal(state.lists.cities[0].code, 'manila'); assert.equal(form.province_code, ''); assert.equal(state.enabled('provinces'), false); stop();
});
test('address failures permit retry', async () => {
    let fail = true; const { state, stop } = address(async () => fail ? { ok: false } : response([{ code: 'mindanao', name: 'Mindanao' }]));
    await state.load('groups'); assert.match(state.failures.groups, /Unable to load/);
    fail = false; await state.load('groups'); assert.equal(state.failures.groups, ''); assert.equal(state.lists.groups.length, 1); stop();
});

test('independent cities remain reachable outside NCR', async () => {
    const group = { code: 'north-luzon', name: 'North Luzon' };
    const city = { code: 'baguio', name: 'Baguio', region_code: 'car', province_code: '' };
    const { state, form, stop } = address(async url => response(url.includes('/provinces') ? [{ code: 'benguet', name: 'Benguet' }] : url.includes('/cities') ? [city] : url.includes('/barangays') ? [{ code: 'brgy', name: 'Barangay' }] : [group]));
    await state.load('groups'); await state.choose(group);
    assert.equal(state.options.value.at(-1).code, 'independent');
    await state.choose(state.options.value.at(-1)); assert.equal(form.province_code, ''); assert.equal(state.enabled('cities'), true);
    await state.choose(city); await state.choose(state.options.value[0]);
    assert.equal(form.region_code, 'car'); assert.equal(form.barangay_code, 'brgy'); assert.match(state.summary.value, /Baguio/); stop();
});
test('reopening the modal address step preserves its completed selection', async () => {
    const form = Vue.reactive({ address_group: 'metro-manila', region_code: 'ncr', province_code: '', city_code: 'manila', barangay_code: 'b', province: '', municipality: 'Manila', barangay: 'Barangay', errors: {} });
    const { state, mounted, stop } = setup('Components/PhilippineAddressSelector.vue', { form }, { fetch: async url => response(url.includes('/groups') ? [{ code: 'metro-manila', name: 'Metro Manila' }] : url.includes('/cities') ? [{ code: 'manila', name: 'Manila' }] : [{ code: 'b', name: 'Barangay' }]) });
    await mounted[0](); assert.equal(state.summary.value, 'Metro Manila, Manila, Barangay'); assert.equal(form.barangay_code, 'b'); assert.equal(state.active.value, 'barangays'); stop();
});

function modal(mode = 'register') {
    const props = Vue.reactive({ show: true, mode });
    const visits = [];
    const { state, stop } = setup('Pages/Auth/AuthModal.vue', props, {
        router: { visit: url => visits.push(url) }, usePage: () => ({ props: { auth: {} } }),
        useForm: values => Vue.reactive({ ...values, errors: {}, reset() {}, clearErrors() {}, processing: false }),
        InputError: {}, PhilippineAddressSelector: {}, route: name => name, fetch: async () => response([]),
    });
    return { props, state, visits, stop };
}

test('modal requires a choice and starts buyer and seller registration', () => {
    const { state, visits, stop } = modal();
    state.startRegistration(); assert.equal(visits.length, 0); assert.match(state.stepError.value, /choose/);
    state.selectRegistrationType('buyer'); state.startRegistration();
    assert.equal(state.registrationStarted.value, true);
    assert.equal(state.isSeller.value, false);
    state.resetRegistration(); state.selectRegistrationType('seller'); state.startRegistration();
    assert.equal(state.registrationStarted.value, true);
    assert.equal(state.isSeller.value, true);
    assert.deepEqual(visits, []);
    stop();
});

test('seller footer preselects seller and header signup clears the choice', async () => {
    const { props, state, stop } = modal('register-seller');
    assert.equal(state.registrationType.value, 'seller');
    props.mode = 'register'; await Vue.nextTick(); assert.equal(state.registrationType.value, null);
    stop();
});

function coverageSelector(fetch) {
    const form = Vue.reactive({ delivery_city_codes: [], sorting_center_id: '', errors: {} });
    const result = setup('Components/DeliveryCoverageSelector.vue', { form, rider: true }, { fetch, PartnerField: {} });
    return { ...result, form };
}

test('delivery areas narrow partners to the intersection and clear an incompatible choice', async () => {
    const rows = [
        { code: '1380300000', label: 'Makati · NCR', partners: [{ id: 1, business_name: 'Partner One' }, { id: 2, business_name: 'Partner Two' }] },
        { code: '1381200000', label: 'Pasig · NCR', partners: [{ id: 1, business_name: 'Partner One' }] },
    ];
    const { state, form, stop } = coverageSelector(async url => { assert.equal(url, '/api/delivery-coverage'); return response(rows); });
    await state.load();
    state.candidate.value = rows[0].code; state.add(); await Vue.nextTick();
    assert.deepEqual(state.partners.value.map(row => row.value), [1, 2]);
    form.sorting_center_id = '2';
    state.candidate.value = rows[1].code; state.add(); await Vue.nextTick();
    assert.deepEqual(state.partners.value.map(row => row.value), [1]);
    assert.equal(form.sorting_center_id, '');
    form.sorting_center_id = '1'; state.remove(rows[1].code); await Vue.nextTick();
    assert.equal(form.sorting_center_id, '1');
    assert.deepEqual(form.delivery_city_codes, [rows[0].code]);
    stop();
});

test('unserved cities cannot be added and disjoint areas have no matching partner', async () => {
    const rows = [{ code: 'a', label: 'Area A', partners: [{ id: 1, business_name: 'One' }] }, { code: 'b', label: 'Area B', partners: [{ id: 2, business_name: 'Two' }] }];
    const { state, form, stop } = coverageSelector(async () => response(rows));
    await state.load();
    state.search.value = 'Unserved'; await Vue.nextTick();
    assert.equal(state.choices.value.length, 0);
    state.candidate.value = 'not-served'; state.add(); assert.deepEqual(form.delivery_city_codes, []);
    state.search.value = ''; await Vue.nextTick();
    state.candidate.value = 'a'; state.add(); await Vue.nextTick();
    state.candidate.value = 'b'; state.add(); await Vue.nextTick();
    assert.equal(state.partners.value.length, 0); assert.equal(form.sorting_center_id, '');
    stop();
});

test('coverage network errors allow retry and empty coverage offers no partner', async () => {
    let fail = true;
    const { state, stop } = coverageSelector(async () => fail ? { ok: false } : response([]));
    await state.load(); assert.match(state.error.value, /Unable to load/);
    fail = false; await state.load(); assert.equal(state.error.value, '');
    assert.equal(state.choices.value.length, 0); assert.equal(state.partners.value.length, 0);
    stop();
});

test('late coverage responses cannot replace the latest partner availability', async () => {
    const requests = [deferred(), deferred()]; let index = 0;
    const { state, stop } = coverageSelector(() => requests[index++].promise);
    const first = state.load(); const latest = state.load();
    requests[1].resolve(response([{ code: 'current', label: 'Currently served city', partners: [] }])); await latest;
    requests[0].resolve(response([{ code: 'old', label: 'Previously served city', partners: [] }])); await first;
    assert.equal(state.cities.value[0].code, 'current'); stop();
});

test('a slow independent-city lookup remains available after choosing a province', async () => {
 const pending = deferred(); let cityCalls = 0;
 const { state, stop } = address(async url => {
  if (url.includes('/provinces')) return response([{code:'p',name:'Province',region_code:'r'}]);
  if (url.includes('/cities')) return ++cityCalls === 1 ? pending.promise : response([{code:'c',name:'Provincial City',province_code:'p',region_code:'r'}]);
  return response([]);
 });
 state.lists.groups=[{code:'north-luzon',name:'North Luzon'}];
 const groupRequest=state.choose(state.lists.groups[0]); await new Promise(resolve=>setImmediate(resolve));
 await state.choose(state.lists.provinces[0]);
 pending.resolve(response([{code:'baguio',name:'Baguio',province_code:''}])); await groupRequest;
 state.active.value='provinces'; assert.ok(state.options.value.some(x=>x.code==='independent')); stop();
});
test('portal logout ends the web session even with an expired API token', async () => {
 const requests=[], removed=[]; const window={ logisticsSession: {token:'expired'}, location:{href:''}, alert:()=>assert.fail('Unexpected logout error') };
 const {state,stop}=setup('Layouts/ScLayout.vue', {}, {
  fetch:async url=>{requests.push(url);return {ok:true}}, window, confirm:()=>true,
  document:{querySelector:()=>({content:'csrf'})}, localStorage:{getItem:()=> 'expired', removeItem:key=>removed.push(key)}
 });
 await state.logout(); assert.deepEqual(requests,['/logout']); assert.ok(removed.includes('logistics_auth_token')); assert.equal(window.location.href,'/sorting-center/login'); stop();
});
test('failed web logout keeps credentials and shows an error', async () => {
 const removed=[],alerts=[]; const window={logisticsSession:{},location:{href:''},alert:message=>alerts.push(message)};
 const {state,stop}=setup('Layouts/ScLayout.vue', {}, {
  fetch:async()=>({ok:false}),window,confirm:()=>true,console:{error:()=>{}},
  document:{querySelector:()=>({content:'csrf'})},localStorage:{getItem:()=> 'token',removeItem:key=>removed.push(key)}
 });
 await state.logout(); assert.deepEqual(removed,[]); assert.equal(window.location.href,''); assert.equal(alerts.length,1); stop();
});
