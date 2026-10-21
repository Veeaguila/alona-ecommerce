<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $buyer;
    protected User $seller;
    protected Category $category;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::factory()->create([
            'name' => 'Payment Test Seller',
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'PayStore',
        ]);

        $this->buyer = User::factory()->create([
            'name' => 'Payment Test Buyer',
            'usertype' => 'buyer',
            'email' => 'buyer.payment@example.com',
            'status' => 'approved',
        ]);

        $this->category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
        ]);

        $this->product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'name' => 'Wireless Headphones',
            'slug' => 'wireless-headphones',
            'price' => 500.00,
            'stock' => 20,
            'weight' => 0.4,
            'status' => 'approved',
        ]);
    }

    /**
     * BUY-PAY-001: COD order moves straight to To Ship with COD amount to collect.
     */
    public function test_buy_pay_001_cod_order_moves_straight_to_to_ship(): void
    {
        $cartItem = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($this->buyer)->post(route('buyer.orders.store'), [
            'shipping_address' => '123 Market St, Manila, NCR',
            'payment_method' => 'cod',
            'shipping_method' => 'standard',
            'selected_items' => [$cartItem->id],
        ]);

        $response->assertSessionHasNoErrors();
        $order = Order::where('user_id', $this->buyer->id)->latest('id')->firstOrFail();

        $this->assertEquals('cod', $order->payment_method);
        $this->assertEquals('unpaid', $order->payment_status);
        $this->assertEquals(550.00, (float) $order->total); // 500 product + 50 NCR shipping

        // Orders tab counts verification
        $ordersRes = $this->actingAs($this->buyer)->get(route('buyer.orders'));
        $ordersRes->assertOk();
        $counts = $ordersRes->viewData('page')['props']['counts'];

        $this->assertEquals(0, $counts['to_pay']);
        $this->assertEquals(1, $counts['to_ship']);
    }

    /**
     * BUY-PAY-002: E-wallet/card payment succeeds and order moves to To Ship.
     */
    public function test_buy_pay_002_ewallet_card_payment_succeeds_and_moves_to_to_ship(): void
    {
        $cartItem = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($this->buyer)->post(route('buyer.orders.store'), [
            'shipping_address' => '123 Market St, Manila, NCR',
            'payment_method' => 'gcash',
            'shipping_method' => 'standard',
            'selected_items' => [$cartItem->id],
        ])->assertSessionHasNoErrors();

        $order = Order::where('user_id', $this->buyer->id)->latest('id')->firstOrFail();
        $this->assertEquals('unpaid', $order->payment_status);

        // Before payment: in To Pay
        $ordersResBefore = $this->actingAs($this->buyer)->get(route('buyer.orders'));
        $this->assertEquals(1, $ordersResBefore->viewData('page')['props']['counts']['to_pay']);
        $this->assertEquals(0, $ordersResBefore->viewData('page')['props']['counts']['to_ship']);

        // Process successful payment
        $payRes = $this->actingAs($this->buyer)->post(route('buyer.orders.pay', $order), [
            'action' => 'success',
            'payment_reference' => 'GCASH-TXN-12345',
        ]);

        $payRes->assertSessionHasNoErrors();
        $payRes->assertRedirect(route('buyer.orders.show', $order));

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);

        // After payment: moved to To Ship
        $ordersResAfter = $this->actingAs($this->buyer)->get(route('buyer.orders'));
        $this->assertEquals(0, $ordersResAfter->viewData('page')['props']['counts']['to_pay']);
        $this->assertEquals(1, $ordersResAfter->viewData('page')['props']['counts']['to_ship']);
    }

    /**
     * BUY-PAY-003: Payment fails and order stays in To Pay with retry option.
     */
    public function test_buy_pay_003_payment_fails_order_stays_in_to_pay(): void
    {
        $cartItem = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($this->buyer)->post(route('buyer.orders.store'), [
            'shipping_address' => '123 Market St, Manila, NCR',
            'payment_method' => 'card',
            'shipping_method' => 'standard',
            'selected_items' => [$cartItem->id],
        ])->assertSessionHasNoErrors();

        $order = Order::where('user_id', $this->buyer->id)->latest('id')->firstOrFail();

        // Simulate failed payment
        $payRes = $this->actingAs($this->buyer)->post(route('buyer.orders.pay', $order), [
            'action' => 'fail',
        ]);

        $payRes->assertSessionHasErrors('payment');

        $order->refresh();
        $this->assertEquals('unpaid', $order->payment_status);
        $this->assertEquals('pending', $order->status);

        // Order remains in To Pay tab
        $ordersRes = $this->actingAs($this->buyer)->get(route('buyer.orders'));
        $this->assertEquals(1, $ordersRes->viewData('page')['props']['counts']['to_pay']);
    }

    /**
     * BUY-PAY-004: Buyer cancels at gateway and returns to order details in To Pay.
     */
    public function test_buy_pay_004_buyer_cancels_at_gateway_returns_to_to_pay(): void
    {
        $cartItem = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($this->buyer)->post(route('buyer.orders.store'), [
            'shipping_address' => '123 Market St, Manila, NCR',
            'payment_method' => 'maya',
            'shipping_method' => 'standard',
            'selected_items' => [$cartItem->id],
        ])->assertSessionHasNoErrors();

        $order = Order::where('user_id', $this->buyer->id)->latest('id')->firstOrFail();

        // Buyer cancels at gateway
        $payRes = $this->actingAs($this->buyer)->post(route('buyer.orders.pay', $order), [
            'action' => 'cancel',
        ]);

        $payRes->assertRedirect(route('buyer.orders.show', $order));

        $order->refresh();
        $this->assertEquals('unpaid', $order->payment_status);
        $this->assertEquals('pending', $order->status);

        // No duplicate orders created
        $this->assertCount(1, Order::where('user_id', $this->buyer->id)->get());
    }

    /**
     * BUY-PAY-005: Browser closed on gateway; order updates to To Ship on webhook.
     */
    public function test_buy_pay_005_webhook_arrives_and_updates_order_to_to_ship(): void
    {
        $cartItem = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($this->buyer)->post(route('buyer.orders.store'), [
            'shipping_address' => '123 Market St, Manila, NCR',
            'payment_method' => 'gcash',
            'shipping_method' => 'standard',
            'selected_items' => [$cartItem->id],
        ])->assertSessionHasNoErrors();

        $order = Order::where('user_id', $this->buyer->id)->latest('id')->firstOrFail();
        $this->assertEquals('unpaid', $order->payment_status);

        // Webhook arrives asynchronously
        $webhookRes = $this->postJson(route('payment.webhook'), [
            'order_id' => $order->id,
            'status' => 'paid',
            'reference' => 'WEBHOOK-PAY-999',
        ]);

        $webhookRes->assertOk();
        $webhookRes->assertJson([
            'status' => 'success',
            'payment_status' => 'paid',
        ]);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);

        // Order is now in To Ship
        $ordersRes = $this->actingAs($this->buyer)->get(route('buyer.orders'));
        $this->assertEquals(1, $ordersRes->viewData('page')['props']['counts']['to_ship']);
        $this->assertEquals(0, $ordersRes->viewData('page')['props']['counts']['to_pay']);
    }

    /**
     * BUY-PAY-006: Unpaid order auto-cancels after 1 hour and restores stock.
     */
    public function test_buy_pay_006_unpaid_order_auto_cancels_after_1_hour_and_restores_stock(): void
    {
        $this->product->update(['stock' => 10]);

        $cartItem = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $this->actingAs($this->buyer)->post(route('buyer.orders.store'), [
            'shipping_address' => '123 Market St, Manila, NCR',
            'payment_method' => 'gcash',
            'shipping_method' => 'standard',
            'selected_items' => [$cartItem->id],
        ])->assertSessionHasNoErrors();

        // 2 units deducted -> 8 remaining
        $this->assertEquals(8, $this->product->fresh()->stock);

        $order = Order::where('user_id', $this->buyer->id)->latest('id')->firstOrFail();

        // Advance time past 1 hour (61 minutes)
        Carbon::setTestNow(now()->addMinutes(61));

        // Auto-cancel runs via artisan worker
        $this->artisan('orders:auto-cancel-unpaid')->assertExitCode(0);

        $order->refresh();
        $this->assertEquals('cancelled', $order->status);
        $this->assertEquals('Cancelled (unpaid)', $order->cancellation_reason);

        // Stock restored back to 10
        $this->assertEquals(10, $this->product->fresh()->stock);

        Carbon::setTestNow();
    }

    /**
     * BUY-PAY-007: Pay just before vs after auto-cancel (59-min payment works; 61-min order is cancelled).
     */
    public function test_buy_pay_007_pay_just_before_vs_after_auto_cancel(): void
    {
        // 1. Order A: paid at 59 minutes -> succeeds
        $cartItemA = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($this->buyer)->post(route('buyer.orders.store'), [
            'shipping_address' => '123 Market St, Manila, NCR',
            'payment_method' => 'gcash',
            'shipping_method' => 'standard',
            'selected_items' => [$cartItemA->id],
        ])->assertSessionHasNoErrors();

        $orderA = Order::where('user_id', $this->buyer->id)->latest('id')->firstOrFail();

        // Advance time to 59 minutes
        Carbon::setTestNow(now()->addMinutes(59));

        $payResA = $this->actingAs($this->buyer)->post(route('buyer.orders.pay', $orderA), [
            'action' => 'success',
        ]);
        $payResA->assertSessionHasNoErrors();
        $this->assertEquals('paid', $orderA->fresh()->payment_status);

        // 2. Order B: attempted to pay at 61 minutes -> blocked and auto-cancelled
        Carbon::setTestNow(); // Reset time to now for new order
        $cartItemB = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($this->buyer)->post(route('buyer.orders.store'), [
            'shipping_address' => '123 Market St, Manila, NCR',
            'payment_method' => 'maya',
            'shipping_method' => 'standard',
            'selected_items' => [$cartItemB->id],
        ])->assertSessionHasNoErrors();

        $orderB = Order::where('user_id', $this->buyer->id)->latest('id')->firstOrFail();

        // Advance time to 61 minutes
        Carbon::setTestNow(now()->addMinutes(61));

        $payResB = $this->actingAs($this->buyer)->post(route('buyer.orders.pay', $orderB), [
            'action' => 'success',
        ]);
        $payResB->assertSessionHasErrors('payment');

        $orderB->refresh();
        $this->assertEquals('cancelled', $orderB->status);
        $this->assertEquals('Cancelled (unpaid)', $orderB->cancellation_reason);

        Carbon::setTestNow();
    }
}

