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

const registrationType = ref(null)
const registrationStarted = ref(false)
const registrationStep = ref(1)
const stepError = ref('')
const registrationSuccess = ref('')
const emailInUseAlert = ref('')
const accountCreatedSuccess = ref('')
const accountVerifiedSuccess = ref('')

const showRegisterPassword = ref(false)
const showRegisterPasswordConfirmation = ref(false)
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
    return isSeller.value ? 4 : 2
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

        if (!isSeller.value && !registerForm.terms) {
            registerForm.setError('terms', 'You must agree to the Terms and Privacy Policy.')
            stepError.value = 'You must agree to the Terms and Privacy Policy.'
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

    if (isSeller.value) {
        registerForm.post(route('register.seller'), {
            forceFormData: true,
            onStart: () => {
                stepError.value = ''
                registrationSuccess.value = ''
                emailInUseAlert.value = ''
            },
            onError: (errors) => {
                console.error('Registration validation errors:', JSON.stringify(errors, null, 2))
                if (errors.email) {
                    registrationStep.value = 1
                    emailInUseAlert.value = cleanMessage(errors.email)
                    stepError.value = emailInUseAlert.value
                } else if (errors.password) {
                    registrationStep.value = 1
                    stepError.value = cleanMessage(errors.password)
                } else if (errors.contact_no) {
                    registrationStep.value = 2
                    stepError.value = cleanMessage(errors.contact_no)
                } else {
                    const first = Object.values(errors)[0]
                    stepError.value = cleanMessage(first) || 'Please correct the highlighted fields and try again.'
                }
            },
            onSuccess: () => {
                emailInUseAlert.value = ''
                window.location.href = '/verify-otp'
            },
        })
        return
    }

    // Buyer Registration (In-Modal OTP Flow)
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

        const response = await fetch(route('register'), {
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
        accountCreatedSuccess.value = data.message || 'Account created successfully! A 6-digit verification code has been sent to your email address.'
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
            window.location.assign(data.redirect || route('buyer.dashboard'))
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
            class="fixed inset-0 z-[100] flex items-center justify-center bg-[#1F2937]/55 px-4 py-6 backdrop-blur-sm"
            @click="closeOnBackground"
        >
            <div
                class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-[#E5E7EB] bg-white shadow-2xl"
                ref="dialog" role="dialog" aria-modal="true" aria-label="Alona account" tabindex="-1" @keydown.esc="close" @keydown="trapFocus"
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
                                isVerifyingOtp
                                    ? 'Verify your email'
                                    : mode === 'login'
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
                                isVerifyingOtp
                                    ? `Enter the 6-digit code sent to ${otpEmail || 'your email'}. It expires in 5 minutes.`
                                    : mode === 'login'
                                        ? 'Sign in to continue shopping with Alona.'
                                        : registrationStarted
                                            ? stepDescription
                                            : 'Choose how you want to join Alona.'
                            }}
                        </p>

                    </div>

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
                            class="rounded-2xl border border-emerald-300 bg-emerald-50 p-4 text-emerald-900 shadow-sm"
                            role="status"
                        >
                            <div class="flex items-start gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <h4 class="text-sm font-semibold text-emerald-950">
                                        Account Created Successfully!
                                    </h4>
                                    <p class="mt-1 text-xs leading-5 text-emerald-800">
                                        A 6-digit verification code has been sent to <span class="font-bold underline">{{ otpEmail }}</span>. Please enter it below to activate your account.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- ACCOUNT VERIFIED SUCCESS ALERT -->
                        <div
                            v-if="accountVerifiedSuccess"
                            class="rounded-2xl border border-emerald-300 bg-emerald-50 p-4 text-emerald-900 shadow-sm"
                            role="status"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <h4 class="text-sm font-semibold text-emerald-950">
                                        Email Verified Successfully!
                                    </h4>
                                    <p class="text-xs text-emerald-800 mt-0.5">
                                        {{ accountVerifiedSuccess }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- RESEND OTP SUCCESS NOTICE -->
                        <div
                            v-if="otpSuccess && !accountCreatedSuccess && !accountVerifiedSuccess"
                            class="rounded-xl border border-emerald-200 bg-[#E8F7F6] px-4 py-3 text-sm font-medium text-[#087F8C]"
                        >
                            {{ otpSuccess }}
                        </div>

                        <div
                            v-if="otpError"
                            class="rounded-2xl bg-[#FEF2F2] px-5 py-4 text-sm leading-relaxed text-[#DC2626]"
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
                                    class="block text-sm font-medium text-[#1F2937]"
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
                                    class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-center text-2xl font-bold tracking-[0.5em] text-[#1F2937] outline-none transition placeholder:tracking-normal placeholder:font-normal focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                                />

                                <p class="mt-2 text-xs text-[#94A3B8]">
                                    Please check your inbox (or spam folder) for the 6-digit code.
                                </p>
                            </div>

                            <button
                                type="submit"
                                :disabled="isOtpSubmitting || otpCode.trim().length !== 6"
                                class="w-full rounded-xl bg-[#087F8C] px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#16A6A0] disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {{ isOtpSubmitting ? 'Verifying...' : 'Verify Account' }}
                            </button>
                        </form>

                        <div class="flex items-center justify-between border-t border-[#E5E7EB] pt-5 text-sm">
                            <button
                                type="button"
                                @click="submitModalResendOtp"
                                :disabled="isOtpResending || resendCooldown > 0"
                                class="font-semibold text-[#087F8C] hover:text-[#16A6A0] disabled:cursor-not-allowed disabled:opacity-60"
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
                                class="font-medium text-[#64748B] hover:text-[#1F2937]"
                            >
                                Change details
                            </button>
                        </div>
                    </div>

                    <!-- ================================================= -->
                    <!-- LOGIN -->
                    <!-- ================================================= -->

                    <form
                        v-else-if="mode === 'login'"
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
                                :message="loginForm.errors.email || page.props.errors?.email"
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

                            <div class="relative mt-2">
                                <input
                                    id="login-password"
                                    v-model="loginForm.password"
                                    :type="showLoginPassword ? 'text' : 'password'"
                                    autocomplete="current-password"
                                    required
                                    placeholder="Enter your password"
                                    class="block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 pr-11 text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                                />
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 focus:outline-none"
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
                                    :aria-pressed="registrationType === 'buyer'"
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
                                    :aria-pressed="registrationType === 'seller'"
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
                                class="mt-4 rounded-2xl bg-[#FEF2F2] px-5 py-4 text-sm leading-relaxed text-[#DC2626]"
                                role="alert"
                            >
                                {{ cleanMessage(stepError) }}
                            </div>

                            <!-- ADDRESS ERROR -->

                            <!-- NEXT -->

                            <button
                                type="button"
                                @click="startRegistration"
                                :disabled="!registrationType"
                                class="mt-5 w-full rounded-xl bg-[#087F8C] px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#16A6A0] disabled:cursor-not-allowed disabled:opacity-50"
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
                                        @input="registerForm.clearErrors('first_name'); stepError = ''"
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
                                        @input="registerForm.clearErrors('last_name'); stepError = ''"
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

                                <!-- MIDDLE NAME & SUFFIX -->

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div class="sm:col-span-2">
                                        <label
                                            for="register-middle-name"
                                            class="text-sm font-medium text-[#1F2937]"
                                        >
                                            Middle name
                                            <span class="text-[#94A3B8]">
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
                                            class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                                        />

                                        <InputError
                                            class="mt-2"
                                            :message="registerForm.errors.middle_name || registerForm.errors.middle_initial"
                                        />
                                    </div>

                                    <div>
                                        <label
                                            for="register-suffix"
                                            class="text-sm font-medium text-[#1F2937]"
                                        >
                                            Suffix
                                            <span class="text-[#94A3B8]">
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
                                            class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                                        />

                                        <InputError
                                            class="mt-2"
                                            :message="registerForm.errors.suffix"
                                        />
                                    </div>
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
                                        :disabled="isGoogleOnboarding"
                                        v-model="registerForm.email"
                                        @input="emailInUseAlert = ''; registerForm.clearErrors('email'); stepError = ''"
                                        @blur="registerForm.email = (registerForm.email || '').trim().toLowerCase()"
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

                                <div v-if="!isGoogleOnboarding">

                                    <label
                                        for="register-password"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        Password
                                    </label>

                                    <div class="relative mt-2">
                                        <input
                                            id="register-password"
                                            v-model="registerForm.password"
                                            :type="showRegisterPassword ? 'text' : 'password'"
                                            autocomplete="new-password"
                                            placeholder="At least 8 characters"
                                            class="block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 pr-11 text-sm outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                                        />
                                        <button
                                            type="button"
                                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 focus:outline-none"
                                            :aria-label="showRegisterPassword ? 'Hide password' : 'Show password'"
                                            @click="showRegisterPassword = !showRegisterPassword"
                                        >
                                            <svg v-if="showRegisterPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                            </svg>
                                            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            </svg>
                                        </button>
                                    </div>

                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.password"
                                    />

                                </div>

                                <!-- CONFIRM PASSWORD -->

                                <div v-if="!isGoogleOnboarding">

                                    <label
                                        for="register-password-confirmation"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        Confirm password
                                    </label>

                                    <div class="relative mt-2">
                                        <input
                                            id="register-password-confirmation"
                                            v-model="registerForm.password_confirmation"
                                            :type="showRegisterPasswordConfirmation ? 'text' : 'password'"
                                            autocomplete="new-password"
                                            placeholder="Repeat your password"
                                            class="block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 pr-11 text-sm outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
                                        />
                                        <button
                                            type="button"
                                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 focus:outline-none"
                                            :aria-label="showRegisterPasswordConfirmation ? 'Hide password' : 'Show password'"
                                            @click="showRegisterPasswordConfirmation = !showRegisterPasswordConfirmation"
                                        >
                                            <svg v-if="showRegisterPasswordConfirmation" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                            </svg>
                                            <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            </svg>
                                        </button>
                                    </div>

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

                                <!-- SEX (Seller Only) -->

                                <div v-if="isSeller">

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
                                        @input="registerForm.clearErrors('birthday', 'age'); stepError = ''"
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
                                        @input="registerForm.clearErrors('contact_no'); stepError = ''"
                                        @blur="normalizeContactNo()"
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

                                <!-- TERMS & PRIVACY POLICY -->
                                <div class="pt-2">
                                    <label for="register-terms" class="flex cursor-pointer items-start gap-3">
                                        <input
                                            id="register-terms"
                                            v-model="registerForm.terms"
                                            @change="registerForm.clearErrors('terms'); stepError = ''"
                                            type="checkbox"
                                            class="mt-1 h-4 w-4 rounded border-[#CBD5E1] text-[#087F8C] focus:ring-[#087F8C]"
                                        />
                                        <span class="text-sm leading-5 text-[#475569]">
                                            I agree to the
                                            <a href="/policies" target="_blank" class="font-semibold text-[#087F8C] underline hover:text-[#16A6A0]">Terms and Conditions</a>
                                            and
                                            <a href="/policies" target="_blank" class="font-semibold text-[#087F8C] underline hover:text-[#16A6A0]">Privacy Policy</a>.
                                            <span class="text-red-500">*</span>
                                        </span>
                                    </label>
                                    <InputError
                                        class="mt-2"
                                        :message="registerForm.errors.terms"
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

                                <!-- POSTAL CODE -->
                                <div>
                                    <label
                                        for="register-postal-code"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        Postal Code
                                        <span class="text-red-500">*</span>
                                    </label>

                                    <input
                                        id="register-postal-code"
                                        v-model="registerForm.postal_code"
                                        @input="registerForm.clearErrors('postal_code'); stepError = ''"
                                        type="text"
                                        maxlength="4"
                                        placeholder="e.g. 1121"
                                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
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

                                <!-- SELLER TYPE -->
                                <div>
                                    <label
                                        for="register-seller-type"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        Seller Type
                                        <span class="text-red-500">*</span>
                                    </label>

                                    <select
                                        id="register-seller-type"
                                        v-model="registerForm.seller_type"
                                        @change="registerForm.clearErrors('seller_type'); stepError = ''"
                                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
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
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        Taxpayer Identification Number (TIN)
                                        <span class="text-red-500">*</span>
                                    </label>

                                    <input
                                        id="register-tin"
                                        v-model="registerForm.tin"
                                        @input="formatTin"
                                        type="text"
                                        maxlength="15"
                                        placeholder="000-000-000-000"
                                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]"
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

                                <!-- DTI CERTIFICATE (Sole Proprietorship) -->
                                <div v-if="registerForm.seller_type === 'Sole Proprietorship'">
                                    <label
                                        for="seller-dti-cert"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        DTI Certificate of Business Name Registration
                                        <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        id="seller-dti-cert"
                                        type="file"
                                        accept=".jpg,.jpeg,.png,.pdf"
                                        @change="handleDtiUpload"
                                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-3 text-sm text-[#64748B] file:mr-4 file:rounded-lg file:border-0 file:bg-[#E8F7F6] file:px-3 file:py-2 file:font-medium file:text-[#087F8C] hover:file:bg-[#D7F1EF]"
                                    />
                                    <p class="mt-1 text-xs text-[#94A3B8]">JPG, JPEG, PNG, or PDF. Maximum 5 MB.</p>
                                    <InputError class="mt-2" :message="registerForm.errors.dti_certificate" />
                                </div>

                                <!-- SEC CERTIFICATE (Corporation) -->
                                <div v-if="registerForm.seller_type === 'Corporation'">
                                    <label
                                        for="seller-sec-cert"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        SEC Certificate of Registration
                                        <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        id="seller-sec-cert"
                                        type="file"
                                        accept=".jpg,.jpeg,.png,.pdf"
                                        @change="handleSecUpload"
                                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-3 text-sm text-[#64748B] file:mr-4 file:rounded-lg file:border-0 file:bg-[#E8F7F6] file:px-3 file:py-2 file:font-medium file:text-[#087F8C] hover:file:bg-[#D7F1EF]"
                                    />
                                    <p class="mt-1 text-xs text-[#94A3B8]">JPG, JPEG, PNG, or PDF. Maximum 5 MB.</p>
                                    <InputError class="mt-2" :message="registerForm.errors.sec_certificate" />
                                </div>

                                <!-- BIR FORM 2303 -->
                                <div v-if="['Sole Proprietorship', 'Corporation'].includes(registerForm.seller_type)">
                                    <label
                                        for="seller-bir-cert"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        BIR Certificate of Registration (Form 2303)
                                        <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        id="seller-bir-cert"
                                        type="file"
                                        accept=".jpg,.jpeg,.png,.pdf"
                                        @change="handleBirUpload"
                                        class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3 py-3 text-sm text-[#64748B] file:mr-4 file:rounded-lg file:border-0 file:bg-[#E8F7F6] file:px-3 file:py-2 file:font-medium file:text-[#087F8C] hover:file:bg-[#D7F1EF]"
                                    />
                                    <p class="mt-1 text-xs text-[#94A3B8]">JPG, JPEG, PNG, or PDF. Maximum 5 MB.</p>
                                    <InputError class="mt-2" :message="registerForm.errors.bir_2303" />
                                </div>

                                <!-- MAYOR'S / BUSINESS PERMIT -->
                                <div>
                                    <label
                                        for="seller-business-permit"
                                        class="text-sm font-medium text-[#1F2937]"
                                    >
                                        Mayor's / Business Permit
                                        <span class="text-slate-400 font-normal">(optional)</span>
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
                                class="mt-5 rounded-2xl border border-emerald-300 bg-emerald-50 p-4 text-emerald-900 shadow-sm"
                                role="status"
                            >
                                <div class="flex items-start gap-3">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="text-sm font-semibold text-emerald-950">
                                            Success!
                                        </h4>
                                        <p class="mt-1 text-xs leading-5 text-emerald-800">
                                            {{ registrationSuccess }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- ERROR -->

                            <div
                                v-if="visibleStepError"
                                class="mt-5 rounded-2xl bg-[#FEF2F2] px-5 py-4 text-sm leading-relaxed text-[#DC2626]"
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
                        v-if="!registrationStarted && !isVerifyingOtp"
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
                        v-if="!registrationStarted && !isVerifyingOtp"
                        :href="route('google.redirect', { role: mode === 'login' ? undefined : registrationType || 'buyer' })"
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
                        v-if="!registrationStarted && !isVerifyingOtp"
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
                            <button v-if="mode !== 'login'" type="button" @click="switchToLogin" class="ml-1 font-semibold text-[#087F8C] hover:text-[#16A6A0]">Log in</button>
                        </p>

                        <button
                            v-if="mode === 'login'"
                            type="button"
                            @click="switchToRegister"
                            class="mt-2 text-sm font-semibold text-[#087F8C] hover:text-[#16A6A0]"
                        >
                            Create an account
                        </button>


                    </div>

                </div>
            </div>
        </div>
    </Teleport>
</template>
