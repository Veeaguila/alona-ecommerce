<template>
  <section class="app-panel app-panel--padded mt-4" aria-label="Return to Seller queue">
    <h2 class="app-section-title">Return to Seller queue</h2>
    <p class="mt-2 text-sm">Three failed delivery attempts create eligibility. Logistics initiates physical return with the current custodian. Use supervised handover before assigning a different Rider. Only Seller receipt confirmation restocks inventory.</p>
    <p v-if="message" role="status" class="app-feedback mt-3">{{ message }}</p>
    <button type="button" class="app-button-secondary mt-3" :disabled="busy || loading" @click="load(page)">{{ loaded ? 'Refresh Return to Seller queue' : 'Open Return to Seller queue' }}</button>
    <p v-if="loaded && !rows.length" class="mt-3">No Return to Seller records.</p>
    <article v-for="row in rows" :key="row.id" class="mt-4 border-t pt-3">
      <h3 class="font-semibold">{{ row.parcel?.order_id }} · {{ label(row.status) }}</h3>
      <p>Seller: {{ row.parcel?.seller?.name }} · Buyer: {{ row.parcel?.buyer?.name }}</p>
      <p>Current custodian: {{ row.parcel?.assigned_rider?.name || 'Needs Logistics review' }}</p>
      <ol class="mt-2 list-decimal pl-5"><li v-for="attempt in row.parcel?.delivery_attempts || []" :key="attempt.id">Attempt {{ attempt.attempt_number }}: {{ attempt.reason }} · {{ date(attempt.created_at) }}</li></ol>
      <form v-if="row.status === 'eligible'" class="mt-3 space-y-3" @submit.prevent="initiate(row)">
        <label class="block">Return Rider<select class="app-input" :aria-label="`Return Rider for ${row.parcel?.order_id}`" :disabled="busy"><option :value="row.parcel?.assigned_rider_id">{{ row.parcel?.assigned_rider?.name || 'No verified custodian' }}</option></select></label>
        <a href="/sorting-center/delivery-monitoring" class="app-button-secondary inline-flex">Supervise a different Rider handover</a>
        <label class="block">Return reason<textarea v-model="reasons[row.id]" :aria-label="`Return reason for ${row.parcel?.order_id}`" class="app-input" maxlength="2000" :disabled="busy"></textarea></label>
        <button type="submit" class="app-button-primary" :disabled="busy || !row.parcel?.assigned_rider_id">Initiate Return to Seller</button>
      </form>
      <p v-else class="mt-2">Return Rider: {{ row.rider?.name }} · {{ row.reason }}</p>
      <p v-if="row.picked_up_at">Return pickup: {{ date(row.picked_up_at) }}</p><p v-if="row.handed_over_at">Rider handover: {{ date(row.handed_over_at) }}</p><p v-if="row.seller_received_at">Seller receipt: {{ date(row.seller_received_at) }} · Restocked: {{ date(row.restocked_at) }}</p>
    </article>
    <div v-if="loaded" class="mt-3 flex gap-2"><button type="button" class="app-button-secondary" :disabled="busy || loading || page <= 1" @click="load(page - 1)">Previous returns</button><span>Page {{ page }} of {{ lastPage }}</span><button type="button" class="app-button-secondary" :disabled="busy || loading || page >= lastPage" @click="load(page + 1)">Next returns</button></div>
  </section>
</template>
<script setup>
import { reactive, ref } from 'vue';
const props = defineProps({ token: { type: String, required: true } });
const rows = ref([]), reasons = reactive({}), loaded = ref(false), loading = ref(false), busy = ref(false), message = ref(''), page = ref(1), lastPage = ref(1);
const label = status => ({ eligible: 'Return to Sender', initiated: 'Return initiated', in_transit: 'In transit to Seller', handed_over: 'Returned to Sender — awaiting Seller confirmation', received: 'Returned to Sender — Seller confirmed' })[status] || status;
const date = value => new Date(value).toLocaleString('en-PH', { timeZone: 'Asia/Manila' });
const headers = () => ({ Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${props.token}` });
async function load(next = 1) {
  loading.value = true;
  try { const response = await fetch('/api/sorting-center/returns?page='+next, { headers: headers() }); const data = await response.json(); if (!response.ok) throw new Error(data.message || 'Return queue could not be loaded.'); rows.value = data.data.data; page.value = data.data.current_page; lastPage.value = data.data.last_page; loaded.value = true; }
  catch (error) { message.value = error.message; } finally { loading.value = false; }
}
async function initiate(row) {
  if (busy.value) return; const reason = (reasons[row.id] || '').trim(); if (reason.length < 10) { message.value = 'Enter an actual return reason of at least 10 characters.'; return; } busy.value = true;
  try { const response = await fetch(`/api/sorting-center/returns/${row.id}/initiate`, { method: 'POST', headers: headers(), body: JSON.stringify({ rider_id: row.parcel.assigned_rider_id, reason }) }); const data = await response.json(); if (!response.ok) throw new Error(data.message || 'Return could not be initiated.'); message.value = data.message; await load(page.value); }
  catch (error) { message.value = error.message; } finally { busy.value = false; }
}
</script>
