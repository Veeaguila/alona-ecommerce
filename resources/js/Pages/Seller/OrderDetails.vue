<script setup>
import SellerPickupLocation from '@/Components/Logistics/SellerPickupLocation.vue'
import LogisticsReviewNotice from '@/Components/Logistics/LogisticsReviewNotice.vue'
import { computed, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'
import SellerReturnReceipt from '@/Components/Logistics/SellerReturnReceipt.vue'

const props = defineProps({
    orderItem: {
        type: Object,
        required: true,
    },
    sellerItems: { type: Array, default: () => [] },
    orderSummary: { type: Object, default: null },
    deliveryDetails: { type: Object, default: null },
    deliveryLinkUnverified: { type: Boolean, default: false },
})

const failedProofs = ref({})

const item = computed(() => props.orderItem)

const showCancelForm = ref(false)

/*
|--------------------------------------------------------------------------
| Forms
|--------------------------------------------------------------------------
|
| Route methods must match routes/web.php:
|
| PATCH seller.orders.process
| PATCH seller.orders.pack
| PATCH seller.orders.ready-for-pickup
| PATCH seller.orders.cancel
| POST  seller.orders.note
|
*/

const processForm = useForm({})
const packForm = useForm({})
const readyForPickupForm = useForm({ collection_address: '' })

const cancelForm = useForm({
    reason: '',
})

const noteForm = useForm({
    seller_note: props.orderItem?.seller_note ?? '',
})

/*
|--------------------------------------------------------------------------
| Status Helpers
|--------------------------------------------------------------------------
*/

const statusLabels = {
    pending: 'Pending',
    processing: 'Processing',
    packed: 'Packed',
    ready_for_pickup: 'Ready for Pickup',
    shipped: 'Shipped',
    out_for_delivery: 'Out for Delivery',
    delivered: 'Delivered',
    completed: 'Completed',
    cancelled: 'Cancelled',
}

const statusLabel = (status) => {
    if (!status) {
        return 'Pending'
    }

    return (
        statusLabels[status] ??
        status
            .replace(/_/g, ' ')
            .replace(/\b\w/g, (letter) => letter.toUpperCase())
    )
}

const statusClass = (status) => {
    switch (status) {
        case 'pending':
            return 'bg-yellow-100 text-yellow-700'

        case 'processing':
            return 'bg-blue-100 text-blue-700'

        case 'packed':
            return 'bg-purple-100 text-purple-700'

        case 'ready_for_pickup':
            return 'bg-orange-100 text-orange-700'

        case 'shipped':
            return 'bg-indigo-100 text-indigo-700'

        case 'out_for_delivery':
            return 'bg-cyan-100 text-cyan-700'

        case 'delivered':
            return 'bg-green-100 text-green-700'

        case 'completed':
            return 'bg-green-100 text-green-700'

        case 'cancelled':
            return 'bg-red-100 text-red-700'

        default:
            return 'bg-gray-100 text-gray-700'
    }
}

/*
|--------------------------------------------------------------------------
| Current Status
|--------------------------------------------------------------------------
*/

const currentStatus = computed(() => {
    return item.value?.status ?? 'pending'
})

/*
|--------------------------------------------------------------------------
| Order Information
|--------------------------------------------------------------------------
*/

const orderNumber = computed(() => {
    return item.value?.order?.order_number ?? 'Order'
})

const orderDate = computed(() => {
    return (
        item.value?.order?.created_at ??
        item.value?.created_at ??
        null
    )
})

const productName = computed(() => {
    return (
        item.value?.product_name ??
        item.value?.product?.name ??
        'Product'
    )
})

const quantity = computed(() => {
    return Number(item.value?.quantity ?? 0)
})

const price = computed(() => {
    return Number(item.value?.price ?? 0)
})

const lineTotal = computed(() => {
    return price.value * quantity.value
})

const customerName = computed(() => {
    return item.value?.order?.user?.name ?? 'Customer'
})

const customerEmail = computed(() => {
    return item.value?.order?.user?.email ?? ''
})

const paymentMethod = computed(() => {
    const payment = item.value?.order?.payment_method

    if (!payment) {
        return 'Not specified'
    }

    return payment
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase())
})

/*
|--------------------------------------------------------------------------
| Variant Information
|--------------------------------------------------------------------------
*/

const variantLabel = computed(() => {
    if (item.value?.variant_label) {
        return item.value.variant_label
    }

    const variant = item.value?.variant

    if (!variant) {
        return null
    }

    const values = []

    if (variant.color) {
        values.push(`Color: ${variant.color}`)
    }

    if (variant.size) {
        values.push(`Size: ${variant.size}`)
    }

    return values.length
        ? values.join(' • ')
        : null
})

/*
|--------------------------------------------------------------------------
| Shipping Address
|--------------------------------------------------------------------------
*/

const shippingAddress = computed(() => {
    const address = item.value?.order?.shipping_address

    if (!address) {
        return 'No shipping address available.'
    }

    if (
        typeof address === 'object' &&
        address !== null
    ) {
        return Object.values(address)
            .filter(Boolean)
            .join(', ')
    }

    try {
        const parsed = JSON.parse(address)

        if (
            typeof parsed === 'object' &&
            parsed !== null
        ) {
            return Object.values(parsed)
                .filter(Boolean)
                .join(', ')
        }
    } catch (error) {
        // Address is already plain text.
    }

    return address
})

/*
|--------------------------------------------------------------------------
| Product Image
|--------------------------------------------------------------------------
*/

