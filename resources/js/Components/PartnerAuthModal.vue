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

const registrationStarted = ref(false)
const currentStep = ref(1)
const totalSteps = 4
const stepError = ref('')
const dialog = ref(null)

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
        registrationStarted.value = false
    }
}

const startRegistration = () => {
    registrationStarted.value = true
    currentStep.value = 1
    stepError.value = ''
    dialog.value?.scrollTo({ top: 0, behavior: 'smooth' })
}

const resetAll = () => {
    registrationStarted.value = false
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
            class="fixed inset-0 z-[100] flex items-center justify-center bg-[#1F2937]/55 px-4 py-6 backdrop-blur-sm"
            @click="closeOnBackground"
        >
            <div
                ref="dialog"
                role="dialog"
                aria-modal="true"
                aria-label="Partner with Alona"
                tabindex="-1"
                class="relative max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl border border-[#E5E7EB] bg-white shadow-2xl"
                @keydown.esc="close"
                @keydown="trapFocus"
            >
                <!-- CLOSE BUTTON -->
                <button
                    type="button"
                    class="absolute right-4 top-4 z-20 flex h-9 w-9 items-center justify-center rounded-full text-[#64748B] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                    aria-label="Close"
                    @click="close"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" />
                    </svg>
                </button>

                <div class="p-6 sm:p-8">
                    <!-- HEADER -->
                    <div class="text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center overflow-hidden rounded-2xl bg-[#E8F7F6] p-2">
                            <img src="/images/Alogo.png" alt="Alona" class="h-full w-full object-contain" />
                        </div>

                        <h2 class="mt-4 text-2xl font-bold tracking-tight text-[#1F2937]">
                            {{
                                activeTab === 'login'
                                    ? 'Partner Sign In'
                                    : registrationStarted
                                        ? stepTitle
                                        : 'Partner with Alona'
                            }}
                        </h2>

                        <p class="mt-2 text-sm leading-5 text-[#64748B]">
                            {{
                                activeTab === 'login'
                                    ? 'Access your sorting center or rider operations portal.'
                                    : registrationStarted
                                        ? stepDescription
                                        : 'Choose your role to join our logistics and delivery network.'
                            }}
                        </p>
                    </div>

                    <!-- PROGRESS BAR (WHEN REGISTRATION STARTED) -->
                    <div v-if="activeTab === 'signup' && registrationStarted" class="mt-5">
                        <div class="flex items-center justify-between text-xs font-medium text-[#64748B]">
                            <span>Step {{ currentStep }} of {{ totalSteps }}</span>
                            <span class="font-semibold text-[#087F8C]">
                                {{ isRider ? 'Delivery Rider application' : 'Logistics Partner application' }}
                            </span>
                        </div>
                        <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-[#E5E7EB]">
                            <div
                                class="h-full bg-[#087F8C] transition-all duration-300"
                                :style="{ width: `${(currentStep / totalSteps) * 100}%` }"
                            ></div>
                        </div>
                    </div>

                    <!-- STEP ERROR BANNER -->
                    <div v-if="stepError" role="alert" class="mt-4 rounded-2xl bg-[#FEF2F2] px-5 py-4 text-sm leading-relaxed text-[#DC2626]">
                        {{ stepError }}
                    </div>

                    <!-- TOGGLE TAB (ONLY WHEN ON ROLE SELECTION OR LOGIN) -->
                    <div v-if="!registrationStarted" class="mt-6 flex rounded-xl bg-[#F1F5F9] p-1">
                        <button
                            type="button"
                            :class="[
                                'flex-1 rounded-lg py-2.5 text-sm font-semibold transition-all duration-200',
                                activeTab === 'signup'
                                    ? 'bg-white text-[#087F8C] shadow-sm'
                                    : 'text-[#64748B] hover:text-[#1F2937]'
                            ]"
                            @click="activeTab = 'signup'"
                        >
                            Sign Up / Apply
                        </button>
                        <button
                            type="button"
                            :class="[
                                'flex-1 rounded-lg py-2.5 text-sm font-semibold transition-all duration-200',
                                activeTab === 'login'
                                    ? 'bg-white text-[#087F8C] shadow-sm'
                                    : 'text-[#64748B] hover:text-[#1F2937]'
                            ]"
                            @click="activeTab = 'login'"
                        >
                            Log In
                        </button>
                    </div>

                    <!-- ================================================= -->
                    <!-- ROLE SELECTION (BEFORE STARTING REGISTRATION) -->
                    <!-- ================================================= -->
                    <div v-if="activeTab === 'signup' && !registrationStarted" class="mt-6">
                        <p class="mb-3 text-xs font-bold uppercase tracking-wider text-[#64748B]">
                            Select your partner role
                        </p>

                        <div class="space-y-3">
                            <!-- LOGISTICS PARTNER CARD -->
                            <button
                                type="button"
                                :aria-pressed="selectedRole === 'logistics'"
                                :class="[
                                    'group w-full rounded-2xl border p-4 text-left transition',
                                    selectedRole === 'logistics'
                                        ? 'border-[#087F8C] bg-[#E8F7F6] ring-2 ring-[#E8F7F6]'
                                        : 'border-[#E5E7EB] bg-white hover:border-[#087F8C]/40 hover:bg-[#E8F7F6]/50'
                                ]"
                                @click="selectedRole = 'logistics'"
                            >
                                <div class="flex items-center gap-4">
                                    <div
                                        :class="[
                                            'flex h-11 w-11 shrink-0 items-center justify-center rounded-xl transition',
                                            selectedRole === 'logistics'
                                                ? 'bg-[#087F8C] text-white'
                                                : 'bg-[#E8F7F6] text-[#087F8C] group-hover:bg-[#087F8C] group-hover:text-white'
                                        ]"
                                    >
                                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <h3 class="font-semibold text-[#1F2937]">
                                            Logistics Partner
                                        </h3>
                                        <p class="mt-0.5 text-xs leading-relaxed text-[#64748B]">
                                            Manage a sorting center, coverage areas, pickups, and fleet operations.
                                        </p>
                                    </div>

                                    <div
                                        :class="[
                                            'h-5 w-5 rounded-full border-2 transition flex items-center justify-center',
                                            selectedRole === 'logistics'
                                                ? 'border-[#087F8C] bg-[#087F8C]'
                                                : 'border-[#CBD5E1]'
                                        ]"
                                    >
                                        <div
                                            v-if="selectedRole === 'logistics'"
                                            class="h-1.5 w-1.5 rounded-full bg-white"
                                        ></div>
                                    </div>
                                </div>
                            </button>

                            <!-- DELIVERY RIDER CARD -->
                            <button
                                type="button"
                                :aria-pressed="selectedRole === 'rider'"
                                :class="[
                                    'group w-full rounded-2xl border p-4 text-left transition',
                                    selectedRole === 'rider'
                                        ? 'border-[#087F8C] bg-[#E8F7F6] ring-2 ring-[#E8F7F6]'
                                        : 'border-[#E5E7EB] bg-white hover:border-[#087F8C]/40 hover:bg-[#E8F7F6]/50'
                                ]"
                                @click="selectedRole = 'rider'"
                            >
                                <div class="flex items-center gap-4">
                                    <div
                                        :class="[
                                            'flex h-11 w-11 shrink-0 items-center justify-center rounded-xl transition',
                                            selectedRole === 'rider'
                                                ? 'bg-[#087F8C] text-white'
                                                : 'bg-[#E8F7F6] text-[#087F8C] group-hover:bg-[#087F8C] group-hover:text-white'
                                        ]"
                                    >
                                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <circle cx="5.5" cy="17.5" r="3.5" />
                                            <circle cx="18.5" cy="17.5" r="3.5" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 6h-3l-2.5 5.5h6L15 6zM5.5 17.5l4-6M18.5 17.5l-3-6h-3" />
                                            <circle cx="16" cy="4.5" r="1.5" />
                                        </svg>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <h3 class="font-semibold text-[#1F2937]">
                                            Delivery Rider
                                        </h3>
                                        <p class="mt-0.5 text-xs leading-relaxed text-[#64748B]">
                                            Join our delivery fleet, accept assigned orders, and deliver to buyers.
                                        </p>
                                    </div>

                                    <div
                                        :class="[
                                            'h-5 w-5 rounded-full border-2 transition flex items-center justify-center',
                                            selectedRole === 'rider'
                                                ? 'border-[#087F8C] bg-[#087F8C]'
                                                : 'border-[#CBD5E1]'
                                        ]"
                                    >
                                        <div
                                            v-if="selectedRole === 'rider'"
                                            class="h-1.5 w-1.5 rounded-full bg-white"
                                        ></div>
                                    </div>
                                </div>
                            </button>
                        </div>

                        <!-- START IN-MODAL REGISTRATION BUTTON -->
                        <button
                            type="button"
                            class="mt-6 w-full rounded-xl bg-[#087F8C] px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#066B76]"
                            @click="startRegistration"
                        >
                            Continue as {{ isRider ? 'Delivery Rider' : 'Logistics Partner' }}
                        </button>

                        <!-- GOOGLE SEPARATOR -->
                        <div class="my-5 flex items-center gap-3 text-xs text-[#94A3B8]">
                            <span class="h-px flex-1 bg-[#E5E7EB]"></span>
                            <span>or apply with</span>
                            <span class="h-px flex-1 bg-[#E5E7EB]"></span>
                        </div>

                        <!-- GOOGLE BUTTON -->
                        <a
                            :href="route('google.redirect', { role: isRider ? 'rider' : 'sorting_center' })"
                            class="flex w-full items-center justify-center gap-3 rounded-xl border border-[#E5E7EB] bg-white px-4 py-3 text-sm font-semibold text-[#1F2937] transition hover:bg-[#F8FAF9]"
                        >
                            <span class="text-base font-bold">G</span>
                            Apply with Google as {{ isRider ? 'Rider' : 'Partner' }}
                        </a>

                        <!-- SWITCH TO LOGIN -->
                        <div class="mt-5 border-t border-[#E5E7EB] pt-4 text-center">
                            <p class="text-sm text-[#64748B]">
                                Already have a partner account?
                                <button
                                    type="button"
                                    class="ml-1 font-semibold text-[#087F8C] hover:text-[#066B76]"
                                    @click="activeTab = 'login'"
                                >
                                    Log In
                                </button>
                            </p>
                        </div>
                    </div>

                    <!-- ================================================= -->
                    <!-- IN-MODAL MULTI-STEP REGISTRATION FORM -->
                    <!-- ================================================= -->
                    <form v-else-if="activeTab === 'signup' && registrationStarted" class="mt-6" @submit.prevent="submitApplication">
                        <!-- STEP 1: ACCOUNT INFORMATION -->
                        <div v-show="currentStep === 1" class="space-y-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <PartnerField name="modal_first_name" label="First name" required v-model="form.first_name" :error="form.errors.first_name" />
                                <PartnerField name="modal_middle_name" label="Middle name (optional)" v-model="form.middle_name" :error="form.errors.middle_name" />
                            </div>

                            <PartnerField name="modal_last_name" label="Last name" required v-model="form.last_name" :error="form.errors.last_name" />
                            <PartnerField name="modal_email" label="Email address" type="email" required v-model="form.email" :error="form.errors.email" />
                            <PartnerField name="modal_contact_no" label="Philippine mobile number" type="tel" required v-model="form.contact_no" :error="form.errors.contact_no" />

                            <div class="grid gap-4 sm:grid-cols-2">
                                <PartnerField name="modal_password" label="Password (min 8 chars)" type="password" required v-model="form.password" :error="form.errors.password" />
                                <PartnerField name="modal_password_confirmation" label="Confirm password" type="password" required v-model="form.password_confirmation" />
                            </div>
                        </div>

                        <!-- STEP 2: DETAILS (RIDER vs LOGISTICS) -->
                        <div v-show="currentStep === 2" class="space-y-4">
                            <!-- RIDER STEP 2 -->
                            <template v-if="isRider">
                                <PartnerField name="modal_birthday" label="Date of birth (18 or older)" type="date" required v-model="form.birthday" :error="form.errors.birthday" />
                                <PartnerField name="modal_vehicle_type" label="Vehicle type" :options="vehicles" required v-model="form.vehicle_type" :error="form.errors.vehicle_type" />

                                <template v-if="isMotor">
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <PartnerField name="modal_vehicle_model" label="Vehicle model" required v-model="form.vehicle_model" :error="form.errors.vehicle_model" />
                                        <PartnerField name="modal_plate_number" label="Plate number" required v-model="form.plate_number" :error="form.errors.plate_number" />
                                    </div>
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <PartnerField name="modal_license_number" label="License number" required v-model="form.license_number" :error="form.errors.license_number" />
                                        <PartnerField name="modal_license_expiry" label="License expiration" type="date" required v-model="form.license_expiry" :error="form.errors.license_expiry" />
                                    </div>
                                    <PartnerField name="modal_license_class" label="License classification" :options="[{ value: 'professional', label: 'Professional' }]" required v-model="form.license_class" :error="form.errors.license_class" />

                                    <div>
                                        <label class="block text-sm font-semibold text-[#1F2937] mb-2">Approved license codes</label>
                                        <div class="flex flex-wrap gap-4">
                                            <label v-for="code in ['A','A1','B','B1','B2']" :key="code" class="flex items-center gap-2 text-sm text-[#1F2937] cursor-pointer">
                                                <input v-model="form.license_codes" type="checkbox" :value="code" class="rounded text-[#087F8C] focus:ring-[#087F8C]" />
                                                <span>{{ code }}</span>
                                            </label>
                                        </div>
                                        <p v-if="form.errors.license_codes" class="mt-1 text-sm text-red-600">{{ form.errors.license_codes }}</p>
                                    </div>
                                </template>
                            </template>

                            <!-- LOGISTICS STEP 2 -->
                            <template v-else>
                                <PartnerField name="modal_business_name" label="Company / trade name" required v-model="form.business_name" :error="form.errors.business_name" />
                                <PartnerField name="modal_business_type" label="Business entity type" :options="businesses" required v-model="form.business_type" :error="form.errors.business_type" />
                                <PartnerField name="modal_authorized_representative" label="Authorized representative" required v-model="form.authorized_representative" :error="form.errors.authorized_representative" />
                                <PartnerField v-if="form.business_type !== 'individual'" name="modal_registration_number" label="Registration number (DTI / SEC)" required v-model="form.registration_number" :error="form.errors.registration_number" />
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <PartnerField name="modal_business_email" label="Business email" type="email" required v-model="form.business_email" :error="form.errors.business_email" />
                                    <PartnerField name="modal_business_phone" label="Business phone number" type="tel" required v-model="form.business_phone" :error="form.errors.business_phone" />
                                </div>
                                <PartnerField name="modal_services" label="Services offered" required v-model="form.services" :error="form.errors.services" />
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <PartnerField name="modal_vehicle_types" label="Fleet vehicle types" required v-model="form.vehicle_types" :error="form.errors.vehicle_types" />
                                    <PartnerField name="modal_fleet_count" label="Fleet vehicle count" type="number" required v-model="form.fleet_count" :error="form.errors.fleet_count" />
                                </div>
                            </template>
                        </div>

                        <!-- STEP 3: ADDRESS & COVERAGE -->
                        <div v-show="currentStep === 3" class="space-y-4">
                            <PhilippineAddressSelector :form="form" />

                            <div class="grid gap-4 sm:grid-cols-2">
                                <PartnerField name="modal_street" label="Street" required v-model="form.street" :error="form.errors.street" />
                                <PartnerField name="modal_house_number" label="House / building / lot number" required v-model="form.house_number" :error="form.errors.house_number" />
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <PartnerField name="modal_subdivision" label="Subdivision (optional)" v-model="form.subdivision" :error="form.errors.subdivision" />
                                <PartnerField name="modal_postal_code" label="Postal code" required v-model="form.postal_code" :error="form.errors.postal_code" />
                            </div>

                            <div class="border-t border-[#E5E7EB] pt-4">
                                <DeliveryCoverageSelector :form="form" :rider="isRider" />
                            </div>

                            <div v-if="!isRider">
                                <PartnerField name="modal_operating_hours" label="Operating hours (e.g. 8:00 AM - 6:00 PM)" required v-model="form.operating_hours" :error="form.errors.operating_hours" />
                            </div>
                        </div>

                        <!-- STEP 4: DOCUMENTS & CONSENT -->
                        <div v-show="currentStep === 4" class="space-y-5">
                            <p class="text-xs text-[#64748B]">
                                Upload JPG, PNG, or PDF files (up to 5 MB each). All uploaded documents are encrypted and kept strictly confidential.
                            </p>

                            <div class="space-y-4">
                                <PartnerField
                                    v-for="field in fileFields"
                                    :key="field.name"
                                    :name="'modal_' + field.name"
                                    :label="field.label"
                                    type="file"
                                    :required="field.required"
                                    v-model="form[field.name]"
                                    :error="form.errors[field.name]"
                                />
                            </div>

                            <div class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-4 space-y-3">
                                <label class="flex items-start gap-3 cursor-pointer">
                                    <input v-model="form.terms" type="checkbox" required class="mt-0.5 rounded text-[#087F8C] focus:ring-[#087F8C]" />
                                    <span class="text-xs text-[#1F2937]">
                                        I accept the <Link href="/policies" target="_blank" class="font-semibold text-[#087F8C] underline">Terms and Conditions</Link>.
                                    </span>
                                </label>
                                <p v-if="form.errors.terms" class="text-xs text-red-600">{{ form.errors.terms }}</p>

                                <label class="flex items-start gap-3 cursor-pointer">
                                    <input v-model="form.privacy" type="checkbox" required class="mt-0.5 rounded text-[#087F8C] focus:ring-[#087F8C]" />
                                    <span class="text-xs text-[#1F2937]">
                                        I accept the <Link href="/policies" target="_blank" class="font-semibold text-[#087F8C] underline">Privacy Policy</Link>.
                                    </span>
                                </label>
                                <p v-if="form.errors.privacy" class="text-xs text-red-600">{{ form.errors.privacy }}</p>
                            </div>
                        </div>

                        <!-- STEP ACTION BUTTONS -->
                        <div class="mt-6 flex items-center justify-between gap-3">
                            <button
                                type="button"
                                class="rounded-xl border border-[#E5E7EB] px-4 py-2.5 text-sm font-semibold text-[#64748B] hover:bg-[#F8FAF9] transition"
                                @click="previousStep"
                            >
                                {{ currentStep === 1 ? 'Cancel' : 'Back' }}
                            </button>

                            <button
                                v-if="currentStep < totalSteps"
                                type="button"
                                class="rounded-xl bg-[#087F8C] px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#066B76] transition"
                                @click="nextStep"
                            >
                                Continue
                            </button>

                            <button
                                v-else
                                type="submit"
                                :disabled="form.processing"
                                class="rounded-xl bg-[#087F8C] px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-[#066B76] transition disabled:opacity-50"
                            >
                                {{ form.processing ? 'Submitting Application...' : 'Submit Application' }}
                            </button>
                        </div>

                        <!-- RESET ROLE BUTTON -->
                        <div class="mt-4 text-center">
                            <button
                                type="button"
                                class="text-xs font-medium text-[#64748B] hover:text-[#087F8C] transition"
                                @click="resetAll"
                            >
                                Switch partner role
                            </button>
                        </div>
                    </form>

                    <!-- ================================================= -->
                    <!-- LOG IN TAB -->
                    <!-- ================================================= -->
                    <form v-else-if="activeTab === 'login'" class="mt-6" @submit.prevent="submitLogin">
                        <div>
                            <label for="partner-login-email" class="text-sm font-medium text-[#1F2937]">
                                Partner Email address
                            </label>

                            <input
                                id="partner-login-email"
                                v-model="loginForm.email"
                                type="email"
                                autocomplete="email"
                                required
                                placeholder="partner@example.com"
                                class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                            />

                            <InputError class="mt-2" :message="loginForm.errors.email || page.props.errors?.email" />
                        </div>

                        <div class="mt-4">
                            <div class="flex items-center justify-between">
                                <label for="partner-login-password" class="text-sm font-medium text-[#1F2937]">
                                    Password
                                </label>
                            </div>

                            <input
                                id="partner-login-password"
                                v-model="loginForm.password"
                                type="password"
                                autocomplete="current-password"
                                required
                                placeholder="Enter your password"
                                class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                            />

                            <InputError class="mt-2" :message="loginForm.errors.password" />
                        </div>

                        <div class="mt-4 flex items-center justify-between">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    v-model="loginForm.remember"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-[#CBD5E1] text-[#087F8C] focus:ring-[#087F8C]"
                                />
                                <span class="text-sm text-[#64748B]">Remember me</span>
                            </label>
                        </div>

                        <button
                            type="submit"
                            :disabled="loginForm.processing"
                            class="mt-6 w-full rounded-xl bg-[#087F8C] px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#066B76] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ loginForm.processing ? 'Signing in...' : 'Sign In to Portal' }}
                        </button>

                        <div class="mt-5 border-t border-[#E5E7EB] pt-4 text-center">
                            <p class="text-sm text-[#64748B]">
                                Don't have a partner account yet?
                                <button
                                    type="button"
                                    class="ml-1 font-semibold text-[#087F8C] hover:text-[#066B76]"
                                    @click="activeTab = 'signup'; resetAll()"
                                >
                                    Apply Now
                                </button>
                            </p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </Teleport>
</template>
