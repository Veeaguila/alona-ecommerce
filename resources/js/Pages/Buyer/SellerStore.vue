<script setup>
import { computed, ref } from 'vue'
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
    stats: {
        type: Object,
        default: () => ({}),
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            sort: 'latest',
        }),
    },
})

const page = usePage()
const user = computed(() => page.props.auth?.user || null)
const isGuest = computed(() => !user.value)
const layoutComponent = computed(() => isGuest.value ? GuestLayout : BuyerLayout)
const statusMessage = computed(() => page.props.flash?.status || null)

const searchQuery = ref(props.filters.search || '')
const sortOption = ref(props.filters.sort || 'latest')
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

const handleSearch = () => {
    router.get(
        route('buyer.store.show', props.seller.id),
        {
            search: searchQuery.value || undefined,
            sort: sortOption.value || 'latest',
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
</script>

<template>
    <Head :title="`${seller.store_name || seller.name} - Storefront`" />

    <component :is="layoutComponent">
        <main class="min-h-[calc(100vh-80px)] w-full min-w-0 overflow-x-hidden bg-[#F8FAF9]">
            <div class="mx-auto w-full max-w-[1536px] px-4 py-6 pb-16 sm:px-6 sm:py-8 lg:px-8 2xl:px-10 lg:py-10">

                <!-- BREADCRUMBS -->
                <nav class="mb-4 flex items-center gap-2 text-xs text-gray-500">
                    <Link :href="route('buyer.products')" class="hover:text-[#087F8C]">Products</Link>
                    <span>/</span>
                    <span class="font-bold text-gray-800">{{ seller.store_name || seller.name }}</span>
                </nav>

                <!-- STORE BANNER & PROFILE CARD (BUYER-34: REPORT SELLER) -->
                <div class="relative overflow-hidden rounded-3xl border border-[#E5E7EB] bg-white p-6 shadow-sm sm:p-8 lg:p-10">
                    <div class="pointer-events-none absolute right-0 top-0 h-44 w-44 rounded-full bg-[#E8F7F6]/60 blur-2xl"></div>

                    <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-start gap-4 sm:gap-6">
                            <!-- STORE LOGO / AVATAR -->
                            <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-[#E8F7F6] text-xl font-black text-[#087F8C] ring-2 ring-[#087F8C]/20 sm:h-20 sm:w-20 sm:text-2xl">
                                <img
                                    v-if="seller.store_logo_path"
                                    :src="imageUrl(seller.store_logo_path)"
                                    :alt="seller.store_name || seller.name"
                                    class="h-full w-full object-cover"
                                />
                                <span v-else>{{ storeInitials }}</span>
                            </div>

                            <!-- STORE INFO -->
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h1 class="text-xl font-black text-gray-900 sm:text-2xl">
                                        {{ seller.store_name || seller.name }}
                                    </h1>
                                    <span class="rounded-full bg-[#EAF8F1] px-2.5 py-0.5 text-[10px] font-extrabold text-[#16A34A]">
                                        ✓ Verified Seller
                                    </span>
                                </div>

                                <p v-if="seller.store_description" class="mt-1.5 max-w-2xl text-xs leading-relaxed text-gray-600 sm:text-sm">
                                    {{ seller.store_description }}
                                </p>
                                <p v-else class="mt-1 text-xs text-gray-400">
                                    Welcome to our official store on Alona.
                                </p>

                                <!-- SELLER STATS -->
                                <div class="mt-4 flex flex-wrap items-center gap-4 text-xs sm:gap-6">
                                    <div>
                                        <span class="font-bold text-gray-900">{{ stats.total_products || 0 }}</span>
                                        <span class="ml-1 text-gray-500">Products</span>
                                    </div>
                                    <div>
                                        <span class="font-bold text-[#F4B942]">★ {{ stats.avg_rating ? stats.avg_rating.toFixed(1) : 'New' }}</span>
                                        <span class="ml-1 text-gray-500">({{ stats.total_reviews || 0 }} reviews)</span>
                                    </div>
                                    <div>
                                        <span class="font-bold text-gray-900">{{ seller.return_policy_days || 7 }} Days</span>
                                        <span class="ml-1 text-gray-500">Return Window</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- STORE ACTIONS -->
                        <div class="flex flex-wrap items-center gap-2.5">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-red-200 bg-[#FEF2F2] px-3.5 py-2 text-xs font-bold text-[#DC2626] transition hover:bg-[#FEE2E2]"
                                title="Report this seller for policy violations"
                                @click="openReportModal"
                            >
                                <span>🚩</span>
                                <span>Report Seller</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- SUCCESS MESSAGE -->
                <div
                    v-if="statusMessage"
                    class="mt-6 flex items-start gap-3 rounded-2xl border border-[#CDE9E4] bg-[#EAF8F1] p-4 text-xs font-bold text-[#17784F] shadow-sm sm:text-sm"
                >
                    <span class="text-base">✓</span>
                    <span>{{ statusMessage }}</span>
                </div>

                <!-- PRODUCTS FILTER & HEADER -->
                <div class="mt-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-black tracking-tight text-gray-900 sm:text-xl">
                            All Products from {{ seller.store_name || seller.name }}
                        </h2>
                        <p class="mt-0.5 text-xs text-gray-500">
                            Showing {{ products.total || 0 }} items available
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <!-- SEARCH -->
                        <div class="relative w-full sm:w-60">
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search store..."
                                class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-8 text-xs text-gray-900 placeholder:text-gray-400 focus:border-[#087F8C] focus:ring-2 focus:ring-[#E8F7F6]"
                                @keydown.enter="handleSearch"
                            />
                            <span class="pointer-events-none absolute left-3 top-2.5 text-xs text-gray-400">🔍</span>
                        </div>

                        <!-- SORT SELECT -->
                        <select
                            v-model="sortOption"
                            class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-bold text-gray-700 focus:border-[#087F8C] focus:ring-2 focus:ring-[#E8F7F6]"
                            @change="changeSort(sortOption)"
                        >
                            <option value="latest">Latest Items</option>
                            <option value="price-low">Price: Low to High</option>
                            <option value="price-high">Price: High to Low</option>
                            <option value="rating">Top Rated</option>
                        </select>
                    </div>
                </div>

                <!-- PRODUCTS GRID -->
                <div
                    v-if="products.data?.length"
                    class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 md:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6"
                >
                    <Link
                        v-for="product in products.data"
                        :key="product.id"
                        :href="route('buyer.product', product.id)"
                        class="group block min-w-0 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-[#087F8C]/40 hover:shadow-md"
                    >
                        <div class="relative flex aspect-square w-full items-center justify-center overflow-hidden bg-[#F8FAF9] p-3">
                            <img
                                v-if="product.image_path"
                                :src="imageUrl(product.image_path)"
                                :alt="product.name"
                                class="h-full w-full object-contain transition duration-300 group-hover:scale-105"
                                loading="lazy"
                            />
                            <span v-else class="text-3xl">🛍️</span>
                        </div>

                        <div class="p-3 sm:p-3.5">
                            <h3 class="truncate text-xs font-semibold text-gray-900 group-hover:text-[#087F8C]" :title="product.name">
                                {{ product.name }}
                            </h3>

                            <div class="mt-1 flex items-center gap-1 text-[11px] text-[#F4B942]">
                                <span>★</span>
                                <span class="font-bold text-gray-700">{{ Number(product.rating || 0).toFixed(1) }}</span>
                                <span class="text-gray-400">({{ product.reviews_count || 0 }})</span>
                            </div>

                            <div class="mt-2.5 flex items-center justify-between">
                                <span class="text-xs font-black text-gray-900 sm:text-sm">
                                    ₱{{ formatPrice(product.price) }}
                                </span>
                                <span class="rounded-lg bg-[#087F8C] px-2 py-1 text-[10px] font-bold text-white transition group-hover:bg-[#066B76]">
                                    View
                                </span>
                            </div>
                        </div>
                    </Link>
                </div>

                <!-- EMPTY STATE -->
                <div
                    v-else
                    class="mt-8 rounded-3xl border border-dashed border-gray-200 bg-white p-12 text-center"
                >
                    <span class="text-4xl">🛍️</span>
                    <h2 class="mt-3 text-sm font-extrabold text-gray-900">No products found</h2>
                    <p class="mt-1 text-xs text-gray-500">
                        This store does not have items matching your search criteria.
                    </p>
                </div>

                <!-- PAGINATION -->
                <div
                    v-if="products.links?.length > 3"
                    class="mt-8 flex w-full flex-wrap items-center justify-center gap-1.5"
                >
                    <Link
                        v-for="(link, index) in products.links"
                        :key="index"
                        :href="link.url || '#'"
                        v-html="link.label"
                        class="rounded-xl border px-3 py-1.5 text-xs font-bold transition"
                        :class="[
                            link.active ? 'border-[#087F8C] bg-[#087F8C] text-white' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50',
                            !link.url ? 'pointer-events-none opacity-40' : ''
                        ]"
                        preserve-scroll
                        preserve-state
                    />
                </div>

            </div>
        </main>

        <!-- REPORT SELLER MODAL (BUYER-34) -->
        <div
            v-if="isReportModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
            @click.self="closeReportModal"
        >
            <div class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-3xl border border-gray-200 bg-white p-6 shadow-2xl sm:p-8">
                <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                    <div>
                        <h2 class="text-lg font-black text-gray-900">Report {{ seller.store_name || seller.name }}</h2>
                        <p class="text-xs text-gray-500">Help our compliance team investigate seller violations.</p>
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
                    <!-- REASON -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700">Reason for Report</label>
                        <select
                            v-model="reportForm.reason"
                            class="mt-1 w-full rounded-xl border border-gray-200 bg-[#F8FAF9] px-3.5 py-2.5 text-xs text-gray-900 focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                        >
                            <option v-for="r in reportReasons" :key="r" :value="r">{{ r }}</option>
                        </select>
                    </div>

                    <!-- DESCRIPTION -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700">Detailed Explanation</label>
                        <textarea
                            v-model="reportForm.description"
                            rows="4"
                            required
                            placeholder="Provide details about the issue with this seller..."
                            class="mt-1 w-full rounded-xl border border-gray-200 bg-[#F8FAF9] px-3.5 py-2.5 text-xs text-gray-900 focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                        ></textarea>
                        <p v-if="reportForm.errors.description" class="mt-1 text-xs text-red-600">{{ reportForm.errors.description }}</p>
                    </div>

                    <!-- EVIDENCE IMAGES -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700">Supporting Screenshots / Evidence (Max 4 images)</label>
                        <input
                            type="file"
                            accept="image/*"
                            multiple
                            class="mt-1 block w-full text-xs text-gray-500 file:mr-3 file:rounded-xl file:border-0 file:bg-[#E8F7F6] file:px-3 file:py-2 file:text-xs file:font-bold file:text-[#087F8C] hover:file:bg-[#D3F0EE]"
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

