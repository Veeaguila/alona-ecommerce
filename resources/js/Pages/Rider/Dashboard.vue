<script setup>
import { computed, onMounted, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { useRealtime } from '@/Composables/useRealtime'

const props = defineProps({
    rider: Object,
    parcels: { type: Array, default: () => [] },
    availablePickups: { type: Array, default: () => [] },
    acceptedPickups: { type: Array, default: () => [] },
    codRecords: { type: Array, default: () => [] },
    earnings: {
        type: Object,
        default: () => ({ pending: 0, available: 0, settled: 0, paid_out: 0, items: [] }),
    },
    notifications: { type: Array, default: () => [] },
})

const page = usePage()
const { listen, onReconnect, reloadProps } = useRealtime()

const scanRef = ref('')
const scanning = ref(false)
const scanResult = ref(null)
const scanError = ref('')
const photoFile = ref(null)
const photoPreview = ref(null)

const acceptingParcelId = ref(null)
const pickupError = ref('')
const pickupNotice = ref('')
const takenParcelIds = ref(new Set())

const visibleAvailablePickups = computed(() =>
    (props.availablePickups || []).filter((job) => !takenParcelIds.value.has(Number(job.id)))
)

const formatMoney = (val) =>
    '₱' + Number(val || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

onMounted(() => {
    const userId = page.props.auth?.user?.id
    const centerId = props.rider?.sorting_center_id || props.rider?.sorting_center?.id

    if (userId) {
        listen(`rider.${userId}`, '*', () => {
            reloadProps()
        })
    }

    if (centerId) {
        listen(`riders.center.${centerId}`, '*', (eventName, payload) => {
            if (
                (eventName === 'parcel.pickup_assigned' || payload?.is_pickup_taken || payload?.is_exclusive_taken) &&
                payload?.parcel_id
            ) {
                const nextSet = new Set(takenParcelIds.value)
                nextSet.add(Number(payload.parcel_id))
                takenParcelIds.value = nextSet
            }
            reloadProps()
        })
    }

    onReconnect(() => {
        reloadProps()
    })
})

async function acceptPickupJob(parcelId) {
    if (acceptingParcelId.value) return
    acceptingParcelId.value = parcelId
    pickupError.value = ''
    pickupNotice.value = ''

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        const response = await fetch(`/rider/pickups/${parcelId}/accept`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            credentials: 'same-origin',
        })
        const data = await response.json().catch(() => ({}))
        if (!response.ok) {
            throw new Error(data.message || 'This pickup has already been accepted by another rider.')
        }
        const nextSet = new Set(takenParcelIds.value)
        nextSet.add(Number(parcelId))
        takenParcelIds.value = nextSet
        pickupNotice.value = data.message || 'Pickup job accepted!'
        reloadProps(['availablePickups', 'acceptedPickups', 'parcels', 'notifications'])
    } catch (err) {
        pickupError.value = err.message
        reloadProps(['availablePickups', 'acceptedPickups'])
    } finally {
        acceptingParcelId.value = null
    }
}

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
            router.reload({ only: ['parcels', 'availablePickups', 'acceptedPickups', 'codRecords', 'earnings'] })
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
                        <p class="text-xs font-bold uppercase tracking-wider text-[#087F8C]">Alona Rider Network · Live</p>
                    </div>
                    <h1 class="mt-1 text-2xl sm:text-3xl font-extrabold text-slate-900">Welcome, {{ rider?.name }}</h1>
                    <p class="mt-1 text-sm text-slate-500 capitalize">{{ rider?.vehicle_type }} · {{ rider?.approval_status }} · {{ rider?.company_name }}</p>
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

            <!-- REAL-TIME EARNINGS SUMMARY (70% Shipping Share) -->
            <section class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Pending Earnings</p>
                    <p class="mt-1 text-lg font-extrabold text-amber-600">{{ formatMoney(earnings?.pending) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Available (70% Share)</p>
                    <p class="mt-1 text-lg font-extrabold text-[#087F8C]">{{ formatMoney(earnings?.available) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Settled</p>
                    <p class="mt-1 text-lg font-extrabold text-emerald-700">{{ formatMoney(earnings?.settled) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-xs">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Paid Out</p>
                    <p class="mt-1 text-lg font-extrabold text-slate-900">{{ formatMoney(earnings?.paid_out) }}</p>
                </div>
            </section>

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

            <!-- AVAILABLE PICKUP JOBS (EXCLUSIVE ATOMIC ACCEPTANCE) -->
            <section class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Available Pickup Jobs</h2>
                        <p class="text-xs text-slate-500">Live seller pickups in your sorting center. Accepting a job immediately claims it exclusively.</p>
                    </div>
                    <span class="rounded-full bg-teal-50 px-3 py-1 text-xs font-bold text-[#087F8C]">
                        {{ visibleAvailablePickups.length }} available
                    </span>
                </div>

                <div v-if="pickupError" class="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-semibold text-rose-700">
                    {{ pickupError }}
                </div>
                <div v-if="pickupNotice" class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs font-semibold text-emerald-700">
                    {{ pickupNotice }}
                </div>

                <p v-if="!visibleAvailablePickups.length" class="mt-6 text-center py-6 text-sm text-slate-500">
                    No unassigned pickup jobs right now. New pickup requests will appear here automatically.
                </p>

                <ul v-else class="mt-4 divide-y divide-slate-100">
                    <li v-for="job in visibleAvailablePickups" :key="job.id" class="py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="font-bold text-slate-900 text-sm">{{ job.order_id }}</p>
                                <span class="rounded-lg bg-teal-100 px-2 py-0.5 text-[10px] font-bold uppercase text-teal-800">
                                    Ready for Pickup
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-slate-600">
                                <strong>Collection:</strong> {{ job.collection_address || 'Seller Store' }}
                            </p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                <strong>Destination:</strong> {{ job.delivery_address }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 rounded-xl bg-[#087F8C] px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-[#065A63] disabled:opacity-50 cursor-pointer"
                            :disabled="acceptingParcelId === job.id"
                            @click="acceptPickupJob(job.id)"
                        >
                            {{ acceptingParcelId === job.id ? 'Accepting…' : 'Accept Pickup' }}
                        </button>
                    </li>
                </ul>
            </section>

            <!-- ACCEPTED PICKUPS -->
            <section v-if="acceptedPickups.length" class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Accepted Pickups</h2>
                        <p class="text-xs text-slate-500">Pickups claimed by you awaiting collection or hub arrival.</p>
                    </div>
                    <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800">
                        {{ acceptedPickups.length }} active
                    </span>
                </div>
                <ul class="mt-4 divide-y divide-slate-100">
                    <li v-for="pickup in acceptedPickups" :key="pickup.id" class="py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div>
                            <p class="font-bold text-slate-900 text-sm">{{ pickup.order_id }} · <span class="text-xs font-semibold text-teal-700">{{ pickup.status }}</span></p>
                            <p class="text-xs text-slate-500">{{ pickup.collection_address }} → {{ pickup.delivery_address }}</p>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 rounded-lg border border-teal-600 bg-teal-50 px-3 py-1.5 text-xs font-bold text-teal-700 hover:bg-teal-100 cursor-pointer"
                            @click="quickScanItem(pickup.order_id)"
                        >
                            📷 Scan Pickup
                        </button>
                    </li>
                </ul>
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

            <!-- COD RECORDS & NOTIFICATIONS -->
            <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-base font-bold text-slate-900">COD Collection Records</h2>
                    <p v-if="!codRecords.length" class="mt-4 text-xs text-slate-500">No COD collections recorded yet.</p>
                    <ul v-else class="mt-3 divide-y divide-slate-100 text-xs">
                        <li v-for="cod in codRecords" :key="cod.id" class="py-2.5 flex items-center justify-between">
                            <div>
                                <span class="font-bold text-slate-800">Order #{{ cod.order_id || cod.parcel_id }}</span>
                                <span class="ml-2 text-slate-500 capitalize">{{ cod.status || 'collected' }}</span>
                            </div>
                            <span class="font-bold text-[#087F8C]">{{ formatMoney(cod.amount || cod.amount_collected) }}</span>
                        </li>
                    </ul>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-base font-bold text-slate-900">Recent Notifications</h2>
                    <p v-if="!notifications.length" class="mt-4 text-xs text-slate-500">No notifications yet.</p>
                    <ul v-else class="mt-3 divide-y divide-slate-100 text-xs">
                        <li v-for="notif in notifications" :key="notif.id" class="py-2.5">
                            <p class="font-bold text-slate-800">{{ notif.title }}</p>
                            <p class="mt-0.5 text-slate-600">{{ notif.body || notif.message }}</p>
                        </li>
                    </ul>
                </section>
            </div>
        </main>
    </GuestLayout>
</template>
