<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
    mustVerifyEmail: {
        type: Boolean,
        default: false,
    },
    status: {
        type: String,
        default: '',
    },
    account: {
        type: Object,
        default: () => ({}),
    },
    verification: {
        type: Object,
        default: () => ({}),
    },
    sessions: {
        type: Array,
        default: () => [],
    },
})

const page = usePage()
const activeTab = ref('profile')

const flashStatus = computed(() => props.status || page.props.flash?.status || '')

/*
|--------------------------------------------------------------------------
| Profile & Address Form
|--------------------------------------------------------------------------
*/
const profilePhotoInput = ref(null)
const profilePhotoPreview = ref('')

const savedAvatarUrl = computed(() => {
    const raw = props.account?.avatar || page.props.auth?.user?.avatar || ''
    if (!raw) return ''
    if (
        String(raw).startsWith('http://') ||
        String(raw).startsWith('https://') ||
        String(raw).startsWith('data:')
    ) {
        return String(raw)
    }
    return String(raw).startsWith('/')
        ? String(raw)
        : `/storage/${String(raw).replace(/^storage\//, '')}`
})

const displayAvatar = computed(() => profilePhotoPreview.value || savedAvatarUrl.value)

const initials = computed(() => {
    const first = String(props.account?.first_name || props.account?.name || 'S')
        .trim()
        .charAt(0)
    const last = String(props.account?.last_name || '')
        .trim()
        .charAt(0)
    return `${first}${last}`.toUpperCase() || 'S'
})

const profileForm = useForm({
    first_name: props.account?.first_name || '',
    middle_initial: props.account?.middle_initial || '',
    last_name: props.account?.last_name || '',
    sex: props.account?.sex || 'Male',
    birthday: props.account?.birthday || '',
    email: props.account?.email || '',
    contact_no: props.account?.contact_no || '',
    province: props.account?.province || '',
    municipality: props.account?.municipality || '',
    barangay: props.account?.barangay || '',
    street_address: props.account?.street_address || '',
    profile_photo: null,
})

const handleProfilePhotoChange = (event) => {
    const file = event.target.files?.[0] || null
    profileForm.profile_photo = file

    if (profilePhotoPreview.value) {
        URL.revokeObjectURL(profilePhotoPreview.value)
        profilePhotoPreview.value = ''
    }

    if (file && file.type.startsWith('image/')) {
        profilePhotoPreview.value = URL.createObjectURL(file)
    }
}

onBeforeUnmount(() => {
    if (profilePhotoPreview.value) {
        URL.revokeObjectURL(profilePhotoPreview.value)
    }
})

// PSGC Philippine Address lookup with graceful text input fallback
const provinces = ref([])
const cities = ref([])
const barangays = ref([])
const useManualAddress = ref(false)

const loadProvinces = async () => {
    try {
        const res = await fetch('/api/philippines/provinces')
        if (!res.ok) throw new Error('Failed')
        const data = await res.json()
        if (Array.isArray(data) && data.length > 0) {
            provinces.value = data
        } else {
            useManualAddress.value = true
        }
    } catch {
        useManualAddress.value = true
    }
}

onMounted(() => {
    loadProvinces()
})

const submitProfile = () => {
    profileForm.post(route('seller.account.profile.update'), {
        preserveScroll: true,
        forceFormData: true,
    })
}

/*
|--------------------------------------------------------------------------
| Business Verification & Documents Form (SELLER-67)
|--------------------------------------------------------------------------
*/
const permitInput = ref(null)
const govIdInput = ref(null)

const verificationForm = useForm({
    line_of_business: props.verification?.line_of_business || props.account?.line_of_business || '',
    business_permit: null,
    government_id: null,
})

const selectedPermitName = ref('')
const selectedGovIdName = ref('')

const handlePermitSelect = (event) => {
    const file = event.target.files?.[0] || null
    verificationForm.business_permit = file
    selectedPermitName.value = file ? file.name : ''
}

const handleGovIdSelect = (event) => {
    const file = event.target.files?.[0] || null
    verificationForm.government_id = file
    selectedGovIdName.value = file ? file.name : ''
}

