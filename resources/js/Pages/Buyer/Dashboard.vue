<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'

const props = defineProps({
    categories: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
})

const safeRoute = (name, fallback = '#', params = undefined) => {
    try {
        return params === undefined ? route(name) : route(name, params)
    } catch (e) {
        return fallback
    }
}

const imageUrl = path => {
    if (!path) return null
    if (
        typeof path === 'string' &&
        (path.startsWith('http://') ||
            path.startsWith('https://') ||
            path.startsWith('/storage/'))
    ) return path
    return `/storage/${String(path).replace(/^\/+/, '')}`
}

const products = computed(() =>
    props.products.map(product => ({
        ...product,
        category: product.category?.name ?? 'Product',
        price: `₱${Number(product.price ?? 0).toLocaleString()}`,
        oldPrice: product.old_price
            ? `₱${Number(product.old_price).toLocaleString()}`
            : null,
        reviews: product.reviews_count ?? 0,
        image: imageUrl(product.image_path),
        description: product.description ?? '',
        soldCount: Number(
            product.sold_count ??
            product.sales_count ??
            product.order_items_count ??
            product.total_sold ??
            0
        ),
        isSale: !!product.old_price,
    }))
)

const featuredProduct = computed(() =>
    [...products.value].sort((a, b) => b.soldCount - a.soldCount)[0] ?? null
)

const trendingProducts = computed(() =>
    products.value
        .filter(product => product.id !== featuredProduct.value?.id)
        .slice(0, 4)
)

const benefits = [
    {
        number: '01',
        title: 'Curated for you',
        text: 'Discover products selected from different categories in one simple marketplace.',
    },
    {
        number: '02',
        title: 'Trusted sellers',
        text: 'Shop from sellers who are part of the Alona marketplace community.',
    },
    {
        number: '03',
        title: 'Simple shopping',
        text: 'Find products, compare choices, and shop without unnecessary steps.',
    },
]
</script>

