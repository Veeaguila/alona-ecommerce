<template>
  <section class="mb-5 rounded-xl border border-amber-300 bg-amber-50/60 p-5 shadow-xs" aria-label="Return to Seller receipt">
    <div class="flex items-center gap-2">
      <span class="inline-block h-2 w-2 rounded-full bg-amber-500"></span>
      <h2 class="text-base font-bold text-amber-950">
        {{ entry.status === 'handed_over' ? 'Returned to Seller — Awaiting Your Receipt Confirmation' : entry.status === 'received' ? 'Returned to Seller — Receipt Confirmed' : 'Return to Seller in Progress' }}
      </h2>
    </div>

    <p class="mt-2 text-sm text-slate-700 font-medium">Return Reason: <span class="font-normal">{{ entry.reason }}</span></p>
    <p v-if="entry.handed_over_at" class="mt-1 text-xs text-slate-600">
      Rider reported handover: <span class="font-semibold">{{ new Date(entry.handed_over_at).toLocaleString() }}</span>
    </p>
    <p v-if="entry.seller_received_at" class="mt-1 text-xs text-emerald-700 font-semibold">
      Your receipt confirmation: {{ new Date(entry.seller_received_at).toLocaleString() }}
    </p>

    <div v-if="message" role="status" class="mt-3 rounded-lg border border-teal-200 bg-teal-50 p-3 text-sm font-medium text-teal-800">
      {{ message }}
    </div>

    <form v-if="entry.status === 'handed_over'" class="mt-4 space-y-4 rounded-lg border border-amber-200 bg-white p-4" @submit.prevent="receive">
      <div class="rounded-lg bg-amber-50 p-3 text-xs text-amber-900 border border-amber-200/60 leading-relaxed">
        <strong>Paalala:</strong> Hindi pa mababago ang imbentaryo hangga't hindi mo kinukumpirma na pisikal mong nahawakan at nasuri ang naibalik na parcel. Kapag kinumpirma mo ito, awtomatikong maibabalik ang stock sa tindahan at mabubuksan ang refund para kay buyer.
      </div>

      <label class="flex items-start gap-3 cursor-pointer select-none">
        <input v-model="confirmed" type="checkbox" :disabled="busy" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-[#087F8C] focus:ring-[#087F8C]" />
        <span class="text-sm font-semibold text-slate-800">
          Kinukumpirma ko na pisikal kong natanggap ang naibalik na parcel at nasuri ang mga gamit para ma-restock.
        </span>
      </label>

      <div>
        <button
          type="submit"
          :disabled="busy || !confirmed"
          class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#087F8C] px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#066570] disabled:cursor-not-allowed disabled:opacity-50"
        >
          <span v-if="busy" class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"></span>
          <span>{{ busy ? 'Kinukumpirma...' : 'Confirm Seller Receipt & Restock Inventory' }}</span>
        </button>
      </div>
    </form>

    <div v-else-if="entry.status === 'received'" class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
      <p class="text-sm font-semibold text-emerald-900">Natanggap na ang gamit at naibalik na sa imbentaryo!</p>
      <p class="mt-1 text-xs text-emerald-700">Maaari mo nang buksan ang Returns & Refunds upang aprubahan ang refund para kay buyer.</p>
      <a href="/seller/returns" class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-emerald-700 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-800 transition">
        Pumunta sa Returns & Refunds para sa Refund
      </a>
    </div>
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
