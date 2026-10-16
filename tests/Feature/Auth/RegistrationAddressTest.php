<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\PhilippineAddressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationAddressTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $role): array
    {
        $data = app(PhilippineAddressService::class)->data();
        $city = collect($data['cities'])->firstWhere('province_code', '0403400000');
        $barangay = collect($data['barangays'])->firstWhere('city_code', $city['code']);

        return [
            'first_name' => 'Address', 'last_name' => 'Test', 'sex' => 'Other',
            'email' => $role.'@address.test', 'contact_no' => $role === 'buyer' ? '09123456789' : '09123456788',
            'birthday' => '2000-01-01', 'age' => 26, 'password' => 'password', 'password_confirmation' => 'password',
            'address_group' => 'south-luzon', 'region_code' => $city['region_code'],
            'province_code' => $city['province_code'], 'city_code' => $city['code'], 'barangay_code' => $barangay['code'],
            'province' => 'Forged province', 'municipality' => 'Forged city', 'barangay' => 'Forged barangay', 'street_address' => '10 Test Street',
            'store_name' => 'Address Store', 'line_of_business' => 'Retail',
            'id' => UploadedFile::fake()->create('id.pdf', 5, 'application/pdf'),
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 5, 'application/pdf'),
            'terms' => true,
        ];
    }

    public function test_buyer_and_seller_save_canonical_provincial_address_and_codes(): void
    {
        Storage::fake('local');
        foreach (['buyer', 'seller'] as $role) {
            $input = $this->payload($role);
            $expected = app(PhilippineAddressService::class)->validate($input);
            $this->post($role === 'buyer' ? '/register' : '/register/seller', $input)
                ->assertSessionHasNoErrors()
                ->assertRedirect($role === 'buyer' ? route('verification.otp') : route('login'));
            $user = User::where('email', $input['email'])->firstOrFail();
            $this->assertSame($expected['province'], $user->province);
            $this->assertSame($expected['city'], $user->municipality);
            $this->assertSame($expected['barangay'], $user->barangay);
            $this->assertSame($input['city_code'], $user->address_geography['city_code']);
            $this->assertSame('south-luzon', $user->address_geography['address_group']);
            $this->assertSame($role === 'buyer' ? 'unverified' : 'pending', $user->status);
        }
    }

    public function test_both_forms_reject_cross_group_and_cross_city_barangays(): void
    {
        Storage::fake('local');
        foreach (['buyer', 'seller'] as $role) {
            $path = $role === 'buyer' ? '/register' : '/register/seller';
            $input = $this->payload($role);
            $this->post($path, array_replace($input, ['address_group' => 'mindanao']))->assertSessionHasErrors('address_group');
            $this->post($path, array_replace($input, ['barangay_code' => '1001322005']))->assertSessionHasErrors('barangay_code');
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
