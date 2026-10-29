<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use App\Services\{LogisticsAudit, PhilippineAddressService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DeliveryRateController extends Controller
{
    private function provinces(): array
    {
        return collect(app(PhilippineAddressService::class)->data()['provinces'])->pluck('name')->push('Metro Manila')->unique()->sort()->values()->all();
    }

    public function index(Request $request)
    {
        return response()->json(['data' => DeliveryZone::where('sorting_center_id', $request->user()->sortingCenter->id)->orderBy('province')->orderBy('id')->get(), 'provinces' => $this->provinces()]);
    }

    public function save(Request $request, ?int $id = null)
    {
        $centerId = (int) $request->user()->sortingCenter->id;
        if ($id) DeliveryZone::where('sorting_center_id', $centerId)->findOrFail($id);
        $input = $request->validate([
            'name' => ['required', 'string', 'max:100'], 'province' => ['required', Rule::in($this->provinces())],
            'base_shipping_rate' => ['required', 'numeric', 'min:0.01', 'decimal:0,2', 'max:999999.99'],
            'per_kg_rate' => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:999999.99'],
            'estimated_days' => ['required', 'string', 'max:50'], 'is_active' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:10', 'max:300'],
        ]);
        app(LogisticsAudit::class)->requireStorage();
        $zone = DB::transaction(function () use ($request, $centerId, $id, $input) {
            $zone = $id ? DeliveryZone::where('sorting_center_id', $centerId)->lockForUpdate()->findOrFail($id) : new DeliveryZone;
            $before = $zone->exists ? $zone->getAttributes() : null;
            $values = collect($input)->except('reason')->all();
            $zone->fill($values); $zone->sorting_center_id = $centerId; $zone->save();
            app(LogisticsAudit::class)->record($request->user(), $id ? 'shipping_rate_updated' : 'shipping_rate_created', null,
                ['zone_id' => $zone->id, 'old' => $before, 'new' => $zone->fresh()->getAttributes(), 'reason' => $input['reason']], null, $centerId);
            return $zone->fresh();
        }, 5);
        return response()->json(['message' => 'Delivery rate saved. Existing order fees are unchanged.', 'data' => $zone], $id ? 200 : 201);
    }
}
