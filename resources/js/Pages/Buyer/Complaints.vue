<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'

const props = defineProps({
    complaints: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
            total: 0,
        }),
    },
    current_status: {
        type: String,
        default: 'all',
    },
    counts: {
        type: Object,
        default: () => ({}),
    },
    orders: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            status: 'all',
            search: '',
        }),
    },
})

const page = usePage()
const statusMessage = computed(() => page.props.flash?.status || null)

const searchQuery = ref(props.filters.search || '')
const isModalOpen = ref(false)
const selectedComplaint = ref(null)
const isDetailModalOpen = ref(false)

const tabs = [
    { key: 'all', label: 'All Cases', countKey: 'all' },
    { key: 'pending', label: 'Pending', countKey: 'pending' },
    { key: 'reviewing', label: 'Under Review', countKey: 'reviewing' },
    { key: 'resolved', label: 'Resolved', countKey: 'resolved' },
    { key: 'rejected', label: 'Closed / Rejected', countKey: 'rejected' },
]

const form = useForm({
    subject: '',
    type: 'complaint',
    order_id: '',
    description: '',
    contact_email: '',
    evidence_images: [],
})

const openSubmitModal = () => {
    form.reset()
    form.clearErrors()
    isModalOpen.value = true
}

const closeSubmitModal = () => {
    isModalOpen.value = false
    form.reset()
}

const openDetailsModal = complaint => {
    selectedComplaint.value = complaint
    isDetailModalOpen.value = true
}

const closeDetailsModal = () => {
    isDetailModalOpen.value = false
    selectedComplaint.value = null
}

const handleFileChange = event => {
    const files = Array.from(event.target.files || [])
    form.evidence_images = files.slice(0, 5)
}

const submitComplaint = () => {
    form.post(route('buyer.complaints.store'), {
        preserveScroll: true,
        onSuccess: () => {
            closeSubmitModal()
        },
    })
}

const selectTab = key => {
    router.get(
        route('buyer.complaints'),
        {
            status: key,
            search: searchQuery.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    )
}

const handleSearch = () => {
    router.get(
        route('buyer.complaints'),
        {
            status: props.current_status || 'all',
            search: searchQuery.value || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    )
}

const clearSearch = () => {
    searchQuery.value = ''
    handleSearch()
}

const getStatusBadge = status => {
    const map = {
        pending: { label: 'Pending Review', color: 'bg-[#FFF7E6] text-[#B47A08] border-[#FED7AA]' },
        reviewing: { label: 'Under Review', color: 'bg-[#EBF5FF] text-[#2563EB] border-[#BFDBFE]' },
        resolved: { label: 'Resolved', color: 'bg-[#EAF8F1] text-[#16A34A] border-[#BBF7D0]' },
        rejected: { label: 'Closed / Rejected', color: 'bg-[#FEF2F2] text-[#DC2626] border-[#FECACA]' },
    }
    return map[status] || { label: status, color: 'bg-gray-100 text-gray-700 border-gray-200' }
}

const getTypeBadge = type => {
    const map = {
        complaint: { label: 'Complaint / Dispute', color: 'bg-[#E8F7F6] text-[#087F8C]' },
        product_report: { label: 'Product Report', color: 'bg-[#FDF2F8] text-[#DB2777]' },
        seller_report: { label: 'Seller Report', color: 'bg-[#FEF3C7] text-[#D97706]' },
        support_ticket: { label: 'Support Inquiry', color: 'bg-[#EEF2FF] text-[#4F46E5]' },
    }
    return map[type] || { label: type || 'Ticket', color: 'bg-gray-100 text-gray-700' }
}

const formatDate = dateString => {
    if (!dateString) return '—'
    const date = new Date(dateString)
    if (isNaN(date.getTime())) return '—'
    return date.toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    })
}

const imageUrl = path => {
    if (!path) return null
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/storage/')) return path
    return `/storage/${path.replace(/^\/+/, '')}`
}
</script>

