<!-- Buyer product detail page styled to match the Guest product detail page. -->
<script setup>
import Icon from '@/Components/Icon.vue'
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3'
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'
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

    sellerStats: {
        type: Object,
        default: () => ({
            total_products: 0,
            avg_rating: 0,
        }),
    },

    isGuest: {
        type: Boolean,
        default: false,
    },
    vouchers: {
        type: Array,
        default: () => [],
    },
    claimed_voucher_ids: {
        type: Array,
        default: () => [],
    },
    userPurchasedCount: {
        type: Number,
        default: 0,
    },
    userRemainingLimit: {
        type: Number,
        default: null,
    },
    inWishlist: {
        type: Boolean,
        default: false,
    },
    isFollowingStore: {
        type: Boolean,
        default: false,
    },
})

const claimedVoucherIds = ref([...props.claimed_voucher_ids])

const claimProductVoucher = voucher => {
    if (isGuestUser.value) {
        router.visit(route('login'))
        return
    }
    router.post(
        route('buyer.vouchers.claim', voucher.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                if (!claimedVoucherIds.value.includes(voucher.id)) {
                    claimedVoucherIds.value.push(voucher.id)
                }
            },
        }
    )
}

const page = usePage()
const user = computed(() => page.props.auth?.user ?? null)
const isGuestUser = computed(() => Boolean(props.isGuest || !user.value))
const product = computed(() => props.product || {})
const pageLayout = computed(() =>
    isGuestUser.value ? GuestLayout : BuyerLayout
)

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
        if (isGuestUser.value) {
            const guestRoutes = {
                'buyer.products': 'guest.products',
                'buyer.product': 'guest.product',
            }
            name = guestRoutes[name] || name
        }

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

const allSizes = computed(() =>
    [
        ...new Set(
            variants.value
                .map(variant => variant.size)
                .filter(Boolean)
        ),
    ]
)

const hasColorOptions = computed(() => colors.value.length > 0)
const hasSizeOptions = computed(() => allSizes.value.length > 0)

const sizesForColor = color =>
    [
        ...new Set(
            variants.value
                .filter(
                    variant =>
                        !color || variant.color === color
                )
                .map(variant => variant.size)
                .filter(Boolean)
        ),
    ]

const variantFor = (color, size) =>
    variants.value.find(
        variant =>
            (!hasColorOptions.value || variant.color === color) &&
            (!hasSizeOptions.value || variant.size === size)
    ) || null

const isSizeOutOfStock = size => {
    if (hasColorOptions.value && !selectedColor.value) {
        return variants.value
            .filter(v => v.size === size)
            .every(v => Number(v.stock ?? 0) <= 0)
    }
    const v = variantFor(selectedColor.value, size)
    return !v || Number(v.stock ?? 0) <= 0
}

const selectedColor = ref(null)
const selectedSize = ref(null)

