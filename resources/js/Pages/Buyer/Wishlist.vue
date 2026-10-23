<script setup>
import Icon from '@/Components/Icon.vue'
import { ref, computed } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'

const props = defineProps({
    wishlist: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
})

const page = usePage()
const flashSuccess = computed(() => page.props.flash?.success || '')
const removingId = ref(null)
const addingCartId = ref(null)

const money = value => {
    const amount = Number(value ?? 0)
    return `₱${amount.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`
}

const failedImages = ref({})
const onImageError = id => {
    failedImages.value[id] = true
}

const imageUrl = path => {
    if (!path) return null
    const value = String(path).trim()
    if (!value) return null

    if (
        value.startsWith('http://') ||
        value.startsWith('https://') ||
        value.startsWith('/storage/') ||
        value.startsWith('/images/') ||
        value.startsWith('data:')
    ) {
        return value
    }

    if (value.startsWith('storage/')) {
        return `/${value}`
    }

    return `/storage/${value.replace(/^\/+/, '')}`
}

const removeFromWishlist = productId => {
    removingId.value = productId
    router.delete(route('buyer.wishlist.destroy', productId), {
        preserveScroll: true,
        onFinish: () => {
            removingId.value = null
        },
    })
}

const addToCart = product => {
    if (product.is_sold_out || (product.stock !== undefined && product.stock <= 0)) {
        return
    }

    addingCartId.value = product.id
    router.post(
        route('buyer.cart.store', product.id),
        {
            quantity: 1,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                addingCartId.value = null
            },
        }
    )
}
</script>

