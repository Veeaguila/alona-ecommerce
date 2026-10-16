<?php

namespace Tests\Feature\Logistics;

use App\Models\Area;
use App\Models\Parcel;
use App\Models\PartnerApplication;
use App\Models\Rider;
use App\Models\SortingCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ParcelAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_active_rider_can_be_assigned_without_starting_delivery(): void
    {
        $sortingCenter = $this->approvedSortingCenter();
        $area = Area::create(['sorting_center_id' => $sortingCenter->sortingCenter->id, 'name' => 'Taguig']);
        $rider = $this->rider('approved', true, $sortingCenter->sortingCenter->id);
        $rider->areas()->attach($area->id);
        $parcel = $this->parcel($area, 'AT_SORTING_CENTER');

        Sanctum::actingAs($sortingCenter);

        $this->postJson("/api/sorting-center/parcels/{$parcel->id}/assign", ['rider_id' => $rider->id])
            ->assertOk()
            ->assertJsonPath('data.assigned_rider_id', $rider->id)
            ->assertJsonPath('data.status', 'AT_SORTING_CENTER');

        $this->assertDatabaseHas('parcels', [
            'id' => $parcel->id,
            'assigned_rider_id' => $rider->id,
            'status' => 'AT_SORTING_CENTER',
        ]);
    }

    public function test_area_without_covering_rider_returns_an_empty_eligible_list(): void
    {
        $sortingCenter = $this->approvedSortingCenter();
        $area = Area::create(['sorting_center_id' => $sortingCenter->sortingCenter->id, 'name' => 'Area B']);
        $parcel = $this->parcel($area, 'AT_SORTING_CENTER');

        Sanctum::actingAs($sortingCenter);

        $this->getJson("/api/sorting-center/parcels/{$parcel->id}/eligible-riders")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_rider_who_does_not_cover_the_area_is_rejected(): void
    {
        $sortingCenter = $this->approvedSortingCenter();
        $area = Area::create(['sorting_center_id' => $sortingCenter->sortingCenter->id, 'name' => 'Area A']);
        $rider = $this->rider('approved', true, $sortingCenter->sortingCenter->id);
        $parcel = $this->parcel($area, 'AT_SORTING_CENTER');

        Sanctum::actingAs($sortingCenter);

        $this->postJson("/api/sorting-center/parcels/{$parcel->id}/assign", ['rider_id' => $rider->id])
            ->assertStatus(422)
            ->assertJsonPath('rider_id', $rider->id);
    }

    public function test_unapproved_or_inactive_riders_are_not_eligible(): void
    {
        $sortingCenter = $this->approvedSortingCenter();
        $area = Area::create(['sorting_center_id' => $sortingCenter->sortingCenter->id, 'name' => 'Area A']);
        $rider = $this->rider('pending', false, $sortingCenter->sortingCenter->id);
        $rider->areas()->attach($area->id);
        $parcel = $this->parcel($area, 'AT_SORTING_CENTER');

        Sanctum::actingAs($sortingCenter);

        $this->getJson("/api/sorting-center/parcels/{$parcel->id}/eligible-riders")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public static function readinessCases(): array
    {
        return [
            'legacy unverified allowed' => ['approved', 'rider', 'approved', true, false, false, true],
            'verified application allowed' => ['approved', 'rider', 'approved', true, true, true, true],
            'unverified application blocked' => ['approved', 'rider', 'approved', true, true, false, false],
            'pending account blocked' => ['pending', 'rider', 'approved', true, false, true, false],
            'suspended account blocked' => ['suspended', 'rider', 'approved', true, false, true, false],
            'wrong role blocked' => ['approved', 'buyer', 'approved', true, false, true, false],
            'pending rider blocked' => ['approved', 'rider', 'pending', true, false, true, false],
            'inactive rider blocked' => ['approved', 'rider', 'approved', false, false, true, false],
        ];
    }

    #[DataProvider('readinessCases')]
    public function test_assignment_and_operational_middleware_agree(
        string $status, string $role, string $approval, bool $active,
        bool $application, bool $verified, bool $allowed
    ): void {
        $center = $this->approvedSortingCenter();
        $area = Area::create(['sorting_center_id' => $center->sortingCenter->id, 'name' => 'Area A']);
        $rider = $this->rider($approval, $active, $center->sortingCenter->id);
        $rider->areas()->attach($area->id);
        $user = $rider->user;
        $user->forceFill(['status' => $status, 'usertype' => $role, 'email_verified_at' => $verified ? now() : null])->save();
        if ($application) {
            PartnerApplication::create(['user_id' => $user->id, 'role' => 'rider',
                'details' => [], 'address' => [], 'documents' => [],
                'terms_accepted_at' => now(), 'privacy_accepted_at' => now()]);
        }
        $parcel = $this->parcel($area, 'AT_SORTING_CENTER');
        Sanctum::actingAs($center);
        $this->getJson("/api/sorting-center/parcels/{$parcel->id}/eligible-riders")
            ->assertOk()->assertJsonCount($allowed ? 1 : 0, 'data');
        $this->postJson("/api/sorting-center/parcels/{$parcel->id}/assign", ['rider_id' => $rider->id])
            ->assertStatus($allowed ? 200 : 422);
        $this->assertSame($allowed ? $rider->id : null, $parcel->fresh()->assigned_rider_id);

        Sanctum::actingAs($user->fresh());
        $this->getJson('/api/rider/deliveries')->assertStatus($allowed ? 200 : 403);
        if ($role === 'rider') {
            $this->getJson('/api/partner/profile')->assertOk()
                ->assertJsonPath('data.status', $status)
                ->assertJsonPath('data.approvalStatus', $approval)
                ->assertJsonPath('data.is_active', $active)
                ->assertJsonPath('data.verification_required', $application)
                ->assertJsonPath('data.verified', $verified);
        }
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

    private function parcel(Area $area, string $status): Parcel
    {
        $seller = User::factory()->create(['status' => 'approved', 'usertype' => 'seller']);
        $buyer = User::factory()->create(['status' => 'approved', 'usertype' => 'buyer']);

        return Parcel::create([
            'order_id' => 'TEST-ASSIGN-0001',
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'sorting_center_id' => $area->sorting_center_id,
            'status' => $status,
            'delivery_province' => 'Metro Manila',
            'delivery_municipality' => 'Taguig',
            'delivery_barangay' => 'Fort Bonifacio',
            'delivery_street' => 'Assignment Street',
            'delivery_house_number' => '101',
            'sorted_area_id' => $area->id,
            'sorted_at' => now(),
        ]);
    }
}
