<script setup>
import { computed, nextTick, onUnmounted, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import LogisticsAddressSelector from '@/Components/LogisticsAddressSelector.vue'

const props = defineProps({
    requestKey: String,
    regions: Array,
    google: Object,
})

const failure = ref('')
const errorSummary = ref(null)
const currentStep = ref(1)
const documentInputVersion = ref(0)

const form = useForm({
    request_key: props.requestKey,
    business_name: '',
    registration_number: '',
    tin: '',
    first_name: '',
    last_name: '',
    position: '',
    email: props.google?.email ?? '',
    contact_no: '',
    password: '',
    password_confirmation: '',
    region_code: '',
    province_selection: '',
    province_code: '',
    city_code: '',
    barangay_code: '',
    street: '',
    postal_code: '',
    service_region_codes: [],
    id_doc: null,
    sec_doc: null,
    bir_doc: null,
    permit_doc: null,
    terms: false,
    privacy: false,
})

let submissionTimer

function clearSubmissionTimer() {
    clearTimeout(submissionTimer)
}

function watchSubmission() {
    clearSubmissionTimer()
    submissionTimer = setTimeout(() => {
        form.cancel()
        showFailure('Registration is taking longer than expected. Sign in to check whether your application was received before trying again.')
    }, 60000)
}

function showFailure(message) {
    failure.value = message
    nextTick(() => {
        errorSummary.value?.focus({ preventScroll: true })
        errorSummary.value?.scrollIntoView({ block: 'center' })
    })
}

const removeExceptionListener = router.on('exception', event => {
    if (!form.processing) return
    event.preventDefault()
    showFailure('Unable to reach ALONA. Check your connection and try again. If the request was already received, sign in to check your application.')
})

const removeInvalidListener = router.on('invalid', event => {
    if (!form.processing) return
    event.preventDefault()
    const status = event.detail.response.status
    showFailure({
        413: 'The server could not accept these uploads. Choose smaller files or contact support about upload limits.',
        419: 'Your registration session expired. Reload this page, complete the form and try again.',
        429: 'Too many attempts. Wait a minute before trying again.',
    }[status] ?? 'The server could not complete registration. Try again shortly. If the request was already received, sign in to check your application.')
})

onUnmounted(() => {
    clearSubmissionTimer()
    removeExceptionListener()
    removeInvalidListener()
})

const documents = [
    { name: 'sec_doc', label: 'SEC certificate' },
    { name: 'bir_doc', label: 'BIR Form 2303' },
    { name: 'permit_doc', label: "Mayor’s / business permit" },
    { name: 'id_doc', label: 'Authorized representative’s valid ID' },
]

const showPassword = ref(false)
const showConfirmPassword = ref(false)
const mobileMaxLength = computed(() => form.contact_no.startsWith('+') ? 13 : 11)
const mobileFormatMessage = 'Use 11 digits starting with 09 (09171234567), or +639 followed by 9 digits (+639171234567).'
const tinFormatMessage = 'Enter 12 digits, with or without hyphens (for example, 123-456-789-000).'

function validateTin() {
    const value = form.tin.trim()
    const digits = value.replace(/[\s\u2010-\u2015\u2212-]/g, '')
    if (!/^[0-9]{12}$/.test(digits)) {
        form.setError('tin', tinFormatMessage)
        return false
    }
    form.tin = digits.match(/[0-9]{3}/g).join('-')
    form.clearErrors('tin')
    return true
}

function validateMobileNumber() {
    if (!/^(09[0-9]{9}|\+639[0-9]{9})$/.test(form.contact_no)) {
        form.setError('contact_no', mobileFormatMessage)
        return false
    }
    form.clearErrors('contact_no')
    return true
}

function checkMobilePaste(event) {
    const pasted = event.clipboardData?.getData('text')
    if (!pasted) return
    const input = event.target
    const next = form.contact_no.slice(0, input.selectionStart) + pasted + form.contact_no.slice(input.selectionEnd)
    const limit = next.startsWith('+') ? 13 : 11
    if (next.length > limit || !/^\+?[0-9]*$/.test(next)) {
        event.preventDefault()
        form.setError('contact_no', mobileFormatMessage)
        return
    }
    // Apply accepted pastes ourselves so an empty field's local limit does not truncate +63 numbers.
    const caret = input.selectionStart + pasted.length
    event.preventDefault()
    form.contact_no = next
    input.value = next
    form.clearErrors('contact_no')
    nextTick(() => input.setSelectionRange?.(caret, caret))
}

function validateStep(step) {
    failure.value = ''
    if (step === 1) {
        if (!form.first_name || !form.last_name || !form.email || !form.contact_no) {
            failure.value = 'Please fill out your name, email, and mobile number before continuing.'
            return false
        }
        if (!validateMobileNumber()) {
            failure.value = mobileFormatMessage
            return false
        }
        if (!props.google && (!form.password || !form.password_confirmation)) {
            failure.value = 'Please provide and confirm your password before continuing.'
            return false
        }
        if (!props.google && form.password !== form.password_confirmation) {
            failure.value = 'Password confirmation does not match.'
            return false
        }
    } else if (step === 2) {
        if (!form.business_name || !form.registration_number || !form.tin) {
            failure.value = 'Please provide your company name, SEC registration, and TIN.'
            return false
        }
        if (!validateTin()) {
            failure.value = tinFormatMessage
            return false
        }
        if (!form.city_code || !form.barangay_code || !form.street) {
            failure.value = 'Please complete the company location address.'
            return false
        }
    }
    return true
}

function goToStep(step) {
    if (step > currentStep.value) {
        if (!validateStep(currentStep.value)) return
    }
    currentStep.value = step
    failure.value = ''
}

function submit() {
    if (form.processing) return
    failure.value = ''
    if (!validateMobileNumber()) {
        currentStep.value = 1
        showFailure(mobileFormatMessage)
        return
    }
    if (!validateTin()) {
        currentStep.value = 2
        showFailure(tinFormatMessage)
        return
    }

    try {
        form.post(route('register.logistics', {}, false), {
            forceFormData: true,
            onStart: watchSubmission,
            onProgress: watchSubmission,
            onFinish: clearSubmissionTimer,
            onError: errors => {
                const regionError = Object.keys(errors).find(key => key.startsWith('service_region_codes.'))
                if (regionError) form.setError('service_region_codes', errors[regionError])

                // Auto-route to the step with an error
                const errorKeys = Object.keys(errors)
                if (errorKeys.some(k => ['first_name', 'last_name', 'position', 'email', 'contact_no', 'password', 'password_confirmation'].includes(k))) {
                    currentStep.value = 1
                } else if (errorKeys.some(k => ['business_name', 'registration_number', 'tin', 'region_code', 'province_code', 'city_code', 'barangay_code', 'street', 'postal_code'].includes(k))) {
                    currentStep.value = 2
                } else {
                    currentStep.value = 3
                }

                showFailure(
                    props.google && errors.password
                        ? 'Your Google session expired. Reload this page and sign in again.'
                        : `Check the highlighted fields and choose your documents again. ${Object.values(errors).join(' ')}`
                )
                form.reset('password', 'password_confirmation', 'id_doc', 'sec_doc', 'bir_doc', 'permit_doc')
                // File inputs cannot be bound with v-model. Clear their native selections too.
                documentInputVersion.value++
            },
        })
    } catch {
        form.cancel()
        clearSubmissionTimer()
        form.processing = false
        form.progress = null
        showFailure('Registration could not start. Reload this page and try again.')
    }
}
</script>

<template>
    <Head title="Logistics Registration - Alona" />

    <GuestLayout>
        <!-- CANVAS BACKGROUND WITH SYSTEM PALETTE ACCENTS -->
        <main class="relative min-h-[calc(100vh-140px)] w-full overflow-hidden bg-[#F8FAF9] px-4 py-10 sm:px-6 lg:flex lg:items-center lg:justify-center lg:py-16">
            
            <!-- System-themed Ambient Corner Accents -->
            <div
                class="pointer-events-none absolute -bottom-28 -left-28 h-96 w-96 rounded-full bg-[#E8F7F6] opacity-90 blur-3xl sm:h-[460px] sm:w-[460px]"
                aria-hidden="true"
            ></div>

            <div
                class="pointer-events-none absolute -top-28 -right-28 h-96 w-96 rounded-[56px] bg-[#087F8C]/12 opacity-80 blur-2xl rotate-12 sm:h-[420px] sm:w-[420px]"
                aria-hidden="true"
            ></div>

            <!-- MAIN TWO-PANEL SPLIT CARD -->
            <div
                class="relative z-10 mx-auto flex w-full max-w-5xl flex-col overflow-hidden rounded-[28px] border border-teal-900/40 bg-[#05505A] shadow-2xl md:flex-row md:min-h-[660px]"
            >
                <!-- ================= LEFT PANEL: BRANDED PANEL (MATCHING AUTH MODAL) ================= -->
                <div class="relative hidden md:flex md:w-[340px] lg:w-[380px] shrink-0 flex-col justify-between overflow-hidden bg-white p-8 lg:p-10 text-slate-900 select-none">
                    <!-- Decorative background ambient circles matching target design -->
                    <div class="pointer-events-none absolute top-[55%] -left-14 h-44 w-44 rounded-full bg-[#F5A524]"></div>
                    <div class="pointer-events-none absolute -bottom-14 -right-14 h-64 w-64 rounded-full bg-[#067A87]"></div>

                    <!-- Top: Full Official Alona Logo with Tagline -->
                    <div class="relative z-10 flex items-center">
                        <img src="/images/alona.png" alt="Alona" class="h-14 w-auto object-contain" />
                    </div>

                    <!-- Bottom: Contextual Hero Heading & Subtitle -->
                    <div class="relative z-10 mt-auto pt-8">
                        <h3 class="text-3xl lg:text-[34px] font-bold leading-[1.15] tracking-tight text-[#0F172A]">
                            Partner with Alona Logistics.
                        </h3>
                        <p class="mt-3 text-sm leading-relaxed text-[#64748B]">
                            Join our nationwide delivery network, manage sorting hubs, and power seamless fulfillment across the Philippines.
                        </p>

                        <!-- Logistics Feature Highlights with System Icons -->
                        <div class="mt-6 space-y-2.5">
                            <div class="flex items-center gap-3 rounded-xl bg-slate-50 border border-slate-100 p-2.5 shadow-sm">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#05505A] text-[#F5A524]">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-slate-800">Automated Dispatch</p>
                                    <p class="text-[11px] text-slate-500 truncate">Direct seller-to-hub parcel transfers</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 rounded-xl bg-slate-50 border border-slate-100 p-2.5 shadow-sm">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#05505A] text-[#F5A524]">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-slate-800">Verified Consignments</p>
                                    <p class="text-[11px] text-slate-500 truncate">Protected payouts & dispute support</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================= RIGHT PANEL: CREATE ACCOUNT (DARK TEAL FORM) ================= -->
                <div class="flex w-full flex-col justify-between bg-[#05505A] p-6 sm:p-10 md:w-[60%] md:p-12 text-white">
                    <div>
                        <!-- PROGRESS HEADER -->
                        <div class="mb-5">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-sm font-semibold text-teal-300">
                                    Step {{ currentStep }} of 3
                                </span>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white/10 text-white border border-white/15">
                                    Logistics Partner Onboarding
                                </span>
                            </div>

                            <div class="flex gap-2">
                                <div
                                    v-for="stepNum in 3"
                                    :key="stepNum"
                                    class="h-1.5 flex-1 rounded-full transition-all duration-300"
                                    :class="stepNum <= currentStep ? 'bg-[#F5A524]' : 'bg-white/20'"
                                ></div>
                            </div>
                        </div>

                        <!-- Title and Subtitle -->
                        <div class="mb-6">
                            <h2 class="text-2xl sm:text-3xl font-bold tracking-tight text-white">
                                {{
                                    currentStep === 1
                                        ? 'Account information'
                                        : currentStep === 2
                                            ? 'Company details & location'
                                            : 'Compliance documents & coverage'
                                }}
                            </h2>
                            <p class="mt-1 text-sm text-teal-100/70">
                                {{
                                    currentStep === 1
                                        ? 'Create your Alona logistics enterprise credentials.'
                                        : currentStep === 2
                                            ? 'Provide your business registration and operating address.'
                                            : 'Upload required accreditation documents and select coverage areas.'
                                }}
                            </p>
                        </div>

                        <!-- Google Sign-In Option (Step 1 only) -->
                        <div v-if="currentStep === 1">
                            <a
                                :href="route('google.redirect', { role: 'sorting_center' })"
                                class="flex w-full items-center justify-center gap-3 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-slate-100"
                                title="Continue with Google"
                            >
                                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24">
                                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                                </svg>
                                <span>Continue with Google</span>
                            </a>

                            <!-- Sub-caption -->
                            <div class="my-5 flex items-center gap-3 text-xs text-teal-200/60">
                                <span class="h-px flex-1 bg-white/15"></span>
                                <span>or use your email for registration</span>
                                <span class="h-px flex-1 bg-white/15"></span>
                            </div>
                        </div>

                        <!-- Google active banner -->
                        <div
                            v-if="google"
                            class="mb-4 rounded-xl bg-white/10 border border-white/20 p-3 text-xs text-teal-200 font-semibold text-center"
                        >
                            Continuing with Google as {{ google.email }}.
                        </div>

                        <!-- Failure Alert -->
                        <div
                            v-if="failure"
                            ref="errorSummary"
                            role="alert"
                            tabindex="-1"
                            class="mb-4 rounded-xl bg-rose-50 border border-rose-200 p-3 text-xs font-semibold text-rose-800 shadow-sm"
                        >
                            {{ failure }}
                        </div>

                        <!-- FORM -->
                        <form
                            class="mt-6 space-y-4"
                            @submit.prevent="submit"
                            @invalid.capture="failure = 'Please complete the required fields with valid values before submitting.'"
                        >

                            <!-- ================= STEP 1: ACCOUNT & REPRESENTATIVE ================= -->
                            <div v-show="currentStep === 1" class="space-y-3">
                                
                                <!-- First & Last Name -->
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="field-container">
                                        <svg class="field-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        <input
                                            id="first_name"
                                            v-model="form.first_name"
                                            required
                                            type="text"
                                            placeholder="First Name *"
                                            class="field-input"
                                        />
                                    </div>

                                    <div class="field-container">
                                        <svg class="field-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        <input
                                            id="last_name"
                                            v-model="form.last_name"
                                            required
                                            type="text"
                                            placeholder="Last Name *"
                                            class="field-input"
                                        />
                                    </div>
                                </div>
                                <p v-if="form.errors.first_name || form.errors.last_name" class="text-[11px] font-semibold text-rose-600">
                                    {{ form.errors.first_name || form.errors.last_name }}
                                </p>

                                <!-- Email -->
                                <div class="field-container">
                                    <svg class="field-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    <input
                                        id="email"
                                        v-model="form.email"
                                        required
                                        type="email"
                                        :disabled="!!google"
                                        placeholder="Email Address *"
                                        class="field-input"
                                    />
                                </div>
                                <p v-if="form.errors.email" class="text-[11px] font-semibold text-rose-600">
                                    {{ form.errors.email }}
                                </p>

                                <!-- Contact No & Position -->
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="field-container">
                                        <svg class="field-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                        <input
                                            id="contact_no"
                                            v-model="form.contact_no"
                                            required
                                            type="tel"
                                            inputmode="tel"
                                            autocomplete="tel"
                                            aria-label="Philippine mobile number"
                                            :maxlength="mobileMaxLength"
                                            :minlength="mobileMaxLength"
                                            pattern="(09[0-9]{9}|\+639[0-9]{9})"
                                            :aria-invalid="!!form.errors.contact_no"
                                            :aria-describedby="form.errors.contact_no ? 'contact_no-help contact_no-error' : 'contact_no-help'"
                                            @input="form.clearErrors('contact_no')"
                                            @blur="validateMobileNumber"
                                            @paste="checkMobilePaste"
                                            placeholder="Mobile (09171234567) *"
                                            class="field-input"
                                        />
                                    </div>

                                    <div class="field-container">
                                        <svg class="field-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                        <input
                                            id="position"
                                            v-model="form.position"
                                            required
                                            type="text"
                                            placeholder="Position / Title *"
                                            class="field-input"
                                        />
                                    </div>
                                </div>
                                <p id="contact_no-help" class="text-[10px] text-slate-500">
                                    Mobile: 11 digits starting with 09, or +639 followed by 9 digits. No spaces or hyphens.
                                </p>
                                <p v-if="form.errors.contact_no" id="contact_no-error" role="alert" class="text-[11px] font-semibold text-rose-600">
                                    {{ form.errors.contact_no }}
                                </p>
                                <p v-if="form.errors.position" class="text-[11px] font-semibold text-rose-600">
                                    {{ form.errors.position }}
                                </p>

                                <!-- Password & Confirm (if not Google) -->
                                <template v-if="!google">
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div class="field-container">
                                            <svg class="field-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                            <input
                                                id="password"
                                                v-model="form.password"
                                                required
                                                :type="showPassword ? 'text' : 'password'"
                                                placeholder="Password *"
                                                class="field-input"
                                            />
                                            <button
                                                type="button"
                                                @click="showPassword = !showPassword"
                                                class="text-[10px] font-bold text-slate-400 hover:text-slate-600"
                                            >
                                                {{ showPassword ? 'Hide' : 'Show' }}
                                            </button>
                                        </div>

                                        <div class="field-container">
                                            <svg class="field-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                            </svg>
                                            <input
                                                id="password_confirmation"
                                                v-model="form.password_confirmation"
                                                required
                                                :type="showConfirmPassword ? 'text' : 'password'"
                                                placeholder="Confirm Password *"
                                                class="field-input"
                                            />
                                            <button
                                                type="button"
                                                @click="showConfirmPassword = !showConfirmPassword"
                                                class="text-[10px] font-bold text-slate-400 hover:text-slate-600"
                                            >
                                                {{ showConfirmPassword ? 'Hide' : 'Show' }}
                                            </button>
                                        </div>
                                    </div>
                                    <p v-if="form.errors.password || form.errors.password_confirmation" class="text-[11px] font-semibold text-rose-600">
                                        {{ form.errors.password || form.errors.password_confirmation }}
                                    </p>
                                    <p class="text-[10px] text-slate-400">
                                        Use at least 8 characters with uppercase, lowercase, numbers, and symbols.
                                    </p>
                                </template>

                                <!-- Step 1 Next Button -->
                                <div class="pt-3">
                                    <button
                                        type="button"
                                        @click="goToStep(2)"
                                        class="w-full rounded-xl bg-[#F5A524] py-3.5 px-4 text-sm font-bold text-slate-900 shadow-md transition hover:bg-[#e0941d] active:scale-[0.99]"
                                    >
                                        Next: Company Details →
                                    </button>
                                </div>
                            </div>

                            <!-- ================= STEP 2: COMPANY DETAILS & ADDRESS ================= -->
                            <div v-show="currentStep === 2" class="space-y-3">
                                
                                <!-- Company Name -->
                                <div class="field-container">
                                    <svg class="field-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                    <input
                                        id="business_name"
                                        v-model="form.business_name"
                                        required
                                        type="text"
                                        placeholder="Company / Trade Name *"
                                        class="field-input"
                                    />
                                </div>
                                <p v-if="form.errors.business_name" class="text-[11px] font-semibold text-rose-300">
                                    {{ form.errors.business_name }}
                                </p>

                                <!-- Registration No & TIN -->
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div class="field-container">
                                        <svg class="field-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <input
                                            id="registration_number"
                                            v-model="form.registration_number"
                                            required
                                            type="text"
                                            placeholder="SEC Number (e.g. CS202012345) *"
                                            class="field-input"
                                        />
                                    </div>

                                    <div class="field-container">
                                        <svg class="field-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                        <input
                                            id="tin"
                                            v-model="form.tin"
                                            required
                                            type="text"
                                            inputmode="numeric"
                                            aria-label="TIN with branch code"
                                            :aria-invalid="!!form.errors.tin"
                                            :aria-describedby="form.errors.tin ? 'tin-help tin-error' : 'tin-help'"
                                            @input="form.clearErrors('tin')"
                                            @blur="validateTin"
                                            placeholder="TIN (000-000-000-000) *"
                                            class="field-input"
                                        />
                                    </div>
                                </div>
                                <p id="tin-help" class="text-[10px] text-teal-200/80">
                                    Enter all 12 digits, including the branch code. Hyphens are added automatically when you leave this field.
                                </p>
                                <p v-if="form.errors.registration_number" class="text-[11px] font-semibold text-rose-300">
                                    {{ form.errors.registration_number }}
                                </p>
                                <p v-if="form.errors.tin" id="tin-error" role="alert" class="text-[11px] font-semibold text-rose-300">
                                    {{ form.errors.tin }}
                                </p>

                                <!-- Logistics Address Selector (PSGC) -->
                                <div class="rounded-2xl border border-white/15 bg-white/5 p-4 text-slate-800">
                                    <p class="mb-2 text-xs font-bold text-white">Company Location (Philippine Address)</p>
                                    <LogisticsAddressSelector :form="form" :regions="regions" />
                                    
                                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                        <div class="field-container">
                                            <input
                                                id="street"
                                                v-model="form.street"
                                                required
                                                type="text"
                                                placeholder="Street Address *"
                                                class="field-input"
                                            />
                                        </div>
                                        <div class="field-container">
                                            <input
                                                id="postal_code"
                                                v-model="form.postal_code"
                                                required
                                                type="text"
                                                placeholder="Postal Code *"
                                                class="field-input"
                                            />
                                        </div>
                                    </div>
                                    <p v-if="form.errors.street || form.errors.postal_code" class="mt-1 text-[11px] font-semibold text-rose-300">
                                        {{ form.errors.street || form.errors.postal_code }}
                                    </p>
                                </div>

                                <!-- Step 2 Action Buttons -->
                                <div class="flex items-center justify-between gap-3 pt-3">
                                    <button
                                        type="button"
                                        @click="currentStep = 1"
                                        class="flex-1 rounded-xl bg-white px-4 py-3.5 text-sm font-bold text-slate-800 shadow-sm transition hover:bg-slate-100"
                                    >
                                        Back
                                    </button>
                                    <button
                                        type="button"
                                        @click="goToStep(3)"
                                        class="flex-1 rounded-xl bg-[#F5A524] px-4 py-3.5 text-sm font-bold text-slate-900 shadow-md transition hover:bg-[#e0941d] active:scale-[0.99]"
                                    >
                                        Next: Documents →
                                    </button>
                                </div>
                            </div>

                            <!-- ================= STEP 3: COVERAGE & DOCUMENTS ================= -->
                            <div v-show="currentStep === 3" class="space-y-4">
                                
                                <!-- Service Areas -->
                                <div class="rounded-2xl border border-white/15 bg-white/5 p-4 text-white">
                                    <p class="text-xs font-bold text-white">Service Coverage Areas *</p>
                                    <p class="text-[11px] text-teal-100/70">Select at least one region your sorting center covers:</p>
                                    <div class="mt-2.5 grid max-h-36 gap-2 overflow-y-auto pr-1 sm:grid-cols-2">
                                        <label
                                            v-for="region in regions"
                                            :key="region.code"
                                            class="flex cursor-pointer items-center gap-2 rounded-xl bg-white/10 p-2 text-xs font-medium text-white hover:bg-white/20 transition"
                                        >
                                            <input
                                                v-model="form.service_region_codes"
                                                type="checkbox"
                                                :value="region.code"
                                                class="rounded border-0 bg-white text-[#F5A524] focus:ring-[#F5A524]"
                                            />
                                            <span class="truncate">{{ region.name }}</span>
                                        </label>
                                    </div>
                                    <p v-if="form.errors.service_region_codes" class="mt-1 text-[11px] font-semibold text-rose-300">
                                        {{ form.errors.service_region_codes }}
                                    </p>
                                </div>

                                <!-- Company Document Uploads -->
                                <div class="rounded-2xl border border-white/15 bg-white/5 p-4 text-white">
                                    <p class="text-xs font-bold text-white">Company Compliance Documents *</p>
                                    <p class="text-[11px] text-teal-100/70">PDF, JPG, or PNG up to 5MB each:</p>
                                    
                                    <div class="mt-2.5 grid gap-2.5 sm:grid-cols-2">
                                        <div
                                            v-for="doc in documents"
                                            :key="doc.name"
                                            class="rounded-xl border border-dashed border-white/25 bg-white/10 p-3 text-xs transition hover:border-[#F5A524]"
                                        >
                                            <span class="block font-bold text-white truncate">{{ doc.label }}</span>
                                            <input
                                                :key="`${doc.name}-${documentInputVersion}`"
                                                :id="doc.name"
                                                type="file"
                                                accept=".jpg,.jpeg,.png,.pdf"
                                                required
                                                class="mt-2 block w-full text-[10px] text-teal-100 file:mr-2 file:rounded-lg file:border-0 file:bg-[#F5A524] file:px-2.5 file:py-1 file:text-[10px] file:font-bold file:text-slate-900 shadow-sm"
                                                @change="form[doc.name] = $event.target.files?.[0] || null"
                                            />
                                            <p v-if="form.errors[doc.name]" class="mt-1 text-[10px] font-semibold text-rose-300">
                                                {{ form.errors[doc.name] }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Terms & Privacy Checkboxes -->
                                <div class="rounded-xl border border-white/15 bg-white/10 p-4 space-y-3">
                                    <label class="flex items-start gap-3 cursor-pointer">
                                        <input
                                            v-model="form.terms"
                                            type="checkbox"
                                            required
                                            class="mt-0.5 rounded border-0 bg-white text-[#F5A524] focus:ring-[#F5A524]"
                                        />
                                        <span class="text-xs text-white">I accept the <Link href="/policies" class="font-semibold text-teal-300 underline hover:text-white">Terms and Conditions</Link>.</span>
                                    </label>
                                    <label class="flex items-start gap-3 cursor-pointer">
                                        <input
                                            v-model="form.privacy"
                                            type="checkbox"
                                            required
                                            class="mt-0.5 rounded border-0 bg-white text-[#F5A524] focus:ring-[#F5A524]"
                                        />
                                        <span class="text-xs text-white">I accept the <Link href="/policies" class="font-semibold text-teal-300 underline hover:text-white">Privacy Policy</Link>.</span>
                                    </label>
                                    <p v-if="form.errors.terms || form.errors.privacy" class="text-[11px] font-semibold text-rose-300">
                                        {{ form.errors.terms || form.errors.privacy }}
                                    </p>
                                </div>

                                <!-- Step 3 Actions: SIGN UP Pill Button (Matches Image!) -->
                                <div class="flex items-center justify-between gap-3 pt-2">
                                    <button
                                        type="button"
                                        @click="currentStep = 2"
                                        class="flex-1 rounded-xl bg-white px-4 py-3.5 text-sm font-bold text-slate-800 shadow-sm transition hover:bg-slate-100"
                                    >
                                        Back
                                    </button>
                                    <button
                                        type="submit"
                                        :disabled="form.processing"
                                        class="flex-1 rounded-xl bg-[#F5A524] px-4 py-3.5 text-sm font-bold text-slate-900 shadow-md transition hover:bg-[#e0941d] active:scale-[0.99] disabled:opacity-50"
                                    >
                                        {{ form.processing ? 'Submitting Application...' : 'Submit Application' }}
                                    </button>
                                </div>
                            </div>

                        </form>

                        <!-- FOOTER -->
                        <div class="mt-6 text-center text-xs text-teal-100/70">
                            Already have a partner account?
                            <a
                                href="/?auth=partner-login"
                                class="font-semibold text-teal-300 hover:text-white hover:underline ml-1"
                            >
                                Log in
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </GuestLayout>
</template>

<style scoped>
.field-container {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border-radius: 0.75rem;
    background-color: #FFFFFF;
    padding: 0.75rem 1rem;
    border: 0;
    transition: all 0.15s ease;
    box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.06);
}

.field-container:focus-within {
    box-shadow: 0 0 0 2px #F5A524;
}

.field-icon {
    height: 1.1rem;
    width: 1.1rem;
    flex-shrink: 0;
    color: #94A3B8;
}

.field-input {
    width: 100%;
    min-width: 0;
    border: 0;
    background: transparent;
    padding: 0;
    font-size: 0.875rem;
    line-height: 1.25rem;
    color: #0F172A;
    outline: none;
}

.field-input::placeholder {
    color: #94A3B8;
}

.field-input:focus {
    outline: none;
    box-shadow: none;
}
</style>
