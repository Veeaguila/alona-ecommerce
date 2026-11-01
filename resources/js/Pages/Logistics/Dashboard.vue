<template>
  <sc-layout title="Dashboard">
    <div class="app-page-shell">
      <div v-if="!token" class="app-panel app-empty-state">
        <h2 class="app-section-title">Authentication required</h2>
        <p>Log in with an approved sorting center account to view the dashboard.</p>
        <a href="/sorting-center/login" class="app-button-primary" >Go to login</a>
      </div>

      <template v-else>
        <div v-if="feedback.message" class="app-feedback" :class="feedback.type === 'error' ? 'app-feedback--error' : 'app-feedback--success'" role="status">
          <span>{{ feedback.message }}</span>
          <button type="button" class="app-button-secondary"  @click="feedback.message = ''">Close</button>
        </div>

        <div class="border-b border-[#E5E7EB] pb-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-[#087F8C]">
              <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>
              <span>Operations Overview</span>
            </div>
            <h1 class="mt-1 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
              Your queue at a glance
            </h1>
            <p class="mt-0.5 text-xs text-slate-500">Refresh to see the latest work waiting for your team.</p>
          </div>
          <div>
            <form class="flex flex-wrap items-end gap-3" @submit.prevent="applyDateFilter">
              <div>
                <label for="dashboard-start-date" class="block text-sm font-semibold">Start date</label>
                <input id="dashboard-start-date" v-model="startDate" type="date" class="app-input" />
              </div>
              <div>
                <label for="dashboard-end-date" class="block text-sm font-semibold">End date</label>
                <input id="dashboard-end-date" v-model="endDate" type="date" class="app-input" />
              </div>
              <button type="submit" :disabled="loading" class="app-button-primary disabled:opacity-50">Apply dates</button>
              <button type="button" :disabled="loading" class="app-button-secondary disabled:opacity-50" @click="filterToday">Today</button>
              <button v-if="appliedDates.start" type="button" :disabled="loading" class="app-button-secondary disabled:opacity-50" @click="clearDates">Clear dates</button>
              <button type="button" :disabled="loading" class="app-button-secondary disabled:opacity-50" @click="loadDashboard">
                {{ loading ? 'Refreshing...' : 'Refresh' }}
              </button>
            </form>
            <p class="mt-2 text-xs text-[var(--app-text-muted)]">Creation dates in Asia/Manila; queues show current statuses. Rider counts use all dates.</p>
            <p class="mt-1 text-xs text-[var(--app-text-muted)]" role="status">{{ appliedDates.start ? `Applied: ${appliedDates.start} to ${appliedDates.end} (inclusive)` : 'Applied: all dates' }}</p>
          </div>
        </div>

        <div v-if="loading && !hasLoaded" class="app-panel app-empty-state">
          <div class="mx-auto h-8 w-8 animate-spin rounded-full border-4 border-slate-200 border-t-[#0D7377]" ></div>
          <p >Loading dashboard...</p>
        </div>

        <div v-else-if="hasLoaded" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <a v-for="card in cards" :key="card.key" :href="card.href" class="app-card group" >
            <div >
              <span class="app-card__label">{{ card.label }}</span>
              <span class="app-card__value">{{ counts[card.key] }}</span>
            </div>
            <div class="app-card__foot">
              <span>Open {{ card.destination }}</span>
              <span aria-hidden="true">→</span>
            </div>
          </a>
        </div>
        <section v-if="hasLoaded" class="app-panel app-panel--padded mt-4" aria-label="Parcel status totals">
          <h2 class="app-section-title">Parcel status totals</h2>
          <p class="app-description">One count per parcel owned by your Logistics organization, using the applied creation dates. In transit includes collection to center and delivery to Buyer; center arrivals are shown separately.</p>
          <div class="grid gap-3 sm:grid-cols-5 mt-4">
            <button v-for="bucket in buckets" :key="bucket.key" type="button" class="app-card text-left" :aria-pressed="selectedBucket === bucket.key" @click="loadParcels(bucket.key)">
              <span class="app-card__label">{{ bucket.label }}</span>
              <span class="app-card__value">{{ parcelTotals[bucket.key] }}</span>
              <span class="text-xs">{{ bucket.statuses }}</span>
            </button>
          </div>
          <template v-if="selectedBucket">
            <h3 class="app-section-title mt-4">{{ buckets.find(bucket => bucket.key === selectedBucket)?.label }} parcels</h3>
            <p role="status">{{ listLoading ? 'Loading parcels...' : `${listTotal} parcels · Page ${listPage} of ${listLastPage}` }}</p>
            <div class="overflow-x-auto">
              <table class="w-full text-left text-sm mt-3" aria-label="Parcels in selected status bucket">
                <thead><tr><th>Tracking reference</th><th>Seller</th><th>Buyer</th><th>Pickup Rider</th><th>Delivery Rider</th><th>Status</th><th>Created</th></tr></thead>
                <tbody><tr v-for="parcel in summaryParcels" :key="parcel.id"><td>{{ parcel.order_id }}</td><td>{{ parcel.seller?.name || 'Not recorded' }}</td><td>{{ parcel.buyer?.name || 'Not recorded' }}</td><td>{{ parcel.pickup_rider?.name || 'Unassigned' }}</td><td>{{ parcel.assigned_rider?.name || 'Unassigned' }}</td><td>{{ parcel.status }}</td><td>{{ parcel.created_at }}</td></tr></tbody>
              </table>
            </div>
            <div class="flex gap-3 mt-3"><button type="button" class="app-button-secondary" :disabled="listLoading || listPage <= 1" @click="loadParcels(selectedBucket, listPage - 1)">Previous parcels</button><button type="button" class="app-button-secondary" :disabled="listLoading || listPage >= listLastPage" @click="loadParcels(selectedBucket, listPage + 1)">Next parcels</button></div>
          </template>
        </section>
      </template>
    </div>
  </sc-layout>
