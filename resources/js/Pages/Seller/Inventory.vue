<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
})

const product = computed(() => props.product)

const form = useForm({
    stock: product.value.stock ?? 0,

    variants: (product.value.variants ?? []).map((variant) => ({
        id: variant.id,
        color: variant.color ?? '',
        size: variant.size ?? '',
        stock: variant.stock ?? 0,
    })),
})

const hasVariants = computed(() => form.variants.length > 0)

const totalVariantStock = computed(() => {
    return form.variants.reduce(
        (total, variant) => total + Number(variant.stock || 0),
        0
    )
})

const stockStatus = computed(() => {
    const stock = Number(form.stock || 0)

    if (stock === 0) {
        return {
            label: 'Out of Stock',
            class: 'border-[#FECACA] bg-[#FEF2F2] text-[#DC2626]',
            dot: 'bg-[#E85D5D]',
        }
    }

    if (stock <= 5) {
        return {
            label: 'Low Stock',
            class: 'border-[#FDE68A] bg-[#FFFBEB] text-[#B45309]',
            dot: 'bg-[#F4B942]',
        }
    }

    return {
        label: 'In Stock',
        class: 'border-[#BBE7D3] bg-[#ECFDF5] text-[#16845A]',
        dot: 'bg-[#22A06B]',
    }
})

const productImage = computed(() => {
    if (!product.value.image_path) {
        return null
    }

    if (
        product.value.image_path.startsWith('http://') ||
        product.value.image_path.startsWith('https://') ||
        product.value.image_path.startsWith('/storage/')
    ) {
        return product.value.image_path
    }

    return `/storage/${product.value.image_path.replace(/^\/+/, '')}`
})

const submit = () => {
    form.patch(
        route(
            'seller.products.inventory.update',
            product.value.id
        ),
        {
            preserveScroll: true,
        }
    )
}
</script>

