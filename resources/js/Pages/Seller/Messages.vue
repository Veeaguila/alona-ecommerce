<script setup>
import { Head, Link } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'

const props = defineProps({
    conversations: {
        type: Array,
        default: () => [],
    },
})

const formatDate = (value) =>
    value
        ? new Date(value).toLocaleDateString('en-US', {
              month: 'short',
              day: 'numeric',
              hour: 'numeric',
              minute: '2-digit',
          })
        : '—'
</script>

<template>
    <Head title="Seller Messages" />

    <SellerLayout>
        <div class="min-h-[calc(100vh-68px)] bg-[#F8FAF9]">
            <div class="mx-auto max-w-5xl px-4 py-5 sm:px-6 lg:px-8">

                <!-- Page Header -->
                <div class="border-b border-[#E5E7EB] pb-5">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-[#087F8C]">
                        Seller Center
                    </p>

                    <h1
                        class="mt-1 text-2xl font-bold tracking-tight text-[#1F2937] sm:text-3xl"
                    >
                        Customer Messages
                    </h1>

                    <p class="mt-1 text-sm text-[#64748B]">
                        Manage conversations and communicate directly with your customers.
                    </p>
                </div>

                <!-- Conversations -->
                <section
                    class="mt-5 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                >
                    <!-- Section Header -->
                    <div class="border-b border-[#E5E7EB] px-5 py-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h2 class="text-base font-bold text-[#1F2937]">
                                    Conversations
                                </h2>

                                <p class="mt-0.5 text-xs text-[#64748B]">
                                    Your recent customer conversations.
                                </p>
                            </div>

                            <div
                                class="rounded-full bg-[#E8F7F6] px-2.5 py-1 text-[10px] font-bold text-[#087F8C]"
                            >
                                {{ props.conversations.length }}
                                {{ props.conversations.length === 1 ? 'conversation' : 'conversations' }}
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div
                        v-if="!props.conversations.length"
                        class="px-5 py-14 text-center"
                    >
                        <div
                            class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-[#F8FAF9] text-[#94A3B8]"
                        >
                            <svg
                                class="h-6 w-6"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.7"
                                    d="M8 10h8M8 14h5m7-2a8 8 0 01-8 8 8.4 8.4 0 01-3.1-.6L4 21l1.6-3.8A8 8 0 1120 12z"
                                />
                            </svg>
                        </div>

                        <p class="mt-3 text-sm font-semibold text-[#475569]">
                            No conversations yet.
                        </p>

                        <p class="mt-1 text-[11px] text-[#94A3B8]">
                            Customer conversations will appear here.
                        </p>
                    </div>

                    <!-- Conversation List -->
                    <div v-else class="divide-y divide-[#E5E7EB]">
                        <Link
                            v-for="conversation in props.conversations"
                            :key="conversation.id"
                            :href="route('seller.messages.show', conversation.id)"
                            class="group block px-5 py-4 transition hover:bg-[#F8FAF9]"
                        >
                            <div class="flex items-center gap-3">

                                <!-- Customer Avatar -->
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#E8F7F6] text-sm font-bold text-[#087F8C]"
                                >
                                    {{
                                        (conversation.buyer?.name || 'C')
                                            .charAt(0)
                                            .toUpperCase()
                                    }}
                                </div>

                                <!-- Conversation Content -->
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <p
                                            class="truncate text-sm font-bold text-[#1F2937] group-hover:text-[#087F8C]"
                                        >
                                            {{ conversation.buyer?.name || 'Customer' }}
                                        </p>

                                        <span
                                            v-if="conversation.unread_count"
                                            class="inline-flex shrink-0 items-center justify-center rounded-full bg-[#087F8C] px-2 py-0.5 text-[10px] font-bold text-white"
                                        >
                                            {{ conversation.unread_count }}
                                        </span>
                                    </div>

                                    <p
                                        class="mt-1 truncate text-xs text-[#64748B]"
                                    >
                                        {{
                                            conversation.messages?.[0]?.body ||
                                            'Start the conversation'
                                        }}
                                    </p>
                                </div>

                                <!-- Date + Arrow -->
                                <div
                                    class="flex shrink-0 items-center gap-2"
                                >
                                    <p class="text-right text-[10px] text-[#94A3B8]">
                                        {{ formatDate(conversation.last_message_at) }}
                                    </p>

                                    <svg
                                        class="h-4 w-4 text-[#CBD5E1] transition group-hover:translate-x-0.5 group-hover:text-[#087F8C]"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="1.8"
                                            d="M9 5l7 7-7 7"
                                        />
                                    </svg>
                                </div>
                            </div>
                        </Link>
                    </div>
                </section>

                <div class="h-5"></div>
            </div>
        </div>
    </SellerLayout>
</template>