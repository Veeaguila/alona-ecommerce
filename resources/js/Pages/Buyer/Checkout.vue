<script setup>
import Icon from '@/Components/Icon.vue'
import { ref, computed, watch } from 'vue'
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
    address_serviceability: {
        type: [Object, Array],
        default: () => ({}),
    },
    hub_routes_required: { type: Boolean, default: false },
    shipping_rate_cards: { type: [Object, Array], default: () => ({}) },

    shipping_options: {
        type: Array,
        default: () => [],
    },

    seller_count: {
        type: Number,
        default: 1,
    },

    free_shipping_threshold: {
        type: [Number, String],
        default: null,
    },

    default_shipping_method: {
        type: String,
        default: 'standard',
    },

    payment_options: {
        type: Array,
        default: () => [],
    },

    saved_payment_methods: {
        type: Array,
        default: () => [],
    },
})

const sellerCount = computed(() => Math.max(1, props.seller_count))

const selectedItems = ref(
    props.selected_items
        .map(id => Number(id))
        .filter(id => Number.isInteger(id) && id > 0)
)

const form = useForm({
    shipping_address: '',
    shipping_method: props.default_shipping_method,
    payment_method: 'cod',
    payment_method_id: null,
    voucher_code: '',
    voucher_codes: [],
    expected_prices: {},
    expected_shipping_fee: null,
    selected_items: [...selectedItems.value],
})

// Preselect the buyer's default saved method for the chosen payment type.
watch(
    () => form.payment_method,
    type => {
        const saved = props.saved_payment_methods.filter(
            method => method.type === type
        )

        form.payment_method_id =
            (saved.find(method => method.is_default) || saved[0])?.id ?? null
    }
)

const voucherInput = ref('')
const appliedVouchers = ref({
    store: null,
    platform: null,
    shipping: null,
})
const appliedVoucherList = computed(() => {
    return Object.values(appliedVouchers.value).filter(Boolean)
})
const appliedVoucher = computed(() => {
    return appliedVoucherList.value[0] || null
})
const voucherError = ref('')
const voucherLoading = ref(false)

const syncFormVouchers = () => {
    const codes = appliedVoucherList.value.map(v => v.code)
    form.voucher_codes = codes
    form.voucher_code = codes.join(',')
}

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

const itemPrice = item => {
    const eff = Number(item.effective_price ?? item.unit_price ?? item.price ?? 0)
    if (eff > 0) return eff
    const varPrice = Number(item.variant?.price ?? 0)
    if (varPrice > 0) return varPrice
    return Number(item.product?.price ?? 0)
}

const itemQuantity = item =>
    Number(item.quantity || 0)

const activeItems = computed(() =>
    props.items.filter(item => selectedItems.value.includes(Number(item.id)))
)

const subtotal = computed(() =>
    activeItems.value.reduce(
        (total, item) =>
            total + itemPrice(item) * itemQuantity(item),
        0
    )
)

const itemDiscount = computed(() => {
    const storeDisc = Number(appliedVouchers.value.store?.discount || 0)
    const platDisc = Number(appliedVouchers.value.platform?.discount || 0)
    return Math.min(subtotal.value, storeDisc + platDisc)
})

const discount = computed(() => itemDiscount.value)

const merchandiseTotal = computed(() =>
    Math.max(0, subtotal.value - discount.value)
)

const isAddressUnserviceable = computed(() => form.shipping_method !== 'pickup' && selectedServiceability.value?.state === 'unsupported')

const calculateStoreShipping = (option, sellerItems) => {
    if (option.key === 'pickup') return 0
    if (
        option.key !== 'express' &&
        props.free_shipping_threshold !== null &&
        props.free_shipping_threshold !== undefined &&
        sellerItems.reduce((sum, item) => sum + itemPrice(item) * itemQuantity(item), 0) >= Number(props.free_shipping_threshold)
    ) {
        return 0
    }

    let sellerWeight = 0
    let hasExplicitWeight = false
    for (const item of sellerItems) {
        const w = Number(item.product?.weight || 0)
        const q = Number(item.quantity || 1)
        if (w > 0) {
            sellerWeight += w * q
            hasExplicitWeight = true
        }
    }

    const billedKg = hasExplicitWeight ? Math.max(1, Math.ceil(sellerWeight)) : 1

    const address = props.addresses.find(address => (address.address || address.full_address || '') === form.shipping_address)
    const cards = props.shipping_rate_cards[address?.id]?.[option.key]
    const tier = hasExplicitWeight ? 'weighted' : 'unweighted'
    const card = cards?.sellers?.[sellerItems[0]?.product?.seller_id]?.[tier] ?? cards?.[tier]
    if (!card) return 0
    return Number(card.base) + Math.max(0, billedKg - 1) * Number(card.per_kg)
}

