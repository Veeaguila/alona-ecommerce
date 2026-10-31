import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { parse } from '@vue/compiler-sfc';
import { compile } from '@vue/compiler-dom';
import * as Vue from 'vue';
import { renderToString } from '@vue/server-renderer';
import { createRequestKey } from '../../resources/js/requestKey.js';

const reply = (data, status = 200) => ({ ok: status < 400, status, json: async () => data });
const hub = { id: 1, code: 'MNL-01', name: 'Manila hub', address: 'Synthetic Manila location', city_code: '1381300000', pickup_city_codes: ['1381300000'], delivery_city_codes: [], is_active: true };
const destination = { ...hub, id: 2, name: 'Cebu hub', code: 'CEB-01' };
const network = () => ({ hubs: [structuredClone(hub), structuredClone(destination)], routes: [], riders: [{ id: 7, name: 'Synthetic Rider', logistics_hub_id: 1 }], booking_routes_required: false });
const parcel = () => ({ id: 4, parcel_id: 8, origin_hub_id: 1, destination_hub_id: 2, current_hub_id: 1, origin_received_at: '2026-10-29T00:00:00Z', destination_received_at: null,
  route_snapshot: { origin_hub_name: 'Manila hub', destination_hub_name: 'Cebu hub' }, parcel: { id: 8, order_id: 'ITEM-8', status: 'AT_SORTING_CENTER' }, transfer: null });
const results = (journeys = []) => ({ data: journeys, current_page: 1, last_page: 1 });
function loader(fetch, token = 'synthetic-token', cryptoSource = globalThis.crypto) {
  const source = readFileSync(new URL('../../resources/js/Pages/Logistics/HubNetwork.vue', import.meta.url), 'utf8');
  const { descriptor } = parse(source);
  const script = descriptor.script.content.replace(/^import .*;\r?\n/gm, '').replace('export default', 'return');
  const unmount = [];
  const layout = { setup: (_, { slots }) => () => Vue.h('main', slots.default?.()) };
  const component = new Function('ref', 'reactive', 'computed', 'onMounted', 'onUnmounted', 'watch', 'ScLayout', 'fetch', 'localStorage', 'createRequestKey', script)(Vue.ref, Vue.reactive, Vue.computed, () => {}, callback => unmount.push(callback), Vue.watch, layout, fetch, { getItem: () => token }, () => createRequestKey(cryptoSource));
  component.render = new Function('Vue', compile(descriptor.template.content, { prefixIdentifiers: true }).code)(Vue);
  const state = component.setup();
  return { state, unmount: () => unmount.forEach(fn => fn()), html: () => renderToString(Vue.createSSRApp({ ...component, setup: () => state })) };
}
function reads(url, journeys = []) {
  if (url.includes('hub-network')) return reply({ data: network() });
  if (url.includes('/addresses/')) return reply({ data: [{ code: '1381300000', name: 'Quezon City' }] });
  return reply({ data: results(journeys) });
}

test('HTTP dispatch generates a valid UUID without crypto.randomUUID and submits the confirmed action', async () => {
  const mutations = [];
  const httpCrypto = { getRandomValues: bytes => globalThis.crypto.getRandomValues(bytes) };
  const app = loader(async (url, options) => {
    if (options.method !== 'GET') { mutations.push(JSON.parse(options.body)); return reply({ data: {}, message: 'Dispatched' }); }
    return reads(url, [parcel()]);
  }, 'synthetic-token', httpCrypto);
  await app.state.refresh(); await app.state.chooseHub(1);
  app.state.confirmations[4] = true; app.state.references[4] = 'SYNTHETIC-HTTP';
  await app.state.transfer(parcel(), 'dispatch');
  assert.equal(mutations.length, 1);
  assert.match(mutations[0].request_key, /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/);
  assert.equal(mutations[0].physical_confirmation, true);
  assert.notEqual(createRequestKey(httpCrypto), createRequestKey(httpCrypto));
});

test('unsupported UUID generation displays a dispatch error and sends no mutation', async () => {
  let mutations = 0;
  const app = loader(async (url, options) => { if (options.method !== 'GET') mutations++; return reads(url, [parcel()]); }, 'synthetic-token', {});
  await app.state.refresh(); await app.state.chooseHub(1);
  app.state.confirmations[4] = true; app.state.references[4] = 'SYNTHETIC-HTTP';
  await app.state.transfer(parcel(), 'dispatch');
  assert.equal(mutations, 0);
  assert.match(app.state.error.value, /cannot generate a request key/);
});

test('hub setup shows physical locations separately from coverage and makes rollout state explicit', async () => {
  const app = loader(async url => reads(url));
  await app.state.refresh();
  const html = await app.html();
  assert.match(html, /Physical hubs/);
  assert.match(html, /actual location/);
  assert.match(html, /activate booking validation/);
  assert.match(html, /No matching hub bookings/);
  assert.equal(app.state.fresh.value, true);
});

