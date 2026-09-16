<script setup>
import { ref, computed, onBeforeUnmount } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
})

const form = useForm({
    name: '',
    category_id: '',
    price: '',
    old_price: '',
    stock: '',
    description: '',
    status: 'pending',
    images: [],
    variants: [],
})

const imagePreviews = ref([])

const colors = [
    'Black',
    'White',
    'Red',
    'Blue',
    'Green',
    'Yellow',
    'Pink',
    'Purple',
    'Orange',
    'Brown',
    'Gray',
    'Beige',
    'Navy',
    'Maroon',
    'Gold',
    'Silver',
    'Other',
]

const sizes = [
    'XS',
    'S',
    'M',
    'L',
    'XL',
    '2XL',
    '3XL',
    '4XL',
    '5XL',
    'Free Size',
    'Other',
]

const totalVariantStock = computed(() => {
    return form.variants.reduce((total, variant) => {
        return total + (Number(variant.stock) || 0)
    }, 0)
})

const discountPercentage = computed(() => {
    const oldPrice = Number(form.old_price)
    const price = Number(form.price)

    if (
        !oldPrice ||
        !price ||
        oldPrice <= 0 ||
        price <= 0 ||
        oldPrice <= price
    ) {
        return 0
    }

    return Math.round(((oldPrice - price) / oldPrice) * 100)
})

const discountAmount = computed(() => {
    const oldPrice = Number(form.old_price)
    const price = Number(form.price)

    if (!oldPrice || !price || oldPrice <= price) {
        return 0
    }

    return oldPrice - price
})

const pricingError = computed(() => {
    const price = Number(form.price)
    const oldPrice = Number(form.old_price)

    if (form.price !== '' && price <= 0) {
        return 'Selling price must be greater than ₱0.00.'
    }

    if (form.old_price !== '') {
        if (oldPrice <= 0) {
            return 'Original price must be greater than ₱0.00.'
        }

        if (oldPrice <= price) {
            return 'Original price must be greater than the selling price.'
        }
    }

    return ''
})

const handleImageChange = (event) => {
    const files = Array.from(event.target.files || [])

    if (!files.length) {
        return
    }

    const allowedTypes = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ]

    const maxFileSize = 5 * 1024 * 1024

    files.forEach((file) => {
        if (!allowedTypes.includes(file.type)) {
            return
        }

        if (file.size > maxFileSize) {
            return
        }

        if (form.images.length >= 10) {
            return
        }

        const alreadyAdded = form.images.some(
            (existingFile) =>
                existingFile.name === file.name &&
                existingFile.size === file.size &&
                existingFile.lastModified === file.lastModified
        )

        if (alreadyAdded) {
            return
        }

        form.images.push(file)

        imagePreviews.value.push({
            file,
            url: URL.createObjectURL(file),
        })
    })

    event.target.value = ''
}

const removeImage = (index) => {
    const preview = imagePreviews.value[index]

    if (preview?.url) {
        URL.revokeObjectURL(preview.url)
    }

    form.images.splice(index, 1)
    imagePreviews.value.splice(index, 1)
}

const removeAllImages = () => {
    imagePreviews.value.forEach((preview) => {
        if (preview.url) {
            URL.revokeObjectURL(preview.url)
        }
    })

    form.images = []
    imagePreviews.value = []

    const input = document.getElementById('product-images')

    if (input) {
        input.value = ''
    }
}

const addVariant = () => {
    form.variants.push({
        color: '',
        size: '',
        stock: 0,
    })
}

const removeVariant = (index) => {
    form.variants.splice(index, 1)
}

const submit = (status = form.status) => {
    form.status = status

    if (pricingError.value) {
        return
    }

    if (form.variants.length > 0) {
        form.stock = totalVariantStock.value
    }

    form.post(route('seller.products.store'), {
        forceFormData: true,
        preserveScroll: true,
    })
}

onBeforeUnmount(() => {
    imagePreviews.value.forEach((preview) => {
        if (preview.url) {
            URL.revokeObjectURL(preview.url)
        }
    })
})
</script>

