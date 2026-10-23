<script setup>
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    title: { type: String, default: 'Platform Vouchers & Campaigns' },
    eyebrow: { type: String, default: 'Promotions Engine' },
    description: { type: String, default: '' },
    active: { type: String, default: 'vouchers' },
    vouchers: { type: Object, default: () => ({ data: [] }) },
    campaigns: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ search: '', status: 'all' }) },
})

const search = ref(props.filters.search)
const status = ref(props.filters.status)

const showModal = ref(false)
const isEditing = ref(false)
const selectedVoucher = ref(null)

const form = useForm({
    code: '',
    type: 'fixed',
    value: '',
    min_spend: 0,
    max_discount: '',
    usage_limit: 100,
    per_customer_limit: 1,
    starts_at: '',
    expires_at: '',
    is_active: true,
})

const applyFilters = () => {
    router.get(
        route('admin.vouchers'),
        { search: search.value, status: status.value },
        { preserveState: true, replace: true }
    )
}

const openCreateModal = () => {
    isEditing.value = false
    selectedVoucher.value = null
    form.reset()
    form.clearErrors()
    form.is_active = true
    form.starts_at = new Date().toISOString().split('T')[0]
    const nextMonth = new Date()
    nextMonth.setMonth(nextMonth.getMonth() + 1)
    form.expires_at = nextMonth.toISOString().split('T')[0]
    showModal.value = true
}

const openEditModal = (v) => {
    isEditing.value = true
    selectedVoucher.value = v
    form.clearErrors()
    form.code = v.code
    form.type = v.type
    form.value = v.value
    form.min_spend = v.min_spend || 0
    form.max_discount = v.max_discount || ''
    form.usage_limit = v.usage_limit || 100
    form.per_customer_limit = v.per_customer_limit || 1
    form.starts_at = v.starts_at || ''
    form.expires_at = v.expires_at || ''
    form.is_active = Boolean(v.is_active)
    showModal.value = true
}

const closeModal = () => {
    showModal.value = false
}

const submitForm = () => {
    if (isEditing.value) {
        form.patch(route('admin.vouchers.update', selectedVoucher.value.id), {
            onSuccess: () => closeModal(),
        })
    } else {
        form.post(route('admin.vouchers.store'), {
            onSuccess: () => closeModal(),
        })
    }
}

