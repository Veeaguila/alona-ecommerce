<script setup>
import { computed, ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
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

const activeSection = computed(() => {
    return props.active || 'settings'
})
</script>

<template>
    <Head :title="title || 'Settings'" />

    <AdminLayout :active="activeSection">
        <div class="mx-auto max-w-7xl">

            <!-- =========================================================
                 PAGE HEADER
            ========================================================== -->

            <div class="pb-5">
                <div class="flex items-center gap-2">
                    <span
                        class="h-1.5 w-1.5 rounded-full bg-[#087F8C]"
                    ></span>

                    <p
                        class="text-xs font-bold uppercase tracking-[0.14em] text-[#087F8C]"
                    >
                        {{ eyebrow || 'Settings' }}
                    </p>
                </div>

                <h1
                    class="mt-2 text-2xl font-bold tracking-tight text-[#1F2937] sm:text-3xl"
                >
                    {{ title || 'Settings' }}
                </h1>

                <p
                    v-if="description"
                    class="mt-1.5 max-w-3xl text-sm leading-5 text-[#64748B]"
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
                            </tr>
                        </thead>

                        <tbody
                            class="divide-y divide-[#E5E7EB]"
                        >
                            <tr
                                v-for="item in settings"
                                :key="
                                    item.id ?? item.key
                                "
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
                            </tr>
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