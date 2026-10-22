<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Parcel;
use App\Models\PickupRequest;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingIntegrationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_express_order_is_flagged_for_seller_and_logistics(): void
    {
        $seller = User::factory()->create(['usertype' => 'seller', 'status' => 'approved']);
        $buyer = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $category = Category::create(['name' => 'Tech', 'slug' => 'tech']);

        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => 'Wireless Mouse',
            'slug' => 'wireless-mouse',
            'price' => 500,
            'stock' => 10,
            'status' => 'approved',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-EXP-001',
            'user_id' => $buyer->id,
            'shipping_address' => '123 Main St, Manila',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'shipping_method' => 'express',
            'subtotal' => 500.00,
            'shipping_fee' => 120.00,
            'discount' => 0.00,
            'total' => 620.00,
            'status' => 'processing',
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 500.00,
            'quantity' => 1,
            'status' => 'packed',
        ]);

        // 1. Check Seller Show endpoint
        $response = $this->actingAs($seller)->get(route('seller.order.details', $orderItem->id));
        $response->assertOk();
        $orderSummary = $response->viewData('page')['props']['orderSummary'];
        $this->assertEquals('express', $orderSummary['shipping_method']);
        $this->assertTrue($orderSummary['is_express']);
        $this->assertFalse($orderSummary['is_pickup']);

        // 2. Ready for pickup creates express parcel and courier pickup request
        $this->actingAs($seller)->patch(route('seller.orders.ready-for-pickup', $orderItem->id));

        $parcel = Parcel::where('order_item_id', $orderItem->id)->first();
        $this->assertNotNull($parcel);
        $this->assertTrue($parcel->is_express);
        $this->assertEquals('express', $parcel->shipping_method);

        $pickupReq = PickupRequest::where('parcel_id', $parcel->id)->first();
        $this->assertNotNull($pickupReq);
        $this->assertTrue($pickupReq->is_express);
    }

    public function test_store_pickup_order_bypasses_courier_and_can_be_completed_in_store(): void
    {
        $seller = User::factory()->create(['usertype' => 'seller', 'status' => 'approved']);
        $buyer = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $category = Category::create(['name' => 'Tech', 'slug' => 'tech']);

        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => 'Mechanical Keyboard',
            'slug' => 'mechanical-keyboard',
            'price' => 2500,
            'stock' => 5,
            'status' => 'approved',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-PCK-001',
            'user_id' => $buyer->id,
            'shipping_address' => 'Store Pickup at Seller Location',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'shipping_method' => 'pickup',
            'subtotal' => 2500.00,
            'shipping_fee' => 0.00,
            'discount' => 0.00,
            'total' => 2500.00,
            'status' => 'processing',
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 2500.00,
            'quantity' => 1,
            'status' => 'packed',
        ]);

        // Seller marks ready for pickup
        $this->actingAs($seller)->patch(route('seller.orders.ready-for-pickup', $orderItem->id))
            ->assertRedirect();

        $orderItem->refresh();
        $this->assertEquals('ready_for_pickup', $orderItem->status);

        // Crucial: Store pickup must NOT create a courier PickupRequest
        $pickupReq = PickupRequest::whereHas('parcel', fn ($q) => $q->where('order_item_id', $orderItem->id))->first();
        $this->assertNull($pickupReq);

        // Seller marks completed when customer collects in-store
        $this->actingAs($seller)->patch(route('seller.orders.complete-pickup', $orderItem->id))
            ->assertRedirect();

        $orderItem->refresh();
        $this->assertEquals('delivered', $orderItem->status);
    }
}
