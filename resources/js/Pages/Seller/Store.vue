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

const form = useForm({
    store_name: props.store.store_name || '',
    store_description: props.store.store_description || '',
    store_logo: null,
})

const logoPreview = ref(null)

const currentLogo = computed(() => {
    const path =
        props.store.store_logo_path ??
        props.store.store_logo ??
        props.store.logo_path ??
        props.store.logo ??
        null

    if (!path) {
        return null
    }

    if (
        path.startsWith('http://') ||
        path.startsWith('https://') ||
        path.startsWith('/storage/')
    ) {
        return path
    }

    return `/storage/${path.replace(/^\/+/, '')}`
})

const storeInitial = computed(() => {
    return (
        props.store.store_name?.charAt(0)?.toUpperCase() ||
        'A'
    )
})

const handleLogoChange = (event) => {
    const file = event.target.files?.[0] ?? null

    form.store_logo = file

    if (logoPreview.value) {
        URL.revokeObjectURL(logoPreview.value)
        logoPreview.value = null
    }

    if (file) {
        logoPreview.value = URL.createObjectURL(file)
    }
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

                form.store_logo = null
            },
        })
}

const clearSelectedLogo = () => {
    form.store_logo = null

    if (logoPreview.value) {
        URL.revokeObjectURL(logoPreview.value)
        logoPreview.value = null
    }

    const input = document.getElementById('store_logo')

    if (input) {
        input.value = ''
    }
}

const flashStatus = computed(() => {
    return page.props.flash?.status ?? null
})

onBeforeUnmount(() => {
    if (logoPreview.value) {
        URL.revokeObjectURL(logoPreview.value)
    }
})
</script>

