<!-- Buyer order history populated from database orders. -->
<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'

const props = defineProps({
    orders: {
        type: [Array, Object],
        default: () => [],
    },
    status: {
        type: String,
        default: '',
    },
})

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
    if (!value) {
        return 'Date unavailable'
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return 'Date unavailable'
    }

    return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}

const formatPaymentMethod = value => {
    if (!value) {
        return 'Payment method unavailable'
    }

    const methods = {
        cod: 'Cash on delivery',
        cash_on_delivery: 'Cash on delivery',
    }

    return methods[value] || value
}

const normalizeStatus = value => {
    return String(value ?? '').toLowerCase()
}

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
        failed: {
            label: 'Payment Failed',
            color: 'bg-[#FDECEC] text-[#B94242] ring-1 ring-[#E85D5D]/20',
        },
    }

    return badges[status] || {
        label: value || 'Unknown',
        color: 'bg-[#F8FAF9] text-[#64748B] ring-1 ring-[#E5E7EB]',
    }
}

const getItemTotal = item => {
    return Number(item?.price ?? 0) * Number(item?.quantity ?? 0)
}

const orderList = computed(() => {
    if (Array.isArray(props.orders)) {
        return props.orders
    }

    if (props.orders?.data && Array.isArray(props.orders.data)) {
        return props.orders.data
    }

    return []
})
</script>

