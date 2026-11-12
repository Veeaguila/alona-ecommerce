<?php

namespace Tests\Feature;

use App\Models\BuyerNotification;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturnRequest;
use App\Models\Product;
use App\Models\SellerNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerReturnsAndRefundsTest extends TestCase
{
    use RefreshDatabase;

    private User $seller1;
    private User $seller2;
    private User $buyer1;
    private Product $productP02;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller1 = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Store 1',
            'street_address' => '123 Market St',
            'barangay' => 'San Antonio',
            'municipality' => 'Pasig',
            'province' => 'Metro Manila',
        ]);

        $this->seller2 = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Store 2',
        ]);

        $this->buyer1 = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
            'name' => 'Buyer One',
            'email' => 'buyer1@example.com',
            'email_verified_at' => now(),
        ]);

        $category = Category::create([
            'name' => 'Handicrafts',
            'slug' => 'handicrafts',
        ]);

        $this->productP02 = Product::create([
            'seller_id' => $this->seller1->id,
            'category_id' => $category->id,
            'name' => 'Woven Basket P-02',
            'sku' => 'P-02',
            'price' => 450.00,
            'stock' => 20,
            'status' => 'approved',
        ]);
    }

    private function createOrderWithItem(float $price = 450.00, int $quantity = 1, string $status = 'delivered'): array
    {
        $order = Order::create([
            'user_id' => $this->buyer1->id,
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'status' => $status,
            'payment_status' => 'paid',
            'payment_method' => 'gcash',
            'shipping_method' => 'standard',
            'shipping_fee' => 0.00,
            'subtotal' => $price * $quantity,
            'total' => $price * $quantity,
            'shipping_address' => '456 Elm St, Pasig City',
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->productP02->id,
            'product_name' => $this->productP02->name,
            'price' => $price,
            'quantity' => $quantity,
            'status' => $status,
        ]);

        return [$order, $orderItem];
    }

    /**
     * SEL-RET-001: View a return request with evidence.
     * Shows reason, description, both photos, item, amount, and response deadline (3 days).
     */
    public function test_sel_ret_001_view_return_request_with_evidence(): void
    {
        [$order, $orderItem] = $this->createOrderWithItem(450.00, 1, 'delivered');

        $returnRequest = OrderReturnRequest::create([
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'buyer_id' => $this->buyer1->id,
            'seller_id' => $this->seller1->id,
            'type' => 'return_and_refund',
            'reason' => 'Damaged or defective',
            'reason_details' => 'The woven handle was completely torn upon delivery.',
            'requested_amount' => 450.00,
            'evidence_images' => ['returns/photo1.jpg', 'returns/photo2.jpg'],
            'status' => 'pending',
            'response_deadline' => now()->addDays(3),
        ]);

        $response = $this->actingAs($this->seller1)
            ->get(route('seller.returns'));

        $response->assertOk();
        $response->assertInertia(function ($page) use ($returnRequest) {
            $page->component('Seller/Returns');
            $data = $page->toArray()['props']['returnRequests']['data'];
            $found = collect($data)->firstWhere('id', $returnRequest->id);
            $this->assertNotNull($found);
            $this->assertSame('Damaged or defective', $found['reason']);
            $this->assertSame('The woven handle was completely torn upon delivery.', $found['reason_details']);
            $this->assertCount(2, $found['evidence_images']);
            $this->assertSame('returns/photo1.jpg', $found['evidence_images'][0]);
            $this->assertSame('returns/photo2.jpg', $found['evidence_images'][1]);
            $this->assertSame('Woven Basket P-02', $found['order_item']['product_name']);
            $this->assertEquals(450.00, (float) $found['requested_amount']);
            $this->assertStringContainsString('3 days', $found['response_deadline_label']);
        });

        // Direct show endpoint
        $showResponse = $this->actingAs($this->seller1)
            ->getJson(route('seller.returns.show', $returnRequest->id));

        $showResponse->assertOk()
            ->assertJsonPath('reason', 'Damaged or defective')
            ->assertJsonPath('reason_details', 'The woven handle was completely torn upon delivery.')
            ->assertJsonPath('requested_amount', '450.00')
            ->assertJsonCount(2, 'evidence_images');
    }

    /**
     * SEL-RET-002: Approve a return request.
     * Status Return Approved on both sides. Buyer 1 is notified and sees return instructions.
     * The amount moves out of Seller 1's pending earnings.
     */
    public function test_sel_ret_002_approve_return_request(): void
    {
        [$order, $orderItem] = $this->createOrderWithItem(450.00, 1, 'processing');

        $returnRequest = OrderReturnRequest::create([
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'buyer_id' => $this->buyer1->id,
            'seller_id' => $this->seller1->id,
            'type' => 'return_and_refund',
            'reason' => 'Damaged or defective',
            'reason_details' => 'Damaged basket handle.',
            'requested_amount' => 450.00,
            'status' => 'pending',
            'response_deadline' => now()->addDays(3),
        ]);

        // Prior to approval, verify ₱450.00 is in pending earnings
        $reportBefore = $this->actingAs($this->seller1)->get(route('seller.reports'));
        $pendingBefore = $reportBefore->viewData('page')['props']['summary']['pending_payments'];
        $this->assertEquals(450.00, $pendingBefore);

        // Seller 1 clicks Approve
        $approveResponse = $this->actingAs($this->seller1)
            ->post(route('seller.returns.decision', $returnRequest->id), [
                'decision' => 'approved',
                'return_instructions' => 'Pack securely in original box and drop off at J&T Express branch.',
                'return_warehouse_address' => '123 Market St, San Antonio, Pasig',
            ]);

        $approveResponse->assertRedirect();
        $returnRequest->refresh();

        // 1. Status Return Approved on both sides
        $this->assertSame('approved', $returnRequest->status);
        $this->assertSame('Return Approved', $returnRequest->status_label);

        // Buyer opens order details
        $buyerResponse = $this->actingAs($this->buyer1)
            ->get(route('buyer.orders.show', $order->id));

        $buyerResponse->assertOk();
        $buyerResponse->assertInertia(function ($page) {
            $items = $page->toArray()['props']['order']['items'];
            $this->assertNotEmpty($items);
            $req = $items[0]['latest_return_request'];
            $this->assertSame('approved', $req['status']);
            $this->assertStringContainsString('Pack securely', $req['return_instructions']);
            $this->assertStringContainsString('123 Market St', $req['return_warehouse_address']);
        });

        // 2. Buyer 1 is notified
        $this->assertTrue(
            BuyerNotification::where('user_id', $this->buyer1->id)
                ->where('title', 'Return Request Approved')
                ->exists()
        );

        // 3. Amount moves out of Seller 1's pending earnings
        $reportAfter = $this->actingAs($this->seller1)->get(route('seller.reports'));
        $pendingAfter = $reportAfter->viewData('page')['props']['summary']['pending_payments'];
        $this->assertEquals(0.00, $pendingAfter);
    }

    /**
     * SEL-RET-003: Reject without a reason.
     * Click Reject and submit with an empty reason, then with 9 characters ('Item used').
     * Both are blocked; the reason needs 10-300 characters. The request stays pending.
     */
    public function test_sel_ret_003_reject_without_a_reason_blocks_and_stays_pending(): void
    {
        [$order, $orderItem] = $this->createOrderWithItem(450.00, 1, 'delivered');

        $returnRequest = OrderReturnRequest::create([
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'buyer_id' => $this->buyer1->id,
            'seller_id' => $this->seller1->id,
            'type' => 'return_and_refund',
            'reason' => 'Damaged or defective',
            'requested_amount' => 450.00,
            'status' => 'pending',
            'response_deadline' => now()->addDays(3),
        ]);

        // Attempt 1: Empty reason
        $responseEmpty = $this->actingAs($this->seller1)
            ->post(route('seller.returns.decision', $returnRequest->id), [
                'decision' => 'rejected',
                'rejection_reason' => '',
            ]);

        $responseEmpty->assertSessionHasErrors(['rejection_reason']);
        $returnRequest->refresh();
        $this->assertSame('pending', $returnRequest->status);

        // Attempt 2: 9 characters ('Item used')
        $this->assertSame(9, strlen('Item used'));
        $responseShort = $this->actingAs($this->seller1)
            ->post(route('seller.returns.decision', $returnRequest->id), [
                'decision' => 'rejected',
                'rejection_reason' => 'Item used',
            ]);

        $responseShort->assertSessionHasErrors(['rejection_reason']);
        $returnRequest->refresh();
        $this->assertSame('pending', $returnRequest->status);

        // Attempt 3: 301 characters (exceeds 300)
        $responseLong = $this->actingAs($this->seller1)
            ->post(route('seller.returns.decision', $returnRequest->id), [
                'decision' => 'rejected',
                'rejection_reason' => str_repeat('a', 301),
            ]);

        $responseLong->assertSessionHasErrors(['rejection_reason']);
        $returnRequest->refresh();
        $this->assertSame('pending', $returnRequest->status);
    }

    /**
     * SEL-RET-004: Reject with a reason.
     * Reject with: "Item shows signs of use and the tag is removed."
     * Status Rejected on both sides; Buyer 1 sees the exact reason and an Escalate button.
     */
    public function test_sel_ret_004_reject_with_a_reason(): void
    {
        [$order, $orderItem] = $this->createOrderWithItem(450.00, 1, 'delivered');

        $returnRequest = OrderReturnRequest::create([
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'buyer_id' => $this->buyer1->id,
            'seller_id' => $this->seller1->id,
            'type' => 'return_and_refund',
            'reason' => 'Damaged or defective',
            'requested_amount' => 450.00,
            'status' => 'pending',
            'response_deadline' => now()->addDays(3),
        ]);

        $reason = 'Item shows signs of use and the tag is removed.';

        $rejectResponse = $this->actingAs($this->seller1)
            ->post(route('seller.returns.decision', $returnRequest->id), [
                'decision' => 'rejected',
                'rejection_reason' => $reason,
            ]);

        $rejectResponse->assertRedirect();
        $returnRequest->refresh();

        // Status Rejected on both sides
        $this->assertSame('rejected', $returnRequest->status);
        $this->assertSame($reason, $returnRequest->rejection_reason);
        $this->assertSame($reason, $returnRequest->seller_response);
        $this->assertTrue($returnRequest->can_escalate);

        // Buyer 1 opens order details
        $buyerResponse = $this->actingAs($this->buyer1)
            ->get(route('buyer.orders.show', $order->id));

        $buyerResponse->assertOk();
        $buyerResponse->assertInertia(function ($page) use ($reason) {
            $items = $page->toArray()['props']['order']['items'];
            $req = $items[0]['latest_return_request'];
            $this->assertSame('rejected', $req['status']);
            $this->assertSame($reason, $req['rejection_reason']);
            $this->assertTrue($req['can_escalate']);
        });
    }

    /**
     * SEL-RET-005: Escalate to a dispute.
     * Request rejected less than 3 days ago.
     * 1. Buyer 1 clicks Escalate.
     * 2. Seller 1 opens the request.
     * 3. Buyer 1 tries to escalate again.
     * Expected Result: Status In Dispute on both sides. Seller 1 can no longer approve or reject.
     * A second escalation is not possible.
     */
    public function test_sel_ret_005_escalate_to_a_dispute(): void
    {
        [$order, $orderItem] = $this->createOrderWithItem(450.00, 1, 'delivered');

        $returnRequest = OrderReturnRequest::create([
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'buyer_id' => $this->buyer1->id,
            'seller_id' => $this->seller1->id,
            'type' => 'return_and_refund',
            'reason' => 'Damaged or defective',
            'requested_amount' => 450.00,
            'status' => 'rejected',
            'rejection_reason' => 'Item shows signs of use and the tag is removed.',
            'seller_responded_at' => now()->subDay(),
            'response_deadline' => now()->addDays(2),
        ]);

        // Step 1: Buyer 1 clicks Escalate
        $escalateResponse = $this->actingAs($this->buyer1)
            ->post(route('buyer.return-requests.escalate', $returnRequest->id), [
                'reason' => 'The item arrived damaged in shipment before I ever opened it.',
            ]);

        $escalateResponse->assertRedirect();
        $returnRequest->refresh();

        // Status In Dispute on both sides
        $this->assertSame('in_dispute', $returnRequest->status);
        $this->assertSame('In Dispute', $returnRequest->status_label);
        $this->assertNotNull($returnRequest->escalated_at);

        // Step 2: Seller 1 opens request
        $sellerResponse = $this->actingAs($this->seller1)
            ->get(route('seller.returns'));

        $sellerResponse->assertOk();
        $sellerResponse->assertInertia(function ($page) use ($returnRequest) {
            $data = $page->toArray()['props']['returnRequests']['data'];
            $found = collect($data)->firstWhere('id', $returnRequest->id);
            $this->assertNotNull($found);
            $this->assertSame('in_dispute', $found['status']);
        });

        // Seller 1 cannot approve or reject
        $attemptApprove = $this->actingAs($this->seller1)
            ->post(route('seller.returns.decision', $returnRequest->id), [
                'decision' => 'approved',
            ]);

        $attemptApprove->assertSessionHasErrors(['status']);
        $returnRequest->refresh();
        $this->assertSame('in_dispute', $returnRequest->status);

        // Step 3: Buyer 1 tries to escalate again (second escalation blocked)
        $secondEscalate = $this->actingAs($this->buyer1)
            ->post(route('buyer.return-requests.escalate', $returnRequest->id));

        $secondEscalate->assertSessionHasErrors(['status']);
        $this->assertFalse($returnRequest->can_escalate);
    }

    /**
     * SEL-RET-006: No response within 3 days.
     * Pending before 3 days; Auto-approved after, and Buyer 1 is notified.
     */
    public function test_sel_ret_006_no_response_within_3_days_auto_approved(): void
    {
        [$order, $orderItem] = $this->createOrderWithItem(450.00, 1, 'delivered');

        // Created 2 days and 23 hours ago (just BEFORE 3 days)
        $returnRequest = OrderReturnRequest::create([
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'buyer_id' => $this->buyer1->id,
            'seller_id' => $this->seller1->id,
            'type' => 'return_and_refund',
            'reason' => 'Damaged or defective',
            'requested_amount' => 450.00,
            'status' => 'pending',
            'created_at' => now()->subDays(3)->addMinutes(10),
            'response_deadline' => now()->addMinutes(10),
        ]);

        // Check before 3 days: stays pending
        $this->actingAs($this->seller1)->get(route('seller.returns'));
        $returnRequest->refresh();
        $this->assertSame('pending', $returnRequest->status);

        // Fast-forward past 3 days (e.g. created 3 days and 1 hour ago)
        $returnRequest->forceFill([
            'created_at' => now()->subDays(3)->subHour(),
            'response_deadline' => now()->subHour(),
        ])->save();

        // Check after 3 days: auto-approved
        $this->actingAs($this->seller1)->get(route('seller.returns'));
        $returnRequest->refresh();

        $this->assertSame('approved', $returnRequest->status);
        $this->assertSame('approved', $returnRequest->decision);
        $this->assertNotNull($returnRequest->auto_approved_at);
        $this->assertStringContainsString('Auto-approved', $returnRequest->seller_response);

        // Buyer 1 is notified
        $this->assertTrue(
            BuyerNotification::where('user_id', $this->buyer1->id)
                ->where('title', 'Return Request Auto-Approved')
                ->exists()
        );
    }

    /**
     * SEL-RET-007: Refunded amount removed from earnings.
     * A return for P-02 (₱450.00) was approved and refunded.
     * Open Sales Reports and Earnings.
     * ₱450.00 and its commission are excluded from gross sales and earnings.
     */
    public function test_sel_ret_007_refunded_amount_removed_from_earnings(): void
    {
        [$order, $orderItem] = $this->createOrderWithItem(450.00, 1, 'delivered');

        $returnRequest = OrderReturnRequest::create([
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'buyer_id' => $this->buyer1->id,
            'seller_id' => $this->seller1->id,
            'type' => 'return_and_refund',
            'reason' => 'Damaged or defective',
            'requested_amount' => 450.00,
            'status' => 'approved',
            'response_deadline' => now()->addDays(3),
        ]);

        // Seller inspects and issues refund of ₱450.00
        $refundResponse = $this->actingAs($this->seller1)
            ->post(route('seller.returns.inspect-resolve', $returnRequest->id), [
                'inspection_status' => 'passed',
                'inspection_notes' => 'Passed inspection. Item returned in good order.',
                'refund_amount' => 450.00,
                'refund_method' => 'original_payment',
                'restock_inventory' => true,
            ]);

        $refundResponse->assertRedirect();
        $returnRequest->refresh();
        $orderItem->refresh();

        $this->assertSame('refunded', $returnRequest->status);
        $this->assertSame('refunded', $orderItem->status);
        $this->assertEquals(450.00, (float) $returnRequest->refunded_amount);

        // Open Sales Reports
        $reportResponse = $this->actingAs($this->seller1)
            ->get(route('seller.reports'));

        $reportResponse->assertOk();
        $reportResponse->assertInertia(function ($page) {
            $summary = $page->toArray()['props']['summary'];
            // ₱450.00 and its 5% commission (₱22.50) are excluded
            $this->assertEquals(0.00, (float) $summary['gross_revenue']);
            $this->assertEquals(0.00, (float) $summary['platform_fees']);
            $this->assertEquals(0.00, (float) $summary['net_earnings']);
            $this->assertEquals(0.00, (float) $summary['pending_payments']);
            $this->assertEquals(0, $summary['orders']);
            $this->assertEquals(0, $summary['units_sold']);
        });

        // Open Dashboard
        $dashboardResponse = $this->actingAs($this->seller1)
            ->get(route('seller.dashboard'));

        $dashboardResponse->assertOk();
        $dashboardResponse->assertInertia(function ($page) {
            $stats = $page->toArray()['props']['stats'];
            $this->assertEquals(0.00, (float) $stats['total_sales']);
            $this->assertEquals(0.00, (float) $stats['estimated_platform_fees']);
            $this->assertEquals(0.00, (float) $stats['estimated_net_profit']);
            $this->assertEquals(0.00, (float) $stats['pending_sales']);
        });
    }
}
