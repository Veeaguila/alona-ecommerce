<?php

namespace App\Services;

use App\Models\{LogisticsHub, Parcel, ParcelHubJourney, User};

class HubWorkspace
{
    public function lookup(User $actor, string $reference, int $hubId): array
    {
        $context = app(HubAccess::class)->context($actor, $hubId, true);
        $company = (int) $context['center']->id;
        $query = Parcel::where('sorting_center_id', $company)->where('order_id', $reference);
        if ($context['staff']) {
            $query->whereHas('hubJourney', fn ($q) => $q->where('sorting_center_id', $company)
                ->where(fn ($q) => $q->where('origin_hub_id', $hubId)->orWhere('destination_hub_id', $hubId)));
        }
        $parcel = $query->firstOrFail();
        $journey = ParcelHubJourney::where('sorting_center_id', $company)->where('parcel_id', $parcel->id)->with('transfer')->first();
        if (!$journey) {
            $journey = app(HubNetwork::class)->ensureJourney($parcel, $hubId);
        }

        // Validate that this parcel belongs to or is routed through the selected hub
        $isRelevantHub = false;
        if ($journey) {
            $isRelevantHub = ((int) $journey->origin_hub_id === (int) $hubId)
                || ((int) $journey->destination_hub_id === (int) $hubId)
                || ($journey->current_hub_id && (int) $journey->current_hub_id === (int) $hubId);
        } else {
            $riderHubId = $parcel->pickupRider?->logistics_hub_id;
            $isRelevantHub = $riderHubId ? ((int) $riderHubId === (int) $hubId) : true;
        }

        if (!$isRelevantHub) {
            $targetHubId = $journey?->current_hub_id ?? $journey?->origin_hub_id ?? $parcel->pickupRider?->logistics_hub_id;
            $actualHub = $targetHubId ? LogisticsHub::where('sorting_center_id', $company)->find($targetHubId) : null;
            $selectedHub = LogisticsHub::where('sorting_center_id', $company)->find($hubId);
            $actualName = $actualHub?->name ?? 'another hub';
            $selectedName = $selectedHub?->name ?? 'this hub';
            abort(422, "{$reference} is located at {$actualName}, not at {$selectedName}. Switch your operating hub to {$actualName} to manage this parcel.");
        }

        // Legacy records remain readable by their owner; their physical hubs are never guessed.
        $route = $journey?->route_snapshot;
        $current = $journey?->current_hub_id
            ? LogisticsHub::where('sorting_center_id', $company)->find($journey->current_hub_id)?->only(['id', 'name', 'address'])
            : ($parcel->status === 'AT_SORTING_CENTER' ? LogisticsHub::where('sorting_center_id', $company)->find($hubId)?->only(['id', 'name', 'address']) : null);
        $next = null;
        $action = null;
        $instruction = $journey ? 'Review the recorded parcel status before taking action.' : 'No hub route is recorded. Ask the company administrator to review this parcel in the existing parcel workflow.';
        if ($journey) {
            if (!$journey->origin_received_at) {
                $next = ['name' => $route['origin_hub_name'], 'kind' => 'hub'];
                $instruction = 'Waiting for the pickup Rider to bring this parcel to its origin hub.';
                if ($parcel->status === 'PICKED_UP' && $hubId === (int) $journey->origin_hub_id) {
                    $action = 'receive_origin';
                    $instruction = 'Check the parcel and confirm that it is physically here.';
                }
            } elseif (!$journey->destination_received_at) {
                $next = ['name' => $route['destination_hub_name'], 'kind' => 'hub'];
                $instruction = 'Waiting for dispatch from the origin hub.';
                if ($parcel->status === 'AT_SORTING_CENTER' && !$parcel->assigned_rider_id && !$journey->transfer
                    && $hubId === (int) $journey->origin_hub_id && (int) $journey->current_hub_id === $hubId) {
                    $action = 'dispatch';
                    $instruction = 'Record the actual transport reference and confirm the shipment is leaving this hub.';
                } elseif ($parcel->status === 'IN_HUB_TRANSIT') {
                    $current = null;
                    $instruction = 'In transit to the destination hub. Confirm receipt only when the parcel is physically there.';
                    if ($journey->transfer && !$journey->transfer->received_at && $hubId === (int) $journey->destination_hub_id) $action = 'receive_destination';
                }
            } else {
                $next = ['name' => $parcel->delivery_address ?: 'Recorded Buyer delivery address', 'kind' => 'delivery'];
                $instruction = $parcel->assigned_rider_id
                    ? 'Waiting for the assigned delivery Rider to collect the parcel and start delivery.'
                    : ($parcel->sorted_area_id
                        ? 'Ready for the company administrator to assign a delivery Rider in Rider Assignment.'
                        : 'Ready for the company administrator to sort this parcel to a City Service Area in Delivery Management before assigning a delivery Rider.');
            }
        }
        if ($parcel->status === 'OUT_FOR_DELIVERY') {
            $current = null; $action = null;
            $instruction = 'The assigned delivery Rider is taking this parcel to the Buyer.';
        }
        if (in_array($parcel->status, ['DELIVERED', 'FAILED_RETURNED'], true)) {
            $action = null; $current = null; $next = null;
            $instruction = 'This parcel has finished its current delivery workflow. Review its existing tracking history.';
        }
        $statusLabel = ($parcel->status === 'AT_SORTING_CENTER' && $current)
            ? 'At ' . $current['name']
            : str_replace('_', ' ', $parcel->status);

        return ['parcel' => $parcel->only(['id', 'order_id', 'status', 'collection_address', 'delivery_address']),
            'operating_hub' => $context['hub']->only(['id', 'name', 'address']), 'current_hub' => $current,
            'status_label' => $statusLabel,
            'location_label' => $current['name'] ?? match ($parcel->status) {
                'IN_HUB_TRANSIT' => 'In transit between hubs', 'PICKED_UP' => 'With pickup Rider',
                'OUT_FOR_DELIVERY' => 'With delivery Rider', 'DELIVERED' => 'Delivered', 'PLACED' => 'Awaiting pickup',
                default => 'See recorded parcel status',
            },
            'next_destination' => $next, 'action' => $action,
            'action_label' => match ($action) { 'receive_origin' => 'Receive at origin hub', 'dispatch' => 'Dispatch to next hub',
                'receive_destination' => 'Receive at destination hub', default => null },
            'instruction' => $instruction, 'route' => $route,
            'can_open_delivery_management' => !$context['staff'] && $journey?->destination_received_at && !$parcel->sorted_area_id && $parcel->status === 'AT_SORTING_CENTER',
            'can_open_delivery_assignment' => !$context['staff'] && $journey?->destination_received_at && $parcel->sorted_area_id && !$parcel->assigned_rider_id && $parcel->status === 'AT_SORTING_CENTER'];
    }
}
