<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ role: String, summary: Object, completed_deliveries: Number, distributions: Array,
    page: Number, pages: Number, settlement_account: String, rules: Array, costs: Array, cod: Object });
const admin = computed(() => props.role === 'admin');
const roleTitle = computed(() => ({ admin: 'Platform', seller: 'Seller', sorting_center: 'Logistics', rider: 'Rider' }[props.role]));
const pageState = usePage();
const refreshing = ref(false);
const refresh = () => {
    if (refreshing.value) return;
    refreshing.value = true;
    router.reload({ onFinish: () => { refreshing.value = false; } });
};
const metricTone = (field) => {
    if (['paid', 'reconciled', 'recognized_platform_revenue'].includes(field)) return 'emerald';
    if (['pending', 'discounts'].includes(field)) return 'amber';
    if (['collected', 'reserved'].includes(field)) return 'blue';
    return 'teal';
};
const home = computed(() => ({ admin: '/admin/dashboard', seller: '/seller/dashboard', sorting_center: '/sorting-center/dashboard', rider: '/' }[props.role]));
const metrics = computed(() => {
    const common = [['allocation', 'Allocated earnings'], ['pending', 'Pending earnings'], ['available', 'Available balance'], ['reserved', 'Reserved payouts'], ['paid', 'Completed payouts'], ['reversals', 'Reversed allocations']];
    if (admin.value) return [['product_sales', 'Product sales'], ['shipping_fees', 'Shipping fees'], ['collected', 'Buyer payments collected'], ['reconciled', 'Funds reconciled'], ['product_commission', 'Product commission · 10%'], ['shipping_commission', 'Shipping commission · 10%'], ['platform_revenue', 'Platform allocation'], ['recognized_platform_revenue', 'Recognized platform revenue'], ['pending', 'Pending settlements'], ['paid', 'Completed payouts'], ['refunds', 'Reconciled refunds'], ['gateway_fees', 'Gateway fees'], ['discounts', 'Discount funding required']];
    if (props.role === 'seller') return [['product_sales', 'Product sales'], ['product_commission', 'Alona commission · 10%'], ...common];
    if (props.role === 'rider') return [...common, ['standard_earnings', 'Standard delivery earnings'], ['express_earnings', 'Express delivery earnings'], ['cod_obligation', 'Cash to remit']];
    return [['shipping_fees', 'Shipping fees'], ['shipping_commission', 'Alona platform fee · 10%'], ...common, ['cod_obligation', 'Pending COD remittances']];
});
const currency = (value) => `₱${String(value ?? '0.00').replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`;
const labels = { seller: 'Seller · 90%', platform_product: 'Alona product · 10%', rider: 'Rider · 70%', logistics: 'Logistics · 20%', platform_shipping: 'Alona shipping · 10%' };
const expanded = ref(null);
const panel = ref(null);
const endpoint = ref('');
const key = () => crypto.randomUUID();
const form = useForm({});
const open = (type, id, line = null) => {
    form.clearErrors();
    const common = { request_key: key() };
    let data;
    if (type === 'receipt') {
        endpoint.value = `/payments/distributions/${id}/receipts`;
        data = { ...common, kind: 'online', amount: '', account: props.settlement_account || '', reference: '', evidence: '', gateway_fee: '0.00', fee_bearer: 'platform', funder: '', collection_id: null, remittance_id: null, matching_id: null };
    } else if (type === 'match') {
        endpoint.value = `/payments/distributions/${id}/cod-matches`;
        data = { ...common, amount: '', collection_id: null, remittance_id: null, evidence: '' };
    } else if (type === 'reserve') {
        endpoint.value = `/payments/distributions/${id}/payouts`;
        data = { ...common, line_id: line.id, destination: { method: 'bank', account_name: '', account_number: '', bank: '' } };
    } else if (type === 'payout') {
        endpoint.value = `/payments/payouts/${id}/outcome`;
        data = { ...common, status: 'paid', reference: '', evidence: '' };
    } else {
        endpoint.value = `/payments/distributions/${id}/refunds`;
        data = { ...common, refund_id: null, item_id: null, component: 'product', liability: 'seller', amount: '', reference: '', evidence: '' };
    }
    // Clear previous action fields; retain the same request key when retrying an uncertain response.
    form.defaults(data); form.reset();
    panel.value = { type, line };
};
const submit = () => form.transform((data) => {
    const keys = panel.value.type === 'reserve' ? ['request_key', 'line_id', 'destination']
        : panel.value.type === 'receipt' ? ['request_key', 'kind', 'amount', 'account', 'reference', 'evidence', 'gateway_fee', 'fee_bearer', 'funder', 'collection_id', 'remittance_id', 'matching_id']
        : panel.value.type === 'match' ? ['request_key', 'amount', 'collection_id', 'remittance_id', 'evidence']
        : panel.value.type === 'payout' ? ['request_key', 'status', 'reference', 'evidence']
        : ['request_key', 'refund_id', 'item_id', 'component', 'liability', 'amount', 'reference', 'evidence'];
    return Object.fromEntries(keys.map(k => [k, data[k]]));
}).post(endpoint.value, { preserveScroll: true, onSuccess: () => { panel.value = null; } });
const cost = useForm({ product_id: '', cost_cents: 0, minimum_margin_cents: 0, block_shortfall: false });
</script>

