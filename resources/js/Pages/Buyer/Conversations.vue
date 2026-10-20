<script setup>
import {
    computed,
    nextTick,
    onMounted,
    ref,
    watch,
} from 'vue'

import { Head, Link, useForm } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'

const props = defineProps({
    conversations: {
        type: Array,
        default: () => [],
    },

    selectedConversation: {
        type: Object,
        default: () => null,
    },

    messages: {
        type: Array,
        default: () => [],
    },

    buyerOrders: {
        type: Array,
        default: () => [],
    },
})

const messageArea = ref(null)
const messageInput = ref(null)
const attachmentInput = ref(null)
const attachmentPreview = ref(null)
const showOrderPicker = ref(false)
const lightboxImage = ref(null)

const form = useForm({
    body: '',
    attachment: null,
    order_id: '',
})

const isImageFile = (msg) => {
    if (msg.attachment_type === 'image') return true
    const ext = (msg.attachment_name || msg.attachment_path || '').split('.').pop().toLowerCase()
    return ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext)
}

const formatFileSize = (bytes) => {
    if (!bytes) return ''
    if (bytes < 1024) return bytes + ' B'
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
    return (bytes / 1048576).toFixed(1) + ' MB'
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

const clearAttachment = () => {
    form.attachment = null
    attachmentPreview.value = null
    if (attachmentInput.value) {
        attachmentInput.value.value = ''
    }
}

const selectOrder = (order) => {
    form.order_id = order.id
    showOrderPicker.value = false
}

const getSelectedOrder = computed(() => {
    if (!form.order_id) return null
    return props.buyerOrders.find(o => o.id === form.order_id)
})

const selectedId = computed(() =>
    props.selectedConversation?.id
        ? Number(props.selectedConversation.id)
        : null
)

const sellerName = conversation => {
    return (
        conversation?.seller?.store_name ||
        conversation?.seller?.name ||
        'Seller'
    )
}

const sellerInitial = conversation => {
    return sellerName(conversation)
        .charAt(0)
        .toUpperCase()
}

const sellerImage = conversation => {
    return (
        conversation?.seller?.logo ||
        conversation?.seller?.avatar ||
        null
    )
}

const unreadCount = conversation => {
    return Number(conversation?.unread_count || 0)
}

const lastMessage = conversation => {
    return (
        conversation?.messages?.[0]?.body ||
        conversation?.last_message?.body ||
        'Start the conversation'
    )
}

const formatDate = value => {
    if (!value) return '—'

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) return '—'

    const now = new Date()

    if (date.toDateString() === now.toDateString()) {
        return date.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
        })
    }

    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year:
            date.getFullYear() !== now.getFullYear()
                ? 'numeric'
                : undefined,
    })
}

const formatTime = value => {
    if (!value) return ''

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) return ''

    return date.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
    })
}

const isMine = message => {
    return (
        Number(message.sender_id) ===
        Number(props.selectedConversation?.buyer_id)
    )
}

const selectConversation = conversation => {
    if (!conversation?.id) return

    window.location.href = route(
        'buyer.conversations.show',
        conversation.id
    )
}

const sendMessage = () => {
    if (!props.selectedConversation?.id) return

    const body = form.body.trim()
    if (!body && !form.attachment && !form.order_id) return
    if (form.processing) return

    form.post(
        route(
            'buyer.conversations.store',
            props.selectedConversation.id
        ),
        {
            preserveScroll: true,
            onSuccess: async () => {
                form.reset('body', 'order_id')
                clearAttachment()
                await nextTick()
                scrollToBottom()
            },
        }
    )
}

const handleKeydown = event => {
    if (event.key === 'Enter') {
        event.preventDefault()
        sendMessage()
    }
}

const scrollToBottom = () => {
    nextTick(() => {
        if (!messageArea.value) return

        messageArea.value.scrollTop =
            messageArea.value.scrollHeight
    })
}

watch(
    () => props.messages,
    () => scrollToBottom(),
    { deep: true }
)

onMounted(() => {
    scrollToBottom()
})
</script>

