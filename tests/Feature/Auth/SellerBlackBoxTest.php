<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerBlackBoxTest extends TestCase
{
    use RefreshDatabase;

    private function validSellerPayload(array $overrides = []): array
    {
        Storage::fake('local');
        Storage::fake('public');

        return array_merge([
            'first_name' => 'Maria Clara',
            'last_name' => 'Santos',
            'middle_name' => 'Reyes',
            'middle_initial' => 'R',
            'suffix' => null,
            'sex' => 'Female',
            'email' => 'mariaclara.santos@example.com',
            'contact_no' => '09189876543',
            'birthday' => '1995-06-15',
            'age' => 31,
            'store_name' => 'Maria Clara Handicrafts',
            'line_of_business' => 'Home & Living',
            'seller_type' => 'Sole Proprietorship',
            'tin' => '123-456-789-000',
            'address_group' => 'metro-manila',
            'region_code' => '1300000000',
            'province_code' => '',
            'city_code' => '1381300000',
            'barangay_code' => '1381300001',
            'street_address' => 'Blk 12 Lot 8, Sampaguita St.',
            'postal_code' => '1121',
            'id' => UploadedFile::fake()->create('govt_id_front.jpg', 1200, 'image/jpeg'),
            'dti_certificate' => UploadedFile::fake()->create('dti_certificate.pdf', 850, 'application/pdf'),
            'bir_2303' => UploadedFile::fake()->create('bir_2303.pdf', 1100, 'application/pdf'),
            'business_permit' => UploadedFile::fake()->create('business_permit.pdf', 1100, 'application/pdf'),
            'password' => 'Tindahan@2026',
            'password_confirmation' => 'Tindahan@2026',
            'terms' => true,
        ], $overrides);
    }

    public function test_tc_sel_001_register_with_all_valid_data(): void
    {
        Notification::fake();
        $payload = $this->validSellerPayload();

        $response = $this->post('/register/seller', $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('verification.otp', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'mariaclara.santos@example.com',
            'usertype' => 'seller',
            'status' => 'unverified',
            'tin' => '123-456-789-000',
            'postal_code' => '1121',
            'seller_type' => 'Sole Proprietorship',
        ]);

        $user = User::where('email', 'mariaclara.santos@example.com')->first();
        $this->assertNotNull($user->otp_code);
        $this->assertNotNull($user->otp_expires_at);
    }

    public function test_tc_sel_002_submit_a_completely_empty_form(): void
    {
        $response = $this->post('/register/seller', []);
        $response->assertSessionHasErrors([
            'first_name',
            'last_name',
            'email',
            'contact_no',
            'store_name',
            'seller_type',
            'tin',
            'address_group',
            'street_address',
            'postal_code',
            'id',
            'password',
            'terms',
        ]);
    }

    public function test_tc_sel_003_leave_one_required_field_blank_at_a_time(): void
    {
        $requiredFields = [
            'first_name',
            'last_name',
            'email',
            'contact_no',
            'store_name',
            'seller_type',
            'tin',
            'street_address',
            'postal_code',
            'id',
            'password',
            'terms',
        ];

        foreach ($requiredFields as $field) {
            $payload = $this->validSellerPayload([$field => null]);
            $response = $this->post('/register/seller', $payload);
            $response->assertSessionHasErrors([$field]);
        }
    }

    public function test_tc_sel_004_name_with_numbers_or_symbols(): void
    {
        $payload = $this->validSellerPayload([
            'first_name' => 'Maria Clara99',
            'last_name' => 'Dela Cruz@#',
        ]);

        $response = $this->post('/register/seller', $payload);
        $response->assertSessionHasErrors(['first_name', 'last_name']);
    }

    public function test_tc_sel_005_name_with_enye_and_allowed_punctuation(): void
    {
        Notification::fake();
        $payload = $this->validSellerPayload([
            'first_name' => 'Niña Corazon',
            'last_name' => "Peñaranda-D'Souza",
            'email' => 'nina.penaranda@example.com',
            'contact_no' => '09187776655',
            'tin' => '999-888-777-000',
            'store_name' => 'Nina Crafts Store',
        ]);

        $response = $this->post('/register/seller', $payload);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', [
            'email' => 'nina.penaranda@example.com',
            'first_name' => 'Niña Corazon',
            'last_name' => "Peñaranda-D'Souza",
        ]);
    }

    public function test_tc_sel_006_name_length_at_the_limits(): void
    {
        // 1 char (reject)
        $resp1 = $this->post('/register/seller', $this->validSellerPayload(['first_name' => 'M']));
        $resp1->assertSessionHasErrors(['first_name']);

        // 51 chars (reject)
        $resp2 = $this->post('/register/seller', $this->validSellerPayload([
            'first_name' => 'Maria Cristina Angelica Bernadette Josefina Leonora'
        ]));
        $resp2->assertSessionHasErrors(['first_name']);

        // 2 chars (accept)
        Notification::fake();
        $resp3 = $this->post('/register/seller', $this->validSellerPayload([
            'first_name' => 'Mo',
            'email' => 'mo.santos@example.com',
            'contact_no' => '09181112233',
            'tin' => '111-222-333-000',
            'store_name' => 'Mo Store Hub',
        ]));
        $resp3->assertSessionHasNoErrors();

        // 50 chars (accept)
        $resp4 = $this->post('/register/seller', $this->validSellerPayload([
            'first_name' => 'Maria Cristina Angelica Bernadette Josefina Leonor',
            'email' => 'maria50.santos@example.com',
            'contact_no' => '09184445566',
            'tin' => '444-555-666-000',
            'store_name' => 'Maria 50 Hub Store',
        ]));
        $resp4->assertSessionHasNoErrors();
    }

    public function test_tc_sel_007_email_in_an_invalid_format(): void
    {
        $invalidEmails = [
            'mariaclara.santosexample.com',
            'mariaclara.santos@',
            '@example.com',
            'mariaclara.santos@example',
        ];

        foreach ($invalidEmails as $email) {
            $response = $this->post('/register/seller', $this->validSellerPayload(['email' => $email]));
            $response->assertSessionHasErrors(['email']);
        }
    }

    public function test_tc_sel_008_email_already_registered(): void
    {
        User::factory()->create(['email' => 'mariaclara.santos@example.com']);

        $payload = $this->validSellerPayload([
            'email' => 'mariaclara.santos@example.com',
        ]);

        $response = $this->post('/register/seller', $payload);
        $response->assertSessionHasErrors(['email']);
    }

    public function test_tc_sel_009_registered_email_typed_in_capital_letters(): void
    {
        User::factory()->create(['email' => 'mariaclara.santos@example.com']);

        $payload = $this->validSellerPayload([
            'email' => 'MARIACLARA.SANTOS@EXAMPLE.COM',
        ]);

        $response = $this->post('/register/seller', $payload);
        $response->assertSessionHasErrors(['email']);
    }

    public function test_tc_sel_010_email_with_spaces_before_and_after(): void
    {
        Notification::fake();
        $payload = $this->validSellerPayload([
            'email' => '   seller.test01@example.com   ',
            'contact_no' => '09189990011',
            'tin' => '777-666-555-000',
            'store_name' => 'Seller Test 01 Store',
        ]);

        $response = $this->post('/register/seller', $payload);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'seller.test01@example.com']);
    }

    public function test_tc_sel_011_mobile_number_in_an_invalid_format(): void
    {
        $invalidMobiles = [
            '0917ABC4567',
            '0917-123-456',
            '12345678901',
            '02-8123-4567',
        ];

        foreach ($invalidMobiles as $mobile) {
            $response = $this->post('/register/seller', $this->validSellerPayload(['contact_no' => $mobile]));
            $response->assertSessionHasErrors(['contact_no']);
        }
    }

    public function test_tc_sel_012_mobile_number_length_at_limits(): void
    {
        // 10 digits (reject)
        $resp1 = $this->post('/register/seller', $this->validSellerPayload(['contact_no' => '0917123456']));
        $resp1->assertSessionHasErrors(['contact_no']);

        // 12 digits (reject)
        $resp2 = $this->post('/register/seller', $this->validSellerPayload(['contact_no' => '091712345678']));
        $resp2->assertSessionHasErrors(['contact_no']);

        // 11 digits (accept)
        Notification::fake();
        $resp3 = $this->post('/register/seller', $this->validSellerPayload([
            'contact_no' => '09170000001',
            'email' => 'valid11@example.com',
            'tin' => '111-000-111-000',
            'store_name' => 'Valid 11 Store',
        ]));
        $resp3->assertSessionHasNoErrors();
    }

    public function test_tc_sel_013_mobile_number_already_registered(): void
    {
        User::factory()->create(['contact_no' => '09189876543']);

        $payload = $this->validSellerPayload(['contact_no' => '09189876543']);
        $response = $this->post('/register/seller', $payload);
        $response->assertSessionHasErrors(['contact_no']);
    }

    public function test_tc_sel_014_registered_number_in_63_format(): void
    {
        User::factory()->create(['contact_no' => '09189876543']);

        $payload = $this->validSellerPayload([
            'contact_no' => '+639189876543',
        ]);

        $response = $this->post('/register/seller', $payload);
        $response->assertSessionHasErrors(['contact_no']);
    }

    public function test_tc_sel_015_password_length_at_the_limit(): void
    {
        // 7 chars (reject)
        $resp1 = $this->post('/register/seller', $this->validSellerPayload([
            'password' => 'Tind@26',
            'password_confirmation' => 'Tind@26',
        ]));
        $resp1->assertSessionHasErrors(['password']);

        // 8 chars (accept)
        Notification::fake();
        $resp2 = $this->post('/register/seller', $this->validSellerPayload([
            'password' => 'Tinda@26',
            'password_confirmation' => 'Tinda@26',
            'email' => 'tinda8@example.com',
            'contact_no' => '09182223344',
            'tin' => '222-333-444-000',
            'store_name' => 'Tinda 8 Store',
        ]));
        $resp2->assertSessionHasNoErrors();
    }

    public function test_tc_sel_016_password_missing_required_character_type(): void
    {
        $payload = $this->validSellerPayload([
            'password' => 'tindahan@2026', // no uppercase
            'password_confirmation' => 'tindahan@2026',
        ]);

        $response = $this->post('/register/seller', $payload);
        $response->assertSessionHasErrors(['password']);
    }

    public function test_tc_sel_017_confirm_password_does_not_match(): void
    {
        $payload = $this->validSellerPayload([
            'password' => 'Tindahan@2026',
            'password_confirmation' => 'Tindahan@2027',
        ]);

        $response = $this->post('/register/seller', $payload);
        $response->assertSessionHasErrors(['password']);
    }

    public function test_tc_sel_019_register_without_accepting_the_terms(): void
    {
        $payload = $this->validSellerPayload(['terms' => false]);
        $response = $this->post('/register/seller', $payload);
        $response->assertSessionHasErrors(['terms']);
    }

    public function test_tc_sel_021_shop_name_already_used(): void
    {
        User::factory()->create(['store_name' => 'Maria Clara Handicrafts']);

        $payload = $this->validSellerPayload([
            'store_name' => 'Maria Clara Handicrafts',
        ]);

        $response = $this->post('/register/seller', $payload);
        $response->assertSessionHasErrors(['store_name']);
    }

    public function test_tc_sel_022_shop_name_length_at_limits(): void
    {
        // 2 characters (reject)
        $resp1 = $this->post('/register/seller', $this->validSellerPayload(['store_name' => 'MC']));
        $resp1->assertSessionHasErrors(['store_name']);

        // 31 characters (reject)
        $resp2 = $this->post('/register/seller', $this->validSellerPayload(['store_name' => 'Maria Clara Handicrafts, Manila']));
        $resp2->assertSessionHasErrors(['store_name']);

        // 3 characters (accept)
        Notification::fake();
        $resp3 = $this->post('/register/seller', $this->validSellerPayload([
            'store_name' => 'MCH',
            'email' => 'mch@example.com',
            'contact_no' => '09183334455',
            'tin' => '333-444-555-000',
        ]));
        $resp3->assertSessionHasNoErrors();
    }

    public function test_tc_sel_023_required_documents_change_with_seller_type(): void
    {
        Notification::fake();

        // Individual: ID only
        $individual = $this->validSellerPayload([
            'seller_type' => 'Individual',
            'dti_certificate' => null,
            'bir_2303' => null,
            'email' => 'individual@example.com',
            'contact_no' => '09185556677',
            'tin' => '555-666-777-000',
            'store_name' => 'Individual Shop',
        ]);
        $this->post('/register/seller', $individual)->assertSessionHasNoErrors();

        // Corporation: ID + SEC + BIR 2303
        $corp = $this->validSellerPayload([
            'seller_type' => 'Corporation',
            'dti_certificate' => null,
            'sec_certificate' => UploadedFile::fake()->create('sec_certificate.pdf', 1000, 'application/pdf'),
            'bir_2303' => UploadedFile::fake()->create('bir_2303.pdf', 1100, 'application/pdf'),
            'email' => 'corp@example.com',
            'contact_no' => '09186667788',
            'tin' => '666-777-888-000',
            'store_name' => 'Corp Hub Shop',
        ]);
        $this->post('/register/seller', $corp)->assertSessionHasNoErrors();
    }

    public function test_tc_sel_024_corporation_registers_without_sec_certificate(): void
    {
        $payload = $this->validSellerPayload([
            'seller_type' => 'Corporation',
            'sec_certificate' => null,
        ]);

        $response = $this->post('/register/seller', $payload);
        $response->assertSessionHasErrors(['sec_certificate']);
    }

    public function test_tc_sel_025_tin_format_and_length(): void
    {
        // 11 digits (reject)
        $resp1 = $this->post('/register/seller', $this->validSellerPayload(['tin' => '123-456-789-00']));
        $resp1->assertSessionHasErrors(['tin']);

        // 13 digits (reject)
        $resp2 = $this->post('/register/seller', $this->validSellerPayload(['tin' => '123-456-789-0001']));
        $resp2->assertSessionHasErrors(['tin']);

        // Letters (reject)
        $resp3 = $this->post('/register/seller', $this->validSellerPayload(['tin' => '123-456-78A-000']));
        $resp3->assertSessionHasErrors(['tin']);
    }

    public function test_tc_sel_026_tin_already_registered(): void
    {
        User::factory()->create(['tin' => '123-456-789-000']);

        $payload = $this->validSellerPayload(['tin' => '123-456-789-000']);
        $response = $this->post('/register/seller', $payload);
        $response->assertSessionHasErrors(['tin']);
    }

    public function test_tc_sel_018_password_is_hidden_while_typing(): void
    {
        $authModalContent = file_get_contents(resource_path('js/Pages/Auth/AuthModal.vue'));
        $this->assertStringContainsString('showRegisterPassword', $authModalContent);
        $this->assertStringContainsString("showRegisterPassword ? 'text' : 'password'", $authModalContent);
    }

    public function test_tc_sel_020_click_register_several_times_quickly(): void
    {
        Notification::fake();
        $payload = $this->validSellerPayload([
            'email' => 'concurrency.seller@example.com',
            'contact_no' => '09187770011',
            'tin' => '999-000-111-000',
            'store_name' => 'Concurrency Store Hub',
        ]);

        $resp1 = $this->post('/register/seller', $payload);
        $resp1->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'concurrency.seller@example.com']);

        // Subsequent rapid click with same data is blocked (no duplicate created)
        $resp2 = $this->post('/register/seller', $payload);
        $resp2->assertSessionHasErrors(['email']);
        $this->assertSame(1, User::where('email', 'concurrency.seller@example.com')->count());
    }

    public function test_tc_sel_027_address_dropdowns_depend_on_each_other(): void
    {
        // Invalid combination: Region VII region code with Metro Manila city code fails
        $payload = $this->validSellerPayload([
            'address_group' => 'visayas',
            'region_code' => '0700000000', // Region VII
            'province_code' => '0722000000', // Cebu
            'city_code' => '1381300000', // Quezon City (Metro Manila - wrong region)
            'barangay_code' => '1381300001',
        ]);

        $response = $this->post('/register/seller', $payload);
        $response->assertSessionHasErrors();
    }

    public function test_tc_sel_028_postal_code_length_and_format(): void
    {
        // 3 digits (reject)
        $resp1 = $this->post('/register/seller', $this->validSellerPayload(['postal_code' => '112']));
        $resp1->assertSessionHasErrors(['postal_code']);

        // 5 digits (reject)
        $resp2 = $this->post('/register/seller', $this->validSellerPayload(['postal_code' => '11210']));
        $resp2->assertSessionHasErrors(['postal_code']);

        // Letters (reject)
        $resp3 = $this->post('/register/seller', $this->validSellerPayload(['postal_code' => '11A1']));
        $resp3->assertSessionHasErrors(['postal_code']);
    }

    public function test_tc_sel_029_upload_documents_in_each_accepted_file_type(): void
    {
        Notification::fake();

        // JPG
        $jpgPayload = $this->validSellerPayload([
            'id' => UploadedFile::fake()->create('id.jpg', 1200, 'image/jpeg'),
            'email' => 'jpg.upload@example.com',
            'contact_no' => '09181110099',
            'tin' => '100-200-300-000',
            'store_name' => 'JPG Store',
        ]);
        $this->post('/register/seller', $jpgPayload)->assertSessionHasNoErrors();

        // PNG
        $pngPayload = $this->validSellerPayload([
            'id' => UploadedFile::fake()->create('id.png', 2400, 'image/png'),
            'email' => 'png.upload@example.com',
            'contact_no' => '09182220099',
            'tin' => '200-300-400-000',
            'store_name' => 'PNG Store',
        ]);
        $this->post('/register/seller', $pngPayload)->assertSessionHasNoErrors();

        // PDF
        $pdfPayload = $this->validSellerPayload([
            'id' => UploadedFile::fake()->create('id.pdf', 800, 'application/pdf'),
            'email' => 'pdf.upload@example.com',
            'contact_no' => '09183330099',
            'tin' => '300-400-500-000',
            'store_name' => 'PDF Store',
        ]);
        $this->post('/register/seller', $pdfPayload)->assertSessionHasNoErrors();
    }

    public function test_tc_sel_030_upload_file_type_not_accepted(): void
    {
        $invalidFiles = [
            UploadedFile::fake()->create('price_list.docx', 320, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            UploadedFile::fake()->create('store_banner.gif', 480, 'image/gif'),
            UploadedFile::fake()->create('installer.exe', 2100, 'application/x-msdownload'),
        ];

        foreach ($invalidFiles as $file) {
            $response = $this->post('/register/seller', $this->validSellerPayload(['id' => $file]));
            $response->assertSessionHasErrors(['id']);
        }
    }

    public function test_tc_sel_031_file_size_at_the_limit(): void
    {
        // 5.1 MB (reject, max 5120 KB)
        $bigFile = UploadedFile::fake()->create('size_5.1MB.jpg', 5250, 'image/jpeg');
        $resp1 = $this->post('/register/seller', $this->validSellerPayload(['id' => $bigFile]));
        $resp1->assertSessionHasErrors(['id']);
    }

    public function test_tc_sel_032_verify_with_the_correct_otp(): void
    {
        $seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'unverified',
            'email' => 'mariaclara.santos@example.com',
            'otp_code' => '482913',
            'otp_expires_at' => now()->addMinutes(5),
            'email_verified_at' => null,
        ]);

        $response = $this->post('/verify-otp', [
            'email' => 'mariaclara.santos@example.com',
            'otp' => '482913',
        ]);

        $response->assertRedirect(route('application.status', absolute: false));
        $seller->refresh();
        $this->assertSame('pending', $seller->status);
        $this->assertNotNull($seller->email_verified_at);
        $this->assertNull($seller->otp_code);
    }

    public function test_tc_sel_033_verify_with_an_incorrect_otp(): void
    {
        $seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'unverified',
            'email' => 'mariaclara.santos@example.com',
            'otp_code' => '482913',
            'otp_expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->post('/verify-otp', [
            'email' => 'mariaclara.santos@example.com',
            'otp' => '000000',
        ]);

        $response->assertSessionHasErrors(['otp']);
        $seller->refresh();
        $this->assertSame('unverified', $seller->status);
    }

    public function test_tc_sel_034_otp_in_the_wrong_format(): void
    {
        $seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'unverified',
            'email' => 'mariaclara.santos@example.com',
            'otp_code' => '482913',
            'otp_expires_at' => now()->addMinutes(5),
        ]);

        $invalidOtps = ['48291', '4829137', '48A91B'];
        foreach ($invalidOtps as $otp) {
            $response = $this->post('/verify-otp', [
                'email' => 'mariaclara.santos@example.com',
                'otp' => $otp,
            ]);
            $response->assertSessionHasErrors(['otp']);
        }
    }

    public function test_tc_sel_035_verify_with_an_expired_otp(): void
    {
        $seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'unverified',
            'email' => 'mariaclara.santos@example.com',
            'otp_code' => '482913',
            'otp_expires_at' => now()->subMinute(),
        ]);

        $response = $this->post('/verify-otp', [
            'email' => 'mariaclara.santos@example.com',
            'otp' => '482913',
        ]);

        $response->assertSessionHasErrors(['otp']);
        $seller->refresh();
        $this->assertSame('unverified', $seller->status);
    }

    public function test_tc_sel_036_resend_otp(): void
    {
        Notification::fake();

        $seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'unverified',
            'email' => 'mariaclara.santos@example.com',
            'otp_code' => '482913',
            'otp_expires_at' => now()->addMinutes(5),
            'email_verified_at' => null,
        ]);

        $response = $this->post('/verify-otp/resend', [
            'email' => 'mariaclara.santos@example.com',
        ]);

        $response->assertSessionHasNoErrors();
        $seller->refresh();
        $firstCode = '482913';
        $secondCode = Notification::sent(
            $seller,
            \App\Notifications\BuyerOtpVerificationNotification::class
        )->last()->otpCode;
        $this->assertNotSame($firstCode, $secondCode);

        // First code rejected
        $this->post('/verify-otp', [
            'email' => 'mariaclara.santos@example.com',
            'otp' => $firstCode,
        ])->assertSessionHasErrors(['otp']);

        // Second code accepted
        $this->post('/verify-otp', [
            'email' => 'mariaclara.santos@example.com',
            'otp' => $secondCode,
        ])->assertRedirect(route('application.status', absolute: false));

        $seller->refresh();
        $this->assertSame('pending', $seller->status);
    }

    public function test_tc_sel_037_login_before_verifying_email(): void
    {
        User::factory()->create([
            'usertype' => 'seller',
            'status' => 'unverified',
            'email' => 'mariaclara.santos@example.com',
            'password' => Hash::make('Tindahan@2026'),
            'email_verified_at' => null,
        ]);

        $response = $this->post('/login', [
            'email' => 'mariaclara.santos@example.com',
            'password' => 'Tindahan@2026',
        ]);

        $response->assertRedirect(route('verification.otp', absolute: false));
    }

    public function test_tc_sel_038_pending_seller_logs_in(): void
    {
        $seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'pending',
            'email' => 'mariaclara.santos@example.com',
            'password' => Hash::make('Tindahan@2026'),
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($seller)->get(route('seller.dashboard'));
        $response->assertRedirect(route('application.status', absolute: false));
    }

    public function test_tc_sel_039_new_application_appears_in_admin_pending_list(): void
    {
        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']);
        $seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'pending',
            'store_name' => 'Maria Clara Handicrafts',
            'email' => 'mariaclara.santos@example.com',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.applications', ['status' => 'pending']));
        $response->assertOk();
    }

    public function test_tc_sel_040_admin_approves_the_application(): void
    {
        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']);
        $seller = User::factory()->create(['usertype' => 'seller', 'status' => 'pending']);

        $response = $this->actingAs($admin)->patch(route('admin.applications.approve', $seller));
        $response->assertRedirect();

        $seller->refresh();
        $this->assertSame('approved', $seller->status);

        // On login, approved seller accesses dashboard
        $this->actingAs($seller)->get(route('seller.dashboard'))->assertOk();
    }

    public function test_tc_sel_041_admin_rejects_application_with_reason(): void
    {
        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']);
        $seller = User::factory()->create(['usertype' => 'seller', 'status' => 'pending']);

        $response = $this->actingAs($admin)->patch(route('admin.applications.reject', $seller), [
            'reason' => 'DTI certificate is blurred and the business name cannot be read.',
        ]);

        $response->assertRedirect();
        $seller->refresh();
        $this->assertSame('rejected', $seller->status);
        $this->assertSame('DTI certificate is blurred and the business name cannot be read.', $seller->rejection_reason);
    }

    public function test_tc_sel_042_rejected_seller_corrects_and_resubmits(): void
    {
        Storage::fake('local');
        $seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'rejected',
            'rejection_reason' => 'DTI certificate is blurred and the business name cannot be read.',
            'line_of_business' => 'Home & Living',
            'email_verified_at' => now(),
        ]);

        $replacementDoc = UploadedFile::fake()->create('dti_certificate_clear.pdf', 1000, 'application/pdf');

        $response = $this->actingAs($seller)->post(route('seller.account.documents.update'), [
            'line_of_business' => 'Home & Living',
            'business_permit' => $replacementDoc,
        ]);

        $response->assertRedirect();
        $seller->refresh();
        $this->assertSame('pending', $seller->status);
        $this->assertNull($seller->rejection_reason);
    }

    public function test_tc_sel_043_non_admin_opens_approval_page(): void
    {
        $buyer = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);

        $response = $this->actingAs($buyer)->get(route('admin.applications'));
        $response->assertForbidden();
    }

    public function test_tc_sel_044_approved_seller_opens_other_role_pages(): void
    {
        $seller = User::factory()->create(['usertype' => 'seller', 'status' => 'approved']);

        $this->actingAs($seller)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($seller)->get(route('sorting-center.dashboard'))->assertForbidden();
        $this->actingAs($seller)->get(route('rider.dashboard'))->assertForbidden();
    }
}
