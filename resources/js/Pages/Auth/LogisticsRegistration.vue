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

function validateStep(step) {
    failure.value = ''
    if (step === 1) {
        if (!form.first_name || !form.last_name || !form.email || !form.contact_no) {
            failure.value = 'Please fill out your name, email, and mobile number before continuing.'
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
                class="relative z-10 mx-auto flex w-full max-w-5xl flex-col overflow-hidden rounded-[28px] border border-slate-100 bg-white shadow-[0_20px_50px_rgba(8,127,140,0.08)] md:flex-row md:min-h-[660px]"
            >
                
                <!-- ================= LEFT PANEL: WELCOME BACK (SYSTEM PALETTE & ENHANCED DESIGN) ================= -->
                <div
                    class="relative flex w-full flex-col justify-between overflow-hidden bg-gradient-to-br from-[#087F8C] via-[#0A8F9E] to-[#065F6B] p-8 text-white sm:p-10 md:w-[42%]"
                >
                    <!-- Background Logistics Decorative Elements -->
                    <div
                        class="pointer-events-none absolute -top-20 -left-20 h-64 w-64 rounded-full bg-white/10 blur-2xl"
                        aria-hidden="true"
                    ></div>
                    <div
                        class="pointer-events-none absolute -bottom-24 -right-24 h-72 w-72 rounded-full border border-white/15 bg-white/5"
                        aria-hidden="true"
                    ></div>
                    <div
                        class="pointer-events-none absolute top-1/4 right-4 h-24 w-24 rounded-3xl border border-white/10 bg-white/5 rotate-12 backdrop-blur-sm"
                        aria-hidden="true"
                    ></div>

                    <!-- Top: Brand Logo & Partner Badge -->
                    <div class="relative z-10 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 backdrop-blur-md border border-white/25 shadow-inner">
                                <img
                                    src="/images/Alogo.png"
                                    alt="Alona"
                                    class="h-7 w-7 object-contain drop-shadow"
                                />
                            </div>
                            <div>
                                <span class="block text-base font-extrabold tracking-wide text-white sm:text-lg leading-tight">
                                    Alona Logistics
                                </span>
                                <span class="block text-[10px] font-semibold uppercase tracking-wider text-white/75">
                                    Hub & 3PL Network
                                </span>
                            </div>
                        </div>
                        <span class="rounded-full border border-white/25 bg-white/15 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-white backdrop-blur-sm shadow-sm">
                            PARTNER
                        </span>
                    </div>

                    <!-- Center: Welcome Back & Ghost Sign In Button & Feature Highlights -->
                    <div class="relative z-10 my-auto py-6 text-center">
                        <div class="inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/10 px-3.5 py-1 text-[11px] font-bold tracking-wider text-white backdrop-blur-sm mb-3">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 animate-pulse"></span>
                            <span>OFFICIAL ONBOARDING</span>
                        </div>

                        <h2 class="text-3xl font-extrabold tracking-tight text-white sm:text-4xl">
                            Welcome Back!
                        </h2>

                        <p class="mx-auto mt-3 max-w-xs text-xs leading-relaxed text-white/90 sm:text-sm">
                            To keep connected with our merchant fleet, please sign in with your partner credentials.
                        </p>

                        <!-- Ghost Sign In Pill Button (Clean Centered Pill) -->
                        <div class="mt-6">
                            <a
                                href="/?auth=login"
                                class="inline-block rounded-full border-2 border-white px-10 py-2.5 text-xs font-extrabold uppercase tracking-widest text-white shadow-sm transition-all duration-200 hover:bg-white hover:text-[#087F8C] active:scale-95"
                            >
                                SIGN IN
                            </a>
                        </div>

                        <!-- Logistics Feature Highlights with System Icons -->
                        <div class="mt-8 space-y-2 text-left max-w-xs mx-auto">
                            <div class="flex items-center gap-3 rounded-xl border border-white/15 bg-white/10 px-3.5 py-2 backdrop-blur-sm">
                                <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white/20 text-white">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 6h11v10H3V6Z" />
                                        <path d="M14 10h4l3 3v3h-7v-6Z" />
                                        <circle cx="7" cy="19" r="1.5" />
                                        <circle cx="17" cy="19" r="1.5" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-white leading-none">Automated Dispatch</p>
                                    <p class="text-[10px] text-white/75 truncate mt-0.5">Direct seller-to-hub parcel transfers</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 rounded-xl border border-white/15 bg-white/10 px-3.5 py-2 backdrop-blur-sm">
                                <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white/20 text-white">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 21s7-6 7-11a7 7 0 1 0-14 0c0 5 7 11 7 11Z" />
                                        <circle cx="12" cy="10" r="2.5" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-white leading-none">Real-Time Manifests</p>
                                    <p class="text-[10px] text-white/75 truncate mt-0.5">Live barcode scanning & tracking</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 rounded-xl border border-white/15 bg-white/10 px-3.5 py-2 backdrop-blur-sm">
                                <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white/20 text-white">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z" />
                                        <path d="m9 12 2 2 4-4" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-white leading-none">Verified Consignments</p>
                                    <p class="text-[10px] text-white/75 truncate mt-0.5">Protected payouts & dispute support</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================= RIGHT PANEL: CREATE ACCOUNT (WHITE FORM) ================= -->
                <div class="flex w-full flex-col justify-between bg-white p-6 sm:p-10 md:w-[58%] md:p-12">
                    <div>
                        <!-- Title in System Brand Palette -->
                        <h1 class="text-center text-2xl font-extrabold tracking-tight text-[#087F8C] sm:text-3xl lg:text-4xl">
                            Create Account
                        </h1>

                        <!-- Google Sign-In Option (Facebook & LinkedIn Removed) -->
                        <div class="mt-4 flex items-center justify-center">
                            <a
                                :href="route('google.redirect', { role: 'sorting_center' })"
                                class="inline-flex items-center gap-2.5 rounded-full border border-slate-200 bg-white px-6 py-2.5 text-xs font-bold text-slate-700 shadow-sm transition hover:border-[#087F8C] hover:bg-[#F8FAF9] hover:text-[#087F8C] hover:shadow active:scale-95"
                                title="Continue with Google"
                            >
                                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24">
                                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                                </svg>
                                <span>Continue with Google</span>
                            </a>
                        </div>

                        <!-- Sub-caption -->
                        <div class="mt-4 flex items-center justify-center gap-3">
                            <span class="h-px w-12 bg-slate-200"></span>
                            <span class="text-xs font-medium text-slate-400">or use your email for registration:</span>
                            <span class="h-px w-12 bg-slate-200"></span>
                        </div>

                        <!-- Google active banner -->
                        <div
                            v-if="google"
                            class="mt-4 rounded-xl bg-[#E8F7F6] p-3 text-xs text-[#087F8C] font-semibold text-center"
                        >
                            Continuing with Google as {{ google.email }}.
                        </div>

                        <!-- Failure Alert -->
                        <div
                            v-if="failure"
                            ref="errorSummary"
                            role="alert"
                            tabindex="-1"
                            class="mt-4 rounded-xl border border-rose-300 bg-rose-50 p-3 text-xs font-semibold text-rose-700"
                        >
                            {{ failure }}
                        </div>

                        <!-- STEP PROGRESS TABS (SYSTEM BRAND PALETTE) -->
                        <div class="mt-6 flex items-center justify-center gap-2 border-b border-slate-100 pb-3">
                            <button
                                type="button"
                                @click="goToStep(1)"
                                class="flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-bold transition"
                                :class="currentStep === 1 ? 'bg-[#087F8C] text-white shadow-sm ring-2 ring-[#087F8C]/20' : 'text-slate-500 hover:text-slate-800 hover:bg-slate-100'"
                            >
                                <span class="flex h-4 w-4 items-center justify-center rounded-full bg-white/20 text-[10px]">1</span>
                                <span>Account</span>
                            </button>
                            <button
                                type="button"
                                @click="goToStep(2)"
                                class="flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-bold transition"
                                :class="currentStep === 2 ? 'bg-[#087F8C] text-white shadow-sm ring-2 ring-[#087F8C]/20' : 'text-slate-500 hover:text-slate-800 hover:bg-slate-100'"
                            >
                                <span class="flex h-4 w-4 items-center justify-center rounded-full bg-white/20 text-[10px]">2</span>
                                <span>Company</span>
                            </button>
                            <button
                                type="button"
                                @click="goToStep(3)"
                                class="flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-bold transition"
                                :class="currentStep === 3 ? 'bg-[#087F8C] text-white shadow-sm ring-2 ring-[#087F8C]/20' : 'text-slate-500 hover:text-slate-800 hover:bg-slate-100'"
                            >
                                <span class="flex h-4 w-4 items-center justify-center rounded-full bg-white/20 text-[10px]">3</span>
                                <span>Documents</span>
                            </button>
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
                                <p v-if="form.errors.contact_no || form.errors.position" class="text-[11px] font-semibold text-rose-600">
                                    {{ form.errors.contact_no || form.errors.position }}
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
                                <div class="pt-3 text-center">
                                    <button
                                        type="button"
                                        @click="goToStep(2)"
                                        class="rounded-full bg-[#087F8C] px-10 py-3 text-xs font-extrabold uppercase tracking-wider text-white shadow-md transition hover:bg-[#065F6B] active:scale-95"
                                    >
                                        NEXT: COMPANY DETAILS →
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
                                <p v-if="form.errors.business_name" class="text-[11px] font-semibold text-rose-600">
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
                                            placeholder="TIN (000-000-000-000) *"
                                            class="field-input"
                                        />
                                    </div>
                                </div>
                                <p v-if="form.errors.registration_number || form.errors.tin" class="text-[11px] font-semibold text-rose-600">
                                    {{ form.errors.registration_number || form.errors.tin }}
                                </p>

                                <!-- Logistics Address Selector (PSGC) -->
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-3.5">
                                    <p class="mb-2 text-xs font-bold text-slate-700">Company Location (Philippine Address)</p>
                                    <LogisticsAddressSelector :form="form" :regions="regions" />
                                    
                                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                        <div class="field-container bg-white">
                                            <input
                                                id="street"
                                                v-model="form.street"
                                                required
                                                type="text"
                                                placeholder="Street Address *"
                                                class="field-input"
                                            />
                                        </div>
                                        <div class="field-container bg-white">
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
                                    <p v-if="form.errors.street || form.errors.postal_code" class="mt-1 text-[11px] font-semibold text-rose-600">
                                        {{ form.errors.street || form.errors.postal_code }}
                                    </p>
                                </div>

                                <!-- Step 2 Action Buttons -->
                                <div class="flex items-center justify-between pt-3">
                                    <button
                                        type="button"
                                        @click="currentStep = 1"
                                        class="text-xs font-bold text-slate-500 hover:text-slate-800"
                                    >
                                        ← Back to Account
                                    </button>
                                    <button
                                        type="button"
                                        @click="goToStep(3)"
                                        class="rounded-full bg-[#087F8C] px-10 py-3 text-xs font-extrabold uppercase tracking-wider text-white shadow-md transition hover:bg-[#065F6B] active:scale-95"
                                    >
                                        NEXT: DOCUMENTS →
                                    </button>
                                </div>
                            </div>

                            <!-- ================= STEP 3: COVERAGE & DOCUMENTS ================= -->
                            <div v-show="currentStep === 3" class="space-y-4">
                                
                                <!-- Service Areas -->
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-3.5">
                                    <p class="text-xs font-bold text-slate-700">Service Coverage Areas *</p>
                                    <p class="text-[11px] text-slate-500">Select at least one region your sorting center covers:</p>
                                    <div class="mt-2.5 grid max-h-36 gap-2 overflow-y-auto pr-1 sm:grid-cols-2">
                                        <label
                                            v-for="region in regions"
                                            :key="region.code"
                                            class="flex cursor-pointer items-center gap-2 rounded-lg bg-white p-2 text-xs font-medium text-slate-700 hover:bg-[#E8F7F6]"
                                        >
                                            <input
                                                v-model="form.service_region_codes"
                                                type="checkbox"
                                                :value="region.code"
                                                class="rounded border-slate-300 text-[#087F8C] focus:ring-[#087F8C]"
                                            />
                                            <span class="truncate">{{ region.name }}</span>
                                        </label>
                                    </div>
                                    <p v-if="form.errors.service_region_codes" class="mt-1 text-[11px] font-semibold text-rose-600">
                                        {{ form.errors.service_region_codes }}
                                    </p>
                                </div>

                                <!-- Company Document Uploads -->
                                <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-3.5">
                                    <p class="text-xs font-bold text-slate-700">Company Compliance Documents *</p>
                                    <p class="text-[11px] text-slate-500">PDF, JPG, or PNG up to 5MB each:</p>
                                    
                                    <div class="mt-2.5 grid gap-2.5 sm:grid-cols-2">
                                        <div
                                            v-for="doc in documents"
                                            :key="doc.name"
                                            class="rounded-xl border border-dashed border-slate-300 bg-white p-2.5 text-xs transition hover:border-[#087F8C]"
                                        >
                                            <span class="block font-bold text-slate-700 truncate">{{ doc.label }}</span>
                                            <input
                                                :id="doc.name"
                                                type="file"
                                                accept=".jpg,.jpeg,.png,.pdf"
                                                required
                                                class="mt-1.5 block w-full text-[10px] text-slate-500 file:mr-2 file:rounded-md file:border-0 file:bg-[#E8F7F6] file:px-2 file:py-1 file:text-[10px] file:font-bold file:text-[#087F8C]"
                                                @change="form[doc.name] = $event.target.files?.[0] || null"
                                            />
                                            <p v-if="form.errors[doc.name]" class="mt-1 text-[10px] font-semibold text-rose-600">
                                                {{ form.errors[doc.name] }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Terms & Privacy Checkboxes -->
                                <div class="space-y-2 text-xs text-slate-600">
                                    <label class="flex items-start gap-2">
                                        <input
                                            v-model="form.terms"
                                            type="checkbox"
                                            required
                                            class="mt-0.5 rounded border-slate-300 text-[#087F8C] focus:ring-[#087F8C]"
                                        />
                                        <span>I accept the <Link href="/policies" class="font-bold text-[#087F8C] hover:underline">Terms and Conditions</Link>.</span>
                                    </label>
                                    <label class="flex items-start gap-2">
                                        <input
                                            v-model="form.privacy"
                                            type="checkbox"
                                            required
                                            class="mt-0.5 rounded border-slate-300 text-[#087F8C] focus:ring-[#087F8C]"
                                        />
                                        <span>I accept the <Link href="/policies" class="font-bold text-[#087F8C] hover:underline">Privacy Policy</Link>.</span>
                                    </label>
                                    <p v-if="form.errors.terms || form.errors.privacy" class="text-[11px] font-semibold text-rose-600">
                                        {{ form.errors.terms || form.errors.privacy }}
                                    </p>
                                </div>

                                <!-- Step 3 Actions: SIGN UP Pill Button (Matches Image!) -->
                                <div class="flex items-center justify-between pt-2">
                                    <button
                                        type="button"
                                        @click="currentStep = 2"
                                        class="text-xs font-bold text-slate-500 hover:text-slate-800"
                                    >
                                        ← Back to Company
                                    </button>
                                    <button
                                        type="submit"
                                        :disabled="form.processing"
                                        class="rounded-full bg-[#087F8C] px-12 py-3.5 text-xs font-extrabold uppercase tracking-widest text-white shadow-md transition hover:bg-[#065F6B] hover:shadow-lg disabled:opacity-50 active:scale-95"
                                    >
                                        {{ form.processing ? 'REGISTERING...' : 'SIGN UP' }}
                                    </button>
                                </div>
                            </div>

                        </form>
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
    background-color: #F4F7F6;
    padding: 0.65rem 1rem;
    border: 1px solid transparent;
    transition: all 0.15s ease;
}

.field-container:focus-within {
    border-color: #087F8C;
    background-color: #FFFFFF;
    box-shadow: 0 0 0 3px rgba(8, 127, 140, 0.15);
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
    font-size: 0.8125rem;
    line-height: 1.25rem;
    color: #1F2937;
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
