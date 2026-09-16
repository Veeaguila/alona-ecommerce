<script setup>
import { computed, ref } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'

const page = usePage()

const props = defineProps({
    vouchers: {
        type: Array,
        default: () => [],
    },
})

const showModal = ref(false)
const editingVoucher = ref(null)

const form = useForm({
    code: '',
    type: 'percentage',
    value: '',
    min_spend: '',
    usage_limit: '',
    starts_at: '',
    expires_at: '',
    is_active: true,
})

/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/

const totalVouchers = computed(() => props.vouchers.length)

const activeVouchers = computed(() =>
    props.vouchers.filter(voucher => voucher.is_active).length
)

const inactiveVouchers = computed(() =>
    props.vouchers.filter(voucher => !voucher.is_active).length
)

const totalUses = computed(() =>
    props.vouchers.reduce(
        (total, voucher) =>
            total + Number(voucher.used_count ?? 0),
        0
    )
)

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const formatDateForInput = date => {
    if (!date) {
        return ''
    }

    return String(date).slice(0, 10)
}

const formatDate = date => {
    if (!date) {
        return '—'
    }

    const parsed = new Date(
        `${String(date).slice(0, 10)}T00:00:00`
    )

    if (Number.isNaN(parsed.getTime())) {
        return '—'
    }

    return parsed.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}

const money = value => {
    const amount = Number(value ?? 0)

    return `₱${amount.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`
}

const discountLabel = voucher => {
    if (voucher.type === 'percentage') {
        return `${Number(voucher.value)}% OFF`
    }

    return `${money(voucher.value)} OFF`
}

const statusLabel = voucher => {
    if (!voucher.is_active) {
        return 'Inactive'
    }

    if (
        voucher.usage_limit &&
        Number(voucher.used_count ?? 0) >=
            Number(voucher.usage_limit)
    ) {
        return 'Limit Reached'
    }

    const today = new Date()
    today.setHours(0, 0, 0, 0)

    if (voucher.starts_at) {
        const start = new Date(
            `${String(voucher.starts_at).slice(0, 10)}T00:00:00`
        )

        if (start > today) {
            return 'Scheduled'
        }
    }

    if (voucher.expires_at) {
        const expiry = new Date(
            `${String(voucher.expires_at).slice(0, 10)}T23:59:59`
        )

        if (expiry < new Date()) {
            return 'Expired'
        }
    }

    return 'Active'
}

const statusClass = voucher => {
    const status = statusLabel(voucher)

    switch (status) {
        case 'Active':
            return 'border-[#BBE7D3] bg-[#ECFDF5] text-[#16845A]'

        case 'Scheduled':
            return 'border-[#BDE9E7] bg-[#E8F7F6] text-[#087F8C]'

        case 'Limit Reached':
            return 'border-[#FDE68A] bg-[#FFFBEB] text-[#B77900]'

        case 'Expired':
            return 'border-[#FECACA] bg-[#FEF2F2] text-[#DC2626]'

        default:
            return 'border-[#E5E7EB] bg-[#F8FAF9] text-[#64748B]'
    }
}

const usagePercent = voucher => {
    if (!voucher.usage_limit) {
        return 0
    }

    return Math.min(
        100,
        (Number(voucher.used_count ?? 0) /
            Number(voucher.usage_limit)) *
            100
    )
}

/*
|--------------------------------------------------------------------------
| Modal
|--------------------------------------------------------------------------
*/

const openCreateModal = () => {
    editingVoucher.value = null

    form.reset()

    form.code = ''
    form.type = 'percentage'
    form.value = ''
    form.min_spend = ''
    form.usage_limit = ''
    form.starts_at = ''
    form.expires_at = ''
    form.is_active = true

    form.clearErrors()

    showModal.value = true
}

const openEditModal = voucher => {
    editingVoucher.value = voucher

    form.code = voucher.code ?? ''
    form.type = voucher.type ?? 'percentage'
    form.value = voucher.value ?? ''
    form.min_spend = voucher.min_spend ?? ''
    form.usage_limit = voucher.usage_limit ?? ''
    form.starts_at = formatDateForInput(voucher.starts_at)
    form.expires_at = formatDateForInput(voucher.expires_at)
    form.is_active = Boolean(voucher.is_active)

    form.clearErrors()

    showModal.value = true
}

