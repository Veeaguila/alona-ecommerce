<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\{Order, OrderItem};
use App\Services\{PickupLocations, RiderPresence, SellerParcelDelivery};
use Illuminate\Http\Request;

class PickupLocationController extends Controller
{
    public function seller(Request $request, OrderItem $orderItem)
    {
        $parcel = $orderItem->parcel;
        abort_unless($parcel && Order::withoutEvents(fn () => app(SellerParcelDelivery::class)->verifiedSellerId($parcel)) === (int) $request->user()->id, 403);
        abort_unless(in_array($parcel->status, ['PLACED', 'PREPARING'], true) && trim((string) $parcel->collection_address) !== '', 422, 'Only an uncollected pickup with a collection address can share its location.');
        $point = $this->point($request);
        app(PickupLocations::class)->saveSeller($parcel, $point);
        return response()->json(['message' => 'Collection point shared for 15 minutes. Refresh it if pickup is delayed.'])->header('Cache-Control', 'no-store');
    }
    public function rider(Request $request)
    {
        $rider = $request->user()->rider;
        if ($request->isMethod('delete')) {
            app(PickupLocations::class)->clearRider($rider);
            return response()->json(['message' => 'Pickup location sharing stopped.']);
        }
        $point = $this->point($request);
        $token = $request->user()->currentAccessToken();
        $session = $token && method_exists($token, 'getKey') ? 'token:'.$token->getKey() : 'user:'.$request->user()->id;
        abort_unless(app(RiderPresence::class)->snapshot($rider)['presence'] === 'Online'
            && app(RiderPresence::class)->currentSession($rider, $session), 409, 'Choose Go Online in this session before sharing your location.');
        app(PickupLocations::class)->saveRider($rider, $point, $session);
        return response()->json(['message' => 'Pickup location shared for 90 seconds. Share again when it expires.'])->header('Cache-Control', 'no-store');
    }
    private function point(Request $request): array
    {
        $input = $request->validate(['latitude' => ['required', 'numeric', 'between:-90,90'], 'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'gt:0', 'max:100000'], 'captured_at' => ['required', 'integer'], 'permission_confirmed' => ['required', 'accepted']]);
        abort_unless($input['captured_at'] <= now()->timestamp + 5 && $input['captured_at'] > now()->timestamp - 90, 422, 'A fresh device location is required. Request location permission and try again.');
        return ['latitude' => (float) $input['latitude'], 'longitude' => (float) $input['longitude'], 'accuracy' => (float) $input['accuracy'], 'captured_at' => (int) $input['captured_at']];
    }
}
