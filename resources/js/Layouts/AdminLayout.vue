<script setup>
import { computed, ref } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'

const props = defineProps({
    active: {
        type: String,
        default: 'dashboard',
    },
})

const sidebarOpen = ref(false)
const form = useForm({})
const page = usePage()

/*
|--------------------------------------------------------------------------
| USER
|--------------------------------------------------------------------------
*/

const user = computed(() => page.props.auth?.user ?? {})

const profileName = computed(() => {
    return user.value.name || 'Administrator'
})

const profileEmail = computed(() => {
    return user.value.email || ''
})

const profilePhoto = computed(() => {
    const path =
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
    return profileName.value?.charAt(0)?.toUpperCase() || 'A'
})

/*
|--------------------------------------------------------------------------
| MAIN NAVIGATION
|--------------------------------------------------------------------------
*/

const navigation = [
    {
        label: 'Dashboard',
        href: route('admin.dashboard'),
        key: 'dashboard',
        icon: 'dashboard',
    },
    {
        label: 'User Accounts',
        href: route('admin.users'),
        key: 'users',
        icon: 'users',
    },
    {
        label: 'Registration Review',
        href: route('admin.applications'),
        key: 'applications',
        icon: 'clipboard',
    },
    {
        label: 'Seller Compliance',
        href: route('admin.compliance'),
        key: 'compliance',
        icon: 'shield',
    },
    {
        label: 'Complaints & Disputes',
        href: route('admin.complaints'),
        key: 'complaints',
        icon: 'alert',
    },
    {
        label: 'Sales Report',
        href: route('admin.reports.sales-summary'),
        key: 'sales-report',
        icon: 'chart',
    },
    {
        label: 'Chat / Messaging',
        href: route('admin.messages'),
        key: 'messages',
        icon: 'message',
    },
]

/*
|--------------------------------------------------------------------------
| SETTINGS
|--------------------------------------------------------------------------
*/

const secondaryNavigation = [
    {
        label: 'Settings',
        href: route('admin.settings'),
        key: 'settings',
        icon: 'settings',
    },
]

/*
|--------------------------------------------------------------------------
| DATE
|--------------------------------------------------------------------------
*/

const currentDateLabel = computed(() => {
    return new Date().toLocaleDateString(undefined, {
        weekday: 'long',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    })
})

/*
|--------------------------------------------------------------------------
| ACTIVE NAVIGATION
|--------------------------------------------------------------------------
*/

const currentPath = computed(() => {
    return page.url
})

const isActive = (item) => {
    if (props.active === item.key) {
        return true
    }

    const path = currentPath.value

    switch (item.key) {
        case 'dashboard':
            return path === '/admin'

        case 'users':
            return path.startsWith('/admin/users')

        case 'applications':
            return (
                path.startsWith('/admin/applications') ||
                path.startsWith('/admin/sellers/applications')
            )

        case 'compliance':
            return path.startsWith('/admin/compliance')

        case 'complaints':
            return path.startsWith('/admin/complaints')

        case 'sales-report':
            return path.startsWith('/admin/reports/sales-summary')

        case 'messages':
            return path.startsWith('/admin/messages')

        case 'settings':
            return (
                path === '/admin/settings' ||
                path.startsWith('/admin/settings/announcements') ||
                path.startsWith('/admin/settings/policies')
            )

        default:
            return false
    }
}

/*
|--------------------------------------------------------------------------
| LOGOUT
|--------------------------------------------------------------------------
*/

const logout = () => {
    if (form.processing) {
        return
    }

    form.post(route('logout'))
}

/*
|--------------------------------------------------------------------------
| MOBILE SIDEBAR
|--------------------------------------------------------------------------
*/

const closeSidebar = () => {
    sidebarOpen.value = false
}
</script>

