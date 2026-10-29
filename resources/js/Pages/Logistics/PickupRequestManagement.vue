<template>
  <sc-layout title="Pickup Requests">
    <div class="app-page-shell">
      <section v-if="!token" class="app-panel app-empty-state">
        <h2>Authentication required</h2>
        <p>Log in with an approved sorting center account to manage pickup requests.</p>
        <a href="/sorting-center/login" class="app-button-primary">Go to login</a>
      </section>

      <template v-else>
        <!-- Page Header -->
        <div class="border-b border-[#E5E7EB] pb-5 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-[#087F8C]">
              <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>
              <span>Seller Pickups</span>
            </div>
            <h1 class="mt-1 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
              Pickup Request Management
            </h1>
            <p class="mt-0.5 text-xs text-slate-500">Track requests from Seller pickup to arrival at the sorting center. Updates refresh every 15 seconds while this page is visible.</p>
          </div>
          <div class="app-toolbar__actions flex items-center gap-3">
            <span class="app-count-pill"><strong>{{ pagination.total }}</strong> {{ pagination.total === 1 ? 'request' : 'requests' }}</span>
            <span class="text-sm" aria-live="polite">Last refreshed: {{ lastUpdated ? formatRequested(lastUpdated) : 'Not yet refreshed' }}</span>
            <button type="button" class="app-button-secondary" :disabled="loading || processingId !== null" @click="loadData(pagination.current_page)">
              {{ loading ? 'Refreshing...' : 'Refresh' }}
            </button>
          </div>
        </div>

        <section class="app-panel app-panel--padded">
          <label for="pickup-stage" class="app-eyebrow">Pickup stage</label>
          <select id="pickup-stage" v-model="selectedStage" class="app-input" :disabled="processingId !== null" @change="changeStage">
            <option value="all">All pickup requests</option>
            <option value="pending">Pending</option>
            <option value="accepted">Accepted</option>
            <option value="assigned">Rider Assigned</option>
            <option value="PICKED_UP">Picked Up from Seller / In Transit to Sorting Center</option>
            <option value="arrived">Arrived at Sorting Center</option>
          </select>
          <p class="app-description">Picked up parcels are in transit until arrival is confirmed. Arrival confirmation stays in Order Management.</p>
          <button type="button" class="app-button-secondary mt-3" :disabled="!selectedPickups.length" @click="dispatch = { ids: [...selectedPickups], rider: null }">Assign selected pickups ({{ selectedPickups.length }})</button>
        </section>
        <pickup-dispatch-panel v-if="dispatch" :key="dispatch.ids.join(',')" :parcel-ids="dispatch.ids" :current-rider-id="dispatch.rider" :token="token" @close="dispatch = null" @updated="selectedPickups = []; loadData(pagination.current_page)" />
        <parcel-handover-panel v-if="handoverParcel" :key="handoverParcel" :parcel-id="handoverParcel" leg="pickup" :token="token" @close="handoverParcel = null" @confirmed="loadData(pagination.current_page)" />

        <div v-if="feedback.message" class="app-feedback" :class="feedback.type === 'error' ? 'app-feedback--error' : 'app-feedback--success'" role="status">
          <span>{{ feedback.message }}</span>
          <button type="button" class="app-button-secondary" @click="feedback.message = ''">Dismiss</button>
        </div>

        <section class="app-panel app-table-panel" :aria-busy="loading">
          <div v-if="loading && pickupRequests.length === 0" class="app-empty-state" role="status">
            <h2>Loading pickup requests</h2>
            <p>Please wait while the queue updates.</p>
          </div>
          <div v-else-if="loadFailed" class="app-empty-state" role="alert">
            <h2>Unable to load requests</h2>
            <p>Please refresh to try again. No changes have been made to the queue.</p>
          </div>
          <div v-else-if="pickupRequests.length === 0" class="app-empty-state">
            <span class="app-count-pill">Queue clear</span>
            <h2>No matching pickup requests</h2>
            <p>Choose another stage or refresh to check for updates.</p>
          </div>
          <div v-else class="app-table-scroll">
            <table class="app-data-table app-data-table--cards">
              <caption class="sr-only">Pickup tracking, page {{ pagination.current_page }} of {{ pagination.last_page }}</caption>
              <thead><tr><th scope="col">Tracking reference</th><th scope="col">Seller</th><th scope="col">Pickup Rider</th><th scope="col">Collection address</th><th scope="col">Delivery address</th><th scope="col">Timestamps</th><th scope="col">Method</th><th scope="col">Latest status</th><th scope="col">Action</th></tr></thead>
              <tbody>
                <tr v-for="request in pickupRequests" :key="request.id">
                  <td data-label="Tracking reference"><input v-if="request.status === 'confirmed' && request.parcel && !request.parcel.pickup_rider_id && ['PLACED', 'PREPARING'].includes(request.parcel.status)" v-model="selectedPickups" type="checkbox" :value="request.parcel.id" :aria-label="`Select pickup ${request.parcel.order_id}`" /> <span class="app-order-id">{{ request.parcel?.order_id ?? 'Unavailable' }}</span></td>
                  <td data-label="Seller">{{ request.seller?.name ?? 'Unknown' }}</td>
                  <td data-label="Pickup Rider">
                    <div>Pickup: {{ request.parcel?.pickup_rider?.name ?? 'Unassigned' }}</div>
                    <div v-if="request.parcel?.assigned_rider">Delivery: {{ request.parcel.assigned_rider.name }}</div>
                  </td>
                  <td data-label="Collection address" class="app-address-cell">{{ request.parcel?.collection_address || 'Confirm collection address with the seller' }}</td>
                  <td data-label="Delivery address" class="app-address-cell">{{ formatAddress(request.parcel) }}</td>
                  <td data-label="Timestamps">
                    <div>Requested: <time v-if="request.requested_at" :datetime="request.requested_at">{{ formatRequested(request.requested_at) }}</time><span v-else>Unknown</span></div>
                    <div>Latest update: <time v-if="latestUpdate(request)" :datetime="latestUpdate(request)">{{ formatRequested(latestUpdate(request)) }}</time><span v-else>Unknown</span></div>
                  </td>
                  <td data-label="Method">
                    <span v-if="request.is_express || request.shipping_method === 'express'" class="rounded-full bg-amber-100 text-amber-800 border border-amber-300 px-2.5 py-0.5 text-[11px] font-black inline-flex items-center gap-1">
                      ⚡ Express
                    </span>
                    <span v-else class="text-xs text-gray-600 font-medium">
                      Standard
                    </span>
                  </td>
                  <td data-label="Latest status">
                    <span class="app-queue-badge">{{ statusLabel(request) }}</span>
                    <details class="mt-3 text-sm">
                      <summary class="cursor-pointer font-semibold">View status timeline</summary>
                      <ol v-if="request.timeline?.length" class="mt-2 space-y-2">
                        <li v-for="(event, index) in request.timeline" :key="index">
                          <span>{{ event.label }}</span> —
                          <time v-if="event.occurred_at" :datetime="event.occurred_at">{{ formatRequested(event.occurred_at) }}</time>
                          <span v-else>Time not recorded</span>
                        </li>
                      </ol>
                      <p v-else>No recorded timeline is available.</p>
                    </details>
                  </td>
                  <td data-label="Action">
                    <button v-if="request.parcel?.status === 'PICKED_UP'" type="button" class="app-button-secondary app-button-compact" @click="handoverParcel = request.parcel.id">Supervised handover</button>
                    <button v-if="request.status === 'confirmed' && request.parcel && ['PLACED', 'PREPARING'].includes(request.parcel.status)" type="button" class="app-button-secondary app-button-compact" @click="dispatch = { ids: [request.parcel.id], rider: request.parcel.pickup_rider_id }">{{ request.parcel.pickup_rider_id ? 'Reassign pickup' : 'Assign pickup Rider' }}</button>
                    <button v-if="request.status === 'pending'" type="button" class="app-button-primary app-button-compact" :aria-label="`Confirm pickup for ${request.parcel?.order_id ?? 'unavailable parcel'}`" :disabled="!request.parcel || processingId !== null || loading" @click="confirmPickup(request.id)">
                      {{ processingId === request.id ? 'Confirming...' : 'Confirm pickup' }}
                    </button>
                    <a v-else-if="request.parcel?.status === 'PICKED_UP'" :href="arrivalUrl(request)" class="app-button-secondary app-button-compact">Confirm arrival in Order Management</a>
                    <span v-else>Tracking</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <footer v-if="!loadFailed" class="app-table-footer">
            <span>{{ pagination.total }} {{ pagination.total === 1 ? 'request' : 'requests' }} · Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
            <div class="app-toolbar__actions">
              <button type="button" class="app-button-secondary app-button-compact" :disabled="loading || processingId !== null || pagination.current_page <= 1" @click="loadData(pagination.current_page - 1)">Previous</button>
              <button type="button" class="app-button-secondary app-button-compact" :disabled="loading || processingId !== null || pagination.current_page >= pagination.last_page" @click="loadData(pagination.current_page + 1)">Next</button>
            </div>
          </footer>
        </section>
      </template>
    </div>
  </sc-layout>
