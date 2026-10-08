<script setup>
import { computed, nextTick, ref } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'

const props = defineProps({
    sessions: {
        type: Array,
        default: () => [],
    },
})

const page = usePage()
const confirmingLogoutOther = ref(false)
const passwordInput = ref(null)
const revokingSessionId = ref(null)

const form = useForm({
    password: '',
})

const confirmLogoutOther = () => {
    confirmingLogoutOther.value = true
    nextTick(() => passwordInput.value?.focus())
}

const closeLogoutOtherModal = () => {
    confirmingLogoutOther.value = false
    form.reset()
    form.clearErrors()
}

const logoutOtherBrowserSessions = () => {
    form.post(route('buyer.sessions.revoke-other'), {
        preserveScroll: true,
        onSuccess: () => closeLogoutOtherModal(),
        onError: () => passwordInput.value?.focus(),
        onFinish: () => form.reset(),
    })
}

const revokeSingleSession = sessionId => {
    if (!confirm('Are you sure you want to revoke and log out of this active session?')) {
        return
    }

    revokingSessionId.value = sessionId
    useForm({}).delete(route('buyer.sessions.revoke', sessionId), {
        preserveScroll: true,
        onFinish: () => {
            revokingSessionId.value = null
        },
    })
}

const getDeviceIcon = deviceType => {
    switch (deviceType) {
        case 'mobile':
            return '📱'
        case 'tablet':
            return '📟'
        default:
            return '🖥️'
    }
}
</script>

<template>
    <section>
        <header>
            <h2 class="text-base font-bold text-gray-900">
                Active Login Sessions
            </h2>

            <p class="mt-1 text-xs text-gray-500 sm:text-sm">
                If necessary, you may log out of all of your other browser sessions across all of your devices.
            </p>
        </header>

        <!-- SESSIONS LIST -->
        <div class="mt-6 space-y-4">
            <div
                v-for="session in sessions"
                :key="session.id"
                class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-4 transition sm:flex-row sm:items-center sm:justify-between"
                :class="session.is_current_device ? 'ring-1 ring-[#087F8C]/30 bg-[#F3FBFA]/40' : ''"
            >
                <div class="flex items-start gap-3 sm:gap-4">
                    <!-- DEVICE ICON -->
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-xl shadow-xs"
                        :class="session.is_current_device ? 'bg-[#E8F7F6] text-[#087F8C]' : 'bg-gray-100 text-gray-500'"
                    >
                        {{ getDeviceIcon(session.device_type) }}
                    </div>

                    <!-- SESSION INFO -->
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h4 class="text-xs font-bold text-gray-900 sm:text-sm">
                                {{ session.browser }} on {{ session.platform }}
                            </h4>

                            <span
                                v-if="session.is_current_device"
                                class="inline-flex items-center gap-1 rounded-full bg-[#EAF8F1] px-2.5 py-0.5 text-[10px] font-bold text-[#16A34A]"
                            >
                                <span class="h-1.5 w-1.5 rounded-full bg-[#16A34A]"></span>
                                This Device (Active Now)
                            </span>
                        </div>

                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                            <span>IP: <strong class="text-gray-700">{{ session.ip_address }}</strong></span>
                            <span>•</span>
                            <span v-if="session.is_current_device" class="text-emerald-700 font-semibold">Active now</span>
                            <span v-else :title="session.last_active_date">Last active: {{ session.last_active }}</span>
                        </div>
                    </div>
                </div>

                <!-- ACTIONS -->
                <div class="flex shrink-0 items-center sm:self-center">
                    <button
                        v-if="!session.is_current_device"
                        type="button"
                        :disabled="revokingSessionId === session.id"
                        class="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-[#FEF2F2] px-3 py-1.5 text-xs font-bold text-[#DC2626] transition hover:bg-[#FEE2E2] disabled:opacity-50"
                        @click="revokeSingleSession(session.id)"
                    >
                        {{ revokingSessionId === session.id ? 'Revoking...' : 'Revoke Session' }}
                    </button>
                    <span v-else class="text-xs font-bold text-[#087F8C]">Current Session</span>
                </div>
            </div>

            <!-- EMPTY SESSIONS FALLBACK -->
            <div
                v-if="!sessions || sessions.length === 0"
                class="rounded-xl border border-gray-100 bg-gray-50 p-6 text-center text-xs text-gray-500"
            >
                No active session records found.
            </div>
        </div>

        <!-- LOGOUT OTHER DEVICES BUTTON -->
        <div v-if="sessions && sessions.length > 1" class="mt-6 flex items-center gap-4">
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-xl bg-[#087F8C] px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#066C76]"
                @click="confirmLogoutOther"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                Log Out Other Browser Sessions
            </button>
        </div>

        <!-- LOGOUT OTHER SESSIONS MODAL -->
        <div
            v-if="confirmingLogoutOther"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs"
        >
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <div>
                        <h3 class="font-bold text-gray-900">Log Out Other Browser Sessions</h3>
                        <p class="text-xs text-gray-500">Enter your password to confirm</p>
                    </div>
                    <button
                        type="button"
                        class="text-gray-400 hover:text-gray-600"
                        @click="closeLogoutOtherModal"
                    >
                        ✕
                    </button>
                </div>

                <p class="mt-3 text-xs leading-relaxed text-gray-600">
                    Please enter your password to confirm you would like to log out of your other browser sessions across all of your devices.
                </p>

                <form class="mt-4 space-y-4" @submit.prevent="logoutOtherBrowserSessions">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700">Current Password</label>
                        <TextInput
                            ref="passwordInput"
                            v-model="form.password"
                            type="password"
                            class="mt-1 block w-full text-xs"
                            placeholder="••••••••"
                            required
                        />
                        <InputError :message="form.errors.password" class="mt-1" />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button
                            type="button"
                            class="rounded-xl border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50"
                            @click="closeLogoutOtherModal"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-xl bg-[#087F8C] px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-[#066C76] disabled:opacity-50"
                        >
                            {{ form.processing ? 'Logging Out...' : 'Log Out Other Sessions' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</template>

