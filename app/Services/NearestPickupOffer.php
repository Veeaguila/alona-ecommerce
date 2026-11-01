<?php

namespace App\Services;

use App\Models\{LogisticsAuditEvent, Parcel, PickupOffer, Rider, RiderNotification, User};
use Illuminate\Support\Facades\{DB, Schema};

class NearestPickupOffer
{
    public const OFFER_SECONDS = 60;
    public const MAX_RIDERS = 3;

    public function active(?PickupOffer $offer): bool
    {
        if (!$offer || $offer->kind !== 'nearest' || $offer->status !== 'offered' || !$offer->expires_at?->isFuture()) return false;
        $rider = Rider::whereKey($offer->rider_id)->operationallyEligible()->first();
        return $rider && app(RiderPresence::class)->snapshot($rider)['presence'] === 'Online';
    }
    public function offer(User $actor, int $centerId, int $parcelId, array $tried = [], ?string $round = null): PickupOffer
    {
        $dispatch = app(PickupDispatch::class); $dispatch->requireStorage();
        return DB::transaction(function () use ($actor, $centerId, $parcelId, $dispatch, $tried, $round) {
            $round ??= (string) \Illuminate\Support\Str::uuid();
            $members = $dispatch->members($parcelId, $centerId); $parcel = $members->firstWhere('id', $parcelId);
            abort_unless($parcel, 422, 'The parcel is not an active shipment member.');
            foreach ($members as $member) {
                abort_unless((int) $member->sorting_center_id === $centerId && (int) $member->seller_id === (int) $parcel->seller_id
                    && trim((string) $member->collection_address) === trim((string) $parcel->collection_address)
                    && in_array($member->status, ['PLACED', 'PREPARING'], true) && !$member->pickup_rider_id
                    && $member->orderItem?->status !== 'cancelled'
                    && $member->pickupRequests()->where(fn ($query) => $query->where('sorting_center_id', $centerId)->orWhereNull('sorting_center_id'))->where('status', 'confirmed')->exists(), 422, 'All shipment parcels must be ready, unclaimed and accepted at the same collection location.');
                abort_if($this->active(PickupOffer::where('parcel_id', $member->id)->lockForUpdate()->first()), 409, 'This shipment already has a live nearest-Rider offer.');
            }
            $locations = app(PickupLocations::class); $seller = $locations->seller($parcel);
            abort_unless($seller, 422, 'Seller must share a fresh, permission-authorized collection point from the pickup address. Manual assignment and self-claim remain available.');
            $journey = app(HubNetwork::class)->journey($parcel);
            $riders = Rider::where('sorting_center_id', $centerId)->whereNotIn('id', $tried)->operationallyEligible()
                ->when($journey, fn ($query) => $query->where('logistics_hub_id', $journey->origin_hub_id))->get();
            app(RiderWorkload::class)->append($riders, $centerId);
            $candidates = $riders->filter(fn ($rider) => $rider->presence === 'Online' && $rider->active_jobs_count < RiderAllocation::MAX_ACTIVE_JOBS)
                ->map(function ($rider) use ($locations, $seller) {
                    $point = $locations->rider($rider);
                    return $point ? ['rider' => $rider, 'distance' => $locations->distance($seller, $point), 'uncertainty' => $seller['accuracy'] + $point['accuracy']] : null;
                })->filter()->sortBy('distance')->values();
            abort_if($candidates->isEmpty(), 422, 'No approved Online Rider with capacity has shared a fresh location. Ask Riders to share location, or use manual assignment/self-claim.');
            $nearest = $candidates->first();
            if ($candidates->count() > 1) abort_if($nearest['distance'] + $nearest['uncertainty'] >= $candidates[1]['distance'] - $candidates[1]['uncertainty'], 422,
                'Location accuracy cannot reliably distinguish the nearest Rider. Refresh locations or use manual assignment.');
            foreach ($members as $member) {
                $offer = PickupOffer::updateOrCreate(['parcel_id' => $member->id], ['sorting_center_id' => $centerId, 'rider_id' => $nearest['rider']->id,
                    'kind' => 'nearest', 'status' => 'offered', 'expires_at' => now()->addSeconds(self::OFFER_SECONDS), 'accepted_at' => null]);
                app(LogisticsAudit::class)->record($actor, 'pickup_nearest_offered', $member, ['to_rider_id' => $offer->rider_id, 'expires_at' => $offer->expires_at,
                    'distance_meters' => round($nearest['distance']), 'location_uncertainty_meters' => round($nearest['uncertainty']), 'parcel_ids' => $members->modelKeys(),
                    'round' => $round, 'attempt' => count($tried) + 1, 'tried_rider_ids' => [...$tried, (int) $offer->rider_id]], $offer->rider_id);
            }
            RiderNotification::create(['user_id' => $nearest['rider']->user_id, 'event_key' => (string) \Illuminate\Support\Str::uuid(), 'type' => 'assignment',
                'title' => 'Nearest pickup offer', 'body' => 'A pickup shipment is offered to you for 60 seconds. Open Available pickup jobs to accept or decline it.', 'data' => ['screen' => 'pickups', 'parcel_id' => $parcelId]]);
            return PickupOffer::where('parcel_id', $parcelId)->firstOrFail();
        }, 5);
    }

