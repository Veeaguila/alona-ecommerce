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
        $pickups = Parcel::query()
            ->where('sorting_center_id', $centerId)
            ->with([
                'pickupRequests' => fn ($query) => $query
                    ->where('status', 'confirmed')
                    ->with('seller:id,name,email'),
            ])
            ->whereIn('status', ['PLACED', 'PREPARING'])
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

        return response()->json([
            'message' => 'Available pickup jobs retrieved successfully.',
            'data' => $pickups,
        ]);
    }

    public function mine(Request $request): JsonResponse
    {
        $riderId = $request->user()->rider?->id;

        $pickups = Parcel::query()
            ->with([
                'pickupRequests' => fn ($query) => $query->with('seller:id,name,email'),
            ])
            ->where('pickup_rider_id', $riderId)
            ->whereIn('status', ['PLACED', 'PREPARING'])
            ->orderByDesc('updated_at')
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

        return response()->json([
            'message' => 'Accepted pickup jobs retrieved successfully.',
            'data' => $pickups,
        ]);
    }

    public function accept(Request $request, int $parcelId): JsonResponse
    {
        $riderId = $request->user()->rider?->id;
        $centerId = $request->user()->rider->sorting_center_id;
        abort_unless($centerId, 403, 'A sorting center assignment is required for pickups.');

        $parcel = DB::transaction(function () use ($parcelId, $riderId, $centerId): Parcel {
            $parcel = Parcel::query()->whereKey($parcelId)->lockForUpdate()->first();

            if (!$parcel || (int) $parcel->sorting_center_id !== (int) $centerId) {
                abort(response()->json(['message' => 'Pickup job not found.'], 404));
            }

            if ($parcel->pickup_rider_id !== null) {
                abort(response()->json([
                    'message' => 'This pickup job has already been accepted by another rider.',
                ], 409));
            }

            if (!in_array($parcel->status, ['PLACED', 'PREPARING'], true)) {
                abort(response()->json([
                    'message' => 'Only parcels awaiting pickup can be accepted.',
                    'current_status' => $parcel->status,
                ], 422));
            }

            $isConfirmed = $parcel->pickupRequests()
                ->where('status', 'confirmed')
                ->exists();

            if (!$isConfirmed) {
                abort(response()->json([
                    'message' => 'Only confirmed pickup requests can be accepted.',
                ], 422));
            }

            $parcel->update(['pickup_rider_id' => $riderId]);

            return $parcel->fresh([
                'pickupRequests' => fn ($query) => $query->with('seller:id,name,email'),
            ]);
        });

        return response()->json([
            'message' => 'Pickup job accepted successfully.',
            'data' => $parcel,
        ]);
    }

    public function confirm(Request $request, int $parcelId): JsonResponse
    {
        $riderId = $request->user()->rider?->id;

        $parcel = DB::transaction(function () use ($parcelId, $riderId): Parcel {
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
