<!-- Buyer account profile page with login session management (BUYER-39). -->
<script setup>
import { Head } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'
import DeleteUserForm from './Partials/DeleteUserForm.vue'
import ManageLoginSessions from './Partials/ManageLoginSessions.vue'
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue'
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue'

defineProps({
    mustVerifyEmail: {
        type: Boolean,
        default: false,
    },

    status: {
        type: String,
        default: null,
    },

    sessions: {
        type: Array,
        default: () => [],
    },

    active: {
        type: String,
        default: 'account',
    },
})
</script>

<template>
    <Head title="My Account" />

    <BuyerLayout :active="active">
        <main class="min-h-[calc(100vh-80px)] w-full min-w-0 overflow-x-hidden bg-[#F8FAF9]">
            <div class="mx-auto w-full max-w-[1536px] px-4 py-6 pb-16 sm:px-6 sm:py-8 lg:px-8 2xl:px-10 lg:py-10">

                <!-- PAGE HEADER -->
                <div class="mb-8">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-[#F4B942]"></span>
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#087F8C] sm:text-xs">
                            Buyer Account
                        </p>
                    </div>

                    <h1 class="mt-1 text-2xl font-black tracking-tight text-[#1F2937] sm:text-3xl">
                        My Account
                    </h1>

                    <p class="mt-1 text-xs leading-5 text-[#64748B] sm:text-sm">
                        Manage your personal information, contact details, delivery address, password, active sessions, and security.
                    </p>
                </div>

                <!-- PROFILE INFORMATION -->
                <section class="mb-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs">
                    <div class="border-b border-gray-100 bg-[#F8FAF9] px-5 py-4 sm:px-8">
                        <h2 class="text-base font-bold text-gray-900">
                            Personal Information
                        </h2>

                        <p class="mt-1 text-xs text-gray-500 sm:text-sm">
                            Update the information associated with your buyer account.
                        </p>
                    </div>

                    <div class="p-5 sm:p-8">
                        <UpdateProfileInformationForm
                            :must-verify-email="mustVerifyEmail"
                            :status="status"
                        />
                    </div>
                </section>

                <!-- PASSWORD -->
                <section class="mb-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs">
                    <div class="border-b border-gray-100 bg-[#F8FAF9] px-5 py-4 sm:px-8">
                        <h2 class="text-base font-bold text-gray-900">
                            Password & Security
                        </h2>

                        <p class="mt-1 text-xs text-gray-500 sm:text-sm">
                            Keep your buyer account secure by using a strong password.
                        </p>
                    </div>

                    <div class="p-5 sm:p-8">
                        <UpdatePasswordForm />
                    </div>
                </section>

                <!-- LOGIN SESSIONS (BUYER-39) -->
                <section class="mb-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs">
                    <div class="border-b border-gray-100 bg-[#F8FAF9] px-5 py-4 sm:px-8">
                        <h2 class="text-base font-bold text-gray-900">
                            Browser Sessions & Connected Devices
                        </h2>

                        <p class="mt-1 text-xs text-gray-500 sm:text-sm">
                            View active logins and revoke access from unrecognized or unwanted devices.
                        </p>
                    </div>

                    <div class="p-5 sm:p-8">
                        <ManageLoginSessions :sessions="sessions" />
                    </div>
                </section>

                <!-- DELETE ACCOUNT -->
                <section class="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-xs">
                    <div class="border-b border-red-100 bg-red-50/70 px-5 py-4 sm:px-8">
                        <h2 class="text-base font-bold text-red-700">
                            Delete Account
                        </h2>

                        <p class="mt-1 text-xs text-red-600 sm:text-sm">
                            Permanently delete your buyer account and associated data.
                        </p>
                    </div>

                    <div class="p-5 sm:p-8">
                        <DeleteUserForm />
                    </div>
                </section>

            </div>
        </main>
    </BuyerLayout>
</template>