const imageUrl = (path) => {
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
| Status Timeline
|--------------------------------------------------------------------------
|
| Seller:
|
| Pending
|   ↓
| Processing
|   ↓
| Packed
|   ↓
| Ready for Pickup
|
| Logistics later:
|
| Ready for Pickup
|   ↓
| Shipped
|   ↓
| Out for Delivery
|   ↓
| Delivered
|   ↓
| Completed
|
*/

const timelineStatuses = [
    'pending',
    'processing',
    'packed',
    'ready_for_pickup',
    'shipped',
    'out_for_delivery',
    'delivered',
    'completed',
]

const statusHistories = computed(() => {
    return item.value?.status_histories ?? []
})

const historyForStatus = (status) => {
    return statusHistories.value.find(
        (history) => history.status === status
    )
}

const isStatusCompleted = (status) => {
    if (currentStatus.value === 'cancelled') {
        return false
    }

    const currentIndex =
        timelineStatuses.indexOf(currentStatus.value)

    const statusIndex =
        timelineStatuses.indexOf(status)

    if (
        currentIndex === -1 ||
        statusIndex === -1
    ) {
        return false
    }

    return statusIndex <= currentIndex
}

const isCurrentStatus = (status) => {
    return currentStatus.value === status
}

const statusDate = (status) => {
    const history = historyForStatus(status)

    if (!history?.created_at) {
        return null
    }

    return formatDate(history.created_at)
}

/*
|--------------------------------------------------------------------------
| Seller Workflow Permissions
|--------------------------------------------------------------------------
*/

const canProcess = computed(() => {
    return currentStatus.value === 'pending'
})

const canPack = computed(() => {
    return currentStatus.value === 'processing'
})

const canReadyForPickup = computed(() => {
    return currentStatus.value === 'packed'
})

const canCancel = computed(() => {
    if (item.value?.can_cancel !== undefined) {
        return Boolean(item.value.can_cancel)
    }
    return [
        'pending',
        'processing',
        'packed',
    ].includes(currentStatus.value)
})

const isLogisticsStatus = computed(() => {
    return [
        'shipped',
        'out_for_delivery',
        'delivered',
        'completed',
    ].includes(currentStatus.value)
})

/*
|--------------------------------------------------------------------------
| Processing
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Route is PATCH, so use patch().
|
*/

const markProcessing = () => {
    processForm.patch(
        route(
            'seller.orders.process',
            item.value.id
        ),
        {
            preserveScroll: true,
        }
    )
}

/*
|--------------------------------------------------------------------------
| Packing
|--------------------------------------------------------------------------
|
| Processing → Packed
|
*/

const markPacked = () => {
    packForm.patch(
        route(
            'seller.orders.pack',
            item.value.id
        ),
        {
            preserveScroll: true,
        }
    )
}

/*
|--------------------------------------------------------------------------
| Ready for Pickup
|--------------------------------------------------------------------------
|
| Packed → Ready for Pickup
|
*/

const markReadyForPickup = () => {
    readyForPickupForm.patch(
        route(
            'seller.orders.ready-for-pickup',
            item.value.id
        ),
        {
            preserveScroll: true,
        }
    )
}
const completePickupForm = useForm({})
const completePickup = () => {
    if (confirm('Confirm that this order item has been picked up by the customer?')) {
        completePickupForm.patch(
            route('seller.orders.complete-pickup', item.value.id),
            {
                preserveScroll: true,
            }
        )
    }
}

/*
|--------------------------------------------------------------------------
| E-Wallet / Card Payment Confirmation
|--------------------------------------------------------------------------
*/

const isEwalletOrCard = computed(() => {
    const method = String(props.orderSummary?.payment_method || item.value?.order?.payment_method || '').toLowerCase()
    return ['gcash', 'maya', 'card'].includes(method)
})

const isPaymentConfirmed = computed(() => {
    const status = String(props.orderSummary?.payment_status || item.value?.order?.payment_status || '').toLowerCase()
    return status === 'paid'
})

const isPaymentAwaitingConfirmation = computed(() => {
    return isEwalletOrCard.value && !isPaymentConfirmed.value
})

const confirmPaymentForm = useForm({})
const confirmPayment = () => {
    if (confirm(`Confirm that you have received payment for this order via ${paymentMethod.value}? This will mark the order as Paid.`)) {
        confirmPaymentForm.patch(
            route('seller.orders.confirm-payment', item.value.id),
            {
                preserveScroll: true,
            }
        )
    }
}

/*
|--------------------------------------------------------------------------
| Cancellation
|--------------------------------------------------------------------------
*/

const openCancelForm = () => {
    cancelForm.clearErrors()
    cancelForm.reason = ''
    showCancelForm.value = true
}

const closeCancelForm = () => {
    if (!cancelForm.processing) {
        showCancelForm.value = false
    }
}

const cancelOrder = () => {
    cancelForm.patch(
        route(
            'seller.orders.cancel',
            item.value.id
        ),
        {
            preserveScroll: true,

            onSuccess: () => {
                showCancelForm.value = false
            },
        }
    )
}

/*
|--------------------------------------------------------------------------
| Seller Note
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Route is POST, so use post().
|
*/

const saveNote = () => {
    noteForm.post(
        route(
            'seller.orders.note',
            item.value.id
        ),
        {
            preserveScroll: true,
        }
    )
}

/*
|--------------------------------------------------------------------------
| Print Waybill & Shipping Documents
|--------------------------------------------------------------------------
*/

const printOrder = () => {
    router.visit(route('seller.orders.waybill', item.value.id))
}

/*
|--------------------------------------------------------------------------
| Courier Pickup Scheduling (SELLER-19)
|--------------------------------------------------------------------------
*/

const showPickupModal = ref(false)

const getTomorrowDate = () => {
    const d = new Date()
    d.setDate(d.getDate() + 1)
    return d.toISOString().split('T')[0]
}

const getTodayDate = () => {
    return new Date().toISOString().split('T')[0]
}

const setQuickDate = (offsetDays) => {
    const d = new Date()
    d.setDate(d.getDate() + offsetDays)
    pickupForm.scheduled_date = d.toISOString().split('T')[0]
}

const pickupForm = useForm({
    scheduled_date: item.value?.scheduled_pickup_date || item.value?.parcel?.pickup_requests?.[0]?.scheduled_date || getTomorrowDate(),
    time_slot: item.value?.pickup_time_slot || item.value?.parcel?.pickup_requests?.[0]?.time_slot || 'morning',
    carrier_partner: item.value?.preferred_carrier || item.value?.courier_name || 'Alona Express Fleet',
    collection_address: item.value?.parcel?.collection_address || item.value?.seller?.address || item.value?.seller?.pickup_address || '',
    notes: item.value?.pickup_notes || '',
})

const openPickupModal = () => {
    pickupForm.clearErrors()
    if (!pickupForm.scheduled_date) {
        pickupForm.scheduled_date = getTomorrowDate()
    }
    if (!pickupForm.collection_address) {
        pickupForm.collection_address = item.value?.parcel?.collection_address || item.value?.seller?.address || item.value?.seller?.pickup_address || ''
    }
    showPickupModal.value = true
}

const submitPickupSchedule = () => {
    pickupForm.post(
        route('seller.orders.schedule-pickup', item.value.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                showPickupModal.value = false
            },
        }
    )
}

const formatTimeSlot = (slot) => {
    switch (slot) {
        case 'morning':
            return 'Morning (9:00 AM – 1:00 PM)'
        case 'afternoon':
            return 'Afternoon (1:00 PM – 5:00 PM)'
        case 'anytime':
        default:
            return 'Anytime (9:00 AM – 6:00 PM)'
    }
}

/*
|--------------------------------------------------------------------------
| Date Formatting
|--------------------------------------------------------------------------
*/

const formatDate = (date) => {
    if (!date) {
        return ''
    }

    const parsedDate = new Date(date)

    if (Number.isNaN(parsedDate.getTime())) {
        return ''
    }

    return parsedDate.toLocaleString(
        'en-US',
        {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        }
    )
}

/*
|--------------------------------------------------------------------------
| Money Formatting
|--------------------------------------------------------------------------
*/

const formatMoney = (value) => {
    return Number(value ?? 0).toLocaleString(
        'en-PH',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }
    )
}
</script>

