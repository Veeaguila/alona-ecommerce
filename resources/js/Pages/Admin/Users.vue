<script setup>
import { computed, reactive } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    title: {
        type: String,
        default: 'Users',
    },

    eyebrow: {
        type: String,
        default: 'User management',
    },

    description: {
        type: String,
        default: '',
    },

    active: {
        type: String,
        default: 'users',
    },

    users: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
        }),
    },

    user: {
        type: Object,
        default: null,
    },

    filters: {
        type: Object,
        default: () => ({
            search: '',
            role: 'all',
            status: 'all',
        }),
    },
})

const page = usePage()

/*
|--------------------------------------------------------------------------
| USERS
|--------------------------------------------------------------------------
*/

const rows = computed(() => props.users?.data ?? [])

/*
|--------------------------------------------------------------------------
| STATUS MODAL
|--------------------------------------------------------------------------
*/

const statusModal = reactive({
    open: false,
    id: null,
    name: '',
    currentStatus: '',
    status: '',
    reason: '',
    processing: false,
})

const statusOptions = [
    {
        value: 'approved',
        label: 'Approved',
    },
    {
        value: 'pending',
        label: 'Pending',
    },
    {
        value: 'suspended',
        label: 'Suspended',
    },
    {
        value: 'deactivated',
        label: 'Deactivated',
    },
    {
        value: 'rejected',
        label: 'Rejected',
    },
]

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

const roleLabel = (role) => {
    switch (role) {
        case 'buyer':
            return 'Buyer'

        case 'seller':
            return 'Seller'

        case 'rider':
            return 'Courier'

        case 'admin':
            return 'Administrator'

        default:
            return role || 'Unknown'
    }
}

const roleColor = (role) => {
    switch (role) {
        case 'buyer':
            return 'bg-[#E8F7F6] text-[#087F8C] ring-[#087F8C]/10'

        case 'seller':
            return 'bg-[#FFF8E7] text-[#9A6A00] ring-[#F4B942]/20'

        case 'rider':
            return 'bg-orange-50 text-orange-700 ring-orange-600/10'

        case 'admin':
            return 'bg-slate-100 text-slate-700 ring-slate-600/10'

        default:
            return 'bg-slate-100 text-slate-600 ring-slate-600/10'
    }
}

const statusColor = (status) => {
    switch (status) {
        case 'approved':
            return 'bg-emerald-50 text-[#22A06B] ring-emerald-600/10'

        case 'pending':
            return 'bg-[#FFF8E7] text-[#9A6A00] ring-[#F4B942]/20'

        case 'suspended':
            return 'bg-red-50 text-[#E85D5D] ring-red-600/10'

        case 'deactivated':
            return 'bg-slate-100 text-slate-600 ring-slate-600/10'

        case 'rejected':
            return 'bg-red-50 text-[#E85D5D] ring-red-600/10'

        default:
            return 'bg-slate-100 text-slate-600 ring-slate-600/10'
    }
}

const formatStatus = (status) => {
    return String(status || 'unknown')
        .replace(/[_-]/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase())
}

const formatDate = (date) => {
    if (!date) {
        return 'N/A'
    }

    return new Date(date).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    })
}

