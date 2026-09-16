<!-- Buyer product detail page styled to match the Guest product detail page. -->
<script setup>
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'
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

    sellerStats: {
        type: Object,
        default: () => ({
            total_products: 0,
            avg_rating: 0,
        }),
    },
})

const product = computed(() => props.product || {})

/*
|--------------------------------------------------------------------------
| IMAGE HELPER
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
| STORE
|--------------------------------------------------------------------------
*/

const seller = computed(() => {
    return product.value.seller || {}
})

const storeName = computed(() => {
    return (
        seller.value.store_name ||
        seller.value.name ||
        'Alona Seller'
    )
})

const storeDescription = computed(() => {
    return seller.value.store_description || ''
})

const storeLogo = computed(() => {
    return imageUrl(
        seller.value.store_logo_path ||
        seller.value.store_logo ||
        null
    )
})

const storeRating = computed(() => {
    const rating = Number(
        props.sellerStats?.avg_rating ?? 0
    )

    return Number.isFinite(rating)
        ? rating
        : 0
})

const storeProductsCount = computed(() => {
    const count = Number(
        props.sellerStats?.total_products ?? 0
    )

    return Number.isFinite(count)
        ? count
        : 0
})

const storeInitial = computed(() => {
    return (
        storeName.value
            ?.charAt(0)
            ?.toUpperCase() || 'A'
    )
})

/*
|--------------------------------------------------------------------------
| REVIEWS
|--------------------------------------------------------------------------
*/

const reviews = computed(() => {
    return Array.isArray(product.value.reviews)
        ? product.value.reviews
        : []
})

const averageRating = computed(() => {
    const rating = Number(product.value.rating ?? 0)

    return Number.isFinite(rating)
        ? rating
        : 0
})

const reviewsCount = computed(() => {
    const count = Number(product.value.reviews_count ?? 0)

    return Number.isFinite(count)
        ? count
        : reviews.value.length
})

const reviewStars = rating => {
    const value = Math.round(Number(rating ?? 0))

    return Array.from(
        { length: 5 },
        (_, index) => index < value
    )
}

const reviewDate = date => {
    if (!date) {
        return ''
    }

    const parsed = new Date(date)

    if (Number.isNaN(parsed.getTime())) {
        return ''
    }

    return parsed.toLocaleDateString(
        'en-PH',
        {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        }
    )
}

/*
|--------------------------------------------------------------------------
| ROUTES
|--------------------------------------------------------------------------
*/

const safeRoute = (name, params = undefined, fallback = '#') => {
    try {
        if (params !== undefined) {
            return route(name, params)
        }

        return route(name)
    } catch (e) {
        return fallback
    }
}

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

    const primary = entryToUrl(
        product.value.image_path
    )

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

const activeImage = ref(
    galleryImages.value[0] ?? null
)

