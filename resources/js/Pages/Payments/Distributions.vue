<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({ role: String, summary: Object, completed_deliveries: Number, distributions: Array,
    page: Number, pages: Number, settlement_account: String, rules: Array, costs: Array, cod: Object });
const admin = computed(() => props.role === 'admin');
const roleTitle = computed(() => ({ admin: 'Platform', seller: 'Seller', sorting_center: 'Logistics', rider: 'Rider' }[props.role]));
const pageState = usePage();
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
const rule = useForm({ shipping_method: 'standard', available: true, block_shortfall: false,
    logistics_cost_cents: 0, logistics_margin_cents: 0, rider_cost_cents: 0, rider_minimum_cents: 0, blocked_zones: [] });
const blockedZones = ref('');
const loadRule = () => {
    const saved = props.rules.find(r => r.shipping_method === rule.shipping_method);
    for (const field of ['logistics_cost_cents', 'logistics_margin_cents', 'rider_cost_cents', 'rider_minimum_cents']) rule[field] = saved?.[field] ?? 0;
    rule.available = saved ? Boolean(saved.available) : true;
    rule.block_shortfall = Boolean(saved?.block_shortfall);
    blockedZones.value = saved?.blocked_zones ? (typeof saved.blocked_zones === 'string' ? JSON.parse(saved.blocked_zones) : saved.blocked_zones).join('\n') : '';
};
loadRule();
const cost = useForm({ product_id: '', cost_cents: 0, minimum_margin_cents: 0, block_shortfall: false });
</script>

<template>
    <Head title="Payment Distribution" />
    <main class="min-h-screen bg-slate-50 text-slate-800">
        <header class="border-b border-teal-900/10 bg-white px-5 py-6">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4">
                <div><Link :href="home" class="text-sm font-medium text-teal-700">← Back to dashboard</Link>
                    <h1 class="mt-3 text-2xl font-bold">Payment distribution</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ roleTitle }} account · PHP · New settlement policy</p></div>
                <div class="rounded-xl bg-teal-50 px-4 py-3 text-sm text-teal-900">Product: 90% Seller / 10% Alona<br>Shipping: 70% Rider / 20% Logistics / 10% Alona</div>
            </div>
        </header>
        <div class="mx-auto max-w-7xl space-y-6 px-5 py-6">
            <p v-if="pageState.props.flash?.status" role="status" class="rounded-xl border border-teal-200 bg-teal-50 p-4">{{ pageState.props.flash.status }}</p>
            <p class="text-sm text-slate-600">Allocations become available after verified delivery, the settlement waiting period, cleared disputes, and reconciled funding. Completed payouts require a confirmed transfer. Historical orders remain in their existing reports.</p>
            <section aria-label="Financial overview" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <article v-for="[field, label] in metrics" :key="field" class="rounded-xl border border-slate-200 bg-white p-5">
                    <p class="text-sm text-slate-500">{{ label }}</p><p class="mt-2 text-2xl font-semibold tabular-nums">{{ currency(summary[field]) }}</p>
                </article>
            </section>
            <p v-if="role === 'rider' || role === 'sorting_center'" class="text-sm">Completed deliveries: <strong>{{ completed_deliveries }}</strong>. COD cash is separate from earnings.</p>
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="border-b p-5"><h2 class="font-semibold">Orders and settlements</h2><p class="mt-1 text-sm text-slate-500">Review allocations, holds, payout history and adjustments.</p></div>
                <p v-if="!distributions.length" class="p-10 text-center text-slate-500">No orders under this settlement policy yet.</p>
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
            <section v-if="admin" class="rounded-xl border bg-white p-5">
                <h2 class="font-semibold">Delivery profitability controls</h2><p class="mt-1 text-sm text-slate-500">Costs and minimums are per seller shipment, in centavos (100 = ₱1). Shares stay fixed. Zone names are matched against the delivery address.</p>
                <form class="mt-4 grid gap-4 md:grid-cols-3" @submit.prevent="rule.blocked_zones = blockedZones.split('\n').map(z => z.trim()).filter(Boolean); rule.post('/payments/profitability', { preserveScroll: true })">
                    <label>Delivery option<select v-model="rule.shipping_method" @change="loadRule"><option>standard</option><option>express</option><option>pickup</option></select></label>
                    <label v-for="[field, label] in [['logistics_cost_cents', 'Logistics operating cost'], ['logistics_margin_cents', 'Logistics minimum margin'], ['rider_cost_cents', 'Rider delivery cost'], ['rider_minimum_cents', 'Rider minimum net earnings']]" :key="field">{{ label }}<input v-model.number="rule[field]" type="number" min="0" step="1" required></label>
                    <label>Unavailable zones (one per line)<textarea v-model="blockedZones" rows="3"></textarea></label>
                    <label class="check"><input v-model="rule.available" type="checkbox"> Delivery option available</label><label class="check"><input v-model="rule.block_shortfall" type="checkbox"> Block orders below minimums</label>
                    <div><button class="action" :disabled="rule.processing">Save controls</button><Link href="/admin/shipping-rates" class="ml-3 text-sm text-teal-700">Delivery zones and fees</Link></div><p v-for="error in rule.errors" :key="error" role="alert" class="text-red-700">{{ error }}</p>
                </form>
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
</template>

<style scoped>
label { display: block; font-size: .875rem; font-weight: 500; }
input:not([type=checkbox]), select, textarea { display: block; width: 100%; margin-top: .35rem; border: 1px solid #cbd5e1; border-radius: .5rem; padding: .6rem .75rem; font-size: .875rem; }
.check { display: flex; align-items: center; gap: .5rem; }
.action { display: inline-block; border-radius: .5rem; background: #0f766e; color: white; padding: .65rem 1rem; font-size: .875rem; font-weight: 600; }
.action:disabled { opacity: .5; cursor: wait; }
th, td { padding-right: 1rem; }
</style>
