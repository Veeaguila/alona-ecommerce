<!-- Buyer Vouchers Page (Browse, Claim, View Claimed/Used/Expired, and Expiring Alerts). -->
<script setup>
import Icon from '@/Components/Icon.vue'
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'

const props = defineProps({
    my_vouchers: {
        type: Object,
        default: () => null,
    },
    browse_vouchers: {
        type: Object,
        default: () => null,
    },
    current_tab: {
        type: String,
        default: 'claimed',
    },
    counts: {
        type: Object,
        default: () => ({
            claimed: 0,
            used: 0,
            expired: 0,
            browse: 0,
        }),
    },
    status: {
        type: String,
        default: '',
    },
})

const tabs = [
    { key: 'claimed', label: 'My Vouchers', countKey: 'claimed' },
    { key: 'browse', label: 'Claim New Vouchers', countKey: 'browse' },
    { key: 'used', label: 'Used Vouchers', countKey: 'used' },
    { key: 'expired', label: 'Expired', countKey: 'expired' },
]

const claimingId = ref(null)
const copiedCode = ref(null)

const money = value => {
    const amount = Number(value ?? 0)
    return `₱${amount.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`
}

const formatDate = value => {
    if (!value) return 'No expiration'
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return 'No expiration'
    return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}

const isExpiringSoon = expiresAt => {
    if (!expiresAt) return false
    const exp = new Date(expiresAt).getTime()
    const now = new Date().getTime()
    const diffDays = (exp - now) / (1000 * 60 * 60 * 24)
    return diffDays >= 0 && diffDays <= 3
}

const copyCode = code => {
    if (!code) return
    navigator.clipboard.writeText(code)
    copiedCode.value = code
    setTimeout(() => {
        copiedCode.value = null
    }, 2000)
}

const selectTab = tabKey => {
    router.get(
        route('buyer.vouchers'),
        { tab: tabKey },
        { preserveState: true, preserveScroll: true }
    )
}

const claimVoucher = voucherId => {
    claimingId.value = voucherId
    router.post(
        route('buyer.vouchers.claim', voucherId),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                claimingId.value = null
            },
        }
    )
}

const voucherList = computed(() => {
    if (props.current_tab === 'browse') {
        return props.browse_vouchers?.data || []
    }
    return props.my_vouchers?.data || []
})

const pagination = computed(() => {
    const paginator = props.current_tab === 'browse' ? props.browse_vouchers : props.my_vouchers
    return paginator?.links || []
})
</script>

