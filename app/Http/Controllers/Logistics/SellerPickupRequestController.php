<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Services\OrderItemPickup;
use Illuminate\Http\Request;

class SellerPickupRequestController extends Controller
{
    public function store(Request $request, OrderItemPickup $pickups)
    {
        $data = $request->validate([
            'order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'collection_address' => ['nullable', 'string', 'max:1000'],
        ]);
        $item = OrderItem::with('product')->findOrFail($data['order_item_id']);
        abort_unless((int) $item->product?->seller_id === (int) $request->user()->id, 403);
        $parcel = $pickups->create($item, $data['collection_address'] ?? null);
        return response()->json([
            'message' => 'Pickup request submitted successfully',
            'parcel' => $parcel,
            'pickup_request' => $parcel->pickupRequests()->first(),
        ], 201);
    }
}
