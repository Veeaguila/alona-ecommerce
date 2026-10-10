<script setup>
import { computed, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'

const props = defineProps({
    stats: {
        type: Object,
        required: true,
    },

    orderMetrics: {
        type: Object,
        default: () => ({
            pending: 0,
            to_ship: 0,
            in_transit: 0,
            delivered: 0,
            cancelled: 0,
            returned: 0,
        }),
    },

    stockMetrics: {
        type: Object,
        default: () => ({
            out_of_stock: 0,
            low_stock: 0,
        }),
    },

    salesChart: {
        type: Array,
        default: () => [],
    },

    recentOrders: {
        type: Array,
        default: () => [],
    },

    topProducts: {
        type: Array,
        default: () => [],
    },

    lowStockProducts: {
        type: Array,
        default: () => [],
    },

    recentReviews: {
        type: Array,
        default: () => [],
    },

    reviewsSummary: {
        type: Object,
        default: () => ({
            average_rating: 0,
            total_count: 0,
        }),
    },

    recentNotifications: {
        type: Array,
        default: () => [],
    },

    unreadNotificationsCount: {
        type: Number,
        default: 0,
    },

    systemAnnouncements: {
        type: Array,
        default: () => [],
    },
})

/*
|--------------------------------------------------------------------------
| LOCAL STATE
|--------------------------------------------------------------------------
*/

const notificationTab = ref('alerts') // 'alerts' | 'announcements'

/*
|--------------------------------------------------------------------------
| FORMATTERS & HELPERS
|--------------------------------------------------------------------------
*/

const peso = (value) => {
    return `₱${Number(value ?? 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`
}

const formatDate = (value) => {
    if (!value) return '—'
    const date = new Date(value)
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    })
}

const timeAgo = (dateStr) => {
    if (!dateStr) return ''
    const date = new Date(dateStr)
    const now = new Date()
    const diffSec = Math.floor((now - date) / 1000)

    if (diffSec < 60) return 'Just now'
    if (diffSec < 3600) return `${Math.floor(diffSec / 60)}m ago`
    if (diffSec < 86400) return `${Math.floor(diffSec / 3600)}h ago`
    if (diffSec < 604800) return `${Math.floor(diffSec / 86400)}d ago`
    return formatDate(dateStr)
}

const currentDateFormatted = computed(() => {
    return new Date().toLocaleDateString('en-US', {
        weekday: 'long',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    })
})

// SELLER-05: Core Financial & Store Stat Cards
const financialStats = computed(() => [
    {
        title: 'Gross Sales',
        value: peso(props.stats.total_sales),
        subtitle: props.stats.pending_sales > 0 ? `+${peso(props.stats.pending_sales)} pending fulfillment` : 'Delivered & completed orders',
        badge: 'Revenue',
        badgeTone: 'teal',
        icon: 'sales',
    },
    {
        title: 'Estimated Net Profit',
        value: peso(props.stats.estimated_net_profit),
        subtitle: `Est. margin: ${props.stats.estimated_net_margin ?? 95}% after ${props.stats.platform_fee_rate ?? 5}% fee`,
        badge: `${props.stats.estimated_net_margin ?? 95}% Margin`,
        badgeTone: 'emerald',
        icon: 'profit',
    },
    {
        title: 'Total Orders',
        value: Number(props.stats.total_orders ?? 0).toLocaleString(),
        subtitle: `${props.orderMetrics?.pending ?? 0} orders waiting to be packed`,
        badge: 'Orders',
        badgeTone: 'blue',
        icon: 'orders',
    },
    {
        title: 'Store Products',
        value: Number(props.stats.total_products ?? 0).toLocaleString(),
        subtitle: props.stockMetrics?.out_of_stock > 0
            ? `${props.stockMetrics.out_of_stock} out of stock · ${props.stockMetrics.low_stock} low`
            : `${props.stockMetrics?.low_stock ?? 0} low stock items`,
        badge: props.stockMetrics?.out_of_stock > 0 ? 'Stock Alert' : 'Catalog',
        badgeTone: props.stockMetrics?.out_of_stock > 0 ? 'red' : 'gold',
        icon: 'products',
    },
])