<template>
    <SellerLayout>
        <Head :title="`Inventory - ${product.name}`" />

        <div class="min-h-[calc(100vh-68px)] bg-[#F8FAF9]">

            <!-- PAGE HEADER -->
            <div class="border-b border-[#E5E7EB] bg-white">
                <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="mb-2 flex items-center gap-2 text-xs">
                                <Link
                                    :href="route('seller.products')"
                                    class="font-semibold text-[#087F8C] transition hover:text-[#066B76]"
                                >
                                    Inventory
                                </Link>

                                <svg
                                    class="h-3.5 w-3.5 text-[#CBD5E1]"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24a.75.75 0 0 1 0 1.06l-4.24 4.24a.75.75 0 0 1-1.08 0Z"
                                        clip-rule="evenodd"
                                    />
                                </svg>

                                <span
                                    class="truncate text-[#64748B]"
                                >
                                    {{ product.name }}
                                </span>
                            </div>

                            <h1
                                class="text-2xl font-bold tracking-tight text-[#1F2937]"
                            >
                                Manage Inventory
                            </h1>

                            <p class="mt-1 text-sm text-[#64748B]">
                                Update stock levels for this product
                                and its variants.
                            </p>
                        </div>

                        <Link
                            :href="route('seller.products')"
                            class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-[#E5E7EB] bg-white px-4 py-2.5 text-sm font-semibold text-[#475569] shadow-sm transition hover:border-[#087F8C] hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                        >
                            <svg
                                class="h-4 w-4"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M17 10a.75.75 0 0 1-.75.75H5.56l3.22 3.22a.75.75 0 1 1-1.06 1.06l-4.5-4.5a.75.75 0 0 1 0-1.06l4.5-4.5a.75.75 0 0 1 1.06 1.06l-3.22 3.22h10.69A.75.75 0 0 1 17 10Z"
                                    clip-rule="evenodd"
                                />
                            </svg>

                            Back to Inventory
                        </Link>
                    </div>
                </div>
            </div>

            <!-- MAIN CONTENT -->
            <main class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                <div class="grid gap-5 lg:grid-cols-[300px_minmax(0,1fr)]">

                    <!-- PRODUCT SUMMARY -->
                    <aside class="lg:sticky lg:top-24 lg:self-start">
                        <div
                            class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                        >
                            <!-- Product Image -->
                            <div class="relative aspect-square bg-[#F8FAF9]">
                                <img
                                    v-if="productImage"
                                    :src="productImage"
                                    :alt="product.name"
                                    class="h-full w-full object-cover"
                                />

                                <div
                                    v-else
                                    class="flex h-full flex-col items-center justify-center text-[#94A3B8]"
                                >
                                    <div
                                        class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#E8F7F6] text-[#087F8C]"
                                    >
                                        <svg
                                            class="h-6 w-6"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="m3 16 5-5 4 4 3-3 6 6M5 20h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z"
                                            />
                                        </svg>
                                    </div>

                                    <span class="mt-2 text-xs font-medium">
                                        No image available
                                    </span>
                                </div>

                                <div
                                    class="absolute left-3 top-3 inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-bold shadow-sm"
                                    :class="stockStatus.class"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="stockStatus.dot"
                                    ></span>

                                    {{ stockStatus.label }}
                                </div>
                            </div>

                            <!-- Product Details -->
                            <div class="p-4">
                                <h2
                                    class="line-clamp-2 text-base font-bold leading-6 text-[#1F2937]"
                                >
                                    {{ product.name }}
                                </h2>

                                <p
                                    v-if="product.category"
                                    class="mt-1 text-xs font-medium text-[#64748B]"
                                >
                                    {{ product.category.name }}
                                </p>

                                <!-- Summary -->
                                <div class="mt-4 grid grid-cols-2 gap-2">
                                    <div
                                        class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-3"
                                    >
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]"
                                            >
                                                <svg
                                                    class="h-4 w-4"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="1.8"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M20 7 12 3 4 7m16 0-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                                                    />
                                                </svg>
                                            </div>
                                        </div>

                                        <p
                                            class="mt-2 text-[10px] font-bold uppercase tracking-wide text-[#64748B]"
                                        >
                                            Product Stock
                                        </p>

                                        <p
                                            class="mt-0.5 text-xl font-bold text-[#1F2937]"
                                        >
                                            {{ form.stock }}
                                        </p>
                                    </div>

                                    <div
                                        class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-3"
                                    >
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#FFF7E2] text-[#B77900]"
                                            >
                                                <svg
                                                    class="h-4 w-4"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="1.8"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M4 7h16M4 12h16M4 17h16"
                                                    />
                                                </svg>
                                            </div>
                                        </div>

                                        <p
                                            class="mt-2 text-[10px] font-bold uppercase tracking-wide text-[#64748B]"
                                        >
                                            Variants
                                        </p>

                                        <p
                                            class="mt-0.5 text-xl font-bold text-[#1F2937]"
                                        >
                                            {{ form.variants.length }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    v-if="hasVariants"
                                    class="mt-2 rounded-xl border border-[#BFE8E5] bg-[#E8F7F6] p-3"
                                >
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p
                                                class="text-[10px] font-bold uppercase tracking-wide text-[#087F8C]"
                                            >
                                                Total Variant Stock
                                            </p>

                                            <p
                                                class="mt-0.5 text-xl font-bold text-[#087F8C]"
                                            >
                                                {{ totalVariantStock }}
                                            </p>
                                        </div>

                                        <div
                                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-white text-[#087F8C] shadow-sm"
                                        >
                                            <svg
                                                class="h-4 w-4"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M12 3v18m9-9H3"
                                                />
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </aside>

                    <!-- INVENTORY FORM -->
                    <div class="min-w-0">
                        <form
                            @submit.prevent="submit"
                            class="space-y-5"
                        >
                            <!-- GENERAL STOCK -->
                            <section
                                class="rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                            >
                                <div
                                    class="border-b border-[#E5E7EB] px-5 py-4"
                                >
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
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
                                                    d="M20 7 12 3 4 7m16 0-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                                                />
                                            </svg>
                                        </div>

                                        <div>
                                            <h2
                                                class="text-base font-bold text-[#1F2937]"
                                            >
                                                General Stock
                                            </h2>

                                            <p class="mt-0.5 text-xs text-[#64748B]">
                                                Set the available stock for this product.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-5">
                                    <div class="max-w-xs">
                                        <label
                                            for="stock"
                                            class="mb-2 block text-xs font-bold uppercase tracking-wide text-[#475569]"
                                        >
                                            Stock Quantity
                                        </label>

                                        <div class="relative">
                                            <input
                                                id="stock"
                                                v-model.number="form.stock"
                                                type="number"
                                                min="0"
                                                class="block w-full rounded-xl border-[#E5E7EB] bg-white px-4 py-3 text-sm font-semibold text-[#1F2937] shadow-sm outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10"
                                            />

                                            <span
                                                class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-[#94A3B8]"
                                            >
                                                units
                                            </span>
                                        </div>

                                        <p
                                            v-if="form.errors.stock"
                                            class="mt-2 text-xs font-medium text-[#DC2626]"
                                        >
                                            {{ form.errors.stock }}
                                        </p>
                                    </div>
                                </div>
                            </section>

                            <!-- PRODUCT VARIANTS -->
                            <section
                                class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                            >
                                <div
                                    class="border-b border-[#E5E7EB] px-5 py-4"
                                >
                                    <div class="flex items-center justify-between gap-4">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#FFF7E2] text-[#B77900]"
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
                                                        d="M4 6h16M4 12h16M4 18h16"
                                                    />
                                                </svg>
                                            </div>

                                            <div>
                                                <h2
                                                    class="text-base font-bold text-[#1F2937]"
                                                >
                                                    Product Variants
                                                </h2>

                                                <p
                                                    class="mt-0.5 text-xs text-[#64748B]"
                                                >
                                                    Update stock for each variant.
                                                </p>
                                            </div>
                                        </div>

                                        <span
                                            v-if="hasVariants"
                                            class="hidden rounded-full bg-[#E8F7F6] px-2.5 py-1 text-[11px] font-bold text-[#087F8C] sm:inline-flex"
                                        >
                                            {{ form.variants.length }} variants
                                        </span>
                                    </div>
                                </div>

                                <!-- DESKTOP TABLE -->
                                <div
                                    v-if="hasVariants"
                                    class="hidden overflow-x-auto md:block"
                                >
                                    <table class="min-w-full">
                                        <thead class="bg-[#F8FAF9]">
                                            <tr>
                                                <th
                                                    class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-[#64748B]"
                                                >
                                                    #
                                                </th>

                                                <th
                                                    class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-[#64748B]"
                                                >
                                                    Color
                                                </th>

                                                <th
                                                    class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-[#64748B]"
                                                >
                                                    Size
                                                </th>

                                                <th
                                                    class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-wide text-[#64748B]"
                                                >
                                                    Stock
                                                </th>
                                            </tr>
                                        </thead>

                                        <tbody class="divide-y divide-[#F1F5F9]">
                                            <tr
                                                v-for="(
                                                    variant, index
                                                ) in form.variants"
                                                :key="variant.id"
                                                class="transition hover:bg-[#F8FAF9]"
                                            >
                                                <td
                                                    class="px-5 py-4 text-xs font-semibold text-[#94A3B8]"
                                                >
                                                    {{ index + 1 }}
                                                </td>

                                                <td
                                                    class="px-5 py-4 text-sm font-semibold text-[#1F2937]"
                                                >
                                                    {{ variant.color || '—' }}
                                                </td>

                                                <td
                                                    class="px-5 py-4 text-sm text-[#64748B]"
                                                >
                                                    {{ variant.size || '—' }}
                                                </td>

                                                <td class="px-5 py-4">
                                                    <div class="max-w-[150px]">
                                                        <input
                                                            v-model.number="
                                                                variant.stock
                                                            "
                                                            type="number"
                                                            min="0"
                                                            class="block w-full rounded-xl border-[#E5E7EB] bg-white px-3 py-2.5 text-sm font-semibold text-[#1F2937] shadow-sm outline-none transition focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10"
                                                        />

                                                        <p
                                                            v-if="
                                                                form.errors[
                                                                    `variants.${index}.stock`
                                                                ]
                                                            "
                                                            class="mt-1 text-[11px] font-medium text-[#DC2626]"
                                                        >
                                                            {{
                                                                form.errors[
                                                                    `variants.${index}.stock`
                                                                ]
                                                            }}
                                                        </p>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- MOBILE VARIANTS -->
                                <div
                                    v-if="hasVariants"
                                    class="space-y-3 p-4 md:hidden"
                                >
                                    <div
                                        v-for="(
                                            variant, index
                                        ) in form.variants"
                                        :key="variant.id"
                                        class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-4"
                                    >
                                        <div
                                            class="flex items-center justify-between gap-4"
                                        >
                                            <div class="min-w-0">
                                                <p
                                                    class="text-[10px] font-bold uppercase tracking-wide text-[#94A3B8]"
                                                >
                                                    Variant {{ index + 1 }}
                                                </p>

                                                <p
                                                    class="mt-1 truncate text-sm font-bold text-[#1F2937]"
                                                >
                                                    {{
                                                        variant.color ||
                                                        'No color'
                                                    }}
                                                </p>

                                                <p class="mt-0.5 text-xs text-[#64748B]">
                                                    Size:
                                                    {{
                                                        variant.size ||
                                                        'No size'
                                                    }}
                                                </p>
                                            </div>

                                            <div class="w-28 shrink-0">
                                                <label
                                                    class="mb-1.5 block text-[10px] font-bold uppercase tracking-wide text-[#64748B]"
                                                >
                                                    Stock
                                                </label>

                                                <input
                                                    v-model.number="
                                                        variant.stock
                                                    "
                                                    type="number"
                                                    min="0"
                                                    class="block w-full rounded-xl border-[#E5E7EB] bg-white px-3 py-2.5 text-sm font-semibold text-[#1F2937] shadow-sm outline-none focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10"
                                                />
                                            </div>
                                        </div>

                                        <p
                                            v-if="
                                                form.errors[
                                                    `variants.${index}.stock`
                                                ]
                                            "
                                            class="mt-2 text-[11px] font-medium text-[#DC2626]"
                                        >
                                            {{
                                                form.errors[
                                                    `variants.${index}.stock`
                                                ]
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <!-- NO VARIANTS -->
                                <div
                                    v-if="!hasVariants"
                                    class="px-6 py-10 text-center"
                                >
                                    <div
                                        class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-[#E8F7F6] text-[#087F8C]"
                                    >
                                        <svg
                                            class="h-6 w-6"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M20 7 12 3 4 7m16 0-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                                            />
                                        </svg>
                                    </div>

                                    <h3
                                        class="mt-3 text-sm font-bold text-[#1F2937]"
                                    >
                                        No variants
                                    </h3>

                                    <p
                                        class="mx-auto mt-1 max-w-sm text-xs leading-5 text-[#64748B]"
                                    >
                                        This product does not have color or
                                        size variants. Inventory is managed
                                        using the general stock quantity.
                                    </p>
                                </div>
                            </section>

                            <!-- ACTIONS -->
                            <div
                                class="flex flex-col-reverse gap-2 border-t border-[#E5E7EB] pt-4 sm:flex-row sm:justify-end"
                            >
                                <Link
                                    :href="route('seller.products')"
                                    class="inline-flex items-center justify-center rounded-xl border border-[#E5E7EB] bg-white px-5 py-2.5 text-sm font-semibold text-[#475569] shadow-sm transition hover:border-[#CBD5E1] hover:bg-[#F8FAF9]"
                                >
                                    Cancel
                                </Link>

                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#087F8C] px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#066B76] disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <svg
                                        v-if="!form.processing"
                                        class="h-4 w-4"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M5 12h14M13 6l6 6-6 6"
                                        />
                                    </svg>

                                    <svg
                                        v-else
                                        class="h-4 w-4 animate-spin"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <circle
                                            cx="12"
                                            cy="12"
                                            r="9"
                                            class="opacity-25"
                                        />
                                        <path
                                            d="M21 12a9 9 0 0 1-9 9"
                                            stroke-linecap="round"
                                        />
                                    </svg>

                                    {{
                                        form.processing
                                            ? 'Saving...'
                                            : 'Save Inventory'
                                    }}
                                </button>
                            </div>

                            <!-- SUCCESS MESSAGE -->
                            <div
                                v-if="$page.props.flash?.status"
                                class="flex items-center gap-3 rounded-xl border border-[#BBE7D3] bg-[#ECFDF5] px-4 py-3 text-sm font-semibold text-[#16845A]"
                            >
                                <div
                                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="m5 12 4 4L19 6"
                                        />
                                    </svg>
                                </div>

                                {{ $page.props.flash.status }}
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </SellerLayout>
</template>