<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturnRequest;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BuyerReturnRefundTest extends TestCase
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
        Storage::fake('public');

        $this->seller = User::factory()->create([
            'name' => 'Return Test Seller',
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'ReturnStore',
            'return_policy_days' => 7,
        ]);

        $this->buyer = User::factory()->create([
            'name' => 'Return Test Buyer',
            'usertype' => 'buyer',
            'email' => 'buyer.return@example.com',
            'status' => 'approved',
        ]);

        $this->otherBuyer = User::factory()->create([
            'name' => 'Other Buyer',
            'usertype' => 'buyer',
            'email' => 'other.buyer.return@example.com',
            'status' => 'approved',
        ]);

        $this->category = Category::create([
            'name' => 'Accessories',
            'slug' => 'accessories',
        ]);

        $this->product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'name' => 'Wireless Keyboard',
            'slug' => 'wireless-keyboard',
            'price' => 1200.00,
            'stock' => 30,
            'weight' => 0.6,
            'status' => 'approved',
        ]);
    }

    /**
     * Helper to create delivered order and items.
     */
    protected function createDeliveredOrder(array $attributes = [], array $itemAttributes = []): Order
    {
        $order = Order::create(array_merge([
            'user_id' => $this->buyer->id,
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'subtotal' => 1200.00,
            'shipping_fee' => 50.00,
            'total' => 1250.00,
            'shipping_address' => '789 Quezon Ave, Quezon City',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'status' => 'delivered',
            'delivered_at' => now()->subDay(),
        ], $attributes));

        OrderItem::create(array_merge([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'price' => 1200.00,
            'quantity' => 1,
            'status' => $order->status,
            'delivered_at' => $order->delivered_at,
        ], $itemAttributes));

        return $order->fresh(['items.product.seller', 'items.returnRequests']);
    }

    /**
     * BUY-RET-001: Request return within window
     * (Select item, reason, description, upload photos -> submitted and moved to Return tab).
     */
    public function test_buy_ret_001_request_return_within_window(): void
    {
        $order = $this->createDeliveredOrder();
        $item = $order->items->first();

        // 1. Check item is eligible to return in show page
        $showResponse = $this->actingAs($this->buyer)->get(route('buyer.orders.show', $order->id));
        $showResponse->assertOk();
        $showResponse->assertInertia(fn (Assert $page) => $page
            ->where('order.items.0.can_return', true)
        );

        // 2. Submit return request with valid reason, description, and photo
        $file = UploadedFile::fake()->image('evidence.jpg');
        $response = $this->actingAs($this->buyer)->post(route('buyer.order-items.return', $item->id), [
            'type' => 'return_and_refund',
            'reason' => 'damaged_defective',
            'reason_details' => 'The spacebar key arrived completely detached and broken.',
            'evidence_images' => [$file],
        ]);

        $response->assertSessionHas('status');

        $this->assertDatabaseHas('order_return_requests', [
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'status' => 'pending',
            'reason' => 'damaged_defective',
        ]);

        // 3. Order is now present in the returns filter tab
        $returnsTabResponse = $this->actingAs($this->buyer)->get(route('buyer.orders', ['status' => 'returns']));
        $returnsTabResponse->assertOk();
        $returnsTabResponse->assertInertia(fn (Assert $page) => $page
            ->where('current_status', 'returns')
            ->where('counts.returns', 1)
            ->has('orders.data', 1)
            ->where('orders.data.0.id', $order->id)
        );
    }

    /**
     * BUY-RET-002: Return not allowed before delivery
     * (Return button hidden while order is not yet delivered).
     */
    public function test_buy_ret_002_return_not_allowed_before_delivery(): void
    {
        // Unshipped order (shipped / in transit)
        $order = Order::create([
            'user_id' => $this->buyer->id,
            'order_number' => 'ORD-TRANSIT-' . uniqid(),
            'subtotal' => 1200.00,
            'shipping_fee' => 50.00,
            'total' => 1250.00,
            'shipping_address' => '789 Quezon Ave, Quezon City',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'status' => 'shipped',
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'price' => 1200.00,
            'quantity' => 1,
            'status' => 'shipped',
        ]);

        // Verify can_return is false in show page
        $showResponse = $this->actingAs($this->buyer)->get(route('buyer.orders.show', $order->id));
        $showResponse->assertOk();
        $showResponse->assertInertia(fn (Assert $page) => $page
            ->where('order.items.0.can_return', false)
        );

        // Attempt direct return request before delivery
        $file = UploadedFile::fake()->image('evidence.jpg');
        $response = $this->actingAs($this->buyer)->post(route('buyer.order-items.return', $item->id), [
            'type' => 'return_and_refund',
            'reason' => 'damaged_defective',
            'reason_details' => 'Item arrived broken before delivery.',
            'evidence_images' => [$file],
        ]);

        $response->assertSessionHasErrors('reason');
        $this->assertDatabaseMissing('order_return_requests', ['order_item_id' => $item->id]);
    }

    /**
     * BUY-RET-003: Return window boundary
     * (Allowed on day 7; blocked after 7 days).
     */
    public function test_buy_ret_003_return_window_boundary_day7_vs_after7(): void
    {
        // Day 7: Delivered 6 days ago (still within 7-day policy window)
        $day7Order = $this->createDeliveredOrder([
            'delivered_at' => Carbon::now()->subDays(6),
        ], [
            'delivered_at' => Carbon::now()->subDays(6),
        ]);
        $day7Item = $day7Order->items->first();

        $day7Show = $this->actingAs($this->buyer)->get(route('buyer.orders.show', $day7Order->id));
        $day7Show->assertOk();
        $day7Show->assertInertia(fn (Assert $page) => $page
            ->where('order.items.0.can_return', true)
        );

        // Day 7 return request SUCCEEDS
        $file1 = UploadedFile::fake()->image('day7.jpg');
        $day7Response = $this->actingAs($this->buyer)->post(route('buyer.order-items.return', $day7Item->id), [
            'type' => 'return_and_refund',
            'reason' => 'wrong_item',
            'reason_details' => 'Received wrong version of the wireless keyboard.',
            'evidence_images' => [$file1],
        ]);
        $day7Response->assertSessionHas('status');
        $this->assertDatabaseHas('order_return_requests', ['order_item_id' => $day7Item->id]);

        // After Day 7: Delivered 8 days ago (return window expired)
        $expiredOrder = $this->createDeliveredOrder([
            'delivered_at' => Carbon::now()->subDays(8),
        ], [
            'delivered_at' => Carbon::now()->subDays(8),
        ]);
        $expiredItem = $expiredOrder->items->first();

        // can_return must be false
        $expiredShow = $this->actingAs($this->buyer)->get(route('buyer.orders.show', $expiredOrder->id));
        $expiredShow->assertOk();
        $expiredShow->assertInertia(fn (Assert $page) => $page
            ->where('order.items.0.can_return', false)
        );

        // Direct return submission after 7 days is BLOCKED with validation error
        $file2 = UploadedFile::fake()->image('expired.jpg');
        $expiredResponse = $this->actingAs($this->buyer)->post(route('buyer.order-items.return', $expiredItem->id), [
            'type' => 'return_and_refund',
            'reason' => 'wrong_item',
            'reason_details' => 'Expired return request attempt after 8 days.',
            'evidence_images' => [$file2],
        ]);
        $expiredResponse->assertSessionHasErrors('reason');
        $this->assertDatabaseMissing('order_return_requests', ['order_item_id' => $expiredItem->id]);
    }

    /**
     * BUY-RET-004: Evidence and description rules
     * (1-5 photos and 10+ character explanation required).
     */
    public function test_buy_ret_004_evidence_and_description_rules(): void
    {
        $order = $this->createDeliveredOrder();
        $item = $order->items->first();

        // 1. Missing evidence photos (0 photos)
        $noPhotoResponse = $this->actingAs($this->buyer)->post(route('buyer.order-items.return', $item->id), [
            'type' => 'return_and_refund',
            'reason' => 'damaged_defective',
            'reason_details' => 'This is a sufficiently long explanation of the issue.',
            'evidence_images' => [],
        ]);
        $noPhotoResponse->assertSessionHasErrors('evidence_images');

        // 2. Short explanation (< 10 characters)
        $file = UploadedFile::fake()->image('evidence.jpg');
        $shortDescResponse = $this->actingAs($this->buyer)->post(route('buyer.order-items.return', $item->id), [
            'type' => 'return_and_refund',
            'reason' => 'damaged_defective',
            'reason_details' => 'Broken', // only 6 chars
            'evidence_images' => [$file],
        ]);
        $shortDescResponse->assertSessionHasErrors('reason_details');

        // 3. More than 5 photos (6 photos)
        $sixPhotos = [
            UploadedFile::fake()->image('p1.jpg'),
            UploadedFile::fake()->image('p2.jpg'),
            UploadedFile::fake()->image('p3.jpg'),
            UploadedFile::fake()->image('p4.jpg'),
            UploadedFile::fake()->image('p5.jpg'),
            UploadedFile::fake()->image('p6.jpg'),
        ];
        $tooManyPhotosResponse = $this->actingAs($this->buyer)->post(route('buyer.order-items.return', $item->id), [
            'type' => 'return_and_refund',
            'reason' => 'damaged_defective',
            'reason_details' => 'The item is damaged and doesn not turn on at all.',
            'evidence_images' => $sixPhotos,
        ]);
        $tooManyPhotosResponse->assertSessionHasErrors('evidence_images');

        // 4. Valid explanation (>= 10 chars) and valid photos (1-5) -> succeeds
        $validFile = UploadedFile::fake()->image('valid.jpg');
        $validResponse = $this->actingAs($this->buyer)->post(route('buyer.order-items.return', $item->id), [
            'type' => 'return_and_refund',
            'reason' => 'damaged_defective',
            'reason_details' => 'The spacebar key arrived completely detached and broken.',
            'evidence_images' => [$validFile],
        ]);
        $validResponse->assertSessionHas('status');
        $this->assertDatabaseHas('order_return_requests', ['order_item_id' => $item->id]);
    }

    /**
     * BUY-RET-005: Track request status
     * (Shows seller rejection reason with escalate button; escalation updates status to in_dispute).
     */
    public function test_buy_ret_005_track_request_status_and_escalate(): void
    {
        $order = $this->createDeliveredOrder();
        $item = $order->items->first();

        // Create a rejected return request
        $returnRequest = OrderReturnRequest::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'type' => 'return_and_refund',
            'reason' => 'Damaged or defective',
            'reason_details' => 'Keycap is broken upon unboxing.',
            'requested_amount' => 1200.00,
            'status' => 'rejected',
            'rejection_reason' => 'Item shows signs of improper handling by customer.',
            'seller_responded_at' => now()->subDay(),
            'response_deadline' => now()->addDays(2),
        ]);

        // Buyer opens order details
        $showResponse = $this->actingAs($this->buyer)->get(route('buyer.orders.show', $order->id));
        $showResponse->assertOk();
        $showResponse->assertInertia(fn (Assert $page) => $page
            ->where('order.items.0.latest_return_request.status', 'rejected')
            ->where('order.items.0.latest_return_request.rejection_reason', 'Item shows signs of improper handling by customer.')
            ->where('order.items.0.latest_return_request.can_escalate', true)
        );

        // Buyer clicks Escalate to Dispute
        $escalateResponse = $this->actingAs($this->buyer)->post(route('buyer.return-requests.escalate', $returnRequest->id), [
            'reason' => 'The item arrived damaged in the sealed courier pouch before opening.',
        ]);

        $escalateResponse->assertSessionHas('status');

        $returnRequest->refresh();
        $this->assertEquals('in_dispute', $returnRequest->status);
        $this->assertNotNull($returnRequest->escalated_at);

        // Second escalation attempt is blocked
        $secondEscalate = $this->actingAs($this->buyer)->post(route('buyer.return-requests.escalate', $returnRequest->id));
        $secondEscalate->assertSessionHasErrors('status');
    }
}

