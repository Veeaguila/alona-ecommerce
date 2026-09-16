<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    title: {
        type: String,
        default: 'Complaint review',
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

    complaint: {
        type: Object,
        default: () => ({}),
    },
})

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const status = ref(
    props.complaint?.status ?? 'pending'
)

const resolution = ref(
    props.complaint?.resolution ?? ''
)

const processing = ref(false)

/*
|--------------------------------------------------------------------------
| Status Configuration
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

const statusLabels = {
    pending: 'Pending',
    reviewing: 'Reviewing',
    resolved: 'Resolved',
    rejected: 'Rejected',
}

/*
|--------------------------------------------------------------------------
| Computed
|--------------------------------------------------------------------------
*/

const currentStatusLabel = computed(() => {
    return (
        statusLabels[status.value] ??
        status.value ??
        'Unknown'
    )
})

const sellerName = computed(() => {
    return (
        props.complaint?.seller?.store_name ??
        props.complaint?.seller?.name ??
        'N/A'
    )
})

const buyerName = computed(() => {
    return (
        props.complaint?.buyer?.name ??
        'Unknown buyer'
    )
})

const courierName = computed(() => {
    return (
        props.complaint?.courier?.name ??
        'No courier assigned'
    )
})

const orderNumber = computed(() => {
    if (!props.complaint?.order) {
        return 'N/A'
    }

    return (
        props.complaint.order.order_number ??
        `Order #${props.complaint.order.id}`
    )
})

const evidence = computed(() => {
    const value =
        props.complaint?.evidence

    if (!value) {
        return []
    }

    if (Array.isArray(value)) {
        return value
    }

    if (typeof value === 'string') {
        try {
            const parsed =
                JSON.parse(value)

            return Array.isArray(parsed)
                ? parsed
                : [parsed]
        } catch {
            return [value]
        }
    }

    return [value]
})

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
| Save Resolution
|--------------------------------------------------------------------------
*/

const saveResolution = () => {
    if (processing.value) {
        return
    }

    if (!resolution.value.trim()) {
        window.alert(
            'Please enter a resolution before saving.'
        )

        return
    }

    processing.value = true

    router.patch(
        route(
            'admin.complaints.resolve',
            props.complaint.id
        ),
        {
            status: status.value,

            resolution:
                resolution.value.trim(),
        },
        {
            preserveScroll: true,

            onFinish: () => {
                processing.value = false
            },
        }
    )
}

/*
|--------------------------------------------------------------------------
| Quick Status
|--------------------------------------------------------------------------
*/

const setStatus = (value) => {
    status.value = value
}
</script>

