<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'

const props = defineProps({
    returnRequests: {
        type: Object,
        default: () => ({ data: [] }),
    },
    refundLedger: {
        type: Object,
        default: () => ({ data: [] }),
    },
    statusCounts: {
        type: Object,
        default: () => ({
            all: 0,
            pending: 0,
            approved: 0,
            rejected: 0,
            item_returned: 0,
            refunded: 0,
        }),
    },
    ledgerStats: {
        type: Object,
        default: () => ({
            total_refunded_amount: 0,
            total_refunds_count: 0,
            avg_resolution_hours: 0,
            return_rate_percentage: 0,
        }),
    },
    activeTab: {
        type: String,
        default: 'returns',
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    storeAddress: {
        type: String,
        default: '',
    },
})

// Current state
const currentTab = ref(props.filters.tab || 'returns')
const selectedStatus = ref(props.filters.status || 'all')
const selectedType = ref(props.filters.type || 'all')
const searchQuery = ref(props.filters.search || '')
const selectedSort = ref(props.filters.sort || 'latest')

// Modals
const showDecisionModal = ref(false)
const selectedRequestForDecision = ref(null)
const decisionType = ref('approved')

const showTrackingModal = ref(false)
const selectedRequestForTracking = ref(null)

const showMilestonesModal = ref(false)
const selectedRequestForMilestones = ref(null)

const showInspectionModal = ref(false)
const selectedRequestForInspection = ref(null)

const showLightbox = ref(false)
const lightboxImages = ref([])
const activeLightboxIndex = ref(0)

// Decision Form (SELLER-38)
const decisionForm = useForm({
    decision: 'approved',
    return_instructions: 'Please pack the item securely in its original packaging and include all accessories.',
    return_warehouse_address: props.storeAddress || '',
    rejection_reason: '',
    seller_response: '',
    refund_now: false,
})

// Tracking Form (SELLER-39)
const trackingForm = useForm({
    return_carrier: 'J&T Express',
    return_tracking_number: '',
    return_waybill_url: '',
    return_shipping_status: 'in_transit',
    milestone_title: '',
    milestone_location: '',
    milestone_notes: '',
})

// Inspection & Resolution Form (SELLER-40)
const inspectionForm = useForm({
    inspection_status: 'passed',
    inspection_notes: '',
    refund_amount: 0,
    refund_method: 'original_payment',
    restock_inventory: true,
})

// Filter application
const applyFilters = (overrides = {}) => {
    const params = {
        tab: currentTab.value,
        status: selectedStatus.value,
        type: selectedType.value,
        search: searchQuery.value || undefined,
        sort: selectedSort.value,
        ...overrides,
    }

    router.get(route('seller.returns'), params, {
        preserveState: true,
        preserveScroll: true,
    })
}

const switchTab = (tab) => {
    currentTab.value = tab
    applyFilters({ tab, page: 1 })
}

const filterStatus = (status) => {
    selectedStatus.value = status
    applyFilters({ status, page: 1 })
}

const clearFilters = () => {
    selectedStatus.value = 'all'
    selectedType.value = 'all'
    searchQuery.value = ''
    selectedSort.value = 'latest'
    applyFilters({ status: 'all', type: 'all', search: undefined, sort: 'latest', page: 1 })
}

// Modal open helpers
const openDecisionModal = (req, type) => {
    selectedRequestForDecision.value = req
    decisionType.value = type
    decisionForm.reset()
    decisionForm.decision = type
    decisionForm.return_warehouse_address = props.storeAddress || ''
    decisionForm.return_instructions = 'Please pack the item securely in its original packaging and include all accessories.'
    decisionForm.rejection_reason = type === 'rejected' ? 'item_used' : ''
    decisionForm.seller_response = ''
    decisionForm.refund_now = false
    decisionForm.clearErrors()
    showDecisionModal.value = true
}

const closeDecisionModal = () => {
    showDecisionModal.value = false
    selectedRequestForDecision.value = null
    decisionForm.reset()
}

const submitDecision = () => {
    if (!selectedRequestForDecision.value) return
    decisionForm.post(route('seller.returns.decision', selectedRequestForDecision.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeDecisionModal()
        },
    })
}

const openTrackingModal = (req) => {
    selectedRequestForTracking.value = req
    trackingForm.reset()
    trackingForm.return_carrier = req.return_carrier || 'J&T Express'
    trackingForm.return_tracking_number = req.return_tracking_number || ''
    trackingForm.return_waybill_url = req.return_waybill_url || ''
    trackingForm.return_shipping_status = req.return_shipping_status || 'in_transit'
    trackingForm.milestone_title = ''
    trackingForm.milestone_location = ''
    trackingForm.milestone_notes = ''
    trackingForm.clearErrors()
    showTrackingModal.value = true
}

const closeTrackingModal = () => {
    showTrackingModal.value = false
    selectedRequestForTracking.value = null
    trackingForm.reset()
}

const submitTracking = () => {
    if (!selectedRequestForTracking.value) return
    trackingForm.post(route('seller.returns.tracking', selectedRequestForTracking.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeTrackingModal()
        },
    })
}

const openMilestonesModal = (req) => {
    selectedRequestForMilestones.value = req
    showMilestonesModal.value = true
}

const closeMilestonesModal = () => {
    showMilestonesModal.value = false
    selectedRequestForMilestones.value = null
}

const openInspectionModal = (req) => {
    selectedRequestForInspection.value = req
    inspectionForm.reset()
    inspectionForm.inspection_status = 'passed'
    inspectionForm.inspection_notes = 'Item inspected in good condition. Meets return criteria.'
    inspectionForm.refund_amount = Number(req.requested_amount || 0)
    inspectionForm.refund_method = 'original_payment'
    inspectionForm.restock_inventory = true
    inspectionForm.clearErrors()
    showInspectionModal.value = true
}

const closeInspectionModal = () => {
    showInspectionModal.value = false
    selectedRequestForInspection.value = null
    inspectionForm.reset()
}

const submitInspection = () => {
    if (!selectedRequestForInspection.value) return
    inspectionForm.post(route('seller.returns.inspect-resolve', selectedRequestForInspection.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeInspectionModal()
        },
    })
}

