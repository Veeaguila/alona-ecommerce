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

function partnerModal(role) {
    const visits = [], posts = [];
    const result = setup('Components/PartnerAuthModal.vue', { show: false, initialRole: role, initialTab: 'signup' }, {
        usePage: () => ({ props: {} }), router: { visit: url => visits.push(url) }, route: name => name,
        useForm: initial => Vue.reactive({ ...initial, errors: {}, reset() {}, clearErrors() {}, post: url => posts.push(url) }),
        InputError: {}, PartnerField: {}, PhilippineAddressSelector: {}, DeliveryCoverageSelector: {},
    });
    return { ...result, visits, posts };
}
test('Logistics popup opens session-bound registration instead of posting the legacy contract', () => {
    const { state, visits, posts, stop } = partnerModal('logistics');
    state.startRegistration();
    assert.deepEqual(visits, ['register.logistics']);
    assert.equal(state.registrationStarted.value, false);
    state.submitApplication();
    assert.deepEqual(visits, ['register.logistics', 'register.logistics']);
    assert.deepEqual(posts, []); stop();
});
test('Rider popup still starts its existing registration steps', () => {
    const { state, visits, stop } = partnerModal('rider');
    state.startRegistration();
    assert.equal(state.registrationStarted.value, true);
    assert.equal(state.currentStep.value, 1);
    assert.deepEqual(visits, []); stop();
});

function logisticsRegistration() {
    const posts = [];
    const result = setup('Pages/Auth/LogisticsRegistration.vue', { requestKey: 'test-key', regions: [], google: null }, {
        router: { on: () => () => {} }, route: name => name,
        useForm: initial => Vue.reactive({ ...initial, errors: {}, processing: false,
            setError(field, message) { this.errors[field] = message; },
            clearErrors(field) { delete this.errors[field]; },
            post: url => posts.push(url),
        }),
        GuestLayout: {}, LogisticsAddressSelector: {},
    });
    Object.assign(result.state.form, { first_name: 'Test', last_name: 'Applicant', email: 'applicant@example.test', password: 'Password@123', password_confirmation: 'Password@123', tin: '123-456-789-000' });
    return { ...result, posts };
}

test('Logistics registration blocks invalid mobile formats before leaving the account step', () => {
    const { state, stop } = logisticsRegistration();
    for (const mobile of ['0917123456', '091712345678', '+63917123456', '+6391712345678', '0917ABC4567', '12345678901', '0917-123-4567']) {
        state.form.contact_no = mobile;
        state.goToStep(2);
        assert.equal(state.currentStep.value, 1, mobile);
        assert.match(state.form.errors.contact_no, /11 digits starting with 09/);
    }
    stop();
});

test('Logistics registration accepts both supported mobile formats with the correct input limits', () => {
    const { state, stop } = logisticsRegistration();
    for (const [mobile, limit] of [['09171234567', 11], ['+639171234567', 13]]) {
        state.currentStep.value = 1;
        state.form.contact_no = mobile;
        assert.equal(state.mobileMaxLength.value, limit);
        state.goToStep(2);
        assert.equal(state.currentStep.value, 2);
        assert.equal(state.form.errors.contact_no, undefined);
    }
    stop();
});

test('Logistics registration rejects oversized or alphabetic pastes without truncating a contact', () => {
    const { state, stop } = logisticsRegistration();
    for (const pasted of ['091712345678', '+6391712345678', '0917ABC1234567']) {
        state.form.contact_no = '';
        let prevented = false;
        state.checkMobilePaste({ clipboardData: { getData: () => pasted }, target: { selectionStart: 0, selectionEnd: 0 }, preventDefault: () => { prevented = true; } });
        assert.equal(prevented, true);
        assert.equal(state.form.contact_no, '');
        assert.match(state.form.errors.contact_no, /11 digits/);
    }
    stop();
});

test('Logistics registration pastes an entire international number into an empty local-length field', () => {
    const { state, stop } = logisticsRegistration();
    assert.equal(state.mobileMaxLength.value, 11);
    const input = { selectionStart: 0, selectionEnd: 0, value: '' };
    let prevented = false;
    state.checkMobilePaste({ clipboardData: { getData: () => '+639171234567' }, target: input, preventDefault: () => { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(state.form.contact_no, '+639171234567');
    assert.equal(input.value, '+639171234567');
    assert.equal(state.mobileMaxLength.value, 13);
    assert.equal(state.validateMobileNumber(), true);
    stop();
});

test('Logistics registration rechecks the mobile number before submitting from a later step', () => {
    const { state, posts, stop } = logisticsRegistration();
    state.currentStep.value = 3;
    state.form.contact_no = '123';
    state.submit();
    assert.equal(state.currentStep.value, 1);
    assert.deepEqual(posts, []);
    state.form.contact_no = '+639171234567';
    state.submit();
    assert.deepEqual(posts, ['register.logistics']);
    stop();
});

test('Logistics TIN accepts complete digits and common separators while clearing old format errors', () => {
    const { state, stop } = logisticsRegistration();
    for (const tin of ['123-456-789-000', '123456789000', ' 123 456 789 000 ', '123\u2011456\u2011789\u2011000']) {
        state.form.tin = tin;
        state.form.setError('tin', 'Old server validation error');
        assert.equal(state.validateTin(), true, tin);
        assert.equal(state.form.tin, '123-456-789-000');
        assert.equal(state.form.errors.tin, undefined);
    }
    stop();
});

test('Logistics TIN rejects missing digits, extra digits and letters without silently changing the number', () => {
    const { state, stop } = logisticsRegistration();
    for (const tin of ['123-456-789-00', '123-456-789-0001', '123-456-78A-000', '1234567890009']) {
        state.form.tin = tin;
        assert.equal(state.validateTin(), false);
        assert.equal(state.form.tin, tin);
        assert.match(state.form.errors.tin, /Enter 12 digits/);
    }
    stop();
});

test('Logistics TIN validation blocks the company step and submission until corrected', () => {
    const { state, posts, stop } = logisticsRegistration();
    Object.assign(state.form, { contact_no: '09171234567', business_name: 'Test Logistics', registration_number: 'CS202012345', city_code: 'city', barangay_code: 'barangay', street: 'Test street', tin: '123' });
    state.currentStep.value = 2;
    state.goToStep(3);
    assert.equal(state.currentStep.value, 2);
    state.submit();
    assert.deepEqual(posts, []);
    state.form.tin = '123456789000';
    state.goToStep(3);
    assert.equal(state.currentStep.value, 3);
    state.submit();
    assert.deepEqual(posts, ['register.logistics']);
    assert.equal(state.form.tin, '123-456-789-000');
    stop();
});
