<template>
  <sc-layout title="Dashboard">
    <div class="app-page-shell">
      <div v-if="!token" class="app-panel app-empty-state">
        <h2 class="app-section-title">Authentication required</h2>
        <p>Log in with an approved sorting center account to view the dashboard.</p>
        <a href="/sorting-center/login" class="app-button-primary" >Go to login</a>
      </div>

      <template v-else>
        <div v-if="feedback.message" class="app-feedback" role="status">
          <span>{{ feedback.message }}</span>
          <button type="button" class="app-button-secondary"  @click="feedback.message = ''">Close</button>
        </div>

        <div class="app-panel app-panel--padded app-toolbar">
          <div>
            <p class="app-eyebrow">Operations overview</p>
            <h2 class="app-section-title">Your queue at a glance</h2>
            <p class="app-description">Refresh to see the latest work waiting for your team.</p>
          </div>
          <button type="button" :disabled="loading" class="app-button-secondary" @click="loadDashboard">
            {{ loading ? 'Refreshing...' : 'Refresh' }}
          </button>
        </div>

        <div v-if="loading && !hasLoaded" class="app-panel app-empty-state">
          <div class="mx-auto h-8 w-8 animate-spin rounded-full border-4 border-slate-200 border-t-[#0D7377]" ></div>
          <p >Loading dashboard...</p>
        </div>

        <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
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
      </template>
    </div>
  </sc-layout>
</template>

<script>
import { onMounted, reactive, ref } from 'vue';
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
    const feedback = reactive({ message: '', type: 'success' });
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

    async function loadDashboard() {
      loading.value = true;
      try {
        const response = await fetch('/api/sorting-center/dashboard', {
          headers: {
            Accept: 'application/json',
            Authorization: 'Bearer ' + (localStorage.getItem('logistics_auth_token') || ''),
          },
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Dashboard could not be loaded.');
        Object.assign(counts, data.data || {});
        hasLoaded.value = true;
        feedback.message = '';
      } catch (error) {
        feedback.message = error.message;
        feedback.type = 'error';
      } finally {
        loading.value = false;
      }
    }

    onMounted(() => {
      loadDashboard();
    });

    return {
      token,
      loading,
      hasLoaded,
      feedback,
      counts,
      cards,
      loadDashboard,
    };
  },
};
</script>
