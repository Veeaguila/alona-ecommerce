<script setup>
import { computed, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'
import GuestLayout from '@/Layouts/GuestLayout.vue'

const props = defineProps({
    isGuest: {
        type: Boolean,
        default: false,
    },
})

const layoutComponent = computed(() => props.isGuest ? GuestLayout : BuyerLayout)

const searchQuery = ref('')
const activeCategory = ref('all')
const openFaqId = ref(null)

const toggleFaq = id => {
    openFaqId.value = openFaqId.value === id ? null : id
}

const safeRoute = (name, fallback = '#') => {
    try {
        if (props.isGuest) {
            const guestMap = {
                'buyer.products': 'guest.products',
                'buyer.help': 'guest.help',
                'buyer.faq': 'guest.faq',
                'buyer.policies': 'guest.policies',
            }
            name = guestMap[name] || name
        }
        return route(name)
    } catch (e) {
        return fallback
    }
}

const categories = [
    { key: 'all', label: 'All FAQs', icon: '🌟' },
    { key: 'orders', label: 'Orders & Tracking', icon: '📦' },
    { key: 'payments', label: 'Payments & Checkout', icon: '💳' },
    { key: 'shipping', label: 'Shipping & Delivery', icon: '🚚' },
    { key: 'returns', label: 'Returns & Refunds', icon: '🔄' },
    { key: 'vouchers', label: 'Vouchers & Deals', icon: '🎟️' },
    { key: 'safety', label: 'Safety & Protection', icon: '🛡️' },
    { key: 'account', label: 'Account & Profile', icon: '👤' },
]

const faqs = [
    // Orders & Tracking
    {
        id: 1,
        category: 'orders',
        question: 'How do I place an order on Alona?',
        answer: 'Browse products or search for what you need, choose your preferred variant (such as color or size) and quantity, then click "Add to Cart" or "Buy Now". Once in your cart, select the items you wish to purchase, proceed to Checkout, choose your delivery address and payment method, and confirm your order.',
    },
    {
        id: 2,
        category: 'orders',
        question: 'How do I track the shipping status of my order?',
        answer: 'Go to "My Orders" and click on your order to view its real-time tracking timeline. You will see fulfillment milestones (To Pack, Shipped, Out for Delivery, Delivered) as well as the assigned courier name and tracking number. You can click "Copy #" to track the parcel directly with the courier.',
    },
    {
        id: 3,
        category: 'orders',
        question: 'Can I cancel an order after placing it?',
        answer: 'Yes, you can cancel an order as long as it has not reached the shipped or out-for-delivery stage. Open the order in "My Orders", click "Cancel Order", select a cancellation reason, and submit. Any reserved inventory will be instantly restored.',
    },
    {
        id: 4,
        category: 'orders',
        question: 'How do I reorder items I purchased before?',
        answer: 'You can easily reorder past purchases by navigating to "My Orders" or "Order Details" and clicking the "Buy Again" button next to any eligible item or for the whole order.',
    },

    // Payments & Checkout
    {
        id: 5,
        category: 'payments',
        question: 'What payment methods can I use at checkout?',
        answer: 'Alona supports multiple flexible payment options: Cash on Delivery (COD), e-wallets (GCash and Maya), and Credit/Debit Cards (Visa, Mastercard, JCB). You can also save payment methods securely in your account for rapid one-click checkout.',
    },
    {
        id: 6,
        category: 'payments',
        question: 'Is my card and payment information secure?',
        answer: 'Yes, absolutely. Alona complies with modern payment security standards. Only the last 4 digits and expiration date are stored for saved payment methods. Sensitive card numbers and CVVs are never saved on our servers.',
    },
    {
        id: 7,
        category: 'payments',
        question: 'How are shipping fees calculated at checkout?',
        answer: 'Shipping fees are calculated based on the delivery province and municipality you select, combined with the courier shipping option chosen (e.g. Standard Delivery vs. Express Shipping). Any applicable shipping discount vouchers will be automatically applied to the total.',
    },

    // Shipping & Delivery
    {
        id: 8,
        category: 'shipping',
        question: 'How long will it take for my order to arrive?',
        answer: 'Metro Manila and nearby provinces typically take 1–3 business days. Provincial areas usually arrive within 3–7 business days. You can monitor courier tracking updates directly on your Order Details page.',
    },
    {
        id: 9,
        category: 'shipping',
        question: 'What should I do if my package is delayed?',
        answer: 'If your order tracking has not updated for several days past the expected delivery date, you can click "Contact Seller" on the order page or open a complaint ticket through the Help Center for platform assistance.',
    },

    // Returns & Refunds
    {
        id: 10,
        category: 'returns',
        question: 'How do I request a return or refund?',
        answer: 'Go to "My Orders", open the delivered order, and click "Return / Refund" next to the specific item. Select whether you are requesting a full return & refund or refund only, choose the appropriate reason (e.g. damaged, wrong item, counterfeit), provide a description, and upload photo/video proof.',
    },
    {
        id: 11,
        category: 'returns',
        question: 'How long does a seller have to respond to a return request?',
        answer: 'Sellers are given 48 to 72 hours to review and respond to return requests. If the seller approves or fails to respond within the timeframe, the platform administrative team will step in to resolve the refund in your favor.',
    },
    {
        id: 12,
        category: 'returns',
        question: 'When will I receive my refund?',
        answer: 'Once approved, refunds are credited back to your original payment method or wallet within 2 to 5 business days depending on your financial institution.',
    },

    // Vouchers & Deals
    {
        id: 13,
        category: 'vouchers',
        question: 'How do I claim and use discount vouchers?',
        answer: 'Visit the "Vouchers & Discounts" page from the main navigation or dashboard. Click "Claim Voucher" on any active promotion. Claimed vouchers will automatically appear in your voucher selection menu during checkout if your cart meets the minimum spend requirements.',
    },
    {
        id: 14,
        category: 'vouchers',
        question: 'Will I be notified when my vouchers are about to expire?',
        answer: 'Yes! Alona sends automated notification alerts to your inbox when a claimed voucher has 3 days or fewer remaining before expiration.',
    },

    // Safety & Protection
    {
        id: 15,
        category: 'safety',
        question: 'How do I report a suspicious product or misconduct by a seller?',
        answer: 'On any product detail page or seller store page, click the "🚩 Report Product" or "🚩 Report Seller" button. Select the reason for your report (e.g. Counterfeit / Fake item, Prohibited content, Fraud, Misleading specifications) and attach evidence. Our compliance and safety team investigates all reports thoroughly.',
    },
    {
        id: 16,
        category: 'safety',
        question: 'How can I track the status of my submitted complaints?',
        answer: 'Navigate to "My Complaints" under your buyer account menu or Help Center. You can view the review stage (Pending, Reviewing, Resolved, Rejected), submitted evidence, and the official admin resolution notes.',
    },

    // Account & Profile
    {
        id: 17,
        category: 'account',
        question: 'How do I change my saved delivery addresses?',
        answer: 'Go to your account profile dropdown and select "My Addresses". You can add new addresses, set a default address for instant checkout, edit existing entries, or delete addresses you no longer use.',
    },
    {
        id: 18,
        category: 'account',
        question: 'How do I update my password and security settings?',
        answer: 'Navigate to "My Account" → "Password & Security". Enter your current password and your new secure password to update your login credentials.',
    },
]

const filteredFaqs = computed(() => {
    return faqs.filter(item => {
        const matchesCategory = activeCategory.value === 'all' || item.category === activeCategory.value
        const matchesSearch = !searchQuery.value ||
            item.question.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            item.answer.toLowerCase().includes(searchQuery.value.toLowerCase())
        return matchesCategory && matchesSearch
    })
})
</script>

<template>
    <Head title="Frequently Asked Questions (FAQ) - Alona" />

    <component :is="layoutComponent">
        <main class="min-h-[calc(100vh-80px)] w-full min-w-0 overflow-x-hidden bg-[#F8FAF9]">
            <div class="mx-auto w-full max-w-[1536px] px-4 py-6 pb-16 sm:px-6 sm:py-8 lg:px-8 2xl:px-10 lg:py-10">

                <!-- BREADCRUMBS -->
                <nav class="mb-4 flex items-center gap-2 text-xs text-gray-500">
                    <Link :href="safeRoute('buyer.help')" class="hover:text-[#087F8C]">Help Center</Link>
                    <span>/</span>
                    <span class="font-bold text-gray-800">FAQs</span>
                </nav>

                <!-- HEADER -->
                <div class="rounded-3xl border border-[#E5E7EB] bg-white p-6 shadow-sm sm:p-8">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-[#F4B942]"></span>
                                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#087F8C] sm:text-xs">
                                    Knowledge Base
                                </p>
                            </div>

                            <h1 class="mt-1 text-2xl font-black tracking-tight text-gray-900 sm:text-3xl">
                                Frequently Asked Questions
                            </h1>

                            <p class="mt-1 text-xs text-gray-500 sm:text-sm">
                                Find instant answers to questions regarding ordering, tracking, payments, returns, and buyer protection.
                            </p>
                        </div>

                        <!-- SEARCH INPUT -->
                        <div class="relative w-full sm:max-w-xs">
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search questions..."
                                class="w-full rounded-xl border border-gray-200 bg-[#F8FAF9] py-2.5 pl-9 pr-8 text-xs text-gray-900 placeholder:text-gray-400 focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                            />
                            <span class="pointer-events-none absolute left-3 top-2.5 text-sm text-gray-400">🔍</span>
                            <button
                                v-if="searchQuery"
                                type="button"
                                class="absolute right-2.5 top-2 text-xs text-gray-400 hover:text-gray-700"
                                @click="searchQuery = ''"
                            >
                                ✕
                            </button>
                        </div>
                    </div>

                    <!-- CATEGORY TABS -->
                    <div class="mt-6 flex flex-wrap gap-2 overflow-x-auto border-t border-gray-100 pt-5">
                        <button
                            v-for="cat in categories"
                            :key="cat.key"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-bold transition"
                            :class="activeCategory === cat.key ? 'bg-[#087F8C] text-white shadow-sm' : 'bg-[#F8FAF9] text-gray-600 hover:bg-gray-100 hover:text-gray-900'"
                            @click="activeCategory = cat.key"
                        >
                            <span>{{ cat.icon }}</span>
                            <span>{{ cat.label }}</span>
                        </button>
                    </div>
                </div>

                <!-- FAQ ACCORDION LIST -->
                <div class="mt-8 space-y-3">
                    <div
                        v-for="faq in filteredFaqs"
                        :key="faq.id"
                        class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white transition hover:border-[#087F8C]/40"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-4 p-5 text-left text-sm font-extrabold text-gray-900 transition hover:bg-[#F8FAF9]"
                            @click="toggleFaq(faq.id)"
                        >
                            <span class="flex items-center gap-3">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-[#E8F7F6] text-xs font-black text-[#087F8C]">
                                    Q
                                </span>
                                <span>{{ faq.question }}</span>
                            </span>

                            <span
                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-500 transition-transform duration-200"
                                :class="{ 'rotate-180 bg-[#E8F7F6] text-[#087F8C]': openFaqId === faq.id }"
                            >
                                ▼
                            </span>
                        </button>

                        <div
                            v-if="openFaqId === faq.id"
                            class="border-t border-gray-100 bg-[#FCFDFC] px-5 py-4 text-xs leading-relaxed text-gray-600 sm:text-sm sm:leading-7"
                        >
                            <p>{{ faq.answer }}</p>
                        </div>
                    </div>

                    <!-- EMPTY SEARCH RESULT -->
                    <div
                        v-if="!filteredFaqs.length"
                        class="rounded-2xl border border-dashed border-gray-200 bg-white p-12 text-center"
                    >
                        <span class="text-3xl">🔍</span>
                        <h2 class="mt-3 text-sm font-extrabold text-gray-900">No matching questions found</h2>
                        <p class="mt-1 text-xs text-gray-500">
                            Try searching with different keywords or contact our support team.
                        </p>
                        <Link
                            :href="safeRoute('buyer.help')"
                            class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-[#087F8C] px-4 py-2 text-xs font-bold text-white transition hover:bg-[#066B76]"
                        >
                            Contact Support Team →
                        </Link>
                    </div>
                </div>

                <!-- STILL NEED HELP BOTTOM CTA -->
                <div class="mt-12 rounded-3xl border border-[#E5E7EB] bg-gradient-to-r from-[#E8F7F6] to-white p-6 sm:p-8">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-base font-extrabold text-gray-900 sm:text-lg">
                                Couldn't find what you're looking for?
                            </h2>
                            <p class="mt-1 text-xs text-gray-600 sm:text-sm">
                                Our customer care team is available Monday through Saturday to assist you with your orders and questions.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <Link
                                :href="safeRoute('buyer.help')"
                                class="inline-flex items-center gap-2 rounded-xl bg-[#087F8C] px-4 py-2.5 text-xs font-extrabold text-white shadow-sm transition hover:bg-[#066B76]"
                            >
                                <span>💬</span>
                                <span>Help Center & Support</span>
                            </Link>
                            <Link
                                :href="safeRoute('buyer.policies')"
                                class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-xs font-extrabold text-gray-700 transition hover:bg-gray-50"
                            >
                                <span>📜</span>
                                <span>View Policies</span>
                            </Link>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </component>
</template>

