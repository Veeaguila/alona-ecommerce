<!-- Marketplace-wide admin overview with metrics and activity. -->
<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    applications: { type: Array, default: () => [] },
    orders: { type: Array, default: () => [] },
    notifications: { type: Array, default: () => [] },
})

/*
|--------------------------------------------------------------------------
| SUMMARY CARDS
|--------------------------------------------------------------------------
*/

const summaryCards = computed(() => [
    {
        label: 'Total Buyers',
        value: Number(props.stats.buyers ?? 0).toLocaleString(),
        note: 'Registered buyers',
        icon: 'users',
        accent: 'teal',
    },
    {
        label: 'Total Sellers',
        value: Number(props.stats.sellers ?? 0).toLocaleString(),
        note: 'Registered sellers',
        icon: 'store',
        accent: 'gold',
    },
    {
        label: 'Orders',
        value: Number(props.stats.orders ?? 0).toLocaleString(),
        note: 'Marketplace orders',
        icon: 'orders',
        accent: 'teal',
    },
    {
        label: 'Gross Sales',
        value: `₱${Number(props.stats.gross_sales ?? 0).toLocaleString()}`,
        note: 'Total marketplace sales',
        icon: 'sales',
        accent: 'gold',
    },
    {
        label: 'Commission',
        value: `₱${Number(props.stats.platform_commission ?? 0).toLocaleString()}`,
        note: '10% platform commission',
        icon: 'commission',
        accent: 'teal',
    },
    {
        label: 'Pending Review',
        value: Number(props.stats.pending_registrations ?? 0).toLocaleString(),
        note: 'Applications awaiting review',
        icon: 'review',
        accent: 'warning',
    },
])

/*
|--------------------------------------------------------------------------
| RECENT ORDERS
|--------------------------------------------------------------------------
*/

const recentOrders = computed(() =>
    props.orders?.map((order) => ({
        id: order.order_number ?? `ORD-${order.id}`,
        customer: order.user?.name ?? 'Customer',
        amount: `₱${Number(order.total ?? 0).toLocaleString()}`,
        status: order.status ?? 'pending',
    })) ?? []
)

/*
|--------------------------------------------------------------------------
| RECENT APPLICATIONS
|--------------------------------------------------------------------------
*/

const recentApplications = computed(() =>
    props.applications?.map((application) => ({
        name: application.name || application.email,
        role: application.usertype,
        created_at: application.created_at
            ? new Date(application.created_at).toLocaleDateString()
            : 'Recently',
    })) ?? []
)

/*
|--------------------------------------------------------------------------
| STATUS HELPERS
|--------------------------------------------------------------------------
*/

const statusClass = (status) => {
    const normalized = String(status || '').toLowerCase()

    if (
        ['completed', 'delivered', 'approved', 'paid', 'resolved'].includes(
            normalized,
        )
    ) {
        return 'bg-emerald-50 text-emerald-700 ring-emerald-600/10'
    }

    if (
        ['cancelled', 'canceled', 'rejected', 'failed'].includes(
            normalized,
        )
    ) {
        return 'bg-red-50 text-red-700 ring-red-600/10'
    }

    if (
        ['processing', 'shipped', 'confirmed'].includes(
            normalized,
        )
    ) {
        return 'bg-blue-50 text-blue-700 ring-blue-600/10'
    }

    return 'bg-amber-50 text-amber-700 ring-amber-600/10'
}

const formatStatus = (status) => {
    return String(status || 'pending')
        .replace(/[_-]/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase())
}
</script>

