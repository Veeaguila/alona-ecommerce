<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;

use App\Models\Parcel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IncomingParcelController extends Controller
{
    public function lookup(Request $request, string $orderId): JsonResponse
    {
        $sortingCenterId = $request->user()->sortingCenter->id;
        $parcel = Parcel::where('order_id', $orderId)->first();

        if (!$parcel || ($parcel->sorting_center_id !== null
            && (int) $parcel->sorting_center_id !== (int) $sortingCenterId)) {
            return response()->json([
                'message' => 'Parcel not found for this sorting center.',
            ], 404);
        }

        if (!in_array($parcel->status, ['PICKED_UP', 'AT_SORTING_CENTER'], true)) {
            return response()->json([
                'message' => 'Parcel is not ready to arrive at a sorting center.',
                'current_status' => $parcel->status,
            ], 422);
        }

        return response()->json([
            'message' => 'Parcel found successfully.',
            'data' => array_merge($parcel->only([
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
            ]), ['hub_route' => app(\App\Services\HubNetwork::class)->journey($parcel)?->route_snapshot]),
        ]);
    }

    public function markArrived(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'string', 'max:50'],
            'hub_id' => ['nullable', 'integer'],
        ]);
        $sortingCenterId = $request->user()->sortingCenter->id;

        $actor = $request->user();
        $parcel = DB::transaction(function () use ($validated, $sortingCenterId, $actor): Parcel {
            $parcel = Parcel::query()
                ->where('order_id', $validated['order_id'])
                ->lockForUpdate()
                ->first();

            if (!$parcel) {
                abort(response()->json([
                    'message' => 'Parcel not found for order ID: '.$validated['order_id'].'.',
                    'order_id' => $validated['order_id'],
                ], 422));
            }

            if ($parcel->sorting_center_id !== null
                && (int) $parcel->sorting_center_id !== (int) $sortingCenterId) {
                abort(response()->json([
                    'message' => 'This parcel is assigned to another sorting center.',
                ], 409));
            }

            if ($parcel->status !== 'PICKED_UP') {
                abort(response()->json([
                    'message' => 'Parcel cannot be marked as arrived because its current status is '.$parcel->status.'. Expected PICKED_UP.',
                    'order_id' => $parcel->order_id,
                    'current_status' => $parcel->status,
                ], 422));
            }

            app(\App\Services\HubNetwork::class)->receiveOrigin($parcel, $validated['hub_id'] ?? null, $actor);
            $parcel->update([
                'sorting_center_id' => $sortingCenterId,
                'status' => 'AT_SORTING_CENTER',
            ]);

            return $parcel->fresh();
        });

        return response()->json([
            'message' => 'Parcel marked as arrived at the sorting center.',
            'data' => $parcel->fresh(),
        ]);
    }
}
