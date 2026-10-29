<?php

namespace App\Http\Controllers;

use App\Models\{Order, Parcel};
use App\Services\DeliveryOtp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BuyerDeliveryOtpController extends Controller
{
    public function show(Request $request, Order $order, int $parcel, DeliveryOtp $otp): JsonResponse
    {
        return DB::transaction(fn () => $this->response($otp->forBuyer($this->owned($request, $order, $parcel))));
    }

    public function renew(Request $request, Order $order, int $parcel, DeliveryOtp $otp): JsonResponse
    {
        return DB::transaction(function () use ($request, $order, $parcel, $otp): JsonResponse {
            $parcel = $this->owned($request, $order, $parcel);
            $otp->renew($parcel);
            return $this->response($otp->forBuyer($parcel));
        });
    }

    private function owned(Request $request, Order $order, int $parcel): Parcel
    {
        abort_unless($request->user()->usertype === 'buyer' && (int) $order->user_id === (int) $request->user()->id, 404);
        $owned = Parcel::whereKey($parcel)->where('buyer_id', $request->user()->id)
            ->whereHas('orderItem', fn ($query) => $query->where('order_id', $order->id))->lockForUpdate()->firstOrFail();
        if (\Illuminate\Support\Facades\Schema::hasTable('parcel_returns')) abort_if($owned->returnToSeller()->exists(), 409,
            'This parcel is in Return to Seller tracking. Buyer delivery OTP is unavailable.');
        return $owned;
    }

    private function response(array $data): JsonResponse
    {
        return response()->json(['data' => $data])->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Pragma', 'no-cache')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
