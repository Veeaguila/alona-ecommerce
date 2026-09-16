<script setup>
import {
    computed,
    onMounted,
    onUnmounted,
    ref,
} from 'vue'

import {
    Link,
    router,
    usePage,
} from '@inertiajs/vue3'

const page = usePage()

/*
|--------------------------------------------------------------------------
| STATE
|--------------------------------------------------------------------------
*/

const mobileOpen = ref(false)
const searchQuery = ref('')

const categoriesOpen = ref(false)
const mobileCategoriesOpen = ref(false)

const accountOpen = ref(false)
const mobileAccountOpen = ref(false)

const notificationsOpen = ref(false)
const mobileNotificationsOpen = ref(false)
const notificationsMarkedRead = ref(false)


/*
|--------------------------------------------------------------------------
| USER
|--------------------------------------------------------------------------
*/

const user = computed(() =>
    page.props.auth?.user ?? null
)


const userName = computed(() => {
    if (!user.value) {
        return 'Account'
    }

    return (
        user.value.name ??
        user.value.first_name ??
        user.value.firstname ??
        'Account'
    )
})


const userEmail = computed(() => {
    return user.value?.email ?? ''
})


/*
|--------------------------------------------------------------------------
| USER PROFILE PHOTO
|--------------------------------------------------------------------------
*/

