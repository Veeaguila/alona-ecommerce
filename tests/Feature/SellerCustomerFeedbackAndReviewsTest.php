<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\SellerReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SellerCustomerFeedbackAndReviewsTest extends TestCase
{
    use RefreshDatabase;

    protected User $seller;
    protected User $buyer;
    protected Product $product;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Apex Hardware Store',
        ]);

        $this->buyer = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
            'name' => 'Maria Santos',
        ]);

        $this->category = Category::create([
            'name' => 'Power Tools',
            'slug' => 'power-tools',
        ]);

        $this->product = Product::create([
            'category_id' => $this->category->id,
            'seller_id' => $this->seller->id,
            'name' => 'Cordless Impact Drill 18V',
            'slug' => 'cordless-impact-drill-18v',
            'price' => 3500.00,
            'stock' => 15,
            'status' => 'approved',
        ]);
    }

    /**
     * SELLER-35: Rating distribution breakdown computed dynamically from actual data.
     */
    public function test_rating_distribution_calculates_actual_data_without_hardcoding(): void
    {
        // 2x 5-star, 1x 4-star, 1x 1-star -> Total 4 reviews, Avg = (5+5+4+1)/4 = 3.75
        $buyer2 = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $buyer3 = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $buyer4 = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);

        Review::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'rating' => 5,
            'comment' => 'Excellent build quality!',
        ]);

        Review::create([
            'user_id' => $buyer2->id,
            'product_id' => $this->product->id,
            'rating' => 5,
            'comment' => 'Very satisfied with the speed.',
        ]);

        Review::create([
            'user_id' => $buyer3->id,
            'product_id' => $this->product->id,
            'rating' => 4,
            'comment' => 'Good tool overall.',
        ]);

        Review::create([
            'user_id' => $buyer4->id,
            'product_id' => $this->product->id,
            'rating' => 1,
            'comment' => 'Box arrived damaged.',
        ]);

        $response = $this->actingAs($this->seller)
            ->get(route('seller.reviews'));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seller/Reviews')
            ->has('stats')
            ->where('stats.total_product_reviews', 4)
            ->where('stats.product_rating_avg', fn ($v) => (float) $v == 3.75)
            ->where('stats.product_distribution.5.count', 2)
            ->where('stats.product_distribution.5.percentage', fn ($v) => (float) $v == 50.0)
            ->where('stats.product_distribution.4.count', 1)
            ->where('stats.product_distribution.4.percentage', fn ($v) => (float) $v == 25.0)
            ->where('stats.product_distribution.1.count', 1)
            ->where('stats.product_distribution.1.percentage', fn ($v) => (float) $v == 25.0)
        );
    }

    /**
     * SELLER-33: Seller rating & review management (direct store ratings alongside product reviews).
     */
    public function test_seller_can_view_and_manage_direct_store_ratings_alongside_product_reviews(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-REV-99',
            'user_id' => $this->buyer->id,
            'subtotal' => 3500.00,
            'total' => 3500.00,
            'status' => 'delivered',
            'shipping_address' => 'Sample Address',
            'payment_method' => 'cod',
        ]);

        $storeReview = SellerReview::create([
            'user_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'order_id' => $order->id,
            'rating' => 5,
            'comment' => 'Shop responded instantly and shipped the item within hours!',
        ]);

        // View store ratings tab
        $response = $this->actingAs($this->seller)
            ->get(route('seller.reviews', ['tab' => 'store']));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seller/Reviews')
            ->where('tab', 'store')
            ->has('storeReviews.data', 1)
            ->where('stats.total_store_reviews', 1)
            ->where('stats.store_rating_avg', fn ($v) => (float) $v == 5.0)
        );

        // Reply to the store rating
        $replyResponse = $this->actingAs($this->seller)
            ->post(route('seller.store-reviews.reply', $storeReview->id), [
                'seller_reply' => 'Thank you Maria! We always strive for rapid dispatch.',
            ]);

        $replyResponse->assertRedirect();
        $this->assertDatabaseHas('seller_reviews', [
            'id' => $storeReview->id,
            'seller_reply' => 'Thank you Maria! We always strive for rapid dispatch.',
        ]);
    }

    /**
     * SELLER-32: Report inappropriate review to platform admins.
     */
    public function test_seller_can_report_inappropriate_review_to_platform_admins(): void
    {
        $abusiveReview = Review::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'rating' => 1,
            'comment' => 'Profane abusive defamatory statement.',
        ]);

        $response = $this->actingAs($this->seller)
            ->post(route('seller.reviews.report', $abusiveReview->id), [
                'reason' => 'abusive_language',
                'notes' => 'Customer is using foul and harassing language violating marketplace terms.',
            ]);

        $response->assertRedirect();

        // 1. Verify review is marked reported
        $this->assertDatabaseHas('reviews', [
            'id' => $abusiveReview->id,
            'is_reported' => true,
            'report_reason' => 'abusive_language',
            'report_status' => 'pending',
        ]);

        // 2. Verify platform admin complaint ticket was created
        $this->assertDatabaseHas('complaints', [
            'seller_id' => $this->seller->id,
            'buyer_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'type' => 'review_report',
            'status' => 'pending',
        ]);

        // 3. Prevent duplicate report
        $dupResponse = $this->actingAs($this->seller)
            ->post(route('seller.reviews.report', $abusiveReview->id), [
                'reason' => 'abusive_language',
                'notes' => 'Trying to report again.',
            ]);

        $dupResponse->assertSessionHasErrors('report');
    }

    /**
     * SELLER-32: Seller can also report fraudulent direct store ratings.
     */
    public function test_seller_can_report_fraudulent_store_rating_to_platform_admins(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-FAKE-01',
            'user_id' => $this->buyer->id,
            'subtotal' => 1000.00,
            'total' => 1000.00,
            'status' => 'delivered',
            'shipping_address' => 'Sample Address',
            'payment_method' => 'cod',
        ]);

        $fakeStoreReview = SellerReview::create([
            'user_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'order_id' => $order->id,
            'rating' => 1,
            'comment' => 'Give me 1000 pesos or I will not remove this 1-star rating.',
        ]);

        $response = $this->actingAs($this->seller)
            ->post(route('seller.store-reviews.report', $fakeStoreReview->id), [
                'reason' => 'extortion_or_blackmail',
                'notes' => 'Buyer is attempting review blackmail for monetary gain.',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('seller_reviews', [
            'id' => $fakeStoreReview->id,
            'is_reported' => true,
            'report_reason' => 'extortion_or_blackmail',
        ]);

        $this->assertDatabaseHas('complaints', [
            'seller_id' => $this->seller->id,
            'buyer_id' => $this->buyer->id,
            'order_id' => $order->id,
            'type' => 'review_report',
        ]);
    }

    /**
     * SELLER-34: Review media viewer support (customer-uploaded photos and videos).
     */
    public function test_review_with_media_is_accessible_for_lightbox_viewer(): void
    {
        $mediaReview = Review::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'rating' => 5,
            'comment' => 'Works smoothly! Here are photos and a test video.',
            'media' => [
                'reviews/drill_photo_1.jpg',
                'reviews/drill_photo_2.jpg',
                'reviews/drill_video.mp4',
            ],
        ]);

        $response = $this->actingAs($this->seller)
            ->get(route('seller.reviews', ['has_media' => 1]));

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Seller/Reviews')
            ->has('reviews.data', 1)
            ->where('reviews.data.0.id', $mediaReview->id)
            ->where('reviews.data.0.media', [
                'reviews/drill_photo_1.jpg',
                'reviews/drill_photo_2.jpg',
                'reviews/drill_video.mp4',
            ])
        );
    }
}
