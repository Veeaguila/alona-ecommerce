<script setup>
import { computed, ref } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
})

const selectedItems = ref([])

const imageUrl = path => {
    if (!path) return null

    if (
        path.startsWith('http://') ||
        path.startsWith('https://') ||
        path.startsWith('/storage/')
    ) {
        return path
    }

    return `/storage/${path.replace(/^\/+/, '')}`
}

const safeRoute = (name, params = undefined, fallback = '#') => {
    try {
        return params !== undefined ? route(name, params) : route(name)
    } catch (e) {
        return fallback
    }
}

const money = value =>
    `₱${Number(value || 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`

const variantLabel = item =>
    [item.variant?.color, item.variant?.size]
        .filter(Boolean)
        .join(' / ')

const stockFor = item =>
    item.variant
        ? Number(item.variant.stock || 0)
        : Number(item.product?.stock || 0)

const itemTotal = item =>
    Number(item.product?.price || 0) * Number(item.quantity || 0)

const selectedCartItems = computed(() =>
    props.items.filter(item =>
        selectedItems.value.includes(Number(item.id))
    )
)

const selectedCount = computed(() =>
    selectedCartItems.value.length
)

const selectedQuantity = computed(() =>
    selectedCartItems.value.reduce(
        (total, item) => total + Number(item.quantity || 0),
        0
    )
)

const subtotal = computed(() =>
    selectedCartItems.value.reduce(
        (total, item) => total + itemTotal(item),
        0
    )
)

const allSelected = computed(() =>
    props.items.length > 0 &&
    selectedItems.value.length === props.items.length
)

const hasSelection = computed(() =>
    selectedItems.value.length > 0
)

const toggleItem = item => {
    const id = Number(item.id)

    if (selectedItems.value.includes(id)) {
        selectedItems.value = selectedItems.value.filter(
            selectedId => selectedId !== id
        )
        return
    }

    selectedItems.value = [...selectedItems.value, id]
}

const toggleAll = () => {
    selectedItems.value = allSelected.value
        ? []
        : props.items.map(item => Number(item.id))
}

const update = (item, quantity) => {
    let newQuantity = Number(quantity)

    if (!Number.isFinite(newQuantity)) return

    const stock = stockFor(item)

    newQuantity = Math.floor(newQuantity)

    if (newQuantity < 1) newQuantity = 1
    if (stock > 0 && newQuantity > stock) newQuantity = stock

    useForm({ quantity: newQuantity }).patch(
        safeRoute('buyer.cart.update', item.id),
        {
            preserveScroll: true,
        }
    )
}

const increaseQuantity = item => {
    const current = Number(item.quantity || 1)
    const stock = stockFor(item)

    if (stock <= 0) return

    if (current < stock) {
        update(item, current + 1)
    }
}

const decreaseQuantity = item => {
    const current = Number(item.quantity || 1)

    if (current > 1) {
        update(item, current - 1)
    }
}

const remove = item => {
    if (
        !confirm(
            `Remove "${item.product?.name || 'this item'}" from your cart?`
        )
    ) {
        return
    }

    useForm({}).delete(
        safeRoute('buyer.cart.destroy', item.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedItems.value = selectedItems.value.filter(
                    id => id !== Number(item.id)
                )
            },
        }
    )
}

const removeSelected = () => {
    if (!hasSelection.value) return

    if (
        !confirm(
            `Remove ${selectedCount.value} selected item(s) from your cart?`
        )
    ) {
        return
    }

    const ids = [...selectedItems.value]
    let remaining = ids.length

    ids.forEach(id => {
        useForm({}).delete(
            safeRoute('buyer.cart.destroy', id),
            {
                preserveScroll: true,
                onSuccess: () => {
                    remaining--

                    if (remaining === 0) {
                        selectedItems.value = []

                        router.reload({
                            only: ['items'],
                            preserveScroll: true,
                        })
                    }
                },
            }
        )
    })
}

const proceedToCheckout = () => {
    if (!hasSelection.value) return

    router.get(
        safeRoute('buyer.checkout'),
        {
            selected_items: selectedItems.value,
        },
        {
            preserveScroll: true,
        }
    )
}
</script>

