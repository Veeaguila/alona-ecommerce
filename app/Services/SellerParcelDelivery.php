<?php

namespace App\Services;

use App\Models\{DeliveryProof, OrderItem, Parcel, SellerNotification, User};
use Illuminate\Support\Facades\Log;

class SellerParcelDelivery
{
    public function verifiedSellerId(Parcel $parcel): ?int
    {
        $sellerId = $this->verifiedItem($parcel)?->product?->seller_id;
        return $sellerId === null ? null : (int) $sellerId;
    }

    private function verifiedItem(Parcel $parcel): ?OrderItem
    {
        // Keep verification reads off the response model's relation graph.
        $item = OrderItem::with(['product:id,seller_id', 'order:id,order_number', 'sellerShipment:id,seller_id'])->find($parcel->order_item_id);
        $sellerId = $item?->product?->seller_id;
        if (!$item?->order || !$sellerId || (int) $sellerId !== (int) $parcel->seller_id) return null;
        if ($item->seller_shipment_id && (int) $item->sellerShipment?->seller_id !== (int) $sellerId) return null;
        return User::whereKey($sellerId)->where('usertype', 'seller')->exists() ? $item : null;
    }

    /** Called inside the existing locked delivery transaction, after status/history update. */
    public function notifyDelivered(Parcel $parcel): void
    {
        if ($parcel->status !== 'DELIVERED') return;
        $item = $this->verifiedItem($parcel);
        if (!$item) {
            Log::notice('Seller delivery notice skipped: no verified seller/order-item link.', ['parcel_id' => $parcel->id]);
            return;
        }
        $sellerId = (int) $item->product->seller_id;
        $url = route('seller.order.details', ['orderItem' => $item->id], false);
        // Parcel lock and one-way completion serialize retries; an item has one parcel.
        SellerNotification::firstOrCreate(['user_id' => $sellerId, 'type' => 'delivery', 'url' => $url], [
            'title' => 'Delivery confirmed',
            'message' => 'Order #'.$item->order->order_number.' — parcel '.$parcel->order_id.' was delivered. Open delivery details to view the photo proof.',
        ]);
    }

    public function details(OrderItem $item, int $sellerId): ?array
    {
        $parcel = $item->parcel;
        if (!$parcel || $this->verifiedSellerId($parcel) !== $sellerId) return null;
        $proofs = $parcel->status === 'DELIVERED'
            ? $parcel->deliveryProofs()->where('type', 'PHOTO')->where('rider_id', $parcel->assigned_rider_id)->latest('captured_at')->get()
            : collect();
        return ['reference' => $parcel->order_id, 'status' => $parcel->status, 'delivered_at' => $parcel->delivered_at,
            'proofs' => $proofs->map(fn (DeliveryProof $proof) => [
                'id' => $proof->id, 'type' => $proof->type, 'notes' => $proof->notes, 'recorded_at' => $proof->captured_at,
                'available' => $proof->isValidPhoto(),
                'file_url' => route('seller.delivery-proofs.file', ['orderItem' => $item->id, 'proof' => $proof->id], false),
            ])->values()->all()];
    }
}
