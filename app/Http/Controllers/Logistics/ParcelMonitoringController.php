<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;

use App\Models\Parcel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ParcelMonitoringController extends Controller
{
    private const FILTERABLE_STATUSES = [
        'AT_SORTING_CENTER',
        'OUT_FOR_DELIVERY',
        'DELIVERED',
        'FAILED_RETURNED',
    ];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(self::FILTERABLE_STATUSES)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $sortingCenterId = $request->user()->sortingCenter->id;
        $query = Parcel::query()
            ->with([
                'sortedArea:id,name',
                'assignedRider:id,name',
            ])
            ->inSortingCenterWorkflow($sortingCenterId)
            ->orderByDesc('updated_at');

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $parcels = $query->paginate(10)->withQueryString();

        return response()->json([
            'message' => 'Parcel monitoring data retrieved successfully.',
            'data' => $parcels,
        ]);
    }
}