const deleteVoucher = (v) => {
    if (confirm(`Are you sure you want to delete platform voucher "${v.code}"?`)) {
        router.delete(route('admin.vouchers.destroy', v.id))
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

                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl bg-[#087F8C] px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#066D78]"
                    @click="openCreateModal"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Create Platform Voucher
                </button>
            </div>

            <!-- Filters -->
            <div class="mt-6 flex flex-wrap items-center gap-3 rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm">
                <div class="min-w-[240px] flex-1">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search vouchers by code..."
                        class="w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-2 text-xs text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white"
                        @keyup.enter="applyFilters"
                    />
                </div>

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

            <!-- Vouchers Table -->
            <div class="mt-6 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-[#E5E7EB] bg-[#F8FAF9] text-[10px] font-bold uppercase tracking-wider text-[#64748B]">
                            <tr>
                                <th class="px-5 py-3.5">Voucher Code</th>
                                <th class="px-5 py-3.5">Discount Benefit</th>
                                <th class="px-5 py-3.5">Min Spend</th>
                                <th class="px-5 py-3.5 text-center">Claimed / Limit</th>
                                <th class="px-5 py-3.5">Validity Period</th>
                                <th class="px-5 py-3.5 text-center">Status</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E5E7EB]">
                            <tr v-if="!vouchers.data.length">
                                <td colspan="7" class="px-5 py-8 text-center text-[#94A3B8]">
                                    No platform vouchers found. Click "Create Platform Voucher" to create a site-wide discount code.
                                </td>
                            </tr>
                            <tr v-for="v in vouchers.data" :key="v.id" class="hover:bg-[#F8FAF9]/60">
                                <td class="px-5 py-4 font-mono font-bold text-[#087F8C]">
                                    <div class="flex items-center gap-2">
                                        <span class="rounded bg-[#E8F7F6] px-2 py-0.5 text-xs text-[#087F8C]">
                                            {{ v.code }}
                                        </span>
                                        <span class="rounded bg-amber-50 px-1.5 py-0.5 text-[9px] font-semibold text-amber-700">
                                            Platform
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-4 font-semibold text-[#1F2937]">
                                    <span v-if="v.type === 'fixed'">₱{{ Number(v.value).toFixed(2) }} OFF</span>
                                    <span v-else-if="v.type === 'percentage'">{{ Number(v.value) }}% OFF (Max ₱{{ v.max_discount || 'None' }})</span>
                                    <span v-else class="text-emerald-600 font-bold">Free Shipping</span>
                                </td>
                                <td class="px-5 py-4 text-[#64748B]">
                                    {{ Number(v.min_spend) > 0 ? `₱${Number(v.min_spend).toFixed(2)}` : 'No minimum' }}
                                </td>
                                <td class="px-5 py-4 text-center text-[#1F2937] font-medium">
                                    {{ v.user_vouchers_count ?? 0 }} / {{ v.usage_limit ?? '∞' }}
                                </td>
                                <td class="px-5 py-4 text-[11px] text-[#64748B]">
                                    {{ v.starts_at || '—' }} to {{ v.expires_at || '—' }}
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                                        :class="v.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                                    >
                                        {{ v.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            class="rounded-lg border border-[#E5E7EB] bg-white px-2.5 py-1 text-xs font-semibold text-[#087F8C] transition hover:bg-[#E8F7F6]"
                                            @click="openEditModal(v)"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-red-200 bg-white px-2.5 py-1 text-xs font-semibold text-red-600 transition hover:bg-red-50"
                                            @click="deleteVoucher(v)"
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
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
                <div class="flex items-center justify-between border-b border-[#E5E7EB] pb-3">
                    <h3 class="text-base font-bold text-[#1F2937]">
                        {{ isEditing ? 'Edit Platform Voucher' : 'Create Platform Voucher' }}
                    </h3>
                    <button type="button" class="text-slate-400 hover:text-slate-600" @click="closeModal">
                        ✕
                    </button>
                </div>

                <form class="mt-4 space-y-4" @submit.prevent="submitForm">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-semibold text-[#1F2937]">Voucher Code *</label>
                            <input
                                v-model="form.code"
                                type="text"
                                required
                                placeholder="e.g. PLATFORM100, FREESHIP"
                                class="mt-1 w-full uppercase rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-2 text-xs font-mono font-bold text-[#1F2937] outline-none focus:border-[#087F8C] focus:bg-white"
                            />
                            <p v-if="form.errors.code" class="mt-1 text-[11px] text-red-500">{{ form.errors.code }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[#1F2937]">Discount Type *</label>
                            <select
                                v-model="form.type"
                                class="mt-1 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2 text-xs text-[#1F2937] outline-none focus:border-[#087F8C]"
                            >
                                <option value="fixed">Fixed Amount (₱ OFF)</option>
                                <option value="percentage">Percentage (% OFF)</option>
                                <option value="free_shipping">Free Shipping</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="block text-xs font-semibold text-[#1F2937]">Discount Value *</label>
                            <input
                                v-model="form.value"
                                type="number"
                                step="0.01"
                                required
                                placeholder="₱ or %"
                                class="mt-1 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-2 text-xs text-[#1F2937] outline-none focus:border-[#087F8C] focus:bg-white"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[#1F2937]">Min Spend (₱)</label>
                            <input
                                v-model="form.min_spend"
                                type="number"
                                step="0.01"
                                placeholder="0.00"
                                class="mt-1 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-2 text-xs text-[#1F2937] outline-none focus:border-[#087F8C] focus:bg-white"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[#1F2937]">Max Discount (₱)</label>
                            <input
                                v-model="form.max_discount"
                                type="number"
                                step="0.01"
                                placeholder="Optional"
                                class="mt-1 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-2 text-xs text-[#1F2937] outline-none focus:border-[#087F8C] focus:bg-white"
                            />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-semibold text-[#1F2937]">Total Usage Limit</label>
                            <input
                                v-model="form.usage_limit"
                                type="number"
                                placeholder="100"
                                class="mt-1 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-2 text-xs text-[#1F2937] outline-none focus:border-[#087F8C] focus:bg-white"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[#1F2937]">Limit Per Customer</label>
                            <input
                                v-model="form.per_customer_limit"
                                type="number"
                                placeholder="1"
                                class="mt-1 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-2 text-xs text-[#1F2937] outline-none focus:border-[#087F8C] focus:bg-white"
                            />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-semibold text-[#1F2937]">Starts At</label>
                            <input
                                v-model="form.starts_at"
                                type="date"
                                class="mt-1 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-2 text-xs text-[#1F2937] outline-none focus:border-[#087F8C] focus:bg-white"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[#1F2937]">Expires At</label>
                            <input
                                v-model="form.expires_at"
                                type="date"
                                class="mt-1 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-2 text-xs text-[#1F2937] outline-none focus:border-[#087F8C] focus:bg-white"
                            />
                        </div>
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <input
                            id="v-active"
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-[#087F8C] focus:ring-[#087F8C]"
                        />
                        <label for="v-active" class="cursor-pointer text-xs font-medium text-[#1F2937]">
                            Activate voucher for immediate marketplace usage
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
                            {{ form.processing ? 'Saving...' : (isEditing ? 'Update Voucher' : 'Create Voucher') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

