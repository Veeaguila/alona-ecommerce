<?php

namespace App\Services;

use App\Models\{Parcel, PickupOffer, Rider, RiderNotification, User};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\{DB, Schema};

class PickupDispatch
{
    public function requireStorage(): void
    {
        app(LogisticsAudit::class)->requireStorage();
        abort_unless(Schema::hasTable('pickup_offers'), 503, 'Pickup dispatch is temporarily unavailable.');
    }

    public function members(int $parcelId, int $centerId): Collection
    {
        if ($group = app(RiderJobPay::class)->groupedMembers($parcelId, $centerId, 'pickup')) return $group;
        $shipment = app(SellerShipmentFinance::class)->lockParcel($parcelId);
        $parcel = Parcel::where('sorting_center_id', $centerId)->whereKey($parcelId)->lockForUpdate()->firstOrFail();
        if (!$shipment) return new Collection([$parcel]);
        $items = $shipment->items()->where('status', '!=', 'cancelled')->orderBy('id')->lockForUpdate()
            ->with(['parcel' => fn ($query) => $query->orderBy('id')->lockForUpdate()])->get();
        abort_if($items->isEmpty() || $items->contains(fn ($item) => !$item->parcel), 422, 'Every shipment item must have a ready pickup parcel.');
        return new Collection($items->map->parcel->all());
    }

