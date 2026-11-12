<script setup>
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'
import PartnerField from '@/Components/PartnerField.vue'
import PhilippineAddressSelector from '@/Components/PhilippineAddressSelector.vue'
import DeliveryCoverageSelector from '@/Components/DeliveryCoverageSelector.vue'

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    initialRole: {
        type: String,
        default: 'logistics', // 'logistics' | 'rider'
    },
    initialTab: {
        type: String,
        default: 'signup', // 'signup' | 'login'
    },
})

const emit = defineEmits(['close'])
const page = usePage()

const activeTab = ref(props.initialTab || 'signup')
const selectedRole = ref(props.initialRole === 'rider' ? 'rider' : 'logistics')
const isRider = computed(() => selectedRole.value === 'rider')

const registrationStarted = ref(true)
const currentStep = ref(1)
const totalSteps = 4
const stepError = ref('')
const dialog = ref(null)
const showLoginPassword = ref(false)

let previouslyFocused
let previousOverflow

const trapFocus = event => {
    if (event.key !== 'Tab' || !dialog.value) return
    const elements = [...dialog.value.querySelectorAll('button:not(:disabled), a[href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled)')].filter(el => el.getClientRects().length)
    const first = elements[0]
    const last = elements.at(-1)
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault()
        last?.focus()
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault()
        first?.focus()
    }
}

/*
|--------------------------------------------------------------------------
| FORMS
|--------------------------------------------------------------------------
*/
const loginForm = useForm({
    email: '',
    password: '',
    remember: false,
})

const form = useForm({
    first_name: '',
    middle_name: '',
    last_name: '',
    email: '',
    contact_no: '',
    password: '',
    password_confirmation: '',
    birthday: '',
    // Address
    address_group: '',
    region_code: '',
    province_code: '',
    city_code: '',
    barangay_code: '',
    street: '',
    house_number: '',
    subdivision: '',
    postal_code: '',
    // Business
    business_name: '',
    business_type: '',
    authorized_representative: '',
    registration_number: '',
    business_email: '',
    business_phone: '',
    services: '',
    vehicle_types: '',
    fleet_count: '',
    operating_hours: '',
    coverage_city_codes: [],
    // Rider
    sorting_center_id: '',
    delivery_city_codes: [],
    vehicle_type: '',
    vehicle_model: '',
    plate_number: '',
    license_number: '',
    license_class: '',
    license_codes: [],
    license_expiry: '',
    // Documents
    id_doc: null,
    permit_doc: null,
    license_doc: null,
    or_cr_doc: null,
    terms: false,
    privacy: false,
})

const isMotor = computed(() => isRider.value && ['motorcycle', 'car', 'van'].includes(form.vehicle_type))

watch(() => form.vehicle_type, () => {
    for (const key of ['license_number', 'license_expiry', 'license_class', 'vehicle_model', 'plate_number']) {
        form[key] = ''
    }
    form.license_codes = []
    form.license_doc = null
    form.or_cr_doc = null
})

const businesses = [
    { value: 'individual', label: 'Individual operator' },
    { value: 'sole_proprietor', label: 'Sole proprietor' },
    { value: 'partnership', label: 'Partnership' },
    { value: 'corporation', label: 'Corporation' },
    { value: 'other', label: 'Other' },
]

const vehicles = [
    { value: 'bicycle', label: 'Bicycle' },
    { value: 'motorcycle', label: 'Motorcycle' },
    { value: 'car', label: 'Car' },
    { value: 'van', label: 'Van' },
]

const fileFields = computed(() => [
    { name: 'id_doc', label: isRider.value ? 'Valid government ID' : 'Authorized representative’s valid ID', required: true },
    ...(!isRider.value ? [{ name: 'permit_doc', label: 'Applicable DTI / SEC / business permit (PDF/Image)', required: form.business_type !== 'individual' }] : []),
    ...(isMotor.value ? [
        { name: 'license_doc', label: 'Professional driver’s license', required: true },
        { name: 'or_cr_doc', label: 'Vehicle registration (OR / CR)', required: true },
    ] : []),
])

