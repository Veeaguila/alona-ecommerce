<script setup>
import { ref, computed, onBeforeUnmount, onMounted, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    categoryVariantMap: {
        type: Object,
        default: () => ({}),
    },
    autoSkuPreview: {
        type: String,
        default: 'ALN-000001',
    },
})



const form = useForm({
    name: '',
    category_id: '',
    price: '',
    old_price: '',
    stock: '',
    weight: 0.5,
    description: '',
    status: 'pending',
    images: [],
    variants: [],
})

const categoryVariantTypes = ref([])
const loadingVariantTypes = ref(false)
const selectedVariantTypeIds = ref([])
const selectedPredefinedValues = ref({}) // { [typeId]: ['Black', 'White'] }
const bulkVariantPrice = ref('')
const bulkVariantStock = ref('')

const fetchCategoryVariants = async (categoryId) => {
    if (!categoryId) {
        categoryVariantTypes.value = []
        return
    }

    // Immediately resolve from preloaded map if present
    if (props.categoryVariantMap && props.categoryVariantMap[categoryId] && props.categoryVariantMap[categoryId].length > 0) {
        categoryVariantTypes.value = props.categoryVariantMap[categoryId]
    }

    loadingVariantTypes.value = categoryVariantTypes.value.length === 0
    try {
        let url = `/seller/categories/${categoryId}/variants`
        try {
            if (typeof route === 'function' && route().has('seller.categories.variants')) {
                url = route('seller.categories.variants', categoryId)
            }
        } catch (_) {}

        const res = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        if (res.ok) {
            const data = await res.json()
            if (data.variant_types && data.variant_types.length > 0) {
                categoryVariantTypes.value = data.variant_types
            }
        }
    } catch (e) {
        console.error('Failed to load category variant types', e)
    } finally {
        loadingVariantTypes.value = false
    }
}

const handleCategoryChange = (val) => {
    const catId = Number(val) || form.category_id
    if (catId) {
        fetchCategoryVariants(catId)
    } else {
        categoryVariantTypes.value = []
    }
}

watch(() => form.category_id, (newCatId) => {
    if (newCatId) {
        fetchCategoryVariants(newCatId)
    } else {
        categoryVariantTypes.value = []
    }
})

onMounted(() => {
    if (form.category_id) {
        fetchCategoryVariants(form.category_id)
    }
})

const toggleVariantTypeSelection = (type) => {
    const idx = selectedVariantTypeIds.value.indexOf(type.id)
    if (idx > -1) {
        selectedVariantTypeIds.value.splice(idx, 1)
        delete selectedPredefinedValues.value[type.id]
    } else {
        selectedVariantTypeIds.value.push(type.id)
        if (!selectedPredefinedValues.value[type.id]) {
            selectedPredefinedValues.value[type.id] = []
        }
    }
}

const toggleValueSelection = (typeId, val) => {
    if (!selectedPredefinedValues.value[typeId]) {
        selectedPredefinedValues.value[typeId] = []
    }
    const arr = selectedPredefinedValues.value[typeId]
    const idx = arr.indexOf(val)
    if (idx > -1) {
        arr.splice(idx, 1)
    } else {
        arr.push(val)
    }
}

const isValueSelected = (typeId, val) => {
    return selectedPredefinedValues.value[typeId]?.includes(val) || false
}

const activeSelectedTypes = computed(() => {
    return categoryVariantTypes.value.filter(t => selectedVariantTypeIds.value.includes(t.id))
})

const combinationCountPreview = computed(() => {
    const chosen = activeSelectedTypes.value.map(t => (selectedPredefinedValues.value[t.id] || []).length).filter(c => c > 0)
    if (chosen.length === 0) return 0
    return chosen.reduce((acc, curr) => acc * curr, 1)
})

