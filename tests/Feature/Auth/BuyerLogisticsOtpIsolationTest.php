<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BuyerLogisticsOtpIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_challenges_cannot_verify_or_reissue_codes_for_another_role(): void
    {
        Notification::fake();
        foreach (['sorting_center', 'rider', 'admin'] as $index => $role) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($index + 1)]);
            $user = User::factory()->unverified()->create(['usertype' => $role, 'status' => 'unverified',
                'otp_code' => '123456', 'otp_expires_at' => now()->addMinutes(5)]);
            $this->postJson('/verify-otp/resend', ['email' => $user->email])->assertUnprocessable();
            $this->postJson('/verify-otp', ['email' => $user->email, 'otp' => '123456'])->assertUnprocessable();
            $this->assertFalse($user->fresh()->hasVerifiedEmail());
            $this->assertSame('123456', $user->fresh()->otp_code);
            $this->assertSame('unverified', $user->fresh()->status);
        }
        Notification::assertNothingSent();
    }

    public function test_signed_in_logistics_cannot_use_buyer_verification_routes(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['usertype' => 'sorting_center', 'status' => 'unverified']);
        $this->actingAs($user)->get('/verify-otp')->assertForbidden();
        $this->postJson('/verify-otp/resend')->assertForbidden();
        $this->postJson('/verify-otp', ['otp' => '123456'])->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        Notification::assertNothingSent();
    }
}
