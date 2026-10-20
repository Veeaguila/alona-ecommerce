<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'

const props = defineProps({
    tab: { type: String, default: 'product' },
    reviews: { type: Object, default: () => ({ data: [] }) },
    storeReviews: { type: Object, default: () => ({ data: [] }) },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
})

/*
|--------------------------------------------------------------------------
| State & Tab Management
|--------------------------------------------------------------------------
*/
const activeTab = ref(props.tab || 'product')
const search = ref(props.filters.search || '')
const selectedRating = ref(props.filters.rating || '')
const hasMediaOnly = ref(props.filters.has_media || false)
const isReportedOnly = ref(props.filters.reported || false)
const replyStatus = ref(props.filters.reply_status || 'all')
const currentSort = ref(props.filters.sort || 'newest')

const activeStats = computed(() => {
    if (activeTab.value === 'store') {
        return {
            total: props.stats.total_store_reviews || 0,
            average: props.stats.store_rating_avg || 0,
            distribution: props.stats.store_distribution || {},
        }
    }
    return {
        total: props.stats.total_product_reviews || 0,
        average: props.stats.product_rating_avg || 0,
        distribution: props.stats.product_distribution || {},
    }
})

const currentReviewsList = computed(() => {
    if (activeTab.value === 'store') {
        return props.storeReviews?.data || []
    }
    return props.reviews?.data || []
})

const currentPagination = computed(() => {
    if (activeTab.value === 'store') {
        return props.storeReviews || {}
    }
    return props.reviews || {}
})

// Star rating percentages
const positiveRateRatio = computed(() => {
    const dist = activeStats.value.distribution
    if (!dist || !activeStats.value.total) return 0
    const positive = (dist[5]?.count || 0) + (dist[4]?.count || 0)
    return Math.round((positive / activeStats.value.total) * 100)
})

/*
|--------------------------------------------------------------------------
| Filter Navigation
|--------------------------------------------------------------------------
*/
const switchTab = (tabName) => {
    activeTab.value = tabName
    applyFilters({ tab: tabName, page: 1 })
}

const filterByRating = (stars) => {
    selectedRating.value = selectedRating.value === String(stars) ? '' : String(stars)
    applyFilters({ rating: selectedRating.value, page: 1 })
}

