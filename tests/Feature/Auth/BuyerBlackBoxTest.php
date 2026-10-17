<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BuyerBlackBoxTest extends TestCase
{
    use RefreshDatabase;

    private function validBuyerPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Juan Miguel',
            'last_name' => 'Dela Cruz',
            'email' => 'juanmiguel.delacruz@example.com',
            'contact_no' => '09171234567',
            'birthday' => '1998-05-14',
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2026',
            'terms' => true,
        ], $overrides);
    }

    public function test_tc_buy_001_register_with_all_valid_data(): void
    {
        Notification::fake();
        $payload = $this->validBuyerPayload();

        $response = $this->post('/register', $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('verification.otp'));

        $this->assertDatabaseHas('users', [
            'email' => 'juanmiguel.delacruz@example.com',
            'first_name' => 'Juan Miguel',
            'last_name' => 'Dela Cruz',
            'usertype' => 'buyer',
            'status' => 'unverified',
        ]);

        $user = User::where('email', 'juanmiguel.delacruz@example.com')->first();
        $this->assertNotNull($user->otp_code);
        $this->assertNotNull($user->otp_expires_at);
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function test_tc_buy_002_submit_a_completely_empty_form(): void
    {
        $response = $this->post('/register', []);

        $response->assertSessionHasErrors([
            'first_name',
            'last_name',
            'email',
            'contact_no',
            'birthday',
            'password',
            'terms',
        ]);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_tc_buy_003_leave_one_required_field_blank_at_a_time(): void
    {
        $fields = [
            'first_name',
            'last_name',
            'email',
            'contact_no',
            'birthday',
            'password',
            'confirm_password',
        ];

        foreach ($fields as $field) {
            $payload = $this->validBuyerPayload();
            if ($field === 'confirm_password') {
                $payload['password_confirmation'] = '';
                $response = $this->post('/register', $payload);
                $response->assertSessionHasErrors('password');
            } else {
                $payload[$field] = '';
                $response = $this->post('/register', $payload);
                $response->assertSessionHasErrors($field);
            }
        }
    }

    public function test_tc_buy_004_name_with_numbers_or_symbols(): void
    {
        $payload = $this->validBuyerPayload([
            'first_name' => 'Juan Miguel123',
            'last_name' => 'Dela Cruz@#',
        ]);

        $response = $this->post('/register', $payload);

        $response->assertSessionHasErrors(['first_name', 'last_name']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_tc_buy_005_name_with_enie_and_allowed_punctuation(): void
    {
        Notification::fake();
        $payload = $this->validBuyerPayload([
            'first_name' => 'Niño Angelo',
            'last_name' => "Peñaflor-O'Hara",
            'email' => 'nino.angelo@example.com',
            'contact_no' => '09172223334',
        ]);

        $response = $this->post('/register', $payload);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', [
            'first_name' => 'Niño Angelo',
            'last_name' => "Peñaflor-O'Hara",
            'email' => 'nino.angelo@example.com',
        ]);
    }

    public function test_tc_buy_006_name_length_at_the_limits(): void
    {
        // 1 char rejected
        $resp1 = $this->post('/register', $this->validBuyerPayload(['first_name' => 'J']));
        $resp1->assertSessionHasErrors('first_name');

        // 2 chars accepted
        $resp2 = $this->post('/register', $this->validBuyerPayload([
            'first_name' => 'Jo',
            'email' => 'jo.delacruz@example.com',
            'contact_no' => '09171110001',
        ]));
        $resp2->assertSessionHasNoErrors();

        // 50 chars accepted
        $name50 = str_pad('Maria Cristina', 50, 'a');
        $resp3 = $this->post('/register', $this->validBuyerPayload([
            'first_name' => $name50,
            'email' => 'name50@example.com',
            'contact_no' => '09171110002',
        ]));
        $resp3->assertSessionHasNoErrors();

        // 51 chars rejected
        $name51 = str_pad('Maria Cristina', 51, 'a');
        $resp4 = $this->post('/register', $this->validBuyerPayload(['first_name' => $name51]));
        $resp4->assertSessionHasErrors('first_name');
    }

    public function test_tc_buy_007_age_at_the_18_year_limit(): void
    {
        // 17 years old rejected
        $dob17 = Carbon::now()->subYears(18)->addDays(1)->format('Y-m-d');
        $resp17 = $this->post('/register', $this->validBuyerPayload([
            'birthday' => $dob17,
            'email' => 'age17@example.com',
        ]));
        $resp17->assertSessionHasErrors('birthday');

        // Turns 18 today accepted
        $dob18 = Carbon::now()->subYears(18)->format('Y-m-d');
        $resp18 = $this->post('/register', $this->validBuyerPayload([
            'birthday' => $dob18,
            'email' => 'age18@example.com',
            'contact_no' => '09171110003',
        ]));
        $resp18->assertSessionHasNoErrors();

        // 18 years and 1 day accepted
        $dob18Plus = Carbon::now()->subYears(18)->subDays(1)->format('Y-m-d');
        $resp18Plus = $this->post('/register', $this->validBuyerPayload([
            'birthday' => $dob18Plus,
            'email' => 'age18plus@example.com',
            'contact_no' => '09171110004',
        ]));
        $resp18Plus->assertSessionHasNoErrors();
    }

    public function test_tc_buy_008_date_of_birth_in_the_future(): void
    {
        $tomorrow = Carbon::now()->addDay()->format('Y-m-d');
        $response = $this->post('/register', $this->validBuyerPayload(['birthday' => $tomorrow]));
        $response->assertSessionHasErrors('birthday');
    }

    public function test_tc_buy_009_email_in_an_invalid_format(): void
    {
        $invalidEmails = [
            'juanmiguel.delacruzexample.com', // no @
            'juanmiguel.delacruz@',           // no domain
            '@example.com',                   // no name
            'juanmiguel.delacruz@example',    // no TLD
            'juanmiguel delacruz@example.com', // space
        ];

        foreach ($invalidEmails as $email) {
            $response = $this->post('/register', $this->validBuyerPayload(['email' => $email]));
            $response->assertSessionHasErrors('email');
        }
    }

    public function test_tc_buy_010_email_that_is_already_registered(): void
    {
        User::factory()->create([
            'email' => 'juanmiguel.delacruz@example.com',
            'usertype' => 'buyer',
        ]);

        $response = $this->post('/register', $this->validBuyerPayload());
        $response->assertSessionHasErrors('email');
    }

    public function test_tc_buy_011_registered_email_typed_in_capital_letters(): void
    {
        User::factory()->create([
            'email' => 'juanmiguel.delacruz@example.com',
            'usertype' => 'buyer',
        ]);

        $response = $this->post('/register', $this->validBuyerPayload([
            'email' => 'JUANMIGUEL.DELACRUZ@EXAMPLE.COM',
        ]));
        $response->assertSessionHasErrors('email');
    }

    public function test_tc_buy_012_email_with_spaces_before_and_after(): void
    {
        Notification::fake();
        $response = $this->post('/register', $this->validBuyerPayload([
            'email' => '  buyer.test01@example.com  ',
            'contact_no' => '09171110005',
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', [
            'email' => 'buyer.test01@example.com',
        ]);
    }

    public function test_tc_buy_013_mobile_number_in_an_invalid_format(): void
    {
        $invalidNumbers = [
            '0917ABC4567',
            '0917-123-456',
            '12345678901',
            '02-8123-4567',
        ];

        foreach ($invalidNumbers as $mobile) {
            $response = $this->post('/register', $this->validBuyerPayload(['contact_no' => $mobile]));
            $response->assertSessionHasErrors('contact_no');
        }
    }

    public function test_tc_buy_014_mobile_number_length_at_the_limits(): void
    {
        // 10 digits reject
        $resp1 = $this->post('/register', $this->validBuyerPayload(['contact_no' => '0917123456']));
        $resp1->assertSessionHasErrors('contact_no');

        // 11 digits accept
        $resp2 = $this->post('/register', $this->validBuyerPayload([
            'contact_no' => '09170000001',
            'email' => 'mobile11@example.com',
        ]));
        $resp2->assertSessionHasNoErrors();

        // 12 digits reject
        $resp3 = $this->post('/register', $this->validBuyerPayload(['contact_no' => '091712345678']));
        $resp3->assertSessionHasErrors('contact_no');
    }

    public function test_tc_buy_015_mobile_number_that_is_already_registered(): void
    {
        User::factory()->create([
            'contact_no' => '09171234567',
        ]);

        $response = $this->post('/register', $this->validBuyerPayload(['contact_no' => '09171234567']));
        $response->assertSessionHasErrors('contact_no');
    }

    public function test_tc_buy_016_registered_number_entered_in_plus_63_format(): void
    {
        User::factory()->create([
            'contact_no' => '09171234567',
        ]);

        $response = $this->post('/register', $this->validBuyerPayload(['contact_no' => '+639171234567']));
        $response->assertSessionHasErrors('contact_no');
    }

    public function test_tc_buy_017_password_length_at_the_limit(): void
    {
        // 7 chars rejected
        $resp1 = $this->post('/register', $this->validBuyerPayload([
            'password' => 'Bili@26',
            'password_confirmation' => 'Bili@26',
        ]));
        $resp1->assertSessionHasErrors('password');

        // 8 chars accepted
        $resp2 = $this->post('/register', $this->validBuyerPayload([
            'password' => 'Bili@202',
            'password_confirmation' => 'Bili@202',
            'email' => 'bili8@example.com',
            'contact_no' => '09171110006',
        ]));
        $resp2->assertSessionHasNoErrors();
    }

    public function test_tc_buy_018_password_missing_a_required_character_type(): void
    {
        $passwords = [
            'mamimili@2026', // No uppercase
            'MAMIMILI@2026', // No lowercase
            'Mamimili@abcd', // No number
            'Mamimili2026',  // No special char
        ];

        foreach ($passwords as $pwd) {
            $response = $this->post('/register', $this->validBuyerPayload([
                'password' => $pwd,
                'password_confirmation' => $pwd,
            ]));
            $response->assertSessionHasErrors('password');
        }
    }

    public function test_tc_buy_019_confirm_password_does_not_match(): void
    {
        $response = $this->post('/register', $this->validBuyerPayload([
            'password' => 'Mamimili@2026',
            'password_confirmation' => 'Mamimili@2027',
        ]));
        $response->assertSessionHasErrors('password');
    }

    public function test_tc_buy_020_password_is_hidden_while_typing(): void
    {
        // Password fields are dynamic :type="showRegisterPassword ? 'text' : 'password'" by default hidden
        $view = file_get_contents(resource_path('js/Pages/Auth/AuthModal.vue'));
        $this->assertStringContainsString("showRegisterPassword ? 'text' : 'password'", $view);
        $this->assertStringContainsString("showRegisterPassword = !showRegisterPassword", $view);
    }

    public function test_tc_buy_021_register_without_accepting_the_terms(): void
    {
        $response = $this->post('/register', $this->validBuyerPayload(['terms' => false]));
        $response->assertSessionHasErrors('terms');
    }

    public function test_tc_buy_022_click_register_several_times_quickly(): void
    {
        Notification::fake();
        $payload = $this->validBuyerPayload([
            'email' => 'anamarie.reyes@example.com',
            'contact_no' => '09173456789',
        ]);

        $resp1 = $this->post('/register', $payload);
        $resp1->assertSessionHasNoErrors();

        // Second click with identical payload triggers unique constraint error without duplicate user creation
        $resp2 = $this->post('/register', $payload);
        $resp2->assertSessionHasErrors('email');

        $this->assertEquals(1, User::where('email', 'anamarie.reyes@example.com')->count());
    }

    public function test_tc_buy_023_verify_with_the_correct_otp(): void
    {
        $user = User::factory()->create([
            'email' => 'juanmiguel.delacruz@example.com',
            'usertype' => 'buyer',
            'status' => 'unverified',
            'otp_code' => '482913',
            'otp_expires_at' => Carbon::now()->addMinutes(5),
            'email_verified_at' => null,
        ]);

        $response = $this->post('/verify-otp', [
            'email' => $user->email,
            'otp' => '482913',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('buyer.dashboard'));

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertEquals('approved', $user->status);
        $this->assertNull($user->otp_code);
        $this->assertAuthenticatedAs($user);
    }

    public function test_tc_buy_024_verify_with_an_incorrect_otp(): void
    {
        $user = User::factory()->create([
            'email' => 'juanmiguel.delacruz@example.com',
            'usertype' => 'buyer',
            'status' => 'unverified',
            'otp_code' => '482913',
            'otp_expires_at' => Carbon::now()->addMinutes(5),
            'email_verified_at' => null,
        ]);

        $response = $this->post('/verify-otp', [
            'email' => $user->email,
            'otp' => '000000',
        ]);

        $response->assertSessionHasErrors('otp');

        $user->refresh();
        $this->assertNull($user->email_verified_at);
        $this->assertEquals('unverified', $user->status);
    }

    public function test_tc_buy_025_otp_in_the_wrong_format(): void
    {
        $user = User::factory()->create([
            'email' => 'juanmiguel.delacruz@example.com',
            'usertype' => 'buyer',
            'status' => 'unverified',
            'otp_code' => '482913',
            'otp_expires_at' => Carbon::now()->addMinutes(5),
            'email_verified_at' => null,
        ]);

        $invalidOtps = [
            '48291',   // 5 digits
            '4829137', // 7 digits
            '48A91B',  // Letters
            '',        // Blank
        ];

        foreach ($invalidOtps as $otp) {
            $response = $this->post('/verify-otp', [
                'email' => $user->email,
                'otp' => $otp,
            ]);
            $response->assertSessionHasErrors('otp');
        }

        $user->refresh();
        $this->assertNull($user->email_verified_at);
    }

    public function test_tc_buy_026_verify_with_an_expired_otp(): void
    {
        $user = User::factory()->create([
            'email' => 'juanmiguel.delacruz@example.com',
            'usertype' => 'buyer',
            'status' => 'unverified',
            'otp_code' => '482913',
            'otp_expires_at' => Carbon::now()->subSeconds(2),
            'email_verified_at' => null,
        ]);

        $response = $this->post('/verify-otp', [
            'email' => $user->email,
            'otp' => '482913',
        ]);

        $response->assertSessionHasErrors('otp');
        $user->refresh();
        $this->assertNull($user->email_verified_at);
        $this->assertEquals('unverified', $user->status);
    }

    public function test_tc_buy_027_resend_otp(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'juanmiguel.delacruz@example.com',
            'usertype' => 'buyer',
            'status' => 'unverified',
            'otp_code' => '482913',
            'otp_expires_at' => Carbon::now()->addMinutes(5),
            'email_verified_at' => null,
        ]);

        $firstCode = $user->otp_code;

        $resendResponse = $this->post('/verify-otp/resend', [
            'email' => $user->email,
        ]);
        $resendResponse->assertSessionHasNoErrors();

        $user->refresh();
        $secondCode = $user->otp_code;
        $this->assertNotEquals($firstCode, $secondCode);

        // Verify with old code fails
        $failResponse = $this->post('/verify-otp', [
            'email' => $user->email,
            'otp' => $firstCode,
        ]);
        $failResponse->assertSessionHasErrors('otp');

        // Verify with new code succeeds
        $successResponse = $this->post('/verify-otp', [
            'email' => $user->email,
            'otp' => $secondCode,
        ]);
        $successResponse->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertEquals('approved', $user->status);
    }

    public function test_tc_buy_028_log_in_before_verifying_the_email(): void
    {
        $user = User::factory()->create([
            'email' => 'juanmiguel.delacruz@example.com',
            'password' => Hash::make('Mamimili@2026'),
            'usertype' => 'buyer',
            'status' => 'unverified',
            'email_verified_at' => null,
        ]);

        $response = $this->post('/login', [
            'email' => 'juanmiguel.delacruz@example.com',
            'password' => 'Mamimili@2026',
        ]);

        // Unverified user is redirected to verification.otp and denied dashboard access
        $response->assertRedirect(route('verification.otp'));

        // Access to dashboard directly is denied and redirects to verification.otp
        $dashboardResponse = $this->actingAs($user)->get(route('buyer.dashboard'));
        $dashboardResponse->assertRedirect(route('verification.otp'));
    }

    public function test_tc_buy_029_log_in_with_the_new_verified_account(): void
    {
        $user = User::factory()->create([
            'email' => 'juanmiguel.delacruz@example.com',
            'first_name' => 'Juan Miguel',
            'name' => 'Juan Miguel Dela Cruz',
            'password' => Hash::make('Mamimili@2026'),
            'usertype' => 'buyer',
            'status' => 'approved',
            'email_verified_at' => now(),
        ]);

        $response = $this->post('/login', [
            'email' => 'juanmiguel.delacruz@example.com',
            'password' => 'Mamimili@2026',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('buyer.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_tc_buy_030_open_another_roles_pages(): void
    {
        $user = User::factory()->create([
            'email' => 'juanmiguel.delacruz@example.com',
            'usertype' => 'buyer',
            'status' => 'approved',
            'email_verified_at' => now(),
        ]);

        $otherRoleRoutes = [
            '/seller/dashboard',
            '/sorting-center/dashboard',
            '/rider/dashboard',
            '/admin/dashboard',
        ];

        foreach ($otherRoleRoutes as $route) {
            $response = $this->actingAs($user)->get($route);
            // Must be denied: HTTP 403 Forbidden or redirected away
            $this->assertTrue(
                in_array($response->getStatusCode(), [302, 403], true),
                "Route {$route} should deny access to buyer but returned {$response->getStatusCode()}"
            );
        }
    }
}