// Mirrors the server-side calculation (the server is authoritative).
const feeFor = option => {
    const groups = {}
    for (const item of activeItems.value) {
        const sId = item.product?.seller_id || 0
        if (!groups[sId]) groups[sId] = []
        groups[sId].push(item)
    }

    const sellerKeys = Object.keys(groups)
    if (sellerKeys.length === 0) {
        return Number(option.fee_per_seller || 50) * sellerCount.value
    }

    let totalFee = 0
    for (const sId of sellerKeys) {
        totalFee += calculateStoreShipping(option, groups[sId])
    }

    return totalFee
}

const selectedShipping = computed(() =>
    props.shipping_options.find(
        option => option.key === form.shipping_method
    )
)

const rawShippingFee = computed(() =>
    selectedShipping.value ? feeFor(selectedShipping.value) : 0
)

const shippingDiscount = computed(() => {
    const shipVoucher = appliedVouchers.value.shipping
    if (!shipVoucher) return 0
    let cap = rawShippingFee.value
    if (shipVoucher.max_discount && Number(shipVoucher.max_discount) > 0) {
        cap = Math.min(cap, Number(shipVoucher.max_discount))
    } else if (shipVoucher.value && Number(shipVoucher.value) > 0) {
        cap = Math.min(cap, Number(shipVoucher.value))
    }
    return Math.min(rawShippingFee.value, cap)
})

const shippingFee = computed(() =>
    Math.max(0, rawShippingFee.value - shippingDiscount.value)
)

// Auto-remove vouchers if subtotal drops below min_spend (BUY-VOU-007)
watch(subtotal, newSubtotal => {
    for (const [cat, v] of Object.entries(appliedVouchers.value)) {
        if (!v) continue
        const minSpend = Number(v.min_spend || 0)
        if (minSpend > 0) {
            let eligibleSub = newSubtotal
            if (cat === 'store' && v.seller_id) {
                eligibleSub = activeItems.value
                    .filter(i => Number(i.product?.seller_id) === Number(v.seller_id))
                    .reduce((sum, i) => sum + itemPrice(i) * itemQuantity(i), 0)
            }
            if (eligibleSub < minSpend) {
                appliedVouchers.value[cat] = null
                syncFormVouchers()
                voucherError.value = `Voucher ${v.code} was automatically removed because subtotal fell below the minimum spend of ${money(minSpend)}.`
            }
        }
    }
})

const savedFor = type =>
    props.saved_payment_methods.filter(method => method.type === type)

const total = computed(() =>
    merchandiseTotal.value + shippingFee.value
)

const totalQuantity = computed(() =>
    activeItems.value.reduce(
        (total, item) => total + itemQuantity(item),
        0
    )
)

const hasItems = computed(() =>
    activeItems.value.length > 0
)

const selectAddress = address => {
    form.clearErrors('shipping_address')
    form.shipping_address =
        address.address ||
        address.full_address ||
        ''
}

const selectedServiceability = computed(() => {
    const address = props.addresses.find(address => (address.address || address.full_address || '') === form.shipping_address)
    return address ? props.address_serviceability[address.id] : null
})

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
                    applied_codes: appliedVoucherList.value.map(v => v.code),
                    shipping_method: form.shipping_method,
                }),
            }
        )

        const data = await response.json()

        if (response.ok && data.valid) {
            const cat = data.voucher.category || (data.voucher.type === 'free_shipping' ? 'shipping' : (data.voucher.is_platform ? 'platform' : 'store'))
            appliedVouchers.value[cat] = data.voucher
            syncFormVouchers()
            voucherInput.value = ''
            voucherError.value = ''
            return
        }

        voucherError.value =
            data.message ||
            'Invalid voucher code.'
    } catch (error) {
        console.error(
            'Voucher validation error:',
            error
        )

        voucherError.value =
            'Failed to validate voucher. Please try again.'
    } finally {
        voucherLoading.value = false
    }
}

