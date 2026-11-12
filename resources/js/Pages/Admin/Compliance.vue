<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    title: {
        type: String,
        default: 'Seller Compliance & AI Verification',
    },

    eyebrow: {
        type: String,
        default: 'Catalog Quality Assurance',
    },

    description: {
        type: String,
        default: '',
    },

    active: {
        type: String,
        default: 'compliance',
    },

    products: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
            meta: {},
        }),
    },

    metrics: {
        type: Object,
        default: () => ({
            total: 0,
            auto_approved: 0,
            pending_review: 0,
            ai_failed: 0,
            flagged: 0,
        }),
    },

    filters: {
        type: Object,
        default: () => ({
            search: '',
            status: 'all',
            approval_method: 'all',
            ai_status: 'all',
        }),
    },
})

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const search = ref(props.filters?.search ?? '')
const status = ref(props.filters?.status ?? 'all')
const approvalMethod = ref(props.filters?.approval_method ?? 'all')
const aiStatus = ref(props.filters?.ai_status ?? 'all')

const selectedProduct = ref(null)
const selectedAction = ref('approve')
const note = ref('')
const processing = ref(false)
const isReverifying = ref(false)

const showReviewModal = ref(false)
const showHistoryModal = ref(false)

/*
|--------------------------------------------------------------------------
| Computed
|--------------------------------------------------------------------------
*/

const rows = computed(() => {
    return props.products?.data ?? []
})

const requiresNote = computed(() => {
    return (
        selectedAction.value === 'reject' ||
        selectedAction.value === 'flag' ||
        selectedAction.value === 'suspend'
    )
})

const canSubmitReview = computed(() => {
    if (processing.value || !selectedProduct.value || !selectedAction.value) {
        return false
    }

    if (requiresNote.value && !note.value.trim()) {
        return false
    }

    return true
})

/*
|--------------------------------------------------------------------------
| Status Classes
|--------------------------------------------------------------------------
*/

const statusClasses = {
    pending:
        'bg-[#FFF8E7] text-[#B7791F] ring-1 ring-[#F4B942]/30',

    approved:
        'bg-emerald-50 text-[#16865A] ring-1 ring-emerald-200',

    inactive:
        'bg-red-50 text-[#C94B4B] ring-1 ring-red-200',

    draft:
        'bg-slate-100 text-slate-600 ring-1 ring-slate-200',
}

const sellerStatusClasses = (value) => {
    switch (value) {
        case 'approved':
        case 'active':
            return 'bg-emerald-50 text-[#16865A] ring-1 ring-emerald-200'

        case 'suspended':
            return 'bg-red-50 text-[#C94B4B] ring-1 ring-red-200'

        case 'pending':
            return 'bg-[#FFF8E7] text-[#B7791F] ring-1 ring-[#F4B942]/30'

        default:
            return 'bg-slate-100 text-slate-600 ring-1 ring-slate-200'
    }
}

const statusDot = (value) => {
    switch (value) {
        case 'approved':
            return 'bg-[#22A06B]'

        case 'pending':
            return 'bg-[#F4B942]'

        case 'inactive':
            return 'bg-[#E85D5D]'

        case 'draft':
            return 'bg-slate-400'

        case 'suspended':
            return 'bg-[#E85D5D]'

        default:
            return 'bg-slate-400'
    }
}

/*
|--------------------------------------------------------------------------
| AI Verification Helpers
|--------------------------------------------------------------------------
*/