test('editing coverage does not alter the loaded hub record before saving', async () => {
  const app = loader(async url => reads(url)); await app.state.refresh();
  app.state.editHub(app.state.network.value.hubs[0]);
  app.state.hubForm.pickup_city_codes.push('0730600000');
  assert.deepEqual([...app.state.network.value.hubs[0].pickup_city_codes], ['1381300000']);
});

test('a missing session or missing migration never produces fake empty success', async () => {
  let requests = 0;
  const loggedOut = loader(async () => { requests++; }, null);
  await loggedOut.state.refresh();
  assert.equal(requests, 0); assert.equal(loggedOut.state.fresh.value, false);
  assert.match(loggedOut.state.error.value, /Sign in/);
  const unavailable = loader(async () => reply({ message: 'Hub setup is not available yet.' }, 503));
  await unavailable.state.refresh();
  assert.equal(unavailable.state.network.value, null);
  assert.match(unavailable.state.error.value, /not available/);
});

test('late network responses cannot populate an unmounted page', async () => {
  let resolve;
  const pending = new Promise(done => { resolve = done; });
  const app = loader(async url => url.includes('hub-network') ? pending : reads(url));
  const loading = app.state.refresh(); app.unmount(); resolve(reply({ data: network() })); await loading;
  assert.equal(app.state.network.value, null);
  assert.equal(app.state.fresh.value, false);
});

test('same-hub routes are blocked before a mutation request', async () => {
  let posts = 0;
  const app = loader(async (url, options) => { if (options.method !== 'GET') posts++; return reads(url); });
  await app.state.refresh();
  app.state.routeForm.origin_hub_id = 1; app.state.routeForm.destination_hub_id = 1;
  await app.state.saveRoute(); assert.equal(posts, 0); assert.match(app.state.error.value, /different hubs/);
});

test('dispatch requires the actual origin hub, physical confirmation and a transport reference', async () => {
  const mutations = [];
  const item = parcel();
  const app = loader(async (url, options) => {
    if (options.method !== 'GET') { mutations.push({ url, body: JSON.parse(options.body) }); return reply({ message: 'Shipment dispatched.', data: [] }); }
    return reads(url, [item]);
  });
  await app.state.refresh();
  app.state.operatingHub.value = 2; await Vue.nextTick();
  app.state.confirmations[4] = true; app.state.references[4] = 'SYNTHETIC-001';
  await app.state.transfer(item, 'dispatch'); assert.equal(mutations.length, 0);
  app.state.operatingHub.value = 1; await Vue.nextTick();
  await app.state.transfer(item, 'dispatch'); assert.equal(mutations.length, 0);
  app.state.confirmations[4] = true; app.state.references[4] = 'x';
  await app.state.transfer(item, 'dispatch'); assert.equal(mutations.length, 0);
  app.state.references[4] = 'SYNTHETIC-001';
  await app.state.transfer(item, 'dispatch'); assert.equal(mutations.length, 1);
  assert.equal(mutations[0].url, '/api/sorting-center/hub-journeys/8/dispatch');
  assert.equal(mutations[0].body.hub_id, 1); assert.equal(mutations[0].body.physical_confirmation, true);
  assert.match(mutations[0].body.request_key, /^[a-f0-9-]{36}$/);
  assert.equal(app.state.confirmations[4], false);
});

test('duplicate clicks cannot submit overlapping hub mutations and rejected edits remain available', async () => {
  let resolve, posts = 0;
  const pending = new Promise(done => { resolve = done; });
  const app = loader(async (url, options) => { if (options.method !== 'GET') { posts++; return pending; } return reads(url); });
  await app.state.refresh(); app.state.editHub(app.state.network.value.hubs[0]);
  const first = app.state.saveHub(); const second = app.state.saveHub();
  resolve(reply({ errors: { address: ['Active shipment hubs cannot be moved.'] } }, 422)); await Promise.all([first, second]);
  assert.equal(posts, 1); assert.equal(app.state.busy.value, false);
  assert.equal(app.state.hubForm.id, 1); assert.match(app.state.error.value, /cannot be moved/);
});

test('destination receipt cannot be submitted from the origin or before dispatch', async () => {
  let posts = 0;
  const item = parcel(); item.parcel.status = 'IN_HUB_TRANSIT';
  const app = loader(async (url, options) => { if (options.method !== 'GET') { posts++; return reply({ data: {} }); } return reads(url, [item]); });
  await app.state.refresh(); app.state.operatingHub.value = 2; await Vue.nextTick(); app.state.confirmations[4] = true;
  await app.state.transfer(item, 'receive'); assert.equal(posts, 0);
  item.transfer = { dispatched_at: '2026-10-29T01:00:00Z', received_at: null };
  app.state.operatingHub.value = 1; await Vue.nextTick(); app.state.confirmations[4] = true;
  await app.state.transfer(item, 'receive'); assert.equal(posts, 0);
});

