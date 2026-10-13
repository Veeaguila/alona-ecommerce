<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import PartnerField from '@/Components/PartnerField.vue'
const props = defineProps({ form: { type: Object, required: true }, rider: Boolean })
const cities = ref([])
const loading = ref(false)
const error = ref('')
const search = ref('')
const candidate = ref('')
let generation = 0
const field = computed(() => props.rider ? 'delivery_city_codes' : 'coverage_city_codes')
const selectedCodes = computed(() => props.form[field.value] ?? [])
const selectedCities = computed(() => selectedCodes.value.map(code => cities.value.find(city => city.code === code) ?? { code, label: code, partners: [] }))
const choices = computed(() => cities.value.filter(city => !selectedCodes.value.includes(city.code) && city.label.toLocaleLowerCase().includes(search.value.trim().toLocaleLowerCase())))
const partners = computed(() => {
    if (!props.rider || !selectedCities.value.length) return []
    return (selectedCities.value[0].partners ?? []).filter(partner => selectedCities.value.every(city => city.partners?.some(match => match.id === partner.id)))
        .map(partner => ({ value: partner.id, label: partner.business_name }))
})
async function fetchList(url) {
    const response = await fetch(url, { headers: { Accept: 'application/json' } })
    if (!response.ok) throw new Error('Unable to load delivery areas. Please try again.')
    const data = await response.json()
    if (!Array.isArray(data.data)) throw new Error('Unable to load delivery areas. Please try again.')
    return data.data
}
async function load() {
    const current = ++generation
    loading.value = true; error.value = ''
    try {
        let list
        if (props.rider) list = await fetchList('/api/delivery-coverage')
        else {
            const [allCities, provinces, regions] = await Promise.all([fetchList('/api/addresses/cities'), fetchList('/api/addresses/provinces'), fetchList('/api/addresses/regions')])
            const provinceNames = Object.fromEntries(provinces.map(row => [row.code, row.name]))
            const regionNames = Object.fromEntries(regions.map(row => [row.code, row.name]))
            list = allCities.map(city => ({ ...city, label: [city.name, provinceNames[city.province_code], regionNames[city.region_code]].filter(Boolean).join(' · ') }))
        }
        if (current === generation) cities.value = list
    } catch (failure) { if (current === generation) { cities.value = []; error.value = failure.message } }
    finally { if (current === generation) loading.value = false }
}
function add() {
    if (!choices.value.some(city => city.code === candidate.value)) return
    if (selectedCodes.value.length >= (props.rider ? 20 : 100)) return
    props.form[field.value] = [...selectedCodes.value, candidate.value]
    candidate.value = ''; search.value = ''
    props.form.clearErrors?.(field.value)
}
function remove(code) { props.form[field.value] = selectedCodes.value.filter(selected => selected !== code) }
watch(() => [...selectedCodes.value], () => {
    candidate.value = ''
    if (props.rider && !partners.value.some(partner => String(partner.value) === String(props.form.sorting_center_id))) props.form.sorting_center_id = ''
})
watch(search, () => { candidate.value = '' })
watch(() => props.rider, () => { cities.value = []; candidate.value = ''; search.value = ''; load() })
watch(partners, available => {
    if (props.rider && !available.some(partner => String(partner.value) === String(props.form.sorting_center_id))) props.form.sorting_center_id = ''
})
onMounted(load)
onUnmounted(() => { ++generation })
</script>
<template>
    <div class="space-y-4 sm:col-span-2">
        <h3 class="text-sm font-semibold">{{ rider ? 'Preferred delivery areas' : 'Delivery coverage' }} <span class="text-[#087F8C]">*</span></h3>
        <p class="text-sm text-slate-500">{{ rider ? 'Choose cities served by approved Logistics Partners. Your delivery areas can differ from your home address.' : 'Add the cities and municipalities your business serves. Riders can select these areas after your account is verified and approved.' }}</p>
        <div v-if="error" role="alert" class="rounded-xl bg-red-50 p-3 text-sm text-red-600">{{ error }} <button type="button" class="font-semibold underline" @click="load">Retry</button></div>
        <p v-else-if="loading" role="status" class="text-sm text-slate-500">Loading delivery areas…</p>
        <template v-else>
            <label :for="field + '-search'" class="block text-sm font-medium">Search city / municipality, province or region</label>
            <input :id="field + '-search'" v-model="search" type="search" placeholder="Search delivery areas…" class="w-full rounded-xl border-slate-200 text-sm focus:ring-[#087F8C]" />
            <div class="flex flex-col gap-3 sm:flex-row">
                <label class="sr-only" :for="field + '-candidate'">City or municipality to add</label>
                <select :id="field + '-candidate'" v-model="candidate" :disabled="!choices.length" class="min-w-0 flex-1 rounded-xl border-slate-200 text-sm disabled:bg-slate-50">
                    <option value="">Choose a city / municipality</option><option v-for="city in choices" :key="city.code" :value="city.code">{{ city.label }}</option>
                </select>
                <button type="button" :disabled="!candidate || selectedCodes.length >= (rider ? 20 : 100)" @click="add" class="shrink-0 rounded-xl border border-[#087F8C] px-4 py-2.5 text-sm font-semibold text-[#087F8C] disabled:opacity-50">Add area</button>
            </div>
            <p v-if="!choices.length && !selectedCodes.length || search && !choices.length" role="status" class="text-sm text-amber-700">{{ rider ? 'No Logistics Partner currently serves this area. Try another area or return when a partner offers coverage.' : 'No matching cities or municipalities.' }}</p>
        </template>
        <ul v-if="selectedCities.length" class="space-y-2"><li v-for="city in selectedCities" :key="city.code" class="flex items-center justify-between gap-3 rounded-xl bg-[#E8F7F6] px-3 py-2 text-sm"><span>{{ city.label }}</span><button type="button" @click="remove(city.code)" :aria-label="'Remove ' + city.label" class="font-semibold text-[#087F8C]">Remove</button></li></ul>
        <p v-if="form.errors[field] || Object.keys(form.errors).some(key => key.startsWith(field + '.'))" role="alert" class="text-sm text-red-600">{{ form.errors[field] || Object.entries(form.errors).find(([key]) => key.startsWith(field + '.'))?.[1] }}</p>
        <template v-if="rider">
            <PartnerField name="sorting_center_id" label="Preferred Logistics Partner" v-model="form.sorting_center_id" :options="partners" :disabled="loading || !!error || !partners.length" required :error="form.errors.sorting_center_id" />
            <p v-if="selectedCodes.length && !loading && !error && !partners.length" role="status" class="text-sm text-amber-700">No approved Logistics Partner serves all selected areas. Remove an area or choose another combination.</p>
            <p class="text-sm text-slate-500">Your application is submitted to the selected partner. Approval is required before you can take deliveries.</p>
        </template>
    </div>
</template>
