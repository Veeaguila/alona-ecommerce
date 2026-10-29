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
    private function deliveryQuery(int $riderId): \Illuminate\Database\Eloquent\Builder
    {
        return Parcel::query()->where('assigned_rider_id', $riderId)
            ->with('sortedArea:id,name')->select([
                'id', 'buyer_id', 'order_id', 'status', 'order_item_id',
                'delivery_address', 'collection_address', 'delivery_province',
                'delivery_municipality', 'delivery_barangay', 'delivery_street',
                'delivery_house_number', 'assigned_rider_id', 'assigned_at', 'sorted_area_id',
            ]);
    }

    private function appendDetails(\Illuminate\Database\Eloquent\Collection $parcels, int $riderId): void
    {
        app(\App\Services\RiderParcelDetails::class)->append($parcels, false);
        $parcels->loadExists([
            'deliveryProofs as has_delivery_proof' => fn ($query) => $query->where('rider_id', $riderId),
        ]);
        $parcels->load(['deliveryProofs' => fn ($query) => $query->where('rider_id', $riderId)->where('type', 'PHOTO')
            ->select(['id', 'parcel_id', 'rider_id', 'type', 'file_path'])]);
        foreach ($parcels as $parcel) {
            $parcel->setAttribute('has_valid_photo_proof', $parcel->deliveryProofs->contains(fn (DeliveryProof $proof) => $proof->isValidPhoto()
                && app(\App\Services\DeliveryAttempts::class)->proofAllowed($parcel, $proof)));
            foreach (app(\App\Services\DeliveryAttempts::class)->state($parcel) as $field => $value) $parcel->setAttribute($field, $value);
            $parcel->unsetRelation('deliveryProofs');
        }
    }

    public function lookup(Request $request): JsonResponse
    {
        $input = $request->validate(['reference' => ['required', 'string', 'max:50', 'regex:/^ITEM-[1-9][0-9]*$/i']]);
        $riderId = (int) $request->user()->rider->id;
        $parcel = $this->deliveryQuery($riderId)->where('order_id', strtoupper($input['reference']))->first();
        // An unauthorized scan must not disclose whether another Rider owns the reference.
        if (!$parcel) return response()->json(['message' => 'Parcel not found in your assignments.', 'code' => 'parcel_not_found'], 404);
        if (in_array($parcel->status, ['DELIVERED', 'FAILED_RETURNED'], true)) {
            return response()->json(['message' => 'This parcel is already completed or returned. Check Delivery History.', 'code' => 'parcel_completed'], 409);
        }
        if (!in_array($parcel->status, ['AT_SORTING_CENTER', 'OUT_FOR_DELIVERY'], true)) {
            return response()->json(['message' => 'This parcel is not ready for delivery.', 'code' => 'parcel_not_ready'], 422);
        }
        $this->appendDetails(new \Illuminate\Database\Eloquent\Collection([$parcel]), $riderId);

        return response()->json(['data' => $parcel])->header('Cache-Control', 'private, no-store');
    }

    public function index(Request $request): JsonResponse
    {
        $riderId = $request->user()->rider?->id;
        $input = $request->validate(['view' => ['sometimes', 'in:active,history'],
            'page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        if (($input['view'] ?? 'active') === 'history') {
            return $this->history((int) $riderId, (int) ($input['per_page'] ?? 20));
        }

        $deliveries = $this->deliveryQuery((int) $riderId)
            ->whereIn('status', ['AT_SORTING_CENTER', 'OUT_FOR_DELIVERY', 'LOST', 'DAMAGED'])
            ->orderByDesc('assigned_at')
            ->get();

        $this->appendDetails($deliveries, (int) $riderId);

        return response()->json([
            'message' => 'Assigned deliveries retrieved successfully.',
            'data' => $deliveries,
        ]);
    }

    private function history(int $riderId, int $perPage): JsonResponse
    {
        $owned = Parcel::query()->where('assigned_rider_id', $riderId);
        $counts = (clone $owned)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $history = (clone $owned)->whereIn('status', ['DELIVERED', 'FAILED_RETURNED'])
            ->orderByDesc('updated_at')->orderByDesc('id')
            ->paginate($perPage, ['id', 'order_id', 'status', 'delivery_address', 'delivery_province',
                'delivery_municipality', 'delivery_barangay', 'delivery_street', 'delivery_house_number',
                'delivered_at', 'updated_at', 'failure_reason']);

        return response()->json(['data' => $history->items(),
            'meta' => ['current_page' => $history->currentPage(), 'last_page' => $history->lastPage(),
                'per_page' => $history->perPage(), 'total' => $history->total()],
            'summary' => ['DELIVERED' => (int) ($counts['DELIVERED'] ?? 0),
                'FAILED_RETURNED' => (int) ($counts['FAILED_RETURNED'] ?? 0),
                'active' => (int) ($counts['AT_SORTING_CENTER'] ?? 0) + (int) ($counts['OUT_FOR_DELIVERY'] ?? 0)]]);
    }

    public function start(Request $request, int $parcelId): JsonResponse
    {
        $riderId = $request->user()->rider?->id;
        $parcel = DB::transaction(function () use ($parcelId, $riderId): Parcel {
            $finance = app(\App\Services\SellerShipmentFinance::class);
            $shipment = $finance->lockParcel($parcelId);
            $parcel = Parcel::query()->whereKey($parcelId)->lockForUpdate()->first();

            if (! $parcel) {
                abort(response()->json(['message' => 'Delivery not found.'], 404));
            }

            if ((int) $parcel->assigned_rider_id !== (int) $riderId) {
                abort(response()->json(['message' => 'This delivery is not assigned to you.'], 403));
            }

            if ($parcel->status !== 'AT_SORTING_CENTER') {
                abort(response()->json(['message' => 'This delivery is not waiting to be started.'], 422));
            }

            $finance->dispatch($shipment, (int) $riderId);
            $parcel->update([
                'status' => 'OUT_FOR_DELIVERY',
                'assigned_at' => now(),
            ]);
            app(\App\Services\DeliveryOtp::class)->ensure($parcel);

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
            'reason' => ['required', 'string', \Illuminate\Validation\Rule::in(\App\Services\DeliveryAttempts::REASONS)],
        ]);

        return response()->json(['message' => 'Failed attempt recorded. Physical return requires Logistics initiation and separate custody confirmations.',
            'data' => app(\App\Services\DeliveryAttempts::class)->fail($request->user(), $parcelId, $validated['reason'])]);
    }

    public function retry(Request $request, int $parcelId): JsonResponse
    {
        return response()->json(['message' => 'Next delivery attempt started. A current photo and Buyer OTP are still required for delivery.',
            'data' => app(\App\Services\DeliveryAttempts::class)->retry($request->user(), $parcelId)]);
    }

    private function transition(Request $request, int $parcelId, string $status, string $message, array $attributes = []): JsonResponse
    {
        $riderId = $request->user()->rider?->id;
        $parcel = DB::transaction(function () use ($request, $parcelId, $riderId, $status, $attributes): Parcel {
            $finance = app(\App\Services\SellerShipmentFinance::class);
            $shipment = $finance->lockParcel($parcelId);
            $parcel = Parcel::query()->whereKey($parcelId)->lockForUpdate()->first();

            if (! $parcel) {
                abort(response()->json(['message' => 'Delivery not found.'], 404));
            }

            if ((int) $parcel->assigned_rider_id !== (int) $riderId) {
                abort(response()->json(['message' => 'This delivery is not assigned to you.'], 403));
            }

            if ($parcel->status !== 'OUT_FOR_DELIVERY') {
                abort(response()->json(['message' => 'This delivery is no longer out for delivery.'], 422));
            }

            if ($status === 'DELIVERED' && ! $parcel->deliveryProofs()->where('rider_id', $riderId)->where('type', 'PHOTO')
                ->lockForUpdate()->get()->contains(fn (DeliveryProof $proof) => $proof->isValidPhoto()
                    && app(\App\Services\DeliveryAttempts::class)->proofAllowed($parcel, $proof))) {
                abort(response()->json([
                    'message' => 'Upload an accessible delivery PHOTO before marking this parcel as delivered.',
                    'code' => 'valid_photo_required',
                ], 422));
            }

            if ($status === 'DELIVERED') {
                app(\App\Services\DeliveryAttempts::class)->assertCanComplete($parcel);
                $otp = $request->validate(['otp' => ['required', 'string', 'regex:/\A[0-9]{6}\z/']])['otp'];
                app(\App\Services\DeliveryOtp::class)->verify($parcel, $otp);
            }
            $parcel->update(array_merge([
                'status' => $status,
                'delivered_at' => $status === 'DELIVERED' ? now() : null,
            ], $attributes));

            if ($status === 'DELIVERED') {
                app(\App\Services\SellerParcelDelivery::class)->notifyDelivered($parcel);
                $finance->recognize($shipment);
            }
            return $parcel->fresh();
        });

        if ($status === 'DELIVERED') {
            try { app(\App\Services\DeliveryOtp::class)->consume($parcel); }
            catch (\Throwable) {
                // The committed DELIVERED status already prevents replay. Do not report a failed delivery after commit.
                \Illuminate\Support\Facades\Log::warning('Delivery OTP cleanup pending.', ['parcel_id' => $parcel->id]);
            }
        }

        return response()->json([
            'message' => $message,
            'data' => $parcel,
        ]);
    }

    public function uploadProof(Request $request, int $parcelId): JsonResponse
    {
        $riderId = $request->user()->rider?->id;
        $validated = $request->validate([
            'file' => ['required', 'file', 'image', 'max:20480', 'dimensions:max_width=6000,max_height=6000'],
            'type' => ['nullable', 'string', 'in:PHOTO,SIGNATURE'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $path = null;
        try {
            $proof = DB::transaction(function () use ($request, $parcelId, $riderId, $validated, &$path): DeliveryProof {
                $parcel = Parcel::query()->whereKey($parcelId)->lockForUpdate()->first();
                abort_unless($parcel, 404, 'Delivery not found.');
                abort_unless((int) $parcel->assigned_rider_id === (int) $riderId, 403, 'This delivery is not assigned to you.');
                abort_unless($parcel->status === 'OUT_FOR_DELIVERY', 422, 'This delivery is no longer out for delivery.');
                app(\App\Services\DeliveryAttempts::class)->assertCanComplete($parcel);
                // Retried requests with the same photo reuse the existing proof under the parcel lock.
                $type = $validated['type'] ?? 'PHOTO';
                $uploadHash = hash_file('sha256', $request->file('file')->getRealPath());
                foreach ($parcel->deliveryProofs()->where('rider_id', $riderId)->where('type', $type)->get() as $existing) {
                    $existingPath = $existing->privateFilePath();
                    $existingHash = $existingPath ? @hash_file('sha256', $existingPath) : false;
                    if (is_string($uploadHash) && is_string($existingHash) && hash_equals($uploadHash, $existingHash)) {
                        abort_unless(app(\App\Services\DeliveryAttempts::class)->proofAllowed($parcel, $existing), 422,
                            'This photo belongs to an earlier failed attempt. Capture a current delivery photo.');
                        $existing->update(['notes' => $validated['notes'] ?? null]);
                        return $existing;
                    }
                }
                $path = $request->file('file')->store('delivery-proofs', 'local');
                abort_unless($path, 500, 'Delivery proof could not be saved.');

                return DeliveryProof::query()->create([
                    'parcel_id' => $parcel->id, 'rider_id' => $riderId,
                    'type' => $validated['type'] ?? 'PHOTO', 'file_path' => $path,
                    'notes' => $validated['notes'] ?? null, 'captured_at' => now(),
                ]);
            });
        } catch (\Throwable $error) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $error;
        }

        return response()->json(['message' => 'Delivery proof uploaded successfully.', 'data' => $proof], 201);
    }
}
