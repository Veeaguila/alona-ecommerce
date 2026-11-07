<?php

namespace Tests\Feature\Logistics;

use App\Http\Controllers\SellerReportController;
use App\Models\{Category, Commission, DeliveryProof, LogisticsHub, LogisticsRoute, Order, OrderItem, Parcel, ParcelHubJourney, Product, Rider, RiderPayEarning, RiderPaySetting, SellerDeliveryCharge, SortingCenter, User};
use App\Services\{CodLedger, CommissionService, LogisticsSmartScan, RiderJobPay};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialDistributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_financial_distribution_across_all_parties(): void
    {
        Storage::fake('local');

        // 1. Company Admin / Sorting Center
        $adminUser = User::factory()->create(['status' => 'approved', 'usertype' => 'sorting_center']);
        $company = SortingCenter::create([
            'user_id' => $adminUser->id,
            'business_name' => 'FastLogistics Corp',
            'approval_status' => 'approved',
        ]);

        // Hubs
        $hub = LogisticsHub::create([
            'sorting_center_id' => $company->id,
            'code' => 'LAG-01',
            'name' => 'Laguna Hub',
            'address' => 'Calamba, Laguna',
            'city_code' => '043405000',
            'pickup_city_codes' => ['043405000'],
            'delivery_city_codes' => ['043405000'],
            'is_active' => true,
        ]);

        // Rider Pay Settings (Enable Job Earnings)
        RiderPaySetting::create([
            'sorting_center_id' => $company->id,
            'enabled' => true,
            'revision' => 1,
            'pickup_cents' => 3000,   // ₱30.00
            'delivery_cents' => 4500, // ₱45.00
            'transfer_cents' => 50000, // ₱500.00
        ]);

        // 2. Pickup and Delivery Riders
        $pickupRiderUser = User::factory()->create(['status' => 'approved', 'usertype' => 'rider']);
        $pickupRider = Rider::create([
            'user_id' => $pickupRiderUser->id,
            'sorting_center_id' => $company->id,
            'logistics_hub_id' => $hub->id,
            'name' => 'Pickup Rider',
            'contact' => '09171111111',
            'vehicle_type' => 'motorcycle',
            'plate_number' => 'PCK-101',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $deliveryRiderUser = User::factory()->create(['status' => 'approved', 'usertype' => 'rider']);
        $deliveryRider = Rider::create([
            'user_id' => $deliveryRiderUser->id,
            'sorting_center_id' => $company->id,
            'logistics_hub_id' => $hub->id,
            'name' => 'Delivery Rider',
            'contact' => '09172222222',
            'vehicle_type' => 'motorcycle',
            'plate_number' => 'DEL-202',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        // 3. Seller and Buyer
        $seller = User::factory()->create(['status' => 'approved', 'usertype' => 'seller']);
        $buyer = User::factory()->create(['status' => 'approved', 'usertype' => 'buyer']);

        $category = Category::firstOrCreate(['slug' => 'electronics'], ['name' => 'Electronics', 'is_active' => true]);
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => 'Wireless Keyboard',
            'slug' => 'wireless-keyboard',
            'price' => 1000,
            'stock' => 20,
            'status' => 'approved',
        ]);

        // Buyer order: Product ₱1000 + Shipping ₱100 = Total ₱1100 (COD)
        $order = Order::create([
            'user_id' => $buyer->id,
            'order_number' => 'ALN-' . strtoupper(uniqid()),
            'status' => 'processing',
            'shipping_address' => 'Calamba, Laguna',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'delivery_fee_payer' => 'buyer',
            'subtotal' => 1000,
            'shipping_fee' => 100,
            'total' => 1100,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'price' => 1000,
            'status' => 'ready_for_pickup',
            'tracking_number' => 'TRK-' . strtoupper(uniqid()),
            'courier_name' => $company->business_name,
        ]);

        $parcel = Parcel::create([
            'order_id' => 'ITEM-100',
            'order_item_id' => $item->id,
            'sorting_center_id' => $company->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => 'PLACED',
            'collection_address' => 'Calamba, Laguna',
            'delivery_address' => 'Calamba, Laguna',
            'delivery_province' => 'Laguna',
            'delivery_municipality' => 'Calamba',
            'delivery_barangay' => 'Poblacion',
            'delivery_street' => 'Street 1',
            'delivery_house_number' => '1',
        ]);

        ParcelHubJourney::create([
            'parcel_id' => $parcel->id,
            'sorting_center_id' => $company->id,
            'origin_hub_id' => $hub->id,
            'destination_hub_id' => $hub->id,
            'current_hub_id' => $hub->id,
            'route_snapshot' => [
                'company_name' => $company->business_name,
                'origin_hub_id' => $hub->id,
                'origin_hub_name' => $hub->name,
                'destination_hub_id' => $hub->id,
                'destination_hub_name' => $hub->name,
                'origin_city_code' => '043405000',
                'destination_city_code' => '043405000',
                'transport_mode' => 'road',
            ],
        ]);

        $scanner = app(LogisticsSmartScan::class);

        // --- STEP 1: Pickup Rider Scans at Store ---
        $scan1 = $scanner->scan($pickupRiderUser, 'ITEM-100');
        $this->assertSame('PICKED_UP', $scan1['action_taken']);
        $this->assertSame('PICKED_UP', $parcel->fresh()->status);

        // --- STEP 2: Hub Staff Scans Inbound at Hub ---
        $scan2 = $scanner->scan($adminUser, 'ITEM-100', ['hub_id' => $hub->id]);
        $this->assertSame('AT_SORTING_CENTER', $scan2['action_taken']);
        $this->assertSame('AT_SORTING_CENTER', $parcel->fresh()->status);

        // --- STEP 3: Delivery Rider Scans Departure ---
        $scan3 = $scanner->scan($deliveryRiderUser, 'ITEM-100');
        $this->assertSame('OUT_FOR_DELIVERY', $scan3['action_taken']);
        $this->assertSame('OUT_FOR_DELIVERY', $parcel->fresh()->status);

        // --- STEP 4: Delivery Rider Scans & Drops off with Photo Proof & Collects COD (₱1100) ---
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aQ1sAAAAASUVORK5CYII=');
        $photo = UploadedFile::fake()->createWithContent('proof.png', $png);

        $scan4 = $scanner->scan($deliveryRiderUser, 'ITEM-100', ['photo' => $photo]);
        $this->assertSame('DELIVERED', $scan4['action_taken']);
        $this->assertSame('DELIVERED', $parcel->fresh()->status);

        // --- VERIFY 1: Rider COD Collection ---
        // Rider collected ₱1,100 from Buyer
        $this->assertDatabaseHas('cod_collections', [
            'parcel_id' => $parcel->id,
            'rider_id' => $deliveryRider->id,
            'amount' => '1100.00',
        ]);

        // --- VERIFY 1B: Rider Job Earnings (Pickup & Delivery Rider Pay) ---
        $pickupEarning = RiderPayEarning::where('rider_id', $pickupRider->id)
            ->where('leg', 'pickup')
            ->first();
        $this->assertNotNull($pickupEarning, 'Pickup rider must receive a job earning snapshot.');
        $this->assertSame('earned', $pickupEarning->status, 'Pickup rider earning must be marked earned upon hub receipt.');
        $this->assertSame(3000, $pickupEarning->amount_cents, 'Pickup rider receives ₱30.00 for origin pickup.');

        $deliveryEarning = RiderPayEarning::where('rider_id', $deliveryRider->id)
            ->where('leg', 'delivery')
            ->first();
        $this->assertNotNull($deliveryEarning, 'Delivery rider must receive a job earning snapshot.');
        $this->assertSame('earned', $deliveryEarning->status, 'Delivery rider earning must be marked earned upon photo delivery.');
        $this->assertSame(4500, $deliveryEarning->amount_cents, 'Delivery rider receives ₱45.00 for last-mile delivery.');

        // --- VERIFY 2: Platform Commission Distribution ---
        // 10% commission on ₱1,000 product sale = ₱100 to Platform, ₱900 to Seller
        $commission = Commission::where('order_id', $order->id)->where('seller_id', $seller->id)->first();
        $this->assertNotNull($commission, 'Platform commission must be auto-recorded upon delivery.');
        $this->assertEquals(100.00, (float) $commission->commission_amount, 'Platform receives 10% (₱100).');
        $this->assertEquals(900.00, (float) $commission->seller_amount, 'Seller receives 90% (₱900).');

        // --- VERIFY 3: Seller Balances ---
        $reportCtrl = app(SellerReportController::class);
        $balances = $reportCtrl->calculateSellerBalances($seller->id);
        // While in return window (delivered), funds are in pending_balance (95% net after 5% platform fee)
        $this->assertEquals(950.00, $balances['pending_balance'], 'Seller has ₱950 in pending balance while delivered.');

        // Buyer confirms "Order Received"
        $this->actingAs($buyer, 'web')->post("/buyer/orders/{$order->id}/receive");
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('completed', $item->fresh()->status);

        // Funds immediately become withdrawable in available_balance!
        $updatedBalances = $reportCtrl->calculateSellerBalances($seller->id);
        $this->assertEquals(950.00, $updatedBalances['available_balance'], 'Seller has ₱950 in withdrawable available balance after completion.');
        $this->assertEquals(0.00, $updatedBalances['pending_balance']);
    }
}
