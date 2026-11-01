<template>
  <sc-layout title="Order Management">
    <div class="app-page-shell">
      <!-- Unauthenticated State -->
      <div v-if="!token" class="app-panel app-empty-state">
        <h2 class="text-amber-950 app-section-title">Authentication required</h2>
        <p class="mt-2 text-sm text-amber-800">Log in with an approved sorting center account to manage incoming orders.</p>
        <a href="/sorting-center/login" class="mt-5 inline-flex app-button-primary">
          Go to login
        </a>
      </div>

      <template v-else>
        <!-- Page Header -->
        <div class="border-b border-[#E5E7EB] pb-5">
          <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-[#087F8C]">
            <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>
            <span>Parcel Operations</span>
          </div>
          <h1 class="mt-1 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Search by Tracking Number</h1>
          <p class="mt-0.5 text-xs text-slate-500">Find an order and confirm when its parcel arrives at the sorting center.</p>
        </div>

        <!-- Search Parcel Card -->
        <div class="app-panel app-panel--padded">

          <!-- Feedback message -->
          <div
            v-if="feedback.message"
            :class="feedback.type === 'success' ? 'app-feedback--success' : 'app-feedback--error'"
            class="app-feedback"
            role="status"
          >
            <span>{{ feedback.message }}</span>
            <button type="button" class="ml-4 font-bold hover:opacity-75" @click="feedback.message = ''">Close</button>
          </div>

          <form class="space-y-5" @submit.prevent="searchParcel">
            <div>
              <label for="order-id" class="block text-sm font-semibold text-gray-700">Tracking Number</label>
              <div class="mt-2 flex flex-col gap-3 sm:flex-row">
                <input
                  id="order-id"
                  v-model.trim="orderId"
                  @input="invalidateLookup"
                  :disabled="confirming"
                  type="text"
                  maxlength="50"
                  required
                  autocomplete="off"
                  placeholder="Enter tracking number"
                  class="min-w-0 flex-1 app-input"
                />
                <button
                  type="submit"
                  :disabled="loading || !orderId"
                  class="disabled:cursor-not-allowed disabled:opacity-50 app-button-primary"
                >
                  {{ loading ? 'Searching...' : 'Search' }}
                </button>
              </div>
            </div>

            <!-- Parcel Result Info -->
            <div v-if="parcel" class="mt-6 rounded-lg border border-[var(--app-border)] bg-gray-50 p-5 text-sm">
              <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                  <span class="text-xs uppercase font-bold text-[var(--app-text-muted)]">Tracking Number:</span>
                  <span class="font-mono font-bold text-[var(--app-primary)] text-base">{{ parcel.order_id }}</span>
                </div>
                <span class="rounded-full bg-[var(--app-primary)] px-3 py-1 text-xs font-bold text-white shadow-sm">{{ parcel.status }}</span>
              </div>
              <div class="mt-4">
                <p class="text-xs font-semibold uppercase text-[var(--app-text-muted)]">Delivery Address</p>
                <p class="mt-1 text-gray-700">{{ formatAddress(parcel) || 'Not provided' }}</p>
              </div>
              <button
                v-if="parcel.status === 'PICKED_UP'"
                type="button"
                :disabled="loading"
                class="mt-5 disabled:cursor-not-allowed disabled:opacity-50 app-button-primary"
                @click="confirmArrival"
              >
                {{ loading ? 'Confirming...' : 'Confirm Arrival' }}
              </button>
              <label v-if="parcel.status === 'PICKED_UP' && parcel.hub_route" class="mt-3 flex items-start gap-2">
                <input v-model="originHubConfirmed" type="checkbox" :disabled="loading" />
                <span>I physically received this parcel at {{ parcel.hub_route.origin_hub_name }}.</span>
              </label>
              <p v-else class="mt-4 font-semibold text-emerald-700">
                Already at this sorting center.
                Proceed to <a href="/sorting-center/delivery-management" class="text-[var(--app-primary)] hover:underline">Delivery Management</a>.
              </p>
            </div>
          </form>
        </div>

        <!-- Instructions & Tips Card -->
        <section class="app-panel app-panel--padded" aria-label="Outbound scanning">
          <h2 class="app-section-title">Outbound scanning</h2>
          <p class="app-description">Scan a parcel after physical arrival. Confirm readiness after sorting and delivery Rider assignment; the Rider starts delivery in their app.</p>
          <form class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="lookupOutbound">
            <div><label for="outbound-reference" class="block text-sm font-semibold">Outbound tracking number</label><input id="outbound-reference" v-model.trim="outboundReference" @input="invalidateOutbound" :disabled="outboundBusy" maxlength="50" required autocomplete="off" class="app-input" /></div>
            <button type="submit" :disabled="outboundBusy || !outboundReference" class="app-button-secondary">Scan Outbound</button>
          </form>
          <p v-if="outboundMessage" class="mt-3" role="status">{{ outboundMessage }}</p>
          <div v-if="outboundParcel" class="mt-3 space-y-2">
            <p>{{ outboundParcel.order_id }} · {{ outboundParcel.status }} · {{ outboundParcel.sorted_area?.name || 'Not sorted' }} · {{ outboundParcel.assigned_rider?.name || 'No delivery Rider assigned' }}</p>
            <a v-if="!outboundParcel.sorted_area_id" href="/sorting-center/delivery-management" class="app-button-secondary">Sort parcel</a>
            <a v-else-if="!outboundParcel.assigned_rider_id" href="/sorting-center/rider-assignment" class="app-button-secondary">Assign delivery Rider</a>
            <button v-else type="button" :disabled="outboundBusy || outboundConfirmed" class="app-button-primary" @click="confirmOutbound">Confirm Outbound Readiness</button>
            <p v-if="outboundConfirmedAt">Recorded: {{ outboundConfirmedAt }}</p>
          </div>
        </section>
        <return-to-seller-panel :token="token" />
        <div class="app-panel app-panel--padded">
          <h3 class="mb-2 app-section-title">Operational Guide</h3>
          <ul class="list-disc pl-5 space-y-1 text-sm text-gray-600">
            <li>Ensure the parcel's tracking number matches the order label.</li>
            <li>Confirm arrival after pickup. The parcel will then appear in the sorting queue.</li>
            <li>Staff can manage arrived parcels from Delivery Management.</li>
          </ul>
        </div>
      </template>
    </div>
  </sc-layout>
</template>

<script>
import { reactive, ref, onMounted, onUnmounted } from 'vue';
import ScLayout from '@/Layouts/ScLayout.vue';
import ReturnToSellerPanel from '@/Components/Logistics/ReturnToSellerPanel.vue';

export default {
  name: 'OrderManagement',
  components: {
    ScLayout,
    ReturnToSellerPanel,
  },
  setup() {
    const outboundReference = ref('');
    const outboundParcel = ref(null);
    const outboundBusy = ref(false);
    const outboundMessage = ref('');
    const outboundConfirmed = ref(false);
    const outboundConfirmedAt = ref(null);
    let outboundVersion = 0;
    onUnmounted(() => { outboundVersion++; });
    function invalidateOutbound() {
      outboundVersion++; outboundParcel.value = null; outboundMessage.value = '';
      outboundConfirmed.value = false; outboundConfirmedAt.value = null;
    }
    async function outboundRequest(confirm = false) {
      if (outboundBusy.value || !token.value) return;
      const reference = outboundReference.value.trim();
      if (!reference || reference.length > 50) return;
      if (confirm && (!outboundParcel.value || outboundParcel.value.order_id !== reference || outboundConfirmed.value)) return;
      if (!confirm) invalidateOutbound();
      const version = outboundVersion;
      outboundBusy.value = true;
      try {
        const response = await fetch(confirm ? '/api/sorting-center/outbound/confirm' : `/api/sorting-center/outbound/lookup/${encodeURIComponent(reference)}`, {
          method: confirm ? 'POST' : 'GET',
          headers: { Accept: 'application/json', Authorization: `Bearer ${token.value}`, ...(confirm ? { 'Content-Type': 'application/json' } : {}) },
          ...(confirm ? { body: JSON.stringify({order_id:reference}) } : {}),
        });
        let data;
        try { data = await response.json(); } catch { throw new Error('The server returned an unreadable response. Please scan again.'); }
        if (version !== outboundVersion) return;
        if (!response.ok) throw new Error(data.message || 'Outbound readiness could not be checked.');
        const parcel = confirm ? data.data?.parcel : data.data;
        if (parcel?.order_id !== reference || parcel?.status !== 'AT_SORTING_CENTER') throw new Error('Unexpected parcel response. Please scan again.');
        outboundParcel.value = parcel;
        if (confirm) { outboundConfirmed.value = true; outboundConfirmedAt.value = data.data.confirmed_at; }
        outboundMessage.value = confirm ? data.message : 'Arrival confirmed. Check sorting and delivery Rider assignment before outbound confirmation.';
      } catch (error) { if (version === outboundVersion) { outboundParcel.value = null; outboundMessage.value = error.message; } }
      finally { outboundBusy.value = false; }
    }
    const lookupOutbound = () => outboundRequest(false);
    const confirmOutbound = () => outboundRequest(true);
    const token = ref(localStorage.getItem('logistics_auth_token'));
    const orderId = ref('');
    const loading = ref(false);
    const confirming = ref(false);
    const parcel = ref(null);
    const originHubConfirmed = ref(false);
    const feedback = reactive({ message: '', type: 'success' });
    let lookupVersion = 0;
    let disposed = false;
    let lookupReference = null;

    onUnmounted(() => { disposed = true; });

    function invalidateLookup() {
      originHubConfirmed.value = false;
      lookupVersion++;
      lookupReference = null;
      parcel.value = null;
      feedback.message = '';
    }

    async function readResponse(response, reference, statuses) {
      const data = await response.json().catch(() => null);
      if (!response.ok) {
        const validation = Object.values(data?.errors ?? {}).flat().find(value => typeof value === 'string');
        const fallback = response.status === 401 ? 'Your session has expired. Please log in again.'
          : response.status === 403 ? 'You do not have permission to manage this parcel.'
            : 'The server could not complete the request. Please try again.';
        throw new Error(typeof data?.message === 'string' && data.message.trim() ? data.message : validation || fallback);
      }
      if (data?.data?.order_id !== reference || !statuses.includes(data?.data?.status)) {
        throw new Error('The server returned an unexpected parcel response. Please search again.');
      }
      return data;
    }

    async function searchParcel() {
      if (loading.value || disposed) return;
      const reference = orderId.value.trim();
      invalidateLookup();
      if (!reference || reference.length > 50) {
        feedback.message = reference ? 'Tracking numbers must be at most 50 characters.' : 'Enter a tracking number.';
        feedback.type = 'error';
        return;
      }

      loading.value = true;
      const version = lookupVersion;

      try {
        const response = await fetch(`/api/sorting-center/parcels/lookup/${encodeURIComponent(reference)}`, {
          headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${token.value}`,
          },
        });
        const data = await readResponse(response, reference, ['PICKED_UP', 'AT_SORTING_CENTER']);
        if (disposed || version !== lookupVersion || orderId.value.trim() !== reference) return;
        lookupReference = data.data.order_id;
        parcel.value = data.data;
      } catch (error) {
        if (disposed || version !== lookupVersion || orderId.value.trim() !== reference) return;
        feedback.message = error instanceof TypeError ? 'Unable to reach the server. Please try again.' : error.message;
        feedback.type = 'error';
      } finally {
        loading.value = false;
      }
    }

    async function confirmArrival() {
      if (loading.value || disposed) return;
      const reference = lookupReference;
      if (!reference || reference !== orderId.value.trim() || parcel.value?.order_id !== reference) {
        invalidateLookup();
        feedback.message = 'Search for the tracking number again before confirming arrival.';
        feedback.type = 'error';
        return;
      }
      if (parcel.value.status !== 'PICKED_UP') return;
      if (parcel.value.hub_route && !originHubConfirmed.value) {
        feedback.message = 'Confirm physical receipt at the booked origin hub before continuing.';
        feedback.type = 'error';
        return;
      }
      loading.value = true;
      confirming.value = true;
      feedback.message = '';

      try {
        const response = await fetch('/api/sorting-center/parcels/mark-arrived', {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            Authorization: `Bearer ${token.value}`,
          },
          body: JSON.stringify({ order_id: reference, ...(parcel.value.hub_route ? { hub_id: parcel.value.hub_route.origin_hub_id } : {}) }),
        });
        const data = await readResponse(response, reference, ['AT_SORTING_CENTER']);
        if (disposed) return;
        parcel.value = data.data;
        feedback.message = typeof data.message === 'string' && data.message.trim() ? data.message : 'Parcel marked as arrived at the sorting center.';
        feedback.type = 'success';
      } catch (error) {
        if (disposed) return;
        feedback.message = error instanceof TypeError ? 'Unable to reach the server. Search again to check whether arrival was recorded.' : error.message;
        feedback.type = 'error';
      } finally {
        loading.value = false;
        confirming.value = false;
      }
    }

    function formatAddress(item) {
      if (item?.delivery_address) return item.delivery_address;
      return [
        [item.delivery_house_number, item.delivery_street].filter(Boolean).join(' '),
        item.delivery_barangay,
        item.delivery_municipality,
        item.delivery_province,
      ].filter(Boolean).join(', ');
    }

    onMounted(() => {
      const reference = new URLSearchParams(window.location.search).get('reference');
      if (reference && token.value) {
        orderId.value = reference;
        searchParcel();
      }
    });

    return {
      outboundReference, outboundParcel, outboundBusy, outboundMessage, outboundConfirmed, outboundConfirmedAt, invalidateOutbound, lookupOutbound, confirmOutbound,
      token,
      orderId,
      loading,
      confirming,
      parcel,
      originHubConfirmed,
      feedback,
      invalidateLookup,
      searchParcel,
      confirmArrival,
      formatAddress,
    };
  },
};
</script>
