<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\UserVoucher;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SellerPromotionsAndVouchersTest extends TestCase
{
    use RefreshDatabase;

    protected User $seller;
    protected User $buyer;
    protected Category $categoryA;
    protected Category $categoryB;
    protected Product $productA;
    protected Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Tech & Tools Hub',
        ]);

        $this->buyer = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
        ]);

        $this->categoryA = Category::create([
            'name' => 'Power Tools',
            'slug' => 'power-tools',
        ]);

        $this->categoryB = Category::create([
            'name' => 'Hand Tools',
            'slug' => 'hand-tools',
        ]);

        $this->productA = Product::create([
            'category_id' => $this->categoryA->id,
            'seller_id' => $this->seller->id,
            'name' => 'Heavy Duty Angle Grinder',
            'slug' => 'heavy-duty-angle-grinder',
            'price' => 2000.00,
            'stock' => 20,
            'status' => 'approved',
        ]);

        $this->productB = Product::create([
            'category_id' => $this->categoryB->id,
            'seller_id' => $this->seller->id,
            'name' => 'Steel Claw Hammer',
            'slug' => 'steel-claw-hammer',
            'price' => 500.00,
            'stock' => 50,
            'status' => 'approved',
        ]);
    }

    /**
     * SELLER-26: Percentage discount cap test.
     */
    public function test_seller_can_create_percentage_voucher_with_discount_cap(): void
    {
        $response = $this->actingAs($this->seller)
            ->post(route('seller.vouchers.store'), [
                'code' => 'CAP20',
                'type' => 'percentage',
                'value' => 20, // 20%
                'max_discount' => 150.00, // Capped at ₱150
                'target_type' => 'all',
                'min_spend' => 500.00,
                'per_customer_limit' => 1,
                'is_active' => true,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vouchers', [
            'seller_id' => $this->seller->id,
            'code' => 'CAP20',
            'type' => 'percentage',
            'value' => 20.00,
            'max_discount' => 150.00,
        ]);

        $voucher = Voucher::where('code', 'CAP20')->first();
        // 20% of ₱2,000 is ₱400, but cap is ₱150
        $discount = $voucher->discountFor(2000.00);
        $this->assertEquals(150.00, $discount);
    }

    /**
     * SELLER-27: Per-customer usage limit test.
     */
    public function test_per_customer_usage_limit_is_enforced(): void
    {
        $voucher = Voucher::create([
            'seller_id' => $this->seller->id,
            'code' => 'ONCEONLY',
            'type' => 'fixed',
            'value' => 100.00,
            'per_customer_limit' => 1,
            'target_type' => 'all',
            'is_active' => true,
        ]);

        // 1. Buyer claims voucher once successfully
        $claimResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.vouchers.claim', $voucher->id));
        $claimResponse->assertRedirect();
        $this->assertDatabaseHas('user_vouchers', [
            'user_id' => $this->buyer->id,
            'voucher_id' => $voucher->id,
            'status' => 'claimed',
        ]);

        // 2. Buyer attempts to claim second time -> rejected
        $secondClaim = $this->actingAs($this->buyer)
            ->post(route('buyer.vouchers.claim', $voucher->id));
        $secondClaim->assertSessionHasErrors('voucher');

        // 3. Buyer uses voucher in an order
        Order::create([
            'order_number' => 'ORD-TEST-001',
            'user_id' => $this->buyer->id,
            'voucher_id' => $voucher->id,
            'voucher_code' => $voucher->code,
            'subtotal' => 1000.00,
            'discount' => 100.00,
            'total' => 900.00,
            'shipping_fee' => 80.00,
            'status' => 'pending',
            'shipping_address' => 'Sample Address',
            'payment_method' => 'cod',
        ]);

        // 4. In validateVoucher, buyer trying to use it again is rejected
        $cartItem = $this->buyer->cartItems()->create([
            'product_id' => $this->productA->id,
            'quantity' => 1,
        ]);

        $validateResponse = $this->actingAs($this->buyer)
            ->postJson(route('buyer.vouchers.validate'), [
                'voucher_code' => 'ONCEONLY',
                'selected_items' => [$cartItem->id],
            ]);

        $validateResponse->assertStatus(422);
        $validateResponse->assertJsonFragment([
            'valid' => false,
        ]);
    }

    /**
     * SELLER-28: Product and category targeting test.
     */
    public function test_voucher_targets_specific_products_or_categories(): void
    {
        // Product targeted voucher: only applies to Product B
        $productVoucher = Voucher::create([
            'seller_id' => $this->seller->id,
            'code' => 'HAMMERDEAL',
            'type' => 'fixed',
            'value' => 50.00,
            'target_type' => 'product',
            'targeted_product_ids' => [$this->productB->id],
            'is_active' => true,
        ]);

        $cartA = $this->buyer->cartItems()->create([
            'product_id' => $this->productA->id, // Grinder
            'quantity' => 1,
        ]);

        $cartB = $this->buyer->cartItems()->create([
            'product_id' => $this->productB->id, // Hammer
            'quantity' => 1,
        ]);

        // Attempt on cart item A (Grinder) -> Should fail
        $resA = $this->actingAs($this->buyer)
            ->postJson(route('buyer.vouchers.validate'), [
                'voucher_code' => 'HAMMERDEAL',
                'selected_items' => [$cartA->id],
            ]);
        $resA->assertStatus(422);

        // Attempt on cart item B (Hammer) -> Should succeed
        $resB = $this->actingAs($this->buyer)
            ->postJson(route('buyer.vouchers.validate'), [
                'voucher_code' => 'HAMMERDEAL',
                'selected_items' => [$cartB->id],
            ]);
        $resB->assertStatus(200);
        $resB->assertJsonPath('valid', true);
        $resB->assertJsonPath('voucher.discount', 50);
    }

    /**
     * SELLER-29: Free shipping vouchers test.
     */
    public function test_seller_can_create_and_apply_free_shipping_voucher(): void
    {
        $freeShipVoucher = Voucher::create([
            'seller_id' => $this->seller->id,
            'code' => 'FREESHIP80',
            'type' => 'free_shipping',
            'value' => 100.00,
            'max_discount' => 80.00, // Capped at ₱80 shipping subsidy
            'target_type' => 'all',
            'is_active' => true,
        ]);

        // Subtotal ₱1,000, Shipping ₱120 -> Free shipping voucher capped at ₱80
        $discount = $freeShipVoucher->discountFor(1000.00, 120.00);
        $this->assertEquals(80.00, $discount);

        // Shipping ₱60 -> Covered fully (₱60)
        $discountFull = $freeShipVoucher->discountFor(1000.00, 60.00);
        $this->assertEquals(60.00, $discountFull);
    }

    /**
     * SELLER-30: Voucher analytics & revenue attribution test.
     */
    public function test_voucher_analytics_computes_real_revenue_attribution(): void
    {
        $voucher = Voucher::create([
            'seller_id' => $this->seller->id,
            'code' => 'ANALYTICS10',
            'type' => 'fixed',
            'value' => 100.00,
            'target_type' => 'all',
            'is_active' => true,
        ]);

        // Place 2 orders with this voucher
        Order::create([
            'order_number' => 'ORD-REV-001',
            'user_id' => $this->buyer->id,
            'voucher_id' => $voucher->id,
            'voucher_code' => $voucher->code,
            'subtotal' => 2000.00,
            'discount' => 100.00,
            'total' => 1980.00,
            'shipping_fee' => 80.00,
            'status' => 'processing',
            'shipping_address' => 'Sample Address',
            'payment_method' => 'cod',
        ]);

        Order::create([
            'order_number' => 'ORD-REV-002',
            'user_id' => $this->buyer->id,
            'voucher_id' => $voucher->id,
            'voucher_code' => $voucher->code,
            'subtotal' => 3000.00,
            'discount' => 100.00,
            'total' => 2980.00,
            'shipping_fee' => 80.00,
            'status' => 'delivered',
            'shipping_address' => 'Sample Address',
            'payment_method' => 'cod',
        ]);

        $response = $this->actingAs($this->seller)
            ->get(route('seller.vouchers'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seller/Vouchers')
            ->has('vouchers')
            ->has('analytics')
            ->where('analytics.total_vouchers', 1)
            ->where('analytics.total_revenue_generated', fn ($v) => (float) $v == 4960.00)
            ->where('analytics.total_discount_funded', fn ($v) => (float) $v == 200.00)
        );
    }

    /**
     * SELLER-31: Voucher expiration countdown & status check.
     */
    public function test_voucher_detects_expiring_soon_and_expired_states(): void
    {
        // Expiring in 24 hours
        $expiringVoucher = Voucher::create([
            'seller_id' => $this->seller->id,
            'code' => 'SOON24',
            'type' => 'fixed',
            'value' => 50.00,
            'target_type' => 'all',
            'expires_at' => now()->addHours(24)->toDateString(),
            'is_active' => true,
        ]);

        $this->assertTrue($expiringVoucher->isExpiringSoon());
        $this->assertTrue($expiringVoucher->isValidNow());

        // Already expired
        $expiredVoucher = Voucher::create([
            'seller_id' => $this->seller->id,
            'code' => 'OLDEXPIRED',
            'type' => 'fixed',
            'value' => 50.00,
            'target_type' => 'all',
            'expires_at' => now()->subDay()->toDateString(),
            'is_active' => true,
        ]);

        $this->assertFalse($expiredVoucher->isValidNow());
        $this->assertFalse($expiredVoucher->isExpiringSoon());
    }
}
