<script setup>
import { computed, onUnmounted, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import PartnerField from '@/Components/PartnerField.vue'
import PhilippineAddressSelector from '@/Components/PhilippineAddressSelector.vue'
import DeliveryCoverageSelector from '@/Components/DeliveryCoverageSelector.vue'
const props = defineProps({ role: String, google: Object })
const rider = computed(() => props.role === 'rider')
const title = computed(() => rider.value ? 'Become a Rider' : 'Become a Logistics Partner')
const failure = ref('')
const removeExceptionListener = router.on('exception', event => {
    event.preventDefault()
    failure.value = 'Unable to reach Alona. Please check your connection and try again.'
})
onUnmounted(removeExceptionListener)
const form = useForm({ first_name: '', middle_name: '', last_name: '', email: props.google?.email ?? '', contact_no: '', password: '', password_confirmation: '', birthday: '',
    address_group: '', region_code: '', province_code: '', city_code: '', barangay_code: '', street: '', house_number: '', subdivision: '', postal_code: '',
    business_name: '', business_type: '', authorized_representative: '', registration_number: '', business_email: '', business_phone: '', services: '', vehicle_types: '', fleet_count: '',
    coverage_city_codes: [], delivery_city_codes: [], operating_hours: '', sorting_center_id: '', vehicle_type: '', vehicle_model: '', plate_number: '', license_number: '', license_class: '', license_codes: [], license_expiry: '',
    id_doc: null, permit_doc: null, license_doc: null, or_cr_doc: null, terms: false, privacy: false })
const motor = computed(() => rider.value && ['motorcycle', 'car', 'van'].includes(form.vehicle_type))
watch(() => form.vehicle_type, () => {
    for (const key of ['license_number', 'license_expiry', 'license_class', 'vehicle_model', 'plate_number']) form[key] = ''
    form.license_codes = []; form.license_doc = null; form.or_cr_doc = null
})
const businesses = [{ value: 'individual', label: 'Individual operator' }, { value: 'sole_proprietor', label: 'Sole proprietor' }, { value: 'partnership', label: 'Partnership' }, { value: 'corporation', label: 'Corporation' }, { value: 'other', label: 'Other' }]
const vehicles = [{ value: 'bicycle', label: 'Bicycle' }, { value: 'motorcycle', label: 'Motorcycle' }, { value: 'car', label: 'Car' }, { value: 'van', label: 'Van' }]
const fileFields = computed(() => [{ name: 'id_doc', label: rider.value ? 'Valid government ID' : 'Authorized representative’s valid ID', required: true },
    ...(!rider.value ? [{ name: 'permit_doc', label: 'Applicable DTI / SEC / business permit documentation (PDF bundle)', required: form.business_type !== 'individual' }] : []),
    ...(motor.value ? [{ name: 'license_doc', label: 'Professional driver’s license', required: true }, { name: 'or_cr_doc', label: 'Vehicle registration (OR / CR)', required: true }] : [])])
function submit() {
    failure.value = ''
    form.post(route(rider.value ? 'register.rider' : 'register.logistics'), { forceFormData: true,
        onError: errors => { failure.value = props.google && errors.password ? 'Your Google sign-in session expired. Reload this page and sign in again to continue.' : 'Please check the highlighted fields and submit again.'; document.querySelector('[role=alert]')?.scrollIntoView({ block: 'center' }) },
    })
}
</script>
<template>
    <Head :title="title" />
    <GuestLayout>
        <main class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
            <div class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm sm:p-8">
                <div class="mb-8 text-center"><img src="/images/Alogo.png" alt="Alona" class="mx-auto mb-4 h-14 w-14 rounded-2xl bg-[#E8F7F6] p-2" /><h1 class="text-2xl font-semibold">{{ title }}</h1><p class="mt-2 text-sm text-[#64748B]">Complete your application. We’ll review your details before you access deliveries.</p></div>

                <!-- ROLE TOGGLE TABS -->
                <div class="mb-6 flex rounded-xl bg-[#F1F5F9] p-1">
                    <Link
                        :href="route('register.logistics')"
                        :class="[
                            'flex-1 rounded-lg py-2.5 text-center text-sm font-semibold transition',
                            !rider ? 'bg-white text-[#087F8C] shadow-sm' : 'text-[#64748B] hover:text-[#1F2937]'
                        ]"
                    >
                        Logistics Partner
                    </Link>
                    <Link
                        :href="route('register.rider')"
                        :class="[
                            'flex-1 rounded-lg py-2.5 text-center text-sm font-semibold transition',
                            rider ? 'bg-white text-[#087F8C] shadow-sm' : 'text-[#64748B] hover:text-[#1F2937]'
                        ]"
                    >
                        Delivery Rider
                    </Link>
                </div>

                <p v-if="failure" role="alert" tabindex="-1" class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-600">{{ failure }}</p>
                <p v-if="google" class="mb-6 rounded-xl bg-[#E8F7F6] p-4 text-sm">Continuing with Google as {{ google.email }}. Complete the details below.</p>
                <a v-else :href="route('google.redirect', { role })" class="mb-6 block rounded-xl border border-slate-200 py-3 text-center text-sm font-semibold">G &nbsp; Continue with Google</a>
                <form @submit.prevent="submit" class="space-y-8">
                    <fieldset><legend class="mb-4 text-lg font-semibold">{{ rider ? 'Personal information' : 'Account information' }}</legend>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <PartnerField v-for="field in [{ name: 'first_name', label: 'First name', required: true }, { name: 'middle_name', label: 'Middle name (optional)' }, { name: 'last_name', label: 'Last name', required: true }, { name: 'contact_no', label: 'Philippine mobile number', type: 'tel', required: true }]" :key="field.name" v-bind="field" v-model="form[field.name]" :error="form.errors[field.name]" />
                            <PartnerField name="email" label="Email address" type="email" v-model="form.email" :disabled="!!google" required :error="form.errors.email" />
                            <PartnerField v-if="rider" name="birthday" label="Date of birth (18 or older)" type="date" v-model="form.birthday" required :error="form.errors.birthday" />
                            <template v-if="!google"><PartnerField name="password" label="Password (at least 8 characters)" type="password" v-model="form.password" required :error="form.errors.password" /><PartnerField name="password_confirmation" label="Confirm password" type="password" v-model="form.password_confirmation" required /></template>
                        </div>
                    </fieldset>
                    <fieldset v-if="!rider"><legend class="mb-4 text-lg font-semibold">Business information</legend>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <PartnerField name="business_name" label="Company / trade name" v-model="form.business_name" required :error="form.errors.business_name" />
                            <PartnerField name="business_type" label="Business type" v-model="form.business_type" :options="businesses" required :error="form.errors.business_type" />
                            <PartnerField v-for="field in [{ name: 'authorized_representative', label: 'Authorized representative' }, { name: 'registration_number', label: 'Registration number', required: form.business_type !== 'individual' }, { name: 'business_email', label: 'Business email', type: 'email' }, { name: 'business_phone', label: 'Business phone number', type: 'tel' }, { name: 'services', label: 'Services offered' }, { name: 'vehicle_types', label: 'Fleet vehicle types' }, { name: 'fleet_count', label: 'Fleet count', type: 'number' }]" :key="field.name" v-bind="field" :required="field.required ?? true" v-model="form[field.name]" :error="form.errors[field.name]" />
                        </div>
                    </fieldset>
                    <div><PhilippineAddressSelector :form="form" />
                        <div class="mt-4 grid gap-4 sm:grid-cols-2"><PartnerField v-for="field in [{ name: 'street', label: 'Street' }, { name: 'house_number', label: 'House / building / lot number' }, { name: 'subdivision', label: 'Subdivision (optional)', required: false }, { name: 'postal_code', label: 'Postal code' }]" :key="field.name" v-bind="field" :required="field.required ?? true" v-model="form[field.name]" :error="form.errors[field.name]" /></div>
                    </div>
                    <fieldset><legend class="mb-4 text-lg font-semibold">{{ rider ? 'Delivery preferences' : 'Delivery operations' }}</legend>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <PartnerField v-if="rider" name="vehicle_type" label="Vehicle type" v-model="form.vehicle_type" :options="vehicles" required :error="form.errors.vehicle_type" />
                            <DeliveryCoverageSelector :form="form" :rider="rider" />
                            <PartnerField v-if="!rider" name="operating_hours" label="Operating hours" v-model="form.operating_hours" required :error="form.errors.operating_hours" />
                            <template v-if="motor">
                                <PartnerField v-for="field in [{ name: 'vehicle_model', label: 'Vehicle model' }, { name: 'plate_number', label: 'Plate number' }, { name: 'license_number', label: 'License number' }, { name: 'license_expiry', label: 'License expiration', type: 'date' }]" :key="field.name" v-bind="field" v-model="form[field.name]" required :error="form.errors[field.name]" />
                                <PartnerField name="license_class" label="License classification" v-model="form.license_class" :options="[{ value: 'professional', label: 'Professional' }]" required :error="form.errors.license_class" />
                                <fieldset><legend class="text-sm font-semibold">Approved license codes</legend><div class="mt-3 flex gap-4"><label v-for="code in ['A','A1','B','B1','B2']" :key="code" class="text-sm"><input v-model="form.license_codes" type="checkbox" :value="code" class="rounded text-[#087F8C]" /> {{ code }}</label></div><p v-if="form.errors.license_codes" class="text-sm text-red-600">{{ form.errors.license_codes }}</p></fieldset>
                            </template>
                        </div>
                        <p v-if="rider" class="mt-3 text-sm text-slate-500">Bicycle riders do not need a driver’s license or vehicle registration. Motor vehicle eligibility and documents are verified during review.</p>
                    </fieldset>
                    <fieldset><legend class="mb-4 text-lg font-semibold">Verification documents</legend><p class="mb-4 text-sm text-[#64748B]">JPG, PNG or PDF, up to 5 MB each. Documents are private and available only to you and authorized reviewers.</p>
                        <div class="grid gap-4 sm:grid-cols-2"><PartnerField v-for="field in fileFields" :key="field.name" v-bind="field" type="file" v-model="form[field.name]" :error="form.errors[field.name]" /></div>
                    </fieldset>
                    <div class="space-y-3 text-sm"><label class="flex items-start gap-3"><input v-model="form.terms" type="checkbox" required class="mt-0.5 rounded text-[#087F8C]" /><span>I accept the <Link href="/policies" class="font-semibold text-[#087F8C]">Terms and Conditions</Link>.</span></label><p v-if="form.errors.terms" class="text-red-600">{{ form.errors.terms }}</p><label class="flex items-start gap-3"><input v-model="form.privacy" type="checkbox" required class="mt-0.5 rounded text-[#087F8C]" /><span>I accept the <Link href="/policies" class="font-semibold text-[#087F8C]">Privacy Policy</Link>.</span></label><p v-if="form.errors.privacy" class="text-red-600">{{ form.errors.privacy }}</p></div>
                    <button type="submit" :disabled="form.processing || (rider ? !form.delivery_city_codes.length || !form.sorting_center_id : !form.coverage_city_codes.length)" class="w-full rounded-xl bg-[#087F8C] px-4 py-3 text-sm font-semibold text-white disabled:opacity-50">{{ form.processing ? 'Submitting application…' : 'Submit application' }}</button>
                    <p class="text-center text-sm text-[#64748B]">Already have an Alona account? <a href="/?auth=login" class="font-semibold text-[#087F8C]">Log in</a></p>
                </form>
            </div>
        </main>
    </GuestLayout>
</template>