// Lightbox for evidence images
const openLightbox = (images, index = 0) => {
    lightboxImages.value = images || []
    activeLightboxIndex.value = index
    showLightbox.value = true
}

const closeLightbox = () => {
    showLightbox.value = false
    lightboxImages.value = []
}

// Helpers
const formatCurrency = (val) => {
    return '₱' + Number(val || 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}

const formatDate = (dateStr) => {
    if (!dateStr) return 'N/A'
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

const formatShortDate = (dateStr) => {
    if (!dateStr) return 'N/A'
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    })
}

const getStatusBadge = (status) => {
    const map = {
        pending: {
            label: 'Pending Review',
            class: 'bg-amber-50 text-amber-800 border-amber-200',
            dot: 'bg-amber-500',
        },
        approved: {
            label: 'Return Authorized',
            class: 'bg-blue-50 text-blue-800 border-blue-200',
            dot: 'bg-blue-500',
        },
        item_returned: {
            label: 'Item Returned / In Inspection',
            class: 'bg-purple-50 text-purple-800 border-purple-200',
            dot: 'bg-purple-500',
        },
        refunded: {
            label: 'Refund Issued',
            class: 'bg-emerald-50 text-emerald-800 border-emerald-200',
            dot: 'bg-emerald-500',
        },
        completed: {
            label: 'Completed',
            class: 'bg-emerald-50 text-emerald-800 border-emerald-200',
            dot: 'bg-emerald-500',
        },
        rejected: {
            label: 'Rejected',
            class: 'bg-rose-50 text-rose-800 border-rose-200',
            dot: 'bg-rose-500',
        },
        cancelled: {
            label: 'Cancelled by Buyer',
            class: 'bg-slate-50 text-slate-700 border-slate-200',
            dot: 'bg-slate-400',
        },
    }
    return map[status] || {
        label: status,
        class: 'bg-slate-50 text-slate-700 border-slate-200',
        dot: 'bg-slate-400',
    }
}

