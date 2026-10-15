<template>
  <sc-layout title="Delivery Reports">
    <div class="app-page-shell">
      <!-- Unauthenticated State -->
      <div v-if="!token" class="app-panel app-empty-state">
        <h2 class="text-amber-950 app-section-title">Authentication required</h2>
        <p class="mt-2 text-sm text-amber-800">Log in with an approved sorting center account to generate reports.</p>
        <a href="/sorting-center/login" class="mt-5 inline-flex app-button-primary">
          Go to login
        </a>
      </div>

      <template v-else>
        <!-- Filter Card -->
        <div class="app-panel app-panel--padded">
          <div class="border-b border-[var(--app-border)] pb-4 mb-6">
            <h2 class="app-section-title">Performance &amp; Analytics</h2>
            <p class="mt-1 text-sm text-gray-600">Analyze parcel throughput, delivery status distribution, and average turnaround time.</p>
          </div>

          <form class="space-y-4" @submit.prevent="generateReport">
            <div class="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
              <div>
                <label for="report-from" class="block text-sm font-semibold text-gray-700">From Date</label>
                <input
                  id="report-from"
                  v-model="form.from"
                  type="date"
                  required
                  class="mt-2 w-full app-input"
                />
              </div>
              <div>
                <label for="report-to" class="block text-sm font-semibold text-gray-700">To Date</label>
                <input
                  id="report-to"
                  v-model="form.to"
                  type="date"
                  required
                  class="mt-2 w-full app-input"
                />
              </div>
              <button
                type="submit"
                :disabled="loading || !form.from || !form.to"
                class="disabled:cursor-not-allowed disabled:opacity-50 app-button-primary"
              >
                {{ loading ? 'Generating…' : 'Generate Report' }}
              </button>
            </div>
            <p class="text-xs text-[var(--app-text-muted)]">The date filter selects parcels sorted within the specified interval.</p>
          </form>
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

        <!-- Report Results Section -->
        <section v-if="report" class="space-y-4">
          <!-- Summary Stat Cards -->
          <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="app-panel app-panel--padded">
              <p class="text-sm font-semibold text-gray-600">Total Parcels</p>
              <p class="mt-2 text-3xl font-bold text-[var(--app-primary)]">{{ report.total_parcels_processed }}</p>
              <p class="mt-2 text-xs text-[var(--app-text-muted)] font-medium">{{ report.from }} to {{ report.to }}</p>
            </div>

            <div class="app-panel app-panel--padded">
              <p class="text-sm font-semibold text-gray-600">Delivered</p>
              <p class="mt-2 text-3xl font-bold text-[var(--app-success)]">{{ report.status_breakdown?.DELIVERED || 0 }}</p>
              <p class="mt-2 text-xs text-[var(--app-text-muted)] font-medium">Successfully completed</p>
            </div>

            <div class="app-panel app-panel--padded">
              <p class="text-sm font-semibold text-gray-600">Failed / Returned</p>
              <p class="mt-2 text-3xl font-bold text-[var(--app-error)]">{{ report.status_breakdown?.FAILED_RETURNED || 0 }}</p>
              <p class="mt-2 text-xs text-[var(--app-text-muted)] font-medium">Undeliverable parcels</p>
            </div>

            <div class="app-panel app-panel--padded">
              <p class="text-sm font-semibold text-gray-600">Avg. Turnaround</p>
              <p class="mt-2 text-2xl font-bold text-[var(--app-secondary)] leading-tight">{{ report.average_turnaround || '—' }}</p>
              <p class="mt-2 text-xs text-[var(--app-text-muted)] font-medium">Sorting to delivery start</p>
            </div>
          </div>

          <!-- Charts Row -->
          <div class="grid gap-4 lg:grid-cols-2">
            <!-- Doughnut: Status Distribution -->
            <div class="app-panel app-panel--padded">
              <h3 class="mb-4 app-section-title">Status Distribution</h3>
              <div class="relative flex items-center justify-center" style="height:280px;">
                <canvas ref="doughnutCanvas" role="img" aria-label="Parcel status distribution. Exact counts are listed in the status table below."></canvas>
              </div>
            </div>

            <!-- Bar: Status Count Comparison -->
            <div class="app-panel app-panel--padded">
              <h3 class="mb-4 app-section-title">Parcel Status Breakdown</h3>
              <div style="height:280px;">
                <canvas ref="barCanvas" role="img" aria-label="Parcel counts by status. Exact counts are listed in the status table below."></canvas>
              </div>
            </div>
          </div>

          <!-- Status Breakdown Table -->
          <div class="overflow-hidden app-panel">
            <div class="p-6 border-b border-[var(--app-border)]">
              <h3 class="app-section-title">Parcel Workflow Status Breakdown</h3>
            </div>
            <table class="app-data-table">
              <thead>
                <tr >
                  <th scope="col" class="text-left">Status</th>
                  <th scope="col" class="text-right">Count</th>
                  <th scope="col" class="text-right">% of Total</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(status, idx) in statusOrder"
                  :key="status"
                  :class="idx % 2 === 0 ? 'bg-white' : 'bg-gray-50'"
                  class="border-b border-[var(--app-border)] last:border-0"
                >
                  <td class="text-gray-700">
                    <span class="h-2.5 w-2.5 rounded-full flex-shrink-0" :style="{ backgroundColor: statusColors[status] }"></span>
                    {{ formatStatus(status) }}
                  </td>
                  <td class="text-right">
                    {{ report.status_breakdown[status] || 0 }}
                  </td>
                  <td class="text-right text-gray-600">
                    {{ report.total_parcels_processed > 0
                        ? Math.round(((report.status_breakdown[status] || 0) / report.total_parcels_processed) * 100)
                        : 0 }}%
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Empty Results -->
        <div v-else-if="hasSearched && !loading" class="text-center app-panel app-empty-state">
          <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-cyan-50 text-sm font-semibold text-[var(--app-primary)]">None</div>
          <h2 class="mt-4 app-section-title">No Parcels Found in Date Range</h2>
          <p class="mt-2 text-sm text-gray-500">Try selecting a different date range to generate the report.</p>
        </div>

        <!-- Initial Prompt -->
        <div v-else-if="!hasSearched && !loading" class="text-center app-panel app-empty-state">
          <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-teal-50 text-sm font-semibold">Date Range</div>
          <h2 class="mt-4 app-section-title">Select a Date Range</h2>
          <p class="mt-2 text-sm text-gray-500">Choose start and end dates above, then click "Generate Report" to view analytics.</p>
        </div>
      </template>
    </div>
  </sc-layout>