<template>
    <Head title="My Complaints & Support Tickets" />

    <BuyerLayout>
        <main class="min-h-[calc(100vh-80px)] w-full min-w-0 overflow-x-hidden bg-[#F8FAF9]">
            <div class="mx-auto w-full max-w-[1536px] px-4 py-6 pb-16 sm:px-6 sm:py-8 lg:px-8 2xl:px-10 lg:py-10">

                <!-- PAGE HEADER -->
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#F4B942]"></span>
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#087F8C] sm:text-xs">
                                Dispute & Case Management
                            </p>
                        </div>

                        <h1 class="mt-1 text-2xl font-black tracking-tight text-gray-900 sm:text-3xl">
                            Complaints & Report Tracking
                        </h1>

                        <p class="mt-1 text-xs text-gray-500 sm:text-sm">
                            Track the status, investigation updates, and official administrator resolutions for your submitted complaints.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2.5">
                        <Link
                            :href="route('buyer.help')"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3.5 py-2 text-xs font-extrabold text-gray-700 shadow-sm transition hover:bg-gray-50"
                        >
                            <span>❓</span>
                            <span>Help Center</span>
                        </Link>

                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl bg-[#087F8C] px-4 py-2.5 text-xs font-extrabold text-white shadow-sm transition hover:bg-[#066B76] active:scale-[0.99]"
                            @click="openSubmitModal"
                        >
                            <span class="text-base leading-none">+</span>
                            <span>File a Complaint</span>
                        </button>
                    </div>
                </div>

                <!-- SUCCESS MESSAGE -->
                <div
                    v-if="statusMessage"
                    class="mt-6 flex items-start gap-3 rounded-2xl border border-[#CDE9E4] bg-[#EAF8F1] p-4 text-xs font-bold text-[#17784F] shadow-sm sm:text-sm"
                >
                    <span class="text-base">✓</span>
                    <span>{{ statusMessage }}</span>
                </div>

                <!-- SEARCH & STATUS TABS -->
                <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <!-- STATUS TABS (BUYER-33) -->
                    <div class="overflow-x-auto pb-1 scrollbar-none">
                        <nav class="flex min-w-max gap-2 border-b border-gray-200 pb-2">
                            <button
                                v-for="tab in tabs"
                                :key="tab.key"
                                type="button"
                                :class="[
                                    'inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-extrabold transition',
                                    current_status === tab.key
                                        ? 'bg-[#087F8C] text-white shadow-sm'
                                        : 'bg-white text-gray-600 hover:bg-gray-100 hover:text-gray-900 border border-gray-200'
                                ]"
                                @click="selectTab(tab.key)"
                            >
                                <span>{{ tab.label }}</span>
                                <span
                                    :class="[
                                        'rounded-full px-1.5 py-0.5 text-[10px] font-bold',
                                        current_status === tab.key
                                            ? 'bg-white/20 text-white'
                                            : 'bg-gray-100 text-gray-600'
                                    ]"
                                >
                                    {{ counts[tab.countKey] || 0 }}
                                </span>
                            </button>
                        </nav>
                    </div>

                    <!-- SEARCH BAR -->
                    <div class="relative w-full sm:max-w-xs">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search complaints..."
                            class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-8 text-xs text-gray-900 placeholder:text-gray-400 focus:border-[#087F8C] focus:ring-2 focus:ring-[#E8F7F6]"
                            @keydown.enter="handleSearch"
                        />
                        <span class="pointer-events-none absolute left-3 top-2.5 text-xs text-gray-400">🔍</span>
                        <button
                            v-if="searchQuery"
                            type="button"
                            class="absolute right-2.5 top-2 text-xs text-gray-400 hover:text-gray-700"
                            @click="clearSearch"
                        >
                            ✕
                        </button>
                    </div>
                </div>

                <!-- COMPLAINTS LIST (BUYER-33) -->
                <div v-if="complaints?.data?.length" class="mt-6 space-y-4">
                    <article
                        v-for="item in complaints.data"
                        :key="item.id"
                        class="overflow-hidden rounded-3xl border border-[#E5E7EB] bg-white p-5 shadow-sm transition hover:border-[#087F8C]/30 hover:shadow-md sm:p-6"
                    >
                        <!-- HEADER & STATUS -->
                        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 pb-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-mono text-xs font-black text-[#087F8C]">
                                        Case #{{ item.id }}
                                    </span>
                                    <span :class="getTypeBadge(item.type).color" class="rounded-full px-2.5 py-0.5 text-[9px] font-extrabold uppercase">
                                        {{ getTypeBadge(item.type).label }}
                                    </span>
                                    <span class="text-[11px] text-gray-400">
                                        Filed on {{ formatDate(item.created_at) }}
                                    </span>
                                </div>

                                <h2 class="mt-2 text-base font-extrabold text-gray-900 sm:text-lg">
                                    {{ item.subject }}
                                </h2>
                            </div>

                            <span
                                :class="[
                                    'inline-flex items-center rounded-full border px-3 py-1 text-xs font-extrabold',
                                    getStatusBadge(item.status).color
                                ]"
                            >
                                {{ getStatusBadge(item.status).label }}
                            </span>
                        </div>

                        <!-- CASE ENTITY ASSOCIATIONS -->
                        <div class="mt-3 flex flex-wrap gap-4 text-xs text-gray-600">
                            <span v-if="item.order">
                                <strong>Order:</strong> #{{ item.order.order_number }}
                            </span>
                            <span v-if="item.seller">
                                <strong>Seller:</strong> {{ item.seller.store_name || item.seller.name }}
                            </span>
                            <span v-if="item.product">
                                <strong>Product:</strong> {{ item.product.name }}
                            </span>
                        </div>

                        <!-- DESCRIPTION -->
                        <p class="mt-3 text-xs leading-relaxed text-gray-700 sm:text-sm whitespace-pre-line">
                            {{ item.description }}
                        </p>

                        <!-- EVIDENCE ATTACHMENTS (BUYER-33) -->
                        <div v-if="item.evidence?.length" class="mt-4">
                            <p class="text-[11px] font-bold text-gray-500">Submitted Evidence:</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <a
                                    v-for="(img, idx) in item.evidence"
                                    :key="idx"
                                    :href="imageUrl(img)"
                                    target="_blank"
                                    class="group relative h-16 w-16 overflow-hidden rounded-xl border border-gray-200 bg-gray-50"
                                >
                                    <img :src="imageUrl(img)" alt="Evidence" class="h-full w-full object-cover transition group-hover:scale-105" />
                                </a>
                            </div>
                        </div>

                        <!-- ADMIN RESOLUTION / RESPONSE (BUYER-33) -->
                        <div
                            v-if="item.resolution || item.status === 'resolved' || item.status === 'rejected'"
                            class="mt-5 rounded-2xl p-4 text-xs"
                            :class="item.status === 'resolved' ? 'border border-[#BBF7D0] bg-[#F0FDF4] text-[#14532D]' : 'border border-gray-200 bg-[#F8FAF9] text-gray-800'"
                        >
                            <div class="flex items-center gap-2">
                                <span class="font-extrabold">
                                    {{ item.status === 'resolved' ? '✓ Official Platform Resolution:' : 'Administrator Response:' }}
                                </span>
                                <span v-if="item.reviewer" class="text-[11px] text-gray-500">
                                    (Reviewed by {{ item.reviewer.name }})
                                </span>
                            </div>

                            <p class="mt-1.5 leading-relaxed whitespace-pre-line text-xs sm:text-sm">
                                {{ item.resolution || 'Your case has been assessed and closed by the moderation team.' }}
                            </p>
                        </div>
                    </article>

                    <!-- PAGINATION -->
                    <div
                        v-if="complaints.links?.length > 3"
                        class="mt-8 flex w-full flex-wrap items-center justify-center gap-1.5"
                    >
                        <Link
                            v-for="(link, index) in complaints.links"
                            :key="index"
                            :href="link.url || '#'"
                            v-html="link.label"
                            class="rounded-xl border px-3 py-1.5 text-xs font-bold transition"
                            :class="[
                                link.active ? 'border-[#087F8C] bg-[#087F8C] text-white' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50',
                                !link.url ? 'pointer-events-none opacity-40' : ''
                            ]"
                            preserve-scroll
                            preserve-state
                        />
                    </div>
                </div>

                <!-- EMPTY STATE -->
                <div
                    v-else
                    class="mt-6 rounded-3xl border border-dashed border-gray-200 bg-white p-12 text-center"
                >
                    <span class="text-4xl">📋</span>
                    <h2 class="mt-3 text-base font-extrabold text-gray-900">
                        {{ current_status === 'all' ? 'No complaints or reports filed yet' : `No ${current_status} cases found` }}
                    </h2>
                    <p class="mt-1 text-xs text-gray-500 sm:text-sm">
                        If you have an issue with an order, courier, or seller, you can submit a complaint for administrator mediation.
                    </p>
                    <button
                        type="button"
                        class="mt-5 inline-flex items-center gap-2 rounded-xl bg-[#087F8C] px-4 py-2.5 text-xs font-extrabold text-white shadow-sm transition hover:bg-[#066B76]"
                        @click="openSubmitModal"
                    >
                        <span>+ File a New Complaint</span>
                    </button>
                </div>

            </div>
        </main>

        <!-- SUBMIT COMPLAINT / REPORT MODAL (BUYER-32, BUYER-33) -->
        <div
            v-if="isModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
            @click.self="closeSubmitModal"
        >
            <div class="max-h-[92vh] w-full max-w-xl overflow-y-auto rounded-3xl border border-gray-200 bg-white p-6 shadow-2xl sm:p-8">
                <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                    <div>
                        <h2 class="text-lg font-black text-gray-900">Submit a Complaint / Dispute</h2>
                        <p class="text-xs text-gray-500">Provide details for the platform administration to investigate.</p>
                    </div>
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200"
                        @click="closeSubmitModal"
                    >
                        ✕
                    </button>
                </div>

                <form class="mt-5 space-y-4" @submit.prevent="submitComplaint">
                    <!-- CASE TYPE -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700">Case Type</label>
                        <select
                            v-model="form.type"
                            class="mt-1 w-full rounded-xl border border-gray-200 bg-[#F8FAF9] px-3.5 py-2.5 text-xs text-gray-900 focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                        >
                            <option value="complaint">General Complaint / Dispute</option>
                            <option value="seller_report">Seller Misconduct / Fraud</option>
                            <option value="product_report">Product Issue / Counterfeit</option>
                            <option value="support_ticket">Customer Support Assistance</option>
                        </select>
                    </div>

                    <!-- SUBJECT -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700">Subject</label>
                        <input
                            v-model="form.subject"
                            type="text"
                            required
                            placeholder="Brief summary of your complaint..."
                            class="mt-1 w-full rounded-xl border border-gray-200 bg-[#F8FAF9] px-3.5 py-2.5 text-xs text-gray-900 focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                        />
                        <p v-if="form.errors.subject" class="mt-1 text-xs text-red-600">{{ form.errors.subject }}</p>
                    </div>

                    <!-- LINKED ORDER (OPTIONAL) -->
                    <div v-if="orders.length">
                        <label class="block text-xs font-bold text-gray-700">Associated Order (Optional)</label>
                        <select
                            v-model="form.order_id"
                            class="mt-1 w-full rounded-xl border border-gray-200 bg-[#F8FAF9] px-3.5 py-2.5 text-xs text-gray-900 focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                        >
                            <option value="">None / Not related to a specific order</option>
                            <option v-for="ord in orders" :key="ord.id" :value="ord.id">
                                Order #{{ ord.order_number || ord.id }} ({{ formatDate(ord.created_at) }})
                            </option>
                        </select>
                    </div>

                    <!-- DESCRIPTION -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700">Detailed Description & Evidence Notes</label>
                        <textarea
                            v-model="form.description"
                            rows="4"
                            required
                            placeholder="Explain what occurred in detail..."
                            class="mt-1 w-full rounded-xl border border-gray-200 bg-[#F8FAF9] px-3.5 py-2.5 text-xs text-gray-900 focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                        ></textarea>
                        <p v-if="form.errors.description" class="mt-1 text-xs text-red-600">{{ form.errors.description }}</p>
                    </div>

                    <!-- EVIDENCE UPLOAD -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700">Upload Evidence Photos (Max 5 images)</label>
                        <input
                            type="file"
                            accept="image/*"
                            multiple
                            class="mt-1 block w-full text-xs text-gray-500 file:mr-3 file:rounded-xl file:border-0 file:bg-[#E8F7F6] file:px-3 file:py-2 file:text-xs file:font-bold file:text-[#087F8C] hover:file:bg-[#D3F0EE]"
                            @change="handleFileChange"
                        />
                        <p v-if="form.errors.evidence_images" class="mt-1 text-xs text-red-600">{{ form.errors.evidence_images }}</p>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-100">
                        <button
                            type="button"
                            class="rounded-xl border border-gray-200 px-4 py-2.5 text-xs font-extrabold text-gray-600 hover:bg-gray-50"
                            @click="closeSubmitModal"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-xl bg-[#087F8C] px-5 py-2.5 text-xs font-extrabold text-white shadow-sm hover:bg-[#066B76] disabled:opacity-50"
                        >
                            {{ form.processing ? 'Submitting Case...' : 'Submit Complaint' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </BuyerLayout>
</template>

