<!-- Buyer Reviews Page with Reviewed Products, Awaiting Review, Seller Ratings, Media uploads, and Edit Review support. -->
<script setup>
import Icon from '@/Components/Icon.vue'
import { ref, computed } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'

const props = defineProps({
    reviews: {
        type: Object,
        default: () => null,
    },
    awaiting_items: {
        type: Object,
        default: () => null,
    },
    seller_reviews: {
        type: Object,
        default: () => null,
    },
    orders_awaiting_seller_review: {
        type: Array,
        default: () => [],
    },
    current_tab: {
        type: String,
        default: 'reviewed',
    },
    counts: {
        type: Object,
        default: () => ({
            reviewed: 0,
            awaiting: 0,
            seller_reviews: 0,
            awaiting_seller: 0,
        }),
    },
    status: {
        type: String,
        default: '',
    },
})

const tabs = [
    { key: 'reviewed', label: 'My Reviews', countKey: 'reviewed' },
    { key: 'awaiting', label: 'To Review', countKey: 'awaiting' },
    { key: 'seller_reviews', label: 'Seller Ratings', countKey: 'seller_reviews' },
]

const selectTab = tabKey => {
    router.get(
        route('buyer.reviews'),
        { tab: tabKey },
        { preserveState: true, preserveScroll: true }
    )
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const formatDate = value => {
    if (!value) return ''
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return ''
    return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}

const imageUrl = path => {
    if (!path) return null
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/storage/')) return path
    return `/storage/${path.replace(/^\/+/, '')}`
}

/*
|--------------------------------------------------------------------------
| Create / Edit Review Modal (BUYER-28, BUYER-29, BUYER-31)
|--------------------------------------------------------------------------
*/

const showProductReviewModal = ref(false)
const editingReview = ref(null)
const targetProduct = ref(null)
const mediaPreviews = ref([])

const productReviewForm = useForm({
    rating: 5,
    comment: '',
    media: [],
    existing_media: [],
})

const openCreateReviewModal = item => {
    editingReview.value = null
    targetProduct.value = item.product
    productReviewForm.reset()
    productReviewForm.rating = 5
    productReviewForm.comment = ''
    productReviewForm.media = []
    productReviewForm.existing_media = []
    mediaPreviews.value = []
    productReviewForm.clearErrors()
    showProductReviewModal.value = true
}

const openEditReviewModal = review => {
    editingReview.value = review
    targetProduct.value = review.product
    productReviewForm.reset()
    productReviewForm.rating = review.rating || 5
    productReviewForm.comment = review.comment || ''
    productReviewForm.media = []
    productReviewForm.existing_media = review.media || []
    mediaPreviews.value = []
    productReviewForm.clearErrors()
    showProductReviewModal.value = true
}

const closeProductReviewModal = () => {
    if (productReviewForm.processing) return
    showProductReviewModal.value = false
    editingReview.value = null
    targetProduct.value = null
    mediaPreviews.value = []
    productReviewForm.reset()
}

const handleMediaUpload = e => {
    const files = Array.from(e.target.files || [])
    productReviewForm.media = files

    mediaPreviews.value = []
    files.forEach(file => {
        const reader = new FileReader()
        reader.onload = ev => {
            mediaPreviews.value.push(ev.target.result)
        }
        reader.readAsDataURL(file)
    })
}

const removeExistingMedia = index => {
    productReviewForm.existing_media.splice(index, 1)
}

const submitProductReview = () => {
    if (productReviewForm.processing) return

    if (editingReview.value) {
        // Edit existing review (BUYER-29)
        productReviewForm.post(route('buyer.reviews.update', editingReview.value.id), {
            preserveScroll: true,
            headers: {
                'X-HTTP-Method-Override': 'PATCH',
            },
            onSuccess: () => {
                closeProductReviewModal()
            },
        })
    } else if (targetProduct.value) {
        // Create new review (BUYER-28, BUYER-31)
        productReviewForm.post(route('buyer.reviews.store', targetProduct.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                closeProductReviewModal()
            },
        })
    }
}

