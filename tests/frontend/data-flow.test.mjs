import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { parse } from '@vue/compiler-sfc';
import { compile } from '@vue/compiler-dom';
import * as Vue from 'vue';
import { renderToString } from '@vue/server-renderer';

function loadPage(name, fetch) {
  const source = readFileSync(new URL(`../../resources/js/Pages/Logistics/${name}.vue`, import.meta.url), 'utf8');
  const { descriptor } = parse(source);
  const script = descriptor.script.content
    .replace(/^import .*;\r?\n/gm, '')
    .replace('export default', 'return');
  const unmountCallbacks = [];
  const ScLayout = { setup: (_, { slots }) => () => Vue.h('main', slots.default?.()) };
  const component = new Function('ref', 'reactive', 'onMounted', 'onUnmounted', 'ScLayout', 'fetch', 'localStorage', script)(
    Vue.ref, Vue.reactive, () => {}, callback => unmountCallbacks.push(callback),
    ScLayout, fetch, { getItem: () => 'test-token' },
  );
  component.render = new Function('Vue', compile(descriptor.template.content, { prefixIdentifiers: true }).code)(Vue);
  return { component, state: component.setup(), unmount: () => unmountCallbacks.forEach(fn => fn()) };
}

function deferred() {
  let resolve;
  const promise = new Promise(done => { resolve = done; });
  return { promise, resolve };
}

function response(records, total = records.length, page = 1, last = 1) {
  return { ok: true, json: async () => ({ data: {
    data: records, total, current_page: page, last_page: last,
    prev_page_url: null, next_page_url: null,
  } }) };
}

test('monitoring retains the newest filter response when requests finish out of order', async () => {
  const requests = [deferred(), deferred()];
  let index = 0;
  const { state } = loadPage('DeliveryMonitoring', () => requests[index++].promise);
  const old = state.loadParcels();
  state.selectedStatus.value = 'DELIVERED';
  const current = state.loadParcels();
  requests[1].resolve(response([{ id: 2, status: 'DELIVERED' }]));
  await current;
  requests[0].resolve(response([{ id: 1, status: 'OUT_FOR_DELIVERY' }]));
  await old;
  assert.equal(state.parcels.value[0].id, 2);
  assert.equal(state.loading.value, false);
});

test('unmounted monitoring ignores an outstanding response', async () => {
  const request = deferred();
  const { state, unmount } = loadPage('DeliveryMonitoring', () => request.promise);
  const loading = state.loadParcels();
  unmount();
  request.resolve(response([{ id: 1 }]));
  await loading;
  assert.deepEqual(state.parcels.value, []);
});

test('pickup confirmation blocks overlapping actions and refreshes the page totals', async () => {
  const request = deferred();
  const calls = [];
  const { state } = loadPage('PickupRequestManagement', async (url, options) => {
    calls.push(url);
    if (options.method === 'POST') return request.promise;
    return response([{ id: 2, parcel: { order_id: 'SECOND' } }], 1);
  });
  const first = state.confirmPickup(1);
  await state.confirmPickup(2);
  await state.loadData();
  assert.equal(calls.length, 1);
  request.resolve({ ok: true, json: async () => ({ message: 'Confirmed' }) });
  await first;
  assert.equal(calls.length, 2);
  assert.equal(state.pagination.total, 1);
  assert.equal(state.pickupRequests.value[0].id, 2);
  assert.equal(state.processingId.value, null);
});

test('pickup pagination moves back when the last page becomes empty', async () => {
  const calls = [];
  const { state } = loadPage('PickupRequestManagement', async url => {
    calls.push(url);
    return url.endsWith('page=2') ? response([], 25, 2, 1) : response([{ id: 1 }], 25);
  });
  await state.loadData(2);
  assert.equal(calls.length, 2);
  assert.equal(state.pagination.current_page, 1);
});

test('pickup cards render safely with missing parcel and seller data', async () => {
  const { component, state } = loadPage('PickupRequestManagement', async () => response([]));
  assert.equal(state.formatAddress(null), 'Address unavailable');
  state.pickupRequests.value = [{ id: 1, parcel: null, seller: null, status: 'pending' }];
  component.setup = () => state;
  const html = await renderToString(Vue.createSSRApp(component));
  assert.match(html, /Unavailable/);
  assert.match(html, /Address unavailable/);
  assert.match(html, /disabled/);
});
