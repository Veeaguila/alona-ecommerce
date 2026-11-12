<script setup>
import { Head, Link } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'

const props = defineProps({
    conversations: {
        type: Array,
        default: () => [],
    },
    sellerOrders: {
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
        <div class="w-full space-y-6">

                <!-- Page Header -->
                <div class="border-b border-[#E5E7EB] pb-5">
                    <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-[#087F8C]">
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                        <span>Chat & Messaging</span>
                    </div>

                    <h1 class="mt-1 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                        Customer Messages
                    </h1>

                    <p class="mt-0.5 text-xs text-slate-500">
                        Manage conversations and communicate directly with your customers in real time.
                    </p>
                </div>

                <!-- Conversations Section -->
                <section class="mt-5 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm">
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

                            <div class="rounded-full bg-[#E8F7F6] px-2.5 py-1 text-[10px] font-bold text-[#087F8C]">
                                {{ props.conversations.length }} {{ props.conversations.length === 1 ? 'conversation' : 'conversations' }}
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-if="!props.conversations.length" class="px-5 py-14 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-[#F8FAF9] text-[#94A3B8]">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 10h8M8 14h5m7-2a8 8 0 01-8 8 8.4 8.4 0 01-3.1-.6L4 21l1.6-3.8A8 8 0 1120 12z" />
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
                                <!-- Avatar -->
                                <div
                                    v-if="conversation.type === 'seller_admin'"
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#FEF3C7] text-sm font-bold text-[#D97706]"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                </div>
                                <div
                                    v-else
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#E8F7F6] text-sm font-bold text-[#087F8C]"
                                >
                                    {{ (conversation.buyer?.name || 'C').charAt(0).toUpperCase() }}
                                </div>

                                <!-- Conversation Details -->
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <p class="truncate text-sm font-bold text-[#1F2937] group-hover:text-[#087F8C]">
                                            {{ conversation.type === 'seller_admin' ? (conversation.admin?.name || conversation.subject || 'Platform Support') : (conversation.buyer?.name || 'Customer') }}
                                        </p>

                                        <span
                                            v-if="conversation.type === 'seller_admin'"
                                            class="rounded bg-amber-100 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-amber-800"
                                        >
                                            Support
                                        </span>

                                        <span
                                            v-if="conversation.unread_count"
                                            class="inline-flex shrink-0 items-center justify-center rounded-full bg-[#087F8C] px-2 py-0.5 text-[10px] font-bold text-white"
                                        >
                                            {{ conversation.unread_count }}
                                        </span>

                                        <span
                                            v-if="conversation.order"
                                            class="rounded bg-[#F1F5F9] px-1.5 py-0.5 text-[9px] font-semibold text-[#64748B]"
                                        >
                                            Order #{{ conversation.order.order_number }}
                                        </span>
                                    </div>

                                    <p class="mt-1 truncate text-xs text-[#64748B]">
                                        {{ conversation.messages?.[0]?.body || 'Start the conversation' }}
                                    </p>
                                </div>

                                <!-- Date and Arrow -->
                                <div class="flex shrink-0 items-center gap-2">
                                    <p class="text-right text-[10px] text-[#94A3B8]">
                                        {{ formatDate(conversation.last_message_at) }}
                                    </p>

                                    <svg
                                        class="h-4 w-4 text-[#CBD5E1] transition group-hover:translate-x-0.5 group-hover:text-[#087F8C]"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5l7 7-7 7" />
                                    </svg>
                                </div>
                            </div>
                        </Link>
                    </div>
                </section>

                <div class="h-5"></div>
            </div>
    </SellerLayout>
</template>