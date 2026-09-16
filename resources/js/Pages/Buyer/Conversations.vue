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
})

const messageArea = ref(null)
const messageInput = ref(null)

const form = useForm({
    body: '',
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

    if (!body || form.processing) return

    form.post(
        route(
            'buyer.conversations.store',
            props.selectedConversation.id
        ),
        {
            preserveScroll: true,
            onSuccess: async () => {
                form.reset()
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
            <div class="mx-auto w-full min-w-0 max-w-[1600px] px-3 py-4 pb-10 sm:px-6 sm:py-6 sm:pb-12 lg:px-8 lg:py-7 lg:pb-14">

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
                                        <div class="max-w-[88%] sm:max-w-[75%]">
                                            <div
                                                class="rounded-2xl px-3.5 py-2.5 text-sm leading-5 shadow-sm"
                                                :class="
                                                    isMine(message)
                                                        ? 'rounded-br-md bg-[#087F8C] text-white'
                                                        : 'rounded-bl-md border border-[#E5E7EB] bg-white text-[#334155]'
                                                "
                                            >
                                                <p class="whitespace-pre-wrap break-words">
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

                            <!-- MESSAGE INPUT -->
                            <div class="shrink-0 border-t border-[#E5E7EB] bg-white p-3 sm:p-4">
                                <form
                                    class="mx-auto w-full max-w-4xl"
                                    @submit.prevent="sendMessage"
                                >
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                                        <textarea
                                            ref="messageInput"
                                            v-model="form.body"
                                            rows="2"
                                            maxlength="5000"
                                            placeholder="Write a message..."
                                            class="min-h-[72px] w-full min-w-0 resize-none rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2.5 text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#16A6A0] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                            :disabled="form.processing"
                                            @keydown="handleKeydown"
                                        ></textarea>

                                        <button
                                            type="submit"
                                            :disabled="form.processing || !form.body.trim()"
                                            class="inline-flex h-10 w-full shrink-0 items-center justify-center gap-2 rounded-xl bg-[#087F8C] px-4 text-sm font-bold text-white transition hover:bg-[#066C76] disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
                                        >
                                            <svg
                                                v-if="!form.processing"
                                                class="h-4 w-4"
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
                                                class="h-4 w-4 animate-spin"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                            >
                                                <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" />
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v3a5 5 0 0 0-5 5H4Z" />
                                            </svg>

                                            {{ form.processing ? 'Sending...' : 'Send' }}
                                        </button>
                                    </div>

                                    <p class="mt-1.5 hidden text-[9px] text-[#94A3B8] sm:block">
                                        Press Enter to send
                                    </p>

                                    <p v-if="form.errors.body" class="mt-1 text-xs text-[#E85D5D]">
                                        {{ form.errors.body }}
                                    </p>
                                </form>
                            </div>
                        </template>
                    </section>
                </div>
            </div>
        </main>
    </BuyerLayout>
</template>
