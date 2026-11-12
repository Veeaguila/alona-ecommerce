<template>
  <section class="app-panel app-panel--padded mt-5 space-y-4" aria-label="COD cash reconciliation">
    <h3 class="app-section-title">COD cash reconciliation</h3>
    <p class="text-sm">Collection dates and confirmed receipt dates use Manila time. These funds are separate from Seller shipment fees, Logistics earnings, Rider earnings and payouts.</p>
    <p v-if="message" role="status" class="app-feedback">{{ message }}</p>
    <div class="flex flex-wrap gap-2"><button type="button" class="app-button-secondary" :disabled="busy || loading" @click="load(page)">Refresh COD cash</button><button type="button" class="app-button-secondary" :disabled="busy || loading || !report" @click="download('csv')">Export COD CSV</button><button type="button" class="app-button-secondary" :disabled="busy || loading || !report" @click="download('xlsx')">Export COD XLSX</button></div>
    <template v-if="report">
      <dl class="grid gap-3 sm:grid-cols-3"><div><dt>Collected in date range</dt><dd>{{ money(report.totals.collected) }}</dd></div><div><dt>Confirmed received in date range</dt><dd>{{ money(report.totals.received) }}</dd></div><div><dt>Period difference (received − collected)</dt><dd :class="Number(report.totals.difference) < 0 ? 'text-red-700 font-bold' : ''">{{ money(report.totals.difference) }} <span v-if="Number(report.totals.difference) < 0 && report.records.some(row => Number(row.discrepancy_difference) < 0 && row.discrepancy_status === 'pending')">· Recorded shortfall</span></dd></div></dl>
      <p class="text-sm">A period difference is not an automatic cash adjustment. Pending remittances remain unconfirmed. Outstanding Rider custody below includes all dates.</p>
      <div class="overflow-x-auto"><table class="app-data-table"><caption class="sr-only">COD cash records for the applied date range</caption><thead><tr><th>Type / ID</th><th>Order / Tracking</th><th>Rider</th><th>Collected</th><th>Submitted</th><th>Confirmed received</th><th>Recorded discrepancy</th><th>Status / Resolution</th><th>Recorded at</th><th>Evidence</th></tr></thead><tbody><tr v-for="row in report.records" :key="`${row.record_type}-${row.record_id}`"><td>{{ row.record_type }} #{{ row.record_id }}</td><td>{{ row.order_number }}<br />{{ row.tracking_reference }}</td><td>{{ row.rider_name }}</td><td>{{ row.collected_amount == null ? '—' : money(row.collected_amount) }}</td><td>{{ row.submitted_amount == null ? '—' : money(row.submitted_amount) }}</td><td>{{ row.received_amount == null ? 'Not confirmed' : money(row.received_amount) }}</td><td :class="Number(row.discrepancy_difference) < 0 ? 'text-red-700 font-bold' : ''">{{ row.discrepancy_difference == null ? '—' : money(row.discrepancy_difference) }}<br />{{ row.discrepancy_reason }}</td><td>{{ row.status }} {{ row.discrepancy_status }}<br />{{ row.resolution }}</td><td>{{ date(row.recorded_at) }}</td><td><button type="button" class="app-button-secondary" :disabled="busy" @click="evidence(row.record_type, row.record_id)">View cash evidence</button><button v-if="row.record_type === 'remittance' && row.status === 'confirmed'" type="button" class="app-button-secondary" :disabled="busy" @click="evidence('receipt', row.record_id)">View receipt evidence</button></td></tr><tr v-if="!report.records.length"><td colspan="10">No recorded COD cash events in this range.</td></tr></tbody></table></div>
      <div class="flex gap-3 items-center"><span>{{ report.pagination.total }} cash events · Page {{ report.pagination.current_page }} of {{ report.pagination.last_page }}</span><button type="button" class="app-button-secondary" :disabled="loading || busy || page <= 1" @click="load(page - 1)">Previous COD records</button><button type="button" class="app-button-secondary" :disabled="loading || busy || page >= report.pagination.last_page" @click="load(page + 1)">Next COD records</button></div>
      <h4 class="font-semibold">Actual Rider cash custody · all dates</h4>
      <p v-for="balance in report.balances" :key="balance.rider_id">{{ balance.name }}: outstanding {{ money(balance.outstanding) }} · awaiting confirmation {{ money(balance.pending) }}</p>
      <h4 class="font-semibold">Submitted cash awaiting Logistics verification · all dates</h4>
      <article v-for="row in report.pending_remittances" :key="row.id" class="border-t pt-3"><p>Remittance #{{ row.id }} · {{ row.rider?.name }} · Rider submitted {{ money(row.submitted_amount) }}</p><button type="button" class="app-button-secondary mt-2" :disabled="busy" @click="open(row)">Verify cash receipt</button></article>
      <form v-if="selected" class="border-t pt-3 space-y-3" aria-label="Confirm COD cash receipt" @submit.prevent="confirm">
        <h4 class="font-semibold">Verify actual cash for remittance #{{ selected.id }}</h4>
        <label class="block">Actual cash received<input v-model="amount" aria-label="Actual cash received" type="text" inputmode="decimal" class="app-input" :disabled="busy" /></label>
        <label class="block">Receipt evidence<input type="file" accept="image/*" aria-label="Receipt evidence" :disabled="busy" @change="file = $event.target.files?.[0] || null" /></label>
        <label class="flex gap-2"><input v-model="declareShortfall" type="checkbox" :disabled="busy" /> Record remaining custody balance as a shortfall for Admin review</label>
        <label class="block">Discrepancy reason (required for a difference or shortfall)<textarea v-model="reason" aria-label="Cash discrepancy reason" class="app-input" maxlength="2000" :disabled="busy"></textarea></label>
        <label class="flex gap-2"><input v-model="physicalConfirmation" type="checkbox" :disabled="busy" /> I verified the actual cash and evidence.</label>
        <div class="flex gap-2"><button type="submit" class="app-button-primary" :disabled="busy || !physicalConfirmation">Confirm actual cash receipt</button><button type="button" class="app-button-secondary" :disabled="busy" @click="selected = null">Cancel cash verification</button></div>
      </form>
      <details><summary>View immutable COD audit</summary><ol class="mt-3 space-y-3"><li v-for="event in report.audits" :key="event.event_key">{{ event.action }} · {{ event.actor?.name }} · {{ date(event.created_at) }}<pre class="whitespace-pre-wrap text-sm">{{ JSON.stringify(event.data) }}</pre></li></ol></details>
    </template>
  </section>