const getShippingStatusBadge = (status) => {
    const map = {
        pending_shipment: { label: 'Awaiting Drop-off', class: 'bg-amber-50 text-amber-700 border-amber-200' },
        in_transit: { label: 'In Transit', class: 'bg-indigo-50 text-indigo-700 border-indigo-200' },
        out_for_delivery: { label: 'Out for Delivery', class: 'bg-blue-50 text-blue-700 border-blue-200' },
        delivered: { label: 'Delivered to Seller', class: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    }
    return map[status] || { label: status || 'Not Shipped', class: 'bg-slate-50 text-slate-600 border-slate-200' }
}

const rejectionReasons = [
    { value: 'item_used', label: 'Item has been heavily used or altered' },
    { value: 'missing_packaging', label: 'Missing original packaging, tags, or parts' },
    { value: 'past_policy_window', label: 'Past the eligible store return window' },
    { value: 'not_defective', label: 'Product tested working / not defective' },
    { value: 'buyer_error', label: 'Buyer selected wrong variation/size' },
    { value: 'fraudulent_claim', label: 'Suspected fraudulent return / claim' },
    { value: 'other', label: 'Other store policy violation' },
]

const carrierOptions = [
    'J&T Express',
    'Flash Express',
    'Ninja Van',
    'Lalamove',
    'Shopee Xpress',
    'Store Pickup Fleet',
    'Other Courier',
]

const hasActiveFilters = computed(() => {
    return (
        selectedStatus.value !== 'all' ||
        selectedType.value !== 'all' ||
        searchQuery.value !== '' ||
        selectedSort.value !== 'latest'
    )
})
</script>

<template>
    <Head title="Returns & Refunds Center - Seller Center" />

    <SellerLayout>
        <div class="mx-auto max-w-7xl space-y-6">

            <!-- PAGE HEADER & NAVIGATION TAB SWITCHER -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-5">
                <div>
                    <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-[#087F8C]">
                        <span>Order Fulfillment</span>
                        <span>•</span>
                        <span>Customer Disputes</span>
                    </div>
                    <h1 class="mt-1 text-2xl font-black text-slate-900 tracking-tight">
                        Returns & Refunds Management
                    </h1>
                    <p class="mt-1 text-xs text-slate-500">
                        Process customer return claims, track return parcel milestones, inspect returned items, and review financial refund ledgers.
                    </p>
                </div>

                <!-- TABS (Returns Requests vs Refund History Ledger) -->
                <div class="flex items-center rounded-2xl bg-slate-100 p-1.5 shadow-inner">
                    <button
                        type="button"
                        @click="switchTab('returns')"
                        class="flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition"
                        :class="currentTab === 'returns' ? 'bg-white text-[#087F8C] shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                    >
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 15v-1a4 4 0 00-4-4H4m0 0l4 4m-4-4l4-4m12 5v2a3 3 0 01-3 3H9" />
                        </svg>
                        <span>Return Requests</span>
                        <span
                            class="rounded-full px-2 py-0.5 text-[10px]"
                            :class="currentTab === 'returns' ? 'bg-[#E8F7F6] text-[#087F8C]' : 'bg-slate-200 text-slate-700'"
                        >
                            {{ props.statusCounts.all || 0 }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="switchTab('ledger')"
                        class="flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition"
                        :class="currentTab === 'ledger' ? 'bg-white text-[#087F8C] shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                    >
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Refund History Ledger</span>
                        <span
                            class="rounded-full px-2 py-0.5 text-[10px]"
                            :class="currentTab === 'ledger' ? 'bg-[#E8F7F6] text-[#087F8C]' : 'bg-slate-200 text-slate-700'"
                        >
                            {{ props.ledgerStats.total_refunds_count || 0 }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- SELLER-41: KPI SUMMARY CARDS -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                    <div class="flex items-center justify-between text-xs text-slate-500 font-medium">
                        <span>Total Refunded</span>
                        <svg class="h-4 w-4 text-rose-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <p class="mt-2 text-xl font-black text-slate-900 tracking-tight sm:text-2xl">
                        {{ formatCurrency(props.ledgerStats.total_refunded_amount) }}
                    </p>
                    <p class="mt-1 text-[11px] text-slate-400">Total seller discount & item refunds</p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                    <div class="flex items-center justify-between text-xs text-slate-500 font-medium">
                        <span>Resolved Claims</span>
                        <svg class="h-4 w-4 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <p class="mt-2 text-xl font-black text-slate-900 tracking-tight sm:text-2xl">
                        {{ props.ledgerStats.total_refunds_count }}
                    </p>
                    <p class="mt-1 text-[11px] text-emerald-600 font-semibold">Completed refund payouts</p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                    <div class="flex items-center justify-between text-xs text-slate-500 font-medium">
                        <span>Avg Resolution Time</span>
                        <svg class="h-4 w-4 text-indigo-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <p class="mt-2 text-xl font-black text-slate-900 tracking-tight sm:text-2xl">
                        {{ props.ledgerStats.avg_resolution_hours > 0 ? `${props.ledgerStats.avg_resolution_hours} hrs` : '< 24 hrs' }}
                    </p>
                    <p class="mt-1 text-[11px] text-slate-400">Claim submission to payout</p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                    <div class="flex items-center justify-between text-xs text-slate-500 font-medium">
                        <span>Store Return Rate</span>
                        <svg class="h-4 w-4 text-amber-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </div>
                    <p class="mt-2 text-xl font-black text-slate-900 tracking-tight sm:text-2xl">
                        {{ props.ledgerStats.return_rate_percentage }}%
                    </p>
                    <p class="mt-1 text-[11px] text-slate-400">Returns vs total items delivered</p>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- TAB 1: RETURN REQUESTS LIST & PIPELINE                    -->
            <!-- ========================================================= -->
            <div v-if="currentTab === 'returns'" class="space-y-5">

                <!-- SELLER-37: STATUS FILTER TABS -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1 border-b border-slate-200">
                    <button
                        type="button"
                        @click="filterStatus('all')"
                        class="flex items-center gap-1.5 whitespace-nowrap rounded-xl px-3.5 py-2 text-xs font-bold transition border"
                        :class="selectedStatus === 'all' ? 'border-[#087F8C] bg-[#E8F7F6] text-[#087F8C]' : 'border-transparent text-slate-600 hover:bg-slate-100'"
                    >
                        <span>All Claims</span>
                        <span class="rounded-full bg-slate-200/80 px-2 py-0.5 text-[10px] text-slate-700">
                            {{ props.statusCounts.all }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="filterStatus('pending')"
                        class="flex items-center gap-1.5 whitespace-nowrap rounded-xl px-3.5 py-2 text-xs font-bold transition border"
                        :class="selectedStatus === 'pending' ? 'border-amber-400 bg-amber-50 text-amber-900' : 'border-transparent text-slate-600 hover:bg-slate-100'"
                    >
                        <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                        <span>Pending Decision</span>
                        <span class="rounded-full bg-amber-200/70 px-2 py-0.5 text-[10px] text-amber-900">
                            {{ props.statusCounts.pending }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="filterStatus('approved')"
                        class="flex items-center gap-1.5 whitespace-nowrap rounded-xl px-3.5 py-2 text-xs font-bold transition border"
                        :class="selectedStatus === 'approved' ? 'border-blue-400 bg-blue-50 text-blue-900' : 'border-transparent text-slate-600 hover:bg-slate-100'"
                    >
                        <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                        <span>Authorized / In Transit</span>
                        <span class="rounded-full bg-blue-200/70 px-2 py-0.5 text-[10px] text-blue-900">
                            {{ props.statusCounts.approved }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="filterStatus('item_returned')"
                        class="flex items-center gap-1.5 whitespace-nowrap rounded-xl px-3.5 py-2 text-xs font-bold transition border"
                        :class="selectedStatus === 'item_returned' ? 'border-purple-400 bg-purple-50 text-purple-900' : 'border-transparent text-slate-600 hover:bg-slate-100'"
                    >
                        <span class="h-2 w-2 rounded-full bg-purple-500"></span>
                        <span>Item Returned / Inspect</span>
                        <span class="rounded-full bg-purple-200/70 px-2 py-0.5 text-[10px] text-purple-900">
                            {{ props.statusCounts.item_returned }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="filterStatus('refunded')"
                        class="flex items-center gap-1.5 whitespace-nowrap rounded-xl px-3.5 py-2 text-xs font-bold transition border"
                        :class="selectedStatus === 'refunded' ? 'border-emerald-400 bg-emerald-50 text-emerald-900' : 'border-transparent text-slate-600 hover:bg-slate-100'"
                    >
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        <span>Refunded</span>
                        <span class="rounded-full bg-emerald-200/70 px-2 py-0.5 text-[10px] text-emerald-900">
                            {{ props.statusCounts.refunded }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="filterStatus('rejected')"
                        class="flex items-center gap-1.5 whitespace-nowrap rounded-xl px-3.5 py-2 text-xs font-bold transition border"
                        :class="selectedStatus === 'rejected' ? 'border-rose-400 bg-rose-50 text-rose-900' : 'border-transparent text-slate-600 hover:bg-slate-100'"
                    >
                        <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                        <span>Rejected</span>
                        <span class="rounded-full bg-rose-200/70 px-2 py-0.5 text-[10px] text-rose-900">
                            {{ props.statusCounts.rejected }}
                        </span>
                    </button>
                </div>

                <!-- FILTER BAR (Search, Type, Sort) -->
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
                    <div class="relative flex-1 max-w-md">
                        <input
                            type="text"
                            v-model="searchQuery"
                            @keyup.enter="applyFilters({ search: searchQuery, page: 1 })"
                            placeholder="Search Order #, buyer name, product, tracking #..."
                            class="w-full rounded-xl border border-slate-200 pl-9 pr-4 py-2 text-xs text-slate-800 placeholder-slate-400 focus:border-[#087F8C] focus:ring-1 focus:ring-[#087F8C] outline-hidden"
                        />
                        <svg class="absolute left-3 top-2.5 h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Type selector -->
                        <select
                            v-model="selectedType"
                            @change="applyFilters({ type: selectedType, page: 1 })"
                            class="h-9 rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 outline-hidden focus:border-[#087F8C]"
                        >
                            <option value="all">All Request Types</option>
                            <option value="return_and_refund">Return & Refund</option>
                            <option value="refund_only">Refund Only</option>
                        </select>

                        <!-- Sort selector -->
                        <select
                            v-model="selectedSort"
                            @change="applyFilters({ sort: selectedSort, page: 1 })"
                            class="h-9 rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 outline-hidden focus:border-[#087F8C]"
                        >
                            <option value="latest">Latest First</option>
                            <option value="oldest">Oldest First</option>
                            <option value="amount_desc">Highest Amount</option>
                            <option value="amount_asc">Lowest Amount</option>
                        </select>

                        <!-- Clear filters -->
                        <button
                            v-if="hasActiveFilters"
                            type="button"
                            @click="clearFilters"
                            class="h-9 rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-600 hover:bg-slate-50 transition"
                        >
                            Clear All
                        </button>
                    </div>
                </div>

                <!-- RETURN REQUESTS CARDS -->
                <div v-if="props.returnRequests.data.length" class="space-y-4">
                    <div
                        v-for="req in props.returnRequests.data"
                        :key="req.id"
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs transition hover:border-slate-300"
                    >
                        <!-- Card Header -->
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-mono font-bold text-slate-800">
                                    Return #{{ req.id }}
                                </span>
                                <span class="text-xs text-slate-400">•</span>
                                <span class="text-xs text-slate-500 font-medium">
                                    Submitted {{ formatDate(req.created_at) }}
                                </span>
                                <span class="text-xs text-slate-400">•</span>
                                <span class="text-xs font-semibold text-[#087F8C]">
                                    Order:
                                    <Link :href="route('seller.orders', { search: req.order?.order_number })" class="hover:underline">
                                        #{{ req.order?.order_number || req.order_id }}
                                    </Link>
                                </span>
                            </div>

                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-bold"
                                    :class="getStatusBadge(req.status).class"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full" :class="getStatusBadge(req.status).dot"></span>
                                    <span>{{ getStatusBadge(req.status).label }}</span>
                                </span>

                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 uppercase tracking-wider">
                                    {{ req.type === 'refund_only' ? 'Refund Only' : 'Return & Refund' }}
                                </span>
                            </div>
                        </div>

                        <!-- Card Body: 3 Columns Grid -->
                        <div class="mt-4 grid grid-cols-1 gap-6 lg:grid-cols-12">

                            <!-- Col 1: Product & Claim Info (5 cols) -->
                            <div class="space-y-3 lg:col-span-5">
                                <div class="flex items-start gap-3">
                                    <img
                                        v-if="req.order_item?.image_url"
                                        :src="req.order_item.image_url"
                                        :alt="req.order_item.product_name"
                                        class="h-16 w-16 shrink-0 rounded-xl border border-slate-100 object-cover"
                                    />
                                    <div v-else class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-400">
                                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0v10l-8 4-8-4V7m16 0l-8 4-8-4m8 4v10" />
                                        </svg>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <h4 class="text-sm font-bold text-slate-900 line-clamp-1">
                                            {{ req.order_item?.product_name || 'Product' }}
                                        </h4>
                                        <p v-if="req.order_item?.variant_title" class="text-xs text-slate-500 font-medium">
                                            Variation: {{ req.order_item.variant_title }}
                                        </p>
                                        <div class="mt-1 flex items-center gap-2 text-xs font-semibold text-slate-700">
                                            <span>{{ formatCurrency(req.order_item?.price) }} × {{ req.order_item?.quantity || 1 }}</span>
                                            <span class="text-slate-400">→</span>
                                            <span class="text-rose-600 font-bold">Claim: {{ formatCurrency(req.requested_amount) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Customer Details -->
                                <div class="rounded-xl bg-slate-50 p-2.5 text-xs text-slate-600">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-slate-800">Buyer: {{ req.buyer?.name || 'Customer' }}</span>
                                        <span class="text-[11px] text-slate-500">{{ req.buyer?.email }}</span>
                                    </div>
                                    <p v-if="req.buyer?.contact_number" class="mt-0.5 text-[11px] text-slate-500">
                                        Contact: {{ req.buyer.contact_number }}
                                    </p>
                                </div>
                            </div>

                            <!-- Col 2: Reason & Evidence (4 cols) -->
                            <div class="space-y-2 lg:col-span-4">
                                <p class="text-xs font-bold text-slate-800">
                                    Claim Reason:
                                    <span class="text-rose-700 font-extrabold">{{ req.reason }}</span>
                                </p>
                                <p v-if="req.reason_details" class="text-xs text-slate-600 leading-relaxed bg-slate-50/70 p-2 rounded-lg border border-slate-100">
                                    "{{ req.reason_details }}"
                                </p>

                                <!-- Evidence Images Lightbox Trigger -->
                                <div v-if="req.evidence_images && req.evidence_images.length">
                                    <p class="text-[11px] font-bold text-slate-500 mb-1.5">Customer Evidence ({{ req.evidence_images.length }}):</p>
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="(img, imgIdx) in req.evidence_images"
                                            :key="imgIdx"
                                            type="button"
                                            @click="openLightbox(req.evidence_images, imgIdx)"
                                            class="relative h-12 w-12 overflow-hidden rounded-lg border border-slate-200 transition hover:opacity-80 cursor-pointer"
                                        >
                                            <img :src="img.startsWith('http') || img.startsWith('/storage/') ? img : `/storage/${img.replace(/^\/+/, '')}`" class="h-full w-full object-cover" alt="Evidence" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Col 3: Shipping & Tracking / Resolution Actions (3 cols) -->
                            <div class="flex flex-col justify-between space-y-3 lg:col-span-3 border-t sm:border-t-0 sm:border-l border-slate-100 sm:pl-4">

                                <!-- Return Parcel Tracking (SELLER-39) -->
                                <div v-if="req.type !== 'refund_only' && req.status !== 'pending' && req.status !== 'rejected'" class="space-y-1.5 text-xs">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-slate-700">Parcel Tracking</span>
                                        <span class="rounded-md border px-1.5 py-0.5 text-[9px] font-bold" :class="getShippingStatusBadge(req.return_shipping_status).class">
                                            {{ getShippingStatusBadge(req.return_shipping_status).label }}
                                        </span>
                                    </div>

                                    <div v-if="req.return_tracking_number" class="text-slate-600">
                                        <div class="font-mono font-bold text-slate-900">{{ req.return_carrier }}: {{ req.return_tracking_number }}</div>
                                        <div class="flex items-center gap-2 mt-1">
                                            <button
                                                type="button"
                                                @click="openMilestonesModal(req)"
                                                class="text-[11px] font-bold text-[#087F8C] hover:underline"
                                            >
                                                Timeline ({{ req.return_shipping_history?.length || 0 }})
                                            </button>
                                            <button
                                                type="button"
                                                @click="openTrackingModal(req)"
                                                class="text-[11px] font-bold text-slate-600 hover:text-slate-900"
                                            >
                                                Edit Tracking
                                            </button>
                                        </div>
                                    </div>

                                    <div v-else class="text-[11px] text-slate-400">
                                        No carrier tracking recorded yet.
                                        <button
                                            type="button"
                                            @click="openTrackingModal(req)"
                                            class="block text-[11px] font-bold text-[#087F8C] hover:underline mt-0.5"
                                        >
                                            + Add Waybill Tracking
                                        </button>
                                    </div>
                                </div>

                                <!-- ACTION BUTTONS BASED ON STAGE -->
                                <div class="pt-2 space-y-2">

                                    <!-- STAGE 1: PENDING SELLER DECISION (SELLER-38) -->
                                    <template v-if="req.status === 'pending'">
                                        <button
                                            type="button"
                                            @click="openDecisionModal(req, 'approved')"
                                            class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#087F8C] px-3.5 py-2 text-xs font-bold text-white shadow-xs hover:bg-[#066B76] transition cursor-pointer"
                                        >
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span>Approve Return</span>
                                        </button>

                                        <button
                                            type="button"
                                            @click="openDecisionModal(req, 'rejected')"
                                            class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2 text-xs font-bold text-rose-700 hover:bg-rose-100 transition cursor-pointer"
                                        >
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            <span>Reject Claim</span>
                                        </button>
                                    </template>

                                    <!-- STAGE 2: APPROVED / AWAITING RECEIPT (SELLER-39 & SELLER-40) -->
                                    <template v-else-if="req.status === 'approved'">
                                        <button
                                            type="button"
                                            @click="openInspectionModal(req)"
                                            class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-purple-600 px-3.5 py-2 text-xs font-bold text-white shadow-xs hover:bg-purple-700 transition cursor-pointer"
                                        >
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span>Confirm Receipt & Inspect</span>
                                        </button>

                                        <button
                                            type="button"
                                            @click="openTrackingModal(req)"
                                            class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition cursor-pointer"
                                        >
                                            <span>Update Shipping Status</span>
                                        </button>
                                    </template>

                                    <!-- STAGE 3: ITEM RETURNED / IN INSPECTION (SELLER-40) -->
                                    <template v-else-if="req.status === 'item_returned'">
                                        <button
                                            type="button"
                                            @click="openInspectionModal(req)"
                                            class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-bold text-white shadow-xs hover:bg-emerald-700 transition cursor-pointer"
                                        >
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span>Resolve & Issue Refund</span>
                                        </button>
                                    </template>

                                    <!-- STAGE 4: COMPLETED / REFUNDED (SELLER-41) -->
                                    <div v-else-if="req.status === 'refunded' || req.status === 'completed'" class="rounded-xl bg-emerald-50 p-2 text-center text-xs text-emerald-800 font-bold border border-emerald-200">
                                        <span>Refund of {{ formatCurrency(req.refunded_amount || req.requested_amount) }} Issued</span>
                                        <p v-if="req.refund_ledger?.refund_reference" class="text-[10px] text-emerald-700 font-mono mt-0.5">
                                            Ref: {{ req.refund_ledger.refund_reference }}
                                        </p>
                                    </div>

                                    <!-- STAGE 5: REJECTED -->
                                    <div v-else-if="req.status === 'rejected'" class="rounded-xl bg-rose-50 p-2 text-xs text-rose-800 border border-rose-200">
                                        <span class="font-bold block">Claim Rejected</span>
                                        <span class="text-[11px] text-rose-600">{{ req.rejection_reason }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card Footer: Decision / Response Notes -->
                        <div v-if="req.seller_response || req.return_instructions" class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-600">
                            <div v-if="req.return_instructions" class="mb-1">
                                <strong class="text-slate-800">Return Instructions:</strong> {{ req.return_instructions }}
                                <span v-if="req.return_warehouse_address" class="block text-[11px] text-slate-500">Warehouse Address: {{ req.return_warehouse_address }}</span>
                            </div>
                            <div v-if="req.seller_response">
                                <strong class="text-slate-800">Seller Note:</strong> {{ req.seller_response }}
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="props.returnRequests.links && props.returnRequests.links.length > 3" class="flex items-center justify-between border-t border-slate-200 pt-4">
                        <div class="text-xs text-slate-500">
                            Showing {{ props.returnRequests.from || 0 }} to {{ props.returnRequests.to || 0 }} of {{ props.returnRequests.total || 0 }} return requests
                        </div>
                        <div class="flex items-center gap-1">
                            <Link
                                v-for="(link, lIdx) in props.returnRequests.links"
                                :key="lIdx"
                                :href="link.url || '#'"
                                class="inline-flex h-8 min-w-[32px] items-center justify-center rounded-lg px-2 text-xs font-bold transition"
                                :class="[
                                    link.active ? 'bg-[#087F8C] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100',
                                    !link.url ? 'opacity-40 pointer-events-none' : ''
                                ]"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-xs">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-[#E8F7F6] text-[#087F8C]">
                        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 15v-1a4 4 0 00-4-4H4m0 0l4 4m-4-4l4-4m12 5v2a3 3 0 01-3 3H9" />
                        </svg>
                    </div>
                    <h3 class="mt-4 text-base font-bold text-slate-900">No return requests found</h3>
                    <p class="mt-1 text-xs text-slate-500 max-w-sm mx-auto">
                        {{ hasActiveFilters ? 'No return claims match your current filters. Try resetting the filters above.' : 'You have not received any customer return or refund requests in this category.' }}
                    </p>
                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        @click="clearFilters"
                        class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-[#087F8C] px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-[#066B76] transition"
                    >
                        Reset All Filters
                    </button>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- TAB 2: SELLER-41 REFUND HISTORY LEDGER                    -->
            <!-- ========================================================= -->
            <div v-else-if="currentTab === 'ledger'" class="space-y-4">
                <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-xs">
                    <div class="flex items-center justify-between border-b border-slate-100 p-4">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Itemized Financial Refund Ledger</h3>
                            <p class="text-xs text-slate-500">Every refund transaction issued to buyers with timestamped audit records.</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[700px] text-left text-xs">
                            <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider text-[10px]">
                                <tr>
                                    <th class="px-4 py-3">Reference #</th>
                                    <th class="px-4 py-3">Order & Product</th>
                                    <th class="px-4 py-3">Buyer</th>
                                    <th class="px-4 py-3 text-right">Refund Amount</th>
                                    <th class="px-4 py-3">Refund Method</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Date Processed</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                <tr v-for="entry in props.refundLedger.data" :key="entry.id" class="hover:bg-slate-50/70 transition">
                                    <td class="px-4 py-3 font-mono font-bold text-slate-900">
                                        {{ entry.refund_reference }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-slate-900">#{{ entry.order?.order_number || entry.order_id }}</div>
                                        <div class="text-[11px] text-slate-500 line-clamp-1">{{ entry.order_item?.product_name }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-slate-800">{{ entry.buyer?.name }}</div>
                                        <div class="text-[10px] text-slate-400">{{ entry.buyer?.email }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right font-black text-rose-600">
                                        -{{ formatCurrency(entry.refunded_amount) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-700 uppercase">
                                            {{ entry.refund_method?.replace(/_/g, ' ') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-800">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            {{ entry.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-[11px] text-slate-500">
                                        {{ formatDate(entry.processed_at) }}
                                    </td>
                                </tr>
                                <tr v-if="!props.refundLedger.data.length">
                                    <td colspan="7" class="py-8 text-center text-xs text-slate-400">
                                        No refund transactions recorded in the ledger yet.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="props.refundLedger.links && props.refundLedger.links.length > 3" class="flex items-center justify-between border-t border-slate-200 p-4">
                        <div class="text-xs text-slate-500">
                            Showing {{ props.refundLedger.from || 0 }} to {{ props.refundLedger.to || 0 }} of {{ props.refundLedger.total || 0 }} ledger transactions
                        </div>
                        <div class="flex items-center gap-1">
                            <Link
                                v-for="(link, lIdx) in props.refundLedger.links"
                                :key="lIdx"
                                :href="link.url || '#'"
                                class="inline-flex h-8 min-w-[32px] items-center justify-center rounded-lg px-2 text-xs font-bold transition"
                                :class="[
                                    link.active ? 'bg-[#087F8C] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100',
                                    !link.url ? 'opacity-40 pointer-events-none' : ''
                                ]"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- MODAL: SELLER DECISION (APPROVE / REJECT) (SELLER-38)     -->
            <!-- ========================================================= -->
            <div
                v-if="showDecisionModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 overflow-y-auto"
                @click.self="closeDecisionModal"
            >
                <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                    <div class="flex items-start justify-between border-b border-slate-100 pb-4">
                        <div>
                            <div class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider" :class="decisionType === 'approved' ? 'text-emerald-600' : 'text-rose-600'">
                                <span>{{ decisionType === 'approved' ? 'Claim Approval' : 'Claim Rejection' }}</span>
                                <span>•</span>
                                <span>Order #{{ selectedRequestForDecision?.order?.order_number }}</span>
                            </div>
                            <h3 class="text-lg font-black text-slate-900 mt-0.5">
                                {{ decisionType === 'approved' ? 'Approve Return Claim' : 'Reject Return Claim' }}
                            </h3>
                        </div>
                        <button type="button" @click="closeDecisionModal" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100">
                            ✕
                        </button>
                    </div>

                    <form @submit.prevent="submitDecision" class="mt-4 space-y-4">

                        <!-- IF APPROVING -->
                        <template v-if="decisionType === 'approved'">
                            <div>
                                <label class="block text-xs font-bold text-slate-700">Return Warehouse Address</label>
                                <textarea
                                    v-model="decisionForm.return_warehouse_address"
                                    rows="2"
                                    placeholder="Enter complete store return delivery address..."
                                    class="mt-1 w-full rounded-xl border border-slate-200 p-2.5 text-xs text-slate-800 focus:border-[#087F8C] focus:ring-1 focus:ring-[#087F8C] outline-hidden"
                                    required
                                ></textarea>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700">Instructions for Buyer</label>
                                <textarea
                                    v-model="decisionForm.return_instructions"
                                    rows="3"
                                    placeholder="Instructions on packaging, accessories, return carrier..."
                                    class="mt-1 w-full rounded-xl border border-slate-200 p-2.5 text-xs text-slate-800 focus:border-[#087F8C] focus:ring-1 focus:ring-[#087F8C] outline-hidden"
                                    required
                                ></textarea>
                            </div>

                            <!-- Refund now option for refund_only claims -->
                            <div v-if="selectedRequestForDecision?.type === 'refund_only'" class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-3">
                                <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-emerald-900">
                                    <input
                                        type="checkbox"
                                        v-model="decisionForm.refund_now"
                                        class="rounded text-[#087F8C] focus:ring-[#087F8C]"
                                    />
                                    <span>Issue refund of {{ formatCurrency(selectedRequestForDecision.requested_amount) }} immediately without item return</span>
                                </label>
                            </div>
                        </template>

                        <!-- IF REJECTING -->
                        <template v-else>
                            <div>
                                <label class="block text-xs font-bold text-slate-700">Rejection Reason Code</label>
                                <select
                                    v-model="decisionForm.rejection_reason"
                                    class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-xs text-slate-800 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 outline-hidden"
                                    required
                                >
                                    <option value="" disabled>Select rejection reason...</option>
                                    <option v-for="r in rejectionReasons" :key="r.value" :value="r.label">
                                        {{ r.label }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700">Detailed Explanation for Customer</label>
                                <textarea
                                    v-model="decisionForm.seller_response"
                                    rows="3"
                                    placeholder="Explain why this request is rejected under your return policy..."
                                    class="mt-1 w-full rounded-xl border border-slate-200 p-2.5 text-xs text-slate-800 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 outline-hidden"
                                    required
                                ></textarea>
                            </div>
                        </template>

                        <div class="mt-5 flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                            <button
                                type="button"
                                @click="closeDecisionModal"
                                class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 transition"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="decisionForm.processing"
                                class="rounded-xl px-4 py-2 text-xs font-bold text-white shadow-sm transition disabled:opacity-50"
                                :class="decisionType === 'approved' ? 'bg-[#087F8C] hover:bg-[#066B76]' : 'bg-rose-600 hover:bg-rose-700'"
                            >
                                {{ decisionForm.processing ? 'Submitting...' : (decisionType === 'approved' ? 'Confirm Approval' : 'Confirm Rejection') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- MODAL: UPDATE RETURN CARRIER & TRACKING (SELLER-39)       -->
            <!-- ========================================================= -->
            <div
                v-if="showTrackingModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 overflow-y-auto"
                @click.self="closeTrackingModal"
            >
                <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                    <div class="flex items-start justify-between border-b border-slate-100 pb-4">
                        <div>
                            <div class="text-xs font-bold text-[#087F8C] uppercase tracking-wider">Logistics & Milestones</div>
                            <h3 class="text-lg font-black text-slate-900 mt-0.5">Return Parcel Tracking</h3>
                        </div>
                        <button type="button" @click="closeTrackingModal" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100">
                            ✕
                        </button>
                    </div>

                    <form @submit.prevent="submitTracking" class="mt-4 space-y-3.5">
                        <div>
                            <label class="block text-xs font-bold text-slate-700">Courier / Shipping Carrier</label>
                            <select
                                v-model="trackingForm.return_carrier"
                                class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-xs text-slate-800 outline-hidden focus:border-[#087F8C]"
                                required
                            >
                                <option v-for="c in carrierOptions" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700">Waybill / Tracking Number</label>
                            <input
                                type="text"
                                v-model="trackingForm.return_tracking_number"
                                placeholder="e.g. JNT-PH-9928172910"
                                class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-xs text-slate-800 outline-hidden focus:border-[#087F8C]"
                                required
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700">Shipping Milestone Status</label>
                            <select
                                v-model="trackingForm.return_shipping_status"
                                class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-xs text-slate-800 outline-hidden focus:border-[#087F8C]"
                                required
                            >
                                <option value="pending_shipment">Pending Shipment / Awaiting Courier</option>
                                <option value="in_transit">In Transit to Seller Facility</option>
                                <option value="out_for_delivery">Out for Delivery to Seller</option>
                                <option value="delivered">Delivered to Seller (Ready for Inspection)</option>
                            </select>
                            <p class="mt-1 text-[10px] text-slate-400">Selecting 'Delivered' automatically advances the claim to Item Returned stage.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700">Milestone Location (Optional)</label>
                            <input
                                type="text"
                                v-model="trackingForm.milestone_location"
                                placeholder="e.g. Paranaque Sorting Facility"
                                class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-xs text-slate-800 outline-hidden focus:border-[#087F8C]"
                            />
                        </div>

                        <div class="mt-5 flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                            <button
                                type="button"
                                @click="closeTrackingModal"
                                class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 transition"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="trackingForm.processing"
                                class="rounded-xl bg-[#087F8C] px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-[#066B76] transition disabled:opacity-50"
                            >
                                {{ trackingForm.processing ? 'Saving...' : 'Save Milestone' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- MODAL: PARCEL SHIPPING MILESTONES TIMELINE (SELLER-39)    -->
            <!-- ========================================================= -->
            <div
                v-if="showMilestonesModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 overflow-y-auto"
                @click.self="closeMilestonesModal"
            >
                <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                    <div class="flex items-start justify-between border-b border-slate-100 pb-4">
                        <div>
                            <div class="text-xs font-bold text-[#087F8C] uppercase tracking-wider">Carrier Tracking Audit</div>
                            <h3 class="text-lg font-black text-slate-900 mt-0.5">
                                {{ selectedRequestForMilestones?.return_carrier || 'Courier' }} Tracking
                            </h3>
                            <p class="text-xs font-mono font-bold text-slate-600 mt-0.5">
                                Waybill #: {{ selectedRequestForMilestones?.return_tracking_number || 'N/A' }}
                            </p>
                        </div>
                        <button type="button" @click="closeMilestonesModal" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100">
                            ✕
                        </button>
                    </div>

                    <!-- Milestones Timeline -->
                    <div class="mt-5 max-h-96 overflow-y-auto pr-2 space-y-4">
                        <div
                            v-for="(step, sIdx) in (selectedRequestForMilestones?.return_shipping_history || [])"
                            :key="sIdx"
                            class="relative flex gap-3 text-xs"
                        >
                            <div class="flex flex-col items-center">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-[#E8F7F6] text-[#087F8C] font-bold text-xs ring-4 ring-white">
                                    ✓
                                </span>
                                <span v-if="sIdx < (selectedRequestForMilestones?.return_shipping_history.length - 1)" class="h-full w-0.5 bg-slate-200 mt-1"></span>
                            </div>
                            <div class="pb-3 min-w-0 flex-1">
                                <p class="font-bold text-slate-900">{{ step.title }}</p>
                                <p class="text-slate-500 text-[11px]">{{ step.location }}</p>
                                <p v-if="step.description" class="text-slate-400 text-[10px] mt-0.5">{{ step.description }}</p>
                                <p class="text-slate-400 text-[10px] font-mono mt-1">{{ formatDate(step.timestamp) }}</p>
                            </div>
                        </div>

                        <div v-if="!selectedRequestForMilestones?.return_shipping_history?.length" class="text-center py-6 text-xs text-slate-400">
                            No milestone scans logged yet for this return parcel.
                        </div>
                    </div>

                    <div class="mt-4 border-t border-slate-100 pt-3 text-right">
                        <button
                            type="button"
                            @click="closeMilestonesModal"
                            class="rounded-xl bg-slate-100 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-200 transition"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- MODAL: INSPECT ITEM & RESOLVE REFUND (SELLER-40, 41)      -->
            <!-- ========================================================= -->
            <div
                v-if="showInspectionModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 overflow-y-auto"
                @click.self="closeInspectionModal"
            >
                <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                    <div class="flex items-start justify-between border-b border-slate-100 pb-4">
                        <div>
                            <div class="text-xs font-bold text-purple-700 uppercase tracking-wider">Quality Control Inspection</div>
                            <h3 class="text-lg font-black text-slate-900 mt-0.5">Item Inspection & Refund Resolution</h3>
                        </div>
                        <button type="button" @click="closeInspectionModal" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100">
                            ✕
                        </button>
                    </div>

                    <form @submit.prevent="submitInspection" class="mt-4 space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700">Inspection Result</label>
                            <select
                                v-model="inspectionForm.inspection_status"
                                class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-xs text-slate-800 outline-hidden focus:border-[#087F8C]"
                                required
                            >
                                <option value="passed">Passed — Good Condition / Verified Claim</option>
                                <option value="partial_damage">Partial Damage — Issue Partial Refund</option>
                                <option value="failed">Failed — Damaged by Buyer / Ineligible for Refund</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700">Inspection Findings / Notes</label>
                            <textarea
                                v-model="inspectionForm.inspection_notes"
                                rows="2"
                                placeholder="Describe condition of returned product, packaging, accessories..."
                                class="mt-1 w-full rounded-xl border border-slate-200 p-2.5 text-xs text-slate-800 outline-hidden focus:border-[#087F8C]"
                                required
                            ></textarea>
                        </div>

                        <!-- Refund settings (if passed or partial) -->
                        <template v-if="inspectionForm.inspection_status !== 'failed'">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700">Refund Amount (₱)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        v-model="inspectionForm.refund_amount"
                                        :max="selectedRequestForInspection?.requested_amount"
                                        class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-xs text-slate-800 outline-hidden focus:border-[#087F8C]"
                                        required
                                    />
                                    <span class="text-[10px] text-slate-400">Max: {{ formatCurrency(selectedRequestForInspection?.requested_amount) }}</span>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700">Refund Method</label>
                                    <select
                                        v-model="inspectionForm.refund_method"
                                        class="mt-1 w-full rounded-xl border border-slate-200 p-2 text-xs text-slate-800 outline-hidden focus:border-[#087F8C]"
                                        required
                                    >
                                        <option value="original_payment">Original Payment Method</option>
                                        <option value="store_credit">Store Credit / Voucher</option>
                                        <option value="manual_transfer">Manual Bank Transfer / GCash</option>
                                        <option value="wallet">Buyer Wallet</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Automatic Restock Checkbox (SELLER-40 connected to SELLER-21 Stock Adjustment) -->
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <label class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-800">
                                    <input
                                        type="checkbox"
                                        v-model="inspectionForm.restock_inventory"
                                        class="rounded text-[#087F8C] focus:ring-[#087F8C]"
                                    />
                                    <span>Automatically restock {{ selectedRequestForInspection?.order_item?.quantity || 1 }} unit(s) into inventory</span>
                                </label>
                                <p class="mt-0.5 text-[11px] text-slate-500 pl-5">
                                    This increments the active variant/product stock and logs a 'return' stock adjustment audit.
                                </p>
                            </div>
                        </template>

                        <div class="mt-5 flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                            <button
                                type="button"
                                @click="closeInspectionModal"
                                class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 transition"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="inspectionForm.processing"
                                class="rounded-xl px-4 py-2 text-xs font-bold text-white shadow-sm transition disabled:opacity-50"
                                :class="inspectionForm.inspection_status === 'failed' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-emerald-600 hover:bg-emerald-700'"
                            >
                                {{ inspectionForm.processing ? 'Processing...' : (inspectionForm.inspection_status === 'failed' ? 'Deny Refund' : 'Confirm Inspection & Issue Refund') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- LIGHTBOX MODAL: EVIDENCE PHOTOS                           -->
            <!-- ========================================================= -->
            <div
                v-if="showLightbox"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 backdrop-blur-md p-4"
                @click.self="closeLightbox"
            >
                <div class="relative flex flex-col items-center max-w-4xl w-full h-[85vh]">
                    <div class="flex items-center justify-between w-full px-4 py-3 bg-white/10 rounded-2xl text-white mb-3">
                        <span class="text-xs font-bold">Customer Photo Evidence ({{ activeLightboxIndex + 1 }} / {{ lightboxImages.length }})</span>
                        <button type="button" @click="closeLightbox" class="rounded-lg bg-white/10 p-2 text-white hover:bg-white/20">
                            ✕
                        </button>
                    </div>

                    <div class="relative flex-1 w-full flex items-center justify-center overflow-hidden rounded-2xl bg-black/40">
                        <img
                            :src="lightboxImages[activeLightboxIndex].startsWith('http') || lightboxImages[activeLightboxIndex].startsWith('/storage/') ? lightboxImages[activeLightboxIndex] : `/storage/${lightboxImages[activeLightboxIndex].replace(/^\/+/, '')}`"
                            alt="Return Evidence"
                            class="max-h-full max-w-full object-contain rounded-xl"
                        />
                    </div>

                    <div v-if="lightboxImages.length > 1" class="mt-3 flex gap-2">
                        <button
                            v-for="(img, idx) in lightboxImages"
                            :key="idx"
                            type="button"
                            @click="activeLightboxIndex = idx"
                            class="h-12 w-12 overflow-hidden rounded-lg border-2"
                            :class="activeLightboxIndex === idx ? 'border-[#087F8C]' : 'border-white/30 opacity-60'"
                        >
                            <img :src="img.startsWith('http') || img.startsWith('/storage/') ? img : `/storage/${img.replace(/^\/+/, '')}`" class="h-full w-full object-cover" />
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </SellerLayout>
</template>

