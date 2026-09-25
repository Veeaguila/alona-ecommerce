<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import InputError from '@/Components/InputError.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const props = defineProps({
    mustVerifyEmail: {
        type: Boolean,
        default: false,
    },

    status: {
        type: String,
        default: '',
    },
})

const page = usePage()

const user = computed(() => page.props.auth?.user || {})

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

const buildFullName = (
    firstName = '',
    middleInitial = '',
    lastName = '',
) => {
    return [
        String(firstName || '').trim(),
        middleInitial
            ? `${String(middleInitial).trim().replace(/\.$/, '')}.`
            : '',
        String(lastName || '').trim(),
    ]
        .filter(Boolean)
        .join(' ')
        .trim()
}

/*
|--------------------------------------------------------------------------
| Profile photo
|--------------------------------------------------------------------------
*/

const profilePhoto = ref(null)
const profilePhotoPreview = ref('')

const savedProfilePhoto = computed(() => {
    const value =
        user.value.profile_photo_url ||
        user.value.profile_photo ||
        user.value.avatar_url ||
        user.value.avatar ||
        ''

    if (!value) {
        return ''
    }

    if (
        String(value).startsWith('http://') ||
        String(value).startsWith('https://') ||
        String(value).startsWith('data:')
    ) {
        return String(value)
    }

    return String(value).startsWith('/')
        ? String(value)
        : `/storage/${String(value).replace(/^storage\//, '')}`
})

const displayProfilePhoto = computed(() => {
    return profilePhotoPreview.value || savedProfilePhoto.value
})

const initials = computed(() => {
    const first = String(user.value.first_name || '')
        .trim()
        .charAt(0)

    const last = String(user.value.last_name || '')
        .trim()
        .charAt(0)

    return `${first}${last}`.toUpperCase() || 'A'
})

const handleProfilePhoto = event => {
    const file = event.target.files?.[0] || null

    profilePhoto.value = file
    form.profile_photo = file

    if (profilePhotoPreview.value) {
        URL.revokeObjectURL(profilePhotoPreview.value)
        profilePhotoPreview.value = ''
    }

    if (!file) {
        return
    }

    if (!file.type.startsWith('image/')) {
        profilePhoto.value = null
        form.profile_photo = null
        return
    }

    profilePhotoPreview.value = URL.createObjectURL(file)
}

/*
|--------------------------------------------------------------------------
| Form
|--------------------------------------------------------------------------
|
| IMPORTANT:
| The backend requires `name`.
| We keep `name` in the form and automatically build it from:
|
| first_name + middle_initial + last_name
|
|--------------------------------------------------------------------------
*/

const form = useForm({
    name:
        user.value.name ||
        buildFullName(
            user.value.first_name,
            user.value.middle_initial,
            user.value.last_name,
        ),

    last_name: user.value.last_name || '',
    first_name: user.value.first_name || '',
    middle_initial: user.value.middle_initial || '',

    sex: user.value.sex || '',

    email: user.value.email || '',
    contact_no: user.value.contact_no || '',

    birthday: user.value.birthday
        ? String(user.value.birthday).substring(0, 10)
        : '',

    age: user.value.age || '',

    province: user.value.province || '',
    municipality: user.value.municipality || '',
    barangay: user.value.barangay || '',
    street_address: user.value.street_address || '',

    profile_photo: null,

    id: null,
})

/*
|--------------------------------------------------------------------------
| Automatically keep name updated
|--------------------------------------------------------------------------
*/

const generatedName = computed(() => {
    return buildFullName(
        form.first_name,
        form.middle_initial,
        form.last_name,
    )
})

watch(
    generatedName,
    value => {
        form.name = value
    },
    {
        immediate: true,
    },
)

/*
|--------------------------------------------------------------------------
| PSGC location data
|--------------------------------------------------------------------------
*/

const provinces = ref([])
const municipalities = ref([])
const barangays = ref([])

const provinceCode = ref('')
const municipalityCode = ref('')

const loadingProvinces = ref(true)
const loadingMunicipalities = ref(false)
const loadingBarangays = ref(false)

