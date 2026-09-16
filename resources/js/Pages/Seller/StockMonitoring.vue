<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'

const props = defineProps({
    products: {
        type: Array,
        default: () => [],
    },

    summary: {
        type: Object,
        default: () => ({
            total_products: 0,
            total_stock: 0,
            in_stock: 0,
            low_stock: 0,
            out_of_stock: 0,
        }),
    },

    filters: {
        type: Object,
        default: () => ({
            search: '',
            stock_status: 'All Stock',
        }),
    },
})

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

const search = ref(props.filters.search ?? '')

const stockStatus = ref(
    props.filters.stock_status ?? 'All Stock'
)

const statuses = [
    'All Stock',
    'In Stock',
    'Low Stock',
    'Out of Stock',
]

let debounce = null

watch(
    [search, stockStatus],
    () => {
        clearTimeout(debounce)

        debounce = setTimeout(() => {
            router.get(
                route('seller.stock-monitoring'),
                {
                    search: search.value || undefined,

                    stock_status:
                        stockStatus.value === 'All Stock'
                            ? undefined
                            : stockStatus.value,
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                }
            )
        }, 300)
    }
)

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

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

const getStockStatus = stock => {
    const quantity = Number(stock ?? 0)

    if (quantity === 0) {
        return {
            label: 'Out of Stock',
            class: 'border-[#FECACA] bg-[#FEF2F2] text-[#DC2626]',
        }
    }

    if (quantity <= 5) {
        return {
            label: 'Low Stock',
            class: 'border-[#FDE68A] bg-[#FFFBEB] text-[#B45309]',
        }
    }

    return {
        label: 'In Stock',
        class: 'border-[#BBE7D3] bg-[#ECFDF5] text-[#16845A]',
    }
}

const stockTextClass = stock => {
    const quantity = Number(stock ?? 0)

    if (quantity === 0) {
        return 'text-[#E85D5D]'
    }

    if (quantity <= 5) {
        return 'text-[#B77900]'
    }

    return 'text-[#1F2937]'
}

const variantLabel = variant => {
    const parts = []

    if (variant.color) {
        parts.push(variant.color)
    }

    if (variant.size) {
        parts.push(variant.size)
    }

    return parts.length
        ? parts.join(' / ')
        : 'Default'
}

/*
|--------------------------------------------------------------------------
| Products With Variants
|--------------------------------------------------------------------------
*/

const monitoredRows = computed(() => {
    const rows = []

    props.products.forEach(product => {
        const variants = product.variants ?? []

        if (variants.length > 0) {
            variants.forEach(variant => {
                rows.push({
                    id: `variant-${variant.id}`,

                    productId: product.id,

                    name: product.name,

                    category:
                        product.category?.name ??
                        'Uncategorized',

                    image_path: product.image_path,

                    price: product.price,

                    sold_count:
                        product.sold_count ?? 0,

                    variant: variantLabel(variant),

                    stock:
                        Number(variant.stock ?? 0),

                    hasVariant: true,
                })
            })

            return
        }

        rows.push({
            id: `product-${product.id}`,

            productId: product.id,

            name: product.name,

            category:
                product.category?.name ??
                'Uncategorized',

            image_path: product.image_path,

            price: product.price,

            sold_count:
                product.sold_count ?? 0,

            variant: 'Default',

            stock:
                Number(product.stock ?? 0),

            hasVariant: false,
        })
    })

    return rows
})

/*
|--------------------------------------------------------------------------
| Display Statistics
|--------------------------------------------------------------------------
*/

const monitoredTotalStock = computed(() => {
    return monitoredRows.value.reduce(
        (total, row) => {
            return total + Number(row.stock || 0)
        },
        0
    )
})

const monitoredInStock = computed(() => {
    return monitoredRows.value.filter(
        row => row.stock > 5
    ).length
})

