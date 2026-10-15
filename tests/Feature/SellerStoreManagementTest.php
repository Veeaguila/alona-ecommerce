<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerStoreManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $seller;
    protected User $buyer;
    protected Category $category;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'CyberPulse Official',
            'store_description' => 'Official gaming and computer hardware distributor.',
        ]);

        $this->buyer = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
            'name' => 'Carlo Mendoza',
        ]);

        $this->category = Category::create([
            'name' => 'Computer Hardware',
            'slug' => 'computer-hardware',
        ]);

        $this->product = Product::create([
            'category_id' => $this->category->id,
            'seller_id' => $this->seller->id,
            'name' => 'GeForce RTX 4070 OC',
            'slug' => 'geforce-rtx-4070-oc',
            'price' => 38000.00,
            'stock' => 10,
            'status' => 'approved',
        ]);
    }

    /**
     * SELLER-62: Store banner & branding upload.
     */
    public function test_seller_can_upload_custom_banner_and_branding(): void
    {
        $logo = UploadedFile::fake()->image('store_logo.png', 400, 400);
        $banner = UploadedFile::fake()->image('cover_banner.jpg', 1200, 300);

        $response = $this->actingAs($this->seller)
            ->patch(route('seller.store.update'), [
                'store_name' => 'CyberPulse Republic',
                'store_tagline' => 'Next-Gen Computing & Custom PC Builds in PH',
                'store_description' => 'Authorized retailer of enthusiast-grade computing gear.',
                'store_logo' => $logo,
                'store_banner' => $banner,
            ]);

        $response->assertRedirect();
        $this->seller->refresh();

        $this->assertEquals('CyberPulse Republic', $this->seller->store_name);
        $this->assertEquals('Next-Gen Computing & Custom PC Builds in PH', $this->seller->store_tagline);
        $this->assertNotNull($this->seller->store_logo_path);
        $this->assertNotNull($this->seller->store_banner_path);

        Storage::disk('public')->assertExists($this->seller->store_logo_path);
        Storage::disk('public')->assertExists($this->seller->store_banner_path);

        // Buyer sees the uploaded banner and branding on storefront
        $buyerResponse = $this->get(route('buyer.store.show', $this->seller->id));
        $buyerResponse->assertOk();
        $buyerResponse->assertInertia(fn ($page) => $page
            ->component('Buyer/SellerStore')
            ->where('seller.store_name', 'CyberPulse Republic')
            ->where('seller.store_tagline', 'Next-Gen Computing & Custom PC Builds in PH')
            ->where('seller.store_banner_path', $this->seller->store_banner_path)
            ->where('seller.store_logo_path', $this->seller->store_logo_path)
        );
    }

    /**
     * SELLER-62: Seller can remove banner image.
     */
    public function test_seller_can_remove_banner_image(): void
    {
        $banner = UploadedFile::fake()->image('banner.jpg', 1200, 300);
        $path = $banner->store('store-banners', 'public');
        $this->seller->update(['store_banner_path' => $path]);

        $this->actingAs($this->seller)
            ->patch(route('seller.store.update'), [
                'store_name' => $this->seller->store_name,
                'remove_banner' => true,
            ]);

        $this->seller->refresh();
        $this->assertNull($this->seller->store_banner_path);
    }

    /**
     * SELLER-63: Operating hours schedule and response time.
     */
    public function test_seller_can_configure_operating_hours_and_response_time(): void
    {
        $schedule = [
            'monday' => ['is_open' => true, 'open' => '08:00', 'close' => '17:00'],
            'tuesday' => ['is_open' => true, 'open' => '08:00', 'close' => '17:00'],
            'wednesday' => ['is_open' => true, 'open' => '08:00', 'close' => '17:00'],
            'thursday' => ['is_open' => true, 'open' => '08:00', 'close' => '17:00'],
            'friday' => ['is_open' => true, 'open' => '08:00', 'close' => '17:00'],
            'saturday' => ['is_open' => true, 'open' => '09:00', 'close' => '15:00'],
            'sunday' => ['is_open' => false, 'open' => '09:00', 'close' => '15:00'],
        ];

        $response = $this->actingAs($this->seller)
            ->patch(route('seller.store.update'), [
                'store_name' => $this->seller->store_name,
                'operating_hours' => $schedule,
                'response_time' => 'within_hour',
            ]);

        $response->assertRedirect();
        $this->seller->refresh();

        $this->assertEquals('within_hour', $this->seller->response_time);
        $this->assertEquals('Replies within 1 hour', $this->seller->getResponseTimeLabel());
        $this->assertEquals('08:00', $this->seller->operating_hours['monday']['open']);
        $this->assertFalse($this->seller->operating_hours['sunday']['is_open']);

        // Buyer storefront passes operating schedule and response label
        $buyerResponse = $this->get(route('buyer.store.show', $this->seller->id));
        $buyerResponse->assertOk();
        $buyerResponse->assertInertia(fn ($page) => $page
            ->component('Buyer/SellerStore')
            ->where('seller.response_time', 'within_hour')
            ->where('seller.response_time_label', 'Replies within 1 hour')
            ->has('seller.store_status')
            ->has('seller.operating_hours')
        );
    }

    /**
     * SELLER-64: Store policies & terms customization.
     */
    public function test_seller_can_customize_store_policies_and_buyer_can_view_them(): void
    {
        $shippingPolicy = "Orders placed before 2PM ship same-day via J&T Express. Double bubble-wrapped.";
        $warrantyPolicy = "1-Year official store warranty with 7-day replacement for dead on arrival.";
        $returnPolicy = "Returns accepted within 7 days in complete original packaging with intact serial numbers.";

        $response = $this->actingAs($this->seller)
            ->patch(route('seller.store.update'), [
                'store_name' => $this->seller->store_name,
                'shipping_policy' => $shippingPolicy,
                'warranty_policy' => $warrantyPolicy,
                'return_policy' => $returnPolicy,
            ]);

        $response->assertRedirect();
        $this->seller->refresh();

        $this->assertEquals($shippingPolicy, $this->seller->shipping_policy);
        $this->assertEquals($warrantyPolicy, $this->seller->warranty_policy);
        $this->assertEquals($returnPolicy, $this->seller->return_policy);

        // Buyer visits store and receives policies
        $buyerResponse = $this->get(route('buyer.store.show', $this->seller->id));
        $buyerResponse->assertOk();
        $buyerResponse->assertInertia(fn ($page) => $page
            ->component('Buyer/SellerStore')
            ->where('seller.shipping_policy', $shippingPolicy)
            ->where('seller.warranty_policy', $warrantyPolicy)
            ->where('seller.return_policy', $returnPolicy)
        );
    }

    /**
     * SELLER-65: Vacation mode toggle and ordering block.
     */
    public function test_vacation_mode_blocks_cart_addition_and_checkout(): void
    {
        $vacationMsg = "Taking a short break from Oct 16 to Oct 20 for warehouse inventory audit.";

        // Enable vacation mode
        $this->actingAs($this->seller)
            ->patch(route('seller.store.update'), [
                'store_name' => $this->seller->store_name,
                'vacation_mode' => true,
                'vacation_message' => $vacationMsg,
            ]);

        $this->seller->refresh();
        $this->assertTrue($this->seller->vacation_mode);
        $this->assertEquals($vacationMsg, $this->seller->vacation_message);
        $this->assertFalse($this->seller->isStoreOpenNow());

        // 1. Buyer visits store -> sees vacation mode notice
        $storeResponse = $this->get(route('buyer.store.show', $this->seller->id));
        $storeResponse->assertOk();
        $storeResponse->assertInertia(fn ($page) => $page
            ->component('Buyer/SellerStore')
            ->where('seller.vacation_mode', true)
            ->where('seller.vacation_message', $vacationMsg)
            ->where('seller.store_status.is_vacation', true)
        );

        // 2. Buyer attempts to add product from vacationing seller to cart -> blocked with error
        $cartResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.cart.store', $this->product->id), [
                'quantity' => 1,
            ]);

        $cartResponse->assertSessionHasErrors('cart');
        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
        ]);

        // 3. Pre-existing cart item from this seller cannot be checked out
        $cartItem = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $orderResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.orders.store'), [
                'shipping_address' => '456 Acacia Ave, Quezon City',
                'payment_method' => 'cod',
                'shipping_method' => 'standard',
                'selected_items' => [$cartItem->id],
            ]);

        $orderResponse->assertSessionHasErrors('selected_items');
        $this->assertDatabaseMissing('orders', [
            'user_id' => $this->buyer->id,
        ]);

        // 4. Turn vacation mode OFF -> cart addition and order succeed
        $this->actingAs($this->seller)
            ->patch(route('seller.store.update'), [
                'store_name' => $this->seller->store_name,
                'vacation_mode' => false,
            ]);

        $this->seller->refresh();
        $this->assertFalse($this->seller->vacation_mode);

        $orderSuccess = $this->actingAs($this->buyer)
            ->post(route('buyer.orders.store'), [
                'shipping_address' => '456 Acacia Ave, Quezon City',
                'payment_method' => 'cod',
                'shipping_method' => 'standard',
                'selected_items' => [$cartItem->id],
            ]);

        $orderSuccess->assertRedirect();
        $this->assertDatabaseHas('orders', [
            'user_id' => $this->buyer->id,
        ]);
    }
}

