<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;

use App\Models\DeliveryProof;
use App\Models\Parcel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RiderDeliveryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $riderId = $request->user()->rider?->id;

        $deliveries = Parcel::query()
            ->where('assigned_rider_id', $riderId)
            ->whereIn('status', ['AT_SORTING_CENTER', 'OUT_FOR_DELIVERY'])
            ->with('sortedArea:id,name')
            ->orderByDesc('assigned_at')
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
                'assigned_rider_id',
                'assigned_at',
            ]);

        return response()->json([
            'message' => 'Assigned deliveries retrieved successfully.',
            'data' => $deliveries,
        ]);
    }

    public function start(Request $request, int $parcelId): JsonResponse
    {
        $riderId = $request->user()->rider?->id;
        $parcel = DB::transaction(function () use ($parcelId, $riderId): Parcel {
            $parcel = Parcel::query()->whereKey($parcelId)->lockForUpdate()->first();

            if (!$parcel) {
                abort(response()->json(['message' => 'Delivery not found.'], 404));
            }

            if ((int) $parcel->assigned_rider_id !== (int) $riderId) {
                abort(response()->json(['message' => 'This delivery is not assigned to you.'], 403));
            }

            if ($parcel->status !== 'AT_SORTING_CENTER') {
                abort(response()->json(['message' => 'This delivery is not waiting to be started.'], 422));
            }

            $parcel->update([
                'status' => 'OUT_FOR_DELIVERY',
                'assigned_at' => now(),
            ]);

            return $parcel->fresh();
        });

        return response()->json([
            'message' => 'Delivery started.',
            'data' => $parcel,
        ]);
    }

    public function deliver(Request $request, int $parcelId): JsonResponse
    {
        return $this->transition($request, $parcelId, 'DELIVERED', 'Delivery marked as delivered.');
    }

    public function fail(Request $request, int $parcelId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string'],
        ]);

        return $this->transition($request, $parcelId, 'FAILED_RETURNED', 'Delivery marked as failed and returned.', [
            'failure_reason' => $validated['reason'],
        ]);
    }

    private function transition(Request $request, int $parcelId, string $status, string $message, array $attributes = []): JsonResponse
    {
        $riderId = $request->user()->rider?->id;
        $parcel = DB::transaction(function () use ($parcelId, $riderId, $status, $attributes): Parcel {
            $parcel = Parcel::query()->whereKey($parcelId)->lockForUpdate()->first();

            if (!$parcel) {
                abort(response()->json(['message' => 'Delivery not found.'], 404));
            }

            if ((int) $parcel->assigned_rider_id !== (int) $riderId) {
                abort(response()->json(['message' => 'This delivery is not assigned to you.'], 403));
            }

            if ($parcel->status !== 'OUT_FOR_DELIVERY') {
                abort(response()->json(['message' => 'This delivery is no longer out for delivery.'], 422));
            }

            if ($status === 'DELIVERED' && !$parcel->deliveryProofs()->where('rider_id', $riderId)->exists()) {
                abort(response()->json([
                    'message' => 'Upload delivery proof before marking this parcel as delivered.',
                ], 422));
            }

            $parcel->update(array_merge([
                'status' => $status,
                'delivered_at' => $status === 'DELIVERED' ? now() : null,
            ], $attributes));

            return $parcel->fresh();
        });

        return response()->json([
            'message' => $message,
            'data' => $parcel,
        ]);
    }

    public function uploadProof(Request $request, int $parcelId): JsonResponse
    {
        $riderId = $request->user()->rider?->id;
        $validated = $request->validate([
            'file' => ['required', 'file', 'image', 'max:20480'],
            'type' => ['nullable', 'string', 'in:PHOTO,SIGNATURE'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $parcel = Parcel::query()->whereKey($parcelId)->first();

        if (!$parcel) {
            abort(response()->json(['message' => 'Delivery not found.'], 404));
        }

        if ((int) $parcel->assigned_rider_id !== (int) $riderId) {
            abort(response()->json(['message' => 'This delivery is not assigned to you.'], 403));
        }

        if ($parcel->status !== 'OUT_FOR_DELIVERY') {
            abort(response()->json(['message' => 'This delivery is no longer out for delivery.'], 422));
        }

        $file = $request->file('file');
        $path = $file->store('delivery-proofs', 'local');
        abort_unless($path, 500, 'Delivery proof could not be saved.');

        $proof = DeliveryProof::query()->create([
            'parcel_id' => $parcel->id,
            'rider_id' => $riderId,
            'type' => $validated['type'] ?? 'PHOTO',
            'file_path' => $path,
            'notes' => $validated['notes'] ?? null,
            'captured_at' => now(),
        ]);

        return response()->json([
            'message' => 'Delivery proof uploaded successfully.',
            'data' => $proof,
        ], 201);
    }
}
