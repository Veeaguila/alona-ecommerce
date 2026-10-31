<?php

namespace Tests\Feature\Logistics;

use App\Models\{HubTransfer, LogisticsHub, LogisticsHubStaff, Parcel, ParcelHubJourney, SortingCenter, User};
use App\Services\LogisticsAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Hash, Schema};
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HubStaffWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private SortingCenter $company;
    private LogisticsHub $origin;
    private LogisticsHub $destination;
    private Parcel $parcel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->owner = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        $this->company = SortingCenter::create(['user_id' => $this->owner->id, 'business_name' => 'Synthetic Hub Company', 'approval_status' => 'approved']);
        $this->origin = $this->hub('ORIGIN', 'Synthetic origin');
        $this->destination = $this->hub('DEST', 'Synthetic destination');
        $buyer = User::factory()->create(['usertype' => 'buyer']);
        $seller = User::factory()->create(['usertype' => 'seller']);
        $this->parcel = Parcel::create(['order_id' => 'ITEM-9001', 'buyer_id' => $buyer->id, 'seller_id' => $seller->id,
            'sorting_center_id' => $this->company->id, 'status' => 'PICKED_UP', 'delivery_province' => 'Synthetic province',
            'delivery_municipality' => 'Synthetic city', 'delivery_barangay' => 'Synthetic barangay',
            'collection_address' => 'Synthetic Seller collection address', 'delivery_address' => 'Synthetic saved Buyer delivery address']);
        ParcelHubJourney::create(['parcel_id' => $this->parcel->id, 'sorting_center_id' => $this->company->id,
            'origin_hub_id' => $this->origin->id, 'destination_hub_id' => $this->destination->id,
            'route_snapshot' => ['origin_hub_id' => $this->origin->id, 'destination_hub_id' => $this->destination->id,
                'origin_hub_name' => $this->origin->name, 'destination_hub_name' => $this->destination->name, 'transport_mode' => 'road']]);
    }

    private function hub(string $code, string $name): LogisticsHub
    {
        return LogisticsHub::create(['sorting_center_id' => $this->company->id, 'code' => $code, 'name' => $name,
            'address' => 'Synthetic hub address', 'city_code' => '1381300000', 'pickup_city_codes' => ['1381300000'],
            'delivery_city_codes' => ['1381300000'], 'is_active' => true]);
    }

    private function staff(?LogisticsHub $hub = null): User
    {
        $user = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved', 'password' => 'Synthetic#123']);
        LogisticsHubStaff::create(['user_id' => $user->id, 'sorting_center_id' => $this->company->id,
            'logistics_hub_id' => ($hub ?? $this->origin)->id, 'is_active' => true]);
        return $user;
    }

    private function action(User $actor, string $action, LogisticsHub $hub, array $extra = [])
    {
        Sanctum::actingAs($actor);
        return $this->postJson('/api/sorting-center/hub-workspace/actions', array_merge([
            'reference' => $this->parcel->order_id, 'hub_id' => $hub->id, 'action' => $action, 'physical_confirmation' => true], $extra));
    }

    public function test_owner_creates_a_scoped_staff_login_without_creating_another_company_or_leaking_credentials(): void
    {
        Sanctum::actingAs($this->owner);
        $response = $this->postJson('/api/sorting-center/hub-staff', ['name' => 'Synthetic Staff', 'email' => 'STAFF@example.test',
            'hub_id' => $this->origin->id, 'password' => 'Synthetic#123', 'password_confirmation' => 'Synthetic#123']);
        $response->assertCreated()->assertJsonPath('data.hub.id', $this->origin->id)->assertJsonMissingPath('data.user.password');
        $user = User::where('email', 'staff@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('Synthetic#123', $user->password));
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->sortingCenter);
        $this->assertDatabaseCount('sorting_centers', 1);
        $this->assertStringNotContainsString('Synthetic#123', $response->getContent());
        $this->assertDatabaseHas('logistics_audit_events', ['actor_id' => $this->owner->id, 'action' => 'hub_staff_created']);
    }

    public function test_account_validation_rejects_existing_email_weak_passwords_and_foreign_hubs(): void
    {
        Sanctum::actingAs($this->owner);
        $data = ['name' => 'Synthetic Staff', 'email' => 'newstaff@example.test', 'hub_id' => $this->origin->id,
            'password' => 'Synthetic#123', 'password_confirmation' => 'Synthetic#123'];
        $this->postJson('/api/sorting-center/hub-staff', array_replace($data, ['email' => strtoupper($this->owner->email)]))->assertUnprocessable();
        $this->postJson('/api/sorting-center/hub-staff', array_replace($data, ['password' => 'weak', 'password_confirmation' => 'weak']))->assertUnprocessable();
        $otherOwner = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        $other = SortingCenter::create(['user_id' => $otherOwner->id, 'business_name' => 'Other', 'approval_status' => 'approved']);
        $foreign = $this->origin->replicate(); $foreign->sorting_center_id = $other->id; $foreign->save();
        $this->postJson('/api/sorting-center/hub-staff', array_replace($data, ['hub_id' => $foreign->id]))->assertNotFound();
        $this->assertDatabaseCount('logistics_hub_staff', 0);
    }

    public function test_staff_login_returns_company_and_assigned_hub_but_denies_company_endpoints(): void
    {
        $staff = $this->staff();
        $this->postJson('/api/sorting-center/login', ['email' => $staff->email, 'password' => 'Synthetic#123'])
            ->assertOk()->assertJsonPath('operator.role', 'hub_staff')->assertJsonPath('operator.hub.id', $this->origin->id)
            ->assertJsonPath('sorting_center.id', $this->company->id);
        Sanctum::actingAs($staff);
        foreach (['dashboard', 'hub-network', 'hub-staff', 'riders', 'delivery-rates', 'reports'] as $path) {
            $this->getJson('/api/sorting-center/'.$path)->assertForbidden();
        }
        $this->getJson('/api/partner/profile')->assertOk()->assertJsonPath('data.operator.role', 'hub_staff');
        $this->getJson('/api/sorting-center/hub-workspace')->assertOk()->assertJsonCount(1, 'data.hubs')->assertJsonPath('data.operating_hub.id', $this->origin->id);
    }

    public function test_web_staff_login_opens_hub_workspace_and_blocks_company_shells(): void
    {
        $staff = $this->staff();
        $this->post('/login', ['email' => $staff->email, 'password' => 'Synthetic#123'])->assertRedirect(route('sorting-center.hub-workspace'));
        $this->get('/sorting-center/hub-workspace')->assertOk()->assertSee('hub-workspace', false);
        $this->get('/sorting-center/dashboard')->assertForbidden();
        $this->get('/sorting-center/hub-staff')->assertForbidden();
    }

    public function test_scan_lookup_returns_saved_addresses_current_location_next_destination_and_only_the_permitted_action(): void
    {
        Sanctum::actingAs($this->staff());
        $this->getJson('/api/sorting-center/hub-workspace/lookup?reference=item-9001')->assertOk()
            ->assertJsonPath('data.parcel.delivery_address', $this->parcel->delivery_address)
            ->assertJsonPath('data.parcel.collection_address', $this->parcel->collection_address)
            ->assertJsonPath('data.location_label', 'With pickup Rider')->assertJsonPath('data.next_destination.name', $this->origin->name)
            ->assertJsonPath('data.action', 'receive_origin');
        $this->assertSame('PICKED_UP', $this->parcel->fresh()->status);
        $this->getJson('/api/sorting-center/hub-workspace/lookup?reference=UNKNOWN')->assertNotFound();
        $this->getJson('/api/sorting-center/hub-workspace/lookup?reference=https://example.test')->assertUnprocessable();
        $this->getJson('/api/sorting-center/hub-workspace/lookup?reference=ITEM-9001&hub_id='.$this->destination->id)->assertForbidden();
    }

    public function test_full_two_hub_staff_receiving_dispatch_and_destination_receipt_preserve_the_existing_delivery_handoff(): void
    {
        $originStaff = $this->staff(); $destinationStaff = $this->staff($this->destination);
        $this->action($originStaff, 'receive_origin', $this->origin)->assertOk()
            ->assertJsonPath('data.current_hub.id', $this->origin->id)->assertJsonPath('data.next_destination.name', $this->destination->name)
            ->assertJsonPath('data.action', 'dispatch');
        $this->action($originStaff, 'receive_origin', $this->origin)->assertConflict();
        $key = (string) Str::uuid();
        $this->action($originStaff, 'dispatch', $this->origin, ['request_key' => $key, 'transport_reference' => 'SYNTHETIC-TRANSPORT'])
            ->assertOk()->assertJsonPath('data.parcel.status', 'IN_HUB_TRANSIT')->assertJsonPath('data.action', null);
        $this->assertDatabaseCount('hub_transfers', 1);
        $this->action($originStaff, 'dispatch', $this->origin, ['request_key' => $key, 'transport_reference' => 'SYNTHETIC-TRANSPORT'])->assertConflict();
        $this->action($destinationStaff, 'receive_destination', $this->destination)->assertOk()
            ->assertJsonPath('data.parcel.status', 'AT_SORTING_CENTER')->assertJsonPath('data.current_hub.id', $this->destination->id)
            ->assertJsonPath('data.next_destination.name', $this->parcel->delivery_address)->assertJsonPath('data.action', null);
        $transfer = HubTransfer::firstOrFail();
        $this->assertSame($originStaff->id, $transfer->dispatched_by);
        $this->assertSame($destinationStaff->id, $transfer->received_by);
        $this->assertNull($this->parcel->fresh()->assigned_rider_id);
        $this->action($destinationStaff, 'receive_destination', $this->destination)->assertConflict();
    }

    public function test_staff_cannot_receive_at_the_wrong_hub_or_dispatch_before_receiving_or_bypass_physical_confirmation(): void
    {
        $staff = $this->staff();
        $this->action($staff, 'receive_origin', $this->destination)->assertForbidden();
        $this->action($staff, 'dispatch', $this->origin, ['request_key' => (string) Str::uuid(), 'transport_reference' => 'SYNTHETIC'])->assertConflict();
        $this->action($staff, 'receive_origin', $this->origin, ['physical_confirmation' => false])->assertUnprocessable();
        $this->assertSame('PICKED_UP', $this->parcel->fresh()->status);
        $this->assertDatabaseCount('hub_transfers', 0);
    }

    public function test_paused_staff_inactive_hubs_and_unavailable_company_lose_access_immediately(): void
    {
        $staff = $this->staff(); $member = LogisticsHubStaff::where('user_id', $staff->id)->firstOrFail();
        Sanctum::actingAs($staff);
        $member->update(['is_active' => false]);
        $this->getJson('/api/sorting-center/hub-workspace')->assertForbidden();
        $member->update(['is_active' => true]); $this->origin->update(['is_active' => false]);
        $this->getJson('/api/sorting-center/hub-workspace')->assertForbidden();
        $this->origin->update(['is_active' => true]); $this->company->update(['approval_status' => 'rejected']);
        $this->getJson('/api/sorting-center/hub-workspace')->assertForbidden();
    }

    public function test_owner_updates_revoke_old_staff_tokens_and_stale_hub_actions_are_rejected(): void
    {
        $staff = $this->staff(); $member = LogisticsHubStaff::where('user_id', $staff->id)->firstOrFail();
        $staff->createToken('synthetic-old-session');
        Sanctum::actingAs($this->owner);
        $this->putJson('/api/sorting-center/hub-staff/'.$member->id, ['hub_id' => $this->destination->id, 'is_active' => true])->assertOk();
        $this->assertSame(0, $staff->tokens()->count());
        $this->action($staff, 'receive_origin', $this->origin)->assertForbidden();
        $this->assertSame('PICKED_UP', $this->parcel->fresh()->status);
    }

    public function test_staff_cannot_read_other_company_or_unrelated_hub_parcels_or_create_accounts(): void
    {
        $staff = $this->staff();
        Sanctum::actingAs($staff);
        $this->postJson('/api/sorting-center/hub-staff', [])->assertForbidden();
        $third = $this->hub('THIRD', 'Other own hub');
        ParcelHubJourney::where('parcel_id', $this->parcel->id)->update(['origin_hub_id' => $third->id]);
        $this->getJson('/api/sorting-center/hub-workspace/lookup?reference=ITEM-9001')->assertNotFound();
        $this->getJson('/api/sorting-center/hub-workspace/queue')->assertOk()->assertJsonCount(0, 'data.data');
        $this->parcel->update(['sorting_center_id' => null]);
        $this->getJson('/api/sorting-center/hub-workspace/lookup?reference=ITEM-9001')->assertNotFound();
    }

    public function test_audit_failure_rolls_back_physical_receipt_and_status(): void
    {
        $audit = \Mockery::mock(LogisticsAudit::class);
        $audit->shouldReceive('record')->once()->andThrow(new \RuntimeException('Synthetic audit failure'));
        $this->app->instance(LogisticsAudit::class, $audit);
        $this->action($this->staff(), 'receive_origin', $this->origin)->assertServerError();
        $this->assertSame('PICKED_UP', $this->parcel->fresh()->status);
        $this->assertNull(ParcelHubJourney::firstOrFail()->origin_received_at);
    }

    public function test_missing_staff_migration_fails_cleanly_for_owner_management_and_existing_hub_work_remains_available(): void
    {
        Schema::drop('logistics_hub_staff');
        Sanctum::actingAs($this->owner);
        $this->getJson('/api/sorting-center/hub-staff')->assertStatus(503);
        $this->getJson('/api/sorting-center/hub-workspace?hub_id='.$this->origin->id)->assertOk();
    }
}
