<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import AuthModal from '@/Pages/Auth/AuthModal.vue'

const props = defineProps({
    product: {
        type: Object,
        default: null,
    },

    relatedProducts: {
        type: Array,
        default: () => [],
    },
})

const product = computed(() => props.product || {})
const variants = computed(() => product.value.variants || [])
const hasVariants = computed(() => variants.value.length > 0)

/*
|--------------------------------------------------------------------------
| HELPERS
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

const safeRoute = (name, fallback = '#') => {
    try {
        return route(name)
    } catch (e) {
        return fallback
    }
}

/*
|--------------------------------------------------------------------------
| BREADCRUMBS
|--------------------------------------------------------------------------
*/

const breadcrumbs = computed(() => {
    const crumbs = [
        {
            label: 'Home',
            href: '/',
        },
    ]

    if (product.value.category?.name) {
        crumbs.push({
            label: product.value.category.name,
            href: `${safeRoute('guest.products')}?category=${product.value.category.slug ?? ''}`,
        })
    }

    crumbs.push({
        label: product.value.name || 'Product',
        href: null,
    })

    return crumbs
})

/*
|--------------------------------------------------------------------------
| IMAGE GALLERY
|--------------------------------------------------------------------------
*/

const entryToUrl = entry => {
    if (!entry) {
        return null
    }

    if (typeof entry === 'string') {
        return imageUrl(entry)
    }

    return imageUrl(
        entry.image_path ||
        entry.path ||
        entry.url ||
        null
    )
}

const galleryImages = computed(() => {
    const urls = []

    const primary = entryToUrl(product.value.image_path)

    if (primary) {
        urls.push(primary)
    }

    if (Array.isArray(product.value.images)) {
        product.value.images.forEach(entry => {
            const url = entryToUrl(entry)

            if (url && !urls.includes(url)) {
                urls.push(url)
            }
        })
    }

    return urls
})

const activeImage = ref(null)

watch(
    galleryImages,
    images => {
        if (!images.includes(activeImage.value)) {
            activeImage.value = images[0] ?? null
        }
    },
    {
        immediate: true,
    }
)

/*
|--------------------------------------------------------------------------
| VARIANTS
|--------------------------------------------------------------------------
*/

const colors = computed(() =>
    [
        ...new Set(
            variants.value
                .map(v => v.color)
                .filter(Boolean)
        ),
    ]
)

const sizesForColor = color =>
    [
        ...new Set(
            variants.value
                .filter(v => v.color === color)
                .map(v => v.size)
                .filter(Boolean)
        ),
    ]

const selectedColor = ref(null)
const selectedSize = ref(null)

watch(
    colors,
    nextColors => {
        if (!nextColors.includes(selectedColor.value)) {
            selectedColor.value = nextColors[0] ?? null
        }
    },
    {
        immediate: true,
    }
)

watch(
    selectedColor,
    color => {
        const available = sizesForColor(color)

        if (!available.includes(selectedSize.value)) {
            selectedSize.value = available[0] ?? null
        }
    },
    {
        immediate: true,
    }
)

const selectedVariant = computed(() =>
    variants.value.find(
        v =>
            v.color === selectedColor.value &&
            v.size === selectedSize.value
    ) || null
)

const availableStock = computed(() =>
    hasVariants.value
        ? Number(selectedVariant.value?.stock ?? 0)
        : Number(product.value.stock ?? 0)
)

const lowStock = computed(() =>
    availableStock.value > 0 &&
    availableStock.value <= 10
)

/*
|--------------------------------------------------------------------------
| QUANTITY
|--------------------------------------------------------------------------
*/

const quantity = ref(1)

watch(
    availableStock,
    stock => {
        if (stock <= 0) {
            quantity.value = 1
            return
        }

        if (quantity.value > stock) {
            quantity.value = stock
        }
    },
    {
        immediate: true,
    }
)

function increaseQuantity() {
    if (quantity.value < availableStock.value) {
        quantity.value++
    }
}

function decreaseQuantity() {
    if (quantity.value > 1) {
        quantity.value--
    }
}

/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

const page = usePage()
const authModalOpen = ref(false)

function requireLogin() {
    if (!page.props.auth?.user) {
        authModalOpen.value = true
        return true
    }

    return false
}

