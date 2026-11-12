<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BuyerRatingsAndReviewsTest extends TestCase
{
    use RefreshDatabase;

    protected User $buyer;
    protected User $otherBuyer;
    protected User $seller;
    protected Category $category;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->seller = User::factory()->create([
            'name' => 'Review Test Seller',
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'ReviewStore',
        ]);

        $this->buyer = User::factory()->create([
            'name' => 'Review Test Buyer',
            'usertype' => 'buyer',
            'email' => 'buyer.review@example.com',
            'status' => 'approved',
        ]);

        $this->otherBuyer = User::factory()->create([
            'name' => 'Other Buyer',
            'usertype' => 'buyer',
            'email' => 'other.review@example.com',
            'status' => 'approved',
        ]);

        $this->category = Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics-' . uniqid(),
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'name' => 'Wireless Noise Canceling Headphones',
            'slug' => 'headphones-' . uniqid(),
            'description' => 'Test headphones description',
            'price' => 2500.00,
            'stock' => 50,
            'rating' => 0,
            'reviews_count' => 0,
            'status' => 'approved',
            'approval_status' => 'approved',
        ]);
    }

    /**
     * Helper to create an order with an item in given status for a user.
     */
    protected function createOrderWithItem(User $buyer, string $orderStatus, string $itemStatus, ?Product $product = null): array
    {
        $targetProduct = $product ?? $this->product;

        $order = Order::create([
            'user_id' => $buyer->id,
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'status' => $orderStatus,
            'subtotal' => $targetProduct->price,
            'shipping_fee' => 50.00,
            'total' => $targetProduct->price + 50.00,
            'shipping_address' => '123 Main St, Manila',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $targetProduct->id,
            'product_name' => $targetProduct->name,
            'price' => $targetProduct->price,
            'quantity' => 1,
            'subtotal' => $targetProduct->price,
            'status' => $itemStatus,
        ]);

        return [$order, $item];
    }

    /**
     * BUY-RATE-001: Rate a completed order
     * Submit 5 stars, review text, and photo; appears on product page with stars.
     */
    public function test_buy_rate_001_rate_completed_order(): void
    {
        [$order, $item] = $this->createOrderWithItem($this->buyer, 'completed', 'completed');

        $photo = UploadedFile::fake()->image('review1.jpg', 600, 600);

        $response = $this->actingAs($this->buyer)
            ->post(route('buyer.reviews.store', $this->product->id), [
                'rating' => 5,
                'comment' => 'Excellent headphones! Arrived super fast and sound is crystal clear.',
                'order_item_id' => $item->id,
                'media' => [$photo],
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // Review is saved in database
        $this->assertDatabaseHas('reviews', [
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'order_item_id' => $item->id,
            'rating' => 5,
            'comment' => 'Excellent headphones! Arrived super fast and sound is crystal clear.',
            'edit_count' => 0,
        ]);

        $review = Review::where('user_id', $this->buyer->id)
            ->where('product_id', $this->product->id)
            ->first();

        $this->assertNotNull($review);
        $this->assertCount(1, $review->media);
        $this->assertTrue($review->can_edit);

        // Appears on product reviews page
        $reviewsResponse = $this->actingAs($this->buyer)
            ->get(route('buyer.product.reviews', $this->product->id));

        $reviewsResponse->assertOk();
        $reviewsResponse->assertInertia(function (Assert $page) use ($review) {
            $page->component('Buyer/ProductReviews')
                ->where('product.id', $this->product->id)
                ->where('product.rating', 5)
                ->where('product.reviews_count', 1)
                ->has('reviews.data', 1)
                ->where('reviews.data.0.id', $review->id)
                ->where('reviews.data.0.rating', 5)
                ->where('reviews.data.0.comment', 'Excellent headphones! Arrived super fast and sound is crystal clear.');
        });
    }

    /**
     * BUY-RATE-002: Rating not allowed before completion
     * Cannot rate order while in transit; rate button is hidden / request rejected until completed.
     */
    public function test_buy_rate_002_rating_not_allowed_before_completion(): void
    {
        // Order is still in transit (shipped)
        [$order, $item] = $this->createOrderWithItem($this->buyer, 'shipped', 'shipped');

        // Order Details page verifies item is not reviewable while in transit
        $detailsResponse = $this->actingAs($this->buyer)
            ->get(route('buyer.orders.show', $order->id));

        $detailsResponse->assertOk();
        $detailsResponse->assertInertia(function (Assert $page) {
            $page->component('Buyer/OrderDetails')
                ->where('order.items.0.can_review', false)
                ->where('order.items.0.has_reviewed', false);
        });

        // Direct submission attempt is rejected
        $submitResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.reviews.store', $this->product->id), [
                'rating' => 5,
                'comment' => 'Tried to review early while in transit.',
                'order_item_id' => $item->id,
            ]);

        $submitResponse->assertSessionHasErrors(['rating']);
        $this->assertDatabaseMissing('reviews', [
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
        ]);
    }

    /**
     * BUY-RATE-003: One review per item
     * Cannot submit second review; only Edit is offered.
     */
    public function test_buy_rate_003_one_review_per_item(): void
    {
        [$order, $item] = $this->createOrderWithItem($this->buyer, 'completed', 'completed');

        // 1st review succeeds
        $firstResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.reviews.store', $this->product->id), [
                'rating' => 5,
                'comment' => 'First review of the product.',
                'order_item_id' => $item->id,
            ]);

        $firstResponse->assertSessionHasNoErrors();
        $this->assertDatabaseCount('reviews', 1);

        // Attempting to submit a second review for the same product is blocked
        $secondResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.reviews.store', $this->product->id), [
                'rating' => 4,
                'comment' => 'Second attempt to review same product.',
                'order_item_id' => $item->id,
            ]);

        $secondResponse->assertSessionHasErrors(['rating']);
        $this->assertDatabaseCount('reviews', 1);

        // On OrderDetails page, item shows has_reviewed = true and user_review with can_edit = true
        $detailsResponse = $this->actingAs($this->buyer)
            ->get(route('buyer.orders.show', $order->id));

        $detailsResponse->assertOk();
        $detailsResponse->assertInertia(function (Assert $page) {
            $page->component('Buyer/OrderDetails')
                ->where('order.items.0.has_reviewed', true)
                ->where('order.items.0.user_review.can_edit', true);
        });
    }

    /**
     * BUY-RATE-004: Stars, text and photo limits
     * 0 stars blocked; 500 characters max, 5 photos max.
     */
    public function test_buy_rate_004_stars_text_and_photo_limits(): void
    {
        [$order, $item] = $this->createOrderWithItem($this->buyer, 'completed', 'completed');

        // 1. 0 stars blocked
        $zeroStarResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.reviews.store', $this->product->id), [
                'rating' => 0,
                'comment' => 'Zero stars rating attempt',
            ]);
        $zeroStarResponse->assertSessionHasErrors(['rating']);

        // 2. >500 characters comment blocked
        $longComment = str_repeat('A', 501);
        $tooLongResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.reviews.store', $this->product->id), [
                'rating' => 4,
                'comment' => $longComment,
            ]);
        $tooLongResponse->assertSessionHasErrors(['comment']);

        // 3. >5 photos blocked
        $photos = [
            UploadedFile::fake()->image('p1.jpg'),
            UploadedFile::fake()->image('p2.jpg'),
            UploadedFile::fake()->image('p3.jpg'),
            UploadedFile::fake()->image('p4.jpg'),
            UploadedFile::fake()->image('p5.jpg'),
            UploadedFile::fake()->image('p6.jpg'),
        ];
        $tooManyPhotosResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.reviews.store', $this->product->id), [
                'rating' => 5,
                'comment' => 'Valid length comment',
                'media' => $photos,
            ]);
        $tooManyPhotosResponse->assertSessionHasErrors(['media']);

        // 4. Exactly 500 characters and 5 photos passes
        $valid500Comment = str_repeat('B', 500);
        $validPhotos = array_slice($photos, 0, 5);

        $validResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.reviews.store', $this->product->id), [
                'rating' => 5,
                'comment' => $valid500Comment,
                'media' => $validPhotos,
            ]);

        $validResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reviews', [
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'rating' => 5,
            'comment' => $valid500Comment,
        ]);
    }

    /**
     * BUY-RATE-005: Edit once within window
     * Can only edit review once, within 30 days of creation.
     */
    public function test_buy_rate_005_edit_once_within_30_days_window(): void
    {
        [$order, $item] = $this->createOrderWithItem($this->buyer, 'completed', 'completed');

        // Create initial review created 5 days ago
        $review = Review::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'order_item_id' => $item->id,
            'rating' => 5,
            'comment' => 'Original review text.',
            'edit_count' => 0,
            'created_at' => Carbon::now()->subDays(5),
            'updated_at' => Carbon::now()->subDays(5),
        ]);

        $this->assertTrue($review->can_edit);

        // 1. Edit once within 30 days succeeds
        $editResponse = $this->actingAs($this->buyer)
            ->patch(route('buyer.reviews.update', $review->id), [
                'rating' => 4,
                'comment' => 'Updated review: good, but bass is a bit strong.',
            ]);

        $editResponse->assertSessionHasNoErrors();
        $editResponse->assertRedirect();

        $review->refresh();
        $this->assertSame(4, $review->rating);
        $this->assertSame('Updated review: good, but bass is a bit strong.', $review->comment);
        $this->assertSame(1, $review->edit_count);
        $this->assertNotNull($review->edited_at);
        $this->assertFalse($review->can_edit);

        // 2. Attempting a second edit is rejected (1-edit limit enforced)
        $secondEditResponse = $this->actingAs($this->buyer)
            ->patch(route('buyer.reviews.update', $review->id), [
                'rating' => 3,
                'comment' => 'Second edit attempt should be blocked.',
            ]);

        $secondEditResponse->assertSessionHasErrors(['rating']);
        $review->refresh();
        $this->assertSame(4, $review->rating); // Unchanged
        $this->assertSame(1, $review->edit_count);

        // 3. Attempting to edit a review older than 30 days is rejected
        $oldProduct = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'name' => 'Old Product',
            'slug' => 'old-product-' . uniqid(),
            'description' => 'Old product description',
            'price' => 500,
            'stock' => 20,
            'status' => 'approved',
            'approval_status' => 'approved',
        ]);
        [$oldOrder, $oldItem] = $this->createOrderWithItem($this->buyer, 'completed', 'completed', $oldProduct);

        $oldReview = Review::create([
            'user_id' => $this->buyer->id,
            'product_id' => $oldProduct->id,
            'order_item_id' => $oldItem->id,
            'rating' => 5,
            'comment' => 'Initial review 35 days ago.',
            'edit_count' => 0,
            'created_at' => Carbon::now()->subDays(35),
            'updated_at' => Carbon::now()->subDays(35),
        ]);

        $this->assertFalse($oldReview->can_edit);

        $expiredEditResponse = $this->actingAs($this->buyer)
            ->patch(route('buyer.reviews.update', $oldReview->id), [
                'rating' => 2,
                'comment' => 'Attempt to edit after 30 days window.',
            ]);

        $expiredEditResponse->assertSessionHasErrors(['rating']);
        $oldReview->refresh();
        $this->assertSame(5, $oldReview->rating); // Unchanged
        $this->assertSame(0, $oldReview->edit_count);
    }

    /**
     * BUY-RATE-006: Average rating updates
     * New review recalculates product star rating average immediately on product card.
     */
    public function test_buy_rate_006_average_rating_updates_immediately(): void
    {
        // Buyer 1 rates 5 stars
        [$order1, $item1] = $this->createOrderWithItem($this->buyer, 'completed', 'completed');
        $this->actingAs($this->buyer)
            ->post(route('buyer.reviews.store', $this->product->id), [
                'rating' => 5,
                'comment' => '5 star review from buyer 1',
                'order_item_id' => $item1->id,
            ])
            ->assertSessionHasNoErrors();

        $this->product->refresh();
        $this->assertEquals(5.0, (float) $this->product->rating);
        $this->assertSame(1, (int) $this->product->reviews_count);

        // Buyer 2 rates 2 stars
        [$order2, $item2] = $this->createOrderWithItem($this->otherBuyer, 'completed', 'completed');
        $this->actingAs($this->otherBuyer)
            ->post(route('buyer.reviews.store', $this->product->id), [
                'rating' => 2,
                'comment' => '2 star review from buyer 2',
                'order_item_id' => $item2->id,
            ])
            ->assertSessionHasNoErrors();

        $this->product->refresh();
        // (5 + 2) / 2 = 3.5
        $this->assertEquals(3.5, (float) $this->product->rating);
        $this->assertSame(2, (int) $this->product->reviews_count);

        // Verify product card / reviews response displays recalculated 3.5 average immediately
        $response = $this->actingAs($this->buyer)
            ->get(route('buyer.product.reviews', $this->product->id));

        $response->assertOk();
        $response->assertInertia(function (Assert $page) {
            $page->component('Buyer/ProductReviews')
                ->where('product.rating', 3.5)
                ->where('product.reviews_count', 2)
                ->has('reviews.data', 2);
        });
    }
}