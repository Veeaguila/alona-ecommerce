<template>
  <section class="app-panel app-panel--padded mt-4" aria-label="Pickup dispatch">
    <div class="flex justify-between gap-3"><h2 class="app-section-title">{{ parcelIds.length > 1 ? 'Bulk pickup assignment' : currentRiderId ? 'Reassign uncollected pickup' : 'Assign pickup Rider' }}</h2><button class="app-button-secondary" :disabled="busy" @click="$emit('close')">Close pickup dispatch</button></div>
    <p class="mt-2 text-sm">Assign complete Seller shipments. Only approved Online Riders with capacity can receive manual assignments. Collected parcels require supervised handover.</p>
    <p v-if="message" role="status" class="app-feedback mt-3">{{ message }}</p>
    <div v-if="parcelIds.length === 1 && !currentRiderId" class="mt-3">
      <button type="button" class="app-button-secondary" :disabled="busy" @click="nearest">Offer to nearest eligible Rider</button>
      <p class="mt-2 text-sm">Requires a fresh Seller collection point and consent-based Online Rider locations. The offer lasts 60 seconds; the Rider must accept before assignment.</p>
    </div>
    <form class="mt-4 space-y-3" @submit.prevent="submit">
      <label class="block">Pickup Rider<select v-model="riderId" aria-label="Pickup Rider" class="app-input" :disabled="busy || loading"><option value="">Choose an eligible Rider</option><option v-for="rider in riders" :key="rider.id" :value="rider.id">{{ rider.name }} · {{ rider.presence }} · {{ rider.active_jobs_count }}/3 jobs</option></select></label>
      <p v-if="!loading && !riders.length" role="status">{{ emptyReason }}</p>
      <label v-if="currentRiderId" class="block">Reassignment reason<textarea v-model="reason" aria-label="Reassignment reason" class="app-input" maxlength="300" :disabled="busy"></textarea></label>
      <div class="flex flex-wrap gap-2"><button class="app-button-primary" type="submit" :disabled="busy || !riderId">{{ busy ? 'Assigning...' : currentRiderId ? 'Confirm pickup reassignment' : parcelIds.length > 1 ? 'Assign selected pickups' : 'Assign pickup' }}</button><button class="app-button-secondary" type="button" :disabled="busy || loading" @click="load">Refresh eligible pickup Riders</button></div>
    </form>
    <ul v-if="results.length" aria-label="Bulk assignment results" class="mt-3 space-y-2"><li v-for="result in results" :key="result.parcel_id">Parcel {{ result.parcel_id }}: {{ result.status }}<span v-if="result.reason"> — {{ result.reason }}</span></li></ul>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
const props = defineProps({ parcelIds: { type: Array, required: true }, currentRiderId: { type: Number, default: null }, token: { type: String, required: true } });
const emit = defineEmits(['updated', 'close']);
const riderId = ref(''), riders = ref([]), reason = ref(''), message = ref(''), emptyReason = ref(''), busy = ref(false), loading = ref(false), results = ref([]);
const headers = () => ({ Accept: 'application/json', Authorization: `Bearer ${props.token}`, 'Content-Type': 'application/json' });
async function load() {
  loading.value = true;
  try {
    const response = await fetch('/api/sorting-center/pickup-dispatch/riders', { headers: headers() }); const data = await response.json();
    if (!response.ok) throw new Error(data.message || 'Eligible Riders could not be loaded.');
    riders.value = data.data.filter(rider => rider.id !== props.currentRiderId); emptyReason.value = data.meta.empty_reason || 'No different Online Rider has available capacity.';
    if (!riders.value.some(rider => rider.id === Number(riderId.value))) riderId.value = '';
  } catch (error) { message.value = error.message; }
  finally { loading.value = false; }
}
async function submit() {
  if (busy.value || !riderId.value) return;
  if (props.currentRiderId && reason.value.trim().length < 10) { message.value = 'Enter a reassignment reason of at least 10 characters.'; return; }
  busy.value = true;
  try {
    const bulk = props.parcelIds.length > 1;
    const body = { rider_id: Number(riderId.value), ...(bulk ? { parcel_ids: props.parcelIds } : props.currentRiderId ? { from_rider_id: props.currentRiderId, reason: reason.value.trim() } : {}) };
    const response = await fetch(`/api/sorting-center/pickup-dispatch/${bulk ? 'bulk' : props.parcelIds[0] + '/assign'}`, { method: 'POST', headers: headers(), body: JSON.stringify(body) });
    const data = await response.json();
    if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Pickup assignment failed.');
    results.value = bulk ? data.data : []; message.value = data.message; emit('updated'); await load();
  } catch (error) { message.value = error.message; }
  finally { busy.value = false; }
}
async function nearest() {
  if (busy.value) return; busy.value = true; results.value = [];
  try {
    const response = await fetch(`/api/sorting-center/pickup-dispatch/${props.parcelIds[0]}/nearest`, { method: 'POST', headers: headers() });
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || 'Nearest offer could not be confirmed. Refresh before retrying.');
    message.value = `Offered to ${data.data.rider_name} until ${new Date(data.data.expires_at).toLocaleTimeString()}. The Rider must accept the pickup.`;
  } catch (error) { message.value = error.message; }
  finally { busy.value = false; }
}
onMounted(load);
</script>
