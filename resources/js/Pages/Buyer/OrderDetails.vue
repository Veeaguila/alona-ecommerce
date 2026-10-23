<!-- Buyer Order Details with tracking milestones, cancellation, reordering, and return/refund requests. -->
<script setup>
import Icon from '@/Components/Icon.vue'
import { ref, computed } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import BuyerAccountLayout from '@/Layouts/BuyerAccountLayout.vue'

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
    status: {
        type: String,
        default: '',
    },
})

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const money = value => {
    const amount = Number(value ?? 0)
    return `₱${amount.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`
}

const formatDate = value => {
    if (!value) return 'Date unavailable'
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return 'Date unavailable'
    return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

const formatDateShort = value => {
    if (!value) return ''
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return ''
    return date.toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
    })
}

const formatPaymentMethod = value => {
    if (!value) return 'Payment method unavailable'
    const methods = {
        cod: 'Cash on Delivery',
        cash_on_delivery: 'Cash on Delivery',
        gcash: 'GCash',
        maya: 'Maya',
        card: 'Credit / Debit Card',
    }
    return methods[value] || value
}

const normalizeStatus = value => String(value ?? '').toLowerCase()

const getStatusBadge = value => {
    const status = normalizeStatus(value)

    if (props.order.payment_status === 'unpaid' && props.order.payment_method !== 'cod' && status !== 'cancelled') {
        return {
            label: 'To Pay',
            color: 'bg-[#FFFBEB] text-[#B45309] ring-1 ring-[#F59E0B]/30',
        }
    }

    const badges = {
        pending: {
            label: 'To Pack',
            color: 'bg-[#FFF7E6] text-[#A66A00] ring-1 ring-[#F4B942]/30',
        },
        processing: {
            label: 'Processing',
            color: 'bg-[#E8F7F6] text-[#087F8C] ring-1 ring-[#16A6A0]/20',
        },
        packed: {
            label: 'Packed (To Ship)',
            color: 'bg-[#E8F7F6] text-[#087F8C] ring-1 ring-[#16A6A0]/20',
        },
        ready_for_pickup: {
            label: 'Ready for Pickup',
            color: 'bg-[#E8F7F6] text-[#087F8C] ring-1 ring-[#16A6A0]/20',
        },
        shipped: {
            label: 'In Transit',
            color: 'bg-[#F0EAFE] text-[#6D4BC3] ring-1 ring-[#8B5CF6]/20',
        },
        out_for_delivery: {
            label: 'Out for Delivery',
            color: 'bg-[#FFF3E8] text-[#C56A20] ring-1 ring-[#F97316]/20',
        },
        delivered: {
            label: 'Delivered',
            color: 'bg-[#EAF8F1] text-[#17784F] ring-1 ring-[#22A06B]/20',
        },
        completed: {
            label: 'Completed',
            color: 'bg-[#EAF8F1] text-[#17784F] ring-1 ring-[#22A06B]/20',
        },
        cancelled: {
            label: 'Cancelled',
            color: 'bg-[#FDECEC] text-[#B94242] ring-1 ring-[#E85D5D]/20',
        },
        refunded: {
            label: 'Refunded',
            color: 'bg-[#FDECEC] text-[#B94242] ring-1 ring-[#E85D5D]/20',
        },
    }

    return badges[status] || {
        label: value || 'Unknown',
        color: 'bg-[#F8FAF9] text-[#64748B] ring-1 ring-[#E5E7EB]',
    }
}

const getReturnStatusBadge = status => {
    const s = normalizeStatus(status)
    const map = {
        pending: { label: 'Pending Seller Review', color: 'bg-[#FFF7E6] text-[#A66A00] ring-1 ring-[#F4B942]/30' },
        approved: { label: 'Return Approved', color: 'bg-[#EBF5FF] text-[#1E40AF] ring-1 ring-[#3B82F6]/20' },
        rejected: { label: 'Rejected', color: 'bg-[#FDECEC] text-[#B94242] ring-1 ring-[#E85D5D]/20' },
        in_dispute: { label: 'In Dispute', color: 'bg-[#F3E8FF] text-[#6B21A8] ring-1 ring-[#9333EA]/20' },
        item_returned: { label: 'Item Returned / In Inspection', color: 'bg-[#F3E8FF] text-[#6B21A8] ring-1 ring-[#9333EA]/20' },
        refunded: { label: 'Refund Issued', color: 'bg-[#EAF8F1] text-[#17784F] ring-1 ring-[#22A06B]/20' },
        completed: { label: 'Refund Completed', color: 'bg-[#EAF8F1] text-[#17784F] ring-1 ring-[#22A06B]/20' },
        cancelled: { label: 'Request Cancelled', color: 'bg-[#F1F5F9] text-[#64748B] ring-1 ring-[#E5E7EB]' },
    }
    return map[s] || { label: status, color: 'bg-gray-100 text-gray-700' }
}

const getItemTotal = item => Number(item?.price ?? 0) * Number(item?.quantity ?? 0)

const copiedTracking = ref(false)
const copyTracking = text => {
    if (!text) return
    navigator.clipboard.writeText(text)
    copiedTracking.value = true
    setTimeout(() => {
        copiedTracking.value = false
    }, 2000)
}

/*
|--------------------------------------------------------------------------
| Tracking Milestones (BUYER-19)
|--------------------------------------------------------------------------
*/

const milestones = computed(() => {
    const status = normalizeStatus(props.order.status)
    const isCancelled = status === 'cancelled'

    if (isCancelled) {
        return [
            { key: 'placed', label: 'Order Placed', completed: true, date: props.order.created_at },
            { key: 'cancelled', label: 'Order Cancelled', completed: true, active: true, error: true, date: props.order.cancelled_at || props.order.updated_at },
        ]
    }

    const stages = [
        { key: 'pending', label: 'Order Placed', step: 1 },
        { key: 'packed', label: 'Packed & Ready', step: 2 },
        { key: 'shipped', label: 'In Transit', step: 3 },
        { key: 'out_for_delivery', label: 'Out for Delivery', step: 4 },
        { key: 'delivered', label: 'Delivered', step: 5 },
    ]

    const statusWeights = {
        pending: 1,
        processing: 1,
        packed: 2,
        ready_for_pickup: 2,
        shipped: 3,
        out_for_delivery: 4,
        delivered: 5,
        completed: 5,
    }

    const currentWeight = statusWeights[status] || 1

    const getStageDate = (stageKey) => {
        if (stageKey === 'pending') return props.order.created_at
        if (stageKey === 'delivered') return props.order.delivered_at || props.order.items?.[0]?.delivered_at
        const historyMatch = props.order.status_histories?.find(h => normalizeStatus(h.status) === stageKey)
        return historyMatch?.created_at || null
    }

    return stages.map(stage => {
        const stageWeight = statusWeights[stage.key] || 1
        return {
            ...stage,
            completed: currentWeight >= stageWeight,
            active: currentWeight === stageWeight,
            date: getStageDate(stage.key),
        }
    })
})

