<script setup>
import Icon from '@/Components/Icon.vue'
import { ref, onMounted, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'

const props = defineProps({
    products: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
            total: 0,
        }),
    },

    categories: {
        type: Array,
        default: () => [],
    },

    filters: {
        type: Object,
        default: () => ({
            search: '',
            category: 'all',
            max_price: null,
            sort: 'featured',
        }),
    },
})

/*
|--------------------------------------------------------------------------
| IMAGE URL
|--------------------------------------------------------------------------
*/

const imageUrl = path => {
    if (!path) {
        return null
    }

    if (
        path.startsWith('http://') ||
        path.startsWith('https://') ||
        path.startsWith('/storage/')
    ) {
        return path
    }

    return `/storage/${path.replace(/^\/+/, '')}`
}

/*
|--------------------------------------------------------------------------
| FORMAT PRICE
|--------------------------------------------------------------------------
*/

const formatPrice = price => {
    return Number(price ?? 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}

const getDiscountPercent = product => {
    const explicit = Number(product?.discount_percentage ?? product?.discount_percent ?? 0)
    if (explicit > 0) return Math.round(explicit)
    const badgeMatch = String(product?.discount_badge ?? '').match(/(\d+)\s*%/)
    if (badgeMatch) return Number(badgeMatch[1])
    const price = Number(product?.price ?? 0)
    const oldPrice = Number(product?.old_price ?? 0)
    if (oldPrice > price && oldPrice > 0) {
        return Math.round(((oldPrice - price) / oldPrice) * 100)
    }
    return 0
}

/*
|--------------------------------------------------------------------------
| CATEGORY FILTER
|--------------------------------------------------------------------------
*/

const categoryBar = ref(null)
const revealSelectedCategory = () => {
    const bar = categoryBar.value
    const selected = bar?.querySelector('[aria-pressed="true"]')
    if (!selected) return
    const buttonRect = selected.getBoundingClientRect()
    const barRect = bar.getBoundingClientRect()
    if (buttonRect.left < barRect.left || buttonRect.right > barRect.right) {
        bar.scrollTo({ left: bar.scrollLeft + buttonRect.left - barRect.left - (bar.clientWidth - buttonRect.width) / 2 })
    }
}
onMounted(revealSelectedCategory)
watch(() => props.filters.category, revealSelectedCategory, { flush: 'post' })

const changeCategory = category => {
    router.get(
        route('guest.products'),
        {
            search: props.filters.search || undefined,
            category: category || 'all',
            max_price: props.filters.max_price ?? undefined,
            sort: props.filters.sort || 'featured',
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    )
}
</script>

<template>
    <Head title="Products" />

    <GuestLayout>
        <!-- ============================================================= -->
        <!-- FULL WIDTH PRODUCTS PAGE -->
        <!-- ============================================================= -->

        <main
            class="w-full min-w-0 overflow-x-hidden bg-[#E8F7F6]"
        >
            <div
                class="mx-auto w-full min-w-0 px-4 py-5 sm:px-6 sm:py-6 lg:px-8 2xl:px-10"
            >
                <!-- ===================================================== -->
                <!-- CATEGORY FILTER -->
                <!-- ===================================================== -->

                <div
                    ref="categoryBar"
                    role="group"
                    aria-label="Filter products by category"
                    class="flex w-full min-w-0 items-center gap-2 overflow-x-auto pb-1"
                >
                    <!-- ALL -->
                    <button
                        type="button"
                        class="shrink-0 rounded-lg border px-3 py-2 text-[10px] font-semibold transition sm:text-[11px]"
                        :class="
                            (props.filters.category || 'all') === 'all'
                                ? 'border-[#087F8C] bg-[#087F8C] text-white'
                                : 'border-[#E5E7EB] bg-white text-[#64748B] hover:border-[#087F8C]/40 hover:text-[#087F8C]'
                        "
                        :aria-pressed="(props.filters.category || 'all') === 'all'"
                        @click="changeCategory('all')"
                    >
                        All
                    </button>

                    <!-- DATABASE CATEGORIES -->
                    <button
                        v-for="category in props.categories"
                        :key="category.slug"
                        type="button"
                        class="shrink-0 rounded-lg border px-3 py-2 text-[10px] font-semibold transition sm:text-[11px]"
                        :class="
                            props.filters.category === category.slug
                                ? 'border-[#087F8C] bg-[#087F8C] text-white'
                                : 'border-[#E5E7EB] bg-white text-[#64748B] hover:border-[#087F8C]/40 hover:text-[#087F8C]'
                        "
                        :aria-pressed="props.filters.category === category.slug"
                        @click="changeCategory(category.slug)"
                    >
                        {{ category.name }}
                    </button>
                </div>

                <!-- ===================================================== -->
                <!-- PRODUCTS -->
                <!-- ===================================================== -->

                <div
                    v-if="products.data?.length"
                    class="mt-6 grid w-full min-w-0 grid-cols-2 gap-3 sm:grid-cols-2 sm:gap-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6"
                >
                    <Link
                        v-for="product in products.data"
                        :key="product.id"
                        :href="route('guest.product', product.id)"
                        class="group flex min-w-0 flex-col justify-between overflow-hidden rounded-2xl border border-[#B2DFDB] bg-[linear-gradient(180deg,#FFFFFF_50%,#E8F7F6_100%)] shadow-[0_2px_6px_rgba(6,90,99,0.07)] transition duration-200 hover:-translate-y-0.5 hover:border-[#087F8C]/40 hover:shadow-md"
                    >
                        <!-- ================================================= -->
                        <!-- IMAGE -->
                        <!-- ================================================= -->

                        <div
                            class="relative flex aspect-square w-full items-center justify-center overflow-hidden rounded-t-[15px] bg-white p-2.5 sm:p-3"
                        >
                            <img
                                v-if="product.image_path"
                                :src="imageUrl(product.image_path)"
                                :alt="product.name"
                                class="h-full w-full object-contain transition duration-300 group-hover:scale-[1.03]"
                                loading="lazy"
                            />

                            <span
                                v-else
                                class="text-3xl sm:text-4xl"
                            ><Icon name="bag" class="h-4 w-4" /></span>

                            <span
                                v-if="getDiscountPercent(product) > 0"
                                class="absolute left-2.5 top-2.5 rounded-full border border-[#E85D5D]/30 bg-[#FFF1F1] px-2.5 py-0.5 text-[10px] font-extrabold text-[#E85D5D]"
                            >
                                {{ getDiscountPercent(product) }}% OFF
                            </span>
                            <span
                                v-else-if="product.discount_badge"
                                class="absolute left-2.5 top-2.5 rounded-full border border-[#E85D5D]/30 bg-[#FFF1F1] px-2.5 py-0.5 text-[10px] font-extrabold text-[#E85D5D]"
                            >
                                {{ product.discount_badge }}
                            </span>
                        </div>

                        <!-- ================================================= -->
                        <!-- PRODUCT INFO -->
                        <!-- ================================================= -->

                        <div class="flex min-w-0 flex-1 flex-col justify-between p-3 sm:p-3.5">
                            <div>
                                <!-- CATEGORY -->
                                <p
                                    class="truncate text-[9.5px] font-bold uppercase tracking-[0.05em] text-[#64748B]"
                                >
                                    {{ product.category?.name || 'Uncategorized' }}
                                </p>

                                <!-- PRODUCT NAME -->
                                <h2
                                    class="mt-1 line-clamp-2 text-[13px] font-semibold leading-snug text-[#1F2937] transition group-hover:text-[#087F8C]"
                                    :title="product.name"
                                >
                                    {{ product.name }}
                                </h2>
                            </div>

                            <div class="mt-2">
                                <!-- RATING -->
                                <div
                                    class="flex min-w-0 items-center gap-1"
                                >
                                    <span class="text-xs leading-none text-[#F4B942]">★</span>

                                    <span
                                        class="text-[11px] font-bold text-[#1F2937]"
                                    >
                                        {{ Number(product.rating ?? 0) > 0 ? Number(product.rating ?? 0).toFixed(1) : 'New' }}
                                    </span>

                                    <span
                                        v-if="Number(product.reviews_count ?? 0) > 0"
                                        class="truncate text-[10px] text-[#64748B]"
                                    >
                                        ({{ Number(product.reviews_count ?? 0) }})
                                    </span>
                                </div>

                                <!-- PRICE -->
                                <div
                                    class="mt-1 flex min-w-0 flex-wrap items-center gap-1.5"
                                >
                                    <span
                                        class="truncate text-[15px] font-extrabold text-[#087F8C]"
                                    >
                                        {{ product.price_range || `₱${formatPrice(product.price)}` }}
                                    </span>

                                    <span
                                        v-if="getDiscountPercent(product) > 0"
                                        class="rounded bg-[#E85D5D]/12 px-1.5 py-0.5 text-[10px] font-extrabold text-[#E85D5D]"
                                    >
                                        -{{ getDiscountPercent(product) }}%
                                    </span>
                                    <span
                                        v-else-if="product.old_price"
                                        class="truncate text-[10px] text-[#64748B] line-through"
                                    >
                                        ₱{{ formatPrice(product.old_price) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </Link>
                </div>

                <!-- ===================================================== -->
                <!-- EMPTY STATE -->
                <!-- ===================================================== -->

                <div
                    v-else
                    class="mt-6 w-full rounded-xl border border-dashed border-[#E5E7EB] bg-white px-5 py-14 text-center sm:px-6"
                >
                    <div
                        class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-[#E8F7F6] text-[#087F8C]"
                    >
                        <svg
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3 7h18M5 7l1 13h12l1-13M9 7V5a3 3 0 0 1 6 0v2"
                            />
                        </svg>
                    </div>

                    <p
                        class="mt-3 text-[12px] font-semibold text-[#1F2937]"
                    >
                        No products available.
                    </p>

                    <p
                        class="mt-1 text-[11px] text-[#64748B]"
                    >
                        Products from sellers will appear here.
                    </p>
                </div>

                <!-- ===================================================== -->
                <!-- PAGINATION -->
                <!-- ===================================================== -->

                <div
                    v-if="products.links?.length > 3"
                    class="mt-8 flex w-full flex-wrap items-center justify-center gap-1.5 overflow-hidden"
                >
                    <Link
                        v-for="(link, index) in products.links"
                        :key="index"
                        :href="link.url || '#'"
                        v-html="link.label"
                        class="rounded-lg border px-2.5 py-1.5 text-[10px] font-medium transition sm:px-3 sm:text-[11px]"
                        :class="[
                            link.active
                                ? 'border-[#087F8C] bg-[#087F8C] text-white'
                                : 'border-[#E5E7EB] bg-white text-slate-700 hover:border-[#087F8C]/40 hover:text-[#087F8C]',

                            !link.url
                                ? 'pointer-events-none opacity-40'
                                : '',
                        ]"
                        preserve-scroll
                        preserve-state
                    />
                </div>
            </div>
        </main>
    </GuestLayout>
</template>
