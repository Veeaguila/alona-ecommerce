import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { parse } from '@vue/compiler-sfc';
import { ref, reactive } from 'vue';

const source = parse(readFileSync(new URL('../../resources/js/Pages/Logistics/RiderPayouts.vue', import.meta.url), 'utf8')).descriptor.scriptSetup.content.replace(/^import .*\r?\n/gm, '');
const payload = () => ({ data: { summary: { unpaid: '65.00', processing: '0.00', paid: '0.00' }, settings: { enabled: false, revision: 0, pickup: null, delivery: null, transfer: null }, riders: [{ id: 7, name: 'Synthetic Rider' }], payouts: { data: [], current_page: 1, last_page: 1 } } });
const reply = data => ({ ok: true, json: async () => data });
function screen(fetch) {
  let cleanup;
  const result = new Function('ref', 'reactive', 'onMounted', 'onUnmounted', 'createRequestKey', 'localStorage', 'fetch', source + '\nreturn {load,loadReview,prepare,payBatch,details,review,reviewKey,selectedRider,destination,reviewed,payment,rates,fresh,data,busy,error};')(
    ref, reactive, () => {}, fn => { cleanup = fn; }, () => 'synthetic-request-key', { getItem: () => 'synthetic-token' }, fetch,
  );
  return { ...result, dispose: () => cleanup() };
}

test('payroll starts without assumed rates or a made-up wallet balance', async () => {
  const state = screen(async () => reply(payload()));
  await state.load();
  assert.equal(state.rates.enabled, false); assert.equal(state.rates.pickup, null); assert.equal(state.rates.delivery, null);
  assert.equal(state.data.value.summary.unpaid, '65.00');
});

test('a pending grouped-payout submission cannot post twice', async () => {
  let writes = 0, release;
  const state = screen(async (path, options) => {
    if (options.method === 'POST') { writes++; await new Promise(resolve => { release = resolve; }); return reply({ data: { id: 5 } }); }
    return reply(payload());
  });
  await state.load();
  state.review.value = { jobs: [{ id: 1 }, { id: 2 }], amount: '65.00' }; state.reviewed.value = true; state.selectedRider.value = 7; state.destination.value = 8; state.reviewKey.value = 'same-reference';
  const first = state.prepare(); await state.prepare();
  assert.equal(writes, 1); release(); await first;
  assert.equal(state.review.value, null);
});

test('an uncertain preparation retains the reviewed request reference across explicit refresh', async () => {
  const keys = [];
  const state = screen(async (path, options) => {
    if (options.method === 'POST') { keys.push(JSON.parse(options.body).request_key); return { ok: false, json: async () => ({ message: 'Synthetic timeout' }) }; }
    return reply(payload());
  });
  await state.load(); state.review.value = { jobs: [{ id: 1 }], amount: '25.00' }; state.reviewKey.value = 'unchanged-reference'; state.selectedRider.value = 7; state.destination.value = 8; state.reviewed.value = true;
  await state.prepare(); assert.equal(state.fresh.value, false);
  await state.load(); await state.prepare();
  assert.deepEqual(keys, ['unchanged-reference', 'unchanged-reference']);
});

test('payment form Enter cannot record a transfer without actual-payment confirmation', async () => {
  let writes = 0;
  const state = screen(async (path, options) => { if (options.method === 'POST') writes++; return reply(payload()); });
  await state.load(); state.payment.reference = 'SYNTHETIC-TRANSFER'; state.payment.file = new Blob(['synthetic']);
  await state.payBatch({ id: 3, amount: '65.00' }); assert.equal(writes, 0);
});

test('a closed payout screen ignores a late server read', async () => {
  let release;
  const state = screen(() => new Promise(resolve => { release = resolve; }));
  const pending = state.load(); state.dispose(); release(reply(payload())); await pending;
  assert.equal(state.data.value, null); assert.equal(state.fresh.value, false);
});
