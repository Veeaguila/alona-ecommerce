<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\SortingCenter;
use App\Models\User;
use App\Services\PhilippineAddressService;
use App\Services\RegistrationDocumentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PartnerOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();
    }

    private function payload(bool $rider = false): array
    {
        $addresses = app(PhilippineAddressService::class)->data();
        $city = collect($addresses['cities'])->first(fn ($row) => $row['region_code'] === '1300000000' && str_contains($row['name'], 'Quezon'));
        $barangay = collect($addresses['barangays'])->firstWhere('city_code', $city['code']);
        if ($rider) {
            $center = SortingCenter::where('business_name', 'Coverage Test Center')->first();
            if (! $center) {
                $center = SortingCenter::create(['business_name' => 'Coverage Test Center',
                    'user_id' => User::factory()->create(['email' => 'coverage-center@example.test', 'usertype' => 'sorting_center', 'status' => 'approved'])->id, 'approval_status' => 'approved']);
            }
            Area::firstOrCreate(['sorting_center_id' => $center->id, 'city_code' => $city['code']], ['name' => $city['name']]);
        }

        $key = (string) \Illuminate\Support\Str::uuid();
        if (! $rider) {
            $this->withSession(['logistics.registration_key' => $key]);
        }

        return ['first_name' => 'Alona', 'middle_name' => 'Test', 'last_name' => 'Partner',
            'email' => 'partner@example.test', 'contact_no' => '09123456789', 'password' => $rider ? 'password' : 'Partner@2026', 'password_confirmation' => $rider ? 'password' : 'Partner@2026',
            'region_code' => $city['region_code'], 'province_code' => '', 'city_code' => $city['code'], 'barangay_code' => $barangay['code'],
            'street' => 'Main Street', 'house_number' => '12', 'postal_code' => '1100', 'terms' => true, 'privacy' => true,
            'id_doc' => UploadedFile::fake()->create('id.pdf', 5, 'application/pdf'),
            ...($rider ? ['birthday' => '2000-01-01', 'vehicle_type' => 'bicycle', 'delivery_city_codes' => [$city['code']], 'sorting_center_id' => $center->id] : [
                'request_key' => $key, 'province_selection' => $city['province_code'] ?: 'independent',
                'position' => 'Manager', 'registration_number' => 'CS202012345', 'tin' => '234-567-890-000',
                'service_region_codes' => [$city['region_code']],
                'sec_doc' => UploadedFile::fake()->create('sec.pdf', 5, 'application/pdf'),
                'bir_doc' => UploadedFile::fake()->create('bir.pdf', 5, 'application/pdf'),
                'permit_doc' => UploadedFile::fake()->create('permit.pdf', 5, 'application/pdf'),
                'coverage_city_codes' => [$city['code']], 'operating_hours' => 'Monday to Friday, 9 AM–5 PM',
                'business_name' => 'Alona Delivery', 'business_type' => 'individual', 'authorized_representative' => 'Alona Partner',
                'business_email' => 'business@example.test', 'business_phone' => '+639123456789', 'services' => 'Parcel delivery', 'vehicle_types' => 'Bicycles', 'fleet_count' => 2,
            ])];
    }

    public function test_pending_rider_can_update_own_contact_without_gaining_operational_access(): void
    {
        $this->postJson('/api/rider/register', $this->payload(true))->assertCreated();
        $user = User::where('email', 'partner@example.test')->firstOrFail();
        Sanctum::actingAs($user);
        $this->patchJson('/api/partner/profile', ['contact_no' => '09987654321'])->assertOk();
        $this->assertSame('09987654321', $user->fresh()->contact_no);
        $this->assertSame('09987654321', $user->rider->fresh()->contact);
        $this->assertSame('09987654321', $user->partnerApplication->fresh()->details['contact_no']);
        $this->getJson('/api/rider/deliveries')->assertForbidden();
    }

    public function test_optional_existing_rider_sex_field_is_preserved(): void
    {
        $this->postJson('/api/rider/register', array_merge($this->payload(true), ['sex' => 'female']))->assertCreated();
        $user = User::where('email', 'partner@example.test')->firstOrFail();
        $this->assertSame('female', $user->rider->sex);
    }

    public function test_logistics_registration_saves_real_profile_and_private_documents(): void
    {
        $this->post('/register/logistics', $this->payload())->assertRedirect(route('application.status'));
        $user = User::where('email', 'partner@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('sorting_center', $user->usertype);
        $this->assertSame('pending', $user->sortingCenter->approval_status);
        $this->assertSame('Alona Delivery', $user->sortingCenter->business_name);
        $this->assertGreaterThan(0, $user->sortingCenter->areas->count());
        $this->getJson('/api/delivery-coverage')->assertJsonCount(0, 'data');
        $this->assertNull($user->partnerApplication->address['province']);
        Storage::disk('local')->assertExists($user->partnerApplication->documents['id_doc']);
        $this->assertFalse(str_starts_with($user->sortingCenter->id_doc_url, 'http'));
        $this->get('/sorting-center/dashboard')->assertRedirect(route('application.status'));
        $this->getJson('/partner/documents/'.$user->id.'/id_doc')->assertOk();
    }

    public function test_bicycle_rider_can_register_without_motorcycle_documents(): void
    {
        $this->post('/register/rider', $this->payload(true))->assertRedirect(route('application.status'));
        $user = User::where('email', 'partner@example.test')->firstOrFail();
        $this->assertSame('bicycle', $user->rider->vehicle_type);
        $this->assertNull($user->rider->license_doc_url);
        $this->assertNotNull($user->rider->sorting_center_id);
        $this->assertCount(1, $user->rider->areas);
        $this->assertArrayNotHasKey('operating_hours', $user->partnerApplication->details);
        $this->get('/rider/dashboard')->assertRedirect(route('application.status'));
    }

    public function test_motorcycle_requires_documents_license_and_legal_age(): void
    {
        $data = array_merge($this->payload(true), ['vehicle_type' => 'motorcycle', 'birthday' => now()->subYears(17)->toDateString()]);
        $this->postJson('/api/rider/register', $data)->assertUnprocessable()->assertJsonValidationErrors(['birthday', 'license_doc', 'or_cr_doc', 'plate_number', 'license_expiry', 'license_codes']);
        $this->assertDatabaseMissing('users', ['email' => 'partner@example.test']);
    }

    public function test_business_types_require_only_applicable_documentation(): void
    {
        $this->postJson('/api/sorting-center/register', array_merge($this->payload(), ['business_type' => 'corporation', 'permit_doc' => null, 'registration_number' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors(['permit_doc', 'registration_number']);
    }

    public function test_address_parent_mismatch_and_missing_consent_are_rejected(): void
    {
        $this->postJson('/api/rider/register', array_merge($this->payload(true), ['region_code' => '0100000000']))
            ->assertUnprocessable()->assertJsonValidationErrors(['city_code']);
        $this->postJson('/api/rider/register', array_merge($this->payload(true), ['terms' => false, 'privacy' => false]))
            ->assertUnprocessable()->assertJsonValidationErrors(['terms', 'privacy']);
    }

    public function test_duplicate_email_cannot_create_or_change_an_existing_role(): void
    {
        $buyer = User::factory()->create(['email' => 'partner@example.test', 'usertype' => 'buyer', 'status' => 'approved']);
        $this->post('/register/logistics', $this->payload())->assertSessionHasErrors('email');
        $this->assertSame('buyer', $buyer->fresh()->usertype);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('sorting_centers', 0);
    }

    public function test_approved_verified_logistics_user_reaches_existing_dashboard_and_scoped_api(): void
    {
        $this->post('/register/logistics', $this->payload());
        $user = User::where('email', 'partner@example.test')->firstOrFail();
        $user->update(['status' => 'approved']);
        $user->sortingCenter->update(['approval_status' => 'approved']);
        $this->get('/sorting-center/dashboard')->assertRedirect(route('application.status'));
        $user->markEmailAsVerified();
        $this->getJson('/api/delivery-coverage')->assertJsonCount($user->sortingCenter->areas()->count(), 'data');
        $this->actingAs($user)->get('/sorting-center/dashboard')->assertOk()->assertSee('window.logisticsSession', false);
        Sanctum::actingAs($user);
        $this->getJson('/api/sorting-center/dashboard')->assertOk();
    }

    public function test_private_documents_are_hidden_from_other_buyers(): void
    {
        $this->post('/register/rider', $this->payload(true));
        $owner = User::where('email', 'partner@example.test')->firstOrFail();
        $buyer = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $this->actingAs($buyer)->get('/partner/documents/'.$owner->id.'/id_doc')->assertForbidden();
        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']);
        $this->actingAs($admin)->get('/partner/documents/'.$owner->id.'/id_doc')->assertOk();
        $this->get('/admin/applications/'.$owner->id)->assertOk()->assertSee('partnerDocuments');
    }

    public function test_login_uses_backend_role_and_approval_instead_of_intended_url(): void
    {
        $this->post('/register/rider', $this->payload(true));
        $user = User::where('email', 'partner@example.test')->firstOrFail();
        $this->post('/logout');
        $this->withSession(['url.intended' => '/admin'])->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('application.status'));
        $user->update(['status' => 'approved']);
        $user->rider->update(['approval_status' => 'approved']);
        $user->markEmailAsVerified();
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('rider.dashboard'));
        $this->get('/rider/dashboard')->assertOk();
    }

    public function test_oauth_registration_uses_server_identity_without_password(): void
    {
        $data = $this->payload(true);
        unset($data['password'], $data['password_confirmation']);
        $this->withSession(['onboarding.google' => ['id' => 'google-123', 'email' => 'verified@example.test', 'role' => 'rider', 'expires_at' => now()->addHour()->timestamp]])
            ->post('/register/rider', $data)->assertRedirect(route('application.status'));
        $user = User::where('email', 'verified@example.test')->firstOrFail();
        $this->assertSame('google-123', $user->google_id);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame('pending', $user->status);
    }

    public function test_suspended_or_deactivated_accounts_cannot_use_operations(): void
    {
        $this->post('/register/rider', $this->payload(true));
        $user = User::where('email', 'partner@example.test')->firstOrFail();
        $user->update(['status' => 'approved']);
        $user->markEmailAsVerified();
        $user->rider->update(['approval_status' => 'approved', 'is_active' => false]);
        Sanctum::actingAs($user);
        $this->getJson('/api/rider/deliveries')->assertForbidden();
        $user->rider->update(['is_active' => true]);
        $user->update(['status' => 'suspended']);
        $this->getJson('/api/rider/deliveries')->assertForbidden();
    }

    public function test_failed_document_upload_cleans_up_files_and_creates_no_account(): void
    {
        Storage::disk('local')->put('partner-documents/orphan.pdf', 'test');
        $storage = \Mockery::mock(RegistrationDocumentStorage::class);
        $storage->shouldReceive('store')->once()->ordered()->andReturn('partner-documents/orphan.pdf');
        $storage->shouldReceive('store')->once()->ordered()->andThrow(new \RuntimeException('Storage unavailable'));
        $this->app->instance(RegistrationDocumentStorage::class, $storage);
        $data = array_merge($this->payload(), ['business_type' => 'sole_proprietor', 'registration_number' => 'CS202012345',
            'permit_doc' => UploadedFile::fake()->create('permit.pdf', 5, 'application/pdf')]);
        $this->post('/register/logistics', $data)->assertServerError();
        $this->assertDatabaseCount('users', 0);
        Storage::disk('local')->assertMissing('partner-documents/orphan.pdf');
    }

    public function test_signed_verification_link_returns_partner_to_application_status(): void
    {
        $this->post('/register/rider', $this->payload(true));
        $user = User::where('email', 'partner@example.test')->firstOrFail();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]);
        $this->get($url)->assertRedirect(route('application.status'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_portal_logout_revokes_only_its_session_token(): void
    {
        $this->post('/register/logistics', $this->payload());
        $user = User::where('email', 'partner@example.test')->firstOrFail();
        $user->update(['status' => 'approved']);
        $user->sortingCenter->update(['approval_status' => 'approved']);
        $user->markEmailAsVerified();
        $otherSession = $user->createToken('logistics-web', ['*'], now()->addHours(2))->accessToken;
        $this->actingAs($user)->get('/sorting-center/dashboard')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 2);
        $this->post('/logout')->assertRedirect('/');
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherSession->id]);
    }

    public function test_pending_seller_login_reaches_status_without_operational_access(): void
    {
        $seller = User::factory()->create(['usertype' => 'seller', 'status' => 'pending']);
        $this->post('/login', ['email' => $seller->email, 'password' => 'password'])->assertRedirect(route('application.status'));
        $this->assertAuthenticatedAs($seller);
        $this->get('/application/status')->assertOk();
        $this->get('/seller')->assertRedirect(route('application.status'));
    }
}