</template>

<script>
import { reactive, ref, nextTick, onBeforeUnmount } from 'vue';
import Chart from 'chart.js/auto';
import ScLayout from '@/Layouts/ScLayout.vue';

export default {
  name: 'DeliveryReports',
  components: { ScLayout },
  setup() {
    const token = ref(localStorage.getItem('logistics_auth_token'));

    // Default date range: last 7 days
    const today = new Date();
    const sevenDaysAgo = new Date(today);
    sevenDaysAgo.setDate(today.getDate() - 7);
    const toDateStr = today.toISOString().slice(0, 10);
    const fromDateStr = sevenDaysAgo.toISOString().slice(0, 10);

    const form = reactive({ from: fromDateStr, to: toDateStr });
    const report = ref(null);
    const loading = ref(false);
    const hasSearched = ref(false);
    const feedback = reactive({ message: '', type: 'success' });

    const statusOrder = ['AT_SORTING_CENTER', 'OUT_FOR_DELIVERY', 'DELIVERED', 'FAILED_RETURNED'];
    const statusColors = {
      AT_SORTING_CENTER: '#0d7377',
      OUT_FOR_DELIVERY: '#d97b06',
      DELIVERED: '#2d6a4f',
      FAILED_RETURNED: '#c1121f',
    };

    // Chart canvas refs
    const doughnutCanvas = ref(null);
    const barCanvas = ref(null);

    // Chart instances (for cleanup)
    let doughnutChart = null;
    let barChart = null;

    function destroyCharts() {
      if (doughnutChart) { doughnutChart.destroy(); doughnutChart = null; }
      if (barChart) { barChart.destroy(); barChart = null; }
    }

    function renderCharts(data) {
      destroyCharts();

      const labels = statusOrder.map(formatStatus);
      const counts = statusOrder.map(s => data.status_breakdown[s] || 0);
      const colors = statusOrder.map(s => statusColors[s]);

      // Doughnut — Status Distribution
      if (doughnutCanvas.value) {
        doughnutChart = new Chart(doughnutCanvas.value, {
          type: 'doughnut',
          data: {
            labels,
            datasets: [{
              data: counts,
              backgroundColor: colors,
              borderColor: '#ffffff',
              borderWidth: 3,
              hoverOffset: 8,
            }],
          },
          options: {
            animation: false,
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: {
              legend: {
                position: 'bottom',
                labels: {
                  padding: 16,
                  font: { size: 12, weight: 'bold' },
                  color: '#333333',
                  usePointStyle: true,
                  pointStyleWidth: 10,
                },
              },
              tooltip: {
                callbacks: {
                  label: (ctx) => {
                    const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                    const pct = total > 0 ? Math.round((ctx.parsed / total) * 100) : 0;
                    return ` ${ctx.label}: ${ctx.parsed} (${pct}%)`;
                  },
                },
              },
            },
          },
        });
      }

      // Bar — Status Breakdown
      if (barCanvas.value) {
        barChart = new Chart(barCanvas.value, {
          type: 'bar',
          data: {
            labels,
            datasets: [{
              label: 'Parcel Count',
              data: counts,
              backgroundColor: colors.map(c => c + 'CC'),   // slight transparency
              borderColor: colors,
              borderWidth: 2,
              borderRadius: 6,
              borderSkipped: false,
            }],
          },
          options: {
            animation: false,
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { display: false },
              tooltip: {
                callbacks: {
                  label: (ctx) => ` Count: ${ctx.parsed.y}`,
                },
              },
            },
            scales: {
              y: {
                beginAtZero: true,
                ticks: {
                  stepSize: 1,
                  color: '#666666',
                  font: { size: 12 },
                },
                grid: { color: '#DCE7E2' },
              },
              x: {
                ticks: {
                  color: '#333333',
                  font: { size: 11, weight: 'bold' },
                  maxRotation: 20,
                },
                grid: { display: false },
              },
            },
          },
        });
      }
    }

    async function generateReport() {
      loading.value = true;
      report.value = null;
      hasSearched.value = true;
      feedback.message = '';
      destroyCharts();

      try {
        const params = new URLSearchParams({ from: form.from, to: form.to });
        const response = await fetch(`/api/sorting-center/reports?${params}`, {
          headers: { Accept: 'application/json', Authorization: `Bearer ${token.value}` },
        });
        const json = await response.json();
        if (!response.ok) throw new Error(json.message || 'The report could not be generated.');

        report.value = json.data;
        feedback.message = json.message || 'Report generated successfully.';
        feedback.type = 'success';

        // Wait for Vue to render chart canvases before drawing
        await nextTick();
        if (json.data && json.data.total_parcels_processed > 0) {
          renderCharts(json.data);
        }
      } catch (error) {
        feedback.message = error.message;
        feedback.type = 'error';
      } finally {
        loading.value = false;
      }
    }

    function formatStatus(status) {
      const map = {
        AT_SORTING_CENTER: 'At Sorting Center',
        OUT_FOR_DELIVERY: 'Out for Delivery',
        DELIVERED: 'Delivered',
        FAILED_RETURNED: 'Failed / Returned',
      };
      return map[status] || (status ? status.replaceAll('_', ' ') : 'Unknown');
    }

    // Clean up Chart.js instances when component unmounts
    onBeforeUnmount(destroyCharts);

    return {
      token,
      form,
      report,
      loading,
      hasSearched,
      feedback,
      statusOrder,
      statusColors,
      doughnutCanvas,
      barCanvas,
      generateReport,
      formatStatus,
    };
  },
};
</script>