<template>
    <Head title="My Vouchers" />

    <BuyerLayout>
        <main class="min-h-[calc(100vh-80px)] w-full min-w-0 overflow-x-hidden bg-[#F8FAF9]">
            <div class="mx-auto w-full max-w-[1536px] px-4 py-6 pb-12 sm:px-6 sm:py-7 lg:px-8 2xl:px-10 lg:py-8 lg:pb-14">

                <!-- PAGE HEADER -->
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#F4B942]"></span>
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#087F8C] sm:text-xs">
                                Savings & Rewards
                            </p>
                        </div>

                        <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-[#1F2937] sm:text-3xl">
                            Vouchers & Discounts
                        </h1>

                        <p class="mt-1 text-xs leading-5 text-[#64748B] sm:text-sm">
                            Browse platform vouchers, claim exclusive seller discounts, and save on checkout.
                        </p>
                    </div>

                    <Link
                        :href="route('buyer.cart')"
                        class="inline-flex w-fit items-center gap-2 rounded-xl bg-[#087F8C] px-4 py-2.5 text-xs font-extrabold text-white shadow-sm transition hover:bg-[#066B76] sm:text-sm"
                    >
                        <span><Icon name="cart" class="h-4 w-4" /></span>
                        Go to Cart
                    </Link>
                </div>

                <!-- SUCCESS MESSAGE -->
                <div
                    v-if="status"
                    class="mt-4 flex items-start gap-2.5 rounded-xl border border-[#CDE9E4] bg-[#EAF8F1] px-3.5 py-3 text-xs font-bold text-[#17784F] sm:text-sm"
                >
                    <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12l4 4L19 6" />
                    </svg>
                    <span>{{ status }}</span>
                </div>

                <!-- TABS (BUYER-24, BUYER-26) -->
                <div class="mt-5 overflow-x-auto pb-1 scrollbar-none">
                    <nav class="flex min-w-max gap-2 border-b border-[#E5E7EB] pb-2">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            :class="[
                                'inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-extrabold transition',
                                current_tab === tab.key
                                    ? 'bg-[#087F8C] text-white shadow-sm'
                                    : 'bg-white text-[#64748B] hover:bg-[#F1F5F9] hover:text-[#1F2937]'
                            ]"
                            @click="selectTab(tab.key)"
                        >
                            <span>{{ tab.label }}</span>
                            <span
                                :class="[
                                    'rounded-full px-1.5 py-0.5 text-[10px] font-bold',
                                    current_tab === tab.key
                                        ? 'bg-white/20 text-white'
                                        : 'bg-[#F1F5F9] text-[#64748B]'
                                ]"
                            >
                                {{ counts[tab.countKey] || 0 }}
                            </span>
                        </button>
                    </nav>
                </div>

                <!-- VOUCHERS GRID -->
                <div
                    v-if="voucherList.length"
                    class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                >
                    <!-- BROWSE AVAILABLE VOUCHERS (BUYER-24, BUYER-25) -->
                    <template v-if="current_tab === 'browse'">
                        <div
                            v-for="voucher in voucherList"
                            :key="voucher.id"
                            class="relative flex flex-col justify-between overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm transition hover:border-[#16A6A0] hover:shadow-md"
                        >
                            <!-- TOP BADGES -->
                            <div class="flex items-start justify-between gap-2">
                                <span
                                    v-if="voucher.is_platform || !voucher.seller_id"
                                    class="rounded-full bg-[#E8F7F6] px-2.5 py-0.5 text-[10px] font-extrabold text-[#087F8C]"
                                >
                                    Platform Voucher
                                </span>
                                <span
                                    v-else
                                    class="rounded-full bg-[#FFF7E6] px-2.5 py-0.5 text-[10px] font-extrabold text-[#B47A08]"
                                >
                                    Store: {{ voucher.seller?.name || 'Seller' }}
                                </span>

                                <span v-if="isExpiringSoon(voucher.expires_at)" class="rounded-full bg-[#FEE2E2] px-2 py-0.5 text-[9px] font-bold text-[#DC2626]">
                                    Expiring Soon </span>
                            </div>

                            <!-- DISCOUNT VALUE -->
                            <div class="mt-3">
                                <p class="text-2xl font-black text-[#1F2937]">
                                    {{ voucher.type === 'percentage' ? `${Number(voucher.value)}% OFF` : `${money(voucher.value)} OFF` }}
                                </p>
                                <p class="mt-1 text-xs text-[#64748B]">
                                    {{ voucher.min_spend ? `Min. spend ${money(voucher.min_spend)}` : 'No minimum spend' }}
                                </p>
                            </div>

                            <!-- CODE & EXPIRY -->
                            <div class="mt-4 border-t border-dashed border-[#E5E7EB] pt-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 font-mono text-xs font-extrabold text-[#087F8C]">
                                        <span><Icon name="tag" class="h-4 w-4" /></span>
                                        <span>{{ voucher.code }}</span>
                                    </div>
                                    <p class="text-[10px] text-[#94A3B8]">
                                        Valid until {{ formatDate(voucher.expires_at) }}
                                    </p>
                                </div>

                                <!-- CLAIM ACTION (BUYER-25) -->
                                <button
                                    type="button"
                                    :disabled="claimingId === voucher.id"
                                    class="mt-3 w-full rounded-xl bg-[#087F8C] py-2 text-xs font-extrabold text-white shadow-sm transition hover:bg-[#066B76] disabled:opacity-50"
                                    @click="claimVoucher(voucher.id)"
                                >
                                    {{ claimingId === voucher.id ? 'Claiming...' : 'Claim Voucher' }}
                                </button>
                            </div>
                        </div>
                    </template>

                    <!-- MY CLAIMED / USED / EXPIRED VOUCHERS (BUYER-26, BUYER-27) -->
                    <template v-else>
                        <div
                            v-for="item in voucherList"
                            :key="item.id"
                            class="relative flex flex-col justify-between overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm"
                            :class="{
                                'opacity-65 bg-[#F8FAF9]': item.status === 'used' || item.status === 'expired'
                            }"
                        >
                            <!-- STATUS & STORE BADGE -->
                            <div class="flex items-start justify-between gap-2">
                                <span
                                    v-if="item.voucher?.is_platform || !item.voucher?.seller_id"
                                    class="rounded-full bg-[#E8F7F6] px-2.5 py-0.5 text-[10px] font-extrabold text-[#087F8C]"
                                >
                                    Platform Voucher
                                </span>
                                <span
                                    v-else
                                    class="rounded-full bg-[#FFF7E6] px-2.5 py-0.5 text-[10px] font-extrabold text-[#B47A08]"
                                >
                                    Store: {{ item.voucher?.seller?.name || 'Seller' }}
                                </span>

                                <span
                                    v-if="item.status === 'claimed' && isExpiringSoon(item.voucher?.expires_at)"
                                    class="rounded-full bg-[#FEE2E2] px-2 py-0.5 text-[9px] font-bold text-[#DC2626]"
                                >
                                    Expiring Soon </span>
                                <span
                                    v-else-if="item.status === 'used'"
                                    class="rounded-full bg-[#F1F5F9] px-2 py-0.5 text-[9px] font-bold text-[#64748B]"
                                >
                                    Used
                                </span>
                                <span
                                    v-else-if="item.status === 'expired'"
                                    class="rounded-full bg-[#FDECEC] px-2 py-0.5 text-[9px] font-bold text-[#B94242]"
                                >
                                    Expired
                                </span>
                            </div>

                            <!-- DISCOUNT VALUE -->
                            <div class="mt-3">
                                <p class="text-2xl font-black text-[#1F2937]">
                                    {{ item.voucher?.type === 'percentage' ? `${Number(item.voucher?.value)}% OFF` : `${money(item.voucher?.value)} OFF` }}
                                </p>
                                <p class="mt-1 text-xs text-[#64748B]">
                                    {{ item.voucher?.min_spend ? `Min. spend ${money(item.voucher?.min_spend)}` : 'No minimum spend' }}
                                </p>
                            </div>

                            <!-- CODE & EXPIRY -->
                            <div class="mt-4 border-t border-dashed border-[#E5E7EB] pt-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 font-mono text-xs font-extrabold text-[#087F8C]">
                                        <span><Icon name="tag" class="h-4 w-4" /></span>
                                        <span>{{ item.voucher?.code }}</span>
                                    </div>
                                    <p class="text-[10px] text-[#94A3B8]">
                                        Valid until {{ formatDate(item.voucher?.expires_at) }}
                                    </p>
                                </div>

                                <div class="mt-3 flex gap-2">
                                    <button
                                        type="button"
                                        class="flex-1 rounded-xl border border-[#D5E4E3] bg-white py-2 text-xs font-bold text-[#087F8C] transition hover:bg-[#E8F7F6]"
                                        @click="copyCode(item.voucher?.code)"
                                    >
                                        {{ copiedCode === item.voucher?.code ? 'Copied! ✓' : 'Copy Code' }}
                                    </button>

                                    <Link
                                        v-if="item.status === 'claimed'"
                                        :href="route('buyer.cart')"
                                        class="flex-1 rounded-xl bg-[#087F8C] py-2 text-center text-xs font-extrabold text-white transition hover:bg-[#066B76]"
                                    >
                                        Use Now →
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- PAGINATION -->
                <div
                    v-if="pagination.length > 3"
                    class="mt-6 flex justify-center gap-1"
                >
                    <Component
                        :is="link.url ? Link : 'span'"
                        v-for="(link, i) in pagination"
                        :key="i"
                        :href="link.url"
                        :class="[
                            'rounded-xl px-3 py-1.5 text-xs font-bold transition',
                            link.active
                                ? 'bg-[#087F8C] text-white'
                                : link.url
                                    ? 'bg-white text-[#64748B] hover:bg-[#E8F7F6] hover:text-[#087F8C]'
                                    : 'bg-[#F1F5F9] text-[#CBD5E1]'
                        ]"
                        v-html="link.label"
                    />
                </div>

                <!-- EMPTY STATE -->
                <div
                    v-if="!voucherList.length"
                    class="mt-6 rounded-2xl border border-dashed border-[#D7E0E2] bg-white px-5 py-14 text-center shadow-sm"
                >
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-[#E8F7F6] text-2xl"><Icon name="tag" class="h-6 w-6" /></div>

                    <p class="mt-4 text-sm font-extrabold text-[#1F2937]">
                        {{ current_tab === 'browse' ? 'No new vouchers available to claim right now.' : 'No vouchers found in this tab.' }}
                    </p>

                    <p class="mx-auto mt-1 max-w-sm text-xs text-[#64748B]">
                        {{ current_tab === 'browse' ? 'Check back soon for seasonal promotions and store discounts.' : 'Check the "Claim New Vouchers" tab to discover available discounts!' }}
                    </p>

                    <button
                        v-if="current_tab !== 'browse'"
                        type="button"
                        class="mt-4 inline-flex rounded-xl bg-[#087F8C] px-4 py-2 text-xs font-extrabold text-white hover:bg-[#066B76]"
                        @click="selectTab('browse')"
                    >
                        Browse Available Vouchers
                    </button>
                </div>
            </div>
        </main>
    </BuyerLayout>
</template>

