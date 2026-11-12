<script setup>
import { ref, computed } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    title: { type: String, default: 'Master Variant Catalog' },
    eyebrow: { type: String, default: 'Catalog Architecture' },
    description: { type: String, default: '' },
    active: { type: String, default: 'variants' },
    variantTypes: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    coverage: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
})

const currentTab = ref('catalog') // 'catalog', 'mappings', 'coverage'
const searchQuery = ref('')
const selectedType = ref(props.variantTypes[0] || null)

// Modals
const showCreateTypeModal = ref(false)
const showAddValueModal = ref(false)
const showEditMappingModal = ref(false)
const mappingTargetCategory = ref(null)

// Forms
const typeForm = useForm({
    name: '',
    slug: '',
    display_type: 'pills',
    description: '',
    sort_order: 0,
    is_active: true,
})

const valueForm = useForm({
    value: '',
    label: '',
    hex: '#000000',
    sort_order: 0,
    is_active: true,
})

const mappingForm = useForm({
    variant_type_ids: [],
})

const filteredTypes = computed(() => {
    if (!searchQuery.value) return props.variantTypes
    const q = searchQuery.value.toLowerCase()
    return props.variantTypes.filter(t =>
        t.name.toLowerCase().includes(q) ||
        t.slug.toLowerCase().includes(q) ||
        (t.description && t.description.toLowerCase().includes(q))
    )
})

const coverageFilter = ref('all')
const filteredCoverage = computed(() => {
    let list = props.coverage
    if (coverageFilter.value === 'covered') {
        list = list.filter(c => c.status === 'Covered')
    } else if (coverageFilter.value === 'review') {
        list = list.filter(c => c.status === 'Requires Review')
    }
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase()
        list = list.filter(c => c.name.toLowerCase().includes(q) || (c.parent_name && c.parent_name.toLowerCase().includes(q)))
    }
    return list
})

const selectType = (type) => {
    selectedType.value = type
}

const submitCreateType = () => {
    typeForm.post(route('admin.variants.types.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateTypeModal.value = false
            typeForm.reset()
        },
    })
}

const toggleTypeStatus = (type) => {
    router.patch(route('admin.variants.types.toggle', type.id), {}, { preserveScroll: true })
}

const openAddValue = () => {
    valueForm.reset()
    valueForm.hex = '#000000'
    showAddValueModal.value = true
}

const submitAddValue = () => {
    if (!selectedType.value) return
    const payload = {
        value: valueForm.value,
        label: valueForm.label || valueForm.value,
        sort_order: valueForm.sort_order,
        is_active: valueForm.is_active,
        meta: selectedType.value.display_type === 'color_swatch' ? { hex: valueForm.hex } : null,
    }

    useForm(payload).post(route('admin.variants.values.store', selectedType.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showAddValueModal.value = false
            valueForm.reset()
        },
    })
}

const toggleValueStatus = (val) => {
    router.patch(route('admin.variants.values.toggle', val.id), {}, { preserveScroll: true })
}

const openEditMappings = (cat) => {
    mappingTargetCategory.value = cat
    const currentTypeIds = cat.variant_types ? cat.variant_types.map(t => t.id) : []
    mappingForm.variant_type_ids = currentTypeIds
    showEditMappingModal.value = true
}

const submitMappings = () => {
    if (!mappingTargetCategory.value) return
    mappingForm.patch(route('admin.categories.variant-mappings', mappingTargetCategory.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEditMappingModal.value = false
        },
    })
}
</script>

