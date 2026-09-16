<script setup>
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },

    mode: {
        type: String,
        default: 'login',
    },

    canResetPassword: {
        type: Boolean,
        default: true,
    },
})

const emit = defineEmits(['close', 'update:mode'])

/*
|--------------------------------------------------------------------------
| LOGIN
|--------------------------------------------------------------------------
*/

const loginForm = useForm({
    email: '',
    password: '',
    remember: false,
})

/*
|--------------------------------------------------------------------------
| REGISTRATION
|--------------------------------------------------------------------------
*/

const registrationType = ref(null)
const registrationStarted = ref(false)
const registrationStep = ref(1)
const stepError = ref('')
const registrationSuccess = ref('')

const registerForm = useForm({
    /*
    |--------------------------------------------------------------------------
    | Personal Information
    |--------------------------------------------------------------------------
    */

    last_name: '',
    first_name: '',
    middle_initial: '',

    sex: '',
    email: '',
    contact_no: '',
    birthday: '',
    age: '',

    /*
    |--------------------------------------------------------------------------
    | Address
    |--------------------------------------------------------------------------
    */

    province: '',
    municipality: '',
    barangay: '',
    street_address: '',

    /*
    |--------------------------------------------------------------------------
    | Seller Information
    |--------------------------------------------------------------------------
    */

    store_name: '',
    line_of_business: '',
    store_description: '',

    /*
    |--------------------------------------------------------------------------
    | Seller Payment Information
    |--------------------------------------------------------------------------
    */

    bank_name: '',
    bank_account_name: '',
    bank_account_number: '',

    /*
    |--------------------------------------------------------------------------
    | Documents
    |--------------------------------------------------------------------------
    */

    id: null,
    business_permit: null,

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    password: '',
    password_confirmation: '',
})

/*
|--------------------------------------------------------------------------
| PHILIPPINE ADDRESS DATA
|--------------------------------------------------------------------------
*/

const provinces = ref([])
const municipalities = ref([])
const barangays = ref([])

const selectedProvinceCode = ref('')
const selectedMunicipalityCode = ref('')

const addressLoading = ref(false)
const municipalityLoading = ref(false)
const barangayLoading = ref(false)

const addressError = ref('')

/*
|--------------------------------------------------------------------------
| GENERIC API HELPER
|--------------------------------------------------------------------------
*/

const fetchJson = async (url) => {
    const response = await fetch(url, {
        method: 'GET',

        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },

        credentials: 'same-origin',
    })

    let data = null

    try {
        data = await response.json()
    } catch {
        data = null
    }

    if (!response.ok) {
        const message =
            data?.message ||
            data?.error ||
            `HTTP ${response.status}`

        throw new Error(message)
    }

    return data
}

/*
|--------------------------------------------------------------------------
| NORMALIZE API RESPONSE
|--------------------------------------------------------------------------
*/

const normalizeList = (data) => {
    if (Array.isArray(data)) {
        return data
    }

    if (Array.isArray(data?.data)) {
        return data.data
    }

    if (Array.isArray(data?.results)) {
        return data.results
    }

    return []
}

/*
|--------------------------------------------------------------------------
| LOAD PROVINCES
|--------------------------------------------------------------------------
*/

const loadProvinces = async () => {
    if (addressLoading.value) {
        return
    }

    addressLoading.value = true
    addressError.value = ''

    try {
        const data = await fetchJson(
            '/api/philippines/provinces'
        )

        const list = normalizeList(data)

        provinces.value = list

        if (list.length === 0) {
            throw new Error(
                'No provinces were returned by the server.'
            )
        }
    } catch (error) {
        console.error(
            'Province API error:',
            error
        )

        provinces.value = []

        addressError.value =
            'Unable to load Philippine provinces. Please try again.'
    } finally {
        addressLoading.value = false
    }
}

/*
|--------------------------------------------------------------------------
| PROVINCE CHANGE
|--------------------------------------------------------------------------
*/

const handleProvinceChange = async () => {
    const provinceCode =
        selectedProvinceCode.value

    const selectedProvince =
        provinces.value.find(
            item =>
                String(item.code) ===
                String(provinceCode)
        )

    registerForm.province =
        selectedProvince?.name || ''

    registerForm.municipality = ''
    registerForm.barangay = ''

    selectedMunicipalityCode.value = ''

    municipalities.value = []
    barangays.value = []

    addressError.value = ''

    if (!selectedProvince) {
        return
    }

    await loadMunicipalities(
        selectedProvince.code
    )
}

/*
|--------------------------------------------------------------------------
| LOAD MUNICIPALITIES / CITIES
|--------------------------------------------------------------------------
*/

const loadMunicipalities = async (
    provinceCode
) => {
    if (!provinceCode) {
        return
    }

    municipalityLoading.value = true
    addressError.value = ''

    try {
        const url =
            `/api/philippines/provinces/${encodeURIComponent(
                provinceCode
            )}/cities-municipalities`

        const data =
            await fetchJson(url)

        const list =
            normalizeList(data)

        municipalities.value = list

        if (list.length === 0) {
            addressError.value =
                'No municipalities or cities were found for the selected province.'
        }
    } catch (error) {
        console.error(
            'Municipality API error:',
            error
        )

        municipalities.value = []

        addressError.value =
            'Unable to load municipalities/cities. Please try again.'
    } finally {
        municipalityLoading.value = false
    }
}

/*
|--------------------------------------------------------------------------
| MUNICIPALITY / CITY CHANGE
|--------------------------------------------------------------------------
*/

const handleMunicipalityChange = async () => {
    const municipalityCode =
        selectedMunicipalityCode.value

    const selectedMunicipality =
        municipalities.value.find(
            item =>
                String(item.code) ===
                String(municipalityCode)
        )

    registerForm.municipality =
        selectedMunicipality?.name || ''

    registerForm.barangay = ''

    barangays.value = []

    addressError.value = ''

    if (!selectedMunicipality) {
        return
    }

    await loadBarangays(
        selectedMunicipality.code
    )
}

