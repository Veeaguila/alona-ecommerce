<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import axios from 'axios'

const props = defineProps({ orderId: { type: Number, required: true }, parcelId: { type: Number, required: true } })
const code = ref('')
const expiresAt = ref(null)
const now = ref(Date.now())
const busy = ref(false)
const error = ref('')
const canRenew = ref(false)
let disposed = false
const controller = new AbortController()
const timer = setInterval(() => { now.value = Date.now() }, 1000)
onBeforeUnmount(() => { disposed = true; clearInterval(timer); controller.abort(); code.value = '' })
const expired = computed(() => expiresAt.value && Date.parse(expiresAt.value) <= now.value)
async function load(renew = false) {
    if (busy.value) return
    busy.value = true; error.value = ''; code.value = ''
    const path = `/buyer/orders/${props.orderId}/parcels/${props.parcelId}/delivery-otp`
    try {
        const response = renew ? await axios.post(`${path}/renew`, {}, { signal: controller.signal }) : await axios.get(path, { signal: controller.signal })
        if (disposed) return
        const data = response.data.data
        if (!/^[0-9]{6}$/.test(data?.otp || '') || !Number.isFinite(Date.parse(data?.expires_at))) throw new Error('The code could not be loaded. Please retry.')
        code.value = data.otp; expiresAt.value = data.expires_at; canRenew.value = false
    } catch (failure) {
        if (disposed) return
        canRenew.value = [410, 429].includes(failure.response?.status)
        error.value = failure.response?.data?.message || 'The delivery code could not be loaded. Please retry.'
    } finally { if (!disposed) busy.value = false }
}
</script>

<template>
    <section class="mt-3 rounded-xl border border-[#D9F0EF] bg-[#E8F7F6] p-3 text-sm" :aria-label="`Delivery OTP for parcel ${parcelId}`">
        <h4 class="font-bold">Buyer delivery OTP</h4>
        <p>Share this code with your assigned Rider only when receiving this parcel. Photo proof is also required.</p>
        <p v-if="code && !expired" class="mt-2 font-mono text-xl font-bold tracking-widest" aria-label="Delivery OTP">{{ code }}</p>
        <p v-if="expiresAt && !expired" class="text-xs">Expires: {{ new Date(expiresAt).toLocaleString() }}</p>
        <p v-if="expired" role="status">This code expired. Request a new code.</p>
        <p v-if="error" role="alert">{{ error }}</p>
        <button type="button" class="mt-2 rounded-lg border border-[#087F8C] px-3 py-2 font-semibold text-[#087F8C] disabled:opacity-50" :disabled="busy" @click="load(!!expired || canRenew)">{{ busy ? 'Loading code...' : expired || canRenew ? 'Request new delivery OTP' : 'Show delivery OTP' }}</button>
    </section>
</template>
