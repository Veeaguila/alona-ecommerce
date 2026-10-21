<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreFollower;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BuyerWishlistAndFollowTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private User $buyer;
    private Category $category;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::factory()->create([
            'name' => 'Bohol Native Crafts',
            'store_name' => 'Bohol Handicrafts Co.',
            'email' => 'seller@example.com',
            'usertype' => 'seller',
            'status' => 'approved',
            'email_verified_at' => now(),
        ]);

        $this->buyer = User::factory()->create([
            'name' => 'Maria Clara',
            'email' => 'maria@example.com',
            'usertype' => 'buyer',
            'status' => 'approved',
            'email_verified_at' => now(),
        ]);

        $this->category = Category::create([
            'name' => 'Handicrafts',
            'slug' => 'handicrafts',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'name' => 'Woven Bamboo Basket',
            'slug' => 'woven-bamboo-basket',
            'price' => 450.00,
            'stock' => 15,
            'status' => 'approved',
        ]);
    }

    /**
     * BUY-WISH-001: Add and remove a wishlist item (Clicking heart saves to wishlist; second click removes).
     */
    public function test_buy_wish_001_buyer_can_add_and_remove_wishlist_item(): void
    {
        // 1. Initial state: Not wishlisted
        $this->assertDatabaseMissing('wishlists', [
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
        ]);

        // 2. First toggle: Add to wishlist
        $response = $this->actingAs($this->buyer)
            ->post(route('buyer.wishlist.toggle', $this->product->id));

        $response->assertSessionHas('success', 'Item added to your wishlist.');
        $this->assertDatabaseHas('wishlists', [
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
        ]);

        // 3. Second toggle: Remove from wishlist
        $response2 = $this->actingAs($this->buyer)
            ->post(route('buyer.wishlist.toggle', $this->product->id));

        $response2->assertSessionHas('success', 'Item removed from your wishlist.');
        $this->assertDatabaseMissing('wishlists', [
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
        ]);

        // 4. Test JSON toggle response for asynchronous / instant UI updates
        $jsonResponse = $this->actingAs($this->buyer)
            ->postJson(route('buyer.wishlist.toggle', $this->product->id));

        $jsonResponse->assertOk()
            ->assertJson([
                'success' => true,
                'wishlisted' => true,
                'count' => 1,
            ]);

        $this->assertDatabaseHas('wishlists', [
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
        ]);

        // 5. Explicit destroy endpoint
        $destroyResponse = $this->actingAs($this->buyer)
            ->delete(route('buyer.wishlist.destroy', $this->product->id));

        $destroyResponse->assertSessionHas('success', 'Item removed from your wishlist.');
        $this->assertDatabaseMissing('wishlists', [
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
        ]);
    }

    /**
     * BUY-WISH-002: Add same item twice quickly (Rapid duplicate prevention).
     */
    public function test_buy_wish_002_rapid_duplicate_prevention_does_not_create_duplicates(): void
    {
        // Simulate rapid concurrent firstOrCreate additions
        Wishlist::firstOrCreate([
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
        ]);

        Wishlist::firstOrCreate([
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
        ]);

        // Database unique constraint and firstOrCreate enforce exactly 1 record
        $this->assertEquals(
            1,
            Wishlist::where('user_id', $this->buyer->id)
                ->where('product_id', $this->product->id)
                ->count()
        );

        // Attempting a raw duplicate insert fails with database constraint violation
        $duplicateCaught = false;
        try {
            Wishlist::create([
                'user_id' => $this->buyer->id,
                'product_id' => $this->product->id,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            $duplicateCaught = true;
        }

        $this->assertTrue($duplicateCaught, 'Database unique constraint properly prevents duplicate wishlist records.');
    }

    /**
     * BUY-WISH-003: Wishlist item goes out of stock (Item in wishlist shows "Sold out" badge).
     */
    public function test_buy_wish_003_out_of_stock_item_in_wishlist_displays_sold_out_badge(): void
    {
        // 1. Create out of stock product
        $soldOutProduct = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'name' => 'Handmade Abaca Placemat',
            'slug' => 'handmade-abaca-placemat',
            'price' => 120.00,
            'stock' => 0, // Out of stock
            'status' => 'approved',
        ]);

        // 2. Add in-stock product and out-of-stock product to wishlist
        Wishlist::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
        ]);

        Wishlist::create([
            'user_id' => $this->buyer->id,
            'product_id' => $soldOutProduct->id,
        ]);

        // 3. View buyer wishlist page
        $response = $this->actingAs($this->buyer)
            ->get(route('buyer.wishlist'));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/Wishlist')
                ->has('wishlist.data', 2)
                ->where('wishlist.data.0.product.is_sold_out', true) // soldOutProduct was created latest
                ->where('wishlist.data.0.product.stock_status_label', 'Sold out')
                ->where('wishlist.data.1.product.is_sold_out', false) // in-stock product
                ->where('wishlist.data.1.product.stock_status_label', 'In Stock')
            );

        // 4. Test variant-based out of stock handling
        $variantProduct = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'name' => 'Ceramic Clay Mug',
            'slug' => 'ceramic-clay-mug',
            'price' => 250.00,
            'stock' => 10,
            'status' => 'approved',
        ]);

        // Create variants with 0 stock
        ProductVariant::create([
            'product_id' => $variantProduct->id,
            'color' => 'Blue',
            'size' => 'Standard',
            'price' => 250.00,
            'stock' => 0,
        ]);
        ProductVariant::create([
            'product_id' => $variantProduct->id,
            'color' => 'Green',
            'size' => 'Standard',
            'price' => 250.00,
            'stock' => 0,
        ]);

        Wishlist::create([
            'user_id' => $this->buyer->id,
            'product_id' => $variantProduct->id,
        ]);

        $responseVariant = $this->actingAs($this->buyer)
            ->get(route('buyer.wishlist'));

        $responseVariant->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/Wishlist')
                ->where('wishlist.data.0.product.is_sold_out', true)
                ->where('wishlist.data.0.product.stock_status_label', 'Sold out')
            );
    }

    /**
     * BUY-WISH-004: Follow and unfollow a store (Follower count increases and decreases).
     */
    public function test_buy_wish_004_buyer_can_follow_and_unfollow_store_updating_follower_count(): void
    {
        // 1. Initial follower count is 0
        $this->assertEquals(0, StoreFollower::where('seller_id', $this->seller->id)->count());

        // 2. Buyer follows the store
        $followResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.store.follow', $this->seller->id));

        $followResponse->assertSessionHas('success', 'Following store successfully.');
        $this->assertDatabaseHas('store_followers', [
            'user_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
        ]);
        $this->assertEquals(1, StoreFollower::where('seller_id', $this->seller->id)->count());

        // Verify seller store page shows follower count = 1 and is_following = true
        $storePage = $this->actingAs($this->buyer)
            ->get(route('buyer.store.show', $this->seller->id));

        $storePage->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/SellerStore')
                ->where('is_following', true)
                ->where('stats.followers_count', 1)
            );

        // 3. Buyer unfollows the store
        $unfollowResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.store.follow', $this->seller->id));

        $unfollowResponse->assertSessionHas('success', 'Unfollowed store successfully.');
        $this->assertDatabaseMissing('store_followers', [
            'user_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
        ]);
        $this->assertEquals(0, StoreFollower::where('seller_id', $this->seller->id)->count());

        // Verify seller store page shows follower count = 0 and is_following = false
        $storePageUnfollowed = $this->actingAs($this->buyer)
            ->get(route('buyer.store.show', $this->seller->id));

        $storePageUnfollowed->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/SellerStore')
                ->where('is_following', false)
                ->where('stats.followers_count', 0)
            );

        // 4. Test JSON toggle response for seamless live frontend interaction
        $jsonFollow = $this->actingAs($this->buyer)
            ->postJson(route('buyer.store.follow', $this->seller->id));

        $jsonFollow->assertOk()
            ->assertJson([
                'success' => true,
                'is_following' => true,
                'followers_count' => 1,
            ]);

        $this->assertEquals(1, StoreFollower::where('seller_id', $this->seller->id)->count());
    }

    /**
     * BUY-WISH-005: Wishlist and follow require login (Clicking heart or follow while logged out redirects to login).
     */
    public function test_buy_wish_005_wishlist_and_follow_require_login(): void
    {
        // 1. Unauthenticated wishlist toggle via web request redirects to login
        $guestWishlistWeb = $this->post(route('buyer.wishlist.toggle', $this->product->id));
        $guestWishlistWeb->assertRedirect(route('login'));
        $this->assertDatabaseMissing('wishlists', [
            'product_id' => $this->product->id,
        ]);

        // 2. Unauthenticated wishlist toggle via JSON request returns 401 Unauthenticated
        $guestWishlistJson = $this->postJson(route('buyer.wishlist.toggle', $this->product->id));
        $guestWishlistJson->assertStatus(401);

        // 3. Unauthenticated store follow via web request redirects to login
        $guestFollowWeb = $this->post(route('buyer.store.follow', $this->seller->id));
        $guestFollowWeb->assertRedirect(route('login'));
        $this->assertDatabaseMissing('store_followers', [
            'seller_id' => $this->seller->id,
        ]);

        // 4. Unauthenticated store follow via JSON request returns 401 Unauthenticated
        $guestFollowJson = $this->postJson(route('buyer.store.follow', $this->seller->id));
        $guestFollowJson->assertStatus(401);

        // 5. Unauthenticated visit to /buyer/wishlist redirects to login
        $guestWishlistPage = $this->get(route('buyer.wishlist'));
        $guestWishlistPage->assertRedirect(route('login'));
    }
}
