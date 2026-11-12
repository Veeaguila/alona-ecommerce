<!-- Buyer account profile page with system-aligned Shopee layout & session management (BUYER-39). -->
<script setup>
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import BuyerAccountLayout from '@/Layouts/BuyerAccountLayout.vue'
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

const currentTab = ref('profile')

const resolveTabFromUrl = () => {
    if (typeof window === 'undefined') return
    const params = new URLSearchParams(window.location.search)
    const tab = params.get('tab')
    if (['profile', 'password', 'privacy'].includes(tab)) {
        currentTab.value = tab
    }
}

onMounted(() => {
    resolveTabFromUrl()
})

const handleSelectTab = (tab) => {
    if (['profile', 'password', 'privacy'].includes(tab)) {
        currentTab.value = tab
        if (typeof window !== 'undefined') {
            const url = new URL(window.location.href)
            url.searchParams.set('tab', tab)
            window.history.replaceState({}, '', url.toString())
        }
    }
}
</script>

<template>
    <Head title="My Account" />

    <BuyerAccountLayout
        active-menu="account"
        :active-sub="currentTab"
        @select-tab="handleSelectTab"
    >
        <div class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-xs sm:p-8">
            
            <!-- TAB 1: PROFILE -->
            <div v-if="currentTab === 'profile'">
                <div class="border-b border-[#EEF1F2] pb-5">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-[#F4B942]"></span>
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#087F8C] sm:text-xs">
                            Account Profile
                        </p>
                    </div>

                    <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-[#1F2937]">
                        My Profile
                    </h1>

                    <p class="mt-1 text-xs text-[#64748B]">
                        Manage your personal details, contact verification, and delivery profile.
                    </p>
                </div>

                <div class="pt-6">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                    />
                </div>
            </div>

            <!-- TAB 2: CHANGE PASSWORD -->
            <div v-else-if="currentTab === 'password'">
                <div class="border-b border-[#EEF1F2] pb-5">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-[#F4B942]"></span>
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#087F8C] sm:text-xs">
                            Security Credentials
                        </p>
                    </div>

                    <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-[#1F2937]">
                        Change Password
                    </h1>

                    <p class="mt-1 text-xs text-[#64748B]">
                        For your account's safety, use a strong password with letters, numbers, and symbols.
                    </p>
                </div>

                <div class="pt-6">
                    <UpdatePasswordForm />
                </div>
            </div>

            <!-- TAB 3: PRIVACY & SESSIONS -->
            <div v-else-if="currentTab === 'privacy'">
                <div class="border-b border-[#EEF1F2] pb-5">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-[#F4B942]"></span>
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#087F8C] sm:text-xs">
                            Access & Security
                        </p>
                    </div>

                    <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-[#1F2937]">
                        Privacy Settings
                    </h1>

                    <p class="mt-1 text-xs text-[#64748B]">
                        Review active browser sessions, connected devices, and account termination options.
                    </p>
                </div>

                <div class="pt-6">
                    <ManageLoginSessions :sessions="sessions" />

                    <div class="mt-8 border-t border-rose-100 bg-rose-50/50 p-5 rounded-xl border">
                        <h2 class="text-sm font-extrabold text-rose-700">
                            Delete Account
                        </h2>
                        <p class="mt-0.5 text-xs text-rose-600">
                            Permanently delete your account and all associated order data.
                        </p>
                        <div class="mt-4">
                            <DeleteUserForm />
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </BuyerAccountLayout>
</template>
