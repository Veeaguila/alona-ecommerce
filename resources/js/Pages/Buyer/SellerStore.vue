<script setup>
import Icon from '@/Components/Icon.vue'
import { computed, onMounted, ref } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'
import GuestLayout from '@/Layouts/GuestLayout.vue'

const props = defineProps({
    seller: {
        type: Object,
        default: () => ({}),
    },
    products: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
            total: 0,
        }),
    },
    recommended_products: {
        type: Array,
        default: () => [],
    },
    categories: {
        type: Array,
        default: () => [],
    },
    vouchers: {
        type: Array,
        default: () => [],
    },
    claimed_voucher_ids: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({}),
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            sort: 'latest',
            category: null,
        }),
    },
})

const page = usePage()
const user = computed(() => page.props.auth?.user || null)
const isGuest = computed(() => !user.value)
const layoutComponent = computed(() => isGuest.value ? GuestLayout : BuyerLayout)

// Active category filter: 'all' or category slug
const activeCategory = ref(props.filters.category || 'all')

// Follow State (persisted in localStorage)
const isFollowing = ref(false)

onMounted(() => {
    try {
        const followed = localStorage.getItem(`followed_seller_${props.seller.id}`)
        isFollowing.value = followed === 'true'
    } catch (e) {}
})

const toggleFollow = () => {
    isFollowing.value = !isFollowing.value
    try {
        localStorage.setItem(`followed_seller_${props.seller.id}`, String(isFollowing.value))
    } catch (e) {}
}

// Chat Trigger (Dispatches event to FloatingChatWidget)
const openChatWithSeller = () => {
    if (isGuest.value) {
        router.visit(route('login'))
        return
    }

    if (typeof window !== 'undefined') {
        window.dispatchEvent(
            new CustomEvent('open-buyer-chat', {
                detail: {
                    sellerId: props.seller.id,
                },
            })
        )
    }
}

// Search & Sort State
const searchQuery = ref(props.filters.search || '')
const sortOption = ref(props.filters.sort || 'latest')

const handleSearch = () => {
    router.get(
        route('buyer.store.show', props.seller.id),
        {
            search: searchQuery.value || undefined,
            sort: sortOption.value || 'latest',
            category: activeCategory.value !== 'all' ? activeCategory.value : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    )
}

const changeSort = sort => {
    sortOption.value = sort
    handleSearch()
}

const selectCategory = categorySlug => {
    activeCategory.value = categorySlug
    router.get(
        route('buyer.store.show', props.seller.id),
        {
            category: categorySlug !== 'all' ? categorySlug : undefined,
            search: searchQuery.value || undefined,
            sort: sortOption.value || 'latest',
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    )
}

// Vouchers carousel scroll
const voucherContainer = ref(null)

const scrollVouchers = direction => {
    if (!voucherContainer.value) return
    const offset = direction === 'left' ? -320 : 320
    voucherContainer.value.scrollBy({ left: offset, behavior: 'smooth' })
}

// Vouchers Claim & Copy Code State
const claimedIds = ref([...props.claimed_voucher_ids])
const copiedCode = ref(null)

const copyCode = code => {
    if (!code) return
    try {
        navigator.clipboard?.writeText(code)
        copiedCode.value = code
        setTimeout(() => {
            if (copiedCode.value === code) {
                copiedCode.value = null
            }
        }, 2000)
    } catch (e) {}
}

const formatVoucherTitle = voucher => {
    if (voucher.type === 'percentage') {
        return `${Number(voucher.value)}% OFF`
    }
    return `₱${Number(voucher.value).toFixed(0)} OFF`
}

const formatVoucherExpiry = expiry => {
    if (!expiry) return 'No Expiration'
    try {
        const d = new Date(expiry)
        return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
    } catch (e) {
        return expiry
    }
}

const claimVoucher = voucher => {
    if (isGuest.value) {
        router.visit(route('login'))
        return
    }

    router.post(
        route('buyer.vouchers.claim', voucher.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                if (!claimedIds.value.includes(voucher.id)) {
                    claimedIds.value.push(voucher.id)
                }
            },
        }
    )
}

// BIR Modal
const isBirModalOpen = ref(false)
const openBirModal = () => {
    isBirModalOpen.value = true
}
const closeBirModal = () => {
    isBirModalOpen.value = false
}

// Report Modal
const isReportModalOpen = ref(false)
const reportForm = useForm({
    report_type: 'seller',
    seller_id: props.seller.id,
    reason: 'Fraud / Suspicious Store',
    description: '',
    evidence_images: [],
})

const reportReasons = [
    'Fraud / Suspicious Store',
    'Selling Fake or Counterfeit Products',
    'Harassment / Abusive Communication',
    'Refusal to Honor Return/Refund Policy',
    'Misleading Store Identity or Pricing',
    'Other Store Violation',
]

const openReportModal = () => {
    if (isGuest.value) {
        router.visit(route('login'))
        return
    }

    reportForm.reset()
    reportForm.seller_id = props.seller.id
    reportForm.report_type = 'seller'
    isReportModalOpen.value = true
}

const closeReportModal = () => {
    isReportModalOpen.value = false
    reportForm.reset()
}

const handleReportFileChange = event => {
    const files = Array.from(event.target.files || [])
    reportForm.evidence_images = files.slice(0, 4)
}

const submitReport = () => {
    reportForm.post(route('buyer.reports.store'), {
        preserveScroll: true,
        onSuccess: () => {
            closeReportModal()
        },
    })
}

// Image helper
const imageUrl = path => {
    if (!path) return null
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/storage/')) return path
    return `/storage/${path.replace(/^\/+/, '')}`
}

const formatPrice = price => {
    return Number(price ?? 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}

const storeInitials = computed(() => {
    const name = props.seller.store_name || props.seller.name || 'Store'
    return name.slice(0, 2).toUpperCase()
})

// Schedule Modal (SELLER-63)
const isScheduleModalOpen = ref(false)
const openScheduleModal = () => {
    isScheduleModalOpen.value = true
}
const closeScheduleModal = () => {
    isScheduleModalOpen.value = false
}

const scheduleDays = [
    { key: 'monday', label: 'Monday' },
    { key: 'tuesday', label: 'Tuesday' },
    { key: 'wednesday', label: 'Wednesday' },
    { key: 'thursday', label: 'Thursday' },
    { key: 'friday', label: 'Friday' },
    { key: 'saturday', label: 'Saturday' },
    { key: 'sunday', label: 'Sunday' },
]

const formatTime = (timeStr) => {
    if (!timeStr) return ''
    try {
        const [h, m] = timeStr.split(':')
        const d = new Date()
        d.setHours(parseInt(h, 10))
        d.setMinutes(parseInt(m, 10))
        return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })
    } catch (e) {
        return timeStr
    }
}
</script>

