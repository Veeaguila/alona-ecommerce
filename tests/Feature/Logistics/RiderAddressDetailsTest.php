<?php

namespace Tests\Feature\Logistics;

use App\Models\{Parcel, PickupRequest, Rider, SortingCenter, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiderAddressDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $seller = User::factory()->create(['usertype' => 'seller', 'status' => 'approved',
            'name' => 'Seller', 'contact_no' => '09170000001', 'address' => 'Seller collection',
            'province' => 'Seller province', 'municipality' => 'Seller city', 'barangay' => 'Seller barangay', 'street_address' => 'Seller street']);
        $buyer = User::factory()->create(['usertype' => 'buyer', 'name' => 'Buyer',
            'contact_no' => '09170000002', 'address' => 'New buyer home', 'province' => 'Wrong current province']);
        $center = SortingCenter::create(['user_id' => User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved'])->id,
            'business_name' => 'Center', 'approval_status' => 'approved']);
        $user = User::factory()->create(['usertype' => 'rider', 'status' => 'approved']);
        $rider = Rider::create(['user_id' => $user->id, 'sorting_center_id' => $center->id,
            'name' => 'Rider', 'contact' => '09170000003', 'vehicle_type' => 'bicycle', 'plate_number' => '',
            'approval_status' => 'approved', 'is_active' => true]);
        $parcel = Parcel::create(['order_id' => Str::uuid(), 'seller_id' => $seller->id, 'buyer_id' => $buyer->id,
            'delivery_address' => 'Order destination', 'delivery_province' => 'Order province',
            'delivery_municipality' => 'Order city', 'delivery_barangay' => 'Order barangay',
            'delivery_street' => 'Order street', 'delivery_house_number' => '4',
            'collection_address' => 'Seller collection', 'status' => 'PLACED',
            'sorting_center_id' => $center->id, 'assigned_rider_id' => $rider->id]);
        PickupRequest::create(['parcel_id' => $parcel->id, 'seller_id' => $seller->id, 'status' => 'confirmed']);
        Sanctum::actingAs($user);

        return compact('parcel', 'rider', 'user');
    }

    public function test_pickup_details_use_collection_and_expose_only_required_person_fields(): void
    {
        $this->fixture();
        $response = $this->getJson('/api/rider/pickups/available')->assertOk()
            ->assertJsonPath('data.0.address_details.complete_address', 'Seller collection')
            ->assertJsonPath('data.0.address_details.name', 'Seller')
            ->assertJsonPath('data.0.address_details.contact_no', '09170000001')
            ->assertJsonPath('data.0.address_details.province', 'Seller province')
            ->assertJsonPath('data.0.address_details.postal_code', null);
        $this->assertArrayNotHasKey('seller', $response->json('data.0'));
        $this->assertArrayNotHasKey('email', $response->json('data.0.address_details'));
    }

    public function test_override_collection_does_not_borrow_a_different_current_seller_address(): void
    {
        $f = $this->fixture();
        $f['parcel']->update(['collection_address' => 'One-time collection location']);
        $this->getJson('/api/rider/pickups/available')->assertOk()
            ->assertJsonPath('data.0.address_details.complete_address', 'One-time collection location')
            ->assertJsonPath('data.0.address_details.province', null)
            ->assertJsonPath('data.0.address_details.street', null);
    }

    public function test_delivery_preserves_order_destination_and_another_rider_cannot_read_it(): void
    {
        $f = $this->fixture();
        $f['parcel']->update(['status' => 'AT_SORTING_CENTER']);
        $this->getJson('/api/rider/deliveries')->assertOk()
            ->assertJsonPath('data.0.address_details.complete_address', 'Order destination')
            ->assertJsonPath('data.0.address_details.province', 'Order province')
            ->assertJsonPath('data.0.address_details.name', 'Buyer')
            ->assertJsonPath('data.0.address_details.contact_no', '09170000002')
            ->assertJsonPath('data.0.address_details.instructions', null);
        $other = User::factory()->create(['usertype' => 'rider', 'status' => 'approved']);
        Rider::create(['user_id' => $other->id, 'sorting_center_id' => $f['rider']->sorting_center_id,
            'name' => 'Other', 'contact' => '09170000004', 'vehicle_type' => 'bicycle', 'plate_number' => '',
            'approval_status' => 'approved', 'is_active' => true]);
        Sanctum::actingAs($other);
        $this->getJson('/api/rider/deliveries')->assertOk()->assertJsonCount(0, 'data');
    }
}