    public function advance(User $actor, int $centerId, int $parcelId, ?int $decliningRider = null): void
    {
        app(PickupDispatch::class)->requireStorage();
        DB::transaction(function () use ($actor, $centerId, $parcelId, $decliningRider) {
            $members = app(PickupDispatch::class)->members($parcelId, $centerId);
            $offer = PickupOffer::where('parcel_id', $parcelId)->lockForUpdate()->first();
            if ($decliningRider !== null) {
                abort_unless($offer && (int) $offer->rider_id === $decliningRider, 403, 'This offer is not addressed to you.');
                abort_unless($this->active($offer), 409, 'This offer has expired or already changed. Refresh pickup jobs.');
            } elseif ($this->active($offer)) return;
            if (!$offer || $offer->kind !== 'nearest' || $offer->status !== 'offered') return;
            // Assignment and custody always take precedence over a stale offer.
            if ($members->contains(fn ($p) => $p->pickup_rider_id || !in_array($p->status, ['PLACED', 'PREPARING'], true))) return;
            $previous = LogisticsAuditEvent::where('parcel_id', $parcelId)->where('action', 'pickup_nearest_offered')->latest('id')->first();
            $tried = $previous?->data['tried_rider_ids'] ?? [(int) $offer->rider_id];
            PickupOffer::whereIn('parcel_id', $members->modelKeys())->update(['status' => 'unassigned', 'expires_at' => null]);
            foreach ($members as $member) app(LogisticsAudit::class)->record($actor, $decliningRider !== null ? 'pickup_nearest_declined' : 'pickup_nearest_expired', $member,
                ['from_rider_id' => $offer->rider_id, 'attempt' => count($tried)], $offer->rider_id);
            if (count($tried) < self::MAX_RIDERS) {
                try {
                    $this->offer($actor, $centerId, $parcelId, $tried, $previous?->data['round'] ?? null);
                    return;
                } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error) {
                    // No eligible fresh location / ready shipment: preserve the unassigned pool.
                    if ($error->getStatusCode() !== 422) throw $error;
                }
            }
            foreach ($members as $member) app(LogisticsAudit::class)->record($actor, 'pickup_nearest_unassigned', $member,
                ['tried_rider_ids' => $tried, 'reason' => count($tried) >= self::MAX_RIDERS ? 'Three Riders did not accept.' : 'No reliably located eligible next Rider.']);
        }, 5);
    }

    public function advanceForCenter(int $centerId): void
    {
        if (!Schema::hasTable('pickup_offers')) return;
        $actor = \App\Models\SortingCenter::find($centerId)?->user;
        if (!$actor) return;
        $ids = PickupOffer::where('sorting_center_id', $centerId)->where('kind', 'nearest')->where('status', 'offered')->pluck('parcel_id');
        foreach ($ids as $id) $this->advance($actor, $centerId, (int) $id);
    }
}