const generateCombinations = () => {
    const active = activeSelectedTypes.value.filter(t => (selectedPredefinedValues.value[t.id] || []).length > 0)
    if (active.length === 0) return

    const cartesian = (arrays) => {
        return arrays.reduce((acc, curr) => {
            const res = []
            acc.forEach(a => {
                curr.forEach(b => {
                    res.push([...a, b])
                })
            })
            return res
        }, [[]])
    }

    const valueArrays = active.map(t => selectedPredefinedValues.value[t.id])
    const cartesianProducts = cartesian(valueArrays)

    const existingMap = new Map()
    form.variants.forEach(v => {
        const key = v.combination_key || (v.color + '|' + v.size)
        existingMap.set(key, v)
    })

    const newVariants = []
    const variationTypesPayload = active.map(t => ({
        name: t.name,
        options: selectedPredefinedValues.value[t.id] || [],
    }))

    cartesianProducts.forEach(combo => {
        const attributes = {}
        let colorVal = null
        let sizeVal = null

        active.forEach((t, i) => {
            const val = combo[i]
            attributes[t.name] = val
            if (t.display_type === 'color_swatch' || t.slug.includes('color')) {
                colorVal = val
            } else if (t.slug.includes('size')) {
                sizeVal = val
            }
        })

        const vals = Object.values(attributes)
        if (!colorVal && vals[0]) colorVal = vals[0]
        if (!sizeVal && vals[1]) sizeVal = vals[1]

        const key = Object.entries(attributes).sort().map(([k, v]) => `${k.toLowerCase()}:${v.toLowerCase()}`).join('|')
        const existing = existingMap.get(key) || existingMap.get(colorVal + '|' + sizeVal)

        newVariants.push({
            color: colorVal,
            size: sizeVal,
            variant_attributes: attributes,
            combination_key: key,
            price: existing?.price ?? (form.price || ''),
            stock: existing?.stock ?? 0,
            weight: existing?.weight ?? '',
            sku: existing?.sku ?? '',
            image_path: existing?.image_path ?? null,
            is_active: existing?.is_active ?? true,
        })
    })

    form.variants = newVariants
    form.variation_types = variationTypesPayload
}

const applyBulkPrice = () => {
    if (bulkVariantPrice.value === '' || Number(bulkVariantPrice.value) < 0) return
    form.variants.forEach(v => {
        v.price = bulkVariantPrice.value
    })
}

const applyBulkStock = () => {
    if (bulkVariantStock.value === '' || Number(bulkVariantStock.value) < 0) return
    form.variants.forEach(v => {
        v.stock = Number(bulkVariantStock.value)
    })
}

const clearAllVariants = () => {
    form.variants = []
    selectedVariantTypeIds.value = []
    selectedPredefinedValues.value = {}
}

