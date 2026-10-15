<script setup>
import { computed, ref, onMounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { generateBarcodeSvg } from '@/Utils/barcode'

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
    initialTab: {
        type: String,
        default: 'waybill', // 'waybill' | 'packing_slip' | 'combined'
    },
})

const activeTab = ref(props.initialTab || 'waybill')
const paperSize = ref('thermal') // 'thermal' (4x6) | 'a4'

const item = computed(() => props.item)
const order = computed(() => props.item?.order ?? {})
const product = computed(() => props.item?.product ?? {})
const seller = computed(() => props.item?.product?.seller ?? {})
const buyer = computed(() => props.item?.order?.user ?? {})

const trackingNumber = computed(() => {
    return item.value?.tracking_number || item.value?.parcel?.order_id || `ITEM-${item.value?.id}`
})

const orderNumber = computed(() => {
    return order.value?.order_number || `ORD-${order.value?.id || '000'}`
})

const isCod = computed(() => {
    const pm = String(order.value?.payment_method ?? '').toLowerCase()
    return pm.includes('cod') || pm.includes('cash')
})

const paymentLabel = computed(() => {
    if (isCod.value) return 'CASH ON DELIVERY (COD)'
    const pm = order.value?.payment_method
    if (!pm) return 'PAID (NON-COD)'
    return `PAID - ${pm.toUpperCase()}`
})

const codAmount = computed(() => {
    if (!isCod.value) return 0
    return Number(item.value?.price || 0) * Number(item.value?.quantity || 1)
})

const barcodeData = computed(() => {
    return generateBarcodeSvg(trackingNumber.value, {
        height: 52,
        moduleWidth: 2,
        quietZone: 8,
    })
})

const orderBarcodeData = computed(() => {
    return generateBarcodeSvg(orderNumber.value, {
        height: 38,
        moduleWidth: 1.6,
        quietZone: 6,
    })
})

const variantLabel = computed(() => {
    if (item.value?.variant_label) return item.value.variant_label
    const v = item.value?.variant
    if (!v) return null
    return [v.color, v.size].filter(Boolean).join(' / ')
})

const sellerAddress = computed(() => {
    return seller.value?.address || [
        seller.value?.street_address,
        seller.value?.barangay,
        seller.value?.municipality,
        seller.value?.province,
    ].filter(Boolean).join(', ') || 'Seller Origin Warehouse'
})

const buyerAddress = computed(() => {
    return order.value?.shipping_address || [
        buyer.value?.street_address,
        buyer.value?.barangay,
        buyer.value?.municipality,
        buyer.value?.province,
    ].filter(Boolean).join(', ') || 'Customer Delivery Address'
})

const destinationHub = computed(() => {
    const addr = buyerAddress.value.toUpperCase()
    if (addr.includes('MANILA') || addr.includes('NCR') || addr.includes('QUEZON') || addr.includes('MAKATI')) {
        return 'HUB-NCR-01'
    }
    if (addr.includes('CEBU') || addr.includes('BOHOL') || addr.includes('ILOILO')) {
        return 'HUB-VIS-02'
    }
    if (addr.includes('DAVAO') || addr.includes('CAGAYAN')) {
        return 'HUB-MIN-03'
    }
    return 'HUB-STD-01'
})

