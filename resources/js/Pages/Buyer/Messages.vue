<script setup>
import {
    computed,
    nextTick,
    onMounted,
    ref,
    watch,
} from 'vue'
import {
    Head,
    Link,
    useForm,
    usePage,
} from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'

const page = usePage()

const props = defineProps({
    messages: { type: Array, default: () => [] },
    conversations: { type: Array, default: () => [] },
    selected_conversation: { type: Object, default: null },
    conversation_messages: { type: Array, default: () => [] },
})

const messagesContainer = ref(null)

const supportForm = useForm({
    subject: '',
    message: '',
})

const chatForm = useForm({
    body: '',
})

const currentUserId = computed(() =>
    Number(page.props.auth?.user?.id || 0)
)

const activeConversation = computed(() =>
    props.selected_conversation || null
)

const activeSeller = computed(() =>
    activeConversation.value?.seller || null
)

const activeProduct = computed(() =>
    activeConversation.value?.product || null
)

const activeOrder = computed(() =>
    activeConversation.value?.order || null
)

const hasConversation = computed(() =>
    Boolean(activeConversation.value)
)

const sellerName = conversation =>
    conversation?.seller?.store_name ||
    conversation?.seller?.name ||
    'Seller'

const sellerInitial = conversation =>
    sellerName(conversation).charAt(0).toUpperCase()

const sellerImage = conversation =>
    conversation?.seller?.logo ||
    conversation?.seller?.avatar ||
    null

const conversationPreview = conversation => {
    const latest =
        conversation?.messages?.[0] ||
        conversation?.last_message

    return latest?.body || 'Start a conversation'
}

const unreadCount = conversation =>
    Number(conversation?.unread_count || 0)

const isMine = message =>
    Number(message?.sender_id) === currentUserId.value

const safeRoute = (name, params = undefined, fallback = '#') => {
    try {
        return params === undefined
            ? route(name)
            : route(name, params)
    } catch {
        return fallback
    }
}

const formatDate = value => {
    if (!value) return ''

    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return ''

    return date.toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    })
}

const formatConversationDate = value => {
    if (!value) return ''

    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return ''

    const now = new Date()
    const sameDay =
        date.getFullYear() === now.getFullYear() &&
        date.getMonth() === now.getMonth() &&
        date.getDate() === now.getDate()

    return sameDay
        ? date.toLocaleTimeString('en-PH', {
              hour: 'numeric',
              minute: '2-digit',
          })
        : date.toLocaleDateString('en-PH', {
              month: 'short',
              day: 'numeric',
          })
}

const scrollToBottom = async () => {
    await nextTick()

    if (!messagesContainer.value) return

    messagesContainer.value.scrollTop =
        messagesContainer.value.scrollHeight
}

const openConversation = conversation => {
    if (!conversation?.id) return

    window.location.href = safeRoute(
        'buyer.conversations.show',
        conversation.id,
        `/buyer/conversations/${conversation.id}`
    )
}

const submitChat = () => {
    const body = chatForm.body.trim()

    if (
        !body ||
        !activeConversation.value ||
        chatForm.processing
    ) {
        return
    }

    chatForm.body = body

    chatForm.post(
        safeRoute(
            'buyer.conversations.store',
            activeConversation.value.id,
            '#'
        ),
        {
            preserveScroll: true,
            onSuccess: async () => {
                chatForm.reset()
                await scrollToBottom()
            },
        }
    )
}

const handleChatKeydown = event => {
    if (
        event.key === 'Enter' &&
        !event.shiftKey
    ) {
        event.preventDefault()

        if (!chatForm.body.trim() || chatForm.processing) return

        submitChat()
    }
}

const submitSupport = () => {
    if (
        !supportForm.subject.trim() ||
        !supportForm.message.trim() ||
        supportForm.processing
    ) {
        return
    }

    supportForm.post(
        safeRoute(
            'buyer.messages.store',
            undefined,
            '/buyer/messages'
        ),
        {
            preserveScroll: true,
            onSuccess: () => supportForm.reset(),
        }
    )
}

watch(
    () => props.selected_conversation?.id,
    async () => {
        chatForm.reset()
        await scrollToBottom()
    }
)

watch(
    () => props.conversation_messages.length,
    async () => {
        await scrollToBottom()
    }
)

onMounted(scrollToBottom)
</script>

