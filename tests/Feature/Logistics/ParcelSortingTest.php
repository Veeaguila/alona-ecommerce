<?php

namespace Tests\Feature\Logistics;

use App\Models\Area;
use App\Models\Parcel;
use Illuminate\Support\Str;
use App\Models\SortingCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ParcelSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_sorting_center_can_assign_an_area_without_advancing_parcel_status(): void
    {
        $sortingCenter = $this->approvedSortingCenter();
        $area = Area::create([
            'sorting_center_id' => $sortingCenter->sortingCenter->id,
            'name' => 'Taguig',
        ]);
        $parcel = $this->parcel('AT_SORTING_CENTER', $sortingCenter->sortingCenter->id);

        Sanctum::actingAs($sortingCenter);

        $this->postJson("/api/sorting-center/parcels/{$parcel->id}/sort", [
            'area_id' => $area->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'AT_SORTING_CENTER')
            ->assertJsonPath('data.sorted_area_id', $area->id);

        $this->assertDatabaseHas('parcels', [
            'id' => $parcel->id,
            'status' => 'AT_SORTING_CENTER',
            'sorted_area_id' => $area->id,
        ]);
        $this->assertNotNull(Parcel::find($parcel->id)->sorted_at);
    }

    public function test_parcel_with_wrong_status_cannot_be_sorted(): void
    {
        $sortingCenter = $this->approvedSortingCenter();
        $area = Area::create([
            'sorting_center_id' => $sortingCenter->sortingCenter->id,
            'name' => 'Taguig',
        ]);
        $parcel = $this->parcel('PLACED', $sortingCenter->sortingCenter->id);

        Sanctum::actingAs($sortingCenter);

        $this->postJson("/api/sorting-center/parcels/{$parcel->id}/sort", [
            'area_id' => $area->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('current_status', 'PLACED');
    }

    public function test_area_from_another_sorting_center_is_rejected(): void
    {
        $sortingCenter = $this->approvedSortingCenter();
        $otherCenter = $this->approvedSortingCenter('Other Center');
        $foreignArea = Area::create([
            'sorting_center_id' => $otherCenter->sortingCenter->id,
            'name' => 'Foreign Area',
        ]);
        $parcel = $this->parcel('AT_SORTING_CENTER', $sortingCenter->sortingCenter->id);

        Sanctum::actingAs($sortingCenter);

        $this->postJson("/api/sorting-center/parcels/{$parcel->id}/sort", [
            'area_id' => $foreignArea->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('area_id', $foreignArea->id);
    }

    public function test_area_list_only_returns_areas_for_authenticated_center(): void
    {
        $sortingCenter = $this->approvedSortingCenter();
        Area::create([
            'sorting_center_id' => $sortingCenter->sortingCenter->id,
            'name' => 'Area A',
        ]);
        $otherCenter = $this->approvedSortingCenter('Other Center');
        Area::create([
            'sorting_center_id' => $otherCenter->sortingCenter->id,
            'name' => 'Foreign Area',
        ]);

        Sanctum::actingAs($sortingCenter);

        $this->getJson('/api/sorting-center/areas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Area A');
    }

    public function test_parcel_cannot_be_sorted_to_a_city_other_than_its_destination(): void
    {
        $sortingCenter = $this->approvedSortingCenter();
        $area = Area::create([
            'sorting_center_id' => $sortingCenter->sortingCenter->id,
            'name' => 'Makati',
        ]);
        $parcel = $this->parcel('AT_SORTING_CENTER', $sortingCenter->sortingCenter->id);
        Sanctum::actingAs($sortingCenter);

        $this->postJson("/api/sorting-center/parcels/{$parcel->id}/sort", [
            'area_id' => $area->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The selected service area must match the parcel destination city.');
    }

    public function test_sorting_center_can_create_a_city_service_area(): void
    {
        $sortingCenter = $this->approvedSortingCenter();
        Sanctum::actingAs($sortingCenter);

        $this->postJson('/api/sorting-center/areas', ['name' => 'Taguig'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Taguig');

        $this->assertDatabaseHas('areas', [
            'sorting_center_id' => $sortingCenter->sortingCenter->id,
            'name' => 'Taguig',
        ]);
    }

    public function test_unsorted_parcels_are_isolated_to_their_sorting_center(): void
    {
        $firstCenter = $this->approvedSortingCenter();
        $secondCenter = $this->approvedSortingCenter('Second Center');
        $firstParcel = $this->parcel('AT_SORTING_CENTER', $firstCenter->sortingCenter->id);
        $this->parcel('AT_SORTING_CENTER', $secondCenter->sortingCenter->id);

        Sanctum::actingAs($firstCenter);

        $this->getJson('/api/sorting-center/parcels')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $firstParcel->id);
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

    private function parcel(string $status, int $sortingCenterId): Parcel
    {
        $seller = User::factory()->create(['status' => 'approved', 'usertype' => 'seller']);
        $buyer = User::factory()->create(['status' => 'approved', 'usertype' => 'buyer']);

        return Parcel::create([
            'order_id' => 'TEST-SORTING-'.Str::uuid(),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'sorting_center_id' => $sortingCenterId,
            'status' => $status,
            'delivery_province' => 'Metro Manila',
            'delivery_municipality' => 'Taguig',
            'delivery_barangay' => 'Fort Bonifacio',
            'delivery_street' => 'Sorting Street',
            'delivery_house_number' => '789',
        ]);
    }
}
