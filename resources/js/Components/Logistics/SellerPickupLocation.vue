<template>
  <section class="my-3 rounded-lg border border-slate-200 bg-white p-4 text-sm" aria-label="Seller pickup location">
    <h2 class="font-semibold">Nearest Rider pickup offers</h2>
    <p>At the parcel's collection address, share your current device location with ALONA for 15 minutes. This is optional; manual assignment and Rider self-claim remain available.</p>
    <label class="mt-2 flex gap-2"><input v-model="atAddress" type="checkbox" :disabled="busy" /> I am at this parcel's collection address.</label>
    <button type="button" class="app-button-secondary mt-2" :disabled="busy || !atAddress" @click="share">Share pickup location</button>
    <p v-if="message" role="status" class="mt-2">{{ message }}</p>
  </section>
</template>
<script setup>
import { ref } from 'vue';
import axios from 'axios';
const props = defineProps({ itemId: { type: Number, required: true } });
const atAddress = ref(false), busy = ref(false), message = ref('');
async function share() {
  if (busy.value || !atAddress.value) return;
  if (!navigator.geolocation || !window.isSecureContext) { message.value = 'Device location needs HTTPS and browser permission. Use manual assignment or Rider self-claim.'; return; }
  busy.value = true; message.value = '';
  try {
    const point = await new Promise((resolve, reject) => navigator.geolocation.getCurrentPosition(resolve, reject, { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 }));
    const { data } = await axios.post(`/seller/orders/${props.itemId}/pickup-location`, { latitude: point.coords.latitude, longitude: point.coords.longitude, accuracy: point.coords.accuracy, captured_at: Math.floor(point.timestamp / 1000), permission_confirmed: true });
    message.value = data.message;
  } catch (error) { message.value = error.response?.data?.message || 'Location was not shared. Check location permission and try again, or use the existing pickup options.'; }
  finally { busy.value = false; }
}
</script>
