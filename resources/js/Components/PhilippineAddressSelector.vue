<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, useId } from 'vue'
const props = defineProps({ form: { type: Object, required: true } })
const id = useId()
const open = ref(false), active = ref('groups'), provinceChoice = ref(''), independent = ref(false)
const lists = reactive({ groups: [], provinces: [], cities: [], barangays: [], independentCities: [] }), loading = reactive({}), failures = reactive({})
const sequence = { groups: 0, provinces: 0, cities: 0, barangays: 0, independentCities: 0 }
const selected = reactive({ group: '', province: props.form.province || '', city: props.form.municipality || '', barangay: props.form.barangay || '' })
const tabs = [{ level: 'groups', label: 'Region' }, { level: 'provinces', label: 'Province' }, { level: 'cities', label: 'City' }, { level: 'barangays', label: 'Barangay' }]
const summary = computed(() => props.form.barangay_code ? [selected.group, selected.province, selected.city, selected.barangay].filter(Boolean).join(', ') : 'Region, Province, City, Barangay')
const options = computed(() => active.value === 'provinces' && independent.value ? [...lists.provinces, { code: 'independent', name: 'Cities / municipalities without a province' }] : lists[active.value])
const enabled = level => level === 'groups' || level === 'provinces' && props.form.address_group && props.form.address_group !== 'metro-manila' || level === 'cities' && (props.form.province_code || provinceChoice.value === 'independent' || props.form.address_group === 'metro-manila') || level === 'barangays' && props.form.city_code
function clear(level) { ++sequence[level]; lists[level] = []; loading[level] = false; failures[level] = '' }
function clearChildren(level) {
    if (level === 'groups') { props.form.province_code = ''; provinceChoice.value = ''; selected.province = ''; clear('provinces'); clear('independentCities') }
    if (['groups', 'provinces'].includes(level)) { props.form.region_code = ''; props.form.city_code = ''; selected.city = ''; clear('cities') }
    props.form.barangay_code = ''; selected.barangay = ''; clear('barangays')
    props.form.province = selected.province; props.form.municipality = selected.city; props.form.barangay = ''
}
function params(level) {
    if (level === 'groups') return ''
    if (level === 'barangays') return '?parent=' + encodeURIComponent(props.form.city_code)
    const query = new URLSearchParams({ group: props.form.address_group })
    if (level === 'cities' || level === 'independentCities') query.set('province', level === 'independentCities' ? '' : props.form.province_code || '')
    return '?' + query.toString()
}
async function load(level) {
    const current = ++sequence[level]
    loading[level] = true; failures[level] = ''; lists[level] = []
    try {
        const response = await fetch('/api/addresses/' + (level === 'independentCities' ? 'cities' : level) + params(level), { headers: { Accept: 'application/json' } })
        if (!response.ok) throw new Error('Unable to load addresses. Please retry.')
        const data = await response.json()
        if (sequence[level] === current) {
            lists[level] = data.data
            if (level === 'independentCities') independent.value = data.data.some(city => !city.province_code)
        }
    } catch (error) { if (sequence[level] === current) failures[level] = error.message }
    finally { if (sequence[level] === current) loading[level] = false }
}
async function choose(row) {
    const level = active.value
    if (loading[level] || !options.value.some(option => option.code === row.code)) return
    if (level === 'groups') {
        clearChildren(level); props.form.address_group = row.code; selected.group = row.name; independent.value = false
        if (row.code === 'metro-manila') { active.value = 'cities'; await load('cities') }
        else {
            active.value = 'provinces'
            await Promise.all([load('provinces'), load('independentCities')])
        }
    } else if (level === 'provinces') {
        clearChildren(level); provinceChoice.value = row.code; props.form.province_code = row.code === 'independent' ? '' : row.code
        props.form.region_code = row.region_code || ''; selected.province = row.code === 'independent' ? '' : row.name
        props.form.province = selected.province; active.value = 'cities'; await load('cities')
    } else if (level === 'cities') {
        clearChildren(level); props.form.city_code = row.code; props.form.region_code = row.region_code
        selected.city = row.name; props.form.municipality = row.name; active.value = 'barangays'; await load('barangays')
    } else { props.form.barangay_code = row.code; selected.barangay = row.name; props.form.barangay = row.name; open.value = false }
}
onMounted(async () => {
    await load('groups')
    selected.group = lists.groups.find(row => row.code === props.form.address_group)?.name || ''
    if (props.form.address_group) {
        provinceChoice.value = props.form.province_code || 'independent'
        if (props.form.address_group !== 'metro-manila') {
            await Promise.all([load('provinces'), load('independentCities')])
        }
        await load('cities')
        if (props.form.city_code) await load('barangays')
        active.value = props.form.city_code ? 'barangays' : 'cities'
    }
})
onUnmounted(() => { for (const level of Object.keys(sequence)) ++sequence[level] })
</script>
<template>
    <fieldset class="space-y-4" @keydown.esc.stop="open = false">
        <legend class="mb-4 text-lg font-semibold">Philippine address</legend>
        <div>
            <button type="button" :aria-expanded="open" :aria-controls="id" class="block w-full rounded-xl border border-[#E5E7EB] bg-[#F8FAF9] px-4 py-3 text-left text-sm outline-none transition focus:border-[#087F8C] focus:bg-white focus:ring-4 focus:ring-[#E8F7F6]" @click="open = !open">{{ summary }} <span aria-hidden="true" class="float-right">&#8964;</span></button>
            <div v-if="open" :id="id" class="mt-2 overflow-hidden rounded-xl border border-[#E5E7EB] bg-white">
                <div role="tablist" aria-label="Address level" class="grid grid-cols-4 border-b border-[#E5E7EB]">
                    <button v-for="tab in tabs" :id="id + '-' + tab.level" :key="tab.level" type="button" role="tab" :aria-selected="active === tab.level" :aria-controls="id + '-options'" :disabled="!enabled(tab.level)" class="border-b-2 px-1 py-3 text-sm disabled:opacity-50" :class="active === tab.level ? 'border-[#087F8C] text-[#087F8C]' : 'border-transparent text-[#64748B]'" @click="active = tab.level">{{ tab.label }}</button>
                </div>
                <div :id="id + '-options'" role="tabpanel" :aria-labelledby="id + '-' + active" :aria-busy="!!loading[active]" class="max-h-64 overflow-y-auto">
                    <p v-if="loading[active]" role="status" class="px-4 py-3 text-sm text-[#64748B]">Loading...</p>
                    <p v-else-if="failures[active]" role="alert" class="px-4 py-3 text-sm text-red-600">{{ failures[active] }} <button type="button" class="underline" @click="load(active)">Retry</button></p>
                    <template v-else>
                        <button v-for="row in options" :key="row.code" type="button" :aria-pressed="({ groups: form.address_group, provinces: provinceChoice, cities: form.city_code, barangays: form.barangay_code }[active]) === row.code" :class="({ groups: form.address_group, provinces: provinceChoice, cities: form.city_code, barangays: form.barangay_code }[active]) === row.code ? 'bg-[#E8F7F6] text-[#087F8C]' : 'text-[#1F2937]'" class="block w-full px-4 py-3 text-left text-sm hover:bg-[#E8F7F6] focus:bg-[#E8F7F6]" @click="choose(row)">{{ row.name }}</button>
                        <p v-if="!options.length" class="px-4 py-3 text-sm text-[#64748B]">No locations available.</p>
                    </template>
                </div>
                <p v-if="active === 'provinces' && failures.independentCities" role="alert" class="px-4 py-3 text-sm text-red-600">{{ failures.independentCities }} <button type="button" class="underline" @click="load('independentCities')">Retry cities without a province</button></p>
            </div>
            <p v-for="key in ['address_group', 'region_code', 'province_code', 'city_code', 'barangay_code']" :key="key" v-show="form.errors?.[key]" role="alert" class="mt-1 text-sm text-red-600">{{ form.errors?.[key] }}</p>
        </div>
    </fieldset>
</template>
