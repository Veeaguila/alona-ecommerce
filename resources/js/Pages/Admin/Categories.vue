<script setup>
import { ref, computed } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    title: { type: String, default: 'Categories & Taxonomy' },
    eyebrow: { type: String, default: 'Catalog Architecture' },
    description: { type: String, default: '' },
    active: { type: String, default: 'categories' },
    categories: { type: Object, default: () => ({ data: [] }) },
    rootCategories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ search: '', status: 'all', type: 'all' }) },
})

const search = ref(props.filters.search)
const status = ref(props.filters.status)
const type = ref(props.filters.type)

const showModal = ref(false)
const isEditing = ref(false)
const selectedCategory = ref(null)

const form = useForm({
    parent_id: '',
    name: '',
    slug: '',
    image_path: '',
    is_active: true,
})

const availableRootCategories = computed(() => {
    if (!isEditing.value || !selectedCategory.value) {
        return props.rootCategories
    }
    // Prevent selecting itself as parent
    return props.rootCategories.filter(cat => cat.id !== selectedCategory.value.id)
})

const applyFilters = () => {
    router.get(
        route('admin.categories'),
        { search: search.value, status: status.value, type: type.value },
        { preserveState: true, replace: true }
    )
}

const openCreateModal = (presetParentId = null) => {
    isEditing.value = false
    selectedCategory.value = null
    form.reset()
    form.clearErrors()
    form.parent_id = presetParentId || ''
    form.is_active = true
    showModal.value = true
}

const openEditModal = (cat) => {
    isEditing.value = true
    selectedCategory.value = cat
    form.clearErrors()
    form.parent_id = cat.parent_id || ''
    form.name = cat.name
    form.slug = cat.slug
    form.image_path = cat.image_path || ''
    form.is_active = Boolean(cat.is_active)
    showModal.value = true
}

const closeModal = () => {
    showModal.value = false
}

const submitForm = () => {
    const payload = {
        ...form.data(),
        parent_id: form.parent_id ? Number(form.parent_id) : null,
    }

    if (isEditing.value) {
        form.transform(() => payload).patch(route('admin.categories.update', selectedCategory.value.id), {
            onSuccess: () => closeModal(),
        })
    } else {
        form.transform(() => payload).post(route('admin.categories.store'), {
            onSuccess: () => closeModal(),
        })
    }
}

const deleteCategory = (cat) => {
    if (confirm(`Are you sure you want to delete category "${cat.name}"?`)) {
        router.delete(route('admin.categories.destroy', cat.id))
    }
}
</script>

