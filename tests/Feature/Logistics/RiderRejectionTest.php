<?php

namespace Tests\Feature\Logistics;

use App\Models\{Rider, RiderNotification, SortingCenter, User};
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RiderRejectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Exercise real afterCommit delivery without connecting to an existing database.
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    public static function invalidReasons(): array
    {
        return [
            'missing' => [[]],
            'empty' => [['reason' => '']],
            'whitespace' => [['reason' => '          ']],
            'nine characters' => [['reason' => '123456789']],
            '301 characters' => [['reason' => str_repeat('x', 301)]],
            'non-string' => [['reason' => 1234567890]],
            'array' => [['reason' => ['Invalid document']]],
        ];
    }

    // LOG-RIDER-002 approved API fix; full UI/Online workflow remains pending.
    #[DataProvider('invalidReasons')]
    public function test_invalid_reason_does_not_reject_or_publish_a_notification(array $payload): void
    {
        [$center, $rider] = $this->fixture();
        $before = $rider->fresh()->getAttributes();
        $userBefore = $rider->user->getAttributes();
        Sanctum::actingAs($center);
        $this->postJson('/api/sorting-center/riders/'.$rider->id.'/reject', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->assertSame($before, $rider->fresh()->getAttributes());
        $this->assertSame($userBefore, $rider->user->fresh()->getAttributes());
        $this->assertDatabaseCount('rider_notifications', 0);
    }

    public static function validReasons(): array
    {
        return [
            'ten characters' => ['1234567890'],
            '300 characters' => [str_repeat('x', 300)],
            'workbook reason' => ['OR/CR photo is unreadable, please re-upload.'],
            'Unicode punctuation' => ['The rider’s license photo needs a clearer replacement.'],
        ];
    }

    #[DataProvider('validReasons')]
    public function test_reason_is_stored_and_included_exactly_once_in_the_own_rejection_feed(string $reason): void
    {
        [$center, $rider] = $this->fixture();
        [, $otherRider] = $this->fixture();
        Sanctum::actingAs($center);
        $url = '/api/sorting-center/riders/'.$rider->id.'/reject';
        $this->postJson($url, ['reason' => $reason])->assertOk()->assertJsonPath('data.approval_status', 'rejected');
        $this->assertDatabaseHas('users', ['id' => $rider->user_id, 'status' => 'rejected', 'rejection_reason' => $reason]);
        $this->assertDatabaseCount('rider_notifications', 1);
        $notice = RiderNotification::firstOrFail();
        $this->assertSame($rider->user_id, $notice->user_id);
        $this->assertStringContainsString($reason, $notice->body);
        $this->assertSame($reason, $notice->data['rejection_reason']);
        $this->postJson($url, ['reason' => $reason])->assertNotFound();
        $this->assertDatabaseCount('rider_notifications', 1);

        Sanctum::actingAs($otherRider->user);
        $this->getJson('/api/rider/notifications')->assertOk()->assertJsonCount(0, 'data');
        Sanctum::actingAs($rider->user->fresh());
        $this->getJson('/api/rider/notifications')->assertOk()->assertJsonPath('data.0.data.rejection_reason', $reason)
            ->assertJsonPath('data.0.body', $notice->body);
        $this->getJson('/api/rider/deliveries')->assertForbidden();
    }

    public function test_other_company_cannot_reject_or_replace_the_reason(): void
    {
        [$center] = $this->fixture();
        [, $foreign] = $this->fixture();
        Sanctum::actingAs($center);
        foreach ([[], ['reason' => 'A different company cannot reject this rider.']] as $payload) {
            $this->postJson('/api/sorting-center/riders/'.$foreign->id.'/reject', $payload)->assertNotFound();
        }
        $this->assertSame('pending', $foreign->fresh()->approval_status);
        $this->assertNull($foreign->user->fresh()->rejection_reason);
        $this->assertDatabaseCount('rider_notifications', 0);
    }

    public function test_outer_rollback_reverts_reason_status_and_notice(): void
    {
        [$center, $rider] = $this->fixture();
        Sanctum::actingAs($center);
        DB::beginTransaction();
        try {
            $this->postJson('/api/sorting-center/riders/'.$rider->id.'/reject', ['reason' => 'The document is unreadable.'])->assertOk();
            $this->assertDatabaseCount('rider_notifications', 0);
        } finally {
            DB::rollBack();
        }
        $this->assertSame('pending', $rider->fresh()->approval_status);
        $this->assertSame('pending', $rider->user->fresh()->status);
        $this->assertNull($rider->user->fresh()->rejection_reason);
        $this->assertDatabaseCount('rider_notifications', 0);
    }

    public function test_same_second_reason_correction_has_a_distinct_notice(): void
    {
        $this->freezeTime();
        [$center, $rider] = $this->fixture();
        Sanctum::actingAs($center);
        $this->postJson('/api/sorting-center/riders/'.$rider->id.'/reject', ['reason' => 'The first reason is unreadable.'])->assertOk();
        $corrected = 'The OR/CR document is actually expired.';
        $rider->user->fresh()->update(['rejection_reason' => $corrected]);
        $this->assertDatabaseCount('rider_notifications', 2);
        $this->assertSame(1, RiderNotification::where('body', 'like', '%'.$corrected.'%')->count());
    }

    private function fixture(): array
    {
        $center = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        $profile = SortingCenter::create(['user_id' => $center->id, 'business_name' => 'Synthetic Center', 'approval_status' => 'approved']);
        $user = User::factory()->create(['usertype' => 'rider', 'status' => 'pending']);
        $rider = Rider::create(['user_id' => $user->id, 'sorting_center_id' => $profile->id, 'name' => 'Synthetic Rider',
            'contact' => '09170000000', 'vehicle_type' => 'bicycle', 'plate_number' => '', 'approval_status' => 'pending', 'is_active' => false]);
        return [$center, $rider];
    }
}
