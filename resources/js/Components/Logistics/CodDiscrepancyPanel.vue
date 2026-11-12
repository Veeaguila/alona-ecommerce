<template>
  <section class="mt-5 rounded-2xl border border-slate-200 bg-white p-5" aria-label="Admin COD discrepancy review">
    <h2 class="text-lg font-bold">COD discrepancy resolutions</h2>
    <p class="mt-2 text-sm">Verify the recorded discrepancy and supporting cash receipts. Approval records your resolution; only confirmed cash receipts change cash balances. This does not modify shipment fees, earnings, refunds or payouts.</p>
    <p v-if="message" role="status" class="mt-3 text-sm">{{ message }}</p>
    <button type="button" class="app-button-secondary mt-3" :disabled="busy || loading" @click="load(page)">Refresh COD discrepancies</button>
    <article v-for="row in rows" :key="row.id" class="mt-4 border-t pt-3">
      <p>Discrepancy #{{ row.id }} · {{ row.remittance?.rider?.name }} · {{ row.difference }} PHP · {{ row.status }}</p>
      <p>{{ row.reason }}</p>
      <p>Remittance #{{ row.cod_remittance_id }}: submitted {{ row.remittance?.submitted_amount }}, Logistics confirmed {{ row.remittance?.received_amount }} PHP</p>
      <button type="button" class="app-button-secondary mt-2" :disabled="busy" @click="evidence(row.id)">View discrepancy evidence</button>
      <form v-if="row.status === 'pending'" class="mt-3 space-y-3" @submit.prevent="approve(row.id)">
        <label class="block">Final resolution<textarea v-model="resolutions[row.id]" :aria-label="`Final resolution for discrepancy ${row.id}`" maxlength="2000" class="app-input" :disabled="busy"></textarea></label>
        <label class="flex gap-2"><input v-model="verified[row.id]" type="checkbox" :disabled="busy" /> I verified the resolution and supporting cash records.</label>
        <button type="submit" class="app-button-primary" :disabled="busy || !verified[row.id]">Approve COD resolution</button>
      </form>
      <p v-else class="mt-2">Resolution: {{ row.resolution }} · {{ new Date(row.approved_at).toLocaleString() }} · Admin #{{ row.approved_by }}</p>
    </article>
    <p v-if="!loading && !rows.length" class="mt-3">No COD discrepancies recorded.</p>
    <div class="mt-3 flex gap-2"><button class="app-button-secondary" :disabled="busy || loading || page <= 1" @click="load(page - 1)">Previous discrepancies</button><span>Page {{ page }} of {{ lastPage }}</span><button class="app-button-secondary" :disabled="busy || loading || page >= lastPage" @click="load(page + 1)">Next discrepancies</button></div>
  </section>
</template>

<script setup>
import { onMounted, onUnmounted, reactive, ref } from 'vue';
import axios from 'axios';
const rows = ref([]), loading = ref(false), busy = ref(false), message = ref(''), page = ref(1), lastPage = ref(1);
const resolutions = reactive({}), verified = reactive({}), urls = new Set();
async function load(next = 1) {
  loading.value = true;
  try { const { data } = await axios.get('/api/admin/cod/discrepancies', { params: { page: next } }); rows.value = data.data.data; page.value = data.data.current_page; lastPage.value = data.data.last_page; }
  catch (error) { message.value = error.response?.data?.message || 'COD discrepancies could not be loaded.'; }
  finally { loading.value = false; }
}
async function approve(id) {
  if (busy.value || !verified[id]) return;
  const resolution = (resolutions[id] || '').trim(); if (resolution.length < 10) { message.value = 'Enter a final resolution of at least 10 characters.'; return; }
  busy.value = true;
  try { const { data } = await axios.post(`/api/admin/cod/discrepancies/${id}/approve`, { resolution }); message.value = data.message; await load(page.value); }
  catch (error) { message.value = Object.values(error.response?.data?.errors || {}).flat()[0] || error.response?.data?.message || 'Resolution approval failed.'; }
  finally { busy.value = false; }
}
async function evidence(id) {
  if (busy.value) return; busy.value = true;
  try { const response = await axios.get(`/api/cod/evidence/discrepancy/${id}`, { responseType: 'blob' }); if (!(response.headers['content-type'] || '').startsWith('image/')) throw new Error('Evidence is unavailable.'); const url = URL.createObjectURL(response.data); urls.add(url); const link = document.createElement('a'); link.href = url; link.download = `cod-discrepancy-${id}`; link.click(); }
  catch { message.value = 'Discrepancy evidence could not be opened.'; } finally { busy.value = false; }
}
onMounted(() => load()); onUnmounted(() => { for (const url of urls) URL.revokeObjectURL(url); });
</script>
