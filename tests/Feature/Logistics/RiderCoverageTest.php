<?php

namespace Tests\Feature\Logistics;

use App\Models\{Area, Parcel, PartnerApplication, Rider, SortingCenter, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiderCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $user = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        $center = SortingCenter::create(['user_id' => $user->id, 'business_name' => 'Coverage test', 'approval_status' => 'approved']);
        $riderUser = User::factory()->create(['usertype' => 'rider', 'status' => 'approved']);
        $rider = Rider::create(['user_id' => $riderUser->id, 'sorting_center_id' => $center->id, 'name' => 'Synthetic Rider', 'contact' => '09170000000', 'vehicle_type' => 'bicycle', 'plate_number' => 'AREA-'.$center->id, 'approval_status' => 'approved', 'is_active' => true]);
        $area = Area::create(['sorting_center_id' => $center->id, 'name' => 'Test city']);
        return [$user, $rider, $area];
    }

    public function test_explicit_coverage_save_enables_assignment_and_removal_preserves_existing_delivery(): void
    {
        [$user, $rider, $area] = $this->fixture();
        $seller = User::factory()->create(['usertype' => 'seller']);
        $buyer = User::factory()->create(['usertype' => 'buyer']);
        $parcel = Parcel::create(['order_id' => 'COVERAGE-1', 'seller_id' => $seller->id, 'buyer_id' => $buyer->id, 'sorting_center_id' => $area->sorting_center_id, 'sorted_area_id' => $area->id, 'status' => 'AT_SORTING_CENTER', 'delivery_municipality' => 'Test city', 'delivery_province' => '', 'delivery_barangay' => '']);
        Sanctum::actingAs($user);
        $url = '/api/sorting-center/riders/'.$rider->id.'/delivery-areas';
        $this->getJson($url)->assertOk()->assertJsonPath('data.area_ids', [])->assertJsonPath('data.can_manage', true);
        $this->getJson('/api/sorting-center/parcels/'.$parcel->id.'/eligible-riders')->assertJsonCount(0, 'data')->assertJsonPath('meta.empty_reason', 'No eligible Rider covers this destination area. Use Rider Management → Manage Delivery Areas to configure coverage.');
        $this->assertDatabaseCount('rider_areas', 0);
        $this->putJson($url, ['area_ids' => [$area->id]])->assertOk()->assertJsonPath('data.area_ids', [$area->id]);
        $this->putJson($url, ['area_ids' => [$area->id]])->assertOk();
        $this->assertDatabaseCount('rider_areas', 1);
        $this->getJson('/api/sorting-center/parcels/'.$parcel->id.'/eligible-riders')->assertJsonPath('data.0.id', $rider->id);
        $this->postJson('/api/sorting-center/parcels/'.$parcel->id.'/assign', ['rider_id' => $rider->id])->assertOk();
        $this->putJson($url, ['area_ids' => []])->assertOk();
        $this->getJson('/api/sorting-center/parcels/'.$parcel->id.'/eligible-riders')->assertJsonCount(0, 'data');
        $this->assertSame($rider->id, $parcel->fresh()->assigned_rider_id);
        Sanctum::actingAs($rider->user);
        $this->postJson('/api/rider/deliveries/'.$parcel->id.'/start')->assertOk();
        $this->assertDatabaseCount('seller_delivery_charges', 0);
        $this->assertDatabaseCount('rider_delivery_earnings', 0);
    }

    public function test_foreign_riders_areas_invalid_and_duplicate_ids_cannot_be_saved(): void
    {
        [$user, $rider, $area] = $this->fixture();
        [, $other, $foreign] = $this->fixture();
        Sanctum::actingAs($user);
        $this->getJson('/api/sorting-center/riders/'.$other->id.'/delivery-areas')->assertNotFound();
        $this->putJson('/api/sorting-center/riders/'.$other->id.'/delivery-areas', ['area_ids' => []])->assertNotFound();
        $url = '/api/sorting-center/riders/'.$rider->id.'/delivery-areas';
        foreach ([[], ['area_ids' => [$foreign->id]], ['area_ids' => [$area->id, $area->id]], ['area_ids' => [99999]], ['area_ids' => ['bad']]] as $input) $this->putJson($url, $input)->assertUnprocessable();
        $this->assertDatabaseCount('rider_areas', 0);
    }

    public function test_ineligible_riders_and_unauthorized_accounts_are_blocked(): void
    {
        [$user, $rider, $area] = $this->fixture();
        $url = '/api/sorting-center/riders/'.$rider->id.'/delivery-areas';
        Sanctum::actingAs($user);
        $rider->update(['is_active' => false]);
        $this->getJson($url)->assertJsonPath('data.can_manage', false);
        $this->putJson($url, ['area_ids' => [$area->id]])->assertUnprocessable();
        $rider->update(['is_active' => true, 'approval_status' => 'pending']);
        $this->putJson($url, ['area_ids' => [$area->id]])->assertUnprocessable();
        $rider->update(['approval_status' => 'approved']);
        $rider->user->update(['status' => 'suspended']);
        $this->putJson($url, ['area_ids' => [$area->id]])->assertUnprocessable();
        $rider->user->update(['status' => 'approved', 'email_verified_at' => null]);
        PartnerApplication::create(['user_id' => $rider->user_id, 'role' => 'rider', 'details' => [], 'address' => [], 'documents' => [], 'terms_accepted_at' => now(), 'privacy_accepted_at' => now()]);
        $this->putJson($url, ['area_ids' => [$area->id]])->assertUnprocessable();
        $user->update(['status' => 'pending']);
        $this->getJson($url)->assertForbidden();
        $this->putJson($url, ['area_ids' => []])->assertForbidden();
        foreach (['rider', 'seller', 'buyer', 'admin'] as $role) {
            Sanctum::actingAs(User::factory()->create(['usertype' => $role, 'status' => 'approved']));
            $this->getJson($url)->assertForbidden();
            $this->putJson($url, ['area_ids' => []])->assertForbidden();
        }
        $this->assertDatabaseCount('rider_areas', 0);
    }
}
