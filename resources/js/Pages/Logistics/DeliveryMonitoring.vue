<template>
  <sc-layout title="Delivery Monitoring">
    <div class="app-page-shell">
      <!-- Unauthenticated State -->
      <div v-if="!token" class="app-panel app-empty-state">
        <h2 class="text-amber-950 app-section-title">Authentication required</h2>
        <p class="mt-2 text-sm text-amber-800">Log in with an approved sorting center account to monitor parcels.</p>
        <a href="/sorting-center/login" class="mt-5 inline-flex app-button-primary">
          Go to login
        </a>
      </div>

      <template v-else>
        <!-- Feedback Banner -->
        <div
          v-if="feedback.message"
          :class="feedback.type === 'success' ? 'app-feedback--success' : 'app-feedback--error'"
          class="app-feedback"
          role="status"
        >
          <span>{{ feedback.message }}</span>
          <button type="button" class="ml-4 font-bold hover:opacity-75" @click="feedback.message = ''">Close</button>
        </div>

        <!-- Filter & Search Bar -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between app-panel app-panel--padded app-toolbar">
          <div>
            <p class="text-sm font-semibold text-gray-600">Tracked Parcels in Workflow</p>
            <div class="mt-1 flex items-baseline gap-2">
              <span class="text-3xl font-bold text-[var(--app-primary)]">{{ pagination.total }}</span>
              <span class="text-xs text-[var(--app-text-muted)] font-medium">total matching filters</span>
            </div>
          </div>
          <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
              <label for="status-filter" class="text-sm font-semibold text-gray-700">Status:</label>
              <select
                id="status-filter"
                v-model="selectedStatus"
                class="app-input"
                @change="loadParcels(1)"
              >
                <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
              </select>
            </div>
            <button
              type="button"
              :disabled="loading"
              class="disabled:opacity-50 app-button-secondary"
              @click="loadParcels(pagination.current_page)"
            >
              {{ loading ? 'Refreshing...' : 'Refresh' }}
            </button>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="text-center app-panel app-empty-state">
          <div class="mx-auto h-8 w-8 animate-spin rounded-full border-4 border-gray-200 border-t-[#008B8B]"></div>
          <p class="mt-4 text-sm text-gray-500">Loading monitoring data...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="parcels.length === 0" class="text-center app-panel app-empty-state">
          <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-sm font-semibold text-emerald-600">
            None
          </div>
          <h2 class="mt-4 app-section-title">No Parcels Match This Filter</h2>
          <p class="mt-2 text-sm text-gray-500">Try choosing another status filter or check back later.</p>
        </div>

        <!-- Parcels Table Card -->
        <div v-else class="overflow-hidden app-panel">
          <div class="overflow-x-auto">
            <table class="app-data-table">
              <thead >
                <tr>
                  <th scope="col" >Order ID</th>
                  <th scope="col" >Method</th>
                  <th scope="col" >Status</th>
                  <th scope="col" >Delivery Destination</th>
                  <th scope="col" >Sorting Area</th>
                  <th scope="col" >Assigned Rider</th>
                  <th scope="col" >Last Updated</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#DCE7E2] bg-white">
                <tr
                  v-for="(parcel, index) in parcels"
                  :key="parcel.id"
                  :class="index % 2 === 0 ? 'bg-white' : 'bg-gray-50/70'"
                  class="transition hover:bg-teal-50/40"
                >
                  <td class="whitespace-nowrap">{{ parcel.order_id }}</td>
                  <td class="whitespace-nowrap">
                    <span v-if="parcel.shipping_method === 'express' || parcel.is_express" class="rounded-full bg-amber-100 text-amber-800 border border-amber-300 px-2 py-0.5 text-[10px] font-black">
                      ⚡ Express
                    </span>
                    <span v-else-if="parcel.shipping_method === 'pickup'" class="rounded-full bg-emerald-100 text-emerald-800 border border-emerald-300 px-2 py-0.5 text-[10px] font-bold">
                      Pickup
                    </span>
                    <span v-else class="rounded-full bg-gray-100 text-gray-700 border border-gray-200 px-2 py-0.5 text-[10px] font-medium">
                      Standard
                    </span>
                  </td>
                  <td class="whitespace-nowrap">
                    <span :class="statusBadgeClass(parcel.status)" class="rounded-full px-3 py-1 text-xs font-bold shadow-xs">
                      {{ formatStatus(parcel.status) }}
                    </span>
                  </td>
                  <td class="text-gray-700">
                    {{ parcel.delivery_municipality }} / {{ parcel.delivery_barangay }}
                  </td>
                  <td class="whitespace-nowrap text-gray-700">
                    <span v-if="parcel.sorted_area" class="font-semibold text-[var(--app-primary)]">{{ parcel.sorted_area.name }}</span>
                    <span v-else class="text-gray-400 italic">Not sorted</span>
                  </td>
                  <td class="whitespace-nowrap text-gray-700">
                    <span v-if="parcel.assigned_rider" class="font-semibold text-gray-900">{{ parcel.assigned_rider.name }}</span>
                    <span v-else class="text-gray-400 italic">Unassigned</span>
                  </td>
                  <td class="whitespace-nowrap text-gray-500">{{ formatDate(parcel.updated_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination Bar -->
          <div class="flex flex-col sm:flex-row items-center justify-between border-t border-[var(--app-border)] px-6 py-4 text-sm bg-gray-50">
            <p class="text-gray-600 font-medium">Page {{ pagination.current_page }} of {{ pagination.last_page }}</p>
            <div class="mt-2 sm:mt-0 flex gap-2">
              <button
                type="button"
                :disabled="!pagination.prev_page_url || loading"
                class="text-gray-700 disabled:cursor-not-allowed disabled:opacity-40 app-button-secondary"
                @click="loadParcels(pagination.current_page - 1)"
              >
                Previous
              </button>
              <button
                type="button"
                :disabled="!pagination.next_page_url || loading"
                class="text-gray-700 disabled:cursor-not-allowed disabled:opacity-40 app-button-secondary"
                @click="loadParcels(pagination.current_page + 1)"
              >
                Next
              </button>
            </div>
          </div>
        </div>
      </template>
    </div>
  </sc-layout>
</template>

<script>
import { onMounted, onUnmounted, reactive, ref } from 'vue';
import ScLayout from '@/Layouts/ScLayout.vue';

export default {
  name: 'DeliveryMonitoring',
  components: {
    ScLayout,
  },
  setup() {
    const token = ref(localStorage.getItem('logistics_auth_token'));
    const parcels = ref([]);
    const selectedStatus = ref('');
    const loading = ref(false);
    const statusOptions = [
      { value: '', label: 'All monitorable parcels' },
      { value: 'AT_SORTING_CENTER', label: 'At sorting center' },
      { value: 'OUT_FOR_DELIVERY', label: 'Out for delivery' },
      { value: 'DELIVERED', label: 'Delivered' },
      { value: 'FAILED_RETURNED', label: 'Failed / returned' },
    ];
    const pagination = reactive({ current_page: 1, last_page: 1, total: 0, prev_page_url: null, next_page_url: null });
    const feedback = reactive({ message: '', type: 'success' });
    let latestRequest = 0;
    onUnmounted(() => { latestRequest++; });

    async function loadParcels(page = 1) {
      if (!token.value) return;
      const requestId = ++latestRequest;
      loading.value = true;
      const params = new URLSearchParams({ page: String(page) });
      if (selectedStatus.value) params.set('status', selectedStatus.value);

      try {
        const response = await fetch(`/api/sorting-center/parcels/monitoring?${params}`, {
          headers: { Accept: 'application/json', Authorization: `Bearer ${token.value}` },
        });
        const data = await response.json();
        if (requestId !== latestRequest) return;
        if (!response.ok) throw new Error(data.message || 'Monitoring data could not be loaded.');
        if (!Array.isArray(data.data?.data)) throw new Error('Invalid monitoring response.');
        parcels.value = data.data.data || [];
        Object.assign(pagination, {
          current_page: data.data.current_page,
          last_page: data.data.last_page,
          total: data.data.total,
          prev_page_url: data.data.prev_page_url,
          next_page_url: data.data.next_page_url,
        });
        feedback.message = '';
      } catch (error) {
        if (requestId !== latestRequest) return;
        feedback.message = error.message;
        feedback.type = 'error';
      } finally {
        if (requestId === latestRequest) loading.value = false;
      }
    }

    function formatStatus(status) {
      return status ? status.replaceAll('_', ' ') : 'UNKNOWN';
    }

    function statusBadgeClass(status) {
      return {
        AT_SORTING_CENTER: 'bg-teal-100 text-[#008B8B]',
        OUT_FOR_DELIVERY: 'bg-orange-100 text-[var(--app-secondary)]',
        DELIVERED: 'bg-green-100 text-green-800',
        FAILED_RETURNED: 'bg-red-100 text-red-800',
        PICKED_UP: 'bg-blue-100 text-blue-800',
      }[status] || 'bg-gray-100 text-gray-700';
    }

    function formatDate(value) {
      return value ? new Date(value).toLocaleString() : 'Unknown';
    }

    onMounted(() => loadParcels());

    return {
      token,
      parcels,
      selectedStatus,
      loading,
      statusOptions,
      pagination,
      feedback,
      loadParcels,
      formatStatus,
      statusBadgeClass,
      formatDate,
    };
  },
};
</script>
