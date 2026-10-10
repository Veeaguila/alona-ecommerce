<!-- Buyer order history with status filter tabs, search, and quick actions. -->
<script setup>
import Icon from '@/Components/Icon.vue'
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'

const props = defineProps({
    orders: {
        type: [Array, Object],
        default: () => [],
    },
    current_status: {
        type: String,
        default: 'all',
    },
    counts: {
        type: Object,
        default: () => ({
            all: 0,
            to_pay: 0,
            to_ship: 0,
            in_transit: 0,
            delivered: 0,
            cancelled: 0,
            returns: 0,
        }),
    },
    filters: {
        type: Object,
        default: () => ({
            status: 'all',
            search: '',
        }),
    },
    status: {
        type: String,
        default: '',
    },
})

const searchQuery = ref(props.filters?.search || '')
const reorderingId = ref(null)

const tabs = [
    { key: 'all', label: 'All Orders', countKey: 'all' },
    { key: 'to_pay', label: 'To Pay', countKey: 'to_pay' },
    { key: 'to_ship', label: 'To Ship', countKey: 'to_ship' },
    { key: 'in_transit', label: 'In Transit', countKey: 'in_transit' },
    { key: 'delivered', label: 'Delivered', countKey: 'delivered' },
    { key: 'cancelled', label: 'Cancelled', countKey: 'cancelled' },
    { key: 'returns', label: 'Returns / Refunds', countKey: 'returns' },
]

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const money = value => {
    const amount = Number(value ?? 0)
    return `₱${amount.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`
}

const formatDate = value => {
    if (!value) return 'Date unavailable'
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return 'Date unavailable'
    return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}

const formatPaymentMethod = value => {
    if (!value) return 'Payment method unavailable'
    const methods = {
        cod: 'Cash on Delivery',
        cash_on_delivery: 'Cash on Delivery',
        gcash: 'GCash',
        maya: 'Maya',
        card: 'Credit / Debit Card',
    }
    return methods[value] || value
}

const normalizeStatus = value => String(value ?? '').toLowerCase()

const getStatusBadge = value => {
    const status = normalizeStatus(value)

    const badges = {
        pending: {
            label: 'To Pack',
            color: 'bg-[#FFF7E6] text-[#A66A00] ring-1 ring-[#F4B942]/30',
        },
        processing: {
            label: 'Processing',
            color: 'bg-[#E8F7F6] text-[#087F8C] ring-1 ring-[#16A6A0]/20',
        },
        packed: {
            label: 'To Ship',
            color: 'bg-[#E8F7F6] text-[#087F8C] ring-1 ring-[#16A6A0]/20',
        },
        ready_for_pickup: {
            label: 'Ready for Pickup',
            color: 'bg-[#E8F7F6] text-[#087F8C] ring-1 ring-[#16A6A0]/20',
        },
        shipped: {
            label: 'In Transit',
            color: 'bg-[#F0EAFE] text-[#6D4BC3] ring-1 ring-[#8B5CF6]/20',
        },
        out_for_delivery: {
            label: 'Out for Delivery',
            color: 'bg-[#FFF3E8] text-[#C56A20] ring-1 ring-[#F97316]/20',
        },
        delivered: {
            label: 'Delivered',
            color: 'bg-[#EAF8F1] text-[#17784F] ring-1 ring-[#22A06B]/20',
        },
        completed: {
            label: 'Completed',
            color: 'bg-[#EAF8F1] text-[#17784F] ring-1 ring-[#22A06B]/20',
        },
        cancelled: {
            label: 'Cancelled',
            color: 'bg-[#FDECEC] text-[#B94242] ring-1 ring-[#E85D5D]/20',
        },
        refunded: {
            label: 'Refunded',
            color: 'bg-[#FDECEC] text-[#B94242] ring-1 ring-[#E85D5D]/20',
        },
    }

    return badges[status] || {
        label: value || 'Unknown',
        color: 'bg-[#F8FAF9] text-[#64748B] ring-1 ring-[#E5E7EB]',
    }
}

const getItemTotal = item => Number(item?.price ?? 0) * Number(item?.quantity ?? 0)

const orderList = computed(() => {
    if (Array.isArray(props.orders)) return props.orders
    if (props.orders?.data && Array.isArray(props.orders.data)) return props.orders.data
    return []
})

const pagination = computed(() => {
    if (props.orders?.links) {
        return props.orders.links
    }
    return []
})

