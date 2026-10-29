<?php

namespace App\Services;

use App\Models\{BuyerNotification, Order, Parcel, ParcelReturn, Product, ProductVariant, Rider, SellerNotification, StockAdjustment, User};
use Illuminate\Support\Facades\DB;

class ReturnToSeller
{
    private function lock(int $returnId): array
    {
        $initial = ParcelReturn::findOrFail($returnId);
        $orderId = $initial->parcel->orderItem?->order_id;
        $order = $orderId ? Order::withoutEvents(fn () => Order::whereKey($orderId)->lockForUpdate()->firstOrFail()) : null;
        app(SellerShipmentFinance::class)->lockParcel((int) $initial->parcel_id);
        $parcel = Parcel::whereKey($initial->parcel_id)->lockForUpdate()->firstOrFail();
        $return = ParcelReturn::whereKey($returnId)->lockForUpdate()->firstOrFail();
        return [$return, $parcel, $order];
    }
    public function initiate(User $actor, int $id, int $riderId, string $reason): ParcelReturn
    {
        app(DeliveryAttempts::class)->requireStorage();
        $initial = ParcelReturn::where('sorting_center_id', $actor->sortingCenter->id)->findOrFail($id);
        return app(RiderAllocation::class)->run([$riderId], fn () => DB::transaction(function () use ($actor, $id, $riderId, $reason) {
            [$return, $parcel] = $this->lock($id);
            abort_unless((int) $return->sorting_center_id === (int) $actor->sortingCenter->id, 404);
            abort_unless($return->status === 'eligible' && $parcel->status === 'OUT_FOR_DELIVERY', 409, 'This return is not awaiting initiation.');
            $journey = app(HubNetwork::class)->journey($parcel);
            abort_if($journey && (int) $journey->origin_hub_id !== (int) $journey->destination_hub_id, 422,
                'This return needs a reverse hub transport plan. Contact company operations; a local delivery Rider cannot confirm an inter-hub Seller return.');
            abort_unless((int) $parcel->assigned_rider_id === $riderId, 422, 'Collected parcels must stay with their current custodian. Complete a supervised handover before choosing another return Rider.');
            abort_unless(Rider::whereKey($riderId)->where('sorting_center_id', $return->sorting_center_id)->operationallyEligible()->lockForUpdate()->first(), 422, 'The return Rider must be approved and operationally eligible.');
            $return->update(['rider_id' => $riderId, 'status' => 'initiated', 'reason' => $reason]);
            app(LogisticsAudit::class)->record($actor, 'rts_initiated', $parcel, ['return_id' => $id, 'reason' => $reason], $riderId);
            return $return->fresh();
        }, 5));
    }
    public function riderAction(User $actor, int $id, bool $handover): ParcelReturn
    {
        app(DeliveryAttempts::class)->requireStorage();
        return DB::transaction(function () use ($actor, $id, $handover) {
            [$return, $parcel, $order] = $this->lock($id);
            abort_unless($actor->usertype === 'rider' && (int) $return->rider_id === (int) $actor->rider?->id
                && (int) $parcel->assigned_rider_id === (int) $actor->rider?->id, 403, 'This physical return is not assigned to you.');
            abort_unless($return->status === ($handover ? 'in_transit' : 'initiated') && $parcel->status === 'OUT_FOR_DELIVERY', 409, 'This return action is already recorded or its custody changed.');
            $return->update($handover ? ['status' => 'handed_over', 'handed_over_at' => now()] : ['status' => 'in_transit', 'picked_up_at' => now()]);
            if ($handover) {
                // A failed attempt alone never reaches this status or restocks inventory.
                $parcel->update(['status' => 'FAILED_RETURNED', 'failure_reason' => $return->reason]);
                if ($order) {
                    $items = $order->items()->with('parcel.returnToSeller')->get();
                    if ($items->every(fn ($item) => $item->status === 'cancelled' || in_array($item->parcel?->returnToSeller?->status, ['handed_over', 'received'], true))) {
                        $order->update(['status' => 'cancelled', 'cancellation_reason' => 'Failed delivery — returned to Seller.', 'cancelled_at' => now()]);
                    }
                }
                $seller = app(SellerParcelDelivery::class)->verifiedSellerId($parcel);
                if ($seller) SellerNotification::create(['user_id' => $seller, 'type' => 'return_to_seller', 'title' => 'Returned parcel awaiting your receipt confirmation',
                    'message' => 'The return Rider reports handover of '.$parcel->order_id.'. Confirm physical receipt and verified units before restocking.',
                    'url' => route('seller.order.details', ['orderItem' => $parcel->order_item_id], false)]);
            }
            app(LogisticsAudit::class)->record($actor, $handover ? 'rts_handed_over' : 'rts_picked_up', $parcel,
                ['return_id' => $id, 'reason' => $return->reason], $actor->rider->id);
            return $return->fresh();
        }, 5);
    }
    public function receive(User $actor, int $id): ParcelReturn
    {
        app(DeliveryAttempts::class)->requireStorage();
        return DB::transaction(function () use ($actor, $id) {
            [$return, $parcel] = $this->lock($id);
            abort_unless($actor->usertype === 'seller' && app(SellerParcelDelivery::class)->verifiedSellerId($parcel) === (int) $actor->id, 403, 'This return belongs to another Seller.');
            abort_unless($return->status === 'handed_over' && !$return->seller_received_at && !$return->restocked_at, 409, 'Physical receipt/restocking is already recorded or the Rider has not handed over this parcel.');
            $item = $parcel->orderItem()->lockForUpdate()->firstOrFail();
            $quantity = (int) $item->quantity;
            abort_unless($quantity > 0, 422, 'The returned quantity requires manual review.');
            $product = Product::whereKey($item->product_id)->where('seller_id', $actor->id)->lockForUpdate()->firstOrFail();
            $variant = $item->product_variant_id ? ProductVariant::whereKey($item->product_variant_id)->where('product_id', $product->id)->lockForUpdate()->firstOrFail() : null;
            $before = (int) ($variant ? $variant->stock : $product->stock);
            if ($variant) $variant->increment('stock', $quantity);
            $product->increment('stock', $quantity);
            StockAdjustment::create(['seller_id' => $actor->id, 'user_id' => $actor->id, 'product_id' => $product->id,
                'product_variant_id' => $variant?->id, 'type' => 'return', 'quantity' => $quantity, 'before_stock' => $before,
                'after_stock' => $before + $quantity, 'notes' => 'Seller confirmed physical RTS receipt for '.$parcel->order_id,
                'reference_number' => 'RTS-'.$return->id]);
            $return->update(['status' => 'received', 'seller_received_at' => now(), 'restocked_at' => now()]);
            app(LogisticsAudit::class)->record($actor, 'rts_seller_received', $parcel, ['return_id' => $id, 'restocked_quantity' => $quantity,
                'before_stock' => $before, 'after_stock' => $before + $quantity, 'reference' => 'RTS-'.$return->id]);
            app(LogisticsRefundReview::class)->request($actor, $parcel->fresh(), 'rts');
            return $return->fresh();
        }, 5);
    }
}
