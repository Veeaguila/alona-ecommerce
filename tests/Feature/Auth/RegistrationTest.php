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

    public function test_tc_buy_004_names_with_numbers_or_symbols_are_rejected(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        // 1. First name with numbers
        $response1 = $this->postJson('/register', [
            'first_name' => 'Juan Miguel123',
            'last_name' => 'Dela Cruz',
            'birthday' => '1999-05-15',
            'email' => 'juan123@example.com',
            'contact_no' => '09171234567',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ]);
        $response1->assertStatus(422);
        $response1->assertJsonValidationErrors([
            'first_name' => 'First name can only contain letters, spaces, hyphens, apostrophes, and periods.',
        ]);

        // 2. Last name with symbols
        $response2 = $this->postJson('/register', [
            'first_name' => 'Juan Miguel',
            'last_name' => 'Dela Cruz@#',
            'birthday' => '1999-05-15',
            'email' => 'delacruz_sym@example.com',
            'contact_no' => '09171234567',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ]);
        $response2->assertStatus(422);
        $response2->assertJsonValidationErrors([
            'last_name' => 'Last name can only contain letters, spaces, hyphens, apostrophes, and periods.',
        ]);

        // 3. Middle name with numbers/symbols
        $response3 = $this->postJson('/register', [
            'first_name' => 'Juan Miguel',
            'middle_name' => 'Santos123',
            'last_name' => 'Dela Cruz',
            'birthday' => '1999-05-15',
            'email' => 'santos123@example.com',
            'contact_no' => '09171234567',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ]);
        $response3->assertStatus(422);
        $response3->assertJsonValidationErrors([
            'middle_name' => 'Middle name can only contain letters, spaces, hyphens, apostrophes, and periods.',
        ]);

        // 4. Suffix with invalid characters
        $response4 = $this->postJson('/register', [
            'first_name' => 'Juan Miguel',
            'last_name' => 'Dela Cruz',
            'suffix' => 'Jr!#',
            'birthday' => '1999-05-15',
            'email' => 'suffix_invalid@example.com',
            'contact_no' => '09171234567',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ]);
        $response4->assertStatus(422);
        $response4->assertJsonValidationErrors([
            'suffix' => 'Suffix can only contain letters, spaces, hyphens, apostrophes, and periods.',
        ]);
    }

    public function test_tc_buy_004_valid_names_with_special_allowed_characters_and_enye_are_accepted(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $response = $this->postJson('/register', [
            'first_name' => 'Niño Mary-Ann',
            'middle_name' => "D'Angelo",
            'last_name' => 'Peña-Reyes',
            'suffix' => 'Jr.',
            'birthday' => '1999-05-15',
            'email' => 'nino.maryann@example.com',
            'contact_no' => '09171234567',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'email' => 'nino.maryann@example.com',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'nino.maryann@example.com',
            'first_name' => 'Niño Mary-Ann',
            'middle_name' => "D'Angelo",
            'last_name' => 'Peña-Reyes',
            'suffix' => 'Jr.',
        ]);
    }

    public function test_tc_buy_006_name_length_at_the_limits_rejected(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        // 1. 1-character first name
        $response1 = $this->postJson('/register', [
            'first_name' => 'J',
            'last_name' => 'Dela Cruz',
            'birthday' => '1999-05-15',
            'email' => 'j_delacruz@example.com',
            'contact_no' => '09171234567',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ]);
        $response1->assertStatus(422);
        $response1->assertJsonValidationErrors([
            'first_name' => 'First name must be at least 2 characters.',
        ]);

        // 2. 1-character last name
        $response2 = $this->postJson('/register', [
            'first_name' => 'Juan',
            'last_name' => 'D',
            'birthday' => '1999-05-15',
            'email' => 'juan_d@example.com',
            'contact_no' => '09171234567',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ]);
        $response2->assertStatus(422);
        $response2->assertJsonValidationErrors([
            'last_name' => 'Last name must be at least 2 characters.',
        ]);

        // 3. 51-character first name
        $response3 = $this->postJson('/register', [
            'first_name' => str_repeat('A', 51),
            'last_name' => 'Dela Cruz',
            'birthday' => '1999-05-15',
            'email' => 'longfirst@example.com',
            'contact_no' => '09171234567',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ]);
        $response3->assertStatus(422);
        $response3->assertJsonValidationErrors([
            'first_name' => 'First name may not be greater than 50 characters.',
        ]);

        // 4. 51-character last name
        $response4 = $this->postJson('/register', [
            'first_name' => 'Juan',
            'last_name' => str_repeat('B', 51),
            'birthday' => '1999-05-15',
            'email' => 'longlast@example.com',
            'contact_no' => '09171234567',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ]);
        $response4->assertStatus(422);
        $response4->assertJsonValidationErrors([
            'last_name' => 'Last name may not be greater than 50 characters.',
        ]);
    }

    public function test_tc_buy_006_name_length_at_the_limits_accepted(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        // 2 characters each
        $response1 = $this->postJson('/register', [
            'first_name' => 'Jo',
            'last_name' => 'Li',
            'birthday' => '1999-05-15',
            'email' => 'jo.li@example.com',
            'contact_no' => '09171234567',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ]);
        $response1->assertOk();
        $this->assertDatabaseHas('users', ['email' => 'jo.li@example.com', 'first_name' => 'Jo', 'last_name' => 'Li']);

        // 50 characters each
        $name50A = str_repeat('A', 50);
        $name50B = str_repeat('B', 50);
        $response2 = $this->postJson('/register', [
            'first_name' => $name50A,
            'last_name' => $name50B,
            'birthday' => '1999-05-15',
            'email' => 'fifty.chars@example.com',
            'contact_no' => '09171234567',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ]);
        $response2->assertOk();
        $this->assertDatabaseHas('users', ['email' => 'fifty.chars@example.com', 'first_name' => $name50A, 'last_name' => $name50B]);
    }

    public function test_tc_buy_007_underage_buyer_is_rejected(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        // 17-year-old registration (18 years minus 1 day)
        $underageBirthday = now()->subYears(18)->addDay()->toDateString();

        $response = $this->postJson('/register', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'birthday' => $underageBirthday,
            'email' => 'underage@example.com',
            'contact_no' => '09171234567',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'birthday' => 'You must be at least 18 years old to register.',
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'underage@example.com']);
    }

    public function test_tc_buy_007_age_18_limit_is_accepted(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        // Exactly 18 years old today
        $exact18Birthday = now()->subYears(18)->toDateString();

        $response = $this->postJson('/register', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'birthday' => $exact18Birthday,
            'email' => 'eighteen@example.com',
            'contact_no' => '09171234567',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'email' => 'eighteen@example.com',
            'age' => 18,
        ]);
        $user = \App\Models\User::where('email', 'eighteen@example.com')->firstOrFail();
        $this->assertEquals($exact18Birthday, \Illuminate\Support\Carbon::parse($user->birthday)->toDateString());
    }
}
