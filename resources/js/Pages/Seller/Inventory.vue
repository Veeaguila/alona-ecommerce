<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
    adjustments: {
        type: Object,
        default: () => ({ data: [] }),
    },
    filters: {
        type: Object,
        default: () => ({ type: 'all' }),
    },
})

const product = computed(() => props.product)

const form = useForm({
    stock: product.value.stock ?? 0,
    notes: '',

    variants: (product.value.variants ?? []).map((variant) => ({
        id: variant.id,
        color: variant.color ?? '',
        size: variant.size ?? '',
        stock: variant.stock ?? 0,
        notes: '',
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
            onSuccess: () => {
                form.notes = ''
            },
        }
    )
}

/*
|--------------------------------------------------------------------------
| Stock Adjustment Modal & Audit Logging (SELLER-13)
|--------------------------------------------------------------------------
*/

const adjustmentModalOpen = ref(false)

const adjustmentForm = useForm({
    type: 'stock_in',
    quantity: 1,
    mode: 'add',
    product_variant_id: product.value.variants?.[0]?.id ?? '',
    notes: '',
    reference_number: '',
})

const openAdjustmentModal = (type = 'stock_in', variantId = null) => {
    adjustmentForm.reset()
    adjustmentForm.clearErrors()
    adjustmentForm.type = type
    if (variantId !== null) {
        adjustmentForm.product_variant_id = variantId
    } else if (hasVariants.value && product.value.variants?.length) {
        adjustmentForm.product_variant_id = product.value.variants[0].id
    } else {
        adjustmentForm.product_variant_id = ''
    }
    adjustmentModalOpen.value = true
}

const selectedVariantCurrentStock = computed(() => {
    if (hasVariants.value && adjustmentForm.product_variant_id) {
        const v = product.value.variants?.find(
            (item) => item.id === Number(adjustmentForm.product_variant_id)
        )
        return v ? Number(v.stock ?? 0) : 0
    }
    return Number(product.value.stock ?? 0)
})

const previewNewStock = computed(() => {
    const cur = selectedVariantCurrentStock.value
    const qty = Math.max(0, Number(adjustmentForm.quantity || 0))
    const t = adjustmentForm.type

    if (t === 'stock_in' || t === 'return') {
        return cur + qty
    }
    if (t === 'damage' || t === 'stock_out') {
        return Math.max(0, cur - qty)
    }
    // manual
    if (adjustmentForm.mode === 'subtract') {
        return Math.max(0, cur - qty)
    }
    if (adjustmentForm.mode === 'set') {
        return qty
    }
    return cur + qty
})

const submitAdjustment = () => {
    adjustmentForm.post(
        route('seller.products.adjust-stock', product.value.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                adjustmentModalOpen.value = false
                adjustmentForm.reset()
            },
        }
    )
}

/*
|--------------------------------------------------------------------------
| Audit Log Type Filtering & Formatting
|--------------------------------------------------------------------------
*/

const activeTypeFilter = ref(props.filters.type || 'all')

const filterAdjustments = (type) => {
    activeTypeFilter.value = type
    router.get(
        route('seller.products.inventory', product.value.id),
        { type: type === 'all' ? undefined : type },
        { preserveScroll: true, preserveState: true }
    )
}

const adjustmentTypes = [
    { key: 'all', label: 'All Records' },
    { key: 'stock_in', label: 'Stock In' },
    { key: 'stock_out', label: 'Stock Out' },
    { key: 'damage', label: 'Damages' },
    { key: 'return', label: 'Returns' },
    { key: 'manual', label: 'Manual' },
]

const getAdjustmentBadge = (type) => {
    switch (type) {
        case 'stock_in':
            return {
                label: 'Stock In',
                class: 'bg-emerald-50 text-emerald-700 border-emerald-200',
                dot: 'bg-emerald-500',
            }
        case 'stock_out':
            return {
                label: 'Stock Out',
                class: 'bg-rose-50 text-rose-700 border-rose-200',
                dot: 'bg-rose-500',
            }
        case 'damage':
            return {
                label: 'Damages',
                class: 'bg-red-50 text-red-700 border-red-200',
                dot: 'bg-red-600',
            }
        case 'return':
            return {
                label: 'Returns',
                class: 'bg-blue-50 text-blue-700 border-blue-200',
                dot: 'bg-blue-500',
            }
        case 'manual':
        default:
            return {
                label: 'Manual Adjustment',
                class: 'bg-amber-50 text-amber-700 border-amber-200',
                dot: 'bg-amber-500',
            }
    }
}

const formatDate = (dateString) => {
    if (!dateString) return '—'
    const d = new Date(dateString)
    return d.toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}
</script>

<template>
    <SellerLayout>
        <Head :title="`Inventory - ${product.name}`" />

        <div class="w-full space-y-6">

            <!-- PAGE HEADER -->
            <div class="border-b border-[#E5E7EB] pb-5">
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-[#087F8C]">
                                <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                <span>Inventory</span>
                                <span class="text-slate-300">·</span>
                                <span class="font-medium normal-case text-slate-500 truncate max-w-xs">{{ product.name }}</span>
                            </div>

                            <h1
                                class="mt-1 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl"
                            >
                                Manage Inventory
                            </h1>

                            <p class="mt-0.5 text-xs text-slate-500">
                                Update stock levels, adjustments, and movements for this product and its variants.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2.5">
                            <button
                                type="button"
                                @click="openAdjustmentModal('stock_in')"
                                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-[#087F8C] px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-[#066B76]"
                            >
                                <svg
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 4.5v15m7.5-7.5h-15"
                                    />
                                </svg>
                                Log Stock Adjustment
                            </button>

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
            <div class="w-full">
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
                                                min="0" max="99999" step="1"
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
                                            {{ form.errors.stock }}</p><div class="mt-4"><label for="notes" class="mb-2 block text-xs font-bold uppercase tracking-wide text-[#475569]">Reason / Notes (Optional)</label><input id="notes" v-model="form.notes" type="text" placeholder="e.g. Restock from supplier, manual recount" class="block w-full rounded-xl border-[#E5E7EB] bg-white px-4 py-2.5 text-sm text-[#1F2937] shadow-sm outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10" /></div><p v-if="false">
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
                                                    <div class="flex items-center gap-2">
                                                        <div class="max-w-[130px]">
                                                            <input
                                                                v-model.number="
                                                                    variant.stock
                                                                "
                                                                type="number"
                                                                min="0" max="99999" step="1"
                                                                class="block w-full rounded-xl border-[#E5E7EB] bg-white px-3 py-2 text-sm font-semibold text-[#1F2937] shadow-sm outline-none transition focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10"
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

                                                        <button
                                                            type="button"
                                                            @click="openAdjustmentModal('stock_in', variant.id)"
                                                            title="Log stock adjustment for this variant"
                                                            class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-[#E5E7EB] bg-[#F8FAF9] px-2.5 py-2 text-xs font-semibold text-[#087F8C] transition hover:border-[#087F8C] hover:bg-[#E8F7F6]"
                                                        >
                                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                                            </svg>
                                                            Adjust
                                                        </button>
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
                                                    min="0" max="99999" step="1"
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
                        </form>

                        <!-- STOCK ADJUSTMENT AUDIT LOG -->
                        <section class="mt-8 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm">
                            <div class="border-b border-[#E5E7EB] p-5">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                                            </svg>
                                        </div>

                                        <div>
                                            <div class="flex items-center gap-2">
                                                <h2 class="text-base font-bold text-[#1F2937]">Stock Adjustment Audit Log</h2>
                                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">
                                                    {{ adjustments?.total ?? adjustments?.data?.length ?? 0 }} entries
                                                </span>
                                            </div>
                                            <p class="mt-0.5 text-xs text-[#64748B]">
                                                Audit trail tracking stock-ins, stock-outs, damages, returns, and manual adjustments with notes.
                                            </p>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        @click="openAdjustmentModal('stock_in')"
                                        class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-xl bg-[#087F8C] px-3.5 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-[#066B76]"
                                    >
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                        </svg>
                                        + Log Adjustment
                                    </button>
                                </div>

                                <!-- Filter Pills -->
                                <div class="mt-4 flex flex-wrap items-center gap-1.5 border-t border-[#F1F5F9] pt-3">
                                    <span class="mr-1 text-xs font-medium text-[#64748B]">Type:</span>
                                    <button
                                        v-for="item in adjustmentTypes"
                                        :key="item.key"
                                        type="button"
                                        @click="filterAdjustments(item.key)"
                                        class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-semibold transition"
                                        :class="activeTypeFilter === item.key ? 'bg-[#087F8C] text-white shadow-xs' : 'bg-[#F8FAF9] text-[#64748B] hover:bg-[#E2E8F0] hover:text-[#1E293B]'"
                                    >
                                        {{ item.label }}
                                    </button>
                                </div>
                            </div>

                            <!-- Table of Adjustments -->
                            <div class="overflow-x-auto">
                                <table v-if="adjustments?.data?.length" class="w-full text-left text-xs">
                                    <thead class="border-b border-[#E5E7EB] bg-[#F8FAF9] text-[11px] font-bold uppercase tracking-wider text-[#64748B]">
                                        <tr>
                                            <th scope="col" class="px-4 py-3">Date & Time</th>
                                            <th scope="col" class="px-4 py-3">Type</th>
                                            <th scope="col" class="px-4 py-3">Target</th>
                                            <th scope="col" class="px-4 py-3 text-right">Quantity</th>
                                            <th scope="col" class="px-4 py-3 text-center">Stock Level</th>
                                            <th scope="col" class="px-4 py-3">Notes & Reference</th>
                                            <th scope="col" class="px-4 py-3">Logged By</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-[#F1F5F9] bg-white">
                                        <tr v-for="adj in adjustments.data" :key="adj.id" class="transition hover:bg-[#FBFDFD]">
                                            <td class="whitespace-nowrap px-4 py-3.5 text-[#64748B]">
                                                {{ formatDate(adj.created_at) }}
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3.5">
                                                <span
                                                    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                                                    :class="getAdjustmentBadge(adj.type).class"
                                                >
                                                    <span class="h-1.5 w-1.5 rounded-full" :class="getAdjustmentBadge(adj.type).dot"></span>
                                                    {{ getAdjustmentBadge(adj.type).label }}
                                                </span>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3.5 font-medium text-[#1F2937]">
                                                <div v-if="adj.variant">
                                                    {{ [adj.variant.color, adj.variant.size].filter(Boolean).join(' / ') || 'Variant' }}
                                                </div>
                                                <div v-else class="text-[#94A3B8]">
                                                    Base Product
                                                </div>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3.5 text-right font-bold">
                                                <span v-if="adj.quantity > 0" class="text-emerald-600">
                                                    +{{ adj.quantity }}
                                                </span>
                                                <span v-else-if="adj.quantity < 0" class="text-rose-600">
                                                    {{ adj.quantity }}
                                                </span>
                                                <span v-else class="text-slate-500">
                                                    0
                                                </span>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3.5 text-center text-[#475569]">
                                                <div class="inline-flex items-center gap-1.5 font-semibold">
                                                    <span class="text-[#94A3B8]">{{ adj.before_stock }}</span>
                                                    <svg class="h-3 w-3 text-slate-400" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                                                    </svg>
                                                    <span class="text-[#1F2937]">{{ adj.after_stock }}</span>
                                                </div>
                                            </td>
                                            <td class="max-w-xs px-4 py-3.5">
                                                <div v-if="adj.reference_number" class="mb-0.5">
                                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-700">
                                                        Ref: {{ adj.reference_number }}
                                                    </span>
                                                </div>
                                                <p v-if="adj.notes" class="truncate text-[#475569]" :title="adj.notes">
                                                    {{ adj.notes }}
                                                </p>
                                                <span v-else class="italic text-[#CBD5E1]">No notes</span>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3.5 text-[#64748B]">
                                                <div class="flex items-center gap-1.5">
                                                    <div class="flex h-5 w-5 items-center justify-center rounded-full bg-[#E8F7F6] text-[10px] font-bold text-[#087F8C]">
                                                        {{ adj.user?.name ? adj.user.name.charAt(0).toUpperCase() : 'S' }}
                                                    </div>
                                                    <span class="truncate max-w-[120px]">{{ adj.user?.name ?? 'System' }}</span>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                <!-- Empty State -->
                                <div v-else class="px-6 py-12 text-center">
                                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-[#E8F7F6] text-[#087F8C]">
                                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                        </svg>
                                    </div>
                                    <h3 class="mt-3 text-sm font-bold text-[#1F2937]">No stock adjustment logs found</h3>
                                    <p class="mx-auto mt-1 max-w-sm text-xs leading-5 text-[#64748B]">
                                        {{ activeTypeFilter === 'all' ? 'No stock adjustments have been recorded for this product yet.' : `No adjustments found for category '${activeTypeFilter}'.` }}
                                    </p>
                                    <button
                                        type="button"
                                        @click="openAdjustmentModal('stock_in')"
                                        class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-[#087F8C] px-3.5 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-[#066B76]"
                                    >
                                        Log Stock Adjustment
                                    </button>
                                </div>
                            </div>

                            <!-- Pagination -->
                            <div v-if="adjustments?.links && adjustments.links.length > 3" class="flex flex-col gap-3 border-t border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="text-xs text-[#64748B]">
                                    Showing <span class="font-semibold text-[#1F2937]">{{ adjustments.from ?? 0 }}</span> to <span class="font-semibold text-[#1F2937]">{{ adjustments.to ?? 0 }}</span> of <span class="font-semibold text-[#1F2937]">{{ adjustments.total ?? 0 }}</span> records
                                </div>
                                <div class="flex flex-wrap gap-1">
                                    <Link
                                        v-for="(link, i) in adjustments.links"
                                        :key="i"
                                        :href="link.url || '#'"
                                        v-html="link.label"
                                        class="rounded-lg px-2.5 py-1 text-xs font-semibold transition"
                                        :class="[
                                            link.active ? 'bg-[#087F8C] text-white shadow-xs' : 'bg-white text-[#475569] border border-[#E5E7EB] hover:bg-[#F1F5F9]',
                                            !link.url ? 'pointer-events-none opacity-40' : ''
                                        ]"
                                    />
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>

            <!-- LOG STOCK ADJUSTMENT MODAL -->
            <div
                v-if="adjustmentModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
            >
                <!-- Backdrop -->
                <div
                    class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
                    @click="adjustmentModalOpen = false"
                ></div>

                <!-- Modal Dialog -->
                <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl border border-slate-100 overflow-hidden z-10 animate-in fade-in zoom-in-95 duration-150">
                    <!-- Modal Header -->
                    <div class="border-b border-[#E5E7EB] px-6 py-4 flex items-center justify-between bg-white">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-[#1F2937]">Log Stock Adjustment</h3>
                                <p class="text-xs text-[#64748B]">Record inventory movements and audit reason</p>
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="adjustmentModalOpen = false"
                            class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Body / Form -->
                    <form @submit.prevent="submitAdjustment" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                        <!-- Adjustment Type -->
                        <div>
                            <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-[#475569]">
                                Adjustment Type <span class="text-rose-500">*</span>
                            </label>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                <button
                                    type="button"
                                    @click="adjustmentForm.type = 'stock_in'"
                                    class="flex flex-col items-start rounded-xl border p-2.5 text-left transition"
                                    :class="adjustmentForm.type === 'stock_in' ? 'border-emerald-500 bg-emerald-50 text-emerald-800 ring-2 ring-emerald-500/20' : 'border-[#E5E7EB] hover:bg-slate-50 text-slate-700'"
                                >
                                    <span class="text-xs font-bold">Stock In</span>
                                    <span class="text-[10px] text-slate-500">New arrivals / PO</span>
                                </button>

                                <button
                                    type="button"
                                    @click="adjustmentForm.type = 'stock_out'"
                                    class="flex flex-col items-start rounded-xl border p-2.5 text-left transition"
                                    :class="adjustmentForm.type === 'stock_out' ? 'border-rose-500 bg-rose-50 text-rose-800 ring-2 ring-rose-500/20' : 'border-[#E5E7EB] hover:bg-slate-50 text-slate-700'"
                                >
                                    <span class="text-xs font-bold">Stock Out</span>
                                    <span class="text-[10px] text-slate-500">Removal / transfer</span>
                                </button>

                                <button
                                    type="button"
                                    @click="adjustmentForm.type = 'damage'"
                                    class="flex flex-col items-start rounded-xl border p-2.5 text-left transition"
                                    :class="adjustmentForm.type === 'damage' ? 'border-red-500 bg-red-50 text-red-800 ring-2 ring-red-500/20' : 'border-[#E5E7EB] hover:bg-slate-50 text-slate-700'"
                                >
                                    <span class="text-xs font-bold">Damages</span>
                                    <span class="text-[10px] text-slate-500">Defective / broken</span>
                                </button>

                                <button
                                    type="button"
                                    @click="adjustmentForm.type = 'return'"
                                    class="flex flex-col items-start rounded-xl border p-2.5 text-left transition"
                                    :class="adjustmentForm.type === 'return' ? 'border-blue-500 bg-blue-50 text-blue-800 ring-2 ring-blue-500/20' : 'border-[#E5E7EB] hover:bg-slate-50 text-slate-700'"
                                >
                                    <span class="text-xs font-bold">Returns</span>
                                    <span class="text-[10px] text-slate-500">Customer return</span>
                                </button>

                                <button
                                    type="button"
                                    @click="adjustmentForm.type = 'manual'"
                                    class="col-span-2 sm:col-span-2 flex flex-col items-start rounded-xl border p-2.5 text-left transition"
                                    :class="adjustmentForm.type === 'manual' ? 'border-amber-500 bg-amber-50 text-amber-800 ring-2 ring-amber-500/20' : 'border-[#E5E7EB] hover:bg-slate-50 text-slate-700'"
                                >
                                    <span class="text-xs font-bold">Manual Adjustment</span>
                                    <span class="text-[10px] text-slate-500">Inventory recount / audit discrepancy</span>
                                </button>
                            </div>
                            <p v-if="adjustmentForm.errors.type" class="mt-1 text-xs text-rose-500">
                                {{ adjustmentForm.errors.type }}
                            </p>
                        </div>

                        <!-- Variant Selector (if product has variants) -->
                        <div v-if="hasVariants">
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#475569]">
                                Select Variant <span class="text-rose-500">*</span>
                            </label>
                            <select
                                v-model="adjustmentForm.product_variant_id"
                                class="w-full rounded-xl border border-[#E5E7EB] bg-white px-3 py-2.5 text-sm font-semibold text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10"
                            >
                                <option
                                    v-for="v in product.variants"
                                    :key="v.id"
                                    :value="v.id"
                                >
                                    {{ [v.color, v.size].filter(Boolean).join(' / ') || 'Variant' }} (Current Stock: {{ v.stock }})
                                </option>
                            </select>
                            <p v-if="adjustmentForm.errors.product_variant_id" class="mt-1 text-xs text-rose-500">
                                {{ adjustmentForm.errors.product_variant_id }}
                            </p>
                        </div>

                        <!-- Manual Adjustment Mode Toggle -->
                        <div v-if="adjustmentForm.type === 'manual'">
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#475569]">
                                Mode
                            </label>
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    type="button"
                                    @click="adjustmentForm.mode = 'add'"
                                    class="rounded-xl border py-2 text-xs font-semibold transition"
                                    :class="adjustmentForm.mode === 'add' ? 'border-[#087F8C] bg-[#E8F7F6] text-[#087F8C]' : 'border-[#E5E7EB] text-slate-600 hover:bg-slate-50'"
                                >
                                    + Add Stock
                                </button>
                                <button
                                    type="button"
                                    @click="adjustmentForm.mode = 'subtract'"
                                    class="rounded-xl border py-2 text-xs font-semibold transition"
                                    :class="adjustmentForm.mode === 'subtract' ? 'border-[#087F8C] bg-[#E8F7F6] text-[#087F8C]' : 'border-[#E5E7EB] text-slate-600 hover:bg-slate-50'"
                                >
                                    - Deduct Stock
                                </button>
                                <button
                                    type="button"
                                    @click="adjustmentForm.mode = 'set'"
                                    class="rounded-xl border py-2 text-xs font-semibold transition"
                                    :class="adjustmentForm.mode === 'set' ? 'border-[#087F8C] bg-[#E8F7F6] text-[#087F8C]' : 'border-[#E5E7EB] text-slate-600 hover:bg-slate-50'"
                                >
                                    = Set to Exact
                                </button>
                            </div>
                        </div>

                        <!-- Quantity -->
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#475569]">
                                {{ adjustmentForm.mode === 'set' && adjustmentForm.type === 'manual' ? 'New Target Stock Level' : 'Quantity' }} <span class="text-rose-500">*</span>
                            </label>
                            <input
                                v-model.number="adjustmentForm.quantity"
                                type="number"
                                :min="adjustmentForm.mode === 'set' ? 0 : 1" max="99999" step="1"
                                class="w-full rounded-xl border border-[#E5E7EB] bg-white px-3 py-2.5 text-sm font-semibold text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10"
                                required
                            />
                            <p v-if="adjustmentForm.errors.quantity" class="mt-1 text-xs text-rose-500">
                                {{ adjustmentForm.errors.quantity }}
                            </p>
                        </div>

                        <!-- Live Stock Change Preview Card -->
                        <div class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-3.5">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-slate-500">Current Stock:</span>
                                <span class="font-bold text-slate-800">{{ selectedVariantCurrentStock }}</span>
                            </div>
                            <div class="mt-1 flex items-center justify-between text-xs">
                                <span class="text-slate-500">Projected Result:</span>
                                <div class="inline-flex items-center gap-1.5">
                                    <span class="font-bold text-slate-800">{{ previewNewStock }}</span>
                                    <span
                                        class="rounded-md px-1.5 py-0.5 text-[10px] font-bold"
                                        :class="previewNewStock > selectedVariantCurrentStock ? 'bg-emerald-100 text-emerald-800' : (previewNewStock < selectedVariantCurrentStock ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-600')"
                                    >
                                        {{ previewNewStock >= selectedVariantCurrentStock ? '+' : '' }}{{ previewNewStock - selectedVariantCurrentStock }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Reference Number (Optional) -->
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#475569]">
                                Reference # <span class="text-slate-400 font-normal lowercase">(optional)</span>
                            </label>
                            <input
                                v-model="adjustmentForm.reference_number"
                                type="text"
                                placeholder="e.g. PO-98421, RMA-402, AUDIT-OCT"
                                class="w-full rounded-xl border border-[#E5E7EB] bg-white px-3 py-2.5 text-sm text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10"
                            />
                            <p v-if="adjustmentForm.errors.reference_number" class="mt-1 text-xs text-rose-500">
                                {{ adjustmentForm.errors.reference_number }}
                            </p>
                        </div>

                        <!-- Notes & Reason -->
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#475569]">
                                Notes / Reason <span class="text-slate-400 font-normal lowercase">(recommended)</span>
                            </label>
                            <textarea
                                v-model="adjustmentForm.notes"
                                rows="3"
                                placeholder="Explain why this adjustment is being made (e.g. received new shipment, box damaged in warehouse, returned by buyer in good condition)..."
                                class="w-full rounded-xl border border-[#E5E7EB] bg-white px-3 py-2.5 text-sm text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10"
                            ></textarea>
                            <p v-if="adjustmentForm.errors.notes" class="mt-1 text-xs text-rose-500">
                                {{ adjustmentForm.errors.notes }}
                            </p>
                        </div>

                        <!-- Modal Actions -->
                        <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-[#E5E7EB]">
                            <button
                                type="button"
                                @click="adjustmentModalOpen = false"
                                class="rounded-xl border border-[#E5E7EB] px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition"
                            >
                                Cancel
                            </button>

                            <button
                                type="submit"
                                :disabled="adjustmentForm.processing"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-[#087F8C] px-5 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#066B76] disabled:opacity-50"
                            >
                                <svg
                                    v-if="adjustmentForm.processing"
                                    class="h-3.5 w-3.5 animate-spin"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.5"
                                >
                                    <circle cx="12" cy="12" r="9" class="opacity-25" />
                                    <path d="M21 12a9 9 0 0 1-9 9" stroke-linecap="round" />
                                </svg>
                                <span>{{ adjustmentForm.processing ? 'Recording...' : 'Save Adjustment' }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </SellerLayout>
</template>