<!-- Seller form for editing an existing product listing. -->
<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
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

    categoryVariantTypes: {
        type: Array,
        default: () => [],
    },

    categoryVariantMap: {
        type: Object,
        default: () => ({}),
    },
})

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

const form = useForm({
    name: props.product.name || '',
    category_id: props.product.category_id ?? props.categories[0]?.id ?? '',

    // Pricing
    price: props.product.price ?? '',
    old_price: props.product.old_price ?? '',
    weight: props.product.weight ?? 0.5,

    stock: props.product.stock ?? 0,
    description: props.product.description ?? '',
    status: props.product.status || 'pending',

    // New replacement image
    image: null,

    // Variations
    variants: (props.product.variants || []).map(v => ({
        id: v.id,
        color: v.color,
        size: v.size,
        variant_attributes: v.variant_attributes || null,
        combination_key: v.combination_key || null,
        price: v.price || props.product.price || '',
        stock: v.stock || 0,
        weight: v.weight || '',
        sku: v.sku || '',
        image_path: v.image_path || null,
        is_active: v.is_active ?? true,
    })),
    variation_types: props.product.variation_types || [],
})

const categoryVariantTypes = ref(props.categoryVariantTypes || [])
const loadingVariantTypes = ref(false)
const selectedVariantTypeIds = ref([])
const selectedPredefinedValues = ref({})
const bulkVariantPrice = ref('')
const bulkVariantStock = ref('')

const totalVariantStock = computed(() => {
    return (form.variants || []).reduce((total, variant) => {
        return total + (Number(variant.stock) || 0)
    }, 0)
})

const fetchCategoryVariants = async (categoryId) => {
    if (!categoryId) {
        categoryVariantTypes.value = []
        return
    }

    if (props.categoryVariantMap && props.categoryVariantMap[categoryId] && props.categoryVariantMap[categoryId].length > 0) {
        categoryVariantTypes.value = props.categoryVariantMap[categoryId]
        populateExistingSelections()
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
                populateExistingSelections()
            }
        }
    } catch (e) {
        console.error('Failed to load category variant types', e)
    } finally {
        loadingVariantTypes.value = false
    }
}

const populateExistingSelections = () => {
    if (selectedVariantTypeIds.value.length > 0) return

    if (Array.isArray(props.product.variation_types) && props.product.variation_types.length > 0) {
        props.product.variation_types.forEach(vt => {
            const matched = categoryVariantTypes.value.find(t => t.name.toLowerCase() === vt.name.toLowerCase())
            if (matched) {
                if (!selectedVariantTypeIds.value.includes(matched.id)) {
                    selectedVariantTypeIds.value.push(matched.id)
                }
                selectedPredefinedValues.value[matched.id] = [...(vt.options || [])]
            }
        })
    } else if (Array.isArray(props.product.variants) && props.product.variants.length > 0) {
        const attributesMap = {}
        props.product.variants.forEach(v => {
            if (v.variant_attributes && typeof v.variant_attributes === 'object') {
                Object.entries(v.variant_attributes).forEach(([attrName, attrVal]) => {
                    const k = attrName.toLowerCase()
                    if (!attributesMap[k]) attributesMap[k] = new Set()
                    if (attrVal) attributesMap[k].add(attrVal)
                })
            }
        })

        const existingColors = [...new Set(props.product.variants.map(v => v.color).filter(Boolean))]
        const existingSizes = [...new Set(props.product.variants.map(v => v.size).filter(Boolean))]

        categoryVariantTypes.value.forEach(t => {
            const attrSet = attributesMap[t.name.toLowerCase()]
            if (attrSet && attrSet.size > 0) {
                if (!selectedVariantTypeIds.value.includes(t.id)) {
                    selectedVariantTypeIds.value.push(t.id)
                }
                selectedPredefinedValues.value[t.id] = Array.from(attrSet)
            } else if ((t.slug.includes('color') || t.display_type === 'color_swatch') && existingColors.length > 0) {
                if (!selectedVariantTypeIds.value.includes(t.id)) {
                    selectedVariantTypeIds.value.push(t.id)
                }
                selectedPredefinedValues.value[t.id] = existingColors
            } else if (t.slug.includes('size') && existingSizes.length > 0) {
                if (!selectedVariantTypeIds.value.includes(t.id)) {
                    selectedVariantTypeIds.value.push(t.id)
                }
                selectedPredefinedValues.value[t.id] = existingSizes
            }
        })
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

// Initial populate
if (categoryVariantTypes.value.length > 0) {
    populateExistingSelections()
} else if (form.category_id) {
    fetchCategoryVariants(form.category_id)
}

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
            id: existing?.id ?? null,
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

const removeVariant = (index) => {
    form.variants.splice(index, 1)
}

const clearAllVariants = () => {
    form.variants = []
    selectedVariantTypeIds.value = []
    selectedPredefinedValues.value = {}
}

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
     * An equal or lower old price and selling price is not a discount.
     */
    if (
        form.old_price !== '' &&
        form.price !== '' &&
        Number(form.old_price) <= Number(form.price)
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
        <div class="w-full space-y-6 pb-20">

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
                            <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-[#087F8C]">
                                <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                <span>Products</span>
                            </div>

                            <h1
                                class="mt-1 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl"
                            >
                                Edit Product
                            </h1>

                            <p class="mt-0.5 text-xs text-slate-500">
                                Update your product information, pricing, stock, and variations.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
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
                                        @change="handleCategoryChange($event.target.value)"
                                        class="block w-full appearance-none rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 pr-10 text-sm text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                    >
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
                                        :value="props.product.sku || 'ALN-000001'"
                                        class="block w-full cursor-not-allowed rounded-xl border border-[#BFE3E0] bg-[#F8FAF9] px-4 py-3 font-mono text-xs font-bold text-[#087F8C] shadow-xs"
                                    />
                                </div>
                                <p class="mt-1 text-[10px] text-[#64748B]">
                                    Unique SKU automatically assigned by the backend. Read-only for sellers.
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
                    <!-- PRODUCT VARIANTS (CATEGORY-BASED PREDEFINED SELECTION) -->
                    <!-- ================================================== -->

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

                    <!-- GENERAL ERROR BANNER -->
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