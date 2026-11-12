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
        <!-- Page Header -->
        <div class="border-b border-[#E5E7EB] pb-5">
          <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-[#087F8C]">
            <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>
            <span>Reports</span>
          </div>
          <h1 class="mt-1 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Performance &amp; Analytics</h1>
          <p class="mt-0.5 text-xs text-slate-500">Analyze parcel throughput, delivery status distribution, and turnaround times by date range.</p>
        </div>

        <!-- Filter Card -->
        <div class="app-panel app-panel--padded">
          <form class="space-y-4" @submit.prevent="generateReport">
            <div class="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
              <div>
                <label for="report-from" class="block text-sm font-semibold text-gray-700">From Date</label>
                <input
                  id="report-from"
                  v-model="form.from"
                  :disabled="loading || exporting || rowsLoading"
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
                  :disabled="loading || exporting || rowsLoading"
                  type="date"
                  required
                  class="mt-2 w-full app-input"
                />
              </div>
              <button
                type="submit"
                :disabled="loading || exporting || rowsLoading || !form.from || !form.to"
                class="disabled:cursor-not-allowed disabled:opacity-50 app-button-primary"
              >
                {{ loading ? 'Generating…' : 'Generate Report' }}
              </button>
            </div>
            <p class="text-xs text-[var(--app-text-muted)]">The date filter selects parcels sorted within the specified interval.</p>
          </form>
        </div>

        <section v-if="appliedRange" class="app-panel app-panel--padded space-y-4" aria-labelledby="delivery-export-heading">
          <h3 id="delivery-export-heading" class="app-section-title">Delivery records and exports</h3>
          <p class="app-description">Applied sorting-date range: {{ appliedRange.from }} to {{ appliedRange.to }}. Exports include all {{ rowsPagination.total }} matching parcel records.</p>
          <p class="text-sm text-amber-800">Delivery exports contain parcel details. COD cash events and confirmation are reported separately below.</p>
          <div class="flex flex-wrap gap-3">
            <button type="button" class="app-button-secondary" :disabled="loading || rowsLoading || exporting" @click="exportReport('csv')">Export delivery CSV</button>
            <button type="button" class="app-button-secondary" :disabled="loading || rowsLoading || exporting" @click="exportReport('xlsx')">Export delivery XLSX</button>
            <span v-if="exporting" role="status">Preparing download...</span>
          </div>
          <div class="overflow-x-auto">
            <table class="app-data-table">
              <caption class="sr-only">Delivery records for the applied date range</caption>
              <thead><tr><th>Parcel ID</th><th>Tracking reference</th><th>Buyer</th><th>Seller</th><th>Delivery Rider</th><th>Status</th><th>Province</th><th>City</th><th>Sorted at</th><th>Delivered at</th></tr></thead>
              <tbody><tr v-for="row in transactions" :key="row.parcel_id"><td>{{ row.parcel_id }}</td><td>{{ row.order_id }}</td><td>{{ row.buyer_name }}</td><td>{{ row.seller_name }}</td><td>{{ row.rider_name }}</td><td>{{ row.status }}</td><td>{{ row.delivery_province }}</td><td>{{ row.delivery_municipality }}</td><td>{{ row.sorted_at || 'Not recorded' }}</td><td>{{ row.delivered_at || 'Not recorded' }}</td></tr></tbody>
            </table>
          </div>
          <div class="flex flex-wrap items-center gap-3">
            <span>{{ rowsPagination.total }} records · Page {{ rowsPagination.current_page }} of {{ rowsPagination.last_page }}</span>
            <button type="button" class="app-button-secondary" :disabled="loading || rowsLoading || exporting || rowsPagination.current_page <= 1" @click="loadRows(rowsPagination.current_page - 1)">Previous records</button>
            <button type="button" class="app-button-secondary" :disabled="loading || rowsLoading || exporting || rowsPagination.current_page >= rowsPagination.last_page" @click="loadRows(rowsPagination.current_page + 1)">Next records</button>
          </div>
        </section>

        <cod-cash-panel v-if="appliedRange" :range="appliedRange" :token="token" />

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
import CodCashPanel from '@/Components/Logistics/CodCashPanel.vue';

export default {
  name: 'DeliveryReports',
  components: { ScLayout, CodCashPanel },
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
    const appliedRange = ref(null);
    const transactions = ref([]);
    const rowsPagination = ref({ total: 0, current_page: 1, last_page: 1 });
    const rowsLoading = ref(false);
    const exporting = ref(false);
    let disposed = false;
    let exportAbort = null;
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
      if (disposed || loading.value || rowsLoading.value || exporting.value) return;
      loading.value = true;
      report.value = null;
      appliedRange.value = null;
      transactions.value = [];
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

        if (disposed) return;
        if (!json.data || json.data.from !== form.from || json.data.to !== form.to) throw new Error('Unexpected report date range. Please try again.');
        appliedRange.value = { from: json.data.from, to: json.data.to };
        if (!await loadRows(1)) throw new Error('Delivery records could not be loaded. Please try again.');
        report.value = json.data;
        feedback.message = json.message || 'Report generated successfully.';
        feedback.type = 'success';

        // Wait for Vue to render chart canvases before drawing
        await nextTick();
        if (json.data && json.data.total_parcels_processed > 0) {
          renderCharts(json.data);
        }
      } catch (error) {
        appliedRange.value = null;
        feedback.message = error.message;
        feedback.type = 'error';
      } finally {
        loading.value = false;
      }
    }

    async function loadRows(page = 1) {
      if (disposed || rowsLoading.value || !appliedRange.value || !Number.isInteger(page) || page < 1) return false;
      rowsLoading.value = true;
      try {
        const params = new URLSearchParams({ ...appliedRange.value, page, per_page: 50 });
        const response = await fetch(`/api/sorting-center/reports/transactions?${params}`, {
          headers: { Accept: 'application/json', Authorization: `Bearer ${token.value}` },
        });
        const json = await response.json();
        if (!response.ok || !Array.isArray(json.data) || !Number.isInteger(json.pagination?.total)) throw new Error(json.message || 'Unable to load delivery records.');
        if (disposed) return false;
        transactions.value = json.data; rowsPagination.value = json.pagination;
        return true;
      } catch (error) {
        if (!disposed) { feedback.type = 'error'; feedback.message = error.message; }
        return false;
      } finally { rowsLoading.value = false; }
    }

    async function exportReport(format) {
      if (disposed || loading.value || rowsLoading.value || exporting.value || !appliedRange.value || !['csv', 'xlsx'].includes(format)) return;
      const range = { ...appliedRange.value };
      exporting.value = true; feedback.message = ''; exportAbort = new AbortController();
      let url = null;
      try {
        const params = new URLSearchParams({ ...range, format, dataset: 'deliveries' });
        const response = await fetch(`/api/sorting-center/reports/export?${params}`, {
          headers: { Accept: format === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', Authorization: `Bearer ${token.value}` },
          signal: exportAbort.signal,
        });
        if (!response.ok) {
          const error = await response.json().catch(() => null);
          throw new Error(error?.message || 'Unable to export this report. Please retry.');
        }
        const expected = format === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        if (!response.headers.get('Content-Type')?.startsWith(expected)) throw new Error('Unexpected export response. Please log in again or retry.');
        const blob = await response.blob();
        if (disposed) return;
        url = URL.createObjectURL(blob);
        const link = document.createElement('a'); link.href = url;
        link.download = `deliveries-${range.from}-to-${range.to}.${format}`;
        document.body.appendChild(link); link.click(); link.remove();
        feedback.type = 'success'; feedback.message = 'Delivery report downloaded.';
      } catch (error) {
        if (!disposed) { feedback.type = 'error'; feedback.message = error.message || 'Unable to export this report.'; }
      } finally {
        if (url) URL.revokeObjectURL(url);
        exporting.value = false; exportAbort = null;
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
    onBeforeUnmount(() => { disposed = true; exportAbort?.abort(); destroyCharts(); });

    return {
      token,
      form,
      report,
      appliedRange, transactions, rowsPagination, rowsLoading, exporting, loadRows, exportReport,
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