<template>
    <Head title="My Orders" />

    <BuyerLayout>
        <main class="min-h-[calc(100vh-80px)] w-full min-w-0 overflow-x-hidden bg-[#F8FAF9]">
            <div class="mx-auto w-full max-w-6xl px-3 py-4 pb-8 sm:px-5 sm:py-5 lg:px-6 lg:py-6 lg:pb-10">

                <!-- PAGE HEADER -->
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#F4B942]"></span>
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#087F8C] sm:text-xs">
                                Order history
                            </p>
                        </div>

                        <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-[#1F2937] sm:text-3xl">
                            My Orders
                        </h1>

                        <p class="mt-1 text-xs leading-5 text-[#64748B] sm:text-sm">
                            Track your purchases and view your order details.
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
                    <svg
                        class="mt-0.5 h-4 w-4 shrink-0"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M5 12l4 4L19 6"
                        />
                    </svg>
                    <span>{{ status }}</span>
                </div>

                <!-- ORDERS -->
                <div
                    v-if="orderList.length"
                    class="mt-5 space-y-3 sm:mt-6"
                >
                    <Link
                        v-for="order in orderList"
                        :key="order.id"
                        :href="route('buyer.orders.show', order.id)"
                        class="group block rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-[0_6px_22px_rgba(15,23,42,0.04)] transition hover:border-[#CFE5E4] hover:shadow-[0_10px_30px_rgba(15,23,42,0.07)] sm:p-5"
                    >
                        <!-- ORDER HEADER -->
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex min-w-0 items-center gap-2">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]">
                                        <svg
                                            class="h-4 w-4"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.7"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M6 3h12l2 5H4l2-5zm-2 5h16v11a2 2 0 01-2 2H6a2 2 0 01-2-2V8zm4 4h8"
                                            />
                                        </svg>
                                    </div>

                                    <p class="truncate text-sm font-extrabold text-[#1F2937] sm:text-base">
                                        {{ order.order_number || `Order #${order.id}` }}
                                    </p>
                                </div>

                                <p class="mt-1.5 pl-10 text-[10px] text-[#94A3B8] sm:text-xs">
                                    {{ formatDate(order.created_at) }}
                                    <span class="mx-1">·</span>
                                    {{ formatPaymentMethod(order.payment_method) }}
                                </p>
                            </div>

                            <span
                                :class="[
                                    'shrink-0 rounded-full px-2.5 py-1 text-[9px] font-extrabold sm:px-3 sm:py-1.5 sm:text-[10px]',
                                    getStatusBadge(order.status).color
                                ]"
                            >
                                {{ getStatusBadge(order.status).label }}
                            </span>
                        </div>

                        <!-- ORDER ITEMS -->
                        <div
                            v-if="order.items?.length"
                            class="mt-4 border-t border-[#EEF1F2] pt-3.5"
                        >
                            <div class="space-y-2.5">
                                <div
                                    v-for="item in order.items"
                                    :key="item.id"
                                    class="flex min-w-0 items-center justify-between gap-3 rounded-xl bg-[#FCFDFC] px-3 py-2.5"
                                >
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-xs font-bold text-[#334155] sm:text-sm">
                                            {{ item.product_name || 'Product' }}
                                            <span
                                                v-if="item.variant_label"
                                                class="font-medium text-[#94A3B8]"
                                            >
                                                ({{ item.variant_label }})
                                            </span>
                                        </p>

                                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                            <span class="text-[10px] text-[#64748B]">
                                                {{ money(item.price) }} × {{ item.quantity || 0 }}
                                            </span>

                                            <span class="text-[#CBD5E1]">·</span>

                                            <span
                                                :class="[
                                                    'rounded-full px-2 py-0.5 text-[8px] font-extrabold sm:text-[9px]',
                                                    getStatusBadge(item.status || order.status).color
                                                ]"
                                            >
                                                {{ getStatusBadge(item.status || order.status).label }}
                                            </span>
                                        </div>
                                    </div>

                                    <p class="shrink-0 text-xs font-extrabold text-[#1F2937] sm:text-sm">
                                        {{ money(getItemTotal(item)) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- NO ITEMS -->
                        <div
                            v-else
                            class="mt-4 border-t border-[#EEF1F2] pt-3.5"
                        >
                            <p class="text-xs text-[#94A3B8]">
                                Order items are unavailable.
                            </p>
                        </div>

                        <!-- ORDER TOTAL / VIEW -->
                        <div class="mt-3.5 flex items-center justify-between border-t border-[#EEF1F2] pt-3">
                            <span class="text-[10px] font-semibold uppercase tracking-[0.1em] text-[#94A3B8] sm:text-xs">
                                Order total
                            </span>

                            <div class="flex items-center gap-2">
                                <span class="text-base font-extrabold text-[#087F8C] sm:text-lg">
                                    {{ money(order.total) }}
                                </span>

                                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#F8FAF9] text-[#94A3B8] transition group-hover:bg-[#E8F7F6] group-hover:text-[#087F8C]">
                                    →
                                </span>
                            </div>
                        </div>
                    </Link>
                </div>

                <!-- EMPTY STATE -->
                <div
                    v-else
                    class="mt-5 rounded-2xl border border-dashed border-[#D7E0E2] bg-white px-5 py-14 text-center shadow-[0_6px_22px_rgba(15,23,42,0.03)] sm:mt-6 sm:py-16"
                >
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-[#E8F7F6] text-[#087F8C]">
                        <svg
                            class="h-7 w-7"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M6 3h12l2 5H4l2-5zm-2 5h16v11a2 2 0 01-2 2H6a2 2 0 01-2-2V8zm4 4h8"
                            />
                        </svg>
                    </div>

                    <p class="mt-4 text-sm font-extrabold text-[#1F2937]">
                        No orders yet.
                    </p>

                    <p class="mx-auto mt-1.5 max-w-sm text-xs leading-5 text-[#64748B] sm:text-sm">
                        Start shopping to place your first order and see it here.
                    </p>

                    <Link
                        :href="route('buyer.products')"
                        class="mt-5 inline-flex items-center gap-2 rounded-xl bg-[#087F8C] px-4 py-2.5 text-xs font-extrabold text-white transition hover:bg-[#066B76] sm:text-sm"
                    >
                        Browse Products
                        <span>→</span>
                    </Link>
                </div>

                <!-- SUBTLE FOOTER NOTE -->
                <p
                    v-if="orderList.length"
                    class="mt-4 text-center text-[9px] text-[#A0ACB8] sm:text-[10px]"
                >
                    Select an order to view its full details.
                </p>
            </div>
        </main>
    </BuyerLayout>
</template>
