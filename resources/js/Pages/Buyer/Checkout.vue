<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },

    selected_items: {
        type: Array,
        default: () => [],
    },

    addresses: {
        type: Array,
        default: () => [],
    },
})

const selectedItems = ref(
    props.selected_items
        .map(id => Number(id))
        .filter(id => Number.isInteger(id) && id > 0)
)

const form = useForm({
    shipping_address: '',
    payment_method: 'cod',
    voucher_code: '',
    selected_items: [...selectedItems.value],
})

const voucherInput = ref('')
const appliedVoucher = ref(null)
const voucherError = ref('')
const voucherLoading = ref(false)

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

const money = value =>
    `₱${Number(value || 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`

const variantLabel = item => {
    if (!item.variant) return ''

    return [
        item.variant.color,
        item.variant.size,
    ]
        .filter(Boolean)
        .join(' / ')
}

const itemPrice = item =>
    Number(
        item.product?.price ??
        item.price ??
        0
    )

const itemQuantity = item =>
    Number(item.quantity || 0)

const subtotal = computed(() =>
    props.items.reduce(
        (total, item) =>
            total + itemPrice(item) * itemQuantity(item),
        0
    )
)

const discount = computed(() =>
    Number(appliedVoucher.value?.discount || 0)
)

const total = computed(() =>
    Math.max(0, subtotal.value - discount.value)
)

const totalQuantity = computed(() =>
    props.items.reduce(
        (total, item) => total + itemQuantity(item),
        0
    )
)

const hasItems = computed(() =>
    props.items.length > 0
)

const selectAddress = address => {
    form.shipping_address =
        address.address ||
        address.full_address ||
        ''
}

const applyVoucher = async () => {
    const code = voucherInput.value.trim()

    if (!code) {
        voucherError.value = 'Please enter a voucher code.'
        return
    }

    if (!selectedItems.value.length) {
        voucherError.value = 'No cart items are selected.'
        return
    }

    voucherLoading.value = true
    voucherError.value = ''

    try {
        const csrfToken =
            document.querySelector(
                'meta[name="csrf-token"]'
            )?.content

        const response = await fetch(
            route('buyer.vouchers.validate'),
            {
                method: 'POST',

                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',

                    ...(csrfToken
                        ? {
                              'X-CSRF-TOKEN': csrfToken,
                          }
                        : {}),
                },

                body: JSON.stringify({
                    voucher_code: code,
                    selected_items: selectedItems.value,
                }),
            }
        )

        const data = await response.json()

        if (response.ok && data.valid) {
            appliedVoucher.value = data.voucher
            form.voucher_code = data.voucher.code
            voucherError.value = ''
            return
        }

        appliedVoucher.value = null
        form.voucher_code = ''

        voucherError.value =
            data.message ||
            'Invalid voucher code.'
    } catch (error) {
        console.error(
            'Voucher validation error:',
            error
        )

        appliedVoucher.value = null
        form.voucher_code = ''

        voucherError.value =
            'Failed to validate voucher. Please try again.'
    } finally {
        voucherLoading.value = false
    }
}

const removeVoucher = () => {
    appliedVoucher.value = null
    voucherInput.value = ''
    form.voucher_code = ''
    voucherError.value = ''
}

const submit = () => {
    if (!selectedItems.value.length) return
    if (!form.shipping_address.trim()) return

    form.selected_items = [
        ...selectedItems.value,
    ]

    form.post(
        route('buyer.orders.store'),
        {
            preserveScroll: true,

            onError: errors => {
                console.error(
                    'Checkout validation errors:',
                    errors
                )
            },
        }
    )
}
</script>

