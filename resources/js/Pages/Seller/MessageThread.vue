<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'

const props = defineProps({
    conversation: { type: Object, required: true },
    messages: { type: Array, default: () => [] },
    sellerOrders: { type: Array, default: () => [] },
})

const isSupportChat = computed(() => props.conversation.type === 'seller_admin')

const form = useForm({
    body: '',
    attachment: null,
    order_id: '',
})

const fileInput = ref(null)
const attachmentPreview = ref(null)
const showOrderSelector = ref(false)
const lightboxImage = ref(null)

const isMine = (message) => message.sender_id === props.conversation.seller_id

const formatTime = (value) => {
    if (!value) return ''
    const d = new Date(value)
    return isNaN(d.getTime()) ? '' : d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
}

const formatDate = (value) => {
    if (!value) return ''
    const d = new Date(value)
    return isNaN(d.getTime()) ? '' : d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

const formatFileSize = (bytes) => {
    if (!bytes) return ''
    if (bytes < 1024) return bytes + ' B'
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
    return (bytes / 1048576).toFixed(1) + ' MB'
}

const isImageFile = (message) => {
    if (message.attachment_type === 'image') return true
    const ext = (message.attachment_name || message.attachment_path || '').split('.').pop().toLowerCase()
    return ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext)
}

const handleFileSelect = (e) => {
    const file = e.target.files[0]
    if (!file) return

    form.attachment = file
    if (file.type.startsWith('image/')) {
        const reader = new FileReader()
        reader.onload = (event) => {
            attachmentPreview.value = event.target.result
        }
        reader.readAsDataURL(file)
    } else {
        attachmentPreview.value = null
    }
}

const removeAttachment = () => {
    form.attachment = null
    attachmentPreview.value = null
    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

const selectOrder = (order) => {
    form.order_id = order.id
    showOrderSelector.value = false
}

const clearSelectedOrder = () => {
    form.order_id = ''
}

const getSelectedOrderObject = computed(() => {
    if (!form.order_id) return null
    return props.sellerOrders.find(o => o.id === form.order_id)
})

const submit = () => {
    if (!form.body.trim() && !form.attachment && !form.order_id) return

    form.post(route('seller.messages.store', props.conversation.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('body', 'order_id')
            removeAttachment()
        },
    })
}

const getStatusBadgeClass = (status) => {
    const s = (status || '').toLowerCase()
    if (['completed', 'delivered'].includes(s)) return 'bg-emerald-50 text-emerald-700 border-emerald-200'
    if (['shipped', 'out_for_delivery'].includes(s)) return 'bg-sky-50 text-sky-700 border-sky-200'
    if (['processing', 'packed'].includes(s)) return 'bg-amber-50 text-amber-700 border-amber-200'
    if (['cancelled', 'rejected'].includes(s)) return 'bg-rose-50 text-rose-700 border-rose-200'
    return 'bg-slate-100 text-slate-700 border-slate-200'
}
</script>

