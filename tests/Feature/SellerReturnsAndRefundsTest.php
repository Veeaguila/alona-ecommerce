<?php

namespace Tests\Feature;

use App\Models\BuyerNotification;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturnRequest;
use App\Models\Product;
use App\Models\RefundLedger;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SellerReturnsAndRefundsTest extends TestCase
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
            'store_name' => 'Apex Electronics',
            'street_address' => '123 Warehouse St',
            'province' => 'Metro Manila',
            'municipality' => 'Pasig City',
            'barangay' => 'San Antonio',
        ]);

        $this->buyer = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
            'name' => 'Juan Dela Cruz',
        ]);

        $category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'seller_id' => $this->seller->id,
            'name' => 'Wireless Gaming Headset',
            'slug' => 'wireless-gaming-headset',
            'price' => 1500.00,
            'stock' => 10,
            'status' => 'approved',
        ]);

        $this->order = Order::create([
            'user_id' => $this->buyer->id,
            'order_number' => 'ALN-20261015-883921',
            'total' => 1500.00,
            'shipping_address' => '123 Buyer St, Manila',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'gcash',
        ]);

        $this->orderItem = OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'price' => 1500.00,
            'quantity' => 1,
            'status' => 'delivered',
        ]);
    }

    /**
     * SELLER-36 & SELLER-37: Dedicated returns center renders with dynamic status counts & filters.
     */
    public function test_dedicated_returns_center_renders_with_actual_counts_and_filters(): void
    {
        // Create return requests with various statuses
        OrderReturnRequest::create([
            'order_id' => $this->order->id,
            'order_item_id' => $this->orderItem->id,
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'type' => 'return_and_refund',
            'reason' => 'defective',
            'reason_details' => 'Microphone is not working',
            'requested_amount' => 1500.00,
            'status' => 'pending',
        ]);

        OrderReturnRequest::create([
            'order_id' => $this->order->id,
            'order_item_id' => $this->orderItem->id,
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'type' => 'refund_only',
            'reason' => 'wrong_item',
            'requested_amount' => 1500.00,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->seller)->get(route('seller.returns'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seller/Returns')
            ->has('returnRequests.data', 2)
            ->where('statusCounts.all', 2)
            ->where('statusCounts.pending', 1)
            ->where('statusCounts.approved', 1)
            ->where('statusCounts.rejected', 0)
        );

        // Test filtering by status
        $pendingResponse = $this->actingAs($this->seller)->get(route('seller.returns', ['status' => 'pending']));
        $pendingResponse->assertOk();
        $pendingResponse->assertInertia(fn (Assert $page) => $page
            ->component('Seller/Returns')
            ->has('returnRequests.data', 1)
            ->where('returnRequests.data.0.status', 'pending')
        );
    }

    /**
     * SELLER-38: Seller return decisioning (Approve with return instructions and warehouse address).
     */
    public function test_seller_can_approve_return_request_with_instructions(): void
    {
        $returnRequest = OrderReturnRequest::create([
            'order_id' => $this->order->id,
            'order_item_id' => $this->orderItem->id,
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'type' => 'return_and_refund',
            'reason' => 'damaged_in_transit',
            'requested_amount' => 1500.00,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->seller)->post(
            route('seller.returns.decision', $returnRequest->id),
            [
                'decision' => 'approved',
                'return_instructions' => 'Pack in bubble wrap with original box.',
                'return_warehouse_address' => 'Apex Returns Depot, Gate 3, Pasig City',
            ]
        );

        $response->assertSessionHas('status');

        $returnRequest->refresh();
        $this->assertEquals('approved', $returnRequest->status);
        $this->assertEquals('approved', $returnRequest->decision);
        $this->assertEquals('Pack in bubble wrap with original box.', $returnRequest->return_instructions);
        $this->assertEquals('Apex Returns Depot, Gate 3, Pasig City', $returnRequest->return_warehouse_address);
        $this->assertNotNull($returnRequest->seller_responded_at);

        // Verify initial shipping milestone was generated
        $this->assertNotEmpty($returnRequest->return_shipping_history);
        $this->assertEquals('approved', $returnRequest->return_shipping_status);

        // Verify buyer was notified
        $this->assertDatabaseHas('buyer_notifications', [
            'user_id' => $this->buyer->id,
            'title' => 'Return Request Approved',
        ]);
    }

    /**
     * SELLER-38: Seller return decisioning (Reject with required rejection reason and explanation).
     */
    public function test_seller_can_reject_return_request_with_reason(): void
    {
        $returnRequest = OrderReturnRequest::create([
            'order_id' => $this->order->id,
            'order_item_id' => $this->orderItem->id,
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'type' => 'return_and_refund',
            'reason' => 'changed_mind',
            'requested_amount' => 1500.00,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->seller)->post(
            route('seller.returns.decision', $returnRequest->id),
            [
                'decision' => 'rejected',
                'rejection_reason' => 'Item has been heavily used or altered',
                'seller_response' => 'Our store policy does not accept returns for opened personal audio equipment.',
            ]
        );

        $response->assertSessionHas('status');

        $returnRequest->refresh();
        $this->assertEquals('rejected', $returnRequest->status);
        $this->assertEquals('rejected', $returnRequest->decision);
        $this->assertEquals('Item has been heavily used or altered', $returnRequest->rejection_reason);
        $this->assertNotNull($returnRequest->seller_responded_at);
        $this->assertNotNull($returnRequest->resolved_at);

        // Verify buyer notification
        $this->assertDatabaseHas('buyer_notifications', [
            'user_id' => $this->buyer->id,
            'title' => 'Return Request Rejected',
        ]);
    }

    /**
     * SELLER-39: Return parcel tracking (Milestones, waybill tracking, transition to item_returned).
     */
    public function test_return_parcel_tracking_milestones_and_delivery_transition(): void
    {
        $returnRequest = OrderReturnRequest::create([
            'order_id' => $this->order->id,
            'order_item_id' => $this->orderItem->id,
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'type' => 'return_and_refund',
            'reason' => 'defective',
            'requested_amount' => 1500.00,
            'status' => 'approved',
        ]);

        // Step 1: Update tracking to in_transit
        $response1 = $this->actingAs($this->seller)->post(
            route('seller.returns.tracking', $returnRequest->id),
            [
                'return_carrier' => 'J&T Express',
                'return_tracking_number' => 'JNT-RET-9901827',
                'return_shipping_status' => 'in_transit',
                'milestone_location' => 'Makati Sorting Hub',
            ]
        );
        $response1->assertSessionHas('status');

        $returnRequest->refresh();
        $this->assertEquals('J&T Express', $returnRequest->return_carrier);
        $this->assertEquals('JNT-RET-9901827', $returnRequest->return_tracking_number);
        $this->assertEquals('in_transit', $returnRequest->return_shipping_status);
        $this->assertNotNull($returnRequest->buyer_shipped_at);
        $this->assertCount(1, $returnRequest->return_shipping_history);

        // Step 2: Milestone update to delivered (advances status to item_returned for inspection)
        $response2 = $this->actingAs($this->seller)->post(
            route('seller.returns.tracking', $returnRequest->id),
            [
                'return_carrier' => 'J&T Express',
                'return_tracking_number' => 'JNT-RET-9901827',
                'return_shipping_status' => 'delivered',
                'milestone_location' => 'Apex Warehouse Gate 3',
            ]
        );
        $response2->assertSessionHas('status');

        $returnRequest->refresh();
        $this->assertEquals('delivered', $returnRequest->return_shipping_status);
        $this->assertEquals('item_returned', $returnRequest->status);
        $this->assertNotNull($returnRequest->item_received_at);
        $this->assertCount(2, $returnRequest->return_shipping_history);
    }

    /**
     * SELLER-40 & SELLER-41: Return item inspection, inventory restocking, and refund ledger creation.
     */
    public function test_item_inspection_restocks_inventory_and_logs_refund_ledger(): void
    {
        $initialStock = $this->product->stock; // 10

        $returnRequest = OrderReturnRequest::create([
            'order_id' => $this->order->id,
            'order_item_id' => $this->orderItem->id,
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'type' => 'return_and_refund',
            'reason' => 'defective',
            'requested_amount' => 1500.00,
            'status' => 'item_returned',
        ]);

        $response = $this->actingAs($this->seller)->post(
            route('seller.returns.inspect-resolve', $returnRequest->id),
            [
                'inspection_status' => 'passed',
                'inspection_notes' => 'Product in pristine condition, accessories all present.',
                'refund_amount' => 1500.00,
                'refund_method' => 'original_payment',
                'restock_inventory' => true,
            ]
        );

        $response->assertSessionHas('status');

        $returnRequest->refresh();
        $this->assertEquals('refunded', $returnRequest->status);
        $this->assertEquals('passed', $returnRequest->inspection_status);
        $this->assertEquals(1500.00, (float) $returnRequest->refunded_amount);
        $this->assertTrue($returnRequest->restocked);
        $this->assertNotNull($returnRequest->resolved_at);

        // Verify product stock incremented from 10 to 11 (SELLER-40)
        $this->product->refresh();
        $this->assertEquals($initialStock + 1, $this->product->stock);

        // Verify stock adjustment audit was created
        $this->assertDatabaseHas('stock_adjustments', [
            'product_id' => $this->product->id,
            'type' => 'return',
            'quantity' => 1,
            'before_stock' => $initialStock,
            'after_stock' => $initialStock + 1,
        ]);

        // Verify SELLER-41 RefundLedger transaction was recorded
        $this->assertDatabaseHas('refund_ledgers', [
            'order_return_request_id' => $returnRequest->id,
            'seller_id' => $this->seller->id,
            'buyer_id' => $this->buyer->id,
            'refunded_amount' => 1500.00,
            'status' => 'completed',
        ]);

        $ledger = RefundLedger::where('order_return_request_id', $returnRequest->id)->first();
        $this->assertNotNull($ledger);
        $this->assertStringStartsWith('REF-', $ledger->refund_reference);

        // Verify OrderItem status updated to refunded
        $this->orderItem->refresh();
        $this->assertEquals('refunded', $this->orderItem->status);

        // Verify buyer notification
        $this->assertDatabaseHas('buyer_notifications', [
            'user_id' => $this->buyer->id,
            'title' => 'Refund Issued',
        ]);
    }
}
