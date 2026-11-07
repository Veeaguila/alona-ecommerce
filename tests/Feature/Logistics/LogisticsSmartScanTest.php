<?php

namespace Tests\Feature\Logistics;

use App\Models\{Area, Category, DeliveryProof, LogisticsHub, LogisticsHubStaff, LogisticsRoute, Order, OrderItem, Parcel, ParcelHubJourney, Product, Rider, SortingCenter, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LogisticsSmartScanTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private SortingCenter $sortingCenter;
    private LogisticsHub $originHub;
    private LogisticsHub $destinationHub;
    private User $pickupRiderUser;
    private Rider $pickupRider;
    private User $deliveryRiderUser;
    private Rider $deliveryRider;
    private User $hubStaffUser;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->adminUser = User::factory()->create(['status' => 'approved', 'usertype' => 'sorting_center']);
        $this->sortingCenter = SortingCenter::create([
            'user_id' => $this->adminUser->id,
            'business_name' => 'Alona Fast Logistics',
            'approval_status' => 'approved',
        ]);

        $this->originHub = LogisticsHub::create([
            'sorting_center_id' => $this->sortingCenter->id,
            'code' => 'MNL-01',
            'name' => 'Manila Operation Hub',
            'address' => '123 Rizal Ave, Manila',
            'city_code' => '1381300000',
            'pickup_city_codes' => ['1381300000'],
            'delivery_city_codes' => [],
            'is_active' => true,
        ]);

        $this->destinationHub = LogisticsHub::create([
            'sorting_center_id' => $this->sortingCenter->id,
            'code' => 'LAG-01',
            'name' => 'Laguna Operation Hub',
            'address' => '456 Crossing, Calamba, Laguna',
            'city_code' => '043405000',
            'pickup_city_codes' => [],
            'delivery_city_codes' => ['043405000'],
            'is_active' => true,
        ]);

        LogisticsRoute::create([
            'sorting_center_id' => $this->sortingCenter->id,
            'origin_hub_id' => $this->originHub->id,
            'destination_hub_id' => $this->destinationHub->id,
            'transport_mode' => 'road',
            'is_active' => true,
        ]);

        // Pickup Rider
        $this->pickupRiderUser = User::factory()->create(['status' => 'approved', 'usertype' => 'rider']);
        $this->pickupRider = Rider::create([
            'user_id' => $this->pickupRiderUser->id,
            'sorting_center_id' => $this->sortingCenter->id,
            'logistics_hub_id' => $this->originHub->id,
            'name' => 'Manila Pickup Rider',
            'contact' => '09171111111',
            'vehicle_type' => 'motorcycle',
            'plate_number' => 'MNL-101',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        // Delivery Rider
        $this->deliveryRiderUser = User::factory()->create(['status' => 'approved', 'usertype' => 'rider']);
        $this->deliveryRider = Rider::create([
            'user_id' => $this->deliveryRiderUser->id,
            'sorting_center_id' => $this->sortingCenter->id,
            'logistics_hub_id' => $this->destinationHub->id,
            'name' => 'Laguna Delivery Rider',
            'contact' => '09172222222',
            'vehicle_type' => 'motorcycle',
            'plate_number' => 'LAG-202',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        // Hub Staff User
        $this->hubStaffUser = User::factory()->create(['status' => 'approved', 'usertype' => 'hub_staff']);
        LogisticsHubStaff::create([
            'user_id' => $this->hubStaffUser->id,
            'sorting_center_id' => $this->sortingCenter->id,
            'logistics_hub_id' => $this->originHub->id,
            'role' => 'staff',
            'is_active' => true,
        ]);
    }

    private function createParcel(string $status = 'PLACED', bool $isCod = true): Parcel
    {
        $seller = User::factory()->create(['status' => 'approved', 'usertype' => 'seller']);
        $buyer = User::factory()->create(['status' => 'approved', 'usertype' => 'buyer']);

        $category = Category::firstOrCreate(['slug' => 'test-cat'], ['name' => 'Test Category', 'is_active' => true]);
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => 'Premium Coffee Beans',
            'slug' => 'coffee-beans-' . uniqid(),
            'price' => 500,
            'stock' => 10,
            'status' => 'approved',
        ]);

        $order = Order::create([
            'user_id' => $buyer->id,
            'order_number' => 'ALN-' . strtoupper(uniqid()),
            'status' => 'processing',
            'shipping_address' => '456 Crossing, Calamba, Laguna',
            'payment_method' => $isCod ? 'cod' : 'credit_card',
            'payment_status' => $isCod ? 'pending' : 'paid',
            'subtotal' => 500,
            'total' => 550,
        ]);

        $itemStatus = match ($status) {
            'PLACED', 'PREPARING' => 'ready_for_pickup',
            'PICKED_UP', 'AT_SORTING_CENTER', 'IN_HUB_TRANSIT' => 'shipped',
            'OUT_FOR_DELIVERY' => 'out_for_delivery',
            'DELIVERED' => 'delivered',
            default => 'ready_for_pickup',
        };

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'price' => 500,
            'status' => $itemStatus,
            'tracking_number' => 'TRK-' . strtoupper(uniqid()),
            'courier_name' => $this->sortingCenter->business_name,
        ]);

        $parcel = Parcel::create([
            'order_id' => 'ITEM-' . uniqid(),
            'order_item_id' => $item->id,
            'sorting_center_id' => $this->sortingCenter->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => $status,
            'collection_address' => '123 Rizal Ave, Manila',
            'delivery_address' => '456 Crossing, Calamba, Laguna',
            'delivery_province' => 'Laguna',
            'delivery_municipality' => 'Calamba',
            'delivery_barangay' => 'Crossing',
            'delivery_street' => 'Crossing Street',
            'delivery_house_number' => '10',
        ]);

        ParcelHubJourney::create([
            'parcel_id' => $parcel->id,
            'sorting_center_id' => $this->sortingCenter->id,
            'origin_hub_id' => $this->originHub->id,
            'destination_hub_id' => $this->destinationHub->id,
            'current_hub_id' => $this->originHub->id,
            'route_snapshot' => [
                'company_name' => $this->sortingCenter->business_name,
                'origin_hub_id' => $this->originHub->id,
                'origin_hub_name' => $this->originHub->name,
                'destination_hub_id' => $this->destinationHub->id,
                'destination_hub_name' => $this->destinationHub->name,
                'origin_city_code' => '1381300000',
                'destination_city_code' => '043405000',
                'transport_mode' => 'road',
            ],
        ]);

        return $parcel;
    }

    public function test_rider_can_scan_to_pick_up_parcel(): void
    {
        $parcel = $this->createParcel('PLACED');
        Sanctum::actingAs($this->pickupRiderUser);

        $response = $this->postJson('/api/logistics/smart-scan', [
            'reference' => $parcel->order_id,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('stage', 'picked_up')
            ->assertJsonPath('action_taken', 'PICKED_UP');

        $this->assertDatabaseHas('parcels', [
            'id' => $parcel->id,
            'status' => 'PICKED_UP',
            'pickup_rider_id' => $this->pickupRider->id,
        ]);
    }

    public function test_hub_staff_can_scan_to_receive_picked_up_parcel(): void
    {
        $parcel = $this->createParcel('PICKED_UP');
        $parcel->update(['pickup_rider_id' => $this->pickupRider->id]);

        Sanctum::actingAs($this->hubStaffUser);

        $response = $this->postJson('/api/logistics/smart-scan', [
            'reference' => $parcel->order_id,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('stage', 'received_at_hub')
            ->assertJsonPath('action_taken', 'AT_SORTING_CENTER');

        $this->assertDatabaseHas('parcels', [
            'id' => $parcel->id,
            'status' => 'AT_SORTING_CENTER',
        ]);
    }

    public function test_hub_staff_scan_dispatches_inter_hub_transfer_with_transport_ref(): void
    {
        $parcel = $this->createParcel('AT_SORTING_CENTER');
        $parcel->hubJourney->update(['origin_received_at' => now(), 'current_hub_id' => $this->originHub->id]);

        Sanctum::actingAs($this->hubStaffUser);

        // First scan without transport ref prompts for one
        $promptResponse = $this->postJson('/api/logistics/smart-scan', [
            'reference' => $parcel->order_id,
        ]);

        $promptResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('stage', 'ready_for_transfer')
            ->assertJsonPath('action_needed', 'PROVIDE_TRANSPORT_REFERENCE');

        // Second scan with transport ref dispatches it
        $dispatchResponse = $this->postJson('/api/logistics/smart-scan', [
            'reference' => $parcel->order_id,
            'transport_reference' => 'LINEHAUL-TRUCK-01',
        ]);

        $dispatchResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('stage', 'dispatched_transfer')
            ->assertJsonPath('action_taken', 'IN_HUB_TRANSIT');

        $this->assertDatabaseHas('parcels', [
            'id' => $parcel->id,
            'status' => 'IN_HUB_TRANSIT',
        ]);
    }

    public function test_destination_hub_staff_scan_receives_inter_hub_transfer(): void
    {
        $parcel = $this->createParcel('AT_SORTING_CENTER');
        $parcel->hubJourney->update(['origin_received_at' => now(), 'current_hub_id' => $this->originHub->id]);

        // Dispatch parcel first
        Sanctum::actingAs($this->hubStaffUser);
        $this->postJson('/api/logistics/smart-scan', [
            'reference' => $parcel->order_id,
            'transport_reference' => 'LINEHAUL-TRUCK-01',
        ])->assertOk();

        // Destination hub staff (Laguna Hub)
        $lagunaStaffUser = User::factory()->create(['status' => 'approved', 'usertype' => 'hub_staff']);
        LogisticsHubStaff::create([
            'user_id' => $lagunaStaffUser->id,
            'sorting_center_id' => $this->sortingCenter->id,
            'logistics_hub_id' => $this->destinationHub->id,
            'role' => 'staff',
            'is_active' => true,
        ]);

        Sanctum::actingAs($lagunaStaffUser);

        $receiveResponse = $this->postJson('/api/logistics/smart-scan', [
            'reference' => $parcel->order_id,
        ]);

        $receiveResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('stage', 'transfer_received')
            ->assertJsonPath('action_taken', 'AT_SORTING_CENTER');

        $this->assertDatabaseHas('parcels', [
            'id' => $parcel->id,
            'status' => 'AT_SORTING_CENTER',
        ]);
        $this->assertNotNull($parcel->fresh()->hubJourney->destination_received_at);
    }

    public function test_delivery_rider_can_scan_to_depart_hub_and_dropoff_with_photo(): void
    {
        $parcel = $this->createParcel('AT_SORTING_CENTER');
        $parcel->hubJourney->update([
            'origin_received_at' => now(),
            'destination_received_at' => now(),
            'current_hub_id' => $this->destinationHub->id,
        ]);

        Sanctum::actingAs($this->deliveryRiderUser);

        // 1. Depart Hub -> OUT_FOR_DELIVERY
        $startResponse = $this->postJson('/api/logistics/smart-scan', [
            'reference' => $parcel->order_id,
        ]);

        $startResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('stage', 'out_for_delivery')
            ->assertJsonPath('action_taken', 'OUT_FOR_DELIVERY');

        $this->assertDatabaseHas('parcels', [
            'id' => $parcel->id,
            'status' => 'OUT_FOR_DELIVERY',
            'assigned_rider_id' => $this->deliveryRider->id,
        ]);

        // 2. Scan at buyer door without photo -> prompts for photo proof & COD amount
        $doorResponse = $this->postJson('/api/logistics/smart-scan', [
            'reference' => $parcel->order_id,
        ]);

        $doorResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('stage', 'ready_for_dropoff')
            ->assertJsonPath('action_needed', 'CAPTURE_PHOTO_PROOF')
            ->assertJsonPath('is_cod', true)
            ->assertJsonPath('cod_amount', 550);

        // 3. Scan with photo proof -> completes delivery & records COD
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aQ1sAAAAASUVORK5CYII=');
        $photo = UploadedFile::fake()->createWithContent('proof.png', $png);

        $completeResponse = $this->postJson('/api/logistics/smart-scan', [
            'reference' => $parcel->order_id,
            'photo' => $photo,
        ]);

        $completeResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('stage', 'delivered')
            ->assertJsonPath('action_taken', 'DELIVERED');

        $this->assertDatabaseHas('parcels', [
            'id' => $parcel->id,
            'status' => 'DELIVERED',
        ]);
        $this->assertDatabaseHas('delivery_proofs', [
            'parcel_id' => $parcel->id,
            'rider_id' => $this->deliveryRider->id,
            'type' => 'PHOTO',
        ]);
        $this->assertDatabaseHas('cod_collections', [
            'parcel_id' => $parcel->id,
            'rider_id' => $this->deliveryRider->id,
            'amount' => '550.00',
        ]);

        // 4. Repeated scan returns already delivered
        $repeatResponse = $this->postJson('/api/logistics/smart-scan', [
            'reference' => $parcel->order_id,
        ]);

        $repeatResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('stage', 'already_delivered');
    }

    public function test_unauthorized_user_cannot_scan(): void
    {
        $parcel = $this->createParcel('PLACED');
        $randomBuyer = User::factory()->create(['status' => 'approved', 'usertype' => 'buyer']);
        Sanctum::actingAs($randomBuyer);

        $response = $this->postJson('/api/logistics/smart-scan', [
            'reference' => $parcel->order_id,
        ]);

        $response->assertForbidden();
    }

    public function test_scan_returns_404_when_parcel_not_found(): void
    {
        Sanctum::actingAs($this->pickupRiderUser);

        $response = $this->postJson('/api/logistics/smart-scan', [
            'reference' => 'NON-EXISTENT-REF-99999',
        ]);

        $response->assertNotFound();
    }
}
