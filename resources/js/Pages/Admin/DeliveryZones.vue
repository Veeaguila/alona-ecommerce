<script setup>
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    title: { type: String, default: 'Shipping Rates & Hub Zones' },
    eyebrow: { type: String, default: 'Logistics Grid' },
    description: { type: String, default: '' },
    active: { type: String, default: 'shipping_rates' },
    zones: { type: Array, default: () => [] },
    sortingCenters: { type: Array, default: () => [] },
})

const showModal = ref(false)
const isEditing = ref(false)
const selectedZone = ref(null)

const form = useForm({
    name: '',
    province: 'Bohol',
    base_shipping_rate: 50.00,
    per_kg_rate: 10.00,
    estimated_days: '1-2 Business Days',
    is_active: true,
    sorting_center_id: '',
})

const openCreateModal = () => {
    isEditing.value = false
    selectedZone.value = null
    form.reset()
    form.clearErrors()
    form.province = 'Bohol'
    form.base_shipping_rate = 50.00
    form.per_kg_rate = 10.00
    form.estimated_days = '1-2 Business Days'
    form.is_active = true
    form.sorting_center_id = props.sortingCenters[0]?.id || ''
    showModal.value = true
}

const openEditModal = (zone) => {
    isEditing.value = true
    selectedZone.value = zone
    form.clearErrors()
    form.name = zone.name
    form.province = zone.province
    form.base_shipping_rate = zone.base_shipping_rate
    form.per_kg_rate = zone.per_kg_rate
    form.estimated_days = zone.estimated_days
    form.is_active = Boolean(zone.is_active)
    form.sorting_center_id = zone.sorting_center_id || ''
    showModal.value = true
}

const closeModal = () => {
    showModal.value = false
}

const submitForm = () => {
    if (isEditing.value) {
        form.patch(route('admin.shipping-rates.update', selectedZone.value.id), {
            onSuccess: () => closeModal(),
        })
    } else {
        form.post(route('admin.shipping-rates.store'), {
            onSuccess: () => closeModal(),
        })
    }
}

const deleteZone = (zone) => {
    if (confirm(`Are you sure you want to remove delivery zone "${zone.name}"?`)) {
        router.delete(route('admin.shipping-rates.destroy', zone.id))
    }
}
</script>