<template>
    <div class="min-h-screen bg-[#F8FAF9] text-[#1F2937]">

        <!-- ============================================================
             MOBILE OVERLAY
        ============================================================= -->

        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-30 bg-slate-900/40 backdrop-blur-[2px] lg:hidden"
                @click="closeSidebar"
            />
        </Transition>

        <!-- ============================================================
             SIDEBAR
        ============================================================= -->

        <aside
            :class="[
                'fixed inset-y-0 left-0 z-40 w-[240px] transform border-r border-[#E5E7EB] bg-white transition-transform duration-200 lg:translate-x-0',
                sidebarOpen
                    ? 'translate-x-0'
                    : '-translate-x-full',
            ]"
        >
            <div class="flex h-full min-h-0 flex-col">

                <!-- ====================================================
                     BRAND
                ===================================================== -->

                <div
                    class="relative flex h-[80px] shrink-0 items-center justify-center border-b border-[#E5E7EB] px-5"
                >
                    <Link
                        :href="route('admin.dashboard')"
                        class="flex items-center justify-center"
                        @click="closeSidebar"
                    >
                        <img
                            src="/images/Alogo.png"
                            alt="Alona"
                            class="block h-auto max-h-10 w-auto max-w-[170px] object-contain"
                        />
                    </Link>

                    <!-- Mobile Close -->
                    <button
                        type="button"
                        class="absolute right-3 rounded-lg p-2 text-slate-500 transition hover:bg-[#E8F7F6] hover:text-[#087F8C] lg:hidden"
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

                <!-- ====================================================
                     SCROLLABLE NAVIGATION
                ===================================================== -->

                <div
                    class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 pb-4"
                >

                    <!-- WORKSPACE -->

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
                                @click="closeSidebar"
                                :class="[
                                    'group flex min-h-[42px] items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                                    isActive(item)
                                        ? 'bg-[#E8F7F6] text-[#087F8C]'
                                        : 'text-slate-600 hover:bg-slate-50 hover:text-[#087F8C]',
                                ]"
                            >

                                <!-- Icon -->

                                <span
                                    :class="[
                                        'flex h-7 w-7 shrink-0 items-center justify-center rounded-lg transition',
                                        isActive(item)
                                            ? 'bg-white text-[#087F8C] shadow-sm'
                                            : 'text-slate-400 group-hover:text-[#087F8C]',
                                    ]"
                                >

                                    <!-- Dashboard -->

                                    <svg
                                        v-if="item.icon === 'dashboard'"
                                        class="h-[17px] w-[17px]"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <rect
                                            x="3"
                                            y="3"
                                            width="7"
                                            height="7"
                                            rx="1"
                                        />
                                        <rect
                                            x="14"
                                            y="3"
                                            width="7"
                                            height="7"
                                            rx="1"
                                        />
                                        <rect
                                            x="3"
                                            y="14"
                                            width="7"
                                            height="7"
                                            rx="1"
                                        />
                                        <rect
                                            x="14"
                                            y="14"
                                            width="7"
                                            height="7"
                                            rx="1"
                                        />
                                    </svg>

                                    <!-- Users -->

                                    <svg
                                        v-else-if="item.icon === 'users'"
                                        class="h-[17px] w-[17px]"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path
                                            d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"
                                        />
                                        <circle
                                            cx="9"
                                            cy="7"
                                            r="4"
                                        />
                                        <path
                                            d="M22 21v-2a4 4 0 0 0-3-3.87"
                                        />
                                        <path
                                            d="M16 3.13a4 4 0 0 1 0 7.75"
                                        />
                                    </svg>

                                    <!-- Clipboard -->

                                    <svg
                                        v-else-if="item.icon === 'clipboard'"
                                        class="h-[17px] w-[17px]"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <rect
                                            x="4"
                                            y="4"
                                            width="16"
                                            height="17"
                                            rx="2"
                                        />
                                        <path d="M9 4V2h6v2" />
                                        <path d="M8 9h8M8 13h8M8 17h5" />
                                    </svg>

                                    <!-- Shield -->

                                    <svg
                                        v-else-if="item.icon === 'shield'"
                                        class="h-[17px] w-[17px]"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path
                                            d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"
                                        />
                                        <path d="m9 12 2 2 4-4" />
                                    </svg>

                                    <!-- Alert -->

                                    <svg
                                        v-else-if="item.icon === 'alert'"
                                        class="h-[17px] w-[17px]"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path
                                            d="M10.3 3.9 2.2 18a2 2 0 0 0 1.7 3h16.2a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"
                                        />
                                        <path d="M12 9v4M12 17h.01" />
                                    </svg>

                                    <!-- Chart -->

                                    <svg
                                        v-else-if="item.icon === 'chart'"
                                        class="h-[17px] w-[17px]"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path d="M4 19V5" />
                                        <path d="M4 19h17" />
                                        <path d="m7 15 3-4 3 2 5-7" />
                                    </svg>

                                    <!-- Message -->

                                    <svg
                                        v-else-if="item.icon === 'message'"
                                        class="h-[17px] w-[17px]"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path
                                            d="M21 11.5a8.4 8.4 0 0 1-9 8.5 9.8 9.8 0 0 1-4-.8L3 21l1.8-4.5A8.3 8.3 0 0 1 3 11.5 8.4 8.4 0 0 1 12 3a8.4 8.4 0 0 1 9 8.5Z"
                                        />
                                    </svg>
                                </span>

                                <!-- Label -->

                                <span class="min-w-0 flex-1 truncate">
                                    {{ item.label }}
                                </span>

                                <!-- Active Indicator -->

                                <span
                                    v-if="isActive(item)"
                                    class="h-1.5 w-1.5 shrink-0 rounded-full bg-[#16A6A0]"
                                />
                            </Link>

                        </nav>
                    </div>

                    <!-- CONFIGURE -->

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
                                @click="closeSidebar"
                                :class="[
                                    'group flex min-h-[42px] items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                                    isActive(item)
                                        ? 'bg-[#E8F7F6] text-[#087F8C]'
                                        : 'text-slate-600 hover:bg-slate-50 hover:text-[#087F8C]',
                                ]"
                            >

                                <span
                                    :class="[
                                        'flex h-7 w-7 shrink-0 items-center justify-center rounded-lg',
                                        isActive(item)
                                            ? 'bg-white text-[#087F8C] shadow-sm'
                                            : 'text-slate-400 group-hover:text-[#087F8C]',
                                    ]"
                                >
                                    <svg
                                        class="h-[17px] w-[17px]"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path
                                            d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"
                                        />
                                        <path
                                            d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-1.8 1.8-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V20h-2.54v-.1a1.7 1.7 0 0 0-1.03-1.56 1.7 1.7 0 0 0-1.88.34l-.06.06-1.8-1.8.06-.06A1.7 1.7 0 0 0 8.1 15a1.7 1.7 0 0 0-1.56-1.03h-.1v-2.54h.1A1.7 1.7 0 0 0 8.1 10.4a1.7 1.7 0 0 0-.34-1.88L7.7 8.46l1.8-1.8.06.06a1.7 1.7 0 0 0 1.88.34 1.7 1.7 0 0 0 1.03-1.56V5h2.54v.1a1.7 1.7 0 0 0 1.03 1.56 1.7 1.7 0 0 0 1.88-.34l.06-.06 1.8 1.8-.06.06a1.7 1.7 0 0 0-.34 1.88 1.7 1.7 0 0 0 1.56 1.03h.1v2.54h-.1A1.7 1.7 0 0 0 19.4 15Z"
                                        />
                                    </svg>
                                </span>

                                <span class="min-w-0 flex-1 truncate">
                                    {{ item.label }}
                                </span>

                                <span
                                    v-if="isActive(item)"
                                    class="h-1.5 w-1.5 shrink-0 rounded-full bg-[#16A6A0]"
                                />
                            </Link>

                        </nav>
                    </div>
                </div>

                <!-- ====================================================
                     PLATFORM STATUS
                ===================================================== -->

                <div
                    class="shrink-0 border-t border-[#E5E7EB] bg-white p-3"
                >
                    <div
                        class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-3"
                    >
                        <div class="flex items-center justify-between">

                            <span
                                class="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400"
                            >
                                Platform
                            </span>

                            <span
                                class="flex items-center gap-1.5 text-[10px] font-bold text-[#22A06B]"
                            >
                                <span
                                    class="h-1.5 w-1.5 rounded-full bg-[#22A06B]"
                                />
                                Online
                            </span>
                        </div>

                        <p
                            class="mt-1.5 text-[11px] leading-4 text-slate-500"
                        >
                            Alona systems are operational.
                        </p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- ============================================================
             MAIN AREA
        ============================================================= -->

        <div class="lg:pl-[240px]">

            <!-- ========================================================
                 HEADER
            ========================================================= -->

            <header
                class="sticky top-0 z-20 h-[68px] border-b border-[#E5E7EB] bg-white/95 backdrop-blur-xl"
            >
                <div
                    class="flex h-full items-center gap-3 px-4 sm:px-6 lg:px-7"
                >

                    <!-- MOBILE MENU -->

                    <button
                        type="button"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-[#E5E7EB] bg-white text-slate-600 transition hover:border-[#16A6A0] hover:bg-[#E8F7F6] hover:text-[#087F8C] lg:hidden"
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

                    <!-- CONTEXT -->

                    <div class="min-w-0">
                        <p
                            class="truncate text-xs font-medium text-slate-400"
                        >
                            Admin Center
                        </p>

                        <div class="mt-0.5 flex items-center gap-2">
                            <span
                                class="h-1.5 w-1.5 rounded-full bg-[#F4B942]"
                            />

                            <p
                                class="truncate text-sm font-semibold text-slate-700 sm:text-base"
                            >
                                {{ currentDateLabel }}
                            </p>
                        </div>
                    </div>

                    <!-- RIGHT -->

                    <div
                        class="ml-auto flex shrink-0 items-center gap-3 sm:gap-4"
                    >

                        <!-- Profile -->

                        <div class="flex items-center gap-2.5">

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

                            <div class="hidden min-w-0 sm:block">
                                <p
                                    class="max-w-[150px] truncate text-sm font-semibold text-slate-700"
                                >
                                    {{ profileName }}
                                </p>

                                <p
                                    v-if="profileEmail"
                                    class="max-w-[150px] truncate text-[11px] text-slate-400"
                                >
                                    {{ profileEmail }}
                                </p>

                                <p
                                    v-else
                                    class="text-[11px] text-slate-400"
                                >
                                    Administrator
                                </p>
                            </div>
                        </div>

                        <!-- Divider -->

                        <div
                            class="hidden h-8 w-px bg-[#E5E7EB] sm:block"
                        />

                        <!-- Logout -->

                        <button
                            type="button"
                            class="hidden items-center gap-1.5 rounded-xl px-2.5 py-2 text-sm font-semibold text-slate-500 transition hover:bg-red-50 hover:text-[#E85D5D] disabled:opacity-50 sm:flex"
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
                                {{
                                    form.processing
                                        ? 'Logging out...'
                                        : 'Logout'
                                }}
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

            <!-- ========================================================
                 PAGE CONTENT
            ========================================================= -->

            <main
                class="min-h-[calc(100vh-68px)] px-4 py-5 sm:px-6 sm:py-6 lg:px-7"
            >
                <slot />
            </main>
        </div>
    </div>
</template>