<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\UserVoucher;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerStoreVouchersTest extends TestCase
{
    use RefreshDatabase;

    protected User $seller1;
    protected User $seller2;
    protected User $buyer1;
    protected User $buyer2;
    protected Category $category;
    protected Product $p01;
    protected ProductVariant $p01NaturalM;
    protected Product $p02;
    protected Product $p03;
    protected Product $p06;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller1 = User::factory()->create([
            'name' => 'Seller One',
            'email' => 'seller1@example.com',
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Maria Bohol Crafts',
        ]);

        $this->seller2 = User::factory()->create([
            'name' => 'Seller Two',
            'email' => 'seller2@example.com',
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Rico Native Delicacies',
        ]);

        $this->buyer1 = User::factory()->create([
            'name' => 'Buyer One',
            'email' => 'buyer1@example.com',
            'usertype' => 'buyer',
            'status' => 'approved',
        ]);

        $this->buyer2 = User::factory()->create([
            'name' => 'Buyer Two',
            'email' => 'buyer2@example.com',
            'usertype' => 'buyer',
            'status' => 'approved',
        ]);

        $this->category = Category::create([
            'name' => 'Handicrafts',
            'slug' => 'handicrafts',
            'is_active' => true,
        ]);

        // P-01: Handwoven Abaca Tote with variant Natural-M (₱249.00)
        $this->p01 = Product::create([
            'seller_id' => $this->seller1->id,
            'category_id' => $this->category->id,
            'name' => 'Handwoven Abaca Tote',
            'slug' => 'handwoven-abaca-tote',
            'description' => 'Authentic handwoven abaca tote bag from Bohol.',
            'price' => 249.00,
            'stock' => 25,
            'status' => 'approved',
        ]);

        $this->p01NaturalM = ProductVariant::create([
            'product_id' => $this->p01->id,
            'color' => 'Natural',
            'size' => 'M',
            'price' => 249.00,
            'stock' => 25,
            'sku' => 'P01-NAT-M',
        ]);

        // P-02: Raffia Sun Hat (₱450.00)
        $this->p02 = Product::create([
            'seller_id' => $this->seller1->id,
            'category_id' => $this->category->id,
            'name' => 'Raffia Sun Hat',
            'slug' => 'raffia-sun-hat',
            'description' => 'Handcrafted raffia sun hat from Bohol weavers.',
            'price' => 450.00,
            'stock' => 20,
            'status' => 'approved',
        ]);

        // P-03: Buri Basket Set (₱1,250.00)
        $this->p03 = Product::create([
            'seller_id' => $this->seller1->id,
            'category_id' => $this->category->id,
            'name' => 'Buri Basket Set',
            'slug' => 'buri-basket-set',
            'description' => 'Handcrafted buri basket set of 3.',
            'price' => 1250.00,
            'stock' => 15,
            'status' => 'approved',
        ]);

        // P-06: Seller 2 product (₱1,499.00)
        $this->p06 = Product::create([
            'seller_id' => $this->seller2->id,
            'category_id' => $this->category->id,
            'name' => 'Calamay Gift Box Deluxe',
            'slug' => 'calamay-gift-box-deluxe',
            'description' => 'Authentic Bohol calamay gift box deluxe set.',
            'price' => 1499.00,
            'stock' => 20,
            'status' => 'approved',
        ]);
    }

    /**
     * SEL-VOU-001: Create a fixed-amount claimable voucher
     */
    public function test_sel_vou_001_create_fixed_amount_claimable_voucher(): void
    {
        $response = $this->actingAs($this->seller1)->post(route('seller.vouchers.store'), [
            'code' => 'MCH50',
            'type' => 'fixed',
            'value' => 50,
            'min_spend' => 300,
            'usage_limit' => 100,
            'per_customer_limit' => 1,
            'starts_at' => now()->startOfMonth()->toDateString(),
            'expires_at' => now()->endOfMonth()->toDateString(),
            'target_type' => 'all',
            'display_mode' => 'claimable',
            'is_active' => true,
        ]);

        $response->assertSessionHasNoErrors();

        $voucher = Voucher::where('code', 'MCH50')->firstOrFail();
        $this->assertEquals('Ongoing', $voucher->status_label);
        $this->assertEquals('Claim ₱50 off, min. spend ₱300', $voucher->claim_label);

        // Open Seller 1's store page
        $storeRes = $this->actingAs($this->buyer1)->get(route('buyer.store.show', $this->seller1));
        $storeRes->assertOk();
        $storeVouchers = $storeRes->viewData('page')['props']['vouchers'];
        $this->assertCount(1, $storeVouchers);
        $this->assertEquals('MCH50', $storeVouchers[0]['code']);
        $this->assertEquals('Claim ₱50 off, min. spend ₱300', $storeVouchers[0]['claim_label']);

        // Open P-02's product page
        $prodRes = $this->actingAs($this->buyer1)->get(route('buyer.product', $this->p02));
        $prodRes->assertOk();
        $prodVouchers = $prodRes->viewData('page')['props']['vouchers'];
        $this->assertCount(1, $prodVouchers);
        $this->assertEquals('MCH50', $prodVouchers[0]['code']);
        $this->assertEquals('Claim ₱50 off, min. spend ₱300', $prodVouchers[0]['claim_label']);
    }

    /**
     * SEL-VOU-002: Create a percentage voucher with a cap
     */
    public function test_sel_vou_002_create_percentage_voucher_with_cap(): void
    {
        $this->actingAs($this->seller1)->post(route('seller.vouchers.store'), [
            'code' => 'MCH10',
            'type' => 'percentage',
            'value' => 10,
            'max_discount' => 100,
            'min_spend' => 500,
            'usage_limit' => 100,
            'per_customer_limit' => 5,
            'starts_at' => now()->startOfMonth()->toDateString(),
            'expires_at' => now()->endOfMonth()->toDateString(),
            'target_type' => 'all',
            'display_mode' => 'claimable',
        ])->assertSessionHasNoErrors();

        $voucher = Voucher::where('code', 'MCH10')->firstOrFail();

        // Buyer 1 claims MCH10
        $this->actingAs($this->buyer1)->post(route('buyer.vouchers.claim', $voucher))
            ->assertSessionHasNoErrors();

        // Check out ₱600 of Seller 1 items -> gets exactly ₱60.00 off
        $item600 = Product::create([
            'seller_id' => $this->seller1->id,
            'category_id' => $this->category->id,
            'name' => 'Woven Table Runner Set',
            'slug' => 'woven-table-runner-set',
            'description' => 'Handwoven table runner set of 2.',
            'price' => 600.00,
            'stock' => 10,
            'status' => 'approved',
        ]);

        $cart600 = CartItem::create([
            'user_id' => $this->buyer1->id,
            'product_id' => $item600->id,
            'quantity' => 1,
        ]);

        $val600 = $this->actingAs($this->buyer1)->postJson(route('buyer.vouchers.validate'), [
            'voucher_code' => 'MCH10',
            'selected_items' => [$cart600->id],
        ]);
        $val600->assertOk()->assertJsonPath('voucher.discount', 60);

        // Check out ₱1,250 (P-03) -> gets exactly ₱100.00 off (not ₱125.00)
        $cart1250 = CartItem::create([
            'user_id' => $this->buyer1->id,
            'product_id' => $this->p03->id,
            'quantity' => 1,
        ]);

        $val1250 = $this->actingAs($this->buyer1)->postJson(route('buyer.vouchers.validate'), [
            'voucher_code' => 'MCH10',
            'selected_items' => [$cart1250->id],
        ]);
        $val1250->assertOk()->assertJsonPath('voucher.discount', 100);
    }

    /**
     * SEL-VOU-003: Code-only voucher is hidden
     */
    public function test_sel_vou_003_code_only_voucher_is_hidden_but_applies_case_insensitively(): void
    {
        $this->actingAs($this->seller2)->post(route('seller.vouchers.store'), [
            'code' => 'RVSECRET',
            'type' => 'fixed',
            'value' => 100,
            'min_spend' => 500,
            'usage_limit' => 50,
            'per_customer_limit' => 1,
            'starts_at' => now()->startOfMonth()->toDateString(),
            'expires_at' => now()->endOfMonth()->toDateString(),
            'target_type' => 'all',
            'display_mode' => 'code_only',
        ])->assertSessionHasNoErrors();

        // Not listed on Seller 2's store page
        $storeRes = $this->actingAs($this->buyer1)->get(route('buyer.store.show', $this->seller2));
        $storeRes->assertOk();
        $this->assertCount(0, $storeRes->viewData('page')['props']['vouchers']);

        // Not listed on P-06's product page
        $prodRes = $this->actingAs($this->buyer1)->get(route('buyer.product', $this->p06));
        $prodRes->assertOk();
        $this->assertCount(0, $prodRes->viewData('page')['props']['vouchers']);

        // Not listed on buyer voucher browse page
        $browseRes = $this->actingAs($this->buyer1)->get(route('buyer.vouchers', ['tab' => 'browse']));
        $browseRes->assertOk();
        $this->assertCount(0, $browseRes->viewData('page')['props']['browse_vouchers']['data']);

        // Typing 'rvsecret' in lowercase at checkout applies ₱100.00 off
        $cartItem = CartItem::create([
            'user_id' => $this->buyer1->id,
            'product_id' => $this->p06->id,
            'quantity' => 1,
        ]);

        $valRes = $this->actingAs($this->buyer1)->postJson(route('buyer.vouchers.validate'), [
            'voucher_code' => 'rvsecret',
            'selected_items' => [$cartItem->id],
        ]);
        $valRes->assertOk()->assertJsonPath('voucher.discount', 100);
    }

    /**
     * SEL-VOU-004: Voucher code format (4 to 12 alphanumeric, no spaces/symbols, saved uppercase)
     */
    public function test_sel_vou_004_voucher_code_format_boundaries(): void
    {
        $base = [
            'type' => 'fixed',
            'value' => 50,
            'min_spend' => 200,
            'usage_limit' => 50,
            'per_customer_limit' => 1,
            'target_type' => 'all',
        ];

        // Reject: ABC (3), ABCDEFGHIJKLM (13), MCH 50 (space), MCH-50 (symbol)
        foreach (['ABC', 'ABCDEFGHIJKLM', 'MCH 50', 'MCH-50'] as $invalidCode) {
            $res = $this->actingAs($this->seller1)->post(
                route('seller.vouchers.store'),
                array_merge($base, ['code' => $invalidCode])
            );
            $res->assertSessionHasErrors('code');
        }

        // Accept: ABCD (4), ABCDEFGHIJKL (12), mch75 (saved as MCH75)
        foreach (['ABCD' => 'ABCD', 'ABCDEFGHIJKL' => 'ABCDEFGHIJKL', 'mch75' => 'MCH75'] as $input => $expected) {
            $res = $this->actingAs($this->seller1)->post(
                route('seller.vouchers.store'),
                array_merge($base, ['code' => $input])
            );
            $res->assertSessionHasNoErrors();
            $this->assertDatabaseHas('vouchers', ['code' => $expected]);
        }
    }

    /**
     * SEL-VOU-005: Duplicate voucher code (across seller and platform)
     */
    public function test_sel_vou_005_duplicate_voucher_code_rejected(): void
    {
        // MCH50 exists (Seller 1)
        Voucher::create([
            'seller_id' => $this->seller1->id,
            'code' => 'MCH50',
            'type' => 'fixed',
            'value' => 50,
            'min_spend' => 300,
            'is_active' => true,
        ]);

        // PLAT100 exists (Platform)
        Voucher::create([
            'seller_id' => null,
            'is_platform' => true,
            'code' => 'PLAT100',
            'type' => 'fixed',
            'value' => 100,
            'min_spend' => 500,
            'is_active' => true,
        ]);

        foreach (['MCH50', 'mch50', 'PLAT100'] as $dupCode) {
            $res = $this->actingAs($this->seller1)->post(route('seller.vouchers.store'), [
                'code' => $dupCode,
                'type' => 'fixed',
                'value' => 50,
                'min_spend' => 300,
                'usage_limit' => 50,
                'per_customer_limit' => 1,
                'target_type' => 'all',
            ]);
            $res->assertSessionHasErrors('code');
        }
    }

    /**
     * SEL-VOU-006: Discount larger than the minimum spend
     */
    public function test_sel_vou_006_discount_larger_than_min_spend(): void
    {
        // 1. ₱500 off with ₱100 min spend -> blocked
        $resBlocked = $this->actingAs($this->seller1)->post(route('seller.vouchers.store'), [
            'code' => 'BIG500',
            'type' => 'fixed',
            'value' => 500,
            'min_spend' => 100,
            'usage_limit' => 50,
            'per_customer_limit' => 1,
            'target_type' => 'all',
        ]);
        $resBlocked->assertSessionHasErrors('value');
        $this->assertStringContainsString(
            'discount cannot exceed the minimum spend',
            strtolower(session('errors')->first('value'))
        );

        // 2. ₱100 off with ₱100 min spend -> accepted
        $resAccepted = $this->actingAs($this->seller1)->post(route('seller.vouchers.store'), [
            'code' => 'EQUAL100',
            'type' => 'fixed',
            'value' => 100,
            'min_spend' => 100,
            'usage_limit' => 50,
            'per_customer_limit' => 1,
            'target_type' => 'all',
        ]);
        $resAccepted->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vouchers', ['code' => 'EQUAL100']);
    }

    /**
     * SEL-VOU-007: Field validation
     */
    public function test_sel_vou_007_field_validation(): void
    {
        $validBase = [
            'code' => 'VALID01',
            'type' => 'fixed',
            'value' => 50,
            'min_spend' => 200,
            'usage_limit' => 10,
            'per_customer_limit' => 1,
            'starts_at' => '2026-10-20',
            'expires_at' => '2026-10-30',
            'target_type' => 'all',
        ];

        // 1. Amount ₱0 (reject)
        $this->actingAs($this->seller1)->post(
            route('seller.vouchers.store'),
            array_merge($validBase, ['value' => 0])
        )->assertSessionHasErrors('value');

        // 2. Percentage 0% (reject)
        $this->actingAs($this->seller1)->post(
            route('seller.vouchers.store'),
            array_merge($validBase, ['type' => 'percentage', 'value' => 0])
        )->assertSessionHasErrors('value');

        // 3. Percentage 100% (reject)
        $this->actingAs($this->seller1)->post(
            route('seller.vouchers.store'),
            array_merge($validBase, ['type' => 'percentage', 'value' => 100])
        )->assertSessionHasErrors('value');

        // 4. Cap ₱0 on a % voucher (reject)
        $this->actingAs($this->seller1)->post(
            route('seller.vouchers.store'),
            array_merge($validBase, ['type' => 'percentage', 'value' => 10, 'max_discount' => 0])
        )->assertSessionHasErrors('max_discount');

        // 5. Total usage 0 (reject)
        $this->actingAs($this->seller1)->post(
            route('seller.vouchers.store'),
            array_merge($validBase, ['usage_limit' => 0])
        )->assertSessionHasErrors('usage_limit');

        // 6. Per buyer 2 with total usage 1 (reject)
        $this->actingAs($this->seller1)->post(
            route('seller.vouchers.store'),
            array_merge($validBase, ['usage_limit' => 1, 'per_customer_limit' => 2])
        )->assertSessionHasErrors('per_customer_limit');

        // 7. End before start (reject)
        $this->actingAs($this->seller1)->post(
            route('seller.vouchers.store'),
            array_merge($validBase, ['starts_at' => '2026-10-25', 'expires_at' => '2026-10-20'])
        )->assertSessionHasErrors('expires_at');

        $this->assertEquals(0, Voucher::count());
    }

    /**
     * SEL-VOU-008: Voucher for selected products only
     */
    public function test_sel_vou_008_voucher_for_selected_products_only(): void
    {
        $this->actingAs($this->seller1)->post(route('seller.vouchers.store'), [
            'code' => 'MCHTOTE',
            'type' => 'fixed',
            'value' => 30,
            'min_spend' => 100,
            'usage_limit' => 50,
            'per_customer_limit' => 5,
            'target_type' => 'product',
            'targeted_product_ids' => [$this->p01->id],
        ])->assertSessionHasNoErrors();

        $cartP01 = CartItem::create([
            'user_id' => $this->buyer1->id,
            'product_id' => $this->p01->id,
            'product_variant_id' => $this->p01NaturalM->id,
            'quantity' => 1,
        ]);

        $cartP02 = CartItem::create([
            'user_id' => $this->buyer1->id,
            'product_id' => $this->p02->id,
            'quantity' => 1,
        ]);

        // 1. P-02 alone -> not applicable
        $resP02 = $this->actingAs($this->buyer1)->postJson(route('buyer.vouchers.validate'), [
            'voucher_code' => 'MCHTOTE',
            'selected_items' => [$cartP02->id],
        ]);
        $resP02->assertStatus(422)->assertJsonPath('valid', false);

        // 2. P-01 Natural-M alone -> ₱30 off
        $resP01 = $this->actingAs($this->buyer1)->postJson(route('buyer.vouchers.validate'), [
            'voucher_code' => 'MCHTOTE',
            'selected_items' => [$cartP01->id],
        ]);
        $resP01->assertOk()->assertJsonPath('voucher.discount', 30);

        // 3. P-01 + P-02 together -> ₱30 off, and minimum spend counts only P-01 amount
        $resBoth = $this->actingAs($this->buyer1)->postJson(route('buyer.vouchers.validate'), [
            'voucher_code' => 'MCHTOTE',
            'selected_items' => [$cartP01->id, $cartP02->id],
        ]);
        $resBoth->assertOk()->assertJsonPath('voucher.discount', 30);

        // Verify min spend counts only P-01 amount: if P-01 is ₱80 (< ₱100 min spend) while P-02 is ₱450
        $this->p01NaturalM->update(['price' => 80.00]);
        $resMinSpend = $this->actingAs($this->buyer1)->postJson(route('buyer.vouchers.validate'), [
            'voucher_code' => 'MCHTOTE',
            'selected_items' => [$cartP01->id, $cartP02->id],
        ]);
        $resMinSpend->assertStatus(422)->assertJsonPath('valid', false);
    }

    /**
     * SEL-VOU-009: Edit a voucher buyers already claimed
     */
    public function test_sel_vou_009_edit_voucher_buyers_already_claimed(): void
    {
        $origExpiry = now()->addDays(10)->toDateString();
        $voucher = Voucher::create([
            'seller_id' => $this->seller1->id,
            'code' => 'MCH50',
            'type' => 'fixed',
            'value' => 50.00,
            'min_spend' => 300.00,
            'usage_limit' => 100,
            'per_customer_limit' => 1,
            'starts_at' => now()->subDay()->toDateString(),
            'expires_at' => $origExpiry,
            'target_type' => 'all',
            'is_active' => true,
        ]);

        // Buyer 1 claims MCH50
        $this->actingAs($this->buyer1)->post(route('buyer.vouchers.claim', $voucher))
            ->assertSessionHasNoErrors();

        // 1. Try to change minimum spend to ₱500 -> locked
        $this->actingAs($this->seller1)->patch(route('seller.vouchers.update', $voucher), [
            'min_spend' => 500,
        ])->assertSessionHasErrors('min_spend');

        // 2. Try to change amount to ₱20 -> locked
        $this->actingAs($this->seller1)->patch(route('seller.vouchers.update', $voucher), [
            'value' => 20,
        ])->assertSessionHasErrors('value');

        $this->assertEquals(300.00, (float) $voucher->fresh()->min_spend);
        $this->assertEquals(50.00, (float) $voucher->fresh()->value);

        // 3 & 4. Extend end date by 7 days and raise total usage to 150 -> saves
        $newExpiry = now()->addDays(17)->toDateString();
        $this->actingAs($this->seller1)->patch(route('seller.vouchers.update', $voucher), [
            'expires_at' => $newExpiry,
            'usage_limit' => 150,
        ])->assertSessionHasNoErrors();

        $freshVoucher = $voucher->fresh();
        $this->assertEquals($newExpiry, $freshVoucher->expires_at->toDateString());
        $this->assertEquals(150, $freshVoucher->usage_limit);

        // Buyer 1's wallet shows the new end date
        $walletRes = $this->actingAs($this->buyer1)->get(route('buyer.vouchers', ['tab' => 'claimed']));
        $walletRes->assertOk();
        $walletItems = $walletRes->viewData('page')['props']['my_vouchers']['data'];
        $this->assertCount(1, $walletItems);
        $this->assertStringStartsWith($newExpiry, $walletItems[0]['voucher']['expires_at']);
    }

    /**
     * SEL-VOU-010: End a voucher buyers already claimed
     */
    public function test_sel_vou_010_end_voucher_buyers_already_claimed(): void
    {
        $voucher = Voucher::create([
            'seller_id' => $this->seller1->id,
            'code' => 'MCH50',
            'type' => 'fixed',
            'value' => 50.00,
            'min_spend' => 300.00,
            'usage_limit' => 100,
            'per_customer_limit' => 1,
            'starts_at' => now()->subDay()->toDateString(),
            'expires_at' => now()->addDays(10)->toDateString(),
            'target_type' => 'all',
            'is_active' => true,
        ]);

        // Buyer 1 claims MCH50 (unused)
        $this->actingAs($this->buyer1)->post(route('buyer.vouchers.claim', $voucher))
            ->assertSessionHasNoErrors();

        // Buyer 2 claims and uses MCH50 on a To Ship order
        $this->actingAs($this->buyer2)->post(route('buyer.vouchers.claim', $voucher))
            ->assertSessionHasNoErrors();

        $cartBuyer2 = CartItem::create([
            'user_id' => $this->buyer2->id,
            'product_id' => $this->p02->id,
            'quantity' => 1,
        ]);

        $this->actingAs($this->buyer2)->post(route('buyer.orders.store'), [
            'selected_items' => [$cartBuyer2->id],
            'shipping_address' => 'Tagbilaran City, Bohol',
            'shipping_method' => 'standard',
            'payment_method' => 'cod',
            'voucher_code' => 'MCH50',
        ])->assertSessionHasNoErrors();

        $buyer2Order = Order::where('user_id', $this->buyer2->id)->firstOrFail();
        $this->assertEquals(50.00, (float) $buyer2Order->discount);

        // Seller 1 ends MCH50 now
        $this->actingAs($this->seller1)->post(route('seller.vouchers.end', $voucher))
            ->assertSessionHasNoErrors();

        $this->assertEquals('Ended', $voucher->fresh()->status_label);

        // Buyer 1's wallet no longer offers MCH50
        $walletRes = $this->actingAs($this->buyer1)->get(route('buyer.vouchers', ['tab' => 'claimed']));
        $walletRes->assertOk();
        $this->assertCount(0, $walletRes->viewData('page')['props']['my_vouchers']['data']);

        // Checkout cannot apply MCH50
        $cartBuyer1 = CartItem::create([
            'user_id' => $this->buyer1->id,
            'product_id' => $this->p02->id,
            'quantity' => 1,
        ]);

        $valRes = $this->actingAs($this->buyer1)->postJson(route('buyer.vouchers.validate'), [
            'voucher_code' => 'MCH50',
            'selected_items' => [$cartBuyer1->id],
        ]);
        $valRes->assertStatus(422)->assertJsonPath('valid', false);

        // Buyer 2's existing order still shows ₱50.00 off
        $this->assertEquals(50.00, (float) $buyer2Order->fresh()->discount);
    }

    /**
     * SEL-VOU-011: Usage counters (increment on order, decrement on cancellation)
     */
    public function test_sel_vou_011_usage_counters_on_order_and_cancellation(): void
    {
        $voucher = Voucher::create([
            'seller_id' => $this->seller1->id,
            'code' => 'MCH50',
            'type' => 'fixed',
            'value' => 50.00,
            'min_spend' => 300.00,
            'usage_limit' => 100,
            'per_customer_limit' => 1,
            'starts_at' => now()->subDay()->toDateString(),
            'expires_at' => now()->addDays(10)->toDateString(),
            'target_type' => 'all',
            'is_active' => true,
        ]);

        $this->actingAs($this->buyer1)->post(route('buyer.vouchers.claim', $voucher))
            ->assertSessionHasNoErrors();

        $cartItem = CartItem::create([
            'user_id' => $this->buyer1->id,
            'product_id' => $this->p02->id,
            'quantity' => 1,
        ]);

        // 1. Buyer 1 uses MCH50 on an order -> usage shows 1 of 100
        $this->actingAs($this->buyer1)->post(route('buyer.orders.store'), [
            'selected_items' => [$cartItem->id],
            'shipping_address' => 'Tagbilaran City, Bohol',
            'shipping_method' => 'standard',
            'payment_method' => 'cod',
            'voucher_code' => 'MCH50',
        ])->assertSessionHasNoErrors();

        $this->assertEquals(1, $voucher->fresh()->used_count);
        $this->assertEquals(100, $voucher->fresh()->usage_limit);

        // 2. Buyer 1 cancels the order -> usage shows 0 of 100 and voucher returns to wallet
        $order = Order::where('user_id', $this->buyer1->id)->firstOrFail();
        $this->actingAs($this->buyer1)->post(route('buyer.orders.cancel', $order), [
            'reason' => 'Changed my mind about the order',
        ])->assertSessionHasNoErrors();

        $this->assertEquals(0, $voucher->fresh()->used_count);
        $this->assertDatabaseHas('user_vouchers', [
            'user_id' => $this->buyer1->id,
            'voucher_id' => $voucher->id,
            'status' => 'claimed',
        ]);
    }
}

