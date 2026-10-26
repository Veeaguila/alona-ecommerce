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
import FloatingChatWidget from '@/Components/FloatingChatWidget.vue'
import AppFooter from '@/Components/AppFooter.vue'

const page = usePage()

const isConversationsPage = computed(() => {
    return page.component === 'Buyer/Conversations'
})

/*
|--------------------------------------------------------------------------
| STATE
|--------------------------------------------------------------------------
*/

const mobileOpen = ref(false)
const searchQuery = ref('')

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

    if (!path || typeof path !== 'string') {
        return null
    }

    if (
        path.startsWith('http://') ||
        path.startsWith('https://') ||
        path.startsWith('/storage/') ||
        path.startsWith('/images/')
    ) {
        return path
    }

    return `/storage/${path.replace(/^\/+/, '')}`
})


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


/*
|--------------------------------------------------------------------------
| NOTIFICATIONS
|--------------------------------------------------------------------------
*/

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
            id:
                item?.id ??
                item?.uuid ??
                `notification-${index}`,

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
                Boolean(
                    item?.read_at ??
                    item?.read
                ),

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
    } catch (error) {
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

    const destination = safeRoute(
        'buyer.dashboard'
    )

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

    const destination = safeRoute(
        'buyer.products'
    )

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
| MESSAGES
|--------------------------------------------------------------------------
*/

const openMessages = () => {
    closeMenus()

    if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('open-buyer-chat'))
    }
}


/*
|--------------------------------------------------------------------------
| CART
|--------------------------------------------------------------------------
*/

const openCart = () => {
    closeMenus()

    const destination = safeRoute(
        'buyer.cart'
    )

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

    notificationsOpen.value = false
}

const toggleMobileAccount = () => {
    mobileAccountOpen.value =
        !mobileAccountOpen.value

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

    const destination = safeRoute(
        'buyer.orders'
    )

    if (destination !== '#') {
        router.visit(destination)
    }
}

const openVouchers = () => {
    closeMenus()

    const destination = safeRoute(
        'buyer.vouchers'
    )

    if (destination !== '#') {
        router.visit(destination)
    }
}

const openWishlist = () => {
    closeMenus()

    const destination = safeRoute(
        'buyer.wishlist'
    )

    if (destination !== '#') {
        router.visit(destination)
    }
}

const openReviews = () => {
    closeMenus()

    const destination = safeRoute(
        'buyer.reviews'
    )

    if (destination !== '#') {
        router.visit(destination)
    }
}

const openHelpCenter = () => {
    closeMenus()

    const destination = safeRoute('buyer.help')
    if (destination !== '#') {
        router.visit(destination)
    }
}

const openComplaints = () => {
    closeMenus()

    const destination = safeRoute('buyer.complaints')
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
    notificationsOpen.value =
        !notificationsOpen.value

    accountOpen.value = false
    mobileOpen.value = false
    mobileNotificationsOpen.value = false
    mobileAccountOpen.value = false
}

const toggleMobileNotifications = () => {
    mobileNotificationsOpen.value =
        !mobileNotificationsOpen.value

    accountOpen.value = false
    mobileOpen.value = false
    notificationsOpen.value = false
    mobileAccountOpen.value = false
}