const removeVoucher = category => {
    if (category) {
        appliedVouchers.value[category] = null
    } else {
        appliedVouchers.value = {
            store: null,
            platform: null,
            shipping: null,
        }
    }
    syncFormVouchers()
    voucherError.value = ''
}

const removeItemFromCheckout = itemId => {
    selectedItems.value = selectedItems.value.filter(id => id !== Number(itemId))
    form.selected_items = [...selectedItems.value]
}

const submit = () => {
    if (!selectedItems.value.length) return
    if (!form.shipping_address.trim()) return
    if (form.shipping_method !== 'pickup' && selectedServiceability.value?.state === 'unsupported') return
    if (isAddressUnserviceable.value) return

    form.selected_items = [
        ...selectedItems.value,
    ]

    form.expected_shipping_fee = rawShippingFee.value

    form.expected_prices = activeItems.value.reduce((map, item) => {
        map[item.id] = itemPrice(item)
        return map
    }, {})

    form.post(
        route('buyer.orders.store'),
        {
            preserveScroll: true,

            onError: errors => {
                console.error(
                    'Checkout validation errors:',
                    errors
                )
                if (errors.voucher_code) {
                    appliedVouchers.value = {
                        store: null,
                        platform: null,
                        shipping: null,
                    }
                    syncFormVouchers()
                    voucherError.value = errors.voucher_code
                }
                if (errors.price_changed || errors.selected_items || errors.shipping_address || errors.shipping_fee) {
                    voucherError.value = errors.price_changed || errors.selected_items || errors.shipping_address || errors.shipping_fee
                }
            },
        }
    )
}
</script>

