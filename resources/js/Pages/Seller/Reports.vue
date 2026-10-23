<script setup>
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
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
            available_balance: 0,
            total_earned: 0,
            pending_balance: 0,
            pending_payout: 0,
            orders: 0,
            units_sold: 0,
            revenue: 0,
            average_order_value: 0,
        }),
    },

    balances: {
        type: Object,
        default: () => ({
            total_earned: 0,
            available_balance: 0,
            pending_balance: 0,
            pending_payout: 0,
            total_withdrawn: 0,
            has_pending_payout: false,
        }),
    },

    payoutRequests: {
        type: Array,
        default: () => [],
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
const dateError = ref('')
const loading = ref(false)

// Payout modal state
const showPayoutModal = ref(false)
const payoutError = ref('')
const payoutForm = useForm({
    amount: '',
    payout_method: 'gcash',
    account_name: '',
    account_number: '',
    bank_name: '',
})

// Order breakdown modal state
const showBreakdownModal = ref(false)
const breakdownLoading = ref(false)
const breakdownData = ref(null)

const peso = (value) => {
    return Number(value || 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}

const formattedDate = (date) => {
    if (!date) return ''
    const parsed = new Date(`${date}T00:00:00`)
    if (Number.isNaN(parsed.getTime())) return date
    return parsed.toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    })
}

const formatDateStr = (date) => {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
}

const setPreset = (preset) => {
    const today = new Date()
    dateError.value = ''

    if (preset === 'today') {
        from.value = formatDateStr(today)
        to.value = formatDateStr(today)
    } else if (preset === '7days') {
        const past = new Date()
        past.setDate(today.getDate() - 6)
        from.value = formatDateStr(past)
        to.value = formatDateStr(today)
    } else if (preset === '30days') {
        const past = new Date()
        past.setDate(today.getDate() - 29)
        from.value = formatDateStr(past)
        to.value = formatDateStr(today)
    }

    generateReport()
}

const generateReport = () => {
    dateError.value = ''

    if (!from.value || !to.value) {
        dateError.value = 'Please select both start and end dates.'
        return
    }

    if (new Date(to.value) < new Date(from.value)) {
        dateError.value = 'The end date cannot be earlier than the start date.'
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
            onError: (errors) => {
                if (errors.to) dateError.value = errors.to
            },
            onFinish: () => {
                loading.value = false
            },
        }
    )
}

const resetRange = () => {
    setPreset('30days')
}

// Request payout submit with double-click guard (SEL-EARN-007, A37)
const submitPayout = () => {
    payoutError.value = ''
    const amt = parseFloat(payoutForm.amount)

    if (isNaN(amt) || amt <= 0) {
        payoutError.value = 'Please enter a valid amount.'
        return
    }

    if (amt < 100) {
        payoutError.value = 'The minimum payout amount is ₱100.00.'
        return
    }

    const available = props.balances?.available_balance ?? props.summary?.available_balance ?? 0
    if (amt > available) {
        payoutError.value = `The payout amount cannot exceed your available balance of ₱${peso(available)}.`
        return
    }

    payoutForm.post(route('seller.payout-requests.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showPayoutModal.value = false
            payoutForm.reset()
        },
        onError: (err) => {
            payoutError.value = err.amount || err.message || 'Failed to submit payout request.'
        },
    })
}

const cancelPayout = (id) => {
    if (!confirm('Are you sure you want to cancel this payout request?')) return

    router.post(route('seller.payout-requests.cancel', id), {}, {
        preserveScroll: true,
    })
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
    if (!props.dailySales.length) return 0
    return Math.max(...props.dailySales.map((item) => Number(item.revenue || 0)))
})
</script>

