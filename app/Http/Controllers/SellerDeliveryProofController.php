<?php

namespace App\Http\Controllers;

use App\Models\{DeliveryProof, OrderItem};
use App\Services\SellerParcelDelivery;
use Illuminate\Http\Request;

class SellerDeliveryProofController extends Controller
{
    public function file(Request $request, OrderItem $orderItem, DeliveryProof $proof, SellerParcelDelivery $delivery)
    {
        abort_unless($request->user()->usertype === 'seller', 403);
        $parcel = $orderItem->parcel;
        abort_unless($parcel && $delivery->verifiedSellerId($parcel) === (int) $request->user()->id
            && $parcel->status === 'DELIVERED' && (int) $proof->parcel_id === (int) $parcel->id
            && (int) $proof->rider_id === (int) $parcel->assigned_rider_id && $proof->isValidPhoto(), 404);
        $path = $proof->privateFilePath();
        abort_unless($path, 404);
        return response()->file($path, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox"]);
    }
}
