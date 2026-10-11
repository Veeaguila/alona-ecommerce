<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Parcel;
use App\Models\PickupRequest;
use Illuminate\Support\Facades\DB;

class OrderItemPickup
{
    public function create(OrderItem $item, ?string $collectionAddress = null): Parcel
    {
        return DB::transaction(function () use ($item, $collectionAddress): Parcel {
            $item = OrderItem::query()->with(['order.user', 'product.seller'])->lockForUpdate()->findOrFail($item->id);
            abort_unless($item->status === 'ready_for_pickup', 422, 'The item must be ready for pickup.');
            $existing = Parcel::where('order_item_id', $item->id)->first();
            if ($existing) {
                return $existing;
            }
            $seller = $item->product->seller;
            $buyer = $item->order->user;
            // Preserve the order's free-text destination, rather than substituting
            // the buyer's current profile address.
            $parcel = Parcel::create([
                'order_item_id' => $item->id,
                'order_id' => 'ITEM-'.$item->id,
                'buyer_id' => $buyer->id,
                'seller_id' => $seller->id,
                'status' => 'PLACED',
                'delivery_address' => $item->order->shipping_address,
                'delivery_province' => '',
                'delivery_municipality' => '',
                'delivery_barangay' => '',
                'delivery_street' => null,
                'collection_address' => $collectionAddress ?: ($seller->address ?: implode(', ', array_filter([
                    $seller->street_address, $seller->barangay, $seller->municipality, $seller->province,
                ]))),
            ]);
            PickupRequest::create([
                'parcel_id' => $parcel->id,
                'seller_id' => $seller->id,
                'status' => 'pending',
            ]);
            $item->update([
                'tracking_number' => $parcel->order_id,
                'courier_name' => 'Alona Logistics',
            ]);
            return $parcel;
        });
    }
}