<template>
    <Head :title="`Order #${orderNumber}`" />

    <SellerLayout>
        <!--
        ======================================================================
        NORMAL SELLER ORDER DETAILS
        ======================================================================
        -->

        <div
            id="seller-order-page"
            class="w-full space-y-6"
        >
            <div v-if="item.parcel" class="mb-4 rounded-lg border border-teal-200 bg-teal-50 p-4 text-sm">
                <p class="font-semibold">Courier reference: {{ item.parcel.order_id }}</p>
                <p>Pickup status: {{ item.parcel.pickup_requests?.[0]?.status || 'pending' }}</p>
                <p>Courier status: {{ item.parcel.status?.replaceAll('_', ' ') }}</p>
                <p v-if="item.parcel.failure_reason">Delivery failure: {{ item.parcel.failure_reason }}</p>
                <p>Collection address: {{ item.parcel.collection_address || 'Contact the sorting center to confirm the collection address.' }}</p>
            </div>
            <p v-if="deliveryLinkUnverified" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                Delivery proof is unavailable because this parcel has no verified Seller/order-item link. Contact Logistics with the order reference.
            </p>
            <SellerPickupLocation v-if="deliveryDetails && ['PLACED', 'PREPARING'].includes(deliveryDetails.status)" :item-id="orderItem.id" />
            <LogisticsReviewNotice :review="deliveryDetails?.logistics_review" />
            <SellerReturnReceipt v-if="deliveryDetails?.return_to_seller" :entry="deliveryDetails.return_to_seller" />
            <section v-if="deliveryDetails?.status === 'DELIVERED'" class="mb-5 rounded-lg border border-teal-200 bg-white p-5" aria-label="Delivery proof">
                <h2 class="text-lg font-semibold text-[#087F8C]">Delivery confirmed</h2>
                <p class="mt-1 text-sm text-gray-600">{{ deliveryDetails.reference }} · {{ formatDate(deliveryDetails.delivered_at) }}</p>
                <p v-if="!deliveryDetails.proofs.length" class="mt-3 text-sm text-gray-600">No available PHOTO proof is recorded for this delivery. Its historical delivery status is unchanged.</p>
                <div v-for="proof in deliveryDetails.proofs" :key="proof.id" class="mt-4">
                    <template v-if="proof.available && !failedProofs[proof.id]">
                        <img :src="proof.file_url" alt="Photo proof of this delivery" class="max-h-80 w-full rounded-lg object-contain bg-gray-50" loading="lazy" @error="failedProofs[proof.id] = true" />
                        <a :href="proof.file_url" target="_blank" rel="noopener" class="mt-2 inline-block text-sm font-semibold text-[#087F8C] underline">Open delivery photo</a>
                    </template>
                    <p v-else class="text-sm text-amber-800">This delivery photo is unavailable. Refresh or contact Logistics.</p>
                    <p class="mt-2 text-xs text-gray-500">Photo recorded: {{ formatDate(proof.recorded_at) }}</p>
                    <p v-if="proof.notes" class="mt-1 whitespace-pre-wrap text-sm text-gray-700">{{ proof.notes }}</p>
                </div>
            </section>
            <!-- Header -->
            <div
                class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-[#087F8C]">
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                        <Link :href="route('seller.orders')" class="hover:underline">Orders</Link>
                        <span class="text-slate-300">·</span>
                        <span class="font-medium normal-case text-slate-500">#{{ orderNumber }}</span>
                    </div>

                    <h1
                        class="mt-1 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl"
                    >
                        Order #{{ orderNumber }}
                    </h1>

                    <p
                        class="mt-0.5 text-xs text-slate-500"
                    >
                        Manage order items, review buyer shipping details, and track fulfillment status.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="route('seller.orders.waybill', item.id)"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-3.5 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:border-[#087F8C] hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24-1.282.68-2.43 1.96-2.43h6.64c1.28 0 2.2 1.148 1.96 2.43a2.986 2.986 0 0 1-2.93 2.421H9.65a2.986 2.986 0 0 1-2.93-2.421Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-8.25A3.375 3.375 0 0 0 4.5 11.625v2.625m15 0a2.25 2.25 0 0 1-2.25 2.25h-.75m3-2.25V9a3 3 0 0 0-3-3h-9a3 3 0 0 0-3 3v5.25m3 2.25h-.75A2.25 2.25 0 0 1 4.5 14.25m3 2.25h9a2.25 2.25 0 0 0 2.25-2.25" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18h12v3a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-3Z" />
                        </svg>
                        Print Waybill
                    </Link>

                    <Link
                        :href="route('seller.orders')"
                        class="inline-flex items-center rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-800"
                    >
                        ← Back to Orders
                    </Link>
                </div>
            </div>

            <!-- Payment Confirmation Alert Banner -->
            <div
                v-if="isPaymentAwaitingConfirmation"
                class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900 shadow-sm"
            >
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <svg class="h-6 w-6 text-amber-600 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <p class="font-bold text-base">Payment Confirmation Required</p>
                            <p class="text-sm text-amber-800">
                                The buyer selected <strong>{{ paymentMethod }}</strong>. Verify that the payment of <strong>₱{{ formatMoney(orderSummary?.total || lineTotal) }}</strong> has been received in your account before packing the order.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="confirmPayment"
                        :disabled="confirmPaymentForm.processing"
                        class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-50 transition"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {{ confirmPaymentForm.processing ? 'Confirming...' : 'Confirm Payment Received' }}
                    </button>
                </div>
            </div>

            <!-- Status -->
            <div
                class="mb-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
            >
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div>
                        <p class="text-sm text-gray-500">
                            Current Status
                        </p>

                        <div
                            class="mt-2 flex flex-wrap items-center gap-3"
                        >
                            <span
                                class="rounded-full px-3 py-1 text-sm font-semibold"
                                :class="statusClass(currentStatus)"
                            >
                                {{ statusLabel(currentStatus) }}
                            </span>

                            <span
                                v-if="item.created_at"
                                class="text-sm text-gray-500"
                            >
                                {{ formatDate(item.created_at) }}
                            </span>
                        </div>
                    </div>

                    <!-- Workflow Actions -->
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Confirm Payment (E-Wallet / Card) -->
                        <button
                            v-if="isPaymentAwaitingConfirmation"
                            type="button"
                            @click="confirmPayment"
                            :disabled="confirmPaymentForm.processing"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-50 transition"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            {{ confirmPaymentForm.processing ? 'Confirming...' : 'Confirm Payment Received' }}
                        </button>

                        <!-- Pending → Processing -->
                        <button
                            v-if="canProcess"
                            type="button"
                            @click="markProcessing"
                            :disabled="processForm.processing"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{
                                processForm.processing
                                    ? 'Processing...'
                                    : 'Start Processing'
                            }}
                        </button>

                        <!-- Processing → Packed -->
                        <button
                            v-if="canPack"
                            type="button"
                            @click="markPacked"
                            :disabled="packForm.processing || isPaymentAwaitingConfirmation"
                            :title="isPaymentAwaitingConfirmation ? 'Confirm payment received before packing' : ''"
                            class="rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-purple-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{
                                packForm.processing
                                    ? 'Packing...'
                                    : 'Mark as Packed'
                            }}
                        </button>

                        <!-- Packed → Ready for Pickup -->
                        <label v-if="canReadyForPickup" class="text-sm text-gray-700">
                            Collection address (optional; uses your seller address)
                            <input v-model="readyForPickupForm.collection_address" type="text" maxlength="1000" class="mt-1 block w-full rounded-lg border-gray-300" />
                            <span v-if="readyForPickupForm.errors.collection_address" class="text-red-600">{{ readyForPickupForm.errors.collection_address }}</span>
                        </label>
                        <button
                            v-if="canReadyForPickup"
                            type="button"
                            @click="markReadyForPickup"
                            :disabled="
                                readyForPickupForm.processing
                            "
                            class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-orange-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{
                                readyForPickupForm.processing
                                    ? 'Updating...'
                                    : (orderSummary?.is_pickup ? 'Mark Ready for Customer Pickup' : 'Mark as Ready for Pickup')
                            }}
                        </button>

                        <!-- Complete In-Store Pickup Button -->
                        <button
                            v-if="currentStatus === 'ready_for_pickup' && orderSummary?.is_pickup"
                            type="button"
                            @click="completePickup"
                            :disabled="completePickupForm.processing"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                        >
                            {{ completePickupForm.processing ? 'Completing...' : '✓ Mark as Picked Up by Customer' }}
                        </button>

                        <!-- Courier Pickup Scheduling Button (SELLER-19) -->
                        <button
                            v-if="['processing', 'packed', 'ready_for_pickup'].includes(currentStatus) && !orderSummary?.is_pickup"
                            type="button"
                            @click="openPickupModal"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-[#087F8C] bg-[#E8F7F6] px-4 py-2 text-sm font-bold text-[#087F8C] transition hover:bg-[#087F8C] hover:text-white"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                            </svg>
                            <span>{{ item.scheduled_pickup_date ? 'Reschedule Courier Pickup' : 'Schedule Courier Pickup' }}</span>
                        </button>

                        <!-- Seller Cancellation -->
                        <button
                            v-if="canCancel"
                            type="button"
                            @click="openCancelForm"
                            class="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-100"
                        >
                            Cancel Order
                        </button>
                    </div>
                </div>

                <!-- Courier Pickup Scheduled Banner (SELLER-19) -->
                <div
                    v-if="item.scheduled_pickup_date"
                    class="mt-4 rounded-xl border border-teal-200 bg-teal-50/80 p-4 text-xs text-teal-900 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-2xs"
                >
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-600 text-white font-bold text-base shadow-xs">
                            🚚
                        </div>
                        <div>
                            <div class="font-bold text-sm text-teal-950 flex flex-wrap items-center gap-2">
                                <span>Courier Pickup Scheduled:</span>
                                <span class="rounded-md bg-teal-100 px-2 py-0.5 font-mono text-teal-900 border border-teal-300">
                                    {{ item.scheduled_pickup_date }}
                                </span>
                                <span class="font-semibold text-teal-700">({{ formatTimeSlot(item.pickup_time_slot) }})</span>
                            </div>
                            <div class="text-[11px] text-teal-800 mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span>Carrier: <strong class="text-teal-950">{{ item.preferred_carrier || item.courier_name || 'Alona Express Fleet' }}</strong></span>
                                <span v-if="item.pickup_notes" class="italic text-teal-700">• Note: "{{ item.pickup_notes }}"</span>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="openPickupModal"
                        class="inline-flex shrink-0 items-center justify-center rounded-lg border border-teal-300 bg-white px-3 py-1.5 text-xs font-bold text-teal-800 shadow-xs hover:bg-teal-100 transition"
                    >
                        Change Schedule
                    </button>
                </div>

                <!-- Logistics Notice -->
                <div
                    v-if="isLogisticsStatus"
                    class="mt-5 rounded-lg border border-indigo-200 bg-indigo-50 p-4"
                >
                    <p
                        class="font-semibold text-indigo-800"
                    >
                        Logistics is handling this shipment.
                    </p>

                    <p
                        class="mt-1 text-sm text-indigo-700"
                    >
                        Seller actions are no longer available for
                        this fulfillment stage.
                    </p>
                </div>
            </div>

            <!-- Cancellation Form -->
            <div
                v-if="showCancelForm"
                class="mb-6 rounded-xl border border-red-200 bg-red-50 p-5 shadow-sm"
            >
                <div class="mb-4">
                    <h2
                        class="text-lg font-bold text-gray-900"
                    >
                        Cancel Order
                    </h2>

                    <p
                        class="mt-1 text-sm text-gray-600"
                    >
                        Stock will be restored after cancellation.
                    </p>
                </div>

                <form
                    @submit.prevent="cancelOrder"
                    class="space-y-4"
                >
                    <div>
                        <label
                            for="cancel_reason"
                            class="mb-2 block text-sm font-semibold text-gray-700"
                        >
                            Cancellation Reason
                        </label>

                        <textarea
                            id="cancel_reason"
                            v-model="cancelForm.reason"
                            rows="3"
                            maxlength="500"
                            placeholder="Enter the reason for cancellation..."
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-sm outline-none focus:border-red-500 focus:ring-2 focus:ring-red-200"
                        ></textarea>

                        <p
                            v-if="cancelForm.errors.reason"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ cancelForm.errors.reason }}
                        </p>
                    </div>

                    <div
                        class="flex justify-end gap-3"
                    >
                        <button
                            type="button"
                            @click="closeCancelForm"
                            :disabled="cancelForm.processing"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                        >
                            Keep Order
                        </button>

                        <button
                            type="submit"
                            :disabled="cancelForm.processing"
                            class="rounded-lg bg-red-600 px-5 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{
                                cancelForm.processing
                                    ? 'Cancelling...'
                                    : 'Confirm Cancellation'
                            }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Progress Timeline -->
            <div
                class="mb-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
            >
                <h2
                    class="mb-6 text-lg font-bold text-gray-900"
                >
                    Order Progress
                </h2>

                <div class="overflow-x-auto">
                    <div
                        class="flex min-w-[1100px] items-start"
                    >
                        <template
                            v-for="(
                                status, index
                            ) in timelineStatuses"
                            :key="status"
                        >
                            <div
                                class="flex flex-1 flex-col items-center"
                            >
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-bold"
                                    :class="
                                        isCurrentStatus(status)
                                            ? 'border-blue-600 bg-blue-600 text-white'
                                            : isStatusCompleted(status)
                                                ? 'border-green-500 bg-green-500 text-white'
                                                : 'border-gray-300 bg-white text-gray-400'
                                    "
                                >
                                    <span
                                        v-if="
                                            isStatusCompleted(
                                                status
                                            ) &&
                                            !isCurrentStatus(status)
                                        "
                                    >
                                        ✓
                                    </span>

                                    <span v-else>
                                        {{ index + 1 }}
                                    </span>
                                </div>

                                <p
                                    class="mt-2 text-center text-sm font-semibold"
                                    :class="
                                        isCurrentStatus(status)
                                            ? 'text-blue-700'
                                            : isStatusCompleted(status)
                                                ? 'text-gray-900'
                                                : 'text-gray-400'
                                    "
                                >
                                    {{ statusLabel(status) }}
                                </p>

                                <p
                                    v-if="statusDate(status)"
                                    class="mt-1 text-center text-xs text-gray-500"
                                >
                                    {{ statusDate(status) }}
                                </p>
                            </div>

                            <div
                                v-if="
                                    index <
                                    timelineStatuses.length - 1
                                "
                                class="mt-5 h-0.5 flex-1"
                                :class="
                                    isStatusCompleted(
                                        timelineStatuses[index + 1]
                                    )
                                        ? 'bg-green-500'
                                        : 'bg-gray-200'
                                "
                            ></div>
                        </template>
                    </div>
                </div>

                <!-- Seller Workflow Explanation -->
                <div
                    class="mt-6 rounded-lg border border-gray-200 bg-gray-50 p-4"
                >
                    <p
                        class="text-sm font-semibold text-gray-800"
                    >
                        Seller workflow
                    </p>

                    <p
                        class="mt-1 text-sm leading-6 text-gray-600"
                    >
                        Pending → Processing → Packed → Ready for
                        Pickup. After pickup, Logistics handles
                        shipment tracking and delivery.
                    </p>
                </div>

                <!-- Cancelled -->
                <div
                    v-if="
                        currentStatus === 'cancelled'
                    "
                    class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4"
                >
                    <p
                        class="font-semibold text-red-800"
                    >
                        This order has been cancelled.
                    </p>

                    <p
                        class="mt-1 text-sm text-red-700"
                    >
                        The item can no longer be processed
                        or shipped.
                    </p>
                </div>
            </div>

            <!-- Main Grid -->
            <div
                class="grid gap-6 lg:grid-cols-3"
            >
                <!-- Left -->
                <div
                    class="space-y-6 lg:col-span-2"
                >
                    <!-- Product -->
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
                    >
                        <h2
                            class="mb-5 text-lg font-bold text-gray-900"
                        >
                            Ordered Product
                        </h2>

                        <div
                            class="flex flex-col gap-5 sm:flex-row"
                        >
                            <div
                                class="flex h-28 w-28 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-gray-100"
                            >
                                <img
                                    v-if="
                                        imageUrl(
                                            item.product?.image_path
                                        )
                                    "
                                    :src="
                                        imageUrl(
                                            item.product?.image_path
                                        )
                                    "
                                    :alt="productName"
                                    class="h-full w-full object-cover"
                                />

                                <span
                                    v-else
                                    class="text-4xl"
                                >
                                    📦
                                </span>
                            </div>

                            <div class="flex-1">
                                <div
                                    class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                                >
                                    <div>
                                        <h3
                                            class="text-lg font-bold text-gray-900"
                                        >
                                            {{ productName }}
                                        </h3>

                                        <p
                                            v-if="variantLabel"
                                            class="mt-1 text-sm text-gray-500"
                                        >
                                            {{ variantLabel }}
                                        </p>
                                    </div>

                                    <span
                                        class="w-fit rounded-full px-3 py-1 text-xs font-semibold"
                                        :class="
                                            statusClass(
                                                currentStatus
                                            )
                                        "
                                    >
                                        {{
                                            statusLabel(
                                                currentStatus
                                            )
                                        }}
                                    </span>
                                </div>

                                <div
                                    class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3"
                                >
                                    <div>
                                        <p
                                            class="text-xs text-gray-500"
                                        >
                                            Unit Price
                                        </p>

                                        <p
                                            class="mt-1 font-semibold text-gray-900"
                                        >
                                            ₱{{ formatMoney(price) }}
                                        </p>
                                    </div>

                                    <div>
                                        <p
                                            class="text-xs text-gray-500"
                                        >
                                            Quantity
                                        </p>

                                        <p
                                            class="mt-1 font-semibold text-gray-900"
                                        >
                                            {{ quantity }}
                                        </p>
                                    </div>

                                    <div>
                                        <p
                                            class="text-xs text-gray-500"
                                        >
                                            Line Total
                                        </p>

                                        <p
                                            class="mt-1 font-semibold text-gray-900"
                                        >
                                            ₱{{ formatMoney(lineTotal) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Delivery Information -->
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
                    >
                        <h2
                            class="mb-5 text-lg font-bold text-gray-900"
                        >
                            Delivery Information
                        </h2>

                        <div
                            class="grid gap-5 sm:grid-cols-2"
                        >
                            <div>
                                <p
                                    class="text-xs font-medium uppercase tracking-wide text-gray-500"
                                >
                                    Courier
                                </p>

                                <p
                                    class="mt-1 font-semibold text-gray-900"
                                >
                                    {{
                                        item.courier_name ||
                                        'Not assigned'
                                    }}
                                </p>
                            </div>

                            <div>
                                <p
                                    class="text-xs font-medium uppercase tracking-wide text-gray-500"
                                >
                                    Tracking Number
                                </p>

                                <p
                                    class="mt-1 break-all font-semibold text-gray-900"
                                >
                                    {{
                                        item.tracking_number ||
                                        'Not assigned'
                                    }}
                                </p>
                            </div>

                            <div class="sm:col-span-2 mb-2">
                                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Shipping Method</p>
                                <div class="mt-1 flex items-center gap-2">
                                    <span v-if="orderSummary?.is_express" class="inline-flex items-center gap-1 rounded-full border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-black text-amber-700">
                                        ⚡ Express Delivery (1-3 days Priority Courier)
                                    </span>
                                    <span v-else-if="orderSummary?.is_pickup" class="inline-flex items-center gap-1 rounded-full border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-black text-emerald-700">
                                        🏪 In-Store Pickup (Customer collects at seller store)
                                    </span>
                                    <span v-else class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-bold text-gray-700">
                                        Standard Delivery (3-7 days Courier)
                                    </span>
                                </div>
                            </div>

                            <div
                                class="sm:col-span-2"
                            >
                                <p
                                    class="text-xs font-medium uppercase tracking-wide text-gray-500"
                                >
                                    Shipping Address
                                </p>

                                <p
                                    class="mt-1 leading-6 text-gray-900"
                                >
                                    {{ shippingAddress }}
                                </p>
                            </div>
                        </div>

                        <!-- Ready for Pickup -->
                        <div
                            v-if="currentStatus === 'ready_for_pickup' && orderSummary?.is_pickup"
                            class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 p-4"
                        >
                            <p class="font-semibold text-emerald-800">
                                Ready for Customer In-Store Pickup
                            </p>
                            <p class="mt-1 text-sm leading-6 text-emerald-700">
                                Item is packed and waiting for the customer to collect at your store. When the customer receives the item, click <strong>"Mark as Picked Up by Customer"</strong> above.
                            </p>
                        </div>
                        <div
                            v-else-if="
                                currentStatus ===
                                'ready_for_pickup'
                            "
                            class="mt-5 rounded-lg border border-orange-200 bg-orange-50 p-4"
                        >
                            <p
                                class="font-semibold text-orange-800"
                            >
                                Ready for Courier Pickup
                            </p>

                            <p
                                class="mt-1 text-sm leading-6 text-orange-700"
                            >
                                Seller fulfillment is complete.
                                Courier assignment, shipment,
                                and tracking are handled by
                                Logistics.
                            </p>
                        </div>

                        <!-- Shipped -->
                        <div
                            v-if="
                                currentStatus ===
                                'shipped'
                            "
                            class="mt-5 rounded-lg border border-indigo-200 bg-indigo-50 p-4"
                        >
                            <p
                                class="font-semibold text-indigo-800"
                            >
                                Shipment in Transit
                            </p>

                            <p
                                class="mt-1 text-sm leading-6 text-indigo-700"
                            >
                                This package has been handed over
                                to Logistics.
                            </p>
                        </div>

                        <!-- Out for Delivery -->
                        <div
                            v-if="
                                currentStatus ===
                                'out_for_delivery'
                            "
                            class="mt-5 rounded-lg border border-cyan-200 bg-cyan-50 p-4"
                        >
                            <p
                                class="font-semibold text-cyan-800"
                            >
                                Out for Delivery
                            </p>

                            <p
                                class="mt-1 text-sm leading-6 text-cyan-700"
                            >
                                The courier is currently
                                delivering this package.
                            </p>
                        </div>

                        <!-- Delivered -->
                        <div
                            v-if="
                                currentStatus ===
                                'delivered'
                            "
                            class="mt-5 rounded-lg border border-green-200 bg-green-50 p-4"
                        >
                            <p
                                class="font-semibold text-green-800"
                            >
                                Delivered
                            </p>

                            <p
                                class="mt-1 text-sm leading-6 text-green-700"
                            >
                                The customer has received this
                                item.
                            </p>
                        </div>
                    </div>

                    <!-- Seller Note -->
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
                    >
                        <div
                            class="mb-4 flex items-center justify-between"
                        >
                            <h2
                                class="text-lg font-bold text-gray-900"
                            >
                                Seller Note
                            </h2>

                            <span
                                class="text-xs text-gray-500"
                            >
                                Internal note
                            </span>
                        </div>

                        <form
                            @submit.prevent="saveNote"
                            class="space-y-4"
                        >
                            <textarea
                                v-model="noteForm.seller_note"
                                rows="4"
                                maxlength="2000"
                                placeholder="Add an internal note about this order..."
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 text-sm outline-none transition focus:border-gray-500 focus:ring-2 focus:ring-gray-200"
                            ></textarea>

                            <p
                                v-if="
                                    noteForm.errors.seller_note
                                "
                                class="text-sm text-red-600"
                            >
                                {{
                                    noteForm.errors.seller_note
                                }}
                            </p>

                            <div
                                class="flex justify-end"
                            >
                                <button
                                    type="submit"
                                    :disabled="
                                        noteForm.processing
                                    "
                                    class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    {{
                                        noteForm.processing
                                            ? 'Saving...'
                                            : 'Save Note'
                                    }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right -->
                <div class="space-y-6">
                    <!-- Customer -->
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
                    >
                        <h2
                            class="mb-5 text-lg font-bold text-gray-900"
                        >
                            Customer
                        </h2>

                        <div
                            class="flex items-center gap-3"
                        >
                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-lg"
                            >
                                👤
                            </div>

                            <div>
                                <p
                                    class="font-semibold text-gray-900"
                                >
                                    {{ customerName }}
                                </p>

                                <p
                                    class="text-sm text-gray-500"
                                >
                                    Buyer
                                </p>

                                <p
                                    v-if="customerEmail"
                                    class="mt-1 text-xs text-gray-400"
                                >
                                    {{ customerEmail }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Payment -->
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
                    >
                        <div class="flex items-center justify-between mb-4">
                            <h2
                                class="text-lg font-bold text-gray-900"
                            >
                                Payment
                            </h2>
                            <span
                                v-if="isPaymentConfirmed"
                                class="inline-flex items-center gap-1 rounded-full border border-emerald-300 bg-emerald-50 px-2.5 py-0.5 text-xs font-bold text-emerald-700"
                            >
                                ✓ Paid
                            </span>
                            <span
                                v-else-if="isPaymentAwaitingConfirmation"
                                class="inline-flex items-center gap-1 rounded-full border border-amber-300 bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-700"
                            >
                                ⏳ Needs Confirmation
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 rounded-full border border-gray-200 bg-gray-50 px-2.5 py-0.5 text-xs font-bold text-gray-600"
                            >
                                Unpaid
                            </span>
                        </div>

                        <div class="space-y-3">
                            <div
                                class="flex items-center justify-between"
                            >
                                <span
                                    class="text-sm text-gray-500"
                                >
                                    Method
                                </span>

                                <span
                                    class="font-semibold text-gray-900"
                                >
                                    {{ paymentMethod }}
                                </span>
                            </div>

                            <div
                                class="flex items-center justify-between"
                            >
                                <span
                                    class="text-sm text-gray-500"
                                >
                                    Status
                                </span>

                                <span
                                    class="text-sm font-semibold"
                                    :class="isPaymentConfirmed ? 'text-emerald-700' : (isPaymentAwaitingConfirmation ? 'text-amber-700' : 'text-gray-700')"
                                >
                                    {{ isPaymentConfirmed ? 'Paid (Confirmed)' : (isPaymentAwaitingConfirmation ? 'Awaiting Confirmation' : 'Unpaid') }}
                                </span>
                            </div>

                            <div
                                v-if="isPaymentAwaitingConfirmation"
                                class="pt-2"
                            >
                                <button
                                    type="button"
                                    @click="confirmPayment"
                                    :disabled="confirmPaymentForm.processing"
                                    class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-50 transition"
                                >
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ confirmPaymentForm.processing ? 'Confirming...' : 'Confirm Payment Received' }}
                                </button>
                            </div>

                            <div
                                class="mt-4 flex items-center justify-between border-t border-gray-100 pt-3"
                            >
                                <span
                                    class="text-sm text-gray-500"
                                >
                                    Item Total
                                </span>

                                <span
                                    class="text-lg font-bold text-gray-900"
                                >
                                    ₱{{ formatMoney(lineTotal) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Order Summary -->
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
                    >
                        <h2
                            class="mb-5 text-lg font-bold text-gray-900"
                        >
                            Order Summary
                        </h2>

                        <div
                            class="space-y-3 text-sm"
                        >
                            <div
                                class="flex justify-between"
                            >
                                <span
                                    class="text-gray-500"
                                >
                                    Item subtotal
                                </span>

                                <span
                                    class="font-medium text-gray-900"
                                >
                                    ₱{{ formatMoney(lineTotal) }}
                                </span>
                            </div>

                            <div
                                class="flex justify-between"
                            >
                                <span
                                    class="text-gray-500"
                                >
                                    Shipping
                                </span>

                                <span
                                    class="font-medium text-gray-900"
                                >
                                    ₱0.00
                                </span>
                            </div>

                            <div
                                class="flex justify-between"
                            >
                                <span
                                    class="text-gray-500"
                                >
                                    Discount
                                </span>

                                <span
                                    class="font-medium text-gray-900"
                                >
                                    ₱0.00
                                </span>
                            </div>

                            <div
                                class="border-t border-gray-200 pt-3"
                            >
                                <div
                                    class="flex justify-between"
                                >
                                    <span
                                        class="font-semibold text-gray-900"
                                    >
                                        Seller Item Total
                                    </span>

                                    <span
                                        class="text-lg font-bold text-gray-900"
                                    >
                                        ₱{{ formatMoney(lineTotal) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Next Step -->
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm"
                    >
                        <h2
                            class="mb-4 text-lg font-bold text-gray-900"
                        >
                            Next Step
                        </h2>

                        <!-- Pending -->
                        <div
                            v-if="
                                currentStatus === 'pending'
                            "
                            class="rounded-lg bg-yellow-50 p-4"
                        >
                            <p
                                class="font-semibold text-yellow-800"
                            >
                                Start preparing this order.
                            </p>

                            <p
                                class="mt-1 text-sm leading-6 text-yellow-700"
                            >
                                Move the order to Processing
                                when you begin preparing the
                                customer's item.
                            </p>
                        </div>

                        <!-- Processing -->
                        <div
                            v-else-if="
                                currentStatus ===
                                'processing'
                            "
                            class="rounded-lg bg-blue-50 p-4"
                        >
                            <p
                                class="font-semibold text-blue-800"
                            >
                                Item is being prepared.
                            </p>

                            <p
                                class="mt-1 text-sm leading-6 text-blue-700"
                            >
                                Mark the item as Packed once it
                                has been properly packed.
                            </p>
                        </div>

                        <!-- Packed -->
                        <div
                            v-else-if="
                                currentStatus === 'packed'
                            "
                            class="rounded-lg bg-purple-50 p-4"
                        >
                            <p
                                class="font-semibold text-purple-800"
                            >
                                Item is packed.
                            </p>

                            <p
                                class="mt-1 text-sm leading-6 text-purple-700"
                            >
                                Print the waybill, then mark the
                                item as Ready for Pickup.
                                Logistics will handle the courier
                                and tracking information.
                            </p>
                        </div>

                        <!-- Ready for Pickup -->
                        <div
                            v-else-if="
                                currentStatus ===
                                'ready_for_pickup'
                            "
                            class="rounded-lg bg-orange-50 p-4"
                        >
                            <p
                                class="font-semibold text-orange-800"
                            >
                                Waiting for courier pickup.
                            </p>

                            <p
                                class="mt-1 text-sm leading-6 text-orange-700"
                            >
                                Seller fulfillment is complete.
                                No additional seller status
                                action is required.
                            </p>
                        </div>

                        <!-- Shipped -->
                        <div
                            v-else-if="
                                currentStatus === 'shipped'
                            "
                            class="rounded-lg bg-indigo-50 p-4"
                        >
                            <p
                                class="font-semibold text-indigo-800"
                            >
                                Shipment is in transit.
                            </p>

                            <p
                                class="mt-1 text-sm leading-6 text-indigo-700"
                            >
                                Logistics is responsible for
                                shipment tracking and delivery.
                            </p>
                        </div>

                        <!-- Out for Delivery -->
                        <div
                            v-else-if="
                                currentStatus ===
                                'out_for_delivery'
                            "
                            class="rounded-lg bg-cyan-50 p-4"
                        >
                            <p
                                class="font-semibold text-cyan-800"
                            >
                                Out for delivery.
                            </p>

                            <p
                                class="mt-1 text-sm leading-6 text-cyan-700"
                            >
                                The courier is currently
                                delivering the customer's item.
                            </p>
                        </div>

                        <!-- Delivered -->
                        <div
                            v-else-if="
                                currentStatus ===
                                'delivered'
                            "
                            class="rounded-lg bg-green-50 p-4"
                        >
                            <p
                                class="font-semibold text-green-800"
                            >
                                Item delivered.
                            </p>

                            <p
                                class="mt-1 text-sm leading-6 text-green-700"
                            >
                                The customer has received the
                                item.
                            </p>
                        </div>

                        <!-- Completed -->
                        <div
                            v-else-if="
                                currentStatus ===
                                'completed'
                            "
                            class="rounded-lg bg-green-50 p-4"
                        >
                            <p
                                class="font-semibold text-green-800"
                            >
                                Order completed.
                            </p>

                            <p
                                class="mt-1 text-sm leading-6 text-green-700"
                            >
                                This order item has completed
                                its fulfillment lifecycle.
                            </p>
                        </div>

                        <!-- Cancelled -->
                        <div
                            v-else-if="
                                currentStatus ===
                                'cancelled'
                            "
                            class="rounded-lg bg-red-50 p-4"
                        >
                            <p
                                class="font-semibold text-red-800"
                            >
                                Order cancelled.
                            </p>

                            <p
                                class="mt-1 text-sm leading-6 text-red-700"
                            >
                                No further seller actions are
                                available.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!--
        ======================================================================
        PRINTABLE WAYBILL
        ======================================================================
        -->

        <div
            id="seller-waybill"
            class="mx-auto hidden max-w-3xl bg-white text-gray-900"
        >
            <!-- Waybill Header -->
            <div
                class="border-b-2 border-gray-900 pb-5"
            >
                <div
                    class="flex items-start justify-between gap-6"
                >
                    <div>
                        <h1
                            class="text-3xl font-black tracking-tight"
                        >
                            ZELLORA
                        </h1>

                        <p
                            class="mt-1 text-sm font-medium text-gray-500"
                        >
                            Seller Shipping Waybill
                        </p>
                    </div>

                    <div class="text-right">
                        <p
                            class="text-xs font-semibold uppercase tracking-widest text-gray-500"
                        >
                            Order Number
                        </p>

                        <p
                            class="mt-1 text-2xl font-black"
                        >
                            #{{ orderNumber }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Shipping Status -->
            <div
                class="mt-5 grid grid-cols-3 gap-4"
            >
                <div
                    class="rounded-lg border border-gray-300 p-4"
                >
                    <p
                        class="text-xs font-semibold uppercase text-gray-500"
                    >
                        Status
                    </p>

                    <p class="mt-1 font-bold">
                        {{ statusLabel(currentStatus) }}
                    </p>
                </div>

                <div
                    class="rounded-lg border border-gray-300 p-4"
                >
                    <p
                        class="text-xs font-semibold uppercase text-gray-500"
                    >
                        Order Date
                    </p>

                    <p class="mt-1 font-bold">
                        {{ formatDate(orderDate) }}
                    </p>
                </div>

                <div
                    class="rounded-lg border border-gray-300 p-4"
                >
                    <p
                        class="text-xs font-semibold uppercase text-gray-500"
                    >
                        Payment
                    </p>

                    <p class="mt-1 font-bold">
                        {{ paymentMethod }}
                    </p>
                </div>
            </div>

            <!-- Customer / Shipping -->
            <div
                class="mt-5 grid grid-cols-2 gap-5"
            >
                <div
                    class="rounded-lg border border-gray-300 p-5"
                >
                    <p
                        class="text-xs font-bold uppercase tracking-wide text-gray-500"
                    >
                        Ship To
                    </p>

                    <p
                        class="mt-2 text-lg font-black"
                    >
                        {{ customerName }}
                    </p>

                    <p
                        v-if="customerEmail"
                        class="mt-1 text-sm text-gray-600"
                    >
                        {{ customerEmail }}
                    </p>

                    <div
                        class="mt-4 border-t border-gray-200 pt-4"
                    >
                        <p
                            class="text-xs font-bold uppercase tracking-wide text-gray-500"
                        >
                            Delivery Address
                        </p>

                        <p
                            class="mt-2 text-sm leading-6"
                        >
                            {{ shippingAddress }}
                        </p>
                    </div>
                </div>

                <div
                    class="rounded-lg border border-gray-300 p-5"
                >
                    <p
                        class="text-xs font-bold uppercase tracking-wide text-gray-500"
                    >
                        Shipment
                    </p>

                    <div
                        class="mt-4 space-y-4"
                    >
                        <div>
                            <p
                                class="text-xs text-gray-500"
                            >
                                Courier
                            </p>

                            <p class="font-bold">
                                {{
                                    item.courier_name ||
                                    'Not assigned'
                                }}
                            </p>
                        </div>

                        <div>
                            <p
                                class="text-xs text-gray-500"
                            >
                                Tracking Number
                            </p>

                            <p
                                class="break-all text-lg font-black"
                            >
                                {{
                                    item.tracking_number ||
                                    'Not assigned'
                                }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Product Table -->
            <div class="mt-5">
                <table
                    class="w-full border-collapse border border-gray-300 text-sm"
                >
                    <thead>
                        <tr class="bg-gray-100">
                            <th
                                class="border border-gray-300 px-4 py-3 text-left"
                            >
                                Product
                            </th>

                            <th
                                class="border border-gray-300 px-4 py-3 text-left"
                            >
                                Variant
                            </th>

                            <th
                                class="border border-gray-300 px-4 py-3 text-center"
                            >
                                Qty
                            </th>

                            <th
                                class="border border-gray-300 px-4 py-3 text-right"
                            >
                                Unit Price
                            </th>

                            <th
                                class="border border-gray-300 px-4 py-3 text-right"
                            >
                                Total
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td
                                class="border border-gray-300 px-4 py-4 font-semibold"
                            >
                                {{ productName }}
                            </td>

                            <td
                                class="border border-gray-300 px-4 py-4"
                            >
                                {{ variantLabel || '—' }}
                            </td>

                            <td
                                class="border border-gray-300 px-4 py-4 text-center font-bold"
                            >
                                {{ quantity }}
                            </td>

                            <td
                                class="border border-gray-300 px-4 py-4 text-right"
                            >
                                ₱{{ formatMoney(price) }}
                            </td>

                            <td
                                class="border border-gray-300 px-4 py-4 text-right font-bold"
                            >
                                ₱{{ formatMoney(lineTotal) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Total -->
            <div
                class="mt-5 flex justify-end"
            >
                <div class="w-72">
                    <div
                        class="flex justify-between border-b border-gray-200 py-2"
                    >
                        <span class="text-gray-600">
                            Item Subtotal
                        </span>

                        <span class="font-semibold">
                            ₱{{ formatMoney(lineTotal) }}
                        </span>
                    </div>

                    <div
                        class="flex justify-between border-b border-gray-200 py-2"
                    >
                        <span class="text-gray-600">
                            Shipping
                        </span>

                        <span class="font-semibold">
                            ₱0.00
                        </span>
                    </div>

                    <div
                        class="flex justify-between py-3 text-lg"
                    >
                        <span class="font-black">
                            Total
                        </span>

                        <span class="font-black">
                            ₱{{ formatMoney(lineTotal) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Tracking Reference -->
            <div
                class="mt-8 border-y-2 border-gray-900 py-6 text-center"
            >
                <p
                    class="text-xs font-bold uppercase tracking-widest text-gray-500"
                >
                    Shipment Reference
                </p>

                <p
                    class="mt-2 text-3xl font-black tracking-widest"
                >
                    {{
                        item.tracking_number ||
                        orderNumber
                    }}
                </p>

                <div
                    class="mx-auto mt-4 max-w-xl"
                >
                    <div
                        class="flex h-16 items-end justify-center gap-1 overflow-hidden"
                    >
                        <span class="h-12 w-1 bg-black"></span>
                        <span class="h-16 w-2 bg-black"></span>
                        <span class="h-10 w-1 bg-black"></span>
                        <span class="h-14 w-3 bg-black"></span>
                        <span class="h-16 w-1 bg-black"></span>
                        <span class="h-11 w-2 bg-black"></span>
                        <span class="h-16 w-1 bg-black"></span>
                        <span class="h-16 w-3 bg-black"></span>
                        <span class="h-10 w-1 bg-black"></span>
                        <span class="h-14 w-2 bg-black"></span>
                        <span class="h-16 w-1 bg-black"></span>
                        <span class="h-12 w-3 bg-black"></span>
                        <span class="h-16 w-1 bg-black"></span>
                        <span class="h-10 w-2 bg-black"></span>
                        <span class="h-16 w-1 bg-black"></span>
                        <span class="h-16 w-2 bg-black"></span>
                        <span class="h-11 w-1 bg-black"></span>
                        <span class="h-16 w-3 bg-black"></span>
                        <span class="h-13 w-1 bg-black"></span>
                        <span class="h-16 w-2 bg-black"></span>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div
                class="mt-6 flex items-start justify-between gap-6 text-xs text-gray-500"
            >
                <div>
                    <p
                        class="font-bold text-gray-900"
                    >
                        ZELLORA
                    </p>

                    <p class="mt-1">
                        Seller fulfillment document
                    </p>
                </div>

                <div class="text-right">
                    <p>
                        Printed:
                        {{ formatDate(new Date()) }}
                    </p>

                    <p class="mt-1">
                        Please attach this waybill to the package.
                    </p>
                </div>
            </div>
        </div>

        <!-- Courier Pickup Scheduling Modal (SELLER-19) -->
        <div
            v-if="showPickupModal"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
            role="dialog"
            aria-modal="true"
        >
            <!-- Overlay -->
            <div
                class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity"
                @click="showPickupModal = false"
            ></div>

            <!-- Modal Panel -->
            <div
                class="relative z-10 w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl transition-all"
            >
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-gray-100 bg-[#F4FBFA] px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#087F8C] text-white shadow-sm">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.25V3.75a1.5 1.5 0 0 0-1.5-1.5h-9a1.5 1.5 0 0 0-1.5 1.5v10.5m12-10.5h-9" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">
                                Schedule Courier Pickup
                            </h3>
                            <p class="text-xs text-gray-500">
                                Order #{{ orderNumber }} &bull; {{ productName }}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="showPickupModal = false"
                        class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <form @submit.prevent="submitPickupSchedule" class="p-6 space-y-5">
                    <!-- Pickup Date -->
                    <div>
                        <div class="flex items-center justify-between">
                            <label class="block text-sm font-semibold text-gray-800">
                                Pickup Date <span class="text-red-500">*</span>
                            </label>
                            <!-- Quick date pills -->
                            <div class="flex items-center gap-1.5 text-xs">
                                <button
                                    type="button"
                                    @click="setQuickDate(0)"
                                    class="rounded-md border border-gray-200 px-2 py-0.5 text-gray-600 hover:border-[#087F8C] hover:text-[#087F8C]"
                                >
                                    Today
                                </button>
                                <button
                                    type="button"
                                    @click="setQuickDate(1)"
                                    class="rounded-md border border-gray-200 px-2 py-0.5 text-gray-600 hover:border-[#087F8C] hover:text-[#087F8C]"
                                >
                                    Tomorrow
                                </button>
                                <button
                                    type="button"
                                    @click="setQuickDate(2)"
                                    class="rounded-md border border-gray-200 px-2 py-0.5 text-gray-600 hover:border-[#087F8C] hover:text-[#087F8C]"
                                >
                                    In 2 Days
                                </button>
                            </div>
                        </div>
                        <input
                            type="date"
                            v-model="pickupForm.scheduled_date"
                            :min="getTodayDate()"
                            required
                            class="mt-1.5 block w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-sm font-medium text-gray-900 shadow-sm focus:border-[#087F8C] focus:outline-none focus:ring-1 focus:ring-[#087F8C]"
                        />
                        <p v-if="pickupForm.errors.scheduled_date" class="mt-1 text-xs text-red-600">
                            {{ pickupForm.errors.scheduled_date }}
                        </p>
                    </div>

                    <!-- Time Slot -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">
                            Preferred Time Window <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-2 grid grid-cols-1 gap-2.5 sm:grid-cols-3">
                            <button
                                type="button"
                                @click="pickupForm.time_slot = 'morning'"
                                :class="pickupForm.time_slot === 'morning' ? 'border-[#087F8C] bg-[#E8F7F6] text-[#043E44] ring-2 ring-[#087F8C]' : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300'"
                                class="flex flex-col items-center justify-center rounded-xl border p-3 text-center transition"
                            >
                                <span class="text-[11px] font-bold uppercase tracking-wider text-[#087F8C]">Morning</span>
                                <span class="mt-0.5 text-xs text-gray-600">9 AM – 1 PM</span>
                            </button>

                            <button
                                type="button"
                                @click="pickupForm.time_slot = 'afternoon'"
                                :class="pickupForm.time_slot === 'afternoon' ? 'border-[#087F8C] bg-[#E8F7F6] text-[#043E44] ring-2 ring-[#087F8C]' : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300'"
                                class="flex flex-col items-center justify-center rounded-xl border p-3 text-center transition"
                            >
                                <span class="text-[11px] font-bold uppercase tracking-wider text-[#087F8C]">Afternoon</span>
                                <span class="mt-0.5 text-xs text-gray-600">1 PM – 5 PM</span>
                            </button>

                            <button
                                type="button"
                                @click="pickupForm.time_slot = 'anytime'"
                                :class="pickupForm.time_slot === 'anytime' ? 'border-[#087F8C] bg-[#E8F7F6] text-[#043E44] ring-2 ring-[#087F8C]' : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300'"
                                class="flex flex-col items-center justify-center rounded-xl border p-3 text-center transition"
                            >
                                <span class="text-[11px] font-bold uppercase tracking-wider text-[#087F8C]">Full Day</span>
                                <span class="mt-0.5 text-xs text-gray-600">9 AM – 6 PM</span>
                            </button>
                        </div>
                        <p v-if="pickupForm.errors.time_slot" class="mt-1 text-xs text-red-600">
                            {{ pickupForm.errors.time_slot }}
                        </p>
                    </div>

                    <!-- Carrier Partner -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">
                            Assigned Carrier Partner <span class="text-red-500">*</span>
                        </label>
                        <select
                            v-model="pickupForm.carrier_partner"
                            class="mt-1.5 block w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-sm font-medium text-gray-900 shadow-sm focus:border-[#087F8C] focus:outline-none focus:ring-1 focus:ring-[#087F8C]"
                        >
                            <option value="Alona Express Fleet">Alona Express Fleet (Standard Fleet Dispatch)</option>
                            <option value="J&T Express Partner">J&T Express Partner (Integrated Hub)</option>
                            <option value="Flash Express Partner">Flash Express Partner (Rapid Transit)</option>
                            <option value="NinjaVan Partner">NinjaVan Partner (Local Logistics)</option>
                        </select>
                        <p v-if="pickupForm.errors.carrier_partner" class="mt-1 text-xs text-red-600">
                            {{ pickupForm.errors.carrier_partner }}
                        </p>
                    </div>

                    <!-- Warehouse / Collection Address -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">
                            Pickup / Warehouse Address <span class="text-xs font-normal text-gray-500">(Optional - defaults to seller profile)</span>
                        </label>
                        <input
                            type="text"
                            v-model="pickupForm.collection_address"
                            placeholder="Enter warehouse gate, building name, or exact pickup location"
                            class="mt-1.5 block w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-sm focus:border-[#087F8C] focus:outline-none focus:ring-1 focus:ring-[#087F8C]"
                        />
                        <p v-if="pickupForm.errors.collection_address" class="mt-1 text-xs text-red-600">
                            {{ pickupForm.errors.collection_address }}
                        </p>
                    </div>

                    <!-- Pickup Notes -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-800">
                            Instructions for Courier Rider <span class="text-xs font-normal text-gray-500">(Optional)</span>
                        </label>
                        <textarea
                            v-model="pickupForm.notes"
                            rows="2"
                            placeholder="e.g., Ring bell at Gate 2, fragile electronics package, call before arriving..."
                            class="mt-1.5 block w-full rounded-xl border border-gray-300 px-3.5 py-2 text-sm text-gray-900 shadow-sm focus:border-[#087F8C] focus:outline-none focus:ring-1 focus:ring-[#087F8C]"
                        ></textarea>
                        <p v-if="pickupForm.errors.notes" class="mt-1 text-xs text-red-600">
                            {{ pickupForm.errors.notes }}
                        </p>
                    </div>

                    <!-- Footer actions -->
                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-100">
                        <button
                            type="button"
                            @click="showPickupModal = false"
                            class="rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="pickupForm.processing"
                            class="inline-flex items-center gap-2 rounded-xl bg-[#087F8C] px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#066570] disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <svg v-if="pickupForm.processing" class="h-4 w-4 animate-spin text-white" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span>{{ pickupForm.processing ? 'Scheduling...' : 'Confirm Pickup Request' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </SellerLayout>
</template>

<style>
/*
|--------------------------------------------------------------------------
| Screen
|--------------------------------------------------------------------------
*/

#seller-waybill {
    display: none;
}

/*
|--------------------------------------------------------------------------
| Print
|--------------------------------------------------------------------------
*/

@media print {
    @page {
        size: A4;
        margin: 12mm;
    }

    html,
    body {
        background: white !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    /*
     * Hide the normal seller page.
     */
    #seller-order-page {
        display: none !important;
    }

    /*
     * Show printable waybill.
     */
    #seller-waybill {
        display: block !important;
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    /*
     * Hide application navigation and controls.
     */
    nav,
    aside,
    header,
    button,
    a {
        display: none !important;
    }

    /*
     * Prevent accidental shadows during printing.
     */
    * {
        box-shadow: none !important;
    }
}
</style>
