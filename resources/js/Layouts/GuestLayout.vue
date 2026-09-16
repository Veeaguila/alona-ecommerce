<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AuthModal from '@/Pages/Auth/AuthModal.vue'

const page = usePage()

const mobileOpen = ref(false)
const searchQuery = ref('')
const categoriesOpen = ref(false)
const mobileCategoriesOpen = ref(false)

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
        router.visit(destination)
    }
}

const searchProducts = () => {
    const query = searchQuery.value.trim()

    if (!query) {
        router.visit(safeRoute('guest.products'))
        return
    }

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
    categoriesOpen.value = false
    mobileCategoriesOpen.value = false
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

const authModalOpen = ref(false)
const authModalMode = ref('login')

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

    if (!mobileOpen.value) {
        mobileCategoriesOpen.value = false
    }
}

const handleOutsideClick = event => {
    const target = event.target

    if (!target.closest('[data-category-dropdown]')) {
        categoriesOpen.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleOutsideClick)
})

onUnmounted(() => {
    document.removeEventListener('click', handleOutsideClick)
})
</script>

<template>
    <div class="min-h-screen bg-[#F8FAF9] text-[#1F2937]">

        <!-- ========================================================= -->
        <!-- HEADER -->
        <!-- ========================================================= -->

        <header
            class="sticky top-0 z-50 border-b border-[#E5E7EB]/80 bg-white/95 backdrop-blur-xl"
        >

            <div
                class="mx-auto flex h-[72px] max-w-[1440px] items-center gap-3 px-4 sm:h-[78px] sm:px-6 lg:gap-5 lg:px-8"
            >

                <!-- LOGO -->

                <Link
                    href="/"
                    class="flex shrink-0 items-center"
                    aria-label="Alona Home"
                >
                    <img
                        src="/images/alona.png"
                        alt="Alona"
                        class="h-9 w-auto object-contain sm:h-10 lg:h-11"
                    />
                </Link>


                <!-- DESKTOP SEARCH + CATEGORIES -->

                <div
                    class="hidden min-w-0 flex-1 items-center gap-2 md:flex"
                >

                    <!-- Search -->

                    <form
                        class="relative min-w-0 flex-1"
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


                    <!-- CATEGORIES -->

                    <div
                        class="relative shrink-0"
                        data-category-dropdown
                    >

                        <button
                            type="button"
                            class="flex h-11 items-center gap-2 rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm font-bold text-[#1F2937] transition hover:border-[#16A6A0] hover:bg-[#E8F7F6]"
                            :class="{
                                'border-[#16A6A0] bg-[#E8F7F6] text-[#087F8C]':
                                    categoriesOpen
                            }"
                            @click.stop="categoriesOpen = !categoriesOpen"
                        >

                            <svg
                                class="h-4 w-4 text-[#087F8C]"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    d="M4 6h16M4 12h16M4 18h16"
                                    stroke-linecap="round"
                                />
                            </svg>

                            <span>
                                Categories
                            </span>

                            <svg
                                class="h-3.5 w-3.5 transition-transform duration-200"
                                :class="{
                                    'rotate-180': categoriesOpen
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


                        <!-- DESKTOP CATEGORY DROPDOWN -->

                        <Transition
                            enter-active-class="transition duration-150 ease-out"
                            enter-from-class="translate-y-1 opacity-0"
                            enter-to-class="translate-y-0 opacity-100"
                            leave-active-class="transition duration-100 ease-in"
                            leave-from-class="translate-y-0 opacity-100"
                            leave-to-class="translate-y-1 opacity-0"
                        >

                            <div
                                v-if="categoriesOpen"
                                class="absolute right-0 top-[calc(100%+10px)] z-50 w-[300px] overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white p-2 shadow-2xl shadow-black/10"
                                @click.stop
                            >

                                <div
                                    class="border-b border-[#E5E7EB] px-3 py-2.5"
                                >
                                    <p
                                        class="text-[10px] font-black uppercase tracking-[0.18em] text-[#087F8C]"
                                    >
                                        Browse
                                    </p>

                                    <p
                                        class="mt-0.5 text-xs text-[#64748B]"
                                    >
                                        Explore products by category
                                    </p>
                                </div>


                                <div
                                    v-if="categories.length"
                                    class="max-h-[420px] overflow-y-auto py-1"
                                >

                                    <button
                                        v-for="category in categories"
                                        :key="category.id ?? category.slug ?? category.name"
                                        type="button"
                                        class="flex w-full items-center justify-between rounded-xl px-3 py-3 text-left transition hover:bg-[#E8F7F6]"
                                        @click="goToCategory(category)"
                                    >

                                        <span
                                            class="flex min-w-0 items-center gap-3"
                                        >

                                            <span
                                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                                            >
                                                <svg
                                                    class="h-4 w-4"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="1.8"
                                                >
                                                    <path
                                                        d="M4 6h16M4 12h16M4 18h16"
                                                        stroke-linecap="round"
                                                    />
                                                </svg>
                                            </span>

                                            <span class="min-w-0">
                                                <span
                                                    class="block truncate text-sm font-bold text-[#1F2937]"
                                                >
                                                    {{ category.name }}
                                                </span>

                                                <span
                                                    class="block text-[10px] text-[#64748B]"
                                                >
                                                    {{ category.products_count ?? 0 }}
                                                    products
                                                </span>
                                            </span>

                                        </span>


                                        <svg
                                            class="h-4 w-4 shrink-0 text-[#94A3B8]"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <path
                                                d="m9 18 6-6-6-6"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            />
                                        </svg>

                                    </button>

                                </div>


                                <div
                                    v-else
                                    class="px-3 py-6 text-center text-xs text-[#64748B]"
                                >
                                    No categories available.
                                </div>

                            </div>

                        </Transition>

                    </div>

                </div>


                <!-- DESKTOP ACTIONS -->

                <div
                    class="ml-auto hidden shrink-0 items-center gap-2 md:flex"
                >

                    <!-- CART -->

                    <button
                        type="button"
                        class="flex h-10 w-10 items-center justify-center rounded-xl text-[#1F2937] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                        aria-label="Cart"
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
                    </button>


                    <template v-if="isLoggedIn">

                        <button
                            type="button"
                            class="rounded-xl bg-[#087F8C] px-4 py-2.5 text-xs font-extrabold text-white transition hover:bg-[#066974]"
                            @click="goToDashboard"
                        >
                            Dashboard
                        </button>

                    </template>


                    <template v-else>

                        <button
                            type="button"
                            class="rounded-xl px-3.5 py-2.5 text-xs font-bold text-[#1F2937] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                            @click="openAuthModal('login')"
                        >
                            Log in
                        </button>

                        <button
                            type="button"
                            class="rounded-xl bg-[#087F8C] px-4 py-2.5 text-xs font-extrabold text-white transition hover:bg-[#066974]"
                            @click="openAuthModal('register')"
                        >
                            Register
                        </button>

                    </template>

                </div>


                <!-- MOBILE ACTIONS -->

                <div
                    class="ml-auto flex items-center gap-1 md:hidden"
                >

                    <!-- Cart -->

                    <button
                        type="button"
                        class="flex h-10 w-10 items-center justify-center rounded-xl text-[#1F2937] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                        aria-label="Cart"
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
                    </button>


                    <!-- Menu -->

                    <button
                        type="button"
                        class="flex h-10 w-10 items-center justify-center rounded-xl text-[#1F2937] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                        aria-label="Open menu"
                        :aria-expanded="mobileOpen"
                        @click="toggleMobileMenu"
                    >

                        <svg
                            v-if="!mobileOpen"
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                d="M4 6h16M4 12h16M4 18h16"
                                stroke-linecap="round"
                            />
                        </svg>

                        <svg
                            v-else
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                d="M6 6l12 12M18 6 6 18"
                                stroke-linecap="round"
                            />
                        </svg>

                    </button>

                </div>

            </div>


            <!-- ========================================================= -->
            <!-- MOBILE MENU -->
            <!-- ========================================================= -->

            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="-translate-y-2 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="-translate-y-2 opacity-0"
            >

                <div
                    v-if="mobileOpen"
                    class="border-t border-[#E5E7EB] bg-white md:hidden"
                >

                    <div
                        class="mx-auto max-h-[calc(100vh-78px)] max-w-[1440px] overflow-y-auto px-4 py-4 sm:px-6"
                    >

                        <!-- MOBILE SEARCH -->

                        <form
                            class="relative"
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
                                class="h-12 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] pl-11 pr-4 text-sm outline-none transition focus:border-[#16A6A0] focus:bg-white focus:ring-2 focus:ring-[#16A6A0]/10"
                            />

                        </form>


                        <!-- MOBILE CATEGORIES -->

                        <div
                            class="mt-3 overflow-hidden rounded-2xl border border-[#E5E7EB]"
                        >

                            <button
                                type="button"
                                class="flex min-h-[52px] w-full items-center justify-between px-4 text-sm font-extrabold text-[#1F2937] transition hover:bg-[#E8F7F6]"
                                :class="{
                                    'bg-[#E8F7F6] text-[#087F8C]':
                                        mobileCategoriesOpen
                                }"
                                @click="mobileCategoriesOpen = !mobileCategoriesOpen"
                            >

                                <span class="flex items-center gap-3">

                                    <span
                                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >
                                            <path
                                                d="M4 6h16M4 12h16M4 18h16"
                                                stroke-linecap="round"
                                            />
                                        </svg>
                                    </span>

                                    Categories

                                </span>


                                <svg
                                    class="h-4 w-4 transition-transform duration-200"
                                    :class="{
                                        'rotate-180':
                                            mobileCategoriesOpen
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


                            <!-- CATEGORY LIST -->

                            <div
                                v-if="mobileCategoriesOpen"
                                class="border-t border-[#E5E7EB] bg-[#F8FAF9] p-2"
                            >

                                <div
                                    v-if="categories.length"
                                    class="max-h-[60vh] overflow-y-auto"
                                >

                                    <button
                                        v-for="category in categories"
                                        :key="category.id ?? category.slug ?? category.name"
                                        type="button"
                                        class="flex min-h-[48px] w-full items-center justify-between rounded-xl px-3 py-2 text-left transition active:bg-[#E8F7F6] hover:bg-[#E8F7F6]"
                                        @click="goToCategory(category)"
                                    >

                                        <span
                                            class="flex min-w-0 items-center gap-3"
                                        >

                                            <span
                                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-[#087F8C] shadow-sm"
                                            >
                                                <svg
                                                    class="h-4 w-4"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="1.8"
                                                >
                                                    <path
                                                        d="M4 6h16M4 12h16M4 18h16"
                                                        stroke-linecap="round"
                                                    />
                                                </svg>
                                            </span>

                                            <span
                                                class="min-w-0"
                                            >
                                                <span
                                                    class="block truncate text-sm font-bold text-[#1F2937]"
                                                >
                                                    {{ category.name }}
                                                </span>

                                                <span
                                                    class="block text-[10px] text-[#64748B]"
                                                >
                                                    {{ category.products_count ?? 0 }}
                                                    products
                                                </span>
                                            </span>

                                        </span>

                                        <svg
                                            class="h-4 w-4 shrink-0 text-[#94A3B8]"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <path
                                                d="m9 18 6-6-6-6"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            />
                                        </svg>

                                    </button>

                                </div>

                                <div
                                    v-else
                                    class="px-3 py-5 text-center text-xs text-[#64748B]"
                                >
                                    No categories available.
                                </div>

                            </div>

                        </div>


                        <!-- MOBILE AUTH -->

                        <div
                            class="mt-3 grid grid-cols-2 gap-2"
                        >

                            <template v-if="isLoggedIn">

                                <button
                                    type="button"
                                    class="col-span-2 rounded-xl bg-[#087F8C] px-4 py-3 text-sm font-extrabold text-white"
                                    @click="goToDashboard"
                                >
                                    Dashboard
                                </button>

                            </template>


                            <template v-else>

                                <button
                                    type="button"
                                    class="rounded-xl border border-[#E5E7EB] px-4 py-3 text-sm font-bold text-[#1F2937] transition hover:bg-[#F8FAF9]"
                                    @click="openAuthModal('login')"
                                >
                                    Log in
                                </button>

                                <button
                                    type="button"
                                    class="rounded-xl bg-[#087F8C] px-4 py-3 text-sm font-extrabold text-white transition hover:bg-[#066974]"
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


        <!-- ========================================================= -->
        <!-- PAGE CONTENT -->
        <!-- ========================================================= -->

        <main>
            <slot />
        </main>


        <!-- ========================================================= -->
        <!-- FOOTER -->
        <!-- ========================================================= -->

        <footer
            class="border-t border-[#E5E7EB] bg-white"
        >

            <div
                class="mx-auto grid max-w-[1440px] gap-10 px-4 py-12 sm:px-6 md:grid-cols-2 lg:grid-cols-4 lg:px-8"
            >

                <!-- Brand -->

                <div class="md:col-span-2 lg:col-span-1">

                    <Link
                        href="/"
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


                <!-- Shop -->

                <div>

                    <h3
                        class="text-xs font-black uppercase tracking-[0.16em] text-[#1F2937]"
                    >
                        Shop
                    </h3>

                    <div class="mt-4 space-y-3">

                        <Link
                            :href="route('guest.products')"
                            class="block text-sm text-[#64748B] transition hover:text-[#087F8C]"
                        >
                            All products
                        </Link>

                        <Link
                            :href="route('guest.categories')"
                            class="block text-sm text-[#64748B] transition hover:text-[#087F8C]"
                        >
                            Categories
                        </Link>

                    </div>

                </div>


                <!-- Help -->

                <div>

                    <h3
                        class="text-xs font-black uppercase tracking-[0.16em] text-[#1F2937]"
                    >
                        Help
                    </h3>

                    <div class="mt-4 space-y-3">

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


                <!-- Seller -->

                <div>

                    <h3
                        class="text-xs font-black uppercase tracking-[0.16em] text-[#1F2937]"
                    >
                        Sell with Alona
                    </h3>

                    <p
                        class="mt-4 text-sm leading-6 text-[#64748B]"
                    >
                        Turn your products into opportunities and reach more
                        customers.
                    </p>

                    <button
                        type="button"
                        class="mt-4 inline-flex rounded-xl bg-[#E8F7F6] px-4 py-2.5 text-xs font-extrabold text-[#087F8C] transition hover:bg-[#087F8C] hover:text-white"
                        @click="openAuthModal('register-seller')"
                    >
                        Become a seller
                    </button>

                </div>

            </div>


            <div
                class="border-t border-[#E5E7EB]"
            >

                <div
                    class="mx-auto flex max-w-[1440px] flex-col gap-2 px-4 py-5 text-xs text-[#64748B] sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8"
                >

                    <p>
                        © {{ new Date().getFullYear() }} Alona. All rights reserved.
                    </p>

                    <p>
                        A marketplace made simple.
                    </p>

                </div>

            </div>

        </footer>


        <!-- ========================================================= -->
        <!-- AUTH MODAL -->
        <!-- ========================================================= -->

        <AuthModal
            :show="authModalOpen"
            :mode="authModalMode"
            @close="closeAuthModal"
            @update:mode="authModalMode = $event"
        />

    </div>
</template>