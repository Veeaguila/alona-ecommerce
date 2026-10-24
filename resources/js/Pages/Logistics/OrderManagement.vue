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
              <p v-else class="mt-4 font-semibold text-emerald-700">
                Already at this sorting center.
                Proceed to <a href="/sorting-center/delivery-management" class="text-[var(--app-primary)] hover:underline">Delivery Management</a>.
              </p>
            </div>
          </form>
        </div>

        <!-- Instructions & Tips Card -->
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
import { reactive, ref } from 'vue';
import ScLayout from '@/Layouts/ScLayout.vue';

export default {
  name: 'OrderManagement',
  components: {
    ScLayout,
  },
  setup() {
    const token = ref(localStorage.getItem('logistics_auth_token'));
    const orderId = ref('');
    const loading = ref(false);
    const parcel = ref(null);
    const feedback = reactive({ message: '', type: 'success' });

    async function searchParcel() {
      if (!orderId.value.trim()) {
        feedback.message = 'Enter a tracking number.';
        feedback.type = 'error';
        return;
      }

      loading.value = true;
      parcel.value = null;
      feedback.message = '';

      try {
        const response = await fetch(`/api/sorting-center/parcels/lookup/${encodeURIComponent(orderId.value.trim())}`, {
          headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${token.value}`,
          },
        });
        const data = await response.json();

        if (!response.ok) {
          throw new Error(data.message || 'The order could not be found.');
        }

        parcel.value = data.data;
      } catch (error) {
        feedback.message = error.message;
        feedback.type = 'error';
      } finally {
        loading.value = false;
      }
    }

    async function confirmArrival() {
      loading.value = true;
      parcel.value = null;
      feedback.message = '';

      try {
        const response = await fetch('/api/sorting-center/parcels/mark-arrived', {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            Authorization: `Bearer ${token.value}`,
          },
          body: JSON.stringify({ order_id: orderId.value }),
        });
        const data = await response.json();

        if (!response.ok) {
          throw new Error(data.message || 'The parcel could not be marked as arrived.');
        }

        parcel.value = data.data;
        feedback.message = data.message;
        feedback.type = 'success';
        orderId.value = '';
      } catch (error) {
        feedback.message = error.message;
        feedback.type = 'error';
      } finally {
        loading.value = false;
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

    return {
      token,
      orderId,
      loading,
      parcel,
      feedback,
      searchParcel,
      confirmArrival,
      formatAddress,
    };
  },
};
</script>
