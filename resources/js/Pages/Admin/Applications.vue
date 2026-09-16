<script setup>
import { computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    title: {
        type: String,
        default: 'Applications',
    },

    eyebrow: {
        type: String,
        default: 'Registration management',
    },

    description: {
        type: String,
        default: '',
    },

    active: {
        type: String,
        default: 'applications',
    },

    applications: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
            meta: {},
        }),
    },

    application: {
        type: Object,
        default: null,
    },

    filters: {
        type: Object,
        default: () => ({
            role: 'all',
            status: 'all',
            search: '',
        }),
    },
})

const rows = computed(() => {
    return props.applications?.data ?? []
})

const initials = (name, email) => {
    const value = name || email || 'NA'

    return value
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('')
}

const statusColor = (status) => {
    switch (status) {
        case 'approved':
            return 'bg-[#EAF8F2] text-[#18794E] ring-1 ring-[#BFE8D4]'

        case 'rejected':
            return 'bg-[#FFF0F0] text-[#C94C4C] ring-1 ring-[#F3C5C5]'

        case 'pending':
            return 'bg-[#FFF8E7] text-[#9A6A08] ring-1 ring-[#F2D58A]'

        default:
            return 'bg-slate-100 text-slate-600 ring-1 ring-slate-200'
    }
}

const statusDot = (status) => {
    switch (status) {
        case 'approved':
            return 'bg-[#22A06B]'

        case 'rejected':
            return 'bg-[#E85D5D]'

        case 'pending':
            return 'bg-[#F4B942]'

        default:
            return 'bg-slate-400'
    }
}

const roleLabel = (role) => {
    switch (role) {
        case 'buyer':
            return 'Buyer'

        case 'seller':
            return 'Seller'

        case 'rider':
            return 'Courier'

        default:
            return role || 'Unknown'
    }
}

const roleColor = (role) => {
    switch (role) {
        case 'seller':
            return 'bg-[#FFF8E7] text-[#9A6A08] ring-1 ring-[#F2D58A]'

        case 'rider':
            return 'bg-violet-50 text-violet-700 ring-1 ring-violet-200'

        case 'buyer':
            return 'bg-[#E8F7F6] text-[#087F8C] ring-1 ring-[#BFE8E5]'

        default:
            return 'bg-slate-100 text-slate-600 ring-1 ring-slate-200'
    }
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

const approveApplication = (id) => {
    if (!confirm('Are you sure you want to approve this application?')) {
        return
    }

    router.patch(
        route('admin.applications.approve', id),
        {},
        {
            preserveScroll: true,
        }
    )
}

const applyFilters = () => {
    router.get(
        route('admin.applications'),
        {
            search: props.filters.search || undefined,

            status:
                props.filters.status &&
                props.filters.status !== 'all'
                    ? props.filters.status
                    : undefined,

            role:
                props.filters.role &&
                props.filters.role !== 'all'
                    ? props.filters.role
                    : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    )
}

const updateSearch = (event) => {
    props.filters.search = event.target.value
}

const updateStatus = (event) => {
    props.filters.status = event.target.value
    applyFilters()
}

const updateRole = (event) => {
    props.filters.role = event.target.value
    applyFilters()
}

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
        }
    )
}
</script>