<template>
    <Head title="Reports & Earnings" />

    <SellerLayout>
        <div class="w-full space-y-6">

                <!-- Page Header -->
                <div class="flex flex-col gap-4 border-b border-[#E5E7EB] pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-[#087F8C]">
                            <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                            <span>Reports</span>
                        </div>

                        <h1 class="mt-1 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                            Sales summary
                        </h1>

                        <p class="mt-0.5 text-xs text-slate-500">
                            Review sales performance, completed orders, products sold, and marketplace activity by date range.
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            @click="showPayoutModal = true"
                            :disabled="balances.has_pending_payout || (balances.available_balance ?? 0) < 100"
                            class="inline-flex items-center gap-2 rounded-xl bg-[#087F8C] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#066D78] disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Request Payout
                        </button>

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
                </div>

                <!-- EARNINGS & BALANCES (SEL-EARN-001, SEL-EARN-002, A15) -->
                <section class="mt-5">
                    <div class="mb-3 flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-bold text-[#1F2937]">
                                Balances & Earnings Overview
                            </h2>
                            <p class="mt-0.5 text-xs text-[#64748B]">
                                Earnings become Available when orders are completed by the buyer.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <!-- Available Balance -->
                        <div class="relative overflow-hidden rounded-2xl border-2 border-[#087F8C]/20 bg-gradient-to-br from-white to-[#F0FAF9] p-5 shadow-sm">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#087F8C]">
                                        Available Balance
                                    </p>
                                    <p class="mt-2 text-2xl font-black text-[#1F2937]">
                                        ₱{{ peso(balances.available_balance) }}
                                    </p>
                                </div>
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                    </svg>
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-[#64748B]">
                                Ready for payout withdrawal (min ₱100.00).
                            </p>
                        </div>

                        <!-- Pending Balance -->
                        <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-sm">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#B77900]">
                                        Pending Balance
                                    </p>
                                    <p class="mt-2 text-2xl font-black text-[#1F2937]">
                                        ₱{{ peso(balances.pending_balance) }}
                                    </p>
                                </div>
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#FFF8E7] text-[#B77900]">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-[#64748B]">
                                Orders delivered or in transit awaiting buyer completion.
                            </p>
                        </div>

                        <!-- Pending Payout -->
                        <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-sm">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#64748B]">
                                        Pending Payouts
                                    </p>
                                    <p class="mt-2 text-2xl font-black text-[#1F2937]">
                                        ₱{{ peso(balances.pending_payout) }}
                                    </p>
                                </div>
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gray-100 text-[#475569]">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-[#64748B]">
                                {{ balances.has_pending_payout ? 'A payout is currently processing.' : 'No active payout requests.' }}
                            </p>
                        </div>

                        <!-- Total Withdrawn -->
                        <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-sm">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#16845A]">
                                        Total Withdrawn
                                    </p>
                                    <p class="mt-2 text-2xl font-black text-[#16845A]">
                                        ₱{{ peso(balances.total_withdrawn) }}
                                    </p>
                                </div>
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#ECFDF5] text-[#16845A]">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-[#64748B]">
                                Lifetime successfully completed payouts.
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Date Range Filter & Presets (SEL-EARN-004) -->
                <section class="mt-5 rounded-2xl border border-[#E5E7EB] bg-white shadow-sm">
                    <div class="border-b border-[#E5E7EB] px-5 py-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z" />
                                    </svg>
                                </div>

                                <div>
                                    <h2 class="text-base font-bold text-[#1F2937]">
                                        Report Period Filter
                                    </h2>
                                    <p class="mt-0.5 text-xs text-[#64748B]">
                                        Filter sales and earnings by today, last 7 days, or a custom range.
                                    </p>
                                </div>
                            </div>

                            <!-- Preset quick buttons -->
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="setPreset('today')"
                                    class="rounded-lg border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-1.5 text-xs font-semibold text-[#475569] transition hover:border-[#087F8C] hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                                >
                                    Today
                                </button>
                                <button
                                    type="button"
                                    @click="setPreset('7days')"
                                    class="rounded-lg border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-1.5 text-xs font-semibold text-[#475569] transition hover:border-[#087F8C] hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                                >
                                    Last 7 Days
                                </button>
                                <button
                                    type="button"
                                    @click="setPreset('30days')"
                                    class="rounded-lg border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-1.5 text-xs font-semibold text-[#475569] transition hover:border-[#087F8C] hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                                >
                                    Last 30 Days
                                </button>
                            </div>
                        </div>
                    </div>

                    <form
                        class="p-5"
                        @submit.prevent="generateReport"
                    >
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                            <div>
                                <label for="from" class="mb-1.5 block text-[11px] font-semibold text-[#475569]">
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
                                <label for="to" class="mb-1.5 block text-[11px] font-semibold text-[#475569]">
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
                                    <svg v-if="loading" class="h-4 w-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v2m6.364.636l-1.414 1.414M21 12h-2m-.636 6.364l-1.414-1.414M12 21v-2m-6.364-.636l1.414-1.414M3 12h2m.636-6.364l1.414 1.414" />
                                    </svg>
                                    <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V7a2 2 0 012-2h10a2 2 0 012 2v10a2 2 0 01-2 2z" />
                                    </svg>
                                    {{ loading ? 'Generating...' : 'Apply Filter' }}
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
                        </div>

                        <!-- Validation error display -->
                        <p v-if="dateError" class="mt-2 text-xs font-semibold text-red-600">
                            {{ dateError }}
                        </p>
                    </form>
                </section>

                <!-- Period Financial Summary (SEL-EARN-001, SEL-EARN-003, A8, A9) -->
                <section class="mt-5">
                    <div class="mb-3">
                        <h2 class="text-base font-bold text-[#1F2937]">
                            Financial Summary (Selected Period)
                        </h2>
                        <p class="mt-0.5 text-xs text-[#64748B]">
                            Calculations exclude cancelled and refunded orders (SEL-EARN-003).
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
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m0-2c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                            </div>
                            <p class="mt-2 text-[11px] text-[#94A3B8]">
                                Completed sales in period
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
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 14l6-6m2-4H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 002-2z" />
                                    </svg>
                                </div>
                            </div>
                            <p class="mt-2 text-[11px] text-[#94A3B8]">
                                Marketplace commission: 5% (A9)
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
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m0-2c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                            </div>
                            <p class="mt-2 text-[11px] text-[#94A3B8]">
                                After seller voucher & commission
                            </p>
                        </div>

                        <!-- Pending in period -->
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
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                            </div>
                            <p class="mt-2 text-[11px] text-[#94A3B8]">
                                Sales awaiting buyer receipt
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
                            Completed orders, units sold, and average order value.
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <div class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm">
                            <p class="text-[11px] font-semibold text-[#64748B]">Completed Orders</p>
                            <p class="mt-2 text-2xl font-bold text-[#1F2937]">{{ summary.orders ?? 0 }}</p>
                        </div>
                        <div class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm">
                            <p class="text-[11px] font-semibold text-[#64748B]">Units Sold</p>
                            <p class="mt-2 text-2xl font-bold text-[#1F2937]">{{ summary.units_sold ?? 0 }}</p>
                        </div>
                        <div class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm">
                            <p class="text-[11px] font-semibold text-[#64748B]">Revenue</p>
                            <p class="mt-2 text-2xl font-bold text-[#1F2937]">₱{{ peso(summary.revenue) }}</p>
                        </div>
                        <div class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm">
                            <p class="text-[11px] font-semibold text-[#64748B]">Average Order Value</p>
                            <p class="mt-2 text-2xl font-bold text-[#1F2937]">₱{{ peso(summary.average_order_value) }}</p>
                        </div>
                    </div>
                </section>

                <!-- Payout Requests History (SEL-EARN-005, SEL-EARN-006) -->
                <section class="mt-5 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm">
                    <div class="border-b border-[#E5E7EB] px-5 py-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-base font-bold text-[#1F2937]">
                                    Payout Requests History
                                </h2>
                                <p class="mt-0.5 text-xs text-[#64748B]">
                                    Track status of your requested payout withdrawals.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div v-if="payoutRequests.length" class="overflow-x-auto">
                        <table class="w-full min-w-[700px]">
                            <thead>
                                <tr class="border-b border-[#E5E7EB] bg-[#F8FAF9] text-left">
                                    <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">Reference</th>
                                    <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">Amount</th>
                                    <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">Method</th>
                                    <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">Account</th>
                                    <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">Status</th>
                                    <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">Date</th>
                                    <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="req in payoutRequests" :key="req.id" class="border-b border-[#F1F5F9] last:border-0">
                                    <td class="px-5 py-3.5 text-xs font-semibold text-[#1F2937]">{{ req.payout_reference }}</td>
                                    <td class="px-5 py-3.5 text-xs font-bold text-[#087F8C]">₱{{ peso(req.amount) }}</td>
                                    <td class="px-5 py-3.5 text-xs uppercase font-medium text-[#475569]">{{ req.payout_method }}</td>
                                    <td class="px-5 py-3.5 text-xs text-[#64748B]">{{ req.account_name }} ({{ req.account_number }})</td>
                                    <td class="px-5 py-3.5">
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                                            :class="{
                                                'bg-[#FFF8E7] text-[#B77900]': req.status === 'pending' || req.status === 'processing',
                                                'bg-[#ECFDF5] text-[#16845A]': req.status === 'approved' || req.status === 'completed',
                                                'bg-red-50 text-red-700': req.status === 'rejected' || req.status === 'cancelled',
                                            }"
                                        >
                                            {{ req.status }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-xs text-[#64748B]">{{ req.created_at }}</td>
                                    <td class="px-5 py-3.5 text-right">
                                        <button
                                            v-if="req.status === 'pending'"
                                            type="button"
                                            @click="cancelPayout(req.id)"
                                            class="text-xs font-semibold text-red-600 hover:text-red-800"
                                        >
                                            Cancel
                                        </button>
                                        <span v-else class="text-xs text-[#94A3B8]">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="px-5 py-8 text-center text-xs text-[#94A3B8]">
                        No payout requests submitted yet.
                    </div>
                </section>

                <!-- Top Products + Daily Sales -->
                <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-3">
                    <section class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm lg:col-span-2">
                        <div class="border-b border-[#E5E7EB] px-5 py-4">
                            <h2 class="text-base font-bold text-[#1F2937]">Top-Selling Products</h2>
                            <p class="mt-0.5 text-xs text-[#64748B]">Best-performing products for this period.</p>
                        </div>
                        <div v-if="topProducts.length" class="overflow-x-auto">
                            <table class="w-full min-w-[600px]">
                                <thead>
                                    <tr class="border-b border-[#E5E7EB] bg-[#F8FAF9] text-left">
                                        <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">#</th>
                                        <th class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">Product</th>
                                        <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">Units</th>
                                        <th class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(product, index) in topProducts" :key="product.product_id" class="border-b border-[#F1F5F9] last:border-0">
                                        <td class="px-5 py-3.5 text-xs font-bold text-[#94A3B8]">{{ index + 1 }}</td>
                                        <td class="px-5 py-3.5 text-xs font-semibold text-[#1F2937]">{{ product.name }}</td>
                                        <td class="px-5 py-3.5 text-right text-xs font-medium text-[#475569]">{{ product.units_sold }}</td>
                                        <td class="px-5 py-3.5 text-right text-xs font-bold text-[#1F2937]">₱{{ peso(product.revenue) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="px-5 py-8 text-center text-xs text-[#94A3B8]">
                            No completed sales found in this period.
                        </div>
                    </section>

                    <section class="rounded-2xl border border-[#E5E7EB] bg-white shadow-sm">
                        <div class="border-b border-[#E5E7EB] px-5 py-4">
                            <h2 class="text-base font-bold text-[#1F2937]">Period Summary</h2>
                            <p class="mt-0.5 text-xs text-[#64748B]">Aggregated metrics for selected period.</p>
                        </div>
                        <div class="space-y-0 px-5">
                            <div class="border-b border-[#F1F5F9] py-4">
                                <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">Report From</p>
                                <p class="mt-1 text-sm font-semibold text-[#1F2937]">{{ formattedDate(range.from) }}</p>
                            </div>
                            <div class="border-b border-[#F1F5F9] py-4">
                                <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">Report To</p>
                                <p class="mt-1 text-sm font-semibold text-[#1F2937]">{{ formattedDate(range.to) }}</p>
                            </div>
                            <div class="border-b border-[#F1F5F9] py-4">
                                <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">Daily Revenue</p>
                                <p class="mt-1 text-lg font-bold text-[#087F8C]">₱{{ peso(totalDailyRevenue) }}</p>
                            </div>
                            <div class="py-4">
                                <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">Daily Units Sold</p>
                                <p class="mt-1 text-lg font-bold text-[#1F2937]">{{ totalDailyUnits }}</p>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="h-8"></div>
            </div>

        <!-- REQUEST PAYOUT MODAL (SEL-EARN-005, SEL-EARN-006, SEL-EARN-007, A15, A37) -->
        <div v-if="showPayoutModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <div class="flex items-center justify-between border-b border-[#E5E7EB] pb-3">
                    <h3 class="text-lg font-bold text-[#1F2937]">Request Payout</h3>
                    <button
                        type="button"
                        @click="showPayoutModal = false"
                        class="text-gray-400 hover:text-gray-600"
                    >
                        ✕
                    </button>
                </div>

                <form @submit.prevent="submitPayout" class="mt-4 space-y-4">
                    <div class="rounded-xl bg-[#F0FAF9] p-3 text-sm text-[#087F8C]">
                        <p class="font-bold">Available Balance: ₱{{ peso(balances.available_balance) }}</p>
                        <p class="text-xs text-[#64748B] mt-0.5">Minimum payout: ₱100.00</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#475569]">Payout Amount (₱)</label>
                        <input
                            v-model="payoutForm.amount"
                            type="number"
                            step="0.01"
                            min="100"
                            placeholder="e.g. 100.00"
                            class="mt-1 w-full rounded-xl border border-[#E5E7EB] px-3 py-2 text-sm outline-none focus:border-[#087F8C]"
                            required
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#475569]">Payout Method</label>
                        <select
                            v-model="payoutForm.payout_method"
                            class="mt-1 w-full rounded-xl border border-[#E5E7EB] px-3 py-2 text-sm outline-none focus:border-[#087F8C]"
                        >
                            <option value="gcash">GCash</option>
                            <option value="maya">Maya</option>
                            <option value="bank">Bank Transfer</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#475569]">Account Name</label>
                        <input
                            v-model="payoutForm.account_name"
                            type="text"
                            placeholder="Full name as shown in account"
                            class="mt-1 w-full rounded-xl border border-[#E5E7EB] px-3 py-2 text-sm outline-none focus:border-[#087F8C]"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#475569]">Account Number / Mobile</label>
                        <input
                            v-model="payoutForm.account_number"
                            type="text"
                            placeholder="09XXXXXXXXX or bank account number"
                            class="mt-1 w-full rounded-xl border border-[#E5E7EB] px-3 py-2 text-sm outline-none focus:border-[#087F8C]"
                        />
                    </div>

                    <p v-if="payoutError" class="text-xs font-semibold text-red-600">
                        {{ payoutError }}
                    </p>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button
                            type="button"
                            @click="showPayoutModal = false"
                            class="rounded-xl border border-[#E5E7EB] px-4 py-2 text-sm font-semibold text-[#64748B] hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="payoutForm.processing"
                            class="rounded-xl bg-[#087F8C] px-5 py-2 text-sm font-semibold text-white hover:bg-[#066D78] disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{ payoutForm.processing ? 'Submitting...' : 'Confirm Request' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </SellerLayout>
</template>