<template>
    <AdminLayout>
        <Head title="Master Variant Catalog" />

        <div class="space-y-6 pb-12">
            <!-- Header Section -->
            <div class="rounded-2xl border border-[#BFE3E0] bg-gradient-to-r from-[#087F8C]/10 via-[#E8F7F6] to-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-[#087F8C]/10 px-3 py-1 text-xs font-bold uppercase tracking-wider text-[#087F8C]">
                            {{ eyebrow }}
                        </span>
                        <h1 class="mt-2 text-2xl font-black text-slate-800 sm:text-3xl">
                            {{ title }}
                        </h1>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ description }}
                        </p>
                    </div>

                    <!-- Action Button -->
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            @click="showCreateTypeModal = true"
                            class="inline-flex items-center gap-2 rounded-xl bg-[#087F8C] px-4 py-2.5 text-xs font-bold text-white shadow-md transition hover:bg-[#065A63]"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            New Variant Type
                        </button>
                    </div>
                </div>

                <!-- Quick KPI Metrics -->
                <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
                    <div class="rounded-xl border border-white/80 bg-white/90 p-3.5 shadow-sm">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Master Types</p>
                        <p class="mt-1 text-xl font-extrabold text-[#087F8C]">{{ summary.total_types || 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-white/80 bg-white/90 p-3.5 shadow-sm">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Predefined Values</p>
                        <p class="mt-1 text-xl font-extrabold text-slate-800">{{ summary.total_values || 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-white/80 bg-white/90 p-3.5 shadow-sm">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Covered Categories</p>
                        <p class="mt-1 text-xl font-extrabold text-emerald-600">{{ summary.covered_categories || 0 }} / {{ summary.total_categories || 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-white/80 bg-white/90 p-3.5 shadow-sm">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Pending Review</p>
                        <p class="mt-1 text-xl font-extrabold text-amber-600">{{ summary.review_required || 0 }}</p>
                    </div>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <div class="flex items-center gap-2 border-b border-slate-200">
                <button
                    type="button"
                    @click="currentTab = 'catalog'"
                    :class="[
                        'border-b-2 px-4 py-3 text-xs font-bold transition',
                        currentTab === 'catalog'
                            ? 'border-[#087F8C] text-[#087F8C]'
                            : 'border-transparent text-slate-500 hover:text-slate-800'
                    ]"
                >
                    Variant Types & Values
                </button>
                <button
                    type="button"
                    @click="currentTab = 'mappings'"
                    :class="[
                        'border-b-2 px-4 py-3 text-xs font-bold transition',
                        currentTab === 'mappings'
                            ? 'border-[#087F8C] text-[#087F8C]'
                            : 'border-transparent text-slate-500 hover:text-slate-800'
                    ]"
                >
                    Category Mappings
                </button>
                <button
                    type="button"
                    @click="currentTab = 'coverage'"
                    :class="[
                        'border-b-2 px-4 py-3 text-xs font-bold transition',
                        currentTab === 'coverage'
                            ? 'border-[#087F8C] text-[#087F8C]'
                            : 'border-transparent text-slate-500 hover:text-slate-800'
                    ]"
                >
                    Category Coverage Report
                </button>
            </div>

            <!-- TAB 1: CATALOG (TYPES & VALUES) -->
            <div v-if="currentTab === 'catalog'" class="grid gap-6 lg:grid-cols-12">
                <!-- Left Column: Type List -->
                <div class="space-y-4 lg:col-span-5">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-500">
                            Predefined Variant Types ({{ filteredTypes.length }})
                        </h2>
                    </div>

                    <div class="relative">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search variant types..."
                            class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs text-slate-800 outline-none transition focus:border-[#087F8C] focus:ring-1 focus:ring-[#087F8C]"
                        />
                    </div>

                    <div class="max-h-[640px] space-y-2 overflow-y-auto pr-1">
                        <div
                            v-for="type in filteredTypes"
                            :key="type.id"
                            @click="selectType(type)"
                            :class="[
                                'cursor-pointer rounded-xl border p-3.5 transition',
                                selectedType && selectedType.id === type.id
                                    ? 'border-[#087F8C] bg-[#E8F7F6]/60 shadow-sm'
                                    : 'border-slate-200 bg-white hover:border-slate-300'
                            ]"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-slate-800">{{ type.name }}</span>
                                        <span
                                            v-if="!type.is_active"
                                            class="rounded bg-rose-50 px-1.5 py-0.5 text-[9px] font-bold text-rose-600"
                                        >
                                            Disabled
                                        </span>
                                    </div>
                                    <p class="mt-0.5 font-mono text-[10px] text-slate-400">{{ type.slug }}</p>
                                </div>
                                <span class="rounded-lg bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">
                                    {{ type.values ? type.values.length : 0 }} values
                                </span>
                            </div>
                            <p v-if="type.description" class="mt-1.5 text-[11px] text-slate-500 line-clamp-1">
                                {{ type.description }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Selected Type Details & Values -->
                <div class="lg:col-span-7">
                    <div v-if="selectedType" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-base font-extrabold text-slate-800">{{ selectedType.name }}</h3>
                                    <span class="rounded-full bg-[#087F8C]/10 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-[#087F8C]">
                                        {{ selectedType.display_type }}
                                    </span>
                                </div>
                                <p class="mt-0.5 text-xs text-slate-500">{{ selectedType.description || 'Predefined catalog option' }}</p>
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="toggleTypeStatus(selectedType)"
                                    :class="[
                                        'rounded-lg border px-3 py-1.5 text-[11px] font-bold transition',
                                        selectedType.is_active
                                            ? 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'
                                            : 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                    ]"
                                >
                                    {{ selectedType.is_active ? 'Disable Type' : 'Enable Type' }}
                                </button>
                                <button
                                    type="button"
                                    @click="openAddValue"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-[#087F8C] px-3 py-1.5 text-[11px] font-bold text-white shadow-sm transition hover:bg-[#065A63]"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Add Value
                                </button>
                            </div>
                        </div>

                        <!-- Approved Values Grid -->
                        <div class="mt-6">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">
                                Approved Values ({{ selectedType.values ? selectedType.values.length : 0 }})
                            </h4>

                            <div class="mt-3 flex flex-wrap gap-2">
                                <div
                                    v-for="val in selectedType.values"
                                    :key="val.id"
                                    class="group relative inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-[#F8FAF9] px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-[#087F8C] hover:bg-white"
                                >
                                    <!-- Color Swatch Circle if Color -->
                                    <span
                                        v-if="val.meta && val.meta.hex"
                                        class="h-3.5 w-3.5 rounded-full border border-slate-300 shadow-xs shrink-0"
                                        :style="{ background: val.meta.hex }"
                                    ></span>

                                    <span>{{ val.label || val.value }}</span>

                                    <button
                                        type="button"
                                        @click.stop="toggleValueStatus(val)"
                                        title="Toggle availability"
                                        :class="[
                                            'ml-1 text-[10px] font-bold transition',
                                            val.is_active ? 'text-slate-400 hover:text-rose-500' : 'text-rose-500'
                                        ]"
                                    >
                                        {{ val.is_active ? '✓' : '✕' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: CATEGORY MAPPINGS -->
            <div v-else-if="currentTab === 'mappings'" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-black text-slate-800">Category Variant Mappings</h2>
                        <p class="text-xs text-slate-500">Assign approved variant types to each category so sellers only see relevant options.</p>
                    </div>
                </div>

                <div class="mt-4 divide-y divide-slate-100">
                    <div
                        v-for="cat in categories"
                        :key="cat.id"
                        class="flex flex-col gap-3 py-3.5 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span v-if="cat.parent_id" class="text-slate-300">↳</span>
                                <span class="text-xs font-bold text-slate-800">{{ cat.name }}</span>
                                <span v-if="cat.parent" class="rounded bg-slate-100 px-1.5 py-0.5 text-[9px] font-semibold text-slate-500">
                                    {{ cat.parent.name }}
                                </span>
                            </div>
                            <div class="mt-1.5 flex flex-wrap gap-1.5">
                                <span
                                    v-for="vt in (cat.variant_types || [])"
                                    :key="vt.id"
                                    class="rounded-md bg-[#E8F7F6] px-2 py-0.5 text-[10px] font-bold text-[#087F8C]"
                                >
                                    {{ vt.name }}
                                </span>
                                <span v-if="!cat.variant_types || cat.variant_types.length === 0" class="text-[10px] italic text-slate-400">
                                    {{ cat.parent_id ? 'Inherits from parent' : 'No direct variant types mapped' }}
                                </span>
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="openEditMappings(cat)"
                            class="self-start rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-bold text-slate-700 transition hover:border-[#087F8C] hover:text-[#087F8C] sm:self-center"
                        >
                            Configure Types
                        </button>
                    </div>
                </div>
            </div>

            <!-- TAB 3: CATEGORY COVERAGE REPORT -->
            <div v-else-if="currentTab === 'coverage'" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 border-b border-slate-100 pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-black text-slate-800">Category Coverage Report</h2>
                        <p class="text-xs text-slate-500">Comprehensive audit of all catalog categories, mapped variant options, and review flags.</p>
                    </div>

                    <!-- Filter pills -->
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="coverageFilter = 'all'"
                            :class="[
                                'rounded-lg px-3 py-1.5 text-[11px] font-bold transition',
                                coverageFilter === 'all' ? 'bg-[#087F8C] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                            ]"
                        >
                            All ({{ coverage.length }})
                        </button>
                        <button
                            type="button"
                            @click="coverageFilter = 'covered'"
                            :class="[
                                'rounded-lg px-3 py-1.5 text-[11px] font-bold transition',
                                coverageFilter === 'covered' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                            ]"
                        >
                            Covered ({{ coverage.filter(c => c.status === 'Covered').length }})
                        </button>
                        <button
                            type="button"
                            @click="coverageFilter = 'review'"
                            :class="[
                                'rounded-lg px-3 py-1.5 text-[11px] font-bold transition',
                                coverageFilter === 'review' ? 'bg-amber-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                            ]"
                        >
                            Requires Review ({{ coverage.filter(c => c.status === 'Requires Review').length }})
                        </button>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Category</th>
                                <th class="px-4 py-3">Parent Group</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Effective Types</th>
                                <th class="px-4 py-3 text-right">Available Options</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="item in filteredCoverage" :key="item.id" class="hover:bg-slate-50/60">
                                <td class="px-4 py-3 font-bold text-slate-800">
                                    <span v-if="item.is_subcategory" class="text-slate-300 mr-1.5">↳</span>
                                    {{ item.name }}
                                </td>
                                <td class="px-4 py-3 text-slate-500">{{ item.parent_name || 'Root Category' }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="[
                                            'inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wider',
                                            item.status === 'Covered'
                                                ? 'bg-emerald-50 text-emerald-700'
                                                : 'bg-amber-50 text-amber-700'
                                        ]"
                                    >
                                        {{ item.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-for="tname in item.effective_type_names"
                                            :key="tname"
                                            class="rounded bg-slate-100 px-1.5 py-0.5 text-[9px] font-semibold text-slate-600"
                                        >
                                            {{ tname }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-slate-700">
                                    {{ item.total_predefined_options }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MODAL: CREATE VARIANT TYPE -->
        <div v-if="showCreateTypeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-xs">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-base font-extrabold text-slate-800">Add Predefined Variant Type</h3>
                <p class="mt-0.5 text-xs text-slate-500">Create an approved variant type to add to the master marketplace catalog.</p>

                <form @submit.prevent="submitCreateType" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700">Type Name</label>
                        <input
                            v-model="typeForm.name"
                            type="text"
                            required
                            placeholder="e.g. Fit, RAM, Flavor"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3.5 py-2 text-xs text-slate-800 outline-none focus:border-[#087F8C]"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700">Display Style</label>
                        <select
                            v-model="typeForm.display_type"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3.5 py-2 text-xs text-slate-800 outline-none focus:border-[#087F8C]"
                        >
                            <option value="pills">Pills / Clickable Badges</option>
                            <option value="color_swatch">Color Swatches (with hex dot)</option>
                            <option value="select">Dropdown Select</option>
                            <option value="button">Square Buttons</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700">Description</label>
                        <textarea
                            v-model="typeForm.description"
                            rows="2"
                            placeholder="Brief description for sellers..."
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3.5 py-2 text-xs text-slate-800 outline-none focus:border-[#087F8C]"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button
                            type="button"
                            @click="showCreateTypeModal = false"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="typeForm.processing"
                            class="rounded-xl bg-[#087F8C] px-4 py-2 text-xs font-bold text-white transition hover:bg-[#065A63]"
                        >
                            Save Type
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: ADD PREDEFINED VALUE -->
        <div v-if="showAddValueModal && selectedType" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-xs">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-base font-extrabold text-slate-800">Add Value to {{ selectedType.name }}</h3>
                <p class="mt-0.5 text-xs text-slate-500">Only Super Admin approved values can be selected by sellers.</p>

                <form @submit.prevent="submitAddValue" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700">Option Value</label>
                        <input
                            v-model="valueForm.value"
                            type="text"
                            required
                            placeholder="e.g. XL, 16GB, Ube"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3.5 py-2 text-xs text-slate-800 outline-none focus:border-[#087F8C]"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700">Display Label</label>
                        <input
                            v-model="valueForm.label"
                            type="text"
                            placeholder="Optional descriptive label"
                            class="mt-1 w-full rounded-xl border border-slate-200 px-3.5 py-2 text-xs text-slate-800 outline-none focus:border-[#087F8C]"
                        />
                    </div>

                    <div v-if="selectedType.display_type === 'color_swatch'">
                        <label class="block text-xs font-semibold text-slate-700">Hex Color</label>
                        <div class="mt-1 flex items-center gap-2">
                            <input
                                v-model="valueForm.hex"
                                type="color"
                                class="h-8 w-12 cursor-pointer rounded border border-slate-200"
                            />
                            <input
                                v-model="valueForm.hex"
                                type="text"
                                class="w-full rounded-xl border border-slate-200 px-3.5 py-1.5 font-mono text-xs text-slate-800 outline-none focus:border-[#087F8C]"
                            />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button
                            type="button"
                            @click="showAddValueModal = false"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="rounded-xl bg-[#087F8C] px-4 py-2 text-xs font-bold text-white transition hover:bg-[#065A63]"
                        >
                            Add Value
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: CONFIGURE CATEGORY MAPPINGS -->
        <div v-if="showEditMappingModal && mappingTargetCategory" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-xs">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-base font-extrabold text-slate-800">Configure Variant Types for {{ mappingTargetCategory.name }}</h3>
                <p class="mt-0.5 text-xs text-slate-500">Select which predefined variant types are enabled for this category.</p>

                <form @submit.prevent="submitMappings" class="mt-4 space-y-4">
                    <div class="max-h-72 space-y-2 overflow-y-auto pr-1">
                        <label
                            v-for="type in variantTypes"
                            :key="type.id"
                            class="flex items-center justify-between rounded-xl border border-slate-200 p-3 hover:bg-slate-50 cursor-pointer"
                        >
                            <div class="flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    :value="type.id"
                                    v-model="mappingForm.variant_type_ids"
                                    class="h-4 w-4 rounded border-slate-300 text-[#087F8C] focus:ring-[#087F8C]"
                                />
                                <div>
                                    <p class="text-xs font-bold text-slate-800">{{ type.name }}</p>
                                    <p class="text-[10px] text-slate-400">{{ type.values ? type.values.length : 0 }} predefined options</p>
                                </div>
                            </div>
                            <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600">
                                {{ type.display_type }}
                            </span>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button
                            type="button"
                            @click="showEditMappingModal = false"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="mappingForm.processing"
                            class="rounded-xl bg-[#087F8C] px-4 py-2 text-xs font-bold text-white transition hover:bg-[#065A63]"
                        >
                            Save Mappings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
