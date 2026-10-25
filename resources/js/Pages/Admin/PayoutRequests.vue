<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    payouts: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
            meta: {},
        }),
    },
    currentStatus: {
        type: String,
        default: 'all',
    },
    filters: {
        type: Object,
        default: () => ({
            status: 'all',
            search: '',
        }),
    },
    stats: {
        type: Object,
        default: () => ({
            total_disbursed: 0,
            pending_amount: 0,
            processing_amount: 0,
            pending_count: 0,
            processing_count: 0,
            approved_count: 0,
            rejected_count: 0,
            total_count: 0,
        }),
    },
    counts: {
        type: Object,
        default: () => ({
            all: 0,
            pending: 0,
            processing: 0,
            approved: 0,
            rejected: 0,
        }),
    },
})

// Search state
const search = ref(props.filters.search ?? '')
const selectedStatus = ref(props.currentStatus ?? 'all')

let searchTimer = null
watch(search, (val) => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        applyFilters()
    }, 350)
})

const applyFilters = (statusOverride = null) => {
    if (statusOverride !== null) {
        selectedStatus.value = statusOverride
    }

    router.get(
        route('admin.payout-requests'),
        {
            status: selectedStatus.value,
            search: search.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    )
}

// Helpers
const peso = (val) => {
    return Number(val || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}

const formatDate = (dateStr) => {
    if (!dateStr) return '—'
    const date = new Date(dateStr)
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

const sellerInitial = (payout) => {
    const name = payout.seller?.store_name || payout.seller?.name || 'S'
    return name.charAt(0).toUpperCase()
}

// Status badge styling
const getStatusBadge = (status) => {
    switch (status) {
        case 'pending':
            return {
                label: 'Pending Review',
                class: 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20',
                dot: 'bg-amber-500',
            }
        case 'processing':
            return {
                label: 'Under Processing',
                class: 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-600/20',
                dot: 'bg-blue-500',
            }
        case 'approved':
        case 'completed':
            return {
                label: 'Approved & Disbursed',
                class: 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20',
                dot: 'bg-emerald-500',
            }
        case 'rejected':
            return {
                label: 'Rejected',
                class: 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20',
                dot: 'bg-rose-500',
            }
        case 'cancelled':
            return {
                label: 'Cancelled',
                class: 'bg-slate-50 text-slate-600 ring-1 ring-inset ring-slate-500/20',
                dot: 'bg-slate-400',
            }
        default:
            return {
                label: status,
                class: 'bg-slate-50 text-slate-600 ring-1 ring-inset ring-slate-500/20',
                dot: 'bg-slate-400',
            }
    }
}

// Payout method badge styling
const getMethodBadge = (method) => {
    const norm = String(method || '').toLowerCase()
    if (norm === 'gcash') {
        return { label: 'GCash', class: 'bg-[#007DFE]/10 text-[#007DFE] border-[#007DFE]/30' }
    }
    if (norm === 'maya') {
        return { label: 'Maya', class: 'bg-[#2ECC71]/10 text-[#27AE60] border-[#2ECC71]/30' }
    }
    return { label: 'Bank Transfer', class: 'bg-purple-50 text-purple-700 border-purple-200' }
}

// Modal management
const activePayout = ref(null)
const viewModalOpen = ref(false)
const actionModalOpen = ref(false)
const actionType = ref('completed') // 'processing' | 'completed' | 'rejected'

const actionForm = useForm({
    status: '',
    admin_notes: '',
    rejection_reason: '',
})

const openViewModal = (payout) => {
    activePayout.value = payout
    viewModalOpen.value = true
}

const openActionModal = (payout, type) => {
    activePayout.value = payout
    actionType.value = type
    actionForm.clearErrors()
    actionForm.reset()
    actionForm.status = type
    actionForm.admin_notes = payout.admin_notes || ''
    actionForm.rejection_reason = payout.rejection_reason || ''
    actionModalOpen.value = true
}

const submitStatusUpdate = () => {
    if (!activePayout.value) return

    actionForm.patch(route('admin.payout-requests.status', activePayout.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            actionModalOpen.value = false
            viewModalOpen.value = false
            activePayout.value = null
        },
    })
}
</script>

<template>
    <Head title="Seller Payout Requests - Admin" />

    <AdminLayout active="payout-requests">
        <div class="w-full space-y-6">

            <!-- PAGE HEADER -->
            <div class="flex flex-col gap-4 border-b border-[#E5E7EB] pb-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-[#087F8C]">
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                        <span>Financial Administration</span>
                    </div>

                    <h1 class="mt-1 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                        Seller Payout Requests
                    </h1>

                    <p class="mt-0.5 text-xs text-slate-500">
                        Review, process, and disburse withdrawal requests submitted by marketplace sellers.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="applyFilters()"
                        class="inline-flex items-center gap-2 rounded-xl border border-[#E5E7EB] bg-white px-3.5 py-2 text-xs font-semibold text-[#64748B] shadow-xs transition hover:border-[#087F8C] hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                    >
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Refresh List
                    </button>
                </div>
            </div>

            <!-- FLASH STATUS / ALERTS -->
            <div
                v-if="$page.props.flash?.status || $page.props.flash?.success"
                class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-800 shadow-xs"
            >
                <svg class="h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                </svg>
                <span>{{ $page.props.flash?.status || $page.props.flash?.success }}</span>
            </div>

            <!-- KPI SUMMARY CARDS -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Total Disbursed -->
                <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-xs transition hover:shadow-sm">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-[#64748B]">Total Disbursed</p>
                            <p class="mt-2 text-2xl font-black text-slate-900">₱{{ peso(stats.total_disbursed) }}</p>
                            <p class="mt-1 text-[11px] font-medium text-emerald-600">
                                {{ stats.approved_count }} approved payouts
                            </p>
                        </div>
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Pending Review -->
                <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-xs transition hover:shadow-sm">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-[#64748B]">Pending Review</p>
                            <p class="mt-2 text-2xl font-black text-amber-600">₱{{ peso(stats.pending_amount) }}</p>
                            <p class="mt-1 text-[11px] font-medium text-amber-700">
                                {{ stats.pending_count }} requests awaiting review
                            </p>
                        </div>
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Under Processing -->
                <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-xs transition hover:shadow-sm">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-[#64748B]">Under Processing</p>
                            <p class="mt-2 text-2xl font-black text-blue-600">₱{{ peso(stats.processing_amount) }}</p>
                            <p class="mt-1 text-[11px] font-medium text-blue-700">
                                {{ stats.processing_count }} in bank/e-wallet queue
                            </p>
                        </div>
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Total Payout Requests -->
                <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-xs transition hover:shadow-sm">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-[#64748B]">Total Volume</p>
                            <p class="mt-2 text-2xl font-black text-[#087F8C]">{{ stats.total_count }}</p>
                            <p class="mt-1 text-[11px] font-medium text-slate-500">
                                {{ stats.rejected_count }} rejected requests
                            </p>
                        </div>
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TABS & SEARCH SECTION -->
            <div class="rounded-2xl border border-[#E5E7EB] bg-white shadow-xs">
                <!-- Filters & Search Toolbar -->
                <div class="flex flex-col gap-4 border-b border-[#E5E7EB] p-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <!-- Status Filter Tabs -->
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button
                            type="button"
                            @click="applyFilters('all')"
                            class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-bold transition"
                            :class="selectedStatus === 'all'
                                ? 'bg-[#087F8C] text-white shadow-xs'
                                : 'text-slate-600 hover:bg-slate-100'"
                        >
                            <span>All</span>
                            <span class="rounded-md px-1.5 py-0.5 text-[10px]" :class="selectedStatus === 'all' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700'">
                                {{ counts.all }}
                            </span>
                        </button>

                        <button
                            type="button"
                            @click="applyFilters('pending')"
                            class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-bold transition"
                            :class="selectedStatus === 'pending'
                                ? 'bg-amber-500 text-white shadow-xs'
                                : 'text-slate-600 hover:bg-slate-100'"
                        >
                            <span>Pending</span>
                            <span class="rounded-md px-1.5 py-0.5 text-[10px]" :class="selectedStatus === 'pending' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800'">
                                {{ counts.pending }}
                            </span>
                        </button>

                        <button
                            type="button"
                            @click="applyFilters('processing')"
                            class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-bold transition"
                            :class="selectedStatus === 'processing'
                                ? 'bg-blue-600 text-white shadow-xs'
                                : 'text-slate-600 hover:bg-slate-100'"
                        >
                            <span>Processing</span>
                            <span class="rounded-md px-1.5 py-0.5 text-[10px]" :class="selectedStatus === 'processing' ? 'bg-white/20 text-white' : 'bg-blue-100 text-blue-800'">
                                {{ counts.processing }}
                            </span>
                        </button>

                        <button
                            type="button"
                            @click="applyFilters('approved')"
                            class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-bold transition"
                            :class="selectedStatus === 'approved'
                                ? 'bg-emerald-600 text-white shadow-xs'
                                : 'text-slate-600 hover:bg-slate-100'"
                        >
                            <span>Approved</span>
                            <span class="rounded-md px-1.5 py-0.5 text-[10px]" :class="selectedStatus === 'approved' ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-800'">
                                {{ counts.approved }}
                            </span>
                        </button>

                        <button
                            type="button"
                            @click="applyFilters('rejected')"
                            class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-bold transition"
                            :class="selectedStatus === 'rejected'
                                ? 'bg-rose-600 text-white shadow-xs'
                                : 'text-slate-600 hover:bg-slate-100'"
                        >
                            <span>Rejected</span>
                            <span class="rounded-md px-1.5 py-0.5 text-[10px]" :class="selectedStatus === 'rejected' ? 'bg-white/20 text-white' : 'bg-rose-100 text-rose-800'">
                                {{ counts.rejected }}
                            </span>
                        </button>
                    </div>

                    <!-- Search Input -->
                    <div class="relative w-full sm:w-72">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                        </svg>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search seller, ref, account..."
                            class="h-9 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] pl-9 pr-3 text-xs text-slate-800 placeholder-slate-400 transition outline-none focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#087F8C]/10"
                        />
                    </div>
                </div>

                <!-- PAYOUT REQUESTS TABLE -->
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[850px] border-collapse text-left">
                        <thead>
                            <tr class="border-b border-[#E5E7EB] bg-[#F8FAF9] text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                <th class="px-5 py-3.5">Seller Information</th>
                                <th class="px-5 py-3.5">Reference & Date</th>
                                <th class="px-5 py-3.5">Requested Amount</th>
                                <th class="px-5 py-3.5">Disbursement Account</th>
                                <th class="px-5 py-3.5">Status</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E5E7EB]">
                            <tr
                                v-for="payout in payouts.data"
                                :key="payout.id"
                                class="transition hover:bg-slate-50/70"
                            >
                                <!-- Seller Info Connection -->
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <!-- Seller Logo / Avatar -->
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-[#E8F7F6] text-xs font-black text-[#087F8C] ring-1 ring-slate-200">
                                            <img
                                                v-if="payout.seller?.avatar || payout.seller?.store_logo_path"
                                                :src="payout.seller?.store_logo_path || payout.seller?.avatar"
                                                :alt="payout.seller?.name"
                                                class="h-full w-full object-cover"
                                            />
                                            <span v-else>{{ sellerInitial(payout) }}</span>
                                        </div>

                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <p class="truncate text-xs font-bold text-slate-900">
                                                    {{ payout.seller?.store_name || payout.seller?.name || 'Seller #' + payout.seller_id }}
                                                </p>
                                                <span
                                                    v-if="payout.seller?.store_name"
                                                    class="rounded-md bg-teal-50 px-1.5 py-0.2 text-[9px] font-bold text-[#087F8C]"
                                                >
                                                    Store
                                                </span>
                                            </div>

                                            <p class="truncate text-[11px] text-slate-500">
                                                {{ payout.seller?.email || 'No email' }}
                                            </p>

                                            <p v-if="payout.seller?.contact_no" class="truncate text-[10px] text-slate-400">
                                                {{ payout.seller?.contact_no }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Reference & Date -->
                                <td class="px-5 py-4">
                                    <p class="font-mono text-xs font-bold text-slate-800">
                                        {{ payout.payout_reference }}
                                    </p>
                                    <p class="mt-0.5 text-[11px] text-slate-500">
                                        {{ formatDate(payout.created_at) }}
                                    </p>
                                </td>

                                <!-- Amount -->
                                <td class="px-5 py-4">
                                    <p class="text-sm font-black tracking-tight text-[#087F8C]">
                                        ₱{{ peso(payout.amount) }}
                                    </p>
                                </td>

                                <!-- Method & Account -->
                                <td class="px-5 py-4">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-1.5">
                                            <span
                                                class="inline-flex items-center rounded-md border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                                                :class="getMethodBadge(payout.payout_method).class"
                                            >
                                                {{ getMethodBadge(payout.payout_method).label }}
                                            </span>
                                            <span v-if="payout.bank_name" class="text-[11px] font-semibold text-slate-700">
                                                {{ payout.bank_name }}
                                            </span>
                                        </div>
                                        <p class="text-xs font-semibold text-slate-800">
                                            {{ payout.account_name }}
                                        </p>
                                        <p class="font-mono text-[11px] text-slate-500">
                                            {{ payout.account_number }}
                                        </p>
                                    </div>
                                </td>

                                <!-- Status -->
                                <td class="px-5 py-4">
                                    <div>
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold"
                                            :class="getStatusBadge(payout.status).class"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full" :class="getStatusBadge(payout.status).dot"></span>
                                            {{ getStatusBadge(payout.status).label }}
                                        </span>

                                        <p v-if="payout.processed_at" class="mt-1 text-[10px] text-slate-400">
                                            Updated {{ formatDate(payout.processed_at) }}
                                        </p>

                                        <p v-if="payout.rejection_reason && payout.status === 'rejected'" class="mt-1 max-w-xs truncate text-[10px] font-medium text-rose-600" :title="payout.rejection_reason">
                                            Reason: {{ payout.rejection_reason }}
                                        </p>
                                    </div>
                                </td>

                                <!-- Action Buttons -->
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- View / Details Button -->
                                        <button
                                            type="button"
                                            @click="openViewModal(payout)"
                                            class="inline-flex h-8 items-center justify-center rounded-lg border border-[#E5E7EB] bg-white px-2.5 text-xs font-semibold text-slate-700 transition hover:border-[#087F8C] hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                                            title="View Payout Details"
                                        >
                                            View
                                        </button>

                                        <!-- If Pending: Allow Processing or Approve/Reject directly -->
                                        <template v-if="payout.status === 'pending'">
                                            <button
                                                type="button"
                                                @click="openActionModal(payout, 'processing')"
                                                class="inline-flex h-8 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 px-2.5 text-xs font-bold text-blue-700 transition hover:bg-blue-100"
                                                title="Mark as under processing"
                                            >
                                                Process
                                            </button>

                                            <button
                                                type="button"
                                                @click="openActionModal(payout, 'completed')"
                                                class="inline-flex h-8 items-center justify-center rounded-lg bg-emerald-600 px-3 text-xs font-bold text-white shadow-xs transition hover:bg-emerald-700"
                                                title="Approve and disburse payout"
                                            >
                                                Approve
                                            </button>

                                            <button
                                                type="button"
                                                @click="openActionModal(payout, 'rejected')"
                                                class="inline-flex h-8 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 px-2.5 text-xs font-bold text-rose-700 transition hover:bg-rose-100"
                                                title="Decline payout request"
                                            >
                                                Reject
                                            </button>
                                        </template>

                                        <!-- If Processing: Allow Approve/Disburse or Reject -->
                                        <template v-else-if="payout.status === 'processing'">
                                            <button
                                                type="button"
                                                @click="openActionModal(payout, 'completed')"
                                                class="inline-flex h-8 items-center justify-center rounded-lg bg-emerald-600 px-3 text-xs font-bold text-white shadow-xs transition hover:bg-emerald-700"
                                                title="Mark as completed/disbursed"
                                            >
                                                Disburse
                                            </button>

                                            <button
                                                type="button"
                                                @click="openActionModal(payout, 'rejected')"
                                                class="inline-flex h-8 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 px-2.5 text-xs font-bold text-rose-700 transition hover:bg-rose-100"
                                                title="Decline payout request"
                                            >
                                                Reject
                                            </button>
                                        </template>

                                        <span v-else class="text-xs text-slate-400">
                                            Completed
                                        </span>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-if="!payouts.data || payouts.data.length === 0">
                                <td colspan="6" class="px-5 py-12 text-center">
                                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                        </svg>
                                    </div>
                                    <h3 class="mt-3 text-sm font-bold text-slate-800">No payout requests found</h3>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ search ? 'No requests match your current search query.' : 'There are currently no payout requests in this filter category.' }}
                                    </p>
                                    <button
                                        v-if="search || selectedStatus !== 'all'"
                                        type="button"
                                        @click="search = ''; applyFilters('all')"
                                        class="mt-3 inline-flex items-center gap-1.5 text-xs font-bold text-[#087F8C] hover:underline"
                                    >
                                        Clear filters
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION FOOTER -->
                <div
                    v-if="payouts.links && payouts.links.length > 3"
                    class="flex flex-col gap-3 border-t border-[#E5E7EB] p-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                >
                    <p class="text-xs text-slate-500">
                        Showing <span class="font-semibold text-slate-900">{{ payouts.from || 0 }}</span> to
                        <span class="font-semibold text-slate-900">{{ payouts.to || 0 }}</span> of
                        <span class="font-semibold text-slate-900">{{ payouts.total || 0 }}</span> payout requests
                    </p>

                    <div class="flex flex-wrap items-center gap-1">
                        <template v-for="(link, i) in payouts.links" :key="i">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                preserve-state
                                class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg border px-2.5 text-xs font-semibold transition"
                                :class="link.active
                                    ? 'border-[#087F8C] bg-[#087F8C] text-white shadow-xs'
                                    : 'border-[#E5E7EB] bg-white text-slate-600 hover:border-[#087F8C]/40 hover:text-[#087F8C]'"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg border border-[#E5E7EB] bg-slate-50 px-2.5 text-xs text-slate-400"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>

            <!-- VIEW DETAILS MODAL -->
            <div
                v-if="viewModalOpen && activePayout"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs"
                @click.self="viewModalOpen = false"
            >
                <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
                    <div class="flex items-center justify-between border-b border-[#E5E7EB] pb-3">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#087F8C]"></span>
                            <h3 class="text-base font-bold text-slate-900">
                                Payout Request Details
                            </h3>
                        </div>
                        <button
                            type="button"
                            @click="viewModalOpen = false"
                            class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                        >
                            ✕
                        </button>
                    </div>

                    <div class="mt-4 space-y-4 text-xs">
                        <!-- Seller Card -->
                        <div class="rounded-xl border border-teal-100 bg-teal-50/50 p-3.5">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-[#087F8C]">Connected Seller</p>
                            <div class="mt-2 flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-extrabold text-slate-900">
                                        {{ activePayout.seller?.store_name || activePayout.seller?.name || 'Seller #' + activePayout.seller_id }}
                                    </p>
                                    <p class="text-slate-600">{{ activePayout.seller?.email }}</p>
                                    <p v-if="activePayout.seller?.contact_no" class="text-slate-500">{{ activePayout.seller?.contact_no }}</p>
                                </div>
                                <Link
                                    :href="route('admin.users', { search: activePayout.seller?.email || activePayout.seller?.name })"
                                    class="rounded-lg border border-teal-200 bg-white px-2.5 py-1.5 text-[11px] font-bold text-[#087F8C] shadow-xs hover:bg-teal-50"
                                >
                                    View Seller Profile
                                </Link>
                            </div>
                        </div>

                        <!-- Payout Info Grid -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Reference</p>
                                <p class="mt-0.5 font-mono text-xs font-bold text-slate-800">{{ activePayout.payout_reference }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Requested Amount</p>
                                <p class="mt-0.5 text-sm font-black text-[#087F8C]">₱{{ peso(activePayout.amount) }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Disbursement Channel</p>
                                <p class="mt-0.5 font-bold uppercase text-slate-800">{{ activePayout.payout_method }}</p>
                                <p v-if="activePayout.bank_name" class="text-[10px] text-slate-500">{{ activePayout.bank_name }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Current Status</p>
                                <p class="mt-0.5 font-bold capitalize" :class="{
                                    'text-amber-600': activePayout.status === 'pending',
                                    'text-blue-600': activePayout.status === 'processing',
                                    'text-emerald-600': activePayout.status === 'approved' || activePayout.status === 'completed',
                                    'text-rose-600': activePayout.status === 'rejected',
                                }">
                                    {{ activePayout.status }}
                                </p>
                            </div>
                        </div>

                        <!-- Destination Account Details -->
                        <div class="rounded-xl border border-slate-200 bg-white p-3">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Recipient Account Details</p>
                            <div class="mt-1 flex justify-between">
                                <span class="text-slate-500">Account Name:</span>
                                <span class="font-bold text-slate-800">{{ activePayout.account_name }}</span>
                            </div>
                            <div class="mt-1 flex justify-between">
                                <span class="text-slate-500">Account Number:</span>
                                <span class="font-mono font-bold text-slate-800">{{ activePayout.account_number }}</span>
                            </div>
                        </div>

                        <!-- Processing Details if already acted on -->
                        <div v-if="activePayout.processed_at" class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Processing Audit</p>
                            <p class="mt-1 text-slate-600">Processed at: <span class="font-semibold text-slate-800">{{ formatDate(activePayout.processed_at) }}</span></p>
                            <p v-if="activePayout.processed_by" class="text-slate-600">Processed by Admin: <span class="font-semibold text-slate-800">{{ activePayout.processed_by?.name || 'Administrator' }}</span></p>
                            <p v-if="activePayout.admin_notes" class="mt-1 text-slate-700">Notes: {{ activePayout.admin_notes }}</p>
                            <p v-if="activePayout.rejection_reason" class="mt-1 font-semibold text-rose-600">Rejection Reason: {{ activePayout.rejection_reason }}</p>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-2 border-t border-[#E5E7EB] pt-3">
                        <button
                            type="button"
                            @click="viewModalOpen = false"
                            class="rounded-xl border border-[#E5E7EB] px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                        >
                            Close
                        </button>
                        <template v-if="activePayout.status === 'pending' || activePayout.status === 'processing'">
                            <button
                                type="button"
                                @click="openActionModal(activePayout, 'completed')"
                                class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-emerald-700"
                            >
                                Approve & Disburse
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- ACTION MODAL (APPROVE / PROCESS / REJECT) -->
            <div
                v-if="actionModalOpen && activePayout"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs"
                @click.self="actionModalOpen = false"
            >
                <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                    <div class="flex items-center justify-between border-b border-[#E5E7EB] pb-3">
                        <h3 class="text-base font-bold text-slate-900">
                            <span v-if="actionType === 'completed'" class="text-emerald-700">Approve & Complete Payout</span>
                            <span v-else-if="actionType === 'processing'" class="text-blue-700">Mark as Under Processing</span>
                            <span v-else class="text-rose-700">Decline Payout Request</span>
                        </h3>
                        <button
                            type="button"
                            @click="actionModalOpen = false"
                            class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                        >
                            ✕
                        </button>
                    </div>

                    <form @submit.prevent="submitStatusUpdate" class="mt-4 space-y-4">
                        <!-- Summary Snippet -->
                        <div class="rounded-xl border p-3 text-xs" :class="{
                            'border-emerald-200 bg-emerald-50/60': actionType === 'completed',
                            'border-blue-200 bg-blue-50/60': actionType === 'processing',
                            'border-rose-200 bg-rose-50/60': actionType === 'rejected',
                        }">
                            <div class="flex justify-between font-bold">
                                <span>{{ activePayout.seller?.store_name || activePayout.seller?.name }}</span>
                                <span class="text-sm font-black text-[#087F8C]">₱{{ peso(activePayout.amount) }}</span>
                            </div>
                            <p class="mt-1 text-slate-600">
                                Via <strong class="uppercase">{{ activePayout.payout_method }}</strong> ({{ activePayout.account_number }})
                            </p>
                        </div>

                        <!-- Warning / Helper Text -->
                        <p class="text-xs text-slate-500">
                            <template v-if="actionType === 'completed'">
                                Confirming this action marks the funds as successfully transferred to the seller's account. An automated confirmation notification will be sent to the seller.
                            </template>
                            <template v-else-if="actionType === 'processing'">
                                This notifies the seller that their withdrawal is currently being processed by the administration.
                            </template>
                            <template v-else>
                                Declining this request will return the held funds back to the seller's available balance and alert them with your stated reason.
                            </template>
                        </p>

                        <!-- Rejection Reason (Mandatory if rejected) -->
                        <div v-if="actionType === 'rejected'">
                            <label class="block text-xs font-bold text-slate-700">
                                Reason for Rejection <span class="text-rose-600">*</span>
                            </label>
                            <textarea
                                v-model="actionForm.rejection_reason"
                                rows="3"
                                required
                                placeholder="State clearly why this payout was declined (e.g. invalid account details, KYC mismatch)..."
                                class="mt-1.5 w-full rounded-xl border border-slate-200 p-2.5 text-xs text-slate-800 outline-none focus:border-rose-500 focus:ring-2 focus:ring-rose-500/10"
                            ></textarea>
                            <p v-if="actionForm.errors.rejection_reason" class="mt-1 text-[11px] text-rose-600">
                                {{ actionForm.errors.rejection_reason }}
                            </p>
                        </div>

                        <!-- Admin Notes / Transaction Ref (Optional) -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700">
                                Admin Transaction Reference / Notes (Optional)
                            </label>
                            <input
                                v-model="actionForm.admin_notes"
                                type="text"
                                placeholder="e.g. Bank Ref #9812470123 / GCash Trans ID"
                                class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 outline-none focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10"
                            />
                            <p v-if="actionForm.errors.admin_notes" class="mt-1 text-[11px] text-rose-600">
                                {{ actionForm.errors.admin_notes }}
                            </p>
                        </div>

                        <!-- Action Buttons -->
                        <div class="mt-6 flex justify-end gap-2 border-t border-[#E5E7EB] pt-3">
                            <button
                                type="button"
                                @click="actionModalOpen = false"
                                class="rounded-xl border border-[#E5E7EB] px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                            >
                                Cancel
                            </button>

                            <button
                                type="submit"
                                :disabled="actionForm.processing"
                                class="rounded-xl px-5 py-2 text-xs font-bold text-white shadow-xs disabled:opacity-50"
                                :class="{
                                    'bg-emerald-600 hover:bg-emerald-700': actionType === 'completed',
                                    'bg-blue-600 hover:bg-blue-700': actionType === 'processing',
                                    'bg-rose-600 hover:bg-rose-700': actionType === 'rejected',
                                }"
                            >
                                <span v-if="actionForm.processing">Saving...</span>
                                <span v-else-if="actionType === 'completed'">Confirm & Disburse</span>
                                <span v-else-if="actionType === 'processing'">Set to Processing</span>
                                <span v-else>Confirm Decline</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </AdminLayout>
</template>

