<?php

namespace Tests\Feature\Logistics;

use App\Models\Rider;
use App\Models\SortingCenter;
use App\Models\User;
use App\Notifications\RiderActivationNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RiderBlackBoxTest extends TestCase
{
    use RefreshDatabase;

    private User $logisticsUser;
    private SortingCenter $sortingCenter;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();

        // Approved logistics account Bilis Hatid Express Inc.
        $this->logisticsUser = User::factory()->create([
            'email' => 'operations@bilishatid.example.com',
            'usertype' => 'sorting_center',
            'status' => 'approved',
            'password' => Hash::make('Hatid@2026'),
        ]);

        $this->sortingCenter = SortingCenter::create([
            'user_id' => $this->logisticsUser->id,
            'business_name' => 'Bilis Hatid Express Inc.',
            'name' => 'Bilis Hatid Hub 1',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
    }

    private function validRiderPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Pedro Jose',
            'last_name' => 'Garcia',
            'date_of_birth' => '1995-03-22',
            'email' => 'pedrojose.garcia@example.com',
            'mobile_number' => '09451239876',
            'license_number' => 'N01-23-456789',
            'license_expiry' => '2030-03-22',
            'vehicle_type' => 'Motorcycle',
            'plate_number' => '123ABC',
            'driver_license' => UploadedFile::fake()->create('drivers_license.jpg', 1500, 'image/jpeg'),
            'or_cr' => UploadedFile::fake()->create('or_cr.pdf', 900, 'application/pdf'),
        ], $overrides);
    }

    /**
     * TC-RID-001: Create a rider with all valid data
     */
    public function test_tc_rid_001_create_rider_with_all_valid_data(): void
    {
        $payload = $this->validRiderPayload();

        $response = $this->actingAs($this->logisticsUser)
            ->postJson('/api/sorting-center/riders', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.approval_status', 'pending_activation')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('users', [
            'email' => 'pedrojose.garcia@example.com',
            'usertype' => 'rider',
            'status' => 'pending_activation',
        ]);

        $this->assertDatabaseHas('riders', [
            'sorting_center_id' => $this->sortingCenter->id,
            'plate_number' => '123ABC',
            'license_number' => 'N01-23-456789',
            'approval_status' => 'pending_activation',
        ]);

        $user = User::where('email', 'pedrojose.garcia@example.com')->first();
        Notification::assertSentTo($user, RiderActivationNotification::class);
    }

    /**
     * TC-RID-002: Submit a completely empty form
     */
    public function test_tc_rid_002_submit_completely_empty_form(): void
    {
        $response = $this->actingAs($this->logisticsUser)
            ->postJson('/api/sorting-center/riders', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'first_name',
                'last_name',
                'date_of_birth',
                'email',
                'mobile_number',
                'license_number',
                'license_expiry',
                'vehicle_type',
                'plate_number',
                'driver_license',
                'or_cr',
            ]);

        $this->assertDatabaseCount('riders', 0);
        Notification::assertNothingSent();
    }

    /**
     * TC-RID-003: Leave one required field blank at a time
     */
    public function test_tc_rid_003_leave_one_required_field_blank_at_a_time(): void
    {
        $requiredFields = [
            'first_name',
            'last_name',
            'date_of_birth',
            'email',
            'mobile_number',
            'license_number',
            'license_expiry',
            'vehicle_type',
            'plate_number',
            'driver_license',
            'or_cr',
        ];

        foreach ($requiredFields as $field) {
            $payload = $this->validRiderPayload();
            unset($payload[$field]);

            $response = $this->actingAs($this->logisticsUser)
                ->postJson('/api/sorting-center/riders', $payload);

            $response->assertStatus(422)
                ->assertJsonValidationErrors([$field]);
        }
    }

    /**
     * TC-RID-004: Name with numbers or symbols
     */
    public function test_tc_rid_004_name_with_numbers_or_symbols(): void
    {
        $payload = $this->validRiderPayload([
            'first_name' => 'Pedro Jose99',
            'last_name' => 'Dela Cruz@#',
        ]);

        $response = $this->actingAs($this->logisticsUser)
            ->postJson('/api/sorting-center/riders', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'last_name']);
    }

    /**
     * TC-RID-005: Name length at the limits
     */
    public function test_tc_rid_005_name_length_at_the_limits(): void
    {
        // 1 char: reject
        $res1 = $this->actingAs($this->logisticsUser)
            ->postJson('/api/sorting-center/riders', $this->validRiderPayload(['first_name' => 'P']));
        $res1->assertStatus(422)->assertJsonValidationErrors(['first_name']);

        // 51 chars: reject
        $res51 = $this->actingAs($this->logisticsUser)
            ->postJson('/api/sorting-center/riders', $this->validRiderPayload([
                'first_name' => 'Maria Cristina Angelica Bernadette Josefina Leonora',
            ]));
        $res51->assertStatus(422)->assertJsonValidationErrors(['first_name']);

        // 2 chars: accept
        $res2 = $this->actingAs($this->logisticsUser)
            ->postJson('/api/sorting-center/riders', $this->validRiderPayload([
                'first_name' => 'Po',
                'email' => 'po@example.com',
                'mobile_number' => '09451239871',
                'license_number' => 'N01-23-456781',
                'plate_number' => '121ABC',
            ]));
        $res2->assertStatus(201);

        // 50 chars: accept
        $res50 = $this->actingAs($this->logisticsUser)
            ->postJson('/api/sorting-center/riders', $this->validRiderPayload([
                'first_name' => 'Maria Cristina Angelica Bernadette Josefina Leonor',
                'email' => 'maria@example.com',
                'mobile_number' => '09451239872',
                'license_number' => 'N01-23-456782',
                'plate_number' => '122ABC',
            ]));
        $res50->assertStatus(201);
    }

    /**
     * TC-RID-006: Age at the 18-year limit
     */
    public function test_tc_rid_006_age_at_the_18_year_limit(): void
    {
        // 17 years old: reject
        $res17 = $this->actingAs($this->logisticsUser)
            ->postJson('/api/sorting-center/riders', $this->validRiderPayload([
                'date_of_birth' => Carbon::today()->subYears(18)->addDay()->format('Y-m-d'),
            ]));
        $res17->assertStatus(422)->assertJsonValidationErrors(['date_of_birth']);

        // 18 years old today: accept
        $res18 = $this->actingAs($this->logisticsUser)
            ->postJson('/api/sorting-center/riders', $this->validRiderPayload([
                'date_of_birth' => Carbon::today()->subYears(18)->format('Y-m-d'),
                'email' => 'rider18@example.com',
                'mobile_number' => '09451239873',
                'license_number' => 'N01-23-456783',
                'plate_number' => '123ABD',
            ]));
        $res18->assertStatus(201);

        // 18 years and 1 day old: accept
        $res18plus = $this->actingAs($this->logisticsUser)
            ->postJson('/api/sorting-center/riders', $this->validRiderPayload([
                'date_of_birth' => Carbon::today()->subYears(18)->subDay()->format('Y-m-d'),
                'email' => 'rider18plus@example.com',
                'mobile_number' => '09451239874',
                'license_number' => 'N01-23-456784',
                'plate_number' => '124ABC',
            ]));
        $res18plus->assertStatus(201);
    }

    /**
     * TC-RID-007: Email in an invalid format
     */
    public function test_tc_rid_007_email_in_an_invalid_format(): void
    {
        $invalidEmails = [
            'pedrojose.garciaexample.com',
            'pedrojose.garcia@',
            '@example.com',
            'pedrojose.garcia@example',
            'pedrojose garcia@example.com',
        ];

        foreach ($invalidEmails as $email) {
            $response = $this->actingAs($this->logisticsUser)
                ->postJson('/api/sorting-center/riders', $this->validRiderPayload(['email' => $email]));

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
        }
    }

    /**
     * TC-RID-008: Email already used by an account of another role
     */
    public function test_tc_rid_008_email_already_used_by_another_role(): void
    {
        User::factory()->create([
            'email' => 'juanmiguel.delacruz@example.com',
            'usertype' => 'buyer',
        ]);

        $response = $this->actingAs($this->logisticsUser)
            ->postJson('/api/sorting-center/riders', $this->validRiderPayload([
                'email' => 'juanmiguel.delacruz@example.com',
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * TC-RID-009: Mobile number in an invalid format
     */
    public function test_tc_rid_009_mobile_number_in_invalid_format(): void
    {
        $invalidNumbers = [
            '0917ABC4567',
            '0917-123-456',
            '12345678901',
            '02-8123-4567',
        ];

        foreach ($invalidNumbers as $mobile) {
            $response = $this->actingAs($this->logisticsUser)
                ->postJson('/api/sorting-center/riders', $this->validRiderPayload(['mobile_number' => $mobile]));

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['mobile_number']);
        }
    }

    /**
     * TC-RID-010: Mobile number length at the limits
     */
    public function test_tc_rid_010_mobile_number_length_at_the_limits(): void
    {
        // 10 digits: reject
        $res10 = $this->actingAs($this->logisticsUser)
            ->postJson('/api/sorting-center/riders', $this->validRiderPayload(['mobile_number' => '0917123456']));
        $res10->assertStatus(422)->assertJsonValidationErrors(['mobile_number']);

        // 12 digits: reject
        $res12 = $this->actingAs($this->logisticsUser)
            ->postJson('/api/sorting-center/riders', $this->validRiderPayload(['mobile_number' => '091712345678']));
        $res12->assertStatus(422)->assertJsonValidationErrors(['mobile_number']);

        // 11 digits: accept
        $res11 = $this->actingAs($this->logisticsUser)
            ->postJson('/api/sorting-center/riders', $this->validRiderPayload(['mobile_number' => '09170000001']));
        $res11->assertStatus(201);
    }

    /**
     * TC-RID-011: Mobile number that is already registered
     */
    public function test_tc_rid_011_mobile_number_already_registered(): void
    {
        User::factory()->create([
            'contact_no' => '09171234567',
            'email' => 'other@example.com',
        ]);

        $response = $this->actingAs($this->logisticsUser)
            ->postJson('/api/sorting-center/riders', $this->validRiderPayload([
                'mobile_number' => '09171234567',
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['mobile_number']);
    }

    /**
     * TC-RID-012: Driver's license number in an invalid format
     */
    public function test_tc_rid_012_drivers_license_number_invalid_format(): void
    {
        $invalidLicenses = [
            'N01-23-45678',   // one digit short
            'N01-23-4567890', // one digit extra
            '101-23-456789',  // no letter
            'N0123456789X',   // letter at end
        ];

        foreach ($invalidLicenses as $license) {
            $response = $this->actingAs($this->logisticsUser)
                ->postJson('/api/sorting-center/riders', $this->validRiderPayload(['license_number' => $license]));

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['license_number']);
        }
    }

    /**
     * TC-RID-013: Driver's license number already registered
     */
    public function test_tc_rid_013_drivers_license_number_already_registered(): void
    {
        $first = $this->actingAs($this->logisticsUser)
            ->postJson('/api/sorting-center/riders', $this->validRiderPayload(['license_number' => 'N01-23-456789']));
        $first->assertStatus(201);

        $response = $this->actingAs($this->logisticsUser)
            ->postJson('/api/sorting-center/riders', $this->validRiderPayload([
                'email' => 'second@example.com',
                'mobile_number' => '09459999999',
                'plate_number' => '456XYZ',
                'license_number' => 'N01-23-456789',
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['license_number']);
    }

    /**
     * TC-RID-014: License expiry date at the limit
     */
    public function test_tc_rid_014_license_expiry_date_at_the_limit(): void
    {
        // Yesterday: reject
        $resPast = $this->actingAs($this->logisticsUser)
            ->postJson('/api/sorting-center/riders', $this->validRiderPayload([
                'license_expiry' => Carbon::yesterday()->format('Y-m-d'),
            ]));
        $resPast->assertStatus(422)->assertJsonValidationErrors(['license_expiry']);

        // Today: accept
        $resToday = $this->actingAs($this->logisticsUser)
            ->postJson('/api/sorting-center/riders', $this->validRiderPayload([
                'license_expiry' => Carbon::today()->format('Y-m-d'),
                'email' => 'today@example.com',
                'mobile_number' => '09451111111',
                'license_number' => 'N01-23-111111',
                'plate_number' => '111AAA',
            ]));
        $resToday->assertStatus(201);

        // Tomorrow: accept
        $resTomorrow = $this->actingAs($this->logisticsUser)
            ->postJson('/api/sorting-center/riders', $this->validRiderPayload([
                'license_expiry' => Carbon::tomorrow()->format('Y-m-d'),
                'email' => 'tomorrow@example.com',
                'mobile_number' => '09452222222',
                'license_number' => 'N01-23-222222',
                'plate_number' => '222AAA',
            ]));
        $resTomorrow->assertStatus(201);
    }

    /**
     * TC-RID-015: Plate number already registered
     */
    public function test_tc_rid_015_plate_number_already_registered(): void
    {
        $first = $this->actingAs($this->logisticsUser)
            ->postJson('/api/sorting-center/riders', $this->validRiderPayload(['plate_number' => '123ABC']));
        $first->assertStatus(201);

        $response = $this->actingAs($this->logisticsUser)
            ->postJson('/api/sorting-center/riders', $this->validRiderPayload([
                'email' => 'second@example.com',
                'mobile_number' => '09458888888',
                'license_number' => 'N01-23-888888',
                'plate_number' => '123ABC',
            ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plate_number']);
    }

    /**
     * TC-RID-016: Upload documents in each accepted file type
     */
    public function test_tc_rid_016_upload_documents_in_accepted_file_types(): void
    {
        $types = [
            ['driver_license.jpg', 'image/jpeg'],
            ['driver_license.png', 'image/png'],
            ['driver_license.pdf', 'application/pdf'],
        ];

        foreach ($types as $idx => [$fileName, $mimeType]) {
            $response = $this->actingAs($this->logisticsUser)
                ->postJson('/api/sorting-center/riders', $this->validRiderPayload([
                    'email' => "doc{$idx}@example.com",
                    'mobile_number' => '0945333333' . $idx,
                    'license_number' => 'N01-23-33333' . $idx,
                    'plate_number' => '33' . $idx . 'ABC',
                    'driver_license' => UploadedFile::fake()->create($fileName, 1000, $mimeType),
                    'or_cr' => UploadedFile::fake()->create('or_cr.pdf', 800, 'application/pdf'),
                ]));

            $response->assertStatus(201);
        }
    }

    /**
     * TC-RID-017: Upload a file type that is not accepted
     */
    public function test_tc_rid_017_upload_file_type_not_accepted(): void
    {
        $unsupported = [
            ['price_list.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            ['store_banner.gif', 'image/gif'],
            ['installer.exe', 'application/x-msdownload'],
        ];

        foreach ($unsupported as [$fileName, $mimeType]) {
            $response = $this->actingAs($this->logisticsUser)
                ->postJson('/api/sorting-center/riders', $this->validRiderPayload([
                    'driver_license' => UploadedFile::fake()->create($fileName, 500, $mimeType),
                ]));

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['driver_license']);
        }
    }

    /**
     * TC-RID-018: File size at the limit
     */
    public function test_tc_rid_018_file_size_at_the_limit(): void
    {
        // 4.9 MB (4900 KB): accept
        $res49 = $this->actingAs($this->logisticsUser)
            ->postJson('/api/sorting-center/riders', $this->validRiderPayload([
                'email' => 'size49@example.com',
                'mobile_number' => '09454444441',
                'license_number' => 'N01-23-444441',
                'plate_number' => '441ABC',
                'driver_license' => UploadedFile::fake()->create('size_4.9MB.jpg', 4900, 'image/jpeg'),
            ]));
        $res49->assertStatus(201);

        // 5.0 MB (5120 KB): accept
        $res50 = $this->actingAs($this->logisticsUser)
            ->postJson('/api/sorting-center/riders', $this->validRiderPayload([
                'email' => 'size50@example.com',
                'mobile_number' => '09454444442',
                'license_number' => 'N01-23-444442',
                'plate_number' => '442ABC',
                'driver_license' => UploadedFile::fake()->create('size_5.0MB.jpg', 5120, 'image/jpeg'),
            ]));
        $res50->assertStatus(201);

        // 5.1 MB (5222 KB): reject
        $res51 = $this->actingAs($this->logisticsUser)
            ->postJson('/api/sorting-center/riders', $this->validRiderPayload([
                'email' => 'size51@example.com',
                'mobile_number' => '09454444443',
                'license_number' => 'N01-23-444443',
                'plate_number' => '443ABC',
                'driver_license' => UploadedFile::fake()->create('size_5.1MB.jpg', 5222, 'image/jpeg'),
            ]));
        $res51->assertStatus(422)
            ->assertJsonValidationErrors(['driver_license']);
    }

    /**
     * TC-RID-019: Click Save several times quickly (idempotency check)
     */
    public function test_tc_rid_019_click_save_several_times_quickly(): void
    {
        $payload = $this->validRiderPayload();

        $res1 = $this->actingAs($this->logisticsUser)->postJson('/api/sorting-center/riders', $payload);
        $res1->assertStatus(201);

        $res2 = $this->actingAs($this->logisticsUser)->postJson('/api/sorting-center/riders', $payload);
        $res2->assertStatus(422);

        $res3 = $this->actingAs($this->logisticsUser)->postJson('/api/sorting-center/riders', $payload);
        $res3->assertStatus(422);

        $this->assertEquals(1, Rider::where('plate_number', '123ABC')->count());
    }

    /**
     * Helper to create a pending activation rider
     */
    private function createPendingRider(string $email = 'pedrojose.garcia@example.com'): array
    {
        $user = User::factory()->create([
            'email' => $email,
            'name' => 'Pedro Jose Garcia',
            'usertype' => 'rider',
            'status' => 'pending_activation',
            'password' => Hash::make(Str::random(32)),
        ]);

        $token = Str::random(40);
        $rider = Rider::create([
            'user_id' => $user->id,
            'sorting_center_id' => $this->sortingCenter->id,
            'name' => $user->name,
            'contact' => '09451239876',
            'birthday' => '1995-03-22',
            'vehicle_type' => 'Motorcycle',
            'plate_number' => '123ABC',
            'license_number' => 'N01-23-456789',
            'license_expiry' => '2030-03-22',
            'activation_token' => $token,
            'activation_token_expires_at' => now()->addHours(24),
            'approval_status' => 'pending_activation',
            'is_active' => false,
        ]);

        return [$user, $rider, $token];
    }

    /**
     * TC-RID-020: Rider activates the account with a valid password
     */
    public function test_tc_rid_020_rider_activates_account_with_valid_password(): void
    {
        [$user, $rider, $token] = $this->createPendingRider();

        $response = $this->postJson("/rider/activate/{$token}", [
            'password' => 'Rider@2026',
            'password_confirmation' => 'Rider@2026',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'active');

        $user->refresh();
        $rider->refresh();

        $this->assertEquals('approved', $user->status);
        $this->assertEquals('approved', $rider->approval_status);
        $this->assertTrue($rider->is_active);
        $this->assertNull($rider->activation_token);
        $this->assertTrue(Hash::check('Rider@2026', $user->password));
    }

    /**
     * TC-RID-021: Set a password that breaks the password rule
     */
    public function test_tc_rid_021_set_password_that_breaks_the_password_rule(): void
    {
        [$user, $rider, $token] = $this->createPendingRider();

        $invalidPasswords = [
            'Ride@26',      // 7 chars (too short)
            'rider@2026',   // No uppercase
            'RIDER@2026',   // No lowercase
            'Rider@abcd',   // No number
            'Rider2026',    // No special character
        ];

        foreach ($invalidPasswords as $pwd) {
            $response = $this->postJson("/rider/activate/{$token}", [
                'password' => $pwd,
                'password_confirmation' => $pwd,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['password']);

            $user->refresh();
            $this->assertEquals('pending_activation', $user->status);
        }
    }

    /**
     * TC-RID-022: Confirm Password does not match
     */
    public function test_tc_rid_022_confirm_password_does_not_match(): void
    {
        [$user, $rider, $token] = $this->createPendingRider();

        $response = $this->postJson("/rider/activate/{$token}", [
            'password' => 'Rider@2026',
            'password_confirmation' => 'Rider@2027',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * TC-RID-023: Open an expired activation link
     */
    public function test_tc_rid_023_open_expired_activation_link(): void
    {
        [$user, $rider, $token] = $this->createPendingRider();
        $rider->update(['activation_token_expires_at' => now()->subMinute()]);

        // GET expired link
        $getRes = $this->getJson("/rider/activate/{$token}");
        $getRes->assertStatus(410)
            ->assertJsonPath('status', 'expired');

        // POST expired link
        $postRes = $this->postJson("/rider/activate/{$token}", [
            'password' => 'Rider@2026',
            'password_confirmation' => 'Rider@2026',
        ]);
        $postRes->assertStatus(422);

        $user->refresh();
        $this->assertEquals('pending_activation', $user->status);
    }

    /**
     * TC-RID-024: Logistics resends the activation email
     */
    public function test_tc_rid_024_logistics_resends_activation_email(): void
    {
        [$user, $rider, $firstToken] = $this->createPendingRider();

        $resend = $this->actingAs($this->logisticsUser)
            ->postJson("/api/sorting-center/riders/{$rider->id}/resend-activation");

        $resend->assertStatus(200);
        $secondToken = $resend->json('data.activation_token');

        $this->assertNotEquals($firstToken, $secondToken);

        // First link no longer works
        $firstRes = $this->getJson("/rider/activate/{$firstToken}");
        $firstRes->assertStatus(404);

        // Second link works
        $secondRes = $this->getJson("/rider/activate/{$secondToken}");
        $secondRes->assertStatus(200)
            ->assertJsonPath('status', 'valid');
    }

    /**
     * TC-RID-025: Reuse the activation link after activating
     */
    public function test_tc_rid_025_reuse_activation_link_after_activating(): void
    {
        [$user, $rider, $token] = $this->createPendingRider();

        // First activation succeeds
        $activate = $this->postJson("/rider/activate/{$token}", [
            'password' => 'Rider@2026',
            'password_confirmation' => 'Rider@2026',
        ]);
        $activate->assertStatus(200);

        // Second activation attempt with the same token
        $reuse = $this->postJson("/rider/activate/{$token}", [
            'password' => 'Rider@2026New',
            'password_confirmation' => 'Rider@2026New',
        ]);
        $reuse->assertStatus(422);
    }

    /**
     * TC-RID-026: Rider logs in before activating
     */
    public function test_tc_rid_026_rider_logs_in_before_activating(): void
    {
        [$user, $rider, $token] = $this->createPendingRider();

        // Web login attempt
        $webRes = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Rider@2026',
        ]);
        $this->assertGuest();

        // API login attempt
        $apiRes = $this->postJson('/api/rider/login', [
            'email' => $user->email,
            'password' => 'Rider@2026',
        ]);
        $this->assertTrue(in_array($apiRes->status(), [401, 403], true));
    }

    /**
     * TC-RID-027: Rider logs in after activating
     */
    public function test_tc_rid_027_rider_logs_in_after_activating(): void
    {
        [$user, $rider, $token] = $this->createPendingRider();

        $this->postJson("/rider/activate/{$token}", [
            'password' => 'Rider@2026',
            'password_confirmation' => 'Rider@2026',
        ])->assertStatus(200);

        // Login via API
        $loginRes = $this->postJson('/api/rider/login', [
            'email' => $user->email,
            'password' => 'Rider@2026',
        ]);

        $loginRes->assertStatus(200)
            ->assertJsonPath('approval_status', 'approved')
            ->assertJsonPath('is_active', true);

        // Access rider dashboard
        $user->refresh();
        $dashRes = $this->actingAs($user)->get('/rider/dashboard');
        $dashRes->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->component('Rider/Dashboard')
                ->where('rider.name', 'Pedro Jose Garcia')
                ->where('rider.company_name', 'Bilis Hatid Express Inc.')
            );
    }

    /**
     * TC-RID-028: Rider is visible only to the company that created it
     */
    public function test_tc_rid_028_rider_is_visible_only_to_company_that_created_it(): void
    {
        [$user, $rider] = $this->createPendingRider();

        // Company 2: Agila Cargo Movers Corp.
        $agilaUser = User::factory()->create([
            'email' => 'admin@agilacargo.example.com',
            'usertype' => 'sorting_center',
            'status' => 'approved',
        ]);
        $agilaCenter = SortingCenter::create([
            'user_id' => $agilaUser->id,
            'business_name' => 'Agila Cargo Movers Corp.',
            'name' => 'Agila Hub 1',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        // Company 1 list shows Pedro Jose Garcia
        $res1 = $this->actingAs($this->logisticsUser)->getJson('/api/sorting-center/riders');
        $res1->assertStatus(200)
            ->assertJsonFragment(['name' => 'Pedro Jose Garcia']);

        // Company 2 list does NOT show Pedro Jose Garcia
        $res2 = $this->actingAs($agilaUser)->getJson('/api/sorting-center/riders');
        $res2->assertStatus(200)
            ->assertJsonMissing(['name' => 'Pedro Jose Garcia']);
    }

    /**
     * TC-RID-029: No public rider registration
     */
    public function test_tc_rid_029_no_public_rider_registration(): void
    {
        // Public registration GET /register/rider redirects to login
        $regRes = $this->get('/register/rider');
        $regRes->assertRedirect('/?auth=login');

        // Accessing Add Rider form unauthenticated redirects to login
        $addRiderRes = $this->get('/sorting-center/riders');
        $addRiderRes->assertRedirect('/login');
    }

    /**
     * TC-RID-030: Buyer or seller opens the Add Rider form
     */
    public function test_tc_rid_030_buyer_or_seller_opens_add_rider_form(): void
    {
        $buyer = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $seller = User::factory()->create(['usertype' => 'seller', 'status' => 'approved']);

        // Buyer gets 403
        $this->actingAs($buyer)->get('/sorting-center/riders')->assertStatus(403);
        $this->actingAs($buyer)->postJson('/api/sorting-center/riders', $this->validRiderPayload())->assertStatus(403);

        // Seller gets 403
        $this->actingAs($seller)->get('/sorting-center/riders')->assertStatus(403);
        $this->actingAs($seller)->postJson('/api/sorting-center/riders', $this->validRiderPayload())->assertStatus(403);
    }

    /**
     * TC-RID-031: Pending logistics account tries to add a rider
     */
    public function test_tc_rid_031_pending_logistics_tries_to_add_rider(): void
    {
        $pendingUser = User::factory()->create([
            'usertype' => 'sorting_center',
            'status' => 'pending',
        ]);
        SortingCenter::create([
            'user_id' => $pendingUser->id,
            'business_name' => 'Pending Express Inc.',
            'name' => 'Pending Hub',
            'approval_status' => 'pending',
            'is_active' => true,
        ]);

        // Web access redirects to application.status
        $this->actingAs($pendingUser)->get('/sorting-center/riders')
            ->assertRedirect(route('application.status'));

        // API access receives 403
        $this->actingAs($pendingUser)->postJson('/api/sorting-center/riders', $this->validRiderPayload())
            ->assertStatus(403)
            ->assertJsonPath('approval_status', 'pending');
    }

    /**
     * TC-RID-032: Active rider opens another role's pages
     */
    public function test_tc_rid_032_active_rider_opens_another_roles_pages(): void
    {
        [$user, $rider, $token] = $this->createPendingRider();

        $this->postJson("/rider/activate/{$token}", [
            'password' => 'Rider@2026',
            'password_confirmation' => 'Rider@2026',
        ])->assertStatus(200);

        $user->refresh();

        // Logistics dashboard: 403
        $this->actingAs($user)->get('/sorting-center/dashboard')->assertStatus(403);

        // Seller dashboard: 403
        $this->actingAs($user)->get('/seller/dashboard')->assertStatus(403);

        // Admin dashboard: 403
        $this->actingAs($user)->get('/admin')->assertStatus(403);
    }
}