const closeModal = () => {
    if (form.processing) {
        return
    }

    showModal.value = false
    editingVoucher.value = null
    form.clearErrors()
}

/*
|--------------------------------------------------------------------------
| Create / Update
|--------------------------------------------------------------------------
*/

const submit = () => {
    if (editingVoucher.value) {
        form.patch(
            route(
                'seller.vouchers.update',
                editingVoucher.value.id
            ),
            {
                preserveScroll: true,
                onSuccess: () => {
                    closeModal()
                },
            }
        )

        return
    }

    form.post(
        route('seller.vouchers.store'),
        {
            preserveScroll: true,
            onSuccess: () => {
                closeModal()
            },
        }
    )
}

/*
|--------------------------------------------------------------------------
| Toggle Active / Inactive
|--------------------------------------------------------------------------
*/

const toggleVoucher = voucher => {
    const toggleForm = useForm({
        code: voucher.code,
        type: voucher.type,
        value: voucher.value,
        min_spend: voucher.min_spend ?? '',
        usage_limit: voucher.usage_limit ?? '',
        starts_at: formatDateForInput(voucher.starts_at),
        expires_at: formatDateForInput(voucher.expires_at),
        is_active: !Boolean(voucher.is_active),
    })

    toggleForm.patch(
        route('seller.vouchers.update', voucher.id),
        {
            preserveScroll: true,
        }
    )
}

/*
|--------------------------------------------------------------------------
| Delete
|--------------------------------------------------------------------------
*/

const deleteVoucher = voucher => {
    const confirmed = window.confirm(
        `Are you sure you want to delete voucher "${voucher.code}"?`
    )

    if (!confirmed) {
        return
    }

    const deleteForm = useForm({})

    deleteForm.delete(
        route('seller.vouchers.destroy', voucher.id),
        {
            preserveScroll: true,
        }
    )
}

/*
|--------------------------------------------------------------------------
| Flash
|--------------------------------------------------------------------------
*/

const statusMessage = computed(
    () => page.props.flash?.status ?? ''
)
</script>

