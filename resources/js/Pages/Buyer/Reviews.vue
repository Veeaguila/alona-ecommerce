<!-- Buyer review history populated from database reviews. -->
<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'

const props = defineProps({
    reviews: {
        type: [Array, Object],
        default: () => [],
    },
})

/*
|--------------------------------------------------------------------------
| Review List
|--------------------------------------------------------------------------
| Supports both:
| - Normal array
| - Laravel paginator object with `data`
|--------------------------------------------------------------------------
*/

const reviewList = computed(() => {
    if (Array.isArray(props.reviews)) {
        return props.reviews
    }

    if (
        props.reviews?.data &&
        Array.isArray(props.reviews.data)
    ) {
        return props.reviews.data
    }

    return []
})

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const ratingValue = rating => {
    const value = Number(rating ?? 0)

    return Math.min(
        5,
        Math.max(0, Math.round(value))
    )
}

const stars = rating => {
    const value = ratingValue(rating)

    return (
        '★'.repeat(value) +
        '☆'.repeat(5 - value)
    )
}

const formatDate = value => {
    if (!value) {
        return ''
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return ''
    }

    return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}
</script>

<template>
    <Head title="Reviews" />

    <BuyerLayout>
        <main class="min-h-[calc(100vh-80px)] w-full min-w-0 overflow-x-hidden bg-[#F8FAF9]">
            <div class="mx-auto w-full max-w-6xl px-3 py-4 pb-8 sm:px-5 sm:py-5 lg:px-6 lg:py-6 lg:pb-10">

                <!-- PAGE HEADER -->
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#F4B942]"></span>
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#087F8C] sm:text-xs">
                                Your feedback
                            </p>
                        </div>

                        <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-[#1F2937] sm:text-3xl">
                            Reviews
                        </h1>

                        <p class="mt-1 text-xs leading-5 text-[#64748B] sm:text-sm">
                            View the feedback you have shared about your purchases.
                        </p>
                    </div>

                    <Link
                        :href="route('buyer.products')"
                        class="inline-flex w-fit items-center gap-2 rounded-xl bg-[#087F8C] px-4 py-2.5 text-xs font-extrabold text-white shadow-sm transition hover:bg-[#066B76] sm:text-sm"
                    >
                        <span class="text-base leading-none">+</span>
                        Shop Products
                    </Link>
                </div>

                <!-- REVIEWS LIST -->
                <div
                    v-if="reviewList.length"
                    class="mt-5 space-y-3 sm:mt-6"
                >
                    <article
                        v-for="review in reviewList"
                        :key="review.id"
                        class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-[0_6px_22px_rgba(15,23,42,0.04)] transition hover:border-[#CFE5E4] hover:shadow-[0_10px_30px_rgba(15,23,42,0.07)] sm:p-5"
                    >
                        <!-- REVIEW HEADER -->
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-[#E8F7F6] text-[#087F8C]">
                                    <img
                                        v-if="review.product?.image_path"
                                        :src="review.product.image_path"
                                        :alt="review.product?.name || 'Product'"
                                        class="h-full w-full object-cover"
                                    />

                                    <svg
                                        v-else
                                        class="h-5 w-5"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.7"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M6 3h12l2 5H4l2-5zm-2 5h16v11a2 2 0 01-2 2H6a2 2 0 01-2-2V8zm4 4h8"
                                        />
                                    </svg>
                                </div>

                                <div class="min-w-0">
                                    <h2 class="truncate text-sm font-extrabold text-[#1F2937] sm:text-base">
                                        {{ review.product?.name || 'Product' }}
                                    </h2>

                                    <p
                                        v-if="review.created_at"
                                        class="mt-1 text-[10px] text-[#94A3B8] sm:text-xs"
                                    >
                                        Reviewed {{ formatDate(review.created_at) }}
                                    </p>
                                </div>
                            </div>

                            <!-- RATING -->
                            <div class="shrink-0 text-right">
                                <div
                                    class="text-base leading-none tracking-[0.08em] text-[#F4B942] sm:text-lg"
                                    :aria-label="`${ratingValue(review.rating)} out of 5 stars`"
                                >
                                    {{ stars(review.rating) }}
                                </div>

                                <span class="mt-1 block text-[9px] font-bold text-[#94A3B8]">
                                    {{ ratingValue(review.rating) }}/5
                                </span>
                            </div>
                        </div>

                        <!-- COMMENT -->
                        <div class="mt-4 rounded-xl bg-[#F8FAF9] px-3.5 py-3">
                            <div class="mb-1.5 flex items-center gap-1.5">
                                <span class="h-1.5 w-1.5 rounded-full bg-[#F4B942]"></span>
                                <span class="text-[9px] font-extrabold uppercase tracking-[0.12em] text-[#087F8C]">
                                    Your review
                                </span>
                            </div>

                            <p class="text-xs leading-5 text-[#475569] sm:text-sm sm:leading-6">
                                {{ review.comment || 'No comment added.' }}
                            </p>
                        </div>

                        <!-- PRODUCT LINK -->
                        <div
                            v-if="review.product?.id"
                            class="mt-3 flex justify-end"
                        >
                            <Link
                                :href="route('buyer.product', review.product.id)"
                                class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[10px] font-extrabold text-[#087F8C] transition hover:bg-[#E8F7F6] sm:text-xs"
                            >
                                View product
                                <span class="text-sm">→</span>
                            </Link>
                        </div>
                    </article>
                </div>

                <!-- EMPTY STATE -->
                <div
                    v-else
                    class="mt-5 rounded-2xl border border-dashed border-[#D7E0E2] bg-white px-5 py-14 text-center shadow-[0_6px_22px_rgba(15,23,42,0.03)] sm:mt-6 sm:py-16"
                >
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-[#FFF7E6] text-[#F4B942]">
                        <svg
                            class="h-7 w-7"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <path d="M12 2.8l2.78 5.63 6.22.9-4.5 4.39 1.06 6.2L12 17.01l-5.56 2.91 1.06-6.2L3 9.33l6.22-.9L12 2.8z" />
                        </svg>
                    </div>

                    <p class="mt-4 text-sm font-extrabold text-[#1F2937]">
                        You have not reviewed a product yet.
                    </p>

                    <p class="mx-auto mt-1.5 max-w-sm text-xs leading-5 text-[#64748B] sm:text-sm">
                        Your reviews will appear here after you submit feedback for a delivered order.
                    </p>

                    <Link
                        :href="route('buyer.products')"
                        class="mt-5 inline-flex items-center gap-2 rounded-xl bg-[#087F8C] px-4 py-2.5 text-xs font-extrabold text-white transition hover:bg-[#066B76] sm:text-sm"
                    >
                        Browse Products
                        <span>→</span>
                    </Link>
                </div>

                <p
                    v-if="reviewList.length"
                    class="mt-4 text-center text-[9px] text-[#A0ACB8] sm:text-[10px]"
                >
                    Your feedback helps other shoppers make better choices.
                </p>
            </div>
        </main>
    </BuyerLayout>
</template>
