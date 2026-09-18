<script setup>
import { computed, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AuthModal from '@/Pages/Auth/AuthModal.vue'

const page = usePage()

const mobileOpen = ref(false)
const searchQuery = ref('')

const authModalOpen = ref(false)
const authModalMode = ref('login')

const user = computed(() => page.props.auth?.user ?? null)

const isLoggedIn = computed(() => !!user.value)

const userType = computed(() =>
    user.value?.usertype ?? user.value?.role ?? null
)

const dashboardRoute = computed(() => {
    if (userType.value === 'admin') {
        return 'admin.dashboard'
    }

    if (userType.value === 'seller') {
        return 'seller.dashboard'
    }

    return 'buyer.dashboard'
})

const categories = computed(() =>
    Array.isArray(page.props.categories)
        ? page.props.categories
        : []
)

const safeRoute = (name, fallback = '#') => {
    try {
        return route(name)
    } catch (e) {
        return fallback
    }
}

const goToDashboard = () => {
    const destination = safeRoute(dashboardRoute.value)

    if (destination !== '#') {
        mobileOpen.value = false
        router.visit(destination)
    }
}

const searchProducts = () => {
    const query = searchQuery.value.trim()

    if (!query) {
        router.visit(safeRoute('guest.products'))
        return
    }

    mobileOpen.value = false

    router.get(
        safeRoute('guest.products'),
        {
            search: query,
        },
        {
            preserveState: true,
            preserveScroll: false,
        }
    )
}

const goToCategory = category => {
    mobileOpen.value = false

    router.get(
        safeRoute('guest.products'),
        {
            category: category.slug ?? category.id,
        }
    )
}

const openCart = () => {
    if (isLoggedIn.value) {
        router.visit(safeRoute('buyer.cart'))
        return
    }

    authModalMode.value = 'login'
    authModalOpen.value = true
}

const openAuthModal = mode => {
    authModalMode.value = mode
    authModalOpen.value = true
    mobileOpen.value = false
}

const closeAuthModal = () => {
    authModalOpen.value = false
}

const toggleMobileMenu = () => {
    mobileOpen.value = !mobileOpen.value
}
</script>

<template>
    <div class="min-h-screen bg-[#F8FAF9] text-[#1F2937]">
        <!-- HEADER -->
        <header
            class="sticky top-0 z-50 border-b border-[#E5E7EB] bg-white/95 shadow-sm backdrop-blur"
        >
            <!-- MAIN HEADER ROW -->
            <div
                class="mx-auto flex h-[68px] max-w-[1440px] items-center gap-2 px-3 sm:h-[74px] sm:gap-3 sm:px-6 lg:h-[78px] lg:gap-5 lg:px-8"
            >
                <!-- LOGO -->
                <Link
                    href="/"
                    class="flex shrink-0 items-center transition duration-200 hover:opacity-90"
                    aria-label="Alona Home"
                >
                    <img
                        src="/images/alona.png"
                        alt="Alona"
                        class="h-8 w-auto object-contain sm:h-9 lg:h-11"
                    />
                </Link>

                <!-- DESKTOP SEARCH -->
                <div class="hidden min-w-0 flex-1 md:block">
                    <form
                        class="relative mx-auto w-full max-w-[720px]"
                        @submit.prevent="searchProducts"
                    >
                        <!-- Search Icon -->
                        <span
                            class="pointer-events-none absolute left-4 top-1/2 z-10 flex -translate-y-1/2 items-center justify-center text-[#64748B]"
                        >
                            <svg
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                                aria-hidden="true"
                            >
                                <circle
                                    cx="11"
                                    cy="11"
                                    r="7"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                />
                                <path
                                    d="M20 20L16.2 16.2"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                />
                            </svg>
                        </span>

                        <input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Search products, brands and more..."
                            class="h-11 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] pl-11 pr-4 text-sm text-[#1F2937] outline-none transition duration-200 placeholder:text-[#94A3B8] hover:border-[#CBD5E1] focus:border-[#16A6A0] focus:bg-white focus:ring-4 focus:ring-[#16A6A0]/10"
                        />
                    </form>
                </div>

                <!-- MOBILE SEARCH -->
                <div class="min-w-0 flex-1 md:hidden">
                    <form
                        class="relative w-full"
                        @submit.prevent="searchProducts"
                    >
                        <!-- Search Icon -->
                        <span
                            class="pointer-events-none absolute left-3 top-1/2 z-10 flex -translate-y-1/2 items-center justify-center text-[#64748B]"
                        >
                            <svg
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                                aria-hidden="true"
                            >
                                <circle
                                    cx="11"
                                    cy="11"
                                    r="7"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                />
                                <path
                                    d="M20 20L16.2 16.2"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                />
                            </svg>
                        </span>

                        <input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Search..."
                            class="h-10 w-full min-w-0 rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] pl-9 pr-2 text-xs text-[#1F2937] outline-none transition duration-200 placeholder:text-[#94A3B8] focus:border-[#16A6A0] focus:bg-white focus:ring-2 focus:ring-[#16A6A0]/10 sm:h-11 sm:pl-10 sm:text-sm"
                        />
                    </form>
                </div>

                <!-- DESKTOP ACTIONS -->
                <div class="hidden shrink-0 items-center gap-2 md:flex">
                    <!-- Cart -->
                    <button
                        type="button"
                        class="group flex h-10 items-center gap-2 rounded-xl px-3 text-[#64748B] transition duration-200 hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                        @click="openCart"
                    >
                        <span
                            class="flex h-9 w-9 items-center justify-center rounded-lg transition duration-200 group-hover:bg-white"
                        >
                            <svg
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                                aria-hidden="true"
                            >
                                <path
                                    d="M3 4H5L7.4 15.2C7.57 16 8.28 16.6 9.1 16.6H17.6C18.37 16.6 19.05 16.1 19.28 15.36L21 9H6"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                                <circle
                                    cx="9.5"
                                    cy="20"
                                    r="1.2"
                                    fill="currentColor"
                                />
                                <circle
                                    cx="17.5"
                                    cy="20"
                                    r="1.2"
                                    fill="currentColor"
                                />
                            </svg>
                        </span>

                        <span class="text-sm font-bold">
                            Cart
                        </span>
                    </button>

                    <!-- Login / Dashboard -->
                    <template v-if="isLoggedIn">
                        <button
                            type="button"
                            class="inline-flex h-10 items-center rounded-xl bg-[#087F8C] px-4 text-sm font-extrabold text-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:bg-[#066B76] hover:shadow-md active:translate-y-0"
                            @click="goToDashboard"
                        >
                            Dashboard
                        </button>
                    </template>

                    <template v-else>
                        <button
                            type="button"
                            class="inline-flex h-10 items-center rounded-xl px-4 text-sm font-extrabold text-[#087F8C] transition duration-200 hover:bg-[#E8F7F6]"
                            @click="openAuthModal('login')"
                        >
                            Log in
                        </button>

                        <button
                            type="button"
                            class="inline-flex h-10 items-center rounded-xl bg-[#087F8C] px-4 text-sm font-extrabold text-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:bg-[#066B76] hover:shadow-md active:translate-y-0"
                            @click="openAuthModal('register')"
                        >
                            Register
                        </button>
                    </template>
                </div>

                <!-- MOBILE ACTIONS -->
                <div class="flex shrink-0 items-center gap-0.5 md:hidden">
                    <!-- Mobile Cart -->
                    <button
                        type="button"
                        class="flex h-10 w-10 items-center justify-center rounded-xl text-[#64748B] transition duration-200 hover:bg-[#E8F7F6] hover:text-[#087F8C] active:scale-95"
                        aria-label="Shopping cart"
                        @click="openCart"
                    >
                        <svg
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                            aria-hidden="true"
                        >
                            <path
                                d="M3 4H5L7.4 15.2C7.57 16 8.28 16.6 9.1 16.6H17.6C18.37 16.6 19.05 16.1 19.28 15.36L21 9H6"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                            <circle
                                cx="9.5"
                                cy="20"
                                r="1.2"
                                fill="currentColor"
                            />
                            <circle
                                cx="17.5"
                                cy="20"
                                r="1.2"
                                fill="currentColor"
                            />
                        </svg>
                    </button>

                    <!-- Mobile Menu -->
                    <button
                        type="button"
                        class="flex h-10 w-10 items-center justify-center rounded-xl text-[#64748B] transition duration-200 hover:bg-[#E8F7F6] hover:text-[#087F8C] active:scale-95"
                        :aria-expanded="mobileOpen"
                        aria-label="Toggle menu"
                        @click="toggleMobileMenu"
                    >
                        <svg
                            v-if="!mobileOpen"
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                            aria-hidden="true"
                        >
                            <path
                                d="M4 6H20M4 12H20M4 18H20"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                            />
                        </svg>

                        <svg
                            v-else
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                            aria-hidden="true"
                        >
                            <path
                                d="M6 6L18 18M18 6L6 18"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                            />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- MOBILE MENU -->
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="translate-y-[-8px] opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="translate-y-[-8px] opacity-0"
            >
                <div
                    v-if="mobileOpen"
                    class="border-t border-[#E5E7EB] bg-white md:hidden"
                >
                    <div
                        class="mx-auto max-h-[calc(100vh-80px)] max-w-[1440px] overflow-y-auto px-4 py-4 sm:px-6"
                    >
                        <!-- Menu Header -->
                        <div class="mb-3 flex items-center gap-2">
                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]"
                            >
                                <svg
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    xmlns="http://www.w3.org/2000/svg"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M4 6H20M4 12H20M4 18H20"
                                        stroke="currentColor"
                                        stroke-width="2"
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

                        <!-- Menu Actions -->
                        <div class="grid grid-cols-2 gap-2">
                            <template v-if="isLoggedIn">
                                <button
                                    type="button"
                                    class="col-span-2 flex h-11 items-center justify-center rounded-xl bg-[#087F8C] px-4 text-sm font-extrabold text-white transition duration-200 hover:bg-[#066B76] active:scale-[0.98]"
                                    @click="goToDashboard"
                                >
                                    Dashboard
                                </button>
                            </template>

                            <template v-else>
                                <button
                                    type="button"
                                    class="flex h-11 items-center justify-center rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm font-extrabold text-[#087F8C] transition duration-200 hover:border-[#16A6A0] hover:bg-[#E8F7F6] active:scale-[0.98]"
                                    @click="openAuthModal('login')"
                                >
                                    Log in
                                </button>

                                <button
                                    type="button"
                                    class="flex h-11 items-center justify-center rounded-xl bg-[#087F8C] px-4 text-sm font-extrabold text-white transition duration-200 hover:bg-[#066B76] active:scale-[0.98]"
                                    @click="openAuthModal('register')"
                                >
                                    Register
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </Transition>
        </header>

        <!-- PAGE CONTENT -->
        <main>
            <slot />
        </main>

        <!-- FOOTER -->
        <footer class="border-t border-[#E5E7EB] bg-white">
            <div
                class="mx-auto max-w-[1440px] px-4 py-10 sm:px-6 lg:px-8 lg:py-12"
            >
                <div
                    class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4 lg:gap-10"
                >
                    <!-- Brand -->
                    <div class="sm:col-span-2 lg:col-span-1">
                        <Link
                            href="/"
                            class="inline-flex items-center"
                            aria-label="Alona Home"
                        >
                            <img
                                src="/images/alona.png"
                                alt="Alona"
                                class="h-10 w-auto object-contain"
                            />
                        </Link>

                        <p
                            class="mt-4 max-w-sm text-sm leading-6 text-[#64748B]"
                        >
                            Discover products from trusted sellers and enjoy
                            a smooth shopping experience with Alona
                            Marketplace.
                        </p>
                    </div>

                    <!-- Shop -->
                    <div>
                        <h3
                            class="text-sm font-extrabold uppercase tracking-wide text-[#1F2937]"
                        >
                            Shop
                        </h3>

                        <div class="mt-4 space-y-3">
                            <Link
                                :href="safeRoute('guest.products')"
                                class="block text-sm text-[#64748B] transition duration-200 hover:text-[#087F8C]"
                            >
                                All Products
                            </Link>

                            <Link
                                :href="safeRoute('guest.deals', safeRoute('guest.products'))"
                                class="block text-sm text-[#64748B] transition duration-200 hover:text-[#087F8C]"
                            >
                                Deals
                            </Link>
                        </div>
                    </div>

                    <!-- Help -->
                    <div>
                        <h3
                            class="text-sm font-extrabold uppercase tracking-wide text-[#1F2937]"
                        >
                            Help
                        </h3>

                        <div class="mt-4 space-y-3">
                            <a
                                href="#"
                                class="block text-sm text-[#64748B] transition duration-200 hover:text-[#087F8C]"
                            >
                                Contact Us
                            </a>

                            <a
                                href="#"
                                class="block text-sm text-[#64748B] transition duration-200 hover:text-[#087F8C]"
                            >
                                Shipping & Delivery
                            </a>

                            <a
                                href="#"
                                class="block text-sm text-[#64748B] transition duration-200 hover:text-[#087F8C]"
                            >
                                Returns & Refunds
                            </a>
                        </div>
                    </div>

                    <!-- Sell -->
                    <div>
                        <h3
                            class="text-sm font-extrabold uppercase tracking-wide text-[#1F2937]"
                        >
                            Sell with Alona
                        </h3>

                        <p
                            class="mt-4 text-sm leading-6 text-[#64748B]"
                        >
                            Start selling your products and grow your
                            business with Alona Marketplace.
                        </p>

                        <button
                            type="button"
                            class="mt-4 inline-flex h-10 items-center rounded-xl bg-[#F4B942] px-4 text-sm font-extrabold text-[#1F2937] shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md active:translate-y-0"
                            @click="openAuthModal('register-seller')"
                        >
                            Become a Seller
                        </button>
                    </div>
                </div>

                <!-- Footer Bottom -->
                <div
                    class="mt-10 flex flex-col gap-3 border-t border-[#E5E7EB] pt-6 text-sm text-[#64748B] sm:flex-row sm:items-center sm:justify-between"
                >
                    <p>
                        © {{ new Date().getFullYear() }} Alona Marketplace.
                        All rights reserved.
                    </p>

                    <div class="flex items-center gap-4">
                        <a
                            href="#"
                            class="transition duration-200 hover:text-[#087F8C]"
                        >
                            Privacy
                        </a>

                        <a
                            href="#"
                            class="transition duration-200 hover:text-[#087F8C]"
                        >
                            Terms
                        </a>
                    </div>
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