<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'

const props = defineProps({ rider: Object, parcels: Array })

const scanRef = ref('')
const scanning = ref(false)
const scanResult = ref(null)
const scanError = ref('')
const photoFile = ref(null)
const photoPreview = ref(null)

async function submitScan() {
    const refCode = scanRef.value.trim()
    if (!refCode || scanning.value) return
    scanning.value = true
    scanError.value = ''
    try {
        const formData = new FormData()
        formData.append('reference', refCode)
        if (photoFile.value) {
            formData.append('photo', photoFile.value)
        }
        const token = localStorage.getItem('logistics_auth_token')
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')

        const response = await fetch('/api/logistics/smart-scan', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                ...(token ? { Authorization: `Bearer ${token}` } : {}),
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            body: formData,
        })
        const data = await response.json()
        if (!response.ok) {
            throw new Error(data.message || 'Scan could not be processed.')
        }
        scanResult.value = data
        if (data.action_taken) {
            photoFile.value = null
            photoPreview.value = null
            router.reload({ only: ['parcels'] })
        }
    } catch (err) {
        scanError.value = err.message
    } finally {
        scanning.value = false
    }
}

function handlePhotoChange(event) {
    const file = event.target.files?.[0]
    if (file) {
        photoFile.value = file
        photoPreview.value = URL.createObjectURL(file)
    }
}

function resetScan() {
    scanRef.value = ''
    scanResult.value = null
    scanError.value = ''
    photoFile.value = null
    photoPreview.value = null
}

function quickScanItem(orderId) {
    scanRef.value = orderId
    submitScan()
}
</script>

