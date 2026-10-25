<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AuthModal from '@/Pages/Auth/AuthModal.vue'
import PartnerAuthModal from '@/Components/PartnerAuthModal.vue'
import AppFooter from '@/Components/AppFooter.vue'

const page = usePage()

const mobileOpen = ref(false)
const searchQuery = ref('')
const authModalOpen = ref(false)
const authModalMode = ref('login')
const partnerModalOpen = ref(false)
const partnerModalRole = ref('logistics')
const partnerModalTab = ref('signup')

const user = computed(() => page.props.auth?.user ?? null)
const isLoggedIn = computed(() => !!user.value)

const buyerCounts = computed(() => page.props.buyerCounts ?? {})

const cartCount = computed(() =>
    Number(
        buyerCounts.value.cart ??
        buyerCounts.value.cart_count ??
        0
    )
)

const safeRoute = (name, fallback = '#') => {
    try {
        return route(name)
    } catch (error) {
        return fallback
    }
}

/*
|--------------------------------------------------------------------------
| MOBILE MENU
|--------------------------------------------------------------------------
*/

const openMobileMenu = () => {
    mobileOpen.value = true
}

const closeMobileMenu = () => {
    mobileOpen.value = false
}

const toggleMobileMenu = event => {
    event?.stopPropagation()

    mobileOpen.value = !mobileOpen.value
}

/*
|--------------------------------------------------------------------------
| AUTH MODAL
|--------------------------------------------------------------------------
*/

const openAuthModal = mode => {
    authModalMode.value = mode
    authModalOpen.value = true
    closeMobileMenu()
}

const closeAuthModal = () => {
    authModalOpen.value = false
}

const openPartnerModal = (role = 'logistics', tab = 'signup') => {
    partnerModalRole.value = role
    partnerModalTab.value = tab
    partnerModalOpen.value = true
    closeMobileMenu()
}

const closePartnerModal = () => {
    partnerModalOpen.value = false
}

/*
|--------------------------------------------------------------------------
| DASHBOARD
|--------------------------------------------------------------------------
*/

const goToDashboard = () => {
    closeMobileMenu()

    if (!isLoggedIn.value) {
        openAuthModal('login')
        return
    }

    const userType =
        user.value?.usertype ??
        user.value?.role ??
        'buyer'

    const destination = page.props.auth?.destination ?? (
        userType === 'admin'
            ? safeRoute('admin.dashboard')
            : userType === 'seller'
                ? safeRoute('seller.dashboard')
                : safeRoute('buyer.dashboard'))

    if (destination !== '#') {
        if (userType === 'sorting_center' || destination.includes('sorting-center')) {
            window.location.assign(destination)
            return
        }
        router.visit(destination)
    }
}

/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

const searchProducts = () => {
    const query = searchQuery.value.trim()
    const destination = safeRoute('guest.products')

    if (destination === '#') {
        return
    }

    closeMobileMenu()

    if (!query) {
        router.visit(destination)
        return
    }

    router.get(
        destination,
        {
            search: query,
        },
        {
            preserveState: true,
            preserveScroll: false,
        }
    )
}

/*
|--------------------------------------------------------------------------
| CART
|--------------------------------------------------------------------------
*/

const openCart = () => {
    closeMobileMenu()

    if (!isLoggedIn.value) {
        openAuthModal('login')
        return
    }

    const destination = safeRoute('buyer.cart')

    if (destination !== '#') {
        router.visit(destination)
    }
}

/*
|--------------------------------------------------------------------------
| OUTSIDE CLICK / ESCAPE
|--------------------------------------------------------------------------
*/

const handleOutsideClick = event => {
    const menu = document.querySelector('[data-mobile-menu]')
    const button = document.querySelector('[data-mobile-menu-button]')

    if (!mobileOpen.value) {
        return
    }

    if (
        menu &&
        !menu.contains(event.target) &&
        button &&
        !button.contains(event.target)
    ) {
        closeMobileMenu()
    }
}

const handleEscape = event => {
    if (event.key === 'Escape') {
        closeMobileMenu()
        closeAuthModal()
        closePartnerModal()
    }
}

