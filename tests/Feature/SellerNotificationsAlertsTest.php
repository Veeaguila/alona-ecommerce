<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturnRequest;
use App\Models\Product;
use App\Models\Review;
use App\Models\SellerNotification;
use App\Models\SellerPayoutRequest;
use App\Models\SellerReview;
use App\Models\User;
use App\Services\SellerNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerNotificationsAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected User $seller;
    protected User $buyer;
    protected User $admin;
    protected Category $category;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'TechZone Official',
        ]);

        $this->buyer = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
            'name' => 'Maria Santos',
        ]);

        $this->admin = User::factory()->create([
            'usertype' => 'admin',
            'status' => 'approved',
            'name' => 'Admin Officer',
        ]);

        $this->category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
        ]);

        $this->product = Product::create([
            'category_id' => $this->category->id,
            'seller_id' => $this->seller->id,
            'name' => 'Mechanical Gaming Keyboard',
            'slug' => 'mechanical-gaming-keyboard',
            'price' => 2500.00,
            'stock' => 15,
            'low_stock_threshold' => 5,
            'status' => 'approved',
        ]);
    }

    /**
     * SELLER-58: Low-stock threshold alerts.
     */
    public function test_low_stock_alert_triggers_when_product_stock_reaches_threshold(): void
    {
        // Stock > threshold: no notification
        $this->product->update(['stock' => 6]);
        $notification = SellerNotificationService::sendLowStockAlert($this->product);
        $this->assertNull($notification);
        $this->assertDatabaseMissing('seller_notifications', [
            'user_id' => $this->seller->id,
            'type' => 'low_stock',
        ]);

        // Stock <= threshold: alert is sent
        $this->product->update(['stock' => 4]);
        $notification = SellerNotificationService::sendLowStockAlert($this->product);
        $this->assertNotNull($notification);
        $this->assertDatabaseHas('seller_notifications', [
            'user_id' => $this->seller->id,
            'type' => 'low_stock',
        ]);

        $this->assertStringContainsString('Mechanical Gaming Keyboard', $notification->title);
        $this->assertStringContainsString('LOW STOCK', $notification->title);
        $this->assertStringContainsString('only 4 item(s) remaining', $notification->message);

        // Deduplication: sending another alert within 12h returns null and doesn't duplicate
        $duplicate = SellerNotificationService::sendLowStockAlert($this->product);
        $this->assertNull($duplicate);
        $this->assertEquals(1, SellerNotification::where('user_id', $this->seller->id)->where('type', 'low_stock')->count());
    }

    /**
     * SELLER-58: Out of stock alert when stock is 0.
     */
    public function test_out_of_stock_alert_triggers_when_stock_is_zero(): void
    {
        $this->product->update(['stock' => 0]);
        $notification = SellerNotificationService::sendLowStockAlert($this->product);

        $this->assertNotNull($notification);
        $this->assertStringContainsString('OUT OF STOCK', $notification->title);
        $this->assertStringContainsString('completely out of stock', $notification->message);
    }

    /**
     * SELLER-58: Artisan command checks all catalog stock and notifies sellers.
     */
    public function test_artisan_command_scans_low_stock_and_notifies_seller(): void
    {
        $this->product->update(['stock' => 2]);

        $this->artisan('stock:check-low-stock')
            ->assertSuccessful();

        $this->assertDatabaseHas('seller_notifications', [
            'user_id' => $this->seller->id,
            'type' => 'low_stock',
        ]);
    }

    /**
     * SELLER-59: New product review alerts.
     */
    public function test_seller_receives_alert_when_buyer_submits_product_review(): void
    {
        $review = Review::create([
            'product_id' => $this->product->id,
            'user_id' => $this->buyer->id,
            'rating' => 5,
            'comment' => 'Exceptional build quality and fast switches!',
        ]);

        $notification = SellerNotificationService::sendProductReviewAlert($review);

        $this->assertNotNull($notification);
        $this->assertDatabaseHas('seller_notifications', [
            'user_id' => $this->seller->id,
            'type' => 'review',
        ]);

        $this->assertStringContainsString('Maria Santos', $notification->message);
        $this->assertStringContainsString('5-star review', $notification->message);
        $this->assertStringContainsString('Exceptional build quality', $notification->message);
    }

    /**
     * SELLER-59: New store review alerts.
     */
    public function test_seller_receives_alert_when_buyer_submits_store_review(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-2026-STORE-1',
            'user_id' => $this->buyer->id,
            'status' => 'completed',
            'total' => 2500.00,
            'shipping_address' => '123 Buyer St, Manila',
            'payment_status' => 'paid',
            'payment_method' => 'gcash',
        ]);

        $sellerReview = SellerReview::create([
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'user_id' => $this->buyer->id,
            'rating' => 5,
            'comment' => 'Very responsive seller and properly packed items.',
        ]);

        $notification = SellerNotificationService::sendStoreReviewAlert($sellerReview);

        $this->assertNotNull($notification);
        $this->assertDatabaseHas('seller_notifications', [
            'user_id' => $this->seller->id,
            'type' => 'review',
            'title' => 'New Store Rating',
        ]);

        $this->assertStringContainsString('Maria Santos', $notification->message);
        $this->assertStringContainsString('rated your store 5 stars', $notification->message);
    }

    /**
     * SELLER-60: Return and refund request alerts.
     */
    public function test_seller_receives_alert_when_buyer_submits_return_or_refund_request(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-2026-TEST-99',
            'user_id' => $this->buyer->id,
            'status' => 'delivered',
            'total' => 2500.00,
            'shipping_address' => '123 Buyer St, Manila',
            'payment_status' => 'paid',
            'payment_method' => 'gcash',
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'seller_id' => $this->seller->id,
            'quantity' => 1,
            'price' => 2500.00,
            'product_name' => $this->product->name,
        ]);

        $returnRequest = OrderReturnRequest::create([
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'type' => 'return_and_refund',
            'reason' => 'Item arrived with loose keycaps',
            'status' => 'pending',
            'refund_amount' => 2500.00,
        ]);

        $notification = SellerNotificationService::sendReturnRequestAlert($returnRequest);

        $this->assertNotNull($notification);
        $this->assertDatabaseHas('seller_notifications', [
            'user_id' => $this->seller->id,
            'type' => 'return_request',
        ]);

        $this->assertStringContainsString('New Return & Refund Request', $notification->title);
        $this->assertStringContainsString('Maria Santos', $notification->message);
        $this->assertStringContainsString('Mechanical Gaming Keyboard', $notification->message);
        $this->assertStringContainsString('Item arrived with loose keycaps', $notification->message);
    }

    /**
     * SELLER-61: Payout status change alerts (Approved and Disbursed).
     */
    public function test_seller_receives_alert_when_admin_approves_payout_request(): void
    {
        $payout = SellerPayoutRequest::create([
            'payout_reference' => 'PAY-20261016-ABC123',
            'seller_id' => $this->seller->id,
            'amount' => 5000.00,
            'payout_method' => 'gcash',
            'account_name' => 'Maria Santos',
            'account_number' => '09171234567',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.payout-requests.status', $payout), [
                'status' => 'approved',
                'admin_notes' => 'Payout transferred via GCash Disbursement API',
            ]);

        $response->assertRedirect();

        $payout->refresh();
        $this->assertEquals('approved', $payout->status);
        $this->assertEquals($this->admin->id, $payout->processed_by);

        $this->assertDatabaseHas('seller_notifications', [
            'user_id' => $this->seller->id,
            'type' => 'payout',
            'title' => 'Payout Approved & Disbursed',
        ]);

        $notification = SellerNotification::where('user_id', $this->seller->id)->where('type', 'payout')->first();
        $this->assertStringContainsString('PAY-20261016-ABC123', $notification->message);
        $this->assertStringContainsString('₱5,000.00', $notification->message);
    }

    /**
     * SELLER-61: Payout status change alerts (Rejected with reason).
     */
    public function test_seller_receives_alert_when_admin_rejects_payout_request(): void
    {
        $payout = SellerPayoutRequest::create([
            'payout_reference' => 'PAY-20261016-XYZ987',
            'seller_id' => $this->seller->id,
            'amount' => 12000.00,
            'payout_method' => 'bank_transfer',
            'account_name' => 'Maria Santos',
            'account_number' => '1234567890',
            'bank_name' => 'BDO',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.payout-requests.status', $payout), [
                'status' => 'rejected',
                'rejection_reason' => 'Bank account name does not match seller identity document.',
            ]);

        $response->assertRedirect();

        $payout->refresh();
        $this->assertEquals('rejected', $payout->status);

        $this->assertDatabaseHas('seller_notifications', [
            'user_id' => $this->seller->id,
            'type' => 'payout',
            'title' => 'Payout Request Declined',
        ]);

        $notification = SellerNotification::where('user_id', $this->seller->id)->where('type', 'payout')->first();
        $this->assertStringContainsString('Reason: Bank account name does not match', $notification->message);
    }

    /**
     * Seller can view notification center and mark notifications as read.
     */
    public function test_seller_can_view_and_manage_notifications(): void
    {
        $n1 = SellerNotification::create([
            'user_id' => $this->seller->id,
            'type' => 'low_stock',
            'title' => 'Low Stock Warning',
            'message' => 'Product stock is low',
            'url' => '/seller/stock-monitoring',
        ]);

        $n2 = SellerNotification::create([
            'user_id' => $this->seller->id,
            'type' => 'review',
            'title' => 'New Review',
            'message' => '5 star review',
            'url' => '/seller/reviews',
        ]);

        $response = $this->actingAs($this->seller)
            ->get(route('seller.notifications'));

        $response->assertOk();

        // Mark single as read
        $this->actingAs($this->seller)
            ->patch(route('seller.notifications.read', $n1->id))
            ->assertRedirect();

        $this->assertNotNull($n1->fresh()->read_at);
        $this->assertNull($n2->fresh()->read_at);

        // Mark all as read
        $this->actingAs($this->seller)
            ->post(route('seller.notifications.read-all'))
            ->assertRedirect();

        $this->assertNotNull($n2->fresh()->read_at);
    }
}
