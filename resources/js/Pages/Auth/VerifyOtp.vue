<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
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

const submitVerify = () => {
    verifyForm.email = verifyForm.email.trim().toLowerCase()
    verifyForm.post(route('verification.otp.verify'))
}

const submitResend = () => {
    resendForm.email = (verifyForm.email || props.email || '').trim().toLowerCase()
    resendForm.post(route('verification.otp.resend'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Verify Email (OTP)" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-[#F8FAF9] px-4 py-12 text-[#1F2937]">
        <div class="w-full max-w-md rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-xl sm:p-8">
            <!-- ALONA LOGO -->
            <div class="mx-auto flex h-14 w-14 items-center justify-center overflow-hidden rounded-2xl bg-[#E8F7F6] p-2">
                <img
                    src="/images/Alogo.png"
                    alt="Alona"
                    class="h-full w-full object-contain"
                />
            </div>

            <div class="mt-4 text-center">
                <h1 class="text-xl font-bold text-[#1F2937]">
                    Verify your email address
                </h1>
                <p class="mt-2 text-sm text-[#64748B]">
                    Enter the 6-digit verification code sent to
                    <span class="font-semibold text-[#1F2937]">{{ verifyForm.email || 'your email' }}</span>.
                    The code expires in 5 minutes.
                </p>
            </div>

            <div
                v-if="status"
                class="mt-5 rounded-xl bg-[#E8F7F6] px-4 py-3 text-sm font-medium text-[#087F8C]"
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
                        6-Digit OTP Code
                    </label>
                    <input
                        id="otp-code"
                        v-model="verifyForm.otp"
                        type="text"
                        inputmode="numeric"
                        maxlength="6"
                        autocomplete="one-time-code"
                        placeholder="000000"
                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-center text-lg font-bold tracking-[0.4em] outline-none transition placeholder:tracking-normal focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                    />
                    <InputError
                        class="mt-2"
                        :message="verifyForm.errors.otp"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="verifyForm.processing"
                    class="w-full rounded-xl bg-[#087F8C] px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#16A6A0] disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ verifyForm.processing ? 'Verifying...' : 'Verify Account' }}
                </button>
            </form>

            <div class="mt-6 flex items-center justify-between border-t border-[#E5E7EB] pt-5 text-sm">
                <button
                    type="button"
                    @click="submitResend"
                    :disabled="resendForm.processing"
                    class="font-semibold text-[#087F8C] hover:text-[#16A6A0] disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ resendForm.processing ? 'Sending...' : 'Resend OTP' }}
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
</template>