<template>
    <Head :title="title" />

    <AdminLayout :active="active">
        <div class="mx-auto max-w-7xl">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full bg-[#F4B942]"></span>
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-[#087F8C]">
                            {{ eyebrow }}
                        </p>
                    </div>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight text-[#1F2937] sm:text-3xl">
                        {{ title }}
                    </h1>
                    <p v-if="description" class="mt-1 text-sm text-[#64748B]">
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
                    Add Delivery Zone
                </button>
            </div>

            <!-- Tax-free Policy Banner -->
            <div class="mt-6 flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 text-xs text-emerald-800">
                <svg class="h-5 w-5 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p>
                    <strong class="font-bold">Tax-Free Marketplace Policy:</strong> All delivery rates configured below are billed directly as pure logistics service fees. Zero sales tax or value-added tax is applied to shipping charges.
                </p>
            </div>

            <!-- Delivery Zones Grid -->
            <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-2">
                <div
                    v-for="zone in zones"
                    :key="zone.id"
                    class="flex flex-col justify-between rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-sm transition hover:border-[#16A6A0]/40"
                >
                    <div>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <span class="rounded bg-[#E8F7F6] px-2 py-0.5 text-[10px] font-bold text-[#087F8C]">
                                    {{ zone.province }}
                                </span>
                                <h3 class="mt-1.5 text-base font-bold text-[#1F2937]">
                                    {{ zone.name }}
                                </h3>
                            </div>
                            <span
                                class="rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                                :class="zone.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                            >
                                {{ zone.is_active ? 'Covered' : 'Disabled' }}
                            </span>
                        </div>

                        <!-- Rates display -->
                        <div class="mt-4 grid grid-cols-2 gap-3 rounded-xl bg-[#F8FAF9] p-3 text-xs">
                            <div>
                                <p class="text-[10px] uppercase font-bold text-[#94A3B8]">Base Shipping Rate</p>
                                <p class="text-base font-extrabold text-[#087F8C]">₱{{ Number(zone.base_shipping_rate).toFixed(2) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase font-bold text-[#94A3B8]">Per KG Extra</p>
                                <p class="text-base font-extrabold text-[#1F2937]">₱{{ Number(zone.per_kg_rate).toFixed(2) }}</p>
                            </div>
                        </div>

                        <div class="mt-3 flex items-center justify-between text-xs text-[#64748B]">
                            <span>Delivery Window: <strong>{{ zone.estimated_days }}</strong></span>
                            <span>Hub: <strong>{{ zone.sorting_center?.business_name || 'Central / Auto' }}</strong></span>
                        </div>
                    </div>

                    <div class="mt-5 flex items-center justify-end gap-2 border-t border-[#E5E7EB] pt-3">
                        <button
                            type="button"
                            class="rounded-lg border border-[#E5E7EB] bg-white px-3 py-1.5 text-xs font-semibold text-[#087F8C] transition hover:bg-[#E8F7F6]"
                            @click="openEditModal(zone)"
                        >
                            Adjust Rates
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-50"
                            @click="deleteZone(zone)"
                        >
                            Remove
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-xs">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <div class="flex items-center justify-between border-b border-[#E5E7EB] pb-3">
                    <h3 class="text-base font-bold text-[#1F2937]">
                        {{ isEditing ? 'Edit Delivery Zone & Rates' : 'Create Delivery Zone' }}
                    </h3>
                    <button type="button" class="text-slate-400 hover:text-slate-600" @click="closeModal">
                        ✕
                    </button>
                </div>

                <form class="mt-4 space-y-4" @submit.prevent="submitForm">
                    <div>
                        <label class="block text-xs font-semibold text-[#1F2937]">Zone / Area Name *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="e.g. Tagbilaran Central, Tubigon District"
                            class="mt-1 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-2 text-xs text-[#1F2937] outline-none focus:border-[#087F8C] focus:bg-white"
                        />
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-semibold text-[#1F2937]">Province / Region *</label>
                            <input
                                v-model="form.province"
                                type="text"
                                required
                                class="mt-1 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-2 text-xs text-[#1F2937] outline-none focus:border-[#087F8C] focus:bg-white"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[#1F2937]">Base Rate (₱) *</label>
                            <input
                                v-model="form.base_shipping_rate"
                                type="number"
                                step="0.01"
                                required
                                class="mt-1 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-2 text-xs text-[#1F2937] outline-none focus:border-[#087F8C] focus:bg-white"
                            />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-semibold text-[#1F2937]">Per KG Rate (₱)</label>
                            <input
                                v-model="form.per_kg_rate"
                                type="number"
                                step="0.01"
                                class="mt-1 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-2 text-xs text-[#1F2937] outline-none focus:border-[#087F8C] focus:bg-white"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-[#1F2937]">Estimated Window</label>
                            <input
                                v-model="form.estimated_days"
                                type="text"
                                placeholder="1-2 Business Days"
                                class="mt-1 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-2 text-xs text-[#1F2937] outline-none focus:border-[#087F8C] focus:bg-white"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-[#1F2937]">Assigned Sorting Center Hub</label>
                        <select
                            v-model="form.sorting_center_id"
                            class="mt-1 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2 text-xs text-[#1F2937] outline-none focus:border-[#087F8C]"
                        >
                            <option value="">Auto / Central Dispatch</option>
                            <option v-for="hub in sortingCenters" :key="hub.id" :value="hub.id">
                                {{ hub.business_name }}
                            </option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2 pt-2">
                        <input
                            id="z-active"
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-[#087F8C] focus:ring-[#087F8C]"
                        />
                        <label for="z-active" class="cursor-pointer text-xs font-medium text-[#1F2937]">
                            Zone actively covered for order checkout and courier routing
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
                            {{ form.processing ? 'Saving...' : (isEditing ? 'Update Zone' : 'Save Zone') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

