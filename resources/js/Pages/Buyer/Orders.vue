<!-- Buyer order history with Shopee-style layout aligned to system design (BUYER-17). -->
<script setup>
import Icon from '@/Components/Icon.vue'
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import BuyerAccountLayout from '@/Layouts/BuyerAccountLayout.vue'

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
            to_receive: 0,
            delivered: 0,
            completed: 0,
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
    { key: 'all', label: 'All', countKey: 'all' },
    { key: 'to_pay', label: 'To Pay', countKey: 'to_pay' },
    { key: 'to_ship', label: 'To Ship', countKey: 'to_ship' },
    { key: 'to_receive', label: 'To Receive', countKey: 'to_receive' },
    { key: 'completed', label: 'Completed', countKey: 'completed' },
    { key: 'cancelled', label: 'Cancelled', countKey: 'cancelled' },
    { key: 'returns', label: 'Return Refund', countKey: 'returns' },
]

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const imageUrl = path => {
    if (!path) return null
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/storage/')) return path
    return `/storage/${path.replace(/^\/+/, '')}`
}

const itemImage = item => {
    return imageUrl(
        item?.variant?.image_path ||
        item?.product?.image_path ||
        item?.product?.images?.[0]?.image_path ||
        item?.product?.primary_image_url ||
        item?.product?.image_url ||
        item?.image_path ||
        null
    )
}

const getOrderShopName = order => {
    const seller = order?.items?.[0]?.product?.seller
    return seller?.store_name || seller?.shop_name || seller?.name || 'Marketplace Seller'
}

const getItemShopName = item => {
    const seller = item?.product?.seller
    return seller?.store_name || seller?.shop_name || seller?.name || 'Marketplace Seller'
}

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
            color: 'bg-[#FFF8E7] text-[#A66B00] ring-1 ring-[#F4B942]/30',
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

const receivingOrderId = ref(null)
const markOrderReceived = orderId => {
    if (!orderId || receivingOrderId.value) return
    if (!confirm('Have you received this order in good condition?')) return
    receivingOrderId.value = orderId
    router.post(route('buyer.orders.receive', orderId), {}, {
        preserveScroll: true,
        onFinish: () => {
            receivingOrderId.value = null
        },
    })
}
</script>