const userInitial = (row) => {
    return String(row?.name || row?.email || 'U')
        .charAt(0)
        .toUpperCase()
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

const applyFilters = () => {
    router.get(
        route('admin.users'),
        {
            search: props.filters.search?.trim() || undefined,

            role:
                props.filters.role &&
                props.filters.role !== 'all'
                    ? props.filters.role
                    : undefined,

            status:
                props.filters.status &&
                props.filters.status !== 'all'
                    ? props.filters.status
                    : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    )
}

const updateRole = (event) => {
    props.filters.role = event.target.value
    applyFilters()
}

const updateStatusFilter = (event) => {
    props.filters.status = event.target.value
    applyFilters()
}

const updateSearch = (event) => {
    props.filters.search = event.target.value
}

/*
|--------------------------------------------------------------------------
| STATUS MANAGEMENT
|--------------------------------------------------------------------------
*/

const openStatusModal = (row) => {
    statusModal.open = true
    statusModal.id = row.id
    statusModal.name = row.name || row.email || 'this user'
    statusModal.currentStatus = row.status || ''
    statusModal.status = row.status || 'approved'
    statusModal.reason = row.rejection_reason || ''
    statusModal.processing = false
}

const closeStatusModal = () => {
    if (statusModal.processing) {
        return
    }

    statusModal.open = false
    statusModal.id = null
    statusModal.name = ''
    statusModal.currentStatus = ''
    statusModal.status = ''
    statusModal.reason = ''
}

const submitStatusChange = () => {
    if (!statusModal.id) {
        return
    }

    const selectedStatus = statusModal.status
    const reason = statusModal.reason.trim()

    if (
        ['suspended', 'deactivated', 'rejected'].includes(selectedStatus) &&
        !reason
    ) {
        alert('Please provide a reason for this status change.')
        return
    }

    if (reason.length > 1000) {
        alert('The reason cannot exceed 1000 characters.')
        return
    }

    statusModal.processing = true

    router.patch(
        route('admin.users.status', statusModal.id),
        {
            status: selectedStatus,
            reason: reason || null,
        },
        {
            preserveScroll: true,

            onSuccess: () => {
                closeStatusModal()
            },

            onFinish: () => {
                statusModal.processing = false
            },
        },
    )
}

/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

const goToPage = (url) => {
    if (!url) {
        return
    }

    router.get(
        url,
        {},
        {
            preserveState: true,
            preserveScroll: true,
        },
    )
}

/*
|--------------------------------------------------------------------------
| FLASH / ERRORS
|--------------------------------------------------------------------------
*/

const flashStatus = computed(() => {
    return page.props.flash?.status ?? null
})

const errors = computed(() => {
    return page.props.errors ?? {}
})
</script>

<template>
    <Head :title="title || 'Users'" />

    <AdminLayout :active="active || 'users'">

        <!-- ============================================================
             USER PROFILE
        ============================================================= -->

        <div
            v-if="user"
            class="mx-auto max-w-7xl"
        >

            <!-- PAGE HEADER -->

            <div class="border-b border-[#E5E7EB] pb-5">
                <div class="flex items-center gap-2">
                    <span class="h-1.5 w-1.5 rounded-full bg-[#16A6A0]" />

                    <p
                        class="text-xs font-bold uppercase tracking-[0.14em] text-[#087F8C]"
                    >
                        {{ eyebrow || 'User management' }}
                    </p>
                </div>

                <h1
                    class="mt-2 text-2xl font-bold tracking-tight text-[#1F2937] sm:text-3xl"
                >
                    {{ title || 'User profile' }}
                </h1>

                <p
                    v-if="description"
                    class="mt-1.5 max-w-3xl text-sm leading-5 text-[#64748B]"
                >
                    {{ description }}
                </p>
            </div>

            <!-- PROFILE -->

            <div
                class="mt-5 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
            >

                <!-- PROFILE HEADER -->

                <div
                    class="border-b border-[#E5E7EB] px-5 py-4"
                >
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#087F8C] text-sm font-bold text-white"
                            >
                                {{ userInitial(user) }}
                            </div>

                            <div class="min-w-0">
                                <p
                                    class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#94A3B8]"
                                >
                                    Account
                                </p>

                                <h2
                                    class="mt-1 truncate text-base font-bold text-[#1F2937]"
                                >
                                    {{ user.name || 'N/A' }}
                                </h2>

                                <p
                                    class="mt-0.5 truncate text-[11px] text-[#64748B]"
                                >
                                    {{ user.email || 'N/A' }}
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <span
                                :class="[
                                    'inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide ring-1 ring-inset',
                                    roleColor(user.usertype),
                                ]"
                            >
                                {{ roleLabel(user.usertype) }}
                            </span>

                            <span
                                :class="[
                                    'inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide ring-1 ring-inset',
                                    statusColor(user.status),
                                ]"
                            >
                                {{ formatStatus(user.status) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- PROFILE BODY -->

                <div class="p-4 sm:p-5">

                    <!-- PERSONAL INFORMATION -->

                    <div
                        class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-4"
                    >
                        <div class="flex items-center gap-2.5">
                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]"
                            >
                                <svg
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <circle cx="12" cy="8" r="4" />
                                    <path d="M4 21a8 8 0 0 1 16 0" />
                                </svg>
                            </span>

                            <div>
                                <p class="text-xs font-bold text-[#1F2937]">
                                    Personal information
                                </p>

                                <p class="mt-0.5 text-[10px] text-[#94A3B8]">
                                    Registered account details
                                </p>
                            </div>
                        </div>

                        <dl
                            class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
                        >
                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-wide text-[#94A3B8]">
                                    First Name
                                </dt>

                                <dd class="mt-1 text-[11px] font-semibold text-[#1F2937]">
                                    {{ user.first_name || 'N/A' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-wide text-[#94A3B8]">
                                    Last Name
                                </dt>

                                <dd class="mt-1 text-[11px] font-semibold text-[#1F2937]">
                                    {{ user.last_name || 'N/A' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-wide text-[#94A3B8]">
                                    Middle Initial
                                </dt>

                                <dd class="mt-1 text-[11px] font-semibold text-[#1F2937]">
                                    {{ user.middle_initial || 'N/A' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-wide text-[#94A3B8]">
                                    Sex
                                </dt>

                                <dd class="mt-1 text-[11px] font-semibold text-[#1F2937]">
                                    {{ user.sex || 'N/A' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-wide text-[#94A3B8]">
                                    Birthday
                                </dt>

                                <dd class="mt-1 text-[11px] font-semibold text-[#1F2937]">
                                    {{ user.birthday || 'N/A' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-wide text-[#94A3B8]">
                                    Age
                                </dt>

                                <dd class="mt-1 text-[11px] font-semibold text-[#1F2937]">
                                    {{ user.age || 'N/A' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-wide text-[#94A3B8]">
                                    Contact Number
                                </dt>

                                <dd class="mt-1 text-[11px] font-semibold text-[#1F2937]">
                                    {{ user.contact_no || 'N/A' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-wide text-[#94A3B8]">
                                    Registered
                                </dt>

                                <dd class="mt-1 text-[11px] font-semibold text-[#1F2937]">
                                    {{ formatDate(user.created_at) }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- ADDRESS -->

                    <div
                        class="mt-3 rounded-xl border border-[#E5E7EB] bg-white p-4"
                    >
                        <div class="flex items-center gap-2.5">
                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]"
                            >
                                <svg
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"
                                    />
                                    <circle cx="12" cy="10" r="2.5" />
                                </svg>
                            </span>

                            <div>
                                <p class="text-xs font-bold text-[#1F2937]">
                                    Address
                                </p>

                                <p class="mt-0.5 text-[10px] text-[#94A3B8]">
                                    Registered residential address
                                </p>
                            </div>
                        </div>

                        <p
                            class="mt-3 text-[11px] font-semibold leading-5 text-[#64748B]"
                        >
                            {{
                                [
                                    user.street_address,
                                    user.barangay,
                                    user.municipality,
                                    user.province,
                                    user.address,
                                ]
                                    .filter(Boolean)
                                    .join(', ') || 'N/A'
                            }}
                        </p>
                    </div>

                    <!-- SELLER INFORMATION -->

                    <div
                        v-if="user.usertype === 'seller'"
                        class="mt-3 rounded-xl border border-[#E5E7EB] bg-white p-4"
                    >
                        <div class="flex items-center gap-2.5">
                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#FFF8E7] text-[#F4B942]"
                            >
                                <svg
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path d="M3 10h18M5 10v10h14V10M4 10l1-6h14l1 6" />
                                    <path d="M9 20v-5h6v5" />
                                </svg>
                            </span>

                            <div>
                                <p class="text-xs font-bold text-[#1F2937]">
                                    Seller information
                                </p>

                                <p class="mt-0.5 text-[10px] text-[#94A3B8]">
                                    Marketplace seller profile
                                </p>
                            </div>
                        </div>

                        <dl
                            class="mt-4 grid gap-4 sm:grid-cols-2"
                        >
                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-wide text-[#94A3B8]">
                                    Store Name
                                </dt>

                                <dd class="mt-1 text-[11px] font-semibold text-[#1F2937]">
                                    {{ user.store_name || 'N/A' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-wide text-[#94A3B8]">
                                    Line of Business
                                </dt>

                                <dd class="mt-1 text-[11px] font-semibold text-[#1F2937]">
                                    {{ user.line_of_business || 'N/A' }}
                                </dd>
                            </div>

                            <div class="sm:col-span-2">
                                <dt class="text-[10px] font-semibold uppercase tracking-wide text-[#94A3B8]">
                                    Store Description
                                </dt>

                                <dd class="mt-1 whitespace-pre-line text-[11px] font-semibold leading-5 text-[#64748B]">
                                    {{ user.store_description || 'No description provided.' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-wide text-[#94A3B8]">
                                    Shipping Fee
                                </dt>

                                <dd class="mt-1 text-[11px] font-semibold text-[#1F2937]">
                                    {{ user.shipping_fee ?? 'N/A' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-wide text-[#94A3BE]">
                                    Return Policy
                                </dt>

                                <dd class="mt-1 text-[11px] font-semibold text-[#1F2937]">
                                    {{
                                        user.return_policy_days !== null &&
                                        user.return_policy_days !== undefined
                                            ? `${user.return_policy_days} day(s)`
                                            : 'N/A'
                                    }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- STATUS REASON -->

                    <div
                        v-if="user.rejection_reason"
                        class="mt-3 rounded-xl border border-red-200 bg-red-50 p-4"
                    >
                        <p
                            class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#E85D5D]"
                        >
                            Status reason
                        </p>

                        <p
                            class="mt-1.5 whitespace-pre-line text-[11px] leading-5 text-red-700"
                        >
                            {{ user.rejection_reason }}
                        </p>
                    </div>

                    <!-- ACTIONS -->

                    <div
                        class="mt-4 flex flex-wrap gap-2 border-t border-[#E5E7EB] pt-4"
                    >
                        <Link
                            :href="route('admin.users')"
                            class="rounded-lg border border-[#E5E7EB] bg-white px-3.5 py-2.5 text-[11px] font-bold text-[#64748B] transition hover:border-[#087F8C] hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                        >
                            ← Back to Users
                        </Link>

                        <button
                            type="button"
                            class="rounded-lg bg-[#087F8C] px-3.5 py-2.5 text-[11px] font-bold text-white transition hover:bg-[#066C77]"
                            @click="openStatusModal(user)"
                        >
                            Manage Status
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================
             USER LIST
        ============================================================= -->

        <div
            v-else
            class="mx-auto max-w-7xl"
        >

            <!-- PAGE HEADER -->

            <div
                class="flex flex-col gap-4 border-b border-[#E5E7EB] pb-5 lg:flex-row lg:items-end lg:justify-between"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full bg-[#16A6A0]" />

                        <p
                            class="text-xs font-bold uppercase tracking-[0.14em] text-[#087F8C]"
                        >
                            {{ eyebrow || 'User management' }}
                        </p>
                    </div>

                    <h1
                        class="mt-2 text-2xl font-bold tracking-tight text-[#1F2937] sm:text-3xl"
                    >
                        {{ title || 'Users' }}
                    </h1>

                    <p
                        v-if="description"
                        class="mt-1.5 max-w-3xl text-sm leading-5 text-[#64748B]"
                    >
                        {{ description }}
                    </p>
                </div>

                <Link
                    :href="route('admin.dashboard')"
                    class="inline-flex items-center self-start rounded-lg border border-[#E5E7EB] bg-white px-3.5 py-2.5 text-[11px] font-bold text-[#64748B] shadow-sm transition hover:border-[#087F8C] hover:bg-[#E8F7F6] hover:text-[#087F8C] lg:self-auto"
                >
                    ← Dashboard
                </Link>
            </div>

            <!-- FLASH -->

            <div
                v-if="flashStatus"
                class="mt-5 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-[11px] font-semibold text-[#22A06B]"
            >
                <span class="h-1.5 w-1.5 rounded-full bg-[#22A06B]" />

                {{ flashStatus }}
            </div>

            <!-- ERROR -->

            <div
                v-if="errors.status"
                class="mt-5 flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-[11px] font-semibold text-[#E85D5D]"
            >
                <span class="h-1.5 w-1.5 rounded-full bg-[#E85D5D]" />

                {{ errors.status }}
            </div>

            <!-- ========================================================
                 FILTER PANEL
            ========================================================= -->

            <div
                class="mt-5 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
            >
                <div class="px-5 py-4">
                    <div
                        class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between"
                    >
                        <div
                            class="flex flex-col gap-2.5 sm:flex-row sm:flex-wrap"
                        >

                            <!-- SEARCH -->

                            <div class="relative">
                                <svg
                                    class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[#94A3B8]"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <circle cx="11" cy="11" r="7" />
                                    <path d="m20 20-4-4" />
                                </svg>

                                <input
                                    :value="filters.search ?? ''"
                                    type="text"
                                    placeholder="Search users..."
                                    class="w-full rounded-lg border border-[#E5E7EB] bg-[#F8FAF9] py-2.5 pl-9 pr-3 text-[11px] text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white sm:w-64"
                                    @input="updateSearch"
                                    @keyup.enter="applyFilters"
                                />
                            </div>

                            <!-- ROLE -->

                            <select
                                :value="filters.role ?? 'all'"
                                class="rounded-lg border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2.5 text-[11px] font-medium text-[#64748B] outline-none transition focus:border-[#087F8C] focus:bg-white"
                                @change="updateRole"
                            >
                                <option value="all">
                                    All roles
                                </option>

                                <option value="buyer">
                                    Buyers
                                </option>

                                <option value="seller">
                                    Sellers
                                </option>

                                <option value="rider">
                                    Couriers
                                </option>

                                <option value="admin">
                                    Administrators
                                </option>
                            </select>

                            <!-- STATUS -->

                            <select
                                :value="filters.status ?? 'all'"
                                class="rounded-lg border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2.5 text-[11px] font-medium text-[#64748B] outline-none transition focus:border-[#087F8C] focus:bg-white"
                                @change="updateStatusFilter"
                            >
                                <option value="all">
                                    All statuses
                                </option>

                                <option value="approved">
                                    Approved
                                </option>

                                <option value="pending">
                                    Pending
                                </option>

                                <option value="suspended">
                                    Suspended
                                </option>

                                <option value="deactivated">
                                    Deactivated
                                </option>

                                <option value="rejected">
                                    Rejected
                                </option>
                            </select>

                            <!-- SEARCH -->

                            <button
                                type="button"
                                class="rounded-lg bg-[#087F8C] px-4 py-2.5 text-[11px] font-bold text-white transition hover:bg-[#066C77]"
                                @click="applyFilters"
                            >
                                Search
                            </button>
                        </div>

                        <!-- COUNT -->

                        <div
                            class="flex items-center gap-2 text-[10px] font-semibold text-[#94A3B8]"
                        >
                            <span class="h-1.5 w-1.5 rounded-full bg-[#16A6A0]" />

                            {{ rows.length }} user(s)
                        </div>
                    </div>
                </div>

                <!-- TABLE -->

                <div class="overflow-x-auto border-t border-[#E5E7EB]">
                    <table
                        class="w-full min-w-[950px] text-left"
                    >
                        <thead class="bg-[#F8FAF9]">
                            <tr>
                                <th
                                    class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                >
                                    User
                                </th>

                                <th
                                    class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                >
                                    Role
                                </th>

                                <th
                                    class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                >
                                    Status
                                </th>

                                <th
                                    class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                >
                                    Store
                                </th>

                                <th
                                    class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                >
                                    Registered
                                </th>

                                <th
                                    class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                >
                                    Action
                                </th>
                            </tr>
                        </thead>

                        <tbody
                            v-if="rows.length"
                            class="divide-y divide-[#E5E7EB]"
                        >
                            <tr
                                v-for="row in rows"
                                :key="row.id"
                                class="group transition hover:bg-[#F8FAF9]"
                            >

                                <!-- USER -->

                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#E8F7F6] text-[10px] font-bold text-[#087F8C] transition group-hover:bg-[#087F8C] group-hover:text-white"
                                        >
                                            {{ userInitial(row) }}
                                        </div>

                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-[11px] font-bold text-[#1F2937]"
                                            >
                                                {{ row.name || 'N/A' }}
                                            </p>

                                            <p
                                                class="mt-0.5 max-w-[220px] truncate text-[9px] text-[#94A3B8]"
                                            >
                                                {{ row.email }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <!-- ROLE -->

                                <td class="px-5 py-3.5">
                                    <span
                                        :class="[
                                            'inline-flex rounded-full px-2.5 py-1 text-[9px] font-bold ring-1 ring-inset',
                                            roleColor(row.usertype),
                                        ]"
                                    >
                                        {{ roleLabel(row.usertype) }}
                                    </span>
                                </td>

                                <!-- STATUS -->

                                <td class="px-5 py-3.5">
                                    <span
                                        :class="[
                                            'inline-flex rounded-full px-2.5 py-1 text-[9px] font-bold uppercase tracking-wide ring-1 ring-inset',
                                            statusColor(row.status),
                                        ]"
                                    >
                                        {{ formatStatus(row.status) }}
                                    </span>
                                </td>

                                <!-- STORE -->

                                <td class="px-5 py-3.5">
                                    <span
                                        class="block max-w-[180px] truncate text-[10px] font-medium text-[#64748B]"
                                    >
                                        {{ row.store_name || 'N/A' }}
                                    </span>
                                </td>

                                <!-- REGISTERED -->

                                <td class="px-5 py-3.5">
                                    <span
                                        class="text-[10px] font-medium text-[#64748B]"
                                    >
                                        {{ formatDate(row.created_at) }}
                                    </span>
                                </td>

                                <!-- ACTIONS -->

                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-1.5">
                                        <Link
                                            :href="route('admin.users.show', row.id)"
                                            class="rounded-lg border border-[#E5E7EB] bg-white px-2.5 py-1.5 text-[9px] font-bold text-[#64748B] transition hover:border-[#087F8C] hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                                        >
                                            View
                                        </Link>

                                        <button
                                            type="button"
                                            class="rounded-lg bg-[#087F8C] px-2.5 py-1.5 text-[9px] font-bold text-white transition hover:bg-[#066C77]"
                                            @click="openStatusModal(row)"
                                        >
                                            Manage
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>

                        <!-- EMPTY -->

                        <tbody v-else>
                            <tr>
                                <td
                                    colspan="6"
                                    class="px-5 py-14 text-center"
                                >
                                    <div
                                        class="mx-auto flex h-10 w-10 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                                    >
                                        <svg
                                            class="h-5 w-5"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >
                                            <circle cx="9" cy="8" r="3" />
                                            <path d="M3 20a6 6 0 0 1 12 0" />
                                            <path d="M16 11h5M18.5 8.5v5" />
                                        </svg>
                                    </div>

                                    <p
                                        class="mt-3 text-sm font-bold text-[#1F2937]"
                                    >
                                        No users found
                                    </p>

                                    <p
                                        class="mt-1 text-[10px] text-[#94A3B8]"
                                    >
                                        Try changing your search or filters.
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION -->

                <div
                    v-if="users?.links?.length > 3"
                    class="flex flex-wrap items-center justify-center gap-1.5 border-t border-[#E5E7EB] p-4"
                >
                    <button
                        v-for="(link, index) in users.links"
                        :key="`${link.label}-${index}`"
                        type="button"
                        :disabled="!link.url"
                        :class="[
                            'rounded-lg px-2.5 py-1.5 text-[9px] font-bold transition',

                            link.active
                                ? 'bg-[#087F8C] text-white'
                                : 'border border-[#E5E7EB] bg-white text-[#64748B] hover:border-[#087F8C] hover:bg-[#E8F7F6] hover:text-[#087F8C]',

                            !link.url
                                ? 'cursor-not-allowed opacity-40'
                                : '',
                        ]"
                        @click="goToPage(link.url)"
                    >
                        <span v-html="link.label" />
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================================
             STATUS MODAL
        ============================================================= -->

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="statusModal.open"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-[2px]"
                @click.self="closeStatusModal"
            >
                <div
                    class="w-full max-w-md overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-2xl"
                >

                    <!-- MODAL HEADER -->

                    <div
                        class="border-b border-[#E5E7EB] px-5 py-4"
                    >
                        <div
                            class="flex items-start justify-between gap-4"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path
                                            d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"
                                        />
                                        <path
                                            d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-1.8 1.8-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V20h-2.54v-.1a1.7 1.7 0 0 0-1.03-1.56 1.7 1.7 0 0 0-1.88.34l-.06.06-1.8-1.8.06-.06A1.7 1.7 0 0 0 8.1 15a1.7 1.7 0 0 0-1.56-1.03h-.1v-2.54h.1A1.7 1.7 0 0 0 8.1 10.4a1.7 1.7 0 0 0-.34-1.88L7.7 8.46l1.8-1.8.06.06a1.7 1.7 0 0 0 1.88.34 1.7 1.7 0 0 0 1.03-1.56V5h2.54v.1a1.7 1.7 0 0 0 1.03 1.56 1.7 1.7 0 0 0 1.88-.34l.06-.06 1.8 1.8-.06.06a1.7 1.7 0 0 0-.34 1.88 1.7 1.7 0 0 0 1.56 1.03h.1v2.54h-.1A1.7 1.7 0 0 0 19.4 15Z"
                                        />
                                    </svg>
                                </div>

                                <div>
                                    <p
                                        class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#087F8C]"
                                    >
                                        Account management
                                    </p>

                                    <h2
                                        class="mt-0.5 text-base font-bold text-[#1F2937]"
                                    >
                                        Manage account
                                    </h2>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="flex h-7 w-7 items-center justify-center rounded-lg text-[#94A3B8] transition hover:bg-[#F8FAF9] hover:text-[#1F2937]"
                                :disabled="statusModal.processing"
                                @click="closeStatusModal"
                            >
                                <svg
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path d="m6 6 12 12M18 6 6 18" />
                                </svg>
                            </button>
                        </div>

                        <p class="mt-3 text-[11px] text-[#64748B]">
                            Update the marketplace access status for
                            <span class="font-bold text-[#1F2937]">
                                {{ statusModal.name }}
                            </span>.
                        </p>
                    </div>

                    <!-- MODAL BODY -->

                    <div class="p-5">

                        <!-- STATUS -->

                        <div>
                            <label
                                for="account_status"
                                class="text-[10px] font-bold uppercase tracking-wide text-[#64748B]"
                            >
                                Account status
                            </label>

                            <select
                                id="account_status"
                                v-model="statusModal.status"
                                class="mt-2 w-full rounded-lg border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2.5 text-[11px] font-medium text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white"
                            >
                                <option
                                    v-for="option in statusOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>

                        <!-- REASON -->

                        <div class="mt-4">
                            <div
                                class="flex items-center justify-between"
                            >
                                <label
                                    for="status_reason"
                                    class="text-[10px] font-bold uppercase tracking-wide text-[#64748B]"
                                >
                                    Reason

                                    <span
                                        v-if="
                                            ['suspended', 'deactivated', 'rejected']
                                                .includes(statusModal.status)
                                        "
                                        class="text-[#E85D5D]"
                                    >
                                        *
                                    </span>
                                </label>

                                <span class="text-[9px] text-[#94A3B8]">
                                    {{ statusModal.reason.length }}/1000
                                </span>
                            </div>

                            <textarea
                                id="status_reason"
                                v-model="statusModal.reason"
                                rows="4"
                                maxlength="1000"
                                placeholder="Explain the reason for this status change..."
                                class="mt-2 w-full resize-none rounded-lg border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2.5 text-[11px] leading-5 text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white"
                            />

                            <p class="mt-1.5 text-[9px] text-[#94A3B8]">
                                {{
                                    ['suspended', 'deactivated', 'rejected']
                                        .includes(statusModal.status)
                                        ? 'A reason is required for this status.'
                                        : 'Optional for approved or pending.'
                                }}
                            </p>
                        </div>
                    </div>

                    <!-- MODAL FOOTER -->

                    <div
                        class="flex justify-end gap-2 border-t border-[#E5E7EB] bg-[#F8FAF9] px-5 py-4"
                    >
                        <button
                            type="button"
                            class="rounded-lg border border-[#E5E7EB] bg-white px-3.5 py-2.5 text-[10px] font-bold text-[#64748B] transition hover:bg-white disabled:opacity-50"
                            :disabled="statusModal.processing"
                            @click="closeStatusModal"
                        >
                            Cancel
                        </button>

                        <button
                            type="button"
                            class="rounded-lg bg-[#087F8C] px-3.5 py-2.5 text-[10px] font-bold text-white transition hover:bg-[#066C77] disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="statusModal.processing"
                            @click="submitStatusChange"
                        >
                            {{
                                statusModal.processing
                                    ? 'Saving...'
                                    : 'Save status'
                            }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

    </AdminLayout>
</template>