<template>
    <Head title="Checkout" />

    <BuyerLayout>
        <main class="min-h-[calc(100vh-80px)] w-full min-w-0 overflow-x-hidden bg-[#F8FAF9]">
            <div class="mx-auto w-full w-full px-4 py-6 pb-12 sm:px-6 sm:py-7 lg:px-8 2xl:px-10 lg:py-8 lg:pb-14">

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
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-[#E8F7F6] text-3xl"><Icon name="cart" class="h-7 w-7" /></div>

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
                                    {{ activeItems.length }}
                                    {{ activeItems.length === 1 ? 'item' : 'items' }}
                                </span>
                            </div>

                            <div class="divide-y divide-[#EEF1F2] px-4 sm:px-5">
                                <div
                                    v-for="item in activeItems"
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

                                        <span v-else class="text-xl sm:text-2xl"><Icon name="bag" class="h-4 w-4" /></span>
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

                                        <button
                                            v-if="selectedItems.length > 1"
                                            type="button"
                                            @click="removeItemFromCheckout(item.id)"
                                            class="mt-1 text-[10px] font-semibold text-rose-500 hover:text-rose-700 transition"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- ADDRESS -->
                        <section class="min-w-0 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-[0_8px_30px_rgba(15,23,42,0.05)]">
                            <div class="flex min-w-0 items-start justify-between gap-4 border-b border-[#EEF1F2] px-4 py-4 sm:px-5">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm"><Icon name="pin" class="h-4 w-4" /></span>
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
                                    <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-[#E8F7F6] text-xl"><Icon name="pin" class="h-5 w-5" /></div>

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
                                <p v-if="selectedServiceability" class="mt-3 text-sm" :class="selectedServiceability.state === 'unsupported' ? 'text-red-700' : 'text-slate-600'" role="status">{{ selectedServiceability.message }}</p>
                            </div>
                        </section>

                        <!-- SHIPPING METHOD -->
                        <section class="min-w-0 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-[0_8px_30px_rgba(15,23,42,0.05)]">
                            <div class="border-b border-[#EEF1F2] px-4 py-4 sm:px-5">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm"><Icon name="truck" class="h-4 w-4" /></span>
                                    <p class="text-[9px] font-bold uppercase tracking-[0.15em] text-[#087F8C]">
                                        Delivery
                                    </p>
                                </div>

                                <h2 class="mt-1 text-sm font-extrabold text-[#1F2937]">
                                    Shipping method
                                </h2>

                                <p class="mt-1 text-xs leading-5 text-[#64748B]">
                                    Fees are charged per seller
                                    ({{ sellerCount }} {{ sellerCount === 1 ? 'seller' : 'sellers' }} in this order).
                                    <span v-if="free_shipping_threshold">
                                        Free standard shipping on each store order of {{ money(free_shipping_threshold) }} or more.
                                    </span>
                                </p>
                            </div>

                            <div class="space-y-3 p-4 sm:p-5">
                                <label
                                    v-for="option in shipping_options"
                                    :key="option.key"
                                    class="flex min-w-0 cursor-pointer items-center gap-3 rounded-xl border p-3.5 transition sm:p-4"
                                    :class="
                                        form.shipping_method === option.key
                                            ? 'border-[#16A6A0] bg-[#E8F7F6] ring-2 ring-[#D5F0EE]'
                                            : 'border-[#E5E7EB] bg-[#F8FAF9]'
                                    "
                                >
                                    <input
                                        v-model="form.shipping_method"
                                        type="radio"
                                        :value="option.key"
                                        class="h-4 w-4 shrink-0 border-gray-300 text-[#087F8C] focus:ring-[#16A6A0]"
                                    />

                                    <span class="min-w-0 flex-1">
                                        <strong class="block text-xs font-extrabold text-[#1F2937]">
                                            {{ option.label }}
                                        </strong>
                                        <small class="mt-1 block text-xs leading-4 text-[#64748B]">
                                            {{ option.description }}
                                        </small>
                                    </span>

                                    <span class="shrink-0 text-xs font-extrabold text-[#1F2937]">
                                        {{ feeFor(option) === 0 ? 'Free' : money(feeFor(option)) }}
                                    </span>
                                </label>

                                <p
                                    v-if="form.errors.shipping_method"
                                    class="rounded-lg bg-[#FFF1F1] px-3 py-2 text-xs font-medium text-[#C24141]"
                                >
                                    {{ form.errors.shipping_method }}
                                </p>
                            </div>
                        </section>

                        <!-- PAYMENT -->
                        <section class="min-w-0 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-[0_8px_30px_rgba(15,23,42,0.05)]">
                            <div class="border-b border-[#EEF1F2] px-4 py-4 sm:px-5">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm"><Icon name="card" class="h-4 w-4" /></span>
                                        <p class="text-[9px] font-bold uppercase tracking-[0.15em] text-[#087F8C]">
                                            Payment
                                        </p>
                                    </div>

                                    <Link
                                        :href="route('buyer.payment-methods')"
                                        class="text-[10px] font-bold text-[#087F8C] hover:underline"
                                    >
                                        Manage saved methods
                                    </Link>
                                </div>

                                <h2 class="mt-1 text-sm font-extrabold text-[#1F2937]">
                                    Payment method
                                </h2>

                                <p class="mt-1 text-xs leading-5 text-[#64748B]">
                                    Select how you want to pay for this order.
                                </p>
                            </div>

                            <div class="space-y-3 p-4 sm:p-5">
                                <div
                                    v-for="option in payment_options"
                                    :key="option.key"
                                >
                                    <label
                                        class="flex min-w-0 cursor-pointer items-center gap-3 rounded-xl border p-3.5 transition sm:p-4"
                                        :class="
                                            form.payment_method === option.key
                                                ? 'border-[#16A6A0] bg-[#E8F7F6] ring-2 ring-[#D5F0EE]'
                                                : 'border-[#E5E7EB] bg-[#F8FAF9]'
                                        "
                                    >
                                        <input
                                            v-model="form.payment_method"
                                            type="radio"
                                            :value="option.key"
                                            required
                                            class="h-4 w-4 shrink-0 border-gray-300 text-[#087F8C] focus:ring-[#16A6A0]"
                                        />

                                        <span class="min-w-0 flex-1">
                                            <strong class="block text-xs font-extrabold text-[#1F2937]">
                                                {{ option.label }}
                                            </strong>
                                            <small class="mt-1 block text-xs leading-4 text-[#64748B]">
                                                {{ option.description }}
                                            </small>
                                        </span>
                                    </label>

                                    <!-- Saved methods of this type -->
                                    <div
                                        v-if="form.payment_method === option.key && option.online"
                                        class="mt-2 space-y-2 pl-7"
                                    >
                                        <label
                                            v-for="saved in savedFor(option.key)"
                                            :key="saved.id"
                                            class="flex cursor-pointer items-center gap-2 rounded-lg border border-[#E5E7EB] bg-white px-3 py-2 text-xs"
                                        >
                                            <input
                                                v-model="form.payment_method_id"
                                                type="radio"
                                                :value="saved.id"
                                                class="h-3.5 w-3.5 border-gray-300 text-[#087F8C] focus:ring-[#16A6A0]"
                                            />
                                            <span class="font-bold text-[#1F2937]">
                                                {{ saved.brand || option.label }} •••• {{ saved.last_four }}
                                            </span>
                                            <span class="text-[#64748B]">{{ saved.holder_name }}</span>
                                        </label>

                                        <p
                                            v-if="!savedFor(option.key).length"
                                            class="text-[10px] leading-4 text-[#64748B]"
                                        >
                                            No saved {{ option.label }} yet.
                                            <Link :href="route('buyer.payment-methods')" class="font-bold text-[#087F8C] hover:underline">
                                                Add one
                                            </Link>
                                            to pay faster next time.
                                        </p>
                                    </div>
                                </div>

                                <p
                                    v-if="form.errors.payment_method || form.errors.payment_method_id"
                                    class="rounded-lg bg-[#FFF1F1] px-3 py-2 text-xs font-medium text-[#C24141]"
                                >
                                    {{ form.errors.payment_method || form.errors.payment_method_id }}
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
                                    v-for="item in activeItems"
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
                                    v-if="appliedVouchers.store"
                                    class="flex items-center justify-between gap-3 text-xs"
                                >
                                    <span class="min-w-0 truncate text-[#22A06B]">
                                        Store Discount ({{ appliedVouchers.store.code }})
                                    </span>
                                    <span class="shrink-0 font-bold text-[#22A06B]">
                                        -{{ money(appliedVouchers.store.discount) }}
                                    </span>
                                </div>

                                <div
                                    v-if="appliedVouchers.platform"
                                    class="flex items-center justify-between gap-3 text-xs"
                                >
                                    <span class="min-w-0 truncate text-[#22A06B]">
                                        Platform Discount ({{ appliedVouchers.platform.code }})
                                    </span>
                                    <span class="shrink-0 font-bold text-[#22A06B]">
                                        -{{ money(appliedVouchers.platform.discount) }}
                                    </span>
                                </div>

                                <div class="flex items-start justify-between gap-3">
                                    <span class="text-[#64748B]">
                                        Shipping
                                        <span v-if="selectedShipping" class="block text-[10px] text-[#94A3B8]">
                                            {{ selectedShipping.label }}
                                        </span>
                                    </span>

                                    <div class="text-right">
                                        <span
                                            v-if="shippingDiscount > 0"
                                            class="mr-1.5 text-[10px] text-[#94A3B8] line-through"
                                        >
                                            {{ money(rawShippingFee) }}
                                        </span>
                                        <span
                                            class="font-bold"
                                            :class="shippingFee === 0 ? 'text-[#22A06B]' : 'text-[#1F2937]'"
                                        >
                                            {{ shippingFee === 0 ? 'Free' : money(shippingFee) }}
                                        </span>
                                        <span
                                            v-if="appliedVouchers.shipping"
                                            class="block text-[10px] font-medium text-[#22A06B]"
                                        >
                                            Free Shipping ({{ appliedVouchers.shipping.code }})
                                        </span>
                                    </div>
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

                            <p v-if="hub_routes_required && form.shipping_method !== 'pickup' && selectedServiceability?.couriers?.length" class="mt-4 text-sm text-[#087F8C]">
                                Courier: {{ selectedServiceability.couriers.join(', ') }}
                            </p>
                            <button
                                type="submit"
                                :disabled="
                                    form.processing ||
                                    (form.shipping_method !== 'pickup' && selectedServiceability?.state === 'unsupported') ||
                                    !form.shipping_address.trim() ||
                                    !selectedItems.length ||
                                    isAddressUnserviceable
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
                                v-if="isAddressUnserviceable"
                                class="mt-3 flex items-center justify-center gap-1 rounded-lg bg-[#FFF1F1] p-2 text-center text-[11px] font-semibold leading-4 text-[#C24141]"
                            >
                                <span><Icon name="warning" class="h-4 w-4" /></span>
                                {{ selectedServiceability?.message || 'The selected delivery address is outside courier serviceable coverage.' }}
                            </p>

                            <p
                                v-else-if="!form.shipping_address"
                                class="mt-3 flex items-center justify-center gap-1 text-center text-[10px] leading-4 text-[#9A6B08]"
                            >
                                <span><Icon name="warning" class="h-4 w-4" /></span>
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
                                <span class="text-sm"><Icon name="tag" class="h-4 w-4" /></span>
                                <p class="text-[9px] font-bold uppercase tracking-[0.15em] text-[#087F8C]">
                                    Savings
                                </p>
                            </div>

                            <h3 class="mt-1 text-sm font-extrabold text-[#1F2937]">
                                Promo Code
                            </h3>

                            <p class="mt-1 text-xs leading-5 text-[#64748B]">
                                Apply up to 3 vouchers (1 Store + 1 Platform + 1 Free Shipping).
                            </p>

                            <!-- Applied Vouchers List -->
                            <div v-if="appliedVoucherList.length > 0" class="mt-3 space-y-2">
                                <div
                                    v-for="v in appliedVoucherList"
                                    :key="v.code"
                                    class="rounded-xl border border-[#D6F0E2] bg-[#EAF8F1] p-3"
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <span class="rounded bg-[#22A06B] px-1.5 py-0.5 text-[9px] font-bold uppercase text-white">
                                                    {{ v.category || (v.type === 'free_shipping' ? 'shipping' : (v.is_platform ? 'platform' : 'store')) }}
                                                </span>
                                                <span class="truncate text-xs font-extrabold text-[#1F2937]">
                                                    {{ v.code }}
                                                </span>
                                            </div>
                                            <p class="mt-0.5 text-[10px] font-medium text-[#22A06B]">
                                                {{
                                                    v.type === 'free_shipping'
                                                        ? 'Free Shipping'
                                                        : (v.type === 'percentage' || v.type === 'percent' || v.type === '%' ? `${Number(v.value)}% off` : `${money(v.value)} off`)
                                                }}
                                                <span v-if="v.discount"> (Savings: {{ money(v.discount) }})</span>
                                            </p>
                                        </div>

                                        <button
                                            type="button"
                                            @click="removeVoucher(v.category || (v.type === 'free_shipping' ? 'shipping' : (v.is_platform ? 'platform' : 'store')))"
                                            class="shrink-0 rounded-lg px-2.5 py-1 text-[10px] font-bold text-[#188154] hover:bg-white transition"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Input to add voucher -->
                            <div v-if="appliedVoucherList.length < 3" class="mt-3">
                                <div class="flex min-w-0 gap-2">
                                    <input
                                        v-model="voucherInput"
                                        type="text"
                                        autocomplete="off"
                                        placeholder="Enter voucher code"
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
                            </div>

                            <p
                                v-if="voucherError"
                                class="mt-2 rounded-lg bg-[#FFF1F1] px-3 py-2 text-[10px] font-medium leading-4 text-[#C24141]"
                            >
                                {{ voucherError }}
                            </p>
                        </section>

                        <!-- COD -->
                        <section
                            v-if="form.payment_method === 'cod'"
                            class="min-w-0 rounded-2xl border border-[#F2D98F] bg-[#FFF7E5] p-4"
                        >
                            <div class="flex min-w-0 gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-base shadow-sm"><Icon name="card" class="h-5 w-5" /></div>

                                <div class="min-w-0">
                                    <h3 class="text-xs font-extrabold text-[#7C5A0B]">
                                        Cash on Delivery
                                    </h3>

                                    <p class="mt-1 text-[10px] leading-4 text-[#9A6B08]">
                                        Prepare {{ money(total) }} in cash when your order arrives.
                                    </p>
                                </div>
                            </div>
                        </section>

                        <!-- TRUST -->
                        <section class="min-w-0 rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-[0_8px_30px_rgba(15,23,42,0.04)] sm:p-5">
                            <div class="grid grid-cols-3 gap-2 sm:gap-3">
                                <div class="min-w-0 text-center">
                                    <div class="mx-auto flex h-9 w-9 items-center justify-center rounded-xl bg-[#E8F7F6] text-sm"><Icon name="lock" class="h-4 w-4" /></div>

                                    <p class="mt-2 truncate text-[9px] font-bold text-[#64748B]">
                                        Secure
                                    </p>
                                </div>

                                <div class="min-w-0 text-center">
                                    <div class="mx-auto flex h-9 w-9 items-center justify-center rounded-xl bg-[#E8F7F6] text-sm"><Icon name="truck" class="h-4 w-4" /></div>

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
