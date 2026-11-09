<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;

use App\Models\Parcel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiderPickupController extends Controller
{
    public function available(Request $request): JsonResponse
    {
        $centerId = $request->user()->rider->sorting_center_id;
        abort_unless($centerId, 403, 'A sorting center assignment is required for pickups.');
        app(\App\Services\NearestPickupOffer::class)->advanceForCenter((int) $centerId);
        $pickups = Parcel::query()
            ->where('sorting_center_id', $centerId)
            ->with([
                'pickupRequests' => fn ($query) => $query
                    ->where('status', 'confirmed')
                    ->with('seller:id,name,email'),
            ])
            ->whereIn('status', ['PLACED', 'PREPARING'])
            ->when(app(\App\Services\HubNetwork::class)->available(), fn ($query) => $query->where(fn ($query) => $query
                ->whereDoesntHave('hubJourney')->orWhereHas('hubJourney', fn ($journey) => $journey
                    ->where('origin_hub_id', $request->user()->rider->logistics_hub_id))))
            ->whereNull('pickup_rider_id')
            ->whereHas('pickupRequests', fn ($query) => $query->where('status', 'confirmed'))
            ->orderByDesc('created_at')
            ->get([
                'id',
                'order_id',
                'seller_id',
                'status',
                'order_item_id',
                'delivery_address',
                'collection_address',
                'delivery_province',
                'delivery_municipality',
                'delivery_barangay',
                'delivery_street',
                'delivery_house_number',
                'pickup_rider_id',
            ]);

        app(\App\Services\RiderParcelDetails::class)->append($pickups, true);
        $this->appendAssignmentState($pickups);
        if (\Illuminate\Support\Facades\Schema::hasTable('pickup_offers')) {
            $offers = \App\Models\PickupOffer::whereIn('parcel_id', $pickups->modelKeys())->get()->keyBy('parcel_id');
            $riderId = (int) $request->user()->rider->id;
            $pickups = $pickups->filter(function ($parcel) use ($offers, $riderId) {
                $offer = $offers->get($parcel->id);
                if (!app(\App\Services\NearestPickupOffer::class)->active($offer)) return true;
                if ((int) $offer->rider_id !== $riderId) return false;
                $parcel->setAttribute('nearest_offer_expires_at', $offer->expires_at);
                return true;
            })->values();
        }
        return response()->json([
            'message' => 'Available pickup jobs retrieved successfully.',
            'data' => $pickups,
        ]);
    }

    public function mine(Request $request): JsonResponse
    {
        $riderId = $request->user()->rider?->id;
        $input = $request->validate([
            'view' => ['sometimes', 'in:active,transit,history'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $view = $input['view'] ?? 'active';
        $statuses = match ($view) {
            'transit' => ['PICKED_UP'],
            'history' => ['AT_SORTING_CENTER', 'IN_HUB_TRANSIT', 'OUT_FOR_DELIVERY', 'DELIVERED', 'FAILED_RETURNED'],
            default => ['PLACED', 'PREPARING'],
        };

        $query = Parcel::query()
            ->with([
                'pickupRequests' => fn ($query) => $query->with('seller:id,name,email'),
            ])
            ->where('pickup_rider_id', $riderId)
            ->where(function ($query) use ($statuses, $view) {
                $query->whereIn('status', $statuses);
                if (in_array($view, ['transit', 'history'], true)) {
                    $query->orWhere(function ($incident) use ($view) {
                        $incident->whereIn('status', ['LOST', 'DAMAGED']);
                        $arrival = fn ($events) => $events->where('note', \App\Services\ParcelTrackingTimeline::ARRIVED);
                        if ($view === 'history') $incident->whereHas('orderItem.statusHistories', $arrival);
                        else $incident->whereDoesntHave('orderItem.statusHistories', $arrival);
                    });
                }
            })
            ->orderByDesc('updated_at')->orderByDesc('id');
        $columns = [
                'id',
                'order_id',
                'seller_id',
                'status',
                'order_item_id',
                'delivery_address',
                'collection_address',
                'delivery_province',
                'delivery_municipality',
                'delivery_barangay',
                'delivery_street',
                'delivery_house_number',
                'pickup_rider_id',
                'updated_at',
            ];
        $page = $view === 'history' ? $query->paginate($input['per_page'] ?? 20, $columns) : null;
        $pickups = $page ? $page->getCollection() : $query->get($columns);

        app(\App\Services\RiderParcelDetails::class)->append($pickups, true);
        $this->appendAssignmentState($pickups);
        return response()->json([
            'message' => 'Accepted pickup jobs retrieved successfully.',
            'data' => $pickups,
            'meta' => $page ? ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()] : null,
        ]);
    }

    public function accept(Request $request, int $parcelId): JsonResponse
    {
        $riderId = $request->user()->rider?->id;
        $centerId = $request->user()->rider->sorting_center_id;
        abort_unless($centerId, 403, 'A sorting center assignment is required for pickups.');
        $current = Parcel::where('sorting_center_id', $centerId)->findOrFail($parcelId);
        if (!in_array($current->status, ['PLACED', 'PREPARING'], true)) {
            return response()->json(['message' => 'This parcel is no longer waiting for pickup.', 'current_status' => $current->status], 422);
        }

        $parcel = app(\App\Services\PickupDispatch::class)->assign($request->user(), (int) $centerId, $parcelId, (int) $riderId, claim: true);

        return response()->json([
            'message' => 'Pickup job accepted successfully.',
            'data' => $parcel,
        ]);
    }

    private function appendAssignmentState($parcels): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('pickup_offers')) return;
        $offers = \App\Models\PickupOffer::whereIn('parcel_id', $parcels->modelKeys())->get()->keyBy('parcel_id');
        foreach ($parcels as $parcel) {
            $offer = $offers->get($parcel->id);
            $parcel->setAttribute('pickup_assignment_state', $offer && (int) $offer->rider_id === (int) $parcel->pickup_rider_id ? $offer->status : null);
        }
    }

    public function confirm(Request $request, int $parcelId): JsonResponse
    {
        $riderId = $request->user()->rider?->id;

        $parcel = DB::transaction(function () use ($parcelId, $riderId, $request): Parcel {
            app(\App\Services\RiderJobPay::class)->groupedMembers($parcelId, (int) $request->user()->rider->sorting_center_id);
            app(\App\Services\SellerShipmentFinance::class)->lockParcel($parcelId);
            $parcel = Parcel::query()->whereKey($parcelId)->lockForUpdate()->first();

            if (!$parcel) {
                abort(response()->json(['message' => 'Pickup job not found.'], 404));
            }

            if ($parcel->pickup_rider_id !== $riderId) {
                abort(response()->json([
                    'message' => 'This pickup job is not assigned to you.',
                ], 403));
            }

            if (!in_array($parcel->status, ['PLACED', 'PREPARING'], true)) {
                abort(response()->json([
                    'message' => 'This pickup cannot be confirmed because its current status is '.$parcel->status.'.',
                    'current_status' => $parcel->status,
                ], 422));
            }

            if (\Illuminate\Support\Facades\Schema::hasTable('pickup_offers')) {
                $offer = \App\Models\PickupOffer::where('parcel_id', $parcelId)->lockForUpdate()->first();
                abort_if($offer && (int) $offer->rider_id === (int) $riderId && $offer->status === 'assigned', 422,
                    'Acknowledge your assigned pickup before confirming collection.');
            }
            $parcel->update(['status' => 'PICKED_UP']);

            return $parcel->fresh([
                'pickupRequests' => fn ($query) => $query->with('seller:id,name,email'),
            ]);
        });

        return response()->json([
            'message' => 'Pickup confirmed successfully.',
            'data' => $parcel,
        ]);
    }
}
