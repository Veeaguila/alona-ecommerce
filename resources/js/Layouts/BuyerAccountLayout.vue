<script setup>
import { computed, ref, onMounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'

const props = defineProps({
    activeMenu: {
        type: String,
        default: 'account', // 'account' | 'purchase' | 'notifications' | 'vouchers' | 'coins'
    },
    activeSub: {
        type: String,
        default: 'profile', // 'profile' | 'banks' | 'addresses' | 'password' | 'privacy' | 'notification_settings' | 'order_settings' | 'order_updates' | 'promotions' | 'wallet_updates' | 'platform_updates'
    },
})

const emit = defineEmits(['selectTab'])

const page = usePage()
const user = computed(() => page.props.auth?.user || {})

const userName = computed(() => {
    return (
        user.value.username ||
        user.value.name ||
        user.value.first_name ||
        'My Account'
    )
})

const userEmail = computed(() => {
    return user.value.email || ''
})

const userAvatar = computed(() => {
    const val =
        user.value.avatar_url ||
        user.value.avatar ||
        user.value.profile_photo_url ||
        user.value.profile_photo

    if (!val) return null

    if (
        String(val).startsWith('http://') ||
        String(val).startsWith('https://') ||
        String(val).startsWith('data:')
    ) {
        return String(val)
    }

    if (String(val).startsWith('/storage/') || String(val).startsWith('/images/')) {
        return String(val)
    }

    return `/storage/${String(val).replace(/^\/+/, '').replace(/^storage\//, '')}`
})

const userInitial = computed(() => {
    const name = userName.value || 'U'
    return name.charAt(0).toUpperCase()
})

// Submenus open states
const accountMenuOpen = ref(true)
const notificationsMenuOpen = ref(props.activeMenu === 'notifications')

onMounted(() => {
    if (props.activeMenu === 'notifications') {
        notificationsMenuOpen.value = true
    }
})

const toggleAccountMenu = () => {
    accountMenuOpen.value = !accountMenuOpen.value
}

const toggleNotificationsMenu = () => {
    notificationsMenuOpen.value = !notificationsMenuOpen.value
}

const handleSubClick = (subKey) => {
    emit('selectTab', subKey)
}
</script>

<template>
    <BuyerLayout>
        <div class="min-h-[calc(100vh-80px)] w-full bg-[#F8FAF9] py-6 sm:py-8 lg:py-10">
            <div class="w-full px-4 sm:px-6 lg:px-8 2xl:px-10">
                
                <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
                    
                    <!-- LEFT SIDEBAR -->
                    <aside class="w-full shrink-0 lg:w-[250px] xl:w-[270px]">
                        
                        <div class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-xs">
                            
                            <!-- USER HEADER -->
                            <div class="flex items-center gap-3 border-b border-[#EEF1F2] pb-4">
                                <Link
                                    :href="route('profile.edit')"
                                    class="relative flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-[#E8F7F6] bg-white text-[#087F8C] shadow-xs transition hover:ring-2 hover:ring-[#087F8C]/30"
                                >
                                    <img
                                        v-if="userAvatar"
                                        :src="userAvatar"
                                        :alt="userName"
                                        class="h-full w-full object-cover"
                                    />
                                    <span
                                        v-else
                                        class="text-base font-extrabold text-[#087F8C]"
                                    >
                                        {{ userInitial }}
                                    </span>
                                </Link>

                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-extrabold text-[#1F2937]">
                                        {{ userName }}
                                    </div>
                                    <Link
                                        :href="route('profile.edit')"
                                        class="mt-0.5 inline-flex items-center gap-1 text-xs font-semibold text-[#087F8C] transition hover:text-[#066B76]"
                                    >
                                        <svg
                                            class="h-3 w-3"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2.2"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                                            />
                                        </svg>
                                        Edit Profile
                                    </Link>
                                </div>
                            </div>

                            <!-- NAVIGATION MENUS -->
                            <nav class="mt-4 space-y-2">
                                
                                <!-- 1. MY ACCOUNT -->
                                <div>
                                    <button
                                        type="button"
                                        class="flex w-full items-center justify-between rounded-xl px-2.5 py-2 text-left text-xs font-extrabold transition"
                                        :class="activeMenu === 'account' ? 'text-[#087F8C]' : 'text-[#1F2937] hover:bg-[#F8FAF9] hover:text-[#087F8C]'"
                                        @click="toggleAccountMenu"
                                    >
                                        <div class="flex items-center gap-2.5">
                                            <!-- User Icon -->
                                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]">
                                                <svg
                                                    class="h-4 w-4"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                >
                                                    <circle cx="12" cy="8" r="4" />
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M4 20c0-3.5 3.5-6 8-6s8 2.5 8 6"
                                                    />
                                                </svg>
                                            </span>
                                            <span>My Account</span>
                                        </div>

                                        <svg
                                            class="h-3.5 w-3.5 text-[#94A3B8] transition-transform duration-200"
                                            :class="accountMenuOpen ? 'rotate-180 text-[#087F8C]' : ''"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2.5"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                        </svg>
                                    </button>

                                    <!-- Sub-items -->
                                    <div
                                        v-show="accountMenuOpen"
                                        class="mt-1 space-y-0.5 pl-9 text-xs"
                                    >
                                        <Link
                                            :href="route('profile.edit', { tab: 'profile' })"
                                            class="block rounded-lg px-2.5 py-1.5 transition"
                                            :class="activeMenu === 'account' && activeSub === 'profile' ? 'bg-[#E8F7F6] font-extrabold text-[#087F8C]' : 'font-medium text-[#64748B] hover:bg-[#F8FAF9] hover:text-[#087F8C]'"
                                            @click="handleSubClick('profile')"
                                        >
                                            Profile
                                        </Link>

                                        <Link
                                            :href="route('buyer.payment-methods')"
                                            class="block rounded-lg px-2.5 py-1.5 transition"
                                            :class="activeMenu === 'account' && activeSub === 'banks' ? 'bg-[#E8F7F6] font-extrabold text-[#087F8C]' : 'font-medium text-[#64748B] hover:bg-[#F8FAF9] hover:text-[#087F8C]'"
                                        >
                                            Banks & Cards
                                        </Link>

                                        <Link
                                            :href="route('buyer.addresses')"
                                            class="block rounded-lg px-2.5 py-1.5 transition"
                                            :class="activeMenu === 'account' && activeSub === 'addresses' ? 'bg-[#E8F7F6] font-extrabold text-[#087F8C]' : 'font-medium text-[#64748B] hover:bg-[#F8FAF9] hover:text-[#087F8C]'"
                                        >
                                            Addresses
                                        </Link>

                                        <Link
                                            :href="route('profile.edit', { tab: 'password' })"
                                            class="block rounded-lg px-2.5 py-1.5 transition"
                                            :class="activeMenu === 'account' && activeSub === 'password' ? 'bg-[#E8F7F6] font-extrabold text-[#087F8C]' : 'font-medium text-[#64748B] hover:bg-[#F8FAF9] hover:text-[#087F8C]'"
                                            @click="handleSubClick('password')"
                                        >
                                            Change Password
                                        </Link>

                                        <Link
                                            :href="route('profile.edit', { tab: 'privacy' })"
                                            class="block rounded-lg px-2.5 py-1.5 transition"
                                            :class="activeMenu === 'account' && activeSub === 'privacy' ? 'bg-[#E8F7F6] font-extrabold text-[#087F8C]' : 'font-medium text-[#64748B] hover:bg-[#F8FAF9] hover:text-[#087F8C]'"
                                            @click="handleSubClick('privacy')"
                                        >
                                            Privacy Settings
                                        </Link>
                                    </div>
                                </div>

                                <!-- 2. MY PURCHASE (ORDERS) -->
                                <div>
                                    <Link
                                        :href="route('buyer.orders')"
                                        class="flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2 text-left text-xs font-extrabold transition"
                                        :class="activeMenu === 'purchase' ? 'bg-[#E8F7F6] text-[#087F8C] ring-1 ring-[#B9E7E4]/50' : 'text-[#1F2937] hover:bg-[#F8FAF9] hover:text-[#087F8C]'"
                                    >
                                        <span
                                            class="flex h-7 w-7 items-center justify-center rounded-lg transition"
                                            :class="activeMenu === 'purchase' ? 'bg-[#087F8C] text-white' : 'bg-[#E8F7F6] text-[#087F8C]'"
                                        >
                                            <svg
                                                class="h-4 w-4"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"
                                                />
                                            </svg>
                                        </span>
                                        <span>My Purchase</span>
                                    </Link>
                                </div>

                                <!-- 3. NOTIFICATIONS -->
                                <div>
                                    <button
                                        type="button"
                                        class="flex w-full items-center justify-between rounded-xl px-2.5 py-2 text-left text-xs font-extrabold transition"
                                        :class="activeMenu === 'notifications' ? 'text-[#087F8C]' : 'text-[#1F2937] hover:bg-[#F8FAF9] hover:text-[#087F8C]'"
                                        @click="toggleNotificationsMenu"
                                    >
                                        <div class="flex items-center gap-2.5">
                                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#FFF8E7] text-[#F4B942]">
                                                <svg
                                                    class="h-4 w-4"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                                                    />
                                                </svg>
                                            </span>
                                            <span>Notifications</span>
                                        </div>

                                        <svg
                                            class="h-3.5 w-3.5 text-[#94A3B8] transition-transform duration-200"
                                            :class="notificationsMenuOpen ? 'rotate-180 text-[#087F8C]' : ''"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2.5"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                        </svg>
                                    </button>

                                    <!-- Sub-items -->
                                    <div
                                        v-show="notificationsMenuOpen"
                                        class="mt-1 space-y-0.5 pl-9 text-xs"
                                    >
                                        <Link
                                            :href="route('buyer.notifications', { type: 'order' })"
                                            class="block rounded-lg px-2.5 py-1.5 transition"
                                            :class="activeMenu === 'notifications' && activeSub === 'order_updates' ? 'bg-[#E8F7F6] font-extrabold text-[#087F8C]' : 'font-medium text-[#64748B] hover:bg-[#F8FAF9] hover:text-[#087F8C]'"
                                        >
                                            Order Updates
                                        </Link>

                                        <Link
                                            :href="route('buyer.notifications', { type: 'promotion' })"
                                            class="block rounded-lg px-2.5 py-1.5 transition"
                                            :class="activeMenu === 'notifications' && activeSub === 'promotions' ? 'bg-[#E8F7F6] font-extrabold text-[#087F8C]' : 'font-medium text-[#64748B] hover:bg-[#F8FAF9] hover:text-[#087F8C]'"
                                        >
                                            Promotions
                                        </Link>

                                        <Link
                                            :href="route('buyer.notifications', { type: 'system' })"
                                            class="block rounded-lg px-2.5 py-1.5 transition"
                                            :class="activeMenu === 'notifications' && activeSub === 'platform_updates' ? 'bg-[#E8F7F6] font-extrabold text-[#087F8C]' : 'font-medium text-[#64748B] hover:bg-[#F8FAF9] hover:text-[#087F8C]'"
                                        >
                                            Alona Updates
                                        </Link>
                                    </div>
                                </div>

                                <!-- 4. MY VOUCHERS -->
                                <div>
                                    <Link
                                        :href="route('buyer.vouchers')"
                                        class="flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2 text-left text-xs font-extrabold transition"
                                        :class="activeMenu === 'vouchers' ? 'bg-[#E8F7F6] text-[#087F8C] ring-1 ring-[#B9E7E4]/50' : 'text-[#1F2937] hover:bg-[#F8FAF9] hover:text-[#087F8C]'"
                                    >
                                        <span
                                            class="flex h-7 w-7 items-center justify-center rounded-lg transition"
                                            :class="activeMenu === 'vouchers' ? 'bg-[#087F8C] text-white' : 'bg-[#E8F7F6] text-[#087F8C]'"
                                        >
                                            <svg
                                                class="h-4 w-4"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"
                                                />
                                            </svg>
                                        </span>
                                        <span>My Vouchers</span>
                                    </Link>
                                </div>

                            </nav>
                        </div>

                    </aside>

                    <!-- RIGHT MAIN CONTENT -->
                    <main class="min-w-0 flex-1">
                        <slot />
                    </main>

                </div>
            </div>
        </div>
    </BuyerLayout>
</template>