/*
|--------------------------------------------------------------------------
| TITLES & DESCRIPTIONS
|--------------------------------------------------------------------------
*/
const stepTitle = computed(() => {
    if (currentStep.value === 1) return 'Account information'
    if (currentStep.value === 2) return isRider.value ? 'Personal & vehicle information' : 'Business information'
    if (currentStep.value === 3) return isRider.value ? 'Address & service area' : 'Operating address & coverage'
    return 'Verification documents'
})

const stepDescription = computed(() => {
    if (currentStep.value === 1) return 'Create your Alona partner account credentials.'
    if (currentStep.value === 2) return isRider.value ? 'Tell us about yourself and your delivery vehicle.' : 'Provide details about your logistics enterprise.'
    if (currentStep.value === 3) return isRider.value ? 'Specify your residence address and delivery coverage.' : 'Set your center address, operating hours, and city coverage.'
    return 'Upload official verification files and review consent agreements.'
})

/*
|--------------------------------------------------------------------------
| STEP VALIDATIONS
|--------------------------------------------------------------------------
*/
const validateCurrentStep = () => {
    stepError.value = ''

    // STEP 1: Account
    if (currentStep.value === 1) {
        if (!form.first_name.trim() || !form.last_name.trim()) {
            stepError.value = 'Please provide your full first and last name.'
            return false
        }
        if (!form.email.trim() || !form.email.includes('@')) {
            stepError.value = 'Please enter a valid email address.'
            return false
        }
        if (!form.contact_no.trim()) {
            stepError.value = 'Please enter your Philippine mobile number (e.g. 09123456789).'
            return false
        }
        if (!form.password || form.password.length < 8) {
            stepError.value = 'Password must be at least 8 characters.'
            return false
        }
        if (form.password !== form.password_confirmation) {
            stepError.value = 'Passwords do not match.'
            return false
        }
        return true
    }

    // STEP 2: Details
    if (currentStep.value === 2) {
        if (isRider.value) {
            if (!form.birthday) {
                stepError.value = 'Please enter your date of birth.'
                return false
            }
            const age = new Date().getFullYear() - new Date(form.birthday).getFullYear()
            if (age < 18) {
                stepError.value = 'You must be at least 18 years old to apply as a rider.'
                return false
            }
            if (!form.vehicle_type) {
                stepError.value = 'Please select your vehicle type.'
                return false
            }
            if (isMotor.value) {
                if (!form.vehicle_model?.trim() || !form.plate_number?.trim()) {
                    stepError.value = 'Please enter your vehicle model and plate number.'
                    return false
                }
                if (!form.license_number?.trim() || !form.license_expiry) {
                    stepError.value = 'Please enter your license number and valid expiration date.'
                    return false
                }
                if (!form.license_class) {
                    stepError.value = 'Please select your driver license classification (Professional).'
                    return false
                }
                const requiredCodes = form.vehicle_type === 'motorcycle' ? ['A', 'A1'] : ['B', 'B1', 'B2']
                const hasCode = form.license_codes.some(c => requiredCodes.includes(c))
                if (!hasCode) {
                    stepError.value = `Your license must include at least one applicable code (${requiredCodes.join('/')}).`
                    return false
                }
            }
            return true
        } else {
            // Logistics Partner
            if (!form.business_name?.trim()) {
                stepError.value = 'Please enter your business or trading name.'
                return false
            }
            if (!form.business_type) {
                stepError.value = 'Please select your business entity type.'
                return false
            }
            if (!form.authorized_representative?.trim()) {
                stepError.value = 'Please provide the name of the authorized representative.'
                return false
            }
            if (form.business_type !== 'individual' && !form.registration_number?.trim()) {
                stepError.value = 'Registration number (DTI/SEC) is required.'
                return false
            }
            if (!form.business_email?.trim() || !form.business_phone?.trim()) {
                stepError.value = 'Please enter business email and phone number.'
                return false
            }
            if (!form.services?.trim() || !form.vehicle_types?.trim() || !form.fleet_count) {
                stepError.value = 'Please complete the services, vehicle types, and fleet count fields.'
                return false
            }
            return true
        }
    }

    // STEP 3: Address & Coverage
    if (currentStep.value === 3) {
        if (!form.region_code || !form.city_code || !form.barangay_code) {
            stepError.value = 'Please select your complete Philippine address.'
            return false
        }
        if (!form.street?.trim() || !form.house_number?.trim() || !form.postal_code?.trim()) {
            stepError.value = 'Please provide your street, building/house number, and postal code.'
            return false
        }
        if (isRider.value) {
            if (!form.delivery_city_codes?.length) {
                stepError.value = 'Please select at least one delivery city you can cover.'
                return false
            }
            if (!form.sorting_center_id) {
                stepError.value = 'Please select the partner sorting center to associate with.'
                return false
            }
        } else {
            if (!form.operating_hours?.trim()) {
                stepError.value = 'Please enter your operating hours (e.g. 8:00 AM - 6:00 PM).'
                return false
            }
            if (!form.coverage_city_codes?.length) {
                stepError.value = 'Please select at least one service city covered by your center.'
                return false
            }
        }
        return true
    }

    // STEP 4: Documents & Consent
    if (currentStep.value === 4) {
        if (!form.id_doc) {
            stepError.value = 'Please upload a valid government identification document.'
            return false
        }
        if (!isRider.value && form.business_type !== 'individual' && !form.permit_doc) {
            stepError.value = 'Please upload your business permit / DTI / SEC documentation.'
            return false
        }
        if (isMotor.value) {
            if (!form.license_doc || !form.or_cr_doc) {
                stepError.value = 'Please upload both your driver’s license and vehicle OR/CR.'
                return false
            }
        }
        if (!form.terms || !form.privacy) {
            stepError.value = 'You must accept the Terms and Conditions and Privacy Policy.'
            return false
        }
        return true
    }

    return true
}