const submitVerification = () => {
    verificationForm.post(route('seller.account.documents.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            verificationForm.business_permit = null
            verificationForm.government_id = null
            selectedPermitName.value = ''
            selectedGovIdName.value = ''
            if (permitInput.value) permitInput.value.value = ''
            if (govIdInput.value) govIdInput.value.value = ''
        },
    })
}

const verificationBadge = computed(() => {
    const st = String(props.verification?.status || props.account?.status || 'pending').toLowerCase()
    if (st === 'approved') {
        return {
            label: 'Verified Merchant',
            classes: 'bg-[#EAF8F2] text-[#18794E] ring-1 ring-[#BFE8D4]',
            dot: 'bg-[#22A06B]',
        }
    }
    if (st === 'rejected') {
        return {
            label: 'Verification Rejected',
            classes: 'bg-[#FFF0F0] text-[#C94C4C] ring-1 ring-[#F3C5C5]',
            dot: 'bg-[#E85D5D]',
        }
    }
    return {
        label: 'Pending Verification',
        classes: 'bg-[#FFF8E7] text-[#9A6A08] ring-1 ring-[#F2D58A]',
        dot: 'bg-[#F4B942]',
    }
})

/*
|--------------------------------------------------------------------------
| Password & Login Sessions (SELLER-66)
|--------------------------------------------------------------------------
*/
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const submitPassword = () => {
    passwordForm.put(route('seller.account.password.update'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    })
}

const confirmingLogoutOther = ref(false)
const otherSessionsPasswordInput = ref(null)
const revokingSessionId = ref(null)

const revokeOthersForm = useForm({
    password: '',
})

const openLogoutOtherModal = () => {
    confirmingLogoutOther.value = true
    nextTick(() => otherSessionsPasswordInput.value?.focus())
}

const closeLogoutOtherModal = () => {
    confirmingLogoutOther.value = false
    revokeOthersForm.reset()
    revokeOthersForm.clearErrors()
}

const submitRevokeOtherSessions = () => {
    revokeOthersForm.post(route('seller.account.sessions.revoke-other'), {
        preserveScroll: true,
        onSuccess: () => closeLogoutOtherModal(),
        onError: () => otherSessionsPasswordInput.value?.focus(),
        onFinish: () => revokeOthersForm.reset(),
    })
}

const revokeSingleSession = (sessionId) => {
    if (!confirm('Are you sure you want to revoke and sign out this device session?')) {
        return
    }
    revokingSessionId.value = sessionId
    useForm({}).delete(route('seller.account.sessions.revoke', sessionId), {
        preserveScroll: true,
        onFinish: () => {
            revokingSessionId.value = null
        },
    })
}

/*
|--------------------------------------------------------------------------
| Account Deletion
|--------------------------------------------------------------------------
*/
const confirmingAccountDeletion = ref(false)
const deletePasswordInput = ref(null)

const deleteForm = useForm({
    password: '',
})

const openDeleteModal = () => {
    confirmingAccountDeletion.value = true
    nextTick(() => deletePasswordInput.value?.focus())
}

const closeDeleteModal = () => {
    confirmingAccountDeletion.value = false
    deleteForm.reset()
    deleteForm.clearErrors()
}

const submitDeleteAccount = () => {
    deleteForm.delete(route('seller.account.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeDeleteModal(),
        onError: () => deletePasswordInput.value?.focus(),
    })
}
</script>