/*
|--------------------------------------------------------------------------
| LOAD BARANGAYS
|--------------------------------------------------------------------------
*/

const loadBarangays = async (
    municipalityCode
) => {
    if (!municipalityCode) {
        return
    }

    barangayLoading.value = true
    addressError.value = ''

    try {
        const url =
            `/api/philippines/cities-municipalities/${encodeURIComponent(
                municipalityCode
            )}/barangays`

        const data =
            await fetchJson(url)

        const list =
            normalizeList(data)

        barangays.value = list

        if (list.length === 0) {
            addressError.value =
                'No barangays were found for the selected municipality/city.'
        }
    } catch (error) {
        console.error(
            'Barangay API error:',
            error
        )

        barangays.value = []

        addressError.value =
            'Unable to load barangays. Please try again.'
    } finally {
        barangayLoading.value = false
    }
}

/*
|--------------------------------------------------------------------------
| BARANGAY CHANGE
|--------------------------------------------------------------------------
*/

const handleBarangayChange = () => {
    const selectedBarangay =
        barangays.value.find(
            item =>
                String(item.name) ===
                String(registerForm.barangay)
        )

    if (selectedBarangay) {
        registerForm.barangay =
            selectedBarangay.name
    }
}

/*
|--------------------------------------------------------------------------
| COMPUTED
|--------------------------------------------------------------------------
*/

const isSeller = computed(() => {
    return registrationType.value === 'seller'
})

const totalSteps = computed(() => 4)

const stepTitle = computed(() => {
    if (registrationStep.value === 1) {
        return 'Account information'
    }

    if (registrationStep.value === 2) {
        return 'Personal information'
    }

    if (registrationStep.value === 3) {
        return 'Address'
    }

    return isSeller.value
        ? 'Store information'
        : 'Verification'
})

const stepDescription = computed(() => {
    if (registrationStep.value === 1) {
        return 'Create your Alona account credentials.'
    }

    if (registrationStep.value === 2) {
        return 'Tell us a little about yourself.'
    }

    if (registrationStep.value === 3) {
        return 'Enter your complete delivery address.'
    }

    return isSeller.value
        ? 'Provide your store and verification information.'
        : 'Upload an identification document to complete registration.'
})

/*
|--------------------------------------------------------------------------
| CLOSE / SWITCH
|--------------------------------------------------------------------------
*/

const close = () => {
    emit('close')
}

const switchToLogin = () => {
    resetRegistration()
    emit('update:mode', 'login')
}

const switchToRegister = () => {
    emit('update:mode', 'register')
}

/*
|--------------------------------------------------------------------------
| RESET REGISTRATION
|--------------------------------------------------------------------------
*/

const resetRegistration = () => {
    registrationType.value = null
    registrationStarted.value = false
    registrationStep.value = 1

    stepError.value = ''
    registrationSuccess.value = ''
    addressError.value = ''

    selectedProvinceCode.value = ''
    selectedMunicipalityCode.value = ''

    municipalities.value = []
    barangays.value = []

    registerForm.reset()
    registerForm.clearErrors()
}

/*
|--------------------------------------------------------------------------
| ACCOUNT TYPE
|--------------------------------------------------------------------------
*/

const selectRegistrationType = (type) => {
    registrationType.value = type
    stepError.value = ''
    registrationSuccess.value = ''
}

const startRegistration = () => {
    if (!registrationType.value) {
        stepError.value =
            'Please choose whether you want to register as a buyer or seller.'

        return
    }

    stepError.value = ''
    registrationSuccess.value = ''
    registrationStarted.value = true
    registrationStep.value = 1
}

/*
|--------------------------------------------------------------------------
| AGE CALCULATION
|--------------------------------------------------------------------------
*/

const calculateAge = (birthday) => {
    if (!birthday) {
        return ''
    }

    const birthDate =
        new Date(`${birthday}T00:00:00`)

    const today = new Date()

    if (
        Number.isNaN(
            birthDate.getTime()
        )
    ) {
        return ''
    }

    let age =
        today.getFullYear() -
        birthDate.getFullYear()

    const monthDifference =
        today.getMonth() -
        birthDate.getMonth()

    if (
        monthDifference < 0 ||
        (
            monthDifference === 0 &&
            today.getDate() <
                birthDate.getDate()
        )
    ) {
        age--
    }

    return age >= 0 ? age : ''
}

watch(
    () => registerForm.birthday,
    (birthday) => {
        registerForm.age =
            calculateAge(birthday)
    }
)

/*
|--------------------------------------------------------------------------
| STEP VALIDATION
|--------------------------------------------------------------------------
*/