<template>
    <Head title="Store Profile" />

    <SellerLayout>
        <div class="min-h-[calc(100vh-68px)] bg-[#F8FAF9] pb-20">
            <div class="mx-auto max-w-5xl px-4 py-5 sm:px-6 lg:px-8">

                <!-- Page Header -->
                <div
                    class="flex flex-col gap-4 border-b border-[#E5E7EB] pb-5 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div>
                        <p
                            class="text-xs font-bold uppercase tracking-[0.16em] text-[#087F8C]"
                        >
                            Seller Center
                        </p>

                        <h1
                            class="mt-1 text-2xl font-bold tracking-tight text-[#1F2937] sm:text-3xl"
                        >
                            Store Profile
                        </h1>

                        <p class="mt-1 text-sm text-[#64748B]">
                            Manage your store name, description, and logo.
                        </p>
                    </div>

                    <Link
                        :href="route('seller.dashboard')"
                        class="inline-flex w-fit items-center gap-2 rounded-xl border border-[#E5E7EB] bg-white px-4 py-2.5 text-xs font-semibold text-[#475569] shadow-sm transition hover:border-[#087F8C] hover:text-[#087F8C]"
                    >
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                d="M15 18l-6-6 6-6"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>

                        Back to Dashboard
                    </Link>
                </div>

                <!-- Success Message -->
                <div
                    v-if="flashStatus"
                    class="mt-5 flex items-start gap-3 rounded-xl border border-[#BBE7D3] bg-[#ECFDF5] px-4 py-3"
                >
                    <div
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white text-[#22A06B]"
                    >
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                d="M5 12l4 4L19 6"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </div>

                    <div>
                        <p class="text-xs font-bold text-[#16845A]">
                            Store updated successfully
                        </p>

                        <p class="mt-0.5 text-[11px] text-[#16845A]">
                            {{ flashStatus }}
                        </p>
                    </div>
                </div>

                <form
                    class="mt-5 space-y-5"
                    @submit.prevent="submit"
                >
                    <!-- Store Details -->
                    <section
                        class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-sm"
                    >
                        <!-- Section Header -->
                        <div
                            class="border-b border-[#E5E7EB] px-5 py-4"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#E8F7F6] text-[#087F8C]"
                                >
                                    <svg
                                        class="h-5 w-5"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.7"
                                    >
                                        <path
                                            d="M3 10l2-5h14l2 5"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                        <path
                                            d="M5 10v9h14v-9"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                        <path
                                            d="M3 10c0 1.7 1.3 3 3 3s3-1.3 3-3c0 1.7 1.3 3 3 3s3-1.3 3-3c0 1.7 1.3 3 3 3s3-1.3 3-3"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                </div>

                                <div>
                                    <h2
                                        class="text-base font-bold text-[#1F2937]"
                                    >
                                        Basic Store Details
                                    </h2>

                                    <p
                                        class="mt-0.5 text-xs text-[#64748B]"
                                    >
                                        Keep your storefront information accurate and recognizable.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Section Body -->
                        <div class="space-y-5 px-5 py-5">

                            <!-- Store Logo -->
                            <div>
                                <label
                                    for="store_logo"
                                    class="block text-xs font-semibold text-[#1F2937]"
                                >
                                    Store Logo
                                </label>

                                <div
                                    class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-center"
                                >
                                    <!-- Logo Preview -->
                                    <div
                                        class="flex h-28 w-28 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-[#E5E7EB] bg-[#F8FAF9]"
                                    >
                                        <!-- Newly Selected Logo -->
                                        <img
                                            v-if="logoPreview"
                                            :src="logoPreview"
                                            alt="New store logo preview"
                                            class="h-full w-full object-cover"
                                        />

                                        <!-- Saved Logo -->
                                        <img
                                            v-else-if="currentLogo"
                                            :src="currentLogo"
                                            alt="Current store logo"
                                            class="h-full w-full object-cover"
                                            @error="$event.target.style.display = 'none'"
                                        />

                                        <!-- No Logo -->
                                        <div
                                            v-else
                                            class="flex flex-col items-center justify-center text-center"
                                        >
                                            <div
                                                class="flex h-12 w-12 items-center justify-center rounded-full bg-[#E8F7F6] text-[#087F8C]"
                                            >
                                                <span
                                                    class="text-lg font-bold"
                                                >
                                                    {{ storeInitial }}
                                                </span>
                                            </div>

                                            <span
                                                class="mt-2 text-[10px] font-medium text-[#94A3B8]"
                                            >
                                                No logo
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Upload Controls -->
                                    <div class="min-w-0 flex-1">
                                        <input
                                            id="store_logo"
                                            type="file"
                                            accept="image/png,image/jpeg,image/jpg,image/webp"
                                            @change="handleLogoChange"
                                            class="block w-full rounded-xl border border-[#E5E7EB] bg-white text-xs text-[#64748B]
                                                file:mr-3 file:border-0 file:bg-[#E8F7F6]
                                                file:px-4 file:py-2.5
                                                file:text-xs file:font-semibold
                                                file:text-[#087F8C]
                                                hover:file:bg-[#DDF3F1]
                                                focus:border-[#087F8C]
                                                focus:outline-none"
                                        />

                                        <p
                                            class="mt-2 text-[11px] text-[#94A3B8]"
                                        >
                                            JPG, PNG, or WebP image recommended.
                                        </p>

                                        <!-- New logo selected -->
                                        <div
                                            v-if="logoPreview"
                                            class="mt-2 flex items-center gap-2"
                                        >
                                            <span
                                                class="inline-flex items-center gap-1.5 rounded-full border border-[#BAE6FD] bg-[#EFF6FF] px-2.5 py-1 text-[10px] font-semibold text-[#0369A1]"
                                            >
                                                <svg
                                                    class="h-3 w-3"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                >
                                                    <path
                                                        d="M12 5v14M5 12h14"
                                                        stroke-linecap="round"
                                                    />
                                                </svg>

                                                New logo selected
                                            </span>

                                            <button
                                                type="button"
                                                class="text-[10px] font-semibold text-[#E85D5D] hover:underline"
                                                @click="clearSelectedLogo"
                                            >
                                                Remove
                                            </button>
                                        </div>

                                        <!-- Existing logo -->
                                        <p
                                            v-else-if="currentLogo"
                                            class="mt-2 flex items-center gap-1.5 text-[11px] font-semibold text-[#22A06B]"
                                        >
                                            <svg
                                                class="h-3.5 w-3.5"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                            >
                                                <path
                                                    d="M5 12l4 4L19 6"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                />
                                            </svg>

                                            Current store logo is uploaded.
                                        </p>
                                    </div>
                                </div>

                                <p
                                    v-if="form.errors.store_logo"
                                    class="mt-1.5 text-xs text-[#E85D5D]"
                                >
                                    {{ form.errors.store_logo }}
                                </p>
                            </div>

                            <!-- Store Name -->
                            <div>
                                <label
                                    for="store_name"
                                    class="block text-xs font-semibold text-[#1F2937]"
                                >
                                    Store Name
                                </label>

                                <input
                                    id="store_name"
                                    v-model="form.store_name"
                                    type="text"
                                    maxlength="255"
                                    placeholder="Enter your store name"
                                    class="mt-2 block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                />

                                <p
                                    v-if="form.errors.store_name"
                                    class="mt-1.5 text-xs text-[#E85D5D]"
                                >
                                    {{ form.errors.store_name }}
                                </p>
                            </div>

                            <!-- Store Description -->
                            <div>
                                <label
                                    for="store_description"
                                    class="block text-xs font-semibold text-[#1F2937]"
                                >
                                    Store Description
                                </label>

                                <textarea
                                    id="store_description"
                                    v-model="form.store_description"
                                    rows="5"
                                    maxlength="2000"
                                    placeholder="Tell customers about your store..."
                                    class="mt-2 block w-full resize-none rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-sm text-[#1F2937] outline-none transition placeholder:text-[#94A3B8] focus:border-[#087F8C] focus:bg-white focus:ring-2 focus:ring-[#E8F7F6]"
                                ></textarea>

                                <div class="mt-1.5 flex items-center justify-between">
                                    <p
                                        v-if="form.errors.store_description"
                                        class="text-xs text-[#E85D5D]"
                                    >
                                        {{ form.errors.store_description }}
                                    </p>

                                    <span
                                        v-else
                                        class="text-[10px] text-[#94A3B8]"
                                    >
                                        {{ form.store_description.length }}/2000
                                    </span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Save Bar -->
                    <div
                        class="flex flex-col gap-3 rounded-2xl border border-[#E5E7EB] bg-white px-5 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p
                                class="text-xs font-semibold text-[#1F2937]"
                            >
                                Store Profile
                            </p>

                            <p
                                class="mt-0.5 text-[11px] text-[#94A3B8]"
                            >
                                Save your changes to update your storefront.
                            </p>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#087F8C] px-5 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#066D77] focus:outline-none focus:ring-2 focus:ring-[#E8F7F6] disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <svg
                                v-if="form.processing"
                                class="h-4 w-4 animate-spin"
                                viewBox="0 0 24 24"
                                fill="none"
                            >
                                <circle
                                    cx="12"
                                    cy="12"
                                    r="9"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    class="opacity-30"
                                />

                                <path
                                    d="M21 12a9 9 0 0 0-9-9"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                />
                            </svg>

                            <svg
                                v-else
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    d="M5 12h14"
                                    stroke-linecap="round"
                                />

                                <path
                                    d="M13 6l6 6-6 6"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>

                            {{ form.processing ? 'Saving...' : 'Save Changes' }}
                        </button>
                    </div>

                    <!-- General Error -->
                    <div
                        v-if="form.errors && Object.keys(form.errors).length"
                        class="rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3"
                    >
                        <p
                            class="text-xs font-semibold text-[#DC2626]"
                        >
                            Please check the highlighted fields and try again.
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </SellerLayout>
</template>