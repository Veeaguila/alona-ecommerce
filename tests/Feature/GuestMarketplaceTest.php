<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_seller_listing_is_publicly_browsable_and_searchable(): void
    {
        $seller = User::factory()->create(['usertype' => 'seller']);
        $category = Category::create(['name' => 'Home & Living', 'slug' => 'home-living']);

        $this->actingAs($seller)
            ->post(route('seller.products.store'), [
                'name' => 'Handmade Lantern',
                'category_id' => $category->id,
                'price' => 1250,
                'stock' => 4,
                'description' => 'A handcrafted bamboo lantern.',
                'status' => 'approved',
            ])
            ->assertRedirect(route('seller.products'));

        $product = Product::where('name', 'Handmade Lantern')->firstOrFail();
        $this->assertSame($seller->id, $product->seller_id);

        auth()->logout();

        $this->get(route('buyer.products', ['search' => 'Lantern', 'category' => 'home-living']))
            ->assertOk()
            ->assertSee('Handmade Lantern');

        $this->get(route('buyer.product', $product))
            ->assertOk()
            ->assertSee('A handcrafted bamboo lantern.');

        $this->assertGuest();
    }

    public function test_buyer_product_details_display_seller_and_stock_information(): void
    {
        $seller = User::factory()->create([
            'usertype' => 'seller',
            'name' => 'Maria Seller',
        ]);

        $category = Category::create(['name' => 'Home & Living', 'slug' => 'home-living']);

        $product = Product::create([
            'category_id' => $category->id,
            'seller_id' => $seller->id,
            'name' => 'Bamboo Lamp',
            'slug' => 'bamboo-lamp',
            'description' => 'A warm ambient lamp made from bamboo.',
            'price' => 1499,
            'stock' => 7,
            'status' => 'approved',
        ]);

        $this->get(route('buyer.product', $product))
            ->assertOk()
            ->assertSee('Maria Seller')
            ->assertSee('7 items available');
    }
}
