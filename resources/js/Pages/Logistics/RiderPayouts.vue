<template>
  <sc-layout title="Rider payouts" subtitle="Review completed work and record your company's actual payments.">
    <div class="space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="app-description">COD cash stays in the <a href="/sorting-center/delivery-reports" class="underline">Cash Desk</a>. These are job earnings, not your company's profit or a wallet balance.</p>
        <button class="app-button-secondary" :disabled="busy" @click="load()">Refresh</button>
      </div>
      <p v-if="error" role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-800">{{ error }}</p>
      <p v-if="notice" role="status" class="rounded-xl border border-teal-200 bg-teal-50 p-4 text-teal-900">{{ notice }}</p>
      <template v-if="data">
        <div class="grid gap-4 sm:grid-cols-3">
          <div v-for="card in [['unpaid', 'Earned, unpaid'], ['processing', 'Payment being processed'], ['paid', 'Recorded payments']]" :key="card[0]" class="app-panel app-panel--padded">
            <p class="app-description">{{ card[1] }}</p><p class="mt-2 text-2xl font-bold text-teal-800">PHP {{ data.summary[card[0]] }}</p>
          </div>
        </div>
        <div class="flex flex-wrap gap-2" aria-label="Payroll sections">
          <button v-for="item in [['payouts', 'Payouts'], ['rates', 'Job rates'], ['trips', 'Transfer trips']]" :key="item[0]" :class="tab === item[0] ? 'app-button-primary' : 'app-button-secondary'" :aria-pressed="tab === item[0]" :disabled="busy" @click="tab = item[0]">{{ item[1] }}</button>
        </div>
        <section v-if="tab === 'rates'" class="app-panel app-panel--padded space-y-4">
          <h2 class="app-section-title">Approved job rates</h2>
          <p class="app-description">One pickup and one delivery rate per Seller shipment, even with several parcels. Rates are saved at assignment. Earlier jobs are not assigned a guessed rate.</p>
          <form class="space-y-4" @submit.prevent="saveRates">
            <label class="flex items-center gap-2"><input v-model="rates.enabled" type="checkbox" :disabled="busy" /> Enable earnings for future assignments</label>
            <div class="grid gap-4 sm:grid-cols-3">
              <label v-for="item in [['pickup', 'Pickup per Seller shipment'], ['delivery', 'Delivery per Seller shipment'], ['transfer', 'Transfer per complete trip (optional)']]" :key="item[0]" class="block text-sm">{{ item[1] }}<input v-model.trim="rates[item[0]]" inputmode="decimal" placeholder="Approved PHP amount" :required="rates.enabled && item[0] !== 'transfer'" :disabled="busy" class="app-input mt-1 w-full" /></label>
            </div>
            <p class="app-description">Your company must fund the approved rates. Check shipping fees and any free-shipping subsidy; the system will not take Rider pay from COD collections.</p>
            <button class="app-button-primary" :disabled="busy || !fresh">Save approved rates</button>
          </form>
        </section>
        <template v-if="tab === 'payouts'">
          <p v-if="!data.settings.enabled" class="app-panel app-panel--padded">Job earnings are not enabled. Set your approved rates in Job rates before new assignments. Historical Seller-funded earnings remain separate.</p>
          <section class="app-panel app-panel--padded space-y-4">
            <h2 class="app-section-title">Group completed jobs into one payout</h2>
            <form class="grid items-end gap-3 sm:grid-cols-4" @submit.prevent="loadReview">
              <label class="text-sm">Rider<select v-model="selectedRider" class="app-input mt-1 w-full" required :disabled="busy" @change="review = null"><option value="">Choose Rider</option><option v-for="rider in data.riders" :key="rider.id" :value="rider.id">{{ rider.name }}</option></select></label>
              <label class="text-sm">From (optional)<input v-model="from" type="date" class="app-input mt-1 w-full" :disabled="busy" @change="review = null" /></label>
              <label class="text-sm">To (optional)<input v-model="to" type="date" class="app-input mt-1 w-full" :disabled="busy" @change="review = null" /></label>
              <button class="app-button-secondary" :disabled="busy || !fresh || !selectedRider">Review unpaid jobs</button>
            </form>
            <div v-if="review" class="space-y-3">
              <p class="font-semibold">{{ review.jobs.length }} completed jobs · PHP {{ review.amount }}</p>
              <p v-if="review.has_more" class="app-description">This batch contains the oldest 500 matching jobs. Prepare another batch for the remaining jobs.</p>
              <div class="max-h-64 overflow-auto"><table class="w-full text-left text-sm"><thead><tr><th class="p-2">Shipment / trip</th><th class="p-2">Work</th><th class="p-2">PHP</th></tr></thead><tbody><tr v-for="job in review.jobs" :key="job.id"><td class="p-2">{{ job.reference }}</td><td class="p-2">{{ job.leg }}</td><td class="p-2">{{ job.amount }}</td></tr></tbody></table></div>
              <p v-if="!review.jobs.length">No completed unpaid jobs match this review.</p>
              <p v-else-if="!review.destinations.length">Ask the Rider to save receiving instructions in My Earnings.</p>
              <form v-else class="space-y-3" @submit.prevent="prepare">
                <label class="block max-w-lg text-sm">Rider-provided receiving method<select v-model="destination" required class="app-input mt-1 w-full" :disabled="busy"><option value="">Choose receiving method</option><option v-for="item in review.destinations" :key="item.id" :value="item.id">{{ item.label }}</option></select></label>
                <label class="flex items-start gap-2"><input v-model="reviewed" type="checkbox" :disabled="busy" /> I reviewed these jobs, total and receiving method.</label>
                <button class="app-button-primary" :disabled="busy || !fresh || !reviewed || !destination">Prepare grouped payout</button>
              </form>
            </div>
          </section>
          <section class="app-panel app-panel--padded space-y-4">
            <h2 class="app-section-title">Payouts and payment history</h2>
            <p class="app-description">Preparation reserves the listed earnings. Make the actual payment using company payroll funds, then record its proof. An unsent payout can be cancelled; an actual payment cannot.</p>
            <article v-for="batch in data.payouts.data" :key="batch.id" class="rounded-xl border border-slate-200 p-4">
              <div class="flex flex-wrap items-center justify-between gap-2"><div><strong>{{ riderName(batch.rider_id) }} · PHP {{ batch.amount }}</strong><p class="app-description">{{ batch.items_count }} jobs · {{ batch.destination.label }}</p></div><span class="rounded-full bg-teal-50 px-3 py-1 text-sm">{{ batch.status === 'prepared' ? 'Processing' : batch.status }}</span></div>
              <p v-if="batch.paid_at" class="mt-2 text-sm">Recorded {{ date(batch.paid_at) }} · {{ batch.reference }} · {{ batch.acknowledged_at ? 'Rider confirmed receipt' : 'Awaiting Rider receipt confirmation' }}</p>
              <button v-if="batch.has_proof" class="app-button-secondary mt-3" :disabled="busy" @click="proof(batch)">Load payment receipt</button>
              <a v-if="proofUrls[batch.id]" :href="proofUrls[batch.id]" :download="`payout-${batch.id}-receipt`" class="ml-3 underline">Download private receipt</a>
              <template v-if="batch.status === 'prepared'">
                <button class="app-button-secondary mt-3" :disabled="busy" @click="details(batch)">Review receiving instructions</button>
                <div v-if="detail?.id === batch.id" class="mt-3 space-y-3 rounded-xl bg-slate-50 p-4">
                  <p>{{ detail.instructions.account_name }} · {{ detail.destination.method }} · {{ detail.instructions.institution }} {{ detail.instructions.account_number }}</p>
                  <form class="space-y-3" @submit.prevent="payBatch(batch)">
                    <label class="block max-w-lg text-sm">Actual payment / cash receipt reference<input v-model.trim="payment.reference" required minlength="3" maxlength="120" :disabled="busy" class="app-input mt-1 w-full" /></label>
                    <label class="block text-sm">Payment proof (image or PDF, up to 5 MB)<input type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required :disabled="busy" class="mt-1 block w-full" @change="payment.file = $event.target.files?.[0] || null" /></label>
                    <label class="flex items-start gap-2"><input v-model="payment.confirmed" type="checkbox" :disabled="busy" /> I actually paid PHP {{ batch.amount }} to these instructions using company payroll funds, without deducting COD cash.</label>
                    <button class="app-button-primary" :disabled="busy || !fresh || !payment.confirmed || !payment.file || payment.reference.length < 3">Record actual payment</button>
                  </form>
                  <details><summary class="cursor-pointer text-sm underline">Cancel a payout that was never sent</summary><form class="mt-3 space-y-3" @submit.prevent="cancelBatch(batch)"><label class="block text-sm">Reason<input v-model.trim="cancelReason" required minlength="3" maxlength="500" :disabled="busy" class="app-input mt-1 w-full" /></label><label class="flex items-center gap-2"><input v-model="neverSent" type="checkbox" :disabled="busy" /> No money was sent for this payout.</label><button class="app-button-secondary" :disabled="busy || !fresh || !neverSent">Cancel unsent payout</button></form></details>
                </div>
              </template>
            </article>
            <p v-if="!data.payouts.data.length">No grouped payouts yet.</p>
            <div class="flex items-center justify-between"><button class="app-button-secondary" :disabled="busy || data.payouts.current_page <= 1" @click="load(data.payouts.current_page - 1)">Previous</button><span>Page {{ data.payouts.current_page }} of {{ data.payouts.last_page }}</span><button class="app-button-secondary" :disabled="busy || data.payouts.current_page >= data.payouts.last_page" @click="load(data.payouts.current_page + 1)">Next</button></div>
          </section>
        </template>
        <section v-if="tab === 'trips'" class="app-panel app-panel--padded space-y-4">
          <h2 class="app-section-title">One transfer earning per complete trip</h2>
          <p class="app-description">Select the actual origin and all parcels leaving together. Include complete Seller shipments. A closed manifest earns once, after every parcel is received at its destination hub.</p>
          <button class="app-button-secondary" :disabled="busy" @click="loadNetwork">Load hubs and transfer jobs</button>
          <template v-if="network">
            <label class="block max-w-lg text-sm">Origin hub<select v-model="origin" class="app-input mt-1 w-full" :disabled="busy" @change="loadTripJobs()"><option value="">Choose origin</option><option v-for="hub in network.hubs.filter(h => h.is_active)" :key="hub.id" :value="hub.id">{{ hub.name }}</option></select></label>
            <form v-if="origin" class="space-y-3" @submit.prevent="dispatchTrip">
              <label class="block max-w-lg text-sm">Transfer Rider (must be Online)<select v-model="driver" required :disabled="busy" class="app-input mt-1 w-full"><option value="">Choose Rider</option><option v-for="rider in network.riders.filter(r => r.is_active && r.approval_status === 'approved' && Number(r.logistics_hub_id) === Number(origin))" :key="rider.id" :value="rider.id">{{ rider.name }}</option></select></label>
              <label class="block max-w-lg text-sm">Unique trip / manifest reference<input v-model.trim="tripReference" required minlength="3" maxlength="100" :disabled="busy" class="app-input mt-1 w-full" /></label>
              <div class="space-y-2"><label v-for="job in tripJobs.data" :key="job.id" class="flex items-center gap-2 rounded-lg border p-3"><input v-model="tripIds" type="checkbox" :value="job.parcel.id" :disabled="busy" /> {{ job.parcel.order_id }} → {{ network.hubs.find(h => h.id === job.destination_hub_id)?.name }}</label></div>
              <div class="flex items-center justify-between"><button type="button" class="app-button-secondary" :disabled="busy || tripJobs.current_page <= 1" @click="loadTripJobs(tripJobs.current_page - 1, true)">Previous parcels</button><span>Page {{ tripJobs.current_page }} of {{ tripJobs.last_page }}</span><button type="button" class="app-button-secondary" :disabled="busy || tripJobs.current_page >= tripJobs.last_page" @click="loadTripJobs(tripJobs.current_page + 1, true)">Next parcels</button></div>
              <label class="flex items-start gap-2"><input v-model="tripConfirmed" type="checkbox" :disabled="busy" /> These {{ tripIds.length }} parcels form the complete manifest and are physically leaving together for one destination.</label>
              <button class="app-button-primary" :disabled="busy || !fresh || !tripConfirmed || !tripIds.length || !driver || !data.settings.transfer">Dispatch complete trip</button>
            </form>
          </template>
          <p class="app-description">Destination staff receive each parcel through the existing hub workspace. Existing shipment transfers without a compensated manifest keep their original workflow.</p>
        </section>
      </template>
    </div>
  </sc-layout>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted } from 'vue';
