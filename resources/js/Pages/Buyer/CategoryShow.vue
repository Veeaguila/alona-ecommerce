<script setup>
import Icon from '@/Components/Icon.vue'
import { computed, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'
import GuestLayout from '@/Layouts/GuestLayout.vue'

const props = defineProps({
    category: {
        type: Object,
        required: true,
    },
    stores: {
        type: Array,
        default: () => [],
    },
    products: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
            total: 0,
            current_page: 1,
            last_page: 1,
        }),
    },
    allCategories: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            sort: 'popular',
            search: '',
        }),
    },
})

const page = usePage()
const user = computed(() => page.props.auth?.user || null)
const isGuest = computed(() => !user.value)
const layoutComponent = computed(() => isGuest.value ? GuestLayout : BuyerLayout)

const currentSort = ref(props.filters.sort || 'popular')
const searchQuery = ref(props.filters.search || '')
const storeScrollRef = ref(null)

const scrollStores = direction => {
    if (!storeScrollRef.value) return
    const scrollAmount = 300 * direction
    storeScrollRef.value.scrollBy({ left: scrollAmount, behavior: 'smooth' })
}

const sortTabs = [
    { key: 'popular', label: 'Popular' },
    { key: 'latest', label: 'Latest' },
    { key: 'top_sales', label: 'Top Sales' },
]

const changeSort = sortKey => {
    currentSort.value = sortKey
    applyFilters()
}

const handlePriceSortChange = event => {
    const value = event.target.value
    if (value) {
        currentSort.value = value
        applyFilters()
    }
}

const handleSearch = () => {
    applyFilters()
}

const clearSearch = () => {
    searchQuery.value = ''
    applyFilters()
}