<template>
    <Head title="My Purchase" />

    <BuyerAccountLayout active-menu="purchase">
        
        <!-- STATUS TABS (SYSTEM ALIGNED) -->
        <div class="overflow-x-auto rounded-2xl border border-[#E5E7EB] bg-white shadow-xs scrollbar-none">
            <div class="flex min-w-max border-b border-[#EEF1F2]">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="relative flex-1 px-5 py-3.5 text-center text-xs font-bold transition sm:text-sm"
                    :class="[
                        current_status === tab.key || (tab.key === 'to_receive' && current_status === 'in_transit')
                            ? 'font-extrabold text-[#087F8C]'
                            : 'text-[#64748B] hover:text-[#087F8C]'
                    ]"
                    @click="selectTab(tab.key)"
                >
                    <span>{{ tab.label }}</span>
                    <span
                        v-if="counts[tab.countKey] > 0"
                        class="ml-1.5 rounded-full px-1.5 py-0.5 text-[10px] font-extrabold"
                        :class="[
                            current_status === tab.key || (tab.key === 'to_receive' && current_status === 'in_transit')
                                ? 'bg-[#E8F7F6] text-[#087F8C]'
                                : 'bg-gray-100 text-[#64748B]'
                        ]"
                    >
                        {{ counts[tab.countKey] }}
                    </span>

                    <!-- Underline Indicator in Brand Teal -->
                    <span
                        v-if="current_status === tab.key || (tab.key === 'to_receive' && current_status === 'in_transit')"
                        class="absolute bottom-0 left-0 right-0 h-[2.5px] bg-[#087F8C]"
                    ></span>
                </button>
            </div>
        </div>

        <!-- SEARCH BAR (SYSTEM ALIGNED) -->
        <div class="my-4 rounded-2xl border border-[#E5E7EB] bg-white p-2.5 shadow-xs sm:p-3">
            <div class="relative flex items-center">
                <span class="pl-2 text-[#087F8C]">
                    <Icon name="search" class="h-4 w-4" />
                </span>

                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="You can search by Seller Name, Order ID or Product name"
                    class="w-full border-0 bg-transparent py-1 pl-3 pr-8 text-xs text-[#1F2937] placeholder:text-[#94A3B8] focus:ring-0 sm:text-sm"
                    @keydown.enter="handleSearch"
                />

                <button
                    v-if="searchQuery"
                    type="button"
                    class="rounded-lg p-1 text-xs text-[#94A3B8] hover:bg-[#F8FAF9] hover:text-[#1F2937]"
                    @click="clearSearch"
                >
                    ✕
                </button>
            </div>
        </div>

        <!-- SUCCESS STATUS NOTIFICATION -->
        <div
            v-if="status"
            class="mb-4 flex items-start gap-2.5 rounded-xl border border-[#CDE9E4] bg-[#EAF8F1] px-4 py-3 text-xs font-bold text-[#17784F]"
        >
            <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12l4 4L19 6" />
            </svg>
            <span>{{ status }}</span>
        </div>

        <!-- ORDERS LIST -->
        <div
            v-if="orderList.length"
            class="space-y-4"
        >
            <div
                v-for="order in orderList"
                :key="order.id"
                class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-xs transition hover:border-[#B9E7E4]"
            >
                <!-- CARD HEADER: SHOP & STATUS -->
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#EEF1F2] bg-[#FCFDFC] px-4 py-3.5 sm:px-6">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 22V12h6v10" />
                            </svg>
                        </span>

                        <span class="text-xs font-extrabold text-[#1F2937] sm:text-sm">
                            {{ getOrderShopName(order) }}
                        </span>

                        <span class="rounded-md bg-[#E8F7F6] px-2 py-0.5 text-[10px] font-extrabold text-[#087F8C]">
                            Shop
                        </span>

                        <Link
                            :href="route('buyer.orders.show', order.id)"
                            class="text-xs font-semibold text-[#64748B] hover:text-[#087F8C]"
                        >
                            {{ order.order_number || `#${order.id}` }}
                        </Link>
                    </div>

                    <div class="flex items-center gap-2">
                        <span
                            v-if="order.return_requests?.length"
                            class="rounded-lg bg-[#FFF8E7] px-2 py-0.5 text-[10px] font-bold text-[#A66B00] ring-1 ring-[#F4B942]/30"
                        >
                            Return Requested
                        </span>

                        <span
                            class="rounded-lg px-2.5 py-0.5 text-xs font-bold uppercase tracking-wider"
                            :class="getStatusBadge(order.status).color"
                        >
                            {{ getStatusBadge(order.status).label }}
                        </span>
                    </div>
                </div>

                <!-- CARD BODY: PRODUCTS -->
                <div class="divide-y divide-[#EEF1F2] px-4 py-2 sm:px-6">
                    <Link
                        v-for="item in order.items"
                        :key="item.id"
                        :href="route('buyer.orders.show', order.id)"
                        class="flex gap-3.5 py-3.5 transition hover:bg-[#F8FAF9]/80 sm:gap-4"
                    >
                        <div class="h-16 w-16 shrink-0 overflow-hidden rounded-xl border border-[#E5E7EB] bg-gray-50 sm:h-20 sm:w-20">
                            <img
                                v-if="itemImage(item)"
                                :src="itemImage(item)"
                                :alt="item.product?.name || item.product_name"
                                class="h-full w-full object-cover"
                            />
                            <div v-else class="flex h-full w-full items-center justify-center text-xs text-gray-400">
                                No image
                            </div>
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="line-clamp-2 text-xs font-extrabold text-[#1F2937] sm:text-sm">
                                {{ item.product?.name || item.product_name || 'Product item' }}
                            </p>
                            <p v-if="item.variant || item.variant_label" class="mt-0.5 text-[11px] font-semibold text-[#64748B]">
                                Variation: {{ item.variant?.name || item.variant?.sku || item.variant_label }}
                            </p>
                            <p class="mt-0.5 text-[11px] font-medium text-[#64748B]">
                                Seller: <span class="font-bold text-[#1F2937]">{{ getItemShopName(item) }}</span>
                            </p>
                            <p class="mt-1 text-xs font-semibold text-[#94A3B8]">
                                Quantity: {{ item.quantity }}
                            </p>
                        </div>

                        <div class="text-right">
                            <span class="text-xs font-extrabold text-[#087F8C] sm:text-sm">
                                {{ money(item.price) }}
                            </span>
                        </div>
                    </Link>
                </div>

                <!-- CARD FOOTER: TOTAL & ACTIONS -->
                <div class="flex flex-col gap-3 border-t border-[#EEF1F2] bg-[#FCFDFC] px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div class="text-xs text-[#64748B]">
                        Placed on {{ formatDate(order.created_at) }}
                        <span class="mx-1">·</span>
                        {{ formatPaymentMethod(order.payment_method) }}
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2.5 sm:gap-3">
                        <div class="text-right mr-1">
                            <span class="text-xs font-medium text-[#64748B]">Order Total: </span>
                            <span class="text-base font-black text-[#087F8C] sm:text-lg">
                                {{ money(order.total_amount ?? order.total) }}
                            </span>
                        </div>

                        <!-- Mark received button for delivered orders -->
                        <button
                            v-if="['shipped', 'out_for_delivery', 'delivered'].includes(normalizeStatus(order.status)) && !['completed', 'cancelled'].includes(normalizeStatus(order.status))"
                            type="button"
                            class="rounded-xl bg-[#087F8C] px-4 py-2 text-xs font-extrabold text-white shadow-xs transition hover:bg-[#066B76]"
                            @click="markOrderReceived(order.id)"
                        >
                            {{ receivingOrderId === order.id ? 'Confirming...' : 'Order Received' }}
                        </button>

                        <!-- Buy Again button -->
                        <button
                            v-if="['completed', 'delivered', 'cancelled'].includes(normalizeStatus(order.status))"
                            type="button"
                            class="rounded-xl border border-[#E5E7EB] bg-white px-4 py-2 text-xs font-extrabold text-[#1F2937] transition hover:border-[#087F8C] hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                            :disabled="reorderingId === order.id"
                            @click="reorder(order.id)"
                        >
                            {{ reorderingId === order.id ? 'Adding...' : 'Buy Again' }}
                        </button>

                        <!-- View details link -->
                        <Link
                            :href="route('buyer.orders.show', order.id)"
                            class="rounded-xl border border-[#E5E7EB] bg-white px-4 py-2 text-xs font-extrabold text-[#087F8C] transition hover:border-[#087F8C] hover:bg-[#E8F7F6]"
                        >
                            View & Track
                        </Link>
                    </div>
                </div>
            </div>

            <!-- PAGINATION -->
            <div
                v-if="pagination.length > 3"
                class="mt-6 flex justify-center gap-1.5 pb-4"
            >
                <Component
                    :is="link.url ? Link : 'span'"
                    v-for="(link, i) in pagination"
                    :key="i"
                    :href="link.url"
                    class="rounded-xl px-3.5 py-2 text-xs font-extrabold transition"
                    :class="[
                        link.active
                            ? 'bg-[#087F8C] text-white shadow-xs'
                            : link.url
                                ? 'bg-white text-[#64748B] border border-[#E5E7EB] hover:border-[#087F8C] hover:bg-[#E8F7F6] hover:text-[#087F8C]'
                                : 'bg-[#F8FAF9] text-[#CBD5E1]'
                    ]"
                    v-html="link.label"
                />
            </div>
        </div>

        <!-- EMPTY STATE (MATCHING SCREENSHOT + SYSTEM DESIGN) -->
        <div
            v-else
            class="flex min-h-[460px] flex-col items-center justify-center rounded-2xl border border-[#E5E7EB] bg-white p-8 text-center shadow-xs"
        >
            <!-- System-Themed Clipboard Illustration -->
            <div class="relative mb-3 flex h-28 w-28 items-center justify-center">
                <!-- Background decorative pastel circles -->
                <span class="absolute -top-1 -left-2 h-3.5 w-3.5 rounded-full bg-[#F4B942]/60"></span>
                <span class="absolute top-2 -right-1 h-2.5 w-2.5 rounded-full bg-[#087F8C]/40"></span>
                <span class="absolute bottom-2 -left-3 h-3 w-3 rounded-full bg-[#16A6A0]/30"></span>

                <!-- Circular soft badge background -->
                <div class="flex h-20 w-20 items-center justify-center rounded-full bg-[#E8F7F6] ring-4 ring-[#E8F7F6]/50">
                    <!-- Clipboard with checkmarks -->
                    <svg
                        class="h-12 w-12 text-[#087F8C]"
                        viewBox="0 0 64 64"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <!-- Board background -->
                        <rect x="14" y="10" width="36" height="46" rx="4" fill="#B9E7E4" stroke="#087F8C" stroke-width="2" />
                        <!-- White paper sheet -->
                        <rect x="18" y="16" width="28" height="36" rx="2" fill="#FFFFFF" />
                        <!-- Top Clip -->
                        <rect x="25" y="7" width="14" height="6" rx="2" fill="#087F8C" />
                        <!-- Checklist lines -->
                        <line x1="22" y1="23" x2="26" y2="23" stroke="#087F8C" stroke-width="2" stroke-linecap="round" />
                        <line x1="29" y1="23" x2="42" y2="23" stroke="#94A3B8" stroke-width="2" stroke-linecap="round" />
                        <line x1="22" y1="30" x2="26" y2="30" stroke="#087F8C" stroke-width="2" stroke-linecap="round" />
                        <line x1="29" y1="30" x2="42" y2="30" stroke="#94A3B8" stroke-width="2" stroke-linecap="round" />
                        <line x1="22" y1="37" x2="26" y2="37" stroke="#087F8C" stroke-width="2" stroke-linecap="round" />
                        <line x1="29" y1="37" x2="40" y2="37" stroke="#94A3B8" stroke-width="2" stroke-linecap="round" />
                    </svg>
                </div>

                <!-- Diagonal Pencil icon in Gold -->
                <svg
                    class="absolute bottom-1 right-2 h-7 w-7 text-[#F4B942] drop-shadow-sm"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                >
                    <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z" />
                </svg>
            </div>

            <!-- "No orders yet" label -->
            <p class="text-sm font-extrabold text-[#1F2937]">
                {{ searchQuery ? 'No matching orders found' : 'No orders yet' }}
            </p>

            <p class="mt-1 text-xs text-[#64748B] max-w-sm">
                {{ searchQuery ? 'Try adjusting your search criteria or filter to find what you are looking for.' : 'When you purchase items from our verified marketplace sellers, your orders will appear here.' }}
            </p>

            <div class="mt-4 flex items-center gap-2">
                <Link
                    v-if="searchQuery"
                    :href="route('buyer.orders')"
                    class="rounded-xl border border-[#E5E7EB] bg-white px-3.5 py-1.5 text-xs font-bold text-[#64748B] transition hover:bg-[#F8FAF9]"
                >
                    Clear search filter
                </Link>

                <Link
                    :href="route('buyer.products')"
                    class="rounded-xl bg-[#087F8C] px-4 py-2 text-xs font-extrabold text-white shadow-xs transition hover:bg-[#066B76]"
                >
                    Browse Products
                </Link>
            </div>
        </div>

    </BuyerAccountLayout>
</template>
