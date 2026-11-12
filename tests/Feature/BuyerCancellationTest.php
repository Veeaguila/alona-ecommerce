<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RefundLedger;
use App\Models\SellerNotification;
use App\Models\User;
use App\Models\UserVoucher;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BuyerCancellationTest extends TestCase
{
    use RefreshDatabase;

    protected User $buyer;
    protected User $seller;
    protected Category $category;
    protected Product $product;
    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::factory()->create([
            'name' => 'Cancel Test Seller',
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'CancelStore',
        ]);

        $this->buyer = User::factory()->create([
            'name' => 'Cancel Test Buyer',
            'usertype' => 'buyer',
            'email' => 'buyer.cancel@example.com',
            'status' => 'approved',
        ]);

        $this->category = Category::create([
            'name' => 'Appliances',
            'slug' => 'appliances',
        ]);

        $this->product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'name' => 'Air Fryer',
            'slug' => 'air-fryer',
            'price' => 2000.00,
            'stock' => 20,
            'weight' => 2.5,
            'status' => 'approved',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'name' => 'Black 4L',
            'price' => 2000.00,
            'stock' => 20,
        ]);
    }

    /**
     * Helper to create an order with item and variant.
     */
    protected function createOrder(array $attributes = [], array $itemAttributes = []): Order
    {
        $order = Order::create(array_merge([
            'user_id' => $this->buyer->id,
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'subtotal' => 2000.00,
            'shipping_fee' => 100.00,
            'total' => 2100.00,
            'shipping_address' => '456 Taft Ave, Pasay, Metro Manila',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'status' => 'pending',
        ], $attributes));

        OrderItem::create(array_merge([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'product_name' => $this->product->name,
            'price' => 2000.00,
            'quantity' => 2,
            'status' => $order->status,
        ], $itemAttributes));

        return $order->fresh(['items.product', 'items.variant']);
    }

    /**
     * BUY-CAN-001: Cancel COD order before shipping
     * (Order cancelled, stock restored to product/variant, seller notified).
     */
    public function test_buy_can_001_cancel_cod_order_before_shipping(): void
    {
        // Reduce stock to simulate purchase
        $this->product->update(['stock' => 18]);
        $this->variant->update(['stock' => 18]);

        $order = $this->createOrder([
            'payment_method' => 'cod',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->buyer)->post(route('buyer.orders.cancel', $order->id), [
            'reason' => 'Need to change shipping address',
        ]);

        $response->assertSessionHas('status');

        $order->refresh();
        $this->assertEquals('cancelled', $order->status);
        $this->assertEquals('Need to change shipping address', $order->cancellation_reason);
        $this->assertNotNull($order->cancelled_at);

        // Check stock restored (+2)
        $this->assertEquals(20, $this->variant->fresh()->stock);
        $this->assertEquals(20, $this->product->fresh()->stock);

        // Check seller notification created
        $this->assertDatabaseHas('seller_notifications', [
            'user_id' => $this->seller->id,
            'title' => 'Order Item Cancelled by Buyer',
        ]);
    }

    /**
     * BUY-CAN-002: Reason is required
     * (Must pick a reason before cancelling).
     */
    public function test_buy_can_002_reason_is_required(): void
    {
        $order = $this->createOrder();

        // Submit with empty reason
        $response = $this->actingAs($this->buyer)->post(route('buyer.orders.cancel', $order->id), [
            'reason' => '',
        ]);

        $response->assertSessionHasErrors('reason');

        $order->refresh();
        $this->assertEquals('pending', $order->status);
    }

    /**
     * BUY-CAN-003: Cancel a prepaid order
     * (Cancelling paid order starts refund and returns voucher).
     */
    public function test_buy_can_003_cancel_prepaid_order_starts_refund_and_returns_voucher(): void
    {
        $this->product->update(['stock' => 18]);
        $this->variant->update(['stock' => 18]);

        // Create voucher and claim it
        $voucher = Voucher::create([
            'seller_id' => $this->seller->id,
            'code' => 'CANCEL50',
            'type' => 'fixed',
            'value' => 50.00,
            'min_spend' => 500.00,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(7),
            'used_count' => 1,
            'is_active' => true,
        ]);

        $userVoucher = UserVoucher::create([
            'user_id' => $this->buyer->id,
            'voucher_id' => $voucher->id,
            'status' => 'used',
            'used_at' => now(),
        ]);

        // Paid order with voucher
        $order = $this->createOrder([
            'voucher_id' => $voucher->id,
            'voucher_code' => 'CANCEL50',
            'discount' => 50.00,
            'total' => 2050.00,
            'payment_method' => 'gcash',
            'payment_status' => 'paid',
            'status' => 'processing',
        ], [
            'status' => 'processing',
        ]);

        $response = $this->actingAs($this->buyer)->post(route('buyer.orders.cancel', $order->id), [
            'reason' => 'Found a cheaper price elsewhere',
        ]);

        $response->assertSessionHas('status');

        $order->refresh();
        $this->assertEquals('cancelled', $order->status);
        $this->assertEquals('refunded', $order->payment_status);
        $this->assertEquals('refund_started', $order->refund_status);
        $this->assertEquals(2050.00, (float) $order->refund_amount);

        // Refund ledger created
        $this->assertDatabaseHas('refund_ledgers', [
            'order_id' => $order->id,
            'buyer_id' => $this->buyer->id,
            'refunded_amount' => 2050.00,
            'status' => 'processing',
        ]);

        // Voucher restored
        $voucher->refresh();
        $this->assertEquals(0, $voucher->used_count);

        $userVoucher->refresh();
        $this->assertEquals('claimed', $userVoucher->status);
        $this->assertNull($userVoucher->used_at);

        // Stock restored
        $this->assertEquals(20, $this->variant->fresh()->stock);
    }

    /**
     * BUY-CAN-004: No cancel after Ready for Pickup
     * (Cancel button removed / action blocked once marked ready for pickup).
     */
    public function test_buy_can_004_no_cancel_after_ready_for_pickup(): void
    {
        $order = $this->createOrder([
            'status' => 'ready_for_pickup',
        ], [
            'status' => 'ready_for_pickup',
        ]);

        // In show page, can_cancel is false
        $showResponse = $this->actingAs($this->buyer)->get(route('buyer.orders.show', $order->id));
        $showResponse->assertOk();
        $showResponse->assertInertia(fn (Assert $page) => $page
            ->where('order.can_cancel', false)
        );

        // Attempt direct cancel
        $response = $this->actingAs($this->buyer)->post(route('buyer.orders.cancel', $order->id), [
            'reason' => 'Changed mind',
        ]);

        $response->assertSessionHasErrors('reason');
        $this->assertEquals('ready_for_pickup', $order->fresh()->status);
    }

    /**
     * BUY-CAN-005: No cancel after shipping
     * (Cancel button removed / action blocked when order is in transit).
     */
    public function test_buy_can_005_no_cancel_after_shipping(): void
    {
        $order = $this->createOrder([
            'status' => 'shipped',
        ], [
            'status' => 'shipped',
        ]);

        // In show page, can_cancel is false
        $showResponse = $this->actingAs($this->buyer)->get(route('buyer.orders.show', $order->id));
        $showResponse->assertOk();
        $showResponse->assertInertia(fn (Assert $page) => $page
            ->where('order.can_cancel', false)
        );

        // Attempt direct cancel
        $response = $this->actingAs($this->buyer)->post(route('buyer.orders.cancel', $order->id), [
            'reason' => 'Delivery lead time is too long',
        ]);

        $response->assertSessionHasErrors('reason');
        $this->assertEquals('shipped', $order->fresh()->status);
    }

    /**
     * BUY-CAN-006: Double-click Cancel
     * (Handled safely, stock only restored once).
     */
    public function test_buy_can_006_double_click_cancel_only_restores_stock_once(): void
    {
        $this->product->update(['stock' => 18]);
        $this->variant->update(['stock' => 18]);

        $order = $this->createOrder([
            'status' => 'pending',
        ]);

        // First cancel request
        $firstResponse = $this->actingAs($this->buyer)->post(route('buyer.orders.cancel', $order->id), [
            'reason' => 'Order created by mistake',
        ]);
        $firstResponse->assertSessionHas('status');

        $this->assertEquals('cancelled', $order->fresh()->status);
        $this->assertEquals(20, $this->variant->fresh()->stock);

        // Second duplicate cancel request (e.g. double-click)
        $secondResponse = $this->actingAs($this->buyer)->post(route('buyer.orders.cancel', $order->id), [
            'reason' => 'Order created by mistake',
        ]);

        $secondResponse->assertSessionHasErrors('reason');

        // Stock MUST remain 20 (not incremented again to 22)
        $this->assertEquals(20, $this->variant->fresh()->stock);
        $this->assertEquals(20, $this->product->fresh()->stock);
    }

    /**
     * Prepaid order cancellation creates a refund ledger entry without requiring an order_return_request_id.
     */
    public function test_prepaid_order_cancellation_creates_refund_ledger(): void
    {
        $order = $this->createOrder([
            'payment_method' => 'gcash',
            'payment_status' => 'paid',
            'status' => 'pending',
            'total' => 2100.00,
        ]);

        $response = $this->actingAs($this->buyer)->post(route('buyer.orders.cancel', $order->id), [
            'reason' => 'Changed mind / No longer needed',
        ]);

        $response->assertSessionHas('status');
        $this->assertEquals('cancelled', $order->fresh()->status);
        $this->assertEquals('refund_started', $order->fresh()->refund_status);

        $this->assertDatabaseHas('refund_ledgers', [
            'order_id' => $order->id,
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'refund_type' => 'full',
            'order_return_request_id' => null,
            'status' => 'processing',
        ]);
    }
}
