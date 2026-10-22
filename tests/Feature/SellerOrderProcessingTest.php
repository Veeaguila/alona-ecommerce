<?php

namespace Tests\Feature;

use App\Models\BuyerNotification;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturnRequest;
use App\Models\Parcel;
use App\Models\PickupRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RefundLedger;
use App\Models\User;
use App\Models\UserVoucher;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SellerOrderProcessingTest extends TestCase
{
    use RefreshDatabase;

    private User $seller1;
    private User $seller2;
    private User $buyer1;
    private Category $category;
    private Product $p01;
    private ProductVariant $p01NaturalM;
    private Product $p02;
    private Product $seller2Product;

    protected const ADDR_1 = '123 Rizal Street, Brgy. Sagpon, Daraga, Albay 4501';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller1 = User::factory()->create([
            'name' => 'Maria Handwoven Crafts',
            'store_name' => 'Maria Handwoven Crafts',
            'usertype' => 'seller',
            'status' => 'approved',
            'address' => '45 Peñaranda St, Legazpi City, Albay',
        ]);

        $this->seller2 = User::factory()->create([
            'name' => 'Bicol Pili House',
            'store_name' => 'Bicol Pili House',
            'usertype' => 'seller',
            'status' => 'approved',
            'address' => '88 Magsaysay Ave, Naga City, Camarines Sur',
        ]);

        $this->buyer1 = User::factory()->create([
            'name' => 'Juan Dela Cruz',
            'usertype' => 'buyer',
            'status' => 'approved',
        ]);

        $this->category = Category::create([
            'name' => 'Native Crafts',
            'slug' => 'native-crafts',
        ]);

        $this->p01 = Product::create([
            'seller_id' => $this->seller1->id,
            'category_id' => $this->category->id,
            'name' => 'Handwoven Abaca Tote Bag',
            'slug' => 'handwoven-abaca-tote-bag-s9',
            'description' => 'Handwoven abaca tote bag made in Bicol.',
            'price' => 249.00,
            'stock' => 20,
            'weight' => 0.50,
            'status' => 'approved',
        ]);

        $this->p01NaturalM = ProductVariant::create([
            'product_id' => $this->p01->id,
            'color' => 'Natural',
            'size' => 'M',
            'price' => 249.00,
            'stock' => 10,
            'sku' => 'P01-NAT-M',
        ]);

        $this->p02 = Product::create([
            'seller_id' => $this->seller1->id,
            'category_id' => $this->category->id,
            'name' => 'Woven Rattan Basket',
            'slug' => 'woven-rattan-basket-s9',
            'description' => 'Handcrafted woven rattan basket from Albay.',
            'price' => 450.00,
            'stock' => 20,
            'weight' => 0.80,
            'status' => 'approved',
        ]);

        $this->seller2Product = Product::create([
            'seller_id' => $this->seller2->id,
            'category_id' => $this->category->id,
            'name' => 'Roasted Pili Nuts 250g',
            'slug' => 'roasted-pili-nuts-250g',
            'description' => 'Crunchy roasted pili nuts from Camarines Sur.',
            'price' => 180.00,
            'stock' => 25,
            'weight' => 0.30,
            'status' => 'approved',
        ]);
    }

    private function createOrderWithItem(string $status, string $orderNumber, ?Carbon $createdAt = null): OrderItem
    {
        $order = Order::create([
            'order_number' => $orderNumber,
            'user_id' => $this->buyer1->id,
            'shipping_address' => self::ADDR_1,
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'shipping_method' => 'standard',
            'subtotal' => 450.00,
            'shipping_fee' => 50.00,
            'discount' => 0.00,
            'total' => 500.00,
            'status' => $status === 'delivered' ? 'completed' : $status,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->p02->id,
            'product_name' => $this->p02->name,
            'price' => 450.00,
            'quantity' => 1,
            'status' => $status,
        ]);

        if ($createdAt) {
            OrderItem::whereKey($item->id)->update(['created_at' => $createdAt]);
        }

        return $item->fresh();
    }

    /**
     * SEL-ORD-001: Order list tabs, counts and filters
     */
    public function test_sel_ord_001_order_list_tabs_counts_and_filters(): void
    {
        $newItem = $this->createOrderWithItem('pending', 'ORD-S9-NEW', Carbon::parse('2026-10-15 10:00:00'));
        $toShipItem = $this->createOrderWithItem('processing', 'ORD-S9-TOSHIP', Carbon::parse('2026-10-16 10:00:00'));
        $shippingItem = $this->createOrderWithItem('shipped', 'ORD-S9-SHIP', Carbon::parse('2026-10-17 10:00:00'));
        $completedItem = $this->createOrderWithItem('completed', 'ORD-S9-COMP', Carbon::parse('2026-10-18 10:00:00'));
        $cancelledItem = $this->createOrderWithItem('cancelled', 'ORD-S9-CANC', Carbon::parse('2026-10-19 10:00:00'));
        $returnItem = $this->createOrderWithItem('delivered', 'ORD-S9-RET', Carbon::parse('2026-10-20 10:00:00'));

        OrderReturnRequest::create([
            'order_id' => $returnItem->order_id,
            'order_item_id' => $returnItem->id,
            'buyer_id' => $this->buyer1->id,
            'seller_id' => $this->seller1->id,
            'type' => 'return_and_refund',
            'reason' => 'Damaged item',
            'reason_details' => 'The basket arrived with a broken handle.',
            'requested_amount' => 450.00,
            'status' => 'pending',
        ]);

        $tabs = [
            'New' => ['summary_key' => 'new', 'expected_id' => $newItem->id],
            'To Ship' => ['summary_key' => 'to_ship', 'expected_id' => $toShipItem->id],
            'Shipping' => ['summary_key' => 'shipping', 'expected_id' => $shippingItem->id],
            'Completed' => ['summary_key' => 'completed_tab', 'expected_id' => $completedItem->id],
            'Cancelled' => ['summary_key' => 'cancelled', 'expected_id' => $cancelledItem->id],
            'Return/Refund' => ['summary_key' => 'return_refund', 'expected_id' => $returnItem->id],
        ];

        $seenIds = [];
        foreach ($tabs as $tabName => $meta) {
            $this->actingAs($this->seller1)
                ->get(route('seller.orders', ['status' => $tabName]))
                ->assertOk()
                ->assertInertia(function (Assert $page) use ($meta, &$seenIds) {
                    $page->component('Seller/Orders')
                        ->where("summary.{$meta['summary_key']}", 1)
                        ->has('orders.data', 1)
                        ->where('orders.data.0.id', $meta['expected_id']);
                    $seenIds[] = $meta['expected_id'];
                });
        }

        // Each order appears in exactly one tab
        $this->assertCount(6, array_unique($seenIds));

        // Filter by date range (2026-10-16 to 2026-10-17)
        $this->actingAs($this->seller1)
            ->get(route('seller.orders', [
                'date_from' => '2026-10-16',
                'date_to' => '2026-10-17',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('orders.data', 2)
            );

        // Search by order ID / order number
        $this->actingAs($this->seller1)
            ->get(route('seller.orders', ['search' => 'ORD-S9-COMP']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('orders.data', 1)
                ->where('orders.data.0.id', $completedItem->id)
            );
    }

    /**
     * SEL-ORD-002: Accept, pack, ready for pickup and print waybill
     */
    public function test_sel_ord_002_accept_pack_ready_for_pickup_and_print_waybill(): void
    {
        $item = $this->createOrderWithItem('pending', 'ORD-S9-002');

        // 1. Click Accept
        $this->actingAs($this->seller1)
            ->patch(route('seller.orders.process', $item->id))
            ->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame('processing', $item->status);
        $this->assertSame('Accepted', $item->seller_status_label);
        $this->assertNotNull($item->accepted_at);

        // 2. Click Mark as Packed
        $this->actingAs($this->seller1)
            ->patch(route('seller.orders.pack', $item->id))
            ->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame('packed', $item->status);
        $this->assertSame('Packed', $item->seller_status_label);
        $this->assertNotNull($item->packed_at);

        // 3. Click Ready for Pickup
        $this->actingAs($this->seller1)
            ->patch(route('seller.orders.ready-for-pickup', $item->id))
            ->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame('ready_for_pickup', $item->status);
        $this->assertSame('Ready for Pickup', $item->seller_status_label);
        $this->assertNotNull($item->ready_for_pickup_at);
        $this->assertNotEmpty($item->tracking_number);

        // 4. Print the waybill -> shows tracking number, ADDR-1, Seller 1's pickup address, 0.8 kg and COD ₱500.00
        $this->actingAs($this->seller1)
            ->get(route('seller.orders.waybill', $item->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Seller/ShippingDocuments')
                ->where('waybill.tracking_number', $item->tracking_number)
                ->where('waybill.shipping_address', self::ADDR_1)
                ->where('waybill.pickup_address', '45 Peñaranda St, Legazpi City, Albay')
                ->where('waybill.weight_label', '0.8 kg')
                ->where('waybill.cod_label', 'COD ₱500.00')
            );

        // 5. Logistics 1 sees the parcel in the pickup queue, and Buyer 1's timeline shows each step
        $this->assertTrue(Parcel::where('order_item_id', $item->id)->exists());
        $this->assertTrue(PickupRequest::where('seller_id', $this->seller1->id)->where('status', 'pending')->exists());

        $historyStatuses = $item->statusHistories()->pluck('status')->all();
        $this->assertContains('processing', $historyStatuses);
        $this->assertContains('packed', $historyStatuses);
        $this->assertContains('ready_for_pickup', $historyStatuses);
    }

    /**
     * SEL-ORD-003: Skip a step
     */
    public function test_sel_ord_003_skip_a_step(): void
    {
        $item = $this->createOrderWithItem('pending', 'ORD-S9-003');

        // 1. Only Accept and Cancel are available on a New order
        $this->assertSame(['accept', 'cancel'], $item->available_actions);

        // 2. Try Ready for Pickup directly before accepting -> rejected (422) and status stays pending (New)
        $this->actingAs($this->seller1)
            ->patch(route('seller.orders.ready-for-pickup', $item->id))
            ->assertStatus(422);

        $this->assertSame('pending', $item->fresh()->status);

        // Try Pack directly before accepting -> rejected (422) and status stays pending (New)
        $this->actingAs($this->seller1)
            ->patch(route('seller.orders.pack', $item->id))
            ->assertStatus(422);

        $this->assertSame('pending', $item->fresh()->status);
    }

    /**
     * SEL-ORD-004: Seller cancels a COD order
     */
    public function test_sel_ord_004_seller_cancels_a_cod_order(): void
    {
        // P-02 stock is 18 after 2 × P-02 were ordered
        $this->p02->update(['stock' => 18]);

        $voucher = Voucher::create([
            'seller_id' => $this->seller1->id,
            'code' => 'MCH50',
            'type' => 'fixed',
            'value' => 50.00,
            'min_spend' => 300.00,
            'usage_limit' => 50,
            'used_count' => 1,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(14),
            'is_active' => true,
        ]);

        UserVoucher::create([
            'user_id' => $this->buyer1->id,
            'voucher_id' => $voucher->id,
            'status' => 'used',
            'claimed_at' => now()->subHour(),
            'used_at' => now()->subMinutes(30),
        ]);

        $order = Order::create([
            'order_number' => 'ORD-S9-004',
            'user_id' => $this->buyer1->id,
            'voucher_id' => $voucher->id,
            'voucher_code' => 'MCH50',
            'shipping_address' => self::ADDR_1,
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'shipping_method' => 'standard',
            'subtotal' => 900.00,
            'discount' => 50.00,
            'shipping_fee' => 50.00,
            'total' => 900.00,
            'status' => 'processing',
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->p02->id,
            'product_name' => $this->p02->name,
            'price' => 450.00,
            'quantity' => 2,
            'status' => 'processing',
        ]);

        // Step 2: Submit without a reason -> rejected
        $this->actingAs($this->seller1)
            ->patch(route('seller.orders.cancel', $item->id), [
                'reason' => '',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertSame('processing', $item->fresh()->status);

        // Step 3: Choose 'Out of stock' and confirm
        $this->actingAs($this->seller1)
            ->patch(route('seller.orders.cancel', $item->id), [
                'reason' => 'Out of stock',
            ])
            ->assertSessionHasNoErrors();

        // Status Cancelled for both sides
        $this->assertSame('cancelled', $item->fresh()->status);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('Out of stock', $order->fresh()->cancellation_reason);

        // P-02 stock restored to 20
        $this->assertSame(20, $this->p02->fresh()->stock);

        // MCH50 back in Buyer 1's wallet
        $this->assertSame(0, $voucher->fresh()->used_count);
        $this->assertDatabaseHas('user_vouchers', [
            'user_id' => $this->buyer1->id,
            'voucher_id' => $voucher->id,
            'status' => 'claimed',
        ]);

        // Buyer 1 notified with the reason
        $notification = BuyerNotification::where('user_id', $this->buyer1->id)->latest('id')->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Out of stock', $notification->message);
    }

    /**
     * SEL-ORD-005: Seller cancels a prepaid order
     */
    public function test_sel_ord_005_seller_cancels_a_prepaid_order(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-S9-005',
            'user_id' => $this->buyer1->id,
            'shipping_address' => self::ADDR_1,
            'payment_method' => 'gcash',
            'payment_status' => 'paid',
            'shipping_method' => 'standard',
            'subtotal' => 450.00,
            'discount' => 0.00,
            'shipping_fee' => 50.00,
            'total' => 500.00,
            'status' => 'processing',
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->p02->id,
            'product_name' => $this->p02->name,
            'price' => 450.00,
            'quantity' => 1,
            'status' => 'processing',
        ]);

        // 1. Cancel with the reason 'Out of stock'
        $this->actingAs($this->seller1)
            ->patch(route('seller.orders.cancel', $item->id), [
                'reason' => 'Out of stock',
            ])
            ->assertSessionHasNoErrors();

        // 2. Order is Cancelled and a refund for the full amount paid (₱500.00) is started
        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('refunded', $order->payment_status);
        $this->assertSame('refund_started', $order->refund_status);
        $this->assertEquals(500.00, (float) $order->refund_amount);

        $this->assertTrue(
            RefundLedger::where('order_id', $order->id)
                ->where('refunded_amount', 500.00)
                ->exists()
        );
    }

    /**
     * SEL-ORD-006: No cancel after handover
     */
    public function test_sel_ord_006_no_cancel_after_handover(): void
    {
        $item = $this->createOrderWithItem('ready_for_pickup', 'ORD-S9-006');

        // Rider 1 has picked up the parcel
        Parcel::create([
            'order_item_id' => $item->id,
            'order_id' => 'ITEM-' . $item->id,
            'buyer_id' => $this->buyer1->id,
            'seller_id' => $this->seller1->id,
            'status' => 'PICKED_UP',
            'delivery_address' => self::ADDR_1,
            'delivery_province' => 'Albay',
            'delivery_municipality' => 'Daraga',
            'delivery_barangay' => 'Sagpon',
            'collection_address' => $this->seller1->address,
        ]);

        // 1. Cancel and Edit are not shown
        $this->actingAs($this->seller1)
            ->get(route('seller.order.details', $item->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Seller/OrderDetails')
                ->where('orderItem.can_cancel', false)
                ->where('orderItem.can_edit', false)
            );

        // 2. Direct cancel request is rejected and status is unchanged
        $this->actingAs($this->seller1)
            ->patch(route('seller.orders.cancel', $item->id), [
                'reason' => 'Out of stock',
            ])
            ->assertStatus(422);

        $this->assertSame('ready_for_pickup', $item->fresh()->status);
    }

    /**
     * SEL-ORD-007: Order details accuracy (worked checkout example)
     */
    public function test_sel_ord_007_order_details_accuracy(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-S9-007',
            'user_id' => $this->buyer1->id,
            'voucher_code' => 'MCH50',
            'shipping_address' => self::ADDR_1,
            'buyer_note' => 'Please pack carefully in eco bag.',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'shipping_method' => 'standard',
            'subtotal' => 879.00,
            'discount' => 50.00,
            'shipping_fee' => 65.00,
            'total' => 894.00,
            'status' => 'pending',
        ]);

        // Seller 1 item 1: Abaca Tote Natural-M × 1 at ₱249.00
        $seller1Item1 = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->p01->id,
            'product_variant_id' => $this->p01NaturalM->id,
            'product_name' => 'Handwoven Abaca Tote Bag',
            'variant_label' => 'Natural-M',
            'price' => 249.00,
            'quantity' => 1,
            'status' => 'pending',
        ]);

        // Seller 1 item 2: Rattan Basket × 1 at ₱450.00
        $seller1Item2 = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->p02->id,
            'product_name' => 'Woven Rattan Basket',
            'price' => 450.00,
            'quantity' => 1,
            'status' => 'pending',
        ]);

        // Seller 2 item in the same checkout: must NOT appear in Seller 1's order details
        $seller2Item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->seller2Product->id,
            'product_name' => 'Roasted Pili Nuts 250g',
            'price' => 180.00,
            'quantity' => 1,
            'status' => 'pending',
        ]);

        $this->actingAs($this->seller1)
            ->get(route('seller.order.details', $seller1Item1->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Seller/OrderDetails')
                ->has('sellerItems', 2)
                ->where('sellerItems.0.product_name', 'Handwoven Abaca Tote Bag')
                ->where('sellerItems.0.variant_label', 'Natural-M')
                ->where('sellerItems.0.quantity', 1)
                ->where('sellerItems.0.price', '249.00')
                ->where('sellerItems.1.product_name', 'Woven Rattan Basket')
                ->where('sellerItems.1.quantity', 1)
                ->where('sellerItems.1.price', '450.00')
                ->where('orderSummary.voucher_label', 'MCH50 −₱50.00')
                ->where('orderSummary.shipping_fee', '65.00')
                ->where('orderSummary.buyer_note', 'Please pack carefully in eco bag.')
                ->where('orderSummary.payment_method_label', 'COD')
                ->where('orderSummary.shipping_address', self::ADDR_1)
            );
    }

    /**
     * SEL-ORD-008: Seller cannot open another store's order
     */
    public function test_sel_ord_008_seller_cannot_open_another_stores_order(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-S9-008',
            'user_id' => $this->buyer1->id,
            'shipping_address' => self::ADDR_1,
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'shipping_method' => 'standard',
            'subtotal' => 180.00,
            'shipping_fee' => 50.00,
            'discount' => 0.00,
            'total' => 230.00,
            'status' => 'pending',
        ]);

        $seller2OrderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->seller2Product->id,
            'product_name' => $this->seller2Product->name,
            'price' => 180.00,
            'quantity' => 1,
            'status' => 'pending',
        ]);

        // Seller 1 attempts to open Seller 2's order details, waybill, or process it -> 403 Forbidden
        $this->actingAs($this->seller1)
            ->get(route('seller.order.details', $seller2OrderItem->id))
            ->assertStatus(403);

        $this->actingAs($this->seller1)
            ->get(route('seller.orders.waybill', $seller2OrderItem->id))
            ->assertStatus(403);

        $this->actingAs($this->seller1)
            ->patch(route('seller.orders.process', $seller2OrderItem->id))
            ->assertStatus(403);
    }

    /**
     * Seller must confirm e-wallet (GCash, Maya) or card payment before marking item as packed
     */
    public function test_seller_must_confirm_ewallet_and_card_payment_before_packing(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-EWALLET-001',
            'user_id' => $this->buyer1->id,
            'shipping_address' => self::ADDR_1,
            'payment_method' => 'gcash',
            'payment_status' => 'unpaid',
            'shipping_method' => 'standard',
            'subtotal' => 249.00,
            'shipping_fee' => 50.00,
            'discount' => 0.00,
            'total' => 299.00,
            'status' => 'pending',
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->p01->id,
            'product_name' => $this->p01->name,
            'price' => 249.00,
            'quantity' => 1,
            'status' => 'processing',
        ]);

        // 1. Seller attempts to pack before confirming payment -> 422
        $response = $this->actingAs($this->seller1)
            ->patch(route('seller.orders.pack', $orderItem->id));

        $response->assertStatus(422);
        $this->assertEquals('processing', $orderItem->fresh()->status);
        $this->assertEquals('unpaid', $order->fresh()->payment_status);

        // 2. Seller confirms payment
        $confirmResponse = $this->actingAs($this->seller1)
            ->patch(route('seller.orders.confirm-payment', $orderItem->id));

        $confirmResponse->assertSessionHas('status', 'Payment verified and confirmed as paid.');
        $this->assertEquals('paid', $order->fresh()->payment_status);

        // Buyer received notification
        $this->assertDatabaseHas('buyer_notifications', [
            'user_id' => $this->buyer1->id,
            'type' => 'payment',
        ]);

        // 3. Now packing succeeds
        $packResponse = $this->actingAs($this->seller1)
            ->patch(route('seller.orders.pack', $orderItem->id));

        $packResponse->assertRedirect();
        $this->assertEquals('packed', $orderItem->fresh()->status);
    }
}