</template>

<script>
import { onMounted, onUnmounted, reactive, ref } from 'vue';
import ScLayout from '@/Layouts/ScLayout.vue';
import PickupDispatchPanel from '@/Components/Logistics/PickupDispatchPanel.vue';
import ParcelHandoverPanel from '@/Components/Logistics/ParcelHandoverPanel.vue';

export default {
  name: 'PickupRequestManagement',
  components: {
    ScLayout,
    PickupDispatchPanel,
    ParcelHandoverPanel,
  },
  setup() {
    const token = ref(localStorage.getItem('logistics_auth_token'));
    const pickupRequests = ref([]);
    const dispatch = ref(null);
    const selectedPickups = ref([]);
    const handoverParcel = ref(null);
    const selectedStage = ref('all');
    const lastUpdated = ref(null);
    const loading = ref(false);
    const loadFailed = ref(false);
    const processingId = ref(null);
    const feedback = reactive({ message: '', type: 'success' });
    const pagination = reactive({ current_page: 1, last_page: 1, total: 0 });
    let latestRequest = 0;
    let disposed = false;
    let pollTimer;
    onUnmounted(() => {
      disposed = true;
      latestRequest++;
      clearInterval(pollTimer);
      document.removeEventListener('visibilitychange', refreshVisible);
      window.removeEventListener('focus', refreshVisible);
    });

    async function requestJson(url, options = {}) {
      const response = await fetch(url, {
        ...options,
        headers: {
          Accept: 'application/json',
          ...(options.body ? { 'Content-Type': 'application/json' } : {}),
          Authorization: `Bearer ${token.value}`,
          ...(options.headers || {}),
        },
      });
      const data = await response.json().catch(() => null);
      if (!response.ok) {
        const fallback = response.status === 401 ? 'Your session has expired. Please log in again.'
          : response.status === 403 ? 'You do not have permission to manage pickup requests.'
            : 'The request could not be completed. Please try again.';
        throw new Error(typeof data?.message === 'string' && data.message.trim() ? data.message : fallback);
      }
      if (!data) throw new Error('Invalid pickup response. Please refresh to try again.');
      return data;
    }

    async function loadData(page = 1, afterConfirmation = false, background = false) {
      if (disposed || !token.value || (processingId.value !== null && !afterConfirmation)) return;
      const requestId = ++latestRequest;
      loading.value = true;
      if (!background || loadFailed.value) feedback.message = '';
      try {
        const data = await requestJson(`/api/sorting-center/pickup-requests?status=all&stage=${encodeURIComponent(selectedStage.value)}&page=${page}`);
        if (requestId !== latestRequest) return;
        const result = data.data;
        if (!Array.isArray(result?.data)) throw new Error('Invalid pickup response.');
        if (page > result.last_page) return await loadData(result.last_page, afterConfirmation, background);
        pickupRequests.value = result.data;
        lastUpdated.value = new Date().toISOString();
        loadFailed.value = false;
        Object.assign(pagination, {
          current_page: result.current_page, last_page: result.last_page, total: result.total,
        });
      } catch (error) {
        if (requestId !== latestRequest) return;
        loadFailed.value = true;
        feedback.message = error instanceof TypeError ? 'Unable to reach the server. Please try again.' : error.message;
        feedback.type = 'error';
      } finally {
        if (requestId === latestRequest) loading.value = false;
      }
    }

    async function confirmPickup(id) {
      if (disposed || !token.value || processingId.value !== null || loading.value) return;
      const record = pickupRequests.value.find(request => request.id === id);
      if (record && record.status !== 'pending') return;
      processingId.value = id;
      feedback.message = '';
      try {
        const data = await requestJson(`/api/sorting-center/pickup-requests/${id}/confirm`, {
          method: 'POST',
        });
        if (disposed) return;
        await loadData(pagination.current_page, true);
        if (disposed) return;
        if (feedback.type === 'error' && feedback.message) return;
        feedback.message = data.message;
        feedback.type = 'success';
      } catch (error) {
        if (disposed) return;
        feedback.message = error instanceof TypeError ? 'Unable to reach the server. Refresh to check whether the pickup was accepted.' : error.message;
        feedback.type = 'error';
      } finally {
        if (!disposed) processingId.value = null;
      }
    }

    function formatRequested(value) {
      const date = new Date(value);
      if (!value || Number.isNaN(date.getTime())) return 'Unknown';
      return date.toLocaleString('en-PH', { timeZone: 'Asia/Manila', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function latestUpdate(request) {
      return [request.updated_at, request.parcel?.updated_at]
        .filter(value => value && !Number.isNaN(new Date(value).getTime()))
        .sort((a, b) => new Date(b).getTime() - new Date(a).getTime())[0] ?? null;
    }

    function statusLabel(request) {
      if (request.status === 'pending') return 'Pending';
      const parcel = request.parcel;
      if (!parcel) return 'Parcel unavailable';
      if (['PLACED', 'PREPARING'].includes(parcel.status)) return parcel.pickup_rider_id ? 'Rider Assigned' : 'Accepted';
      return ({ PICKED_UP: 'Picked Up from Seller / In Transit to Sorting Center', AT_SORTING_CENTER: 'Arrived at Sorting Center',
        OUT_FOR_DELIVERY: 'Out for Delivery', DELIVERED: 'Delivered', FAILED_RETURNED: 'Failed / Returned' })[parcel.status] ?? parcel.status ?? 'Unknown';
    }

    function arrivalUrl(request) {
      return `/sorting-center/order-management?reference=${encodeURIComponent(request.parcel?.order_id ?? '')}`;
    }

    function changeStage() {
      if (processingId.value !== null || disposed) return;
      pickupRequests.value = [];
      lastUpdated.value = null;
      return loadData(1);
    }

    function refreshVisible() {
      if (disposed || document.visibilityState === 'hidden' || loading.value || processingId.value !== null) return;
      return loadData(pagination.current_page, false, true);
    }

    function formatAddress(parcel) {
      if (parcel?.delivery_address) return parcel.delivery_address;
      if (!parcel) return 'Address unavailable';
      return [
        [parcel.delivery_house_number, parcel.delivery_street].filter(Boolean).join(' '),
        parcel.delivery_barangay,
        parcel.delivery_municipality,
        parcel.delivery_province,
      ].filter(Boolean).join(', ') || 'Address unavailable';
    }

    onMounted(() => {
      loadData();
      pollTimer = setInterval(refreshVisible, 15000);
      document.addEventListener('visibilitychange', refreshVisible);
      window.addEventListener('focus', refreshVisible);
    });

    return {
      token,
      pickupRequests,
      dispatch,
      selectedPickups,
      handoverParcel,
      selectedStage,
      lastUpdated,
      pagination,
      loading,
      loadFailed,
      processingId,
      feedback,
      loadData,
      confirmPickup,
      changeStage,
      refreshVisible,
      statusLabel,
      latestUpdate,
      arrivalUrl,
      formatAddress,
      formatRequested,
    };
  },
};
</script>
