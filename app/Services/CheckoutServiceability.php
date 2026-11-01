<?php

namespace App\Services;

use App\Models\Area;
use Illuminate\Validation\ValidationException;

class CheckoutServiceability
{
    public function __construct(private PhilippineAddressService $addresses) {}

    public function forAddresses(array $addresses): array
    {
        $geography = $this->addresses->data();
        $cities = array_column($geography['cities'], null, 'code');
        $provinces = array_column($geography['provinces'], 'name', 'code');
        $regions = array_column($geography['regions'], 'name', 'code');
        $codes = Area::whereHas('sortingCenter', fn ($query) => $query->availableForEnrollment())->pluck('city_code')->all();
        $known = array_values(array_filter($codes, fn ($code) => isset($cities[$code])));
        // Unmapped/invalid legacy coverage cannot establish that a destination is excluded.
        $complete = count($known) === count($codes) && count($known) > 0;
        $result = [];
        foreach ($addresses as $id => $address) {
            $tokens = array_values(array_filter(array_map($this->normalize(...), explode(',', $address))));
            $matches = [];
            $namedProvinces = array_filter($provinces, fn ($name) => in_array($this->normalize($name), $tokens, true));
            $ncr = count(array_intersect($tokens, ['metro manila', 'ncr', 'national capital region', $this->normalize($regions['1300000000'] ?? '')])) > 0;
            foreach ($cities as $city) {
                $name = $this->normalize($city['name']);
                $base = DeliveryCoverageService::normalizeCityName($city['name']);
                $aliases = array_unique([$name, $base, str_contains($name, 'city') ? $base.' city' : $name]);
                if (!array_intersect($tokens, $aliases)) continue;
                if ($city['region_code'] === '1300000000') {
                    $context = $ncr && !$namedProvinces;
                } else {
                    $province = $provinces[$city['province_code'] ?? ''] ?? null;
                    $context = !$ncr && count($namedProvinces) <= 1 && ($province
                        ? in_array($this->normalize($province), $tokens, true)
                        : (!$namedProvinces && in_array($this->normalize($regions[$city['region_code']]), $tokens, true)));
                    // One ambiguous token cannot stand for both city and province.
                    if ($province && in_array($this->normalize($province), $aliases, true)
                        && count(array_keys($tokens, $this->normalize($province), true)) < 2) $context = false;
                }
                if ($context) $matches[$city['code']] = $city;
            }
            $city = count($matches) === 1 ? reset($matches) : null;
            $zone = app(ShippingRates::class)->zone($address);
            $supported = $city && (in_array($city['code'], $known, true) || $zone?->sorting_center_id);
            if (app(ShippingRates::class)->remoteExcluded($address)) $supported = false;
            $state = $supported ? 'supported' : ($city && $complete || app(ShippingRates::class)->remoteExcluded($address) ? 'unsupported' : 'unknown');
            $message = match ($state) {
                'supported' => 'Delivery coverage is available for this destination.',
                'unsupported' => 'This destination is not serviceable under current delivery coverage. Please choose another address.',
                default => 'Delivery coverage could not be verified. Please review the full address before ordering.',
            };
            $result[$id] = ['state' => $state, 'city_code' => $city['code'] ?? null, 'message' => $message];
        }
        return $result;
    }

    public function assertServiceable(string $address): void
    {
        $result = $this->forAddresses([$address])[0];
        if ($result['state'] === 'unsupported') {
            throw ValidationException::withMessages(['shipping_address' => $result['message']]);
        }
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value)));
    }
}
