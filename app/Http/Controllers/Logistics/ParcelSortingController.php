<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;

use App\Models\Area;
use App\Models\Parcel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class ParcelSortingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($request->query('status') === 'sorted-unassigned') {
            return app(ParcelAssignmentController::class)->index($request);
        }

        $sortingCenterId = $request->user()->sortingCenter->id;

        $parcels = Parcel::query()
            ->where('sorting_center_id', $sortingCenterId)
            ->where('status', 'AT_SORTING_CENTER')
            ->whereNull('sorted_area_id')
            ->orderByDesc('updated_at')
            ->get([
                'id',
                'order_id',
                'status',
                'order_item_id',
                'delivery_address',
                'collection_address',
                'delivery_province',
                'delivery_municipality',
                'delivery_barangay',
                'delivery_street',
                'delivery_house_number',
                'sorted_at',
                'sorted_area_id',
            ]);

        return response()->json([
            'message' => 'Unsorted arrived parcels retrieved successfully.',
            'data' => $parcels,
        ]);
    }

    public function areas(Request $request): JsonResponse
    {
        $sortingCenterId = $request->user()->sortingCenter->id;

        return response()->json([
            'message' => 'Sorting areas retrieved successfully.',
            'data' => Area::where('sorting_center_id', $sortingCenterId)
                ->orderBy('name')
                ->get(['id', 'name', 'sorting_center_id']),
        ]);
    }

    public function createArea(Request $request): JsonResponse
    {
        $sortingCenterId = $request->user()->sortingCenter->id;
        $request->merge(['name' => trim((string) $request->input('name', ''))]);
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('areas', 'name')->where('sorting_center_id', $sortingCenterId),
            ],
        ]);

        $area = Area::create([
            'sorting_center_id' => $sortingCenterId,
            'name' => $validated['name'],
        ]);

        return response()->json([
            'message' => 'City service area created successfully.',
            'data' => $area,
        ], 201);
    }

    public function sort(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'area_id' => ['required', 'integer'],
        ]);
        $sortingCenterId = $request->user()->sortingCenter->id;

        $parcel = DB::transaction(function () use ($id, $validated, $sortingCenterId): Parcel {
            $parcel = Parcel::query()
                ->where('sorting_center_id', $sortingCenterId)
                ->whereKey($id)
                ->lockForUpdate()
                ->first();

            if (!$parcel) {
                abort(response()->json([
                    'message' => 'Parcel not found.',
                ], 422));
            }

            if ($parcel->status !== 'AT_SORTING_CENTER') {
                abort(response()->json([
                    'message' => 'Parcel cannot be sorted because its current status is '.$parcel->status.'. Expected AT_SORTING_CENTER.',
                    'current_status' => $parcel->status,
                ], 422));
            }

            if ($parcel->sorted_area_id !== null) {
                abort(response()->json([
                    'message' => 'Parcel has already been assigned to a sorting area.',
                    'sorted_area_id' => $parcel->sorted_area_id,
                ], 422));
            }

            $area = Area::where('id', $validated['area_id'])
                ->where('sorting_center_id', $sortingCenterId)
                ->first();

            if (!$area) {
                abort(response()->json([
                    'message' => 'The selected area does not belong to this sorting center.',
                    'area_id' => $validated['area_id'],
                ], 422));
            }

            if ($parcel->delivery_municipality !== '' && strcasecmp(trim($area->name), trim($parcel->delivery_municipality)) !== 0) {
                abort(response()->json([
                    'message' => 'The selected service area must match the parcel destination city.',
                    'delivery_municipality' => $parcel->delivery_municipality,
                    'area_name' => $area->name,
                ], 422));
            }

            $parcel->update([
                // For ecommerce's free-text addresses, the operator explicitly
                // classifies the destination by selecting its city service area.
                'delivery_municipality' => $parcel->delivery_municipality ?: $area->name,
                'sorted_area_id' => $area->id,
                'sorted_at' => now(),
            ]);

            return $parcel->fresh(['sortedArea']);
        });

        return response()->json([
            'message' => 'Parcel sorted successfully.',
            'data' => $parcel,
        ]);
    }
}
