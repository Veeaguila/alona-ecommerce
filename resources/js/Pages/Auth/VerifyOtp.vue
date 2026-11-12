<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
    email: {
        type: String,
        default: '',
    },
    status: {
        type: String,
        default: null,
    },
})

const verifyForm = useForm({
    email: props.email || '',
    otp: '',
})

const resendForm = useForm({
    email: props.email || '',
})

const resendCooldown = ref(60)
let resendTimer = null

const startCooldown = (seconds = 60) => {
    if (resendTimer) clearInterval(resendTimer)
    resendCooldown.value = seconds
    resendTimer = setInterval(() => {
        if (resendCooldown.value > 0) {
            resendCooldown.value--
        } else {
            clearInterval(resendTimer)
            resendTimer = null
        }
    }, 1000)
}

onMounted(() => {
    startCooldown(60)
})

onUnmounted(() => {
    if (resendTimer) clearInterval(resendTimer)
})

const handleOtpInput = (event) => {
    verifyForm.otp = event.target.value.replace(/\D/g, '').slice(0, 6)
    if (verifyForm.errors.otp) verifyForm.clearErrors('otp')
    if (verifyForm.otp.length === 6) {
        submitVerify()
    }
}

const submitVerify = () => {
    verifyForm.email = verifyForm.email.trim().toLowerCase()
    verifyForm.post(route('verification.otp.verify'))
}

const submitResend = () => {
    if (resendCooldown.value > 0 || resendForm.processing) return
    resendForm.email = (verifyForm.email || props.email || '').trim().toLowerCase()
    resendForm.post(route('verification.otp.resend'), {
        preserveScroll: true,
        onSuccess: () => {
            startCooldown(60)
        },
    })
}

const closeModal = () => {
    router.visit('/')
}

const closeOnBackground = (event) => {
    if (event.target === event.currentTarget) {
        closeModal()
    }
}
</script>

<template>
    <Head title="Verify Email (OTP)" />

    <GuestLayout>
        <!-- Modal Backdrop Overlay -->
        <div
            class="fixed inset-0 z-[100] flex items-center justify-center bg-[#1F2937]/55 px-4 py-6 backdrop-blur-sm"
            @click="closeOnBackground"
        >
            <div
                class="relative max-h-[90vh] w-full max-w-md overflow-y-auto rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-2xl sm:p-8"
                role="dialog"
                aria-modal="true"
                aria-label="Verify Email"
            >
                <!-- Close Button -->
                <button
                    type="button"
                    @click="closeModal"
                    class="absolute right-4 top-4 z-20 flex h-9 w-9 items-center justify-center rounded-full text-[#64748B] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                    aria-label="Close"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- ALONA LOGO -->
                <div class="mx-auto flex h-14 w-14 items-center justify-center overflow-hidden rounded-2xl bg-[#E8F7F6] p-2">
                    <img
                        src="/images/Alogo.png"
                        alt="Alona"
                        class="h-full w-full object-contain"
                    />
                </div>

                <div class="mt-4 text-center">
                    <h1 class="text-2xl font-bold tracking-tight text-[#1F2937]">
                        Verify your email
                    </h1>
                    <p class="mt-2 text-sm leading-5 text-[#64748B]">
                        Enter the 6-digit verification code sent to
                        <span class="font-semibold text-[#1F2937]">{{ verifyForm.email || 'your email' }}</span>.
                        The code expires in 5 minutes.
                    </p>
                </div>

                <div
                    v-if="status"
                    class="mt-5 rounded-xl border border-emerald-200 bg-[#E8F7F6] px-4 py-3 text-sm font-medium text-[#087F8C]"
                >
                    {{ status }}
                </div>

                <form
                    @submit.prevent="submitVerify"
                    class="mt-6 space-y-5"
                >
                    <div v-if="!props.email">
                        <label
                            for="otp-email"
                            class="block text-sm font-medium text-[#1F2937]"
                        >
                            Email address
                        </label>
                        <input
                            id="otp-email"
                            v-model="verifyForm.email"
                            type="email"
                            required
                            placeholder="you@example.com"
                            class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                        />
                        <InputError
                            class="mt-2"
                            :message="verifyForm.errors.email"
                        />
                    </div>

                    <div>
                        <label
                            for="otp-code"
                            class="block text-sm font-medium text-[#1F2937]"
                        >
                            6-Digit Verification Code
                        </label>
                        <input
                            id="otp-code"
                            :value="verifyForm.otp"
                            @input="handleOtpInput"
                            type="text"
                            inputmode="numeric"
                            maxlength="6"
                            autocomplete="one-time-code"
                            placeholder="000000"
                            autofocus
                            class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-center text-2xl font-bold tracking-[0.5em] text-[#1F2937] outline-none transition placeholder:tracking-normal placeholder:font-normal focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                        />
                        <InputError
                            class="mt-2"
                            :message="verifyForm.errors.otp"
                        />
                        <p class="mt-2 text-xs text-[#94A3B8]">
                            Please check your inbox (or spam folder) for the 6-digit code.
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="verifyForm.processing || verifyForm.otp.trim().length !== 6"
                        class="w-full rounded-xl bg-[#087F8C] px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#16A6A0] disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ verifyForm.processing ? 'Verifying...' : 'Verify Account' }}
                    </button>
                </form>

                <div class="mt-6 flex items-center justify-between border-t border-[#E5E7EB] pt-5 text-sm">
                    <button
                        type="button"
                        @click="submitResend"
                        :disabled="resendForm.processing || resendCooldown > 0"
                        class="font-semibold text-[#087F8C] hover:text-[#16A6A0] disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{
                            resendForm.processing
                                ? 'Sending...'
                                : resendCooldown > 0
                                    ? `Resend code in ${resendCooldown}s`
                                    : 'Resend Code'
                        }}
                    </button>

                    <Link
                        :href="route('login')"
                        class="font-medium text-[#64748B] hover:text-[#1F2937]"
                    >
                        Back to Log in
                    </Link>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>