<template>
    <Head title="My Wishlist" />

    <BuyerLayout>
        <main class="min-h-[calc(100vh-80px)] w-full min-w-0 overflow-x-hidden bg-[#F8FAF9]">
            <div class="mx-auto w-full max-w-[1536px] px-4 py-6 pb-12 sm:px-6 sm:py-7 lg:px-8 2xl:px-10 lg:py-8 lg:pb-14">

                <!-- PAGE HEADER -->
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#E85D5D]"></span>
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#087F8C] sm:text-xs">
                                Saved Items
                            </p>
                        </div>

                        <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-[#1F2937] sm:text-3xl">
                            My Wishlist
                        </h1>

                        <p class="mt-1 text-xs leading-5 text-[#64748B] sm:text-sm">
                            Keep track of products you love and want to purchase later.
                        </p>
                    </div>
                </div>

                <!-- SUCCESS MESSAGE -->
                <div
                    v-if="flashSuccess"
                    class="mt-4 flex items-start gap-2.5 rounded-xl border border-[#CDE9E4] bg-[#EAF8F1] px-3.5 py-3 text-xs font-bold text-[#17784F] sm:text-sm"
                >
                    <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12l4 4L19 6" />
                    </svg>
                    <span>{{ flashSuccess }}</span>
                </div>

                <!-- EMPTY STATE -->
                <div
                    v-if="!wishlist.data || wishlist.data.length === 0"
                    class="mt-8 rounded-2xl border border-[#E5E7EB] bg-white p-10 text-center shadow-xs sm:py-16"
                >
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-rose-50 text-rose-500">
                        <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                    </div>

                    <h2 class="mt-4 text-lg font-bold text-[#1F2937] sm:text-xl">
                        Your wishlist is currently empty
                    </h2>

                    <p class="mx-auto mt-2 max-w-md text-xs leading-5 text-[#64748B] sm:text-sm">
                        Explore our wide marketplace selection and click the heart icon on any product to save it here for later.
                    </p>

                    <div class="mt-6">
                        <Link
                            :href="route('buyer.products')"
                            class="inline-flex items-center gap-2 rounded-xl bg-[#087F8C] px-6 py-3 text-xs font-extrabold text-white shadow-sm transition hover:bg-[#066B76] sm:text-sm"
                        >
                            Explore Marketplace
                        </Link>
                    </div>
                </div>

                <!-- WISHLIST GRID -->
                <div
                    v-else
                    class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-6 sm:gap-5"
                >
                    <div
                        v-for="item in wishlist.data"
                        :key="item.id"
                        class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-[#087F8C]/40 hover:shadow-md"
                    >
                        <!-- PRODUCT CARD TOP -->
                        <div>
                            <!-- IMAGE & BADGES -->
                            <div class="relative aspect-square w-full overflow-hidden bg-[#F8FAF9] p-3">
                                <Link
                                    v-if="item.product"
                                    :href="route('buyer.product', item.product.id)"
                                    class="block h-full w-full"
                                >
                                    <img
                                        v-if="item.product.image_path && !failedImages[item.product.id]"
                                        :src="imageUrl(item.product.image_path)"
                                        :alt="item.product.name"
                                        @error="onImageError(item.product.id)"
                                        class="h-full w-full object-contain transition duration-300 group-hover:scale-[1.03]"
                                        :class="{ 'opacity-60 grayscale': item.product.is_sold_out || (item.product.stock !== undefined && item.product.stock <= 0) }"
                                        loading="lazy"
                                    />
                                    <div
                                        v-else
                                        class="flex h-full w-full items-center justify-center text-3xl text-slate-300"
                                    >
                                        <Icon name="bag" class="h-10 w-10" />
                                    </div>
                                </Link>

                                <!-- REMOVE FROM WISHLIST (HEART / CLOSE BUTTON) -->
                                <button
                                    type="button"
                                    @click="removeFromWishlist(item.product.id)"
                                    :disabled="removingId === item.product.id"
                                    title="Remove from wishlist"
                                    class="absolute right-2.5 top-2.5 z-10 flex h-8 w-8 items-center justify-center rounded-full bg-white/90 text-rose-500 shadow-xs backdrop-blur-xs transition hover:bg-rose-500 hover:text-white disabled:opacity-50"
                                >
                                    <svg class="h-4 w-4 fill-current" viewBox="0 0 24 24">
                                        <path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                    </svg>
                                </button>

                                <!-- BUY-WISH-003: SOLD OUT BADGE -->
                                <div
                                    v-if="item.product?.is_sold_out || (item.product?.stock !== undefined && item.product.stock <= 0)"
                                    class="absolute left-2.5 top-2.5 z-10 rounded-md bg-rose-600 px-2 py-0.5 text-[11px] font-black uppercase tracking-wider text-white shadow-xs"
                                >
                                    Sold out
                                </div>
                                <div
                                    v-else-if="item.product?.discount_badge"
                                    class="absolute left-2.5 top-2.5 z-10 rounded-md bg-amber-500 px-2 py-0.5 text-[10px] font-extrabold text-white shadow-xs"
                                >
                                    {{ item.product.discount_badge }}
                                </div>
                            </div>

                            <!-- DETAILS -->
                            <div class="p-3.5">
                                <!-- STORE LINK -->
                                <div v-if="item.product?.seller" class="truncate text-[10px] font-semibold text-[#64748B]">
                                    <Link
                                        :href="route('buyer.store.show', item.product.seller.id)"
                                        class="hover:text-[#087F8C] transition"
                                    >
                                        {{ item.product.seller.store_name || item.product.seller.name }}
                                    </Link>
                                </div>

                                <!-- PRODUCT TITLE -->
                                <h3 class="mt-1 line-clamp-2 text-xs font-bold leading-snug text-[#1F2937] sm:text-sm">
                                    <Link
                                        v-if="item.product"
                                        :href="route('buyer.product', item.product.id)"
                                        class="hover:text-[#087F8C] transition"
                                    >
                                        {{ item.product.name }}
                                    </Link>
                                </h3>

                                <!-- PRICE & STOCK STATUS -->
                                <div class="mt-2.5 flex items-baseline justify-between gap-1">
                                    <span class="text-sm font-extrabold text-[#087F8C] sm:text-base">
                                        {{ money(item.product?.price) }}
                                    </span>

                                    <!-- STOCK STATUS BADGE -->
                                    <span
                                        v-if="item.product?.is_sold_out || (item.product?.stock !== undefined && item.product.stock <= 0)"
                                        class="text-[11px] font-bold text-rose-600"
                                    >
                                        Sold out
                                    </span>
                                    <span
                                        v-else
                                        class="text-[11px] font-medium text-emerald-600"
                                    >
                                        In Stock
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- ACTIONS FOOTER -->
                        <div class="border-t border-[#E5E7EB] p-3 pt-2.5">
                            <button
                                type="button"
                                @click="addToCart(item.product)"
                                :disabled="
                                    item.product?.is_sold_out ||
                                    (item.product?.stock !== undefined && item.product.stock <= 0) ||
                                    addingCartId === item.product?.id
                                "
                                class="w-full rounded-xl py-2 text-center text-xs font-bold transition disabled:cursor-not-allowed"
                                :class="
                                    item.product?.is_sold_out || (item.product?.stock !== undefined && item.product.stock <= 0)
                                        ? 'bg-slate-100 text-slate-400'
                                        : 'bg-[#087F8C] text-white hover:bg-[#066B76] shadow-xs'
                                "
                            >
                                <span v-if="item.product?.is_sold_out || (item.product?.stock !== undefined && item.product.stock <= 0)">
                                    Sold out
                                </span>
                                <span v-else-if="addingCartId === item.product?.id">
                                    Adding...
                                </span>
                                <span v-else class="flex items-center justify-center gap-1.5">
                                    <Icon name="cart" class="h-3.5 w-3.5" />
                                    Add to Cart
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- PAGINATION -->
                <div
                    v-if="wishlist.links && wishlist.links.length > 3"
                    class="mt-8 flex justify-center gap-1"
                >
                    <template v-for="(link, i) in wishlist.links" :key="i">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            v-html="link.label"
                            class="rounded-lg border px-3 py-1.5 text-xs font-medium transition"
                            :class="link.active ? 'border-[#087F8C] bg-[#087F8C] text-white' : 'border-[#E5E7EB] bg-white text-[#1F2937] hover:bg-slate-50'"
                        />
                        <span
                            v-else
                            v-html="link.label"
                            class="rounded-lg border border-transparent px-3 py-1.5 text-xs font-medium text-slate-400"
                        />
                    </template>
                </div>

            </div>
        </main>
    </BuyerLayout>
</template>