watch(() => page.url, url => {
    const mode = new URLSearchParams(url.split('?')[1] ?? '').get('auth')
    if (['register-logistics', 'register-rider'].includes(mode) && !isLoggedIn.value) {
        openPartnerModal(mode === 'register-rider' ? 'rider' : 'logistics', 'signup')
        return
    }
    if (['partner-login'].includes(mode) && !isLoggedIn.value) {
        openPartnerModal('logistics', 'login')
        return
    }
    if (['login', 'register', 'register-buyer', 'register-seller', 'verify-otp'].includes(mode) && !isLoggedIn.value) openAuthModal(mode)
}, { immediate: true })
onMounted(() => {
    document.addEventListener('click', handleOutsideClick)
    document.addEventListener('keydown', handleEscape)
})

onUnmounted(() => {
    document.removeEventListener('click', handleOutsideClick)
    document.removeEventListener('keydown', handleEscape)
})
</script>

<template>
    <div class="min-h-screen bg-[#F8FAF9] text-[#1F2937]">

        <!-- HEADER -->
        <header
            class="sticky top-0 z-50 border-b border-[#E5E7EB]/80 bg-white/95 backdrop-blur-xl"
        >

            <!-- MAIN HEADER -->
            <div
                class="mx-auto flex h-[64px] max-w-[1440px] items-center gap-2 px-3 sm:h-[72px] sm:px-6 lg:h-[78px] lg:gap-5 lg:px-8"
            >

                <!-- LOGO -->
                <Link
                    :href="safeRoute('guest.home', '/')"
                    class="flex shrink-0 items-center transition hover:opacity-90"
                    aria-label="Alona Home"
                >
                    <img
                        src="/images/alona.png"
                        alt="Alona"
                        class="h-8 w-auto object-contain sm:h-9 lg:h-10"
                    />
                </Link>

                <!-- DESKTOP SEARCH -->
                <div
                    class="hidden min-w-0 flex-1 items-center md:flex max-w-2xl mx-auto"
                >
                    <form
                        class="relative min-w-0 w-full"
                        @submit.prevent="searchProducts"
                    >
                        <svg
                            class="pointer-events-none absolute left-3.5 sm:left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#64748B]"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <circle
                                cx="11"
                                cy="11"
                                r="7"
                            />

                            <path
                                d="m20 20-4-4"
                                stroke-linecap="round"
                            />
                        </svg>

                        <input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Search products, brands, and categories..."
                            class="h-10 sm:h-11 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] pl-10 sm:pl-11 pr-4 text-xs sm:text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#16A6A0] focus:bg-white focus:ring-2 focus:ring-[#16A6A0]/10"
                        />
                    </form>
                </div>

                <!-- DESKTOP ACTIONS -->
                <div
                    class="ml-auto hidden shrink-0 items-center gap-1.5 md:flex"
                >

                    <!-- CART -->
                    <button
                        type="button"
                        class="relative flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center rounded-xl text-[#1F2937] transition duration-200 hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                        aria-label="Cart"
                        title="Cart"
                        @click="openCart"
                    >
                        <svg
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                d="M3 4h2l2.2 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L21 8H6"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />

                            <circle
                                cx="9"
                                cy="20"
                                r="1"
                            />

                            <circle
                                cx="18"
                                cy="20"
                                r="1"
                            />
                        </svg>

                        <span
                            v-if="cartCount > 0"
                            class="absolute -right-0.5 -top-0.5 flex min-h-[17px] min-w-[17px] items-center justify-center rounded-full bg-[#E85D5D] px-1 text-[9px] font-black leading-none text-white"
                        >
                            {{ cartCount > 99 ? '99+' : cartCount }}
                        </span>
                    </button>

                    <!-- LOGGED-IN -->
                    <template v-if="isLoggedIn">
                        <button
                            type="button"
                            class="inline-flex h-9 sm:h-10 items-center rounded-xl bg-[#087F8C] px-3.5 sm:px-4 text-xs sm:text-sm font-extrabold text-white shadow-xs transition duration-200 hover:-translate-y-0.5 hover:bg-[#066B76] hover:shadow-md"
                            @click="goToDashboard"
                        >
                            Dashboard
                        </button>
                    </template>

                    <!-- GUEST -->
                    <template v-else>
                        <button
                            type="button"
                            class="inline-flex h-9 sm:h-10 items-center rounded-xl px-3 sm:px-3.5 text-xs sm:text-sm font-extrabold text-[#087F8C] transition duration-200 hover:bg-[#E8F7F6]"
                            @click="openAuthModal('login')"
                        >
                            Log in
                        </button>

                        <button
                            type="button"
                            class="inline-flex h-9 sm:h-10 items-center rounded-xl bg-[#087F8C] px-3.5 sm:px-4 text-xs sm:text-sm font-extrabold text-white shadow-xs transition duration-200 hover:-translate-y-0.5 hover:bg-[#066B76] hover:shadow-md"
                            @click="openAuthModal('register')"
                        >
                            Sign Up
                        </button>
                    </template>
                </div>

                <!-- MOBILE ACTIONS -->
                <div
                    class="ml-auto flex min-w-0 flex-1 items-center justify-end gap-1 sm:gap-1.5 md:hidden"
                >

                    <!-- MOBILE SEARCH -->
                    <form
                        class="min-w-0 flex-1 max-w-[280px] sm:max-w-sm"
                        @submit.prevent="searchProducts"
                    >
                        <div class="relative">
                            <svg
                                class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[#64748B]"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <circle
                                    cx="11"
                                    cy="11"
                                    r="7"
                                />

                                <path
                                    d="m20 20-4-4"
                                    stroke-linecap="round"
                                />
                            </svg>

                            <input
                                v-model="searchQuery"
                                type="search"
                                placeholder="Search..."
                                class="h-9 sm:h-10 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] pl-8 sm:pl-9 pr-2.5 text-xs text-[#1F2937] outline-none transition duration-200 placeholder:text-[#94A3B8] focus:border-[#16A6A0] focus:bg-white focus:ring-2 focus:ring-[#16A6A0]/10"
                            />
                        </div>
                    </form>

                    <!-- MOBILE CART -->
                    <button
                        type="button"
                        class="relative flex h-9 w-9 sm:h-10 sm:w-10 shrink-0 items-center justify-center rounded-xl text-[#1F2937] transition duration-200 hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                        aria-label="Cart"
                        title="Cart"
                        @click="openCart"
                    >
                        <svg
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                d="M3 4h2l2.2 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6L21 8H6"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />

                            <circle
                                cx="9"
                                cy="20"
                                r="1"
                            />

                            <circle
                                cx="18"
                                cy="20"
                                r="1"
                            />
                        </svg>

                        <span
                            v-if="cartCount > 0"
                            class="absolute -right-0.5 -top-0.5 flex min-h-[17px] min-w-[17px] items-center justify-center rounded-full bg-[#E85D5D] px-1 text-[9px] font-black leading-none text-white"
                        >
                            {{ cartCount > 99 ? '99+' : cartCount }}
                        </span>
                    </button>

                    <!-- MOBILE MENU BUTTON -->
                    <button
                        type="button"
                        data-mobile-menu-button
                        class="flex h-9 w-9 sm:h-10 sm:w-10 shrink-0 items-center justify-center rounded-xl text-[#1F2937] transition duration-200 hover:bg-[#E8F7F6] hover:text-[#087F8C] active:scale-95"
                        :aria-expanded="mobileOpen"
                        aria-controls="mobile-menu"
                        aria-label="Toggle menu"
                        @click.stop="toggleMobileMenu"
                    >
                        <Transition
                            mode="out-in"
                            enter-active-class="transition duration-150 ease-out"
                            enter-from-class="scale-75 rotate-45 opacity-0"
                            enter-to-class="scale-100 rotate-0 opacity-100"
                            leave-active-class="transition duration-100 ease-in"
                            leave-from-class="scale-100 rotate-0 opacity-100"
                            leave-to-class="scale-75 -rotate-45 opacity-0"
                        >
                            <svg
                                v-if="!mobileOpen"
                                key="menu"
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    d="M4 6H20M4 12H20M4 18H20"
                                    stroke-linecap="round"
                                />
                            </svg>

                            <svg
                                v-else
                                key="close"
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    d="M6 6L18 18M18 6L6 18"
                                    stroke-linecap="round"
                                />
                            </svg>
                        </Transition>
                    </button>
                </div>
            </div>

            <!-- MOBILE MENU -->
            <Transition
                enter-active-class="transition-all duration-200 ease-out"
                enter-from-class="max-h-0 opacity-0"
                enter-to-class="max-h-[360px] opacity-100"
                leave-active-class="transition-all duration-150 ease-in"
                leave-from-class="max-h-[360px] opacity-100"
                leave-to-class="max-h-0 opacity-0"
            >
                <div
                    v-if="mobileOpen"
                    id="mobile-menu"
                    data-mobile-menu
                    class="overflow-hidden border-t border-[#E5E7EB] bg-white shadow-xl md:hidden"
                    @click.stop
                >
                    <div
                        class="mx-auto max-h-[calc(100vh-68px)] max-w-[1440px] overflow-y-auto px-4 py-4 sm:px-6 space-y-3"
                    >

                        <!-- MENU OPTIONS -->
                        <div class="grid gap-2">

                            <!-- LOGGED-IN -->
                            <template v-if="isLoggedIn">
                                <button
                                    type="button"
                                    class="flex h-11 w-full items-center justify-center rounded-xl bg-[#087F8C] px-4 text-sm font-extrabold text-white transition duration-200 hover:bg-[#066B76] active:scale-[0.98]"
                                    @click="goToDashboard"
                                >
                                    Go to Dashboard
                                </button>
                            </template>

                            <!-- GUEST -->
                            <template v-else>
                                <div class="grid grid-cols-2 gap-2">

                                    <button
                                        type="button"
                                        class="flex h-11 w-full items-center justify-center rounded-xl border border-[#E5E7EB] px-4 text-sm font-extrabold text-[#087F8C] transition duration-200 hover:bg-[#E8F7F6] active:scale-[0.98]"
                                        @click="openAuthModal('login')"
                                    >
                                        Log in
                                    </button>

                                    <button
                                        type="button"
                                        class="flex h-11 w-full items-center justify-center rounded-xl bg-[#087F8C] px-4 text-sm font-extrabold text-white transition duration-200 hover:bg-[#066B76] active:scale-[0.98]"
                                        @click="openAuthModal('register')"
                                    >
                                        Sign Up
                                    </button>

                                </div>
                            </template>
                        </div>

                        <!-- NAVIGATION LINKS -->
                        <div class="border-t border-[#E5E7EB] pt-3 space-y-1">
                            <Link
                                :href="safeRoute('guest.products', '/products')"
                                class="flex h-10 items-center gap-3 rounded-xl px-3 text-xs font-bold text-slate-700 transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                                @click="closeMobileMenu"
                            >
                                <svg class="h-4 w-4 text-[#087F8C]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                                <span>All Products</span>
                            </Link>

                            <button
                                type="button"
                                class="flex h-10 w-full items-center gap-3 rounded-xl px-3 text-left text-xs font-bold text-slate-700 transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                                @click="openPartnerModal('logistics', 'signup')"
                            >
                                <svg class="h-4 w-4 text-[#087F8C]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                <span>Become a Logistics Partner</span>
                            </button>

                            <button
                                type="button"
                                class="flex h-10 w-full items-center gap-3 rounded-xl px-3 text-left text-xs font-bold text-slate-700 transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                                @click="openPartnerModal('rider', 'signup')"
                            >
                                <svg class="h-4 w-4 text-[#087F8C]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                <span>Drive / Deliver with Us</span>
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </header>

        <!-- PAGE CONTENT -->
        <main class="min-h-[60vh]">
            <slot />
        </main>

        <!-- FOOTER -->
        <AppFooter />

        <!-- AUTH MODAL (BUYER & SELLER) -->
        <AuthModal
            :show="authModalOpen"
            :mode="authModalMode"
            @close="closeAuthModal"
            @update:mode="authModalMode = $event"
        />

        <!-- PARTNER AUTH MODAL (LOGISTICS & RIDER) -->
        <PartnerAuthModal
            :show="partnerModalOpen"
            :initial-role="partnerModalRole"
            :initial-tab="partnerModalTab"
            @close="closePartnerModal"
        />
    </div>
</template>

<style scoped>
input[type='search']::-webkit-search-cancel-button {
    cursor: pointer;
}
</style>