const deleteReview = reviewId => {
    if (!confirm('Are you sure you want to remove your review?')) return
    router.delete(route('buyer.reviews.destroy', reviewId), {
        preserveScroll: true,
    })
}

/*
|--------------------------------------------------------------------------
| Seller Review Modal (BUYER-30)
|--------------------------------------------------------------------------
*/

const showSellerReviewModal = ref(false)
const targetSeller = ref(null)
const targetOrder = ref(null)

const sellerReviewForm = useForm({
    rating: 5,
    comment: '',
})

const openSellerReviewModal = (seller, order) => {
    targetSeller.value = seller
    targetOrder.value = order
    sellerReviewForm.reset()
    sellerReviewForm.rating = 5
    sellerReviewForm.comment = ''
    sellerReviewForm.clearErrors()
    showSellerReviewModal.value = true
}

const closeSellerReviewModal = () => {
    if (sellerReviewForm.processing) return
    showSellerReviewModal.value = false
    targetSeller.value = null
    targetOrder.value = null
    sellerReviewForm.reset()
}

const submitSellerReview = () => {
    if (sellerReviewForm.processing || !targetSeller.value || !targetOrder.value) return

    sellerReviewForm.post(
        route('buyer.seller-reviews.store', {
            order: targetOrder.value.id,
            seller: targetSeller.value.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                closeSellerReviewModal()
            },
        }
    )
}
</script>