<template>
    <Head :title="isSupportChat ? `Support Ticket - ${conversation.subject || 'Admin Desk'}` : `Message - ${conversation.buyer?.name || 'Customer'}`" />

    <SellerLayout>
        <div class="min-h-[calc(100vh-68px)] bg-[#F8FAF9] pb-20">
            <div class="mx-auto max-w-5xl px-4 py-5 sm:px-6 lg:px-8">

                <!-- Breadcrumb / Back Link -->
                <div class="mb-3">
                    <Link
                        :href="route('seller.messages')"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#64748B] hover:text-[#087F8C]"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back to All Conversations
                    </Link>
                </div>

                <!-- Conversation Section -->
                <section class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm">

                    <!-- Conversation Header (Customer vs Admin Support Desk) -->
                    <div class="border-b border-[#E5E7EB] bg-white px-5 py-4">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <!-- Avatar / Icon -->
                                <div
                                    v-if="isSupportChat"
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#FEF3C7] text-[#D97706]"
                                >
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                </div>
                                <div
                                    v-else
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#E8F7F6] text-sm font-bold text-[#087F8C]"
                                >
                                    {{ (conversation.buyer?.name || 'C').charAt(0).toUpperCase() }}
                                </div>

                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h2 class="truncate text-base font-bold text-[#1F2937]">
                                            {{ isSupportChat ? (conversation.subject || 'Platform Support Desk') : (conversation.buyer?.name || 'Customer') }}
                                        </h2>
                                        <span
                                            v-if="isSupportChat"
                                            class="rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-800"
                                        >
                                            Official Admin Support
                                        </span>
                                        <span
                                            v-else
                                            class="rounded-full bg-teal-50 px-2 py-0.5 text-[10px] font-bold text-teal-700"
                                        >
                                            Customer
                                        </span>
                                    </div>

                                    <p class="mt-0.5 text-xs text-[#64748B]">
                                        {{ isSupportChat ? 'Direct channel with Shopalona platform administrators' : (conversation.buyer?.email || 'Customer conversation') }}
                                    </p>
                                </div>
                            </div>

                            <div v-if="conversation.order" class="hidden sm:block text-right">
                                <span class="rounded-lg bg-[#F8FAF9] border border-[#E5E7EB] px-3 py-1.5 text-xs font-semibold text-[#475569]">
                                    Order #{{ conversation.order.order_number }}
                                </span>
                            </div>
                        </div>

                        <!-- Pinned Conversation Order Reference (SELLER-43) -->
                        <div
                            v-if="conversation.order"
                            class="mt-3.5 flex flex-col gap-3 rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-3.5 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-white border border-[#E5E7EB] text-[#087F8C]">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <p class="text-xs font-bold text-[#1F2937]">Order #{{ conversation.order.order_number }}</p>
                                        <span :class="['rounded px-2 py-0.5 text-[10px] font-bold border uppercase tracking-wider', getStatusBadgeClass(conversation.order.status)]">
                                            {{ conversation.order.status }}
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-[#64748B]">
                                        Total: <strong class="text-[#1F2937]">₱{{ Number(conversation.order.total).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}</strong>
                                        <span v-if="conversation.order.items?.length" class="ml-2">
                                            ({{ conversation.order.items.length }} {{ conversation.order.items.length === 1 ? 'item' : 'items' }})
                                        </span>
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <a
                                    :href="`/seller/orders?search=${conversation.order.order_number}`"
                                    class="inline-flex items-center gap-1 text-xs font-bold text-[#087F8C] hover:underline"
                                >
                                    Manage Order
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Messages Timeline Area -->
                    <div class="min-h-[360px] max-h-[600px] overflow-y-auto space-y-4 bg-[#F8FAF9] p-4 sm:p-5">
                        <!-- Empty Chat State -->
                        <div v-if="!props.messages.length" class="flex min-h-[300px] items-center justify-center text-center">
                            <div>
                                <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-white text-[#94A3B8] shadow-sm">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h8M8 14h5m7-2a8 8 0 01-8 8 8.4 8.4 0 01-3.1-.6L4 21l1.6-3.8A8 8 0 1120 12z" />
                                    </svg>
                                </div>
                                <p class="mt-3 text-sm font-semibold text-[#64748B]">No messages yet.</p>
                                <p class="mt-1 text-[11px] text-[#94A3B8]">Send a message to start the conversation.</p>
                            </div>
                        </div>

                        <!-- Message List -->
                        <div
                            v-for="msg in props.messages"
                            :key="msg.id"
                            :class="['flex w-full', isMine(msg) ? 'justify-end' : 'justify-start']"
                        >
                            <div class="max-w-[88%] sm:max-w-[70%] space-y-1.5">
                                <!-- Sender Label if not mine -->
                                <div v-if="!isMine(msg)" class="flex items-center gap-1.5 px-1">
                                    <span class="text-[11px] font-bold text-[#475569]">
                                        {{ msg.sender?.name || (isSupportChat ? 'Admin Support' : 'Customer') }}
                                    </span>
                                    <span
                                        v-if="msg.sender?.usertype === 'admin'"
                                        class="rounded bg-amber-100 px-1.5 py-0.2 text-[9px] font-bold text-amber-800 uppercase"
                                    >
                                        Admin
                                    </span>
                                </div>

                                <!-- Message Bubble -->
                                <div
                                    :class="[
                                        'rounded-2xl p-4 text-sm leading-relaxed shadow-sm space-y-2.5',
                                        isMine(msg)
                                            ? 'rounded-br-sm bg-[#087F8C] text-white'
                                            : 'rounded-bl-sm border border-[#E5E7EB] bg-white text-[#1F2937]'
                                    ]"
                                >
                                    <!-- Embedded Order Snippet inside Message (SELLER-43) -->
                                    <div
                                        v-if="msg.order"
                                        :class="[
                                            'rounded-xl border p-3 text-xs',
                                            isMine(msg)
                                                ? 'border-white/20 bg-white/10 text-white'
                                                : 'border-[#E5E7EB] bg-[#F8FAF9] text-[#1F2937]'
                                        ]"
                                    >
                                        <div class="flex items-center justify-between gap-2 border-b pb-2 mb-2" :class="isMine(msg) ? 'border-white/15' : 'border-[#E5E7EB]'">
                                            <div class="flex items-center gap-1.5">
                                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                                </svg>
                                                <span class="font-bold">Order #{{ msg.order.order_number }}</span>
                                            </div>
                                            <span :class="['rounded px-1.5 py-0.5 text-[9px] font-bold border uppercase', getStatusBadgeClass(msg.order.status)]">
                                                {{ msg.order.status }}
                                            </span>
                                        </div>

                                        <!-- Items summary -->
                                        <div v-if="msg.order.items?.length" class="space-y-1.5">
                                            <div
                                                v-for="item in msg.order.items.slice(0, 2)"
                                                :key="item.id"
                                                class="flex items-center gap-2"
                                            >
                                                <img
                                                    v-if="item.product?.image_path"
                                                    :src="item.product.image_path.startsWith('http') ? item.product.image_path : `/storage/${item.product.image_path}`"
                                                    :alt="item.product_name"
                                                    class="h-8 w-8 rounded object-cover shrink-0 bg-white"
                                                />
                                                <div class="min-w-0 flex-1">
                                                    <p class="truncate text-[11px] font-medium">{{ item.product_name }}</p>
                                                    <p class="text-[10px] opacity-75">Qty: {{ item.quantity }} × ₱{{ Number(item.price).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}</p>
                                                </div>
                                            </div>
                                            <p v-if="msg.order.items.length > 2" class="text-[10px] italic opacity-80">
                                                + {{ msg.order.items.length - 2 }} more items
                                            </p>
                                        </div>

                                        <div class="mt-2.5 pt-2 border-t flex items-center justify-between" :class="isMine(msg) ? 'border-white/15' : 'border-[#E5E7EB]'">
                                            <span class="text-[11px] font-semibold">Total: ₱{{ Number(msg.order.total).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}</span>
                                            <a
                                                :href="`/seller/orders?search=${msg.order.order_number}`"
                                                class="text-[11px] font-bold underline hover:opacity-80"
                                            >
                                                View Order
                                            </a>
                                        </div>
                                    </div>

                                    <!-- Media Attachment (SELLER-42) -->
                                    <div v-if="msg.attachment_path" class="pt-1">
                                        <!-- Image Attachment with Lightbox -->
                                        <div
                                            v-if="isImageFile(msg)"
                                            class="group relative overflow-hidden rounded-xl border max-w-sm cursor-pointer"
                                            :class="isMine(msg) ? 'border-white/20' : 'border-[#E5E7EB]'"
                                            @click="lightboxImage = msg.attachment_url"
                                        >
                                            <img
                                                :src="msg.attachment_url"
                                                :alt="msg.attachment_name || 'Attachment'"
                                                class="max-h-64 w-full object-cover transition duration-200 group-hover:scale-105"
                                            />
                                            <div class="absolute inset-0 bg-black/30 opacity-0 transition group-hover:opacity-100 flex items-center justify-center text-white">
                                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                                                </svg>
                                            </div>
                                        </div>

                                        <!-- Document/File Attachment -->
                                        <div
                                            v-else
                                            class="flex items-center gap-3 rounded-xl border p-2.5 text-xs max-w-sm"
                                            :class="isMine(msg) ? 'border-white/20 bg-white/10' : 'border-[#CBD5E1] bg-slate-50 text-[#1F2937]'"
                                        >
                                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-[#087F8C]">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate font-semibold">{{ msg.attachment_name || 'Attached Document' }}</p>
                                                <p class="text-[10px] opacity-75">{{ formatFileSize(msg.attachment_size) }}</p>
                                            </div>
                                            <a
                                                :href="msg.attachment_url"
                                                target="_blank"
                                                download
                                                class="rounded-lg p-1.5 transition hover:bg-white/20"
                                                title="Download file"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                </svg>
                                            </a>
                                        </div>
                                    </div>

                                    <!-- Text Body -->
                                    <p v-if="msg.body" class="whitespace-pre-wrap break-words">
                                        {{ msg.body }}
                                    </p>
                                </div>

                                <!-- Timestamp -->
                                <p
                                    :class="['px-1 text-[9px] text-[#94A3B8]', isMine(msg) ? 'text-right' : 'text-left']"
                                >
                                    {{ formatTime(msg.created_at) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Selected Attachment & Order Reference Chips (Above Composer) -->
                    <div
                        v-if="form.attachment || form.order_id"
                        class="flex flex-wrap items-center gap-2 border-t border-[#E5E7EB] bg-slate-50 px-4 py-2.5 text-xs"
                    >
                        <!-- Order Chip -->
                        <div
                            v-if="getSelectedOrderObject"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-teal-200 bg-teal-50 px-2.5 py-1 text-teal-800 font-semibold"
                        >
                            <svg class="h-3.5 w-3.5 text-[#087F8C]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                            Order #{{ getSelectedOrderObject.order_number }}
                            <button type="button" @click="clearSelectedOrder" class="text-teal-600 hover:text-rose-600">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- File Chip -->
                        <div
                            v-if="form.attachment"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-slate-700 font-medium"
                        >
                            <svg class="h-3.5 w-3.5 text-[#087F8C]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                            </svg>
                            <span class="max-w-[150px] truncate">{{ form.attachment.name }}</span>
                            <button type="button" @click="removeAttachment" class="text-slate-400 hover:text-rose-600">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Message Composer Form -->
                    <form class="border-t border-[#E5E7EB] bg-white p-4" @submit.prevent="submit">
                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/jpeg,image/png"
                            @change="handleFileSelect"
                            class="hidden"
                        />

                        <div class="relative">
                            <textarea
                                id="message"
                                v-model="form.body"
                                rows="3"
                                maxlength="1000"
                                :placeholder="isSupportChat ? 'Write your message to Shopalona Admin Support...' : 'Type a message to your customer...'"
                                class="w-full resize-none rounded-xl border border-[#CBD5E1] bg-[#F8FAF9] p-3 text-sm text-[#1F2937] placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:outline-none focus:ring-1 focus:ring-[#087F8C]"
                                @keydown.enter.exact.prevent="submit"
                            ></textarea>

                            <!-- Quick attachment action buttons bar inside composer -->
                            <div class="mt-2 flex items-center justify-between gap-2">
                                <div class="flex items-center gap-1.5">
                                    <!-- Attach Media Button (SELLER-42, SEL-REV-005, SEL-REV-006 / A16: JPG or PNG only, max 5MB) -->
                                    <button
                                        type="button"
                                        @click="$refs.fileInput.click()"
                                        title="Attach image (JPG, PNG max 5MB)"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-[#CBD5E1] bg-white px-2.5 py-1.5 text-xs font-semibold text-[#64748B] hover:border-[#087F8C] hover:text-[#087F8C]"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                        </svg>
                                        Attach Image
                                    </button>

                                    <!-- Share Order Snippet Button (SELLER-43) -->
                                    <div class="relative">
                                        <button
                                            type="button"
                                            @click="showOrderSelector = !showOrderSelector"
                                            title="Embed order reference snippet"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-[#CBD5E1] bg-white px-2.5 py-1.5 text-xs font-semibold text-[#64748B] hover:border-[#087F8C] hover:text-[#087F8C]"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                            </svg>
                                            Attach Order
                                        </button>

                                        <!-- Order Picker Popover -->
                                        <div
                                            v-if="showOrderSelector"
                                            class="absolute bottom-full left-0 mb-2 w-72 max-h-60 overflow-y-auto rounded-xl border border-[#CBD5E1] bg-white p-2 shadow-lg z-30 space-y-1"
                                        >
                                            <p class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-[#94A3B8]">Select an Order to Share</p>
                                            <div v-if="!sellerOrders.length" class="p-3 text-center text-xs text-[#94A3B8]">
                                                No recent orders found.
                                            </div>
                                            <button
                                                v-for="ord in sellerOrders"
                                                :key="ord.id"
                                                type="button"
                                                @click="selectOrder(ord)"
                                                class="w-full rounded-lg px-2 py-1.5 text-left text-xs hover:bg-[#F8FAF9] flex items-center justify-between"
                                            >
                                                <span class="font-bold text-[#1F2937]">#{{ ord.order_number }}</span>
                                                <span class="text-[#64748B]">₱{{ Number(ord.total).toFixed(2) }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Send Message Button -->
                                <button
                                    type="submit"
                                    :disabled="form.processing || (!form.body.trim() && !form.attachment && !form.order_id)"
                                    class="inline-flex items-center gap-2 rounded-xl bg-[#087F8C] px-5 py-2 text-xs font-bold text-white transition hover:bg-[#066C76] disabled:opacity-50"
                                >
                                    <svg v-if="!form.processing" class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                    </svg>
                                    <svg v-else class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                    {{ form.processing ? 'Sending...' : 'Send Message' }}
                                </button>
                            </div>
                        </div>
                    </form>
                </section>
            </div>
        </div>

        <!-- Lightbox Modal for Media Attachments (SELLER-42) -->
        <div
            v-if="lightboxImage"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm"
            @click="lightboxImage = null"
        >
            <div class="relative max-h-[90vh] max-w-[90vw]" @click.stop>
                <img
                    :src="lightboxImage"
                    alt="Enlarged Attachment"
                    class="max-h-[85vh] max-w-[85vw] rounded-xl object-contain shadow-2xl"
                />
                <button
                    type="button"
                    @click="lightboxImage = null"
                    class="absolute -top-3 -right-3 flex h-8 w-8 items-center justify-center rounded-full bg-white text-slate-800 shadow-lg hover:bg-slate-100"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </SellerLayout>
</template>