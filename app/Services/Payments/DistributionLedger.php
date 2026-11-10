<?php

namespace App\Services\Payments;

use App\Models\{CodCollection, CodRemittance, Order, PaymentDistribution, PaymentDistributionLine, PaymentDistributionPayout, RefundLedger, User};
use Illuminate\Support\Facades\{DB, Schema};

/** All financial mutations lock Order -> distribution -> source/line -> payout. */
class DistributionLedger
{
    public function installed(): bool
    {
        return Schema::hasTable('payment_distributions');
    }

    public function manages(int $orderId): bool
    {
        return $this->installed() && PaymentDistribution::where('order_id', $orderId)->exists();
    }

    public function admin(User $actor): void
    {
        abort_unless($actor->usertype === 'admin' && $actor->status === 'approved', 403);
    }

    public function snapshot(Order $order): ?PaymentDistribution
    {
        if (! config('distribution.enabled') || ! $this->installed() || $order->delivery_fee_payer === 'seller') return null;
        return DB::transaction(function () use ($order) {
            Order::withoutEvents(fn () => Order::whereKey($order->id)->lockForUpdate()->firstOrFail());
            if ($existing = PaymentDistribution::where('order_id', $order->id)->first()) return $existing;
            $items = $order->items()->with('product')->orderBy('id')->get();
            abort_if($items->isEmpty(), 422, 'Cannot allocate an order without items.');
            $gross = 0;
            foreach ($items as $item) {
                abort_unless($item->product?->seller_id && $item->quantity > 0, 422, 'An item has no valid seller or quantity.');
                $gross += Money::check(Money::cents($item->price) * $item->quantity);
            }
            Money::check($gross);
            $shipping = Money::cents($order->shipping_fee_before_discount ?? $order->shipping_fee ?? '0');
            $discount = Money::cents($order->discount ?? '0');
            $shippingDiscount = Money::cents($order->shipping_discount ?? '0');
            $buyer = Money::cents($order->total);
            $warnings = app(Profitability::class)->validateOrder($order, $items, $shipping);
            $distribution = PaymentDistribution::create([
                'policy' => 'alona-90-10-70-20-10-v1', 'currency' => 'PHP',
                'order_id' => $order->id, 'product_cents' => $gross, 'shipping_cents' => $shipping,
                'buyer_cents' => $buyer, 'discount_cents' => $discount, 'shipping_discount_cents' => $shippingDiscount,
                'unexplained_cents' => $buyer - ($gross + $shipping - $discount - $shippingDiscount),
                'snapshot' => ['shipping_method' => $order->shipping_method, 'payment_method' => $order->payment_method,
                    'vouchers' => $order->applied_vouchers, 'release_days' => config('distribution.release_days', 7),
                    'shipping_allocation' => 'equal per seller, stable seller ID, largest remainder',
                    'discount_policy' => 'Explicit sponsor funding required; no silent share deductions.',
                    'tax_cents' => 0, 'tax_policy' => 'Checkout currently has no tax component. Unexplained price differences block settlement.',
                    'profitability_warnings' => $warnings],
            ]);
            foreach ($items as $item) {
                $base = Money::cents($item->price) * $item->quantity;
                $commission = Money::percent($base, 10);
                foreach (['seller' => $base - $commission, 'platform_product' => $commission] as $role => $amount) {
                    $distribution->lines()->create(['line_key' => $role.':'.$item->id, 'role' => $role,
                        'seller_id' => $item->product->seller_id, 'order_item_id' => $item->id,
                        'beneficiary_id' => $role === 'seller' ? $item->product->seller_id : null,
                        'base_cents' => $base, 'amount_cents' => $amount]);
                }
            }
            $sellers = $items->pluck('product.seller_id')->unique()->sort()->values()->all();
            $shippingParts = Money::split($shipping, array_fill_keys($sellers, 1));
            foreach ($shippingParts as $seller => $base) {
                foreach (Money::split($base, ['rider' => 70, 'logistics' => 20, 'platform_shipping' => 10]) as $role => $amount) {
                    $distribution->lines()->create(['line_key' => $role.':'.$seller, 'role' => $role,
                        'seller_id' => $seller, 'base_cents' => $base, 'amount_cents' => $amount]);
                }
            }
            $this->event($distribution, 'snapshot:'.$order->id, 'calculated', $gross + $shipping, ['policy' => $distribution->policy]);
            return $distribution;
        }, 5);
    }

