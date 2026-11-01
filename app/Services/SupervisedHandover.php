<?php

namespace App\Services;

use App\Models\{Parcel, ParcelHandover, PickupOffer, Rider, RiderNotification, User};
use Illuminate\Support\Facades\{DB, Schema};

class SupervisedHandover
{
    public function requireStorage(): void
    {
        app(LogisticsAudit::class)->requireStorage();
        abort_unless(Schema::hasTable('parcel_handovers'), 503, 'Supervised handover is temporarily unavailable.');
    }

    public function request(User $actor, int $centerId, int $parcelId, array $input): ParcelHandover
    {
        $this->requireStorage();
        $parcel = Parcel::where('sorting_center_id', $centerId)->findOrFail($parcelId);
        $field = $input['leg'] === 'pickup' ? 'pickup_rider_id' : 'assigned_rider_id';
        $sourceId = (int) $parcel->$field;
        return app(RiderAllocation::class)->run([$sourceId, $input['rider_id']], fn () => DB::transaction(function () use ($actor, $centerId, $parcelId, $input, $sourceId, $field) {
            $members = app(PickupDispatch::class)->members($parcelId, $centerId);
            $parcel = $members->firstWhere('id', $parcelId);
            abort_unless($parcel, 422, 'The parcel is no longer an active shipment member.');
            $existing = ParcelHandover::where('request_key', $input['request_key'])->first();
            if ($existing) {
                abort_unless((int) $existing->parcel_id === $parcelId && (int) $existing->requested_by === (int) $actor->id
                    && (int) $existing->to_rider_id === (int) $input['rider_id'] && $existing->reason === $input['reason']
                    && $existing->leg === $input['leg'], 409, 'This request key has already been used.');
                return $existing;
            }
            abort_unless($sourceId && (int) $parcel->$field === $sourceId && $sourceId !== (int) $input['rider_id'], 422, 'Choose a different Rider from the current parcel custodian.');
            abort_unless(Rider::where('sorting_center_id', $centerId)->whereKey($sourceId)->exists(), 422, 'The current custodian does not belong to this Logistics organization.');
            $target = $this->target($centerId, (int) $input['rider_id']);
            $this->assertMembers($members, $sourceId, $input['leg'], $centerId);
            $this->assertTarget($target, $members, $input['leg']);
            abort_if(ParcelHandover::whereIn('parcel_id', $members->modelKeys())->where('status', 'pending')->exists(), 409, 'A supervised handover is already pending for this shipment.');
            $handover = ParcelHandover::create(['request_key' => $input['request_key'], 'parcel_id' => $parcelId,
                'sorting_center_id' => $centerId, 'from_rider_id' => $sourceId, 'to_rider_id' => $target->id,
                'requested_by' => $actor->id, 'leg' => $input['leg'], 'reason' => $input['reason'], 'parcel_ids' => $members->modelKeys()]);
            $event = app(LogisticsAudit::class)->record($actor, 'handover_requested', $parcel, [
                'handover_id' => $handover->id, 'from_rider_id' => $sourceId, 'to_rider_id' => $target->id,
                'parcel_ids' => $handover->parcel_ids, 'leg' => $handover->leg, 'reason' => $handover->reason,
            ]);
            $this->notify($handover, $event->event_key, 'Physical handover acknowledgment requested');
            return $handover;
        }, 5));
    }

    public function acknowledge(User $actor, int $id): ParcelHandover
    {
        $this->requireStorage();
        abort_unless($actor->usertype === 'rider' && $actor->rider, 403);
        $handover = ParcelHandover::where(fn ($query) => $query->where('from_rider_id', $actor->rider->id)->orWhere('to_rider_id', $actor->rider->id))->findOrFail($id);
        return $this->locked($handover, function ($current, $members) use ($actor) {
            $field = (int) $current->from_rider_id === (int) $actor->rider->id ? 'source_acknowledged_at' : 'target_acknowledged_at';
            abort_if($current->$field, 409, 'You have already acknowledged this physical handover.');
            $current->update([$field => now()]);
            app(LogisticsAudit::class)->record($actor, 'handover_acknowledged', $members->first(), [
                'handover_id' => $current->id, 'role' => $field === 'source_acknowledged_at' ? 'source' : 'target',
                'parcel_ids' => $current->parcel_ids,
            ], $actor->rider->id);
            return $current->fresh();
        });
    }

