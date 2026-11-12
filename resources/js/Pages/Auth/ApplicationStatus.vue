<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import PartnerField from '@/Components/PartnerField.vue'
const props = defineProps({ name: String, email: String, role: String, status: String, approvalStatus: String, verified: Boolean, reason: String, destination: String, profile: Object, usesOtp: Boolean })
const page = usePage()
const profileForm = useForm({ contact_no: props.profile?.details?.contact_no ?? '', operating_hours: props.profile?.details?.operating_hours ?? '' })
const otpForm = useForm({ otp: '' })
const resendForm = useForm({})
const resubmitForm = useForm({ id_doc: null, sec_doc: null, bir_doc: null, permit_doc: null })
const documentFields = [{ name: 'sec_doc', label: 'SEC certificate' }, { name: 'bir_doc', label: 'BIR Form 2303' }, { name: 'permit_doc', label: 'Mayor’s / business permit' }, { name: 'id_doc', label: 'Authorized representative’s valid ID' }]
function verify() {
    if (!otpForm.processing) otpForm.post(route('logistics.otp.verify'), { onFinish: () => otpForm.reset('otp') })
}
function resubmit() {
    if (!resubmitForm.processing) resubmitForm.post(route('logistics.resubmit'), { forceFormData: true, onFinish: () => resubmitForm.reset() })
}
</script>
<template>
    <Head title="Application status" /><GuestLayout><main class="mx-auto max-w-2xl px-4 py-12"><section class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <img src="/images/Alogo.png" alt="Alona" class="mb-5 h-14 w-14" /><h1 class="text-2xl font-semibold">Your application</h1><p class="mt-2 text-slate-500">{{ name }} · {{ email }}</p>
        <p class="mt-6 rounded-xl bg-[#E8F7F6] p-4 text-[#087F8C]">Account: {{ status }} · Application: {{ usesOtp && !verified ? 'awaiting email verification' : approvalStatus }}</p>
        <p v-if="page.props.flash?.status" role="status" class="mt-4 text-sm text-[#087F8C]">{{ page.props.flash.status === 'verification-link-sent' ? 'A new verification link has been sent to your email.' : page.props.flash.status }}</p>
        <div v-if="page.props.errors?.email" role="alert" class="mt-4 rounded-2xl bg-[#FEF2F2] px-5 py-4 text-sm leading-relaxed text-[#DC2626]">{{ page.props.errors.email }}</div>
        <div v-if="reason" class="mt-4 rounded-2xl bg-[#FEF2F2] px-5 py-4 text-sm leading-relaxed text-[#DC2626]">Review feedback: {{ reason }}</div>
        <div v-if="!verified && usesOtp" class="mt-6 border-y border-slate-200 py-6">
            <h2 class="text-lg font-semibold">Verify your email</h2><p class="mt-2 text-sm text-slate-500">Enter the six-digit code sent to {{ email }}. It expires after five minutes.</p>
            <form class="mt-4 space-y-3" @submit.prevent="verify"><label for="otp" class="block text-sm font-semibold">Verification code</label><input id="otp" v-model="otpForm.otp" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required :aria-invalid="!!otpForm.errors.otp" aria-describedby="otp-error" class="w-full rounded-xl border-slate-200 text-lg tracking-widest focus:ring-[#087F8C]" /><p id="otp-error" v-if="otpForm.errors.otp || page.props.errors?.otp" role="alert" class="text-sm text-red-600">{{ otpForm.errors.otp || page.props.errors.otp }}</p><button type="submit" :disabled="otpForm.processing" class="rounded-xl bg-[#087F8C] px-5 py-3 text-sm font-semibold text-white disabled:opacity-50">{{ otpForm.processing ? 'Verifying…' : 'Verify email' }}</button></form>
            <button type="button" :disabled="resendForm.processing" @click="resendForm.post(route('logistics.otp.resend'))" class="mt-4 text-sm font-semibold text-[#087F8C] disabled:opacity-50">{{ resendForm.processing ? 'Sending…' : 'Resend code' }}</button><p v-if="resendForm.errors.otp" role="alert" class="mt-2 text-sm text-red-600">{{ resendForm.errors.otp }}</p>
        </div>
        <div v-else-if="!verified" class="mt-5"><p class="text-sm">Check your email for a verification link. Approval and email verification are required before operations.</p><button type="button" @click="router.post(route('verification.send'))" class="mt-3 font-semibold text-[#087F8C]">Resend verification email</button></div>
        <form v-if="usesOtp && verified && status === 'rejected'" class="mt-6 space-y-4 border-y border-slate-200 py-6" @submit.prevent="resubmit">
            <h2 class="text-lg font-semibold">Replace documents and resubmit</h2><p class="text-sm text-slate-500">Address the review feedback above. Replace at least one document with a JPG, PNG or PDF, up to 5 MB each. Documents you leave unchanged stay on your application.</p>
            <PartnerField v-for="field in documentFields" :key="field.name" v-bind="field" type="file" v-model="resubmitForm[field.name]" :error="resubmitForm.errors[field.name]" />
            <button type="submit" :disabled="resubmitForm.processing" class="rounded-xl bg-[#087F8C] px-5 py-3 text-sm font-semibold text-white disabled:opacity-50">{{ resubmitForm.processing ? 'Resubmitting…' : 'Resubmit application' }}</button>
        </form>
        <p v-if="!destination" class="mt-5 text-sm text-slate-500">{{ usesOtp && !verified ? 'Verify your email before your application can be reviewed.' : ['rejected', 'suspended'].includes(status) ? 'Your account currently has no access to operations. Contact support for help with the review.' : status === 'approved' && !verified ? 'Your application is approved. Verify your email to access operations.' : 'Your application is being reviewed. Return here after signing in to check its status.' }}</p>
        <a v-if="destination" :href="destination" class="mt-6 inline-block rounded-xl bg-[#087F8C] px-5 py-3 font-semibold text-white">Open dashboard</a>
        <form v-if="profile && !usesOtp" class="mt-8 space-y-4" @submit.prevent="profileForm.patch(route('partner.profile.update'))"><h2 class="text-lg font-semibold">{{ role === 'rider' ? 'Contact details' : 'Contact and operating hours' }}</h2><PartnerField name="contact_no" label="Philippine mobile number" v-model="profileForm.contact_no" :error="profileForm.errors.contact_no" required /><PartnerField v-if="role !== 'rider'" name="operating_hours" label="Operating hours" v-model="profileForm.operating_hours" :error="profileForm.errors.operating_hours" required /><button :disabled="profileForm.processing" class="rounded-xl border border-[#087F8C] px-4 py-2 text-[#087F8C]">Save details</button></form>
        <div class="mt-8 flex gap-5 text-sm"><Link href="/" class="text-[#087F8C]">Visit marketplace</Link><button type="button" @click="router.post(route('logout'))" class="text-slate-500">Log out</button></div>
    </section></main></GuestLayout>
</template>