<template>
    <Head title="Rider dashboard" />
    <GuestLayout>
        <main class="mx-auto max-w-5xl px-4 py-8">
            <!-- Header Section -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                        <p class="text-xs font-bold uppercase tracking-wider text-[#087F8C]">Alona Rider Network</p>
                    </div>
                    <h1 class="mt-1 text-2xl sm:text-3xl font-extrabold text-slate-900">Welcome, {{ rider.name }}</h1>
                    <p class="mt-1 text-sm text-slate-500 capitalize">{{ rider.vehicle_type }} · {{ rider.approval_status }} · {{ rider.company_name }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('application.status')" class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        Profile
                    </Link>
                    <button type="button" @click="router.reload()" class="rounded-xl border border-teal-600 bg-teal-50 px-3.5 py-2 text-xs font-bold text-teal-700 hover:bg-teal-100">
                        ↻ Refresh
                    </button>
                </div>
            </div>

            <!-- UNIVERSAL SMART SCANNER CARD -->
            <section class="mt-6 rounded-2xl border-2 border-teal-500/40 bg-gradient-to-br from-teal-950 via-slate-900 to-slate-950 p-6 text-white shadow-xl">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-teal-800/60 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-teal-500/20 text-teal-300 border border-teal-400/30 text-lg">
                            📷
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-teal-100">Universal Smart Scanner</h2>
                            <p class="text-xs text-teal-300/80">Scan waybill barcode to automatically advance pickup, depart hub, or drop off</p>
                        </div>
                    </div>
                    <span class="rounded-full bg-teal-500/20 px-3 py-1 text-[11px] font-semibold text-teal-300 border border-teal-500/30">
                        Auto-State Machine
                    </span>
                </div>

                <!-- Input Form -->
                <form class="mt-4 flex flex-col sm:flex-row items-stretch sm:items-center gap-3" @submit.prevent="submitScan">
                    <div class="relative flex-1">
                        <input
                            v-model.trim="scanRef"
                            type="text"
                            placeholder="Scan barcode or enter waybill code (e.g. ITEM-123)"
                            class="w-full rounded-xl border border-teal-700/60 bg-slate-800/90 px-4 py-3 text-sm text-white placeholder-slate-400 focus:border-teal-400 focus:outline-none focus:ring-2 focus:ring-teal-400/20"
                            :disabled="scanning"
                            autofocus
                        />
                        <button
                            v-if="scanRef"
                            type="button"
                            @click="resetScan"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 hover:text-white"
                        >
                            ✕
                        </button>
                    </div>
                    <button
                        type="submit"
                        class="rounded-xl bg-teal-500 px-6 py-3 text-sm font-bold text-slate-950 shadow-md hover:bg-teal-400 active:scale-95 transition-all disabled:opacity-50 cursor-pointer"
                        :disabled="scanning || !scanRef"
                    >
                        {{ scanning ? 'Scanning…' : '📷 Scan Waybill' }}
                    </button>
                </form>

                <!-- Error Alert -->
                <div v-if="scanError" class="mt-4 rounded-xl border border-red-500/40 bg-red-950/60 p-3 text-xs text-red-200">
                    <strong>Error:</strong> {{ scanError }}
                </div>

                <!-- Scan Results Panel -->
                <div v-if="scanResult" class="mt-4 rounded-xl border border-teal-600/40 bg-slate-800/80 p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">
                                {{ scanResult.action_taken === 'DELIVERED' ? '🎉' : scanResult.action_taken === 'OUT_FOR_DELIVERY' ? '🚚' : '📦' }}
                            </span>
                            <h3 class="font-bold text-white text-sm">{{ scanResult.title }}</h3>
                        </div>
                        <span class="rounded-lg bg-teal-400/20 px-2 py-0.5 text-[11px] font-bold text-teal-300 uppercase">
                            {{ scanResult.action_taken || scanResult.stage }}
                        </span>
                    </div>

                    <p class="mt-1 text-xs text-slate-300">{{ scanResult.message }}</p>

                    <!-- Parcel Summary -->
                    <div v-if="scanResult.parcel" class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-2 rounded-lg bg-slate-900/70 p-3 text-xs">
                        <div>
                            <span class="text-slate-400 block text-[10px]">Reference</span>
                            <strong class="text-white">{{ scanResult.parcel.order_id }}</strong>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[10px]">Payment</span>
                            <span :class="scanResult.parcel.is_cod ? 'text-amber-400 font-bold' : 'text-emerald-400 font-bold'">
                                {{ scanResult.parcel.cod_label }}
                            </span>
                        </div>
                        <div class="col-span-2">
                            <span class="text-slate-400 block text-[10px]">Delivery Address</span>
                            <span class="text-slate-200 truncate block">{{ scanResult.parcel.delivery_address }}</span>
                        </div>
                    </div>

                    <!-- Next instruction if any -->
                    <p v-if="scanResult.next_instruction" class="mt-2 text-xs font-semibold text-teal-300">
                        ➔ {{ scanResult.next_instruction }}
                    </p>

                    <!-- DROP-OFF COMPLETION STEP (PHOTO & COD CONFIRMATION) -->
                    <div v-if="scanResult.action_needed === 'CAPTURE_PHOTO_PROOF'" class="mt-4 rounded-xl border border-amber-500/40 bg-amber-950/40 p-4">
                        <div class="flex items-center justify-between pb-2 border-b border-amber-800/40">
                            <div>
                                <h4 class="font-bold text-amber-200 text-sm">📸 Delivery Photo Proof & Collection</h4>
                                <p class="text-xs text-amber-300/80">Capture photo of package handed to buyer to complete delivery</p>
                            </div>
                            <span v-if="scanResult.is_cod" class="rounded-lg bg-amber-500 px-2.5 py-1 text-xs font-black text-slate-950">
                                Collect ₱{{ scanResult.cod_amount }}
                            </span>
                        </div>

                        <!-- Buyer Info -->
                        <div v-if="scanResult.buyer" class="mt-3 text-xs text-slate-300">
                            <p><strong>Buyer:</strong> {{ scanResult.buyer.name }} ({{ scanResult.buyer.phone }})</p>
                            <p class="mt-0.5"><strong>Address:</strong> {{ scanResult.buyer.address }}</p>
                        </div>

                        <!-- Photo Capture / Upload -->
                        <div class="mt-3 flex flex-col sm:flex-row items-center gap-3">
                            <label class="flex-1 w-full cursor-pointer rounded-xl border border-dashed border-amber-400/50 bg-slate-900/60 p-3 text-center hover:border-amber-400">
                                <input type="file" accept="image/*" capture="environment" class="hidden" @change="handlePhotoChange" />
                                <span v-if="!photoPreview" class="text-xs text-amber-200 font-semibold">
                                    📷 Tap to take photo proof with camera
                                </span>
                                <div v-else class="flex items-center justify-center gap-2">
                                    <img :src="photoPreview" alt="Proof" class="h-10 w-10 rounded object-cover border border-amber-400" />
                                    <span class="text-xs text-emerald-300 font-bold">✓ Photo captured - ready to submit</span>
                                </div>
                            </label>

                            <button
                                type="button"
                                class="w-full sm:w-auto rounded-xl bg-amber-500 px-6 py-3 text-xs font-black text-slate-950 shadow-md hover:bg-amber-400 active:scale-95 disabled:opacity-50 cursor-pointer"
                                :disabled="scanning || !photoFile"
                                @click="submitScan"
                            >
                                {{ scanning ? 'Saving…' : '✓ Confirm & Mark Delivered' }}
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ASSIGNED DELIVERIES LIST -->
            <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Assigned Deliveries</h2>
                        <p class="text-xs text-slate-500">Parcels assigned to your route. Tap Scan to quickly process any item.</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">
                        {{ parcels.length }} parcel(s)
                    </span>
                </div>

                <p v-if="!parcels.length" class="mt-6 text-center py-8 text-sm text-slate-500">
                    No deliveries assigned yet. Assigned parcels will appear here automatically.
                </p>

                <ul v-else class="mt-4 divide-y divide-slate-100">
                    <li v-for="parcel in parcels" :key="parcel.id" class="py-4 hover:bg-slate-50/60 rounded-xl px-2 transition-colors">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div>
                                <div class="flex items-center gap-2">
                                    <p class="font-bold text-slate-900 text-sm">{{ parcel.order_id }}</p>
                                    <span :class="[
                                        'rounded-lg px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider',
                                        parcel.status === 'DELIVERED' ? 'bg-emerald-100 text-emerald-800' :
                                        parcel.status === 'OUT_FOR_DELIVERY' ? 'bg-blue-100 text-blue-800' :
                                        'bg-amber-100 text-amber-800'
                                    ]">
                                        {{ parcel.status.replaceAll('_', ' ') }}
                                    </span>
                                    <span v-if="parcel.shipping_method === 'express' || parcel.is_express" class="rounded-full bg-amber-500 px-2 py-0.5 text-[10px] font-black text-white">
                                        ⚡ EXPRESS
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">{{ parcel.delivery_address }}</p>
                            </div>
                            <button
                                type="button"
                                class="shrink-0 rounded-lg border border-teal-600 bg-teal-50 px-3 py-1.5 text-xs font-bold text-teal-700 hover:bg-teal-100 active:scale-95 cursor-pointer"
                                @click="quickScanItem(parcel.order_id)"
                            >
                                📷 Scan this
                            </button>
                        </div>
                    </li>
                </ul>
            </section>
        </main>
    </GuestLayout>
</template>