const applyFilters = (overrides = {}) => {
    router.get(
        route('seller.reviews'),
        {
            tab: activeTab.value,
            rating: selectedRating.value || undefined,
            has_media: hasMediaOnly.value ? 1 : undefined,
            reported: isReportedOnly.value ? 1 : undefined,
            reply_status: replyStatus.value !== 'all' ? replyStatus.value : undefined,
            search: search.value || undefined,
            sort: currentSort.value !== 'newest' ? currentSort.value : undefined,
            ...overrides,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    )
}

const clearFilters = () => {
    search.value = ''
    selectedRating.value = ''
    hasMediaOnly.value = false
    isReportedOnly.value = false
    replyStatus.value = 'all'
    currentSort.value = 'newest'
    applyFilters({
        search: undefined,
        rating: undefined,
        has_media: undefined,
        reported: undefined,
        reply_status: undefined,
        sort: undefined,
        page: 1,
    })
}

const hasActiveFilters = computed(() => {
    return !!(
        search.value ||
        selectedRating.value ||
        hasMediaOnly.value ||
        isReportedOnly.value ||
        replyStatus.value !== 'all' ||
        currentSort.value !== 'newest'
    )
})

/*
|--------------------------------------------------------------------------
| Reply Handling
|--------------------------------------------------------------------------
*/
const replyForms = {}
const openReplyBoxId = ref(null)

const getReplyForm = (review, isStoreReview = false) => {
    const key = `${isStoreReview ? 'store_' : 'prod_'}${review.id}`
    if (!replyForms[key]) {
        replyForms[key] = useForm({ seller_reply: review.seller_reply || '' })
    }
    return replyForms[key]
}

const toggleReplyBox = (reviewId) => {
    openReplyBoxId.value = openReplyBoxId.value === reviewId ? null : reviewId
}

const quickReplies = [
    'Thank you so much for your kind words and support! We hope to serve you again soon.',
    'Thank you for your feedback! We are constantly working to improve our quality and service.',
    'We appreciate your review! If you have any further questions or needs, please feel free to reach out to us.',
]

const setQuickReply = (review, text, isStoreReview = false) => {
    const form = getReplyForm(review, isStoreReview)
    form.seller_reply = text
}

const submitReply = (review, isStoreReview = false) => {
    const form = getReplyForm(review, isStoreReview)
    const targetRoute = isStoreReview
        ? route('seller.store-reviews.reply', review.id)
        : route('seller.reviews.reply', review.id)

    const isEdit = !!review.seller_reply
    form.transform((data) => ({
        ...data,
        is_edit: isEdit,
    })).post(targetRoute, {
        preserveScroll: true,
        onSuccess: () => {
            review.seller_reply = form.seller_reply
            review.seller_replied_at = new Date().toISOString()
            openReplyBoxId.value = null
        },
    })
}

/*
|--------------------------------------------------------------------------
| Reporting Inappropriate Reviews (SELLER-32)
|--------------------------------------------------------------------------
*/
const showReportModal = ref(false)
const reportingReview = ref(null)
const reportingIsStore = ref(false)

const reportForm = useForm({
    reason: 'abusive_language',
    notes: '',
})

const reportReasons = [
    { value: 'abusive_language', label: 'Abusive or Inappropriate Language', desc: 'Contains profanity, hate speech, harassment, or personal threats.' },
    { value: 'fake_or_fraudulent', label: 'Fake or Fraudulent Review', desc: 'Suspected fake review, false claims, or customer never contacted store.' },
    { value: 'competitor_sabotage', label: 'Competitor Malicious Review', desc: 'Suspected competitor attempting to harm store reputation.' },
    { value: 'extortion_or_blackmail', label: 'Extortion or Blackmail', desc: 'Buyer demanded free items or discounts in exchange for positive review.' },
    { value: 'irrelevant_or_spam', label: 'Spam or Irrelevant Content', desc: 'Content is unrelated to the product or store (advertising, links, gibberish).' },
    { value: 'wrong_product', label: 'Wrong Product Mentioned', desc: 'Review describes a completely different item not sold by this store.' },
    { value: 'other', label: 'Other Policy Violation', desc: 'Violates platform marketplace policies.' },
]

const openReportModal = (review, isStoreReview = false) => {
    reportingReview.value = review
    reportingIsStore.value = isStoreReview
    reportForm.reset()
    reportForm.reason = 'abusive_language'
    reportForm.notes = ''
    reportForm.clearErrors()
    showReportModal.value = true
}

const closeReportModal = () => {
    showReportModal.value = false
    reportingReview.value = null
}

const submitReport = () => {
    if (!reportingReview.value) return

    const targetRoute = reportingIsStore.value
        ? route('seller.store-reviews.report', reportingReview.value.id)
        : route('seller.reviews.report', reportingReview.value.id)

    reportForm.post(targetRoute, {
        preserveScroll: true,
        onSuccess: () => {
            if (reportingReview.value) {
                reportingReview.value.is_reported = true
                reportingReview.value.report_reason = reportForm.reason
                reportingReview.value.report_notes = reportForm.notes
                reportingReview.value.report_status = 'pending'
                reportingReview.value.reported_at = new Date().toISOString()
            }
            closeReportModal()
        },
    })
}

/*
|--------------------------------------------------------------------------
| Review Media Lightbox Viewer (SELLER-34)
|--------------------------------------------------------------------------
*/
const showLightbox = ref(false)
const lightboxMediaList = ref([])
const lightboxActiveIndex = ref(0)
const lightboxReviewContext = ref(null)

const isVideo = (path) => {
    if (!path) return false
    const clean = String(path).toLowerCase()
    return clean.endsWith('.mp4') || clean.endsWith('.mov') || clean.endsWith('.webm') || clean.endsWith('.ogg')
}

const getMediaUrl = (path) => {
    if (!path) return ''
    if (path.startsWith('http://') || path.startsWith('https://')) return path
    const normalized = path.replace(/^\/?storage\//, '').replace(/^public\//, '')
    return `/storage/${normalized}`
}

const openLightbox = (review, initialIndex = 0) => {
    if (!review?.media || !review.media.length) return
    lightboxMediaList.value = review.media
    lightboxActiveIndex.value = initialIndex
    lightboxReviewContext.value = review
    showLightbox.value = true
}

const closeLightbox = () => {
    showLightbox.value = false
    lightboxMediaList.value = []
    lightboxReviewContext.value = null
}

const prevMedia = () => {
    if (lightboxActiveIndex.value > 0) {
        lightboxActiveIndex.value--
    } else {
        lightboxActiveIndex.value = lightboxMediaList.value.length - 1
    }
}

const nextMedia = () => {
    if (lightboxActiveIndex.value < lightboxMediaList.value.length - 1) {
        lightboxActiveIndex.value++
    } else {
        lightboxActiveIndex.value = 0
    }
}

const handleKeydown = (e) => {
    if (!showLightbox.value) return
    if (e.key === 'Escape') closeLightbox()
    if (e.key === 'ArrowLeft') prevMedia()
    if (e.key === 'ArrowRight') nextMedia()
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown)
})

