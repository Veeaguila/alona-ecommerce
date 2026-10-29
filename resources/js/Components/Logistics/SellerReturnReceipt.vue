<template>
  <section class="mb-5 rounded-lg border border-amber-200 bg-white p-5" aria-label="Return to Seller receipt">
    <h2 class="text-lg font-semibold">{{ entry.status === 'handed_over' ? 'Returned — awaiting your confirmation' : entry.status === 'received' ? 'Returned — receipt confirmed' : 'Return to Seller in progress' }}</h2>
    <p class="mt-2">{{ entry.reason }}</p>
    <p v-if="entry.handed_over_at">Rider reported handover: {{ new Date(entry.handed_over_at).toLocaleString() }}</p>
    <p v-if="entry.seller_received_at">Your receipt confirmation: {{ new Date(entry.seller_received_at).toLocaleString() }}</p>
    <p v-if="message" role="status" class="mt-3">{{ message }}</p>
    <form v-if="entry.status === 'handed_over'" class="mt-3 space-y-3" @submit.prevent="receive">
      <p>Inventory stays unchanged until you confirm physical receipt and verified units for restocking.</p>
      <label class="flex gap-2"><input v-model="confirmed" type="checkbox" :disabled="busy" /> I physically received the parcel and verified all units for restocking.</label>
      <button type="submit" :disabled="busy || !confirmed" class="app-button-primary">Confirm Seller receipt and restock</button>
    </form>
  </section>
</template>
<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
const props = defineProps({ entry: { type: Object, required: true } });
const confirmed = ref(false), busy = ref(false), message = ref('');
async function receive() {
  if (busy.value || !confirmed.value) return; busy.value = true;
  try { const { data } = await axios.post(`/seller/logistics-returns/${props.entry.id}/receive`, { physical_confirmation: true }); message.value = data.message; router.reload({ only: ['orderItem', 'deliveryDetails'] }); }
  catch (error) { message.value = error.response?.data?.message || 'Receipt could not be confirmed. Refresh before retrying.'; }
  finally { busy.value = false; }
}
</script>