<template>
    <Head :title="title || 'Applications'" />

    <AdminLayout :active="active || 'applications'">

        <!-- ========================================================= -->
        <!-- APPLICATION DETAILS -->
        <!-- ========================================================= -->

        <div
            v-if="application"
            class="mx-auto max-w-7xl"
        >
            <!-- PAGE HEADER -->

            <div
                class="flex flex-col justify-between gap-4 pb-5 sm:flex-row sm:items-end"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <span
                            class="h-1.5 w-1.5 rounded-full bg-[#087F8C]"
                        ></span>

                        <p
                            class="text-xs font-bold uppercase tracking-[0.14em] text-[#087F8C]"
                        >
                            {{ eyebrow || 'Application review' }}
                        </p>
                    </div>

                    <h1
                        class="mt-2 text-2xl font-bold tracking-tight text-[#1F2937] sm:text-3xl"
                    >
                        {{ title || 'Application' }}
                    </h1>

                    <p
                        v-if="description"
                        class="mt-1.5 max-w-3xl text-sm leading-5 text-[#64748B]"
                    >
                        {{ description }}
                    </p>
                </div>

                <Link
                    :href="route('admin.applications')"
                    class="inline-flex w-fit items-center gap-2 rounded-lg border border-[#E5E7EB] bg-white px-3.5 py-2 text-xs font-semibold text-[#64748B] shadow-sm transition hover:border-[#BFDAD8] hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                >
                    <svg
                        class="h-3.5 w-3.5"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M17 10a.75.75 0 01-.75.75H5.56l3.22 3.22a.75.75 0 11-1.06 1.06l-4.5-4.5a.75.75 0 010-1.06l4.5-4.5a.75.75 0 111.06 1.06L5.56 9.25h10.69A.75.75 0 0117 10z"
                            clip-rule="evenodd"
                        />
                    </svg>

                    Back to applications
                </Link>
            </div>

            <!-- MAIN DETAIL CARD -->

            <div
                class="mt-5 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
            >
                <!-- APPLICANT SUMMARY -->

                <div
                    class="border-b border-[#E5E7EB] bg-[#F8FAF9] px-5 py-4"
                >
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#087F8C] text-sm font-bold text-white"
                            >
                                {{ initials(application.name, application.email) }}
                            </div>

                            <div class="min-w-0">
                                <p
                                    class="truncate text-sm font-bold text-[#1F2937]"
                                >
                                    {{ application.name || 'N/A' }}
                                </p>

                                <p
                                    class="mt-1 truncate text-xs text-[#64748B]"
                                >
                                    {{ application.email || 'N/A' }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <div class="text-right">
                                <p
                                    class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                >
                                    Application status
                                </p>

                                <div
                                    class="mt-1.5 flex items-center justify-end gap-2"
                                >
                                    <span
                                        :class="[
                                            'h-1.5 w-1.5 rounded-full',
                                            statusDot(application.status),
                                        ]"
                                    ></span>

                                    <span
                                        :class="[
                                            'rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide',
                                            statusColor(application.status),
                                        ]"
                                    >
                                        {{ application.status || 'unknown' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DETAIL CONTENT -->

                <div class="p-4 sm:p-5">

                    <!-- OVERVIEW -->

                    <div class="grid gap-3 sm:grid-cols-2">

                        <!-- ROLE -->

                        <div
                            class="rounded-xl border border-[#E5E7EB] bg-white p-4"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <p
                                    class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                >
                                    Application role
                                </p>

                                <span
                                    :class="[
                                        'rounded-full px-2.5 py-1 text-[10px] font-bold',
                                        roleColor(application.usertype),
                                    ]"
                                >
                                    {{ roleLabel(application.usertype) }}
                                </span>
                            </div>

                            <p
                                class="mt-2 text-sm font-semibold text-[#1F2937]"
                            >
                                {{ roleLabel(application.usertype) }}
                            </p>
                        </div>

                        <!-- SUBMITTED -->

                        <div
                            class="rounded-xl border border-[#E5E7EB] bg-white p-4"
                        >
                            <p
                                class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                            >
                                Submitted
                            </p>

                            <p
                                class="mt-2 text-sm font-semibold text-[#1F2937]"
                            >
                                {{ formatDate(application.created_at) }}
                            </p>
                        </div>
                    </div>

                    <!-- PROFILE INFORMATION -->

                    <div
                        class="mt-5 rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-4"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]"
                            >
                                <svg
                                    class="h-4 w-4"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path
                                        d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 8a7 7 0 1114 0H3z"
                                    />
                                </svg>
                            </div>

                            <div>
                                <p
                                    class="text-xs font-bold text-[#1F2937]"
                                >
                                    Profile information
                                </p>

                                <p
                                    class="mt-1 text-[11px] text-[#94A3B8]"
                                >
                                    Applicant-provided registration details
                                </p>
                            </div>
                        </div>

                        <dl
                            class="mt-5 grid gap-x-8 gap-y-4 text-[13px] sm:grid-cols-2"
                        >
                            <!-- CONTACT -->

                            <div>
                                <dt
                                    class="text-[10px] font-bold uppercase tracking-[0.1em] text-[#94A3B8]"
                                >
                                    Contact number
                                </dt>

                                <dd
                                    class="mt-1 text-sm font-semibold text-[#1F2937]"
                                >
                                    {{ application.contact_no || 'N/A' }}
                                </dd>
                            </div>

                            <!-- STORE -->

                            <div>
                                <dt
                                    class="text-[10px] font-bold uppercase tracking-[0.1em] text-[#94A3B8]"
                                >
                                    Store name
                                </dt>

                                <dd
                                    class="mt-1 text-sm font-semibold text-[#1F2937]"
                                >
                                    {{ application.store_name || 'N/A' }}
                                </dd>
                            </div>

                            <!-- ADDRESS -->

                            <div class="sm:col-span-2">
                                <dt
                                    class="text-[10px] font-bold uppercase tracking-[0.1em] text-[#94A3B8]"
                                >
                                    Address
                                </dt>

                                <dd
                                    class="mt-1 text-sm font-semibold leading-5 text-[#1F2937]"
                                >
                                    {{
                                        [
                                            application.street_address,
                                            application.barangay,
                                            application.municipality,
                                            application.province,
                                            application.address,
                                        ]
                                            .filter(Boolean)
                                            .join(', ') || 'N/A'
                                    }}
                                </dd>
                            </div>

                            <!-- STORE DESCRIPTION -->

                            <div class="sm:col-span-2">
                                <dt
                                    class="text-[10px] font-bold uppercase tracking-[0.1em] text-[#94A3B8]"
                                >
                                    Store description
                                </dt>

                                <dd
                                    class="mt-1 whitespace-pre-line text-sm leading-5 text-[#64748B]"
                                >
                                    {{
                                        application.store_description ||
                                        'No description provided.'
                                    }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- ACTIONS -->

                    <div
                        class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-[#E5E7EB] pt-4"
                    >
                        <Link
                            :href="route('admin.applications')"
                            class="inline-flex items-center rounded-lg border border-[#E5E7EB] bg-white px-3.5 py-2 text-xs font-semibold text-[#64748B] transition hover:bg-[#F8FAF9]"
                        >
                            Back
                        </Link>

                        <button
                            v-if="application.status === 'pending'"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg bg-[#087F8C] px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-[#066D78] focus:outline-none focus:ring-2 focus:ring-[#087F8C]/20"
                            @click="approveApplication(application.id)"
                        >
                            <svg
                                class="h-3.5 w-3.5"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M16.704 5.29a1 1 0 010 1.42l-7.25 7.25a1 1 0 01-1.415.005l-3.25-3.15a1 1 0 111.402-1.43l2.543 2.465 6.548-6.56a1 1 0 011.422 0z"
                                    clip-rule="evenodd"
                                />
                            </svg>

                            Approve application
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- APPLICATION LIST -->
        <!-- ========================================================= -->

        <div
            v-else
            class="mx-auto max-w-7xl"
        >
            <!-- PAGE HEADER -->

            <div
                class="flex flex-col justify-between gap-4 pb-5 sm:flex-row sm:items-end"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <span
                            class="h-1.5 w-1.5 rounded-full bg-[#087F8C]"
                        ></span>

                        <p
                            class="text-xs font-bold uppercase tracking-[0.14em] text-[#087F8C]"
                        >
                            {{ eyebrow || 'Registration management' }}
                        </p>
                    </div>

                    <h1
                        class="mt-2 text-2xl font-bold tracking-tight text-[#1F2937] sm:text-3xl"
                    >
                        {{ title || 'Applications' }}
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
                    class="inline-flex w-fit items-center gap-2 rounded-lg border border-[#E5E7EB] bg-white px-3.5 py-2 text-xs font-semibold text-[#64748B] shadow-sm transition hover:border-[#BFDAD8] hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                >
                    <svg
                        class="h-3.5 w-3.5"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M10.707 2.293a1 1 0 010 1.414L4.414 10l6.293 6.293a.75.75 0 01-1.06 1.06l-7-7a.75.75 0 010-1.06l7-7a1 1 0 011.06 0z"
                            clip-rule="evenodd"
                        />

                        <path
                            fill-rule="evenodd"
                            d="M4 9.25a.75.75 0 000 1.5h12a.75.75 0 000-1.5H4z"
                            clip-rule="evenodd"
                        />
                    </svg>

                    Dashboard
                </Link>
            </div>

            <!-- APPLICATION CARD -->

            <div
                class="mt-5 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
            >
                <!-- FILTER HEADER -->

                <div
                    class="border-b border-[#E5E7EB] px-5 py-4"
                >
                    <div
                        class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <!-- FILTERS -->

                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:flex-wrap"
                        >
                            <!-- SEARCH -->

                            <div class="relative">
                                <svg
                                    class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-[#94A3B8]"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M9 3a6 6 0 104.472 10.003l2.262 2.262a.75.75 0 101.06-1.06l-2.262-2.262A6 6 0 009 3zm-4.5 6a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0z"
                                        clip-rule="evenodd"
                                    />
                                </svg>

                                <input
                                    :value="filters.search ?? ''"
                                    type="text"
                                    placeholder="Search applications..."
                                    class="w-full rounded-lg border border-[#E5E7EB] bg-[#F8FAF9] py-2.5 pl-9 pr-3 text-xs text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#087F8C]/10 sm:w-64"
                                    @input="updateSearch"
                                    @keyup.enter="applyFilters"
                                />
                            </div>

                            <!-- STATUS -->

                            <select
                                :value="filters.status ?? 'all'"
                                class="rounded-lg border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2.5 text-xs text-[#64748B] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#087F8C]/10"
                                @change="updateStatus"
                            >
                                <option value="all">
                                    All statuses
                                </option>

                                <option value="pending">
                                    Pending
                                </option>

                                <option value="approved">
                                    Approved
                                </option>

                                <option value="rejected">
                                    Rejected
                                </option>
                            </select>

                            <!-- ROLE -->

                            <select
                                :value="filters.role ?? 'all'"
                                class="rounded-lg border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2.5 text-xs text-[#64748B] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#087F8C]/10"
                                @change="updateRole"
                            >
                                <option value="all">
                                    All roles
                                </option>

                                <option value="buyer">
                                    Buyer
                                </option>

                                <option value="seller">
                                    Seller
                                </option>

                                <option value="rider">
                                    Courier
                                </option>
                            </select>
                        </div>

                        <!-- COUNT -->

                        <div
                            class="flex items-center gap-2 text-xs font-semibold text-[#64748B]"
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full bg-[#087F8C]"
                            ></span>

                            {{ rows.length }}
                            application{{ rows.length === 1 ? '' : 's' }}
                        </div>
                    </div>
                </div>

                <!-- TABLE -->

                <div class="overflow-x-auto">
                    <table
                        class="w-full min-w-[850px] text-left text-xs text-[#64748B]"
                    >
                        <thead
                            class="border-b border-[#E5E7EB] bg-[#F8FAF9] text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                        >
                            <tr>
                                <th class="px-5 py-3.5">
                                    Applicant
                                </th>

                                <th class="px-5 py-3.5">
                                    Role
                                </th>

                                <th class="px-5 py-3.5">
                                    Submitted
                                </th>

                                <th class="px-5 py-3.5">
                                    Status
                                </th>

                                <th class="px-5 py-3.5">
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
                                <!-- APPLICANT -->

                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#E8F7F6] text-[10px] font-bold text-[#087F8C]"
                                        >
                                            {{ initials(row.name, row.email) }}
                                        </div>

                                        <div class="min-w-0">
                                            <div
                                                class="text-xs font-semibold text-[#1F2937]"
                                            >
                                                {{ row.name || row.email }}
                                            </div>

                                            <div
                                                class="mt-1 max-w-[260px] truncate text-[11px] text-[#94A3B8]"
                                            >
                                                {{ row.email }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- ROLE -->

                                <td class="px-5 py-4">
                                    <span
                                        :class="[
                                            'inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold',
                                            roleColor(row.usertype),
                                        ]"
                                    >
                                        {{ roleLabel(row.usertype) }}
                                    </span>
                                </td>

                                <!-- DATE -->

                                <td
                                    class="whitespace-nowrap px-5 py-4 text-[11px] text-[#64748B]"
                                >
                                    {{ formatDate(row.created_at) }}
                                </td>

                                <!-- STATUS -->

                                <td class="px-5 py-4">
                                    <span
                                        :class="[
                                            'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide',
                                            statusColor(row.status),
                                        ]"
                                    >
                                        <span
                                            :class="[
                                                'h-1.5 w-1.5 rounded-full',
                                                statusDot(row.status),
                                            ]"
                                        ></span>

                                        {{ row.status || 'unknown' }}
                                    </span>
                                </td>

                                <!-- ACTION -->

                                <td class="px-5 py-4">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <!-- REVIEW -->

                                        <Link
                                            :href="
                                                route(
                                                    'admin.applications.show',
                                                    row.id
                                                )
                                            "
                                            class="rounded-lg border border-[#E5E7EB] bg-white px-2.5 py-1.5 text-[11px] font-semibold text-[#087F8C] transition hover:border-[#BFE8E5] hover:bg-[#E8F7F6]"
                                        >
                                            Review
                                        </Link>

                                        <!-- APPROVE -->

                                        <button
                                            v-if="row.status === 'pending'"
                                            type="button"
                                            class="rounded-lg bg-[#E8F7F6] px-2.5 py-1.5 text-[11px] font-bold text-[#087F8C] transition hover:bg-[#D5F0EE]"
                                            @click="approveApplication(row.id)"
                                        >
                                            Approve
                                        </button>

                                        <!-- REJECT -->

                                        <button
                                            v-if="row.status === 'pending'"
                                            type="button"
                                            class="rounded-lg border border-[#F3C5C5] bg-[#FFF0F0] px-2.5 py-1.5 text-[11px] font-semibold text-[#C94C4C] transition hover:bg-[#FFE5E5]"
                                        >
                                            Reject
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>

                        <!-- EMPTY STATE -->

                        <tbody v-else>
                            <tr>
                                <td
                                    colspan="5"
                                    class="px-5 py-12 text-center"
                                >
                                    <div
                                        class="mx-auto flex max-w-md flex-col items-center"
                                    >
                                        <div
                                            class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                                        >
                                            <svg
                                                class="h-5 w-5"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-1 1v2a1 1 0 102 0V8a1 1 0 00-1-1zm0 6a1 1 0 100 2 1 1 0 000-2z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>
                                        </div>

                                        <p
                                            class="mt-3 text-sm font-bold text-[#1F2937]"
                                        >
                                            No applications found
                                        </p>

                                        <p
                                            class="mt-1.5 text-xs leading-5 text-[#64748B]"
                                        >
                                            There are currently no registration
                                            applications matching your filters.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION -->

                <div
                    v-if="applications?.links?.length > 3"
                    class="flex flex-wrap items-center justify-center gap-1.5 border-t border-[#E5E7EB] bg-[#F8FAF9] p-4"
                >
                    <button
                        v-for="(link, index) in applications.links"
                        :key="`${link.label}-${index}`"
                        type="button"
                        :disabled="!link.url"
                        :class="[
                            'min-w-8 rounded-lg px-2.5 py-1.5 text-[11px] font-semibold transition',
                            link.active
                                ? 'bg-[#087F8C] text-white shadow-sm'
                                : 'border border-[#E5E7EB] bg-white text-[#64748B] hover:border-[#BFE8E5] hover:bg-[#E8F7F6] hover:text-[#087F8C]',
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
    </AdminLayout>
</template>