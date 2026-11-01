<template>
  <section class="mt-5 rounded-2xl border border-slate-200 bg-white p-5" aria-label="Admin loss claim review">
    <h2 class="text-lg font-bold">Lost parcel claims</h2>
    <p class="mt-2 text-sm">Reported loss claims awaiting review. This queue does not approve compensation or execute refunds.</p>
    <p v-if="message" role="status" class="mt-3">{{ message }}</p>
    <button type="button" class="app-button-secondary mt-3" :disabled="loading" @click="load(page)">Refresh loss claims</button>
    <article v-for="row in rows" :key="row.id" class="mt-4 border-t pt-3">
      <h3 class="font-semibold">{{ row.claim_reference }} · Pending review · {{ row.parcel?.order_id }}</h3>
      <p>{{ row.reason }}</p>
      <p>Seller: {{ row.parcel?.seller?.name || 'Unavailable' }} · Buyer: {{ row.parcel?.buyer?.name || 'Unavailable' }}</p>
      <p>Reported by {{ row.reporter?.name || 'Unavailable' }} · {{ new Date(row.created_at).toLocaleString() }}</p>
      <button v-if="row.has_evidence" type="button" class="app-button-secondary mt-2" @click="evidence(row.id)">View loss claim evidence</button>
    </article>
    <p v-if="!loading && !rows.length" class="mt-3">No loss claims recorded.</p>
    <div class="mt-3 flex gap-2"><button class="app-button-secondary" :disabled="loading || page <= 1" @click="load(page - 1)">Previous loss claims</button><span>Page {{ page }} of {{ lastPage }}</span><button class="app-button-secondary" :disabled="loading || page >= lastPage" @click="load(page + 1)">Next loss claims</button></div>
  </section>
</template>
<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';
const rows = ref([]), page = ref(1), lastPage = ref(1), loading = ref(false), message = ref(''), urls = new Set();
let disposed = false;
async function load(next = 1) {
  loading.value = true; message.value = '';
  try {
    const { data } = await axios.get('/api/admin/logistics-claims', { params: { page: next } });
    if (!disposed) { rows.value = data.data.data; page.value = data.data.current_page; lastPage.value = data.data.last_page; }
  } catch (error) { if (!disposed) message.value = error.response?.data?.message || 'Claims could not be loaded.'; }
  finally { loading.value = false; }
}
async function evidence(id) {
  try {
    const { data } = await axios.get(`/api/admin/logistics-claims/${id}/evidence`, { responseType: 'blob' });
    if (disposed) return;
    const url = URL.createObjectURL(data); urls.add(url);
    const link = document.createElement('a'); link.href = url; link.download = `loss-claim-${id}`; link.click();
  } catch { message.value = 'Evidence could not be opened.'; }
}
onMounted(() => load());
onUnmounted(() => { disposed = true; for (const url of urls) URL.revokeObjectURL(url); });
</script>
