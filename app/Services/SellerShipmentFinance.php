<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Parcel;
use App\Models\RiderDeliveryEarning;
use App\Models\SellerDeliveryCharge;
use App\Models\SellerShipment;

class SellerShipmentFinance
{
    // All callers hold a transaction. Lock parent order, shipment, then members/parcels.
    public function lockItem(int $id): ?SellerShipment
    {
        $shipmentId = OrderItem::whereKey($id)->value('seller_shipment_id');

        if (! $shipmentId) {
            return null;
        }
        $orderId = SellerShipment::whereKey($shipmentId)->value('order_id');
        Order::whereKey($orderId)->lockForUpdate()->firstOrFail();

        return SellerShipment::whereKey($shipmentId)->lockForUpdate()->firstOrFail();
    }

    public function lockParcel(int $id): ?SellerShipment
    {
        $itemId = Parcel::whereKey($id)->value('order_item_id');

        return $itemId ? $this->lockItem((int) $itemId) : null;
    }

    public function createMembership(Order $order): void
    {
        if ($order->delivery_fee_payer !== 'seller') {
            return;
        }
        foreach ($order->items()->with('product')->get()->groupBy('product.seller_id') as $sellerId => $items) {
            abort_unless((int) $sellerId > 0, 422, 'A shipment seller is required.');
            $shipment = SellerShipment::firstOrCreate(['order_id' => $order->id, 'seller_id' => $sellerId]);
            foreach ($items as $item) {
                abort_if($item->seller_shipment_id && (int) $item->seller_shipment_id !== $shipment->id, 422, 'Shipment membership cannot change.');
                if (! $item->seller_shipment_id) {
                    $this->assertMutable($shipment);
                    $item->update(['seller_shipment_id' => $shipment->id]);
                }
            }
        }
    }

    public function assertMutable(?SellerShipment $shipment): void
    {
        abort_if($shipment?->dispatched_at, 422, 'Shipment membership is frozen after dispatch.');
    }

    public function assign(?SellerShipment $shipment, Parcel $parcel, int $riderId, int $centerId): void
    {
        if (! $shipment) {
            return;
        }
        abort_unless((int) $parcel->seller_id === (int) $shipment->seller_id, 422, 'Shipment seller mismatch.');
        abort_if($shipment->dispatched_at, 422, 'This shipment has already been dispatched.');
        abort_if($shipment->assigned_rider_id && (int) $shipment->assigned_rider_id !== $riderId, 422, 'All shipment parcels require the same delivery rider.');
        abort_if($shipment->sorting_center_id && (int) $shipment->sorting_center_id !== $centerId, 422, 'All shipment parcels require the same sorting center.');
        $shipment->update(['assigned_rider_id' => $riderId, 'sorting_center_id' => $centerId]);
    }

    public function dispatch(?SellerShipment $shipment, int $riderId): void
    {
        if (! $shipment) {
            return;
        }
        abort_unless((int) $shipment->assigned_rider_id === $riderId, 422, 'Shipment rider mismatch.');
        if ($shipment->dispatched_at) {
            return;
        }
        $items = $this->currentMembers($shipment);
        abort_if($items->isEmpty(), 422, 'A cancelled shipment cannot be dispatched.');
        $collection = null;
        foreach ($items as $item) {
            $parcel = $item->parcel;
            abort_unless($parcel && $parcel->status === 'AT_SORTING_CENTER'
                && (int) $parcel->assigned_rider_id === $riderId
                && (int) $parcel->sorting_center_id === (int) $shipment->sorting_center_id
                && (int) $parcel->seller_id === (int) $shipment->seller_id
                && $parcel->delivery_address === $shipment->order->shipping_address,
                422, 'Every shipment item must be ready at the same center and assigned to the same rider.');
            $address = trim((string) $parcel->collection_address);
            abort_if($collection !== null && $collection !== $address, 422, 'Shipment items must share one pickup location.');
            $collection = $address;
        }
        $shipment->update(['dispatched_at' => now()]);
    }

    public function recognize(?SellerShipment $shipment): void
    {
        if (! $shipment || ! $shipment->dispatched_at || $shipment->completed_at) {
            return;
        }
        $items = $this->currentMembers($shipment);
        if ($items->isEmpty() || $shipment->order->delivery_fee_payer !== 'seller') {
            return;
        }
        foreach ($items as $item) {
            $parcel = $item->parcel;
            if (! $parcel || $parcel->status !== 'DELIVERED'
                || (int) $parcel->assigned_rider_id !== (int) $shipment->assigned_rider_id
                || (int) $parcel->seller_id !== (int) $shipment->seller_id
                || (int) $parcel->sorting_center_id !== (int) $shipment->sorting_center_id
                || ! $parcel->deliveryProofs()->where('rider_id', $shipment->assigned_rider_id)->lockForUpdate()->first()) {
                return;
            }
        }
        // Integer minor units. Persist fixed decimal strings; never derive from vouchers/order total.
        $fee = 5000;
        $logistics = intdiv($fee * 2000, 10000);
        $money = fn (int $cents): string => intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
        $charge = SellerDeliveryCharge::create([
            'seller_shipment_id' => $shipment->id, 'seller_id' => $shipment->seller_id,
            'sorting_center_id' => $shipment->sorting_center_id, 'currency' => 'PHP',
            'amount' => $money($fee), 'logistics_amount' => $money($logistics), 'rider_amount' => $money($fee - $logistics),
            'logistics_basis_points' => 2000, 'pricing_rule' => 'seller-shipment-flat-50-v1', 'recognized_at' => now(),
        ]);
        RiderDeliveryEarning::create(['seller_delivery_charge_id' => $charge->id, 'rider_id' => $shipment->assigned_rider_id,
            'amount' => $charge->rider_amount, 'earned_at' => $charge->recognized_at]);
        $shipment->update(['completed_at' => $charge->recognized_at]);
    }

    private function currentMembers(SellerShipment $shipment): \Illuminate\Database\Eloquent\Collection
    {
        // Locking reads see the latest committed siblings even under MySQL REPEATABLE READ.
        return $shipment->items()->where('status', '!=', 'cancelled')->orderBy('id')->lockForUpdate()
            ->with(['parcel' => fn ($query) => $query->orderBy('id')->lockForUpdate()])->get();
    }
}
