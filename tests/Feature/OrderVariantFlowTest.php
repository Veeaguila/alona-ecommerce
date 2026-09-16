<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderVariantFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_checkout_creates_order_item_with_variant_metadata(): void
    {
        $category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'is_active' => true,
        ]);

        $seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'approved',
        ]);

        $buyer = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'seller_id' => $seller->id,
            'name' => 'Smart Watch',
            'slug' => 'smart-watch',
            'description' => 'Smart watch for testing.',
            'price' => 2499.00,
            'stock' => 10,
            'status' => 'approved',
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Black',
            'size' => '42mm',
            'stock' => 10,
        ]);

        $this->actingAs($buyer)
            ->post(route('buyer.cart.store', $product), [
                'quantity' => 1,
                'product_variant_id' => $variant->id,
            ])
            ->assertRedirect();

        $response = $this->actingAs($buyer)
            ->post(route('buyer.orders.store'), [
                'shipping_address' => '123 Test Street',
                'payment_method' => 'cod',
            ]);

        $response->assertRedirect(route('buyer.orders'));

        $this->assertDatabaseHas('orders', [
            'user_id' => $buyer->id,
            'payment_method' => 'cod',
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'product_name' => 'Smart Watch',
            'quantity' => 1,
            'price' => '2499.00',
            'status' => 'pending',
            'variant_label' => 'Black / 42mm',
            'product_variant_id' => $variant->id,
        ]);
    }
}
