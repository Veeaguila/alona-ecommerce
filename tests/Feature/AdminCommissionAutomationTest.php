<?php

namespace Tests\Feature;

use App\Models\Commission;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCommissionAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_order_automatically_records_ten_percent_commission(): void
    {
        $seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'approved',
        ]);

        $buyer = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
        ]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Test Gadget',
            'slug' => 'test-gadget-' . uniqid(),
            'price' => 500.00,
            'stock' => 50,
            'status' => 'approved',
        ]);

        $order = Order::create([
            'user_id' => $buyer->id,
            'order_number' => 'ORD-TEST-001',
            'shipping_address' => '123 Main St, Tagbilaran City, Bohol',
            'subtotal' => 1000.00,
            'total' => 1000.00,
            'status' => 'pending',
            'payment_status' => 'paid',
            'payment_method' => 'cod',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 500.00,
            'quantity' => 2,
            'status' => 'pending',
        ]);

        // Marking as completed should trigger commission recording
        $order->update(['status' => 'completed']);

        $this->assertDatabaseHas('commissions', [
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'sale_amount' => 1000.00,
            'commission_rate' => 10.00,
            'commission_amount' => 100.00,
            'seller_amount' => 900.00,
            'status' => 'completed',
        ]);

        // Commission report route should return HTTP 200
        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']);
        $response = $this->actingAs($admin)->get(route('admin.reports.commission'));
        $response->assertOk();
    }

    public function test_cancelled_or_refunded_order_cancels_commission(): void
    {
        $seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'approved',
        ]);

        $buyer = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
        ]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Test Item',
            'slug' => 'test-item-' . uniqid(),
            'price' => 200.00,
            'stock' => 50,
            'status' => 'approved',
        ]);

        $order = Order::create([
            'user_id' => $buyer->id,
            'order_number' => 'ORD-TEST-002',
            'shipping_address' => '123 Main St, Tagbilaran City, Bohol',
            'subtotal' => 200.00,
            'total' => 200.00,
            'status' => 'pending',
            'payment_status' => 'paid',
            'payment_method' => 'cod',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 200.00,
            'quantity' => 1,
            'status' => 'pending',
        ]);

        $order->update(['status' => 'completed']);

        $this->assertDatabaseHas('commissions', [
            'order_id' => $order->id,
            'status' => 'completed',
        ]);

        // Cancelling the order cancels the commission
        $order->update(['status' => 'cancelled']);

        $this->assertDatabaseHas('commissions', [
            'order_id' => $order->id,
            'status' => 'cancelled',
        ]);
    }
}

