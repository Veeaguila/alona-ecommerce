<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_buyer_can_visit_seller_store(): void
    {
        $seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Orashare Official Store',
            'store_description' => 'Premium Electronics & Accessories',
        ]);

        $category = Category::create([
            'name' => 'Electronics',
            'slug' => 'store-test-electronics',
        ]);

        Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => '10000mAh Power Bank',
            'slug' => '10000mah-power-bank',
            'price' => 599.00,
            'stock' => 50,
            'status' => 'approved',
        ]);

        Voucher::create([
            'seller_id' => $seller->id,
            'code' => 'WELCOME5',
            'type' => 'fixed',
            'value' => 5.00,
            'min_spend' => 409.00,
            'is_active' => true,
        ]);

        $response = $this->get(route('buyer.store.show', $seller->id));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Buyer/SellerStore')
                ->has('seller')
                ->has('products.data', 1)
                ->has('recommended_products')
                ->has('categories')
                ->has('vouchers', 1)
                ->has('stats')
                ->where('seller.store_name', 'Orashare Official Store')
                ->where('stats.total_products', 1)
                ->where('stats.total_reviews', 0)
                ->where('stats.avg_rating', null)
                ->missing('stats.followers')
                ->missing('stats.following')
                ->missing('stats.chat_performance')
                ->missing('seller.is_official')
                ->missing('seller.active_status')
            );
    }

    public function test_store_without_vouchers_returns_empty_vouchers_list(): void
    {
        $seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Bare Minimum Store',
        ]);

        $response = $this->get(route('buyer.store.show', $seller->id));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Buyer/SellerStore')
                ->where('seller.store_name', 'Bare Minimum Store')
                ->has('vouchers', 0)
                ->where('stats.total_products', 0)
                ->where('stats.avg_rating', null)
                ->missing('stats.followers')
            );
    }

    public function test_guest_can_access_store_via_guest_route_and_see_seller_vouchers(): void
    {
        $seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Mega Deals Store',
        ]);

        // Seller puts two active vouchers
        Voucher::create([
            'seller_id' => $seller->id,
            'code' => 'MEGADEAL10',
            'type' => 'percentage',
            'value' => 10,
            'min_spend' => 500,
            'is_active' => true,
        ]);

        Voucher::create([
            'seller_id' => $seller->id,
            'code' => 'FLAT50',
            'type' => 'fixed',
            'value' => 50,
            'min_spend' => 300,
            'is_active' => true,
        ]);

        $response = $this->get(route('guest.store', $seller->id));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Buyer/SellerStore')
                ->where('seller.store_name', 'Mega Deals Store')
                ->has('vouchers', 2)
                ->where('vouchers.0.code', 'FLAT50')
                ->where('vouchers.1.code', 'MEGADEAL10')
            );
    }
}

