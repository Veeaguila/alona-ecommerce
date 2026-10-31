<?php

namespace App\Services;

use App\Models\{Parcel, Rider, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReleaseUncollectedPickups
{
    // Caller holds the owned Rider lock and transaction. Collected shipment siblings prevent partial release.
    public function release(Rider $rider, User $actor, string $reason): array
    {
        $candidates = Parcel::where('pickup_rider_id', $rider->id)->where('sorting_center_id', $rider->sorting_center_id)
            ->whereIn('status', ['PLACED', 'PREPARING'])->orderBy('id')->lockForUpdate()
            ->with('orderItem:id,seller_shipment_id,status')->get();
        if ($candidates->isEmpty()) return [];
        $audit = app(LogisticsAudit::class); $audit->requireStorage();
        abort_unless(Schema::hasTable('pickup_offers'), 503, 'Pickup release is temporarily unavailable. Please contact your administrator.');
        $released = [];
        foreach ($candidates->groupBy(fn ($parcel) => $parcel->orderItem?->seller_shipment_id ? 'shipment:'.$parcel->orderItem->seller_shipment_id : 'parcel:'.$parcel->id) as $parcels) {
            $shipmentId = $parcels->first()->orderItem?->seller_shipment_id;
            if ($shipmentId && Parcel::whereHas('orderItem', fn ($query) => $query->where('seller_shipment_id', $shipmentId)->where('status', '!=', 'cancelled'))
                ->whereNotIn('status', ['PLACED', 'PREPARING'])->exists()) continue;
            foreach ($parcels as $parcel) {
                if ($parcel->orderItem?->status === 'cancelled') continue;
                // An inconsistent legacy assignment must not disappear into a pool that cannot display it.
                if (!$parcel->pickupRequests()->where('status', 'confirmed')->where('sorting_center_id', $rider->sorting_center_id)->exists()) continue;
                $parcel->update(['pickup_rider_id' => null]);
                DB::table('pickup_offers')->where('parcel_id', $parcel->id)->update(['status' => 'released', 'updated_at' => now()]);
                $event = $audit->record($actor, 'pickup_released', $parcel, ['from_rider_id' => $rider->id, 'to_rider_id' => null,
                    'reason' => $reason, 'cause' => 'rider_suspended'], $rider->id);
                \App\Models\RiderNotification::firstOrCreate(['user_id' => $rider->user_id, 'event_key' => hash('sha256', 'pickup-release:'.$event->event_key)],
                    ['type' => 'assignment', 'title' => 'Uncollected pickup released', 'body' => 'Logistics returned '.$parcel->order_id.' to the available pickup pool.',
                        'data' => ['screen' => 'pickups', 'parcel_id' => $parcel->id]]);
                $released[] = $parcel->id;
            }
        }
        return $released;
    }
}
