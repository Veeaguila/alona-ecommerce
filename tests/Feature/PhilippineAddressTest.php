<?php

namespace Tests\Feature;

use App\Services\PhilippineAddressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhilippineAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_groups_cover_every_official_region_province_and_city_once(): void
    {
        $service = app(PhilippineAddressService::class);
        $this->getJson('/api/addresses/groups')->assertOk()->assertJsonPath('data.*.name', ['Metro Manila', 'Mindanao', 'North Luzon', 'South Luzon', 'Visayas']);
        $regions = array_merge(...array_column($service::GROUPS, 'regions'));
        $this->assertCount(18, array_unique($regions));
        $this->assertEqualsCanonicalizing(array_column($service->data()['regions'], 'code'), $regions);
        $provinces = $cities = [];
        foreach (array_keys($service::GROUPS) as $group) {
            $provinces = array_merge($provinces, array_column($service->list('provinces', group: $group), 'code'));
            $cities = array_merge($cities, array_column($service->list('cities', group: $group), 'code'));
        }
        $this->assertCount(82, array_unique($provinces));
        $this->assertCount(1642, array_unique($cities));
        $this->getJson('/api/addresses/provinces?group=metro-manila')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/addresses/cities?group=metro-manila&province=')->assertOk()->assertJsonCount(17, 'data');
        $this->getJson('/api/addresses/provinces?group=invalid')->assertUnprocessable();
        $province = $service->list('provinces', group: 'south-luzon')[0];
        $response = $this->getJson('/api/addresses/cities?group=south-luzon&province='.$province['code'])->assertOk();
        $this->assertNotEmpty($response->json('data'));
        foreach ($response->json('data') as $city) {
            $this->assertSame($province['code'], $city['province_code']);
        }
        $this->getJson('/api/addresses/cities?group=mindanao&province='.$province['code'])->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_address_validation_rejects_cross_group_and_cross_barangay_combinations(): void
    {
        $service = app(PhilippineAddressService::class);
        $city = collect($service->data()['cities'])->firstWhere('code', '1381300000');
        $barangay = collect($service->data()['barangays'])->firstWhere('city_code', $city['code']);
        $input = ['address_group' => 'metro-manila', 'region_code' => $city['region_code'], 'province_code' => '', 'city_code' => $city['code'], 'barangay_code' => $barangay['code']];
        $this->assertSame($city['name'], $service->validate($input)['city']);
        foreach ([['address_group' => 'mindanao'], ['province_code' => '0403400000'], ['barangay_code' => '1001322005']] as $invalid) {
            try {
                $service->validate(array_merge($input, $invalid));
                $this->fail('Invalid ancestry was accepted.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey(array_key_first($invalid), $e->errors());
            }
        }
    }

    public function test_snapshot_has_complete_counts_and_valid_parents(): void
    {
        $data = app(PhilippineAddressService::class)->data();
        $this->assertCount(18, $data['regions']);
        $this->assertCount(82, $data['provinces']);
        $this->assertCount(1642, $data['cities']);
        $this->assertCount(42010, $data['barangays']);
        $this->assertSame('Lambagan', collect($data['barangays'])->firstWhere('code', '1001322005')['name']);
        $regions = array_column($data['regions'], null, 'code');
        $provinces = array_column($data['provinces'], null, 'code');
        $cities = array_column($data['cities'], null, 'code');
        foreach ($data['cities'] as $city) {
            $this->assertArrayHasKey($city['region_code'], $regions);
            if ($city['province_code']) {
                $this->assertArrayHasKey($city['province_code'], $provinces);
            }
        }
        foreach ($data['barangays'] as $barangay) {
            $this->assertArrayHasKey($barangay['city_code'], $cities);
        }
    }

    public function test_lookup_supports_ncr_nir_independent_cities_and_manila(): void
    {
        $this->getJson('/api/addresses/regions')->assertOk()->assertJsonCount(18, 'data');
        $this->getJson('/api/addresses/provinces?parent=1300000000')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/addresses/provinces?parent=1800000000')->assertOk()->assertJsonCount(3, 'data');
        $response = $this->getJson('/api/addresses/cities?parent=1400000000&province=')->assertOk();
        $this->assertTrue(collect($response->json('data'))->contains(fn ($city) => str_contains($city['name'], 'Baguio')));
        $this->getJson('/api/addresses/barangays?parent=1380600000')->assertOk()->assertJsonCount(897, 'data');
        $this->getJson('/api/addresses/cities?parent=invalid')->assertUnprocessable();
        $data = app(PhilippineAddressService::class)->data();
        $this->assertNull(collect($data['cities'])->firstWhere('code', '0990100000'));
        $this->assertSame('', collect($data['cities'])->firstWhere('code', '0990101000')['province_code']);
        $this->assertSame('', collect($data['cities'])->firstWhere('code', '0105518000')['province_code']);
    }
}
