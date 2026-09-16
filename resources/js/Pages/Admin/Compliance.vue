<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    title: {
        type: String,
        default: 'Seller compliance',
    },

    eyebrow: {
        type: String,
        default: 'Compliance',
    },

    description: {
        type: String,
        default: '',
    },

    active: {
        type: String,
        default: 'compliance',
    },

    products: {
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

const search = ref(props.filters?.search ?? '')
const status = ref(props.filters?.status ?? 'all')

const selectedProduct = ref(null)
const selectedAction = ref('')
const note = ref('')
const processing = ref(false)

const showReviewModal = ref(false)
const showHistoryModal = ref(false)

/*
|--------------------------------------------------------------------------
| Computed
|--------------------------------------------------------------------------
*/

const rows = computed(() => {
    return props.products?.data ?? []
})

const requiresNote = computed(() => {
    return (
        selectedAction.value === 'flag' ||
        selectedAction.value === 'suspend'
    )
})

const canSubmitReview = computed(() => {
    if (processing.value || !selectedProduct.value || !selectedAction.value) {
        return false
    }

    if (requiresNote.value && !note.value.trim()) {
        return false
    }

    return true
})

/*
|--------------------------------------------------------------------------
| Status Classes
|--------------------------------------------------------------------------
*/

const statusClasses = {
    pending:
        'bg-[#FFF8E7] text-[#B7791F] ring-1 ring-[#F4B942]/30',

    approved:
        'bg-emerald-50 text-[#16865A] ring-1 ring-emerald-200',

    inactive:
        'bg-red-50 text-[#C94B4B] ring-1 ring-red-200',

    draft:
        'bg-slate-100 text-slate-600 ring-1 ring-slate-200',
}

/*
|--------------------------------------------------------------------------
| Seller Status Classes
|--------------------------------------------------------------------------
*/

const sellerStatusClasses = (value) => {
    switch (value) {
        case 'approved':
            return 'bg-emerald-50 text-[#16865A] ring-1 ring-emerald-200'

        case 'suspended':
            return 'bg-red-50 text-[#C94B4B] ring-1 ring-red-200'

        case 'pending':
            return 'bg-[#FFF8E7] text-[#B7791F] ring-1 ring-[#F4B942]/30'

        default:
            return 'bg-slate-100 text-slate-600 ring-1 ring-slate-200'
    }
}

/*
|--------------------------------------------------------------------------
| Status Dot
|--------------------------------------------------------------------------
*/

const statusDot = (value) => {
    switch (value) {
        case 'approved':
            return 'bg-[#22A06B]'

        case 'pending':
            return 'bg-[#F4B942]'

        case 'inactive':
            return 'bg-[#E85D5D]'

        case 'draft':
            return 'bg-slate-400'

        case 'suspended':
            return 'bg-[#E85D5D]'

        default:
            return 'bg-slate-400'
    }
}

/*
|--------------------------------------------------------------------------
| Action Labels
|--------------------------------------------------------------------------
*/

const actionLabels = {
    approve: 'Approve product',
    flag: 'Flag product',
    suspend: 'Suspend seller',
}

/*
|--------------------------------------------------------------------------
| Review Action Classes
|--------------------------------------------------------------------------
*/

const reviewActionClasses = {
    approved:
        'bg-emerald-50 text-[#16865A] ring-1 ring-emerald-200',

    flagged:
        'bg-[#FFF8E7] text-[#B7791F] ring-1 ring-[#F4B942]/30',

    seller_suspended:
        'bg-red-50 text-[#C94B4B] ring-1 ring-red-200',

    seller_unsuspended:
        'bg-emerald-50 text-[#16865A] ring-1 ring-emerald-200',
}

/*
|--------------------------------------------------------------------------
| Apply Filters
|--------------------------------------------------------------------------
*/

const applyFilters = () => {
    router.get(
        route('admin.compliance'),
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
        route('admin.compliance'),
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
| Open Review Modal
|--------------------------------------------------------------------------
*/

const openReview = (product, action) => {
    selectedProduct.value = product
    selectedAction.value = action
    note.value = ''
    showReviewModal.value = true
}

/*
|--------------------------------------------------------------------------
| Close Review Modal
|--------------------------------------------------------------------------
*/

const closeReview = () => {
    if (processing.value) {
        return
    }

    showReviewModal.value = false
    selectedProduct.value = null
    selectedAction.value = ''
    note.value = ''
}

/*
|--------------------------------------------------------------------------
| Submit Compliance Review
|--------------------------------------------------------------------------
*/

const submitReview = () => {
    if (!canSubmitReview.value) {
        return
    }

    processing.value = true

    router.patch(
        route(
            'admin.compliance.status',
            selectedProduct.value.id
        ),
        {
            action: selectedAction.value,
            note: note.value.trim() || null,
        },
        {
            preserveState: false,
            preserveScroll: true,

            onSuccess: () => {
                showReviewModal.value = false
                selectedProduct.value = null
                selectedAction.value = ''
                note.value = ''

                router.get(
                    route('admin.compliance'),
                    {
                        search: props.filters?.search ?? '',
                        status: props.filters?.status ?? 'all',
                    },
                    {
                        preserveState: false,
                        preserveScroll: true,
                        replace: true,
                    }
                )
            },

            onError: () => {
                processing.value = false
            },

            onFinish: () => {
                processing.value = false
            },
        }
    )
}

/*
|--------------------------------------------------------------------------
| Unsuspend Seller
|--------------------------------------------------------------------------
*/

const unsuspendSeller = (product) => {
    if (!product?.id) {
        return
    }

    const seller = sellerName(product)

    const confirmed = window.confirm(
        `Are you sure you want to unsuspend ${seller}?`
    )

    if (!confirmed) {
        return
    }

    processing.value = true

    router.patch(
        route(
            'admin.compliance.unsuspend',
            product.id
        ),
        {},
        {
            preserveState: false,
            preserveScroll: true,

            onSuccess: () => {
                router.get(
                    route('admin.compliance'),
                    {
                        search: props.filters?.search ?? '',
                        status: props.filters?.status ?? 'all',
                    },
                    {
                        preserveState: false,
                        preserveScroll: true,
                        replace: true,
                    }
                )
            },

            onError: () => {
                processing.value = false
            },

            onFinish: () => {
                processing.value = false
            },
        }
    )
}

/*
|--------------------------------------------------------------------------
| Open History
|--------------------------------------------------------------------------
*/

const openHistory = (product) => {
    selectedProduct.value = product
    showHistoryModal.value = true
}

/*
|--------------------------------------------------------------------------
| Close History
|--------------------------------------------------------------------------
*/

const closeHistory = () => {
    showHistoryModal.value = false
    selectedProduct.value = null
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

    if (Number.isNaN(date.getTime())) {
        return value
    }

    return date.toLocaleString()
}

/*
|--------------------------------------------------------------------------
| Format Action
|--------------------------------------------------------------------------
*/

const formatAction = (action) => {
    return {
        approved: 'Approved',
        flagged: 'Flagged',
        seller_suspended: 'Seller suspended',
        seller_unsuspended: 'Seller unsuspended',
    }[action] ?? action
}

/*
|--------------------------------------------------------------------------
| Seller Name
|--------------------------------------------------------------------------
*/

const sellerName = (product) => {
    return (
        product?.seller?.store_name ||
        product?.seller?.name ||
        'Unknown seller'
    )
}

/*
|--------------------------------------------------------------------------
| Seller Status
|--------------------------------------------------------------------------
*/

const sellerStatus = (product) => {
    return product?.seller?.status ?? 'unknown'
}
</script>

<template>
    <Head :title="title" />

    <AdminLayout :active="active">
        <div class="mx-auto max-w-7xl">

            <!-- ===================================================== -->
            <!-- PAGE HEADER -->
            <!-- ===================================================== -->

            <div
                class="flex flex-col gap-4 border-b border-[#E5E7EB] pb-5 sm:flex-row sm:items-end sm:justify-between"
            >
                <div class="min-w-0">
                    <p
                        class="text-xs font-bold uppercase tracking-[0.14em] text-[#087F8C]"
                    >
                        {{ eyebrow }}
                    </p>

                    <h1
                        class="mt-1 text-2xl font-bold tracking-tight text-[#1F2937] sm:text-3xl"
                    >
                        {{ title }}
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
                    class="inline-flex w-fit shrink-0 items-center gap-2 rounded-lg border border-[#E5E7EB] bg-white px-3.5 py-2 text-xs font-semibold text-[#64748B] shadow-sm transition hover:border-[#087F8C]/30 hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                >
                    <svg
                        class="h-3.5 w-3.5"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M10.707 2.293a1 1 0 010 1.414L4.414 10l6.293 6.293a.75.75 0 01-1.06 1.06l-7-7a.75.75 0 010-1.06l7-7a.75.75 0 011.06 0z"
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

            <!-- ===================================================== -->
            <!-- FILTER CARD -->
            <!-- ===================================================== -->

            <div
                class="mt-5 rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
            >
                <div class="border-b border-[#E5E7EB] px-5 py-4">
                    <div class="flex items-center gap-2">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]"
                        >
                            <svg
                                class="h-4 w-4"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M3 4.75A1.75 1.75 0 014.75 3h10.5A1.75 1.75 0 0117 4.75v.5a1.75 1.75 0 01-.513 1.237L12 10.72v4.53a1.75 1.75 0 01-.768 1.45l-1.5 1A1.75 1.75 0 017 16.25v-5.53L2.513 6.487A1.75 1.75 0 012 5.25v-.5A1.75 1.75 0 013.75 3H3zm.5 1.25l4.28 4.28a.75.75 0 01.22.53v5.44l1-.667v-4.773a.75.75 0 01.22-.53L13.5 6H3.5z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </div>

                        <div>
                            <h2 class="text-base font-bold text-[#1F2937]">
                                Compliance filters
                            </h2>

                            <p class="mt-1 text-xs text-[#94A3B8]">
                                Narrow listings by product name, seller, category, or status.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="p-4">
                    <div class="grid gap-3 lg:grid-cols-[1fr_210px_auto] lg:items-end">

                        <!-- SEARCH -->

                        <div>
                            <label
                                class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                            >
                                Search
                            </label>

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
                                    v-model="search"
                                    type="text"
                                    placeholder="Search product, seller, email, or category..."
                                    class="w-full rounded-lg border border-[#E5E7EB] bg-[#F8FAF9] py-2.5 pl-9 pr-3 text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#087F8C]/10"
                                    @keyup.enter="applyFilters"
                                />
                            </div>
                        </div>

                        <!-- STATUS -->

                        <div>
                            <label
                                class="mb-1.5 block text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                            >
                                Product status
                            </label>

                            <select
                                v-model="status"
                                class="w-full rounded-lg border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2.5 text-sm text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#087F8C]/10"
                                @change="applyFilters"
                            >
                                <option value="all">
                                    All statuses
                                </option>

                                <option value="pending">
                                    Pending review
                                </option>

                                <option value="approved">
                                    Approved
                                </option>

                                <option value="inactive">
                                    Flagged / inactive
                                </option>

                                <option value="draft">
                                    Draft
                                </option>
                            </select>
                        </div>

                        <!-- BUTTONS -->

                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="rounded-lg bg-[#087F8C] px-4 py-2.5 text-xs font-semibold text-white shadow-sm transition hover:bg-[#066D78]"
                                @click="applyFilters"
                            >
                                Search
                            </button>

                            <button
                                type="button"
                                class="rounded-lg border border-[#E5E7EB] bg-white px-4 py-2.5 text-xs font-semibold text-[#64748B] transition hover:border-[#087F8C]/20 hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                                @click="clearFilters"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===================================================== -->
            <!-- PRODUCT COMPLIANCE CARD -->
            <!-- ===================================================== -->

            <div
                class="mt-5 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
            >
                <!-- HEADER -->

                <div
                    class="flex flex-col gap-3 border-b border-[#E5E7EB] px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <div class="flex items-center gap-2">
                            <div
                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]"
                            >
                                <svg
                                    class="h-4 w-4"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path
                                        d="M4 3.5A1.5 1.5 0 015.5 2h9A1.5 1.5 0 0116 3.5v13a1.5 1.5 0 01-1.5 1.5h-9A1.5 1.5 0 014 16.5v-13zM6 5.25A.75.75 0 016.75 4.5h6.5a.75.75 0 010 1.5h-6.5A.75.75 0 016 5.25zm0 3.5A.75.75 0 016.75 8h6.5a.75.75 0 010 1.5h-6.5A.75.75 0 016 8.75zm0 3.5a.75.75 0 01.75-.75h4a.75.75 0 010 1.5h-4a.75.75 0 01-.75-.75z"
                                    />
                                </svg>
                            </div>

                            <div>
                                <h2 class="text-base font-bold text-[#1F2937]">
                                    Product compliance
                                </h2>

                                <p class="mt-1 text-xs text-[#94A3B8]">
                                    Review marketplace listings and seller compliance.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="inline-flex w-fit items-center gap-2 rounded-full bg-[#E8F7F6] px-3 py-1.5 text-[10px] font-bold text-[#087F8C]"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-[#16A6A0]"></span>

                        {{ rows.length }}
                        listing{{ rows.length === 1 ? '' : 's' }}
                    </div>
                </div>

                <!-- TABLE -->

                <div
                    v-if="rows.length"
                    class="overflow-x-auto"
                >
                    <table
                        class="w-full min-w-[1120px] text-left text-[13px] text-[#64748B]"
                    >
                        <thead
                            class="border-b border-[#E5E7EB] bg-[#F8FAF9] text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                        >
                            <tr>
                                <th class="px-5 py-3.5">
                                    Product
                                </th>

                                <th class="px-5 py-3.5">
                                    Seller
                                </th>

                                <th class="px-5 py-3.5">
                                    Category
                                </th>

                                <th class="px-5 py-3.5">
                                    Product status
                                </th>

                                <th class="px-5 py-3.5">
                                    Seller status
                                </th>

                                <th class="px-5 py-3.5">
                                    Actions
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-[#E5E7EB]">
                            <tr
                                v-for="row in rows"
                                :key="row.id"
                                class="group transition hover:bg-[#F8FAF9]"
                            >
                                <!-- PRODUCT -->

                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-[#F8FAF9] ring-1 ring-[#E5E7EB]"
                                        >
                                            <img
                                                v-if="row.image_path"
                                                :src="row.image_path"
                                                :alt="row.name"
                                                class="h-full w-full object-cover"
                                            />

                                            <svg
                                                v-else
                                                class="h-4 w-4 text-[#94A3B8]"
                                                viewBox="0 0 20 20"
                                                fill="currentColor"
                                            >
                                                <path
                                                    d="M3 4.5A1.5 1.5 0 014.5 3h11A1.5 1.5 0 0117 4.5v11a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 013 15.5v-11zM5 5a1 1 0 00-1 1v7.086l2.293-2.293a1 1 0 011.414 0L10 13.086l2.793-2.793a1 1 0 011.414 0L16 12.086V6a1 1 0 00-1-1H5zm0 2a1 1 0 110 2 1 1 0 010-2z"
                                                />
                                            </svg>
                                        </div>

                                        <div class="min-w-0">
                                            <p
                                                class="max-w-[220px] truncate text-xs font-semibold text-[#1F2937]"
                                                :title="row.name"
                                            >
                                                {{ row.name }}
                                            </p>

                                            <p
                                                class="mt-1 text-[10px] text-[#94A3B8]"
                                            >
                                                Product #{{ row.id }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <!-- SELLER -->

                                <td class="px-5 py-4">
                                    <p class="text-xs font-semibold text-[#1F2937]">
                                        {{ sellerName(row) }}
                                    </p>

                                    <p
                                        class="mt-1 max-w-[180px] truncate text-[10px] text-[#94A3B8]"
                                    >
                                        {{ row.seller?.email ?? 'No email' }}
                                    </p>
                                </td>

                                <!-- CATEGORY -->

                                <td class="px-5 py-4 text-xs text-[#64748B]">
                                    {{ row.category?.name ?? 'Unassigned' }}
                                </td>

                                <!-- PRODUCT STATUS -->

                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[9px] font-bold uppercase tracking-wide"
                                        :class="
                                            statusClasses[row.status] ??
                                            'bg-slate-100 text-slate-600 ring-1 ring-slate-200'
                                        "
                                    >
                                        <span
                                            :class="[
                                                'h-1.5 w-1.5 rounded-full',
                                                statusDot(row.status)
                                            ]"
                                        ></span>

                                        {{ row.status ?? 'unknown' }}
                                    </span>
                                </td>

                                <!-- SELLER STATUS -->

                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[9px] font-bold uppercase tracking-wide"
                                        :class="
                                            sellerStatusClasses(
                                                sellerStatus(row)
                                            )
                                        "
                                    >
                                        <span
                                            :class="[
                                                'h-1.5 w-1.5 rounded-full',
                                                statusDot(
                                                    sellerStatus(row)
                                                )
                                            ]"
                                        ></span>

                                        {{ sellerStatus(row) }}
                                    </span>
                                </td>

                                <!-- ACTIONS -->

                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap items-center gap-1.5">

                                        <button
                                            type="button"
                                            class="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-[10px] font-semibold text-[#16865A] transition hover:bg-emerald-100"
                                            @click="openReview(row, 'approve')"
                                        >
                                            Approve
                                        </button>

                                        <button
                                            type="button"
                                            class="rounded-lg border border-[#F4B942]/30 bg-[#FFF8E7] px-2.5 py-1.5 text-[10px] font-semibold text-[#B7791F] transition hover:bg-[#FFF1C9]"
                                            @click="openReview(row, 'flag')"
                                        >
                                            Flag
                                        </button>

                                        <button
                                            v-if="sellerStatus(row) !== 'suspended'"
                                            type="button"
                                            class="rounded-lg border border-red-200 bg-red-50 px-2.5 py-1.5 text-[10px] font-semibold text-[#C94B4B] transition hover:bg-red-100"
                                            @click="openReview(row, 'suspend')"
                                        >
                                            Suspend
                                        </button>

                                        <button
                                            v-else
                                            type="button"
                                            class="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-[10px] font-semibold text-[#16865A] transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="processing"
                                            @click="unsuspendSeller(row)"
                                        >
                                            Unsuspend
                                        </button>

                                        <button
                                            type="button"
                                            class="rounded-lg border border-[#E5E7EB] bg-white px-2.5 py-1.5 text-[10px] font-semibold text-[#64748B] transition hover:border-[#087F8C]/20 hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                                            @click="openHistory(row)"
                                        >
                                            History
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- EMPTY -->

                <div
                    v-else
                    class="px-6 py-14 text-center"
                >
                    <div
                        class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                    >
                        <svg
                            class="h-5 w-5"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path
                                fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.172 7.707 8.879a.75.75 0 10-1.414 1.414l2 2a.75.75 0 001.06 0l3.414-3.414z"
                                clip-rule="evenodd"
                            />
                        </svg>
                    </div>

                    <h3 class="mt-3 text-base font-bold text-[#1F2937]">
                        No products found
                    </h3>

                    <p
                        class="mx-auto mt-1.5 max-w-md text-xs leading-5 text-[#94A3B8]"
                    >
                        No product listings match the current search or status filter.
                    </p>

                    <button
                        v-if="search || status !== 'all'"
                        type="button"
                        class="mt-3 text-xs font-semibold text-[#087F8C] transition hover:text-[#066D78]"
                        @click="clearFilters"
                    >
                        Clear filters
                    </button>
                </div>

                <!-- PAGINATION -->

                <div
                    v-if="products?.links?.length > 3"
                    class="flex flex-wrap items-center justify-center gap-1 border-t border-[#E5E7EB] bg-[#F8FAF9] p-4"
                >
                    <template
                        v-for="(link, index) in products.links"
                        :key="index"
                    >
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="rounded-lg px-3 py-1.5 text-[11px] font-semibold transition"
                            :class="
                                link.active
                                    ? 'bg-[#087F8C] text-white shadow-sm'
                                    : 'text-[#64748B] hover:bg-white hover:text-[#087F8C]'
                            "
                            preserve-scroll
                            v-html="link.label"
                        />

                        <span
                            v-else
                            class="rounded-lg px-3 py-1.5 text-[11px] font-semibold text-[#CBD5E1]"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- REVIEW MODAL -->
        <!-- ========================================================= -->

        <div
            v-if="showReviewModal && selectedProduct"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-[2px]"
            @click.self="closeReview"
        >
            <div
                class="w-full max-w-lg overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-2xl"
            >
                <!-- HEADER -->

                <div
                    class="flex items-start justify-between gap-4 border-b border-[#E5E7EB] px-5 py-4 sm:px-6"
                >
                    <div>
                        <p
                            class="text-xs font-bold uppercase tracking-[0.14em] text-[#087F8C]"
                        >
                            Compliance review
                        </p>

                        <h2 class="mt-1 text-lg font-bold text-[#1F2937]">
                            {{ actionLabels[selectedAction] }}
                        </h2>

                        <p class="mt-1 text-[11px] text-[#94A3B8]">
                            Confirm the compliance action before applying it.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-[#94A3B8] transition hover:bg-[#E8F7F6] hover:text-[#087F8C] disabled:opacity-50"
                        :disabled="processing"
                        @click="closeReview"
                    >
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path
                                d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"
                            />
                        </svg>
                    </button>
                </div>

                <!-- BODY -->

                <div class="px-5 py-5 sm:px-6">

                    <!-- PRODUCT SUMMARY -->

                    <div
                        class="flex items-center gap-3 rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-4"
                    >
                        <div
                            class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white ring-1 ring-[#E5E7EB]"
                        >
                            <img
                                v-if="selectedProduct.image_path"
                                :src="selectedProduct.image_path"
                                :alt="selectedProduct.name"
                                class="h-full w-full object-cover"
                            />

                            <svg
                                v-else
                                class="h-4 w-4 text-[#94A3B8]"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    d="M3 4.5A1.5 1.5 0 014.5 3h11A1.5 1.5 0 0117 4.5v11a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 013 15.5v-11zM5 5a1 1 0 00-1 1v7.086l2.293-2.293a1 1 0 011.414 0L10 13.086l2.793-2.793a1 1 0 011.414 0L16 12.086V6a1 1 0 00-1-1H5zm0 2a1 1 0 110 2 1 1 0 010-2z"
                                />
                            </svg>
                        </div>

                        <div class="min-w-0">
                            <p
                                class="truncate text-sm font-bold text-[#1F2937]"
                            >
                                {{ selectedProduct.name }}
                            </p>

                            <p
                                class="mt-1 truncate text-[11px] text-[#64748B]"
                            >
                                {{ sellerName(selectedProduct) }}
                            </p>

                            <p
                                class="mt-1 text-[10px] text-[#94A3B8]"
                            >
                                {{ selectedProduct.category?.name ?? 'Unassigned' }}
                                ·
                                {{ selectedProduct.status ?? 'unknown' }}
                            </p>
                        </div>
                    </div>

                    <!-- ACTION INFORMATION -->

                    <div
                        class="mt-4 rounded-xl border p-4"
                        :class="
                            selectedAction === 'approve'
                                ? 'border-emerald-200 bg-emerald-50'
                                : selectedAction === 'flag'
                                    ? 'border-[#F4B942]/30 bg-[#FFF8E7]'
                                    : 'border-red-200 bg-red-50'
                        "
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/80"
                            >
                                <svg
                                    v-if="selectedAction === 'approve'"
                                    class="h-4 w-4 text-[#22A06B]"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M16.704 5.29a1 1 0 010 1.42l-7.25 7.25a1 1 0 01-1.415.005l-3.25-3.15a1 1 0 111.402-1.43l2.543 2.465 6.548-6.56a1 1 0 011.422 0z"
                                        clip-rule="evenodd"
                                    />
                                </svg>

                                <svg
                                    v-else-if="selectedAction === 'flag'"
                                    class="h-4 w-4 text-[#B7791F]"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M4.25 2a.75.75 0 01.75.75V4h7.75a.75.75 0 01.61.314l2.5 3.5a.75.75 0 010 .872l-2.5 3.5a.75.75 0 01-.61.314H5v4.75a.75.75 0 01-1.5 0v-15A.75.75 0 014.25 2z"
                                        clip-rule="evenodd"
                                    />
                                </svg>

                                <svg
                                    v-else
                                    class="h-4 w-4 text-[#E85D5D]"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M8.485 2.34a2 2 0 013.03 0l6.145 7.167a2 2 0 01-1.516 3.293H3.856a2 2 0 01-1.516-3.293L8.485 2.34zM10 7a.75.75 0 01.75.75v2.5a.75.75 0 01-1.5 0v-2.5A1 1 0 0110 7zm0 5.25a.875.875 0 100 1.75.875.875 0 000-1.75z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </div>

                            <div>
                                <p
                                    class="text-xs font-bold"
                                    :class="
                                        selectedAction === 'approve'
                                            ? 'text-emerald-800'
                                            : selectedAction === 'flag'
                                                ? 'text-[#8A6418]'
                                                : 'text-red-800'
                                    "
                                >
                                    {{
                                        selectedAction === 'approve'
                                            ? 'Approve this listing'
                                            : selectedAction === 'flag'
                                                ? 'Flag this listing'
                                                : 'Suspend this seller'
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-[11px] leading-5"
                                    :class="
                                        selectedAction === 'approve'
                                            ? 'text-emerald-700'
                                            : selectedAction === 'flag'
                                                ? 'text-[#9A731F]'
                                                : 'text-red-700'
                                    "
                                >
                                    {{
                                        selectedAction === 'approve'
                                            ? 'The product will be marked approved and can be restored to the marketplace.'
                                            : selectedAction === 'flag'
                                                ? 'The product will be hidden from buyers and marked inactive.'
                                                : 'The seller account will be suspended and this product will be hidden from buyers.'
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- NOTE -->

                    <div class="mt-5">
                        <div class="mb-1.5 flex items-center justify-between">
                            <label class="text-xs font-semibold text-[#1F2937]">
                                Review note

                                <span
                                    v-if="requiresNote"
                                    class="text-[#E85D5D]"
                                >
                                    *
                                </span>
                            </label>

                            <span class="text-[10px] text-[#94A3B8]">
                                {{ note.length }}/1000
                            </span>
                        </div>

                        <textarea
                            v-model="note"
                            rows="4"
                            maxlength="1000"
                            placeholder="Enter the reason or compliance notes..."
                            class="w-full resize-none rounded-lg border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2.5 text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#087F8C]/10"
                        />

                        <p
                            v-if="requiresNote && !note.trim()"
                            class="mt-1.5 text-[10px] text-[#E85D5D]"
                        >
                            A reason is required for this action.
                        </p>
                    </div>
                </div>

                <!-- FOOTER -->

                <div
                    class="flex justify-end gap-2 border-t border-[#E5E7EB] bg-[#F8FAF9] px-5 py-4 sm:px-6"
                >
                    <button
                        type="button"
                        class="rounded-lg border border-[#E5E7EB] bg-white px-4 py-2 text-xs font-semibold text-[#64748B] transition hover:bg-slate-50 disabled:opacity-50"
                        :disabled="processing"
                        @click="closeReview"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="rounded-lg px-4 py-2 text-xs font-semibold text-white shadow-sm transition disabled:cursor-not-allowed disabled:opacity-50"
                        :class="
                            selectedAction === 'approve'
                                ? 'bg-[#22A06B] hover:bg-[#16865A]'
                                : selectedAction === 'flag'
                                    ? 'bg-[#C89220] hover:bg-[#B17C18]'
                                    : 'bg-[#E85D5D] hover:bg-[#D94D4D]'
                        "
                        :disabled="!canSubmitReview"
                        @click="submitReview"
                    >
                        {{
                            processing
                                ? 'Processing...'
                                : actionLabels[selectedAction]
                        }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- HISTORY MODAL -->
        <!-- ========================================================= -->

        <div
            v-if="showHistoryModal && selectedProduct"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-[2px]"
            @click.self="closeHistory"
        >
            <div
                class="max-h-[85vh] w-full max-w-2xl overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-2xl"
            >
                <!-- HEADER -->

                <div
                    class="flex items-start justify-between gap-4 border-b border-[#E5E7EB] px-5 py-4 sm:px-6"
                >
                    <div class="min-w-0">
                        <p
                            class="text-xs font-bold uppercase tracking-[0.14em] text-[#087F8C]"
                        >
                            Compliance history
                        </p>

                        <h2
                            class="mt-1 truncate text-lg font-bold text-[#1F2937]"
                        >
                            {{ selectedProduct.name }}
                        </h2>

                        <p class="mt-1 truncate text-[11px] text-[#94A3B8]">
                            {{ sellerName(selectedProduct) }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-[#94A3B8] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                        @click="closeHistory"
                    >
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path
                                d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"
                            />
                        </svg>
                    </button>
                </div>

                <!-- HISTORY -->

                <div
                    class="max-h-[60vh] overflow-y-auto px-5 py-5 sm:px-6"
                >
                    <div
                        v-if="selectedProduct.complianceReviews?.length"
                        class="space-y-3"
                    >
                        <div
                            v-for="review in selectedProduct.complianceReviews"
                            :key="review.id"
                            class="rounded-xl border border-[#E5E7EB] bg-white p-4 transition hover:border-[#087F8C]/30 hover:bg-[#F8FAF9]"
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div>
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-1 text-[9px] font-bold uppercase tracking-wide"
                                        :class="
                                            reviewActionClasses[
                                                review.action
                                            ] ??
                                            'bg-slate-100 text-slate-600 ring-1 ring-slate-200'
                                        "
                                    >
                                        {{ formatAction(review.action) }}
                                    </span>

                                    <p class="mt-2 text-[11px] text-[#94A3B8]">
                                        Reviewed by

                                        <span class="font-semibold text-[#64748B]">
                                            {{ review.admin?.name ?? 'Administrator' }}
                                        </span>
                                    </p>
                                </div>

                                <p class="text-[10px] text-[#94A3B8]">
                                    {{ formatDate(review.reviewed_at) }}
                                </p>
                            </div>

                            <div
                                v-if="review.note"
                                class="mt-3 rounded-lg border border-[#E5E7EB] bg-[#F8FAF9] p-3"
                            >
                                <p
                                    class="text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                >
                                    Review note
                                </p>

                                <p
                                    class="mt-1 whitespace-pre-wrap text-xs leading-5 text-[#64748B]"
                                >
                                    {{ review.note }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- EMPTY HISTORY -->

                    <div
                        v-else
                        class="py-12 text-center"
                    >
                        <div
                            class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                        >
                            <svg
                                class="h-5 w-5"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-11a.75.75 0 01.75.75v2.75a.75.75 0 01-.75.75H7.25a.75.75 0 010-1.5h2V7.75A.75.75 0 0110 7zm0 6a1 1 0 100-2 1 1 0 000 2z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </div>

                        <h3 class="mt-3 text-sm font-bold text-[#1F2937]">
                            No compliance history
                        </h3>

                        <p
                            class="mx-auto mt-1.5 max-w-sm text-xs leading-5 text-[#94A3B8]"
                        >
                            No administrator review actions have been recorded
                            for this product yet.
                        </p>
                    </div>
                </div>

                <!-- FOOTER -->

                <div
                    class="flex justify-end border-t border-[#E5E7EB] bg-[#F8FAF9] px-5 py-4 sm:px-6"
                >
                    <button
                        type="button"
                        class="rounded-lg bg-[#087F8C] px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-[#066D78]"
                        @click="closeHistory"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>