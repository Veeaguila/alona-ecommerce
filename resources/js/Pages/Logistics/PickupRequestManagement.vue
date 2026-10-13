<template>
  <sc-layout title="Pickup Requests">
    <div class="app-page-shell">
      <section v-if="!token" class="app-panel app-empty-state">
        <h2>Authentication required</h2>
        <p>Log in with an approved sorting center account to manage pickup requests.</p>
        <a href="/sorting-center/login" class="app-button-primary">Go to login</a>
      </section>

      <template v-else>
        <section class="app-panel app-panel--padded app-toolbar">
          <div>
            <p class="app-eyebrow">Pickup queue</p>
            <h2 class="app-section-title">Review incoming requests</h2>
            <p class="app-description">Approve a request to make it available for rider pickup.</p>
          </div>
          <div class="app-toolbar__actions">
            <span class="app-count-pill"><strong>{{ pagination.total }}</strong> pending</span>
            <button type="button" class="app-button-secondary" :disabled="loading || processingId !== null" @click="loadData(pagination.current_page)">
              {{ loading ? 'Refreshing...' : 'Refresh' }}
            </button>
          </div>
        </section>

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
            <h2>No pending requests</h2>
            <p>New requests will appear here when submitted.</p>
          </div>
          <div v-else class="app-table-scroll">
            <table class="app-data-table app-data-table--cards">
              <caption class="sr-only">Pending pickup requests, page {{ pagination.current_page }} of {{ pagination.last_page }}</caption>
              <thead><tr><th scope="col">Order item</th><th scope="col">Seller</th><th scope="col">Collection address</th><th scope="col">Delivery address</th><th scope="col">Requested</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
              <tbody>
                <tr v-for="request in pickupRequests" :key="request.id">
                  <td data-label="Order"><span class="app-order-id">{{ request.parcel?.order_id ?? 'Unavailable' }}</span></td>
                  <td data-label="Seller">{{ request.seller?.name ?? 'Unknown' }}</td>
                  <td data-label="Collection address" class="app-address-cell">{{ request.parcel?.collection_address || 'Confirm collection address with the seller' }}</td>
                  <td data-label="Delivery address" class="app-address-cell">{{ formatAddress(request.parcel) }}</td>
                  <td data-label="Requested"><time v-if="request.requested_at" :datetime="request.requested_at">{{ formatRequested(request.requested_at) }}</time><span v-else>Unknown</span></td>
                  <td data-label="Status"><span class="app-queue-badge">Awaiting approval</span></td>
                  <td data-label="Action">
                    <button type="button" class="app-button-primary app-button-compact" :aria-label="`Confirm pickup for ${request.parcel?.order_id ?? 'unavailable parcel'}`" :disabled="!request.parcel || processingId !== null || loading" @click="confirmPickup(request.id)">
                      {{ processingId === request.id ? 'Confirming...' : 'Confirm pickup' }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <footer v-if="!loadFailed" class="app-table-footer">
            <span>{{ pagination.total }} pending · Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
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

export default {
  name: 'PickupRequestManagement',
  components: {
    ScLayout,
  },
  setup() {
    const token = ref(localStorage.getItem('logistics_auth_token'));
    const pickupRequests = ref([]);
    const loading = ref(false);
    const loadFailed = ref(false);
    const processingId = ref(null);
    const feedback = reactive({ message: '', type: 'success' });
    const pagination = reactive({ current_page: 1, last_page: 1, total: 0 });
    let latestRequest = 0;
    onUnmounted(() => { latestRequest++; });

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
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'The request could not be completed.');
      return data;
    }

    async function loadData(page = 1, afterConfirmation = false) {
      if (!token.value || (processingId.value !== null && !afterConfirmation)) return;
      const requestId = ++latestRequest;
      loading.value = true;
      feedback.message = '';
      try {
        const data = await requestJson(`/api/sorting-center/pickup-requests?status=pending&page=${page}`);
        if (requestId !== latestRequest) return;
        const result = data.data;
        if (!Array.isArray(result?.data)) throw new Error('Invalid pickup response.');
        if (page > result.last_page) return await loadData(result.last_page, afterConfirmation);
        pickupRequests.value = result.data;
        loadFailed.value = false;
        Object.assign(pagination, {
          current_page: result.current_page, last_page: result.last_page, total: result.total,
        });
      } catch (error) {
        if (requestId !== latestRequest) return;
        loadFailed.value = true;
        feedback.message = error.message;
        feedback.type = 'error';
      } finally {
        if (requestId === latestRequest) loading.value = false;
      }
    }

    async function confirmPickup(id) {
      if (!token.value || processingId.value !== null || loading.value) return;
      processingId.value = id;
      feedback.message = '';
      try {
        const data = await requestJson(`/api/sorting-center/pickup-requests/${id}/confirm`, {
          method: 'POST',
        });
        await loadData(pagination.current_page, true);
        if (feedback.type === 'error' && feedback.message) return;
        feedback.message = data.message;
        feedback.type = 'success';
      } catch (error) {
        feedback.message = error.message;
        feedback.type = 'error';
      } finally {
        processingId.value = null;
      }
    }

    function formatRequested(value) {
      const date = new Date(value);
      if (!value || Number.isNaN(date.getTime())) return 'Unknown';
      return date.toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
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

    onMounted(() => loadData());

    return {
      token,
      pickupRequests,
      pagination,
      loading,
      loadFailed,
      processingId,
      feedback,
      loadData,
      confirmPickup,
      formatAddress,
      formatRequested,
    };
  },
};
</script>
