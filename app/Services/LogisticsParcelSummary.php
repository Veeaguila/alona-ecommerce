<?php

namespace App\Services;

use App\Models\Parcel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LogisticsParcelSummary
{
    // Reporting buckets only: these do not create or change workflow statuses.
    public const STATUSES = [
        'pending' => ['PLACED', 'PREPARING'],
        'in_transit' => ['PICKED_UP', 'IN_HUB_TRANSIT', 'OUT_FOR_DELIVERY'],
        'at_center' => ['AT_SORTING_CENTER'],
        'delivered' => ['DELIVERED'],
        'failed' => ['FAILED_RETURNED', 'LOST', 'DAMAGED'],
    ];

    public function dates(Request $request): array
    {
        $dates = $request->validate([
            'start_date' => ['bail', 'nullable', 'required_with:end_date', 'string', 'date_format:Y-m-d'],
            'end_date' => ['bail', 'nullable', 'required_with:start_date', 'string', 'date_format:Y-m-d'],
        ]);
        if (!empty($dates['start_date']) && $dates['start_date'] > $dates['end_date']) {
            throw ValidationException::withMessages(['end_date' => 'End date must be on or after start date.']);
        }
        return $dates;
    }

    public function filterDates(Builder $query, array $dates): Builder
    {
        if (!empty($dates['start_date']) && !empty($dates['end_date'])) {
            $timezone = config('app.timezone', 'UTC');
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $dates['start_date'], 'Asia/Manila')->setTimezone($timezone);
            $until = CarbonImmutable::createFromFormat('!Y-m-d', $dates['end_date'], 'Asia/Manila')->addDay()->setTimezone($timezone);
            $query->where($query->getModel()->qualifyColumn('created_at'), '>=', $start->toDateTimeString())
                ->where($query->getModel()->qualifyColumn('created_at'), '<', $until->toDateTimeString());
        }
        return $query;
    }

    public function parcels(int $centerId, array $dates): Builder
    {
        // Unclaimed shared pickup requests remain in the existing operational card.
        return $this->filterDates(Parcel::where('sorting_center_id', $centerId), $dates);
    }

    public function totals(Builder $query): array
    {
        $counts = (clone $query)->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $totals = [];
        foreach (self::STATUSES as $bucket => $statuses) {
            $totals[$bucket] = array_sum(array_map(fn ($status) => (int) ($counts[$status] ?? 0), $statuses));
        }
        return $totals;
    }
}
