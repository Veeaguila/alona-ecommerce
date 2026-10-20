<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    title: { type: String, default: 'Admin Activity Audit Logs' },
    eyebrow: { type: String, default: 'Security & Compliance' },
    description: { type: String, default: '' },
    active: { type: String, default: 'audit_logs' },
    logs: { type: Object, default: () => ({ data: [] }) },
    actionTypes: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ search: '', action: 'all' }) },
})

const search = ref(props.filters.search)
const action = ref(props.filters.action)

const selectedLog = ref(null)
const showPayloadModal = ref(false)

const applyFilters = () => {
    router.get(
        route('admin.audit-logs'),
        { search: search.value, action: action.value },
        { preserveState: true, replace: true }
    )
}

const viewPayload = (log) => {
    selectedLog.value = log
    showPayloadModal.value = true
}

const formatDate = (dateStr) => {
    if (!dateStr) return '—'
    const date = new Date(dateStr)
    return date.toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    })
}
</script>

<template>
    <Head :title="title" />

    <AdminLayout :active="active">
        <div class="mx-auto max-w-7xl">
            <!-- Header -->
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

            <!-- Filters -->
            <div class="mt-6 flex flex-wrap items-center gap-3 rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm">
                <div class="min-w-[240px] flex-1">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search logs by description, action, or admin name..."
                        class="w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-2 text-xs text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white"
                        @keyup.enter="applyFilters"
                    />
                </div>

                <select
                    v-model="action"
                    class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2 text-xs text-[#1F2937] outline-none focus:border-[#087F8C]"
                    @change="applyFilters"
                >
                    <option value="all">All Action Types</option>
                    <option v-for="act in actionTypes" :key="act" :value="act">{{ act }}</option>
                </select>

                <button
                    type="button"
                    class="rounded-xl bg-[#087F8C] px-4 py-2 text-xs font-semibold text-white transition hover:bg-[#066D78]"
                    @click="applyFilters"
                >
                    Filter
                </button>
            </div>

            <!-- Audit Logs Table -->
            <div class="mt-6 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-[#E5E7EB] bg-[#F8FAF9] text-[10px] font-bold uppercase tracking-wider text-[#64748B]">
                            <tr>
                                <th class="px-5 py-3.5">Timestamp</th>
                                <th class="px-5 py-3.5">Administrator</th>
                                <th class="px-5 py-3.5">Action Code</th>
                                <th class="px-5 py-3.5">Activity Description</th>
                                <th class="px-5 py-3.5 text-center">IP Address</th>
                                <th class="px-5 py-3.5 text-right">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E5E7EB]">
                            <tr v-if="!logs.data.length">
                                <td colspan="6" class="px-5 py-8 text-center text-[#94A3B8]">
                                    No administrative activity logs recorded yet.
                                </td>
                            </tr>
                            <tr v-for="log in logs.data" :key="log.id" class="hover:bg-[#F8FAF9]/60">
                                <td class="whitespace-nowrap px-5 py-4 font-mono text-[11px] text-[#64748B]">
                                    {{ formatDate(log.created_at) }}
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-[#E8F7F6] text-xs font-bold text-[#087F8C]">
                                            {{ log.admin?.name?.charAt(0) || 'A' }}
                                        </div>
                                        <div>
                                            <p class="font-bold text-[#1F2937]">{{ log.admin?.name || 'Administrator' }}</p>
                                            <p class="text-[10px] text-[#94A3B8]">{{ log.admin?.email || '' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4 font-mono text-[11px]">
                                    <span class="rounded bg-slate-100 px-2 py-0.5 font-semibold text-slate-700">
                                        {{ log.action }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 font-medium text-[#1F2937]">
                                    {{ log.description }}
                                </td>
                                <td class="px-5 py-4 text-center font-mono text-[11px] text-[#64748B]">
                                    {{ log.ip_address || '127.0.0.1' }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <button
                                        v-if="log.changes"
                                        type="button"
                                        class="rounded-lg border border-[#E5E7EB] bg-white px-2.5 py-1 text-xs font-semibold text-[#087F8C] transition hover:bg-[#E8F7F6]"
                                        @click="viewPayload(log)"
                                    >
                                        Payload
                                    </button>
                                    <span v-else class="text-[11px] text-[#94A3B8]">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Payload Modal -->
        <div v-if="showPayloadModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-xs">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <div class="flex items-center justify-between border-b border-[#E5E7EB] pb-3">
                    <h3 class="text-sm font-bold text-[#1F2937]">
                        Audit Payload Diffs: {{ selectedLog?.action }}
                    </h3>
                    <button type="button" class="text-slate-400 hover:text-slate-600" @click="showPayloadModal = false">
                        ✕
                    </button>
                </div>
                <div class="mt-4">
                    <pre class="max-h-72 overflow-y-auto rounded-xl bg-slate-900 p-3.5 text-xs font-mono text-emerald-400">{{ JSON.stringify(selectedLog?.changes, null, 2) }}</pre>
                </div>
                <div class="mt-4 flex justify-end border-t border-[#E5E7EB] pt-3">
                    <button
                        type="button"
                        class="rounded-xl bg-[#087F8C] px-4 py-1.5 text-xs font-semibold text-white hover:bg-[#066D78]"
                        @click="showPayloadModal = false"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

