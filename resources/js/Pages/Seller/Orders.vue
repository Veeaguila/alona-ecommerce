<!-- Seller order list for fulfillment and customer service. -->
<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'
import { computed, ref, watch } from 'vue'

const props = defineProps({
    orders: {
        type: Object,
        required: true,
    },

    summary: {
        type: Object,
        default: () => ({}),
    },

    filters: {
        type: Object,
        default: () => ({}),
    },
})

const search = ref(props.filters.search ?? '')
const status = ref(props.filters.status ?? 'All Orders')
const dateFrom = ref(props.filters.date_from ?? '')
const dateTo = ref(props.filters.date_to ?? '')

const statuses = [
    'All Orders',
    'Pending',
    'Processing',
    'Packed',
    'Ready for Pickup',
    'Shipped',
    'Delivered',
    'Cancelled',
]

const orderRows = computed(() => props.orders?.data ?? [])

const formatMoney = value => {
    return `₱${Number(value ?? 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`
}

const formatDate = value => {
    return value
        ? new Date(value).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        })
        : '—'
}

const normalizeStatus = value => {
    if (!value) {
        return 'Pending'
    }

    const map = {
        pending: 'Pending',
        processing: 'Processing',
        packed: 'Packed',
        ready_for_pickup: 'Ready for Pickup',
        shipped: 'Shipped',
        delivered: 'Delivered',
        completed: 'Completed',
        cancelled: 'Cancelled',
    }

    return map[String(value).toLowerCase()] ?? String(value)
}

const badgeClass = value => {
    const normalized = String(value ?? '').toLowerCase()

    const map = {
        pending:
            'border-[#FDE68A] bg-[#FFFBEB] text-[#B77900]',
        processing:
            'border-[#BDE9E7] bg-[#E8F7F6] text-[#087F8C]',
        packed:
            'border-[#C7E9EA] bg-[#F0FAFA] text-[#087F8C]',
        ready_for_pickup:
            'border-[#F6D98B] bg-[#FFF8E7] text-[#A66A00]',
        shipped:
            'border-[#D5DDF4] bg-[#F3F6FC] text-[#4B5F96]',
        delivered:
            'border-[#BBE7D3] bg-[#ECFDF5] text-[#16845A]',
        completed:
            'border-[#BBE7D3] bg-[#ECFDF5] text-[#16845A]',
        cancelled:
            'border-[#FECACA] bg-[#FEF2F2] text-[#DC2626]',
    }

    return (
        map[normalized] ??
        'border-[#E5E7EB] bg-[#F8FAF9] text-[#64748B]'
    )
}

const paymentLabel = value => {
    const normalized = String(value ?? '').toLowerCase()

    if (
        normalized.includes('cod') ||
        normalized.includes('cash')
    ) {
        return 'COD'
    }

    if (
        normalized.includes('gcash') ||
        normalized.includes('paymaya') ||
        normalized.includes('card') ||
        normalized.includes('bank')
    ) {
        return 'Paid'
    }

    return 'Paid'
}

const paymentClass = value => {
    return paymentLabel(value) === 'Paid'
        ? 'border-[#BBE7D3] bg-[#ECFDF5] text-[#16845A]'
        : 'border-[#FDE68A] bg-[#FFFBEB] text-[#B77900]'
}

const buyerName = order => {
    return order.order?.user?.name ?? 'Customer'
}

const buyerInitial = order => {
    return buyerName(order)
        .charAt(0)
        .toUpperCase()
}

const productName = order => {
    return (
        order.product_name ??
        order.product?.name ??
        'Product'
    )
}

const orderTotal = order => {
    return (
        order.line_total ??
        Number(order.price ?? 0) *
            Number(order.quantity ?? 0)
    )
}

const orderNumber = order => {
    return (
        order.order?.order_number ??
        `#${order.id}`
    )
}

const buildQuery = () => ({
    search: search.value || undefined,

    status:
        status.value === 'All Orders'
            ? undefined
            : status.value,

    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
})

let debounce = null

watch(
    [search, status, dateFrom, dateTo],
    () => {
        clearTimeout(debounce)

        debounce = setTimeout(() => {
            router.get(
                route('seller.orders'),
                buildQuery(),
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                }
            )
        }, 300)
    }
)
</script>

