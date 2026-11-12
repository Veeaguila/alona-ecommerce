<?php

namespace App\Http\Controllers;

use App\Models\{PaymentDistribution, PaymentDistributionPayout, Product};
use App\Services\Payments\{DistributionLedger, Money};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PaymentDistributionController extends Controller
{
    public function __construct(private DistributionLedger $ledger) {}

    private function actor(Request $request)
    {
        $actor = $request->user();
        abort_unless($actor && $actor->status === 'approved'
            && in_array($actor->usertype, ['admin', 'seller', 'sorting_center', 'rider'], true), 403);
        if ($actor->usertype === 'sorting_center') abort_unless($actor->sortingCenter?->approval_status === 'approved', 403);
        if ($actor->usertype === 'rider') abort_unless($actor->rider?->approval_status === 'approved' && $actor->rider->is_active, 403);
        abort_unless($this->ledger->installed(), 503, 'Payment distribution schema has not been installed.');
        return $actor;
    }

    public function index(Request $request)
    {
        $actor = $this->actor($request);
        $admin = $actor->usertype === 'admin';
        $query = PaymentDistribution::query();
        if (! $admin) {
            $query->where(function ($q) use ($actor) {
                $q->whereHas('lines', fn ($l) => $l->where('beneficiary_id', $actor->id));
                if (in_array($actor->usertype, ['rider', 'sorting_center'], true)) {
                    $q->orWhereHas('order.items.parcel', function ($p) use ($actor) {
                        $p->where($actor->usertype === 'rider' ? 'assigned_rider_id' : 'sorting_center_id',
                            $actor->usertype === 'rider' ? $actor->rider->id : $actor->sortingCenter->id);
                    });
                }
            });
        }
        $summary = array_fill_keys(['product_sales', 'shipping_fees', 'collected', 'reconciled', 'product_commission',
            'shipping_commission', 'platform_revenue', 'allocation', 'pending', 'available', 'reserved', 'paid',
            'refunds', 'reversals', 'cod_obligation', 'standard_earnings', 'express_earnings', 'gateway_fees', 'discounts', 'recognized_platform_revenue'], 0);
        $rows = [];
        $page = max(1, (int) $request->query('page', 1));
        $count = (clone $query)->count();
        $completedDeliveries = 0;
        foreach ($query->orderByDesc('id')->cursor() as $index => $distribution) {
            $row = $this->ledger->locked($distribution->id, function ($d) use ($actor, $admin, &$summary, &$completedDeliveries) {
                $this->ledger->bindShipping($d);
                $all = $d->lines()->get();
                $lines = $all;
                if ($actor->usertype === 'seller') $lines = $all->where('seller_id', $actor->id)->whereIn('role', ['seller', 'platform_product']);
                elseif (! $admin) {
                    $sellerIds = \App\Models\Parcel::where('order_id', $d->order_id)
                        ->where($actor->usertype === 'rider' ? 'assigned_rider_id' : 'sorting_center_id',
                            $actor->usertype === 'rider' ? $actor->rider->id : $actor->sortingCenter->id)->pluck('seller_id');
                    $lines = $all->filter(fn ($line) => (int) $line->beneficiary_id === $actor->id
                        || ($sellerIds->contains($line->seller_id) && in_array($line->role, ['rider', 'logistics', 'platform_shipping'], true)));
                }
                $holds = $this->ledger->holds($d);
                $lineRows = [];
                foreach ($lines as $line) {
                    $balance = $this->ledger->balance($line);
                    $payouts = PaymentDistributionPayout::where('line_id', $line->id)->get();
                    $paid = (int) $payouts->where('status', 'paid')->sum('amount_cents');
                    $reserved = (int) $payouts->whereIn('status', ['reserved', 'reconciling'])->sum('amount_cents');
                    $own = $admin || (int) $line->beneficiary_id === $actor->id
                        || (! $line->beneficiary_id && (($actor->usertype === 'rider' && $line->role === 'rider')
                        || ($actor->usertype === 'sorting_center' && $line->role === 'logistics')));
                    $payable = in_array($line->role, ['seller', 'rider', 'logistics'], true);
                    $remaining = max(0, $balance - $paid - $reserved);
                    $available = ! $holds && $line->beneficiary_id && $payable ? $remaining : 0;
                    if ($line->role === 'seller') $summary['product_sales'] += $line->base_cents;
                    if ($line->role === 'rider') $summary['shipping_fees'] += $line->base_cents;
                    if ($line->role === 'platform_product') $summary['product_commission'] += $balance;
                    if ($line->role === 'platform_shipping') $summary['shipping_commission'] += $balance;
                    if ($admin && ! $holds && in_array($line->role, ['platform_product', 'platform_shipping'], true)) $summary['recognized_platform_revenue'] += $balance;
                    if ($own && $payable) {
                        $summary['allocation'] += $balance;
                        $summary['pending'] += $remaining - $available;
                        $summary['available'] += $available;
                        $summary['reserved'] += $reserved;
                        $summary['paid'] += $paid;
                        $summary['reversals'] += $line->amount_cents - $balance;
                        if ($line->role === 'rider') $summary[$d->snapshot['shipping_method'] === 'express' ? 'express_earnings' : 'standard_earnings'] += $balance;
                    }
                    $lineRows[] = ['id' => $line->id, 'role' => $line->role, 'base' => Money::decimal($line->base_cents),
                        'original' => Money::decimal($line->amount_cents), 'allocation' => Money::decimal($balance),
                        'reversed' => Money::decimal($line->amount_cents - $balance), 'available' => Money::decimal($own ? $available : 0),
                        'paid' => Money::decimal($own ? $paid : 0), 'reserved' => Money::decimal($own ? $reserved : 0),
                        'recovery' => Money::decimal(max(0, $paid - $balance)), 'beneficiary_id' => $line->beneficiary_id,
                        'can_request' => $own && $available > 0, 'payouts' => $own ? $payouts->map(fn ($p) => [
                            'id' => $p->id, 'status' => $p->status, 'amount' => Money::decimal($p->amount_cents),
                            'reference' => $p->transfer_reference, 'paid_at' => $p->paid_at,
                            'destination' => $admin ? $p->destination : null,
                        ])->all() : []];
                }
                if (in_array($d->order->status, ['delivered', 'completed'], true)) $completedDeliveries++;
                $receipts = $this->ledger->total($d->id, 'receipt');
                $refunds = $this->ledger->total($d->id, 'refund');
                $codState = null;
                if (in_array($d->snapshot['payment_method'], ['cod', 'cash_on_delivery'], true)) {
                    $hasCash = \App\Models\CodCollection::where('order_id', $d->order_id)->exists();
                    $codReconciled = (int) $this->ledger->events($d->id)->where('kind', 'receipt')->where('source_type', 'cod')->sum('amount_cents');
                    $codState = $codReconciled >= $d->buyer_cents ? 'reconciled' : ($this->ledger->total($d->id, 'cod_remitted') > 0 ? 'remitted' : ($hasCash ? 'collected' : 'pending'));
                    $unpaid = $all->whereIn('role', ['seller', 'rider', 'logistics'])->contains(fn ($line) => $this->ledger->balance($line)
                        > (int) PaymentDistributionPayout::where('line_id', $line->id)->where('status', 'paid')->sum('amount_cents'));
                    if ($codState === 'reconciled' && ! $unpaid && ! $holds) $codState = 'settled';
                }
                if ($admin) {
                    $cashCollected = \App\Models\CodCollection::where('order_id', $d->order_id)->get()->sum(fn ($c) => Money::cents($c->amount));
                    $onlineCollected = $this->ledger->events($d->id)->where('kind', 'receipt')->where('source_type', 'online')->sum('amount_cents');
                    $summary['collected'] += $cashCollected + max($this->ledger->total($d->id, 'payment_captured'), $onlineCollected + $this->ledger->total($d->id, 'gateway_fee'));
                    $summary['reconciled'] += $receipts;
                    $summary['refunds'] += $refunds;
                    $summary['gateway_fees'] += $this->ledger->total($d->id, 'gateway_fee');
                    $summary['discounts'] += $d->discount_cents + $d->shipping_discount_cents;
                }
                $events = $this->ledger->events($d->id);
                if (! $admin) $events->whereIn('line_id', $lines->where('beneficiary_id', $actor->id)->pluck('id'));
                return ['id' => $d->id, 'order_number' => $d->order->order_number, 'order_status' => $d->order->status, 'cod_status' => $codState,
                    'sources' => $admin ? [
                        'collections' => \App\Models\CodCollection::where('order_id', $d->order_id)->get(['id', 'rider_id', 'sorting_center_id', 'amount']),
                        'remittances' => \App\Models\CodRemittance::whereIn('rider_id', \App\Models\CodCollection::where('order_id', $d->order_id)->pluck('rider_id'))->get(['id', 'rider_id', 'sorting_center_id', 'received_amount', 'status']),
                        'refunds' => \App\Models\RefundLedger::where('order_id', $d->order_id)->get(['id', 'order_item_id', 'refunded_amount', 'status']),
                        'matches' => $this->ledger->events($d->id)->where('kind', 'cod_remitted')->get(['id', 'amount_cents', 'details']),
                    ] : null,
                    'shipping_method' => $d->snapshot['shipping_method'], 'holds' => $holds,
                    'warnings' => $d->snapshot['profitability_warnings'], 'lines' => $lineRows,
                    'reconciled' => $admin ? Money::decimal($receipts) : null, 'refunds' => $admin ? Money::decimal($refunds) : null,
                    'discounts' => $admin ? Money::decimal($d->discount_cents + $d->shipping_discount_cents) : null,
                    'events' => $events->orderByDesc('id')->limit(50)->get()->map(fn ($e) => ['id' => $e->id, 'kind' => $e->kind,
                        'amount' => Money::decimal($e->amount_cents), 'at' => $e->created_at,
                        'details' => $admin ? json_decode($e->details, true) : null]),
                ];
            });
            if ($index >= ($page - 1) * 20 && $index < $page * 20) $rows[] = $row;
        }
        $summary['platform_revenue'] = $summary['product_commission'] + $summary['shipping_commission'];
        $cod = null;
        if ($actor->usertype === 'rider') {
            $cod = app(\App\Services\CodLedger::class)->balance($actor->rider->id, $actor->rider->sorting_center_id);
            $summary['cod_obligation'] = $cod['outstanding'];
        } elseif ($actor->usertype === 'sorting_center') {
            $collections = \App\Models\CodCollection::where('sorting_center_id', $actor->sortingCenter->id)->get()->sum(fn ($c) => Money::cents($c->amount));
            $received = \App\Models\CodRemittance::where('sorting_center_id', $actor->sortingCenter->id)->where('status', 'confirmed')->get()->sum(fn ($c) => Money::cents($c->received_amount));
            $summary['cod_obligation'] = max(0, $collections - $received);
        }
        $data = ['role' => $actor->usertype, 'summary' => array_map(fn ($n) => Money::decimal($n), $summary),
            'completed_deliveries' => $completedDeliveries, 'distributions' => $rows, 'page' => $page,
            'pages' => max(1, (int) ceil($count / 20)), 'settlement_account' => $admin ? config('distribution.settlement_account') : null,
            'rules' => $admin ? DB::table('payment_profitability_rules')->get() : [],
            'costs' => $actor->usertype === 'seller' ? DB::table('payment_product_costs')->where('seller_id', $actor->id)->get() : [],
            'cod' => $cod];
        return $request->wantsJson() ? response()->json($data) : Inertia::render('Payments/Distributions', $data);
    }

    public function receipt(Request $request, int $id)
    {
        $actor = $this->actor($request);
        $this->ledger->admin($actor);
        $input = $request->validate($this->receiptRules());
        $this->ledger->receipt($actor, $id, $input);
        return $this->done($request, 'Receipt reconciled.');
    }

    public function matchCod(Request $request, int $id)
    {
        $actor = $this->actor($request);
        $input = $request->validate(['request_key' => 'required|string|max:120', 'amount' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'collection_id' => 'required|integer', 'remittance_id' => 'required|integer', 'evidence' => 'required|string|min:8|max:2000']);
        $this->ledger->matchCod($actor, $id, $input);
        return $this->done($request, 'COD remittance matched. Platform funding still requires reconciliation.');
    }

    public function receiptRules(): array
    {
        return ['request_key' => 'required|string|max:120', 'kind' => 'required|in:online,cod,subsidy',
            'amount' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,2})?$/'], 'account' => 'required|string|max:150',
            'reference' => 'required|string|min:3|max:180', 'evidence' => 'required|string|min:8|max:2000',
            'gateway_fee' => ['nullable', 'string', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'fee_bearer' => 'nullable|in:platform', 'funder' => 'required_if:kind,subsidy|nullable|string|max:150',
            'collection_id' => 'required_if:kind,cod|nullable|integer', 'remittance_id' => 'required_if:kind,cod|nullable|integer',
            'matching_id' => 'required_if:kind,cod|nullable|integer'];
    }

    public function reserve(Request $request, int $id)
    {
        $actor = $this->actor($request);
        $input = $request->validate(['request_key' => 'required|string|max:120', 'line_id' => 'required|integer',
            'destination' => 'required|array:method,account_name,account_number,bank',
            'destination.method' => 'required|in:bank,gcash,maya', 'destination.account_name' => 'required|string|min:2|max:150',
            'destination.account_number' => 'required|string|min:5|max:100', 'destination.bank' => 'required|string|min:2|max:150']);
        $this->ledger->reserve($actor, $id, $input);
        return $this->done($request, 'Payout reserved for processing.');
    }

    public function payout(Request $request, int $id)
    {
        $actor = $this->actor($request);
        $input = $request->validate(['request_key' => 'required|string|max:120', 'status' => 'required|in:paid,reconciling,cancelled',
            'reference' => 'required|string|min:3|max:180', 'evidence' => 'required|string|min:8|max:2000']);
        $this->ledger->payout($actor, $id, $input);
        return $this->done($request, 'Payout outcome recorded.');
    }

    public function refund(Request $request, int $id)
    {
        $actor = $this->actor($request);
        $input = $request->validate(['request_key' => 'required|string|max:120', 'refund_id' => 'required|integer',
            'amount' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,2})?$/'], 'component' => 'required|in:product,shipping',
            'item_id' => 'required_if:component,product|nullable|integer', 'liability' => 'required|in:seller,logistics,platform',
            'reference' => 'required|string|min:3|max:180', 'evidence' => 'required|string|min:8|max:2000']);
        $this->ledger->refund($actor, $id, $input);
        return $this->done($request, 'Refund reconciled and adjustments recorded.');
    }

    public function rule(Request $request)
    {
        $actor = $this->actor($request);
        $this->ledger->admin($actor);
        $input = $request->validate(['shipping_method' => 'required|in:standard,express,pickup', 'available' => 'required|boolean',
            'block_shortfall' => 'required|boolean', 'logistics_cost_cents' => 'required|integer|min:0|max:100000000',
            'logistics_margin_cents' => 'required|integer|min:0|max:100000000', 'rider_cost_cents' => 'required|integer|min:0|max:100000000',
            'rider_minimum_cents' => 'required|integer|min:0|max:100000000', 'blocked_zones' => 'present|array|max:100',
            'blocked_zones.*' => 'required|string|min:2|max:150']);
        DB::transaction(function () use ($actor, $input) {
            DB::table('payment_profitability_rules')->updateOrInsert(['shipping_method' => $input['shipping_method']],
                [...$input, 'blocked_zones' => json_encode($input['blocked_zones']), 'updated_by' => $actor->id, 'updated_at' => now(), 'created_at' => now()]);
            DB::table('payment_policy_audits')->insert(['actor_id' => $actor->id, 'kind' => 'profitability_rule', 'details' => json_encode($input), 'created_at' => now()]);
        });
        return $this->done($request, 'Profitability rules saved.');
    }

    public function cost(Request $request)
    {
        $actor = $this->actor($request);
        abort_unless($actor->usertype === 'seller', 403);
        $input = $request->validate(['product_id' => 'required|integer', 'cost_cents' => 'required|integer|min:0|max:100000000',
            'minimum_margin_cents' => 'required|integer|min:0|max:100000000', 'block_shortfall' => 'required|boolean']);
        abort_unless(Product::whereKey($input['product_id'])->where('seller_id', $actor->id)->exists(), 403);
        DB::transaction(function () use ($actor, $input) {
            DB::table('payment_product_costs')->updateOrInsert(['product_id' => $input['product_id']], [...$input, 'seller_id' => $actor->id, 'updated_at' => now(), 'created_at' => now()]);
            DB::table('payment_policy_audits')->insert(['actor_id' => $actor->id, 'kind' => 'product_cost', 'details' => json_encode($input), 'created_at' => now()]);
        });
        return $this->done($request, 'Declared cost saved.');
    }

    private function done(Request $request, string $message)
    {
        return $request->wantsJson() ? response()->json(['message' => $message]) : back()->with('status', $message);
    }
}
