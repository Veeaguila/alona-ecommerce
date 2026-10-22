<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerGuestAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_browsing_and_search_routes_are_public(): void
    {
        $productsRoute = app('router')->getRoutes()->getByName('buyer.products');
        $productRoute = app('router')->getRoutes()->getByName('buyer.product');

        $this->assertNotContains('auth', $productsRoute->middleware());
        $this->assertNotContains('auth', $productRoute->middleware());
    }

    public function test_guests_are_redirected_to_login_from_buyer_account_pages(): void
    {
        foreach ([
            'buyer.dashboard',
            'buyer.cart',
            'buyer.wishlist',
            'buyer.checkout',
            'buyer.orders',
            'buyer.reviews',
            'buyer.notifications',
            'buyer.messages',
            'buyer.account',
            'buyer.addresses',
        ] as $routeName) {
            $this->get(route($routeName))
                ->assertRedirect(route('login'));
        }
    }

    public function test_authenticated_buyer_visiting_root_is_redirected_to_buyer_dashboard(): void
    {
        $buyer = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($buyer)
            ->get('/')
            ->assertRedirect(route('buyer.dashboard'));
    }

    public function test_authenticated_seller_visiting_root_is_redirected_to_seller_dashboard(): void
    {
        $seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'approved',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($seller)
            ->get('/')
            ->assertRedirect(route('seller.dashboard'));
    }

    public function test_authenticated_admin_visiting_root_is_redirected_to_admin_dashboard(): void
    {
        $admin = User::factory()->create([
            'usertype' => 'admin',
            'status' => 'approved',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_authenticated_buyer_visiting_guest_products_is_redirected_to_buyer_products(): void
    {
        $buyer = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($buyer)
            ->get(route('guest.products', ['search' => 'laptop']))
            ->assertRedirect(route('buyer.products', ['search' => 'laptop']));
    }

    public function test_authenticated_buyer_visiting_guest_categories_is_redirected_to_buyer_categories(): void
    {
        $buyer = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($buyer)
            ->get(route('guest.categories'))
            ->assertRedirect(route('buyer.categories'));
    }
}
