```vue
<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'

const props = defineProps({
    conversation: {
        type: Object,
        default: () => ({}),
    },

    messages: {
        type: Array,
        default: () => [],
    },
})

const form = useForm({
    body: '',
})

const messagesContainer = ref(null)

/*
|--------------------------------------------------------------------------
| Seller
|--------------------------------------------------------------------------
*/

const seller = computed(() => props.conversation?.seller || {})

const sellerName = computed(() => {
    return (
        seller.value?.store_name ||
        seller.value?.name ||
        'Seller'
    )
})

const sellerInitial = computed(() => {
    return sellerName.value.charAt(0).toUpperCase()
})

/*
|--------------------------------------------------------------------------
| Product / Order
|--------------------------------------------------------------------------
*/

const product = computed(() => props.conversation?.product || null)

const order = computed(() => props.conversation?.order || null)

/*
|--------------------------------------------------------------------------
| Message ownership
|--------------------------------------------------------------------------
*/

const isMine = message => {
    return (
        Number(message.sender_id) ===
        Number(props.conversation?.buyer_id)
    )
}

/*
|--------------------------------------------------------------------------
| Date / Time
|--------------------------------------------------------------------------
*/

const formatTime = value => {
    if (!value) {
        return ''
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return ''
    }

    return date.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
    })
}

const formatDate = value => {
    if (!value) {
        return ''
    }

    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return ''
    }

    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    })
}

/*
|--------------------------------------------------------------------------
| Scroll
|--------------------------------------------------------------------------
*/

const scrollToBottom = async () => {
    await nextTick()

    if (messagesContainer.value) {
        messagesContainer.value.scrollTop =
            messagesContainer.value.scrollHeight
    }
}

/*
|--------------------------------------------------------------------------
| Send Message
|--------------------------------------------------------------------------
*/

const submit = () => {
    const body = form.body.trim()

    if (!body || form.processing) {
        return
    }

    form.body = body

    form.post(
        route(
            'buyer.conversations.store',
            props.conversation.id
        ),
        {
            preserveScroll: true,

            onSuccess: async () => {
                form.reset('body')

                await scrollToBottom()
            },
        }
    )
}

/*
|--------------------------------------------------------------------------
| Enter = Send
| Shift + Enter = New Line
|--------------------------------------------------------------------------
*/

const handleKeydown = event => {
    /*
     * Enter:
     * Send the message.
     *
     * Shift + Enter:
     * Keep the normal textarea behavior
     * so the user can create a new line.
     */

    if (
        event.key === 'Enter' &&
        !event.shiftKey
    ) {
        event.preventDefault()

        if (
            !form.body.trim() ||
            form.processing
        ) {
            return
        }

        submit()
    }
}

/*
|--------------------------------------------------------------------------
| Mounted
|--------------------------------------------------------------------------
*/

onMounted(() => {
    scrollToBottom()
})
</script>

<template>
    <Head :title="`Chat with ${sellerName}`" />

    <BuyerLayout>
        <main class="w-full min-w-0 overflow-x-hidden">
            <div
                class="w-full min-w-0 px-4 py-6 sm:px-6 sm:py-7 lg:px-8 lg:py-8"
            >
                <!-- ================================================== -->
                <!-- PAGE HEADER -->
                <!-- ================================================== -->

                <div class="w-full min-w-0">
                    <div
                        class="flex min-w-0 items-center gap-3"
                    >
                        <!-- BACK BUTTON -->

                        <Link
                            :href="
                                route(
                                    'buyer.conversations'
                                )
                            "
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-gray-100 bg-white text-gray-500 shadow-sm transition hover:bg-gray-50 hover:text-gray-900"
                        >
                            <svg
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15 19l-7-7 7-7"
                                />
                            </svg>
                        </Link>

                        <!-- SELLER AVATAR -->

                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-sm font-bold text-indigo-600"
                        >
                            {{ sellerInitial }}
                        </div>

                        <!-- HEADER TEXT -->

                        <div class="min-w-0 flex-1">
                            <p
                                class="text-[10px] font-semibold uppercase tracking-wide text-indigo-600 sm:text-xs"
                            >
                                Stay connected
                            </p>

                            <h1
                                class="mt-0.5 truncate text-xl font-bold tracking-tight text-gray-900 sm:mt-1 sm:text-2xl lg:text-3xl"
                            >
                                {{ sellerName }}
                            </h1>

                            <p
                                class="mt-1 hidden text-sm text-gray-500 sm:block"
                            >
                                Chat with the seller about your
                                product or order.
                            </p>
                        </div>
                    </div>

                    <!-- MOBILE DESCRIPTION -->

                    <p
                        class="mt-3 text-xs leading-5 text-gray-500 sm:hidden"
                    >
                        Chat with the seller about your product
                        or order.
                    </p>
                </div>

                <!-- ================================================== -->
                <!-- CONTENT -->
                <!-- ================================================== -->

                <div
                    class="mt-6 grid w-full min-w-0 gap-5 sm:mt-7 lg:grid-cols-[minmax(0,1fr)_320px] xl:grid-cols-[minmax(0,1fr)_340px]"
                >
                    <!-- ================================================== -->
                    <!-- CHAT -->
                    <!-- ================================================== -->

                    <section
                        class="min-w-0 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm"
                    >
                        <!-- SECTION HEADER -->

                        <div
                            class="border-b border-gray-100 px-4 py-3.5 sm:px-5 sm:py-4"
                        >
                            <div
                                class="flex min-w-0 items-center justify-between gap-3"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="text-xs font-semibold text-indigo-600"
                                    >
                                        Conversation
                                    </p>

                                    <h2
                                        class="mt-1 text-sm font-bold text-gray-900"
                                    >
                                        Messages
                                    </h2>
                                </div>

                                <span
                                    class="shrink-0 rounded-full bg-gray-50 px-2.5 py-1 text-[10px] font-semibold text-gray-500"
                                >
                                    {{ props.messages.length }}

                                    {{
                                        props.messages.length ===
                                        1
                                            ? 'message'
                                            : 'messages'
                                    }}
                                </span>
                            </div>
                        </div>

                        <!-- ================================================== -->
                        <!-- MESSAGE AREA -->
                        <!-- ================================================== -->

                        <div
                            ref="messagesContainer"
                            class="h-[360px] min-w-0 overflow-y-auto overscroll-contain bg-gray-50/50 px-3 py-5 sm:h-[450px] sm:px-6 sm:py-6 lg:h-[500px]"
                        >
                            <!-- EMPTY -->

                            <div
                                v-if="!props.messages.length"
                                class="flex h-full items-center justify-center"
                            >
                                <div
                                    class="max-w-xs px-4 text-center sm:max-w-sm"
                                >
                                    <div
                                        class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-500"
                                    >
                                        <svg
                                            class="h-6 w-6"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.7"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v7A2.5 2.5 0 0 1 17.5 15H11l-4.5 4V15h-.0A2.5 2.5 0 0 1 4 12.5v-7Z"
                                            />
                                        </svg>
                                    </div>

                                    <h3
                                        class="mt-4 text-sm font-semibold text-gray-900"
                                    >
                                        No messages yet
                                    </h3>

                                    <p
                                        class="mx-auto mt-1.5 max-w-sm text-xs leading-5 text-gray-500"
                                    >
                                        Start a conversation with
                                        {{ sellerName }}.
                                    </p>
                                </div>
                            </div>

                            <!-- ================================================== -->
                            <!-- MESSAGES -->
                            <!-- ================================================== -->

                            <div
                                v-else
                                class="space-y-4 sm:space-y-5"
                            >
                                <div
                                    v-for="message in props.messages"
                                    :key="message.id"
                                    class="flex min-w-0"
                                    :class="
                                        isMine(message)
                                            ? 'justify-end'
                                            : 'justify-start'
                                    "
                                >
                                    <div
                                        class="flex min-w-0 max-w-[90%] items-end gap-2 sm:max-w-[78%]"
                                        :class="
                                            isMine(message)
                                                ? 'flex-row-reverse'
                                                : ''
                                        "
                                    >
                                        <!-- SELLER AVATAR -->

                                        <div
                                            v-if="
                                                !isMine(
                                                    message
                                                )
                                            "
                                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-[9px] font-bold text-indigo-600 sm:h-8 sm:w-8 sm:rounded-xl sm:text-[10px]"
                                        >
                                            {{ sellerInitial }}
                                        </div>

                                        <!-- MESSAGE -->

                                        <div
                                            class="min-w-0 max-w-full"
                                        >
                                            <div
                                                class="break-words rounded-2xl px-3.5 py-2.5 text-xs leading-5 shadow-sm sm:px-4 sm:py-3 sm:text-sm sm:leading-6"
                                                :class="
                                                    isMine(
                                                        message
                                                    )
                                                        ? 'rounded-br-md bg-indigo-600 text-white'
                                                        : 'rounded-bl-md border border-gray-100 bg-white text-gray-700'
                                                "
                                            >
                                                <p
                                                    class="whitespace-pre-line break-words"
                                                >
                                                    {{
                                                        message.body
                                                    }}
                                                </p>
                                            </div>

                                            <!-- TIME -->

                                            <div
                                                class="mt-1 px-1 text-[9px] text-gray-400 sm:text-[10px]"
                                                :class="
                                                    isMine(
                                                        message
                                                    )
                                                        ? 'text-right'
                                                        : 'text-left'
                                                "
                                            >
                                                {{
                                                    formatTime(
                                                        message.created_at
                                                    )
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ================================================== -->
                        <!-- MESSAGE FORM -->
                        <!-- INSIDE THE CONVERSATION -->
                        <!-- ================================================== -->

                        <div
                            class="border-t border-gray-100 bg-white p-3 sm:p-4"
                        >
                            <form
                                @submit.prevent="submit"
                            >
                                <div
                                    class="min-w-0 overflow-hidden rounded-xl border border-gray-100 bg-gray-50 transition focus-within:border-indigo-300 focus-within:bg-white focus-within:ring-4 focus-within:ring-indigo-50"
                                >
                                    <!-- TEXTAREA -->

                                    <textarea
                                        v-model="form.body"
                                        rows="3"
                                        maxlength="2000"
                                        :disabled="form.processing"
                                        placeholder="Write a message..."
                                        class="block w-full resize-none border-0 bg-transparent px-3.5 py-3 text-xs leading-5 text-gray-900 outline-none placeholder:text-gray-400 focus:ring-0 disabled:cursor-not-allowed disabled:opacity-60 sm:px-4 sm:text-sm"
                                        @keydown="handleKeydown"
                                    ></textarea>

                                    <!-- ERROR -->

                                    <p
                                        v-if="form.errors.body"
                                        class="px-3.5 pb-2 text-[10px] font-medium text-red-600 sm:px-4"
                                    >
                                        {{ form.errors.body }}
                                    </p>

                                    <!-- BOTTOM -->

                                    <div
                                        class="flex flex-col gap-2 border-t border-gray-100 px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <!-- KEYBOARD HINT -->

                                        <span
                                            class="text-[9px] leading-4 text-gray-400 sm:text-[10px]"
                                        >
                                            <span
                                                class="font-medium text-gray-500"
                                            >
                                                Enter
                                            </span>
                                            to send ·
                                            <span
                                                class="font-medium text-gray-500"
                                            >
                                                Shift + Enter
                                            </span>
                                            for a new line
                                        </span>

                                        <!-- SEND BUTTON -->

                                        <button
                                            type="submit"
                                            :disabled="
                                                form.processing ||
                                                !form.body.trim()
                                            "
                                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-[10px] font-semibold text-white transition hover:bg-indigo-700 active:bg-indigo-800 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto sm:text-xs"
                                        >
                                            <!-- LOADING -->

                                            <svg
                                                v-if="
                                                    form.processing
                                                "
                                                class="h-3.5 w-3.5 animate-spin sm:h-4 sm:w-4"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                            >
                                                <circle
                                                    cx="12"
                                                    cy="12"
                                                    r="9"
                                                    class="opacity-25"
                                                    stroke="currentColor"
                                                    stroke-width="3"
                                                />

                                                <path
                                                    d="M21 12a9 9 0 0 1-9 9"
                                                    stroke="currentColor"
                                                    stroke-width="3"
                                                    stroke-linecap="round"
                                                />
                                            </svg>

                                            <!-- SEND ICON -->

                                            <svg
                                                v-else
                                                class="h-3.5 w-3.5 sm:h-4 sm:w-4"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
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

                                            {{
                                                form.processing
                                                    ? 'Sending...'
                                                    : 'Send message'
                                            }}
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </section>

                    <!-- ================================================== -->
                    <!-- RIGHT SIDEBAR -->
                    <!-- ================================================== -->

                    <aside class="min-w-0">
                        <!-- ================================================== -->
                        <!-- SELLER INFO -->
                        <!-- ================================================== -->

                        <div
                            class="min-w-0 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm sm:p-5"
                        >
                            <div>
                                <p
                                    class="text-xs font-semibold text-indigo-600"
                                >
                                    Conversation
                                </p>

                                <h2
                                    class="mt-1 text-sm font-bold text-gray-900"
                                >
                                    Seller information
                                </h2>
                            </div>

                            <!-- SELLER -->

                            <div
                                class="mt-4 flex min-w-0 items-center gap-3 sm:mt-5"
                            >
                                <div
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-sm font-bold text-indigo-600"
                                >
                                    {{ sellerInitial }}
                                </div>

                                <div class="min-w-0">
                                    <p
                                        class="truncate text-sm font-semibold text-gray-900"
                                    >
                                        {{ sellerName }}
                                    </p>

                                    <p
                                        class="mt-0.5 text-[11px] text-gray-400"
                                    >
                                        Seller
                                    </p>
                                </div>
                            </div>

                            <!-- PRODUCT -->

                            <div
                                v-if="product"
                                class="mt-4 border-t border-gray-100 pt-4 sm:mt-5 sm:pt-5"
                            >
                                <p
                                    class="text-[10px] font-semibold uppercase tracking-wide text-gray-400"
                                >
                                    Product
                                </p>

                                <div
                                    class="mt-3 flex min-w-0 items-center gap-3"
                                >
                                    <img
                                        v-if="
                                            product.image_path
                                        "
                                        :src="
                                            product.image_path
                                        "
                                        :alt="product.name"
                                        class="h-12 w-12 shrink-0 rounded-xl border border-gray-100 object-cover"
                                    />

                                    <div
                                        v-else
                                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gray-50 text-[10px] text-gray-400"
                                    >
                                        N/A
                                    </div>

                                    <p
                                        class="min-w-0 line-clamp-2 break-words text-xs font-semibold leading-5 text-gray-700"
                                    >
                                        {{ product.name }}
                                    </p>
                                </div>
                            </div>

                            <!-- ORDER -->

                            <div
                                v-if="order"
                                class="mt-4 border-t border-gray-100 pt-4 sm:mt-5 sm:pt-5"
                            >
                                <p
                                    class="text-[10px] font-semibold uppercase tracking-wide text-gray-400"
                                >
                                    Order
                                </p>

                                <div
                                    class="mt-3 flex min-w-0 items-center justify-between gap-3"
                                >
                                    <span
                                        class="min-w-0 truncate text-xs font-semibold text-gray-800"
                                    >
                                        #{{ order.order_number }}
                                    </span>

                                    <span
                                        class="shrink-0 rounded-full bg-indigo-50 px-2.5 py-1 text-[10px] font-semibold capitalize text-indigo-600"
                                    >
                                        {{ order.status }}
                                    </span>
                                </div>
                            </div>

                            <!-- STARTED -->

                            <div
                                v-if="
                                    conversation.created_at
                                "
                                class="mt-4 border-t border-gray-100 pt-4 sm:mt-5 sm:pt-5"
                            >
                                <p
                                    class="text-[10px] font-semibold uppercase tracking-wide text-gray-400"
                                >
                                    Conversation started
                                </p>

                                <p
                                    class="mt-1.5 text-xs text-gray-500"
                                >
                                    {{
                                        formatDate(
                                            conversation.created_at
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- ================================================== -->
                        <!-- HELP CARD -->
                        <!-- ================================================== -->

                        <div
                            class="mt-4 min-w-0 rounded-2xl border border-indigo-100 bg-indigo-50 p-4 sm:p-5"
                        >
                            <div
                                class="flex min-w-0 gap-3"
                            >
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-indigo-600"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.7"
                                    >
                                        <circle
                                            cx="12"
                                            cy="12"
                                            r="9"
                                        />

                                        <path
                                            stroke-linecap="round"
                                            d="M9.5 9a2.5 2.5 0 1 1 4.4 1.6c-.9 1-1.9 1.3-1.9 2.4"
                                        />

                                        <path
                                            stroke-linecap="round"
                                            d="M12 16h.01"
                                        />
                                    </svg>
                                </div>

                                <div
                                    class="min-w-0"
                                >
                                    <h3
                                        class="text-xs font-bold text-indigo-900"
                                    >
                                        Need help?
                                    </h3>

                                    <p
                                        class="mt-1 text-[10px] leading-4 text-indigo-700"
                                    >
                                        Keep your conversation
                                        related to your product
                                        or order so the seller
                                        can assist you faster.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </main>
    </BuyerLayout>
</template>
```
