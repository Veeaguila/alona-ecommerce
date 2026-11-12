<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;

use App\Models\DeliveryProof;
use App\Models\Parcel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DeliveryProofController extends Controller
{
    public function getProofs(Request $request, int $parcelId): JsonResponse
    {
        $sortingCenterId = $request->user()->sortingCenter->id;

        $parcel = Parcel::query()
            ->where('sorting_center_id', $sortingCenterId)
            ->whereKey($parcelId)
            ->first();

        if (!$parcel) {
            return response()->json(['message' => 'Parcel not found.'], 404);
        }

        $proofs = DeliveryProof::query()
            ->where('parcel_id', $parcelId)
            ->with(['rider:id,user_id,name', 'rider.user:id,email'])
            ->orderByDesc('captured_at')
            ->get([
                'id',
                'parcel_id',
                'rider_id',
                'type',
                'file_path',
                'notes',
                'captured_at',
            ]);

        return response()->json([
            'message' => 'Delivery proofs retrieved successfully.',
            'data' => [
                'parcel' => [
                    'id' => $parcel->id,
                    'order_id' => $parcel->order_id,
                    'status' => $parcel->status,
                    'delivery_street' => $parcel->delivery_street,
                    'delivery_house_number' => $parcel->delivery_house_number,
                ],
                'proofs' => $proofs->map(fn (DeliveryProof $proof) => $this->proofData($proof)),
            ],
        ]);
    }

    public function getProof(Request $request, int $proofId): JsonResponse
    {
        $proof = DeliveryProof::query()
            ->with(['parcel', 'rider:id,user_id,name', 'rider.user:id,email'])
            ->whereKey($proofId)
            ->first();

        if (!$proof) {
            return response()->json(['message' => 'Proof not found.'], 404);
        }

        $sortingCenterId = $request->user()->sortingCenter->id;
        if (!$proof->parcel || (int) $proof->parcel->sorting_center_id !== (int) $sortingCenterId) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        return response()->json([
            'message' => 'Delivery proof retrieved successfully.',
            'data' => $this->proofData($proof),
        ]);
    }

    private function proofData(DeliveryProof $proof): array
    {
        return [
            'id' => $proof->id,
            'parcel_id' => $proof->parcel_id,
            'rider' => [
                'id' => $proof->rider?->id,
                'name' => $proof->rider?->name,
                'email' => $proof->rider?->user?->email,
            ],
            'type' => $proof->type,
            'file_url' => route('sorting-center.proofs.file', ['proofId' => $proof->id], false),
            'notes' => $proof->notes,
            'captured_at' => $proof->captured_at,
        ];
    }

    public function file(Request $request, int $proofId)
    {
        $proof = DeliveryProof::query()
            ->whereHas('parcel', fn ($query) => $query->where(
                'sorting_center_id', $request->user()->sortingCenter->id
            ))
            ->findOrFail($proofId);

        abort_unless(Storage::disk('local')->exists($proof->file_path), 404);

        return Storage::disk('local')->response($proof->file_path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
