<?php

namespace Tests\Feature\Logistics;

use App\Models\Area;
use App\Models\Parcel;
use App\Models\PickupRequest;
use App\Models\Rider;
use App\Models\SortingCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_the_six_confirmed_counts(): void
    {
        $sortingCenter = $this->approvedSortingCenter();
        $area = Area::create(['sorting_center_id' => $sortingCenter->sortingCenter->id, 'name' => 'Area A']);
        $activeRider = $this->rider('approved', true, $sortingCenter->sortingCenter->id);
        $activeRider->areas()->attach($area->id);
        $this->rider('pending', true, $sortingCenter->sortingCenter->id);
        $this->parcel(null, 'AT_SORTING_CENTER', sortingCenterId: $sortingCenter->sortingCenter->id);
        $this->parcel($area, 'AT_SORTING_CENTER', null, $area->id);
        $this->parcel($area, 'OUT_FOR_DELIVERY', $activeRider->id, $area->id);
        $pendingParcel = $this->parcel(null, 'PLACED', null, null, $sortingCenter->sortingCenter->id);
        PickupRequest::create([
            'parcel_id' => $pendingParcel->id,
            'seller_id' => $pendingParcel->seller_id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($sortingCenter);

        $this->getJson('/api/sorting-center/dashboard')
            ->assertOk()
            ->assertJsonPath('data.riders_pending_approval', 1)
            ->assertJsonPath('data.pickup_requests_pending', 1)
            ->assertJsonPath('data.parcels_awaiting_sorting', 1)
            ->assertJsonPath('data.parcels_awaiting_rider_assignment', 1)
            ->assertJsonPath('data.parcels_out_for_delivery', 1)
            ->assertJsonPath('data.active_approved_riders', 1);
    }

    public function test_active_riders_are_scoped_to_this_sorting_center_areas(): void
    {
        $sortingCenter = $this->approvedSortingCenter();
        $otherCenter = $this->approvedSortingCenter('Other Center');
        $otherArea = Area::create(['sorting_center_id' => $otherCenter->sortingCenter->id, 'name' => 'Other Area']);
        $rider = $this->rider('approved', true, $otherCenter->sortingCenter->id);
        $rider->areas()->attach($otherArea->id);

        Sanctum::actingAs($sortingCenter);

        $this->getJson('/api/sorting-center/dashboard')
            ->assertOk()
            ->assertJsonPath('data.active_approved_riders', 0);
    }

    private function approvedSortingCenter(string $businessName = 'Approved Center'): User
    {
        $user = User::factory()->create(['status' => 'approved', 'usertype' => 'sorting_center']);
        SortingCenter::create([
            'user_id' => $user->id,
            'business_name' => $businessName,
            'approval_status' => 'approved',
        ]);
        $user->load('sortingCenter');

        return $user;
    }

    private function rider(string $approvalStatus, bool $isActive, int $sortingCenterId): Rider
    {
        $user = User::factory()->create(['status' => 'approved', 'usertype' => 'rider']);

        return Rider::create([
            'user_id' => $user->id,
            'sorting_center_id' => $sortingCenterId,
            'name' => 'Test Rider',
            'contact' => '09170000000',
            'vehicle_type' => 'motorcycle',
            'plate_number' => 'TEST-777',
            'approval_status' => $approvalStatus,
            'is_active' => $isActive,
        ]);
    }

    private function parcel(
        ?Area $area,
        string $status,
        ?int $assignedRiderId = null,
        ?int $sortedAreaId = null,
        ?int $sortingCenterId = null,
    ): Parcel
    {
        $seller = User::factory()->create(['status' => 'approved', 'usertype' => 'seller']);
        $buyer = User::factory()->create(['status' => 'approved', 'usertype' => 'buyer']);

        return Parcel::create([
            'order_id' => uniqid('TEST-DASHBOARD-'),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'sorting_center_id' => $sortingCenterId ?? $area?->sorting_center_id,
            'status' => $status,
            'delivery_province' => 'Metro Manila',
            'delivery_municipality' => 'Taguig',
            'delivery_barangay' => 'Fort Bonifacio',
            'sorted_area_id' => $sortedAreaId ?? $area?->id,
            'assigned_rider_id' => $assignedRiderId,
        ]);
    }
}
