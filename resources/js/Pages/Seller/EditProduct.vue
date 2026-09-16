<!-- Seller form for editing an existing product listing. -->
<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },

    categories: {
        type: Array,
        default: () => [],
    },
})

const form = useForm({
    name: props.product.name || '',
    category_id: props.product.category_id ?? props.categories[0]?.id ?? '',

    // Pricing
    price: props.product.price ?? '',
    old_price: props.product.old_price ?? '',

    stock: props.product.stock ?? 0,
    description: props.product.description ?? '',
    status: props.product.status || 'pending',

    // New replacement image
    image: null,
})

/*
|--------------------------------------------------------------------------
| Existing Product Image
|--------------------------------------------------------------------------
*/

const existingImage = computed(() => {
    const path = props.product.image_path

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
| New Image Preview
|--------------------------------------------------------------------------
*/

const imagePreview = ref(null)

const handleImageChange = (event) => {
    const file = event.target.files?.[0]

    if (!file) {
        return
    }

    const allowedTypes = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ]

    const maxFileSize = 5 * 1024 * 1024

    if (!allowedTypes.includes(file.type)) {
        form.setError(
            'image',
            'The image must be a JPG, PNG, or WEBP file.'
        )

        event.target.value = ''
        return
    }

    if (file.size > maxFileSize) {
        form.setError(
            'image',
            'The image must not be larger than 5MB.'
        )

        event.target.value = ''
        return
    }

    form.clearErrors('image')
    form.image = file

    if (imagePreview.value) {
        URL.revokeObjectURL(imagePreview.value)
    }

    imagePreview.value = URL.createObjectURL(file)
}

const removeNewImage = () => {
    form.image = null
    form.clearErrors('image')

    if (imagePreview.value) {
        URL.revokeObjectURL(imagePreview.value)
        imagePreview.value = null
    }

    const input = document.getElementById('product-image')

    if (input) {
        input.value = ''
    }
}

const currentImage = computed(() => {
    return imagePreview.value || existingImage.value
})

onBeforeUnmount(() => {
    if (imagePreview.value) {
        URL.revokeObjectURL(imagePreview.value)
    }
})

/*
|--------------------------------------------------------------------------
| Pricing
|--------------------------------------------------------------------------
*/

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

    return Math.round(
        ((oldPrice - price) / oldPrice) * 100
    )
})

const discountAmount = computed(() => {
    const oldPrice = Number(form.old_price)
    const price = Number(form.price)

    if (
        !oldPrice ||
        !price ||
        oldPrice <= price
    ) {
        return 0
    }

    return oldPrice - price
})

/*
|--------------------------------------------------------------------------
| Pricing Validation
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

const statusOptions = [
    {
        label: 'Pending Review',
        value: 'pending',
    },
    {
        label: 'Draft',
        value: 'draft',
    },
    {
        label: 'Inactive',
        value: 'inactive',
    },
]

const statusBadgeClass = (status) => {
    const map = {
        pending:
            'border-[#FDE68A] bg-[#FFFBEB] text-[#B45309]',
        draft:
            'border-[#FDE68A] bg-[#FFF8E7] text-[#B77900]',
        inactive:
            'border-[#E5E7EB] bg-[#F8FAF9] text-[#64748B]',
        archived:
            'border-[#E5E7EB] bg-[#F8FAF9] text-[#64748B]',
    }

    return (
        map[status] ??
        'border-[#E5E7EB] bg-[#F8FAF9] text-[#64748B]'
    )
}            

const statusLabel = computed(() => {
    return (
        statusOptions.find(
            option => option.value === form.status
        )?.label ?? form.status
    )
})

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const selectedCategoryName = computed(() => {
    return (
        props.categories.find(
            category =>
                Number(category.id) === Number(form.category_id)
        )?.name ?? 'Category'
    )
})

const stockStatus = computed(() => {
    const stock = Number(form.stock ?? 0)

    if (stock === 0) {
        return {
            label: 'Out of Stock',
            class: 'border-[#FECACA] bg-[#FEF2F2] text-[#DC2626]',
        }
    }

    if (stock <= 5) {
        return {
            label: 'Low Stock',
            class: 'border-[#FDE68A] bg-[#FFFBEB] text-[#B45309]',
        }
    }

    return {
        label: 'In Stock',
        class: 'border-[#BBE7D3] bg-[#ECFDF5] text-[#16845A]',
    }
})

const peso = (value) => {
    return `₱${Number(value ?? 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`
}

/*
|--------------------------------------------------------------------------
| Submit
|--------------------------------------------------------------------------
*/

