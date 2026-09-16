<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'
import { computed, ref, watch } from 'vue'

const props = defineProps({
    products: {
        type: Object,
        required: true,
    },
    summary: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    categories: {
        type: Array,
        default: () => [],
    },
})

const search = ref(props.filters.search ?? '')
const category = ref(props.filters.category ?? 'All Categories')
const status = ref(props.filters.status ?? 'All Status')

const categories = computed(() => [
    'All Categories',
    ...props.categories.map(item => item.name),
])

const statuses = [
    'All Status',
    'Active',
    'Low Stock',
    'Out of Stock',
    'Archived',
]

let debounce = null

watch([search, category, status], () => {
    clearTimeout(debounce)

    debounce = setTimeout(() => {
        router.get(
            route('seller.products'),
            {
                search: search.value || undefined,

                category:
                    category.value === 'All Categories'
                        ? undefined
                        : category.value,

                status:
                    status.value === 'All Status'
                        ? undefined
                        : status.value,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        )
    }, 300)
})

const peso = value => {
    return `₱${Number(value ?? 0).toLocaleString()}`
}

const productImage = product => {
    if (!product?.image_path) {
        return null
    }

    if (
        product.image_path.startsWith('http://') ||
        product.image_path.startsWith('https://') ||
        product.image_path.startsWith('/storage/')
    ) {
        return product.image_path
    }

    return `/storage/${product.image_path.replace(/^\/+/, '')}`
}

const statusOf = product => {
    if (product.status === 'archived') {
        return 'Archived'
    }

    const stock = Number(product.stock ?? 0)

    if (stock === 0) {
        return 'Out of Stock'
    }

    if (stock <= 5) {
        return 'Low Stock'
    }

    return 'Active'
}

const statusClass = product => {
    const currentStatus = statusOf(product)

    if (currentStatus === 'Active') {
        return 'border-[#BBE7D3] bg-[#ECFDF5] text-[#16845A]'
    }

    if (currentStatus === 'Low Stock') {
        return 'border-[#FDE68A] bg-[#FFFBEB] text-[#B45309]'
    }

    if (currentStatus === 'Out of Stock') {
        return 'border-[#FECACA] bg-[#FEF2F2] text-[#DC2626]'
    }

    return 'border-[#E2E8F0] bg-[#F8FAFC] text-[#64748B]'
}

const stockClass = product => {
    const stock = Number(product.stock ?? 0)

    if (stock === 0) {
        return 'text-[#E85D5D]'
    }

    if (stock <= 5) {
        return 'text-[#B77900]'
    }

    return 'text-[#1F2937]'
}

const archive = product => {
    if (!confirm(`Archive "${product.name}"? You can restore it later.`)) {
        return
    }

    router.patch(
        route('seller.products.archive', product.id),
        {},
        {
            preserveScroll: true,
        }
    )
}

const restore = product => {
    if (!confirm(`Restore "${product.name}"?`)) {
        return
    }

    router.patch(
        route('seller.products.restore', product.id),
        {},
        {
            preserveScroll: true,
        }
    )
}

const clearFilters = () => {
    search.value = ''
    category.value = 'All Categories'
    status.value = 'All Status'
}

const totalProducts = computed(() => {
    return Number(
        props.summary.total ??
        props.summary.products ??
        props.summary.total_products ??
        props.products.total ??
        0
    )
})

const activeProducts = computed(() => {
    return Number(
        props.summary.active ??
        props.summary.active_products ??
        0
    )
})

const lowStockProducts = computed(() => {
    return Number(
        props.summary.low_stock ??
        props.summary.lowStock ??
        props.summary.low_stock_products ??
        0
    )
})

const outOfStockProducts = computed(() => {
    return Number(
        props.summary.out_of_stock ??
        props.summary.outOfStock ??
        props.summary.out_of_stock_products ??
        0
    )
})
</script>

<template>
    <Head title="My Products" />

    <SellerLayout>
        <div class="min-h-[calc(100vh-68px)] bg-[#F8FAF9]">
            <div class="mx-auto w-full max-w-7xl px-4 py-5 sm:px-5 lg:px-6">

                <!-- Page Header -->
                <div
                    class="flex flex-col gap-4 border-b border-[#E5E7EB] pb-5 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div>
                        <p
                            class="text-xs font-bold uppercase tracking-[0.14em] text-[#087F8C]"
                        >
                            Seller Center
                        </p>

                        <h1
                            class="mt-1 text-2xl font-bold tracking-tight text-[#1F2937] sm:text-3xl"
                        >
                            My Products
                        </h1>

                        <p class="mt-1 text-sm text-[#64748B]">
                            Manage your products, inventory, pricing, and availability.
                        </p>
                    </div>

                    <Link
                        :href="route('seller.products.create')"
                        class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-xl bg-[#087F8C] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#066D78] focus:outline-none focus:ring-2 focus:ring-[#16A6A0]/30"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 5v14M5 12h14"
                            />
                        </svg>

                        Add Product
                    </Link>
                </div>

                <!-- Summary -->
                <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">

                    <!-- Total -->
                    <div
                        class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm"
                    >
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-semibold text-[#64748B]">
                                Total Products
                            </p>

                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-4 w-4"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                                    />
                                </svg>
                            </div>
                        </div>

                        <p
                            class="mt-2 text-xl font-bold tracking-tight text-[#1F2937] sm:text-2xl"
                        >
                            {{ totalProducts }}
                        </p>

                        <p class="mt-1 text-[11px] text-[#94A3B8]">
                            Products in your catalog
                        </p>
                    </div>

                    <!-- Active -->
                    <div
                        class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm"
                    >
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-semibold text-[#64748B]">
                                Active
                            </p>

                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#ECFDF5] text-[#22A06B]"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-4 w-4"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                            </div>
                        </div>

                        <p
                            class="mt-2 text-xl font-bold tracking-tight text-[#1F2937] sm:text-2xl"
                        >
                            {{ activeProducts }}
                        </p>

                        <p class="mt-1 text-[11px] text-[#94A3B8]">
                            Currently available
                        </p>
                    </div>

                    <!-- Low Stock -->
                    <div
                        class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm"
                    >
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-semibold text-[#64748B]">
                                Low Stock
                            </p>

                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#FFF8E7] text-[#B77900]"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-4 w-4"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 9v4m0 4h.01M10.29 3.86l-7.82 13.5A2 2 0 004.2 20.36h15.6a2 2 0 001.73-3l-7.82-13.5a2 2 0 00-3.42 0z"
                                    />
                                </svg>
                            </div>
                        </div>

                        <p
                            class="mt-2 text-xl font-bold tracking-tight text-[#1F2937] sm:text-2xl"
                        >
                            {{ lowStockProducts }}
                        </p>

                        <p class="mt-1 text-[11px] text-[#94A3B8]">
                            Needs replenishment
                        </p>
                    </div>

                    <!-- Out of Stock -->
                    <div
                        class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm"
                    >
                        <div class="flex items-center justify-between">
                            <p class="text-xs font-semibold text-[#64748B]">
                                Out of Stock
                            </p>

                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#FEF2F2] text-[#E85D5D]"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-4 w-4"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M6 6l12 12M18 6L6 18"
                                    />
                                </svg>
                            </div>
                        </div>

                        <p
                            class="mt-2 text-xl font-bold tracking-tight text-[#1F2937] sm:text-2xl"
                        >
                            {{ outOfStockProducts }}
                        </p>

                        <p class="mt-1 text-[11px] text-[#94A3B8]">
                            Currently unavailable
                        </p>
                    </div>
                </div>

                <!-- Filters -->
                <div
                    class="mt-5 rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                >
                    <div
                        class="flex flex-col gap-3 border-b border-[#E5E7EB] px-5 py-4 lg:flex-row lg:items-end"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="text-base font-bold text-[#1F2937]">
                                Product Listings
                            </p>

                            <p class="mt-1 text-xs text-[#64748B]">
                                Search and filter your catalog.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:w-[680px]">
                            <!-- Search -->
                            <div class="relative sm:col-span-3 lg:col-span-1">
                                <label
                                    class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                >
                                    Search
                                </label>

                                <div class="relative">
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#94A3B8]"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="m21 21-4.35-4.35M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z"
                                        />
                                    </svg>

                                    <input
                                        v-model="search"
                                        type="text"
                                        placeholder="Search products..."
                                        class="h-10 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] pl-9 pr-3 text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#16A6A0] focus:bg-white focus:ring-2 focus:ring-[#16A6A0]/10"
                                    />
                                </div>
                            </div>

                            <!-- Category -->
                            <div>
                                <label
                                    class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                >
                                    Category
                                </label>

                                <select
                                    v-model="category"
                                    class="h-10 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 text-sm text-[#1F2937] outline-none transition focus:border-[#16A6A0] focus:bg-white focus:ring-2 focus:ring-[#16A6A0]/10"
                                >
                                    <option
                                        v-for="item in categories"
                                        :key="item"
                                        :value="item"
                                    >
                                        {{ item }}
                                    </option>
                                </select>
                            </div>

                            <!-- Status -->
                            <div>
                                <label
                                    class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                >
                                    Status
                                </label>

                                <select
                                    v-model="status"
                                    class="h-10 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 text-sm text-[#1F2937] outline-none transition focus:border-[#16A6A0] focus:bg-white focus:ring-2 focus:ring-[#16A6A0]/10"
                                >
                                    <option
                                        v-for="item in statuses"
                                        :key="item"
                                        :value="item"
                                    >
                                        {{ item }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <button
                            v-if="
                                search ||
                                category !== 'All Categories' ||
                                status !== 'All Status'
                            "
                            type="button"
                            class="h-10 shrink-0 rounded-xl border border-[#E5E7EB] bg-white px-3 text-xs font-semibold text-[#64748B] transition hover:border-[#087F8C] hover:text-[#087F8C]"
                            @click="clearFilters"
                        >
                            Clear
                        </button>
                    </div>

                    <!-- Desktop Table -->
                    <div class="hidden overflow-x-auto lg:block">
                        <table class="w-full">
                            <thead>
                                <tr
                                    class="border-b border-[#E5E7EB] bg-[#F8FAF9]"
                                >
                                    <th
                                        class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                    >
                                        Product
                                    </th>

                                    <th
                                        class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                    >
                                        Category
                                    </th>

                                    <th
                                        class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                    >
                                        Price
                                    </th>

                                    <th
                                        class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                    >
                                        Stock
                                    </th>

                                    <th
                                        class="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                    >
                                        Status
                                    </th>

                                    <th
                                        class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                    >
                                        Actions
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-[#E5E7EB]">
                                <tr
                                    v-for="product in products.data"
                                    :key="product.id"
                                    class="transition hover:bg-[#F8FAF9]"
                                >
                                    <!-- Product -->
                                    <td class="px-5 py-3">
                                        <div class="flex min-w-[240px] items-center gap-3">
                                            <div
                                                class="h-12 w-12 shrink-0 overflow-hidden rounded-xl border border-[#E5E7EB] bg-[#F8FAF9]"
                                            >
                                                <img
                                                    v-if="productImage(product)"
                                                    :src="productImage(product)"
                                                    :alt="product.name"
                                                    class="h-full w-full object-cover"
                                                />

                                                <div
                                                    v-else
                                                    class="flex h-full w-full items-center justify-center text-[#087F8C]"
                                                >
                                                    <svg
                                                        xmlns="http://www.w3.org/2000/svg"
                                                        class="h-5 w-5"
                                                        fill="none"
                                                        viewBox="0 0 24 24"
                                                        stroke="currentColor"
                                                        stroke-width="1.7"
                                                    >
                                                        <path
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                            d="m3 16 5-5 4 4 3-3 6 6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2Z"
                                                        />
                                                    </svg>
                                                </div>
                                            </div>

                                            <div class="min-w-0">
                                                <p
                                                    class="truncate text-sm font-semibold text-[#1F2937]"
                                                >
                                                    {{ product.name }}
                                                </p>

                                                <p
                                                    class="mt-0.5 text-[11px] text-[#94A3B8]"
                                                >
                                                    Product #{{ product.id }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Category -->
                                    <td class="px-4 py-3">
                                        <span
                                            class="text-xs font-medium text-[#64748B]"
                                        >
                                            {{
                                                product.category?.name ??
                                                product.category_name ??
                                                '—'
                                            }}
                                        </span>
                                    </td>

                                    <!-- Price -->
                                    <td class="px-4 py-3">
                                        <p
                                            class="text-sm font-bold text-[#087F8C]"
                                        >
                                            {{ peso(product.price) }}
                                        </p>
                                    </td>

                                    <!-- Stock -->
                                    <td class="px-4 py-3">
                                        <p
                                            class="text-sm font-bold"
                                            :class="stockClass(product)"
                                        >
                                            {{ product.stock ?? 0 }}
                                        </p>

                                        <p class="mt-0.5 text-[10px] text-[#94A3B8]">
                                            units
                                        </p>
                                    </td>

                                    <!-- Status -->
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-bold"
                                            :class="statusClass(product)"
                                        >
                                            {{ statusOf(product) }}
                                        </span>
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-5 py-3">
                                        <div
                                            class="flex items-center justify-end gap-1.5"
                                        >
                                            <Link
                                                :href="
                                                    route(
                                                        'seller.products.edit',
                                                        product.id
                                                    )
                                                "
                                                class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-[#E5E7EB] bg-white px-2.5 text-[11px] font-semibold text-[#64748B] transition hover:border-[#087F8C] hover:text-[#087F8C]"
                                            >
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    class="h-3.5 w-3.5"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.5-8.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 8.5-8.5z"
                                                    />
                                                </svg>

                                                Edit
                                            </Link>

                                            <button
                                                v-if="product.status !== 'archived'"
                                                type="button"
                                                class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-2.5 text-[11px] font-semibold text-[#DC2626] transition hover:bg-[#FEE2E2]"
                                                @click="archive(product)"
                                            >
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    class="h-3.5 w-3.5"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M3 7h18M5 7l1 12h12l1-12M9 7V4h6v3"
                                                    />
                                                </svg>

                                                Archive
                                            </button>

                                            <button
                                                v-else
                                                type="button"
                                                class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-[#BBE7D3] bg-[#ECFDF5] px-2.5 text-[11px] font-semibold text-[#16845A] transition hover:bg-[#DFF8EB]"
                                                @click="restore(product)"
                                            >
                                                <svg
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    class="h-3.5 w-3.5"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M3 12a9 9 0 109-9 9.1 9.1 0 00-6.36 2.64L3 9m0 0V4m0 5h5"
                                                    />
                                                </svg>

                                                Restore
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Desktop Empty -->
                        <div
                            v-if="!products.data?.length"
                            class="px-5 py-12 text-center"
                        >
                            <div
                                class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-[#E8F7F6] text-[#087F8C]"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-6 w-6"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                                    />
                                </svg>
                            </div>

                            <h3 class="mt-3 text-sm font-bold text-[#1F2937]">
                                No products found
                            </h3>

                            <p class="mt-1 text-xs text-[#64748B]">
                                Try adjusting your filters or add a new product.
                            </p>

                            <Link
                                :href="route('seller.products.create')"
                                class="mt-4 inline-flex h-9 items-center gap-2 rounded-xl bg-[#087F8C] px-4 text-xs font-semibold text-white transition hover:bg-[#066D78]"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-4 w-4"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 5v14M5 12h14"
                                    />
                                </svg>

                                Add Product
                            </Link>
                        </div>
                    </div>

                    <!-- Mobile Cards -->
                    <div class="divide-y divide-[#E5E7EB] lg:hidden">
                        <div
                            v-for="product in products.data"
                            :key="product.id"
                            class="p-4"
                        >
                            <div class="flex gap-3">
                                <div
                                    class="h-16 w-16 shrink-0 overflow-hidden rounded-xl border border-[#E5E7EB] bg-[#F8FAF9]"
                                >
                                    <img
                                        v-if="productImage(product)"
                                        :src="productImage(product)"
                                        :alt="product.name"
                                        class="h-full w-full object-cover"
                                    />

                                    <div
                                        v-else
                                        class="flex h-full w-full items-center justify-center text-[#087F8C]"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-6 w-6"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="1.7"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="m3 16 5-5 4 4 3-3 6 6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2Z"
                                            />
                                        </svg>
                                    </div>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <h3
                                                class="truncate text-sm font-bold text-[#1F2937]"
                                            >
                                                {{ product.name }}
                                            </h3>

                                            <p
                                                class="mt-0.5 truncate text-[11px] text-[#94A3B8]"
                                            >
                                                {{
                                                    product.category?.name ??
                                                    product.category_name ??
                                                    'Uncategorized'
                                                }}
                                            </p>
                                        </div>

                                        <span
                                            class="shrink-0 rounded-full border px-2 py-1 text-[9px] font-bold"
                                            :class="statusClass(product)"
                                        >
                                            {{ statusOf(product) }}
                                        </span>
                                    </div>

                                    <div
                                        class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3"
                                    >
                                        <div>
                                            <p
                                                class="text-[10px] font-semibold uppercase tracking-[0.1em] text-[#94A3B8]"
                                            >
                                                Price
                                            </p>

                                            <p
                                                class="mt-0.5 text-sm font-bold text-[#087F8C]"
                                            >
                                                {{ peso(product.price) }}
                                            </p>
                                        </div>

                                        <div>
                                            <p
                                                class="text-[10px] font-semibold uppercase tracking-[0.1em] text-[#94A3B8]"
                                            >
                                                Stock
                                            </p>

                                            <p
                                                class="mt-0.5 text-sm font-bold"
                                                :class="stockClass(product)"
                                            >
                                                {{ product.stock ?? 0 }}
                                            </p>
                                        </div>

                                        <div>
                                            <p
                                                class="text-[10px] font-semibold uppercase tracking-[0.1em] text-[#94A3B8]"
                                            >
                                                Product ID
                                            </p>

                                            <p
                                                class="mt-0.5 text-sm font-semibold text-[#64748B]"
                                            >
                                                #{{ product.id }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="mt-3 flex gap-2">
                                        <Link
                                            :href="
                                                route(
                                                    'seller.products.edit',
                                                    product.id
                                                )
                                            "
                                            class="inline-flex h-8 flex-1 items-center justify-center gap-1.5 rounded-lg border border-[#E5E7EB] bg-white px-3 text-[11px] font-semibold text-[#64748B] transition hover:border-[#087F8C] hover:text-[#087F8C]"
                                        >
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="h-3.5 w-3.5"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.5-8.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 8.5-8.5z"
                                                />
                                            </svg>

                                            Edit
                                        </Link>

                                        <button
                                            v-if="product.status !== 'archived'"
                                            type="button"
                                            class="inline-flex h-8 flex-1 items-center justify-center gap-1.5 rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-3 text-[11px] font-semibold text-[#DC2626] transition hover:bg-[#FEE2E2]"
                                            @click="archive(product)"
                                        >
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="h-3.5 w-3.5"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M3 7h18M5 7l1 12h12l1-12M9 7V4h6v3"
                                                />
                                            </svg>

                                            Archive
                                        </button>

                                        <button
                                            v-else
                                            type="button"
                                            class="inline-flex h-8 flex-1 items-center justify-center gap-1.5 rounded-lg border border-[#BBE7D3] bg-[#ECFDF5] px-3 text-[11px] font-semibold text-[#16845A] transition hover:bg-[#DFF8EB]"
                                            @click="restore(product)"
                                        >
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="h-3.5 w-3.5"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M3 12a9 9 0 109-9 9.1 9.1 0 00-6.36 2.64L3 9m0 0V4m0 5h5"
                                                />
                                            </svg>

                                            Restore
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Mobile Empty -->
                        <div
                            v-if="!products.data?.length"
                            class="px-5 py-12 text-center"
                        >
                            <div
                                class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-[#E8F7F6] text-[#087F8C]"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-6 w-6"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                                    />
                                </svg>
                            </div>

                            <h3 class="mt-3 text-sm font-bold text-[#1F2937]">
                                No products found
                            </h3>

                            <p class="mt-1 text-xs text-[#64748B]">
                                Try adjusting your filters or add a new product.
                            </p>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="products.links?.length > 3"
                        class="flex flex-col gap-3 border-t border-[#E5E7EB] px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p class="text-[11px] text-[#94A3B8]">
                            Showing
                            <span class="font-semibold text-[#64748B]">
                                {{ products.from ?? 0 }}
                            </span>
                            to
                            <span class="font-semibold text-[#64748B]">
                                {{ products.to ?? 0 }}
                            </span>
                            of
                            <span class="font-semibold text-[#64748B]">
                                {{ products.total ?? 0 }}
                            </span>
                            products
                        </p>

                        <div class="flex flex-wrap gap-1">
                            <template
                                v-for="(link, index) in products.links"
                                :key="index"
                            >
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    preserve-scroll
                                    class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg border px-2.5 text-[11px] font-semibold transition"
                                    :class="
                                        link.active
                                            ? 'border-[#087F8C] bg-[#087F8C] text-white'
                                            : 'border-[#E5E7EB] bg-white text-[#64748B] hover:border-[#16A6A0] hover:text-[#087F8C]'
                                    "
                                >
                                    <span v-html="link.label"></span>
                                </Link>

                                <span
                                    v-else
                                    class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg border border-[#E5E7EB] bg-[#F8FAF9] px-2.5 text-[11px] text-[#CBD5E1]"
                                >
                                    <span v-html="link.label"></span>
                                </span>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SellerLayout>
</template>