<?php

namespace App\Services;

use App\Models\{Parcel, PickupRequest};

class ParcelTrackingTimeline
{
    public const ACCEPTED = 'Logistics accepted pickup request.';
    public const PICKUP_ASSIGNED = 'Pickup Rider assigned.';
    public const ARRIVED = 'Arrived at Sorting Center.';
    public const SORTED = 'Sorted to delivery area.';
    public const DELIVERY_ASSIGNED = 'Delivery Rider assigned.';
    public const OUTBOUND = 'Logistics confirmed outbound readiness.';

    public function record(Parcel $parcel, string $note): void
    {
        if ($parcel->order_item_id && ($item = $parcel->orderItem)) {
            $item->statusHistories()->create(['status' => $item->status, 'note' => $note]);
        }
    }

    public function forPickup(PickupRequest $request): array
    {
        $parcel = $request->parcel;
        $events = [];
        $add = function (string $label, $at) use (&$events): void {
            $events[] = ['label' => $label, 'occurred_at' => $at?->toISOString()];
        };
        $recorded = function (string $note) use ($parcel) {
            return $parcel?->orderItem?->statusHistories->firstWhere('note', $note)?->created_at;
        };
        $add('Pickup requested', $request->requested_at ?? $request->created_at);
        if ($request->status === 'confirmed') $add('Logistics accepted', $recorded(self::ACCEPTED));
        if (!$parcel) return $events;
        if ($parcel->pickup_rider_id || $recorded(self::PICKUP_ASSIGNED)) $add('Pickup Rider assigned', $recorded(self::PICKUP_ASSIGNED));
        $arrived = in_array($parcel->status, ['AT_SORTING_CENTER', 'IN_HUB_TRANSIT', 'OUT_FOR_DELIVERY', 'DELIVERED', 'FAILED_RETURNED'], true);
        if ($parcel->status === 'PICKED_UP' || $arrived) $add('Picked up / In transit to center', $recorded('Courier update: PICKED UP.'));
        if ($arrived) $add('Arrived at Sorting Center', $recorded(self::ARRIVED));
        if ($parcel->sorted_at) $add('Sorted to delivery area', $recorded(self::SORTED) ?? $parcel->sorted_at);
        if ($parcel->assigned_rider_id) $add('Delivery Rider assigned', $recorded(self::DELIVERY_ASSIGNED));
        if ($at = $recorded(self::OUTBOUND)) $add('Outbound readiness confirmed', $at);
        if ($parcel->assigned_at || in_array($parcel->status, ['OUT_FOR_DELIVERY', 'DELIVERED', 'FAILED_RETURNED'], true)) {
            $add('Buyer delivery started', $parcel->assigned_at ?? $recorded('Courier update: OUT FOR DELIVERY.'));
        }
        if ($parcel->status === 'DELIVERED') $add('Delivered to Buyer', $parcel->delivered_at ?? $recorded('Courier update: DELIVERED.'));
        if ($parcel->status === 'FAILED_RETURNED') {
            $failure = $parcel->orderItem?->statusHistories->first(fn ($event) => str_starts_with((string) $event->note, 'Delivery failed and returned:'));
            $add('Delivery failed / returned', $failure?->created_at);
        }
        if ($parcel->relationLoaded('logisticsEvents')) {
            $hasAuditEvents = false;
            foreach ($parcel->logisticsEvents as $event) {
                $label = match ($event->action) {
                    'pickup_released' => 'Uncollected pickup released to available pool',
                    'pickup_reassigned' => 'Pickup Rider reassigned',
                    'handover_confirmed' => 'Supervised Rider handover confirmed',
                    'status_override' => 'Logistics status override',
                    'incident_reported' => 'Parcel reported '.($event->data['type'] ?? 'as an incident'),
                    'delivery_attempt_failed' => 'Delivery attempt '.($event->data['attempt_number'] ?? '').' failed',
                    'delivery_retry_started' => 'Delivery attempt '.($event->data['attempt_number'] ?? '').' started',
                    'rts_initiated' => 'Return to Seller initiated by Logistics',
                    'rts_picked_up' => 'Return parcel in transit to Seller',
                    'rts_handed_over' => 'Return handed to Seller; awaiting Seller receipt confirmation',
                    'rts_seller_received' => 'Seller confirmed physical receipt and restocking',
                    'logistics_claim_opened' => 'Loss claim opened for review',
                    'pickup_nearest_offered' => 'Pickup offered to nearest eligible Rider',
                    'hub_transfer_dispatched' => 'Shipment dispatched to destination hub',
                    'hub_transfer_received' => 'Shipment received at destination hub',
                    'logistics_refund_requested' => 'Refund review requested; no refund issued',
                    'logistics_refund_review_blocked' => 'Refund review needs an authorized decision',
                    default => null,
                };
                if ($label) { $add($label, $event->created_at); $hasAuditEvents = true; }
            }
            if ($hasAuditEvents) usort($events, fn ($a, $b) => strcmp($a['occurred_at'] ?? '', $b['occurred_at'] ?? ''));
        }
        return $events;
    }
}
