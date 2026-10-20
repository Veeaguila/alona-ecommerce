<!-- Buyer delivery address book with granular fields, PSGC cascading dropdowns, 10-address limit, and default guards. -->
<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import BuyerLayout from '@/Layouts/BuyerLayout.vue'

const props = defineProps({
    addresses: {
        type: Array,
        default: () => [],
    },
})

const page = usePage()
const user = computed(() => page.props.auth?.user || {})

const isEditing = ref(false)
const editingAddressId = ref(null)

/*
|--------------------------------------------------------------------------
| Form with Granular Fields (BUY-ADDR-001, BUY-ADDR-003, BUY-ADDR-004)
|--------------------------------------------------------------------------
*/

const form = useForm({
    label: 'Home',
    recipient_name: user.value.first_name ? `${user.value.first_name} ${user.value.last_name || ''}`.trim() : '',
    recipient_phone: user.value.contact_no || '',
    province: '',
    municipality: '',
    barangay: '',
    street_address: '',
    postal_code: '',
    address: '',
    is_default: false,
})

const addressList = computed(() => {
    return Array.isArray(props.addresses)
        ? props.addresses
        : []
})

const isLimitReached = computed(() => {
    return addressList.value.length >= 10 && !isEditing.value
})

/*
|--------------------------------------------------------------------------
| PSGC Cascading Location Data (BUY-ADDR-002)
|--------------------------------------------------------------------------
*/

const provinces = ref([])
const municipalities = ref([])
const barangays = ref([])

const provinceCode = ref('')
const municipalityCode = ref('')

const loadingProvinces = ref(false)
const loadingMunicipalities = ref(false)
const loadingBarangays = ref(false)

const load = async url => {
    const response = await fetch(url)
    if (!response.ok) {
        throw new Error(`Failed to load ${url}`)
    }
    return response.json()
}

onMounted(async () => {
    try {
        loadingProvinces.value = true
        provinces.value = await load('https://psgc.gitlab.io/api/provinces/')
    } catch (e) {
        console.error('Failed to load PSGC provinces:', e)
    } finally {
        loadingProvinces.value = false
    }
})

watch(provinceCode, async code => {
    const found = provinces.value.find(p => p.code === code)
    if (found) {
        form.province = found.name
    }

    municipalities.value = []
    barangays.value = []
    municipalityCode.value = ''

    if (!code) {
        form.municipality = ''
        form.barangay = ''
        return
    }

    try {
        loadingMunicipalities.value = true
        municipalities.value = await load(`https://psgc.gitlab.io/api/provinces/${code}/municipalities/`)

        if (form.municipality) {
            const savedMuni = municipalities.value.find(m => m.name === form.municipality)
            if (savedMuni) {
                municipalityCode.value = savedMuni.code
            }
        }
    } catch (e) {
        console.error('Failed to load PSGC municipalities:', e)
    } finally {
        loadingMunicipalities.value = false
    }
})

watch(municipalityCode, async code => {
    const found = municipalities.value.find(m => m.code === code)
    if (found) {
        form.municipality = found.name
    }

    barangays.value = []

    if (!code) {
        form.barangay = ''
        return
    }

    try {
        loadingBarangays.value = true
        barangays.value = await load(`https://psgc.gitlab.io/api/municipalities/${code}/barangays/`)
    } catch (e) {
        console.error('Failed to load PSGC barangays:', e)
    } finally {
        loadingBarangays.value = false
    }
})

/*
|--------------------------------------------------------------------------
| Actions
|--------------------------------------------------------------------------
*/

const resetForm = () => {
    form.reset()
    form.label = 'Home'
    form.recipient_name = user.value.first_name ? `${user.value.first_name} ${user.value.last_name || ''}`.trim() : ''
    form.recipient_phone = user.value.contact_no || ''
    form.province = ''
    form.municipality = ''
    form.barangay = ''
    form.street_address = ''
    form.postal_code = ''
    form.address = ''
    form.is_default = false

    provinceCode.value = ''
    municipalityCode.value = ''
    municipalities.value = []
    barangays.value = []

    isEditing.value = false
    editingAddressId.value = null
    form.clearErrors()
}

const submit = () => {
    // Generate full address string
    const parts = [
        form.street_address,
        form.barangay ? `Brgy. ${form.barangay}` : '',
        form.municipality,
        form.province,
        form.postal_code,
    ].filter(Boolean)

    form.address = parts.join(', ')

    if (isEditing.value && editingAddressId.value) {
        form.patch(route('buyer.addresses.update', editingAddressId.value), {
            preserveScroll: true,
            onSuccess: () => {
                resetForm()
            },
        })
        return
    }

    form.post(route('buyer.addresses.store'), {
        preserveScroll: true,
        onSuccess: () => {
            resetForm()
        },
    })
}