watch(
    galleryImages,
    images => {
        if (!images.includes(activeImage.value)) {
            activeImage.value =
                images[0] ?? null
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

const variants = computed(() =>
    Array.isArray(product.value.variants)
        ? product.value.variants
        : []
)

const hasVariants = computed(() =>
    variants.value.length > 0
)

const colors = computed(() =>
    [
        ...new Set(
            variants.value
                .map(variant => variant.color)
                .filter(Boolean)
        ),
    ]
)

const sizesForColor = color =>
    [
        ...new Set(
            variants.value
                .filter(
                    variant =>
                        variant.color === color
                )
                .map(variant => variant.size)
                .filter(Boolean)
        ),
    ]

const selectedColor = ref(
    colors.value[0] ?? null
)

const selectedSize = ref(
    sizesForColor(
        selectedColor.value
    )[0] ?? null
)

watch(
    colors,
    nextColors => {
        if (
            !nextColors.includes(
                selectedColor.value
            )
        ) {
            selectedColor.value =
                nextColors[0] ?? null
        }
    },
    {
        immediate: true,
    }
)

watch(
    selectedColor,
    color => {
        const available =
            sizesForColor(color)

        if (
            !available.includes(
                selectedSize.value
            )
        ) {
            selectedSize.value =
                available[0] ?? null
        }
    }
)

/*
|--------------------------------------------------------------------------
| SELECTED VARIANT
|--------------------------------------------------------------------------
*/

const selectedVariant = computed(() => {
    if (!hasVariants.value) {
        return null
    }

    return (
        variants.value.find(
            variant =>
                variant.color ===
                    selectedColor.value &&
                variant.size ===
                    selectedSize.value
        ) || null
    )
})

/*
|--------------------------------------------------------------------------
| STOCK
|--------------------------------------------------------------------------
*/

const availableStock = computed(() => {
    if (hasVariants.value) {
        return Number(
            selectedVariant.value?.stock ?? 0
        )
    }

    return Number(
        product.value.stock ?? 0
    )
})

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

        if (quantity.value < 1) {
            quantity.value = 1
        }
    },
    {
        immediate: true,
    }
)

function increaseQuantity() {
    if (
        quantity.value <
        availableStock.value
    ) {
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
| PRICE
|--------------------------------------------------------------------------
*/

const unitPrice = computed(() =>
    Number(product.value.price || 0)
)

const formattedUnitPrice = computed(() =>
    `₱${unitPrice.value.toLocaleString(
        'en-PH',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }
    )}`
)

const formattedOldPrice = computed(() =>
    Number(
        product.value.old_price || 0
    ).toLocaleString(
        'en-PH',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }
    )
)

const totalPrice = computed(() =>
    `₱${(
        unitPrice.value *
        quantity.value
    ).toLocaleString(
        'en-PH',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }
    )}`
)

/*
|--------------------------------------------------------------------------
| PURCHASE AVAILABILITY
|--------------------------------------------------------------------------
*/

const canPurchase = computed(() =>
    Boolean(product.value.id) &&
    availableStock.value > 0 &&
    (
        !hasVariants.value ||
        Boolean(selectedVariant.value)
    )
)

/*
|--------------------------------------------------------------------------
| AUTHENTICATION
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

/*
|--------------------------------------------------------------------------
| CART FORM
|--------------------------------------------------------------------------
*/

const cartForm = useForm({
    quantity: 1,
    product_variant_id: null,
})

function prepareCartForm() {
    cartForm.quantity =
        quantity.value

    cartForm.product_variant_id =
        selectedVariant.value?.id ?? null
}

/*
|--------------------------------------------------------------------------
| ADD TO CART
|--------------------------------------------------------------------------
*/

function addToCart() {
    if (requireLogin()) {
        return
    }

    if (!canPurchase.value) {
        return
    }

    prepareCartForm()

    cartForm.post(
        safeRoute(
            'buyer.cart.store',
            product.value.id
        ),
        {
            preserveScroll: true,
        }
    )
}

/*
|--------------------------------------------------------------------------
| BUY NOW
|--------------------------------------------------------------------------
*/

function buyNow() {
    if (requireLogin()) {
        return
    }

    if (!canPurchase.value) {
        return
    }

    prepareCartForm()

    cartForm.post(
        safeRoute(
            'buyer.cart.store',
            product.value.id
        ),
        {
            preserveScroll: true,

            onSuccess: () => {
                router.visit(
                    safeRoute(
                        'buyer.cart'
                    )
                )
            },
        }
    )
}

/*
|--------------------------------------------------------------------------
| MESSAGE SELLER
|--------------------------------------------------------------------------
*/

function messageSeller() {
    if (requireLogin()) {
        return
    }

    if (!product.value.id) {
        return
    }

    router.post(
        safeRoute(
            'buyer.conversations.start',
            product.value.id
        ),
        {},
        {
            preserveScroll: true,
        }
    )
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
            href: safeRoute(
                'buyer.dashboard',
                undefined,
                '/'
            ),
        },
    ]

    if (product.value.category?.name) {
        const categorySlug =
            product.value.category?.slug

        let categoryHref =
            safeRoute(
                'buyer.products',
                undefined,
                '#'
            )

        if (categoryHref !== '#') {
            categoryHref =
                categorySlug
                    ? `${categoryHref}?category=${encodeURIComponent(categorySlug)}`
                    : categoryHref
        }

        crumbs.push({
            label:
                product.value.category.name,
            href: categoryHref,
        })
    }

    crumbs.push({
        label:
            product.value.name ||
            'Product',
        href: null,
    })

    return crumbs
})
</script>

