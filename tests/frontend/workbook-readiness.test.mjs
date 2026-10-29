import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { parse } from '@vue/compiler-sfc';
import { ref, computed, reactive } from 'vue';

function script(file) {
  return parse(readFileSync(new URL(`../../resources/js/${file}`, import.meta.url), 'utf8')).descriptor.scriptSetup.content.replace(/^import .*\r?\n/gm, '');
}
const fixture = () => ({ items: [{ id: 1, quantity: 1, price: 100, product: { seller_id: 9, weight: 1 } }], selected_items: [1], addresses: [{ id: 2, address: 'Quezon City, Metro Manila' }],
  shipping_options: [{ key: 'standard', fee_per_seller: 50 }, { key: 'pickup', fee_per_seller: 0 }], default_shipping_method: 'standard', seller_count: 1, free_shipping_threshold: 2000,
  payment_options: [], saved_payment_methods: [], address_serviceability: { 2: { state: 'supported' } },
  shipping_rate_cards: { 2: { standard: { weighted: { base: 55, per_kg: 20 }, unweighted: { base: 55, per_kg: 20 } } } } });
function checkout(props) {
  const source = script('Pages/Buyer/Checkout.vue'); let posted;
  const state = new Function('defineProps', 'ref', 'computed', 'watch', 'useForm', 'route', source + '\nreturn { form, feeFor, rawShippingFee, calculateStoreShipping, submit, appliedVouchers, subtotal };')(
    () => props, ref, computed, () => {}, value => ({ ...value, clearErrors() {}, post() { posted = structuredClone({ expected_shipping_fee: this.expected_shipping_fee, selected_items: this.selected_items }); } }), name => name);
  state.form.shipping_address = props.addresses[0].address;
  return { ...state, posted: () => posted };
}
test('checkout displays configured 55 rate rather than hardcoded 50 and submits that quote for server verification', () => {
  const props = fixture(), state = checkout(props);
  assert.equal(state.rawShippingFee.value, 55);
  state.submit(); assert.equal(state.posted().expected_shipping_fee, 55);
});
test('configured extra kg rate and per seller grouping match the server tariff card', () => {
  const props = fixture(); props.items[0].product.weight = 1.2;
  props.items.push({ id: 3, quantity: 2, price: 100, product: { seller_id: 10, weight: 1 } }); props.selected_items.push(3);
  const state = checkout(props); assert.equal(state.rawShippingFee.value, 150);
});

test('checkout sums each sellers booked company tariff rather than applying the first company rate to both', () => {
  const props = fixture(); props.items[0].product.weight = 2.2;
  props.items.push({ id: 3, quantity: 1, price: 100, product: { seller_id: 10, weight: 1.2 } }); props.selected_items.push(3);
  props.shipping_rate_cards[2].standard.sellers = {
    9: { weighted: { base: 120, per_kg: 20 } },
    10: { weighted: { base: 75, per_kg: 10 } },
  };
  const state = checkout(props);
  assert.equal(state.rawShippingFee.value, 245);
  state.submit(); assert.equal(state.posted().expected_shipping_fee, 245);
});
test('free shipping uses the same before-voucher threshold as placement and pickup remains zero', () => {
  const props = fixture(); props.items[0].price = 2000;
  const state = checkout(props); state.appliedVouchers.value.platform = { discount: 100 };
  assert.equal(state.rawShippingFee.value, 0);
  state.form.shipping_method = 'pickup'; assert.equal(state.rawShippingFee.value, 0);
});
test('a configured remote zone can be selected while unsupported delivery never posts an order', () => {
  const props = fixture(); props.addresses[0].address = 'Itbayat, Batanes';
  const state = checkout(props); state.submit(); assert.ok(state.posted());
  const excluded = fixture(); excluded.address_serviceability[2].state = 'unsupported';
  const blocked = checkout(excluded); blocked.submit(); assert.equal(blocked.posted(), undefined);
});

function rates(fetch) {
  let dispose;
  const source = script('Pages/Logistics/DeliveryRates.vue');
  const state = new Function('ref', 'reactive', 'onMounted', 'onUnmounted', 'fetch', 'localStorage', source + '\nreturn { form, zones, fresh, busy, error, load, save, edit };')(
    ref, reactive, () => {}, cb => { dispose = cb; }, fetch, { getItem: () => 'synthetic-token' });
  return { state, dispose: () => dispose() };
}
const reply = (data, status = 200) => ({ ok: status < 400, json: async () => data });
test('rate editing does not mutate loaded records and double submit sends one mutation', async () => {
  const calls = []; let finish;
  const app = rates(async (url, options) => {
    calls.push({ url, options });
    if (!options.method) return reply({ data: [{ id: 4, name: 'NCR', province: 'Metro Manila', base_shipping_rate: '50.00' }], provinces: ['Metro Manila'] });
    return new Promise(resolve => { finish = resolve; });
  });
  await app.state.load(); app.state.edit(app.state.zones.value[0]); app.state.form.base_shipping_rate = '55.00';
  assert.equal(app.state.zones.value[0].base_shipping_rate, '50.00');
  const pending = app.state.save(); await app.state.save();
  assert.equal(calls.filter(c => c.options.method === 'PUT').length, 1);
  finish(reply({ data: {}, message: 'Saved' })); await pending;
  assert.equal(app.state.fresh.value, true);
});
test('failed mutations require a fresh read and keep the reason available for correction', async () => {
  let posts = 0;
  const app = rates(async (_url, options) => options.method ? (posts++, reply({ errors: { base_shipping_rate: ['Base rate must be positive.'] } }, 422)) : reply({ data: [], provinces: [] }));
  await app.state.load(); app.state.form.reason = 'Approved rate correction'; await app.state.save();
  assert.equal(app.state.fresh.value, false); assert.equal(app.state.form.reason, 'Approved rate correction');
  assert.match(app.state.error.value, /positive/); await app.state.save(); assert.equal(posts, 1);
});
test('unmounted rate responses cannot update the closed screen', async () => {
  let finish;
  const app = rates(() => new Promise(resolve => { finish = resolve; })); const pending = app.state.load(); app.dispose();
  finish(reply({ data: [{ id: 99 }], provinces: [] })); await pending;
  assert.deepEqual(app.state.zones.value, []); assert.equal(app.state.fresh.value, false);
});