<template>
    <Head title="Vouchers" />

    <SellerLayout>
        <div class="min-h-[calc(100vh-68px)] bg-[#F8FAF9]">
            <div
                class="mx-auto w-full max-w-7xl px-4 py-5 sm:px-5 lg:px-6"
            >
                <!-- PAGE HEADER -->
                <div
                    class="flex flex-col gap-4 border-b border-[#E5E7EB] pb-5 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div>
                        <p
                            class="text-xs font-bold uppercase tracking-[0.14em] text-[#087F8C]"
                        >
                            Seller Center
                        </p>

                        <h1
                            class="mt-1 text-2xl font-bold tracking-tight text-[#1F2937] sm:text-3xl"
                        >
                            Vouchers
                        </h1>

                        <p
                            class="mt-1 text-sm text-[#64748B]"
                        >
                            Create and manage discount vouchers for your customers.
                        </p>
                    </div>
                </div>

                <!-- FLASH MESSAGE -->
                <div
                    v-if="statusMessage"
                    class="mt-5 flex items-center gap-3 rounded-xl border border-[#BBE7D3] bg-[#ECFDF5] px-4 py-3 text-sm font-semibold text-[#16845A]"
                >
                    <div
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white"
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M5 12.5l4 4L19 7"
                            />
                        </svg>
                    </div>

                    {{ statusMessage }}
                </div>

                <!-- SUMMARY -->
                <div
                    class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4"
                >
                    <!-- TOTAL -->
                    <div
                        class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm"
                    >
                        <div
                            class="flex items-start justify-between gap-3"
                        >
                            <div>
                                <p
                                    class="text-[11px] font-semibold uppercase tracking-wide text-[#94A3B8]"
                                >
                                    Total Vouchers
                                </p>

                                <p
                                    class="mt-1.5 text-xl font-bold tracking-tight text-[#1F2937] sm:text-2xl"
                                >
                                    {{ totalVouchers }}
                                </p>

                                <p
                                    class="mt-1 text-[11px] text-[#64748B]"
                                >
                                    All created vouchers
                                </p>
                            </div>

                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.7"
                                        d="M15 5l4 4m0 0l-9 9H6v-4l9-9zm-1-1l2-2 4 4-2 2"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- ACTIVE -->
                    <div
                        class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm"
                    >
                        <div
                            class="flex items-start justify-between gap-3"
                        >
                            <div>
                                <p
                                    class="text-[11px] font-semibold uppercase tracking-wide text-[#94A3B8]"
                                >
                                    Active
                                </p>

                                <p
                                    class="mt-1.5 text-xl font-bold tracking-tight text-[#22A06B] sm:text-2xl"
                                >
                                    {{ activeVouchers }}
                                </p>

                                <p
                                    class="mt-1 text-[11px] text-[#64748B]"
                                >
                                    Currently enabled
                                </p>
                            </div>

                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#ECFDF5] text-[#22A06B]"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.8"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- INACTIVE -->
                    <div
                        class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm"
                    >
                        <div
                            class="flex items-start justify-between gap-3"
                        >
                            <div>
                                <p
                                    class="text-[11px] font-semibold uppercase tracking-wide text-[#94A3B8]"
                                >
                                    Inactive
                                </p>

                                <p
                                    class="mt-1.5 text-xl font-bold tracking-tight text-[#64748B] sm:text-2xl"
                                >
                                    {{ inactiveVouchers }}
                                </p>

                                <p
                                    class="mt-1 text-[11px] text-[#64748B]"
                                >
                                    Currently disabled
                                </p>
                            </div>

                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#F1F5F9] text-[#64748B]"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.7"
                                        d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- USES -->
                    <div
                        class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm"
                    >
                        <div
                            class="flex items-start justify-between gap-3"
                        >
                            <div>
                                <p
                                    class="text-[11px] font-semibold uppercase tracking-wide text-[#94A3B8]"
                                >
                                    Total Uses
                                </p>

                                <p
                                    class="mt-1.5 text-xl font-bold tracking-tight text-[#087F8C] sm:text-2xl"
                                >
                                    {{ totalUses }}
                                </p>

                                <p
                                    class="mt-1 text-[11px] text-[#64748B]"
                                >
                                    Customer redemptions
                                </p>
                            </div>

                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#16A6A0]"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.7"
                                        d="M17 20h5V4H2v16h5m10 0v-6H7v6m10 0H7"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- DESKTOP VOUCHER TABLE -->
                <section
                    class="mt-5 hidden overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm lg:block"
                >
                    <div
                        class="flex items-center justify-between border-b border-[#E5E7EB] px-5 py-4"
                    >
                        <div>
                            <div
                                class="flex items-center gap-2"
                            >
                                <h2
                                    class="text-base font-bold text-[#1F2937]"
                                >
                                    Voucher Management
                                </h2>

                                <span
                                    class="rounded-full bg-[#E8F7F6] px-2 py-0.5 text-[10px] font-bold text-[#087F8C]"
                                >
                                    {{ vouchers.length }}
                                </span>
                            </div>

                            <p
                                class="mt-1 text-xs text-[#94A3B8]"
                            >
                                Manage your discount campaigns and voucher usage.
                            </p>
                        </div>

                        <button
                            v-if="vouchers.length"
                            type="button"
                            @click="openCreateModal"
                            class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-[#BDE9E7] bg-[#E8F7F6] px-3 text-xs font-bold text-[#087F8C] transition hover:bg-[#D9F2F0]"
                        >
                            <svg
                                class="h-3.5 w-3.5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.8"
                                    d="M12 4v16m8-8H4"
                                />
                            </svg>

                            New Voucher
                        </button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr
                                    class="border-b border-[#E5E7EB] bg-[#F8FAF9]"
                                >
                                    <th
                                        class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-[0.08em] text-[#94A3B8]"
                                    >
                                        Voucher
                                    </th>

                                    <th
                                        class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-[0.08em] text-[#94A3B8]"
                                    >
                                        Discount
                                    </th>

                                    <th
                                        class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-[0.08em] text-[#94A3B8]"
                                    >
                                        Minimum Spend
                                    </th>

                                    <th
                                        class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-[0.08em] text-[#94A3B8]"
                                    >
                                        Usage
                                    </th>

                                    <th
                                        class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-[0.08em] text-[#94A3B8]"
                                    >
                                        Validity
                                    </th>

                                    <th
                                        class="px-5 py-3 text-left text-[10px] font-bold uppercase tracking-[0.08em] text-[#94A3B8]"
                                    >
                                        Status
                                    </th>

                                    <th
                                        class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-[0.08em] text-[#94A3B8]"
                                    >
                                        Actions
                                    </th>
                                </tr>
                            </thead>

                            <tbody
                                v-if="vouchers.length"
                                class="divide-y divide-[#F1F5F9]"
                            >
                                <tr
                                    v-for="voucher in vouchers"
                                    :key="voucher.id"
                                    class="transition hover:bg-[#FAFCFB]"
                                >
                                    <!-- Voucher -->
                                    <td class="px-5 py-4">
                                        <p
                                            class="text-sm font-bold tracking-wide text-[#087F8C]"
                                        >
                                            {{ voucher.code }}
                                        </p>

                                        <p
                                            class="mt-1 text-[11px] text-[#94A3B8]"
                                        >
                                            Created
                                            {{ formatDate(voucher.created_at) }}
                                        </p>
                                    </td>

                                    <!-- Discount -->
                                    <td class="px-5 py-4">
                                        <p
                                            class="text-sm font-bold text-[#1F2937]"
                                        >
                                            {{ discountLabel(voucher) }}
                                        </p>

                                        <p
                                            class="mt-1 text-[10px] capitalize text-[#94A3B8]"
                                        >
                                            {{ voucher.type }}
                                        </p>
                                    </td>

                                    <!-- Minimum Spend -->
                                    <td
                                        class="px-5 py-4 text-sm font-medium text-[#64748B]"
                                    >
                                        {{
                                            voucher.min_spend
                                                ? money(voucher.min_spend)
                                                : 'No minimum'
                                        }}
                                    </td>

                                    <!-- Usage -->
                                    <td class="px-5 py-4">
                                        <p
                                            class="text-sm font-semibold text-[#1F2937]"
                                        >
                                            {{ voucher.used_count ?? 0 }}

                                            <span
                                                v-if="voucher.usage_limit"
                                                class="font-normal text-[#94A3B8]"
                                            >
                                                /
                                                {{ voucher.usage_limit }}
                                            </span>
                                        </p>

                                        <div
                                            v-if="voucher.usage_limit"
                                            class="mt-2 h-1.5 w-24 overflow-hidden rounded-full bg-[#E8F7F6]"
                                        >
                                            <div
                                                class="h-full rounded-full bg-[#16A6A0] transition-all"
                                                :style="{
                                                    width: `${usagePercent(voucher)}%`,
                                                }"
                                            />
                                        </div>

                                        <p
                                            v-else
                                            class="mt-1 text-[10px] text-[#94A3B8]"
                                        >
                                            Unlimited
                                        </p>
                                    </td>

                                    <!-- Validity -->
                                    <td
                                        class="whitespace-nowrap px-5 py-4 text-sm text-[#64748B]"
                                    >
                                        {{ formatDate(voucher.starts_at) }}

                                        <span
                                            class="mx-1 text-[#CBD5E1]"
                                        >
                                            →
                                        </span>

                                        {{ formatDate(voucher.expires_at) }}
                                    </td>

                                    <!-- Status -->
                                    <td class="px-5 py-4">
                                        <span
                                            class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-bold"
                                            :class="statusClass(voucher)"
                                        >
                                            {{ statusLabel(voucher) }}
                                        </span>
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-5 py-4">
                                        <div
                                            class="flex items-center justify-end gap-1.5"
                                        >
                                            <button
                                                type="button"
                                                @click="toggleVoucher(voucher)"
                                                class="rounded-lg border px-2.5 py-1.5 text-[10px] font-bold transition"
                                                :class="
                                                    voucher.is_active
                                                        ? 'border-[#FDE68A] bg-[#FFF8E7] text-[#A66A00] hover:bg-[#FFF1C9]'
                                                        : 'border-[#BBE7D3] bg-[#ECFDF5] text-[#16845A] hover:bg-[#DDF8EA]'
                                                "
                                            >
                                                {{
                                                    voucher.is_active
                                                        ? 'Deactivate'
                                                        : 'Activate'
                                                }}
                                            </button>

                                            <button
                                                type="button"
                                                @click="openEditModal(voucher)"
                                                class="rounded-lg border border-[#E5E7EB] bg-white px-2.5 py-1.5 text-[10px] font-bold text-[#64748B] transition hover:border-[#087F8C] hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                                            >
                                                Edit
                                            </button>

                                            <button
                                                type="button"
                                                @click="deleteVoucher(voucher)"
                                                class="rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-2.5 py-1.5 text-[10px] font-bold text-[#DC2626] transition hover:bg-[#FDE8E8]"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>

                            <!-- Empty -->
                            <tbody v-else>
                                <tr>
                                    <td
                                        colspan="7"
                                        class="px-6 py-14 text-center"
                                    >
                                        <div
                                            class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-[#E8F7F6] text-[#087F8C]"
                                        >
                                            <svg
                                                class="h-6 w-6"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="1.7"
                                                    d="M15 5l4 4m0 0l-9 9H6v-4l9-9zm-1-1l2-2 4 4-2 2"
                                                />
                                            </svg>
                                        </div>

                                        <h3
                                            class="mt-3 text-sm font-bold text-[#1F2937]"
                                        >
                                            No vouchers yet
                                        </h3>

                                        <p
                                            class="mt-1 text-xs text-[#94A3B8]"
                                        >
                                            Create your first voucher to offer discounts to customers.
                                        </p>

                                        <button
                                            type="button"
                                            @click="openCreateModal"
                                            class="mt-4 inline-flex h-9 items-center rounded-lg bg-[#087F8C] px-4 text-xs font-bold text-white transition hover:bg-[#066B76]"
                                        >
                                            Create Voucher
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- MOBILE VOUCHERS -->
                <section class="mt-5 lg:hidden">
                    <div
                        class="mb-3 flex items-center justify-between"
                    >
                        <div>
                            <h2
                                class="text-base font-bold text-[#1F2937]"
                            >
                                Voucher Management
                            </h2>

                            <p
                                class="mt-0.5 text-xs text-[#94A3B8]"
                            >
                                {{ vouchers.length }} voucher{{
                                    vouchers.length === 1 ? '' : 's'
                                }}
                            </p>
                        </div>

                        <button
                            v-if="vouchers.length"
                            type="button"
                            @click="openCreateModal"
                            class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[#087F8C] px-3 text-xs font-bold text-white"
                        >
                            <svg
                                class="h-3.5 w-3.5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.8"
                                    d="M12 4v16m8-8H4"
                                />
                            </svg>

                            New
                        </button>
                    </div>

                    <div
                        v-if="vouchers.length"
                        class="space-y-3"
                    >
                        <div
                            v-for="voucher in vouchers"
                            :key="voucher.id"
                            class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm"
                        >
                            <!-- Card Top -->
                            <div
                                class="flex items-start justify-between gap-3"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="truncate text-sm font-bold tracking-wide text-[#087F8C]"
                                    >
                                        {{ voucher.code }}
                                    </p>

                                    <p
                                        class="mt-1 text-sm font-bold text-[#1F2937]"
                                    >
                                        {{ discountLabel(voucher) }}
                                    </p>

                                    <p
                                        class="mt-0.5 text-[10px] capitalize text-[#94A3B8]"
                                    >
                                        {{ voucher.type }} discount
                                    </p>
                                </div>

                                <span
                                    class="shrink-0 rounded-full border px-2.5 py-1 text-[10px] font-bold"
                                    :class="statusClass(voucher)"
                                >
                                    {{ statusLabel(voucher) }}
                                </span>
                            </div>

                            <!-- Details -->
                            <div
                                class="mt-4 grid grid-cols-2 gap-3"
                            >
                                <div>
                                    <p
                                        class="text-[10px] font-bold uppercase tracking-wide text-[#94A3B8]"
                                    >
                                        Minimum Spend
                                    </p>

                                    <p
                                        class="mt-1 text-sm font-semibold text-[#1F2937]"
                                    >
                                        {{
                                            voucher.min_spend
                                                ? money(voucher.min_spend)
                                                : 'No minimum'
                                        }}
                                    </p>
                                </div>

                                <div>
                                    <p
                                        class="text-[10px] font-bold uppercase tracking-wide text-[#94A3B8]"
                                    >
                                        Usage
                                    </p>

                                    <p
                                        class="mt-1 text-sm font-semibold text-[#1F2937]"
                                    >
                                        {{ voucher.used_count ?? 0 }}

                                        <span
                                            v-if="voucher.usage_limit"
                                            class="font-normal text-[#94A3B8]"
                                        >
                                            /
                                            {{ voucher.usage_limit }}
                                        </span>
                                    </p>
                                </div>

                                <div class="col-span-2">
                                    <p
                                        class="text-[10px] font-bold uppercase tracking-wide text-[#94A3B8]"
                                    >
                                        Validity
                                    </p>

                                    <p
                                        class="mt-1 text-sm font-medium text-[#1F2937]"
                                    >
                                        {{ formatDate(voucher.starts_at) }}

                                        <span class="mx-1 text-[#CBD5E1]">
                                            →
                                        </span>

                                        {{ formatDate(voucher.expires_at) }}
                                    </p>
                                </div>
                            </div>

                            <!-- Usage Progress -->
                            <div
                                v-if="voucher.usage_limit"
                                class="mt-3"
                            >
                                <div
                                    class="flex items-center justify-between text-[10px]"
                                >
                                    <span
                                        class="font-semibold text-[#64748B]"
                                    >
                                        Usage progress
                                    </span>

                                    <span
                                        class="font-bold text-[#087F8C]"
                                    >
                                        {{ Math.round(usagePercent(voucher)) }}%
                                    </span>
                                </div>

                                <div
                                    class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-[#E8F7F6]"
                                >
                                    <div
                                        class="h-full rounded-full bg-[#16A6A0]"
                                        :style="{
                                            width: `${usagePercent(voucher)}%`,
                                        }"
                                    />
                                </div>
                            </div>

                            <!-- Actions -->
                            <div
                                class="mt-4 flex flex-wrap gap-2 border-t border-[#F1F5F9] pt-3"
                            >
                                <button
                                    type="button"
                                    @click="toggleVoucher(voucher)"
                                    class="rounded-lg border px-3 py-2 text-[10px] font-bold transition"
                                    :class="
                                        voucher.is_active
                                            ? 'border-[#FDE68A] bg-[#FFF8E7] text-[#A66A00]'
                                            : 'border-[#BBE7D3] bg-[#ECFDF5] text-[#16845A]'
                                    "
                                >
                                    {{
                                        voucher.is_active
                                            ? 'Deactivate'
                                            : 'Activate'
                                    }}
                                </button>

                                <button
                                    type="button"
                                    @click="openEditModal(voucher)"
                                    class="rounded-lg border border-[#E5E7EB] bg-white px-3 py-2 text-[10px] font-bold text-[#64748B]"
                                >
                                    Edit
                                </button>

                                <button
                                    type="button"
                                    @click="deleteVoucher(voucher)"
                                    class="rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-3 py-2 text-[10px] font-bold text-[#DC2626]"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Mobile Empty -->
                    <div
                        v-else
                        class="rounded-2xl border border-[#E5E7EB] bg-white px-6 py-12 text-center shadow-sm"
                    >
                        <div
                            class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-[#E8F7F6] text-[#087F8C]"
                        >
                            <svg
                                class="h-6 w-6"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.7"
                                    d="M15 5l4 4m0 0l-9 9H6v-4l9-9zm-1-1l2-2 4 4-2 2"
                                />
                            </svg>
                        </div>

                        <h3
                            class="mt-3 text-sm font-bold text-[#1F2937]"
                        >
                            No vouchers yet
                        </h3>

                        <p
                            class="mt-1 text-xs text-[#94A3B8]"
                        >
                            Create your first voucher to offer discounts to customers.
                        </p>

                        <button
                            type="button"
                            @click="openCreateModal"
                            class="mt-4 h-9 rounded-lg bg-[#087F8C] px-4 text-xs font-bold text-white"
                        >
                            Create Voucher
                        </button>
                    </div>
                </section>
            </div>
        </div>

        <!-- CREATE / EDIT MODAL -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-[100] flex items-center justify-center overflow-y-auto bg-[#1F2937]/60 p-4 backdrop-blur-[2px]"
            @click.self="closeModal"
        >
            <div
                class="w-full max-w-2xl overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-2xl"
            >
                <!-- MODAL HEADER -->
                <div
                    class="flex items-center justify-between border-b border-[#E5E7EB] bg-white px-5 py-4"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                        >
                            <svg
                                class="h-4.5 w-4.5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.8"
                                    d="M15 5l4 4m0 0l-9 9H6v-4l9-9zm-1-1l2-2 4 4-2 2"
                                />
                            </svg>
                        </div>

                        <div>
                            <h2
                                class="text-base font-bold text-[#1F2937]"
                            >
                                {{
                                    editingVoucher
                                        ? 'Edit Voucher'
                                        : 'Create Voucher'
                                }}
                            </h2>

                            <p
                                class="mt-0.5 text-xs text-[#94A3B8]"
                            >
                                {{
                                    editingVoucher
                                        ? 'Update your voucher details.'
                                        : 'Create a new discount voucher.'
                                }}
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="closeModal"
                        class="rounded-lg p-2 text-[#94A3B8] transition hover:bg-[#F8FAF9] hover:text-[#1F2937]"
                    >
                        <svg
                            class="h-5 w-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>

                <!-- FORM -->
                <form @submit.prevent="submit">
                    <div
                        class="max-h-[70vh] space-y-4 overflow-y-auto px-5 py-5 sm:px-6"
                    >
                        <!-- CODE -->
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-bold text-[#1F2937]"
                            >
                                Voucher Code
                            </label>

                            <input
                                v-model="form.code"
                                type="text"
                                maxlength="30"
                                placeholder="e.g. SAVE20"
                                class="h-10 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 text-sm uppercase text-[#1F2937] outline-none transition placeholder:normal-case placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                            />

                            <p
                                class="mt-1 text-[10px] text-[#94A3B8]"
                            >
                                Letters, numbers, hyphens, and underscores only.
                            </p>

                            <p
                                v-if="form.errors.code"
                                class="mt-1 text-xs font-medium text-[#DC2626]"
                            >
                                {{ form.errors.code }}
                            </p>
                        </div>

                        <!-- TYPE + VALUE -->
                        <div
                            class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                        >
                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-bold text-[#1F2937]"
                                >
                                    Discount Type
                                </label>

                                <select
                                    v-model="form.type"
                                    class="h-10 w-full rounded-xl border border-[#E5E7EB] bg-white px-3.5 text-sm text-[#64748B] outline-none transition focus:border-[#087F8C] focus:ring-2 focus:ring-[#E8F7F6]"
                                >
                                    <option value="percentage">
                                        Percentage
                                    </option>

                                    <option value="fixed">
                                        Fixed Amount
                                    </option>
                                </select>

                                <p
                                    v-if="form.errors.type"
                                    class="mt-1 text-xs font-medium text-[#DC2626]"
                                >
                                    {{ form.errors.type }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-bold text-[#1F2937]"
                                >
                                    Discount Value
                                </label>

                                <div class="relative">
                                    <span
                                        v-if="form.type === 'fixed'"
                                        class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-bold text-[#64748B]"
                                    >
                                        ₱
                                    </span>

                                    <input
                                        v-model="form.value"
                                        type="number"
                                        min="0.01"
                                        :max="
                                            form.type === 'percentage'
                                                ? 100
                                                : 99999999.99
                                        "
                                        step="0.01"
                                        :placeholder="
                                            form.type === 'percentage'
                                                ? '20'
                                                : '100'
                                        "
                                        class="h-10 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                        :class="
                                            form.type === 'fixed'
                                                ? 'pl-8 pr-10'
                                                : 'pl-3.5 pr-9'
                                        "
                                    />

                                    <span
                                        v-if="form.type === 'percentage'"
                                        class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-sm font-bold text-[#64748B]"
                                    >
                                        %
                                    </span>
                                </div>

                                <p
                                    v-if="form.type === 'percentage'"
                                    class="mt-1 text-[10px] text-[#94A3B8]"
                                >
                                    Maximum discount is 100%.
                                </p>

                                <p
                                    v-if="form.errors.value"
                                    class="mt-1 text-xs font-medium text-[#DC2626]"
                                >
                                    {{ form.errors.value }}
                                </p>
                            </div>
                        </div>

                        <!-- MINIMUM + USAGE -->
                        <div
                            class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                        >
                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-bold text-[#1F2937]"
                                >
                                    Minimum Spend
                                </label>

                                <div class="relative">
                                    <span
                                        class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-bold text-[#64748B]"
                                    >
                                        ₱
                                    </span>

                                    <input
                                        v-model="form.min_spend"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="0"
                                        class="h-10 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] py-2 pl-8 pr-3.5 text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                    />
                                </div>

                                <p
                                    class="mt-1 text-[10px] text-[#94A3B8]"
                                >
                                    Leave at 0 for no minimum purchase.
                                </p>

                                <p
                                    v-if="form.errors.min_spend"
                                    class="mt-1 text-xs font-medium text-[#DC2626]"
                                >
                                    {{ form.errors.min_spend }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-bold text-[#1F2937]"
                                >
                                    Usage Limit
                                </label>

                                <input
                                    v-model="form.usage_limit"
                                    type="number"
                                    min="1"
                                    step="1"
                                    placeholder="Unlimited"
                                    class="h-10 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                />

                                <p
                                    class="mt-1 text-[10px] text-[#94A3B8]"
                                >
                                    Leave blank for unlimited uses.
                                </p>

                                <p
                                    v-if="form.errors.usage_limit"
                                    class="mt-1 text-xs font-medium text-[#DC2626]"
                                >
                                    {{ form.errors.usage_limit }}
                                </p>
                            </div>
                        </div>

                        <!-- DATES -->
                        <div
                            class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                        >
                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-bold text-[#1F2937]"
                                >
                                    Start Date
                                </label>

                                <input
                                    v-model="form.starts_at"
                                    type="date"
                                    class="h-10 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 text-sm text-[#64748B] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                />

                                <p
                                    v-if="form.errors.starts_at"
                                    class="mt-1 text-xs font-medium text-[#DC2626]"
                                >
                                    {{ form.errors.starts_at }}
                                </p>
                            </div>

                            <div>
                                <label
                                    class="mb-1.5 block text-xs font-bold text-[#1F2937]"
                                >
                                    Expiry Date
                                </label>

                                <input
                                    v-model="form.expires_at"
                                    type="date"
                                    class="h-10 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 text-sm text-[#64748B] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                />

                                <p
                                    v-if="form.errors.expires_at"
                                    class="mt-1 text-xs font-medium text-[#DC2626]"
                                >
                                    {{ form.errors.expires_at }}
                                </p>
                            </div>
                        </div>

                        <!-- ACTIVE -->
                        <div
                            class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-3.5"
                        >
                            <label
                                class="flex cursor-pointer items-center justify-between gap-4"
                            >
                                <div>
                                    <p
                                        class="text-xs font-bold text-[#1F2937]"
                                    >
                                        Active Voucher
                                    </p>

                                    <p
                                        class="mt-0.5 text-[10px] text-[#64748B]"
                                    >
                                        Customers can use this voucher when active.
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="form.is_active"
                                    @click="
                                        form.is_active =
                                            !form.is_active
                                    "
                                    class="relative h-6 w-11 shrink-0 rounded-full transition"
                                    :class="
                                        form.is_active
                                            ? 'bg-[#087F8C]'
                                            : 'bg-[#CBD5E1]'
                                    "
                                >
                                    <span
                                        class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition"
                                        :class="
                                            form.is_active
                                                ? 'left-[22px]'
                                                : 'left-0.5'
                                        "
                                    />
                                </button>
                            </label>

                            <p
                                v-if="form.errors.is_active"
                                class="mt-2 text-xs font-medium text-[#DC2626]"
                            >
                                {{ form.errors.is_active }}
                            </p>
                        </div>
                    </div>

                    <!-- MODAL FOOTER -->
                    <div
                        class="flex flex-col-reverse gap-2 border-t border-[#E5E7EB] bg-[#F8FAF9] px-5 py-4 sm:flex-row sm:justify-end sm:px-6"
                    >
                        <button
                            type="button"
                            @click="closeModal"
                            class="h-10 rounded-xl border border-[#E5E7EB] bg-white px-5 text-sm font-bold text-[#64748B] transition hover:border-[#CBD5E1] hover:bg-[#F8FAF9]"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="h-10 rounded-xl bg-[#087F8C] px-5 text-sm font-bold text-white shadow-sm transition hover:bg-[#066B76] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{
                                form.processing
                                    ? 'Saving...'
                                    : editingVoucher
                                        ? 'Update Voucher'
                                        : 'Create Voucher'
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </SellerLayout>
</template>