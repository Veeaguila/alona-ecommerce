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
*/

const categoryStyles = {
    electronics: {
        icon: 'M4 5h16v10H4zM9 19h6M12 15v4',
        bg: 'bg-[#E8F7F6]',
        text: 'text-[#087F8C]',
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
        icon: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18ZM3 12h18M12 3c2.5 2.5 2.5 15.5 0 18M12 3c-2.5-2.5-2.5 15.5 0 18',
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
        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
            <!-- HEADER -->
            <div class="text-center">
                <p class="text-xs font-semibold text-[#087F8C]">
                    Alona marketplace
                </p>

                <h1 class="mt-1.5 text-xl font-bold tracking-tight text-gray-900 sm:text-2xl">
                    Shop by Category
                </h1>

                <p class="mx-auto mt-2 max-w-2xl text-xs text-gray-500">
                    Explore products and curated subcategories from trusted sellers by category.
                </p>
            </div>

            <!-- CATEGORIES WITH SUBCATEGORIES -->
            <div
                v-if="categories.length"
                class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            >
                <div
                    v-for="category in categories"
                    :key="category.id"
                    class="group flex flex-col justify-between rounded-2xl border border-gray-100 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-[#087F8C]/40 hover:shadow-md"
                >
                    <div>
                        <!-- Header: Icon + Category Link -->
                        <Link
                            :href="`${route('guest.products')}?category=${category.slug}`"
                            class="flex items-start gap-3.5"
                        >
                            <span
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl transition duration-200 group-hover:scale-105"
                                :class="[
                                    getStyle(category.slug).bg,
                                    getStyle(category.slug).text,
                                ]"
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

                            <div class="min-w-0 flex-1">
                                <h2 class="truncate text-sm font-bold text-gray-900 transition group-hover:text-[#087F8C]">
                                    {{ category.name }}
                                </h2>
                                <p class="mt-0.5 text-xs font-semibold text-[#087F8C]">
                                    {{ category.total_products_count ?? category.products_count ?? 0 }}
                                    {{ Number(category.total_products_count ?? category.products_count ?? 0) === 1 ? 'product' : 'products' }}
                                </p>
                            </div>
                        </Link>

                        <!-- Subcategories Chips -->
                        <div
                            v-if="(category.active_subcategories || category.activeSubcategories || []).length"
                            class="mt-4 border-t border-gray-100 pt-3"
                        >
                            <div class="mb-2 flex items-center justify-between text-[10px] font-bold uppercase tracking-wider text-gray-400">
                                <span>Subcategories</span>
                                <span class="rounded-full bg-gray-100 px-1.5 py-0.2 text-[9px] text-gray-600">
                                    {{ (category.active_subcategories || category.activeSubcategories).length }}
                                </span>
                            </div>

                            <div class="flex flex-wrap gap-1.5">
                                <Link
                                    v-for="sub in (category.active_subcategories || category.activeSubcategories).slice(0, 5)"
                                    :key="sub.id"
                                    :href="`${route('guest.products')}?category=${sub.slug}`"
                                    class="inline-flex items-center rounded-lg bg-[#F8FAF9] px-2.5 py-1 text-xs font-medium text-gray-700 transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                                >
                                    <span>{{ sub.name }}</span>
                                    <span v-if="sub.products_count !== undefined" class="ml-1 text-[10px] text-gray-400">
                                        ({{ sub.products_count }})
                                    </span>
                                </Link>

                                <Link
                                    v-if="(category.active_subcategories || category.activeSubcategories).length > 5"
                                    :href="`${route('guest.products')}?category=${category.slug}`"
                                    class="inline-flex items-center rounded-lg bg-teal-50 px-2.5 py-1 text-xs font-bold text-[#087F8C] transition hover:bg-teal-100"
                                >
                                    +{{ (category.active_subcategories || category.activeSubcategories).length - 5 }} more
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Footer link -->
                    <div class="mt-4 border-t border-gray-50 pt-3">
                        <Link
                            :href="`${route('guest.products')}?category=${category.slug}`"
                            class="inline-flex items-center gap-1.5 text-xs font-bold text-[#087F8C] hover:underline"
                        >
                            <span>Browse all {{ category.name }}</span>
                            <span>→</span>
                        </Link>
                    </div>
                </div>
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
                    class="mt-4 inline-flex rounded-lg bg-[#087F8C] px-4 py-2 text-xs font-semibold text-white transition hover:bg-[#066B76]"
                >
                    Browse Products
                </Link>
            </div>
        </main>
    </GuestLayout>
</template>
