<?php

namespace App\Services;

use App\Models\{Parcel, Rider};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class RiderWorkload
{
    public function append(Collection $riders, int $centerId): void
    {
        if ($riders->isEmpty()) return;
        $ids = $riders->modelKeys();
        $eligible = Rider::where('sorting_center_id', $centerId)->whereIn('id', $ids)
            ->operationallyEligible()->pluck('id')->all();
        $pickups = $deliveries = $completed = $parcelCounts = [];
        $active = Parcel::where('sorting_center_id', $centerId)
            ->where(function ($query) use ($ids) {
                $query->where(fn ($query) => $query->whereIn('pickup_rider_id', $ids)->whereIn('status', ['PLACED', 'PREPARING', 'PICKED_UP']))
                    ->orWhere(fn ($query) => $query->whereIn('assigned_rider_id', $ids)->whereIn('status', ['AT_SORTING_CENTER', 'OUT_FOR_DELIVERY']))
                    ->orWhere(fn ($query) => $query->whereIn('status', ['LOST', 'DAMAGED'])
                        ->where(fn ($query) => $query->whereIn('pickup_rider_id', $ids)->orWhereIn('assigned_rider_id', $ids)));
            })->with('orderItem:id,seller_shipment_id,status')
            ->get(['id', 'order_item_id', 'pickup_rider_id', 'assigned_rider_id', 'status']);
        foreach ($active as $parcel) {
            if ($parcel->orderItem?->status === 'cancelled') continue;
            $key = $parcel->orderItem?->seller_shipment_id ? 'shipment:'.$parcel->orderItem->seller_shipment_id : 'parcel:'.$parcel->id;
            $pickup = in_array($parcel->status, ['PLACED', 'PREPARING', 'PICKED_UP'], true)
                || (in_array($parcel->status, ['LOST', 'DAMAGED'], true) && !$parcel->assigned_rider_id);
            $riderId = $pickup ? $parcel->pickup_rider_id : $parcel->assigned_rider_id;
            if ($pickup) $pickups[$riderId][$key] = true;
            else $deliveries[$riderId][$key] = true;
            $parcelCounts[$riderId][$parcel->id] = true;
        }
        $start = CarbonImmutable::now('Asia/Manila')->startOfDay()->utc();
        $end = $start->addDay();
        $delivered = Parcel::where('sorting_center_id', $centerId)->whereIn('assigned_rider_id', $ids)->where('status', 'DELIVERED')
            ->where('delivered_at', '>=', $start)->where('delivered_at', '<', $end)
            ->with(['orderItem:id,seller_shipment_id', 'orderItem.sellerShipment:id,assigned_rider_id,sorting_center_id,completed_at'])
            ->get(['id', 'order_item_id', 'assigned_rider_id']);
        foreach ($delivered as $parcel) {
            $shipment = $parcel->orderItem?->sellerShipment;
            if ($parcel->orderItem?->seller_shipment_id) {
                if (!$shipment?->completed_at || $shipment->completed_at->lt($start) || $shipment->completed_at->gte($end)
                    || (int) $shipment->sorting_center_id !== $centerId || (int) $shipment->assigned_rider_id !== (int) $parcel->assigned_rider_id) continue;
                $key = 'shipment:'.$shipment->id;
            } else $key = 'parcel:'.$parcel->id;
            $completed[$parcel->assigned_rider_id][$key] = true;
        }
        foreach ($riders as $rider) {
            $pickupCount = count($pickups[$rider->id] ?? []);
            $deliveryCount = count($deliveries[$rider->id] ?? []);
            $rider->setAttribute('operational_eligible', in_array($rider->id, $eligible, true));
            $rider->setAttribute('availability', $rider->approval_status === 'approved' && !$rider->is_active ? 'Suspended'
                : (in_array($rider->id, $eligible, true) ? 'Eligible for assignments' : 'Account not eligible'));
            foreach (app(RiderPresence::class)->snapshot($rider, in_array($rider->id, $eligible, true)) as $field => $value) {
                $rider->setAttribute($field, $value);
            }
            $rider->setAttribute('pickup_jobs_count', $pickupCount);
            $rider->setAttribute('delivery_jobs_count', $deliveryCount);
            $rider->setAttribute('active_jobs_count', $pickupCount + $deliveryCount);
            $rider->setAttribute('active_parcels_count', count($parcelCounts[$rider->id] ?? []));
            $rider->setAttribute('completed_jobs_today', count($completed[$rider->id] ?? []));
        }
    }
}
