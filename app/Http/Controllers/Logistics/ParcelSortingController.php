<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Parcel;
use App\Services\DeliveryCoverageService;
use App\Services\PhilippineAddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ParcelSortingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($request->query('status') === 'sorted-unassigned') {
            return app(ParcelAssignmentController::class)->index($request);
        }

        $sortingCenterId = $request->user()->sortingCenter->id;

        $parcels = Parcel::query()
            ->with('orderItem.order:id,shipping_method')
            ->where('sorting_center_id', $sortingCenterId)
            ->where('status', 'AT_SORTING_CENTER')
            ->when($request->query('status') === 'sorted',
                fn ($query) => $query->whereNotNull('sorted_area_id'),
                fn ($query) => $query->whereNull('sorted_area_id'))
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

        $geography = app(PhilippineAddressService::class)->data();
        $cities = array_column($geography['cities'], null, 'code');
        $provinces = array_column($geography['provinces'], 'name', 'code');
        $regions = array_column($geography['regions'], 'name', 'code');

        return response()->json([
            'message' => 'Sorting areas retrieved successfully.',
            'data' => Area::where('sorting_center_id', $sortingCenterId)
                ->orderBy('name')
                ->get(['id', 'name', 'sorting_center_id', 'city_code'])->map(function ($area) use ($cities, $provinces, $regions) {
                    $city = $cities[$area->city_code] ?? null;

                    return array_merge($area->toArray(), ['city_name' => $city['name'] ?? $area->name,
                        'region_code' => $city['region_code'] ?? null,
                        'region_label' => ($city['region_code'] ?? null) === '1300000000' ? 'NCR' : ($regions[$city['region_code'] ?? ''] ?? 'Unmapped service area'),
                        'province_name' => $provinces[$city['province_code'] ?? ''] ?? null,
                        'label' => $city ? implode(' · ', array_filter([$city['name'], $provinces[$city['province_code']] ?? null, $regions[$city['region_code']]])) : $area->name]);
                }),
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

            if (! $parcel) {
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

            if (! $area) {
                abort(response()->json([
                    'message' => 'The selected area does not belong to this sorting center.',
                    'area_id' => $validated['area_id'],
                ], 422));
            }

            $city = $area->city_code ? collect(app(PhilippineAddressService::class)->data()['cities'])->firstWhere('code', $area->city_code) : null;
            $cityName = $city['name'] ?? $area->name;
            $province = $city && $city['province_code'] ? collect(app(PhilippineAddressService::class)->data()['provinces'])->firstWhere('code', $city['province_code']) : null;
            if ($parcel->delivery_municipality !== '' && DeliveryCoverageService::normalizeCityName($cityName) !== DeliveryCoverageService::normalizeCityName($parcel->delivery_municipality)) {
                abort(response()->json([
                    'message' => 'The selected service area must match the parcel destination city.',
                    'delivery_municipality' => $parcel->delivery_municipality,
                    'area_name' => $area->name,
                ], 422));
            }

            if ($province && $parcel->delivery_province !== '' && strcasecmp(trim($province['name']), trim($parcel->delivery_province)) !== 0) {
                abort(response()->json(['message' => 'The selected service area must match the parcel destination province.', 'delivery_province' => $parcel->delivery_province], 422));
            }

            $parcel->update([
                // For ecommerce's free-text addresses, the operator explicitly
                // classifies the destination by selecting its city service area.
                'delivery_municipality' => $parcel->delivery_municipality ?: $cityName,
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
