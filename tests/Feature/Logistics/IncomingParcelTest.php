<?php

namespace Tests\Feature\Logistics;

use App\Models\Parcel;
use App\Models\SortingCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IncomingParcelTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_sorting_center_can_search_for_a_picked_up_parcel(): void
    {
        Sanctum::actingAs($this->approvedSortingCenter());
        $parcel = $this->parcel('PICKED_UP');

        $this->getJson('/api/sorting-center/parcels/lookup/'.$parcel->order_id)
            ->assertOk()
            ->assertJsonPath('data.order_id', $parcel->order_id)
            ->assertJsonPath('data.status', 'PICKED_UP')
            ->assertJsonPath('data.delivery_province', 'Metro Manila');
    }

    public function test_a_sorting_center_cannot_search_for_another_centers_parcel(): void
    {
        $firstCenter = $this->approvedSortingCenter();
        $secondCenter = $this->approvedSortingCenter('Second Center');
        $parcel = $this->parcel('PICKED_UP');

        Sanctum::actingAs($firstCenter);
        $this->postJson('/api/sorting-center/parcels/mark-arrived', [
            'order_id' => $parcel->order_id,
        ])->assertOk();

        Sanctum::actingAs($secondCenter);
        $this->getJson('/api/sorting-center/parcels/lookup/'.$parcel->order_id)
            ->assertNotFound()
            ->assertJsonPath('message', 'Parcel not found for this sorting center.');
    }

    public function test_approved_sorting_center_can_mark_a_picked_up_parcel_as_arrived(): void
    {
        $sortingCenter = $this->approvedSortingCenter();
        $parcel = $this->parcel('PICKED_UP');
        $sortingCenterId = $sortingCenter->sortingCenter->id;

        Sanctum::actingAs($sortingCenter);

        $this->postJson('/api/sorting-center/parcels/mark-arrived', [
            'order_id' => $parcel->order_id,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'AT_SORTING_CENTER')
            ->assertJsonPath('data.sorted_at', null)
            ->assertJsonPath('data.sorted_area_id', null);

        $this->assertDatabaseHas('parcels', [
            'id' => $parcel->id,
            'status' => 'AT_SORTING_CENTER',
            'sorting_center_id' => $sortingCenterId,
            'sorted_at' => null,
            'sorted_area_id' => null,
        ]);
    }

    public function test_a_parcel_arrival_is_owned_by_only_the_scanning_sorting_center(): void
    {
        $firstCenter = $this->approvedSortingCenter();
        $secondCenter = $this->approvedSortingCenter('Second Center');
        $parcel = $this->parcel('PICKED_UP');

        Sanctum::actingAs($firstCenter);
        $this->postJson('/api/sorting-center/parcels/mark-arrived', [
            'order_id' => $parcel->order_id,
        ])->assertOk();

        Sanctum::actingAs($secondCenter);
        $this->postJson('/api/sorting-center/parcels/mark-arrived', [
            'order_id' => $parcel->order_id,
        ])->assertStatus(409);

        $this->assertDatabaseHas('parcels', [
            'id' => $parcel->id,
            'sorting_center_id' => $firstCenter->sortingCenter->id,
            'status' => 'AT_SORTING_CENTER',
        ]);
    }

    public function test_missing_order_id_returns_a_clear_unprocessable_response(): void
    {
        Sanctum::actingAs($this->approvedSortingCenter());

        $this->postJson('/api/sorting-center/parcels/mark-arrived', [
            'order_id' => 'DOES-NOT-EXIST',
        ])
            ->assertStatus(422)
            ->assertJsonPath('order_id', 'DOES-NOT-EXIST')
            ->assertJsonPath('message', 'Parcel not found for order ID: DOES-NOT-EXIST.');
    }

    public function test_parcel_with_wrong_status_returns_current_status(): void
    {
        Sanctum::actingAs($this->approvedSortingCenter());
        $parcel = $this->parcel('PLACED');

        $this->postJson('/api/sorting-center/parcels/mark-arrived', [
            'order_id' => $parcel->order_id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('current_status', 'PLACED')
            ->assertJsonPath('order_id', $parcel->order_id);
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

    private function parcel(string $status): Parcel
    {
        $seller = User::factory()->create(['status' => 'approved', 'usertype' => 'seller']);
        $buyer = User::factory()->create(['status' => 'approved', 'usertype' => 'buyer']);

        return Parcel::create([
            'order_id' => 'TEST-INCOMING-0001',
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => $status,
            'delivery_province' => 'Metro Manila',
            'delivery_municipality' => 'Quezon City',
            'delivery_barangay' => 'Bagumbayan',
            'delivery_street' => 'Test Street',
            'delivery_house_number' => '123',
        ]);
    }
}
