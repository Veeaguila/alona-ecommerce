<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    title: {
        type: String,
        default: 'Complaints and disputes',
    },

    eyebrow: {
        type: String,
        default: 'Dispute management',
    },

    description: {
        type: String,
        default: '',
    },

    active: {
        type: String,
        default: 'complaints',
    },

    complaints: {
        type: Object,
        default: () => ({
            data: [],
            links: [],
            meta: {},
        }),
    },

    filters: {
        type: Object,
        default: () => ({
            search: '',
            status: 'all',
        }),
    },
})

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const search = ref(
    props.filters?.search ?? ''
)

const status = ref(
    props.filters?.status ?? 'all'
)

const processing = ref(false)

/*
|--------------------------------------------------------------------------
| Computed
|--------------------------------------------------------------------------
*/

const rows = computed(() => {
    return props.complaints?.data ?? []
})

/*
|--------------------------------------------------------------------------
| Status Classes
|--------------------------------------------------------------------------
*/

const statusClasses = {
    pending:
        'bg-[#FFF8E7] text-[#B7791F] ring-1 ring-inset ring-[#F4B942]/30',

    reviewing:
        'bg-[#E8F7F6] text-[#087F8C] ring-1 ring-inset ring-[#16A6A0]/25',

    resolved:
        'bg-emerald-50 text-[#22A06B] ring-1 ring-inset ring-emerald-200',

    rejected:
        'bg-red-50 text-[#E85D5D] ring-1 ring-inset ring-red-200',
}

/*
|--------------------------------------------------------------------------
| Status Labels
|--------------------------------------------------------------------------
*/

const statusLabels = {
    pending: 'Pending',
    reviewing: 'Reviewing',
    resolved: 'Resolved',
    rejected: 'Rejected',
}

/*
|--------------------------------------------------------------------------
| Apply Filters
|--------------------------------------------------------------------------
*/

