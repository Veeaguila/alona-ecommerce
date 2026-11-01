<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SellerFulfillmentWaybillAndPickupTest extends TestCase
{
    use RefreshDatabase;

    protected User $seller;
    protected User $buyer;
    protected Product $product;
    protected Order $order;
    protected OrderItem $orderItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'TechGear Official Store',
            'address' => 'Warehouse 4, Laguna Technopark, Santa Rosa, Laguna',
            'contact_no' => '09171234567',
        ]);

        $this->buyer = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
            'name' => 'Maria Santos',
            'contact_no' => '09189876543',
        ]);

        $category = Category::firstOrCreate(['slug' => 'electronics'], [
            'name' => 'Electronics',
            'slug' => 'electronics',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'seller_id' => $this->seller->id,
            'name' => 'Noise Cancelling Headphones',
            'slug' => 'noise-cancelling-headphones',
            'sku' => 'NCH-PRO-01',
            'price' => 3500.00,
            'stock' => 20,
            'status' => 'approved',
        ]);

        $this->order = Order::create([
            'user_id' => $this->buyer->id,
            'order_number' => 'ORD-2026-9999',
            'subtotal' => 3500.00,
            'total' => 3600.00,
            'shipping_fee' => 100.00,
            'status' => 'processing',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'shipping_address' => 'Unit 12B Horizon Tower, BGC, Taguig City, Metro Manila',
        ]);

        $this->orderItem = OrderItem::create([
            'order_id' => $this->order->id,
            'seller_id' => $this->seller->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'price' => 3500.00,
            'quantity' => 1,
            'subtotal' => 3500.00,
            'status' => 'packed',
        ]);
    }

    public function test_seller_can_view_printable_waybill(): void
    {
        $response = $this->actingAs($this->seller)
            ->get(route('seller.orders.waybill', $this->orderItem->id));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seller/ShippingDocuments')
            ->has('item')
            ->where('initialTab', 'waybill')
            ->where('item.id', $this->orderItem->id)
        );
    }

    public function test_seller_cannot_view_waybill_of_another_seller(): void
    {
        $otherSeller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Competitor Shop',
        ]);

        $response = $this->actingAs($otherSeller)
            ->get(route('seller.orders.waybill', $this->orderItem->id));

        $response->assertStatus(403);
    }

    public function test_seller_can_schedule_courier_pickup_for_order(): void
    {
        $scheduledDate = now()->addDays(1)->toDateString();

        $response = $this->actingAs($this->seller)
            ->post(route('seller.orders.schedule-pickup', $this->orderItem->id), [
                'scheduled_date' => $scheduledDate,
                'time_slot' => 'morning',
                'carrier_partner' => 'J&T Express Partner',
                'collection_address' => 'Dock 2, Laguna Technopark',
                'notes' => 'Handle with care: Electronics item',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        // Verify order item updated
        $this->orderItem->refresh();
        $this->assertEquals('ready_for_pickup', $this->orderItem->status);
        $this->assertEquals($scheduledDate, $this->orderItem->scheduled_pickup_date->toDateString());
        $this->assertEquals('morning', $this->orderItem->pickup_time_slot);
        $this->assertEquals('J&T Express Partner', $this->orderItem->preferred_carrier);
        $this->assertEquals('Handle with care: Electronics item', $this->orderItem->pickup_notes);

        // Verify parcel and pickup request were created
        $this->assertDatabaseHas('parcels', [
            'order_item_id' => $this->orderItem->id,
            'collection_address' => 'Dock 2, Laguna Technopark',
        ]);

        $this->assertDatabaseHas('pickup_requests', [
            'time_slot' => 'morning',
            'carrier_partner' => 'J&T Express Partner',
            'notes' => 'Handle with care: Electronics item',
        ]);

        $pickupRequest = $this->orderItem->parcel->pickupRequests()->latest()->first();
        $this->assertNotNull($pickupRequest);
        $this->assertEquals($scheduledDate, $pickupRequest->scheduled_date->toDateString());
    }

    public function test_schedule_pickup_validates_required_fields(): void
    {
        $response = $this->actingAs($this->seller)
            ->post(route('seller.orders.schedule-pickup', $this->orderItem->id), [
                'scheduled_date' => '',
                'time_slot' => 'invalid_slot',
                'carrier_partner' => '',
            ]);

        $response->assertSessionHasErrors(['scheduled_date', 'time_slot', 'carrier_partner']);
    }
}

