<script setup>
import { nextTick, onUnmounted, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import PartnerField from '@/Components/PartnerField.vue'
import LogisticsAddressSelector from '@/Components/LogisticsAddressSelector.vue'
const props = defineProps({ requestKey: String, regions: Array, google: Object })
const failure = ref('')
const errorSummary = ref(null)
const form = useForm({ request_key: props.requestKey, business_name: '', registration_number: '', tin: '',
    first_name: '', last_name: '', position: '', email: props.google?.email ?? '', contact_no: '', password: '', password_confirmation: '',
    region_code: '', province_selection: '', province_code: '', city_code: '', barangay_code: '', street: '', postal_code: '',
    service_region_codes: [], id_doc: null, sec_doc: null, bir_doc: null, permit_doc: null, terms: false, privacy: false })
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
// Inertia's onError handles field validation only, not network or HTML error responses.
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
onUnmounted(() => { clearSubmissionTimer(); removeExceptionListener(); removeInvalidListener() })
const documents = [{ name: 'sec_doc', label: 'SEC certificate' }, { name: 'bir_doc', label: 'BIR Form 2303' },
    { name: 'permit_doc', label: "Mayor’s / business permit" }, { name: 'id_doc', label: 'Authorized representative’s valid ID' }]
function submit() {
    if (form.processing) return
    failure.value = ''
    try {
        // Keep the web session and CSRF cookie on the origin that rendered this form.
        form.post(route('register.logistics', {}, false), { forceFormData: true,
            onStart: watchSubmission, onProgress: watchSubmission, onFinish: clearSubmissionTimer, onError: errors => {
            const regionError = Object.keys(errors).find(key => key.startsWith('service_region_codes.'))
            if (regionError) form.setError('service_region_codes', errors[regionError])
            showFailure(props.google && errors.password ? 'Your Google session expired. Reload this page and sign in again.' :
                `Check the highlighted fields and choose your documents again. ${Object.values(errors).join(' ')}`)
            form.reset('password', 'password_confirmation', 'id_doc', 'sec_doc', 'bir_doc', 'permit_doc')
        } })
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
    <Head title="Logistics registration" />
    <GuestLayout>
        <main class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
            <div class="rounded-2xl border border-[#E5E7EB] bg-white p-6 sm:p-8">
                <img src="/images/Alogo.png" alt="ALONA" class="mb-5 h-14 w-14" />
                <h1 class="text-2xl font-semibold text-[#1F2937]">Register your logistics company</h1>
                <p class="mt-2 max-w-xl text-sm text-[#64748B]">Verify your email, then submit your company for admin review. Approval is required before you can manage deliveries.</p>
                <p v-if="google" class="mt-5 rounded-xl bg-[#E8F7F6] p-4 text-sm">Continuing with Google as {{ google.email }}. Complete your company details below.</p>
                <a v-else :href="route('google.redirect', { role: 'sorting_center' })" class="mt-5 block rounded-xl border border-slate-200 px-4 py-3 text-center text-sm font-semibold text-[#087F8C]">Continue with Google</a>
                <p v-if="failure" ref="errorSummary" role="alert" tabindex="-1" class="mt-5 rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ failure }}</p>
                <form class="mt-8 space-y-8" @submit.prevent="submit" @invalid.capture="failure = 'Complete the required fields with valid values before registering.'">
                    <fieldset><legend class="mb-4 text-lg font-semibold">Company details</legend>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <PartnerField name="business_name" label="Company name" v-model="form.business_name" required :error="form.errors.business_name" />
                            <PartnerField name="registration_number" label="SEC registration number" v-model="form.registration_number" required :error="form.errors.registration_number" />
                            <PartnerField name="tin" label="TIN (000-000-000-000)" v-model="form.tin" required :error="form.errors.tin" />
                        </div>
                        <p class="mt-2 text-sm text-[#64748B]">Your SEC registration number must contain 2 letters followed by 9 digits.</p>
                    </fieldset>
                    <fieldset><legend class="mb-4 text-lg font-semibold">Contact person</legend>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <PartnerField v-for="field in [{name:'first_name',label:'First name'}, {name:'last_name',label:'Last name'}, {name:'position',label:'Position'}, {name:'email',label:'Email',type:'email'}, {name:'contact_no',label:'Philippine mobile number',type:'tel'}]" :key="field.name" v-bind="field" v-model="form[field.name]" :disabled="field.name === 'email' && !!google" required :error="form.errors[field.name]" />
                        </div>
                    </fieldset>
                    <fieldset><legend class="mb-4 text-lg font-semibold">Company address</legend>
                        <LogisticsAddressSelector :form="form" :regions="regions" />
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <PartnerField name="street" label="Street address" v-model="form.street" required :error="form.errors.street" />
                            <PartnerField name="postal_code" label="Postal code" v-model="form.postal_code" required :error="form.errors.postal_code" />
                        </div>
                    </fieldset>
                    <fieldset><legend class="mb-2 text-lg font-semibold">Service areas</legend>
                        <p class="mb-4 text-sm text-[#64748B]">Choose at least one region your company serves.</p>
                        <div class="grid gap-3 sm:grid-cols-2"><label v-for="region in regions" :key="region.code" class="flex items-start gap-3 text-sm"><input v-model="form.service_region_codes" type="checkbox" :value="region.code" class="mt-0.5 rounded text-[#087F8C] focus:ring-[#087F8C]" /><span>{{ region.name }}</span></label></div>
                        <p v-if="form.errors.service_region_codes" role="alert" class="mt-2 text-sm text-red-600">{{ form.errors.service_region_codes }}</p>
                    </fieldset>
                    <fieldset><legend class="mb-2 text-lg font-semibold">Company documents</legend>
                        <p class="mb-4 text-sm text-[#64748B]">Upload a JPG, PNG or PDF for each document, up to 5 MB each. Only you and authorized reviewers can open them.</p>
                        <div class="grid gap-4 sm:grid-cols-2"><PartnerField v-for="field in documents" :key="field.name" v-bind="field" type="file" v-model="form[field.name]" required :error="form.errors[field.name]" /></div>
                    </fieldset>
                    <fieldset v-if="!google"><legend class="mb-2 text-lg font-semibold">Account password</legend>
                        <p class="mb-4 text-sm text-[#64748B]">Use at least 8 characters, including uppercase and lowercase letters, a number and a special character.</p>
                        <div class="grid gap-4 sm:grid-cols-2"><PartnerField name="password" label="Password" type="password" v-model="form.password" required :error="form.errors.password" /><PartnerField name="password_confirmation" label="Confirm password" type="password" v-model="form.password_confirmation" required :error="form.errors.password_confirmation" /></div>
                    </fieldset>
                    <div class="space-y-3 text-sm">
                        <label class="flex items-start gap-3"><input v-model="form.terms" type="checkbox" required class="mt-0.5 rounded text-[#087F8C]" /><span>I accept the <Link href="/policies" class="font-semibold text-[#087F8C]">Terms and Conditions</Link>.</span></label><p v-if="form.errors.terms" role="alert" class="text-red-600">{{ form.errors.terms }}</p>
                        <label class="flex items-start gap-3"><input v-model="form.privacy" type="checkbox" required class="mt-0.5 rounded text-[#087F8C]" /><span>I accept the <Link href="/policies" class="font-semibold text-[#087F8C]">Privacy Policy</Link>.</span></label><p v-if="form.errors.privacy" role="alert" class="text-red-600">{{ form.errors.privacy }}</p>
                    </div>
                    <button type="submit" :disabled="form.processing" class="w-full rounded-xl bg-[#087F8C] px-4 py-3 text-sm font-semibold text-white focus:outline-2 focus:outline-offset-2 focus:outline-[#087F8C] disabled:opacity-50">{{ form.processing ? 'Registering…' : 'Register company' }}</button>
                    <p class="text-center text-sm text-[#64748B]">Already registered? <a href="/?auth=login" class="font-semibold text-[#087F8C]">Log in</a>. <Link :href="route('register.rider')" class="font-semibold text-[#087F8C]">Register as a rider</Link></p>
                </form>
            </div>
        </main>
    </GuestLayout>
</template>
