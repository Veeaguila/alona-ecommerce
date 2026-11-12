<?php

namespace Tests\Feature;

use App\Models\PartnerApplication;
use App\Models\SortingCenter;
use App\Models\User;
use App\Services\PhilippineAddressService;
use App\Services\RegistrationDocumentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function city(string $part): array
    {
        return collect(app(PhilippineAddressService::class)->data()['cities'])->first(fn ($city) => $city['region_code'] === '1300000000' && str_contains($city['name'], $part));
    }

    private function center(array $cities = [], string $status = 'approved', string $accountStatus = 'approved'): SortingCenter
    {
        $user = User::factory()->create(['usertype' => 'sorting_center', 'status' => $accountStatus]);
        $center = SortingCenter::create(['user_id' => $user->id, 'business_name' => 'Partner '.$user->id, 'approval_status' => $status]);
        foreach ($cities as $city) {
            $center->areas()->create(['name' => $city['name'], 'city_code' => $city['code']]);
        }

        return $center;
    }

    private function riderPayload(SortingCenter $center, array $cities): array
    {
        // Residence deliberately differs from the chosen delivery cities.
        $home = $this->city('Quezon');
        $barangay = collect(app(PhilippineAddressService::class)->data()['barangays'])->firstWhere('city_code', $home['code']);

        return ['first_name' => 'Test', 'last_name' => 'Rider', 'email' => 'coverage-rider@example.test', 'contact_no' => '09123456789',
            'password' => 'password', 'password_confirmation' => 'password', 'birthday' => '2000-01-01', 'vehicle_type' => 'bicycle',
            'region_code' => $home['region_code'], 'province_code' => '', 'city_code' => $home['code'], 'barangay_code' => $barangay['code'],
            'street' => 'Home Street', 'house_number' => '1', 'postal_code' => '1100', 'terms' => true, 'privacy' => true,
            'sorting_center_id' => $center->id, 'delivery_city_codes' => array_column($cities, 'code'),
            'id_doc' => UploadedFile::fake()->create('id.pdf', 5, 'application/pdf')];
    }

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
    }

    public function test_directory_only_lists_coded_areas_from_approved_available_partners(): void
    {
        $city = $this->city('Makati');
        $first = $this->center([$city]);
        $second = $this->center([$city]);
        $this->center([$this->city('Pasig')], 'pending');
        $this->center([$this->city('Taguig')], 'approved', 'suspended');
        $first->areas()->create(['name' => 'An unspecified legacy zone']);
        $result = $this->getJson('/api/delivery-coverage')->assertOk()->assertJsonCount(1, 'data')->json('data');
        $this->assertSame($city['code'], $result[0]['code']);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], array_column($result[0]['partners'], 'id'));
        $this->assertArrayNotHasKey('user', $result[0]['partners'][0]);
    }

    public function test_rider_saves_all_selected_area_links_without_a_schedule(): void
    {
        $cities = [$this->city('Makati'), $this->city('Pasig')];
        $center = $this->center($cities);
        $this->post('/register/rider', $this->riderPayload($center, $cities))->assertRedirect(route('application.status'));
        $user = User::where('email', 'coverage-rider@example.test')->firstOrFail();
        $this->assertSame('pending', $user->status);
        $this->assertSame($center->id, $user->rider->sorting_center_id);
        $this->assertEqualsCanonicalizing(array_column($cities, 'code'), $user->rider->areas->pluck('city_code')->all());
        $this->assertSame($this->city('Quezon')['code'], $user->partnerApplication->address['city_code']);
        $this->assertArrayNotHasKey('operating_hours', $user->partnerApplication->details);
        $this->patch('/partner/profile', ['contact_no' => '09987654321', 'operating_hours' => 'Injected schedule'])->assertRedirect();
        $this->assertArrayNotHasKey('operating_hours', $user->partnerApplication->fresh()->details);
    }

    public function test_unserved_city_and_mismatched_partner_are_rejected(): void
    {
        $served = $this->city('Makati');
        $unserved = $this->city('Pasig');
        $center = $this->center([$served]);
        $this->postJson('/api/rider/register', $this->riderPayload($center, [$unserved]))
            ->assertUnprocessable()->assertJsonValidationErrors('delivery_city_codes');
        $other = $this->center([$unserved]);
        $this->postJson('/api/rider/register', $this->riderPayload($other, [$served]))
            ->assertUnprocessable()->assertJsonValidationErrors('delivery_city_codes');
        $this->assertDatabaseMissing('users', ['email' => 'coverage-rider@example.test']);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_multi_area_application_requires_one_partner_covering_every_area(): void
    {
        $firstCity = $this->city('Makati');
        $secondCity = $this->city('Pasig');
        $center = $this->center([$firstCity]);
        $this->center([$secondCity]);
        $this->postJson('/api/rider/register', $this->riderPayload($center, [$firstCity, $secondCity]))
            ->assertUnprocessable()->assertJsonValidationErrors('delivery_city_codes');
    }

    public function test_partner_suspended_after_lookup_is_rejected_on_submission(): void
    {
        $city = $this->city('Makati');
        $center = $this->center([$city]);
        $this->getJson('/api/delivery-coverage')->assertJsonCount(1, 'data');
        $center->user->update(['status' => 'suspended']);
        $this->postJson('/api/rider/register', $this->riderPayload($center, [$city]))
            ->assertUnprocessable()->assertJsonValidationErrors('sorting_center_id');
        $this->getJson('/api/delivery-coverage')->assertJsonCount(0, 'data');
    }

    public function test_transaction_rechecks_coverage_after_validation_and_cleans_up_uploaded_documents(): void
    {
        $city = $this->city('Makati');
        $center = $this->center([$city]);
        $this->mock(RegistrationDocumentStorage::class, function ($mock) use ($center) {
            $mock->shouldReceive('store')->once()->andReturnUsing(function () use ($center) {
                Storage::disk('local')->put('partner-documents/race.pdf', 'test');
                $center->areas()->delete();

                return 'partner-documents/race.pdf';
            });
        });
        $this->postJson('/api/rider/register', $this->riderPayload($center, [$city]))
            ->assertUnprocessable()->assertJsonValidationErrors('delivery_city_codes');
        Storage::disk('local')->assertMissing('partner-documents/race.pdf');
        $this->assertDatabaseMissing('users', ['email' => 'coverage-rider@example.test']);
    }

    public function test_owned_coverage_creation_reuses_legacy_area_and_is_idempotent(): void
    {
        $center = $this->center();
        $city = $this->city('Makati');
        $legacy = $center->areas()->create(['name' => 'Makati']);
        Sanctum::actingAs($center->user);
        $this->postJson('/api/sorting-center/coverage', ['coverage_city_codes' => [$city['code']]])->assertCreated();
        $this->postJson('/api/sorting-center/coverage', ['coverage_city_codes' => [$city['code']]])->assertCreated();
        $this->assertSame($city['code'], $legacy->fresh()->city_code);
        $this->assertSame('Makati', $legacy->fresh()->name);
        $this->assertDatabaseCount('areas', 1);
        $this->postJson('/api/sorting-center/coverage', ['coverage_city_codes' => ['0000000000']])
            ->assertUnprocessable()->assertJsonValidationErrors('coverage_city_codes');
    }

    public function test_duplicate_codes_and_missing_partner_cannot_be_submitted(): void
    {
        $city = $this->city('Makati');
        $center = $this->center([$city]);
        $payload = $this->riderPayload($center, [$city, $city]);
        unset($payload['sorting_center_id']);
        $this->postJson('/api/rider/register', $payload)->assertUnprocessable()
            ->assertJsonValidationErrors(['sorting_center_id', 'delivery_city_codes.0']);
    }

    public function test_coverage_updates_are_scoped_and_homonymous_cities_keep_distinct_codes(): void
    {
        $center = $this->center();
        $other = $this->center();
        Sanctum::actingAs($center->user);
        $codes = ['0105532000', '1804524000'];
        $this->postJson('/api/sorting-center/coverage', ['coverage_city_codes' => $codes, 'sorting_center_id' => $other->id])->assertCreated();
        $this->assertSame(0, $other->areas()->count());
        $this->assertEqualsCanonicalizing($codes, $center->areas()->pluck('city_code')->all());
        $response = $this->getJson('/api/delivery-coverage')->assertOk()->assertJsonCount(2, 'data');
        $this->assertNotSame($response->json('data.0.label'), $response->json('data.1.label'));
    }

    public function test_new_partner_coverage_requires_both_approval_and_email_verification(): void
    {
        $city = $this->city('Makati');
        $center = $this->center([$city]);
        PartnerApplication::create(['user_id' => $center->user_id, 'role' => 'sorting_center', 'details' => [], 'address' => [], 'documents' => [], 'terms_accepted_at' => now(), 'privacy_accepted_at' => now()]);
        $center->user->forceFill(['email_verified_at' => null])->save();
        $this->getJson('/api/delivery-coverage')->assertJsonCount(0, 'data');
        $this->postJson('/api/rider/register', $this->riderPayload($center, [$city]))->assertUnprocessable()->assertJsonValidationErrors('sorting_center_id');
        $center->user->markEmailAsVerified();
        $this->getJson('/api/delivery-coverage')->assertJsonCount(1, 'data');
        $center->update(['approval_status' => 'rejected']);
        $this->getJson('/api/delivery-coverage')->assertJsonCount(0, 'data');
    }

    public function test_migration_maps_only_unambiguous_legacy_names_and_preserves_records(): void
    {
        $center = $this->center();
        $unique = $center->areas()->create(['name' => 'Taguig']);
        $ambiguous = $center->areas()->create(['name' => 'City of San Carlos']);
        $custom = $center->areas()->create(['name' => 'East Zone']);
        $migration = require database_path('migrations/2026_10_12_000002_add_city_codes_to_service_areas.php');
        $migration->down();
        $migration->up();
        $this->assertSame($this->city('Taguig')['code'], $unique->fresh()->city_code);
        $this->assertNull($ambiguous->fresh()->city_code);
        $this->assertNull($custom->fresh()->city_code);
        $this->assertDatabaseCount('areas', 3);
        $this->assertSame('Taguig', $unique->fresh()->name);
    }
}
