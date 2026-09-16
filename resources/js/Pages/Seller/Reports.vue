<script setup>
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'

const props = defineProps({
    range: {
        type: Object,
        default: () => ({
            from: '',
            to: '',
        }),
    },

    summary: {
        type: Object,
        default: () => ({
            gross_revenue: 0,
            platform_fees: 0,
            net_earnings: 0,
            pending_payments: 0,
            orders: 0,
            units_sold: 0,
            revenue: 0,
            average_order_value: 0,
        }),
    },

    topProducts: {
        type: Array,
        default: () => [],
    },

    dailySales: {
        type: Array,
        default: () => [],
    },
})

const from = ref(props.range.from)
const to = ref(props.range.to)

const loading = ref(false)

const peso = (value) => {
    return Number(value || 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}

const formattedDate = (date) => {
    if (!date) {
        return ''
    }

    const parsed = new Date(`${date}T00:00:00`)

    if (Number.isNaN(parsed.getTime())) {
        return date
    }

    return parsed.toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    })
}

const generateReport = () => {
    if (!from.value || !to.value) {
        return
    }

    loading.value = true

    router.get(
        route('seller.reports'),
        {
            from: from.value,
            to: to.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                loading.value = false
            },
        }
    )
}

const resetRange = () => {
    const today = new Date()
    const thirtyDaysAgo = new Date()

    thirtyDaysAgo.setDate(today.getDate() - 29)

    const formatDate = (date) => {
        const year = date.getFullYear()
        const month = String(date.getMonth() + 1).padStart(2, '0')
        const day = String(date.getDate()).padStart(2, '0')

        return `${year}-${month}-${day}`
    }

    from.value = formatDate(thirtyDaysAgo)
    to.value = formatDate(today)

    generateReport()
}

const totalDailyRevenue = computed(() => {
    return props.dailySales.reduce(
        (total, item) => total + Number(item.revenue || 0),
        0
    )
})

const totalDailyUnits = computed(() => {
    return props.dailySales.reduce(
        (total, item) => total + Number(item.units_sold || 0),
        0
    )
})

const highestDailyRevenue = computed(() => {
    if (!props.dailySales.length) {
        return 0
    }

    return Math.max(
        ...props.dailySales.map(
            (item) => Number(item.revenue || 0)
        )
    )
})
</script>

