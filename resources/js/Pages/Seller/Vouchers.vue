<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'

const page = usePage()

const props = defineProps({
    vouchers: {
        type: Array,
        default: () => [],
    },
    analytics: {
        type: Object,
        default: () => ({
            total_vouchers: 0,
            active_vouchers: 0,
            total_redemptions: 0,
            total_revenue_generated: 0,
            total_discount_funded: 0,
        }),
    },
    products: {
        type: Array,
        default: () => [],
    },
})

const showModal = ref(false)
const editingVoucher = ref(null)
const productSearch = ref('')

// Reactive current time for live expiry countdowns
const nowTime = ref(Date.now())
let timerInterval = null

onMounted(() => {
    timerInterval = setInterval(() => {
        nowTime.value = Date.now()
    }, 30000)
})

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval)
})

const form = useForm({
    code: '',
    type: 'percentage',
    value: '',
    max_discount: '',
    per_customer_limit: 1,
    target_type: 'all',
    targeted_product_ids: [],
    min_spend: '',
    usage_limit: '',
    starts_at: '',
    expires_at: '',
    is_active: true,
})

/*
|--------------------------------------------------------------------------
| Summary & Analytics
|--------------------------------------------------------------------------
*/

const totalVouchers = computed(() => props.analytics.total_vouchers ?? props.vouchers.length)
const activeVouchers = computed(() => props.analytics.active_vouchers ?? props.vouchers.filter(v => v.is_active).length)
const totalUses = computed(() => props.analytics.total_redemptions ?? props.vouchers.reduce((t, v) => t + Number(v.used_count ?? 0), 0))
const totalRevenue = computed(() => Number(props.analytics.total_revenue_generated ?? 0))
const totalDiscountFunded = computed(() => Number(props.analytics.total_discount_funded ?? 0))

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const formatDateForInput = date => {
    if (!date) return ''
    return String(date).slice(0, 10)
}

const formatDate = date => {
    if (!date) return '—'
    const parsed = new Date(`${String(date).slice(0, 10)}T00:00:00`)
    if (Number.isNaN(parsed.getTime())) return '—'
    return parsed.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}

const money = value => {
    const amount = Number(value ?? 0)
    return `₱${amount.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`
}

const discountLabel = voucher => {
    if (voucher.type === 'free_shipping') {
        if (voucher.max_discount && Number(voucher.max_discount) > 0) {
            return `Free Shipping (Cap ${money(voucher.max_discount)})`
        }
        return 'Free Shipping'
    }

    if (voucher.type === 'percentage') {
        const base = `${Number(voucher.value)}% OFF`
        if (voucher.max_discount && Number(voucher.max_discount) > 0) {
            return `${base} (Cap ${money(voucher.max_discount)})`
        }
        return base
    }

    return `${money(voucher.value)} OFF`
}

const targetScopeLabel = voucher => {
    if (voucher.target_type === 'product') {
        const count = Array.isArray(voucher.targeted_product_ids) ? voucher.targeted_product_ids.length : 0
        return `${count} Targeted Product${count === 1 ? '' : 's'}`
    }
    return 'Storewide (All Products)'
}

const getExpiryInfo = voucher => {
    if (!voucher.expires_at) {
        return { label: 'No Expiry', isExpiringSoon: false, isExpired: false, badgeClass: 'bg-slate-100 text-slate-700' }
    }

    const expiryDate = new Date(`${String(voucher.expires_at).slice(0, 10)}T23:59:59`)
    const diffMs = expiryDate.getTime() - nowTime.value

    if (diffMs <= 0) {
        return {
            label: 'Expired',
            subtext: `Expired ${formatDate(voucher.expires_at)}`,
            isExpiringSoon: false,
            isExpired: true,
            badgeClass: 'bg-red-50 text-red-600 border border-red-200',
        }
    }

    const diffHours = Math.floor(diffMs / (1000 * 60 * 60))
    const diffMins = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60))
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24))

    if (diffHours <= 48 && voucher.is_active) {
        return {
            label: 'Expiring Soon',
            subtext: `${diffHours}h ${diffMins}m left`,
            isExpiringSoon: true,
            isExpired: false,
            badgeClass: 'bg-amber-50 text-amber-700 border border-amber-300 font-bold animate-pulse',
        }
    }

    return {
        label: `${diffDays} days left`,
        subtext: `Expires ${formatDate(voucher.expires_at)}`,
        isExpiringSoon: false,
        isExpired: false,
        badgeClass: 'bg-teal-50 text-teal-700 border border-teal-200',
    }
}

