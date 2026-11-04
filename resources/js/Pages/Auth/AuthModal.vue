<script setup>
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'
import PhilippineAddressSelector from '@/Components/PhilippineAddressSelector.vue'

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
const page = usePage()
const googleIdentity = computed(() => page.props.auth?.googleOnboarding ?? {})
const isGoogleOnboarding = computed(() => !!googleIdentity.value.email && googleIdentity.value.role === registrationType.value)
const dialog = ref(null)
let previouslyFocused
let previousOverflow
const trapFocus = event => {
    if (event.key !== 'Tab') return
    const elements = [...dialog.value.querySelectorAll('button:not(:disabled), a[href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled)')].filter(el => el.getClientRects().length)
    const first = elements[0], last = elements.at(-1)
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus() }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus() }
}
watch(() => props.show, async show => {
    if (typeof document === 'undefined') return
    if (show) { previouslyFocused = document.activeElement; previousOverflow = document.body.style.overflow; document.body.style.overflow = 'hidden'; await nextTick(); dialog.value?.querySelector('button')?.focus() }
    else if (previousOverflow !== undefined) { document.body.style.overflow = previousOverflow; previouslyFocused?.focus() }
}, { immediate: true })
onUnmounted(() => {
    if (typeof document !== 'undefined' && previousOverflow !== undefined) document.body.style.overflow = previousOverflow
    if (resendTimer) clearInterval(resendTimer)
})

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
| REGISTRATION & IN-MODAL OTP
|--------------------------------------------------------------------------
*/

const registrationType = ref('buyer')
const registrationStarted = ref(true)
const registrationStep = ref(1)
const stepError = ref('')
const registrationSuccess = ref('')
const emailInUseAlert = ref('')
const accountCreatedSuccess = ref('')
const accountVerifiedSuccess = ref('')

const showRegisterPassword = ref(false)
const showRegisterPasswordConfirmation = ref(false)
const showRegisterPasswords = ref(false)
const showAdditionalNameFields = ref(false)
const showLoginPassword = ref(false)

const isVerifyingOtp = ref(false)
const otpEmail = ref('')
const otpCode = ref('')
const otpError = ref('')
const otpSuccess = ref('')
const isOtpSubmitting = ref(false)
const isOtpResending = ref(false)
const resendCooldown = ref(0)
let resendTimer = null

const startResendCooldown = (seconds = 60) => {
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

const getCsrfToken = () => {
    return (
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
        document.cookie
            .split('; ')
            .find((row) => row.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ||
        ''
    )
}

const handleOtpInput = (event) => {
    otpCode.value = event.target.value.replace(/\D/g, '').slice(0, 6)
    if (otpError.value) otpError.value = ''
    if (otpCode.value.length === 6) {
        submitModalVerifyOtp()
    }
}

const cleanMessage = (msg) => {
    if (!msg) return ''
    if (Array.isArray(msg)) {
        msg = msg[0] || ''
    }
    if (typeof msg === 'object') {
        const first = Object.values(msg)[0]
        msg = Array.isArray(first) ? first[0] : first
    }
    let str = String(msg || '').trim()
    if (str.startsWith('["') && str.endsWith('"]')) {
        str = str.slice(2, -2)
    } else if (str.startsWith('[') && str.endsWith(']')) {
        str = str.slice(1, -1).trim()
        if (str.startsWith('"') && str.endsWith('"')) {
            str = str.slice(1, -1)
        }
    }
    return str
}

const registerForm = useForm({
    /*
    |--------------------------------------------------------------------------
    | Personal Information
    |--------------------------------------------------------------------------
    */

    last_name: '',
    first_name: '',
    middle_name: '',
    middle_initial: '',
    suffix: '',

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

    address_group: '', region_code: '', province_code: '', city_code: '', barangay_code: '',
    province: '',
    municipality: '',
    barangay: '',
    street_address: '',
    postal_code: '',

    /*
    |--------------------------------------------------------------------------
    | Seller Information
    |--------------------------------------------------------------------------
    */

    store_name: '',
    seller_type: 'Sole Proprietorship',
    tin: '',
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
    dti_certificate: null,
    sec_certificate: null,
    bir_2303: null,

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    password: '',
    password_confirmation: '',
    terms: false,
})

/*
|--------------------------------------------------------------------------
| PHILIPPINE ADDRESS DATA
|--------------------------------------------------------------------------
*/

const isSeller = computed(() => {
    return registrationType.value === 'seller'
})

const totalSteps = computed(() => {
    return isSeller.value ? 4 : 3
})

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

    return 'Store information'
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

    return 'Provide your store and required verification information.'
})

