<script setup>
import { computed, ref, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    title: {
        type: String,
        default: 'Settings',
    },

    eyebrow: {
        type: String,
        default: 'Settings',
    },

    description: {
        type: String,
        default: '',
    },

    active: {
        type: String,
        default: 'settings',
    },

    announcements: {
        type: Array,
        default: () => [],
    },

    policies: {
        type: Array,
        default: () => [],
    },

    settings: {
        type: Array,
        default: () => [],
    },

    faqs: {
        type: Array,
        default: () => [],
    },

    whyAlona: {
        type: Object,
        default: null,
    },

    heroBanner: {
        type: Object,
        default: null,
    },

    heroBanners: {
        type: Array,
        default: () => [],
    },

    footer: {
        type: Object,
        default: null,
    },
})

/*
|--------------------------------------------------------------------------
| UI State
|--------------------------------------------------------------------------
*/

const showAnnouncementForm = ref(false)
const showPolicyForm = ref(false)
const showPlatformSettingForm = ref(false)

const editingPolicyId = ref(null)

/*
|--------------------------------------------------------------------------
| Announcement Form
|--------------------------------------------------------------------------
*/

const announcementForm = useForm({
    title: '',
    body: '',
    status: 'draft',
})

const submitAnnouncement = () => {
    announcementForm.post(
        route('admin.announcements.store'),
        {
            preserveScroll: true,

            onSuccess: () => {
                announcementForm.reset()

                announcementForm.status = 'draft'

                showAnnouncementForm.value = false
            },
        }
    )
}

const cancelAnnouncement = () => {
    announcementForm.reset()

    announcementForm.clearErrors()

    announcementForm.status = 'draft'

    showAnnouncementForm.value = false
}

/*
|--------------------------------------------------------------------------
| New Policy Form
|--------------------------------------------------------------------------
*/

const policyForm = useForm({
    title: '',
    content: '',
    version: '1.0',
    is_published: false,
})

const submitNewPolicy = () => {
    policyForm.post(
        route('admin.policies.store'),
        {
            preserveScroll: true,

            onSuccess: () => {
                policyForm.reset()

                policyForm.version = '1.0'

                policyForm.is_published = false

                showPolicyForm.value = false
            },
        }
    )
}

const cancelNewPolicy = () => {
    policyForm.reset()

    policyForm.clearErrors()

    policyForm.version = '1.0'

    policyForm.is_published = false

    showPolicyForm.value = false
}

/*
|--------------------------------------------------------------------------
| Existing Policy Forms
|--------------------------------------------------------------------------
*/

const policyForms = ref({})

const getPolicyForm = (policy) => {
    if (!policyForms.value[policy.id]) {
        policyForms.value[policy.id] = useForm({
            title: policy.title ?? '',
            content: policy.content ?? '',
            version: policy.version ?? '',
            is_published: Boolean(policy.is_published),
        })
    }

    return policyForms.value[policy.id]
}

const startEditingPolicy = (policy) => {
    const form = getPolicyForm(policy)

    form.title = policy.title ?? ''
    form.content = policy.content ?? ''
    form.version = policy.version ?? ''
    form.is_published = Boolean(policy.is_published)

    form.clearErrors()

    editingPolicyId.value = policy.id
}

const cancelEditingPolicy = () => {
    editingPolicyId.value = null
}

const submitPolicy = (policy) => {
    const form = getPolicyForm(policy)

    form.patch(
        route('admin.policies.update', policy.id),
        {
            preserveScroll: true,

            onSuccess: () => {
                editingPolicyId.value = null
            },
        }
    )
}

/*
|--------------------------------------------------------------------------
| Platform Setting Form
|--------------------------------------------------------------------------
*/

const platformSettingForm = useForm({
    key: '',
    value: '',
    description: '',
    is_active: true,
})

const submitPlatformSetting = () => {
    platformSettingForm.post(
        route('admin.platform-settings.store'),
        {
            preserveScroll: true,

            onSuccess: () => {
                platformSettingForm.reset()

                platformSettingForm.is_active = true

                showPlatformSettingForm.value = false
            },
        }
    )
}

const cancelPlatformSetting = () => {
    platformSettingForm.reset()

    platformSettingForm.clearErrors()

    platformSettingForm.is_active = true

    showPlatformSettingForm.value = false
}

/*
|--------------------------------------------------------------------------
| Homepage Multi-Hero Banners
|--------------------------------------------------------------------------
*/

const defaultSlideTemplate = {
    id: '',
    is_active: true,
    layout_type: 'split',
    overlay_text: false,
    theme: 'teal',
    badge_text: '',
    title: 'Welcome to',
    title_highlight: 'Alona.',
    description: 'Everything you love, all in one place. Discover quality products, trusted sellers, and great deals made for you.',
    show_primary_button: true,
    primary_button_text: 'Shop Now',
    primary_button_url: '/products',
    show_primary_button_arrow: true,
    show_secondary_button: true,
    secondary_button_text: 'Explore Categories',
    secondary_button_url: '#categories',
    show_image: true,
    image_path: '/images/Alogo.png',
    show_floating_cards: true,
    card_1_title: 'Products',
    card_1_subtitle: 'available',
    card_2_title: 'CATEGORIES',
    card_2_subtitle: 'to explore',
}

const parseInitialBanners = () => {
    let list = []
    if (Array.isArray(props.heroBanners) && props.heroBanners.length > 0) {
        list = JSON.parse(JSON.stringify(props.heroBanners))
    } else if (props.heroBanner && Object.keys(props.heroBanner).length > 0) {
        list = [JSON.parse(JSON.stringify(props.heroBanner))]
    }
    if (list.length === 0) {
        list = [{ ...defaultSlideTemplate, id: 'banner_1' }]
    }
    return list.map((b, idx) => ({
        ...defaultSlideTemplate,
        ...b,
        id: b.id || `banner_${idx + 1}`,
        is_active: b.is_active !== false,
        layout_type: b.layout_type || 'split',
        overlay_text: b.overlay_text ?? false,
        show_primary_button: b.show_primary_button !== false,
        show_primary_button_arrow: b.show_primary_button_arrow !== false,
        show_secondary_button: b.show_secondary_button !== false && (b.secondary_button_text !== '' || b.show_secondary_button === true),
        show_image: b.show_image !== false,
        theme: b.theme || 'teal',
    }))
}

const bannerSlides = ref(parseInitialBanners())
const activeSlideIndex = ref(0)
const bannerFiles = ref({})
const bannerPreviews = ref({})
const isSavingBanners = ref(false)
const bannerSavedRecently = ref(false)

watch(
    () => props.heroBanners,
    () => {
        bannerSlides.value = parseInitialBanners()
    },
    { deep: true }
)

const currentEditSlide = computed(() => {
    if (activeSlideIndex.value < 0 || activeSlideIndex.value >= bannerSlides.value.length) {
        activeSlideIndex.value = 0
    }
    return bannerSlides.value[activeSlideIndex.value] || bannerSlides.value[0] || defaultSlideTemplate
})

const getThemeGradient = (theme) => {
    switch (theme) {
        case 'emerald':
            return 'bg-gradient-to-br from-[#064E3B] via-[#047857] to-[#059669]'
        case 'indigo':
            return 'bg-gradient-to-br from-[#0F172A] via-[#1E1B4B] to-[#312E81]'
        case 'navy':
            return 'bg-gradient-to-br from-[#0A1128] via-[#001F54] to-[#034078]'
        case 'purple':
            return 'bg-gradient-to-br from-[#3B0764] via-[#581C87] to-[#7E22CE]'
        case 'rose':
            return 'bg-gradient-to-br from-[#881337] via-[#BE123C] to-[#E11D48]'
        case 'amber':
            return 'bg-gradient-to-br from-[#78350F] via-[#9A3412] to-[#B45309]'
        case 'dark':
            return 'bg-gradient-to-br from-[#111827] via-[#18181B] to-[#27272A]'
        case 'teal':
        default:
            return 'bg-[#087F8C]'
    }
}

const themeOptions = [
    { key: 'teal', label: 'Teal (Alona Signature)', color: '#087F8C' },
    { key: 'emerald', label: 'Emerald Forest', color: '#047857' },
    { key: 'indigo', label: 'Midnight Indigo', color: '#312E81' },
    { key: 'navy', label: 'Deep Ocean Navy', color: '#001F54' },
    { key: 'purple', label: 'Royal Purple', color: '#581C87' },
    { key: 'rose', label: 'Crimson Rose', color: '#BE123C' },
    { key: 'amber', label: 'Sunset Amber', color: '#9A3412' },
    { key: 'dark', label: 'Obsidian Dark', color: '#18181B' },
]

const addSlide = () => {
    const newIdx = bannerSlides.value.length + 1
    const newSlide = {
        ...defaultSlideTemplate,
        id: `banner_${Date.now()}_${newIdx}`,
        is_active: true,
        title: 'Special Offer',
        title_highlight: 'Save Today.',
        badge_text: 'HOT PROMOTION',
        description: 'Explore exclusive deals, new arrivals, and special seasonal discounts.',
        primary_button_text: 'Shop Now',
        primary_button_url: '/products',
        secondary_button_text: 'View Categories',
        secondary_button_url: '#categories',
        theme: 'emerald',
    }
    bannerSlides.value.push(newSlide)
    activeSlideIndex.value = bannerSlides.value.length - 1
}

const duplicateSlide = (idx) => {
    const target = bannerSlides.value[idx]
    if (!target) return
    const cloned = JSON.parse(JSON.stringify(target))
    cloned.id = `banner_${Date.now()}`
    cloned.title = `${cloned.title} (Copy)`
    bannerSlides.value.splice(idx + 1, 0, cloned)
    activeSlideIndex.value = idx + 1
}

const removeSlide = (idx) => {
    if (bannerSlides.value.length <= 1) {
        alert('You must have at least one hero banner.')
        return
    }
    if (!confirm('Are you sure you want to delete this banner slide?')) {
        return
    }
    bannerSlides.value.splice(idx, 1)
    if (activeSlideIndex.value >= bannerSlides.value.length) {
        activeSlideIndex.value = bannerSlides.value.length - 1
    }
}

const moveSlide = (idx, direction) => {
    const newIndex = idx + direction
    if (newIndex < 0 || newIndex >= bannerSlides.value.length) return
    const item = bannerSlides.value.splice(idx, 1)[0]
    bannerSlides.value.splice(newIndex, 0, item)
    activeSlideIndex.value = newIndex
}

const handleSlideImageUpload = (e, slide) => {
    const file = e.target.files?.[0]
    if (file) {
        bannerFiles.value[slide.id] = file
        bannerPreviews.value[slide.id] = URL.createObjectURL(file)
    }
}

const getSlideDisplayImage = (slide) => {
    if (slide && bannerPreviews.value[slide.id]) {
        return bannerPreviews.value[slide.id]
    }
    return slide?.image_path || '/images/Alogo.png'
}

const saveAllHeroBanners = () => {
    isSavingBanners.value = true
    bannerSavedRecently.value = false

    const cleanBanners = bannerSlides.value.map((b, idx) => ({
        id: b.id || `banner_${idx + 1}`,
        is_active: b.is_active !== false,
        layout_type: b.layout_type || 'split',
        overlay_text: b.overlay_text ?? false,
        theme: b.theme || 'teal',
        badge_text: b.badge_text || '',
        title: b.title || '',
        title_highlight: b.title_highlight || '',
        description: b.description || '',
        show_primary_button: b.show_primary_button !== false,
        primary_button_text: b.primary_button_text || 'Shop Now',
        primary_button_url: b.primary_button_url || '/products',
        show_primary_button_arrow: b.show_primary_button_arrow !== false,
        show_secondary_button: b.show_secondary_button !== false,
        secondary_button_text: b.secondary_button_text || '',
        secondary_button_url: b.secondary_button_url || '',
        show_image: b.show_image !== false,
        image_path: b.image_path || '/images/Alogo.png',
        show_floating_cards: b.show_floating_cards !== false,
        card_1_title: b.card_1_title || 'Products',
        card_1_subtitle: b.card_1_subtitle || 'available',
        card_2_title: b.card_2_title || 'CATEGORIES',
        card_2_subtitle: b.card_2_subtitle || 'to explore',
    }))

    // Use a plain object so Inertia's objectToFormData accurately serializes all fields
    const payload = {
        banners_data: JSON.stringify(cleanBanners),
    }

    Object.keys(bannerFiles.value).forEach(id => {
        const file = bannerFiles.value[id]
        if (file) {
            payload[`banner_image_${id}`] = file
        }
    })

    router.post(route('admin.settings.hero-banner.update'), payload, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            bannerFiles.value = {}
            bannerPreviews.value = {}
            isSavingBanners.value = false
            bannerSavedRecently.value = true
            setTimeout(() => {
                bannerSavedRecently.value = false
            }, 5000)
        },
        onError: (err) => {
            console.error('Failed to save banners:', err)
            isSavingBanners.value = false
            alert('Failed to save banners. Please check your inputs.')
        },
        onFinish: () => {
            isSavingBanners.value = false
        },
    })
}

const resetAllBannersToDefault = () => {
    if (!confirm('Reset all hero banners back to single default Welcome banner?')) return
    bannerSlides.value = [{ ...defaultSlideTemplate, id: 'banner_default' }]
    activeSlideIndex.value = 0
    bannerFiles.value = {}
    bannerPreviews.value = {}
}

/*
|--------------------------------------------------------------------------
| Why Alona Banner Form
|--------------------------------------------------------------------------
*/

const defaultWhyAlona = {
    eyebrow: 'WHY ALONA',
    title: 'Shopping should feel effortless.',
    description: 'Alona brings products and sellers together through a marketplace designed to keep discovering simple.',
    cards: [
        {
            number: '01',
            title: 'Curated for you',
            description: 'Discover products selected from different categories in one simple marketplace.',
        },
        {
            number: '02',
            title: 'Trusted sellers',
            description: 'Shop from sellers who are part of the Alona marketplace community.',
        },
        {
            number: '03',
            title: 'Simple shopping',
            description: 'Find products, compare choices, and shop without unnecessary steps.',
        },
    ],
}

const whyAlonaForm = useForm({
    eyebrow: props.whyAlona?.eyebrow ?? defaultWhyAlona.eyebrow,
    title: props.whyAlona?.title ?? defaultWhyAlona.title,
    description: props.whyAlona?.description ?? defaultWhyAlona.description,
    cards: (props.whyAlona?.cards && props.whyAlona.cards.length === 3)
        ? JSON.parse(JSON.stringify(props.whyAlona.cards))
        : JSON.parse(JSON.stringify(defaultWhyAlona.cards)),
})

const submitWhyAlona = () => {
    whyAlonaForm.post(route('admin.settings.why-alona.update'), {
        preserveScroll: true,
    })
}

const resetWhyAlona = () => {
    whyAlonaForm.eyebrow = defaultWhyAlona.eyebrow
    whyAlonaForm.title = defaultWhyAlona.title
    whyAlonaForm.description = defaultWhyAlona.description
    whyAlonaForm.cards = JSON.parse(JSON.stringify(defaultWhyAlona.cards))
}

/*
|--------------------------------------------------------------------------
| Marketplace Footer Navigation Form
|--------------------------------------------------------------------------
*/

