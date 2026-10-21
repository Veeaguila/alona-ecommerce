<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemStatusHistory;
use App\Models\OrderReturnRequest;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BuyerOrderTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected User $buyer;
    protected User $otherBuyer;
    protected User $seller;
    protected Category $category;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::factory()->create([
            'name' => 'Tracking Seller',
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'TrackStore',
        ]);

        $this->buyer = User::factory()->create([
            'name' => 'Tracking Buyer',
            'usertype' => 'buyer',
            'email' => 'buyer.tracking@example.com',
            'status' => 'approved',
        ]);

        $this->otherBuyer = User::factory()->create([
            'name' => 'Other Buyer',
            'usertype' => 'buyer',
            'email' => 'other.buyer@example.com',
            'status' => 'approved',
        ]);

        $this->category = Category::create([
            'name' => 'Fashion',
            'slug' => 'fashion',
        ]);

        $this->product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'name' => 'Smart Watch',
            'slug' => 'smart-watch',
            'price' => 1500.00,
            'stock' => 50,
            'weight' => 0.2,
            'status' => 'approved',
        ]);
    }

    /**
     * Helper to create an order with an item.
     */
    protected function createOrder(array $attributes = [], array $itemAttributes = []): Order
    {
        $order = Order::create(array_merge([
            'user_id' => $this->buyer->id,
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'subtotal' => 1500.00,
            'shipping_fee' => 50.00,
            'total' => 1550.00,
            'shipping_address' => '123 Rizal St, Makati, Metro Manila',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'status' => 'pending',
        ], $attributes));

        OrderItem::create(array_merge([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'price' => 1500.00,
            'quantity' => 1,
            'status' => $order->status,
        ], $itemAttributes));

        return $order->fresh(['items']);
    }

    /**
     * BUY-TRK-001: Orders appear in correct tab
     * (To Pay, To Ship, In Transit / To Receive, Delivered, Completed, Cancelled, Returns).
     */
    public function test_buy_trk_001_orders_appear_in_correct_tab(): void
    {
        // 1. To Pay: unpaid, non-COD
        $orderToPay = $this->createOrder([
            'payment_method' => 'gcash',
            'payment_status' => 'unpaid',
            'status' => 'pending',
        ]);

        // 2. To Ship: paid, pending
        $orderToShip = $this->createOrder([
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'status' => 'pending',
        ]);

        // 3. In Transit: shipped
        $orderInTransit = $this->createOrder([
            'payment_status' => 'paid',
            'status' => 'shipped',
        ], [
            'status' => 'shipped',
        ]);

        // 4. Delivered
        $orderDelivered = $this->createOrder([
            'payment_status' => 'paid',
            'status' => 'delivered',
            'delivered_at' => now()->subDay(),
        ], [
            'status' => 'delivered',
        ]);

        // 5. Completed
        $orderCompleted = $this->createOrder([
            'payment_status' => 'paid',
            'status' => 'completed',
        ], [
            'status' => 'completed',
        ]);

        // 6. Cancelled
        $orderCancelled = $this->createOrder([
            'status' => 'cancelled',
            'cancellation_reason' => 'Changed mind',
            'cancelled_at' => now(),
        ], [
            'status' => 'cancelled',
        ]);

        // 7. Returns: has return request
        $orderReturns = $this->createOrder([
            'payment_status' => 'paid',
            'status' => 'completed',
        ], [
            'status' => 'completed',
        ]);
        OrderReturnRequest::create([
            'order_id' => $orderReturns->id,
            'order_item_id' => $orderReturns->items->first()->id,
            'buyer_id' => $this->buyer->id,
            'user_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'reason' => 'wrong_item',
            'details' => 'Received wrong color',
            'status' => 'pending',
        ]);

        // Check tabs via GET /buyer/orders
        $responseToPay = $this->actingAs($this->buyer)->get(route('buyer.orders', ['status' => 'to_pay']));
        $responseToPay->assertOk();
        $responseToPay->assertInertia(fn (Assert $page) => $page
            ->component('Buyer/Orders')
            ->where('current_status', 'to_pay')
            ->where('counts.to_pay', 1)
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $orderToPay->id)
        );

        $responseToShip = $this->actingAs($this->buyer)->get(route('buyer.orders', ['status' => 'to_ship']));
        $responseToShip->assertOk();
        $responseToShip->assertInertia(fn (Assert $page) => $page
            ->where('current_status', 'to_ship')
            ->where('counts.to_ship', 1)
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $orderToShip->id)
        );

        $responseInTransit = $this->actingAs($this->buyer)->get(route('buyer.orders', ['status' => 'in_transit']));
        $responseInTransit->assertOk();
        $responseInTransit->assertInertia(fn (Assert $page) => $page
            ->where('current_status', 'in_transit')
            ->where('counts.in_transit', 1)
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $orderInTransit->id)
        );

        $responseDelivered = $this->actingAs($this->buyer)->get(route('buyer.orders', ['status' => 'delivered']));
        $responseDelivered->assertOk();
        $responseDelivered->assertInertia(fn (Assert $page) => $page
            ->where('current_status', 'delivered')
            ->where('counts.delivered', 1)
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $orderDelivered->id)
        );

        $responseCompleted = $this->actingAs($this->buyer)->get(route('buyer.orders', ['status' => 'completed']));
        $responseCompleted->assertOk();
        $responseCompleted->assertInertia(fn (Assert $page) => $page
            ->where('current_status', 'completed')
            ->where('counts.completed', 2) // $orderCompleted and $orderReturns (completed)
        );

        $responseCancelled = $this->actingAs($this->buyer)->get(route('buyer.orders', ['status' => 'cancelled']));
        $responseCancelled->assertOk();
        $responseCancelled->assertInertia(fn (Assert $page) => $page
            ->where('current_status', 'cancelled')
            ->where('counts.cancelled', 1)
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $orderCancelled->id)
        );

        $responseReturns = $this->actingAs($this->buyer)->get(route('buyer.orders', ['status' => 'returns']));
        $responseReturns->assertOk();
        $responseReturns->assertInertia(fn (Assert $page) => $page
            ->where('current_status', 'returns')
            ->where('counts.returns', 1)
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $orderReturns->id)
        );
    }

    /**
     * BUY-TRK-002: Timeline matches updates
     * (status timeline displays accepted, picked up, in-transit, delivered with timestamps).
     */
    public function test_buy_trk_002_timeline_matches_updates_with_timestamps(): void
    {
        $order = $this->createOrder([
            'status' => 'delivered',
            'delivered_at' => Carbon::now()->subHours(2),
        ], [
            'status' => 'delivered',
        ]);

        $item = $order->items->first();

        // Add chronological status history records
        $time1 = Carbon::now()->subDays(3);
        $time2 = Carbon::now()->subDays(2);
        $time3 = Carbon::now()->subDays(1);
        $time4 = Carbon::now()->subHours(2);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => 'processing',
            'note' => 'Order accepted by seller.',
            'created_at' => $time1,
        ]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => 'packed',
            'note' => 'Parcel packed and ready for courier pickup.',
            'created_at' => $time2,
        ]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => 'shipped',
            'note' => 'Parcel in transit to delivery hub.',
            'created_at' => $time3,
        ]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => 'delivered',
            'note' => 'Parcel successfully delivered.',
            'created_at' => $time4,
        ]);

        $response = $this->actingAs($this->buyer)->get(route('buyer.orders.show', $order->id));
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Buyer/OrderDetails')
            ->has('order.status_histories', 4)
            ->where('order.status_histories.0.status', 'processing')
            ->where('order.status_histories.1.status', 'packed')
            ->where('order.status_histories.2.status', 'shipped')
            ->where('order.status_histories.3.status', 'delivered')
        );
    }

    /**
     * BUY-TRK-003: Order Received button completes order and unlocks Rate.
     */
    public function test_buy_trk_003_order_received_completes_order_and_unlocks_rate(): void
    {
        $order = $this->createOrder([
            'status' => 'delivered',
            'delivered_at' => now()->subDay(),
        ], [
            'status' => 'delivered',
        ]);

        // Show page before receive: can_mark_received is true, status is delivered
        $showBefore = $this->actingAs($this->buyer)->get(route('buyer.orders.show', $order->id));
        $showBefore->assertOk();
        $showBefore->assertInertia(fn (Assert $page) => $page
            ->where('order.status', 'delivered')
            ->where('order.can_mark_received', true)
        );

        // Buyer clicks "Order Received"
        $response = $this->actingAs($this->buyer)->post(route('buyer.orders.receive', $order->id));
        $response->assertSessionHas('status');

        $order->refresh();
        $this->assertEquals('completed', $order->status);
        $this->assertEquals('completed', $order->items->first()->status);

        // Show page after receive: status is completed, can_review is true (unlocks Rate)
        $showAfter = $this->actingAs($this->buyer)->get(route('buyer.orders.show', $order->id));
        $showAfter->assertOk();
        $showAfter->assertInertia(fn (Assert $page) => $page
            ->where('order.status', 'completed')
            ->where('order.can_mark_received', false)
            ->where('order.items.0.can_review', true)
        );
    }

    /**
     * BUY-TRK-004: Cannot mark order received while still in transit
     * (Order Received button hidden and action rejected before delivery).
     */
    public function test_buy_trk_004_cannot_mark_order_received_while_in_transit(): void
    {
        // Order is in transit (shipped)
        $order = $this->createOrder([
            'status' => 'shipped',
        ], [
            'status' => 'shipped',
        ]);

        // UI check: can_mark_received is false, item cannot confirm delivery
        $showResponse = $this->actingAs($this->buyer)->get(route('buyer.orders.show', $order->id));
        $showResponse->assertOk();
        $showResponse->assertInertia(fn (Assert $page) => $page
            ->where('order.status', 'shipped')
            ->where('order.can_mark_received', false)
            ->where('order.items.0.can_confirm_delivery', false)
        );

        // Direct request attempt while in transit must be rejected with validation error
        $response = $this->actingAs($this->buyer)->post(route('buyer.orders.receive', $order->id));
        $response->assertSessionHasErrors('status');

        // Verify order status was not changed
        $order->refresh();
        $this->assertEquals('shipped', $order->status);

        // Item confirm-delivery attempt must also be rejected
        $itemResponse = $this->actingAs($this->buyer)->patch(
            route('buyer.order-items.confirm-delivery', $order->items->first()->id)
        );
        $itemResponse->assertSessionHasErrors('status');
        $this->assertEquals('shipped', $order->items->first()->fresh()->status);
    }

    /**
     * BUY-TRK-005: Delivered order auto-completes after 7 days
     * (system automatically updates status to Completed after 7 days; status history recorded).
     */
    public function test_buy_trk_005_delivered_order_auto_completes_after_7_days(): void
    {
        // 1. Order delivered 8 days ago -> should auto-complete
        $oldDeliveredOrder = $this->createOrder([
            'status' => 'delivered',
            'delivered_at' => Carbon::now()->subDays(8),
        ], [
            'status' => 'delivered',
            'delivered_at' => Carbon::now()->subDays(8),
        ]);

        // 2. Order delivered 3 days ago -> should NOT auto-complete yet
        $recentDeliveredOrder = $this->createOrder([
            'status' => 'delivered',
            'delivered_at' => Carbon::now()->subDays(3),
        ], [
            'status' => 'delivered',
            'delivered_at' => Carbon::now()->subDays(3),
        ]);

        // Run the scheduled artisan command
        $exitCode = \Illuminate\Support\Facades\Artisan::call('orders:auto-complete-delivered');
        $this->assertEquals(0, $exitCode);

        // Verify old delivered order is now completed
        $oldDeliveredOrder->refresh();
        $this->assertEquals('completed', $oldDeliveredOrder->status);
        $this->assertEquals('completed', $oldDeliveredOrder->items->first()->status);

        // Verify status history recorded
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $oldDeliveredOrder->id,
            'status' => 'completed',
            'note' => 'Order auto-completed after 7 days in delivered status.',
        ]);

        // Verify recent delivered order remains delivered
        $recentDeliveredOrder->refresh();
        $this->assertEquals('delivered', $recentDeliveredOrder->status);
    }

    /**
     * BUY-TRK-006: Cannot open another buyer's order
     * (viewing another buyer's order URL returns 403 Forbidden).
     */
    public function test_buy_trk_006_cannot_open_another_buyers_order(): void
    {
        // Order belongs to $this->buyer
        $order = $this->createOrder();

        // Other buyer tries to view order details
        $response = $this->actingAs($this->otherBuyer)->get(route('buyer.orders.show', $order->id));
        $response->assertStatus(403);

        // Other buyer tries to mark order as received
        $receiveResponse = $this->actingAs($this->otherBuyer)->post(route('buyer.orders.receive', $order->id));
        $receiveResponse->assertStatus(403);

        // Other buyer tries to confirm delivery on item
        $confirmResponse = $this->actingAs($this->otherBuyer)->patch(
            route('buyer.order-items.confirm-delivery', $order->items->first()->id)
        );
        $confirmResponse->assertStatus(403);
    }
}
