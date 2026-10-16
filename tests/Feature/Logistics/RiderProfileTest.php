<?php

namespace Tests\Feature\Logistics;

use App\Models\{PartnerApplication, Rider, User};
use App\Services\PhilippineAddressService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiderProfileTest extends TestCase
{
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        Storage::fake('local');
        $this->seq = 0;
    }

    private function fixture(): User
    {
        $this->seq++;
        $contact = $this->seq === 1 ? '09170000001' : sprintf('0917%07d', $this->seq);
        $plate = $this->seq === 1 ? 'TEST-1' : 'TEST-'.$this->seq;
        $user = User::factory()->create(['usertype' => 'rider', 'status' => 'approved', 'name' => 'Actual Rider',
            'contact_no' => $contact, 'birthday' => '2000-01-02', 'address' => 'Existing address']);
        Rider::create(['user_id' => $user->id, 'name' => $user->name, 'contact' => $user->contact_no,
            'birthday' => '2000-01-02', 'vehicle_type' => 'motorcycle', 'plate_number' => $plate,
            'approval_status' => 'approved', 'is_active' => true]);
        PartnerApplication::create(['user_id' => $user->id, 'role' => 'rider', 'details' => ['contact_no' => $user->contact_no, 'vehicle_type' => 'motorcycle'],
            'address' => [], 'documents' => ['id_doc' => 'untouched.pdf'], 'terms_accepted_at' => now(), 'privacy_accepted_at' => now()]);
        Sanctum::actingAs($user);
        return $user;
    }

    private function address(): array
    {
        $data = app(PhilippineAddressService::class)->data();
        $city = collect($data['cities'])->firstWhere('region_code', '1300000000');
        $barangay = collect($data['barangays'])->firstWhere('city_code', $city['code']);
        return ['region_code' => $city['region_code'], 'province_code' => $city['province_code'] ?? '',
            'city_code' => $city['code'], 'barangay_code' => $barangay['code'], 'street' => 'New Street',
            'house_number' => '21', 'subdivision' => '', 'postal_code' => '1000'];
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('avatar.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6WQAAAABJRU5ErkJggg=='));
    }

    public function test_profile_displays_actual_fields_and_contact_does_not_change_approval(): void
    {
        $user = $this->fixture();
        $this->getJson('/api/partner/profile')->assertOk()->assertJsonPath('data.name', 'Actual Rider')
            ->assertJsonPath('data.rider_profile.birthday', '2000-01-02')->assertJsonPath('data.rider_profile.address', 'Existing address')
            ->assertJsonPath('data.rider_profile.plate_number', 'TEST-1')->assertJsonPath('data.rider_profile.photo_available', false);
        $this->patchJson('/api/rider/profile', ['contact_no' => '09179999999'])->assertOk()->assertJsonPath('requires_review', false);
        $this->assertSame('approved', $user->fresh()->status);
        $this->assertSame('09179999999', $user->rider->fresh()->contact);
        $this->assertSame('09179999999', $user->partnerApplication->fresh()->details['contact_no']);
        $this->patchJson('/api/rider/profile', ['contact_no' => 'bad'])->assertUnprocessable();
        $this->patchJson('/api/rider/profile', ['status' => 'approved', 'vehicle_type' => 'bicycle'])->assertUnprocessable();
        $this->assertSame('motorcycle', $user->rider->fresh()->vehicle_type);
    }

    public function test_canonical_address_changes_require_reapproval_and_preserve_reviewed_vehicle_documents(): void
    {
        $user = $this->fixture();
        $input = $this->address();
        $this->patchJson('/api/rider/profile', ['address' => array_merge($input, ['barangay_code' => 'invalid'])])->assertUnprocessable();
        $this->assertSame('Existing address', $user->fresh()->address);
        $this->patchJson('/api/rider/profile', ['address' => $input])->assertOk()->assertJsonPath('requires_review', true);
        $this->assertSame('pending', $user->fresh()->status);
        $this->assertSame('pending', $user->rider->fresh()->approval_status);
        $this->assertSame('New Street', $user->rider->fresh()->street);
        $this->assertSame('TEST-1', $user->rider->fresh()->plate_number);
        $this->assertSame(['id_doc' => 'untouched.pdf'], $user->partnerApplication->fresh()->documents);
        Sanctum::actingAs($user->fresh());
        $this->getJson('/api/rider/deliveries')->assertForbidden();
        $this->patchJson('/api/rider/profile', ['address' => $input])->assertOk()->assertJsonPath('requires_review', false);
        $this->patchJson('/api/rider/profile', ['address' => array_reverse($input, true)])->assertOk()->assertJsonPath('requires_review', false);
    }

    public function test_photo_is_private_replaced_after_commit_and_invalid_files_rejected(): void
    {
        $user = $this->fixture();
        $this->postJson('/api/rider/profile-photo', ['photo' => $this->image()])->assertOk();
        $first = $user->fresh()->avatar;
        Storage::disk('local')->assertExists($first);
        Sanctum::actingAs($user->fresh());
        $this->getJson('/api/rider/profile-photo')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->getJson('/api/partner/profile')->assertJsonPath('data.rider_profile.photo_available', true);
        $this->postJson('/api/rider/profile-photo', ['photo' => $this->image()])->assertOk();
        Storage::disk('local')->assertMissing($first);
        $this->postJson('/api/rider/profile-photo', ['photo' => UploadedFile::fake()->createWithContent('bad.svg', '<svg/>')])->assertUnprocessable();
        $other = $this->fixture();
        $this->getJson('/api/rider/profile-photo')->assertNotFound();
        $this->assertNotSame($user->id, $other->id);
        Sanctum::actingAs(User::factory()->create(['usertype' => 'buyer']));
        $this->postJson('/api/rider/profile-photo', ['photo' => $this->image()])->assertForbidden();
        $this->patchJson('/api/rider/profile', ['contact_no' => '09179999999'])->assertForbidden();
    }

    public function test_failed_photo_database_write_cleans_new_file_without_deleting_old_photo(): void
    {
        $user = $this->fixture();
        $old = 'rider-avatars/'.$user->id.'/old.png';
        Storage::disk('local')->put($old, 'old');
        $user->update(['avatar' => $old]);
        User::updating(function (User $row): void { if ($row->isDirty('avatar')) throw new \RuntimeException('Test rollback'); });
        $this->postJson('/api/rider/profile-photo', ['photo' => $this->image()])->assertServerError();
        $this->assertSame($old, $user->fresh()->avatar);
        $this->assertSame([$old], Storage::disk('local')->allFiles('rider-avatars/'.$user->id));
    }
}