<template>
    <Head title="Add Product" />

    <SellerLayout>
        <div class="min-h-[calc(100vh-68px)] bg-[#F8FAF9] pb-20">
            <div class="mx-auto max-w-5xl px-4 py-5 sm:px-6 lg:px-8">

                <!-- PAGE HEADER -->
                <div
                    class="flex flex-col gap-4 border-b border-[#E5E7EB] pb-5 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div>
                        <p
                            class="text-xs font-bold uppercase tracking-[0.16em] text-[#087F8C]"
                        >
                            Seller Center
                        </p>

                        <h1
                            class="mt-1 text-2xl font-bold tracking-tight text-[#1F2937] sm:text-3xl"
                        >
                            Add Product
                        </h1>

                        <p class="mt-1 text-sm text-[#64748B]">
                            Create a new product listing for your store.
                        </p>
                    </div>

                    <Link
                        :href="route('seller.products')"
                        class="inline-flex w-fit items-center gap-2 rounded-xl border border-[#E5E7EB] bg-white px-4 py-2.5 text-xs font-semibold text-[#475569] shadow-sm transition hover:border-[#087F8C] hover:text-[#087F8C]"
                    >
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                d="M15 18l-6-6 6-6"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>

                        Back to Products
                    </Link>
                </div>

                <form
                    class="mt-5 space-y-5"
                    enctype="multipart/form-data"
                    @submit.prevent="submit()"
                >

                    <!-- ================================================= -->
                    <!-- BASIC INFORMATION -->
                    <!-- ================================================= -->

                    <section
                        class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                    >
                        <div class="border-b border-[#E5E7EB] px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                                >
                                    <svg
                                        class="h-5 w-5"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.7"
                                    >
                                        <path
                                            d="M4 7h16v13H4z"
                                            stroke-linejoin="round"
                                        />
                                        <path
                                            d="M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2"
                                            stroke-linecap="round"
                                        />
                                        <path
                                            d="M8 12h8M8 16h5"
                                            stroke-linecap="round"
                                        />
                                    </svg>
                                </div>

                                <div>
                                    <h2
                                        class="text-base font-bold text-[#1F2937]"
                                    >
                                        Basic Information
                                    </h2>

                                    <p
                                        class="mt-0.5 text-xs text-[#64748B]"
                                    >
                                        Provide the basic details of your product.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-5 px-5 py-5">

                            <!-- PRODUCT NAME -->
                            <div>
                                <label
                                    for="name"
                                    class="block text-xs font-semibold text-[#1F2937]"
                                >
                                    Product Name
                                </label>

                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    placeholder="Enter product name"
                                    class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                />

                                <p
                                    v-if="form.errors.name"
                                    class="mt-1.5 text-xs text-[#E85D5D]"
                                >
                                    {{ form.errors.name }}
                                </p>
                            </div>

                            <!-- CATEGORY -->
                            <div>
                                <label
                                    for="category"
                                    class="block text-xs font-semibold text-[#1F2937]"
                                >
                                    Category
                                </label>

                                <select
                                    id="category"
                                    v-model="form.category_id"
                                    class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                >
                                    <option value="">
                                        Select category
                                    </option>

                                    <option
                                        v-for="category in props.categories"
                                        :key="category.id"
                                        :value="category.id"
                                    >
                                        {{ category.name }}
                                    </option>
                                </select>

                                <p
                                    v-if="form.errors.category_id"
                                    class="mt-1.5 text-xs text-[#E85D5D]"
                                >
                                    {{ form.errors.category_id }}
                                </p>
                            </div>

                            <!-- PRICING -->
                            <div>
                                <div class="mb-4">
                                    <h3
                                        class="text-sm font-bold text-[#1F2937]"
                                    >
                                        Product Pricing
                                    </h3>

                                    <p
                                        class="mt-0.5 text-[11px] text-[#64748B]"
                                    >
                                        Set the regular price and optional discounted
                                        selling price.
                                    </p>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">

                                    <!-- ORIGINAL PRICE -->
                                    <div>
                                        <label
                                            for="old_price"
                                            class="block text-xs font-semibold text-[#1F2937]"
                                        >
                                            Original Price
                                            <span class="font-normal text-[#94A3B8]">
                                                (Optional)
                                            </span>
                                        </label>

                                        <div class="relative mt-2">
                                            <span
                                                class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-semibold text-[#64748B]"
                                            >
                                                ₱
                                            </span>

                                            <input
                                                id="old_price"
                                                v-model="form.old_price"
                                                type="number"
                                                min="0.01"
                                                step="0.01"
                                                placeholder="0.00"
                                                class="block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 pl-9 text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                            />
                                        </div>

                                        <p class="mt-1.5 text-[11px] text-[#94A3B8]">
                                            Use this when the product is discounted.
                                        </p>

                                        <p
                                            v-if="form.errors.old_price"
                                            class="mt-1.5 text-xs text-[#E85D5D]"
                                        >
                                            {{ form.errors.old_price }}
                                        </p>
                                    </div>

                                    <!-- SELLING PRICE -->
                                    <div>
                                        <label
                                            for="price"
                                            class="block text-xs font-semibold text-[#1F2937]"
                                        >
                                            Selling Price
                                        </label>

                                        <div class="relative mt-2">
                                            <span
                                                class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-semibold text-[#64748B]"
                                            >
                                                ₱
                                            </span>

                                            <input
                                                id="price"
                                                v-model="form.price"
                                                type="number"
                                                min="0.01"
                                                step="0.01"
                                                placeholder="0.00"
                                                class="block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 pl-9 text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                            />
                                        </div>

                                        <p class="mt-1.5 text-[11px] text-[#94A3B8]">
                                            The current price customers will pay.
                                        </p>

                                        <p
                                            v-if="form.errors.price"
                                            class="mt-1.5 text-xs text-[#E85D5D]"
                                        >
                                            {{ form.errors.price }}
                                        </p>
                                    </div>
                                </div>

                                <!-- PRICING ERROR -->
                                <div
                                    v-if="pricingError"
                                    class="mt-4 flex items-start gap-3 rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3"
                                >
                                    <svg
                                        class="mt-0.5 h-4 w-4 shrink-0 text-[#E85D5D]"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <circle
                                            cx="12"
                                            cy="12"
                                            r="9"
                                        />
                                        <path
                                            d="M12 8v4M12 16h.01"
                                            stroke-linecap="round"
                                        />
                                    </svg>

                                    <p class="text-xs font-semibold text-[#DC2626]">
                                        {{ pricingError }}
                                    </p>
                                </div>

                                <!-- DISCOUNT PREVIEW -->
                                <div
                                    v-if="discountPercentage > 0"
                                    class="mt-4 rounded-xl border border-[#BBE7D3] bg-[#ECFDF5] p-4"
                                >
                                    <div
                                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span
                                                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-white text-[#22A06B]"
                                                >
                                                    <svg
                                                        class="h-4 w-4"
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        stroke-width="1.8"
                                                    >
                                                        <path
                                                            d="M20 12V7a2 2 0 00-2-2h-5L4 14l6 6 9-9a2 2 0 001-1z"
                                                            stroke-linecap="round"
                                                            stroke-linejoin="round"
                                                        />
                                                        <circle
                                                            cx="14.5"
                                                            cy="8.5"
                                                            r="1"
                                                            fill="currentColor"
                                                            stroke="none"
                                                        />
                                                    </svg>
                                                </span>

                                                <p class="text-xs font-bold text-[#16845A]">
                                                    Discount Applied
                                                </p>
                                            </div>

                                            <p class="mt-1 text-[11px] text-[#16845A]">
                                                Customers will see the discounted
                                                selling price.
                                            </p>
                                        </div>

                                        <div class="text-left sm:text-right">
                                            <div
                                                class="text-xl font-bold tracking-tight text-[#16845A]"
                                            >
                                                {{ discountPercentage }}% OFF
                                            </div>

                                            <p class="text-[11px] text-[#22A06B]">
                                                Save ₱{{ discountAmount.toFixed(2) }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="mt-3 flex items-center gap-3">
                                        <span
                                            class="text-sm text-[#94A3B8] line-through"
                                        >
                                            ₱{{ Number(form.old_price).toFixed(2) }}
                                        </span>

                                        <span
                                            class="text-base font-bold text-[#16845A]"
                                        >
                                            ₱{{ Number(form.price).toFixed(2) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- STOCK -->
                            <div>
                                <label
                                    for="stock"
                                    class="block text-xs font-semibold text-[#1F2937]"
                                >
                                    Stock Quantity
                                </label>

                                <div
                                    v-if="form.variants.length > 0"
                                    class="mt-2 rounded-xl border border-[#BFE3E0] bg-[#E8F7F6] px-4 py-3"
                                >
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-xs font-bold text-[#087F8C]">
                                                Total Variant Stock
                                            </p>

                                            <p class="mt-0.5 text-[10px] text-[#16845A]">
                                                Automatically calculated from variants.
                                            </p>
                                        </div>

                                        <span
                                            class="text-xl font-bold tracking-tight text-[#087F8C]"
                                        >
                                            {{ totalVariantStock }}
                                        </span>
                                    </div>
                                </div>

                                <input
                                    v-else
                                    id="stock"
                                    v-model="form.stock"
                                    type="number"
                                    min="0"
                                    placeholder="0"
                                    class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                />

                                <p
                                    v-if="form.errors.stock"
                                    class="mt-1.5 text-xs text-[#E85D5D]"
                                >
                                    {{ form.errors.stock }}
                                </p>
                            </div>

                            <!-- DESCRIPTION -->
                            <div>
                                <label
                                    for="description"
                                    class="block text-xs font-semibold text-[#1F2937]"
                                >
                                    Product Description
                                </label>

                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="5"
                                    placeholder="Describe your product..."
                                    class="mt-2 block w-full resize-none rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                ></textarea>

                                <p
                                    v-if="form.errors.description"
                                    class="mt-1.5 text-xs text-[#E85D5D]"
                                >
                                    {{ form.errors.description }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- ================================================= -->
                    <!-- PRODUCT VARIANTS -->
                    <!-- ================================================= -->

                    <section
                        class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                    >
                        <div class="border-b border-[#E5E7EB] px-5 py-4">
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#FFF8E7] text-[#B77900]"
                                    >
                                        <svg
                                            class="h-5 w-5"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.7"
                                        >
                                            <path
                                                d="M12 3l8 4.5v9L12 21l-8-4.5v-9L12 3z"
                                                stroke-linejoin="round"
                                            />
                                            <path
                                                d="M4 7.5l8 4.5 8-4.5M12 12v9"
                                                stroke-linejoin="round"
                                            />
                                        </svg>
                                    </div>

                                    <div>
                                        <h2 class="text-base font-bold text-[#1F2937]">
                                            Product Variants
                                        </h2>

                                        <p class="mt-0.5 text-xs text-[#64748B]">
                                            Optional. Add color, size, and stock combinations.
                                        </p>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    @click="addVariant"
                                    class="inline-flex w-fit items-center justify-center gap-2 rounded-xl bg-[#087F8C] px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#066D77]"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path
                                            d="M12 5v14M5 12h14"
                                            stroke-linecap="round"
                                        />
                                    </svg>

                                    Add Variant
                                </button>
                            </div>
                        </div>

                        <div class="px-5 py-5">

                            <!-- EMPTY STATE -->
                            <div
                                v-if="form.variants.length === 0"
                                class="rounded-xl border border-dashed border-[#CBD5E1] bg-[#F8FAF9] px-6 py-10 text-center"
                            >
                                <div
                                    class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                                >
                                    <svg
                                        class="h-6 w-6"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.7"
                                    >
                                        <path
                                            d="M12 3l8 4.5v9L12 21l-8-4.5v-9L12 3z"
                                            stroke-linejoin="round"
                                        />
                                        <path
                                            d="M4 7.5l8 4.5 8-4.5M12 12v9"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                </div>

                                <h3
                                    class="mt-3 text-sm font-bold text-[#1F2937]"
                                >
                                    No variants added
                                </h3>

                                <p
                                    class="mx-auto mt-1 max-w-md text-xs leading-5 text-[#64748B]"
                                >
                                    Add variants if your product has different
                                    colors, sizes, or stock combinations.
                                </p>

                                <button
                                    type="button"
                                    @click="addVariant"
                                    class="mt-4 inline-flex items-center gap-2 rounded-xl border border-[#BFE3E0] bg-white px-4 py-2.5 text-xs font-bold text-[#087F8C] transition hover:bg-[#E8F7F6]"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path
                                            d="M12 5v14M5 12h14"
                                            stroke-linecap="round"
                                        />
                                    </svg>

                                    Add Your First Variant
                                </button>
                            </div>

                            <!-- VARIANT LIST -->
                            <div
                                v-else
                                class="space-y-3"
                            >
                                <div
                                    v-for="(variant, index) in form.variants"
                                    :key="index"
                                    class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-4"
                                >
                                    <div
                                        class="mb-4 flex items-center justify-between gap-3"
                                    >
                                        <div>
                                            <p
                                                class="text-xs font-bold text-[#1F2937]"
                                            >
                                                Variant {{ index + 1 }}
                                            </p>

                                            <p
                                                class="mt-0.5 text-[10px] text-[#94A3B8]"
                                            >
                                                Set the color, size, and available stock.
                                            </p>
                                        </div>

                                        <button
                                            type="button"
                                            @click="removeVariant(index)"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-[#FECACA] bg-white px-3 py-2 text-[10px] font-bold text-[#E85D5D] transition hover:bg-[#FEF2F2]"
                                        >
                                            <svg
                                                class="h-3.5 w-3.5"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <path
                                                    d="M4 7h16M10 11v6M14 11v6M6 7l1 14h10l1-14M9 7V4h6v3"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                />
                                            </svg>

                                            Remove
                                        </button>
                                    </div>

                                    <div class="grid gap-4 sm:grid-cols-3">

                                        <!-- COLOR -->
                                        <div>
                                            <label
                                                :for="`variant-color-${index}`"
                                                class="block text-[11px] font-semibold text-[#1F2937]"
                                            >
                                                Color
                                            </label>

                                            <select
                                                :id="`variant-color-${index}`"
                                                v-model="variant.color"
                                                class="mt-1.5 block w-full rounded-xl border border-[#E5E7EB] bg-white px-3 py-2.5 text-xs text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:ring-2 focus:ring-[#E8F7F6]"
                                            >
                                                <option value="">
                                                    Select Color
                                                </option>

                                                <option
                                                    v-for="color in colors"
                                                    :key="color"
                                                    :value="color"
                                                >
                                                    {{ color }}
                                                </option>
                                            </select>

                                            <p
                                                v-if="form.errors[`variants.${index}.color`]"
                                                class="mt-1.5 text-[10px] text-[#E85D5D]"
                                            >
                                                {{ form.errors[`variants.${index}.color`] }}
                                            </p>
                                        </div>

                                        <!-- SIZE -->
                                        <div>
                                            <label
                                                :for="`variant-size-${index}`"
                                                class="block text-[11px] font-semibold text-[#1F2937]"
                                            >
                                                Size
                                            </label>

                                            <select
                                                :id="`variant-size-${index}`"
                                                v-model="variant.size"
                                                class="mt-1.5 block w-full rounded-xl border border-[#E5E7EB] bg-white px-3 py-2.5 text-xs text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:ring-2 focus:ring-[#E8F7F6]"
                                            >
                                                <option value="">
                                                    Select Size
                                                </option>

                                                <option
                                                    v-for="size in sizes"
                                                    :key="size"
                                                    :value="size"
                                                >
                                                    {{ size }}
                                                </option>
                                            </select>

                                            <p
                                                v-if="form.errors[`variants.${index}.size`]"
                                                class="mt-1.5 text-[10px] text-[#E85D5D]"
                                            >
                                                {{ form.errors[`variants.${index}.size`] }}
                                            </p>
                                        </div>

                                        <!-- STOCK -->
                                        <div>
                                            <label
                                                :for="`variant-stock-${index}`"
                                                class="block text-[11px] font-semibold text-[#1F2937]"
                                            >
                                                Stock
                                            </label>

                                            <input
                                                :id="`variant-stock-${index}`"
                                                v-model.number="variant.stock"
                                                type="number"
                                                min="0"
                                                placeholder="0"
                                                class="mt-1.5 block w-full rounded-xl border border-[#E5E7EB] bg-white px-3 py-2.5 text-xs text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:ring-2 focus:ring-[#E8F7F6]"
                                            />

                                            <p
                                                v-if="form.errors[`variants.${index}.stock`]"
                                                class="mt-1.5 text-[10px] text-[#E85D5D]"
                                            >
                                                {{ form.errors[`variants.${index}.stock`] }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- TOTAL STOCK -->
                            <div
                                v-if="form.variants.length > 0"
                                class="mt-4 rounded-xl border border-[#BFE3E0] bg-[#E8F7F6] px-4 py-3"
                            >
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <p
                                            class="text-xs font-bold text-[#087F8C]"
                                        >
                                            Total Stock
                                        </p>

                                        <p
                                            class="mt-0.5 text-[10px] text-[#16845A]"
                                        >
                                            Automatically calculated from all variants.
                                        </p>
                                    </div>

                                    <span
                                        class="text-xl font-bold tracking-tight text-[#087F8C]"
                                    >
                                        {{ totalVariantStock }}
                                    </span>
                                </div>
                            </div>

                            <!-- VARIANT COUNT -->
                            <div
                                v-if="form.variants.length > 0"
                                class="mt-3 flex items-center justify-between rounded-xl border border-[#E5E7EB] bg-white px-4 py-3"
                            >
                                <span
                                    class="text-[11px] font-semibold text-[#64748B]"
                                >
                                    {{ form.variants.length }}
                                    variant{{ form.variants.length === 1 ? '' : 's' }}
                                    added
                                </span>

                                <span
                                    class="text-[10px] font-medium text-[#94A3B8]"
                                >
                                    Stock updates automatically
                                </span>
                            </div>

                            <p
                                v-if="form.errors.variants"
                                class="mt-3 text-xs text-[#E85D5D]"
                            >
                                {{ form.errors.variants }}
                            </p>
                        </div>
                    </section>

                    <!-- ================================================= -->
                    <!-- PRODUCT IMAGES -->
                    <!-- ================================================= -->

                    <section
                        class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                    >
                        <div class="border-b border-[#E5E7EB] px-5 py-4">
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#FFF8E7] text-[#B77900]"
                                    >
                                        <svg
                                            class="h-5 w-5"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.7"
                                        >
                                            <rect
                                                x="3"
                                                y="4"
                                                width="18"
                                                height="16"
                                                rx="2"
                                            />
                                            <circle
                                                cx="8.5"
                                                cy="9"
                                                r="1.5"
                                            />
                                            <path
                                                d="M21 15l-4.5-4.5L8 19"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            />
                                        </svg>
                                    </div>

                                    <div>
                                        <h2 class="text-base font-bold text-[#1F2937]">
                                            Product Images
                                        </h2>

                                        <p class="mt-0.5 text-xs text-[#64748B]">
                                            Add multiple clear photos of your product.
                                        </p>
                                    </div>
                                </div>

                                <span
                                    v-if="form.images.length > 0"
                                    class="inline-flex w-fit rounded-full bg-[#E8F7F6] px-2.5 py-1 text-[10px] font-bold text-[#087F8C]"
                                >
                                    {{ form.images.length }}/10 photos
                                </span>
                            </div>
                        </div>

                        <div class="px-5 py-5">

                            <!-- IMAGE PREVIEWS -->
                            <div v-if="imagePreviews.length > 0">
                                <div
                                    class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4"
                                >
                                    <div
                                        v-for="(preview, index) in imagePreviews"
                                        :key="`${preview.file.name}-${preview.file.lastModified}-${index}`"
                                        class="group relative overflow-hidden rounded-xl border border-[#E5E7EB] bg-[#F8FAF9]"
                                    >
                                        <img
                                            :src="preview.url"
                                            :alt="`Product image ${index + 1}`"
                                            class="h-36 w-full object-cover sm:h-40"
                                        />

                                        <!-- MAIN PHOTO -->
                                        <div
                                            v-if="index === 0"
                                            class="absolute left-2 top-2 rounded-full bg-[#087F8C] px-2.5 py-1 text-[9px] font-bold text-white"
                                        >
                                            MAIN PHOTO
                                        </div>

                                        <!-- REMOVE -->
                                        <button
                                            type="button"
                                            @click="removeImage(index)"
                                            class="absolute right-2 top-2 flex h-7 w-7 items-center justify-center rounded-full bg-white text-[#E85D5D] shadow-sm transition hover:bg-[#FEF2F2]"
                                            aria-label="Remove image"
                                        >
                                            <svg
                                                class="h-3.5 w-3.5"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path
                                                    d="M6 6l12 12M18 6L6 18"
                                                    stroke-linecap="round"
                                                />
                                            </svg>
                                        </button>

                                        <div class="border-t border-[#E5E7EB] px-3 py-2">
                                            <p
                                                class="truncate text-[10px] font-semibold text-[#475569]"
                                            >
                                                Photo {{ index + 1 }}
                                            </p>

                                            <p
                                                class="mt-0.5 truncate text-[9px] text-[#94A3B8]"
                                            >
                                                {{ preview.file.name }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- ADD PHOTO -->
                                    <label
                                        v-if="form.images.length < 10"
                                        for="product-images"
                                        class="flex min-h-[178px] cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-[#CBD5E1] bg-[#F8FAF9] px-4 text-center transition hover:border-[#087F8C] hover:bg-[#E8F7F6]"
                                    >
                                        <div
                                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-[#087F8C]"
                                        >
                                            <svg
                                                class="h-5 w-5"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <path
                                                    d="M12 5v14M5 12h14"
                                                    stroke-linecap="round"
                                                />
                                            </svg>
                                        </div>

                                        <p
                                            class="mt-2 text-xs font-bold text-[#475569]"
                                        >
                                            Add Another Photo
                                        </p>

                                        <p class="mt-1 text-[10px] text-[#94A3B8]">
                                            Select more images
                                        </p>
                                    </label>
                                </div>

                                <!-- IMAGE ACTIONS -->
                                <div class="mt-4 flex flex-wrap gap-2.5">
                                    <label
                                        v-if="form.images.length < 10"
                                        for="product-images"
                                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl bg-[#087F8C] px-4 py-2.5 text-[10px] font-bold text-white transition hover:bg-[#066D77]"
                                    >
                                        <svg
                                            class="h-3.5 w-3.5"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >
                                            <path
                                                d="M12 5v14M5 12h14"
                                                stroke-linecap="round"
                                            />
                                        </svg>

                                        Add More Photos
                                    </label>

                                    <button
                                        type="button"
                                        @click="removeAllImages"
                                        class="inline-flex items-center gap-1.5 rounded-xl border border-[#FECACA] bg-white px-4 py-2.5 text-[10px] font-bold text-[#E85D5D] transition hover:bg-[#FEF2F2]"
                                    >
                                        <svg
                                            class="h-3.5 w-3.5"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >
                                            <path
                                                d="M4 7h16M10 11v6M14 11v6M6 7l1 14h10l1-14M9 7V4h6v3"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            />
                                        </svg>

                                        Remove All
                                    </button>
                                </div>
                            </div>

                            <!-- EMPTY UPLOAD BOX -->
                            <label
                                v-else
                                for="product-images"
                                class="flex min-h-56 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-[#CBD5E1] bg-[#F8FAF9] px-6 text-center transition hover:border-[#087F8C] hover:bg-[#E8F7F6]"
                            >
                                <div
                                    class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-[#087F8C] shadow-sm"
                                >
                                    <svg
                                        class="h-7 w-7"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.6"
                                    >
                                        <rect
                                            x="3"
                                            y="4"
                                            width="18"
                                            height="16"
                                            rx="2"
                                        />
                                        <circle
                                            cx="8.5"
                                            cy="9"
                                            r="1.5"
                                        />
                                        <path
                                            d="M21 15l-4.5-4.5L8 19"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                </div>

                                <p
                                    class="mt-3 text-sm font-bold text-[#475569]"
                                >
                                    Click to upload product photos
                                </p>

                                <p
                                    class="mt-1 text-xs text-[#64748B]"
                                >
                                    You can select multiple photos at once.
                                </p>

                                <p
                                    class="mt-1 text-[10px] text-[#94A3B8]"
                                >
                                    JPG, PNG, or WebP · Maximum 5MB per image · Up to 10 photos
                                </p>

                                <span
                                    class="mt-4 inline-flex items-center gap-2 rounded-xl bg-[#087F8C] px-5 py-2.5 text-[10px] font-bold text-white"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path
                                            d="M12 5v14M5 12h14"
                                            stroke-linecap="round"
                                        />
                                    </svg>

                                    Choose Photos
                                </span>
                            </label>

                            <!-- FILE INPUT -->
                            <input
                                id="product-images"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                multiple
                                class="hidden"
                                @change="handleImageChange"
                            />

                            <p
                                v-if="form.errors.images"
                                class="mt-2 text-xs text-[#E85D5D]"
                            >
                                {{ form.errors.images }}
                            </p>

                            <p
                                v-if="form.errors['images.0']"
                                class="mt-1.5 text-xs text-[#E85D5D]"
                            >
                                {{ form.errors['images.0'] }}
                            </p>
                        </div>
                    </section>

                    <!-- ================================================= -->
                    <!-- LISTING STATUS -->
                    <!-- ================================================= -->

                    <section
                        class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                    >
                        <div class="border-b border-[#E5E7EB] px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                                >
                                    <svg
                                        class="h-5 w-5"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.7"
                                    >
                                        <path
                                            d="M12 3l7 4v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V7l7-4z"
                                            stroke-linejoin="round"
                                        />
                                        <path
                                            d="M9 12l2 2 4-4"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                </div>

                                <div>
                                    <h2 class="text-base font-bold text-[#1F2937]">
                                        Listing Status
                                    </h2>

                                    <p class="mt-0.5 text-xs text-[#64748B]">
                                        Choose how you want to save this product.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="px-5 py-5">
                            <label
                                for="status"
                                class="block text-xs font-semibold text-[#1F2937]"
                            >
                                Status
                            </label>

                            <select
                                id="status"
                                v-model="form.status"
                                class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                            >
                                <option value="pending">
                                    Pending - Submit for approval
                                </option>

                                <option value="draft">
                                    Draft - Save for later
                                </option>

                                <option value="inactive">
                                    Inactive
                                </option>
                            </select>

                            <p class="mt-2 text-[11px] leading-5 text-[#64748B]">
                                Draft products are saved without submitting them
                                for approval. Pending products are submitted to
                                the admin for review.
                            </p>

                            <p
                                v-if="form.errors.status"
                                class="mt-1.5 text-xs text-[#E85D5D]"
                            >
                                {{ form.errors.status }}
                            </p>
                        </div>
                    </section>

                    <!-- ================================================= -->
                    <!-- ACTION BAR -->
                    <!-- ================================================= -->

                    <div
                        class="rounded-2xl border border-[#E5E7EB] bg-white px-5 py-4 shadow-sm"
                    >
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <p
                                    class="text-xs font-bold text-[#1F2937]"
                                >
                                    Ready to list your product?
                                </p>

                                <p
                                    class="mt-0.5 text-[11px] text-[#94A3B8]"
                                >
                                    Save it as a draft or submit it for admin approval.
                                </p>
                            </div>

                            <div
                                class="flex flex-col-reverse gap-2.5 sm:flex-row"
                            >
                                <Link
                                    :href="route('seller.products')"
                                    class="inline-flex items-center justify-center rounded-xl border border-[#E5E7EB] bg-white px-5 py-2.5 text-xs font-bold text-[#64748B] transition hover:border-[#CBD5E1] hover:bg-[#F8FAF9]"
                                >
                                    Cancel
                                </Link>

                                <button
                                    type="button"
                                    @click="submit('draft')"
                                    :disabled="form.processing || !!pricingError"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-[#E5E7EB] bg-white px-5 py-2.5 text-xs font-bold text-[#475569] transition hover:border-[#087F8C] hover:bg-[#F8FAF9] hover:text-[#087F8C] disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <svg
                                        v-if="form.processing"
                                        class="h-4 w-4 animate-spin"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                    >
                                        <circle
                                            cx="12"
                                            cy="12"
                                            r="9"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            class="opacity-30"
                                        />

                                        <path
                                            d="M21 12a9 9 0 00-9-9"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                        />
                                    </svg>

                                    <svg
                                        v-else
                                        class="h-4 w-4"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path
                                            d="M5 5h11l3 3v11H5z"
                                            stroke-linejoin="round"
                                        />
                                        <path
                                            d="M8 5v5h7V5M8 19v-5h8v5"
                                            stroke-linejoin="round"
                                        />
                                    </svg>

                                    {{ form.processing ? 'Saving...' : 'Save as Draft' }}
                                </button>

                                <button
                                    type="submit"
                                    :disabled="form.processing || !!pricingError"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#087F8C] px-5 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#066D77] disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <svg
                                        v-if="form.processing"
                                        class="h-4 w-4 animate-spin"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                    >
                                        <circle
                                            cx="12"
                                            cy="12"
                                            r="9"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            class="opacity-30"
                                        />

                                        <path
                                            d="M21 12a9 9 0 00-9-9"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                        />
                                    </svg>

                                    <svg
                                        v-else
                                        class="h-4 w-4"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path
                                            d="M5 12h14"
                                            stroke-linecap="round"
                                        />

                                        <path
                                            d="M13 6l6 6-6 6"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>

                                    {{
                                        form.processing
                                            ? 'Submitting...'
                                            : 'Submit for Approval'
                                    }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- GENERAL ERROR -->
                    <div
                        v-if="form.errors && Object.keys(form.errors).length"
                        class="rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3"
                    >
                        <div class="flex items-start gap-3">
                            <svg
                                class="mt-0.5 h-4 w-4 shrink-0 text-[#E85D5D]"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <circle
                                    cx="12"
                                    cy="12"
                                    r="9"
                                />
                                <path
                                    d="M12 8v4M12 16h.01"
                                    stroke-linecap="round"
                                />
                            </svg>

                            <div>
                                <p
                                    class="text-xs font-bold text-[#DC2626]"
                                >
                                    Please check the highlighted fields.
                                </p>

                                <p
                                    class="mt-0.5 text-[11px] text-[#E85D5D]"
                                >
                                    Correct the errors above and try again.
                                </p>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </SellerLayout>
</template>