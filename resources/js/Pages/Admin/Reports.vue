<script setup>
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    title: {
        type: String,
        default: 'Reports',
    },

    eyebrow: {
        type: String,
        default: 'Reports',
    },

    description: {
        type: String,
        default: '',
    },

    active: {
        type: String,
        default: 'reports',
    },

    report: {
        type: Object,
        default: () => ({}),
    },
})

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

const from = ref(props.report.from ?? '')
const to = ref(props.report.to ?? '')

const isLoading = ref(false)

/*
|--------------------------------------------------------------------------
| Report type
|--------------------------------------------------------------------------
*/

const isCommissionReport = computed(() => {
    return props.report.type === 'commission' || props.active === 'finance'
})

const isSalesReport = computed(() => {
    return !isCommissionReport.value
})

/*
|--------------------------------------------------------------------------
| Formatting
|--------------------------------------------------------------------------
*/

const formatCurrency = (value) => {
    return `₱${Number(value ?? 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`
}

const formatNumber = (value) => {
    return Number(value ?? 0).toLocaleString('en-PH')
}

const formatDate = (value) => {
    if (!value) {
        return '—'
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return value
    }

    return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}

/*
|--------------------------------------------------------------------------
| Summary cards
|--------------------------------------------------------------------------
*/

const salesSummaryCards = computed(() => [
    {
        label: 'Gross Sales',
        value: formatCurrency(
            props.report.gross_sales ??
            props.report.total_sales ??
            0
        ),
        icon: 'sales',
    },
    {
        label: 'Net Sales',
        value: formatCurrency(props.report.net_sales ?? 0),
        icon: 'net',
    },
    {
        label: 'Completed Orders',
        value: formatNumber(props.report.completed_orders ?? 0),
        icon: 'orders',
    },
    {
        label: 'Products Sold',
        value: formatNumber(props.report.products_sold ?? 0),
        icon: 'products',
    },
])

const commissionSummaryCards = computed(() => [
    {
        label: 'Eligible Sales',
        value: formatCurrency(props.report.eligible_sales ?? 0),
        icon: 'eligible',
    },
    {
        label: 'Total Commission',
        value: formatCurrency(props.report.total_commission ?? 0),
        icon: 'commission',
    },
    {
        label: 'Seller Earnings',
        value: formatCurrency(props.report.total_seller_amount ?? 0),
        icon: 'earnings',
    },
    {
        label: 'Commission Records',
        value: formatNumber(props.report.commission_records ?? 0),
        icon: 'records',
    },
])

const summaryCards = computed(() => {
    return isCommissionReport.value
        ? commissionSummaryCards.value
        : salesSummaryCards.value
})

/*
|--------------------------------------------------------------------------
| Commission data
|--------------------------------------------------------------------------
*/

const commissionRows = computed(() => {
    return props.report.commission_by_seller ?? []
})

const orderRows = computed(() => {
    return props.report.commission_by_order ?? []
})

/*
|--------------------------------------------------------------------------
| Report navigation
|--------------------------------------------------------------------------
*/

const goToSalesReport = () => {
    isLoading.value = true

    router.get(
        route('admin.reports.sales-summary'),
        {
            from: from.value,
            to: to.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isLoading.value = false
            },
        }
    )
}

const goToCommissionReport = () => {
    isLoading.value = true

    router.get(
        route('admin.reports.commission'),
        {
            from: from.value,
            to: to.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isLoading.value = false
            },
        }
    )
}

const applyFilters = () => {
    if (isCommissionReport.value) {
        goToCommissionReport()
    } else {
        goToSalesReport()
    }
}

const resetFilters = () => {
    from.value = props.report.from ?? ''
    to.value = props.report.to ?? ''

    applyFilters()
}

/*
|--------------------------------------------------------------------------
| Commission status
|--------------------------------------------------------------------------
*/

const statusClasses = (status) => {
    const classes = {
        pending: 'bg-[#FFF8E7] text-[#9A6B00] ring-[#F4B942]/30',
        approved: 'bg-blue-50 text-blue-700 ring-blue-200',
        paid: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        completed: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        cancelled: 'bg-red-50 text-red-700 ring-red-200',
    }

    return classes[status] ?? 'bg-slate-50 text-slate-600 ring-slate-200'
}

