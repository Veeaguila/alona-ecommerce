<?php

namespace App\Services;

use App\Models\DeliveryZone;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ShippingRates
{
    public function zone(string $address, ?int $centerId = null): ?DeliveryZone
    {
        if (!Schema::hasTable('delivery_zones')) return null;
        $parts = array_map($this->normalize(...), explode(',', $address));
        $zones = DeliveryZone::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('sorting_center_id')->orWhereHas('sortingCenter', fn ($q) => $q->availableForEnrollment()))
            ->when($centerId !== null, fn ($q) => $q->where(fn ($q) => $q->where('sorting_center_id', $centerId)->orWhereNull('sorting_center_id')))
            ->orderBy('id')->get();
        // Exact address components prevent a street name from granting province coverage.
        $match = fn ($choices) => $choices->first(fn ($z) => in_array($this->normalize($z->name), $parts, true))
            ?? $choices->first(function ($z) use ($parts) {
                $provinces = array_filter(array_map($this->normalize(...), preg_split('/\s*\/\s*/', $z->province ?? '')));
                return count(array_intersect($parts, $provinces)) > 0;
            });
        // A booked company's tariff takes priority; only administrator rates may be a fallback.
        return $centerId !== null
            ? ($match($zones->where('sorting_center_id', $centerId)) ?? $match($zones->whereNull('sorting_center_id')))
            : $match($zones);
    }

    public function card(string $method, string $address, bool $explicitWeight, ?int $centerId = null): array
    {
        $zone = $this->zone($address, $centerId);
        $lower = mb_strtolower($address);
        if ($zone) {
            $base = (float) $zone->base_shipping_rate; $perKg = (float) $zone->per_kg_rate;
        } elseif (str_contains($lower, 'visayas') || str_contains($lower, 'cebu') || str_contains($lower, 'leyte')) {
            $base = 120.0; $perKg = 25.0;
        } elseif (str_contains($lower, 'ncr') || str_contains($lower, 'metro manila') || str_contains($lower, 'manila') || str_contains($lower, 'quezon city')) {
            $base = 50.0; $perKg = 20.0;
        } else {
            $base = $explicitWeight ? 60.0 : (float) config('checkout.shipping_methods.'.$method.'.fee_per_seller', 50);
            $perKg = 20.0;
        }
        if ($method === 'express') $base = max($base, 120.0);
        return ['base' => $method === 'pickup' ? 0.0 : $base, 'per_kg' => $method === 'pickup' ? 0.0 : $perKg, 'zone_id' => $zone?->id];
    }

    public function cards(array $addresses, $items = null): array
    {
        $result = [];
        foreach ($addresses as $id => $address) {
            $plans = [];
            if ($items !== null) {
                try {
                    $plans = app(HubNetwork::class)->checkoutPlans($items, $address, 'standard');
                } catch (ValidationException) {
                    // The separate coverage result blocks this address; pickup remains available.
                }
            }
            $companies = array_values(array_unique(array_column($plans, 'sorting_center_id')));
            $centerId = count($companies) === 1 ? (int) $companies[0] : null;
            foreach (array_keys(config('checkout.shipping_methods', [])) as $method) {
                $result[$id][$method] = ['weighted' => $this->card($method, $address, true, $centerId), 'unweighted' => $this->card($method, $address, false, $centerId)];
                foreach ($plans as $sellerId => $plan) {
                    $result[$id][$method]['sellers'][$sellerId] = [
                        'weighted' => $this->card($method, $address, true, (int) $plan['sorting_center_id']),
                        'unweighted' => $this->card($method, $address, false, (int) $plan['sorting_center_id']),
                    ];
                }
            }
        }
        return $result;
    }

    public function calculate(string $method, $items, float $subtotal, ?string $address = null, ?array $hubPlans = null): float
    {
        if ($method === 'pickup') return 0.0;
        $threshold = config('checkout.free_shipping_threshold');
        if ($method !== 'express' && $threshold !== null && $subtotal >= (float) $threshold) return 0.0;
        $groups = collect($items)->groupBy(fn ($item) => (int) ($item->product?->seller_id ?? 0));
        if ($groups->isEmpty()) return 50.0;
        $hubPlans ??= $address !== null ? app(HubNetwork::class)->checkoutPlans($items, $address, $method) : [];
        $total = 0.0;
        foreach ($groups as $sellerId => $group) {
            $weight = $group->sum(fn ($item) => max(0, (float) ($item->product?->weight ?? 0)) * (int) ($item->quantity ?? 1));
            $card = $this->card($method, $address ?? '', $weight > 0, $hubPlans[$sellerId]['sorting_center_id'] ?? null);
            $total += $card['base'] + max(0, (int) ceil($weight) - 1) * $card['per_kg'];
        }
        return round($total, 2);
    }

    public function remoteExcluded(string $address): bool
    {
        $parts = array_map($this->normalize(...), explode(',', $address));
        return count(array_intersect($parts, ['batanes', 'kalayaan', 'pag-asa island'])) > 0
            && !$this->zone($address)?->sorting_center_id;
    }

    private function normalize(string $value): string { return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value))); }
}