const visibleStepError = computed(() => {
    if (!stepError.value) return ''
    const cleanedStep = cleanMessage(stepError.value)
    if (!cleanedStep) return ''

    // Suppress stepError if it matches any field error currently rendered on the form
    const hasMatchingFieldError = Object.values(registerForm.errors || {}).some((err) => {
        return cleanMessage(err) === cleanedStep
    })

    if (hasMatchingFieldError) {
        return ''
    }

    return cleanedStep
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
    registrationType.value = 'buyer'
    registrationStarted.value = true
    registrationStep.value = 1
}

/*
|--------------------------------------------------------------------------
| RESET REGISTRATION
|--------------------------------------------------------------------------
*/

const resetRegistration = () => {
    registrationType.value = 'buyer'
    registrationStarted.value = true
    registrationStep.value = 1

    stepError.value = ''
    registrationSuccess.value = ''

    isVerifyingOtp.value = false
    otpEmail.value = ''
    otpCode.value = ''
    otpError.value = ''
    otpSuccess.value = ''
    emailInUseAlert.value = ''
    accountCreatedSuccess.value = ''
    accountVerifiedSuccess.value = ''
    showRegisterPassword.value = false
    showRegisterPasswordConfirmation.value = false
    showRegisterPasswords.value = false
    showAdditionalNameFields.value = false
    showLoginPassword.value = false
    if (resendTimer) {
        clearInterval(resendTimer)
        resendTimer = null
    }
    resendCooldown.value = 0

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
            'Please choose how you want to join Alona.'

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

const normalizeContactNo = () => {
    if (!registerForm.contact_no) return
    let contact = registerForm.contact_no.trim()
    if (/^(?:\+63|63)(9\d{9})$/.test(contact)) {
        contact = '0' + contact.replace(/^(?:\+63|63)/, '')
    }
    registerForm.contact_no = contact
}

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
        const namePattern = /^[\p{L}\s'.-]+$/u

        const firstName = registerForm.first_name.trim()
        if (!firstName) {
            registerForm.setError('first_name', 'Please enter your first name.')
            stepError.value =
                'Please enter your first name.'

            return false
        }

        if (firstName.length < 2) {
            registerForm.setError('first_name', 'First name must be at least 2 characters.')
            stepError.value = 'First name must be at least 2 characters.'
            return false
        }

        if (firstName.length > 50) {
            registerForm.setError('first_name', 'First name may not be greater than 50 characters.')
            stepError.value = 'First name may not be greater than 50 characters.'
            return false
        }

        if (!namePattern.test(firstName)) {
            registerForm.setError('first_name', 'First name can only contain letters, spaces, hyphens, apostrophes, and periods.')
            stepError.value = 'First name can only contain letters, spaces, hyphens, apostrophes, and periods.'
            return false
        }

        const lastName = registerForm.last_name.trim()
        if (!lastName) {
            registerForm.setError('last_name', 'Please enter your last name.')
            stepError.value =
                'Please enter your last name.'

            return false
        }

        if (lastName.length < 2) {
            registerForm.setError('last_name', 'Last name must be at least 2 characters.')
            stepError.value = 'Last name must be at least 2 characters.'
            return false
        }

        if (lastName.length > 50) {
            registerForm.setError('last_name', 'Last name may not be greater than 50 characters.')
            stepError.value = 'Last name may not be greater than 50 characters.'
            return false
        }

        if (!namePattern.test(lastName)) {
            registerForm.setError('last_name', 'Last name can only contain letters, spaces, hyphens, apostrophes, and periods.')
            stepError.value = 'Last name can only contain letters, spaces, hyphens, apostrophes, and periods.'
            return false
        }

        if (registerForm.middle_name && registerForm.middle_name.trim() && !namePattern.test(registerForm.middle_name.trim())) {
            registerForm.setError('middle_name', 'Middle name can only contain letters, spaces, hyphens, apostrophes, and periods.')
            stepError.value = 'Middle name can only contain letters, spaces, hyphens, apostrophes, and periods.'
            return false
        }

        if (registerForm.suffix && registerForm.suffix.trim() && !namePattern.test(registerForm.suffix.trim())) {
            registerForm.setError('suffix', 'Suffix can only contain letters, spaces, hyphens, apostrophes, and periods.')
            stepError.value = 'Suffix can only contain letters, spaces, hyphens, apostrophes, and periods.'
            return false
        }

        if (registerForm.email) {
            registerForm.email = registerForm.email.trim().toLowerCase()
        }

        if (
            !registerForm.email
        ) {
            registerForm.setError('email', 'Please enter your email address.')
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
            registerForm.setError('email', 'Please enter a valid email address.')
            stepError.value =
                'Please enter a valid email address.'

            return false
        }

        if (!isGoogleOnboarding.value) {
            const pwd = registerForm.password || ''

            if (!pwd) {
                registerForm.setError('password', 'Please create a password.')
                stepError.value = 'Please create a password.'
                return false
            }

            if (pwd.length < 8) {
                registerForm.setError('password', 'Your password must be at least 8 characters.')
                stepError.value = 'Your password must be at least 8 characters.'
                return false
            }

            if (pwd.length > 64) {
                registerForm.setError('password', 'Your password may not be greater than 64 characters.')
                stepError.value = 'Your password may not be greater than 64 characters.'
                return false
            }

            const hasUpper = /[A-Z]/.test(pwd) || /\p{Lu}/u.test(pwd)
            const hasLower = /[a-z]/.test(pwd) || /\p{Ll}/u.test(pwd)
            const hasNumber = /[0-9]/.test(pwd) || /\p{N}/u.test(pwd)
            const hasSymbol = /[\p{P}\p{S}\W_]/u.test(pwd)

            if (!hasUpper || !hasLower || !hasNumber || !hasSymbol) {
                const missing = []
                if (!hasUpper) missing.push('an uppercase letter')
                if (!hasLower) missing.push('a lowercase letter')
                if (!hasNumber) missing.push('a number')
                if (!hasSymbol) missing.push('a symbol')

                const msg = `Password must contain at least ${missing.join(', ')}.`
                registerForm.setError('password', msg)
                stepError.value = msg
                return false
            }

            if (!registerForm.password_confirmation) {
                registerForm.setError('password_confirmation', 'Please confirm your password.')
                stepError.value = 'Please confirm your password.'
                return false
            }

            if (registerForm.password !== registerForm.password_confirmation) {
                registerForm.setError('password_confirmation', 'The passwords do not match.')
                stepError.value = 'The passwords do not match.'
                return false
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | STEP 2
    |--------------------------------------------------------------------------
    */

    if (registrationStep.value === 2) {
        if (isSeller.value && !registerForm.sex) {
            registerForm.setError('sex', 'Please select your sex.')
            stepError.value =
                'Please select your sex.'

            return false
        }

        if (!registerForm.birthday) {
            registerForm.setError('birthday', 'Please enter your birthday.')
            stepError.value =
                'Please enter your birthday.'

            return false
        }

        const calculatedAge = registerForm.age !== '' && registerForm.age !== null
            ? Number(registerForm.age)
            : Number(calculateAge(registerForm.birthday))

        if (
            Number.isNaN(calculatedAge) ||
            calculatedAge < 1
        ) {
            registerForm.setError('birthday', 'Please enter a valid birthday.')
            stepError.value =
                'Please enter a valid birthday.'

            return false
        }

        if (calculatedAge < 18) {
            registerForm.setError('birthday', 'You must be at least 18 years old to register.')
            stepError.value =
                'You must be at least 18 years old to register.'

            return false
        }

        normalizeContactNo()

        if (
            !registerForm.contact_no
        ) {
            registerForm.setError('contact_no', 'Please enter your contact number.')
            stepError.value =
                'Please enter your contact number.'

            return false
        }

        const phonePattern = /^09\d{9}$/
        if (!phonePattern.test(registerForm.contact_no)) {
            registerForm.setError('contact_no', 'Please enter a valid 11-digit Philippine mobile number starting with 09.')
            stepError.value =
                'Please enter a valid 11-digit Philippine mobile number starting with 09.'

            return false
        }

    }

    /*
    |--------------------------------------------------------------------------
    | STEP 3
    |--------------------------------------------------------------------------
    */

    if (registrationStep.value === 3) {
        if (!registerForm.address_group || !registerForm.region_code || !registerForm.city_code || !registerForm.barangay_code) {
            stepError.value = 'Please select your complete Philippine address.'
            return false
        }

        if (
            !registerForm.street_address.trim()
        ) {
            stepError.value =
                'Please enter your house number and street address.'

            return false
        }

        if (!registerForm.postal_code || !/^\d{4}$/.test(registerForm.postal_code.trim())) {
            registerForm.setError('postal_code', 'Please enter a valid 4-digit postal code.')
            stepError.value = 'Please enter a valid 4-digit postal code.'
            return false
        }

        if (!isSeller.value && !registerForm.terms) {
            registerForm.setError('terms', 'You must agree to the Terms and Privacy Policy.')
            stepError.value = 'You must agree to the Terms and Privacy Policy.'
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

const handleDtiUpload = (event) => {
    const file = event.target.files?.[0] ?? null
    registerForm.dti_certificate = file
    if (file) {
        registerForm.clearErrors('dti_certificate')
        stepError.value = ''
    }
}

const handleSecUpload = (event) => {
    const file = event.target.files?.[0] ?? null
    registerForm.sec_certificate = file
    if (file) {
        registerForm.clearErrors('sec_certificate')
        stepError.value = ''
    }
}

const handleBirUpload = (event) => {
    const file = event.target.files?.[0] ?? null
    registerForm.bir_2303 = file
    if (file) {
        registerForm.clearErrors('bir_2303')
        stepError.value = ''
    }
}

const formatTin = () => {
    let val = (registerForm.tin || '').replace(/\D/g, '').substring(0, 12)
    let parts = []
    if (val.length > 0) parts.push(val.substring(0, 3))
    if (val.length > 3) parts.push(val.substring(3, 6))
    if (val.length > 6) parts.push(val.substring(6, 9))
    if (val.length > 9) parts.push(val.substring(9, 12))
    registerForm.tin = parts.join('-')
    registerForm.clearErrors('tin')
    stepError.value = ''
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

const submitRegister = async () => {
    if (registerForm.email) {
        registerForm.email = registerForm.email.trim().toLowerCase()
    }
    normalizeContactNo()

    if (!validateCurrentStep()) {
        return
    }

    stepError.value = ''
    registrationSuccess.value = ''
    emailInUseAlert.value = ''

    registerForm.processing = true
    stepError.value = ''
    registrationSuccess.value = ''
    emailInUseAlert.value = ''

    try {
        const payload = registerForm.data()
        const formData = new FormData()
        for (const [key, val] of Object.entries(payload)) {
            if (val !== null && val !== undefined && val !== '') {
                formData.append(key, typeof val === 'boolean' ? (val ? '1' : '0') : val)
            }
        }

        const targetUrl = route(isSeller.value ? 'register.seller' : 'register')

        const response = await fetch(targetUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: formData,
        })

        const data = await response.json()

        if (!response.ok) {
            if (response.status === 422 && data.errors) {
                registerForm.clearErrors()
                const flattened = {}
                for (const [key, val] of Object.entries(data.errors)) {
                    flattened[key] = cleanMessage(val)
                }
                registerForm.setError(flattened)

                if (data.errors.email) {
                    registrationStep.value = 1
                    emailInUseAlert.value = cleanMessage(data.errors.email) || 'This email address is already registered. Please log in instead or use another email.'
                    stepError.value = emailInUseAlert.value
                } else if (data.errors.password) {
                    registrationStep.value = 1
                    stepError.value = cleanMessage(data.errors.password)
                } else if (data.errors.contact_no) {
                    registrationStep.value = 2
                    stepError.value = cleanMessage(data.errors.contact_no)
                } else if (data.errors.address_group || data.errors.region_code || data.errors.province_code || data.errors.city_code || data.errors.barangay_code || data.errors.street_address || data.errors.postal_code) {
                    registrationStep.value = 3
                    stepError.value = cleanMessage(data.errors.street_address || data.errors.address_group || data.errors.city_code || data.errors.postal_code) || 'Please correct your address details.'
                } else if (isSeller.value && (data.errors.store_name || data.errors.seller_type || data.errors.tin || data.errors.line_of_business || data.errors.id || data.errors.business_permit || data.errors.dti_certificate || data.errors.sec_certificate || data.errors.bir_2303)) {
                    registrationStep.value = 4
                    const firstStep4 = cleanMessage(data.errors.store_name || data.errors.tin || data.errors.line_of_business || data.errors.id || data.errors.business_permit || data.errors.dti_certificate || data.errors.sec_certificate || data.errors.bir_2303)
                    stepError.value = firstStep4 || 'Please correct your store and document details.'
                } else {
                    emailInUseAlert.value = ''
                    const firstError = Object.values(data.errors)[0]
                    stepError.value = cleanMessage(firstError) || 'Please correct the highlighted fields and try again.'
                }
            } else {
                stepError.value = cleanMessage(data.message) || 'An error occurred during registration. Please try again.'
            }
            return
        }

        // Successfully registered: display OTP Verification box right inside the modal!
        isVerifyingOtp.value = true
        otpEmail.value = registerForm.email
        otpCode.value = ''
        otpError.value = ''
        emailInUseAlert.value = ''
        accountCreatedSuccess.value = data.message || (isSeller.value ? 'Seller application submitted! A 6-digit verification code has been sent to your email address.' : 'Account created successfully! A 6-digit verification code has been sent to your email address.')
        otpSuccess.value = accountCreatedSuccess.value
        startResendCooldown(60)
    } catch (err) {
        console.error('Registration submit error:', err)
        stepError.value = 'Unable to complete registration. Please check your connection and try again.'
    } finally {
        registerForm.processing = false
    }
}

/*
|--------------------------------------------------------------------------
| IN-MODAL OTP VERIFICATION HANDLERS
|--------------------------------------------------------------------------
*/

const submitModalVerifyOtp = async () => {
    const code = otpCode.value.trim()
    if (!code || !/^\d{6}$/.test(code)) {
        otpError.value = 'Please enter a valid 6-digit verification code.'
        return
    }

    otpError.value = ''
    otpSuccess.value = ''
    isOtpSubmitting.value = true

    try {
        const response = await fetch(route('verification.otp.verify'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({
                email: otpEmail.value,
                otp: code,
            }),
        })

        const data = await response.json()

        if (!response.ok) {
            otpError.value = data.errors?.otp?.[0] || data.message || 'Invalid verification code.'
            return
        }

        accountVerifiedSuccess.value = data.message || 'Account created and email verified successfully! Welcome to Alona.'
        otpSuccess.value = accountVerifiedSuccess.value
        setTimeout(() => {
            close()
            window.location.assign(data.redirect || (isSeller.value ? route('application.status') : route('buyer.dashboard')))
        }, 1200)
    } catch (err) {
        console.error('OTP verification error:', err)
        otpError.value = 'Unable to verify code. Please check your connection and try again.'
    } finally {
        isOtpSubmitting.value = false
    }
}

const submitModalResendOtp = async () => {
    if (isOtpResending.value || resendCooldown.value > 0) return

    otpError.value = ''
    otpSuccess.value = ''
    isOtpResending.value = true

    try {
        const response = await fetch(route('verification.otp.resend'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({
                email: otpEmail.value,
            }),
        })

        const data = await response.json()

        if (!response.ok) {
            otpError.value = data.errors?.email?.[0] || data.message || 'Failed to resend code.'
            return
        }

        otpSuccess.value = data.message || 'A new 6-digit verification code has been sent to your email address.'
        startResendCooldown(60)
    } catch (err) {
        console.error('OTP resend error:', err)
        otpError.value = 'Unable to resend code. Please try again.'
    } finally {
        isOtpResending.value = false
    }
}

const backFromOtp = () => {
    isVerifyingOtp.value = false
    otpCode.value = ''
    otpError.value = ''
    otpSuccess.value = ''
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
    () => [props.show, props.mode],
    ([show, mode]) => {
        if (show) {
            resetRegistration()
            if (mode === 'verify-otp') {
                isVerifyingOtp.value = true
                otpEmail.value = page.props.auth?.user?.email || (typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('email') : '') || ''
            } else {
                registrationType.value = { 'register-buyer': 'buyer', 'register-seller': 'seller' }[mode] ?? null
                if (googleIdentity.value.email && googleIdentity.value.role === registrationType.value) registerForm.email = googleIdentity.value.email
                if (typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('onboarding') === '1' && ['buyer', 'seller'].includes(registrationType.value)) startRegistration()
            }
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
            class="fixed inset-0 z-[100] flex items-center justify-center bg-[#1F2937]/60 p-4 sm:p-6 backdrop-blur-sm"
            @click="closeOnBackground"
        >
            <div
                class="relative max-h-[92vh] w-full max-w-4xl overflow-hidden rounded-[28px] border border-teal-900/40 bg-[#05505A] shadow-2xl flex flex-col md:flex-row"
                ref="dialog" role="dialog" aria-modal="true" aria-label="Alona account" tabindex="-1" @keydown.esc="close" @keydown="trapFocus"
            >
                <!-- LEFT BRANDED PANEL (White background with logo and decorative circles) -->
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
                                isVerifyingOtp
                                    ? 'Check your inbox.'
                                    : mode === 'login'
                                        ? 'Good to see you again.'
                                        : isSeller
                                            ? 'Grow your business.'
                                            : "Let's get you set up."
                            }}
                        </h3>
                        <p class="mt-3 text-sm leading-relaxed text-[#64748B]">
                            {{
                                isVerifyingOtp
                                    ? 'Enter the 6-digit verification code sent to your email to secure and activate your account.'
                                    : mode === 'login'
                                        ? 'Your cart, saved items and orders are right where you left them.'
                                        : isSeller
                                            ? 'Reach thousands of active customers across the Philippines and scale your sales.'
                                            : 'Create your buyer account in three short steps and start shopping.'
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
                    <!-- OTP VERIFICATION SCREEN -->
                    <!-- ================================================= -->

                    <div
                        v-if="isVerifyingOtp"
                        class="mt-6 space-y-5"
                    >
                        <!-- ACCOUNT CREATED SUCCESS ALERT -->
                        <div
                            v-if="accountCreatedSuccess && !accountVerifiedSuccess"
                            class="rounded-2xl border border-emerald-400/40 bg-emerald-500/20 p-4 text-emerald-100 shadow-sm"
                            role="status"
                        >
                            <div class="flex items-start gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-500/30 text-emerald-200">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <h4 class="text-sm font-semibold text-white">
                                        Account Created Successfully!
                                    </h4>
                                    <p class="mt-1 text-xs leading-5 text-emerald-200">
                                        A 6-digit verification code has been sent to <span class="font-bold underline text-white">{{ otpEmail }}</span>. Please enter it below to activate your account.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- ACCOUNT VERIFIED SUCCESS ALERT -->
                        <div
                            v-if="accountVerifiedSuccess"
                            class="rounded-2xl border border-emerald-400/40 bg-emerald-500/20 p-4 text-emerald-100 shadow-sm"
                            role="status"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-500/30 text-emerald-200">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <h4 class="text-sm font-semibold text-white">
                                        Email Verified Successfully!
                                    </h4>
                                    <p class="text-xs text-emerald-200 mt-0.5">
                                        {{ accountVerifiedSuccess }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- RESEND OTP SUCCESS NOTICE -->
                        <div
                            v-if="otpSuccess && !accountCreatedSuccess && !accountVerifiedSuccess"
                            class="rounded-xl border border-emerald-400/30 bg-emerald-500/20 px-4 py-3 text-sm font-medium text-emerald-200"
                        >
                            {{ otpSuccess }}
                        </div>

                        <div
                            v-if="otpError"
                            class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm leading-relaxed text-rose-800 shadow-sm"
                            role="alert"
                        >
                            {{ cleanMessage(otpError) }}
                        </div>

                        <form
                            @submit.prevent="submitModalVerifyOtp"
                            class="space-y-5"
                        >
                            <div>
                                <label
                                    for="modal-otp-code"
                                    class="block text-sm font-semibold text-white"
                                >
                                    6-Digit Verification Code
                                </label>

                                <input
                                    id="modal-otp-code"
                                    :value="otpCode"
                                    @input="handleOtpInput"
                                    type="text"
                                    inputmode="numeric"
                                    maxlength="6"
                                    autocomplete="one-time-code"
                                    placeholder="000000"
                                    autofocus
                                    class="mt-2 block w-full rounded-xl border-0 bg-white px-4 py-3 text-center text-2xl font-bold tracking-[0.5em] text-slate-900 shadow-inner outline-none transition placeholder:tracking-normal placeholder:font-normal focus:ring-2 focus:ring-[#F5A524]"
                                />

                                <p class="mt-2 text-xs text-teal-200/70">
                                    Please check your inbox (or spam folder) for the 6-digit code.
                                </p>
                            </div>

                            <button
                                type="submit"
                                :disabled="isOtpSubmitting || otpCode.trim().length !== 6"
                                class="w-full rounded-xl bg-[#F5A524] px-4 py-3.5 text-sm font-bold text-slate-900 shadow-md transition hover:bg-[#e0941d] active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {{ isOtpSubmitting ? 'Verifying...' : 'Verify Account' }}
                            </button>
                        </form>

                        <div class="flex items-center justify-between border-t border-white/15 pt-5 text-sm">
                            <button
                                type="button"
                                @click="submitModalResendOtp"
                                :disabled="isOtpResending || resendCooldown > 0"
                                class="font-semibold text-teal-300 hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {{
                                    isOtpResending
                                        ? 'Sending...'
                                        : resendCooldown > 0
                                            ? `Resend code in ${resendCooldown}s`
                                            : 'Resend Code'
                                }}
                            </button>

                            <button
                                type="button"
                                @click="backFromOtp"
                                class="font-medium text-teal-200/80 hover:text-white"
                            >
                                Change details
                            </button>
                        </div>
                    </div>

                    <!-- ================================================= -->
                    <!-- LOGIN -->
                    <!-- ================================================= -->

                    <div
                        v-else-if="mode === 'login'"
                    >
                        <h2 class="text-2xl sm:text-3xl font-bold tracking-tight text-white">
                            Welcome back
                        </h2>
                        <p class="mt-1 text-sm text-teal-100/70">
                            Sign in to continue shopping with Alona.
                        </p>

                        <!-- GOOGLE LOGIN BUTTON -->
                        <a
                            :href="route('google.redirect', { role: undefined })"
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

                        <!-- GOOGLE COLLISION / GENERAL LOGIN ERROR BANNER -->
                        <div
                            v-if="page.props.errors?.google || (loginForm.errors.email && loginForm.errors.email.toLowerCase().includes('google'))"
                            class="mb-4 rounded-xl bg-rose-50 border border-rose-200 p-3.5 text-sm text-rose-800 leading-relaxed shadow-sm"
                            role="alert"
                        >
                            {{ page.props.errors?.google || loginForm.errors.email }}
                        </div>

                        <form
                            @submit.prevent="submitLogin"
                            class="space-y-4"
                        >
                            <!-- EMAIL -->
                            <div>
                                <label
                                    for="login-email"
                                    class="block text-sm font-semibold text-white"
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
                                    class="mt-1.5 block w-full rounded-xl border-0 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 outline-none shadow-inner transition focus:ring-2 focus:ring-[#F5A524]"
                                />

                                <InputError
                                    v-if="!loginForm.errors.email?.toLowerCase().includes('google')"
                                    class="mt-1.5"
                                    :message="loginForm.errors.email || page.props.errors?.email"
                                />
                            </div>

                            <!-- PASSWORD -->
                            <div>
                                <div class="flex items-center justify-between">
                                    <label
                                        for="login-password"
                                        class="block text-sm font-semibold text-white"
                                    >
                                        Password
                                    </label>

                                    <a
                                        v-if="props.canResetPassword"
                                        :href="route('password.request')"
                                        class="text-xs font-semibold text-teal-300 hover:text-white hover:underline"
                                    >
                                        Forgot password?
                                    </a>
                                </div>

                                <div class="relative mt-1.5">
                                    <input
                                        id="login-password"
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

                                <InputError
                                    class="mt-1.5"
                                    :message="loginForm.errors.password"
                                />
                            </div>

                            <!-- REMEMBER -->
                            <div class="flex items-center gap-2 pt-1">
                                <input
                                    id="login-remember"
                                    v-model="loginForm.remember"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-0 bg-white text-[#F5A524] focus:ring-[#F5A524] focus:ring-offset-0"
                                />
                                <label
                                    for="login-remember"
                                    class="text-sm text-teal-100/90 select-none cursor-pointer"
                                >
                                    Remember me
                                </label>
                            </div>

                            <!-- LOGIN SUBMIT -->
                            <button
                                type="submit"
                                :disabled="loginForm.processing"
                                class="mt-2 w-full rounded-xl bg-[#F5A524] py-3.5 px-4 text-sm font-bold text-slate-900 shadow-md transition hover:bg-[#e0941d] active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {{
                                    loginForm.processing
                                        ? 'Signing in...'
                                        : 'Log in'
                                }}
                            </button>
                        </form>

                        <!-- LOGIN FOOTER -->
                        <div class="mt-6 text-center text-xs text-teal-100/70">
                            New to Alona?
                            <button
                                type="button"
                                @click="switchToRegister"
                                class="font-semibold text-teal-300 hover:text-white hover:underline ml-1"
                            >
                                Create an account
                            </button>
                        </div>
                    </div>

                    <!-- ================================================= -->
                    <!-- REGISTER -->
                    <!-- ================================================= -->

                    <div
                        v-else
                        class="mt-2"
                    >
                        <!-- PROGRESS HEADER -->
                        <div class="mb-5">
                            <div class="flex items-center">
                                    <span class="text-sm font-semibold text-teal-300">
                                        Step {{ registrationStep }} of {{ totalSteps }}
                                    </span>
                                    <span class="ml-3 inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white/10 text-white border border-white/15">
                                        {{ isSeller ? 'Seller application' : 'Buyer registration' }}
                                    </span>
                                </div>

                                <div class="mt-3 flex gap-2">
                                    <div
                                        v-for="stepNum in totalSteps"
                                        :key="stepNum"
                                        class="h-1.5 flex-1 rounded-full transition-all duration-300"
                                        :class="stepNum <= registrationStep ? 'bg-[#F5A524]' : 'bg-white/20'"
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

                            <!-- ================================================= -->
                            <!-- STEP 1 -->
                            <!-- ================================================= -->

                            <div
                                v-if="registrationStep === 1"
                                class="space-y-4"
                            >
                                <!-- GOOGLE BUTTON (Step 1) -->
                                <a
                                    :href="route('google.redirect', { role: registrationType || 'buyer' })"
                                    class="flex w-full items-center justify-center gap-3 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-slate-800 shadow-sm transition hover:bg-slate-100"
                                >
                                    <svg class="h-5 w-5" viewBox="0 0 24 24">
                                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                                    </svg>
                                    <span>Continue with Google</span>
                                </a>

                                <div class="my-4 flex items-center gap-3 text-xs text-teal-200/60">
                                    <span class="h-px flex-1 bg-white/15"></span>
                                    <span>or use your email</span>
                                    <span class="h-px flex-1 bg-white/15"></span>
                                </div>

                                <!-- FIRST & LAST NAME (Side-by-side) -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label
                                            for="register-first-name"
                                            class="block text-sm font-semibold text-white"
                                        >
                                            First name
                                        </label>

                                        <input
                                            id="register-first-name"
                                            v-model="registerForm.first_name"
                                            @input="registerForm.clearErrors('first_name'); stepError = ''"
                                            type="text"
                                            autocomplete="given-name"
                                            placeholder="First name"
                                            class="mt-1.5 block w-full rounded-xl border-0 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 outline-none shadow-inner transition focus:ring-2 focus:ring-[#F5A524]"
                                        />

                                        <InputError
                                            class="mt-1.5"
                                            :message="registerForm.errors.first_name"
                                        />
                                    </div>

                                    <div>
                                        <label
                                            for="register-last-name"
                                            class="block text-sm font-semibold text-white"
                                        >
                                            Last name
                                        </label>

                                        <input
                                            id="register-last-name"
                                            v-model="registerForm.last_name"
                                            @input="registerForm.clearErrors('last_name'); stepError = ''"
                                            type="text"
                                            autocomplete="family-name"
                                            placeholder="Last name"
                                            class="mt-1.5 block w-full rounded-xl border-0 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 outline-none shadow-inner transition focus:ring-2 focus:ring-[#F5A524]"
                                        />

                                        <InputError
                                            class="mt-1.5"
                                            :message="registerForm.errors.last_name"
                                        />
                                    </div>
                                </div>

                                <!-- TOGGLE MIDDLE NAME / SUFFIX -->
                                <div class="pt-0.5">
                                    <button
                                        type="button"
                                        @click="showAdditionalNameFields = !showAdditionalNameFields"
                                        class="text-xs font-semibold text-teal-300 hover:text-white hover:underline inline-flex items-center gap-1"
                                    >
                                        <span>{{ showAdditionalNameFields ? '− Remove middle name or suffix' : '+ Add middle name or suffix' }}</span>
                                    </button>
                                </div>

                                <!-- EXPANDABLE MIDDLE NAME & SUFFIX -->
                                <div v-if="showAdditionalNameFields" class="grid grid-cols-1 gap-3 sm:grid-cols-3 pt-1">
                                    <div class="sm:col-span-2">
                                        <label
                                            for="register-middle-name"
                                            class="block text-sm font-semibold text-white"
                                        >
                                            Middle name
                                            <span class="text-teal-200/60 font-normal">
                                                (optional)
                                            </span>
                                        </label>

                                        <input
                                            id="register-middle-name"
                                            v-model="registerForm.middle_name"
                                            @input="registerForm.middle_initial = registerForm.middle_name ? registerForm.middle_name.trim().charAt(0) : ''; registerForm.clearErrors('middle_name', 'middle_initial'); stepError = ''"
                                            type="text"
                                            maxlength="50"
                                            placeholder="Enter middle name"
                                            class="mt-1.5 block w-full rounded-xl border-0 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 outline-none shadow-inner transition focus:ring-2 focus:ring-[#F5A524]"
                                        />

                                        <InputError
                                            class="mt-1.5"
                                            :message="registerForm.errors.middle_name || registerForm.errors.middle_initial"
                                        />
                                    </div>

                                    <div>
                                        <label
                                            for="register-suffix"
                                            class="block text-sm font-semibold text-white"
                                        >
                                            Suffix
                                            <span class="text-teal-200/60 font-normal">
                                                (optional)
                                            </span>
                                        </label>

                                        <input
                                            id="register-suffix"
                                            v-model="registerForm.suffix"
                                            @input="registerForm.clearErrors('suffix'); stepError = ''"
                                            type="text"
                                            maxlength="20"
                                            placeholder="Jr., Sr., III"
                                            class="mt-1.5 block w-full rounded-xl border-0 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 outline-none shadow-inner transition focus:ring-2 focus:ring-[#F5A524]"
                                        />

                                        <InputError
                                            class="mt-1.5"
                                            :message="registerForm.errors.suffix"
                                        />
                                    </div>
                                </div>

                                <!-- EMAIL -->
                                <div>
                                    <label
                                        for="register-email"
                                        class="block text-sm font-semibold text-white"
                                    >
                                        Email address
                                    </label>

                                    <input
                                        id="register-email"
                                        :disabled="isGoogleOnboarding"
                                        v-model="registerForm.email"
                                        @input="emailInUseAlert = ''; registerForm.clearErrors('email'); stepError = ''"
                                        @blur="registerForm.email = (registerForm.email || '').trim().toLowerCase()"
                                        type="email"
                                        autocomplete="email"
                                        placeholder="you@example.com"
                                        class="mt-1.5 block w-full rounded-xl border-0 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 outline-none shadow-inner transition focus:ring-2 focus:ring-[#F5A524]"
                                    />

                                    <InputError
                                        class="mt-1.5"
                                        :message="registerForm.errors.email"
                                    />
                                </div>

                                <!-- PASSWORD & CONFIRM PASSWORD (Side-by-side) -->
                                <div v-if="!isGoogleOnboarding" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label
                                            for="register-password"
                                            class="block text-sm font-semibold text-white"
                                        >
                                            Password
                                        </label>

                                        <input
                                            id="register-password"
                                            v-model="registerForm.password"
                                            :type="showRegisterPasswords ? 'text' : 'password'"
                                            autocomplete="new-password"
                                            placeholder="At least 8 characters"
                                            class="mt-1.5 block w-full rounded-xl border-0 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 outline-none shadow-inner transition focus:ring-2 focus:ring-[#F5A524]"
                                        />

                                        <InputError
                                            class="mt-1.5"
                                            :message="registerForm.errors.password"
                                        />
                                    </div>

                                    <div>
                                        <label
                                            for="register-password-confirmation"
                                            class="block text-sm font-semibold text-white"
                                        >
                                            Confirm password
                                        </label>

                                        <input
                                            id="register-password-confirmation"
                                            v-model="registerForm.password_confirmation"
                                            :type="showRegisterPasswords ? 'text' : 'password'"
                                            autocomplete="new-password"
                                            placeholder="Repeat password"
                                            class="mt-1.5 block w-full rounded-xl border-0 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 outline-none shadow-inner transition focus:ring-2 focus:ring-[#F5A524]"
                                        />

                                        <InputError
                                            class="mt-1.5"
                                            :message="registerForm.errors.password_confirmation"
                                        />
                                    </div>
                                </div>

                                <!-- SHOW PASSWORDS CHECKBOX -->
                                <div v-if="!isGoogleOnboarding" class="flex items-center gap-2 pt-0.5">
                                    <input
                                        id="register-show-passwords"
                                        v-model="showRegisterPasswords"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-0 bg-white text-[#F5A524] focus:ring-[#F5A524] focus:ring-offset-0"
                                    />
                                    <label
                                        for="register-show-passwords"
                                        class="text-xs text-teal-100/90 select-none cursor-pointer"
                                    >
                                        Show passwords
                                    </label>
                                </div>
                            </div>

                            <!-- ================================================= -->
                            <!-- STEP 2 -->
                            <!-- ================================================= -->

                            <div
                                v-else-if="registrationStep === 2"
                                class="space-y-5"
                            >

                                <!-- SEX (Seller Only) -->

                                <div v-if="isSeller">

                                    <label
                                        for="register-sex"
                                        class="block text-sm font-semibold text-white"
                                    >
                                        Sex
                                    </label>

                                    <select
                                        id="register-sex"
                                        v-model="registerForm.sex"
                                        class="mt-1.5 block w-full rounded-xl border-0 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:ring-2 focus:ring-[#F5A524]"
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
                                        class="block text-sm font-semibold text-white"
                                    >
                                        Birthday
                                    </label>

                                    <input
                                        id="register-birthday"
                                        v-model="registerForm.birthday"
                                        @input="registerForm.clearErrors('birthday', 'age'); stepError = ''"
                                        type="date"
                                        :max="new Date().toISOString().split('T')[0]"
                                        class="mt-1.5 block w-full rounded-xl border-0 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:ring-2 focus:ring-[#F5A524]"
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
                                        class="block text-sm font-semibold text-white"
                                    >
                                        Age
                                    </label>

                                    <input
                                        id="register-age"
                                        v-model="registerForm.age"
                                        type="number"
                                        readonly
                                        placeholder="Automatically calculated"
                                        class="mt-1.5 block w-full rounded-xl border-0 bg-white/80 px-4 py-3 text-sm text-slate-600 outline-none"
                                    />

                                    <p
                                        class="mt-1 text-xs text-teal-200/70"
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
                                        class="block text-sm font-semibold text-white"
                                    >
                                        Contact number
                                    </label>

                                    <input
                                        id="register-contact"
                                        v-model="registerForm.contact_no"
                                        @input="registerForm.clearErrors('contact_no'); stepError = ''"
                                        @blur="normalizeContactNo()"
                                        type="tel"
                                        autocomplete="tel"
                                        placeholder="09XXXXXXXXX"
                                        class="mt-1.5 block w-full rounded-xl border-0 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 outline-none transition focus:ring-2 focus:ring-[#F5A524]"
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

                                <PhilippineAddressSelector :form="registerForm" />

                                <!-- STREET -->

                                <div>

                                    <label
                                        for="register-street"
                                        class="block text-sm font-semibold text-white"
                                    >
                                        House No. / Street Address
                                    </label>

                                    <textarea
                                        id="register-street"
                                        v-model="registerForm.street_address"
                                        rows="3"
                                        placeholder="House number, street, subdivision, etc."
                                        class="mt-1.5 block w-full resize-none rounded-xl border-0 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 outline-none shadow-inner transition focus:ring-2 focus:ring-[#F5A524]"
                                    ></textarea>

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.street_address"
                                    />

                                </div>

                                <!-- POSTAL CODE -->
                                <div>
                                    <label
                                        for="register-postal-code"
                                        class="block text-sm font-semibold text-white"
                                    >
                                        Postal Code
                                        <span class="text-rose-400">*</span>
                                    </label>

                                    <input
                                        id="register-postal-code"
                                        v-model="registerForm.postal_code"
                                        @input="registerForm.clearErrors('postal_code'); stepError = ''"
                                        type="text"
                                        maxlength="4"
                                        placeholder="e.g. 1121"
                                        class="mt-1.5 block w-full rounded-xl border-0 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 outline-none shadow-inner transition focus:ring-2 focus:ring-[#F5A524]"
                                    />

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.postal_code"
                                    />
                                </div>

                                <!-- ADDRESS INFORMATION -->

                                <div
                                    v-if="
                                        registerForm.city_code &&
                                        registerForm.municipality &&
                                        registerForm.barangay
                                    "
                                    class="rounded-xl bg-white/10 border border-white/15 p-4"
                                >

                                    <p
                                        class="text-xs font-semibold uppercase tracking-wide text-teal-300"
                                    >
                                        Selected address
                                    </p>

                                    <p
                                        class="mt-1 text-sm leading-5 text-white"
                                    >
                                        {{ registerForm.barangay }},
                                        {{ registerForm.municipality }},
                                        {{ registerForm.province }}
                                    </p>

                                </div>

                                <!-- TERMS & PRIVACY POLICY (Buyer Step 3) -->
                                <div v-if="!isSeller" class="pt-2">
                                    <label for="register-terms" class="flex cursor-pointer items-start gap-3">
                                        <input
                                            id="register-terms"
                                            v-model="registerForm.terms"
                                            @change="registerForm.clearErrors('terms'); stepError = ''"
                                            type="checkbox"
                                            class="mt-1 h-4 w-4 rounded border-0 bg-white text-[#F5A524] focus:ring-[#F5A524] focus:ring-offset-0"
                                        />
                                        <span class="text-sm leading-5 text-teal-100/90">
                                            I agree to the
                                            <a href="/policies" target="_blank" class="font-semibold text-teal-300 underline hover:text-white">Terms and Conditions</a>
                                            and
                                            <a href="/policies" target="_blank" class="font-semibold text-teal-300 underline hover:text-white">Privacy Policy</a>.
                                            <span class="text-rose-400">*</span>
                                        </span>
                                    </label>
                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.terms"
                                    />
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
                                    class="rounded-xl bg-white/10 border border-white/15 p-4"
                                >

                                    <h3
                                        class="font-semibold text-white"
                                    >
                                        Seller application
                                    </h3>

                                    <p
                                        class="mt-1 text-sm leading-5 text-teal-100/80"
                                    >
                                        Provide your store details and required verification documents to apply as an Alona seller.
                                    </p>

                                </div>

                                <!-- STORE NAME -->

                                <div>

                                    <label
                                        for="register-store-name"
                                        class="block text-sm font-semibold text-white"
                                    >
                                        Store name
                                    </label>

                                    <input
                                        id="register-store-name"
                                        v-model="registerForm.store_name"
                                        type="text"
                                        placeholder="Enter your store name"
                                        class="mt-1.5 block w-full rounded-xl border-0 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 outline-none shadow-inner transition focus:ring-2 focus:ring-[#F5A524]"
                                    />

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.store_name"
                                    />

                                </div>

                                <!-- SELLER TYPE -->
                                <div>
                                    <label
                                        for="register-seller-type"
                                        class="block text-sm font-semibold text-white"
                                    >
                                        Seller Type
                                        <span class="text-rose-400">*</span>
                                    </label>

                                    <select
                                        id="register-seller-type"
                                        v-model="registerForm.seller_type"
                                        @change="registerForm.clearErrors('seller_type'); stepError = ''"
                                        class="mt-1.5 block w-full rounded-xl border-0 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:ring-2 focus:ring-[#F5A524]"
                                    >
                                        <option value="Sole Proprietorship">Sole Proprietorship</option>
                                        <option value="Individual">Individual</option>
                                        <option value="Corporation">Corporation</option>
                                    </select>

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.seller_type"
                                    />
                                </div>

                                <!-- TIN -->
                                <div>
                                    <label
                                        for="register-tin"
                                        class="block text-sm font-semibold text-white"
                                    >
                                        Taxpayer Identification Number (TIN)
                                        <span class="text-rose-400">*</span>
                                    </label>

                                    <input
                                        id="register-tin"
                                        v-model="registerForm.tin"
                                        @input="formatTin"
                                        type="text"
                                        maxlength="15"
                                        placeholder="000-000-000-000"
                                        class="mt-1.5 block w-full rounded-xl border-0 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 outline-none shadow-inner transition focus:ring-2 focus:ring-[#F5A524]"
                                    />

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.tin"
                                    />
                                </div>

                                <!-- LINE OF BUSINESS -->

                                <div>

                                    <label
                                        for="register-line-of-business"
                                        class="block text-sm font-semibold text-white"
                                    >
                                        Line of business
                                    </label>

                                    <input
                                        id="register-line-of-business"
                                        v-model="registerForm.line_of_business"
                                        type="text"
                                        placeholder="e.g. Clothing, Electronics, Food"
                                        class="mt-1.5 block w-full rounded-xl border-0 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 outline-none shadow-inner transition focus:ring-2 focus:ring-[#F5A524]"
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
                                        class="block text-sm font-semibold text-white"
                                    >
                                        Store description
                                        <span class="text-teal-200/60 font-normal">
                                            (optional)
                                        </span>
                                    </label>

                                    <textarea
                                        id="register-store-description"
                                        v-model="registerForm.store_description"
                                        rows="3"
                                        placeholder="Tell customers about your store"
                                        class="mt-1.5 block w-full resize-none rounded-xl border-0 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 outline-none shadow-inner transition focus:ring-2 focus:ring-[#F5A524]"
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
                                        class="block text-sm font-semibold text-white"
                                    >
                                        Bank name
                                        <span class="text-teal-200/60 font-normal">
                                            (optional)
                                        </span>
                                    </label>

                                    <input
                                        id="register-bank-name"
                                        v-model="registerForm.bank_name"
                                        type="text"
                                        placeholder="Bank name"
                                        class="mt-1.5 block w-full rounded-xl border-0 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 outline-none shadow-inner transition focus:ring-2 focus:ring-[#F5A524]"
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
                                        class="block text-sm font-semibold text-white"
                                    >
                                        Bank account name
                                        <span class="text-teal-200/60 font-normal">
                                            (optional)
                                        </span>
                                    </label>

                                    <input
                                        id="register-bank-account-name"
                                        v-model="registerForm.bank_account_name"
                                        type="text"
                                        placeholder="Account holder name"
                                        class="mt-1.5 block w-full rounded-xl border-0 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 outline-none shadow-inner transition focus:ring-2 focus:ring-[#F5A524]"
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
                                        class="block text-sm font-semibold text-white"
                                    >
                                        Bank account number
                                        <span class="text-teal-200/60 font-normal">
                                            (optional)
                                        </span>
                                    </label>

                                    <input
                                        id="register-bank-account-number"
                                        v-model="registerForm.bank_account_number"
                                        type="text"
                                        placeholder="Account number"
                                        class="mt-1.5 block w-full rounded-xl border-0 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 outline-none shadow-inner transition focus:ring-2 focus:ring-[#F5A524]"
                                    />

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.bank_account_number"
                                    />

                                </div>

                                <!-- GOVERNMENT ID TYPE -->

                                <div>

                                    <label
                                        for="seller-id-type"
                                        class="block text-sm font-semibold text-white"
                                    >
                                        Valid Government ID
                                    </label>

                                    <select
                                        id="seller-id-type"
                                        v-model="registerForm.id_type"
                                        class="mt-1.5 block w-full rounded-xl border-0 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:ring-2 focus:ring-[#F5A524]"
                                    >

                                        <option
                                            value=""
                                            disabled
                                        >
                                            Select government ID type
                                        </option>

                                        <option value="Passport">
                                            Passport
                                        </option>

                                        <option value="Driver's License">
                                            Driver's License
                                        </option>

                                        <option value="UMID">
                                            UMID
                                        </option>

                                        <option value="PhilHealth">
                                            PhilHealth ID
                                        </option>

                                        <option value="SSS">
                                            SSS ID
                                        </option>

                                        <option value="Voter's ID">
                                            Voter's ID
                                        </option>

                                        <option value="Postal ID">
                                            Postal ID
                                        </option>

                                        <option value="National ID">
                                            National ID (PhilSys)
                                        </option>

                                    </select>

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.id_type"
                                    />

                                </div>

                                <!-- GOVERNMENT ID NUMBER -->

                                <div>

                                    <label
                                        for="seller-id-number"
                                        class="block text-sm font-semibold text-white"
                                    >
                                        ID Number
                                    </label>

                                    <input
                                        id="seller-id-number"
                                        v-model="registerForm.id_number"
                                        type="text"
                                        placeholder="Enter your ID number"
                                        class="mt-1.5 block w-full rounded-xl border-0 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 outline-none shadow-inner transition focus:ring-2 focus:ring-[#F5A524]"
                                    />

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.id_number"
                                    />

                                </div>

                                <!-- ID FRONT UPLOAD -->

                                <div>

                                    <label
                                        for="seller-id-front"
                                        class="block text-sm font-semibold text-white"
                                    >
                                        ID Front Photo
                                    </label>

                                    <input
                                        id="seller-id-front"
                                        type="file"
                                        accept="image/jpeg,image/png,image/jpg"
                                        @change="handleIdFrontUpload"
                                        class="mt-1.5 block w-full rounded-xl border border-white/20 bg-white/10 px-3 py-2.5 text-sm text-teal-100 file:mr-4 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-1.5 file:font-semibold file:text-slate-900 hover:file:bg-slate-100"
                                    />

                                    <p
                                        class="mt-1 text-xs text-teal-200/70"
                                    >
                                        JPG, JPEG, or PNG. Maximum 5 MB.
                                    </p>

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.id_front"
                                    />

                                </div>

                                <!-- ID BACK UPLOAD -->

                                <div>

                                    <label
                                        for="seller-id-back"
                                        class="block text-sm font-semibold text-white"
                                    >
                                        ID Back Photo
                                        <span class="text-teal-200/60 font-normal">
                                            (optional)
                                        </span>
                                    </label>

                                    <input
                                        id="seller-id-back"
                                        type="file"
                                        accept="image/jpeg,image/png,image/jpg"
                                        @change="handleIdBackUpload"
                                        class="mt-1.5 block w-full rounded-xl border border-white/20 bg-white/10 px-3 py-2.5 text-sm text-teal-100 file:mr-4 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-1.5 file:font-semibold file:text-slate-900 hover:file:bg-slate-100"
                                    />

                                    <p
                                        class="mt-1 text-xs text-teal-200/70"
                                    >
                                        JPG, JPEG, or PNG. Maximum 5 MB.
                                    </p>

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.id_back"
                                    />

                                </div>

                                <!-- MAYOR'S / BUSINESS PERMIT -->
                                <div>
                                    <label
                                        for="seller-business-permit"
                                        class="block text-sm font-semibold text-white"
                                    >
                                        Mayor's / Business Permit
                                        <span class="text-teal-200/60 font-normal">(optional)</span>
                                    </label>

                                    <input
                                        id="seller-business-permit"
                                        type="file"
                                        accept=".jpg,.jpeg,.png,.pdf"
                                        @change="handleBusinessPermitUpload"
                                        class="mt-1.5 block w-full rounded-xl border border-white/20 bg-white/10 px-3 py-2.5 text-sm text-teal-100 file:mr-4 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-1.5 file:font-semibold file:text-slate-900 hover:file:bg-slate-100"
                                    />

                                    <p
                                        class="mt-1 text-xs text-teal-200/70"
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
                                    class="rounded-xl border border-white/15 bg-white/10 p-4 text-sm text-teal-100"
                                >

                                    <p
                                        class="font-semibold text-white"
                                    >
                                        Application review
                                    </p>

                                    <p
                                        class="mt-1 leading-5 text-teal-100/80"
                                    >
                                        Your seller account will be created with
                                        <strong class="text-teal-300">
                                            pending
                                        </strong>
                                        status and will require administrator approval.
                                    </p>

                                </div>

                            </div>

                            <!-- SUCCESS -->

                            <div
                                v-if="registrationSuccess"
                                class="mt-5 rounded-2xl border border-emerald-400/40 bg-emerald-500/20 p-4 text-emerald-100 shadow-sm"
                                role="status"
                            >
                                <div class="flex items-start gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-500/30 text-emerald-200">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="text-sm font-semibold text-white">
                                            Success!
                                        </h4>
                                        <p class="mt-1 text-xs leading-5 text-emerald-200">
                                            {{ registrationSuccess }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- ERROR -->

                            <div
                                v-if="visibleStepError"
                                class="mt-5 rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm leading-relaxed text-rose-800 shadow-sm"
                                role="alert"
                            >
                                {{ visibleStepError }}
                            </div>

                            <!-- NAVIGATION -->
                            <div class="mt-7 flex gap-3">
                                <!-- BACK -->
                                <button
                                    type="button"
                                    @click="previousStep"
                                    :disabled="registerForm.processing"
                                    class="flex-1 rounded-xl bg-white px-4 py-3.5 text-sm font-bold text-slate-800 shadow-sm transition hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    Back
                                </button>

                                <!-- NEXT -->
                                <button
                                    v-if="registrationStep < totalSteps"
                                    type="button"
                                    @click="nextStep"
                                    class="flex-1 rounded-xl bg-[#F5A524] px-4 py-3.5 text-sm font-bold text-slate-900 shadow-md transition hover:bg-[#e0941d] active:scale-[0.99]"
                                >
                                    Next
                                </button>

                                <!-- SUBMIT -->
                                <button
                                    v-else
                                    type="button"
                                    @click="submitRegister"
                                    :disabled="registerForm.processing"
                                    class="flex-1 rounded-xl bg-[#F5A524] px-4 py-3.5 text-sm font-bold text-slate-900 shadow-md transition hover:bg-[#e0941d] active:scale-[0.99] disabled:cursor-not-allowed disabled:opacity-60"
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

                            <!-- REGISTRATION FOOTER -->
                            <div class="mt-6 text-center text-xs text-teal-100/70">
                                Already have an account?
                                <button
                                    type="button"
                                    @click="switchToLogin"
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