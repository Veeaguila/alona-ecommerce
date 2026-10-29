<?php

namespace App\Services;

use App\Models\{HubTransfer, Parcel, ParcelHubJourney, User};
use Illuminate\Support\Facades\DB;

class HubTransport
{
    public function dispatch(User $actor, int $centerId, int $parcelId, array $input): array
    {
        app(HubNetwork::class)->requireStorage();
        return DB::transaction(function () use ($actor, $centerId, $parcelId, $input): array {
            // Preserve whole seller-shipment custody and the existing finance lock order.
            $members = app(PickupDispatch::class)->members($parcelId, $centerId);
            $journeys = ParcelHubJourney::where('sorting_center_id', $centerId)->whereIn('parcel_id', $members->modelKeys())
                ->orderBy('parcel_id')->lockForUpdate()->get()->keyBy('parcel_id');
            abort_unless($journeys->count() === $members->count(), 422, 'Every shipment parcel must have a booked hub route.');
            $first = $journeys->get($parcelId);
            abort_unless($first && (int) $first->origin_hub_id === (int) $input['hub_id'], 422, 'Dispatch must be confirmed at the booked origin hub.');
            $result = [];
            foreach ($members as $parcel) {
                $journey = $journeys->get($parcel->id);
                abort_unless((int) $journey->origin_hub_id === (int) $first->origin_hub_id
                    && (int) $journey->destination_hub_id === (int) $first->destination_hub_id, 422, 'All shipment parcels must use the same hub route.');
                abort_unless((int) $journey->origin_hub_id !== (int) $journey->destination_hub_id, 422, 'Local parcels do not need a hub transfer.');
                abort_if(HubTransfer::where('parcel_hub_journey_id', $journey->id)->exists(), 409, 'This shipment has already been dispatched. Refresh its transfer history.');
                abort_unless($parcel->status === 'AT_SORTING_CENTER' && !$parcel->assigned_rider_id && $journey->origin_received_at
                    && (int) $journey->current_hub_id === (int) $journey->origin_hub_id, 422,
                    'Physically receive every parcel at the origin hub before dispatch. Delivery assignments must be cleared first.');
                $result[] = HubTransfer::create(['parcel_hub_journey_id' => $journey->id,
                    'request_key' => $parcel->id === $parcelId ? $input['request_key'] : (string) \Illuminate\Support\Str::uuid(),
                    'transport_reference' => $input['transport_reference'], 'dispatched_by' => $actor->id, 'dispatched_at' => now()]);
                $journey->update(['current_hub_id' => null]);
                $parcel->update(['status' => 'IN_HUB_TRANSIT']);
                $note = 'Dispatched from '.$journey->route_snapshot['origin_hub_name'].' to '.$journey->route_snapshot['destination_hub_name'].'.';
                app(ParcelTrackingTimeline::class)->record($parcel, $note);
                app(LogisticsAudit::class)->record($actor, 'hub_transfer_dispatched', $parcel,
                    ['origin_hub_id' => $journey->origin_hub_id, 'destination_hub_id' => $journey->destination_hub_id,
                        'transport_reference' => $input['transport_reference'], 'transport_mode' => $journey->route_snapshot['transport_mode']]);
            }
            return $result;
        });
    }

    public function receive(User $actor, int $centerId, int $parcelId, int $hubId): ParcelHubJourney
    {
        app(HubNetwork::class)->requireStorage();
        return DB::transaction(function () use ($actor, $centerId, $parcelId, $hubId): ParcelHubJourney {
            app(SellerShipmentFinance::class)->lockParcel($parcelId);
            $parcel = Parcel::where('sorting_center_id', $centerId)->whereKey($parcelId)->lockForUpdate()->firstOrFail();
            $journey = ParcelHubJourney::where('sorting_center_id', $centerId)->where('parcel_id', $parcel->id)->lockForUpdate()->firstOrFail();
            $transfer = HubTransfer::where('parcel_hub_journey_id', $journey->id)->lockForUpdate()->first();
            abort_unless((int) $journey->destination_hub_id === $hubId, 422, 'Confirm receipt at the booked destination hub.');
            abort_unless($transfer, 422, 'The origin hub must dispatch this parcel first.');
            abort_if($transfer->received_at || $journey->destination_received_at, 409, 'This parcel has already been received at its destination hub.');
            abort_unless($parcel->status === 'IN_HUB_TRANSIT', 422, 'This parcel is not in hub transport.');
            $transfer->update(['received_by' => $actor->id, 'received_at' => now()]);
            $journey->update(['current_hub_id' => $hubId, 'destination_received_at' => now()]);
            $parcel->update(['status' => 'AT_SORTING_CENTER']);
            app(ParcelTrackingTimeline::class)->record($parcel, 'Received at destination hub: '.$journey->route_snapshot['destination_hub_name'].'.');
            app(LogisticsAudit::class)->record($actor, 'hub_transfer_received', $parcel, ['hub_id' => $hubId, 'transfer_id' => $transfer->id]);
            return $journey->fresh('transfer');
        });
    }
}
