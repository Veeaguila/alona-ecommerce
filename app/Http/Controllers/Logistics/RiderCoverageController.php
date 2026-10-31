<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\{Area, Rider, User};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiderCoverageController extends Controller
{
    public function show(Request $request, int $id): JsonResponse
    {
        $centerId = $request->user()->sortingCenter->id;
        $rider = Rider::where('sorting_center_id', $centerId)->findOrFail($id);
        $eligible = Rider::whereKey($id)->operationallyEligible()->exists();
        return response()->json(['data' => [
            'rider_id' => $rider->id,
            'areas' => Area::where('sorting_center_id', $centerId)->orderBy('name')->get(['id', 'name']),
            'area_ids' => $rider->areas()->where('areas.sorting_center_id', $centerId)->pluck('areas.id'),
            'can_manage' => $eligible,
            'reason' => $eligible ? null : 'Only approved, active Riders with eligible accounts can have delivery coverage changed.',
        ]]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $centerId = $request->user()->sortingCenter->id;
        $result = DB::transaction(function () use ($request, $id, $centerId): array {
            $owner = Rider::where('sorting_center_id', $centerId)->whereKey($id)->value('user_id');
            abort_unless($owner, 404, 'Rider not found for your Logistics organization.');
            // Match the profile workflow's User -> Rider lock order; assignment also locks this Rider.
            User::whereKey($owner)->lockForUpdate()->firstOrFail();
            $rider = Rider::where('sorting_center_id', $centerId)->where('user_id', $owner)->whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless(Rider::whereKey($id)->operationallyEligible()->exists(), 422,
                'Only approved, active Riders with eligible accounts can have delivery coverage changed.');
            $input = $request->validate([
                'area_ids' => ['present', 'array', 'max:500'],
                'area_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            ]);
            $ids = array_map('intval', $input['area_ids']);
            if (Area::where('sorting_center_id', $centerId)->whereIn('id', $ids)->count() !== count($ids)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['area_ids' => 'Choose only existing service areas belonging to your Logistics organization.']);
            }
            // Future eligibility changes; existing parcel assignments/history are preserved.
            $rider->areas()->sync($ids);
            return ['rider_id' => $rider->id, 'area_ids' => $rider->areas()->pluck('areas.id')];
        });
        return response()->json(['message' => 'Delivery areas saved. Existing deliveries remain assigned.', 'data' => $result]);
    }
}
