<script setup>
import Icon from '@/Components/Icon.vue'
import { computed, ref } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'
import GuestLayout from '@/Layouts/GuestLayout.vue'

const props = defineProps({
    isGuest: {
        type: Boolean,
        default: false,
    },
    recentOrders: {
        type: Array,
        default: () => [],
    },
    contactSettings: {
        type: Object,
        default: () => ({
            email: 'support@alona.ph',
            phone: '+63 (02) 8888-ALONA',
            hours: 'Mon – Sat: 8:00 AM – 8:00 PM PHT',
        }),
    },
})

const page = usePage()
const layoutComponent = computed(() => props.isGuest ? GuestLayout : BuyerLayout)
const statusMessage = computed(() => page.props.flash?.status || null)

const searchQuery = ref('')
const selectedCategory = ref('all')

const supportForm = useForm({
    category: 'General Inquiry',
    subject: '',
    message: '',
})

const supportCategories = [
    'General Inquiry',
    'Order Status & Tracking',
    'Payment & Billing',
    'Return & Refund Dispute',
    'Vouchers & Promotions',
    'Seller Report / Issue',
    'Account & Security',
]

const submitSupportInquiry = () => {
    supportForm.post(route('buyer.support.contact'), {
        preserveScroll: true,
        onSuccess: () => {
            supportForm.reset()
        },
    })
}

const safeRoute = (name, fallback = '#', params = undefined) => {
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
        return params !== undefined ? route(name, params) : route(name)
    } catch (e) {
        return fallback
    }
}

const helpTopics = [
    {
        id: 'orders',
        category: 'Orders & Shipping',
        icon: 'package',
        title: 'How do I track my order?',
        summary: 'Find your package whereabouts, courier tracking number, and delivery milestones in real-time.',
        link: '/faq#tracking',
    },
    {
        id: 'returns',
        category: 'Returns & Refunds',
        icon: 'undo',
        title: 'How to request a return or refund?',
        summary: 'Submit a return request within the return window with photo or video evidence.',
        link: '/faq#returns',
    },
    {
        id: 'payments',
        category: 'Payments & Checkout',
        icon: 'card',
        title: 'Payment methods accepted on Alona',
        summary: 'Learn about Cash on Delivery (COD), GCash, Maya, and Credit/Debit cards.',
        link: '/faq#payments',
    },
    {
        id: 'vouchers',
        category: 'Vouchers & Deals',
        icon: 'ticket',
        title: 'How to claim and apply vouchers?',
        summary: 'Claim store and platform vouchers to get instant discounts during checkout.',
        link: '/faq#vouchers',
    },
    {
        id: 'safety',
        category: 'Safety & Protection',
        icon: 'shield',
        title: 'Buyer Protection & Reporting',
        summary: 'How Alona protects your payments and allows reporting suspicious items or sellers.',
        link: '/faq#safety',
    },
    {
        id: 'account',
        category: 'Account & Security',
        icon: 'user',
        title: 'Managing addresses & password security',
        summary: 'Update delivery addresses, verify your email, and protect your login credentials.',
        link: '/faq#account',
    },
]

const filteredTopics = computed(() => {
    return helpTopics.filter(topic => {
        const matchesCategory = selectedCategory.value === 'all' || topic.category.toLowerCase().includes(selectedCategory.value.toLowerCase())
        const matchesSearch = !searchQuery.value ||
            topic.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            topic.summary.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            topic.category.toLowerCase().includes(searchQuery.value.toLowerCase())
        return matchesCategory && matchesSearch
    })
})
</script>