const getAiBadge = (product) => {
    const aiStatusVal = product?.ai_verification_status || 'pending'
    const confidence = product?.ai_confidence_score ? `${Math.round(product.ai_confidence_score * 100)}%` : null

    switch (aiStatusVal) {
        case 'passed':
            return {
                label: confidence ? `AI Passed (${confidence})` : 'AI Passed',
                class: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-300',
                dot: 'bg-emerald-500',
            }

        case 'failed':
            return {
                label: confidence ? `Mismatch (${confidence})` : 'AI Mismatch',
                class: 'bg-rose-50 text-rose-700 ring-1 ring-rose-300',
                dot: 'bg-rose-500',
            }

        case 'uncertain':
            return {
                label: confidence ? `Low Conf. (${confidence})` : 'Low Confidence',
                class: 'bg-amber-50 text-amber-700 ring-1 ring-amber-300',
                dot: 'bg-amber-500',
            }

        case 'unclear_image':
            return {
                label: 'Unclear Image',
                class: 'bg-orange-50 text-orange-700 ring-1 ring-orange-300',
                dot: 'bg-orange-500',
            }

        case 'policy_violation':
            return {
                label: 'Policy Violation',
                class: 'bg-red-50 text-red-700 ring-1 ring-red-300',
                dot: 'bg-red-500',
            }

        case 'service_error':
            return {
                label: 'AI Unavailable',
                class: 'bg-slate-100 text-slate-700 ring-1 ring-slate-300',
                dot: 'bg-slate-400',
            }

        case 'seller_ineligible':
            return {
                label: 'Seller Ineligible',
                class: 'bg-amber-50 text-amber-700 ring-1 ring-amber-300',
                dot: 'bg-amber-500',
            }

        default:
            return {
                label: 'Pending Verification',
                class: 'bg-slate-100 text-slate-600 ring-1 ring-slate-200',
                dot: 'bg-slate-400',
            }
    }
}

const actionLabels = {
    approve: 'Approve product',
    reject: 'Reject product listing',
    flag: 'Flag product',
    suspend: 'Suspend seller',
}

const reviewActionClasses = {
    approved:
        'bg-emerald-50 text-[#16865A] ring-1 ring-emerald-200',

    rejected:
        'bg-red-50 text-[#C94B4B] ring-1 ring-red-200',

    flagged:
        'bg-[#FFF8E7] text-[#B7791F] ring-1 ring-[#F4B942]/30',

    seller_suspended:
        'bg-red-50 text-[#C94B4B] ring-1 ring-red-200',

    seller_unsuspended:
        'bg-emerald-50 text-[#16865A] ring-1 ring-emerald-200',
}

/*
|--------------------------------------------------------------------------
| Filter Controls
|--------------------------------------------------------------------------
*/