const submit = () => {
    if (pricingError.value) {
        return
    }

    /*
     * An equal old price and selling price is not a discount.
     */
    if (
        form.old_price !== '' &&
        Number(form.old_price) === Number(form.price)
    ) {
        form.old_price = ''
    }

    form
        .transform((data) => ({
            ...data,
            _method: 'PATCH',
        }))
        .post(
            route('seller.products.update', props.product.id),
            {
                forceFormData: true,
                preserveScroll: true,
            }
        )
}
</script>

<template>
    <Head title="Edit Product" />

    <SellerLayout>
        <div class="min-h-[calc(100vh-68px)] bg-[#F8FAF9] pb-20">
            <div class="mx-auto max-w-5xl px-4 py-5 sm:px-6 lg:px-8">

                <!-- ====================================================== -->
                <!-- HEADER -->
                <!-- ====================================================== -->

                <div
                    class="flex flex-col gap-4 border-b border-[#E5E7EB] pb-5 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div class="flex items-start gap-3">
                        <Link
                            :href="route('seller.products')"
                            class="mt-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-[#E5E7EB] bg-white text-[#64748B] shadow-sm transition hover:border-[#087F8C] hover:text-[#087F8C]"
                            aria-label="Back to products"
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
                        </Link>

                        <div>
                            <p
                                class="text-xs font-bold uppercase tracking-[0.16em] text-[#087F8C]"
                            >
                                Seller Center
                            </p>

                            <h1
                                class="mt-1 text-2xl font-bold tracking-tight text-[#1F2937] sm:text-3xl"
                            >
                                Edit Product
                            </h1>

                            <p class="mt-1 text-sm text-[#64748B]">
                                Update your product information, pricing,
                                stock, and image.
                            </p>
                        </div>
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
                                d="M4 7h16M4 12h16M4 17h10"
                                stroke-linecap="round"
                            />
                        </svg>
                        My Products
                    </Link>
                </div>


                <!-- ====================================================== -->
                <!-- PRODUCT SUMMARY -->
                <!-- ====================================================== -->

                <section
                    class="mt-5 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                >
                    <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:p-5">

                        <!-- IMAGE -->
                        <div
                            class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-[#E5E7EB] bg-[#F8FAF9]"
                        >
                            <img
                                v-if="currentImage"
                                :src="currentImage"
                                :alt="form.name"
                                class="h-full w-full object-cover"
                            />

                            <div
                                v-else
                                class="flex h-full w-full items-center justify-center bg-[#E8F7F6] text-[#087F8C]"
                            >
                                <svg
                                    class="h-8 w-8"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.6"
                                >
                                    <path
                                        d="M4 7h16v13H4z"
                                        stroke-linejoin="round"
                                    />
                                    <path
                                        d="M8 7l1.5-3h5L16 7M8 12h.01M12 12h.01M16 12h.01"
                                        stroke-linecap="round"
                                    />
                                </svg>
                            </div>
                        </div>


                        <!-- DETAILS -->
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2
                                    class="truncate text-base font-bold text-[#1F2937] sm:text-lg"
                                >
                                    {{ form.name || 'Untitled Product' }}
                                </h2>

                                <span
                                    :class="[
                                        'rounded-full border px-2.5 py-1 text-[10px] font-bold',
                                        statusBadgeClass(form.status),
                                    ]"
                                >
                                    {{ statusLabel }}
                                </span>
                            </div>

                            <p
                                class="mt-1 text-xs text-[#64748B]"
                            >
                                Product #{{ props.product.id }}
                                <span class="mx-1 text-[#CBD5E1]">•</span>
                                {{ selectedCategoryName }}
                            </p>

                            <div
                                class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2"
                            >
                                <div>
                                    <p
                                        class="text-[10px] font-semibold uppercase tracking-[0.08em] text-[#94A3B8]"
                                    >
                                        Current Price
                                    </p>

                                    <p
                                        class="mt-0.5 text-base font-bold tracking-tight text-[#1F2937]"
                                    >
                                        {{ peso(form.price) }}
                                    </p>
                                </div>

                                <div>
                                    <p
                                        class="text-[10px] font-semibold uppercase tracking-[0.08em] text-[#94A3B8]"
                                    >
                                        Stock
                                    </p>

                                    <p
                                        class="mt-0.5 text-base font-bold tracking-tight"
                                        :class="
                                            Number(form.stock) === 0
                                                ? 'text-[#E85D5D]'
                                                : Number(form.stock) <= 5
                                                    ? 'text-[#B77900]'
                                                    : 'text-[#1F2937]'
                                        "
                                    >
                                        {{ form.stock ?? 0 }}
                                    </p>
                                </div>

                                <span
                                    :class="[
                                        'rounded-full border px-2.5 py-1 text-[10px] font-bold',
                                        stockStatus.class,
                                    ]"
                                >
                                    {{ stockStatus.label }}
                                </span>

                                <span
                                    v-if="discountPercentage > 0"
                                    class="rounded-full border border-[#BBE7D3] bg-[#ECFDF5] px-2.5 py-1 text-[10px] font-bold text-[#16845A]"
                                >
                                    {{ discountPercentage }}% OFF
                                </span>
                            </div>
                        </div>
                    </div>
                </section>


                <!-- ====================================================== -->
                <!-- FORM -->
                <!-- ====================================================== -->

                <form
                    id="product-form"
                    class="mt-5 space-y-5"
                    @submit.prevent="submit"
                >

                    <!-- ================================================== -->
                    <!-- BASIC INFORMATION -->
                    <!-- ================================================== -->

                    <section
                        class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
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
                                        stroke-width="1.7"
                                    >
                                        <path
                                            d="M4 5h16v14H4z"
                                            stroke-linejoin="round"
                                        />
                                        <path
                                            d="M8 9h8M8 13h5"
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
                                        Update the details customers see on
                                        your product listing.
                                    </p>
                                </div>
                            </div>
                        </div>


                        <div class="grid gap-5 px-5 py-5 sm:grid-cols-2">

                            <!-- PRODUCT NAME -->
                            <div class="sm:col-span-2">
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

                                <div class="relative mt-2">
                                    <select
                                        id="category"
                                        v-model="form.category_id"
                                        class="block w-full appearance-none rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 pr-10 text-sm text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                    >
                                        <option
                                            v-for="category in categories"
                                            :key="category.id"
                                            :value="category.id"
                                        >
                                            {{ category.name }}
                                        </option>
                                    </select>

                                    <svg
                                        class="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#94A3B8]"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path
                                            d="M6 9l6 6 6-6"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                </div>

                                <p
                                    v-if="form.errors.category_id"
                                    class="mt-1.5 text-xs text-[#E85D5D]"
                                >
                                    {{ form.errors.category_id }}
                                </p>
                            </div>


                            <!-- SLUG -->
                            <div>
                                <label
                                    class="block text-xs font-semibold text-[#1F2937]"
                                >
                                    Product Slug
                                </label>

                                <div
                                    class="mt-2 flex min-h-[46px] items-center rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-xs text-[#64748B]"
                                >
                                    <span class="truncate">
                                        {{ props.product.slug || 'product-slug' }}
                                    </span>
                                </div>

                                <p
                                    class="mt-1.5 text-[11px] text-[#94A3B8]"
                                >
                                    The product URL identifier.
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
                                        class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-semibold text-[#087F8C]"
                                    >
                                        ₱
                                    </span>

                                    <input
                                        id="price"
                                        v-model.number="form.price"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        class="block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] py-3 pl-9 pr-4 text-sm text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
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


                            <!-- ORIGINAL PRICE -->
                            <div>
                                <label
                                    for="old_price"
                                    class="block text-xs font-semibold text-[#1F2937]"
                                >
                                    Original Price
                                    <span
                                        class="font-normal text-[#94A3B8]"
                                    >
                                        (Optional)
                                    </span>
                                </label>

                                <div class="relative mt-2">
                                    <span
                                        class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-semibold text-[#087F8C]"
                                    >
                                        ₱
                                    </span>

                                    <input
                                        id="old_price"
                                        v-model.number="form.old_price"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        placeholder="0.00"
                                        class="block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] py-3 pl-9 pr-4 text-sm text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                    />
                                </div>

                                <p class="mt-1.5 text-[11px] text-[#94A3B8]">
                                    Enter this only when the product is
                                    discounted.
                                </p>

                                <p
                                    v-if="form.errors.old_price"
                                    class="mt-1.5 text-xs text-[#E85D5D]"
                                >
                                    {{ form.errors.old_price }}
                                </p>
                            </div>


                            <!-- PRICING ERROR -->
                            <div
                                v-if="pricingError"
                                class="sm:col-span-2 flex items-start gap-3 rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3"
                            >
                                <div
                                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white text-[#DC2626]"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path
                                            d="M12 8v5M12 16h.01"
                                            stroke-linecap="round"
                                        />
                                        <circle
                                            cx="12"
                                            cy="12"
                                            r="9"
                                        />
                                    </svg>
                                </div>

                                <div>
                                    <p
                                        class="text-xs font-bold text-[#DC2626]"
                                    >
                                        Pricing needs attention
                                    </p>

                                    <p
                                        class="mt-0.5 text-[11px] text-[#DC2626]"
                                    >
                                        {{ pricingError }}
                                    </p>
                                </div>
                            </div>


                            <!-- DISCOUNT PREVIEW -->
                            <div
                                v-if="discountPercentage > 0"
                                class="sm:col-span-2 rounded-xl border border-[#BBE7D3] bg-[#ECFDF5] p-4"
                            >
                                <div
                                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div class="flex items-start gap-3">
                                        <div
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-[#22A06B]"
                                        >
                                            <svg
                                                class="h-5 w-5"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.7"
                                            >
                                                <path
                                                    d="M20 12l-8 8-8-8V4h8l8 8z"
                                                    stroke-linejoin="round"
                                                />
                                                <path
                                                    d="M8 8h.01"
                                                    stroke-linecap="round"
                                                />
                                            </svg>
                                        </div>

                                        <div>
                                            <p
                                                class="text-xs font-bold text-[#16845A]"
                                            >
                                                Discount Applied
                                            </p>

                                            <p
                                                class="mt-0.5 text-[11px] text-[#16845A]"
                                            >
                                                Customers will see the
                                                discounted selling price.
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        class="text-left sm:text-right"
                                    >
                                        <div
                                            class="text-xl font-bold tracking-tight text-[#16845A]"
                                        >
                                            {{ discountPercentage }}% OFF
                                        </div>

                                        <p
                                            class="mt-0.5 text-[11px] text-[#16845A]"
                                        >
                                            Save {{ peso(discountAmount) }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="mt-4 flex flex-wrap items-center gap-3 border-t border-[#BBE7D3] pt-3"
                                >
                                    <span
                                        class="text-xs text-[#64748B] line-through"
                                    >
                                        {{ peso(form.old_price) }}
                                    </span>

                                    <svg
                                        class="h-3.5 w-3.5 text-[#94A3B8]"
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

                                    <span
                                        class="text-base font-bold text-[#16845A]"
                                    >
                                        {{ peso(form.price) }}
                                    </span>
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

                                <input
                                    id="stock"
                                    v-model.number="form.stock"
                                    type="number"
                                    min="0"
                                    class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                />

                                <div
                                    class="mt-2 flex items-center justify-between gap-2"
                                >
                                    <p class="text-[11px] text-[#94A3B8]">
                                        Manage stock from Stock Monitoring
                                        when needed.
                                    </p>

                                    <span
                                        :class="[
                                            'shrink-0 rounded-full border px-2 py-1 text-[10px] font-bold',
                                            stockStatus.class,
                                        ]"
                                    >
                                        {{ stockStatus.label }}
                                    </span>
                                </div>

                                <p
                                    v-if="form.errors.stock"
                                    class="mt-1.5 text-xs text-[#E85D5D]"
                                >
                                    {{ form.errors.stock }}
                                </p>
                            </div>


                            <!-- STATUS -->
                            <div>
                                <label
                                    for="status"
                                    class="block text-xs font-semibold text-[#1F2937]"
                                >
                                    Product Status
                                </label>

                                <div class="relative mt-2">
                                    <select
                                        id="status"
                                        v-model="form.status"
                                        class="block w-full appearance-none rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 pr-10 text-sm text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                    >
                                        <option
                                            v-for="option in statusOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>

                                    <svg
                                        class="pointer-events-none absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#94A3B8]"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path
                                            d="M6 9l6 6 6-6"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                </div>

                                <p
                                    v-if="form.errors.status"
                                    class="mt-1.5 text-xs text-[#E85D5D]"
                                >
                                    {{ form.errors.status }}
                                </p>
                            </div>


                            <!-- DESCRIPTION -->
                            <div class="sm:col-span-2">
                                <div class="flex items-center justify-between gap-3">
                                    <label
                                        for="description"
                                        class="block text-xs font-semibold text-[#1F2937]"
                                    >
                                        Product Description
                                    </label>

                                    <span
                                        class="text-[10px] text-[#94A3B8]"
                                    >
                                        {{ form.description.length }} characters
                                    </span>
                                </div>

                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="5"
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


                    <!-- ================================================== -->
                    <!-- PRODUCT IMAGE -->
                    <!-- ================================================== -->

                    <section
                        class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                    >
                        <div
                            class="border-b border-[#E5E7EB] px-5 py-4"
                        >
                            <div class="flex items-center gap-3">
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
                                            d="M3 16l5-5 4 4 3-3 6 6"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                </div>

                                <div>
                                    <h2
                                        class="text-base font-bold text-[#1F2937]"
                                    >
                                        Product Image
                                    </h2>

                                    <p
                                        class="mt-0.5 text-xs text-[#64748B]"
                                    >
                                        Replace the current product image if
                                        needed.
                                    </p>
                                </div>
                            </div>
                        </div>


                        <div class="p-5">
                            <div
                                class="grid gap-5 lg:grid-cols-[224px_1fr] lg:items-start"
                            >

                                <!-- PREVIEW -->
                                <div>
                                    <div
                                        class="flex aspect-square w-full max-w-[224px] items-center justify-center overflow-hidden rounded-2xl border border-[#E5E7EB] bg-[#F8FAF9]"
                                    >
                                        <img
                                            v-if="currentImage"
                                            :src="currentImage"
                                            :alt="form.name"
                                            class="h-full w-full object-cover"
                                        />

                                        <div
                                            v-else
                                            class="flex h-full w-full flex-col items-center justify-center bg-[#E8F7F6] text-center text-[#087F8C]"
                                        >
                                            <div
                                                class="flex h-14 w-14 items-center justify-center rounded-full bg-white"
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
                                                        d="M3 16l5-5 4 4 3-3 6 6"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                    />
                                                </svg>
                                            </div>

                                            <span
                                                class="mt-2 text-[10px] font-semibold"
                                            >
                                                No product image
                                            </span>
                                        </div>
                                    </div>

                                    <p
                                        v-if="imagePreview"
                                        class="mt-2 text-center text-[10px] font-semibold text-[#087F8C]"
                                    >
                                        New image preview
                                    </p>
                                </div>


                                <!-- UPLOAD CONTROLS -->
                                <div class="min-w-0">
                                    <div
                                        class="rounded-xl border border-dashed border-[#BAE6FD] bg-[#F8FAF9] p-4"
                                    >
                                        <div class="flex items-start gap-3">
                                            <div
                                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                                            >
                                                <svg
                                                    class="h-5 w-5"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="1.7"
                                                >
                                                    <path
                                                        d="M12 16V5"
                                                        stroke-linecap="round"
                                                    />
                                                    <path
                                                        d="M8 9l4-4 4 4"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                    />
                                                    <path
                                                        d="M5 14v4a1 1 0 001 1h12a1 1 0 001-1v-4"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                    />
                                                </svg>
                                            </div>

                                            <div class="min-w-0">
                                                <p
                                                    class="text-xs font-bold text-[#1F2937]"
                                                >
                                                    Upload a replacement image
                                                </p>

                                                <p
                                                    class="mt-1 text-[11px] leading-5 text-[#64748B]"
                                                >
                                                    Choose a clear product photo.
                                                    Your existing image remains
                                                    unchanged until you save.
                                                </p>
                                            </div>
                                        </div>

                                        <div class="mt-4">
                                            <label
                                                for="product-image"
                                                class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-[#087F8C] px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#066D77] focus-within:ring-2 focus-within:ring-[#E8F7F6]"
                                            >
                                                <svg
                                                    class="h-4 w-4"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="1.8"
                                                >
                                                    <path
                                                        d="M12 16V5"
                                                        stroke-linecap="round"
                                                    />
                                                    <path
                                                        d="M8 9l4-4 4 4"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                    />
                                                    <path
                                                        d="M5 14v4a1 1 0 001 1h12a1 1 0 001-1v-4"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                    />
                                                </svg>
                                                Choose New Image
                                            </label>

                                            <input
                                                id="product-image"
                                                type="file"
                                                accept="image/jpeg,image/png,image/webp"
                                                class="hidden"
                                                @change="handleImageChange"
                                            />
                                        </div>

                                        <p
                                            class="mt-2 text-[10px] text-[#94A3B8]"
                                        >
                                            JPG, PNG, or WEBP · maximum 5MB.
                                        </p>

                                        <div
                                            v-if="imagePreview"
                                            class="mt-3 flex flex-wrap items-center gap-2"
                                        >
                                            <span
                                                class="inline-flex items-center gap-1.5 rounded-full border border-[#BAE6FD] bg-[#EFF6FF] px-2.5 py-1 text-[10px] font-bold text-[#0369A1]"
                                            >
                                                <svg
                                                    class="h-3 w-3"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                >
                                                    <path
                                                        d="M5 12l4 4L19 6"
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                    />
                                                </svg>
                                                New image selected
                                            </span>

                                            <button
                                                type="button"
                                                class="text-[10px] font-bold text-[#E85D5D] hover:underline"
                                                @click="removeNewImage"
                                            >
                                                Cancel New Image
                                            </button>
                                        </div>

                                        <p
                                            v-if="form.errors.image"
                                            class="mt-2 text-xs text-[#E85D5D]"
                                        >
                                            {{ form.errors.image }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>


                    <!-- ================================================== -->
                    <!-- GENERAL FORM ERROR -->
                    <!-- ================================================== -->

                    <div
                        v-if="
                            form.errors &&
                            Object.keys(form.errors).length
                        "
                        class="flex items-start gap-3 rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3"
                    >
                        <div
                            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white text-[#DC2626]"
                        >
                            <svg
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    d="M12 8v5M12 16h.01"
                                    stroke-linecap="round"
                                />
                                <circle
                                    cx="12"
                                    cy="12"
                                    r="9"
                                />
                            </svg>
                        </div>

                        <div>
                            <p
                                class="text-xs font-bold text-[#DC2626]"
                            >
                                Please check the highlighted fields
                            </p>

                            <p
                                class="mt-0.5 text-[11px] text-[#DC2626]"
                            >
                                Correct the errors above before saving your
                                product.
                            </p>
                        </div>
                    </div>


                    <!-- ================================================== -->
                    <!-- ACTIONS -->
                    <!-- ================================================== -->

                    <div
                        class="flex flex-col gap-3 rounded-2xl border border-[#E5E7EB] bg-white px-5 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p
                                class="text-xs font-semibold text-[#1F2937]"
                            >
                                Ready to update this product?
                            </p>

                            <p
                                class="mt-0.5 text-[11px] text-[#94A3B8]"
                            >
                                Your changes will be saved to the product
                                listing.
                            </p>
                        </div>

                        <div
                            class="flex flex-col-reverse gap-2 sm:flex-row"
                        >
                            <Link
                                :href="route('seller.products')"
                                class="inline-flex items-center justify-center rounded-xl border border-[#E5E7EB] bg-white px-5 py-2.5 text-xs font-bold text-[#475569] transition hover:border-[#087F8C] hover:text-[#087F8C]"
                            >
                                Cancel
                            </Link>

                            <button
                                type="submit"
                                :disabled="
                                    form.processing ||
                                    !!pricingError
                                "
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#087F8C] px-5 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#066D77] focus:outline-none focus:ring-2 focus:ring-[#E8F7F6] disabled:cursor-not-allowed disabled:opacity-60"
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
                                        ? 'Saving Changes...'
                                        : 'Save Changes'
                                }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </SellerLayout>
</template>