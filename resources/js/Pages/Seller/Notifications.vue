<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'

const props = defineProps({
    notifications: {
        type: Array,
        default: () => [],
    },
})

const selectedCategory = ref('all')
const showUnreadOnly = ref(false)

const unreadCount = computed(() => {
    return props.notifications.filter(
        (notification) => !notification.read_at
    ).length
})

const categoryCounts = computed(() => {
    const list = props.notifications
    return {
        all: list.length,
        low_stock: list.filter((n) => n.type === 'low_stock').length,
        review: list.filter((n) => n.type === 'review').length,
        return_request: list.filter((n) => n.type === 'return_request' || n.type === 'return').length,
        payout: list.filter((n) => n.type === 'payout' || n.type === 'payout_status').length,
        order: list.filter((n) => n.type === 'order' || n.type === 'new_order' || n.type === 'delivery').length,
    }
})

const filteredNotifications = computed(() => {
    return props.notifications.filter((notification) => {
        if (showUnreadOnly.value && notification.read_at) {
            return false
        }

        if (selectedCategory.value === 'all') {
            return true
        }

        if (selectedCategory.value === 'low_stock') {
            return notification.type === 'low_stock'
        }

        if (selectedCategory.value === 'review') {
            return notification.type === 'review'
        }

        if (selectedCategory.value === 'return_request') {
            return notification.type === 'return_request' || notification.type === 'return'
        }

        if (selectedCategory.value === 'payout') {
            return notification.type === 'payout' || notification.type === 'payout_status'
        }

        if (selectedCategory.value === 'order') {
            return notification.type === 'order' || notification.type === 'new_order' || notification.type === 'delivery'
        }

        return true
    })
})

const formatDate = (value) => {
    if (!value) {
        return '—'
    }

    return new Date(value).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    })
}

const markRead = (notification) => {
    if (!notification?.id || notification.read_at) {
        return
    }

    router.patch(
        route('seller.notifications.read', notification.id),
        {},
        {
            preserveScroll: true,
        }
    )
}

const deliveryUrl = (notification) => {
    const url = notification?.url
    return notification?.type === 'delivery' && typeof url === 'string' && /^\/seller\/orders\/[1-9][0-9]*$/.test(url) ? url : null
}

const openDelivery = (notification) => {
    const url = deliveryUrl(notification)
    if (!url) return
    if (notification.read_at) return router.visit(url)
    router.patch(route('seller.notifications.read', notification.id), {}, {
        preserveScroll: true,
        onSuccess: () => router.visit(url),
    })
}

const markAllRead = () => {
    if (!unreadCount.value) {
        return
    }

    router.post(
        route('seller.notifications.read-all'),
        {},
        {
            preserveScroll: true,
        }
    )
}

const notificationTheme = (type) => {
    switch (type) {
        case 'low_stock':
            return {
                bg: 'bg-amber-50 text-amber-600 border-amber-200',
                badge: 'bg-amber-50 text-amber-700 border-amber-200',
                label: 'Low Stock',
            }
        case 'review':
            return {
                bg: 'bg-yellow-50 text-yellow-600 border-yellow-200',
                badge: 'bg-yellow-50 text-yellow-700 border-yellow-200',
                label: 'Review',
            }
        case 'return_request':
        case 'return':
            return {
                bg: 'bg-rose-50 text-rose-600 border-rose-200',
                badge: 'bg-rose-50 text-rose-700 border-rose-200',
                label: 'Return / Refund',
            }
        case 'payout':
        case 'payout_status':
            return {
                bg: 'bg-emerald-50 text-emerald-600 border-emerald-200',
                badge: 'bg-emerald-50 text-emerald-700 border-emerald-200',
                label: 'Payout Alert',
            }
        case 'order':
        case 'new_order':
        case 'delivery':
            return {
                bg: 'bg-teal-50 text-teal-600 border-teal-200',
                badge: 'bg-teal-50 text-teal-700 border-teal-200',
                label: 'Order',
            }
        default:
            return {
                bg: 'bg-[#E8F7F6] text-[#087F8C] border-teal-100',
                badge: 'bg-slate-100 text-slate-700 border-slate-200',
                label: 'Store Alert',
            }
    }
}
</script>

