<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityRemediationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inertia_shared_user_props_are_filtered(): void
    {
        $user = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Test Seller',
            'bank_name' => 'Test Bank',
            'tin' => '999-999-999',
            'id_path' => 'private/id.pdf',
            'business_permit_path' => 'private/permit.pdf',
            'pending_email_otp' => '123456',
            'pending_phone_otp' => '654321',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
        $page = $response->viewData('page');
        $this->assertIsArray($page);
        $sharedUser = data_get($page, 'props.auth.user');
        $this->assertIsArray($sharedUser);

        $this->assertArrayNotHasKey('bank_account_number', $sharedUser);
        $this->assertArrayNotHasKey('bank_account_name', $sharedUser);
        $this->assertArrayNotHasKey('bank_name', $sharedUser);
        $this->assertArrayNotHasKey('tin', $sharedUser);
        $this->assertArrayNotHasKey('id_path', $sharedUser);
        $this->assertArrayNotHasKey('business_permit_path', $sharedUser);
        $this->assertArrayNotHasKey('pending_email_otp', $sharedUser);
        $this->assertArrayNotHasKey('pending_phone_otp', $sharedUser);
    }

    public function test_otp_is_hashed_and_verified_securely(): void
    {
        $user = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'unverified',
            'email' => 'otp-test@example.com',
        ]);

        $otp = $user->generateAndSendOtp();

        // Verify otp_code in database is hashed (not plaintext)
        $user->refresh();
        $this->assertNotEquals($otp, $user->otp_code);
        $this->assertTrue(Hash::check($otp, $user->otp_code));

        // Test verification succeeds with correct OTP
        $response = $this->postJson('/verify-otp', [
            'email' => $user->email,
            'otp' => $otp,
        ]);

        $response->assertStatus(200);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_api_otp_verification_accepts_the_hashed_code(): void
    {
        $user = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'unverified',
            'email_verified_at' => null,
            'email' => 'otp-api-test@example.com',
        ]);
        $otp = $user->generateAndSendOtp();

        $this->postJson('/api/buyer/verify-otp', [
            'email' => $user->email,
            'otp' => $otp,
        ])->assertOk();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_legacy_plaintext_otp_remains_compatible_until_successful_verification(): void
    {
        $user = User::factory()->unverified()->create([
            'usertype' => 'buyer',
            'status' => 'unverified',
            'otp_code' => '112233',
            'otp_expires_at' => now()->addMinutes(5),
        ]);

        $this->postJson('/api/buyer/verify-otp', [
            'email' => $user->email,
            'otp' => '112233',
        ])->assertOk();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_architecture_documentation_routes_require_admin(): void
    {
        // Guest should be denied
        $response = $this->get('/docs/architecture');
        $response->assertRedirect();

        // Buyer should be denied
        $buyer = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $response = $this->actingAs($buyer)->get('/docs/architecture');
        $response->assertStatus(403);

        $route = Route::getRoutes()->getByName('docs.architecture');
        $this->assertNotNull($route);
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('admin', $route->gatherMiddleware());

        // Admin should be granted access and receive the file
        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']);
        $adminResponse = $this->actingAs($admin)->get('/docs/architecture');
        $adminResponse->assertStatus(200);

        $markdownResponse = $this->actingAs($admin)->get('/docs/architecture/markdown');
        $markdownResponse->assertStatus(200);
    }

    public function test_footer_url_validation_rejects_unsafe_protocols(): void
    {
        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']);

        $response = $this->actingAs($admin)->post('/admin/settings/footer', [
            'sections' => [
                [
                    'title' => 'Support',
                    'links' => [
                        ['label' => 'Malicious', 'url' => 'javascript:alert(1)'],
                    ],
                ],
            ],
        ]);

        $response->assertSessionHasErrors('sections.0.links.0.url');
    }

    public function test_pending_rider_cannot_access_approved_rider_api(): void
    {
        $rider = User::factory()->create(['usertype' => 'rider', 'status' => 'pending']);

        $this->actingAs($rider, 'sanctum')
            ->getJson('/api/rider/earnings')
            ->assertForbidden();
    }

    public function test_test_environment_uses_encrypted_sessions(): void
    {
        $this->assertTrue(config('session.encrypt'));
    }
}
