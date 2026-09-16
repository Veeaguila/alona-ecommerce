<script setup>
import { computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'

const props = defineProps({
    notifications: {
        type: Array,
        default: () => [],
    },
})

const unreadCount = computed(() => {
    return props.notifications.filter(
        (notification) => !notification.read_at
    ).length
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
</script>

<template>
    <Head title="Seller Notifications" />

    <SellerLayout>
        <div class="min-h-[calc(100vh-68px)] bg-[#F8FAF9] pb-20">
            <div class="mx-auto max-w-5xl px-4 py-5 sm:px-6 lg:px-8">

                <!-- Page Header -->
                <div
                    class="flex flex-col gap-4 border-b border-[#E5E7EB] pb-5 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div class="min-w-0">
                        <p
                            class="text-xs font-bold uppercase tracking-[0.16em] text-[#087F8C]"
                        >
                            Seller Center
                        </p>

                        <div class="mt-1 flex flex-wrap items-center gap-2.5">
                            <h1
                                class="text-2xl font-bold tracking-tight text-[#1F2937] sm:text-3xl"
                            >
                                Notifications
                            </h1>

                            <span
                                v-if="unreadCount"
                                class="inline-flex items-center rounded-full bg-[#E8F7F6] px-2.5 py-1 text-[10px] font-bold text-[#087F8C]"
                            >
                                {{ unreadCount }} unread
                            </span>
                        </div>

                        <p class="mt-1 text-sm text-[#64748B]">
                            Stay updated with your store activity and important alerts.
                        </p>
                    </div>

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
                                    Recent Notifications
                                </h2>

                                <p
                                    class="mt-0.5 text-xs text-[#64748B]"
                                >
                                    Your latest store updates and alerts.
                                </p>
                            </div>
                        </div>

                        <span
                            class="hidden rounded-full border border-[#E5E7EB] bg-[#F8FAF9] px-2.5 py-1 text-[10px] font-semibold text-[#64748B] sm:inline-flex"
                        >
                            {{ props.notifications.length }}
                            {{ props.notifications.length === 1 ? 'notification' : 'notifications' }}
                        </span>
                    </div>

                    <!-- Empty State -->
                    <div
                        v-if="!props.notifications.length"
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
                            No notifications yet
                        </h3>

                        <p
                            class="mt-1 max-w-sm text-xs leading-5 text-[#94A3B8]"
                        >
                            You'll see important store updates, order activity,
                            and other alerts here.
                        </p>

                        <Link
                            :href="route('seller.dashboard')"
                            class="mt-4 inline-flex items-center gap-1.5 text-xs font-bold text-[#087F8C] hover:underline"
                        >
                            Back to Dashboard

                            <svg
                                class="h-3.5 w-3.5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    d="M5 12h14"
                                    stroke-linecap="round"
                                />
                                <path
                                    d="M13 6l6 6-6 6"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </Link>
                    </div>

                    <!-- Notifications -->
                    <div
                        v-else
                        class="divide-y divide-[#E5E7EB]"
                    >
                        <article
                            v-for="notification in props.notifications"
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

                                    <!-- Notification Icon -->
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                                        :class="
                                            notification.read_at
                                                ? 'bg-[#F8FAF9] text-[#94A3B8]'
                                                : 'bg-[#E8F7F6] text-[#087F8C]'
                                        "
                                    >
                                        <svg
                                            class="h-[18px] w-[18px]"
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

                                    <!-- Content -->
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
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

                                        <div
                                            class="mt-2 flex items-center gap-1.5 text-[10px] text-[#94A3B8]"
                                        >
                                            <svg
                                                class="h-3.5 w-3.5"
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
                                                    d="M12 7v5l3 2"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                />
                                            </svg>

                                            {{ formatDate(notification.created_at) }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Mark Read -->
                                <button
                                    v-if="!notification.read_at"
                                    type="button"
                                    @click="markRead(notification)"
                                    class="inline-flex w-fit shrink-0 items-center gap-1.5 rounded-lg border border-[#BFE3E0] bg-white px-3 py-2 text-[10px] font-bold text-[#087F8C] transition hover:border-[#087F8C] hover:bg-[#E8F7F6]"
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
                                    class="inline-flex w-fit shrink-0 items-center gap-1.5 rounded-lg bg-[#F8FAF9] px-3 py-2 text-[10px] font-semibold text-[#94A3B8]"
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

                                    Read
                                </span>
                            </div>
                        </article>
                    </div>
                </section>
            </div>
        </div>
    </SellerLayout>
</template>