/*
|--------------------------------------------------------------------------
| Age
|--------------------------------------------------------------------------
*/

const age = computed(() => {
    if (!form.birthday) {
        return ''
    }

    const birthDate = new Date(`${form.birthday}T00:00:00`)

    if (Number.isNaN(birthDate.getTime())) {
        return ''
    }

    const today = new Date()

    let calculatedAge =
        today.getFullYear() -
        birthDate.getFullYear()

    const monthDifference =
        today.getMonth() -
        birthDate.getMonth()

    if (
        monthDifference < 0 ||
        (
            monthDifference === 0 &&
            today.getDate() < birthDate.getDate()
        )
    ) {
        calculatedAge--
    }

    return calculatedAge >= 0
        ? calculatedAge
        : ''
})

/*
|--------------------------------------------------------------------------
| PSGC loader
|--------------------------------------------------------------------------
*/

const load = async url => {
    const response = await fetch(url)

    if (!response.ok) {
        throw new Error(`Failed to load ${url}`)
    }

    return response.json()
}

/*
|--------------------------------------------------------------------------
| Initial location loading
|--------------------------------------------------------------------------
*/

onMounted(async () => {
    try {
        loadingProvinces.value = true

        provinces.value = await load(
            'https://psgc.gitlab.io/api/provinces/',
        )

        const savedProvince = provinces.value.find(
            item => item.name === form.province,
        )

        if (savedProvince) {
            provinceCode.value = savedProvince.code
        }
    } catch (error) {
        console.error(
            'Failed to load provinces:',
            error,
        )
    } finally {
        loadingProvinces.value = false
    }
})

/*
|--------------------------------------------------------------------------
| Province watcher
|--------------------------------------------------------------------------
*/

watch(
    provinceCode,
    async code => {
        const province = provinces.value.find(
            item => item.code === code,
        )

        if (province) {
            form.province = province.name
        }

        municipalities.value = []
        barangays.value = []
        municipalityCode.value = ''

        loadingMunicipalities.value = false
        loadingBarangays.value = false

        if (!code) {
            form.municipality = ''
            form.barangay = ''
            return
        }

        try {
            loadingMunicipalities.value = true

            municipalities.value = await load(
                `https://psgc.gitlab.io/api/provinces/${code}/municipalities/`,
            )

            const savedMunicipality =
                municipalities.value.find(
                    item => item.name === form.municipality,
                )

            if (savedMunicipality) {
                municipalityCode.value =
                    savedMunicipality.code
            }
        } catch (error) {
            console.error(
                'Failed to load municipalities:',
                error,
            )
        } finally {
            loadingMunicipalities.value = false
        }
    },
)

/*
|--------------------------------------------------------------------------
| Municipality watcher
|--------------------------------------------------------------------------
*/

watch(
    municipalityCode,
    async code => {
        const municipality =
            municipalities.value.find(
                item => item.code === code,
            )

        if (municipality) {
            form.municipality = municipality.name
        }

        barangays.value = []
        loadingBarangays.value = false

        if (!code) {
            form.barangay = ''
            return
        }

        try {
            loadingBarangays.value = true

            barangays.value = await load(
                `https://psgc.gitlab.io/api/municipalities/${code}/barangays/`,
            )
        } catch (error) {
            console.error(
                'Failed to load barangays:',
                error,
            )
        } finally {
            loadingBarangays.value = false
        }
    },
)

/*
|--------------------------------------------------------------------------
| Birthday → age
|--------------------------------------------------------------------------
*/

watch(
    () => form.birthday,
    () => {
        form.age = age.value
    },
)

/*
|--------------------------------------------------------------------------
| Submit
|--------------------------------------------------------------------------
*/

