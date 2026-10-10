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
    databasePolicies: {
        type: Array,
        default: () => [],
    },
})

const layoutComponent = computed(() => props.isGuest ? GuestLayout : BuyerLayout)

const activePolicyKey = ref(null)

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


const policies = computed(() => props.databasePolicies || [])

const activePolicy = computed(() => {
    return policies.value.find(p => p.id === activePolicyKey.value) || policies.value[0] || null
})

const formatDate = (value) => {
    if (!value) return ''
    const d = new Date(value)
    return Number.isNaN(d.getTime())
        ? ''
        : d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })
}
</script>
<template>
    <Head :title="`${activePolicy ? activePolicy.title : 'Policies'} - Alona Buyer Policies`" />

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
                            v-for="policy in policies"
                            :key="policy.id"
                            type="button"
                            class="flex w-full items-center justify-between rounded-2xl p-4 text-left transition"
                            :class="activePolicy && activePolicy.id === policy.id ? 'border border-[#087F8C]/40 bg-[#E8F7F6] text-[#087F8C] font-extrabold shadow-sm' : 'border border-[#E5E7EB] bg-white text-gray-700 hover:bg-gray-50'"
                            @click="activePolicyKey = policy.id"
                        >
                            <span class="flex items-center gap-3">
                                <Icon name="book" class="h-4 w-4" />
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
                        <div v-if="!activePolicy" class="py-10 text-center text-sm text-gray-500">
                            No policies have been published yet.
                        </div>
                        <template v-else>
                            <div class="border-b border-gray-100 pb-5">
                                <div class="flex items-center gap-2">
                                    <Icon name="book" class="h-5 w-5 text-[#087F8C]" />
                                    <h2 class="text-xl font-black tracking-tight text-gray-900 sm:text-2xl">
                                        {{ activePolicy.title }}
                                    </h2>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">
                                    Version {{ activePolicy.version }}<span v-if="formatDate(activePolicy.updated_at)"> · Last revised on {{ formatDate(activePolicy.updated_at) }}</span>
                                </p>
                            </div>
                            <div class="mt-8 whitespace-pre-line text-xs leading-relaxed text-gray-600 sm:text-sm sm:leading-7">{{ activePolicy.content }}</div>
                        </template>
                    </article>

                </div>

            </div>
        </main>
    </component>
</template>