const formatDate = (date) => {
    if (!date) return '—'
    const d = new Date(date)
    return d.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

const printDocument = () => {
    window.print()
}

onMounted(() => {
    // If URL has ?autoPrint=1, trigger print automatically
    const urlParams = new URLSearchParams(window.location.search)
    if (urlParams.get('print') === '1') {
        setTimeout(() => {
            window.print()
        }, 500)
    }
})
</script>

<template>
    <Head :title="`Shipping Documents - Order #${orderNumber}`" />

    <div class="min-h-screen bg-slate-100 text-slate-900 font-sans print:bg-white print:m-0 print:p-0">
        <!-- TOP TOOLBAR (Hidden in Print) -->
        <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur-md px-4 py-3 shadow-xs print:hidden">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('seller.order.details', item.id)"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.56l3.22 3.22a.75.75 0 1 1-1.06 1.06l-4.5-4.5a.75.75 0 0 1 0-1.06l4.5-4.5a.75.75 0 0 1 1.06 1.06l-3.22 3.22h10.69A.75.75 0 0 1 17 10Z" clip-rule="evenodd" />
                        </svg>
                        Back to Order
                    </Link>

                    <div class="hidden sm:block h-5 w-px bg-slate-200"></div>

                    <h1 class="text-sm font-bold text-slate-800">
                        Shipping Documents <span class="text-xs font-normal text-slate-500">#{{ orderNumber }}</span>
                    </h1>
                </div>

                <!-- Tabs -->
                <div class="flex items-center rounded-xl bg-slate-100 p-1">
                    <button
                        type="button"
                        @click="activeTab = 'waybill'"
                        class="rounded-lg px-3 py-1 text-xs font-bold transition"
                        :class="activeTab === 'waybill' ? 'bg-white text-[#087F8C] shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                    >
                        📋 Waybill
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'packing_slip'"
                        class="rounded-lg px-3 py-1 text-xs font-bold transition"
                        :class="activeTab === 'packing_slip' ? 'bg-white text-[#087F8C] shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                    >
                        📦 Packing Slip
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'combined'"
                        class="rounded-lg px-3 py-1 text-xs font-bold transition"
                        :class="activeTab === 'combined' ? 'bg-white text-[#087F8C] shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                    >
                        📄 Combined Both
                    </button>
                </div>

                <!-- Print Options & Action -->
                <div class="flex items-center gap-2">
                    <div class="hidden md:flex items-center rounded-lg border border-slate-200 bg-white p-0.5 text-[11px]">
                        <button
                            type="button"
                            @click="paperSize = 'thermal'"
                            class="rounded px-2.5 py-1 font-semibold transition"
                            :class="paperSize === 'thermal' ? 'bg-slate-800 text-white' : 'text-slate-600 hover:text-slate-900'"
                        >
                            4" × 6" Thermal
                        </button>
                        <button
                            type="button"
                            @click="paperSize = 'a4'"
                            class="rounded px-2.5 py-1 font-semibold transition"
                            :class="paperSize === 'a4' ? 'bg-slate-800 text-white' : 'text-slate-600 hover:text-slate-900'"
                        >
                            A4 Paper
                        </button>
                    </div>

                    <button
                        type="button"
                        @click="printDocument"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-[#087F8C] px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-[#066B76] transition cursor-pointer"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24-1.282.68-2.43 1.96-2.43h6.64c1.28 0 2.2 1.148 1.96 2.43a2.986 2.986 0 0 1-2.93 2.421H9.65a2.986 2.986 0 0 1-2.93-2.421Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-8.25A3.375 3.375 0 0 0 4.5 11.625v2.625m15 0a2.25 2.25 0 0 1-2.25 2.25h-.75m3-2.25V9a3 3 0 0 0-3-3h-9a3 3 0 0 0-3 3v5.25m3 2.25h-.75A2.25 2.25 0 0 1 4.5 14.25m3 2.25h9a2.25 2.25 0 0 0 2.25-2.25" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18h12v3a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1v-3Z" />
                        </svg>
                        Print Document
                    </button>
                </div>
            </div>
        </header>

        <!-- PRINT PREVIEW CONTAINER -->
        <main class="mx-auto py-8 px-4 flex justify-center print:p-0 print:m-0 print:w-full print:block">
            <div
                class="transition-all print:w-full print:m-0 print:p-0"
                :class="[
                    paperSize === 'thermal' ? 'w-[400px]' : 'w-[800px]',
                    'space-y-6 print:space-y-4'
                ]"
            >
                <!-- ========================================================= -->
                <!-- WAYBILL COMPONENT (SELLER-17)                            -->
                <!-- ========================================================= -->
                <div
                    v-if="activeTab === 'waybill' || activeTab === 'combined'"
                    class="waybill-container bg-white border-2 border-black p-4 text-black shadow-lg print:shadow-none print:border-2 print:border-black print:p-3 print:break-after-page"
                    :class="paperSize === 'thermal' ? 'max-w-[400px]' : 'max-w-[800px]'"
                >
                    <!-- Carrier Header & Hub Routing -->
                    <div class="border-b-2 border-black pb-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="bg-black text-white px-2 py-0.5 text-xs font-black tracking-wider uppercase rounded-xs">
                                    ALONA
                                </div>
                                <span class="text-xs font-bold tracking-tight">LOGISTICS EXPRESS</span>
                            </div>
                            <div class="border border-black px-2 py-0.5 text-xs font-black bg-slate-100">
                                {{ destinationHub }}
                            </div>
                        </div>

                        <!-- Scannable Barcode -->
                        <div class="mt-3 text-center">
                            <div
                                class="barcode-wrapper mx-auto w-full flex justify-center overflow-hidden"
                                v-html="barcodeData.svg"
                            ></div>
                            <div class="mt-1 font-mono text-sm font-black tracking-widest uppercase">
                                {{ trackingNumber }}
                            </div>
                        </div>
                    </div>

                    <!-- Routing Grid: From / To -->
                    <div class="grid grid-cols-2 divide-x-2 border-b-2 border-black text-[11px] leading-tight">
                        <!-- SENDER (From) -->
                        <div class="p-2 space-y-1">
                            <div class="text-[9px] font-black uppercase text-slate-500 tracking-wider">FROM (SENDER):</div>
                            <div class="font-bold truncate text-[12px]">{{ seller?.store_name || seller?.name || 'Seller Store' }}</div>
                            <div class="text-[10px] text-slate-700 leading-3 line-clamp-3">
                                {{ sellerAddress }}
                            </div>
                            <div class="text-[10px] font-semibold">Tel: {{ seller?.phone || seller?.contact_number || 'N/A' }}</div>
                        </div>

                        <!-- RECEIVER (To) -->
                        <div class="p-2 space-y-1">
                            <div class="text-[9px] font-black uppercase text-slate-500 tracking-wider">TO (CONSIGNEE):</div>
                            <div class="font-black truncate text-[13px]">{{ buyer?.name || 'Customer' }}</div>
                            <div class="text-[10px] text-black font-medium leading-3 line-clamp-3">
                                {{ buyerAddress }}
                            </div>
                            <div class="text-[10px] font-bold">Tel: {{ buyer?.phone || buyer?.contact_number || 'N/A' }}</div>
                        </div>
                    </div>

                    <!-- Payment and Collectible Info -->
                    <div class="border-b-2 border-black p-2 flex items-center justify-between">
                        <div>
                            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-500 block">Payment Method:</span>
                            <span class="text-xs font-black uppercase" :class="isCod ? 'text-rose-700' : 'text-emerald-700'">
                                {{ paymentLabel }}
                            </span>
                        </div>
                        <div class="text-right">
                            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-500 block">
                                {{ isCod ? 'COD AMOUNT TO COLLECT' : 'COLLECTIBLE FROM BUYER' }}:
                            </span>
                            <span class="text-base font-black" :class="isCod ? 'text-black font-mono' : 'text-slate-500'">
                                {{ isCod ? `₱${codAmount.toLocaleString('en-PH', { minimumFractionDigits: 2 })}` : '₱0.00' }}
                            </span>
                        </div>
                    </div>

                    <!-- Package & Order Summary Item -->
                    <div class="border-b-2 border-black p-2 text-[11px]">
                        <div class="flex justify-between items-center text-[10px] text-slate-600 mb-1">
                            <span>Order: <strong>#{{ orderNumber }}</strong></span>
                            <span>Date: {{ formatDate(order.created_at) }}</span>
                        </div>
                        <div class="border border-slate-300 rounded p-1.5 bg-slate-50/50">
                            <div class="font-bold text-xs truncate">{{ product?.name || 'Product Item' }}</div>
                            <div class="flex justify-between text-[10px] text-slate-600 mt-0.5">
                                <span v-if="variantLabel">Variant: {{ variantLabel }}</span>
                                <span v-else>Standard Item</span>
                                <span class="font-bold text-black">Qty: {{ item.quantity }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Courier Pickup Info (SELLER-19) -->
                    <div class="border-b-2 border-black p-2 text-[10px] flex justify-between items-center bg-slate-50">
                        <div>
                            <span class="text-slate-500">Carrier:</span>
                            <strong class="ml-1">{{ item.preferred_carrier || item.courier_name || 'Alona Express Fleet' }}</strong>
                        </div>
                        <div v-if="item.scheduled_pickup_date">
                            <span class="text-slate-500">Pickup Date:</span>
                            <strong class="ml-1 font-mono">{{ item.scheduled_pickup_date }}</strong>
                            <span class="ml-1 text-[9px] uppercase font-bold text-slate-600">({{ item.pickup_time_slot || 'Anytime' }})</span>
                        </div>
                    </div>

                    <!-- Signature & Terms Footer -->
                    <div class="p-2 pt-3 grid grid-cols-2 gap-4 text-[9px]">
                        <div class="border-t border-black pt-1 text-center">
                            <span class="text-slate-500">Sender / Dispatch Signature</span>
                        </div>
                        <div class="border-t border-black pt-1 text-center">
                            <span class="text-slate-500">Receiver Signature & Date</span>
                        </div>
                    </div>

                    <div class="text-[8px] text-center text-slate-400 mt-1 uppercase tracking-tighter">
                        Alona Marketplace Logistics Waybill • Thermal Label Format (100x150mm)
                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- PACKING SLIP COMPONENT (SELLER-18)                       -->
                <!-- ========================================================= -->
                <div
                    v-if="activeTab === 'packing_slip' || activeTab === 'combined'"
                    class="packing-slip-container bg-white border border-slate-300 p-6 text-black shadow-lg print:shadow-none print:border-2 print:border-black print:p-4"
                    :class="paperSize === 'thermal' ? 'max-w-[400px]' : 'max-w-[800px]'"
                >
                    <!-- Header -->
                    <div class="flex items-start justify-between border-b-2 border-slate-900 pb-4">
                        <div>
                            <div class="text-xl font-black tracking-tight text-slate-900 uppercase">
                                WAREHOUSE PACKING SLIP
                            </div>
                            <div class="text-xs text-slate-600 font-semibold mt-0.5">
                                {{ seller?.store_name || seller?.name || 'Seller Store' }}
                            </div>
                            <div class="text-[11px] text-slate-500">
                                {{ sellerAddress }}
                            </div>
                        </div>

                        <div class="text-right space-y-1">
                            <div class="text-xs font-bold text-slate-500 uppercase">Order Reference</div>
                            <div class="font-mono text-base font-black text-slate-900">#{{ orderNumber }}</div>
                            <div class="text-[11px] text-slate-500">Packed: {{ formatDate(new Date()) }}</div>
                        </div>
                    </div>

                    <!-- Customer & Shipping Summary -->
                    <div class="grid grid-cols-2 gap-4 py-3 border-b border-slate-200 text-xs">
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Deliver To:</span>
                            <div class="font-bold text-slate-900">{{ buyer?.name || 'Customer' }}</div>
                            <div class="text-[11px] text-slate-600 mt-0.5 leading-relaxed">{{ buyerAddress }}</div>
                            <div class="text-[11px] text-slate-600 mt-1">Tel: {{ buyer?.phone || buyer?.contact_number || 'N/A' }}</div>
                        </div>

                        <div class="space-y-1 text-right">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Fulfillment Details:</span>
                            <div class="text-[11px]"><strong class="text-slate-700">Carrier:</strong> {{ item.preferred_carrier || item.courier_name || 'Alona Express' }}</div>
                            <div class="text-[11px]"><strong class="text-slate-700">Tracking:</strong> <span class="font-mono">{{ trackingNumber }}</span></div>
                            <div class="text-[11px]"><strong class="text-slate-700">Payment:</strong> {{ paymentLabel }}</div>
                            <div v-if="item.scheduled_pickup_date" class="text-[11px]">
                                <strong class="text-slate-700">Pickup:</strong> {{ item.scheduled_pickup_date }} ({{ item.pickup_time_slot }})
                            </div>
                        </div>
                    </div>

                    <!-- Items Checklist Table -->
                    <div class="py-4">
                        <table class="w-full text-left text-xs">
                            <thead class="border-b-2 border-slate-900 text-[10px] font-bold uppercase tracking-wider text-slate-700">
                                <tr>
                                    <th scope="col" class="w-8 py-2 text-center">Check</th>
                                    <th scope="col" class="py-2">Item Description</th>
                                    <th scope="col" class="py-2">SKU / Variant</th>
                                    <th scope="col" class="py-2 text-center">Qty</th>
                                    <th scope="col" class="py-2 text-right">Unit Price</th>
                                    <th scope="col" class="py-2 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <tr>
                                    <td class="py-3 text-center">
                                        <div class="mx-auto h-4 w-4 rounded-xs border-2 border-slate-900"></div>
                                    </td>
                                    <td class="py-3">
                                        <div class="font-bold text-slate-900 text-xs">{{ product?.name || 'Product Title' }}</div>
                                        <div class="text-[10px] text-slate-500">ID: PROD-{{ product?.id }}</div>
                                    </td>
                                    <td class="py-3 text-slate-600">
                                        {{ variantLabel || 'Default Variant' }}
                                    </td>
                                    <td class="py-3 text-center font-bold text-slate-900">
                                        {{ item.quantity }}
                                    </td>
                                    <td class="py-3 text-right font-mono">
                                        ₱{{ Number(item.price || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
                                    </td>
                                    <td class="py-3 text-right font-mono font-bold">
                                        ₱{{ (Number(item.price || 0) * Number(item.quantity || 1)).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Notes & Inspection Sign-off -->
                    <div class="border-t-2 border-slate-900 pt-3">
                        <div class="flex justify-between items-start text-xs">
                            <div class="max-w-md">
                                <span class="text-[10px] font-bold uppercase text-slate-400">Special Instructions / Notes:</span>
                                <p class="text-[11px] text-slate-600 italic mt-0.5">
                                    {{ item.seller_note || item.pickup_notes || 'Verify contents, packaging integrity, and ensure package is safely sealed before dispatch.' }}
                                </p>
                            </div>

                            <div class="space-y-1 text-right text-xs">
                                <div class="flex justify-between gap-6">
                                    <span class="text-slate-500">Item Total:</span>
                                    <span class="font-mono font-bold">₱{{ (Number(item.price || 0) * Number(item.quantity || 1)).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Warehouse Verification Sign-off -->
                        <div class="mt-6 pt-4 border-t border-slate-200 grid grid-cols-2 gap-8 text-[10px]">
                            <div class="border-t border-slate-400 pt-1">
                                <span class="font-semibold text-slate-700">Packed By (Signature / Date):</span>
                            </div>
                            <div class="border-t border-slate-400 pt-1">
                                <span class="font-semibold text-slate-700">Quality Checked By (Signature):</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>

<style>
@media print {
    @page {
        size: auto;
        margin: 5mm;
    }

    body {
        background: white !important;
        margin: 0 !important;
        padding: 0 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .print\\:hidden {
        display: none !important;
    }

    .waybill-container,
    .packing-slip-container {
        box-shadow: none !important;
        page-break-inside: avoid;
    }
}
</style>