const statusLabel = voucher => {
    if (!voucher.is_active) return 'Inactive'

    if (voucher.usage_limit && Number(voucher.used_count ?? 0) >= Number(voucher.usage_limit)) {
        return 'Limit Reached'
    }

    const today = new Date()
    today.setHours(0, 0, 0, 0)

    if (voucher.starts_at) {
        const start = new Date(`${String(voucher.starts_at).slice(0, 10)}T00:00:00`)
        if (start > today) return 'Scheduled'
    }

    const expiryInfo = getExpiryInfo(voucher)
    if (expiryInfo.isExpired) return 'Expired'
    if (expiryInfo.isExpiringSoon) return 'Expiring Soon'

    return 'Active'
}

const statusClass = voucher => {
    const status = statusLabel(voucher)
    switch (status) {
        case 'Active':
            return 'border-[#BBE7D3] bg-[#ECFDF5] text-[#16845A]'
        case 'Expiring Soon':
            return 'border-amber-300 bg-amber-50 text-amber-700 font-bold'
        case 'Scheduled':
            return 'border-[#BDE9E7] bg-[#E8F7F6] text-[#087F8C]'
        case 'Limit Reached':
            return 'border-[#FDE68A] bg-[#FFFBEB] text-[#B77900]'
        case 'Expired':
            return 'border-[#FECACA] bg-[#FEF2F2] text-[#DC2626]'
        default:
            return 'border-[#E5E7EB] bg-[#F8FAF9] text-[#64748B]'
    }
}

const usagePercent = voucher => {
    if (!voucher.usage_limit) return 0
    return Math.min(100, (Number(voucher.used_count ?? 0) / Number(voucher.usage_limit)) * 100)
}

/*
|--------------------------------------------------------------------------
| Modal & Actions
|--------------------------------------------------------------------------
*/

const openCreateModal = () => {
    editingVoucher.value = null
    form.reset()
    form.code = ''
    form.type = 'percentage'
    form.value = ''
    form.max_discount = ''
    form.per_customer_limit = 1
    form.target_type = 'all'
    form.targeted_product_ids = []
    form.min_spend = ''
    form.usage_limit = ''
    form.starts_at = ''
    form.expires_at = ''
    form.is_active = true
    form.clearErrors()
    productSearch.value = ''
    showModal.value = true
}

const openEditModal = voucher => {
    editingVoucher.value = voucher
    form.code = voucher.code ?? ''
    form.type = voucher.type ?? 'percentage'
    form.value = voucher.value ?? ''
    form.max_discount = voucher.max_discount ?? ''
    form.per_customer_limit = voucher.per_customer_limit ?? 1
    form.target_type = voucher.target_type ?? 'all'
    form.targeted_product_ids = Array.isArray(voucher.targeted_product_ids) ? [...voucher.targeted_product_ids] : []
    form.min_spend = voucher.min_spend ?? ''
    form.usage_limit = voucher.usage_limit ?? ''
    form.starts_at = formatDateForInput(voucher.starts_at)
    form.expires_at = formatDateForInput(voucher.expires_at)
    form.is_active = Boolean(voucher.is_active)
    form.clearErrors()
    productSearch.value = ''
    showModal.value = true
}

const closeModal = () => {
    if (form.processing) return
    showModal.value = false
    editingVoucher.value = null
    form.clearErrors()
}

const submit = () => {
    if (editingVoucher.value) {
        form.patch(route('seller.vouchers.update', editingVoucher.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        })
        return
    }

    form.post(route('seller.vouchers.store'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    })
}

const toggleVoucher = voucher => {
    const toggleForm = useForm({
        code: voucher.code,
        type: voucher.type,
        value: voucher.value,
        max_discount: voucher.max_discount ?? '',
        per_customer_limit: voucher.per_customer_limit ?? 1,
        target_type: voucher.target_type ?? 'all',
        targeted_product_ids: voucher.targeted_product_ids ?? [],
        min_spend: voucher.min_spend ?? '',
        usage_limit: voucher.usage_limit ?? '',
        starts_at: formatDateForInput(voucher.starts_at),
        expires_at: formatDateForInput(voucher.expires_at),
        is_active: !Boolean(voucher.is_active),
    })

    toggleForm.patch(route('seller.vouchers.update', voucher.id), {
        preserveScroll: true,
    })
}