<template>
    <Head title="Admin Dashboard" />

    <AdminLayout active="dashboard">
        <div class="mx-auto max-w-7xl">

            <!-- ========================================================
                 PAGE HEADER
            ========================================================= -->

            <div
                class="flex flex-col gap-4 border-b border-[#E5E7EB] pb-5 lg:flex-row lg:items-end lg:justify-between"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <span
                            class="h-1.5 w-1.5 rounded-full bg-[#087F8C]"
                        />

                        <p
                            class="text-xs font-bold uppercase tracking-[0.14em] text-[#087F8C]"
                        >
                            Platform overview
                        </p>
                    </div>

                    <h1
                        class="mt-2 text-2xl font-bold tracking-tight text-[#1F2937] sm:text-3xl"
                    >
                        Admin Dashboard
                    </h1>

                    <p
                        class="mt-1.5 max-w-2xl text-sm leading-5 text-[#64748B]"
                    >
                        Monitor marketplace performance, approvals, orders,
                        and platform activity.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="route('admin.announcements')"
                        class="inline-flex items-center gap-2 rounded-lg border border-[#E5E7EB] bg-white px-3.5 py-2.5 text-xs font-semibold text-[#64748B] shadow-sm transition hover:border-[#087F8C]/30 hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                    >
                        <svg
                            class="h-3.5 w-3.5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                d="M3 11v2a2 2 0 0 0 2 2h2l7 4V5l-7 4H5a2 2 0 0 0-2 2Z"
                            />
                            <path d="M17 9a4 4 0 0 1 0 6" />
                        </svg>

                        New announcement
                    </Link>

                    <Link
                        :href="route('admin.applications')"
                        class="inline-flex items-center gap-2 rounded-lg bg-[#087F8C] px-3.5 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#066B76]"
                    >
                        Review applications

                        <span
                            class="rounded-md bg-white/15 px-1.5 py-0.5 text-[10px] font-bold text-white"
                        >
                            {{ props.applications.length }}
                        </span>

                        <svg
                            class="h-3 w-3 text-white/70"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path d="m9 18 6-6-6-6" />
                        </svg>
                    </Link>
                </div>
            </div>

            <!-- ========================================================
                 SUMMARY CARDS
            ========================================================= -->

            <div
                class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6"
            >
                <div
                    v-for="item in summaryCards"
                    :key="item.label"
                    class="group relative overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="flex items-start justify-between gap-3">
                        <p
                            class="text-xs font-semibold text-[#64748B]"
                        >
                            {{ item.label }}
                        </p>

                        <span
                            :class="[
                                'flex h-8 w-8 shrink-0 items-center justify-center rounded-xl',
                                item.accent === 'teal'
                                    ? 'bg-[#E8F7F6] text-[#087F8C]'
                                    : item.accent === 'gold'
                                      ? 'bg-[#FFF8E7] text-[#C58A08]'
                                      : 'bg-amber-50 text-amber-600',
                            ]"
                        >
                            <!-- Users -->

                            <svg
                                v-if="item.icon === 'users'"
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"
                                />
                                <circle cx="9" cy="7" r="4" />
                                <path
                                    d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"
                                />
                            </svg>

                            <!-- Store -->

                            <svg
                                v-else-if="item.icon === 'store'"
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    d="M3 10h18M5 10v10h14V10M4 10l1-6h14l1 6"
                                />
                                <path d="M9 20v-5h6v5" />
                            </svg>

                            <!-- Orders -->

                            <svg
                                v-else-if="item.icon === 'orders'"
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    d="M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"
                                />
                                <path d="M8 7h8M8 11h8M8 15h5" />
                            </svg>

                            <!-- Sales -->

                            <svg
                                v-else-if="item.icon === 'sales'"
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path d="M4 19V5M4 19h17" />
                                <path d="m7 15 3-4 3 2 5-7" />
                            </svg>

                            <!-- Commission -->

                            <svg
                                v-else-if="item.icon === 'commission'"
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <circle cx="12" cy="12" r="9" />
                                <path d="M8 12h8M12 8v8" />
                            </svg>

                            <!-- Review -->

                            <svg
                                v-else
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path d="M4 4h16v16H4zM8 9h8M8 13h5" />
                            </svg>
                        </span>
                    </div>

                    <p
                        class="mt-4 truncate text-xl font-bold tracking-tight text-[#1F2937] sm:text-2xl"
                    >
                        {{ item.value }}
                    </p>

                    <p
                        class="mt-1.5 truncate text-[11px] font-medium text-[#94A3B8]"
                    >
                        {{ item.note }}
                    </p>

                    <div
                        :class="[
                            'absolute bottom-0 left-0 h-0.5 w-full origin-left scale-x-0 transition-transform duration-200 group-hover:scale-x-100',
                            item.accent === 'gold'
                                ? 'bg-[#F4B942]'
                                : 'bg-[#087F8C]',
                        ]"
                    />
                </div>
            </div>

            <!-- ========================================================
                 ORDERS + ALERTS
            ========================================================= -->

            <div
                class="mt-5 grid gap-5 xl:grid-cols-[1.45fr_0.85fr]"
            >

                <!-- RECENT ORDERS -->

                <section
                    class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                >
                    <div
                        class="flex items-center justify-between border-b border-[#E5E7EB] px-5 py-4"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                            >
                                <svg
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        d="M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"
                                    />
                                    <path d="M8 7h8M8 11h8M8 15h5" />
                                </svg>
                            </span>

                            <div>
                                <h2
                                    class="text-base font-bold text-[#1F2937]"
                                >
                                    Recent orders
                                </h2>

                                <p
                                    class="mt-0.5 text-xs text-[#94A3B8]"
                                >
                                    Latest marketplace activity
                                </p>
                            </div>
                        </div>

                        <Link
                            :href="route('admin.reports.sales-summary')"
                            class="text-xs font-bold text-[#087F8C] transition hover:text-[#066B76]"
                        >
                            View report →
                        </Link>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[560px] text-left">
                            <thead
                                class="border-b border-[#E5E7EB] bg-[#F8FAF9]"
                            >
                                <tr>
                                    <th
                                        class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                    >
                                        Order
                                    </th>

                                    <th
                                        class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                    >
                                        Buyer
                                    </th>

                                    <th
                                        class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                    >
                                        Amount
                                    </th>

                                    <th
                                        class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                    >
                                        Status
                                    </th>
                                </tr>
                            </thead>

                            <tbody
                                v-if="recentOrders.length"
                                class="divide-y divide-[#E5E7EB]"
                            >
                                <tr
                                    v-for="order in recentOrders"
                                    :key="order.id"
                                    class="transition hover:bg-[#F8FAF9]"
                                >
                                    <td class="px-5 py-3.5">
                                        <span
                                            class="text-xs font-bold text-[#1F2937]"
                                        >
                                            {{ order.id }}
                                        </span>
                                    </td>

                                    <td class="px-5 py-3.5">
                                        <span
                                            class="text-xs font-medium text-[#64748B]"
                                        >
                                            {{ order.customer }}
                                        </span>
                                    </td>

                                    <td class="px-5 py-3.5">
                                        <span
                                            class="text-xs font-bold text-[#1F2937]"
                                        >
                                            {{ order.amount }}
                                        </span>
                                    </td>

                                    <td class="px-5 py-3.5">
                                        <span
                                            :class="[
                                                'inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide ring-1 ring-inset',
                                                statusClass(order.status),
                                            ]"
                                        >
                                            {{ formatStatus(order.status) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>

                            <tbody v-else>
                                <tr>
                                    <td
                                        colspan="4"
                                        class="px-5 py-12 text-center"
                                    >
                                        <div
                                            class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-[#F8FAF9] text-[#94A3B8]"
                                        >
                                            <svg
                                                class="h-4 w-4"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <path
                                                    d="M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"
                                                />
                                            </svg>
                                        </div>

                                        <p
                                            class="mt-2 text-xs font-semibold text-[#64748B]"
                                        >
                                            No recent orders
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- PENDING ALERTS -->

                <section
                    class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm sm:p-5"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#FFF8E7] text-[#C58A08]"
                            >
                                <svg
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        d="M10.3 3.9 2.2 18a2 2 0 0 0 1.7 3h16.2a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"
                                    />
                                    <path d="M12 9v4M12 17h.01" />
                                </svg>
                            </span>

                            <div>
                                <h2
                                    class="text-base font-bold text-[#1F2937]"
                                >
                                    Pending alerts
                                </h2>

                                <p
                                    class="mt-0.5 text-xs text-[#94A3B8]"
                                >
                                    Items requiring attention
                                </p>
                            </div>
                        </div>

                        <span
                            class="rounded-full bg-[#FFF8E7] px-2.5 py-1 text-[10px] font-bold text-[#A56F00]"
                        >
                            {{ notifications.length }}
                        </span>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div
                            v-for="notification in notifications"
                            :key="notification.title"
                            class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-3 transition hover:border-[#087F8C]/20 hover:bg-[#E8F7F6]/40"
                        >
                            <div class="flex items-start gap-2.5">
                                <span
                                    class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-[#F4B942]"
                                />

                                <div class="min-w-0">
                                    <p
                                        class="text-xs font-semibold text-[#1F2937]"
                                    >
                                        {{ notification.title }}
                                    </p>

                                    <p
                                        class="mt-1 text-[11px] leading-4 text-[#64748B]"
                                    >
                                        {{ notification.message }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="!notifications.length"
                            class="rounded-xl border border-dashed border-[#E5E7EB] py-8 text-center"
                        >
                            <p
                                class="text-xs font-semibold text-[#94A3B8]"
                            >
                                No pending alerts
                            </p>

                            <p
                                class="mt-1 text-[11px] text-[#94A3B8]"
                            >
                                Everything looks good.
                            </p>
                        </div>
                    </div>
                </section>
            </div>

            <!-- ========================================================
                 APPLICATIONS + ADMIN ACTIONS
            ========================================================= -->

            <div class="mt-5 grid gap-5 xl:grid-cols-2">

                <!-- RECENT APPLICATIONS -->

                <section
                    class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm sm:p-5"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                            >
                                <svg
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        d="M4 4h16v16H4zM8 9h8M8 13h8M8 17h5"
                                    />
                                </svg>
                            </span>

                            <div>
                                <h2
                                    class="text-base font-bold text-[#1F2937]"
                                >
                                    Recent applications
                                </h2>

                                <p
                                    class="mt-0.5 text-xs text-[#94A3B8]"
                                >
                                    Latest registration reviews
                                </p>
                            </div>
                        </div>

                        <Link
                            :href="route('admin.applications')"
                            class="text-xs font-bold text-[#087F8C] transition hover:text-[#066B76]"
                        >
                            Open →
                        </Link>
                    </div>

                    <div class="mt-4 space-y-2">
                        <div
                            v-for="application in recentApplications"
                            :key="`${application.name}-${application.created_at}`"
                            class="flex items-center justify-between gap-4 rounded-xl border border-[#E5E7EB] px-3 py-2.5 transition hover:border-[#087F8C]/20 hover:bg-[#F8FAF9]"
                        >
                            <div class="flex min-w-0 items-center gap-2.5">
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#E8F7F6] text-[10px] font-bold text-[#087F8C]"
                                >
                                    {{ application.name?.charAt(0)?.toUpperCase() }}
                                </span>

                                <div class="min-w-0">
                                    <p
                                        class="truncate text-xs font-bold text-[#1F2937]"
                                    >
                                        {{ application.name }}
                                    </p>

                                    <p
                                        class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-[#94A3B8]"
                                    >
                                        {{ application.role }}
                                    </p>
                                </div>
                            </div>

                            <span
                                class="shrink-0 text-[10px] font-medium text-[#94A3B8]"
                            >
                                {{ application.created_at }}
                            </span>
                        </div>

                        <div
                            v-if="!recentApplications.length"
                            class="rounded-xl border border-dashed border-[#E5E7EB] py-8 text-center"
                        >
                            <p
                                class="text-xs font-semibold text-[#94A3B8]"
                            >
                                No recent applications
                            </p>
                        </div>
                    </div>
                </section>

                <!-- ADMIN ACTIONS -->

                <section
                    class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm sm:p-5"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-xl bg-[#087F8C] text-white"
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
                        </span>

                        <div>
                            <h2
                                class="text-base font-bold text-[#1F2937]"
                            >
                                Admin actions
                            </h2>

                            <p
                                class="mt-0.5 text-xs text-[#94A3B8]"
                            >
                                Core administration functions
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                        <Link
                            :href="route('admin.users')"
                            class="group flex items-center justify-between rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-3 transition hover:border-[#087F8C]/25 hover:bg-white hover:shadow-sm"
                        >
                            <span
                                class="text-xs font-semibold text-[#64748B] transition group-hover:text-[#087F8C]"
                            >
                                User Accounts
                            </span>

                            <span
                                class="text-[#94A3B8] transition group-hover:translate-x-0.5 group-hover:text-[#087F8C]"
                            >
                                →
                            </span>
                        </Link>

                        <Link
                            :href="route('admin.applications')"
                            class="group flex items-center justify-between rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-3 transition hover:border-[#087F8C]/25 hover:bg-white hover:shadow-sm"
                        >
                            <span
                                class="text-xs font-semibold text-[#64748B] transition group-hover:text-[#087F8C]"
                            >
                                Registration Review
                            </span>

                            <span
                                class="text-[#94A3B8] transition group-hover:translate-x-0.5 group-hover:text-[#087F8C]"
                            >
                                →
                            </span>
                        </Link>

                        <Link
                            :href="route('admin.compliance')"
                            class="group flex items-center justify-between rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-3 transition hover:border-[#087F8C]/25 hover:bg-white hover:shadow-sm"
                        >
                            <span
                                class="text-xs font-semibold text-[#64748B] transition group-hover:text-[#087F8C]"
                            >
                                Seller Compliance
                            </span>

                            <span
                                class="text-[#94A3B8] transition group-hover:translate-x-0.5 group-hover:text-[#087F8C]"
                            >
                                →
                            </span>
                        </Link>

                        <Link
                            :href="route('admin.complaints')"
                            class="group flex items-center justify-between rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-3 transition hover:border-[#087F8C]/25 hover:bg-white hover:shadow-sm"
                        >
                            <span
                                class="text-xs font-semibold text-[#64748B] transition group-hover:text-[#087F8C]"
                            >
                                Complaints & Disputes
                            </span>

                            <span
                                class="text-[#94A3B8] transition group-hover:translate-x-0.5 group-hover:text-[#087F8C]"
                            >
                                →
                            </span>
                        </Link>

                        <Link
                            :href="route('admin.settings')"
                            class="group flex items-center justify-between rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-3 transition hover:border-[#087F8C]/25 hover:bg-white hover:shadow-sm sm:col-span-2"
                        >
                            <span
                                class="text-xs font-semibold text-[#64748B] transition group-hover:text-[#087F8C]"
                            >
                                Platform Settings
                            </span>

                            <span
                                class="text-[#94A3B8] transition group-hover:translate-x-0.5 group-hover:text-[#087F8C]"
                            >
                                →
                            </span>
                        </Link>
                    </div>
                </section>
            </div>
        </div>
    </AdminLayout>
</template>