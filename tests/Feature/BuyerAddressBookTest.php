<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerAddressBookTest extends TestCase
{
    use RefreshDatabase;

    private function createBuyer(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'usertype' => 'buyer',
            'status' => 'approved',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'name' => 'Juan Dela Cruz',
            'email' => 'juan.delacruz@example.com',
            'contact_no' => '09171234567',
            'email_verified_at' => now(),
        ], $attributes));
    }

    private function validAddressPayload(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Home',
            'recipient_name' => 'Juan Dela Cruz',
            'recipient_phone' => '09171234567',
            'province' => 'Bohol',
            'municipality' => 'Tagbilaran City',
            'barangay' => 'Poblacion II',
            'street_address' => '456 Carlos P. Garcia Avenue',
            'postal_code' => '6300',
            'is_default' => false,
        ], $overrides);
    }

    /**
     * BUY-ADDR-001: Add an address with valid granular data.
     */
    public function test_buy_addr_001_add_address_with_valid_granular_data(): void
    {
        $buyer = $this->createBuyer();

        $response = $this->actingAs($buyer)->post(route('buyer.addresses.store'), $this->validAddressPayload([
            'label' => 'Office',
            'recipient_name' => 'Juan Dela Cruz',
            'recipient_phone' => '09189876543',
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('addresses', [
            'user_id' => $buyer->id,
            'label' => 'Office',
            'recipient_name' => 'Juan Dela Cruz',
            'recipient_phone' => '09189876543',
            'province' => 'Bohol',
            'municipality' => 'Tagbilaran City',
            'barangay' => 'Poblacion II',
            'street_address' => '456 Carlos P. Garcia Avenue',
            'postal_code' => '6300',
            'is_default' => true, // First address automatically becomes default
        ]);
    }

    /**
     * BUY-ADDR-002: Region, Province, City, Barangay data structure.
     */
    public function test_buy_addr_002_region_province_city_barangay_structure(): void
    {
        $buyer = $this->createBuyer();

        $payload = $this->validAddressPayload([
            'region' => 'Region VII',
            'province' => 'Cebu',
            'municipality' => 'Cebu City',
            'barangay' => 'Lahug',
            'street_address' => 'Salinas Drive, IT Park',
        ]);

        $response = $this->actingAs($buyer)->post(route('buyer.addresses.store'), $payload);
        $response->assertSessionHasNoErrors();

        $address = $buyer->addresses()->first();
        $this->assertEquals('Cebu', $address->province);
        $this->assertEquals('Cebu City', $address->municipality);
        $this->assertEquals('Lahug', $address->barangay);
        $this->assertStringContainsString('Lahug', $address->address);
        $this->assertStringContainsString('Cebu City', $address->address);
    }

    /**
     * BUY-ADDR-003: Individual required address field validation.
     */
    public function test_buy_addr_003_individual_required_fields_validation(): void
    {
        $buyer = $this->createBuyer();

        $fields = [
            'recipient_name',
            'recipient_phone',
            'street_address',
            'barangay',
            'municipality',
            'province',
            'label',
        ];

        foreach ($fields as $field) {
            $payload = $this->validAddressPayload([$field => '']);
            $response = $this->actingAs($buyer)->post(route('buyer.addresses.store'), $payload);
            $response->assertSessionHasErrors($field);
        }
    }

    /**
     * BUY-ADDR-004: Recipient phone number validation.
     */
    public function test_buy_addr_004_recipient_phone_number_validation(): void
    {
        $buyer = $this->createBuyer();

        // Invalid phone strings rejected
        $invalidNumbers = ['12345', 'abcdef', '08123456789', '0912345', '+1234567890'];

        foreach ($invalidNumbers as $badPhone) {
            $response = $this->actingAs($buyer)->post(route('buyer.addresses.store'), $this->validAddressPayload([
                'recipient_phone' => $badPhone,
            ]));
            $response->assertSessionHasErrors('recipient_phone');
        }

        // Valid formats accepted (09xxxxxxxxx or +639xxxxxxxxx)
        $validResp = $this->actingAs($buyer)->post(route('buyer.addresses.store'), $this->validAddressPayload([
            'recipient_phone' => '+639171234567',
        ]));
        $validResp->assertSessionHasNoErrors();
    }

    /**
     * BUY-ADDR-005: Address book 10-address limit.
     */
    public function test_buy_addr_005_address_book_10_address_limit(): void
    {
        $buyer = $this->createBuyer();

        // Add 10 addresses
        for ($i = 1; $i <= 10; $i++) {
            Address::create([
                'user_id' => $buyer->id,
                'label' => "Address {$i}",
                'recipient_name' => "Recipient {$i}",
                'recipient_phone' => '09171234567',
                'street_address' => "Street {$i}",
                'barangay' => 'Barangay 1',
                'municipality' => 'City',
                'province' => 'Province',
                'address' => "Street {$i}, City, Province",
                'is_default' => $i === 1,
            ]);
        }

        $this->assertEquals(10, $buyer->addresses()->count());

        // Attempting to add 11th address is blocked
        $response = $this->actingAs($buyer)->post(route('buyer.addresses.store'), $this->validAddressPayload([
            'label' => 'Address 11',
        ]));

        $response->assertSessionHasErrors('address');
        $this->assertEquals(10, $buyer->addresses()->count());
    }

    /**
     * BUY-ADDR-006: Set different default address.
     */
    public function test_buy_addr_006_set_different_default_address(): void
    {
        $buyer = $this->createBuyer();

        $addr1 = Address::create([
            'user_id' => $buyer->id,
            'label' => 'Home 1',
            'recipient_name' => 'Juan',
            'recipient_phone' => '09171234567',
            'address' => 'Address 1',
            'is_default' => true,
        ]);

        $addr2 = Address::create([
            'user_id' => $buyer->id,
            'label' => 'Home 2',
            'recipient_name' => 'Juan',
            'recipient_phone' => '09171234567',
            'address' => 'Address 2',
            'is_default' => false,
        ]);

        // Switch default to addr2
        $response = $this->actingAs($buyer)->patch(route('buyer.addresses.default', $addr2->id));
        $response->assertSessionHasNoErrors();

        $addr1->refresh();
        $addr2->refresh();

        $this->assertFalse($addr1->is_default);
        $this->assertTrue($addr2->is_default);
    }

    /**
     * BUY-ADDR-007: Delete the default address is blocked if other addresses exist.
     */
    public function test_buy_addr_007_delete_the_default_address_blocked(): void
    {
        $buyer = $this->createBuyer();

        $defaultAddr = Address::create([
            'user_id' => $buyer->id,
            'label' => 'Default Home',
            'recipient_name' => 'Juan',
            'recipient_phone' => '09171234567',
            'address' => 'Default Address',
            'is_default' => true,
        ]);

        $otherAddr = Address::create([
            'user_id' => $buyer->id,
            'label' => 'Other Office',
            'recipient_name' => 'Juan',
            'recipient_phone' => '09171234567',
            'address' => 'Other Address',
            'is_default' => false,
        ]);

        // Attempting to delete default address directly when multiple addresses exist
        $response = $this->actingAs($buyer)->delete(route('buyer.addresses.destroy', $defaultAddr->id));

        $response->assertSessionHasErrors('address');
        $this->assertDatabaseHas('addresses', ['id' => $defaultAddr->id]);

        // Set other as default first, then delete original
        $this->actingAs($buyer)->patch(route('buyer.addresses.default', $otherAddr->id));
        $delResponse = $this->actingAs($buyer)->delete(route('buyer.addresses.destroy', $defaultAddr->id));
        $delResponse->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('addresses', ['id' => $defaultAddr->id]);
    }

    /**
     * BUY-ADDR-008: Edit/delete address used by active order preserves snapshot.
     */
    public function test_buy_addr_008_edit_delete_address_used_by_active_order(): void
    {
        $buyer = $this->createBuyer();

        $addr = Address::create([
            'user_id' => $buyer->id,
            'label' => 'Home',
            'recipient_name' => 'Juan',
            'recipient_phone' => '09171234567',
            'address' => '123 Historical Snapshot Street, Tagbilaran',
            'is_default' => true,
        ]);

        $order = Order::create([
            'user_id' => $buyer->id,
            'order_number' => 'ORD-TEST-001',
            'status' => 'to_ship',
            'payment_status' => 'paid',
            'payment_method' => 'cod',
            'total' => 500,
            'shipping_address' => $addr->address,
        ]);

        // Update the address in the address book
        $this->actingAs($buyer)->patch(route('buyer.addresses.update', $addr->id), $this->validAddressPayload([
            'street_address' => '999 Brand New Street',
        ]));

        $order->refresh();
        $this->assertEquals('123 Historical Snapshot Street, Tagbilaran', $order->shipping_address);
    }

    /**
     * BUY-ADDR-009: Cross-buyer address security.
     */
    public function test_buy_addr_009_cross_buyer_address_security(): void
    {
        $buyer1 = $this->createBuyer(['email' => 'buyer1@example.com']);
        $buyer2 = $this->createBuyer(['email' => 'buyer2@example.com', 'contact_no' => '09182223344']);

        $addrOfBuyer2 = Address::create([
            'user_id' => $buyer2->id,
            'label' => 'Buyer 2 Home',
            'recipient_name' => 'Buyer 2',
            'recipient_phone' => '09182223344',
            'address' => 'Secret Address',
            'is_default' => true,
        ]);

        // Buyer 1 tries to update Buyer 2's address -> 404
        $respUpdate = $this->actingAs($buyer1)->patch(route('buyer.addresses.update', $addrOfBuyer2->id), $this->validAddressPayload());
        $respUpdate->assertNotFound();

        // Buyer 1 tries to delete Buyer 2's address -> 404
        $respDelete = $this->actingAs($buyer1)->delete(route('buyer.addresses.destroy', $addrOfBuyer2->id));
        $respDelete->assertNotFound();
    }
}
