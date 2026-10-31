<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;

use App\Models\PickupRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PickupRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'in:all,pending,confirmed'],
            // View filters derived from existing request/parcel fields; never stored as statuses.
            'stage' => ['sometimes', 'string', 'in:all,pending,accepted,assigned,PICKED_UP,arrived'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $status = $validated['status'] ?? 'pending';
        $sortingCenterId = $request->user()->sortingCenter->id;

        $query = PickupRequest::query()
            ->when(\Illuminate\Support\Facades\Schema::hasTable('logistics_audit_events'), fn ($query) => $query->with(['parcel.logisticsEvents' => fn ($events) => $events->orderBy('created_at')->orderBy('id')]))
            ->with([
                'parcel:id,order_id,order_item_id,seller_id,status,pickup_rider_id,assigned_rider_id,sorted_at,assigned_at,delivered_at,updated_at,delivery_address,collection_address,delivery_province,delivery_municipality,delivery_barangay,delivery_street,delivery_house_number',
                'parcel.pickupRider:id,name',
                'parcel.assignedRider:id,name',
                'parcel.orderItem:id,order_id,status',
                'parcel.orderItem.statusHistories' => fn ($query) => $query
                    ->where(fn ($query) => $query->whereIn('note', [\App\Services\ParcelTrackingTimeline::ACCEPTED, \App\Services\ParcelTrackingTimeline::PICKUP_ASSIGNED,
                        \App\Services\ParcelTrackingTimeline::ARRIVED, \App\Services\ParcelTrackingTimeline::SORTED,
                        \App\Services\ParcelTrackingTimeline::DELIVERY_ASSIGNED, \App\Services\ParcelTrackingTimeline::OUTBOUND, 'Courier update: PICKED UP.', 'Courier update: OUT FOR DELIVERY.', 'Courier update: DELIVERED.'])
                        ->orWhere('note', 'like', 'Delivery failed and returned:%'))
                    ->reorder('created_at')->orderBy('id'),
                'parcel.orderItem.order:id,shipping_method',
                'seller:id,name,email',
            ])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->where(function ($query) use ($sortingCenterId): void {
                $query->whereNull('sorting_center_id')
                    ->orWhere('sorting_center_id', $sortingCenterId);
            })
            ->whereHas('parcel', function ($query) use ($sortingCenterId): void {
                $query->where('sorting_center_id', $sortingCenterId)
                    ->orWhere(function ($query): void {
                        $query->whereNull('sorting_center_id')->where('pickup_requests.status', 'pending');
                    });
            });

        $stage = $validated['stage'] ?? 'all';
        if ($stage === 'pending') {
            $query->where('status', 'pending');
        } elseif (in_array($stage, ['accepted', 'assigned', 'PICKED_UP', 'arrived'], true)) {
            $query->where('status', 'confirmed')->whereHas('parcel', function ($query) use ($stage): void {
                if ($stage === 'accepted' || $stage === 'assigned') {
                    $query->whereIn('status', ['PLACED', 'PREPARING']);
                    $stage === 'accepted' ? $query->whereNull('pickup_rider_id') : $query->whereNotNull('pickup_rider_id');
                } elseif ($stage === 'PICKED_UP') {
                    // Collection and transit to the center share PICKED_UP in the existing workflow.
                    $query->where('status', 'PICKED_UP');
                } else {
                    $query->whereIn('status', ['AT_SORTING_CENTER', 'OUT_FOR_DELIVERY', 'DELIVERED', 'FAILED_RETURNED']);
                }
            });
        }

        $pickupRequests = $query
            ->orderByDesc('requested_at')
            ->orderByDesc('id')
            ->paginate(25);
        foreach ($pickupRequests->items() as $pickup) {
            $pickup->setAttribute('timeline', app(\App\Services\ParcelTrackingTimeline::class)->forPickup($pickup));
            // Only the timeline DTO is public; avoid returning the full linked item/history.
            // Shipping-method accessors still need the loaded order relation.
            $pickup->parcel?->makeHidden(['orderItem', 'logisticsEvents']);
        }

        return response()->json([
            'message' => 'Pickup requests retrieved successfully.',
            'data' => $pickupRequests,
        ]);
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        $sortingCenterId = $request->user()->sortingCenter->id;
        $pickupRequest = DB::transaction(function () use ($id, $sortingCenterId): PickupRequest {
            $pickupRequest = PickupRequest::query()->whereKey($id)->lockForUpdate()->first();

            if (!$pickupRequest) {
                abort(response()->json([
                    'message' => 'Pickup request not found.',
                ], 404));
            }

            if ($pickupRequest->sorting_center_id !== null
                && (int) $pickupRequest->sorting_center_id !== (int) $sortingCenterId) {
                abort(response()->json([
                    'message' => 'This pickup request belongs to another sorting center.',
                ], 404));
            }

            if ($pickupRequest->status !== 'pending') {
                abort(response()->json([
                    'message' => 'Only pending pickup requests can be confirmed.',
                    'status' => $pickupRequest->status,
                ], 422));
            }

            $parcel = $pickupRequest->parcel()->lockForUpdate()->first();

            if (!$parcel) {
                abort(404, 'The parcel for this pickup request no longer exists.');
            }

            if (!in_array($parcel->status, ['PENDING_PICKUP', 'PLACED', 'PREPARING'], true)) {
                abort(422, 'Only parcels awaiting pickup can have their pickup request confirmed.');
            }

            if ($parcel->sorting_center_id !== null
                && (int) $parcel->sorting_center_id !== (int) $sortingCenterId) {
                abort(response()->json([
                    'message' => 'This parcel is assigned to another sorting center.',
                ], 409));
            }

            $pickupRequest->update([
                'status' => 'confirmed',
                'sorting_center_id' => $sortingCenterId,
            ]);
            $parcel->update([
                'sorting_center_id' => $sortingCenterId,
                // Normalize legacy requests at the handoff to the rider API.
                'status' => $parcel->status === 'PENDING_PICKUP' ? 'PLACED' : $parcel->status,
            ]);
            app(\App\Services\ParcelTrackingTimeline::class)->record($parcel, \App\Services\ParcelTrackingTimeline::ACCEPTED);

            return $pickupRequest->fresh([
                'parcel:id,order_id,seller_id,status,delivery_province,delivery_municipality,delivery_barangay,delivery_street,delivery_house_number',
                'seller:id,name,email',
            ]);
        });

        return response()->json([
            'message' => 'Pickup request confirmed successfully.',
            'data' => $pickupRequest,
        ]);
    }
}
