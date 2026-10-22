<template>
  <sc-layout title="Delivery Management">
    <div class="app-page-shell">
      <!-- Unauthenticated State -->
      <div v-if="!token" class="app-panel app-empty-state">
        <h2 class="text-amber-950 app-section-title">Authentication required</h2>
        <p class="mt-2 text-sm text-amber-800">Log in with an approved sorting center account to sort parcels.</p>
        <a href="/sorting-center/login" class="mt-5 inline-flex app-button-primary">
          Go to login
        </a>
      </div>

      <template v-else>
        <form class="app-panel app-panel--padded space-y-4" @submit.prevent="saveCoverage">
          <h2 class="app-section-title">Service area coverage</h2>
          <p class="app-description">Declare the cities you serve so Riders can choose your Logistics Partner when applying.</p>
          <p v-if="areas.some(area => area.city_code)" class="text-sm text-gray-600">Current coverage: {{ areas.filter(area => area.city_code).map(area => area.city_name || area.name).join(', ') }}</p>
          <delivery-coverage-selector :form="coverageForm" />
          <button type="submit" :disabled="savingCoverage || !coverageForm.coverage_city_codes.length" class="app-button-primary disabled:opacity-50">{{ savingCoverage ? 'Saving…' : 'Save service areas' }}</button>
        </form>
        <!-- Top Status & Actions Card -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 app-panel app-panel--padded app-toolbar">
          <div>
            <p class="text-sm font-semibold text-gray-600">Arrived & Unsorted Queue</p>
            <div class="mt-1 flex items-baseline gap-2">
              <span class="text-3xl font-bold text-[var(--app-primary)]">{{ parcels.length }}</span>
              <span class="text-xs text-[var(--app-text-muted)] font-medium">parcels waiting for city service-area assignment</span>
            </div>
          </div>
          <button
            type="button"
            :disabled="loading"
            class="inline-flex items-center justify-center disabled:opacity-50 app-button-secondary"
            @click="loadData"
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
          <p class="mt-4 text-sm text-gray-500">Loading parcels and areas...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="parcels.length === 0" class="text-center app-panel app-empty-state">
          <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-sm font-semibold text-emerald-600">
            Clear
          </div>
          <h2 class="mt-4 app-section-title">Nothing Needs Sorting</h2>
          <p class="mt-2 text-sm text-gray-500">All arrived parcels have been sorted to delivery areas.</p>
        </div>

        <!-- Parcel Sorting Grid -->
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
                    Arrived
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
              <label :for="`area-${parcel.id}`" class="block text-sm font-semibold text-gray-700 mb-1">
                Assign City Service Area
              </label>
              <div class="flex flex-col gap-3 sm:flex-row">
                <select
                  :id="`area-${parcel.id}`"
                  v-model="selectedAreas[parcel.id]"
                  class="min-w-0 flex-1 app-input"
                >
                  <option value="">Choose {{ parcel.delivery_municipality || 'destination city area' }}</option>
                  <option v-for="area in areasForParcel(parcel)" :key="area.id" :value="area.id">{{ area.label || area.name }}</option>
                </select>
                <button
                  type="button"
                  :disabled="!selectedAreas[parcel.id] || sortingId === parcel.id"
                  class="disabled:cursor-not-allowed disabled:opacity-50 app-button-primary"
                  @click="sortParcel(parcel.id)"
                >
                  {{ sortingId === parcel.id ? 'Assigning...' : 'Sort to Area' }}
                </button>
              </div>
              <p v-if="areasForParcel(parcel).length === 0" class="mt-2 text-xs text-amber-700 font-medium">
                No service area yet for {{ parcel.delivery_municipality || 'this destination' }}.
                <input v-if="!parcel.delivery_municipality" v-model="parcel.destination_city" class="app-input mt-2" placeholder="Destination city from the delivery address" :aria-label="`Destination city for ${parcel.order_id}`" />
                <button
                  type="button"
                  class="font-bold text-[var(--app-primary)] underline"
                  :disabled="creatingArea || (!parcel.delivery_municipality && !parcel.destination_city?.trim())"
                  @click="createCityArea(parcel)"
                >
                  {{ creatingArea ? 'Creating...' : 'Add this city' }}
                </button>
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
import DeliveryCoverageSelector from '@/Components/DeliveryCoverageSelector.vue';

