<script setup>
import { ref } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
})

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const money = value => {
    const amount = Number(value ?? 0)

    return `₱${amount.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`
}

const formatDate = value => {
    if (!value) {
        return 'Date unavailable'
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return 'Date unavailable'
    }

    return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}

const formatPaymentMethod = value => {
    if (!value) {
        return 'Payment method unavailable'
    }

    const methods = {
        cod: 'Cash on delivery',
        cash_on_delivery: 'Cash on delivery',
    }

    return methods[value] || value
}

const normalizeStatus = value => {
    return String(value ?? '').toLowerCase()
}

const getStatusBadge = value => {
    const status = normalizeStatus(value)

    const badges = {
        pending: {
            label: 'To Pack',
            color: 'bg-[#FFF7E6] text-[#A66A00] ring-1 ring-[#F4B942]/30',
        },
        processing: {
            label: 'Processing',
            color: 'bg-[#E8F7F6] text-[#087F8C] ring-1 ring-[#16A6A0]/20',
        },
        packed: {
            label: 'To Ship',
            color: 'bg-[#E8F7F6] text-[#087F8C] ring-1 ring-[#16A6A0]/20',
        },
        shipped: {
            label: 'In Transit',
            color: 'bg-[#F0EAFE] text-[#6D4BC3] ring-1 ring-[#8B5CF6]/20',
        },
        out_for_delivery: {
            label: 'Out for Delivery',
            color: 'bg-[#FFF3E8] text-[#C56A20] ring-1 ring-[#F97316]/20',
        },
        delivered: {
            label: 'Delivered',
            color: 'bg-[#EAF8F1] text-[#17784F] ring-1 ring-[#22A06B]/20',
        },
        completed: {
            label: 'Completed',
            color: 'bg-[#EAF8F1] text-[#17784F] ring-1 ring-[#22A06B]/20',
        },
        cancelled: {
            label: 'Cancelled',
            color: 'bg-[#FDECEC] text-[#B94242] ring-1 ring-[#E85D5D]/20',
        },
        refunded: {
            label: 'Refunded',
            color: 'bg-[#FDECEC] text-[#B94242] ring-1 ring-[#E85D5D]/20',
        },
        failed: {
            label: 'Payment Failed',
            color: 'bg-[#FDECEC] text-[#B94242] ring-1 ring-[#E85D5D]/20',
        },
    }

    return badges[status] || {
        label: value || 'Unknown',
        color: 'bg-[#F8FAF9] text-[#64748B] ring-1 ring-[#E5E7EB]',
    }
}

const getItemTotal = item => {
    return Number(item?.price ?? 0) * Number(item?.quantity ?? 0)
}

const canConfirmDelivery = item => {
    return normalizeStatus(item?.status) === 'shipped'
}

const canReview = item => {
    const status = normalizeStatus(item?.status)

    return status === 'delivered' || status === 'completed'
}

const hasTracking = item => {
    const status = normalizeStatus(item?.status)

    return Boolean(item?.tracking_number) &&
        ['shipped', 'out_for_delivery'].includes(status)
}

/*
|--------------------------------------------------------------------------
| Review
|--------------------------------------------------------------------------
*/

const showReviewModal = ref(false)
const reviewingItem = ref(null)

const reviewForm = useForm({
    rating: 5,
    comment: '',
})

const openReviewModal = item => {
    if (!item?.product_id) {
        return
    }

    reviewingItem.value = item
    reviewForm.rating = 5
    reviewForm.comment = ''
    reviewForm.clearErrors()

    showReviewModal.value = true
}

const closeReviewModal = () => {
    if (reviewForm.processing) {
        return
    }

    showReviewModal.value = false
    reviewingItem.value = null
    reviewForm.reset()
    reviewForm.rating = 5
}

const submitReview = () => {
    if (
        reviewForm.processing ||
        !reviewingItem.value?.product_id
    ) {
        return
    }

    reviewForm.post(
        route(
            'buyer.reviews.store',
            reviewingItem.value.product_id
        ),
        {
            preserveScroll: true,

            onSuccess: () => {
                closeReviewModal()
            },
        }
    )
}

/*
|--------------------------------------------------------------------------
| Delivery Confirmation
|--------------------------------------------------------------------------
*/

const confirmingItemId = ref(null)

const confirmDelivery = itemId => {
    if (!itemId || confirmingItemId.value) {
        return
    }

    if (!confirm('Confirm that you have received this item?')) {
        return
    }

    confirmingItemId.value = itemId

    router.patch(
        route(
            'buyer.order-items.confirm-delivery',
            itemId
        ),
        {},
        {
            preserveScroll: true,

            onFinish: () => {
                confirmingItemId.value = null
            },
        }
    )
}
</script>