const submit = () => {
    /*
     * Always rebuild the required backend `name`
     * immediately before submitting.
     */
    const fullName = buildFullName(
        form.first_name,
        form.middle_initial,
        form.last_name,
    )

    form.name = fullName

    form
        .transform(data => ({
            ...data,

            /*
             * REQUIRED BY BACKEND
             */
            name: fullName,

            /*
             * Automatically calculated
             */
            age: age.value,
        }))
        .patch(
            route('buyer.account.update'),
            {
                forceFormData: true,
                preserveScroll: true,

                onSuccess: () => {
                    form.id = null
                    form.profile_photo = null

                    profilePhoto.value = null

                    if (profilePhotoPreview.value) {
                        URL.revokeObjectURL(
                            profilePhotoPreview.value,
                        )

                        profilePhotoPreview.value = ''
                    }
                },

                onError: errors => {
                    console.error(
                        'Account update validation errors:',
                        errors,
                    )
                },
            },
        )
}

/*
|--------------------------------------------------------------------------
| Cleanup preview URL
|--------------------------------------------------------------------------
*/

onBeforeUnmount(() => {
    if (profilePhotoPreview.value) {
        URL.revokeObjectURL(
            profilePhotoPreview.value,
        )
    }
})
</script>

<template>
    <section class="min-w-0">

        <!-- PROFILE HEADER -->
        <div
            class="rounded-2xl border border-[#DDE8E8] bg-white p-4 shadow-[0_8px_30px_rgba(15,23,42,0.05)] sm:p-5"
        >
            <div
                class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between"
            >

                <div
                    class="flex min-w-0 items-center gap-4"
                >

                    <!-- PROFILE PHOTO -->
                    <label
                        class="group relative block h-20 w-20 shrink-0 cursor-pointer sm:h-24 sm:w-24"
                    >
                        <div
                            class="h-full w-full overflow-hidden rounded-2xl bg-[#E8F7F6] ring-4 ring-white shadow-md"
                        >
                            <img
                                v-if="displayProfilePhoto"
                                :src="displayProfilePhoto"
                                alt="Profile photo"
                                class="h-full w-full object-cover"
                            />

                            <div
                                v-else
                                class="flex h-full w-full items-center justify-center bg-[#E8F7F6] text-2xl font-extrabold text-[#087F8C] sm:text-3xl"
                            >
                                {{ initials }}
                            </div>
                        </div>

                        <div
                            class="absolute inset-0 flex items-center justify-center rounded-2xl bg-black/45 opacity-0 transition group-hover:opacity-100"
                        >
                            <div class="text-center text-white">
                                <svg
                                    class="mx-auto h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M4 7h3l1.5-2h7L17 7h3v12H4V7zm8 3.25a3.25 3.25 0 100 6.5 3.25 3.25 0 000-6.5z"
                                    />
                                </svg>

                                <span
                                    class="mt-1 block text-[9px] font-bold"
                                >
                                    Change photo
                                </span>
                            </div>
                        </div>

                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="sr-only"
                            @change="handleProfilePhoto"
                        />

                        <span
                            class="absolute -bottom-1 -right-1 flex h-7 w-7 items-center justify-center rounded-full border-2 border-white bg-[#F4B942] text-[#1F2937] shadow-sm"
                        >
                            <svg
                                class="h-3.5 w-3.5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 5v14M5 12h14"
                                />
                            </svg>
                        </span>
                    </label>

                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span
                                class="h-1.5 w-1.5 rounded-full bg-[#F4B942]"
                            ></span>

                            <p
                                class="text-[9px] font-extrabold uppercase tracking-[0.18em] text-[#087F8C] sm:text-[10px]"
                            >
                                Alona profile
                            </p>
                        </div>

                        <h2
                            class="mt-1 truncate text-lg font-extrabold text-[#1F2937] sm:text-xl"
                        >
                            {{ user.first_name || 'Buyer' }}
                            {{ user.last_name || '' }}
                        </h2>

                        <p
                            class="mt-0.5 truncate text-xs text-[#64748B]"
                        >
                            {{ user.email || 'Update your account information' }}
                        </p>

                        <label
                            class="mt-2 inline-flex cursor-pointer items-center gap-1.5 text-[10px] font-bold text-[#087F8C] hover:text-[#066B76]"
                        >
                            <span>Upload a profile photo</span>

                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="hidden"
                                @change="handleProfilePhoto"
                            />
                        </label>
                    </div>
                </div>

                <div
                    class="rounded-xl bg-[#F8FAF9] px-3 py-2.5 text-left sm:max-w-xs sm:text-right"
                >
                    <p
                        class="text-[9px] font-extrabold uppercase tracking-[0.12em] text-[#94A3B8]"
                    >
                        Profile tip
                    </p>

                    <p
                        class="mt-1 text-[10px] leading-4 text-[#64748B]"
                    >
                        Use a clear square photo so sellers can easily recognize your account.
                    </p>
                </div>
            </div>

            <InputError
                :message="form.errors.profile_photo"
                class="mt-3"
            />
        </div>

        <!-- INFORMATION HEADER -->
        <div class="mt-5">
            <div class="flex items-center gap-2">
                <span
                    class="h-2 w-2 rounded-full bg-[#F4B942]"
                ></span>

                <p
                    class="text-[10px] font-extrabold uppercase tracking-[0.18em] text-[#087F8C]"
                >
                    Account details
                </p>
            </div>

            <h3
                class="mt-1 text-xl font-extrabold tracking-tight text-[#1F2937] sm:text-2xl"
            >
                Personal information
            </h3>

            <p
                class="mt-1 text-xs text-[#64748B] sm:text-sm"
            >
                Update the same information used during registration.
            </p>
        </div>

        <!-- FORM -->
        <form
            class="mt-4 grid gap-4 sm:grid-cols-2"
            @submit.prevent="submit"
        >

            <!-- PERSONAL INFORMATION -->
            <div
                class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-[0_5px_20px_rgba(15,23,42,0.035)] sm:p-5"
            >
                <div class="mb-4">
                    <h4
                        class="text-sm font-extrabold text-[#1F2937]"
                    >
                        Basic details
                    </h4>

                    <p
                        class="mt-0.5 text-[10px] text-[#94A3B8]"
                    >
                        Keep your personal information up to date.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">

                    <!-- Last name -->
                    <label>
                        <span class="label">
                            Last name *
                        </span>

                        <input
                            v-model="form.last_name"
                            required
                            class="field"
                        />

                        <InputError
                            :message="form.errors.last_name"
                        />
                    </label>

                    <!-- First name -->
                    <label>
                        <span class="label">
                            First name *
                        </span>

                        <input
                            v-model="form.first_name"
                            required
                            class="field"
                        />

                        <InputError
                            :message="form.errors.first_name"
                        />
                    </label>

                    <!-- Middle initial -->
                    <label>
                        <span class="label">
                            Middle initial
                        </span>

                        <input
                            v-model="form.middle_initial"
                            maxlength="1"
                            class="field"
                        />

                        <InputError
                            :message="form.errors.middle_initial"
                        />
                    </label>

                    <!-- Sex -->
                    <label>
                        <span class="label">
                            Sex *
                        </span>

                        <select
                            v-model="form.sex"
                            required
                            class="field"
                        >
                            <option value="">
                                Select
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
                            :message="form.errors.sex"
                        />
                    </label>

                    <!-- Birthday -->
                    <label>
                        <span class="label">
                            Birthday *
                        </span>

                        <input
                            v-model="form.birthday"
                            type="date"
                            required
                            class="field"
                        />

                        <InputError
                            :message="form.errors.birthday"
                        />
                    </label>

                    <!-- Age -->
                    <label>
                        <span class="label">
                            Age (auto)
                        </span>

                        <input
                            :value="age"
                            readonly
                            class="field field-readonly"
                        />

                        <InputError
                            :message="form.errors.age"
                        />
                    </label>

                </div>
            </div>

            <!-- CONTACT -->
            <div
                class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-[0_5px_20px_rgba(15,23,42,0.035)] sm:p-5"
            >
                <div class="mb-4">
                    <h4
                        class="text-sm font-extrabold text-[#1F2937]"
                    >
                        Contact information
                    </h4>

                    <p
                        class="mt-0.5 text-[10px] text-[#94A3B8]"
                    >
                        Make sure we can reach you about your orders.
                    </p>
                </div>

                <div class="grid gap-3">

                    <!-- Email -->
                    <label>
                        <span class="label">
                            E-mail *
                        </span>

                        <input
                            v-model="form.email"
                            type="email"
                            required
                            class="field"
                        />

                        <InputError
                            :message="form.errors.email"
                        />
                    </label>

                    <!-- Contact -->
                    <label>
                        <span class="label">
                            Contact No. *
                        </span>

                        <input
                            v-model="form.contact_no"
                            type="tel"
                            required
                            class="field"
                        />

                        <InputError
                            :message="form.errors.contact_no"
                        />
                    </label>

                    <!-- EMAIL VERIFICATION -->
                    <div
                        v-if="
                            props.mustVerifyEmail &&
                            user.email_verified_at === null
                        "
                        class="rounded-xl border border-[#F4B942]/30 bg-[#FFF7E6] px-3 py-2.5"
                    >
                        <p
                            class="text-[10px] font-bold text-[#8A6200]"
                        >
                            Your email is unverified.
                        </p>

                        <Link
                            :href="route('verification.send')"
                            method="post"
                            as="button"
                            class="mt-1 text-[10px] font-extrabold text-[#087F8C] underline"
                        >
                            Resend verification email
                        </Link>
                    </div>

                </div>
            </div>

            <!-- ADDRESS -->
            <div
                class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-[0_5px_20px_rgba(15,23,42,0.035)] sm:col-span-2 sm:p-5"
            >
                <div
                    class="mb-4 flex items-start justify-between gap-3"
                >
                    <div>
                        <h4
                            class="text-sm font-extrabold text-[#1F2937]"
                        >
                            Delivery address
                        </h4>

                        <p
                            class="mt-0.5 text-[10px] text-[#94A3B8]"
                        >
                            Choose your location to keep delivery details accurate.
                        </p>
                    </div>

                    <span
                        class="hidden rounded-full bg-[#E8F7F6] px-2.5 py-1 text-[9px] font-extrabold text-[#087F8C] sm:inline-flex"
                    >
                        PSGC assisted
                    </span>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">

                    <!-- Province -->
                    <label>
                        <span class="label">
                            Province *
                        </span>

                        <select
                            v-model="provinceCode"
                            required
                            class="field"
                            :disabled="loadingProvinces"
                        >
                            <option value="">
                                {{
                                    loadingProvinces
                                        ? 'Loading...'
                                        : 'Select province'
                                }}
                            </option>

                            <option
                                v-for="item in provinces"
                                :key="item.code"
                                :value="item.code"
                            >
                                {{ item.name }}
                            </option>
                        </select>

                        <InputError
                            :message="form.errors.province"
                        />
                    </label>

                    <!-- Municipality -->
                    <label>
                        <span class="label">
                            Municipality *
                        </span>

                        <select
                            v-model="municipalityCode"
                            required
                            class="field"
                            :disabled="
                                !provinceCode ||
                                loadingMunicipalities
                            "
                        >
                            <option value="">
                                {{
                                    loadingMunicipalities
                                        ? 'Loading...'
                                        : 'Select municipality'
                                }}
                            </option>

                            <option
                                v-for="item in municipalities"
                                :key="item.code"
                                :value="item.code"
                            >
                                {{ item.name }}
                            </option>
                        </select>

                        <InputError
                            :message="form.errors.municipality"
                        />
                    </label>

                    <!-- Barangay -->
                    <label>
                        <span class="label">
                            Barangay *
                        </span>

                        <select
                            v-model="form.barangay"
                            required
                            class="field"
                            :disabled="
                                !municipalityCode ||
                                loadingBarangays
                            "
                        >
                            <option value="">
                                {{
                                    loadingBarangays
                                        ? 'Loading...'
                                        : 'Select barangay'
                                }}
                            </option>

                            <option
                                v-for="item in barangays"
                                :key="item.code"
                                :value="item.name"
                            >
                                {{ item.name }}
                            </option>
                        </select>

                        <InputError
                            :message="form.errors.barangay"
                        />
                    </label>

                    <!-- Street -->
                    <label class="sm:col-span-3">
                        <span class="label">
                            Street, house number, and other details *
                        </span>

                        <textarea
                            v-model="form.street_address"
                            required
                            rows="2"
                            class="field resize-none"
                            placeholder="House number, street, subdivision, etc."
                        ></textarea>

                        <InputError
                            :message="form.errors.street_address"
                        />
                    </label>

                </div>
            </div>

            <!-- VERIFICATION / ID -->
            <div
                class="rounded-2xl border border-[#E5E7EB] bg-white p-4 shadow-[0_5px_20px_rgba(15,23,42,0.035)] sm:p-5"
            >
                <div class="mb-4">
                    <h4
                        class="text-sm font-extrabold text-[#1F2937]"
                    >
                        Verification
                    </h4>

                    <p
                        class="mt-0.5 text-[10px] text-[#94A3B8]"
                    >
                        Replace your uploaded identification file if needed.
                    </p>
                </div>

                <label>
                    <span class="label">
                        Replace uploaded ID
                    </span>

                    <input
                        type="file"
                        accept=".jpg,.jpeg,.png,.pdf"
                        class="field file-field"
                        @change="
                            form.id =
                                $event.target.files?.[0] ||
                                null
                        "
                    />

                    <InputError
                        :message="form.errors.id"
                    />
                </label>
            </div>

            <!-- ACTIONS -->
            <div
                class="flex flex-col justify-center rounded-2xl border border-[#CDE9E4] bg-[#E8F7F6] p-4 sm:p-5"
            >
                <p
                    class="text-[10px] font-extrabold uppercase tracking-[0.12em] text-[#087F8C]"
                >
                    Ready to save?
                </p>

                <p
                    class="mt-1 text-xs leading-5 text-[#64748B]"
                >
                    Your updated account and delivery information will be saved securely.
                </p>

                <div
                    class="mt-4 flex flex-wrap items-center gap-3"
                >
                    <PrimaryButton
                        :disabled="form.processing"
                        class="!rounded-xl !bg-[#087F8C] !px-4 !py-2.5 !text-xs !font-extrabold hover:!bg-[#066B76]"
                    >
                        {{
                            form.processing
                                ? 'Saving...'
                                : 'Save information'
                        }}
                    </PrimaryButton>

                    <span
                        v-if="form.recentlySuccessful"
                        class="inline-flex items-center gap-1.5 text-[10px] font-extrabold text-[#17784F]"
                    >
                        <span
                            class="flex h-4 w-4 items-center justify-center rounded-full bg-[#22A06B] text-white"
                        >
                            ✓
                        </span>

                        Account and address saved successfully.
                    </span>
                </div>
            </div>

        </form>
    </section>