<template>
    <Head title="Seller Notifications" />

    <SellerLayout>
        <div class="w-full space-y-6 pb-20">

                <!-- Page Header -->
                <div
                    class="flex flex-col gap-4 border-b border-[#E5E7EB] pb-5 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-[#087F8C]">
                            <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                            <span>Notifications</span>
                        </div>

                        <div class="mt-1 flex flex-wrap items-center gap-2.5">
                            <h1
                                class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl"
                            >
                                Notifications & Alerts
                            </h1>

                            <span
                                v-if="unreadCount"
                                class="inline-flex items-center rounded-full bg-[#E8F7F6] px-2.5 py-1 text-[10px] font-bold text-[#087F8C]"
                            >
                                {{ unreadCount }} unread
                            </span>
                        </div>

                        <p class="mt-0.5 text-xs text-slate-500">
                            Stay updated with low-stock alerts, customer reviews, return requests, and store activity.
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <label class="flex cursor-pointer items-center gap-2 text-xs font-semibold text-slate-600">
                            <input
                                v-model="showUnreadOnly"
                                type="checkbox"
                                class="rounded border-slate-300 text-[#087F8C] focus:ring-[#087F8C]"
                            />
                            Unread only
                        </label>

                        <button
                            type="button"
                            :disabled="!unreadCount"
                            @click="markAllRead"
                            class="inline-flex w-fit items-center justify-center gap-2 rounded-xl border border-[#E5E7EB] bg-white px-4 py-2.5 text-xs font-semibold text-[#475569] shadow-sm transition hover:border-[#087F8C] hover:text-[#087F8C] disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <svg
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    d="M5 12l4 4L19 6"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                            Mark all as read
                        </button>
                    </div>
                </div>

                <!-- Category Filter Pills (SELLER-58 to SELLER-61) -->
                <div class="mt-5 flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-1.5 text-xs font-semibold transition"
                        :class="selectedCategory === 'all' ? 'bg-[#087F8C] text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'"
                        @click="selectedCategory = 'all'"
                    >
                        <span>All Alerts</span>
                        <span class="rounded-full px-1.5 py-0.2 text-[10px]" :class="selectedCategory === 'all' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'">{{ categoryCounts.all }}</span>
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-1.5 text-xs font-semibold transition"
                        :class="selectedCategory === 'low_stock' ? 'bg-[#087F8C] text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'"
                        @click="selectedCategory = 'low_stock'"
                    >
                        <svg class="h-3.5 w-3.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>Low Stock</span>
                        <span class="rounded-full px-1.5 py-0.2 text-[10px]" :class="selectedCategory === 'low_stock' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'">{{ categoryCounts.low_stock }}</span>
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-1.5 text-xs font-semibold transition"
                        :class="selectedCategory === 'review' ? 'bg-[#087F8C] text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'"
                        @click="selectedCategory = 'review'"
                    >
                        <svg class="h-3.5 w-3.5 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                        <span>Reviews</span>
                        <span class="rounded-full px-1.5 py-0.2 text-[10px]" :class="selectedCategory === 'review' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'">{{ categoryCounts.review }}</span>
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-1.5 text-xs font-semibold transition"
                        :class="selectedCategory === 'return_request' ? 'bg-[#087F8C] text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'"
                        @click="selectedCategory = 'return_request'"
                    >
                        <svg class="h-3.5 w-3.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 15v-1a4 4 0 00-4-4H4m0 0l4 4m-4-4l4-4m12 5v2a3 3 0 01-3 3H9" />
                        </svg>
                        <span>Returns & Refunds</span>
                        <span class="rounded-full px-1.5 py-0.2 text-[10px]" :class="selectedCategory === 'return_request' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'">{{ categoryCounts.return_request }}</span>
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-1.5 text-xs font-semibold transition"
                        :class="selectedCategory === 'payout' ? 'bg-[#087F8C] text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'"
                        @click="selectedCategory = 'payout'"
                    >
                        <svg class="h-3.5 w-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Payouts</span>
                        <span class="rounded-full px-1.5 py-0.2 text-[10px]" :class="selectedCategory === 'payout' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'">{{ categoryCounts.payout }}</span>
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl px-3.5 py-1.5 text-xs font-semibold transition"
                        :class="selectedCategory === 'order' ? 'bg-[#087F8C] text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'"
                        @click="selectedCategory = 'order'"
                    >
                        <svg class="h-3.5 w-3.5 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        <span>Orders</span>
                        <span class="rounded-full px-1.5 py-0.2 text-[10px]" :class="selectedCategory === 'order' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'">{{ categoryCounts.order }}</span>
                    </button>
                </div>

                <!-- Notification List -->
                <section
                    class="mt-5 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                >
                    <!-- Section Header -->
                    <div
                        class="flex items-center justify-between gap-3 border-b border-[#E5E7EB] px-5 py-4"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                            >
                                <svg
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                >
                                    <path
                                        d="M18 8a6 6 0 00-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                    <path
                                        d="M10 21h4"
                                        stroke-linecap="round"
                                    />
                                </svg>
                            </div>

                            <div>
                                <h2
                                    class="text-base font-bold text-[#1F2937]"
                                >
                                    Store Activity & Notifications
                                </h2>

                                <p
                                    class="mt-0.5 text-xs text-[#64748B]"
                                >
                                    Showing {{ filteredNotifications.length }} alert(s)
                                </p>
                            </div>
                        </div>

                        <span
                            class="hidden rounded-full border border-[#E5E7EB] bg-[#F8FAF9] px-2.5 py-1 text-[10px] font-semibold text-[#64748B] sm:inline-flex"
                        >
                            {{ filteredNotifications.length }}
                            {{ filteredNotifications.length === 1 ? 'record' : 'records' }}
                        </span>
                    </div>

                    <!-- Empty State -->
                    <div
                        v-if="!filteredNotifications.length"
                        class="flex flex-col items-center justify-center px-5 py-14 text-center"
                    >
                        <div
                            class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#E8F7F6] text-[#087F8C]"
                        >
                            <svg
                                class="h-7 w-7"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.6"
                            >
                                <path
                                    d="M18 8a6 6 0 00-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                                <path
                                    d="M10 21h4"
                                    stroke-linecap="round"
                                />
                            </svg>
                        </div>

                        <h3
                            class="mt-4 text-sm font-bold text-[#1F2937]"
                        >
                            No notifications match this filter
                        </h3>

                        <p
                            class="mt-1 max-w-sm text-xs leading-5 text-[#94A3B8]"
                        >
                            All clear! Important alerts about low stock, reviews, returns, and payouts will appear here.
                        </p>
                    </div>

                    <!-- Notifications List -->
                    <div
                        v-else
                        class="divide-y divide-[#E5E7EB]"
                    >
                        <article
                            v-for="notification in filteredNotifications"
                            :key="notification.id"
                            class="relative px-5 py-4 transition"
                            :class="
                                notification.read_at
                                    ? 'bg-white hover:bg-[#FAFBFB]'
                                    : 'bg-[#F8FCFC] hover:bg-[#F2FAF9]'
                            "
                        >
                            <!-- Unread Indicator -->
                            <span
                                v-if="!notification.read_at"
                                class="absolute bottom-4 left-0 top-4 w-1 rounded-r-full bg-[#087F8C]"
                            ></span>

                            <div
                                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="flex min-w-0 gap-3">

                                    <!-- Notification Icon (Type specific) -->
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border"
                                        :class="notificationTheme(notification.type).bg"
                                    >
                                        <!-- Low Stock Icon (SELLER-58) -->
                                        <svg
                                            v-if="notification.type === 'low_stock'"
                                            class="h-4 w-4"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>

                                        <!-- Review Icon (SELLER-59) -->
                                        <svg
                                            v-else-if="notification.type === 'review'"
                                            class="h-4 w-4"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                        </svg>

                                        <!-- Return / Refund Icon (SELLER-60) -->
                                        <svg
                                            v-else-if="notification.type === 'return_request' || notification.type === 'return'"
                                            class="h-4 w-4"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 15v-1a4 4 0 00-4-4H4m0 0l4 4m-4-4l4-4m12 5v2a3 3 0 01-3 3H9" />
                                        </svg>

                                        <!-- Payout Icon (SELLER-61) -->
                                        <svg
                                            v-else-if="notification.type === 'payout' || notification.type === 'payout_status'"
                                            class="h-4 w-4"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>

                                        <!-- Order Icon -->
                                        <svg
                                            v-else-if="notification.type === 'order' || notification.type === 'new_order'"
                                            class="h-4 w-4"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                        </svg>

                                        <!-- Default Bell Icon -->
                                        <svg
                                            v-else
                                            class="h-4 w-4"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.7"
                                        >
                                            <path d="M18 8a6 6 0 00-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" stroke-linecap="round" stroke-linejoin="round" />
                                            <path d="M10 21h4" stroke-linecap="round" />
                                        </svg>
                                    </div>

                                    <!-- Content -->
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span
                                                class="inline-flex items-center rounded-md border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                                                :class="notificationTheme(notification.type).badge"
                                            >
                                                {{ notificationTheme(notification.type).label }}
                                            </span>

                                            <h3
                                                class="text-sm font-bold"
                                                :class="
                                                    notification.read_at
                                                        ? 'text-[#475569]'
                                                        : 'text-[#1F2937]'
                                                "
                                            >
                                                {{ notification.title || 'Notification' }}
                                            </h3>

                                            <span
                                                v-if="!notification.read_at"
                                                class="rounded-full bg-[#E8F7F6] px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-[#087F8C]"
                                            >
                                                New
                                            </span>
                                        </div>

                                        <p
                                            class="mt-1 text-sm leading-6"
                                            :class="
                                                notification.read_at
                                                    ? 'text-[#64748B]'
                                                    : 'text-[#475569]'
                                            "
                                        >
                                            {{ notification.message }}
                                        </p>

                                        <div class="mt-2.5 flex flex-wrap items-center gap-4 text-xs">
                                            <div
                                                class="flex items-center gap-1.5 text-[11px] text-[#94A3B8]"
                                            >
                                                <svg
                                                    class="h-3.5 w-3.5"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="1.7"
                                                >
                                                    <circle cx="12" cy="12" r="9" />
                                                    <path d="M12 7v5l3 2" stroke-linecap="round" stroke-linejoin="round" />
                                                </svg>
                                                {{ formatDate(notification.created_at) }}
                                            </div>

                                            <!-- Direct Link if available -->
                                            <Link
                                                v-if="notification.url"
                                                :href="notification.url"
                                                @click="markRead(notification)"
                                                class="inline-flex items-center gap-1 font-semibold text-[#087F8C] hover:underline"
                                            >
                                                <span>View details</span>
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                                </svg>
                                            </Link>
                                        </div>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex items-center gap-2">
                                    <button v-if="deliveryUrl(notification)" type="button" @click="openDelivery(notification)" class="inline-flex w-fit shrink-0 rounded-lg border border-[#BFE3E0] px-3 py-2 text-xs font-semibold text-[#087F8C] hover:bg-[#E8F7F6]">
                                        View delivery details
                                    </button>
                                    <button
                                        v-if="!notification.read_at"
                                        type="button"
                                        @click="markRead(notification)"
                                        class="inline-flex w-fit shrink-0 items-center gap-1.5 rounded-lg border border-[#BFE3E0] bg-white px-3 py-1.5 text-[11px] font-bold text-[#087F8C] transition hover:border-[#087F8C] hover:bg-[#E8F7F6]"
                                    >
                                        <svg
                                            class="h-3.5 w-3.5"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >
                                            <path
                                                d="M5 12l4 4L19 6"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            />
                                        </svg>
                                        Mark read
                                    </button>

                                    <span
                                        v-else
                                        class="inline-flex w-fit shrink-0 items-center gap-1.5 rounded-lg bg-[#F8FAF9] px-2.5 py-1 text-[10px] font-semibold text-[#94A3B8]"
                                    >
                                        <svg
                                            class="h-3 w-3"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >
                                            <path
                                                d="M5 12l4 4L19 6"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            />
                                        </svg>
                                        Read
                                    </span>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>
            </div>
    </SellerLayout>
</template>