function addToCart() {
    requireLogin()
}

function buyNow() {
    requireLogin()
}

function messageSeller() {
    requireLogin()
}

/*
|--------------------------------------------------------------------------
| PRICE
|--------------------------------------------------------------------------
*/

const totalPrice = computed(() => {
    return Number(product.value.price || 0) * quantity.value
})

const formattedTotal = computed(() =>
    `₱${totalPrice.value.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`
)

const formattedUnitPrice = computed(() =>
    `₱${Number(product.value.price || 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`
)

const formattedOldPrice = computed(() =>
    `₱${Number(product.value.old_price || 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`
)
</script>

<template>
    <Head :title="product.name || 'Product'" />

    <GuestLayout>
        <main class="mx-auto max-w-7xl px-4 py-5 sm:px-6 sm:py-7 lg:px-8 lg:py-8">

            <!-- ===================================================== -->
            <!-- BREADCRUMBS -->
            <!-- ===================================================== -->

            <nav
                class="mb-5 flex flex-wrap items-center gap-1.5 text-[11px] text-[#64748B]"
                aria-label="Breadcrumb"
            >
                <template
                    v-for="(crumb, index) in breadcrumbs"
                    :key="index"
                >
                    <Link
                        v-if="crumb.href"
                        :href="crumb.href"
                        class="transition hover:text-[#087F8C]"
                    >
                        {{ crumb.label }}
                    </Link>

                    <span
                        v-else
                        class="font-medium text-[#1F2937]"
                    >
                        {{ crumb.label }}
                    </span>

                    <svg
                        v-if="index < breadcrumbs.length - 1"
                        class="h-3 w-3 text-[#CBD5E1]"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="m9 6 6 6-6 6"
                        />
                    </svg>
                </template>
            </nav>

            <!-- ===================================================== -->
            <!-- PRODUCT CARD -->
            <!-- ===================================================== -->

            <div
                class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
            >
                <div class="grid lg:grid-cols-2">

                    <!-- ================================================= -->
                    <!-- IMAGE AREA -->
                    <!-- ================================================= -->

                    <div
                        class="flex min-h-[430px] flex-col items-center justify-center bg-gradient-to-br from-[#E8F7F6] via-white to-[#F8FAF9] p-5 sm:p-8 lg:min-h-[560px]"
                    >

                        <!-- Main image -->
                        <div
                            class="relative flex h-[280px] w-full max-w-[430px] items-center justify-center overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm sm:h-[360px]"
                        >
                            <img
                                v-if="activeImage"
                                :src="activeImage"
                                :alt="product.name"
                                class="h-full w-full object-contain p-4 transition duration-300"
                                loading="eager"
                            />

                            <span
                                v-else
                                class="text-7xl"
                            >
                                🛍️
                            </span>

                            <!-- Sale badge -->
                            <span
                                v-if="product.old_price"
                                class="absolute left-4 top-4 rounded-full bg-[#E85D5D] px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-white shadow-sm"
                            >
                                Sale
                            </span>
                        </div>

                        <!-- Thumbnails -->
                        <div
                            v-if="galleryImages.length > 1"
                            class="mt-4 flex max-w-full flex-wrap justify-center gap-2"
                        >
                            <button
                                v-for="(image, index) in galleryImages"
                                :key="index"
                                type="button"
                                @click="activeImage = image"
                                class="h-14 w-14 overflow-hidden rounded-lg border-2 bg-white transition duration-200 sm:h-16 sm:w-16"
                                :class="
                                    activeImage === image
                                        ? 'border-[#087F8C] ring-2 ring-[#087F8C]/10'
                                        : 'border-transparent opacity-70 hover:border-[#16A6A0]/40 hover:opacity-100'
                                "
                            >
                                <img
                                    :src="image"
                                    :alt="`${product.name} thumbnail ${index + 1}`"
                                    class="h-full w-full object-cover"
                                    loading="lazy"
                                />
                            </button>
                        </div>

                    </div>

                    <!-- ================================================= -->
                    <!-- PRODUCT INFORMATION -->
                    <!-- ================================================= -->

                    <div class="p-5 sm:p-7 lg:p-10">

                        <!-- Category -->
                        <p
                            class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#087F8C]"
                        >
                            {{ product.category?.name || 'Product' }}
                        </p>

                        <!-- Product name -->
                        <h1
                            class="mt-2 text-2xl font-bold tracking-tight text-[#1F2937] sm:text-3xl"
                        >
                            {{ product.name }}
                        </h1>

                        <!-- Seller -->
                        <div
                            class="mt-3 flex flex-wrap items-center gap-1.5 text-xs text-[#64748B]"
                        >
                            <span>Sold by</span>

                            <span class="font-semibold text-[#1F2937]">
                                {{ product.seller?.name || 'Alona Seller' }}
                            </span>
                        </div>

                        <!-- Rating -->
                        <div class="mt-3 flex items-center gap-2">
                            <div class="text-sm tracking-wide text-[#F4B942]">
                                ★★★★★
                            </div>

                            <span class="text-xs text-[#64748B]">
                                {{ product.rating ?? 0 }}
                                ({{ product.reviews_count ?? 0 }} reviews)
                            </span>
                        </div>

                        <!-- Price -->
                        <div class="mt-5 flex flex-wrap items-center gap-2.5">
                            <span
                                class="text-2xl font-bold text-[#1F2937] sm:text-3xl"
                            >
                                {{ formattedUnitPrice }}
                            </span>

                            <span
                                v-if="product.old_price"
                                class="text-sm text-[#94A3B8] line-through"
                            >
                                {{ formattedOldPrice }}
                            </span>

                            <span
                                v-if="product.old_price"
                                class="rounded-full bg-[#FEF2F2] px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-[#E85D5D]"
                            >
                                Sale
                            </span>
                        </div>

                        <!-- Description -->
                        <p
                            v-if="product.description"
                            class="mt-4 text-xs leading-6 text-[#64748B]"
                        >
                            {{ product.description }}
                        </p>

                        <div class="my-6 border-t border-[#E5E7EB]"></div>

                        <!-- ================================================= -->
                        <!-- PRODUCT INFORMATION -->
                        <!-- ================================================= -->

                        <div class="grid gap-3 sm:grid-cols-2">

                            <!-- Stock -->
                            <div
                                class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3"
                            >
                                <p
                                    class="text-[10px] font-semibold uppercase tracking-wide text-[#64748B]"
                                >
                                    Available stock
                                </p>

                                <p
                                    class="mt-1 text-sm font-bold"
                                    :class="
                                        lowStock
                                            ? 'text-[#E85D5D]'
                                            : 'text-[#1F2937]'
                                    "
                                >
                                    {{ availableStock }} items available

                                    <span
                                        v-if="lowStock"
                                        class="font-normal"
                                    >
                                        — almost gone
                                    </span>
                                </p>
                            </div>

                            <!-- Variations -->
                            <div
                                class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3"
                            >
                                <p
                                    class="text-[10px] font-semibold uppercase tracking-wide text-[#64748B]"
                                >
                                    Available variations
                                </p>

                                <p class="mt-1 text-sm font-bold text-[#1F2937]">
                                    {{
                                        hasVariants
                                            ? colors.length + ' color options'
                                            : 'Single option'
                                    }}
                                </p>
                            </div>

                        </div>

                        <!-- ================================================= -->
                        <!-- COLOR -->
                        <!-- ================================================= -->

                        <div
                            v-if="hasVariants && colors.length"
                            class="mt-6"
                        >
                            <p class="text-xs font-semibold text-[#1F2937]">
                                Color
                            </p>

                            <div class="mt-2.5 flex flex-wrap gap-2">
                                <button
                                    v-for="color in colors"
                                    :key="color"
                                    type="button"
                                    @click="selectedColor = color"
                                    class="rounded-lg border px-3 py-1.5 text-xs transition duration-200"
                                    :class="
                                        selectedColor === color
                                            ? 'border-[#087F8C] bg-[#E8F7F6] font-semibold text-[#087F8C]'
                                            : 'border-[#E5E7EB] bg-white text-[#64748B] hover:border-[#16A6A0]/50 hover:text-[#087F8C]'
                                    "
                                >
                                    {{ color }}
                                </button>
                            </div>
                        </div>

                        <!-- ================================================= -->
                        <!-- SIZE -->
                        <!-- ================================================= -->

                        <div
                            v-if="
                                hasVariants &&
                                sizesForColor(selectedColor).length
                            "
                            class="mt-5"
                        >
                            <p class="text-xs font-semibold text-[#1F2937]">
                                Size
                            </p>

                            <div class="mt-2.5 flex flex-wrap gap-2">
                                <button
                                    v-for="size in sizesForColor(selectedColor)"
                                    :key="size"
                                    type="button"
                                    @click="selectedSize = size"
                                    class="rounded-lg border px-3 py-1.5 text-xs transition duration-200"
                                    :class="
                                        selectedSize === size
                                            ? 'border-[#087F8C] bg-[#E8F7F6] font-semibold text-[#087F8C]'
                                            : 'border-[#E5E7EB] bg-white text-[#64748B] hover:border-[#16A6A0]/50 hover:text-[#087F8C]'
                                    "
                                >
                                    {{ size }}
                                </button>
                            </div>
                        </div>

                        <!-- Out of stock -->
                        <p
                            v-if="
                                hasVariants &&
                                selectedVariant &&
                                selectedVariant.stock === 0
                            "
                            class="mt-3 rounded-lg bg-[#FEF2F2] px-3 py-2 text-xs font-semibold text-[#E85D5D]"
                        >
                            This color/size combination is currently out of stock.
                        </p>

                        <!-- ================================================= -->
                        <!-- QUANTITY -->
                        <!-- ================================================= -->

                        <div class="mt-5">

                            <div class="flex items-center justify-between">
                                <p class="text-xs font-semibold text-[#1F2937]">
                                    Quantity
                                </p>

                                <p class="text-[11px] text-[#94A3B8]">
                                    {{ availableStock }} items available
                                </p>
                            </div>

                            <div
                                class="mt-2.5 flex w-fit items-center overflow-hidden rounded-lg border border-[#E5E7EB] bg-white"
                            >
                                <button
                                    type="button"
                                    @click="decreaseQuantity"
                                    :disabled="quantity <= 1"
                                    class="flex h-9 w-9 items-center justify-center text-sm text-[#64748B] transition hover:bg-[#F8FAF9] hover:text-[#087F8C] disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    −
                                </button>

                                <span
                                    class="flex h-9 w-11 items-center justify-center border-x border-[#E5E7EB] text-xs font-semibold text-[#1F2937]"
                                >
                                    {{ quantity }}
                                </span>

                                <button
                                    type="button"
                                    @click="increaseQuantity"
                                    :disabled="
                                        quantity >= availableStock ||
                                        availableStock <= 0
                                    "
                                    class="flex h-9 w-9 items-center justify-center text-sm text-[#64748B] transition hover:bg-[#F8FAF9] hover:text-[#087F8C] disabled:cursor-not-allowed disabled:opacity-40"
                                >
                                    +
                                </button>
                            </div>
                        </div>

                        <!-- ================================================= -->
                        <!-- TOTAL -->
                        <!-- ================================================= -->

                        <div
                            class="mt-5 flex items-center justify-between rounded-xl bg-[#E8F7F6] px-4 py-3.5"
                        >
                            <span class="text-xs font-medium text-[#64748B]">
                                Total
                            </span>

                            <span class="text-lg font-bold text-[#087F8C]">
                                {{ formattedTotal }}
                            </span>
                        </div>

                        <!-- ================================================= -->
                        <!-- ACTION BUTTONS -->
                        <!-- ================================================= -->

                        <div class="mt-5 grid gap-2.5 sm:grid-cols-2">

                            <!-- Add to cart -->
                            <button
                                type="button"
                                @click="addToCart"
                                :disabled="
                                    availableStock <= 0 ||
                                    (hasVariants && !selectedVariant)
                                "
                                class="rounded-xl border border-[#087F8C] bg-white px-4 py-3 text-xs font-semibold text-[#087F8C] transition duration-200 hover:bg-[#E8F7F6] disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                🛒 Add to Cart
                            </button>

                            <!-- Buy now -->
                            <button
                                type="button"
                                @click="buyNow"
                                :disabled="
                                    availableStock <= 0 ||
                                    (hasVariants && !selectedVariant)
                                "
                                class="rounded-xl bg-[#087F8C] px-4 py-3 text-xs font-semibold text-white shadow-sm transition duration-200 hover:bg-[#066C77] disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                Buy Now
                            </button>

                        </div>

                        <!-- ================================================= -->
                        <!-- MESSAGE SELLER -->
                        <!-- ================================================= -->

                        <div
                            v-if="product.id"
                            class="mt-3"
                        >
                            <button
                                type="button"
                                @click="messageSeller"
                                class="w-full rounded-xl border border-[#E5E7EB] bg-white px-4 py-2.5 text-xs font-semibold text-[#1F2937] transition duration-200 hover:border-[#16A6A0]/40 hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                            >
                                Message Seller
                            </button>
                        </div>

                        <!-- ================================================= -->
                        <!-- TRUST -->
                        <!-- ================================================= -->

                        <div
                            class="mt-6 grid grid-cols-3 gap-3 border-t border-[#E5E7EB] pt-5"
                        >
                            <div class="text-center">
                                <div class="text-lg">
                                    🚚
                                </div>

                                <p class="mt-1 text-[10px] font-semibold text-[#1F2937]">
                                    Fast Delivery
                                </p>
                            </div>

                            <div class="text-center">
                                <div class="text-lg">
                                    🔒
                                </div>

                                <p class="mt-1 text-[10px] font-semibold text-[#1F2937]">
                                    Secure Payment
                                </p>
                            </div>

                            <div class="text-center">
                                <div class="text-lg">
                                    ↩️
                                </div>

                                <p class="mt-1 text-[10px] font-semibold text-[#1F2937]">
                                    Easy Returns
                                </p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ===================================================== -->
            <!-- RELATED PRODUCTS -->
            <!-- ===================================================== -->

            <section
                v-if="relatedProducts.length"
                class="mt-10"
            >
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p
                            class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#087F8C]"
                        >
                            More to explore
                        </p>

                        <h2
                            class="mt-1 text-lg font-bold tracking-tight text-[#1F2937]"
                        >
                            You might also like
                        </h2>
                    </div>

                    <Link
                        v-if="product.category?.slug"
                        :href="`${safeRoute('guest.products')}?category=${product.category.slug}`"
                        class="hidden text-xs font-semibold text-[#087F8C] transition hover:text-[#066C77] sm:block"
                    >
                        View more
                    </Link>
                </div>

                <div
                    class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-5"
                >
                    <Link
                        v-for="related in relatedProducts"
                        :key="related.id"
                        :href="route('guest.product', related.id)"
                        class="group block overflow-hidden rounded-xl border border-[#E5E7EB] bg-white transition duration-200 hover:-translate-y-0.5 hover:border-[#16A6A0]/40 hover:shadow-md"
                    >
                        <!-- Image -->
                        <div
                            class="relative flex h-32 items-center justify-center overflow-hidden bg-[#F8FAF9] sm:h-36"
                        >
                            <img
                                v-if="related.image_path"
                                :src="imageUrl(related.image_path)"
                                :alt="related.name"
                                class="h-full w-full object-contain p-2 transition duration-300 group-hover:scale-105"
                                loading="lazy"
                            />

                            <span
                                v-else
                                class="text-3xl"
                            >
                                🛍️
                            </span>
                        </div>

                        <!-- Product info -->
                        <div class="p-3">
                            <p
                                class="text-[9px] font-semibold uppercase tracking-wide text-[#94A3B8]"
                            >
                                {{ related.category?.name || 'Product' }}
                            </p>

                            <h3
                                class="mt-1 line-clamp-1 text-xs font-semibold text-[#1F2937]"
                            >
                                {{ related.name }}
                            </h3>

                            <p
                                class="mt-1.5 text-sm font-bold text-[#087F8C]"
                            >
                                ₱{{
                                    Number(
                                        related.price ?? 0
                                    ).toLocaleString('en-PH', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2,
                                    })
                                }}
                            </p>
                        </div>
                    </Link>
                </div>
            </section>

        </main>

        <!-- ===================================================== -->
        <!-- LOGIN MODAL -->
        <!-- ===================================================== -->

        <AuthModal
            :show="authModalOpen"
            mode="login"
            @close="authModalOpen = false"
        />

    </GuestLayout>
</template>