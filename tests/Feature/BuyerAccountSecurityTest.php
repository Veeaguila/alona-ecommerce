<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use App\Notifications\EmailChangeOtpNotification;
use App\Notifications\PhoneChangeOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BuyerAccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function createBuyer(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'usertype' => 'buyer',
            'status' => 'approved',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'name' => 'Maria Santos',
            'email' => 'maria.santos@example.com',
            'contact_no' => '09171234567',
            'birthday' => '1995-04-12',
            'province' => 'Bohol',
            'municipality' => 'Tagbilaran City',
            'barangay' => 'Poblacion',
            'street_address' => '123 Rizal Street',
            'email_verified_at' => now(),
            'password' => Hash::make('Mamimili@2026'),
        ], $attributes));
    }

    private function validPayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Maria Santos',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'middle_initial' => 'C',
            'sex' => 'Female',
            'email' => $user->email,
            'contact_no' => $user->contact_no,
            'birthday' => '1995-04-12',
            'province' => 'Bohol',
            'municipality' => 'Tagbilaran City',
            'barangay' => 'Poblacion',
            'street_address' => '123 Rizal Street',
        ], $overrides);
    }

    /**
     * BUY-ACCT-001: Edit profile with valid data (including avatar photo upload).
     */
    public function test_buy_acct_001_edit_profile_with_valid_data_and_avatar(): void
    {
        Storage::fake('public');
        $buyer = $this->createBuyer();

        $avatarFile = UploadedFile::fake()->image('profile.jpg', 300, 300);

        $response = $this->actingAs($buyer)->patch(route('buyer.account.update'), $this->validPayload($buyer, [
            'first_name' => 'Maria Luisa',
            'last_name' => 'Santos-Cruz',
            'profile_photo' => $avatarFile,
        ]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('buyer.account'));

        $buyer->refresh();
        $this->assertEquals('Maria Luisa', $buyer->first_name);
        $this->assertEquals('Santos-Cruz', $buyer->last_name);
        $this->assertNotNull($buyer->avatar);
        $this->assertNotNull($buyer->avatar_url);
        Storage::disk('public')->assertExists($buyer->avatar);
    }

    /**
     * BUY-ACCT-002: Profile field validation (letters only, min:2, photo limits).
     */
    public function test_buy_acct_002_profile_field_validation(): void
    {
        $buyer = $this->createBuyer();

        // Single character name rejected
        $respSingle = $this->actingAs($buyer)->patch(route('buyer.account.update'), $this->validPayload($buyer, [
            'first_name' => 'M',
        ]));
        $respSingle->assertSessionHasErrors('first_name');

        // Name with numbers rejected
        $respNumbers = $this->actingAs($buyer)->patch(route('buyer.account.update'), $this->validPayload($buyer, [
            'first_name' => 'Maria123',
        ]));
        $respNumbers->assertSessionHasErrors('first_name');

        // Name with special invalid characters rejected
        $respInvalid = $this->actingAs($buyer)->patch(route('buyer.account.update'), $this->validPayload($buyer, [
            'last_name' => 'Santos#$%',
        ]));
        $respInvalid->assertSessionHasErrors('last_name');

        // Huge photo > 5MB rejected
        $hugePhoto = UploadedFile::fake()->create('huge.png', 6 * 1024);
        $respHuge = $this->actingAs($buyer)->patch(route('buyer.account.update'), $this->validPayload($buyer, [
            'profile_photo' => $hugePhoto,
        ]));
        $respHuge->assertSessionHasErrors('profile_photo');
    }

    /**
     * BUY-ACCT-003: Change password with correct old password.
     */
    public function test_buy_acct_003_change_password_with_correct_old_password(): void
    {
        $buyer = $this->createBuyer();

        $response = $this->actingAs($buyer)->put(route('password.update'), [
            'current_password' => 'Mamimili@2026',
            'password' => 'NewPassword@2026!',
            'password_confirmation' => 'NewPassword@2026!',
        ]);

        $response->assertSessionHasNoErrors();
        $buyer->refresh();

        $this->assertTrue(Hash::check('NewPassword@2026!', $buyer->password));
        $this->assertFalse(Hash::check('Mamimili@2026', $buyer->password));
    }

    /**
     * BUY-ACCT-004: Change password with wrong old password.
     */
    public function test_buy_acct_004_change_password_with_wrong_old_password(): void
    {
        $buyer = $this->createBuyer();

        $response = $this->actingAs($buyer)->put(route('password.update'), [
            'current_password' => 'WrongPassword123!',
            'password' => 'NewPassword@2026!',
            'password_confirmation' => 'NewPassword@2026!',
        ]);

        $response->assertSessionHasErrors('current_password');
        $buyer->refresh();

        $this->assertTrue(Hash::check('Mamimili@2026', $buyer->password));
    }

    /**
     * BUY-ACCT-005: New password complexity rules.
     */
    public function test_buy_acct_005_new_password_complexity_rules(): void
    {
        $buyer = $this->createBuyer();

        // Weak password rejected
        $response = $this->actingAs($buyer)->put(route('password.update'), [
            'current_password' => 'Mamimili@2026',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertSessionHasErrors('password');
    }

    /**
     * BUY-ACCT-006: Change email triggers OTP challenge without updating DB record directly.
     */
    public function test_buy_acct_006_change_email_with_otp_challenge(): void
    {
        Notification::fake();
        $buyer = $this->createBuyer();

        $response = $this->actingAs($buyer)->patch(route('buyer.account.update'), $this->validPayload($buyer, [
            'email' => 'new.email@example.com',
        ]));

        $response->assertSessionHasNoErrors();
        $buyer->refresh();

        // Email in DB remains the original email
        $this->assertEquals('maria.santos@example.com', $buyer->email);
        $this->assertEquals('new.email@example.com', $buyer->pending_email);
        $this->assertNotNull($buyer->pending_email_otp);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $buyer->pending_email_otp);
        $this->assertNotNull($buyer->pending_email_otp_expires_at);

        Notification::assertSentOnDemand(EmailChangeOtpNotification::class);
    }

    /**
     * BUY-ACCT-007: Change email OTP wrong and expired handling.
     */
    public function test_buy_acct_007_change_email_otp_validation_and_completion(): void
    {
        $buyer = $this->createBuyer([
            'pending_email' => 'verified.new@example.com',
            'pending_email_otp' => '654321',
            'pending_email_otp_expires_at' => now()->addMinutes(5),
        ]);

        // Wrong OTP rejected
        $respWrong = $this->actingAs($buyer)->post(route('buyer.account.email.verify'), [
            'otp' => '111111',
        ]);
        $respWrong->assertSessionHasErrors('otp');
        $buyer->refresh();
        $this->assertEquals('maria.santos@example.com', $buyer->email);

        // Expired OTP rejected
        $buyer->update(['pending_email_otp_expires_at' => now()->subMinute()]);
        $respExpired = $this->actingAs($buyer)->post(route('buyer.account.email.verify'), [
            'otp' => '654321',
        ]);
        $respExpired->assertSessionHasErrors('otp');

        // Valid OTP successfully updates email
        $buyer->update(['pending_email_otp_expires_at' => now()->addMinutes(5)]);
        $respValid = $this->actingAs($buyer)->post(route('buyer.account.email.verify'), [
            'otp' => '654321',
        ]);
        $respValid->assertSessionHasNoErrors();
        $buyer->refresh();
        $this->assertEquals('verified.new@example.com', $buyer->email);
        $this->assertNull($buyer->pending_email);
        $this->assertNull($buyer->pending_email_otp);
    }

    /**
     * BUY-ACCT-008: Change email to already registered email is blocked.
     */
    public function test_buy_acct_008_change_email_to_already_registered_email(): void
    {
        $buyer1 = $this->createBuyer(['email' => 'first@example.com']);
        $buyer2 = $this->createBuyer(['email' => 'existing@example.com', 'contact_no' => '09181112233']);

        $response = $this->actingAs($buyer1)->patch(route('buyer.account.update'), $this->validPayload($buyer1, [
            'email' => 'existing@example.com',
        ]));

        $response->assertSessionHasErrors('email');
    }

    /**
     * BUY-ACCT-009: Change phone with OTP verification.
     */
    public function test_buy_acct_009_change_phone_with_otp_verification(): void
    {
        Notification::fake();
        $buyer = $this->createBuyer();

        // Invalid format rejected
        $respInvalid = $this->actingAs($buyer)->patch(route('buyer.account.update'), $this->validPayload($buyer, [
            'contact_no' => '12345',
        ]));
        $respInvalid->assertSessionHasErrors('contact_no');

        // Valid format initiates challenge without changing original phone
        $respValid = $this->actingAs($buyer)->patch(route('buyer.account.update'), $this->validPayload($buyer, [
            'contact_no' => '09198765432',
        ]));
        $respValid->assertSessionHasNoErrors();
        $buyer->refresh();

        $this->assertEquals('09171234567', $buyer->contact_no);
        $this->assertEquals('09198765432', $buyer->pending_phone);
        $this->assertNotNull($buyer->pending_phone_otp);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $buyer->pending_phone_otp);

        // Wrong OTP rejected
        $respWrong = $this->actingAs($buyer)->post(route('buyer.account.phone.verify'), [
            'otp' => '000000',
        ]);
        $respWrong->assertSessionHasErrors('otp');

        // Correct OTP completes phone update
        $respCorrect = $this->actingAs($buyer)->post(route('buyer.account.phone.verify'), [
            'otp' => $buyer->pending_phone_otp,
        ]);
        $respCorrect->assertSessionHasNoErrors();
        $buyer->refresh();

        $this->assertEquals('09198765432', $buyer->contact_no);
        $this->assertNull($buyer->pending_phone);
        $this->assertNull($buyer->pending_phone_otp);
    }
}