<template>
    <Head title="Help Center & Customer Support" />

    <component :is="layoutComponent">
        <main class="min-h-[calc(100vh-80px)] w-full min-w-0 overflow-x-hidden bg-[#F8FAF9]">
            <div class="mx-auto w-full max-w-[1536px] px-4 py-6 pb-16 sm:px-6 sm:py-8 lg:px-8 2xl:px-10 lg:py-10">

                <!-- HERO SECTION -->
                <div class="relative overflow-hidden rounded-[28px] bg-gradient-to-br from-[#087F8C] via-[#066B76] to-[#044850] p-6 text-white shadow-lg sm:p-10 lg:p-12">
                    <div class="pointer-events-none absolute -right-20 -top-20 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
                    <div class="pointer-events-none absolute -bottom-24 left-1/3 h-72 w-72 rounded-full bg-[#16A6A0]/20 blur-3xl"></div>

                    <div class="relative z-10 max-w-3xl">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#F4B942]"></span>
                            <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-[#F4B942] sm:text-xs">
                                Alona Support Center
                            </p>
                        </div>

                        <h1 class="mt-2 text-2xl font-black tracking-tight sm:text-4xl lg:text-5xl">
                            How can we help you today?
                        </h1>

                        <p class="mt-3 text-xs leading-relaxed text-teal-50 sm:text-sm sm:leading-6">
                            Find quick answers to common questions, track your packages, resolve disputes, or reach out to our customer care team.
                        </p>

                        <!-- SEARCH BOX -->
                        <div class="relative mt-6 max-w-2xl">
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search by topic, e.g. return refund, tracking, voucher, payment..."
                                class="w-full rounded-2xl border-0 bg-white py-3.5 pl-11 pr-4 text-xs font-medium text-gray-900 shadow-xl placeholder:text-gray-400 focus:ring-2 focus:ring-[#F4B942] sm:text-sm"
                            />
                            <span class="pointer-events-none absolute left-4 top-3.5 text-base sm:top-4"><Icon name="search" class="h-5 w-5" /></span>
                            <button
                                v-if="searchQuery"
                                type="button"
                                class="absolute right-3.5 top-3 text-xs text-gray-400 hover:text-gray-700"
                                @click="searchQuery = ''"
                            >
                                ✕
                            </button>
                        </div>
                    </div>
                </div>

                <!-- SUCCESS STATUS ALERT -->
                <div
                    v-if="statusMessage"
                    class="mt-6 flex items-start gap-3 rounded-2xl border border-[#CDE9E4] bg-[#EAF8F1] p-4 text-xs font-bold text-[#17784F] shadow-sm sm:text-sm"
                >
                    <span class="text-base">✓</span>
                    <span>{{ statusMessage }}</span>
                </div>

                <!-- QUICK ACTIONS GRID (BUYER-32, BUYER-33, BUYER-36, BUYER-38) -->
                <div class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6 sm:gap-4">
                    <Link
                        :href="safeRoute('buyer.orders')"
                        class="group flex flex-col items-center rounded-2xl border border-[#E5E7EB] bg-white p-4 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-[#087F8C]/40 hover:shadow-md"
                    >
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#E8F7F6] text-xl transition group-hover:scale-110"><Icon name="package" class="h-5 w-5" /></span>
                        <h2 class="mt-2.5 text-xs font-extrabold text-gray-900">Track Orders</h2>
                        <p class="mt-0.5 text-[10px] text-gray-500">Live parcel tracking</p>
                    </Link>

                    <Link
                        :href="safeRoute('buyer.complaints')"
                        class="group flex flex-col items-center rounded-2xl border border-[#E5E7EB] bg-white p-4 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-[#087F8C]/40 hover:shadow-md"
                    >
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#FFF7E6] text-xl transition group-hover:scale-110"><Icon name="receipt" class="h-5 w-5" /></span>
                        <h2 class="mt-2.5 text-xs font-extrabold text-gray-900">My Complaints</h2>
                        <p class="mt-0.5 text-[10px] text-gray-500">Track disputes & cases</p>
                    </Link>

                    <Link
                        :href="safeRoute('buyer.faq')"
                        class="group flex flex-col items-center rounded-2xl border border-[#E5E7EB] bg-white p-4 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-[#087F8C]/40 hover:shadow-md"
                    >
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#EBF5FF] text-xl transition group-hover:scale-110"><Icon name="help" class="h-5 w-5" /></span>
                        <h2 class="mt-2.5 text-xs font-extrabold text-gray-900">Browse FAQs</h2>
                        <p class="mt-0.5 text-[10px] text-gray-500">Frequently asked Q&As</p>
                    </Link>

                    <Link
                        :href="safeRoute('buyer.vouchers')"
                        class="group flex flex-col items-center rounded-2xl border border-[#E5E7EB] bg-white p-4 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-[#087F8C]/40 hover:shadow-md"
                    >
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#FDF2F8] text-xl transition group-hover:scale-110"><Icon name="ticket" class="h-5 w-5" /></span>
                        <h2 class="mt-2.5 text-xs font-extrabold text-gray-900">Vouchers & Deals</h2>
                        <p class="mt-0.5 text-[10px] text-gray-500">Claim discounts</p>
                    </Link>

                    <Link
                        :href="safeRoute('buyer.policies')"
                        class="group flex flex-col items-center rounded-2xl border border-[#E5E7EB] bg-white p-4 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-[#087F8C]/40 hover:shadow-md"
                    >
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#F0FDF4] text-xl transition group-hover:scale-110"><Icon name="book" class="h-5 w-5" /></span>
                        <h2 class="mt-2.5 text-xs font-extrabold text-gray-900">Buyer Policies</h2>
                        <p class="mt-0.5 text-[10px] text-gray-500">Terms & refund rules</p>
                    </Link>

                    <a
                        href="#contact-support"
                        class="group flex flex-col items-center rounded-2xl border border-[#E5E7EB] bg-white p-4 text-center shadow-sm transition hover:-translate-y-0.5 hover:border-[#087F8C]/40 hover:shadow-md"
                    >
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#FEF2F2] text-xl transition group-hover:scale-110"><Icon name="message" class="h-5 w-5" /></span>
                        <h2 class="mt-2.5 text-xs font-extrabold text-gray-900">Direct Support</h2>
                        <p class="mt-0.5 text-[10px] text-gray-500">Contact admin team</p>
                    </a>
                </div>

                <!-- MAIN CONTENT TWO COLUMNS -->
                <div class="mt-10 grid gap-8 lg:grid-cols-[1fr_420px] xl:grid-cols-[1fr_460px]">

                    <!-- LEFT: KNOWLEDGE BASE GUIDES & TOPICS -->
                    <div>
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-black tracking-tight text-gray-900 sm:text-xl">
                                    Help Articles & Guides
                                </h2>
                                <p class="mt-0.5 text-xs text-gray-500">
                                    Browse step-by-step guidance for shopping on Alona.
                                </p>
                            </div>

                            <Link
                                :href="safeRoute('buyer.faq')"
                                class="text-xs font-extrabold text-[#087F8C] hover:underline"
                            >
                                View full FAQ →
                            </Link>
                        </div>

                        <!-- CATEGORY FILTER CHIPS -->
                        <div class="mt-4 flex flex-wrap gap-2 overflow-x-auto pb-1">
                            <button
                                v-for="cat in ['all', 'Orders', 'Returns', 'Payments', 'Vouchers', 'Safety']"
                                :key="cat"
                                type="button"
                                class="rounded-xl px-3 py-1.5 text-xs font-bold transition"
                                :class="selectedCategory.toLowerCase() === cat.toLowerCase() ? 'bg-[#087F8C] text-white shadow-sm' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'"
                                @click="selectedCategory = cat"
                            >
                                {{ cat === 'all' ? 'All Topics' : cat }}
                            </button>
                        </div>

                        <!-- TOPICS LIST -->
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <Link
                                v-for="topic in filteredTopics"
                                :key="topic.id"
                                :href="safeRoute('buyer.faq')"
                                class="group flex flex-col justify-between rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm transition hover:border-[#087F8C]/40 hover:shadow-md"
                            >
                                <div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-2xl"><Icon :name="topic.icon" class="h-4 w-4" /></span>
                                        <span class="rounded-full bg-[#F1F5F9] px-2 py-0.5 text-[9px] font-bold text-[#64748B]">
                                            {{ topic.category }}
                                        </span>
                                    </div>

                                    <h3 class="mt-3 text-sm font-extrabold text-gray-900 transition group-hover:text-[#087F8C]">
                                        {{ topic.title }}
                                    </h3>

                                    <p class="mt-1 text-xs leading-relaxed text-gray-500">
                                        {{ topic.summary }}
                                    </p>
                                </div>

                                <div class="mt-4 flex items-center gap-1 text-[11px] font-bold text-[#087F8C]">
                                    <span>Read guide</span>
                                    <span class="transition group-hover:translate-x-1">→</span>
                                </div>
                            </Link>
                        </div>

                        <!-- SAFETY & COMPLIANCE NOTICE (BUYER-34, BUYER-38) -->
                        <div class="mt-6 rounded-2xl border border-[#FED7AA] bg-[#FFFBEB] p-5">
                            <div class="flex items-start gap-3">
                                <span class="text-2xl"><Icon name="shield" class="h-4 w-4" /></span>
                                <div>
                                    <h3 class="text-xs font-extrabold text-[#92400E] sm:text-sm">
                                        Alona Buyer Protection Guarantee
                                    </h3>
                                    <p class="mt-1 text-xs leading-relaxed text-[#78350F]">
                                        Every order placed on Alona is protected. If you receive an item that is damaged, counterfeit, or significantly different from what was described, you can request a return or report the seller for dispute investigation.
                                    </p>
                                    <div class="mt-3 flex flex-wrap items-center gap-3">
                                        <Link
                                            :href="safeRoute('buyer.policies')"
                                            class="inline-flex text-xs font-bold text-[#92400E] underline hover:text-[#78350F]"
                                        >
                                            Read Buyer Protection Policy →
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT: DIRECT CUSTOMER SUPPORT CONTACT FORM (BUYER-32) -->
                    <div id="contact-support" class="space-y-6 scroll-mt-24">

                        <!-- CONTACT FORM CARD -->
                        <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-sm sm:p-6">
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-[#087F8C]"></span>
                                <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#087F8C]">
                                    Direct Contact
                                </p>
                            </div>

                            <h2 class="mt-1 text-base font-extrabold text-gray-900 sm:text-lg">
                                Contact Platform Support
                            </h2>

                            <p class="mt-1 text-xs text-gray-500">
                                Send a message directly to Alona customer care. We typically respond within 2-4 hours.
                            </p>

                            <form class="mt-5 space-y-4" @submit.prevent="submitSupportInquiry">
                                <!-- CATEGORY -->
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-700">Inquiry Category</label>
                                    <select
                                        v-model="supportForm.category"
                                        class="mt-1 w-full rounded-xl border border-gray-200 bg-[#F8FAF9] px-3.5 py-2.5 text-xs text-gray-900 focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                    >
                                        <option v-for="cat in supportCategories" :key="cat" :value="cat">
                                            {{ cat }}
                                        </option>
                                    </select>
                                </div>

                                <!-- SUBJECT -->
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-700">Subject</label>
                                    <input
                                        v-model="supportForm.subject"
                                        type="text"
                                        required
                                        placeholder="Brief summary of your question or issue..."
                                        class="mt-1 w-full rounded-xl border border-gray-200 bg-[#F8FAF9] px-3.5 py-2.5 text-xs text-gray-900 focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                    />
                                    <p v-if="supportForm.errors.subject" class="mt-1 text-[11px] text-red-600">
                                        {{ supportForm.errors.subject }}
                                    </p>
                                </div>

                                <!-- MESSAGE -->
                                <div>
                                    <label class="block text-[11px] font-bold text-gray-700">Message / Details</label>
                                    <textarea
                                        v-model="supportForm.message"
                                        rows="4"
                                        required
                                        placeholder="Please provide details including order number or seller name if applicable..."
                                        class="mt-1 w-full rounded-xl border border-gray-200 bg-[#F8FAF9] px-3.5 py-2.5 text-xs text-gray-900 focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                    ></textarea>
                                    <p v-if="supportForm.errors.message" class="mt-1 text-[11px] text-red-600">
                                        {{ supportForm.errors.message }}
                                    </p>
                                </div>

                                <button
                                    type="submit"
                                    :disabled="supportForm.processing"
                                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#087F8C] py-3 text-xs font-extrabold text-white shadow-sm transition hover:bg-[#066B76] active:scale-[0.99] disabled:opacity-50"
                                >
                                    <span><Icon name="mail" class="h-4 w-4" /></span>
                                    <span>{{ supportForm.processing ? 'Sending Inquiry...' : 'Send Message to Support' }}</span>
                                </button>
                            </form>
                        </div>

                        <!-- SUPPORT CHANNELS & OPERATING HOURS -->
                        <div class="rounded-2xl border border-[#E5E7EB] bg-[#FCFDFC] p-5 shadow-sm">
                            <h3 class="text-xs font-extrabold text-gray-900">
                                Platform Support Channels
                            </h3>

                            <ul class="mt-3 space-y-3 text-xs">
                                <li class="flex items-center gap-3">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#E8F7F6] text-sm text-[#087F8C]"><Icon name="mail" class="h-4 w-4" /></span>
                                    <div class="min-w-0">
                                        <p class="font-bold text-gray-800">Support Email</p>
                                        <p class="text-gray-500 font-mono text-[11px]">{{ contactSettings.email }}</p>
                                    </div>
                                </li>

                                <li class="flex items-center gap-3">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#FFF7E6] text-sm text-[#B47A08]"><Icon name="phone" class="h-4 w-4" /></span>
                                    <div class="min-w-0">
                                        <p class="font-bold text-gray-800">Customer Helpline</p>
                                        <p class="text-gray-500 text-[11px]">{{ contactSettings.phone }}</p>
                                    </div>
                                </li>

                                <li class="flex items-center gap-3">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#EBF5FF] text-sm text-[#2563EB]"><Icon name="clock" class="h-4 w-4" /></span>
                                    <div class="min-w-0">
                                        <p class="font-bold text-gray-800">Operating Hours</p>
                                        <p class="text-gray-500 text-[11px]">{{ contactSettings.hours }}</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </component>
</template>

