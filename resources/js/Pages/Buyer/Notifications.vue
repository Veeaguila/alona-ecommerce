<!-- Buyer notification inbox with read-state actions. -->
<script setup>
import { useForm } from '@inertiajs/vue3'
import { Head } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'

defineProps({
    notifications: {
        type: Array,
        default: () => [],
    },
})

const read = item =>
    useForm({}).patch(
        route('buyer.notifications.read', item.id),
        {
            preserveScroll: true,
        }
    )

const readAll = () =>
    useForm({}).post(
        route('buyer.notifications.read-all'),
        {
            preserveScroll: true,
        }
    )
</script>

<template>
    <Head title="Notifications" />

    <BuyerLayout>
        <main class="min-h-[calc(100vh-80px)] w-full min-w-0 overflow-x-hidden bg-[#F8FAF9]">
            <div class="mx-auto w-full max-w-4xl px-4 py-6 pb-12 sm:px-6 sm:py-7 lg:px-8 lg:py-8 lg:pb-14">

                <!-- HEADER -->
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#F4B942]"></span>
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#087F8C] sm:text-xs">
                                Recent activity
                            </p>
                        </div>

                        <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-[#1F2937] sm:text-3xl">
                            Notifications
                        </h1>

                        <p class="mt-1 text-xs leading-5 text-[#64748B] sm:text-sm">
                            Stay updated with your orders, account, and Alona activity.
                        </p>
                    </div>

                    <button
                        v-if="notifications.length"
                        type="button"
                        class="inline-flex w-fit items-center gap-1.5 rounded-lg px-2.5 py-2 text-xs font-bold text-[#087F8C] transition hover:bg-[#E8F7F6]"
                        @click="readAll"
                    >
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M5 13l4 4L19 7"
                            />
                        </svg>
                        Mark all read
                    </button>
                </div>

                <!-- NOTIFICATIONS -->
                <section
                    class="mt-7 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-[0_8px_30px_rgba(15,23,42,0.05)] sm:mt-8"
                >
                    <div
                        v-if="notifications.length"
                        class="divide-y divide-[#EEF1F2]"
                    >
                        <button
                            v-for="item in notifications"
                            :key="item.id"
                            type="button"
                            class="relative block w-full p-4 text-left transition sm:p-5"
                            :class="
                                !item.read_at
                                    ? 'bg-[#F3FBFA] hover:bg-[#EAF8F7]'
                                    : 'bg-white hover:bg-[#F8FAF9]'
                            "
                            @click="read(item)"
                        >
                            <!-- UNREAD INDICATOR -->
                            <span
                                v-if="!item.read_at"
                                class="absolute inset-y-0 left-0 w-1 bg-[#087F8C]"
                            ></span>

                            <div class="flex min-w-0 gap-3 sm:gap-4">
                                <!-- ICON -->
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-base sm:h-11 sm:w-11"
                                    :class="
                                        !item.read_at
                                            ? 'bg-[#E8F7F6] text-[#087F8C]'
                                            : 'bg-[#F8FAF9] text-[#94A3B8]'
                                    "
                                >
                                    <svg
                                        class="h-5 w-5"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.7"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M15 17H9m10-1V11a7 7 0 10-14 0v5l-2 2h18l-2-2z"
                                        />
                                    </svg>
                                </div>

                                <!-- CONTENT -->
                                <div class="min-w-0 flex-1">
                                    <div class="flex min-w-0 items-start justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex min-w-0 items-center gap-2">
                                                <span
                                                    v-if="!item.read_at"
                                                    class="h-1.5 w-1.5 shrink-0 rounded-full bg-[#087F8C]"
                                                ></span>

                                                <p
                                                    class="line-clamp-2 text-xs font-extrabold leading-5 sm:text-sm"
                                                    :class="
                                                        !item.read_at
                                                            ? 'text-[#1F2937]'
                                                            : 'text-[#475569]'
                                                    "
                                                >
                                                    {{ item.title }}
                                                </p>
                                            </div>

                                            <p class="mt-1.5 line-clamp-3 text-[11px] leading-5 text-[#64748B] sm:text-xs">
                                                {{ item.message }}
                                            </p>
                                        </div>

                                        <span
                                            v-if="!item.read_at"
                                            class="shrink-0 rounded-full bg-[#E8F7F6] px-2 py-1 text-[9px] font-bold text-[#087F8C]"
                                        >
                                            New
                                        </span>
                                    </div>

                                    <div class="mt-2.5 flex items-center gap-2">
                                        <span
                                            class="h-1.5 w-1.5 rounded-full"
                                            :class="
                                                !item.read_at
                                                    ? 'bg-[#F4B942]'
                                                    : 'bg-[#CBD5E1]'
                                            "
                                        ></span>

                                        <span class="text-[9px] font-medium text-[#94A3B8]">
                                            {{ item.read_at ? 'Read' : 'Unread' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </button>
                    </div>

                    <!-- EMPTY -->
                    <div
                        v-else
                        class="px-5 py-16 text-center sm:px-6 sm:py-20"
                    >
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-[#E8F7F6] text-[#087F8C]">
                            <svg
                                class="h-7 w-7"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.6"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15 17H9m10-1V11a7 7 0 10-14 0v5l-2 2h18l-2-2z"
                                />
                            </svg>
                        </div>

                        <h2 class="mt-4 text-sm font-extrabold text-[#1F2937]">
                            No notifications yet
                        </h2>

                        <p class="mx-auto mt-1.5 max-w-md text-xs leading-5 text-[#64748B]">
                            We'll let you know when there is something new about your Alona account or orders.
                        </p>
                    </div>
                </section>

                <!-- FOOTER NOTE -->
                <div
                    v-if="notifications.length"
                    class="mt-4 flex items-center justify-center gap-2 text-[10px] text-[#94A3B8]"
                >
                    <span class="h-1.5 w-1.5 rounded-full bg-[#F4B942]"></span>
                    Tap a notification to mark it as read.
                </div>
            </div>
        </main>
    </BuyerLayout>
</template>