const isMarkingReceived = ref(false)
const markOrderReceived = () => {
    if (isMarkingReceived.value) return
    if (!confirm('Have you received all items in this order in good condition?')) return
    isMarkingReceived.value = true
    router.post(route('buyer.orders.receive', props.order.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            isMarkingReceived.value = false
        },
    })
}

/*
|--------------------------------------------------------------------------
| Cancellation (BUYER-18)
|--------------------------------------------------------------------------
*/

const showCancelModal = ref(false)
const cancelForm = useForm({
    reason: 'Need to change shipping address',
})

const cancelReasonOptions = [
    'Need to change shipping address',
    'Found a cheaper price elsewhere',
    'Order created by mistake / duplicate',
    'Delivery lead time is too long',
    'Changed mind / No longer needed',
    'Other reason',
]

const openCancelModal = () => {
    cancelForm.reason = cancelReasonOptions[0]
    cancelForm.clearErrors()
    showCancelModal.value = true
}

const closeCancelModal = () => {
    if (cancelForm.processing) return
    showCancelModal.value = false
    cancelForm.reset()
}

const submitCancelOrder = () => {
    cancelForm.post(route('buyer.orders.cancel', props.order.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeCancelModal()
        },
    })
}

/*
|--------------------------------------------------------------------------
| Reorder (BUYER-20)
|--------------------------------------------------------------------------
*/

const isReordering = ref(false)
const reorderEntireOrder = () => {
    isReordering.value = true
    router.post(route('buyer.orders.reorder', props.order.id), {}, {
        onFinish: () => {
            isReordering.value = false
        },
    })
}

/*
|--------------------------------------------------------------------------
| Payment Actions (BUY-PAY-002, BUY-PAY-003, BUY-PAY-004)
|--------------------------------------------------------------------------
*/

const showPayModal = ref(false)
const selectedPayMethod = ref(props.order.payment_method !== 'cod' ? props.order.payment_method : 'gcash')
const isPaying = ref(false)

const openPayModal = () => {
    showPayModal.value = true
}

const closePayModal = () => {
    if (isPaying.value) return
    showPayModal.value = false
}

const submitPayment = (action = 'success') => {
    isPaying.value = true
    router.post(
        route('buyer.orders.pay', props.order.id),
        {
            action,
            payment_method: selectedPayMethod.value,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                isPaying.value = false
                showPayModal.value = false
            },
        }
    )
}

const reorderingItemId = ref(null)
const reorderSingleItem = itemId => {
    reorderingItemId.value = itemId
    router.post(route('buyer.order-items.reorder', itemId), {}, {
        onFinish: () => {
            reorderingItemId.value = null
        },
    })
}

/*
|--------------------------------------------------------------------------
| Delivery Confirmation
|--------------------------------------------------------------------------
*/

const confirmingItemId = ref(null)
const confirmDelivery = itemId => {
    if (!itemId || confirmingItemId.value) return
    if (!confirm('Confirm that you have received this item in good condition?')) return

    confirmingItemId.value = itemId
    router.patch(route('buyer.order-items.confirm-delivery', itemId), {}, {
        preserveScroll: true,
        onFinish: () => {
            confirmingItemId.value = null
        },
    })
}

/*
|--------------------------------------------------------------------------
| Reviews
|--------------------------------------------------------------------------
*/

const showReviewModal = ref(false)
const reviewingItem = ref(null)
const editingReview = ref(null)
const reviewForm = useForm({
    rating: 5,
    comment: '',
})

const openReviewModal = (item, existingReview = null) => {
    if (!item?.product_id) return
    reviewingItem.value = item
    editingReview.value = existingReview
    reviewForm.rating = existingReview?.rating || 5
    reviewForm.comment = existingReview?.comment || ''
    reviewForm.clearErrors()
    showReviewModal.value = true
}

const closeReviewModal = () => {
    if (reviewForm.processing) return
    showReviewModal.value = false
    reviewingItem.value = null
    editingReview.value = null
    reviewForm.reset()
}

const submitReview = () => {
    if (reviewForm.processing || !reviewingItem.value?.product_id) return
    if (editingReview.value) {
        reviewForm.patch(route('buyer.reviews.update', editingReview.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                closeReviewModal()
            },
        })
    } else {
        reviewForm.post(route('buyer.reviews.store', reviewingItem.value.product_id), {
            preserveScroll: true,
            onSuccess: () => {
                closeReviewModal()
            },
        })
    }
}

/*
|--------------------------------------------------------------------------
| Returns & Refunds (BUYER-21, BUYER-22, BUYER-23)
|--------------------------------------------------------------------------
*/

const showReturnModal = ref(false)
const returningItem = ref(null)
const evidencePreviews = ref([])

const returnForm = useForm({
    type: 'return_and_refund',
    reason: 'damaged_defective',
    reason_details: '',
    evidence_images: [],
})

const returnReasons = [
    { value: 'damaged_defective', label: 'Item is damaged, broken, or defective' },
    { value: 'wrong_item', label: 'Received wrong item / color / size' },
    { value: 'missing_item_parts', label: 'Missing items or accessories' },
    { value: 'item_not_as_described', label: 'Item differs significantly from description' },
    { value: 'changed_mind', label: 'Changed mind (within return policy)' },
    { value: 'other', label: 'Other reasons' },
]

const openReturnModal = item => {
    returningItem.value = item
    returnForm.reset()
    returnForm.type = 'return_and_refund'
    returnForm.reason = 'damaged_defective'
    returnForm.reason_details = ''
    returnForm.evidence_images = []
    evidencePreviews.value = []
    returnForm.clearErrors()
    showReturnModal.value = true
}

const closeReturnModal = () => {
    if (returnForm.processing) return
    showReturnModal.value = false
    returningItem.value = null
    evidencePreviews.value = []
    returnForm.reset()
}

const handleFileChange = e => {
    const files = Array.from(e.target.files || [])
    returnForm.evidence_images = files

    evidencePreviews.value = []
    files.forEach(file => {
        const reader = new FileReader()
        reader.onload = ev => {
            evidencePreviews.value.push(ev.target.result)
        }
        reader.readAsDataURL(file)
    })
}

const submitReturnRequest = () => {
    if (!returningItem.value || returnForm.processing) return

    returnForm.clearErrors()

    if (!returnForm.reason_details || returnForm.reason_details.trim().length < 10) {
        returnForm.setError('reason_details', 'Please provide an explanation of at least 10 characters.')
        return
    }

    if (!returnForm.evidence_images || returnForm.evidence_images.length === 0) {
        returnForm.setError('evidence_images', 'Please upload at least 1 photo as evidence.')
        return
    }

    returnForm.post(route('buyer.order-items.return', returningItem.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeReturnModal()
        },
    })
}

