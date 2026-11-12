<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected User $buyer;
    protected User $sellerA;
    protected User $sellerB;
    protected Category $category;
    protected Product $productA;
    protected Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sellerA = User::factory()->create([
            'name' => 'Seller Store Alpha',
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Store Alpha',
        ]);

        $this->sellerB = User::factory()->create([
            'name' => 'Seller Store Beta',
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Store Beta',
        ]);

        $this->buyer = User::factory()->create([
            'name' => 'Buyer Tester',
            'usertype' => 'buyer',
            'email' => 'buyer.test@example.com',
            'status' => 'approved',
        ]);

        $this->category = Category::create([
            'name' => 'General Goods',
            'slug' => 'general-goods',
        ]);

        $this->productA = Product::create([
            'seller_id' => $this->sellerA->id,
            'category_id' => $this->category->id,
            'name' => 'Alpha Product',
            'slug' => 'alpha-product',
            'price' => 100.00,
            'stock' => 50,
            'weight' => 0.5,
            'status' => 'approved',
        ]);

        $this->productB = Product::create([
            'seller_id' => $this->sellerB->id,
            'category_id' => $this->category->id,
            'name' => 'Beta Product',
            'slug' => 'beta-product',
            'price' => 200.00,
            'stock' => 50,
            'weight' => 0.5,
            'status' => 'approved',
        ]);
    }

    /**
     * BUY-CHK-001: Multi-store checkout splits into separate store orders.
     */
    public function test_buy_chk_001_multi_store_checkout_splits_into_separate_orders(): void
    {
        $cartA = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->productA->id,
            'quantity' => 2,
        ]);

        $cartB = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->productB->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($this->buyer)->post(route('buyer.orders.store'), [
            'shipping_address' => '123 Rizal Ave, Manila, NCR',
            'payment_method' => 'cod',
            'shipping_method' => 'standard',
            'selected_items' => [$cartA->id, $cartB->id],
        ]);

        $response->assertSessionHasNoErrors();

        // Must split into 2 separate store orders
        $orders = Order::where('user_id', $this->buyer->id)->get();
        $this->assertCount(2, $orders);

        $orderA = $orders->first(fn ($o) => $o->items->contains('product_id', $this->productA->id));
        $orderB = $orders->first(fn ($o) => $o->items->contains('product_id', $this->productB->id));

        $this->assertNotNull($orderA);
        $this->assertNotNull($orderB);
        $this->assertNotEquals($orderA->id, $orderB->id);
        $this->assertEquals(200.00, (float) $orderA->subtotal);
        $this->assertEquals(200.00, (float) $orderB->subtotal);

        // Cart items removed
        $this->assertDatabaseMissing('cart_items', ['id' => $cartA->id]);
        $this->assertDatabaseMissing('cart_items', ['id' => $cartB->id]);
    }

    /**
     * BUY-CHK-002: Shipping fee per store and address (NCR is ₱50; Visayas is ₱120).
     */
    public function test_buy_chk_002_shipping_fee_per_store_and_address(): void
    {
        // 1. NCR address -> ₱50
        $cart1 = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
        ]);

        $resNCR = $this->actingAs($this->buyer)->post(route('buyer.orders.store'), [
            'shipping_address' => '456 Quezon Blvd, Quezon City, Metro Manila',
            'payment_method' => 'cod',
            'shipping_method' => 'standard',
            'selected_items' => [$cart1->id],
        ]);
        $resNCR->assertSessionHasNoErrors();
        $orderNCR = Order::where('user_id', $this->buyer->id)->latest('id')->first();
        $this->assertEquals(50.00, (float) $orderNCR->shipping_fee_before_discount);

        // 2. Visayas address -> ₱120
        $cart2 = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
        ]);

        $resVisayas = $this->actingAs($this->buyer)->post(route('buyer.orders.store'), [
            'shipping_address' => '789 Osmena Blvd, Cebu City, Visayas',
            'payment_method' => 'cod',
            'shipping_method' => 'standard',
            'selected_items' => [$cart2->id],
        ]);
        $resVisayas->assertSessionHasNoErrors();
        $orderVisayas = Order::where('user_id', $this->buyer->id)->latest('id')->first();
        $this->assertEquals(120.00, (float) $orderVisayas->shipping_fee_before_discount);
    }

    /**
     * BUY-CHK-003: Shipping weight at 1 kg boundary (1.0 kg is ₱60; 1.2 kg rounded up to 2 kg for ₱80).
     */
    public function test_buy_chk_003_shipping_weight_at_1kg_boundary(): void
    {
        $product1Kg = Product::create([
            'seller_id' => $this->sellerA->id,
            'category_id' => $this->category->id,
            'name' => '1.0kg Item',
            'slug' => 'item-1kg',
            'price' => 150.00,
            'stock' => 50,
            'weight' => 1.0,
            'status' => 'approved',
        ]);

        $product1_2Kg = Product::create([
            'seller_id' => $this->sellerB->id,
            'category_id' => $this->category->id,
            'name' => '1.2kg Item',
            'slug' => 'item-1-2kg',
            'price' => 150.00,
            'stock' => 50,
            'weight' => 1.2,
            'status' => 'approved',
        ]);

        // 1.0 kg -> base ₱60
        $cart1 = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $product1Kg->id,
            'quantity' => 1,
        ]);

        $res1 = $this->actingAs($this->buyer)->post(route('buyer.orders.store'), [
            'shipping_address' => '100 Main St, Davao City',
            'payment_method' => 'cod',
            'shipping_method' => 'standard',
            'selected_items' => [$cart1->id],
        ]);
        $res1->assertSessionHasNoErrors();
        $order1 = Order::where('user_id', $this->buyer->id)->latest('id')->first();
        $this->assertEquals(60.00, (float) $order1->shipping_fee_before_discount);

        // 1.2 kg -> rounded to 2 kg -> ₱60 + ₱20 = ₱80
        $cart2 = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $product1_2Kg->id,
            'quantity' => 1,
        ]);

        $res2 = $this->actingAs($this->buyer)->post(route('buyer.orders.store'), [
            'shipping_address' => '100 Main St, Davao City',
            'payment_method' => 'cod',
            'shipping_method' => 'standard',
            'selected_items' => [$cart2->id],
        ]);
        $res2->assertSessionHasNoErrors();
        $order2 = Order::where('user_id', $this->buyer->id)->latest('id')->first();
        $this->assertEquals(80.00, (float) $order2->shipping_fee_before_discount);
    }

    /**
     * BUY-CHK-004: Unserviceable address blocks Place Order.
     */
    public function test_buy_chk_004_unserviceable_address_blocks_order(): void
    {
        $cart = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($this->buyer)->post(route('buyer.orders.store'), [
            'shipping_address' => 'Basco, Batanes',
            'payment_method' => 'cod',
            'shipping_method' => 'standard',
            'selected_items' => [$cart->id],
        ]);

        $response->assertSessionHasErrors('shipping_address');
        $this->assertCount(0, Order::all());
    }

    /**
     * BUY-CHK-005: Note to seller length (200 characters saved; 201 rejected).
     */
    public function test_buy_chk_005_note_to_seller_length_boundary(): void
    {
        $cart1 = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
        ]);

        // 201 characters -> rejected
        $res201 = $this->actingAs($this->buyer)->post(route('buyer.orders.store'), [
            'shipping_address' => '123 Taft Ave, Manila',
            'payment_method' => 'cod',
            'shipping_method' => 'standard',
            'buyer_note' => str_repeat('a', 201),
            'selected_items' => [$cart1->id],
        ]);
        $res201->assertSessionHasErrors('buyer_note');

        // 200 characters -> accepted
        $res200 = $this->actingAs($this->buyer)->post(route('buyer.orders.store'), [
            'shipping_address' => '123 Taft Ave, Manila',
            'payment_method' => 'cod',
            'shipping_method' => 'standard',
            'buyer_note' => str_repeat('b', 200),
            'selected_items' => [$cart1->id],
        ]);
        $res200->assertSessionHasNoErrors();
        $order = Order::latest('id')->first();
        $this->assertEquals(str_repeat('b', 200), $order->buyer_note);
    }

    /**
     * BUY-CHK-006: No payment method selected blocks order submission.
     */
    public function test_buy_chk_006_no_payment_method_selected_blocks_order(): void
    {
        $cart = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($this->buyer)->post(route('buyer.orders.store'), [
            'shipping_address' => '123 Taft Ave, Manila',
            'shipping_method' => 'standard',
            'selected_items' => [$cart->id],
        ]);

        $response->assertSessionHasErrors('payment_method');
        $this->assertCount(0, Order::all());
    }

    /**
     * BUY-CHK-007: Rapid double-click Place Order only creates 1 order.
     */
    public function test_buy_chk_007_double_click_place_order_creates_only_one_order(): void
    {
        $cart = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
        ]);

        $res1 = $this->actingAs($this->buyer)->post(route('buyer.orders.store'), [
            'shipping_address' => '123 Taft Ave, Manila',
            'payment_method' => 'cod',
            'shipping_method' => 'standard',
            'selected_items' => [$cart->id],
        ]);
        $res1->assertSessionHasNoErrors();

        // Immediate duplicate request with same cart item
        $res2 = $this->actingAs($this->buyer)->post(route('buyer.orders.store'), [
            'shipping_address' => '123 Taft Ave, Manila',
            'payment_method' => 'cod',
            'shipping_method' => 'standard',
            'selected_items' => [$cart->id],
        ]);
        $res2->assertSessionHasErrors('selected_items');

        $this->assertCount(1, Order::all());
    }

    /**
     * BUY-CHK-008: Price changed during checkout alerts buyer.
     */
    public function test_buy_chk_008_price_changed_during_checkout_alerts_buyer(): void
    {
        $cart = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
        ]);

        // Seller raises price from 100 to 150 while buyer was on checkout page
        $this->productA->update(['price' => 150.00]);

        // Buyer submits with expected_prices of 100.00
        $response = $this->actingAs($this->buyer)->post(route('buyer.orders.store'), [
            'shipping_address' => '123 Taft Ave, Manila',
            'payment_method' => 'cod',
            'shipping_method' => 'standard',
            'selected_items' => [$cart->id],
            'expected_prices' => [
                $cart->id => 100.00,
            ],
        ]);

        $response->assertSessionHasErrors(['expected_prices']);
        $this->assertCount(0, Order::all());
    }

    /**
     * BUY-CHK-009: Stock ran out during checkout blocks order safely.
     */
    public function test_buy_chk_009_stock_ran_out_during_checkout_blocks_order(): void
    {
        $this->productA->update(['stock' => 1]);

        $cart = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
        ]);

        // Another buyer buys the last unit
        $this->productA->update(['stock' => 0]);

        $response = $this->actingAs($this->buyer)->post(route('buyer.orders.store'), [
            'shipping_address' => '123 Taft Ave, Manila',
            'payment_method' => 'cod',
            'shipping_method' => 'standard',
            'selected_items' => [$cart->id],
        ]);

        $response->assertSessionHasErrors('selected_items');
        $this->assertCount(0, Order::all());
    }

    /**
     * BUY-CHK-010: Voucher invalid during checkout blocks submission and updates total.
     */
    public function test_buy_chk_010_voucher_ended_during_checkout_blocks_order(): void
    {
        $voucher = Voucher::create([
            'seller_id' => $this->sellerA->id,
            'code' => 'ACTIVE50',
            'type' => 'fixed',
            'value' => 50.00,
            'min_spend' => 50.00,
            'is_active' => true,
        ]);

        $cart = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->productA->id,
            'quantity' => 1,
        ]);

        // Seller ends the voucher right before buyer places order
        $voucher->update([
            'is_active' => false,
            'ended_at' => now(),
        ]);

        $response = $this->actingAs($this->buyer)->post(route('buyer.orders.store'), [
            'shipping_address' => '123 Taft Ave, Manila',
            'payment_method' => 'cod',
            'shipping_method' => 'standard',
            'selected_items' => [$cart->id],
            'voucher_codes' => ['ACTIVE50'],
        ]);

        $response->assertSessionHasErrors('voucher_code');
        $this->assertCount(0, Order::all());
    }

    /**
     * BUY-CHK-011: Tampered price in request is rejected and recalculated by server.
     */
    public function test_buy_chk_011_tampered_price_is_recalculated_by_server(): void
    {
        $cart = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->productA->id, // Actual price is 100.00
            'quantity' => 2,
        ]);

        $response = $this->actingAs($this->buyer)->post(route('buyer.orders.store'), [
            'shipping_address' => '123 Taft Ave, Manila',
            'payment_method' => 'cod',
            'shipping_method' => 'standard',
            'selected_items' => [$cart->id],
            'subtotal' => 1.00, // Fake subtotal
            'total' => 1.00,    // Fake total
            'price' => 1.00,    // Fake unit price
        ]);

        $response->assertSessionHasNoErrors();
        $order = Order::latest('id')->first();
        $this->assertEquals(200.00, (float) $order->subtotal);
        $this->assertNotEquals(1.00, (float) $order->total);
    }
}

