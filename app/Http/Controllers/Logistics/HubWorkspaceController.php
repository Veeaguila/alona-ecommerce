<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\{LogisticsHub, LogisticsHubStaff, Parcel, User};
use App\Services\{HubAccess, HubNetwork, HubOperations, HubTransport, HubWorkspace, SellerShipmentFinance};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HubWorkspaceController extends Controller
{
    public function page(Request $request)
    {
        if ($request->header('X-Inertia')) return \Inertia\Inertia::location($request->fullUrl());
        $context = app(HubAccess::class)->context($request->user());
        $token = $request->session()->get('logistics.web_token');
        $lifetime = app(\App\Services\LogisticsSessionLifetime::class);
        $existing = $lifetime->ownedWebToken($request->user(), $token);
        if (!$existing || !$lifetime->validToken($existing)) {
            $existing?->delete();
            $token = $request->user()->createToken('logistics-web', ['*'], now()->addHours(2))->plainTextToken;
            $request->session()->put('logistics.web_token', $token);
        }
        return view('sorting-center.hub-workspace', ['logisticsSession' => ['token' => $token,
            'user' => $request->user()->only(['id', 'name', 'email']), 'sorting_center' => $context['center']->only(['id', 'business_name']),
            'operator' => app(HubAccess::class)->identity($request->user())]]);
    }

    private function context(Request $request, bool $requireHub = false): array
    {
        app(HubNetwork::class)->requireStorage();
        $input = $request->validate(['hub_id' => ['nullable', 'integer', 'min:1']]);
        return app(HubAccess::class)->context($request->user(), isset($input['hub_id']) ? (int) $input['hub_id'] : null, $requireHub);
    }

    public function index(Request $request): JsonResponse
    {
        $context = $this->context($request);
        $hub = $context['hub'];
        $hubs = LogisticsHub::where('sorting_center_id', $context['center']->id)->where('is_active', true)
            ->when($context['staff'], fn ($q) => $q->whereKey($hub->id))->orderBy('name')->get(['id', 'name', 'code', 'address']);
        $counts = $hub ? (app(HubOperations::class)->counts($context['center']->id)[$hub->id] ?? []) : [];
        return response()->json(['data' => ['operator' => app(HubAccess::class)->identity($request->user()),
            'company' => $context['center']->only(['id', 'business_name']), 'hubs' => $hubs, 'operating_hub' => $hub?->only(['id', 'name', 'address']),
            'counts' => array_replace(array_fill_keys(array_slice(HubOperations::STAGES, 1), 0), $counts)]]);
    }

    private function reference(Request $request): string
    {
        $input = $request->validate(['reference' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/']]);
        return preg_match('/^ITEM-[1-9][0-9]*$/i', $input['reference']) ? strtoupper($input['reference']) : $input['reference'];
    }

    public function lookup(Request $request): JsonResponse
    {
        $context = $this->context($request, true);
        return response()->json(['data' => app(HubWorkspace::class)->lookup($request->user(), $this->reference($request), (int) $context['hub']->id)]);
    }

    public function queue(Request $request): JsonResponse
    {
        $context = $this->context($request, true);

        // Auto-ensure journeys for active parcels in this center that don't have one yet
        $unlinkedParcels = Parcel::where('sorting_center_id', $context['center']->id)
            ->whereNotIn('status', ['DELIVERED', 'FAILED_RETURNED'])
            ->whereDoesntHave('hubJourney')
            ->get();
        foreach ($unlinkedParcels as $p) {
            app(HubNetwork::class)->ensureJourney($p);
        }

        $input = $request->validate(['stage' => ['sometimes', Rule::in(HubOperations::STAGES)], 'page' => ['sometimes', 'integer', 'min:1']]);
        $query = app(HubOperations::class)->queue($context['center']->id, $context['hub']->id, $input['stage'] ?? 'all')
            ->whereHas('parcel', fn ($q) => $q->whereNotIn('status', ['DELIVERED', 'FAILED_RETURNED']))
            ->with(['parcel:id,order_id,status', 'originHub:id,name', 'destinationHub:id,name']);
        return response()->json(['data' => $query->orderByDesc('id')->paginate(20)]);
    }

    public function act(Request $request): JsonResponse
    {
        $input = $request->validate(['hub_id' => ['required', 'integer', 'min:1'],
            'action' => ['required', Rule::in(['receive_origin', 'dispatch', 'receive_destination'])],
            'physical_confirmation' => ['required', 'accepted'], 'request_key' => ['required_if:action,dispatch', 'nullable', 'uuid'],
            'transport_reference' => ['required_if:action,dispatch', 'nullable', 'string', 'min:3', 'max:100']]);
        $reference = $this->reference($request);
        $actorId = $request->user()->id;
        $result = DB::transaction(function () use ($input, $reference, $actorId) {
            $actor = User::whereKey($actorId)->lockForUpdate()->firstOrFail();
            if (app(HubAccess::class)->membership($actor)) LogisticsHubStaff::where('user_id', $actorId)->lockForUpdate()->firstOrFail();
            $context = app(HubAccess::class)->context($actor, (int) $input['hub_id'], true);
            $view = app(HubWorkspace::class)->lookup($actor, $reference, (int) $context['hub']->id);
            abort_unless($view['action'] === $input['action'], 409, 'The parcel or its next action changed. Look it up again before confirming.');
            $parcelId = $view['parcel']['id'];
            $company = (int) $context['center']->id;
            if ($input['action'] === 'dispatch') {
                app(HubTransport::class)->dispatch($actor, $company, $parcelId, $input);
            } elseif ($input['action'] === 'receive_destination') {
                app(HubTransport::class)->receive($actor, $company, $parcelId, (int) $input['hub_id']);
            } else {
                app(\App\Services\RiderJobPay::class)->groupedMembers($parcelId, $company);
                app(SellerShipmentFinance::class)->lockParcel($parcelId);
                $parcel = Parcel::where('sorting_center_id', $company)->whereKey($parcelId)->lockForUpdate()->firstOrFail();
                abort_unless($parcel->status === 'PICKED_UP', 409, 'The parcel has already moved. Look it up again.');
                app(HubNetwork::class)->receiveOrigin($parcel, (int) $input['hub_id'], $actor);
                $parcel->update(['status' => 'AT_SORTING_CENTER']);
                app(\App\Services\RiderJobPay::class)->complete($parcel, 'pickup', $actor);
            }
            return app(HubWorkspace::class)->lookup($actor, $reference, (int) $input['hub_id']);
        });
        return response()->json(['message' => 'Parcel movement recorded.', 'data' => $result]);
    }

    public function batchDispatch(Request $request): JsonResponse
    {
        $input = $request->validate([
            'hub_id' => ['required', 'integer', 'min:1'],
            'destination_hub_id' => ['required', 'integer', 'min:1'],
            'transport_reference' => ['required', 'string', 'min:3', 'max:100'],
            'parcel_ids' => ['nullable', 'array'],
            'parcel_ids.*' => ['integer', 'min:1'],
        ]);

        $actor = $request->user();
        $context = app(HubAccess::class)->context($actor, (int) $input['hub_id'], true);
        $companyId = (int) $context['center']->id;
        $originHubId = (int) $context['hub']->id;
        $destHubId = (int) $input['destination_hub_id'];
        $transportRef = trim($input['transport_reference']);

        abort_if($originHubId === $destHubId, 422, 'Destination hub must be different from the origin hub.');

        $query = Parcel::query()
            ->where('sorting_center_id', $companyId)
            ->where('status', 'AT_SORTING_CENTER')
            ->whereNull('assigned_rider_id')
            ->whereHas('hubJourney', function ($q) use ($originHubId, $destHubId) {
                $q->where('origin_hub_id', $originHubId)
                  ->where('destination_hub_id', $destHubId)
                  ->whereNotNull('origin_received_at')
                  ->whereNull('destination_received_at');
            });

        if (!empty($input['parcel_ids'])) {
            $query->whereIn('id', $input['parcel_ids']);
        }

        $parcels = $query->with('hubJourney')->get();
        abort_if($parcels->isEmpty(), 422, 'No parcels are ready for dispatch to the selected destination hub.');

        $dispatched = DB::transaction(function () use ($actor, $companyId, $originHubId, $destHubId, $transportRef, $parcels) {
            $results = [];
            foreach ($parcels as $parcel) {
                if ($parcel->fresh()->status === 'IN_HUB_TRANSIT') {
                    continue;
                }
                app(HubTransport::class)->dispatch($actor, $companyId, $parcel->id, [
                    'hub_id' => $originHubId,
                    'transport_reference' => $transportRef,
                    'request_key' => (string) Str::uuid(),
                ]);
                $results[] = $parcel->order_id;
            }
            return $results;
        });

        return response()->json([
            'message' => count($dispatched) . " parcel(s) dispatched to destination hub via {$transportRef}.",
            'data' => [
                'dispatched_count' => count($dispatched),
                'parcels' => $dispatched,
                'transport_reference' => $transportRef,
            ],
        ]);
    }

    public function batchReceive(Request $request): JsonResponse
    {
        $input = $request->validate([
            'hub_id' => ['required', 'integer', 'min:1'],
            'transport_reference' => ['nullable', 'string', 'max:100'],
            'parcel_ids' => ['nullable', 'array'],
            'parcel_ids.*' => ['integer', 'min:1'],
        ]);

        $actor = $request->user();
        $context = app(HubAccess::class)->context($actor, (int) $input['hub_id'], true);
        $companyId = (int) $context['center']->id;
        $destHubId = (int) $context['hub']->id;
        $transportRef = trim((string) ($input['transport_reference'] ?? ''));

        $query = Parcel::query()
            ->where('sorting_center_id', $companyId)
            ->where('status', 'IN_HUB_TRANSIT')
            ->whereHas('hubJourney', function ($q) use ($destHubId, $transportRef) {
                $q->where('destination_hub_id', $destHubId)
                  ->whereNull('destination_received_at')
                  ->when($transportRef !== '', function ($tq) use ($transportRef) {
                      $tq->whereHas('transfer', fn ($x) => $x->where('transport_reference', $transportRef));
                  });
            });

        if (!empty($input['parcel_ids'])) {
            $query->whereIn('id', $input['parcel_ids']);
        }

        $parcels = $query->with('hubJourney.transfer')->get();
        abort_if($parcels->isEmpty(), 422, 'No transit parcels are awaiting receipt at this hub' . ($transportRef !== '' ? " for manifest '{$transportRef}'." : '.'));

        $received = DB::transaction(function () use ($actor, $companyId, $destHubId, $parcels) {
            $results = [];
            foreach ($parcels as $parcel) {
                if ($parcel->fresh()->status === 'AT_SORTING_CENTER') {
                    continue;
                }
                app(HubTransport::class)->receive($actor, $companyId, $parcel->id, $destHubId);
                $results[] = $parcel->order_id;
            }
            return $results;
        });

        return response()->json([
            'message' => count($received) . " parcel(s) received at " . $context['hub']->name . ".",
            'data' => [
                'received_count' => count($received),
                'parcels' => $received,
            ],
        ]);
    }
}