<template>
    <Head title="Reports" />

    <SellerLayout>
        <div class="min-h-[calc(100vh-68px)] bg-[#F8FAF9]">
            <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">

                <!-- Page Header -->
                <div class="flex flex-col gap-4 border-b border-[#E5E7EB] pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-[#087F8C]">
                            Seller Center
                        </p>

                        <h1 class="mt-1 text-2xl font-bold tracking-tight text-[#1F2937] sm:text-3xl">
                            Sales Reports
                        </h1>

                        <p class="mt-1 text-sm text-[#64748B]">
                            Analyze your store's sales, revenue, and performance.
                        </p>
                    </div>

                    <div class="rounded-xl border border-[#E5E7EB] bg-white px-4 py-3 shadow-sm">
                        <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#94A3B8]">
                            Report Period
                        </p>

                        <p class="mt-1 text-sm font-semibold text-[#1F2937]">
                            {{ formattedDate(range.from) }}
                            <span class="px-1 text-[#94A3B8]">—</span>
                            {{ formattedDate(range.to) }}
                        </p>
                    </div>
                </div>

                <!-- Date Range -->
                <section class="mt-5 rounded-2xl border border-[#E5E7EB] bg-white shadow-sm">
                    <div class="border-b border-[#E5E7EB] px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]">
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.8"
                                        d="M8 7V3m8 4V3m-9 8h10M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"
                                    />
                                </svg>
                            </div>

                            <div>
                                <h2 class="text-base font-bold text-[#1F2937]">
                                    Generate Report
                                </h2>

                                <p class="mt-0.5 text-xs text-[#64748B]">
                                    Select a date range to view seller performance.
                                </p>
                            </div>
                        </div>
                    </div>

                    <form
                        class="grid grid-cols-1 gap-3 p-5 md:grid-cols-3"
                        @submit.prevent="generateReport"
                    >
                        <div>
                            <label
                                for="from"
                                class="mb-1.5 block text-[11px] font-semibold text-[#475569]"
                            >
                                From Date
                            </label>

                            <input
                                id="from"
                                v-model="from"
                                type="date"
                                class="w-full rounded-xl border border-[#E5E7EB] bg-white px-3 py-2.5 text-sm text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:ring-2 focus:ring-[#E8F7F6]"
                            />
                        </div>

                        <div>
                            <label
                                for="to"
                                class="mb-1.5 block text-[11px] font-semibold text-[#475569]"
                            >
                                To Date
                            </label>

                            <input
                                id="to"
                                v-model="to"
                                type="date"
                                class="w-full rounded-xl border border-[#E5E7EB] bg-white px-3 py-2.5 text-sm text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:ring-2 focus:ring-[#E8F7F6]"
                            />
                        </div>

                        <div class="flex items-end gap-2">
                            <button
                                type="submit"
                                :disabled="loading"
                                class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-[#087F8C] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#066D78] disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <svg
                                    v-if="loading"
                                    class="h-4 w-4 animate-spin"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 3v2m6.364.636l-1.414 1.414M21 12h-2m-.636 6.364l-1.414-1.414M12 21v-2m-6.364-.636l1.414-1.414M3 12h2m.636-6.364l1.414 1.414"
                                    />
                                </svg>

                                <svg
                                    v-else
                                    class="h-4 w-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.8"
                                        d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V7a2 2 0 012-2h10a2 2 0 012 2v10a2 2 0 01-2 2z"
                                    />
                                </svg>

                                {{ loading ? 'Generating...' : 'Generate Report' }}
                            </button>

                            <button
                                type="button"
                                :disabled="loading"
                                class="rounded-xl border border-[#E5E7EB] bg-white px-4 py-2.5 text-sm font-semibold text-[#475569] transition hover:border-[#087F8C] hover:bg-[#E8F7F6] hover:text-[#087F8C] disabled:cursor-not-allowed disabled:opacity-60"
                                @click="resetRange"
                            >
                                Reset
                            </button>
                        </div>
                    </form>
                </section>

                <!-- Financial Summary -->
                <section class="mt-5">
                    <div class="mb-3">
                        <h2 class="text-base font-bold text-[#1F2937]">
                            Financial Summary
                        </h2>

                        <p class="mt-0.5 text-xs text-[#64748B]">
                            Financial performance for the selected period.
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">

                        <!-- Gross Revenue -->
                        <div class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[11px] font-semibold text-[#64748B]">
                                        Gross Revenue
                                    </p>

                                    <p class="mt-1.5 text-xl font-bold tracking-tight text-[#1F2937] sm:text-2xl">
                                        ₱{{ peso(summary.gross_revenue) }}
                                    </p>
                                </div>

                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]">
                                    <svg
                                        class="h-5 w-5"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="1.8"
                                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m0-2c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                </div>
                            </div>

                            <p class="mt-2 text-[11px] text-[#94A3B8]">
                                Completed sales
                            </p>
                        </div>

                        <!-- Platform Fees -->
                        <div class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[11px] font-semibold text-[#64748B]">
                                        Platform Fees
                                    </p>

                                    <p class="mt-1.5 text-xl font-bold tracking-tight text-[#1F2937] sm:text-2xl">
                                        ₱{{ peso(summary.platform_fees) }}
                                    </p>
                                </div>

                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#FFF8E7] text-[#B77900]">
                                    <svg
                                        class="h-5 w-5"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="1.8"
                                            d="M9 14l6-6m2-4H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 002-2z"
                                        />
                                    </svg>
                                </div>
                            </div>

                            <p class="mt-2 text-[11px] text-[#94A3B8]">
                                Current rate: 5%
                            </p>
                        </div>

                        <!-- Net Earnings -->
                        <div class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[11px] font-semibold text-[#64748B]">
                                        Net Earnings
                                    </p>

                                    <p class="mt-1.5 text-xl font-bold tracking-tight text-[#16845A] sm:text-2xl">
                                        ₱{{ peso(summary.net_earnings) }}
                                    </p>
                                </div>

                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#ECFDF5] text-[#16845A]">
                                    <svg
                                        class="h-5 w-5"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="1.8"
                                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m0-2c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                </div>
                            </div>

                            <p class="mt-2 text-[11px] text-[#94A3B8]">
                                Revenue after platform fees
                            </p>
                        </div>

                        <!-- Pending -->
                        <div class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[11px] font-semibold text-[#64748B]">
                                        Pending Payments
                                    </p>

                                    <p class="mt-1.5 text-xl font-bold tracking-tight text-[#1F2937] sm:text-2xl">
                                        ₱{{ peso(summary.pending_payments) }}
                                    </p>
                                </div>

                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#FFF8E7] text-[#B77900]">
                                    <svg
                                        class="h-5 w-5"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="1.8"
                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                </div>
                            </div>

                            <p class="mt-2 text-[11px] text-[#94A3B8]">
                                Sales awaiting completion
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Sales Performance -->
                <section class="mt-5">
                    <div class="mb-3">
                        <h2 class="text-base font-bold text-[#1F2937]">
                            Sales Performance
                        </h2>

                        <p class="mt-0.5 text-xs text-[#64748B]">
                            Overview of completed sales during the selected period.
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">

                        <!-- Orders -->
                        <div class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm">
                            <div class="flex items-center gap-2">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]">
                                    <svg
                                        class="h-4 w-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="1.8"
                                            d="M9 5h6m-7 4h8m-8 4h5m5-8v12a2 2 0 01-2 2H8a2 2 0 01-2-2V5a2 2 0 012-2h8a2 2 0 012 2z"
                                        />
                                    </svg>
                                </div>

                                <p class="text-[11px] font-semibold text-[#64748B]">
                                    Orders
                                </p>
                            </div>

                            <p class="mt-3 text-xl font-bold tracking-tight text-[#1F2937] sm:text-2xl">
                                {{ summary.orders ?? 0 }}
                            </p>
                        </div>

                        <!-- Units -->
                        <div class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm">
                            <div class="flex items-center gap-2">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#FFF8E7] text-[#B77900]">
                                    <svg
                                        class="h-4 w-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="1.8"
                                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                                        />
                                    </svg>
                                </div>

                                <p class="text-[11px] font-semibold text-[#64748B]">
                                    Units Sold
                                </p>
                            </div>

                            <p class="mt-3 text-xl font-bold tracking-tight text-[#1F2937] sm:text-2xl">
                                {{ summary.units_sold ?? 0 }}
                            </p>
                        </div>

                        <!-- Revenue -->
                        <div class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm">
                            <div class="flex items-center gap-2">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#16A6A0]">
                                    <svg
                                        class="h-4 w-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="1.8"
                                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m0-2c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                </div>

                                <p class="text-[11px] font-semibold text-[#64748B]">
                                    Revenue
                                </p>
                            </div>

                            <p class="mt-3 text-xl font-bold tracking-tight text-[#1F2937] sm:text-2xl">
                                ₱{{ peso(summary.revenue) }}
                            </p>
                        </div>

                        <!-- AOV -->
                        <div class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm">
                            <div class="flex items-center gap-2">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#ECFDF5] text-[#16845A]">
                                    <svg
                                        class="h-4 w-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="1.8"
                                            d="M3 12l3-3 4 4 8-8m0 0h-5m5 0v5"
                                        />
                                    </svg>
                                </div>

                                <p class="text-[11px] font-semibold text-[#64748B]">
                                    Average Order Value
                                </p>
                            </div>

                            <p class="mt-3 text-xl font-bold tracking-tight text-[#1F2937] sm:text-2xl">
                                ₱{{ peso(summary.average_order_value) }}
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Top Products + Period Summary -->
                <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-3">

                    <!-- Top Products -->
                    <section class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm lg:col-span-2">
                        <div class="border-b border-[#E5E7EB] px-5 py-4">
                            <h2 class="text-base font-bold text-[#1F2937]">
                                Top-Selling Products
                            </h2>

                            <p class="mt-0.5 text-xs text-[#64748B]">
                                Best-performing products for this period.
                            </p>
                        </div>

                        <div
                            v-if="topProducts.length"
                            class="overflow-x-auto"
                        >
                            <table class="w-full min-w-[600px]">
                                <thead>
                                    <tr class="border-b border-[#E5E7EB] bg-[#F8FAF9] text-left">
                                        <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">
                                            #
                                        </th>

                                        <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">
                                            Product
                                        </th>

                                        <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">
                                            Units Sold
                                        </th>

                                        <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">
                                            Revenue
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <tr
                                        v-for="(product, index) in topProducts"
                                        :key="product.product_id"
                                        class="border-b border-[#F1F5F9] last:border-0"
                                    >
                                        <td class="px-5 py-3.5">
                                            <div
                                                class="flex h-7 w-7 items-center justify-center rounded-lg text-xs font-bold"
                                                :class="
                                                    index === 0
                                                        ? 'bg-[#FFF8E7] text-[#B77900]'
                                                        : 'bg-[#F8FAF9] text-[#94A3B8]'
                                                "
                                            >
                                                {{ index + 1 }}
                                            </div>
                                        </td>

                                        <td class="px-5 py-3.5">
                                            <p class="text-xs font-semibold text-[#1F2937]">
                                                {{ product.name }}
                                            </p>
                                        </td>

                                        <td class="px-5 py-3.5 text-right text-xs font-medium text-[#475569]">
                                            {{ product.units_sold }}
                                        </td>

                                        <td class="px-5 py-3.5 text-right text-xs font-bold text-[#1F2937]">
                                            ₱{{ peso(product.revenue) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div
                            v-else
                            class="px-5 py-12 text-center"
                        >
                            <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-[#F8FAF9] text-[#94A3B8]">
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.5"
                                        d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0H4m4-4h8"
                                    />
                                </svg>
                            </div>

                            <p class="mt-3 text-sm font-semibold text-[#64748B]">
                                No completed sales found.
                            </p>

                            <p class="mt-1 text-[11px] text-[#94A3B8]">
                                Try selecting another date range.
                            </p>
                        </div>
                    </section>

                    <!-- Period Summary -->
                    <section class="rounded-2xl border border-[#E5E7EB] bg-white shadow-sm">
                        <div class="border-b border-[#E5E7EB] px-5 py-4">
                            <h2 class="text-base font-bold text-[#1F2937]">
                                Period Summary
                            </h2>

                            <p class="mt-0.5 text-xs text-[#64748B]">
                                Quick overview of this report.
                            </p>
                        </div>

                        <div class="space-y-0 px-5">
                            <div class="border-b border-[#F1F5F9] py-4">
                                <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">
                                    Report From
                                </p>

                                <p class="mt-1 text-sm font-semibold text-[#1F2937]">
                                    {{ formattedDate(range.from) }}
                                </p>
                            </div>

                            <div class="border-b border-[#F1F5F9] py-4">
                                <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">
                                    Report To
                                </p>

                                <p class="mt-1 text-sm font-semibold text-[#1F2937]">
                                    {{ formattedDate(range.to) }}
                                </p>
                            </div>

                            <div class="border-b border-[#F1F5F9] py-4">
                                <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">
                                    Daily Revenue
                                </p>

                                <p class="mt-1 text-lg font-bold tracking-tight text-[#087F8C]">
                                    ₱{{ peso(totalDailyRevenue) }}
                                </p>
                            </div>

                            <div class="py-4">
                                <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">
                                    Daily Units
                                </p>

                                <p class="mt-1 text-lg font-bold tracking-tight text-[#1F2937]">
                                    {{ totalDailyUnits }}
                                </p>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Daily Sales -->
                <section class="mt-5 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm">
                    <div class="border-b border-[#E5E7EB] px-5 py-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h2 class="text-base font-bold text-[#1F2937]">
                                    Daily Sales
                                </h2>

                                <p class="mt-0.5 text-xs text-[#64748B]">
                                    Daily completed sales during the selected period.
                                </p>
                            </div>

                            <div
                                v-if="dailySales.length"
                                class="hidden rounded-full bg-[#E8F7F6] px-3 py-1.5 text-[10px] font-bold text-[#087F8C] sm:block"
                            >
                                {{ dailySales.length }} days
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="dailySales.length"
                        class="overflow-x-auto"
                    >
                        <table class="w-full min-w-[650px]">
                            <thead>
                                <tr class="border-b border-[#E5E7EB] bg-[#F8FAF9] text-left">
                                    <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">
                                        Date
                                    </th>

                                    <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">
                                        Units Sold
                                    </th>

                                    <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">
                                        Revenue
                                    </th>

                                    <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">
                                        Performance
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="day in dailySales"
                                    :key="day.day"
                                    class="border-b border-[#F1F5F9] last:border-0"
                                >
                                    <td class="px-5 py-3.5 text-xs font-semibold text-[#1F2937]">
                                        {{ formattedDate(day.day) }}
                                    </td>

                                    <td class="px-5 py-3.5 text-right text-xs font-medium text-[#475569]">
                                        {{ day.units_sold }}
                                    </td>

                                    <td class="px-5 py-3.5 text-right text-xs font-bold text-[#1F2937]">
                                        ₱{{ peso(day.revenue) }}
                                    </td>

                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center justify-end gap-3">
                                            <div class="h-2 w-24 overflow-hidden rounded-full bg-[#E8F7F6] sm:w-32">
                                                <div
                                                    class="h-full rounded-full bg-[#16A6A0] transition-all"
                                                    :style="{
                                                        width: highestDailyRevenue > 0
                                                            ? `${Math.max((Number(day.revenue) / highestDailyRevenue) * 100, 4)}%`
                                                            : '0%'
                                                    }"
                                                ></div>
                                            </div>

                                            <span class="hidden w-10 text-right text-[10px] font-semibold text-[#94A3B8] sm:block">
                                                {{
                                                    highestDailyRevenue > 0
                                                        ? `${Math.round((Number(day.revenue) / highestDailyRevenue) * 100)}%`
                                                        : '0%'
                                                }}
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-else
                        class="px-5 py-12 text-center"
                    >
                        <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-[#F8FAF9] text-[#94A3B8]">
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.5"
                                    d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V7a2 2 0 012-2h10a2 2 0 012 2v10a2 2 0 01-2 2z"
                                />
                            </svg>
                        </div>

                        <p class="mt-3 text-sm font-semibold text-[#64748B]">
                            No sales data available.
                        </p>

                        <p class="mt-1 text-[11px] text-[#94A3B8]">
                            Completed sales will appear here.
                        </p>
                    </div>
                </section>

                <!-- Bottom spacing -->
                <div class="h-5"></div>
            </div>
        </div>
    </SellerLayout>
</template>