const defaultFooterSettings = {
    theme: 'light',
    brand: {
        tagline: 'Discover products, connect with sellers, and enjoy simple shopping with Alona.',
        copyright: `© ${new Date().getFullYear()} Alona. All rights reserved.`,
        subtext: 'A marketplace made simple.',
    },
    sections: [
        {
            id: 'shop',
            title: 'Shop',
            icon: 'bag',
            links: [
                { label: 'All Products', url: '/products' },
                { label: 'Categories', url: '/categories' },
                { label: 'Cart', url: '/buyer/cart' },
                { label: 'Track My Order', url: '/buyer/orders' },
            ],
        },
        {
            id: 'customer-support',
            title: 'Customer Support',
            icon: 'headphones',
            links: [
                { label: 'Help Center', url: '/help' },
                { label: 'Frequently Asked Questions', url: '/faq' },
                { label: 'Contact Us', url: '/help' },
                { label: 'Complaints & Ticket Status', url: '/buyer/complaints' },
                { label: 'Buyer Protection', url: '/policies' },
            ],
        },
        {
            id: 'partner-account',
            title: 'Partner & Account',
            icon: 'user',
            links: [
                { label: 'Become a Seller', url: '/register/seller' },
                { label: 'Become a Rider', url: '/register' },
                { label: 'Logistics Partner', url: '/register' },
                { label: 'My Account', url: '/buyer/dashboard' },
            ],
        },
        {
            id: 'policies',
            title: 'Policies & Information',
            icon: 'shield',
            links: [
                { label: 'Privacy Policy', url: '/policies#privacy' },
                { label: 'Terms & Conditions', url: '/policies#terms' },
                { label: 'Shipping & Delivery Policy', url: '/policies#shipping' },
                { label: 'Returns & Refunds Policy', url: '/policies#returns' },
                { label: 'Payment Methods', url: '/policies?policy=payment' },
            ],
        },
    ],
}

const initialFooter = props.footer && Array.isArray(props.footer.sections) && props.footer.sections.length > 0
    ? props.footer
    : defaultFooterSettings

const footerForm = useForm({
    theme: initialFooter.theme ?? 'dark',
    brand: {
        tagline: initialFooter.brand?.tagline ?? defaultFooterSettings.brand.tagline,
        copyright: initialFooter.brand?.copyright ?? defaultFooterSettings.brand.copyright,
        subtext: initialFooter.brand?.subtext ?? defaultFooterSettings.brand.subtext,
    },
    sections: JSON.parse(JSON.stringify(initialFooter.sections)),
})

const addFooterSection = () => {
    const num = footerForm.sections.length + 1
    footerForm.sections.push({
        id: `section_${Date.now()}`,
        title: `Section ${num}`,
        icon: 'bag',
        links: [
            { label: 'New Link', url: '/' }
        ]
    })
}

const removeFooterSection = (index) => {
    if (footerForm.sections.length > 1) {
        footerForm.sections.splice(index, 1)
    }
}

const addFooterLink = (sectionIndex) => {
    footerForm.sections[sectionIndex].links.push({
        label: 'New Link',
        url: '/'
    })
}

const removeFooterLink = (sectionIndex, linkIndex) => {
    footerForm.sections[sectionIndex].links.splice(linkIndex, 1)
}

const submitFooter = () => {
    footerForm.post(route('admin.settings.footer.update'), {
        preserveScroll: true,
    })
}