const userProfilePhoto = computed(() => {
    const path =
        user.value?.profile_photo_url ??
        user.value?.profile_photo ??
        user.value?.avatar_url ??
        user.value?.avatar ??
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


/*
|--------------------------------------------------------------------------
| CATEGORIES
|--------------------------------------------------------------------------
*/

const categories = computed(() =>
    Array.isArray(page.props.categories)
        ? page.props.categories
        : []
)


/*
|--------------------------------------------------------------------------
| BUYER COUNTS
|--------------------------------------------------------------------------
*/

const buyerCounts = computed(() =>
    page.props.buyerCounts ?? {}
)


const cartCount = computed(() =>
    Number(
        buyerCounts.value.cart ??
        buyerCounts.value.cart_count ??
        0
    )
)


const wishlistCount = computed(() =>
    Number(
        buyerCounts.value.wishlist ??
        buyerCounts.value.wishlist_count ??
        0
    )
)


const notificationCount = computed(() => {
    if (notificationsMarkedRead.value) {
        return 0
    }

    return Number(
        buyerCounts.value.notifications ??
        buyerCounts.value.notification_count ??
        0
    )
})


const notificationItems = computed(() => {
    const source =
        page.props.notifications ??
        page.props.buyerNotifications ??
        page.props.buyer_notifications ??
        page.props.auth?.notifications ??
        []

    const items = Array.isArray(source)
        ? source
        : Array.isArray(source?.data)
            ? source.data
            : []

    return items.slice(0, 5).map((item, index) => {
        const data = item?.data ?? {}

        return {
            id: item?.id ?? item?.uuid ?? `notification-${index}`,

            title:
                item?.title ??
                data.title ??
                item?.subject ??
                data.subject ??
                'Notification',

            message:
                item?.message ??
                data.message ??
                item?.body ??
                data.body ??
                item?.description ??
                data.description ??
                '',

            createdAt:
                item?.created_at ??
                item?.createdAt ??
                '',

            read:
                notificationsMarkedRead.value ||
                Boolean(item?.read_at ?? item?.read),

            url:
                item?.url ??
                data.url ??
                item?.link ??
                data.link ??
                null,
        }
    })
})


/*
|--------------------------------------------------------------------------
| SAFE ROUTE
|--------------------------------------------------------------------------
*/

const safeRoute = (name, fallback = '#') => {
    try {
        return route(name)
    } catch (e) {
        return fallback
    }
}


/*
|--------------------------------------------------------------------------
| DASHBOARD
|--------------------------------------------------------------------------
*/

const goToDashboard = () => {
    closeMenus()

    const destination = safeRoute('buyer.dashboard')

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

    const destination = safeRoute('buyer.products')

    if (destination === '#') {
        return
    }

    closeMenus()

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
| CATEGORY
|--------------------------------------------------------------------------
*/

const goToCategory = category => {
    categoriesOpen.value = false
    mobileCategoriesOpen.value = false
    mobileOpen.value = false

    const destination = safeRoute('buyer.products')

    if (destination === '#') {
        return
    }

    router.get(destination, {
        category: category.slug ?? category.id,
    })
}


/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/

const openMessages = () => {
    closeMenus()

    const destination = safeRoute(
        'buyer.conversations',
        safeRoute('buyer.messages')
    )

    if (destination !== '#') {
        router.visit(destination)
    }
}


/*
|--------------------------------------------------------------------------
| CART
|--------------------------------------------------------------------------
*/

const openCart = () => {
    closeMenus()

    const destination = safeRoute('buyer.cart')

    if (destination !== '#') {
        router.visit(destination)
    }
}


/*
|--------------------------------------------------------------------------
| ACCOUNT
|--------------------------------------------------------------------------
*/

const toggleAccount = () => {
    accountOpen.value = !accountOpen.value

    categoriesOpen.value = false
    notificationsOpen.value = false
}


const toggleMobileAccount = () => {
    mobileAccountOpen.value = !mobileAccountOpen.value

    mobileCategoriesOpen.value = false
    mobileNotificationsOpen.value = false
}


const openProfile = () => {
    closeMenus()

    const destination = safeRoute(
        'buyer.profile',
        safeRoute('profile.edit')
    )

    if (destination !== '#') {
        router.visit(destination)
    }
}


const openOrders = () => {
    closeMenus()

    const destination = safeRoute('buyer.orders')

    if (destination !== '#') {
        router.visit(destination)
    }
}


const openWishlist = () => {
    closeMenus()

    const destination = safeRoute('buyer.wishlist')

    if (destination !== '#') {
        router.visit(destination)
    }
}


/*
|--------------------------------------------------------------------------
| NOTIFICATIONS
|--------------------------------------------------------------------------
*/

const toggleNotifications = () => {
    notificationsOpen.value = !notificationsOpen.value

    categoriesOpen.value = false
    accountOpen.value = false
    mobileOpen.value = false
    mobileNotificationsOpen.value = false
    mobileAccountOpen.value = false
}


const toggleMobileNotifications = () => {
    mobileNotificationsOpen.value =
        !mobileNotificationsOpen.value

    categoriesOpen.value = false
    accountOpen.value = false
    mobileOpen.value = false
    notificationsOpen.value = false
    mobileAccountOpen.value = false
}


const markAllNotificationsRead = () => {
    notificationsMarkedRead.value = true

    const destination =
        safeRoute('buyer.notifications.markAllRead') !== '#'
            ? safeRoute('buyer.notifications.markAllRead')
            : safeRoute('buyer.notifications.readAll') !== '#'
                ? safeRoute('buyer.notifications.readAll')
                : safeRoute('buyer.notifications.mark-all-read')

    if (destination !== '#') {
        router.post(
            destination,
            {},
            {
                preserveScroll: true,
                preserveState: true,
            }
        )
    }
}


const seeAllNotifications = () => {
    notificationsOpen.value = false
    mobileNotificationsOpen.value = false
    closeMenus()

    const destination = safeRoute('buyer.notifications')

    if (destination !== '#') {
        router.visit(destination)
    }
}


/*
|--------------------------------------------------------------------------
| LOGOUT
|--------------------------------------------------------------------------
*/

const logout = () => {
    closeMenus()

    router.post(
        safeRoute('logout'),
        {},
        {
            preserveScroll: false,
        }
    )
}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

const toggleMobileMenu = () => {
    mobileOpen.value = !mobileOpen.value

    if (!mobileOpen.value) {
        mobileCategoriesOpen.value = false
        mobileAccountOpen.value = false
        mobileNotificationsOpen.value = false
    }
}


/*
|--------------------------------------------------------------------------
| CLOSE MENUS
|--------------------------------------------------------------------------
*/

const closeMenus = () => {
    categoriesOpen.value = false
    accountOpen.value = false

    mobileCategoriesOpen.value = false
    mobileAccountOpen.value = false

    mobileOpen.value = false

    notificationsOpen.value = false
    mobileNotificationsOpen.value = false
}


/*
|--------------------------------------------------------------------------
| OUTSIDE CLICK
|--------------------------------------------------------------------------
*/

const handleOutsideClick = event => {
    const target = event.target

    if (!target.closest('[data-category-dropdown]')) {
        categoriesOpen.value = false
    }

    if (!target.closest('[data-account-dropdown]')) {
        accountOpen.value = false
    }

    if (!target.closest('[data-notification-dropdown]')) {
        notificationsOpen.value = false
    }

    if (!target.closest('[data-mobile-notification-dropdown]')) {
        mobileNotificationsOpen.value = false
    }
}


/*
|--------------------------------------------------------------------------
| ESCAPE KEY
|--------------------------------------------------------------------------
*/

const handleEscape = event => {
    if (event.key !== 'Escape') {
        return
    }

    closeMenus()
}


/*
|--------------------------------------------------------------------------
| LIFECYCLE
|--------------------------------------------------------------------------
*/

onMounted(() => {
    document.addEventListener(
        'click',
        handleOutsideClick
    )

    document.addEventListener(
        'keydown',
        handleEscape
    )
})


onUnmounted(() => {
    document.removeEventListener(
        'click',
        handleOutsideClick
    )

    document.removeEventListener(
        'keydown',
        handleEscape
    )
})
</script>


<template>

    <div
        class="min-h-screen bg-[#F8FAF9] text-[#1F2937]"
    >

        <!-- ========================================================= -->
        <!-- HEADER -->
        <!-- ========================================================= -->

        <header
            class="sticky top-0 z-50 border-b border-[#E5E7EB]/80 bg-white/95 backdrop-blur-xl"
        >

            <!-- ===================================================== -->
            <!-- DESKTOP / MAIN HEADER -->
            <!-- ===================================================== -->

            <div
                class="mx-auto flex h-[72px] max-w-[1440px] items-center gap-3 px-4 sm:h-[78px] sm:px-6 lg:gap-5 lg:px-8"
            >

                <!-- ================================================= -->
                <!-- LOGO -->
                <!-- ================================================= -->

                <Link
                    :href="safeRoute('buyer.dashboard', '/')"
                    class="flex shrink-0 items-center"
                    aria-label="Alona Home"
                >

                    <img
                        src="/images/alona.png"
                        alt="Alona"
                        class="h-9 w-auto object-contain sm:h-10 lg:h-11"
                    />

                </Link>


                <!-- ================================================= -->
                <!-- DESKTOP SEARCH + CATEGORIES -->
                <!-- ================================================= -->

                <div
                    class="hidden min-w-0 flex-1 items-center gap-2 md:flex"
                >

                    <!-- SEARCH -->

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


                    <!-- ================================================= -->
                    <!-- CATEGORIES -->
                    <!-- ================================================= -->

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
                            @click.stop="
                                categoriesOpen = !categoriesOpen;
                                accountOpen = false;
                                notificationsOpen = false
                            "
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


                        <!-- CATEGORY DROPDOWN -->

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
                                        :key="
                                            category.id ??
                                            category.slug ??
                                            category.name
                                        "
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
                                                    {{
                                                        category.products_count ??
                                                        0
                                                    }}
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


                <!-- ================================================= -->
                <!-- DESKTOP ACTIONS -->
                <!-- ================================================= -->

                <div
                    class="ml-auto hidden shrink-0 items-center gap-1 md:flex"
                >

                    <!-- ================================================= -->
                    <!-- MESSAGES -->
                    <!-- ================================================= -->

                    <button
                        type="button"
                        class="relative flex h-10 w-10 items-center justify-center rounded-xl text-[#1F2937] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                        aria-label="Messages"
                        title="Messages"
                        @click="openMessages"
                    >

                        <svg
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >

                            <path
                                d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H8l-4 2v-5.1A7.5 7.5 0 0 1 4.5 4.5 7.5 7.5 0 0 1 20 11.5Z"
                                stroke-linejoin="round"
                            />

                            <path
                                d="M8 11h.01M12 11h.01M16 11h.01"
                                stroke-linecap="round"
                                stroke-width="2.5"
                            />

                        </svg>

                    </button>


                    <!-- ================================================= -->
                    <!-- CART -->
                    <!-- ================================================= -->

                    <button
                        type="button"
                        class="relative flex h-10 w-10 items-center justify-center rounded-xl text-[#1F2937] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
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


                    <!-- ================================================= -->
                    <!-- NOTIFICATIONS -->
                    <!-- ================================================= -->

                    <div
                        class="relative"
                        data-notification-dropdown
                    >

                        <button
                            type="button"
                            class="relative flex h-10 w-10 items-center justify-center rounded-xl text-[#1F2937] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                            aria-label="Notifications"
                            title="Notifications"
                            :aria-expanded="notificationsOpen"
                            @click.stop="toggleNotifications"
                        >

                            <svg
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >

                                <path
                                    d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />

                                <path
                                    d="M10 21h4"
                                    stroke-linecap="round"
                                />

                            </svg>


                            <span
                                v-if="notificationCount > 0"
                                class="absolute -right-0.5 -top-0.5 flex min-h-[17px] min-w-[17px] items-center justify-center rounded-full bg-[#E85D5D] px-1 text-[9px] font-black leading-none text-white"
                            >
                                {{
                                    notificationCount > 99
                                        ? '99+'
                                        : notificationCount
                                }}
                            </span>

                        </button>


                        <Transition
                            enter-active-class="transition duration-150 ease-out"
                            enter-from-class="translate-y-1 opacity-0"
                            enter-to-class="translate-y-0 opacity-100"
                            leave-active-class="transition duration-100 ease-in"
                            leave-from-class="translate-y-0 opacity-100"
                            leave-to-class="translate-y-1 opacity-0"
                        >

                            <div
                                v-if="notificationsOpen"
                                class="absolute right-0 top-[calc(100%+10px)] z-50 w-[360px] overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-2xl shadow-black/10"
                                @click.stop
                            >

                                <div
                                    class="flex items-center justify-between border-b border-[#E5E7EB] px-4 py-3"
                                >

                                    <div>

                                        <p
                                            class="text-sm font-extrabold text-[#1F2937]"
                                        >
                                            Notifications
                                        </p>

                                        <p
                                            class="text-[11px] text-[#64748B]"
                                        >
                                            Your latest updates
                                        </p>

                                    </div>


                                    <button
                                        v-if="notificationCount > 0"
                                        type="button"
                                        class="text-[11px] font-bold text-[#087F8C] transition hover:text-[#065F6B]"
                                        @click="markAllNotificationsRead"
                                    >
                                        Mark all as read
                                    </button>

                                </div>


                                <div
                                    class="max-h-[360px] overflow-y-auto"
                                >

                                    <button
                                        v-for="notification in notificationItems"
                                        :key="notification.id"
                                        type="button"
                                        class="flex w-full gap-3 border-b border-[#F1F5F9] px-4 py-3 text-left transition hover:bg-[#F8FAF9]"
                                        @click="
                                            notification.url
                                                ? router.visit(notification.url)
                                                : seeAllNotifications()
                                        "
                                    >

                                        <span
                                            class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full"
                                            :class="
                                                notification.read
                                                    ? 'bg-transparent'
                                                    : 'bg-[#E85D5D]'
                                            "
                                        ></span>


                                        <span
                                            class="min-w-0 flex-1"
                                        >

                                            <span
                                                class="block text-xs font-extrabold text-[#1F2937]"
                                            >
                                                {{ notification.title }}
                                            </span>


                                            <span
                                                v-if="notification.message"
                                                class="mt-0.5 block line-clamp-2 text-[11px] leading-4 text-[#64748B]"
                                            >
                                                {{ notification.message }}
                                            </span>


                                            <span
                                                v-if="notification.createdAt"
                                                class="mt-1 block text-[9px] text-[#94A3B8]"
                                            >
                                                {{ notification.createdAt }}
                                            </span>

                                        </span>

                                    </button>


                                    <div
                                        v-if="notificationItems.length === 0"
                                        class="px-4 py-8 text-center"
                                    >

                                        <div
                                            class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-[#E8F7F6] text-[#087F8C]"
                                        >

                                            <svg
                                                class="h-5 w-5"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >

                                                <path
                                                    d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                />

                                                <path
                                                    d="M10 21h4"
                                                    stroke-linecap="round"
                                                />

                                            </svg>

                                        </div>


                                        <p
                                            class="mt-2 text-xs font-bold text-[#1F2937]"
                                        >
                                            No notifications
                                        </p>


                                        <p
                                            class="mt-1 text-[10px] text-[#64748B]"
                                        >
                                            You're all caught up.
                                        </p>

                                    </div>

                                </div>


                                <button
                                    type="button"
                                    class="flex w-full items-center justify-center border-t border-[#E5E7EB] px-4 py-3 text-xs font-extrabold text-[#087F8C] transition hover:bg-[#F8FAF9]"
                                    @click="seeAllNotifications"
                                >
                                    See all notifications
                                </button>

                            </div>

                        </Transition>

                    </div>


                    <!-- ================================================= -->
                    <!-- ACCOUNT -->
                    <!-- ================================================= -->

                    <div
                        class="relative ml-1"
                        data-account-dropdown
                    >

                        <button
                            type="button"
                            class="flex h-10 items-center gap-2 rounded-xl border border-transparent px-2.5 text-left transition hover:border-[#E5E7EB] hover:bg-[#E8F7F6]"
                            :class="{
                                'border-[#E5E7EB] bg-[#E8F7F6]':
                                    accountOpen
                            }"
                            aria-label="Account"
                            :aria-expanded="accountOpen"
                            @click.stop="toggleAccount"
                        >

                            <!-- DESKTOP PROFILE PHOTO -->

                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#E8F7F6] text-xs font-black text-[#087F8C]"
                            >

                                <img
                                    v-if="userProfilePhoto"
                                    :src="userProfilePhoto"
                                    :alt="`${userName} profile photo`"
                                    class="h-full w-full object-cover"
                                />

                                <span v-else>
                                    {{
                                        userName
                                            .charAt(0)
                                            .toUpperCase()
                                    }}
                                </span>

                            </span>


                            <span
                                class="hidden max-w-[100px] lg:block"
                            >

                                <span
                                    class="block truncate text-xs font-extrabold text-[#1F2937]"
                                >
                                    {{ userName }}
                                </span>


                                <span
                                    class="block text-[9px] text-[#64748B]"
                                >
                                    My account
                                </span>

                            </span>


                            <svg
                                class="hidden h-3.5 w-3.5 text-[#64748B] transition-transform duration-200 lg:block"
                                :class="{
                                    'rotate-180': accountOpen
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


                        <!-- ================================================= -->
                        <!-- ACCOUNT DROPDOWN -->
                        <!-- ================================================= -->

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

                                <!-- ACCOUNT INFO -->

                                <div
                                    class="border-b border-[#E5E7EB] bg-[#F8FAF9] px-4 py-4"
                                >

                                    <div
                                        class="flex items-center gap-3"
                                    >

                                        <!-- DROPDOWN PROFILE PHOTO -->

                                        <span
                                            class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#E8F7F6] text-sm font-black text-[#087F8C]"
                                        >

                                            <img
                                                v-if="userProfilePhoto"
                                                :src="userProfilePhoto"
                                                :alt="`${userName} profile photo`"
                                                class="h-full w-full object-cover"
                                            />

                                            <span v-else>
                                                {{
                                                    userName
                                                        .charAt(0)
                                                        .toUpperCase()
                                                }}
                                            </span>

                                        </span>


                                        <div
                                            class="min-w-0"
                                        >

                                            <p
                                                class="truncate text-sm font-extrabold text-[#1F2937]"
                                            >
                                                {{ userName }}
                                            </p>


                                            <p
                                                class="truncate text-xs text-[#64748B]"
                                            >
                                                {{ userEmail }}
                                            </p>

                                        </div>

                                    </div>

                                </div>


                                <!-- ACCOUNT LINKS -->

                                <div
                                    class="p-2"
                                >

                                    <!-- DASHBOARD -->

                                    <button
                                        type="button"
                                        class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-bold text-[#1F2937] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                                        @click="goToDashboard"
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

                                                <rect
                                                    x="4"
                                                    y="4"
                                                    width="6"
                                                    height="6"
                                                    rx="1"
                                                />

                                                <rect
                                                    x="14"
                                                    y="4"
                                                    width="6"
                                                    height="6"
                                                    rx="1"
                                                />

                                                <rect
                                                    x="4"
                                                    y="14"
                                                    width="6"
                                                    height="6"
                                                    rx="1"
                                                />

                                                <rect
                                                    x="14"
                                                    y="14"
                                                    width="6"
                                                    height="6"
                                                    rx="1"
                                                />

                                            </svg>

                                        </span>


                                        Dashboard

                                    </button>


                                    <!-- PROFILE -->

                                    <button
                                        type="button"
                                        class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-bold text-[#1F2937] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                                        @click="openProfile"
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


                                        My Profile

                                    </button>


                                    <!-- ORDERS -->

                                    <button
                                        type="button"
                                        class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-bold text-[#1F2937] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                                        @click="openOrders"
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
                                                    d="M6 3h12v18l-6-3-6 3V3Z"
                                                    stroke-linejoin="round"
                                                />

                                            </svg>

                                        </span>


                                        My Orders

                                    </button>

                                </div>


                                <!-- LOGOUT -->

                                <div
                                    class="border-t border-[#E5E7EB] p-2"
                                >

                                    <button
                                        type="button"
                                        class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-bold text-[#E85D5D] transition hover:bg-[#FFF1F1]"
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


                                        Log out

                                    </button>

                                </div>

                            </div>

                        </Transition>

                    </div>

                </div>


                <!-- ================================================= -->
                <!-- MOBILE ACTIONS -->
                <!-- ================================================= -->

                <div
                    class="ml-auto flex items-center gap-1 md:hidden"
                >

                    <!-- MOBILE MESSAGES -->

                    <button
                        type="button"
                        class="relative flex h-10 w-10 items-center justify-center rounded-xl text-[#1F2937] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                        aria-label="Messages"
                        @click="openMessages"
                    >

                        <svg
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >

                            <path
                                d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H8l-4 2v-5.1A7.5 7.5 0 0 1 4.5 4.5 7.5 7.5 0 0 1 20 11.5Z"
                                stroke-linejoin="round"
                            />

                            <path
                                d="M8 11h.01M12 11h.01M16 11h.01"
                                stroke-linecap="round"
                                stroke-width="2.5"
                            />

                        </svg>

                    </button>


                    <!-- MOBILE CART -->

                    <button
                        type="button"
                        class="relative flex h-10 w-10 items-center justify-center rounded-xl text-[#1F2937] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
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


                        <span
                            v-if="cartCount > 0"
                            class="absolute -right-0.5 -top-0.5 flex min-h-[17px] min-w-[17px] items-center justify-center rounded-full bg-[#E85D5D] px-1 text-[9px] font-black leading-none text-white"
                        >
                            {{ cartCount > 99 ? '99+' : cartCount }}
                        </span>

                    </button>


                    <!-- MOBILE NOTIFICATIONS -->

                    <div
                        class="relative"
                        data-mobile-notification-dropdown
                    >

                        <button
                            type="button"
                            class="relative flex h-10 w-10 items-center justify-center rounded-xl text-[#1F2937] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                            aria-label="Notifications"
                            title="Notifications"
                            :aria-expanded="mobileNotificationsOpen"
                            @click.stop="toggleMobileNotifications"
                        >

                            <svg
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >

                                <path
                                    d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />

                                <path
                                    d="M10 21h4"
                                    stroke-linecap="round"
                                />

                            </svg>


                            <span
                                v-if="notificationCount > 0"
                                class="absolute -right-0.5 -top-0.5 flex min-h-[17px] min-w-[17px] items-center justify-center rounded-full bg-[#E85D5D] px-1 text-[9px] font-black leading-none text-white"
                            >
                                {{
                                    notificationCount > 99
                                        ? '99+'
                                        : notificationCount
                                }}
                            </span>

                        </button>


                        <Transition
                            enter-active-class="transition duration-150 ease-out"
                            enter-from-class="translate-y-1 opacity-0"
                            enter-to-class="translate-y-0 opacity-100"
                            leave-active-class="transition duration-100 ease-in"
                            leave-from-class="translate-y-0 opacity-100"
                            leave-to-class="translate-y-1 opacity-0"
                        >

                            <div
                                v-if="mobileNotificationsOpen"
                                class="absolute right-0 top-[calc(100%+10px)] z-50 w-[min(360px,calc(100vw-24px))] overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-2xl shadow-black/10"
                                @click.stop
                            >

                                <div
                                    class="flex items-center justify-between border-b border-[#E5E7EB] px-4 py-3"
                                >

                                    <div>

                                        <p
                                            class="text-sm font-extrabold text-[#1F2937]"
                                        >
                                            Notifications
                                        </p>

                                        <p
                                            class="text-[11px] text-[#64748B]"
                                        >
                                            Your latest updates
                                        </p>

                                    </div>


                                    <button
                                        v-if="notificationCount > 0"
                                        type="button"
                                        class="text-[11px] font-bold text-[#087F8C] transition hover:text-[#065F6B]"
                                        @click="markAllNotificationsRead"
                                    >
                                        Mark all as read
                                    </button>

                                </div>


                                <div
                                    class="max-h-[360px] overflow-y-auto"
                                >

                                    <button
                                        v-for="notification in notificationItems"
                                        :key="notification.id"
                                        type="button"
                                        class="flex w-full gap-3 border-b border-[#F1F5F9] px-4 py-3 text-left transition hover:bg-[#F8FAF9]"
                                        @click="
                                            notification.url
                                                ? router.visit(notification.url)
                                                : seeAllNotifications()
                                        "
                                    >

                                        <span
                                            class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full"
                                            :class="
                                                notification.read
                                                    ? 'bg-transparent'
                                                    : 'bg-[#E85D5D]'
                                            "
                                        ></span>


                                        <span
                                            class="min-w-0 flex-1"
                                        >

                                            <span
                                                class="block text-xs font-extrabold text-[#1F2937]"
                                            >
                                                {{ notification.title }}
                                            </span>


                                            <span
                                                v-if="notification.message"
                                                class="mt-0.5 block line-clamp-2 text-[11px] leading-4 text-[#64748B]"
                                            >
                                                {{ notification.message }}
                                            </span>


                                            <span
                                                v-if="notification.createdAt"
                                                class="mt-1 block text-[9px] text-[#94A3B8]"
                                            >
                                                {{ notification.createdAt }}
                                            </span>

                                        </span>

                                    </button>


                                    <div
                                        v-if="notificationItems.length === 0"
                                        class="px-4 py-8 text-center"
                                    >

                                        <p
                                            class="text-xs font-bold text-[#1F2937]"
                                        >
                                            No notifications
                                        </p>

                                        <p
                                            class="mt-1 text-[10px] text-[#64748B]"
                                        >
                                            You're all caught up.
                                        </p>

                                    </div>

                                </div>


                                <button
                                    type="button"
                                    class="flex w-full items-center justify-center border-t border-[#E5E7EB] px-4 py-3 text-xs font-extrabold text-[#087F8C] transition hover:bg-[#F8FAF9]"
                                    @click="seeAllNotifications"
                                >
                                    See all notifications
                                </button>

                            </div>

                        </Transition>

                    </div>


                    <!-- ================================================= -->
                    <!-- MOBILE MENU -->
                    <!-- ================================================= -->

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


                        <!-- ================================================= -->
                        <!-- MOBILE CATEGORIES -->
                        <!-- ================================================= -->

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
                                @click="
                                    mobileCategoriesOpen =
                                        !mobileCategoriesOpen
                                "
                            >

                                <span
                                    class="flex items-center gap-3"
                                >

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
                                        :key="
                                            category.id ??
                                            category.slug ??
                                            category.name
                                        "
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
                                                    {{
                                                        category.products_count ??
                                                        0
                                                    }}
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


                        <!-- ================================================= -->
                        <!-- MOBILE ACCOUNT -->
                        <!-- ================================================= -->

                        <div
                            class="mt-3 overflow-hidden rounded-2xl border border-[#E5E7EB]"
                        >

                            <button
                                type="button"
                                class="flex min-h-[60px] w-full items-center justify-between px-4 text-left transition hover:bg-[#E8F7F6]"
                                :class="{
                                    'bg-[#E8F7F6]':
                                        mobileAccountOpen
                                }"
                                @click="toggleMobileAccount"
                            >

                                <span
                                    class="flex min-w-0 items-center gap-3"
                                >

                                    <!-- MOBILE PROFILE PHOTO -->

                                    <span
                                        class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#E8F7F6] text-sm font-black text-[#087F8C]"
                                    >

                                        <img
                                            v-if="userProfilePhoto"
                                            :src="userProfilePhoto"
                                            :alt="`${userName} profile photo`"
                                            class="h-full w-full object-cover"
                                        />

                                        <span v-else>
                                            {{
                                                userName
                                                    .charAt(0)
                                                    .toUpperCase()
                                            }}
                                        </span>

                                    </span>


                                    <span
                                        class="min-w-0"
                                    >

                                        <span
                                            class="block truncate text-sm font-extrabold text-[#1F2937]"
                                        >
                                            {{ userName }}
                                        </span>


                                        <span
                                            class="block truncate text-xs text-[#64748B]"
                                        >
                                            My Account
                                        </span>

                                    </span>

                                </span>


                                <svg
                                    class="h-4 w-4 shrink-0 text-[#64748B] transition-transform"
                                    :class="{
                                        'rotate-180':
                                            mobileAccountOpen
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


                            <!-- MOBILE ACCOUNT LINKS -->

                            <div
                                v-if="mobileAccountOpen"
                                class="border-t border-[#E5E7EB] bg-[#F8FAF9] p-2"
                            >

                                <!-- DASHBOARD -->

                                <button
                                    type="button"
                                    class="flex min-h-[48px] w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm font-bold text-[#1F2937] transition hover:bg-white hover:text-[#087F8C]"
                                    @click="goToDashboard"
                                >

                                    <span
                                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-[#087F8C] shadow-sm"
                                    >

                                        <svg
                                            class="h-4 w-4"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >

                                            <rect
                                                x="4"
                                                y="4"
                                                width="6"
                                                height="6"
                                                rx="1"
                                            />

                                            <rect
                                                x="14"
                                                y="4"
                                                width="6"
                                                height="6"
                                                rx="1"
                                            />

                                            <rect
                                                x="4"
                                                y="14"
                                                width="6"
                                                height="6"
                                                rx="1"
                                            />

                                            <rect
                                                x="14"
                                                y="14"
                                                width="6"
                                                height="6"
                                                rx="1"
                                            />

                                        </svg>

                                    </span>


                                    Dashboard

                                </button>


                                <!-- PROFILE -->

                                <button
                                    type="button"
                                    class="flex min-h-[48px] w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm font-bold text-[#1F2937] transition hover:bg-white hover:text-[#087F8C]"
                                    @click="openProfile"
                                >

                                    <span
                                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-[#64748B] shadow-sm"
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


                                    My Profile

                                </button>


                                <!-- ORDERS -->

                                <button
                                    type="button"
                                    class="flex min-h-[48px] w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm font-bold text-[#1F2937] transition hover:bg-white hover:text-[#087F8C]"
                                    @click="openOrders"
                                >

                                    <span
                                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-[#64748B] shadow-sm"
                                    >

                                        <svg
                                            class="h-4 w-4"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >

                                            <path
                                                d="M6 3h12v18l-6-3-6 3V3Z"
                                                stroke-linejoin="round"
                                            />

                                        </svg>

                                    </span>


                                    My Orders

                                </button>


                                <!-- LOGOUT -->

                                <button
                                    type="button"
                                    class="mt-1 flex min-h-[48px] w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm font-bold text-[#E85D5D] transition hover:bg-[#FFF1F1]"
                                    @click="logout"
                                >

                                    <span
                                        class="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-[#E85D5D] shadow-sm"
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


                                    Log out

                                </button>

                            </div>

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

                <!-- BRAND -->

                <div
                    class="md:col-span-2 lg:col-span-1"
                >

                    <Link
                        :href="safeRoute('buyer.dashboard', '/')"
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


                    <div
                        class="mt-4 space-y-3"
                    >

                        <Link
                            :href="safeRoute('buyer.products')"
                            class="block text-sm text-[#64748B] transition hover:text-[#087F8C]"
                        >
                            All products
                        </Link>


                        <Link
                            :href="safeRoute('buyer.categories')"
                            class="block text-sm text-[#64748B] transition hover:text-[#087F8C]"
                        >
                            Categories
                        </Link>


                        <Link
                            :href="safeRoute('buyer.cart')"
                            class="block text-sm text-[#64748B] transition hover:text-[#087F8C]"
                        >
                            Cart
                        </Link>

                    </div>

                </div>


                <!-- HELP -->

                <div>

                    <h3
                        class="text-xs font-black uppercase tracking-[0.16em] text-[#1F2937]"
                    >
                        Help
                    </h3>


                    <div
                        class="mt-4 space-y-3"
                    >

                        <Link
                            :href="safeRoute('buyer.conversations', '#')"
                            class="block text-sm text-[#64748B] transition hover:text-[#087F8C]"
                        >
                            Messages
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


                <!-- ACCOUNT -->

                <div>

                    <h3
                        class="text-xs font-black uppercase tracking-[0.16em] text-[#1F2937]"
                    >
                        My Account
                    </h3>


                    <p
                        class="mt-4 text-sm leading-6 text-[#64748B]"
                    >
                        Manage your orders, wishlist, profile, and shopping
                        activity from your Alona account.
                    </p>


                    <button
                        type="button"
                        class="mt-4 inline-flex rounded-xl bg-[#E8F7F6] px-4 py-2.5 text-xs font-extrabold text-[#087F8C] transition hover:bg-[#087F8C] hover:text-white"
                        @click="goToDashboard"
                    >
                        My dashboard
                    </button>

                </div>

            </div>


            <!-- ========================================================= -->
            <!-- FOOTER BOTTOM -->
            <!-- ========================================================= -->

            <div
                class="border-t border-[#E5E7EB]"
            >

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

    </div>

</template>