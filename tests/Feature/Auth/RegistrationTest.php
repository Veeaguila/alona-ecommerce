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
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
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
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
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
            'contact_no' => '09170000050',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ]);
        $response2->assertOk();
        $this->assertDatabaseHas('users', ['email' => 'fifty.chars@example.com', 'first_name' => $name50A, 'last_name' => $name50B, 'contact_no' => '09170000050']);
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

    public function test_tc_buy_009_email_with_missing_tld_or_invalid_format_is_rejected(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $invalidEmails = [
            'juanmiguel.delacruz@example',
            'plainaddress',
            '@missingusername.com',
            'user@.com.my',
        ];

        foreach ($invalidEmails as $invalidEmail) {
            $response = $this->postJson('/register', [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'birthday' => '1999-05-15',
                'email' => $invalidEmail,
                'contact_no' => '09171234567',
                'password' => 'Mamimili@2026',
                'password_confirmation' => 'Mamimili@2026',
                'terms' => true,
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['email']);
        }
    }

    public function test_tc_buy_012_email_with_spaces_before_and_after_is_trimmed_and_saved(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $response = $this->postJson('/register', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'birthday' => '1999-05-15',
            'email' => '   buyer.test01@example.com   ',
            'contact_no' => '09171234567',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'email' => 'buyer.test01@example.com',
        ]);
        $this->assertDatabaseMissing('users', [
            'email' => '   buyer.test01@example.com   ',
        ]);
    }

    public function test_tc_buy_013_mobile_number_in_an_invalid_format_is_rejected(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $invalidNumbers = [
            '0917ABC4567',
            '0917-123-456',
            '12345678901',
            '02-8123-4567',
        ];

        foreach ($invalidNumbers as $invalidNumber) {
            $response = $this->postJson('/register', [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'birthday' => '1999-05-15',
                'email' => 'valid_format_' . uniqid() . '@example.com',
                'contact_no' => $invalidNumber,
                'password' => 'Mamimili@2026',
                'password_confirmation' => 'Mamimili@2026',
                'terms' => true,
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['contact_no']);
        }
    }

    public function test_tc_buy_014_mobile_number_length_at_the_limits(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        // 10 digits
        $response1 = $this->postJson('/register', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'birthday' => '1999-05-15',
            'email' => 'ten_digits@example.com',
            'contact_no' => '0917123456',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ]);
        $response1->assertStatus(422);
        $response1->assertJsonValidationErrors(['contact_no']);

        // 12 digits
        $response2 = $this->postJson('/register', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'birthday' => '1999-05-15',
            'email' => 'twelve_digits@example.com',
            'contact_no' => '091712345678',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ]);
        $response2->assertStatus(422);
        $response2->assertJsonValidationErrors(['contact_no']);

        // 11 digits (accepted)
        $response3 = $this->postJson('/register', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'birthday' => '1999-05-15',
            'email' => 'eleven_digits@example.com',
            'contact_no' => '09170000001',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ]);
        $response3->assertOk();
        $this->assertDatabaseHas('users', ['email' => 'eleven_digits@example.com', 'contact_no' => '09170000001']);
    }

    public function test_tc_buy_015_duplicate_mobile_number_is_rejected(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        \App\Models\User::factory()->create([
            'email' => 'existing_phone_user@example.com',
            'contact_no' => '09171234567',
        ]);

        $response = $this->postJson('/register', [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'birthday' => '1999-05-15',
            'email' => 'new_user_same_phone@example.com',
            'contact_no' => '09171234567',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'contact_no' => 'This mobile number is already registered. Please use another mobile number.',
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'new_user_same_phone@example.com']);
    }

    public function test_tc_buy_016_registered_number_entered_in_plus_63_format_is_normalized_and_rejected(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        \App\Models\User::factory()->create([
            'email' => 'user_with_09@example.com',
            'contact_no' => '09171234567',
        ]);

        // Trying to register with +639171234567 (same number in +63 format)
        $response = $this->postJson('/register', [
            'first_name' => 'Pedro',
            'last_name' => 'Penduko',
            'birthday' => '1999-05-15',
            'email' => 'pedro@example.com',
            'contact_no' => '+639171234567',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'contact_no' => 'This mobile number is already registered. Please use another mobile number.',
        ]);

        // Registering a new number in +63 format normalizes and saves as 09...
        $response2 = $this->postJson('/register', [
            'first_name' => 'Juana',
            'last_name' => 'Change',
            'birthday' => '1999-05-15',
            'email' => 'juana@example.com',
            'contact_no' => '+639170000002',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ]);

        $response2->assertOk();
        $this->assertDatabaseHas('users', [
            'email' => 'juana@example.com',
            'contact_no' => '09170000002',
        ]);
    }

    public function test_tc_buy_018_password_missing_required_character_type(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $invalidPasswords = [
            'mamimili@2026', // missing uppercase
            'MAMIMILI@2026', // missing lowercase
            'Mamimili@abcd', // missing number
            'Mamimili2026',  // missing symbol
        ];

        foreach ($invalidPasswords as $idx => $invalidPassword) {
            $response = $this->postJson('/register', [
                'first_name' => 'Juan',
                'last_name' => 'Cruz',
                'birthday' => '1995-01-01',
                'email' => "weak{$idx}@example.com",
                'contact_no' => sprintf('091800000%02d', $idx),
                'password' => $invalidPassword,
                'password_confirmation' => $invalidPassword,
                'terms' => true,
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['password']);
            $this->assertDatabaseMissing('users', ['email' => "weak{$idx}@example.com"]);
        }

        // Compliant password with uppercase, lowercase, number, and symbol passes
        $validResponse = $this->postJson('/register', [
            'first_name' => 'Juan',
            'last_name' => 'Cruz',
            'birthday' => '1995-01-01',
            'email' => 'strong@example.com',
            'contact_no' => '09180000099',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ]);

        $validResponse->assertOk();
        $this->assertDatabaseHas('users', ['email' => 'strong@example.com']);
    }

    public function test_tc_buy_023_verify_with_correct_otp_approves_buyer_and_redirects_to_dashboard(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $user = \App\Models\User::factory()->unverified()->create([
            'usertype' => 'buyer',
            'status' => 'unverified',
        ]);
        $user->generateAndSendOtp();
        $otp = $user->otp_code;

        $response = $this->post('/verify-otp', [
            'email' => $user->email,
            'otp' => $otp,
        ]);

        $response->assertRedirect(route('buyer.dashboard'));
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertEquals('approved', $user->status);
        $this->assertNull($user->otp_code);
        $this->assertNull($user->otp_expires_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_tc_buy_024_verify_with_incorrect_otp_fails(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $user = \App\Models\User::factory()->unverified()->create([
            'usertype' => 'buyer',
            'status' => 'unverified',
        ]);
        $user->generateAndSendOtp();

        $response = $this->postJson('/verify-otp', [
            'email' => $user->email,
            'otp' => '000000',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['otp' => 'Invalid verification code.']);
        $user->refresh();
        $this->assertNull($user->email_verified_at);
        $this->assertEquals('unverified', $user->status);
    }

    public function test_tc_buy_025_otp_in_wrong_format_is_rejected(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $user = \App\Models\User::factory()->unverified()->create([
            'usertype' => 'buyer',
            'status' => 'unverified',
        ]);
        $user->generateAndSendOtp();

        $invalidFormats = ['12345', '1234567', 'abcdef', '12345a'];

        foreach ($invalidFormats as $invalidOtp) {
            $response = $this->postJson('/verify-otp', [
                'email' => $user->email,
                'otp' => $invalidOtp,
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['otp']);
        }
    }

    public function test_tc_buy_026_verify_with_expired_otp_fails(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $user = \App\Models\User::factory()->unverified()->create([
            'usertype' => 'buyer',
            'status' => 'unverified',
            'otp_code' => '654321',
            'otp_expires_at' => now()->subMinutes(1),
        ]);

        $response = $this->postJson('/verify-otp', [
            'email' => $user->email,
            'otp' => '654321',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['otp' => 'OTP has expired. Please request a new code.']);
        $user->refresh();
        $this->assertNull($user->email_verified_at);
    }

    public function test_tc_buy_027_resend_otp_invalidates_old_code_and_sends_new_one(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $user = \App\Models\User::factory()->unverified()->create([
            'usertype' => 'buyer',
            'status' => 'unverified',
            'otp_code' => '111111',
            'otp_expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/verify-otp/resend', [
            'email' => $user->email,
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'success']);

        $user->refresh();
        $this->assertNotEquals('111111', $user->otp_code);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $user->otp_code);
        $this->assertTrue(now()->lessThanOrEqualTo($user->otp_expires_at));

        // Old code fails
        $oldCodeResponse = $this->postJson('/verify-otp', [
            'email' => $user->email,
            'otp' => '111111',
        ]);
        $oldCodeResponse->assertStatus(422);

        // New code succeeds
        $newCodeResponse = $this->postJson('/verify-otp', [
            'email' => $user->email,
            'otp' => $user->otp_code,
        ]);
        $newCodeResponse->assertOk();
    }

    public function test_tc_buy_028_login_before_verifying_email_redirects_to_otp_screen(): void
    {
        // 1. Unverified buyer with status 'unverified'
        $unverifiedBuyer = \App\Models\User::factory()->unverified()->create([
            'usertype' => 'buyer',
            'status' => 'unverified',
            'password' => \Illuminate\Support\Facades\Hash::make('Mamimili@2026'),
        ]);

        $loginResponse = $this->post('/login', [
            'email' => $unverifiedBuyer->email,
            'password' => 'Mamimili@2026',
        ]);

        $loginResponse->assertRedirect(route('verification.otp'));

        // Log out so subsequent login test starts as guest
        $this->post('/logout');

        // 2. Admin mistakenly marked buyer 'approved' while email_verified_at is still null
        $unverifiedApprovedBuyer = \App\Models\User::factory()->unverified()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
            'password' => \Illuminate\Support\Facades\Hash::make('Mamimili@2026'),
        ]);

        $loginResponse2 = $this->post('/login', [
            'email' => $unverifiedApprovedBuyer->email,
            'password' => 'Mamimili@2026',
        ]);

        $loginResponse2->assertRedirect(route('verification.otp'));

        // 3. Directly hitting buyer dashboard as unverified buyer is blocked by EnsureBuyer middleware
        $directAccess = $this->actingAs($unverifiedApprovedBuyer)->get(route('buyer.dashboard'));
        $directAccess->assertRedirect(route('verification.otp'));
    }

    public function test_tc_buy_029_login_with_verified_account_lands_on_buyer_dashboard(): void
    {
        $verifiedBuyer = \App\Models\User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
            'email_verified_at' => now(),
            'password' => \Illuminate\Support\Facades\Hash::make('Mamimili@2026'),
        ]);

        $response = $this->post('/login', [
            'email' => $verifiedBuyer->email,
            'password' => 'Mamimili@2026',
        ]);

        $this->assertAuthenticatedAs($verifiedBuyer);
        $response->assertRedirect(route('buyer.dashboard'));
    }

    public function test_tc_buy_002_submit_completely_empty_form(): void
    {
        $response = $this->postJson('/register', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'first_name',
            'last_name',
            'email',
            'birthday',
            'contact_no',
            'password',
            'terms',
        ]);
    }

    public function test_tc_buy_003_leave_one_required_field_blank_at_a_time(): void
    {
        $baseData = [
            'first_name' => 'Juan Miguel',
            'last_name' => 'Dela Cruz',
            'email' => 'juanmiguel.delacruz@example.com',
            'mobile_number' => '09171234567',
            'contact_no' => '09171234567',
            'birthday' => '1998-05-14',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ];

        $requiredFields = ['first_name', 'last_name', 'email', 'contact_no', 'birthday', 'password'];

        foreach ($requiredFields as $field) {
            $testData = $baseData;
            unset($testData[$field]);

            $response = $this->postJson('/register', $testData);
            $response->assertStatus(422);
            $response->assertJsonValidationErrors([$field]);
        }
    }

    public function test_tc_buy_008_date_of_birth_in_the_future(): void
    {
        $tomorrow = now()->addDay()->toDateString();

        $response = $this->postJson('/register', [
            'first_name' => 'Juan Miguel',
            'last_name' => 'Dela Cruz',
            'email' => 'juanmiguel.delacruz@example.com',
            'contact_no' => '09171234567',
            'birthday' => $tomorrow,
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['birthday']);
    }

    public function test_tc_buy_017_password_length_at_the_limit(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        // 7 characters: Bili@26 (reject)
        $reject = $this->postJson('/register', [
            'first_name' => 'Juan Miguel',
            'last_name' => 'Dela Cruz',
            'email' => 'buyer.test01@example.com',
            'contact_no' => '09171234567',
            'birthday' => '1998-05-14',
            'password' => 'Bili@26',
            'password_confirmation' => 'Bili@26',
            'terms' => true,
        ]);
        $reject->assertStatus(422);
        $reject->assertJsonValidationErrors(['password']);

        // 8 characters: Bili@202 (accept)
        $accept = $this->postJson('/register', [
            'first_name' => 'Juan Miguel',
            'last_name' => 'Dela Cruz',
            'email' => 'buyer.test01@example.com',
            'contact_no' => '09171234567',
            'birthday' => '1998-05-14',
            'password' => 'Bili@202',
            'password_confirmation' => 'Bili@202',
            'terms' => true,
        ]);
        $accept->assertOk();
    }

    public function test_tc_buy_019_confirm_password_does_not_match(): void
    {
        $response = $this->postJson('/register', [
            'first_name' => 'Juan Miguel',
            'last_name' => 'Dela Cruz',
            'email' => 'buyer.test02@example.com',
            'contact_no' => '09171234568',
            'birthday' => '1998-05-14',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2027',
            'terms' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_tc_buy_030_buyer_cannot_access_other_role_dashboards(): void
    {
        $verifiedBuyer = \App\Models\User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
            'email_verified_at' => now(),
        ]);

        // 1. Seller Dashboard (/seller/dashboard) -> 403 Forbidden
        $sellerResponse = $this->actingAs($verifiedBuyer)->get('/seller/dashboard');
        $this->assertEquals(403, $sellerResponse->status());

        // 2. Admin Dashboard (/admin and /admin/dashboard) -> 403 Forbidden
        $adminResponse = $this->actingAs($verifiedBuyer)->get('/admin');
        $this->assertEquals(403, $adminResponse->status());

        $adminDashboardResponse = $this->actingAs($verifiedBuyer)->get('/admin/dashboard');
        $this->assertEquals(403, $adminDashboardResponse->status());

        // 3. Sorting Center / Logistics Dashboard -> 403 or redirect
        $sortingResponse = $this->actingAs($verifiedBuyer)->get('/sorting-center/dashboard');
        $this->assertTrue(in_array($sortingResponse->status(), [403, 302]));

        // 4. Rider Dashboard -> 403 or redirect
        $riderResponse = $this->actingAs($verifiedBuyer)->get('/rider/dashboard');
        $this->assertTrue(in_array($riderResponse->status(), [403, 302]));
    }
}

