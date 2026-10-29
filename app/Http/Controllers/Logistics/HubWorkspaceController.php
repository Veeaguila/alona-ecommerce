<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\{LogisticsHub, LogisticsHubStaff, Parcel, User};
use App\Services\{HubAccess, HubNetwork, HubOperations, HubTransport, HubWorkspace, SellerShipmentFinance};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
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
                app(SellerShipmentFinance::class)->lockParcel($parcelId);
                $parcel = Parcel::where('sorting_center_id', $company)->whereKey($parcelId)->lockForUpdate()->firstOrFail();
                abort_unless($parcel->status === 'PICKED_UP', 409, 'The parcel has already moved. Look it up again.');
                app(HubNetwork::class)->receiveOrigin($parcel, (int) $input['hub_id'], $actor);
                $parcel->update(['status' => 'AT_SORTING_CENTER']);
            }
            return app(HubWorkspace::class)->lookup($actor, $reference, (int) $input['hub_id']);
        });
        return response()->json(['message' => 'Parcel movement recorded.', 'data' => $result]);
    }
}
