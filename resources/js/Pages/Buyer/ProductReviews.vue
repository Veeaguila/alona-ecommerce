<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'

const props = defineProps({
    product: {
        type: Object,
        default: () => ({
            id: null,
            name: '',
            image_path: null,
            rating: 0,
            reviews_count: 0,
        }),
    },

    reviews: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
            total: 0,
        }),
    },
})

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

const averageRating = computed(() => {
    return Number(props.product?.rating ?? 0)
})

const reviewCount = computed(() => {
    return Number(
        props.product?.reviews_count ??
        props.reviews?.total ??
        0
    )
})

const stars = rating => {
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

    return new Date(date).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    })
}

const initials = name => {
    if (!name) {
        return 'A'
    }

    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map(part => part.charAt(0).toUpperCase())
        .join('')
}
</script>

<template>
    <Head :title="`${product.name} Reviews`" />

    <BuyerLayout>
        <main
            class="min-h-[calc(100vh-80px)] w-full min-w-0 overflow-x-hidden bg-[#F8FAF9]"
        >
            <div
                class="mx-auto w-full max-w-5xl px-4 py-5 sm:px-6 sm:py-7 lg:px-8"
            >

                <!-- BACK -->
                <Link
                    :href="route('buyer.product', product.id)"
                    class="inline-flex items-center gap-2 text-xs font-semibold text-[#087F8C] transition hover:text-[#16A6A0]"
                >
                    <svg
                        class="h-4 w-4"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M15 19l-7-7 7-7"
                        />
                    </svg>

                    Back to product
                </Link>


                <!-- PRODUCT HEADER -->
                <section
                    class="mt-4 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                >
                    <div
                        class="flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:p-6"
                    >

                        <!-- PRODUCT IMAGE -->
                        <div
                            class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-[#F8FAF9] sm:h-28 sm:w-28"
                        >
                            <img
                                v-if="product.image_path"
                                :src="imageUrl(product.image_path)"
                                :alt="product.name"
                                class="h-full w-full object-contain"
                            />

                            <span
                                v-else
                                class="text-3xl"
                            >
                                🛍️
                            </span>
                        </div>


                        <!-- PRODUCT INFO -->
                        <div class="min-w-0 flex-1">

                            <p
                                class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#087F8C]"
                            >
                                Customer reviews
                            </p>

                            <h1
                                class="mt-1 text-lg font-bold text-[#1F2937] sm:text-xl"
                            >
                                {{ product.name }}
                            </h1>


                            <!-- RATING -->
                            <div
                                class="mt-2 flex flex-wrap items-center gap-2"
                            >
                                <div class="flex items-center">
                                    <span
                                        v-for="(filled, index) in stars(averageRating)"
                                        :key="index"
                                        class="text-lg leading-none"
                                        :class="
                                            filled
                                                ? 'text-[#F4B942]'
                                                : 'text-[#CBD5E1]'
                                        "
                                    >
                                        ★
                                    </span>
                                </div>

                                <span
                                    class="text-sm font-bold text-[#1F2937]"
                                >
                                    {{ averageRating.toFixed(2) }}
                                </span>

                                <span class="text-sm text-[#64748B]">
                                    ·
                                    {{ reviewCount }}
                                    {{ reviewCount === 1 ? 'review' : 'reviews' }}
                                </span>
                            </div>

                        </div>

                    </div>
                </section>


                <!-- REVIEW LIST -->
                <section class="mt-6">

                    <div
                        class="mb-3 flex items-end justify-between gap-3"
                    >
                        <div>
                            <p
                                class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#087F8C]"
                            >
                                Reviews
                            </p>

                            <h2
                                class="mt-0.5 text-base font-bold text-[#1F2937]"
                            >
                                What customers say
                            </h2>
                        </div>

                        <span
                            class="text-xs text-[#64748B]"
                        >
                            {{ reviewCount }} total
                        </span>
                    </div>


                    <!-- REVIEWS -->
                    <div
                        v-if="reviews.data?.length"
                        class="space-y-3"
                    >

                        <article
                            v-for="review in reviews.data"
                            :key="review.id"
                            class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm sm:p-5"
                        >

                            <div
                                class="flex items-start gap-3"
                            >

                                <!-- AVATAR -->
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#E8F7F6] text-xs font-bold text-[#087F8C]"
                                >
                                    {{ initials(review.user?.name) }}
                                </div>


                                <div class="min-w-0 flex-1">

                                    <!-- NAME / DATE -->
                                    <div
                                        class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <p
                                            class="truncate text-sm font-bold text-[#1F2937]"
                                        >
                                            {{ review.user?.name || 'Alona Customer' }}
                                        </p>

                                        <span
                                            class="text-[10px] text-[#94A3B8]"
                                        >
                                            {{ reviewDate(review.created_at) }}
                                        </span>
                                    </div>


                                    <!-- REVIEW STARS -->
                                    <div
                                        class="mt-1 flex items-center"
                                    >
                                        <span
                                            v-for="(filled, index) in stars(review.rating)"
                                            :key="index"
                                            class="text-sm leading-none"
                                            :class="
                                                filled
                                                    ? 'text-[#F4B942]'
                                                    : 'text-[#CBD5E1]'
                                            "
                                        >
                                            ★
                                        </span>
                                    </div>


                                    <!-- COMMENT -->
                                    <p
                                        v-if="review.comment"
                                        class="mt-3 whitespace-pre-line text-sm leading-6 text-[#475569]"
                                    >
                                        {{ review.comment }}
                                    </p>


                                    <!-- SELLER REPLY -->
                                    <div
                                        v-if="review.seller_reply"
                                        class="mt-4 rounded-xl border border-[#E8F7F6] bg-[#F8FAF9] p-3"
                                    >

                                        <div
                                            class="flex items-center gap-2"
                                        >
                                            <span
                                                class="h-2 w-2 rounded-full bg-[#16A6A0]"
                                            ></span>

                                            <p
                                                class="text-xs font-bold text-[#087F8C]"
                                            >
                                                Seller reply
                                            </p>

                                            <span
                                                v-if="review.seller_replied_at"
                                                class="text-[10px] text-[#94A3B8]"
                                            >
                                                ·
                                                {{ reviewDate(review.seller_replied_at) }}
                                            </span>
                                        </div>

                                        <p
                                            class="mt-2 whitespace-pre-line text-sm leading-5 text-[#475569]"
                                        >
                                            {{ review.seller_reply }}
                                        </p>

                                    </div>

                                </div>

                            </div>

                        </article>

                    </div>


                    <!-- EMPTY -->
                    <div
                        v-else
                        class="rounded-2xl border border-dashed border-[#E5E7EB] bg-white px-5 py-12 text-center"
                    >

                        <div
                            class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-[#E8F7F6] text-[#087F8C]"
                        >
                            ★
                        </div>

                        <h3
                            class="mt-3 text-sm font-bold text-[#1F2937]"
                        >
                            No reviews yet
                        </h3>

                        <p
                            class="mt-1 text-xs text-[#64748B]"
                        >
                            Customers can share their experience after purchasing this product.
                        </p>

                    </div>

                </section>


                <!-- PAGINATION -->
                <div
                    v-if="reviews.links?.length > 3"
                    class="mt-6 flex w-full flex-wrap items-center justify-center gap-1.5"
                >

                    <Link
                        v-for="(link, index) in reviews.links"
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
    </BuyerLayout>
</template>