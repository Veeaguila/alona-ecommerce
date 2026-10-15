<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import SellerLayout from '@/Layouts/SellerLayout.vue'

const props = defineProps({
    store: {
        type: Object,
        default: () => ({}),
    },
})

const page = usePage()
const activeTab = ref('branding')

const defaultHours = {
    monday: { is_open: true, open: '09:00', close: '18:00' },
    tuesday: { is_open: true, open: '09:00', close: '18:00' },
    wednesday: { is_open: true, open: '09:00', close: '18:00' },
    thursday: { is_open: true, open: '09:00', close: '18:00' },
    friday: { is_open: true, open: '09:00', close: '18:00' },
    saturday: { is_open: true, open: '09:00', close: '17:00' },
    sunday: { is_open: false, open: '09:00', close: '17:00' },
}

const form = useForm({
    store_name: props.store.store_name || '',
    store_tagline: props.store.store_tagline || '',
    store_description: props.store.store_description || '',
    store_logo: null,
    store_banner: null,
    remove_logo: false,
    remove_banner: false,
    operating_hours: props.store.operating_hours
        ? { ...defaultHours, ...props.store.operating_hours }
        : { ...defaultHours },
    response_time: props.store.response_time || 'within_day',
    shipping_policy: props.store.shipping_policy || '',
    warranty_policy: props.store.warranty_policy || '',
    return_policy: props.store.return_policy || '',
    vacation_mode: Boolean(props.store.vacation_mode),
    vacation_message: props.store.vacation_message || '',
})

const logoPreview = ref(null)
const bannerPreview = ref(null)

const currentLogo = computed(() => {
    if (form.remove_logo) return null
    const path = props.store.store_logo_path ?? null
    if (!path) return null
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/storage/')) {
        return path
    }
    return `/storage/${path.replace(/^\/+/, '')}`
})

const currentBanner = computed(() => {
    if (form.remove_banner) return null
    const path = props.store.store_banner_path ?? null
    if (!path) return null
    if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/storage/')) {
        return path
    }
    return `/storage/${path.replace(/^\/+/, '')}`
})

const storeInitial = computed(() => {
    return props.store.store_name?.charAt(0)?.toUpperCase() || 'S'
})

const handleLogoChange = (event) => {
    const file = event.target.files?.[0] ?? null
    form.store_logo = file
    form.remove_logo = false

    if (logoPreview.value) {
        URL.revokeObjectURL(logoPreview.value)
        logoPreview.value = null
    }

    if (file) {
        logoPreview.value = URL.createObjectURL(file)
    }
}

const handleBannerChange = (event) => {
    const file = event.target.files?.[0] ?? null
    form.store_banner = file
    form.remove_banner = false

    if (bannerPreview.value) {
        URL.revokeObjectURL(bannerPreview.value)
        bannerPreview.value = null
    }

    if (file) {
        bannerPreview.value = URL.createObjectURL(file)
    }
}

const clearSelectedLogo = () => {
    form.store_logo = null
    form.remove_logo = true
    if (logoPreview.value) {
        URL.revokeObjectURL(logoPreview.value)
        logoPreview.value = null
    }
    const input = document.getElementById('store_logo')
    if (input) input.value = ''
}

const clearSelectedBanner = () => {
    form.store_banner = null
    form.remove_banner = true
    if (bannerPreview.value) {
        URL.revokeObjectURL(bannerPreview.value)
        bannerPreview.value = null
    }
    const input = document.getElementById('store_banner')
    if (input) input.value = ''
}

const daysList = [
    { key: 'monday', label: 'Monday' },
    { key: 'tuesday', label: 'Tuesday' },
    { key: 'wednesday', label: 'Wednesday' },
    { key: 'thursday', label: 'Thursday' },
    { key: 'friday', label: 'Friday' },
    { key: 'saturday', label: 'Saturday' },
    { key: 'sunday', label: 'Sunday' },
]

const applyMonToWeekdays = () => {
    const monday = form.operating_hours.monday
    const weekdays = ['tuesday', 'wednesday', 'thursday', 'friday']
    weekdays.forEach((day) => {
        form.operating_hours[day] = {
            is_open: monday.is_open,
            open: monday.open,
            close: monday.close,
        }
    })
}

