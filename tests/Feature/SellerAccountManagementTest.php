<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SellerAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createSeller(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Maria Santos',
            'first_name' => 'Maria',
            'middle_initial' => 'L',
            'last_name' => 'Santos',
            'sex' => 'Female',
            'contact_no' => '09171234567',
            'province' => 'Laguna',
            'municipality' => 'Calamba City',
            'barangay' => 'Real',
            'street_address' => '123 Rizal Ave',
            'store_name' => 'Santos Artisan Crafts',
            'line_of_business' => 'Handicrafts & Home Decor',
            'usertype' => 'seller',
            'status' => 'approved',
            'password' => Hash::make('Password123!'),
        ], $overrides));
    }

    public function test_seller_can_view_account_management_page(): void
    {
        $seller = $this->createSeller();

        $response = $this->actingAs($seller)->get(route('seller.account'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Seller/Account')
                ->where('account.email', $seller->email)
                ->where('account.store_name', 'Santos Artisan Crafts')
                ->where('verification.status', 'approved')
                ->where('verification.line_of_business', 'Handicrafts & Home Decor')
                ->has('sessions')
            );
    }

    public function test_seller_can_update_personal_profile_and_address(): void
    {
        Storage::fake('public');
        $seller = $this->createSeller();

        $avatar = UploadedFile::fake()->image('avatar.jpg', 300, 300);

        $response = $this->actingAs($seller)->post(route('seller.account.profile.update'), [
            'first_name' => 'Maria Clara',
            'middle_initial' => 'D',
            'last_name' => 'Santos',
            'sex' => 'Female',
            'birthday' => '1992-05-15',
            'email' => $seller->email,
            'contact_no' => '09189998877',
            'province' => 'Cavite',
            'municipality' => 'Tagaytay City',
            'barangay' => 'Kaybagal South',
            'street_address' => '88 Aguinaldo Hwy',
            'profile_photo' => $avatar,
        ]);

        $response->assertRedirect(route('seller.account'));

        $seller->refresh();
        $this->assertSame('Maria Clara D. Santos', $seller->name);
        $this->assertSame('09189998877', $seller->contact_no);
        $this->assertSame('Cavite', $seller->province);
        $this->assertSame('Tagaytay City', $seller->municipality);
        $this->assertNotNull($seller->avatar);
        Storage::disk('public')->assertExists($seller->avatar);
    }

    public function test_seller_can_upload_business_verification_documents_and_sync_with_admin(): void
    {
        Storage::fake('local');
        $seller = $this->createSeller();
        $admin = User::factory()->create([
            'usertype' => 'admin',
            'status' => 'approved',
        ]);

        $permitFile = UploadedFile::fake()->create('mayors-permit-2026.pdf', 512, 'application/pdf');
        $govIdFile = UploadedFile::fake()->image('valid-id.png', 600, 400);

        $response = $this->actingAs($seller)->post(route('seller.account.documents.update'), [
            'line_of_business' => 'Specialty Coffee & Equipment',
            'business_permit' => $permitFile,
            'government_id' => $govIdFile,
        ]);

        $response->assertRedirect(route('seller.account'));

        $seller->refresh();
        $this->assertSame('Specialty Coffee & Equipment', $seller->line_of_business);
        $this->assertNotNull($seller->business_permit_path);
        $this->assertNotNull($seller->id_path);
        Storage::disk('local')->assertExists($seller->business_permit_path);
        Storage::disk('local')->assertExists($seller->id_path);

        // PartnerApplication synced for Admin review
        $this->assertDatabaseHas('partner_applications', [
            'user_id' => $seller->id,
            'role' => 'seller',
        ]);

        // Automated seller notification logged
        $this->assertDatabaseHas('seller_notifications', [
            'user_id' => $seller->id,
            'type' => 'verification',
            'title' => 'Business Verification Documents Updated',
        ]);

        // Both Seller and Admin can view the uploaded Business Permit & Government ID
        $this->actingAs($seller)
            ->get(route('partner.documents', [$seller, 'business_permit']))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('partner.documents', [$seller, 'business_permit']))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('partner.documents', [$seller, 'government_id']))
            ->assertOk();
    }

    public function test_seller_can_monitor_and_revoke_login_sessions(): void
    {
        $seller = $this->createSeller();

        // Insert two additional active device sessions for this seller
        DB::table('sessions')->insert([
            [
                'id' => 'other-device-session-1',
                'user_id' => $seller->id,
                'ip_address' => '203.0.113.42',
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
                'payload' => base64_encode('test'),
                'last_activity' => now()->subMinutes(10)->timestamp,
            ],
            [
                'id' => 'other-device-session-2',
                'user_id' => $seller->id,
                'ip_address' => '198.51.100.18',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/122.0.0.0',
                'payload' => base64_encode('test2'),
                'last_activity' => now()->subMinutes(25)->timestamp,
            ],
        ]);

        // Revoke single session
        $this->actingAs($seller)
            ->delete(route('seller.account.sessions.revoke', 'other-device-session-1'))
            ->assertRedirect(route('seller.account'));

        $this->assertDatabaseMissing('sessions', [
            'id' => 'other-device-session-1',
        ]);

        // Revoke all other sessions with password confirmation
        $this->actingAs($seller)
            ->post(route('seller.account.sessions.revoke-other'), [
                'password' => 'Password123!',
            ])
            ->assertRedirect(route('seller.account'));

        $this->assertDatabaseMissing('sessions', [
            'id' => 'other-device-session-2',
        ]);
    }

    public function test_seller_can_update_account_password(): void
    {
        $seller = $this->createSeller();

        $this->actingAs($seller)
            ->put(route('seller.account.password.update'), [
                'current_password' => 'Password123!',
                'password' => 'NewSecurePass456!',
                'password_confirmation' => 'NewSecurePass456!',
            ])
            ->assertRedirect(route('seller.account'));

        $seller->refresh();
        $this->assertTrue(Hash::check('NewSecurePass456!', $seller->password));
    }
}
