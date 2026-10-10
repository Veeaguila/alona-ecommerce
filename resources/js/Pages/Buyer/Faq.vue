<script setup>
import Icon from '@/Components/Icon.vue'
import { computed, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'
import GuestLayout from '@/Layouts/GuestLayout.vue'

const props = defineProps({
    isGuest: {
        type: Boolean,
        default: false,
    },
    faqs: {
        type: Array,
        default: () => [],
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

const categoryIconMap = {
    all: 'sparkle',
    orders: 'package',
    payments: 'card',
    shipping: 'truck',
    returns: 'undo',
    vouchers: 'ticket',
    safety: 'shield',
    account: 'user',
    general: 'help',
}

const categoryLabels = {
    orders: 'Orders & Tracking',
    payments: 'Payments & Checkout',
    shipping: 'Shipping & Delivery',
    returns: 'Returns & Refunds',
    vouchers: 'Vouchers & Deals',
    safety: 'Safety & Protection',
    account: 'Account & Profile',
    general: 'General Inquiries',
}

const categories = computed(() => {
    const list = [{ key: 'all', label: 'All FAQs', icon: 'sparkle' }]
    const found = new Set()
    for (const f of props.faqs) {
        const cat = (f.category || 'general').toLowerCase()
        if (!found.has(cat)) {
            found.add(cat)
            list.push({
                key: cat,
                label: categoryLabels[cat] || cat.charAt(0).toUpperCase() + cat.slice(1),
                icon: categoryIconMap[cat] || 'help',
            })
        }
    }
    return list
})

const filteredFaqs = computed(() => {
    return props.faqs.filter(item => {
        const itemCat = (item.category || 'general').toLowerCase()
        const matchesCategory = activeCategory.value === 'all' || itemCat === activeCategory.value
        const q = (item.question || '').toLowerCase()
        const a = (item.answer || '').toLowerCase()
        const s = searchQuery.value.toLowerCase().trim()
        const matchesSearch = !s || q.includes(s) || a.includes(s)
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
                            <span class="pointer-events-none absolute left-3 top-2.5 text-sm text-gray-400"><Icon name="search" class="h-4 w-4" /></span>
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
                            <span><Icon :name="cat.icon" class="h-4 w-4" /></span>
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
                        <span class="text-3xl"><Icon name="search" class="h-4 w-4" /></span>
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
                                <span><Icon name="message" class="h-4 w-4" /></span>
                                <span>Help Center & Support</span>
                            </Link>
                            <Link
                                :href="safeRoute('buyer.policies')"
                                class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-xs font-extrabold text-gray-700 transition hover:bg-gray-50"
                            >
                                <span><Icon name="book" class="h-4 w-4" /></span>
                                <span>View Policies</span>
                            </Link>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </component>
</template>

