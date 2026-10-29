<template>
  <section class="app-panel app-panel--padded mt-4" aria-label="Supervised handover">
    <div class="flex justify-between gap-3"><h2 class="app-section-title">Supervised whole-shipment handover</h2><button type="button" class="app-button-secondary" :disabled="busy" @click="$emit('close')">Close handover</button></div>
    <p class="mt-2 text-sm">Both Riders acknowledge the actual physical handover in My account. Logistics then explicitly confirms custody transfer. Requesting or acknowledging alone never reassigns parcels.</p>
    <p v-if="message" role="status" class="app-feedback mt-3">{{ message }}</p>
    <form v-if="!handovers.some(row => row.status === 'pending')" class="mt-3 space-y-3" @submit.prevent="create">
      <label class="block">Receiving Rider<select v-model="riderId" aria-label="Receiving Rider" class="app-input" :disabled="busy"><option value="">Choose an approved Online Rider</option><option v-for="rider in riders" :key="rider.id" :value="rider.id">{{ rider.name }} · {{ rider.active_jobs_count }}/3 jobs</option></select></label>
      <label class="block">Handover reason<textarea v-model="reason" aria-label="Handover reason" class="app-input" maxlength="2000" :disabled="busy"></textarea></label>
      <button type="submit" class="app-button-primary" :disabled="busy || !riderId">Request physical handover</button>
    </form>
    <button type="button" class="app-button-secondary mt-3" :disabled="busy" @click="load">Refresh handover acknowledgments</button>
    <article v-for="handover in handovers" :key="handover.id" class="mt-4 border-t pt-3">
      <p>{{ handover.from_rider?.name }} → {{ handover.to_rider?.name }} · {{ handover.status }} · {{ handover.parcel_ids.length }} parcel(s)</p>
      <p>{{ handover.reason }}</p>
      <p>Source acknowledgment: {{ timestamp(handover.source_acknowledged_at) }}</p><p>Receiving acknowledgment: {{ timestamp(handover.target_acknowledged_at) }}</p>
      <template v-if="handover.status === 'pending'">
        <button type="button" class="app-button-primary mt-2" :disabled="busy || !handover.source_acknowledged_at || !handover.target_acknowledged_at" @click="confirm(handover.id)">Confirm supervised handover</button>
        <label class="mt-2 block">Cancellation reason<textarea v-model="cancellations[handover.id]" :aria-label="`Cancellation reason for handover ${handover.id}`" class="app-input" maxlength="2000" :disabled="busy"></textarea></label>
        <button type="button" class="app-button-secondary mt-2" :disabled="busy" @click="cancel(handover.id)">Cancel handover request</button>
      </template>
      <p v-else-if="handover.confirmed_at">Logistics confirmed: {{ timestamp(handover.confirmed_at) }}</p>
    </article>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { createRequestKey } from '@/requestKey.js';
const props = defineProps({ parcelId: { type: Number, required: true }, leg: { type: String, required: true }, token: { type: String, required: true } });
const emit = defineEmits(['confirmed', 'close']);
const handovers = ref([]), riders = ref([]), riderId = ref(''), reason = ref(''), message = ref(''), busy = ref(false), requestKey = ref(createRequestKey());
const cancellations = reactive({});
const timestamp = value => value ? new Date(value).toLocaleString() : 'Pending';
async function request(path, method = 'GET', body) {
  const response = await fetch('/api/sorting-center/'+path, { method, headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${props.token}` }, ...(body ? { body: JSON.stringify(body) } : {}) });
  const data = await response.json(); if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Handover request failed.'); return data;
}
async function load() {
  try {
    const [current, eligible] = await Promise.all([request(`parcels/${props.parcelId}/handovers`), request('pickup-dispatch/riders')]);
    handovers.value = current.data; riders.value = eligible.data;
  } catch (error) { message.value = error.message; }
}
async function mutate(path, body) {
  if (busy.value) return false; busy.value = true;
  try { const data = await request(path, 'POST', body); message.value = data.message; await load(); return true; }
  catch (error) { message.value = error.message; return false; }
  finally { busy.value = false; }
}
async function create() {
  if (!riderId.value) return;
  if (reason.value.trim().length < 10) { message.value = 'Enter a handover reason of at least 10 characters.'; return; }
  if (await mutate(`parcels/${props.parcelId}/handovers`, { request_key: requestKey.value, rider_id: Number(riderId.value), reason: reason.value.trim(), leg: props.leg })) requestKey.value = createRequestKey();
}
async function confirm(id) { if (await mutate(`handovers/${id}/confirm`, {})) emit('confirmed'); }
async function cancel(id) {
  const reason = (cancellations[id] || '').trim(); if (reason.length < 10) { message.value = 'Enter a cancellation reason of at least 10 characters.'; return; }
  await mutate(`handovers/${id}/cancel`, { reason });
}
onMounted(load);
</script>
