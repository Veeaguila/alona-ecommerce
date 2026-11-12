import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { parse } from '@vue/compiler-sfc';
import { compile } from '@vue/compiler-dom';
import * as Vue from 'vue';
import { renderToString } from '@vue/server-renderer';
import { createRequestKey } from '../../resources/js/requestKey.js';

const reply = (data, status = 200) => ({ ok: status < 400, status, json: async () => data });
const hub = { id: 1, name: 'Synthetic origin', address: 'Synthetic hub address' };
const otherHub = { ...hub, id: 2, name: 'Synthetic destination' };
const context = () => ({ operator: { role: 'hub_staff' }, hubs: [hub], operating_hub: hub, counts: {} });
const details = (action = 'receive_origin') => ({ parcel: { id: 8, order_id: 'ITEM-8', status: 'PICKED_UP', collection_address: 'Saved collection', delivery_address: 'Saved Buyer destination' },
  operating_hub: hub, location_label: 'With pickup Rider', next_destination: { name: hub.name }, action, action_label: 'Receive at origin hub', instruction: 'Confirm physical receipt.' });
const emptyQueue = { data: [], current_page: 1, last_page: 1 };
function loader(name, fetch, crypto = globalThis.crypto) {
  const { descriptor } = parse(readFileSync(new URL(`../../resources/js/Pages/Logistics/${name}.vue`, import.meta.url), 'utf8'));
  const script = descriptor.script.content.replace(/^import .*;\r?\n/gm, '').replace('export default', 'return');
  const dispose = [];
  const layout = { setup: (_, { slots }) => () => Vue.h('main', slots.default?.()) };
  const component = new Function('ref', 'reactive', 'computed', 'onMounted', 'onUnmounted', 'ScLayout', 'fetch', 'localStorage', 'createRequestKey', script)(
    Vue.ref, Vue.reactive, Vue.computed, () => {}, fn => dispose.push(fn), layout, fetch, { getItem: () => 'synthetic-token' }, () => createRequestKey(crypto));
  component.render = new Function('Vue', compile(descriptor.template.content, { prefixIdentifiers: true }).code)(Vue);
  const state = component.setup();
  return { state, dispose: () => dispose.forEach(fn => fn()), html: () => renderToString(Vue.createSSRApp({ ...component, setup: () => state })) };
}
const reads = url => reply({ data: url.includes('/queue') ? emptyQueue : context() });

test('waybill lookup shows saved addresses and next destination without recording a movement', async () => {
  let writes = 0;
  const app = loader('HubWorkspace', async (url, options) => { if (options.method === 'POST') writes++; return url.includes('/lookup') ? reply({ data: details() }) : reads(url); });
  await app.state.refresh(); app.state.reference.value = 'ITEM-8'; await app.state.lookup();
  const html = await app.html();
  assert.match(html, /Saved Buyer destination/); assert.match(html, /With pickup Rider/); assert.match(html, /Synthetic origin/);
  assert.equal(app.state.isStaff.value, true); assert.equal(writes, 0);
  assert.doesNotMatch(html, /<select[^>]*>[\s\S]*Choose a hub/);
});

test('editing a reference rejects a late lookup and removes the old physical confirmation', async () => {
  let resolve;
  const app = loader('HubWorkspace', async url => url.includes('/lookup') ? new Promise(done => { resolve = done; }) : reads(url));
  await app.state.refresh(); app.state.reference.value = 'ITEM-8'; const pending = app.state.lookup();
  app.state.reference.value = 'ITEM-9'; app.state.confirmed.value = true; app.state.invalidate();
  resolve(reply({ data: details() })); await pending;
  assert.equal(app.state.result.value, null); assert.equal(app.state.confirmed.value, false);
});

test('changing owner operating hub clears the parcel and old confirmations before reading the new queue', async () => {
  const app = loader('HubWorkspace', async url => {
    if (url.includes('/lookup')) return reply({ data: details() });
    if (url.includes('/queue')) return reply({ data: emptyQueue });
    return reply({ data: { ...context(), operator: { role: 'company_admin' }, hubs: [hub, otherHub], operating_hub: url.includes('hub_id=2') ? otherHub : hub } });
  });
  await app.state.refresh(); app.state.reference.value = 'ITEM-8'; await app.state.lookup();
  app.state.confirmed.value = true; app.state.hubId.value = 2; await app.state.switchHub();
  assert.equal(app.state.result.value, null); assert.equal(app.state.confirmed.value, false); assert.equal(app.state.reference.value, '');
  assert.equal(app.state.hubId.value, 2);
});

