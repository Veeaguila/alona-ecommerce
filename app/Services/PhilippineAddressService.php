<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class PhilippineAddressService
{
    public const VERSION = '2026-Q3';

    public const GROUPS = [
        'metro-manila' => ['name' => 'Metro Manila', 'regions' => ['1300000000']],
        'mindanao' => ['name' => 'Mindanao', 'regions' => ['0900000000', '1000000000', '1100000000', '1200000000', '1600000000', '1900000000']],
        'north-luzon' => ['name' => 'North Luzon', 'regions' => ['0100000000', '0200000000', '0300000000', '1400000000']],
        'south-luzon' => ['name' => 'South Luzon', 'regions' => ['0400000000', '0500000000', '1700000000']],
        'visayas' => ['name' => 'Visayas', 'regions' => ['0600000000', '0700000000', '0800000000', '1800000000']],
    ];

    public function data(): array
    {
        return Cache::rememberForever('psgc:'.self::VERSION, fn () => json_decode(
            file_get_contents(resource_path('data/psgc.json')), true, 512, JSON_THROW_ON_ERROR
        ));
    }

    public function list(string $level, ?string $parent = null, ?string $province = null, ?string $group = null): array
    {
        if ($level === 'groups') {
            return collect(self::GROUPS)->map(fn ($row, $code) => ['code' => $code, 'name' => $row['name']])->values()->all();
        }
        $records = $this->data()[$level] ?? [];
        $key = match ($level) {
            'provinces', 'cities' => 'region_code',
            'barangays' => 'city_code',
            default => null,
        };
        $records = array_filter($records, fn ($row) => (! $key || ! $parent || $row[$key] === $parent)
            && ($level !== 'cities' || $province === null || ($row['province_code'] ?? '') === $province)
            && (! $group || in_array($row['region_code'] ?? '', self::GROUPS[$group]['regions'] ?? [], true)));
        usort($records, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return array_values($records);
    }

    public function validate(array $input): array
    {
        $data = $this->data();
        $find = fn ($level, $code) => collect($data[$level])->firstWhere('code', $code);
        $region = $find('regions', $input['region_code'] ?? '');
        $province = $find('provinces', $input['province_code'] ?? '');
        $city = $find('cities', $input['city_code'] ?? '');
        $barangay = $find('barangays', $input['barangay_code'] ?? '');
        $errors = [];
        $group = $input['address_group'] ?? null;
        if ($group && ! in_array($region['code'] ?? '', self::GROUPS[$group]['regions'] ?? [], true)) {
            $errors['address_group'] = 'Choose a location in the selected geographical group.';
        }
        if (! $region) {
            $errors['region_code'] = 'Choose a valid region.';
        }
        if (! $city || $city['region_code'] !== ($region['code'] ?? null)) {
            $errors['city_code'] = 'Choose a city or municipality in the selected region.';
        }
        $expectedProvince = $city['province_code'] ?? '';
        if (($input['province_code'] ?? '') !== $expectedProvince || ($expectedProvince && (! $province || $province['region_code'] !== ($region['code'] ?? null)))) {
            $errors['province_code'] = 'Choose the province belonging to this city, or leave it empty for an independent city.';
        }
        if (! $barangay || $barangay['city_code'] !== ($city['code'] ?? null)) {
            $errors['barangay_code'] = 'Choose a barangay in the selected city or municipality.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return array_merge(collect($input)->only(['region_code', 'province_code', 'city_code', 'barangay_code', 'street', 'house_number', 'subdivision', 'postal_code'])->all(), [
            'region' => $region['name'], 'province' => $province['name'] ?? null,
            'city' => $city['name'], 'barangay' => $barangay['name'], 'psgc_version' => self::VERSION,
            'address_group' => collect(self::GROUPS)->search(fn ($row) => in_array($region['code'], $row['regions'], true)),
        ]);
    }
}
