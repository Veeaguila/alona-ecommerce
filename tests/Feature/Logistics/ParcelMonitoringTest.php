<?php

namespace Tests\Feature\Logistics;

use App\Models\Area;
use App\Models\Parcel;
use App\Models\Rider;
use App\Models\SortingCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParcelMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitoring_returns_paginated_workflow_parcels_with_relations(): void
    {
        $sortingCenter = $this->approvedSortingCenter();
        $area = Area::create(['sorting_center_id' => $sortingCenter->sortingCenter->id, 'name' => 'Area A']);
        $rider = $this->rider($sortingCenter->sortingCenter->id);
        $rider->areas()->attach($area->id);
        $parcel = $this->parcel($area, 'OUT_FOR_DELIVERY');
        Parcel::create([
            'order_id' => 'OTHER-CENTER-0001',
            'buyer_id' => $parcel->buyer_id,
            'seller_id' => $parcel->seller_id,
            'status' => 'OUT_FOR_DELIVERY',
            'delivery_province' => 'Cebu',
            'delivery_municipality' => 'Cebu City',
            'delivery_barangay' => 'Lahug',
            'sorted_area_id' => Area::create(['sorting_center_id' => SortingCenter::create([
                'user_id' => User::factory()->create(['status' => 'approved', 'usertype' => 'sorting_center'])->id,
                'business_name' => 'Other Center',
                'approval_status' => 'approved',
            ])->id, 'name' => 'Other Area'])->id,
        ]);

        Sanctum::actingAs($sortingCenter);

        $this->getJson('/api/sorting-center/parcels/monitoring?status=OUT_FOR_DELIVERY')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.order_id', $parcel->order_id)
            ->assertJsonPath('data.data.0.sorted_area.name', 'Area A');
    }

    public function test_monitoring_includes_unsorted_arrived_parcels_for_this_workflow(): void
    {
        $sortingCenter = $this->approvedSortingCenter();
        $parcel = $this->parcel(null, 'AT_SORTING_CENTER', $sortingCenter->sortingCenter->id);

        Sanctum::actingAs($sortingCenter);

        $this->getJson('/api/sorting-center/parcels/monitoring?status=AT_SORTING_CENTER')
            ->assertOk()
            ->assertJsonPath('data.data.0.order_id', $parcel->order_id);
    }

    public function test_monitoring_rejects_pre_arrival_status_filters(): void
    {
        Sanctum::actingAs($this->approvedSortingCenter());

        $this->getJson('/api/sorting-center/parcels/monitoring?status=PLACED')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    private function approvedSortingCenter(): User
    {
        $user = User::factory()->create(['status' => 'approved', 'usertype' => 'sorting_center']);
        SortingCenter::create([
            'user_id' => $user->id,
            'business_name' => 'Approved Center',
            'approval_status' => 'approved',
        ]);
        $user->load('sortingCenter');

        return $user;
    }

    private function rider(int $sortingCenterId): Rider
    {
        $user = User::factory()->create(['status' => 'approved', 'usertype' => 'rider']);

        return Rider::create([
            'user_id' => $user->id,
            'sorting_center_id' => $sortingCenterId,
            'name' => 'Test Rider',
            'contact' => '09170000000',
            'vehicle_type' => 'motorcycle',
            'plate_number' => 'TEST-777',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
    }

    private function parcel(?Area $area, string $status, ?int $sortingCenterId = null): Parcel
    {
        $seller = User::factory()->create(['status' => 'approved', 'usertype' => 'seller']);
        $buyer = User::factory()->create(['status' => 'approved', 'usertype' => 'buyer']);

        return Parcel::create([
            'order_id' => 'TEST-MONITOR-0001',
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'sorting_center_id' => $sortingCenterId ?? $area?->sorting_center_id,
            'status' => $status,
            'delivery_province' => 'Metro Manila',
            'delivery_municipality' => 'Taguig',
            'delivery_barangay' => 'Fort Bonifacio',
            'delivery_street' => 'Monitoring Street',
            'delivery_house_number' => '303',
            'sorted_area_id' => $area?->id,
            'sorted_at' => now(),
        ]);
    }
}