test('a confirmed movement sends one scoped action and duplicate clicks do not replay it', async () => {
  const writes = []; let finish;
  const app = loader('HubWorkspace', async (url, options) => {
    if (options.method === 'POST') { writes.push(JSON.parse(options.body)); return new Promise(done => { finish = done; }); }
    return url.includes('/lookup') ? reply({ data: details() }) : reads(url);
  });
  await app.state.refresh(); app.state.reference.value = 'ITEM-8'; await app.state.lookup();
  await app.state.performAction(); assert.equal(writes.length, 0);
  app.state.confirmed.value = true; const pending = app.state.performAction(); await app.state.performAction();
  assert.equal(writes.length, 1); assert.deepEqual(writes[0], { reference: 'ITEM-8', hub_id: 1, action: 'receive_origin', physical_confirmation: true });
  finish(reply({ data: { ...details(null), parcel: { ...details().parcel, status: 'AT_SORTING_CENTER' } } })); await pending;
  assert.equal(app.state.confirmed.value, false); assert.equal(app.state.result.value.action, null);
});

test('uncertain mutations clear actionable state and require a fresh read without automatic replay', async () => {
  let writes = 0;
  const app = loader('HubWorkspace', async (url, options) => {
    if (options.method === 'POST') { writes++; throw new Error('Synthetic connection interruption'); }
    return url.includes('/lookup') ? reply({ data: details() }) : reads(url);
  });
  await app.state.refresh(); app.state.reference.value = 'ITEM-8'; await app.state.lookup(); app.state.confirmed.value = true;
  await app.state.performAction(); await app.state.performAction();
  assert.equal(writes, 1); assert.equal(app.state.fresh.value, false); assert.equal(app.state.result.value, null);
});

test('dispatch uses a valid request UUID and requires a real manifest reference', async () => {
  const writes = [];
  const app = loader('HubWorkspace', async (url, options) => {
    if (options.method === 'POST') { writes.push(JSON.parse(options.body)); return reply({ data: details(null) }); }
    return url.includes('/lookup') ? reply({ data: details('dispatch') }) : reads(url);
  });
  await app.state.refresh(); app.state.reference.value = 'ITEM-8'; await app.state.lookup(); app.state.confirmed.value = true;
  await app.state.performAction(); assert.equal(writes.length, 0);
  app.state.transportReference.value = 'SYNTHETIC-MANIFEST'; await app.state.performAction();
  assert.equal(writes.length, 1); assert.equal(writes[0].transport_reference, 'SYNTHETIC-MANIFEST');
  assert.match(writes[0].request_key, /^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/);
});

test('unmounting prevents late reads from displaying another private parcel', async () => {
  let finish;
  const app = loader('HubWorkspace', async url => url.includes('/lookup') ? new Promise(done => { finish = done; }) : reads(url));
  await app.state.refresh(); app.state.reference.value = 'ITEM-8'; const pending = app.state.lookup(); app.dispose(); finish(reply({ data: details() })); await pending;
  assert.equal(app.state.result.value, null);
});

test('staff account creation suppresses duplicate clicks and clears passwords after a confirmed save', async () => {
  let finish; let writes = 0;
  const app = loader('HubStaff', async (url, options) => {
    if (options.method === 'POST') { writes++; return new Promise(done => { finish = done; }); }
    return reply({ data: { hubs: [hub], staff: [] } });
  });
  await app.state.load(); Object.assign(app.state.form, { name: 'Synthetic Person', email: 'fixture@example.test', hub_id: 1, password: 'Synthetic#123', password_confirmation: 'Synthetic#123' });
  const pending = app.state.createStaff(); await app.state.createStaff(); assert.equal(writes, 1);
  finish(reply({ data: { id: 9 }, message: 'Created' })); await pending;
  assert.equal(app.state.form.password, ''); assert.equal(app.state.form.password_confirmation, ''); assert.equal(app.state.form.email, '');
});

test('unreadable staff records cannot enable account writes', async () => {
  let writes = 0;
  const app = loader('HubStaff', async (url, options) => { if (options.method === 'POST') writes++; return reply({ data: { hubs: [] } }); });
  await app.state.load(); await app.state.createStaff(); assert.equal(writes, 0); assert.equal(app.state.loaded.value, false);
});
