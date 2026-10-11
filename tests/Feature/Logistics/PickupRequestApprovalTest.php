<?php

namespace Tests\Feature\Logistics;

use App\Models\Parcel;
use App\Models\PickupRequest;
use App\Models\SortingCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PickupRequestApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_sorting_center_can_list_pending_pickup_requests(): void
    {
        $sortingCenter = $this->approvedSortingCenter();
        $seller = User::factory()->create(['status' => 'approved', 'usertype' => 'seller']);
        $buyer = User::factory()->create(['status' => 'approved', 'usertype' => 'buyer']);
        $parcel = $this->parcel($seller, $buyer);
        $pickupRequest = PickupRequest::create([
            'parcel_id' => $parcel->id,
            'seller_id' => $seller->id,
            'status' => 'pending',
        ]);
        $sortingCenterId = $sortingCenter->sortingCenter->id;

        Sanctum::actingAs($sortingCenter);

        $this->getJson('/api/sorting-center/pickup-requests?status=pending')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $pickupRequest->id)
            ->assertJsonPath('data.data.0.parcel.order_id', 'TEST-ORDER-0001')
            ->assertJsonPath('data.data.0.seller.email', $seller->email);
    }

    public function test_confirming_request_assigns_it_to_the_sorting_center(): void
    {
        $sortingCenter = $this->approvedSortingCenter();
        $sortingCenterId = $sortingCenter->sortingCenter->id;
        $seller = User::factory()->create(['status' => 'approved', 'usertype' => 'seller']);
        $buyer = User::factory()->create(['status' => 'approved', 'usertype' => 'buyer']);
        $parcel = $this->parcel($seller, $buyer);
        $pickupRequest = PickupRequest::create([
            'parcel_id' => $parcel->id,
            'seller_id' => $seller->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($sortingCenter);

        $this->postJson("/api/sorting-center/pickup-requests/{$pickupRequest->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.parcel.status', 'PLACED');

        $this->assertDatabaseHas('pickup_requests', [
            'id' => $pickupRequest->id,
            'sorting_center_id' => $sortingCenterId,
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('parcels', [
            'id' => $parcel->id,
            'sorting_center_id' => $sortingCenterId,
            'status' => 'PLACED',
        ]);
    }

    public function test_unapproved_user_cannot_list_pickup_requests(): void
    {
        $sortingCenter = User::factory()->create(['status' => 'approved', 'usertype' => 'sorting_center']);
        SortingCenter::create([
            'user_id' => $sortingCenter->id,
            'business_name' => 'Pending Center',
            'approval_status' => 'pending',
        ]);

        Sanctum::actingAs($sortingCenter);

        $this->getJson('/api/sorting-center/pickup-requests')
            ->assertForbidden();
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

    public function test_pickup_list_is_paginated_and_rejects_invalid_filters(): void
    {
        $center = $this->approvedSortingCenter();
        $seller = User::factory()->create(['status' => 'approved', 'usertype' => 'seller']);
        $buyer = User::factory()->create(['status' => 'approved', 'usertype' => 'buyer']);
        $parcel = $this->parcel($seller, $buyer);
        for ($i = 0; $i < 26; $i++) {
            PickupRequest::create([
                'parcel_id' => $parcel->id, 'seller_id' => $seller->id, 'status' => 'pending',
            ]);
        }
        Sanctum::actingAs($center);
        $this->getJson('/api/sorting-center/pickup-requests')
            ->assertOk()->assertJsonCount(25, 'data.data')->assertJsonPath('data.total', 26);
        $this->getJson('/api/sorting-center/pickup-requests?page=2')
            ->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.current_page', 2);
        $this->getJson('/api/sorting-center/pickup-requests?status=invalid')
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->getJson('/api/sorting-center/pickup-requests?page=0')
            ->assertUnprocessable()->assertJsonValidationErrors('page');
    }

    private function parcel(User $seller, User $buyer): Parcel
    {
        return Parcel::create([
            'order_id' => 'TEST-ORDER-0001',
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => 'PLACED',
            'delivery_province' => 'Metro Manila',
            'delivery_municipality' => 'Quezon City',
            'delivery_barangay' => 'Bagumbayan',
            'delivery_street' => 'Test Street',
            'delivery_house_number' => '123',
        ]);
    }
}