import ScLayout from '@/Layouts/ScLayout.vue';
import { createRequestKey } from '@/requestKey.js';

const data = ref(null), error = ref(''), notice = ref(''), busy = ref(false), fresh = ref(false), tab = ref('payouts');
const selectedRider = ref(''), from = ref(''), to = ref(''), review = ref(null), destination = ref(''), reviewed = ref(false), reviewKey = ref('');
const rates = reactive({ enabled: false, revision: 0, pickup: '', delivery: '', transfer: '' });
const detail = ref(null), payment = reactive({ reference: '', file: null, confirmed: false, key: '' }), cancelReason = ref(''), neverSent = ref(false), proofUrls = reactive({});
const network = ref(null), origin = ref(''), driver = ref(''), tripReference = ref(''), tripConfirmed = ref(false), tripIds = ref([]), tripKey = ref(createRequestKey()), tripJobs = ref({ data: [], current_page: 1, last_page: 1 });
let disposed = false, revision = 0;
onUnmounted(() => { disposed = true; revision++; Object.values(proofUrls).forEach(URL.revokeObjectURL); });
const date = value => new Date(value).toLocaleString();
const riderName = id => data.value?.riders.find(r => r.id === id)?.name || `Rider #${id}`;
async function request(path = '', method = 'GET', body) {
  const token = localStorage.getItem('logistics_auth_token');
  if (!token) throw new Error('Sign in to manage company payouts.');
  const multipart = body instanceof FormData;
  const response = await fetch('/api/sorting-center/' + path, { method, headers: { Accept: 'application/json', Authorization: `Bearer ${token}`, ...(!multipart && body ? { 'Content-Type': 'application/json' } : {}) }, ...(body ? { body: multipart ? body : JSON.stringify(body) } : {}) });
  const result = await response.json().catch(() => null);
  if (!response.ok) throw new Error(Object.values(result?.errors || {}).flat()[0] || result?.message || 'Request failed. Refresh to check the saved state.');
  if (!result || typeof result !== 'object') throw new Error('The response could not be read. Refresh before continuing.');
  return result;
}
async function action(fn, message) {
  if (busy.value) return;
  busy.value = true; error.value = ''; notice.value = '';
  try { await fn(); if (!disposed) notice.value = message; }
  catch (failure) { if (!disposed) { error.value = failure.message; fresh.value = false; } }
  finally { if (!disposed) busy.value = false; }
}
async function read(page = 1) {
  const current = ++revision;
  const result = await request(`payroll?payouts_page=${page}`);
  if (disposed || current !== revision) return;
  data.value = result.data; Object.assign(rates, result.data.settings); fresh.value = true;
}
async function load(page = 1) { await action(() => read(page), ''); }
async function saveRates() { await action(async () => { await request('payroll/settings', 'PUT', { ...rates, transfer: rates.transfer || null }); await read(); }, 'Approved rates saved for future assignments.'); }
async function loadReview() { await action(async () => { const result = await request(`payroll/riders/${selectedRider.value}/review?` + new URLSearchParams({ ...(from.value ? { from: from.value } : {}), ...(to.value ? { to: to.value } : {}) })); if (disposed) return; review.value = result.data; reviewed.value = false; destination.value = ''; reviewKey.value = createRequestKey(); }, ''); }
async function prepare() { await action(async () => { await request('payroll/payouts', 'POST', { rider_id: selectedRider.value, destination_id: destination.value, earning_ids: review.value.jobs.map(j => j.id), expected_amount: review.value.amount, request_key: reviewKey.value, reviewed: reviewed.value }); if (disposed) return; review.value = null; await read(); }, 'Grouped payout prepared. Review receiving instructions, then record the actual payment.'); }
async function details(batch) { await action(async () => { const result = await request(`payroll/payouts/${batch.id}`); if (disposed) return; detail.value = result.data; Object.assign(payment, { reference: '', file: null, confirmed: false, key: createRequestKey() }); cancelReason.value = ''; neverSent.value = false; }, ''); }
async function payBatch(batch) { if (!fresh.value || !payment.confirmed || !payment.file || payment.reference.trim().length < 3) return; await action(async () => { const body = new FormData(); for (const [key, value] of Object.entries({ expected_amount: batch.amount, payment_key: payment.key, reference: payment.reference, actually_paid: '1', paid_from_payroll: '1' })) body.append(key, value); body.append('proof', payment.file); await request(`payroll/payouts/${batch.id}/pay`, 'POST', body); if (disposed) return; detail.value = null; await read(); }, 'Actual payment recorded. The Rider can confirm receipt.'); }
async function cancelBatch(batch) { await action(async () => { await request(`payroll/payouts/${batch.id}/cancel`, 'POST', { reason: cancelReason.value, not_sent: neverSent.value }); if (disposed) return; detail.value = null; await read(); }, 'Unsent payout cancelled. Its completed jobs are unpaid and available again.'); }
async function proof(batch) { await action(async () => { const response = await fetch(`/api/sorting-center/payroll/payouts/${batch.id}/proof`, { headers: { Authorization: `Bearer ${localStorage.getItem('logistics_auth_token')}`, Accept: 'application/octet-stream' } }); if (!response.ok) throw new Error('The private receipt could not be loaded.'); const blob = await response.blob(); if (disposed) return; if (proofUrls[batch.id]) URL.revokeObjectURL(proofUrls[batch.id]); proofUrls[batch.id] = URL.createObjectURL(blob); }, ''); }
async function loadNetwork() { await action(async () => { const result = await request('hub-network'); if (!disposed) network.value = result.data; }, ''); }
async function loadTripJobs(page = 1, keep = false) { await action(async () => { const result = await request('hub-journeys?' + new URLSearchParams({ hub_id: origin.value, stage: 'dispatch_ready', page })); if (disposed) return; tripJobs.value = result.data; if (!keep) { tripIds.value = []; driver.value = ''; tripConfirmed.value = false; tripKey.value = createRequestKey(); } }, ''); }
async function dispatchTrip() { await action(async () => { await request('payroll/trips', 'POST', { rider_id: driver.value, hub_id: origin.value, parcel_ids: tripIds.value, transport_reference: tripReference.value, request_key: tripKey.value, physical_confirmation: tripConfirmed.value }); if (disposed) return; tripIds.value = []; tripConfirmed.value = false; tripKey.value = createRequestKey(); const result = await request('hub-journeys?' + new URLSearchParams({ hub_id: origin.value, stage: 'dispatch_ready', page: 1 })); if (!disposed) tripJobs.value = result.data; }, 'Trip dispatched. Its single transfer earning waits for complete destination receipt.'); }
onMounted(() => load());
</script>