watch(
    selectedColor,
    color => {
        if (!color) return

        // Switch main image when color variant has an image_path (SEL-VAR-008)
        const variantWithImg = variants.value.find(
            v => v.color === color && v.image_path
        )
        if (variantWithImg?.image_path) {
            activeImage.value = imageUrl(variantWithImg.image_path)
        }

        const available = sizesForColor(color)
        if (
            selectedSize.value &&
            (!available.includes(selectedSize.value) || isSizeOutOfStock(selectedSize.value))
        ) {
            selectedSize.value = null
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

    if (hasColorOptions.value && !selectedColor.value) {
        return null
    }

    if (hasSizeOptions.value && !selectedSize.value) {
        return null
    }

    return variantFor(selectedColor.value, selectedSize.value)
})

watch(
    selectedVariant,
    variant => {
        if (variant?.image_path) {
            activeImage.value = imageUrl(variant.image_path)
        }
    }
)

/*
|--------------------------------------------------------------------------
| STOCK
|--------------------------------------------------------------------------
*/

const availableStock = computed(() => {
    if (hasVariants.value) {
        if (selectedVariant.value) {
            return Number(selectedVariant.value.stock ?? 0)
        }
        return Number(product.value.stock ?? 0)
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
| LIVE FLASH SALE COUNTDOWN TIMER (BUY-PDP-006)
|--------------------------------------------------------------------------
*/

const flashCountdown = ref({
    hours: '00',
    minutes: '00',
    seconds: '00',
    totalSeconds: 0,
    isEnded: false,
})

let flashTimerInterval = null

function updateCountdown() {
    if (!product.value?.has_active_flash_sale || !product.value?.flash_ends_at) {
        return
    }

    const end = new Date(product.value.flash_ends_at).getTime()
    const now = Date.now()
    const diff = Math.max(0, Math.floor((end - now) / 1000))

    if (diff <= 0) {
        flashCountdown.value = {
            hours: '00',
            minutes: '00',
            seconds: '00',
            totalSeconds: 0,
            isEnded: true,
        }
        return
    }

    const hours = Math.floor(diff / 3600)
    const minutes = Math.floor((diff % 3600) / 60)
    const seconds = diff % 60

    flashCountdown.value = {
        hours: String(hours).padStart(2, '0'),
        minutes: String(minutes).padStart(2, '0'),
        seconds: String(seconds).padStart(2, '0'),
        totalSeconds: diff,
        isEnded: false,
    }
}

onMounted(() => {
    if (product.value?.has_active_flash_sale) {
        updateCountdown()
        flashTimerInterval = setInterval(updateCountdown, 1000)
    }
})

onUnmounted(() => {
    if (flashTimerInterval) {
        clearInterval(flashTimerInterval)
    }
})

/*
|--------------------------------------------------------------------------
| QUANTITY & PURCHASE LIMITS (BUY-PDP-004 & BUY-PDP-005)
|--------------------------------------------------------------------------
*/

const quantity = ref(1)
const quantityError = ref('')

const effectivePerBuyerLimit = computed(() => {
    return Number(product.value?.per_buyer_limit || product.value?.effective_per_buyer_limit || 0)
})

const remainingPerBuyer = computed(() => {
    if (!effectivePerBuyerLimit.value || effectivePerBuyerLimit.value <= 0) {
        return null
    }
    if (props.userRemainingLimit !== undefined && props.userRemainingLimit !== null) {
        return Number(props.userRemainingLimit)
    }
    return effectivePerBuyerLimit.value
})

const isPurchaseLimitReached = computed(() => {
    return remainingPerBuyer.value !== null && remainingPerBuyer.value <= 0
})

const maxAllowedQuantity = computed(() => {
    const stock = Number(availableStock.value || 0)
    if (remainingPerBuyer.value !== null) {
        return Math.max(0, Math.min(stock, remainingPerBuyer.value))
    }
    return Math.max(0, stock)
})

watch(
    availableStock,
    stock => {
        quantityError.value = ''
        if (stock <= 0) {
            quantity.value = 1
            return
        }

        const max = maxAllowedQuantity.value > 0 ? maxAllowedQuantity.value : stock
        if (quantity.value > max) {
            quantity.value = max
        }

        if (quantity.value < 1) {
            quantity.value = 1
        }
    },
    {
        immediate: true,
    }
)

function onQuantityChange(event) {
    const rawVal = event.target.value
    quantityError.value = ''

    if (rawVal === '' || rawVal === null) {
        quantityError.value = 'Please enter a valid quantity.'
        return
    }

    const val = parseInt(rawVal, 10)
    if (isNaN(val) || val < 1) {
        quantityError.value = 'Quantity must be at least 1.'
        quantity.value = 1
        return
    }

    if (val > availableStock.value) {
        quantityError.value = `Cannot exceed available stock of ${availableStock.value}.`
        quantity.value = availableStock.value
        return
    }

    if (remainingPerBuyer.value !== null && val > remainingPerBuyer.value) {
        quantityError.value = remainingPerBuyer.value > 0
            ? `Purchase limit: max ${effectivePerBuyerLimit.value} per customer. You can only buy up to ${remainingPerBuyer.value} more.`
            : `Purchase limit reached. Maximum ${effectivePerBuyerLimit.value} allowed per customer.`
        quantity.value = Math.max(1, remainingPerBuyer.value)
        return
    }

    quantity.value = val
}

function increaseQuantity() {
    quantityError.value = ''
    if (quantity.value < maxAllowedQuantity.value) {
        quantity.value++
    } else if (remainingPerBuyer.value !== null && quantity.value >= remainingPerBuyer.value) {
        quantityError.value = `Purchase limit: max ${effectivePerBuyerLimit.value} per customer.`
    } else if (quantity.value >= availableStock.value) {
        quantityError.value = `Cannot exceed available stock of ${availableStock.value}.`
    }
}

function decreaseQuantity() {
    quantityError.value = ''
    if (quantity.value > 1) {
        quantity.value--
    } else {
        quantityError.value = 'Quantity must be at least 1.'
    }
}

/*
|--------------------------------------------------------------------------
| PRICE
|--------------------------------------------------------------------------
*/

const unitPrice = computed(() =>
    Number(
        selectedVariant.value?.price ??
        product.value.price ??
        0
    )
)

const formattedUnitPrice = computed(() => {
    if (hasVariants.value && !selectedVariant.value && product.value.price_range) {
        return product.value.price_range
    }

    return `₱${unitPrice.value.toLocaleString(
        'en-PH',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }
    )}`
})

const formattedOldPrice = computed(() =>
    Number(
        selectedVariant.value?.old_price || product.value.old_price || 0
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

const isSellerOnVacation = computed(() => Boolean(seller.value?.vacation_mode))

const canPurchase = computed(() =>
    Boolean(product.value.id) &&
    availableStock.value > 0 &&
    !isSellerOnVacation.value &&
    !isPurchaseLimitReached.value &&
    (
        !hasVariants.value ||
        (Boolean(selectedVariant.value) && Number(selectedVariant.value.stock ?? 0) > 0)
    )
)

/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

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
    buy_now: false,
})

function prepareCartForm(isBuyNow = false) {
    cartForm.quantity = quantity.value
    cartForm.product_variant_id = selectedVariant.value?.id ?? null
    cartForm.buy_now = Boolean(isBuyNow)
}

/*
|--------------------------------------------------------------------------
| ADD TO CART
|--------------------------------------------------------------------------
*/

function addToCart() {
    if (isGuestUser.value) {
        requireLogin()
        return
    }

    if (requireLogin()) {
        return
    }

    if (!canPurchase.value) {
        return
    }

    prepareCartForm(false)

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
| BUY NOW (BUY-PDP-007)
|--------------------------------------------------------------------------
*/

function buyNow() {
    if (isGuestUser.value) {
        requireLogin()
        return
    }

    if (requireLogin()) {
        return
    }

    if (!canPurchase.value) {
        return
    }

    prepareCartForm(true)

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
| MESSAGE SELLER
|--------------------------------------------------------------------------
*/

function messageSeller() {
    if (isGuestUser.value) {
        requireLogin()
        return
    }

    if (requireLogin()) {
        return
    }

    if (!product.value.id) {
        return
    }

    if (typeof window !== 'undefined') {
        window.dispatchEvent(
            new CustomEvent('open-buyer-chat', {
                detail: {
                    productId: product.value.id,
                    sellerId: product.value.seller_id,
                },
            })
        )
    }
}

/*
|--------------------------------------------------------------------------
| WISHLIST & FOLLOW STORE (BUY-WISH-001 - BUY-WISH-005)
|--------------------------------------------------------------------------
*/

const inWishlist = ref(Boolean(props.inWishlist))
const wishlistProcessing = ref(false)

watch(
    () => props.inWishlist,
    val => {
        inWishlist.value = Boolean(val)
    }
)

function toggleWishlist() {
    if (isGuestUser.value) {
        requireLogin()
        return
    }

    if (wishlistProcessing.value || !product.value.id) return
    wishlistProcessing.value = true

    const willWishlist = !inWishlist.value
    inWishlist.value = willWishlist

    router.post(
        route('buyer.wishlist.toggle', product.value.id),
        {},
        {
            preserveScroll: true,
            onError: () => {
                inWishlist.value = !willWishlist
            },
            onFinish: () => {
                wishlistProcessing.value = false
            },
        }
    )
}

const remindingFlashSale = ref(false)

function toggleFlashReminder(flashSaleId) {
    if (isGuestUser.value) {
        requireLogin()
        return
    }

    if (remindingFlashSale.value || !flashSaleId) return
    remindingFlashSale.value = true

    router.post(
        route('buyer.flash-sales.remind', flashSaleId),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                if (product.value?.upcoming_flash_sale) {
                    product.value.upcoming_flash_sale.has_reminder = !product.value.upcoming_flash_sale.has_reminder
                }
            },
            onFinish: () => {
                remindingFlashSale.value = false
            },
        }
    )
}

const isFollowingStore = ref(Boolean(props.isFollowingStore))
const storeFollowersCount = ref(Number(props.sellerStats?.followers_count ?? 0))
const followStoreProcessing = ref(false)

watch(
    () => props.isFollowingStore,
    val => {
        isFollowingStore.value = Boolean(val)
    }
)
watch(
    () => props.sellerStats?.followers_count,
    val => {
        if (val !== undefined) {
            storeFollowersCount.value = Number(val)
        }
    }
)

function toggleFollowStore() {
    if (isGuestUser.value) {
        requireLogin()
        return
    }

    if (!seller.value?.id || followStoreProcessing.value) return
    followStoreProcessing.value = true

    const willFollow = !isFollowingStore.value
    isFollowingStore.value = willFollow
    storeFollowersCount.value = Math.max(0, storeFollowersCount.value + (willFollow ? 1 : -1))

    router.post(
        route('buyer.store.follow', seller.value.id),
        {},
        {
            preserveScroll: true,
            onError: () => {
                isFollowingStore.value = !willFollow
                storeFollowersCount.value = Math.max(0, storeFollowersCount.value + (!willFollow ? 1 : -1))
            },
            onFinish: () => {
                followStoreProcessing.value = false
            },
        }
    )
}

/*
|--------------------------------------------------------------------------
| REPORT PRODUCT (BUYER-34)
|--------------------------------------------------------------------------
*/

const isReportModalOpen = ref(false)

const reportForm = useForm({
    report_type: 'product',
    product_id: null,
    seller_id: null,
    reason: 'Counterfeit / Fake Item',
    description: '',
    evidence_images: [],
})

const reportReasons = [
    'Counterfeit / Fake Item',
    'Misleading Description or Photos',
    'Prohibited or Illegal Item',
    'Damaged / Defective / Expired Product',
    'Price Gouging / Scam',
    'Other Product Issue',
]

function openReportModal() {
    if (isGuestUser.value) {
        requireLogin()
        return
    }

    if (requireLogin()) {
        return
    }

    reportForm.reset()
    reportForm.report_type = 'product'
    reportForm.product_id = product.value.id
    reportForm.seller_id = product.value.seller?.id || null
    isReportModalOpen.value = true
}

function closeReportModal() {
    isReportModalOpen.value = false
    reportForm.reset()
}

function handleReportFileChange(event) {
    const files = Array.from(event.target.files || [])
    reportForm.evidence_images = files.slice(0, 4)
}

function submitReport() {
    reportForm.post(safeRoute('buyer.reports.store'), {
        preserveScroll: true,
        onSuccess: () => {
            closeReportModal()
        },
    })
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
            href: isGuestUser.value
                ? '/'
                : safeRoute(
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

    <component :is="pageLayout">
        <main
            class="min-h-screen bg-[#F8FAF9]"
        >
            <div
                class="mx-auto max-w-[1536px] w-full px-4 py-6 sm:px-6 lg:px-8 2xl:px-10 lg:py-8"
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
                            class="relative flex flex-col items-center justify-center gap-4 bg-[#E8F7F6] p-8"
                        >
                            <!-- WISHLIST HEART BUTTON (BUY-WISH-001 & BUY-WISH-005) -->
                            <button
                                type="button"
                                @click="toggleWishlist"
                                :disabled="wishlistProcessing"
                                :title="inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'"
                                class="absolute right-4 top-4 z-10 flex h-10 w-10 items-center justify-center rounded-full bg-white/90 text-rose-500 shadow-sm backdrop-blur-xs transition hover:scale-110 hover:bg-rose-50 disabled:opacity-50"
                            >
                                <svg
                                    class="h-5 w-5"
                                    :class="inWishlist ? 'fill-current text-rose-500' : 'text-slate-400 hover:text-rose-400'"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    fill="none"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"
                                    />
                                </svg>
                            </button>

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
                                                v-if="product.seller?.id"
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
                                            <span
                                                v-else
                                                class="mt-0.5 block truncate text-sm font-bold text-[#1F2937]"
                                            >
                                                {{ storeName }}
                                            </span>

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

                                                <span
                                                    class="h-3 w-px bg-[#E5E7EB]"
                                                ></span>

                                                <span
                                                    class="text-[11px] text-[#64748B]"
                                                >
                                                    {{
                                                        storeFollowersCount
                                                    }}
                                                    followers
                                                </span>
                                            </div>
                                        </div>

                                        <!-- VISIT STORE & FOLLOW STORE -->
                                        <div class="hidden shrink-0 items-center gap-1.5 sm:flex">
                                            <button
                                                v-if="product.seller?.id"
                                                type="button"
                                                @click="toggleFollowStore"
                                                :disabled="followStoreProcessing"
                                                class="items-center gap-1 rounded-lg border px-2.5 py-2 text-[10px] font-bold transition inline-flex"
                                                :class="
                                                    isFollowingStore
                                                        ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                                        : 'border-[#087F8C] bg-white text-[#087F8C] hover:bg-[#E8F7F6]'
                                                "
                                            >
                                                <span v-if="!isFollowingStore">+ Follow</span>
                                                <span v-else class="flex items-center gap-1">
                                                    <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                        <polyline points="20 6 9 17 4 12"></polyline>
                                                    </svg>
                                                    Following
                                                </span>
                                            </button>

                                            <Link
                                                v-if="product.seller?.id"
                                                :href="
                                                    safeRoute(
                                                        'buyer.store.show',
                                                        product.seller.id
                                                    )
                                                "
                                                class="items-center gap-1.5 rounded-lg border border-[#087F8C] bg-white px-3 py-2 text-[10px] font-bold text-[#087F8C] transition hover:bg-[#E8F7F6] inline-flex"
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

                                    <!-- VACATION MODE ALERT (SELLER-65) -->
                                    <div
                                        v-if="isSellerOnVacation"
                                        class="mt-3 flex items-center gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
                                    >
                                        <svg class="h-4 w-4 shrink-0 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10" />
                                            <line x1="12" y1="8" x2="12" y2="12" />
                                            <line x1="12" y1="16" x2="12.01" y2="16" />
                                        </svg>
                                        <span>
                                            <strong class="font-semibold">Store on Vacation:</strong>
                                            {{ seller.vacation_message || 'Ordering from this store is temporarily paused.' }}
                                        </span>
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
                                        v-if="product.seller?.id"
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
                                    {{ product.discount_badge || 'SALE' }}
                                </span>
                            </div>

                            <!-- FLASH SALE BANNER WITH LIVE COUNTDOWN TIMER (SEL-FLASH-001 / BUY-PDP-006) -->
                            <div
                                v-if="product.has_active_flash_sale"
                                class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-amber-300 bg-amber-50 px-3.5 py-2.5 text-xs shadow-sm"
                            >
                                <div class="flex items-center gap-2 font-bold text-amber-900">
                                    <span class="rounded bg-amber-500 px-2 py-0.5 text-[10px] font-extrabold uppercase text-white shadow-sm">
                                        ⚡ Flash Sale
                                    </span>
                                    <span v-if="product.flash_remaining_label" class="text-red-600 font-extrabold">
                                        {{ product.flash_remaining_label }}
                                    </span>
                                </div>
                                
                                <!-- Live Countdown Timer Display -->
                                <div v-if="product.flash_ends_at && !flashCountdown.isEnded" class="flex items-center gap-1.5 text-xs font-bold text-amber-950">
                                    <span class="text-[11px] font-medium text-amber-800">Ends in</span>
                                    <div class="flex items-center gap-1 font-mono text-[11px] font-bold text-white">
                                        <span class="flex h-5 w-6 items-center justify-center rounded bg-amber-700/90 px-1">{{ flashCountdown.hours }}</span>
                                        <span class="text-amber-800 font-black">:</span>
                                        <span class="flex h-5 w-6 items-center justify-center rounded bg-amber-700/90 px-1">{{ flashCountdown.minutes }}</span>
                                        <span class="text-amber-800 font-black">:</span>
                                        <span class="flex h-5 w-6 items-center justify-center rounded bg-amber-700/90 px-1">{{ flashCountdown.seconds }}</span>
                                    </div>
                                </div>
                                <div v-else-if="product.flash_ends_at" class="text-[11px] font-semibold text-amber-800">
                                    Ends at {{ new Date(product.flash_ends_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                                </div>
                            </div>

                            <!-- UPCOMING FLASH SALE NOTICE (SEL-FLASH-001, BUY-CHAT-004) -->
                            <div
                                v-else-if="product.upcoming_flash_sale"
                                class="mt-3 flex flex-col gap-2 rounded-xl border border-indigo-200 bg-indigo-50 p-3 text-xs text-indigo-900 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="font-bold">
                                        {{ product.upcoming_flash_sale.notice }}
                                    </span>
                                    <span class="rounded bg-indigo-600 px-2 py-0.5 text-[10px] font-bold text-white shrink-0">
                                        {{ product.upcoming_flash_sale.flash_quantity }} units
                                    </span>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-extrabold transition shrink-0 shadow-2xs"
                                    :class="product.upcoming_flash_sale.has_reminder ? 'bg-indigo-600 text-white hover:bg-indigo-700' : 'bg-white text-indigo-700 border border-indigo-300 hover:bg-indigo-100/60'"
                                    :disabled="remindingFlashSale"
                                    @click="toggleFlashReminder(product.upcoming_flash_sale.id)"
                                >
                                    <span>{{ product.upcoming_flash_sale.has_reminder ? '✓ Reminder Set' : '🔔 Remind Me' }}</span>
                                </button>
                            </div>

                            <!-- CLAIMABLE STORE VOUCHERS (SEL-VOU-001) -->
                            <div
                                v-if="vouchers && vouchers.length > 0"
                                class="mt-4 space-y-2 rounded-xl border border-[#B2DFDB] bg-[#E8F7F6]/50 p-3"
                            >
                                <p class="text-[11px] font-bold uppercase tracking-wide text-[#087F8C]">
                                    Store Vouchers Available
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    <div
                                        v-for="v in vouchers"
                                        :key="v.id"
                                        class="flex items-center justify-between gap-3 rounded-lg border border-[#087F8C]/30 bg-white px-3 py-1.5 text-xs shadow-2xs"
                                    >
                                        <span class="font-bold text-[#1F2937]">
                                            {{ v.claim_label || `Claim ₱${Number(v.value).toFixed(0)} off` }}
                                        </span>
                                        <button
                                            type="button"
                                            @click="claimProductVoucher(v)"
                                            :disabled="!isGuestUser && claimedVoucherIds.includes(v.id)"
                                            class="rounded-md px-2.5 py-1 text-[11px] font-bold transition"
                                            :class="
                                                !isGuestUser && claimedVoucherIds.includes(v.id)
                                                    ? 'bg-slate-200 text-slate-500 cursor-not-allowed'
                                                    : 'bg-[#087F8C] text-white hover:bg-[#066B76]'
                                            "
                                        >
                                            {{ !isGuestUser && claimedVoucherIds.includes(v.id) ? 'Claimed' : 'Claim' }}
                                        </button>
                                    </div>
                                </div>
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
                                        {{ availableStock <= 0 ? '— Sold out' : 'items available' }}

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
                                        :disabled="isSizeOutOfStock(size)"
                                        @click="
                                            !isSizeOutOfStock(size) &&
                                                (selectedSize = size)
                                        "
                                        class="rounded-lg border px-3 py-1.5 text-xs transition"
                                        :class="[
                                            isSizeOutOfStock(size)
                                                ? 'cursor-not-allowed border-gray-200 bg-gray-100 text-gray-400 line-through opacity-60'
                                                : selectedSize === size
                                                    ? 'border-[#087F8C] bg-[#E8F7F6] font-semibold text-[#087F8C]'
                                                    : 'border-[#E5E7EB] text-[#64748B] hover:border-gray-300',
                                        ]"
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
                                        {{ availableStock <= 0 ? '— Sold out' : 'items available' }}
                                    </p>
                                </div>

                                <!-- Purchase Limit Notice (BUY-PDP-005) -->
                                <div
                                    v-if="effectivePerBuyerLimit > 0"
                                    class="mt-2 flex items-center gap-2 rounded-xl border px-3 py-2 text-xs"
                                    :class="isPurchaseLimitReached ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-amber-200 bg-amber-50 text-amber-900'"
                                >
                                    <Icon name="info" class="h-4 w-4 shrink-0" :class="isPurchaseLimitReached ? 'text-rose-600' : 'text-amber-600'" />
                                    <div class="min-w-0">
                                        <span v-if="isPurchaseLimitReached" class="font-bold">
                                            You have reached the purchase limit of {{ effectivePerBuyerLimit }} item(s) for this product.
                                        </span>
                                        <span v-else>
                                            Purchase limit: <strong>{{ effectivePerBuyerLimit }}</strong> per customer
                                            <span v-if="remainingPerBuyer !== null" class="text-[#64748B]">
                                                (max {{ remainingPerBuyer }} more allowed)
                                            </span>
                                        </span>
                                    </div>
                                </div>

                                <!-- Quantity Controls with Editable Input (BUY-PDP-004) -->
                                <div class="mt-2.5 flex items-center gap-3">
                                    <div
                                        class="flex w-fit items-center overflow-hidden rounded-lg border border-[#E5E7EB] bg-white"
                                    >
                                        <button
                                            type="button"
                                            @click="decreaseQuantity"
                                            :disabled="quantity <= 1 || availableStock <= 0 || isPurchaseLimitReached"
                                            class="flex h-8 w-8 items-center justify-center text-sm text-[#64748B] hover:bg-[#F8FAF9] disabled:cursor-not-allowed disabled:opacity-40"
                                        >
                                            −
                                        </button>

                                        <input
                                            id="pdp-quantity-input"
                                            type="number"
                                            :value="quantity"
                                            @input="onQuantityChange"
                                            :min="1"
                                            :max="maxAllowedQuantity"
                                            :disabled="availableStock <= 0 || isPurchaseLimitReached"
                                            class="flex h-8 w-12 items-center justify-center border-y-0 border-x border-[#E5E7EB] bg-transparent text-center text-xs font-semibold text-[#1F2937] focus:outline-none focus:ring-1 focus:ring-[#087F8C] p-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                        />

                                        <button
                                            type="button"
                                            @click="increaseQuantity"
                                            :disabled="quantity >= maxAllowedQuantity || availableStock <= 0 || isPurchaseLimitReached"
                                            class="flex h-8 w-8 items-center justify-center text-sm text-[#64748B] hover:bg-[#F8FAF9] disabled:cursor-not-allowed disabled:opacity-40"
                                        >
                                            +
                                        </button>
                                    </div>

                                    <span v-if="effectivePerBuyerLimit > 0" class="text-[11px] text-[#64748B]">
                                        (Max {{ effectivePerBuyerLimit }} per buyer)
                                    </span>
                                </div>

                                <!-- Inline Quantity Warning (BUY-PDP-004) -->
                                <p v-if="quantityError" class="mt-1.5 text-xs font-semibold text-rose-600">
                                    {{ quantityError }}
                                </p>
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
                                        cartForm.processing ||
                                        isPurchaseLimitReached
                                    "
                                    class="rounded-xl border border-[#087F8C] px-4 py-3 text-xs font-semibold text-[#087F8C] transition hover:bg-[#E8F7F6] disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {{
                                        isPurchaseLimitReached
                                            ? 'Limit Reached'
                                            : isSellerOnVacation
                                                ? 'Store on Vacation'
                                                : cartForm.processing
                                                    ? 'Adding...'
                                                    : 'Add to Cart'
                                    }}
                                </button>

                                <button
                                    type="button"
                                    @click="buyNow"
                                    :disabled="
                                        !canPurchase ||
                                        cartForm.processing ||
                                        isPurchaseLimitReached
                                    "
                                    class="rounded-xl bg-[#087F8C] px-4 py-3 text-xs font-semibold text-white shadow-sm transition hover:bg-[#16A6A0] disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {{
                                        isPurchaseLimitReached
                                            ? 'Limit Reached'
                                            : isSellerOnVacation
                                                ? 'Store on Vacation'
                                                : cartForm.processing
                                                    ? 'Processing...'
                                                    : 'Buy Now'
                                    }}
                                </button>
                            </div>

                            <!-- ================================================= -->
                            <!-- MESSAGE SELLER & REPORT PRODUCT (BUYER-34) -->
                            <!-- ================================================= -->

                            <div
                                v-if="product.id"
                                class="mt-3 flex items-center gap-2"
                            >
                                <button
                                    type="button"
                                    @click="toggleWishlist"
                                    :disabled="wishlistProcessing"
                                    :title="inWishlist ? 'Remove from Wishlist' : 'Add to Wishlist'"
                                    class="inline-flex items-center justify-center gap-1.5 rounded-xl border px-3.5 py-2.5 text-xs font-semibold transition cursor-pointer"
                                    :class="
                                        inWishlist
                                            ? 'border-rose-300 bg-rose-50 text-rose-600'
                                            : 'border-[#E5E7EB] bg-white text-[#1F2937] hover:border-rose-300 hover:text-rose-600'
                                    "
                                >
                                    <svg
                                        class="h-4 w-4"
                                        :class="inWishlist ? 'fill-current text-rose-500' : 'text-slate-400'"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        fill="none"
                                        stroke-width="2"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"
                                        />
                                    </svg>
                                    <span>{{ inWishlist ? 'Wishlisted' : 'Wishlist' }}</span>
                                </button>

                                <button
                                    type="button"
                                    @click="
                                        messageSeller
                                    "
                                    :disabled="
                                        cartForm.processing
                                    "
                                    class="flex-1 rounded-xl border border-[#E5E7EB] bg-white px-4 py-2.5 text-xs font-semibold text-[#1F2937] transition hover:border-[#16A6A0] hover:bg-[#E8F7F6] hover:text-[#087F8C] disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    Message Seller
                                </button>

                                <button
                                    type="button"
                                    @click="openReportModal"
                                    title="Report this product for policy violations"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-red-200 bg-[#FEF2F2] px-3.5 py-2.5 text-xs font-semibold text-[#DC2626] transition hover:bg-[#FEE2E2]"
                                >
                                    <span><Icon name="flag" class="h-4 w-4" /></span>
                                    <span>Report</span>
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

                        <button
                            v-if="isGuestUser"
                            type="button"
                            @click="requireLogin"
                            class="inline-flex items-center justify-center rounded-lg bg-[#087F8C] px-4 py-2 text-xs font-bold text-white transition hover:bg-[#16A6A0]"
                        >
                            See all reviews
                        </button>
                        <Link
                            v-else
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
                        class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-6"
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

        <!-- ========================================================= -->
        <!-- REPORT PRODUCT MODAL (BUYER-34) -->
        <!-- ========================================================= -->

        <div
            v-if="isReportModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs"
        >
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xl"><Icon name="flag" class="h-4 w-4" /></span>
                        <div>
                            <h3 class="font-bold text-gray-900">Report This Product</h3>
                            <p class="text-xs text-gray-500">Flag policy violations, fake listings, or safety issues</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="text-gray-400 hover:text-gray-600"
                        @click="closeReportModal"
                    >
                        ✕
                    </button>
                </div>

                <form class="mt-4 space-y-4" @submit.prevent="submitReport">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700">Product</label>
                        <p class="mt-1 text-xs font-medium text-gray-800 line-clamp-1">
                            {{ product.name }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700">Violation Reason</label>
                        <select
                            v-model="reportForm.reason"
                            class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-xs focus:border-[#087F8C] focus:outline-hidden"
                            required
                        >
                            <option v-for="reason in reportReasons" :key="reason" :value="reason">
                                {{ reason }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700">Description of Violation</label>
                        <textarea
                            v-model="reportForm.description"
                            rows="4"
                            class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 text-xs focus:border-[#087F8C] focus:outline-hidden"
                            placeholder="Provide specific details about why this product violates community standards..."
                            required
                        ></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700">Evidence / Screenshots (Optional, max 4)</label>
                        <input
                            type="file"
                            multiple
                            accept="image/*"
                            class="mt-1 block w-full text-xs text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-[#E8F7F6] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-[#087F8C] hover:file:bg-[#d6f0ef]"
                            @change="handleReportFileChange"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button
                            type="button"
                            class="rounded-xl border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50"
                            @click="closeReportModal"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="reportForm.processing"
                            class="rounded-xl bg-[#DC2626] px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-[#B91C1C] disabled:opacity-50"
                        >
                            {{ reportForm.processing ? 'Submitting Report...' : 'Submit Report' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </component>

    <AuthModal
        :show="authModalOpen"
        mode="login"
        @close="authModalOpen = false"
    />
</template>