const cancelingReturnId = ref(null)
const cancelReturnRequest = returnRequestId => {
    if (!confirm('Are you sure you want to cancel this return/refund request?')) return
    cancelingReturnId.value = returnRequestId
    router.post(route('buyer.return-requests.cancel', returnRequestId), {}, {
        preserveScroll: true,
        onFinish: () => {
            cancelingReturnId.value = null
        },
    })
}

const escalatingReturnId = ref(null)
const escalateReturn = returnRequestId => {
    if (!confirm('Are you sure you want to escalate this rejected return request to a dispute for administrative review?')) return
    escalatingReturnId.value = returnRequestId
    router.post(route('buyer.return-requests.escalate', returnRequestId), {}, {
        preserveScroll: true,
        onFinish: () => {
            escalatingReturnId.value = null
        },
    })
}

const imageUrl = path => {
    if (!path) return null
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/storage/')) return path
    return `/storage/${path.replace(/^\/+/, '')}`
}

const itemImage = item => {
    return imageUrl(
        item?.variant?.image_path ||
        item?.product?.image_path ||
        item?.product?.images?.[0]?.image_path ||
        item?.product?.primary_image_url ||
        item?.product?.image_url ||
        item?.image_path ||
        null
    )
}

const getOrderShopName = computed(() => {
    const seller = props.order?.items?.[0]?.product?.seller
    return seller?.store_name || seller?.shop_name || seller?.name || 'Marketplace Seller'
})

const getItemShopName = item => {
    const seller = item?.product?.seller
    return seller?.store_name || seller?.shop_name || seller?.name || 'Marketplace Seller'
}
</script>

