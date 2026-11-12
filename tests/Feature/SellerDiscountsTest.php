<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SellerDiscountsTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private User $buyer1;
    private User $buyer2;
    private Category $category;
    private Product $p01;
    private Product $p02;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Maria Handwoven Crafts',
        ]);

        $this->buyer1 = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
        ]);

        $this->buyer2 = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
        ]);

        $this->category = Category::create([
            'name' => 'Handicrafts',
            'slug' => 'handicrafts',
            'is_active' => true,
        ]);

        // P-01: Product with variants (S ₱199, M ₱249, L ₱299)
        $this->p01 = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'name' => 'Abaca Tote Bag (P-01)',
            'slug' => 'abaca-tote-bag-p01',
            'description' => 'Handwoven abaca tote bag in multiple sizes.',
            'price' => 199.00,
            'stock' => 23,
            'status' => 'approved',
        ]);

        ProductVariant::create([
            'product_id' => $this->p01->id,
            'color' => 'Natural',
            'size' => 'S',
            'price' => 199.00,
            'stock' => 10,
            'sku' => 'P01-NAT-S',
        ]);

        ProductVariant::create([
            'product_id' => $this->p01->id,
            'color' => 'Natural',
            'size' => 'M',
            'price' => 249.00,
            'stock' => 8,
            'sku' => 'P01-NAT-M',
        ]);

        ProductVariant::create([
            'product_id' => $this->p01->id,
            'color' => 'Natural',
            'size' => 'L',
            'price' => 299.00,
            'stock' => 5,
            'sku' => 'P01-NAT-L',
        ]);

        // P-02: Single-price product at ₱450.00
        $this->p02 = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'name' => 'Rattan Storage Basket (P-02)',
            'slug' => 'rattan-storage-basket-p02',
            'description' => 'Handcrafted rattan storage basket from Bohol.',
            'price' => 450.00,
            'stock' => 20,
            'status' => 'approved',
        ]);
    }

    /**
     * SEL-DISC-001: Create a scheduled percentage sale
     */
    public function test_sel_disc_001_create_scheduled_percentage_sale(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));

        $startTime = now()->addMinutes(10);
        $endTime = now()->addHours(2);

        $response = $this->actingAs($this->seller)->post(route('seller.sales.store'), [
            'product_id' => $this->p02->id,
            'type' => 'percentage',
            'value' => 20,
            'starts_at' => $startTime->toDateTimeString(),
            'ends_at' => $endTime->toDateTimeString(),
        ]);

        $response->assertSessionHasNoErrors();

        $sale = ProductSale::where('product_id', $this->p02->id)->firstOrFail();
        $this->assertEquals('Upcoming', $sale->status_label);
        $this->assertEquals('360.00', $sale->sale_price);
        $this->assertEquals($startTime->timestamp, $sale->starts_at->timestamp);
        $this->assertEquals($endTime->timestamp, $sale->ends_at->timestamp);

        // Before start time, buyers see ₱450.00 and no badge
        $freshP02 = $this->p02->fresh();
        $this->assertEquals('450.00', $freshP02->price);
        $this->assertNull($freshP02->old_price);
        $this->assertNull($freshP02->discount_badge);
        $this->assertFalse($freshP02->has_active_sale);

        Carbon::setTestNow();
    }

    /**
     * SEL-DISC-002: Sale goes live at the exact start time
     */
    public function test_sel_disc_002_sale_goes_live_at_exact_start_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));

        $startTime = now()->addMinutes(10); // 10:10:00
        $endTime = now()->addHours(2);      // 12:00:00

        $sale = ProductSale::create([
            'seller_id' => $this->seller->id,
            'product_id' => $this->p02->id,
            'type' => 'percentage',
            'value' => 20,
            'starts_at' => $startTime,
            'ends_at' => $endTime,
        ]);

        // 1 minute before start time (10:09:00) -> ₱450.00
        Carbon::setTestNow($startTime->copy()->subMinute());
        $beforeP02 = $this->p02->fresh();
        $this->assertEquals('Upcoming', $sale->fresh()->status_label);
        $this->assertEquals('450.00', $beforeP02->price);
        $this->assertNull($beforeP02->old_price);
        $this->assertNull($beforeP02->discount_badge);

        // 1 minute after start time (10:11:00) -> ₱360.00 with ₱450.00 struck through and −20% badge
        Carbon::setTestNow($startTime->copy()->addMinute());
        $afterP02 = $this->p02->fresh();
        $this->assertEquals('Ongoing', $sale->fresh()->status_label);
        $this->assertEquals('360.00', $afterP02->price);
        $this->assertEquals('450.00', $afterP02->old_price);
        $this->assertEquals('−20%', $afterP02->discount_badge);

        // Add P-02 to cart and open checkout -> shows ₱360.00
        $this->actingAs($this->buyer1)->post(route('buyer.cart.store', $this->p02), [
            'quantity' => 1,
        ])->assertSessionHasNoErrors();

        $cartItem = CartItem::where('user_id', $this->buyer1->id)->firstOrFail();

        $checkoutRes = $this->actingAs($this->buyer1)->get(route('buyer.checkout', [
            'selected_items' => [$cartItem->id],
        ]));
        $checkoutRes->assertOk();
        $checkoutProps = $checkoutRes->viewData('page')['props'];
        $this->assertEquals('360.00', $checkoutProps['items'][0]['product']['price']);

        Carbon::setTestNow();
    }

    /**
     * SEL-DISC-003: Sale ends at the exact end time
     */
    public function test_sel_disc_003_sale_ends_at_exact_end_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));

        $startTime = now()->subMinutes(30); // 09:30:00
        $endTime = now()->addMinutes(30);   // 10:30:00

        $sale = ProductSale::create([
            'seller_id' => $this->seller->id,
            'product_id' => $this->p02->id,
            'type' => 'percentage',
            'value' => 20,
            'starts_at' => $startTime,
            'ends_at' => $endTime,
        ]);

        $cartItem = CartItem::create([
            'user_id' => $this->buyer1->id,
            'product_id' => $this->p02->id,
            'quantity' => 1,
        ]);

        // 1 minute before end time (10:29:00) -> ₱360.00
        Carbon::setTestNow($endTime->copy()->subMinute());
        $this->assertEquals('Ongoing', $sale->fresh()->status_label);
        $this->assertEquals('360.00', $this->p02->fresh()->price);

        // 1 minute after end time (10:31:00) -> ₱450.00 everywhere, no badge, no old_price
        Carbon::setTestNow($endTime->copy()->addMinute());
        $endedP02 = $this->p02->fresh();
        $this->assertEquals('Ended', $sale->fresh()->status_label);
        $this->assertEquals('450.00', $endedP02->price);
        $this->assertNull($endedP02->old_price);
        $this->assertNull($endedP02->discount_badge);

        $checkoutRes = $this->actingAs($this->buyer1)->get(route('buyer.checkout', [
            'selected_items' => [$cartItem->id],
        ]));
        $checkoutRes->assertOk();
        $checkoutProps = $checkoutRes->viewData('page')['props'];
        $this->assertEquals('450.00', $checkoutProps['items'][0]['product']['price']);

        Carbon::setTestNow();
    }

    /**
     * SEL-DISC-004: Invalid sale dates
     */
    public function test_sel_disc_004_invalid_sale_dates_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));

        // 1. End before start -> reject
        $resEndBeforeStart = $this->actingAs($this->seller)->post(route('seller.sales.store'), [
            'product_id' => $this->p02->id,
            'type' => 'percentage',
            'value' => 20,
            'starts_at' => now()->addHours(2)->toDateTimeString(),
            'ends_at' => now()->addHour()->toDateTimeString(),
        ]);
        $resEndBeforeStart->assertSessionHasErrors('ends_at');

        // 2. Start = end -> reject
        $sameTime = now()->addHour()->toDateTimeString();
        $resSame = $this->actingAs($this->seller)->post(route('seller.sales.store'), [
            'product_id' => $this->p02->id,
            'type' => 'percentage',
            'value' => 20,
            'starts_at' => $sameTime,
            'ends_at' => $sameTime,
        ]);
        $resSame->assertSessionHasErrors('ends_at');

        // 3. Start in the past -> reject
        $resPast = $this->actingAs($this->seller)->post(route('seller.sales.store'), [
            'product_id' => $this->p02->id,
            'type' => 'percentage',
            'value' => 20,
            'starts_at' => now()->subMinutes(10)->toDateTimeString(),
            'ends_at' => now()->addHour()->toDateTimeString(),
        ]);
        $resPast->assertSessionHasErrors('starts_at');

        $this->assertEquals(0, ProductSale::count());

        Carbon::setTestNow();
    }

    /**
     * SEL-DISC-005: Discount percentage at the limits
     */
    public function test_sel_disc_005_discount_percentage_at_limits(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));

        $basePayload = [
            'product_id' => $this->p02->id,
            'type' => 'percentage',
            'starts_at' => now()->addMinutes(10)->toDateTimeString(),
            'ends_at' => now()->addHours(2)->toDateTimeString(),
        ];

        // Invalid percentages: 0%, 100%, 101%, -5%, 10.5%
        foreach ([0, 100, 101, -5, '10.5'] as $invalidPct) {
            $res = $this->actingAs($this->seller)->post(
                route('seller.sales.store'),
                array_merge($basePayload, ['value' => $invalidPct])
            );
            $res->assertSessionHasErrors('value');
        }

        // Valid 1% -> ₱445.50
        $res1 = $this->actingAs($this->seller)->post(
            route('seller.sales.store'),
            array_merge($basePayload, ['value' => 1])
        );
        $res1->assertSessionHasNoErrors();
        $sale1 = ProductSale::latest('id')->firstOrFail();
        $this->assertEquals('445.50', $sale1->sale_price);
        $sale1->delete();

        // Valid 99% -> ₱4.50
        $res99 = $this->actingAs($this->seller)->post(
            route('seller.sales.store'),
            array_merge($basePayload, ['value' => 99])
        );
        $res99->assertSessionHasNoErrors();
        $sale99 = ProductSale::latest('id')->firstOrFail();
        $this->assertEquals('4.50', $sale99->sale_price);

        Carbon::setTestNow();
    }

    /**
     * SEL-DISC-006: Fixed sale price at or above the original
     */
    public function test_sel_disc_006_fixed_sale_price_boundaries(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));

        $basePayload = [
            'product_id' => $this->p02->id,
            'type' => 'fixed',
            'starts_at' => now()->addMinutes(10)->toDateTimeString(),
            'ends_at' => now()->addHours(2)->toDateTimeString(),
        ];

        // Reject: ₱450.01, ₱450.00, ₱0.99
        foreach ([450.01, 450.00, 0.99] as $invalidFixed) {
            $res = $this->actingAs($this->seller)->post(
                route('seller.sales.store'),
                array_merge($basePayload, ['value' => $invalidFixed])
            );
            $res->assertSessionHasErrors('value');
        }

        // Accept: ₱449.99
        $resMax = $this->actingAs($this->seller)->post(
            route('seller.sales.store'),
            array_merge($basePayload, ['value' => 449.99])
        );
        $resMax->assertSessionHasNoErrors();
        $saleMax = ProductSale::latest('id')->firstOrFail();
        $this->assertEquals('449.99', $saleMax->sale_price);
        $saleMax->delete();

        // Accept: ₱1.00
        $resMin = $this->actingAs($this->seller)->post(
            route('seller.sales.store'),
            array_merge($basePayload, ['value' => 1.00])
        );
        $resMin->assertSessionHasNoErrors();
        $saleMin = ProductSale::latest('id')->firstOrFail();
        $this->assertEquals('1.00', $saleMin->sale_price);

        Carbon::setTestNow();
    }

    /**
     * SEL-DISC-007: Overlapping sales on the same product
     */
    public function test_sel_disc_007_overlapping_sales_on_same_product_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));

        // First sale: today 13:00–15:00
        $this->actingAs($this->seller)->post(route('seller.sales.store'), [
            'product_id' => $this->p02->id,
            'type' => 'percentage',
            'value' => 20,
            'starts_at' => '2026-10-20 13:00:00',
            'ends_at' => '2026-10-20 15:00:00',
        ])->assertSessionHasNoErrors();

        // Second overlapping sale: today 14:00–16:00 -> rejected
        $overlapRes = $this->actingAs($this->seller)->post(route('seller.sales.store'), [
            'product_id' => $this->p02->id,
            'type' => 'percentage',
            'value' => 15,
            'starts_at' => '2026-10-20 14:00:00',
            'ends_at' => '2026-10-20 16:00:00',
        ]);

        $overlapRes->assertSessionHasErrors('product_id');
        $this->assertStringContainsString(
            'already has a sale in that period',
            session('errors')->first('product_id')
        );
        $this->assertEquals(1, ProductSale::where('product_id', $this->p02->id)->count());

        Carbon::setTestNow();
    }

    /**
     * SEL-DISC-008: Sale on a product with variants
     */
    public function test_sel_disc_008_sale_on_product_with_variants(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));

        // Put P-01 (S ₱199, M ₱249, L ₱299) on 10% off (Ongoing)
        ProductSale::create([
            'seller_id' => $this->seller->id,
            'product_id' => $this->p01->id,
            'type' => 'percentage',
            'value' => 10,
            'starts_at' => now()->subMinutes(5),
            'ends_at' => now()->addHours(2),
        ]);

        $freshP01 = $this->p01->fresh('variants');
        $variantsBySize = $freshP01->variants->keyBy('size');

        $this->assertEquals('179.10', $variantsBySize['S']->price);
        $this->assertEquals('224.10', $variantsBySize['M']->price);
        $this->assertEquals('269.10', $variantsBySize['L']->price);
        $this->assertEquals('₱179.10 – ₱269.10', $freshP01->price_range);

        Carbon::setTestNow();
    }

    /**
     * SEL-DISC-009: Edit a running sale
     */
    public function test_sel_disc_009_edit_running_sale(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));

        // A 20% sale on P-02 is Ongoing
        $sale = ProductSale::create([
            'seller_id' => $this->seller->id,
            'product_id' => $this->p02->id,
            'type' => 'percentage',
            'value' => 20,
            'starts_at' => now()->subMinutes(15),
            'ends_at' => now()->addHours(2),
        ]);

        // Buyer 1 places an order at ₱360.00 during the 20% sale
        $cartItem1 = CartItem::create([
            'user_id' => $this->buyer1->id,
            'product_id' => $this->p02->id,
            'quantity' => 1,
        ]);

        $this->actingAs($this->buyer1)->post(route('buyer.orders.store'), [
            'selected_items' => [$cartItem1->id],
            'shipping_address' => '123 CPG Ave, Tagbilaran City, Bohol',
            'shipping_method' => 'standard',
            'payment_method' => 'cod',
        ])->assertSessionHasNoErrors();

        $buyer1Order = Order::where('user_id', $this->buyer1->id)->with('items')->latest('id')->firstOrFail();
        $this->assertEquals(360.00, (float) $buyer1Order->items->first()->price);

        // Seller changes the running sale discount to 30% and saves
        $this->actingAs($this->seller)->patch(route('seller.sales.update', $sale), [
            'value' => 30,
        ])->assertSessionHasNoErrors();

        // Buyer 2 sees ₱315.00 immediately
        $this->assertEquals('315.00', $this->p02->fresh()->price);

        // Buyer 1's earlier order still shows ₱360.00
        $this->assertEquals(360.00, (float) $buyer1Order->fresh('items')->items->first()->price);

        Carbon::setTestNow();
    }

    /**
     * SEL-DISC-010: End a running sale now
     */
    public function test_sel_disc_010_end_running_sale_now(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));

        $sale = ProductSale::create([
            'seller_id' => $this->seller->id,
            'product_id' => $this->p02->id,
            'type' => 'percentage',
            'value' => 20,
            'starts_at' => now()->subMinutes(15),
            'ends_at' => now()->addHours(2),
        ]);

        $cartItem = CartItem::create([
            'user_id' => $this->buyer1->id,
            'product_id' => $this->p02->id,
            'quantity' => 1,
        ]);

        $this->assertEquals('Ongoing', $sale->fresh()->status_label);
        $this->assertEquals('360.00', $this->p02->fresh()->price);

        // Seller clicks End Now
        $this->actingAs($this->seller)->post(route('seller.sales.end', $sale))
            ->assertSessionHasNoErrors();

        $this->assertEquals('Ended', $sale->fresh()->status_label);

        // Buyers see ₱450.00 at once in every view
        $freshP02 = $this->p02->fresh();
        $this->assertEquals('450.00', $freshP02->price);
        $this->assertNull($freshP02->old_price);
        $this->assertNull($freshP02->discount_badge);

        $checkoutRes = $this->actingAs($this->buyer1)->get(route('buyer.checkout', [
            'selected_items' => [$cartItem->id],
        ]));
        $checkoutRes->assertOk();
        $checkoutProps = $checkoutRes->viewData('page')['props'];
        $this->assertEquals('450.00', $checkoutProps['items'][0]['product']['price']);

        Carbon::setTestNow();
    }
}
