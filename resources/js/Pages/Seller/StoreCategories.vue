<template>
    <SellerLayout>
        <Head title="Store Categories - Seller Center" />

        <div class="space-y-6 pb-12">
            <!-- Header Section -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-[#1F2937]">Store Categories</h1>
                    <p class="mt-1 text-sm text-[#64748B]">
                        Organize your products into custom categories and subcategories on your storefront.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-[#087F8C] to-[#0A96A6] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:shadow-md hover:brightness-105"
                        @click="openCreateModal(null)"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Add Category
                    </button>
                </div>
            </div>

            <!-- Flash Status Message -->
            <transition name="fade">
                <div
                    v-if="$page.props.flash?.status || statusMessage"
                    class="flex items-center justify-between rounded-xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm font-medium text-teal-800"
                >
                    <div class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>{{ $page.props.flash?.status || statusMessage }}</span>
                    </div>
                    <button type="button" class="text-teal-600 hover:text-teal-900" @click="statusMessage = ''">✕</button>
                </div>
            </transition>

            <!-- Error Banner -->
            <transition name="fade">
                <div
                    v-if="errorMessage"
                    class="flex items-center justify-between rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800"
                >
                    <div class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ errorMessage }}</span>
                    </div>
                    <button type="button" class="text-rose-600 hover:text-rose-900" @click="errorMessage = ''">✕</button>
                </div>
            </transition>

            <!-- Quick Metrics -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wider text-[#64748B]">Total Categories</p>
                    <p class="mt-2 text-2xl font-black text-[#1F2937]">{{ totalCategoriesCount }}</p>
                    <p class="mt-1 text-xs text-slate-500">Root & subcategories active in store</p>
                </div>

                <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wider text-[#64748B]">Uncategorized Products</p>
                    <p class="mt-2 text-2xl font-black text-amber-600">{{ uncategorized_count ?? 0 }}</p>
                    <p class="mt-1 text-xs text-slate-500">Products not assigned to any store category</p>
                </div>

                <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                    <p class="text-xs font-bold uppercase tracking-wider text-[#64748B]">Category Nesting Limit</p>
                    <p class="mt-2 text-2xl font-black text-[#087F8C]">2 Levels</p>
                    <p class="mt-1 text-xs text-slate-500">Root categories & Level 2 subcategories</p>
                </div>
            </div>

            <!-- Categories Tree Table -->
            <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <div class="border-b border-slate-100 bg-slate-50/75 px-6 py-4">
                    <h2 class="text-base font-bold text-[#1F2937]">Store Category Hierarchy</h2>
                </div>

                <div v-if="categories.length === 0" class="py-16 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-teal-50 text-[#087F8C]">
                        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                    <p class="mt-4 text-base font-bold text-[#1F2937]">No store categories created yet</p>
                    <p class="mt-1 text-sm text-[#64748B]">Create custom categories to help buyers navigate your products.</p>
                    <button
                        type="button"
                        class="mt-4 inline-flex items-center gap-2 rounded-xl bg-[#087F8C] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:brightness-105"
                        @click="openCreateModal(null)"
                    >
                        + Create Your First Category
                    </button>
                </div>

                <div v-else class="divide-y divide-slate-100">
                    <template v-for="cat in categories" :key="cat.id">
                        <!-- Root Category Row -->
                        <div class="flex flex-col gap-3 px-6 py-4 transition hover:bg-slate-50/70 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-[#087F8C] font-bold text-xs">
                                    L1
                                </span>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-bold text-[#1F2937]">{{ cat.name }}</span>
                                        <span
                                            v-if="cat.is_visible"
                                            class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 border border-emerald-200"
                                        >
                                            Visible
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 border border-slate-200"
                                        >
                                            Hidden
                                        </span>
                                    </div>
                                    <p class="text-xs text-[#64748B] mt-0.5">
                                        Slug: <code class="text-slate-500 font-mono">{{ cat.slug }}</code> •
                                        {{ cat.products_count ?? 0 }} product(s) •
                                        {{ (cat.children || []).length }} subcategory(ies)
                                    </p>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-2 shrink-0">
                                <button
                                    type="button"
                                    class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-[#087F8C] hover:bg-teal-50"
                                    @click="openCreateModal(cat.id)"
                                >
                                    + Subcategory
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                    @click="toggleVisibility(cat)"
                                >
                                    {{ cat.is_visible ? 'Hide' : 'Show' }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                    @click="openEditModal(cat)"
                                >
                                    Edit
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                                    @click="confirmDelete(cat)"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>

                        <!-- Subcategories (Level 2) -->
                        <div
                            v-for="sub in cat.children || []"
                            :key="sub.id"
                            class="flex flex-col gap-3 bg-slate-50/40 pl-14 pr-6 py-3 border-t border-slate-100 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="text-slate-400 font-mono">└─</span>
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded bg-slate-200 text-slate-600 font-bold text-[10px]">
                                    L2
                                </span>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold text-[#1F2937]">{{ sub.name }}</span>
                                        <span
                                            v-if="sub.is_visible"
                                            class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700"
                                        >
                                            Visible
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600"
                                        >
                                            Hidden
                                        </span>
                                    </div>
                                    <p class="text-xs text-[#64748B]">
                                        {{ sub.products_count ?? 0 }} product(s)
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <button
                                    type="button"
                                    class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                    @click="toggleVisibility(sub)"
                                >
                                    {{ sub.is_visible ? 'Hide' : 'Show' }}
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                    @click="openEditModal(sub)"
                                >
                                    Edit
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                                    @click="confirmDelete(sub)"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Create Category Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                <h3 class="text-lg font-bold text-[#1F2937]">
                    {{ createForm.parent_id ? 'Add Subcategory (Level 2)' : 'Add Root Store Category (Level 1)' }}
                </h3>

                <form @submit.prevent="submitCreate" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-[#64748B]">Category Name *</label>
                        <input
                            v-model="createForm.name"
                            type="text"
                            maxlength="50"
                            placeholder="e.g. Summer Outfits"
                            class="mt-1 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#087F8C] focus:ring-[#087F8C]"
                            required
                        />
                        <div class="mt-1 flex justify-between text-xs text-slate-400">
                            <span>1 - 50 characters</span>
                            <span>{{ createForm.name.length }} / 50</span>
                        </div>
                        <p v-if="createForm.errors.name" class="mt-1 text-xs text-rose-600 font-medium">
                            {{ createForm.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-[#64748B]">Parent Category</label>
                        <select
                            v-model="createForm.parent_id"
                            class="mt-1 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#087F8C] focus:ring-[#087F8C]"
                        >
                            <option :value="null">None (Root Category - Level 1)</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                {{ cat.name }} (Level 1)
                            </option>
                        </select>
                        <p v-if="createForm.errors.parent_id" class="mt-1 text-xs text-rose-600 font-medium">
                            {{ createForm.errors.parent_id }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            v-model="createForm.is_visible"
                            type="checkbox"
                            id="create_is_visible"
                            class="h-4 w-4 rounded border-slate-300 text-[#087F8C] focus:ring-[#087F8C]"
                        />
                        <label for="create_is_visible" class="text-sm font-medium text-[#1F2937]">
                            Visible on Storefront
                        </label>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 pt-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="showCreateModal = false"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="createForm.processing"
                            class="rounded-xl bg-[#087F8C] px-5 py-2 text-sm font-semibold text-white shadow-sm hover:brightness-105 disabled:opacity-50"
                        >
                            Create Category
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Category Modal -->
        <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                <h3 class="text-lg font-bold text-[#1F2937]">Edit Store Category</h3>

                <form @submit.prevent="submitEdit" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-[#64748B]">Category Name *</label>
                        <input
                            v-model="editForm.name"
                            type="text"
                            maxlength="50"
                            class="mt-1 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#087F8C] focus:ring-[#087F8C]"
                            required
                        />
                        <div class="mt-1 flex justify-between text-xs text-slate-400">
                            <span>1 - 50 characters</span>
                            <span>{{ editForm.name.length }} / 50</span>
                        </div>
                        <p v-if="editForm.errors.name" class="mt-1 text-xs text-rose-600 font-medium">
                            {{ editForm.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-[#64748B]">Sort Order</label>
                        <input
                            v-model.number="editForm.sort_order"
                            type="number"
                            min="0"
                            class="mt-1 block w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-[#087F8C] focus:ring-[#087F8C]"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            v-model="editForm.is_visible"
                            type="checkbox"
                            id="edit_is_visible"
                            class="h-4 w-4 rounded border-slate-300 text-[#087F8C] focus:ring-[#087F8C]"
                        />
                        <label for="edit_is_visible" class="text-sm font-medium text-[#1F2937]">
                            Visible on Storefront
                        </label>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 pt-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            @click="showEditModal = false"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="editForm.processing"
                            class="rounded-xl bg-[#087F8C] px-5 py-2 text-sm font-semibold text-white shadow-sm hover:brightness-105 disabled:opacity-50"
                        >
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Deletion Warning Confirmation Modal (SEL-SCAT-009, SEL-SCAT-010) -->
        <div v-if="showDeleteModal && categoryToDelete" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-[#1F2937]">Delete Store Category?</h3>
                        <p class="text-xs text-[#64748B]">Action cannot be undone</p>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-900 space-y-2">
                    <p class="font-bold">⚠️ Warning: Products will NOT be deleted!</p>
                    <p>
                        Deleting <strong>"{{ categoryToDelete.name }}"</strong> will remove this category from your storefront.
                        All assigned products will be moved to <strong>Uncategorized</strong> and will remain active and buyable in your store.
                    </p>
                    <div v-if="categoryToDelete.children && categoryToDelete.children.length > 0" class="pt-1">
                        <p class="font-semibold text-amber-950">
                            Affected subcategories ({{ categoryToDelete.children.length }}):
                        </p>
                        <ul class="list-disc pl-4 mt-1 space-y-0.5">
                            <li v-for="child in categoryToDelete.children" :key="child.id">
                                {{ child.name }} ({{ child.products_count ?? 0 }} products)
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="showDeleteModal = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-rose-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-700"
                        @click="executeDelete"
                    >
                        Confirm Delete
                    </button>
                </div>
            </div>
        </div>
    </SellerLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    uncategorized_count: {
        type: Number,
        default: 0,
    },
    products: {
        type: Array,
        default: () => [],
    },
})

const statusMessage = ref('')
const errorMessage = ref('')

const totalCategoriesCount = computed(() => {
    let count = props.categories.length
    for (const cat of props.categories) {
        if (cat.children) {
            count += cat.children.length
        }
    }
    return count
})

// Create Modal State
const showCreateModal = ref(false)
const createForm = useForm({
    name: '',
    parent_id: null,
    is_visible: true,
})

const openCreateModal = (parentId = null) => {
    createForm.reset()
    createForm.parent_id = parentId
    createForm.is_visible = true
    showCreateModal.value = true
}

const submitCreate = () => {
    createForm.post(route('seller.categories.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false
            createForm.reset()
        },
        onError: (errors) => {
            if (errors.name) errorMessage.value = errors.name
        },
    })
}

// Edit Modal State
const showEditModal = ref(false)
const editForm = useForm({
    id: null,
    name: '',
    sort_order: 0,
    is_visible: true,
})

const openEditModal = (cat) => {
    editForm.reset()
    editForm.id = cat.id
    editForm.name = cat.name
    editForm.sort_order = cat.sort_order ?? 0
    editForm.is_visible = Boolean(cat.is_visible)
    showEditModal.value = true
}

const submitEdit = () => {
    editForm.patch(route('seller.categories.update', editForm.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false
            editForm.reset()
        },
        onError: (errors) => {
            if (errors.name) errorMessage.value = errors.name
        },
    })
}

// Visibility Toggle (SEL-SCAT-007, SEL-SCAT-008)
const toggleVisibility = (cat) => {
    router.patch(
        route('seller.categories.update', cat.id),
        { is_visible: !cat.is_visible },
        { preserveScroll: true }
    )
}

// Deletion Confirmation Modal State (SEL-SCAT-009, SEL-SCAT-010)
const showDeleteModal = ref(false)
const categoryToDelete = ref(null)

const confirmDelete = (cat) => {
    categoryToDelete.value = cat
    showDeleteModal.value = true
}

const executeDelete = () => {
    if (!categoryToDelete.value) return
    router.delete(route('seller.categories.destroy', categoryToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteModal.value = false
            categoryToDelete.value = null
        },
    })
}
</script>