const selectTab = tabKey => {
    router.get(
        route('buyer.orders'),
        {
            status: tabKey,
            search: searchQuery.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    )
}

const handleSearch = () => {
    router.get(
        route('buyer.orders'),
        {
            status: props.current_status,
            search: searchQuery.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    )
}

const clearSearch = () => {
    searchQuery.value = ''
    handleSearch()
}

const reorder = orderId => {
    reorderingId.value = orderId
    router.post(
        route('buyer.orders.reorder', orderId),
        {},
        {
            onFinish: () => {
                reorderingId.value = null
            },
        }
    )
}
</script>

<template>
    <Head title="My Orders" />

    <BuyerLayout>
        <main class="min-h-[calc(100vh-80px)] w-full min-w-0 overflow-x-hidden bg-[#F8FAF9]">
            <div class="mx-auto w-full max-w-[1536px] px-4 py-6 pb-12 sm:px-6 sm:py-7 lg:px-8 2xl:px-10 lg:py-8 lg:pb-14">

                <!-- PAGE HEADER -->
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#F4B942]"></span>
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#087F8C] sm:text-xs">
                                Order management
                            </p>
                        </div>

                        <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-[#1F2937] sm:text-3xl">
                            My Orders
                        </h1>

                        <p class="mt-1 text-xs leading-5 text-[#64748B] sm:text-sm">
                            Track packages, manage deliveries, cancellations, and return requests.
                        </p>
                    </div>

                    <Link
                        :href="route('buyer.products')"
                        class="inline-flex w-fit items-center gap-2 rounded-xl bg-[#087F8C] px-4 py-2.5 text-xs font-extrabold text-white shadow-sm transition hover:bg-[#066B76] sm:text-sm"
                    >
                        <span class="text-base leading-none">+</span>
                        Shop Products
                    </Link>
                </div>

                <!-- SUCCESS MESSAGE -->
                <div
                    v-if="status"
                    class="mt-4 flex items-start gap-2.5 rounded-xl border border-[#CDE9E4] bg-[#EAF8F1] px-3.5 py-3 text-xs font-bold text-[#17784F] sm:text-sm"
                >
                    <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12l4 4L19 6" />
                    </svg>
                    <span>{{ status }}</span>
                </div>

                <!-- SEARCH & FILTER BAR -->
                <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <!-- SEARCH -->
                    <div class="relative w-full sm:max-w-xs">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search by order # or product..."
                            class="w-full rounded-xl border border-[#E5E7EB] bg-white py-2 pl-9 pr-8 text-xs text-[#1F2937] placeholder:text-[#94A3B8] focus:border-[#16A6A0] focus:ring-2 focus:ring-[#E8F7F6]"
                            @keydown.enter="handleSearch"
                        />
                        <span class="pointer-events-none absolute left-3 top-2.5 text-[#94A3B8]"><Icon name="search" class="h-4 w-4" /></span>
                        <button
                            v-if="searchQuery"
                            type="button"
                            class="absolute right-2.5 top-2 text-xs text-[#94A3B8] hover:text-[#475569]"
                            @click="clearSearch"
                        >
                            ✕
                        </button>
                    </div>
                </div>

                <!-- STATUS TABS (BUYER-17) -->
                <div class="mt-4 overflow-x-auto pb-1 scrollbar-none">
                    <nav class="flex min-w-max gap-2 border-b border-[#E5E7EB] pb-2">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            :class="[
                                'inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-extrabold transition',
                                current_status === tab.key
                                    ? 'bg-[#087F8C] text-white shadow-sm'
                                    : 'bg-white text-[#64748B] hover:bg-[#F1F5F9] hover:text-[#1F2937]'
                            ]"
                            @click="selectTab(tab.key)"
                        >
                            <span>{{ tab.label }}</span>
                            <span
                                :class="[
                                    'rounded-full px-1.5 py-0.5 text-[10px] font-bold',
                                    current_status === tab.key
                                        ? 'bg-white/20 text-white'
                                        : 'bg-[#F1F5F9] text-[#64748B]'
                                ]"
                            >
                                {{ counts[tab.countKey] || 0 }}
                            </span>
                        </button>
                    </nav>
                </div>

                <!-- ORDERS LIST -->
                <div
                    v-if="orderList.length"
                    class="mt-5 space-y-4"
                >
                    <div
                        v-for="order in orderList"
                        :key="order.id"
                        class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-[0_6px_22px_rgba(15,23,42,0.04)] transition hover:border-[#CFE5E4]"
                    >
                        <!-- ORDER HEADER -->
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#EEF1F2] bg-[#FCFDFC] px-4 py-3.5 sm:px-5">
                            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 3h12l2 5H4l2-5zm-2 5h16v11a2 2 0 01-2 2H6a2 2 0 01-2-2V8zm4 4h8" />
                                    </svg>
                                </div>

                                <div>
                                    <Link
                                        :href="route('buyer.orders.show', order.id)"
                                        class="text-sm font-extrabold text-[#1F2937] hover:text-[#087F8C] sm:text-base"
                                    >
                                        {{ order.order_number || `Order #${order.id}` }}
                                    </Link>
                                    <p class="text-[10px] text-[#94A3B8] sm:text-xs">
                                        Placed {{ formatDate(order.created_at) }}
                                        <span class="mx-1">·</span>
                                        {{ formatPaymentMethod(order.payment_method) }}
                                        <span v-if="order.shipping_method" class="ml-1 text-[#087F8C] font-semibold">
                                            ({{ order.shipping_method }})
                                        </span>
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <span
                                    v-if="order.return_requests?.length"
                                    class="rounded-full bg-[#FFF3E8] px-2.5 py-1 text-[9px] font-extrabold text-[#C56A20] ring-1 ring-[#F97316]/20"
                                >
                                    Return Requested
                                </span>

                                <span
                                    :class="[
                                        'rounded-full px-2.5 py-1 text-[9px] font-extrabold sm:px-3 sm:py-1.5 sm:text-[10px]',
                                        getStatusBadge(order.status).color
                                    ]"
                                >
                                    {{ getStatusBadge(order.status).label }}
                                </span>
                            </div>
                        </div>

                        <!-- ORDER ITEMS -->
                        <div class="divide-y divide-[#EEF1F2] px-4 sm:px-5">
                            <div
                                v-for="item in order.items"
                                :key="item.id"
                                class="flex min-w-0 items-center justify-between gap-3 py-3 sm:py-3.5"
                            >
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xs font-bold text-[#1F2937] sm:text-sm">
                                        {{ item.product_name || 'Product' }}
                                        <span
                                            v-if="item.variant_label"
                                            class="font-medium text-[#94A3B8]"
                                        >
                                            ({{ item.variant_label }})
                                        </span>
                                    </p>

                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-[10px] text-[#64748B]">
                                        <span>{{ money(item.price) }} × {{ item.quantity || 0 }}</span>
                                        <span class="text-[#CBD5E1]">·</span>
                                        <span :class="getStatusBadge(item.status || order.status).color" class="rounded-full px-2 py-0.5 text-[8px] font-extrabold sm:text-[9px]">
                                            {{ getStatusBadge(item.status || order.status).label }}
                                        </span>
                                        <span v-if="item.tracking_number" class="text-[#087F8C] font-semibold">
                                            {{ item.courier_name || 'Courier' }}: {{ item.tracking_number }}
                                        </span>
                                    </div>
                                </div>

                                <p class="shrink-0 text-xs font-extrabold text-[#1F2937] sm:text-sm">
                                    {{ money(getItemTotal(item)) }}
                                </p>
                            </div>
                        </div>

                        <!-- ORDER FOOTER / ACTIONS (BUYER-19, BUYER-20) -->
                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[#EEF1F2] bg-[#FCFDFC] px-4 py-3 sm:px-5">
                            <div>
                                <span class="text-[10px] font-semibold uppercase tracking-wider text-[#94A3B8]">
                                    Total:
                                </span>
                                <span class="ml-1 text-sm font-extrabold text-[#087F8C] sm:text-base">
                                    {{ money(order.total) }}
                                </span>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    :disabled="reorderingId === order.id"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-[#D5E4E3] bg-white px-3 py-1.5 text-xs font-bold text-[#087F8C] transition hover:bg-[#E8F7F6] disabled:opacity-50"
                                    @click="reorder(order.id)"
                                >
                                    <span><Icon name="undo" class="h-4 w-4" /></span>
                                    {{ reorderingId === order.id ? 'Adding...' : 'Buy Again' }}
                                </button>

                                <Link
                                    :href="route('buyer.orders.show', order.id)"
                                    class="inline-flex items-center gap-1 rounded-xl bg-[#087F8C] px-3.5 py-1.5 text-xs font-extrabold text-white transition hover:bg-[#066B76]"
                                >
                                    <span>View & Track</span>
                                    <span>→</span>
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- PAGINATION -->
                    <div
                        v-if="pagination.length > 3"
                        class="mt-6 flex justify-center gap-1"
                    >
                        <Component
                            :is="link.url ? Link : 'span'"
                            v-for="(link, i) in pagination"
                            :key="i"
                            :href="link.url"
                            :class="[
                                'rounded-xl px-3 py-1.5 text-xs font-bold transition',
                                link.active
                                    ? 'bg-[#087F8C] text-white'
                                    : link.url
                                        ? 'bg-white text-[#64748B] hover:bg-[#E8F7F6] hover:text-[#087F8C]'
                                        : 'bg-[#F1F5F9] text-[#CBD5E1]'
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>

                <!-- EMPTY STATE -->
                <div
                    v-else
                    class="mt-5 rounded-2xl border border-dashed border-[#D7E0E2] bg-white px-5 py-14 text-center shadow-[0_6px_22px_rgba(15,23,42,0.03)] sm:mt-6 sm:py-16"
                >
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-[#E8F7F6] text-[#087F8C]">
                        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 3h12l2 5H4l2-5zm-2 5h16v11a2 2 0 01-2 2H6a2 2 0 01-2-2V8zm4 4h8" />
                        </svg>
                    </div>

                    <p class="mt-4 text-sm font-extrabold text-[#1F2937]">
                        {{ searchQuery ? 'No matching orders found.' : 'No orders in this category.' }}
                    </p>

                    <p class="mx-auto mt-1.5 max-w-sm text-xs leading-5 text-[#64748B] sm:text-sm">
                        {{ searchQuery ? 'Try searching with another keyword.' : 'Check back later or browse our marketplace products.' }}
                    </p>

                    <Link
                        :href="route('buyer.products')"
                        class="mt-5 inline-flex items-center gap-2 rounded-xl bg-[#087F8C] px-4 py-2.5 text-xs font-extrabold text-white transition hover:bg-[#066B76] sm:text-sm"
                    >
                        Browse Products
                        <span>→</span>
                    </Link>
                </div>
            </div>
        </main>
    </BuyerLayout>
</template>
