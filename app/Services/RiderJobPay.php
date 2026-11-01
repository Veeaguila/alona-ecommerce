<?php

namespace App\Services;

use App\Models\{HubTransfer, Parcel, Rider, RiderPayBatch, RiderPayDestination, RiderPayEarning, RiderPaySetting, RiderPayTrip, User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Illuminate\Validation\ValidationException;

/** Manual company-payroll records. Never moves provider funds or offsets COD. */
class RiderJobPay
{
    public function installed(): bool
    {
        return Schema::hasTable('rider_pay_earnings') && Schema::hasTable('rider_pay_settings')
            && Schema::hasTable('rider_pay_batches') && Schema::hasTable('rider_pay_destinations')
            && Schema::hasTable('rider_pay_batch_items') && Schema::hasTable('rider_pay_trips')
            && Schema::hasColumn('hub_transfers', 'rider_pay_trip_id');
    }

    public function requireStorage(): void
    {
        abort_unless($this->installed(), 503, 'Job earnings require the reviewed Rider-pay schema upgrade. Existing delivery and historical earnings remain available.');
    }

    public function owner(User $actor): int
    {
        return (int) app(HubAccess::class)->owner($actor->fresh())->id;
    }

    public function rider(User $actor): Rider
    {
        $fresh = $actor->fresh();
        abort_unless($fresh && $fresh->usertype === 'rider' && $fresh->status === 'approved', 403);
        return Rider::where('user_id', $fresh->id)->operationallyEligible()->firstOrFail();
    }

    public function cents(string $amount): int
    {
        if (!preg_match('/\A(0|[1-9][0-9]{0,8})(?:\.([0-9]{1,2}))?\z/', $amount, $match)) {
            throw ValidationException::withMessages(['amount' => 'Enter a PHP amount with no more than two decimals.']);
        }
        return (int) $match[1] * 100 + (int) str_pad($match[2] ?? '', 2, '0');
    }

    public function money(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public function settings(int $center): array
    {
        $row = RiderPaySetting::where('sorting_center_id', $center)->first();
        return ['enabled' => $row?->enabled ?? false, 'revision' => $row?->revision ?? 0,
            'pickup' => $row?->pickup_cents === null ? null : $this->money($row->pickup_cents),
            'delivery' => $row?->delivery_cents === null ? null : $this->money($row->delivery_cents),
            'transfer' => $row?->transfer_cents === null ? null : $this->money($row->transfer_cents),
            'currency' => 'PHP', 'work_unit' => 'seller_shipment', 'transfer_unit' => 'complete_manifest'];
    }

    public function configure(User $actor, array $input): array
    {
        $this->requireStorage();
        DB::transaction(function () use ($actor, $input) {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $center = $this->owner($actor);
            $row = RiderPaySetting::firstOrCreate(['sorting_center_id' => $center], ['revision' => 0]);
            $row = RiderPaySetting::whereKey($row->id)->lockForUpdate()->firstOrFail();
            abort_unless($row->revision === (int) $input['revision'], 409, 'Rates changed. Refresh before saving.');
            $values = ['enabled' => (bool) $input['enabled'], 'revision' => $row->revision + 1, 'updated_by' => $actor->id];
            foreach (['pickup', 'delivery', 'transfer'] as $leg) {
                $amount = isset($input[$leg]) ? $this->cents((string) $input[$leg]) : null;
                if (($input['enabled'] && $leg !== 'transfer' && !$amount) || ($amount !== null && ($amount <= 0 || $amount > 100000000))) {
                    throw ValidationException::withMessages([$leg => 'Set a positive approved rate of at most PHP 1,000,000.00 before enabling job earnings.']);
                }
                $values[$leg.'_cents'] = $leg === 'transfer' && array_key_exists($leg, $input)
                    ? $amount : ($amount ?? $row->{$leg.'_cents'});
            }
            $row->fill($values)->save();
            $this->event($actor, $center, 'pay_rates_saved', ['revision' => $row->revision, 'enabled' => $row->enabled]);
        }, 5);
        return $this->settings($this->owner($actor));
    }

    private function event(User $actor, int $center, string $action, array $data, ?int $rider = null): void
    {
        app(LogisticsAudit::class)->record($actor, $action, data: $data, riderId: $rider, centerId: $center);
    }

    private function workKey(Parcel $parcel, string $leg): string
    {
        return 'order:'.$parcel->orderItem->order_id.':seller:'.$parcel->seller_id.':'.$leg;
    }

    /** Enabled new jobs share one Seller-order unit. Parent order locks precede parcels. */
    public function groupedMembers(int $parcelId, int $center, ?string $newLeg = null): ?\Illuminate\Database\Eloquent\Collection
    {
        if (!$this->installed()) return null;
        $peek = Parcel::with('orderItem.order')->find($parcelId);
        if (!$peek || $peek->orderItem?->order?->delivery_fee_payer !== 'buyer') return null;
        $prefix = 'order:'.$peek->orderItem->order_id.':seller:'.$peek->seller_id.':';
        $enabled = RiderPaySetting::where('sorting_center_id', $center)->where('enabled', true)->exists();
        $active = RiderPayEarning::where('sorting_center_id', $center)->whereIn('active_key', [$prefix.'pickup', $prefix.'delivery'])->exists();
        if (!$active && (!$enabled || $newLeg === null)) return null;
        $field = $newLeg === 'delivery' ? 'assigned_rider_id' : 'pickup_rider_id';
        if (!$active && $peek->$field !== null) return null; // Never regroup pre-existing assignments on activation.
        \App\Models\Order::whereKey($peek->orderItem->order_id)->lockForUpdate()->firstOrFail();
        $items = \App\Models\OrderItem::where('order_id', $peek->orderItem->order_id)->where('status', '!=', 'cancelled')
            ->whereHas('product', fn ($query) => $query->where('seller_id', $peek->seller_id))->orderBy('id')->lockForUpdate()->get();
        $parcels = Parcel::whereIn('order_item_id', $items->modelKeys())->orderBy('id')->lockForUpdate()->get();
        if (!$active && $parcels->contains(fn ($row) => $row->$field !== null)) return null;
        abort_unless($items->isNotEmpty() && $parcels->count() === $items->count(), 422, 'Mark every item in this Seller shipment Ready for Pickup before assigning or moving the shipment.');
        abort_unless($parcels->every(fn ($row) => (int) $row->seller_id === (int) $peek->seller_id
            && (!$row->sorting_center_id || (int) $row->sorting_center_id === $center)
            && $row->delivery_address === $peek->delivery_address && $row->collection_address === $peek->collection_address),
            422, 'A paid shipment job must share one company, Seller, pickup address and destination.');
        return $parcels;
    }

    /** Called under the existing assignment transaction, never on a historic list/read. */
    public function assign(Parcel $parcel, int $riderId, string $leg, User $actor, ?\Illuminate\Database\Eloquent\Collection $members = null): void
    {
        if (!$this->installed() || !in_array($leg, ['pickup', 'delivery'], true)
            || !$members || $parcel->orderItem?->order?->delivery_fee_payer !== 'buyer') return;
        $key = $this->workKey($parcel, $leg);
        $previous = RiderPayEarning::where('active_key', $key)->lockForUpdate()->first();
        if ($previous && (int) $previous->rider_id === $riderId) return;
        abort_if($previous?->status === 'earned', 409, 'Completed payable work cannot be reassigned.');
        if ($previous) {
            $previous->update(['status' => 'cancelled', 'active_key' => null, 'cancelled_at' => now()]);
            $this->event($actor, (int) $previous->sorting_center_id, 'pay_assignment_cancelled', ['earning_id' => $previous->id], (int) $previous->rider_id);
        }
        $setting = RiderPaySetting::where('sorting_center_id', $parcel->sorting_center_id)->where('enabled', true)->lockForUpdate()->first();
        if (!$setting || !$setting->{$leg.'_cents'}) return;
        $members = Parcel::whereIn('id', $members->modelKeys())->orderBy('id')->lockForUpdate()->get();
        abort_unless($members && $members->every(fn ($member) => (int) $member->{$leg === 'pickup' ? 'pickup_rider_id' : 'assigned_rider_id'} === $riderId), 422, 'Assign the whole Seller shipment to the same Rider before creating its pay snapshot.');
        $earning = RiderPayEarning::create(['sorting_center_id' => $parcel->sorting_center_id, 'rider_id' => $riderId,
            'parcel_id' => $parcel->id, 'leg' => $leg, 'work_key' => $key, 'active_key' => $key,
            'amount_cents' => $setting->{$leg.'_cents'}, 'rate_revision' => $setting->revision,
            'snapshot' => ['reference' => $parcel->orderItem->order->order_number, 'order_id' => $parcel->orderItem->order_id,
                'seller_id' => $parcel->seller_id, 'unit' => 'seller_shipment', 'parcel_ids' => $members->modelKeys()]]);
        $this->event($actor, (int) $parcel->sorting_center_id, 'pay_assignment_snapshotted', ['earning_id' => $earning->id, 'amount_cents' => $earning->amount_cents, 'leg' => $leg], $riderId);
    }

    /** Same unfinished job/rate, with a new custodian after both Riders and Logistics confirm. */
    public function handoverPending(\App\Models\ParcelHandover $handover, \Illuminate\Database\Eloquent\Collection $members, User $actor): void
    {
        if (!$this->installed() || !$members->first()?->orderItem) return;
        $key = $this->workKey($members->first(), $handover->leg);
        $previous = RiderPayEarning::where('sorting_center_id', $handover->sorting_center_id)
            ->where('active_key', $key)->lockForUpdate()->first();
        if (!$previous) return; // Existing work without an agreed pay snapshot is never backfilled.
        abort_unless($previous->status === 'pending' && !$previous->batch_id
            && (int) $previous->rider_id === (int) $handover->from_rider_id, 409, 'This job earning requires review before transferring custody.');
        $expected = Parcel::whereIn('id', $previous->snapshot['parcel_ids'])
            ->whereHas('orderItem', fn ($query) => $query->where('status', '!=', 'cancelled'))->pluck('id')->all();
        $actual = $members->modelKeys(); sort($expected); sort($actual);
        $field = $handover->leg === 'pickup' ? 'pickup_rider_id' : 'assigned_rider_id';
        abort_unless($actual === $expected && $members->every(fn ($member) =>
            (int) $member->$field === (int) $handover->to_rider_id
            && (int) $member->sorting_center_id === (int) $previous->sorting_center_id),
            409, 'Transfer the complete agreed shipment job to one receiving Rider.');
        $previous->update(['status' => 'cancelled', 'active_key' => null, 'cancelled_at' => now()]);
        $next = RiderPayEarning::create(['sorting_center_id' => $previous->sorting_center_id, 'rider_id' => $handover->to_rider_id,
            'parcel_id' => $previous->parcel_id, 'leg' => $previous->leg, 'work_key' => $key, 'active_key' => $key,
            'amount_cents' => $previous->amount_cents, 'rate_revision' => $previous->rate_revision,
            'snapshot' => array_merge($previous->snapshot, ['handover_id' => $handover->id, 'previous_earning_id' => $previous->id])]);
        $this->event($actor, (int) $previous->sorting_center_id, 'pay_handover_snapshotted',
            ['handover_id' => $handover->id, 'previous_earning_id' => $previous->id, 'earning_id' => $next->id,
                'amount_cents' => $next->amount_cents, 'leg' => $next->leg], (int) $next->rider_id);
    }

    public function complete(Parcel $parcel, string $leg, User $actor): void
    {
        if (!$this->installed()) return;
        if (!$parcel->orderItem) return;
        $earning = RiderPayEarning::where('active_key', $this->workKey($parcel, $leg))->lockForUpdate()->first();
        if (!$earning || $earning->status !== 'pending') return;
        $field = $leg === 'pickup' ? 'pickup_rider_id' : 'assigned_rider_id';
        abort_unless((int) $parcel->$field === (int) $earning->rider_id
            && (int) $parcel->sorting_center_id === (int) $earning->sorting_center_id, 409, 'The completed work differs from its earning snapshot.');
        $members = Parcel::whereIn('id', $earning->snapshot['parcel_ids'])->orderBy('id')->lockForUpdate()->get();
        abort_unless($members->count() === count($earning->snapshot['parcel_ids']), 409, 'The shipment manifest needs review.');
        $active = $members->filter(fn ($member) => $member->orderItem?->status !== 'cancelled');
        if ($active->isEmpty()) return;
        foreach ($active as $member) {
            abort_unless((int) $member->$field === (int) $earning->rider_id && (int) $member->sorting_center_id === (int) $earning->sorting_center_id,
                409, 'Every shipment parcel must belong to the snapshotted Rider and company.');
            if ($leg === 'pickup') {
                // A recorded origin receipt remains authoritative after later hub movements.
                if ($member->status !== 'AT_SORTING_CENTER' && !app(HubNetwork::class)->journey($member)?->origin_received_at) return;
            } else {
                if ($leg !== 'delivery' || $member->status !== 'DELIVERED') return;
                abort_unless($member->deliveryProofs()->where('rider_id', $earning->rider_id)->where('type', 'PHOTO')->get()->contains(fn ($proof) => $proof->isValidPhoto()),
                    422, 'Each delivered parcel requires its completing Rider photo.');
            }
        }
        $this->earn($earning, $actor);
    }

    private function earn(RiderPayEarning $earning, User $actor): void
    {
        $earning->update(['status' => 'earned', 'earned_at' => now()]);
        $this->event($actor, (int) $earning->sorting_center_id, 'pay_job_earned', ['earning_id' => $earning->id,
            'amount_cents' => $earning->amount_cents, 'leg' => $earning->leg], (int) $earning->rider_id);
    }

    public function destination(User $actor, array $input): RiderPayDestination
    {
        $this->requireStorage();
        return DB::transaction(function () use ($actor, $input) {
            $rider = $this->rider($actor);
            Rider::whereKey($rider->id)->lockForUpdate()->firstOrFail();
            $number = trim($input['account_number'] ?? '');
            if ($input['method'] === 'gcash' && !preg_match('/\A09[0-9]{9}\z/', $number)) {
                throw ValidationException::withMessages(['account_number' => 'Enter the 11-digit GCash mobile number starting with 09.']);
            }
            if ($input['method'] === 'bank' && (!preg_match('/\A[A-Za-z0-9 -]{6,40}\z/', $number) || empty(trim($input['institution'] ?? '')))) {
                throw ValidationException::withMessages(['account_number' => 'Enter your bank and its receiving account number.']);
            }
            $label = $input['method'] === 'cash' ? 'Cash · '.$input['account_name']
                : ucfirst($input['method']).' · '.substr($number, -4).' · '.$input['account_name'];
            $row = RiderPayDestination::create(['rider_id' => $rider->id, 'method' => $input['method'], 'label' => $label,
                'instructions' => ['account_name' => trim($input['account_name']), 'account_number' => $input['method'] === 'cash' ? null : $number,
                    'institution' => $input['method'] === 'bank' ? trim($input['institution']) : null], 'created_at' => now()]);
            $this->event($actor, (int) $rider->sorting_center_id, 'pay_destination_saved', ['destination_id' => $row->id], (int) $rider->id);
            return $row;
        }, 5);
    }

    public function prepare(User $actor, array $input): RiderPayBatch
    {
        $this->requireStorage();
        return DB::transaction(function () use ($actor, $input) {
            $center = $this->owner($actor);
            $rider = Rider::where('sorting_center_id', $center)->whereKey($input['rider_id'])->operationallyEligible()->lockForUpdate()->firstOrFail();
            $ids = array_map('intval', $input['earning_ids']); sort($ids);
            $hash = hash('sha256', json_encode([$center, $rider->id, (int) $input['destination_id'], $ids, $input['expected_amount']]));
            if ($old = RiderPayBatch::where('request_key', $input['request_key'])->lockForUpdate()->first()) {
                abort_unless((int) $old->sorting_center_id === $center && hash_equals($old->request_hash, $hash), 409, 'This payout reference belongs to a different review.');
                return $old;
            }
            $destination = RiderPayDestination::where('rider_id', $rider->id)->findOrFail($input['destination_id']);
            $rows = RiderPayEarning::where('sorting_center_id', $center)->where('rider_id', $rider->id)
                ->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            abort_unless($rows->count() === count($ids) && $rows->every(fn ($row) => $row->status === 'earned' && $row->batch_id === null),
                409, 'Some jobs are no longer eligible. Refresh the earning list before preparing payment.');
            $total = (int) $rows->sum('amount_cents');
            abort_unless($total > 0 && $total === $this->cents($input['expected_amount']), 409, 'The reviewed payout total changed. Refresh before continuing.');
            $batch = RiderPayBatch::create(['request_key' => $input['request_key'], 'request_hash' => $hash,
                'sorting_center_id' => $center, 'rider_id' => $rider->id, 'destination_id' => $destination->id,
                'amount_cents' => $total, 'items_count' => $rows->count(), 'prepared_by' => $actor->id, 'status' => 'prepared']);
            foreach ($rows as $row) {
                $row->update(['batch_id' => $batch->id]);
                DB::table('rider_pay_batch_items')->insert(['batch_id' => $batch->id, 'earning_id' => $row->id]);
            }
            $this->event($actor, $center, 'pay_batch_prepared', ['batch_id' => $batch->id, 'amount_cents' => $total, 'items_count' => $rows->count()], (int) $rider->id);
            return $batch;
        }, 5);
    }

    private function lockedBatch(User $actor, int $id): RiderPayBatch
    {
        $center = $this->owner($actor);
        $peek = RiderPayBatch::where('sorting_center_id', $center)->findOrFail($id);
        Rider::whereKey($peek->rider_id)->lockForUpdate()->firstOrFail();
        return RiderPayBatch::where('sorting_center_id', $center)->lockForUpdate()->findOrFail($id);
    }

    public function pay(User $actor, int $id, array $input, UploadedFile $proof): RiderPayBatch
    {
        $this->requireStorage(); $path = null;
        try {
            return DB::transaction(function () use ($actor, $id, $input, $proof, &$path) {
                $batch = $this->lockedBatch($actor, $id);
                $reference = strtoupper(trim($input['reference']));
                $referenceHash = hash('sha256', $reference);
                $proofHash = hash_file('sha256', $proof->getRealPath());
                abort_unless($this->cents($input['expected_amount']) === $batch->amount_cents, 409, 'Pay the exact reviewed batch amount.');
                if ($batch->status === 'paid') {
                    abort_unless($batch->payment_key === $input['payment_key'] && $batch->reference_hash === $referenceHash
                        && $batch->proof_sha256 === $proofHash, 409, 'This payout already has an actual payment.');
                    return $batch;
                }
                abort_unless($batch->status === 'prepared', 409, 'Only prepared payouts can be paid.');
                abort_unless(Rider::whereKey($batch->rider_id)->where('sorting_center_id', $batch->sorting_center_id)->operationallyEligible()->exists(), 422, 'The payee account needs authorized review before payment.');
                abort_if(RiderPayBatch::where('payment_key', $input['payment_key'])->orWhere(fn ($query) => $query
                    ->where('sorting_center_id', $batch->sorting_center_id)->where('reference_hash', $referenceHash))->exists(), 409, 'This payment reference is already recorded.');
                $rows = RiderPayEarning::where('batch_id', $batch->id)->orderBy('id')->lockForUpdate()->get();
                abort_unless($rows->count() === $batch->items_count && (int) $rows->sum('amount_cents') === $batch->amount_cents
                    && $rows->every(fn ($row) => $row->status === 'earned' && (int) $row->rider_id === (int) $batch->rider_id), 409, 'The reserved payout needs review.');
                $path ??= $proof->store('rider-payout-proofs', 'local');
                abort_unless($path, 500, 'Payment evidence could not be saved.');
                $batch->update(['status' => 'paid', 'payment_key' => $input['payment_key'], 'reference' => $reference,
                    'reference_hash' => $referenceHash, 'proof_path' => $path, 'proof_sha256' => $proofHash, 'paid_by' => $actor->id, 'paid_at' => now()]);
                $this->event($actor, (int) $batch->sorting_center_id, 'pay_batch_paid', ['batch_id' => $id, 'amount_cents' => $batch->amount_cents,
                    'funding_source' => 'company_payroll', 'has_private_proof' => true], (int) $batch->rider_id);
                return $batch;
            }, 5);
        } catch (\Throwable $error) {
            if ($path) Storage::disk('local')->delete($path);
            throw $error;
        }
    }

    public function cancel(User $actor, int $id, string $reason): RiderPayBatch
    {
        return DB::transaction(function () use ($actor, $id, $reason) {
            $batch = $this->lockedBatch($actor, $id);
            if ($batch->status === 'cancelled') return $batch;
            abort_unless($batch->status === 'prepared', 409, 'An actual payment cannot be cancelled as unsent.');
            foreach (RiderPayEarning::where('batch_id', $id)->orderBy('id')->lockForUpdate()->get() as $row) $row->update(['batch_id' => null]);
            $batch->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            $this->event($actor, (int) $batch->sorting_center_id, 'pay_batch_cancelled_unsent', ['batch_id' => $id, 'reason' => $reason], (int) $batch->rider_id);
            return $batch;
        }, 5);
    }

    public function acknowledge(User $actor, int $id): RiderPayBatch
    {
        return DB::transaction(function () use ($actor, $id) {
            $rider = $this->rider($actor);
            Rider::whereKey($rider->id)->lockForUpdate()->firstOrFail();
            $batch = RiderPayBatch::where('rider_id', $rider->id)->lockForUpdate()->findOrFail($id);
            abort_unless($batch->status === 'paid', 409, 'Only an actual recorded payment can be acknowledged.');
            if (!$batch->acknowledged_at) {
                $batch->update(['acknowledged_at' => now()]);
                $this->event($actor, (int) $batch->sorting_center_id, 'pay_batch_acknowledged', ['batch_id' => $id], (int) $rider->id);
            }
            return $batch;
        }, 5);
    }

    public function dispatchTrip(User $actor, array $input): RiderPayTrip
    {
        $this->requireStorage();
        return app(RiderAllocation::class)->run([(int) $input['rider_id']], fn () => DB::transaction(function () use ($actor, $input) {
            $center = $this->owner($actor);
            $rider = Rider::where('sorting_center_id', $center)->whereKey($input['rider_id'])->operationallyEligible()->firstOrFail();
            $ids = array_map('intval', $input['parcel_ids']); sort($ids);
            if ($old = RiderPayTrip::where('request_key', $input['request_key'])->first()) {
                abort_unless((int) $old->sorting_center_id === $center && (int) $old->rider_id === (int) $rider->id
                    && (int) $old->origin_hub_id === (int) $input['hub_id'] && $old->transport_reference === trim($input['transport_reference'])
                    && $old->manifest['requested_ids'] === $ids, 409, 'This trip reference belongs to another manifest.');
                return $old;
            }
            $parcels = []; $groups = [];
            foreach ($ids as $id) {
                if (isset($parcels[$id])) continue;
                $members = app(PickupDispatch::class)->members($id, $center); $groups[] = $id;
                foreach ($members as $member) $parcels[$member->id] = $member;
            }
            ksort($parcels);
            abort_unless($ids === array_keys($parcels), 422, 'Select every parcel in each Seller shipment before dispatching its trip manifest.');
            $rider = Rider::whereKey($rider->id)->operationallyEligible()->lockForUpdate()->firstOrFail();
            app(RiderAllocation::class)->assertCanReceive($rider, reset($parcels), 'pickup', true);
            $journeys = \App\Models\ParcelHubJourney::where('sorting_center_id', $center)->whereIn('parcel_id', array_keys($parcels))->orderBy('parcel_id')->lockForUpdate()->get();
            abort_unless($journeys->count() === count($parcels) && $journeys->pluck('origin_hub_id')->unique()->count() === 1
                && $journeys->pluck('destination_hub_id')->unique()->count() === 1
                && (int) $journeys->first()->origin_hub_id === (int) $input['hub_id']
                && (int) $rider->logistics_hub_id === (int) $input['hub_id'], 422, 'Choose one origin, one destination and a Rider based at the origin hub.');
            abort_if(RiderPayTrip::where('sorting_center_id', $center)->where('transport_reference', trim($input['transport_reference']))->exists(), 409, 'This trip/manifest reference already exists.');
            $setting = RiderPaySetting::where('sorting_center_id', $center)->where('enabled', true)->lockForUpdate()->first();
            abort_unless($setting && $setting->transfer_cents > 0, 422, 'Configure approved transfer pay before dispatching a compensated trip.');
            $trip = RiderPayTrip::create(['request_key' => $input['request_key'], 'sorting_center_id' => $center, 'rider_id' => $rider->id,
                'origin_hub_id' => $input['hub_id'], 'destination_hub_id' => $journeys->first()->destination_hub_id,
                'transport_reference' => trim($input['transport_reference']), 'manifest' => ['requested_ids' => $ids, 'parcel_ids' => array_keys($parcels)],
                'dispatched_by' => $actor->id, 'dispatched_at' => now()]);
            foreach ($groups as $id) {
                $transfers = app(HubTransport::class)->dispatch($actor, $center, $id, ['hub_id' => $input['hub_id'],
                    'request_key' => (string) \Illuminate\Support\Str::uuid(), 'transport_reference' => $trip->transport_reference]);
                foreach ($transfers as $transfer) $transfer->update(['rider_pay_trip_id' => $trip->id]);
            }
            $key = 'trip:'.$trip->id.':transfer';
            RiderPayEarning::create(['sorting_center_id' => $center, 'rider_id' => $rider->id, 'trip_id' => $trip->id,
                'leg' => 'transfer', 'work_key' => $key, 'active_key' => $key, 'amount_cents' => $setting->transfer_cents,
                'rate_revision' => $setting->revision, 'snapshot' => ['reference' => $trip->transport_reference, 'unit' => 'complete_manifest', 'parcel_count' => count($parcels)]]);
            $this->event($actor, $center, 'pay_trip_dispatched', ['trip_id' => $trip->id, 'parcel_ids' => array_keys($parcels)], (int) $rider->id);
            return $trip;
        }, 5));
    }

    /** Serialize all destination receipts of a paid trip before locking its parcels. */
    public function lockTrip(int $center, int $parcelId): void
    {
        if (!$this->installed()) return;
        $id = DB::table('hub_transfers as t')->join('parcel_hub_journeys as j', 'j.id', '=', 't.parcel_hub_journey_id')
            ->where('j.sorting_center_id', $center)->where('j.parcel_id', $parcelId)->value('t.rider_pay_trip_id');
        if ($id) RiderPayTrip::where('sorting_center_id', $center)->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function completeTrip(HubTransfer $transfer, User $actor): void
    {
        if (!$this->installed() || !$transfer->rider_pay_trip_id) return;
        $trip = RiderPayTrip::whereKey($transfer->rider_pay_trip_id)->lockForUpdate()->firstOrFail();
        if ($trip->completed_at) return;
        $rows = HubTransfer::where('rider_pay_trip_id', $trip->id)->orderBy('id')->lockForUpdate()->get();
        if ($rows->count() !== count($trip->manifest['parcel_ids']) || $rows->contains(fn ($row) => !$row->received_at)) return;
        $earning = RiderPayEarning::where('active_key', 'trip:'.$trip->id.':transfer')->lockForUpdate()->firstOrFail();
        abort_unless($earning->status === 'pending', 409, 'The trip earning needs review.');
        $trip->update(['completed_at' => now()]);
        $this->earn($earning, $actor);
    }
}