</template>

<script setup>
import { onUnmounted, ref, watch } from 'vue';
const props = defineProps({ range: { type: Object, required: true }, token: { type: String, required: true } });
const report = ref(null), page = ref(1), loading = ref(false), busy = ref(false), message = ref(''), selected = ref(null);
const amount = ref(''), file = ref(null), reason = ref(''), declareShortfall = ref(false), physicalConfirmation = ref(false);
let revision = 0; const urls = new Set();
const headers = () => ({ Accept: 'application/json', Authorization: `Bearer ${props.token}` });
const money = value => `${Number(value) < 0 ? '−' : ''}₱${Math.abs(Number(value)).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const date = value => value ? new Date(value).toLocaleString('en-PH', { timeZone: 'Asia/Manila' }) : 'Not recorded';
const query = () => new URLSearchParams({ from: props.range.from, to: props.range.to });
async function load(next = 1) {
  const current = ++revision; loading.value = true;
  try {
    const params = query(); params.set('page', String(next)); const response = await fetch('/api/sorting-center/cod?'+params, { headers: headers() }); const data = await response.json();
    if (current !== revision) return;
    if (!response.ok) throw new Error(data.message || 'COD records could not be loaded.');
    report.value = data.data; page.value = next;
  } catch (error) { if (current === revision) { report.value = null; message.value = error.message; } }
  finally { if (current === revision) loading.value = false; }
}
function open(row) { selected.value = row; amount.value = ''; file.value = null; reason.value = ''; declareShortfall.value = false; physicalConfirmation.value = false; message.value = ''; }
async function confirm() {
  if (busy.value || !selected.value || !physicalConfirmation.value) return;
  if (!/^[0-9]{1,10}(\.[0-9]{1,2})?$/.test(amount.value) || !file.value) { message.value = 'Enter the actual cash amount and attach receipt evidence.'; return; }
  busy.value = true;
  try {
    const body = new FormData(); body.append('amount', amount.value); body.append('file', file.value); body.append('declare_shortfall', declareShortfall.value ? '1' : '0'); if (reason.value.trim()) body.append('reason', reason.value.trim());
    const response = await fetch(`/api/sorting-center/cod/remittances/${selected.value.id}/confirm`, { method: 'POST', headers: headers(), body }); const data = await response.json();
    if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Cash receipt could not be confirmed.');
    selected.value = null; message.value = data.message; await load(page.value);
  } catch (error) { message.value = error.message; }
  finally { busy.value = false; }
}
async function saveResponse(response, filename) {
  if (!response.ok || (response.headers.get('Content-Type') || '').includes('application/json') || (response.headers.get('Content-Type') || '').includes('text/html')) throw new Error('Download is unavailable. Refresh and retry.');
  const url = URL.createObjectURL(await response.blob()); urls.add(url); const link = document.createElement('a'); link.href = url; link.download = filename; link.click();
}
async function download(format) {
  if (busy.value || loading.value || !report.value) return; busy.value = true;
  try { const params = query(); params.set('format', format); await saveResponse(await fetch('/api/sorting-center/cod/export?'+params, { headers: headers() }), `cod-${props.range.from}-to-${props.range.to}.${format}`); }
  catch (error) { message.value = error.message; } finally { busy.value = false; }
}
async function evidence(kind, id) {
  if (busy.value) return; busy.value = true;
  try { await saveResponse(await fetch(`/api/cod/evidence/${kind}/${id}`, { headers: headers() }), `cod-${kind}-${id}`); }
  catch (error) { message.value = error.message; } finally { busy.value = false; }
}
watch(() => props.range, () => { selected.value = null; message.value = ''; load(1); }, { immediate: true, deep: true });
onUnmounted(() => { revision++; for (const url of urls) URL.revokeObjectURL(url); });
</script>