    public function assign(User $actor, int $centerId, int $parcelId, int $riderId, ?int $fromId = null, ?string $reason = null, bool $claim = false): Parcel
    {
        if (!$claim) $this->requireStorage();
        app(NearestPickupOffer::class)->advanceForCenter($centerId);
        return app(RiderAllocation::class)->run([$riderId, $fromId], function () use ($actor, $centerId, $parcelId, $riderId, $fromId, $reason, $claim) {
            return DB::transaction(function () use ($actor, $centerId, $parcelId, $riderId, $fromId, $reason, $claim) {
                $members = $this->members($parcelId, $centerId);
                $payGroup = app(RiderJobPay::class)->groupedMembers($parcelId, $centerId, 'pickup');
                $parcel = $members->firstWhere('id', $parcelId);
                abort_unless($parcel, 422, 'The requested item is no longer an active shipment member.');
                if ($fromId) abort_unless(Rider::where('sorting_center_id', $centerId)->whereKey($fromId)->exists(), 422, 'The previous Rider does not belong to this Logistics organization.');
                $rider = Rider::where('sorting_center_id', $centerId)->whereKey($riderId)->operationallyEligible()->lockForUpdate()->first();
                abort_unless($rider, $claim ? 403 : 422, 'The Rider is not eligible for new pickup work.');
                $hasStorage = Schema::hasTable('pickup_offers') && Schema::hasTable('logistics_audit_events');
                $nearestAccepted = false;
                foreach ($members as $member) {
                    $offer = $hasStorage ? PickupOffer::where('parcel_id', $member->id)->lockForUpdate()->first() : null;
                    if (app(NearestPickupOffer::class)->active($offer)) {
                        abort_unless($claim && (int) $offer->rider_id === $riderId, 409, 'This pickup is temporarily offered to another Rider. Each nearest-Rider offer lasts 60 seconds.');
                        $nearestAccepted = true;
                    }
                    abort_unless((int) $member->sorting_center_id === $centerId && (int) $member->seller_id === (int) $parcel->seller_id
                        && trim((string) $member->collection_address) === trim((string) $parcel->collection_address), 422,
                        'All shipment parcels must use the same Logistics organization, Seller and collection location.');
                    abort_unless(in_array($member->status, ['PLACED', 'PREPARING'], true), 422,
                        'Collected parcels require a supervised handover. They cannot be reassigned here.');
                    abort_if($member->orderItem?->status === 'cancelled', 422, 'Cancelled items cannot be assigned.');
                    abort_unless(($member->pickup_rider_id === null && $fromId === null)
                        || (!$claim && $fromId !== null && (int) $member->pickup_rider_id === $fromId), 409,
                        'This pickup has already been accepted or its assignment changed. Refresh the queue.');
                    abort_unless($member->pickupRequests()->where('status', 'confirmed')->where(fn ($query) => $query->where('sorting_center_id', $centerId)->orWhereNull('sorting_center_id'))->exists(), 422,
                        'Every shipment pickup request must be accepted by Logistics first.');
                }
                abort_if($fromId !== null && $fromId === $riderId, 422, 'Choose a different Rider for reassignment.');
                abort_if($fromId !== null && mb_strlen(trim((string) $reason)) < 10, 422, 'A reassignment reason of at least 10 characters is required.');
                app(RiderAllocation::class)->assertCanReceive($rider, $parcel, 'pickup', !$claim || $nearestAccepted);
                foreach ($members as $member) {
                    if ($hasStorage) PickupOffer::updateOrCreate(['parcel_id' => $member->id], [
                        'sorting_center_id' => $centerId, 'rider_id' => $riderId, 'kind' => $nearestAccepted ? 'nearest' : ($claim ? 'self_claim' : 'manual'),
                        'status' => $claim ? 'accepted' : 'assigned', 'accepted_at' => $claim ? now() : null, 'expires_at' => null,
                    ]);
                    $member->update(['pickup_rider_id' => $riderId]);
                    if ($hasStorage) {
                        $event = app(LogisticsAudit::class)->record($actor, $fromId ? 'pickup_reassigned' : 'pickup_assigned', $member,
                            ['from_rider_id' => $fromId, 'to_rider_id' => $riderId, 'reason' => $reason,
                                'mode' => $nearestAccepted ? 'nearest' : ($claim ? 'self_claim' : 'manual'), 'parcel_ids' => $members->modelKeys()], $riderId);
                        if (!$claim) RiderNotification::firstOrCreate([
                            'user_id' => $rider->user_id, 'event_key' => hash('sha256', 'pickup-assigned:'.$event->event_key),
                        ], ['type' => 'assignment', 'title' => 'Pickup assigned — acknowledgment required',
                            'body' => 'Logistics assigned '.$member->order_id.'. Accept the assignment before collecting the parcel.',
                            'data' => ['screen' => 'pickups', 'parcel_id' => $member->id]]);
                        if ($member->orderItem) $member->orderItem->statusHistories()->create([
                            'status' => $member->orderItem->status,
                            'note' => $fromId ? 'Pickup Rider reassigned from '.(Rider::find($fromId)?->name ?? 'previous Rider').' to '.$rider->name.'.'
                                : 'Pickup Rider assigned to '.$rider->name.'.',
                        ]);
                        if ($fromId && ($old = Rider::find($fromId))) RiderNotification::firstOrCreate([
                            'user_id' => $old->user_id, 'event_key' => hash('sha256', 'pickup-reassigned:'.$event->event_key),
                        ], ['type' => 'assignment', 'title' => 'Uncollected pickup reassigned',
                            'body' => $member->order_id.' was reassigned by Logistics. Reason: '.$reason,
                            'data' => ['screen' => 'pickups', 'parcel_id' => $member->id]]);
                    }
                }
                app(RiderJobPay::class)->assign($parcel->fresh(), $riderId, 'pickup', $actor, $payGroup);
                return $parcel->fresh(['pickupRequests.seller:id,name,email']);
            }, 5);
        });
    }

    public function acknowledge(User $actor, int $parcelId): Parcel
    {
        $this->requireStorage();
        $rider = $actor->rider;
        return app(RiderAllocation::class)->run([$rider->id], fn () => DB::transaction(function () use ($actor, $parcelId, $rider) {
            $members = $this->members($parcelId, (int) $rider->sorting_center_id);
            foreach ($members as $member) {
                abort_unless((int) $member->pickup_rider_id === (int) $rider->id, 403, 'This pickup is not assigned to you.');
                abort_unless(in_array($member->status, ['PLACED', 'PREPARING'], true), 422, 'This pickup is already collected.');
                $offer = PickupOffer::where('parcel_id', $member->id)->lockForUpdate()->firstOrFail();
                abort_unless((int) $offer->rider_id === (int) $rider->id && $offer->status === 'assigned', 409, 'This assignment has already been acknowledged or changed.');
                $offer->update(['status' => 'accepted', 'accepted_at' => now()]);
                app(LogisticsAudit::class)->record($actor, 'pickup_acknowledged', $member, ['rider_id' => $rider->id], $rider->id);
            }
            return $members->firstWhere('id', $parcelId)->fresh();
        }, 5));
    }
}
