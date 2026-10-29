<?php

namespace App\Services;

use App\Models\{Parcel, Rider};
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class RiderAllocation
{
    public const MAX_ACTIVE_JOBS = 3;

    // Acquire before opening the assignment transaction, so its workload snapshot sees prior allocations.
    public function run(array $riderIds, callable $callback)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $riderIds))));
        sort($ids);
        $locks = [];
        try {
            foreach ($ids as $id) {
                $lock = Cache::lock('alona:rider-allocation:v1:'.$id, 30);
                $lock->block(5);
                $locks[] = $lock;
            }
            return $callback();
        } catch (LockTimeoutException) {
            abort(409, 'This Rider is being updated. Refresh and try again.');
        } finally {
            foreach (array_reverse($locks) as $lock) $lock->release();
        }
    }

    public function assertCanReceive(Rider $rider, Parcel $parcel, string $leg, bool $requireOnline = false): void
    {
        app(HubNetwork::class)->assertRider($parcel, $rider, $leg);
        $presence = app(RiderPresence::class)->snapshot($rider)['presence'];
        abort_if($presence === 'Offline' || ($requireOnline && $presence !== 'Online'), 422,
            $requireOnline ? 'This Rider must explicitly Go Online before receiving a manual pickup assignment.' : 'This Rider is Offline and cannot receive new work.');
        if ($this->alreadyCounted($rider, $parcel, $leg)) return;
        app(RiderWorkload::class)->append(new Collection([$rider]), (int) $rider->sorting_center_id);
        abort_if($rider->active_jobs_count >= self::MAX_ACTIVE_JOBS, 422,
            'This Rider already has three active shipment jobs. Complete a job before assigning another.');
    }

    public function alreadyCounted(Rider $rider, Parcel $parcel, string $leg): bool
    {
        $field = $leg === 'pickup' ? 'pickup_rider_id' : 'assigned_rider_id';
        $statuses = $leg === 'pickup' ? ['PLACED', 'PREPARING', 'PICKED_UP'] : ['AT_SORTING_CENTER', 'OUT_FOR_DELIVERY'];
        $shipmentId = $parcel->orderItem?->seller_shipment_id;
        return $shipmentId && Parcel::where($field, $rider->id)->whereIn('status', $statuses)
            ->whereHas('orderItem', fn ($query) => $query->where('seller_shipment_id', $shipmentId)->where('status', '!=', 'cancelled'))->exists();
    }
}