const statusLabel = (status) => {
    if (!status) {
        return 'Unknown'
    }

    return status
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase())
}
</script>

<template>
    <Head :title="title || 'Reports'" />

    <AdminLayout :active="active || 'reports'">
        <div class="mx-auto max-w-7xl">

            <!-- =========================================================
                 PAGE HEADER
            ========================================================== -->
            <div class="pb-5">
                <div class="flex items-center gap-2">
                    <span
                        class="h-1.5 w-1.5 rounded-full bg-[#F4B942]"
                    ></span>

                    <p
                        class="text-xs font-bold uppercase tracking-[0.14em] text-[#087F8C]"
                    >
                        {{ eyebrow || 'Reports' }}
                    </p>
                </div>

                <h1
                    class="mt-2 text-2xl font-bold tracking-tight text-[#1F2937] sm:text-3xl"
                >
                    {{ title || 'Reports' }}
                </h1>

                <p
                    v-if="description"
                    class="mt-1.5 max-w-3xl text-sm leading-5 text-[#64748B]"
                >
                    {{ description }}
                </p>
            </div>


            <!-- =========================================================
                 REPORT SWITCHER
            ========================================================== -->
            <div
                class="inline-flex max-w-full flex-wrap gap-1 rounded-xl border border-[#E5E7EB] bg-white p-1.5 shadow-sm"
            >
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-[11px] font-semibold transition"
                    :class="
                        isSalesReport
                            ? 'bg-[#087F8C] text-white shadow-sm'
                            : 'text-[#64748B] hover:bg-[#E8F7F6] hover:text-[#087F8C]'
                    "
                    :disabled="isLoading"
                    @click="goToSalesReport"
                >
                    <svg
                        class="h-3.5 w-3.5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M4 19V5m0 14h16"
                        />

                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="m7 15 3-4 3 2 5-7"
                        />
                    </svg>

                    Sales Summary
                </button>

                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-[11px] font-semibold transition"
                    :class="
                        isCommissionReport
                            ? 'bg-[#087F8C] text-white shadow-sm'
                            : 'text-[#64748B] hover:bg-[#E8F7F6] hover:text-[#087F8C]'
                    "
                    :disabled="isLoading"
                    @click="goToCommissionReport"
                >
                    <svg
                        class="h-3.5 w-3.5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <circle
                            cx="12"
                            cy="12"
                            r="8.5"
                        />

                        <path
                            stroke-linecap="round"
                            d="M12 7.5v9M14.5 9.5c-.5-1-1.5-1.5-2.8-1.5-1.5 0-2.7.7-2.7 2 0 3 5.5 1.4 5.5 4 0 1.2-1.2 2-2.8 2-1.3 0-2.4-.5-2.9-1.5"
                        />
                    </svg>

                    Commission Report
                </button>
            </div>


            <!-- =========================================================
                 DATE FILTERS
            ========================================================== -->
            <section
                class="mt-5 rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
            >
                <div class="px-5 py-4">

                    <div class="flex items-center gap-2">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]"
                        >
                            <svg
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <rect
                                    x="3"
                                    y="4"
                                    width="18"
                                    height="17"
                                    rx="2"
                                />

                                <path
                                    stroke-linecap="round"
                                    d="M8 2v4M16 2v4M3 10h18"
                                />
                            </svg>
                        </div>

                        <div>
                            <h2
                                class="text-base font-bold text-[#1F2937]"
                            >
                                Report Period
                            </h2>

                            <p
                                class="mt-0.5 text-xs text-[#64748B]"
                            >
                                Choose the date range for this report.
                            </p>
                        </div>
                    </div>

                    <div
                        class="mt-4 grid gap-3 md:grid-cols-[1fr_1fr_auto_auto]"
                    >
                        <div>
                            <label
                                for="from"
                                class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                            >
                                From
                            </label>

                            <input
                                id="from"
                                v-model="from"
                                type="date"
                                class="w-full rounded-lg border border-[#E5E7EB] bg-white px-3 py-2.5 text-xs font-medium text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10"
                            />
                        </div>

                        <div>
                            <label
                                for="to"
                                class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                            >
                                To
                            </label>

                            <input
                                id="to"
                                v-model="to"
                                type="date"
                                class="w-full rounded-lg border border-[#E5E7EB] bg-white px-3 py-2.5 text-xs font-medium text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10"
                            />
                        </div>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-[#087F8C] px-4 py-2.5 text-[11px] font-semibold text-white shadow-sm transition hover:bg-[#066D77] disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="isLoading"
                            @click="applyFilters"
                        >
                            <svg
                                v-if="!isLoading"
                                class="h-3.5 w-3.5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M3 5h18M6 12h12M10 19h4"
                                />
                            </svg>

                            <svg
                                v-else
                                class="h-3.5 w-3.5 animate-spin"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <circle
                                    cx="12"
                                    cy="12"
                                    r="9"
                                    class="opacity-30"
                                />

                                <path
                                    stroke-linecap="round"
                                    d="M21 12a9 9 0 0 1-9 9"
                                />
                            </svg>

                            {{ isLoading ? 'Loading...' : 'Apply Filters' }}
                        </button>

                        <button
                            type="button"
                            class="rounded-lg border border-[#E5E7EB] bg-white px-4 py-2.5 text-[11px] font-semibold text-[#64748B] transition hover:border-[#087F8C] hover:bg-[#E8F7F6] hover:text-[#087F8C] disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="isLoading"
                            @click="resetFilters"
                        >
                            Reset
                        </button>
                    </div>

                    <div
                        class="mt-3 flex flex-wrap items-center gap-1.5 border-t border-[#E5E7EB] pt-3 text-[10px] text-[#94A3B8]"
                    >
                        <span>Report period:</span>

                        <span class="font-semibold text-[#64748B]">
                            {{ formatDate(report.from) }}
                        </span>

                        <span>→</span>

                        <span class="font-semibold text-[#64748B]">
                            {{ formatDate(report.to) }}
                        </span>
                    </div>
                </div>
            </section>


            <!-- =========================================================
                 SUMMARY CARDS
            ========================================================== -->
            <div
                class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
            >
                <div
                    v-for="item in summaryCards"
                    :key="item.label"
                    class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="flex items-start justify-between gap-3">

                        <div class="min-w-0">
                            <p
                                class="text-[10px] font-bold uppercase tracking-[0.1em] text-[#94A3B8]"
                            >
                                {{ item.label }}
                            </p>

                            <p
                                class="mt-2 truncate text-xl font-bold tracking-tight text-[#1F2937] sm:text-2xl"
                            >
                                {{ item.value }}
                            </p>
                        </div>

                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                        >

                            <!-- Sales -->
                            <svg
                                v-if="item.icon === 'sales'"
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M4 19V5m0 14h16"
                                />

                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="m7 15 3-4 3 2 5-7"
                                />
                            </svg>

                            <!-- Net -->
                            <svg
                                v-else-if="item.icon === 'net'"
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <circle
                                    cx="12"
                                    cy="12"
                                    r="8.5"
                                />

                                <path
                                    stroke-linecap="round"
                                    d="M12 7.5v9M14.5 9.5c-.5-1-1.5-1.5-2.8-1.5-1.5 0-2.7.7-2.7 2 0 3 5.5 1.4 5.5 4 0 1.2-1.2 2-2.8 2-1.3 0-2.4-.5-2.9-1.5"
                                />
                            </svg>

                            <!-- Orders -->
                            <svg
                                v-else-if="item.icon === 'orders'"
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M6 3h12v18H6z"
                                />

                                <path
                                    stroke-linecap="round"
                                    d="M9 7h6M9 11h6M9 15h4"
                                />
                            </svg>

                            <!-- Products -->
                            <svg
                                v-else-if="item.icon === 'products'"
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    stroke-linejoin="round"
                                    d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"
                                />

                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="m4.5 7.5 7.5 4 7.5-4M12 11.5V21"
                                />
                            </svg>

                            <!-- Eligible -->
                            <svg
                                v-else-if="item.icon === 'eligible'"
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <circle
                                    cx="12"
                                    cy="12"
                                    r="8.5"
                                />

                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="m8.5 12 2.2 2.2 4.8-5"
                                />
                            </svg>

                            <!-- Commission -->
                            <svg
                                v-else-if="item.icon === 'commission'"
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <circle
                                    cx="12"
                                    cy="12"
                                    r="8.5"
                                />

                                <path
                                    stroke-linecap="round"
                                    d="M12 7.5v9M14.5 9.5c-.5-1-1.5-1.5-2.8-1.5-1.5 0-2.7.7-2.7 2 0 3 5.5 1.4 5.5 4 0 1.2-1.2 2-2.8 2-1.3 0-2.4-.5-2.9-1.5"
                                />
                            </svg>

                            <!-- Earnings -->
                            <svg
                                v-else-if="item.icon === 'earnings'"
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M4 18h16M6 15l3-4 3 2 5-7"
                                />
                            </svg>

                            <!-- Records -->
                            <svg
                                v-else
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M7 3h10v18H7z"
                                />

                                <path
                                    stroke-linecap="round"
                                    d="M9.5 7h5M9.5 11h5M9.5 15h3"
                                />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>


            <!-- =========================================================
                 SALES REPORT
            ========================================================== -->
            <template v-if="isSalesReport">

                <!-- SALES OVERVIEW -->
                <section
                    class="mt-5 rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                >
                    <div class="px-5 py-4">

                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="h-1.5 w-1.5 rounded-full bg-[#F4B942]"
                                    ></span>

                                    <h2
                                        class="text-base font-bold text-[#1F2937]"
                                    >
                                        Sales Overview
                                    </h2>
                                </div>

                                <p
                                    class="mt-1 text-xs text-[#64748B]"
                                >
                                    Marketplace order activity for the selected period.
                                </p>
                            </div>

                            <div
                                class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-2.5 sm:min-w-[130px] sm:text-right"
                            >
                                <p
                                    class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                >
                                    Active Orders
                                </p>

                                <p
                                    class="mt-1 text-xl font-bold text-[#1F2937]"
                                >
                                    {{ formatNumber(report.active_orders ?? 0) }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="mt-4 grid gap-3 sm:grid-cols-3"
                        >

                            <!-- TOTAL -->
                            <div
                                class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-4"
                            >
                                <p
                                    class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                >
                                    Total Orders
                                </p>

                                <p
                                    class="mt-1.5 text-xl font-bold text-[#1F2937]"
                                >
                                    {{ formatNumber(report.total_orders ?? 0) }}
                                </p>
                            </div>

                            <!-- COMPLETED -->
                            <div
                                class="rounded-xl border border-emerald-100 bg-emerald-50 p-4"
                            >
                                <p
                                    class="text-[10px] font-bold uppercase tracking-[0.12em] text-emerald-600"
                                >
                                    Completed
                                </p>

                                <p
                                    class="mt-1.5 text-xl font-bold text-emerald-800"
                                >
                                    {{ formatNumber(report.completed_orders ?? 0) }}
                                </p>
                            </div>

                            <!-- CANCELLED -->
                            <div
                                class="rounded-xl border border-red-100 bg-red-50 p-4"
                            >
                                <p
                                    class="text-[10px] font-bold uppercase tracking-[0.12em] text-red-600"
                                >
                                    Cancelled
                                </p>

                                <p
                                    class="mt-1.5 text-xl font-bold text-red-800"
                                >
                                    {{ formatNumber(report.cancelled_orders ?? 0) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>


                <!-- REPORT NOTES -->
                <section
                    class="mt-5 rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                >
                    <div class="px-5 py-4">

                        <div class="flex items-center gap-2">
                            <span
                                class="h-1.5 w-1.5 rounded-full bg-[#F4B942]"
                            ></span>

                            <h2
                                class="text-base font-bold text-[#1F2937]"
                            >
                                Report Notes
                            </h2>
                        </div>

                        <div
                            class="mt-3 grid gap-3 md:grid-cols-2"
                        >
                            <div
                                class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-4"
                            >
                                <p
                                    class="text-xs font-semibold text-[#1F2937]"
                                >
                                    Gross Sales
                                </p>

                                <p
                                    class="mt-1 text-[11px] leading-5 text-[#64748B]"
                                >
                                    Order value excluding cancelled orders.
                                </p>
                            </div>

                            <div
                                class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-4"
                            >
                                <p
                                    class="text-xs font-semibold text-[#1F2937]"
                                >
                                    Net Sales
                                </p>

                                <p
                                    class="mt-1 text-[11px] leading-5 text-[#64748B]"
                                >
                                    Sales from completed or delivered orders only.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>
            </template>


            <!-- =========================================================
                 COMMISSION REPORT
            ========================================================== -->
            <template v-else>

                <!-- COMMISSION OVERVIEW -->
                <section
                    class="mt-5 rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                >
                    <div class="px-5 py-4">

                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="h-1.5 w-1.5 rounded-full bg-[#F4B942]"
                                    ></span>

                                    <h2
                                        class="text-base font-bold text-[#1F2937]"
                                    >
                                        Commission Overview
                                    </h2>
                                </div>

                                <p
                                    class="mt-1 text-xs text-[#64748B]"
                                >
                                    Commission records already recorded in the marketplace.
                                </p>
                            </div>

                            <div
                                class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-2.5 sm:min-w-[130px] sm:text-right"
                            >
                                <p
                                    class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                >
                                    Effective Rate
                                </p>

                                <p
                                    class="mt-1 text-xl font-bold text-[#1F2937]"
                                >
                                    {{
                                        report.commission_rate !== null &&
                                        report.commission_rate !== undefined
                                            ? `${Number(report.commission_rate).toFixed(2)}%`
                                            : '—'
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>


                <!-- EMPTY STATE -->
                <section
                    v-if="commissionRows.length === 0 && orderRows.length === 0"
                    class="mt-5 rounded-2xl border border-dashed border-[#CBD5E1] bg-white p-10 text-center shadow-sm"
                >
                    <div
                        class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                    >
                        <svg
                            class="h-6 w-6"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                    </div>

                    <h2
                        class="mt-3 text-base font-bold text-[#1F2937]"
                    >
                        No commission records
                    </h2>

                    <p
                        class="mx-auto mt-1.5 max-w-lg text-[11px] leading-5 text-[#64748B]"
                    >
                        There are no commission records for the selected date
                        range. Commission reporting will appear here once
                        commission records are recorded.
                    </p>
                </section>


                <!-- COMMISSION TABLES -->
                <template v-else>

                    <!-- COMMISSION BY SELLER -->
                    <section
                        class="mt-5 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                    >
                        <div
                            class="border-b border-[#E5E7EB] px-5 py-4"
                        >
                            <div class="flex items-center gap-2">
                                <span
                                    class="h-1.5 w-1.5 rounded-full bg-[#F4B942]"
                                ></span>

                                <h2
                                    class="text-base font-bold text-[#1F2937]"
                                >
                                    Commission by Seller
                                </h2>
                            </div>

                            <p
                                class="mt-1 text-xs text-[#64748B]"
                            >
                                Seller sales, marketplace commission, and seller earnings.
                            </p>
                        </div>

                        <div class="overflow-x-auto">
                            <table
                                class="w-full min-w-[700px] text-left"
                            >
                                <thead
                                    class="bg-[#F8FAF9] text-[10px] uppercase tracking-[0.12em] text-[#94A3B8]"
                                >
                                    <tr>
                                        <th
                                            class="px-5 py-3 font-bold"
                                        >
                                            Seller
                                        </th>

                                        <th
                                            class="px-5 py-3 text-right font-bold"
                                        >
                                            Sales
                                        </th>

                                        <th
                                            class="px-5 py-3 text-right font-bold"
                                        >
                                            Commission
                                        </th>

                                        <th
                                            class="px-5 py-3 text-right font-bold"
                                        >
                                            Seller Earnings
                                        </th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-[#E5E7EB]">
                                    <tr
                                        v-for="row in commissionRows"
                                        :key="row.seller_id ?? row.seller"
                                        class="transition hover:bg-[#F8FAF9]"
                                    >
                                        <td
                                            class="px-5 py-3.5 text-xs font-semibold text-[#1F2937]"
                                        >
                                            {{ row.seller }}
                                        </td>

                                        <td
                                            class="px-5 py-3.5 text-right text-xs text-[#64748B]"
                                        >
                                            {{ formatCurrency(row.sales_amount) }}
                                        </td>

                                        <td
                                            class="px-5 py-3.5 text-right text-xs font-semibold text-[#087F8C]"
                                        >
                                            {{ formatCurrency(row.commission_amount) }}
                                        </td>

                                        <td
                                            class="px-5 py-3.5 text-right text-xs font-semibold text-emerald-700"
                                        >
                                            {{ formatCurrency(row.seller_amount) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>


                    <!-- COMMISSION BY ORDER -->
                    <section
                        v-if="orderRows.length > 0"
                        class="mt-5 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                    >
                        <div
                            class="border-b border-[#E5E7EB] px-5 py-4"
                        >
                            <div class="flex items-center gap-2">
                                <span
                                    class="h-1.5 w-1.5 rounded-full bg-[#F4B942]"
                                ></span>

                                <h2
                                    class="text-base font-bold text-[#1F2937]"
                                >
                                    Commission by Order
                                </h2>
                            </div>

                            <p
                                class="mt-1 text-xs text-[#64748B]"
                            >
                                Detailed commission records for individual orders.
                            </p>
                        </div>

                        <div class="overflow-x-auto">
                            <table
                                class="w-full min-w-[900px] text-left"
                            >
                                <thead
                                    class="bg-[#F8FAF9] text-[10px] uppercase tracking-[0.12em] text-[#94A3B8]"
                                >
                                    <tr>
                                        <th
                                            class="px-5 py-3 font-bold"
                                        >
                                            Order
                                        </th>

                                        <th
                                            class="px-5 py-3 font-bold"
                                        >
                                            Seller
                                        </th>

                                        <th
                                            class="px-5 py-3 text-right font-bold"
                                        >
                                            Sale Amount
                                        </th>

                                        <th
                                            class="px-5 py-3 text-right font-bold"
                                        >
                                            Rate
                                        </th>

                                        <th
                                            class="px-5 py-3 text-right font-bold"
                                        >
                                            Commission
                                        </th>

                                        <th
                                            class="px-5 py-3 text-right font-bold"
                                        >
                                            Seller Amount
                                        </th>

                                        <th
                                            class="px-5 py-3 font-bold"
                                        >
                                            Status
                                        </th>

                                        <th
                                            class="px-5 py-3 font-bold"
                                        >
                                            Recorded
                                        </th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-[#E5E7EB]">
                                    <tr
                                        v-for="row in orderRows"
                                        :key="
                                            row.id ??
                                            `${row.order_id}-${row.seller}`
                                        "
                                        class="transition hover:bg-[#F8FAF9]"
                                    >
                                        <td
                                            class="whitespace-nowrap px-5 py-3.5 text-xs font-semibold text-[#1F2937]"
                                        >
                                            {{ row.order_number }}
                                        </td>

                                        <td
                                            class="px-5 py-3.5 text-xs text-[#64748B]"
                                        >
                                            {{ row.seller }}
                                        </td>

                                        <td
                                            class="px-5 py-3.5 text-right text-xs text-[#64748B]"
                                        >
                                            {{ formatCurrency(row.sale_amount) }}
                                        </td>

                                        <td
                                            class="px-5 py-3.5 text-right text-xs text-[#64748B]"
                                        >
                                            {{
                                                Number(
                                                    row.commission_rate ?? 0
                                                ).toFixed(2)
                                            }}%
                                        </td>

                                        <td
                                            class="px-5 py-3.5 text-right text-xs font-semibold text-[#087F8C]"
                                        >
                                            {{ formatCurrency(row.commission_amount) }}
                                        </td>

                                        <td
                                            class="px-5 py-3.5 text-right text-xs font-semibold text-emerald-700"
                                        >
                                            {{ formatCurrency(row.seller_amount) }}
                                        </td>

                                        <td class="px-5 py-3.5">
                                            <span
                                                class="inline-flex rounded-full px-2 py-1 text-[9px] font-semibold ring-1 ring-inset"
                                                :class="
                                                    statusClasses(row.status)
                                                "
                                            >
                                                {{ statusLabel(row.status) }}
                                            </span>
                                        </td>

                                        <td
                                            class="whitespace-nowrap px-5 py-3.5 text-[11px] text-[#64748B]"
                                        >
                                            {{ formatDate(row.recorded_at) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                </template>
            </template>
        </div>
    </AdminLayout>
</template>