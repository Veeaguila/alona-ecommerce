<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'

const props = defineProps({
    notifications: {
        type: Array,
        default: () => [],
    },
})

const activeTab = ref('all')

const tabs = [
    { key: 'all', label: 'All Notifications' },
    { key: 'order', label: '📦 Orders' },
    { key: 'shipping', label: '🚚 Shipping' },
    { key: 'message', label: '💬 Messages' },
    { key: 'complaint', label: '🛡️ Support & Tickets' },
    { key: 'promotion', label: '🎟️ Vouchers & Promos' },
]

const filteredNotifications = computed(() => {
    if (activeTab.value === 'all') {
        return props.notifications
    }
    return props.notifications.filter(n => (n.type || 'general') === activeTab.value)
})

const unreadCount = computed(() => {
    return props.notifications.filter(n => !n.read_at).length
})

const readAll = () => {
    useForm({}).post(route('buyer.notifications.read-all'), {
        preserveScroll: true,
    })
}

const handleNotificationClick = item => {
    if (!item.read_at) {
        useForm({}).patch(route('buyer.notifications.read', item.id), {
            preserveScroll: true,
            onSuccess: () => {
                if (item.action_url) {
                    router.visit(item.action_url)
                }
            },
        })
    } else if (item.action_url) {
        router.visit(item.action_url)
    }
}

const getTypeMeta = type => {
    switch (type) {
        case 'order':
            return {
                label: 'Order Update',
                icon: '📦',
                badgeBg: 'bg-blue-50 text-blue-700 border-blue-100',
                iconBg: 'bg-blue-50 text-blue-600',
            }
        case 'payment':
            return {
                label: 'Payment',
                icon: '💳',
                badgeBg: 'bg-emerald-50 text-emerald-700 border-emerald-100',
                iconBg: 'bg-emerald-50 text-emerald-600',
            }
        case 'shipping':
            return {
                label: 'Shipping & Delivery',
                icon: '🚚',
                badgeBg: 'bg-amber-50 text-amber-700 border-amber-100',
                iconBg: 'bg-amber-50 text-amber-600',
            }
        case 'message':
            return {
                label: 'Seller Message',
                icon: '💬',
                badgeBg: 'bg-teal-50 text-teal-700 border-teal-100',
                iconBg: 'bg-teal-50 text-teal-600',
            }
        case 'promotion':
            return {
                label: 'Promotion & Voucher',
                icon: '🎟️',
                badgeBg: 'bg-purple-50 text-purple-700 border-purple-100',
                iconBg: 'bg-purple-50 text-purple-600',
            }
        case 'complaint':
            return {
                label: 'Support & Resolution',
                icon: '🛡️',
                badgeBg: 'bg-rose-50 text-rose-700 border-rose-100',
                iconBg: 'bg-rose-50 text-rose-600',
            }
        default:
            return {
                label: 'System Notice',
                icon: '🔔',
                badgeBg: 'bg-gray-50 text-gray-700 border-gray-100',
                iconBg: 'bg-[#E8F7F6] text-[#087F8C]',
            }
    }
}

const formatDate = dateStr => {
    if (!dateStr) return ''
    const date = new Date(dateStr)
    return date.toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}
</script>