<template>
    <Head title="Seller Orders" />

    <SellerLayout>
        <div class="min-h-[calc(100vh-68px)] bg-[#F8FAF9]">
            <div
                class="mx-auto w-full max-w-7xl px-4 py-5 sm:px-5 lg:px-6"
            >
                <!-- PAGE HEADER -->
                <div
                    class="flex flex-col gap-4 border-b border-[#E5E7EB] pb-5 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div>
                        <p
                            class="text-xs font-bold uppercase tracking-[0.14em] text-[#087F8C]"
                        >
                            Seller Center
                        </p>

                        <h1
                            class="mt-1 text-2xl font-bold tracking-tight text-[#1F2937] sm:text-3xl"
                        >
                            Orders
                        </h1>

                        <p
                            class="mt-1 text-sm text-[#64748B]"
                        >
                            Manage customer orders and fulfillment.
                        </p>
                    </div>

                    <Link
                        :href="route('seller.dashboard')"
                        class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm font-semibold text-[#64748B] shadow-sm transition hover:border-[#087F8C] hover:text-[#087F8C]"
                    >
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
                                d="M15 19l-7-7 7-7"
                            />
                        </svg>

                        Dashboard
                    </Link>
                </div>

                <!-- SUMMARY -->
                <div
                    class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4"
                >
                    <!-- Total Orders -->
                    <div
                        class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm"
                    >
                        <div
                            class="flex items-start justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <p
                                    class="text-[11px] font-semibold uppercase tracking-wide text-[#94A3B8]"
                                >
                                    Total Orders
                                </p>

                                <p
                                    class="mt-1.5 text-xl font-bold tracking-tight text-[#1F2937] sm:text-2xl"
                                >
                                    {{ summary.total ?? 0 }}
                                </p>

                                <p
                                    class="mt-1 text-[11px] text-[#64748B]"
                                >
                                    All customer orders
                                </p>
                            </div>

                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.7"
                                        d="M4 7h16v12H4zM8 7V5h8v2M8 11h8M8 15h5"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Pending -->
                    <div
                        class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm"
                    >
                        <div
                            class="flex items-start justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <p
                                    class="text-[11px] font-semibold uppercase tracking-wide text-[#94A3B8]"
                                >
                                    Pending
                                </p>

                                <p
                                    class="mt-1.5 text-xl font-bold tracking-tight text-[#B77900] sm:text-2xl"
                                >
                                    {{ summary.pending ?? 0 }}
                                </p>

                                <p
                                    class="mt-1 text-[11px] text-[#64748B]"
                                >
                                    Awaiting processing
                                </p>
                            </div>

                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#FFF8E7] text-[#F4B942]"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        cx="12"
                                        cy="12"
                                        r="8"
                                        stroke-width="1.7"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-width="1.7"
                                        d="M12 8v4l2.5 2"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Processing -->
                    <div
                        class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm"
                    >
                        <div
                            class="flex items-start justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <p
                                    class="text-[11px] font-semibold uppercase tracking-wide text-[#94A3B8]"
                                >
                                    Processing
                                </p>

                                <p
                                    class="mt-1.5 text-xl font-bold tracking-tight text-[#087F8C] sm:text-2xl"
                                >
                                    {{ summary.processing ?? 0 }}
                                </p>

                                <p
                                    class="mt-1 text-[11px] text-[#64748B]"
                                >
                                    Currently being prepared
                                </p>
                            </div>

                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#16A6A0]"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.7"
                                        d="M12 3v3M12 18v3M3 12h3M18 12h3M5.64 5.64l2.12 2.12M16.24 16.24l2.12 2.12M18.36 5.64l-2.12 2.12M7.76 16.24l-2.12 2.12"
                                    />
                                    <circle
                                        cx="12"
                                        cy="12"
                                        r="3"
                                        stroke-width="1.7"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Completed -->
                    <div
                        class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm"
                    >
                        <div
                            class="flex items-start justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <p
                                    class="text-[11px] font-semibold uppercase tracking-wide text-[#94A3B8]"
                                >
                                    Completed
                                </p>

                                <p
                                    class="mt-1.5 text-xl font-bold tracking-tight text-[#22A06B] sm:text-2xl"
                                >
                                    {{ summary.completed ?? 0 }}
                                </p>

                                <p
                                    class="mt-1 text-[11px] text-[#64748B]"
                                >
                                    Successfully fulfilled
                                </p>
                            </div>

                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#ECFDF5] text-[#22A06B]"
                            >
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
                                        d="M5 12.5l4 4L19 7"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FILTERS -->
                <section
                    class="mt-5 rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                >
                    <div
                        class="border-b border-[#E5E7EB] px-5 py-4"
                    >
                        <div
                            class="flex items-center gap-2"
                        >
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]"
                            >
                                <svg
                                    class="h-4 w-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.7"
                                        d="M4 6h16M7 12h10M10 18h4"
                                    />
                                </svg>
                            </div>

                            <div>
                                <h2
                                    class="text-sm font-bold text-[#1F2937]"
                                >
                                    Order Filters
                                </h2>

                                <p
                                    class="text-[11px] text-[#94A3B8]"
                                >
                                    Narrow down orders by status or date.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="p-4">
                        <div
                            class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_180px_170px_170px]"
                        >
                            <!-- Search -->
                            <div class="relative">
                                <svg
                                    class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#94A3B8]"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.8"
                                        d="m21 21-4.35-4.35M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z"
                                    />
                                </svg>

                                <input
                                    v-model="search"
                                    type="text"
                                    placeholder="Search order ID, buyer, or product..."
                                    class="h-10 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] py-2 pl-9 pr-3 text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                />
                            </div>

                            <!-- Status -->
                            <select
                                v-model="status"
                                class="h-10 rounded-xl border border-[#E5E7EB] bg-white px-3 text-sm text-[#64748B] outline-none transition focus:border-[#087F8C] focus:ring-2 focus:ring-[#E8F7F6]"
                            >
                                <option
                                    v-for="item in statuses"
                                    :key="item"
                                    :value="item"
                                >
                                    {{ item }}
                                </option>
                            </select>

                            <!-- Date From -->
                            <div class="relative">
                                <label
                                    class="absolute left-3 top-[-6px] bg-white px-1 text-[9px] font-semibold uppercase tracking-wide text-[#94A3B8]"
                                >
                                    From
                                </label>

                                <input
                                    v-model="dateFrom"
                                    type="date"
                                    class="h-10 w-full rounded-xl border border-[#E5E7EB] bg-white px-3 text-sm text-[#64748B] outline-none transition focus:border-[#087F8C] focus:ring-2 focus:ring-[#E8F7F6]"
                                />
                            </div>

                            <!-- Date To -->
                            <div class="relative">
                                <label
                                    class="absolute left-3 top-[-6px] bg-white px-1 text-[9px] font-semibold uppercase tracking-wide text-[#94A3B8]"
                                >
                                    To
                                </label>

                                <input
                                    v-model="dateTo"
                                    type="date"
                                    class="h-10 w-full rounded-xl border border-[#E5E7EB] bg-white px-3 text-sm text-[#64748B] outline-none transition focus:border-[#087F8C] focus:ring-2 focus:ring-[#E8F7F6]"
                                />
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ORDERS -->
                <section
                    class="mt-5 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                >
                    <!-- Section Header -->
                    <div
                        class="flex items-center justify-between border-b border-[#E5E7EB] px-5 py-4"
                    >
                        <div>
                            <div
                                class="flex items-center gap-2"
                            >
                                <h2
                                    class="text-base font-bold text-[#1F2937]"
                                >
                                    Customer Orders
                                </h2>

                                <span
                                    class="rounded-full bg-[#E8F7F6] px-2 py-0.5 text-[10px] font-bold text-[#087F8C]"
                                >
                                    {{ orderRows.length }}
                                </span>
                            </div>

                            <p
                                class="mt-1 text-xs text-[#94A3B8]"
                            >
                                Recent orders from your customers.
                            </p>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div
                        v-if="!orderRows.length"
                        class="px-6 py-14 text-center"
                    >
                        <div
                            class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-[#E8F7F6] text-[#087F8C]"
                        >
                            <svg
                                class="h-6 w-6"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.7"
                                    d="M4 7h16v12H4zM8 7V5h8v2M8 11h8M8 15h5"
                                />
                            </svg>
                        </div>

                        <h3
                            class="mt-3 text-sm font-bold text-[#1F2937]"
                        >
                            No orders found
                        </h3>

                        <p
                            class="mt-1 text-xs text-[#94A3B8]"
                        >
                            No orders match the current filters.
                        </p>
                    </div>

                    <!-- DESKTOP TABLE -->
                    <div
                        v-else
                        class="hidden overflow-x-auto lg:block"
                    >
                        <table class="w-full">
                            <thead>
                                <tr
                                    class="border-b border-[#E5E7EB] bg-[#F8FAF9] text-left"
                                >
                                    <th
                                        class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-[#94A3B8]"
                                    >
                                        Order
                                    </th>

                                    <th
                                        class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-[#94A3B8]"
                                    >
                                        Buyer
                                    </th>

                                    <th
                                        class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-[#94A3B8]"
                                    >
                                        Product
                                    </th>

                                    <th
                                        class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-[#94A3B8]"
                                    >
                                        Total
                                    </th>

                                    <th
                                        class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-[#94A3B8]"
                                    >
                                        Payment
                                    </th>

                                    <th
                                        class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-[#94A3B8]"
                                    >
                                        Status
                                    </th>

                                    <th
                                        class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-[0.08em] text-[#94A3B8]"
                                    >
                                        Action
                                    </th>
                                </tr>
                            </thead>

                            <tbody
                                class="divide-y divide-[#F1F5F9]"
                            >
                                <tr
                                    v-for="order in orderRows"
                                    :key="order.id"
                                    class="transition hover:bg-[#FAFCFB]"
                                >
                                    <!-- Order -->
                                    <td class="px-5 py-4">
                                        <p
                                            class="text-sm font-bold text-[#087F8C]"
                                        >
                                            {{ orderNumber(order) }}
                                        </p>

                                        <p
                                            class="mt-1 text-[11px] text-[#94A3B8]"
                                        >
                                            {{
                                                formatDate(
                                                    order.created_at ??
                                                    order.order?.created_at
                                                )
                                            }}
                                        </p>
                                    </td>

                                    <!-- Buyer -->
                                    <td class="px-5 py-4">
                                        <div
                                            class="flex items-center gap-2.5"
                                        >
                                            <div
                                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#E8F7F6] text-[11px] font-bold text-[#087F8C]"
                                            >
                                                {{ buyerInitial(order) }}
                                            </div>

                                            <span
                                                class="max-w-[150px] truncate text-sm font-medium text-[#1F2937]"
                                            >
                                                {{ buyerName(order) }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Product -->
                                    <td class="max-w-[220px] px-5 py-4">
                                        <p
                                            class="truncate text-sm font-semibold text-[#1F2937]"
                                        >
                                            {{ productName(order) }}
                                        </p>

                                        <p
                                            class="mt-1 text-[11px] text-[#94A3B8]"
                                        >
                                            Qty: {{ order.quantity }}
                                        </p>
                                    </td>

                                    <!-- Total -->
                                    <td
                                        class="whitespace-nowrap px-5 py-4 text-sm font-bold text-[#1F2937]"
                                    >
                                        {{ formatMoney(orderTotal(order)) }}
                                    </td>

                                    <!-- Payment -->
                                    <td class="px-5 py-4">
                                        <span
                                            :class="[
                                                'inline-flex rounded-full border px-2.5 py-1 text-[10px] font-bold',
                                                paymentClass(
                                                    order.order?.payment_method
                                                ),
                                            ]"
                                        >
                                            {{
                                                paymentLabel(
                                                    order.order?.payment_method
                                                )
                                            }}
                                        </span>
                                    </td>

                                    <!-- Status -->
                                    <td class="px-5 py-4">
                                        <span
                                            :class="[
                                                'inline-flex rounded-full border px-2.5 py-1 text-[10px] font-bold',
                                                badgeClass(order.status),
                                            ]"
                                        >
                                            {{
                                                normalizeStatus(
                                                    order.status
                                                )
                                            }}
                                        </span>
                                    </td>

                                    <!-- Action -->
                                    <td
                                        class="px-5 py-4 text-right"
                                    >
                                        <Link
                                            :href="
                                                route(
                                                    'seller.order.details',
                                                    order.id
                                                )
                                            "
                                            class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-[#E5E7EB] bg-white px-3 text-xs font-semibold text-[#64748B] transition hover:border-[#087F8C] hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                                        >
                                            View

                                            <svg
                                                class="h-3.5 w-3.5"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="1.8"
                                                    d="M9 5l7 7-7 7"
                                                />
                                            </svg>
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- MOBILE -->
                    <div
                        v-if="orderRows.length"
                        class="divide-y divide-[#E5E7EB] lg:hidden"
                    >
                        <div
                            v-for="order in orderRows"
                            :key="order.id"
                            class="p-4"
                        >
                            <!-- Top -->
                            <div
                                class="flex items-start justify-between gap-3"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="text-sm font-bold text-[#087F8C]"
                                    >
                                        {{ orderNumber(order) }}
                                    </p>

                                    <p
                                        class="mt-1 text-[11px] text-[#94A3B8]"
                                    >
                                        {{
                                            formatDate(
                                                order.created_at ??
                                                order.order?.created_at
                                            )
                                        }}
                                    </p>
                                </div>

                                <span
                                    :class="[
                                        'shrink-0 rounded-full border px-2.5 py-1 text-[10px] font-bold',
                                        badgeClass(order.status),
                                    ]"
                                >
                                    {{
                                        normalizeStatus(
                                            order.status
                                        )
                                    }}
                                </span>
                            </div>

                            <!-- Buyer + Total -->
                            <div
                                class="mt-4 grid grid-cols-2 gap-3"
                            >
                                <div>
                                    <p
                                        class="text-[10px] font-bold uppercase tracking-wide text-[#94A3B8]"
                                    >
                                        Buyer
                                    </p>

                                    <div
                                        class="mt-1.5 flex items-center gap-2"
                                    >
                                        <div
                                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#E8F7F6] text-[10px] font-bold text-[#087F8C]"
                                        >
                                            {{ buyerInitial(order) }}
                                        </div>

                                        <p
                                            class="truncate text-sm font-medium text-[#1F2937]"
                                        >
                                            {{ buyerName(order) }}
                                        </p>
                                    </div>
                                </div>

                                <div class="text-right">
                                    <p
                                        class="text-[10px] font-bold uppercase tracking-wide text-[#94A3B8]"
                                    >
                                        Total
                                    </p>

                                    <p
                                        class="mt-1.5 text-sm font-bold text-[#1F2937]"
                                    >
                                        {{ formatMoney(orderTotal(order)) }}
                                    </p>
                                </div>
                            </div>

                            <!-- Product + Payment -->
                            <div
                                class="mt-4 grid grid-cols-[minmax(0,1fr)_auto] gap-3"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="text-[10px] font-bold uppercase tracking-wide text-[#94A3B8]"
                                    >
                                        Product
                                    </p>

                                    <p
                                        class="mt-1 truncate text-sm font-semibold text-[#1F2937]"
                                    >
                                        {{ productName(order) }}
                                    </p>

                                    <p
                                        class="mt-0.5 text-[11px] text-[#94A3B8]"
                                    >
                                        Quantity: {{ order.quantity }}
                                    </p>
                                </div>

                                <div class="text-right">
                                    <p
                                        class="text-[10px] font-bold uppercase tracking-wide text-[#94A3B8]"
                                    >
                                        Payment
                                    </p>

                                    <span
                                        :class="[
                                            'mt-1.5 inline-flex rounded-full border px-2.5 py-1 text-[10px] font-bold',
                                            paymentClass(
                                                order.order?.payment_method
                                            ),
                                        ]"
                                    >
                                        {{
                                            paymentLabel(
                                                order.order?.payment_method
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>

                            <!-- Action -->
                            <div
                                class="mt-4 flex justify-end border-t border-[#F1F5F9] pt-3"
                            >
                                <Link
                                    :href="
                                        route(
                                            'seller.order.details',
                                            order.id
                                        )
                                    "
                                    class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[#087F8C] px-3.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#066B76]"
                                >
                                    View Order

                                    <svg
                                        class="h-3.5 w-3.5"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="1.8"
                                            d="M9 5l7 7-7 7"
                                        />
                                    </svg>
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </SellerLayout>
</template>