<template>
  <sc-layout title="Rider Assignment">
    <div class="app-page-shell">
      <!-- Unauthenticated State -->
      <div v-if="!token" class="app-panel app-empty-state">
        <h2 class="text-amber-950 app-section-title">Authentication required</h2>
        <p class="mt-2 text-sm text-amber-800">Log in with an approved sorting center account to assign riders.</p>
        <a href="/sorting-center/login" class="mt-5 inline-flex app-button-primary">
          Go to login
        </a>
      </div>

      <template v-else>
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 border-b border-[#E5E7EB] pb-5">
          <div>
            <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-[#087F8C]">
              <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>
              <span>Fleet Dispatch</span>
            </div>
            <h1 class="mt-1 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
              Rider Assignment & Dispatch
            </h1>
            <p class="mt-0.5 text-xs text-slate-500">
              Assign ready-to-deliver parcels to active delivery riders. ({{ parcels.length }} unassigned)
            </p>
          </div>
          <button
            type="button"
            :disabled="loading"
            class="inline-flex items-center justify-center disabled:opacity-50 app-button-secondary shrink-0"
            @click="loadParcels"
          >
            {{ loading ? 'Refreshing...' : 'Refresh' }}
          </button>
        </div>

        <!-- Feedback Notification -->
        <div
          v-if="feedback.message"
          :class="feedback.type === 'success' ? 'app-feedback--success' : 'app-feedback--error'"
          class="app-feedback"
          role="status"
        >
          <span>{{ feedback.message }}</span>
          <button type="button" class="ml-4 font-bold hover:opacity-75" @click="feedback.message = ''">Close</button>
        </div>

        <!-- Loading State -->
        <div v-if="loading && parcels.length === 0" class="text-center app-panel app-empty-state">
          <div class="mx-auto h-8 w-8 animate-spin rounded-full border-4 border-gray-200 border-t-[#008B8B]"></div>
          <p class="mt-4 text-sm text-gray-500">Loading parcels and eligible riders...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="parcels.length === 0" class="text-center app-panel app-empty-state">
          <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-sm font-semibold text-emerald-600">
            Clear
          </div>
          <h2 class="mt-4 app-section-title">Nothing Needs Assignment</h2>
          <p class="mt-2 text-sm text-gray-500">All sorted parcels have been assigned to delivery riders.</p>
        </div>

        <!-- Parcels Assignment Grid -->
        <div v-else class="grid gap-4 lg:grid-cols-2">
          <article
            v-for="parcel in parcels"
            :key="parcel.id"
            class="flex flex-col justify-between app-panel app-panel--padded"
          >
            <div>
              <div class="flex items-start justify-between border-b border-[var(--app-border)] pb-4">
                <div>
                  <p class="text-xs font-semibold uppercase tracking-wider text-[var(--app-text-muted)]">Order ID</p>
                  <h3 class="mt-1 font-mono app-section-title">{{ parcel.order_id }}</h3>
                </div>
                <div class="flex items-center gap-2">
                  <span v-if="parcel.shipping_method === 'express' || parcel.is_express" class="rounded-full bg-amber-500 px-2.5 py-0.5 text-xs font-black uppercase tracking-wide text-white shadow-sm flex items-center gap-1">
                    ⚡ EXPRESS
                  </span>
                  <span class="rounded-full bg-[var(--app-primary)] px-3 py-1 text-xs font-bold uppercase tracking-wide text-white shadow-sm">
                    {{ parcel.sorted_area?.name || parcel.delivery_municipality || 'Area Assigned' }}
                  </span>
                </div>
              </div>

              <div class="py-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-[var(--app-text-muted)]">Delivery Address</p>
                <p class="mt-1 text-sm leading-6 text-gray-700 bg-gray-50 p-3 rounded-lg border border-[var(--app-border)]">
                  {{ formatAddress(parcel) }}
                </p>
              </div>
            </div>

            <div class="pt-2 border-t border-[var(--app-border)]">
              <label :for="`rider-${parcel.id}`" class="block text-sm font-semibold text-gray-700 mb-1">
                Assign Rider
              </label>
              <p v-if="!loadingRiders[parcel.id] && !riders[parcel.id]?.length" class="mb-3 text-sm text-amber-800" role="status">{{ riderReasons[parcel.id] || 'No eligible Rider covers this destination. Configure delivery areas in Rider Management.' }}</p>
              <div class="flex flex-col gap-3 sm:flex-row">
                <select
                  :id="`rider-${parcel.id}`"
                  v-model="selectedRiders[parcel.id]"
                  :disabled="loadingRiders[parcel.id]"
                  class="min-w-0 flex-1 disabled:opacity-60 app-input"
                >
                  <option value="">
                    {{ loadingRiders[parcel.id] ? 'Loading riders...' : riders[parcel.id]?.length ? 'Choose a rider' : 'No eligible riders' }}
                  </option>
                  <option v-for="rider in riders[parcel.id] || []" :key="rider.id" :value="rider.id">
                    {{ rider.name }}{{ rider.vehicle_type ? ` - ${rider.vehicle_type}` : '' }}
                    · {{ rider.deliveries_in_progress_count > 0
                      ? `Delivering ${rider.deliveries_in_progress_count}`
                      : rider.active_assignments_count > 0
                        ? `${rider.active_assignments_count} assigned`
                        : 'Available' }}
                  </option>
                </select>
                <button
                  type="button"
                  :disabled="!selectedRiders[parcel.id] || assigningId === parcel.id"
                  class="disabled:cursor-not-allowed disabled:opacity-50 app-button-primary"
                  @click="assignRider(parcel.id)"
                >
                  {{ assigningId === parcel.id ? 'Assigning...' : 'Assign Delivery' }}
                </button>
              </div>
              <p
                v-for="rider in riders[parcel.id] || []"
                :key="`workload-${rider.id}`"
                class="mt-2 text-xs"
                :class="rider.deliveries_in_progress_count > 0 ? 'text-amber-700' : 'text-emerald-700'"
              >
                {{ rider.name }}:
                <strong v-if="rider.deliveries_in_progress_count > 0">
                  Delivering {{ rider.deliveries_in_progress_count }} parcel(s)
                </strong>
                <strong v-else-if="rider.active_assignments_count > 0">
                  {{ rider.active_assignments_count }} assigned parcel(s), not started
                </strong>
                <strong v-else>Available</strong>
              </p>
              <p v-if="!loadingRiders[parcel.id] && riders[parcel.id]?.length === 0" class="mt-2 text-xs text-amber-700 font-medium">
                No approved and active rider currently covers this area.
              </p>
            </div>
          </article>
        </div>
      </template>
    </div>
  </sc-layout>
</template>

<script>
import { onMounted, reactive, ref } from 'vue';
import ScLayout from '@/Layouts/ScLayout.vue';

export default {
  name: 'RiderAssignment',
  components: {
    ScLayout,
  },
  setup() {
    const token = ref(localStorage.getItem('logistics_auth_token'));
    const parcels = ref([]);
    const riders = reactive({});
    const selectedRiders = reactive({});
    const loadingRiders = reactive({});
    const riderReasons = reactive({});
    const loading = ref(false);
    const assigningId = ref(null);
    const feedback = reactive({ message: '', type: 'success' });

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

    async function loadParcels() {
      loading.value = true;
      try {
        const data = await requestJson('/api/sorting-center/parcels?status=sorted-unassigned');
        parcels.value = data.data || [];
        await Promise.all(parcels.value.map((parcel) => loadEligibleRiders(parcel.id)));
      } catch (error) {
        feedback.message = error.message;
        feedback.type = 'error';
      } finally {
        loading.value = false;
      }
    }

    async function loadEligibleRiders(parcelId) {
      loadingRiders[parcelId] = true;
      try {
        const data = await requestJson(`/api/sorting-center/parcels/${parcelId}/eligible-riders`);
        riders[parcelId] = data.data || [];
        riderReasons[parcelId] = data.meta?.empty_reason || '';
      } catch (error) {
        riders[parcelId] = [];
        feedback.message = error.message;
        feedback.type = 'error';
      } finally {
        loadingRiders[parcelId] = false;
      }
    }

    async function assignRider(parcelId) {
      assigningId.value = parcelId;
      feedback.message = '';
      try {
        const data = await requestJson(`/api/sorting-center/parcels/${parcelId}/assign`, {
          method: 'POST',
          body: JSON.stringify({ rider_id: selectedRiders[parcelId] }),
        });
        parcels.value = parcels.value.filter((parcel) => parcel.id !== parcelId);
        feedback.message = data.message;
        feedback.type = 'success';
      } catch (error) {
        feedback.message = error.message;
        feedback.type = 'error';
      } finally {
        assigningId.value = null;
      }
    }

    function formatAddress(parcel) {
      if (parcel?.delivery_address) return parcel.delivery_address;
      return [
        [parcel.delivery_house_number, parcel.delivery_street].filter(Boolean).join(' '),
        parcel.delivery_barangay,
        parcel.delivery_municipality,
        parcel.delivery_province,
      ].filter(Boolean).join(', ');
    }

    onMounted(loadParcels);

    return {
      token,
      parcels,
      riders,
      selectedRiders,
      loadingRiders,
      riderReasons,
      loading,
      assigningId,
      feedback,
      loadParcels,
      assignRider,
      formatAddress,
    };
  },
};
</script>