// SELLER-01: Order Fulfillment Pipeline Cards
const pipelineCards = computed(() => [
    {
        key: 'pending',
        title: 'To Pack',
        stage: 'Pending',
        count: props.orderMetrics?.pending ?? 0,
        statusFilter: 'pending',
        accent: 'amber',
        dotColor: 'bg-amber-500',
        badgeClass: 'bg-amber-50 text-amber-700 border-amber-200',
        helper: 'Awaiting fulfillment',
    },
    {
        key: 'to_ship',
        title: 'To Ship',
        stage: 'Packing / Ready',
        count: props.orderMetrics?.to_ship ?? 0,
        statusFilter: 'to_ship',
        accent: 'blue',
        dotColor: 'bg-blue-500',
        badgeClass: 'bg-blue-50 text-blue-700 border-blue-200',
        helper: 'Packed & ready for pickup',
    },
    {
        key: 'in_transit',
        title: 'In Transit',
        stage: 'With Carrier',
        count: props.orderMetrics?.in_transit ?? 0,
        statusFilter: 'in_transit',
        accent: 'indigo',
        dotColor: 'bg-indigo-500',
        badgeClass: 'bg-indigo-50 text-indigo-700 border-indigo-200',
        helper: 'En route to customer',
    },
    {
        key: 'delivered',
        title: 'Delivered',
        stage: 'Completed',
        count: props.orderMetrics?.delivered ?? 0,
        statusFilter: 'delivered',
        accent: 'emerald',
        dotColor: 'bg-emerald-500',
        badgeClass: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        helper: 'Order complete',
    },
    {
        key: 'cancelled',
        title: 'Cancelled',
        stage: 'Voided',
        count: props.orderMetrics?.cancelled ?? 0,
        statusFilter: 'cancelled',
        accent: 'slate',
        dotColor: 'bg-slate-400',
        badgeClass: 'bg-slate-50 text-slate-700 border-slate-200',
        helper: 'Cancelled orders',
    },
    {
        key: 'returned',
        title: 'Returns / Refunds',
        stage: 'Disputes',
        count: props.orderMetrics?.returned ?? 0,
        statusFilter: 'returned',
        accent: 'rose',
        dotColor: 'bg-rose-500',
        badgeClass: 'bg-rose-50 text-rose-700 border-rose-200',
        helper: 'Customer return requests',
    },
])

const chartMax = computed(() => {
    return Math.max(
        1,
        ...props.salesChart.map((day) => Number(day.total ?? 0))
    )
})

const chartAverage = computed(() => {
    if (!props.salesChart.length) return 0
    const total = props.salesChart.reduce(
        (sum, day) => sum + Number(day.total ?? 0),
        0
    )
    return total / props.salesChart.length
})

const chartTotal7Days = computed(() => {
    if (!props.salesChart.length) return 0
    return props.salesChart.reduce(
        (sum, day) => sum + Number(day.total ?? 0),
        0
    )
})

const statusLabel = (item) => {
    const map = {
        pending: 'To Pack',
        processing: 'Processing',
        packed: 'Packed',
        ready_for_pickup: 'Ready for Pickup',
        shipped: 'Shipped',
        out_for_delivery: 'Out for Delivery',
        delivered: 'Delivered',
        completed: 'Completed',
        cancelled: 'Cancelled',
    }
    return map[item.status] ?? item.status
}

const statusClass = (status) => {
    const map = {
        pending: 'bg-amber-50 text-amber-700 border-amber-200',
        processing: 'bg-[#E8F7F6] text-[#087F8C] border-[#BCE8E5]',
        packed: 'bg-[#E8F7F6] text-[#087F8C] border-[#BCE8E5]',
        ready_for_pickup: 'bg-sky-50 text-sky-700 border-sky-200',
        shipped: 'bg-blue-50 text-blue-700 border-blue-200',
        out_for_delivery: 'bg-indigo-50 text-indigo-700 border-indigo-200',
        delivered: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        completed: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        cancelled: 'bg-red-50 text-red-700 border-red-200',
    }
    return map[status] ?? 'bg-slate-100 text-slate-700 border-slate-200'
}
</script>