<template>
    <Head :title="`${seller.store_name || seller.name} - Storefront`" />

    <component :is="layoutComponent">
        <div class="w-full min-w-0 overflow-x-hidden bg-[#F8FAF9] font-sans pb-16">
            <!-- Full width responsive container matching Products.vue and Show.vue -->
            <div class="mx-auto w-full max-w-[1536px] min-w-0 px-4 py-5 sm:px-6 sm:py-6 lg:px-8 2xl:px-10 space-y-6">

                <!-- ============================================================== -->
                <!-- VACATION MODE ALERT (SELLER-65) -->
                <!-- ============================================================== -->
                <div
                    v-if="seller.vacation_mode"
                    class="w-full rounded-2xl border border-amber-300 bg-amber-100/90 p-4 sm:p-5 shadow-xs flex items-start gap-3.5 sm:gap-4"
                >
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-white shadow-xs">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="6" y="4" width="4" height="16" />
                            <rect x="14" y="4" width="4" height="16" />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-black text-amber-950 uppercase tracking-wide">Store on Vacation Mode</h3>
                            <span class="rounded-full bg-amber-200 px-2.5 py-0.5 text-[10px] font-bold text-amber-900 border border-amber-300">Orders Paused</span>
                        </div>
                        <p class="text-xs text-amber-900 mt-1 leading-relaxed">
                            {{ seller.vacation_message || 'This store has temporarily paused checkout and incoming orders. You may still browse products and message the seller.' }}
                        </p>
                    </div>
                </div>

                <!-- ============================================================== -->
                <!-- 1. STORE PROFILE HEADER (Alona Design System) -->
                <!-- ============================================================== -->
                <div class="w-full bg-white rounded-2xl border border-slate-200/90 shadow-sm overflow-hidden">
                    <!-- Top Brand Banner with Alona Teal Gradient or Custom Cover Banner (SELLER-62) -->
                    <div
                        class="relative text-white p-5 sm:p-7 lg:p-8 bg-cover bg-center overflow-hidden"
                        :style="seller.store_banner_path ? { backgroundImage: `url(${imageUrl(seller.store_banner_path)})` } : {}"
                        :class="!seller.store_banner_path ? 'bg-gradient-to-r from-[#087F8C] via-[#0A96A6] to-[#065A63]' : ''"
                    >
                        <!-- Dark overlay for custom banner image to maintain crisp readability -->
                        <div v-if="seller.store_banner_path" class="absolute inset-0 bg-slate-950/65 backdrop-blur-[0.5px]"></div>

                        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-5 sm:gap-6">

                            <!-- Left: Store Avatar & Identity -->
                            <div class="flex items-start sm:items-center gap-3.5 sm:gap-5">
                                <!-- Store Logo / Initials Avatar -->
                                <div class="relative shrink-0">
                                    <div class="w-16 h-16 sm:w-20 sm:h-20 lg:w-24 lg:h-24 rounded-2xl border-4 border-white/90 shadow-md bg-white overflow-hidden flex items-center justify-center">
                                        <img
                                            v-if="seller.store_logo_path"
                                            :src="imageUrl(seller.store_logo_path)"
                                            :alt="seller.store_name || seller.name"
                                            class="w-full h-full object-cover"
                                        />
                                        <div
                                            v-else
                                            class="w-full h-full bg-gradient-to-br from-[#087F8C] to-[#065A63] text-white font-black text-xl sm:text-2xl flex items-center justify-center tracking-wider"
                                        >
                                            {{ storeInitials }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Name, Badges & Description -->
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h1 class="text-lg sm:text-xl lg:text-2xl font-black text-white tracking-tight drop-shadow-sm">
                                            {{ seller.store_name || seller.name }}
                                        </h1>

                                        <!-- Verified Merchant Badge (Based on real status / permit) -->
                                        <span
                                            v-if="seller.has_business_permit || seller.status === 'approved'"
                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] sm:text-[11px] font-bold bg-[#EAF8F1] text-[#16A34A] shadow-xs"
                                        >
                                            <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                            <span>Verified Merchant</span>
                                        </span>

                                        <!-- Line of Business -->
                                        <span
                                            v-if="seller.line_of_business"
                                            class="px-2 py-0.5 rounded-md text-[10px] sm:text-[11px] font-semibold bg-white/20 text-white backdrop-blur-xs"
                                        >
                                            {{ seller.line_of_business }}
                                        </span>
                                    </div>

                                    <!-- Store Tagline (SELLER-62) -->
                                    <p
                                        v-if="seller.store_tagline"
                                        class="text-xs sm:text-sm font-semibold text-emerald-100/95 tracking-wide mt-1"
                                    >
                                        {{ seller.store_tagline }}
                                    </p>

                                    <!-- Operating Status & Response Time Pills (SELLER-63) -->
                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        <!-- Live Open/Closed Status Button -->
                                        <button
                                            type="button"
                                            @click="openScheduleModal"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold transition cursor-pointer"
                                            :class="seller.store_status?.is_open ? 'bg-emerald-500/25 text-emerald-200 border border-emerald-400/40 hover:bg-emerald-500/35' : 'bg-white/20 text-white/90 border border-white/30 hover:bg-white/30'"
                                        >
                                            <span class="h-2 w-2 rounded-full" :class="seller.store_status?.is_open ? 'bg-emerald-400 animate-pulse' : 'bg-slate-300'"></span>
                                            <span>{{ seller.store_status?.status_label || 'Store Hours' }}</span>
                                            <span class="text-[10px] opacity-80">({{ seller.store_status?.detail }})</span>
                                            <svg class="h-3 w-3 ml-0.5 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="12" cy="12" r="10" />
                                                <polyline points="12 6 12 12 16 14" />
                                            </svg>
                                        </button>

                                        <!-- Response Time Commitment -->
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium bg-white/15 text-white/90 border border-white/20 backdrop-blur-xs">
                                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                                            </svg>
                                            <span>{{ seller.response_time_label || 'Replies within the day' }}</span>
                                        </span>
                                    </div>

                                    <!-- Description if present in database -->
                                    <p
                                        v-if="seller.store_description"
                                        class="text-xs sm:text-sm text-white/90 mt-2 max-w-2xl line-clamp-2 leading-relaxed"
                                    >
                                        {{ seller.store_description }}
                                    </p>
                                </div>
                            </div>

                            <!-- Right: Store Actions (Chat, Follow, Report) -->
                            <div class="flex flex-wrap items-center gap-2 sm:gap-2.5 shrink-0 pt-2 sm:pt-0">
                                <!-- Chat with Seller Button -->
                                <button
                                    type="button"
                                    @click="openChatWithSeller"
                                    class="px-3.5 sm:px-4 py-2 sm:py-2.5 rounded-xl bg-white text-[#087F8C] hover:bg-slate-50 font-bold text-xs sm:text-sm flex items-center gap-1.5 sm:gap-2 shadow-sm transition cursor-pointer"
                                    title="Message this store"
                                >
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                                    </svg>
                                    <span>Chat with Seller</span>
                                </button>

                                <!-- Follow Button -->
                                <button
                                    type="button"
                                    @click="toggleFollow"
                                    class="px-3.5 sm:px-4 py-2 sm:py-2.5 rounded-xl border border-white/40 hover:bg-white/15 text-white font-bold text-xs sm:text-sm flex items-center gap-1.5 transition cursor-pointer"
                                >
                                    <span v-if="!isFollowing">+ Follow</span>
                                    <span v-else class="text-emerald-300">✓ Following</span>
                                </button>

                                <!-- Report Store -->
                                <button
                                    type="button"
                                    @click="openReportModal"
                                    class="px-2.5 sm:px-3 py-2 rounded-xl text-white/70 hover:text-white hover:bg-white/10 text-xs flex items-center gap-1 transition cursor-pointer"
                                    title="Report store violation"
                                >
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="10" />
                                        <line x1="12" y1="8" x2="12" y2="12" />
                                        <line x1="12" y1="16" x2="12.01" y2="16" />
                                    </svg>
                                    <span>Report</span>
                                </button>
                            </div>

                        </div>
                    </div>

                    <!-- Bottom Bar: Authentic Store Metrics Only (No hardcoded metrics) -->
                    <div class="px-4 sm:px-6 lg:px-8 py-3.5 sm:py-4 bg-slate-50/70 border-t border-slate-100 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4 text-xs">
                        <!-- Metric 1: Products -->
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-[#E8F7F6] text-[#087F8C] flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" />
                                    <line x1="3" y1="6" x2="21" y2="6" />
                                    <path d="M16 10a4 4 0 0 1-8 0" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-slate-400 font-medium">Products</p>
                                <p class="font-extrabold text-slate-800 text-sm truncate">
                                    {{ stats.total_products || 0 }}
                                </p>
                            </div>
                        </div>

                        <!-- Metric 2: Authentic Store Rating -->
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-500 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-slate-400 font-medium">Rating</p>
                                <p class="font-extrabold text-slate-800 text-sm truncate">
                                    <span v-if="stats.avg_rating">
                                        {{ Number(stats.avg_rating).toFixed(1) }}
                                        <span class="text-slate-400 text-xs font-normal">({{ stats.total_reviews }} reviews)</span>
                                    </span>
                                    <span v-else class="text-slate-500 font-normal">New Seller</span>
                                </p>
                            </div>
                        </div>

                        <!-- Metric 3: Member Since / Joined -->
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                                    <line x1="16" y1="2" x2="16" y2="6" />
                                    <line x1="8" y1="2" x2="8" y2="6" />
                                    <line x1="3" y1="10" x2="21" y2="10" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-slate-400 font-medium">Joined</p>
                                <p class="font-extrabold text-slate-800 text-sm truncate">
                                    {{ stats.joined || 'Registered' }}
                                </p>
                            </div>
                        </div>

                        <!-- Metric 4: Return Policy -->
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-slate-400 font-medium">Return Guarantee</p>
                                <p class="font-extrabold text-slate-800 text-sm truncate">
                                    {{ seller.return_policy_days || stats.return_policy_days || 7 }} Days Policy
                                </p>
                            </div>
                        </div>

                        <!-- Metric 5: BIR / Business Verification -->
                        <div class="flex items-center gap-2.5 col-span-2 sm:col-span-1">
                            <div class="w-8 h-8 rounded-lg bg-teal-50 text-[#087F8C] flex items-center justify-center shrink-0">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                    <polyline points="14 2 14 8 20 8" />
                                    <line x1="16" y1="13" x2="8" y2="13" />
                                    <line x1="16" y1="17" x2="8" y2="17" />
                                    <polyline points="10 9 9 9 8 9" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <p class="text-slate-400 font-medium">Business Permit</p>
                                <button
                                    type="button"
                                    @click="openBirModal"
                                    class="font-extrabold text-[#087F8C] hover:underline cursor-pointer flex items-center gap-1"
                                >
                                    <span>{{ stats.has_bir ? 'Verified Seal' : 'Store Details' }}</span>
                                    <span class="text-slate-400 text-[10px]">ℹ</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================== -->
                <!-- 2. STORE CATEGORIES TABS (Alona System Design) -->
                <!-- ============================================================== -->
                <div class="flex w-full min-w-0 items-center gap-2 overflow-x-auto pb-1 no-scrollbar">
                    <!-- ALL PRODUCTS TAB -->
                    <button
                        type="button"
                        class="shrink-0 rounded-lg border px-3 py-2 text-xs font-semibold transition cursor-pointer"
                        :class="
                            activeCategory === 'all'
                                ? 'border-[#087F8C] bg-[#087F8C] text-white shadow-xs'
                                : 'border-[#E5E7EB] bg-white text-[#64748B] hover:border-[#087F8C]/40 hover:text-[#087F8C]'
                        "
                        @click="selectCategory('all')"
                    >
                        All Products
                    </button>

                    <!-- DYNAMIC CATEGORIES PRESENT IN THIS STORE -->
                    <button
                        v-for="cat in categories"
                        :key="cat.id"
                        type="button"
                        class="shrink-0 rounded-lg border px-3 py-2 text-xs font-semibold transition cursor-pointer"
                        :class="
                            activeCategory === cat.slug
                                ? 'border-[#087F8C] bg-[#087F8C] text-white shadow-xs'
                                : 'border-[#E5E7EB] bg-white text-[#64748B] hover:border-[#087F8C]/40 hover:text-[#087F8C]'
                        "
                        @click="selectCategory(cat.slug)"
                    >
                        {{ cat.name }}
                    </button>

                    <!-- STORE POLICIES & TERMS TAB (SELLER-64) -->
                    <button
                        type="button"
                        class="shrink-0 rounded-lg border px-3 py-2 text-xs font-semibold transition cursor-pointer flex items-center gap-1.5"
                        :class="
                            activeCategory === 'policies'
                                ? 'border-[#087F8C] bg-[#087F8C] text-white shadow-xs'
                                : 'border-[#E5E7EB] bg-white text-[#64748B] hover:border-[#087F8C]/40 hover:text-[#087F8C]'
                        "
                        @click="activeCategory = 'policies'"
                    >
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                            <line x1="16" y1="13" x2="8" y2="13" />
                            <line x1="16" y1="17" x2="8" y2="17" />
                        </svg>
                        <span>Policies & Terms</span>
                    </button>
                </div>

                <!-- ============================================================== -->
                <!-- 3. REAL STORE VOUCHERS (Shows any voucher put by this store) -->
                <!-- ============================================================== -->
                <div
                    v-if="activeCategory !== 'policies' && vouchers && vouchers.length > 0"
                    class="relative w-full bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-sm"
                >
                    <div class="flex items-center justify-between mb-3 px-1">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-[#087F8C]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z" />
                                <path d="M13 5v2" />
                                <path d="M13 17v2" />
                                <path d="M13 11v2" />
                            </svg>
                            <h2 class="text-sm font-extrabold text-slate-800">
                                Store Vouchers & Discounts
                            </h2>
                        </div>
                        <span class="text-xs text-slate-500">
                            {{ isGuest ? 'Claim or copy code for checkout' : 'Claim and apply at checkout' }}
                        </span>
                    </div>

                    <!-- Carousel Controls -->
                    <button
                        v-if="vouchers.length > 2"
                        type="button"
                        @click="scrollVouchers('left')"
                        class="hidden sm:flex absolute left-2 top-1/2 -translate-y-1/2 z-10 w-8 h-8 rounded-full bg-white shadow-md border border-slate-200 items-center justify-center text-slate-600 hover:bg-slate-50 hover:text-[#087F8C] transition cursor-pointer"
                        aria-label="Scroll left"
                    >
                        ‹
                    </button>

                    <button
                        v-if="vouchers.length > 2"
                        type="button"
                        @click="scrollVouchers('right')"
                        class="hidden sm:flex absolute right-2 top-1/2 -translate-y-1/2 z-10 w-8 h-8 rounded-full bg-white shadow-md border border-slate-200 items-center justify-center text-slate-600 hover:bg-slate-50 hover:text-[#087F8C] transition cursor-pointer"
                        aria-label="Scroll right"
                    >
                        ›
                    </button>

                    <div
                        ref="voucherContainer"
                        class="flex items-center gap-3 sm:gap-4 overflow-x-auto no-scrollbar scroll-smooth px-1 pb-1"
                    >
                        <!-- Dynamic Real Voucher Ticket Card in Alona Theme -->
                        <div
                            v-for="voucher in vouchers"
                            :key="voucher.id"
                            class="relative shrink-0 w-[270px] sm:w-[310px] md:w-[320px] bg-gradient-to-br from-[#E8F7F6]/60 via-white to-white border border-[#B2DFDB] rounded-xl p-3.5 flex items-center justify-between shadow-xs transition hover:shadow-sm"
                        >
                            <!-- Ticket cutout notches -->
                            <span class="absolute -left-2 top-1/2 -translate-y-1/2 w-3.5 h-3.5 bg-white border-r border-[#B2DFDB] rounded-full" />
                            <span class="absolute -right-2 top-1/2 -translate-y-1/2 w-3.5 h-3.5 bg-white border-l border-[#B2DFDB] rounded-full" />

                            <div class="min-w-0 flex-1 pr-3">
                                <h3 class="text-sm sm:text-base font-black text-[#087F8C]">
                                    {{ voucher.claim_label || formatVoucherTitle(voucher) }}
                                </h3>

                                <p class="text-[11px] text-slate-600 mt-0.5">
                                    <span v-if="voucher.min_spend && Number(voucher.min_spend) > 0">
                                        Min. Spend ₱{{ Number(voucher.min_spend).toLocaleString() }}
                                    </span>
                                    <span v-else class="text-emerald-700 font-medium">
                                        No Minimum Spend
                                    </span>
                                </p>

                                <!-- Voucher code with quick copy -->
                                <div v-if="voucher.code" class="mt-1 flex items-center gap-1.5">
                                    <span class="inline-block px-1.5 py-0.5 text-[10px] font-mono font-bold text-[#087F8C] border border-[#087F8C]/40 rounded bg-white">
                                        {{ voucher.code }}
                                    </span>
                                    <button
                                        type="button"
                                        @click="copyCode(voucher.code)"
                                        class="text-[10px] text-slate-500 hover:text-[#087F8C] transition flex items-center gap-0.5 cursor-pointer"
                                        title="Copy code"
                                    >
                                        <span v-if="copiedCode === voucher.code" class="text-emerald-600 font-bold">Copied!</span>
                                        <span v-else>Copy</span>
                                    </button>
                                </div>

                                <p class="text-[10px] text-slate-400 mt-1.5">
                                    Valid till: {{ formatVoucherExpiry(voucher.expires_at) }}
                                </p>
                            </div>

                            <!-- Claim Button (Shows in guest with login prompt) -->
                            <button
                                type="button"
                                @click="claimVoucher(voucher)"
                                :disabled="!isGuest && claimedIds.includes(voucher.id)"
                                :class="[
                                    'shrink-0 px-3.5 py-1.5 rounded-lg text-xs font-bold transition shadow-xs cursor-pointer',
                                    !isGuest && claimedIds.includes(voucher.id)
                                        ? 'bg-slate-200 text-slate-500 cursor-not-allowed'
                                        : 'bg-[#087F8C] hover:bg-[#065A63] text-white'
                                ]"
                            >
                                <span v-if="isGuest">Claim</span>
                                <span v-else>{{ claimedIds.includes(voucher.id) ? 'Claimed' : 'Claim' }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ============================================================== -->
                <!-- 4. FEATURED PRODUCTS (Only if real featured products exist) -->
                <!-- ============================================================== -->
                <div
                    v-if="recommended_products && recommended_products.length > 0 && activeCategory === 'all' && !searchQuery"
                    class="space-y-3"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-[#087F8C]"></span>
                            <h2 class="text-sm font-extrabold uppercase tracking-wide text-slate-800">
                                Featured Products
                            </h2>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-2 sm:gap-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6">
                        <Link
                            v-for="product in recommended_products"
                            :key="`rec-${product.id}`"
                            :href="route('buyer.product', product.id)"
                            class="group block min-w-0 overflow-hidden rounded-xl border border-[#E5E7EB] bg-white shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-[#087F8C]/40 hover:shadow-md"
                        >
                            <div class="relative flex aspect-square w-full items-center justify-center overflow-hidden bg-[#F8FAF9] p-2.5 sm:p-3">
                                <img
                                    v-if="product.image_path"
                                    :src="imageUrl(product.image_path)"
                                    :alt="product.name"
                                    class="h-full w-full object-contain transition duration-300 group-hover:scale-[1.03]"
                                    loading="lazy"
                                />
                                <span v-else class="text-3xl text-slate-300">
                                    <Icon name="bag" class="h-6 w-6" />
                                </span>
                            </div>

                            <div class="min-w-0 p-3 sm:p-3.5">
                                <h3 class="truncate text-xs sm:text-sm font-semibold text-slate-800 group-hover:text-[#087F8C] transition">
                                    {{ product.name }}
                                </h3>

                                <div class="mt-2 flex items-baseline justify-between gap-1">
                                    <p class="text-xs sm:text-sm font-extrabold text-[#087F8C]">
                                        ₱{{ formatPrice(product.price) }}
                                    </p>
                                    <span v-if="product.reviews_count" class="text-[10px] text-slate-400">
                                        {{ product.reviews_count }} sold
                                    </span>
                                </div>

                                <div v-if="product.rating" class="mt-1 flex items-center gap-1 text-[10px] text-amber-500 font-bold">
                                    <span>★ {{ Number(product.rating).toFixed(1) }}</span>
                                </div>
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- ============================================================== -->
                <!-- STORE POLICIES & TERMS VIEW (SELLER-64) -->
                <!-- ============================================================== -->
                <div v-if="activeCategory === 'policies'" class="space-y-6 pt-2">
                    <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-xl bg-[#E8F7F6] text-[#087F8C] flex items-center justify-center shrink-0">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                    <polyline points="14 2 14 8 20 8" />
                                    <line x1="16" y1="13" x2="8" y2="13" />
                                    <line x1="16" y1="17" x2="8" y2="17" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-base font-extrabold text-slate-900">Store Terms & Policies</h2>
                                <p class="text-xs text-slate-500">Official guidelines, warranty terms, and consumer fulfillment protocols for {{ seller.store_name || seller.name }}.</p>
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="selectCategory('all')"
                            class="inline-flex items-center gap-1.5 text-xs font-bold text-[#087F8C] hover:underline"
                        >
                            <span>Back to All Products</span>
                            <span>&rarr;</span>
                        </button>
                    </div>

                    <div class="grid gap-5 md:grid-cols-3">
                        <!-- Shipping Policy -->
                        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-col justify-between space-y-4">
                            <div>
                                <div class="flex items-center gap-2 text-[#087F8C] font-bold text-xs uppercase tracking-wider mb-2.5">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="1" y="3" width="15" height="13" />
                                        <polygon points="16 8 20 8 23 11 23 16 16 16 16 8" />
                                        <circle cx="5.5" cy="18.5" r="2.5" />
                                        <circle cx="18.5" cy="18.5" r="2.5" />
                                    </svg>
                                    <span>Shipping & Delivery</span>
                                </div>
                                <h3 class="text-sm font-bold text-slate-900 mb-2">Delivery Guidelines</h3>
                                <div class="text-xs text-slate-600 whitespace-pre-line leading-relaxed">
                                    {{ seller.shipping_policy || 'Standard platform shipping applies. Orders are packed carefully and handed over to platform partner couriers with live tracking provided.' }}
                                </div>
                            </div>
                            <div class="pt-3 border-t border-slate-100 text-[11px] text-slate-500 font-semibold">
                                Flat Shipping Fee: ₱{{ Number(seller.shipping_fee || 0).toFixed(2) }}
                            </div>
                        </div>

                        <!-- Warranty Policy -->
                        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-col justify-between space-y-4">
                            <div>
                                <div class="flex items-center gap-2 text-emerald-600 font-bold text-xs uppercase tracking-wider mb-2.5">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                                    </svg>
                                    <span>Warranty Coverage</span>
                                </div>
                                <h3 class="text-sm font-bold text-slate-900 mb-2">Quality & Authenticity Guarantee</h3>
                                <div class="text-xs text-slate-600 whitespace-pre-line leading-relaxed">
                                    {{ seller.warranty_policy || 'All products sold by this verified merchant are 100% genuine and covered by standard merchant replacement guarantees for factory defects.' }}
                                </div>
                            </div>
                            <div class="pt-3 border-t border-slate-100 text-[11px] text-emerald-600 font-semibold">
                                ✓ Verified Platform Merchant Guarantee
                            </div>
                        </div>

                        <!-- Return & Refund Policy -->
                        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex flex-col justify-between space-y-4">
                            <div>
                                <div class="flex items-center gap-2 text-amber-600 font-bold text-xs uppercase tracking-wider mb-2.5">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="1 4 1 10 7 10" />
                                        <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10" />
                                    </svg>
                                    <span>Returns & Refunds</span>
                                </div>
                                <h3 class="text-sm font-bold text-slate-900 mb-2">{{ seller.return_policy_days || 7 }}-Day Return Protection</h3>
                                <div class="text-xs text-slate-600 whitespace-pre-line leading-relaxed">
                                    {{ seller.return_policy || 'Buyers can file a return or refund request within the guarantee period if items arrive damaged, defective, or incorrect.' }}
                                </div>
                            </div>
                            <div class="pt-3 border-t border-slate-100 text-[11px] text-amber-700 font-semibold">
                                Protected by Alona Buyer Guarantee
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================== -->
                <!-- 5. ALL PRODUCTS SECTION (With Search, Filter, Sort) -->
                <!-- ============================================================== -->
                <div v-else class="space-y-4 pt-2">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 rounded-2xl border border-slate-200/90 shadow-xs">
                        <div>
                            <h2 class="text-base font-extrabold text-slate-900">
                                {{ activeCategory === 'all' ? 'All Products' : 'Category Products' }}
                            </h2>
                            <p class="text-xs text-slate-500">
                                Showing {{ products.total || 0 }} items from {{ seller.store_name || seller.name }}
                            </p>
                        </div>

                        <!-- Search & Sort Controls -->
                        <div class="flex flex-wrap items-center gap-2.5">
                            <!-- Search in Store Input -->
                            <div class="relative w-full sm:w-56 md:w-64">
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search in store..."
                                    class="w-full pl-8 pr-7 py-1.5 bg-[#F8FAF9] border border-slate-200 focus:border-[#087F8C] focus:bg-white rounded-lg text-xs text-slate-800 placeholder-slate-400 focus:outline-none transition"
                                    @keydown.enter="handleSearch"
                                />
                                <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5 pointer-events-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8" />
                                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                </svg>
                                <button
                                    v-if="searchQuery"
                                    @click="searchQuery = ''; handleSearch()"
                                    type="button"
                                    class="absolute right-2 top-2 text-slate-400 hover:text-slate-600 text-xs"
                                >
                                    ✕
                                </button>
                            </div>

                            <!-- Sort Select -->
                            <select
                                v-model="sortOption"
                                @change="changeSort(sortOption)"
                                class="bg-[#F8FAF9] border border-slate-200 focus:border-[#087F8C] rounded-lg px-3 py-1.5 text-xs font-bold text-slate-700 focus:outline-none transition cursor-pointer"
                            >
                                <option value="latest">Latest Items</option>
                                <option value="price-low">Price: Low to High</option>
                                <option value="price-high">Price: High to Low</option>
                                <option value="rating">Top Rated</option>
                            </select>
                        </div>
                    </div>

                    <!-- Products Grid matching Alona Full Screen Responsive Design -->
                    <div
                        v-if="products.data?.length"
                        class="grid grid-cols-2 gap-3 sm:grid-cols-2 sm:gap-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6"
                    >
                        <Link
                            v-for="product in products.data"
                            :key="product.id"
                            :href="route('buyer.product', product.id)"
                            class="group block min-w-0 overflow-hidden rounded-xl border border-[#E5E7EB] bg-white shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-[#087F8C]/40 hover:shadow-md"
                        >
                            <div class="relative flex aspect-square w-full items-center justify-center overflow-hidden bg-[#F8FAF9] p-2.5 sm:p-3">
                                <img
                                    v-if="product.image_path"
                                    :src="imageUrl(product.image_path)"
                                    :alt="product.name"
                                    class="h-full w-full object-contain transition duration-300 group-hover:scale-[1.03]"
                                    loading="lazy"
                                />
                                <span v-else class="text-3xl text-slate-300">
                                    <Icon name="bag" class="h-6 w-6" />
                                </span>
                            </div>

                            <div class="min-w-0 p-3 sm:p-3.5">
                                <h3 class="truncate text-xs sm:text-sm font-semibold text-slate-800 group-hover:text-[#087F8C] transition">
                                    {{ product.name }}
                                </h3>

                                <div class="mt-2 flex items-baseline justify-between gap-1">
                                    <p class="text-xs sm:text-sm font-extrabold text-[#087F8C]">
                                        ₱{{ formatPrice(product.price) }}
                                    </p>
                                    <span v-if="product.reviews_count" class="text-[10px] text-slate-400">
                                        {{ product.reviews_count }} sold
                                    </span>
                                </div>

                                <div class="mt-1 flex items-center justify-between text-[10px]">
                                    <span v-if="product.rating" class="text-amber-500 font-bold">
                                        ★ {{ Number(product.rating).toFixed(1) }}
                                    </span>
                                    <span v-else class="text-slate-400">
                                        No reviews
                                    </span>

                                    <span v-if="seller.shipping_fee === 0" class="text-emerald-600 font-bold">
                                        Free Ship
                                    </span>
                                </div>
                            </div>
                        </Link>
                    </div>

                    <!-- Empty State -->
                    <div
                        v-else
                        class="bg-white rounded-2xl border border-dashed border-slate-300 p-12 text-center"
                    >
                        <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            </svg>
                        </div>
                        <h3 class="mt-3 text-sm font-bold text-slate-800">No products found</h3>
                        <p class="text-xs text-slate-500 mt-1">
                            This store does not currently have products matching your criteria.
                        </p>
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="products.links?.length > 3"
                        class="mt-8 flex w-full flex-wrap items-center justify-center gap-1.5"
                    >
                        <Link
                            v-for="(link, index) in products.links"
                            :key="index"
                            :href="link.url || '#'"
                            v-html="link.label"
                            class="rounded-lg border px-3 py-1.5 text-xs font-bold transition"
                            :class="[
                                link.active ? 'border-[#087F8C] bg-[#087F8C] text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
                                !link.url ? 'pointer-events-none opacity-40' : ''
                            ]"
                            preserve-scroll
                            preserve-state
                        />
                    </div>
                </div>

            </div>
        </div>

        <!-- ============================================================== -->
        <!-- BIR & BUSINESS PERMIT MODAL -->
        <!-- ============================================================== -->
        <div
            v-if="isBirModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
            @click.self="closeBirModal"
        >
            <div class="w-full max-w-md bg-white rounded-2xl border border-slate-200 p-6 shadow-2xl animate-in fade-in zoom-in-95">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-[#E8F7F6] text-[#087F8C] flex items-center justify-center font-bold">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11M20 10v11M8 14v3M12 14v3M16 14v3" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-extrabold text-slate-900">
                            Merchant Verification
                        </h3>
                    </div>
                    <button
                        type="button"
                        @click="closeBirModal"
                        class="text-slate-400 hover:text-slate-600 text-sm font-bold"
                    >
                        ✕
                    </button>
                </div>

                <div class="mt-4 space-y-3 text-xs text-slate-600 leading-relaxed">
                    <div
                        v-if="seller.has_business_permit"
                        class="p-3 bg-emerald-50/70 border border-emerald-200 rounded-xl flex items-center gap-2.5"
                    >
                        <span class="text-emerald-600 font-bold text-base">✓</span>
                        <div>
                            <p class="font-bold text-emerald-900">Registered Business Merchant</p>
                            <p class="text-[11px] text-emerald-700">Official business permit submitted and validated on file.</p>
                        </div>
                    </div>
                    <div
                        v-else
                        class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center gap-2.5"
                    >
                        <span class="text-slate-400 font-bold text-base">ℹ</span>
                        <div>
                            <p class="font-bold text-slate-800">Standard Merchant</p>
                            <p class="text-[11px] text-slate-500">Registered platform seller under standard verification.</p>
                        </div>
                    </div>

                    <div class="space-y-2 pt-2">
                        <div class="flex justify-between border-b border-slate-100 pb-2">
                            <span class="text-slate-400">Store Name:</span>
                            <span class="font-bold text-slate-800">{{ seller.store_name || seller.name }}</span>
                        </div>
                        <div v-if="seller.line_of_business" class="flex justify-between border-b border-slate-100 pb-2">
                            <span class="text-slate-400">Line of Business:</span>
                            <span class="font-bold text-slate-800">{{ seller.line_of_business }}</span>
                        </div>
                        <div class="flex justify-between border-b border-slate-100 pb-2">
                            <span class="text-slate-400">Permit Status:</span>
                            <span :class="seller.has_business_permit ? 'text-emerald-600 font-bold' : 'text-slate-600 font-medium'">
                                {{ seller.has_business_permit ? 'Active & Validated' : 'Standard Account' }}
                            </span>
                        </div>
                        <div class="flex justify-between pt-0.5">
                            <span class="text-slate-400">Return Guarantee:</span>
                            <span class="font-bold text-slate-800">{{ seller.return_policy_days || 7 }} Days Policy</span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        @click="closeBirModal"
                        class="px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-bold hover:bg-slate-800 transition cursor-pointer"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- WEEKLY OPERATING HOURS SCHEDULE MODAL (SELLER-63) -->
        <!-- ============================================================== -->
        <div
            v-if="isScheduleModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
            @click.self="closeScheduleModal"
        >
            <div class="w-full max-w-md bg-white rounded-2xl p-6 shadow-2xl border border-slate-200 space-y-4 animate-in fade-in zoom-in-95">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2.5">
                        <div class="h-9 w-9 rounded-xl bg-[#E8F7F6] text-[#087F8C] flex items-center justify-center">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10" />
                                <polyline points="12 6 12 12 16 14" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-extrabold text-slate-900">Operating Schedule</h3>
                            <p class="text-[11px] text-slate-500">{{ seller.store_name || seller.name }}</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="closeScheduleModal"
                        class="text-slate-400 hover:text-slate-600 text-sm font-bold p-1 cursor-pointer"
                    >
                        ✕
                    </button>
                </div>

                <!-- Live Status & Response -->
                <div class="flex items-center justify-between p-3.5 rounded-xl bg-slate-50 border border-slate-100 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full" :class="seller.store_status?.is_open ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'"></span>
                        <span class="font-bold text-slate-800">{{ seller.store_status?.status_label }}</span>
                        <span class="text-slate-500 text-[11px]">({{ seller.store_status?.detail }})</span>
                    </div>
                    <span class="text-[11px] font-semibold text-[#087F8C] bg-white px-2 py-0.5 rounded-md border border-slate-200">
                        {{ seller.response_time_label }}
                    </span>
                </div>

                <!-- Schedule List -->
                <div class="divide-y divide-slate-100 text-xs">
                    <div
                        v-for="day in scheduleDays"
                        :key="day.key"
                        class="flex items-center justify-between py-2.5 px-1"
                    >
                        <span class="font-semibold text-slate-800">{{ day.label }}</span>
                        <span
                            v-if="seller.operating_hours && seller.operating_hours[day.key] && seller.operating_hours[day.key].is_open"
                            class="font-semibold text-slate-700"
                        >
                            {{ formatTime(seller.operating_hours[day.key].open) }} - {{ formatTime(seller.operating_hours[day.key].close) }}
                        </span>
                        <span v-else class="text-slate-400 font-medium italic">
                            Closed
                        </span>
                    </div>
                </div>

                <div class="pt-2 flex justify-end">
                    <button
                        type="button"
                        @click="closeScheduleModal"
                        class="px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-bold hover:bg-slate-800 transition cursor-pointer"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- REPORT SELLER MODAL -->
        <!-- ============================================================== -->
        <div
            v-if="isReportModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
            @click.self="closeReportModal"
        >
            <div class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-3xl border border-gray-200 bg-white p-6 shadow-2xl sm:p-8">
                <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                    <div>
                        <h2 class="text-lg font-black text-gray-900">Report {{ seller.store_name || seller.name }}</h2>
                        <p class="text-xs text-gray-500">Help our team investigate seller violations.</p>
                    </div>
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200"
                        @click="closeReportModal"
                    >
                        ✕
                    </button>
                </div>

                <form class="mt-5 space-y-4" @submit.prevent="submitReport">
                    <div>
                        <label class="block text-xs font-bold text-gray-700">Reason for Report</label>
                        <select
                            v-model="reportForm.reason"
                            class="mt-1 w-full rounded-xl border border-gray-200 bg-[#F8FAF9] px-3.5 py-2.5 text-xs text-gray-900 focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-teal-100"
                        >
                            <option v-for="r in reportReasons" :key="r" :value="r">{{ r }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700">Detailed Explanation</label>
                        <textarea
                            v-model="reportForm.description"
                            rows="4"
                            required
                            placeholder="Provide details about the issue with this seller..."
                            class="mt-1 w-full rounded-xl border border-gray-200 bg-[#F8FAF9] px-3.5 py-2.5 text-xs text-gray-900 focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-teal-100"
                        />
                        <p v-if="reportForm.errors.description" class="mt-1 text-xs text-red-600">{{ reportForm.errors.description }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700">Supporting Screenshots / Evidence (Max 4 images)</label>
                        <input
                            type="file"
                            accept="image/*"
                            multiple
                            class="mt-1 block w-full text-xs text-gray-500 file:mr-3 file:rounded-xl file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-bold file:text-slate-700 hover:file:bg-slate-200"
                            @change="handleReportFileChange"
                        />
                    </div>

                    <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-100">
                        <button
                            type="button"
                            class="rounded-xl border border-gray-200 px-4 py-2.5 text-xs font-extrabold text-gray-600 hover:bg-gray-50"
                            @click="closeReportModal"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            :disabled="reportForm.processing"
                            class="rounded-xl bg-[#DC2626] px-5 py-2.5 text-xs font-extrabold text-white shadow-sm hover:bg-[#B91C1C] disabled:opacity-50"
                        >
                            {{ reportForm.processing ? 'Submitting Report...' : 'Submit Report' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </component>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