<template>
    <Head title="Checkout" />

    <BuyerLayout>
        <main class="min-h-[calc(100vh-80px)] w-full min-w-0 overflow-x-hidden bg-[#F8FAF9]">
            <div class="mx-auto w-full max-w-[1500px] px-4 py-6 pb-12 sm:px-6 sm:py-7 lg:px-8 lg:py-8 lg:pb-14">

                <!-- HEADER -->
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#F4B942]"></span>
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#087F8C] sm:text-xs">
                                Secure checkout
                            </p>
                        </div>

                        <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-[#1F2937] sm:text-3xl">
                            Checkout
                        </h1>

                        <p class="mt-1 max-w-2xl text-xs leading-5 text-[#64748B] sm:text-sm">
                            Review your order, choose your delivery address, and place your order.
                        </p>
                    </div>

                    <Link
                        :href="route('buyer.cart')"
                        class="inline-flex w-fit items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-bold text-[#087F8C] transition hover:bg-[#E8F7F6]"
                    >
                        <span>←</span>
                        Back to Cart
                    </Link>
                </div>

                <!-- EMPTY -->
                <div
                    v-if="!hasItems"
                    class="mt-7 rounded-2xl border border-dashed border-[#D9E3E2] bg-white px-5 py-14 text-center shadow-sm sm:mt-8"
                >
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-[#E8F7F6] text-3xl">
                        🛒
                    </div>

                    <h2 class="mt-4 text-base font-extrabold text-[#1F2937]">
                        No items selected
                    </h2>

                    <p class="mx-auto mt-1.5 max-w-md text-xs leading-5 text-[#64748B] sm:text-sm">
                        Return to your cart and select the items you want to checkout.
                    </p>

                    <Link
                        :href="route('buyer.cart')"
                        class="mt-5 inline-flex rounded-xl bg-[#087F8C] px-5 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#066C76]"
                    >
                        Return to Cart
                    </Link>
                </div>

                <!-- CHECKOUT -->
                <form
                    v-else
                    class="mt-7 grid min-w-0 gap-5 lg:mt-8 lg:grid-cols-[minmax(0,1fr)_350px] xl:grid-cols-[minmax(0,1fr)_370px]"
                    @submit.prevent="submit"
                >
                    <!-- LEFT COLUMN -->
                    <div class="min-w-0 space-y-5">

                        <!-- ITEMS -->
                        <section class="min-w-0 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-[0_8px_30px_rgba(15,23,42,0.05)]">
                            <div class="flex min-w-0 items-start justify-between gap-3 border-b border-[#EEF1F2] px-4 py-4 sm:px-5">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="h-1.5 w-1.5 rounded-full bg-[#F4B942]"></span>
                                        <p class="text-[9px] font-bold uppercase tracking-[0.15em] text-[#087F8C]">
                                            Your order
                                        </p>
                                    </div>

                                    <h2 class="mt-1 text-sm font-extrabold text-[#1F2937]">
                                        Items to checkout
                                    </h2>

                                    <p class="mt-1 text-xs leading-5 text-[#64748B]">
                                        Only your selected cart items are included.
                                    </p>
                                </div>

                                <span class="shrink-0 rounded-full bg-[#E8F7F6] px-2.5 py-1 text-[10px] font-bold text-[#087F8C]">
                                    {{ items.length }}
                                    {{ items.length === 1 ? 'item' : 'items' }}
                                </span>
                            </div>

                            <div class="divide-y divide-[#EEF1F2] px-4 sm:px-5">
                                <div
                                    v-for="item in items"
                                    :key="item.id"
                                    class="flex min-w-0 gap-3 py-4 first:pt-4 last:pb-5 sm:gap-4"
                                >
                                    <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-gradient-to-br from-[#F8FAF9] to-[#E8F7F6] ring-1 ring-[#D9EEEC] sm:h-20 sm:w-20">
                                        <img
                                            v-if="item.product?.image_path"
                                            :src="imageUrl(item.product.image_path)"
                                            :alt="item.product?.name || 'Product'"
                                            class="h-full w-full object-cover"
                                            loading="lazy"
                                        />

                                        <span v-else class="text-xl sm:text-2xl">
                                            🛍️
                                        </span>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-[9px] font-bold uppercase tracking-[0.12em] text-[#087F8C]">
                                            {{ item.product?.category?.name || 'Product' }}
                                        </p>

                                        <h3 class="mt-1 line-clamp-2 text-xs font-extrabold leading-5 text-[#1F2937] sm:text-sm">
                                            {{ item.product?.name || 'Product' }}
                                        </h3>

                                        <p
                                            v-if="variantLabel(item)"
                                            class="mt-1 truncate text-[10px] font-medium text-[#64748B]"
                                        >
                                            {{ variantLabel(item) }}
                                        </p>

                                        <p class="mt-1.5 text-xs text-[#64748B]">
                                            {{ money(itemPrice(item)) }}
                                            <span class="px-1 text-[#CBD5E1]">×</span>
                                            {{ itemQuantity(item) }}
                                        </p>
                                    </div>

                                    <div class="w-[78px] shrink-0 text-right sm:w-[100px]">
                                        <p class="text-[9px] font-semibold uppercase tracking-wide text-[#94A3B8]">
                                            Total
                                        </p>

                                        <p class="mt-1 break-words text-xs font-extrabold text-[#1F2937] sm:text-sm">
                                            {{ money(itemPrice(item) * itemQuantity(item)) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- ADDRESS -->
                        <section class="min-w-0 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-[0_8px_30px_rgba(15,23,42,0.05)]">
                            <div class="flex min-w-0 items-start justify-between gap-4 border-b border-[#EEF1F2] px-4 py-4 sm:px-5">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm">📍</span>
                                        <p class="text-[9px] font-bold uppercase tracking-[0.15em] text-[#087F8C]">
                                            Shipping
                                        </p>
                                    </div>

                                    <h2 class="mt-1 text-sm font-extrabold text-[#1F2937]">
                                        Delivery address
                                    </h2>

                                    <p class="mt-1 text-xs leading-5 text-[#64748B]">
                                        Choose where you want your order delivered.
                                    </p>
                                </div>

                                <span class="shrink-0 rounded-full bg-[#FFF7E5] px-2.5 py-1 text-[10px] font-bold text-[#9A6B08]">
                                    Required
                                </span>
                            </div>

                            <div class="px-4 py-4 sm:px-5 sm:py-5">
                                <div
                                    v-if="!addresses.length"
                                    class="rounded-xl border border-dashed border-[#D9E3E2] bg-[#F8FAF9] px-4 py-8 text-center"
                                >
                                    <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-[#E8F7F6] text-xl">
                                        📍
                                    </div>

                                    <h3 class="mt-3 text-xs font-extrabold text-[#1F2937]">
                                        No saved addresses
                                    </h3>

                                    <p class="mx-auto mt-1 max-w-sm text-[10px] leading-4 text-[#64748B]">
                                        Please add a delivery address from your account before placing an order.
                                    </p>
                                </div>

                                <div
                                    v-else
                                    class="grid min-w-0 gap-3 sm:grid-cols-2"
                                >
                                    <button
                                        v-for="address in addresses"
                                        :key="address.id"
                                        type="button"
                                        class="min-w-0 rounded-xl border p-4 text-left transition"
                                        :class="
                                            form.shipping_address ===
                                            (address.address || address.full_address || '')
                                                ? 'border-[#16A6A0] bg-[#E8F7F6] ring-2 ring-[#D5F0EE]'
                                                : 'border-[#E5E7EB] bg-[#F8FAF9] hover:border-[#B7DFDC] hover:bg-white'
                                        "
                                        @click="selectAddress(address)"
                                    >
                                        <div class="flex min-w-0 items-start justify-between gap-3">
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-xs font-extrabold text-[#1F2937]">
                                                    {{ address.label || address.name || 'Saved Address' }}
                                                </p>

                                                <p class="mt-1 break-words text-xs leading-5 text-[#64748B]">
                                                    {{ address.address || address.full_address }}
                                                </p>
                                            </div>

                                            <span
                                                v-if="address.is_default"
                                                class="shrink-0 rounded-full bg-[#EAF8F1] px-2 py-1 text-[9px] font-bold text-[#22A06B]"
                                            >
                                                Default
                                            </span>
                                        </div>

                                        <div
                                            v-if="
                                                form.shipping_address ===
                                                (address.address || address.full_address || '')
                                            "
                                            class="mt-3 flex items-center gap-1.5 text-[10px] font-bold text-[#087F8C]"
                                        >
                                            <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-[#087F8C] text-[8px] text-white">
                                                ✓
                                            </span>

                                            Selected
                                        </div>
                                    </button>
                                </div>

                                <p
                                    v-if="form.errors.shipping_address"
                                    class="mt-3 rounded-lg bg-[#FFF1F1] px-3 py-2 text-xs font-medium text-[#C24141]"
                                >
                                    {{ form.errors.shipping_address }}
                                </p>
                            </div>
                        </section>

                        <!-- PAYMENT -->
                        <section class="min-w-0 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-[0_8px_30px_rgba(15,23,42,0.05)]">
                            <div class="border-b border-[#EEF1F2] px-4 py-4 sm:px-5">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm">💳</span>
                                    <p class="text-[9px] font-bold uppercase tracking-[0.15em] text-[#087F8C]">
                                        Payment
                                    </p>
                                </div>

                                <h2 class="mt-1 text-sm font-extrabold text-[#1F2937]">
                                    Payment method
                                </h2>

                                <p class="mt-1 text-xs leading-5 text-[#64748B]">
                                    Select how you want to pay for this order.
                                </p>
                            </div>

                            <div class="p-4 sm:p-5">
                                <label
                                    class="flex min-w-0 cursor-pointer items-center gap-3 rounded-xl border p-3.5 transition sm:p-4"
                                    :class="
                                        form.payment_method === 'cod'
                                            ? 'border-[#16A6A0] bg-[#E8F7F6] ring-2 ring-[#D5F0EE]'
                                            : 'border-[#E5E7EB] bg-[#F8FAF9]'
                                    "
                                >
                                    <input
                                        v-model="form.payment_method"
                                        type="radio"
                                        value="cod"
                                        required
                                        class="h-4 w-4 shrink-0 border-gray-300 text-[#087F8C] focus:ring-[#16A6A0]"
                                    />

                                    <span class="min-w-0 flex-1">
                                        <strong class="block text-xs font-extrabold text-[#1F2937]">
                                            Cash on Delivery
                                        </strong>

                                        <small class="mt-1 block text-xs leading-4 text-[#64748B]">
                                            Pay with cash when your order arrives.
                                        </small>
                                    </span>

                                    <span class="hidden shrink-0 rounded-full bg-white px-2.5 py-1 text-[9px] font-bold text-[#087F8C] sm:inline-flex">
                                        Available
                                    </span>
                                </label>

                                <p
                                    v-if="form.errors.payment_method"
                                    class="mt-2 rounded-lg bg-[#FFF1F1] px-3 py-2 text-xs font-medium text-[#C24141]"
                                >
                                    {{ form.errors.payment_method }}
                                </p>
                            </div>
                        </section>
                    </div>

                    <!-- RIGHT COLUMN -->
                    <aside class="min-w-0 space-y-4 lg:sticky lg:top-24 lg:self-start">

                        <!-- SUMMARY -->
                        <section class="h-fit min-w-0 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-[0_8px_30px_rgba(15,23,42,0.06)] sm:p-5">
                            <div class="flex min-w-0 items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[9px] font-bold uppercase tracking-[0.15em] text-[#087F8C]">
                                        Summary
                                    </p>

                                    <h2 class="mt-1 text-sm font-extrabold text-[#1F2937]">
                                        Order summary
                                    </h2>
                                </div>

                                <span class="shrink-0 rounded-full bg-[#E8F7F6] px-2.5 py-1 text-[10px] font-bold text-[#087F8C]">
                                    {{ totalQuantity }}
                                    {{ totalQuantity === 1 ? 'item' : 'items' }}
                                </span>
                            </div>

                            <div class="mt-5 space-y-3">
                                <div
                                    v-for="item in items"
                                    :key="`summary-${item.id}`"
                                    class="flex min-w-0 items-start justify-between gap-3 text-xs"
                                >
                                    <span class="min-w-0 flex-1 text-[#64748B]">
                                        <span class="block line-clamp-2 break-words">
                                            {{ item.product?.name }}
                                        </span>

                                        <span
                                            v-if="variantLabel(item)"
                                            class="mt-0.5 block truncate text-[10px] text-[#94A3B8]"
                                        >
                                            {{ variantLabel(item) }}
                                        </span>

                                        <span class="text-[10px] text-[#94A3B8]">
                                            × {{ itemQuantity(item) }}
                                        </span>
                                    </span>

                                    <span class="shrink-0 text-right font-bold text-[#1F2937]">
                                        {{ money(itemPrice(item) * itemQuantity(item)) }}
                                    </span>
                                </div>
                            </div>

                            <div class="mt-4 space-y-2 border-t border-[#EEF1F2] pt-4 text-xs">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-[#64748B]">Subtotal</span>
                                    <span class="font-bold text-[#1F2937]">
                                        {{ money(subtotal) }}
                                    </span>
                                </div>

                                <div
                                    v-if="discount > 0"
                                    class="flex items-center justify-between gap-3 text-xs"
                                >
                                    <span class="min-w-0 truncate text-[#22A06B]">
                                        Discount
                                        <span v-if="appliedVoucher?.code">
                                            ({{ appliedVoucher.code }})
                                        </span>
                                    </span>

                                    <span class="shrink-0 font-bold text-[#22A06B]">
                                        -{{ money(discount) }}
                                    </span>
                                </div>

                                <div class="flex items-start justify-between gap-3">
                                    <span class="text-[#64748B]">Shipping</span>

                                    <span class="text-right text-[10px] font-medium leading-4 text-[#94A3B8]">
                                        Calculated at checkout
                                    </span>
                                </div>
                            </div>

                            <div class="mt-4 rounded-xl bg-[#F8FAF9] px-3.5 py-3.5">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-extrabold text-[#1F2937]">
                                        Total
                                    </span>

                                    <span class="shrink-0 text-xl font-extrabold text-[#087F8C]">
                                        {{ money(total) }}
                                    </span>
                                </div>
                            </div>

                            <button
                                type="submit"
                                :disabled="
                                    form.processing ||
                                    !form.shipping_address.trim() ||
                                    !selectedItems.length
                                "
                                class="mt-4 w-full rounded-xl bg-[#087F8C] px-4 py-3 text-xs font-bold text-white shadow-sm transition hover:bg-[#066C76] disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{ form.processing ? 'Placing order...' : 'Place Order' }}
                            </button>

                            <p
                                v-if="form.processing"
                                class="mt-2 text-center text-[10px] leading-4 text-[#94A3B8]"
                            >
                                Please wait while we create your order.
                            </p>

                            <p
                                v-if="!form.shipping_address"
                                class="mt-3 flex items-center justify-center gap-1 text-center text-[10px] leading-4 text-[#9A6B08]"
                            >
                                <span>⚠️</span>
                                Select a delivery address to continue.
                            </p>

                            <Link
                                :href="route('buyer.cart')"
                                class="mt-4 block text-center text-xs font-bold text-[#087F8C] transition hover:text-[#066C76]"
                            >
                                ← Back to cart
                            </Link>
                        </section>

                        <!-- PROMO -->
                        <section class="min-w-0 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-[0_8px_30px_rgba(15,23,42,0.04)] sm:p-5">
                            <div class="flex items-center gap-2">
                                <span class="text-sm">🏷️</span>
                                <p class="text-[9px] font-bold uppercase tracking-[0.15em] text-[#087F8C]">
                                    Savings
                                </p>
                            </div>

                            <h3 class="mt-1 text-sm font-extrabold text-[#1F2937]">
                                Promo Code
                            </h3>

                            <p class="mt-1 text-xs leading-5 text-[#64748B]">
                                Apply a voucher to save on your selected items.
                            </p>

                            <div
                                v-if="appliedVoucher"
                                class="mt-4 rounded-xl border border-[#D6F0E2] bg-[#EAF8F1] p-3.5"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-extrabold text-[#1F2937]">
                                            {{ appliedVoucher.code }}
                                        </p>

                                        <p class="mt-1 text-[10px] font-medium text-[#22A06B]">
                                            {{
                                                appliedVoucher.type === 'percentage' ||
                                                appliedVoucher.type === 'percent' ||
                                                appliedVoucher.type === '%'
                                                    ? `${Number(appliedVoucher.value)}% off`
                                                    : `${money(appliedVoucher.value)} off`
                                            }}
                                        </p>
                                    </div>

                                    <button
                                        type="button"
                                        @click="removeVoucher"
                                        class="shrink-0 rounded-lg px-2.5 py-1.5 text-[10px] font-bold text-[#188154] transition hover:bg-white"
                                    >
                                        Remove
                                    </button>
                                </div>

                                <div
                                    v-if="appliedVoucher.eligible_subtotal !== undefined"
                                    class="mt-3 border-t border-[#D6F0E2] pt-3 text-[10px] text-[#188154]"
                                >
                                    Eligible subtotal:
                                    {{ money(appliedVoucher.eligible_subtotal) }}
                                </div>
                            </div>

                            <div
                                v-else
                                class="mt-4"
                            >
                                <div class="flex min-w-0 gap-2">
                                    <input
                                        v-model="voucherInput"
                                        type="text"
                                        autocomplete="off"
                                        placeholder="Enter code"
                                        class="min-w-0 flex-1 rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2.5 text-xs uppercase outline-none transition placeholder:text-[#94A3B8] focus:border-[#16A6A0] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                                        @keydown.enter.prevent="applyVoucher"
                                    />

                                    <button
                                        type="button"
                                        @click="applyVoucher"
                                        :disabled="voucherLoading || !voucherInput.trim()"
                                        class="shrink-0 rounded-xl bg-[#087F8C] px-3 py-2.5 text-xs font-bold text-white transition hover:bg-[#066C76] disabled:cursor-not-allowed disabled:opacity-50 sm:px-3.5"
                                    >
                                        {{ voucherLoading ? 'Checking...' : 'Apply' }}
                                    </button>
                                </div>

                                <p
                                    v-if="voucherError"
                                    class="mt-2 rounded-lg bg-[#FFF1F1] px-3 py-2 text-[10px] font-medium leading-4 text-[#C24141]"
                                >
                                    {{ voucherError }}
                                </p>
                            </div>
                        </section>

                        <!-- COD -->
                        <section class="min-w-0 rounded-2xl border border-[#F2D98F] bg-[#FFF7E5] p-4">
                            <div class="flex min-w-0 gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-base shadow-sm">
                                    💵
                                </div>

                                <div class="min-w-0">
                                    <h3 class="text-xs font-extrabold text-[#7C5A0B]">
                                        Cash on Delivery
                                    </h3>

                                    <p class="mt-1 text-[10px] leading-4 text-[#9A6B08]">
                                        Prepare sufficient cash when your order arrives.
                                    </p>
                                </div>
                            </div>
                        </section>

                        <!-- TRUST -->
                        <section class="min-w-0 rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-[0_8px_30px_rgba(15,23,42,0.04)] sm:p-5">
                            <div class="grid grid-cols-3 gap-2 sm:gap-3">
                                <div class="min-w-0 text-center">
                                    <div class="mx-auto flex h-9 w-9 items-center justify-center rounded-xl bg-[#E8F7F6] text-sm">
                                        🔒
                                    </div>

                                    <p class="mt-2 truncate text-[9px] font-bold text-[#64748B]">
                                        Secure
                                    </p>
                                </div>

                                <div class="min-w-0 text-center">
                                    <div class="mx-auto flex h-9 w-9 items-center justify-center rounded-xl bg-[#E8F7F6] text-sm">
                                        🚚
                                    </div>

                                    <p class="mt-2 truncate text-[9px] font-bold text-[#64748B]">
                                        Delivery
                                    </p>
                                </div>

                                <div class="min-w-0 text-center">
                                    <div class="mx-auto flex h-9 w-9 items-center justify-center rounded-xl bg-[#FFF7E5] text-sm">
                                        ✓
                                    </div>

                                    <p class="mt-2 truncate text-[9px] font-bold text-[#64748B]">
                                        Verified
                                    </p>
                                </div>
                            </div>
                        </section>
                    </aside>
                </form>
            </div>
        </main>
    </BuyerLayout>
</template>