export default {
  name: 'DeliveryManagement',
  components: {
    ScLayout,
    DeliveryCoverageSelector,
  },
  setup() {
    const token = ref(localStorage.getItem('logistics_auth_token'));
    const parcels = ref([]);
    const areas = ref([]);
    const selectedAreas = reactive({});
    const loading = ref(false);
    const sortingId = ref(null);
    const creatingArea = ref(false);
    const savingCoverage = ref(false);
    const coverageForm = reactive({ coverage_city_codes: [], errors: {} });
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

      if (!response.ok) {
        const error = new Error(data.message || 'The request could not be completed.');
        error.fields = data.errors || {};
        throw error;
      }
      return data;
    }

    async function loadData() {
      loading.value = true;
      try {
        const [parcelData, areaData] = await Promise.all([
          requestJson('/api/sorting-center/parcels'),
          requestJson('/api/sorting-center/areas'),
        ]);
        parcels.value = parcelData.data || [];
        areas.value = areaData.data || [];
      } catch (error) {
        feedback.message = error.message;
        feedback.type = 'error';
      } finally {
        loading.value = false;
      }
    }

    async function sortParcel(id) {
      sortingId.value = id;
      feedback.message = '';
      try {
        const data = await requestJson(`/api/sorting-center/parcels/${id}/sort`, {
          method: 'POST',
          body: JSON.stringify({ area_id: selectedAreas[id] }),
        });
        parcels.value = parcels.value.filter((parcel) => parcel.id !== id);
        delete selectedAreas[id];
        feedback.message = data.message;
        feedback.type = 'success';
      } catch (error) {
        feedback.message = error.message;
        feedback.type = 'error';
      } finally {
        sortingId.value = null;
      }
    }

    function areasForParcel(parcel) {
      const normalize = name => String(name || '').trim().replace(/(^city of\s+|\s+city$)/gi, '').trim().toLocaleLowerCase();
      const municipality = normalize(parcel.delivery_municipality);
      if (!municipality) return areas.value;
      return areas.value.filter((area) => normalize(area.city_name || area.name) === municipality && (!area.province_name || !parcel.delivery_province || area.province_name.trim().toLocaleLowerCase() === parcel.delivery_province.trim().toLocaleLowerCase()));
    }

    async function saveCoverage() {
      if (savingCoverage.value) return;
      savingCoverage.value = true;
      coverageForm.errors = {};
      try {
        const data = await requestJson('/api/sorting-center/coverage', { method: 'POST', body: JSON.stringify({ coverage_city_codes: coverageForm.coverage_city_codes }) });
        coverageForm.coverage_city_codes = [];
        await loadData();
        feedback.message = data.message;
        feedback.type = 'success';
      } catch (error) {
        coverageForm.errors = Object.fromEntries(Object.entries(error.fields || {}).map(([key, messages]) => [key, Array.isArray(messages) ? messages[0] : messages]));
        feedback.message = error.message;
        feedback.type = 'error';
      } finally { savingCoverage.value = false; }
    }

    async function createCityArea(parcel) {
      creatingArea.value = true;
      feedback.message = '';
      try {
        const data = await requestJson('/api/sorting-center/areas', {
          method: 'POST',
          body: JSON.stringify({ name: parcel.delivery_municipality || parcel.destination_city?.trim() }),
        });
        areas.value.push(data.data);
        selectedAreas[parcel.id] = data.data.id;
        feedback.message = data.message;
        feedback.type = 'success';
      } catch (error) {
        feedback.message = error.message;
        feedback.type = 'error';
      } finally {
        creatingArea.value = false;
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

    onMounted(loadData);

    return {
      coverageForm,
      savingCoverage,
      saveCoverage,
      token,
      parcels,
      areas,
      selectedAreas,
      loading,
      sortingId,
      creatingArea,
      feedback,
      loadData,
      sortParcel,
      areasForParcel,
      createCityArea,
      formatAddress,
    };
  },
};
</script>
