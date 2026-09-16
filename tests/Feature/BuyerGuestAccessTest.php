<?php

namespace Tests\Feature;

use Tests\TestCase;

class BuyerGuestAccessTest extends TestCase
{
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
}
