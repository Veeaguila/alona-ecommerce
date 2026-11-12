<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\Support\OrderJourney;

class BuyerMobileAuthSecurityTest extends OrderJourney
{
    public function test_already_verified_account_cannot_exchange_any_otp_for_a_login_token(): void
    {
        $this->postJson('/api/buyer/verify-otp', ['email' => $this->buyer->email, 'otp' => '000000'])->assertUnprocessable()->assertJsonMissingPath('token');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_verification_code_is_validated_consumed_once_and_never_reactivates_restricted_accounts(): void
    {
        $this->buyer->forceFill(['email_verified_at' => null, 'status' => 'unverified', 'otp_code' => '123456', 'otp_expires_at' => now()->addMinutes(5)])->save();
        $this->postJson('/api/buyer/verify-otp', ['email' => $this->buyer->email, 'otp' => '000000'])->assertUnprocessable();
        $this->postJson('/api/buyer/verify-otp', ['email' => $this->buyer->email, 'otp' => '123456'])->assertOk()->assertJsonStructure(['token']);
        $this->postJson('/api/buyer/verify-otp', ['email' => $this->buyer->email, 'otp' => '123456'])->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->buyer->forceFill(['email_verified_at' => null, 'status' => 'suspended', 'otp_code' => '123456', 'otp_expires_at' => now()->addMinutes(5)])->save();
        $this->postJson('/api/buyer/verify-otp', ['email' => $this->buyer->email, 'otp' => '123456'])->assertForbidden();
        $this->assertSame('suspended', $this->buyer->fresh()->status);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_expired_code_and_other_role_cannot_receive_buyer_tokens(): void
    {
        $this->buyer->forceFill(['email_verified_at' => null, 'status' => 'unverified', 'otp_code' => '123456', 'otp_expires_at' => now()->subSecond()])->save();
        $this->postJson('/api/buyer/verify-otp', ['email' => $this->buyer->email, 'otp' => '123456'])->assertUnprocessable();
        $this->postJson('/api/buyer/verify-otp', ['email' => $this->seller->email, 'otp' => '123456'])->assertNotFound();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_google_email_and_client_identity_claims_cannot_create_or_take_over_accounts(): void
    {
        foreach ([$this->buyer->email, 'synthetic-new@example.invalid'] as $email) {
            $this->postJson('/api/buyer/google-login', ['email' => $email, 'google_id' => 'invented-by-client', 'id_token' => 'unverified-token'])
                ->assertStatus(503)->assertJsonMissingPath('token');
        }
        $this->assertNull($this->buyer->fresh()->google_id);
        $this->assertSame('approved', $this->buyer->fresh()->status);
        $this->assertDatabaseMissing('users', ['email' => 'synthetic-new@example.invalid']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_resend_does_not_generate_codes_for_verified_or_restricted_accounts(): void
    {
        $this->postJson('/api/buyer/resend-otp', ['email' => $this->buyer->email])->assertUnprocessable();
        $this->buyer->forceFill(['email_verified_at' => null, 'status' => 'suspended'])->save();
        $this->postJson('/api/buyer/resend-otp', ['email' => $this->buyer->email])->assertForbidden();
        $this->assertNull($this->buyer->fresh()->otp_code);
    }
}