<template>
    <Head title="Seller Dashboard" />

    <SellerLayout>
        <div class="mx-auto w-full max-w-[1536px] space-y-6">

            <!-- ========================================================
                 PAGE HEADER (Clean & Modern)
            ========================================================= -->

            <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 sm:p-6 shadow-xs">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-[#E8F7F6] px-3 py-1 text-xs font-bold text-[#087F8C]">
                                <span class="h-2 w-2 rounded-full bg-[#16A6A0] animate-pulse"></span>
                                Seller Center
                            </span>
                            <span class="text-xs text-slate-400">·</span>
                            <span class="text-xs font-medium text-slate-500">
                                {{ currentDateFormatted }}
                            </span>
                        </div>

                        <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-[#1F2937] sm:text-3xl">
                            Store Dashboard
                        </h1>

                        <p class="mt-1 max-w-2xl text-xs sm:text-sm text-[#64748B]">
                            Monitor your store revenue, fulfill pending customer orders, and manage inventory and reviews in real time.
                        </p>
                    </div>

                    <!-- Header Quick Navigation Buttons -->
                    <div class="flex flex-wrap items-center gap-2.5">
                        <Link
                            :href="route('seller.products.create')"
                            class="inline-flex items-center gap-2 rounded-xl bg-[#087F8C] px-4 py-2.5 text-xs font-bold text-white shadow-xs transition hover:bg-[#066D78] active:translate-y-0.5"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 5v14M5 12h14" />
                            </svg>
                            <span>Add Product</span>
                        </Link>

                        <Link
                            :href="route('seller.orders')"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-bold text-slate-700 shadow-2xs transition hover:border-[#16A6A0] hover:text-[#087F8C]"
                        >
                            <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M6 3h12l2 4v13H4V7l2-4Z" />
                                <path d="M4 7h16" />
                            </svg>
                            <span>Orders</span>
                            <span
                                v-if="orderMetrics.pending > 0"
                                class="rounded-full bg-amber-100 px-1.5 py-0.2 text-[10px] font-bold text-amber-800"
                            >
                                {{ orderMetrics.pending }}
                            </span>
                        </Link>

                        <Link
                            :href="route('seller.stock-monitoring')"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-bold text-slate-700 shadow-2xs transition hover:border-[#16A6A0] hover:text-[#087F8C]"
                        >
                            <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            <span>Stock Monitor</span>
                            <span
                                v-if="stockMetrics.out_of_stock > 0"
                                class="rounded-full bg-red-100 px-1.5 py-0.2 text-[10px] font-bold text-red-600"
                            >
                                {{ stockMetrics.out_of_stock }}
                            </span>
                        </Link>

                        <Link
                            :href="route('seller.store')"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-bold text-slate-700 shadow-2xs transition hover:border-[#16A6A0] hover:text-[#087F8C]"
                        >
                            <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M3 10l2-6h14l2 6" />
                                <path d="M4 10v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-9" />
                                <path d="M3 10h18" />
                            </svg>
                            <span>Storefront</span>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- ========================================================
                 SELLER-05: FINANCIAL & STORE PERFORMANCE OVERVIEW
            ========================================================= -->

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="stat in financialStats"
                    :key="stat.title"
                    class="group relative overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-xs transition duration-150 hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="text-xs font-bold uppercase tracking-wider text-slate-500">
                                    {{ stat.title }}
                                </p>
                            </div>

                            <p class="mt-2.5 truncate text-2xl font-black tracking-tight text-[#1F2937] sm:text-3xl">
                                {{ stat.value }}
                            </p>

                            <p class="mt-1.5 truncate text-[11px] font-medium text-slate-500">
                                {{ stat.subtitle }}
                            </p>
                        </div>

                        <!-- Icon Container with subtle pastel color -->
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
                            :class="
                                stat.icon === 'sales' ? 'bg-[#E8F7F6] text-[#087F8C]' :
                                stat.icon === 'profit' ? 'bg-emerald-50 text-emerald-600' :
                                stat.icon === 'orders' ? 'bg-blue-50 text-blue-600' :
                                'bg-[#FFF8E7] text-[#B7791F]'
                            "
                        >
                            <!-- Sales -->
                            <svg v-if="stat.icon === 'sales'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                            </svg>

                            <!-- Profit -->
                            <svg v-else-if="stat.icon === 'profit'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M4 19V5" />
                                <path d="M4 19h17" />
                                <path d="m7 15 3-4 3 2 5-7" />
                            </svg>

                            <!-- Orders -->
                            <svg v-else-if="stat.icon === 'orders'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M6 3h12l2 4v13H4V7l2-4Z" />
                                <path d="M4 7h16" />
                                <path d="M9 11h6M9 15h4" />
                            </svg>

                            <!-- Products -->
                            <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z" />
                                <path d="m4 7.5 8 4.5 8-4.5" />
                                <path d="M12 12v9" />
                            </svg>
                        </div>
                    </div>

                    <!-- Bottom Tag Bar -->
                    <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3 text-[10px]">
                        <span
                            class="rounded-md px-2 py-0.5 font-bold"
                            :class="
                                stat.badgeTone === 'emerald' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                                stat.badgeTone === 'teal' ? 'bg-[#E8F7F6] text-[#087F8C] border border-[#BCE8E5]' :
                                stat.badgeTone === 'blue' ? 'bg-blue-50 text-blue-700 border border-blue-200' :
                                stat.badgeTone === 'red' ? 'bg-red-50 text-red-700 border border-red-200' :
                                'bg-amber-50 text-amber-700 border border-amber-200'
                            "
                        >
                            {{ stat.badge }}
                        </span>

                        <span class="text-slate-400 font-medium">Store metric</span>
                    </div>
                </div>
            </div>

            <!-- ========================================================
                 SELLER-01: ORDER FULFILLMENT PIPELINE (Clean White Cards)
            ========================================================= -->

            <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 sm:p-6 shadow-xs">
                <div class="flex flex-col gap-2 border-b border-slate-100 pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h2 class="text-base font-bold text-[#1F2937]">
                                Order Fulfillment Pipeline
                            </h2>
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold text-slate-600">
                                {{ Number(stats.total_orders ?? 0).toLocaleString() }} Total Orders
                            </span>
                        </div>
                        <p class="mt-0.5 text-xs text-[#64748B]">
                            Click any stage to filter and process orders directly in Order Management.
                        </p>
                    </div>

                    <Link
                        :href="route('seller.orders')"
                        class="inline-flex items-center gap-1.5 text-xs font-bold text-[#087F8C] transition hover:text-[#066D78]"
                    >
                        <span>Open Order Center</span>
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M13 6l6 6-6 6" />
                        </svg>
                    </Link>
                </div>

                <!-- 6 Unified Pipeline Cards -->
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    <Link
                        v-for="card in pipelineCards"
                        :key="card.key"
                        :href="route('seller.orders', { status: card.statusFilter })"
                        class="group relative flex flex-col justify-between rounded-xl border border-slate-200/90 bg-white p-3.5 transition-all duration-150 hover:-translate-y-0.5 hover:border-[#087F8C] hover:shadow-xs"
                    >
                        <!-- Top Row: Stage & Pill -->
                        <div>
                            <div class="flex items-center justify-between gap-1.5">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <span class="h-2 w-2 shrink-0 rounded-full" :class="card.dotColor"></span>
                                    <span class="truncate text-xs font-bold text-slate-800 group-hover:text-[#087F8C]">
                                        {{ card.title }}
                                    </span>
                                </div>
                            </div>

                            <!-- Big Count -->
                            <p class="mt-2 text-2xl font-black tracking-tight text-slate-900 group-hover:text-[#087F8C]">
                                {{ Number(card.count).toLocaleString() }}
                            </p>
                        </div>

                        <!-- Bottom Helper -->
                        <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-2 text-[10px] text-slate-500">
                            <span class="truncate">{{ card.stage }}</span>
                            <svg class="h-3.5 w-3.5 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-[#087F8C]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="m9 18 6-6-6-6" />
                            </svg>
                        </div>
                    </Link>
                </div>
            </div>

            <!-- ========================================================
                 MAIN GRID (CHARTS, RECENT ORDERS & RIGHT-HAND WIDGETS)
            ========================================================= -->

            <div class="grid gap-6 xl:grid-cols-3">

                <!-- ====================================================
                     LEFT COLUMN (2 / 3 width on desktop)
                ===================================================== -->

                <div class="space-y-6 xl:col-span-2">

                    <!-- SALES PERFORMANCE 7-DAY CHART -->
                    <section class="rounded-2xl border border-[#E5E7EB] bg-white p-5 sm:p-6 shadow-xs">
                        <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="text-base font-bold text-[#1F2937]">
                                        7-Day Sales Performance
                                    </h2>
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">
                                        {{ peso(chartTotal7Days) }} past 7 days
                                    </span>
                                </div>
                                <p class="mt-0.5 text-xs text-[#64748B]">
                                    Daily revenue from completed and delivered customer purchases.
                                </p>
                            </div>

                            <div class="flex items-center gap-2 rounded-xl bg-slate-50 border border-slate-200/80 px-3 py-1.5">
                                <span class="h-2 w-2 rounded-full bg-[#16A6A0]"></span>
                                <span class="text-[11px] font-semibold text-slate-500">Daily Average:</span>
                                <span class="text-xs font-bold text-[#087F8C]">{{ peso(chartAverage) }}</span>
                            </div>
                        </div>

                        <div class="pt-6">
                            <div v-if="salesChart.length" class="relative">
                                <!-- Chart Background Gridlines -->
                                <div class="pointer-events-none absolute inset-x-0 top-0 h-52">
                                    <div class="absolute inset-x-0 top-0 border-t border-dashed border-slate-100"></div>
                                    <div class="absolute inset-x-0 top-1/2 border-t border-dashed border-slate-100"></div>
                                    <div class="absolute inset-x-0 bottom-0 border-t border-dashed border-slate-100"></div>
                                </div>

                                <!-- Bars -->
                                <div class="relative flex h-52 items-end gap-2 sm:gap-4">
                                    <div
                                        v-for="day in salesChart"
                                        :key="day.label"
                                        class="group flex h-full flex-1 flex-col items-center justify-end"
                                    >
                                        <div class="relative flex h-full w-full items-end justify-center">
                                            <!-- Floating Tooltip -->
                                            <div class="pointer-events-none absolute bottom-full left-1/2 mb-2 -translate-x-1/2 whitespace-nowrap rounded-lg bg-[#1F2937] px-2.5 py-1.5 text-[11px] font-bold text-white opacity-0 shadow-md transition group-hover:opacity-100 z-10">
                                                {{ peso(day.total) }}
                                            </div>

                                            <!-- Bar Pill -->
                                            <div
                                                class="w-full max-w-10 rounded-t-lg bg-[#16A6A0] transition-all duration-200 group-hover:bg-[#087F8C]"
                                                :style="{
                                                    height: `${Math.max(6, (Number(day.total ?? 0) / chartMax) * 100)}%`,
                                                }"
                                            ></div>
                                        </div>

                                        <span class="mt-2.5 text-[11px] font-medium text-slate-500">
                                            {{ day.label }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="flex h-52 flex-col items-center justify-center rounded-xl bg-slate-50 text-center">
                                <p class="text-sm font-semibold text-slate-600">No sales recorded this week</p>
                                <p class="mt-1 text-xs text-slate-400">Delivered orders will automatically chart here.</p>
                            </div>
                        </div>
                    </section>

                    <!-- RECENT CUSTOMER ORDERS -->
                    <section class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-xs">
                        <div class="flex items-center justify-between border-b border-[#E5E7EB] px-5 py-4">
                            <div>
                                <h2 class="text-base font-bold text-[#1F2937]">Recent Customer Orders</h2>
                                <p class="mt-0.5 text-xs text-[#64748B]">Newest customer purchases awaiting fulfillment or recent dispatch.</p>
                            </div>

                            <Link
                                :href="route('seller.orders')"
                                class="inline-flex items-center gap-1 text-xs font-bold text-[#087F8C] hover:text-[#066D78]"
                            >
                                <span>All Orders</span>
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M5 12h14M13 6l6 6-6 6" />
                                </svg>
                            </Link>
                        </div>

                        <div v-if="recentOrders.length" class="divide-y divide-slate-100">
                            <div
                                v-for="order in recentOrders"
                                :key="order.id"
                                class="flex flex-col gap-3 px-5 py-3.5 transition hover:bg-slate-50/70 sm:flex-row sm:items-center"
                            >
                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <path d="M6 3h12l2 4v13H4V7l2-4Z" />
                                            <path d="M4 7h16" />
                                        </svg>
                                    </div>

                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-[#087F8C]">
                                            #{{ order.order_number }}
                                        </p>
                                        <p class="mt-0.5 truncate text-[11px] text-slate-400">
                                            {{ order.user?.name || 'Customer' }} · {{ timeAgo(order.created_at) }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex min-w-0 flex-1 flex-wrap gap-1.5">
                                    <span
                                        v-for="item in order.items"
                                        :key="item.id"
                                        class="max-w-full truncate rounded-lg border border-slate-200/80 bg-slate-50 px-2.5 py-1 text-[11px] font-medium text-slate-700"
                                    >
                                        {{ item.product?.name }} ×{{ item.quantity }}
                                    </span>
                                </div>

                                <div class="flex items-center justify-between gap-3 sm:justify-end">
                                    <span
                                        class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-bold"
                                        :class="statusClass(order.status)"
                                    >
                                        {{ statusLabel(order) }}
                                    </span>

                                    <Link
                                        :href="route('seller.orders')"
                                        class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                                        title="View order in Order Center"
                                    >
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="m9 18 6-6-6-6" />
                                        </svg>
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <div v-else class="flex flex-col items-center justify-center px-5 py-12 text-center">
                            <p class="text-sm font-semibold text-slate-600">No orders received yet</p>
                            <p class="mt-1 text-xs text-slate-400">Customer orders will appear here automatically.</p>
                        </div>
                    </section>

                    <!-- ====================================================
                         SELLER-02: RECENT CUSTOMER REVIEWS WIDGET
                    ===================================================== -->

                    <section class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-xs">
                        <div class="flex flex-col gap-2 border-b border-[#E5E7EB] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="flex items-center gap-2.5">
                                    <h2 class="text-base font-bold text-[#1F2937]">Recent Customer Reviews</h2>
                                    <div class="flex items-center gap-1 rounded-full bg-amber-50 border border-amber-200 px-2.5 py-0.5 text-xs font-bold text-amber-800">
                                        <svg class="h-3.5 w-3.5 fill-amber-400 text-amber-400" viewBox="0 0 24 24">
                                            <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z" />
                                        </svg>
                                        <span>{{ reviewsSummary.average_rating > 0 ? reviewsSummary.average_rating : '0.0' }}</span>
                                        <span class="text-[10px] text-amber-700 font-medium">({{ reviewsSummary.total_count }} reviews)</span>
                                    </div>
                                </div>
                                <p class="mt-0.5 text-xs text-[#64748B]">Customer feedback and star ratings on your delivered items.</p>
                            </div>

                            <Link
                                :href="route('seller.reviews')"
                                class="inline-flex items-center gap-1 text-xs font-bold text-[#087F8C] hover:text-[#066D78]"
                            >
                                <span>Manage All Reviews</span>
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M5 12h14M13 6l6 6-6 6" />
                                </svg>
                            </Link>
                        </div>

                        <div v-if="recentReviews.length" class="divide-y divide-slate-100">
                            <div
                                v-for="review in recentReviews"
                                :key="review.id"
                                class="p-5 transition hover:bg-slate-50/60"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 font-bold text-slate-700 text-xs uppercase">
                                            {{ review.user?.name ? review.user.name.charAt(0) : 'C' }}
                                        </div>

                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <p class="truncate text-sm font-semibold text-slate-800">
                                                    {{ review.user?.name || 'Customer' }}
                                                </p>
                                                <!-- Gold Star Icons -->
                                                <div class="flex items-center gap-0.5 text-amber-400">
                                                    <svg
                                                        v-for="i in 5"
                                                        :key="i"
                                                        class="h-3.5 w-3.5"
                                                        :class="i <= review.rating ? 'fill-current' : 'text-slate-200'"
                                                        viewBox="0 0 24 24"
                                                    >
                                                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z" />
                                                    </svg>
                                                </div>
                                            </div>

                                            <p class="truncate text-[11px] text-slate-400">
                                                For item: <span class="font-medium text-slate-600">{{ review.product?.name }}</span> · {{ timeAgo(review.created_at) }}
                                            </p>
                                        </div>
                                    </div>

                                    <span
                                        v-if="review.seller_reply"
                                        class="rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700"
                                    >
                                        Replied
                                    </span>
                                    <span
                                        v-else
                                        class="rounded-full bg-amber-50 border border-amber-200 px-2.5 py-0.5 text-[10px] font-bold text-amber-700"
                                    >
                                        Needs Reply
                                    </span>
                                </div>

                                <p class="mt-2.5 text-xs leading-relaxed text-slate-600">
                                    {{ review.comment || 'No written comment left with this rating.' }}
                                </p>

                                <!-- Reply Snippet -->
                                <div v-if="review.seller_reply" class="mt-2.5 rounded-xl bg-slate-50 p-2.5 text-[11px] text-slate-600 border border-slate-200/80">
                                    <span class="font-bold text-[#087F8C]">Store response: </span>
                                    <span>{{ review.seller_reply }}</span>
                                </div>
                            </div>
                        </div>

                        <div v-else class="flex flex-col items-center justify-center px-5 py-12 text-center">
                            <svg class="h-10 w-10 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z" />
                            </svg>
                            <p class="mt-3 text-sm font-semibold text-slate-600">No customer reviews yet</p>
                            <p class="mt-1 text-xs text-slate-400">Delivered product ratings and feedback will display here.</p>
                        </div>
                    </section>
                </div>

                <!-- ====================================================
                     RIGHT COLUMN (1 / 3 width on desktop)
                ===================================================== -->

                <div class="space-y-6">

                    <!-- ================================================
                         SELLER-04: INVENTORY & STOCK HEALTH ALERTS
                    ================================================= -->

                    <section class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-xs">
                        <div class="flex items-center justify-between border-b border-[#E5E7EB] px-5 py-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h2 class="text-base font-bold text-[#1F2937]">Stock Health</h2>
                                    <!-- Out of stock & low badges -->
                                    <span
                                        v-if="stockMetrics.out_of_stock > 0"
                                        class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-extrabold text-red-700 animate-pulse"
                                    >
                                        {{ stockMetrics.out_of_stock }} Out of Stock
                                    </span>
                                    <span
                                        v-if="stockMetrics.low_stock > 0"
                                        class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-extrabold text-amber-800"
                                    >
                                        {{ stockMetrics.low_stock }} Low
                                    </span>
                                </div>
                                <p class="mt-0.5 text-xs text-[#64748B]">Items requiring replenishment or restocking.</p>
                            </div>

                            <Link
                                :href="route('seller.stock-monitoring')"
                                class="text-xs font-bold text-[#087F8C] hover:text-[#066D78]"
                            >
                                Stock Monitor
                            </Link>
                        </div>

                        <!-- Low Stock & Out of Stock Product List -->
                        <div v-if="lowStockProducts.length" class="divide-y divide-slate-100">
                            <div
                                v-for="product in lowStockProducts"
                                :key="product.id"
                                class="flex items-center gap-3 px-5 py-3.5 transition hover:bg-slate-50"
                            >
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                                    :class="product.stock === 0 ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-600'"
                                >
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M10.3 3.9 2.2 18a2 2 0 0 0 1.7 3h16.2a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z" />
                                        <path d="M12 9v4M12 17h.01" />
                                    </svg>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-slate-800">
                                        {{ product.name }}
                                    </p>

                                    <div class="mt-0.5 flex items-center gap-2">
                                        <span
                                            class="rounded px-1.5 py-0.2 text-[10px] font-bold"
                                            :class="product.stock === 0 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800'"
                                        >
                                            {{ product.stock === 0 ? '0 in stock' : `${product.stock} units left` }}
                                        </span>
                                    </div>
                                </div>

                                <Link
                                    :href="route('seller.products.edit', product.id)"
                                    class="shrink-0 rounded-lg bg-[#087F8C] px-3 py-1.5 text-[10px] font-bold text-white shadow-2xs transition hover:bg-[#066D78]"
                                >
                                    Restock
                                </Link>
                            </div>
                        </div>

                        <div v-else class="flex flex-col items-center justify-center px-5 py-10 text-center">
                            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-[#22A06B]">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 12l2 2 4-4" />
                                    <circle cx="12" cy="12" r="9" />
                                </svg>
                            </div>
                            <p class="mt-2.5 text-sm font-semibold text-slate-700">Healthy Stock Levels</p>
                            <p class="mt-0.5 text-xs text-slate-400">All products have sufficient inventory on hand.</p>
                        </div>
                    </section>

                    <!-- ================================================
                         SELLER-03: NOTIFICATIONS & ANNOUNCEMENTS WIDGET
                    ================================================= -->

                    <section class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-xs">
                        <div class="border-b border-[#E5E7EB] px-5 py-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <h2 class="text-base font-bold text-[#1F2937]">Store Feed & Alerts</h2>
                                    <span
                                        v-if="unreadNotificationsCount > 0"
                                        class="rounded-full bg-[#087F8C] px-2 py-0.5 text-[10px] font-bold text-white"
                                    >
                                        {{ unreadNotificationsCount }} new
                                    </span>
                                </div>

                                <Link
                                    :href="route('seller.notifications')"
                                    class="text-xs font-bold text-[#087F8C] hover:text-[#066D78]"
                                >
                                    View All
                                </Link>
                            </div>

                            <!-- Toggle Tabs: Alerts vs Announcements -->
                            <div class="mt-3 flex rounded-xl bg-slate-100 p-1 text-xs font-semibold">
                                <button
                                    type="button"
                                    @click="notificationTab = 'alerts'"
                                    class="flex-1 rounded-lg py-1.5 text-center transition"
                                    :class="notificationTab === 'alerts' ? 'bg-white text-[#087F8C] shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900'"
                                >
                                    Store Alerts
                                </button>
                                <button
                                    type="button"
                                    @click="notificationTab = 'announcements'"
                                    class="flex-1 rounded-lg py-1.5 text-center transition"
                                    :class="notificationTab === 'announcements' ? 'bg-white text-[#087F8C] shadow-2xs font-bold' : 'text-slate-600 hover:text-slate-900'"
                                >
                                    Announcements (Admin)
                                </button>
                            </div>
                        </div>

                        <!-- Alerts Tab -->
                        <div v-if="notificationTab === 'alerts'">
                            <div v-if="recentNotifications.length" class="divide-y divide-slate-100">
                                <div
                                    v-for="item in recentNotifications"
                                    :key="item.id"
                                    class="flex items-start gap-3 p-4 transition hover:bg-slate-50"
                                    :class="!item.read_at ? 'bg-[#F4FBFA]' : ''"
                                >
                                    <div
                                        class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                                        :class="!item.read_at ? 'bg-[#087F8C] text-white' : 'bg-slate-100 text-slate-500'"
                                    >
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                                            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                                        </svg>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-bold text-slate-800">
                                            {{ item.title }}
                                        </p>
                                        <p class="mt-0.5 text-[11px] leading-relaxed text-slate-500 line-clamp-2">
                                            {{ item.message }}
                                        </p>
                                        <p class="mt-1 text-[10px] text-slate-400">
                                            {{ timeAgo(item.created_at) }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="p-8 text-center text-xs text-slate-400">
                                No store notifications yet.
                            </div>
                        </div>

                        <!-- Announcements Tab (Admin Connected) -->
                        <div v-else>
                            <div v-if="systemAnnouncements.length" class="divide-y divide-slate-100">
                                <div
                                    v-for="notice in systemAnnouncements"
                                    :key="notice.id"
                                    class="p-4 transition hover:bg-slate-50"
                                >
                                    <div class="flex items-center gap-2">
                                        <span class="rounded bg-sky-100 px-1.5 py-0.2 text-[9px] font-bold text-sky-800 uppercase tracking-wider">
                                            Platform Update
                                        </span>
                                        <span class="text-[10px] text-slate-400">
                                            {{ formatDate(notice.published_at) }}
                                        </span>
                                    </div>

                                    <p class="mt-1.5 text-xs font-bold text-slate-800">
                                        {{ notice.title }}
                                    </p>

                                    <p class="mt-1 text-[11px] leading-relaxed text-slate-600 line-clamp-3">
                                        {{ notice.body }}
                                    </p>
                                </div>
                            </div>

                            <div v-else class="p-8 text-center text-xs text-slate-400">
                                No active platform announcements from admin.
                            </div>
                        </div>
                    </section>

                    <!-- TOP PERFORMING PRODUCTS -->
                    <section class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-xs">
                        <div class="flex items-center justify-between border-b border-[#E5E7EB] px-5 py-4">
                            <div>
                                <h2 class="text-base font-bold text-[#1F2937]">Top Selling Products</h2>
                                <p class="mt-0.5 text-xs text-[#64748B]">Ranked by units sold and gross revenue.</p>
                            </div>

                            <Link
                                :href="route('seller.products')"
                                class="text-xs font-bold text-[#087F8C] hover:text-[#066D78]"
                            >
                                All Products
                            </Link>
                        </div>

                        <div v-if="topProducts.length" class="divide-y divide-slate-100">
                            <div
                                v-for="(product, index) in topProducts"
                                :key="product.product_id"
                                class="flex items-center gap-3 px-5 py-3.5 transition hover:bg-slate-50"
                            >
                                <div
                                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-xs font-black"
                                    :class="index === 0 ? 'bg-[#FFF8E7] text-[#B7791F]' : 'bg-slate-100 text-slate-500'"
                                >
                                    #{{ index + 1 }}
                                </div>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-slate-800">
                                        {{ product.product?.name }}
                                    </p>
                                    <p class="mt-0.5 text-[11px] text-slate-400">
                                        {{ product.sold }} units sold
                                    </p>
                                </div>

                                <div class="text-right">
                                    <p class="text-xs font-bold text-[#087F8C]">
                                        {{ peso(product.revenue) }}
                                    </p>
                                    <p class="text-[10px] text-slate-400">revenue</p>
                                </div>
                            </div>
                        </div>

                        <div v-else class="p-8 text-center text-xs text-slate-400">
                            No sales data yet for top products.
                        </div>
                    </section>

                    <!-- QUICK ACTIONS -->
                    <section class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-xs">
                        <h2 class="text-base font-bold text-[#1F2937]">Store Operations</h2>
                        <p class="mt-0.5 text-xs text-[#64748B]">Quick shortcuts to frequent store management pages.</p>

                        <div class="mt-4 grid grid-cols-2 gap-2.5">
                            <Link
                                :href="route('seller.products.create')"
                                class="flex flex-col items-center justify-center rounded-xl border border-slate-200/90 p-3 text-center transition hover:border-[#16A6A0] hover:bg-[#E8F7F6]/50 group"
                            >
                                <svg class="h-5 w-5 text-[#087F8C]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 5v14M5 12h14" />
                                </svg>
                                <span class="mt-1.5 text-xs font-bold text-slate-700 group-hover:text-[#087F8C]">New Product</span>
                            </Link>

                            <Link
                                :href="route('seller.orders')"
                                class="flex flex-col items-center justify-center rounded-xl border border-slate-200/90 p-3 text-center transition hover:border-[#16A6A0] hover:bg-[#E8F7F6]/50 group"
                            >
                                <svg class="h-5 w-5 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 3h12l2 4v13H4V7l2-4Z" />
                                    <path d="M4 7h16" />
                                </svg>
                                <span class="mt-1.5 text-xs font-bold text-slate-700 group-hover:text-[#087F8C]">Fulfillment</span>
                            </Link>

                            <Link
                                :href="route('seller.reviews')"
                                class="flex flex-col items-center justify-center rounded-xl border border-slate-200/90 p-3 text-center transition hover:border-[#16A6A0] hover:bg-[#E8F7F6]/50 group"
                            >
                                <svg class="h-5 w-5 text-amber-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z" />
                                </svg>
                                <span class="mt-1.5 text-xs font-bold text-slate-700 group-hover:text-[#087F8C]">Reviews</span>
                            </Link>

                            <Link
                                :href="route('seller.store')"
                                class="flex flex-col items-center justify-center rounded-xl border border-slate-200/90 p-3 text-center transition hover:border-[#16A6A0] hover:bg-[#E8F7F6]/50 group"
                            >
                                <svg class="h-5 w-5 text-purple-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 10l2-6h14l2 6" />
                                    <path d="M4 10v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-9" />
                                </svg>
                                <span class="mt-1.5 text-xs font-bold text-slate-700 group-hover:text-[#087F8C]">Storefront</span>
                            </Link>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </SellerLayout>
</template>