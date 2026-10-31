<template>
  <section class="app-panel app-panel--padded mt-4" aria-label="Parcel operations">
    <div class="flex justify-between gap-4"><h2 class="app-section-title">Parcel {{ parcel.order_id }}</h2><button class="app-button-secondary" @click="$emit('close')">Close parcel operations</button></div>
    <p class="mt-2">Current status: {{ parcel.status }}</p>
    <button v-if="parcel.status === 'OUT_FOR_DELIVERY'" type="button" class="app-button-secondary mt-2" @click="handoverOpen = true">Supervised delivery handover</button>
    <parcel-handover-panel v-if="handoverOpen" :parcel-id="parcel.id" leg="delivery" :token="token" @close="handoverOpen = false" @confirmed="$emit('updated', parcel.status)" />
    <p v-if="message" role="status" class="app-feedback mt-3">{{ message }}</p>
    <form v-if="['PICKED_UP', 'AT_SORTING_CENTER', 'IN_HUB_TRANSIT', 'OUT_FOR_DELIVERY'].includes(parcel.status)" class="mt-4 space-y-3" @submit.prevent="submit">
      <label class="block">Operation
        <select v-model="operation" aria-label="Parcel operation" class="app-input" :disabled="busy">
          <option v-if="parcel.status === 'OUT_FOR_DELIVERY'" value="delivered">Privileged Delivered override</option>
          <option value="lost">Report Lost</option><option value="damaged">Report Damaged</option>
        </select>
      </label>
      <p v-if="operation === 'delivered'" class="text-sm">Authorized exception to Buyer OTP and photo proof. This records delivery confirmation and a permanent audit; it does not confirm cash or issue payouts.</p>
      <p v-else class="text-sm">Record the actual incident. Refund review remains subject to the existing authorized workflow.</p>
      <label class="block">Reason<textarea v-model="reason" aria-label="Operation reason" class="app-input" maxlength="300" :disabled="busy"></textarea></label>
      <label v-if="operation !== 'delivered'" class="block">Incident photo <span v-if="operation === 'damaged'">(required)</span>
        <input :key="operation" type="file" accept="image/*" aria-label="Incident photo" :disabled="busy" @change="file = $event.target.files?.[0] || null" />
      </label>
      <label class="flex gap-2"><input v-model="confirmed" type="checkbox" :disabled="busy" /> I confirm this physical outcome and the recorded reason.</label>
      <button class="app-button-primary" type="submit" :disabled="busy || !confirmed">{{ busy ? 'Saving...' : 'Confirm parcel operation' }}</button>
    </form>
    <LogisticsReviewNotice :review="review" />
    <h3 class="mt-5 font-semibold">Immutable audit history</h3>
    <p v-if="!events.length" class="mt-2 text-sm">{{ loading ? 'Loading audit...' : 'No recorded Logistics operations.' }}</p>
    <ol class="mt-2 space-y-3">
      <li v-for="event in events" :key="event.event_key" class="border-t pt-2">
        <p>{{ event.action.replaceAll('_', ' ') }} · {{ event.actor?.name || 'Recorded actor unavailable' }} · {{ formatDate(event.created_at) }}</p>
        <p v-if="event.data.previous_status">{{ event.data.previous_status }} → {{ event.data.new_status }}</p>
        <p v-if="event.data.reason">{{ event.data.reason }}</p>
      </li>
    </ol>
    <div v-for="incident in incidents" :key="incident.id" class="mt-3">
      <p>{{ incident.type }}: {{ incident.reason }}</p>
      <button v-if="incident.type === 'damaged'" type="button" class="app-button-secondary" :disabled="busy" @click="viewEvidence(incident.id)">View incident evidence</button>
    </div>
  </section>
</template>

<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';
import { createRequestKey } from '@/requestKey.js';
import ParcelHandoverPanel from './ParcelHandoverPanel.vue';
import LogisticsReviewNotice from './LogisticsReviewNotice.vue';
const props = defineProps({ parcel: { type: Object, required: true }, token: { type: String, required: true } });
const emit = defineEmits(['updated', 'close']);
const operation = ref(props.parcel.status === 'OUT_FOR_DELIVERY' ? 'delivered' : 'lost');
const handoverOpen = ref(false);
const reason = ref(''), file = ref(null), confirmed = ref(false), busy = ref(false), loading = ref(false), message = ref('');
const events = ref([]), incidents = ref([]), review = ref(null);
const urls = new Set();
const requestKey = ref(createRequestKey());
const headers = () => ({ Accept: 'application/json', Authorization: `Bearer ${props.token}` });
const formatDate = value => value ? new Date(value).toLocaleString() : 'Unknown';
watch(operation, () => { file.value = null; confirmed.value = false; requestKey.value = createRequestKey(); message.value = ''; });
async function load() {
  loading.value = true;
  try {
    const response = await fetch(`/api/sorting-center/parcels/${props.parcel.id}/operations`, { headers: headers() });
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || 'Audit could not be loaded.');
    events.value = data.data.events; incidents.value = data.data.incidents; review.value = data.data.review;
  } catch (error) { message.value = error.message; }
  finally { loading.value = false; }
}
async function submit() {
  if (busy.value || !confirmed.value) return;
  if (reason.value.trim().length < 10) { message.value = 'Enter a reason of at least 10 characters.'; return; }
  if (operation.value === 'damaged' && !file.value) { message.value = 'A damaged-parcel photo is required.'; return; }
  busy.value = true;
  try {
    const delivered = operation.value === 'delivered';
    const body = new FormData(); body.append('reason', reason.value.trim());
    if (!delivered) { body.append('type', operation.value); body.append('request_key', requestKey.value); if (file.value) body.append('file', file.value); }
    const response = await fetch(`/api/sorting-center/parcels/${props.parcel.id}/${delivered ? 'override-delivered' : 'incidents'}`, { method: 'POST', headers: headers(), body });
    const data = await response.json();
    if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Operation failed.');
    message.value = data.message; confirmed.value = false;
    emit('updated', delivered ? 'DELIVERED' : operation.value.toUpperCase());
    await load();
  } catch (error) { message.value = error.message; }
  finally { busy.value = false; }
}
async function viewEvidence(incidentId) {
  try {
    const response = await fetch(`/api/sorting-center/parcels/${props.parcel.id}/incidents/${incidentId}/evidence`, { headers: headers() });
    if (!response.ok) throw new Error('Incident evidence could not be opened.');
    const url = URL.createObjectURL(await response.blob()); urls.add(url);
    const link = document.createElement('a'); link.href = url; link.download = `incident-${incidentId}`; link.click();
  } catch (error) { message.value = error.message; }
}
onMounted(load);
onUnmounted(() => { for (const url of urls) URL.revokeObjectURL(url); });
</script>
