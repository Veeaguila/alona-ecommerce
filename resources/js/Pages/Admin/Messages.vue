<script setup>
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

const form = useForm({
    body: '',
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

const submitMessage = () => {
    if (!props.conversation || !form.body.trim()) {
        return
    }

    form.post(
        route('admin.messages.store', props.conversation.id),
        {
            preserveScroll: true,

            onSuccess: () => {
                form.reset('body')
            },
        }
    )
}
</script>

<template>
    <Head :title="title || 'Messages'" />

    <AdminLayout :active="active || 'messages'">
        <div class="mx-auto max-w-7xl">

            <!-- PAGE HEADER -->
            <div class="pb-5">
                <div class="flex items-center gap-2">
                    <span
                        class="h-1.5 w-1.5 rounded-full bg-[#F4B942]"
                    ></span>

                    <p
                        class="text-xs font-bold uppercase tracking-[0.14em] text-[#087F8C]"
                    >
                        {{ eyebrow }}
                    </p>
                </div>

                <h1
                    class="mt-2 text-2xl font-bold tracking-tight text-[#1F2937] sm:text-3xl"
                >
                    {{ title }}
                </h1>

                <p
                    class="mt-1.5 max-w-3xl text-sm leading-5 text-[#64748B]"
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
                                    class="rounded-xl px-3.5 py-2.5 text-xs leading-5 shadow-sm sm:text-[13px]"
                                    :class="
                                        isAdminMessage(message)
                                            ? 'rounded-br-sm bg-[#087F8C] text-white'
                                            : 'rounded-bl-sm border border-[#E5E7EB] bg-white text-[#1F2937]'
                                    "
                                >
                                    {{ message.body }}
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
                        <div
                            class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-2"
                        >
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

                                <!-- SEND BUTTON -->
                                <button
                                    type="submit"
                                    :disabled="
                                        form.processing ||
                                        !form.body.trim()
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
    </AdminLayout>
</template>