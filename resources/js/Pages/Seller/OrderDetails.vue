<script setup>
import { computed, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'

const props = defineProps({
    orderItem: {
        type: Object,
        required: true,
    },
})

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
const readyForPickupForm = useForm({})

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
| Print Waybill
|--------------------------------------------------------------------------
*/

const printOrder = () => {
    window.print()
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
            class="min-h-screen bg-gray-50 px-4 py-6 sm:px-6 lg:px-8"
        >
            <!-- Header -->
            <div
                class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <div
                        class="mb-2 flex items-center gap-2"
                    >
                        <Link
                            :href="route('seller.orders')"
                            class="text-sm font-medium text-gray-500 hover:text-gray-700"
                        >
                            Orders
                        </Link>

                        <span class="text-gray-400">
                            /
                        </span>

                        <span
                            class="text-sm text-gray-700"
                        >
                            Order #{{ orderNumber }}
                        </span>
                    </div>

                    <h1
                        class="text-2xl font-bold text-gray-900"
                    >
                        Order #{{ orderNumber }}
                    </h1>

                    <p
                        class="mt-1 text-sm text-gray-500"
                    >
                        Manage this order item and fulfillment status.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        @click="printOrder"
                        class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50"
                    >
                        🖨️ Print Waybill
                    </button>

                    <Link
                        :href="route('seller.orders')"
                        class="inline-flex items-center rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-gray-800"
                    >
                        ← Back to Orders
                    </Link>
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
                    <div class="flex flex-wrap gap-2">
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
                            :disabled="packForm.processing"
                            class="rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-purple-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{
                                packForm.processing
                                    ? 'Packing...'
                                    : 'Mark as Packed'
                            }}
                        </button>

                        <!-- Packed → Ready for Pickup -->
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
                                    : 'Mark as Ready for Pickup'
                            }}
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
                            v-if="
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
                        <h2
                            class="mb-5 text-lg font-bold text-gray-900"
                        >
                            Payment
                        </h2>

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
                            class="mt-4 flex items-center justify-between"
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