<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Parcel;
use App\Models\Rider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParcelAssignmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sortingCenterId = $request->user()->sortingCenter->id;

        $parcels = Parcel::query()
            ->with(['sortedArea:id,name', 'orderItem.order:id,shipping_method'])
            ->where('sorting_center_id', $sortingCenterId)
            ->where('status', 'AT_SORTING_CENTER')
            ->whereNotNull('sorted_area_id')
            ->whereNull('assigned_rider_id')
            ->orderByDesc('sorted_at')
            ->get([
                'id',
                'order_id',
                'status',
                'sorted_area_id',
                'sorted_at',
                'assigned_rider_id',
                'order_item_id',
                'delivery_address',
                'collection_address',
                'delivery_province',
                'delivery_municipality',
                'delivery_barangay',
                'delivery_street',
                'delivery_house_number',
            ]);

        return response()->json([
            'message' => 'Sorted unassigned parcels retrieved successfully.',
            'data' => $parcels,
        ]);
    }

    public function eligibleRiders(Request $request, int $id): JsonResponse
    {
        $sortingCenterId = $request->user()->sortingCenter->id;
        $parcel = Parcel::where('sorting_center_id', $sortingCenterId)->find($id);

        if (! $parcel) {
            return response()->json([
                'message' => 'Parcel not found.',
            ], 422);
        }

        $riders = Rider::query()
            ->with('user:id,name,email')
            ->withCount([
                'assignedParcels as active_assignments_count' => fn ($query) => $query->whereIn('status', [
                    'AT_SORTING_CENTER',
                    'OUT_FOR_DELIVERY',
                ]),
                'assignedParcels as deliveries_in_progress_count' => fn ($query) => $query->where('status', 'OUT_FOR_DELIVERY'),
            ])
            ->where('sorting_center_id', $sortingCenterId)
            ->operationallyEligible()
            ->whereHas('areas', function ($query) use ($parcel): void {
                $query->where('areas.id', $parcel->sorted_area_id);
            })
            ->orderBy('name')
            ->get([
                'id',
                'user_id',
                'name',
                'contact',
                'vehicle_type',
                'plate_number',
                'approval_status',
                'is_active',
            ]);

        return response()->json([
            'message' => 'Eligible riders retrieved successfully.',
            'data' => $riders,
        ]);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'rider_id' => ['required', 'integer'],
        ]);
        $sortingCenterId = $request->user()->sortingCenter->id;

        $parcel = DB::transaction(function () use ($id, $validated, $sortingCenterId): Parcel {
            $finance = app(\App\Services\SellerShipmentFinance::class);
            $shipment = $finance->lockParcel($id);
            $parcel = Parcel::query()
                ->where('sorting_center_id', $sortingCenterId)
                ->whereKey($id)
                ->lockForUpdate()
                ->first();

            if (! $parcel) {
                abort(response()->json([
                    'message' => 'Parcel not found.',
                ], 422));
            }

            if ($parcel->status !== 'AT_SORTING_CENTER') {
                abort(response()->json([
                    'message' => 'Parcel cannot be assigned because its current status is '.$parcel->status.'. Expected AT_SORTING_CENTER.',
                    'current_status' => $parcel->status,
                ], 422));
            }

            if ($parcel->sorted_area_id === null) {
                abort(response()->json([
                    'message' => 'Parcel cannot be assigned because it has no sorting area.',
                ], 422));
            }

            if ($parcel->assigned_rider_id !== null) {
                abort(response()->json([
                    'message' => 'Parcel has already been assigned to a rider.',
                    'assigned_rider_id' => $parcel->assigned_rider_id,
                ], 422));
            }

            $rider = Rider::whereKey($validated['rider_id'])
                ->where('sorting_center_id', $sortingCenterId)
                ->operationallyEligible()
                ->whereHas('areas', function ($query) use ($parcel): void {
                    $query->where('areas.id', $parcel->sorted_area_id);
                })
                ->first();

            if (! $rider) {
                abort(response()->json([
                    'message' => 'The selected rider does not cover this parcel area or is not eligible for operational access.',
                    'rider_id' => $validated['rider_id'],
                ], 422));
            }

            $finance->assign($shipment, $parcel, $rider->id, $sortingCenterId);
            $parcel->update([
                'assigned_rider_id' => $rider->id,
                'assigned_at' => null,
            ]);

            return $parcel->fresh(['sortedArea', 'assignedRider.user']);
        });

        return response()->json([
            'message' => 'Rider assigned successfully. They can start the delivery from their app.',
            'data' => $parcel,
        ]);
    }
}
