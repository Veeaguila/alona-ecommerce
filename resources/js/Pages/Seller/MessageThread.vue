<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'

const props = defineProps({
    conversation: { type: Object, required: true },
    messages: { type: Array, default: () => [] },
})

const form = useForm({ body: '' })

const submit = () => {
    form.post(route('seller.messages.store', props.conversation.id), {
        preserveScroll: true,
        onSuccess: () => form.reset('body'),
    })
}

const isMine = (message) => message.sender_id === props.conversation.seller_id
</script>

<template>
    <Head :title="`Message - ${conversation.buyer?.name || 'Customer'}`" />

    <SellerLayout>
        <!-- Bottom padding reserves space for the fixed SellerLayout footer -->
        <div class="min-h-[calc(100vh-68px)] bg-[#F8FAF9] pb-20">
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
                        Communicate directly with your customer.
                    </p>
                </div>

                <!-- Conversation -->
                <section
                    class="mt-5 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                >

                    <!-- Conversation Header -->
                    <div
                        class="flex items-center gap-3 border-b border-[#E5E7EB] px-5 py-4"
                    >
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#E8F7F6] text-sm font-bold text-[#087F8C]"
                        >
                            {{ (conversation.buyer?.name || 'C').charAt(0).toUpperCase() }}
                        </div>

                        <div class="min-w-0">
                            <p class="text-sm font-bold text-[#1F2937]">
                                {{ conversation.buyer?.name || 'Customer' }}
                            </p>

                            <p class="mt-0.5 text-[11px] text-[#94A3B8]">
                                Customer conversation
                            </p>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div
                        class="min-h-[320px] space-y-3 bg-[#F8FAF9] p-4 sm:min-h-[360px] sm:p-5"
                    >
                        <!-- Empty State -->
                        <div
                            v-if="!props.messages.length"
                            class="flex min-h-[280px] items-center justify-center text-center sm:min-h-[320px]"
                        >
                            <div>
                                <div
                                    class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-white text-[#94A3B8] shadow-sm"
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
                                            stroke-width="1.7"
                                            d="M8 10h8M8 14h5m7-2a8 8 0 01-8 8 8.4 8.4 0 01-3.1-.6L4 21l1.6-3.8A8 8 0 0120 12z"
                                        />
                                    </svg>
                                </div>

                                <p class="mt-3 text-sm font-semibold text-[#64748B]">
                                    No messages yet.
                                </p>

                                <p class="mt-1 text-[11px] text-[#94A3B8]">
                                    Send a message to start the conversation.
                                </p>
                            </div>
                        </div>

                        <!-- Message List -->
                        <div
                            v-for="message in props.messages"
                            v-else
                            :key="message.id"
                            :class="[
                                'flex',
                                isMine(message)
                                    ? 'justify-end'
                                    : 'justify-start',
                            ]"
                        >
                            <div
                                :class="[
                                    'max-w-[85%] rounded-2xl px-4 py-3 text-sm leading-relaxed shadow-sm sm:max-w-lg',
                                    isMine(message)
                                        ? 'rounded-br-md bg-[#087F8C] text-white'
                                        : 'rounded-bl-md border border-[#E5E7EB] bg-white text-[#475569]',
                                ]"
                            >
                                {{ message.body }}
                            </div>
                        </div>
                    </div>

                    <!-- Message Composer -->
                    <form
                        class="border-t border-[#E5E7EB] bg-white p-4 sm:p-5"
                        @submit.prevent="submit"
                    >
                        <label
                            for="message"
                            class="mb-1.5 block text-[11px] font-semibold text-[#475569]"
                        >
                            Message
                        </label>

                        <textarea
                            id="message"
                            v-model="form.body"
                            rows="3"
                            placeholder="Type a message to your customer..."
                            class="w-full resize-none rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                        ></textarea>

                        <div class="mt-3 flex items-center justify-between gap-3">
                            <p
                                class="hidden text-[11px] text-[#94A3B8] sm:block"
                            >
                                Keep your communication clear and helpful.
                            </p>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="ml-auto inline-flex items-center justify-center gap-2 rounded-xl bg-[#087F8C] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#066D78] disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <svg
                                    v-if="!form.processing"
                                    class="h-4 w-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.8"
                                        d="M22 2L11 13"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.8"
                                        d="M22 2l-7 20-4-9 20-7z"
                                    />
                                </svg>

                                <svg
                                    v-else
                                    class="h-4 w-4 animate-spin"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 3v2m6.364.636l-1.414 1.414M21 12h-2m-.636 6.364l-1.414-1.414M12 21v-2m-6.364-.636l1.414-1.414M3 12h2m.636-6.364l1.414 1.414"
                                    />
                                </svg>

                                {{ form.processing ? 'Sending...' : 'Send message' }}
                            </button>
                        </div>
                    </form>
                </section>

                <!-- Extra breathing room above fixed footer -->
                <div class="h-2"></div>
            </div>
        </div>
    </SellerLayout>
</template>