const validateCurrentStep = () => {
    stepError.value = ''

    /*
    |--------------------------------------------------------------------------
    | STEP 1
    |--------------------------------------------------------------------------
    */

    if (registrationStep.value === 1) {
        if (
            !registerForm.first_name.trim()
        ) {
            stepError.value =
                'Please enter your first name.'

            return false
        }

        if (
            !registerForm.last_name.trim()
        ) {
            stepError.value =
                'Please enter your last name.'

            return false
        }

        if (
            !registerForm.email.trim()
        ) {
            stepError.value =
                'Please enter your email address.'

            return false
        }

        const emailPattern =
            /^[^\s@]+@[^\s@]+\.[^\s@]+$/

        if (
            !emailPattern.test(
                registerForm.email
            )
        ) {
            stepError.value =
                'Please enter a valid email address.'

            return false
        }

        if (
            !registerForm.password
        ) {
            stepError.value =
                'Please create a password.'

            return false
        }

        if (
            registerForm.password.length < 8
        ) {
            stepError.value =
                'Your password must be at least 8 characters.'

            return false
        }

        if (
            !registerForm.password_confirmation
        ) {
            stepError.value =
                'Please confirm your password.'

            return false
        }

        if (
            registerForm.password !==
            registerForm.password_confirmation
        ) {
            stepError.value =
                'The passwords do not match.'

            return false
        }
    }

    /*
    |--------------------------------------------------------------------------
    | STEP 2
    |--------------------------------------------------------------------------
    */

    if (registrationStep.value === 2) {
        if (!registerForm.sex) {
            stepError.value =
                'Please select your sex.'

            return false
        }

        if (!registerForm.birthday) {
            stepError.value =
                'Please enter your birthday.'

            return false
        }

        if (
            !registerForm.age ||
            Number(registerForm.age) < 1
        ) {
            stepError.value =
                'Please enter a valid birthday.'

            return false
        }

        if (
            !registerForm.contact_no.trim()
        ) {
            stepError.value =
                'Please enter your contact number.'

            return false
        }
    }

    /*
    |--------------------------------------------------------------------------
    | STEP 3
    |--------------------------------------------------------------------------
    */

    if (registrationStep.value === 3) {
        if (!registerForm.province) {
            stepError.value =
                'Please select your province.'

            return false
        }

        if (
            !selectedProvinceCode.value
        ) {
            stepError.value =
                'Please select a valid province.'

            return false
        }

        if (!registerForm.municipality) {
            stepError.value =
                'Please select your municipality or city.'

            return false
        }

        if (
            !selectedMunicipalityCode.value
        ) {
            stepError.value =
                'Please select a valid municipality or city.'

            return false
        }

        if (!registerForm.barangay) {
            stepError.value =
                'Please select your barangay.'

            return false
        }

        if (
            !registerForm.street_address.trim()
        ) {
            stepError.value =
                'Please enter your house number and street address.'

            return false
        }
    }

    /*
    |--------------------------------------------------------------------------
    | STEP 4 SELLER
    |--------------------------------------------------------------------------
    */

    if (
        registrationStep.value === 4 &&
        isSeller.value
    ) {
        if (
            !registerForm.store_name.trim()
        ) {
            stepError.value =
                'Please enter your store name.'

            return false
        }

        if (
            !registerForm.line_of_business.trim()
        ) {
            stepError.value =
                'Please enter your line of business.'

            return false
        }

        if (!registerForm.id) {
            stepError.value =
                'Please upload a valid ID.'

            return false
        }

        if (!registerForm.business_permit) {
            stepError.value =
                'Please upload your business permit.'

            return false
        }
    }

    /*
    |--------------------------------------------------------------------------
    | STEP 4 BUYER
    |--------------------------------------------------------------------------
    */

    if (
        registrationStep.value === 4 &&
        !isSeller.value
    ) {
        return true
    }

    return true
}

/*
|--------------------------------------------------------------------------
| NEXT STEP
|--------------------------------------------------------------------------
*/

const nextStep = () => {
    if (!validateCurrentStep()) {
        return
    }

    if (
        registrationStep.value <
        totalSteps.value
    ) {
        registrationStep.value++

        stepError.value = ''
    }
}

/*
|--------------------------------------------------------------------------
| PREVIOUS STEP
|--------------------------------------------------------------------------
*/

const previousStep = () => {
    stepError.value = ''

    if (
        registrationStep.value > 1
    ) {
        registrationStep.value--
    }
}

/*
|--------------------------------------------------------------------------
| ID UPLOAD
|--------------------------------------------------------------------------
*/

const handleIdUpload = (event) => {
    const file =
        event.target.files?.[0] ?? null

    registerForm.id = file

    if (file) {
        registerForm.clearErrors('id')
        stepError.value = ''
    }
}

/*
|--------------------------------------------------------------------------
| BUSINESS PERMIT UPLOAD
|--------------------------------------------------------------------------
*/

const handleBusinessPermitUpload = (event) => {
    const file =
        event.target.files?.[0] ?? null

    registerForm.business_permit = file

    if (file) {
        registerForm.clearErrors(
            'business_permit'
        )

        stepError.value = ''
    }
}

/*
|--------------------------------------------------------------------------
| LOGIN SUBMIT
|--------------------------------------------------------------------------
*/

const submitLogin = () => {
    loginForm.post(
        route('login'),
        {
            onFinish: () => {
                loginForm.reset('password')
            },
        }
    )
}

/*
|--------------------------------------------------------------------------
| REGISTRATION SUBMIT
|--------------------------------------------------------------------------
*/

const submitRegister = () => {
    if (!validateCurrentStep()) {
        return
    }

    stepError.value = ''
    registrationSuccess.value = ''

    const registrationRoute =
        isSeller.value
            ? route('register.seller')
            : route('register')

    registerForm.post(
        registrationRoute,
        {
            forceFormData: true,

            onStart: () => {
                stepError.value = ''
                registrationSuccess.value = ''
            },

            onError: (errors) => {
                console.error(
                    'Registration validation errors:',
                    errors
                )

                stepError.value =
                    'Please correct the highlighted fields and try again.'
            },

            onSuccess: () => {
                registrationSuccess.value =
                    isSeller.value
                        ? 'Seller application submitted successfully.'
                        : 'Registration submitted successfully.'
            },

            onFinish: () => {
                console.log(
                    'Registration request finished.'
                )
            },
        }
    )
}

/*
|--------------------------------------------------------------------------
| BACKGROUND CLOSE
|--------------------------------------------------------------------------
*/

const closeOnBackground = (event) => {
    if (
        event.target ===
        event.currentTarget
    ) {
        close()
    }
}

/*
|--------------------------------------------------------------------------
| LOAD ADDRESS DATA WHEN MODAL OPENS
|--------------------------------------------------------------------------
*/

watch(
    () => props.show,
    (show) => {
        if (
            show &&
            provinces.value.length === 0 &&
            !addressLoading.value
        ) {
            loadProvinces()
        }
    },
    {
        immediate: true,
    }
)
</script>

