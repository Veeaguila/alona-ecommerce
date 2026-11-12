<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;

use App\Models\Parcel;
use App\Models\PickupRequest;
use App\Models\Rider;
use App\Services\LogisticsParcelSummary;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DashboardController extends Controller
{
    public function index(Request $request, LogisticsParcelSummary $summary): JsonResponse
    {
        $dates = $summary->dates($request);
        $sortingCenterId = $request->user()->sortingCenter->id;
        $workflowParcels = Parcel::query()->inSortingCenterWorkflow($sortingCenterId);
        $pendingPickups = PickupRequest::query()
            ->where('status', 'pending')
            ->where(function ($query) use ($sortingCenterId): void {
                $query->whereNull('sorting_center_id')
                    ->orWhere('sorting_center_id', $sortingCenterId);
            });

        $summary->filterDates($workflowParcels, $dates);
        $summary->filterDates($pendingPickups, $dates);

        return response()->json([
            'message' => 'Dashboard summary retrieved successfully.',
            'data' => [
                'parcel_totals' => $summary->totals($summary->parcels($sortingCenterId, $dates)),
                'riders_pending_approval' => Rider::query()
                    ->where('sorting_center_id', $sortingCenterId)
                    ->where('approval_status', 'pending')
                    ->count(),
                'pickup_requests_pending' => $pendingPickups->count(),
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
    public function parcels(Request $request, LogisticsParcelSummary $summary): JsonResponse
    {
        $dates = $summary->dates($request);
        $input = $request->validate(['bucket' => ['required', 'string', Rule::in(array_keys($summary::STATUSES))],
            'page' => ['nullable', 'integer', 'min:1']]);
        $parcels = $summary->parcels($request->user()->sortingCenter->id, $dates)
            ->whereIn('status', $summary::STATUSES[$input['bucket']])
            ->with(['seller:id,name', 'buyer:id,name', 'pickupRider:id,name', 'assignedRider:id,name'])
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(10)->withQueryString();
        return response()->json(['data' => $parcels]);
    }

}
