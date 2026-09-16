<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'

const props = defineProps({
    stats: {
        type: Object,
        required: true,
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
})

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

const peso = (value) => {
    return `₱${Number(value ?? 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`
}

const statCards = computed(() => [
    {
        title: 'Total Sales',
        value: peso(props.stats.total_sales),
        description: 'Lifetime store sales',
        icon: 'sales',
        tone: 'teal',
    },
    {
        title: 'Total Orders',
        value: Number(props.stats.total_orders ?? 0).toLocaleString(),
        description: 'Orders received',
        icon: 'orders',
        tone: 'blue',
    },
    {
        title: 'Products',
        value: Number(props.stats.total_products ?? 0).toLocaleString(),
        description: 'Products in your store',
        icon: 'products',
        tone: 'gold',
    },
    {
        title: 'Customers',
        value: Number(props.stats.total_customers ?? 0).toLocaleString(),
        description: 'Customers served',
        icon: 'customers',
        tone: 'purple',
    },
])

const chartMax = computed(() => {
    return Math.max(
        1,
        ...props.salesChart.map((day) => Number(day.total ?? 0))
    )
})

const chartAverage = computed(() => {
    if (!props.salesChart.length) {
        return 0
    }

    const total = props.salesChart.reduce(
        (sum, day) => sum + Number(day.total ?? 0),
        0
    )

    return total / props.salesChart.length
})

const statusLabel = (item) => {
    const map = {
        pending: 'To Pack',
        processing: 'Processing',
        packed: 'Packed',
        shipped: 'Shipped',
        delivered: 'Delivered',
        completed: 'Completed',
        cancelled: 'Cancelled',
    }

    return map[item.status] ?? item.status
}

const statusClass = (status) => {
    const map = {
        pending: 'bg-[#FFF8E7] text-[#B7791F]',
        processing: 'bg-[#E8F7F6] text-[#087F8C]',
        packed: 'bg-[#E8F7F6] text-[#087F8C]',
        shipped: 'bg-blue-50 text-blue-600',
        delivered: 'bg-emerald-50 text-emerald-600',
        completed: 'bg-emerald-50 text-emerald-600',
        cancelled: 'bg-red-50 text-[#E85D5D]',
    }

    return map[status] ?? 'bg-slate-100 text-slate-600'
}

const statIconClass = (tone) => {
    const map = {
        teal: 'bg-[#E8F7F6] text-[#087F8C]',
        blue: 'bg-blue-50 text-blue-600',
        gold: 'bg-[#FFF8E7] text-[#B7791F]',
        purple: 'bg-purple-50 text-purple-600',
    }

    return map[tone] ?? 'bg-slate-100 text-slate-600'
}
</script>

<template>
    <Head title="Seller Dashboard" />

    <SellerLayout>
        <div class="mx-auto max-w-7xl">

            <!-- ========================================================
                 PAGE HEADER
            ========================================================= -->

            <div
                class="flex flex-col gap-4 border-b border-[#E5E7EB] pb-5 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <span
                            class="h-2 w-2 rounded-full bg-[#F4B942]"
                        ></span>

                        <p
                            class="text-xs font-bold uppercase tracking-[0.14em] text-[#087F8C]"
                        >
                            Seller Center
                        </p>
                    </div>

                    <h1
                        class="mt-2 text-2xl font-bold tracking-tight text-[#1F2937] sm:text-3xl"
                    >
                        Dashboard
                    </h1>

                    <p
                        class="mt-1.5 max-w-xl text-sm text-[#64748B]"
                    >
                        Monitor your store performance, orders, and inventory
                        from one place.
                    </p>
                </div>
            </div>

            <!-- ========================================================
                 STATISTICS
            ========================================================= -->

            <div
                class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
            >
                <div
                    v-for="stat in statCards"
                    :key="stat.title"
                    class="group rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="flex items-start justify-between gap-3">

                        <div class="min-w-0">
                            <p
                                class="text-xs font-semibold text-[#64748B]"
                            >
                                {{ stat.title }}
                            </p>

                            <p
                                class="mt-2 truncate text-xl font-bold tracking-tight text-[#1F2937] sm:text-2xl"
                            >
                                {{ stat.value }}
                            </p>

                            <p
                                class="mt-1 text-[11px] text-slate-400"
                            >
                                {{ stat.description }}
                            </p>
                        </div>

                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                            :class="statIconClass(stat.tone)"
                        >

                            <!-- Sales -->

                            <svg
                                v-if="stat.icon === 'sales'"
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path d="M4 19V5" />
                                <path d="M4 19h17" />
                                <path d="m7 15 3-4 3 2 5-7" />
                            </svg>

                            <!-- Orders -->

                            <svg
                                v-else-if="stat.icon === 'orders'"
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    d="M6 3h12l2 4v13H4V7l2-4Z"
                                />
                                <path d="M4 7h16" />
                                <path d="M9 11h6M9 15h4" />
                            </svg>

                            <!-- Products -->

                            <svg
                                v-else-if="stat.icon === 'products'"
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"
                                />
                                <path d="m4 7.5 8 4.5 8-4.5" />
                                <path d="M12 12v9" />
                            </svg>

                            <!-- Customers -->

                            <svg
                                v-else
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <circle
                                    cx="9"
                                    cy="7"
                                    r="4"
                                />
                                <path
                                    d="M2 21a7 7 0 0 1 14 0"
                                />
                                <path d="M16 4.5a4 4 0 0 1 0 5" />
                                <path
                                    d="M18 14a6 6 0 0 1 4 7"
                                />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================
                 MAIN GRID
            ========================================================= -->

            <div
                class="mt-5 grid gap-5 xl:grid-cols-3"
            >

                <!-- ====================================================
                     SALES OVERVIEW
                ===================================================== -->

                <section
                    class="rounded-2xl border border-[#E5E7EB] bg-white shadow-sm xl:col-span-2"
                >
                    <div
                        class="flex flex-col gap-3 border-b border-[#E5E7EB] px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h2
                                class="text-base font-bold text-[#1F2937]"
                            >
                                Sales Overview
                            </h2>

                            <p
                                class="mt-1 text-xs text-[#64748B]"
                            >
                                Your sales performance over the past 7 days.
                            </p>
                        </div>

                        <div
                            class="flex items-center gap-2 rounded-lg bg-[#F8FAF9] px-3 py-2"
                        >
                            <span
                                class="h-2 w-2 rounded-full bg-[#16A6A0]"
                            ></span>

                            <span
                                class="text-[11px] font-semibold text-slate-500"
                            >
                                Avg.
                            </span>

                            <span
                                class="text-xs font-bold text-[#087F8C]"
                            >
                                {{ peso(chartAverage) }}
                            </span>
                        </div>
                    </div>

                    <div class="px-5 pb-5 pt-6">

                        <div
                            v-if="salesChart.length"
                            class="relative"
                        >

                            <!-- Chart grid -->

                            <div
                                class="pointer-events-none absolute inset-x-0 top-0 h-52"
                            >
                                <div
                                    class="absolute inset-x-0 top-0 border-t border-dashed border-slate-100"
                                ></div>

                                <div
                                    class="absolute inset-x-0 top-1/2 border-t border-dashed border-slate-100"
                                ></div>

                                <div
                                    class="absolute inset-x-0 bottom-0 border-t border-dashed border-slate-100"
                                ></div>
                            </div>

                            <!-- Bars -->

                            <div
                                class="relative flex h-52 items-end gap-2 sm:gap-4"
                            >
                                <div
                                    v-for="day in salesChart"
                                    :key="day.label"
                                    class="group flex h-full flex-1 flex-col items-center justify-end"
                                >

                                    <div
                                        class="relative flex h-full w-full items-end justify-center"
                                    >

                                        <!-- Tooltip -->

                                        <div
                                            class="pointer-events-none absolute bottom-full left-1/2 mb-2 -translate-x-1/2 whitespace-nowrap rounded-lg bg-[#1F2937] px-2.5 py-1.5 text-[10px] font-semibold text-white opacity-0 shadow-lg transition group-hover:opacity-100"
                                        >
                                            {{ peso(day.total) }}
                                        </div>

                                        <!-- Bar -->

                                        <div
                                            class="w-full max-w-10 rounded-t-lg bg-[#16A6A0] transition-all duration-200 group-hover:bg-[#087F8C]"
                                            :style="{
                                                height: `${Math.max(
                                                    5,
                                                    (Number(day.total ?? 0) / chartMax) * 100
                                                )}%`,
                                            }"
                                        ></div>
                                    </div>

                                    <span
                                        class="mt-2 text-[10px] font-medium text-slate-400"
                                    >
                                        {{ day.label }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div
                            v-else
                            class="flex h-52 flex-col items-center justify-center rounded-xl bg-[#F8FAF9]"
                        >
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                            >
                                <svg
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path d="M4 19V5" />
                                    <path d="M4 19h17" />
                                    <path d="m7 15 3-4 3 2 5-7" />
                                </svg>
                            </div>

                            <p
                                class="mt-3 text-sm font-semibold text-slate-600"
                            >
                                No sales yet
                            </p>

                            <p
                                class="mt-1 text-xs text-slate-400"
                            >
                                Sales activity will appear here.
                            </p>
                        </div>
                    </div>
                </section>

                <!-- ====================================================
                     QUICK ACTIONS
                ===================================================== -->

                <section
                    class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-sm"
                >
                    <div>
                        <h2
                            class="text-base font-bold text-[#1F2937]"
                        >
                            Quick Actions
                        </h2>

                        <p
                            class="mt-1 text-xs text-[#64748B]"
                        >
                            Common store management tasks.
                        </p>
                    </div>

                    <div class="mt-5 space-y-2.5">

                        <!-- Add Product -->

                        <Link
                            :href="route('seller.products.create')"
                            class="group flex items-center gap-3 rounded-xl border border-[#E5E7EB] p-3 transition hover:border-[#16A6A0] hover:bg-[#E8F7F6]"
                        >
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                            >
                                <svg
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path d="M12 5v14M5 12h14" />
                                </svg>
                            </div>

                            <div class="min-w-0 flex-1">
                                <p
                                    class="text-sm font-semibold text-slate-700 group-hover:text-[#087F8C]"
                                >
                                    Add Product
                                </p>

                                <p
                                    class="mt-0.5 text-[11px] text-slate-400"
                                >
                                    List a new product
                                </p>
                            </div>

                            <svg
                                class="h-4 w-4 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-[#087F8C]"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path d="m9 18 6-6-6-6" />
                            </svg>
                        </Link>

                        <!-- Orders -->

                        <Link
                            :href="route('seller.orders')"
                            class="group flex items-center gap-3 rounded-xl border border-[#E5E7EB] p-3 transition hover:border-[#16A6A0] hover:bg-[#E8F7F6]"
                        >
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600"
                            >
                                <svg
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        d="M6 3h12l2 4v13H4V7l2-4Z"
                                    />
                                    <path d="M4 7h16" />
                                    <path d="M9 11h6M9 15h4" />
                                </svg>
                            </div>

                            <div class="min-w-0 flex-1">
                                <p
                                    class="text-sm font-semibold text-slate-700 group-hover:text-[#087F8C]"
                                >
                                    Manage Orders
                                </p>

                                <p
                                    class="mt-0.5 text-[11px] text-slate-400"
                                >
                                    Process customer orders
                                </p>
                            </div>

                            <svg
                                class="h-4 w-4 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-[#087F8C]"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path d="m9 18 6-6-6-6" />
                            </svg>
                        </Link>

                        <!-- Store -->

                        <Link
                            :href="route('seller.store')"
                            class="group flex items-center gap-3 rounded-xl border border-[#E5E7EB] p-3 transition hover:border-[#16A6A0] hover:bg-[#E8F7F6]"
                        >
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#FFF8E7] text-[#B7791F]"
                            >
                                <svg
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        d="M3 10l2-6h14l2 6"
                                    />
                                    <path
                                        d="M4 10v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-9"
                                    />
                                    <path d="M3 10h18" />
                                    <path d="M8 10v3m4-3v3m4-3v3" />
                                </svg>
                            </div>

                            <div class="min-w-0 flex-1">
                                <p
                                    class="text-sm font-semibold text-slate-700 group-hover:text-[#087F8C]"
                                >
                                    Manage Store
                                </p>

                                <p
                                    class="mt-0.5 text-[11px] text-slate-400"
                                >
                                    Update your store profile
                                </p>
                            </div>

                            <svg
                                class="h-4 w-4 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-[#087F8C]"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path d="m9 18 6-6-6-6" />
                            </svg>
                        </Link>
                    </div>
                </section>
            </div>

            <!-- ========================================================
                 RECENT ORDERS
            ========================================================= -->

            <section
                class="mt-5 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
            >
                <div
                    class="flex flex-col gap-3 border-b border-[#E5E7EB] px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2
                            class="text-base font-bold text-[#1F2937]"
                        >
                            Recent Orders
                        </h2>

                        <p
                            class="mt-1 text-xs text-[#64748B]"
                        >
                            Your latest customer orders.
                        </p>
                    </div>

                    <Link
                        :href="route('seller.orders')"
                        class="inline-flex items-center gap-1 text-xs font-bold text-[#087F8C] hover:text-[#066D78]"
                    >
                        View All

                        <svg
                            class="h-3.5 w-3.5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path d="M5 12h14M13 6l6 6-6 6" />
                        </svg>
                    </Link>
                </div>

                <!-- Orders -->

                <div v-if="recentOrders.length">
                    <div
                        v-for="order in recentOrders"
                        :key="order.id"
                        class="border-b border-[#E5E7EB] px-5 py-4 last:border-b-0"
                    >
                        <div
                            class="flex flex-col gap-3 lg:flex-row lg:items-center"
                        >

                            <!-- Order Info -->

                            <div
                                class="flex min-w-0 flex-1 items-center gap-3"
                            >
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path
                                            d="M6 3h12l2 4v13H4V7l2-4Z"
                                        />
                                        <path d="M4 7h16" />
                                    </svg>
                                </div>

                                <div class="min-w-0">
                                    <p
                                        class="truncate text-sm font-bold text-[#087F8C]"
                                    >
                                        {{ order.order_number }}
                                    </p>

                                    <p
                                        class="mt-0.5 truncate text-[11px] text-slate-400"
                                    >
                                        {{ order.user?.name || 'Customer' }}
                                    </p>
                                </div>
                            </div>

                            <!-- Products -->

                            <div
                                class="flex min-w-0 flex-1 flex-wrap gap-1.5"
                            >
                                <span
                                    v-for="item in order.items"
                                    :key="item.id"
                                    class="max-w-full truncate rounded-lg bg-slate-50 px-2.5 py-1.5 text-[10px] font-medium text-slate-500"
                                >
                                    {{ item.product?.name }}
                                    ×{{ item.quantity }}
                                </span>
                            </div>

                            <!-- Status -->

                            <div class="shrink-0">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1.5 text-[10px] font-bold"
                                    :class="statusClass(order.status)"
                                >
                                    {{ statusLabel(order) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty -->

                <div
                    v-else
                    class="flex flex-col items-center justify-center px-5 py-12"
                >
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                    >
                        <svg
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                d="M6 3h12l2 4v13H4V7l2-4Z"
                            />
                            <path d="M4 7h16" />
                        </svg>
                    </div>

                    <p
                        class="mt-3 text-sm font-semibold text-slate-600"
                    >
                        No orders yet
                    </p>

                    <p
                        class="mt-1 text-xs text-slate-400"
                    >
                        Customer orders will appear here.
                    </p>
                </div>
            </section>

            <!-- ========================================================
                 BOTTOM GRID
            ========================================================= -->

            <div
                class="mt-5 grid gap-5 lg:grid-cols-2"
            >

                <!-- ====================================================
                     BEST SELLING PRODUCTS
                ===================================================== -->

                <section
                    class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                >
                    <div
                        class="flex items-center justify-between border-b border-[#E5E7EB] px-5 py-4"
                    >
                        <div>
                            <h2
                                class="text-base font-bold text-[#1F2937]"
                            >
                                Best-Selling Products
                            </h2>

                            <p
                                class="mt-1 text-xs text-[#64748B]"
                            >
                                Your top performing products.
                            </p>
                        </div>

                        <Link
                            :href="route('seller.products')"
                            class="text-xs font-bold text-[#087F8C] hover:text-[#066D78]"
                        >
                            View All
                        </Link>
                    </div>

                    <div v-if="topProducts.length">
                        <div
                            v-for="(product, index) in topProducts"
                            :key="product.product_id"
                            class="flex items-center gap-3 border-b border-[#E5E7EB] px-5 py-3.5 last:border-b-0"
                        >

                            <!-- Rank -->

                            <div
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                                :class="
                                    index === 0
                                        ? 'bg-[#FFF8E7] text-[#B7791F]'
                                        : 'bg-slate-50 text-slate-500'
                                "
                            >
                                <span
                                    class="text-xs font-bold"
                                >
                                    {{ index + 1 }}
                                </span>
                            </div>

                            <!-- Product -->

                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-sm font-semibold text-slate-700"
                                >
                                    {{ product.product?.name }}
                                </p>

                                <p
                                    class="mt-0.5 text-[11px] text-slate-400"
                                >
                                    {{ product.sold }} sold
                                </p>
                            </div>

                            <!-- Revenue -->

                            <div class="text-right">
                                <p
                                    class="text-sm font-bold text-[#087F8C]"
                                >
                                    {{ peso(product.revenue) }}
                                </p>

                                <p
                                    class="mt-0.5 text-[10px] text-slate-400"
                                >
                                    revenue
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="flex flex-col items-center justify-center px-5 py-12"
                    >
                        <div
                            class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                        >
                            <svg
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"
                                />
                            </svg>
                        </div>

                        <p
                            class="mt-3 text-sm font-semibold text-slate-600"
                        >
                            No completed sales yet
                        </p>

                        <p
                            class="mt-1 text-xs text-slate-400"
                        >
                            Your best-selling products will appear here.
                        </p>
                    </div>
                </section>

                <!-- ====================================================
                     LOW STOCK
                ===================================================== -->

                <section
                    class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                >
                    <div
                        class="flex items-center justify-between border-b border-[#E5E7EB] px-5 py-4"
                    >
                        <div>
                            <div class="flex items-center gap-2">
                                <h2
                                    class="text-base font-bold text-[#1F2937]"
                                >
                                    Low Stock Products
                                </h2>

                                <span
                                    v-if="lowStockProducts.length"
                                    class="rounded-full bg-red-50 px-2 py-0.5 text-[9px] font-bold text-[#E85D5D]"
                                >
                                    {{ lowStockProducts.length }}
                                </span>
                            </div>

                            <p
                                class="mt-1 text-xs text-[#64748B]"
                            >
                                Products that need your attention.
                            </p>
                        </div>

                        <Link
                            :href="route('seller.stock-monitoring')"
                            class="text-xs font-bold text-[#087F8C] hover:text-[#066D78]"
                        >
                            Stock Monitor
                        </Link>
                    </div>

                    <div v-if="lowStockProducts.length">
                        <div
                            v-for="product in lowStockProducts"
                            :key="product.id"
                            class="flex items-center gap-3 border-b border-[#E5E7EB] px-5 py-3.5 last:border-b-0"
                        >

                            <!-- Warning -->

                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-red-50 text-[#E85D5D]"
                            >
                                <svg
                                    class="h-4.5 w-4.5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        d="M10.3 3.9 2.2 18a2 2 0 0 0 1.7 3h16.2a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"
                                    />
                                    <path d="M12 9v4M12 17h.01" />
                                </svg>
                            </div>

                            <!-- Product -->

                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-sm font-semibold text-slate-700"
                                >
                                    {{ product.name }}
                                </p>

                                <p
                                    class="mt-0.5 text-[11px] text-[#E85D5D]"
                                >
                                    Only {{ product.stock }} left in stock
                                </p>
                            </div>

                            <!-- Restock -->

                            <Link
                                :href="route('seller.products.edit', product.id)"
                                class="shrink-0 rounded-lg bg-[#087F8C] px-3 py-2 text-[10px] font-bold text-white transition hover:bg-[#066D78]"
                            >
                                Restock
                            </Link>
                        </div>
                    </div>

                    <div
                        v-else
                        class="flex flex-col items-center justify-center px-5 py-12"
                    >
                        <div
                            class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-[#22A06B]"
                        >
                            <svg
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    d="M9 12l2 2 4-4"
                                />
                                <circle
                                    cx="12"
                                    cy="12"
                                    r="9"
                                />
                            </svg>
                        </div>

                        <p
                            class="mt-3 text-sm font-semibold text-slate-600"
                        >
                            Stock levels look healthy
                        </p>

                        <p
                            class="mt-1 text-xs text-slate-400"
                        >
                            No products currently need restocking.
                        </p>
                    </div>
                </section>
            </div>
        </div>
    </SellerLayout>
</template>