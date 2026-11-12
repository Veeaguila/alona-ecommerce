<script setup>
import {
    computed,
    nextTick,
    onMounted,
    onUnmounted,
    ref,
    watch,
} from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'

const page = usePage()

const user = computed(() => page.props.auth?.user ?? null)
const isBuyer = computed(() => user.value && (user.value.usertype === 'buyer' || !user.value.usertype))

// Window open / minimized states
const isOpen = ref(false)
const isMinimized = ref(false)

// Data states
const conversations = ref([])
const selectedConversation = ref(null)
const messages = ref([])
const isLoading = ref(false)
const isSending = ref(false)
const searchQuery = ref('')
const filterType = ref('all') // 'all' or 'unread'
const showFilterDropdown = ref(false)

// Message input & timeline
const messageText = ref('')
const messageContainer = ref(null)
const showEmojiPicker = ref(false)
const fileInput = ref(null)
const selectedAttachment = ref(null)

const handleFileSelect = e => {
    const file = e.target.files?.[0]
    if (file) {
        selectedAttachment.value = file
    }
}

const clearAttachment = () => {
    selectedAttachment.value = null
    if (fileInput.value) {
        fileInput.value.value = ''
    }
}

const quickReplies = [
    'Is this item available?',
    'When will you ship my order?',
    'Can I get more details?',
    'Thank you so much!',
]

const emojis = ['😊', '👍', '❤️', '🙏', '📦', '✨', '👋', '💯']

let pollInterval = null

const getCsrfToken = () => {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}

// Format currency
const formatPrice = value => {
    const num = Number(value || 0)
    return '₱' + num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// Format time & date
const formatTime = timestamp => {
    if (!timestamp) return ''
    const date = new Date(timestamp)
    if (Number.isNaN(date.getTime())) return ''
    return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
}

const formatDateLabel = timestamp => {
    if (!timestamp) return ''
    const date = new Date(timestamp)
    if (Number.isNaN(date.getTime())) return ''
    const now = new Date()
    if (date.toDateString() === now.toDateString()) {
        return 'Today'
    }
    const yesterday = new Date(now)
    yesterday.setDate(now.getDate() - 1)
    if (date.toDateString() === yesterday.toDateString()) {
        return 'Yesterday'
    }
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

const formatPreviewDate = timestamp => {
    if (!timestamp) return ''
    const date = new Date(timestamp)
    if (Number.isNaN(date.getTime())) return ''
    const now = new Date()
    if (date.toDateString() === now.toDateString()) {
        return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })
    }
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

// Conversation helpers
const getSellerName = conv => {
    return conv?.seller?.store_name || conv?.seller?.name || 'Seller'
}

const getSellerInitial = conv => {
    return getSellerName(conv).charAt(0).toUpperCase()
}

const getSellerAvatar = conv => {
    const path = conv?.seller?.logo || conv?.seller?.avatar || null
    if (!path) return null
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/storage/')) {
        return path
    }
    return `/storage/${path.replace(/^\/+/, '')}`
}

const getProductImage = conv => {
    const path = conv?.product?.image_path || null
    if (!path) return null
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/storage/')) {
        return path
    }
    return `/storage/${path.replace(/^\/+/, '')}`
}

const isImageFile = msg => {
    if (msg?.attachment_type === 'image') return true
    const ext = (msg?.attachment_name || msg?.attachment_path || '').split('.').pop().toLowerCase()
    return ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext)
}

const getAttachmentUrl = msg => {
    if (msg?.attachment_url) return msg.attachment_url
    if (!msg?.attachment_path) return ''
    if (msg.attachment_path.startsWith('http://') || msg.attachment_path.startsWith('https://') || msg.attachment_path.startsWith('/storage/')) {
        return msg.attachment_path
    }
    return `/storage/${msg.attachment_path.replace(/^\/+/, '')}`
}

// Unread counts
const totalUnreadCount = computed(() => {
    return conversations.value.reduce((sum, c) => sum + Number(c.unread_count || 0), 0)
})

// Filtered conversation list
const filteredConversations = computed(() => {
    let list = conversations.value

    if (filterType.value === 'unread') {
        list = list.filter(c => Number(c.unread_count || 0) > 0)
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim()
        list = list.filter(c => {
            const name = getSellerName(c).toLowerCase()
            const lastMsg = (c.messages?.[0]?.body || '').toLowerCase()
            return name.includes(q) || lastMsg.includes(q)
        })
    }

    return list
})

