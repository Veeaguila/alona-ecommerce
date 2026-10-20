<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\DeliveryZone;
use App\Models\SortingCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDeliveryZoneController extends Controller
{
    /**
     * Display shipping rates and hub zone configurator.
     */
    public function index(Request $request): Response
    {
        // Seed initial default zones if empty
        if (DeliveryZone::count() === 0) {
            $defaultHub = SortingCenter::first();
            $hubId = $defaultHub?->id;

            DeliveryZone::create([
                'name' => 'Tagbilaran City & Metro Core',
                'province' => 'Bohol',
                'base_shipping_rate' => 45.00,
                'per_kg_rate' => 10.00,
                'estimated_days' => '1-2 Business Days',
                'is_active' => true,
                'sorting_center_id' => $hubId,
            ]);

            DeliveryZone::create([
                'name' => 'Panglao & Dauis Tourism District',
                'province' => 'Bohol',
                'base_shipping_rate' => 55.00,
                'per_kg_rate' => 12.00,
                'estimated_days' => '1-2 Business Days',
                'is_active' => true,
                'sorting_center_id' => $hubId,
            ]);

            DeliveryZone::create([
                'name' => 'Outer Municipalities (North & East Bohol)',
                'province' => 'Bohol',
                'base_shipping_rate' => 75.00,
                'per_kg_rate' => 15.00,
                'estimated_days' => '2-3 Business Days',
                'is_active' => true,
                'sorting_center_id' => $hubId,
            ]);

            DeliveryZone::create([
                'name' => 'Inter-Island & Central Visayas',
                'province' => 'Cebu / Leyte',
                'base_shipping_rate' => 120.00,
                'per_kg_rate' => 25.00,
                'estimated_days' => '3-5 Business Days',
                'is_active' => true,
                'sorting_center_id' => null,
            ]);
        }

        $zones = DeliveryZone::query()
            ->with(['sortingCenter:id,business_name'])
            ->orderBy('province')
            ->orderBy('name')
            ->get();

        $sortingCenters = SortingCenter::query()
            ->get(['id', 'business_name', 'approval_status']);

        return Inertia::render('Admin/DeliveryZones', [
            'title' => 'Shipping Rates & Hub Zones',
            'eyebrow' => 'Logistics Grid',
            'description' => 'Configure base shipping rates per zone, toggle active courier coverage boundaries, and route shipments to local sorting centers (Tax-Free Platform).',
            'active' => 'shipping_rates',
            'zones' => $zones,
            'sortingCenters' => $sortingCenters,
        ]);
    }

    /**
     * Store a new delivery zone.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'base_shipping_rate' => ['required', 'numeric', 'min:0'],
            'per_kg_rate' => ['nullable', 'numeric', 'min:0'],
            'estimated_days' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'sorting_center_id' => ['nullable', 'exists:sorting_centers,id'],
        ]);

        $zone = DeliveryZone::create([
            'name' => trim($validated['name']),
            'province' => trim($validated['province']),
            'base_shipping_rate' => $validated['base_shipping_rate'],
            'per_kg_rate' => $validated['per_kg_rate'] ?? 0,
            'estimated_days' => $validated['estimated_days'] ?? '1-3 Business Days',
            'is_active' => $request->boolean('is_active', true),
            'sorting_center_id' => $validated['sorting_center_id'] ?? null,
        ]);

        AdminAuditLog::record(
            $request->user(),
            'shipping_rate_created',
            "Created delivery zone '{$zone->name}' (Base rate: ₱{$zone->base_shipping_rate})",
            $zone,
            ['base_rate' => $zone->base_shipping_rate, 'province' => $zone->province]
        );

        return back()->with('status', "Delivery zone '{$zone->name}' configured successfully.");
    }

    /**
     * Update an existing delivery zone.
     */
    public function update(Request $request, DeliveryZone $deliveryZone): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'base_shipping_rate' => ['required', 'numeric', 'min:0'],
            'per_kg_rate' => ['nullable', 'numeric', 'min:0'],
            'estimated_days' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'sorting_center_id' => ['nullable', 'exists:sorting_centers,id'],
        ]);

        $oldData = $deliveryZone->only(['base_shipping_rate', 'is_active', 'sorting_center_id']);

        $deliveryZone->update([
            'name' => trim($validated['name']),
            'province' => trim($validated['province']),
            'base_shipping_rate' => $validated['base_shipping_rate'],
            'per_kg_rate' => $validated['per_kg_rate'] ?? $deliveryZone->per_kg_rate,
            'estimated_days' => $validated['estimated_days'] ?? $deliveryZone->estimated_days,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $deliveryZone->is_active,
            'sorting_center_id' => $validated['sorting_center_id'] ?? null,
        ]);

        AdminAuditLog::record(
            $request->user(),
            'shipping_rate_updated',
            "Updated shipping rate and coverage for '{$deliveryZone->name}' (Base: ₱{$deliveryZone->base_shipping_rate})",
            $deliveryZone,
            ['old' => $oldData, 'new' => $deliveryZone->only(['base_shipping_rate', 'is_active', 'sorting_center_id'])]
        );

        return back()->with('status', "Delivery zone '{$deliveryZone->name}' updated successfully.");
    }

    /**
     * Delete a delivery zone.
     */
    public function destroy(Request $request, DeliveryZone $deliveryZone): RedirectResponse
    {
        $zoneName = $deliveryZone->name;
        $deliveryZone->delete();

        AdminAuditLog::record(
            $request->user(),
            'shipping_rate_deleted',
            "Deleted delivery zone '{$zoneName}'",
            null,
            ['name' => $zoneName]
        );

        return back()->with('status', "Delivery zone '{$zoneName}' deleted successfully.");
    }
}

