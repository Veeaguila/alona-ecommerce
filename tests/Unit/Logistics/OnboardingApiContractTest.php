<?php

namespace Tests\Unit\Logistics;

use App\Models\LogisticsRegistration;
use App\Models\PartnerApplication;
use App\Models\User;
use App\Notifications\LogisticsEmailOtp;
use App\Services\LogisticsOnboarding;
use App\Services\PhilippineAddressService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Isolated RAM fixtures only: no RefreshDatabase, migrations, seeders or live SQL. */
class OnboardingApiContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!$this->app->environment('testing') || config('database.default') !== 'sqlite'
            || DB::connection()->getDatabaseName() !== ':memory:'
            || config('mail.default') !== 'array' || config('cache.default') !== 'array') {
            throw new \LogicException('Onboarding fixtures require isolated SQLite :memory: and array transports.');
        }
        $this->assertSame('', DB::connection()->getPdo()->query('PRAGMA database_list')->fetch(\PDO::FETCH_ASSOC)['file']);
        Notification::fake();
        Storage::fake('local');
        // These ephemeral fixture tables describe only the existing models exercised here.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            foreach (['name', 'first_name', 'last_name', 'password', 'usertype', 'status'] as $field) $table->string($field);
            $table->string('email')->unique();
            $table->string('contact_no')->nullable()->unique();
            foreach (['google_id', 'province', 'municipality', 'barangay', 'street_address', 'address', 'rejection_reason', 'remember_token'] as $field) $table->text($field)->nullable();
            $table->text('address_geography')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
        Schema::create('sorting_centers', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id')->unique();
            foreach (['business_name', 'id_doc_url', 'permit_doc_url', 'approval_status'] as $field) $table->string($field);
            $table->timestamps();
        });
        Schema::create('partner_applications', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id')->unique(); $table->string('role');
            foreach (['details', 'address', 'documents'] as $field) $table->json($field);
            $table->timestamp('terms_accepted_at'); $table->timestamp('privacy_accepted_at'); $table->timestamps();
        });
        Schema::create('logistics_registrations', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id')->unique();
            foreach (['request_key', 'company_key', 'sec_number', 'tin', 'mobile'] as $field) $table->string($field)->unique();
            $table->string('otp_hash')->nullable(); $table->timestamp('otp_expires_at')->nullable();
            $table->integer('otp_attempts')->default(0); $table->timestamps();
        });
        Schema::create('areas', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('sorting_center_id');
            $table->string('name'); $table->string('city_code')->nullable(); $table->timestamps();
        });
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id(); $table->morphs('tokenable'); $table->string('name'); $table->string('token', 64)->unique();
            $table->text('abilities')->nullable(); $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable(); $table->timestamps();
        });
    }

    private function payload(): array
    {
        $data = app(PhilippineAddressService::class)->data();
        $city = collect($data['cities'])->first(fn ($row) => $row['region_code'] === '1300000000' && str_contains($row['name'], 'Pasig'));
        $barangay = collect($data['barangays'])->firstWhere('city_code', $city['code']);
        $input = ['first_name' => 'Synthetic', 'last_name' => 'Operator', 'position' => 'Operations',
            'email' => 'onboarding@example.test', 'contact_no' => '09171234567',
            'password' => 'Synthetic#123', 'password_confirmation' => 'Synthetic#123',
            'business_name' => 'Synthetic Logistics', 'registration_number' => 'CS202012345', 'tin' => '234-567-890-000',
            'region_code' => $city['region_code'], 'province_code' => $city['province_code'],
            'province_selection' => $city['province_code'] ?: 'independent',
            'city_code' => $city['code'], 'barangay_code' => $barangay['code'],
            'street' => 'Synthetic address', 'postal_code' => '1600',
            'service_region_codes' => ['1300000000'], 'terms' => true, 'privacy' => true, 'request_key' => (string) Str::uuid()];
        foreach (LogisticsOnboarding::DOCUMENTS as $field) $input[$field] = UploadedFile::fake()->create($field.'.pdf', 1, 'application/pdf');
        return $input;
    }

    private function register(): array
    {
        $response = $this->post('/api/sorting-center/onboarding/register', $this->payload(), ['Accept' => 'application/json']);
        $response->assertCreated();
        return [$response, User::firstOrFail(), $response->json('token')];
    }

    private function code(User $user): string
    {
        return Notification::sent($user, LogisticsEmailOtp::class)->last()->code;
    }

    public function test_registration_returns_only_an_owned_non_operational_session(): void
    {
        [$response, $user, $token] = $this->register();
        $response->assertJsonPath('account_status', 'unverified')->assertJsonPath('sorting_center.approval_status', 'pending')
            ->assertJsonMissingPath('password')->assertJsonMissingPath('otp')->assertJsonMissingPath('request_key');
        $this->assertSame('unverified', $user->status);
        $this->assertCount(4, $user->partnerApplication->documents);
        $this->withToken($token)->getJson('/api/sorting-center/reports?from=2026-10-01&to=2026-10-25')->assertForbidden();
    }

    public function test_replayed_key_never_authenticates_an_existing_account(): void
    {
        $input = $this->payload();
        $this->post('/api/sorting-center/onboarding/register', $input, ['Accept' => 'application/json'])->assertCreated();
        $this->post('/api/sorting-center/onboarding/register', $input, ['Accept' => 'application/json'])
            ->assertConflict()->assertJsonMissingPath('token');
        $input['request_key'] = (string) Str::uuid();
        $this->post('/api/sorting-center/onboarding/register', $input, ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonMissingPath('token');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertCount(4, Storage::disk('local')->allFiles());
    }

    public function test_v2_login_cannot_grant_operational_access_or_allow_suspended_accounts(): void
    {
        [, $user] = $this->register();
        $input = ['email' => $user->email, 'password' => 'Synthetic#123'];
        $this->postJson('/api/sorting-center/login', $input)->assertOk()->assertJsonPath('account_status', 'unverified');
        $user->update(['status' => 'rejected']);
        $this->postJson('/api/sorting-center/login', $input)->assertOk()->assertJsonPath('account_status', 'rejected');
        $user->update(['status' => 'suspended']);
        $this->postJson('/api/sorting-center/login', $input)->assertForbidden();
    }

    public function test_verification_is_single_use_and_never_admin_approval(): void
    {
        [, $user, $token] = $this->register();
        $code = $this->code($user);
        $this->withToken($token)->postJson('/api/sorting-center/onboarding/verify-email', ['otp' => $code])->assertOk();
        $this->assertSame('pending', $user->fresh()->status);
        $this->assertSame('pending', $user->sortingCenter->fresh()->approval_status);
        $this->assertNull(LogisticsRegistration::firstOrFail()->otp_hash);
        $this->postJson('/api/sorting-center/onboarding/verify-email', ['otp' => $code])->assertUnprocessable();
    }

    public function test_resend_invalidates_previous_code_and_returns_no_code(): void
    {
        [, $user, $token] = $this->register();
        $old = $this->code($user);
        $this->withToken($token)->postJson('/api/sorting-center/onboarding/resend-code')->assertOk()->assertJsonMissingPath('otp');
        $new = $this->code($user);
        $this->assertNotSame($old, $new);
        $this->postJson('/api/sorting-center/onboarding/verify-email', ['otp' => $old])->assertUnprocessable();
        $this->postJson('/api/sorting-center/onboarding/verify-email', ['otp' => $new])->assertOk();
    }

    public function test_expired_codes_are_rejected_without_approval(): void
    {
        [, $user, $token] = $this->register();
        $code = $this->code($user);
        $this->travel(6)->minutes();
        try {
            $this->withToken($token)->postJson('/api/sorting-center/onboarding/verify-email', ['otp' => $code])->assertUnprocessable();
            $this->assertSame('unverified', $user->fresh()->status);
        } finally { $this->travelBack(); }
    }

    public function test_attempt_limit_applies_even_to_the_correct_code(): void
    {
        [, $user] = $this->register();
        $code = $this->code($user);
        $wrong = $code === '000000' ? '000001' : '000000';
        $service = app(LogisticsOnboarding::class);
        for ($i = 0; $i < 5; $i++) {
            try { $service->verify($user, $wrong); $this->fail('Incorrect OTP accepted.'); }
            catch (ValidationException $exception) { $this->assertArrayHasKey('otp', $exception->errors()); }
        }
        $this->assertSame(5, LogisticsRegistration::firstOrFail()->otp_attempts);
        $this->expectException(ValidationException::class);
        $service->verify($user, $code);
    }

    public function test_resubmission_replaces_only_owned_documents_and_returns_to_pending_once(): void
    {
        [, $user, $token] = $this->register();
        app(LogisticsOnboarding::class)->verify($user, $this->code($user));
        $user = $user->fresh();
        $user->update(['status' => 'rejected', 'rejection_reason' => 'Synthetic review']);
        $user->sortingCenter->update(['approval_status' => 'rejected']);
        $old = $user->partnerApplication->documents;
        $this->withToken($token)->post('/api/sorting-center/onboarding/resubmit',
            ['permit_doc' => UploadedFile::fake()->create('replacement.pdf', 1, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
        $new = $user->partnerApplication->fresh()->documents;
        $this->assertSame($old['id_doc'], $new['id_doc']);
        $this->assertNotSame($old['permit_doc'], $new['permit_doc']);
        Storage::disk('local')->assertMissing($old['permit_doc']);
        Storage::disk('local')->assertExists($new['permit_doc']);
        $this->assertSame('pending', $user->fresh()->status);
        $this->post('/api/sorting-center/onboarding/resubmit',
            ['permit_doc' => UploadedFile::fake()->create('again.pdf', 1, 'application/pdf')], ['Accept' => 'application/json'])->assertForbidden();
        $this->assertSame($new, $user->partnerApplication->fresh()->documents);
    }

    public function test_unauthenticated_onboarding_and_web_nonce_bypass_are_rejected(): void
    {
        foreach (['verify-email', 'resend-code', 'resubmit'] as $action) {
            $this->postJson('/api/sorting-center/onboarding/'.$action)->assertUnauthorized();
        }
        $this->post('/register/logistics', ['request_key' => (string) Str::uuid()])->assertStatus(419);
    }
}