// Auto scroll timeline to bottom
const scrollToBottom = async () => {
    await nextTick()
    if (messageContainer.value) {
        messageContainer.value.scrollTop = messageContainer.value.scrollHeight
    }
}

// Fetch conversations
const fetchConversations = async (keepSelection = true) => {
    if (!isBuyer.value) return

    try {
        const response = await fetch('/buyer/conversations', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })

        if (!response.ok) return

        const data = await response.json()
        conversations.value = data.conversations || []

        if (keepSelection && selectedConversation.value) {
            const found = conversations.value.find(c => c.id === selectedConversation.value.id)
            if (found) {
                selectedConversation.value = { ...found, ...selectedConversation.value }
            }
        } else if (!selectedConversation.value && conversations.value.length > 0) {
            selectConversation(conversations.value[0], false)
        }
    } catch (err) {
        // Silent fail for network dropouts
    }
}

// Select and load conversation
const selectConversation = async (conv, markAsRead = true) => {
    if (!conv) return
    selectedConversation.value = conv

    try {
        isLoading.value = true
        const response = await fetch(`/buyer/conversations/${conv.id}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })

        if (response.ok) {
            const data = await response.json()
            if (data.conversation) {
                selectedConversation.value = data.conversation
            }
            messages.value = data.messages || []

            if (markAsRead) {
                const target = conversations.value.find(c => c.id === conv.id)
                if (target) {
                    target.unread_count = 0
                }
            }

            scrollToBottom()
        }
    } catch (err) {
        // Handle error
    } finally {
        isLoading.value = false
    }
}

// Refresh active conversation messages in background
const refreshActiveConversation = async () => {
    if (!selectedConversation.value || !isOpen.value || isMinimized.value) return

    try {
        const response = await fetch(`/buyer/conversations/${selectedConversation.value.id}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })

        if (response.ok) {
            const data = await response.json()
            const newMessages = data.messages || []
            if (newMessages.length !== messages.value.length) {
                messages.value = newMessages
                scrollToBottom()
            }
        }
    } catch (err) {
        // Silent fail
    }
}

// Send message
const sendMessage = async () => {
    const text = messageText.value.trim()
    const file = selectedAttachment.value
    if ((!text && !file) || !selectedConversation.value || isSending.value) return

    isSending.value = true
    const currentConvId = selectedConversation.value.id

    try {
        let response
        if (file) {
            const formData = new FormData()
            if (text) formData.append('body', text)
            formData.append('attachment', file)

            response = await fetch(`/buyer/conversations/${currentConvId}`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            })
        } else {
            response = await fetch(`/buyer/conversations/${currentConvId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ body: text }),
            })
        }

        if (response.ok) {
            const data = await response.json()
            if (data.message) {
                messages.value.push(data.message)
            }
            messageText.value = ''
            clearAttachment()
            showEmojiPicker.value = false
            scrollToBottom()

            const target = conversations.value.find(c => c.id === currentConvId)
            if (target) {
                target.messages = [data.message]
                target.last_message_at = new Date().toISOString()
            }
        }
    } catch (err) {
        // Error handling
    } finally {
        isSending.value = false
    }
}

const sendQuickReply = reply => {
    messageText.value = reply
    sendMessage()
}

const appendEmoji = emoji => {
    messageText.value += emoji
    showEmojiPicker.value = false
}

// Window controls
const toggleOpen = () => {
    if (isMinimized.value) {
        isMinimized.value = false
        isOpen.value = true
    } else {
        isOpen.value = !isOpen.value
    }

    if (isOpen.value) {
        fetchConversations()
    }
}

const minimize = () => {
    isMinimized.value = true
    isOpen.value = false
}

const close = () => {
    isOpen.value = false
    isMinimized.value = false
}

// Handle keydown in textarea
const handleKeydown = e => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault()
        sendMessage()
    }
}

// Handle global custom event for opening chat
const handleGlobalChatOpen = e => {
    isOpen.value = true
    isMinimized.value = false

    const detail = e.detail || {}
    fetchConversations().then(async () => {
        if (detail.conversationId) {
            const found = conversations.value.find(c => c.id === Number(detail.conversationId))
            if (found) {
                selectConversation(found)
            }
        } else if (detail.sellerId) {
            const found = conversations.value.find(c => Number(c.seller_id) === Number(detail.sellerId))
            if (found) {
                if (detail.productId && Number(found.product_id) !== Number(detail.productId)) {
                    try {
                        await fetch(`/buyer/conversations/start/${detail.productId}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': getCsrfToken(),
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        })
                        await fetchConversations()
                        const updated = conversations.value.find(c => c.id === found.id)
                        if (updated) {
                            selectConversation(updated)
                            return
                        }
                    } catch (err) {}
                }
                selectConversation(found)
            } else {
                // No conversation with this seller yet - start one!
                try {
                    const endpoint = detail.productId
                        ? `/buyer/conversations/start/${detail.productId}`
                        : `/buyer/conversations/start-seller/${detail.sellerId}`

                    const res = await fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken(),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    })
                    if (res.ok) {
                        const data = await res.json()
                        await fetchConversations()
                        if (data.conversation_id) {
                            const newConv = conversations.value.find(c => c.id === Number(data.conversation_id))
                            if (newConv) selectConversation(newConv)
                        }
                    }
                } catch (err) {}
            }
        }
    })
}

