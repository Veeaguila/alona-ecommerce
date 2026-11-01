<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Parcel;
use App\Models\PickupRequest;
use Illuminate\Support\Facades\DB;

class OrderItemPickup
{
    public function create(OrderItem $item, ?string $collectionAddress = null, array $scheduling = []): Parcel
    {
        return DB::transaction(function () use ($item, $collectionAddress, $scheduling): Parcel {
            $finance = app(SellerShipmentFinance::class);
            $shipment = $finance->lockItem($item->id);
            $finance->assertMutable($shipment);
            $item = OrderItem::query()->with(['order.user', 'product.seller'])->lockForUpdate()->findOrFail($item->id);
            abort_unless($item->status === 'ready_for_pickup', 422, 'The item must be ready for pickup.');
            $existing = Parcel::where('order_item_id', $item->id)->first();

            $scheduledDate = $scheduling['scheduled_date'] ?? $item->scheduled_pickup_date ?? now()->addDay()->toDateString();
            $timeSlot = $scheduling['time_slot'] ?? $item->pickup_time_slot ?? 'morning';
            $carrierPartner = $scheduling['carrier_partner'] ?? $item->preferred_carrier ?? 'Alona Express';
            $notes = $scheduling['notes'] ?? $item->pickup_notes ?? null;
            $hubPlan = $item->hub_route_plan;
            if ($hubPlan) {
                $carrierPartner = $hubPlan['company_name'];
                if ($collectionAddress) abort_unless(app(HubNetwork::class)->cityCode($collectionAddress) === $hubPlan['origin_city_code'],
                    422, 'The pickup city differs from the booked hub route. Ask Logistics to review the booking.');
            }

            if ($existing) {
                // Update existing pickup request if present
                $existingPickup = $existing->pickupRequests()->latest()->first();
                if ($existingPickup) {
                    $existingPickup->update([
                        'scheduled_date' => $scheduledDate,
                        'time_slot' => $timeSlot,
                        'carrier_partner' => $carrierPartner,
                        'notes' => $notes,
                    ]);
                }
                $item->update([
                    'scheduled_pickup_date' => $scheduledDate,
                    'pickup_time_slot' => $timeSlot,
                    'preferred_carrier' => $carrierPartner,
                    'pickup_notes' => $notes,
                    'courier_name' => $carrierPartner,
                ]);
                return $existing;
            }
            $seller = $shipment ? \App\Models\User::findOrFail($shipment->seller_id) : $item->product->seller;
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
                ...($hubPlan ? ['sorting_center_id' => $hubPlan['sorting_center_id']] : []),
                'seller_id' => $seller->id,
                'status' => 'pending',
                'scheduled_date' => $scheduledDate,
                'time_slot' => $timeSlot,
                'carrier_partner' => $carrierPartner,
                'notes' => $notes,
            ]);

            if ($hubPlan) {
                app(HubNetwork::class)->attach($parcel, $hubPlan);
                $parcel->update(['sorting_center_id' => $hubPlan['sorting_center_id']]);
            }

            $item->update([
                'tracking_number' => $parcel->order_id,
                'courier_name' => $carrierPartner,
                'scheduled_pickup_date' => $scheduledDate,
                'pickup_time_slot' => $timeSlot,
                'preferred_carrier' => $carrierPartner,
                'pickup_notes' => $notes,
            ]);

            return $parcel;
        });
    }
}