<template>
    <Teleport to="body">
        <div
            v-if="show"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-[#1F2937]/55 px-4 py-6 backdrop-blur-sm"
            @click="closeOnBackground"
        >
            <div
                class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-[#E5E7EB] bg-white shadow-2xl"
            >

                <!-- CLOSE -->

                <button
                    type="button"
                    @click="close"
                    class="absolute right-4 top-4 z-20 flex h-9 w-9 items-center justify-center rounded-full text-[#64748B] transition hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                    aria-label="Close"
                >
                    <svg
                        class="h-5 w-5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M6 6l12 12M18 6L6 18"
                        />
                    </svg>
                </button>

                <div class="p-6 sm:p-8">

                    <!-- HEADER -->

                    <div class="text-center">

                        <!-- ALONA LOGO -->

                        <div
                            class="mx-auto flex h-14 w-14 items-center justify-center overflow-hidden rounded-2xl bg-[#E8F7F6] p-2"
                        >
                            <img
                                src="/images/Alogo.png"
                                alt="Alona"
                                class="h-full w-full object-contain"
                            />
                        </div>

                        <h2
                            class="mt-4 text-2xl font-bold tracking-tight text-[#1F2937]"
                        >
                            {{
                                mode === 'login'
                                    ? 'Welcome back'
                                    : registrationStarted
                                        ? stepTitle
                                        : 'Create your account'
                            }}
                        </h2>

                        <p
                            class="mt-2 text-sm leading-5 text-[#64748B]"
                        >
                            {{
                                mode === 'login'
                                    ? 'Sign in to continue shopping with Alona.'
                                    : registrationStarted
                                        ? stepDescription
                                        : 'Choose how you want to join Alona.'
                            }}
                        </p>

                    </div>

                    <!-- ================================================= -->
                    <!-- LOGIN -->
                    <!-- ================================================= -->

                    <form
                        v-if="mode === 'login'"
                        class="mt-7"
                        @submit.prevent="submitLogin"
                    >

                        <!-- EMAIL -->

                        <div>

                            <label
                                for="login-email"
                                class="text-sm font-medium text-[#1F2937]"
                            >
                                Email address
                            </label>

                            <input
                                id="login-email"
                                v-model="loginForm.email"
                                type="email"
                                autocomplete="email"
                                required
                                placeholder="you@example.com"
                                class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                            />

                            <InputError
                                class="mt-2"
                                :message="loginForm.errors.email"
                            />

                        </div>

                        <!-- PASSWORD -->

                        <div class="mt-5">

                            <div
                                class="flex items-center justify-between"
                            >

                                <label
                                    for="login-password"
                                    class="text-sm font-medium text-[#1F2937]"
                                >
                                    Password
                                </label>

                                <a
                                    v-if="props.canResetPassword"
                                    :href="route('password.request')"
                                    class="text-xs font-semibold text-[#087F8C] hover:text-[#16A6A0]"
                                >
                                    Forgot password?
                                </a>

                            </div>

                            <input
                                id="login-password"
                                v-model="loginForm.password"
                                type="password"
                                autocomplete="current-password"
                                required
                                placeholder="Enter your password"
                                class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                            />

                            <InputError
                                class="mt-2"
                                :message="loginForm.errors.password"
                            />

                        </div>

                        <!-- REMEMBER -->

                        <label
                            class="mt-5 flex items-center gap-2"
                        >

                            <input
                                v-model="loginForm.remember"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-[#087F8C] focus:ring-[#087F8C]"
                            />

                            <span
                                class="text-sm text-[#64748B]"
                            >
                                Remember me
                            </span>

                        </label>

                        <!-- LOGIN -->

                        <button
                            type="submit"
                            :disabled="loginForm.processing"
                            class="mt-6 w-full rounded-xl bg-[#087F8C] px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#16A6A0] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{
                                loginForm.processing
                                    ? 'Signing in...'
                                    : 'Log in'
                            }}
                        </button>

                    </form>

                    <!-- ================================================= -->
                    <!-- REGISTER -->
                    <!-- ================================================= -->

                    <div
                        v-else
                        class="mt-7"
                    >

                        <!-- ACCOUNT TYPE -->

                        <div
                            v-if="!registrationStarted"
                        >

                            <div class="space-y-4">

                                <!-- BUYER -->

                                <button
                                    type="button"
                                    @click="selectRegistrationType('buyer')"
                                    :class="[
                                        'group w-full rounded-2xl border p-5 text-left transition',
                                        registrationType === 'buyer'
                                            ? 'border-[#087F8C] bg-[#E8F7F6] ring-2 ring-[#E8F7F6]'
                                            : 'border-[#E5E7EB] bg-white hover:border-[#087F8C]/40 hover:bg-[#E8F7F6]/50'
                                    ]"
                                >

                                    <div
                                        class="flex items-center gap-4"
                                    >

                                        <div
                                            :class="[
                                                'flex h-11 w-11 shrink-0 items-center justify-center rounded-xl transition',
                                                registrationType === 'buyer'
                                                    ? 'bg-[#087F8C] text-white'
                                                    : 'bg-[#E8F7F6] text-[#087F8C] group-hover:bg-[#087F8C] group-hover:text-white'
                                            ]"
                                        >

                                            <svg
                                                class="h-5 w-5"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M20 21a8 8 0 0 0-16 0"
                                                />

                                                <circle
                                                    cx="12"
                                                    cy="7"
                                                    r="4"
                                                />
                                            </svg>

                                        </div>

                                        <div
                                            class="min-w-0 flex-1"
                                        >

                                            <h3
                                                class="font-semibold text-[#1F2937]"
                                            >
                                                Register as Buyer
                                            </h3>

                                            <p
                                                class="mt-1 text-sm text-[#64748B]"
                                            >
                                                Create an account to browse and purchase products.
                                            </p>

                                        </div>

                                        <div
                                            :class="[
                                                'h-5 w-5 rounded-full border-2',
                                                registrationType === 'buyer'
                                                    ? 'border-[#087F8C] bg-[#087F8C]'
                                                    : 'border-[#CBD5E1]'
                                            ]"
                                        >

                                            <div
                                                v-if="registrationType === 'buyer'"
                                                class="m-1 h-1.5 w-1.5 rounded-full bg-white"
                                            ></div>

                                        </div>

                                    </div>

                                </button>

                                <!-- SELLER -->

                                <button
                                    type="button"
                                    @click="selectRegistrationType('seller')"
                                    :class="[
                                        'group w-full rounded-2xl border p-5 text-left transition',
                                        registrationType === 'seller'
                                            ? 'border-[#16A6A0] bg-[#E8F7F6] ring-2 ring-[#E8F7F6]'
                                            : 'border-[#E5E7EB] bg-white hover:border-[#16A6A0]/40 hover:bg-[#E8F7F6]/50'
                                    ]"
                                >

                                    <div
                                        class="flex items-center gap-4"
                                    >

                                        <div
                                            :class="[
                                                'flex h-11 w-11 shrink-0 items-center justify-center rounded-xl transition',
                                                registrationType === 'seller'
                                                    ? 'bg-[#16A6A0] text-white'
                                                    : 'bg-[#E8F7F6] text-[#16A6A0] group-hover:bg-[#16A6A0] group-hover:text-white'
                                            ]"
                                        >

                                            <svg
                                                class="h-5 w-5"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M3 10h18"
                                                />

                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M5 10v9h14v-9"
                                                />

                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M4 10 6 4h12l2 6"
                                                />

                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M9 19v-5h6v5"
                                                />
                                            </svg>

                                        </div>

                                        <div
                                            class="min-w-0 flex-1"
                                        >

                                            <h3
                                                class="font-semibold text-[#1F2937]"
                                            >
                                                Register as Seller
                                            </h3>

                                            <p
                                                class="mt-1 text-sm text-[#64748B]"
                                            >
                                                Apply to open your own store and sell products.
                                            </p>

                                        </div>

                                        <div
                                            :class="[
                                                'h-5 w-5 rounded-full border-2',
                                                registrationType === 'seller'
                                                    ? 'border-[#16A6A0] bg-[#16A6A0]'
                                                    : 'border-[#CBD5E1]'
                                            ]"
                                        >

                                            <div
                                                v-if="registrationType === 'seller'"
                                                class="m-1 h-1.5 w-1.5 rounded-full bg-white"
                                            ></div>

                                        </div>

                                    </div>

                                </button>

                            </div>

                            <!-- ERROR -->

                            <div
                                v-if="stepError"
                                class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-600"
                            >
                                {{ stepError }}
                            </div>

                            <!-- ADDRESS ERROR -->

                            <div
                                v-if="addressError"
                                class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-600"
                            >
                                {{ addressError }}
                            </div>

                            <!-- NEXT -->

                            <button
                                type="button"
                                @click="startRegistration"
                                class="mt-5 w-full rounded-xl bg-[#087F8C] px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#16A6A0]"
                            >
                                Next
                            </button>

                        </div>

                        <!-- ================================================= -->
                        <!-- REGISTRATION STEPS -->
                        <!-- ================================================= -->

                        <div v-else>

                            <!-- PROGRESS -->

                            <div class="mb-7">

                                <div
                                    class="flex items-center justify-between text-xs font-medium text-[#64748B]"
                                >

                                    <span>
                                        Step
                                        {{ registrationStep }}
                                        of
                                        {{ totalSteps }}
                                    </span>

                                    <span>
                                        {{
                                            isSeller
                                                ? 'Seller application'
                                                : 'Buyer registration'
                                        }}
                                    </span>

                                </div>

                                <div
                                    class="mt-3 h-2 overflow-hidden rounded-full bg-[#E5E7EB]"
                                >

                                    <div
                                        class="h-full rounded-full bg-[#087F8C] transition-all duration-300"
                                        :style="{
                                            width: `${(registrationStep / totalSteps) * 100}%`
                                        }"
                                    ></div>

                                </div>

                            </div>

                            <!-- ================================================= -->
                            <!-- STEP 1 -->
                            <!-- ================================================= -->

                            <div
                                v-if="registrationStep === 1"
                                class="space-y-5"
                            >

                                <!-- FIRST NAME -->

                                <div>

                                    <label
                                        for="register-first-name"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        First name
                                    </label>

                                    <input
                                        id="register-first-name"
                                        v-model="registerForm.first_name"
                                        type="text"
                                        autocomplete="given-name"
                                        placeholder="Enter your first name"
                                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                                    />

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.first_name"
                                    />

                                </div>

                                <!-- LAST NAME -->

                                <div>

                                    <label
                                        for="register-last-name"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        Last name
                                    </label>

                                    <input
                                        id="register-last-name"
                                        v-model="registerForm.last_name"
                                        type="text"
                                        autocomplete="family-name"
                                        placeholder="Enter your last name"
                                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                                    />

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.last_name"
                                    />

                                </div>

                                <!-- MIDDLE INITIAL -->

                                <div>

                                    <label
                                        for="register-middle-initial"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        Middle initial
                                        <span class="text-[#94A3B8]">
                                            (optional)
                                        </span>
                                    </label>

                                    <input
                                        id="register-middle-initial"
                                        v-model="registerForm.middle_initial"
                                        type="text"
                                        maxlength="1"
                                        placeholder="M"
                                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm uppercase outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                                    />

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.middle_initial"
                                    />

                                </div>

                                <!-- EMAIL -->

                                <div>

                                    <label
                                        for="register-email"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        Email address
                                    </label>

                                    <input
                                        id="register-email"
                                        v-model="registerForm.email"
                                        type="email"
                                        autocomplete="email"
                                        placeholder="you@example.com"
                                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                                    />

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.email"
                                    />

                                </div>

                                <!-- PASSWORD -->

                                <div>

                                    <label
                                        for="register-password"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        Password
                                    </label>

                                    <input
                                        id="register-password"
                                        v-model="registerForm.password"
                                        type="password"
                                        autocomplete="new-password"
                                        placeholder="At least 8 characters"
                                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                                    />

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.password"
                                    />

                                </div>

                                <!-- CONFIRM PASSWORD -->

                                <div>

                                    <label
                                        for="register-password-confirmation"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        Confirm password
                                    </label>

                                    <input
                                        id="register-password-confirmation"
                                        v-model="registerForm.password_confirmation"
                                        type="password"
                                        autocomplete="new-password"
                                        placeholder="Repeat your password"
                                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                                    />

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.password_confirmation"
                                    />

                                </div>

                            </div>

                            <!-- ================================================= -->
                            <!-- STEP 2 -->
                            <!-- ================================================= -->

                            <div
                                v-else-if="registrationStep === 2"
                                class="space-y-5"
                            >

                                <!-- SEX -->

                                <div>

                                    <label
                                        for="register-sex"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        Sex
                                    </label>

                                    <select
                                        id="register-sex"
                                        v-model="registerForm.sex"
                                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                                    >

                                        <option
                                            value=""
                                            disabled
                                        >
                                            Select sex
                                        </option>

                                        <option value="Male">
                                            Male
                                        </option>

                                        <option value="Female">
                                            Female
                                        </option>

                                        <option value="Other">
                                            Other
                                        </option>

                                    </select>

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.sex"
                                    />

                                </div>

                                <!-- BIRTHDAY -->

                                <div>

                                    <label
                                        for="register-birthday"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        Birthday
                                    </label>

                                    <input
                                        id="register-birthday"
                                        v-model="registerForm.birthday"
                                        type="date"
                                        :max="new Date().toISOString().split('T')[0]"
                                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                                    />

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.birthday"
                                    />

                                </div>

                                <!-- AGE -->

                                <div>

                                    <label
                                        for="register-age"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        Age
                                    </label>

                                    <input
                                        id="register-age"
                                        v-model="registerForm.age"
                                        type="number"
                                        readonly
                                        placeholder="Automatically calculated"
                                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F1F5F9] px-4 py-3 text-sm text-[#64748B] outline-none"
                                    />

                                    <p
                                        class="mt-1 text-xs text-[#94A3B8]"
                                    >
                                        Age is automatically calculated from your birthday.
                                    </p>

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.age"
                                    />

                                </div>

                                <!-- CONTACT -->

                                <div>

                                    <label
                                        for="register-contact"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        Contact number
                                    </label>

                                    <input
                                        id="register-contact"
                                        v-model="registerForm.contact_no"
                                        type="tel"
                                        autocomplete="tel"
                                        placeholder="09XXXXXXXXX"
                                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                                    />

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.contact_no"
                                    />

                                </div>

                            </div>

                            <!-- ================================================= -->
                            <!-- STEP 3 ADDRESS -->
                            <!-- ================================================= -->

                            <div
                                v-else-if="registrationStep === 3"
                                class="space-y-5"
                            >

                                <!-- API ERROR -->

                                <div
                                    v-if="addressError"
                                    class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-600"
                                >
                                    {{ addressError }}
                                </div>

                                <!-- PROVINCE -->

                                <div>

                                    <label
                                        for="register-province"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        Province
                                    </label>

                                    <select
                                        id="register-province"
                                        v-model="selectedProvinceCode"
                                        @change="handleProvinceChange"
                                        :disabled="addressLoading"
                                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm outline-none transition disabled:cursor-not-allowed disabled:opacity-60 focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                                    >

                                        <option value="">
                                            {{
                                                addressLoading
                                                    ? 'Loading provinces...'
                                                    : 'Select province'
                                            }}
                                        </option>

                                        <option
                                            v-for="province in provinces"
                                            :key="province.code"
                                            :value="province.code"
                                        >
                                            {{ province.name }}
                                        </option>

                                    </select>

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.province"
                                    />

                                </div>

                                <!-- MUNICIPALITY -->

                                <div>

                                    <label
                                        for="register-municipality"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        Municipality / City
                                    </label>

                                    <select
                                        id="register-municipality"
                                        v-model="selectedMunicipalityCode"
                                        @change="handleMunicipalityChange"
                                        :disabled="
                                            !selectedProvinceCode ||
                                            municipalityLoading
                                        "
                                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm outline-none transition disabled:cursor-not-allowed disabled:opacity-60 focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                                    >

                                        <option value="">
                                            {{
                                                municipalityLoading
                                                    ? 'Loading municipalities...'
                                                    : selectedProvinceCode
                                                        ? 'Select municipality / city'
                                                        : 'Select province first'
                                            }}
                                        </option>

                                        <option
                                            v-for="municipality in municipalities"
                                            :key="municipality.code"
                                            :value="municipality.code"
                                        >
                                            {{ municipality.name }}
                                            {{
                                                municipality.type
                                                    ? ` (${municipality.type})`
                                                    : ''
                                            }}
                                        </option>

                                    </select>

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.municipality"
                                    />

                                </div>

                                <!-- BARANGAY -->

                                <div>

                                    <label
                                        for="register-barangay"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        Barangay
                                    </label>

                                    <select
                                        id="register-barangay"
                                        v-model="registerForm.barangay"
                                        @change="handleBarangayChange"
                                        :disabled="
                                            !selectedMunicipalityCode ||
                                            barangayLoading
                                        "
                                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm outline-none transition disabled:cursor-not-allowed disabled:opacity-60 focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                                    >

                                        <option value="">
                                            {{
                                                barangayLoading
                                                    ? 'Loading barangays...'
                                                    : selectedMunicipalityCode
                                                        ? 'Select barangay'
                                                        : 'Select municipality first'
                                            }}
                                        </option>

                                        <option
                                            v-for="barangay in barangays"
                                            :key="barangay.code"
                                            :value="barangay.name"
                                        >
                                            {{ barangay.name }}
                                        </option>

                                    </select>

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.barangay"
                                    />

                                </div>

                                <!-- STREET -->

                                <div>

                                    <label
                                        for="register-street"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        House No. / Street Address
                                    </label>

                                    <textarea
                                        id="register-street"
                                        v-model="registerForm.street_address"
                                        rows="3"
                                        placeholder="House number, street, subdivision, etc."
                                        class="mt-2 block w-full resize-none rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                                    ></textarea>

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.street_address"
                                    />

                                </div>

                                <!-- ADDRESS INFORMATION -->

                                <div
                                    v-if="
                                        registerForm.province &&
                                        registerForm.municipality &&
                                        registerForm.barangay
                                    "
                                    class="rounded-xl bg-[#E8F7F6] p-4"
                                >

                                    <p
                                        class="text-xs font-semibold uppercase tracking-wide text-[#087F8C]"
                                    >
                                        Selected address
                                    </p>

                                    <p
                                        class="mt-1 text-sm leading-5 text-[#1F2937]"
                                    >
                                        {{ registerForm.barangay }},
                                        {{ registerForm.municipality }},
                                        {{ registerForm.province }}
                                    </p>

                                </div>

                            </div>

                            <!-- ================================================= -->
                            <!-- STEP 4 BUYER -->
                            <!-- ================================================= -->

                            <div
                                v-else-if="
                                    registrationStep === 4 &&
                                    !isSeller
                                "
                                class="space-y-5"
                            >

                                <div
                                    class="rounded-xl bg-[#E8F7F6] p-4"
                                >

                                    <h3
                                        class="font-semibold text-[#087F8C]"
                                    >
                                        Identity verification
                                    </h3>

                                    <p
                                        class="mt-1 text-sm leading-5 text-[#64748B]"
                                    >
                                        Upload a valid identification document.
                                        Your registration will be reviewed by an administrator.
                                    </p>

                                </div>

                                <!-- ID -->

                                <div>

                                    <label
                                        for="register-id"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        Valid ID
                                        <span class="text-[#94A3B8]">
                                            (optional)
                                        </span>
                                    </label>

                                    <input
                                        id="register-id"
                                        type="file"
                                        accept=".jpg,.jpeg,.png,.pdf"
                                        @change="handleIdUpload"
                                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-3 text-sm text-[#64748B] file:mr-4 file:rounded-lg file:border-0 file:bg-[#E8F7F6] file:px-3 file:py-2 file:text-sm file:font-medium file:text-[#087F8C] hover:file:bg-[#D7F1EF]"
                                    />

                                    <p
                                        class="mt-1 text-xs text-[#94A3B8]"
                                    >
                                        JPG, JPEG, PNG, or PDF. Maximum 5 MB.
                                    </p>

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.id"
                                    />

                                </div>

                                <div
                                    class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-4 text-sm text-[#64748B]"
                                >

                                    <p
                                        class="font-medium text-[#1F2937]"
                                    >
                                        Registration review
                                    </p>

                                    <p
                                        class="mt-1 leading-5"
                                    >
                                        Your account will be created with
                                        <strong class="text-[#087F8C]">
                                            pending
                                        </strong>
                                        status and will require administrator approval.
                                    </p>

                                </div>

                            </div>

                            <!-- ================================================= -->
                            <!-- STEP 4 SELLER -->
                            <!-- ================================================= -->

                            <div
                                v-else-if="
                                    registrationStep === 4 &&
                                    isSeller
                                "
                                class="space-y-5"
                            >

                                <div
                                    class="rounded-xl bg-[#E8F7F6] p-4"
                                >

                                    <h3
                                        class="font-semibold text-[#087F8C]"
                                    >
                                        Seller application
                                    </h3>

                                    <p
                                        class="mt-1 text-sm leading-5 text-[#64748B]"
                                    >
                                        Provide your store details and required verification documents to apply as an Alona seller.
                                    </p>

                                </div>

                                <!-- STORE NAME -->

                                <div>

                                    <label
                                        for="register-store-name"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        Store name
                                    </label>

                                    <input
                                        id="register-store-name"
                                        v-model="registerForm.store_name"
                                        type="text"
                                        placeholder="Enter your store name"
                                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                                    />

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.store_name"
                                    />

                                </div>

                                <!-- LINE OF BUSINESS -->

                                <div>

                                    <label
                                        for="register-line-of-business"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        Line of business
                                    </label>

                                    <input
                                        id="register-line-of-business"
                                        v-model="registerForm.line_of_business"
                                        type="text"
                                        placeholder="e.g. Clothing, Electronics, Food"
                                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                                    />

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.line_of_business"
                                    />

                                </div>

                                <!-- STORE DESCRIPTION -->

                                <div>

                                    <label
                                        for="register-store-description"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        Store description
                                        <span class="text-[#94A3B8]">
                                            (optional)
                                        </span>
                                    </label>

                                    <textarea
                                        id="register-store-description"
                                        v-model="registerForm.store_description"
                                        rows="3"
                                        placeholder="Tell customers about your store"
                                        class="mt-2 block w-full resize-none rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                                    ></textarea>

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.store_description"
                                    />

                                </div>

                                <!-- BANK NAME -->

                                <div>

                                    <label
                                        for="register-bank-name"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        Bank name
                                        <span class="text-[#94A3B8]">
                                            (optional)
                                        </span>
                                    </label>

                                    <input
                                        id="register-bank-name"
                                        v-model="registerForm.bank_name"
                                        type="text"
                                        placeholder="Bank name"
                                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                                    />

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.bank_name"
                                    />

                                </div>

                                <!-- BANK ACCOUNT NAME -->

                                <div>

                                    <label
                                        for="register-bank-account-name"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        Bank account name
                                        <span class="text-[#94A3B8]">
                                            (optional)
                                        </span>
                                    </label>

                                    <input
                                        id="register-bank-account-name"
                                        v-model="registerForm.bank_account_name"
                                        type="text"
                                        placeholder="Account holder name"
                                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                                    />

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.bank_account_name"
                                    />

                                </div>

                                <!-- BANK ACCOUNT NUMBER -->

                                <div>

                                    <label
                                        for="register-bank-account-number"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        Bank account number
                                        <span class="text-[#94A3B8]">
                                            (optional)
                                        </span>
                                    </label>

                                    <input
                                        id="register-bank-account-number"
                                        v-model="registerForm.bank_account_number"
                                        type="text"
                                        placeholder="Account number"
                                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                                    />

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.bank_account_number"
                                    />

                                </div>

                                <!-- VALID ID -->

                                <div>

                                    <label
                                        for="seller-register-id"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        Valid ID
                                        <span class="text-red-500">
                                            *
                                        </span>
                                    </label>

                                    <input
                                        id="seller-register-id"
                                        type="file"
                                        accept=".jpg,.jpeg,.png,.pdf"
                                        @change="handleIdUpload"
                                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-3 text-sm text-[#64748B] file:mr-4 file:rounded-lg file:border-0 file:bg-[#E8F7F6] file:px-3 file:py-2 file:font-medium file:text-[#087F8C] hover:file:bg-[#D7F1EF]"
                                    />

                                    <p
                                        class="mt-1 text-xs text-[#94A3B8]"
                                    >
                                        JPG, JPEG, PNG, or PDF. Maximum 5 MB.
                                    </p>

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.id"
                                    />

                                </div>

                                <!-- BUSINESS PERMIT -->

                                <div>

                                    <label
                                        for="seller-business-permit"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        Business Permit
                                        <span class="text-red-500">
                                            *
                                        </span>
                                    </label>

                                    <input
                                        id="seller-business-permit"
                                        type="file"
                                        accept=".jpg,.jpeg,.png,.pdf"
                                        @change="handleBusinessPermitUpload"
                                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-3 text-sm text-[#64748B] file:mr-4 file:rounded-lg file:border-0 file:bg-[#E8F7F6] file:px-3 file:py-2 file:font-medium file:text-[#087F8C] hover:file:bg-[#D7F1EF]"
                                    />

                                    <p
                                        class="mt-1 text-xs text-[#94A3B8]"
                                    >
                                        JPG, JPEG, PNG, or PDF. Maximum 5 MB.
                                    </p>

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.business_permit"
                                    />

                                </div>

                                <!-- APPLICATION REVIEW -->

                                <div
                                    class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-4 text-sm text-[#64748B]"
                                >

                                    <p
                                        class="font-medium text-[#1F2937]"
                                    >
                                        Application review
                                    </p>

                                    <p
                                        class="mt-1 leading-5"
                                    >
                                        Your seller account will be created with
                                        <strong class="text-[#087F8C]">
                                            pending
                                        </strong>
                                        status and will require administrator approval.
                                    </p>

                                </div>

                            </div>

                            <!-- SUCCESS -->

                            <div
                                v-if="registrationSuccess"
                                class="mt-5 rounded-xl bg-[#E8F7F6] px-4 py-3 text-sm text-[#087F8C]"
                            >
                                {{ registrationSuccess }}
                            </div>

                            <!-- ERROR -->

                            <div
                                v-if="stepError"
                                class="mt-5 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-600"
                            >
                                {{ stepError }}
                            </div>

                            <!-- NAVIGATION -->

                            <div class="mt-7 flex gap-3">

                                <!-- BACK -->

                                <button
                                    type="button"
                                    @click="previousStep"
                                    :disabled="registerForm.processing"
                                    class="flex-1 rounded-xl border border-[#E5E7EB] bg-white px-4 py-3 text-sm font-semibold text-[#1F2937] transition hover:bg-[#F8FAF9] disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    Back
                                </button>

                                <!-- NEXT -->

                                <button
                                    v-if="
                                        registrationStep <
                                        totalSteps
                                    "
                                    type="button"
                                    @click="nextStep"
                                    class="flex-1 rounded-xl bg-[#087F8C] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#16A6A0]"
                                >
                                    Next
                                </button>

                                <!-- SUBMIT -->

                                <button
                                    v-else
                                    type="button"
                                    @click="submitRegister"
                                    :disabled="registerForm.processing"
                                    class="flex-1 rounded-xl bg-[#087F8C] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#16A6A0] disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    {{
                                        registerForm.processing
                                            ? 'Submitting...'
                                            : isSeller
                                                ? 'Submit Seller Application'
                                                : 'Create Account'
                                    }}
                                </button>

                            </div>

                            <!-- CHANGE ACCOUNT TYPE -->

                            <button
                                type="button"
                                @click="resetRegistration"
                                :disabled="registerForm.processing"
                                class="mt-4 w-full text-center text-xs font-medium text-[#64748B] hover:text-[#087F8C] disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                Change account type
                            </button>

                        </div>

                    </div>

                    <!-- ================================================= -->
                    <!-- GOOGLE -->
                    <!-- ================================================= -->

                    <div
                        v-if="!registrationStarted"
                        class="my-6 flex items-center gap-3 text-xs text-[#94A3B8]"
                    >

                        <span
                            class="h-px flex-1 bg-[#E5E7EB]"
                        ></span>

                        <span>
                            or continue with
                        </span>

                        <span
                            class="h-px flex-1 bg-[#E5E7EB]"
                        ></span>

                    </div>

                    <a
                        v-if="!registrationStarted"
                        :href="route('google.redirect')"
                        class="flex w-full items-center justify-center gap-3 rounded-xl border border-[#E5E7EB] bg-white px-4 py-3 text-sm font-semibold text-[#1F2937] transition hover:bg-[#F8FAF9]"
                    >

                        <span
                            class="text-base font-bold"
                        >
                            G
                        </span>

                        Continue with Google

                    </a>

                    <!-- ================================================= -->
                    <!-- LOGIN / REGISTER SWITCH -->
                    <!-- ================================================= -->

                    <div
                        v-if="!registrationStarted"
                        class="mt-6 border-t border-[#E5E7EB] pt-6 text-center"
                    >

                        <p
                            class="text-sm text-[#64748B]"
                        >
                            {{
                                mode === 'login'
                                    ? "Don't have an Alona account?"
                                    : 'Already have an Alona account?'
                            }}
                        </p>

                        <button
                            v-if="mode === 'login'"
                            type="button"
                            @click="switchToRegister"
                            class="mt-2 text-sm font-semibold text-[#087F8C] hover:text-[#16A6A0]"
                        >
                            Create an account
                        </button>

                        <button
                            v-else
                            type="button"
                            @click="switchToLogin"
                            class="mt-2 text-sm font-semibold text-[#087F8C] hover:text-[#16A6A0]"
                        >
                            Log in
                        </button>

                    </div>

                </div>
            </div>
        </div>
    </Teleport>
</template>