<template>
    <Head title="Alona" />

    <BuyerLayout>
        <div class="min-h-screen overflow-hidden bg-[#F8FAF9] text-[#1F2937]">

            <!-- HERO -->
            <section class="relative">
                <div class="pointer-events-none absolute -left-32 top-20 h-72 w-72 rounded-full bg-[#16A6A0]/10 blur-3xl"></div>
                <div class="pointer-events-none absolute right-0 top-0 h-96 w-96 rounded-full bg-[#F4B942]/10 blur-3xl"></div>

                <div class="mx-auto max-w-[1440px] px-4 pb-8 pt-5 sm:px-6 lg:px-8 xl:pb-12">
                    <div class="relative overflow-hidden rounded-[32px] bg-[#087F8C]">
                        <div class="absolute -right-24 -top-32 h-96 w-96 rounded-full border-[70px] border-white/[0.05]"></div>
                        <div class="absolute -bottom-32 left-[40%] h-72 w-72 rounded-full bg-[#F4B942]/10 blur-2xl"></div>
                        <div class="absolute right-[32%] top-16 h-24 w-24 rounded-full bg-white/[0.04]"></div>

                        <div class="relative grid min-h-[490px] grid-cols-1 lg:grid-cols-[1.05fr_.95fr]">
                            <div class="relative z-10 flex flex-col justify-center px-6 py-12 sm:px-10 lg:px-14 xl:px-20">
                                <div class="mb-6 flex items-center gap-3">
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-[#F4B942] text-[#087F8C]">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 3v18M3 12h18" stroke-linecap="round"/>
                                        </svg>
                                    </span>
                                    <span class="text-xs font-bold uppercase tracking-[0.2em] text-teal-50">
                                        The Alona Marketplace
                                    </span>
                                </div>

                                <h1 class="max-w-2xl text-4xl font-black leading-[0.98] tracking-[-0.04em] text-white sm:text-5xl lg:text-6xl xl:text-[68px]">
                                    Find your next
                                    <span class="text-[#F4B942]"> favorite thing.</span>
                                </h1>

                                <p class="mt-6 max-w-xl text-sm leading-6 text-teal-50 sm:text-base sm:leading-7">
                                    A fresh marketplace for everyday finds, useful essentials,
                                    exciting deals, and products from sellers worth discovering.
                                </p>

                                <div class="mt-8 flex flex-wrap gap-3">
                                    <Link
                                        :href="safeRoute('buyer.products')"
                                        class="group inline-flex items-center gap-3 rounded-2xl bg-white px-5 py-3.5 text-sm font-extrabold text-[#087F8C] shadow-xl shadow-black/10 transition duration-200 hover:-translate-y-1 hover:bg-[#F4FBFA]"
                                    >
                                        Start exploring
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-[#E8F7F6] transition group-hover:bg-[#087F8C] group-hover:text-white">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3">
                                                <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </span>
                                    </Link>
                                </div>
                            </div>

                            <div class="relative flex min-h-[330px] items-center justify-center lg:min-h-0">
                                <div class="absolute h-[280px] w-[280px] rounded-full bg-white/[0.08] sm:h-[360px] sm:w-[360px] lg:h-[420px] lg:w-[420px]"></div>
                                <div class="absolute h-[220px] w-[220px] rounded-full border border-white/10 sm:h-[300px] sm:w-[300px]"></div>

                                <img
                                    src="/images/Alogo.png"
                                    alt="Alona"
                                    class="relative z-10 w-[250px] max-w-[75%] object-contain drop-shadow-[0_25px_35px_rgba(0,0,0,0.18)] sm:w-[330px] lg:w-[390px]"
                                />

                                <div class="absolute right-[7%] top-[12%] z-20 hidden rounded-2xl border border-white/20 bg-white/95 p-3 shadow-xl backdrop-blur sm:block">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path d="M3 3h2l2 12h10l2-8H6" stroke-linecap="round" stroke-linejoin="round"/>
                                                <circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-[9px] font-bold uppercase tracking-wider text-gray-400">Shopping</p>
                                            <p class="text-xs font-extrabold text-gray-900">Made simple</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="absolute bottom-[12%] left-[7%] z-20 hidden rounded-2xl border border-white/20 bg-white/95 px-4 py-3 shadow-xl backdrop-blur sm:block">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-[#F4B942] text-white">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9L12 3Z" stroke-linejoin="round"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-[9px] font-bold text-gray-400">DISCOVER</p>
                                            <p class="text-xs font-extrabold text-gray-900">Something new</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- TOP SELLING -->
            <section v-if="featuredProduct" class="mx-auto max-w-[1440px] px-4 py-7 sm:px-6 lg:px-8 lg:py-9">
                <div class="mb-4 flex items-end justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.22em] text-[#087F8C]">Top Selling</p>
                        <h2 class="mt-1 text-xl font-black tracking-tight text-gray-900 sm:text-2xl">Customer favorites</h2>
                    </div>
                    <Link :href="safeRoute('buyer.products')" class="hidden items-center gap-2 text-xs font-bold text-[#087F8C] sm:flex">
                        See all products
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </Link>
                </div>

                <div class="grid overflow-hidden rounded-[24px] border border-gray-100 bg-white shadow-sm lg:grid-cols-[.85fr_1.15fr]">
                    <Link :href="safeRoute('buyer.product', '#', featuredProduct.id)" class="group relative h-[230px] overflow-hidden bg-[#F3F7F6] sm:h-[280px] lg:h-[320px]">
                        <img v-if="featuredProduct.image" :src="featuredProduct.image" :alt="featuredProduct.name" class="h-full w-full object-cover transition duration-700 group-hover:scale-105" loading="lazy"/>
                        <div v-else class="flex h-full items-center justify-center text-5xl">🛍️</div>
                        <div class="absolute left-4 top-4 rounded-full bg-[#F4B942] px-3 py-1.5 text-[9px] font-black uppercase tracking-wider text-[#087F8C]">#1 Top Selling</div>
                        <div v-if="featuredProduct.isSale" class="absolute bottom-4 left-4 rounded-full bg-[#E85D5D] px-3 py-1.5 text-[9px] font-black uppercase tracking-wider text-white">Sale</div>
                    </Link>

                    <div class="flex flex-col justify-center p-5 sm:p-7 lg:p-8">
                        <span class="w-fit rounded-full bg-[#E8F7F6] px-3 py-1.5 text-[9px] font-black uppercase tracking-wider text-[#087F8C]">{{ featuredProduct.category }}</span>
                        <h3 class="mt-3 text-xl font-black leading-tight tracking-[-0.03em] text-gray-900 sm:text-2xl">{{ featuredProduct.name }}</h3>
                        <p v-if="featuredProduct.description" class="mt-3 line-clamp-3 max-w-xl text-xs leading-5 text-gray-500 sm:text-sm">{{ featuredProduct.description }}</p>
                        <p v-else class="mt-3 text-xs leading-5 text-gray-400">Discover this popular product from one of our Alona sellers.</p>

                        <div class="mt-4 flex items-end gap-3">
                            <span class="text-xl font-black text-[#087F8C]">{{ featuredProduct.price }}</span>
                            <span v-if="featuredProduct.oldPrice" class="pb-0.5 text-xs text-gray-400 line-through">{{ featuredProduct.oldPrice }}</span>
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-2 text-[10px] text-gray-400">
                            <span class="text-[#F4B942]">★</span>
                            <strong class="text-gray-600">{{ featuredProduct.rating ?? 0 }}</strong>
                            <span>({{ featuredProduct.reviews }} reviews)</span>
                            <span class="text-gray-300">•</span>
                            <span>{{ featuredProduct.soldCount }} sold</span>
                        </div>

                        <Link
                            :href="safeRoute('buyer.product', '#', featuredProduct.id)"
                            class="mt-5 inline-flex w-fit items-center gap-3 rounded-xl bg-[#087F8C] px-4 py-3 text-xs font-extrabold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-[#066974]"
                        >
                            View product
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </Link>
                    </div>
                </div>
            </section>

            <!-- TRENDING -->
            <section class="border-y border-gray-100 bg-white">
                <div class="mx-auto max-w-[1440px] px-4 py-12 sm:px-6 lg:px-8">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-[#F4B942]"></span>
                                <p class="text-[10px] font-black uppercase tracking-[0.22em] text-[#087F8C]">Trending now</p>
                            </div>
                            <h2 class="mt-2 text-2xl font-black tracking-tight text-gray-900 sm:text-3xl">People are looking</h2>
                        </div>
                        <Link :href="safeRoute('buyer.products')" class="hidden items-center gap-2 text-xs font-bold text-[#087F8C] sm:flex">
                            See everything
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </Link>
                    </div>

                    <div v-if="trendingProducts.length" class="mt-7 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        <Link v-for="(product, index) in trendingProducts" :key="product.id" :href="safeRoute('buyer.product', '#', product.id)" class="group">
                            <div class="relative aspect-[.92] overflow-hidden rounded-[24px] bg-[#F4F7F6]">
                                <img v-if="product.image" :src="product.image" :alt="product.name" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy"/>
                                <div v-else class="flex h-full items-center justify-center text-4xl">🛍️</div>
                                <span class="absolute left-3 top-3 flex h-7 w-7 items-center justify-center rounded-full bg-white/90 text-[10px] font-black text-[#087F8C] shadow-sm backdrop-blur">{{ String(index + 1).padStart(2, '0') }}</span>
                                <span v-if="product.isSale" class="absolute bottom-3 left-3 rounded-full bg-[#E85D5D] px-2.5 py-1 text-[9px] font-black text-white">SALE</span>
                            </div>

                            <div class="px-1 pt-4">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-[#087F8C]">{{ product.category }}</p>
                                <h3 class="mt-1 line-clamp-2 text-sm font-extrabold leading-5 text-gray-900 transition group-hover:text-[#087F8C]">{{ product.name }}</h3>
                                <div class="mt-2 flex items-center gap-2">
                                    <span class="text-sm font-black text-[#087F8C]">{{ product.price }}</span>
                                    <span v-if="product.oldPrice" class="text-[10px] text-gray-400 line-through">{{ product.oldPrice }}</span>
                                </div>
                                <div class="mt-2 flex items-center gap-1 text-[10px] text-gray-400">
                                    <span class="text-[#F4B942]">★</span>
                                    {{ product.rating ?? 0 }}
                                    <span>({{ product.reviews }})</span>
                                </div>
                            </div>
                        </Link>
                    </div>

                    <div v-else class="mt-7 rounded-[24px] border border-dashed border-gray-200 bg-[#F8FAF9] p-12 text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-[#E8F7F6] text-2xl">🛍️</div>
                        <h3 class="mt-4 text-sm font-extrabold text-gray-900">Products are coming soon</h3>
                        <p class="mx-auto mt-1 max-w-sm text-xs leading-5 text-gray-500">Check back soon for new products from Alona sellers.</p>
                    </div>
                </div>
            </section>

            <!-- DEAL BANNER -->
            <section class="mx-auto max-w-[1440px] px-4 py-12 sm:px-6 lg:px-8">
                <div class="relative overflow-hidden rounded-[30px] bg-[#F4B942]">
                    <div class="absolute -right-20 -top-24 h-64 w-64 rounded-full bg-white/20"></div>
                    <div class="absolute -bottom-28 left-[45%] h-64 w-64 rounded-full bg-[#087F8C]/10"></div>
                    <div class="relative grid items-center gap-8 px-7 py-9 sm:px-10 lg:grid-cols-[1fr_auto] lg:px-14 lg:py-11">
                        <div>
                            <span class="inline-flex rounded-full bg-white/70 px-3 py-1.5 text-[9px] font-black uppercase tracking-wider text-[#087F8C]">Alona finds</span>
                            <h2 class="mt-4 max-w-2xl text-3xl font-black leading-tight tracking-[-0.03em] text-[#087F8C] sm:text-4xl">Good finds don't have to be complicated.</h2>
                            <p class="mt-3 max-w-xl text-sm leading-6 text-[#087F8C]/75">Browse products, discover new sellers, and find something that fits your everyday life.</p>
                        </div>
                        <Link :href="safeRoute('buyer.deals', safeRoute('buyer.products'))" class="inline-flex w-fit items-center gap-3 rounded-2xl bg-[#087F8C] px-5 py-3.5 text-xs font-extrabold text-white shadow-lg transition hover:-translate-y-1 hover:bg-[#066974]">
                            Explore deals
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </Link>
                    </div>
                </div>
            </section>

            <!-- WHY ALONA -->
            <section class="bg-[#087F8C]">
                <div class="mx-auto max-w-[1440px] px-4 py-14 sm:px-6 lg:px-8 lg:py-16">
                    <div class="grid gap-10 lg:grid-cols-[.8fr_1.2fr]">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.22em] text-[#F4B942]">Why Alona</p>
                            <h2 class="mt-3 max-w-md text-3xl font-black leading-tight tracking-[-0.03em] text-white sm:text-4xl">Shopping should feel effortless.</h2>
                            <p class="mt-4 max-w-md text-sm leading-6 text-teal-50">Alona brings products and sellers together through a marketplace designed to keep discovering simple.</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div v-for="benefit in benefits" :key="benefit.number" class="rounded-[24px] border border-white/10 bg-white/[0.07] p-5 transition hover:-translate-y-1 hover:bg-white/10">
                                <span class="text-xs font-black text-[#F4B942]">{{ benefit.number }}</span>
                                <h3 class="mt-8 text-sm font-extrabold text-white">{{ benefit.title }}</h3>
                                <p class="mt-2 text-xs leading-5 text-teal-100">{{ benefit.text }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- SELLER CTA -->
            <section class="mx-auto max-w-[1440px] px-4 py-12 sm:px-6 lg:px-8">
                <div class="relative overflow-hidden rounded-[30px] border border-gray-100 bg-white shadow-sm">
                    <div class="absolute right-0 top-0 h-40 w-40 rounded-full bg-[#E8F7F6]"></div>
                    <div class="absolute -bottom-16 right-24 h-32 w-32 rounded-full bg-[#F4B942]/20"></div>

                    <div class="relative grid gap-8 p-7 sm:p-10 lg:grid-cols-[1fr_auto] lg:items-center lg:p-12">
                        <div>
                            <span class="inline-flex rounded-full bg-[#FFF6DF] px-3 py-1.5 text-[9px] font-black uppercase tracking-wider text-[#A87308]">For sellers</span>
                            <h2 class="mt-4 text-2xl font-black tracking-tight text-gray-900 sm:text-3xl">Have something worth selling?</h2>
                            <p class="mt-2 max-w-xl text-sm leading-6 text-gray-500">Put your products in front of new customers and grow your business with Alona.</p>
                        </div>
                        <Link :href="safeRoute('register.seller')" class="inline-flex w-fit items-center gap-3 rounded-2xl border border-[#087F8C] px-5 py-3.5 text-xs font-extrabold text-[#087F8C] transition hover:bg-[#087F8C] hover:text-white">
                            Start selling
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </Link>
                    </div>
                </div>
            </section>

            <!-- FINAL CTA -->
            <section class="border-t border-gray-100 bg-white">
                <div class="mx-auto max-w-[1440px] px-4 py-12 text-center sm:px-6 lg:px-8 lg:py-16">
                    <p class="text-[10px] font-black uppercase tracking-[0.25em] text-[#087F8C]">Your next find is waiting</p>
                    <h2 class="mx-auto mt-3 max-w-2xl text-3xl font-black tracking-[-0.04em] text-gray-900 sm:text-4xl">Discover something you didn't know you needed.</h2>
                    <p class="mx-auto mt-4 max-w-xl text-sm leading-6 text-gray-500">Explore the Alona marketplace and find products from different categories in one place.</p>
                    <Link :href="safeRoute('buyer.products')" class="mt-7 inline-flex items-center gap-3 rounded-2xl bg-[#087F8C] px-6 py-3.5 text-xs font-extrabold text-white shadow-lg shadow-[#087F8C]/10 transition hover:-translate-y-1 hover:bg-[#066974]">
                        Explore Alona
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </Link>
                </div>
            </section>

        </div>
    </BuyerLayout>
</template>
