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
    databasePolicies: {
        type: Array,
        default: () => [],
    },
})

const layoutComponent = computed(() => props.isGuest ? GuestLayout : BuyerLayout)

const activePolicyKey = ref('terms')

const safeRoute = (name, fallback = '#') => {
    try {
        if (props.isGuest) {
            const guestMap = {
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

const defaultPolicies = [
    {
        key: 'terms',
        title: 'Terms of Service',
        icon: '📋',
        version: 'v2.4',
        lastUpdated: 'October 1, 2026',
        summary: 'General terms and conditions governing the use of the Alona marketplace for buyers and visitors.',
        sections: [
            {
                heading: '1. Acceptance of Terms',
                content: 'By accessing or using the Alona marketplace platform, you agree to be bound by these Terms of Service and all policies referenced herein. If you do not agree, you must discontinue using our services.',
            },
            {
                heading: '2. Buyer Account & Security',
                content: 'You must provide accurate and complete registration information. You are responsible for maintaining the confidentiality of your account credentials and password. Any actions taken under your account will be considered authorized by you.',
            },
            {
                heading: '3. Purchases and Order Placement',
                content: 'When placing an order, you agree that you are making a binding offer to purchase the selected items at the listed price plus applicable shipping fees. Prices, product specifications, and availability are subject to change by sellers.',
            },
            {
                heading: '4. Cancellations and Modifications',
                content: 'Buyers may cancel an order before the seller packs or dispatches the parcel. Once an order is shipped, cancellation is no longer possible and the standard Return and Refund Policy applies upon delivery.',
            },
            {
                heading: '5. Limitation of Liability',
                content: 'Alona acts as a platform connecting independent third-party sellers with buyers. To the extent permitted by Philippine law, Alona is not liable for indirect, incidental, or consequential damages arising from transactions between buyers and sellers.',
            },
        ],
    },
    {
        key: 'privacy',
        title: 'Privacy Policy',
        icon: '🔒',
        version: 'v2.1',
        lastUpdated: 'September 15, 2026',
        summary: 'How Alona collects, uses, protects, and handles your personal data under the Data Privacy Act of 2012.',
        sections: [
            {
                heading: '1. Information We Collect',
                content: 'We collect personal information necessary to fulfill your orders, including your full name, email address, delivery addresses, mobile numbers, and transaction logs. When using payment methods, only truncated identifiers (last 4 digits) are retained.',
            },
            {
                heading: '2. How We Use Your Data',
                content: 'Your information is used strictly to process orders, communicate tracking updates, manage customer support tickets, prevent fraudulent transactions, and provide personalized marketplace recommendations.',
            },
            {
                heading: '3. Data Sharing with Sellers and Couriers',
                content: 'To complete delivery, we share your recipient name, delivery address, and contact number with the designated seller and logistics carrier. They are legally restricted from using your data for unauthorized purposes.',
            },
            {
                heading: '4. Data Security and Retention',
                content: 'We employ SSL/TLS encryption, hashed credentials, and strict access controls. Your data is retained only as long as your account remains active or as required by Philippine tax and accounting regulations.',
            },
        ],
    },
    {
        key: 'returns',
        title: 'Return, Refund & Cancellation Policy',
        icon: '🔄',
        version: 'v3.0',
        lastUpdated: 'October 5, 2026',
        summary: 'Guidelines, eligible reasons, evidence requirements, and timelines for return and refund requests.',
        sections: [
            {
                heading: '1. Eligibility Window',
                content: 'Buyers may submit a Return or Refund request within 7 calendar days from the date the parcel is confirmed delivered by the carrier.',
            },
            {
                heading: '2. Valid Reasons for Returns and Refunds',
                content: 'Valid grounds include: (a) Damaged or defective product upon unboxing, (b) Wrong item, color, or size delivered, (c) Incomplete items or missing accessories, (d) Counterfeit or fake items, (e) Item significantly different from listing description.',
            },
            {
                heading: '3. Photo and Video Evidence',
                content: 'Buyers are required to upload clear photos or unboxing videos showing the package label, defective areas, or incorrect items to facilitate fast review by sellers and platform mediators.',
            },
            {
                heading: '4. Seller Response & Platform Mediation',
                content: 'Sellers must respond within 48 to 72 hours. If a seller rejects a valid request or fails to respond, the Alona Dispute Team will review the evidence and issue a final resolution.',
            },
        ],
    },
    {
        key: 'shipping',
        title: 'Shipping & Delivery Policy',
        icon: '🚚',
        version: 'v1.8',
        lastUpdated: 'September 20, 2026',
        summary: 'Delivery timelines, courier operations, shipping fee calculations, and failed delivery protocols.',
        sections: [
            {
                heading: '1. Delivery Estimates',
                content: 'Standard Delivery takes 1–3 business days for Metro Manila and 3–7 business days for provincial locations. Courier tracking numbers are issued as soon as the seller dispatches the package.',
            },
            {
                heading: '2. Delivery Attempts',
                content: 'Couriers will make up to 2 delivery attempts. If the recipient is unavailable after 2 attempts, the parcel will be returned to the seller and the order may be marked cancelled.',
            },
            {
                heading: '3. Inspection upon Receipt',
                content: 'Buyers are encouraged to inspect outer packaging integrity before acknowledging receipt and to record an unboxing video as evidence in case of damage or missing contents.',
            },
        ],
    },
    {
        key: 'protection',
        title: 'Buyer Protection & Counterfeit Policy',
        icon: '🛡️',
        version: 'v2.0',
        lastUpdated: 'October 1, 2026',
        summary: 'Zero-tolerance policy on counterfeit goods, seller penalties, and 100% money-back guarantee.',
        sections: [
            {
                heading: '1. Authentic Goods Guarantee',
                content: 'Alona enforces a strict zero-tolerance policy against fake, counterfeit, pirated, or unauthorized replica products. Sellers found listing counterfeit items will face store suspension and financial forfeiture.',
            },
            {
                heading: '2. 100% Money-Back Guarantee',
                content: 'If an item is proven to be counterfeit upon review, the buyer receives a 100% full refund including shipping costs.',
            },
            {
                heading: '3. How to Report Suspicious Listings',
                content: 'Click "Report this product" or "Report seller" on any store or product page to alert our compliance team immediately.',
            },
        ],
    },
    {
        key: 'community',
        title: 'Community & Review Guidelines',
        icon: '💬',
        version: 'v1.5',
        lastUpdated: 'August 28, 2026',
        summary: 'Standards for honest product ratings, customer feedback, media uploads, and respectful messaging.',
        sections: [
            {
                heading: '1. Authentic Reviews',
                content: 'Only verified purchasers of a delivered product may submit reviews. Reviews must reflect genuine product experiences and must not contain abusive, defamatory, or promotional spam.',
            },
            {
                heading: '2. Media Guidelines',
                content: 'Uploaded photos and videos must directly showcase the purchased product. Inappropriate, offensive, or copyrighted media is strictly prohibited and will be removed.',
            },
        ],
    },
]

const activePolicy = computed(() => {
    return defaultPolicies.find(p => p.key === activePolicyKey.value) || defaultPolicies[0]
})
</script>

<template>
    <Head :title="`${activePolicy.title} - Alona Buyer Policies`" />

    <component :is="layoutComponent">
        <main class="min-h-[calc(100vh-80px)] w-full min-w-0 overflow-x-hidden bg-[#F8FAF9]">
            <div class="mx-auto w-full max-w-[1536px] px-4 py-6 pb-16 sm:px-6 sm:py-8 lg:px-8 2xl:px-10 lg:py-10">

                <!-- BREADCRUMBS -->
                <nav class="mb-4 flex items-center gap-2 text-xs text-gray-500">
                    <Link :href="safeRoute('buyer.help')" class="hover:text-[#087F8C]">Help Center</Link>
                    <span>/</span>
                    <span class="font-bold text-gray-800">Buyer Policies</span>
                </nav>

                <!-- HEADER -->
                <div class="rounded-3xl border border-[#E5E7EB] bg-white p-6 shadow-sm sm:p-8">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-[#F4B942]"></span>
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#087F8C] sm:text-xs">
                            Legal & Compliance
                        </p>
                    </div>

                    <h1 class="mt-1 text-2xl font-black tracking-tight text-gray-900 sm:text-3xl">
                        Alona Platform Policies & Terms
                    </h1>

                    <p class="mt-1 max-w-3xl text-xs leading-relaxed text-gray-500 sm:text-sm">
                        Read our official platform terms, privacy standards, return rules, and buyer protection guarantees.
                    </p>
                </div>

                <!-- MAIN TWO COLUMNS -->
                <div class="mt-8 grid gap-8 lg:grid-cols-[300px_minmax(0,1fr)] xl:grid-cols-[340px_minmax(0,1fr)]">

                    <!-- LEFT: POLICIES SIDEBAR MENU -->
                    <aside class="space-y-2">
                        <p class="px-2 text-[11px] font-bold uppercase tracking-wider text-gray-400">
                            Available Policies
                        </p>

                        <button
                            v-for="policy in defaultPolicies"
                            :key="policy.key"
                            type="button"
                            class="flex w-full items-center justify-between rounded-2xl p-4 text-left transition"
                            :class="activePolicyKey === policy.key ? 'border border-[#087F8C]/40 bg-[#E8F7F6] text-[#087F8C] font-extrabold shadow-sm' : 'border border-[#E5E7EB] bg-white text-gray-700 hover:bg-gray-50'"
                            @click="activePolicyKey = policy.key"
                        >
                            <span class="flex items-center gap-3">
                                <span class="text-xl">{{ policy.icon }}</span>
                                <span class="text-xs sm:text-sm">{{ policy.title }}</span>
                            </span>

                            <span class="rounded-full bg-white/80 px-2 py-0.5 text-[10px] font-bold text-gray-500">
                                {{ policy.version }}
                            </span>
                        </button>

                        <!-- HELP & SUPPORT LINK CARD -->
                        <div class="mt-6 rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm">
                            <h3 class="text-xs font-bold text-gray-900">Have policy questions?</h3>
                            <p class="mt-1 text-[11px] text-gray-500 leading-relaxed">
                                If you have questions regarding these terms, our support team is available to help.
                            </p>
                            <Link
                                :href="safeRoute('buyer.help')"
                                class="mt-3 inline-flex items-center gap-1.5 text-xs font-extrabold text-[#087F8C] hover:underline"
                            >
                                <span>Contact Support</span>
                                <span>→</span>
                            </Link>
                        </div>
                    </aside>

                    <!-- RIGHT: POLICY DETAIL VIEW -->
                    <article class="rounded-3xl border border-[#E5E7EB] bg-white p-6 shadow-sm sm:p-8 lg:p-10">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-5">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-2xl">{{ activePolicy.icon }}</span>
                                    <h2 class="text-xl font-black tracking-tight text-gray-900 sm:text-2xl">
                                        {{ activePolicy.title }}
                                    </h2>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">
                                    Version {{ activePolicy.version }} · Last revised on {{ activePolicy.lastUpdated }}
                                </p>
                            </div>

                            <span class="inline-flex items-center rounded-full bg-[#EAF8F1] px-3 py-1 text-xs font-bold text-[#22A06B]">
                                Active & In Effect
                            </span>
                        </div>

                        <!-- POLICY SUMMARY BANNER -->
                        <div class="mt-6 rounded-2xl bg-[#F8FAF9] p-4 text-xs font-medium leading-relaxed text-gray-700 sm:text-sm">
                            <strong class="font-bold text-gray-900">Summary: </strong>
                            {{ activePolicy.summary }}
                        </div>

                        <!-- POLICY SECTIONS -->
                        <div class="mt-8 space-y-6">
                            <section
                                v-for="(sec, idx) in activePolicy.sections"
                                :key="idx"
                                class="border-b border-gray-50 pb-5 last:border-b-0"
                            >
                                <h3 class="text-sm font-extrabold text-gray-900 sm:text-base">
                                    {{ sec.heading }}
                                </h3>
                                <p class="mt-2 text-xs leading-relaxed text-gray-600 sm:text-sm sm:leading-7">
                                    {{ sec.content }}
                                </p>
                            </section>
                        </div>

                        <!-- DYNAMIC ADMIN POLICIES IF PRESENT -->
                        <div v-if="databasePolicies.length" class="mt-10 border-t border-gray-100 pt-6">
                            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">
                                Platform Updates & Policy Addendums
                            </h3>
                            <div class="mt-3 space-y-3">
                                <div
                                    v-for="dbPolicy in databasePolicies"
                                    :key="dbPolicy.id"
                                    class="rounded-xl border border-gray-100 bg-[#FCFDFC] p-4 text-xs"
                                >
                                    <h4 class="font-bold text-gray-900">{{ dbPolicy.title }} ({{ dbPolicy.version }})</h4>
                                    <p class="mt-1 text-gray-600 whitespace-pre-line">{{ dbPolicy.content }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- BOTTOM ACKNOWLEDGEMENT -->
                        <div class="mt-10 rounded-2xl border border-dashed border-gray-200 bg-[#FCFDFC] p-4 text-center text-xs text-gray-500">
                            By continuing to use Alona, you confirm your acceptance of the {{ activePolicy.title }}.
                        </div>
                    </article>

                </div>

            </div>
        </main>
    </component>
</template>