</template>

<script>
import { onMounted, onUnmounted, reactive, ref } from 'vue';
import ScLayout from '@/Layouts/ScLayout.vue';

export default {
  name: 'Dashboard',
  components: {
    ScLayout,
  },
  setup() {
    const token = ref(localStorage.getItem('logistics_auth_token'));
    const loading = ref(false);
    const hasLoaded = ref(false);
    const startDate = ref('');
    const endDate = ref('');
    const appliedDates = reactive({ start: '', end: '' });
    const feedback = reactive({ message: '', type: 'success' });
    const buckets = [
      { key: 'pending', label: 'Pending', statuses: 'PLACED / PREPARING' },
      { key: 'in_transit', label: 'In Transit', statuses: 'PICKED_UP / OUT_FOR_DELIVERY' },
      { key: 'at_center', label: 'At Sorting Center', statuses: 'AT_SORTING_CENTER' },
      { key: 'delivered', label: 'Delivered', statuses: 'DELIVERED' },
      { key: 'failed', label: 'Failed', statuses: 'FAILED_RETURNED' },
    ];
    const parcelTotals = reactive(Object.fromEntries(buckets.map(bucket => [bucket.key, 0])));
    const selectedBucket = ref('');
    const summaryParcels = ref([]);
    const listLoading = ref(false);
    const listPage = ref(1);
    const listLastPage = ref(1);
    const listTotal = ref(0);
    const counts = reactive({
      riders_pending_approval: 0,
      pickup_requests_pending: 0,
      parcels_awaiting_sorting: 0,
      parcels_awaiting_rider_assignment: 0,
      parcels_out_for_delivery: 0,
      active_approved_riders: 0,
    });

    const cards = [
      { key: 'parcels_awaiting_sorting', label: 'Parcels awaiting sorting', destination: 'delivery management', href: '/sorting-center/delivery-management' },
      { key: 'pickup_requests_pending', label: 'Pending pickup requests', destination: 'pickup requests', href: '/sorting-center/pickup-requests' },
      { key: 'parcels_awaiting_rider_assignment', label: 'Parcels awaiting rider assignment', destination: 'rider assignment', href: '/sorting-center/rider-assignment' },
      { key: 'parcels_out_for_delivery', label: 'Parcels out for delivery', destination: 'delivery monitoring', href: '/sorting-center/delivery-monitoring' },
    ];

    let latestRequest = 0;
    let latestList = 0;
    onUnmounted(() => { latestRequest++; latestList++; });

    async function loadParcels(bucket = selectedBucket.value, page = 1) {
      if (!token.value || !buckets.some(row => row.key === bucket)) return;
      const requestId = ++latestList;
      selectedBucket.value = bucket;
      listLoading.value = true;
      summaryParcels.value = [];
      const params = new URLSearchParams({ bucket, page });
      if (appliedDates.start) { params.set('start_date', appliedDates.start); params.set('end_date', appliedDates.end); }
      try {
        const response = await fetch(`/api/sorting-center/dashboard/parcels?${params}`, { headers: { Accept: 'application/json', Authorization: `Bearer ${token.value}` } });
        const data = await response.json();
        if (requestId !== latestList) return;
        if (!response.ok) throw new Error(data.message || 'Parcel list could not be loaded.');
        summaryParcels.value = data.data.data;
        listPage.value = data.data.current_page;
        listLastPage.value = data.data.last_page;
        listTotal.value = data.data.total;
      } catch (error) {
        if (requestId === latestList) { feedback.message = error.message; feedback.type = 'error'; }
      } finally { if (requestId === latestList) listLoading.value = false; }
    }

    function validDate(value) {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
      const date = new Date(`${value}T00:00:00Z`);
      return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value;
    }

    async function applyDateFilter() {
      if (loading.value) return;
      let error = '';
      if (Boolean(startDate.value) !== Boolean(endDate.value)) error = 'Choose both start and end dates.';
      else if (startDate.value && (!validDate(startDate.value) || !validDate(endDate.value))) error = 'Choose valid start and end dates.';
      else if (startDate.value > endDate.value) error = 'End date must be on or after start date.';
      if (error) {
        feedback.message = error;
        feedback.type = 'error';
        return;
      }
      Object.assign(appliedDates, { start: startDate.value, end: endDate.value });
      latestList++;
      summaryParcels.value = [];
      hasLoaded.value = false;
      await loadDashboard();
    }

    async function filterToday() {
      if (loading.value) return;
      const parts = new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date());
      const value = type => parts.find(part => part.type === type).value;
      startDate.value = endDate.value = `${value('year')}-${value('month')}-${value('day')}`;
      await applyDateFilter();
    }

    async function clearDates() {
      if (loading.value) return;
      startDate.value = endDate.value = '';
      await applyDateFilter();
    }

    async function loadDashboard() {
      if (!token.value) return;
      const requestId = ++latestRequest;
      loading.value = true;
      const params = new URLSearchParams();
      if (appliedDates.start) {
        params.set('start_date', appliedDates.start);
        params.set('end_date', appliedDates.end);
      }
      try {
        const response = await fetch(`/api/sorting-center/dashboard${params.size ? `?${params}` : ''}`, {
          headers: {
            Accept: 'application/json',
            Authorization: `Bearer ${token.value}`,
          },
        });
        const data = await response.json();
        if (requestId !== latestRequest) return;
        if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Dashboard could not be loaded.');
        if (!data.data || !Object.keys(counts).every(key => Number.isSafeInteger(data.data[key]) && data.data[key] >= 0)) throw new Error('Invalid dashboard response. Please refresh.');
        for (const key of Object.keys(counts)) counts[key] = data.data[key];
        if (!data.data.parcel_totals || !buckets.every(bucket => Number.isSafeInteger(data.data.parcel_totals[bucket.key]) && data.data.parcel_totals[bucket.key] >= 0)) throw new Error('Invalid parcel totals. Please refresh.');
        Object.assign(parcelTotals, data.data.parcel_totals);
        hasLoaded.value = true;
        feedback.message = '';
        if (selectedBucket.value) await loadParcels();
      } catch (error) {
        if (requestId !== latestRequest) return;
        feedback.message = error.message;
        feedback.type = 'error';
      } finally {
        if (requestId === latestRequest) loading.value = false;
      }
    }

    onMounted(() => {
      loadDashboard();
    });

    return {
      token,
      loading,
      hasLoaded,
      startDate,
      endDate,
      appliedDates,
      feedback,
      counts,
      buckets, parcelTotals, selectedBucket, summaryParcels, listLoading, listPage, listLastPage, listTotal, loadParcels,
      cards,
      loadDashboard,
      applyDateFilter,
      filterToday,
      clearDates,
    };
  },
};
</script>