const editAddress = address => {
    if (!address?.id) return

    isEditing.value = true
    editingAddressId.value = address.id
    form.clearErrors()

    form.label = address.label || 'Home'
    form.recipient_name = address.recipient_name || user.value.name || ''
    form.recipient_phone = address.recipient_phone || user.value.contact_no || ''
    form.province = address.province || ''
    form.municipality = address.municipality || ''
    form.barangay = address.barangay || ''
    form.street_address = address.street_address || ''
    form.postal_code = address.postal_code || ''
    form.address = address.address || ''
    form.is_default = Boolean(address.is_default)

    // Match province in PSGC list
    const foundProv = provinces.value.find(p => p.name === address.province)
    if (foundProv) {
        provinceCode.value = foundProv.code
    }
}

const setDefaultAddress = address => {
    if (!address?.id || address.is_default) return

    useForm({}).patch(route('buyer.addresses.default', address.id), {
        preserveScroll: true,
    })
}

const remove = address => {
    if (!address?.id) return

    // Guard deleting default address when other addresses exist (BUY-ADDR-007)
    if (address.is_default && addressList.value.length > 1) {
        alert('Cannot delete your default delivery address. Please set another address as default first.')
        return
    }

    if (!window.confirm(`Are you sure you want to remove the "${address.label || 'address'}" delivery address?`)) {
        return
    }

    useForm({}).delete(route('buyer.addresses.destroy', address.id), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="My Addresses" />

    <BuyerLayout>
        <main class="min-h-[calc(100vh-80px)] w-full min-w-0 overflow-x-hidden bg-[#F8FAF9]">
            <div class="mx-auto w-full max-w-[1536px] px-4 py-6 pb-12 sm:px-6 sm:py-7 lg:px-8 2xl:px-10 lg:py-8 lg:pb-14">
                
                <!-- PAGE HEADER -->
                <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-[#F4B942]"></span>
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[#087F8C] sm:text-xs">
                                Delivery Details
                            </p>
                        </div>

                        <h1 class="mt-1 text-2xl font-extrabold tracking-tight text-[#1F2937] sm:text-3xl">
                            My Address Book
                        </h1>

                        <p class="mt-1 text-xs leading-5 text-[#64748B] sm:text-sm">
                            Manage the shipping locations you use for orders and checkout.
                        </p>
                    </div>

                    <!-- 10-Address Limit Badge (BUY-ADDR-005) -->
                    <div class="flex items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-bold"
                            :class="addressList.length >= 10 ? 'border-amber-300 bg-amber-50 text-amber-800' : 'border-[#DDE8E8] bg-white text-[#475569] shadow-sm'"
                        >
                            <span
                                class="h-2 w-2 rounded-full"
                                :class="addressList.length >= 10 ? 'bg-amber-500' : 'bg-[#087F8C]'"
                            ></span>
                            <span>{{ addressList.length }} / 10 Addresses Saved</span>
                        </span>
                    </div>
                </div>

                <!-- GLOBAL ERROR BANNER (e.g. from backend default delete guard) -->
                <div
                    v-if="$page.props.errors?.address || $page.props.errors?.error"
                    class="mb-6 rounded-2xl border border-rose-300 bg-rose-50 p-4 text-xs font-bold text-rose-800 shadow-sm"
                >
                    <div class="flex items-center gap-2">
                        <svg class="h-5 w-5 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ $page.props.errors?.address || $page.props.errors?.error }}</span>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-[1fr_420px]">
                    
                    <!-- SAVED ADDRESSES LIST -->
                    <section class="space-y-4">
                        <div v-if="addressList.length" class="space-y-4">
                            <article
                                v-for="address in addressList"
                                :key="address.id"
                                class="rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-sm transition hover:shadow-md"
                                :class="address.is_default ? 'ring-2 ring-[#087F8C]/20 border-[#087F8C]/40' : ''"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h2 class="text-sm font-extrabold text-[#1F2937]">
                                                {{ address.label || 'Home' }}
                                            </h2>

                                            <span
                                                v-if="address.is_default"
                                                class="rounded-full bg-[#E8F7F6] px-2.5 py-0.5 text-[10px] font-extrabold text-[#087F8C]"
                                            >
                                                Default Delivery Address
                                            </span>
                                        </div>

                                        <!-- Recipient details (BUY-ADDR-001) -->
                                        <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-[#1F2937]">
                                            <span class="font-extrabold text-[#087F8C]">
                                                {{ address.recipient_name || user.name || 'Recipient' }}
                                            </span>
                                            <span class="text-slate-300">|</span>
                                            <span class="font-medium text-[#64748B]">
                                                {{ address.recipient_phone || user.contact_no || 'No contact phone' }}
                                            </span>
                                        </div>

                                        <!-- Formatted full address -->
                                        <p class="mt-1.5 text-xs leading-5 text-[#64748B]">
                                            {{ address.address }}
                                        </p>
                                    </div>

                                    <!-- Actions -->
                                    <div class="flex shrink-0 items-center gap-2">
                                        <button
                                            v-if="!address.is_default"
                                            type="button"
                                            class="rounded-lg border border-[#087F8C]/30 bg-[#E8F7F6] px-2.5 py-1 text-xs font-bold text-[#087F8C] transition hover:bg-[#D4EFEF]"
                                            @click="setDefaultAddress(address)"
                                        >
                                            Set as Default
                                        </button>

                                        <button
                                            type="button"
                                            class="rounded-lg px-2 py-1 text-xs font-bold text-[#087F8C] hover:bg-slate-100"
                                            @click="editAddress(address)"
                                        >
                                            Edit
                                        </button>

                                        <button
                                            type="button"
                                            class="rounded-lg px-2 py-1 text-xs font-bold text-rose-600 hover:bg-rose-50"
                                            @click="remove(address)"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <!-- EMPTY STATE -->
                        <div
                            v-else
                            class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center"
                        >
                            <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <h2 class="mt-3 text-base font-extrabold text-[#1F2937]">
                                No saved delivery addresses yet
                            </h2>
                            <p class="mt-1 text-xs text-[#64748B]">
                                Use the address form on the right to add your delivery location.
                            </p>
                        </div>
                    </section>

                    <!-- ADD / EDIT ADDRESS FORM -->
                    <form
                        class="h-fit rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-sm sm:p-6"
                        @submit.prevent="submit"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-[10px] font-extrabold uppercase tracking-wide text-[#087F8C]">
                                    {{ isEditing ? 'Edit Address' : 'New Address' }}
                                </p>
                                <h2 class="text-base font-extrabold text-[#1F2937]">
                                    {{ isEditing ? 'Update Delivery Address' : 'Add Delivery Address' }}
                                </h2>
                            </div>

                            <span
                                v-if="isEditing"
                                class="rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-bold text-amber-800"
                            >
                                Editing
                            </span>
                        </div>

                        <!-- 10 ADDRESS LIMIT WARNING BANNER (BUY-ADDR-005) -->
                        <div
                            v-if="isLimitReached"
                            class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-3 text-xs text-amber-900"
                        >
                            <strong>Limit Reached:</strong> You have reached the maximum allowed limit of 10 addresses. Please remove an existing address before adding a new one.
                        </div>

                        <!-- RECIPIENT NAME & PHONE (BUY-ADDR-001 & BUY-ADDR-004) -->
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <label class="block">
                                <span class="label">Recipient Name *</span>
                                <input
                                    v-model="form.recipient_name"
                                    required
                                    minlength="2"
                                    maxlength="100"
                                    class="field"
                                    placeholder="Juan Dela Cruz"
                                />
                                <p v-if="form.errors.recipient_name" class="mt-1 text-[11px] font-semibold text-rose-600">
                                    {{ form.errors.recipient_name }}
                                </p>
                            </label>

                            <label class="block">
                                <span class="label">Recipient Phone *</span>
                                <input
                                    v-model="form.recipient_phone"
                                    required
                                    type="tel"
                                    placeholder="09171234567"
                                    class="field"
                                />
                                <p v-if="form.errors.recipient_phone" class="mt-1 text-[11px] font-semibold text-rose-600">
                                    {{ form.errors.recipient_phone }}
                                </p>
                            </label>
                        </div>

                        <!-- LABEL -->
                        <label class="mt-3 block">
                            <span class="label">Address Label *</span>
                            <div class="mt-1 flex gap-2">
                                <button
                                    type="button"
                                    v-for="preset in ['Home', 'Office', 'Work']"
                                    :key="preset"
                                    @click="form.label = preset"
                                    class="rounded-lg px-2.5 py-1 text-xs font-bold transition"
                                    :class="form.label === preset ? 'bg-[#087F8C] text-white' : 'border border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100'"
                                >
                                    {{ preset }}
                                </button>
                                <input
                                    v-model="form.label"
                                    required
                                    maxlength="50"
                                    class="field !mt-0 flex-1"
                                    placeholder="Other (e.g. Warehouse)"
                                />
                            </div>
                            <p v-if="form.errors.label" class="mt-1 text-[11px] font-semibold text-rose-600">
                                {{ form.errors.label }}
                            </p>
                        </label>

                        <!-- PSGC CASCADING LOCATION MENUS (BUY-ADDR-002) -->
                        <div class="mt-3 space-y-3">
                            <!-- Province -->
                            <label class="block">
                                <span class="label">Province *</span>
                                <select
                                    v-model="provinceCode"
                                    required
                                    class="field"
                                    :disabled="loadingProvinces"
                                >
                                    <option value="">
                                        {{ loadingProvinces ? 'Loading provinces...' : 'Select Province' }}
                                    </option>
                                    <option
                                        v-for="prov in provinces"
                                        :key="prov.code"
                                        :value="prov.code"
                                    >
                                        {{ prov.name }}
                                    </option>
                                </select>
                                <p v-if="form.errors.province" class="mt-1 text-[11px] font-semibold text-rose-600">
                                    {{ form.errors.province }}
                                </p>
                            </label>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <!-- Municipality / City -->
                                <label class="block">
                                    <span class="label">City / Municipality *</span>
                                    <select
                                        v-model="municipalityCode"
                                        required
                                        class="field"
                                        :disabled="loadingMunicipalities || !provinceCode"
                                    >
                                        <option value="">
                                            {{ loadingMunicipalities ? 'Loading cities...' : 'Select City / Municipality' }}
                                        </option>
                                        <option
                                            v-for="muni in municipalities"
                                            :key="muni.code"
                                            :value="muni.code"
                                        >
                                            {{ muni.name }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.municipality" class="mt-1 text-[11px] font-semibold text-rose-600">
                                        {{ form.errors.municipality }}
                                    </p>
                                </label>

                                <!-- Barangay -->
                                <label class="block">
                                    <span class="label">Barangay *</span>
                                    <select
                                        v-model="form.barangay"
                                        required
                                        class="field"
                                        :disabled="loadingBarangays || !municipalityCode"
                                    >
                                        <option value="">
                                            {{ loadingBarangays ? 'Loading barangays...' : 'Select Barangay' }}
                                        </option>
                                        <option
                                            v-for="brgy in barangays"
                                            :key="brgy.code"
                                            :value="brgy.name"
                                        >
                                            {{ brgy.name }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.barangay" class="mt-1 text-[11px] font-semibold text-rose-600">
                                        {{ form.errors.barangay }}
                                    </p>
                                </label>
                            </div>
                        </div>

                        <!-- STREET ADDRESS & POSTAL CODE (BUY-ADDR-001) -->
                        <div class="mt-3 grid gap-3 sm:grid-cols-3">
                            <label class="block sm:col-span-2">
                                <span class="label">Street, Unit / House No. *</span>
                                <input
                                    v-model="form.street_address"
                                    required
                                    maxlength="255"
                                    class="field"
                                    placeholder="123 Rizal Street, Bldg 4"
                                />
                                <p v-if="form.errors.street_address" class="mt-1 text-[11px] font-semibold text-rose-600">
                                    {{ form.errors.street_address }}
                                </p>
                            </label>

                            <label class="block">
                                <span class="label">Postal Code</span>
                                <input
                                    v-model="form.postal_code"
                                    maxlength="10"
                                    class="field"
                                    placeholder="6300"
                                />
                                <p v-if="form.errors.postal_code" class="mt-1 text-[11px] font-semibold text-rose-600">
                                    {{ form.errors.postal_code }}
                                </p>
                            </label>
                        </div>

                        <!-- DEFAULT CHECKBOX (BUY-ADDR-006) -->
                        <label class="mt-4 flex items-center gap-2 text-xs font-semibold text-[#1F2937]">
                            <input
                                v-model="form.is_default"
                                type="checkbox"
                                class="rounded border-slate-300 text-[#087F8C] focus:ring-[#087F8C]"
                            />
                            <span>Set as default delivery address</span>
                        </label>

                        <!-- FORM ACTIONS -->
                        <div class="mt-5 flex gap-2">
                            <button
                                type="submit"
                                :disabled="form.processing || isLimitReached"
                                class="flex-1 rounded-xl bg-[#087F8C] px-4 py-2.5 text-xs font-extrabold text-white shadow-sm transition hover:bg-[#066B76] disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {{
                                    form.processing
                                        ? 'Saving...'
                                        : isEditing
                                            ? 'Update Address'
                                            : 'Save Address'
                                }}
                            </button>

                            <button
                                v-if="isEditing"
                                type="button"
                                :disabled="form.processing"
                                class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600 transition hover:bg-slate-50 disabled:opacity-50"
                                @click="resetForm"
                            >
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>

                <!-- CHECKOUT LINK -->
                <div class="mt-6 flex items-center justify-between">
                    <Link
                        :href="route('buyer.checkout')"
                        class="inline-flex items-center gap-1.5 text-xs font-extrabold text-[#087F8C] hover:text-[#066B76]"
                    >
                        <span>Continue to checkout</span>
                        <span>→</span>
                    </Link>
                </div>
            </div>
        </main>
    </BuyerLayout>
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
</style>