const monitoredLowStock = computed(() => {
    return monitoredRows.value.filter(
        row =>
            row.stock > 0 &&
            row.stock <= 5
    ).length
})

const monitoredOutOfStock = computed(() => {
    return monitoredRows.value.filter(
        row => row.stock === 0
    ).length
})
</script>

<template>
    <Head title="Stock Monitoring - Seller Center" />

    <SellerLayout>
        <div class="min-h-[calc(100vh-68px)] bg-[#F8FAF9]">
            <div
                class="mx-auto w-full max-w-7xl px-4 py-5 sm:px-5 lg:px-6"
            >

                <!-- ===================================================== -->
                <!-- PAGE HEADER -->
                <!-- ===================================================== -->

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
                            Stock Monitoring
                        </h1>

                        <p
                            class="mt-1 text-sm text-[#64748B]"
                        >
                            Monitor product and variant stock levels
                            and identify items that need restocking.
                        </p>
                    </div>

                    <Link
                        :href="route('seller.products')"
                        class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-xl border border-[#E5E7EB] bg-white px-4 text-sm font-semibold text-[#64748B] shadow-sm transition hover:border-[#087F8C] hover:text-[#087F8C]"
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
                                d="M15 19l-7-7 7-7"
                            />
                        </svg>

                        Product Listings
                    </Link>
                </div>

                <!-- ===================================================== -->
                <!-- SUMMARY -->
                <!-- ===================================================== -->

                <div
                    class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4"
                >

                    <!-- TOTAL STOCK -->

                    <div
                        class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm"
                    >
                        <div
                            class="flex items-center justify-between"
                        >
                            <p
                                class="text-xs font-semibold text-[#64748B]"
                            >
                                Total Stock
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
                            {{ monitoredTotalStock }}
                        </p>

                        <p
                            class="mt-1 text-[11px] text-[#94A3B8]"
                        >
                            Units across inventory
                        </p>
                    </div>

                    <!-- IN STOCK -->

                    <div
                        class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm"
                    >
                        <div
                            class="flex items-center justify-between"
                        >
                            <p
                                class="text-xs font-semibold text-[#64748B]"
                            >
                                In Stock
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
                            {{ monitoredInStock }}
                        </p>

                        <p
                            class="mt-1 text-[11px] text-[#94A3B8]"
                        >
                            Healthy stock levels
                        </p>
                    </div>

                    <!-- LOW STOCK -->

                    <div
                        class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm"
                    >
                        <div
                            class="flex items-center justify-between"
                        >
                            <p
                                class="text-xs font-semibold text-[#64748B]"
                            >
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
                            {{ monitoredLowStock }}
                        </p>

                        <p
                            class="mt-1 text-[11px] text-[#94A3B8]"
                        >
                            Needs replenishment
                        </p>
                    </div>

                    <!-- OUT OF STOCK -->

                    <div
                        class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm"
                    >
                        <div
                            class="flex items-center justify-between"
                        >
                            <p
                                class="text-xs font-semibold text-[#64748B]"
                            >
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
                            {{ monitoredOutOfStock }}
                        </p>

                        <p
                            class="mt-1 text-[11px] text-[#94A3B8]"
                        >
                            Requires immediate action
                        </p>
                    </div>
                </div>

                <!-- ===================================================== -->
                <!-- FILTERS -->
                <!-- ===================================================== -->

                <section
                    class="mt-5 rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                >
                    <div
                        class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-end"
                    >
                        <div class="min-w-0 flex-1">
                            <p
                                class="text-base font-bold text-[#1F2937]"
                            >
                                Inventory Filters
                            </p>

                            <p
                                class="mt-1 text-xs text-[#64748B]"
                            >
                                Search products or filter by stock level.
                            </p>
                        </div>

                        <div
                            class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:min-w-[520px]"
                        >

                            <!-- SEARCH -->

                            <div>
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

                            <!-- STOCK STATUS -->

                            <div>
                                <label
                                    class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                >
                                    Stock Status
                                </label>

                                <select
                                    v-model="stockStatus"
                                    class="h-10 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 text-sm text-[#1F2937] outline-none transition focus:border-[#16A6A0] focus:bg-white focus:ring-2 focus:ring-[#16A6A0]/10"
                                >
                                    <option
                                        v-for="status in statuses"
                                        :key="status"
                                        :value="status"
                                    >
                                        {{ status }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ===================================================== -->
                <!-- INVENTORY OVERVIEW -->
                <!-- ===================================================== -->

                <section
                    class="mt-5 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                >

                    <!-- SECTION HEADER -->

                    <div
                        class="flex flex-col gap-2 border-b border-[#E5E7EB] px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h2
                                class="text-base font-bold text-[#1F2937]"
                            >
                                Inventory Overview
                            </h2>

                            <p
                                class="mt-1 text-xs text-[#64748B]"
                            >
                                {{ monitoredRows.length }}
                                inventory item(s) found.
                            </p>
                        </div>

                        <div
                            class="inline-flex w-fit items-center rounded-full bg-[#FFF8E7] px-2.5 py-1 text-[10px] font-semibold text-[#B77900]"
                        >
                            Low stock threshold: 5 units
                        </div>
                    </div>

                    <!-- ================================================= -->
                    <!-- DESKTOP TABLE -->
                    <!-- ================================================= -->

                    <div
                        v-if="monitoredRows.length"
                        class="hidden overflow-x-auto lg:block"
                    >
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
                                        Variant
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
                                        Action
                                    </th>
                                </tr>
                            </thead>

                            <tbody
                                class="divide-y divide-[#E5E7EB]"
                            >
                                <tr
                                    v-for="row in monitoredRows"
                                    :key="row.id"
                                    class="transition hover:bg-[#F8FAF9]"
                                >

                                    <!-- PRODUCT -->

                                    <td class="px-5 py-3">
                                        <div
                                            class="flex min-w-[250px] items-center gap-3"
                                        >
                                            <div
                                                class="h-12 w-12 shrink-0 overflow-hidden rounded-xl border border-[#E5E7EB] bg-[#F8FAF9]"
                                            >
                                                <img
                                                    v-if="productImage(row)"
                                                    :src="productImage(row)"
                                                    :alt="row.name"
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
                                                    {{ row.name }}
                                                </p>

                                                <p
                                                    class="mt-0.5 truncate text-[11px] text-[#94A3B8]"
                                                >
                                                    {{ row.category }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- VARIANT -->

                                    <td class="px-4 py-3">
                                        <span
                                            class="text-xs font-medium text-[#64748B]"
                                        >
                                            {{ row.variant }}
                                        </span>
                                    </td>

                                    <!-- PRICE -->

                                    <td class="px-4 py-3">
                                        <span
                                            class="text-sm font-bold text-[#087F8C]"
                                        >
                                            {{ peso(row.price) }}
                                        </span>
                                    </td>

                                    <!-- STOCK -->

                                    <td class="px-4 py-3">
                                        <span
                                            class="text-sm font-bold"
                                            :class="stockTextClass(row.stock)"
                                        >
                                            {{ row.stock }}
                                        </span>

                                        <span
                                            class="ml-1 text-[10px] text-[#94A3B8]"
                                        >
                                            units
                                        </span>
                                    </td>

                                    <!-- STATUS -->

                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-bold"
                                            :class="getStockStatus(row.stock).class"
                                        >
                                            {{ getStockStatus(row.stock).label }}
                                        </span>
                                    </td>

                                    <!-- ACTION -->

                                    <td class="px-5 py-3">
                                        <div
                                            class="flex justify-end"
                                        >
                                            <Link
                                                :href="
                                                    route(
                                                        'seller.products.inventory',
                                                        row.productId
                                                    )
                                                "
                                                class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-[#E5E7EB] bg-white px-3 text-[11px] font-semibold text-[#64748B] transition hover:border-[#087F8C] hover:text-[#087F8C]"
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
                                                        d="M12 6v12m6-6H6"
                                                    />
                                                </svg>

                                                Manage Stock
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- ================================================= -->
                    <!-- MOBILE -->
                    <!-- ================================================= -->

                    <div
                        v-if="monitoredRows.length"
                        class="divide-y divide-[#E5E7EB] lg:hidden"
                    >
                        <div
                            v-for="row in monitoredRows"
                            :key="row.id"
                            class="p-4"
                        >
                            <div class="flex gap-3">

                                <!-- IMAGE -->

                                <div
                                    class="h-14 w-14 shrink-0 overflow-hidden rounded-xl border border-[#E5E7EB] bg-[#F8FAF9]"
                                >
                                    <img
                                        v-if="productImage(row)"
                                        :src="productImage(row)"
                                        :alt="row.name"
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

                                <!-- DETAILS -->

                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex items-start justify-between gap-2"
                                    >
                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-sm font-bold text-[#1F2937]"
                                            >
                                                {{ row.name }}
                                            </p>

                                            <p
                                                class="mt-0.5 truncate text-[11px] text-[#94A3B8]"
                                            >
                                                {{ row.category }}
                                            </p>
                                        </div>

                                        <span
                                            class="shrink-0 rounded-full border px-2 py-1 text-[9px] font-bold"
                                            :class="getStockStatus(row.stock).class"
                                        >
                                            {{ getStockStatus(row.stock).label }}
                                        </span>
                                    </div>

                                    <!-- MOBILE DATA -->

                                    <div
                                        class="mt-3 grid grid-cols-3 gap-3"
                                    >
                                        <div>
                                            <p
                                                class="text-[10px] font-semibold uppercase tracking-[0.1em] text-[#94A3B8]"
                                            >
                                                Variant
                                            </p>

                                            <p
                                                class="mt-0.5 truncate text-xs font-semibold text-[#64748B]"
                                            >
                                                {{ row.variant }}
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
                                                :class="stockTextClass(row.stock)"
                                            >
                                                {{ row.stock }}
                                            </p>
                                        </div>

                                        <div>
                                            <p
                                                class="text-[10px] font-semibold uppercase tracking-[0.1em] text-[#94A3B8]"
                                            >
                                                Price
                                            </p>

                                            <p
                                                class="mt-0.5 text-xs font-bold text-[#087F8C]"
                                            >
                                                {{ peso(row.price) }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- ACTION -->

                                    <Link
                                        :href="
                                            route(
                                                'seller.products.inventory',
                                                row.productId
                                            )
                                        "
                                        class="mt-3 inline-flex h-8 w-full items-center justify-center gap-1.5 rounded-lg border border-[#E5E7EB] bg-white text-[11px] font-semibold text-[#64748B] transition hover:border-[#087F8C] hover:text-[#087F8C]"
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
                                                d="M12 6v12m6-6H6"
                                            />
                                        </svg>

                                        Manage Stock
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ================================================= -->
                    <!-- EMPTY STATE -->
                    <!-- ================================================= -->

                    <div
                        v-if="!monitoredRows.length"
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

                        <h3
                            class="mt-3 text-sm font-bold text-[#1F2937]"
                        >
                            No inventory items found
                        </h3>

                        <p
                            class="mx-auto mt-1 max-w-md text-xs text-[#64748B]"
                        >
                            Try changing your search or stock filter.
                        </p>

                        <Link
                            :href="route('seller.products')"
                            class="mt-4 inline-flex h-9 items-center gap-2 rounded-xl bg-[#087F8C] px-4 text-xs font-semibold text-white transition hover:bg-[#066D78]"
                        >
                            View Products
                        </Link>
                    </div>
                </section>
            </div>
        </div>
    </SellerLayout>
</template>