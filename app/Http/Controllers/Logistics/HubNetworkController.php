<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\{LogisticsHub, LogisticsRoute, OrderItem, Parcel, ParcelHubJourney, Rider};
use App\Services\{DeliveryCoverageService, HubNetwork, HubOperations, HubTransport, LogisticsAudit, RiderAllocation};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HubNetworkController extends Controller
{
    private function center(Request $request): int
    {
        app(HubNetwork::class)->requireStorage();
        return (int) $request->user()->sortingCenter->id;
    }

    public function index(Request $request): JsonResponse
    {
        $center = $this->center($request);
        $counts = app(HubOperations::class)->counts($center);
        $hubs = LogisticsHub::where('sorting_center_id', $center)->orderBy('name')->get();
        $hubs->each(fn ($hub) => $hub->setAttribute('operation_counts', array_replace(array_fill_keys(array_slice(HubOperations::STAGES, 1), 0), $counts[$hub->id] ?? [])));
        return response()->json(['data' => [
            'company' => $request->user()->sortingCenter->only(['id', 'business_name']),
            'hubs' => $hubs,
            'routes' => LogisticsRoute::where('sorting_center_id', $center)->with(['originHub', 'destinationHub'])->orderBy('id')->get(),
            'riders' => Rider::where('sorting_center_id', $center)->orderBy('name')->get(['id', 'name', 'logistics_hub_id', 'approval_status', 'is_active']),
            'booking_routes_required' => app(HubNetwork::class)->enforced(),
        ]]);
    }

    public function saveHub(Request $request, ?int $id = null): JsonResponse
    {
        $center = $this->center($request);
        $hub = $id ? LogisticsHub::where('sorting_center_id', $center)->findOrFail($id) : new LogisticsHub(['sorting_center_id' => $center]);
        $input = $request->validate([
            'code' => ['required', 'string', 'max:24', 'regex:/^[A-Z0-9][A-Z0-9-]*$/', Rule::unique('logistics_hubs')->where('sorting_center_id', $center)->ignore($hub->id)],
            'name' => ['required', 'string', 'max:100'], 'address' => ['required', 'string', 'max:500'],
            'city_code' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'pickup_city_codes' => ['present', 'array', 'max:2000'],
            'pickup_city_codes.*' => ['required', 'string', 'distinct', 'regex:/^[0-9]{10}$/'],
            'delivery_city_codes' => ['present', 'array', 'max:2000'],
            'delivery_city_codes.*' => ['required', 'string', 'distinct', 'regex:/^[0-9]{10}$/'],
            'is_active' => ['required', 'boolean'],
        ]);
        app(DeliveryCoverageService::class)->cities(array_unique([$input['city_code'], ...$input['pickup_city_codes'], ...$input['delivery_city_codes']]));
        DB::transaction(function () use ($hub, $input, $request, $center): void {
            if ($hub->exists) {
                $hub->setRawAttributes($hub->newQuery()->where('sorting_center_id', $center)->whereKey($hub->id)->lockForUpdate()->firstOrFail()->getAttributes(), true);
                $busy = ParcelHubJourney::where(fn ($query) => $query->where('origin_hub_id', $hub->id)->orWhere('destination_hub_id', $hub->id))
                    ->whereHas('parcel', fn ($query) => $query->whereNotIn('status', ['DELIVERED', 'FAILED_RETURNED']))->exists();
                $busy = $busy || OrderItem::whereNotIn('status', ['delivered', 'completed', 'cancelled'])
                    ->where(fn ($query) => $query->where('hub_route_plan->origin_hub_id', $hub->id)->orWhere('hub_route_plan->destination_hub_id', $hub->id))->exists();
                abort_if($busy && ($hub->city_code !== $input['city_code'] || $hub->address !== $input['address']), 422,
                    'A hub with active shipments cannot be relocated. Finish its shipments or create a new hub.');
            }
            $hub->fill($input)->save();
            app(DeliveryCoverageService::class)->addCities($request->user()->sortingCenter,
                array_values(array_unique([...$input['pickup_city_codes'], ...$input['delivery_city_codes']])));
            app(LogisticsAudit::class)->record($request->user(), 'hub_saved', data: ['hub_id' => $hub->id, 'is_active' => $hub->is_active], centerId: $center);
        });
        return response()->json(['message' => 'Hub saved.', 'data' => $hub->fresh()], $id ? 200 : 201);
    }

    public function saveRoute(Request $request, ?int $id = null): JsonResponse
    {
        $center = $this->center($request);
        $route = $id ? LogisticsRoute::where('sorting_center_id', $center)->findOrFail($id) : new LogisticsRoute(['sorting_center_id' => $center]);
        $input = $request->validate(['origin_hub_id' => ['required', 'integer'],
            'destination_hub_id' => ['required', 'integer', 'different:origin_hub_id'],
            'transport_mode' => ['required', Rule::in(['road', 'sea', 'air', 'mixed'])], 'is_active' => ['required', 'boolean']]);
        DB::transaction(function () use ($route, $input, $center, $request): void {
            $hubs = LogisticsHub::where('sorting_center_id', $center)->whereIn('id', [$input['origin_hub_id'], $input['destination_hub_id']])->orderBy('id')->lockForUpdate()->get();
            abort_unless($hubs->count() === 2, 422, 'Both hubs must belong to your company. Cross-company transfers require a separate partnership workflow.');
            abort_if($input['is_active'] && $hubs->contains(fn ($hub) => !$hub->is_active), 422, 'Activate both hubs before enabling this route.');
            abort_if(LogisticsRoute::where('origin_hub_id', $input['origin_hub_id'])->where('destination_hub_id', $input['destination_hub_id'])
                ->when($route->exists, fn ($query) => $query->whereKeyNot($route->id))->exists(), 422, 'This directed route already exists. Edit it instead.');
            $route->fill($input)->save();
            app(LogisticsAudit::class)->record($request->user(), 'hub_route_saved', data: ['route_id' => $route->id, 'is_active' => $route->is_active], centerId: $center);
        });
        return response()->json(['message' => 'Route saved. The reverse direction needs its own route.', 'data' => $route->fresh()], $id ? 200 : 201);
    }

    public function assignRider(Request $request, int $id): JsonResponse
    {
        $center = $this->center($request);
        $input = $request->validate(['hub_id' => ['required', 'integer']]);
        $result = app(RiderAllocation::class)->run([$id], fn () => DB::transaction(function () use ($id, $center, $input, $request) {
            $rider = Rider::where('sorting_center_id', $center)->whereKey($id)->lockForUpdate()->firstOrFail();
            $hub = LogisticsHub::where('sorting_center_id', $center)->where('is_active', true)->findOrFail($input['hub_id']);
            $hasWork = Parcel::where(fn ($query) => $query->where('pickup_rider_id', $id)->orWhere('assigned_rider_id', $id))
                ->whereNotIn('status', ['DELIVERED', 'FAILED_RETURNED'])->exists();
            abort_if($hasWork && (int) $rider->logistics_hub_id !== (int) $hub->id, 422, 'Finish or explicitly hand over existing jobs before changing this Rider hub.');
            $rider->forceFill(['logistics_hub_id' => $hub->id])->save();
            app(LogisticsAudit::class)->record($request->user(), 'rider_hub_assigned', data: ['hub_id' => $hub->id], riderId: $rider->id, centerId: $center);
            return $rider;
        }));
        return response()->json(['message' => 'Rider hub assigned. Configure the Rider delivery areas separately.', 'data' => $result]);
    }

    public function journeys(Request $request): JsonResponse
    {
        $center = $this->center($request);
        $input = $request->validate(['search' => ['nullable', 'string', 'max:50'], 'page' => ['nullable', 'integer', 'min:1'],
            'hub_id' => ['nullable', 'integer', 'min:1'], 'stage' => ['sometimes', Rule::in(HubOperations::STAGES)]]);
        $hubId = isset($input['hub_id']) ? (int) $input['hub_id'] : null;
        if ($hubId) LogisticsHub::where('sorting_center_id', $center)->findOrFail($hubId);
        $stage = $input['stage'] ?? 'all';
        if (!$hubId && $stage !== 'all') throw \Illuminate\Validation\ValidationException::withMessages(['hub_id' => 'Choose your operating hub before filtering its work queue.']);
        $query = app(HubOperations::class)->queue($center, $hubId, $stage)->with(['parcel:id,order_id,status,assigned_rider_id', 'transfer']);
        if (!empty($input['search'])) $query->whereHas('parcel', fn ($query) => $query->where('order_id', $input['search']));
        return response()->json(['data' => $query->orderByDesc('id')->paginate(20)]);
    }

    public function dispatch(Request $request, int $id): JsonResponse
    {
        $center = $this->center($request);
        $input = $request->validate(['hub_id' => ['required', 'integer'], 'request_key' => ['required', 'uuid', Rule::unique('hub_transfers')],
            'transport_reference' => ['required', 'string', 'min:3', 'max:100'], 'physical_confirmation' => ['required', 'accepted']]);
        return response()->json(['message' => 'Shipment dispatched. Await destination receipt.', 'data' => app(HubTransport::class)->dispatch($request->user(), $center, $id, $input)]);
    }

    public function receive(Request $request, int $id): JsonResponse
    {
        $center = $this->center($request);
        $input = $request->validate(['hub_id' => ['required', 'integer'], 'physical_confirmation' => ['required', 'accepted']]);
        return response()->json(['message' => 'Parcel received at destination hub.', 'data' => app(HubTransport::class)->receive($request->user(), $center, $id, $input['hub_id'])]);
    }
}
