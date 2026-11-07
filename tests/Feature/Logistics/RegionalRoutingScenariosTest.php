<?php

namespace Tests\Feature\Logistics;

use App\Models\{Category, LogisticsHub, LogisticsHubStaff, LogisticsRoute, Order, OrderItem, Parcel, ParcelHubJourney, Product, Rider, SortingCenter, User};
use App\Services\LogisticsSmartScan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegionalRoutingScenariosTest extends TestCase
{
    use RefreshDatabase;

    private User $companyAdmin;
    private SortingCenter $company;
    private LogisticsHub $lagunaHub;
    private LogisticsHub $manilaHub;
    private LogisticsRoute $routeLagunaToManila;
    private LogisticsRoute $routeManilaToLaguna;
    private User $lagunaStaff;
    private User $manilaStaff;
    private User $lagunaRider;
    private User $manilaRider;
    private Rider $lagunaRiderModel;
    private Rider $manilaRiderModel;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->companyAdmin = User::factory()->create(['status' => 'approved', 'usertype' => 'sorting_center']);
        $this->company = SortingCenter::create([
            'user_id' => $this->companyAdmin->id,
            'business_name' => 'Alona Express',
            'approval_status' => 'approved',
        ]);

        // Hub 1: Laguna
        $this->lagunaHub = LogisticsHub::create([
            'sorting_center_id' => $this->company->id,
            'code' => 'LAG-01',
            'name' => 'Laguna Operation Hub',
            'address' => 'Calamba, Laguna',
            'city_code' => '0403405000',
            'pickup_city_codes' => ['0403405000'],
            'delivery_city_codes' => ['0403405000'],
            'is_active' => true,
        ]);

        // Hub 2: Manila
        $this->manilaHub = LogisticsHub::create([
            'sorting_center_id' => $this->company->id,
            'code' => 'MNL-01',
            'name' => 'Manila Operation Hub',
            'address' => 'Manila City',
            'city_code' => '1381600000',
            'pickup_city_codes' => ['1381600000'],
            'delivery_city_codes' => ['1381600000'],
            'is_active' => true,
        ]);

        // Routes
        $this->routeLagunaToManila = LogisticsRoute::create([
            'sorting_center_id' => $this->company->id,
            'origin_hub_id' => $this->lagunaHub->id,
            'destination_hub_id' => $this->manilaHub->id,
            'transport_mode' => 'road',
            'is_active' => true,
        ]);

        $this->routeManilaToLaguna = LogisticsRoute::create([
            'sorting_center_id' => $this->company->id,
            'origin_hub_id' => $this->manilaHub->id,
            'destination_hub_id' => $this->lagunaHub->id,
            'transport_mode' => 'road',
            'is_active' => true,
        ]);

        // Hub Staff
        $this->lagunaStaff = User::factory()->create(['status' => 'approved', 'usertype' => 'hub_staff', 'email' => 'staff.laguna@alona.com']);
        LogisticsHubStaff::create([
            'sorting_center_id' => $this->company->id,
            'logistics_hub_id' => $this->lagunaHub->id,
            'user_id' => $this->lagunaStaff->id,
            'is_active' => true,
        ]);

        $this->manilaStaff = User::factory()->create(['status' => 'approved', 'usertype' => 'hub_staff', 'email' => 'staff.manila@alona.com']);
        LogisticsHubStaff::create([
            'sorting_center_id' => $this->company->id,
            'logistics_hub_id' => $this->manilaHub->id,
            'user_id' => $this->manilaStaff->id,
            'is_active' => true,
        ]);

        // Laguna Rider
        $this->lagunaRider = User::factory()->create(['status' => 'approved', 'usertype' => 'rider']);
        $this->lagunaRiderModel = Rider::create([
            'user_id' => $this->lagunaRider->id,
            'sorting_center_id' => $this->company->id,
            'logistics_hub_id' => $this->lagunaHub->id,
            'name' => 'Laguna Courier',
            'contact' => '09171000001',
            'vehicle_type' => 'motorcycle',
            'plate_number' => 'LAG-101',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        // Manila Rider
        $this->manilaRider = User::factory()->create(['status' => 'approved', 'usertype' => 'rider']);
        $this->manilaRiderModel = Rider::create([
            'user_id' => $this->manilaRider->id,
            'sorting_center_id' => $this->company->id,
            'logistics_hub_id' => $this->manilaHub->id,
            'name' => 'Manila Courier',
            'contact' => '09172000002',
            'vehicle_type' => 'motorcycle',
            'plate_number' => 'MNL-202',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
    }

    private function createOrderAndParcel(string $orderNumber, string $originCity, string $destCity, int $originHubId, int $destHubId): Parcel
    {
        $seller = User::factory()->create(['status' => 'approved', 'usertype' => 'seller']);
        $buyer = User::factory()->create(['status' => 'approved', 'usertype' => 'buyer']);
        $category = Category::firstOrCreate(['slug' => 'test-cat'], ['name' => 'Test Cat', 'is_active' => true]);
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => 'Product ' . $orderNumber,
            'slug' => 'product-' . strtolower($orderNumber),
            'price' => 500,
            'stock' => 10,
            'status' => 'approved',
        ]);

        $order = Order::create([
            'user_id' => $buyer->id,
            'order_number' => $orderNumber,
            'status' => 'processing',
            'shipping_address' => $destCity,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'delivery_fee_payer' => 'buyer',
            'subtotal' => 500,
            'shipping_fee' => 80,
            'total' => 580,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'price' => 500,
            'status' => 'ready_for_pickup',
            'tracking_number' => 'TRK-' . $orderNumber,
            'courier_name' => $this->company->business_name,
        ]);

        $parcel = Parcel::create([
            'order_id' => 'ITEM-' . $orderNumber,
            'order_item_id' => $item->id,
            'sorting_center_id' => $this->company->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => 'PLACED',
            'collection_address' => $originCity,
            'delivery_address' => $destCity,
            'delivery_province' => 'Test',
            'delivery_municipality' => 'Test',
            'delivery_barangay' => 'Test',
            'delivery_street' => 'Street',
            'delivery_house_number' => '1',
        ]);

        ParcelHubJourney::create([
            'parcel_id' => $parcel->id,
            'sorting_center_id' => $this->company->id,
            'origin_hub_id' => $originHubId,
            'destination_hub_id' => $destHubId,
            'current_hub_id' => $originHubId,
            'route_snapshot' => [
                'company_name' => $this->company->business_name,
                'origin_hub_id' => $originHubId,
                'origin_hub_name' => $originHubId === $this->lagunaHub->id ? 'Laguna Operation Hub' : 'Manila Operation Hub',
                'destination_hub_id' => $destHubId,
                'destination_hub_name' => $destHubId === $this->lagunaHub->id ? 'Laguna Operation Hub' : 'Manila Operation Hub',
                'origin_city_code' => $originHubId === $this->lagunaHub->id ? '0403405000' : '1381600000',
                'destination_city_code' => $destHubId === $this->lagunaHub->id ? '0403405000' : '1381600000',
                'transport_mode' => $originHubId === $destHubId ? 'local' : 'road',
            ],
        ]);

        return $parcel;
    }

    public function test_laguna_to_laguna_local_delivery_flow(): void
    {
        $scanner = app(LogisticsSmartScan::class);
        $parcel = $this->createOrderAndParcel('LAG-TO-LAG-01', 'Calamba, Laguna', 'San Pedro, Laguna', $this->lagunaHub->id, $this->lagunaHub->id);

        // 1. Laguna Rider picks up at Laguna Seller
        $scan1 = $scanner->scan($this->lagunaRider, $parcel->order_id);
        $this->assertSame('PICKED_UP', $scan1['action_taken']);
        $this->assertSame('PICKED_UP', $parcel->fresh()->status);

        // 2. Laguna Hub Staff scans inbound
        $scan2 = $scanner->scan($this->lagunaStaff, $parcel->order_id);
        $this->assertSame('AT_SORTING_CENTER', $scan2['action_taken']);
        $this->assertSame('AT_SORTING_CENTER', $parcel->fresh()->status);

        // 3. Hub Staff checks: Local delivery does not need linehaul transfer
        $scanPrompt = $scanner->scan($this->lagunaStaff, $parcel->order_id);
        $this->assertSame('ready_for_local_delivery', $scanPrompt['stage']);

        // 4. Laguna Delivery Rider scans departure
        $scan3 = $scanner->scan($this->lagunaRider, $parcel->order_id);
        $this->assertSame('OUT_FOR_DELIVERY', $scan3['action_taken']);
        $this->assertSame('OUT_FOR_DELIVERY', $parcel->fresh()->status);

        // 5. Laguna Delivery Rider drops off with photo proof
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aQ1sAAAAASUVORK5CYII=');
        $photo = UploadedFile::fake()->createWithContent('proof.png', $png);
        $scan4 = $scanner->scan($this->lagunaRider, $parcel->order_id, ['photo' => $photo]);
        $this->assertSame('DELIVERED', $scan4['action_taken']);
        $this->assertSame('DELIVERED', $parcel->fresh()->status);
    }

    public function test_manila_to_laguna_inter_hub_delivery_flow(): void
    {
        $scanner = app(LogisticsSmartScan::class);
        $parcel = $this->createOrderAndParcel('MNL-TO-LAG-01', 'Manila City', 'Calamba, Laguna', $this->manilaHub->id, $this->lagunaHub->id);

        // 1. Manila Rider picks up at Manila Seller
        $scan1 = $scanner->scan($this->manilaRider, $parcel->order_id);
        $this->assertSame('PICKED_UP', $scan1['action_taken']);
        $this->assertSame('PICKED_UP', $parcel->fresh()->status);

        // 2. Manila Hub Staff scans inbound
        $scan2 = $scanner->scan($this->manilaStaff, $parcel->order_id);
        $this->assertSame('AT_SORTING_CENTER', $scan2['action_taken']);
        $this->assertSame('AT_SORTING_CENTER', $parcel->fresh()->status);

        // 3. Manila Hub dispatches linehaul truck to Laguna Hub
        $scan3 = $scanner->scan($this->manilaStaff, $parcel->order_id, [
            'transport_reference' => 'TRUCK-MNL-LAG-99',
        ]);
        $this->assertSame('IN_HUB_TRANSIT', $scan3['action_taken']);
        $this->assertSame('IN_HUB_TRANSIT', $parcel->fresh()->status);

        // Guard: Laguna Rider cannot dispatch out for delivery before arrival
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $scanner->scan($this->lagunaRider, $parcel->order_id);
    }

    public function test_manila_to_laguna_arrival_and_last_mile(): void
    {
        $scanner = app(LogisticsSmartScan::class);
        $parcel = $this->createOrderAndParcel('MNL-TO-LAG-02', 'Manila City', 'Calamba, Laguna', $this->manilaHub->id, $this->lagunaHub->id);

        // Move to IN_HUB_TRANSIT
        $scanner->scan($this->manilaRider, $parcel->order_id);
        $scanner->scan($this->manilaStaff, $parcel->order_id);
        $scanner->scan($this->manilaStaff, $parcel->order_id, ['transport_reference' => 'TRUCK-01']);
        $this->assertSame('IN_HUB_TRANSIT', $parcel->fresh()->status);

        // 4. Laguna Hub Staff receives linehaul truck from Manila
        $scanReceive = $scanner->scan($this->lagunaStaff, $parcel->order_id);
        $this->assertSame('AT_SORTING_CENTER', $scanReceive['action_taken']);
        $this->assertSame('AT_SORTING_CENTER', $parcel->fresh()->status);
        $this->assertNotNull($parcel->fresh()->hubJourney->destination_received_at);

        // 5. Laguna Delivery Rider scans departure
        $scanDispatch = $scanner->scan($this->lagunaRider, $parcel->order_id);
        $this->assertSame('OUT_FOR_DELIVERY', $scanDispatch['action_taken']);
        $this->assertSame('OUT_FOR_DELIVERY', $parcel->fresh()->status);

        // 6. Laguna Delivery Rider completes delivery with photo
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aQ1sAAAAASUVORK5CYII=');
        $photo = UploadedFile::fake()->createWithContent('proof.png', $png);
        $scanDelivered = $scanner->scan($this->lagunaRider, $parcel->order_id, ['photo' => $photo]);
        $this->assertSame('DELIVERED', $scanDelivered['action_taken']);
        $this->assertSame('DELIVERED', $parcel->fresh()->status);
    }

    public function test_laguna_to_manila_vice_versa_inter_hub_flow(): void
    {
        $scanner = app(LogisticsSmartScan::class);
        $parcel = $this->createOrderAndParcel('LAG-TO-MNL-01', 'Calamba, Laguna', 'Manila City', $this->lagunaHub->id, $this->manilaHub->id);

        // 1. Laguna Rider picks up at Laguna Seller
        $scan1 = $scanner->scan($this->lagunaRider, $parcel->order_id);
        $this->assertSame('PICKED_UP', $scan1['action_taken']);
        $this->assertSame('PICKED_UP', $parcel->fresh()->status);

        // 2. Laguna Hub Staff scans inbound
        $scan2 = $scanner->scan($this->lagunaStaff, $parcel->order_id);
        $this->assertSame('AT_SORTING_CENTER', $scan2['action_taken']);
        $this->assertSame('AT_SORTING_CENTER', $parcel->fresh()->status);

        // 3. Laguna Hub dispatches linehaul truck to Manila Hub
        $scan3 = $scanner->scan($this->lagunaStaff, $parcel->order_id, [
            'transport_reference' => 'TRUCK-LAG-MNL-55',
        ]);
        $this->assertSame('IN_HUB_TRANSIT', $scan3['action_taken']);
        $this->assertSame('IN_HUB_TRANSIT', $parcel->fresh()->status);

        // 4. Manila Hub Staff receives linehaul truck from Laguna
        $scanReceive = $scanner->scan($this->manilaStaff, $parcel->order_id);
        $this->assertSame('AT_SORTING_CENTER', $scanReceive['action_taken']);
        $this->assertSame('AT_SORTING_CENTER', $parcel->fresh()->status);
        $this->assertNotNull($parcel->fresh()->hubJourney->destination_received_at);

        // 5. Manila Delivery Rider scans departure
        $scanDispatch = $scanner->scan($this->manilaRider, $parcel->order_id);
        $this->assertSame('OUT_FOR_DELIVERY', $scanDispatch['action_taken']);
        $this->assertSame('OUT_FOR_DELIVERY', $parcel->fresh()->status);

        // 6. Manila Delivery Rider completes delivery with photo
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aQ1sAAAAASUVORK5CYII=');
        $photo = UploadedFile::fake()->createWithContent('proof.png', $png);
        $scanDelivered = $scanner->scan($this->manilaRider, $parcel->order_id, ['photo' => $photo]);
        $this->assertSame('DELIVERED', $scanDelivered['action_taken']);
        $this->assertSame('DELIVERED', $parcel->fresh()->status);
    }
}