<template>
    <Head title="Seller Account & Security" />

    <SellerLayout>
        <div class="mx-auto max-w-6xl space-y-6 pb-12">
            <!-- ========================================================= -->
            <!-- TOP SELLER ACCOUNT BANNER -->
            <!-- ========================================================= -->
            <div class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-xs">
                <div class="bg-gradient-to-r from-[#043E44] via-[#087F8C] to-[#0A9AA9] px-6 py-6 text-white">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-4">
                            <div class="relative h-16 w-16 shrink-0 overflow-hidden rounded-2xl border-2 border-white/40 bg-white/15 shadow-md">
                                <img
                                    v-if="displayAvatar"
                                    :src="displayAvatar"
                                    :alt="account.name"
                                    class="h-full w-full object-cover"
                                />
                                <div
                                    v-else
                                    class="flex h-full w-full items-center justify-center text-xl font-bold text-white"
                                >
                                    {{ initials }}
                                </div>
                            </div>

                            <div>
                                <div class="flex flex-wrap items-center gap-2.5">
                                    <h1 class="text-xl font-bold tracking-tight text-white sm:text-2xl">
                                        {{ account.name || 'Seller Account' }}
                                    </h1>
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold"
                                        :class="verificationBadge.classes"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full" :class="verificationBadge.dot"></span>
                                        {{ verificationBadge.label }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-white/80 sm:text-sm">
                                    {{ account.store_name ? `${account.store_name} • ` : '' }}{{ account.email }}
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <div class="rounded-xl border border-white/15 bg-white/10 px-4 py-2.5 backdrop-blur-xs">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">
                                    Active Sessions
                                </p>
                                <p class="mt-0.5 text-lg font-bold text-white">
                                    {{ sessions.length }} {{ sessions.length === 1 ? 'Device' : 'Devices' }}
                                </p>
                            </div>

                            <Link
                                :href="route('seller.store')"
                                class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-xs font-bold text-[#043E44] shadow-xs transition hover:bg-[#F8FAF9]"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-4 w-4 text-[#087F8C]"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M3 10l2-6h14l2 6M4 10v9a2 2 0 002 2h12a2 2 0 002-2v-9M3 10h18"
                                    />
                                </svg>
                                Store Profile
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- NAVIGATION TABS -->
                <div class="flex flex-wrap border-t border-[#E5E7EB] bg-[#F8FAF9] px-4 sm:px-6">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 border-b-2 px-4 py-3.5 text-xs font-bold transition sm:text-sm"
                        :class="
                            activeTab === 'profile'
                                ? 'border-[#087F8C] text-[#087F8C]'
                                : 'border-transparent text-slate-500 hover:text-slate-800'
                        "
                        @click="activeTab = 'profile'"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"
                            />
                        </svg>
                        Personal &amp; Address
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center gap-2 border-b-2 px-4 py-3.5 text-xs font-bold transition sm:text-sm"
                        :class="
                            activeTab === 'verification'
                                ? 'border-[#087F8C] text-[#087F8C]'
                                : 'border-transparent text-slate-500 hover:text-slate-800'
                        "
                        @click="activeTab = 'verification'"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"
                            />
                        </svg>
                        Business Verification &amp; Permits
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center gap-2 border-b-2 px-4 py-3.5 text-xs font-bold transition sm:text-sm"
                        :class="
                            activeTab === 'security'
                                ? 'border-[#087F8C] text-[#087F8C]'
                                : 'border-transparent text-slate-500 hover:text-slate-800'
                        "
                        @click="activeTab = 'security'"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"
                            />
                        </svg>
                        Password &amp; Login Sessions
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center gap-2 border-b-2 px-4 py-3.5 text-xs font-bold transition sm:text-sm"
                        :class="
                            activeTab === 'danger'
                                ? 'border-red-600 text-red-600'
                                : 'border-transparent text-slate-500 hover:text-red-600'
                        "
                        @click="activeTab = 'danger'"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
                            />
                        </svg>
                        Account Closure
                    </button>
                </div>
            </div>

            <!-- FLASH STATUS BANNER -->
            <div
                v-if="flashStatus"
                class="flex items-center gap-3 rounded-xl border border-[#BFE8D4] bg-[#EAF8F2] px-4 py-3 text-sm font-semibold text-[#18794E]"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-5 w-5 shrink-0 text-[#22A06B]"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ flashStatus }}</span>
            </div>

            <!-- ========================================================= -->
            <!-- TAB 1: PERSONAL & ADDRESS DETAILS -->
            <!-- ========================================================= -->
            <form
                v-show="activeTab === 'profile'"
                class="space-y-6"
                @submit.prevent="submitProfile"
            >
                <div class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-xs">
                    <div class="flex flex-col gap-4 border-b border-[#E5E7EB] pb-5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">
                                Seller Personal Information
                            </h2>
                            <p class="mt-1 text-xs text-slate-500">
                                Manage your owner identity, profile avatar, and primary contact number.
                            </p>
                        </div>

                        <div class="flex items-center gap-3">
                            <input
                                ref="profilePhotoInput"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="hidden"
                                @change="handleProfilePhotoChange"
                            />
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl border border-[#E5E7EB] bg-white px-3.5 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50"
                                @click="profilePhotoInput?.click()"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-4 w-4 text-[#087F8C]"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"
                                    />
                                </svg>
                                Change Avatar
                            </button>
                        </div>
                    </div>

                    <InputError :message="profileForm.errors.profile_photo" class="mt-2" />

                    <div class="mt-6 grid gap-5 sm:grid-cols-3">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                                First Name *
                            </label>
                            <input
                                v-model="profileForm.first_name"
                                type="text"
                                required
                                class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#087F8C] focus:ring-[#087F8C]"
                            />
                            <InputError :message="profileForm.errors.first_name" class="mt-1" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                                Middle Initial
                            </label>
                            <input
                                v-model="profileForm.middle_initial"
                                type="text"
                                maxlength="2"
                                class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#087F8C] focus:ring-[#087F8C]"
                            />
                            <InputError :message="profileForm.errors.middle_initial" class="mt-1" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                                Last Name *
                            </label>
                            <input
                                v-model="profileForm.last_name"
                                type="text"
                                required
                                class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#087F8C] focus:ring-[#087F8C]"
                            />
                            <InputError :message="profileForm.errors.last_name" class="mt-1" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                                Sex
                            </label>
                            <select
                                v-model="profileForm.sex"
                                class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#087F8C] focus:ring-[#087F8C]"
                            >
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                            <InputError :message="profileForm.errors.sex" class="mt-1" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                                Birthday
                            </label>
                            <input
                                v-model="profileForm.birthday"
                                type="date"
                                class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#087F8C] focus:ring-[#087F8C]"
                            />
                            <InputError :message="profileForm.errors.birthday" class="mt-1" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                                Contact Number *
                            </label>
                            <input
                                v-model="profileForm.contact_no"
                                type="text"
                                required
                                placeholder="09XXXXXXXXX"
                                class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#087F8C] focus:ring-[#087F8C]"
                            />
                            <InputError :message="profileForm.errors.contact_no" class="mt-1" />
                        </div>

                        <div class="sm:col-span-3">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                                Email Address *
                            </label>
                            <input
                                v-model="profileForm.email"
                                type="email"
                                required
                                class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#087F8C] focus:ring-[#087F8C]"
                            />
                            <InputError :message="profileForm.errors.email" class="mt-1" />
                        </div>
                    </div>
                </div>

                <!-- BUSINESS & PICKUP ADDRESS -->
                <div class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-xs">
                    <div class="border-b border-[#E5E7EB] pb-4">
                        <h2 class="text-base font-bold text-slate-900">
                            Business &amp; Pickup Address
                        </h2>
                        <p class="mt-1 text-xs text-slate-500">
                            Used for waybill sender routing, courier pickup scheduling, and return shipments.
                        </p>
                    </div>

                    <div class="mt-5 grid gap-5 sm:grid-cols-3">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                                Province
                            </label>
                            <input
                                v-model="profileForm.province"
                                type="text"
                                placeholder="e.g. Laguna"
                                class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#087F8C] focus:ring-[#087F8C]"
                            />
                            <InputError :message="profileForm.errors.province" class="mt-1" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                                City / Municipality
                            </label>
                            <input
                                v-model="profileForm.municipality"
                                type="text"
                                placeholder="e.g. Calamba City"
                                class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#087F8C] focus:ring-[#087F8C]"
                            />
                            <InputError :message="profileForm.errors.municipality" class="mt-1" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                                Barangay
                            </label>
                            <input
                                v-model="profileForm.barangay"
                                type="text"
                                placeholder="e.g. Brgy. Real"
                                class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#087F8C] focus:ring-[#087F8C]"
                            />
                            <InputError :message="profileForm.errors.barangay" class="mt-1" />
                        </div>

                        <div class="sm:col-span-3">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                                Street / Building / Unit Address
                            </label>
                            <input
                                v-model="profileForm.street_address"
                                type="text"
                                placeholder="House/Unit No., Street Name, Subdivision/Village"
                                class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#087F8C] focus:ring-[#087F8C]"
                            />
                            <InputError :message="profileForm.errors.street_address" class="mt-1" />
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3 border-t border-[#E5E7EB] pt-4">
                        <button
                            type="submit"
                            :disabled="profileForm.processing"
                            class="inline-flex items-center gap-2 rounded-xl bg-[#087F8C] px-5 py-2.5 text-xs font-bold text-white shadow-xs transition hover:bg-[#066873] disabled:opacity-50"
                        >
                            {{ profileForm.processing ? 'Saving Changes...' : 'Save Personal & Address Info' }}
                        </button>
                    </div>
                </div>
            </form>

            <!-- ========================================================= -->
            <!-- TAB 2: BUSINESS VERIFICATION & PERMITS (SELLER-67) -->
            <!-- ========================================================= -->
            <form
                v-show="activeTab === 'verification'"
                class="space-y-6"
                @submit.prevent="submitVerification"
            >
                <!-- VERIFICATION STATUS CARD -->
                <div class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-xs">
                    <div class="flex flex-col justify-between gap-4 border-b border-[#E5E7EB] pb-5 sm:flex-row sm:items-center">
                        <div>
                            <div class="flex items-center gap-2.5">
                                <h2 class="text-base font-bold text-slate-900">
                                    Business Verification &amp; Compliance Status
                                </h2>
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-0.5 text-xs font-bold"
                                    :class="verificationBadge.classes"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full" :class="verificationBadge.dot"></span>
                                    {{ verificationBadge.label }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                Keep your DTI/Mayor's Business Permit and Government Valid ID up to date for platform compliance.
                            </p>
                        </div>

                        <div v-if="verification.last_updated_at" class="text-xs text-slate-400">
                            Last updated: <strong class="text-slate-600">{{ verification.last_updated_at }}</strong>
                        </div>
                    </div>

                    <!-- REJECTION NOTICE IF REJECTED -->
                    <div
                        v-if="verification.status === 'rejected' && verification.rejection_reason"
                        class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-xs text-red-800"
                    >
                        <p class="font-bold uppercase tracking-wider text-red-900">
                            Admin Compliance Feedback:
                        </p>
                        <p class="mt-1">
                            {{ verification.rejection_reason }}
                        </p>
                        <p class="mt-2 font-semibold text-red-700">
                            Re-uploading your updated Business Permit or Valid ID below will automatically re-submit your application for Admin review.
                        </p>
                    </div>

                    <!-- LINE OF BUSINESS -->
                    <div class="mt-6">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                            Registered Line of Business *
                        </label>
                        <input
                            v-model="verificationForm.line_of_business"
                            type="text"
                            required
                            placeholder="e.g. Retail Electronics, Apparel & Fashion, Food & Beverage"
                            class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#087F8C] focus:ring-[#087F8C]"
                        />
                        <InputError :message="verificationForm.errors.line_of_business" class="mt-1" />
                    </div>

                    <!-- DOCUMENT CARDS -->
                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <!-- BUSINESS PERMIT -->
                        <div class="flex flex-col justify-between rounded-xl border border-slate-200 bg-[#F8FAF9] p-5">
                            <div>
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]">
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="h-5 w-5"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
                                                />
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="text-sm font-bold text-slate-900">
                                                Business Permit (DTI / Mayor's Permit)
                                            </h3>
                                            <p class="mt-0.5 text-xs text-slate-500">
                                                Accepted formats: PDF, JPG, PNG (Max 5MB)
                                            </p>
                                        </div>
                                    </div>

                                    <span
                                        class="rounded-full px-2.5 py-0.5 text-[11px] font-bold"
                                        :class="
                                            verification.has_business_permit
                                                ? 'bg-[#EAF8F2] text-[#18794E]'
                                                : 'bg-amber-50 text-amber-700'
                                        "
                                    >
                                        {{ verification.has_business_permit ? 'On File' : 'Missing' }}
                                    </span>
                                </div>

                                <div v-if="verification.business_permit_url" class="mt-4">
                                    <a
                                        :href="verification.business_permit_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="inline-flex items-center gap-1.5 text-xs font-bold text-[#087F8C] hover:underline"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-4 w-4"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"
                                            />
                                        </svg>
                                        View Current Business Permit
                                    </a>
                                </div>
                            </div>

                            <div class="mt-4 border-t border-slate-200 pt-4">
                                <label class="block text-xs font-bold text-slate-700">
                                    Upload / Replace Business Permit
                                </label>
                                <input
                                    ref="permitInput"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.pdf"
                                    class="mt-1.5 block w-full text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-[#087F8C] file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-white hover:file:bg-[#066873]"
                                    @change="handlePermitSelect"
                                />
                                <p v-if="selectedPermitName" class="mt-1 text-xs font-semibold text-[#087F8C]">
                                    Selected: {{ selectedPermitName }}
                                </p>
                                <InputError :message="verificationForm.errors.business_permit" class="mt-1" />
                            </div>
                        </div>

                        <!-- GOVERNMENT VALID ID -->
                        <div class="flex flex-col justify-between rounded-xl border border-slate-200 bg-[#F8FAF9] p-5">
                            <div>
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]">
                                            <svg
                                                xmlns="http://www.w3.org/2000/svg"
                                                class="h-5 w-5"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z"
                                                />
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="text-sm font-bold text-slate-900">
                                                Government-Issued Valid ID
                                            </h3>
                                            <p class="mt-0.5 text-xs text-slate-500">
                                                Accepted formats: PDF, JPG, PNG (Max 5MB)
                                            </p>
                                        </div>
                                    </div>

                                    <span
                                        class="rounded-full px-2.5 py-0.5 text-[11px] font-bold"
                                        :class="
                                            verification.has_government_id
                                                ? 'bg-[#EAF8F2] text-[#18794E]'
                                                : 'bg-amber-50 text-amber-700'
                                        "
                                    >
                                        {{ verification.has_government_id ? 'On File' : 'Missing' }}
                                    </span>
                                </div>

                                <div v-if="verification.government_id_url" class="mt-4">
                                    <a
                                        :href="verification.government_id_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="inline-flex items-center gap-1.5 text-xs font-bold text-[#087F8C] hover:underline"
                                    >
                                        <svg
                                            xmlns="http://www.w3.org/2000/svg"
                                            class="h-4 w-4"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"
                                            />
                                        </svg>
                                        View Current Government ID
                                    </a>
                                </div>
                            </div>

                            <div class="mt-4 border-t border-slate-200 pt-4">
                                <label class="block text-xs font-bold text-slate-700">
                                    Upload / Replace Government ID
                                </label>
                                <input
                                    ref="govIdInput"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.pdf"
                                    class="mt-1.5 block w-full text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-[#087F8C] file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-white hover:file:bg-[#066873]"
                                    @change="handleGovIdSelect"
                                />
                                <p v-if="selectedGovIdName" class="mt-1 text-xs font-semibold text-[#087F8C]">
                                    Selected: {{ selectedGovIdName }}
                                </p>
                                <InputError :message="verificationForm.errors.government_id" class="mt-1" />
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3 border-t border-[#E5E7EB] pt-4">
                        <button
                            type="submit"
                            :disabled="verificationForm.processing"
                            class="inline-flex items-center gap-2 rounded-xl bg-[#087F8C] px-5 py-2.5 text-xs font-bold text-white shadow-xs transition hover:bg-[#066873] disabled:opacity-50"
                        >
                            {{ verificationForm.processing ? 'Uploading & Saving...' : 'Save Verification Documents' }}
                        </button>
                    </div>
                </div>
            </form>

            <!-- ========================================================= -->
            <!-- TAB 3: PASSWORD & ACTIVE LOGIN SESSIONS (SELLER-66) -->
            <!-- ========================================================= -->
            <div v-show="activeTab === 'security'" class="space-y-6">
                <!-- CHANGE PASSWORD CARD -->
                <form
                    class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-xs"
                    @submit.prevent="submitPassword"
                >
                    <div class="border-b border-[#E5E7EB] pb-4">
                        <h2 class="text-base font-bold text-slate-900">
                            Update Account Password
                        </h2>
                        <p class="mt-1 text-xs text-slate-500">
                            Ensure your seller portal uses a strong, unique password to protect your payouts and inventory.
                        </p>
                    </div>

                    <div class="mt-5 grid gap-5 sm:grid-cols-3">
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                                Current Password *
                            </label>
                            <input
                                v-model="passwordForm.current_password"
                                type="password"
                                required
                                autocomplete="current-password"
                                class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#087F8C] focus:ring-[#087F8C]"
                            />
                            <InputError :message="passwordForm.errors.current_password" class="mt-1" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                                New Password *
                            </label>
                            <input
                                v-model="passwordForm.password"
                                type="password"
                                required
                                autocomplete="new-password"
                                class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#087F8C] focus:ring-[#087F8C]"
                            />
                            <InputError :message="passwordForm.errors.password" class="mt-1" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">
                                Confirm New Password *
                            </label>
                            <input
                                v-model="passwordForm.password_confirmation"
                                type="password"
                                required
                                autocomplete="new-password"
                                class="mt-1.5 w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#087F8C] focus:ring-[#087F8C]"
                            />
                            <InputError :message="passwordForm.errors.password_confirmation" class="mt-1" />
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end border-t border-[#E5E7EB] pt-4">
                        <button
                            type="submit"
                            :disabled="passwordForm.processing"
                            class="inline-flex items-center gap-2 rounded-xl bg-[#087F8C] px-5 py-2.5 text-xs font-bold text-white shadow-xs transition hover:bg-[#066873] disabled:opacity-50"
                        >
                            {{ passwordForm.processing ? 'Updating Password...' : 'Update Password' }}
                        </button>
                    </div>
                </form>

                <!-- ACTIVE LOGIN SESSIONS CARD (SELLER-66) -->
                <div class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-xs">
                    <div class="flex flex-col justify-between gap-4 border-b border-[#E5E7EB] pb-4 sm:flex-row sm:items-center">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">
                                Active Login Sessions
                            </h2>
                            <p class="mt-1 text-xs text-slate-500">
                                Monitor and revoke active browser sessions signed into your Seller ERP account across devices.
                            </p>
                        </div>

                        <button
                            v-if="sessions.length > 1"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-xs font-bold text-red-700 transition hover:bg-red-100"
                            @click="openLogoutOtherModal"
                        >
                            Log Out Other Browser Sessions
                        </button>
                    </div>

                    <div class="mt-5 space-y-3">
                        <div
                            v-for="session in sessions"
                            :key="session.id"
                            class="flex flex-col gap-4 rounded-xl border border-slate-200 p-4 transition sm:flex-row sm:items-center sm:justify-between"
                            :class="session.is_current_device ? 'border-[#087F8C]/40 bg-[#F3FBFA]/60' : 'bg-white'"
                        >
                            <div class="flex items-start gap-3.5">
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                                    :class="
                                        session.is_current_device
                                            ? 'bg-[#E8F7F6] text-[#087F8C]'
                                            : 'bg-slate-100 text-slate-500'
                                    "
                                >
                                    <!-- Mobile SVG -->
                                    <svg
                                        v-if="session.device_type === 'mobile'"
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="h-5 w-5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"
                                        />
                                    </svg>
                                    <!-- Desktop / Tablet SVG -->
                                    <svg
                                        v-else
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="h-5 w-5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25"
                                        />
                                    </svg>
                                </div>

                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h4 class="text-sm font-bold text-slate-900">
                                            {{ session.browser }} on {{ session.platform }}
                                        </h4>
                                        <span
                                            v-if="session.is_current_device"
                                            class="inline-flex items-center gap-1 rounded-full bg-[#EAF8F2] px-2.5 py-0.5 text-[10px] font-bold text-[#18794E]"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full bg-[#22A06B]"></span>
                                            This Device (Active Now)
                                        </span>
                                    </div>

                                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                                        <span>IP Address: <strong class="text-slate-700">{{ session.ip_address }}</strong></span>
                                        <span>•</span>
                                        <span v-if="session.is_current_device" class="font-semibold text-[#18794E]">
                                            Active now
                                        </span>
                                        <span v-else :title="session.last_active_date">
                                            Last active {{ session.last_active }} ({{ session.last_active_date }})
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center">
                                <button
                                    v-if="!session.is_current_device"
                                    type="button"
                                    :disabled="revokingSessionId === session.id"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-bold text-red-700 transition hover:bg-red-100 disabled:opacity-50"
                                    @click="revokeSingleSession(session.id)"
                                >
                                    {{ revokingSessionId === session.id ? 'Revoking...' : 'Revoke Session' }}
                                </button>
                                <span v-else class="text-xs font-bold text-[#087F8C]">
                                    Current Session
                                </span>
                            </div>
                        </div>

                        <div
                            v-if="sessions.length === 0"
                            class="rounded-xl border border-slate-100 bg-slate-50 p-6 text-center text-xs text-slate-500"
                        >
                            No active login session records found for this account.
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- TAB 4: DANGER ZONE / ACCOUNT CLOSURE -->
            <!-- ========================================================= -->
            <div
                v-show="activeTab === 'danger'"
                class="rounded-2xl border border-red-200 bg-white p-6 shadow-xs"
            >
                <h2 class="text-base font-bold text-red-700">
                    Close &amp; Delete Seller Account
                </h2>
                <p class="mt-1 max-w-2xl text-xs leading-5 text-slate-600">
                    Once your seller account is deleted, all associated store configurations and access credentials will be permanently removed. Please ensure all pending orders and payouts are settled before closing your account.
                </p>

                <div class="mt-5">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-xs font-bold text-white shadow-xs transition hover:bg-red-700"
                        @click="openDeleteModal"
                    >
                        Delete Seller Account
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- MODAL: REVOKE OTHER LOGIN SESSIONS -->
        <!-- ========================================================= -->
        <div
            v-if="confirmingLogoutOther"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-xs"
        >
            <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl">
                <h3 class="text-base font-bold text-slate-900">
                    Log Out Other Browser Sessions
                </h3>
                <p class="mt-1.5 text-xs leading-5 text-slate-500">
                    Please enter your seller account password to confirm that you would like to revoke all other active browser sessions across your devices.
                </p>

                <div class="mt-4">
                    <input
                        ref="otherSessionsPasswordInput"
                        v-model="revokeOthersForm.password"
                        type="password"
                        placeholder="Enter your password"
                        class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-[#087F8C] focus:ring-[#087F8C]"
                        @keyup.enter="submitRevokeOtherSessions"
                    />
                    <InputError :message="revokeOthersForm.errors.password" class="mt-1.5" />
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50"
                        @click="closeLogoutOtherModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        :disabled="revokeOthersForm.processing"
                        class="rounded-xl bg-[#087F8C] px-4 py-2 text-xs font-bold text-white hover:bg-[#066873] disabled:opacity-50"
                        @click="submitRevokeOtherSessions"
                    >
                        {{ revokeOthersForm.processing ? 'Logging Out...' : 'Log Out Other Sessions' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- MODAL: DELETE ACCOUNT -->
        <!-- ========================================================= -->
        <div
            v-if="confirmingAccountDeletion"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-xs"
        >
            <div class="w-full max-w-md rounded-2xl border border-red-200 bg-white p-6 shadow-xl">
                <h3 class="text-base font-bold text-red-700">
                    Confirm Seller Account Deletion
                </h3>
                <p class="mt-1.5 text-xs leading-5 text-slate-500">
                    Please enter your current password to permanently delete your seller account.
                </p>

                <div class="mt-4">
                    <input
                        ref="deletePasswordInput"
                        v-model="deleteForm.password"
                        type="password"
                        placeholder="Enter your password"
                        class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 focus:border-red-600 focus:ring-red-600"
                        @keyup.enter="submitDeleteAccount"
                    />
                    <InputError :message="deleteForm.errors.password" class="mt-1.5" />
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50"
                        @click="closeDeleteModal"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        :disabled="deleteForm.processing"
                        class="rounded-xl bg-red-600 px-4 py-2 text-xs font-bold text-white hover:bg-red-700 disabled:opacity-50"
                        @click="submitDeleteAccount"
                    >
                        {{ deleteForm.processing ? 'Deleting...' : 'Permanently Delete Account' }}
                    </button>
                </div>
            </div>
        </div>
    </SellerLayout>
</template>