const groupedCategories = computed(() => {
    const roots = props.categories.filter(c => !c.parent_id)
    if (roots.length > 0) {
        return roots.map(root => {
            const subs = props.categories.filter(c => c.parent_id === root.id)
            return {
                ...root,
                subcategories: subs.length > 0 ? subs : (root.active_subcategories || root.activeSubcategories || []),
            }
        })
    }
    return props.categories.map(c => ({ ...c, subcategories: [] }))
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

    if (form.old_price !== '' && oldPrice <= 0) {
        return 'Original price must be greater than ₱0.00.'
    }

    if (
        form.old_price !== '' &&
        form.price !== '' &&
        oldPrice > 0 &&
        price > 0 &&
        oldPrice < price
    ) {
        return 'Original price cannot be lower than the selling price.'
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
        price: form.price || '',
        stock: 0,
        sku: '',
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

    /*
     * An equal old price and selling price is not a discount.
     */
    if (
        form.old_price !== '' &&
        form.price !== '' &&
        Number(form.old_price) <= Number(form.price)
    ) {
        form.old_price = ''
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
        <div class="w-full space-y-6 pb-20">

                <!-- PAGE HEADER -->
                <div
                    class="flex flex-col gap-4 border-b border-[#E5E7EB] pb-5 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div>
                        <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-[#087F8C]">
                            <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                            <span>Products</span>
                        </div>

                        <h1
                            class="mt-1 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl"
                        >
                            Add New Product
                        </h1>

                        <p class="mt-0.5 text-xs text-slate-500">
                            Create a new product listing with pricing, variations, stock, and media.
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

                    <!-- BASIC INFORMATION -->
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

                                    <p class="mt-0.5 text-xs text-[#64748B]">
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

                            <!-- PRODUCT SKU (AUTOMATICALLY GENERATED, READ-ONLY) -->
                            <div>
                                <div class="flex items-center justify-between">
                                    <label class="block text-xs font-semibold text-[#1F2937]">
                                        Product SKU (Stock Keeping Unit)
                                    </label>
                                    <span class="inline-flex items-center gap-1 rounded-md bg-[#E8F7F6] px-2 py-0.5 text-[10px] font-bold text-[#087F8C]">
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                        Auto-Generated (Read-Only)
                                    </span>
                                </div>

                                <div class="relative mt-2">
                                    <input
                                        type="text"
                                        readonly
                                        disabled
                                        :value="props.autoSkuPreview || 'ALN-000001 (Assigned on save)'"
                                        class="block w-full cursor-not-allowed rounded-xl border border-[#BFE3E0] bg-[#F8FAF9] px-4 py-3 font-mono text-xs font-bold text-[#087F8C] shadow-xs"
                                    />
                                </div>
                                <p class="mt-1 text-[10px] text-[#64748B]">
                                    Unique SKU automatically assigned by the backend. Read-only for sellers.
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
                                    v-model.number="form.category_id"
                                    @change="handleCategoryChange($event.target.value)"
                                    class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                >
                                    <option value="">
                                        Select category
                                    </option>

                                    <template v-for="category in groupedCategories" :key="category.id">
                                        <optgroup v-if="category.subcategories && category.subcategories.length > 0" :label="category.name">
                                            <option :value="category.id">
                                                {{ category.name }} (General)
                                            </option>
                                            <option
                                                v-for="sub in category.subcategories"
                                                :key="sub.id"
                                                :value="sub.id"
                                            >
                                                ↳ {{ sub.name }}
                                            </option>
                                        </optgroup>
                                        <option v-else :value="category.id">
                                            {{ category.name }}
                                        </option>
                                    </template>
                                </select>

                                <p
                                    v-if="form.errors.category_id"
                                    class="mt-1.5 text-xs text-[#E85D5D]"
                                >
                                    {{ form.errors.category_id }}
                                </p>

                                <p
                                    v-if="props.categories.length === 0"
                                    class="mt-1.5 text-[11px] text-[#E85D5D]"
                                >
                                    No active categories are currently available.
                                </p>
                            </div>

                            <!-- PRICING -->
                            <div>
                                <div class="mb-4">
                                    <h3 class="text-sm font-bold text-[#1F2937]">
                                        Product Pricing
                                    </h3>

                                    <p class="mt-0.5 text-[11px] text-[#64748B]">
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
                                            Leave blank if not on sale. If discounted, must be higher than selling price.
                                        </p>

                                        <p
                                            v-if="form.old_price && form.price && Number(form.old_price) === Number(form.price)"
                                            class="mt-1 text-[11px] font-medium text-slate-500"
                                        >
                                            Matches selling price (no discount applied).
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
                                        <circle cx="12" cy="12" r="9" />
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

                    <!-- PRODUCT VARIANTS (CATEGORY-BASED PREDEFINED SELECTION) -->
                    <section
                        class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                    >
                        <div
                            class="border-b border-[#E5E7EB] bg-[#F8FAF9] px-5 py-4"
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
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
                                                d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            />
                                        </svg>
                                    </div>

                                    <div>
                                        <h2 class="text-base font-bold text-[#1F2937]">
                                            Product Variations
                                        </h2>

                                        <p class="mt-0.5 text-xs text-[#64748B]">
                                            Select approved category attributes to generate combinations with independent stock and prices.
                                        </p>
                                    </div>
                                </div>

                                <button
                                    v-if="form.variants.length > 0 || selectedVariantTypeIds.length > 0"
                                    type="button"
                                    @click="clearAllVariants"
                                    class="inline-flex w-fit items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-bold text-slate-600 transition hover:bg-slate-50 hover:text-rose-600"
                                >
                                    Clear Variations (Single Product)
                                </button>
                            </div>
                        </div>

                        <div class="space-y-6 px-5 py-5">
                            <!-- Category Notice if none selected -->
                            <div
                                v-if="!form.category_id"
                                class="rounded-xl border border-dashed border-[#CBD5E1] bg-[#F8FAF9] p-6 text-center"
                            >
                                <p class="text-xs font-semibold text-slate-600">
                                    Please select a Category in the Product Information section above to view available variant types.
                                </p>
                            </div>

                            <div v-else-if="loadingVariantTypes" class="py-6 text-center text-xs text-slate-500">
                                Loading approved variant options for this category...
                            </div>

                            <div v-else-if="categoryVariantTypes.length === 0" class="rounded-xl border border-slate-200 bg-slate-50 p-5 text-center">
                                <p class="text-xs font-semibold text-slate-700">Standard Single Product</p>
                                <p class="mt-1 text-[11px] text-slate-500">This category does not require variations. Inventory and pricing are managed via the main fields above.</p>
                            </div>

                            <!-- STEP 1: SELECT VARIANT TYPES -->
                            <div v-else class="space-y-5">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500">
                                        Step 1: Choose Variation Attributes
                                    </label>
                                    <p class="mt-0.5 text-[11px] text-[#64748B]">
                                        Click to enable the attributes that apply to this product (e.g. Color, Size, Storage).
                                    </p>

                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <button
                                            v-for="type in categoryVariantTypes"
                                            :key="type.id"
                                            type="button"
                                            @click="toggleVariantTypeSelection(type)"
                                            :class="[
                                                'inline-flex items-center gap-1.5 rounded-xl border px-3.5 py-2 text-xs font-bold transition',
                                                selectedVariantTypeIds.includes(type.id)
                                                    ? 'border-[#087F8C] bg-[#E8F7F6] text-[#087F8C] shadow-xs ring-1 ring-[#087F8C]'
                                                    : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'
                                            ]"
                                        >
                                            <span>{{ selectedVariantTypeIds.includes(type.id) ? '✓' : '+' }}</span>
                                            <span>{{ type.name }}</span>
                                            <span class="rounded bg-white/80 px-1.5 py-0.2 text-[10px] text-slate-500">
                                                {{ type.values ? type.values.length : 0 }}
                                            </span>
                                        </button>
                                    </div>
                                </div>

                                <!-- STEP 2: SELECT PREDEFINED VALUES -->
                                <div v-if="activeSelectedTypes.length > 0" class="space-y-4 rounded-xl border border-slate-200 bg-[#F8FAF9] p-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500">
                                                Step 2: Select Approved Options
                                            </label>
                                            <p class="text-[11px] text-[#64748B]">
                                                Click options to include them in the variant combinations.
                                            </p>
                                        </div>

                                        <div v-if="combinationCountPreview > 0" class="flex items-center gap-3">
                                            <span class="text-xs font-extrabold text-[#087F8C]">
                                                {{ combinationCountPreview }} combination{{ combinationCountPreview === 1 ? '' : 's' }}
                                            </span>
                                            <button
                                                type="button"
                                                @click="generateCombinations"
                                                class="inline-flex items-center gap-1.5 rounded-xl bg-[#087F8C] px-3.5 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-[#066D77]"
                                            >
                                                ⚡ Generate Combinations
                                            </button>
                                        </div>
                                    </div>

                                    <div
                                        v-for="type in activeSelectedTypes"
                                        :key="type.id"
                                        class="rounded-xl border border-slate-200 bg-white p-3.5"
                                    >
                                        <div class="flex items-center justify-between pb-2">
                                            <span class="text-xs font-bold text-slate-800">{{ type.name }}</span>
                                            <span class="text-[10px] font-semibold text-slate-400">
                                                {{ (selectedPredefinedValues[type.id] || []).length }} of {{ type.values.length }} selected
                                            </span>
                                        </div>

                                        <!-- COLOR SWATCH PICKER -->
                                        <div v-if="type.display_type === 'color_swatch'" class="mt-2 flex flex-wrap gap-2">
                                            <button
                                                v-for="val in type.values"
                                                :key="val.id"
                                                type="button"
                                                @click="toggleValueSelection(type.id, val.value)"
                                                :title="val.label || val.value"
                                                :class="[
                                                    'group inline-flex items-center gap-2 rounded-xl border px-3 py-1.5 text-xs font-semibold transition',
                                                    isValueSelected(type.id, val.value)
                                                        ? 'border-[#087F8C] bg-[#E8F7F6] text-[#087F8C] ring-1 ring-[#087F8C]'
                                                        : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'
                                                ]"
                                            >
                                                <span
                                                    class="h-3.5 w-3.5 rounded-full border border-slate-300 shrink-0"
                                                    :style="{ background: val.hex || '#000000' }"
                                                ></span>
                                                <span>{{ val.label || val.value }}</span>
                                                <span v-if="isValueSelected(type.id, val.value)" class="text-[10px]">✓</span>
                                            </button>
                                        </div>

                                        <!-- PILLS PICKER (SIZES, RAM, STORAGE, FLAVOR, ETC.) -->
                                        <div v-else class="mt-2 flex flex-wrap gap-2">
                                            <button
                                                v-for="val in type.values"
                                                :key="val.id"
                                                type="button"
                                                @click="toggleValueSelection(type.id, val.value)"
                                                :class="[
                                                    'rounded-xl border px-3 py-1.5 text-xs font-semibold transition',
                                                    isValueSelected(type.id, val.value)
                                                        ? 'border-[#087F8C] bg-[#E8F7F6] text-[#087F8C] ring-1 ring-[#087F8C]'
                                                        : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'
                                                ]"
                                            >
                                                {{ val.label || val.value }}
                                                <span v-if="isValueSelected(type.id, val.value)" class="ml-1 text-[10px]">✓</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- STEP 3 & 4: COMBINATIONS TABLE & BULK CONTROLS -->
                                <div v-if="form.variants.length > 0" class="space-y-4">
                                    <div class="flex flex-col gap-3 rounded-xl border border-[#BFE3E0] bg-[#E8F7F6] p-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <h3 class="text-xs font-bold text-[#087F8C]">Bulk Update Combinations</h3>
                                            <p class="text-[10px] text-[#16845A]">Apply the same price or stock across all generated variants at once.</p>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2">
                                            <div class="flex items-center gap-1">
                                                <input
                                                    v-model="bulkVariantPrice"
                                                    type="number"
                                                    min="1"
                                                    step="0.01"
                                                    placeholder="Price ₱"
                                                    class="w-24 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-800 outline-none focus:border-[#087F8C]"
                                                />
                                                <button
                                                    type="button"
                                                    @click="applyBulkPrice"
                                                    class="rounded-lg bg-[#087F8C] px-2.5 py-1.5 text-[11px] font-bold text-white hover:bg-[#066D77]"
                                                >
                                                    Set Price
                                                </button>
                                            </div>

                                            <div class="flex items-center gap-1">
                                                <input
                                                    v-model="bulkVariantStock"
                                                    type="number"
                                                    min="0"
                                                    placeholder="Stock"
                                                    class="w-20 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-800 outline-none focus:border-[#087F8C]"
                                                />
                                                <button
                                                    type="button"
                                                    @click="applyBulkStock"
                                                    class="rounded-lg bg-[#087F8C] px-2.5 py-1.5 text-[11px] font-bold text-white hover:bg-[#066D77]"
                                                >
                                                    Set Stock
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- COMBINATION ROWS -->
                                    <div class="space-y-3">
                                        <div
                                            v-for="(variant, index) in form.variants"
                                            :key="index"
                                            class="rounded-xl border border-slate-200 bg-white p-3.5 shadow-xs"
                                        >
                                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                <div class="flex items-center gap-2.5">
                                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 font-mono text-[10px] font-bold text-slate-600">
                                                        {{ index + 1 }}
                                                    </span>

                                                    <div>
                                                        <div class="flex items-center gap-2">
                                                            <span
                                                                v-if="variant.color"
                                                                class="h-3 w-3 rounded-full border border-slate-300"
                                                                :style="{ background: variant.color.toLowerCase() }"
                                                            ></span>
                                                            <span class="text-xs font-bold text-slate-800">
                                                                {{ variant.variant_attributes ? Object.values(variant.variant_attributes).join(' · ') : (variant.color + (variant.size ? ' · ' + variant.size : '')) }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="flex flex-wrap items-center gap-3">
                                                    <!-- Price -->
                                                    <div class="flex items-center gap-1">
                                                        <label class="text-[10px] font-bold text-slate-400">₱</label>
                                                        <input
                                                            v-model="variant.price"
                                                            type="number"
                                                            min="1"
                                                            step="0.01"
                                                            placeholder="0.00"
                                                            required
                                                            class="w-24 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs text-slate-800 outline-none focus:border-[#087F8C]"
                                                        />
                                                    </div>

                                                    <!-- Stock -->
                                                    <div class="flex items-center gap-1">
                                                        <label class="text-[10px] font-bold text-slate-400">Qty</label>
                                                        <input
                                                            v-model.number="variant.stock"
                                                            type="number"
                                                            min="0"
                                                            placeholder="0"
                                                            required
                                                            class="w-20 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs text-slate-800 outline-none focus:border-[#087F8C]"
                                                        />
                                                    </div>

                                                    <!-- Optional Weight -->
                                                    <div class="flex items-center gap-1">
                                                        <label class="text-[10px] font-bold text-slate-400">kg</label>
                                                        <input
                                                            v-model="variant.weight"
                                                            type="number"
                                                            step="0.01"
                                                            min="0"
                                                            placeholder="0.5"
                                                            class="w-16 rounded-lg border border-slate-200 px-2 py-1.5 text-xs text-slate-800 outline-none focus:border-[#087F8C]"
                                                        />
                                                    </div>

                                                    <!-- Remove Row -->
                                                    <button
                                                        type="button"
                                                        @click="removeVariant(index)"
                                                        class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                                                        title="Remove combination"
                                                    >
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>

                                            <div v-if="form.errors[`variants.${index}.price`]" class="mt-1 text-[10px] text-[#E85D5D]">
                                                {{ form.errors[`variants.${index}.price`] }}
                                            </div>
                                            <div v-if="form.errors[`variants.${index}.stock`]" class="mt-1 text-[10px] text-[#E85D5D]">
                                                {{ form.errors[`variants.${index}.stock`] }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- TOTAL VARIANT STOCK -->
                                    <div class="flex items-center justify-between rounded-xl border border-[#BFE3E0] bg-[#E8F7F6] px-4 py-3">
                                        <div>
                                            <p class="text-xs font-bold text-[#087F8C]">Total Calculated Variant Stock</p>
                                            <p class="text-[10px] text-[#16845A]">Sum across all {{ form.variants.length }} combinations.</p>
                                        </div>
                                        <span class="text-xl font-bold tracking-tight text-[#087F8C]">
                                            {{ totalVariantStock }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- PRODUCT IMAGES -->
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
                                            <circle cx="8.5" cy="9" r="1.5" />
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

                                        <div
                                            v-if="index === 0"
                                            class="absolute left-2 top-2 rounded-full bg-[#087F8C] px-2.5 py-1 text-[9px] font-bold text-white"
                                        >
                                            MAIN PHOTO
                                        </div>

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

                                        <p class="mt-2 text-xs font-bold text-[#475569]">
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
                                        <circle cx="8.5" cy="9" r="1.5" />
                                        <path
                                            d="M21 15l-4.5-4.5L8 19"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                </div>

                                <p class="mt-3 text-sm font-bold text-[#475569]">
                                    Click to upload product photos
                                </p>

                                <p class="mt-1 text-xs text-[#64748B]">
                                    You can select multiple photos at once.
                                </p>

                                <p class="mt-1 text-[10px] text-[#94A3B8]">
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

                    <!-- LISTING STATUS -->
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

                    <!-- ACTION BAR -->
                    <div
                        class="rounded-2xl border border-[#E5E7EB] bg-white px-5 py-4 shadow-sm"
                    >
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <p class="text-xs font-bold text-[#1F2937]">
                                    Ready to list your product?
                                </p>

                                <p class="mt-0.5 text-[11px] text-[#94A3B8]">
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
                                <circle cx="12" cy="12" r="9" />

                                <path
                                    d="M12 8v4M12 16h.01"
                                    stroke-linecap="round"
                                />
                            </svg>

                            <div class="space-y-1">
                                <p class="text-xs font-bold text-[#DC2626]">
                                    Please check and correct the following errors:
                                </p>

                                <ul class="list-disc pl-4 text-[11px] text-[#E85D5D] space-y-0.5">
                                    <li v-for="(err, key) in form.errors" :key="key">
                                        {{ err }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
    </SellerLayout>
</template>