const responseTimeOptions = [
    { value: 'within_hour', label: 'Within 1 hour', sub: 'Fastest responsiveness badge' },
    { value: 'few_hours', label: 'Within a few hours', sub: 'Typical business response' },
    { value: 'same_day', label: 'Within the day', sub: 'Standard daily replies' },
    { value: 'next_day', label: 'Within 24 hours', sub: 'Next business day' },
]

const loadSampleShippingPolicy = () => {
    form.shipping_policy = `1. Order Dispatch: Orders placed before 2:00 PM (Monday-Friday) are processed and dispatched on the same day.\n2. Carrier Partners: We ship nationwide via standard platform couriers (e.g. J&T Express, Flash Express).\n3. Packaging: All delicate electronics and goods are bubble-wrapped with tamper-evident seal tapes to ensure zero in-transit damage.\n4. Delivery Timeframes: Metro Manila: 2-3 business days. Provincial Luzon: 3-5 business days. Visayas & Mindanao: 5-8 business days.`
}

const loadSampleWarrantyPolicy = () => {
    form.warranty_policy = `1. Warranty Coverage: All brand-new items come with a standard 1-Year Official Manufacturer/Store Warranty from delivery date.\n2. Replacement Period: 7-day outright replacement for factory defects confirmed upon unboxing.\n3. Exclusions: Physical abuse, unauthorized tampering, liquid spillage, water ingress, or improper electrical surge damages are not covered.\n4. Claim Process: Contact our store chat with your Order ID, proof of purchase, and a short video demonstration of the reported issue.`
}

const loadSampleReturnPolicy = () => {
    form.return_policy = `1. Return Window: Eligible items can be returned within 7 calendar days from confirmed delivery.\n2. Condition Requirements: Items must be in original condition with complete accessories, intact warranty stickers, and original manufacturer box.\n3. Change of Mind: Change of mind returns are subject to pre-inspection and may require customer return shipping if item seal is intact.\n4. Refund Disbursement: Refunds are released immediately upon warehouse inspection and verification of returned item.`
}

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            _method: 'PATCH',
        }))
        .post(route('seller.store.update'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                if (logoPreview.value) {
                    URL.revokeObjectURL(logoPreview.value)
                    logoPreview.value = null
                }
                if (bannerPreview.value) {
                    URL.revokeObjectURL(bannerPreview.value)
                    bannerPreview.value = null
                }
                form.store_logo = null
                form.store_banner = null
                form.remove_logo = false
                form.remove_banner = false
            },
        })
}

const flashStatus = computed(() => {
    return page.props.flash?.status ?? null
})

onBeforeUnmount(() => {
    if (logoPreview.value) URL.revokeObjectURL(logoPreview.value)
    if (bannerPreview.value) URL.revokeObjectURL(bannerPreview.value)
})
</script>