<template>
    <Head :title="title" />

    <AdminLayout :active="active">
        <div class="w-full">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-[#087F8C]">
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                        <span>{{ eyebrow }}</span>
                    </div>
                    <h1 class="mt-1 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                        {{ title }}
                    </h1>
                    <p v-if="description" class="mt-0.5 text-xs text-slate-500">
                        {{ description }}
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-[#087F8C] px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#066D78]"
                        @click="openCreateModal()"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        New Category
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="mt-6 flex flex-wrap items-center gap-3 rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm">
                <div class="min-w-[240px] flex-1">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search categories by name or slug..."
                        class="w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-2 text-xs text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white"
                        @keyup.enter="applyFilters"
                    />
                </div>

                <select
                    v-model="type"
                    class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2 text-xs text-[#1F2937] outline-none focus:border-[#087F8C]"
                    @change="applyFilters"
                >
                    <option value="all">All Levels</option>
                    <option value="root">Top-Level Categories</option>
                    <option value="sub">Subcategories</option>
                </select>

                <select
                    v-model="status"
                    class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2 text-xs text-[#1F2937] outline-none focus:border-[#087F8C]"
                    @change="applyFilters"
                >
                    <option value="all">All Statuses</option>
                    <option value="active">Active Only</option>
                    <option value="inactive">Inactive Only</option>
                </select>

                <button
                    type="button"
                    class="rounded-xl bg-[#087F8C] px-4 py-2 text-xs font-semibold text-white transition hover:bg-[#066D78]"
                    @click="applyFilters"
                >
                    Filter
                </button>
            </div>

            <!-- Categories Table -->
            <div class="mt-6 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-[#E5E7EB] bg-[#F8FAF9] text-[10px] font-bold uppercase tracking-wider text-[#64748B]">
                            <tr>
                                <th class="px-5 py-3.5">Category Name</th>
                                <th class="px-5 py-3.5">Hierarchy Level</th>
                                <th class="px-5 py-3.5">Slug</th>
                                <th class="px-5 py-3.5 text-center">Subcategories</th>
                                <th class="px-5 py-3.5 text-center">Products Linked</th>
                                <th class="px-5 py-3.5 text-center">Status</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E5E7EB]">
                            <tr v-if="!categories.data.length">
                                <td colspan="7" class="px-5 py-8 text-center text-[#94A3B8]">
                                    No categories found. Click "New Category" to create one.
                                </td>
                            </tr>
                            <tr
                                v-for="cat in categories.data"
                                :key="cat.id"
                                class="hover:bg-[#F8FAF9]/60"
                                :class="cat.parent_id ? 'bg-[#FCFDFD]' : ''"
                            >
                                <td class="px-5 py-4 font-semibold text-[#1F2937]">
                                    <div class="flex items-center gap-3">
                                        <!-- Indent visual for subcategories -->
                                        <div
                                            v-if="cat.parent_id"
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500"
                                        >
                                            <span class="text-xs font-bold">↳</span>
                                        </div>
                                        <div
                                            v-else
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                            </svg>
                                        </div>

                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span v-if="cat.parent" class="text-xs text-slate-400 font-normal">
                                                    {{ cat.parent.name }} ›
                                                </span>
                                                <p class="font-bold text-[#1F2937]" :class="cat.parent_id ? 'text-xs' : 'text-sm'">
                                                    {{ cat.name }}
                                                </p>
                                            </div>
                                            <p v-if="cat.image_path" class="text-[10px] text-[#94A3B8]">Custom banner set</p>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    <span
                                        v-if="cat.parent_id"
                                        class="inline-flex items-center gap-1 rounded-md bg-purple-50 px-2 py-0.5 text-[10px] font-bold text-purple-700 ring-1 ring-purple-200"
                                    >
                                        Subcategory
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 rounded-md bg-teal-50 px-2 py-0.5 text-[10px] font-bold text-[#087F8C] ring-1 ring-teal-200"
                                    >
                                        Top-Level
                                    </span>
                                </td>

                                <td class="px-5 py-4 font-mono text-[#64748B]">{{ cat.slug }}</td>

                                <td class="px-5 py-4 text-center font-semibold text-slate-700">
                                    <span v-if="!cat.parent_id" class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold">
                                        {{ cat.children_count ?? 0 }}
                                    </span>
                                    <span v-else class="text-slate-300">—</span>
                                </td>

                                <td class="px-5 py-4 text-center font-bold text-[#087F8C]">
                                    {{ cat.products_count ?? 0 }}
                                </td>

                                <td class="px-5 py-4 text-center">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                                        :class="cat.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                                    >
                                        {{ cat.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>

                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Quick add subcategory button for root categories -->
                                        <button
                                            v-if="!cat.parent_id"
                                            type="button"
                                            class="rounded-lg border border-[#087F8C]/30 bg-[#E8F7F6]/60 px-2 py-1 text-[11px] font-bold text-[#087F8C] transition hover:bg-[#E8F7F6]"
                                            title="Add subcategory under this category"
                                            @click="openCreateModal(cat.id)"
                                        >
                                            + Subcategory
                                        </button>

                                        <button
                                            type="button"
                                            class="rounded-lg border border-[#E5E7EB] bg-white px-2.5 py-1 text-xs font-semibold text-[#087F8C] transition hover:bg-[#E8F7F6]"
                                            @click="openEditModal(cat)"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-red-200 bg-white px-2.5 py-1 text-xs font-semibold text-red-600 transition hover:bg-red-50 disabled:opacity-40"
                                            :disabled="cat.products_count > 0 || cat.children_count > 0"
                                            :title="
                                                cat.children_count > 0
                                                    ? 'Cannot delete category with subcategories'
                                                    : (cat.products_count > 0 ? 'Cannot delete category with linked products' : 'Delete category')
                                            "
                                            @click="deleteCategory(cat)"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-xs">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <div class="flex items-center justify-between border-b border-[#E5E7EB] pb-3">
                    <h3 class="text-base font-bold text-[#1F2937]">
                        {{ isEditing ? 'Edit Category' : 'Create New Category' }}
                    </h3>
                    <button type="button" class="text-slate-400 hover:text-slate-600" @click="closeModal">
                        ✕
                    </button>
                </div>

                <form class="mt-4 space-y-4" @submit.prevent="submitForm">
                    <!-- Parent Category (Optional) -->
                    <div>
                        <label class="block text-xs font-semibold text-[#1F2937]">Parent Category</label>
                        <select
                            v-model="form.parent_id"
                            class="mt-1 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-2 text-xs text-[#1F2937] outline-none focus:border-[#087F8C] focus:bg-white"
                        >
                            <option value="">None (Top-Level Category)</option>
                            <option
                                v-for="root in availableRootCategories"
                                :key="root.id"
                                :value="root.id"
                            >
                                {{ root.name }}
                            </option>
                        </select>
                        <p class="mt-1 text-[11px] text-[#64748B]">
                            Select a parent category if this is a subcategory (e.g. "Audio" under "Electronics").
                        </p>
                        <p v-if="form.errors.parent_id" class="mt-1 text-[11px] text-red-500">{{ form.errors.parent_id }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-[#1F2937]">Category Name *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="e.g. Headphones, Men's Shoes, Laptops"
                            class="mt-1 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-2 text-xs text-[#1F2937] outline-none focus:border-[#087F8C] focus:bg-white"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-[11px] text-red-500">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-[#1F2937]">Slug (URL Key)</label>
                        <input
                            v-model="form.slug"
                            type="text"
                            placeholder="Optional: auto-generated from name"
                            class="mt-1 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-2 text-xs text-[#1F2937] outline-none focus:border-[#087F8C] focus:bg-white"
                        />
                        <p v-if="form.errors.slug" class="mt-1 text-[11px] text-red-500">{{ form.errors.slug }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-[#1F2937]">Image / Banner URL</label>
                        <input
                            v-model="form.image_path"
                            type="text"
                            placeholder="e.g. /images/categories/audio.jpg"
                            class="mt-1 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-2 text-xs text-[#1F2937] outline-none focus:border-[#087F8C] focus:bg-white"
                        />
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <input
                            id="cat-active"
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-[#087F8C] focus:ring-[#087F8C]"
                        />
                        <label for="cat-active" class="cursor-pointer text-xs font-medium text-[#1F2937]">
                            Visible to marketplace buyers and sellers (Active)
                        </label>
                    </div>

                    <div class="mt-6 flex justify-end gap-2 border-t border-[#E5E7EB] pt-4">
                        <button
                            type="button"
                            class="rounded-xl border border-[#E5E7EB] px-4 py-2 text-xs font-semibold text-[#64748B] hover:bg-slate-50"
                            @click="closeModal"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="rounded-xl bg-[#087F8C] px-5 py-2 text-xs font-bold text-white transition hover:bg-[#066D78] disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Saving...' : (isEditing ? 'Update Category' : 'Create Category') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
