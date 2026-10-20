<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerPayoutRequest;
use App\Models\User;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SellerReportsAndEarningsTest extends TestCase
{
    use RefreshDatabase;

    private User $seller1;
    private User $seller2;
    private User $buyer1;
    private Product $product1;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller1 = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Seller One Store',
            'name' => 'Seller One',
            'contact_no' => '09171234567',
        ]);

        $this->seller2 = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Seller Two Store',
            'name' => 'Seller Two',
        ]);

        $this->buyer1 = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
            'name' => 'Buyer One',
            'email' => 'buyer1@example.com',
        ]);

        $this->category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
        ]);

        $this->product1 = Product::create([
            'seller_id' => $this->seller1->id,
            'category_id' => $this->category->id,
            'name' => 'Example Item',
            'slug' => 'example-item',
            'description' => 'Test item',
            'price' => 699.00,
            'stock' => 50,
            'status' => 'active',
        ]);
    }

    /**
     * Helper to create the worked checkout example order.
     * Gross: ₱699.00
     * Seller voucher: −₱50.00
     * Platform voucher: PLAT100 (₱63.66 platform share)
     * Shipping fee: ₱65.00
     * Shipping voucher: FREESHIP
     */
    private function createWorkedCheckoutOrder(string $status = 'completed', ?Carbon $createdAt = null): Order
    {
        $code = 'SELLER50_' . strtoupper(uniqid());

        $sellerVoucher = Voucher::create([
            'seller_id' => $this->seller1->id,
            'is_platform' => false,
            'code' => $code,
            'type' => 'fixed',
            'value' => 50.00,
            'min_spend' => 500.00,
            'is_active' => true,
        ]);

        $order = new Order([
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'user_id' => $this->buyer1->id,
            'voucher_id' => $sellerVoucher->id,
            'voucher_code' => $code,
            'subtotal' => 699.00,
            'discount' => 50.00, // Seller voucher discount
            'shipping_fee' => 65.00,
            'shipping_discount' => 65.00, // FREESHIP discount
            'total' => 649.00,
            'shipping_address' => '123 Test Street, Manila',
            'shipping_method' => 'standard',
            'payment_method' => 'gcash',
            'payment_status' => 'paid',
            'status' => $status,
        ]);
        if ($createdAt) {
            $order->timestamps = false;
            $order->created_at = $createdAt;
            $order->updated_at = $createdAt;
        }
        $order->save();

        $item = new OrderItem([
            'order_id' => $order->id,
            'product_id' => $this->product1->id,
            'product_name' => $this->product1->name,
            'quantity' => 1,
            'price' => 699.00,
            'status' => $status,
        ]);
        if ($createdAt) {
            $item->timestamps = false;
            $item->created_at = $createdAt;
            $item->updated_at = $createdAt;
        }
        $item->save();

        return $order;
    }

    /**
     * SEL-EARN-001: Net earnings calculation on worked checkout example (A8, A9).
     * Gross ₱699.00; seller voucher −₱50.00; commission −₱32.45 (5% of ₱649.00); net ₱616.55.
     * PLAT100 (₱63.66 share), FREESHIP, and the ₱65.00 shipping fee do not appear as deductions.
     */
    public function test_sel_earn_001_net_earnings_calculation_on_worked_checkout_example(): void
    {
        $order = $this->createWorkedCheckoutOrder('completed');

        // Check via breakdown endpoint
        $response = $this->actingAs($this->seller1)
            ->getJson("/seller/reports/orders/{$order->id}/breakdown");

        $response->assertOk();
        $data = $response->json();

        $this->assertEquals(699.00, $data['gross'], 'Gross must be ₱699.00');
        $this->assertEquals(50.00, $data['seller_voucher_discount'], 'Seller voucher discount must be ₱50.00');
        $this->assertEquals(649.00, $data['net_subtotal'], 'Item subtotal after seller discount must be ₱649.00');
        $this->assertEquals(32.45, $data['commission'], 'Marketplace commission must be ₱32.45 (5% of ₱649.00)');
        $this->assertEquals(616.55, $data['net_earnings'], 'Net earnings must be ₱616.55');

        // Check via Reports page
        $reportResponse = $this->actingAs($this->seller1)
            ->get('/seller/reports');

        $reportResponse->assertOk();
        $reportResponse->assertInertia(fn ($page) => $page
            ->component('Seller/Reports')
            ->where('summary.gross_revenue', fn ($val) => (float) $val === 699.0)
            ->where('summary.platform_fees', 32.45)
            ->where('summary.net_earnings', 616.55)
            ->where('balances.available_balance', 616.55)
            ->where('balances.pending_balance', fn ($val) => (float) $val === 0.0)
        );
    }

    /**
     * SEL-EARN-002: Earnings pending until completion (A15).
     * When example order is Delivered (not Completed), ₱616.55 is in Pending balance, ₱0 in Available.
     * When Buyer 1 clicks "Order Received", ₱616.55 moves to Available; Pending reduced by ₱616.55.
     */
    public function test_sel_earn_002_earnings_pending_until_completion(): void
    {
        $order = $this->createWorkedCheckoutOrder('delivered');

        // Before: Delivered but not Completed
        $reportBefore = $this->actingAs($this->seller1)->get('/seller/reports');
        $reportBefore->assertInertia(fn ($page) => $page
            ->where('balances.available_balance', fn ($val) => (float) $val === 0.0)
            ->where('balances.pending_balance', 616.55)
        );

        // Buyer 1 marks order as received (Order Received)
        $receiveResponse = $this->actingAs($this->buyer1)
            ->postJson("/buyer/orders/{$order->id}/receive");
        $receiveResponse->assertOk();

        $order->refresh();
        $this->assertEquals('completed', $order->status);

        // After: Completed
        $reportAfter = $this->actingAs($this->seller1)->get('/seller/reports');
        $reportAfter->assertInertia(fn ($page) => $page
            ->where('balances.available_balance', 616.55)
            ->where('balances.pending_balance', fn ($val) => (float) $val === 0.0)
        );
    }

    /**
     * SEL-EARN-003: Cancelled and refunded orders excluded (A15).
     * Neither order adds to gross sales, commission or earnings, and they are not counted in completed-order count.
     */
    public function test_sel_earn_003_cancelled_and_refunded_orders_excluded(): void
    {
        // One completed order
        $this->createWorkedCheckoutOrder('completed');

        // One cancelled order
        $cancelledOrder = Order::create([
            'order_number' => 'ORD-CANCELLED',
            'user_id' => $this->buyer1->id,
            'subtotal' => 1200.00,
            'discount' => 0.00,
            'shipping_fee' => 50.00,
            'total' => 1250.00,
            'shipping_address' => '123 Test St',
            'shipping_method' => 'standard',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'status' => 'cancelled',
        ]);
        OrderItem::create([
            'order_id' => $cancelledOrder->id,
            'product_id' => $this->product1->id,
            'product_name' => $this->product1->name,
            'quantity' => 2,
            'price' => 600.00,
            'status' => 'cancelled',
        ]);

        // One refunded order
        $refundedOrder = Order::create([
            'order_number' => 'ORD-REFUNDED',
            'user_id' => $this->buyer1->id,
            'subtotal' => 800.00,
            'discount' => 0.00,
            'shipping_fee' => 50.00,
            'total' => 850.00,
            'shipping_address' => '123 Test St',
            'shipping_method' => 'standard',
            'payment_method' => 'gcash',
            'payment_status' => 'paid',
            'status' => 'refunded',
        ]);
        OrderItem::create([
            'order_id' => $refundedOrder->id,
            'product_id' => $this->product1->id,
            'product_name' => $this->product1->name,
            'quantity' => 1,
            'price' => 800.00,
            'status' => 'refunded',
        ]);

        $response = $this->actingAs($this->seller1)->get('/seller/reports');
        $response->assertOk();

        // Totals must only reflect the one completed order (Gross 699, Commission 32.45, Net 616.55, Orders 1)
        $response->assertInertia(fn ($page) => $page
            ->where('summary.gross_revenue', fn ($val) => (float) $val === 699.0)
            ->where('summary.platform_fees', 32.45)
            ->where('summary.net_earnings', 616.55)
            ->where('summary.orders', 1)
        );
    }

    /**
     * SEL-EARN-004: Report date range.
     * Filter today, last 7 days, and custom range matching order's date.
     * Orders on start or end date are included. Invalid date range (end before start) is rejected.
     */
    public function test_sel_earn_004_report_date_range(): void
    {
        // Order 1: Placed today
        $this->createWorkedCheckoutOrder('completed', now());

        // Order 2: Placed 4 days ago
        $this->createWorkedCheckoutOrder('completed', now()->subDays(4));

        // 1. Filter today (should only include Order 1)
        $todayResponse = $this->actingAs($this->seller1)
            ->get('/seller/reports?period=today');
        $todayResponse->assertOk();
        $todayResponse->assertInertia(fn ($page) => $page
            ->where('summary.orders', 1)
            ->where('summary.gross_revenue', fn ($val) => (float) $val === 699.0)
        );

        // 2. Filter last 7 days (should include both Order 1 and Order 2)
        $sevenDaysResponse = $this->actingAs($this->seller1)
            ->get('/seller/reports?period=7days');
        $sevenDaysResponse->assertOk();
        $sevenDaysResponse->assertInertia(fn ($page) => $page
            ->where('summary.orders', 2)
            ->where('summary.gross_revenue', fn ($val) => (float) $val === 1398.0)
        );

        // 3. Custom range matching Order 2's date exactly (start = end = 4 days ago)
        $order2Date = now()->subDays(4)->toDateString();
        $customResponse = $this->actingAs($this->seller1)
            ->get("/seller/reports?from={$order2Date}&to={$order2Date}");
        $customResponse->assertOk();
        $customResponse->assertInertia(fn ($page) => $page
            ->where('summary.orders', 1)
            ->where('summary.gross_revenue', fn ($val) => (float) $val === 699.0)
        );

        // 4. Invalid date range (end date before start date) is rejected with validation error
        $invalidResponse = $this->actingAs($this->seller1)
            ->get('/seller/reports?from=2026-10-25&to=2026-10-20');
        $invalidResponse->assertSessionHasErrors(['to']);
    }

    /**
     * SEL-EARN-005: Payout request amounts (A15).
     * When Available is ₱616.55 and no payout pending:
     * ₱99.99 (reject: < ₱100)
     * ₱100.00 (accept)
     * ₱616.55 (accept)
     * ₱616.56 (reject: > Available)
     * ₱0 (reject)
     * abc (reject)
     * A valid request lowers Available by that amount and creates a Pending payout.
     */
    public function test_sel_earn_005_payout_request_amounts(): void
    {
        // Setup order so Available balance is ₱616.55
        $this->createWorkedCheckoutOrder('completed');

        // 1. Amount ₱99.99 -> reject (< ₱100)
        $res99 = $this->actingAs($this->seller1)
            ->postJson('/seller/payout-requests', ['amount' => 99.99]);
        $res99->assertStatus(422);
        $res99->assertJsonValidationErrors(['amount']);

        // 2. Amount ₱0 -> reject
        $res0 = $this->actingAs($this->seller1)
            ->postJson('/seller/payout-requests', ['amount' => 0]);
        $res0->assertStatus(422);
        $res0->assertJsonValidationErrors(['amount']);

        // 3. Amount 'abc' -> reject
        $resAbc = $this->actingAs($this->seller1)
            ->postJson('/seller/payout-requests', ['amount' => 'abc']);
        $resAbc->assertStatus(422);
        $resAbc->assertJsonValidationErrors(['amount']);

        // 4. Amount ₱616.56 -> reject (> Available 616.55)
        $res61656 = $this->actingAs($this->seller1)
            ->postJson('/seller/payout-requests', ['amount' => 616.56]);
        $res61656->assertStatus(422);
        $res61656->assertJsonValidationErrors(['amount']);

        // 5. Amount ₱100.00 -> accept
        $res100 = $this->actingAs($this->seller1)
            ->postJson('/seller/payout-requests', ['amount' => 100.00]);
        $res100->assertStatus(201);
        $payout100Id = $res100->json('payout.id');

        // Check Available balance lowered to ₱516.55 and Pending payout is ₱100.00
        $report = $this->actingAs($this->seller1)->get('/seller/reports');
        $report->assertInertia(fn ($page) => $page
            ->where('balances.available_balance', 516.55)
            ->where('balances.pending_payout', fn ($val) => (float) $val === 100.0)
            ->where('balances.has_pending_payout', true)
        );

        // Cancel the ₱100 request so we can test the next amount
        $cancelRes = $this->actingAs($this->seller1)
            ->postJson("/seller/payout-requests/{$payout100Id}/cancel");
        $cancelRes->assertOk();

        // Available balance is restored to ₱616.55
        $reportRestored = $this->actingAs($this->seller1)->get('/seller/reports');
        $reportRestored->assertInertia(fn ($page) => $page
            ->where('balances.available_balance', 616.55)
            ->where('balances.has_pending_payout', false)
        );

        // 6. Amount ₱616.55 (exact available balance) -> accept
        $resFull = $this->actingAs($this->seller1)
            ->postJson('/seller/payout-requests', ['amount' => 616.55]);
        $resFull->assertStatus(201);

        $reportFull = $this->actingAs($this->seller1)->get('/seller/reports');
        $reportFull->assertInertia(fn ($page) => $page
            ->where('balances.available_balance', fn ($val) => (float) $val === 0.0)
            ->where('balances.pending_payout', 616.55)
            ->where('balances.has_pending_payout', true)
        );
    }

    /**
     * SEL-EARN-006: Second payout while one is pending is blocked (A15).
     * Blocked with a message that a payout is already pending.
     */
    public function test_sel_earn_006_second_payout_while_one_is_pending_is_blocked(): void
    {
        // Setup order so Available balance is ₱616.55
        $this->createWorkedCheckoutOrder('completed');

        // First payout request: ₱100.00
        $firstRes = $this->actingAs($this->seller1)
            ->postJson('/seller/payout-requests', ['amount' => 100.00]);
        $firstRes->assertStatus(201);

        // Second payout request: ₱100.00 while first is pending
        $secondRes = $this->actingAs($this->seller1)
            ->postJson('/seller/payout-requests', ['amount' => 100.00]);

        $secondRes->assertStatus(422);
        $secondRes->assertJsonValidationErrors(['amount']);
        $this->assertStringContainsString('already pending', $secondRes->json('errors.amount.0'));
    }

    /**
     * SEL-EARN-007: Double-click Request Payout is idempotent (A37).
     * Exactly one payout request exists and Available is lowered once.
     */
    public function test_sel_earn_007_double_click_request_payout_is_idempotent(): void
    {
        $this->createWorkedCheckoutOrder('completed');

        // First click
        $res1 = $this->actingAs($this->seller1)
            ->postJson('/seller/payout-requests', ['amount' => 100.00]);
        $res1->assertStatus(201);

        // Immediate double-click attempt
        $res2 = $this->actingAs($this->seller1)
            ->postJson('/seller/payout-requests', ['amount' => 100.00]);
        $res2->assertStatus(422);

        // Exactly one payout request exists
        $this->assertEquals(1, SellerPayoutRequest::where('seller_id', $this->seller1->id)->count());

        // Available balance is lowered exactly once (616.55 - 100 = 516.55)
        $report = $this->actingAs($this->seller1)->get('/seller/reports');
        $report->assertInertia(fn ($page) => $page
            ->where('balances.available_balance', 516.55)
            ->where('balances.pending_payout', fn ($val) => (float) $val === 100.0)
        );
    }
}
