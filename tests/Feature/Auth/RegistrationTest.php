<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertRedirect('/?auth=register-buyer&onboarding=1');
    }

    public function test_new_users_can_register(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $response = $this->post('/register', [
            'last_name' => 'User',
            'first_name' => 'Test',
            'middle_initial' => 'T',
            'sex' => 'Other',
            'email' => 'test@example.com',
            'contact_no' => '09123456789',
            'birthday' => '2000-01-01',
            'age' => 26,
            'address_group' => 'metro-manila',
            'region_code' => '1300000000',
            'province_code' => '',
            'city_code' => '1381300000',
            'barangay_code' => '1381300001',
            'province' => 'Cebu',
            'municipality' => 'Cebu City',
            'barangay' => 'Barangay Central',
            'street_address' => '1 Test Street',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => true,
        ]);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'municipality' => 'Quezon City', 'barangay' => 'Alicia', 'status' => 'unverified']);
        $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'province' => null]);
        $this->assertGuest();
        $response->assertRedirect(route('verification.otp', absolute: false));
    }

    public function test_buyer_can_register_with_test_sheet_fields_only(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $response = $this->post('/register', [
            'first_name' => 'Juan Miguel',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'suffix' => 'Jr.',
            'birthday' => '1999-05-15',
            'email' => 'juanmiguel.delacruz@example.com',
            'contact_no' => '09171234567',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('verification.otp', absolute: false));
        $this->assertDatabaseHas('users', [
            'email' => 'juanmiguel.delacruz@example.com',
            'name' => 'Juan Miguel Santos Dela Cruz Jr.',
            'first_name' => 'Juan Miguel',
            'middle_name' => 'Santos',
            'middle_initial' => 'S',
            'last_name' => 'Dela Cruz',
            'suffix' => 'Jr.',
            'contact_no' => '09171234567',
            'usertype' => 'buyer',
            'status' => 'unverified',
        ]);

        $user = \App\Models\User::where('email', 'juanmiguel.delacruz@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $user->otp_code);
        $this->assertNotNull($user->otp_expires_at);

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $user,
            \App\Notifications\BuyerOtpVerificationNotification::class
        );
    }

    public function test_tc_buy_021_register_without_accepting_terms_and_privacy_policy_is_blocked(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $response = $this->post('/register', [
            'first_name' => 'Juan Miguel',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'suffix' => 'Jr.',
            'birthday' => '1999-05-15',
            'email' => 'juanmiguel.noterms@example.com',
            'contact_no' => '09171234567',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            // 'terms' omitted or false
        ]);

        $response->assertSessionHasErrors(['terms' => 'You must agree to the Terms and Privacy Policy.']);
        $this->assertDatabaseMissing('users', ['email' => 'juanmiguel.noterms@example.com']);
        \Illuminate\Support\Facades\Notification::assertNothingSent();
    }

    public function test_seller_registration_can_be_created_with_pending_review(): void
    {
        Storage::fake('local');
        $response = $this->post('/register/seller', [
            'last_name' => 'Seller',
            'first_name' => 'Test',
            'middle_initial' => 'S',
            'sex' => 'Female',
            'email' => 'seller@example.com',
            'contact_no' => '09123456789',
            'birthday' => '1995-02-14',
            'age' => 31,
            'address_group' => 'metro-manila',
            'region_code' => '1300000000',
            'province_code' => '',
            'city_code' => '1381300000',
            'barangay_code' => '1381300001',
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Barangay 1',
            'street_address' => '10 Seller Street',
            'store_name' => 'Seller Test Store',
            'store_description' => 'Handmade items for everyday life.',
            'bank_name' => 'BDO',
            'bank_account_name' => 'Test Seller',
            'bank_account_number' => '1234567890',
            'line_of_business' => 'Handmade goods',
            'id' => UploadedFile::fake()->create('id.pdf', 5, 'application/pdf'),
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 5, 'application/pdf'),
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'seller@example.com',
            'usertype' => 'seller',
            'status' => 'pending',
            'store_name' => 'Seller Test Store',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login', absolute: false));
    }

    public function test_buyer_can_register_and_verify_via_modal_json_flow(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        // 1. Submit registration via JSON (AuthModal flow)
        $registerResponse = $this->postJson('/register', [
            'first_name' => 'Maria Clara',
            'last_name' => 'De Los Santos',
            'birthday' => '2001-08-20',
            'email' => 'mariaclara@example.com',
            'contact_no' => '09181234567',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ]);

        $registerResponse->assertOk();
        $registerResponse->assertJson([
            'status' => 'success',
            'email' => 'mariaclara@example.com',
        ]);

        $user = \App\Models\User::where('email', 'mariaclara@example.com')->firstOrFail();
        $this->assertEquals('unverified', $user->status);
        $this->assertNotNull($user->otp_code);

        // 2. Resend OTP via modal JSON
        $resendResponse = $this->postJson('/verify-otp/resend', [
            'email' => 'mariaclara@example.com',
        ]);
        $resendResponse->assertOk();
        $resendResponse->assertJson(['status' => 'success']);

        $user->refresh();
        $otp = $user->otp_code;

        // 3. Verify OTP via modal JSON
        $verifyResponse = $this->postJson('/verify-otp', [
            'email' => 'mariaclara@example.com',
            'otp' => $otp,
        ]);

        $verifyResponse->assertOk();
        $verifyResponse->assertJson([
            'status' => 'success',
            'redirect' => route('buyer.dashboard'),
        ]);

        $user->refresh();
        $this->assertEquals('approved', $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_buyer_registration_rejects_duplicate_email_with_custom_alert_message(): void
    {
        \App\Models\User::factory()->create([
            'email' => 'existingbuyer@example.com',
        ]);

        $response = $this->postJson('/register', [
            'first_name' => 'Maria Clara',
            'last_name' => 'De Los Santos',
            'birthday' => '2001-08-20',
            'email' => 'EXISTINGBUYER@EXAMPLE.COM',
            'contact_no' => '09181234567',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'email' => 'This email address is already registered. Please log in instead or use another email.',
        ]);
    }
}
