<?php

namespace App\Services;

use App\Models\ParcelHubJourney;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class HubOperations
{
    public const STAGES = ['all', 'pickup_inbound', 'dispatch_ready', 'transfer_inbound', 'at_hub', 'out_for_delivery'];

    public function queue(int $companyId, ?int $hubId, string $stage): Builder
    {
        $query = ParcelHubJourney::where('sorting_center_id', $companyId)
            ->whereHas('parcel', fn ($q) => $q->where('sorting_center_id', $companyId));
        if ($hubId) $query->where(fn ($q) => $q->where('origin_hub_id', $hubId)->orWhere('destination_hub_id', $hubId));
        return match ($stage) {
            'pickup_inbound' => $query->where('origin_hub_id', $hubId)->whereNull('origin_received_at')
                ->whereHas('parcel', fn ($q) => $q->where('status', 'PICKED_UP')),
            'dispatch_ready' => $query->where('origin_hub_id', $hubId)->where('current_hub_id', $hubId)
                ->whereColumn('origin_hub_id', '!=', 'destination_hub_id')->whereNotNull('origin_received_at')->whereDoesntHave('transfer')
                ->whereHas('parcel', fn ($q) => $q->where('status', 'AT_SORTING_CENTER')->whereNull('assigned_rider_id')),
            'transfer_inbound' => $query->where('destination_hub_id', $hubId)->whereNull('destination_received_at')
                ->whereHas('transfer', fn ($q) => $q->whereNull('received_at'))
                ->whereHas('parcel', fn ($q) => $q->where('status', 'IN_HUB_TRANSIT')),
            'at_hub' => $query->where('current_hub_id', $hubId)->whereHas('parcel', fn ($q) => $q->where('status', 'AT_SORTING_CENTER')),
            'out_for_delivery' => $query->where('destination_hub_id', $hubId)->whereHas('parcel', fn ($q) => $q->where('status', 'OUT_FOR_DELIVERY')),
            default => $query,
        };
    }

    public function counts(int $companyId): array
    {
        $base = DB::table('parcel_hub_journeys as j')->join('parcels as p', 'p.id', '=', 'j.parcel_id')
            ->leftJoin('hub_transfers as t', 't.parcel_hub_journey_id', '=', 'j.id')
            ->where('j.sorting_center_id', $companyId)->where('p.sorting_center_id', $companyId);
        $group = fn ($query, $column) => $query->select($column)->selectRaw('COUNT(*) as total')->groupBy($column)->pluck('total', $column);
        $sets = [
            'pickup_inbound' => $group((clone $base)->where('p.status', 'PICKED_UP')->whereNull('j.origin_received_at'), 'j.origin_hub_id'),
            'dispatch_ready' => $group((clone $base)->where('p.status', 'AT_SORTING_CENTER')->whereNull('p.assigned_rider_id')
                ->whereColumn('j.current_hub_id', 'j.origin_hub_id')->whereColumn('j.origin_hub_id', '!=', 'j.destination_hub_id')
                ->whereNotNull('j.origin_received_at')->whereNull('t.id'), 'j.origin_hub_id'),
            'transfer_inbound' => $group((clone $base)->where('p.status', 'IN_HUB_TRANSIT')->whereNull('j.destination_received_at')
                ->whereNotNull('t.id')->whereNull('t.received_at'), 'j.destination_hub_id'),
            'at_hub' => $group((clone $base)->where('p.status', 'AT_SORTING_CENTER')->whereNotNull('j.current_hub_id'), 'j.current_hub_id'),
            'out_for_delivery' => $group((clone $base)->where('p.status', 'OUT_FOR_DELIVERY'), 'j.destination_hub_id'),
        ];
        $result = [];
        foreach ($sets as $stage => $counts) foreach ($counts as $hub => $count) $result[$hub][$stage] = (int) $count;
        return $result;
    }
}