/*
|--------------------------------------------------------------------------
| Formatting Helpers
|--------------------------------------------------------------------------
*/
const formatDate = (dateStr) => {
    if (!dateStr) return '—'
    const d = new Date(dateStr)
    return d.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}

const starsText = (rating) => {
    const r = Math.round(Number(rating || 0))
    return '★'.repeat(r).padEnd(5, '☆')
}

const reasonLabel = (val) => {
    const found = reportReasons.find((r) => r.value === val)
    return found ? found.label : val?.replace(/_/g, ' ') || 'Reported'
}
</script>

<template>
    <Head title="Customer Feedback & Reviews - Seller Center" />

    <SellerLayout>
        <div class="mx-auto max-w-7xl space-y-6">

            <!-- PAGE HEADER & TABS -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-5">
                <div>
                    <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-[#087F8C]">
                        <span>Customer Management</span>
                        <span>•</span>
                        <span>Feedback & Reputation</span>
                    </div>
                    <h1 class="mt-1 text-2xl font-black text-slate-900 tracking-tight">
                        Reviews & Ratings Center
                    </h1>
                    <p class="mt-1 text-xs text-slate-500">
                        Monitor buyer sentiment, reply to customer feedback, analyze star breakdown, and report policy violations.
                    </p>
                </div>

                <!-- TABS (SELLER-33: Product Reviews vs Direct Store Ratings) -->
                <div class="flex items-center rounded-2xl bg-slate-100 p-1.5 shadow-inner">
                    <button
                        type="button"
                        @click="switchTab('product')"
                        class="flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition"
                        :class="activeTab === 'product' ? 'bg-white text-[#087F8C] shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                    >
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0v10l-8 4-8-4V7m16 0l-8 4-8-4m8 4v10" />
                        </svg>
                        <span>Product Reviews</span>
                        <span
                            class="rounded-full px-2 py-0.5 text-[10px]"
                            :class="activeTab === 'product' ? 'bg-[#E8F7F6] text-[#087F8C]' : 'bg-slate-200 text-slate-700'"
                        >
                            {{ props.stats.total_product_reviews || 0 }}
                        </span>
                    </button>

                    <button
                        type="button"
                        @click="switchTab('store')"
                        class="flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition"
                        :class="activeTab === 'store' ? 'bg-white text-[#087F8C] shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                    >
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10l2-6h14l2 6M4 10v9a2 2 0 002 2h12a2 2 0 002-2v-9M3 10h18M8 10v3m4-3v3m4-3v3" />
                        </svg>
                        <span>Store Ratings</span>
                        <span
                            class="rounded-full px-2 py-0.5 text-[10px]"
                            :class="activeTab === 'store' ? 'bg-[#E8F7F6] text-[#087F8C]' : 'bg-slate-200 text-slate-700'"
                        >
                            {{ props.stats.total_store_reviews || 0 }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- SELLER-35: RATING BREAKDOWN & PERFORMANCE STATS HERO -->
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-12">

                <!-- Overall Average Card -->
                <div class="rounded-2xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-6 shadow-xs lg:col-span-4 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-400">
                                {{ activeTab === 'store' ? 'Store Reputation' : 'Product Satisfaction' }}
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                {{ positiveRateRatio }}% Positive
                            </span>
                        </div>

                        <div class="mt-4 flex items-baseline gap-2">
                            <span class="text-5xl font-black text-slate-900 tracking-tight">
                                {{ Number(activeStats.average).toFixed(1) }}
                            </span>
                            <span class="text-xl font-bold text-slate-400">/ 5.0</span>
                        </div>

                        <div class="mt-2 flex items-center gap-1 text-lg text-amber-400">
                            <span v-for="star in 5" :key="star" :class="star <= Math.round(activeStats.average) ? 'text-amber-400' : 'text-slate-200'">
                                ★
                            </span>
                            <span class="ml-2 text-xs font-medium text-slate-500">
                                Based on {{ activeStats.total }} total {{ activeTab === 'store' ? 'store ratings' : 'product reviews' }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-6 border-t border-slate-200 pt-4 grid grid-cols-2 gap-3 text-center">
                        <div class="rounded-xl bg-slate-100 p-2.5">
                            <div class="text-[10px] font-bold uppercase text-slate-500">Total Combined</div>
                            <div class="text-sm font-black text-slate-800">{{ props.stats.total_combined || 0 }} Reviews</div>
                        </div>
                        <div class="rounded-xl bg-slate-100 p-2.5">
                            <div class="text-[10px] font-bold uppercase text-slate-500">Storewide Avg</div>
                            <div class="text-sm font-black text-[#087F8C]">⭐ {{ Number(props.stats.combined_rating_avg || 0).toFixed(1) }}</div>
                        </div>
                    </div>
                </div>

                <!-- SELLER-35: Visual Star Distribution Progress Bars -->
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs lg:col-span-8 flex flex-col justify-between">
                    <div class="flex items-center justify-between mb-3">
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-700">
                            Rating Distribution Breakdown
                        </div>
                        <span class="text-[11px] text-slate-400">Click any rating bar to quick-filter list</span>
                    </div>

                    <!-- Progress Bars 5 to 1 -->
                    <div class="space-y-2.5">
                        <button
                            v-for="star in [5, 4, 3, 2, 1]"
                            :key="star"
                            type="button"
                            @click="filterByRating(star)"
                            class="group flex w-full items-center gap-3 rounded-xl p-1.5 transition text-left"
                            :class="selectedRating === String(star) ? 'bg-[#E8F7F6] ring-1 ring-[#087F8C]' : 'hover:bg-slate-50'"
                        >
                            <!-- Star Label -->
                            <div class="flex w-14 shrink-0 items-center gap-1 text-xs font-bold text-slate-700">
                                <span>{{ star }}</span>
                                <span class="text-amber-400">★</span>
                            </div>

                            <!-- Progress Track -->
                            <div class="relative h-3 flex-1 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full transition-all duration-500"
                                    :class="[
                                        star >= 4 ? 'bg-[#087F8C]' : star === 3 ? 'bg-amber-400' : 'bg-red-400',
                                        selectedRating === String(star) ? 'ring-2 ring-offset-1 ring-[#087F8C]' : ''
                                    ]"
                                    :style="{ width: `${activeStats.distribution[star]?.percentage || 0}%` }"
                                ></div>
                            </div>

                            <!-- Stats count & percent -->
                            <div class="flex w-24 shrink-0 items-center justify-end gap-2 text-right">
                                <span class="font-mono text-xs font-bold text-slate-800">
                                    {{ activeStats.distribution[star]?.count || 0 }}
                                </span>
                                <span class="w-10 text-[10px] font-medium text-slate-400">
                                    {{ activeStats.distribution[star]?.percentage || 0 }}%
                                </span>
                            </div>
                        </button>
                    </div>

                    <!-- Reset rating filter if active -->
                    <div v-if="selectedRating" class="mt-3 flex items-center justify-between border-t border-slate-100 pt-2 text-xs">
                        <span class="text-[#087F8C] font-semibold">Filtering by {{ selectedRating }}-star reviews</span>
                        <button type="button" @click="filterByRating(selectedRating)" class="text-xs font-bold text-slate-500 hover:text-slate-800 underline">
                            Clear star filter
                        </button>
                    </div>
                </div>
            </div>

            <!-- FILTER TOOLBAR -->
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">

                    <!-- Search Input -->
                    <div class="relative flex-1 max-w-md">
                        <svg class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search reviewer, product, or comment..."
                            class="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-4 text-xs text-slate-800 outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                            @keydown.enter="applyFilters({ search: search, page: 1 })"
                        />
                    </div>

                    <!-- Dropdowns & Toggles -->
                    <div class="flex flex-wrap items-center gap-2">

                        <!-- Rating Dropdown -->
                        <select
                            v-model="selectedRating"
                            @change="applyFilters({ rating: selectedRating, page: 1 })"
                            class="h-9 rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 outline-none focus:border-[#087F8C]"
                        >
                            <option value="">All Ratings</option>
                            <option value="5">5 Stars (★ ★ ★ ★ ★)</option>
                            <option value="4">4 Stars (★ ★ ★ ★ ☆)</option>
                            <option value="3">3 Stars (★ ★ ★ ☆ ☆)</option>
                            <option value="2">2 Stars (★ ★ ☆ ☆ ☆)</option>
                            <option value="1">1 Star  (★ ☆ ☆ ☆ ☆)</option>
                        </select>

                        <!-- Reply Status Dropdown -->
                        <select
                            v-model="replyStatus"
                            @change="applyFilters({ reply_status: replyStatus, page: 1 })"
                            class="h-9 rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 outline-none focus:border-[#087F8C]"
                        >
                            <option value="all">All Response Status</option>
                            <option value="unreplied">Awaiting Reply</option>
                            <option value="replied">Replied</option>
                        </select>

                        <!-- Sort Dropdown -->
                        <select
                            v-model="currentSort"
                            @change="applyFilters({ sort: currentSort, page: 1 })"
                            class="h-9 rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 outline-none focus:border-[#087F8C]"
                        >
                            <option value="newest">Newest First</option>
                            <option value="oldest">Oldest First</option>
                            <option value="highest">Highest Rating</option>
                            <option value="lowest">Lowest Rating</option>
                        </select>

                        <!-- Media Toggle -->
                        <label
                            class="flex h-9 cursor-pointer items-center gap-1.5 rounded-xl border px-3 text-xs font-semibold transition"
                            :class="hasMediaOnly ? 'border-[#087F8C] bg-[#E8F7F6] text-[#087F8C]' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                        >
                            <input
                                type="checkbox"
                                v-model="hasMediaOnly"
                                @change="applyFilters({ has_media: hasMediaOnly ? 1 : undefined, page: 1 })"
                                class="sr-only"
                            />
                            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span>With Photos/Videos</span>
                        </label>

                        <!-- Reported Only Toggle -->
                        <label
                            class="flex h-9 cursor-pointer items-center gap-1.5 rounded-xl border px-3 text-xs font-semibold transition"
                            :class="isReportedOnly ? 'border-rose-400 bg-rose-50 text-rose-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                        >
                            <input
                                type="checkbox"
                                v-model="isReportedOnly"
                                @change="applyFilters({ reported: isReportedOnly ? 1 : undefined, page: 1 })"
                                class="sr-only"
                            />
                            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H20a1 1 0 011 1v7a1 1 0 01-1 1h-7.5l-1-1H5a2 2 0 00-2 2z" />
                            </svg>
                            <span>Reported Only</span>
                        </label>

                        <!-- Clear filters button -->
                        <button
                            v-if="hasActiveFilters"
                            type="button"
                            @click="clearFilters"
                            class="h-9 rounded-xl border border-slate-200 px-3 text-xs font-bold text-slate-500 hover:bg-slate-100 transition"
                        >
                            Clear All
                        </button>
                    </div>
                </div>
            </div>

            <!-- REVIEWS LIST -->
            <div class="space-y-4">
                <div
                    v-for="review in currentReviewsList"
                    :key="review.id"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs transition hover:border-slate-300"
                    :class="review.is_reported ? 'bg-amber-50/20 border-amber-200' : ''"
                >
                    <!-- Review Top Bar -->
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">

                        <!-- Reviewer & Product/Order Info -->
                        <div class="flex items-start gap-3.5">
                            <!-- Avatar / Initial -->
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#E8F7F6] text-sm font-bold text-[#087F8C] shadow-xs">
                                {{ review.user?.name ? review.user.name.charAt(0).toUpperCase() : 'C' }}
                            </div>

                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="text-sm font-bold text-slate-900">
                                        {{ review.user?.name || 'Customer' }}
                                    </h4>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">
                                        <svg class="h-3 w-3 text-emerald-600" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                                        </svg>
                                        Verified Purchase
                                    </span>

                                    <!-- Reported Badge (SELLER-32) -->
                                    <span
                                        v-if="review.is_reported"
                                        class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-bold text-amber-800"
                                        :title="`Reported on ${formatDate(review.reported_at)}: ${review.report_notes}`"
                                    >
                                        <svg class="h-3 w-3 shrink-0 text-amber-800" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H20a1 1 0 011 1v7a1 1 0 01-1 1h-7.5l-1-1H5a2 2 0 00-2 2z" />
                                        </svg>
                                        <span>Reported:</span>
                                        <span>{{ reasonLabel(review.report_reason) }}</span>
                                        <span class="text-[9px] uppercase tracking-wider font-semibold opacity-75">({{ review.report_status || 'Under Review' }})</span>
                                    </span>
                                </div>

                                <!-- Subtitle / Context -->
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                    <span>{{ formatDate(review.created_at) }}</span>

                                    <!-- Product Info if Product Review -->
                                    <template v-if="activeTab === 'product' && review.product">
                                        <span>•</span>
                                        <span class="font-medium text-slate-700">Product: {{ review.product.name }}</span>
                                        <span
                                            v-if="review.variant_name || review.variant?.title"
                                            class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600"
                                        >
                                            Variant: {{ review.variant_name || review.variant?.title }}
                                        </span>
                                    </template>

                                    <!-- Order Info if Store Review -->
                                    <template v-if="activeTab === 'store' && review.order">
                                        <span>•</span>
                                        <span class="font-medium text-slate-700">Order #{{ review.order.order_number }}</span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Star Rating & Actions -->
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-1 text-sm font-bold text-amber-400">
                                <span v-for="star in 5" :key="star" :class="star <= review.rating ? 'text-amber-400' : 'text-slate-200'">
                                    ★
                                </span>
                                <span class="ml-1 text-xs text-slate-600">{{ review.rating }}/5</span>
                            </div>

                            <!-- SELLER-32: Report Review Action Button -->
                            <button
                                v-if="!review.is_reported"
                                type="button"
                                @click="openReportModal(review, activeTab === 'store')"
                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-600 transition hover:border-rose-300 hover:bg-rose-50 hover:text-rose-700"
                                title="Report abusive or fraudulent review to platform admin"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" />
                                </svg>
                                <span>Report</span>
                            </button>
                        </div>
                    </div>

                    <!-- Review Comment Body -->
                    <div class="mt-3.5 text-sm leading-relaxed text-slate-700 whitespace-pre-line">
                        {{ review.comment || 'No written comment provided by the buyer.' }}
                    </div>

                    <!-- SELLER-34: REVIEW MEDIA GALLERY (PHOTOS & VIDEOS) -->
                    <div v-if="review.media && review.media.length" class="mt-4">
                        <div class="mb-1.5 flex items-center gap-1.5 text-xs font-bold text-slate-500">
                            <svg class="h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span>Customer Attachments ({{ review.media.length }}) • Click to view in lightbox</span>
                        </div>

                        <div class="flex flex-wrap gap-2.5">
                            <button
                                v-for="(mediaPath, index) in review.media"
                                :key="index"
                                type="button"
                                @click="openLightbox(review, index)"
                                class="group relative h-20 w-20 overflow-hidden rounded-xl border border-slate-200 bg-slate-100 shadow-2xs transition hover:scale-105 hover:border-[#087F8C] focus:outline-none"
                            >
                                <!-- Video indicator overlay -->
                                <template v-if="isVideo(mediaPath)">
                                    <div class="flex h-full w-full items-center justify-center bg-slate-900 text-white">
                                        <svg class="h-7 w-7 text-white/90" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M8 5v14l11-7z" />
                                        </svg>
                                    </div>
                                    <span class="absolute bottom-1 right-1 rounded bg-black/75 px-1 py-0.5 text-[9px] font-bold text-white uppercase tracking-wider">
                                        Video
                                    </span>
                                </template>

                                <!-- Image thumbnail -->
                                <template v-else>
                                    <img
                                        :src="getMediaUrl(mediaPath)"
                                        alt="Review Attachment"
                                        class="h-full w-full object-cover"
                                        loading="lazy"
                                    />
                                    <div class="absolute inset-0 bg-black/0 transition group-hover:bg-black/10 flex items-center justify-center">
                                        <svg class="h-5 w-5 text-white opacity-0 transition group-hover:opacity-100 drop-shadow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                                        </svg>
                                    </div>
                                </template>
                            </button>
                        </div>
                    </div>

                    <!-- SELLER REPLY SECTION -->
                    <div class="mt-4 border-t border-slate-100 pt-3.5">

                        <!-- Already Replied Card -->
                        <div v-if="review.seller_reply && openReplyBoxId !== review.id" class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2 font-bold text-emerald-900">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-200 text-emerald-800">
                                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10l2-6h14l2 6M4 10v9a2 2 0 002 2h12a2 2 0 002-2v-9M3 10h18M8 10v3m4-3v3m4-3v3" />
                                        </svg>
                                    </span>
                                    <span>Your Store Response</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-[11px] text-emerald-700">
                                        {{ formatDate(review.seller_replied_at) }}
                                    </span>
                                    <button
                                        type="button"
                                        @click="toggleReplyBox(review.id)"
                                        class="text-xs font-bold text-[#087F8C] hover:underline"
                                    >
                                        Edit Response
                                    </button>
                                </div>
                            </div>
                            <p class="mt-2 text-xs leading-relaxed text-emerald-950 whitespace-pre-line">
                                {{ review.seller_reply }}
                            </p>
                        </div>

                        <!-- Reply Form (New or Editing) -->
                        <div v-else-if="!review.seller_reply || openReplyBoxId === review.id" class="space-y-3">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-bold text-slate-800">
                                    {{ review.seller_reply ? 'Update Your Response' : 'Reply to this Customer' }}
                                </label>
                                <button
                                    v-if="review.seller_reply"
                                    type="button"
                                    @click="toggleReplyBox(review.id)"
                                    class="text-xs text-slate-400 hover:text-slate-600"
                                >
                                    Cancel
                                </button>
                            </div>

                            <!-- Quick Reply Templates -->
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="(text, qIdx) in quickReplies"
                                    :key="qIdx"
                                    type="button"
                                    @click="setQuickReply(review, text, activeTab === 'store')"
                                    class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] text-slate-600 transition hover:border-[#087F8C] hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                                >
                                    + {{ text.slice(0, 38) }}...
                                </button>
                            </div>

                            <form @submit.prevent="submitReply(review, activeTab === 'store')">
                                <textarea
                                    v-model="getReplyForm(review, activeTab === 'store').seller_reply"
                                    rows="3"
                                    placeholder="Write a courteous and helpful response to your customer..."
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-800 outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                ></textarea>
                                <p v-if="getReplyForm(review, activeTab === 'store').errors.seller_reply" class="mt-1 text-xs text-red-600">
                                    {{ getReplyForm(review, activeTab === 'store').errors.seller_reply }}
                                </p>

                                <div class="mt-2.5 flex items-center justify-end gap-2">
                                    <button
                                        type="submit"
                                        :disabled="getReplyForm(review, activeTab === 'store').processing"
                                        class="inline-flex items-center gap-1.5 rounded-xl bg-[#087F8C] px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-[#066B76] disabled:opacity-60 cursor-pointer"
                                    >
                                        {{ getReplyForm(review, activeTab === 'store').processing ? 'Posting...' : 'Post Response' }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="!currentReviewsList.length" class="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-xs">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-[#E8F7F6] text-[#087F8C]">
                        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                    </div>
                    <h3 class="mt-4 text-base font-bold text-slate-900">No reviews found</h3>
                    <p class="mt-1 text-xs text-slate-500 max-w-sm mx-auto">
                        {{ hasActiveFilters ? 'No reviews match your current filters. Try resetting the filters above.' : 'You have not received any customer feedback in this section yet.' }}
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

                <!-- Pagination -->
                <div v-if="currentPagination.links && currentPagination.links.length > 3" class="mt-6 flex items-center justify-between border-t border-slate-200 pt-4">
                    <div class="text-xs text-slate-500">
                        Showing {{ currentPagination.from || 0 }} to {{ currentPagination.to || 0 }} of {{ currentPagination.total || 0 }} reviews
                    </div>

                    <div class="flex items-center gap-1">
                        <Link
                            v-for="(link, lIdx) in currentPagination.links"
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

            <!-- ========================================================= -->
            <!-- SELLER-32: REPORT INAPPROPRIATE REVIEW MODAL              -->
            <!-- ========================================================= -->
            <div
                v-if="showReportModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4 overflow-y-auto"
                @click.self="closeReportModal"
            >
                <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                    <div class="flex items-start justify-between border-b border-slate-100 pb-4">
                        <div>
                            <div class="flex items-center gap-2 text-rose-600 font-bold text-xs uppercase tracking-wider">
                                <span class="flex items-center gap-1">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H20a1 1 0 011 1v7a1 1 0 01-1 1h-7.5l-1-1H5a2 2 0 00-2 2z" />
                                    </svg>
                                    <span>Platform Dispute</span>
                                </span>
                                <span>•</span>
                                <span>Moderation Request</span>
                            </div>
                            <h3 class="text-lg font-black text-slate-900 mt-1">
                                Report Inappropriate Review
                            </h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Reports are sent directly to platform administrators for investigation.
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="closeReportModal"
                            class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                        >
                            ✕
                        </button>
                    </div>

                    <form @submit.prevent="submitReport" class="mt-4 space-y-4">

                        <!-- Target Review Snippet Preview -->
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs">
                            <div class="flex items-center justify-between text-slate-500 font-semibold mb-1">
                                <span>Reviewer: {{ reportingReview?.user?.name || 'Customer' }}</span>
                                <span class="text-amber-500">{{ reportingReview?.rating }} ★</span>
                            </div>
                            <p class="text-slate-700 italic line-clamp-2">
                                "{{ reportingReview?.comment || 'No comment text' }}"
                            </p>
                        </div>

                        <!-- Reason Options Radio List -->
                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-2">
                                Select Violation Category <span class="text-rose-500">*</span>
                            </label>
                            <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                                <label
                                    v-for="r in reportReasons"
                                    :key="r.value"
                                    class="flex items-start gap-3 rounded-xl border p-2.5 text-xs cursor-pointer transition"
                                    :class="reportForm.reason === r.value ? 'border-[#087F8C] bg-[#E8F7F6]' : 'border-slate-200 bg-white hover:bg-slate-50'"
                                >
                                    <input
                                        v-model="reportForm.reason"
                                        type="radio"
                                        :value="r.value"
                                        class="mt-0.5 text-[#087F8C] focus:ring-[#087F8C]"
                                    />
                                    <div>
                                        <div class="font-bold text-slate-900">{{ r.label }}</div>
                                        <div class="text-[11px] text-slate-500">{{ r.desc }}</div>
                                    </div>
                                </label>
                            </div>
                            <p v-if="reportForm.errors.reason" class="mt-1 text-xs text-rose-600">{{ reportForm.errors.reason }}</p>
                        </div>

                        <!-- Explanation / Details -->
                        <div>
                            <label class="block text-xs font-bold text-slate-800 mb-1">
                                Explain the Incident in Detail <span class="text-rose-500">*</span>
                            </label>
                            <textarea
                                v-model="reportForm.notes"
                                rows="3"
                                placeholder="Describe why this review violates platform rules and attach any relevant context for administrators..."
                                class="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-800 outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                            ></textarea>
                            <div class="mt-1 flex justify-between text-[10px] text-slate-400">
                                <span>Minimum 5 characters.</span>
                                <span>{{ reportForm.notes.length }} / 1500</span>
                            </div>
                            <p v-if="reportForm.errors.notes" class="mt-1 text-xs text-rose-600">{{ reportForm.errors.notes }}</p>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                            <button
                                type="button"
                                @click="closeReportModal"
                                class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 transition"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="reportForm.processing || reportForm.notes.length < 5"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-rose-700 transition disabled:opacity-50 cursor-pointer"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span>{{ reportForm.processing ? 'Submitting...' : 'Submit Report to Admin' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- SELLER-34: REVIEW MEDIA LIGHTBOX VIEWER MODAL             -->
            <!-- ========================================================= -->
            <div
                v-if="showLightbox"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 backdrop-blur-md p-4 select-none"
                @click.self="closeLightbox"
            >
                <div class="relative flex flex-col items-center max-w-5xl w-full h-[90vh]">

                    <!-- Top Bar with Review Info & Close -->
                    <div class="flex items-center justify-between w-full px-4 py-3 bg-white/10 backdrop-blur-md rounded-2xl text-white mb-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-[#087F8C] text-xs font-bold">
                                {{ lightboxReviewContext?.user?.name ? lightboxReviewContext.user.name.charAt(0).toUpperCase() : 'C' }}
                            </div>
                            <div>
                                <div class="text-xs font-bold">{{ lightboxReviewContext?.user?.name || 'Customer' }}</div>
                                <div class="text-[10px] text-amber-300">
                                    {{ starsText(lightboxReviewContext?.rating) }} • {{ formatDate(lightboxReviewContext?.created_at) }}
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <span class="text-xs font-mono font-bold text-slate-300">
                                {{ lightboxActiveIndex + 1 }} / {{ lightboxMediaList.length }}
                            </span>
                            <button
                                type="button"
                                @click="closeLightbox"
                                class="rounded-lg bg-white/10 p-2 text-white hover:bg-white/20 transition cursor-pointer"
                                title="Close (Esc)"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Main Viewer Stage -->
                    <div class="relative flex-1 w-full flex items-center justify-center overflow-hidden rounded-2xl bg-black/50">

                        <!-- Prev Button -->
                        <button
                            v-if="lightboxMediaList.length > 1"
                            type="button"
                            @click="prevMedia"
                            class="absolute left-4 z-10 rounded-full bg-black/60 p-3 text-white hover:bg-black/80 transition cursor-pointer"
                            title="Previous (Left Arrow)"
                        >
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>

                        <!-- Media Display (Image or Video) -->
                        <div class="h-full w-full flex items-center justify-center p-2">
                            <video
                                v-if="isVideo(lightboxMediaList[lightboxActiveIndex])"
                                :src="getMediaUrl(lightboxMediaList[lightboxActiveIndex])"
                                controls
                                autoplay
                                class="max-h-full max-w-full rounded-xl shadow-2xl"
                            ></video>

                            <img
                                v-else
                                :src="getMediaUrl(lightboxMediaList[lightboxActiveIndex])"
                                alt="Enlarged Review Media"
                                class="max-h-full max-w-full object-contain rounded-xl shadow-2xl"
                            />
                        </div>

                        <!-- Next Button -->
                        <button
                            v-if="lightboxMediaList.length > 1"
                            type="button"
                            @click="nextMedia"
                            class="absolute right-4 z-10 rounded-full bg-black/60 p-3 text-white hover:bg-black/80 transition cursor-pointer"
                            title="Next (Right Arrow)"
                        >
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>

                    <!-- Bottom Thumbnail Strip -->
                    <div v-if="lightboxMediaList.length > 1" class="mt-3 flex items-center justify-center gap-2 overflow-x-auto py-1">
                        <button
                            v-for="(path, idx) in lightboxMediaList"
                            :key="idx"
                            type="button"
                            @click="lightboxActiveIndex = idx"
                            class="relative h-14 w-14 overflow-hidden rounded-lg border-2 transition"
                            :class="lightboxActiveIndex === idx ? 'border-[#087F8C] scale-105' : 'border-white/30 opacity-60 hover:opacity-100'"
                        >
                            <div v-if="isVideo(path)" class="h-full w-full flex items-center justify-center bg-slate-800 text-white text-[10px]">
                                ▶ Video
                            </div>
                            <img v-else :src="getMediaUrl(path)" class="h-full w-full object-cover" />
                        </button>
                    </div>

                    <!-- Customer Comment Caption -->
                    <div v-if="lightboxReviewContext?.comment" class="mt-2 text-center text-xs text-slate-300 max-w-2xl px-4 line-clamp-2">
                        "{{ lightboxReviewContext.comment }}"
                    </div>
                </div>
            </div>

        </div>
    </SellerLayout>
</template>
