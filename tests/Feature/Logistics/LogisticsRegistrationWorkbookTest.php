<?php

namespace Tests\Feature\Logistics;

use App\Models\LogisticsRegistration;
use App\Models\PartnerApplication;
use App\Models\SortingCenter;
use App\Models\User;
use App\Notifications\LogisticsEmailOtp;
use App\Services\PhilippineAddressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class LogisticsRegistrationWorkbookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Storage::fake('local');
        Notification::fake();
        Mail::fake();
        // Validation loops intentionally exceed route throttles; authentication remains enabled.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function payload(): array
    {
        $data = app(PhilippineAddressService::class)->data();
        $city = collect($data['cities'])->first(fn ($row) => $row['region_code'] === '1300000000' && str_contains($row['name'], 'Pasig'));
        $barangay = collect($data['barangays'])->firstWhere('city_code', $city['code']);

        return ['first_name' => 'Carlos Antonio', 'last_name' => 'Mendoza', 'position' => 'Operations Manager',
            'email' => 'operations@bilishatid.example.com', 'contact_no' => '09285551234',
            'password' => 'Hatid@2026', 'password_confirmation' => 'Hatid@2026',
            'business_name' => 'Bilis Hatid Express Inc.', 'registration_number' => 'CS202012345', 'tin' => '234-567-890-000',
            'region_code' => $city['region_code'], 'province_code' => $city['province_code'], 'province_selection' => $city['province_code'] ?: 'independent', 'city_code' => $city['code'], 'barangay_code' => $barangay['code'],
            'street' => 'Unit 5B, 88 West Capitol Drive', 'postal_code' => '1603',
            'service_region_codes' => ['1300000000', '0400000000'], 'terms' => true, 'privacy' => true,
            'request_key' => (string) Str::uuid(),
            'id_doc' => UploadedFile::fake()->create('govt_id.pdf', 5, 'application/pdf'),
            'sec_doc' => UploadedFile::fake()->create('sec_certificate.pdf', 5, 'application/pdf'),
            'bir_doc' => UploadedFile::fake()->create('bir_2303.pdf', 5, 'application/pdf'),
            'permit_doc' => UploadedFile::fake()->create('mayors_permit.pdf', 5, 'application/pdf')];
    }

    private function submit(array $input)
    {
        $response = $this->withSession(['logistics.registration_key' => $input['request_key']])->post('/register/logistics', $input);
        $this->assertNotSame(429, $response->status());

        return $response;
    }

    private function registered(bool $verified = false): User
    {
        $this->submit($this->payload())->assertRedirect(route('application.status'));
        $user = User::where('email', 'operations@bilishatid.example.com')->firstOrFail();
        if ($verified) {
            $this->post('/logistics/verify-email', ['otp' => $this->code($user)])->assertSessionHasNoErrors();
        }

        return $user->fresh();
    }

    private function code(User $user): string
    {
        return Notification::sent($user, LogisticsEmailOtp::class)->last()->code;
    }

    private function invalid(string $field, array $values): void
    {
        foreach ($values as $value) {
            $this->submit(array_replace($this->payload(), [$field => $value]))->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('logistics_registrations', 0);
    }

    public function test_tc_log_001_valid_registration(): void
    {
        $user = $this->registered();
        $this->assertSame('unverified', $user->status);
        $this->assertNull($user->email_verified_at);
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $this->code($user));
        Notification::assertSentToTimes($user, LogisticsEmailOtp::class, 1);
        $this->assertCount(4, $user->partnerApplication->documents);
    }

    public function test_browser_form_contract_reaches_otp_then_admin_review(): void
    {
        $this->get('/register/logistics')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Auth/LogisticsRegistration')->has('regions')
            ->where('requestKey', fn ($key) => Str::isUuid($key)));
        $input = $this->payload();
        $input['request_key'] = session('logistics.registration_key');
        // Browser FormData serializes accepted checkboxes as strings, not PHP booleans.
        $input['terms'] = '1';
        $input['privacy'] = '1';
        $this->withHeaders(['X-Inertia' => 'true', 'Accept' => 'text/html, application/xhtml+xml'])
            ->post('/register/logistics', $input)->assertSessionHasNoErrors()
            ->assertRedirect(route('application.status'));
        $user = User::where('email', $input['email'])->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('logistics_registrations', 1);
        $this->assertDatabaseCount('partner_applications', 1);
        $this->withoutHeader('X-Inertia');
        $this->get('/application/status')->assertInertia(fn ($page) => $page
            ->component('Auth/ApplicationStatus')->where('usesOtp', true)
            ->where('verified', false)->where('status', 'unverified'));
        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']);
        $this->actingAs($admin)->get('/admin/applications?status=pending&role=sorting_center')
            ->assertOk()->assertDontSee($input['email']);
        $this->actingAs($user)->from('/application/status')->post('/logistics/verify-email', ['otp' => $this->code($user)])
            ->assertSessionHasNoErrors()->assertRedirect(route('application.status'));
        $this->actingAs($admin)->get('/admin/applications?status=pending&role=sorting_center')
            ->assertOk()->assertSee($input['email']);
    }

    public function test_browser_submission_requires_csrf_and_the_rendered_registration_key(): void
    {
        // Laravel normally skips CSRF in PHPUnit. Enable the real middleware for this contract test.
        $csrf = new class($this->app, $this->app['encrypter']) extends \Illuminate\Foundation\Http\Middleware\PreventRequestForgery {
            protected function runningUnitTests() { return false; }
        };
        $this->app->instance(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, $csrf);
        $this->get('/register/logistics')->assertOk();
        $input = $this->payload();
        $input['request_key'] = session('logistics.registration_key');
        $token = session()->token();
        $this->post('/register/logistics', $input)->assertStatus(419);
        $this->withHeader('X-CSRF-TOKEN', $token)->post('/register/logistics',
            array_replace($input, ['request_key' => (string) Str::uuid()]))->assertStatus(419);
        $this->assertDatabaseCount('logistics_registrations', 0);
        $this->withHeader('X-CSRF-TOKEN', $token)->post('/register/logistics', $input)
            ->assertSessionHasNoErrors()->assertRedirect(route('application.status'));
        $this->assertDatabaseCount('logistics_registrations', 1);
    }

    public function test_tc_log_002_empty_form(): void
    {
        $key = (string) Str::uuid();
        $this->submit(['request_key' => $key])->assertSessionHasErrors(['first_name', 'last_name', 'position', 'email', 'contact_no',
            'password', 'password_confirmation', 'business_name', 'registration_number', 'tin', 'region_code', 'city_code', 'barangay_code',
            'street', 'postal_code', 'province_selection', 'service_region_codes', 'id_doc', 'sec_doc', 'bir_doc', 'permit_doc', 'terms', 'privacy']);
        $this->assertDatabaseCount('users', 0);
        Notification::assertNothingSent();
    }

    public function test_tc_log_003_each_required_field(): void
    {
        foreach (['first_name', 'last_name', 'position', 'email', 'contact_no', 'password', 'password_confirmation', 'business_name',
            'registration_number', 'tin', 'region_code', 'province_selection', 'city_code', 'barangay_code', 'street', 'postal_code', 'service_region_codes',
            'id_doc', 'sec_doc', 'bir_doc', 'permit_doc'] as $field) {
            $input = $this->payload();
            unset($input[$field]);
            $response = $this->submit($input);
            $this->assertNotNull(session('errors'), 'Missing validation for '.$field.'; HTTP '.$response->status());
            $response->assertSessionHasErrors($field);
        }
        // Province is mandatory when the selected PSGC city belongs to a province.
        $data = app(PhilippineAddressService::class)->data();
        $city = collect($data['cities'])->first(fn ($row) => (bool) $row['province_code'] && collect($data['barangays'])->contains('city_code', $row['code']));
        $barangay = collect($data['barangays'])->firstWhere('city_code', $city['code']);
        $this->submit(array_replace($this->payload(), ['region_code' => $city['region_code'], 'city_code' => $city['code'],
            'barangay_code' => $barangay['code'], 'province_code' => '']))->assertSessionHasErrors('province_code');
        $this->assertDatabaseCount('users', 0);
        Notification::assertNothingSent();
    }

    public function test_tc_log_004_invalid_names(): void
    {
        $this->invalid('first_name', ['Carlos Antonio7']);
        $this->invalid('last_name', ['Dela Cruz@#']);
    }

    public function test_tc_log_005_unicode_names(): void
    {
        $this->submit(array_replace($this->payload(), ['first_name' => 'Iñigo Rafael', 'last_name' => "Muñoz-D'Angelo"]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['first_name' => 'Iñigo Rafael', 'last_name' => "Muñoz-D'Angelo"]);
    }

    public function test_tc_log_006_name_boundaries(): void
    {
        $this->invalid('first_name', ['C', str_repeat('A', 51)]);
        foreach (['Co', str_repeat('A', 50)] as $index => $name) {
            $p = $this->payload();
            $this->submit(array_replace($p, ['first_name' => $name, 'email' => "names$index@example.com", 'contact_no' => '0917000000'.$index,
                'business_name' => 'Boundary Company '.$index, 'registration_number' => 'CS20201234'.$index, 'tin' => '345-678-901-00'.$index]))->assertSessionHasNoErrors();
            $this->post('/logout');
        }
    }

    public function test_tc_log_007_invalid_emails(): void
    {
        $this->invalid('email', ['operations.bilishatidexample.com', 'operations.bilishatid@', '@example.com', 'operations.bilishatid@example', 'operations bilishatid@example.com']);
    }

    public function test_tc_log_008_duplicate_email(): void
    {
        User::factory()->create(['email' => 'operations@bilishatid.example.com']);
        $this->invalid('email', ['operations@bilishatid.example.com']);
    }

    public function test_tc_log_009_duplicate_email_case(): void
    {
        User::factory()->create(['email' => 'operations@bilishatid.example.com']);
        $this->invalid('email', ['OPERATIONS@BILISHATID.EXAMPLE.COM']);
    }

    public function test_tc_log_010_email_trim(): void
    {
        $this->submit(array_replace($this->payload(), ['email' => '  logistics.test01@example.com  ']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'logistics.test01@example.com']);
    }

    public function test_tc_log_011_invalid_mobile(): void
    {
        $this->invalid('contact_no', ['0917ABC4567', '0917-123-456', '12345678901', '02-8123-4567']);
    }

    public function test_tc_log_012_mobile_boundaries(): void
    {
        $this->invalid('contact_no', ['0917123456', '091712345678']);
        $this->submit(array_replace($this->payload(), ['contact_no' => '09170000001']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['contact_no' => '+639170000001']);
    }

    public function test_tc_log_013_duplicate_mobile(): void
    {
        User::factory()->create(['contact_no' => '09285551234']);
        $this->invalid('contact_no', ['09285551234']);
    }

    public function test_tc_log_014_duplicate_mobile_international(): void
    {
        User::factory()->create(['contact_no' => '09285551234']);
        $this->invalid('contact_no', ['+639285551234']);
    }

    public function test_tc_log_015_password_length(): void
    {
        $this->invalid('password', ['Hati@26']);
        $this->submit(array_replace($this->payload(), ['password' => 'Hatid@26', 'password_confirmation' => 'Hatid@26']))->assertSessionHasNoErrors();
    }

    public function test_tc_log_016_password_character_types(): void
    {
        $this->invalid('password', ['hatid@2026', 'HATID@2026', 'Hatid@abcd', 'Hatid2026']);
    }

    public function test_tc_log_017_password_confirmation(): void
    {
        $this->submit(array_replace($this->payload(), ['password_confirmation' => 'Hatid@2027']))->assertSessionHasErrors('password_confirmation');
    }

    // TC-LOG-018 is executed in scripts/check-logistics-ui.cjs.

    public function test_tc_log_019_consent(): void
    {
        $this->invalid('terms', [false]);
        $this->invalid('privacy', [false]);
    }

    public function test_tc_log_020_duplicate_submission(): void
    {
        $p = $this->payload();
        $this->submit($p)->assertRedirect(route('application.status'));
        $this->post('/logout');
        $this->submit($p)->assertRedirect(route('application.status'));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('logistics_registrations', 1);
        Notification::assertCount(1);
    }

    public function test_tc_log_021_duplicate_company(): void
    {
        $owner = User::factory()->create(['usertype' => 'sorting_center']);
        SortingCenter::create(['user_id' => $owner->id, 'business_name' => 'Bilis Hatid Express Inc.']);
        $this->invalid('business_name', ['Bilis Hatid Express Inc.', 'bilis hatid express inc.']);
    }

    public static function submission_commit_keys(): array
    {
        return ['same owned submission' => [true], 'different submission' => [false]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('submission_commit_keys')]
    public function test_tc_log_020_retry_after_another_request_commits_during_upload(bool $sameKey): void
    {
        $input = $this->payload();
        $calls = 0;
        $storage = \Mockery::mock(\App\Services\RegistrationDocumentStorage::class);
        $storage->shouldReceive('store')->times(4)->andReturnUsing(function () use (&$calls, $input, $sameKey) {
            $path = 'partner-documents/retry-'.(++$calls).'.pdf';
            Storage::disk('local')->put($path, 'retry fixture');
            if ($calls === 4) {
                // The other request has committed after validation but before our transaction.
                $winner = User::factory()->unverified()->create(['usertype' => 'sorting_center', 'status' => 'unverified',
                    'email' => $input['email'], 'contact_no' => '+639285551234']);
                LogisticsRegistration::create(['user_id' => $winner->id, 'company_key' => mb_strtolower($input['business_name']),
                    'sec_number' => $input['registration_number'], 'tin' => $input['tin'], 'mobile' => $winner->contact_no,
                    'request_key' => hash('sha256', $sameKey ? $input['request_key'] : (string) Str::uuid())]);
                Storage::disk('local')->put('partner-documents/winner.pdf', 'winner fixture');
                SortingCenter::create(['user_id' => $winner->id, 'business_name' => $input['business_name'],
                    'approval_status' => 'pending', 'id_doc_url' => 'partner-documents/winner.pdf']);
                app(\App\Services\LogisticsOnboarding::class)->sendOtp($winner);
            }

            return $path;
        });
        $this->app->instance(\App\Services\RegistrationDocumentStorage::class, $storage);
        $response = $this->submit($input);
        if ($sameKey) {
            $response->assertRedirect(route('application.status'))->assertSessionHasNoErrors();
            $this->assertAuthenticatedAs(User::first());
        } else {
            $response->assertSessionHasErrors('email');
            $this->assertGuest();
        }
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('logistics_registrations', 1);
        Notification::assertCount(1);
        $this->assertSame(['partner-documents/winner.pdf'], Storage::disk('local')->allFiles());
    }

    public function test_tc_log_022_sec_format(): void
    {
        $this->invalid('registration_number', ['CS20201234', '202012345CS', 'CS2020-12345', '12345678901']);
    }

    public function test_tc_log_023_duplicate_sec(): void
    {
        $user = User::factory()->create();
        PartnerApplication::create(['user_id' => $user->id, 'role' => 'sorting_center', 'details' => ['registration_number' => 'CS202012345'], 'address' => [], 'documents' => [], 'terms_accepted_at' => now(), 'privacy_accepted_at' => now()]);
        $this->invalid('registration_number', ['CS202012345']);
    }

    public function test_tc_log_024_multiple_regions(): void
    {
        $this->submit(array_replace($this->payload(), ['service_region_codes' => ['1300000000', '0300000000', '0400000000']]))->assertSessionHasNoErrors();
        $user = User::where('email', 'operations@bilishatid.example.com')->firstOrFail();
        $this->assertCount(3, $user->partnerApplication->details['service_region_codes']);
        $regions = collect(app(PhilippineAddressService::class)->data()['cities'])->whereIn('code', $user->sortingCenter->areas->pluck('city_code'))->pluck('region_code')->unique()->sort()->values()->all();
        $this->assertSame(['0300000000', '0400000000', '1300000000'], $regions);
    }

    public function test_tc_log_025_tin_format(): void
    {
        $this->invalid('tin', ['123-456-789-00', '123-456-789-0001', '123-456-78A-000']);
        $this->submit(array_replace($this->payload(), ['tin' => '345-678-901-000']))->assertSessionHasNoErrors();
    }

    public function test_tc_log_026_duplicate_tin(): void
    {
        $seller = User::factory()->create(['usertype' => 'seller']);
        PartnerApplication::create(['user_id' => $seller->id, 'role' => 'seller', 'details' => ['tin' => '234-567-890-000'], 'address' => [], 'documents' => [], 'terms_accepted_at' => now(), 'privacy_accepted_at' => now()]);
        $this->invalid('tin', ['234-567-890-000']);
    }

    public function test_tc_log_027_address_parent_validation(): void
    {
        $this->submit(array_replace($this->payload(), ['region_code' => '0700000000']))->assertSessionHasErrors('city_code');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_tc_log_028_postal_code(): void
    {
        $this->invalid('postal_code', ['160', '16030', '16A3']);
        $this->submit($this->payload())->assertSessionHasNoErrors();
    }

    public function test_tc_log_029_document_types(): void
    {
        foreach (['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png'] as $extension => $mime) {
            $p = $this->payload();
            $suffix = count(User::all());
            foreach (['id_doc', 'sec_doc', 'bir_doc', 'permit_doc'] as $field) {
                $p[$field] = UploadedFile::fake()->create("document.$extension", 5, $mime);
            }
            $this->submit(array_replace($p, ['email' => "doc$suffix@example.com", 'contact_no' => '0917000000'.$suffix,
                'business_name' => 'Document Company '.$suffix, 'registration_number' => 'CS20201234'.$suffix, 'tin' => '345-678-901-00'.$suffix]))->assertSessionHasNoErrors();
            $this->post('/logout');
        }
        $this->assertDatabaseCount('users', 3);
    }

    public function test_tc_log_030_rejected_document_types(): void
    {
        $this->invalid('permit_doc', [UploadedFile::fake()->create('price_list.docx', 320, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            UploadedFile::fake()->create('banner.gif', 480, 'image/gif'), UploadedFile::fake()->create('installer.exe', 2100, 'application/x-msdownload')]);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_tc_log_031_upload_boundaries(): void
    {
        $this->invalid('permit_doc', [UploadedFile::fake()->create('size_5.1MB.jpg', 5223, 'image/jpeg')]);
        foreach ([5018, 5120] as $index => $size) {
            $this->submit(array_replace($this->payload(), ['permit_doc' => UploadedFile::fake()->create('size.jpg', $size, 'image/jpeg'),
                'email' => "size$index@example.com", 'contact_no' => '0917000000'.$index,
                'business_name' => 'Size Company '.$index, 'registration_number' => 'CS20201234'.$index, 'tin' => '345-678-901-00'.$index]))->assertSessionHasNoErrors();
            $this->post('/logout');
        }
    }

    public function test_tc_log_032_correct_otp(): void
    {
        $user = $this->registered(true);
        $this->assertSame('pending', $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull(LogisticsRegistration::first()->otp_hash);
    }

    public function test_tc_log_033_incorrect_otp(): void
    {
        $user = $this->registered();
        $wrong = $this->code($user) === '000000' ? '111111' : '000000';
        $this->post('/logistics/verify-email', ['otp' => $wrong])->assertSessionHasErrors('otp');
        $this->assertSame('unverified', $user->fresh()->status);
    }

    public function test_tc_log_034_otp_format(): void
    {
        $user = $this->registered();
        foreach (['48291', '4829137', '48A91B', ''] as $code) {
            $this->post('/logistics/verify-email', ['otp' => $code])->assertSessionHasErrors('otp');
        }
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_tc_log_035_otp_expiry(): void
    {
        $user = $this->registered();
        $code = $this->code($user);
        $this->travel(301)->seconds();
        $this->post('/logistics/verify-email', ['otp' => $code])->assertSessionHasErrors('otp');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_tc_log_036_otp_resend(): void
    {
        $user = $this->registered();
        $first = $this->code($user);
        $this->post('/logistics/resend-code')->assertSessionHasNoErrors();
        $second = $this->code($user);
        $this->assertNotSame($first, $second);
        $this->post('/logistics/verify-email', ['otp' => $first])->assertSessionHasErrors('otp');
        $this->post('/logistics/verify-email', ['otp' => $second])->assertSessionHasNoErrors();
        $this->post('/logistics/verify-email', ['otp' => $second])->assertSessionHasErrors('otp');
        $this->assertSame('pending', $user->fresh()->status);
    }

    public function test_tc_log_037_unverified_login(): void
    {
        $user = $this->registered();
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'Hatid@2026'])->assertRedirect(route('application.status'));
        $this->get('/sorting-center/dashboard')->assertRedirect(route('application.status'));
    }

    public function test_tc_log_038_pending_login(): void
    {
        $user = $this->registered(true);
        $this->get('/sorting-center/dashboard')->assertRedirect(route('application.status'));
        $this->getJson('/api/sorting-center/dashboard')->assertForbidden();
    }

    public function test_tc_log_039_admin_application(): void
    {
        $user = $this->registered(true);
        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']);
        $this->actingAs($admin)->get('/admin/applications?status=pending&role=sorting_center')->assertOk()->assertSee('operations@bilishatid.example.com');
        $this->get('/admin/applications/'.$user->id)->assertOk()->assertSee('sec_doc')->assertSee('bir_doc');
        foreach ($user->partnerApplication->documents as $field => $path) {
            $this->get('/partner/documents/'.$user->id.'/'.$field)->assertOk();
        }
    }

    public function test_tc_log_040_approval(): void
    {
        $user = $this->registered(true);
        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']);
        Mail::shouldReceive('raw')->once()->withArgs(function ($body, $callback) use ($user) {
            $this->assertStringContainsString('approved', $body);
            $message = new \Illuminate\Mail\Message(new \Symfony\Component\Mime\Email);
            $callback($message);
            $this->assertSame($user->email, $message->getTo()[0]->getAddress());

            return true;
        });
        $this->actingAs($admin)->patch('/admin/applications/'.$user->id.'/approve')->assertSessionHasNoErrors();
        $this->assertSame('approved', $user->fresh()->status);
        $this->assertSame('approved', $user->sortingCenter->fresh()->approval_status);
        $this->actingAs($user->fresh())->get('/sorting-center/dashboard')->assertOk();
    }

    public function test_tc_log_041_rejection(): void
    {
        $user = $this->registered(true);
        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']);
        $reason = "Mayor's permit expired on 12/31/2025.";
        Mail::shouldReceive('raw')->once()->withArgs(function ($body, $callback) use ($user, $reason) {
            $this->assertStringContainsString($reason, $body);
            $message = new \Illuminate\Mail\Message(new \Symfony\Component\Mime\Email);
            $callback($message);
            $this->assertSame($user->email, $message->getTo()[0]->getAddress());

            return true;
        });
        $this->actingAs($admin)->patch('/admin/applications/'.$user->id.'/reject', ['reason' => $reason])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $user->fresh()->status);
        $this->assertSame($reason, $user->fresh()->rejection_reason);
        $this->actingAs($user->fresh())->get('/sorting-center/dashboard')->assertRedirect(route('application.status'));
        $this->get('/application/status')->assertOk()->assertInertia(fn ($page) => $page->where('reason', $reason));
    }

    public function test_tc_log_042_resubmission(): void
    {
        $user = $this->registered(true);
        $old = $user->partnerApplication->documents['permit_doc'];
        $user->update(['status' => 'rejected', 'rejection_reason' => 'Expired permit']);
        $user->sortingCenter->update(['approval_status' => 'rejected']);
        $this->actingAs($user)->post('/logistics/resubmit', ['permit_doc' => UploadedFile::fake()->create('mayors_permit_2026.pdf', 1200, 'application/pdf')])->assertSessionHasNoErrors();
        $this->assertSame('pending', $user->fresh()->status);
        $this->assertNull($user->fresh()->rejection_reason);
        $new = $user->partnerApplication->fresh()->documents['permit_doc'];
        $this->assertNotSame($old, $new);
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($new);
    }

    public function test_tc_log_043_admin_only(): void
    {
        $user = $this->registered(true);
        $buyer = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $this->actingAs($buyer)->get('/admin/applications')->assertForbidden();
        $this->get('/admin/applications/'.$user->id)->assertForbidden();
        $this->patch('/admin/applications/'.$user->id.'/approve')->assertForbidden();
        $this->patch('/admin/applications/'.$user->id.'/reject', ['reason' => 'Invalid'])->assertForbidden();
    }

    public function test_tc_log_044_other_roles_denied(): void
    {
        $user = $this->registered(true);
        $user->update(['status' => 'approved']);
        $user->sortingCenter->update(['approval_status' => 'approved']);
        $this->actingAs($user->fresh())->get('/admin')->assertForbidden();
        $this->get('/seller')->assertForbidden();
        $this->get('/rider/dashboard')->assertForbidden();
    }

    public function test_otp_attempt_limit_requires_resend_and_never_approves(): void
    {
        $user = $this->registered();
        $code = $this->code($user);
        $wrong = $code === '000000' ? '111111' : '000000';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/logistics/verify-email', ['otp' => $wrong])->assertSessionHasErrors('otp');
        }
        $this->post('/logistics/verify-email', ['otp' => $code])->assertSessionHasErrors('otp');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->post('/logistics/resend-code')->assertSessionHasNoErrors();
        $this->post('/logistics/verify-email', ['otp' => $this->code($user)])->assertSessionHasNoErrors();
        $this->assertSame('pending', $user->fresh()->status);
    }

    public function test_otp_is_hashed_and_never_exposed_in_profile(): void
    {
        $user = $this->registered();
        $registration = LogisticsRegistration::first();
        $this->assertNotSame($this->code($user), $registration->otp_hash);
        $this->assertArrayNotHasKey('otp_hash', $registration->toArray());
        $this->getJson('/api/partner/profile')->assertOk()->assertDontSee($this->code($user))->assertDontSee('otp_hash');
    }

    public function test_legacy_signed_link_cannot_bypass_logistics_otp(): void
    {
        $user = $this->registered();
        $url = \Illuminate\Support\Facades\URL::temporarySignedRoute('verification.verify', now()->addMinutes(30),
            ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url)->assertRedirect(route('application.status'))->assertSessionHasErrors('otp');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_admin_cannot_approve_unverified_application(): void
    {
        $user = $this->registered();
        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']);
        $this->actingAs($admin)->patch('/admin/applications/'.$user->id.'/approve')->assertStatus(409);
        $this->assertSame('unverified', $user->fresh()->status);
    }

    public function test_resubmission_rejects_missing_or_invalid_documents_and_approved_accounts(): void
    {
        $user = $this->registered(true);
        $old = $user->partnerApplication->documents;
        $this->post('/logistics/resubmit', ['permit_doc' => UploadedFile::fake()->create('new.pdf', 5, 'application/pdf')])->assertForbidden();
        $user->update(['status' => 'rejected']);
        $user->sortingCenter->update(['approval_status' => 'rejected']);
        $this->actingAs($user)->post('/logistics/resubmit', [])->assertSessionHasErrors('permit_doc');
        $this->post('/logistics/resubmit', ['permit_doc' => UploadedFile::fake()->create('program.exe', 5, 'application/x-msdownload')])->assertSessionHasErrors('permit_doc');
        $this->assertSame($old, $user->partnerApplication->fresh()->documents);
        $this->assertSame('rejected', $user->fresh()->status);
    }

    public function test_private_documents_cannot_be_read_by_another_logistics_account(): void
    {
        $user = $this->registered(true);
        $other = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        $this->actingAs($other)->get('/partner/documents/'.$user->id.'/sec_doc')->assertForbidden();
        $this->get('/partner/documents/'.$user->id.'/bir_doc')->assertForbidden();
    }

    public function test_invalid_region_and_spoofed_file_extensions_are_rejected(): void
    {
        $this->invalid('service_region_codes', [['9999999999']]);
        $this->submit(array_replace($this->payload(), ['service_region_codes' => ['1300000000', '1300000000']]))
            ->assertSessionHasErrors(['service_region_codes.0', 'service_region_codes.1']);
        $this->invalid('permit_doc', [UploadedFile::fake()->create('installer.exe', 5, 'application/pdf')]);
    }

    public function test_another_role_cannot_reuse_logistics_mobile(): void
    {
        $this->registered();
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        User::factory()->create(['usertype' => 'buyer', 'contact_no' => '09285551234']);
    }

    public function test_foreign_browser_cannot_resume_a_submission(): void
    {
        $input = $this->payload();
        $this->submit($input)->assertRedirect();
        $this->post('/logout');
        $this->withSession(['logistics.registration_key' => (string) Str::uuid()])->post('/register/logistics', $input)->assertStatus(419);
        Notification::assertCount(1);
        $this->assertGuest();
    }

    public function test_malformed_arrays_return_validation_errors(): void
    {
        $this->invalid('email', [['bad' => 'value']]);
        $this->invalid('business_name', [['bad' => 'value']]);
    }

    public function test_google_login_does_not_bypass_otp(): void
    {
        $user = $this->registered();
        $this->post('/logout');
        $google = \Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $google->shouldReceive('getEmail')->andReturn($user->email);
        $google->shouldReceive('getId')->andReturn('logistics-google-test');
        $google->shouldReceive('getAvatar')->andReturn(null);
        $google->user = ['email_verified' => true];
        $driver = \Mockery::mock();
        $driver->shouldReceive('setHttpClient')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($google);
        \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
        $this->get('/auth/google/callback')->assertRedirect(route('application.status'));
        $this->assertSame('unverified', $user->fresh()->status);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_google_registration_uses_server_identity_and_requires_otp(): void
    {
        $input = $this->payload();
        unset($input['password'], $input['password_confirmation']);
        $input['email'] = 'client-forged@example.com';
        $this->withSession(['onboarding.google' => ['id' => 'google-logistics-signup', 'email' => 'google-company@example.com',
            'role' => 'sorting_center', 'expires_at' => now()->addHour()->timestamp]]);
        $this->submit($input)->assertRedirect(route('application.status'))->assertSessionHasNoErrors();
        $user = User::where('email', 'google-company@example.com')->firstOrFail();
        $this->assertSame('google-logistics-signup', $user->google_id);
        $this->assertSame('unverified', $user->status);
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentToTimes($user, LogisticsEmailOtp::class, 1);
    }

    public function test_google_expired_or_other_role_identity_cannot_skip_password(): void
    {
        foreach ([['sorting_center', now()->subMinute()->timestamp], ['buyer', now()->addHour()->timestamp]] as [$role, $expires]) {
            $input = $this->payload();
            unset($input['password'], $input['password_confirmation']);
            $this->withSession(['onboarding.google' => ['id' => 'google-expired', 'email' => 'identity@example.com',
                'role' => $role, 'expires_at' => $expires]]);
            $this->submit($input)->assertSessionHasErrors(['password', 'password_confirmation']);
        }
        $this->assertDatabaseCount('users', 0);
        Notification::assertNothingSent();
    }
}