<template>
    <Head title="Messages" />

    <BuyerLayout>
        <main class="min-h-screen w-full min-w-0 overflow-x-hidden bg-[#F8FAF9]">
            <div class="mx-auto w-full max-w-[1600px] px-3 py-5 sm:px-6 lg:px-8 lg:py-7">

                <!-- HEADER -->
                <header class="mb-5">
                    <div class="flex items-end justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#087F8C]">
                                Alona Inbox
                            </p>
                            <h1 class="mt-1 text-2xl font-black tracking-tight text-[#1F2937] sm:text-3xl">
                                Messages
                            </h1>
                            <p class="mt-1 max-w-2xl text-sm text-[#64748B]">
                                Chat with sellers and get help from Alona support.
                            </p>
                        </div>

                        <div class="hidden shrink-0 rounded-full bg-white px-3 py-1.5 text-xs font-bold text-[#087F8C] ring-1 ring-[#E5E7EB] sm:block">
                            {{ props.conversations.length }} chats
                        </div>
                    </div>
                </header>

                <!-- MAIN MESSENGER -->
                <section
                    class="grid min-w-0 overflow-hidden rounded-3xl border border-[#E5E7EB] bg-white shadow-[0_10px_35px_rgba(15,23,42,0.07)] lg:h-[680px] lg:grid-cols-[320px_minmax(0,1fr)] xl:grid-cols-[350px_minmax(0,1fr)]"
                >
                    <!-- SIDEBAR -->
                    <aside class="flex min-h-0 min-w-0 flex-col border-b border-[#E5E7EB] bg-white lg:border-b-0 lg:border-r">
                        <div class="flex shrink-0 items-center justify-between border-b border-[#E5E7EB] px-4 py-4 sm:px-5">
                            <div>
                                <p class="text-sm font-extrabold text-[#1F2937]">Your conversations</p>
                                <p class="mt-0.5 text-xs text-[#64748B]">
                                    Messages from sellers
                                </p>
                            </div>
                            <span class="rounded-full bg-[#E8F7F6] px-2.5 py-1 text-[11px] font-bold text-[#087F8C]">
                                {{ props.conversations.length }}
                            </span>
                        </div>

                        <div
                            v-if="props.conversations.length"
                            class="min-h-0 flex-1 overflow-y-auto"
                        >
                            <button
                                v-for="conversation in props.conversations"
                                :key="conversation.id"
                                type="button"
                                class="group relative flex w-full min-w-0 gap-3 border-b border-[#F1F5F9] px-4 py-3.5 text-left transition hover:bg-[#F8FAF9]"
                                :class="
                                    Number(activeConversation?.id) === Number(conversation.id)
                                        ? 'bg-[#E8F7F6]'
                                        : ''
                                "
                                @click="openConversation(conversation)"
                            >
                                <span
                                    v-if="Number(activeConversation?.id) === Number(conversation.id)"
                                    class="absolute inset-y-2 left-0 w-1 rounded-r-full bg-[#087F8C]"
                                />

                                <div class="relative h-11 w-11 shrink-0 overflow-hidden rounded-full bg-[#E8F7F6] text-center text-sm font-black leading-[44px] text-[#087F8C]">
                                    <img
                                        v-if="sellerImage(conversation)"
                                        :src="sellerImage(conversation)"
                                        :alt="sellerName(conversation)"
                                        class="h-full w-full object-cover"
                                    />
                                    <span v-else>{{ sellerInitial(conversation) }}</span>

                                    <span
                                        v-if="unreadCount(conversation) > 0"
                                        class="absolute right-0 top-0 h-3 w-3 rounded-full border-2 border-white bg-[#F4B942]"
                                    />
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex min-w-0 items-start justify-between gap-2">
                                        <h3 class="min-w-0 flex-1 truncate text-sm font-extrabold text-[#1F2937]">
                                            {{ sellerName(conversation) }}
                                        </h3>
                                        <span class="shrink-0 whitespace-nowrap text-[10px] text-[#94A3B8]">
                                            {{ formatConversationDate(conversation.last_message_at) }}
                                        </span>
                                    </div>

                                    <p
                                        v-if="conversation.product?.name"
                                        class="mt-0.5 truncate text-[11px] font-semibold text-[#087F8C]"
                                    >
                                        {{ conversation.product.name }}
                                    </p>

                                    <p
                                        class="mt-1 line-clamp-2 break-words text-xs leading-4 text-[#64748B]"
                                        :class="unreadCount(conversation) > 0 ? 'font-semibold text-[#334155]' : ''"
                                    >
                                        {{ conversationPreview(conversation) }}
                                    </p>

                                    <span
                                        v-if="unreadCount(conversation) > 0"
                                        class="mt-2 inline-flex rounded-full bg-[#087F8C] px-2 py-0.5 text-[9px] font-bold text-white"
                                    >
                                        {{ unreadCount(conversation) > 99 ? '99+' : unreadCount(conversation) }} new
                                    </span>
                                </div>
                            </button>
                        </div>

                        <div
                            v-else
                            class="flex flex-1 items-center justify-center px-6 py-12"
                        >
                            <div class="max-w-xs text-center">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-[#E8F7F6] text-[#087F8C]">
                                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h6m8-1a8.5 8.5 0 0 1-8.5 8.5 8.5 8.5 0 0 1-4.17-1.09L4 20l1.59-3.33A8.5 8.5 0 1 1 21 11Z"/>
                                    </svg>
                                </div>
                                <h3 class="mt-4 text-sm font-extrabold text-[#1F2937]">No conversations yet</h3>
                                <p class="mt-1 text-xs leading-5 text-[#64748B]">
                                    Start chatting with a seller from a product page.
                                </p>
                            </div>
                        </div>
                    </aside>

                    <!-- CHAT -->
                    <section
                        v-if="hasConversation"
                        class="flex min-h-0 min-w-0 flex-col bg-[#F8FAF9]"
                    >
                        <!-- CHAT HEADER -->
                        <div class="shrink-0 border-b border-[#E5E7EB] bg-white px-4 py-3.5 sm:px-5">
                            <div class="flex min-w-0 items-center gap-3">
                                <Link
                                    :href="safeRoute('buyer.conversations', undefined, '/buyer/conversations')"
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-[#64748B] hover:bg-[#F1F5F9] lg:hidden"
                                >
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/>
                                    </svg>
                                </Link>

                                <div class="h-10 w-10 shrink-0 overflow-hidden rounded-full bg-[#E8F7F6] text-center text-sm font-black leading-10 text-[#087F8C]">
                                    <img
                                        v-if="sellerImage(activeConversation)"
                                        :src="sellerImage(activeConversation)"
                                        :alt="sellerName(activeConversation)"
                                        class="h-full w-full object-cover"
                                    />
                                    <span v-else>{{ sellerInitial(activeConversation) }}</span>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <h2 class="truncate text-sm font-extrabold text-[#1F2937] sm:text-base">
                                        {{ sellerName(activeConversation) }}
                                    </h2>
                                    <p class="truncate text-[11px] text-[#64748B]">
                                        {{ activeProduct?.name || 'Seller conversation' }}
                                    </p>
                                </div>

                                <div v-if="activeOrder" class="hidden shrink-0 text-right sm:block">
                                    <p class="text-[9px] font-bold uppercase tracking-wider text-[#94A3B8]">Order</p>
                                    <p class="text-xs font-extrabold text-[#334155]">
                                        #{{ activeOrder.order_number || activeOrder.id }}
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="activeProduct"
                                class="mt-3 flex min-w-0 items-center gap-3 rounded-2xl border border-[#E5E7EB] bg-[#F8FAF9] p-2.5"
                            >
                                <div class="h-11 w-11 shrink-0 overflow-hidden rounded-xl bg-white">
                                    <img
                                        v-if="activeProduct.image_path"
                                        :src="
                                            activeProduct.image_path.startsWith('http')
                                                ? activeProduct.image_path
                                                : `/storage/${activeProduct.image_path.replace(/^\/+/, '')}`
                                        "
                                        :alt="activeProduct.name"
                                        class="h-full w-full object-cover"
                                    />
                                    <div v-else class="flex h-full w-full items-center justify-center text-[#94A3B8]">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4-4 4 4 4-6 4 6M5 20h14a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v14a1 1 0 001 1z"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xs font-bold text-[#334155]">{{ activeProduct.name }}</p>
                                    <p v-if="activeProduct.price" class="mt-0.5 text-xs font-extrabold text-[#087F8C]">
                                        ₱{{ Number(activeProduct.price).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- MESSAGES -->
                        <div
                            ref="messagesContainer"
                            class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden px-3 py-5 sm:px-5 lg:px-7"
                        >
                            <div
                                v-if="!props.conversation_messages.length"
                                class="flex min-h-[320px] items-center justify-center"
                            >
                                <div class="max-w-sm text-center">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-[#E8F7F6] text-[#087F8C]">
                                        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h6m8-1a8.5 8.5 0 0 1-8.5 8.5 8.5 8.5 0 0 1-4.17-1.09L4 20l1.59-3.33A8.5 8.5 0 1 1 21 11Z"/>
                                        </svg>
                                    </div>
                                    <h3 class="mt-4 text-sm font-extrabold text-[#334155]">Start the conversation</h3>
                                    <p class="mt-1 text-xs leading-5 text-[#64748B]">
                                        Send a message to {{ sellerName(activeConversation) }}.
                                    </p>
                                </div>
                            </div>

                            <div
                                v-else
                                class="mx-auto flex w-full max-w-4xl flex-col gap-4"
                            >
                                <div
                                    v-for="message in props.conversation_messages"
                                    :key="message.id"
                                    class="flex w-full min-w-0"
                                    :class="isMine(message) ? 'justify-end' : 'justify-start'"
                                >
                                    <!--
                                        Important overlap fix:
                                        - max-width is controlled at the message wrapper
                                        - min-width:0 allows flex children to shrink
                                        - long URLs/text are forced to wrap
                                        - bubble width is fit-content instead of stretching
                                    -->
                                    <div
                                        class="min-w-0 max-w-[82%] sm:max-w-[70%] lg:max-w-[68%]"
                                    >
                                        <div
                                            class="w-fit max-w-full rounded-2xl px-4 py-3 text-sm shadow-sm"
                                            :class="
                                                isMine(message)
                                                    ? 'ml-auto rounded-br-md bg-[#087F8C] text-white'
                                                    : 'mr-auto rounded-bl-md border border-[#E5E7EB] bg-white text-[#334155]'
                                            "
                                        >
                                            <p
                                                class="max-w-full whitespace-pre-wrap break-words [overflow-wrap:anywhere] leading-6"
                                            >
                                                {{ message.body }}
                                            </p>
                                        </div>

                                        <p
                                            class="mt-1 px-1 text-[9px] text-[#94A3B8]"
                                            :class="isMine(message) ? 'text-right' : 'text-left'"
                                        >
                                            {{ formatDate(message.created_at) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- COMPOSER -->
                        <div class="shrink-0 border-t border-[#E5E7EB] bg-white p-3 sm:p-4">
                            <form @submit.prevent="submitChat">
                                <div class="mx-auto flex max-w-4xl items-end gap-2">
                                    <textarea
                                        v-model="chatForm.body"
                                        rows="1"
                                        maxlength="2000"
                                        placeholder="Write a message..."
                                        class="min-h-[46px] max-h-32 min-w-0 flex-1 resize-none rounded-2xl border border-[#D7DEE5] bg-[#F8FAF9] px-4 py-3 text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                        :disabled="chatForm.processing"
                                        @keydown="handleChatKeydown"
                                    />

                                    <button
                                        type="submit"
                                        :disabled="chatForm.processing || !chatForm.body.trim()"
                                        class="inline-flex h-[46px] shrink-0 items-center justify-center gap-2 rounded-2xl bg-[#087F8C] px-4 text-sm font-extrabold text-white transition hover:bg-[#066D78] active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        <svg v-if="!chatForm.processing" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m22 2-7 20-4-9-9-4Z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M22 2 11 13"/>
                                        </svg>
                                        <svg v-else class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                            <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v3a5 5 0 0 0-5 5H4Z"/>
                                        </svg>
                                        <span class="hidden sm:inline">
                                            {{ chatForm.processing ? 'Sending...' : 'Send' }}
                                        </span>
                                    </button>
                                </div>

                                <p class="mx-auto mt-1.5 max-w-4xl px-1 text-[10px] text-[#94A3B8]">
                                    Enter to send · Shift + Enter for a new line
                                </p>
                            </form>
                        </div>
                    </section>

                    <!-- NO ACTIVE CHAT -->
                    <section
                        v-else
                        class="flex min-h-[560px] items-center justify-center bg-[#F8FAF9] px-6"
                    >
                        <div class="max-w-sm text-center">
                            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-[#E8F7F6] text-[#087F8C]">
                                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h6m8-1a8.5 8.5 0 0 1-8.5 8.5 8.5 8.5 0 0 1-4.17-1.09L4 20l1.59-3.33A8.5 8.5 0 1 1 21 11Z"/>
                                </svg>
                            </div>
                            <h2 class="mt-5 text-lg font-black text-[#1F2937]">Select a conversation</h2>
                            <p class="mt-1 text-sm leading-6 text-[#64748B]">
                                Choose a seller from the list to view and reply to your messages.
                            </p>
                        </div>
                    </section>
                </section>

                <!-- SUPPORT -->
                <section class="mt-6 overflow-hidden rounded-3xl border border-[#E5E7EB] bg-white shadow-sm">
                    <div class="border-b border-[#E5E7EB] bg-gradient-to-r from-[#E8F7F6] to-white px-4 py-5 sm:px-6">
                        <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-[#087F8C]">Help Center</p>
                        <h2 class="mt-1 text-xl font-black text-[#1F2937]">Contact Alona Support</h2>
                        <p class="mt-1 text-sm text-[#64748B]">
                            Need help with an order or account? We're here to help.
                        </p>
                    </div>

                    <div class="grid gap-6 p-4 sm:p-6 lg:grid-cols-2">
                        <form class="space-y-4" @submit.prevent="submitSupport">
                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-[#334155]">Subject</label>
                                <input
                                    v-model="supportForm.subject"
                                    type="text"
                                    maxlength="255"
                                    placeholder="What can we help you with?"
                                    class="w-full rounded-2xl border border-[#D7DEE5] px-4 py-3 text-sm outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:ring-2 focus:ring-[#E8F7F6]"
                                    :disabled="supportForm.processing"
                                />
                                <p v-if="supportForm.errors.subject" class="mt-1 text-xs text-[#E85D5D]">
                                    {{ supportForm.errors.subject }}
                                </p>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-xs font-bold text-[#334155]">Message</label>
                                <textarea
                                    v-model="supportForm.message"
                                    rows="5"
                                    maxlength="2000"
                                    placeholder="Describe your concern..."
                                    class="w-full resize-none rounded-2xl border border-[#D7DEE5] px-4 py-3 text-sm outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:ring-2 focus:ring-[#E8F7F6]"
                                    :disabled="supportForm.processing"
                                />
                                <p v-if="supportForm.errors.message" class="mt-1 text-xs text-[#E85D5D]">
                                    {{ supportForm.errors.message }}
                                </p>
                            </div>

                            <button
                                type="submit"
                                :disabled="
                                    supportForm.processing ||
                                    !supportForm.subject.trim() ||
                                    !supportForm.message.trim()
                                "
                                class="rounded-2xl bg-[#087F8C] px-5 py-3 text-sm font-extrabold text-white transition hover:bg-[#066D78] disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{ supportForm.processing ? 'Sending...' : 'Send to Support' }}
                            </button>
                        </form>

                        <div>
                            <div class="mb-3">
                                <h3 class="text-sm font-black text-[#1F2937]">Previous support messages</h3>
                                <p class="mt-0.5 text-xs text-[#64748B]">Your support requests and replies.</p>
                            </div>

                            <div
                                v-if="props.messages.length"
                                class="max-h-[360px] space-y-3 overflow-y-auto pr-1"
                            >
                                <article
                                    v-for="message in props.messages"
                                    :key="message.id"
                                    class="rounded-2xl border border-[#E5E7EB] bg-[#F8FAF9] p-4"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <h4 class="truncate text-sm font-bold text-[#1F2937]">
                                                {{ message.subject || 'Support Request' }}
                                            </h4>
                                            <p class="mt-1 text-[10px] text-[#94A3B8]">
                                                {{ formatDate(message.created_at) }}
                                            </p>
                                        </div>

                                        <span
                                            v-if="message.status"
                                            class="shrink-0 rounded-full bg-white px-2 py-1 text-[10px] font-bold capitalize text-[#64748B] ring-1 ring-[#E5E7EB]"
                                        >
                                            {{ message.status }}
                                        </span>
                                    </div>

                                    <p class="mt-3 whitespace-pre-wrap break-words [overflow-wrap:anywhere] text-xs leading-5 text-[#64748B]">
                                        {{ message.message || message.body }}
                                    </p>

                                    <div
                                        v-if="message.reply"
                                        class="mt-3 rounded-xl border border-[#BFE7E4] bg-[#E8F7F6] p-3"
                                    >
                                        <p class="text-[10px] font-black uppercase tracking-wide text-[#087F8C]">
                                            Support Reply
                                        </p>
                                        <p class="mt-1 whitespace-pre-wrap break-words [overflow-wrap:anywhere] text-xs leading-5 text-[#334155]">
                                            {{ message.reply }}
                                        </p>
                                    </div>
                                </article>
                            </div>

                            <div
                                v-else
                                class="flex min-h-[220px] items-center justify-center rounded-2xl border border-dashed border-[#D7DEE5] bg-[#F8FAF9] px-5"
                            >
                                <div class="text-center">
                                    <p class="text-sm font-bold text-[#334155]">No support messages</p>
                                    <p class="mt-1 text-xs text-[#64748B]">
                                        Your support conversations will appear here.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </BuyerLayout>
</template>
