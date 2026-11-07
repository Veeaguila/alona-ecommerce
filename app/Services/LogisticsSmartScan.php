<?php

namespace App\Services;

use App\Models\{DeliveryProof, LogisticsAuditEvent, Order, OrderItem, Parcel, ParcelHubJourney, Rider, User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LogisticsSmartScan
{
    public function scan(User $actor, string $reference, array $options = []): array
    {
        $ref = trim($reference);
        abort_unless($ref !== '', 422, 'A waybill or tracking reference is required.');

        // Resolve parcel by order_id (ITEM-X), tracking_number, or order_number
        $parcel = Parcel::query()
            ->where('order_id', strtoupper($ref))
            ->orWhere('order_id', $ref)
            ->orWhereHas('orderItem', fn ($q) => $q->where('tracking_number', $ref))
            ->orWhereHas('orderItem.order', fn ($q) => $q->where('order_number', $ref))
            ->with([
                'orderItem.order.user',
                'orderItem.product',
                'hubJourney.transfer',
                'pickupRider.user',
                'assignedRider.user',
                'sortedArea:id,name',
            ])
            ->first();

        abort_unless($parcel, 404, "Parcel '{$ref}' was not found in the logistics network.");

        $isRider = ($actor->usertype === 'rider' && $actor->rider);
        $staff = app(HubAccess::class)->membership($actor);
        $isHubStaff = ($staff && $staff->is_active);
        $isAdmin = ($actor->usertype === 'sorting_center');

        abort_unless($isRider || $isHubStaff || $isAdmin, 403, 'You are not authorized to operate the logistics scanner.');

        if ($isRider) {
            return $this->handleRiderScan($actor, $parcel, $options);
        }

        $hubId = $isHubStaff ? (int) $staff->logistics_hub_id : (int) ($options['hub_id'] ?? 0);
        return $this->handleHubScan($actor, $parcel, $options, $hubId);
    }

    private function handleRiderScan(User $actor, Parcel $parcel, array $options): array
    {
        $rider = $actor->rider;
        abort_unless($rider && $rider->approval_status === 'approved' && $rider->is_active, 403, 'Your rider account is not active or approved.');

        $status = $parcel->status;

        // 1. PICKUP AT STORE
        if (in_array($status, ['PLACED', 'PREPARING'], true)) {
            return DB::transaction(function () use ($actor, $rider, $parcel) {
                $payGroup = app(RiderJobPay::class)->groupedMembers($parcel->id, (int) $parcel->sorting_center_id, 'pickup');
                app(SellerShipmentFinance::class)->lockParcel($parcel->id);
                $parcel = Parcel::whereKey($parcel->id)->lockForUpdate()->firstOrFail();
                abort_unless(in_array($parcel->status, ['PLACED', 'PREPARING'], true), 422, 'This parcel is no longer awaiting pickup.');

                $parcel->update([
                    'pickup_rider_id' => $rider->id,
                    'status' => 'PICKED_UP',
                ]);

                // Ensure journey plan is initialized
                app(HubNetwork::class)->ensureJourney($parcel, (int) ($rider->logistics_hub_id ?: 0));

                if ($payGroup) {
                    app(RiderJobPay::class)->assign($parcel->fresh(), (int) $rider->id, 'pickup', $actor, $payGroup);
                }

                app(ParcelTrackingTimeline::class)->record($parcel, "Picked up from seller by rider {$actor->name}.");
                app(LogisticsAudit::class)->record($actor, 'rider_picked_up', $parcel, ['rider_id' => $rider->id]);

                return [
                    'success' => true,
                    'stage' => 'picked_up',
                    'title' => 'Pickup Confirmed!',
                    'message' => "Parcel {$parcel->order_id} collected from Seller.",
                    'action_taken' => 'PICKED_UP',
                    'parcel' => $this->summary($parcel->fresh()),
                    'next_instruction' => 'Bring this parcel to your sorting hub for inbound processing.',
                ];
            }, 5);
        }

        // 2. DISPATCH FROM HUB TO DELIVERY (OUT FOR DELIVERY)
        if ($status === 'AT_SORTING_CENTER') {
            return DB::transaction(function () use ($actor, $rider, $parcel) {
                $finance = app(SellerShipmentFinance::class);
                $shipment = $finance->lockParcel($parcel->id);
                $parcel = Parcel::whereKey($parcel->id)->lockForUpdate()->firstOrFail();
                abort_unless($parcel->status === 'AT_SORTING_CENTER', 422, 'This parcel is no longer at the sorting center.');

                // Check inter-hub arrival guard if applicable
                $journey = $parcel->hubJourney ?? app(HubNetwork::class)->journey($parcel);
                if ($journey && (int) $journey->origin_hub_id !== (int) $journey->destination_hub_id) {
                    abort_unless($journey->destination_received_at, 422, 'This parcel has not been received at its destination hub yet.');
                }

                // Assign to rider if unassigned
                if (!$parcel->assigned_rider_id) {
                    $parcel->update([
                        'assigned_rider_id' => $rider->id,
                        'assigned_at' => now(),
                    ]);
                } elseif ((int) $parcel->assigned_rider_id !== (int) $rider->id) {
                    abort(422, 'This parcel is assigned to another delivery rider.');
                }

                try {
                    $payGroup = app(RiderJobPay::class)->groupedMembers($parcel->id, (int) $parcel->sorting_center_id, 'delivery');
                    if ($payGroup) {
                        app(RiderJobPay::class)->assign($parcel->fresh(), (int) $rider->id, 'delivery', $actor, $payGroup);
                    }
                } catch (\Throwable) {}

                $finance->dispatch($shipment, (int) $rider->id);

                $parcel->update(['status' => 'OUT_FOR_DELIVERY']);

                app(DeliveryOtp::class)->ensure($parcel);
                app(ParcelTrackingTimeline::class)->record($parcel, "Out for delivery with rider {$actor->name}.");
                app(LogisticsAudit::class)->record($actor, 'rider_out_for_delivery', $parcel, ['rider_id' => $rider->id]);

                return [
                    'success' => true,
                    'stage' => 'out_for_delivery',
                    'title' => 'Out for Delivery!',
                    'message' => "Parcel {$parcel->order_id} is loaded and in transit to the buyer.",
                    'action_taken' => 'OUT_FOR_DELIVERY',
                    'parcel' => $this->summary($parcel->fresh()),
                    'next_instruction' => 'Deliver to buyer address: ' . $parcel->delivery_address,
                ];
            }, 5);
        }

        // 3. DROP-OFF AT BUYER (DELIVERED)
        if ($status === 'OUT_FOR_DELIVERY') {
            abort_unless((int) $parcel->assigned_rider_id === (int) $rider->id, 403, 'This delivery is not assigned to you.');

            $order = $parcel->orderItem?->order;
            $isCod = in_array(strtolower($order?->payment_method ?? 'cod'), ['cod', 'cash_on_delivery'], true);
            $codAmount = $isCod ? (float) ($order?->total ?? 0) : 0.0;

            // If photo is not uploaded yet, prompt for photo & payment
            if (empty($options['photo'])) {
                return [
                    'success' => true,
                    'stage' => 'ready_for_dropoff',
                    'title' => 'Drop-off at Buyer',
                    'message' => $isCod
                        ? "Collect COD payment of ₱" . number_format($codAmount, 2) . " from buyer and capture delivery photo."
                        : "Prepaid order (₱0.00). Capture delivery photo.",
                    'action_needed' => 'CAPTURE_PHOTO_PROOF',
                    'is_cod' => $isCod,
                    'cod_amount' => $codAmount,
                    'cod_label' => $isCod ? ("COD: ₱" . number_format($codAmount, 2)) : "PREPAID (₱0.00)",
                    'buyer' => [
                        'name' => $order?->user?->name ?? 'Buyer',
                        'phone' => $order?->user?->contact_no ?? 'N/A',
                        'address' => $parcel->delivery_address,
                    ],
                    'parcel' => $this->summary($parcel),
                ];
            }

            // Complete delivery with photo
            return DB::transaction(function () use ($actor, $rider, $parcel, $order, $isCod, $codAmount, $options) {
                app(RiderJobPay::class)->groupedMembers($parcel->id, (int) $rider->sorting_center_id);
                $finance = app(SellerShipmentFinance::class);
                $shipment = $finance->lockParcel($parcel->id);
                $parcel = Parcel::whereKey($parcel->id)->lockForUpdate()->firstOrFail();
                abort_unless($parcel->status === 'OUT_FOR_DELIVERY', 422, 'This parcel is no longer out for delivery.');

                $photoFile = $options['photo'];
                abort_unless($photoFile instanceof UploadedFile && $photoFile->isValid(), 422, 'A valid photo proof of delivery is required.');

                $path = $photoFile->store('delivery-proofs', 'local');
                abort_unless($path, 500, 'Delivery photo could not be stored.');

                $proof = DeliveryProof::create([
                    'parcel_id' => $parcel->id,
                    'rider_id' => $rider->id,
                    'type' => 'PHOTO',
                    'file_path' => $path,
                    'notes' => $options['notes'] ?? 'Scanned delivery completion',
                    'captured_at' => now(),
                ]);

                abort_unless($proof->isValidPhoto(), 422, 'The uploaded delivery photo is unreadable or invalid.');
                app(DeliveryAttempts::class)->assertCanComplete($parcel);

                $parcel->update([
                    'status' => 'DELIVERED',
                    'delivered_at' => now(),
                ]);

                if ($order && $isCod) {
                    $order->update([
                        'payment_status' => 'paid',
                    ]);
                }

                if ($isCod && $codAmount > 0) {
                    app(CodLedger::class)->collect($actor, $parcel->id, [
                        'request_key' => (string) Str::uuid(),
                        'amount' => (string) $codAmount,
                    ], $photoFile);
                }

                app(SellerParcelDelivery::class)->notifyDelivered($parcel);
                $finance->recognize($shipment);
                try {
                    app(RiderJobPay::class)->complete($parcel, 'delivery', $actor);
                } catch (\Throwable) {}

                try {
                    app(DeliveryOtp::class)->consume($parcel);
                } catch (\Throwable) {}

                return [
                    'success' => true,
                    'stage' => 'delivered',
                    'title' => 'Delivered Successfully! 🎉',
                    'message' => "Parcel {$parcel->order_id} marked as Delivered." . ($isCod ? " Cash collected: ₱" . number_format($codAmount, 2) : ""),
                    'action_taken' => 'DELIVERED',
                    'parcel' => $this->summary($parcel->fresh()),
                ];
            }, 5);
        }

        if ($status === 'DELIVERED') {
            return [
                'success' => true,
                'stage' => 'already_delivered',
                'title' => 'Already Delivered',
                'message' => "Parcel {$parcel->order_id} was already delivered on {$parcel->delivered_at}.",
                'action_taken' => 'NONE',
                'parcel' => $this->summary($parcel),
            ];
        }

        abort(422, "Cannot process scan for parcel in status '{$status}'.");
    }

    private function handleHubScan(User $actor, Parcel $parcel, array $options, int $operatingHubId): array
    {
        $status = $parcel->status;
        $centerId = (int) $parcel->sorting_center_id;

        $journey = $parcel->hubJourney ?? app(HubNetwork::class)->journey($parcel) ?? app(HubNetwork::class)->ensureJourney($parcel, $operatingHubId ?: null);

        // Auto-detect hub if not explicitly passed
        if (!$operatingHubId && $journey) {
            $operatingHubId = (int) ($journey->current_hub_id
                ?? $journey->origin_hub_id
                ?? $journey->destination_hub_id
                ?? 0);
        }

        // 1. INBOUND RECEIVE FROM RIDER PICKUP
        if ($status === 'PICKED_UP') {
            return DB::transaction(function () use ($actor, $parcel, $operatingHubId, $journey) {
                $targetHub = $operatingHubId ?: (int) ($journey?->origin_hub_id ?? 0);
                app(HubNetwork::class)->receiveOrigin($parcel, $targetHub ?: null, $actor);
                $parcel = $parcel->fresh(['hubJourney']);
                $parcel->update(['status' => 'AT_SORTING_CENTER']);

                try {
                    app(RiderJobPay::class)->complete($parcel, 'pickup', $actor);
                } catch (\Throwable) {}

                return [
                    'success' => true,
                    'stage' => 'received_at_hub',
                    'title' => 'Inbound Parcel Received!',
                    'message' => "Parcel {$parcel->order_id} received at origin hub.",
                    'action_taken' => 'AT_SORTING_CENTER',
                    'parcel' => $this->summary($parcel),
                    'next_instruction' => 'Ready for sorting or inter-hub transfer dispatch.',
                ];
            }, 5);
        }

        // 2. INBOUND RECEIVE FROM INTER-HUB TRANSFER TRUCK
        if ($status === 'IN_HUB_TRANSIT') {
            abort_unless($journey, 422, 'No hub route found for this transit parcel.');

            $destHubId = (int) $journey->destination_hub_id;
            return DB::transaction(function () use ($actor, $parcel, $centerId, $destHubId) {
                $journey = app(HubTransport::class)->receive($actor, $centerId, $parcel->id, $destHubId);

                return [
                    'success' => true,
                    'stage' => 'transfer_received',
                    'title' => 'Linehaul Transfer Received!',
                    'message' => "Parcel {$parcel->order_id} received at destination hub: " . ($journey->route_snapshot['destination_hub_name'] ?? 'Hub'),
                    'action_taken' => 'AT_SORTING_CENTER',
                    'parcel' => $this->summary($parcel->fresh()),
                    'next_instruction' => 'Ready for local area sorting and delivery rider assignment.',
                ];
            }, 5);
        }

        // 3. OUTBOUND DISPATCH TO NEXT HUB
        if ($status === 'AT_SORTING_CENTER') {
            $needsTransfer = ($journey && (int) $journey->origin_hub_id !== (int) $journey->destination_hub_id && !$journey->destination_received_at);

            if ($needsTransfer) {
                $transportRef = trim((string) ($options['transport_reference'] ?? ''));
                if ($transportRef === '') {
                    return [
                        'success' => true,
                        'stage' => 'ready_for_transfer',
                        'title' => 'Ready for Linehaul Dispatch',
                        'message' => "Parcel needs transfer from {$journey->route_snapshot['origin_hub_name']} to {$journey->route_snapshot['destination_hub_name']}.",
                        'action_needed' => 'PROVIDE_TRANSPORT_REFERENCE',
                        'parcel' => $this->summary($parcel),
                        'destination_hub' => $journey->route_snapshot['destination_hub_name'] ?? 'Destination Hub',
                    ];
                }

                // Perform dispatch
                return DB::transaction(function () use ($actor, $centerId, $parcel, $journey, $transportRef) {
                    $input = [
                        'hub_id' => (int) $journey->origin_hub_id,
                        'transport_reference' => $transportRef,
                        'request_key' => (string) Str::uuid(),
                    ];
                    app(HubTransport::class)->dispatch($actor, $centerId, $parcel->id, $input);

                    return [
                        'success' => true,
                        'stage' => 'dispatched_transfer',
                        'title' => 'Dispatched to Linehaul Truck!',
                        'message' => "Parcel {$parcel->order_id} dispatched via {$transportRef}.",
                        'action_taken' => 'IN_HUB_TRANSIT',
                        'parcel' => $this->summary($parcel->fresh()),
                        'next_instruction' => "En route to {$journey->route_snapshot['destination_hub_name']}.",
                    ];
                }, 5);
            }

            return [
                'success' => true,
                'stage' => 'ready_for_local_delivery',
                'title' => 'At Sorting Hub',
                'message' => "Parcel {$parcel->order_id} is at the delivery hub. Ready for rider assignment or departure.",
                'action_taken' => 'NONE',
                'parcel' => $this->summary($parcel),
            ];
        }

        abort(422, "Cannot process hub scan for parcel in status '{$status}'.");
    }

    private function summary(Parcel $parcel): array
    {
        $order = $parcel->orderItem?->order;
        $isCod = in_array(strtolower($order?->payment_method ?? 'cod'), ['cod', 'cash_on_delivery'], true);
        $codAmount = $isCod ? (float) ($order?->total ?? 0) : 0.0;

        return [
            'id' => $parcel->id,
            'order_id' => $parcel->order_id,
            'tracking_number' => $parcel->orderItem?->tracking_number,
            'status' => $parcel->status,
            'product_name' => $parcel->orderItem?->product?->name ?? 'General Goods',
            'quantity' => (int) ($parcel->orderItem?->quantity ?? 1),
            'buyer_name' => $order?->user?->name ?? 'Buyer',
            'delivery_address' => $parcel->delivery_address,
            'collection_address' => $parcel->collection_address,
            'payment_method' => strtoupper($order?->payment_method ?? 'COD'),
            'is_cod' => $isCod,
            'cod_amount' => number_format($codAmount, 2),
            'cod_label' => $isCod ? ("COD ₱" . number_format($codAmount, 2)) : "PREPAID (₱0.00)",
            'assigned_rider' => $parcel->assignedRider?->user?->name,
            'sorted_area' => $parcel->sortedArea?->name,
        ];
    }
}
