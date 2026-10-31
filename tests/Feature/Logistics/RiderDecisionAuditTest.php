<?php

namespace Tests\Feature\Logistics;

use App\Models\{Rider, RiderDecisionAudit, SortingCenter, User};
use Illuminate\Support\Facades\{DB, Schema};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiderDecisionAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    public function test_successful_approval_records_actor_company_time_and_both_states_without_reason(): void
    {
        $this->freezeTime();
        [$center, $rider] = $this->fixture();
        Sanctum::actingAs($center);
        $url = '/api/sorting-center/riders/'.$rider->id.'/approve';
        $this->postJson($url, ['actor_user_id' => 9999, 'sorting_center_id' => 9999])->assertOk();
        $this->assertTrue(Schema::hasTable('rider_decision_audits'), 'Successful approval must have durable audit storage.');
        $audit = RiderDecisionAudit::sole();
        $this->assertSame($center->id, $audit->actor_user_id);
        $this->assertSame($center->name, $audit->actor_name);
        $this->assertSame($center->sortingCenter->id, $audit->sorting_center_id);
        $this->assertSame($rider->id, $audit->rider_id);
        $this->assertSame('approve', $audit->action);
        $this->assertNull($audit->reason);
        $this->assertSame(['approval_status' => 'pending', 'user_status' => 'pending', 'is_active' => false], $audit->old_state);
        $this->assertSame(['approval_status' => 'approved', 'user_status' => 'approved', 'is_active' => true], $audit->new_state);
        $this->assertTrue($audit->occurred_at->equalTo(now()));
        $this->postJson($url)->assertNotFound();
        $this->assertDatabaseCount('rider_decision_audits', 1);
    }

    public function test_rejection_audit_and_rider_notice_keep_exact_validated_reason_once(): void
    {
        [$center, $rider] = $this->fixture();
        Sanctum::actingAs($center);
        $reason = 'OR/CR photo is unreadable, please re-upload.';
        $url = '/api/sorting-center/riders/'.$rider->id.'/reject';
        $this->postJson($url, ['reason' => $reason])->assertOk();
        $audit = RiderDecisionAudit::sole();
        $this->assertSame('reject', $audit->action);
        $this->assertSame($reason, $audit->reason);
        $this->assertSame('pending', $audit->old_state['approval_status']);
        $this->assertSame('rejected', $audit->new_state['user_status']);
        $this->assertSame($reason, $rider->user->fresh()->rejection_reason);
        $this->assertDatabaseCount('rider_notifications', 1);
        $this->postJson($url, ['reason' => $reason])->assertNotFound();
        $this->assertDatabaseCount('rider_decision_audits', 1);
    }

    public function test_invalid_decisions_and_foreign_riders_do_not_create_audits(): void
    {
        [$center, $owned] = $this->fixture();
        [, $foreign] = $this->fixture();
        Sanctum::actingAs($center);
        $this->postJson('/api/sorting-center/riders/'.$owned->id.'/reject', ['reason' => 'short'])->assertUnprocessable();
        foreach (['approve', 'reject'] as $action) {
            $this->postJson('/api/sorting-center/riders/'.$foreign->id.'/'.$action, ['reason' => 'A foreign company cannot review this.'])->assertNotFound();
        }
        $this->getJson('/api/sorting-center/riders/'.$foreign->id.'/audits')->assertNotFound();
        $this->assertDatabaseCount('rider_decision_audits', 0);
        $this->assertSame('pending', $owned->fresh()->approval_status);
        $this->assertSame('pending', $foreign->fresh()->approval_status);
    }

    public function test_audit_history_is_scoped_paginated_and_keeps_actor_snapshot(): void
    {
        $this->freezeTime();
        [$center, $rider] = $this->fixture();
        foreach (range(1, 12) as $i) $this->entry($center, $rider);
        [$foreign] = $this->fixture();
        $this->entry($foreign, $rider);
        $actorName = $center->name;
        $center->update(['name' => 'Changed name']);
        Sanctum::actingAs($center);
        $url = '/api/sorting-center/riders/'.$rider->id.'/audits';
        $this->getJson($url)->assertOk()->assertJsonPath('data.total', 12)->assertJsonCount(10, 'data.data')
            ->assertJsonPath('data.data.0.actor_name', $actorName)->assertJsonPath('data.data.0.id', 12);
        $this->getJson($url.'?page=2')->assertOk()->assertJsonCount(2, 'data.data')->assertJsonPath('data.current_page', 2);
        $this->getJson($url.'?page=0')->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->getJson($url.'?page[]=1')->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->getJson('/api/sorting-center/riders/not-an-id/audits')->assertNotFound();
    }

    public function test_audit_history_requires_approved_company_authentication(): void
    {
        [$center, $rider] = $this->fixture();
        $url = '/api/sorting-center/riders/'.$rider->id.'/audits';
        $this->getJson($url)->assertUnauthorized();
        Sanctum::actingAs($rider->user);
        $this->getJson($url)->assertForbidden();
        foreach (['admin', 'buyer', 'seller', 'rider'] as $role) {
            Sanctum::actingAs(User::factory()->create(['usertype' => $role, 'status' => 'approved']));
            $this->getJson($url)->assertForbidden();
        }
        $center->update(['status' => 'pending']);
        Sanctum::actingAs($center);
        $this->getJson($url)->assertForbidden();
    }

    public function test_audit_model_and_http_access_do_not_offer_updates_or_deletes(): void
    {
        [$center, $rider] = $this->fixture();
        $audit = $this->entry($center, $rider);
        foreach (['update', 'delete'] as $action) {
            try {
                $action === 'update' ? $audit->update(['reason' => 'Changed']) : $audit->delete();
                $this->fail('Audit entries must be append-only.');
            } catch (\LogicException $error) {
                $this->assertStringContainsString('append-only', $error->getMessage());
            }
            $audit->refresh();
        }
        Sanctum::actingAs($center);
        foreach (['postJson', 'putJson', 'patchJson', 'deleteJson'] as $method) {
            $this->$method('/api/sorting-center/riders/'.$rider->id.'/audits', ['reason' => 'Changed'])->assertStatus(405);
        }
        $this->assertDatabaseCount('rider_decision_audits', 1);
        $this->assertNull($audit->fresh()->reason);
    }

    public function test_audit_storage_failure_rolls_back_decision_user_and_notifications(): void
    {
        [$center, $rider] = $this->fixture();
        // This trigger exists only in this test's guarded SQLite :memory: connection.
        DB::unprepared("CREATE TRIGGER audit_insert_failure BEFORE INSERT ON rider_decision_audits BEGIN SELECT RAISE(ABORT, 'Synthetic audit failure'); END");
        Sanctum::actingAs($center);
        foreach (['approve', 'reject'] as $action) {
            $this->postJson('/api/sorting-center/riders/'.$rider->id.'/'.$action, ['reason' => 'Unreadable document photo.'])->assertInternalServerError();
            $this->assertSame('pending', $rider->fresh()->approval_status);
            $this->assertSame('pending', $rider->user->fresh()->status);
            $this->assertDatabaseCount('rider_decision_audits', 0);
            $this->assertDatabaseCount('rider_notifications', 0);
        }
    }

    public function test_pending_mysql_migration_returns_clear_unavailable_without_mutation(): void
    {
        [$center, $rider] = $this->fixture();
        Schema::shouldReceive('hasTable')->with('rider_decision_audits')->andReturn(false);
        Schema::shouldReceive('hasTable')->with('logistics_hub_staff')->andReturn(true);
        Sanctum::actingAs($center);
        foreach (['approve', 'reject'] as $action) {
            $this->postJson('/api/sorting-center/riders/'.$rider->id.'/'.$action, ['reason' => 'Unreadable document photo.'])
                ->assertStatus(503)->assertJsonPath('message', 'Rider decision auditing is unavailable. Contact your administrator.');
        }
        $this->getJson('/api/sorting-center/riders/'.$rider->id.'/audits')->assertStatus(503);
        $this->assertSame('pending', $rider->fresh()->approval_status);
        $this->assertSame('pending', $rider->user->fresh()->status);
        $this->assertDatabaseCount('rider_decision_audits', 0);
        $this->assertDatabaseCount('rider_notifications', 0);
    }

    public function test_outer_rollback_removes_audit_and_never_publishes_notice(): void
    {
        [$center, $rider] = $this->fixture();
        Sanctum::actingAs($center);
        DB::beginTransaction();
        try {
            $this->postJson('/api/sorting-center/riders/'.$rider->id.'/approve')->assertOk();
            $this->assertDatabaseCount('rider_decision_audits', 1);
            $this->assertDatabaseCount('rider_notifications', 0);
        } finally {
            DB::rollBack();
        }
        $this->assertSame('pending', $rider->fresh()->approval_status);
        $this->assertDatabaseCount('rider_decision_audits', 0);
        $this->assertDatabaseCount('rider_notifications', 0);
    }

    private function entry(User $center, Rider $rider): RiderDecisionAudit
    {
        return RiderDecisionAudit::create(['sorting_center_id' => $center->sortingCenter->id, 'rider_id' => $rider->id,
            'actor_user_id' => $center->id, 'actor_name' => $center->name, 'action' => 'approve',
            'old_state' => ['approval_status' => 'pending', 'user_status' => 'pending', 'is_active' => false],
            'new_state' => ['approval_status' => 'approved', 'user_status' => 'approved', 'is_active' => true], 'occurred_at' => now()]);
    }

    private function fixture(): array
    {
        $center = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        SortingCenter::create(['user_id' => $center->id, 'business_name' => 'Fixture', 'approval_status' => 'approved']);
        $user = User::factory()->create(['usertype' => 'rider', 'status' => 'pending']);
        $rider = Rider::create(['user_id' => $user->id, 'sorting_center_id' => $center->sortingCenter->id, 'name' => 'Fixture Rider',
            'contact' => '09170000000', 'vehicle_type' => 'bicycle', 'plate_number' => '', 'approval_status' => 'pending', 'is_active' => false]);
        return [$center, $rider];
    }
}