<template>
    <Head title="Messages" />

    <BuyerLayout>
        <main class="min-h-[calc(100vh-80px)] w-full min-w-0 overflow-x-hidden bg-[#F8FAF9]">
            <div class="mx-auto w-full min-w-0 max-w-[1536px] px-4 py-6 pb-10 sm:px-6 sm:py-6 sm:pb-12 lg:px-8 2xl:px-10 lg:py-7 lg:pb-14">

                <!-- PAGE HEADER -->
                <div class="mb-5 flex min-w-0 items-end justify-between gap-4 sm:mb-6">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#F4B942]"></span>
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#087F8C] sm:text-xs">
                                Alona Inbox
                            </p>
                        </div>

                        <h1 class="mt-1 truncate text-2xl font-extrabold tracking-tight text-[#1F2937] sm:text-3xl">
                            Messages
                        </h1>

                        <p class="mt-1 hidden text-sm text-[#64748B] sm:block">
                            Chat with sellers about products, orders, and your purchases.
                        </p>
                    </div>

                    <div
                        v-if="props.conversations.length"
                        class="shrink-0 rounded-full border border-[#D8EEEC] bg-[#E8F7F6] px-3 py-1.5 text-[10px] font-bold text-[#087F8C] sm:px-3.5 sm:text-xs"
                    >
                        {{ props.conversations.length }}
                        {{ props.conversations.length === 1 ? 'conversation' : 'conversations' }}
                    </div>
                </div>

                <!-- MESSAGING PANEL -->
                <div
                    class="grid w-full min-w-0 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-[0_8px_30px_rgba(15,23,42,0.06)] lg:h-[calc(100vh-250px)] lg:min-h-[560px] lg:grid-cols-[310px_minmax(0,1fr)] xl:grid-cols-[350px_minmax(0,1fr)]"
                >

                    <!-- CONVERSATION SIDEBAR -->
                    <aside
                        class="min-w-0 border-b border-[#E5E7EB] bg-white lg:flex lg:h-full lg:flex-col lg:border-b-0 lg:border-r"
                        :class="selectedId ? 'hidden lg:flex' : 'flex'"
                    >
                        <div class="flex shrink-0 items-center justify-between border-b border-[#EEF1F2] px-4 py-4 sm:px-5">
                            <div>
                                <h2 class="text-sm font-extrabold text-[#1F2937]">
                                    All Messages
                                </h2>

                                <p class="mt-0.5 text-[10px] text-[#94A3B8]">
                                    Keep in touch with your sellers
                                </p>
                            </div>

                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h6m8-1a8.5 8.5 0 0 1-8.5 8.5 8.5 8.5 0 0 1-4.17-1.09L4 20l1.59-3.33A8.5 8.5 0 1 1 21 11Z" />
                                </svg>
                            </div>
                        </div>

                        <div class="min-w-0 overflow-y-auto lg:flex-1">
                            <!-- EMPTY -->
                            <div v-if="!props.conversations.length" class="px-5 py-12 text-center">
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]">
                                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h6m8-1a8.5 8.5 0 0 1-8.5 8.5 8.5 8.5 0 0 1-4.17-1.09L4 20l1.59-3.33A8.5 8.5 0 1 1 21 11Z" />
                                    </svg>
                                </div>

                                <h3 class="mt-3 text-sm font-bold text-[#1F2937]">
                                    No conversations yet
                                </h3>

                                <p class="mt-1 text-xs leading-5 text-[#64748B]">
                                    Your conversations with sellers will appear here.
                                </p>
                            </div>

                            <!-- CONVERSATIONS -->
                            <button
                                v-for="conversation in props.conversations"
                                :key="conversation.id"
                                type="button"
                                class="group relative w-full min-w-0 border-b border-[#F0F2F3] px-3 py-3 text-left transition hover:bg-[#F7FBFA] sm:px-4"
                                :class="selectedId === Number(conversation.id) ? 'bg-[#E8F7F6]' : ''"
                                @click="selectConversation(conversation)"
                            >
                                <span
                                    v-if="selectedId === Number(conversation.id)"
                                    class="absolute inset-y-3 left-0 w-1 rounded-r-full bg-[#087F8C]"
                                ></span>

                                <div class="flex min-w-0 items-center gap-3">
                                    <!-- AVATAR -->
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#D9F1EF] text-xs font-extrabold text-[#087F8C] sm:h-11 sm:w-11">
                                        <img
                                            v-if="sellerImage(conversation)"
                                            :src="sellerImage(conversation)"
                                            :alt="sellerName(conversation)"
                                            class="h-full w-full object-cover"
                                        />

                                        <span v-else>
                                            {{ sellerInitial(conversation) }}
                                        </span>
                                    </div>

                                    <!-- INFO -->
                                    <div class="min-w-0 flex-1">
                                        <div class="flex min-w-0 items-center justify-between gap-2">
                                            <div class="flex min-w-0 items-center gap-1.5">
                                                <h3 class="truncate text-xs font-bold text-[#1F2937] sm:text-sm">
                                                    {{ sellerName(conversation) }}
                                                </h3>

                                                <span
                                                    v-if="unreadCount(conversation) > 0"
                                                    class="inline-flex min-w-4 shrink-0 items-center justify-center rounded-full bg-[#087F8C] px-1 text-[8px] font-bold text-white"
                                                >
                                                    {{ unreadCount(conversation) > 99 ? '99+' : unreadCount(conversation) }}
                                                </span>
                                            </div>

                                            <span class="shrink-0 whitespace-nowrap text-[9px] text-[#94A3B8]">
                                                {{ formatDate(conversation.last_message_at) }}
                                            </span>
                                        </div>

                                        <p
                                            class="mt-0.5 truncate text-[11px] text-[#64748B] sm:text-xs"
                                            :class="unreadCount(conversation) > 0 ? 'font-bold text-[#334155]' : ''"
                                        >
                                            {{ lastMessage(conversation) }}
                                        </p>

                                        <div
                                            v-if="conversation.product?.name || conversation.order?.id"
                                            class="mt-1.5 flex min-w-0 flex-wrap gap-1"
                                        >
                                            <span
                                                v-if="conversation.product?.name"
                                                class="max-w-[65%] truncate rounded-full bg-[#F1F5F9] px-1.5 py-0.5 text-[8px] font-semibold text-[#64748B]"
                                            >
                                                {{ conversation.product.name }}
                                            </span>

                                            <span
                                                v-if="conversation.order?.id"
                                                class="shrink-0 rounded-full bg-[#FFF7E5] px-1.5 py-0.5 text-[8px] font-bold text-[#9A6B08]"
                                            >
                                                Order #{{ conversation.order.id }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </button>
                        </div>
                    </aside>

                    <!-- MESSAGE AREA -->
                    <section
                        class="min-h-0 min-w-0 flex-col bg-[#F8FAF9]"
                        :class="selectedId ? 'flex' : 'hidden lg:flex'"
                    >
                        <!-- NO SELECTED CONVERSATION -->
                        <div
                            v-if="!selectedConversation"
                            class="flex min-h-[500px] flex-1 items-center justify-center px-6 py-12"
                        >
                            <div class="max-w-sm text-center">
                                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-[#E8F7F6] text-[#087F8C]">
                                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h6m8-1a8.5 8.5 0 0 1-8.5 8.5 8.5 8.5 0 0 1-4.17-1.09L4 20l1.59-3.33A8.5 8.5 0 1 1 21 11Z" />
                                    </svg>
                                </div>

                                <h2 class="mt-4 text-base font-extrabold text-[#1F2937]">
                                    Select a conversation
                                </h2>

                                <p class="mt-1 text-sm leading-6 text-[#64748B]">
                                    Choose a seller from the list to view your messages.
                                </p>
                            </div>
                        </div>

                        <!-- SELECTED CONVERSATION -->
                        <template v-else>
                            <!-- MESSAGE HEADER -->
                            <div class="flex min-w-0 shrink-0 items-center gap-2 border-b border-[#E5E7EB] bg-white px-3 py-3 sm:gap-3 sm:px-5">
                                <Link
                                    :href="route('buyer.conversations')"
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-[#64748B] hover:bg-[#E8F7F6] hover:text-[#087F8C] lg:hidden"
                                >
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" />
                                    </svg>
                                </Link>

                                <!-- SELLER AVATAR -->
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#D9F1EF] text-xs font-extrabold text-[#087F8C] sm:h-10 sm:w-10">
                                    <img
                                        v-if="sellerImage(selectedConversation)"
                                        :src="sellerImage(selectedConversation)"
                                        :alt="sellerName(selectedConversation)"
                                        class="h-full w-full object-cover"
                                    />

                                    <span v-else>
                                        {{ sellerInitial(selectedConversation) }}
                                    </span>
                                </div>

                                <!-- SELLER INFO -->
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <h2 class="truncate text-sm font-extrabold text-[#1F2937] sm:text-base">
                                            {{ sellerName(selectedConversation) }}
                                        </h2>
                                        <span class="hidden h-1.5 w-1.5 rounded-full bg-[#22A06B] sm:block"></span>
                                        <span class="hidden text-[10px] font-semibold text-[#64748B] sm:block">Seller</span>
                                    </div>

                                    <p class="truncate text-[10px] text-[#64748B] sm:text-xs">
                                        {{ selectedConversation.product?.name || 'Seller conversation' }}
                                    </p>
                                </div>

                                <div class="hidden shrink-0 text-right sm:block">
                                    <p class="text-[10px] font-semibold text-[#94A3B8]">
                                        {{ props.messages.length }}
                                        {{ props.messages.length === 1 ? 'message' : 'messages' }}
                                    </p>
                                </div>
                            </div>

                            <!-- MESSAGES -->
                            <div
                                ref="messageArea"
                                class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden px-3 py-4 sm:px-5 sm:py-5 lg:px-6"
                            >
                                <!-- EMPTY CHAT -->
                                <div
                                    v-if="!props.messages.length"
                                    class="flex h-full min-h-[300px] items-center justify-center"
                                >
                                    <div class="max-w-xs text-center">
                                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-white text-[#087F8C] shadow-sm ring-1 ring-[#E5E7EB]">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h6m8-1a8.5 8.5 0 0 1-8.5 8.5 8.5 8.5 0 0 1-4.17-1.09L4 20l1.59-3.33A8.5 8.5 0 1 1 21 11Z" />
                                            </svg>
                                        </div>

                                        <p class="mt-3 text-sm font-bold text-[#334155]">
                                            Start the conversation
                                        </p>

                                        <p class="mt-1 text-xs text-[#64748B]">
                                            Send a message to {{ sellerName(selectedConversation) }}.
                                        </p>
                                    </div>
                                </div>

                                <!-- MESSAGE LIST -->
                                <div
                                    v-else
                                    class="mx-auto flex w-full max-w-4xl flex-col gap-3"
                                >
                                    <div
                                        v-for="message in props.messages"
                                        :key="message.id"
                                        class="flex w-full"
                                        :class="isMine(message) ? 'justify-end' : 'justify-start'"
                                    >
                                        <div class="max-w-[88%] sm:max-w-[75%] space-y-1.5">
                                            <div
                                                class="rounded-2xl px-4 py-3 text-sm leading-5 shadow-sm space-y-2.5"
                                                :class="
                                                    isMine(message)
                                                        ? 'rounded-br-md bg-[#087F8C] text-white'
                                                        : 'rounded-bl-md border border-[#E5E7EB] bg-white text-[#334155]'
                                                "
                                            >
                                                <!-- Embedded Order Snippet (SELLER-43) -->
                                                <div
                                                    v-if="message.order"
                                                    :class="[
                                                        'rounded-xl border p-3 text-xs',
                                                        isMine(message)
                                                            ? 'border-white/20 bg-white/10 text-white'
                                                            : 'border-[#E5E7EB] bg-[#F8FAF9] text-[#1F2937]'
                                                    ]"
                                                >
                                                    <div class="flex items-center justify-between gap-2 border-b pb-2 mb-2" :class="isMine(message) ? 'border-white/15' : 'border-[#E5E7EB]'">
                                                        <div class="flex items-center gap-1.5">
                                                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                                            </svg>
                                                            <span class="font-bold">Order #{{ message.order.order_number }}</span>
                                                        </div>
                                                        <span class="rounded px-1.5 py-0.5 text-[9px] font-bold border uppercase" :class="isMine(message) ? 'bg-white/20 text-white border-white/30' : 'bg-slate-100 text-slate-700 border-slate-200'">
                                                            {{ message.order.status }}
                                                        </span>
                                                    </div>

                                                    <div v-if="message.order.items?.length" class="space-y-1.5">
                                                        <div
                                                            v-for="item in message.order.items.slice(0, 2)"
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
                                                    </div>

                                                    <div class="mt-2.5 pt-2 border-t flex items-center justify-between" :class="isMine(message) ? 'border-white/15' : 'border-[#E5E7EB]'">
                                                        <span class="text-[11px] font-semibold">Total: ₱{{ Number(message.order.total).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}</span>
                                                        <Link
                                                            :href="route('buyer.orders.show', message.order.id)"
                                                            class="text-[11px] font-bold underline hover:opacity-80"
                                                        >
                                                            View Details
                                                        </Link>
                                                    </div>
                                                </div>

                                                <!-- Media Attachment (SELLER-42) -->
                                                <div v-if="message.attachment_path" class="pt-1">
                                                    <!-- Image Thumbnail -->
                                                    <div
                                                        v-if="isImageFile(message)"
                                                        class="group relative overflow-hidden rounded-xl border max-w-sm cursor-pointer"
                                                        :class="isMine(message) ? 'border-white/20' : 'border-[#E5E7EB]'"
                                                        @click="lightboxImage = message.attachment_url"
                                                    >
                                                        <img
                                                            :src="message.attachment_url"
                                                            :alt="message.attachment_name || 'Attachment'"
                                                            class="max-h-64 w-full object-cover transition duration-200 group-hover:scale-105"
                                                        />
                                                        <div class="absolute inset-0 bg-black/30 opacity-0 transition group-hover:opacity-100 flex items-center justify-center text-white">
                                                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                                                            </svg>
                                                        </div>
                                                    </div>

                                                    <!-- Document Download Card -->
                                                    <div
                                                        v-else
                                                        class="flex items-center gap-3 rounded-xl border p-2.5 text-xs max-w-sm"
                                                        :class="isMine(message) ? 'border-white/20 bg-white/10' : 'border-[#CBD5E1] bg-slate-50 text-[#1F2937]'"
                                                    >
                                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-[#087F8C]">
                                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                            </svg>
                                                        </div>
                                                        <div class="min-w-0 flex-1">
                                                            <p class="truncate font-semibold">{{ message.attachment_name || 'Attached File' }}</p>
                                                            <p class="text-[10px] opacity-75">{{ formatFileSize(message.attachment_size) }}</p>
                                                        </div>
                                                        <a
                                                            :href="message.attachment_url"
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

                                                <!-- Text message -->
                                                <p v-if="message.body" class="whitespace-pre-wrap break-words">
                                                    {{ message.body }}
                                                </p>
                                            </div>

                                            <p
                                                class="mt-1 px-1 text-[9px] text-[#94A3B8]"
                                                :class="isMine(message) ? 'text-right' : 'text-left'"
                                            >
                                                {{ formatTime(message.created_at) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Chips Preview (Attachment & Order) -->
                            <div
                                v-if="form.attachment || form.order_id"
                                class="flex flex-wrap items-center gap-2 border-t border-[#E5E7EB] bg-slate-50 px-4 py-2 text-xs"
                            >
                                <div
                                    v-if="getSelectedOrder"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-teal-200 bg-teal-50 px-2.5 py-1 text-teal-800 font-semibold"
                                >
                                    <svg class="h-3.5 w-3.5 text-[#087F8C]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                    </svg>
                                    Order #{{ getSelectedOrder.order_number }}
                                    <button type="button" @click="form.order_id = ''" class="text-teal-600 hover:text-rose-600">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>

                                <div
                                    v-if="form.attachment"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-slate-700 font-medium"
                                >
                                    <svg class="h-3.5 w-3.5 text-[#087F8C]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                    </svg>
                                    <span class="max-w-[150px] truncate">{{ form.attachment.name }}</span>
                                    <button type="button" @click="clearAttachment" class="text-slate-400 hover:text-rose-600">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- MESSAGE INPUT -->
                            <div class="shrink-0 border-t border-[#E5E7EB] bg-white p-3 sm:p-4">
                                <input
                                    ref="attachmentInput"
                                    type="file"
                                    accept="image/jpeg,image/png"
                                    @change="handleFileSelect"
                                    class="hidden"
                                />

                                <form
                                    class="mx-auto w-full max-w-4xl"
                                    @submit.prevent="sendMessage"
                                >
                                    <div class="flex flex-col gap-2">
                                        <textarea
                                            ref="messageInput"
                                            v-model="form.body"
                                            rows="2"
                                            maxlength="1000"
                                            placeholder="Write a message to seller..."
                                            class="min-h-[64px] w-full min-w-0 resize-none rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2.5 text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#16A6A0] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                            :disabled="form.processing"
                                            @keydown="handleKeydown"
                                        ></textarea>

                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-1.5">
                                                <!-- Attach file -->
                                                <button
                                                    type="button"
                                                    @click="$refs.attachmentInput.click()"
                                                    title="Attach image or document"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-[#E5E7EB] bg-white px-2.5 py-1.5 text-xs font-semibold text-[#64748B] hover:border-[#087F8C] hover:text-[#087F8C]"
                                                >
                                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                                    </svg>
                                                    Attach
                                                </button>

                                                <!-- Attach order -->
                                                <div v-if="props.buyerOrders?.length" class="relative">
                                                    <button
                                                        type="button"
                                                        @click="showOrderPicker = !showOrderPicker"
                                                        title="Embed order reference"
                                                        class="inline-flex items-center gap-1 rounded-lg border border-[#E5E7EB] bg-white px-2.5 py-1.5 text-xs font-semibold text-[#64748B] hover:border-[#087F8C] hover:text-[#087F8C]"
                                                    >
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                                        </svg>
                                                        Share Order
                                                    </button>

                                                    <div
                                                        v-if="showOrderPicker"
                                                        class="absolute bottom-full left-0 mb-2 w-72 max-h-56 overflow-y-auto rounded-xl border border-[#CBD5E1] bg-white p-2 shadow-lg z-30 space-y-1"
                                                    >
                                                        <p class="px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-[#94A3B8]">Select Your Order</p>
                                                        <button
                                                            v-for="ord in props.buyerOrders"
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

                                            <button
                                                type="submit"
                                                :disabled="form.processing || (!form.body.trim() && !form.attachment && !form.order_id)"
                                                class="inline-flex h-9 items-center justify-center gap-2 rounded-xl bg-[#087F8C] px-4 text-xs font-bold text-white transition hover:bg-[#066C76] disabled:cursor-not-allowed disabled:opacity-50"
                                            >
                                                <svg
                                                    v-if="!form.processing"
                                                    class="h-3.5 w-3.5"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="1.8"
                                                >
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m22 2-7 20-4-9-9-4Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M22 2 11 13" />
                                                </svg>
                                                <svg
                                                    v-else
                                                    class="h-3.5 w-3.5 animate-spin"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                >
                                                    <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" />
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v3a5 5 0 0 0-5 5H4Z" />
                                                </svg>
                                                {{ form.processing ? 'Sending...' : 'Send' }}
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </template>
                    </section>
                </div>
            </div>
        </main>

        <!-- Lightbox Modal -->
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
    </BuyerLayout>
</template>