const nextStep = () => {
    if (validateCurrentStep()) {
        if (currentStep.value < totalSteps) {
            currentStep.value++
            stepError.value = ''
            dialog.value?.scrollTo({ top: 0, behavior: 'smooth' })
        }
    }
}

const previousStep = () => {
    stepError.value = ''
    if (currentStep.value > 1) {
        currentStep.value--
        dialog.value?.scrollTo({ top: 0, behavior: 'smooth' })
    } else {
        close()
    }
}

const startRegistration = () => {
    if (!isRider.value) {
        router.visit(route('register.logistics', {}, false))
        return
    }
    registrationStarted.value = true
    currentStep.value = 1
    stepError.value = ''
    dialog.value?.scrollTo({ top: 0, behavior: 'smooth' })
}

const resetAll = () => {
    registrationStarted.value = true
    currentStep.value = 1
    stepError.value = ''
    form.reset()
    form.clearErrors()
}

/*
|--------------------------------------------------------------------------
| LIFECYCLE & WATCHERS
|--------------------------------------------------------------------------
*/
watch(
    () => props.show,
    async show => {
        if (typeof document === 'undefined') return
        if (show) {
            activeTab.value = props.initialTab || 'signup'
            selectedRole.value = props.initialRole === 'rider' ? 'rider' : 'logistics'
            resetAll()
            previouslyFocused = document.activeElement
            previousOverflow = document.body.style.overflow
            document.body.style.overflow = 'hidden'
            await nextTick()
            dialog.value?.querySelector('button')?.focus()
        } else if (previousOverflow !== undefined) {
            document.body.style.overflow = previousOverflow
            previouslyFocused?.focus()
        }
    },
    { immediate: true }
)

watch(
    () => props.initialRole,
    newRole => {
        if (newRole) {
            selectedRole.value = newRole === 'rider' ? 'rider' : 'logistics'
        }
    }
)

watch(
    () => props.initialTab,
    newTab => {
        if (newTab) {
            activeTab.value = newTab
        }
    }
)

onUnmounted(() => {
    if (typeof document !== 'undefined' && previousOverflow !== undefined) {
        document.body.style.overflow = previousOverflow
    }
})

const close = () => {
    resetAll()
    emit('close')
}