const applyFilters = () => {
    router.get(
        route('admin.complaints'),
        {
            search: search.value.trim(),
            status: status.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    )
}

/*
|--------------------------------------------------------------------------
| Clear Filters
|--------------------------------------------------------------------------
*/

const clearFilters = () => {
    search.value = ''
    status.value = 'all'

    router.get(
        route('admin.complaints'),
        {},
        {
            preserveState: false,
            preserveScroll: true,
            replace: true,
        }
    )
}

/*
|--------------------------------------------------------------------------
| Format Date
|--------------------------------------------------------------------------
*/

const formatDate = (value) => {
    if (!value) {
        return '—'
    }

    const date = new Date(value)

    if (
        Number.isNaN(
            date.getTime()
        )
    ) {
        return value
    }

    return date.toLocaleString()
}

/*
|--------------------------------------------------------------------------
| Status Label
|--------------------------------------------------------------------------
*/

const getStatusLabel = (value) => {
    return (
        statusLabels[value] ??
        value ??
        'Unknown'
    )
}

/*
|--------------------------------------------------------------------------
| Search Enter
|--------------------------------------------------------------------------
*/

const handleSearchKeydown = (event) => {
    if (event.key === 'Enter') {
        applyFilters()
    }
}
</script>

<template>
    <Head :title="title" />

    <AdminLayout :active="active">
        <div class="mx-auto max-w-7xl">

            <!-- PAGE HEADER -->
            <div class="pb-5">
                <div class="flex items-center gap-2">
                    <span
                        class="h-1.5 w-1.5 rounded-full bg-[#087F8C]"
                    ></span>

                    <p
                        class="text-xs font-bold uppercase tracking-[0.14em] text-[#087F8C]"
                    >
                        {{ eyebrow }}
                    </p>
                </div>

                <div
                    class="mt-1.5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div>
                        <h1
                            class="text-2xl font-bold tracking-tight text-[#1F2937] sm:text-3xl"
                        >
                            {{ title }}
                        </h1>

                        <p
                            v-if="description"
                            class="mt-1 max-w-3xl text-sm leading-5 text-[#64748B]"
                        >
                            {{ description }}
                        </p>
                    </div>

                    <Link
                        :href="route('admin.dashboard')"
                        class="inline-flex w-fit shrink-0 items-center gap-1.5 rounded-lg border border-[#E5E7EB] bg-white px-3.5 py-2.5 text-xs font-semibold text-[#64748B] shadow-sm transition hover:border-[#087F8C]/30 hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                    >
                        <svg
                            class="h-3.5 w-3.5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15 18l-6-6 6-6"
                            />
                        </svg>

                        Back to overview
                    </Link>
                </div>
            </div>

            <!-- FILTERS -->
            <section
                class="mt-5 rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
            >
                <!-- FILTER HEADER -->
                <div
                    class="flex items-center gap-3 border-b border-[#E5E7EB] px-5 py-4"
                >
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                    >
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3 4.5h18M6 12h12m-8 7.5h4"
                            />
                        </svg>
                    </div>

                    <div>
                        <h2
                            class="text-base font-bold text-[#1F2937]"
                        >
                            Filter complaints
                        </h2>

                        <p
                            class="mt-0.5 text-xs text-[#94A3B8]"
                        >
                            Search and narrow down dispute records.
                        </p>
                    </div>
                </div>

                <!-- FILTER BODY -->
                <div class="p-4">
                    <div
                        class="flex flex-col gap-3 lg:flex-row lg:items-end"
                    >
                        <!-- SEARCH -->
                        <div class="min-w-0 flex-1">
                            <label
                                class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.12em] text-[#64748B]"
                            >
                                Search
                            </label>

                            <div class="relative">
                                <svg
                                    class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#94A3B8]"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <circle
                                        cx="11"
                                        cy="11"
                                        r="7"
                                    />

                                    <path
                                        stroke-linecap="round"
                                        d="m20 20-4-4"
                                    />
                                </svg>

                                <input
                                    v-model="search"
                                    type="text"
                                    placeholder="Search subject, buyer, seller, order..."
                                    class="w-full rounded-lg border border-[#E5E7EB] bg-[#F8FAF9] py-2.5 pl-9 pr-3 text-[13px] text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                    @keydown="handleSearchKeydown"
                                />
                            </div>
                        </div>

                        <!-- STATUS -->
                        <div class="w-full lg:w-52">
                            <label
                                class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.12em] text-[#64748B]"
                            >
                                Complaint status
                            </label>

                            <select
                                v-model="status"
                                class="w-full rounded-lg border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2.5 text-[13px] text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                @change="applyFilters"
                            >
                                <option value="all">
                                    All statuses
                                </option>

                                <option value="pending">
                                    Pending
                                </option>

                                <option value="reviewing">
                                    Reviewing
                                </option>

                                <option value="resolved">
                                    Resolved
                                </option>

                                <option value="rejected">
                                    Rejected
                                </option>
                            </select>
                        </div>

                        <!-- BUTTONS -->
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-[#087F8C] px-3.5 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-[#066D78] focus:outline-none focus:ring-2 focus:ring-[#E8F7F6]"
                                @click="applyFilters"
                            >
                                <svg
                                    class="h-3.5 w-3.5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <circle
                                        cx="11"
                                        cy="11"
                                        r="7"
                                    />

                                    <path
                                        stroke-linecap="round"
                                        d="m20 20-4-4"
                                    />
                                </svg>

                                Search
                            </button>

                            <button
                                type="button"
                                class="rounded-lg border border-[#E5E7EB] bg-white px-3.5 py-2.5 text-xs font-semibold text-[#64748B] transition hover:bg-[#F8FAF9] hover:text-[#1F2937]"
                                @click="clearFilters"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- TABLE -->
            <section
                class="mt-5 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
            >
                <!-- TABLE HEADER -->
                <div
                    class="flex flex-col gap-3 border-b border-[#E5E7EB] px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                        >
                            <svg
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M8 10h8M8 14h5"
                                />

                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M5 4h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-5l-4 3v-3H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"
                                />
                            </svg>
                        </div>

                        <div>
                            <h2
                                class="text-base font-bold text-[#1F2937]"
                            >
                                Complaints and disputes
                            </h2>

                            <p
                                class="mt-0.5 text-xs text-[#94A3B8]"
                            >
                                Review buyer complaints and resolve marketplace disputes.
                            </p>
                        </div>
                    </div>

                    <div
                        class="inline-flex w-fit items-center rounded-full bg-[#E8F7F6] px-2.5 py-1 text-[10px] font-bold text-[#087F8C]"
                    >
                        {{ rows.length }}
                        complaint{{ rows.length === 1 ? '' : 's' }}
                    </div>
                </div>

                <!-- TABLE CONTENT -->
                <div
                    v-if="rows.length"
                    class="overflow-x-auto"
                >
                    <table
                        class="w-full min-w-[1050px] text-left text-[13px] text-[#64748B]"
                    >
                        <thead
                            class="border-b border-[#E5E7EB] bg-[#F8FAF9] text-[10px] uppercase tracking-[0.12em] text-[#94A3B8]"
                        >
                            <tr>
                                <th class="px-5 py-3 font-bold">
                                    Complaint
                                </th>

                                <th class="px-5 py-3 font-bold">
                                    Buyer
                                </th>

                                <th class="px-5 py-3 font-bold">
                                    Seller
                                </th>

                                <th class="px-5 py-3 font-bold">
                                    Order
                                </th>

                                <th class="px-5 py-3 font-bold">
                                    Status
                                </th>

                                <th class="px-5 py-3 font-bold">
                                    Submitted
                                </th>

                                <th class="px-5 py-3 font-bold">
                                    Action
                                </th>
                            </tr>
                        </thead>

                        <tbody
                            class="divide-y divide-[#E5E7EB]"
                        >
                            <tr
                                v-for="row in rows"
                                :key="row.id"
                                class="transition hover:bg-[#F8FAF9]"
                            >
                                <!-- COMPLAINT -->
                                <td class="px-5 py-4">
                                    <div class="max-w-[260px]">
                                        <p
                                            class="truncate text-[13px] font-semibold text-[#1F2937]"
                                            :title="row.subject"
                                        >
                                            {{
                                                row.subject ??
                                                'Untitled complaint'
                                            }}
                                        </p>

                                        <p
                                            class="mt-1 truncate text-[11px] text-[#94A3B8]"
                                            :title="row.description"
                                        >
                                            {{
                                                row.description ??
                                                'No description'
                                            }}
                                        </p>

                                        <p
                                            class="mt-1.5 text-[10px] font-semibold text-[#94A3B8]"
                                        >
                                            Complaint #{{ row.id }}
                                        </p>
                                    </div>
                                </td>

                                <!-- BUYER -->
                                <td class="px-5 py-4">
                                    <p
                                        class="text-[13px] font-semibold text-[#1F2937]"
                                    >
                                        {{
                                            row.buyer?.name ??
                                            'Unknown buyer'
                                        }}
                                    </p>

                                    <p
                                        class="mt-1 text-[11px] text-[#94A3B8]"
                                    >
                                        {{
                                            row.buyer?.email ??
                                            'No email'
                                        }}
                                    </p>
                                </td>

                                <!-- SELLER -->
                                <td class="px-5 py-4">
                                    <p
                                        class="text-[13px] font-semibold text-[#1F2937]"
                                    >
                                        {{
                                            row.seller?.store_name ??
                                            row.seller?.name ??
                                            'N/A'
                                        }}
                                    </p>

                                    <p
                                        class="mt-1 text-[11px] text-[#94A3B8]"
                                    >
                                        {{
                                            row.seller?.email ??
                                            'No email'
                                        }}
                                    </p>
                                </td>

                                <!-- ORDER -->
                                <td class="px-5 py-4">
                                    <span
                                        v-if="row.order"
                                        class="inline-flex rounded-lg bg-[#F8FAF9] px-2.5 py-1.5 text-[12px] font-semibold text-[#1F2937]"
                                    >
                                        {{
                                            row.order.order_number ??
                                            `Order #${row.order.id}`
                                        }}
                                    </span>

                                    <span
                                        v-else
                                        class="text-[12px] text-[#94A3B8]"
                                    >
                                        N/A
                                    </span>
                                </td>

                                <!-- STATUS -->
                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[9px] font-bold uppercase tracking-wide"
                                        :class="
                                            statusClasses[row.status] ??
                                            'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200'
                                        "
                                    >
                                        <span
                                            class="h-1.5 w-1.5 rounded-full"
                                            :class="
                                                row.status === 'pending'
                                                    ? 'bg-[#F4B942]'
                                                    : row.status === 'reviewing'
                                                        ? 'bg-[#16A6A0]'
                                                        : row.status === 'resolved'
                                                            ? 'bg-[#22A06B]'
                                                            : row.status === 'rejected'
                                                                ? 'bg-[#E85D5D]'
                                                                : 'bg-[#94A3B8]'
                                            "
                                        ></span>

                                        {{
                                            getStatusLabel(
                                                row.status
                                            )
                                        }}
                                    </span>
                                </td>

                                <!-- DATE -->
                                <td
                                    class="whitespace-nowrap px-5 py-4 text-[11px] text-[#94A3B8]"
                                >
                                    {{
                                        formatDate(
                                            row.created_at
                                        )
                                    }}
                                </td>

                                <!-- ACTION -->
                                <td class="px-5 py-4">
                                    <Link
                                        :href="
                                            route(
                                                'admin.complaints.show',
                                                row.id
                                            )
                                        "
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-[#E5E7EB] bg-white px-3 py-1.5 text-[11px] font-semibold text-[#64748B] transition hover:border-[#087F8C]/30 hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                                    >
                                        Review

                                        <svg
                                            class="h-3 w-3"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="m9 18 6-6-6-6"
                                            />
                                        </svg>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- EMPTY -->
                <div
                    v-else
                    class="px-6 py-12 text-center"
                >
                    <div
                        class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                    >
                        <svg
                            class="h-6 w-6"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M8 10h8M8 14h5"
                            />

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M5 4h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-5l-4 3v-3H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"
                            />
                        </svg>
                    </div>

                    <h3
                        class="mt-4 text-base font-bold text-[#1F2937]"
                    >
                        No complaints found
                    </h3>

                    <p
                        class="mx-auto mt-1.5 max-w-md text-[13px] leading-5 text-[#94A3B8]"
                    >
                        No complaints match the current search or status filter.
                    </p>

                    <button
                        v-if="
                            search ||
                            status !== 'all'
                        "
                        type="button"
                        class="mt-4 text-xs font-semibold text-[#087F8C] transition hover:text-[#066D78]"
                        @click="clearFilters"
                    >
                        Clear filters
                    </button>
                </div>

                <!-- PAGINATION -->
                <div
                    v-if="
                        complaints?.links?.length >
                        3
                    "
                    class="flex flex-wrap items-center justify-center gap-1 border-t border-[#E5E7EB] p-3.5"
                >
                    <template
                        v-for="(
                            link,
                            index
                        ) in complaints.links"
                        :key="index"
                    >
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="rounded-lg px-2.5 py-1.5 text-[11px] font-semibold transition"
                            :class="
                                link.active
                                    ? 'bg-[#087F8C] text-white shadow-sm'
                                    : 'text-[#64748B] hover:bg-[#E8F7F6] hover:text-[#087F8C]'
                            "
                            preserve-scroll
                            v-html="link.label"
                        />

                        <span
                            v-else
                            class="rounded-lg px-2.5 py-1.5 text-[11px] font-semibold text-[#CBD5E1]"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>