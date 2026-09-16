<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'

const props = defineProps({
    settings: {
        type: Object,
        default: () => ({}),
    },
})

const form = useForm({
    shipping_fee: props.settings.shipping_fee ?? 0,
    return_policy_days: props.settings.return_policy_days ?? 30,
    bank_name: props.settings.bank_name ?? '',
    bank_account_name: props.settings.bank_account_name ?? '',
    bank_account_number: props.settings.bank_account_number ?? '',
    notify_new_order: !!props.settings.notify_new_order,
    notify_messages: !!props.settings.notify_messages,
})

const submit = () => form.patch(route('seller.settings.update'))
</script>

<template>
    <Head title="Seller Settings" />

    <SellerLayout>
        <div class="min-h-[calc(100vh-68px)] bg-[#F8FAF9] pb-20">
            <div class="mx-auto max-w-5xl px-4 py-5 sm:px-6 lg:px-8">

                <!-- Page Header -->
                <div
                    class="flex flex-col gap-4 border-b border-[#E5E7EB] pb-5 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div>
                        <p
                            class="text-xs font-bold uppercase tracking-[0.16em] text-[#087F8C]"
                        >
                            Seller Center
                        </p>

                        <h1
                            class="mt-1 text-2xl font-bold tracking-tight text-[#1F2937] sm:text-3xl"
                        >
                            Seller Settings
                        </h1>

                        <p class="mt-1 text-sm text-[#64748B]">
                            Manage your store preferences, payout details, and notifications.
                        </p>
                    </div>

                    <Link
                        :href="route('seller.dashboard')"
                        class="inline-flex w-fit items-center gap-2 rounded-xl border border-[#E5E7EB] bg-white px-4 py-2.5 text-sm font-semibold text-[#475569] shadow-sm transition hover:border-[#087F8C] hover:bg-[#E8F7F6] hover:text-[#087F8C]"
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
                                d="M10 19l-7-7 7-7"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M3 12h18"
                            />
                        </svg>

                        Back to Dashboard
                    </Link>
                </div>

                <!-- Settings Form -->
                <form
                    class="mt-5 space-y-5"
                    @submit.prevent="submit"
                >

                    <!-- Store Policies -->
                    <section
                        class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                    >
                        <div class="border-b border-[#E5E7EB] px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
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
                                            d="M12 8c-2.21 0-4 1.12-4 2.5S9.79 13 12 13s4 1.12 4 2.5S14.21 18 12 18m0-13v2m0 13v2"
                                        />
                                        <circle
                                            cx="12"
                                            cy="12"
                                            r="9"
                                            stroke-width="1.7"
                                        />
                                    </svg>
                                </div>

                                <div>
                                    <h2 class="text-base font-bold text-[#1F2937]">
                                        Store Policies
                                    </h2>

                                    <p class="mt-0.5 text-xs text-[#64748B]">
                                        Configure your shipping and return policy.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-4 p-5 sm:grid-cols-2">
                            <!-- Shipping Fee -->
                            <div>
                                <label
                                    for="shipping_fee"
                                    class="block text-xs font-semibold text-[#475569]"
                                >
                                    Shipping fee
                                </label>

                                <div class="relative mt-1.5">
                                    <span
                                        class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm font-semibold text-[#64748B]"
                                    >
                                        ₱
                                    </span>

                                    <input
                                        id="shipping_fee"
                                        v-model.number="form.shipping_fee"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        class="w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] py-2.5 pl-8 pr-3 text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                    />
                                </div>

                                <p class="mt-1 text-[10px] text-[#94A3B8]">
                                    Default shipping charge for orders.
                                </p>

                                <p
                                    v-if="form.errors.shipping_fee"
                                    class="mt-1 text-[10px] font-medium text-[#E85D5D]"
                                >
                                    {{ form.errors.shipping_fee }}
                                </p>
                            </div>

                            <!-- Return Policy -->
                            <div>
                                <label
                                    for="return_policy_days"
                                    class="block text-xs font-semibold text-[#475569]"
                                >
                                    Return policy
                                </label>

                                <div class="relative mt-1.5">
                                    <input
                                        id="return_policy_days"
                                        v-model.number="form.return_policy_days"
                                        type="number"
                                        min="0"
                                        max="365"
                                        class="w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2.5 pr-16 text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                    />

                                    <span
                                        class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-[#94A3B8]"
                                    >
                                        days
                                    </span>
                                </div>

                                <p class="mt-1 text-[10px] text-[#94A3B8]">
                                    Number of days customers can request a return.
                                </p>

                                <p
                                    v-if="form.errors.return_policy_days"
                                    class="mt-1 text-[10px] font-medium text-[#E85D5D]"
                                >
                                    {{ form.errors.return_policy_days }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- Payout Details -->
                    <section
                        class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                    >
                        <div class="border-b border-[#E5E7EB] px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#FFF8E7] text-[#B77900]"
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
                                            d="M3 10h18M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"
                                        />
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="1.7"
                                            d="M7 15h3"
                                        />
                                    </svg>
                                </div>

                                <div>
                                    <h2 class="text-base font-bold text-[#1F2937]">
                                        Payout Details
                                    </h2>

                                    <p class="mt-0.5 text-xs text-[#64748B]">
                                        Keep your bank information up to date for payouts.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-4 p-5 sm:grid-cols-2">
                            <!-- Bank Name -->
                            <div>
                                <label
                                    for="bank_name"
                                    class="block text-xs font-semibold text-[#475569]"
                                >
                                    Bank name
                                </label>

                                <input
                                    id="bank_name"
                                    v-model="form.bank_name"
                                    type="text"
                                    placeholder="Enter bank name"
                                    class="mt-1.5 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2.5 text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                />

                                <p
                                    v-if="form.errors.bank_name"
                                    class="mt-1 text-[10px] font-medium text-[#E85D5D]"
                                >
                                    {{ form.errors.bank_name }}
                                </p>
                            </div>

                            <!-- Account Name -->
                            <div>
                                <label
                                    for="bank_account_name"
                                    class="block text-xs font-semibold text-[#475569]"
                                >
                                    Bank account name
                                </label>

                                <input
                                    id="bank_account_name"
                                    v-model="form.bank_account_name"
                                    type="text"
                                    placeholder="Account holder name"
                                    class="mt-1.5 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2.5 text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                />

                                <p
                                    v-if="form.errors.bank_account_name"
                                    class="mt-1 text-[10px] font-medium text-[#E85D5D]"
                                >
                                    {{ form.errors.bank_account_name }}
                                </p>
                            </div>

                            <!-- Account Number -->
                            <div class="sm:col-span-2">
                                <label
                                    for="bank_account_number"
                                    class="block text-xs font-semibold text-[#475569]"
                                >
                                    Bank account number
                                </label>

                                <input
                                    id="bank_account_number"
                                    v-model="form.bank_account_number"
                                    type="text"
                                    inputmode="numeric"
                                    placeholder="Enter bank account number"
                                    class="mt-1.5 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-2.5 text-sm tracking-wide text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                />

                                <p
                                    v-if="form.errors.bank_account_number"
                                    class="mt-1 text-[10px] font-medium text-[#E85D5D]"
                                >
                                    {{ form.errors.bank_account_number }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- Notifications -->
                    <section
                        class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                    >
                        <div class="border-b border-[#E5E7EB] px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
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
                                            d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1"
                                        />
                                    </svg>
                                </div>

                                <div>
                                    <h2 class="text-base font-bold text-[#1F2937]">
                                        Notifications
                                    </h2>

                                    <p class="mt-0.5 text-xs text-[#64748B]">
                                        Choose which seller activity you want to be notified about.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2 p-5">
                            <!-- New Order -->
                            <label
                                class="flex cursor-pointer items-center justify-between gap-4 rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-3.5 transition hover:border-[#B9E4E1] hover:bg-[#E8F7F6]"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-[#087F8C]"
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
                                                stroke-width="1.7"
                                                d="M3 7h18M5 7l1 13h12l1-13M8 7V5a4 4 0 018 0v2"
                                            />
                                        </svg>
                                    </div>

                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold text-[#1F2937]">
                                            New order notifications
                                        </p>

                                        <p class="mt-0.5 text-[10px] text-[#64748B]">
                                            Notify me when a new order comes in.
                                        </p>
                                    </div>
                                </div>

                                <input
                                    v-model="form.notify_new_order"
                                    type="checkbox"
                                    class="h-4 w-4 shrink-0 rounded border-[#CBD5E1] text-[#087F8C] accent-[#087F8C] focus:ring-2 focus:ring-[#E8F7F6]"
                                />
                            </label>

                            <!-- Messages -->
                            <label
                                class="flex cursor-pointer items-center justify-between gap-4 rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-3.5 transition hover:border-[#B9E4E1] hover:bg-[#E8F7F6]"
                            >
                                <div class="flex min-w-0 items-center gap-3">
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-[#087F8C]"
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
                                                stroke-width="1.7"
                                                d="M8 10h8M8 14h5m7-2a8 8 0 01-8 8 8.4 8.4 0 01-3.1-.6L4 21l1.6-3.8A8 8 0 1120 12z"
                                            />
                                        </svg>
                                    </div>

                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold text-[#1F2937]">
                                            Customer message notifications
                                        </p>

                                        <p class="mt-0.5 text-[10px] text-[#64748B]">
                                            Notify me when there are new customer messages.
                                        </p>
                                    </div>
                                </div>

                                <input
                                    v-model="form.notify_messages"
                                    type="checkbox"
                                    class="h-4 w-4 shrink-0 rounded border-[#CBD5E1] text-[#087F8C] accent-[#087F8C] focus:ring-2 focus:ring-[#E8F7F6]"
                                />
                            </label>
                        </div>
                    </section>

                    <!-- Save Bar -->
                    <div
                        class="flex flex-col-reverse gap-3 rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p class="text-xs font-semibold text-[#475569]">
                                Store settings
                            </p>

                            <p class="mt-0.5 text-[10px] text-[#94A3B8]">
                                Changes will be applied to your seller account.
                            </p>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#087F8C] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#066D78] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <svg
                                v-if="!form.processing"
                                class="h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.8"
                                    d="M5 12.5l4 4L19 6.5"
                                />
                            </svg>

                            <svg
                                v-else
                                class="h-4 w-4 animate-spin"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 3v2m6.364.636l-1.414 1.414M21 12h-2m-.636 6.364l-1.414-1.414M12 21v-2m-6.364-.636l1.414-1.414M3 12h2m.636-6.364l1.414 1.414"
                                />
                            </svg>

                            {{ form.processing ? 'Saving...' : 'Save settings' }}
                        </button>
                    </div>

                </form>

                <!-- Footer Clearance -->
                <div class="h-2"></div>
            </div>
        </div>
    </SellerLayout>
</template>