<template>
    <div class="min-h-screen bg-[#F8FAF9] text-[#1F2937] print:bg-white print:min-h-0">

        <!-- Mobile Overlay -->
        <transition name="fade">
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden print:hidden"
                @click="closeSidebar"
            ></div>
        </transition>

        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-40 w-[240px] transform border-r border-[#065A63]/30 bg-gradient-to-b from-[#087F8C] via-[#076F7B] to-[#065A63] text-white shadow-xl transition-transform duration-200 lg:translate-x-0 print:hidden"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex h-full min-h-0 flex-col">

                <!-- Logo -->
                <div
                    class="relative flex h-[80px] shrink-0 items-center border-b border-white/10 px-4 sm:px-5"
                >
                    <Link
                        :href="route('seller.dashboard')"
                        class="flex items-center gap-3 transition hover:opacity-95"
                        @click="closeSidebar"
                    >
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white p-1.5 shadow-sm"
                        >
                            <img
                                src="/images/alona-icon.png"
                                alt="Alona"
                                class="h-full w-full object-contain"
                            />
                        </div>

                        <div class="flex flex-col min-w-0">
                            <span class="text-base font-extrabold tracking-tight text-white leading-tight">
                                Alona
                            </span>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-teal-100/90 leading-tight mt-0.5">
                                Seller Center
                            </span>
                        </div>
                    </Link>

                    <!-- Mobile Close -->
                    <button
                        type="button"
                        class="absolute right-3 rounded-lg p-2 text-white/70 hover:bg-white/10 hover:text-white lg:hidden"
                        @click="closeSidebar"
                        aria-label="Close sidebar"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>
                <!-- Scrollable Navigation -->
                <div
                    class="sidebar-scrollbar min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 pb-4"
                >

                    <!-- Seller Menu -->
                    <div class="pt-5">
                        <p
                            class="mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.14em] text-teal-100/70"
                        >
                            Seller Menu
                        </p>

                        <nav class="space-y-1">
                            <Link
                                v-for="item in navigation"
                                :key="item.key"
                                :href="item.href"
                                class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition"
                                :class="
                                    isActive(item)
                                        ? 'bg-[#F5A711] text-[#043E44] font-bold shadow-md'
                                        : 'text-white/80 hover:bg-white/10 hover:text-white'
                                "
                                @click="closeSidebar"
                            >
                                <!-- Overview -->
                                <svg
                                    v-if="item.key === 'dashboard'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-[18px] w-[18px] shrink-0"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M3 13h8V3H3v10zm10 8h8V11h-8v10zM3 21h8v-4H3v4zm10-18v4h8V3h-8z"
                                    />
                                </svg>

                                <!-- Inventory -->
                                <svg
                                    v-else-if="item.key === 'inventory'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-[18px] w-[18px] shrink-0"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M20 7l-8-4-8 4m16 0v10l-8 4-8-4V7m16 0l-8 4-8-4m8 4v10"
                                    />
                                </svg>

                                <!-- Stock -->
                                <svg
                                    v-else-if="item.key === 'stock'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-[18px] w-[18px] shrink-0"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M9 17v-2m3 2v-4m3 4v-6m4 9H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v12a2 2 0 01-2 2z"
                                    />
                                </svg>

                                <!-- Orders -->
                                <svg
                                    v-else-if="item.key === 'orders'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-[18px] w-[18px] shrink-0"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"
                                    />
                                </svg>

                                <!-- Returns & Refunds -->
                                <svg
                                    v-else-if="item.key === 'returns'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-[18px] w-[18px] shrink-0"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                    />
                                </svg>

                                <!-- Vouchers -->
                                <svg
                                    v-else-if="item.key === 'vouchers'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-[18px] w-[18px] shrink-0"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M9 14l6-6m5-3.5a2.5 2.5 0 010 5A2.5 2.5 0 0020 12a2.5 2.5 0 010 5 2.5 2.5 0 00-2.5 2.5H6.5A2.5 2.5 0 004 17a2.5 2.5 0 010-5 2.5 2.5 0 000-5A2.5 2.5 0 006.5 4.5h11A2.5 2.5 0 0020 7z"
                                    />
                                </svg>

                                <!-- Customer Reviews -->
                                <svg
                                    v-else-if="item.key === 'reviews'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-[18px] w-[18px] shrink-0"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"
                                    />
                                </svg>

                                <!-- Reports -->
                                <svg
                                    v-else-if="item.key === 'reports'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-[18px] w-[18px] shrink-0"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M4 19V5m0 14h16M8 16v-5m4 5V7m4 9v-8"
                                    />
                                </svg>

                                <!-- Messages -->
                                <svg
                                    v-else-if="item.key === 'messages'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-[18px] w-[18px] shrink-0"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M8 10h8M8 14h5m7-2a8 8 0 01-8 8 8.7 8.7 0 01-4-.95L4 20l.95-4A8 8 0 1120 12z"
                                    />
                                </svg>

                                <span class="truncate">
                                    {{ item.label }}
                                </span>
                            </Link>
                        </nav>
                    </div>

                    <!-- Divider -->
                    <div class="my-4 border-t border-white/10"></div>

                    <!-- Store Management -->
                    <div>
                        <p
                            class="mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.14em] text-teal-100/70"
                        >
                            Store Management
                        </p>

                        <nav class="space-y-1">
                            <Link
                                v-for="item in accountNavigation"
                                :key="item.key"
                                :href="item.href"
                                class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition"
                                :class="
                                    isActive(item)
                                        ? 'bg-[#F5A711] text-[#043E44] font-bold shadow-md'
                                        : 'text-white/80 hover:bg-white/10 hover:text-white'
                                "
                                @click="closeSidebar"
                            >
                                <!-- My Store -->
                                <svg
                                    v-if="item.key === 'store'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-[18px] w-[18px] shrink-0"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M3 10l2-6h14l2 6M4 10v9a2 2 0 002 2h12a2 2 0 002-2v-9M3 10h18M8 10v3m4-3v3m4-3v3"
                                    />
                                </svg>

                                <!-- Categories -->
                                <svg
                                    v-else-if="item.key === 'categories'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-[18px] w-[18px] shrink-0"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"
                                    />
                                </svg>

                                <!-- My Account -->
                                <svg
                                    v-else-if="item.key === 'account'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-[18px] w-[18px] shrink-0"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <circle cx="12" cy="8" r="3.5" />
                                    <path
                                        d="M5 20c.7-3.3 3.2-5 7-5s6.3 1.7 7 5"
                                        stroke-linecap="round"
                                    />
                                </svg>

                                <!-- Settings -->
                                <svg
                                    v-else-if="item.key === 'settings'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-[18px] w-[18px] shrink-0"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M10.5 3h3l.7 2.2a7.7 7.7 0 012 1.2l2.2-.4 1.5 2.6-1.5 1.7c.1.5.2 1 .2 1.7s-.1 1.2-.2 1.7l1.5 1.7-1.5 2.6-2.2-.4a7.7 7.7 0 01-2 1.2l-.7 2.2h-3l-.7-2.2a7.7 7.7 0 01-2-1.2l-2.2.4-1.5-2.6 1.5-1.7a7 7 0 010-3.4L4.1 8.6l1.5-2.6 2.2.4a7.7 7.7 0 012-1.2L10.5 3z"
                                    />
                                    <circle
                                        cx="12"
                                        cy="12"
                                        r="2.5"
                                    />
                                </svg>

                                <span class="truncate">
                                    {{ item.label }}
                                </span>
                            </Link>
                        </nav>
                    </div>
                </div>

                <!-- Sidebar Bottom: User Card & Logout on the side -->
                <div class="shrink-0 border-t border-white/10 bg-black/10 p-3">
                    <div
                        class="flex items-center justify-between gap-2 rounded-xl border border-white/10 bg-white/10 p-2 backdrop-blur-xs transition hover:bg-white/15"
                    >
                        <!-- Profile Info (Navigates to Account Management) -->
                        <Link
                            :href="route('seller.account')"
                            class="group flex min-w-0 flex-1 items-center gap-2.5 transition"
                            title="Account Management"
                            @click="closeSidebar"
                        >
                            <!-- Profile Photo / Initial -->
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white text-xs font-black text-[#087F8C] shadow-xs"
                            >
                                <img
                                    v-if="profilePhoto"
                                    :src="profilePhoto"
                                    :alt="profileName"
                                    class="h-full w-full object-cover"
                                />
                                <span v-else>
                                    {{ profileInitial }}
                                </span>
                            </span>

                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-xs font-bold text-white group-hover:text-teal-200"
                                >
                                    {{ profileName }}
                                </p>
                                <p class="text-[10px] text-teal-100/70">
                                    My account
                                </p>
                            </div>
                        </Link>

                        <!-- Logout Button on the side -->
                        <button
                            type="button"
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-rose-200 transition hover:bg-rose-500/20 hover:text-white active:scale-95 disabled:opacity-50"
                            :title="form.processing ? 'Logging out...' : 'Log out'"
                            :aria-label="form.processing ? 'Logging out...' : 'Log out'"
                            :disabled="form.processing"
                            @click="logout"
                        >
                            <svg
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    d="M10 17l5-5-5-5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                                <path
                                    d="M15 12H3"
                                    stroke-linecap="round"
                                />
                                <path
                                    d="M19 4h1a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-1"
                                    stroke-linecap="round"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="lg:pl-[240px] print:pl-0">

            <!-- Header -->
            <header
                class="sticky top-0 z-20 h-[68px] border-b border-[#E5E7EB] bg-white/95 backdrop-blur print:hidden"
            >
                <div
                    class="flex h-full items-center justify-between gap-3 px-4 sm:px-6"
                >

                    <!-- Left -->
                    <div class="flex min-w-0 items-center gap-3">

                        <!-- Mobile Menu -->
                        <button
                            type="button"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-[#E5E7EB] bg-white text-slate-600 hover:border-[#16A6A0] hover:text-[#087F8C] lg:hidden"
                            @click="sidebarOpen = true"
                            aria-label="Open sidebar"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                            </svg>
                        </button>

                        <div class="min-w-0">
                            <p
                                class="truncate text-sm font-semibold text-slate-700 sm:text-base"
                            >
                                {{ currentDateLabel }}
                            </p>
                        </div>
                    </div>

                    <!-- Right -->
                    <div class="flex shrink-0 items-center gap-2 sm:gap-3">

                        <!-- Notifications -->
                        <Link
                            :href="route('seller.notifications')"
                            class="relative flex h-10 w-10 items-center justify-center rounded-xl border transition"
                            :class="
                                isNotificationsActive
                                    ? 'border-[#087F8C] bg-[#E8F7F6] text-[#087F8C]'
                                    : 'border-[#E5E7EB] bg-white text-slate-600 hover:border-[#16A6A0] hover:bg-[#E8F7F6] hover:text-[#087F8C]'
                            "
                            title="Notifications"
                            aria-label="Notifications"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-[19px] w-[19px]"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15 17h5l-1.4-1.8A2 2 0 0118 14V10a6 6 0 00-12 0v4a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 01-6 0"
                                />
                            </svg>

                            <!-- Notification Count Badge -->
                            <span
                                v-if="unreadNotificationsCount > 0"
                                class="absolute -right-1 -top-1 flex min-h-[18px] min-w-[18px] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-black leading-none text-white ring-2 ring-white"
                            >
                                {{ unreadNotificationsCount > 99 ? '99+' : unreadNotificationsCount }}
                            </span>
                        </Link>

                        <!-- Store Status -->
                        <div
                            class="ml-1 flex items-center gap-2.5 rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-1.5 transition sm:px-3.5 sm:py-2"
                        >
                            <span
                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 shadow-xs"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-4 w-4"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                            </span>

                            <div class="min-w-0">
                                <p
                                    class="text-[10px] font-semibold uppercase tracking-wider text-[#64748B]"
                                >
                                    Store status
                                </p>

                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="h-1.5 w-1.5 rounded-full bg-emerald-500 ring-2 ring-emerald-100"
                                    ></span>

                                    <span
                                        class="text-xs font-bold text-emerald-700"
                                    >
                                        Active
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page -->
            <main
                class="min-h-[calc(100vh-68px)] px-4 py-5 sm:px-6 sm:py-6 lg:px-7 print:min-h-0 print:p-0 print:m-0"
            >
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'

const sidebarOpen = ref(false)

const form = useForm({})

const page = usePage()

const user = computed(() => page.props.auth?.user ?? {})

const profileName = computed(() => {
    return user.value.store_name || user.value.name || 'Seller'
})

const profileEmail = computed(() => {
    return user.value.email || ''
})

const profilePhoto = computed(() => {
    const path =
        user.value.store_logo_path ??
        user.value.profile_photo_url ??
        user.value.profile_photo ??
        user.value.avatar_url ??
        user.value.avatar ??
        null

    if (!path) {
        return null
    }

    if (
        path.startsWith('http://') ||
        path.startsWith('https://') ||
        path.startsWith('/storage/')
    ) {
        return path
    }

    return `/storage/${path.replace(/^\/+/, '')}`
})

const profileInitial = computed(() => {
    return profileName.value?.charAt(0)?.toUpperCase() || 'S'
})

const navigation = [
    {
        label: 'Dashboard',
        href: route('seller.dashboard'),
        key: 'dashboard',
    },
    {
        label: 'Products',
        href: route('seller.products'),
        key: 'inventory',
    },
    {
        label: 'Stock Monitoring',
        href: route('seller.stock-monitoring'),
        key: 'stock',
    },
    {
        label: 'Orders',
        href: route('seller.orders'),
        key: 'orders',
    },
    {
        label: 'Returns & Refunds',
        href: route('seller.returns'),
        key: 'returns',
    },
    {
        label: 'Discounts & Vouchers',
        href: route('seller.vouchers'),
        key: 'vouchers',
    },
    {
        label: 'Reviews',
        href: route('seller.reviews'),
        key: 'reviews',
    },
    {
        label: 'Sales & Reports',
        href: route('seller.reports'),
        key: 'reports',
    },
    {
        label: 'Messages',
        href: route('seller.messages'),
        key: 'messages',
    },
]

const accountNavigation = [
    {
        label: 'My Store',
        href: route('seller.store'),
        key: 'store',
    },
    {
        label: 'Categories',
        href: route('seller.categories'),
        key: 'categories',
    },
    {
        label: 'My Account',
        href: route('seller.account'),
        key: 'account',
    },
    {
        label: 'Settings',
        href: route('seller.settings'),
        key: 'settings',
    },
]

const unreadNotificationsCount = computed(() => {
    return Number(page.props.sellerCounts?.notifications ?? page.props.unreadNotificationsCount ?? 0)
})

const isNotificationsActive = computed(() => {
    return currentPath.value.startsWith('/seller/notifications')
})

const currentDateLabel = computed(() => {
    return new Date().toLocaleDateString(undefined, {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    })
})

const currentPath = computed(() => {
    return page.url
})

const isActive = (item) => {
    if (item.key === 'dashboard') {
        return currentPath.value === '/seller'
    }

    if (item.key === 'inventory') {
        return (
            currentPath.value.startsWith('/seller/products') &&
            !currentPath.value.startsWith('/seller/stock-monitoring')
        )
    }

    if (item.key === 'stock') {
        return currentPath.value.startsWith('/seller/stock-monitoring')
    }

    if (item.key === 'orders') {
        return currentPath.value.startsWith('/seller/orders')
    }

    if (item.key === 'returns') {
        return currentPath.value.startsWith('/seller/returns')
    }

    if (item.key === 'vouchers') {
        return currentPath.value.startsWith('/seller/vouchers')
    }

    if (item.key === 'reviews') {
        return currentPath.value.startsWith('/seller/reviews')
    }

    if (item.key === 'reports') {
        return currentPath.value.startsWith('/seller/reports')
    }

    if (item.key === 'messages') {
        return currentPath.value.startsWith('/seller/messages')
    }

    if (item.key === 'account') {
        return currentPath.value.startsWith('/seller/account')
    }

    if (item.key === 'store') {
        return currentPath.value.startsWith('/seller/store')
    }

    if (item.key === 'categories') {
        return currentPath.value.startsWith('/seller/categories')
    }

    if (item.key === 'settings') {
        return currentPath.value.startsWith('/seller/settings')
    }

    return false
}

const logout = () => {
    if (form.processing) {
        return
    }

    form.post(route('logout'))
}

const closeSidebar = () => {
    sidebarOpen.value = false
}
</script>

<style scoped>
.sidebar-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.22) transparent;
}

.sidebar-scrollbar::-webkit-scrollbar {
    width: 5px;
}

.sidebar-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.22);
    border-radius: 9999px;
}

.sidebar-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.4);
}

.sidebar-scrollbar::-webkit-scrollbar-button {
    display: none;
    width: 0;
    height: 0;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>