test('an uncertain mutation result requires a fresh read before another action', async () => {
  const app = loader(async (url, options) => {
    if (options.method !== 'GET') throw new TypeError('Synthetic connection lost after submission');
    return reads(url);
  });
  await app.state.refresh(); app.state.editHub(app.state.network.value.hubs[0]);
  await app.state.saveHub();
  assert.equal(app.state.fresh.value, false);
  assert.equal(app.state.busy.value, false);
  assert.equal(app.state.hubForm.id, 1);
  assert.match(app.state.error.value, /connection lost/);
});

test('inbound hub bookings require explicit origin receipt and submit the booked hub ID', async () => {
  const source = readFileSync(new URL('../../resources/js/Pages/Logistics/OrderManagement.vue', import.meta.url), 'utf8');
  const { descriptor } = parse(source);
  const script = descriptor.script.content.replace(/^import .*;\r?\n/gm, '').replace('export default', 'return');
  const requests = [];
  const fetch = async (url, options) => {
    requests.push({ url, options });
    return reply({ data: { order_id: 'ITEM-8', status: options?.method === 'POST' ? 'AT_SORTING_CENTER' : 'PICKED_UP',
      hub_route: { origin_hub_id: 1, origin_hub_name: 'Manila hub' } } });
  };
  const component = new Function('ref', 'reactive', 'onMounted', 'onUnmounted', 'ScLayout', 'ReturnToSellerPanel', 'fetch', 'localStorage', 'window', script)(Vue.ref, Vue.reactive, () => {}, () => {}, {}, {}, fetch, { getItem: () => 'synthetic-token' }, { location: { search: '' } });
  const state = component.setup(); state.orderId.value = 'ITEM-8';
  await state.searchParcel(); await state.confirmArrival();
  assert.equal(requests.filter(request => request.options?.method === 'POST').length, 0);
  assert.match(state.feedback.message, /physical receipt/);
  state.originHubConfirmed.value = true; await state.confirmArrival();
  const post = requests.find(request => request.options?.method === 'POST');
  assert.deepEqual(JSON.parse(post.options.body), { order_id: 'ITEM-8', hub_id: 1 });
  assert.equal(state.parcel.value.status, 'AT_SORTING_CENTER');
});

test('one account can choose either company hub and requests that hub work queue', async () => {
  const urls = [];
  const app = loader(async url => { urls.push(url); return reads(url); });
  await app.state.refresh();
  assert.equal(app.state.activeTab.value, 'operations');
  await app.state.chooseHub(1);
  assert.match(urls.at(-1), /hub_id=1/);
  app.state.stage.value = 'transfer_inbound'; await app.state.loadJourneys();
  assert.match(urls.at(-1), /stage=transfer_inbound/);
  app.state.confirmations[4] = true;
  await app.state.chooseHub(2); await Vue.nextTick();
  assert.match(urls.at(-1), /hub_id=2/);
  assert.equal(app.state.stage.value, 'all');
  assert.equal(app.state.confirmations[4], false);
  const html = await app.html(); assert.match(html, /This Logistics account manages all your company hubs/);
});

test('pickup origin receipt requires the selected origin and fresh physical confirmation', async () => {
  const item = parcel(); item.parcel.status = 'PICKED_UP'; item.origin_received_at = null; item.current_hub_id = null;
  const mutations = [];
  const app = loader(async (url, options) => {
    if (options.method !== 'GET') { mutations.push({ url, body: JSON.parse(options.body) }); return reply({ data: {}, message: 'Origin receipt recorded.' }); }
    return reads(url, [item]);
  });
  await app.state.refresh(); app.state.confirmations[4] = true;
  await app.state.receiveOrigin(item); assert.equal(mutations.length, 0);
  await app.state.chooseHub(2); await Vue.nextTick(); app.state.confirmations[4] = true;
  await app.state.receiveOrigin(item); assert.equal(mutations.length, 0);
  await app.state.chooseHub(1); await Vue.nextTick();
  await app.state.receiveOrigin(item); assert.equal(mutations.length, 0);
  app.state.confirmations[4] = true; await app.state.receiveOrigin(item);
  assert.deepEqual(mutations, [{ url: '/api/sorting-center/parcels/mark-arrived', body: { order_id: 'ITEM-8', hub_id: 1, physical_confirmation: true } }]);
});

test('changing hub clears old queue rows before its response and never presents them as another hub work', async () => {
  let finish;
  const app = loader(async url => url.includes('hub_id=2') ? new Promise(resolve => { finish = resolve; }) : reads(url, [parcel()]));
  await app.state.refresh();
  assert.equal(app.state.journeys.value.data.length, 1);
  const pending = app.state.chooseHub(2);
  assert.equal(app.state.journeys.value.data.length, 0);
  assert.equal(app.state.busy.value, true);
  finish(reply({ data: results([]) })); await pending;
  assert.equal(app.state.journeys.value.data.length, 0);
});

test('hub management navigation remains available while required checkout routing is disabled', () => {
  const source = readFileSync(new URL('../../resources/js/Layouts/ScLayout.vue', import.meta.url), 'utf8');
  assert.match(source, /navItems\[9\], navItems\[10\]/);
  assert.doesNotMatch(source, /hub_routes_required \? \[navItems\[10\]\]/);
});
