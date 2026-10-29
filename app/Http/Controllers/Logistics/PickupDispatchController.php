<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\{Parcel, Rider};
use App\Services\{PickupDispatch, RiderAllocation, RiderPresence, RiderWorkload};
use Illuminate\Http\Request;

class PickupDispatchController extends Controller
{
    public function nearest(Request $request, int $id)
    {
        $centerId = (int) $request->user()->sortingCenter->id;
        Parcel::where('sorting_center_id', $centerId)->findOrFail($id);
        $offer = app(\App\Services\NearestPickupOffer::class)->offer($request->user(), $centerId, $id);
        return response()->json(['message' => 'Nearest eligible Rider offered the whole pickup shipment for 60 seconds. Assignment occurs only after Rider acceptance.',
            'data' => ['rider_name' => Rider::findOrFail($offer->rider_id)->name, 'expires_at' => $offer->expires_at]]);
    }
    public function riders(Request $request)
    {
        $centerId = (int) $request->user()->sortingCenter->id;
        $riders = Rider::where('sorting_center_id', $centerId)->operationallyEligible()->with('user:id,name')->orderBy('name')->get();
        app(RiderWorkload::class)->append($riders, $centerId);
        $data = $riders->filter(fn ($rider) => $rider->presence === 'Online' && $rider->active_jobs_count < RiderAllocation::MAX_ACTIVE_JOBS)
            ->map(fn ($rider) => ['id' => $rider->id, 'name' => $rider->name, 'presence' => $rider->presence,
                'active_jobs_count' => $rider->active_jobs_count])->values();
        return response()->json(['data' => $data, 'meta' => ['empty_reason' => $data->isEmpty()
            ? 'No approved Online Rider has capacity. Riders must Go Online; the limit is three active shipment jobs across both legs.' : null]]);
    }

    public function assign(Request $request, int $id)
    {
        $input = $request->validate(['rider_id' => ['required', 'integer', 'min:1'], 'from_rider_id' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:300', 'required_with:from_rider_id', 'min:10']]);
        $centerId = (int) $request->user()->sortingCenter->id;
        Parcel::where('sorting_center_id', $centerId)->findOrFail($id);
        $parcel = app(PickupDispatch::class)->assign($request->user(), $centerId, $id, $input['rider_id'], $input['from_rider_id'] ?? null, $input['reason'] ?? null);
        return response()->json(['message' => 'Whole pickup shipment assigned. The Rider must acknowledge before collection.', 'data' => $parcel]);
    }

    public function bulk(Request $request)
    {
        $input = $request->validate(['rider_id' => ['required', 'integer', 'min:1'], 'parcel_ids' => ['required', 'array', 'min:1', 'max:50'],
            'parcel_ids.*' => ['required', 'integer', 'min:1', 'distinct']]);
        $service = app(PickupDispatch::class); $service->requireStorage();
        $centerId = (int) $request->user()->sortingCenter->id;
        $results = [];
        foreach ($input['parcel_ids'] as $id) {
            try {
                Parcel::where('sorting_center_id', $centerId)->findOrFail($id);
                $service->assign($request->user(), $centerId, $id, $input['rider_id']);
                $results[] = ['parcel_id' => $id, 'status' => 'assigned'];
            } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface|\Illuminate\Database\Eloquent\ModelNotFoundException $error) {
                $results[] = ['parcel_id' => $id, 'status' => 'skipped', 'reason' => $error instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 'Pickup not found in your organization.' : $error->getMessage()];
            }
        }
        return response()->json(['message' => 'Bulk assignment completed. Each whole shipment was checked independently.', 'data' => $results]);
    }

    public function acknowledge(Request $request, int $id)
    {
        return response()->json(['message' => 'Pickup assignment acknowledged.', 'data' => app(PickupDispatch::class)->acknowledge($request->user(), $id)]);
    }

    public function decline(Request $request, int $id)
    {
        $rider = $request->user()->rider;
        Parcel::where('sorting_center_id', $rider->sorting_center_id)->findOrFail($id);
        app(\App\Services\NearestPickupOffer::class)->advance($request->user(), (int) $rider->sorting_center_id, $id, (int) $rider->id);
        return response()->json(['message' => 'Pickup offer declined. The next eligible Rider is offered the shipment, or it returns to the unassigned queue.']);
    }
}