<template>
    <Head title="Store Management" />

    <SellerLayout>
        <div class="min-h-[calc(100vh-68px)] bg-[#F8FAF9] pb-24 font-sans text-slate-800">
            <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8 space-y-6">

                <!-- Page Header -->
                <div class="flex flex-col gap-4 border-b border-[#E5E7EB] pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold uppercase tracking-[0.16em] text-[#087F8C]">Store Center</span>
                            <span
                                v-if="form.vacation_mode"
                                class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-bold text-amber-800 border border-amber-200"
                            >
                                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10" />
                                    <line x1="12" y1="8" x2="12" y2="12" />
                                    <line x1="12" y1="16" x2="12.01" y2="16" />
                                </svg>
                                Vacation Mode Active
                            </span>
                        </div>

                        <h1 class="mt-1 text-2xl font-black tracking-tight text-[#1F2937] sm:text-3xl">
                            Store Management & Branding
                        </h1>

                        <p class="mt-1 text-sm text-[#64748B]">
                            Customize your brand assets, operating schedule, terms & policies, and vacation settings.
                        </p>
                    </div>

                    <div class="flex items-center gap-2.5">
                        <a
                            v-if="store.store_name"
                            :href="route('buyer.store.show', page.props.auth.user.id)"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-[#087F8C] bg-white px-3.5 py-2 text-xs font-bold text-[#087F8C] shadow-xs transition hover:bg-[#E8F7F6]"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
                                <polyline points="15 3 21 3 21 9" />
                                <line x1="10" y1="14" x2="21" y2="3" />
                            </svg>
                            View Storefront
                        </a>

                        <Link
                            :href="route('seller.dashboard')"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-[#E5E7EB] bg-white px-3.5 py-2 text-xs font-semibold text-[#475569] shadow-xs transition hover:border-[#087F8C] hover:text-[#087F8C]"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            Dashboard
                        </Link>
                    </div>
                </div>

                <!-- Success Message -->
                <div
                    v-if="flashStatus"
                    class="flex items-start gap-3 rounded-xl border border-[#BBE7D3] bg-[#ECFDF5] px-4 py-3"
                >
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white text-[#22A06B] shadow-xs">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-[#16845A]">Settings Updated Successfully</p>
                        <p class="mt-0.5 text-xs text-[#16845A]">{{ flashStatus }}</p>
                    </div>
                </div>

                <!-- Vacation Active Alert -->
                <div
                    v-if="form.vacation_mode"
                    class="flex items-start gap-3.5 rounded-2xl border border-amber-200 bg-amber-50 p-4"
                >
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="6" y="4" width="4" height="16" />
                            <rect x="14" y="4" width="4" height="16" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-amber-900">Vacation Mode is Currently ON</h4>
                        <p class="mt-0.5 text-xs text-amber-700 leading-relaxed">
                            New customer orders on your products are paused at checkout. Store visitors will see your custom vacation message banner. You can turn this off anytime under the <strong>Vacation Mode</strong> tab.
                        </p>
                    </div>
                </div>

                <!-- Navigation Tabs -->
                <div class="flex flex-wrap gap-2 border-b border-[#E5E7EB] pb-2">
                    <button
                        type="button"
                        @click="activeTab = 'branding'"
                        :class="[
                            'inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold transition cursor-pointer',
                            activeTab === 'branding'
                                ? 'bg-[#087F8C] text-white shadow-xs'
                                : 'bg-white text-slate-600 border border-[#E5E7EB] hover:bg-slate-50'
                        ]"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                            <circle cx="8.5" cy="8.5" r="1.5" />
                            <polyline points="21 15 16 10 5 21" />
                        </svg>
                        <span>Branding & Banner</span>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'schedule'"
                        :class="[
                            'inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold transition cursor-pointer',
                            activeTab === 'schedule'
                                ? 'bg-[#087F8C] text-white shadow-xs'
                                : 'bg-white text-slate-600 border border-[#E5E7EB] hover:bg-slate-50'
                        ]"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10" />
                            <polyline points="12 6 12 12 16 14" />
                        </svg>
                        <span>Operating Hours</span>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'policies'"
                        :class="[
                            'inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold transition cursor-pointer',
                            activeTab === 'policies'
                                ? 'bg-[#087F8C] text-white shadow-xs'
                                : 'bg-white text-slate-600 border border-[#E5E7EB] hover:bg-slate-50'
                        ]"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                            <line x1="16" y1="13" x2="8" y2="13" />
                            <line x1="16" y1="17" x2="8" y2="17" />
                        </svg>
                        <span>Store Policies</span>
                    </button>

                    <button
                        type="button"
                        @click="activeTab = 'vacation'"
                        :class="[
                            'inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold transition cursor-pointer',
                            activeTab === 'vacation'
                                ? 'bg-amber-600 text-white shadow-xs'
                                : 'bg-white text-slate-600 border border-[#E5E7EB] hover:bg-slate-50'
                        ]"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 8h1a4 4 0 0 1 0 8h-1" />
                            <path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z" />
                            <line x1="6" y1="1" x2="6" y2="4" />
                            <line x1="10" y1="1" x2="10" y2="4" />
                            <line x1="14" y1="1" x2="14" y2="4" />
                        </svg>
                        <span>Vacation Mode</span>
                        <span
                            v-if="form.vacation_mode"
                            class="rounded-full bg-white text-amber-700 px-1.5 py-0.2 text-[10px] font-black"
                        >
                            ON
                        </span>
                    </button>
                </div>

                <form @submit.prevent="submit" class="space-y-6">

                    <!-- ============================================================== -->
                    <!-- TAB 1: BRANDING & BANNER (SELLER-62) -->
                    <!-- ============================================================== -->
                    <div v-show="activeTab === 'branding'" class="space-y-6">

                        <!-- Store Cover Banner Upload -->
                        <section class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-xs">
                            <div class="border-b border-[#E5E7EB] px-5 py-4">
                                <h3 class="text-sm font-bold text-[#1F2937]">Store Cover Banner</h3>
                                <p class="text-xs text-[#64748B] mt-0.5">
                                    Display a customized branded cover image at the top of your buyer storefront (Recommended: 1200 x 300 px, max 4MB).
                                </p>
                            </div>

                            <div class="p-5 space-y-4">
                                <!-- Banner Preview Box -->
                                <div class="relative w-full h-36 sm:h-44 rounded-xl overflow-hidden border border-slate-200 bg-gradient-to-r from-[#087F8C] via-[#0A96A6] to-[#065A63] flex items-center justify-center">
                                    <!-- Uploaded Banner Preview -->
                                    <img
                                        v-if="bannerPreview || currentBanner"
                                        :src="bannerPreview || currentBanner"
                                        alt="Store Cover Banner"
                                        class="w-full h-full object-cover"
                                    />
                                    <!-- Fallback Brand Watermark -->
                                    <div v-else class="text-center text-white/80 px-4">
                                        <svg class="h-8 w-8 mx-auto mb-1 stroke-current opacity-60" viewBox="0 0 24 24" fill="none" stroke-width="1.8">
                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                                            <circle cx="8.5" cy="8.5" r="1.5" />
                                            <polyline points="21 15 16 10 5 21" />
                                        </svg>
                                        <p class="text-xs font-bold text-white">Default Gradient Banner Active</p>
                                        <p class="text-[11px] text-white/70">Upload a banner image below to personalize your storefront.</p>
                                    </div>

                                    <!-- Remove Button -->
                                    <button
                                        v-if="bannerPreview || currentBanner"
                                        type="button"
                                        @click="clearSelectedBanner"
                                        class="absolute top-2.5 right-2.5 rounded-lg bg-black/60 backdrop-blur-xs text-white p-1.5 text-xs font-semibold hover:bg-black/80 transition"
                                        title="Remove banner"
                                    >
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="18" y1="6" x2="6" y2="18" />
                                            <line x1="6" y1="6" x2="18" y2="18" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="flex items-center gap-3">
                                    <label
                                        for="store_banner"
                                        class="inline-flex items-center gap-1.5 rounded-xl border border-[#087F8C] bg-[#E8F7F6] px-3.5 py-2 text-xs font-bold text-[#087F8C] transition hover:bg-[#d8f1ef] cursor-pointer"
                                    >
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                            <polyline points="17 8 12 3 7 8" />
                                            <line x1="12" y1="3" x2="12" y2="15" />
                                        </svg>
                                        <span>Choose Banner Image</span>
                                    </label>
                                    <input
                                        id="store_banner"
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        class="hidden"
                                        @change="handleBannerChange"
                                    />
                                    <span v-if="form.store_banner" class="text-xs font-semibold text-emerald-600">
                                        {{ form.store_banner.name }} selected
                                    </span>
                                </div>
                                <p v-if="form.errors.store_banner" class="text-xs text-rose-600 font-semibold">
                                    {{ form.errors.store_banner }}
                                </p>
                            </div>
                        </section>

                        <!-- Store Logo & Tagline -->
                        <section class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-xs">
                            <div class="border-b border-[#E5E7EB] px-5 py-4">
                                <h3 class="text-sm font-bold text-[#1F2937]">Store Identity & Brand Assets</h3>
                                <p class="text-xs text-[#64748B] mt-0.5">
                                    Establish your brand logo, name, tagline, and introductory description.
                                </p>
                            </div>

                            <div class="p-5 space-y-5">
                                <!-- Logo Uploader -->
                                <div>
                                    <label class="block text-xs font-semibold text-[#1F2937] mb-2">Store Logo / Avatar</label>
                                    <div class="flex items-center gap-4">
                                        <div class="h-16 w-16 sm:h-20 sm:w-20 rounded-2xl border-2 border-[#E5E7EB] bg-[#F8FAF9] overflow-hidden flex items-center justify-center shrink-0">
                                            <img
                                                v-if="logoPreview || currentLogo"
                                                :src="logoPreview || currentLogo"
                                                alt="Store Logo"
                                                class="w-full h-full object-cover"
                                            />
                                            <span v-else class="text-xl font-black text-[#087F8C]">
                                                {{ storeInitial }}
                                            </span>
                                        </div>

                                        <div class="space-y-1.5">
                                            <div class="flex items-center gap-2">
                                                <label
                                                    for="store_logo"
                                                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:border-[#087F8C] hover:text-[#087F8C] transition cursor-pointer shadow-2xs"
                                                >
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                                        <polyline points="17 8 12 3 7 8" />
                                                        <line x1="12" y1="3" x2="12" y2="15" />
                                                    </svg>
                                                    <span>Upload Logo</span>
                                                </label>
                                                <input
                                                    id="store_logo"
                                                    type="file"
                                                    accept="image/png,image/jpeg,image/webp"
                                                    class="hidden"
                                                    @change="handleLogoChange"
                                                />
                                                <button
                                                    v-if="logoPreview || currentLogo"
                                                    type="button"
                                                    @click="clearSelectedLogo"
                                                    class="text-xs font-semibold text-rose-600 hover:underline"
                                                >
                                                    Remove
                                                </button>
                                            </div>
                                            <p class="text-[11px] text-[#64748B]">Square aspect ratio recommended (PNG, JPG, max 2MB).</p>
                                        </div>
                                    </div>
                                    <p v-if="form.errors.store_logo" class="mt-1 text-xs text-rose-600 font-semibold">{{ form.errors.store_logo }}</p>
                                </div>

                                <!-- Store Name -->
                                <div>
                                    <label for="store_name" class="block text-xs font-semibold text-[#1F2937]">Store Name *</label>
                                    <input
                                        id="store_name"
                                        v-model="form.store_name"
                                        type="text"
                                        required
                                        maxlength="150"
                                        placeholder="e.g. Apex Tech Official Store"
                                        class="mt-1.5 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-2.5 text-sm text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                    />
                                    <p v-if="form.errors.store_name" class="mt-1 text-xs text-rose-600 font-semibold">{{ form.errors.store_name }}</p>
                                </div>

                                <!-- Store Tagline (SELLER-62) -->
                                <div>
                                    <label for="store_tagline" class="block text-xs font-semibold text-[#1F2937]">Store Tagline / Slogan</label>
                                    <input
                                        id="store_tagline"
                                        v-model="form.store_tagline"
                                        type="text"
                                        maxlength="255"
                                        placeholder="e.g. Premium Gaming Gear & Peripherals in the Philippines"
                                        class="mt-1.5 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-2.5 text-sm text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                    />
                                    <p v-if="form.errors.store_tagline" class="mt-1 text-xs text-rose-600 font-semibold">{{ form.errors.store_tagline }}</p>
                                </div>

                                <!-- Store Description -->
                                <div>
                                    <label for="store_description" class="block text-xs font-semibold text-[#1F2937]">Store Description</label>
                                    <textarea
                                        id="store_description"
                                        v-model="form.store_description"
                                        rows="4"
                                        maxlength="2000"
                                        placeholder="Provide buyers with a detailed overview of your store, specialty products, and authorized dealership credentials..."
                                        class="mt-1.5 block w-full resize-none rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-2.5 text-sm text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                    ></textarea>
                                    <div class="mt-1 flex items-center justify-between text-[11px] text-[#94A3B8]">
                                        <span>Visible on your public storefront and merchant profile.</span>
                                        <span>{{ form.store_description.length }}/2000</span>
                                    </div>
                                    <p v-if="form.errors.store_description" class="mt-1 text-xs text-rose-600 font-semibold">{{ form.errors.store_description }}</p>
                                </div>
                            </div>
                        </section>
                    </div>

                    <!-- ============================================================== -->
                    <!-- TAB 2: OPERATING HOURS & RESPONSE TIME (SELLER-63) -->
                    <!-- ============================================================== -->
                    <div v-show="activeTab === 'schedule'" class="space-y-6">

                        <!-- Weekly Operating Hours -->
                        <section class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-xs">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-[#E5E7EB] px-5 py-4 gap-3">
                                <div>
                                    <h3 class="text-sm font-bold text-[#1F2937]">Weekly Operating Schedule</h3>
                                    <p class="text-xs text-[#64748B] mt-0.5">
                                        Define when your store is open for fulfillment and customer inquiries.
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    @click="applyMonToWeekdays"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-[#087F8C] bg-[#E8F7F6] px-3 py-1.5 text-xs font-bold text-[#087F8C] hover:bg-[#d5f0ee] transition"
                                >
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="20 6 9 17 4 12" />
                                    </svg>
                                    <span>Copy Monday to Weekdays</span>
                                </button>
                            </div>

                            <div class="divide-y divide-slate-100 p-2 sm:p-5">
                                <div
                                    v-for="day in daysList"
                                    :key="day.key"
                                    class="flex flex-col sm:flex-row sm:items-center justify-between py-3 px-2 sm:px-3 rounded-xl hover:bg-slate-50/70 transition gap-3"
                                >
                                    <div class="flex items-center gap-3 w-40">
                                        <input
                                            :id="`open_${day.key}`"
                                            type="checkbox"
                                            v-model="form.operating_hours[day.key].is_open"
                                            class="h-4 w-4 rounded border-slate-300 text-[#087F8C] focus:ring-[#087F8C]"
                                        />
                                        <label :for="`open_${day.key}`" class="text-xs font-bold text-slate-800 cursor-pointer">
                                            {{ day.label }}
                                        </label>
                                    </div>

                                    <div v-if="form.operating_hours[day.key].is_open" class="flex items-center gap-2">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-[11px] text-slate-500 font-medium">Opens:</span>
                                            <input
                                                type="time"
                                                v-model="form.operating_hours[day.key].open"
                                                class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-800 focus:border-[#087F8C] focus:ring-1 focus:ring-[#087F8C]"
                                            />
                                        </div>

                                        <span class="text-slate-400 text-xs">—</span>

                                        <div class="flex items-center gap-1.5">
                                            <span class="text-[11px] text-slate-500 font-medium">Closes:</span>
                                            <input
                                                type="time"
                                                v-model="form.operating_hours[day.key].close"
                                                class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-800 focus:border-[#087F8C] focus:ring-1 focus:ring-[#087F8C]"
                                            />
                                        </div>
                                    </div>

                                    <div v-else class="text-xs font-semibold text-slate-400 italic">
                                        Closed for orders / fulfillment
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Response Time Commitment -->
                        <section class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-xs">
                            <div class="border-b border-[#E5E7EB] px-5 py-4">
                                <h3 class="text-sm font-bold text-[#1F2937]">Customer Chat Response Time</h3>
                                <p class="text-xs text-[#64748B] mt-0.5">
                                    Set expectations for how quickly your team responds to customer chat messages.
                                </p>
                            </div>

                            <div class="p-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <div
                                    v-for="opt in responseTimeOptions"
                                    :key="opt.value"
                                    @click="form.response_time = opt.value"
                                    :class="[
                                        'rounded-xl border p-3.5 transition cursor-pointer flex flex-col justify-between',
                                        form.response_time === opt.value
                                            ? 'border-[#087F8C] bg-[#E8F7F6]/60 shadow-xs ring-1 ring-[#087F8C]'
                                            : 'border-slate-200 bg-white hover:border-slate-300'
                                    ]"
                                >
                                    <div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold text-slate-900">{{ opt.label }}</span>
                                            <span
                                                :class="[
                                                    'h-3.5 w-3.5 rounded-full border flex items-center justify-center',
                                                    form.response_time === opt.value
                                                        ? 'border-[#087F8C] bg-[#087F8C]'
                                                        : 'border-slate-300'
                                                ]"
                                            >
                                                <span v-if="form.response_time === opt.value" class="h-1.5 w-1.5 rounded-full bg-white"></span>
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-slate-500 mt-1">{{ opt.sub }}</p>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <!-- ============================================================== -->
                    <!-- TAB 3: STORE POLICIES & TERMS (SELLER-64) -->
                    <!-- ============================================================== -->
                    <div v-show="activeTab === 'policies'" class="space-y-6">

                        <!-- Shipping Policy -->
                        <section class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-xs">
                            <div class="flex items-center justify-between border-b border-[#E5E7EB] px-5 py-4">
                                <div>
                                    <h3 class="text-sm font-bold text-[#1F2937]">Shipping & Delivery Policy</h3>
                                    <p class="text-xs text-[#64748B] mt-0.5">
                                        Clarify cutoff times, packaging protocols, and delivery lead times.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    @click="loadSampleShippingPolicy"
                                    class="text-xs font-bold text-[#087F8C] hover:underline"
                                >
                                    Insert Standard Template
                                </button>
                            </div>
                            <div class="p-5">
                                <textarea
                                    v-model="form.shipping_policy"
                                    rows="5"
                                    maxlength="5000"
                                    placeholder="Explain your shipping schedule, cutoff times, packaging guidelines, and courier partners..."
                                    class="w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-xs leading-relaxed text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                ></textarea>
                                <p v-if="form.errors.shipping_policy" class="mt-1 text-xs text-rose-600 font-semibold">{{ form.errors.shipping_policy }}</p>
                            </div>
                        </section>

                        <!-- Warranty Policy -->
                        <section class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-xs">
                            <div class="flex items-center justify-between border-b border-[#E5E7EB] px-5 py-4">
                                <div>
                                    <h3 class="text-sm font-bold text-[#1F2937]">Warranty & Guarantee Policy</h3>
                                    <p class="text-xs text-[#64748B] mt-0.5">
                                        Specify coverage conditions, duration, replacement windows, and exclusions.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    @click="loadSampleWarrantyPolicy"
                                    class="text-xs font-bold text-[#087F8C] hover:underline"
                                >
                                    Insert Standard Template
                                </button>
                            </div>
                            <div class="p-5">
                                <textarea
                                    v-model="form.warranty_policy"
                                    rows="5"
                                    maxlength="5000"
                                    placeholder="Specify warranty duration, factory defect replacement conditions, service centers, and exclusions..."
                                    class="w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-xs leading-relaxed text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                ></textarea>
                                <p v-if="form.errors.warranty_policy" class="mt-1 text-xs text-rose-600 font-semibold">{{ form.errors.warranty_policy }}</p>
                            </div>
                        </section>

                        <!-- Return & Refund Policy -->
                        <section class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-xs">
                            <div class="flex items-center justify-between border-b border-[#E5E7EB] px-5 py-4">
                                <div>
                                    <h3 class="text-sm font-bold text-[#1F2937]">Return & Refund Policy</h3>
                                    <p class="text-xs text-[#64748B] mt-0.5">
                                        Outline eligible return reasons, package inspection requirements, and refund timelines.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    @click="loadSampleReturnPolicy"
                                    class="text-xs font-bold text-[#087F8C] hover:underline"
                                >
                                    Insert Standard Template
                                </button>
                            </div>
                            <div class="p-5">
                                <textarea
                                    v-model="form.return_policy"
                                    rows="5"
                                    maxlength="5000"
                                    placeholder="Explain your return acceptance criteria, required unboxing videos/photos, item conditions, and refund release..."
                                    class="w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-xs leading-relaxed text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                ></textarea>
                                <p v-if="form.errors.return_policy" class="mt-1 text-xs text-rose-600 font-semibold">{{ form.errors.return_policy }}</p>
                            </div>
                        </section>
                    </div>

                    <!-- ============================================================== -->
                    <!-- TAB 4: VACATION MODE (SELLER-65) -->
                    <!-- ============================================================== -->
                    <div v-show="activeTab === 'vacation'" class="space-y-6">
                        <section class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-xs">
                            <div class="border-b border-[#E5E7EB] px-5 py-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h3 class="text-sm font-bold text-[#1F2937]">Store Vacation Mode</h3>
                                        <p class="text-xs text-[#64748B] mt-0.5">
                                            Temporarily halt incoming orders when you are taking a break or auditing inventory.
                                        </p>
                                    </div>

                                    <!-- Switch -->
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input
                                            type="checkbox"
                                            v-model="form.vacation_mode"
                                            class="sr-only peer"
                                        />
                                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                                    </label>
                                </div>
                            </div>

                            <div class="p-5 space-y-4">
                                <!-- Warning Box -->
                                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-900 leading-relaxed space-y-2">
                                    <div class="flex items-center gap-2 font-bold">
                                        <svg class="h-4 w-4 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10" />
                                            <line x1="12" y1="8" x2="12" y2="12" />
                                            <line x1="12" y1="16" x2="12.01" y2="16" />
                                        </svg>
                                        <span>How Vacation Mode Affects Your Store:</span>
                                    </div>
                                    <ul class="list-disc pl-5 space-y-1 text-amber-800 text-[11px]">
                                        <li>Your products will remain visible in searches, but the <strong>"Add to Cart" and "Buy Now" buttons will be disabled</strong>.</li>
                                        <li>If a customer attempts to checkout an existing cart item from your store, <strong>checkout is safely blocked</strong> with your message.</li>
                                        <li>A prominent vacation alert banner is shown at the top of your store page and product pages.</li>
                                        <li>Turn off Vacation Mode at any time to immediately resume normal sales.</li>
                                    </ul>
                                </div>

                                <!-- Custom Vacation Notice Message -->
                                <div>
                                    <label for="vacation_message" class="block text-xs font-semibold text-[#1F2937]">
                                        Notice to Store Visitors & Buyers
                                    </label>
                                    <textarea
                                        id="vacation_message"
                                        v-model="form.vacation_message"
                                        rows="3"
                                        maxlength="1000"
                                        placeholder="e.g. Our warehouse is undergoing inventory auditing from Oct 16 to Oct 20. All existing orders are being delivered on schedule. We will reopen for new orders soon!"
                                        class="mt-1.5 block w-full resize-none rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-2.5 text-xs text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                    ></textarea>
                                    <p v-if="form.errors.vacation_message" class="mt-1 text-xs text-rose-600 font-semibold">{{ form.errors.vacation_message }}</p>
                                </div>

                                <!-- Live Banner Preview -->
                                <div>
                                    <p class="text-xs font-semibold text-slate-700 mb-2">Live Preview of Customer Banner:</p>
                                    <div class="rounded-xl border border-amber-300 bg-amber-100/70 p-3.5 flex items-start gap-3">
                                        <svg class="h-5 w-5 shrink-0 text-amber-700 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10" />
                                            <line x1="12" y1="8" x2="12" y2="12" />
                                            <line x1="12" y1="16" x2="12.01" y2="16" />
                                        </svg>
                                        <div class="text-xs">
                                            <p class="font-bold text-amber-900">Store on Vacation Mode</p>
                                            <p class="text-amber-800 mt-0.5">
                                                {{ form.vacation_message || 'This store has temporarily paused incoming orders. Please check back soon!' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <!-- Bottom Action Save Bar -->
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-xs">
                        <div>
                            <p class="text-xs font-bold text-[#1F2937]">Save Store Configuration</p>
                            <p class="text-[11px] text-[#94A3B8]">Changes apply immediately across buyer storefront and checkout.</p>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#087F8C] px-6 py-2.5 text-xs font-bold text-white shadow-xs transition hover:bg-[#066D77] disabled:opacity-60 cursor-pointer"
                        >
                            <svg v-if="form.processing" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" class="opacity-30" />
                                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            </svg>
                            <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                                <polyline points="17 21 17 13 7 13 7 21" />
                                <polyline points="7 3 7 8 15 8" />
                            </svg>
                            <span>{{ form.processing ? 'Saving Changes...' : 'Save Store Changes' }}</span>
                        </button>
                    </div>

                    <!-- Error Alert -->
                    <div v-if="form.errors && Object.keys(form.errors).length" class="rounded-xl border border-rose-200 bg-rose-50 p-4">
                        <p class="text-xs font-bold text-rose-800">Please review highlighted form errors before saving.</p>
                    </div>
                </form>

            </div>
        </div>
    </SellerLayout>
</template>