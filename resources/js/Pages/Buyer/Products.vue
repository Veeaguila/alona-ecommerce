<script setup>
import { Head, Link } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'

const props = defineProps({
    products: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
            total: 0,
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
</script>

<template>
    <Head title="Products" />

    <BuyerLayout>

        <!-- ============================================================= -->
        <!-- FULL WIDTH PRODUCTS PAGE -->
        <!-- ============================================================= -->

        <main
            class="w-full min-w-0 overflow-x-hidden bg-[#F8FAF9]"
        >

            <div
                class="w-full min-w-0 px-4 py-5 sm:px-6 sm:py-6 lg:px-8"
            >

                <!-- ===================================================== -->
                <!-- PAGE HEADER -->
                <!-- ===================================================== -->

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
                        :href="route('buyer.product', product.id)"
                        class="group block min-w-0 overflow-hidden rounded-xl border border-[#E5E7EB] bg-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-[#E5E7EB] hover:shadow-md"
                    >

                        <!-- ================================================= -->
                        <!-- IMAGE -->
                        <!-- ================================================= -->

                        <div
                            class="relative flex aspect-square w-full items-center justify-center overflow-hidden bg-[#F8FAF9] p-2.5 sm:p-3"
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
                            >
                                🛍️
                            </span>

                        </div>


                        <!-- ================================================= -->
                        <!-- PRODUCT INFO -->
                        <!-- ================================================= -->

                        <div
                            class="min-w-0 p-3 sm:p-3.5"
                        >

                            <!-- CATEGORY -->

                            <p
                                class="truncate text-[9px] font-semibold uppercase tracking-wide text-[#64748B] sm:text-[10px]"
                            >
                                {{ product.category?.name || 'Uncategorized' }}
                            </p>


                            <!-- PRODUCT NAME -->

                            <h2
                                class="mt-1 truncate text-[12px] font-semibold text-[#1F2937] sm:text-[13px]"
                                :title="product.name"
                            >
                                {{ product.name }}
                            </h2>


                            <!-- ================================================= -->
                            <!-- RATING -->
                            <!-- ================================================= -->

                            <div
                                class="mt-1.5 flex min-w-0 items-center gap-1"
                            >
                                <!-- STARS -->
                                <div class="flex shrink-0 items-center">
                                    <span
                                        v-for="star in 5"
                                        :key="star"
                                        class="text-[11px] leading-none sm:text-xs"
                                        :class="
                                            star <= Math.round(Number(product.rating ?? 0))
                                                ? 'text-[#F4B942]'
                                                : 'text-[#CBD5E1]'
                                        "
                                    >
                                        ★
                                    </span>
                                </div>

                                <!-- RATING NUMBER -->
                                <span
                                    class="text-[10px] font-medium text-[#64748B] sm:text-[11px]"
                                >
                                    {{ Number(product.rating ?? 0).toFixed(2) }}
                                </span>

                                <!-- REVIEW COUNT -->
                                <span
                                    class="truncate text-[10px] text-[#94A3B8] sm:text-[11px]"
                                >
                                    ({{ Number(product.reviews_count ?? 0) }})
                                </span>
                            </div>


                            <!-- PRICE -->

                            <div
                                class="mt-3 flex min-w-0 items-center justify-between gap-2"
                            >

                                <div class="min-w-0">

                                    <span
                                        class="block truncate text-[12px] font-bold text-[#1F2937] sm:text-sm"
                                    >
                                        ₱{{ formatPrice(product.price) }}
                                    </span>

                                    <span
                                        v-if="product.old_price"
                                        class="mt-0.5 block truncate text-[9px] text-[#64748B] line-through sm:ml-0 sm:text-[10px]"
                                    >
                                        ₱{{ formatPrice(product.old_price) }}
                                    </span>

                                </div>


                                <!-- VIEW BUTTON -->

                                <span
                                    class="flex shrink-0 items-center justify-center rounded-lg bg-[#087F8C] px-2 py-1.5 text-[9px] font-semibold text-white transition group-hover:bg-[#16A6A0] sm:px-2.5 sm:text-[10px]"
                                >
                                    View
                                </span>

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

    </BuyerLayout>
</template>