<template>
    <Head
        :title="
            product.name ||
            'Product Details'
        "
    />

    <BuyerLayout>
        <main
            class="min-h-screen bg-[#F8FAF9]"
        >
            <div
                class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8"
            >

                <!-- ===================================================== -->
                <!-- BREADCRUMBS -->
                <!-- ===================================================== -->

                <nav
                    class="mb-4 flex flex-wrap items-center gap-1.5 text-[11px] text-[#64748B]"
                    aria-label="Breadcrumb"
                >
                    <template
                        v-for="(
                            crumb, index
                        ) in breadcrumbs"
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
                            v-if="
                                index <
                                breadcrumbs.length - 1
                            "
                            class="h-3 w-3 text-[#E5E7EB]"
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
                    <div
                        class="grid lg:grid-cols-2"
                    >

                        <!-- ================================================= -->
                        <!-- IMAGE GALLERY -->
                        <!-- ================================================= -->

                        <div
                            class="flex flex-col items-center justify-center gap-4 bg-[#E8F7F6] p-8"
                        >

                            <!-- MAIN IMAGE -->

                            <div
                                class="flex h-56 w-56 items-center justify-center overflow-hidden rounded-2xl bg-white shadow-md sm:h-64 sm:w-64"
                            >
                                <img
                                    v-if="activeImage"
                                    :src="activeImage"
                                    :alt="
                                        product.name ||
                                        'Product image'
                                    "
                                    class="h-full w-full object-cover"
                                    loading="eager"
                                />

                                <div
                                    v-else
                                    class="flex h-full w-full items-center justify-center text-[#087F8C]"
                                >
                                    <svg
                                        class="h-16 w-16"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M3 7.5A2.5 2.5 0 0 1 5.5 5h13A2.5 2.5 0 0 1 21 7.5v9a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 16.5v-9Z"
                                        />
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="m3.5 15 4.2-4.2a1.5 1.5 0 0 1 2.1 0l2.2 2.2 1.7-1.7a1.5 1.5 0 0 1 2.1 0l4.2 4.2"
                                        />
                                        <circle
                                            cx="8.5"
                                            cy="9"
                                            r="1.25"
                                        />
                                    </svg>
                                </div>
                            </div>

                            <!-- THUMBNAILS -->

                            <div
                                v-if="
                                    galleryImages.length >
                                    1
                                "
                                class="flex flex-wrap justify-center gap-2"
                            >
                                <button
                                    v-for="(
                                        image, index
                                    ) in galleryImages"
                                    :key="index"
                                    type="button"
                                    @click="
                                        activeImage =
                                            image
                                    "
                                    class="h-14 w-14 overflow-hidden rounded-lg border-2 bg-white transition"
                                    :class="
                                        activeImage ===
                                        image
                                            ? 'border-[#087F8C]'
                                            : 'border-transparent opacity-70 hover:opacity-100'
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

                        <div
                            class="p-6 lg:p-10"
                        >

                            <!-- CATEGORY -->

                            <p
                                class="text-[11px] font-semibold uppercase tracking-wide text-[#087F8C]"
                            >
                                {{
                                    product.category?.name ||
                                    'Product'
                                }}
                            </p>

                            <!-- PRODUCT NAME -->

                            <h1
                                class="mt-1.5 text-xl font-bold tracking-tight text-[#1F2937] sm:text-2xl"
                            >
                                {{ product.name }}
                            </h1>

                            <!-- ================================================= -->
                            <!-- STORE -->
                            <!-- ================================================= -->

                            <div
                                v-if="product.seller?.id"
                                class="mt-4 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-[#F8FAF9]"
                            >
                                <div class="p-4">
                                    <div class="flex items-start gap-3">

                                        <!-- STORE LOGO -->

                                        <div
                                            class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-[#E5E7EB] bg-[#E8F7F6] text-[#087F8C]"
                                        >
                                            <img
                                                v-if="storeLogo"
                                                :src="storeLogo"
                                                :alt="`${storeName} logo`"
                                                class="h-full w-full object-cover"
                                            />

                                            <span
                                                v-else
                                                class="text-base font-bold"
                                            >
                                                {{ storeInitial }}
                                            </span>
                                        </div>

                                        <!-- STORE INFORMATION -->

                                        <div class="min-w-0 flex-1">
                                            <p
                                                class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#087F8C]"
                                            >
                                                Store
                                            </p>

                                            <Link
                                                :href="
                                                    safeRoute(
                                                        'buyer.store.show',
                                                        product.seller.id
                                                    )
                                                "
                                                class="mt-0.5 block truncate text-sm font-bold text-[#1F2937] transition hover:text-[#087F8C]"
                                            >
                                                {{ storeName }}
                                            </Link>

                                            <div
                                                class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1"
                                            >
                                                <span
                                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#1F2937]"
                                                >
                                                    <span
                                                        class="text-[#F4B942]"
                                                    >
                                                        ★
                                                    </span>

                                                    {{
                                                        storeRating.toFixed(
                                                            1
                                                        )
                                                    }}
                                                </span>

                                                <span
                                                    class="h-3 w-px bg-[#E5E7EB]"
                                                ></span>

                                                <span
                                                    class="text-[11px] text-[#64748B]"
                                                >
                                                    {{
                                                        storeProductsCount
                                                    }}
                                                    products
                                                </span>
                                            </div>
                                        </div>

                                        <!-- VISIT STORE -->

                                        <Link
                                            :href="
                                                safeRoute(
                                                    'buyer.store.show',
                                                    product.seller.id
                                                )
                                            "
                                            class="hidden shrink-0 items-center gap-1.5 rounded-lg border border-[#087F8C] bg-white px-3 py-2 text-[10px] font-bold text-[#087F8C] transition hover:bg-[#E8F7F6] sm:inline-flex"
                                        >
                                            Visit Store

                                            <svg
                                                class="h-3.5 w-3.5"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path
                                                    d="M5 12h14"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                />

                                                <path
                                                    d="m13 6 6 6-6 6"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                />
                                            </svg>
                                        </Link>
                                    </div>

                                    <!-- STORE DESCRIPTION -->

                                    <p
                                        v-if="storeDescription"
                                        class="mt-3 line-clamp-2 text-[11px] leading-5 text-[#64748B]"
                                    >
                                        {{ storeDescription }}
                                    </p>

                                    <!-- MOBILE VISIT STORE -->

                                    <Link
                                        :href="
                                            safeRoute(
                                                'buyer.store.show',
                                                product.seller.id
                                            )
                                        "
                                        class="mt-3 flex items-center justify-center gap-1.5 rounded-lg border border-[#E5E7EB] bg-white px-3 py-2 text-[10px] font-bold text-[#087F8C] transition hover:border-[#087F8C] hover:bg-[#E8F7F6] sm:hidden"
                                    >
                                        Visit Store

                                        <svg
                                            class="h-3.5 w-3.5"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <path
                                                d="M5 12h14"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            />

                                            <path
                                                d="m13 6 6 6-6 6"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            />
                                        </svg>
                                    </Link>
                                </div>
                            </div>

                            <!-- PRICE -->

                            <div
                                class="mt-5 flex flex-wrap items-center gap-2.5"
                            >
                                <span
                                    class="text-2xl font-bold text-[#1F2937]"
                                >
                                    {{
                                        formattedUnitPrice
                                    }}
                                </span>

                                <span
                                    v-if="
                                        product.old_price
                                    "
                                    class="text-sm text-[#64748B] line-through"
                                >
                                    ₱{{
                                        formattedOldPrice
                                    }}
                                </span>

                                <span
                                    v-if="
                                        product.old_price
                                    "
                                    class="rounded-full bg-[#FFF1F1] px-2.5 py-1 text-[10px] font-bold text-[#E85D5D]"
                                >
                                    SALE
                                </span>
                            </div>

                            <!-- DESCRIPTION -->

                            <p
                                class="mt-4 text-xs leading-6 text-[#64748B]"
                            >
                                {{
                                    product.description ||
                                    'No product description available.'
                                }}
                            </p>

                            <div
                                class="my-6 border-t border-[#E5E7EB]"
                            ></div>

                            <!-- ================================================= -->
                            <!-- PRODUCT INFORMATION -->
                            <!-- ================================================= -->

                            <div
                                class="grid gap-3 sm:grid-cols-2"
                            >

                                <!-- STOCK -->

                                <div
                                    class="rounded-xl bg-[#F8FAF9] px-4 py-2.5"
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
                                        {{
                                            availableStock
                                        }}
                                        items available

                                        <span
                                            v-if="
                                                lowStock
                                            "
                                            class="font-normal"
                                        >
                                            — almost gone
                                        </span>
                                    </p>
                                </div>

                                <!-- VARIATIONS -->

                                <div
                                    class="rounded-xl bg-[#F8FAF9] px-4 py-2.5"
                                >
                                    <p
                                        class="text-[10px] font-semibold uppercase tracking-wide text-[#64748B]"
                                    >
                                        Available variations
                                    </p>

                                    <p
                                        class="mt-1 text-sm font-bold text-[#1F2937]"
                                    >
                                        {{
                                            hasVariants
                                                ? colors.length +
                                                  ' color options'
                                                : 'Single option'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <!-- ================================================= -->
                            <!-- COLOR -->
                            <!-- ================================================= -->

                            <div
                                v-if="
                                    hasVariants &&
                                    colors.length
                                "
                                class="mt-6"
                            >
                                <p
                                    class="text-xs font-semibold text-[#1F2937]"
                                >
                                    Color
                                </p>

                                <div
                                    class="mt-2.5 flex flex-wrap gap-2"
                                >
                                    <button
                                        v-for="
                                            color in colors
                                        "
                                        :key="color"
                                        type="button"
                                        @click="
                                            selectedColor =
                                                color
                                        "
                                        class="rounded-lg border px-3 py-1.5 text-xs transition"
                                        :class="
                                            selectedColor ===
                                            color
                                                ? 'border-[#087F8C] bg-[#E8F7F6] font-semibold text-[#087F8C]'
                                                : 'border-[#E5E7EB] text-[#64748B] hover:border-gray-300'
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
                                    sizesForColor(
                                        selectedColor
                                    ).length
                                "
                                class="mt-5"
                            >
                                <p
                                    class="text-xs font-semibold text-[#1F2937]"
                                >
                                    Size
                                </p>

                                <div
                                    class="mt-2.5 flex flex-wrap gap-2"
                                >
                                    <button
                                        v-for="
                                            size in sizesForColor(
                                                selectedColor
                                            )
                                        "
                                        :key="size"
                                        type="button"
                                        @click="
                                            selectedSize =
                                                size
                                        "
                                        class="rounded-lg border px-3 py-1.5 text-xs transition"
                                        :class="
                                            selectedSize ===
                                            size
                                                ? 'border-[#087F8C] bg-[#E8F7F6] font-semibold text-[#087F8C]'
                                                : 'border-[#E5E7EB] text-[#64748B] hover:border-gray-300'
                                        "
                                    >
                                        {{ size }}
                                    </button>
                                </div>
                            </div>

                            <!-- ================================================= -->
                            <!-- VARIANT ERROR -->
                            <!-- ================================================= -->

                            <p
                                v-if="
                                    hasVariants &&
                                    !selectedVariant
                                "
                                class="mt-3 text-xs font-semibold text-[#E85D5D]"
                            >
                                Please select an available
                                variation.
                            </p>

                            <!-- ================================================= -->
                            <!-- OUT OF STOCK -->
                            <!-- ================================================= -->

                            <p
                                v-if="
                                    hasVariants &&
                                    selectedVariant &&
                                    Number(
                                        selectedVariant.stock
                                    ) <= 0
                                "
                                class="mt-3 text-xs font-semibold text-[#E85D5D]"
                            >
                                This color/size combination
                                is currently out of stock.
                            </p>

                            <!-- ================================================= -->
                            <!-- QUANTITY -->
                            <!-- ================================================= -->

                            <div
                                class="mt-5"
                            >
                                <div
                                    class="flex items-center justify-between"
                                >
                                    <p
                                        class="text-xs font-semibold text-[#1F2937]"
                                    >
                                        Quantity
                                    </p>

                                    <p
                                        class="text-[11px] text-[#64748B]"
                                    >
                                        {{
                                            availableStock
                                        }}
                                        items available
                                    </p>
                                </div>

                                <div
                                    class="mt-2.5 flex w-fit items-center overflow-hidden rounded-lg border border-[#E5E7EB]"
                                >
                                    <button
                                        type="button"
                                        @click="
                                            decreaseQuantity
                                        "
                                        :disabled="
                                            quantity <= 1 ||
                                            availableStock <=
                                                0
                                        "
                                        class="flex h-8 w-8 items-center justify-center text-sm text-[#64748B] hover:bg-[#F8FAF9] disabled:cursor-not-allowed disabled:opacity-40"
                                    >
                                        −
                                    </button>

                                    <span
                                        class="flex h-8 w-10 items-center justify-center border-x border-[#E5E7EB] text-xs font-semibold"
                                    >
                                        {{ quantity }}
                                    </span>

                                    <button
                                        type="button"
                                        @click="
                                            increaseQuantity
                                        "
                                        :disabled="
                                            quantity >=
                                                availableStock ||
                                            availableStock <=
                                                0
                                        "
                                        class="flex h-8 w-8 items-center justify-center text-sm text-[#64748B] hover:bg-[#F8FAF9] disabled:cursor-not-allowed disabled:opacity-40"
                                    >
                                        +
                                    </button>
                                </div>
                            </div>

                            <!-- ================================================= -->
                            <!-- TOTAL -->
                            <!-- ================================================= -->

                            <div
                                class="mt-5 flex items-center justify-between rounded-xl bg-[#F8FAF9] px-4 py-3"
                            >
                                <span
                                    class="text-xs text-[#64748B]"
                                >
                                    Total
                                </span>

                                <span
                                    class="text-base font-bold text-[#1F2937]"
                                >
                                    {{ totalPrice }}
                                </span>
                            </div>

                            <!-- ================================================= -->
                            <!-- ACTION BUTTONS -->
                            <!-- ================================================= -->

                            <div
                                class="mt-5 grid gap-2.5 sm:grid-cols-2"
                            >
                                <button
                                    type="button"
                                    @click="addToCart"
                                    :disabled="
                                        !canPurchase ||
                                        cartForm.processing
                                    "
                                    class="rounded-xl border border-[#087F8C] px-4 py-3 text-xs font-semibold text-[#087F8C] transition hover:bg-[#E8F7F6] disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {{
                                        cartForm.processing
                                            ? 'Adding...'
                                            : 'Add to Cart'
                                    }}
                                </button>

                                <button
                                    type="button"
                                    @click="buyNow"
                                    :disabled="
                                        !canPurchase ||
                                        cartForm.processing
                                    "
                                    class="rounded-xl bg-[#087F8C] px-4 py-3 text-xs font-semibold text-white shadow-sm transition hover:bg-[#16A6A0] disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {{
                                        cartForm.processing
                                            ? 'Processing...'
                                            : 'Buy Now'
                                    }}
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
                                    @click="
                                        messageSeller
                                    "
                                    :disabled="
                                        cartForm.processing
                                    "
                                    class="w-full rounded-xl border border-[#E5E7EB] bg-white px-4 py-2.5 text-xs font-semibold text-[#1F2937] transition hover:border-[#16A6A0] hover:bg-[#E8F7F6] hover:text-[#087F8C] disabled:cursor-not-allowed disabled:opacity-50"
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
                                <div
                                    class="text-center"
                                >
                                    <div
                                        class="mx-auto flex h-8 w-8 items-center justify-center rounded-full bg-[#E8F7F6] text-[#087F8C]"
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M3 7h11v9H3z"
                                            />
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M14 10h4l3 3v3h-7z"
                                            />
                                            <circle
                                                cx="7"
                                                cy="18"
                                                r="1.5"
                                            />
                                            <circle
                                                cx="18"
                                                cy="18"
                                                r="1.5"
                                            />
                                        </svg>
                                    </div>

                                    <p
                                        class="mt-1 text-[10px] font-semibold text-[#1F2937]"
                                    >
                                        Fast Delivery
                                    </p>
                                </div>

                                <div
                                    class="text-center"
                                >
                                    <div
                                        class="mx-auto flex h-8 w-8 items-center justify-center rounded-full bg-[#E8F7F6] text-[#087F8C]"
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >
                                            <rect
                                                x="5"
                                                y="10"
                                                width="14"
                                                height="10"
                                                rx="2"
                                            />
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M8 10V7a4 4 0 0 1 8 0v3"
                                            />
                                        </svg>
                                    </div>

                                    <p
                                        class="mt-1 text-[10px] font-semibold text-[#1F2937]"
                                    >
                                        Secure Payment
                                    </p>
                                </div>

                                <div
                                    class="text-center"
                                >
                                    <div
                                        class="mx-auto flex h-8 w-8 items-center justify-center rounded-full bg-[#E8F7F6] text-[#087F8C]"
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M4 7h16"
                                            />
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M7 4v3m10-3v3"
                                            />
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M6 11h12v7H6z"
                                            />
                                        </svg>
                                    </div>

                                    <p
                                        class="mt-1 text-[10px] font-semibold text-[#1F2937]"
                                    >
                                        Easy Returns
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ===================================================== -->
                <!-- REVIEWS & RATINGS -->
                <!-- ===================================================== -->

                <section
                    class="mt-10 rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-sm sm:p-6"
                >
                    <!-- HEADER -->

                    <div
                        class="flex flex-col gap-5 border-b border-[#E5E7EB] pb-5 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <div class="flex items-center gap-2">
                                <span
                                    class="h-2 w-2 rounded-full bg-[#F4B942]"
                                ></span>

                                <p
                                    class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#087F8C]"
                                >
                                    Customer feedback
                                </p>
                            </div>

                            <h2
                                class="mt-1.5 text-lg font-bold text-[#1F2937]"
                            >
                                Ratings & Reviews
                            </h2>

                            <div
                                class="mt-2 flex items-center gap-3"
                            >
                                <div
                                    class="text-lg tracking-wide text-[#F4B942]"
                                    aria-label="Product rating"
                                >
                                    ★★★★★
                                </div>

                                <div
                                    class="text-xs text-[#64748B]"
                                >
                                    <span
                                        class="font-bold text-[#1F2937]"
                                    >
                                        {{
                                            averageRating.toFixed(
                                                1
                                            )
                                        }}
                                    </span>

                                    <span class="mx-1">
                                        ·
                                    </span>

                                    {{ reviewsCount }} reviews
                                </div>
                            </div>
                        </div>

                        <Link
                            :href="
                                safeRoute(
                                    'buyer.product.reviews',
                                    product.id
                                )
                            "
                            class="inline-flex items-center justify-center rounded-lg bg-[#087F8C] px-4 py-2 text-xs font-bold text-white transition hover:bg-[#16A6A0]"
                        >
                            See all reviews
                        </Link>
                    </div>

                    <!-- REVIEW LIST -->

                    <div
                        v-if="reviews.length"
                        class="divide-y divide-[#E5E7EB]"
                    >
                        <article
                            v-for="review in reviews"
                            :key="review.id"
                            class="py-5 first:pt-5 last:pb-1"
                        >
                            <!-- REVIEW HEADER -->

                            <div
                                class="flex items-start justify-between gap-4"
                            >
                                <!-- CUSTOMER -->

                                <div
                                    class="flex min-w-0 items-center gap-3"
                                >
                                    <!-- AVATAR -->

                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#E8F7F6] text-xs font-bold text-[#087F8C]"
                                    >
                                        {{
                                            (
                                                review.user?.name ||
                                                'Buyer'
                                            )
                                                .charAt(0)
                                                .toUpperCase()
                                        }}
                                    </div>

                                    <div
                                        class="min-w-0"
                                    >
                                        <p
                                            class="truncate text-xs font-bold text-[#1F2937]"
                                        >
                                            {{
                                                review.user?.name ||
                                                'Verified Buyer'
                                            }}
                                        </p>

                                        <p
                                            class="mt-0.5 text-[10px] text-[#64748B]"
                                        >
                                            {{
                                                reviewDate(
                                                    review.created_at
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <!-- STAR RATING -->

                                <div
                                    class="flex shrink-0 items-center gap-0.5"
                                    :aria-label="`${review.rating} out of 5 stars`"
                                >
                                    <span
                                        v-for="(
                                            filled, index
                                        ) in reviewStars(
                                            review.rating
                                        )"
                                        :key="index"
                                        class="text-sm"
                                        :class="
                                            filled
                                                ? 'text-[#F4B942]'
                                                : 'text-[#E5E7EB]'
                                        "
                                    >
                                        ★
                                    </span>
                                </div>
                            </div>

                            <!-- COMMENT -->

                            <div
                                v-if="review.comment"
                                class="mt-3 rounded-xl bg-[#F8FAF9] px-4 py-3"
                            >
                                <p
                                    class="text-xs leading-6 text-[#475569]"
                                >
                                    {{ review.comment }}
                                </p>
                            </div>

                            <!-- SELLER REPLY -->

                            <div
                                v-if="review.seller_reply"
                                class="mt-3 ml-4 rounded-xl border-l-2 border-[#16A6A0] bg-[#E8F7F6] px-4 py-3"
                            >
                                <p
                                    class="text-[10px] font-bold uppercase tracking-wide text-[#087F8C]"
                                >
                                    Seller response
                                </p>

                                <p
                                    class="mt-1.5 text-xs leading-5 text-[#475569]"
                                >
                                    {{ review.seller_reply }}
                                </p>

                                <p
                                    v-if="
                                        review.seller_replied_at
                                    "
                                    class="mt-1 text-[10px] text-[#64748B]"
                                >
                                    {{
                                        reviewDate(
                                            review.seller_replied_at
                                        )
                                    }}
                                </p>
                            </div>
                        </article>
                    </div>

                    <!-- EMPTY REVIEWS -->

                    <div
                        v-else
                        class="py-10 text-center"
                    >
                        <div
                            class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-[#E8F7F6] text-xl text-[#F4B942]"
                        >
                            ★
                        </div>

                        <h3
                            class="mt-3 text-sm font-bold text-[#1F2937]"
                        >
                            No reviews yet
                        </h3>

                        <p
                            class="mx-auto mt-1 max-w-sm text-xs leading-5 text-[#64748B]"
                        >
                            Be the first customer to share
                            your experience with this
                            product.
                        </p>
                    </div>
                </section>

                <!-- ===================================================== -->
                <!-- RELATED PRODUCTS -->
                <!-- ===================================================== -->

                <section
                    v-if="relatedProducts.length"
                    class="mt-10"
                >
                    <h2
                        class="text-sm font-semibold text-[#1F2937]"
                    >
                        You might also like
                    </h2>

                    <div
                        class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5"
                    >
                        <Link
                            v-for="
                                related in relatedProducts
                            "
                            :key="related.id"
                            :href="
                                safeRoute(
                                    'buyer.product',
                                    related.id
                                )
                            "
                            class="group block overflow-hidden rounded-xl border border-[#E5E7EB] bg-white transition hover:-translate-y-0.5 hover:border-[#16A6A0] hover:shadow-md"
                        >
                            <!-- IMAGE -->

                            <div
                                class="flex h-28 items-center justify-center bg-[#F8FAF9]"
                            >
                                <img
                                    v-if="
                                        related.image_path
                                    "
                                    :src="
                                        imageUrl(
                                            related.image_path
                                        )
                                    "
                                    :alt="
                                        related.name
                                    "
                                    class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                    loading="lazy"
                                />

                                <div
                                    v-else
                                    class="flex h-full w-full items-center justify-center text-[#087F8C]"
                                >
                                    <svg
                                        class="h-9 w-9"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.5"
                                    >
                                        <rect
                                            x="4"
                                            y="5"
                                            width="16"
                                            height="14"
                                            rx="2"
                                        />
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="m4 15 4-4 3 3 2-2 7 5"
                                        />
                                    </svg>
                                </div>
                            </div>

                            <!-- INFO -->

                            <div
                                class="p-3"
                            >
                                <h3
                                    class="line-clamp-1 text-xs font-semibold text-[#1F2937]"
                                >
                                    {{
                                        related.name
                                    }}
                                </h3>

                                <p
                                    class="mt-1 text-xs font-bold text-[#1F2937]"
                                >
                                    ₱{{
                                        Number(
                                            related.price ??
                                                0
                                        ).toLocaleString(
                                            'en-PH',
                                            {
                                                minimumFractionDigits: 2,
                                                maximumFractionDigits: 2,
                                            }
                                        )
                                    }}
                                </p>
                            </div>
                        </Link>
                    </div>
                </section>
            </div>
        </main>

        <!-- ========================================================= -->
        <!-- LOGIN MODAL -->
        <!-- ========================================================= -->

        <AuthModal
            :show="authModalOpen"
            mode="login"
            @close="
                authModalOpen = false
            "
        />
    </BuyerLayout>
</template>