const resetFooter = () => {
    footerForm.theme = defaultFooterSettings.theme
    footerForm.brand = JSON.parse(JSON.stringify(defaultFooterSettings.brand))
    footerForm.sections = JSON.parse(JSON.stringify(defaultFooterSettings.sections))
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const formatDate = (date) => {
    if (!date) {
        return '—'
    }

    const parsed = new Date(date)

    if (Number.isNaN(parsed.getTime())) {
        return date
    }

    return parsed.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}

const announcementStatusClass = (status) => {
    switch (status) {
        case 'published':
            return 'bg-[#E8F7F6] text-[#087F8C] ring-1 ring-inset ring-[#B9E7E4]'

        case 'archived':
            return 'bg-slate-100 text-slate-500 ring-1 ring-inset ring-slate-200'

        default:
            return 'bg-[#FFF8E7] text-[#A66B00] ring-1 ring-inset ring-[#F4B942]/30'
    }
}

const policyStatusClass = (published) => {
    return published
        ? 'bg-[#E8F7F6] text-[#087F8C] ring-1 ring-inset ring-[#B9E7E4]'
        : 'bg-slate-100 text-slate-500 ring-1 ring-inset ring-slate-200'
}


/*
|--------------------------------------------------------------------------
| FAQ State & Actions
|--------------------------------------------------------------------------
*/

const showFaqForm = ref(false)
const editingFaqId = ref(null)
const faqForms = ref({})

const faqCategories = [
    { value: 'orders', label: 'Orders & Tracking' },
    { value: 'payments', label: 'Payments & Checkout' },
    { value: 'shipping', label: 'Shipping & Delivery' },
    { value: 'returns', label: 'Returns & Refunds' },
    { value: 'vouchers', label: 'Vouchers & Deals' },
    { value: 'safety', label: 'Safety & Protection' },
    { value: 'account', label: 'Account & Profile' },
    { value: 'general', label: 'General Inquiries' },
]

const faqForm = useForm({
    category: 'general',
    question: '',
    answer: '',
    sort_order: 0,
    is_published: true,
})

const submitNewFaq = () => {
    faqForm.post(route('admin.faqs.store'), {
        preserveScroll: true,
        onSuccess: () => {
            faqForm.reset()
            faqForm.category = 'general'
            faqForm.sort_order = 0
            faqForm.is_published = true
            showFaqForm.value = false
        },
    })
}

const cancelNewFaq = () => {
    faqForm.reset()
    faqForm.clearErrors()
    faqForm.category = 'general'
    faqForm.sort_order = 0
    faqForm.is_published = true
    showFaqForm.value = false
}

const getFaqForm = (faq) => {
    if (!faqForms.value[faq.id]) {
        faqForms.value[faq.id] = useForm({
            category: faq.category ?? 'general',
            question: faq.question ?? '',
            answer: faq.answer ?? '',
            sort_order: faq.sort_order ?? 0,
            is_published: Boolean(faq.is_published),
        })
    }
    return faqForms.value[faq.id]
}

const startEditingFaq = (faq) => {
    const form = getFaqForm(faq)
    form.category = faq.category ?? 'general'
    form.question = faq.question ?? ''
    form.answer = faq.answer ?? ''
    form.sort_order = faq.sort_order ?? 0
    form.is_published = Boolean(faq.is_published)
    form.clearErrors()
    editingFaqId.value = faq.id
}

const cancelEditingFaq = () => {
    editingFaqId.value = null
}

const submitFaq = (faq) => {
    const form = getFaqForm(faq)
    form.patch(route('admin.faqs.update', faq.id), {
        preserveScroll: true,
        onSuccess: () => {
            editingFaqId.value = null
        },
    })
}

const deleteFaq = (faq) => {
    if (confirm(`Are you sure you want to delete this FAQ: "${faq.question}"?`)) {
        router.delete(route('admin.faqs.destroy', faq.id), {
            preserveScroll: true,
        })
    }
}

/*
|--------------------------------------------------------------------------
| Platform Setting Edit & Delete Actions
|--------------------------------------------------------------------------
*/

const editingSettingId = ref(null)
const settingForms = ref({})

const getSettingForm = (setting) => {
    if (!settingForms.value[setting.id]) {
        settingForms.value[setting.id] = useForm({
            value: setting.value ?? '',
            description: setting.description ?? '',
            is_active: Boolean(setting.is_active),
        })
    }
    return settingForms.value[setting.id]
}

const startEditingSetting = (setting) => {
    const form = getSettingForm(setting)
    form.value = setting.value ?? ''
    form.description = setting.description ?? ''
    form.is_active = Boolean(setting.is_active)
    form.clearErrors()
    editingSettingId.value = setting.id
}

const cancelEditingSetting = () => {
    editingSettingId.value = null
}

const submitSetting = (setting) => {
    const form = getSettingForm(setting)
    form.patch(route('admin.platform-settings.update', setting.id), {
        preserveScroll: true,
        onSuccess: () => {
            editingSettingId.value = null
        },
    })
}

const deleteSetting = (setting) => {
    if (confirm(`Are you sure you want to delete the platform setting "${setting.key}"?`)) {
        router.delete(route('admin.platform-settings.destroy', setting.id), {
            preserveScroll: true,
        })
    }
}

const activeSection = computed(() => {
    return props.active || 'settings'
})
</script>

<template>
    <Head :title="title || 'Settings'" />

    <AdminLayout :active="activeSection">
        <div class="w-full">

            <!-- =========================================================
                 PAGE HEADER
            ========================================================== -->

            <div class="pb-5">
                <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-[#087F8C]">
                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                    <span>{{ eyebrow || 'Settings' }}</span>
                </div>

                <h1
                    class="mt-1 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl"
                >
                    {{ title || 'Settings' }}
                </h1>

                <p
                    v-if="description"
                    class="mt-0.5 text-xs text-slate-500"
                >
                    {{ description }}
                </p>
            </div>

            <!-- =========================================================
                 SUCCESS MESSAGE
            ========================================================== -->

            <div
                v-if="$page.props.flash?.status"
                class="mb-5 flex items-start gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-700"
            >
                <svg
                    class="mt-0.5 h-4 w-4 shrink-0"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="m5 12 4 4L19 6"
                    />
                </svg>

                <span>{{ $page.props.flash.status }}</span>
            </div>

            <!-- =========================================================
                 ANNOUNCEMENTS
            ========================================================== -->

            <section
                class="rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
            >
                <!-- Section Header -->

                <div
                    class="border-b border-[#E5E7EB] px-5 py-4"
                >
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span
                                    class="h-1.5 w-1.5 rounded-full bg-[#16A6A0]"
                                ></span>

                                <p
                                    class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#94A3B8]"
                                >
                                    Marketplace communications
                                </p>
                            </div>

                            <h2
                                class="mt-1 text-base font-bold text-[#1F2937]"
                            >
                                Announcements
                            </h2>

                            <p
                                class="mt-1 text-xs text-[#64748B]"
                            >
                                Publish important messages for buyers and sellers.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-lg bg-[#087F8C] px-3.5 py-2 text-[11px] font-semibold text-white shadow-sm transition hover:bg-[#066E79] focus:outline-none focus:ring-2 focus:ring-[#087F8C]/20"
                            @click="
                                showAnnouncementForm =
                                    !showAnnouncementForm
                            "
                        >
                            <svg
                                v-if="!showAnnouncementForm"
                                class="h-3.5 w-3.5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    d="M12 5v14M5 12h14"
                                />
                            </svg>

                            <svg
                                v-else
                                class="h-3.5 w-3.5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    d="M6 6l12 12M18 6 6 18"
                                />
                            </svg>

                            {{
                                showAnnouncementForm
                                    ? 'Close Form'
                                    : 'Add Announcement'
                            }}
                        </button>
                    </div>
                </div>

                <!-- Announcement Form -->

                <form
                    v-if="showAnnouncementForm"
                    class="border-b border-[#E5E7EB] bg-[#F8FAF9] p-4 sm:p-5"
                    @submit.prevent="submitAnnouncement"
                >
                    <div class="rounded-xl border border-[#E5E7EB] bg-white p-4">
                        <div class="mb-4">
                            <p
                                class="text-xs font-bold text-[#1F2937]"
                            >
                                Create announcement
                            </p>

                            <p
                                class="mt-1 text-[11px] text-[#94A3B8]"
                            >
                                Add a message that will be visible to marketplace users.
                            </p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">

                            <!-- Title -->

                            <div class="md:col-span-2">
                                <label
                                    for="announcement-title"
                                    class="mb-1.5 block text-[11px] font-semibold text-[#475569]"
                                >
                                    Title
                                </label>

                                <input
                                    id="announcement-title"
                                    v-model="announcementForm.title"
                                    type="text"
                                    maxlength="255"
                                    placeholder="Announcement title"
                                    class="w-full rounded-lg border border-[#E5E7EB] bg-white px-3 py-2.5 text-xs text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10"
                                />

                                <p
                                    v-if="announcementForm.errors.title"
                                    class="mt-1 text-[10px] font-semibold text-[#E85D5D]"
                                >
                                    {{ announcementForm.errors.title }}
                                </p>
                            </div>

                            <!-- Body -->

                            <div class="md:col-span-2">
                                <label
                                    for="announcement-body"
                                    class="mb-1.5 block text-[11px] font-semibold text-[#475569]"
                                >
                                    Message
                                </label>

                                <textarea
                                    id="announcement-body"
                                    v-model="announcementForm.body"
                                    rows="5"
                                    maxlength="5000"
                                    placeholder="Write your announcement..."
                                    class="w-full resize-y rounded-lg border border-[#E5E7EB] bg-white px-3 py-2.5 text-xs leading-5 text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10"
                                ></textarea>

                                <p
                                    v-if="announcementForm.errors.body"
                                    class="mt-1 text-[10px] font-semibold text-[#E85D5D]"
                                >
                                    {{ announcementForm.errors.body }}
                                </p>
                            </div>

                            <!-- Status -->

                            <div>
                                <label
                                    for="announcement-status"
                                    class="mb-1.5 block text-[11px] font-semibold text-[#475569]"
                                >
                                    Status
                                </label>

                                <select
                                    id="announcement-status"
                                    v-model="announcementForm.status"
                                    class="w-full rounded-lg border border-[#E5E7EB] bg-white px-3 py-2.5 text-xs text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10"
                                >
                                    <option value="draft">
                                        Draft
                                    </option>

                                    <option value="published">
                                        Published
                                    </option>

                                    <option value="archived">
                                        Archived
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Actions -->

                        <div
                            class="mt-4 flex flex-wrap justify-end gap-2 border-t border-[#E5E7EB] pt-4"
                        >
                            <button
                                type="button"
                                class="rounded-lg border border-[#E5E7EB] bg-white px-3.5 py-2 text-[11px] font-semibold text-[#64748B] transition hover:bg-[#F8FAF9] hover:text-[#1F2937]"
                                @click="cancelAnnouncement"
                            >
                                Cancel
                            </button>

                            <button
                                type="submit"
                                :disabled="announcementForm.processing"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-[#087F8C] px-4 py-2 text-[11px] font-semibold text-white shadow-sm transition hover:bg-[#066E79] disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <svg
                                    v-if="!announcementForm.processing"
                                    class="h-3.5 w-3.5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M5 12h14M13 6l6 6-6 6"
                                    />
                                </svg>

                                <svg
                                    v-else
                                    class="h-3.5 w-3.5 animate-spin"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <circle
                                        cx="12"
                                        cy="12"
                                        r="9"
                                        class="opacity-30"
                                    />

                                    <path
                                        stroke-linecap="round"
                                        d="M21 12a9 9 0 0 1-9 9"
                                    />
                                </svg>

                                {{
                                    announcementForm.processing
                                        ? 'Saving...'
                                        : 'Save Announcement'
                                }}
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Announcement List -->

                <div class="space-y-3 p-4 sm:p-5">
                    <div
                        v-for="item in announcements"
                        :key="item.id"
                        class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-4 transition hover:border-[#C9D7D5] hover:shadow-sm"
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div class="min-w-0">
                                <div
                                    class="flex flex-wrap items-center gap-2"
                                >
                                    <h3
                                        class="text-xs font-semibold text-[#1F2937]"
                                    >
                                        {{ item.title }}
                                    </h3>

                                    <span
                                        class="inline-flex rounded-full px-2 py-1 text-[10px] font-semibold capitalize"
                                        :class="
                                            announcementStatusClass(
                                                item.status
                                            )
                                        "
                                    >
                                        {{ item.status || 'draft' }}
                                    </span>
                                </div>

                                <p
                                    class="mt-2 whitespace-pre-line text-[11px] leading-5 text-[#64748B]"
                                >
                                    {{ item.body }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="mt-3 flex flex-wrap gap-x-5 gap-y-1.5 border-t border-[#E5E7EB] pt-2.5 text-[10px] text-[#94A3B8]"
                        >
                            <span>
                                Created
                                {{ formatDate(item.created_at) }}
                            </span>

                            <span v-if="item.published_at">
                                Published
                                {{ formatDate(item.published_at) }}
                            </span>
                        </div>
                    </div>

                    <!-- Empty State -->

                    <div
                        v-if="!announcements.length"
                        class="rounded-xl border border-dashed border-[#DCE5E3] bg-[#F8FAF9] p-8 text-center"
                    >
                        <div
                            class="mx-auto flex h-10 w-10 items-center justify-center rounded-xl bg-white text-[#94A3B8] shadow-sm ring-1 ring-[#E5E7EB]"
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
                                    d="M4 5h16v11H7l-3 3V5Z"
                                />
                            </svg>
                        </div>

                        <p
                            class="mt-2.5 text-xs font-semibold text-[#64748B]"
                        >
                            No announcements yet.
                        </p>

                        <p
                            class="mt-1 text-[10px] text-[#94A3B8]"
                        >
                            Create your first marketplace announcement above.
                        </p>
                    </div>
                </div>
            </section>

            <!-- =========================================================
                 POLICIES
            ========================================================== -->

            <section
                class="mt-5 rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
            >
                <!-- Section Header -->

                <div
                    class="border-b border-[#E5E7EB] px-5 py-4"
                >
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span
                                    class="h-1.5 w-1.5 rounded-full bg-[#16A6A0]"
                                ></span>

                                <p
                                    class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#94A3B8]"
                                >
                                    Platform policies
                                </p>
                            </div>

                            <h2
                                class="mt-1 text-base font-bold text-[#1F2937]"
                            >
                                Policies
                            </h2>

                            <p
                                class="mt-1 text-xs text-[#64748B]"
                            >
                                Manage the policies that govern the Zellora marketplace.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-lg bg-[#087F8C] px-3.5 py-2 text-[11px] font-semibold text-white shadow-sm transition hover:bg-[#066E79] focus:outline-none focus:ring-2 focus:ring-[#087F8C]/20"
                            @click="
                                showPolicyForm =
                                    !showPolicyForm
                            "
                        >
                            <svg
                                v-if="!showPolicyForm"
                                class="h-3.5 w-3.5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    d="M12 5v14M5 12h14"
                                />
                            </svg>

                            <svg
                                v-else
                                class="h-3.5 w-3.5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    d="M6 6l12 12M18 6 6 18"
                                />
                            </svg>

                            {{
                                showPolicyForm
                                    ? 'Close Form'
                                    : 'Add Policy'
                            }}
                        </button>
                    </div>
                </div>

                <!-- New Policy Form -->

                <form
                    v-if="showPolicyForm"
                    class="border-b border-[#E5E7EB] bg-[#F8FAF9] p-4 sm:p-5"
                    @submit.prevent="submitNewPolicy"
                >
                    <div class="rounded-xl border border-[#E5E7EB] bg-white p-4">
                        <div class="mb-4">
                            <p
                                class="text-xs font-bold text-[#1F2937]"
                            >
                                Create marketplace policy
                            </p>

                            <p
                                class="mt-1 text-[11px] text-[#94A3B8]"
                            >
                                Define a new policy and choose whether it should be published.
                            </p>
                        </div>

                        <div class="grid gap-4">

                            <!-- Title -->

                            <div>
                                <label
                                    for="new-policy-title"
                                    class="mb-1.5 block text-[11px] font-semibold text-[#475569]"
                                >
                                    Policy Title
                                </label>

                                <input
                                    id="new-policy-title"
                                    v-model="policyForm.title"
                                    type="text"
                                    maxlength="255"
                                    placeholder="e.g. Terms and Conditions"
                                    class="w-full rounded-lg border border-[#E5E7EB] bg-white px-3 py-2.5 text-xs text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10"
                                />

                                <p
                                    v-if="policyForm.errors.title"
                                    class="mt-1 text-[10px] font-semibold text-[#E85D5D]"
                                >
                                    {{ policyForm.errors.title }}
                                </p>
                            </div>

                            <!-- Version -->

                            <div>
                                <label
                                    for="new-policy-version"
                                    class="mb-1.5 block text-[11px] font-semibold text-[#475569]"
                                >
                                    Version
                                </label>

                                <input
                                    id="new-policy-version"
                                    v-model="policyForm.version"
                                    type="text"
                                    maxlength="50"
                                    placeholder="e.g. 1.0"
                                    class="w-full rounded-lg border border-[#E5E7EB] bg-white px-3 py-2.5 text-xs text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10"
                                />

                                <p
                                    v-if="policyForm.errors.version"
                                    class="mt-1 text-[10px] font-semibold text-[#E85D5D]"
                                >
                                    {{ policyForm.errors.version }}
                                </p>
                            </div>

                            <!-- Content -->

                            <div>
                                <label
                                    for="new-policy-content"
                                    class="mb-1.5 block text-[11px] font-semibold text-[#475569]"
                                >
                                    Policy Content
                                </label>

                                <textarea
                                    id="new-policy-content"
                                    v-model="policyForm.content"
                                    rows="10"
                                    placeholder="Write the policy content..."
                                    class="w-full resize-y rounded-lg border border-[#E5E7EB] bg-white px-3 py-2.5 text-xs leading-5 text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10"
                                ></textarea>

                                <p
                                    v-if="policyForm.errors.content"
                                    class="mt-1 text-[10px] font-semibold text-[#E85D5D]"
                                >
                                    {{ policyForm.errors.content }}
                                </p>
                            </div>

                            <!-- Publish -->

                            <label
                                class="flex cursor-pointer items-center gap-3 rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-3.5 transition hover:border-[#C9D7D5]"
                            >
                                <input
                                    v-model="policyForm.is_published"
                                    type="checkbox"
                                    class="h-3.5 w-3.5 rounded border-slate-300 text-[#087F8C] focus:ring-[#087F8C]"
                                />

                                <span>
                                    <span
                                        class="block text-[11px] font-semibold text-[#475569]"
                                    >
                                        Publish this policy
                                    </span>

                                    <span
                                        class="mt-0.5 block text-[10px] leading-4 text-[#94A3B8]"
                                    >
                                        Published policies are treated as active marketplace policies.
                                    </span>
                                </span>
                            </label>
                        </div>

                        <!-- Actions -->

                        <div
                            class="mt-4 flex flex-wrap justify-end gap-2 border-t border-[#E5E7EB] pt-4"
                        >
                            <button
                                type="button"
                                class="rounded-lg border border-[#E5E7EB] bg-white px-3.5 py-2 text-[11px] font-semibold text-[#64748B] transition hover:bg-[#F8FAF9]"
                                @click="cancelNewPolicy"
                            >
                                Cancel
                            </button>

                            <button
                                type="submit"
                                :disabled="policyForm.processing"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-[#087F8C] px-4 py-2 text-[11px] font-semibold text-white shadow-sm transition hover:bg-[#066E79] disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <svg
                                    v-if="!policyForm.processing"
                                    class="h-3.5 w-3.5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        d="M12 5v14M5 12h14"
                                    />
                                </svg>

                                <svg
                                    v-else
                                    class="h-3.5 w-3.5 animate-spin"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <circle
                                        cx="12"
                                        cy="12"
                                        r="9"
                                        class="opacity-30"
                                    />

                                    <path
                                        stroke-linecap="round"
                                        d="M21 12a9 9 0 0 1-9 9"
                                    />
                                </svg>

                                {{
                                    policyForm.processing
                                        ? 'Creating...'
                                        : 'Add Policy'
                                }}
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Existing Policies -->

                <div class="space-y-3 p-4 sm:p-5">
                    <article
                        v-for="item in policies"
                        :key="item.id"
                        class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-4 transition hover:border-[#C9D7D5]"
                    >
                        <!-- View Mode -->

                        <template
                            v-if="editingPolicyId !== item.id"
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="min-w-0">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <h3
                                            class="text-xs font-semibold text-[#1F2937]"
                                        >
                                            {{ item.title }}
                                        </h3>

                                        <span
                                            class="inline-flex rounded-full px-2 py-1 text-[10px] font-semibold"
                                            :class="
                                                policyStatusClass(
                                                    item.is_published
                                                )
                                            "
                                        >
                                            {{
                                                item.is_published
                                                    ? 'Published'
                                                    : 'Draft'
                                            }}
                                        </span>
                                    </div>

                                    <p
                                        v-if="item.version"
                                        class="mt-1 text-[10px] font-semibold text-[#94A3B8]"
                                    >
                                        Version {{ item.version }}
                                    </p>

                                    <p
                                        class="mt-2.5 whitespace-pre-line text-[11px] leading-5 text-[#64748B]"
                                    >
                                        {{ item.content }}
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-[#E5E7EB] bg-white px-3 py-2 text-[10px] font-semibold text-[#64748B] transition hover:border-[#087F8C] hover:bg-[#E8F7F6] hover:text-[#087F8C]"
                                    @click="
                                        startEditingPolicy(item)
                                    "
                                >
                                    <svg
                                        class="h-3 w-3"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="m14 6 4 4M4 20l4.5-1 10-10a2.8 2.8 0 0 0-4-4l-10 10L4 20Z"
                                        />
                                    </svg>

                                    Edit Policy
                                </button>
                            </div>

                            <div
                                class="mt-3 border-t border-[#E5E7EB] pt-2.5 text-[10px] text-[#94A3B8]"
                            >
                                Last updated
                                {{ formatDate(item.updated_at) }}
                            </div>
                        </template>

                        <!-- Edit Mode -->

                        <form
                            v-else
                            @submit.prevent="
                                submitPolicy(item)
                            "
                        >
                            <div
                                class="rounded-xl border border-[#E5E7EB] bg-white p-4"
                            >
                                <div class="mb-4">
                                    <p
                                        class="text-xs font-bold text-[#1F2937]"
                                    >
                                        Edit policy
                                    </p>

                                    <p
                                        class="mt-1 text-[11px] text-[#94A3B8]"
                                    >
                                        Update the policy details and publication status.
                                    </p>
                                </div>

                                <div class="grid gap-4">

                                    <!-- Title -->

                                    <div>
                                        <label
                                            :for="`policy-title-${item.id}`"
                                            class="mb-1.5 block text-[11px] font-semibold text-[#475569]"
                                        >
                                            Title
                                        </label>

                                        <input
                                            :id="`policy-title-${item.id}`"
                                            v-model="
                                                getPolicyForm(item).title
                                            "
                                            type="text"
                                            maxlength="255"
                                            class="w-full rounded-lg border border-[#E5E7EB] bg-white px-3 py-2.5 text-xs text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10"
                                        />

                                        <p
                                            v-if="
                                                getPolicyForm(item)
                                                    .errors.title
                                            "
                                            class="mt-1 text-[10px] font-semibold text-[#E85D5D]"
                                        >
                                            {{
                                                getPolicyForm(item)
                                                    .errors.title
                                            }}
                                        </p>
                                    </div>

                                    <!-- Version -->

                                    <div>
                                        <label
                                            :for="`policy-version-${item.id}`"
                                            class="mb-1.5 block text-[11px] font-semibold text-[#475569]"
                                        >
                                            Version
                                        </label>

                                        <input
                                            :id="`policy-version-${item.id}`"
                                            v-model="
                                                getPolicyForm(item).version
                                            "
                                            type="text"
                                            maxlength="50"
                                            placeholder="e.g. 1.0"
                                            class="w-full rounded-lg border border-[#E5E7EB] bg-white px-3 py-2.5 text-xs text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10"
                                        />

                                        <p
                                            v-if="
                                                getPolicyForm(item)
                                                    .errors.version
                                            "
                                            class="mt-1 text-[10px] font-semibold text-[#E85D5D]"
                                        >
                                            {{
                                                getPolicyForm(item)
                                                    .errors.version
                                            }}
                                        </p>
                                    </div>

                                    <!-- Content -->

                                    <div>
                                        <label
                                            :for="`policy-content-${item.id}`"
                                            class="mb-1.5 block text-[11px] font-semibold text-[#475569]"
                                        >
                                            Policy Content
                                        </label>

                                        <textarea
                                            :id="`policy-content-${item.id}`"
                                            v-model="
                                                getPolicyForm(item).content
                                            "
                                            rows="10"
                                            class="w-full resize-y rounded-lg border border-[#E5E7EB] bg-white px-3 py-2.5 text-xs leading-5 text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10"
                                        ></textarea>

                                        <p
                                            v-if="
                                                getPolicyForm(item)
                                                    .errors.content
                                            "
                                            class="mt-1 text-[10px] font-semibold text-[#E85D5D]"
                                        >
                                            {{
                                                getPolicyForm(item)
                                                    .errors.content
                                            }}
                                        </p>
                                    </div>

                                    <!-- Publish -->

                                    <label
                                        class="flex cursor-pointer items-center gap-3 rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-3.5 transition hover:border-[#C9D7D5]"
                                    >
                                        <input
                                            v-model="
                                                getPolicyForm(item)
                                                    .is_published
                                            "
                                            type="checkbox"
                                            class="h-3.5 w-3.5 rounded border-slate-300 text-[#087F8C] focus:ring-[#087F8C]"
                                        />

                                        <span>
                                            <span
                                                class="block text-[11px] font-semibold text-[#475569]"
                                            >
                                                Publish this policy
                                            </span>

                                            <span
                                                class="mt-0.5 block text-[10px] leading-4 text-[#94A3B8]"
                                            >
                                                Published policies can be treated as active marketplace policies.
                                            </span>
                                        </span>
                                    </label>
                                </div>

                                <!-- Actions -->

                                <div
                                    class="mt-4 flex flex-wrap justify-end gap-2 border-t border-[#E5E7EB] pt-4"
                                >
                                    <button
                                        type="button"
                                        class="rounded-lg border border-[#E5E7EB] bg-white px-3.5 py-2 text-[11px] font-semibold text-[#64748B] transition hover:bg-[#F8FAF9]"
                                        @click="
                                            cancelEditingPolicy
                                        "
                                    >
                                        Cancel
                                    </button>

                                    <button
                                        type="submit"
                                        :disabled="
                                            getPolicyForm(item)
                                                .processing
                                        "
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-[#087F8C] px-4 py-2 text-[11px] font-semibold text-white shadow-sm transition hover:bg-[#066E79] disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        <svg
                                            v-if="
                                                !getPolicyForm(item)
                                                    .processing
                                            "
                                            class="h-3.5 w-3.5"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="m5 12 4 4L19 6"
                                            />
                                        </svg>

                                        <svg
                                            v-else
                                            class="h-3.5 w-3.5 animate-spin"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                        >
                                            <circle
                                                cx="12"
                                                cy="12"
                                                r="9"
                                                class="opacity-30"
                                            />

                                            <path
                                                stroke-linecap="round"
                                                d="M21 12a9 9 0 0 1-9 9"
                                            />
                                        </svg>

                                        {{
                                            getPolicyForm(item)
                                                .processing
                                                ? 'Updating...'
                                                : 'Update Policy'
                                        }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </article>

                    <!-- Empty State -->

                    <div
                        v-if="!policies.length"
                        class="rounded-xl border border-dashed border-[#DCE5E3] bg-[#F8FAF9] p-8 text-center"
                    >
                        <div
                            class="mx-auto flex h-10 w-10 items-center justify-center rounded-xl bg-white text-[#94A3B8] shadow-sm ring-1 ring-[#E5E7EB]"
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
                                    d="M6 4h12v16H6z"
                                />

                                <path
                                    stroke-linecap="round"
                                    d="M9 8h6M9 12h6M9 16h4"
                                />
                            </svg>
                        </div>

                        <p
                            class="mt-2.5 text-xs font-semibold text-[#64748B]"
                        >
                            No policies configured.
                        </p>

                        <p
                            class="mt-1 text-[10px] text-[#94A3B8]"
                        >
                            Click Add Policy to create your first marketplace policy.
                        </p>
                    </div>
                </div>
            </section>


            <!-- =========================================================
                 FAQS
            ========================================================== -->

            <section
                class="mt-5 rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
            >
                <!-- Section Header -->
                <div class="border-b border-[#E5E7EB] px-5 py-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="h-1.5 w-1.5 rounded-full bg-[#16A6A0]"></span>
                                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#94A3B8]">
                                    Platform Knowledge Base
                                </p>
                            </div>
                            <h2 class="mt-1 text-base font-bold text-[#1F2937]">
                                Frequently Asked Questions (FAQs)
                            </h2>
                            <p class="mt-1 text-xs text-[#64748B]">
                                Create, organize, and publish FAQs for marketplace buyers and sellers.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-lg bg-[#087F8C] px-3.5 py-2 text-[11px] font-semibold text-white shadow-sm transition hover:bg-[#066E79] focus:outline-none focus:ring-2 focus:ring-[#087F8C]/20"
                            @click="showFaqForm = !showFaqForm"
                        >
                            <svg v-if="!showFaqForm" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" d="M12 5v14M5 12h14" />
                            </svg>
                            <svg v-else class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" />
                            </svg>
                            {{ showFaqForm ? 'Close Form' : 'Add FAQ' }}
                        </button>
                    </div>
                </div>

                <!-- New FAQ Form -->
                <form
                    v-if="showFaqForm"
                    class="border-b border-[#E5E7EB] bg-[#F8FAF9] p-4 sm:p-5"
                    @submit.prevent="submitNewFaq"
                >
                    <div class="rounded-xl border border-[#E5E7EB] bg-white p-4">
                        <div class="mb-4">
                            <p class="text-xs font-bold text-[#1F2937]">Create new FAQ</p>
                            <p class="mt-1 text-[11px] text-[#94A3B8]">
                                Enter the question, answer, category, and display order.
                            </p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="md:col-span-2">
                                <label for="faq-question" class="mb-1.5 block text-[11px] font-semibold text-[#475569]">
                                    Question
                                </label>
                                <input
                                    id="faq-question"
                                    v-model="faqForm.question"
                                    type="text"
                                    maxlength="500"
                                    placeholder="e.g. How do I request a refund?"
                                    class="w-full rounded-lg border border-[#E5E7EB] bg-white px-3 py-2.5 text-xs text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10"
                                />
                                <p v-if="faqForm.errors.question" class="mt-1 text-[10px] font-semibold text-[#E85D5D]">
                                    {{ faqForm.errors.question }}
                                </p>
                            </div>

                            <div>
                                <label for="faq-category" class="mb-1.5 block text-[11px] font-semibold text-[#475569]">
                                    Category
                                </label>
                                <select
                                    id="faq-category"
                                    v-model="faqForm.category"
                                    class="w-full rounded-lg border border-[#E5E7EB] bg-white px-3 py-2.5 text-xs text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10"
                                >
                                    <option v-for="cat in faqCategories" :key="cat.value" :value="cat.value">
                                        {{ cat.label }}
                                    </option>
                                </select>
                                <p v-if="faqForm.errors.category" class="mt-1 text-[10px] font-semibold text-[#E85D5D]">
                                    {{ faqForm.errors.category }}
                                </p>
                            </div>

                            <div class="md:col-span-3">
                                <label for="faq-answer" class="mb-1.5 block text-[11px] font-semibold text-[#475569]">
                                    Answer
                                </label>
                                <textarea
                                    id="faq-answer"
                                    v-model="faqForm.answer"
                                    rows="5"
                                    placeholder="Write a clear and comprehensive answer..."
                                    class="w-full resize-y rounded-lg border border-[#E5E7EB] bg-white px-3 py-2.5 text-xs leading-5 text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10"
                                ></textarea>
                                <p v-if="faqForm.errors.answer" class="mt-1 text-[10px] font-semibold text-[#E85D5D]">
                                    {{ faqForm.errors.answer }}
                                </p>
                            </div>

                            <div>
                                <label for="faq-sort-order" class="mb-1.5 block text-[11px] font-semibold text-[#475569]">
                                    Sort Order
                                </label>
                                <input
                                    id="faq-sort-order"
                                    v-model="faqForm.sort_order"
                                    type="number"
                                    min="0"
                                    placeholder="0"
                                    class="w-full rounded-lg border border-[#E5E7EB] bg-white px-3 py-2.5 text-xs text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10"
                                />
                            </div>

                            <div class="md:col-span-2 flex items-center pt-5">
                                <label class="flex cursor-pointer items-center gap-3">
                                    <input
                                        v-model="faqForm.is_published"
                                        type="checkbox"
                                        class="h-3.5 w-3.5 rounded border-slate-300 text-[#087F8C] focus:ring-[#087F8C]"
                                    />
                                    <span class="text-[11px] font-semibold text-[#475569]">
                                        Publish immediately (visible to buyers)
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap justify-end gap-2 border-t border-[#E5E7EB] pt-4">
                            <button
                                type="button"
                                class="rounded-lg border border-[#E5E7EB] bg-white px-3.5 py-2 text-[11px] font-semibold text-[#64748B] transition hover:bg-[#F8FAF9]"
                                @click="cancelNewFaq"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="faqForm.processing"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-[#087F8C] px-4 py-2 text-[11px] font-semibold text-white shadow-sm transition hover:bg-[#066E79] disabled:opacity-50"
                            >
                                {{ faqForm.processing ? 'Saving...' : 'Save FAQ' }}
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Existing FAQs List -->
                <div class="space-y-3 p-4 sm:p-5">
                    <article
                        v-for="item in faqs"
                        :key="item.id"
                        class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-4 transition hover:border-[#C9D7D5]"
                    >
                        <!-- View Mode -->
                        <template v-if="editingFaqId !== item.id">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-xs font-bold text-[#1F2937]">
                                            {{ item.question }}
                                        </h3>
                                        <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-slate-600">
                                            {{ item.category }}
                                        </span>
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                            :class="item.is_published ? 'bg-[#E8F7F6] text-[#087F8C]' : 'bg-slate-100 text-slate-500'"
                                        >
                                            {{ item.is_published ? 'Published' : 'Draft' }}
                                        </span>
                                    </div>
                                    <p class="mt-2 text-[11px] leading-5 text-[#64748B] whitespace-pre-line">
                                        {{ item.answer }}
                                    </p>
                                    <p class="mt-2 text-[10px] text-[#94A3B8]">
                                        Sort order: {{ item.sort_order ?? 0 }}
                                    </p>
                                </div>

                                <div class="flex shrink-0 items-center gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-[#E5E7EB] bg-white px-2.5 py-1.5 text-[11px] font-semibold text-[#087F8C] transition hover:bg-[#E8F7F6]"
                                        @click="startEditingFaq(item)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-red-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-red-600 transition hover:bg-red-50"
                                        @click="deleteFaq(item)"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </template>

                        <!-- Edit Mode -->
                        <template v-else>
                            <form @submit.prevent="submitFaq(item)">
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <div class="sm:col-span-2">
                                        <label class="block text-[10px] font-bold text-gray-700">Question</label>
                                        <input
                                            v-model="getFaqForm(item).question"
                                            type="text"
                                            class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-700">Category</label>
                                        <select
                                            v-model="getFaqForm(item).category"
                                            class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs"
                                        >
                                            <option v-for="cat in faqCategories" :key="cat.value" :value="cat.value">
                                                {{ cat.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="sm:col-span-3">
                                        <label class="block text-[10px] font-bold text-gray-700">Answer</label>
                                        <textarea
                                            v-model="getFaqForm(item).answer"
                                            rows="4"
                                            class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs"
                                            required
                                        ></textarea>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-gray-700">Sort Order</label>
                                        <input
                                            v-model="getFaqForm(item).sort_order"
                                            type="number"
                                            min="0"
                                            class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs"
                                        />
                                    </div>
                                    <div class="sm:col-span-2 flex items-center pt-4">
                                        <label class="flex cursor-pointer items-center gap-2">
                                            <input
                                                v-model="getFaqForm(item).is_published"
                                                type="checkbox"
                                                class="h-3.5 w-3.5 rounded border-slate-300 text-[#087F8C]"
                                            />
                                            <span class="text-xs font-semibold text-gray-700">Published</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="mt-3 flex justify-end gap-2 border-t border-gray-200 pt-3">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50"
                                        @click="cancelEditingFaq"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        :disabled="getFaqForm(item).processing"
                                        class="rounded-lg bg-[#087F8C] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#066E79] disabled:opacity-50"
                                    >
                                        {{ getFaqForm(item).processing ? 'Updating...' : 'Update FAQ' }}
                                    </button>
                                </div>
                            </form>
                        </template>
                    </article>

                    <!-- Empty state -->
                    <div
                        v-if="!faqs.length"
                        class="rounded-xl border border-dashed border-[#DCE5E3] bg-[#F8FAF9] p-8 text-center"
                    >
                        <p class="text-xs font-semibold text-[#64748B]">No FAQs configured yet.</p>
                        <p class="mt-1 text-[10px] text-[#94A3B8]">Click Add FAQ to create the first frequently asked question.</p>
                    </div>
                </div>
            </section>

            <!-- =========================================================
                 HOMEPAGE HERO BANNER SETTINGS
            ========================================================== -->

            <section
                class="mt-5 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
            >
                <!-- Section Header -->
                <div class="border-b border-[#E5E7EB] px-5 py-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="h-1.5 w-1.5 rounded-full bg-[#F4B942]"></span>
                                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#94A3B8]">
                                    Marketplace Header Banners
                                </p>
                            </div>
                            <h2 class="mt-1 text-base font-bold text-[#1F2937]">
                                Homepage Hero Banners (Multi-Slide Carousel)
                            </h2>
                            <p class="mt-1 text-xs text-[#64748B]">
                                Create and manage multiple rotating hero banners for the homepage. Admin can configure custom headlines, buttons, color themes, and illustrations for each slide.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <span v-if="bannerSavedRecently" class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 border border-emerald-200 animate-pulse">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                Saved Successfully!
                            </span>
                            <button
                                type="button"
                                class="rounded-lg border border-[#E5E7EB] bg-white px-3 py-1.5 text-xs font-semibold text-[#64748B] transition hover:bg-[#F8FAF9]"
                                @click="resetAllBannersToDefault"
                            >
                                Reset to Default
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-[#087F8C] bg-[#E8F7F6] px-3 py-1.5 text-xs font-bold text-[#087F8C] transition hover:bg-[#d6f1ef]"
                                @click="addSlide"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                Add Slide
                            </button>
                            <button
                                type="button"
                                :disabled="isSavingBanners"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-[#087F8C] px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-[#066E79] disabled:opacity-50"
                                @click="saveAllHeroBanners"
                            >
                                <svg
                                    v-if="isSavingBanners"
                                    class="h-3.5 w-3.5 animate-spin"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                >
                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25" />
                                    <path fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" class="opacity-75" />
                                </svg>
                                {{ isSavingBanners ? 'Saving Banners...' : 'Save All Hero Banners' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Slide Navigation Tabs -->
                <div class="border-b border-[#E5E7EB] bg-[#F8FAF9] px-5 py-3">
                    <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-thin">
                        <button
                            v-for="(slide, sIdx) in bannerSlides"
                            :key="slide.id || sIdx"
                            type="button"
                            class="group relative flex items-center gap-2.5 rounded-xl border px-3.5 py-2 text-xs font-medium transition whitespace-nowrap"
                            :class="activeSlideIndex === sIdx
                                ? 'border-[#087F8C] bg-white text-[#087F8C] shadow-sm ring-1 ring-[#087F8C]'
                                : 'border-[#E5E7EB] bg-white text-[#64748B] hover:border-slate-300 hover:text-[#1F2937]'"
                            @click="activeSlideIndex = sIdx"
                        >
                            <!-- Active Status Dot -->
                            <span
                                class="h-2 w-2 rounded-full"
                                :class="slide.is_active ? 'bg-emerald-500' : 'bg-slate-300'"
                                :title="slide.is_active ? 'Active on Storefront' : 'Inactive / Hidden'"
                            ></span>

                            <!-- Theme Color Dot -->
                            <span
                                class="h-2.5 w-2.5 rounded-full border border-black/10"
                                :style="{ backgroundColor: themeOptions.find(t => t.key === slide.theme)?.color || '#087F8C' }"
                            ></span>

                            <span class="font-bold">Slide {{ sIdx + 1 }}</span>
                            <span class="max-w-[120px] truncate text-[11px] text-slate-500">
                                {{ slide.title_highlight || slide.title || 'Untitled' }}
                            </span>
                        </button>

                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-dashed border-[#CBD5E1] bg-white/70 px-3 py-2 text-xs font-semibold text-[#087F8C] transition hover:border-[#087F8C] hover:bg-white"
                            @click="addSlide"
                        >
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                            + New Slide
                        </button>
                    </div>
                </div>

                <!-- Slide Management Form -->
                <div class="p-5 sm:p-6 space-y-6">
                    <!-- Slide Action Bar (Reorder / Status / Duplicate / Delete) -->
                    <div class="flex flex-col gap-3 rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-3.5 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-2">
                            <span class="rounded-lg bg-[#E8F7F6] px-2.5 py-1 text-xs font-bold text-[#087F8C]">
                                Slide {{ activeSlideIndex + 1 }} of {{ bannerSlides.length }}
                            </span>

                            <!-- Reorder buttons -->
                            <button
                                type="button"
                                :disabled="activeSlideIndex === 0"
                                class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-[#E5E7EB] bg-white text-xs font-bold text-[#475569] transition hover:bg-slate-50 disabled:opacity-30"
                                title="Move Left / Earlier"
                                @click="moveSlide(activeSlideIndex, -1)"
                            >
                                ←
                            </button>
                            <button
                                type="button"
                                :disabled="activeSlideIndex === bannerSlides.length - 1"
                                class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-[#E5E7EB] bg-white text-xs font-bold text-[#475569] transition hover:bg-slate-50 disabled:opacity-30"
                                title="Move Right / Later"
                                @click="moveSlide(activeSlideIndex, 1)"
                            >
                                →
                            </button>

                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg border border-[#E5E7EB] bg-white px-2.5 py-1 text-xs font-medium text-[#475569] transition hover:bg-slate-50"
                                title="Duplicate this slide"
                                @click="duplicateSlide(activeSlideIndex)"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" /></svg>
                                Duplicate
                            </button>

                            <button
                                type="button"
                                :disabled="bannerSlides.length <= 1"
                                class="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-white px-2.5 py-1 text-xs font-medium text-red-600 transition hover:bg-red-50 disabled:opacity-30"
                                title="Delete this slide"
                                @click="removeSlide(activeSlideIndex)"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                Delete
                            </button>
                        </div>

                        <!-- Active Toggle -->
                        <div class="flex items-center gap-3">
                            <label class="flex cursor-pointer items-center gap-2 text-xs font-semibold text-[#1F2937]">
                                <input
                                    v-model="currentEditSlide.is_active"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-[#CBD5E1] text-[#087F8C] focus:ring-[#087F8C]"
                                />
                                <span>Active on Homepage Carousel</span>
                            </label>
                        </div>
                    </div>

                    <!-- Banner Layout Format Selector (Split vs Full Image) -->
                    <div class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-sm">
                        <label class="block text-xs font-bold text-[#1F2937] mb-1">Banner Layout Style</label>
                        <p class="text-[11px] text-[#64748B] mb-3">Choose whether this slide uses a split illustration layout or a full-width promotional graphic banner.</p>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <!-- Option 1: Split Layout -->
                            <button
                                type="button"
                                class="flex items-start gap-3 rounded-xl border p-3.5 text-left transition"
                                :class="currentEditSlide.layout_type === 'split' || !currentEditSlide.layout_type
                                    ? 'border-[#087F8C] bg-[#E8F7F6]/50 ring-2 ring-[#087F8C]'
                                    : 'border-[#E5E7EB] bg-[#F8FAF9] hover:border-slate-300'"
                                @click="currentEditSlide.layout_type = 'split'"
                            >
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white border border-[#CBD5E1] text-[#087F8C] font-bold text-sm shadow-sm">
                                    ◧
                                </span>
                                <div>
                                    <p class="text-xs font-bold text-[#1F2937]">Split Content (Text & Illustration)</p>
                                    <p class="text-[11px] text-[#64748B] mt-0.5">Headline and action buttons on left, floating illustration/mascot on the right.</p>
                                </div>
                            </button>

                            <!-- Option 2: Full Image Banner -->
                            <button
                                type="button"
                                class="flex items-start gap-3 rounded-xl border p-3.5 text-left transition"
                                :class="currentEditSlide.layout_type === 'full'
                                    ? 'border-[#087F8C] bg-[#E8F7F6]/50 ring-2 ring-[#087F8C]'
                                    : 'border-[#E5E7EB] bg-[#F8FAF9] hover:border-slate-300'"
                                @click="currentEditSlide.layout_type = 'full'"
                            >
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white border border-[#CBD5E1] text-[#087F8C] font-bold text-sm shadow-sm">
                                    ■
                                </span>
                                <div>
                                    <p class="text-xs font-bold text-[#1F2937]">Full Image Banner (Edge-to-Edge)</p>
                                    <p class="text-[11px] text-[#64748B] mt-0.5">Uploaded promotional graphic covers the entire banner. Clicking it opens your destination link.</p>
                                </div>
                            </button>
                        </div>

                        <!-- If Full Image Banner: Text Overlay Option -->
                        <div v-if="currentEditSlide.layout_type === 'full'" class="mt-4 pt-3.5 border-t border-[#E5E7EB] flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-[#1F2937]">Overlay Headline & Buttons on Image</p>
                                <p class="text-[11px] text-[#64748B]">Enable if you uploaded a background photo and want text over it. Keep disabled if your graphic already contains text/graphics.</p>
                            </div>
                            <label class="relative inline-flex cursor-pointer items-center">
                                <input
                                    v-model="currentEditSlide.overlay_text"
                                    type="checkbox"
                                    class="peer sr-only"
                                />
                                <div class="peer h-5 w-9 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-4 after:w-4 rounded-full bg-white transition-all content-[''] peer-checked:bg-[#087F8C] peer-checked:after:translate-x-full"></div>
                            </label>
                        </div>
                    </div>

                    <!-- Live Storefront Preview for Current Slide -->
                    <div
                        class="overflow-hidden rounded-2xl border border-[#C9D7D5] p-6 text-white shadow-inner sm:p-8 transition-colors duration-500"
                        :class="getThemeGradient(currentEditSlide.theme)"
                    >
                        <div class="mb-4 flex items-center justify-between border-b border-white/10 pb-3">
                            <span class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-[#F4B942]">
                                <span class="h-1.5 w-1.5 rounded-full bg-[#F4B942]"></span>
                                Live Storefront Preview — Slide {{ activeSlideIndex + 1 }}
                                <span class="ml-1 text-white/70">
                                    ({{ currentEditSlide.layout_type === 'full' ? (currentEditSlide.overlay_text ? 'Full Image with Text Overlay' : 'Full Image Graphic') : 'Split Content' }})
                                </span>
                            </span>
                            <span class="text-[10px] text-white/80">
                                {{ currentEditSlide.is_active ? '● Live on Storefront' : '○ Hidden (Inactive)' }}
                            </span>
                        </div>

                        <!-- PREVIEW: 1. Full Image Graphic (No Overlay Text) -->
                        <div
                            v-if="currentEditSlide.layout_type === 'full' && !currentEditSlide.overlay_text"
                            class="relative w-full overflow-hidden rounded-xl bg-slate-900 border border-white/20"
                        >
                            <img
                                :src="getSlideDisplayImage(currentEditSlide)"
                                alt="Full Banner Preview"
                                class="h-[240px] sm:h-[300px] w-full object-cover rounded-xl"
                            />
                            <div class="absolute bottom-3 left-3 rounded-lg bg-black/60 backdrop-blur px-3 py-1.5 text-[11px] font-semibold text-white shadow">
                                🔗 Entire banner clicks through to: <code class="text-[#F4B942]">{{ currentEditSlide.primary_button_url || '/products' }}</code>
                            </div>
                        </div>

                        <!-- PREVIEW: 2. Full Image Banner with Overlay Text -->
                        <div
                            v-else-if="currentEditSlide.layout_type === 'full' && currentEditSlide.overlay_text"
                            class="relative grid min-h-[280px] grid-cols-1 items-center gap-6 overflow-hidden rounded-xl border border-white/20 p-6 sm:p-8"
                        >
                            <img
                                :src="getSlideDisplayImage(currentEditSlide)"
                                alt="Background Preview"
                                class="absolute inset-0 h-full w-full object-cover"
                            />
                            <div class="absolute inset-0 bg-gradient-to-r from-black/85 via-black/55 to-black/20"></div>

                            <div class="relative z-10 flex flex-col justify-center">
                                <div v-if="currentEditSlide.badge_text" class="mb-3 flex items-center gap-2">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-[#F4B942] text-[#087F8C]">
                                        <Icon name="store" class="h-3 w-3" />
                                    </span>
                                    <span class="text-[11px] font-bold uppercase tracking-[0.18em] text-white">
                                        {{ currentEditSlide.badge_text }}
                                    </span>
                                </div>

                                <h3 class="text-2xl font-black leading-tight tracking-tight text-white sm:text-3xl lg:text-4xl drop-shadow">
                                    {{ currentEditSlide.title || 'Welcome to' }}
                                    <span class="text-[#F4B942]">{{ currentEditSlide.title_highlight || 'Alona.' }}</span>
                                </h3>

                                <p v-if="currentEditSlide.description" class="mt-3 text-xs leading-5 text-white/90 sm:text-sm drop-shadow-sm">
                                    {{ currentEditSlide.description }}
                                </p>

                                <div
                                    v-if="(currentEditSlide.show_primary_button && currentEditSlide.primary_button_text) || (currentEditSlide.show_secondary_button && currentEditSlide.secondary_button_text)"
                                    class="mt-5 flex flex-wrap gap-2.5"
                                >
                                    <span
                                        v-if="currentEditSlide.show_primary_button && currentEditSlide.primary_button_text"
                                        class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-xs font-extrabold text-[#087F8C] shadow"
                                    >
                                        {{ currentEditSlide.primary_button_text }}
                                        <span v-if="currentEditSlide.show_primary_button_arrow !== false">→</span>
                                    </span>

                                    <span
                                        v-if="currentEditSlide.show_secondary_button && currentEditSlide.secondary_button_text"
                                        class="inline-flex items-center gap-2 rounded-xl border border-white/50 bg-black/20 px-4 py-2.5 text-xs font-extrabold text-white backdrop-blur"
                                    >
                                        {{ currentEditSlide.secondary_button_text }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- PREVIEW: 3. Split Layout (Default) -->
                        <div
                            v-else
                            class="relative grid min-h-[280px] grid-cols-1 items-center gap-6"
                            :class="currentEditSlide.show_image !== false ? 'lg:grid-cols-[1.1fr_0.9fr]' : 'lg:grid-cols-1'"
                        >
                            <!-- Left: Text & CTA -->
                            <div class="relative z-10 flex flex-col justify-center">
                                <div class="mb-3 flex items-center gap-2">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-[#F4B942] text-[#087F8C]">
                                        <Icon name="store" class="h-3 w-3" />
                                    </span>
                                    <span class="text-[11px] font-bold uppercase tracking-[0.18em] text-teal-50">
                                        {{ currentEditSlide.badge_text || '5 PRODUCTS • 12 CATEGORIES' }}
                                    </span>
                                </div>

                                <h3 class="text-2xl font-black leading-tight tracking-tight text-white sm:text-3xl lg:text-4xl">
                                    {{ currentEditSlide.title || 'Welcome to' }}
                                    <span class="text-[#F4B942]">{{ currentEditSlide.title_highlight || 'Alona.' }}</span>
                                </h3>

                                <p class="mt-3 text-xs leading-5 text-teal-50/90 sm:text-sm">
                                    {{ currentEditSlide.description || 'Everything you love, all in one place. Discover quality products, trusted sellers, and great deals made for you.' }}
                                </p>

                                <div
                                    v-if="(currentEditSlide.show_primary_button && currentEditSlide.primary_button_text) || (currentEditSlide.show_secondary_button && currentEditSlide.secondary_button_text)"
                                    class="mt-5 flex flex-wrap gap-2.5"
                                >
                                    <span
                                        v-if="currentEditSlide.show_primary_button && currentEditSlide.primary_button_text"
                                        class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-xs font-extrabold text-[#087F8C] shadow"
                                    >
                                        {{ currentEditSlide.primary_button_text }}
                                        <span v-if="currentEditSlide.show_primary_button_arrow !== false">→</span>
                                    </span>

                                    <span
                                        v-if="currentEditSlide.show_secondary_button && currentEditSlide.secondary_button_text"
                                        class="inline-flex items-center gap-2 rounded-xl border border-white/50 px-4 py-2.5 text-xs font-extrabold text-white"
                                    >
                                        {{ currentEditSlide.secondary_button_text }}
                                    </span>
                                </div>
                            </div>

                            <!-- Right: Illustration & Floating Stats -->
                            <div
                                v-if="currentEditSlide.show_image !== false"
                                class="relative flex min-h-[220px] items-center justify-center"
                            >
                                <div class="absolute h-[180px] w-[180px] rounded-full bg-white/[0.08] sm:h-[220px] sm:w-[220px]"></div>
                                <img
                                    :src="getSlideDisplayImage(currentEditSlide)"
                                    alt="Hero Illustration"
                                    class="relative z-10 w-[180px] object-contain drop-shadow-lg sm:w-[230px]"
                                />

                                <!-- Floating Card 1 -->
                                <div
                                    v-if="currentEditSlide.show_floating_cards"
                                    class="absolute right-[5%] top-[8%] z-20 hidden rounded-xl border border-white/20 bg-white/95 p-2.5 shadow-lg backdrop-blur sm:block text-slate-800"
                                >
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C]">
                                            <Icon name="package" class="h-3.5 w-3.5" />
                                        </div>
                                        <div>
                                            <p class="text-[8px] font-bold uppercase tracking-wider text-gray-400">
                                                {{ currentEditSlide.card_1_title || 'Products' }}
                                            </p>
                                            <p class="text-[10px] font-extrabold text-gray-900">
                                                5 {{ currentEditSlide.card_1_subtitle || 'available' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Floating Card 2 -->
                                <div
                                    v-if="currentEditSlide.show_floating_cards"
                                    class="absolute bottom-[8%] left-[5%] z-20 hidden rounded-xl border border-white/20 bg-white/95 px-3 py-2 shadow-lg backdrop-blur sm:block text-slate-800"
                                >
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-[#F4B942] text-white">
                                            <Icon name="grid" class="h-3.5 w-3.5" />
                                        </div>
                                        <div>
                                            <p class="text-[8px] font-bold uppercase tracking-wider text-gray-400">
                                                {{ currentEditSlide.card_2_title || 'CATEGORIES' }}
                                            </p>
                                            <p class="text-[10px] font-extrabold text-gray-900">
                                                12 {{ currentEditSlide.card_2_subtitle || 'to explore' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Slide Color Theme Selector (Used for split layout, background, and banner card backdrop) -->
                    <div>
                        <label class="block text-xs font-bold text-[#1F2937]">
                            Banner Theme / Background Accent
                        </label>
                        <p class="text-[11px] text-[#64748B]">
                            Sets the background color gradient and accent styling for this banner.
                        </p>
                        <div class="mt-2.5 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-8">
                            <button
                                v-for="theme in themeOptions"
                                :key="theme.key"
                                type="button"
                                class="flex items-center gap-2 rounded-xl border p-2 text-left text-xs transition"
                                :class="currentEditSlide.theme === theme.key
                                    ? 'border-[#087F8C] bg-[#E8F7F6]/50 font-bold ring-2 ring-[#087F8C]'
                                    : 'border-[#E5E7EB] bg-white text-slate-700 hover:border-slate-300'"
                                @click="currentEditSlide.theme = theme.key"
                            >
                                <span
                                    class="h-4 w-4 shrink-0 rounded-full border border-black/10 shadow-sm"
                                    :style="{ backgroundColor: theme.color }"
                                ></span>
                                <span class="truncate text-[11px]">{{ theme.label }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Input Fields for Current Slide -->
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <!-- BANNER PICTURE / ILLUSTRATION MANAGER (Replace Picture, Custom URL, or Remove Picture) -->
                        <div class="md:col-span-2 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-3">
                                <div>
                                    <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                        <svg class="h-4 w-4 text-[#087F8C]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        {{ currentEditSlide.layout_type === 'full' ? 'Full Banner Image Graphic (Edge-to-Edge)' : 'Banner Picture / Mascot Illustration' }}
                                    </h4>
                                    <p class="text-xs text-slate-500 mt-0.5">
                                        {{ currentEditSlide.layout_type === 'full'
                                            ? 'Upload your wide banner poster or marketing creative (recommended: 1200×400px or 1920×600px).'
                                            : 'Replace the shopping mascot/product picture, upload your own image, or remove the picture entirely.' }}
                                    </p>
                                </div>
                                <div class="shrink-0">
                                    <button
                                        v-if="currentEditSlide.show_image !== false"
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-xl border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-bold text-red-600 transition hover:bg-red-100 shadow-sm"
                                        @click="currentEditSlide.show_image = false"
                                        title="Remove picture from this banner"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Remove Picture
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-xl border border-[#087F8C] bg-[#E8F7F6] px-3.5 py-1.5 text-xs font-bold text-[#087F8C] transition hover:bg-[#d6f1ef] shadow-sm"
                                        @click="currentEditSlide.show_image = true; if (!currentEditSlide.image_path) currentEditSlide.image_path = '/images/Alogo.png'"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                        </svg>
                                        + Add / Show Picture
                                    </button>
                                </div>
                            </div>

                            <!-- Picture Active Form -->
                            <div v-if="currentEditSlide.show_image !== false" class="mt-4">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                                    <div class="relative flex h-24 w-28 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 p-2 shadow-inner">
                                        <img
                                            :src="getSlideDisplayImage(currentEditSlide)"
                                            alt="Banner Picture preview"
                                            class="h-full w-full object-contain"
                                        />
                                        <span class="absolute -top-2 -right-2 rounded-full bg-[#087F8C] px-1.5 py-0.5 text-[9px] font-bold text-white shadow">Current</span>
                                    </div>

                                    <div class="flex-1 space-y-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-[#087F8C] bg-[#E8F7F6] px-4 py-2 text-xs font-bold text-[#087F8C] transition hover:bg-[#d6f1ef] shadow-sm">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                                </svg>
                                                Choose File to Replace Picture
                                                <input
                                                    type="file"
                                                    accept="image/*"
                                                    class="sr-only"
                                                    @change="handleSlideImageUpload($event, currentEditSlide)"
                                                />
                                            </label>

                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 shadow-sm"
                                                @click="currentEditSlide.image_path = '/images/Alogo.png'; delete bannerPreviews[currentEditSlide.id]; delete bannerFiles[currentEditSlide.id]"
                                                title="Reset to default Alona bag graphic"
                                            >
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                                Reset to Default Logo
                                            </button>
                                        </div>

                                        <div>
                                            <label class="block text-[11px] font-bold text-slate-600">
                                                Or enter custom image path / URL:
                                            </label>
                                            <input
                                                v-model="currentEditSlide.image_path"
                                                type="text"
                                                placeholder="/images/Alogo.png or /storage/products/..."
                                                class="mt-1 w-full rounded-xl border border-slate-200 bg-[#F8FAF9] px-3.5 py-2 text-xs text-slate-800 outline-none transition focus:border-[#087F8C] focus:bg-white"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Picture Removed Placeholder -->
                            <div v-else class="mt-4 rounded-xl border-2 border-dashed border-slate-200 bg-slate-50/70 p-6 text-center">
                                <p class="text-xs font-bold text-slate-700">Banner Picture is Removed (Hidden)</p>
                                <p class="text-[11px] text-slate-500 max-w-md mx-auto mt-0.5">
                                    The headline, badge, and description text will expand across the full width of the banner.
                                </p>
                                <button
                                    type="button"
                                    class="mt-3 inline-flex items-center gap-1 rounded-lg border border-teal-300 bg-[#E8F7F6] px-3.5 py-1.5 text-xs font-bold text-[#087F8C] transition hover:bg-[#d6f1ef]"
                                    @click="currentEditSlide.show_image = true; if (!currentEditSlide.image_path) currentEditSlide.image_path = '/images/Alogo.png'"
                                >
                                    + Add / Show Picture
                                </button>
                            </div>
                        </div>

                        <!-- DESTINATION URL (When Full Image Banner without overlay) -->
                        <div v-if="currentEditSlide.layout_type === 'full' && !currentEditSlide.overlay_text" class="md:col-span-2">
                            <label class="block text-xs font-semibold text-[#1F2937]">
                                Banner Click Destination URL
                            </label>
                            <input
                                v-model="currentEditSlide.primary_button_url"
                                type="text"
                                placeholder="e.g. /products or /categories/electronics"
                                class="mt-1.5 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-2.5 text-xs text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white"
                            />
                            <p class="text-[11px] text-[#64748B] mt-1">
                                Clicking anywhere on this full graphic banner will take the user to this link.
                            </p>
                        </div>

                        <!-- Slide Reference Name / Title (If full graphic banner, title is used as reference & alt text) -->
                        <div v-if="currentEditSlide.layout_type === 'full' && !currentEditSlide.overlay_text" class="md:col-span-2">
                            <label class="block text-xs font-semibold text-[#1F2937]">
                                Banner Title / Reference Note (Optional)
                            </label>
                            <input
                                v-model="currentEditSlide.title"
                                type="text"
                                placeholder="e.g. Summer Super Sale Graphic"
                                class="mt-1.5 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-2.5 text-xs text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white"
                            />
                        </div>

                        <!-- TEXT & BUTTON FIELDS (Shown for Split layout OR when Text Overlay is enabled) -->
                        <template v-if="currentEditSlide.layout_type === 'split' || currentEditSlide.overlay_text">
                            <!-- Eyebrow Badge -->
                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-[#1F2937]">
                                    Eyebrow Badge Text (Optional)
                                </label>
                                <p class="text-[11px] text-[#64748B]">
                                    Leave blank to automatically display dynamic counts: "X products • Y categories".
                                </p>
                                <input
                                    v-model="currentEditSlide.badge_text"
                                    type="text"
                                    placeholder="e.g. 5 PRODUCTS • 12 CATEGORIES (or custom promo text)"
                                    class="mt-1.5 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-2.5 text-xs text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white"
                                />
                            </div>

                            <!-- Title Prefix -->
                            <div>
                                <label class="block text-xs font-semibold text-[#1F2937]">
                                    Main Headline Prefix
                                </label>
                                <input
                                    v-model="currentEditSlide.title"
                                    type="text"
                                    placeholder="e.g. Welcome to"
                                    class="mt-1.5 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-2.5 text-xs text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white"
                                />
                            </div>

                            <!-- Highlight Word (Gold Accent) -->
                            <div>
                                <label class="block text-xs font-semibold text-[#1F2937]">
                                    Highlighted Word / Brand Accent (Gold)
                                </label>
                                <input
                                    v-model="currentEditSlide.title_highlight"
                                    type="text"
                                    placeholder="e.g. Alona."
                                    class="mt-1.5 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-2.5 text-xs text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white"
                                />
                            </div>

                            <!-- Description -->
                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-[#1F2937]">
                                    Banner Subtitle / Description
                                </label>
                                <textarea
                                    v-model="currentEditSlide.description"
                                    rows="3"
                                    placeholder="Describe your marketplace value proposition..."
                                    class="mt-1.5 w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-3.5 py-2.5 text-xs text-[#1F2937] outline-none transition focus:border-[#087F8C] focus:bg-white"
                                ></textarea>
                            </div>

                            <!-- ACTION BUTTONS MANAGER (Rename labels, URLs, or Remove Buttons) -->
                            <div class="md:col-span-2 rounded-2xl border border-slate-200 bg-slate-50/70 p-5 shadow-sm">
                                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between border-b border-slate-200 pb-3">
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                                            <svg class="h-4 w-4 text-[#087F8C]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
                                            </svg>
                                            Action Buttons (Call to Action)
                                        </h4>
                                        <p class="text-xs text-slate-500">
                                            Rename button labels, update links, or completely remove buttons from this banner.
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2 text-xs font-semibold">
                                        <span class="text-slate-400">Status:</span>
                                        <span v-if="currentEditSlide.show_primary_button && currentEditSlide.show_secondary_button" class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] text-emerald-700 font-bold border border-emerald-200">2 Buttons Active</span>
                                        <span v-else-if="currentEditSlide.show_primary_button || currentEditSlide.show_secondary_button" class="rounded-full bg-sky-50 px-2.5 py-0.5 text-[11px] text-sky-700 font-bold border border-sky-200">1 Button Active</span>
                                        <span v-else class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] text-slate-600 font-bold border border-slate-300">All Buttons Removed</span>
                                    </div>
                                </div>

                                <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                                    <!-- BUTTON 1: PRIMARY BUTTON -->
                                    <div
                                        class="rounded-xl border transition-all p-4"
                                        :class="currentEditSlide.show_primary_button !== false ? 'border-teal-200 bg-white shadow-sm' : 'border-dashed border-slate-300 bg-slate-100/60'"
                                    >
                                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                            <div class="flex items-center gap-2">
                                                <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-[#E8F7F6] text-[#087F8C] font-black text-xs">1</span>
                                                <span class="text-xs font-bold text-slate-800">Button 1 (Primary)</span>
                                            </div>
                                            <div>
                                                <button
                                                    v-if="currentEditSlide.show_primary_button !== false"
                                                    type="button"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-red-50 px-2.5 py-1 text-[11px] font-bold text-red-600 transition hover:bg-red-100"
                                                    @click="currentEditSlide.show_primary_button = false"
                                                    title="Remove Button 1 from banner"
                                                >
                                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                    Remove Button 1
                                                </button>
                                                <button
                                                    v-else
                                                    type="button"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-teal-300 bg-[#E8F7F6] px-2.5 py-1 text-[11px] font-bold text-[#087F8C] transition hover:bg-[#d6f1ef]"
                                                    @click="currentEditSlide.show_primary_button = true; if (!currentEditSlide.primary_button_text) currentEditSlide.primary_button_text = 'Shop Now'"
                                                >
                                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                                    </svg>
                                                    Enable Button 1
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Active Form for Button 1 -->
                                        <div v-if="currentEditSlide.show_primary_button !== false" class="mt-3 space-y-3">
                                            <div>
                                                <label class="block text-[11px] font-bold text-slate-700">
                                                    Rename Button 1 Label
                                                </label>
                                                <input
                                                    v-model="currentEditSlide.primary_button_text"
                                                    type="text"
                                                    placeholder="e.g. Shop Now, Browse Catalog, Start Shopping"
                                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-[#F8FAF9] px-3 py-2 text-xs text-slate-800 outline-none transition focus:border-[#087F8C] focus:bg-white"
                                                />
                                            </div>

                                            <div>
                                                <label class="block text-[11px] font-bold text-slate-700">
                                                    Destination URL
                                                </label>
                                                <input
                                                    v-model="currentEditSlide.primary_button_url"
                                                    type="text"
                                                    placeholder="e.g. /products or /categories/clothing"
                                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-[#F8FAF9] px-3 py-2 text-xs text-slate-800 outline-none transition focus:border-[#087F8C] focus:bg-white"
                                                />
                                            </div>

                                            <div class="flex items-center gap-2 pt-1">
                                                <label class="flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                                                    <input
                                                        v-model="currentEditSlide.show_primary_button_arrow"
                                                        type="checkbox"
                                                        class="rounded border-slate-300 text-[#087F8C] focus:ring-[#087F8C]"
                                                    />
                                                    <span>Show trailing arrow icon (<span class="font-bold">→</span>)</span>
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Removed State for Button 1 -->
                                        <div v-else class="mt-4 py-4 text-center">
                                            <p class="text-xs font-medium text-slate-500">
                                                Button 1 is currently <span class="font-bold text-red-500">removed</span>.
                                            </p>
                                            <p class="text-[11px] text-slate-400 mt-0.5">
                                                It will not be displayed on the banner.
                                            </p>
                                        </div>
                                    </div>

                                    <!-- BUTTON 2: SECONDARY BUTTON -->
                                    <div
                                        class="rounded-xl border transition-all p-4"
                                        :class="currentEditSlide.show_secondary_button !== false ? 'border-teal-200 bg-white shadow-sm' : 'border-dashed border-slate-300 bg-slate-100/60'"
                                    >
                                        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                                            <div class="flex items-center gap-2">
                                                <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-slate-100 text-slate-700 font-black text-xs">2</span>
                                                <span class="text-xs font-bold text-slate-800">Button 2 (Secondary / Outline)</span>
                                            </div>
                                            <div>
                                                <button
                                                    v-if="currentEditSlide.show_secondary_button !== false"
                                                    type="button"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-red-200 bg-red-50 px-2.5 py-1 text-[11px] font-bold text-red-600 transition hover:bg-red-100"
                                                    @click="currentEditSlide.show_secondary_button = false"
                                                    title="Remove Button 2 from banner"
                                                >
                                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                    Remove Button 2
                                                </button>
                                                <button
                                                    v-else
                                                    type="button"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-teal-300 bg-[#E8F7F6] px-2.5 py-1 text-[11px] font-bold text-[#087F8C] transition hover:bg-[#d6f1ef]"
                                                    @click="currentEditSlide.show_secondary_button = true; if (!currentEditSlide.secondary_button_text) currentEditSlide.secondary_button_text = 'Explore Categories'"
                                                >
                                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                                    </svg>
                                                    Enable Button 2
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Active Form for Button 2 -->
                                        <div v-if="currentEditSlide.show_secondary_button !== false" class="mt-3 space-y-3">
                                            <div>
                                                <label class="block text-[11px] font-bold text-slate-700">
                                                    Rename Button 2 Label
                                                </label>
                                                <input
                                                    v-model="currentEditSlide.secondary_button_text"
                                                    type="text"
                                                    placeholder="e.g. Explore Categories, Discover More"
                                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-[#F8FAF9] px-3 py-2 text-xs text-slate-800 outline-none transition focus:border-[#087F8C] focus:bg-white"
                                                />
                                            </div>

                                            <div>
                                                <label class="block text-[11px] font-bold text-slate-700">
                                                    Destination URL
                                                </label>
                                                <input
                                                    v-model="currentEditSlide.secondary_button_url"
                                                    type="text"
                                                    placeholder="e.g. #categories or /all-deals"
                                                    class="mt-1 w-full rounded-lg border border-slate-200 bg-[#F8FAF9] px-3 py-2 text-xs text-slate-800 outline-none transition focus:border-[#087F8C] focus:bg-white"
                                                />
                                            </div>
                                            <p class="text-[11px] text-slate-400 pt-1">
                                                Tip: Use <code class="font-mono text-[#087F8C]">#categories</code> to smoothly scroll down to the category grid.
                                            </p>
                                        </div>

                                        <!-- Removed State for Button 2 -->
                                        <div v-else class="mt-4 py-4 text-center">
                                            <p class="text-xs font-medium text-slate-500">
                                                Button 2 is currently <span class="font-bold text-red-500">removed</span>.
                                            </p>
                                            <p class="text-[11px] text-slate-400 mt-0.5">
                                                It will not be displayed on the banner.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Floating Stat Cards Customization (Only for split layout) -->
                            <div v-if="(currentEditSlide.layout_type === 'split' || !currentEditSlide.layout_type) && currentEditSlide.show_image !== false" class="md:col-span-2 rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="text-xs font-bold text-[#1F2937]">Floating Stat Cards</h4>
                                        <p class="text-[11px] text-[#64748B]">Show or customize the small floating badges on the illustration.</p>
                                    </div>
                                    <label class="relative inline-flex cursor-pointer items-center">
                                        <input
                                            v-model="currentEditSlide.show_floating_cards"
                                            type="checkbox"
                                            class="peer sr-only"
                                        />
                                        <div class="peer h-5 w-9 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-4 after:w-4 rounded-full bg-white transition-all content-[''] peer-checked:bg-[#087F8C] peer-checked:after:translate-x-full"></div>
                                    </label>
                                </div>

                                <div v-if="currentEditSlide.show_floating_cards" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-[#475569]">Card 1 Suffix (under Products)</label>
                                        <input
                                            v-model="currentEditSlide.card_1_subtitle"
                                            type="text"
                                            placeholder="e.g. available"
                                            class="mt-1 w-full rounded-lg border border-[#E5E7EB] bg-white px-3 py-2 text-xs"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-[#475569]">Card 2 Suffix (under Categories)</label>
                                        <input
                                            v-model="currentEditSlide.card_2_subtitle"
                                            type="text"
                                            placeholder="e.g. to explore"
                                            class="mt-1 w-full rounded-lg border border-[#E5E7EB] bg-white px-3 py-2 text-xs"
                                        />
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Bottom Submit Bar -->
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-t border-[#E5E7EB] pt-4">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="rounded-lg border border-[#E5E7EB] bg-white px-3.5 py-2 text-xs font-semibold text-[#64748B] transition hover:bg-[#F8FAF9]"
                                @click="resetAllBannersToDefault"
                            >
                                Reset All Banners
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-[#087F8C] bg-[#E8F7F6] px-3.5 py-2 text-xs font-bold text-[#087F8C] transition hover:bg-[#d6f1ef]"
                                @click="addSlide"
                            >
                                + Add Another Banner
                            </button>
                        </div>

                        <div class="flex items-center gap-3">
                            <span v-if="bannerSavedRecently" class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 border border-emerald-200 animate-pulse">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                Saved Successfully!
                            </span>

                            <button
                                type="button"
                                :disabled="isSavingBanners"
                                class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-[#087F8C] px-5 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#066E79] disabled:opacity-50"
                                @click="saveAllHeroBanners"
                            >
                            <svg
                                v-if="isSavingBanners"
                                class="h-3.5 w-3.5 animate-spin"
                                viewBox="0 0 24 24"
                                fill="none"
                            >
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25" />
                                <path fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" class="opacity-75" />
                            </svg>
                            {{ isSavingBanners ? 'Saving Banners...' : 'Save All Hero Banners' }}
                        </button>
                    </div>
                </div>
            </div>
        </section>

            <!-- =========================================================
                 "WHY ALONA" MARKETPLACE BANNER SETTINGS
            ========================================================== -->

            <section
                class="mt-5 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
            >
                <!-- Section Header -->
                <div class="border-b border-[#E5E7EB] px-5 py-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="h-1.5 w-1.5 rounded-full bg-[#16A6A0]"></span>
                                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#94A3B8]">
                                    Marketplace Homepage Banner
                                </p>
                            </div>
                            <h2 class="mt-1 text-base font-bold text-[#1F2937]">
                                "Why Alona" Feature Section
                            </h2>
                            <p class="mt-1 text-xs text-[#64748B]">
                                Customize the 3-feature value proposition banner displayed on both Guest and Buyer dashboards.
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="rounded-lg border border-[#E5E7EB] bg-white px-3.5 py-2 text-xs font-semibold text-[#64748B] transition hover:bg-[#F8FAF9]"
                                @click="resetWhyAlona"
                            >
                                Reset to Default
                            </button>
                            <button
                                type="button"
                                :disabled="whyAlonaForm.processing"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-[#087F8C] px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-[#066E79] disabled:opacity-50"
                                @click="submitWhyAlona"
                            >
                                {{ whyAlonaForm.processing ? 'Saving...' : 'Save Banner Changes' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Form Content -->
                <form @submit.prevent="submitWhyAlona" class="p-5 sm:p-6 space-y-6">
                    <!-- Live Preview -->
                    <div class="overflow-hidden rounded-2xl border border-[#C9D7D5] bg-[#087F8C] p-6 text-white shadow-inner sm:p-8">
                        <div class="mb-4 flex items-center justify-between border-b border-white/10 pb-3">
                            <span class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-[#F4B942]">
                                <span class="h-1.5 w-1.5 rounded-full bg-[#F4B942]"></span>
                                Live Storefront Preview
                            </span>
                            <span class="text-[10px] text-teal-100/70">Shown on Guest &amp; Buyer Home</span>
                        </div>

                        <div class="grid items-center gap-6 lg:grid-cols-[1fr_1.8fr] xl:gap-8">
                            <div>
                                <p class="text-[11px] font-black uppercase tracking-[0.22em] text-[#F4B942]">
                                    {{ whyAlonaForm.eyebrow || 'WHY ALONA' }}
                                </p>
                                <h3 class="mt-2 text-2xl font-black leading-tight text-white sm:text-3xl">
                                    {{ whyAlonaForm.title || 'Shopping should feel effortless.' }}
                                </h3>
                                <p class="mt-3 text-xs leading-5 text-teal-50/90">
                                    {{ whyAlonaForm.description || 'Alona brings products and sellers together through a marketplace designed to keep discovering simple.' }}
                                </p>
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <div
                                    v-for="(card, idx) in whyAlonaForm.cards"
                                    :key="idx"
                                    class="flex min-h-[140px] flex-col justify-between rounded-2xl border border-white/10 bg-white/[0.07] p-4 backdrop-blur"
                                >
                                    <span class="text-[11px] font-bold text-[#F4B942]">
                                        {{ card.number || String(idx + 1).padStart(2, '0') }}
                                    </span>
                                    <div class="mt-4">
                                        <h4 class="text-xs font-bold text-white">
                                            {{ card.title || `Card ${idx + 1}` }}
                                        </h4>
                                        <p class="mt-1 text-[11px] leading-4 text-teal-50/80">
                                            {{ card.description || 'Card description here.' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Banner Left Side Inputs -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-bold text-[#334155]">
                                Eyebrow Tagline
                            </label>
                            <input
                                v-model="whyAlonaForm.eyebrow"
                                type="text"
                                placeholder="e.g. WHY ALONA"
                                class="mt-1.5 w-full rounded-xl border border-[#DCE5E3] px-3.5 py-2.5 text-xs text-[#1F2937] placeholder-[#94A3B8] transition focus:border-[#087F8C] focus:outline-none focus:ring-1 focus:ring-[#087F8C]"
                            />
                            <p v-if="whyAlonaForm.errors.eyebrow" class="mt-1 text-[11px] text-red-500">
                                {{ whyAlonaForm.errors.eyebrow }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-[#334155]">
                                Banner Headline / Title
                            </label>
                            <input
                                v-model="whyAlonaForm.title"
                                type="text"
                                placeholder="e.g. Shopping should feel effortless."
                                class="mt-1.5 w-full rounded-xl border border-[#DCE5E3] px-3.5 py-2.5 text-xs text-[#1F2937] placeholder-[#94A3B8] transition focus:border-[#087F8C] focus:outline-none focus:ring-1 focus:ring-[#087F8C]"
                            />
                            <p v-if="whyAlonaForm.errors.title" class="mt-1 text-[11px] text-red-500">
                                {{ whyAlonaForm.errors.title }}
                            </p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-[#334155]">
                                Banner Description
                            </label>
                            <textarea
                                v-model="whyAlonaForm.description"
                                rows="2"
                                placeholder="e.g. Alona brings products and sellers together through a marketplace designed to keep discovering simple."
                                class="mt-1.5 w-full rounded-xl border border-[#DCE5E3] px-3.5 py-2.5 text-xs text-[#1F2937] placeholder-[#94A3B8] transition focus:border-[#087F8C] focus:outline-none focus:ring-1 focus:ring-[#087F8C]"
                            ></textarea>
                            <p v-if="whyAlonaForm.errors.description" class="mt-1 text-[11px] text-red-500">
                                {{ whyAlonaForm.errors.description }}
                            </p>
                        </div>
                    </div>

                    <!-- 3 Feature Cards Inputs -->
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-[#64748B]">
                            Feature Cards (3 Columns)
                        </h3>
                        <p class="mt-0.5 text-[11px] text-[#94A3B8]">
                            Configure the title and copy for each of the 3 cards.
                        </p>

                        <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div
                                v-for="(card, index) in whyAlonaForm.cards"
                                :key="index"
                                class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-4 transition hover:border-[#C9D7D5]"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="rounded-md bg-[#087F8C]/10 px-2 py-0.5 text-[10px] font-black text-[#087F8C]">
                                        Card #{{ card.number || String(index + 1).padStart(2, '0') }}
                                    </span>
                                </div>

                                <div class="mt-3 space-y-3">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-[#475569]">
                                            Card Title
                                        </label>
                                        <input
                                            v-model="card.title"
                                            type="text"
                                            class="mt-1 w-full rounded-lg border border-[#DCE5E3] bg-white px-3 py-2 text-xs text-[#1F2937] placeholder-[#94A3B8] transition focus:border-[#087F8C] focus:outline-none focus:ring-1 focus:ring-[#087F8C]"
                                        />
                                    </div>

                                    <div>
                                        <label class="block text-[11px] font-semibold text-[#475569]">
                                            Card Description
                                        </label>
                                        <textarea
                                            v-model="card.description"
                                            rows="3"
                                            class="mt-1 w-full rounded-lg border border-[#DCE5E3] bg-white px-3 py-2 text-xs text-[#1F2937] placeholder-[#94A3B8] transition focus:border-[#087F8C] focus:outline-none focus:ring-1 focus:ring-[#087F8C]"
                                        ></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Submit -->
                    <div class="flex items-center justify-end gap-3 border-t border-[#E5E7EB] pt-4">
                        <button
                            type="button"
                            class="rounded-lg border border-[#E5E7EB] bg-white px-4 py-2 text-xs font-semibold text-[#64748B] transition hover:bg-[#F8FAF9]"
                            @click="resetWhyAlona"
                        >
                            Reset to Default
                        </button>
                        <button
                            type="submit"
                            :disabled="whyAlonaForm.processing"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-[#087F8C] px-5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-[#066E79] disabled:opacity-50"
                        >
                            {{ whyAlonaForm.processing ? 'Saving Changes...' : 'Save Banner Changes' }}
                        </button>
                    </div>
                </form>
            </section>

            <!-- =========================================================
                 MARKETPLACE FOOTER NAVIGATION SETTINGS
            ========================================================== -->

            <section
                class="mt-5 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
            >
                <!-- Section Header -->
                <div class="border-b border-[#E5E7EB] px-5 py-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="h-1.5 w-1.5 rounded-full bg-[#16A6A0]"></span>
                                <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#94A3B8]">
                                    Storefront Navigation
                                </p>
                            </div>
                            <h2 class="mt-1 text-base font-bold text-[#1F2937]">
                                Marketplace Footer Navigation
                            </h2>
                            <p class="mt-1 text-xs text-[#64748B]">
                                Manage footer columns, links, icons, badges, and brand text displayed across Buyer and Guest pages.
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="rounded-lg border border-[#E5E7EB] bg-white px-3.5 py-2 text-xs font-semibold text-[#64748B] transition hover:bg-[#F8FAF9]"
                                @click="resetFooter"
                            >
                                Reset to Default
                            </button>
                            <button
                                type="button"
                                :disabled="footerForm.processing"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-[#087F8C] px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-[#066E79] disabled:opacity-50"
                                @click="submitFooter"
                            >
                                {{ footerForm.processing ? 'Saving...' : 'Save Footer Changes' }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Form Content -->
                <form @submit.prevent="submitFooter" class="p-5 sm:p-6 space-y-6">
                    <!-- Live Footer Preview (Matching Earlier Clean Storefront Design) -->
                    <div class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-xs transition">
                        <div class="mb-5 flex items-center justify-between border-b border-[#E5E7EB] pb-3">
                            <span class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-[#087F8C]">
                                <span class="h-1.5 w-1.5 rounded-full bg-[#087F8C]"></span>
                                Storefront Footer Preview (Clean Design)
                            </span>
                            <span class="text-[10px] text-[#64748B]">
                                Active across Buyer &amp; Guest layouts
                            </span>
                        </div>

                        <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-5">
                            <!-- Brand preview -->
                            <div class="lg:col-span-1">
                                <img
                                    src="/images/alona.png"
                                    alt="Alona"
                                    class="h-8 w-auto object-contain"
                                />
                                <p class="mt-3 text-xs leading-5 text-[#64748B]">
                                    {{ footerForm.brand.tagline }}
                                </p>
                            </div>

                            <!-- Columns preview -->
                            <div
                                v-for="section in footerForm.sections"
                                :key="section.id || section.title"
                                class="space-y-3"
                            >
                                <h4 class="text-xs font-black uppercase tracking-[0.16em] text-[#1F2937]">
                                    {{ section.title }}
                                </h4>

                                <div class="space-y-2.5">
                                    <div
                                        v-for="(link, lIdx) in section.links"
                                        :key="lIdx"
                                        class="text-xs text-[#64748B] hover:text-[#087F8C] truncate transition"
                                    >
                                        {{ link.label }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer bottom preview -->
                        <div class="mt-8 pt-4 border-t border-[#E5E7EB] flex items-center justify-between text-[11px] text-[#94A3B8]">
                            <p>{{ footerForm.brand.copyright }}</p>
                            <p>{{ footerForm.brand.subtext }}</p>
                        </div>
                    </div>

                    <!-- General Settings: Brand Tagline & Copyright -->
                    <div class="rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-4 space-y-4">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-[#1F2937]">Brand &amp; Copyright Text</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-[#1F2937]">Brand Tagline</label>
                                <input
                                    v-model="footerForm.brand.tagline"
                                    type="text"
                                    class="mt-1 block w-full rounded-lg border border-[#E5E7EB] bg-white px-3 py-2 text-xs text-[#1F2937] focus:border-[#087F8C] focus:outline-none"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-[#1F2937]">Footer Subtext</label>
                                <input
                                    v-model="footerForm.brand.subtext"
                                    type="text"
                                    class="mt-1 block w-full rounded-lg border border-[#E5E7EB] bg-white px-3 py-2 text-xs text-[#1F2937] focus:border-[#087F8C] focus:outline-none"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Section Customization Cards -->
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-[#1F2937]">
                                Footer Columns &amp; Links ({{ footerForm.sections.length }} Columns)
                            </h3>
                            <button
                                type="button"
                                @click="addFooterSection"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-[#E8F7F6] px-3 py-1.5 text-xs font-bold text-[#087F8C] hover:bg-[#087F8C] hover:text-white transition cursor-pointer"
                            >
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="M12 5v14M5 12h14" />
                                </svg>
                                Add Column
                            </button>
                        </div>

                        <div class="space-y-4">
                            <div
                                v-for="(section, sIdx) in footerForm.sections"
                                :key="sIdx"
                                class="rounded-xl border border-[#E5E7EB] bg-white p-4 shadow-2xs space-y-3"
                            >
                                <div class="flex items-center justify-between border-b border-[#F1F5F9] pb-3">
                                    <span class="text-xs font-extrabold text-[#087F8C]">Column {{ sIdx + 1 }}</span>
                                    <button
                                        type="button"
                                        :disabled="footerForm.sections.length <= 1"
                                        @click="removeFooterSection(sIdx)"
                                        class="text-xs font-semibold text-red-500 hover:text-red-700 disabled:opacity-30 cursor-pointer"
                                    >
                                        Delete Column
                                    </button>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-[#64748B]">Column Title</label>
                                    <input
                                        v-model="section.title"
                                        type="text"
                                        placeholder="e.g. Shop"
                                        class="mt-1 block w-full rounded-lg border border-[#E5E7EB] px-2.5 py-1.5 text-xs text-[#1F2937] focus:border-[#087F8C] focus:outline-none"
                                    />
                                </div>

                                <!-- Links table for this section -->
                                <div class="mt-2 pt-2 border-t border-[#F8FAF9]">
                                    <div class="flex items-center justify-between mb-2">
                                        <label class="text-[11px] font-bold text-[#64748B]">Links in this Column</label>
                                        <button
                                            type="button"
                                            @click="addFooterLink(sIdx)"
                                            class="text-[11px] font-bold text-[#087F8C] hover:underline cursor-pointer"
                                        >
                                            + Add Link
                                        </button>
                                    </div>

                                    <div class="space-y-2">
                                        <div
                                            v-for="(link, lIdx) in section.links"
                                            :key="lIdx"
                                            class="flex items-center gap-2"
                                        >
                                            <input
                                                v-model="link.label"
                                                type="text"
                                                placeholder="Link Label (e.g. All Products)"
                                                class="flex-1 rounded-lg border border-[#E5E7EB] px-2.5 py-1.5 text-xs text-[#1F2937] focus:border-[#087F8C] focus:outline-none"
                                            />
                                            <input
                                                v-model="link.url"
                                                type="text"
                                                placeholder="URL (e.g. /products or #chat)"
                                                class="flex-1 rounded-lg border border-[#E5E7EB] px-2.5 py-1.5 text-xs text-[#1F2937] focus:border-[#087F8C] focus:outline-none font-mono"
                                            />
                                            <button
                                                type="button"
                                                @click="removeFooterLink(sIdx, lIdx)"
                                                title="Remove link"
                                                class="text-slate-400 hover:text-red-500 font-bold px-1.5 py-1 cursor-pointer"
                                            >
                                                ✕
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end gap-3 border-t border-[#E5E7EB] pt-5">
                        <button
                            type="button"
                            class="rounded-lg border border-[#E5E7EB] bg-white px-4 py-2 text-xs font-semibold text-[#64748B] transition hover:bg-[#F8FAF9]"
                            @click="resetFooter"
                        >
                            Reset to Default
                        </button>
                        <button
                            type="submit"
                            :disabled="footerForm.processing"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-[#087F8C] px-5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-[#066E79] disabled:opacity-50"
                        >
                            {{ footerForm.processing ? 'Saving Changes...' : 'Save Footer Navigation' }}
                        </button>
                    </div>
                </form>
            </section>

            <!-- =========================================================
                 PLATFORM SETTINGS
            ========================================================== -->

            <section
                class="mt-5 overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
            >
                <!-- Section Header -->

                <div
                    class="border-b border-[#E5E7EB] px-5 py-4"
                >
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span
                                    class="h-1.5 w-1.5 rounded-full bg-[#16A6A0]"
                                ></span>

                                <p
                                    class="text-[10px] font-bold uppercase tracking-[0.14em] text-[#94A3B8]"
                                >
                                    Marketplace configuration
                                </p>
                            </div>

                            <h2
                                class="mt-1 text-base font-bold text-[#1F2937]"
                            >
                                Platform Settings
                            </h2>

                            <p
                                class="mt-1 text-xs text-[#64748B]"
                            >
                                Manage global configuration values used by the marketplace.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-lg bg-[#087F8C] px-3.5 py-2 text-[11px] font-semibold text-white shadow-sm transition hover:bg-[#066E79] focus:outline-none focus:ring-2 focus:ring-[#087F8C]/20"
                            @click="
                                showPlatformSettingForm =
                                    !showPlatformSettingForm
                            "
                        >
                            <svg
                                v-if="!showPlatformSettingForm"
                                class="h-3.5 w-3.5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    d="M12 5v14M5 12h14"
                                />
                            </svg>

                            <svg
                                v-else
                                class="h-3.5 w-3.5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    d="M6 6l12 12M18 6 6 18"
                                />
                            </svg>

                            {{
                                showPlatformSettingForm
                                    ? 'Close Form'
                                    : 'Add Setting'
                            }}
                        </button>
                    </div>
                </div>

                <!-- Add Platform Setting Form -->

                <form
                    v-if="showPlatformSettingForm"
                    class="border-b border-[#E5E7EB] bg-[#F8FAF9] p-4 sm:p-5"
                    @submit.prevent="
                        submitPlatformSetting
                    "
                >
                    <div class="rounded-xl border border-[#E5E7EB] bg-white p-4">
                        <div class="mb-4">
                            <p
                                class="text-xs font-bold text-[#1F2937]"
                            >
                                Add platform setting
                            </p>

                            <p
                                class="mt-1 text-[11px] text-[#94A3B8]"
                            >
                                Create a global configuration value used across the marketplace.
                            </p>
                        </div>

                        <div
                            class="grid gap-4 md:grid-cols-2"
                        >

                            <!-- Key -->

                            <div>
                                <label
                                    for="setting-key"
                                    class="mb-1.5 block text-[11px] font-semibold text-[#475569]"
                                >
                                    Setting Key
                                </label>

                                <input
                                    id="setting-key"
                                    v-model="
                                        platformSettingForm.key
                                    "
                                    type="text"
                                    maxlength="255"
                                    placeholder="e.g. commission_rate"
                                    class="w-full rounded-lg border border-[#E5E7EB] bg-white px-3 py-2.5 text-xs text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10"
                                />

                                <p
                                    class="mt-1 text-[10px] leading-4 text-[#94A3B8]"
                                >
                                    Letters, numbers, dots, hyphens, and underscores only.
                                </p>

                                <p
                                    v-if="
                                        platformSettingForm
                                            .errors.key
                                    "
                                    class="mt-1 text-[10px] font-semibold text-[#E85D5D]"
                                >
                                    {{
                                        platformSettingForm
                                            .errors.key
                                    }}
                                </p>
                            </div>

                            <!-- Value -->

                            <div>
                                <label
                                    for="setting-value"
                                    class="mb-1.5 block text-[11px] font-semibold text-[#475569]"
                                >
                                    Value
                                </label>

                                <input
                                    id="setting-value"
                                    v-model="
                                        platformSettingForm.value
                                    "
                                    type="text"
                                    maxlength="5000"
                                    placeholder="e.g. 10"
                                    class="w-full rounded-lg border border-[#E5E7EB] bg-white px-3 py-2.5 text-xs text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10"
                                />

                                <p
                                    v-if="
                                        platformSettingForm
                                            .errors.value
                                    "
                                    class="mt-1 text-[10px] font-semibold text-[#E85D5D]"
                                >
                                    {{
                                        platformSettingForm
                                            .errors.value
                                    }}
                                </p>
                            </div>

                            <!-- Description -->

                            <div class="md:col-span-2">
                                <label
                                    for="setting-description"
                                    class="mb-1.5 block text-[11px] font-semibold text-[#475569]"
                                >
                                    Description
                                </label>

                                <textarea
                                    id="setting-description"
                                    v-model="
                                        platformSettingForm
                                            .description
                                    "
                                    rows="3"
                                    maxlength="1000"
                                    placeholder="Describe what this setting controls..."
                                    class="w-full resize-y rounded-lg border border-[#E5E7EB] bg-white px-3 py-2.5 text-xs leading-5 text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:ring-2 focus:ring-[#087F8C]/10"
                                ></textarea>

                                <p
                                    v-if="
                                        platformSettingForm
                                            .errors.description
                                    "
                                    class="mt-1 text-[10px] font-semibold text-[#E85D5D]"
                                >
                                    {{
                                        platformSettingForm
                                            .errors.description
                                    }}
                                </p>
                            </div>

                            <!-- Active -->

                            <label
                                class="flex cursor-pointer items-center gap-3 rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] p-3.5 transition hover:border-[#C9D7D5]"
                            >
                                <input
                                    v-model="
                                        platformSettingForm
                                            .is_active
                                    "
                                    type="checkbox"
                                    class="h-3.5 w-3.5 rounded border-slate-300 text-[#087F8C] focus:ring-[#087F8C]"
                                />

                                <span>
                                    <span
                                        class="block text-[11px] font-semibold text-[#475569]"
                                    >
                                        Active setting
                                    </span>

                                    <span
                                        class="mt-0.5 block text-[10px] leading-4 text-[#94A3B8]"
                                    >
                                        Enable this configuration immediately.
                                    </span>
                                </span>
                            </label>
                        </div>

                        <!-- Actions -->

                        <div
                            class="mt-4 flex flex-wrap justify-end gap-2 border-t border-[#E5E7EB] pt-4"
                        >
                            <button
                                type="button"
                                class="rounded-lg border border-[#E5E7EB] bg-white px-3.5 py-2 text-[11px] font-semibold text-[#64748B] transition hover:bg-[#F8FAF9]"
                                @click="
                                    cancelPlatformSetting
                                "
                            >
                                Cancel
                            </button>

                            <button
                                type="submit"
                                :disabled="
                                    platformSettingForm.processing
                                "
                                class="inline-flex items-center gap-1.5 rounded-lg bg-[#087F8C] px-4 py-2 text-[11px] font-semibold text-white shadow-sm transition hover:bg-[#066E79] disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <svg
                                    v-if="
                                        !platformSettingForm.processing
                                    "
                                    class="h-3.5 w-3.5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        d="M12 5v14M5 12h14"
                                    />
                                </svg>

                                <svg
                                    v-else
                                    class="h-3.5 w-3.5 animate-spin"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <circle
                                        cx="12"
                                        cy="12"
                                        r="9"
                                        class="opacity-30"
                                    />

                                    <path
                                        stroke-linecap="round"
                                        d="M21 12a9 9 0 0 1-9 9"
                                    />
                                </svg>

                                {{
                                    platformSettingForm.processing
                                        ? 'Creating...'
                                        : 'Add Setting'
                                }}
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Settings Table -->

                <div
                    v-if="settings.length"
                    class="overflow-x-auto"
                >
                    <table
                        class="w-full min-w-[650px] text-left"
                    >
                        <thead
                            class="border-b border-[#E5E7EB] bg-[#F8FAF9]"
                        >
                            <tr>
                                <th
                                    class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                >
                                    Key
                                </th>

                                <th
                                    class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                >
                                    Value
                                </th>

                                <th
                                    class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                >
                                    Description
                                </th>

                                <th
                                    class="px-5 py-3 text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                >
                                    Status
                                </th>

                                <th
                                    class="px-5 py-3 text-right text-[10px] font-bold uppercase tracking-[0.12em] text-[#94A3B8]"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>

                        <tbody
                            class="divide-y divide-[#E5E7EB]"
                        >
                            <template
                                v-for="item in settings"
                                :key="item.id ?? item.key"
                            >
                                <tr
                                    class="transition hover:bg-[#F8FAF9]"
                                >
                                    <td
                                        class="px-5 py-3.5 text-xs font-semibold text-[#1F2937]"
                                    >
                                        <div
                                            class="inline-flex rounded-lg bg-[#E8F7F6] px-2.5 py-1.5 font-mono text-[10px] font-semibold text-[#087F8C]"
                                        >
                                            {{ item.key }}
                                        </div>
                                    </td>

                                    <td
                                        class="max-w-[220px] px-5 py-3.5 text-[11px] font-semibold text-[#475569]"
                                    >
                                        <span
                                            class="break-words"
                                        >
                                            {{
                                                item.value ?? '—'
                                            }}
                                        </span>
                                    </td>

                                    <td
                                        class="max-w-[360px] px-5 py-3.5 text-[11px] leading-5 text-[#64748B]"
                                    >
                                        {{
                                            item.description ||
                                            '—'
                                        }}
                                    </td>

                                    <td
                                        class="px-5 py-3.5"
                                    >
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-semibold"
                                            :class="
                                                item.is_active
                                                    ? 'bg-[#E8F7F6] text-[#087F8C] ring-1 ring-inset ring-[#B9E7E4]'
                                                    : 'bg-slate-100 text-slate-500 ring-1 ring-inset ring-slate-200'
                                            "
                                        >
                                            <span
                                                class="h-1.5 w-1.5 rounded-full"
                                                :class="
                                                    item.is_active
                                                        ? 'bg-[#22A06B]'
                                                        : 'bg-[#94A3B8]'
                                                "
                                            ></span>

                                            {{
                                                item.is_active
                                                    ? 'Active'
                                                    : 'Inactive'
                                            }}
                                        </span>
                                    </td>

                                    <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                        <button
                                            type="button"
                                            class="rounded-lg border border-[#E5E7EB] bg-white px-2.5 py-1 text-[11px] font-semibold text-[#087F8C] transition hover:bg-[#E8F7F6]"
                                            @click="startEditingSetting(item)"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            class="ml-1.5 rounded-lg border border-red-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-red-600 transition hover:bg-red-50"
                                            @click="deleteSetting(item)"
                                        >
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                                <!-- Inline edit modal/row if editingSettingId === item.id -->
                                <tr v-if="editingSettingId === item.id" class="bg-[#F0FAF9]">
                                    <td colspan="5" class="p-4 border-t border-b border-[#087F8C]/20">
                                        <form @submit.prevent="submitSetting(item)">
                                            <div class="grid gap-3 sm:grid-cols-3">
                                                <div class="sm:col-span-1">
                                                    <label class="block text-[10px] font-bold text-gray-700">Key (read-only)</label>
                                                    <input :value="item.key" disabled class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-100 px-3 py-2 text-xs font-mono text-gray-500" />
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <label class="block text-[10px] font-bold text-gray-700">Value</label>
                                                    <input v-model="getSettingForm(item).value" type="text" class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs" />
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <label class="block text-[10px] font-bold text-gray-700">Description</label>
                                                    <input v-model="getSettingForm(item).description" type="text" class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs" />
                                                </div>
                                                <div class="flex items-center pt-4">
                                                    <label class="flex cursor-pointer items-center gap-2">
                                                        <input v-model="getSettingForm(item).is_active" type="checkbox" class="h-3.5 w-3.5 rounded border-slate-300 text-[#087F8C]" />
                                                        <span class="text-xs font-semibold text-gray-700">Active</span>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="mt-3 flex justify-end gap-2">
                                                <button type="button" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50" @click="cancelEditingSetting">Cancel</button>
                                                <button type="submit" :disabled="getSettingForm(item).processing" class="rounded-lg bg-[#087F8C] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#066E79] disabled:opacity-50">Save Changes</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Empty State -->

                <div
                    v-else
                    class="p-8 text-center"
                >
                    <div
                        class="mx-auto flex h-10 w-10 items-center justify-center rounded-xl bg-[#F8FAF9] text-[#94A3B8] ring-1 ring-[#E5E7EB]"
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
                                d="M12 3v18M3 12h18"
                            />
                        </svg>
                    </div>

                    <p
                        class="mt-2.5 text-xs font-semibold text-[#64748B]"
                    >
                        No platform settings configured.
                    </p>

                    <p
                        class="mt-1 text-[10px] text-[#94A3B8]"
                    >
                        Click Add Setting to create your first marketplace configuration.
                    </p>
                </div>
            </section>

        </div>
    </AdminLayout>
</template>