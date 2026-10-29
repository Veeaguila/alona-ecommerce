<script setup>
import { ref, reactive, onMounted, onUnmounted } from 'vue'
import ScLayout from '@/Layouts/ScLayout.vue'

const zones = ref([]), provinces = ref([]), busy = ref(false), fresh = ref(false), error = ref(''), notice = ref('')
const blank = () => ({ id: null, name: '', province: '', base_shipping_rate: '', per_kg_rate: '0', estimated_days: '3-7 Business Days', is_active: true, reason: '' })
const form = reactive(blank())
let alive = true, controller
const reset = () => Object.assign(form, blank())
const edit = zone => { Object.assign(form, blank(), zone); form.reason = '' }
const headers = () => ({ Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${localStorage.getItem('logistics_auth_token') || ''}` })
async function request(path, options = {}) {
  const response = await fetch(`/api/sorting-center/delivery-rates${path}`, { ...options, headers: headers(), signal: controller.signal })
  const data = await response.json()
  if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Unable to load delivery rates.')
  return data
}
async function load() {
  if (busy.value) return
  controller = new AbortController(); busy.value = true; fresh.value = false; error.value = ''
  try {
    const data = await request('')
    if (!alive) return
    zones.value = data.data; provinces.value = data.provinces; fresh.value = true
  } catch (e) { if (alive && e.name !== 'AbortError') error.value = e.message }
  finally { if (alive) busy.value = false }
}
async function save() {
  if (busy.value || !fresh.value) return
  controller = new AbortController(); busy.value = true; fresh.value = false; error.value = ''; notice.value = ''
  try {
    const data = await request(form.id ? `/${form.id}` : '', { method: form.id ? 'PUT' : 'POST', body: JSON.stringify(form) })
    if (!alive) return
    notice.value = data.message; reset()
  } catch (e) { if (alive && e.name !== 'AbortError') error.value = `${e.message} Refresh rates before trying again.` }
  finally { if (alive) busy.value = false }
  if (alive && !error.value) await load()
}
onMounted(load)
onUnmounted(() => { alive = false; controller?.abort() })
</script>

<template>
  <ScLayout title="Zones & Rates" subtitle="Set delivery coverage and fees for your company.">
    <div class="space-y-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="app-description max-w-2xl">The base rate covers the first kilogram per seller. Each additional started kilogram uses the extra rate. Existing orders retain their booked fees.</p>
        <button class="app-button-secondary" type="button" :disabled="busy" @click="load">{{ busy ? 'Loading…' : 'Refresh rates' }}</button>
      </div>
      <p v-if="error" role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ error }}</p>
      <p v-if="notice" role="status" class="rounded-lg border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900">{{ notice }}</p>
      <div class="grid gap-6 xl:grid-cols-2">
        <section class="app-panel app-panel--padded">
          <h2 class="app-section-title">Your delivery zones</h2>
          <p v-if="fresh && !zones.length" class="py-6 text-sm text-slate-600">No company rates configured. Add a province your company can actually serve.</p>
          <ul class="divide-y divide-slate-200">
            <li v-for="zone in zones" :key="zone.id" class="flex items-start justify-between gap-3 py-4">
              <div class="min-w-0 break-words"><strong>{{ zone.name }}</strong><p class="mt-1 text-sm">{{ zone.province }} · {{ zone.is_active ? 'Active' : 'Inactive' }}</p><p class="mt-1 text-sm text-slate-600">₱{{ Number(zone.base_shipping_rate).toFixed(2) }} first kg + ₱{{ Number(zone.per_kg_rate).toFixed(2) }} / extra kg</p><p class="mt-1 text-xs text-slate-500">{{ zone.estimated_days }}</p></div>
              <button type="button" class="app-button-secondary" :disabled="busy || !fresh" @click="edit(zone)">Edit</button>
            </li>
          </ul>
        </section>
        <section class="app-panel app-panel--padded">
          <form class="space-y-4" @submit.prevent="save">
            <div class="flex items-center justify-between gap-3"><h2 class="app-section-title">{{ form.id ? 'Edit delivery rate' : 'Add delivery zone' }}</h2><button v-if="form.id" type="button" class="text-sm underline" :disabled="busy" @click="reset">Cancel edit</button></div>
            <label class="block text-sm">Zone name<input v-model.trim="form.name" class="app-input mt-1 w-full" maxlength="100" required :disabled="busy" /></label>
            <label class="block text-sm">Province / Metro Manila<select v-model="form.province" class="app-input mt-1 w-full" required :disabled="busy"><option value="">Choose province</option><option v-for="province in provinces" :key="province" :value="province">{{ province }}</option></select></label>
            <div class="grid gap-3 sm:grid-cols-2">
              <label class="text-sm">Base rate (₱)<input v-model="form.base_shipping_rate" type="number" min="0.01" max="999999.99" step="0.01" required class="app-input mt-1 w-full" :disabled="busy" /></label>
              <label class="text-sm">Extra kilogram (₱)<input v-model="form.per_kg_rate" type="number" min="0" max="999999.99" step="0.01" required class="app-input mt-1 w-full" :disabled="busy" /></label>
            </div>
            <label class="block text-sm">Delivery estimate<input v-model.trim="form.estimated_days" maxlength="50" required class="app-input mt-1 w-full" :disabled="busy" /></label>
            <label class="flex items-start gap-2 text-sm"><input v-model="form.is_active" type="checkbox" class="mt-1" :disabled="busy" /> Our company can serve this province and accepts new bookings.</label>
            <label class="block text-sm">Reason for change<textarea v-model.trim="form.reason" minlength="10" maxlength="300" required class="app-input mt-1 w-full" :disabled="busy" /></label>
            <p class="text-xs text-slate-500">Activating a zone declares company coverage. Arrange your Riders and transport before accepting deliveries. Disabling a rate does not cancel existing orders or remove separately configured service areas.</p>
            <button class="app-button-primary" :disabled="busy || !fresh">{{ busy ? 'Saving…' : 'Save delivery rate' }}</button>
          </form>
        </section>
      </div>
    </div>
  </ScLayout>
</template>
