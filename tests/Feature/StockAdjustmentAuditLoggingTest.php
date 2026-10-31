<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdjustmentAuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    protected User $seller;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Seller Store',
        ]);

        $this->category = Category::create([
            'name' => 'Electronics',
            'slug' => 'stock-test-electronics',
        ]);
    }

    public function test_seller_can_log_stock_in_adjustment(): void
    {
        $product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'name' => 'Wireless Keyboard',
            'slug' => 'wireless-keyboard',
            'price' => 1200,
            'stock' => 10,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->seller)->post(
            route('seller.products.adjust-stock', $product->id),
            [
                'type' => 'stock_in',
                'quantity' => 15,
                'notes' => 'Received new stock shipment batch #102',
                'reference_number' => 'PO-9988',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Stock adjustment logged successfully.');

        $product->refresh();
        $this->assertEquals(25, $product->stock);

        $this->assertDatabaseHas('stock_adjustments', [
            'seller_id' => $this->seller->id,
            'product_id' => $product->id,
            'type' => 'stock_in',
            'quantity' => 15,
            'before_stock' => 10,
            'after_stock' => 25,
            'notes' => 'Received new stock shipment batch #102',
            'reference_number' => 'PO-9988',
        ]);
    }

    public function test_seller_can_log_damage_adjustment(): void
    {
        $product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'name' => 'Mechanical Keyboard',
            'slug' => 'mech-keyboard',
            'price' => 2500,
            'stock' => 20,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->seller)->post(
            route('seller.products.adjust-stock', $product->id),
            [
                'type' => 'damage',
                'quantity' => 3,
                'notes' => 'Water damage during storage',
                'reference_number' => 'DMG-01',
            ]
        );

        $response->assertRedirect();

        $product->refresh();
        $this->assertEquals(17, $product->stock);

        $this->assertDatabaseHas('stock_adjustments', [
            'seller_id' => $this->seller->id,
            'product_id' => $product->id,
            'type' => 'damage',
            'quantity' => -3,
            'before_stock' => 20,
            'after_stock' => 17,
            'notes' => 'Water damage during storage',
        ]);
    }

    public function test_variant_level_stock_adjustment(): void
    {
        $product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'name' => 'Cotton T-Shirt',
            'slug' => 'cotton-tshirt',
            'price' => 500,
            'stock' => 10,
            'status' => 'approved',
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Black',
            'size' => 'L',
            'stock' => 10,
        ]);

        $response = $this->actingAs($this->seller)->post(
            route('seller.products.adjust-stock', $product->id),
            [
                'type' => 'return',
                'product_variant_id' => $variant->id,
                'quantity' => 2,
                'notes' => 'Customer return in sealed box',
                'reference_number' => 'RET-4421',
            ]
        );

        $response->assertRedirect();

        $variant->refresh();
        $product->refresh();

        $this->assertEquals(12, $variant->stock);
        $this->assertEquals(12, $product->stock);

        $this->assertDatabaseHas('stock_adjustments', [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'type' => 'return',
            'quantity' => 2,
            'before_stock' => 10,
            'after_stock' => 12,
        ]);
    }

    public function test_seller_cannot_adjust_stock_of_another_sellers_product(): void
    {
        $otherSeller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Other Store',
        ]);

        $product = Product::create([
            'seller_id' => $otherSeller->id,
            'category_id' => $this->category->id,
            'name' => 'Other Seller Item',
            'slug' => 'other-seller-item',
            'price' => 800,
            'stock' => 5,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->seller)->post(
            route('seller.products.adjust-stock', $product->id),
            [
                'type' => 'stock_in',
                'quantity' => 5,
            ]
        );

        $response->assertForbidden();
    }
}
