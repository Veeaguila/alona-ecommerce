<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertRedirect('/?auth=register-buyer&onboarding=1');
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'last_name' => 'User',
            'first_name' => 'Test',
            'middle_initial' => 'T',
            'sex' => 'Other',
            'email' => 'test@example.com',
            'contact_no' => '09123456789',
            'birthday' => '2000-01-01',
            'age' => 26,
            'address_group' => 'metro-manila',
            'region_code' => '1300000000',
            'province_code' => '',
            'city_code' => '1381300000',
            'barangay_code' => '1381300001',
            'province' => 'Cebu',
            'municipality' => 'Cebu City',
            'barangay' => 'Barangay Central',
            'street_address' => '1 Test Street',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'municipality' => 'Quezon City', 'barangay' => 'Alicia']);
        $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'province' => null]);
        $this->assertGuest();
        $response->assertRedirect(route('login', absolute: false));
    }

    public function test_seller_registration_can_be_created_with_pending_review(): void
    {
        Storage::fake('local');
        $response = $this->post('/register/seller', [
            'last_name' => 'Seller',
            'first_name' => 'Test',
            'middle_initial' => 'S',
            'sex' => 'Female',
            'email' => 'seller@example.com',
            'contact_no' => '09123456789',
            'birthday' => '1995-02-14',
            'age' => 31,
            'address_group' => 'metro-manila',
            'region_code' => '1300000000',
            'province_code' => '',
            'city_code' => '1381300000',
            'barangay_code' => '1381300001',
            'province' => 'Metro Manila',
            'municipality' => 'Quezon City',
            'barangay' => 'Barangay 1',
            'street_address' => '10 Seller Street',
            'store_name' => 'Seller Test Store',
            'store_description' => 'Handmade items for everyday life.',
            'bank_name' => 'BDO',
            'bank_account_name' => 'Test Seller',
            'bank_account_number' => '1234567890',
            'line_of_business' => 'Handmade goods',
            'id' => UploadedFile::fake()->create('id.pdf', 5, 'application/pdf'),
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 5, 'application/pdf'),
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'seller@example.com',
            'usertype' => 'seller',
            'status' => 'pending',
            'store_name' => 'Seller Test Store',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login', absolute: false));
    }
}
