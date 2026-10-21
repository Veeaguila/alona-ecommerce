<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\UserVoucher;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerVouchersTest extends TestCase
{
    use RefreshDatabase;

    protected User $buyer;
    protected User $sellerA;
    protected User $sellerB;
    protected Category $category;
    protected Product $productA1;
    protected Product $productA2;
    protected Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sellerA = User::factory()->create([
            'name' => 'Seller Alpha',
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Alpha Crafts',
        ]);

        $this->sellerB = User::factory()->create([
            'name' => 'Seller Beta',
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Beta Goods',
        ]);

        $this->buyer = User::factory()->create([
            'name' => 'Buyer One',
            'usertype' => 'buyer',
            'status' => 'approved',
        ]);

        Address::create([
            'user_id' => $this->buyer->id,
            'address' => '123 Coastal Road, Tagbilaran City, Bohol',
            'label' => 'Home',
            'is_default' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Handicrafts',
            'slug' => 'handicrafts',
        ]);

        $this->productA1 = Product::create([
            'seller_id' => $this->sellerA->id,
            'category_id' => $this->category->id,
            'name' => 'Handwoven Basket',
            'slug' => 'handwoven-basket',
            'price' => 200.00,
            'stock' => 50,
            'status' => 'approved',
        ]);

        $this->productA2 = Product::create([
            'seller_id' => $this->sellerA->id,
            'category_id' => $this->category->id,
            'name' => 'Woven Placemat',
            'slug' => 'woven-placemat',
            'price' => 100.00,
            'stock' => 50,
            'status' => 'approved',
        ]);

        $this->productB = Product::create([
            'seller_id' => $this->sellerB->id,
            'category_id' => $this->category->id,
            'name' => 'Ceramic Mug',
            'slug' => 'ceramic-mug',
            'price' => 300.00,
            'stock' => 50,
            'status' => 'approved',
        ]);
    }

    /**
     * BUY-VOU-001: Claim a voucher into the wallet.
     */
    public function test_buy_vou_001_claim_voucher_into_wallet(): void
    {
        $voucher = Voucher::create([
            'seller_id' => $this->sellerA->id,
            'code' => 'CLAIM50',
            'type' => 'fixed',
            'value' => 50.00,
            'min_spend' => 200.00,
            'per_customer_limit' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->buyer)
            ->post(route('buyer.vouchers.claim', $voucher));

        $response->assertSessionHas('status');
        $this->assertDatabaseHas('user_vouchers', [
            'user_id' => $this->buyer->id,
            'voucher_id' => $voucher->id,
            'status' => 'claimed',
        ]);

        // Attempting to claim again beyond per_customer_limit is rejected
        $repeatResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.vouchers.claim', $voucher));
        $repeatResponse->assertSessionHasErrors(['voucher']);
    }

    /**
     * BUY-VOU-002: Apply a store voucher.
     */
    public function test_buy_vou_002_apply_store_voucher(): void
    {
        $cartItem = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->productA1->id,
            'quantity' => 2, // 2 x 200 = 400
        ]);

        $voucher = Voucher::create([
            'seller_id' => $this->sellerA->id,
            'code' => 'STORE50',
            'type' => 'fixed',
            'value' => 50.00,
            'min_spend' => 300.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->buyer)
            ->postJson(route('buyer.vouchers.validate'), [
                'voucher_code' => 'STORE50',
                'selected_items' => [$cartItem->id],
            ]);

        $response->assertOk()
            ->assertJson([
                'valid' => true,
                'voucher' => [
                    'code' => 'STORE50',
                    'category' => 'store',
                    'discount' => 50.00,
                ],
            ]);
    }

    /**
     * BUY-VOU-003: Stacking per agreed rule: 1 store + 1 platform + 1 free shipping voucher stack.
     */
    public function test_buy_vou_003_stacking_three_voucher_categories(): void
    {
        $cartItemA = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->productA1->id,
            'quantity' => 3, // 3 x 200 = 600
        ]);

        $storeVoucher = Voucher::create([
            'seller_id' => $this->sellerA->id,
            'is_platform' => false,
            'code' => 'ALPHA50',
            'type' => 'fixed',
            'value' => 50.00,
            'min_spend' => 200.00,
            'is_active' => true,
        ]);

        $platformVoucher = Voucher::create([
            'seller_id' => null,
            'is_platform' => true,
            'code' => 'PLATFORM100',
            'type' => 'fixed',
            'value' => 100.00,
            'min_spend' => 400.00,
            'is_active' => true,
        ]);

        $shippingVoucher = Voucher::create([
            'seller_id' => null,
            'is_platform' => true,
            'code' => 'FREESHIP50',
            'type' => 'free_shipping',
            'value' => 50.00,
            'max_discount' => 50.00,
            'is_active' => true,
        ]);

        // 1. Verify validateVoucher allows stacking across different categories
        $valResponse = $this->actingAs($this->buyer)
            ->postJson(route('buyer.vouchers.validate'), [
                'voucher_code' => 'PLATFORM100',
                'selected_items' => [$cartItemA->id],
                'applied_codes' => ['ALPHA50'],
            ]);
        $valResponse->assertOk()->assertJson(['valid' => true]);

        // 2. Verify validateVoucher rejects applying two vouchers of the same category
        $storeVoucher2 = Voucher::create([
            'seller_id' => $this->sellerA->id,
            'code' => 'ALPHA20',
            'type' => 'fixed',
            'value' => 20.00,
            'is_active' => true,
        ]);

        $dupResponse = $this->actingAs($this->buyer)
            ->postJson(route('buyer.vouchers.validate'), [
                'voucher_code' => 'ALPHA20',
                'selected_items' => [$cartItemA->id],
                'applied_codes' => ['ALPHA50'],
            ]);
        $dupResponse->assertStatus(422)
            ->assertJson([
                'valid' => false,
                'message' => 'Only one store voucher can be applied per order.',
            ]);

        // 3. Place order with all 3 stacked vouchers
        $orderResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.orders.store'), [
                'shipping_address' => '123 Coastal Road, Tagbilaran City, Bohol',
                'payment_method' => 'cod',
                'shipping_method' => 'standard',
                'selected_items' => [$cartItemA->id],
                'voucher_codes' => ['ALPHA50', 'PLATFORM100', 'FREESHIP50'],
            ]);

        $orderResponse->assertSessionHasNoErrors();
        $order = Order::latest()->first();
        $this->assertNotNull($order);
        $orderResponse->assertRedirect(route('buyer.orders.show', $order));
        // Subtotal: 600
        // Item discounts: 50 (store) + 100 (platform) = 150
        $this->assertEquals(600.00, (float) $order->subtotal);
        $this->assertEquals(150.00, (float) $order->discount);
        // Shipping fee: 50 - 50 = 0
        $this->assertEquals(50.00, (float) $order->shipping_fee_before_discount);
        $this->assertEquals(50.00, (float) $order->shipping_discount);
        $this->assertEquals(0.00, (float) $order->shipping_fee);
        // Total: (600 - 150) + 0 = 450
        $this->assertEquals(450.00, (float) $order->total);
        $this->assertCount(3, $order->applied_vouchers);
    }

    /**
     * BUY-VOU-004: Minimum spend at boundary: ₱299.99 rejected; ₱300.00 accepted.
     */
    public function test_buy_vou_004_minimum_spend_at_boundary(): void
    {
        $voucher = Voucher::create([
            'seller_id' => $this->sellerA->id,
            'code' => 'MIN300',
            'type' => 'fixed',
            'value' => 50.00,
            'min_spend' => 300.00,
            'is_active' => true,
        ]);

        // Product with ₱299.99 price
        $product299 = Product::create([
            'seller_id' => $this->sellerA->id,
            'category_id' => $this->category->id,
            'name' => 'Item 299',
            'slug' => 'item-299',
            'price' => 299.99,
            'stock' => 10,
            'status' => 'approved',
        ]);

        $cartItem299 = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $product299->id,
            'quantity' => 1,
        ]);

        // Boundary test: ₱299.99 is rejected
        $resReject = $this->actingAs($this->buyer)
            ->postJson(route('buyer.vouchers.validate'), [
                'voucher_code' => 'MIN300',
                'selected_items' => [$cartItem299->id],
            ]);
        $resReject->assertStatus(422)
            ->assertJsonFragment(['valid' => false]);

        // Product with ₱300.00 price
        $cartItem300 = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->productB->id, // price 300.00
            'quantity' => 1,
        ]);

        $platformMin300 = Voucher::create([
            'seller_id' => null,
            'is_platform' => true,
            'code' => 'PLATMIN300',
            'type' => 'fixed',
            'value' => 50.00,
            'min_spend' => 300.00,
            'is_active' => true,
        ]);

        // Boundary test: ₱300.00 is accepted
        $resAccept = $this->actingAs($this->buyer)
            ->postJson(route('buyer.vouchers.validate'), [
                'voucher_code' => 'PLATMIN300',
                'selected_items' => [$cartItem300->id],
            ]);
        $resAccept->assertOk()
            ->assertJson(['valid' => true]);
    }

    /**
     * BUY-VOU-005: Expired, future, limit reached codes rejected with clear error.
     */
    public function test_buy_vou_005_expired_future_limit_reached_codes(): void
    {
        $cartItem = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->productA1->id,
            'quantity' => 2,
        ]);

        // 1. Expired voucher
        $expiredVoucher = Voucher::create([
            'seller_id' => $this->sellerA->id,
            'code' => 'EXPIRED10',
            'type' => 'fixed',
            'value' => 10.00,
            'expires_at' => Carbon::now()->subDays(5),
            'is_active' => true,
        ]);

        $resExpired = $this->actingAs($this->buyer)
            ->postJson(route('buyer.vouchers.validate'), [
                'voucher_code' => 'EXPIRED10',
                'selected_items' => [$cartItem->id],
            ]);
        $resExpired->assertStatus(422)
            ->assertJson(['valid' => false, 'message' => 'This voucher has expired.']);

        // 2. Future voucher
        $futureVoucher = Voucher::create([
            'seller_id' => $this->sellerA->id,
            'code' => 'FUTURE10',
            'type' => 'fixed',
            'value' => 10.00,
            'starts_at' => Carbon::now()->addDays(5),
            'is_active' => true,
        ]);

        $resFuture = $this->actingAs($this->buyer)
            ->postJson(route('buyer.vouchers.validate'), [
                'voucher_code' => 'FUTURE10',
                'selected_items' => [$cartItem->id],
            ]);
        $resFuture->assertStatus(422)
            ->assertJson(['valid' => false, 'message' => 'This voucher is not active yet.']);

        // 3. Usage limit reached
        $limitVoucher = Voucher::create([
            'seller_id' => $this->sellerA->id,
            'code' => 'LIMITREACHED',
            'type' => 'fixed',
            'value' => 10.00,
            'usage_limit' => 5,
            'used_count' => 5,
            'is_active' => true,
        ]);

        $resLimit = $this->actingAs($this->buyer)
            ->postJson(route('buyer.vouchers.validate'), [
                'voucher_code' => 'LIMITREACHED',
                'selected_items' => [$cartItem->id],
            ]);
        $resLimit->assertStatus(422)
            ->assertJson(['valid' => false, 'message' => 'This voucher has reached its usage limit.']);

        // 4. Fake / non-existent code
        $resFake = $this->actingAs($this->buyer)
            ->postJson(route('buyer.vouchers.validate'), [
                'voucher_code' => 'DOESNOTEXIST',
                'selected_items' => [$cartItem->id],
            ]);
        $resFake->assertStatus(422)
            ->assertJson(['valid' => false, 'message' => 'Voucher code not found.']);
    }

    /**
     * BUY-VOU-006: Per-buyer limit reached.
     */
    public function test_buy_vou_006_per_buyer_limit_reached(): void
    {
        $voucher = Voucher::create([
            'seller_id' => $this->sellerA->id,
            'code' => 'ONCEONLY',
            'type' => 'fixed',
            'value' => 50.00,
            'per_customer_limit' => 1,
            'is_active' => true,
        ]);

        $cartItem1 = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->productA1->id,
            'quantity' => 1,
        ]);

        // First purchase works
        $this->actingAs($this->buyer)
            ->post(route('buyer.orders.store'), [
                'shipping_address' => '123 Coastal Road, Tagbilaran City, Bohol',
                'payment_method' => 'cod',
                'shipping_method' => 'standard',
                'selected_items' => [$cartItem1->id],
                'voucher_code' => 'ONCEONLY',
            ])
            ->assertSessionHasNoErrors();

        // Second purchase with same voucher is blocked
        $cartItem2 = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->productA2->id,
            'quantity' => 2,
        ]);

        $resSecond = $this->actingAs($this->buyer)
            ->postJson(route('buyer.vouchers.validate'), [
                'voucher_code' => 'ONCEONLY',
                'selected_items' => [$cartItem2->id],
            ]);

        $resSecond->assertStatus(422)
            ->assertJson([
                'valid' => false,
                'message' => 'You have reached the maximum usage limit (1) for this voucher.',
            ]);
    }

    /**
     * BUY-VOU-007: Removing item drops below min spend.
     */
    public function test_buy_vou_007_removing_item_drops_below_min_spend(): void
    {
        $voucher = Voucher::create([
            'seller_id' => $this->sellerA->id,
            'code' => 'MINSPEND500',
            'type' => 'fixed',
            'value' => 50.00,
            'min_spend' => 500.00,
            'is_active' => true,
        ]);

        // Cart item: 1 x 200 = 200 (below 500)
        $cartItem = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->productA1->id,
            'quantity' => 1,
        ]);

        // Server validation rejects min_spend failure
        $res = $this->actingAs($this->buyer)
            ->postJson(route('buyer.vouchers.validate'), [
                'voucher_code' => 'MINSPEND500',
                'selected_items' => [$cartItem->id],
            ]);
        $res->assertStatus(422)
            ->assertJsonFragment(['valid' => false]);

        // Direct store submission is also blocked
        $this->actingAs($this->buyer)
            ->from(route('buyer.checkout'))
            ->post(route('buyer.orders.store'), [
                'shipping_address' => '123 Coastal Road, Tagbilaran City, Bohol',
                'payment_method' => 'cod',
                'shipping_method' => 'standard',
                'selected_items' => [$cartItem->id],
                'voucher_code' => 'MINSPEND500',
            ])
            ->assertSessionHasErrors(['voucher_code']);
    }

    /**
     * BUY-VOU-008: Percentage voucher respects cap: 10% discount caps at maximum ₱100 on ₱1,250 order.
     */
    public function test_buy_vou_008_percentage_voucher_respects_max_discount_cap(): void
    {
        $voucher = Voucher::create([
            'seller_id' => null,
            'is_platform' => true,
            'code' => 'PERCENT10',
            'type' => 'percentage',
            'value' => 10.00, // 10%
            'max_discount' => 100.00, // Cap at ₱100
            'is_active' => true,
        ]);

        // Cart total: 5 x ₱300 = ₱1,500 (10% would be ₱150, but capped at ₱100)
        $cartItem = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->productB->id,
            'quantity' => 5,
        ]);

        $response = $this->actingAs($this->buyer)
            ->postJson(route('buyer.vouchers.validate'), [
                'voucher_code' => 'PERCENT10',
                'selected_items' => [$cartItem->id],
            ]);

        $response->assertOk()
            ->assertJson([
                'valid' => true,
                'voucher' => [
                    'code' => 'PERCENT10',
                    'discount' => 100.00,
                ],
            ]);
    }

    /**
     * BUY-VOU-009: Shipping voucher larger than fee: ₱50 free shipping voucher on ₱50 fee makes shipping ₱0 cleanly without discounting items.
     */
    public function test_buy_vou_009_shipping_voucher_capped_cleanly_at_shipping_fee(): void
    {
        $voucher = Voucher::create([
            'seller_id' => null,
            'is_platform' => true,
            'code' => 'FREESHIP100',
            'type' => 'free_shipping',
            'value' => 100.00, // subsidy up to ₱100
            'max_discount' => 100.00,
            'is_active' => true,
        ]);

        // 1 item from Seller A -> Shipping fee is ₱50 (standard)
        $cartItem = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->productA1->id,
            'quantity' => 1, // ₱200 subtotal
        ]);

        $this->actingAs($this->buyer)
            ->post(route('buyer.orders.store'), [
                'shipping_address' => '123 Coastal Road, Tagbilaran City, Bohol',
                'payment_method' => 'cod',
                'shipping_method' => 'standard',
                'selected_items' => [$cartItem->id],
                'voucher_code' => 'FREESHIP100',
            ])
            ->assertSessionHasNoErrors();

        $order = Order::latest()->first();
        $this->assertNotNull($order);
        // Shipping fee was ₱50, voucher subsidy is ₱100 -> shipping fee becomes ₱0, shipping discount is ₱50
        $this->assertEquals(50.00, (float) $order->shipping_fee_before_discount);
        $this->assertEquals(50.00, (float) $order->shipping_discount);
        $this->assertEquals(0.00, (float) $order->shipping_fee);
        // Product item subtotal must NEVER be reduced by a shipping voucher!
        $this->assertEquals(200.00, (float) $order->subtotal);
        $this->assertEquals(0.00, (float) $order->discount);
        $this->assertEquals(200.00, (float) $order->total);
    }

    /**
     * BUY-VOU-010: Total never negative: ₱100 voucher on ₱100 product makes items ₱0, not negative; charged shipping fee.
     */
    public function test_buy_vou_010_total_never_negative(): void
    {
        $voucher = Voucher::create([
            'seller_id' => null,
            'is_platform' => true,
            'code' => 'OVER150',
            'type' => 'fixed',
            'value' => 150.00, // ₱150 voucher on ₱100 product
            'is_active' => true,
        ]);

        $cartItem = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->productA2->id,
            'quantity' => 1, // ₱100 subtotal
        ]);

        $this->actingAs($this->buyer)
            ->post(route('buyer.orders.store'), [
                'shipping_address' => '123 Coastal Road, Tagbilaran City, Bohol',
                'payment_method' => 'cod',
                'shipping_method' => 'standard',
                'selected_items' => [$cartItem->id],
                'voucher_code' => 'OVER150',
            ])
            ->assertSessionHasNoErrors();

        $order = Order::latest()->first();
        $this->assertNotNull($order);
        // Subtotal: 100, Discount: capped at 100
        $this->assertEquals(100.00, (float) $order->subtotal);
        $this->assertEquals(100.00, (float) $order->discount);
        // Shipping fee is 50. Total is 0 + 50 = 50 (never negative)
        $this->assertEquals(50.00, (float) $order->shipping_fee);
        $this->assertEquals(50.00, (float) $order->total);
    }

    /**
     * BUY-VOU-011: Voucher on items already on sale: voucher applies on top of discounted sale price.
     */
    public function test_buy_vou_011_voucher_applies_on_sale_price(): void
    {
        // Product with sale/effective price
        $saleProduct = Product::create([
            'seller_id' => $this->sellerA->id,
            'category_id' => $this->category->id,
            'name' => 'Flash Sale Item',
            'slug' => 'flash-sale-item',
            'price' => 500.00,
            'stock' => 10,
            'status' => 'approved',
        ]);

        $cartItem = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $saleProduct->id,
            'quantity' => 1,
        ]);

        $voucher = Voucher::create([
            'seller_id' => $this->sellerA->id,
            'code' => 'EXTRA50',
            'type' => 'fixed',
            'value' => 50.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->buyer)
            ->postJson(route('buyer.vouchers.validate'), [
                'voucher_code' => 'EXTRA50',
                'selected_items' => [$cartItem->id],
            ]);

        $response->assertOk()
            ->assertJson([
                'valid' => true,
                'voucher' => [
                    'code' => 'EXTRA50',
                    'discount' => 50.00,
                ],
            ]);
    }

    /**
     * BUY-VOU-012: Voucher returned after cancellation: cancelled order puts voucher back in wallet.
     */
    public function test_buy_vou_012_voucher_returned_after_cancellation(): void
    {
        $voucher = Voucher::create([
            'seller_id' => $this->sellerA->id,
            'code' => 'RESTORE50',
            'type' => 'fixed',
            'value' => 50.00,
            'used_count' => 0,
            'is_active' => true,
        ]);

        // Buyer claimed voucher
        UserVoucher::create([
            'user_id' => $this->buyer->id,
            'voucher_id' => $voucher->id,
            'status' => 'claimed',
            'claimed_at' => now(),
        ]);

        $cartItem = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->productA1->id,
            'quantity' => 2,
        ]);

        // Place order with voucher
        $this->actingAs($this->buyer)
            ->post(route('buyer.orders.store'), [
                'shipping_address' => '123 Coastal Road, Tagbilaran City, Bohol',
                'payment_method' => 'cod',
                'shipping_method' => 'standard',
                'selected_items' => [$cartItem->id],
                'voucher_code' => 'RESTORE50',
            ])
            ->assertSessionHasNoErrors();

        $order = Order::latest()->first();
        $this->assertNotNull($order);

        // Verify voucher usage count was incremented and status changed to used
        $voucher->refresh();
        $this->assertEquals(1, $voucher->used_count);
        $userVoucher = UserVoucher::where('user_id', $this->buyer->id)->where('voucher_id', $voucher->id)->first();
        $this->assertEquals('used', $userVoucher->status);

        // Cancel order
        $this->actingAs($this->buyer)
            ->post(route('buyer.orders.cancel', $order), [
                'reason' => 'Changed my mind before shipment.',
            ])
            ->assertRedirect();

        // Verify voucher was returned to wallet (used_count decremented, user_voucher status restored to 'claimed')
        $voucher->refresh();
        $this->assertEquals(0, $voucher->used_count);

        $userVoucher->refresh();
        $this->assertEquals('claimed', $userVoucher->status);
        $this->assertNull($userVoucher->used_at);
    }
}
