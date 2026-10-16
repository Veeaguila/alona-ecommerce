<?php

namespace Tests\Feature\Logistics;

use App\Models\{Parcel, Rider, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiderParcelLookupTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $user = User::factory()->create(['usertype' => 'rider', 'status' => 'approved']);
        $rider = Rider::create(['user_id' => $user->id, 'name' => 'Assigned rider', 'contact' => '09170000001',
            'vehicle_type' => 'bicycle', 'plate_number' => '', 'approval_status' => 'approved', 'is_active' => true]);
        $buyer = User::factory()->create(['name' => 'Buyer', 'contact_no' => '09170000002']);
        $parcel = Parcel::create(['order_id' => 'ITEM-101', 'buyer_id' => $buyer->id,
            'seller_id' => User::factory()->create(['usertype' => 'seller'])->id,
            'assigned_rider_id' => $rider->id, 'status' => 'OUT_FOR_DELIVERY', 'delivery_address' => 'Order destination',
            'delivery_province' => '', 'delivery_municipality' => '', 'delivery_barangay' => '']);
        Sanctum::actingAs($user);
        return compact('user', 'rider', 'parcel');
    }

    public function test_guest_and_non_rider_cannot_use_scan_lookup(): void
    {
        $this->getJson('/api/rider/deliveries/lookup?reference=ITEM-101')->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create(['usertype' => 'seller', 'status' => 'approved']));
        $this->getJson('/api/rider/deliveries/lookup?reference=ITEM-101')->assertForbidden();
    }

    public function test_own_reference_resolves_delivery_dto_without_mutating_or_duplicate_records(): void
    {
        $f = $this->fixture();
        for ($i = 0; $i < 2; $i++) {
            $this->getJson('/api/rider/deliveries/lookup?reference=item-101')->assertOk()
                ->assertJsonPath('data.id', $f['parcel']->id)->assertJsonPath('data.address_details.name', 'Buyer')
                ->assertJsonPath('data.address_details.complete_address', 'Order destination')
                ->assertJsonPath('data.has_delivery_proof', false)->assertJsonMissingPath('data.buyer.email');
        }
        $this->assertDatabaseCount('parcels', 1);
        $this->assertDatabaseCount('delivery_proofs', 0);
        $this->assertSame('OUT_FOR_DELIVERY', $f['parcel']->fresh()->status);
    }

    public function test_invalid_unknown_and_other_rider_codes_do_not_expose_parcel_details(): void
    {
        $f = $this->fixture();
        $this->getJson('/api/rider/deliveries/lookup?reference=https%3A%2F%2Fexample.test')->assertUnprocessable();
        $this->getJson('/api/rider/deliveries/lookup?reference=ITEM-999')->assertNotFound()->assertJsonMissingPath('data');
        $f['parcel']->update(['assigned_rider_id' => null]);
        $this->getJson('/api/rider/deliveries/lookup?reference=ITEM-101')->assertNotFound()->assertJsonMissingPath('data');
        $f['parcel']->update(['assigned_rider_id' => Rider::create(['user_id' => User::factory()->create(['usertype' => 'rider'])->id,
            'name' => 'Other', 'contact' => '09170000003', 'vehicle_type' => 'bicycle', 'plate_number' => '', 'approval_status' => 'approved', 'is_active' => true])->id]);
        $this->getJson('/api/rider/deliveries/lookup?reference=ITEM-101')->assertNotFound()->assertJsonMissingPath('data');
    }

    public function test_completed_returned_not_ready_and_pending_accounts_are_handled_safely(): void
    {
        $f = $this->fixture();
        foreach (['DELIVERED', 'FAILED_RETURNED'] as $status) {
            $f['parcel']->update(['status' => $status]);
            $this->getJson('/api/rider/deliveries/lookup?reference=ITEM-101')->assertConflict()
                ->assertJsonPath('code', 'parcel_completed')->assertJsonMissingPath('data');
        }
        $f['parcel']->update(['status' => 'PLACED']);
        $this->getJson('/api/rider/deliveries/lookup?reference=ITEM-101')->assertUnprocessable()->assertJsonPath('code', 'parcel_not_ready');
        $f['user']->update(['status' => 'pending']);
        Sanctum::actingAs($f['user']->fresh());
        $this->getJson('/api/rider/deliveries/lookup?reference=ITEM-101')->assertForbidden();
    }
}