<template>
    <Head :title="admin ? 'Payments - Settlements & Payouts' : 'Payment Distribution'" />
    <component :is="admin ? AdminLayout : 'div'" :active="admin ? 'payments' : undefined">
    <main :class="admin ? 'settlement-admin w-full space-y-6 text-slate-800' : 'min-h-screen bg-slate-50 text-slate-800'">
        <header v-if="admin" class="flex flex-col gap-4 border-b border-[#E5E7EB] pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div class="flex items-center gap-2 text-[11px] font-bold uppercase tracking-wider text-[#087F8C]">
                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                    <span>Financial Administration</span>
                </div>
                <h1 class="mt-1 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">Payments</h1>
                <p class="mt-0.5 text-xs text-slate-500">Review allocations, reconcile funds, and process payouts for new-policy orders.</p>
            </div>
            <button type="button" :disabled="refreshing" @click="refresh" class="inline-flex items-center gap-2 rounded-xl border border-[#E5E7EB] bg-white px-3.5 py-2 text-xs font-semibold text-[#64748B] shadow-xs transition hover:border-[#087F8C] hover:bg-[#E8F7F6] hover:text-[#087F8C] disabled:opacity-50">
                <svg class="h-3.5 w-3.5" :class="{ 'animate-spin': refreshing }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                {{ refreshing ? 'Refreshing…' : 'Refresh List' }}
            </button>
        </header>
        <header v-else class="border-b border-teal-900/10 bg-white px-5 py-6">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4">
                <div><Link v-if="!admin" :href="home" class="text-sm font-medium text-teal-700">← Back to dashboard</Link>
                    <h1 class="mt-3 text-2xl font-bold">{{ admin ? 'Payments' : 'Payment distribution' }}</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ roleTitle }} account · PHP · New settlement policy</p></div>
                <div class="rounded-xl bg-teal-50 px-4 py-3 text-sm text-teal-900">Product: 90% Seller / 10% Alona<br>Shipping: 70% Rider / 20% Logistics / 10% Alona</div>
            </div>
        </header>
        <div :class="admin ? 'w-full space-y-6' : 'mx-auto max-w-7xl space-y-6 px-5 py-6'">
            <h2 v-if="admin" class="text-xl font-semibold">Settlements &amp; Payouts</h2>
            <p v-if="pageState.props.flash?.status" role="status" class="rounded-xl border border-teal-200 bg-teal-50 p-4">{{ pageState.props.flash.status }}</p>
            <section aria-label="Financial overview" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <article v-for="[field, label] in metrics" :key="field" class="metric-card rounded-2xl border border-[#E5E7EB] bg-white p-5 shadow-xs transition hover:shadow-sm" :data-tone="metricTone(field)">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-wider text-[#64748B]">{{ label }}</p>
                            <p class="metric-value mt-2 break-words text-2xl font-black tabular-nums">{{ currency(summary[field]) }}</p>
                        </div>
                        <span v-if="admin" class="metric-icon flex h-10 w-10 shrink-0 items-center justify-center rounded-xl">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <template v-if="metricTone(field) === 'emerald'"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" stroke-linejoin="round" d="m8 12 3 3 5-6" /></template>
                                <template v-else-if="metricTone(field) === 'amber'"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="M12 7v5l3 2" /></template>
                                <template v-else><rect x="3" y="5" width="18" height="14" rx="3" /><path stroke-linecap="round" d="M3 10h18M7 15h3" /></template>
                            </svg>
                        </span>
                    </div>
                </article>
            </section>
            <div class="rounded-2xl border border-[#E5E7EB] bg-white p-5 text-xs leading-relaxed text-slate-500">
                <p v-if="admin" class="mb-2 font-semibold text-[#087F8C]">Product: 90% Seller / 10% Alona · Shipping: 70% Rider / 20% Logistics / 10% Alona</p>
                <p>Allocations become available after verified delivery, the settlement waiting period, cleared disputes, and reconciled funding. Completed payouts require a confirmed transfer. Historical orders remain in their existing reports.</p>
            </div>
            <p v-if="role === 'rider' || role === 'sorting_center'" class="text-sm">Completed deliveries: <strong>{{ completed_deliveries }}</strong>. COD cash is separate from earnings.</p>
            <section class="overflow-hidden rounded-2xl border border-[#E5E7EB] bg-white shadow-xs">
                <div class="border-b border-[#E5E7EB] p-5"><h2 class="font-semibold text-slate-900">Orders and settlements</h2><p class="mt-1 text-xs text-slate-500">Review allocations, holds, payout history and adjustments.</p></div>
                <div v-if="!distributions.length" class="flex flex-col items-center px-5 py-14 text-center">
                    <span class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400"><svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3" /><path stroke-linecap="round" d="M3 10h18M7 15h3" /></svg></span>
                    <h3 class="text-sm font-semibold text-slate-900">No settlements found</h3>
                    <p class="mt-1 text-xs text-slate-500">There are no orders under this settlement policy yet.</p>
                </div>
                <article v-for="order in distributions" :key="order.id" class="border-b last:border-0">
                    <button type="button" class="flex w-full flex-wrap items-center justify-between gap-3 p-5 text-left hover:bg-slate-50" :aria-expanded="expanded === order.id" @click="expanded = expanded === order.id ? null : order.id">
                        <div class="font-semibold">{{ order.order_number }}<span class="ml-3 text-xs font-normal text-slate-500">{{ order.shipping_method }} · {{ order.order_status }}</span></div>
                        <span class="rounded-full px-3 py-1 text-xs" :class="order.holds.length ? 'bg-amber-50 text-amber-800' : 'bg-teal-50 text-teal-800'">{{ order.holds.length ? `${order.holds.length} settlement holds` : 'Funding and delivery checks passed' }}</span>
                    </button>
                    <div v-if="expanded === order.id" class="space-y-4 px-5 pb-6">
                        <p v-if="order.cod_status" class="text-sm font-medium">COD status: {{ order.cod_status }}</p>
                        <button v-if="admin && order.cod_status" class="action" @click="open('match', order.id)">Match confirmed COD remittance</button>
                        <ul v-if="order.holds.length" class="list-inside list-disc rounded-lg bg-amber-50 p-4 text-sm text-amber-900"><li v-for="hold in order.holds" :key="hold">{{ hold }}</li></ul>
                        <p v-for="warning in order.warnings" :key="warning" class="text-sm text-amber-800">{{ warning }}</p>
                        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="border-b text-slate-500"><tr><th class="py-3">Participant</th><th>Allocation</th><th>Reversed</th><th>Available</th><th>Paid</th><th></th></tr></thead>
                            <tbody><template v-for="line in order.lines" :key="line.id"><tr class="border-b"><td class="py-3">{{ labels[line.role] }}<small v-if="!line.beneficiary_id && ['rider', 'logistics'].includes(line.role)" class="block text-amber-700">Delivery beneficiary pending verification</small></td><td>{{ currency(line.allocation) }}</td><td>{{ currency(line.reversed) }}</td><td>{{ currency(line.available) }}</td><td>{{ currency(line.paid) }}</td><td><button v-if="line.can_request" class="font-medium text-teal-700" @click="open('reserve', order.id, line)">Request payout</button></td></tr>
                                <tr v-for="payout in line.payouts" :key="`p${payout.id}`"><td colspan="6" class="bg-slate-50 px-3 py-2 text-xs">Payout #{{ payout.id }} · {{ currency(payout.amount) }} · {{ payout.status }} <span v-if="payout.reference">· {{ payout.reference }}</span><span v-if="admin && payout.destination"> · {{ payout.destination.bank }} / {{ payout.destination.account_name }} / {{ payout.destination.account_number }}</span><button v-if="admin && ['reserved', 'reconciling'].includes(payout.status)" class="ml-3 font-medium text-teal-700" @click="open('payout', payout.id)">Record transfer outcome</button></td></tr>
                            </template></tbody></table></div>
                        <div v-if="admin" class="flex flex-wrap gap-3"><button class="action" @click="open('receipt', order.id)">Reconcile receipt</button><button class="action" @click="open('refund', order.id)">Reconcile refund</button><span class="self-center text-sm text-slate-500">Funded {{ currency(order.reconciled) }} · Refunded {{ currency(order.refunds) }} · Discounts {{ currency(order.discounts) }}</span></div>
                        <details v-if="admin" class="text-sm"><summary class="cursor-pointer text-teal-700">Collection, remittance and refund records</summary><div class="mt-3 grid gap-3 md:grid-cols-3"><div v-for="(records, name) in order.sources" :key="name"><h3 class="font-semibold capitalize">{{ name }}</h3><p v-if="!records.length">None</p><pre v-for="record in records" :key="record.id" class="mt-2 whitespace-pre-wrap rounded bg-slate-50 p-2 text-xs">{{ JSON.stringify(record, null, 2) }}</pre></div></div></details>
                        <details class="text-sm"><summary class="cursor-pointer text-teal-700">Transaction and adjustment history</summary><ol class="mt-3 space-y-2"><li v-for="event in order.events" :key="event.id" class="rounded bg-slate-50 p-3">{{ event.at }} · {{ event.kind.replaceAll('_', ' ') }} · {{ currency(event.amount) }}<pre v-if="admin" class="mt-1 whitespace-pre-wrap text-xs text-slate-500">{{ JSON.stringify(event.details, null, 2) }}</pre></li></ol></details>
                    </div>
                </article>
                <nav v-if="pages > 1" class="flex justify-between p-4" aria-label="Pagination"><Link v-if="page > 1" :href="`?page=${page - 1}`">Previous</Link><span>{{ page }} / {{ pages }}</span><Link v-if="page < pages" :href="`?page=${page + 1}`">Next</Link></nav>
            </section>

            <section v-if="role === 'seller'" class="rounded-xl border bg-white p-5"><h2 class="font-semibold">Declared product costs</h2><p class="text-sm text-slate-500">Validate your selling price after the 10% commission. Enter costs in centavos.</p><form class="mt-4 grid gap-4 sm:grid-cols-3" @submit.prevent="cost.post('/payments/product-costs', { preserveScroll: true })"><label>Product ID<input v-model="cost.product_id" type="number" min="1" required></label><label>Unit cost (centavos)<input v-model.number="cost.cost_cents" type="number" min="0" required></label><label>Minimum margin (centavos)<input v-model.number="cost.minimum_margin_cents" type="number" min="0" required></label><label class="check"><input v-model="cost.block_shortfall" type="checkbox"> Block sales below minimum</label><button class="action" :disabled="cost.processing">Save declared cost</button><p v-for="error in cost.errors" :key="error" role="alert" class="text-red-700">{{ error }}</p></form><p v-for="entry in costs" :key="entry.id" class="mt-3 text-sm">Product {{ entry.product_id }} · Cost {{ entry.cost_cents }} centavos · Margin {{ entry.minimum_margin_cents }} centavos · {{ entry.block_shortfall ? 'Block shortfall' : 'Warn on shortfall' }}</p></section>
        </div>
        <div v-if="panel" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/50 p-4" @click.self="panel = null">
            <section role="dialog" aria-modal="true" aria-labelledby="action-title" class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl">
                <div class="flex justify-between"><h2 id="action-title" class="text-xl font-semibold">{{ { receipt: 'Reconcile actual funds', match: 'Match COD remittance', reserve: 'Request payout', payout: 'Record transfer outcome', refund: 'Reconcile buyer refund' }[panel.type] }}</h2><button aria-label="Close" @click="panel = null">✕</button></div>
                <p class="mt-2 text-sm text-slate-500">{{ panel.type === 'match' ? 'Match confirmed hub cash to its buyer collection. Platform funding is reconciled separately.' : panel.type === 'payout' ? 'Record a verified transfer result. For an uncertain result, choose Reconciling. Cancel only with evidence of nonpayment.' : panel.type === 'receipt' ? 'Confirm actual receipt in the platform account. Hub receipt alone does not establish platform funding.' : panel.type === 'refund' ? 'Reference an existing completed refund and the investigation evidence. Rider earnings are preserved.' : 'Provide the actual receiving account. This reserves funds for processing; it does not send money.' }}</p>
                <form class="mt-5 space-y-4" @submit.prevent="submit">
                    <template v-if="panel.type === 'reserve'"><label>Channel<select v-model="form.destination.method"><option value="bank">Bank</option><option value="gcash">GCash</option><option value="maya">Maya</option></select></label><label>Account holder<input v-model="form.destination.account_name" required></label><label>Account number<input v-model="form.destination.account_number" required autocomplete="off"></label><label>Bank or wallet provider<input v-model="form.destination.bank" required></label></template>
                    <template v-if="panel.type === 'receipt'"><label>Receipt type<select v-model="form.kind"><option value="online">Online settlement</option><option value="cod">COD remittance</option><option value="subsidy">Funded discount, fee or loss</option></select></label><label>Platform settlement account<input v-model="form.account" required></label><template v-if="form.kind === 'cod'"><label>Collection ID<input v-model.number="form.collection_id" type="number" required></label><label>Confirmed remittance ID<input v-model.number="form.remittance_id" type="number" required></label></template><label v-if="form.kind === 'subsidy'">Funder<input v-model="form.funder" required></label><label v-if="form.kind === 'online'">Gateway fee (PHP)<input v-model="form.gateway_fee" inputmode="decimal" required></label></template>
                    <template v-if="panel.type === 'refund'"><label>Completed refund ledger ID<input v-model.number="form.refund_id" type="number" required></label><label>Component<select v-model="form.component"><option value="product">Product</option><option value="shipping">Shipping</option></select></label><label v-if="form.component === 'product'">Order item ID<input v-model.number="form.item_id" type="number" required></label><label>Liable party after investigation<select v-model="form.liability"><option value="seller">Seller</option><option value="logistics">Logistics</option><option value="platform">Alona</option></select></label></template>
                    <template v-if="panel.type === 'match'"><label>Collection ID<input v-model.number="form.collection_id" type="number" required></label><label>Confirmed remittance ID<input v-model.number="form.remittance_id" type="number" required></label><p class="text-sm text-slate-500">Match confirmed hub cash to this order. This does not make funds available for payouts.</p></template>
                    <label v-if="panel.type === 'receipt' && form.kind === 'cod'">COD matching event ID<input v-model.number="form.matching_id" type="number" required></label>
                    <label v-if="['receipt', 'refund', 'match'].includes(panel.type)">Amount (PHP)<input v-model="form.amount" inputmode="decimal" placeholder="0.00" required></label>
                    <label v-if="panel.type === 'payout'">Outcome<select v-model="form.status"><option value="paid">Paid — verified transfer</option><option value="reconciling">Reconciling — result uncertain</option><option value="cancelled">Cancelled — confirmed nonpayment</option></select></label>
                    <template v-if="panel.type !== 'reserve'"><label v-if="panel.type !== 'match'">Unique transaction / allocation reference<input v-model="form.reference" minlength="3" required></label><label>Evidence record reference and verification notes<textarea v-model="form.evidence" minlength="8" rows="3" required></textarea></label></template>
                    <p v-for="error in form.errors" :key="error" role="alert" class="text-sm text-red-700">{{ error }}</p>
                    <button class="action w-full" :disabled="form.processing">{{ form.processing ? 'Saving…' : 'Save record' }}</button>
                </form>
            </section>
        </div>
    </main>
    </component>
</template>

<style scoped>
label { display: block; font-size: .875rem; font-weight: 500; }
input:not([type=checkbox]), select, textarea { display: block; width: 100%; margin-top: .35rem; border: 1px solid #cbd5e1; border-radius: .5rem; padding: .6rem .75rem; font-size: .875rem; }
.check { display: flex; align-items: center; gap: .5rem; }
.action { display: inline-block; border-radius: .5rem; background: #0f766e; color: white; padding: .65rem 1rem; font-size: .875rem; font-weight: 600; }
.action:disabled { opacity: .5; cursor: wait; }
th, td { padding-right: 1rem; }
.settlement-admin .metric-value { color: #087f8c; }
.settlement-admin .metric-icon { background: #e8f7f6; color: #087f8c; }
.settlement-admin [data-tone=emerald] .metric-value { color: #059669; }
.settlement-admin [data-tone=emerald] .metric-icon { background: #ecfdf5; color: #059669; }
.settlement-admin [data-tone=amber] .metric-value { color: #d97706; }
.settlement-admin [data-tone=amber] .metric-icon { background: #fffbeb; color: #d97706; }
.settlement-admin [data-tone=blue] .metric-value { color: #2563eb; }
.settlement-admin [data-tone=blue] .metric-icon { background: #eff6ff; color: #2563eb; }
.settlement-admin .action { background: #087f8c; border-radius: .75rem; font-size: .75rem; transition: background .15s; }
.settlement-admin .action:hover { background: #066775; }
.settlement-admin input:not([type=checkbox]), .settlement-admin select, .settlement-admin textarea { border-color: #e5e7eb; border-radius: .75rem; background: #f8fafc; }
.settlement-admin input:focus, .settlement-admin select:focus, .settlement-admin textarea:focus { outline: 2px solid #087f8c; outline-offset: 2px; }
.settlement-admin thead { background: #f8fafc; font-size: .625rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; }
.settlement-admin th, .settlement-admin td { padding: .875rem 1rem; }
.settlement-admin .action:focus-visible { outline: 2px solid #087f8c; outline-offset: 3px; }
</style>
