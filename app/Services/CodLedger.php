<?php

namespace App\Services;

use App\Models\{CodCollection, CodDiscrepancy, CodRemittance, Order, Parcel, Rider, RiderNotification, User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Schema, Storage};

class CodLedger
{
    public function requireStorage(): void
    {
        app(LogisticsAudit::class)->requireStorage();
        foreach (['cod_collections', 'cod_remittances', 'cod_discrepancies'] as $table) abort_unless(Schema::hasTable($table), 503, 'COD cash records are temporarily unavailable.');
    }
    public function cents(string $value): int
    {
        if (!preg_match('/\A(-?)([0-9]{1,10})(?:\.([0-9]{1,2}))?\z/', $value, $parts)) throw new \InvalidArgumentException('Invalid cash amount.');
        return ($parts[1] === '-' ? -1 : 1) * ((int) $parts[2] * 100 + (int) str_pad($parts[3] ?? '', 2, '0'));
    }
    public function money(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }
    public function sum($query, string $field): int
    {
        return (clone $query)->select(['id', $field])->orderBy('id')->lazy(500)->sum(fn ($row) => $this->cents((string) $row->$field));
    }
    public function balance(int $riderId, int $centerId): array
    {
        $collections = CodCollection::where('rider_id', $riderId)->where('sorting_center_id', $centerId);
        $remittances = CodRemittance::where('rider_id', $riderId)->where('sorting_center_id', $centerId);
        $collected = $this->sum($collections, 'amount');
        $received = $this->sum((clone $remittances)->where('status', 'confirmed'), 'received_amount');
        $pending = $this->sum((clone $remittances)->where('status', 'submitted'), 'submitted_amount');
        return ['collected' => $collected, 'received' => $received, 'pending' => $pending, 'outstanding' => $collected - $received];
    }
    private function rider(User $actor): Rider
    {
        abort_unless($actor->usertype === 'rider' && $actor->rider, 403);
        return $actor->rider;
    }
    private function notify(Rider $rider, string $eventKey, string $message): void
    {
        RiderNotification::firstOrCreate(['user_id' => $rider->user_id, 'event_key' => hash('sha256', 'cod:'.$eventKey)],
            ['type' => 'cod_cash', 'title' => 'COD cash record updated', 'body' => $message,
                'data' => ['screen' => 'account']]);
    }
    public function collect(User $actor, int $parcelId, array $input, UploadedFile $evidence): CodCollection
    {
        $this->requireStorage(); $rider = $this->rider($actor); $path = null;
        try {
            return DB::transaction(function () use ($actor, $parcelId, $input, $evidence, $rider, &$path) {
                $orderId = Parcel::findOrFail($parcelId)->orderItem?->order_id;
                abort_unless($orderId, 422, 'A verified order-item link is required for COD collection.');
                $order = Order::withoutEvents(fn () => Order::whereKey($orderId)->lockForUpdate()->firstOrFail());
                $parcel = Parcel::whereKey($parcelId)->lockForUpdate()->firstOrFail();
                abort_unless((int) $parcel->assigned_rider_id === (int) $rider->id, 403, 'Only the assigned delivery Rider can record collected Buyer cash.');
                abort_unless($parcel->sorting_center_id && (int) $parcel->buyer_id === (int) $order->user_id
                    && in_array($order->payment_method, ['cod', 'cash_on_delivery'], true), 422, 'This parcel is not linked to a COD Buyer order.');
                abort_unless(app(SellerParcelDelivery::class)->verifiedSellerId($parcel), 422, 'The parcel must have a verified Seller/order-item link.');
                abort_unless(in_array($parcel->status, ['OUT_FOR_DELIVERY', 'DELIVERED'], true), 422, 'COD collection can only be recorded during or after Buyer delivery.');
                Rider::whereKey($rider->id)->lockForUpdate()->firstOrFail();
                $existing = CodCollection::where('request_key', $input['request_key'])->first();
                if ($existing) {
                    abort_unless((int) $existing->parcel_id === $parcelId && (int) $existing->rider_id === (int) $rider->id
                        && $this->cents($existing->amount) === $this->cents((string) $input['amount']), 409, 'This request key has already been used.');
                    return $existing;
                }
                abort_if(CodCollection::where('parcel_id', $parcelId)->exists(), 409, 'Cash collection is already recorded for this parcel. Corrections require authorized review.');
                $path ??= $evidence->store('cod-evidence', 'local'); abort_unless($path, 500, 'Cash evidence could not be saved.');
                $collection = CodCollection::create(['request_key' => $input['request_key'], 'order_id' => $order->id,
                    'parcel_id' => $parcelId, 'rider_id' => $rider->id, 'sorting_center_id' => $parcel->sorting_center_id,
                    'amount' => $this->money($this->cents((string) $input['amount'])), 'evidence_path' => $path, 'collected_at' => now(), 'created_at' => now()]);
                app(LogisticsAudit::class)->record($actor, 'cod_collected', $parcel, ['collection_id' => $collection->id,
                    'order_id' => $order->id, 'amount' => $collection->amount, 'has_evidence' => true], $rider->id);
                return $collection;
            }, 5);
        } catch (\Throwable $error) { if ($path) Storage::disk('local')->delete($path); throw $error; }
    }
    public function submit(User $actor, array $input, UploadedFile $evidence): CodRemittance
    {
        $this->requireStorage(); $rider = $this->rider($actor); $path = null;
        try {
            return DB::transaction(function () use ($actor, $input, $evidence, $rider, &$path) {
                Rider::whereKey($rider->id)->lockForUpdate()->firstOrFail();
                $existing = CodRemittance::where('request_key', $input['request_key'])->first();
                if ($existing) {
                    abort_unless((int) $existing->rider_id === (int) $rider->id && (int) $existing->sorting_center_id === (int) $input['sorting_center_id']
                        && $this->cents($existing->submitted_amount) === $this->cents((string) $input['amount']), 409, 'This request key has already been used.');
                    return $existing;
                }
                $balance = $this->balance($rider->id, (int) $input['sorting_center_id']);
                $amount = $this->cents((string) $input['amount']);
                abort_if($amount > $balance['outstanding'] - $balance['pending'], 422, 'The remittance exceeds unsubmitted recorded cash in your custody.');
                $path ??= $evidence->store('cod-evidence', 'local'); abort_unless($path, 500, 'Remittance evidence could not be saved.');
                $row = CodRemittance::create(['request_key' => $input['request_key'], 'rider_id' => $rider->id,
                    'sorting_center_id' => $input['sorting_center_id'], 'submitted_amount' => $this->money($amount), 'evidence_path' => $path, 'status' => 'submitted']);
                app(LogisticsAudit::class)->record($actor, 'cod_remittance_submitted', data: ['remittance_id' => $row->id, 'submitted_amount' => $row->submitted_amount], riderId: $rider->id, centerId: $row->sorting_center_id);
                return $row;
            }, 5);
        } catch (\Throwable $error) { if ($path) Storage::disk('local')->delete($path); throw $error; }
    }
    public function confirm(User $actor, int $id, array $input, UploadedFile $evidence): CodRemittance
    {
        $this->requireStorage(); $centerId = $actor->sortingCenter->id; $path = null;
        $initial = CodRemittance::where('sorting_center_id', $centerId)->findOrFail($id);
        try {
            return DB::transaction(function () use ($actor, $id, $input, $evidence, $centerId, $initial, &$path) {
                $rider = Rider::whereKey($initial->rider_id)->lockForUpdate()->firstOrFail();
                $row = CodRemittance::where('sorting_center_id', $centerId)->whereKey($id)->lockForUpdate()->firstOrFail();
                abort_unless($row->status === 'submitted', 409, 'This remittance has already been confirmed.');
                $received = $this->cents((string) $input['amount']); $balance = $this->balance($rider->id, $centerId);
                $otherPending = $balance['pending'] - $this->cents($row->submitted_amount);
                abort_if($received > $balance['outstanding'] - $otherPending, 422, 'Received cash exceeds recorded cash available for this remittance.');
                $difference = $received - $this->cents($row->submitted_amount);
                if ($input['declare_shortfall'] ?? false) {
                    abort_if($otherPending !== 0, 422, 'Review other submitted remittances before recording the remaining shortfall.');
                    $remaining = $balance['outstanding'] - $received;
                    abort_unless($remaining > 0, 422, 'There is no remaining shortfall to record.');
                    $difference = -$remaining;
                }
                if ($difference !== 0) abort_unless(mb_strlen(trim($input['reason'] ?? '')) >= 10, 422, 'A discrepancy reason of at least 10 characters and evidence are required.');
                $path ??= $evidence->store('cod-evidence', 'local'); abort_unless($path, 500, 'Receipt evidence could not be saved.');
                $row->update(['received_amount' => $this->money($received), 'receipt_evidence_path' => $path,
                    'status' => 'confirmed', 'confirmed_by' => $actor->id, 'confirmed_at' => now()]);
                if ($difference !== 0) {
                    $case = CodDiscrepancy::create(['cod_remittance_id' => $row->id, 'difference' => $this->money($difference),
                        'reason' => $input['reason'], 'evidence_path' => $path, 'status' => 'pending']);
                    app(LogisticsAudit::class)->record($actor, 'cod_discrepancy_recorded', data: ['discrepancy_id' => $case->id,
                        'remittance_id' => $id, 'difference' => $case->difference, 'reason' => $case->reason], riderId: $rider->id, centerId: $centerId);
                }
                $audit = app(LogisticsAudit::class)->record($actor, 'cod_remittance_confirmed', data: ['remittance_id' => $id,
                    'submitted_amount' => $row->submitted_amount, 'received_amount' => $row->received_amount,
                    'balance_before' => $this->money($balance['outstanding']), 'balance_after' => $this->money($balance['outstanding'] - $received)], riderId: $rider->id, centerId: $centerId);
                $this->notify($rider, $audit->event_key, 'Logistics confirmed actual COD cash received: PHP '.$row->received_amount.'. This is separate from earnings and payouts.');
                return $row->fresh('discrepancy');
            }, 5);
        } catch (\Throwable $error) { if ($path) Storage::disk('local')->delete($path); throw $error; }
    }
    public function approve(User $actor, int $id, string $resolution): CodDiscrepancy
    {
        $this->requireStorage(); abort_unless($actor->usertype === 'admin' && $actor->status === 'approved', 403);
        return DB::transaction(function () use ($actor, $id, $resolution) {
            $case = CodDiscrepancy::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless($case->status === 'pending', 409, 'This discrepancy resolution has already been approved.');
            $case->update(['status' => 'approved', 'resolution' => $resolution, 'approved_by' => $actor->id, 'approved_at' => now()]);
            $remittance = $case->remittance;
            app(LogisticsAudit::class)->record($actor, 'cod_resolution_approved', data: ['discrepancy_id' => $id,
                'difference' => $case->difference, 'resolution' => $resolution, 'cash_balance_adjusted' => false],
                riderId: $remittance->rider_id, centerId: $remittance->sorting_center_id);
            // Approval records the decision. Only separately confirmed cash receipts change cash balances.
            return $case;
        }, 5);
    }
}