onMounted(() => {
    if (isBuyer.value) {
        fetchConversations(false)

        pollInterval = setInterval(() => {
            if (isOpen.value && !isMinimized.value) {
                refreshActiveConversation()
                fetchConversations(true)
            } else {
                fetchConversations(false)
            }
        }, 12000)

        window.addEventListener('open-buyer-chat', handleGlobalChatOpen)
    }
})

onUnmounted(() => {
    if (pollInterval) {
        clearInterval(pollInterval)
    }
    window.removeEventListener('open-buyer-chat', handleGlobalChatOpen)
})
</script>

<template>
    <!-- FLOATING CHAT ROOT -->
    <div v-if="isBuyer" class="fixed z-50 bottom-4 right-4 sm:right-6 font-sans">
        <!-- 1. FLOATING CHAT BUTTON (Circular FAB Icon Only) -->
        <button
            v-if="!isOpen"
            type="button"
            @click="toggleOpen"
            class="group relative flex items-center justify-center w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-gradient-to-tr from-[#087F8C] to-[#0A96A6] hover:from-[#066B76] hover:to-[#087F8C] text-white shadow-[0_8px_30px_rgba(8,127,140,0.38)] hover:shadow-[0_12px_36px_rgba(8,127,140,0.48)] transition-all duration-300 hover:-translate-y-1 hover:scale-105 active:scale-95 cursor-pointer"
            aria-label="Open Alona Chat"
            title="Chat with sellers"
        >
            <!-- Large Chat Bubble Icon with Dots -->
            <svg
                class="w-7 h-7 sm:w-8 sm:h-8 text-white transition-transform duration-200 group-hover:scale-110"
                viewBox="0 0 24 24"
                fill="currentColor"
            >
                <path
                    d="M12 2C6.477 2 2 6.141 2 11.25c0 2.827 1.397 5.372 3.633 7.027a.75.75 0 0 1 .28.618l-.297 2.378a.75.75 0 0 0 1.05.772l3.076-1.428a.75.75 0 0 1 .452-.054c.582.115 1.183.187 1.806.187 5.523 0 10-4.141 10-9.25S17.523 2 12 2z"
                />
                <circle cx="8" cy="11.25" r="1.3" fill="#087F8C" />
                <circle cx="12" cy="11.25" r="1.3" fill="#087F8C" />
                <circle cx="16" cy="11.25" r="1.3" fill="#087F8C" />
            </svg>

            <!-- Unread Badge on Icon -->
            <span
                v-if="totalUnreadCount > 0"
                class="absolute -top-1 -right-1 flex items-center justify-center min-w-[22px] h-[22px] px-1 text-[11px] font-black leading-none text-white bg-[#EF4444] border-2 border-white rounded-full shadow-md animate-pulse"
            >
                {{ totalUnreadCount > 99 ? '99+' : totalUnreadCount }}
            </span>
        </button>

        <!-- 2. EXPANDED CHAT DOCK (Shopee Layout + Alona Branding) -->
        <div
            v-else
            class="w-[740px] max-w-[calc(100vw-2rem)] h-[550px] max-h-[calc(100vh-2rem)] bg-white rounded-2xl shadow-[0_16px_50px_rgba(15,23,42,0.18)] border border-[#E5E7EB] flex flex-col overflow-hidden transition-all duration-200"
        >
            <!-- TOP BAR (Alona Style) -->
            <div
                class="h-12 bg-white border-b border-[#E5E7EB] px-4 flex items-center justify-between select-none shrink-0"
            >
                <div class="flex items-center gap-2">
                    <!-- Alona Chat Icon -->
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]">
                        <svg
                            class="w-4 h-4"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                        >
                            <path
                                d="M12 2C6.477 2 2 6.141 2 11.25c0 2.827 1.397 5.372 3.633 7.027a.75.75 0 0 1 .28.618l-.297 2.378a.75.75 0 0 0 1.05.772l3.076-1.428a.75.75 0 0 1 .452-.054c.582.115 1.183.187 1.806.187 5.523 0 10-4.141 10-9.25S17.523 2 12 2z"
                            />
                            <circle cx="8" cy="11.25" r="1.2" fill="#E8F7F6" />
                            <circle cx="12" cy="11.25" r="1.2" fill="#E8F7F6" />
                            <circle cx="16" cy="11.25" r="1.2" fill="#E8F7F6" />
                        </svg>
                    </div>

                    <h2 class="text-sm font-extrabold text-[#1F2937] flex items-center gap-1.5">
                        Alona Chat
                        <span
                            v-if="totalUnreadCount > 0"
                            class="text-xs font-bold text-[#FF6B35]"
                        >
                            ({{ totalUnreadCount }})
                        </span>
                    </h2>
                </div>

                <!-- Window Control Buttons -->
                <div class="flex items-center gap-1 text-[#64748B]">
                    <!-- Minimize (—) -->
                    <button
                        type="button"
                        @click="minimize"
                        title="Minimize"
                        class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-[#F8FAF9] hover:text-[#1F2937] transition cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                    </button>

                    <!-- Close (✕) -->
                    <button
                        type="button"
                        @click="close"
                        title="Close"
                        class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-[#F8FAF9] hover:text-red-600 transition cursor-pointer"
                    >
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- TWO-COLUMN WORKSPACE -->
            <div class="flex-1 flex overflow-hidden">
                <!-- ================= LEFT COLUMN: CONVERSATION LIST ================= -->
                <div class="w-64 sm:w-72 border-r border-[#E5E7EB] flex flex-col bg-white shrink-0">
                    <!-- Search & Filter Controls -->
                    <div class="p-2.5 border-b border-[#F1F5F9] space-y-2">
                        <!-- Search Name Input -->
                        <div class="relative">
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search name..."
                                class="w-full pl-8 pr-7 py-1.5 bg-[#F8FAF9] border border-[#E5E7EB] focus:border-[#087F8C] focus:bg-white rounded-xl text-xs text-[#1F2937] placeholder-[#94A3B8] focus:outline-none transition"
                            />
                            <svg
                                class="w-3.5 h-3.5 text-[#94A3B8] absolute left-2.5 top-2.5 pointer-events-none"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.2"
                            >
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            </svg>
                            <button
                                v-if="searchQuery"
                                @click="searchQuery = ''"
                                type="button"
                                class="absolute right-2 top-2 text-[#94A3B8] hover:text-[#1F2937] text-xs cursor-pointer"
                            >
                                ✕
                            </button>
                        </div>

                        <!-- Filter Tab / Dropdown Row -->
                        <div class="flex items-center justify-between text-xs px-0.5">
                            <div class="relative">
                                <button
                                    type="button"
                                    @click="showFilterDropdown = !showFilterDropdown"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 bg-[#F8FAF9] hover:bg-[#E8F7F6] text-[#087F8C] border border-[#D5EFEF] rounded-lg font-bold text-[11px] transition cursor-pointer"
                                >
                                    <span>{{ filterType === 'unread' ? 'Unread' : 'All' }}</span>
                                    <svg class="w-3 h-3 text-[#087F8C]" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M7 10l5 5 5-5z" />
                                    </svg>
                                </button>

                                <!-- Dropdown Menu -->
                                <div
                                    v-if="showFilterDropdown"
                                    class="absolute left-0 mt-1 w-28 bg-white border border-[#E5E7EB] rounded-xl shadow-lg z-20 py-1 text-xs"
                                >
                                    <button
                                        type="button"
                                        @click="filterType = 'all'; showFilterDropdown = false"
                                        :class="[
                                            'w-full text-left px-3 py-1.5 transition flex items-center justify-between',
                                            filterType === 'all' ? 'font-bold text-[#087F8C] bg-[#E8F7F6]' : 'text-[#1F2937] hover:bg-[#F8FAF9]'
                                        ]"
                                    >
                                        <span>All</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="filterType = 'unread'; showFilterDropdown = false"
                                        :class="[
                                            'w-full text-left px-3 py-1.5 transition flex items-center justify-between',
                                            filterType === 'unread' ? 'font-bold text-[#087F8C] bg-[#E8F7F6]' : 'text-[#1F2937] hover:bg-[#F8FAF9]'
                                        ]"
                                    >
                                        <span>Unread</span>
                                        <span v-if="totalUnreadCount > 0" class="text-[10px] bg-[#FF6B35] text-white px-1.5 rounded-full font-bold">
                                            {{ totalUnreadCount }}
                                        </span>
                                    </button>
                                </div>
                            </div>

                            <span class="text-[10px] font-semibold text-[#94A3B8]">
                                {{ filteredConversations.length }} {{ filteredConversations.length === 1 ? 'chat' : 'chats' }}
                            </span>
                        </div>
                    </div>

                    <!-- Conversation Scrollable List -->
                    <div class="flex-1 overflow-y-auto divide-y divide-[#F1F5F9]">
                        <template v-if="filteredConversations.length > 0">
                            <button
                                v-for="conv in filteredConversations"
                                :key="conv.id"
                                type="button"
                                @click="selectConversation(conv)"
                                :class="[
                                    'w-full text-left p-3 flex items-start gap-2.5 transition relative cursor-pointer',
                                    selectedConversation?.id === conv.id
                                        ? 'bg-[#E8F7F6]'
                                        : 'hover:bg-[#F8FAF9] bg-white'
                                ]"
                            >
                                <!-- Left indicator for active chat -->
                                <div
                                    v-if="selectedConversation?.id === conv.id"
                                    class="absolute left-0 top-0 bottom-0 w-1 bg-[#087F8C]"
                                />

                                <!-- Store Avatar -->
                                <div class="relative shrink-0">
                                    <img
                                        v-if="getSellerAvatar(conv)"
                                        :src="getSellerAvatar(conv)"
                                        :alt="getSellerName(conv)"
                                        class="w-10 h-10 rounded-full object-cover border border-[#E5E7EB]"
                                    />
                                    <div
                                        v-else
                                        class="w-10 h-10 rounded-full bg-[#E8F7F6] text-[#087F8C] font-black text-sm flex items-center justify-center border border-[#D5EFEF]"
                                    >
                                        {{ getSellerInitial(conv) }}
                                    </div>

                                    <!-- Online dot -->
                                    <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-emerald-500 ring-2 ring-white" />
                                </div>

                                <!-- Text Content -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-1">
                                        <h4 class="text-xs font-bold text-[#1F2937] truncate">
                                            {{ getSellerName(conv) }}
                                        </h4>
                                        <span class="text-[10px] text-[#94A3B8] shrink-0 font-medium">
                                            {{ formatPreviewDate(conv.last_message_at || conv.messages?.[0]?.created_at) }}
                                        </span>
                                    </div>

                                    <div class="flex items-center justify-between gap-1 mt-1">
                                        <p class="text-[11px] text-[#64748B] truncate">
                                            {{ conv.messages?.[0]?.body || 'Start chatting...' }}
                                        </p>

                                        <!-- Unread Badge -->
                                        <span
                                            v-if="Number(conv.unread_count || 0) > 0"
                                            class="shrink-0 flex items-center justify-center min-w-[16px] h-4 px-1 text-[10px] font-black leading-none text-white bg-[#FF6B35] rounded-full"
                                        >
                                            {{ conv.unread_count }}
                                        </span>
                                    </div>
                                </div>
                            </button>
                        </template>

                        <!-- Empty State -->
                        <div
                            v-else
                            class="p-6 text-center text-[#94A3B8] text-xs flex flex-col items-center justify-center h-full gap-2"
                        >
                            <div class="w-10 h-10 rounded-xl bg-[#E8F7F6] text-[#087F8C] flex items-center justify-center">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h6m8-1a8.5 8.5 0 0 1-8.5 8.5 8.5 8.5 0 0 1-4.17-1.09L4 20l1.59-3.33A8.5 8.5 0 1 1 21 11Z" />
                                </svg>
                            </div>
                            <p>No conversations found</p>
                        </div>
                    </div>
                </div>

                <!-- ================= RIGHT COLUMN: CHAT WINDOW ================= -->
                <div class="flex-1 flex flex-col bg-[#F8FAF9] overflow-hidden">
                    <template v-if="selectedConversation">
                        <!-- Chat Header -->
                        <div
                            class="h-12 bg-white border-b border-[#E5E7EB] px-4 flex items-center justify-between shrink-0"
                        >
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="min-w-0">
                                    <h3 class="text-xs font-extrabold text-[#1F2937] truncate flex items-center gap-1.5">
                                        {{ getSellerName(selectedConversation) }}
                                    </h3>
                                    <div class="flex items-center gap-1 text-[10px] text-emerald-600 font-semibold">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                                        <span>Active today</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Store link -->
                            <Link
                                v-if="selectedConversation.seller_id"
                                :href="`/buyer/store/${selectedConversation.seller_id}`"
                                class="inline-flex items-center gap-1 px-3 py-1 text-[11px] font-bold text-[#087F8C] bg-[#E8F7F6] hover:bg-[#087F8C] hover:text-white rounded-lg transition cursor-pointer"
                            >
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                                    <polyline points="9 22 9 12 15 12 15 22" />
                                </svg>
                                <span>Visit Store</span>
                            </Link>
                        </div>

                        <!-- Chat Timeline -->
                        <div
                            ref="messageContainer"
                            class="flex-1 overflow-y-auto p-4 space-y-3"
                        >
                            <!-- Date divider -->
                            <div class="flex items-center justify-center my-1">
                                <span class="px-3 py-0.5 bg-white border border-[#E5E7EB] text-[#64748B] rounded-full text-[10px] font-bold shadow-xs">
                                    {{ formatDateLabel(messages[0]?.created_at || selectedConversation.created_at) }}
                                </span>
                            </div>

                            <!-- Pinned Product Card (Alona Branded) -->
                            <div
                                v-if="selectedConversation.product"
                                class="bg-white rounded-xl p-2.5 border border-[#E5E7EB] shadow-xs flex items-center gap-3 transition hover:border-[#087F8C]"
                            >
                                <img
                                    v-if="getProductImage(selectedConversation)"
                                    :src="getProductImage(selectedConversation)"
                                    :alt="selectedConversation.product.name"
                                    class="w-12 h-12 rounded-lg object-cover border border-[#F1F5F9] shrink-0"
                                />
                                <div
                                    v-else
                                    class="w-12 h-12 rounded-lg bg-[#E8F7F6] text-[#087F8C] flex items-center justify-center shrink-0"
                                >
                                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="3" width="18" height="18" rx="2" />
                                        <circle cx="8.5" cy="8.5" r="1.5" />
                                        <path d="M21 15l-5-5L5 21" />
                                    </svg>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-bold text-[#1F2937] line-clamp-1">
                                        {{ selectedConversation.product.name }}
                                    </p>
                                    <p class="text-xs font-extrabold text-[#087F8C] mt-0.5">
                                        {{ formatPrice(selectedConversation.product.price) }}
                                    </p>
                                </div>

                                <Link
                                    :href="`/buyer/product/${selectedConversation.product.id}`"
                                    class="shrink-0 px-3 py-1 text-[11px] font-bold text-[#087F8C] bg-[#E8F7F6] hover:bg-[#087F8C] hover:text-white rounded-lg transition cursor-pointer"
                                >
                                    View Product
                                </Link>
                            </div>

                            <!-- Messages Loop -->
                            <template v-if="messages.length > 0">
                                <div
                                    v-for="msg in messages"
                                    :key="msg.id"
                                    :class="[
                                        'flex flex-col',
                                        Number(msg.sender_id) === Number(user?.id) ? 'items-end' : 'items-start'
                                    ]"
                                >
                                    <!-- Bubble Container -->
                                    <div
                                        class="flex items-end gap-1.5 max-w-[80%]"
                                        :class="Number(msg.sender_id) === Number(user?.id) ? 'flex-row-reverse' : 'flex-row'"
                                    >
                                        <!-- Seller avatar next to their bubble -->
                                        <div
                                            v-if="Number(msg.sender_id) !== Number(user?.id)"
                                            class="shrink-0 mb-1"
                                        >
                                            <div
                                                v-if="getSellerAvatar(selectedConversation)"
                                                class="w-6 h-6 rounded-full overflow-hidden border border-[#E5E7EB]"
                                            >
                                                <img
                                                    :src="getSellerAvatar(selectedConversation)"
                                                    :alt="getSellerName(selectedConversation)"
                                                    class="w-full h-full object-cover"
                                                />
                                            </div>
                                            <div
                                                v-else
                                                class="w-6 h-6 rounded-full bg-[#E8F7F6] text-[#087F8C] font-bold text-[10px] flex items-center justify-center border border-[#D5EFEF]"
                                            >
                                                {{ getSellerInitial(selectedConversation) }}
                                            </div>
                                        </div>

                                        <!-- Speech Bubble (Alona Colors: Buyer Soft Teal #E8F7F6, Seller Crisp White) -->
                                        <div
                                            :class="[
                                                'px-3.5 py-2 rounded-2xl text-xs break-words shadow-xs leading-relaxed',
                                                Number(msg.sender_id) === Number(user?.id)
                                                    ? 'bg-[#E8F7F6] text-[#0A454C] border border-[#C5ECE8] rounded-tr-xs font-medium'
                                                    : 'bg-white text-[#1F2937] border border-[#E5E7EB] rounded-tl-xs'
                                            ]"
                                        >
                                            <div v-if="msg.body" class="whitespace-pre-wrap">{{ msg.body }}</div>

                                            <!-- Media Attachment -->
                                            <div v-if="msg.attachment_path" :class="{ 'mt-1.5': msg.body }">
                                                <a
                                                    v-if="isImageFile(msg)"
                                                    :href="getAttachmentUrl(msg)"
                                                    target="_blank"
                                                    class="block rounded-lg overflow-hidden border border-black/10 hover:opacity-90 transition max-w-xs"
                                                >
                                                    <img
                                                        :src="getAttachmentUrl(msg)"
                                                        :alt="msg.attachment_name || 'Attachment'"
                                                        class="max-h-48 w-full object-cover rounded-lg"
                                                    />
                                                </a>
                                                <a
                                                    v-else
                                                    :href="getAttachmentUrl(msg)"
                                                    target="_blank"
                                                    class="flex items-center gap-2 p-2 rounded-lg bg-black/5 hover:bg-black/10 transition text-xs font-semibold"
                                                >
                                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                                        <polyline points="14 2 14 8 20 8"></polyline>
                                                    </svg>
                                                    <span class="truncate">{{ msg.attachment_name || 'Download file' }}</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Timestamp below bubble -->
                                    <span
                                        :class="[
                                            'text-[9px] text-[#94A3B8] font-medium mt-1 px-1',
                                            Number(msg.sender_id) === Number(user?.id) ? 'text-right mr-1' : 'ml-8'
                                        ]"
                                    >
                                        {{ formatTime(msg.created_at) }}
                                    </span>
                                </div>
                            </template>

                            <div v-else class="text-center py-8 text-xs text-[#94A3B8]">
                                Send a message to start chatting with {{ getSellerName(selectedConversation) }}
                            </div>
                        </div>

                        <!-- Chat Input Toolbar & Area -->
                        <div class="bg-white border-t border-[#E5E7EB] p-2.5 flex flex-col gap-2 shrink-0">
                            <!-- Quick action strip -->
                            <div class="flex items-center justify-between gap-2 px-1 relative">
                                <div class="flex items-center gap-2">
                                    <!-- Hidden Attachment Input -->
                                    <input
                                        ref="fileInput"
                                        type="file"
                                        class="hidden"
                                        accept="image/*,application/pdf"
                                        @change="handleFileSelect"
                                    />

                                    <!-- Attachment Button -->
                                    <button
                                        type="button"
                                        @click="fileInput?.click()"
                                        title="Attach file or image"
                                        class="w-6 h-6 flex items-center justify-center text-[#64748B] hover:text-[#087F8C] transition cursor-pointer"
                                    >
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48" />
                                        </svg>
                                    </button>

                                    <!-- Emoji Button -->
                                    <button
                                        type="button"
                                        @click="showEmojiPicker = !showEmojiPicker"
                                        title="Add emoji"
                                        class="w-6 h-6 flex items-center justify-center text-[#64748B] hover:text-[#087F8C] transition cursor-pointer"
                                    >
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10" />
                                            <path d="M8 14s1.5 2 4 2 4-2 4-2" />
                                            <line x1="9" y1="9" x2="9.01" y2="9" />
                                            <line x1="15" y1="9" x2="15.01" y2="9" />
                                        </svg>
                                    </button>

                                    <!-- Quick Reply Chips (Alona Themed) -->
                                    <div class="hidden sm:flex items-center gap-1.5 overflow-x-auto no-scrollbar">
                                        <button
                                            v-for="reply in quickReplies"
                                            :key="reply"
                                            type="button"
                                            @click="sendQuickReply(reply)"
                                            class="text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-[#F8FAF9] hover:bg-[#E8F7F6] hover:text-[#087F8C] border border-[#E5E7EB] text-[#64748B] transition truncate max-w-[150px] cursor-pointer"
                                        >
                                            {{ reply }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Emoji Popover -->
                                <div
                                    v-if="showEmojiPicker"
                                    class="absolute left-0 bottom-8 bg-white border border-[#E5E7EB] rounded-xl shadow-lg p-2 flex gap-1 z-30"
                                >
                                    <button
                                        v-for="e in emojis"
                                        :key="e"
                                        type="button"
                                        @click="appendEmoji(e)"
                                        class="text-base hover:scale-125 transition p-1 cursor-pointer"
                                    >
                                        {{ e }}
                                    </button>
                                </div>
                            </div>

                            <!-- Selected Attachment Preview Chip -->
                            <div
                                v-if="selectedAttachment"
                                class="flex items-center justify-between bg-[#E8F7F6] border border-[#C5ECE8] rounded-xl px-3 py-1.5 text-xs text-[#087F8C] mx-1"
                            >
                                <div class="flex items-center gap-2 truncate">
                                    <svg class="w-4 h-4 shrink-0 text-[#087F8C]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48" />
                                    </svg>
                                    <span class="font-bold truncate">{{ selectedAttachment.name }}</span>
                                    <span class="text-[10px] text-[#64748B]">({{ (selectedAttachment.size / 1024).toFixed(1) }} KB)</span>
                                </div>
                                <button
                                    type="button"
                                    @click="clearAttachment"
                                    title="Remove attachment"
                                    class="text-[#64748B] hover:text-red-500 font-bold ml-2 cursor-pointer"
                                >
                                    ✕
                                </button>
                            </div>

                            <!-- Text Input and Send Row -->
                            <div class="flex items-end gap-2">
                                <textarea
                                    v-model="messageText"
                                    @keydown="handleKeydown"
                                    rows="1"
                                    placeholder="Type a message..."
                                    class="flex-1 resize-none bg-[#F8FAF9] border border-[#E5E7EB] focus:border-[#087F8C] focus:bg-white rounded-xl px-3 py-2 text-xs text-[#1F2937] placeholder-[#94A3B8] focus:outline-none transition max-h-24 min-h-[36px]"
                                />

                                <!-- Alona Teal Send Button -->
                                <button
                                    type="button"
                                    @click="sendMessage"
                                    :disabled="(!messageText.trim() && !selectedAttachment) || isSending"
                                    class="w-9 h-9 rounded-xl bg-[#087F8C] hover:bg-[#065A63] disabled:opacity-40 disabled:hover:bg-[#087F8C] text-white flex items-center justify-center transition shadow-xs shrink-0 cursor-pointer"
                                    title="Send Message"
                                >
                                    <svg
                                        v-if="!isSending"
                                        class="w-4 h-4 translate-x-0.5"
                                        viewBox="0 0 24 24"
                                        fill="currentColor"
                                    >
                                        <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z" />
                                    </svg>
                                    <svg
                                        v-else
                                        class="w-4 h-4 animate-spin"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2.5"
                                    >
                                        <circle cx="12" cy="12" r="10" stroke-opacity="0.25" />
                                        <path d="M12 2a10 10 0 0 1 10 10" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </template>

                    <!-- No conversation selected empty placeholder -->
                    <div
                        v-else
                        class="flex-1 flex flex-col items-center justify-center p-8 text-center text-[#94A3B8] gap-3"
                    >
                        <div class="w-14 h-14 rounded-2xl bg-[#E8F7F6] text-[#087F8C] flex items-center justify-center border border-[#D5EFEF]">
                            <svg class="w-7 h-7" viewBox="0 0 24 24" fill="currentColor">
                                <path
                                    d="M12 2C6.477 2 2 6.141 2 11.25c0 2.827 1.397 5.372 3.633 7.027a.75.75 0 0 1 .28.618l-.297 2.378a.75.75 0 0 0 1.05.772l3.076-1.428a.75.75 0 0 1 .452-.054c.582.115 1.183.187 1.806.187 5.523 0 10-4.141 10-9.25S17.523 2 12 2z"
                                />
                                <circle cx="8" cy="11.25" r="1.2" fill="#E8F7F6" />
                                <circle cx="12" cy="11.25" r="1.2" fill="#E8F7F6" />
                                <circle cx="16" cy="11.25" r="1.2" fill="#E8F7F6" />
                            </svg>
                        </div>
                        <h4 class="text-sm font-extrabold text-[#1F2937]">Alona Messages</h4>
                        <p class="text-xs text-[#64748B] max-w-xs leading-relaxed">
                            Select a chat from the left or chat with a store on any product page.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