<template>
    <Head :title="title" />

    <AdminLayout :active="active">
        <div class="mx-auto max-w-5xl">

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

                    <span
                        class="inline-flex w-fit shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-[9px] font-bold uppercase tracking-wide"
                        :class="
                            statusClasses[
                                complaint.status
                            ] ??
                            'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200'
                        "
                    >
                        <span
                            class="h-1.5 w-1.5 rounded-full"
                            :class="
                                complaint.status === 'pending'
                                    ? 'bg-[#F4B942]'
                                    : complaint.status === 'reviewing'
                                        ? 'bg-[#16A6A0]'
                                        : complaint.status === 'resolved'
                                            ? 'bg-[#22A06B]'
                                            : complaint.status === 'rejected'
                                                ? 'bg-[#E85D5D]'
                                                : 'bg-[#94A3B8]'
                            "
                        ></span>

                        {{
                            statusLabels[
                                complaint.status
                            ] ??
                            complaint.status ??
                            'Unknown'
                        }}
                    </span>
                </div>
            </div>

            <!-- MAIN COMPLAINT CARD -->
            <section
                class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
            >

                <!-- COMPLAINT HEADER -->
                <div
                    class="border-b border-[#E5E7EB] px-5 py-4"
                >
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="min-w-0">
                            <p
                                class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                            >
                                Complaint #{{ complaint.id }}
                            </p>

                            <h2
                                class="mt-1 text-xl font-bold leading-7 text-[#1F2937]"
                            >
                                {{
                                    complaint.subject ??
                                    'Untitled complaint'
                                }}
                            </h2>
                        </div>

                        <div
                            class="shrink-0 rounded-xl bg-[#F8FAF9] px-3 py-2 sm:text-right"
                        >
                            <p
                                class="text-[10px] font-bold uppercase tracking-[0.1em] text-[#94A3B8]"
                            >
                                Submitted
                            </p>

                            <p
                                class="mt-0.5 text-[11px] font-semibold text-[#64748B]"
                            >
                                {{
                                    formatDate(
                                        complaint.created_at
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- PARTICIPANTS -->
                <div
                    class="grid gap-3 border-b border-[#E5E7EB] p-4 md:grid-cols-3"
                >

                    <!-- BUYER -->
                    <div
                        class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-4"
                    >
                        <div
                            class="flex items-center gap-2"
                        >
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]"
                            >
                                <svg
                                    class="h-3.5 w-3.5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <circle
                                        cx="12"
                                        cy="8"
                                        r="3"
                                    />

                                    <path
                                        stroke-linecap="round"
                                        d="M6 20c.8-3.2 2.8-5 6-5s5.2 1.8 6 5"
                                    />
                                </svg>
                            </div>

                            <p
                                class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#64748B]"
                            >
                                Buyer
                            </p>
                        </div>

                        <p
                            class="mt-3 text-[13px] font-semibold text-[#1F2937]"
                        >
                            {{ buyerName }}
                        </p>

                        <p
                            class="mt-1 text-[11px] text-[#94A3B8]"
                        >
                            {{
                                complaint.buyer?.email ??
                                'No email'
                            }}
                        </p>

                        <p
                            v-if="complaint.buyer?.contact_no"
                            class="mt-1 text-[11px] text-[#94A3B8]"
                        >
                            {{
                                complaint.buyer.contact_no
                            }}
                        </p>
                    </div>

                    <!-- SELLER -->
                    <div
                        class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-4"
                    >
                        <div
                            class="flex items-center gap-2"
                        >
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]"
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
                                        d="M4 10h16M5 10v9h14v-9M8 10V6h8v4M3 19h18"
                                    />
                                </svg>
                            </div>

                            <p
                                class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#64748B]"
                            >
                                Seller
                            </p>
                        </div>

                        <p
                            class="mt-3 text-[13px] font-semibold text-[#1F2937]"
                        >
                            {{ sellerName }}
                        </p>

                        <p
                            class="mt-1 text-[11px] text-[#94A3B8]"
                        >
                            {{
                                complaint.seller?.email ??
                                'No email'
                            }}
                        </p>

                        <p
                            v-if="complaint.seller?.contact_no"
                            class="mt-1 text-[11px] text-[#94A3B8]"
                        >
                            {{
                                complaint.seller.contact_no
                            }}
                        </p>
                    </div>

                    <!-- COURIER -->
                    <div
                        class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-4"
                    >
                        <div
                            class="flex items-center gap-2"
                        >
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]"
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
                                        d="M3 7h11v10H3zM14 10h4l3 3v4h-7z"
                                    />

                                    <circle
                                        cx="7"
                                        cy="19"
                                        r="2"
                                    />

                                    <circle
                                        cx="18"
                                        cy="19"
                                        r="2"
                                    />
                                </svg>
                            </div>

                            <p
                                class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#64748B]"
                            >
                                Courier
                            </p>
                        </div>

                        <p
                            class="mt-3 text-[13px] font-semibold text-[#1F2937]"
                        >
                            {{ courierName }}
                        </p>

                        <p
                            class="mt-1 text-[11px] text-[#94A3B8]"
                        >
                            {{
                                complaint.courier?.email ??
                                'No email'
                            }}
                        </p>
                    </div>
                </div>

                <!-- ORDER -->
                <div
                    class="border-b border-[#E5E7EB] px-5 py-4"
                >
                    <div
                        class="flex items-center gap-2"
                    >
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]"
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
                                    d="M6 3h12v18H6zM9 7h6M9 11h6M9 15h4"
                                />
                            </svg>
                        </div>

                        <div>
                            <p
                                class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#64748B]"
                            >
                                Related order
                            </p>

                            <p
                                class="mt-0.5 text-[11px] text-[#94A3B8]"
                            >
                                Order associated with this complaint.
                            </p>
                        </div>
                    </div>

                    <div
                        class="mt-3 inline-flex rounded-lg bg-[#F8FAF9] px-3 py-2"
                    >
                        <p
                            class="text-[13px] font-semibold text-[#1F2937]"
                        >
                            {{ orderNumber }}
                        </p>
                    </div>
                </div>

                <!-- DESCRIPTION -->
                <div
                    class="border-b border-[#E5E7EB] px-5 py-5"
                >
                    <div
                        class="flex items-center gap-2"
                    >
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]"
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
                                    d="M5 5h14M5 9h14M5 13h9M5 17h6"
                                />
                            </svg>
                        </div>

                        <div>
                            <p
                                class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#64748B]"
                            >
                                Complaint details
                            </p>

                            <p
                                class="mt-0.5 text-[11px] text-[#94A3B8]"
                            >
                                Details submitted by the complainant.
                            </p>
                        </div>
                    </div>

                    <div
                        class="mt-4 rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-4"
                    >
                        <p
                            class="whitespace-pre-wrap text-[13px] leading-6 text-[#64748B]"
                        >
                            {{
                                complaint.description ??
                                'No description provided.'
                            }}
                        </p>
                    </div>
                </div>

                <!-- EVIDENCE -->
                <div
                    class="border-b border-[#E5E7EB] px-5 py-5"
                >
                    <div
                        class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <div
                                class="flex items-center gap-2"
                            >
                                <div
                                    class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]"
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
                                            d="M15.5 7.5 9 14a3 3 0 0 0 4.2 4.2l6-6a5 5 0 0 0-7.1-7.1l-7 7a7 7 0 0 0 9.9 9.9l6-6"
                                        />
                                    </svg>
                                </div>

                                <div>
                                    <p
                                        class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#64748B]"
                                    >
                                        Evidence
                                    </p>

                                    <p
                                        class="mt-0.5 text-[11px] text-[#94A3B8]"
                                    >
                                        Files or references submitted with the complaint.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <span
                            v-if="evidence.length"
                            class="inline-flex w-fit rounded-full bg-[#E8F7F6] px-2.5 py-1 text-[10px] font-bold text-[#087F8C]"
                        >
                            {{ evidence.length }}
                            item{{ evidence.length === 1 ? '' : 's' }}
                        </span>
                    </div>

                    <!-- EVIDENCE LIST -->
                    <div
                        v-if="evidence.length"
                        class="mt-4 space-y-2"
                    >
                        <div
                            v-for="(
                                item,
                                index
                            ) in evidence"
                            :key="index"
                            class="flex items-center justify-between gap-3 rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-3"
                        >
                            <div
                                class="flex min-w-0 items-center gap-3"
                            >
                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white text-[#087F8C] shadow-sm"
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
                                            d="M6 3h8l4 4v14H6z"
                                        />

                                        <path
                                            stroke-linecap="round"
                                            d="M14 3v5h5"
                                        />
                                    </svg>
                                </div>

                                <div class="min-w-0">
                                    <a
                                        v-if="
                                            typeof item === 'string'
                                        "
                                        :href="item"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="block truncate text-[12px] font-semibold text-[#087F8C] hover:text-[#066D78]"
                                    >
                                        Evidence
                                        {{ index + 1 }}
                                    </a>

                                    <a
                                        v-else-if="item?.url"
                                        :href="item.url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="block truncate text-[12px] font-semibold text-[#087F8C] hover:text-[#066D78]"
                                    >
                                        {{
                                            item.name ??
                                            `Evidence ${index + 1}`
                                        }}
                                    </a>

                                    <p
                                        v-else
                                        class="break-all text-[12px] text-[#64748B]"
                                    >
                                        {{
                                            JSON.stringify(
                                                item
                                            )
                                        }}
                                    </p>

                                    <p
                                        class="mt-0.5 text-[10px] text-[#94A3B8]"
                                    >
                                        Evidence item {{ index + 1 }}
                                    </p>
                                </div>
                            </div>

                            <a
                                v-if="
                                    typeof item === 'string' ||
                                    item?.url
                                "
                                :href="
                                    typeof item === 'string'
                                        ? item
                                        : item.url
                                "
                                target="_blank"
                                rel="noopener noreferrer"
                                class="shrink-0 rounded-lg border border-[#E5E7EB] bg-white px-2.5 py-1.5 text-[10px] font-semibold text-[#64748B] transition hover:border-[#087F8C]/30 hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                            >
                                Open
                            </a>
                        </div>
                    </div>

                    <!-- NO EVIDENCE -->
                    <div
                        v-else
                        class="mt-4 rounded-xl border border-dashed border-[#E5E7EB] bg-[#F8FAF9] px-5 py-6 text-center"
                    >
                        <div
                            class="mx-auto flex h-10 w-10 items-center justify-center rounded-lg bg-white text-[#CBD5E1]"
                        >
                            <svg
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15.5 7.5 9 14a3 3 0 0 0 4.2 4.2l6-6a5 5 0 0 0-7.1-7.1l-7 7a7 7 0 0 0 9.9 9.9l6-6"
                                />
                            </svg>
                        </div>

                        <p
                            class="mt-2 text-[12px] text-[#94A3B8]"
                        >
                            No evidence was submitted.
                        </p>
                    </div>
                </div>

                <!-- EXISTING RESOLUTION -->
                <div
                    v-if="
                        complaint.resolution &&
                        complaint.status !== 'pending'
                    "
                    class="border-b border-[#E5E7EB] px-5 py-5"
                >
                    <div
                        class="flex items-center gap-2"
                    >
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-[#22A06B]"
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
                                    d="m5 12 4 4L19 6"
                                />
                            </svg>
                        </div>

                        <div>
                            <p
                                class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#64748B]"
                            >
                                Current resolution
                            </p>

                            <p
                                class="mt-0.5 text-[11px] text-[#94A3B8]"
                            >
                                The latest resolution recorded for this complaint.
                            </p>
                        </div>
                    </div>

                    <div
                        class="mt-4 rounded-xl border border-emerald-100 bg-emerald-50/50 p-4"
                    >
                        <p
                            class="whitespace-pre-wrap text-[13px] leading-6 text-[#64748B]"
                        >
                            {{
                                complaint.resolution
                            }}
                        </p>
                    </div>

                    <div
                        v-if="complaint.reviewer"
                        class="mt-3 text-[11px] text-[#94A3B8]"
                    >
                        Reviewed by

                        <span
                            class="font-semibold text-[#64748B]"
                        >
                            {{
                                complaint.reviewer.name
                            }}
                        </span>

                        <span
                            v-if="complaint.reviewer.email"
                        >
                            —
                            {{
                                complaint.reviewer.email
                            }}
                        </span>
                    </div>
                </div>

                <!-- ADMIN ACTION -->
                <div
                    class="bg-[#F8FAF9] px-5 py-5"
                >
                    <div
                        class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm sm:p-5"
                    >
                        <!-- ACTION HEADER -->
                        <div
                            class="flex items-start gap-3"
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
                                        d="M12 6v12M6 12h12"
                                    />
                                </svg>
                            </div>

                            <div>
                                <p
                                    class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#087F8C]"
                                >
                                    Administrator action
                                </p>

                                <h2
                                    class="mt-0.5 text-base font-bold text-[#1F2937]"
                                >
                                    Update complaint resolution
                                </h2>

                                <p
                                    class="mt-1 text-xs leading-5 text-[#64748B]"
                                >
                                    Set the current status and record the administrator's resolution.
                                </p>
                            </div>
                        </div>

                        <!-- STATUS -->
                        <div class="mt-5">
                            <div
                                class="mb-2 flex items-center justify-between gap-3"
                            >
                                <label
                                    class="text-xs font-semibold text-[#1F2937]"
                                >
                                    Status
                                </label>

                                <span
                                    class="text-[10px] text-[#94A3B8]"
                                >
                                    Current:
                                    {{ currentStatusLabel }}
                                </span>
                            </div>

                            <div
                                class="grid grid-cols-2 gap-2 sm:grid-cols-4"
                            >
                                <!-- PENDING -->
                                <button
                                    type="button"
                                    class="rounded-lg border px-3 py-2.5 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-50"
                                    :class="
                                        status === 'pending'
                                            ? 'border-[#F4B942]/50 bg-[#FFF8E7] text-[#B7791F]'
                                            : 'border-[#E5E7EB] bg-white text-[#64748B] hover:bg-[#F8FAF9]'
                                    "
                                    :disabled="processing"
                                    @click="setStatus('pending')"
                                >
                                    Pending
                                </button>

                                <!-- REVIEWING -->
                                <button
                                    type="button"
                                    class="rounded-lg border px-3 py-2.5 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-50"
                                    :class="
                                        status === 'reviewing'
                                            ? 'border-[#16A6A0]/40 bg-[#E8F7F6] text-[#087F8C]'
                                            : 'border-[#E5E7EB] bg-white text-[#64748B] hover:bg-[#F8FAF9]'
                                    "
                                    :disabled="processing"
                                    @click="setStatus('reviewing')"
                                >
                                    Reviewing
                                </button>

                                <!-- RESOLVED -->
                                <button
                                    type="button"
                                    class="rounded-lg border px-3 py-2.5 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-50"
                                    :class="
                                        status === 'resolved'
                                            ? 'border-emerald-200 bg-emerald-50 text-[#22A06B]'
                                            : 'border-[#E5E7EB] bg-white text-[#64748B] hover:bg-[#F8FAF9]'
                                    "
                                    :disabled="processing"
                                    @click="setStatus('resolved')"
                                >
                                    Resolved
                                </button>

                                <!-- REJECTED -->
                                <button
                                    type="button"
                                    class="rounded-lg border px-3 py-2.5 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-50"
                                    :class="
                                        status === 'rejected'
                                            ? 'border-red-200 bg-red-50 text-[#E85D5D]'
                                            : 'border-[#E5E7EB] bg-white text-[#64748B] hover:bg-[#F8FAF9]'
                                    "
                                    :disabled="processing"
                                    @click="setStatus('rejected')"
                                >
                                    Rejected
                                </button>
                            </div>
                        </div>

                        <!-- RESOLUTION -->
                        <div class="mt-5">
                            <div
                                class="mb-2 flex items-center justify-between gap-3"
                            >
                                <label
                                    class="text-xs font-semibold text-[#1F2937]"
                                >
                                    Resolution
                                </label>

                                <span
                                    class="text-[10px] text-[#94A3B8]"
                                >
                                    {{ resolution.length }}/2000
                                </span>
                            </div>

                            <textarea
                                v-model="resolution"
                                rows="5"
                                maxlength="2000"
                                placeholder="Enter the action taken, decision, or resolution for this complaint..."
                                class="w-full resize-none rounded-lg border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-3 text-[13px] leading-6 text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6] disabled:cursor-not-allowed disabled:bg-slate-100"
                                :disabled="processing"
                            />

                            <p
                                class="mt-1.5 text-[10px] text-[#94A3B8]"
                            >
                                A resolution is required before the complaint can be saved.
                            </p>
                        </div>

                        <!-- ACTION BUTTONS -->
                        <div
                            class="mt-5 flex flex-col-reverse gap-2 border-t border-[#E5E7EB] pt-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <Link
                                :href="
                                    route(
                                        'admin.complaints'
                                    )
                                "
                                class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-[#E5E7EB] bg-white px-4 py-2.5 text-xs font-semibold text-[#64748B] transition hover:bg-[#F8FAF9] hover:text-[#1F2937]"
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
                                        d="m15 18-6-6 6-6"
                                    />
                                </svg>

                                Back to complaints
                            </Link>

                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#087F8C] px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-[#066D78] disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="
                                    processing ||
                                    !resolution.trim()
                                "
                                @click="saveResolution"
                            >
                                <svg
                                    v-if="!processing"
                                    class="h-3.5 w-3.5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M5 12.5 9.5 17 19 7"
                                    />
                                </svg>

                                <svg
                                    v-else
                                    class="h-3.5 w-3.5 animate-spin"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <circle
                                        cx="12"
                                        cy="12"
                                        r="9"
                                        class="opacity-30"
                                    />

                                    <path
                                        stroke-linecap="round"
                                        d="M21 12a9 9 0 0 1-9 9"
                                    />
                                </svg>

                                {{
                                    processing
                                        ? 'Saving...'
                                        : 'Save resolution'
                                }}
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>