<script setup>
import { ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    title: {
        type: String,
        default: 'Messages',
    },

    eyebrow: {
        type: String,
        default: 'Admin chat',
    },

    description: {
        type: String,
        default: 'View and respond to buyer and seller conversations from one place.',
    },

    active: {
        type: String,
        default: 'messages',
    },

    conversations: {
        type: Array,
        default: () => [],
    },

    conversation: {
        type: Object,
        default: null,
    },

    messages: {
        type: Array,
        default: () => [],
    },
})

const lightboxImage = ref(null)
const fileInput = ref(null)
const attachmentPreview = ref(null)

const form = useForm({
    body: '',
    attachment: null,
})

const formatDate = (value) => {
    if (!value) {
        return '—'
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return value
    }

    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    })
}

const isAdminMessage = (message) => {
    return (
        message.sender_id !== props.conversation?.buyer_id &&
        message.sender_id !== props.conversation?.seller_id
    )
}

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
    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

const submitMessage = () => {
    if (!props.conversation) return
    if (!form.body.trim() && !form.attachment) return

    form.post(
        route('admin.messages.store', props.conversation.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('body')
                clearAttachment()
            },
        }
    )
}
</script>

<template>
    <Head :title="title || 'Messages'" />

    <AdminLayout :active="active || 'messages'">
        <div class="w-full">

            <!-- PAGE HEADER -->
            <div class="pb-5">
                <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-[#087F8C]">
                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                    <span>{{ eyebrow }}</span>
                </div>

                <h1
                    class="mt-1 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl"
                >
                    {{ title }}
                </h1>

                <p
                    class="mt-0.5 text-xs text-slate-500"
                >
                    {{ description }}
                </p>
            </div>

            <!-- MAIN MESSAGES AREA -->
            <div
                class="grid gap-5 xl:grid-cols-[minmax(280px,0.82fr)_minmax(0,1.18fr)]"
            >

                <!-- ===================================================== -->
                <!-- CONVERSATIONS -->
                <!-- ===================================================== -->
                <section
                    class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                >

                    <!-- SECTION HEADER -->
                    <div
                        class="border-b border-[#E5E7EB] px-5 py-4"
                    >
                        <div
                            class="flex items-center justify-between gap-3"
                        >
                            <div class="flex min-w-0 items-center gap-3">

                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                                >
                                    <svg
                                        class="h-4.5 w-4.5"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                        stroke-width="1.8"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M8 10h8m-8 4h5m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                </div>

                                <div class="min-w-0">
                                    <h2
                                        class="text-base font-bold text-[#1F2937]"
                                    >
                                        Conversations
                                    </h2>

                                    <p
                                        class="mt-0.5 text-xs text-[#64748B]"
                                    >
                                        Buyer and seller conversations
                                    </p>
                                </div>
                            </div>

                            <span
                                class="inline-flex min-w-7 shrink-0 items-center justify-center rounded-full bg-[#E8F7F6] px-2 py-1 text-[10px] font-bold text-[#087F8C]"
                            >
                                {{ conversations.length }}
                            </span>
                        </div>
                    </div>

                    <!-- CONVERSATION LIST -->
                    <div
                        v-if="conversations.length"
                        class="divide-y divide-[#E5E7EB]"
                    >
                        <Link
                            v-for="item in conversations"
                            :key="item.id"
                            :href="
                                route(
                                    'admin.messages.show',
                                    item.id
                                )
                            "
                            preserve-scroll
                            class="relative block px-5 py-3.5 transition"
                            :class="
                                conversation?.id === item.id
                                    ? 'bg-[#E8F7F6]/70'
                                    : 'hover:bg-[#F8FAF9]'
                            "
                        >

                            <!-- SELECTED INDICATOR -->
                            <span
                                v-if="conversation?.id === item.id"
                                class="absolute bottom-0 left-0 top-0 w-1 bg-[#087F8C]"
                            ></span>

                            <div class="flex items-start gap-3">

                                <!-- AVATAR -->
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                                    :class="
                                        conversation?.id === item.id
                                            ? 'bg-[#087F8C] text-white'
                                            : 'bg-[#F1F5F9] text-[#64748B]'
                                    "
                                >
                                    <svg
                                        class="h-4.5 w-4.5"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                        stroke-width="1.8"
                                    >
                                        <circle
                                            cx="12"
                                            cy="8"
                                            r="3"
                                        />

                                        <path
                                            stroke-linecap="round"
                                            d="M6 20c.8-3.2 2.8-5 6-5s5.2 1.8 6 5"
                                        />
                                    </svg>
                                </div>

                                <div class="min-w-0 flex-1">

                                    <!-- NAMES + UNREAD -->
                                    <div
                                        class="flex items-start justify-between gap-2"
                                    >
                                        <div
                                            class="flex min-w-0 flex-1 items-center gap-1.5"
                                        >
                                            <p
                                                class="truncate text-xs font-semibold text-[#1F2937]"
                                            >
                                                {{
                                                    item.buyer?.name ??
                                                    'Buyer'
                                                }}
                                            </p>

                                            <span
                                                class="shrink-0 text-[10px] text-[#94A3B8]"
                                            >
                                                →
                                            </span>

                                            <p
                                                class="truncate text-xs font-semibold text-[#1F2937]"
                                            >
                                                {{
                                                    item.seller?.name ??
                                                    'Seller'
                                                }}
                                            </p>
                                        </div>

                                        <span
                                            v-if="item.unread_count"
                                            class="inline-flex min-w-5 shrink-0 items-center justify-center rounded-full bg-[#F4B942] px-1.5 py-0.5 text-[9px] font-bold text-[#1F2937]"
                                        >
                                            {{ item.unread_count }}
                                        </span>
                                    </div>

                                    <!-- LAST MESSAGE -->
                                    <p
                                        class="mt-1 truncate text-[11px] leading-5 text-[#64748B]"
                                    >
                                        {{
                                            item.messages?.[0]?.body ??
                                            'No messages yet'
                                        }}
                                    </p>

                                    <!-- DATE -->
                                    <p
                                        class="mt-1.5 text-[10px] text-[#94A3B8]"
                                    >
                                        {{
                                            formatDate(
                                                item.last_message_at
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </Link>
                    </div>

                    <!-- EMPTY STATE -->
                    <div
                        v-else
                        class="px-5 py-12 text-center"
                    >
                        <div
                            class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-[#F1F5F9] text-[#94A3B8]"
                        >
                            <svg
                                class="h-6 w-6"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                stroke-width="1.8"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M8 10h8m-8 4h5m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                        </div>

                        <p
                            class="mt-3 text-sm font-semibold text-[#1F2937]"
                        >
                            No conversations available
                        </p>

                        <p
                            class="mx-auto mt-1 max-w-xs text-[11px] leading-5 text-[#94A3B8]"
                        >
                            Buyer and seller conversations will appear here.
                        </p>
                    </div>
                </section>


                <!-- ===================================================== -->
                <!-- CONVERSATION THREAD -->
                <!-- ===================================================== -->
                <section
                    class="flex min-h-[600px] flex-col overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                >

                    <!-- THREAD HEADER -->
                    <div
                        class="border-b border-[#E5E7EB] px-5 py-4"
                    >
                        <div
                            class="flex items-center justify-between gap-3"
                        >

                            <div class="min-w-0">

                                <!-- ACTIVE CONVERSATION -->
                                <template v-if="conversation">
                                    <p
                                        class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#94A3B8]"
                                    >
                                        Conversation
                                    </p>

                                    <h2
                                        class="mt-1 truncate text-base font-bold text-[#1F2937]"
                                    >
                                        {{
                                            conversation.buyer?.name ??
                                            'Buyer'
                                        }}

                                        <span
                                            class="mx-1 font-normal text-[#94A3B8]"
                                        >
                                            →
                                        </span>

                                        {{
                                            conversation.seller?.name ??
                                            'Seller'
                                        }}
                                    </h2>
                                </template>

                                <!-- NO ACTIVE CONVERSATION -->
                                <template v-else>
                                    <div class="flex items-center gap-3">

                                        <div
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#F1F5F9] text-[#94A3B8]"
                                        >
                                            <svg
                                                class="h-4 w-4"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                                stroke-width="1.8"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M8 10h8m-8 4h5m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
                                                />
                                            </svg>
                                        </div>

                                        <div>
                                            <h2
                                                class="text-base font-bold text-[#1F2937]"
                                            >
                                                Message thread
                                            </h2>

                                            <p
                                                class="mt-0.5 text-xs text-[#94A3B8]"
                                            >
                                                Select a conversation to view messages.
                                            </p>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <!-- OVERVIEW -->
                            <Link
                                :href="route('admin.dashboard')"
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-[#E5E7EB] px-2.5 py-1.5 text-[10px] font-semibold text-[#64748B] transition hover:border-[#087F8C] hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                            >
                                <svg
                                    class="h-3 w-3"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M3 12h18M12 3l9 9-9 9"
                                    />
                                </svg>

                                Overview
                            </Link>
                        </div>
                    </div>


                    <!-- ================================================= -->
                    <!-- THREAD MESSAGES -->
                    <!-- ================================================= -->
                    <div
                        v-if="conversation"
                        class="flex-1 space-y-4 overflow-y-auto bg-[#F8FAF9] p-4 sm:p-5"
                    >

                        <!-- NO MESSAGES -->
                        <div
                            v-if="!messages.length"
                            class="flex min-h-[380px] items-center justify-center"
                        >
                            <div class="max-w-xs text-center">

                                <div
                                    class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl border border-[#E5E7EB] bg-white text-[#94A3B8] shadow-sm"
                                >
                                    <svg
                                        class="h-5 w-5"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="1.8"
                                            d="M8 10h8m-8 4h5m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                </div>

                                <p
                                    class="mt-3 text-sm font-semibold text-[#1F2937]"
                                >
                                    No messages yet
                                </p>

                                <p
                                    class="mt-1 text-[11px] leading-5 text-[#94A3B8]"
                                >
                                    Send the first message in this conversation.
                                </p>
                            </div>
                        </div>


                        <!-- MESSAGE -->
                        <div
                            v-for="message in messages"
                            :key="message.id"
                            class="flex"
                            :class="
                                isAdminMessage(message)
                                    ? 'justify-end'
                                    : 'justify-start'
                            "
                        >
                            <div
                                class="max-w-[82%] sm:max-w-[75%]"
                            >

                                <!-- SENDER -->
                                <p
                                    class="mb-1 text-[9px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                    :class="
                                        isAdminMessage(message)
                                            ? 'text-right'
                                            : 'text-left'
                                    "
                                >
                                    {{ message.sender?.name ?? 'User' }}
                                </p>

                                <!-- MESSAGE BUBBLE -->
                                <div
                                    class="rounded-xl px-3.5 py-2.5 text-xs leading-5 shadow-sm sm:text-[13px] space-y-2"
                                    :class="
                                        isAdminMessage(message)
                                            ? 'rounded-br-sm bg-[#087F8C] text-white'
                                            : 'rounded-bl-sm border border-[#E5E7EB] bg-white text-[#1F2937]'
                                    "
                                >
                                    <!-- Embedded Order Snippet (SELLER-43) -->
                                    <div
                                        v-if="message.order"
                                        :class="[
                                            'rounded-lg border p-2.5 text-xs',
                                            isAdminMessage(message)
                                                ? 'border-white/20 bg-white/10 text-white'
                                                : 'border-[#E5E7EB] bg-[#F8FAF9] text-[#1F2937]'
                                        ]"
                                    >
                                        <div class="flex items-center justify-between gap-2 border-b pb-1.5 mb-1.5" :class="isAdminMessage(message) ? 'border-white/15' : 'border-[#E5E7EB]'">
                                            <span class="font-bold">Order #{{ message.order.order_number }}</span>
                                            <span class="rounded px-1.5 py-0.5 text-[9px] font-bold border uppercase" :class="isAdminMessage(message) ? 'bg-white/20 text-white border-white/30' : 'bg-slate-100 text-slate-700 border-slate-200'">
                                                {{ message.order.status }}
                                            </span>
                                        </div>
                                        <p class="text-[11px] font-semibold">Total: ₱{{ Number(message.order.total).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}</p>
                                    </div>

                                    <!-- Media Attachment (SELLER-42) -->
                                    <div v-if="message.attachment_path" class="pt-1">
                                        <div
                                            v-if="isImageFile(message)"
                                            class="group relative overflow-hidden rounded-lg border max-w-sm cursor-pointer"
                                            :class="isAdminMessage(message) ? 'border-white/20' : 'border-[#E5E7EB]'"
                                            @click="lightboxImage = message.attachment_url"
                                        >
                                            <img
                                                :src="message.attachment_url"
                                                :alt="message.attachment_name || 'Attachment'"
                                                class="max-h-56 w-full object-cover transition duration-200 group-hover:scale-105"
                                            />
                                        </div>

                                        <div
                                            v-else
                                            class="flex items-center gap-2.5 rounded-lg border p-2 text-xs max-w-sm"
                                            :class="isAdminMessage(message) ? 'border-white/20 bg-white/10' : 'border-[#CBD5E1] bg-slate-50 text-[#1F2937]'"
                                        >
                                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded bg-teal-50 text-[#087F8C]">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate font-semibold text-[11px]">{{ message.attachment_name || 'Attached File' }}</p>
                                                <p class="text-[9px] opacity-75">{{ formatFileSize(message.attachment_size) }}</p>
                                            </div>
                                            <a
                                                :href="message.attachment_url"
                                                target="_blank"
                                                download
                                                class="rounded p-1 hover:bg-white/20"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                </svg>
                                            </a>
                                        </div>
                                    </div>

                                    <p v-if="message.body" class="whitespace-pre-wrap break-words">
                                        {{ message.body }}
                                    </p>
                                </div>

                                <!-- TIMESTAMP -->
                                <p
                                    class="mt-1 text-[9px] text-[#94A3B8]"
                                    :class="
                                        isAdminMessage(message)
                                            ? 'text-right'
                                            : 'text-left'
                                    "
                                >
                                    {{
                                        formatDate(
                                            message.created_at
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>


                    <!-- ================================================= -->
                    <!-- NO CONVERSATION SELECTED -->
                    <!-- ================================================= -->
                    <div
                        v-else
                        class="flex flex-1 items-center justify-center bg-[#F8FAF9] p-8"
                    >
                        <div class="max-w-sm text-center">

                            <div
                                class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl border border-[#E5E7EB] bg-white text-[#94A3B8] shadow-sm"
                            >
                                <svg
                                    class="h-7 w-7"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.7"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M8 10h8m-8 4h5m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                            </div>

                            <h3
                                class="mt-4 text-base font-bold text-[#1F2937]"
                            >
                                No conversation selected
                            </h3>

                            <p
                                class="mt-1.5 text-[11px] leading-5 text-[#64748B]"
                            >
                                Select a buyer and seller conversation from the
                                list to view the complete message history.
                            </p>
                        </div>
                    </div>


                    <!-- ================================================= -->
                    <!-- REPLY FORM -->
                    <!-- ================================================= -->
                    <form
                        v-if="conversation"
                        class="border-t border-[#E5E7EB] bg-white p-4 sm:p-5"
                        @submit.prevent="submitMessage"
                    >
                        <!-- ATTACHMENT PREVIEW CHIP -->
                        <div
                            v-if="form.attachment"
                            class="mb-2 flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-700"
                        >
                            <div class="flex items-center gap-2 min-w-0">
                                <svg class="h-3.5 w-3.5 text-[#087F8C]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                </svg>
                                <span class="truncate">{{ form.attachment.name }}</span>
                            </div>
                            <button type="button" @click="clearAttachment" class="text-slate-400 hover:text-rose-600">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div
                            class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-2"
                        >
                            <input
                                ref="fileInput"
                                type="file"
                                accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip"
                                @change="handleFileSelect"
                                class="hidden"
                            />

                            <textarea
                                v-model="form.body"
                                rows="3"
                                maxlength="5000"
                                placeholder="Type your message..."
                                class="w-full resize-none bg-transparent px-2 py-1.5 text-[13px] leading-5 text-[#1F2937] outline-none placeholder:text-[#94A3B8] disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="form.processing"
                            ></textarea>

                            <div
                                class="flex items-center justify-between gap-3 border-t border-[#E5E7EB] pt-2"
                            >
                                <div class="flex items-center gap-2">
                                    <!-- ATTACH FILE BUTTON -->
                                    <button
                                        type="button"
                                        @click="$refs.fileInput.click()"
                                        title="Attach document or image"
                                        class="inline-flex items-center gap-1 rounded-lg border border-[#E5E7EB] bg-white px-2.5 py-1.5 text-[11px] font-semibold text-[#64748B] hover:border-[#087F8C] hover:text-[#087F8C]"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                        </svg>
                                        Attach
                                    </button>

                                    <!-- FORM MESSAGE -->
                                    <p
                                        v-if="form.errors.body"
                                        class="px-2 text-[10px] font-medium text-[#E85D5D]"
                                    >
                                        {{ form.errors.body }}
                                    </p>

                                    <p
                                        v-else
                                        class="px-2 text-[10px] text-[#94A3B8]"
                                    >
                                        Messages are sent from the admin account.
                                    </p>
                                </div>

                                <!-- SEND BUTTON -->
                                <button
                                    type="submit"
                                    :disabled="
                                        form.processing ||
                                        (!form.body.trim() && !form.attachment)
                                    "
                                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-[#087F8C] px-3.5 py-2 text-[11px] font-semibold text-white transition hover:bg-[#066D77] disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <!-- SEND ICON -->
                                    <svg
                                        v-if="!form.processing"
                                        class="h-3.5 w-3.5"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M22 2 11 13"
                                        />

                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="m22 2-7 20-4-9-9-4 20-7Z"
                                        />
                                    </svg>

                                    <!-- LOADING ICON -->
                                    <svg
                                        v-else
                                        class="h-3.5 w-3.5 animate-spin"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <circle
                                            cx="12"
                                            cy="12"
                                            r="9"
                                            class="opacity-30"
                                        />

                                        <path
                                            stroke-linecap="round"
                                            d="M21 12a9 9 0 0 1-9 9"
                                        />
                                    </svg>

                                    {{
                                        form.processing
                                            ? 'Sending...'
                                            : 'Send message'
                                    }}
                                </button>
                            </div>
                        </div>

                        <!-- CHARACTER COUNT -->
                        <div class="mt-1.5 flex justify-end">
                            <span
                                class="text-[9px] text-[#94A3B8]"
                            >
                                {{ form.body.length }}/5000
                            </span>
                        </div>
                    </form>


                    <!-- ================================================= -->
                    <!-- NO REPLY FORM -->
                    <!-- ================================================= -->
                    <div
                        v-else
                        class="border-t border-[#E5E7EB] bg-white px-5 py-4"
                    >
                        <p
                            class="text-center text-[11px] text-[#94A3B8]"
                        >
                            Select a conversation before sending a message.
                        </p>
                    </div>

                </section>
            </div>
        </div>

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
    </AdminLayout>
</template>