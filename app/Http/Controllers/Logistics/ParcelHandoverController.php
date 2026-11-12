<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\{Parcel, ParcelHandover};
use App\Services\SupervisedHandover;
use Illuminate\Http\Request;

class ParcelHandoverController extends Controller
{
    public function index(Request $request, int $id)
    {
        Parcel::where('sorting_center_id', $request->user()->sortingCenter->id)->findOrFail($id);
        app(SupervisedHandover::class)->requireStorage();
        return response()->json(['data' => ParcelHandover::where('sorting_center_id', $request->user()->sortingCenter->id)
            ->where(fn ($query) => $query->where('parcel_id', $id)->orWhereJsonContains('parcel_ids', $id))
            ->with(['fromRider:id,name', 'toRider:id,name'])->orderByDesc('id')->limit(50)->get()]);
    }
    public function store(Request $request, int $id)
    {
        $input = $request->validate(['request_key' => ['required', 'uuid'], 'rider_id' => ['required', 'integer', 'min:1'],
            'leg' => ['required', 'in:pickup,delivery'], 'reason' => ['required', 'string', 'min:10', 'max:2000']]);
        return response()->json(['message' => 'Both Riders must acknowledge physical handover before Logistics confirmation.',
            'data' => app(SupervisedHandover::class)->request($request->user(), (int) $request->user()->sortingCenter->id, $id, $input)], 201);
    }
    public function confirm(Request $request, int $id)
    {
        $handover = ParcelHandover::where('sorting_center_id', $request->user()->sortingCenter->id)->findOrFail($id);
        return response()->json(['message' => 'Whole-shipment physical handover confirmed.',
            'data' => app(SupervisedHandover::class)->confirm($request->user(), $handover)]);
    }
    public function cancel(Request $request, int $id)
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']])['reason'];
        $handover = ParcelHandover::where('sorting_center_id', $request->user()->sortingCenter->id)->findOrFail($id);
        return response()->json(['message' => 'Handover request cancelled. Parcel custody remains unchanged.',
            'data' => app(SupervisedHandover::class)->cancel($request->user(), $handover, $reason)]);
    }
    public function mine(Request $request)
    {
        abort_unless($request->user()->usertype === 'rider' && $request->user()->rider, 403);
        app(SupervisedHandover::class)->requireStorage();
        $riderId = $request->user()->rider->id;
        $handovers = ParcelHandover::where(fn ($query) => $query->where('from_rider_id', $riderId)->orWhere('to_rider_id', $riderId))
            ->with(['parcel:id,order_id,status', 'fromRider:id,name', 'toRider:id,name'])->orderByDesc('id')->limit(50)->get();
        return response()->json(['data' => $handovers, 'meta' => ['rider_id' => $riderId]])->header('Cache-Control', 'private, no-store');
    }
    public function acknowledge(Request $request, int $id)
    {
        $request->validate(['physical_handover' => ['required', 'accepted']]);
        return response()->json(['message' => 'Physical handover acknowledged. Logistics must still confirm the transfer.',
            'data' => app(SupervisedHandover::class)->acknowledge($request->user(), $id)]);
    }
}