<template>
    <Head :title="`Order ${order.order_number || order.id}`" />

    <BuyerAccountLayout active-menu="purchase">
        <div class="space-y-4">

                <!-- TOP NAV & ACTIONS -->
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <Link
                        :href="route('buyer.orders')"
                        class="inline-flex items-center gap-1.5 text-xs font-bold text-[#087F8C] transition hover:text-[#066B76] sm:text-sm"
                    >
                        <span>←</span>
                        <span>Back to My Orders</span>
                    </Link>

                    <div class="flex flex-wrap items-center gap-2">
                        <!-- ORDER RECEIVED BUTTON (BUY-TRK-003, BUY-TRK-004) -->
                        <button
                            v-if="normalizeStatus(order.status) === 'delivered'"
                            type="button"
                            :disabled="isMarkingReceived"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-[#087F8C] px-3.5 py-1.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#066B76] disabled:opacity-50"
                            @click="markOrderReceived"
                        >
                            <span>✓</span>
                            <span>{{ isMarkingReceived ? 'Completing...' : 'Order Received' }}</span>
                        </button>

                        <!-- PAY NOW BUTTON (BUY-PAY-002, BUY-PAY-003) -->
                        <button
                            v-if="order.payment_status === 'unpaid' && order.payment_method !== 'cod' && order.status !== 'cancelled'"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-[#087F8C] px-3.5 py-1.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#066B76]"
                            @click="openPayModal"
                        >
                            <span><Icon name="check" class="h-4 w-4" /></span>
                            <span>Pay Now</span>
                        </button>

                        <!-- CANCEL ORDER BUTTON (BUYER-18) -->
                        <button
                            v-if="order.can_cancel"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-[#FCA5A5] bg-[#FEF2F2] px-3.5 py-1.5 text-xs font-bold text-[#DC2626] transition hover:bg-[#FEE2E2]"
                            @click="openCancelModal"
                        >
                            <span>✕</span>
                            <span>Cancel Order</span>
                        </button>

                        <!-- REORDER BUTTON (BUYER-20) -->
                        <button
                            type="button"
                            :disabled="isReordering"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-[#D5E4E3] bg-white px-3.5 py-1.5 text-xs font-bold text-[#087F8C] shadow-sm transition hover:bg-[#E8F7F6] disabled:opacity-50"
                            @click="reorderEntireOrder"
                        >
                            <span><Icon name="undo" class="h-4 w-4" /></span>
                            <span>{{ isReordering ? 'Adding to cart...' : 'Buy Again' }}</span>
                        </button>
                    </div>
                </div>

                <!-- SUCCESS MESSAGE -->
                <div
                    v-if="status"
                    class="mt-4 flex items-start gap-2.5 rounded-xl border border-[#CDE9E4] bg-[#EAF8F1] px-3.5 py-3 text-xs font-bold text-[#17784F] sm:text-sm"
                >
                    <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12l4 4L19 6" />
                    </svg>
                    <span>{{ status }}</span>
                </div>

                <!-- PAYMENT ERROR / WARNING MESSAGE -->
                <div
                    v-if="$page.props.errors?.payment"
                    class="mt-4 flex items-start gap-2.5 rounded-xl border border-[#FCA5A5] bg-[#FEF2F2] px-3.5 py-3 text-xs font-bold text-[#DC2626] sm:text-sm"
                >
                    <span class="mt-0.5"><Icon name="warning" class="h-4 w-4 shrink-0" /></span>
                    <span>{{ $page.props.errors.payment }}</span>
                </div>

                <!-- CANCELLED BANNER -->
                <div
                    v-if="order.status === 'cancelled'"
                    class="mt-4 rounded-2xl border border-[#FCA5A5] bg-[#FEF2F2] p-4 text-[#991B1B] sm:p-5"
                >
                    <div class="flex items-start gap-3">
                        <span class="text-xl"><Icon name="warning" class="h-4 w-4" /></span>
                        <div>
                            <h2 class="text-sm font-extrabold sm:text-base">Order Cancelled</h2>
                            <p class="mt-1 text-xs text-[#B91C1C]">
                                {{ order.cancellation_reason ? `Reason: ${order.cancellation_reason}` : 'This order was cancelled and items have been returned to stock.' }}
                            </p>
                            <p v-if="order.cancelled_at" class="mt-1 text-[11px] text-[#DC2626]">
                                Cancelled on {{ formatDate(order.cancelled_at) }}
                            </p>
                            <div v-if="order.payment_status === 'refunded' || order.refund_status" class="mt-2.5 inline-flex items-center gap-1.5 rounded-lg bg-red-100 px-2.5 py-1 text-xs font-bold text-[#991B1B]">
                                <span>↩️</span>
                                <span>
                                    Refund: {{ order.refund_status === 'refund_started' ? 'Refund Started' : (order.refund_status || 'Refunded') }} ({{ money(order.refund_amount || order.total) }})
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TRACKING PROGRESS (BUYER-19) -->
                <section class="mt-4 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-[0_6px_22px_rgba(15,23,42,0.04)] sm:p-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-[#087F8C]"></span>
                                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-[#087F8C]">
                                    Tracking Status
                                </p>
                            </div>
                            <h2 class="mt-1 text-base font-extrabold text-[#1F2937] sm:text-lg">
                                {{ order.order_number || `Order #${order.id}` }}
                            </h2>
                        </div>

                        <span
                            :class="[
                                'inline-flex w-fit items-center rounded-full px-3 py-1.5 text-xs font-extrabold',
                                getStatusBadge(order.status).color
                            ]"
                        >
                            {{ getStatusBadge(order.status).label }}
                        </span>
                    </div>

                    <!-- STEP PROGRESSION BAR -->
                    <div class="mt-6">
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-5 sm:gap-2">
                            <div
                                v-for="(step, idx) in milestones"
                                :key="step.key"
                                class="flex flex-col items-center text-center"
                            >
                                <div class="flex items-center justify-center">
                                    <div
                                        :class="[
                                            'flex h-9 w-9 items-center justify-center rounded-full text-xs font-extrabold transition',
                                            step.error
                                                ? 'bg-[#EF4444] text-white'
                                                : step.completed
                                                    ? 'bg-[#087F8C] text-white'
                                                    : 'bg-[#F1F5F9] text-[#94A3B8]'
                                        ]"
                                    >
                                        <span v-if="step.completed && !step.error">✓</span>
                                        <span v-else-if="step.error">✕</span>
                                        <span v-else>{{ idx + 1 }}</span>
                                    </div>
                                </div>
                                <p
                                    class="mt-2 text-xs font-bold"
                                    :class="step.completed ? 'text-[#1F2937]' : 'text-[#94A3B8]'"
                                >
                                    {{ step.label }}
                                </p>
                                <p v-if="step.date" class="text-[10px] text-[#94A3B8]">
                                    {{ formatDateShort(step.date) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- MAIN DETAILS GRID -->
                <div class="mt-5 grid gap-5 lg:grid-cols-[1fr_360px] xl:grid-cols-[1fr_390px]">
                    <!-- LEFT: ORDER ITEMS & RETURNS -->
                    <div class="space-y-4">
                        <section class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm">
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#EEF1F2] bg-[#FCFDFC] px-4 py-3.5 sm:px-5">
                                <div class="flex items-center gap-2.5">
                                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 22V12h6v10" />
                                        </svg>
                                    </span>
                                    <span class="text-sm font-extrabold text-[#1F2937]">
                                        {{ getOrderShopName }}
                                    </span>
                                    <span class="rounded-md bg-[#E8F7F6] px-2 py-0.5 text-[10px] font-extrabold text-[#087F8C]">
                                        Shop
                                    </span>
                                </div>
                                <h3 class="text-xs font-semibold text-[#64748B]">
                                    Order Items ({{ order.items?.length || 0 }})
                                </h3>
                            </div>

                            <div class="divide-y divide-[#EEF1F2] px-4 sm:px-5">
                                <article
                                    v-for="item in order.items"
                                    :key="item.id"
                                    class="py-4"
                                >
                                    <div class="flex gap-3.5 sm:gap-4">
                                        <!-- Product Image -->
                                        <div class="h-16 w-16 shrink-0 overflow-hidden rounded-xl border border-[#E5E7EB] bg-gray-50 sm:h-20 sm:w-20">
                                            <img
                                                v-if="itemImage(item)"
                                                :src="itemImage(item)"
                                                :alt="item.product_name || item.product?.name"
                                                class="h-full w-full object-cover"
                                            />
                                            <div v-else class="flex h-full w-full items-center justify-center text-xs text-gray-400">
                                                No image
                                            </div>
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div>
                                                            <h4 class="text-sm font-extrabold text-[#1F2937]">
                                                                {{ item.product_name || item.product?.name || 'Product' }}
                                                            </h4>
                                                            <p v-if="item.variant_label || item.variant?.name" class="mt-0.5 text-xs text-[#64748B]">
                                                                Variation: {{ item.variant_label || item.variant?.name }}
                                                            </p>
                                                            <p class="mt-1 text-xs text-[#64748B]">
                                                                {{ money(item.price) }} × {{ item.quantity || 0 }}
                                                            </p>
                                                        </div>

                                                        <p class="text-sm font-extrabold text-[#1F2937]">
                                                            {{ money(getItemTotal(item)) }}
                                                        </p>
                                                    </div>

                                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                                        <span :class="getStatusBadge(item.status || order.status).color" class="rounded-full px-2.5 py-0.5 text-[9px] font-extrabold">
                                                            {{ getStatusBadge(item.status || order.status).label }}
                                                        </span>

                                                        <span class="text-[11px] font-semibold text-[#64748B]">
                                                            Seller: <span class="font-bold text-[#1F2937]">{{ getItemShopName(item) }}</span>
                                                        </span>
                                                    </div>

                                            <!-- CARRIER TRACKING DETAILS (BUYER-19) -->
                                            <div
                                                v-if="item.tracking_number"
                                                class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-[#D9F0EF] bg-[#E8F7F6] p-3 text-xs"
                                            >
                                                <div class="flex items-center gap-2">
                                                    <span class="text-base"><Icon name="package" class="h-4 w-4" /></span>
                                                    <div>
                                                        <p class="font-extrabold text-[#087F8C]">
                                                            {{ item.courier_name || 'Courier' }}
                                                        </p>
                                                        <p class="font-mono text-[#334155]">
                                                            Tracking #: {{ item.tracking_number }}
                                                        </p>
                                                    </div>
                                                </div>

                                                <button
                                                    type="button"
                                                    class="rounded-lg bg-white px-2.5 py-1 text-[10px] font-bold text-[#087F8C] shadow-sm hover:bg-[#F1F5F9]"
                                                    @click="copyTracking(item.tracking_number)"
                                                >
                                                    {{ copiedTracking ? 'Copied! ✓' : 'Copy #' }}
                                                </button>
                                            </div>

                                            <!-- RETURN/REFUND STATUS DISPLAY (BUYER-23) -->
                                            <div
                                                v-if="item.active_return_request || item.latest_return_request"
                                                class="mt-3 rounded-xl border border-[#FED7AA] bg-[#FFF7ED] p-3.5"
                                            >
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-base"><Icon name="undo" class="h-4 w-4" /></span>
                                                        <span class="text-xs font-extrabold text-[#9A3412]">
                                                            {{ item.latest_return_request.type === 'refund_only' ? 'Refund Request' : 'Return & Refund Request' }}
                                                        </span>
                                                    </div>

                                                    <span :class="getReturnStatusBadge(item.latest_return_request.status).color" class="rounded-full px-2 py-0.5 text-[9px] font-bold">
                                                        {{ getReturnStatusBadge(item.latest_return_request.status).label }}
                                                    </span>
                                                </div>

                                                <p class="mt-2 text-xs text-[#7C2D12]">
                                                    <strong class="font-semibold">Reason:</strong>
                                                    {{ item.latest_return_request.reason }}
                                                    <span v-if="item.latest_return_request.reason_details">
                                                        — {{ item.latest_return_request.reason_details }}
                                                    </span>
                                                </p>

                                                <!-- EVIDENCE IMAGES PREVIEW (BUYER-23) -->
                                                <div
                                                    v-if="item.latest_return_request.evidence_images?.length"
                                                    class="mt-2.5 flex flex-wrap gap-2"
                                                >
                                                    <a
                                                        v-for="(img, idx) in item.latest_return_request.evidence_images"
                                                        :key="idx"
                                                        :href="imageUrl(img)"
                                                        target="_blank"
                                                        class="h-12 w-12 overflow-hidden rounded-lg border border-[#FDBA74] bg-white ring-1 ring-black/5"
                                                    >
                                                        <img :src="imageUrl(img)" class="h-full w-full object-cover" alt="Evidence" />
                                                    </a>
                                                </div>

                                                <!-- SELLER / ADMIN RESPONSE (BUYER-23) -->
                                                <div
                                                    v-if="(item.latest_return_request.seller_response || item.latest_return_request.admin_response) && item.latest_return_request.status !== 'rejected'"
                                                    class="mt-2.5 rounded-lg bg-white/80 p-2.5 text-xs text-[#431407]"
                                                >
                                                    <p class="font-extrabold text-[#9A3412]">Seller Decision / Response:</p>
                                                    <p class="mt-0.5 leading-relaxed">
                                                        {{ item.latest_return_request.seller_response || item.latest_return_request.admin_response }}
                                                    </p>
                                                </div>

                                                <!-- REJECTION REASON (SEL-RET-004) -->
                                                <div
                                                    v-if="item.latest_return_request.status === 'rejected'"
                                                    class="mt-2.5 rounded-lg border border-red-200 bg-red-50/80 p-2.5 text-xs text-[#991B1B]"
                                                >
                                                    <p class="font-extrabold text-red-900">Rejection Reason:</p>
                                                    <p class="mt-0.5 leading-relaxed font-medium">
                                                        {{ item.latest_return_request.rejection_reason || item.latest_return_request.seller_response }}
                                                    </p>
                                                </div>

                                                <!-- IN DISPUTE STATUS (SEL-RET-005) -->
                                                <div
                                                    v-if="item.latest_return_request.status === 'in_dispute'"
                                                    class="mt-2.5 rounded-lg border border-purple-200 bg-purple-50/80 p-2.5 text-xs text-purple-950"
                                                >
                                                    <p class="font-extrabold text-purple-900">Dispute Under Administrative Review:</p>
                                                    <p class="mt-0.5 leading-relaxed">
                                                        This return claim has been escalated to dispute mediation. Both parties will be contacted by platform support.
                                                    </p>
                                                </div>

                                                <!-- RETURN INSTRUCTIONS & WAREHOUSE ADDRESS -->
                                                <div
                                                    v-if="item.latest_return_request.return_instructions || item.latest_return_request.return_warehouse_address"
                                                    class="mt-2.5 rounded-lg border border-blue-200 bg-blue-50/70 p-2.5 text-xs text-blue-950"
                                                >
                                                    <p class="font-extrabold text-blue-900">Return Instructions:</p>
                                                    <p class="mt-0.5 leading-relaxed">
                                                        {{ item.latest_return_request.return_instructions }}
                                                    </p>
                                                    <p v-if="item.latest_return_request.return_warehouse_address" class="mt-1 font-semibold text-blue-800">
                                                        Ship to Warehouse: <span class="font-normal">{{ item.latest_return_request.return_warehouse_address }}</span>
                                                    </p>
                                                </div>

                                                <!-- RETURN SHIPPING TRACKING -->
                                                <div
                                                    v-if="item.latest_return_request.return_tracking_number"
                                                    class="mt-2.5 rounded-lg border border-indigo-200 bg-indigo-50/70 p-2.5 text-xs text-indigo-950"
                                                >
                                                    <p class="font-extrabold text-indigo-900">Return Carrier & Waybill Tracking:</p>
                                                    <p class="mt-0.5 font-mono font-bold text-indigo-800">
                                                        {{ item.latest_return_request.return_carrier }}: {{ item.latest_return_request.return_tracking_number }}
                                                    </p>
                                                    <p class="mt-0.5 text-[11px] text-indigo-700 capitalize">
                                                        Status: {{ item.latest_return_request.return_shipping_status?.replace(/_/g, ' ') }}
                                                    </p>
                                                </div>

                                                <!-- CANCEL RETURN REQUEST (BUYER-21) -->
                                                <div
                                                    v-if="item.latest_return_request.status === 'pending'"
                                                    class="mt-2.5 flex justify-end"
                                                >
                                                    <button
                                                        type="button"
                                                        :disabled="cancelingReturnId === item.latest_return_request.id"
                                                        class="text-[11px] font-bold text-[#DC2626] hover:underline disabled:opacity-50"
                                                        @click="cancelReturnRequest(item.latest_return_request.id)"
                                                    >
                                                        {{ cancelingReturnId === item.latest_return_request.id ? 'Cancelling...' : 'Cancel Request' }}
                                                    </button>
                                                </div>

                                                <!-- ESCALATE RETURN REQUEST (SEL-RET-004, SEL-RET-005) -->
                                                <div
                                                    v-if="item.latest_return_request.status === 'rejected' && item.latest_return_request.can_escalate"
                                                    class="mt-2.5 flex justify-end"
                                                >
                                                    <button
                                                        type="button"
                                                        :disabled="escalatingReturnId === item.latest_return_request.id"
                                                        class="inline-flex items-center gap-1.5 rounded-xl bg-purple-600 px-3.5 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-purple-700 disabled:opacity-50 transition"
                                                        @click="escalateReturn(item.latest_return_request.id)"
                                                    >
                                                        <span>⚠️</span>
                                                        <span>{{ escalatingReturnId === item.latest_return_request.id ? 'Escalating...' : 'Escalate to Dispute' }}</span>
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- ITEM ACTIONS (BUYER-20, BUYER-21, BUYER-22) -->
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                <!-- CONFIRM DELIVERY / ORDER RECEIVED (BUY-TRK-003, BUY-TRK-004) -->
                                                <button
                                                    v-if="item.can_confirm_delivery"
                                                    type="button"
                                                    :disabled="confirmingItemId === item.id"
                                                    class="inline-flex items-center rounded-xl bg-[#087F8C] px-3.5 py-1.5 text-xs font-extrabold text-white transition hover:bg-[#066B76] disabled:opacity-60"
                                                    @click="confirmDelivery(item.id)"
                                                >
                                                    {{ confirmingItemId === item.id ? 'Confirming...' : '✓ Order Received' }}
                                                </button>

                                                <!-- WRITE REVIEW / RATE (BUY-TRK-003, BUY-RATE-003) -->
                                                <button
                                                    v-if="item.can_review && !item.has_reviewed"
                                                    type="button"
                                                    class="inline-flex items-center rounded-xl border border-[#FDE68A] bg-[#FFFBEB] px-3.5 py-1.5 text-xs font-bold text-[#B45309] transition hover:bg-[#FEF3C7]"
                                                    @click="openReviewModal(item)"
                                                >
                                                    ★ Rate / Review
                                                </button>
                                                <button
                                                    v-else-if="item.has_reviewed && item.user_review?.can_edit"
                                                    type="button"
                                                    class="inline-flex items-center rounded-xl border border-[#D7E0E2] bg-white px-3.5 py-1.5 text-xs font-bold text-[#087F8C] transition hover:bg-[#E8F7F6]"
                                                    @click="openReviewModal(item, item.user_review)"
                                                >
                                                    ★ Edit Review
                                                </button>

                                                <!-- REQUEST RETURN / REFUND (BUYER-21, 22) -->
                                                <button
                                                    v-if="item.can_return"
                                                    type="button"
                                                    class="inline-flex items-center gap-1 rounded-xl border border-[#FED7AA] bg-[#FFF7ED] px-3.5 py-1.5 text-xs font-bold text-[#C2410C] transition hover:bg-[#FFEDD5]"
                                                    @click="openReturnModal(item)"
                                                >
                                                    <span>Return / Refund</span>
                                                </button>

                                                <!-- BUY AGAIN / REORDER ITEM (BUYER-20) -->
                                                <button
                                                    v-if="item.can_reorder"
                                                    type="button"
                                                    :disabled="reorderingItemId === item.id"
                                                    class="inline-flex items-center gap-1 rounded-xl border border-[#E2E8F0] bg-white px-3.5 py-1.5 text-xs font-bold text-[#475569] transition hover:bg-[#F8FAF9] disabled:opacity-50"
                                                    @click="reorderSingleItem(item.id)"
                                                >
                                                    <span><Icon name="undo" class="h-4 w-4" /></span>
                                                    <span>{{ reorderingItemId === item.id ? 'Adding...' : 'Buy Again' }}</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                            </div>
                        </section>

                        <!-- FULFILLMENT ACTIVITY TIMELINE (BUYER-19) -->
                        <section
                            v-if="order.status_histories?.length"
                            class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm sm:p-5"
                        >
                            <h3 class="text-sm font-extrabold text-[#1F2937]">
                                Fulfillment History & Activity Log
                            </h3>

                            <div class="mt-4 space-y-3">
                                <div
                                    v-for="(history, i) in order.status_histories"
                                    :key="i"
                                    class="flex items-start gap-3 text-xs"
                                >
                                    <div class="mt-1 h-2 w-2 rounded-full bg-[#087F8C]"></div>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-bold text-[#1F2937]">
                                            {{ history.note || `Status changed to ${history.status}` }}
                                        </p>
                                        <p class="text-[10px] text-[#94A3B8]">
                                            {{ formatDate(history.created_at) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <!-- RIGHT: ORDER SUMMARY & SHIPPING DETAILS -->
                    <div class="space-y-4">
                        <!-- SUMMARY -->
                        <section class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm sm:p-5">
                            <h3 class="text-sm font-extrabold text-[#1F2937]">Order Summary</h3>

                            <div class="mt-4 space-y-2 text-xs">
                                <div class="flex justify-between text-[#64748B]">
                                    <span>Subtotal</span>
                                    <span class="font-bold text-[#1F2937]">{{ money(order.subtotal) }}</span>
                                </div>

                                <div v-if="order.discount > 0" class="flex justify-between text-[#22A06B]">
                                    <span>Discount {{ order.voucher_code ? `(${order.voucher_code})` : '' }}</span>
                                    <span class="font-bold">-{{ money(order.discount) }}</span>
                                </div>

                                <div class="flex justify-between text-[#64748B]">
                                    <span>Shipping ({{ order.shipping_method || 'Standard' }})</span>
                                    <span class="font-bold text-[#1F2937]">
                                        {{ Number(order.shipping_fee) === 0 ? 'Free' : money(order.shipping_fee) }}
                                    </span>
                                </div>

                                <div class="border-t border-[#EEF1F2] pt-2.5 flex justify-between text-sm font-extrabold text-[#1F2937]">
                                    <span>Total</span>
                                    <span class="text-[#087F8C]">{{ money(order.total) }}</span>
                                </div>
                            </div>
                        </section>

                        <!-- SHIPPING & PAYMENT DETAILS -->
                        <section class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm sm:p-5">
                            <h3 class="text-sm font-extrabold text-[#1F2937]">Delivery & Payment</h3>

                            <div class="mt-4 space-y-3.5 text-xs">
                                <div>
                                    <p class="font-bold uppercase tracking-wider text-[#94A3B8] text-[10px]">
                                        Delivery Address
                                    </p>
                                    <p class="mt-1 text-[#334155] leading-relaxed">
                                        {{ order.shipping_address || 'No shipping address provided.' }}
                                    </p>
                                </div>

                                <div>
                                    <p class="font-bold uppercase tracking-wider text-[#94A3B8] text-[10px]">
                                        Payment Method
                                    </p>
                                    <p class="mt-1 text-[#334155] font-semibold">
                                        {{ formatPaymentMethod(order.payment_method) }}
                                    </p>
                                    <p v-if="order.payment_status" class="mt-0.5 text-[11px] text-[#64748B] capitalize">
                                        Status: {{ order.payment_status }}
                                    </p>

                                    <!-- AWAITING PAYMENT ALERT (BUY-PAY-003, BUY-PAY-006) -->
                                    <div
                                        v-if="order.payment_status === 'unpaid' && order.payment_method !== 'cod' && order.status !== 'cancelled'"
                                        class="mt-3 rounded-xl border border-[#FDE68A] bg-[#FFFBEB] p-3 text-xs text-[#92400E]"
                                    >
                                        <div class="flex items-center gap-1.5 font-bold">
                                            <span><Icon name="clock" class="h-4 w-4" /></span>
                                            <span>Awaiting Payment</span>
                                        </div>
                                        <p class="mt-1 text-[11px] leading-relaxed">
                                            Please complete payment within 1 hour. Unpaid orders auto-cancel after 1 hour and stock is returned.
                                        </p>
                                        <button
                                            type="button"
                                            class="mt-2.5 inline-flex w-full items-center justify-center rounded-lg bg-[#087F8C] px-3 py-2 text-xs font-bold text-white transition hover:bg-[#066B76]"
                                            @click="openPayModal"
                                        >
                                            Pay Now / Pay Again
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>

        <!-- PAY ORDER MODAL (BUY-PAY-002, BUY-PAY-003, BUY-PAY-004) -->
        <div
            v-if="showPayModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#0F172A]/50 p-4 backdrop-blur-sm"
            @click.self="closePayModal"
        >
            <div class="w-full max-w-md rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-2xl sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-extrabold text-[#1F2937]">Pay for Order</h3>
                        <p class="mt-1 text-xs text-[#64748B]">
                            Complete payment for Order #{{ order.order_number }} (Total: {{ money(order.total) }}).
                        </p>
                    </div>
                    <button type="button" class="text-xl text-[#94A3B8] hover:text-[#475569]" @click="closePayModal">✕</button>
                </div>

                <div class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-[#475569]">Select Payment Method</label>
                        <div class="mt-2 space-y-2">
                            <label
                                class="flex items-center gap-3 rounded-xl border p-3 cursor-pointer transition"
                                :class="selectedPayMethod === 'gcash' ? 'border-[#087F8C] bg-[#E8F7F6]' : 'border-[#E5E7EB] bg-[#F8FAF9]'"
                            >
                                <input type="radio" v-model="selectedPayMethod" value="gcash" class="text-[#087F8C] focus:ring-[#087F8C]" />
                                <span class="text-xs font-bold text-[#1F2937]">GCash</span>
                            </label>

                            <label
                                class="flex items-center gap-3 rounded-xl border p-3 cursor-pointer transition"
                                :class="selectedPayMethod === 'maya' ? 'border-[#087F8C] bg-[#E8F7F6]' : 'border-[#E5E7EB] bg-[#F8FAF9]'"
                            >
                                <input type="radio" v-model="selectedPayMethod" value="maya" class="text-[#087F8C] focus:ring-[#087F8C]" />
                                <span class="text-xs font-bold text-[#1F2937]">Maya</span>
                            </label>

                            <label
                                class="flex items-center gap-3 rounded-xl border p-3 cursor-pointer transition"
                                :class="selectedPayMethod === 'card' ? 'border-[#087F8C] bg-[#E8F7F6]' : 'border-[#E5E7EB] bg-[#F8FAF9]'"
                            >
                                <input type="radio" v-model="selectedPayMethod" value="card" class="text-[#087F8C] focus:ring-[#087F8C]" />
                                <span class="text-xs font-bold text-[#1F2937]">Credit / Debit Card</span>
                            </label>
                        </div>
                    </div>

                    <div class="space-y-2 pt-2">
                        <button
                            type="button"
                            :disabled="isPaying"
                            class="w-full rounded-xl bg-[#087F8C] px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#066B76] disabled:opacity-50"
                            @click="submitPayment('success')"
                        >
                            {{ isPaying ? 'Processing...' : `Pay ${money(order.total)} Now` }}
                        </button>

                        <div class="flex gap-2">
                            <button
                                type="button"
                                :disabled="isPaying"
                                class="flex-1 rounded-xl border border-[#FCA5A5] bg-[#FEF2F2] px-3 py-2 text-[11px] font-bold text-[#DC2626] hover:bg-[#FEE2E2] disabled:opacity-50"
                                @click="submitPayment('fail')"
                            >
                                Simulate Failure
                            </button>
                            <button
                                type="button"
                                :disabled="isPaying"
                                class="flex-1 rounded-xl border border-[#E5E7EB] px-3 py-2 text-[11px] font-bold text-[#64748B] hover:bg-[#F8FAF9] disabled:opacity-50"
                                @click="submitPayment('cancel')"
                            >
                                Cancel at Gateway
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CANCEL ORDER MODAL (BUYER-18) -->
        <div
            v-if="showCancelModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#0F172A]/50 p-4 backdrop-blur-sm"
            @click.self="closeCancelModal"
        >
            <div class="w-full max-w-md rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-2xl sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-extrabold text-[#1F2937]">Cancel Order</h3>
                        <p class="mt-1 text-xs text-[#64748B]">
                            Are you sure you want to cancel this order? Items will be restored to inventory.
                        </p>
                    </div>
                    <button type="button" class="text-xl text-[#94A3B8] hover:text-[#475569]" @click="closeCancelModal">✕</button>
                </div>

                <form class="mt-4 space-y-4" @submit.prevent="submitCancelOrder">
                    <div>
                        <label class="block text-xs font-bold text-[#475569]">Reason for Cancellation</label>
                        <select
                            v-model="cancelForm.reason"
                            class="mt-1.5 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2 text-xs text-[#1F2937] focus:border-[#16A6A0] focus:ring-2 focus:ring-[#E8F7F6]"
                        >
                            <option v-for="opt in cancelReasonOptions" :key="opt" :value="opt">{{ opt }}</option>
                        </select>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button
                            type="button"
                            :disabled="cancelForm.processing"
                            class="flex-1 rounded-xl border border-[#E5E7EB] px-4 py-2.5 text-xs font-bold text-[#64748B] hover:bg-[#F8FAF9]"
                            @click="closeCancelModal"
                        >
                            Nevermind
                        </button>
                        <button
                            type="submit"
                            :disabled="cancelForm.processing"
                            class="flex-1 rounded-xl bg-[#DC2626] px-4 py-2.5 text-xs font-bold text-white hover:bg-[#B91C1C] disabled:opacity-50"
                        >
                            {{ cancelForm.processing ? 'Cancelling...' : 'Confirm Cancellation' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- RETURN / REFUND REQUEST MODAL (BUYER-21, 22, 23) -->
        <div
            v-if="showReturnModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#0F172A]/50 p-4 backdrop-blur-sm"
            @click.self="closeReturnModal"
        >
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-2xl sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-extrabold text-[#1F2937]">Request Return / Refund</h3>
                        <p class="mt-1 text-xs text-[#64748B]">
                            {{ returningItem?.product_name }}
                        </p>
                    </div>
                    <button type="button" class="text-xl text-[#94A3B8] hover:text-[#475569]" @click="closeReturnModal">✕</button>
                </div>

                <form class="mt-4 space-y-4" @submit.prevent="submitReturnRequest">
                    <!-- TYPE SELECTION (BUYER-21, 22) -->
                    <div>
                        <label class="block text-xs font-bold text-[#475569]">Request Type</label>
                        <div class="mt-1.5 grid grid-cols-2 gap-2">
                            <label
                                :class="[
                                    'flex cursor-pointer flex-col rounded-xl border p-3 text-xs transition',
                                    returnForm.type === 'return_and_refund'
                                        ? 'border-[#087F8C] bg-[#E8F7F6] text-[#087F8C] font-bold'
                                        : 'border-[#E5E7EB] bg-white text-[#64748B]'
                                ]"
                            >
                                <div class="flex items-center gap-2">
                                    <input v-model="returnForm.type" type="radio" value="return_and_refund" class="text-[#087F8C]" />
                                    <span>Return & Refund</span>
                                </div>
                                <span class="mt-1 text-[10px] text-[#64748B] font-normal">Return physical item to seller for refund.</span>
                            </label>

                            <label
                                :class="[
                                    'flex cursor-pointer flex-col rounded-xl border p-3 text-xs transition',
                                    returnForm.type === 'refund_only'
                                        ? 'border-[#087F8C] bg-[#E8F7F6] text-[#087F8C] font-bold'
                                        : 'border-[#E5E7EB] bg-white text-[#64748B]'
                                ]"
                            >
                                <div class="flex items-center gap-2">
                                    <input v-model="returnForm.type" type="radio" value="refund_only" class="text-[#087F8C]" />
                                    <span>Refund Only</span>
                                </div>
                                <span class="mt-1 text-[10px] text-[#64748B] font-normal">Missing / damaged item without return.</span>
                            </label>
                        </div>
                    </div>

                    <!-- REASON (BUYER-21) -->
                    <div>
                        <label class="block text-xs font-bold text-[#475569]">Reason for Return</label>
                        <select
                            v-model="returnForm.reason"
                            class="mt-1.5 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2 text-xs text-[#1F2937] focus:border-[#16A6A0] focus:ring-2 focus:ring-[#E8F7F6]"
                        >
                            <option v-for="r in returnReasons" :key="r.value" :value="r.label">{{ r.label }}</option>
                        </select>
                    </div>

                    <!-- REASON DETAILS -->
                    <div>
                        <label class="block text-xs font-bold text-[#475569]">Description & Details (Min 10 characters) *</label>
                        <textarea
                            v-model="returnForm.reason_details"
                            rows="3"
                            placeholder="Please describe the issue in detail (at least 10 characters)..."
                            class="mt-1.5 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-3 text-xs text-[#1F2937] placeholder:text-[#94A3B8] focus:border-[#16A6A0] focus:ring-2 focus:ring-[#E8F7F6]"
                        ></textarea>
                        <p v-if="returnForm.errors.reason_details" class="mt-1 text-xs text-[#DC2626]">
                            {{ returnForm.errors.reason_details }}
                        </p>
                    </div>

                    <!-- EVIDENCE UPLOADS (BUYER-23) -->
                    <div>
                        <label class="block text-xs font-bold text-[#475569]">Upload Evidence Photos (1 to 5 photos required) *</label>
                        <input
                            type="file"
                            multiple
                            accept="image/*"
                            class="mt-1.5 w-full text-xs text-[#64748B] file:mr-3 file:rounded-xl file:border-0 file:bg-[#E8F7F6] file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-[#087F8C]"
                            @change="handleFileChange"
                        />
                        <p v-if="returnForm.errors.evidence_images" class="mt-1 text-xs text-[#DC2626]">
                            {{ returnForm.errors.evidence_images }}
                        </p>

                        <!-- PREVIEWS -->
                        <div v-if="evidencePreviews.length" class="mt-2.5 flex flex-wrap gap-2">
                            <div
                                v-for="(preview, idx) in evidencePreviews"
                                :key="idx"
                                class="h-16 w-16 overflow-hidden rounded-xl border border-[#E5E7EB]"
                            >
                                <img :src="preview" class="h-full w-full object-cover" alt="Preview" />
                            </div>
                        </div>
                    </div>

                    <!-- REFUND AMOUNT -->
                    <div class="rounded-xl bg-[#F8FAF9] p-3 text-xs text-[#64748B]">
                        <div class="flex justify-between">
                            <span>Refund Amount:</span>
                            <span class="font-extrabold text-[#1F2937]">{{ money(getItemTotal(returningItem)) }}</span>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button
                            type="button"
                            :disabled="returnForm.processing"
                            class="flex-1 rounded-xl border border-[#E5E7EB] px-4 py-2.5 text-xs font-bold text-[#64748B] hover:bg-[#F8FAF9]"
                            @click="closeReturnModal"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="returnForm.processing"
                            class="flex-1 rounded-xl bg-[#087F8C] px-4 py-2.5 text-xs font-bold text-white hover:bg-[#066B76] disabled:opacity-50"
                        >
                            {{ returnForm.processing ? 'Submitting...' : 'Submit Request' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- REVIEW MODAL -->
        <div
            v-if="showReviewModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#0F172A]/50 p-4 backdrop-blur-sm"
            @click.self="closeReviewModal"
        >
            <div class="w-full max-w-md rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-2xl sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-extrabold text-[#1F2937]">
                            {{ editingReview ? 'Edit Your Review' : 'Write a Review' }}
                        </h3>
                        <p class="mt-1 text-xs text-[#64748B]">{{ reviewingItem?.product_name }}</p>
                    </div>
                    <button type="button" class="text-xl text-[#94A3B8] hover:text-[#475569]" @click="closeReviewModal">✕</button>
                </div>

                <form class="mt-4 space-y-4" @submit.prevent="submitReview">
                    <div>
                        <label class="block text-xs font-bold text-[#475569]">Rating</label>
                        <div class="mt-2 flex gap-1">
                            <button
                                v-for="star in 5"
                                :key="star"
                                type="button"
                                class="flex h-9 w-9 items-center justify-center rounded-xl text-2xl transition hover:bg-[#FFF9EC]"
                                :class="star <= reviewForm.rating ? 'text-[#F4B942]' : 'text-[#D7DDE0]'"
                                @click="reviewForm.rating = star"
                            >
                                ★
                            </button>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-[#475569]">Review Details</label>
                            <span class="text-[11px] text-[#94A3B8]">{{ (reviewForm.comment || '').length }}/500</span>
                        </div>
                        <textarea
                            v-model="reviewForm.comment"
                            rows="3"
                            maxlength="500"
                            placeholder="Share your experience with this product..."
                            class="mt-1.5 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-3 text-xs text-[#1F2937] placeholder:text-[#94A3B8] focus:border-[#16A6A0] focus:ring-2 focus:ring-[#E8F7F6]"
                        ></textarea>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button
                            type="button"
                            :disabled="reviewForm.processing"
                            class="flex-1 rounded-xl border border-[#E5E7EB] px-4 py-2.5 text-xs font-bold text-[#64748B] hover:bg-[#F8FAF9]"
                            @click="closeReviewModal"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="reviewForm.processing"
                            class="flex-1 rounded-xl bg-[#087F8C] px-4 py-2.5 text-xs font-bold text-white hover:bg-[#066B76] disabled:opacity-50"
                        >
                            {{ reviewForm.processing ? 'Saving...' : (editingReview ? 'Update Review' : 'Submit Review') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </BuyerAccountLayout>
</template>
