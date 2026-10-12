<?php

namespace Tests\Feature;

use App\Models\SortingCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ApplicationDecisionNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_failure_does_not_fail_logistics_approval(): void
    {
        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']);
        $user = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'pending']);
        $center = SortingCenter::create(['user_id' => $user->id, 'business_name' => 'Review Center', 'approval_status' => 'pending']);
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('Simulated mail outage'));
        $this->actingAs($admin)->patch(route('admin.applications.approve', $user))->assertRedirect();
        $this->assertSame('approved', $user->fresh()->status);
        $this->assertSame('approved', $center->fresh()->approval_status);
    }

    public function test_mail_failure_does_not_fail_logistics_rejection(): void
    {
        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']);
        $user = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'pending']);
        $center = SortingCenter::create(['user_id' => $user->id, 'business_name' => 'Review Center', 'approval_status' => 'pending']);
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('Simulated mail outage'));
        $this->actingAs($admin)->patch(route('admin.applications.reject', $user), ['reason' => 'Missing documentation'])->assertRedirect();
        $this->assertSame('rejected', $user->fresh()->status);
        $this->assertSame('Missing documentation', $user->fresh()->rejection_reason);
        $this->assertSame('rejected', $center->fresh()->approval_status);
    }

    public function test_web_logout_revokes_expired_portal_token_and_ends_session(): void
    {
        $user = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        $expired = $user->createToken('logistics-web', ['*'], now()->subMinute());
        $other = $user->createToken('another-session');
        $this->actingAs($user)->withSession(['logistics.web_token' => $expired->plainTextToken])->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $expired->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $other->accessToken->id]);
    }
}