<template>
    <Head title="Notifications" />

    <BuyerLayout>
        <main class="min-h-[calc(100vh-80px)] w-full min-w-0 overflow-x-hidden bg-[#F8FAF9]">
            <div class="mx-auto w-full max-w-[1536px] px-4 py-6 pb-16 sm:px-6 sm:py-8 lg:px-8 2xl:px-10 lg:py-10">

                <!-- HEADER -->
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#F4B942]"></span>
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#087F8C] sm:text-xs">
                                Inbox & Activity Center
                            </p>
                        </div>

                        <h1 class="mt-1 text-2xl font-black tracking-tight text-[#1F2937] sm:text-3xl">
                            Notifications
                        </h1>

                        <p class="mt-1 text-xs leading-5 text-[#64748B] sm:text-sm">
                            Stay up to date with order progress, messages, vouchers, and customer support responses.
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <span
                            v-if="unreadCount > 0"
                            class="rounded-full bg-[#E8F7F6] px-3 py-1 text-xs font-extrabold text-[#087F8C]"
                        >
                            {{ unreadCount }} Unread
                        </span>

                        <button
                            v-if="unreadCount > 0"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-[#087F8C]/20 bg-white px-3.5 py-2 text-xs font-bold text-[#087F8C] shadow-xs transition hover:bg-[#E8F7F6]"
                            @click="readAll"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            Mark all as read
                        </button>
                    </div>
                </div>

                <!-- CATEGORY TABS -->
                <div class="mt-6 flex overflow-x-auto border-b border-gray-200 pb-px scrollbar-none">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        class="shrink-0 border-b-2 px-4 py-3 text-xs font-bold transition sm:text-sm"
                        :class="
                            activeTab === tab.key
                                ? 'border-[#087F8C] text-[#087F8C]'
                                : 'border-transparent text-gray-500 hover:text-gray-800'
                        "
                        @click="activeTab = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <!-- NOTIFICATIONS LIST -->
                <section class="mt-6 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-xs">
                    <div v-if="filteredNotifications.length" class="divide-y divide-[#EEF1F2]">
                        <div
                            v-for="item in filteredNotifications"
                            :key="item.id"
                            role="button"
                            tabindex="0"
                            class="group relative block w-full p-4.5 text-left transition hover:bg-[#F8FAF9] sm:p-5 cursor-pointer"
                            :class="!item.read_at ? 'bg-[#F3FBFA]/60' : 'bg-white'"
                            @click="handleNotificationClick(item)"
                            @keydown.enter="handleNotificationClick(item)"
                        >
                            <!-- UNREAD ACCENT STRIP -->
                            <span
                                v-if="!item.read_at"
                                class="absolute inset-y-0 left-0 w-1.5 bg-[#087F8C]"
                            ></span>

                            <div class="flex min-w-0 items-start gap-3.5 sm:gap-4.5">
                                <!-- TYPE ICON -->
                                <div
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-xl shadow-xs"
                                    :class="getTypeMeta(item.type).iconBg"
                                >
                                    <span>{{ getTypeMeta(item.type).icon }}</span>
                                </div>

                                <!-- CONTENT -->
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span
                                                class="rounded-md border px-2 py-0.5 text-[10px] font-bold"
                                                :class="getTypeMeta(item.type).badgeBg"
                                            >
                                                {{ getTypeMeta(item.type).label }}
                                            </span>

                                            <h3
                                                class="text-xs font-bold sm:text-sm"
                                                :class="!item.read_at ? 'text-[#1F2937]' : 'text-gray-700'"
                                            >
                                                {{ item.title }}
                                            </h3>

                                            <span
                                                v-if="!item.read_at"
                                                class="inline-block h-2 w-2 rounded-full bg-[#087F8C]"
                                            ></span>
                                        </div>

                                        <span class="text-[10px] text-gray-400 sm:text-xs">
                                            {{ formatDate(item.created_at) }}
                                        </span>
                                    </div>

                                    <p class="mt-1 text-xs leading-relaxed text-gray-600 sm:text-sm">
                                        {{ item.message }}
                                    </p>

                                    <!-- ACTION LINK HINT -->
                                    <div v-if="item.action_url" class="mt-2.5 flex items-center gap-1.5 text-xs font-bold text-[#087F8C]">
                                        <span>View details</span>
                                        <svg class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- EMPTY STATE -->
                    <div v-else class="px-6 py-16 text-center sm:py-20">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-[#E8F7F6] text-2xl text-[#087F8C]">
                            🔔
                        </div>

                        <h2 class="mt-4 text-base font-bold text-gray-900">
                            No notifications in this tab
                        </h2>

                        <p class="mx-auto mt-1 max-w-sm text-xs text-gray-500">
                            You're all caught up! New notifications about your orders, chat messages, and support updates will appear here.
                        </p>
                    </div>
                </section>
            </div>
        </main>
    </BuyerLayout>
</template>