const closeOnBackground = event => {
    if (event.target === event.currentTarget) {
        close()
    }
}

const submitLogin = () => {
    loginForm.post(route('login'), {
        onSuccess: () => {
            close()
        },
    })
}

const submitApplication = () => {
    if (!isRider.value) {
        router.visit(route('register.logistics', {}, false))
        return
    }
    if (!validateCurrentStep()) return

    form.post(route(isRider.value ? 'register.rider' : 'register.logistics'), {
        forceFormData: true,
        onError: errors => {
            stepError.value = 'Please correct the highlighted fields and submit again.'
            dialog.value?.scrollTo({ top: 0, behavior: 'smooth' })
        },
        onSuccess: () => {
            close()
        },
    })
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-[#1F2937]/60 p-4 sm:p-6 backdrop-blur-sm"
            @click="closeOnBackground"
        >
            <div
                ref="dialog"
                role="dialog"
                aria-modal="true"
                aria-label="Partner with Alona"
                tabindex="-1"
                class="relative max-h-[92vh] w-full max-w-4xl overflow-hidden rounded-[28px] border border-teal-900/40 bg-[#05505A] shadow-2xl flex flex-col md:flex-row"
                @keydown.esc="close"
                @keydown="trapFocus"
            >
                <!-- LEFT BRANDED PANEL (White background with logo and decorative circles matching AuthModal) -->
                <div class="relative hidden md:flex md:w-[320px] lg:w-[355px] shrink-0 flex-col justify-between overflow-hidden bg-white p-8 lg:p-10 text-slate-900 select-none">
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
                            {{
                                activeTab === 'login'
                                    ? 'Good to see you again.'
                                    : isRider
                                        ? 'Deliver with Alona.'
                                        : 'Partner with Alona.'
                            }}
                        </h3>
                        <p class="mt-3 text-sm leading-relaxed text-[#64748B]">
                            {{
                                activeTab === 'login'
                                    ? 'Your deliveries, hubs and fleet operations are right where you left them.'
                                    : isRider
                                        ? 'Earn flexible income delivering orders across your city.'
                                        : 'Join our nationwide delivery network and grow your logistics operations.'
                            }}
                        </p>
                    </div>
                </div>

                <!-- RIGHT MAIN CONTENT PANEL (Dark Teal background) -->
                <div class="relative flex-1 overflow-y-auto max-h-[92vh] bg-[#05505A] p-6 sm:p-8 lg:p-10 flex flex-col justify-between text-white">
                    <!-- CLOSE BUTTON (top right white circular button) -->
                    <button
                        type="button"
                        @click="close"
                        class="absolute right-5 top-5 z-20 flex h-8 w-8 items-center justify-center rounded-full bg-white text-slate-700 shadow-sm transition hover:bg-slate-100 hover:text-slate-900"
                        aria-label="Close"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <div>
                        <!-- ================================================= -->
                        <!-- LOGIN TAB -->
                        <!-- ================================================= -->
                        <div v-if="activeTab === 'login'">
                            <h2 class="text-2xl sm:text-3xl font-bold tracking-tight text-white">
                                Welcome back
                            </h2>
                            <p class="mt-1 text-sm text-teal-100/70">
                                Sign in to access your logistics or rider portal.
                            </p>

                            <!-- GOOGLE LOGIN BUTTON -->
                            <a
                                :href="route('google.redirect', { role: isRider ? 'rider' : 'sorting_center' })"
                                class="mt-6 flex w-full items-center justify-center gap-3 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-slate-100"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 24 24">
                                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                                </svg>
                                <span>Continue with Google</span>
                            </a>

                            <!-- DIVIDER -->
                            <div class="my-5 flex items-center gap-3 text-xs text-teal-200/60">
                                <span class="h-px flex-1 bg-white/15"></span>
                                <span>or sign in with email</span>
                                <span class="h-px flex-1 bg-white/15"></span>
                            </div>

                            <form @submit.prevent="submitLogin" class="space-y-4">
                                <!-- EMAIL -->
                                <div>
                                    <label for="partner-login-email" class="block text-sm font-semibold text-white">
                                        Partner Email address
                                    </label>
                                    <input
                                        id="partner-login-email"
                                        v-model="loginForm.email"
                                        type="email"
                                        autocomplete="email"
                                        required
                                        placeholder="partner@example.com"
                                        class="mt-1.5 block w-full rounded-xl border-0 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 outline-none shadow-inner transition focus:ring-2 focus:ring-[#F5A524]"
                                    />
                                    <InputError class="mt-1.5" :message="loginForm.errors.email || page.props.errors?.email" />
                                </div>

                                <!-- PASSWORD -->
                                <div>
                                    <div class="flex items-center justify-between">
                                        <label for="partner-login-password" class="block text-sm font-semibold text-white">
                                            Password
                                        </label>
                                        <a href="/forgot-password" class="text-xs font-semibold text-teal-300 hover:text-white hover:underline">
                                            Forgot password?
                                        </a>
                                    </div>

                                    <div class="relative mt-1.5">
                                        <input
                                            id="partner-login-password"
                                            v-model="loginForm.password"
                                            :type="showLoginPassword ? 'text' : 'password'"
                                            autocomplete="current-password"
                                            required
                                            placeholder="Enter your password"
                                            class="block w-full rounded-xl border-0 bg-white px-4 py-3 pr-11 text-sm text-slate-900 placeholder:text-slate-400 outline-none shadow-inner transition focus:ring-2 focus:ring-[#F5A524]"
                                        />
                                        <button
                                            type="button"
                                            class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-600 focus:outline-none"
                                            :aria-label="showLoginPassword ? 'Hide password' : 'Show password'"
                                            @click="showLoginPassword = !showLoginPassword"
                                        >
                                            <svg v-if="showLoginPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                            </svg>
                                            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            </svg>
                                        </button>
                                    </div>
                                    <InputError class="mt-1.5" :message="loginForm.errors.password" />
                                </div>

                                <!-- REMEMBER -->
                                <div class="flex items-center gap-2 pt-1">
                                    <input
                                        id="partner-login-remember"
                                        v-model="loginForm.remember"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-0 bg-white text-[#F5A524] focus:ring-[#F5A524] focus:ring-offset-0"
                                    />
                                    <label for="partner-login-remember" class="text-sm text-teal-100/90 select-none cursor-pointer">
                                        Remember me
                                    </label>
                                </div>

                                <!-- LOGIN SUBMIT -->
                                <button
                                    type="submit"
                                    :disabled="loginForm.processing"
                                    class="mt-2 w-full rounded-xl bg-[#F5A524] py-3.5 px-4 text-sm font-bold text-slate-900 shadow-md transition hover:bg-[#e0941d] active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    {{ loginForm.processing ? 'Signing in...' : 'Log in' }}
                                </button>
                            </form>

                            <!-- LOGIN FOOTER -->
                            <div class="mt-6 text-center text-xs text-teal-100/70">
                                Don't have a partner account yet?
                                <button
                                    type="button"
                                    @click="activeTab = 'signup'; resetAll()"
                                    class="font-semibold text-teal-300 hover:text-white hover:underline ml-1"
                                >
                                    Apply Now
                                </button>
                            </div>
                        </div>

                        <!-- ================================================= -->
                        <!-- SIGNUP / APPLY TAB -->
                        <!-- ================================================= -->
                        <div v-else class="mt-1">
                            <!-- ROLE PILL SWITCHER TABS -->
                            <div class="mb-5 flex rounded-xl bg-white/10 p-1">
                                <button
                                    type="button"
                                    :class="[
                                        'flex-1 rounded-lg py-2 text-xs sm:text-sm font-bold transition-all',
                                        !isRider
                                            ? 'bg-[#F5A524] text-slate-900 shadow-sm'
                                            : 'text-teal-100 hover:text-white'
                                    ]"
                                    @click="selectedRole = 'logistics'; currentStep = 1; stepError = ''"
                                >
                                    Logistics Partner
                                </button>
                                <button
                                    type="button"
                                    :class="[
                                        'flex-1 rounded-lg py-2 text-xs sm:text-sm font-bold transition-all',
                                        isRider
                                            ? 'bg-[#F5A524] text-slate-900 shadow-sm'
                                            : 'text-teal-100 hover:text-white'
                                    ]"
                                    @click="selectedRole = 'rider'; currentStep = 1; stepError = ''"
                                >
                                    Delivery Rider
                                </button>
                            </div>

                            <!-- PROGRESS HEADER -->
                            <div class="mb-5">
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-sm font-semibold text-teal-300">
                                        Step {{ currentStep }} of {{ totalSteps }}
                                    </span>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white/10 text-white border border-white/15">
                                        {{ isRider ? 'Delivery Rider application' : 'Logistics Partner application' }}
                                    </span>
                                </div>

                                <div class="flex gap-2">
                                    <div
                                        v-for="stepNum in totalSteps"
                                        :key="stepNum"
                                        class="h-1.5 flex-1 rounded-full transition-all duration-300"
                                        :class="stepNum <= currentStep ? 'bg-[#F5A524]' : 'bg-white/20'"
                                    ></div>
                                </div>
                            </div>

                            <!-- STEP TITLE & SUBTITLE -->
                            <div class="mb-5">
                                <h2 class="text-2xl font-bold tracking-tight text-white">
                                    {{ stepTitle }}
                                </h2>
                                <p class="mt-1 text-sm text-teal-100/70">
                                    {{ stepDescription }}
                                </p>
                            </div>

                            <!-- STEP ERROR BANNER -->
                            <div
                                v-if="stepError"
                                class="mb-4 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm leading-relaxed text-rose-800 shadow-sm"
                                role="alert"
                            >
                                {{ stepError }}
                            </div>

                            <!-- MULTI-STEP FORM -->
                            <form @submit.prevent="submitApplication" class="space-y-4">
                                <!-- STEP 1: ACCOUNT INFORMATION -->
                                <div v-show="currentStep === 1" class="space-y-4">
                                    <!-- GOOGLE BUTTON -->
                                    <a
                                        :href="route('google.redirect', { role: isRider ? 'rider' : 'sorting_center' })"
                                        class="flex w-full items-center justify-center gap-3 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-slate-100"
                                    >
                                        <svg class="h-5 w-5" viewBox="0 0 24 24">
                                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                                        </svg>
                                        <span>Apply with Google as {{ isRider ? 'Rider' : 'Partner' }}</span>
                                    </a>

                                    <div class="my-4 flex items-center gap-3 text-xs text-teal-200/60">
                                        <span class="h-px flex-1 bg-white/15"></span>
                                        <span>or use your email</span>
                                        <span class="h-px flex-1 bg-white/15"></span>
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <PartnerField name="modal_first_name" label="First name" required dark v-model="form.first_name" :error="form.errors.first_name" />
                                        <PartnerField name="modal_middle_name" label="Middle name (optional)" dark v-model="form.middle_name" :error="form.errors.middle_name" />
                                    </div>

                                    <PartnerField name="modal_last_name" label="Last name" required dark v-model="form.last_name" :error="form.errors.last_name" />
                                    <PartnerField name="modal_email" label="Email address" type="email" required dark v-model="form.email" :error="form.errors.email" />
                                    <PartnerField name="modal_contact_no" label="Philippine mobile number" type="tel" required dark v-model="form.contact_no" :error="form.errors.contact_no" />

                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <PartnerField name="modal_password" label="Password (min 8 chars)" type="password" required dark v-model="form.password" :error="form.errors.password" />
                                        <PartnerField name="modal_password_confirmation" label="Confirm password" type="password" required dark v-model="form.password_confirmation" />
                                    </div>
                                </div>

                                <!-- STEP 2: DETAILS (RIDER vs LOGISTICS) -->
                                <div v-show="currentStep === 2" class="space-y-4">
                                    <template v-if="isRider">
                                        <PartnerField name="modal_birthday" label="Date of birth (18 or older)" type="date" required dark v-model="form.birthday" :error="form.errors.birthday" />
                                        <PartnerField name="modal_vehicle_type" label="Vehicle type" :options="vehicles" required dark v-model="form.vehicle_type" :error="form.errors.vehicle_type" />

                                        <template v-if="isMotor">
                                            <div class="grid gap-3 sm:grid-cols-2">
                                                <PartnerField name="modal_vehicle_model" label="Vehicle model" required dark v-model="form.vehicle_model" :error="form.errors.vehicle_model" />
                                                <PartnerField name="modal_plate_number" label="Plate number" required dark v-model="form.plate_number" :error="form.errors.plate_number" />
                                            </div>
                                            <div class="grid gap-3 sm:grid-cols-2">
                                                <PartnerField name="modal_license_number" label="License number" required dark v-model="form.license_number" :error="form.errors.license_number" />
                                                <PartnerField name="modal_license_expiry" label="License expiration" type="date" required dark v-model="form.license_expiry" :error="form.errors.license_expiry" />
                                            </div>
                                            <PartnerField name="modal_license_class" label="License classification" :options="[{ value: 'professional', label: 'Professional' }]" required dark v-model="form.license_class" :error="form.errors.license_class" />

                                            <div>
                                                <label class="block text-sm font-semibold text-white mb-2">Approved license codes</label>
                                                <div class="flex flex-wrap gap-4">
                                                    <label v-for="code in ['A','A1','B','B1','B2']" :key="code" class="flex items-center gap-2 text-sm text-white cursor-pointer">
                                                        <input v-model="form.license_codes" type="checkbox" :value="code" class="rounded border-0 bg-white text-[#F5A524] focus:ring-[#F5A524]" />
                                                        <span>{{ code }}</span>
                                                    </label>
                                                </div>
                                                <p v-if="form.errors.license_codes" class="mt-1 text-xs text-rose-300">{{ form.errors.license_codes }}</p>
                                            </div>
                                        </template>
                                    </template>

                                    <template v-else>
                                        <PartnerField name="modal_business_name" label="Company / trade name" required dark v-model="form.business_name" :error="form.errors.business_name" />
                                        <PartnerField name="modal_business_type" label="Business entity type" :options="businesses" required dark v-model="form.business_type" :error="form.errors.business_type" />
                                        <PartnerField name="modal_authorized_representative" label="Authorized representative" required dark v-model="form.authorized_representative" :error="form.errors.authorized_representative" />
                                        <PartnerField v-if="form.business_type !== 'individual'" name="modal_registration_number" label="Registration number (DTI / SEC)" required dark v-model="form.registration_number" :error="form.errors.registration_number" />
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <PartnerField name="modal_business_email" label="Business email" type="email" required dark v-model="form.business_email" :error="form.errors.business_email" />
                                            <PartnerField name="modal_business_phone" label="Business phone number" type="tel" required dark v-model="form.business_phone" :error="form.errors.business_phone" />
                                        </div>
                                        <PartnerField name="modal_services" label="Services offered" required dark v-model="form.services" :error="form.errors.services" />
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <PartnerField name="modal_vehicle_types" label="Fleet vehicle types" required dark v-model="form.vehicle_types" :error="form.errors.vehicle_types" />
                                            <PartnerField name="modal_fleet_count" label="Fleet vehicle count" type="number" required dark v-model="form.fleet_count" :error="form.errors.fleet_count" />
                                        </div>
                                    </template>
                                </div>

                                <!-- STEP 3: ADDRESS & COVERAGE -->
                                <div v-show="currentStep === 3" class="space-y-4">
                                    <div class="rounded-2xl border border-white/15 bg-white/5 p-4 text-slate-800">
                                        <PhilippineAddressSelector :form="form" />
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <PartnerField name="modal_street" label="Street" required dark v-model="form.street" :error="form.errors.street" />
                                        <PartnerField name="modal_house_number" label="House / building / lot number" required dark v-model="form.house_number" :error="form.errors.house_number" />
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <PartnerField name="modal_subdivision" label="Subdivision (optional)" dark v-model="form.subdivision" :error="form.errors.subdivision" />
                                        <PartnerField name="modal_postal_code" label="Postal code" required dark v-model="form.postal_code" :error="form.errors.postal_code" />
                                    </div>

                                    <div class="rounded-2xl border border-white/15 bg-white/5 p-4 text-white">
                                        <DeliveryCoverageSelector :form="form" :rider="isRider" />
                                    </div>

                                    <div v-if="!isRider">
                                        <PartnerField name="modal_operating_hours" label="Operating hours (e.g. 8:00 AM - 6:00 PM)" required dark v-model="form.operating_hours" :error="form.errors.operating_hours" />
                                    </div>
                                </div>

                                <!-- STEP 4: DOCUMENTS & CONSENT -->
                                <div v-show="currentStep === 4" class="space-y-4">
                                    <p class="text-xs text-teal-100/80">
                                        Upload JPG, PNG, or PDF files (up to 5 MB each). All uploaded documents are encrypted and kept strictly confidential.
                                    </p>

                                    <div class="space-y-3">
                                        <PartnerField
                                            v-for="field in fileFields"
                                            :key="field.name"
                                            :name="'modal_' + field.name"
                                            :label="field.label"
                                            type="file"
                                            dark
                                            :required="field.required"
                                            v-model="form[field.name]"
                                            :error="form.errors[field.name]"
                                        />
                                    </div>

                                    <div class="rounded-xl border border-white/15 bg-white/10 p-4 space-y-3">
                                        <label class="flex items-start gap-3 cursor-pointer">
                                            <input v-model="form.terms" type="checkbox" required class="mt-0.5 rounded border-0 bg-white text-[#F5A524] focus:ring-[#F5A524]" />
                                            <span class="text-xs text-white">
                                                I accept the <Link href="/policies" target="_blank" class="font-semibold text-teal-300 underline hover:text-white">Terms and Conditions</Link>.
                                            </span>
                                        </label>
                                        <p v-if="form.errors.terms" class="text-xs text-rose-300">{{ form.errors.terms }}</p>

                                        <label class="flex items-start gap-3 cursor-pointer">
                                            <input v-model="form.privacy" type="checkbox" required class="mt-0.5 rounded border-0 bg-white text-[#F5A524] focus:ring-[#F5A524]" />
                                            <span class="text-xs text-white">
                                                I accept the <Link href="/policies" target="_blank" class="font-semibold text-teal-300 underline hover:text-white">Privacy Policy</Link>.
                                            </span>
                                        </label>
                                        <p v-if="form.errors.privacy" class="text-xs text-rose-300">{{ form.errors.privacy }}</p>
                                    </div>
                                </div>

                                <!-- NAVIGATION BUTTONS -->
                                <div class="mt-6 flex gap-3">
                                    <button
                                        type="button"
                                        @click="previousStep"
                                        :disabled="form.processing"
                                        class="flex-1 rounded-xl bg-white px-4 py-3.5 text-sm font-bold text-slate-800 shadow-sm transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        {{ currentStep === 1 ? 'Cancel' : 'Back' }}
                                    </button>

                                    <button
                                        v-if="currentStep < totalSteps"
                                        type="button"
                                        @click="nextStep"
                                        class="flex-1 rounded-xl bg-[#F5A524] px-4 py-3.5 text-sm font-bold text-slate-900 shadow-md transition hover:bg-[#e0941d] active:scale-[0.99]"
                                    >
                                        Next
                                    </button>

                                    <button
                                        v-else
                                        type="submit"
                                        :disabled="form.processing"
                                        class="flex-1 rounded-xl bg-[#F5A524] px-4 py-3.5 text-sm font-bold text-slate-900 shadow-md transition hover:bg-[#e0941d] active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        {{ form.processing ? 'Submitting...' : 'Submit Application' }}
                                    </button>
                                </div>
                            </form>

                            <!-- REGISTRATION FOOTER -->
                            <div class="mt-6 text-center text-xs text-teal-100/70">
                                Already have a partner account?
                                <button
                                    type="button"
                                    @click="activeTab = 'login'"
                                    class="font-semibold text-teal-300 hover:text-white hover:underline ml-1"
                                >
                                    Log in
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
