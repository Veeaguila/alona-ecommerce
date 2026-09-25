<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AuthModal from '@/Pages/Auth/AuthModal.vue'

const page = usePage()

const mobileOpen = ref(false)
const searchQuery = ref('')
const authModalOpen = ref(false)
const authModalMode = ref('login')

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

    const destination =
        userType === 'admin'
            ? safeRoute('admin.dashboard')
            : userType === 'seller'
                ? safeRoute('seller.dashboard')
                : safeRoute('buyer.dashboard')

    if (destination !== '#') {
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
</script>

<template>
    <div class="min-h-screen bg-[#F8FAF9] text-[#1F2937]">

        <!-- HEADER -->
        <header
            class="sticky top-0 z-50 border-b border-[#E5E7EB]/80 bg-white/95 backdrop-blur-xl"
        >

            <!-- MAIN HEADER -->
            <div
                class="mx-auto flex h-[72px] max-w-[1440px] items-center gap-2 px-3 sm:h-[78px] sm:px-6 lg:gap-5 lg:px-8"
            >

                <!-- LOGO -->
                <Link
                    :href="safeRoute('guest.home', '/')"
                    class="flex shrink-0 items-center"
                    aria-label="Alona Home"
                >
                    <img
                        src="/images/alona.png"
                        alt="Alona"
                        class="h-9 w-auto object-contain sm:h-10 lg:h-11"
                    />
                </Link>

                <!-- DESKTOP SEARCH -->
                <div
                    class="hidden min-w-0 flex-1 items-center md:flex"
                >
                    <form
                        class="relative min-w-0 w-full"
                        @submit.prevent="searchProducts"
                    >
                        <svg
                            class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#64748B]"
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
                            placeholder="Search products..."
                            class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] pl-11 pr-4 text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#16A6A0] focus:bg-white focus:ring-2 focus:ring-[#16A6A0]/10"
                        />
                    </form>
                </div>

                <!-- DESKTOP ACTIONS -->
                <div
                    class="ml-auto hidden shrink-0 items-center gap-1 md:flex"
                >

                    <!-- CART -->
                    <button
                        type="button"
                        class="relative flex h-10 w-10 items-center justify-center rounded-xl text-[#1F2937] transition duration-200 hover:bg-[#E8F7F6] hover:text-[#087F8C]"
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
                            class="inline-flex h-10 items-center rounded-xl bg-[#087F8C] px-4 text-sm font-extrabold text-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:bg-[#066B76] hover:shadow-md"
                            @click="goToDashboard"
                        >
                            Dashboard
                        </button>
                    </template>

                    <!-- GUEST -->
                    <template v-else>
                        <button
                            type="button"
                            class="inline-flex h-10 items-center rounded-xl px-3.5 text-sm font-extrabold text-[#087F8C] transition duration-200 hover:bg-[#E8F7F6]"
                            @click="openAuthModal('login')"
                        >
                            Log in
                        </button>

                        <button
                            type="button"
                            class="inline-flex h-10 items-center rounded-xl bg-[#087F8C] px-4 text-sm font-extrabold text-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:bg-[#066B76] hover:shadow-md"
                            @click="openAuthModal('register')"
                        >
                            Register
                        </button>
                    </template>
                </div>

                <!-- MOBILE ACTIONS -->
                <div
                    class="ml-auto flex min-w-0 flex-1 items-center justify-end gap-1 md:hidden"
                >

                    <!-- MOBILE SEARCH -->
                    <form
                        class="min-w-0 flex-1"
                        @submit.prevent="searchProducts"
                    >
                        <div class="relative">
                            <svg
                                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#64748B]"
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
                                class="h-10 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] pl-9 pr-2 text-xs text-[#1F2937] outline-none transition duration-200 placeholder:text-[#94A3B8] focus:border-[#16A6A0] focus:bg-white focus:ring-2 focus:ring-[#16A6A0]/10"
                            />
                        </div>
                    </form>

                    <!-- MOBILE CART -->
                    <button
                        type="button"
                        class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-[#1F2937] transition duration-200 hover:bg-[#E8F7F6] hover:text-[#087F8C]"
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
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-[#1F2937] transition duration-200 hover:bg-[#E8F7F6] hover:text-[#087F8C] active:scale-95"
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
                enter-to-class="max-h-[220px] opacity-100"
                leave-active-class="transition-all duration-150 ease-in"
                leave-from-class="max-h-[220px] opacity-100"
                leave-to-class="max-h-0 opacity-0"
            >
                <div
                    v-if="mobileOpen"
                    id="mobile-menu"
                    data-mobile-menu
                    class="overflow-hidden border-t border-[#E5E7EB] bg-white md:hidden"
                    @click.stop
                >
                    <div
                        class="mx-auto max-w-[1440px] px-4 py-4 sm:px-6"
                    >

                        <!-- MENU TITLE -->
                        <div class="mb-3 flex items-center gap-2">
                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]"
                            >
                                <svg
                                    class="h-4 w-4"
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
                            </span>

                            <span
                                class="text-sm font-extrabold text-[#1F2937]"
                            >
                                Menu
                            </span>
                        </div>

                        <!-- MENU OPTIONS -->
                        <div class="grid gap-2">

                            <!-- LOGGED-IN -->
                            <template v-if="isLoggedIn">
                                <button
                                    type="button"
                                    class="flex h-11 w-full items-center justify-center rounded-xl bg-[#087F8C] px-4 text-sm font-extrabold text-white transition duration-200 hover:bg-[#066B76] active:scale-[0.98]"
                                    @click="goToDashboard"
                                >
                                    Dashboard
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
                                        Register
                                    </button>

                                </div>
                            </template>
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
        <footer class="border-t border-[#E5E7EB] bg-white">
            <div
                class="mx-auto grid max-w-[1440px] gap-10 px-4 py-12 sm:px-6 md:grid-cols-2 lg:grid-cols-4 lg:px-8"
            >

                <!-- BRAND -->
                <div class="md:col-span-2 lg:col-span-1">
                    <Link
                        :href="safeRoute('guest.home', '/')"
                        class="inline-flex"
                    >
                        <img
                            src="/images/alona.png"
                            alt="Alona"
                            class="h-10 w-auto object-contain"
                        />
                    </Link>

                    <p
                        class="mt-4 max-w-xs text-sm leading-6 text-[#64748B]"
                    >
                        Discover products, connect with sellers, and enjoy
                        simple shopping with Alona.
                    </p>
                </div>

                <!-- SHOP -->
                <div>
                    <h3
                        class="text-xs font-black uppercase tracking-[0.16em] text-[#1F2937]"
                    >
                        Shop
                    </h3>

                    <div class="mt-4 space-y-3">
                        <Link
                            :href="safeRoute('guest.products')"
                            class="block text-sm text-[#64748B] transition hover:text-[#087F8C]"
                        >
                            All products
                        </Link>

                        <Link
                            :href="safeRoute('guest.products')"
                            class="block text-sm text-[#64748B] transition hover:text-[#087F8C]"
                        >
                            Categories
                        </Link>

                        <a
                            href="#"
                            class="block text-sm text-[#64748B] transition hover:text-[#087F8C]"
                        >
                            Contact us
                        </a>

                        <a
                            href="#"
                            class="block text-sm text-[#64748B] transition hover:text-[#087F8C]"
                        >
                            FAQs
                        </a>
                    </div>
                </div>

                <!-- SELL WITH ALONA -->
                <div>
                    <h3
                        class="text-xs font-black uppercase tracking-[0.16em] text-[#1F2937]"
                    >
                        Sell with Alona
                    </h3>

                    <p
                        class="mt-4 text-sm leading-6 text-[#64748B]"
                    >
                        Start selling your products and grow your business
                        with Alona Marketplace.
                    </p>

                    <button
                        type="button"
                        class="mt-4 inline-flex rounded-xl bg-[#F4B942] px-4 py-2.5 text-xs font-extrabold text-[#1F2937] transition hover:-translate-y-0.5 hover:shadow-md"
                        @click="openAuthModal('register-seller')"
                    >
                        Become a Seller
                    </button>
                </div>
            </div>

            <!-- FOOTER BOTTOM -->
            <div class="border-t border-[#E5E7EB]">
                <div
                    class="mx-auto flex max-w-[1440px] flex-col gap-2 px-4 py-5 text-xs text-[#64748B] sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8"
                >
                    <p>
                        © {{ new Date().getFullYear() }} Alona.
                        All rights reserved.
                    </p>

                    <p>
                        A marketplace made simple.
                    </p>
                </div>
            </div>
        </footer>

        <!-- AUTH MODAL -->
        <AuthModal
            :show="authModalOpen"
            :mode="authModalMode"
            @close="closeAuthModal"
            @update:mode="authModalMode = $event"
        />
    </div>
</template>

<style scoped>
input[type='search']::-webkit-search-cancel-button {
    cursor: pointer;
}
</style>