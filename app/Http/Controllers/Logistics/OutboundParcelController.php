<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\{Parcel, Rider};
use App\Services\{ParcelTrackingTimeline, SellerShipmentFinance};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OutboundParcelController extends Controller
{
    public function lookup(Request $request, string $reference): JsonResponse
    {
        $parcel = Parcel::where('sorting_center_id', $request->user()->sortingCenter->id)
            ->where('order_id', $reference)->with(['sortedArea:id,name', 'assignedRider:id,name'])->first();
        abort_unless($parcel, 404, 'Parcel not found for this sorting center.');
        $this->arrived($parcel);
        return response()->json(['data' => $parcel]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $input = $request->validate(['order_id' => ['required', 'string', 'max:50']]);
        $centerId = $request->user()->sortingCenter->id;
        $result = DB::transaction(function () use ($input, $centerId): array {
            $id = Parcel::where('sorting_center_id', $centerId)->where('order_id', $input['order_id'])->value('id');
            abort_unless($id, 404, 'Parcel not found for this sorting center.');
            $shipment = app(SellerShipmentFinance::class)->lockParcel($id);
            $parcel = Parcel::where('sorting_center_id', $centerId)->lockForUpdate()->findOrFail($id);
            $this->arrived($parcel);
            abort_unless($parcel->sorted_area_id && $parcel->sorted_at, 422, 'Sort this parcel before confirming outbound readiness.');
            $rider = Rider::whereKey($parcel->assigned_rider_id)->where('sorting_center_id', $centerId)
                ->operationallyEligible()->lockForUpdate()->first();
            abort_unless($rider && $rider->areas()->where('areas.id', $parcel->sorted_area_id)->exists(), 422,
                'Assign an eligible delivery Rider covering this destination before outbound confirmation.');
            app(\App\Services\HubNetwork::class)->assertRider($parcel, $rider, 'delivery');
            if ($shipment) {
                abort_unless((int) $shipment->sorting_center_id === (int) $centerId
                    && (int) $shipment->assigned_rider_id === (int) $rider->id && !$shipment->dispatched_at,
                    422, 'The whole shipment must be assigned at this center and not yet dispatched.');
                $items = $shipment->items()->where('status', '!=', 'cancelled')->orderBy('id')->lockForUpdate()
                    ->with(['parcel' => fn ($query) => $query->orderBy('id')->lockForUpdate()])->get();
                abort_if($items->isEmpty(), 422, 'A cancelled shipment cannot be confirmed outbound.');
                foreach ($items as $item) {
                    $member = $item->parcel;
                    abort_unless($member && $member->status === 'AT_SORTING_CENTER' && $member->sorted_area_id && $member->sorted_at
                        && (int) $member->sorting_center_id === (int) $centerId
                        && (int) $member->assigned_rider_id === (int) $rider->id
                        && (int) $member->seller_id === (int) $shipment->seller_id
                        && $member->delivery_address === $shipment->order->shipping_address
                        && trim((string) $member->collection_address) === trim((string) $parcel->collection_address)
                        && $rider->areas()->where('areas.id', $member->sorted_area_id)->exists(),
                        422, 'Every shipment parcel must have arrived, been sorted and assigned to the same eligible Rider.');
                }
            }
            $event = $parcel->orderItem?->statusHistories()->firstOrCreate(
                ['note' => ParcelTrackingTimeline::OUTBOUND], ['status' => $parcel->orderItem->status]
            );
            return ['parcel' => $parcel->fresh(['assignedRider:id,name', 'sortedArea:id,name']),
                'confirmed_at' => $event?->created_at?->toISOString()];
        });
        return response()->json(['message' => 'Outbound readiness confirmed. The assigned Rider can start delivery.', 'data' => $result]);
    }

    private function arrived(Parcel $parcel): void
    {
        abort_unless($parcel->status === 'AT_SORTING_CENTER', 422,
            'Outbound requires confirmed physical arrival and status AT_SORTING_CENTER. Current status: '.$parcel->status.'.');
    }
}