<template>
    <Head title="My Reviews" />

    <BuyerLayout>
        <main class="min-h-[calc(100vh-80px)] w-full min-w-0 overflow-x-hidden bg-[#F8FAF9]">
            <div class="mx-auto w-full max-w-[1536px] px-4 py-6 pb-12 sm:px-6 sm:py-7 lg:px-8 2xl:px-10 lg:py-8 lg:pb-14">

                <!-- PAGE HEADER -->
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#F4B942]"></span>
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#087F8C] sm:text-xs">
                                Community & Ratings
                            </p>
                        </div>

                        <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-[#1F2937] sm:text-3xl">
                            Product & Seller Reviews
                        </h1>

                        <p class="mt-1 text-xs leading-5 text-[#64748B] sm:text-sm">
                            Manage your reviews, rate delivered purchases, and share feedback with photos and videos.
                        </p>
                    </div>

                    <Link
                        :href="route('buyer.orders')"
                        class="inline-flex w-fit items-center gap-2 rounded-xl bg-[#087F8C] px-4 py-2.5 text-xs font-extrabold text-white shadow-sm transition hover:bg-[#066B76] sm:text-sm"
                    >
                        <span><Icon name="package" class="h-4 w-4" /></span>
                        View Orders
                    </Link>
                </div>

                <!-- SUCCESS MESSAGE -->
                <div
                    v-if="status"
                    class="mt-4 flex items-start gap-2.5 rounded-xl border border-[#CDE9E4] bg-[#EAF8F1] px-3.5 py-3 text-xs font-bold text-[#17784F] sm:text-sm"
                >
                    <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12l4 4L19 6" />
                    </svg>
                    <span>{{ status }}</span>
                </div>

                <!-- TABS (BUYER-28, BUYER-29, BUYER-30) -->
                <div class="mt-5 overflow-x-auto pb-1 scrollbar-none">
                    <nav class="flex min-w-max gap-2 border-b border-[#E5E7EB] pb-2">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            :class="[
                                'inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-extrabold transition',
                                current_tab === tab.key
                                    ? 'bg-[#087F8C] text-white shadow-sm'
                                    : 'bg-white text-[#64748B] hover:bg-[#F1F5F9] hover:text-[#1F2937]'
                            ]"
                            @click="selectTab(tab.key)"
                        >
                            <span>{{ tab.label }}</span>
                            <span
                                :class="[
                                    'rounded-full px-1.5 py-0.5 text-[10px] font-bold',
                                    current_tab === tab.key
                                        ? 'bg-white/20 text-white'
                                        : 'bg-[#F1F5F9] text-[#64748B]'
                                ]"
                            >
                                {{ counts[tab.countKey] || 0 }}
                            </span>
                        </button>
                    </nav>
                </div>

                <!-- 1. MY PRODUCT REVIEWS TAB (BUYER-29, BUYER-31) -->
                <div v-if="current_tab === 'reviewed'" class="mt-5 space-y-4">
                    <div
                        v-if="reviews?.data?.length"
                        class="space-y-3"
                    >
                        <article
                            v-for="review in reviews.data"
                            :key="review.id"
                            class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm sm:p-5"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <div class="h-12 w-12 shrink-0 overflow-hidden rounded-xl bg-[#E8F7F6]">
                                        <img
                                            v-if="review.product?.image_path"
                                            :src="imageUrl(review.product.image_path)"
                                            :alt="review.product?.name"
                                            class="h-full w-full object-cover"
                                        />
                                        <div v-else class="flex h-full w-full items-center justify-center text-xl"><Icon name="bag" class="h-5 w-5" /></div>
                                    </div>

                                    <div>
                                        <Link
                                            :href="route('buyer.product.show', review.product?.id)"
                                            class="text-sm font-extrabold text-[#1F2937] hover:text-[#087F8C]"
                                        >
                                            {{ review.product?.name || 'Product' }}
                                        </Link>

                                        <!-- RATING STARS -->
                                        <div class="mt-1 flex items-center gap-1 text-sm text-[#F4B942]">
                                            <span v-for="star in 5" :key="star">
                                                {{ star <= review.rating ? '★' : '☆' }}
                                            </span>
                                            <span class="ml-1 text-[11px] text-[#94A3B8]">
                                                {{ formatDate(review.created_at) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- EDIT / DELETE BUTTONS (BUYER-29) -->
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-[#E2E8F0] px-2.5 py-1 text-xs font-bold text-[#087F8C] hover:bg-[#E8F7F6]"
                                        @click="openEditReviewModal(review)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg px-2 py-1 text-xs font-bold text-[#DC2626] hover:bg-[#FEF2F2]"
                                        @click="deleteReview(review.id)"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>

                            <!-- REVIEW COMMENT -->
                            <p v-if="review.comment" class="mt-3 text-xs leading-relaxed text-[#334155] sm:text-sm">
                                {{ review.comment }}
                            </p>

                            <!-- REVIEW MEDIA (BUYER-31) -->
                            <div
                                v-if="review.media?.length"
                                class="mt-3 flex flex-wrap gap-2"
                            >
                                <a
                                    v-for="(med, idx) in review.media"
                                    :key="idx"
                                    :href="imageUrl(med)"
                                    target="_blank"
                                    class="h-16 w-16 overflow-hidden rounded-xl border border-[#E5E7EB]"
                                >
                                    <img :src="imageUrl(med)" class="h-full w-full object-cover" alt="Review media" />
                                </a>
                            </div>

                            <!-- SELLER REPLY -->
                            <div
                                v-if="review.seller_reply"
                                class="mt-3.5 rounded-xl border border-[#D9EEEC] bg-[#F4FBFA] p-3 text-xs text-[#065F46]"
                            >
                                <div class="flex items-center justify-between font-extrabold text-[#087F8C]">
                                    <span>Seller Reply:</span>
                                    <span class="text-[10px] text-[#64748B] font-normal">{{ formatDate(review.seller_replied_at) }}</span>
                                </div>
                                <p class="mt-1 leading-relaxed">{{ review.seller_reply }}</p>
                            </div>
                        </article>
                    </div>

                    <div v-else class="rounded-2xl border border-dashed border-[#D7E0E2] bg-white p-12 text-center shadow-sm">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-[#E8F7F6] text-xl">★</div>
                        <p class="mt-3 text-sm font-extrabold text-[#1F2937]">No product reviews written yet.</p>
                        <p class="mt-1 text-xs text-[#64748B]">Delivered products will appear in the "To Review" tab.</p>
                        <button
                            type="button"
                            class="mt-4 rounded-xl bg-[#087F8C] px-4 py-2 text-xs font-bold text-white hover:bg-[#066B76]"
                            @click="selectTab('awaiting')"
                        >
                            View Products To Review
                        </button>
                    </div>
                </div>

                <!-- 2. PRODUCTS AWAITING REVIEW TAB (BUYER-28) -->
                <div v-else-if="current_tab === 'awaiting'" class="mt-5 space-y-4">
                    <div
                        v-if="awaiting_items?.data?.length"
                        class="space-y-3"
                    >
                        <article
                            v-for="item in awaiting_items.data"
                            :key="item.id"
                            class="flex flex-col gap-3 rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-5"
                        >
                            <div class="flex items-center gap-3">
                                <div class="h-12 w-12 shrink-0 overflow-hidden rounded-xl bg-[#E8F7F6]">
                                    <img
                                        v-if="item.product?.image_path"
                                        :src="imageUrl(item.product.image_path)"
                                        :alt="item.product?.name"
                                        class="h-full w-full object-cover"
                                    />
                                    <div v-else class="flex h-full w-full items-center justify-center text-xl"><Icon name="bag" class="h-5 w-5" /></div>
                                </div>

                                <div>
                                    <h4 class="text-sm font-extrabold text-[#1F2937]">
                                        {{ item.product_name || item.product?.name }}
                                    </h4>
                                    <p class="text-xs text-[#64748B]">
                                        Delivered from Order #{{ item.order?.order_number }}
                                    </p>
                                    <p v-if="item.product?.seller?.name" class="text-[11px] text-[#94A3B8]">
                                        Seller: {{ item.product.seller.name }}
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#087F8C] px-4 py-2 text-xs font-extrabold text-white shadow-sm hover:bg-[#066B76]"
                                @click="openCreateReviewModal(item)"
                            >
                                <span>★</span>
                                <span>Write Review</span>
                            </button>
                        </article>
                    </div>

                    <div v-else class="rounded-2xl border border-dashed border-[#D7E0E2] bg-white p-12 text-center shadow-sm">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-[#E8F7F6] text-xl">✓</div>
                        <p class="mt-3 text-sm font-extrabold text-[#1F2937]">No pending product reviews!</p>
                        <p class="mt-1 text-xs text-[#64748B]">You have reviewed all your delivered products so far.</p>
                    </div>
                </div>

                <!-- 3. SELLER RATINGS TAB (BUYER-30) -->
                <div v-else-if="current_tab === 'seller_reviews'" class="mt-5 space-y-5">
                    <!-- ORDERS AWAITING SELLER RATING -->
                    <div v-if="orders_awaiting_seller_review?.length" class="space-y-3">
                        <h3 class="text-xs font-extrabold uppercase tracking-wider text-[#64748B]">
                            Rate Sellers for Completed Orders
                        </h3>

                        <div
                            v-for="order in orders_awaiting_seller_review"
                            :key="order.id"
                            class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-xs font-bold text-[#1F2937]">
                                        Order #{{ order.order_number }}
                                    </p>
                                    <p class="text-[11px] text-[#94A3B8]">
                                        Delivered · {{ formatDate(order.created_at) }}
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <template v-for="item in order.items" :key="item.id">
                                        <button
                                            v-if="item.product?.seller"
                                            type="button"
                                            class="rounded-xl border border-[#087F8C] bg-[#E8F7F6] px-3 py-1.5 text-xs font-extrabold text-[#087F8C] hover:bg-[#D5F0EE]"
                                            @click="openSellerReviewModal(item.product.seller, order)"
                                        >
                                            Rate Seller: {{ item.product.seller.name }} ★
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SUBMITTED SELLER REVIEWS -->
                    <div class="space-y-3">
                        <h3 class="text-xs font-extrabold uppercase tracking-wider text-[#64748B]">
                            Submitted Seller Ratings
                        </h3>

                        <div v-if="seller_reviews?.data?.length" class="space-y-3">
                            <article
                                v-for="sr in seller_reviews.data"
                                :key="sr.id"
                                class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm sm:p-5"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h4 class="text-sm font-extrabold text-[#1F2937]">
                                            Seller: {{ sr.seller?.name || 'Seller' }}
                                        </h4>
                                        <p class="text-xs text-[#94A3B8]">
                                            Order #{{ sr.order?.order_number }}
                                        </p>
                                    </div>

                                    <div class="flex items-center gap-1 text-sm text-[#F4B942]">
                                        <span v-for="star in 5" :key="star">{{ star <= sr.rating ? '★' : '☆' }}</span>
                                    </div>
                                </div>

                                <p v-if="sr.comment" class="mt-2 text-xs leading-relaxed text-[#334155] sm:text-sm">
                                    {{ sr.comment }}
                                </p>

                                <div
                                    v-if="sr.seller_reply"
                                    class="mt-3 rounded-xl border border-[#D9EEEC] bg-[#F4FBFA] p-3 text-xs text-[#065F46]"
                                >
                                    <p class="font-bold text-[#087F8C]">Seller Reply:</p>
                                    <p class="mt-0.5">{{ sr.seller_reply }}</p>
                                </div>
                            </article>
                        </div>

                        <div v-else-if="!orders_awaiting_seller_review?.length" class="rounded-2xl border border-dashed border-[#D7E0E2] bg-white p-12 text-center shadow-sm">
                            <p class="text-sm font-extrabold text-[#1F2937]">No seller reviews yet.</p>
                            <p class="mt-1 text-xs text-[#64748B]">You will be able to rate sellers after completing deliveries.</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- WRITE / EDIT PRODUCT REVIEW MODAL (BUYER-28, BUYER-29, BUYER-31) -->
        <div
            v-if="showProductReviewModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#0F172A]/50 p-4 backdrop-blur-sm"
            @click.self="closeProductReviewModal"
        >
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-2xl sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-extrabold text-[#1F2937]">
                            {{ editingReview ? 'Edit Your Review' : 'Write Product Review' }}
                        </h3>
                        <p class="mt-1 text-xs text-[#64748B]">
                            {{ targetProduct?.name }}
                        </p>
                    </div>
                    <button type="button" class="text-xl text-[#94A3B8] hover:text-[#475569]" @click="closeProductReviewModal">✕</button>
                </div>

                <form class="mt-4 space-y-4" @submit.prevent="submitProductReview">
                    <!-- RATING STARS -->
                    <div>
                        <label class="block text-xs font-bold text-[#475569]">Rating</label>
                        <div class="mt-2 flex gap-1">
                            <button
                                v-for="star in 5"
                                :key="star"
                                type="button"
                                class="flex h-9 w-9 items-center justify-center rounded-xl text-2xl transition hover:bg-[#FFF9EC]"
                                :class="star <= productReviewForm.rating ? 'text-[#F4B942]' : 'text-[#D7DDE0]'"
                                @click="productReviewForm.rating = star"
                            >
                                ★
                            </button>
                        </div>
                    </div>

                    <!-- COMMENT -->
                    <div>
                        <label class="block text-xs font-bold text-[#475569]">Review Details</label>
                        <textarea
                            v-model="productReviewForm.comment"
                            rows="3"
                            placeholder="How is the quality, packaging, and fit? Share your thoughts..."
                            class="mt-1.5 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-3 text-xs text-[#1F2937] placeholder:text-[#94A3B8] focus:border-[#16A6A0] focus:ring-2 focus:ring-[#E8F7F6]"
                        ></textarea>
                        <p v-if="productReviewForm.errors.comment" class="mt-1 text-xs text-[#DC2626]">
                            {{ productReviewForm.errors.comment }}
                        </p>
                    </div>

                    <!-- MEDIA UPLOAD (BUYER-31) -->
                    <div>
                        <label class="block text-xs font-bold text-[#475569]">Attach Photos or Videos (Max 5)</label>
                        <input
                            type="file"
                            multiple
                            accept="image/*,video/*"
                            class="mt-1.5 w-full text-xs text-[#64748B] file:mr-3 file:rounded-xl file:border-0 file:bg-[#E8F7F6] file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-[#087F8C]"
                            @change="handleMediaUpload"
                        />

                        <!-- EXISTING MEDIA (FOR EDITING) -->
                        <div v-if="productReviewForm.existing_media?.length" class="mt-2.5">
                            <p class="text-[10px] font-bold text-[#64748B]">Existing Photos:</p>
                            <div class="mt-1 flex flex-wrap gap-2">
                                <div
                                    v-for="(med, idx) in productReviewForm.existing_media"
                                    :key="idx"
                                    class="relative h-14 w-14 overflow-hidden rounded-xl border border-[#E5E7EB]"
                                >
                                    <img :src="imageUrl(med)" class="h-full w-full object-cover" alt="Media" />
                                    <button
                                        type="button"
                                        class="absolute right-0.5 top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-black/60 text-[9px] text-white"
                                        @click="removeExistingMedia(idx)"
                                    >
                                        ✕
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- NEW PREVIEWS -->
                        <div v-if="mediaPreviews.length" class="mt-2.5 flex flex-wrap gap-2">
                            <div
                                v-for="(preview, idx) in mediaPreviews"
                                :key="idx"
                                class="h-14 w-14 overflow-hidden rounded-xl border border-[#E5E7EB]"
                            >
                                <img :src="preview" class="h-full w-full object-cover" alt="Preview" />
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button
                            type="button"
                            :disabled="productReviewForm.processing"
                            class="flex-1 rounded-xl border border-[#E5E7EB] px-4 py-2.5 text-xs font-bold text-[#64748B] hover:bg-[#F8FAF9]"
                            @click="closeProductReviewModal"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="productReviewForm.processing"
                            class="flex-1 rounded-xl bg-[#087F8C] px-4 py-2.5 text-xs font-bold text-white hover:bg-[#066B76] disabled:opacity-50"
                        >
                            {{ productReviewForm.processing ? 'Saving...' : editingReview ? 'Update Review' : 'Submit Review' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- RATE SELLER MODAL (BUYER-30) -->
        <div
            v-if="showSellerReviewModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#0F172A]/50 p-4 backdrop-blur-sm"
            @click.self="closeSellerReviewModal"
        >
            <div class="w-full max-w-md rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-2xl sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-extrabold text-[#1F2937]">Rate Seller</h3>
                        <p class="mt-1 text-xs text-[#64748B]">
                            {{ targetSeller?.name }} · Order #{{ targetOrder?.order_number }}
                        </p>
                    </div>
                    <button type="button" class="text-xl text-[#94A3B8] hover:text-[#475569]" @click="closeSellerReviewModal">✕</button>
                </div>

                <form class="mt-4 space-y-4" @submit.prevent="submitSellerReview">
                    <div>
                        <label class="block text-xs font-bold text-[#475569]">Service & Packaging Rating</label>
                        <div class="mt-2 flex gap-1">
                            <button
                                v-for="star in 5"
                                :key="star"
                                type="button"
                                class="flex h-9 w-9 items-center justify-center rounded-xl text-2xl transition hover:bg-[#FFF9EC]"
                                :class="star <= sellerReviewForm.rating ? 'text-[#F4B942]' : 'text-[#D7DDE0]'"
                                @click="sellerReviewForm.rating = star"
                            >
                                ★
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#475569]">Feedback for Seller</label>
                        <textarea
                            v-model="sellerReviewForm.comment"
                            rows="3"
                            placeholder="Share your experience with the seller's service, packaging, and responsiveness..."
                            class="mt-1.5 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-3 text-xs text-[#1F2937] placeholder:text-[#94A3B8] focus:border-[#16A6A0] focus:ring-2 focus:ring-[#E8F7F6]"
                        ></textarea>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button
                            type="button"
                            :disabled="sellerReviewForm.processing"
                            class="flex-1 rounded-xl border border-[#E5E7EB] px-4 py-2.5 text-xs font-bold text-[#64748B] hover:bg-[#F8FAF9]"
                            @click="closeSellerReviewModal"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="sellerReviewForm.processing"
                            class="flex-1 rounded-xl bg-[#087F8C] px-4 py-2.5 text-xs font-bold text-white hover:bg-[#066B76] disabled:opacity-50"
                        >
                            {{ sellerReviewForm.processing ? 'Submitting...' : 'Submit Rating' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </BuyerLayout>
</template>