    public function locked(int $id, callable $callback): mixed
    {
        $peek = PaymentDistribution::findOrFail($id);
        return DB::transaction(function () use ($id, $peek, $callback) {
            $order = Order::withoutEvents(fn () => Order::whereKey($peek->order_id)->lockForUpdate()->firstOrFail());
            $distribution = PaymentDistribution::whereKey($id)->lockForUpdate()->firstOrFail();
            $distribution->setRelation('order', $order);
            return $callback($distribution);
        }, 5);
    }

    public function events(int $id)
    {
        return DB::table('payment_distribution_events')->where('distribution_id', $id);
    }

    public function total(int $id, string|array $kind): int
    {
        return (int) $this->events($id)->whereIn('kind', (array) $kind)->sum('amount_cents');
    }

    private function hash(array $payload): string
    {
        // Callers construct payloads with deterministic field ordering.
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function replay(PaymentDistribution $d, string $key, string $kind, int $amount, array $details): ?object
    {
        $existing = DB::table('payment_distribution_events')->where('request_key', $key)->first();
        if ($existing) abort_unless((int) $existing->distribution_id === $d->id && hash_equals($existing->payload_hash,
            $this->hash([$kind, $amount, $details])), 409, 'Idempotency key was reused with different data.');
        return $existing;
    }

    private function event(PaymentDistribution $d, string $key, string $kind, int $amount, array $details,
        ?User $actor = null, ?int $line = null, ?string $sourceType = null, ?int $sourceId = null, ?string $reference = null): object
    {
        if ($row = $this->replay($d, $key, $kind, $amount, $details)) return $row;
        if ($reference) abort_if(DB::table('payment_distribution_events')->where('external_reference', $reference)->exists(), 409, 'This receipt or adjustment reference has already been used.');
        $id = DB::table('payment_distribution_events')->insertGetId([
            'distribution_id' => $d->id, 'line_id' => $line, 'request_key' => $key, 'kind' => $kind,
            'payload_hash' => $this->hash([$kind, $amount, $details]), 'amount_cents' => $amount,
            'actor_id' => $actor?->id, 'source_type' => $sourceType, 'source_id' => $sourceId,
            'external_reference' => $reference, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now(),
        ]);
        return $this->events($d->id)->find($id);
    }

    /** A receipt is usable cash in the configured platform account, never a browser-paid flag. */
    public function capture(int $id, string $key, int $amount, string $reference): object
    {
        return $this->locked($id, function ($d) use ($key, $amount, $reference) {
            abort_unless(! in_array($d->snapshot['payment_method'], ['cod', 'cash_on_delivery'], true)
                && $amount === (int) $d->buyer_cents, 422, 'Captured amount or payment method mismatch.');
            $details = ['reference' => $reference, 'currency' => 'PHP'];
            if ($row = $this->replay($d, $key, 'payment_captured', $amount, $details)) return $row;
            abort_if($this->events($d->id)->where('kind', 'payment_captured')->exists(), 409, 'Payment capture already recorded; investigate a second charge.');
            $row = $this->event($d, $key, 'payment_captured', $amount, $details, reference: 'capture:'.$reference);
            $d->order->update(['payment_status' => 'paid']);
            return $row;
        });
    }

    public function receipt(User $actor, int $id, array $input): object
    {
        $this->admin($actor);
        return $this->recordReceipt($id, $input, $actor);
    }

    /** Match confirmed hub receipts to buyer cash without pretending it is in Alona's account. */
    public function matchCod(User $actor, int $id, array $input): object
    {
        abort_unless($actor->status === 'approved' && in_array($actor->usertype, ['admin', 'sorting_center'], true), 403);
        return $this->locked($id, function ($d) use ($actor, $input) {
            $amount = Money::cents($input['amount']);
            $collection = CodCollection::whereKey($input['collection_id'])->lockForUpdate()->firstOrFail();
            $remittance = CodRemittance::whereKey($input['remittance_id'])->lockForUpdate()->firstOrFail();
            if ($actor->usertype === 'sorting_center') abort_unless((int) $collection->sorting_center_id === (int) $actor->sortingCenter?->id, 403);
            abort_unless($amount > 0 && in_array($d->snapshot['payment_method'], ['cod', 'cash_on_delivery'], true)
                && (int) $collection->order_id === (int) $d->order_id && $remittance->status === 'confirmed'
                && (int) $collection->rider_id === (int) $remittance->rider_id
                && (int) $collection->sorting_center_id === (int) $remittance->sorting_center_id, 422, 'Match the collection to confirmed cash from the same Rider and company.');
            $details = ['collection_id' => $collection->id, 'remittance_id' => $remittance->id, 'evidence' => $input['evidence']];
            if ($row = $this->replay($d, $input['request_key'], 'cod_remitted', $amount, $details)) return $row;
            $matchedCollection = (int) DB::table('payment_distribution_events')->where('kind', 'cod_remitted')->where('source_id', $collection->id)->lockForUpdate()->get()->sum('amount_cents');
            $matchedRemittance = (int) DB::table('payment_distribution_events')->where('kind', 'cod_remitted')->where('details->remittance_id', $remittance->id)->lockForUpdate()->get()->sum('amount_cents');
            abort_if($matchedCollection + $amount > Money::cents($collection->amount) || $matchedRemittance + $amount > Money::cents($remittance->received_amount)
                || $this->total($d->id, 'cod_remitted') + $amount > $d->buyer_cents, 422, 'Matching exceeds collected, remitted or expected buyer cash.');
            return $this->event($d, $input['request_key'], 'cod_remitted', $amount, $details, $actor, sourceType: 'cod', sourceId: $collection->id);
        });
    }

    public function recordReceipt(int $id, array $input, ?User $actor = null): object
    {
        return $this->locked($id, function ($d) use ($input, $actor) {
            abort_unless(config('distribution.settlement_account') && $input['account'] === config('distribution.settlement_account'), 422, 'Receipt must be reconciled to the configured platform settlement account.');
            $amount = Money::cents($input['amount']);
            $fee = Money::cents($input['gateway_fee'] ?? '0');
            abort_unless($amount > 0, 422, 'Receipt amount must be positive.');
            $kind = $input['kind'];
            abort_unless(in_array($kind, ['online', 'cod', 'subsidy'], true), 422);
            abort_if($kind !== 'online' && $fee !== 0, 422, 'Only online settlements can include gateway fees.');
            $details = ['kind' => $kind, 'account' => $input['account'], 'reference' => $input['reference'],
                'evidence' => $input['evidence'], 'gateway_fee_cents' => $fee,
                'fee_bearer' => $input['fee_bearer'] ?? 'platform', 'collection_id' => isset($input['collection_id']) ? (int) $input['collection_id'] : null,
                'remittance_id' => isset($input['remittance_id']) ? (int) $input['remittance_id'] : null,
                'matching_id' => isset($input['matching_id']) ? (int) $input['matching_id'] : null, 'funder' => $input['funder'] ?? null];
            if ($existing = $this->replay($d, $input['request_key'], 'receipt', $amount, $details)) return $existing;
            $cod = in_array($d->snapshot['payment_method'], ['cod', 'cash_on_delivery'], true);
            abort_if(($kind === 'online' && $cod) || ($kind === 'cod' && ! $cod), 422, 'Payment method does not match the order snapshot.');
            $sourceId = null;
            if ($kind === 'cod') {
                abort_unless($fee === 0, 422, 'COD receipts cannot have gateway fees.');
                $collection = CodCollection::whereKey($input['collection_id'] ?? 0)->lockForUpdate()->firstOrFail();
                $remittance = CodRemittance::whereKey($input['remittance_id'] ?? 0)->lockForUpdate()->firstOrFail();
                abort_unless((int) $collection->order_id === (int) $d->order_id && $remittance->status === 'confirmed'
                    && (int) $collection->rider_id === (int) $remittance->rider_id
                    && (int) $collection->sorting_center_id === (int) $remittance->sorting_center_id, 422, 'COD collection and confirmed remittance must belong to the same order, Rider and company.');
                $match = $this->events($d->id)->where('kind', 'cod_remitted')->where('source_id', $collection->id)
                    ->where('details->remittance_id', $remittance->id)->where('id', $input['matching_id'] ?? 0)->first();
                abort_unless($match, 422, 'Match the confirmed remittance to this collection first.');
                $usedMatch = (int) $this->events($d->id)->where('kind', 'receipt')->where('details->matching_id', $match->id)->sum('amount_cents');
                abort_if($usedMatch + $amount > $match->amount_cents, 422, 'Receipt exceeds the matched remittance.');
                // Current locking reads also see allocations committed while waiting for this remittance lock (MySQL REPEATABLE READ).
                $usedCollection = (int) DB::table('payment_distribution_events')->where('kind', 'receipt')->where('source_type', 'cod')->where('source_id', $collection->id)->lockForUpdate()->get()->sum('amount_cents');
                $usedRemittance = (int) DB::table('payment_distribution_events')->where('kind', 'receipt')->where('details->remittance_id', $remittance->id)->lockForUpdate()->get()->sum('amount_cents');
                abort_if($usedCollection + $amount > Money::cents($collection->amount)
                    || $usedRemittance + $amount > Money::cents($remittance->received_amount), 422, 'Allocation exceeds collected or remitted cash.');
                $sourceId = $collection->id;
            }
            $received = (int) $this->events($d->id)->where('kind', 'receipt')->where('details->kind', '!=', 'subsidy')->get()->sum(function ($row) {
                return $row->amount_cents + (json_decode($row->details, true)['gateway_fee_cents'] ?? 0);
            });
            if ($kind !== 'subsidy') abort_if($received + $amount + $fee > $d->buyer_cents, 422, 'Receipts exceed the buyer payment.');
            else abort_unless(! empty($input['funder']), 422, 'Identify the subsidy or fee funder.');
            $row = $this->event($d, $input['request_key'], 'receipt', $amount, $details, $actor, sourceType: $kind, sourceId: $sourceId,
                reference: 'receipt:'.$input['reference']);
            if ($fee > 0) $this->event($d, 'fee:'.$row->id, 'gateway_fee', $fee, ['receipt_id' => $row->id, 'bearer' => $details['fee_bearer']], $actor);
            // A late payment is recorded without reviving a cancelled order.
            if ($kind !== 'subsidy' && $received + $amount + $fee === (int) $d->buyer_cents) {
                $d->order->update(['payment_status' => 'paid']);
            }
            return $row;
        });
    }

    /** Cumulative item refunds reverse the original commission exactly, including final centavo. */
    public function refund(User $actor, int $id, array $input): object
    {
        $this->admin($actor);
        return $this->locked($id, function ($d) use ($actor, $input) {
            $refund = RefundLedger::whereKey($input['refund_id'])->lockForUpdate()->firstOrFail();
            abort_unless((int) $refund->order_id === (int) $d->order_id && $refund->status === 'completed', 422, 'A completed refund record for this order is required.');
            $amount = Money::cents($input['amount']);
            $details = ['refund_id' => $refund->id, 'item_id' => isset($input['item_id']) ? (int) $input['item_id'] : null, 'component' => $input['component'],
                'liability' => $input['liability'], 'evidence' => $input['evidence'], 'reference' => $input['reference']];
            if ($existing = $this->replay($d, $input['request_key'], 'refund', $amount, $details)) return $existing;
            abort_unless($amount > 0 && in_array($input['liability'], ['seller', 'logistics', 'platform'], true), 422);
            $already = (int) $this->events($d->id)->where('kind', 'refund')->where('source_id', $refund->id)->sum('amount_cents');
            abort_if($already + $amount > Money::cents($refund->refunded_amount), 422, 'Adjustment exceeds the existing refund record.');
            abort_if($this->total($d->id, 'refund') + $amount > $d->buyer_cents, 422, 'Refund exceeds buyer payment.');
            // Freeze remaining cash until all payout reservations have resolved; paid funds create recovery obligations.
            abort_if(PaymentDistributionPayout::where('distribution_id', $d->id)->whereIn('status', ['reserved', 'reconciling'])->exists(), 409, 'Resolve payout reservations before reconciling the refund.');
            $row = $this->event($d, $input['request_key'], 'refund', $amount, $details, $actor,
                sourceType: 'refund_ledger', sourceId: $refund->id, reference: 'refund:'.$input['reference']);
            if ($input['component'] === 'product') {
                $seller = $d->lines()->where('order_item_id', $input['item_id'] ?? 0)->where('role', 'seller')->firstOrFail();
                abort_unless((int) $refund->order_item_id === (int) $seller->order_item_id || ! $refund->order_item_id, 422, 'Refund item mismatch.');
                $commission = $d->lines()->where('order_item_id', $seller->order_item_id)->where('role', 'platform_product')->firstOrFail();
                $previous = (int) $this->events($d->id)->where('kind', 'refund')->where('id', '!=', $row->id)
                    ->where('details->component', 'product')->where('details->item_id', $seller->order_item_id)->sum('amount_cents');
                abort_if($previous + $amount > $seller->base_cents, 422, 'Cumulative refund exceeds the item price.');
                $reverseCommission = Money::percent($previous + $amount, 10) - Money::percent($previous, 10);
                $this->event($d, 'reversal:'.$row->id.':platform', 'reversal', $reverseCommission, ['refund_event' => $row->id], $actor, $commission->id);
                if ($input['liability'] === 'seller') {
                    $this->event($d, 'reversal:'.$row->id.':seller', 'reversal', $amount - $reverseCommission, ['refund_event' => $row->id], $actor, $seller->id);
                } else {
                    $this->event($d, 'liability:'.$row->id, 'liability', $amount - $reverseCommission,
                        ['refund_event' => $row->id, 'liable_party' => $input['liability'], 'evidence' => $input['evidence']], $actor);
                }
            } else {
                abort_unless($input['component'] === 'shipping', 422);
                $cumulative = (int) $this->events($d->id)->where('kind', 'refund')->where('details->component', 'shipping')->sum('amount_cents');
                abort_if($cumulative > $d->shipping_cents, 422);
                $platformLines = $d->lines()->where('role', 'platform_shipping')->orderBy('id')->get();
                $originalCommission = (int) $platformLines->sum('amount_cents');
                $alreadyReversed = (int) $this->events($d->id)->where('kind', 'reversal')->whereIn('line_id', $platformLines->modelKeys())->sum('amount_cents');
                $reverse = Money::proportion($originalCommission, $cumulative, (int) $d->shipping_cents) - $alreadyReversed;
                $left = $reverse;
                foreach ($platformLines as $line) {
                    $portion = min($left, $this->balance($line));
                    if ($portion > 0) $this->event($d, 'reversal:'.$row->id.':shipping:'.$line->id, 'reversal', $portion,
                        ['refund_event' => $row->id], $actor, $line->id);
                    $left -= $portion;
                }
                // Delivered work is preserved. Liability requires investigation; it is never a Rider wage deduction.
                $this->event($d, 'liability:'.$row->id, 'liability', $amount - $reverse,
                    ['refund_event' => $row->id, 'liable_party' => $input['liability'], 'evidence' => $input['evidence']], $actor);
            }
            return $row;
        });
    }

    public function balance(PaymentDistributionLine $line): int
    {
        return (int) $line->amount_cents - (int) $this->events($line->distribution_id)->where('kind', 'reversal')->where('line_id', $line->id)->sum('amount_cents');
    }

    public function holds(PaymentDistribution $d): array
    {
        $order = $d->order;
        $holds = [];
        if (! config('distribution.enabled')) $holds[] = 'Settlement release is disabled';
        if (! in_array($order->status, ['delivered', 'completed'], true)) $holds[] = 'Awaiting successful delivery';
        if ($order->payment_status !== 'paid') $holds[] = 'Payment not verified';
        if (! $order->delivered_at || $order->delivered_at->copy()->addDays((int) $d->snapshot['release_days'])->isFuture()) $holds[] = 'Settlement waiting period';
        if ($d->unexplained_cents != 0) $holds[] = 'Pricing components need reconciliation';
        if ($order->items()->whereNotIn('status', ['delivered', 'completed'])->exists()) $holds[] = 'Incomplete or cancelled items require review';
        if ($order->returnRequests()->whereNotIn('status', ['cancelled', 'rejected', 'refunded', 'completed'])->exists()) $holds[] = 'Open return or dispute';
        if (Schema::hasTable('complaints') && DB::table('complaints')->where('order_id', $order->id)->whereNotIn('status', ['resolved', 'closed', 'rejected'])->exists()) $holds[] = 'Open complaint';
        $ledgerRefund = (int) RefundLedger::where('order_id', $order->id)->get()->sum(fn ($r) => Money::cents($r->refunded_amount));
        $orderRefund = Money::cents($order->refund_amount ?? '0');
        if (max($ledgerRefund, $orderRefund) > $this->total($d->id, 'refund') || ($order->refund_status === 'refunded' && $this->total($d->id, 'refund') === 0)) $holds[] = 'Refund requires reconciliation';
        if ($d->snapshot['shipping_method'] !== 'pickup') {
            $items = $order->items()->pluck('id');
            $parcels = \App\Models\Parcel::whereIn('order_item_id', $items)->with('deliveryProofs')->get();
            if ($parcels->count() !== $items->count() || $parcels->contains(fn ($p) => $p->status !== 'DELIVERED'
                || ! $p->deliveryProofs->contains(fn ($proof) => (int) $proof->rider_id === (int) $p->assigned_rider_id && $proof->isValidPhoto()))) {
                $holds[] = 'Delivery evidence is incomplete';
            }
        }
        $obligations = (int) $d->lines()->get()->sum(function ($line) {
            $paid = (int) PaymentDistributionPayout::where('line_id', $line->id)->where('status', 'paid')->sum('amount_cents');
            return max($paid, $this->balance($line));
        });
        if ($this->total($d->id, 'receipt') - $this->total($d->id, 'refund') < $obligations) $holds[] = 'Insufficient reconciled funding';
        return array_values(array_unique($holds));
    }

    /** Bind shipping shares to verified completed shipments; no arbitrary payee selection. */
    public function bindShipping(PaymentDistribution $d): void
    {
        foreach ($d->lines()->whereIn('role', ['rider', 'logistics'])->get() as $line) {
            if ($line->beneficiary_id || $line->amount_cents === 0) continue;
            $itemIds = $d->lines()->where('role', 'seller')->where('seller_id', $line->seller_id)->pluck('order_item_id');
            $parcels = \App\Models\Parcel::whereIn('order_item_id', $itemIds)->with('deliveryProofs')->get();
            if ($parcels->count() !== $itemIds->count() || $parcels->isEmpty()) continue;
            if ($parcels->contains(fn ($p) => $p->status !== 'DELIVERED' || ! $p->assigned_rider_id
                || ! $p->deliveryProofs->contains(fn ($proof) => (int) $proof->rider_id === (int) $p->assigned_rider_id && $proof->isValidPhoto()))) continue;
            if ($parcels->pluck('assigned_rider_id')->unique()->count() !== 1 || $parcels->pluck('sorting_center_id')->unique()->count() !== 1) continue;
            $userId = $line->role === 'rider'
                ? \App\Models\Rider::find($parcels->first()->assigned_rider_id)?->user_id
                : \App\Models\SortingCenter::find($parcels->first()->sorting_center_id)?->user_id;
            if (! $userId) continue;
            $line->update(['beneficiary_id' => $userId]);
            $this->event($d, 'beneficiary:'.$line->id, 'beneficiary', 0, ['beneficiary_id' => $userId, 'parcel_ids' => $parcels->modelKeys()], line: $line->id);
        }
    }

    public function reserve(User $actor, int $id, array $input): PaymentDistributionPayout
    {
        return $this->locked($id, function ($d) use ($actor, $input) {
            abort_unless(config('distribution.enabled'), 503, 'Settlement release is disabled.');
            $this->bindShipping($d);
            $line = $d->lines()->findOrFail($input['line_id']);
            abort_unless($actor->status === 'approved' && ($actor->usertype === 'admin' || (int) $line->beneficiary_id === $actor->id), 403);
            abort_unless($line->beneficiary_id && in_array($line->role, ['seller', 'rider', 'logistics'], true), 422, 'Beneficiary has not been verified.');
            abort_unless(User::whereKey($line->beneficiary_id)->where('status', 'approved')->exists(), 422, 'Beneficiary account is not approved.');
            $payload = $this->hash([$d->id, $line->id, $input['destination']]);
            if ($existing = PaymentDistributionPayout::where('request_key', $input['request_key'])->first()) {
                abort_unless(hash_equals($existing->payload_hash, $payload), 409, 'Payout key reused with different data.');
                return $existing;
            }
            $holds = $this->holds($d);
            abort_if($holds, 422, implode('; ', $holds));
            $reserved = (int) PaymentDistributionPayout::where('line_id', $line->id)->whereIn('status', ['reserved', 'reconciling', 'paid'])->sum('amount_cents');
            $amount = $this->balance($line) - $reserved;
            abort_unless($amount > 0, 409, 'This allocation is already reserved or paid.');
            $committed = (int) PaymentDistributionPayout::where('distribution_id', $d->id)->whereIn('status', ['reserved', 'reconciling', 'paid'])->sum('amount_cents');
            abort_if($committed + $amount > $this->total($d->id, 'receipt') - $this->total($d->id, 'refund'), 422, 'Insufficient available cash.');
            $payout = PaymentDistributionPayout::create(['distribution_id' => $d->id, 'line_id' => $line->id,
                'beneficiary_id' => $line->beneficiary_id, 'request_key' => $input['request_key'], 'payload_hash' => $payload,
                'amount_cents' => $amount, 'destination' => $input['destination']]);
            $this->event($d, 'reserve:'.$payout->id, 'payout_reserved', $amount, ['payout_id' => $payout->id], $actor, $line->id);
            return $payout;
        });
    }

    public function payout(User $actor, int $id, array $input): PaymentDistributionPayout
    {
        $this->admin($actor);
        $peek = PaymentDistributionPayout::findOrFail($id);
        return $this->locked($peek->distribution_id, function ($d) use ($id, $actor, $input) {
            $payout = PaymentDistributionPayout::whereKey($id)->lockForUpdate()->firstOrFail();
            $details = ['payout_id' => $id, 'status' => $input['status'], 'reference' => $input['reference'], 'evidence' => $input['evidence']];
            if ($this->replay($d, $input['request_key'], 'payout_'.$input['status'], $payout->amount_cents, $details)) return $payout;
            abort_unless(in_array($payout->status, ['reserved', 'reconciling'], true), 409, 'Payout has already been resolved.');
            abort_unless(in_array($input['status'], ['paid', 'reconciling', 'cancelled'], true), 422);
            if ($input['status'] === 'paid') {
                abort_unless(config('distribution.enabled'), 503);
                $holds = $this->holds($d);
                abort_if($holds, 422, implode('; ', $holds));
                abort_if(PaymentDistributionPayout::where('transfer_reference', $input['reference'])->exists(), 409, 'Transfer reference already used.');
                $payout->transfer_reference = $input['reference'];
                $payout->paid_at = now();
            }
            // 'cancelled' evidence must confirm nonpayment, never merely a timeout.
            $payout->status = $input['status'];
            $payout->processed_by = $actor->id;
            $payout->save();
            $this->event($d, $input['request_key'], 'payout_'.$input['status'], $payout->amount_cents, $details, $actor, $payout->line_id);
            return $payout;
        });
    }
}
