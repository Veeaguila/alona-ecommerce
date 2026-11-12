<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FlashSale;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BuyerProductDetailsTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private User $buyer;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::factory()->create([
            'name' => 'Artisan Crafts',
            'store_name' => 'Bohol Artisan Hub',
            'email' => 'artisan@example.com',
            'usertype' => 'seller',
            'status' => 'approved',
        ]);

        $this->buyer = User::factory()->create([
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'usertype' => 'buyer',
            'status' => 'approved',
        ]);

        $this->category = Category::create([
            'name' => 'Native Bags',
            'slug' => 'native-bags',
            'is_active' => true,
        ]);
    }

    private function createProduct(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'name' => 'Handwoven Basket',
            'slug' => 'handwoven-basket',
            'description' => 'Authentic Bohol handwoven basket',
            'price' => 500.00,
            'old_price' => 650.00,
            'stock' => 20,
            'status' => 'approved',
            'per_buyer_limit' => null,
        ], $attributes));
    }

    /**
     * BUY-PDP-001: All variation types must be selected.
     */
    public function test_buy_pdp_001_all_variation_types_must_be_selected_before_adding_to_cart(): void
    {
        $product = $this->createProduct(['stock' => 30]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Natural',
            'size' => 'Medium',
            'price' => 550.00,
            'stock' => 15,
            'sku' => 'BASKET-NAT-M',
        ]);

        // Attempting to add to cart without specifying variation must fail
        $response = $this->actingAs($this->buyer)
            ->post(route('buyer.cart.store', $product->id), [
                'quantity' => 1,
            ]);

        $response->assertSessionHasErrors('product_variant_id');

        // Selecting the variant successfully adds to cart
        $successResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.cart.store', $product->id), [
                'quantity' => 1,
                'product_variant_id' => $variant->id,
            ]);

        $successResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $this->buyer->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);
    }

    /**
     * BUY-PDP-002: Variant updates price, stock, and image.
     */
    public function test_buy_pdp_002_variant_updates_price_and_stock_on_product_page(): void
    {
        $product = $this->createProduct(['stock' => 50, 'price' => 500.00]);

        $variantSmall = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Brown',
            'size' => 'Small',
            'price' => 450.00,
            'stock' => 8,
            'sku' => 'BASKET-BRN-S',
        ]);

        $variantLarge = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Brown',
            'size' => 'Large',
            'price' => 700.00,
            'stock' => 18,
            'sku' => 'BASKET-BRN-L',
        ]);

        $response = $this->get(route('buyer.product', $product));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Buyer/Show')
            ->has('product.variants', 2)
            ->where('product.variants', fn ($variants) => 
                collect($variants)->pluck('price')->sort()->values()->all() === ['450.00', '700.00']
                && collect($variants)->pluck('stock')->sort()->values()->all() === [8, 18]
            )
        );
    }

    /**
     * BUY-PDP-003: Out-of-stock variant disabled and blocked.
     */
    public function test_buy_pdp_003_out_of_stock_variant_cannot_be_added_to_cart(): void
    {
        $product = $this->createProduct(['stock' => 20]);

        $soldOutVariant = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Red',
            'size' => 'Medium',
            'price' => 520.00,
            'stock' => 0,
            'sku' => 'BASKET-RED-M',
        ]);

        $response = $this->actingAs($this->buyer)
            ->post(route('buyer.cart.store', $product->id), [
                'quantity' => 1,
                'product_variant_id' => $soldOutVariant->id,
            ]);

        $response->assertSessionHasErrors('cart');
        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $this->buyer->id,
            'product_variant_id' => $soldOutVariant->id,
        ]);
    }

    /**
     * BUY-PDP-004: Quantity limits (cannot enter 0, negative, or more than stock).
     */
    public function test_buy_pdp_004_quantity_limits_reject_zero_negative_and_excessive_stock(): void
    {
        $product = $this->createProduct(['stock' => 15]);

        // 1. Zero quantity rejected
        $zeroResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.cart.store', $product->id), [
                'quantity' => 0,
            ]);
        $zeroResponse->assertSessionHasErrors('quantity');

        // 2. Negative quantity rejected
        $negativeResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.cart.store', $product->id), [
                'quantity' => -3,
            ]);
        $negativeResponse->assertSessionHasErrors('quantity');

        // 3. Exceeding available stock rejected
        $excessResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.cart.store', $product->id), [
                'quantity' => 25,
            ]);
        $excessResponse->assertSessionHasErrors('quantity');

        // 4. Valid stock amount succeeds
        $validResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.cart.store', $product->id), [
                'quantity' => 5,
            ]);
        $validResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $this->buyer->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);
    }

    /**
     * BUY-PDP-005: Per-buyer purchase limit.
     */
    public function test_buy_pdp_005_per_buyer_purchase_limit_is_strictly_enforced(): void
    {
        // Product allows only 2 units per customer
        $product = $this->createProduct([
            'stock' => 50,
            'per_buyer_limit' => 2,
        ]);

        // 1. Attempting to add 3 units at once is rejected
        $overLimitResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.cart.store', $product->id), [
                'quantity' => 3,
            ]);
        $overLimitResponse->assertSessionHasErrors('quantity');

        // 2. Adding the allowed 2 units succeeds
        $allowedResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.cart.store', $product->id), [
                'quantity' => 2,
            ]);
        $allowedResponse->assertSessionHasNoErrors();

        // 3. Trying to add 1 more to cart fails because limit (2) is reached
        $secondAddResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.cart.store', $product->id), [
                'quantity' => 1,
            ]);
        $secondAddResponse->assertSessionHasErrors('quantity');

        // 4. Simulate completed order of 2 units
        $this->buyer->cartItems()->delete();

        $order = Order::create([
            'order_number' => 'ORD-TEST-LIMIT-001',
            'user_id' => $this->buyer->id,
            'status' => 'delivered',
            'payment_status' => 'paid',
            'payment_method' => 'cod',
            'shipping_method' => 'standard',
            'shipping_fee' => 50,
            'total' => 1050,
            'subtotal' => 1000,
            'discount' => 0,
            'shipping_address' => '123 Rizal Street, Bohol',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => $product->price,
            'quantity' => 2,
            'status' => 'delivered',
        ]);

        // 5. Trying to buy again after previously purchasing 2 units is rejected
        $repeatResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.cart.store', $product->id), [
                'quantity' => 1,
            ]);
        $repeatResponse->assertSessionHasErrors('quantity');
    }

    /**
     * BUY-PDP-006: Sale price, countdown, and vouchers shown.
     */
    public function test_buy_pdp_006_sale_price_countdown_and_vouchers_shown(): void
    {
        $product = $this->createProduct(['price' => 800.00, 'stock' => 25]);

        // Active flash sale
        FlashSale::create([
            'seller_id' => $this->seller->id,
            'product_id' => $product->id,
            'flash_price' => 599.00,
            'flash_quantity' => 10,
            'sold_quantity' => 2,
            'slot' => '12:00',
            'starts_at' => now()->subMinutes(30),
            'ends_at' => now()->addHours(2),
        ]);

        // Active seller voucher
        Voucher::create([
            'seller_id' => $this->seller->id,
            'code' => 'BOHOL10',
            'type' => 'percentage',
            'value' => 10,
            'min_spend' => 300,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(5),
        ]);

        $response = $this->get(route('buyer.product', $product));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Buyer/Show')
            ->where('product.has_active_flash_sale', true)
            ->where('product.flash_price', '599.00')
            ->where('product.flash_remaining', 8)
            ->has('product.flash_ends_at')
            ->has('vouchers', 1)
            ->where('vouchers.0.code', 'BOHOL10')
        );
    }

    /**
     * BUY-PDP-007: Buy Now checks out only that item.
     */
    public function test_buy_pdp_007_buy_now_checks_out_only_the_selected_item(): void
    {
        // Buyer already has another item in cart
        $unrelatedProduct = $this->createProduct(['name' => 'Handmade Scarf', 'slug' => 'handmade-scarf']);
        $existingCartItem = $this->buyer->cartItems()->create([
            'product_id' => $unrelatedProduct->id,
            'quantity' => 3,
        ]);

        $buyNowProduct = $this->createProduct(['name' => 'Native Hat', 'slug' => 'native-hat']);

        // Buy now posts with buy_now = true
        $response = $this->actingAs($this->buyer)
            ->post(route('buyer.cart.store', $buyNowProduct->id), [
                'quantity' => 1,
                'buy_now' => true,
            ]);

        // Finds the created cart item for the Buy Now product
        $buyNowCartItem = $this->buyer->cartItems()->where('product_id', $buyNowProduct->id)->first();
        $this->assertNotNull($buyNowCartItem);

        // Redirects directly to checkout with only that item
        $response->assertRedirect(route('buyer.checkout', [
            'selected_items' => [$buyNowCartItem->id],
        ]));

        // Visiting checkout with that item selects only the clicked item
        $checkoutResponse = $this->actingAs($this->buyer)
            ->get(route('buyer.checkout', ['selected_items' => [$buyNowCartItem->id]]));

        $checkoutResponse->assertOk();
        $checkoutResponse->assertInertia(fn (Assert $page) => $page
            ->component('Buyer/Checkout')
            ->has('items', 1)
            ->where('items.0.product_id', $buyNowProduct->id)
        );

        // The other cart item still exists unharmed in buyer's cart
        $this->assertDatabaseHas('cart_items', [
            'id' => $existingCartItem->id,
            'quantity' => 3,
        ]);
    }

    /**
     * BUY-PDP-008: Product page of unavailable product returns 404.
     */
    public function test_buy_pdp_008_unavailable_or_unapproved_product_returns_404(): void
    {
        $pendingProduct = $this->createProduct([
            'name' => 'Draft Item',
            'slug' => 'draft-item',
            'status' => 'pending',
        ]);

        $rejectedProduct = $this->createProduct([
            'name' => 'Rejected Item',
            'slug' => 'rejected-item',
            'status' => 'rejected',
        ]);

        // Pending product returns 404
        $this->get(route('buyer.product', $pendingProduct))->assertNotFound();

        // Rejected product returns 404
        $this->get(route('buyer.product', $rejectedProduct))->assertNotFound();

        // Non-existent product ID returns 404
        $this->get('/buyer/product/9999999')->assertNotFound();
    }
}