</template>

<style scoped>
.label {
    display: block;
    font-size: 0.68rem;
    line-height: 1rem;
    font-weight: 800;
    color: #475569;
}

.field {
    margin-top: 0.35rem;
    display: block;
    width: 100%;
    min-width: 0;
    border: 1px solid #E5E7EB;
    border-radius: 0.7rem;
    background: #F8FAF9;
    padding: 0.62rem 0.75rem;
    font-size: 0.75rem;
    line-height: 1.15rem;
    color: #1F2937;
    outline: none;
    transition:
        border-color 150ms ease,
        background-color 150ms ease,
        box-shadow 150ms ease;
}

.field::placeholder {
    color: #A0ACB8;
}

.field:hover {
    border-color: #D3E4E3;
}

.field:focus {
    border-color: #16A6A0;
    background: #FFFFFF;
    box-shadow: 0 0 0 3px rgba(22, 166, 160, 0.10);
}

.field:disabled {
    cursor: not-allowed;
    opacity: 0.65;
}

.field-readonly {
    cursor: default;
    background: #F1F5F4;
    color: #64748B;
}

.file-field {
    padding: 0.48rem 0.6rem;
}

.file-field::file-selector-button {
    margin-right: 0.5rem;
    border: 0;
    border-radius: 0.55rem;
    background: #E8F7F6;
    padding: 0.35rem 0.6rem;
    font-size: 0.65rem;
    font-weight: 800;
    color: #087F8C;
    cursor: pointer;
}
</style>