<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\ProductVariant;
use App\Models\SellerPromotion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SellerFlashSalesAndBundlesTest extends TestCase
{
    use RefreshDatabase;

    private User $seller1;
    private User $buyer1;
    private User $buyer2;
    private Category $category;
    private Product $p01;
    private ProductVariant $p01NaturalS;
    private ProductVariant $p01BlackL;
    private Product $p02;
    private Product $p04;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller1 = User::factory()->create([
            'name' => 'Maria Handwoven Crafts',
            'store_name' => 'Maria Handwoven Crafts',
            'usertype' => 'seller',
            'status' => 'approved',
        ]);

        $this->buyer1 = User::factory()->create([
            'name' => 'Buyer One',
            'usertype' => 'buyer',
            'status' => 'approved',
        ]);

        $this->buyer2 = User::factory()->create([
            'name' => 'Buyer Two',
            'usertype' => 'buyer',
            'status' => 'approved',
        ]);

        $this->category = Category::create([
            'name' => 'Native Crafts',
            'slug' => 'native-crafts',
        ]);

        // P-01: Handwoven Abaca Tote Bag with variants Natural-S (₱199) and Black-L (₱299)
        $this->p01 = Product::create([
            'seller_id' => $this->seller1->id,
            'category_id' => $this->category->id,
            'name' => 'Handwoven Abaca Tote Bag',
            'slug' => 'handwoven-abaca-tote-bag-p01',
            'description' => 'Authentic handwoven abaca tote bag crafted in Bicol.',
            'price' => 199.00,
            'stock' => 20,
            'weight' => 0.50,
            'status' => 'approved',
        ]);

        $this->p01NaturalS = ProductVariant::create([
            'product_id' => $this->p01->id,
            'color' => 'Natural',
            'size' => 'S',
            'price' => 199.00,
            'stock' => 10,
            'sku' => 'P01-NAT-S',
        ]);

        $this->p01BlackL = ProductVariant::create([
            'product_id' => $this->p01->id,
            'color' => 'Black',
            'size' => 'L',
            'price' => 299.00,
            'stock' => 10,
            'sku' => 'P01-BLK-L',
        ]);

        // P-02: Woven Rattan Basket (₱450.00, stock 20)
        $this->p02 = Product::create([
            'seller_id' => $this->seller1->id,
            'category_id' => $this->category->id,
            'name' => 'Woven Rattan Basket',
            'slug' => 'woven-rattan-basket-p02',
            'description' => 'Sturdy woven rattan basket made by local artisans.',
            'price' => 450.00,
            'stock' => 20,
            'weight' => 0.60,
            'status' => 'approved',
        ]);

        // P-04: Buri Coaster Set (₱350.00, restocked to 10)
        $this->p04 = Product::create([
            'seller_id' => $this->seller1->id,
            'category_id' => $this->category->id,
            'name' => 'Buri Coaster Set',
            'slug' => 'buri-coaster-set-p04',
            'description' => 'Handwoven buri coaster set of 6 pieces.',
            'price' => 350.00,
            'stock' => 10,
            'weight' => 0.25,
            'status' => 'approved',
        ]);
    }

    /**
     * SEL-FLASH-001: Create a flash sale in a time slot
     */
    public function test_sel_flash_001_create_flash_sale_in_a_time_slot(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 11:00:00'));

        $response = $this->actingAs($this->seller1)
            ->post(route('seller.flash-sales.store'), [
                'product_id' => $this->p02->id,
                'date' => '2026-10-20',
                'slot' => '12:00–14:00',
                'flash_price' => 299.00,
                'flash_quantity' => 3,
            ]);

        $response->assertSessionHasNoErrors();

        $flashSale = FlashSale::where('product_id', $this->p02->id)->firstOrFail();
        $this->assertSame('Upcoming', $flashSale->status_label);
        $this->assertEquals(299.00, (float) $flashSale->flash_price);
        $this->assertSame(3, $flashSale->flash_quantity);

        // At 11:59 buyers see ₱450 (with an 'upcoming flash sale' notice)
        $this->travelTo(Carbon::parse('2026-10-20 11:59:00'));

        $this->actingAs($this->buyer1)
            ->get(route('buyer.product', $this->p02->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/Show')
                ->where('product.price', '450.00')
                ->where('product.has_active_flash_sale', false)
                ->where('product.upcoming_flash_sale.flash_price', '299.00')
                ->where('product.upcoming_flash_sale.flash_quantity', 3)
            );

        // At 12:00 they see ₱299, a countdown to 14:00 and '3 left'
        $this->travelTo(Carbon::parse('2026-10-20 12:00:00'));

        $this->actingAs($this->buyer1)
            ->get(route('buyer.product', $this->p02->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/Show')
                ->where('product.price', '299.00')
                ->where('product.has_active_flash_sale', true)
                ->where('product.flash_remaining', 3)
                ->where('product.flash_remaining_label', '3 left')
                ->where('product.flash_countdown_seconds', 7200)
            );
    }

    /**
     * SEL-FLASH-002: Flash quantity and price validation
     */
    public function test_sel_flash_002_flash_quantity_and_price_validation(): void
    {
        $startsAt = now()->addHour()->toDateTimeString();
        $endsAt = now()->addHours(3)->toDateTimeString();

        // 1. Quantity 0 (reject)
        $this->actingAs($this->seller1)
            ->post(route('seller.flash-sales.store'), [
                'product_id' => $this->p02->id,
                'flash_price' => 299.00,
                'flash_quantity' => 0,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ])
            ->assertSessionHasErrors('flash_quantity');

        // 2. Quantity 21 when stock is 20 (reject)
        $this->actingAs($this->seller1)
            ->post(route('seller.flash-sales.store'), [
                'product_id' => $this->p02->id,
                'flash_price' => 299.00,
                'flash_quantity' => 21,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ])
            ->assertSessionHasErrors('flash_quantity');

        // 3. Flash price ₱450 equal to regular price ₱450 (reject)
        $this->actingAs($this->seller1)
            ->post(route('seller.flash-sales.store'), [
                'product_id' => $this->p02->id,
                'flash_price' => 450.00,
                'flash_quantity' => 5,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ])
            ->assertSessionHasErrors('flash_price');

        $this->assertSame(0, FlashSale::count());

        // 4. Quantity 20 and Flash price ₱449.99 (accept)
        $this->actingAs($this->seller1)
            ->post(route('seller.flash-sales.store'), [
                'product_id' => $this->p02->id,
                'flash_price' => 449.99,
                'flash_quantity' => 20,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, FlashSale::count());
        $saved = FlashSale::first();
        $this->assertEquals(449.99, (float) $saved->flash_price);
        $this->assertSame(20, $saved->flash_quantity);
    }

    /**
     * SEL-FLASH-003: Price returns to normal when flash quantity runs out
     */
    public function test_sel_flash_003_price_returns_to_normal_when_flash_quantity_runs_out(): void
    {
        FlashSale::create([
            'seller_id' => $this->seller1->id,
            'product_id' => $this->p02->id,
            'flash_price' => 299.00,
            'flash_quantity' => 3,
            'sold_quantity' => 0,
            'slot' => '12:00–14:00',
            'starts_at' => now()->subMinutes(10),
            'ends_at' => now()->addHours(2),
        ]);

        // Step 1: Buyer 1 buys 3 × P-02
        $cartItem1 = CartItem::create([
            'user_id' => $this->buyer1->id,
            'product_id' => $this->p02->id,
            'quantity' => 3,
        ]);

        $this->actingAs($this->buyer1)
            ->post(route('buyer.orders.store'), [
                'shipping_address' => '123 Rizal St, Legazpi City, Albay',
                'payment_method' => 'cod',
                'shipping_method' => 'standard',
                'selected_items' => [$cartItem1->id],
            ])
            ->assertSessionHasNoErrors();

        $order = Order::where('user_id', $this->buyer1->id)->latest('id')->firstOrFail();
        $this->assertEquals(897.00, (float) $order->subtotal);

        // Step 2: Buyer 2 refreshes P-02 and adds it to the cart -> sees ₱450 with no flash label
        $this->actingAs($this->buyer2)
            ->get(route('buyer.product', $this->p02->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/Show')
                ->where('product.price', '450.00')
                ->where('product.has_active_flash_sale', false)
                ->where('product.flash_remaining_label', null)
            );

        $this->actingAs($this->buyer2)
            ->post(route('buyer.cart.store', $this->p02->id), [
                'quantity' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->buyer2)
            ->get(route('buyer.cart'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/Cart')
                ->where('items.0.effective_price', '450.00')
                ->where('items.0.product.price', '450.00')
                ->where('items.0.product.has_active_flash_sale', false)
            );
    }

    /**
     * SEL-FLASH-004: Order more than the remaining flash quantity
     */
    public function test_sel_flash_004_order_more_than_remaining_flash_quantity(): void
    {
        FlashSale::create([
            'seller_id' => $this->seller1->id,
            'product_id' => $this->p02->id,
            'flash_price' => 299.00,
            'flash_quantity' => 3,
            'sold_quantity' => 2, // 1 flash unit left
            'slot' => '12:00–14:00',
            'starts_at' => now()->subMinutes(10),
            'ends_at' => now()->addHours(2),
        ]);

        // Buyer 1 adds 2 × P-02 and opens checkout
        $cartItem = CartItem::create([
            'user_id' => $this->buyer1->id,
            'product_id' => $this->p02->id,
            'quantity' => 2,
        ]);

        $this->actingAs($this->buyer1)
            ->get(route('buyer.checkout', ['selected_items' => [$cartItem->id]]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/Checkout')
                ->has('items', 2)
                ->where('items.0.quantity', 1)
                ->where('items.0.price', '299.00')
                ->where('items.0.is_flash_sale', true)
                ->where('items.1.quantity', 1)
                ->where('items.1.price', '450.00')
                ->where('items.1.is_flash_sale', false)
                ->where('subtotal', '749.00')
            );

        // Place the order
        $this->actingAs($this->buyer1)
            ->post(route('buyer.orders.store'), [
                'shipping_address' => '123 Rizal St, Legazpi City, Albay',
                'payment_method' => 'cod',
                'shipping_method' => 'standard',
                'selected_items' => [$cartItem->id],
            ])
            ->assertSessionHasNoErrors();

        $order = Order::with('items')->where('user_id', $this->buyer1->id)->latest('id')->firstOrFail();
        $this->assertEquals(749.00, (float) $order->subtotal);
        $this->assertCount(2, $order->items);

        $prices = $order->items->pluck('price')->map(fn ($p) => (float) $p)->sort()->values()->all();
        $this->assertSame([299.00, 450.00], $prices);

        // Seller's view shows the same split
        $this->actingAs($this->seller1)
            ->get(route('seller.orders'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Seller/Orders')
                ->has('orders.data', 2)
            );
    }

    /**
     * SEL-FLASH-005: Flash sale ends at the slot end
     */
    public function test_sel_flash_005_flash_sale_ends_at_slot_end(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 13:50:00'));

        FlashSale::create([
            'seller_id' => $this->seller1->id,
            'product_id' => $this->p02->id,
            'flash_price' => 299.00,
            'flash_quantity' => 3,
            'sold_quantity' => 0,
            'slot' => '12:00–14:00',
            'starts_at' => Carbon::parse('2026-10-20 12:00:00'),
            'ends_at' => Carbon::parse('2026-10-20 14:00:00'),
        ]);

        $cartItem = CartItem::create([
            'user_id' => $this->buyer1->id,
            'product_id' => $this->p02->id,
            'quantity' => 1,
        ]);

        // At 14:00 the slot ends
        $this->travelTo(Carbon::parse('2026-10-20 14:00:00'));

        // Product page returns to ₱450 with no error
        $this->actingAs($this->buyer1)
            ->get(route('buyer.product', $this->p02->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/Show')
                ->where('product.price', '450.00')
                ->where('product.has_active_flash_sale', false)
            );

        // Cart returns to ₱450 with no error
        $this->actingAs($this->buyer1)
            ->get(route('buyer.cart'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/Cart')
                ->where('items.0.effective_price', '450.00')
                ->where('subtotal_after_promotions', '450.00')
            );

        // Checkout returns to ₱450 with no error
        $this->actingAs($this->buyer1)
            ->get(route('buyer.checkout', ['selected_items' => [$cartItem->id]]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/Checkout')
                ->where('items.0.price', '450.00')
                ->where('subtotal', '450.00')
            );
    }

    /**
     * SEL-FLASH-006: Overlapping store sale and flash sale
     */
    public function test_sel_flash_006_overlapping_store_sale_and_flash_sale(): void
    {
        // P-02 has a store sale at ₱360 (20% off ₱450)
        ProductSale::create([
            'seller_id' => $this->seller1->id,
            'product_id' => $this->p02->id,
            'type' => 'fixed',
            'value' => 360.00,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(5),
        ]);

        // And a flash sale at ₱299 at the same time, 1 flash unit left
        FlashSale::create([
            'seller_id' => $this->seller1->id,
            'product_id' => $this->p02->id,
            'flash_price' => 299.00,
            'flash_quantity' => 3,
            'sold_quantity' => 2, // 1 left
            'slot' => '12:00–14:00',
            'starts_at' => now()->subMinutes(15),
            'ends_at' => now()->addHours(2),
        ]);

        // 1. Product page shows ₱299
        $this->actingAs($this->buyer1)
            ->get(route('buyer.product', $this->p02->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/Show')
                ->where('product.price', '299.00')
                ->where('product.has_active_flash_sale', true)
                ->where('product.flash_remaining_label', '1 left')
            );

        // 2. Add 2 to the cart and open checkout -> shows 1 × ₱299 and 1 × ₱360 (the store sale, not ₱450)
        $cartItem = CartItem::create([
            'user_id' => $this->buyer1->id,
            'product_id' => $this->p02->id,
            'quantity' => 2,
        ]);

        $this->actingAs($this->buyer1)
            ->get(route('buyer.checkout', ['selected_items' => [$cartItem->id]]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/Checkout')
                ->has('items', 2)
                ->where('items.0.quantity', 1)
                ->where('items.0.price', '299.00')
                ->where('items.0.is_flash_sale', true)
                ->where('items.1.quantity', 1)
                ->where('items.1.price', '360.00')
                ->where('items.1.is_flash_sale', false)
                ->where('subtotal', '659.00')
            );
    }

    /**
     * SEL-FLASH-007: Bundle deal item counting
     */
    public function test_sel_flash_007_bundle_deal_item_counting(): void
    {
        // Seller 1 creates Bundle: any 3 items from P-01 for 15% off
        $this->actingAs($this->seller1)
            ->post(route('seller.promotions.store'), [
                'type' => 'bundle',
                'product_id' => $this->p01->id,
                'min_quantity' => 3,
                'discount_type' => 'percentage',
                'discount_value' => 15,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, SellerPromotion::where('type', 'bundle')->count());

        // Step 1: Add 2 × P-01 Natural-S (₱199 × 2 = ₱398) -> no discount
        $itemNatS = CartItem::create([
            'user_id' => $this->buyer1->id,
            'product_id' => $this->p01->id,
            'product_variant_id' => $this->p01NaturalS->id,
            'quantity' => 2,
        ]);

        $this->actingAs($this->buyer1)
            ->get(route('buyer.cart'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/Cart')
                ->where('raw_subtotal', '398.00')
                ->where('bundle_discount', '0.00')
                ->where('subtotal_after_promotions', '398.00')
            );

        // Step 2: Add 1 × P-01 Black-L (₱299) -> 3 mixed variants: 15% of ₱697 = ₱104.55 off
        $itemBlkL = CartItem::create([
            'user_id' => $this->buyer1->id,
            'product_id' => $this->p01->id,
            'product_variant_id' => $this->p01BlackL->id,
            'quantity' => 1,
        ]);

        $this->actingAs($this->buyer1)
            ->get(route('buyer.cart'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/Cart')
                ->where('raw_subtotal', '697.00')
                ->where('bundle_discount', '104.55')
                ->where('subtotal_after_promotions', '592.45')
            );

        // Step 3: Remove one Natural-S (quantity becomes 1) -> discount removed
        $this->actingAs($this->buyer1)
            ->patch(route('buyer.cart.update', $itemNatS->id), [
                'quantity' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->buyer1)
            ->get(route('buyer.cart'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/Cart')
                ->where('raw_subtotal', '498.00')
                ->where('bundle_discount', '0.00')
                ->where('subtotal_after_promotions', '498.00')
            );
    }

    /**
     * SEL-FLASH-008: Buy X Get Y
     */
    public function test_sel_flash_008_buy_x_get_y(): void
    {
        // Seller 1 creates Promotion: buy 2 × P-02, get 1 × P-04 free
        $this->actingAs($this->seller1)
            ->post(route('seller.promotions.store'), [
                'type' => 'buy_x_get_y',
                'buy_product_id' => $this->p02->id,
                'buy_quantity' => 2,
                'get_product_id' => $this->p04->id,
                'get_quantity' => 1,
            ])
            ->assertSessionHasNoErrors();

        // Step 1: Buyer 1 adds 2 × P-02 and 1 × P-04 -> P-04 shows ₱0.00
        $itemP02 = CartItem::create([
            'user_id' => $this->buyer1->id,
            'product_id' => $this->p02->id,
            'quantity' => 2,
            'created_at' => now()->subMinute(),
        ]);

        $itemP04 = CartItem::create([
            'user_id' => $this->buyer1->id,
            'product_id' => $this->p04->id,
            'quantity' => 1,
            'created_at' => now(),
        ]);

        $this->actingAs($this->buyer1)
            ->get(route('buyer.cart'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/Cart')
                ->where('items.0.product_id', $this->p04->id)
                ->where('items.0.effective_price', '0.00')
                ->where('items.0.price', '0.00')
                ->where('items.0.is_free_gift', true)
                ->where('buy_x_get_y_discount', '350.00')
                ->where('subtotal_after_promotions', '900.00')
            );

        // Step 2: Remove one P-02 (quantity becomes 1) -> P-04 returns to ₱350.00
        $this->actingAs($this->buyer1)
            ->patch(route('buyer.cart.update', $itemP02->id), [
                'quantity' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->buyer1)
            ->get(route('buyer.cart'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/Cart')
                ->where('items.0.product_id', $this->p04->id)
                ->where('items.0.effective_price', '350.00')
                ->where('items.0.price', '350.00')
                ->where('items.0.is_free_gift', false)
                ->where('buy_x_get_y_discount', '0.00')
                ->where('subtotal_after_promotions', '800.00')
            );
    }
}

