<script setup>
import { Head, Link } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
})

/*
|--------------------------------------------------------------------------
| CATEGORY ICONS + COLORS
|--------------------------------------------------------------------------
| Kept in sync with the icon/color map used on the homepage so a category
| looks the same wherever it appears.
*/

const categoryStyles = {
    electronics: {
        icon: 'M4 5h16v10H4zM9 19h6M12 15v4',
        bg: 'bg-blue-50',
        text: 'text-blue-600',
    },
    fashion: {
        icon: 'M8 4 6 6l1 3-2 1v10h14V10l-2-1 1-3-2-2-2 2h-2z',
        bg: 'bg-rose-50',
        text: 'text-rose-500',
    },
    'home-living': {
        icon: 'M3 10a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v3H3v-3ZM4 13v5h2v-3h12v3h2v-5',
        bg: 'bg-emerald-50',
        text: 'text-emerald-600',
    },
    beauty: {
        icon: 'M9 2h6M10 2v3.5L7 9v11a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9l-3-3.5V2',
        bg: 'bg-red-50',
        text: 'text-red-500',
    },
    sports: {
        icon: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18ZM3 12h18M12 3c2.5 2.5 2.5 15.5 0 18M12 3c-2.5 2.5-2.5 15.5 0 18',
        bg: 'bg-orange-50',
        text: 'text-orange-500',
    },
    'toys-games': {
        icon: 'M6 9h12a3 3 0 0 1 3 3l1 4a2 2 0 0 1-3.5 1.6L16 15H8l-2.5 2.6A2 2 0 0 1 2 16l1-4a3 3 0 0 1 3-3ZM8 11v2M7 12h2M15 12h.01M17.5 10.5h.01',
        bg: 'bg-sky-50',
        text: 'text-sky-600',
    },
    groceries: {
        icon: 'M4 8h16l-1.5 9a2 2 0 0 1-2 1.7H7.5a2 2 0 0 1-2-1.7L4 8ZM8 8V6a4 4 0 0 1 8 0v2',
        bg: 'bg-green-50',
        text: 'text-green-600',
    },
    automotive: {
        icon: 'M5 12l1.5-4.5A2 2 0 0 1 8.4 6h7.2a2 2 0 0 1 1.9 1.5L19 12M4 12h16v5H4zM7 17v2M17 17v2M7 14.5h.01M17 14.5h.01',
        bg: 'bg-slate-100',
        text: 'text-slate-600',
    },
    others: {
        icon: 'M4 7h16M4 12h16M4 17h10',
        bg: 'bg-gray-50',
        text: 'text-gray-500',
    },
}

const defaultStyle = categoryStyles.others

const getStyle = slug => categoryStyles[slug] ?? defaultStyle
</script>

<template>
    <Head title="Categories" />

    <GuestLayout>
        <main class="mx-auto max-w-7xl px-6 py-10">

            <!-- HEADER -->
            <div class="text-center">
                <p class="text-xs font-semibold text-indigo-600">
                    Zellora marketplace
                </p>

                <h1
                    class="mt-1.5 text-xl font-bold tracking-tight text-gray-900 sm:text-2xl"
                >
                    Shop by Category
                </h1>

                <p class="mx-auto mt-2 max-w-2xl text-xs text-gray-500">
                    Explore products from trusted sellers by category.
                </p>
            </div>

            <!-- CATEGORIES -->
            <div
                v-if="categories.length"
                class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4"
            >
                <Link
                    v-for="category in categories"
                    :key="category.id"
                    :href="`${route('guest.products')}?category=${category.slug}`"
                    class="group rounded-xl border border-gray-100 bg-white p-5 transition hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md"
                >
                    <!-- ICON -->
                    <span
                        class="flex h-11 w-11 items-center justify-center rounded-lg transition group-hover:scale-105"
                        :class="[getStyle(category.slug).bg, getStyle(category.slug).text]"
                    >
                        <svg
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                :d="getStyle(category.slug).icon"
                            />
                        </svg>
                    </span>

                    <!-- NAME -->
                    <h2
                        class="mt-4 text-sm font-semibold text-gray-900 group-hover:text-indigo-600"
                    >
                        {{ category.name }}
                    </h2>

                    <!-- PRODUCT COUNT -->
                    <p class="mt-1 text-xs text-gray-500">
                        {{ category.products_count ?? 0 }}
                        {{ category.products_count === 1 ? 'product' : 'products' }}
                    </p>

                    <p
                        class="mt-3 text-xs font-semibold text-indigo-600"
                    >
                        Browse category →
                    </p>
                </Link>
            </div>

            <!-- EMPTY STATE -->
            <div
                v-else
                class="mt-8 rounded-2xl border border-dashed border-gray-200 bg-gray-50 px-6 py-14 text-center"
            >
                <div
                    class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-white text-gray-400 shadow-sm"
                >
                    <svg
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M4 7h16M4 12h16M4 17h10"
                        />
                    </svg>
                </div>

                <h2 class="mt-3 text-sm font-semibold text-gray-900">
                    No categories available
                </h2>

                <p class="mt-1 text-xs text-gray-500">
                    Categories will appear here once they are added.
                </p>

                <Link
                    :href="route('guest.products')"
                    class="mt-4 inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-700"
                >
                    Browse Products
                </Link>
            </div>

        </main>
    </GuestLayout>
</template>