const applyFilters = () => {
    router.get(
        route('buyer.category.show', props.category.slug),
        {
            sort: currentSort.value,
            search: searchQuery.value ? searchQuery.value : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    )
}

const imageUrl = path => {
    if (!path) return null
    if (
        path.startsWith('http://') ||
        path.startsWith('https://') ||
        path.startsWith('/storage/') ||
        path.startsWith('data:')
    ) {
        return path
    }
    return `/storage/${path.replace(/^\/+/, '')}`
}

const formatPrice = price => {
    return Number(price ?? 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}

const getStoreInitials = name => {
    return (name || 'Store').slice(0, 2).toUpperCase()
}

const categoryError = ref(false)
const onCategoryImgError = () => {
    categoryError.value = true
}
</script>

<template>
    <Head :title="`${category.name} - Official Stores & Products`" />

    <component :is="layoutComponent">
        <main class="min-h-[calc(100vh-80px)] w-full min-w-0 overflow-x-hidden bg-[#F8FAF9]">
            <div class="mx-auto w-full w-full px-4 py-5 pb-20 sm:px-6 sm:py-6 lg:px-8 2xl:px-10">

                <!-- ===================================================== -->
                <!-- BREADCRUMBS -->
                <!-- ===================================================== -->
                <nav class="mb-4 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                    <Link :href="route('buyer.dashboard')" class="transition hover:text-[#087F8C]">Home</Link>
                    <span>/</span>
                    <Link :href="route('buyer.categories')" class="transition hover:text-[#087F8C]">Categories</Link>
                    <span>/</span>
                    <span class="font-extrabold text-gray-900">{{ category.name }}</span>
                </nav>

                <!-- ===================================================== -->
                <!-- SECTION 1: OFFICIAL STORES CAROUSEL (SHOPEE MALL STYLE) -->
                <!-- ===================================================== -->
                <section class="rounded-2xl border border-gray-200/90 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                        <div class="flex items-center gap-3">
                            <span class="rounded bg-[#E8F7F6] px-2.5 py-1 text-[11px] font-black uppercase tracking-wider text-[#087F8C]">
                                ALONA MALL
                            </span>
                            <h2 class="text-base font-extrabold uppercase tracking-wide text-gray-900 sm:text-lg">
                                Official Stores in {{ category.name }}
                            </h2>
                        </div>

                        <div class="flex items-center gap-2">
                            <Link
                                :href="route('buyer.products', { category: category.slug })"
                                class="flex items-center gap-1 text-xs font-bold text-[#087F8C] hover:underline"
                            >
                                <span>See All</span>
                                <span>&gt;</span>
                            </Link>

                            <button
                                v-if="stores.length > 4"
                                type="button"
                                class="hidden h-7 w-7 items-center justify-center rounded-full border border-gray-200 text-gray-600 transition hover:bg-gray-100 sm:flex"
                                @click="scrollStores(-1)"
                            >
                                ‹
                            </button>
                            <button
                                v-if="stores.length > 4"
                                type="button"
                                class="hidden h-7 w-7 items-center justify-center rounded-full border border-gray-200 text-gray-600 transition hover:bg-gray-100 sm:flex"
                                @click="scrollStores(1)"
                            >
                                ›
                            </button>
                        </div>
                    </div>

                    <!-- STORES LOGO GRID / CAROUSEL -->
                    <div
                        v-if="stores.length"
                        ref="storeScrollRef"
                        class="mt-4 flex gap-3 overflow-x-auto pb-2 scroll-smooth sm:gap-4 no-scrollbar"
                    >
                        <Link
                            v-for="store in stores"
                            :key="store.id"
                            :href="route('buyer.store.show', store.id)"
                            class="group relative flex min-w-[150px] max-w-[190px] flex-1 flex-col items-center justify-between rounded-xl border border-gray-100 bg-white p-4 text-center transition duration-200 hover:-translate-y-1 hover:border-[#087F8C]/40 hover:shadow-md"
                        >
                            <!-- Store Logo / Emblem -->
                            <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-[#F8FAF9] p-2 ring-1 ring-gray-100 transition group-hover:scale-105">
                                <img
                                    v-if="store.store_logo_path"
                                    :src="imageUrl(store.store_logo_path)"
                                    :alt="store.store_name"
                                    class="h-full w-full object-contain"
                                />
                                <span v-else class="text-xl font-black text-[#087F8C]">
                                    {{ getStoreInitials(store.store_name) }}
                                </span>
                            </div>

                            <!-- Store Name -->
                            <h3 class="mt-3 line-clamp-1 w-full text-xs font-black text-gray-900 group-hover:text-[#087F8C]" :title="store.store_name">
                                {{ store.store_name }}
                            </h3>

                            <!-- Category Count Badge -->
                            <p class="mt-1 text-[11px] font-semibold text-[#087F8C]">
                                {{ store.category_products_count }} {{ store.category_products_count === 1 ? 'product' : 'products' }}
                            </p>

                            <!-- Rating -->
                            <div class="mt-1.5 flex items-center justify-center gap-1 text-[10px] text-gray-500">
                                <span v-if="store.avg_rating" class="text-[#F4B942]">★</span>
                                <span class="font-bold text-gray-700">
                                    {{ store.avg_rating ? store.avg_rating.toFixed(1) : 'New Store' }}
                                </span>
                                <span v-if="store.total_reviews > 0" class="text-gray-400">
                                    ({{ store.total_reviews }})
                                </span>
                            </div>
                        </Link>
                    </div>

                    <div v-else class="p-8 text-center text-xs text-gray-400">
                        No official stores registered in this category yet.
                    </div>
                </section>

                <!-- ===================================================== -->
                <!-- SECTION 2: RECOMMENDATION PRODUCTS WITH SIDEBAR -->
                <!-- ===================================================== -->
                <div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-[240px_1fr]">

                    <!-- LEFT SIDEBAR: ALL CATEGORIES ACCORDION / TREE -->
                    <aside class="hidden rounded-2xl border border-gray-200/90 bg-white p-5 shadow-sm lg:block">
                        <div class="flex items-center gap-2 border-b border-gray-100 pb-3 text-sm font-extrabold text-gray-900">
                            <Icon name="grid" class="h-4 w-4 text-[#087F8C]" />
                            <span>All Categories</span>
                        </div>

                        <ul class="mt-3 space-y-1 text-xs">
                            <li
                                v-for="cat in allCategories"
                                :key="cat.id"
                            >
                                <Link
                                    :href="route('buyer.category.show', cat.slug)"
                                    class="flex items-center justify-between rounded-lg px-2.5 py-2 font-semibold transition"
                                    :class="
                                        cat.id === category.id
                                            ? 'bg-[#E8F7F6] font-black text-[#087F8C]'
                                            : 'text-gray-600 hover:bg-gray-50 hover:text-[#087F8C]'
                                    "
                                >
                                    <span class="truncate">{{ cat.name }}</span>
                                    <span v-if="cat.id === category.id" class="text-sm">›</span>
                                </Link>
                            </li>
                        </ul>
                    </aside>

                    <!-- RIGHT MAIN COLUMN: SORTING TOOLBAR & PRODUCTS -->
                    <section class="min-w-0">

                        <!-- SHOPEE STYLE SORT BAR -->
                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200/90 bg-white p-3 shadow-sm sm:px-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs font-bold text-gray-500 mr-1">Sort by</span>

                                <!-- Popular Tab -->
                                <button
                                    type="button"
                                    class="rounded-lg px-4 py-2 text-xs font-bold transition"
                                    :class="
                                        currentSort === 'popular'
                                            ? 'bg-[#087F8C] text-white shadow-sm'
                                            : 'border border-gray-200 bg-white text-gray-700 hover:bg-gray-50'
                                    "
                                    @click="changeSort('popular')"
                                >
                                    Popular
                                </button>

                                <!-- Latest Tab -->
                                <button
                                    type="button"
                                    class="rounded-lg px-4 py-2 text-xs font-bold transition"
                                    :class="
                                        currentSort === 'latest'
                                            ? 'bg-[#087F8C] text-white shadow-sm'
                                            : 'border border-gray-200 bg-white text-gray-700 hover:bg-gray-50'
                                    "
                                    @click="changeSort('latest')"
                                >
                                    Latest
                                </button>

                                <!-- Top Sales Tab -->
                                <button
                                    type="button"
                                    class="rounded-lg px-4 py-2 text-xs font-bold transition"
                                    :class="
                                        currentSort === 'top_sales'
                                            ? 'bg-[#087F8C] text-white shadow-sm'
                                            : 'border border-gray-200 bg-white text-gray-700 hover:bg-gray-50'
                                    "
                                    @click="changeSort('top_sales')"
                                >
                                    Top Sales
                                </button>

                                <!-- Price Dropdown Filter -->
                                <div class="relative">
                                    <select
                                        :value="['price_low', 'price_high'].includes(currentSort) ? currentSort : ''"
                                        class="rounded-lg border border-gray-200 bg-white py-2 pl-3 pr-8 text-xs font-bold text-gray-700 transition focus:border-[#087F8C] focus:ring-1 focus:ring-[#087F8C]"
                                        :class="
                                            ['price_low', 'price_high'].includes(currentSort)
                                                ? 'border-[#087F8C] text-[#087F8C]'
                                                : ''
                                        "
                                        @change="handlePriceSortChange"
                                    >
                                        <option value="" disabled>Price</option>
                                        <option value="price_low">Price: Low to High</option>
                                        <option value="price_high">Price: High to Low</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Pagination indicator in sort bar (e.g., 1/8 < >) -->
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-bold text-gray-500">
                                    <span class="text-[#087F8C]">{{ products.current_page || 1 }}</span>/{{ products.last_page || 1 }}
                                </span>

                                <div class="flex items-center gap-1">
                                    <Link
                                        :href="products.prev_page_url || '#'"
                                        class="flex h-7 w-7 items-center justify-center rounded border border-gray-200 text-xs font-bold text-gray-600 transition hover:bg-gray-50"
                                        :class="!products.prev_page_url ? 'pointer-events-none opacity-40' : ''"
                                        preserve-scroll
                                    >
                                        ‹
                                    </Link>
                                    <Link
                                        :href="products.next_page_url || '#'"
                                        class="flex h-7 w-7 items-center justify-center rounded border border-gray-200 text-xs font-bold text-gray-600 transition hover:bg-gray-50"
                                        :class="!products.next_page_url ? 'pointer-events-none opacity-40' : ''"
                                        preserve-scroll
                                    >
                                        ›
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- SEARCH BOX IN CATEGORY -->
                        <div class="mt-3 flex items-center justify-between gap-3">
                            <p class="text-xs font-bold text-gray-500">
                                Recommended Products in <span class="text-gray-900 font-extrabold">{{ category.name }}</span>
                            </p>

                            <div class="relative w-64">
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search in this category..."
                                    class="w-full rounded-xl border border-gray-200 bg-white py-1.5 pl-8 pr-7 text-xs text-gray-900 placeholder:text-gray-400 focus:border-[#087F8C] focus:ring-2 focus:ring-[#E8F7F6]"
                                    @keydown.enter="handleSearch"
                                />
                                <div class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400">
                                    <Icon name="search" class="h-3.5 w-3.5" />
                                </div>
                                <button
                                    v-if="searchQuery"
                                    type="button"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-gray-400 hover:text-gray-600"
                                    @click="clearSearch"
                                >
                                    ✕
                                </button>
                            </div>
                        </div>

                        <!-- PRODUCTS GRID (SHOPEE STYLE CARDS) -->
                        <div
                            v-if="products.data?.length"
                            class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-5"
                        >
                            <Link
                                v-for="product in products.data"
                                :key="product.id"
                                :href="isGuest ? route('guest.product', product.id) : route('buyer.product', product.id)"
                                class="group relative flex flex-col justify-between overflow-hidden rounded-xl border border-gray-200/80 bg-white shadow-sm transition duration-200 hover:-translate-y-1 hover:border-[#087F8C]/40 hover:shadow-md"
                            >
                                <!-- Product Image Container -->
                                <div class="relative aspect-square overflow-hidden bg-[#F8FAF9]">
                                    <img
                                        v-if="product.image_path"
                                        :src="imageUrl(product.image_path)"
                                        :alt="product.name"
                                        class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                        loading="lazy"
                                    />
                                    <div
                                        v-else
                                        class="flex h-full items-center justify-center text-4xl"
                                    >
                                        <Icon name="bag" class="h-10 w-10 text-[#087F8C]/50" />
                                    </div>

                                    <!-- Discount Percent Badge (Top Right) -->
                                    <span
                                        v-if="product.old_price && Number(product.old_price) > Number(product.price)"
                                        class="absolute right-0 top-0 bg-[#FFF1F0] px-2 py-0.5 text-[10px] font-extrabold text-[#E85D5D]"
                                    >
                                        -{{ Math.round(((Number(product.old_price) - Number(product.price)) / Number(product.old_price)) * 100) }}%
                                    </span>

                                    <!-- Preferred Badge (Top Left) -->
                                    <span
                                        v-if="product.is_featured"
                                        class="absolute left-1.5 top-1.5 rounded bg-[#087F8C] px-1.5 py-0.5 text-[8px] font-black uppercase text-white shadow-sm"
                                    >
                                        Preferred
                                    </span>
                                </div>

                                <!-- Product Info -->
                                <div class="flex flex-1 flex-col justify-between p-3">
                                    <div>
                                        <h3
                                            class="line-clamp-2 text-xs font-semibold leading-relaxed text-gray-900 group-hover:text-[#087F8C]"
                                            :title="product.name"
                                        >
                                            {{ product.name }}
                                        </h3>
                                    </div>

                                    <div class="mt-3">
                                        <!-- Price -->
                                        <div class="flex items-baseline gap-1.5">
                                            <span class="text-sm font-black text-[#087F8C]">
                                                ₱{{ formatPrice(product.price) }}
                                            </span>
                                            <span
                                                v-if="product.old_price"
                                                class="text-[10px] text-gray-400 line-through"
                                            >
                                                ₱{{ formatPrice(product.old_price) }}
                                            </span>
                                        </div>

                                        <!-- Ratings & Sold -->
                                        <div class="mt-1.5 flex items-center justify-between text-[10px] text-gray-400">
                                            <div class="flex items-center gap-0.5">
                                                <span v-if="Number(product.rating ?? 0) > 0" class="text-[#F4B942]">★</span>
                                                <span class="font-bold text-gray-700">
                                                    {{ Number(product.rating ?? 0) > 0 ? Number(product.rating).toFixed(1) : 'New' }}
                                                </span>
                                            </div>

                                            <span v-if="Number(product.sold_count ?? 0) > 0">
                                                {{ product.sold_count }} sold
                                            </span>
                                            <span v-else-if="Number(product.reviews_count ?? 0) > 0">
                                                {{ Number(product.reviews_count) }} reviews
                                            </span>
                                            <span v-else class="text-gray-400">
                                                0 sold
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </Link>
                        </div>

                        <!-- EMPTY PRODUCTS STATE -->
                        <div
                            v-else
                            class="mt-6 rounded-2xl border border-dashed border-gray-200 bg-white p-12 text-center"
                        >
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-[#E8F7F6] text-[#087F8C]">
                                <Icon name="package" class="h-6 w-6" />
                            </div>
                            <h3 class="mt-3 text-sm font-extrabold text-gray-900">
                                No products found in {{ category.name }}
                            </h3>
                            <p class="mt-1 text-xs text-gray-500">
                                <span v-if="searchQuery">Try adjusting your search keywords or clear the filter.</span>
                                <span v-else>Check back soon as sellers add new items to this category.</span>
                            </p>
                            <button
                                v-if="searchQuery"
                                type="button"
                                class="mt-4 rounded-xl bg-[#087F8C] px-4 py-2 text-xs font-bold text-white hover:bg-[#066974]"
                                @click="clearSearch"
                            >
                                Clear Search
                            </button>
                        </div>

                        <!-- BOTTOM PAGINATION -->
                        <div
                            v-if="products.links?.length > 3"
                            class="mt-10 flex w-full flex-wrap items-center justify-center gap-1.5"
                        >
                            <Link
                                v-for="(link, index) in products.links"
                                :key="index"
                                :href="link.url || '#'"
                                v-html="link.label"
                                class="rounded-lg border px-3 py-1.5 text-xs font-bold transition"
                                :class="[
                                    link.active
                                        ? 'border-[#087F8C] bg-[#087F8C] text-white shadow-sm'
                                        : 'border-gray-200 bg-white text-gray-700 hover:border-[#087F8C]/40 hover:text-[#087F8C]',
                                    !link.url ? 'pointer-events-none opacity-40' : '',
                                ]"
                                preserve-scroll
                                preserve-state
                            />
                        </div>

                    </section>

                </div>

            </div>
        </main>
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