const deleteVoucher = voucher => {
    const confirmed = window.confirm(`Are you sure you want to delete voucher "${voucher.code}"?`)
    if (!confirmed) return

    const deleteForm = useForm({})
    deleteForm.delete(route('seller.vouchers.destroy', voucher.id), {
        preserveScroll: true,
    })
}

// Product search filter in modal
const filteredProducts = computed(() => {
    if (!productSearch.value.trim()) return props.products
    const q = productSearch.value.toLowerCase()
    return props.products.filter(p => p.name.toLowerCase().includes(q))
})

const toggleAllProducts = () => {
    if (form.targeted_product_ids.length === props.products.length) {
        form.targeted_product_ids = []
    } else {
        form.targeted_product_ids = props.products.map(p => p.id)
    }
}

const statusMessage = computed(() => page.props.flash?.status ?? '')
</script>

<template>
    <Head title="Promotions & Vouchers" />

    <SellerLayout>
        <div class="min-h-[calc(100vh-68px)] bg-[#F8FAF9]">
            <div class="mx-auto w-full max-w-7xl px-4 py-5 sm:px-5 lg:px-6">
                <!-- PAGE HEADER -->
                <div class="flex flex-col gap-4 border-b border-[#E5E7EB] pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-[#087F8C]">
                            Promotions & Marketing
                        </p>
                        <h1 class="mt-1 text-2xl font-bold tracking-tight text-[#1F2937] sm:text-3xl">
                            Vouchers & Campaigns
                        </h1>
                        <p class="mt-1 text-sm text-[#64748B]">
                            Create percentage discounts with caps, free shipping subsidies, product-targeted vouchers, and track real revenue attribution.
                        </p>
                    </div>

                    <button
                        type="button"
                        @click="openCreateModal"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-xl bg-[#087F8C] px-4 text-xs font-bold text-white shadow-sm transition hover:bg-[#066B76]"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Create New Voucher
                    </button>
                </div>

                <!-- FLASH MESSAGE -->
                <div
                    v-if="statusMessage"
                    class="mt-5 flex items-center gap-3 rounded-xl border border-[#BBE7D3] bg-[#ECFDF5] px-4 py-3 text-sm font-semibold text-[#16845A]"
                >
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12.5l4 4L19 7" />
                        </svg>
                    </div>
                    {{ statusMessage }}
                </div>

                <!-- SELLER-30: REVENUE ATTRIBUTION & PERFORMANCE SUMMARY CARDS -->
                <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-5">
                    <!-- TOTAL VOUCHERS -->
                    <div class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#94A3B8]">Total Campaigns</p>
                        <p class="mt-1.5 text-2xl font-bold tracking-tight text-[#1F2937]">{{ totalVouchers }}</p>
                        <p class="mt-1 text-[11px] text-[#64748B]">{{ activeVouchers }} currently active</p>
                    </div>

                    <!-- TOTAL CLAIMS & USES -->
                    <div class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#94A3B8]">Redemptions</p>
                        <p class="mt-1.5 text-2xl font-bold tracking-tight text-[#087F8C]">{{ totalUses }}</p>
                        <p class="mt-1 text-[11px] text-[#64748B]">Orders with voucher used</p>
                    </div>

                    <!-- ATTRIBUTED GROSS REVENUE -->
                    <div class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#94A3B8]">Gross Sales Generated</p>
                        <p class="mt-1.5 text-2xl font-bold tracking-tight text-[#16845A]">{{ money(totalRevenue) }}</p>
                        <p class="mt-1 text-[11px] text-[#64748B]">Real customer order totals</p>
                    </div>

                    <!-- TOTAL DISCOUNT FUNDED -->
                    <div class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#94A3B8]">Discounts Funded</p>
                        <p class="mt-1.5 text-2xl font-bold tracking-tight text-amber-600">{{ money(totalDiscountFunded) }}</p>
                        <p class="mt-1 text-[11px] text-[#64748B]">Actual subsidies granted</p>
                    </div>

                    <!-- ROI / REVENUE RATIO -->
                    <div class="col-span-2 rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm lg:col-span-1">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-[#94A3B8]">Sales / Discount Ratio</p>
                        <p class="mt-1.5 text-2xl font-bold tracking-tight text-[#087F8C]">
                            {{ totalDiscountFunded > 0 ? (totalRevenue / totalDiscountFunded).toFixed(1) + 'x' : '—' }}
                        </p>
                        <p class="mt-1 text-[11px] text-[#64748B]">Gross sales per ₱1 discount</p>
                    </div>
                </div>

                <!-- DESKTOP VOUCHERS TABLE -->
                <section class="mt-6 hidden overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm lg:block">
                    <div class="flex items-center justify-between border-b border-[#E5E7EB] px-5 py-4">
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-bold text-[#1F2937]">Active & Past Campaigns</h2>
                            <span class="rounded-full bg-[#E8F7F6] px-2.5 py-0.5 text-xs font-bold text-[#087F8C]">
                                {{ vouchers.length }}
                            </span>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-[#E5E7EB] bg-[#F8FAF9]">
                                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-[0.08em] text-[#94A3B8]">Voucher & Scope</th>
                                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-[0.08em] text-[#94A3B8]">Discount & Cap</th>
                                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-[0.08em] text-[#94A3B8]">Limits & Criteria</th>
                                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-[0.08em] text-[#94A3B8]">Attributed Revenue</th>
                                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-[0.08em] text-[#94A3B8]">Countdown / Validity</th>
                                    <th class="px-5 py-3.5 text-left text-[10px] font-bold uppercase tracking-[0.08em] text-[#94A3B8]">Status</th>
                                    <th class="px-5 py-3.5 text-right text-[10px] font-bold uppercase tracking-[0.08em] text-[#94A3B8]">Actions</th>
                                </tr>
                            </thead>

                            <tbody v-if="vouchers.length" class="divide-y divide-[#F1F5F9]">
                                <tr v-for="voucher in vouchers" :key="voucher.id" class="transition hover:bg-[#FAFCFB]">
                                    <!-- Voucher & Scope -->
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-sm font-bold tracking-wider text-[#087F8C]">
                                                {{ voucher.code }}
                                            </span>
                                            <span
                                                v-if="voucher.type === 'free_shipping'"
                                                class="rounded-md bg-emerald-100 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-emerald-800"
                                            >
                                                Shipping
                                            </span>
                                            <span
                                                v-else-if="voucher.type === 'percentage'"
                                                class="rounded-md bg-blue-100 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-blue-800"
                                            >
                                                Percentage
                                            </span>
                                            <span
                                                v-else
                                                class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-slate-800"
                                            >
                                                Fixed
                                            </span>
                                        </div>
                                        <p class="mt-1 text-xs text-[#64748B] flex items-center gap-1">
                                            <svg class="h-3 w-3 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                            </svg>
                                            {{ targetScopeLabel(voucher) }}
                                        </p>
                                    </td>

                                    <!-- Discount & Cap -->
                                    <td class="px-5 py-4">
                                        <p class="text-sm font-bold text-[#1F2937]">
                                            {{ discountLabel(voucher) }}
                                        </p>
                                        <p v-if="voucher.max_discount && Number(voucher.max_discount) > 0" class="mt-0.5 text-[11px] font-medium text-amber-700">
                                            Max Cap: {{ money(voucher.max_discount) }}
                                        </p>
                                    </td>

                                    <!-- Limits & Criteria -->
                                    <td class="px-5 py-4 text-xs text-[#64748B]">
                                        <p>
                                            <span class="font-semibold text-[#1F2937]">Min Spend:</span>
                                            {{ voucher.min_spend ? money(voucher.min_spend) : 'None' }}
                                        </p>
                                        <p class="mt-1">
                                            <span class="font-semibold text-[#1F2937]">Buyer Limit:</span>
                                            {{ voucher.per_customer_limit ? voucher.per_customer_limit + 'x per user' : 'Unlimited' }}
                                        </p>
                                        <p class="mt-0.5 text-[11px] text-slate-400">
                                            Total uses: {{ voucher.used_count ?? 0 }} {{ voucher.usage_limit ? '/ ' + voucher.usage_limit : '' }}
                                        </p>
                                    </td>

                                    <!-- SELLER-30: Attributed Revenue -->
                                    <td class="px-5 py-4">
                                        <p class="text-sm font-bold text-[#16845A]">
                                            {{ money(voucher.gross_revenue) }}
                                        </p>
                                        <p class="text-[11px] text-[#64748B]">
                                            {{ voucher.orders_count ?? 0 }} orders placed
                                        </p>
                                        <p v-if="Number(voucher.total_discount_spent ?? 0) > 0" class="text-[10px] text-amber-600">
                                            -{{ money(voucher.total_discount_spent) }} discounts
                                        </p>
                                    </td>

                                    <!-- SELLER-31: Countdown / Validity -->
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-1.5">
                                            <span
                                                class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px]"
                                                :class="getExpiryInfo(voucher).badgeClass"
                                            >
                                                <svg
                                                    v-if="getExpiryInfo(voucher).isExpiringSoon"
                                                    class="h-3 w-3 text-amber-600"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                {{ getExpiryInfo(voucher).label }}
                                            </span>
                                        </div>
                                        <p class="mt-1 text-[11px] text-slate-500">
                                            {{ formatDate(voucher.starts_at) }} → {{ formatDate(voucher.expires_at) }}
                                        </p>
                                    </td>

                                    <!-- Status -->
                                    <td class="px-5 py-4">
                                        <span
                                            class="inline-block rounded-full border px-2.5 py-1 text-[11px] font-bold"
                                            :class="statusClass(voucher)"
                                        >
                                            {{ statusLabel(voucher) }}
                                        </span>
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-5 py-4 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <button
                                                type="button"
                                                @click="toggleVoucher(voucher)"
                                                class="rounded-lg border px-2.5 py-1.5 text-[10px] font-bold transition"
                                                :class="
                                                    voucher.is_active
                                                        ? 'border-[#FDE68A] bg-[#FFF8E7] text-[#A66A00] hover:bg-[#FFF1C9]'
                                                        : 'border-[#BBE7D3] bg-[#ECFDF5] text-[#16845A] hover:bg-[#DDF8EA]'
                                                "
                                            >
                                                {{ voucher.is_active ? 'Deactivate' : 'Activate' }}
                                            </button>

                                            <button
                                                type="button"
                                                @click="openEditModal(voucher)"
                                                class="rounded-lg border border-[#E5E7EB] bg-white px-2.5 py-1.5 text-[10px] font-bold text-[#64748B] transition hover:border-[#087F8C] hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                                            >
                                                Edit
                                            </button>

                                            <button
                                                type="button"
                                                @click="deleteVoucher(voucher)"
                                                class="rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-2.5 py-1.5 text-[10px] font-bold text-[#DC2626] transition hover:bg-[#FDE8E8]"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>

                            <tbody v-else>
                                <tr>
                                    <td colspan="7" class="px-6 py-14 text-center">
                                        <h3 class="text-sm font-bold text-[#1F2937]">No vouchers yet</h3>
                                        <p class="mt-1 text-xs text-[#94A3B8]">
                                            Create your first promotion to boost sales and reward loyal customers.
                                        </p>
                                        <button
                                            type="button"
                                            @click="openCreateModal"
                                            class="mt-4 inline-flex h-9 items-center rounded-lg bg-[#087F8C] px-4 text-xs font-bold text-white transition hover:bg-[#066B76]"
                                        >
                                            Create Voucher
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- MOBILE VOUCHERS LIST -->
                <section class="mt-5 space-y-3 lg:hidden">
                    <div
                        v-for="voucher in vouchers"
                        :key="voucher.id"
                        class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <span class="font-mono text-base font-bold text-[#087F8C]">{{ voucher.code }}</span>
                                <p class="text-sm font-bold text-[#1F2937]">{{ discountLabel(voucher) }}</p>
                                <p class="text-[11px] text-[#64748B]">{{ targetScopeLabel(voucher) }}</p>
                            </div>
                            <span class="rounded-full border px-2.5 py-1 text-[10px] font-bold" :class="statusClass(voucher)">
                                {{ statusLabel(voucher) }}
                            </span>
                        </div>

                        <!-- Real Revenue Attribution -->
                        <div class="mt-3 grid grid-cols-2 gap-2 rounded-xl bg-slate-50 p-2.5 text-xs">
                            <div>
                                <span class="text-[10px] text-slate-500">Gross Sales</span>
                                <p class="font-bold text-[#16845A]">{{ money(voucher.gross_revenue) }}</p>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-500">Orders Placed</span>
                                <p class="font-bold text-slate-800">{{ voucher.orders_count ?? 0 }} orders</p>
                            </div>
                        </div>

                        <!-- Countdown / Validity -->
                        <div class="mt-2.5 flex items-center justify-between text-xs">
                            <span class="text-slate-500">Expires: {{ formatDate(voucher.expires_at) }}</span>
                            <span class="font-bold" :class="getExpiryInfo(voucher).badgeClass">
                                {{ getExpiryInfo(voucher).label }}
                            </span>
                        </div>

                        <div class="mt-3 flex gap-2 border-t border-slate-100 pt-3">
                            <button
                                type="button"
                                @click="toggleVoucher(voucher)"
                                class="flex-1 rounded-lg border py-1.5 text-xs font-bold"
                                :class="voucher.is_active ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'"
                            >
                                {{ voucher.is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                            <button
                                type="button"
                                @click="openEditModal(voucher)"
                                class="flex-1 rounded-lg border border-slate-200 py-1.5 text-xs font-bold text-slate-700"
                            >
                                Edit
                            </button>
                            <button
                                type="button"
                                @click="deleteVoucher(voucher)"
                                class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-bold text-red-600"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- CREATE / EDIT MODAL -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto bg-[#1F2937]/60 p-4 backdrop-blur-[2px]"
            @click.self="closeModal"
        >
            <div class="w-full max-w-2xl overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-2xl">
                <!-- MODAL HEADER -->
                <div class="flex items-center justify-between border-b border-[#E5E7EB] bg-white px-5 py-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5l4 4m0 0l-9 9H6v-4l9-9zm-1-1l2-2 4 4-2 2" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-[#1F2937]">
                                {{ editingVoucher ? 'Edit Voucher Campaign' : 'Create Voucher Campaign' }}
                            </h2>
                            <p class="text-xs text-[#94A3B8]">
                                Configure discount rates, maximum caps, buyer limits, and target scopes.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="closeModal"
                        class="rounded-lg p-2 text-[#94A3B8] transition hover:bg-[#F8FAF9] hover:text-[#1F2937]"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- FORM -->
                <form @submit.prevent="submit">
                    <div class="max-h-[72vh] space-y-4 overflow-y-auto px-5 py-5 sm:px-6">
                        <!-- CODE -->
                        <div>
                            <label class="mb-1 block text-xs font-bold text-[#1F2937]">
                                Voucher Promo Code <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.code"
                                type="text"
                                maxlength="30"
                                placeholder="e.g. FLASH20, FREESHIP80, MEGA50"
                                class="h-10 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 text-sm uppercase text-[#1F2937] outline-none transition placeholder:normal-case placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                            />
                            <p v-if="form.errors.code" class="mt-1 text-xs font-medium text-[#DC2626]">
                                {{ form.errors.code }}
                            </p>
                        </div>

                        <!-- SELLER-29: TYPE (Percentage, Fixed, Free Shipping) -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div class="sm:col-span-1">
                                <label class="mb-1 block text-xs font-bold text-[#1F2937]">Discount Type</label>
                                <select
                                    v-model="form.type"
                                    class="h-10 w-full rounded-xl border border-[#E5E7EB] bg-white px-3 text-sm text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:ring-2 focus:ring-[#E8F7F6]"
                                >
                                    <option value="percentage">Percentage (%)</option>
                                    <option value="fixed">Fixed Amount (₱)</option>
                                    <option value="free_shipping">Free Shipping</option>
                                </select>
                            </div>

                            <!-- VALUE -->
                            <div class="sm:col-span-1">
                                <label class="mb-1 block text-xs font-bold text-[#1F2937]">
                                    {{ form.type === 'free_shipping' ? 'Subsidy Amount' : 'Discount Value' }}
                                </label>
                                <div class="relative">
                                    <span v-if="form.type === 'fixed'" class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-bold text-slate-500">₱</span>
                                    <input
                                        v-model="form.value"
                                        type="number"
                                        min="0"
                                        :step="form.type === 'percentage' ? '1' : '0.01'"
                                        :disabled="form.type === 'free_shipping'"
                                        :placeholder="form.type === 'free_shipping' ? '100% Free' : form.type === 'percentage' ? '20' : '100'"
                                        class="h-10 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] text-sm text-[#1F2937] outline-none transition disabled:bg-slate-100 disabled:text-slate-400 focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                        :class="form.type === 'fixed' ? 'pl-8 pr-3' : 'px-3'"
                                    />
                                    <span v-if="form.type === 'percentage'" class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-bold text-slate-500">%</span>
                                </div>
                                <p v-if="form.errors.value" class="mt-1 text-xs text-red-600">{{ form.errors.value }}</p>
                            </div>

                            <!-- SELLER-26: MAXIMUM DISCOUNT CAP -->
                            <div class="sm:col-span-1">
                                <label class="mb-1 block text-xs font-bold text-[#1F2937]">
                                    Max Discount Cap (₱)
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-bold text-slate-500">₱</span>
                                    <input
                                        v-model="form.max_discount"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        placeholder="Optional Cap"
                                        class="h-10 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] pl-8 pr-3 text-sm text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                    />
                                </div>
                                <p class="mt-0.5 text-[10px] text-slate-400">
                                    Caps {{ form.type === 'free_shipping' ? 'shipping subsidy' : 'percentage discount' }}.
                                </p>
                                <p v-if="form.errors.max_discount" class="mt-1 text-xs text-red-600">{{ form.errors.max_discount }}</p>
                            </div>
                        </div>

                        <!-- SELLER-27: LIMITS & MINIMUM PURCHASE -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-xs font-bold text-[#1F2937]">Minimum Spend (₱)</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-bold text-slate-500">₱</span>
                                    <input
                                        v-model="form.min_spend"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="0"
                                        class="h-10 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] pl-8 pr-3 text-sm text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                    />
                                </div>
                                <p v-if="form.errors.min_spend" class="mt-1 text-xs text-red-600">{{ form.errors.min_spend }}</p>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-bold text-[#1F2937]">Limit Per Customer</label>
                                <input
                                    v-model="form.per_customer_limit"
                                    type="number"
                                    min="1"
                                    max="100"
                                    step="1"
                                    placeholder="1"
                                    class="h-10 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 text-sm text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                />
                                <p class="mt-0.5 text-[10px] text-slate-400">Claims/uses allowed per buyer.</p>
                                <p v-if="form.errors.per_customer_limit" class="mt-1 text-xs text-red-600">{{ form.errors.per_customer_limit }}</p>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-bold text-[#1F2937]">Total Campaign Limit</label>
                                <input
                                    v-model="form.usage_limit"
                                    type="number"
                                    min="1"
                                    step="1"
                                    placeholder="Unlimited"
                                    class="h-10 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 text-sm text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                />
                                <p class="mt-0.5 text-[10px] text-slate-400">Total redemptions storewide.</p>
                                <p v-if="form.errors.usage_limit" class="mt-1 text-xs text-red-600">{{ form.errors.usage_limit }}</p>
                            </div>
                        </div>

                        <!-- SELLER-28: PRODUCT & CATEGORY TARGETING -->
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <label class="mb-1.5 block text-xs font-bold text-[#1F2937]">Target Eligibility Scope</label>
                            <div class="grid grid-cols-2 gap-2">
                                <label
                                    class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border p-2.5 text-center text-xs font-bold transition"
                                    :class="form.target_type === 'all' ? 'border-[#087F8C] bg-[#E8F7F6] text-[#087F8C]' : 'border-slate-200 bg-white text-slate-700'"
                                >
                                    <input v-model="form.target_type" type="radio" value="all" class="sr-only" />
                                    All Store Products
                                </label>
                                <label
                                    class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border p-2.5 text-center text-xs font-bold transition"
                                    :class="form.target_type === 'product' ? 'border-[#087F8C] bg-[#E8F7F6] text-[#087F8C]' : 'border-slate-200 bg-white text-slate-700'"
                                >
                                    <input v-model="form.target_type" type="radio" value="product" class="sr-only" />
                                    Specific Products
                                </label>
                            </div>

                            <!-- Target Products Selector -->
                            <div v-if="form.target_type === 'product'" class="mt-3 space-y-2 border-t border-slate-200 pt-3">
                                <div class="flex items-center justify-between">
                                    <input
                                        v-model="productSearch"
                                        type="text"
                                        placeholder="Search your products..."
                                        class="h-8 w-64 rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-700 outline-none"
                                    />
                                    <button
                                        type="button"
                                        @click="toggleAllProducts"
                                        class="text-xs font-bold text-[#087F8C] hover:underline"
                                    >
                                        {{ form.targeted_product_ids.length === products.length ? 'Deselect All' : 'Select All' }}
                                    </button>
                                </div>

                                <div class="max-h-36 overflow-y-auto space-y-1 rounded-lg border border-slate-200 bg-white p-2">
                                    <label
                                        v-for="prod in filteredProducts"
                                        :key="prod.id"
                                        class="flex items-center gap-2 rounded px-2 py-1 text-xs hover:bg-slate-50 cursor-pointer"
                                    >
                                        <input
                                            type="checkbox"
                                            :value="prod.id"
                                            v-model="form.targeted_product_ids"
                                            class="rounded border-slate-300 text-[#087F8C] focus:ring-[#087F8C]"
                                        />
                                        <span class="flex-1 truncate font-medium text-slate-800">{{ prod.name }}</span>
                                        <span class="text-slate-400">₱{{ Number(prod.price).toFixed(2) }}</span>
                                    </label>
                                    <p v-if="!filteredProducts.length" class="text-center text-xs text-slate-400 py-2">
                                        No matching products found.
                                    </p>
                                </div>
                                <p class="text-[10px] text-slate-500">
                                    {{ form.targeted_product_ids.length }} of {{ products.length }} products selected.
                                </p>
                            </div>
                        </div>

                        <!-- DATES -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-bold text-[#1F2937]">Start Date</label>
                                <input
                                    v-model="form.starts_at"
                                    type="date"
                                    class="h-10 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 text-sm text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                />
                                <p v-if="form.errors.starts_at" class="mt-1 text-xs text-red-600">{{ form.errors.starts_at }}</p>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-bold text-[#1F2937]">Expiry Date</label>
                                <input
                                    v-model="form.expires_at"
                                    type="date"
                                    class="h-10 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 text-sm text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                />
                                <p v-if="form.errors.expires_at" class="mt-1 text-xs text-red-600">{{ form.errors.expires_at }}</p>
                            </div>
                        </div>

                        <!-- ACTIVE SWITCH -->
                        <div class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-3.5">
                            <label class="flex cursor-pointer items-center justify-between gap-4">
                                <div>
                                    <p class="text-xs font-bold text-[#1F2937]">Campaign Active</p>
                                    <p class="mt-0.5 text-[10px] text-[#64748B]">Buyers can claim and redeem this voucher while active.</p>
                                </div>
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="form.is_active"
                                    @click="form.is_active = !form.is_active"
                                    class="relative h-6 w-11 shrink-0 rounded-full transition"
                                    :class="form.is_active ? 'bg-[#087F8C]' : 'bg-[#CBD5E1]'"
                                >
                                    <span
                                        class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition"
                                        :class="form.is_active ? 'left-[22px]' : 'left-0.5'"
                                    />
                                </button>
                            </label>
                        </div>
                    </div>

                    <!-- MODAL FOOTER -->
                    <div class="flex flex-col-reverse gap-2 border-t border-[#E5E7EB] bg-[#F8FAF9] px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
                        <button
                            type="button"
                            @click="closeModal"
                            class="h-10 rounded-xl border border-[#E5E7EB] bg-white px-5 text-sm font-bold text-[#64748B] transition hover:bg-[#F8FAF9]"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="h-10 rounded-xl bg-[#087F8C] px-5 text-sm font-bold text-white shadow-sm transition hover:bg-[#066B76] disabled:opacity-60"
                        >
                            {{ form.processing ? 'Saving...' : editingVoucher ? 'Update Voucher' : 'Create Voucher' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </SellerLayout>
</template>