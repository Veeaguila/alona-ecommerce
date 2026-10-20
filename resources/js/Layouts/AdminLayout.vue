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

        <!-- Sidebar (matches SellerLayout) -->
        <aside
            class="fixed inset-y-0 left-0 z-40 w-[240px] transform border-r border-[#065A63]/30 bg-gradient-to-b from-[#087F8C] via-[#076F7B] to-[#065A63] text-white shadow-xl transition-transform duration-200 lg:translate-x-0 print:hidden"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex h-full min-h-0 flex-col">

                <!-- Logo / Brand -->
                <div
                    class="relative flex h-[80px] shrink-0 items-center justify-center border-b border-white/10 px-5"
                >
                    <Link
                        :href="route('admin.dashboard')"
                        class="flex items-center justify-center rounded-xl bg-white px-3.5 py-1.5 shadow-sm transition hover:bg-slate-50"
                        @click="closeSidebar"
                    >
                        <img
                            src="/images/alona.png"
                            alt="Alona"
                            class="block h-7 w-auto max-w-[150px] object-contain"
                        />
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
                    class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 pb-4"
                >
                    <!-- Workspace -->
                    <div class="pt-5">
                        <p
                            class="mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.14em] text-teal-100/70"
                        >
                            Administration
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
                                <!-- Dashboard / Overview -->
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

                                <!-- User Accounts -->
                                <svg
                                    v-else-if="item.key === 'users'"
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
                                        d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"
                                    />
                                    <circle cx="9" cy="7" r="4" />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"
                                    />
                                </svg>

                                <!-- Registration Review -->
                                <svg
                                    v-else-if="item.key === 'applications'"
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-[18px] w-[18px] shrink-0"
                                    fill="none"
                                    viewBox="0 0 24 24"
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
                                    <path d="M9 4V2h6v2M8 9h8M8 13h8M8 17h5" />
                                </svg>

                                <!-- Seller Compliance -->
                                <svg
                                    v-else-if="item.key === 'compliance'"
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
                                        d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="m9 12 2 2 4-4"
                                    />
                                </svg>

                                <!-- Complaints & Disputes -->
                                <svg
                                    v-else-if="item.key === 'complaints'"
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
                                        d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"
                                    />
                                </svg>

                                <!-- Sales Report -->
                                <svg
                                    v-else-if="item.key === 'sales-report'"
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

                                <!-- Commission Report -->
                                <svg
                                    v-else-if="item.key === 'commission-report'"
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
                                        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 10v2m0-2c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>

                                <!-- Payout Requests -->
                                <svg
                                    v-else-if="item.key === 'payout-requests'"
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
                                        d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"
                                    />
                                </svg>

                                <!-- Chat / Messaging -->
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
                                        d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-1.8 1.8-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V20h-2.54v-.1a1.7 1.7 0 0 0-1.03-1.56 1.7 1.7 0 0 0-1.88.34l-.06.06-1.8-1.8.06-.06A1.7 1.7 0 0 0 8.1 15a1.7 1.7 0 0 0-1.56-1.03h-.1v-2.54h.1A1.7 1.7 0 0 0 8.1 10.4a1.7 1.7 0 0 0-.34-1.88L7.7 8.46l1.8-1.8.06.06a1.7 1.7 0 0 0 1.88.34 1.7 1.7 0 0 0 1.03-1.56V5h2.54v.1a1.7 1.7 0 0 0 1.03 1.56 1.7 1.7 0 0 0 1.88-.34l.06-.06 1.8 1.8-.06.06a1.7 1.7 0 0 0-.34 1.88 1.7 1.7 0 0 0 1.56 1.03h.1v2.54h-.1A1.7 1.7 0 0 0 19.4 15Z"
                                    />
                                </svg>

                                <span class="truncate">
                                    {{ item.label }}
                                </span>
                            </Link>
                        </nav>
                    </div>
                </div>

                <!-- Platform Status (matches SellerLayout) -->
                <div class="shrink-0 border-t border-white/10 bg-black/10 p-3">
                    <div
                        class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/10 px-3 py-3 backdrop-blur-xs"
                    >
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white text-[#087F8C] shadow-xs"
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
                            <p class="text-xs font-semibold text-white">
                                Platform Status
                            </p>

                            <div class="mt-0.5 flex items-center gap-1.5">
                                <span
                                    class="h-1.5 w-1.5 rounded-full bg-emerald-400"
                                ></span>

                                <span
                                    class="truncate text-[11px] font-medium text-emerald-300"
                                >
                                    Operational
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="lg:pl-[240px] print:pl-0">

            <!-- Sticky Header (height 68px, matching SellerLayout) -->
            <header
                class="sticky top-0 z-20 h-[68px] border-b border-[#E5E7EB] bg-white/95 backdrop-blur print:hidden"
            >
                <div
                    class="flex h-full items-center justify-between gap-3 px-4 sm:px-6"
                >
                    <!-- Left: Mobile menu toggle + Eyebrow & Date -->
                    <div class="flex min-w-0 items-center gap-3">
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
                                Admin Center
                            </p>

                            <p
                                class="truncate text-sm font-semibold text-slate-700 sm:text-base"
                            >
                                {{ currentDateLabel }}
                            </p>
                        </div>
                    </div>

                    <!-- Right: Notifications & Account Dropdown (exact SellerLayout pattern) -->
                    <div class="flex shrink-0 items-center gap-2 sm:gap-3">

                        <!-- Notifications Quick Link -->
                        <Link
                            :href="route('admin.dashboard')"
                            class="relative flex h-10 w-10 items-center justify-center rounded-xl border border-[#E5E7EB] bg-white text-slate-600 transition hover:border-[#16A6A0] hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                            title="Dashboard Overview"
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

                        <!-- Admin Account Dropdown -->
                        <div
                            class="relative ml-1 border-l border-[#E5E7EB] pl-2 sm:pl-3"
                            data-admin-account-dropdown
                        >
                            <button
                                type="button"
                                class="flex h-10 items-center gap-2 rounded-xl border border-transparent px-2.5 text-left transition hover:border-[#E5E7EB] hover:bg-[#E8F7F6]"
                                :class="{
                                    'border-[#E5E7EB] bg-[#E8F7F6]': accountOpen,
                                }"
                                aria-label="Account"
                                :aria-expanded="accountOpen"
                                @click.stop="toggleAccountDropdown"
                            >
                                <!-- Profile Photo / Initial -->
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#E8F7F6] text-xs font-black text-[#087F8C]"
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

                                <span class="hidden max-w-[130px] sm:block">
                                    <span
                                        class="block truncate text-xs font-extrabold text-[#1F2937]"
                                    >
                                        {{ profileName }}
                                    </span>
                                    <span
                                        class="block text-[9px] text-[#64748B]"
                                    >
                                        Administrator
                                    </span>
                                </span>

                                <svg
                                    class="hidden h-3.5 w-3.5 text-[#64748B] transition-transform duration-200 sm:block"
                                    :class="{
                                        'rotate-180': accountOpen,
                                    }"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        d="m6 9 6 6 6-6"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </button>

                            <!-- Account Dropdown Menu Panel -->
                            <Transition
                                enter-active-class="transition duration-150 ease-out"
                                enter-from-class="translate-y-1 opacity-0"
                                enter-to-class="translate-y-0 opacity-100"
                                leave-active-class="transition duration-100 ease-in"
                                leave-from-class="translate-y-0 opacity-100"
                                leave-to-class="translate-y-1 opacity-0"
                            >
                                <div
                                    v-if="accountOpen"
                                    class="absolute right-0 top-[calc(100%+10px)] z-50 w-[280px] overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-2xl shadow-black/10"
                                    @click.stop
                                >
                                    <!-- Account Info Header -->
                                    <div
                                        class="border-b border-[#E5E7EB] bg-[#F8FAF9] px-4 py-4"
                                    >
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#E8F7F6] text-sm font-black text-[#087F8C]"
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

                                            <div class="min-w-0">
                                                <p
                                                    class="truncate text-sm font-extrabold text-[#1F2937]"
                                                >
                                                    {{ profileName }}
                                                </p>
                                                <p
                                                    v-if="profileEmail"
                                                    class="truncate text-xs text-[#64748B]"
                                                >
                                                    {{ profileEmail }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Account Links -->
                                    <div class="p-2">
                                        <!-- Account Management / Profile Edit -->
                                        <Link
                                            :href="route('profile.edit')"
                                            class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-bold text-[#1F2937] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                                            @click="closeAccountDropdown"
                                        >
                                            <span
                                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]"
                                            >
                                                <svg
                                                    class="h-4 w-4"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="1.8"
                                                >
                                                    <circle
                                                        cx="12"
                                                        cy="8"
                                                        r="3.5"
                                                    />
                                                    <path
                                                        d="M5 20c.7-3.3 3.2-5 7-5s6.3 1.7 7 5"
                                                        stroke-linecap="round"
                                                    />
                                                </svg>
                                            </span>
                                            Account Profile
                                        </Link>

                                        <!-- Platform Settings -->
                                        <Link
                                            :href="route('admin.settings')"
                                            class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-bold text-[#1F2937] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                                            @click="closeAccountDropdown"
                                        >
                                            <span
                                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#F8FAF9] text-[#64748B]"
                                            >
                                                <svg
                                                    class="h-4 w-4"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
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
                                            </span>
                                            Platform Settings
                                        </Link>

                                        <!-- Announcements -->
                                        <Link
                                            :href="route('admin.announcements')"
                                            class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-bold text-[#1F2937] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                                            @click="closeAccountDropdown"
                                        >
                                            <span
                                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#F8FAF9] text-[#64748B]"
                                            >
                                                <svg
                                                    class="h-4 w-4"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="1.8"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"
                                                    />
                                                </svg>
                                            </span>
                                            Announcements
                                        </Link>

                                        <!-- Platform Policies -->
                                        <Link
                                            :href="route('admin.policies')"
                                            class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-bold text-[#1F2937] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                                            @click="closeAccountDropdown"
                                        >
                                            <span
                                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#F8FAF9] text-[#64748B]"
                                            >
                                                <svg
                                                    class="h-4 w-4"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="1.8"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                                    />
                                                </svg>
                                            </span>
                                            Policies
                                        </Link>
                                    </div>

                                    <!-- Logout -->
                                    <div
                                        class="border-t border-[#E5E7EB] p-2"
                                    >
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-bold text-[#E85D5D] transition hover:bg-[#FFF1F1] disabled:opacity-50"
                                            :disabled="form.processing"
                                            @click="logout"
                                        >
                                            <span
                                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#FFF1F1]"
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
                                            </span>
                                            {{ form.processing ? 'Logging out...' : 'Log out' }}
                                        </button>
                                    </div>
                                </div>
                            </Transition>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Page Content Slot -->
            <main
                class="min-h-[calc(100vh-68px)] px-4 py-5 sm:px-6 sm:py-6 lg:px-7 print:min-h-0 print:p-0 print:m-0"
            >
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'

const props = defineProps({
    active: {
        type: String,
        default: 'dashboard',
    },
})

const sidebarOpen = ref(false)
const accountOpen = ref(false)

const form = useForm({})
const page = usePage()

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

const toggleAccountDropdown = () => {
    accountOpen.value = !accountOpen.value
}

const closeAccountDropdown = () => {
    accountOpen.value = false
}

const closeSidebar = () => {
    sidebarOpen.value = false
}

const handleOutsideClick = (event) => {
    const target = event.target
    if (target && typeof target.closest === 'function' && !target.closest('[data-admin-account-dropdown]')) {
        accountOpen.value = false
    }
}

const handleEscape = (event) => {
    if (event.key === 'Escape') {
        accountOpen.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleOutsideClick)
    document.addEventListener('keydown', handleEscape)
})

onUnmounted(() => {
    document.removeEventListener('click', handleOutsideClick)
    document.removeEventListener('keydown', handleEscape)
})

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
| NAVIGATION (Aligned to Admin ERP Specification)
|--------------------------------------------------------------------------
*/

const navigation = [
    {
        label: 'Dashboard',
        href: route('admin.dashboard'),
        key: 'dashboard',
    },
    {
        label: 'User Accounts',
        href: route('admin.users'),
        key: 'users',
    },
    {
        label: 'Registration Review',
        href: route('admin.applications'),
        key: 'applications',
    },
    {
        label: 'Seller Compliance',
        href: route('admin.compliance'),
        key: 'compliance',
    },
    {
        label: 'Complaints & Disputes',
        href: route('admin.complaints'),
        key: 'complaints',
    },
    {
        label: 'Sales Report',
        href: route('admin.reports.sales-summary'),
        key: 'sales-report',
    },
    {
        label: 'Commission Report',
        href: route('admin.reports.commission'),
        key: 'commission-report',
    },
    {
        label: 'Payout Requests',
        href: route('admin.payout-requests'),
        key: 'payout-requests',
    },
    {
        label: 'Chat / Messaging',
        href: route('admin.messages'),
        key: 'messages',
    },
    {
        label: 'Platform Settings',
        href: route('admin.settings'),
        key: 'settings',
    },
]

const currentPath = computed(() => page.url)

const isActive = (item) => {
    if (props.active === item.key) {
        return true
    }

    const path = currentPath.value

    switch (item.key) {
        case 'dashboard':
            return path === '/admin' || path === '/admin/dashboard'

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

        case 'commission-report':
            return path.startsWith('/admin/reports/commission')

        case 'payout-requests':
            return path.startsWith('/admin/payout-requests')

        case 'messages':
            return path.startsWith('/admin/messages')

        case 'settings':
            return (
                path === '/admin/settings' ||
                path.startsWith('/admin/settings/')
            )

        default:
            return false
    }
}

const logout = () => {
    if (form.processing) {
        return
    }

    form.post(route('logout'))
}
</script>