    public function confirm(User $actor, ParcelHandover $handover): ParcelHandover
    {
        return $this->locked($handover, function ($current, $members) use ($actor) {
            abort_unless($current->source_acknowledged_at && $current->target_acknowledged_at, 422, 'Both Riders must acknowledge the physical handover before Logistics confirms it.');
            $target = $this->target((int) $current->sorting_center_id, (int) $current->to_rider_id);
            $this->assertTarget($target, $members, $current->leg);
            $field = $current->leg === 'pickup' ? 'pickup_rider_id' : 'assigned_rider_id';
            if ($current->leg === 'delivery' && ($shipment = app(SellerShipmentFinance::class)->lockParcel($current->parcel_id))) {
                abort_if($shipment->completed_at || \App\Models\SellerDeliveryCharge::where('seller_shipment_id', $shipment->id)->exists(), 422, 'A financially completed shipment cannot be transferred.');
                $shipment->update(['assigned_rider_id' => $target->id]);
            }
            foreach ($members as $parcel) {
                $parcel->update([$field => $target->id]);
                if ($current->leg === 'delivery' && Schema::hasTable('parcel_returns')) \App\Models\ParcelReturn::where('parcel_id', $parcel->id)
                    ->whereIn('status', ['initiated', 'in_transit'])->update(['rider_id' => $target->id, 'updated_at' => now()]);
                if ($current->leg === 'pickup' && Schema::hasTable('pickup_offers')) PickupOffer::where('parcel_id', $parcel->id)->update([
                    'rider_id' => $target->id, 'kind' => 'handover', 'status' => 'accepted', 'accepted_at' => now(), 'updated_at' => now(),
                ]);
                app(LogisticsAudit::class)->record($actor, 'handover_confirmed', $parcel, [
                    'handover_id' => $current->id, 'from_rider_id' => $current->from_rider_id, 'to_rider_id' => $target->id,
                    'parcel_ids' => $current->parcel_ids, 'leg' => $current->leg, 'reason' => $current->reason,
                    'source_acknowledged_at' => $current->source_acknowledged_at->toISOString(),
                    'target_acknowledged_at' => $current->target_acknowledged_at->toISOString(),
                ], $target->id);
            }
            app(RiderJobPay::class)->handoverPending($current, $members, $actor);
            $current->update(['status' => 'confirmed', 'confirmed_by' => $actor->id, 'confirmed_at' => now()]);
            $this->notify($current, $current->request_key.':confirmed', 'Logistics confirmed physical handover');
            return $current->fresh();
        });
    }

    public function cancel(User $actor, ParcelHandover $handover, string $reason): ParcelHandover
    {
        $this->requireStorage();
        return app(RiderAllocation::class)->run([$handover->from_rider_id, $handover->to_rider_id], fn () => DB::transaction(function () use ($actor, $handover, $reason) {
            $current = ParcelHandover::whereKey($handover->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->status === 'pending', 409, 'This handover has already been resolved.');
            $current->update(['status' => 'cancelled']);
            app(LogisticsAudit::class)->record($actor, 'handover_cancelled', $current->parcel, ['handover_id' => $current->id, 'reason' => $reason]);
            $this->notify($current, $current->request_key.':cancelled', 'Logistics cancelled the handover request');
            return $current;
        }, 5));
    }

    private function locked(ParcelHandover $handover, callable $operation)
    {
        $this->requireStorage();
        return app(RiderAllocation::class)->run([$handover->from_rider_id, $handover->to_rider_id], fn () => DB::transaction(function () use ($handover, $operation) {
            $members = app(PickupDispatch::class)->members((int) $handover->parcel_id, (int) $handover->sorting_center_id);
            $current = ParcelHandover::whereKey($handover->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->status === 'pending', 409, 'This handover has already been resolved.');
            $actual = $members->modelKeys(); $expected = $current->parcel_ids; sort($actual); sort($expected);
            abort_unless($actual === $expected, 409, 'Shipment membership changed. Cancel this request and review the complete shipment.');
            $this->assertMembers($members, (int) $current->from_rider_id, $current->leg, (int) $current->sorting_center_id);
            return $operation($current, $members);
        }, 5));
    }

    private function assertMembers($members, int $sourceId, string $leg, int $centerId): void
    {
        $field = $leg === 'pickup' ? 'pickup_rider_id' : 'assigned_rider_id';
        $status = $leg === 'pickup' ? 'PICKED_UP' : 'OUT_FOR_DELIVERY';
        foreach ($members as $parcel) abort_unless($parcel->status === $status && (int) $parcel->$field === $sourceId
            && (int) $parcel->sorting_center_id === $centerId, 422,
            'Every active parcel in the shipment must still be collected and held by the original Rider on this leg.');
    }

    private function target(int $centerId, int $id): Rider
    {
        $rider = Rider::where('sorting_center_id', $centerId)->whereKey($id)->operationallyEligible()->lockForUpdate()->first();
        abort_unless($rider, 422, 'The receiving Rider must be approved, active and eligible in your Logistics organization.');
        return $rider;
    }

    private function assertTarget(Rider $target, $members, string $leg): void
    {
        app(RiderAllocation::class)->assertCanReceive($target, $members->first(), $leg, true);
        if ($leg === 'delivery') foreach ($members as $parcel) abort_unless($parcel->sorted_area_id && $target->areas()->where('areas.id', $parcel->sorted_area_id)->exists(), 422,
            'The receiving Rider must cover every destination area in the shipment.');
    }

    private function notify(ParcelHandover $handover, string $key, string $title): void
    {
        foreach (Rider::whereIn('id', [$handover->from_rider_id, $handover->to_rider_id])->get() as $rider) RiderNotification::firstOrCreate([
            'user_id' => $rider->user_id, 'event_key' => hash('sha256', 'handover:'.$key.':'.$rider->id),
        ], ['type' => 'handover', 'title' => $title, 'body' => 'Open My account to review the supervised whole-shipment handover.',
            'data' => ['screen' => 'account', 'handover_id' => $handover->id]]);
    }
}
