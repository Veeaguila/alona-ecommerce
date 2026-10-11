<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;

use App\Models\Parcel;
use App\Models\PickupRequest;
use App\Models\Rider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sortingCenterId = $request->user()->sortingCenter->id;
        $workflowParcels = Parcel::query()->inSortingCenterWorkflow($sortingCenterId);

        return response()->json([
            'message' => 'Dashboard summary retrieved successfully.',
            'data' => [
                'riders_pending_approval' => Rider::query()
                    ->where('sorting_center_id', $sortingCenterId)
                    ->where('approval_status', 'pending')
                    ->count(),
                'pickup_requests_pending' => PickupRequest::query()
                    ->where('status', 'pending')
                    ->where(function ($query) use ($sortingCenterId): void {
                        $query->whereNull('sorting_center_id')
                            ->orWhere('sorting_center_id', $sortingCenterId);
                    })
                    ->count(),
                'parcels_awaiting_sorting' => (clone $workflowParcels)
                    ->where('status', 'AT_SORTING_CENTER')
                    ->whereNull('sorted_area_id')
                    ->count(),
                'parcels_awaiting_rider_assignment' => (clone $workflowParcels)
                    ->where('status', 'AT_SORTING_CENTER')
                    ->whereNotNull('sorted_area_id')
                    ->whereNull('assigned_rider_id')
                    ->count(),
                'parcels_out_for_delivery' => (clone $workflowParcels)
                    ->where('status', 'OUT_FOR_DELIVERY')
                    ->count(),
                'active_approved_riders' => Rider::query()
                    ->where('sorting_center_id', $sortingCenterId)
                    ->where('approval_status', 'approved')
                    ->where('is_active', true)
                    ->whereHas('areas', function ($query) use ($sortingCenterId): void {
                        $query->where('areas.sorting_center_id', $sortingCenterId);
                    })
                    ->distinct()
                    ->count('riders.id'),
            ],
        ]);
    }
}
