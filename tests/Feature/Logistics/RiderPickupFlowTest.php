<?php

namespace Tests\Feature\Logistics;

use App\Models\Parcel;
use App\Models\PickupRequest;
use App\Models\Rider;
use App\Models\SortingCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiderPickupFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_request_can_be_approved_picked_up_and_received_without_role_pages(): void
    {
        [$riderUser, $rider] = $this->approvedRider();
        $centerUser = $rider->sortingCenter->user;
        $parcel = $this->parcel('PENDING_PICKUP');
        $pickup = PickupRequest::create([
            'parcel_id' => $parcel->id,
            'seller_id' => $parcel->seller_id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($centerUser);
        $this->postJson("/api/sorting-center/pickup-requests/{$pickup->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.parcel.status', 'PLACED');

        Sanctum::actingAs($riderUser);
        $this->getJson('/api/rider/pickups/available')
            ->assertOk()->assertJsonPath('data.0.id', $parcel->id);
        $this->postJson("/api/rider/pickups/{$parcel->id}/accept")->assertOk();
        $this->postJson("/api/rider/pickups/{$parcel->id}/confirm")
            ->assertOk()->assertJsonPath('data.status', 'PICKED_UP');

        Sanctum::actingAs($centerUser);
        $this->postJson('/api/sorting-center/parcels/mark-arrived', ['order_id' => $parcel->order_id])
            ->assertOk()->assertJsonPath('data.status', 'AT_SORTING_CENTER');
        $this->getJson('/api/sorting-center/parcels')
            ->assertOk()->assertJsonPath('data.0.id', $parcel->id);
    }

    public function test_only_parcels_waiting_for_pickup_appear_in_the_available_queue(): void
    {
        [$riderUser, $rider] = $this->approvedRider();
        $available = $this->parcel('PLACED');
        $available->update(['sorting_center_id' => $rider->sorting_center_id]);
        $this->confirmedPickup($available);
        $arrived = $this->parcel('AT_SORTING_CENTER');
        $this->confirmedPickup($arrived);

        Sanctum::actingAs($riderUser);

        $this->getJson('/api/rider/pickups/available')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $available->id);
    }

    public function test_rider_cannot_accept_a_pickup_after_the_parcel_has_arrived(): void
    {
        [$riderUser, $rider] = $this->approvedRider();
        $parcel = $this->parcel('AT_SORTING_CENTER');
        $parcel->update(['sorting_center_id' => $rider->sorting_center_id]);
        $this->confirmedPickup($parcel);

        Sanctum::actingAs($riderUser);

        $this->postJson("/api/rider/pickups/{$parcel->id}/accept")
            ->assertUnprocessable()
            ->assertJsonPath('current_status', 'AT_SORTING_CENTER');

        $this->assertDatabaseHas('parcels', [
            'id' => $parcel->id,
            'pickup_rider_id' => null,
        ]);
    }

    private function approvedRider(): array
    {
        $sortingCenterUser = User::factory()->create(['status' => 'approved', 'usertype' => 'sorting_center']);
        $sortingCenter = SortingCenter::create([
            'user_id' => $sortingCenterUser->id,
            'business_name' => 'Pickup Test Center',
            'approval_status' => 'approved',
        ]);
        $user = User::factory()->create(['status' => 'approved', 'usertype' => 'rider']);
        $rider = Rider::create([
            'user_id' => $user->id,
            'sorting_center_id' => $sortingCenter->id,
            'name' => 'Pickup Test Rider',
            'contact' => '09170000000',
            'vehicle_type' => 'motorcycle',
            'plate_number' => 'PICKUP-1',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        return [$user, $rider];
    }

    public function test_rider_cannot_list_or_accept_another_centers_pickup(): void
    {
        [$user, $rider] = $this->approvedRider();
        [, $other] = $this->approvedRider();
        $own = $this->parcel('PLACED');
        $own->update(['sorting_center_id' => $rider->sorting_center_id]);
        $foreign = $this->parcel('PLACED');
        $foreign->update(['sorting_center_id' => $other->sorting_center_id]);
        $this->confirmedPickup($own);
        $this->confirmedPickup($foreign);
        Sanctum::actingAs($user);

        $this->getJson('/api/rider/pickups/available')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->postJson("/api/rider/pickups/{$foreign->id}/accept")->assertNotFound();
        $this->assertNull($foreign->fresh()->pickup_rider_id);
        $this->postJson("/api/rider/pickups/{$own->id}/accept")->assertOk();
    }

    public function test_rider_without_a_center_cannot_access_unassigned_pickups(): void
    {
        [$user, $rider] = $this->approvedRider();
        $rider->update(['sorting_center_id' => null]);
        $parcel = $this->parcel('PLACED');
        $this->confirmedPickup($parcel);
        Sanctum::actingAs($user);
        $this->getJson('/api/rider/pickups/available')->assertForbidden();
        $this->postJson("/api/rider/pickups/{$parcel->id}/accept")->assertForbidden();
        $this->assertNull($parcel->fresh()->pickup_rider_id);
    }

    private function parcel(string $status): Parcel
    {
        $seller = User::factory()->create(['status' => 'approved', 'usertype' => 'seller']);
        $buyer = User::factory()->create(['status' => 'approved', 'usertype' => 'buyer']);

        return Parcel::create([
            'order_id' => 'PICKUP-'.Str::uuid(),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => $status,
            'delivery_province' => 'Metro Manila',
            'delivery_municipality' => 'Quezon City',
            'delivery_barangay' => 'Bagumbayan',
        ]);
    }

    private function confirmedPickup(Parcel $parcel): void
    {
        PickupRequest::create([
            'parcel_id' => $parcel->id,
            'seller_id' => $parcel->seller_id,
            'status' => 'confirmed',
        ]);
    }
}