const markAllNotificationsRead = () => {
    notificationsMarkedRead.value = true

    const markAllReadRoute =
        safeRoute(
            'buyer.notifications.markAllRead'
        )

    const readAllRoute =
        safeRoute(
            'buyer.notifications.readAll'
        )

    const markAllReadAlternative =
        safeRoute(
            'buyer.notifications.mark-all-read'
        )

    const destination =
        markAllReadRoute !== '#'
            ? markAllReadRoute
            : readAllRoute !== '#'
                ? readAllRoute
                : markAllReadAlternative

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

    const destination = safeRoute(
        'buyer.notifications'
    )

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

    const destination = safeRoute(
        'logout'
    )

    if (destination === '#') {
        return
    }

    router.post(
        destination,
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
    mobileOpen.value =
        !mobileOpen.value

    if (!mobileOpen.value) {
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
    accountOpen.value = false

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

    if (
        !target.closest(
            '[data-account-dropdown]'
        )
    ) {
        accountOpen.value = false
    }

    if (
        !target.closest(
            '[data-notification-dropdown]'
        )
    ) {
        notificationsOpen.value = false
    }

    if (
        !target.closest(
            '[data-mobile-notification-dropdown]'
        )
    ) {
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

    if (typeof window !== 'undefined') {
        const params = new URLSearchParams(window.location.search)
        const chatId = params.get('chat') || params.get('conversation')
        if (chatId) {
            params.delete('chat')
            params.delete('conversation')
            const newSearch = params.toString() ? `?${params.toString()}` : ''
            window.history.replaceState({}, '', `${window.location.pathname}${newSearch}`)

            setTimeout(() => {
                window.dispatchEvent(
                    new CustomEvent('open-buyer-chat', {
                        detail: {
                            conversationId: chatId === '1' ? null : chatId,
                        },
                    })
                )
            }, 200)
        }
    }
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
                class="mx-auto flex h-[64px] max-w-[1536px] w-full items-center gap-2 px-3 sm:h-[72px] sm:gap-3 sm:px-6 lg:h-[78px] lg:gap-5 lg:px-8 2xl:px-10"
            >

                <!-- ================================================= -->
                <!-- LOGO -->
                <!-- ================================================= -->

                <Link
                    :href="
                        safeRoute(
                            'buyer.dashboard',
                            '/'
                        )
                    "
                    class="flex shrink-0 items-center transition hover:opacity-90"
                    aria-label="Alona Home"
                >

                    <img
                        src="/images/alona.png"
                        alt="Alona"
                        class="h-8 w-auto object-contain sm:h-9 lg:h-10"
                    />

                </Link>


                <!-- ================================================= -->
                <!-- DESKTOP SEARCH -->
                <!-- ================================================= -->

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


                <!-- ================================================= -->
                <!-- DESKTOP ACTIONS -->
                <!-- ================================================= -->

                <div
                    class="ml-auto hidden shrink-0 items-center gap-1 sm:gap-1.5 md:flex"
                >
                    <template v-if="user">


                    <!-- ================================================= -->
                    <!-- WISHLIST -->
                    <!-- ================================================= -->

                    <button
                        type="button"
                        class="relative flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center rounded-xl text-[#1F2937] transition hover:bg-[#E8F7F6] hover:text-rose-500"
                        aria-label="Wishlist"
                        title="Wishlist"
                        @click="openWishlist"
                    >
                        <svg
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </button>

                    <!-- ================================================= -->
                    <!-- CART -->
                    <!-- ================================================= -->

                    <button
                        type="button"
                        class="relative flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center rounded-xl text-[#1F2937] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
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
                            {{
                                cartCount > 99
                                    ? '99+'
                                    : cartCount
                            }}
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
                            class="relative flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center rounded-xl text-[#1F2937] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                            aria-label="Notifications"
                            title="Notifications"
                            :aria-expanded="
                                notificationsOpen
                            "
                            @click.stop="
                                toggleNotifications
                            "
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
                                v-if="
                                    notificationCount > 0
                                "
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
                                class="absolute right-0 top-[calc(100%+8px)] z-50 w-[min(380px,calc(100vw-24px))] max-h-[min(540px,calc(100vh-90px))] overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-2xl shadow-black/10"
                                @click.stop
                            >

                                <!-- NOTIFICATION HEADER -->

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
                                        v-if="
                                            notificationCount > 0
                                        "
                                        type="button"
                                        class="text-[11px] font-bold text-[#087F8C] transition hover:text-[#065F6B]"
                                        @click="
                                            markAllNotificationsRead
                                        "
                                    >
                                        Mark all as read
                                    </button>

                                </div>


                                <!-- NOTIFICATION LIST -->

                                <div
                                    class="max-h-[360px] overflow-y-auto"
                                >

                                    <button
                                        v-for="
                                            notification in notificationItems
                                        "
                                        :key="
                                            notification.id
                                        "
                                        type="button"
                                        class="flex w-full gap-3 border-b border-[#F1F5F9] px-4 py-3 text-left transition hover:bg-[#F8FAF9]"
                                        @click="
                                            notification.url
                                                ? router.visit(
                                                    notification.url
                                                )
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
                                                {{
                                                    notification.title
                                                }}
                                            </span>


                                            <span
                                                v-if="
                                                    notification.message
                                                "
                                                class="mt-0.5 block line-clamp-2 text-[11px] leading-4 text-[#64748B]"
                                            >
                                                {{
                                                    notification.message
                                                }}
                                            </span>


                                            <span
                                                v-if="
                                                    notification.createdAt
                                                "
                                                class="mt-1 block text-[9px] text-[#94A3B8]"
                                            >
                                                {{
                                                    notification.createdAt
                                                }}
                                            </span>

                                        </span>

                                    </button>


                                    <!-- EMPTY STATE -->

                                    <div
                                        v-if="
                                            notificationItems.length === 0
                                        "
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


                                <!-- SEE ALL -->

                                <button
                                    type="button"
                                    class="flex w-full items-center justify-center border-t border-[#E5E7EB] px-4 py-3 text-xs font-extrabold text-[#087F8C] transition hover:bg-[#F8FAF9]"
                                    @click="
                                        seeAllNotifications
                                    "
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
                            class="flex h-9 sm:h-10 items-center gap-2 rounded-xl border border-transparent px-2 sm:px-2.5 text-left transition hover:border-[#E5E7EB] hover:bg-[#E8F7F6]"
                            :class="{
                                'border-[#E5E7EB] bg-[#E8F7F6]':
                                    accountOpen
                            }"
                            aria-label="Account"
                            :aria-expanded="accountOpen"
                            @click.stop="
                                toggleAccount
                            "
                        >

                            <!-- PROFILE PHOTO -->

                            <span
                                class="flex h-7 w-7 sm:h-8 sm:w-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#E8F7F6] text-xs font-black text-[#087F8C]"
                            >

                                <img
                                    v-if="
                                        userProfilePhoto
                                    "
                                    :src="
                                        userProfilePhoto
                                    "
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
                                class="hidden max-w-[100px] xl:block"
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
                                class="hidden h-3.5 w-3.5 text-[#64748B] transition-transform duration-200 xl:block"
                                :class="{
                                    'rotate-180':
                                        accountOpen
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


                        <!-- ACCOUNT DROPDOWN -->

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
                                class="absolute right-0 top-[calc(100%+8px)] z-50 w-[min(280px,calc(100vw-24px))] max-h-[min(540px,calc(100vh-90px))] overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-2xl shadow-black/10"
                                @click.stop
                            >

                                <!-- ACCOUNT INFO -->

                                <div
                                    class="border-b border-[#E5E7EB] bg-[#F8FAF9] px-4 py-4"
                                >

                                    <div
                                        class="flex items-center gap-3"
                                    >

                                        <span
                                            class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#E8F7F6] text-sm font-black text-[#087F8C]"
                                        >

                                            <img
                                                v-if="
                                                    userProfilePhoto
                                                "
                                                :src="
                                                    userProfilePhoto
                                                "
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

                                    <!-- ACCOUNT MANAGEMENT -->

                                    <button
                                        type="button"
                                        class="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-bold text-[#1F2937] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                                        @click="openProfile"
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

                                        Account Management

                                    </button>


                                    <!-- MY ORDERS -->

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
                    </template>

                    <template v-else>
                        <!-- GUEST CART -->
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
                                <circle cx="9" cy="20" r="1" />
                                <circle cx="18" cy="20" r="1" />
                            </svg>
                        </button>

                        <Link
                            :href="safeRoute('login')"
                            class="inline-flex h-10 items-center rounded-xl px-3.5 text-sm font-extrabold text-[#087F8C] transition duration-200 hover:bg-[#E8F7F6]"
                        >
                            Log in
                        </Link>

                        <Link
                            :href="safeRoute('register')"
                            class="inline-flex h-10 items-center rounded-xl bg-[#087F8C] px-4 text-sm font-extrabold text-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:bg-[#066B76] hover:shadow-md"
                        >
                            Sign Up
                        </Link>
                    </template>

                </div>


                <!-- ================================================= -->
                <!-- MOBILE ACTIONS -->
                <!-- ================================================= -->

                <div
                    class="ml-auto flex items-center gap-0.5 xs:gap-1 sm:gap-1.5 md:hidden"
                >

                    <!-- MOBILE WISHLIST -->

                    <button
                        v-if="user"
                        type="button"
                        class="relative flex h-9 w-9 xs:h-10 xs:w-10 items-center justify-center rounded-xl text-[#1F2937] transition hover:bg-[#E8F7F6] hover:text-rose-500"
                        aria-label="Wishlist"
                        title="Wishlist"
                        @click="openWishlist"
                    >
                        <svg
                            class="h-4 w-4 xs:h-5 xs:w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </button>

                    <!-- MOBILE CART -->

                    <button
                        type="button"
                        class="relative flex h-9 w-9 xs:h-10 xs:w-10 items-center justify-center rounded-xl text-[#1F2937] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                        aria-label="Cart"
                        title="Cart"
                        @click="openCart"
                    >

                        <svg
                            class="h-4 w-4 xs:h-5 xs:w-5"
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
                            {{
                                cartCount > 99
                                    ? '99+'
                                    : cartCount
                            }}
                        </span>

                    </button>


                    <!-- MOBILE NOTIFICATIONS -->

                    <div
                        class="relative"
                        data-mobile-notification-dropdown
                    >

                        <button
                            type="button"
                            class="relative flex h-9 w-9 xs:h-10 xs:w-10 items-center justify-center rounded-xl text-[#1F2937] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                            aria-label="Notifications"
                            title="Notifications"
                            :aria-expanded="
                                mobileNotificationsOpen
                            "
                            @click.stop="
                                toggleMobileNotifications
                            "
                        >

                            <svg
                                class="h-4 w-4 xs:h-5 xs:w-5"
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
                                v-if="
                                    notificationCount > 0
                                "
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
                                v-if="
                                    mobileNotificationsOpen
                                "
                                class="fixed inset-x-3 top-[68px] sm:absolute sm:inset-auto sm:right-0 sm:top-[calc(100%+8px)] sm:w-[360px] z-50 max-h-[calc(100vh-80px)] overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-2xl shadow-black/10"
                                @click.stop
                            >

                                <!-- HEADER -->

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
                                        v-if="
                                            notificationCount > 0
                                        "
                                        type="button"
                                        class="text-[11px] font-bold text-[#087F8C] transition hover:text-[#065F6B]"
                                        @click="
                                            markAllNotificationsRead
                                        "
                                    >
                                        Mark all as read
                                    </button>

                                </div>


                                <!-- LIST -->

                                <div
                                    class="max-h-[360px] overflow-y-auto"
                                >

                                    <button
                                        v-for="
                                            notification in notificationItems
                                        "
                                        :key="
                                            notification.id
                                        "
                                        type="button"
                                        class="flex w-full gap-3 border-b border-[#F1F5F9] px-4 py-3 text-left transition hover:bg-[#F8FAF9]"
                                        @click="
                                            notification.url
                                                ? router.visit(
                                                    notification.url
                                                )
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
                                                {{
                                                    notification.title
                                                }}
                                            </span>


                                            <span
                                                v-if="
                                                    notification.message
                                                "
                                                class="mt-0.5 block line-clamp-2 text-[11px] leading-4 text-[#64748B]"
                                            >
                                                {{
                                                    notification.message
                                                }}
                                            </span>


                                            <span
                                                v-if="
                                                    notification.createdAt
                                                "
                                                class="mt-1 block text-[9px] text-[#94A3B8]"
                                            >
                                                {{
                                                    notification.createdAt
                                                }}
                                            </span>

                                        </span>

                                    </button>


                                    <!-- EMPTY -->

                                    <div
                                        v-if="
                                            notificationItems.length === 0
                                        "
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


                                <!-- SEE ALL -->

                                <button
                                    type="button"
                                    class="flex w-full items-center justify-center border-t border-[#E5E7EB] px-4 py-3 text-xs font-extrabold text-[#087F8C] transition hover:bg-[#F8FAF9]"
                                    @click="
                                        seeAllNotifications
                                    "
                                >
                                    See all notifications
                                </button>

                            </div>

                        </Transition>

                    </div>


                    <!-- ================================================= -->
                    <!-- MOBILE MENU BUTTON -->
                    <!-- ================================================= -->

                    <button
                        type="button"
                        class="flex h-9 w-9 xs:h-10 xs:w-10 items-center justify-center rounded-xl text-[#1F2937] transition hover:bg-[#E8F7F6] hover:text-[#087F8C] active:scale-95"
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
                    class="border-t border-[#E5E7EB] bg-white shadow-xl md:hidden"
                >

                    <div
                        class="mx-auto max-h-[calc(100vh-68px)] sm:max-h-[calc(100vh-76px)] max-w-[1536px] w-full overflow-y-auto px-4 py-4 sm:px-6 2xl:px-10"
                    >

                        <!-- ================================================= -->
                        <!-- MOBILE SEARCH -->
                        <!-- ================================================= -->

                        <form
                            class="relative"
                            @submit.prevent="
                                searchProducts
                            "
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
                        <!-- MOBILE ACCOUNT -->
                        <!-- ================================================= -->

                        <div
                            v-if="user"
                            class="mt-3 overflow-hidden rounded-2xl border border-[#E5E7EB]"
                        >

                            <button
                                type="button"
                                class="flex min-h-[60px] w-full items-center justify-between px-4 text-left transition hover:bg-[#E8F7F6]"
                                :class="{
                                    'bg-[#E8F7F6]':
                                        mobileAccountOpen
                                }"
                                @click="
                                    toggleMobileAccount
                                "
                            >

                                <span
                                    class="flex min-w-0 items-center gap-3"
                                >

                                    <!-- PROFILE PHOTO -->

                                    <span
                                        class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#E8F7F6] text-sm font-black text-[#087F8C]"
                                    >

                                        <img
                                            v-if="
                                                userProfilePhoto
                                            "
                                            :src="
                                                userProfilePhoto
                                            "
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
                                v-if="
                                    mobileAccountOpen
                                "
                                class="border-t border-[#E5E7EB] bg-[#F8FAF9] p-2"
                            >

                                <!-- ACCOUNT MANAGEMENT -->
                                <button
                                    type="button"
                                    class="flex min-h-[48px] w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm font-bold text-[#1F2937] transition hover:bg-white hover:text-[#087F8C]"
                                    @click="openProfile"
                                >
                                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-[#087F8C] shadow-sm">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <circle cx="12" cy="8" r="3.5" />
                                            <path d="M5 20c.7-3.3 3.2-5 7-5s6.3 1.7 7 5" stroke-linecap="round" />
                                        </svg>
                                    </span>
                                    Account Management
                                </button>

                                <!-- MY ORDERS -->
                                <button
                                    type="button"
                                    class="flex min-h-[48px] w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm font-bold text-[#1F2937] transition hover:bg-white hover:text-[#087F8C]"
                                    @click="openOrders"
                                >
                                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-[#64748B] shadow-sm">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <path d="M6 3h12v18l-6-3-6 3V3Z" stroke-linejoin="round" />
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

                        <div
                            v-else
                            class="mt-3 flex gap-2"
                        >
                            <Link
                                :href="safeRoute('login')"
                                class="flex h-11 flex-1 items-center justify-center rounded-xl border border-[#087F8C] text-sm font-extrabold text-[#087F8C] transition hover:bg-[#E8F7F6]"
                                @click="closeMenus"
                            >
                                Log in
                            </Link>

                            <Link
                                :href="safeRoute('register')"
                                class="flex h-11 flex-1 items-center justify-center rounded-xl bg-[#087F8C] text-sm font-extrabold text-white shadow-sm transition hover:bg-[#066B76]"
                                @click="closeMenus"
                            >
                                Sign Up
                            </Link>
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

        <AppFooter />

        <!-- Floating Chat Widget (Shopee-Style Dock) -->
        <FloatingChatWidget v-if="user" />

    </div>

</template>