<template>
    <Head :title="`Order ${order.order_number || order.id}`" />

    <BuyerLayout>
        <main class="min-h-[calc(100vh-80px)] w-full min-w-0 overflow-x-hidden bg-[#F8FAF9]">
            <div class="mx-auto w-full max-w-6xl px-3 py-4 pb-8 sm:px-5 sm:py-5 lg:px-6 lg:py-6 lg:pb-10">

                <!-- BACK -->
                <Link
                    :href="route('buyer.orders')"
                    class="inline-flex items-center gap-2 text-xs font-bold text-[#087F8C] transition hover:text-[#066B76] sm:text-sm"
                >
                    <span class="text-base">←</span>
                    Back to orders
                </Link>

                <!-- ORDER HERO -->
                <section class="mt-2.5 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-[0_8px_30px_rgba(15,23,42,0.05)] sm:mt-5">
                    <div class="p-3.5 sm:p-4 lg:p-6">
                        <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-[#F4B942]"></span>
                                    <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#087F8C] sm:text-xs">
                                        Order details
                                    </p>
                                </div>

                                <h1 class="mt-2 break-all text-xl font-extrabold tracking-tight text-[#1F2937] sm:text-2xl">
                                    {{ order.order_number || `Order #${order.id}` }}
                                </h1>

                                <p class="mt-1.5 text-xs text-[#64748B] sm:text-sm">
                                    Placed on {{ formatDate(order.created_at) }}
                                </p>
                            </div>

                            <span
                                :class="[
                                    'inline-flex w-fit shrink-0 items-center rounded-full px-3 py-1.5 text-[10px] font-extrabold sm:text-xs',
                                    getStatusBadge(order.status).color
                                ]"
                            >
                                {{ getStatusBadge(order.status).label }}
                            </span>
                        </div>

                        <!-- ORDER ITEMS -->
                        <div
                            v-if="order.items?.length"
                            class="mt-4 border-t border-[#EEF1F2] pt-4"
                        >
                            <div class="mb-2.5 flex items-center justify-between">
                                <h2 class="text-sm font-extrabold text-[#1F2937] sm:text-base">
                                    Order items
                                </h2>
                                <span class="text-[10px] font-semibold text-[#94A3B8] sm:text-xs">
                                    {{ order.items.length }} {{ order.items.length === 1 ? 'item' : 'items' }}
                                </span>
                            </div>

                            <div class="space-y-3">
                                <article
                                    v-for="item in order.items"
                                    :key="item.id"
                                    class="rounded-2xl border border-[#E5E7EB] bg-[#FCFDFC] p-3.5 transition hover:border-[#D6E9E8] sm:p-4"
                                >
                                    <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex min-w-0 items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <p class="text-sm font-extrabold text-[#1F2937] sm:text-base">
                                                        {{ item.product_name || 'Product' }}
                                                    </p>

                                                    <p
                                                        v-if="item.variant_label"
                                                        class="mt-1 text-[10px] font-medium text-[#94A3B8] sm:text-xs"
                                                    >
                                                        {{ item.variant_label }}
                                                    </p>

                                                    <p class="mt-1.5 text-xs text-[#64748B]">
                                                        {{ money(item.price) }}
                                                        <span class="mx-1 text-[#CBD5E1]">×</span>
                                                        {{ item.quantity || 0 }}
                                                    </p>
                                                </div>

                                                <p class="shrink-0 text-sm font-extrabold text-[#1F2937] sm:text-base">
                                                    {{ money(getItemTotal(item)) }}
                                                </p>
                                            </div>

                                            <!-- ITEM STATUS -->
                                            <div class="mt-2.5">
                                                <span
                                                    :class="[
                                                        'inline-flex rounded-full px-2.5 py-1 text-[9px] font-extrabold sm:text-[10px]',
                                                        getStatusBadge(item.status || order.status).color
                                                    ]"
                                                >
                                                    {{ getStatusBadge(item.status || order.status).label }}
                                                </span>
                                            </div>

                                            <!-- TRACKING -->
                                            <div
                                                v-if="hasTracking(item)"
                                                class="mt-2.5 rounded-xl border border-[#D9F0EF] bg-[#E8F7F6] px-3 py-2.5"
                                            >
                                                <div class="flex items-start gap-2.5">
                                                    <div class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white text-[#087F8C]">
                                                        <svg
                                                            class="h-4 w-4"
                                                            viewBox="0 0 24 24"
                                                            fill="none"
                                                            stroke="currentColor"
                                                            stroke-width="1.7"
                                                        >
                                                            <path
                                                                stroke-linecap="round"
                                                                stroke-linejoin="round"
                                                                d="M3 7h11v10H3zM14 10h4l3 3v4h-7zM7 17a2 2 0 104 0m6 0a2 2 0 104 0"
                                                            />
                                                        </svg>
                                                    </div>

                                                    <div class="min-w-0">
                                                        <p class="text-[10px] font-extrabold text-[#087F8C] sm:text-xs">
                                                            Tracking information
                                                        </p>
                                                        <p class="mt-0.5 break-all text-[11px] text-[#42767A] sm:text-xs">
                                                            <span class="font-bold">
                                                                {{ item.courier_name || 'Courier' }}:
                                                            </span>
                                                            {{ item.tracking_number }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- ACTIONS -->
                                            <div
                                                v-if="canConfirmDelivery(item) || canReview(item)"
                                                class="mt-2.5 flex flex-wrap gap-2"
                                            >
                                                <button
                                                    v-if="canConfirmDelivery(item)"
                                                    type="button"
                                                    :disabled="confirmingItemId === item.id"
                                                    class="inline-flex items-center justify-center rounded-xl bg-[#087F8C] px-4 py-2.5 text-[11px] font-extrabold text-white transition hover:bg-[#066B76] disabled:cursor-not-allowed disabled:opacity-60 sm:text-xs"
                                                    @click="confirmDelivery(item.id)"
                                                >
                                                    {{
                                                        confirmingItemId === item.id
                                                            ? 'Confirming...'
                                                            : '✓ Confirm Delivery'
                                                    }}
                                                </button>

                                                <button
                                                    v-if="canReview(item)"
                                                    type="button"
                                                    class="inline-flex items-center justify-center rounded-xl border border-[#E8D49E] bg-[#FFF9EC] px-4 py-2.5 text-[11px] font-extrabold text-[#9A6800] transition hover:bg-[#FFF4D8] sm:text-xs"
                                                    @click="openReviewModal(item)"
                                                >
                                                    ★ Write Review
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        </div>

                        <!-- EMPTY ITEMS -->
                        <div
                            v-else
                            class="mt-6 rounded-2xl border border-dashed border-[#DDE3E5] bg-[#F8FAF9] px-5 py-10 text-center"
                        >
                            <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]">
                                <svg
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M7 4h10a2 2 0 012 2v14H5V6a2 2 0 012-2zm3 0V2h4v2m-4 5h4m-4 4h4"
                                    />
                                </svg>
                            </div>

                            <p class="mt-2.5 text-xs font-semibold text-[#64748B]">
                                No order items are available.
                            </p>
                        </div>

                        <!-- DELIVERY & PAYMENT -->
                        <div class="mt-4 grid gap-3 border-t border-[#EEF1F2] pt-4 sm:grid-cols-2">
                            <div class="rounded-2xl border border-[#E5E7EB] bg-[#FCFDFC] p-3.5 sm:p-4">
                                <div class="flex items-center gap-2">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]">
                                        <svg
                                            class="h-4 w-4"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.7"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M3 10.5L12 4l9 6.5M5 9.5V20h14V9.5M9 20v-6h6v6"
                                            />
                                        </svg>
                                    </div>
                                    <p class="text-[10px] font-extrabold uppercase tracking-[0.12em] text-[#64748B] sm:text-xs">
                                        Delivery address
                                    </p>
                                </div>

                                <p class="mt-2.5 text-xs leading-5 text-[#475569] sm:text-sm">
                                    {{ order.shipping_address || 'No shipping address available.' }}
                                </p>
                            </div>

                            <div class="rounded-2xl border border-[#E5E7EB] bg-[#FCFDFC] p-3.5 sm:p-4">
                                <div class="flex items-center gap-2">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#FFF7E6] text-[#B47A08]">
                                        <svg
                                            class="h-4 w-4"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.7"
                                        >
                                            <rect
                                                x="3"
                                                y="5"
                                                width="18"
                                                height="14"
                                                rx="2"
                                            />
                                            <path
                                                stroke-linecap="round"
                                                d="M3 10h18"
                                            />
                                        </svg>
                                    </div>
                                    <p class="text-[10px] font-extrabold uppercase tracking-[0.12em] text-[#64748B] sm:text-xs">
                                        Payment
                                    </p>
                                </div>

                                <p class="mt-2.5 text-xs text-[#475569] sm:text-sm">
                                    {{ formatPaymentMethod(order.payment_method) }}
                                </p>

                                <div class="mt-4 border-t border-[#EEF1F2] pt-3">
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-[#94A3B8]">
                                        Order total
                                    </p>
                                    <p class="mt-1 text-lg font-extrabold text-[#087F8C] sm:text-xl">
                                        {{ money(order.total) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- MESSAGE SELLER -->
                        <div
                            v-if="order.items?.length && order.items[0]?.product_id"
                            class="mt-4 border-t border-[#EEF1F2] pt-4"
                        >
                            <Link
                                :href="route(
                                    'buyer.conversations.start',
                                    order.items[0].product_id
                                )"
                                method="post"
                                as="button"
                                class="group inline-flex w-full items-center justify-center gap-2.5 rounded-xl border border-[#CFE5E4] bg-[#F3FBFA] px-4 py-3 text-xs font-extrabold text-[#087F8C] transition hover:border-[#16A6A0] hover:bg-[#E8F7F6] sm:text-sm"
                            >
                                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-white text-[#087F8C] shadow-sm">
                                    <svg
                                        class="h-4 w-4"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.7"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M21 11.5a8.5 8.5 0 01-9 8.5 9.8 9.8 0 01-4-.8L3 21l1.8-4.3A8.3 8.3 0 013 11.5 8.5 8.5 0 0112 3a8.5 8.5 0 019 8.5z"
                                        />
                                    </svg>
                                </span>
                                Message Seller
                            </Link>
                        </div>
                    </div>
                </section>
            </div>
        </main>

        <!-- REVIEW MODAL -->
        <div
            v-if="showReviewModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#0F172A]/55 p-4 backdrop-blur-[2px]"
            @click.self="closeReviewModal"
        >
            <div class="max-h-[88vh] w-full max-w-md overflow-y-auto rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-2xl sm:p-6">
                <!-- MODAL HEADER -->
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#F4B942]"></span>
                            <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#087F8C]">
                                Your feedback
                            </p>
                        </div>

                        <h2 class="mt-1.5 text-xl font-extrabold text-[#1F2937]">
                            Write a Review
                        </h2>

                        <p class="mt-1 line-clamp-2 text-xs text-[#64748B]">
                            {{ reviewingItem?.product_name }}
                        </p>
                    </div>

                    <button
                        type="button"
                        :disabled="reviewForm.processing"
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xl text-[#94A3B8] transition hover:bg-[#F8FAF9] hover:text-[#475569] disabled:opacity-50"
                        aria-label="Close review modal"
                        @click="closeReviewModal"
                    >
                        ×
                    </button>
                </div>

                <!-- REVIEW FORM -->
                <form
                    class="mt-6 space-y-5"
                    @submit.prevent="submitReview"
                >
                    <!-- RATING -->
                    <div>
                        <label class="block text-xs font-extrabold text-[#475569] sm:text-sm">
                            Rating
                        </label>

                        <div class="mt-2.5 flex gap-1">
                            <button
                                v-for="star in 5"
                                :key="star"
                                type="button"
                                class="flex h-10 w-10 items-center justify-center rounded-xl text-2xl transition hover:bg-[#FFF9EC]"
                                :class="
                                    star <= reviewForm.rating
                                        ? 'text-[#F4B942]'
                                        : 'text-[#D7DDE0]'
                                "
                                :aria-label="`${star} star rating`"
                                @click="reviewForm.rating = star"
                            >
                                ★
                            </button>
                        </div>

                        <p
                            v-if="reviewForm.errors.rating"
                            class="mt-1 text-xs font-medium text-[#E85D5D]"
                        >
                            {{ reviewForm.errors.rating }}
                        </p>
                    </div>

                    <!-- COMMENT -->
                    <div>
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-extrabold text-[#475569] sm:text-sm">
                                Your Review
                                <span class="font-medium text-[#94A3B8]">(Optional)</span>
                            </label>
                            <span class="text-[10px] text-[#94A3B8]">
                                {{ reviewForm.comment.length }}/1000
                            </span>
                        </div>

                        <textarea
                            v-model="reviewForm.comment"
                            rows="4"
                            maxlength="1000"
                            placeholder="Share your experience with this product..."
                            class="mt-2 w-full resize-none rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-xs leading-5 text-[#1F2937] outline-none transition placeholder:text-[#A0ACB8] focus:border-[#16A6A0] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6] sm:text-sm"
                        ></textarea>

                        <p
                            v-if="reviewForm.errors.comment"
                            class="mt-1 text-xs font-medium text-[#E85D5D]"
                        >
                            {{ reviewForm.errors.comment }}
                        </p>
                    </div>

                    <!-- BUTTONS -->
                    <div class="flex gap-3">
                        <button
                            type="button"
                            :disabled="reviewForm.processing"
                            class="flex-1 rounded-xl border border-[#E5E7EB] bg-white px-4 py-2.5 text-xs font-extrabold text-[#475569] transition hover:bg-[#F8FAF9] disabled:opacity-50 sm:text-sm"
                            @click="closeReviewModal"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            :disabled="reviewForm.processing"
                            class="flex-1 rounded-xl bg-[#087F8C] px-4 py-2.5 text-xs font-extrabold text-white transition hover:bg-[#066B76] disabled:cursor-not-allowed disabled:opacity-60 sm:text-sm"
                        >
                            {{
                                reviewForm.processing
                                    ? 'Submitting...'
                                    : 'Submit Review'
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </BuyerLayout>
</template>
