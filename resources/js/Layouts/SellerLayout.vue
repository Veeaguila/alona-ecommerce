<template>
    <div class="min-h-screen bg-[#F8FAF9] text-[#1F2937]">

        <!-- Mobile Overlay -->
        <transition name="fade">
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden"
                @click="closeSidebar"
            ></div>
        </transition>

        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-40 w-[240px] transform border-r border-[#E5E7EB] bg-white transition-transform duration-200 lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex h-full min-h-0 flex-col">

                <!-- Logo -->
                    <div
                        class="relative flex h-[80px] shrink-0 items-center justify-center border-b border-[#E5E7EB] px-5"
                    >
                        <Link
                            :href="route('seller.dashboard')"
                            class="flex items-center justify-center"
                            @click="closeSidebar"
                        >
                            <img
                                src="/images/alona.png"
                                alt="Alona"
                                class="block h-auto max-h-10 w-auto max-w-[170px] object-contain"
                            />
                        </Link>

                        <!-- Mobile Close -->
                        <button
                            type="button"
                            class="absolute right-3 rounded-lg p-2 text-slate-500 hover:bg-[#F8FAF9] hover:text-[#087F8C] lg:hidden"
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
                    class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 pb-4"
                >

                    <!-- Workspace -->
                    <div class="pt-5">
                        <p
                            class="mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400"
                        >
                            Workspace
                        </p>

                        <nav class="space-y-1">
                            <Link
                                v-for="item in navigation"
                                :key="item.key"
                                :href="item.href"
                                class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition"
                                :class="
                                    isActive(item)
                                        ? 'bg-[#E8F7F6] text-[#087F8C]'
                                        : 'text-slate-600 hover:bg-slate-50 hover:text-[#087F8C]'
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
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a3 3 0 006 0M9 5h6M9 12h6m-6 4h4"
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

                    <!-- Configure -->
                    <div class="pt-7">
                        <p
                            class="mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400"
                        >
                            Configure
                        </p>

                        <nav class="space-y-1">
                            <Link
                                v-for="item in secondaryNavigation"
                                :key="item.key"
                                :href="item.href"
                                class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition"
                                :class="
                                    isActive(item)
                                        ? 'bg-[#E8F7F6] text-[#087F8C]'
                                        : 'text-slate-600 hover:bg-slate-50 hover:text-[#087F8C]'
                                "
                                @click="closeSidebar"
                            >
                                <!-- Store -->
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

                                <!-- Notifications -->
                                <svg
                                    v-else
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
                                        d="M15 17h5l-1.4-1.8A2 2 0 0118 14V10a6 6 0 00-12 0v4a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 01-6 0"
                                    />
                                </svg>

                                <span class="truncate">
                                    {{ item.label }}
                                </span>
                            </Link>
                        </nav>
                    </div>
                </div>

                <!-- Store Status -->
                <div class="shrink-0 border-t border-[#E5E7EB] bg-white p-3">
                    <div
                        class="flex items-center gap-3 rounded-xl bg-[#F8FAF9] px-3 py-3"
                    >
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-4 w-4"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                        </span>

                        <div class="min-w-0">
                            <p class="text-xs font-semibold text-slate-700">
                                Store status
                            </p>

                            <div class="mt-0.5 flex items-center gap-1.5">
                                <span
                                    class="h-1.5 w-1.5 rounded-full bg-[#22A06B]"
                                ></span>

                                <span
                                    class="truncate text-[11px] font-medium text-[#22A06B]"
                                >
                                    Active
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="lg:pl-[240px]">

            <!-- Header -->
            <header
                class="sticky top-0 z-20 h-[68px] border-b border-[#E5E7EB] bg-white/95 backdrop-blur"
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
                                class="truncate text-xs font-medium text-slate-400"
                            >
                                Seller Center
                            </p>

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
                            class="relative flex h-10 w-10 items-center justify-center rounded-xl border border-[#E5E7EB] bg-white text-slate-600 transition hover:border-[#16A6A0] hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                            title="Notifications"
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
                        </Link>

                        <!-- Seller Profile -->
                        <div
                            class="hidden items-center gap-2.5 border-l border-[#E5E7EB] pl-3 sm:flex"
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#E8F7F6] text-sm font-bold text-[#087F8C]"
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
                            </div>

                            <div class="max-w-[150px] min-w-0">
                                <p
                                    class="truncate text-sm font-semibold text-slate-700"
                                >
                                    {{ profileName }}
                                </p>

                                <p
                                    v-if="profileEmail"
                                    class="truncate text-[11px] text-slate-400"
                                >
                                    {{ profileEmail }}
                                </p>
                            </div>
                        </div>

                        <!-- Logout -->
                        <button
                            type="button"
                            class="hidden items-center gap-1.5 rounded-xl px-2.5 py-2 text-sm font-semibold text-slate-500 transition hover:bg-red-50 hover:text-[#E85D5D] sm:flex"
                            :disabled="form.processing"
                            @click="logout"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-[17px] w-[17px]"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15 8l4 4m0 0l-4 4m4-4H9m6 8H6a2 2 0 01-2-2V6a2 2 0 012-2h9"
                                />
                            </svg>

                            <span>
                                {{ form.processing ? 'Logging out...' : 'Logout' }}
                            </span>
                        </button>

                        <!-- Mobile Logout -->
                        <button
                            type="button"
                            class="flex h-10 w-10 items-center justify-center rounded-xl text-slate-500 transition hover:bg-red-50 hover:text-[#E85D5D] sm:hidden"
                            :disabled="form.processing"
                            @click="logout"
                            aria-label="Logout"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-[18px] w-[18px]"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15 8l4 4m0 0l-4 4m4-4H9m6 8H6a2 2 0 01-2-2V6a2 2 0 012-2h9"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Page -->
            <main
                class="min-h-[calc(100vh-68px)] px-4 py-5 sm:px-6 sm:py-6 lg:px-7"
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
        label: 'Overview',
        href: route('seller.dashboard'),
        key: 'dashboard',
    },
    {
        label: 'Inventory',
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
        label: 'Vouchers',
        href: route('seller.vouchers'),
        key: 'vouchers',
    },
    {
        label: 'Sales Report',
        href: route('seller.reports'),
        key: 'reports',
    },
    {
        label: 'Chat / Messaging',
        href: route('seller.messages'),
        key: 'messages',
    },
]

const secondaryNavigation = [
    {
        label: 'Store',
        href: route('seller.store'),
        key: 'store',
    },
    {
        label: 'Seller Settings',
        href: route('seller.settings'),
        key: 'settings',
    },
    {
        label: 'Notifications',
        href: route('seller.notifications'),
        key: 'notifications',
    },
]

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

    if (item.key === 'vouchers') {
        return currentPath.value.startsWith('/seller/vouchers')
    }

    if (item.key === 'reports') {
        return currentPath.value.startsWith('/seller/reports')
    }

    if (item.key === 'messages') {
        return currentPath.value.startsWith('/seller/messages')
    }

    if (item.key === 'store') {
        return currentPath.value.startsWith('/seller/store')
    }

    if (item.key === 'settings') {
        return currentPath.value.startsWith('/seller/settings')
    }

    if (item.key === 'notifications') {
        return currentPath.value.startsWith('/seller/notifications')
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
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>