<?php

namespace Tests\Feature\Logistics;

use App\Models\{Area, Parcel, PickupRequest, Rider, SortingCenter, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiderSuspensionTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $staff = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        $center = SortingCenter::create(['user_id' => $staff->id, 'business_name' => 'Suspension test', 'approval_status' => 'approved']);
        $user = User::factory()->create(['usertype' => 'rider', 'status' => 'approved']);
        $rider = Rider::create(['user_id' => $user->id, 'sorting_center_id' => $center->id, 'name' => 'Retained jobs Rider',
            'contact' => '09170000000', 'vehicle_type' => 'bicycle', 'plate_number' => '', 'approval_status' => 'approved', 'is_active' => true]);
        $area = Area::create(['sorting_center_id' => $center->id, 'name' => 'Cebu City']);
        $rider->areas()->attach($area);
        return [$staff, $rider, $area];
    }

    private function parcel(Rider $rider, Area $area, string $status, bool $owned = false): Parcel
    {
        return Parcel::create(['order_id' => 'SUSPEND-'.uniqid(), 'seller_id' => User::factory()->create(['usertype' => 'seller'])->id,
            'buyer_id' => User::factory()->create()->id, 'sorting_center_id' => $rider->sorting_center_id,
            'status' => $status, 'pickup_rider_id' => $owned ? $rider->id : null,
            'assigned_rider_id' => $owned && $status === 'AT_SORTING_CENTER' ? $rider->id : null,
            'sorted_area_id' => $area->id, 'delivery_municipality' => 'Cebu City', 'delivery_province' => '', 'delivery_barangay' => '']);
    }

    public function test_suspend_and_reactivate_are_audited_retain_jobs_and_block_new_work(): void
    {
        [$staff, $rider, $area] = $this->fixture();
        foreach (['PLACED', 'PICKED_UP', 'AT_SORTING_CENTER'] as $status) $this->parcel($rider, $area, $status, true);
        $new = $this->parcel($rider, $area, 'PLACED');
        PickupRequest::create(['parcel_id' => $new->id, 'seller_id' => $new->seller_id, 'sorting_center_id' => $rider->sorting_center_id, 'status' => 'confirmed']);
        $delivery = $this->parcel($rider, $area, 'AT_SORTING_CENTER');
        $before = Parcel::orderBy('id')->get()->toArray();
        Sanctum::actingAs($staff);
        $url = '/api/sorting-center/riders/'.$rider->id;
        $this->postJson($url.'/deactivate', ['reason' => 'Repeated late deliveries this week.'])->assertOk()->assertJsonPath('data.is_active', false);
        $this->postJson($url.'/deactivate', ['reason' => 'Repeated late deliveries this week.'])->assertStatus(409);
        $this->assertSame($before, Parcel::orderBy('id')->get()->toArray());
        $this->assertDatabaseHas('rider_decision_audits', ['action' => 'suspend', 'actor_user_id' => $staff->id, 'rider_id' => $rider->id, 'reason' => 'Repeated late deliveries this week.']);
        $this->getJson('/api/sorting-center/parcels/'.$delivery->id.'/eligible-riders')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/sorting-center/parcels/'.$delivery->id.'/assign', ['rider_id' => $rider->id])->assertUnprocessable();
        Sanctum::actingAs($rider->user);
        $this->postJson('/api/rider/pickups/'.$new->id.'/accept')->assertForbidden();
        Sanctum::actingAs($staff);
        $this->postJson($url.'/activate')->assertOk()->assertJsonPath('data.is_active', true);
        $this->postJson($url.'/activate')->assertStatus(409);
        $this->assertSame($before, Parcel::orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('rider_decision_audits', 2);
        $this->getJson($url.'/audits')->assertOk()->assertJsonPath('data.data.0.action', 'reactivate');
        Sanctum::actingAs($rider->user->fresh());
        $this->postJson('/api/rider/pickups/'.$new->id.'/accept')->assertUnprocessable();
        $this->postJson('/api/rider/presence', ['action' => 'go_online'])->assertOk();
        $this->postJson('/api/rider/pickups/'.$new->id.'/accept')->assertUnprocessable();
        Parcel::where('assigned_rider_id', $rider->id)->where('status', 'AT_SORTING_CENTER')->firstOrFail()
            ->update(['status' => 'DELIVERED', 'delivered_at' => now()]);
        $this->postJson('/api/rider/pickups/'.$new->id.'/accept')->assertOk();
        $this->assertDatabaseCount('seller_delivery_charges', 0);
        $this->assertDatabaseCount('rider_delivery_earnings', 0);
    }

    public function test_invalid_reasons_foreign_staff_and_other_roles_cannot_suspend(): void
    {
        [$staff, $rider] = $this->fixture();
        $url = '/api/sorting-center/riders/'.$rider->id.'/deactivate';
        Sanctum::actingAs($staff);
        foreach (['', '         ', '123456789', str_repeat('a', 301), ['invalid']] as $reason) {
            $this->postJson($url, ['reason' => $reason])->assertUnprocessable();
            $this->assertTrue($rider->fresh()->is_active);
        }
        [$other] = $this->fixture();
        Sanctum::actingAs($other);
        $this->postJson($url, ['reason' => 'Valid reason from foreign staff'])->assertNotFound();
        Sanctum::actingAs($rider->user);
        $this->postJson($url, ['reason' => 'Valid reason from wrong role'])->assertForbidden();
        $this->assertTrue($rider->fresh()->is_active);
        $this->assertDatabaseCount('rider_decision_audits', 0);
    }

    public function test_reactivation_cannot_bypass_account_approval_and_missing_audit_storage_is_atomic(): void
    {
        [$staff, $rider] = $this->fixture();
        Sanctum::actingAs($staff);
        $url = '/api/sorting-center/riders/'.$rider->id;
        $rider->update(['is_active' => false]);
        $rider->user->update(['status' => 'suspended']);
        $this->postJson($url.'/activate')->assertUnprocessable();
        $this->assertFalse($rider->fresh()->is_active);
        $rider->user->update(['status' => 'approved']);
        Schema::rename('rider_decision_audits', 'test_unavailable_audits');
        try { $this->postJson($url.'/activate')->assertStatus(503); }
        finally { Schema::rename('test_unavailable_audits', 'rider_decision_audits'); }
        $this->assertFalse($rider->fresh()->is_active);
        $this->assertDatabaseCount('rider_decision_audits', 0);
    }
}