const applyFilters = () => {
    router.get(
        route('admin.compliance'),
        {
            search: search.value.trim(),
            status: status.value,
            approval_method: approvalMethod.value,
            ai_status: aiStatus.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    )
}

const clearFilters = () => {
    search.value = ''
    status.value = 'all'
    approvalMethod.value = 'all'
    aiStatus.value = 'all'

    router.get(
        route('admin.compliance'),
        {},
        {
            preserveState: false,
            preserveScroll: true,
            replace: true,
        }
    )
}

/*
|--------------------------------------------------------------------------
| Modal Handlers
|--------------------------------------------------------------------------
*/

const openReview = (product, defaultAction = 'approve') => {
    selectedProduct.value = product
    selectedAction.value = defaultAction
    note.value = product.rejection_reason || ''
    showReviewModal.value = true
}

const closeReview = () => {
    if (processing.value || isReverifying.value) {
        return
    }

    showReviewModal.value = false
    selectedProduct.value = null
    selectedAction.value = 'approve'
    note.value = ''
}

const submitReview = () => {
    if (!canSubmitReview.value) {
        return
    }

    processing.value = true

    router.patch(
        route('admin.compliance.status', selectedProduct.value.id),
        {
            action: selectedAction.value,
            note: note.value.trim() || null,
        },
        {
            preserveState: false,
            preserveScroll: true,

            onSuccess: () => {
                showReviewModal.value = false
                selectedProduct.value = null
                selectedAction.value = 'approve'
                note.value = ''
            },

            onError: () => {
                processing.value = false
            },

            onFinish: () => {
                processing.value = false
            },
        }
    )
}

const rerunAiVerification = (product) => {
    if (!product?.id || isReverifying.value) {
        return
    }

    isReverifying.value = true

    router.post(
        route('admin.compliance.verify-ai', product.id),
        {},
        {
            preserveState: false,
            preserveScroll: true,

            onFinish: () => {
                isReverifying.value = false
                showReviewModal.value = false
            },
        }
    )
}

const unsuspendSeller = (product) => {
    if (!product?.id) {
        return
    }

    const seller = sellerName(product)

    const confirmed = window.confirm(
        `Are you sure you want to unsuspend ${seller}?`
    )

    if (!confirmed) {
        return
    }

    processing.value = true

    router.patch(
        route('admin.compliance.unsuspend', product.id),
        {},
        {
            preserveState: false,
            preserveScroll: true,

            onError: () => {
                processing.value = false
            },

            onFinish: () => {
                processing.value = false
            },
        }
    )
}

const openHistory = (product) => {
    selectedProduct.value = product
    showHistoryModal.value = true
}

const closeHistory = () => {
    showHistoryModal.value = false
    selectedProduct.value = null
}

const formatDate = (value) => {
    if (!value) {
        return '—'
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return value
    }

    return date.toLocaleString()
}

const formatAction = (action) => {
    return {
        approved: 'Approved',
        rejected: 'Rejected',
        flagged: 'Flagged',
        seller_suspended: 'Seller suspended',
        seller_unsuspended: 'Seller unsuspended',
    }[action] ?? action
}

const sellerName = (product) => {
    return (
        product?.seller?.store_name ||
        product?.seller?.name ||
        'Unknown seller'
    )
}

const formatPrice = (val) => {
    return Number(val || 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}
</script>

<template>
    <Head :title="title" />

    <AdminLayout :active="active">
        <div class="w-full">

            <!-- ===================================================== -->
            <!-- PAGE HEADER -->
            <!-- ===================================================== -->

            <div
                class="flex flex-col gap-4 border-b border-[#E5E7EB] pb-5 sm:flex-row sm:items-end sm:justify-between"
            >
                <div class="min-w-0">
                    <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-[#087F8C]">
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-teal-500"></span>
                        <span>{{ eyebrow }}</span>
                    </div>

                    <h1 class="mt-1 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                        {{ title }}
                    </h1>

                    <p v-if="description" class="mt-0.5 text-xs text-slate-500">
                        {{ description }}
                    </p>
                </div>

                <Link
                    :href="route('admin.dashboard')"
                    class="inline-flex w-fit shrink-0 items-center gap-2 rounded-xl border border-[#E5E7EB] bg-white px-3.5 py-2 text-xs font-semibold text-[#64748B] shadow-sm transition hover:border-[#087F8C]/30 hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                >
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10.707 2.293a1 1 0 010 1.414L4.414 10l6.293 6.293a.75.75 0 01-1.06 1.06l-7-7a.75.75 0 010-1.06l7-7a.75.75 0 011.06 0z" clip-rule="evenodd" />
                        <path fill-rule="evenodd" d="M4 9.25a.75.75 0 000 1.5h12a.75.75 0 000-1.5H4z" clip-rule="evenodd" />
                    </svg>
                    Dashboard
                </Link>
            </div>

            <!-- ===================================================== -->
            <!-- AI METRICS STRIP -->
            <!-- ===================================================== -->

            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4 lg:gap-4">
                <!-- Total Listings -->
                <div class="group relative overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-start justify-between gap-3">
                        <span class="text-xs font-semibold text-[#64748B]">Total Listings</span>
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                            </svg>
                        </span>
                    </div>
                    <p class="mt-2 truncate text-xl font-bold tracking-tight text-[#1F2937] sm:text-2xl">{{ metrics.total }}</p>
                    <p class="mt-1 truncate text-[11px] font-medium text-[#94A3B8]">Across all categories</p>
                    <div class="absolute bottom-0 left-0 h-0.5 w-full origin-left scale-x-0 transition-transform duration-200 group-hover:scale-x-100 bg-slate-500" />
                </div>

                <!-- Auto-Approved (AI) -->
                <div class="group relative overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-start justify-between gap-3">
                        <span class="text-xs font-semibold text-[#64748B]">Auto-Approved (AI)</span>
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" />
                            </svg>
                        </span>
                    </div>
                    <p class="mt-2 truncate text-xl font-bold tracking-tight text-[#087F8C] sm:text-2xl">{{ metrics.auto_approved }}</p>
                    <p class="mt-1 truncate text-[11px] font-medium text-[#087F8C]/80">Passed vision verification</p>
                    <div class="absolute bottom-0 left-0 h-0.5 w-full origin-left scale-x-0 transition-transform duration-200 group-hover:scale-x-100 bg-[#087F8C]" />
                </div>

                <!-- Pending Review -->
                <div class="group relative overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-start justify-between gap-3">
                        <span class="text-xs font-semibold text-[#64748B]">Pending Review</span>
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </span>
                    </div>
                    <p class="mt-2 truncate text-xl font-bold tracking-tight text-amber-600 sm:text-2xl">{{ metrics.pending_review }}</p>
                    <p class="mt-1 truncate text-[11px] font-medium text-amber-600/80">{{ metrics.ai_failed }} flagged by AI</p>
                    <div class="absolute bottom-0 left-0 h-0.5 w-full origin-left scale-x-0 transition-transform duration-200 group-hover:scale-x-100 bg-amber-500" />
                </div>

                <!-- Flagged / Inactive -->
                <div class="group relative overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md">
                    <div class="flex items-start justify-between gap-3">
                        <span class="text-xs font-semibold text-[#64748B]">Flagged / Inactive</span>
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0-10.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.002A11.959 11.959 0 0 1 12 2.714Zm0 14.286h.008v.008H12v-.008Z" />
                            </svg>
                        </span>
                    </div>
                    <p class="mt-2 truncate text-xl font-bold tracking-tight text-rose-600 sm:text-2xl">{{ metrics.flagged }}</p>
                    <p class="mt-1 truncate text-[11px] font-medium text-rose-600/80">Hidden from storefront</p>
                    <div class="absolute bottom-0 left-0 h-0.5 w-full origin-left scale-x-0 transition-transform duration-200 group-hover:scale-x-100 bg-rose-500" />
                </div>
            </div>

            <!-- ===================================================== -->
            <!-- FILTER CARD -->
            <!-- ===================================================== -->

            <div class="mt-5 rounded-2xl border border-[#E5E7EB] bg-white shadow-sm">
                <div class="border-b border-[#E5E7EB] px-5 py-4">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M3 4.75A1.75 1.75 0 014.75 3h10.5A1.75 1.75 0 0117 4.75v.5a1.75 1.75 0 01-.513 1.237L12 10.72v4.53a1.75 1.75 0 01-.768 1.45l-1.5 1A1.75 1.75 0 017 16.25v-5.53L2.513 6.487A1.75 1.75 0 012 5.25v-.5A1.75 1.75 0 013.75 3H3zm.5 1.25l4.28 4.28a.75.75 0 01.22.53v5.44l1-.667v-4.773a.75.75 0 01.22-.53L13.5 6H3.5z" clip-rule="evenodd" />
                            </svg>
                        </div>

                        <div>
                            <h2 class="text-base font-bold text-[#1F2937]">Compliance & AI Verification Filters</h2>
                            <p class="mt-0.5 text-xs text-[#94A3B8]">Filter by search term, status, AI outcome, or approval method.</p>
                        </div>
                    </div>
                </div>

                <div class="p-4">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_160px_160px_180px_auto] lg:items-end">
                        <!-- SEARCH -->
                        <div>
                            <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">
                                Search
                            </label>
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search product, seller, category, or AI finding..."
                                class="w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-2 text-xs text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white"
                                @keyup.enter="applyFilters"
                            />
                        </div>

                        <!-- STATUS -->
                        <div>
                            <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">
                                Product status
                            </label>
                            <select
                                v-model="status"
                                class="w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2 text-xs text-[#1F2937] outline-none focus:border-[#087F8C]"
                                @change="applyFilters"
                            >
                                <option value="all">All statuses</option>
                                <option value="pending">Pending review</option>
                                <option value="approved">Approved</option>
                                <option value="inactive">Flagged / Inactive</option>
                                <option value="draft">Draft</option>
                            </select>
                        </div>

                        <!-- APPROVAL METHOD -->
                        <div>
                            <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">
                                Approval method
                            </label>
                            <select
                                v-model="approvalMethod"
                                class="w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2 text-xs text-[#1F2937] outline-none focus:border-[#087F8C]"
                                @change="applyFilters"
                            >
                                <option value="all">All methods</option>
                                <option value="automatic">Automatic (AI)</option>
                                <option value="manual">Manual Admin</option>
                            </select>
                        </div>

                        <!-- AI VERIFICATION STATUS -->
                        <div>
                            <label class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">
                                AI Verification
                            </label>
                            <select
                                v-model="aiStatus"
                                class="w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2 text-xs text-[#1F2937] outline-none focus:border-[#087F8C]"
                                @change="applyFilters"
                            >
                                <option value="all">All AI outcomes</option>
                                <option value="passed">AI Passed</option>
                                <option value="failed">Mismatch Detected</option>
                                <option value="uncertain">Low Confidence</option>
                                <option value="unclear_image">Unclear Image</option>
                                <option value="service_error">Service Error</option>
                                <option value="pending">Pending AI</option>
                            </select>
                        </div>

                        <!-- BUTTONS -->
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="rounded-xl bg-[#087F8C] px-4 py-2 text-xs font-semibold text-white transition hover:bg-[#066D78]"
                                @click="applyFilters"
                            >
                                Filter
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border border-[#E5E7EB] bg-white px-3 py-2 text-xs font-semibold text-[#64748B] transition hover:bg-slate-50"
                                @click="clearFilters"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===================================================== -->
            <!-- PRODUCTS COMPLIANCE TABLE -->
            <!-- ===================================================== -->

            <div class="mt-6 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-[#E5E7EB] px-5 py-4">
                    <div class="flex items-center gap-2">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M4 3.5A1.5 1.5 0 015.5 2h9A1.5 1.5 0 0116 3.5v13a1.5 1.5 0 01-1.5 1.5h-9A1.5 1.5 0 014 16.5v-13zM6 5.25A.75.75 0 016.75 4.5h6.5a.75.75 0 010 1.5h-6.5A.75.75 0 016 5.25zm0 3.5A.75.75 0 016.75 8h6.5a.75.75 0 010 1.5h-6.5A.75.75 0 016 8.75zm0 3.5a.75.75 0 01.75-.75h4a.75.75 0 010 1.5h-4a.75.75 0 01-.75-.75z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-[#1F2937]">Product Listings & Approvals</h2>
                            <p class="text-[11px] text-[#94A3B8]">Automated and manual compliance decisions</p>
                        </div>
                    </div>

                    <span class="rounded-full bg-[#E8F7F6] px-3 py-1 text-xs font-bold text-[#087F8C]">
                        {{ rows.length }} listing{{ rows.length === 1 ? '' : 's' }}
                    </span>
                </div>

                <div v-if="rows.length" class="overflow-x-auto">
                    <table class="w-full min-w-[1050px] text-left text-xs">
                        <thead class="border-b border-[#E5E7EB] bg-[#F8FAF9] text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]">
                            <tr>
                                <th class="px-5 py-3.5">Product</th>
                                <th class="px-5 py-3.5">Seller</th>
                                <th class="px-5 py-3.5">Category</th>
                                <th class="px-5 py-3.5 text-center">Approval Mode</th>
                                <th class="px-5 py-3.5">AI Verification</th>
                                <th class="px-5 py-3.5 text-center">Status</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-[#E5E7EB]">
                            <tr v-for="row in rows" :key="row.id" class="transition hover:bg-[#F8FAF9]/70">
                                <!-- PRODUCT -->
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-50 ring-1 ring-slate-200">
                                            <img
                                                v-if="row.image_path"
                                                :src="row.image_path.startsWith('http') ? row.image_path : `/storage/${row.image_path}`"
                                                :alt="row.name"
                                                class="h-full w-full object-cover"
                                            />
                                            <span v-else class="text-slate-300">No img</span>
                                        </div>

                                        <div class="min-w-0 max-w-[220px]">
                                            <p class="truncate font-bold text-[#1F2937]" :title="row.name">
                                                {{ row.name }}
                                            </p>
                                            <p class="mt-0.5 text-[11px] font-semibold text-[#087F8C]">
                                                ₱{{ formatPrice(row.price) }}
                                            </p>
                                            <p class="text-[10px] text-[#94A3B8]">ID: #{{ row.id }}</p>
                                        </div>
                                    </div>
                                </td>

                                <!-- SELLER -->
                                <td class="px-5 py-4">
                                    <div class="min-w-0">
                                        <p class="truncate font-bold text-[#1F2937]">{{ sellerName(row) }}</p>
                                        <p class="mt-0.5 truncate text-[11px] text-[#64748B]">{{ row.seller?.email || '—' }}</p>
                                        <span
                                            class="mt-1 inline-flex items-center gap-1 rounded-full px-2 py-0.2 text-[9px] font-bold"
                                            :class="sellerStatusClasses(row.seller?.status)"
                                        >
                                            {{ row.seller?.status || 'Unknown' }}
                                        </span>
                                    </div>
                                </td>

                                <!-- CATEGORY -->
                                <td class="px-5 py-4 font-medium text-[#475569]">
                                    {{ row.category?.name || 'Unassigned' }}
                                </td>

                                <!-- APPROVAL METHOD -->
                                <td class="px-5 py-4 text-center">
                                    <span
                                        v-if="row.approval_method === 'automatic'"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-[#E8F7F6] px-2.5 py-1 text-[11px] font-bold text-[#087F8C] ring-1 ring-[#087F8C]/20"
                                        title="Approved automatically by AI vision verification"
                                    >
                                        <svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                                        </svg>
                                        Automatic
                                    </span>
                                    <span
                                        v-else-if="row.status === 'approved'"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-700 ring-1 ring-slate-200"
                                    >
                                        <svg class="h-3 w-3 shrink-0 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                        </svg>
                                        Manual
                                    </span>
                                    <span v-else class="text-slate-400 text-[11px]">—</span>
                                </td>

                                <!-- AI VERIFICATION -->
                                <td class="px-5 py-4">
                                    <div class="min-w-[150px]">
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-bold"
                                            :class="getAiBadge(row).class"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full" :class="getAiBadge(row).dot"></span>
                                            {{ getAiBadge(row).label }}
                                        </span>

                                        <p
                                            v-if="row.ai_verification_explanation"
                                            class="mt-1 line-clamp-1 max-w-[200px] text-[10px] text-[#64748B]"
                                            :title="row.ai_verification_explanation"
                                        >
                                            {{ row.ai_verification_explanation }}
                                        </p>
                                    </div>
                                </td>

                                <!-- STATUS -->
                                <td class="px-5 py-4 text-center">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                                        :class="statusClasses[row.status] || 'bg-slate-100 text-slate-600'"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full" :class="statusDot(row.status)"></span>
                                        {{ row.status }}
                                    </span>
                                </td>

                                <!-- ACTIONS -->
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- Open Review & AI Details Modal -->
                                        <button
                                            type="button"
                                            class="rounded-lg bg-[#087F8C] px-3 py-1.5 text-xs font-bold text-white transition hover:bg-[#066D78]"
                                            @click="openReview(row, row.status === 'approved' ? 'flag' : 'approve')"
                                        >
                                            Review
                                        </button>

                                        <button
                                            type="button"
                                            class="rounded-lg border border-[#E5E7EB] bg-white px-2.5 py-1.5 text-xs font-semibold text-[#64748B] transition hover:bg-slate-50"
                                            @click="openHistory(row)"
                                        >
                                            History
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- EMPTY STATE -->
                <div v-else class="py-12 text-center">
                    <p class="text-sm font-bold text-slate-700">No products match the selected criteria.</p>
                    <button
                        type="button"
                        class="mt-2 text-xs font-bold text-[#087F8C] hover:underline"
                        @click="clearFilters"
                    >
                        Reset filters
                    </button>
                </div>

                <!-- PAGINATION -->
                <div v-if="products.links && products.links.length > 3" class="flex items-center justify-center gap-1 border-t border-[#E5E7EB] p-4">
                    <template v-for="(link, i) in products.links" :key="i">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="rounded-lg px-3 py-1 text-xs font-bold transition"
                            :class="link.active ? 'bg-[#087F8C] text-white' : 'text-slate-600 hover:bg-slate-100'"
                            v-html="link.label"
                        />
                        <span v-else class="px-2 text-xs text-slate-300" v-html="link.label"></span>
                    </template>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- COMPLIANCE & AI REVIEW MODAL -->
        <!-- ========================================================= -->

        <div
            v-if="showReviewModal && selectedProduct"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-xs"
            @click.self="closeReview"
        >
            <div class="max-h-[92vh] w-full max-w-xl overflow-y-auto rounded-2xl border border-[#E5E7EB] bg-white shadow-2xl">
                <!-- HEADER -->
                <div class="flex items-center justify-between border-b border-[#E5E7EB] px-6 py-4">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-[#087F8C]">Listing Compliance Audit</span>
                        <h2 class="text-base font-bold text-[#1F2937]">Review Product: {{ selectedProduct.name }}</h2>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-slate-600 text-lg" @click="closeReview">✕</button>
                </div>

                <div class="p-6 space-y-5">
                    <!-- PRODUCT HERO -->
                    <div class="flex items-start gap-4 rounded-xl border border-slate-100 bg-[#F8FAF9] p-4">
                        <div class="h-20 w-20 shrink-0 overflow-hidden rounded-xl border border-slate-200 bg-white">
                            <img
                                v-if="selectedProduct.image_path"
                                :src="selectedProduct.image_path.startsWith('http') ? selectedProduct.image_path : `/storage/${selectedProduct.image_path}`"
                                :alt="selectedProduct.name"
                                class="h-full w-full object-cover"
                            />
                            <div v-else class="flex h-full w-full items-center justify-center text-xs text-slate-400">No Image</div>
                        </div>

                        <div class="min-w-0 flex-1 text-xs">
                            <h3 class="text-sm font-bold text-[#1F2937]">{{ selectedProduct.name }}</h3>
                            <p class="mt-1 font-semibold text-[#087F8C]">₱{{ formatPrice(selectedProduct.price) }}</p>
                            <p class="mt-1 text-slate-500">
                                Seller: <span class="font-bold text-slate-700">{{ sellerName(selectedProduct) }}</span>
                                · Category: <span class="font-semibold">{{ selectedProduct.category?.name || 'Unassigned' }}</span>
                            </p>
                            <p v-if="selectedProduct.description" class="mt-2 line-clamp-2 text-slate-600 italic">
                                "{{ selectedProduct.description }}"
                            </p>
                        </div>
                    </div>

                    <!-- AI VERIFICATION DIAGNOSTICS SECTION -->
                    <div class="rounded-xl border border-teal-100 bg-teal-50/40 p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                                    </svg>
                                </span>
                                <h4 class="text-xs font-bold text-[#087F8C] uppercase tracking-wider">
                                    AI Vision Verification Diagnostics
                                </h4>
                            </div>

                            <span
                                class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[10px] font-bold"
                                :class="getAiBadge(selectedProduct).class"
                            >
                                {{ getAiBadge(selectedProduct).label }}
                            </span>
                        </div>

                        <!-- Confidence Bar -->
                        <div v-if="selectedProduct.ai_confidence_score" class="mt-3">
                            <div class="flex justify-between text-[11px] font-semibold text-slate-600">
                                <span>Verification Confidence:</span>
                                <span>{{ Math.round(selectedProduct.ai_confidence_score * 100) }}%</span>
                            </div>
                            <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-slate-200">
                                <div
                                    class="h-full transition-all duration-300"
                                    :class="
                                        selectedProduct.ai_confidence_score >= 0.85
                                            ? 'bg-emerald-500'
                                            : (selectedProduct.ai_confidence_score >= 0.6 ? 'bg-amber-500' : 'bg-rose-500')
                                    "
                                    :style="{ width: `${Math.round(selectedProduct.ai_confidence_score * 100)}%` }"
                                ></div>
                            </div>
                        </div>

                        <!-- AI Explanation -->
                        <div class="mt-3 rounded-lg border border-slate-200/80 bg-white p-3 text-xs">
                            <p class="font-bold text-slate-700">Detailed AI Analysis:</p>
                            <p class="mt-1 leading-relaxed text-slate-600">
                                {{ selectedProduct.ai_verification_explanation || 'No verification diagnostic recorded yet.' }}
                            </p>

                            <div v-if="selectedProduct.ai_verified_at" class="mt-2 text-[10px] text-slate-400">
                                Evaluated at: {{ formatDate(selectedProduct.ai_verified_at) }}
                            </div>
                        </div>

                        <!-- Re-run AI Button -->
                        <div class="mt-3 flex justify-end">
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-lg border border-[#087F8C]/40 bg-white px-3.5 py-1.5 text-xs font-bold text-[#087F8C] shadow-2xs transition hover:bg-[#E8F7F6] disabled:opacity-50"
                                :disabled="isReverifying"
                                @click="rerunAiVerification(selectedProduct)"
                            >
                                <svg
                                    v-if="isReverifying"
                                    class="h-3.5 w-3.5 animate-spin text-[#087F8C]"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                >
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg
                                    v-else
                                    class="h-3.5 w-3.5 text-[#087F8C]"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                                <span>{{ isReverifying ? 'Running AI Vision...' : 'Re-run AI Verification' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- ADMIN ACTION FORM -->
                    <div>
                        <label class="block text-xs font-bold text-slate-800">Choose Admin Action *</label>
                        <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-1.5 rounded-xl border p-2.5 text-center text-xs font-bold transition"
                                :class="selectedAction === 'approve' ? 'border-emerald-500 bg-emerald-50 text-emerald-800 ring-1 ring-emerald-400' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                                @click="selectedAction = 'approve'"
                            >
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                Approve
                            </button>

                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-1.5 rounded-xl border p-2.5 text-center text-xs font-bold transition"
                                :class="selectedAction === 'reject' ? 'border-rose-500 bg-rose-50 text-rose-800 ring-1 ring-rose-400' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                                @click="selectedAction = 'reject'"
                            >
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                                Reject
                            </button>

                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-1.5 rounded-xl border p-2.5 text-center text-xs font-bold transition"
                                :class="selectedAction === 'flag' ? 'border-amber-500 bg-amber-50 text-amber-800 ring-1 ring-amber-400' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                                @click="selectedAction = 'flag'"
                            >
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5" />
                                </svg>
                                Flag Inactive
                            </button>

                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-1.5 rounded-xl border p-2.5 text-center text-xs font-bold transition"
                                :class="selectedAction === 'suspend' ? 'border-red-600 bg-red-50 text-red-800 ring-1 ring-red-400' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                                @click="selectedAction = 'suspend'"
                            >
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                                Suspend Seller
                            </button>
                        </div>

                        <!-- Review Note / Rejection Reason -->
                        <div class="mt-4">
                            <div class="flex items-center justify-between text-xs font-semibold text-slate-700">
                                <span>Reason / Note <span v-if="requiresNote" class="text-rose-500">*</span></span>
                                <span class="text-[10px] text-slate-400">{{ note.length }}/1000</span>
                            </div>
                            <textarea
                                v-model="note"
                                rows="3"
                                maxlength="1000"
                                :placeholder="selectedAction === 'reject' ? 'Explain why this product listing is rejected (sent to seller)...' : 'Add compliance review note...'"
                                class="mt-1 w-full rounded-xl border border-slate-200 bg-[#F8FAF9] p-3 text-xs text-slate-800 outline-none focus:border-[#087F8C] focus:bg-white"
                            ></textarea>
                            <p v-if="requiresNote && !note.trim()" class="mt-1 text-[11px] text-rose-500">
                                A reason is required when rejecting, flagging, or suspending.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- FOOTER -->
                <div class="flex justify-end gap-2 border-t border-[#E5E7EB] bg-[#F8FAF9] px-6 py-4">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                        @click="closeReview"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-xl px-5 py-2 text-xs font-bold text-white transition disabled:opacity-50"
                        :class="
                            selectedAction === 'approve'
                                ? 'bg-emerald-600 hover:bg-emerald-700'
                                : (selectedAction === 'reject' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-[#087F8C] hover:bg-[#066D78]')
                        "
                        :disabled="!canSubmitReview"
                        @click="submitReview"
                    >
                        {{ processing ? 'Processing...' : `Confirm ${actionLabels[selectedAction]}` }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- HISTORY MODAL -->
        <!-- ========================================================= -->

        <div
            v-if="showHistoryModal && selectedProduct"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-xs"
            @click.self="closeHistory"
        >
            <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-[#E5E7EB] px-6 py-4">
                    <h3 class="text-sm font-bold text-slate-800">Compliance History: {{ selectedProduct.name }}</h3>
                    <button type="button" class="text-slate-400 hover:text-slate-600" @click="closeHistory">✕</button>
                </div>

                <div class="max-h-96 overflow-y-auto p-6">
                    <div v-if="selectedProduct.compliance_reviews?.length" class="space-y-3">
                        <div
                            v-for="rev in selectedProduct.compliance_reviews"
                            :key="rev.id"
                            class="rounded-xl border border-slate-100 bg-[#F8FAF9] p-3 text-xs"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="rounded-md px-2 py-0.5 text-[10px] font-bold"
                                    :class="reviewActionClasses[rev.action] || 'bg-slate-100 text-slate-700'"
                                >
                                    {{ formatAction(rev.action) }}
                                </span>
                                <span class="text-[10px] text-slate-400">{{ formatDate(rev.reviewed_at) }}</span>
                            </div>
                            <p v-if="rev.note" class="mt-2 text-slate-700">{{ rev.note }}</p>
                            <p class="mt-1 text-[10px] text-slate-400">
                                Reviewer: {{ rev.admin?.name || 'Automated AI System' }}
                            </p>
                        </div>
                    </div>
                    <div v-else class="py-8 text-center text-xs text-slate-400">
                        No previous compliance records for this product.
                    </div>
                </div>

                <div class="border-t border-[#E5E7EB] bg-[#F8FAF9] px-6 py-3 text-right">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                        @click="closeHistory"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>

    </AdminLayout>
</template>
