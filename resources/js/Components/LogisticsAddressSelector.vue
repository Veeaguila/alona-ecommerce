<script setup>
import { onMounted, reactive, ref } from 'vue'
const props = defineProps({ form: { type: Object, required: true }, regions: { type: Array, required: true } })
const provinces = ref([]), cities = ref([]), barangays = ref([])
const loading = reactive({ provinces: false, cities: false, barangays: false })
const errors = reactive({ provinces: '', cities: '', barangays: '' })
let generation = 0
async function fetchRows(level, query, current) {
    loading[level] = true; errors[level] = ''
    try {
        const response = await fetch('/api/addresses/' + level + '?' + new URLSearchParams(query), { headers: { Accept: 'application/json' } })
        if (!response.ok) throw new Error('Unable to load locations. Choose the location again to retry.')
        const json = await response.json()
        return current === generation ? json.data : null
    } catch (error) { if (current === generation) errors[level] = error.message; return null }
    finally { if (current === generation) loading[level] = false }
}
async function changeRegion() {
    const current = ++generation
    props.form.province_selection = ''; props.form.province_code = ''; props.form.city_code = ''; props.form.barangay_code = ''
    provinces.value = []; cities.value = []; barangays.value = []
    Object.keys(loading).forEach(key => { loading[key] = false; errors[key] = '' })
    if (!props.form.region_code) return
    const [provinceRows, cityRows] = await Promise.all([
        fetchRows('provinces', { parent: props.form.region_code }, current),
        fetchRows('cities', { parent: props.form.region_code }, current),
    ])
    if (current !== generation || !provinceRows || !cityRows) return
    provinces.value = provinceRows.map(row => ({ ...row, value: row.code }))
    if (cityRows.some(row => !row.province_code)) provinces.value.push({ value: 'independent', name: props.form.region_code === '1300000000' ? 'Metro Manila' : 'Independent city (no province)' })
}
async function changeProvince() {
    const current = ++generation
    props.form.province_code = props.form.province_selection === 'independent' ? '' : props.form.province_selection
    props.form.city_code = ''; props.form.barangay_code = ''; cities.value = []; barangays.value = []
    loading.barangays = false; errors.barangays = ''
    if (!props.form.province_selection) return
    const rows = await fetchRows('cities', { parent: props.form.region_code, province: props.form.province_code }, current)
    if (rows) cities.value = rows
}
async function changeCity() {
    const current = ++generation
    props.form.barangay_code = ''; barangays.value = []
    if (!props.form.city_code) return
    const rows = await fetchRows('barangays', { parent: props.form.city_code }, current)
    if (rows) barangays.value = rows
}
onMounted(() => { if (props.form.region_code) changeRegion() })
</script>
<template>
    <div class="grid gap-4 sm:grid-cols-2">
        <div v-for="field in [
            { key: 'region_code', label: 'Region', rows: regions, change: changeRegion },
            { key: 'province_selection', label: 'Province', rows: provinces, change: changeProvince, level: 'provinces' },
            { key: 'city_code', label: 'City / municipality', rows: cities, change: changeCity, level: 'cities' },
            { key: 'barangay_code', label: 'Barangay', rows: barangays, change: () => {}, level: 'barangays' },
        ]" :key="field.key">
            <label :for="field.key" class="mb-1.5 block text-sm font-semibold">{{ field.label }} <span class="text-[#087F8C]">*</span></label>
            <select :id="field.key" v-model="form[field.key]" required :aria-invalid="!!(form.errors[field.key] || field.key === 'province_selection' && form.errors.province_code)" class="w-full rounded-xl border-slate-200 text-sm focus:ring-[#087F8C]" @change="field.change">
                <option value="">{{ loading[field.level] ? 'Loading…' : 'Choose ' + field.label.toLowerCase() }}</option>
                <option v-for="row in field.rows" :key="row.value || row.code" :value="row.value || row.code">{{ row.name }}</option>
            </select>
            <p v-if="form.errors[field.key] || field.key === 'province_selection' && form.errors.province_code" role="alert" class="mt-1 text-sm text-red-600">{{ form.errors[field.key] || form.errors.province_code }}</p>
            <p v-if="errors[field.level]" role="alert" class="mt-1 text-sm text-red-600">{{ errors[field.level] }}</p>
        </div>
    </div>
</template>
