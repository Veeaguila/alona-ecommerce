<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SellerNotification;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SellerInventoryTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private User $buyer;
    private User $secondBuyer;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Bohol Crafts Store',
        ]);

        $this->buyer = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
        ]);

        $this->secondBuyer = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
        ]);

        $this->category = Category::create([
            'name' => 'Handicrafts',
            'slug' => 'handicrafts',
            'is_active' => true,
        ]);
    }

    private function createProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'name' => 'Woven Basket',
            'slug' => 'woven-basket-' . uniqid(),
            'description' => 'Handcrafted Bohol woven basket.',
            'price' => 350.00,
            'stock' => 20,
            'low_stock_threshold' => 5,
            'status' => 'approved',
        ], $overrides));
    }

    private function orderPayload(array $cartItemIds, string $paymentMethod = 'cod'): array
    {
        return [
            'selected_items' => $cartItemIds,
            'shipping_address' => '123 CPG Ave, Poblacion, Tagbilaran City, Bohol 6300',
            'shipping_method' => 'standard',
            'payment_method' => $paymentMethod,
        ];
    }

    /**
     * SEL-INV-001: Stock deducted when COD order is placed
     */
    public function test_sel_inv_001_stock_deducted_when_cod_order_is_placed(): void
    {
        $product = $this->createProduct(['stock' => 20]);

        $cartItem = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($this->buyer)->post(
            route('buyer.orders.store'),
            $this->orderPayload([$cartItem->id], 'cod')
        );

        $response->assertSessionHasNoErrors();

        $this->assertEquals(18, $product->fresh()->stock);
    }

    /**
     * SEL-INV-002: Stock deducted (reserved) when unpaid e-wallet order is placed
     */
    public function test_sel_inv_002_stock_deducted_when_unpaid_ewallet_order_is_placed(): void
    {
        $product = $this->createProduct(['stock' => 20]);

        $cartItem = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($this->buyer)->post(
            route('buyer.orders.store'),
            $this->orderPayload([$cartItem->id], 'gcash')
        );

        $response->assertSessionHasNoErrors();

        $order = Order::where('user_id', $this->buyer->id)->latest('id')->firstOrFail();
        $this->assertEquals('pending', $order->status);
        $this->assertEquals('unpaid', $order->payment_status);
        $this->assertEquals('To Pay', $order->buyer_status_label);
        $this->assertEquals(19, $product->fresh()->stock);
    }

    /**
     * SEL-INV-003: Stock restored when buyer cancels order
     */
    public function test_sel_inv_003_stock_restored_when_buyer_cancels_order(): void
    {
        $product = $this->createProduct(['stock' => 20]);

        $cartItem = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->actingAs($this->buyer)->post(
            route('buyer.orders.store'),
            $this->orderPayload([$cartItem->id], 'cod')
        );

        $this->assertEquals(18, $product->fresh()->stock);

        $order = Order::where('user_id', $this->buyer->id)->latest('id')->firstOrFail();

        $cancelResponse = $this->actingAs($this->buyer)->patch(route('buyer.orders.cancel', $order), [
            'cancellation_reason' => 'Changed my mind',
        ]);

        $cancelResponse->assertSessionHasNoErrors();
        $this->assertEquals('cancelled', $order->fresh()->status);
        $this->assertEquals(20, $product->fresh()->stock);
    }

    /**
     * SEL-INV-004: Stock restored on auto-cancel of unpaid e-wallet order (after 1 hour)
     */
    public function test_sel_inv_004_stock_restored_on_auto_cancel_of_unpaid_order(): void
    {
        $product = $this->createProduct(['stock' => 20]);

        $cartItem = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($this->buyer)->post(
            route('buyer.orders.store'),
            $this->orderPayload([$cartItem->id], 'gcash')
        );

        $this->assertEquals(19, $product->fresh()->stock);

        $order = Order::where('user_id', $this->buyer->id)->latest('id')->firstOrFail();

        // Advance time past the 1-hour unpaid expiry window
        Carbon::setTestNow(now()->addMinutes(61));

        // Trigger auto-cancel via artisan command
        $this->artisan('orders:auto-cancel-unpaid')->assertExitCode(0);

        $freshOrder = $order->fresh();
        $this->assertEquals('cancelled', $freshOrder->status);
        $this->assertEquals('Cancelled (unpaid)', $freshOrder->cancellation_reason);
        $this->assertEquals(20, $product->fresh()->stock);

        Carbon::setTestNow();
    }

    /**
     * SEL-INV-005: Stock per variant deducted independently
     */
    public function test_sel_inv_005_stock_per_variant_deducted_independently(): void
    {
        $product = $this->createProduct(['stock' => 13]);

        $variantM = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Natural',
            'size' => 'M',
            'price' => 350.00,
            'stock' => 8,
            'sku' => 'WB-NAT-M',
        ]);

        $variantL = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Natural',
            'size' => 'L',
            'price' => 380.00,
            'stock' => 5,
            'sku' => 'WB-NAT-L',
        ]);

        $cartItem = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $product->id,
            'product_variant_id' => $variantM->id,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($this->buyer)->post(
            route('buyer.orders.store'),
            $this->orderPayload([$cartItem->id], 'cod')
        );

        $response->assertSessionHasNoErrors();

        $this->assertEquals(7, $variantM->fresh()->stock);
        $this->assertEquals(5, $variantL->fresh()->stock);
        $this->assertEquals(12, $product->fresh()->stock);
    }

    /**
     * SEL-INV-006: Prevent overselling when 2 buyers buy last item
     */
    public function test_sel_inv_006_prevent_overselling_when_two_buyers_buy_last_item(): void
    {
        $product = $this->createProduct([
            'name' => 'Raffia Tote',
            'stock' => 1,
        ]);

        $cartItemA = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $cartItemB = CartItem::create([
            'user_id' => $this->secondBuyer->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        // Buyer A places order for the last unit
        $responseA = $this->actingAs($this->buyer)->post(
            route('buyer.orders.store'),
            $this->orderPayload([$cartItemA->id], 'cod')
        );

        $responseA->assertSessionHasNoErrors();

        // Buyer B tries to place order for the same last unit
        $responseB = $this->actingAs($this->secondBuyer)->from(route('buyer.checkout'))->post(
            route('buyer.orders.store'),
            $this->orderPayload([$cartItemB->id], 'cod')
        );

        $responseB->assertSessionHasErrors('selected_items');
        $this->assertStringContainsString(
            'Sold out',
            session('errors')->first('selected_items')
        );

        // Only 1 order exists, Buyer B has no order, and stock is 0 (never -1)
        $this->assertEquals(1, Order::where('user_id', $this->buyer->id)->count());
        $this->assertEquals(0, Order::where('user_id', $this->secondBuyer->id)->count());
        $this->assertEquals(0, $product->fresh()->stock);
    }

    /**
     * SEL-INV-007: Automatic Sold Out status when stock hits 0
     */
    public function test_sel_inv_007_automatic_sold_out_status_when_stock_hits_zero(): void
    {
        $product = $this->createProduct([
            'name' => 'Abaca Coaster',
            'stock' => 1,
        ]);

        $cartItem = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->actingAs($this->buyer)->post(
            route('buyer.orders.store'),
            $this->orderPayload([$cartItem->id], 'cod')
        );

        $freshProduct = $product->fresh();
        $this->assertEquals(0, $freshProduct->stock);
        $this->assertTrue($freshProduct->is_sold_out);
        $this->assertEquals('Sold out', $freshProduct->stock_status_label);

        // Buyer tries to add sold-out product to cart -> blocked
        $addCartResponse = $this->actingAs($this->buyer)->post(route('buyer.cart.store', $freshProduct), [
            'quantity' => 1,
        ]);
        $addCartResponse->assertSessionHasErrors('cart');

        // Seller product list filter 'Out of Stock' returns this product
        $sellerResponse = $this->actingAs($this->seller)->get(route('seller.products', [
            'status' => 'Out of Stock',
        ]));
        $sellerResponse->assertOk();
        $pageProps = $sellerResponse->viewData('page')['props'];
        $this->assertEquals(1, $pageProps['summary']['out_of_stock']);
        $this->assertCount(1, $pageProps['products']['data']);
        $this->assertEquals($freshProduct->id, $pageProps['products']['data'][0]['id']);
    }

    /**
     * SEL-INV-008: Low-stock alert at threshold (5) — once per crossing
     */
    public function test_sel_inv_008_low_stock_alert_at_threshold_once_per_crossing(): void
    {
        $product = $this->createProduct([
            'name' => 'Calamay Jar',
            'stock' => 7,
            'low_stock_threshold' => 5,
        ]);

        $placeOrder = function (int $qty) use ($product) {
            $cartItem = CartItem::create([
                'user_id' => $this->buyer->id,
                'product_id' => $product->id,
                'quantity' => $qty,
            ]);

            $this->actingAs($this->buyer)->post(
                route('buyer.orders.store'),
                $this->orderPayload([$cartItem->id], 'cod')
            );
        };

        // 1. Sell 1 -> stock = 6 (above threshold 5) -> no alert
        $placeOrder(1);
        $this->assertEquals(6, $product->fresh()->stock);
        $this->assertEquals(
            0,
            SellerNotification::where('user_id', $this->seller->id)
                ->where('type', 'low_stock')
                ->count()
        );

        // 2. Sell 1 -> stock = 5 (crosses into threshold <= 5) -> 1 alert sent
        $placeOrder(1);
        $this->assertEquals(5, $product->fresh()->stock);
        $this->assertEquals(
            1,
            SellerNotification::where('user_id', $this->seller->id)
                ->where('type', 'low_stock')
                ->count()
        );

        // 3. Sell 1 -> stock = 4 (already below threshold) -> does NOT repeat
        $placeOrder(1);
        $this->assertEquals(4, $product->fresh()->stock);
        $this->assertEquals(
            1,
            SellerNotification::where('user_id', $this->seller->id)
                ->where('type', 'low_stock')
                ->count()
        );

        // 4. Restock above threshold (e.g. to 10), then sell 6 (stock = 4) -> crosses threshold again -> 2nd alert sent
        $this->actingAs($this->seller)->patch(route('seller.products.inventory.update', $product), [
            'stock' => 10,
            'notes' => 'Restocked from supplier',
        ]);
        $this->assertFalse($product->fresh()->low_stock_alerted);

        $placeOrder(6);
        $this->assertEquals(4, $product->fresh()->stock);
        $this->assertEquals(
            2,
            SellerNotification::where('user_id', $this->seller->id)
                ->where('type', 'low_stock')
                ->count()
        );
    }

    /**
     * SEL-INV-009: Manual stock adjustment — boundary validation & reason notes
     */
    public function test_sel_inv_009_manual_stock_adjustment_boundary_validation_and_reason(): void
    {
        $product = $this->createProduct(['stock' => 10]);

        // -1 -> reject
        $resNeg = $this->actingAs($this->seller)->patch(route('seller.products.inventory.update', $product), [
            'stock' => -1,
        ]);
        $resNeg->assertSessionHasErrors('stock');

        // 100000 -> reject
        $resOver = $this->actingAs($this->seller)->patch(route('seller.products.inventory.update', $product), [
            'stock' => 100000,
        ]);
        $resOver->assertSessionHasErrors('stock');

        // 2.5 -> reject
        $resDec = $this->actingAs($this->seller)->patch(route('seller.products.inventory.update', $product), [
            'stock' => '2.5',
        ]);
        $resDec->assertSessionHasErrors('stock');

        // abc -> reject
        $resAlpha = $this->actingAs($this->seller)->patch(route('seller.products.inventory.update', $product), [
            'stock' => 'abc',
        ]);
        $resAlpha->assertSessionHasErrors('stock');

        // 0 -> accept with reason
        $resZero = $this->actingAs($this->seller)->patch(route('seller.products.inventory.update', $product), [
            'stock' => 0,
            'notes' => 'Damaged batch removed',
        ]);
        $resZero->assertSessionHasNoErrors();
        $this->assertEquals(0, $product->fresh()->stock);

        // 99999 -> accept with reason
        $resMax = $this->actingAs($this->seller)->patch(route('seller.products.inventory.update', $product), [
            'stock' => 99999,
            'reason' => 'Restock from supplier',
        ]);
        $resMax->assertSessionHasNoErrors();
        $this->assertEquals(99999, $product->fresh()->stock);

        $latestLog = StockAdjustment::where('product_id', $product->id)->latest('id')->firstOrFail();
        $this->assertEquals(0, $latestLog->before_stock);
        $this->assertEquals(99999, $latestLog->after_stock);
        $this->assertEquals('Restock from supplier', $latestLog->notes);
    }

    /**
     * SEL-INV-010: Reduce stock below quantity in a buyer's cart
     */
    public function test_sel_inv_010_reduce_stock_below_quantity_in_buyer_cart(): void
    {
        $product = $this->createProduct([
            'name' => 'Buri Hat',
            'stock' => 10,
        ]);

        // Buyer has 5 units in cart
        $cartItem = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        // Seller reduces stock to 3
        $this->actingAs($this->seller)->patch(route('seller.products.inventory.update', $product), [
            'stock' => 3,
            'notes' => 'Inventory recount',
        ])->assertSessionHasNoErrors();

        $this->assertEquals(3, $product->fresh()->stock);

        // Buyer views cart -> sees 'Only 3 left' stock warning on cart item
        $freshCartItem = $cartItem->fresh();
        $this->assertEquals('Only 3 left', $freshCartItem->stock_warning);

        // Buyer tries to place order with quantity 5 -> blocked with 'Only 3 left'
        $orderAttempt = $this->actingAs($this->buyer)->from(route('buyer.cart'))->post(
            route('buyer.orders.store'),
            $this->orderPayload([$cartItem->id], 'cod')
        );

        $orderAttempt->assertSessionHasErrors('selected_items');
        $this->assertStringContainsString(
            'Only 3 left',
            session('errors')->first('selected_items')
        );

        // Buyer reduces cart quantity to 3 -> checkout succeeds
        $this->actingAs($this->buyer)->patch(route('buyer.cart.update', $cartItem), [
            'quantity' => 3,
        ])->assertSessionHasNoErrors();

        $orderSuccess = $this->actingAs($this->buyer)->post(
            route('buyer.orders.store'),
            $this->orderPayload([$cartItem->id], 'cod')
        );

        $orderSuccess->assertSessionHasNoErrors();
        $this->assertEquals(0, $product->fresh()->stock);
    }
}