<template>
    <Head title="Shopping Cart" />

    <BuyerLayout>
        <main class="min-h-[calc(100vh-80px)] w-full min-w-0 overflow-x-hidden bg-[#F8FAF9]">
            <div class="mx-auto w-full max-w-[1500px] px-4 py-6 pb-12 sm:px-6 sm:py-7 lg:px-8 lg:py-8 lg:pb-14">

                <!-- HEADER -->
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#F4B942]"></span>
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#087F8C] sm:text-xs">
                                Your basket
                            </p>
                        </div>

                        <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-[#1F2937] sm:text-3xl">
                            Shopping Cart
                        </h1>

                        <p class="mt-1 text-xs leading-5 text-[#64748B] sm:text-sm">
                            Review your items and choose what you want to purchase.
                        </p>
                    </div>

                    <Link
                        :href="safeRoute('buyer.products')"
                        class="inline-flex w-fit items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-bold text-[#087F8C] transition hover:bg-[#E8F7F6]"
                    >
                        <span>←</span>
                        Continue Shopping
                    </Link>
                </div>

                <!-- EMPTY CART -->
                <div
                    v-if="!items.length"
                    class="mt-7 rounded-2xl border border-dashed border-[#D9E3E2] bg-white px-5 py-14 text-center shadow-sm sm:mt-8"
                >
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-[#E8F7F6] text-3xl">
                        🛒
                    </div>

                    <h2 class="mt-4 text-base font-extrabold text-[#1F2937]">
                        Your cart is empty
                    </h2>

                    <p class="mx-auto mt-1.5 max-w-md text-xs leading-5 text-[#64748B] sm:text-sm">
                        Discover something you love from the Alona marketplace.
                    </p>

                    <Link
                        :href="safeRoute('buyer.products')"
                        class="mt-5 inline-flex rounded-xl bg-[#087F8C] px-5 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#066C76]"
                    >
                        Browse Products
                    </Link>
                </div>

                <!-- CART -->
                <div
                    v-else
                    class="mt-7 grid min-w-0 gap-5 lg:mt-8 lg:grid-cols-[minmax(0,1fr)_330px] xl:grid-cols-[minmax(0,1fr)_360px]"
                >
                    <!-- LEFT -->
                    <section class="min-w-0 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-[0_8px_30px_rgba(15,23,42,0.05)]">

                        <!-- CART TOOLBAR -->
                        <div class="flex min-w-0 flex-wrap items-center justify-between gap-3 border-b border-[#EEF1F2] bg-white px-4 py-4 sm:px-5">
                            <label class="flex cursor-pointer items-center gap-2.5">
                                <input
                                    type="checkbox"
                                    :checked="allSelected"
                                    @change="toggleAll"
                                    class="h-4 w-4 rounded border-gray-300 text-[#087F8C] focus:ring-[#16A6A0]"
                                />

                                <span class="text-xs font-bold text-[#334155]">
                                    Select All
                                </span>

                                <span class="text-[10px] text-[#94A3B8]">
                                    {{ selectedCount }} of {{ items.length }} selected
                                </span>
                            </label>

                            <button
                                v-if="hasSelection"
                                type="button"
                                @click="removeSelected"
                                class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-bold text-[#E85D5D] transition hover:bg-[#FFF1F1]"
                            >
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V5h6v2m-7 0 1 13h6l1-13M10 11v5m4-5v5" />
                                </svg>
                                Remove Selected
                            </button>
                        </div>

                        <!-- ITEMS -->
                        <div class="divide-y divide-[#EEF1F2]">
                            <article
                                v-for="item in items"
                                :key="item.id"
                                class="relative min-w-0 p-4 transition sm:p-5"
                                :class="selectedItems.includes(Number(item.id)) ? 'bg-[#F3FBFA]' : 'bg-white'"
                            >
                                <div
                                    v-if="selectedItems.includes(Number(item.id))"
                                    class="absolute inset-y-0 left-0 w-1 bg-[#087F8C]"
                                ></div>

                                <div class="flex min-w-0 gap-3 sm:gap-4">
                                    <!-- CHECK -->
                                    <div class="shrink-0 pt-1">
                                        <input
                                            type="checkbox"
                                            :checked="selectedItems.includes(Number(item.id))"
                                            @change="toggleItem(item)"
                                            class="h-4 w-4 rounded border-gray-300 text-[#087F8C] focus:ring-[#16A6A0] sm:h-5 sm:w-5"
                                        />
                                    </div>

                                    <!-- IMAGE -->
                                    <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-[#E8F7F6] ring-1 ring-[#D9EEEC] sm:h-24 sm:w-24">
                                        <img
                                            v-if="item.product?.image_path"
                                            :src="imageUrl(item.product.image_path)"
                                            :alt="item.product?.name || 'Product'"
                                            class="h-full w-full object-cover"
                                            loading="lazy"
                                        />

                                        <span v-else class="text-3xl">🛍️</span>
                                    </div>

                                    <!-- CONTENT -->
                                    <div class="min-w-0 flex-1">
                                        <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:justify-between">
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-[9px] font-bold uppercase tracking-[0.12em] text-[#087F8C]">
                                                    {{ item.product?.category?.name || 'Product' }}
                                                </p>

                                                <h2 class="mt-1 line-clamp-2 text-sm font-extrabold leading-5 text-[#1F2937]">
                                                    {{ item.product?.name || 'Product' }}
                                                </h2>

                                                <p
                                                    v-if="item.variant && variantLabel(item)"
                                                    class="mt-1 truncate text-[11px] font-medium text-[#64748B]"
                                                >
                                                    {{ variantLabel(item) }}
                                                </p>

                                                <p class="mt-2 text-sm font-extrabold text-[#087F8C]">
                                                    {{ money(item.product?.price) }}
                                                </p>
                                            </div>

                                            <div class="shrink-0 sm:w-[100px] sm:text-right">
                                                <p class="text-[9px] font-semibold uppercase tracking-wide text-[#94A3B8]">
                                                    Item total
                                                </p>

                                                <p class="mt-1 text-sm font-extrabold text-[#1F2937]">
                                                    {{ money(itemTotal(item)) }}
                                                </p>
                                            </div>
                                        </div>

                                        <!-- CONTROLS -->
                                        <div class="mt-3 flex min-w-0 flex-wrap items-center gap-3">
                                            <div class="flex shrink-0 items-center overflow-hidden rounded-lg border border-[#DDE5E4] bg-white">
                                                <button
                                                    type="button"
                                                    @click="decreaseQuantity(item)"
                                                    :disabled="Number(item.quantity) <= 1"
                                                    class="flex h-8 w-8 items-center justify-center text-sm font-bold text-[#64748B] transition hover:bg-[#E8F7F6] hover:text-[#087F8C] disabled:cursor-not-allowed disabled:opacity-40"
                                                >
                                                    −
                                                </button>

                                                <span class="flex h-8 min-w-9 items-center justify-center border-x border-[#DDE5E4] px-2 text-xs font-bold text-[#1F2937]">
                                                    {{ item.quantity }}
                                                </span>

                                                <button
                                                    type="button"
                                                    @click="increaseQuantity(item)"
                                                    :disabled="Number(item.quantity) >= stockFor(item)"
                                                    class="flex h-8 w-8 items-center justify-center text-sm font-bold text-[#64748B] transition hover:bg-[#E8F7F6] hover:text-[#087F8C] disabled:cursor-not-allowed disabled:opacity-40"
                                                >
                                                    +
                                                </button>
                                            </div>

                                            <span class="inline-flex items-center gap-1 text-[10px] font-medium text-[#94A3B8]">
                                                <span class="h-1.5 w-1.5 rounded-full bg-[#22A06B]"></span>
                                                {{ stockFor(item) }} available
                                            </span>

                                            <button
                                                type="button"
                                                @click="remove(item)"
                                                class="rounded-lg px-2 py-1 text-xs font-bold text-[#E85D5D] transition hover:bg-[#FFF1F1]"
                                            >
                                                Remove
                                            </button>
                                        </div>

                                        <p
                                            v-if="stockFor(item) <= 0"
                                            class="mt-2.5 rounded-lg bg-[#FFF1F1] px-2.5 py-2 text-[10px] font-bold text-[#C24141]"
                                        >
                                            This item is currently out of stock.
                                        </p>

                                        <p
                                            v-else-if="Number(item.quantity) > stockFor(item)"
                                            class="mt-2.5 rounded-lg bg-[#FFF7E5] px-2.5 py-2 text-[10px] font-bold text-[#9A6B08]"
                                        >
                                            Requested quantity exceeds available stock.
                                        </p>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </section>

                    <!-- SUMMARY -->
                    <aside class="h-fit min-w-0 rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-[0_8px_30px_rgba(15,23,42,0.05)] sm:p-5 lg:sticky lg:top-24">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[9px] font-bold uppercase tracking-[0.14em] text-[#087F8C]">
                                    Checkout
                                </p>
                                <h2 class="mt-0.5 text-base font-extrabold text-[#1F2937]">
                                    Order Summary
                                </h2>
                            </div>

                            <span class="shrink-0 rounded-full bg-[#E8F7F6] px-2.5 py-1 text-[10px] font-bold text-[#087F8C]">
                                {{ selectedCount }} selected
                            </span>
                        </div>

                        <div class="mt-5 space-y-3 text-xs">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-[#64748B]">Selected items</span>
                                <span class="font-bold text-[#1F2937]">{{ selectedCount }}</span>
                            </div>

                            <div class="flex items-center justify-between gap-3">
                                <span class="text-[#64748B]">Total quantity</span>
                                <span class="font-bold text-[#1F2937]">{{ selectedQuantity }}</span>
                            </div>

                            <div class="border-t border-[#EEF1F2] pt-3 flex items-center justify-between gap-3">
                                <span class="text-[#64748B]">Subtotal</span>
                                <span class="font-bold text-[#1F2937]">{{ money(subtotal) }}</span>
                            </div>

                            <div class="flex items-start justify-between gap-3">
                                <span class="text-[#64748B]">Shipping</span>
                                <span class="text-right text-[10px] font-bold leading-4 text-[#22A06B]">
                                    Calculated at checkout
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 rounded-xl bg-[#F8FAF9] px-3.5 py-3.5">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-sm font-extrabold text-[#1F2937]">Total</span>
                                <span class="text-xl font-extrabold text-[#087F8C]">
                                    {{ money(subtotal) }}
                                </span>
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="proceedToCheckout"
                            :disabled="!hasSelection"
                            class="mt-4 w-full rounded-xl bg-[#087F8C] px-4 py-3 text-xs font-bold text-white shadow-sm transition hover:bg-[#066C76] disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{ hasSelection ? `Proceed to Checkout (${selectedCount})` : 'Select Items to Checkout' }}
                        </button>

                        <Link
                            :href="safeRoute('buyer.products')"
                            class="mt-2.5 flex w-full items-center justify-center rounded-xl border border-[#DDE5E4] bg-white px-4 py-3 text-xs font-bold text-[#334155] transition hover:border-[#B7DFDC] hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                        >
                            Continue Shopping
                        </Link>

                        <div class="mt-4 rounded-xl border border-[#D9EEEC] bg-[#E8F7F6] px-3.5 py-3">
                            <div class="flex gap-2">
                                <span class="shrink-0 text-sm">🔒</span>
                                <p class="text-[10px] leading-5 text-[#64748B]">
                                    Only selected items will be sent to checkout. Everything else stays safely in your cart.
                                </p>
                            </div>
                        </div>

                        <div class="mt-5 grid grid-cols-3 gap-2 border-t border-[#EEF1F2] pt-4">
                            <div class="text-center">
                                <div class="text-base">🚚</div>
                                <p class="mt-1 truncate text-[9px] font-bold text-[#64748B]">
                                    Fast Delivery
                                </p>
                            </div>

                            <div class="text-center">
                                <div class="text-base">🔒</div>
                                <p class="mt-1 truncate text-[9px] font-bold text-[#64748B]">
                                    Secure Payment
                                </p>
                            </div>

                            <div class="text-center">
                                <div class="text-base">↩️</div>
                                <p class="mt-1 truncate text-[9px] font-bold text-[#64748B]">
                                    Easy Returns
                                </p>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </main>
    </BuyerLayout>
</template>
