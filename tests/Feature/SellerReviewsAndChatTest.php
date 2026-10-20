<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerReviewsAndChatTest extends TestCase
{
    use RefreshDatabase;

    private User $seller1;
    private User $seller2;
    private User $buyer1;
    private Product $productP02;
    private ProductVariant $variantNatural;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->seller1 = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Store 1',
            'street_address' => '123 Market St',
            'barangay' => 'San Antonio',
            'municipality' => 'Pasig',
            'province' => 'Metro Manila',
        ]);

        $this->seller2 = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Store 2',
        ]);

        $this->buyer1 = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
            'name' => 'Buyer One',
            'email' => 'buyer1@example.com',
            'email_verified_at' => now(),
        ]);

        $category = Category::create([
            'name' => 'Handicrafts',
            'slug' => 'handicrafts',
        ]);

        $this->productP02 = Product::create([
            'seller_id' => $this->seller1->id,
            'category_id' => $category->id,
            'name' => 'Woven Basket P-02',
            'slug' => 'woven-basket-p-02',
            'price' => 450.00,
            'stock' => 50,
            'status' => 'approved',
            'description' => 'Authentic handwoven storage basket made from natural bamboo fibers.',
        ]);

        $this->variantNatural = ProductVariant::create([
            'product_id' => $this->productP02->id,
            'color' => 'Natural',
            'size' => 'Medium',
            'price' => 450.00,
            'stock' => 25,
            'sku' => 'WB-P02-MED-NAT',
        ]);
    }

    /**
     * SEL-REV-001: View and filter reviews.
     * Logged in as Seller 1 (Approved). P-02 has a 5-star, a 3-star, and a 1-star review.
     * 1. Open Reviews.
     * 2. Filter by 1 star, then 5 stars.
     * Expected Result: All 3 reviews are listed with stars, text, photos, variant and date.
     * Each filter shows only matching reviews.
     */
    public function test_sel_rev_001_view_and_filter_reviews(): void
    {
        // Create 3 reviews for P-02: 5-star, 3-star, 1-star
        $review5 = Review::create([
            'user_id' => $this->buyer1->id,
            'product_id' => $this->productP02->id,
            'variant_id' => $this->variantNatural->id,
            'variant_name' => $this->variantNatural->title,
            'rating' => 5,
            'comment' => 'Super high quality craftsmanship! Exactly as pictured.',
            'media' => ['reviews/photo1.jpg', 'reviews/photo2.jpg'],
            'created_at' => now()->subDays(5),
        ]);

        $buyer2 = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $review3 = Review::create([
            'user_id' => $buyer2->id,
            'product_id' => $this->productP02->id,
            'variant_id' => $this->variantNatural->id,
            'variant_name' => $this->variantNatural->title,
            'rating' => 3,
            'comment' => 'Good basket but shipping took a little longer than expected.',
            'media' => [],
            'created_at' => now()->subDays(3),
        ]);

        $buyer3 = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $review1 = Review::create([
            'user_id' => $buyer3->id,
            'product_id' => $this->productP02->id,
            'variant_id' => $this->variantNatural->id,
            'variant_name' => $this->variantNatural->title,
            'rating' => 1,
            'comment' => 'Handle was damaged upon arrival. Very disappointed.',
            'media' => ['reviews/damage.jpg'],
            'created_at' => now()->subDay(),
        ]);

        // 1. Open Reviews (all reviews listed)
        $responseAll = $this->actingAs($this->seller1)->get(route('seller.reviews'));
        $responseAll->assertOk();
        $responseAll->assertInertia(function ($page) {
            $reviews = $page->toArray()['props']['reviews']['data'];
            $this->assertCount(3, $reviews);

            // Check variant, stars, text, photos, date
            $rev5 = collect($reviews)->firstWhere('rating', 5);
            $this->assertNotNull($rev5);
            $this->assertSame('Super high quality craftsmanship! Exactly as pictured.', $rev5['comment']);
            $this->assertCount(2, $rev5['media']);
            $this->assertSame($this->variantNatural->title, $rev5['variant_name'] ?? $rev5['variant']['title']);
            $this->assertNotEmpty($rev5['created_at']);
        });

        // 2. Filter by 1 star
        $response1Star = $this->actingAs($this->seller1)->get(route('seller.reviews', ['rating' => 1]));
        $response1Star->assertOk();
        $response1Star->assertInertia(function ($page) {
            $reviews = $page->toArray()['props']['reviews']['data'];
            $this->assertCount(1, $reviews);
            $this->assertSame(1, $reviews[0]['rating']);
            $this->assertSame('Handle was damaged upon arrival. Very disappointed.', $reviews[0]['comment']);
        });

        // 3. Filter by 5 stars
        $response5Star = $this->actingAs($this->seller1)->get(route('seller.reviews', ['rating' => 5]));
        $response5Star->assertOk();
        $response5Star->assertInertia(function ($page) {
            $reviews = $page->toArray()['props']['reviews']['data'];
            $this->assertCount(1, $reviews);
            $this->assertSame(5, $reviews[0]['rating']);
            $this->assertSame('Super high quality craftsmanship! Exactly as pictured.', $reviews[0]['comment']);
        });
    }

    /**
     * SEL-REV-002: Reply to a review.
     * Logged in as Seller 1 (Approved).
     * 1. Reply to the 5-star review: "Salamat po! Enjoy your basket."
     * 2. As Buyer 1, open P-02's reviews.
     * Expected Result: The reply appears under that review, labelled as the seller's response.
     */
    public function test_sel_rev_002_reply_to_a_review(): void
    {
        $review = Review::create([
            'user_id' => $this->buyer1->id,
            'product_id' => $this->productP02->id,
            'variant_id' => $this->variantNatural->id,
            'variant_name' => $this->variantNatural->title,
            'rating' => 5,
            'comment' => 'Beautiful weaving!',
        ]);

        $replyText = 'Salamat po! Enjoy your basket.';

        // Seller replies to the 5-star review
        $response = $this->actingAs($this->seller1)
            ->post(route('seller.reviews.reply', $review->id), [
                'seller_reply' => $replyText,
            ]);

        $response->assertRedirect();
        $review->refresh();

        $this->assertSame($replyText, $review->seller_reply);
        $this->assertNotNull($review->seller_replied_at);

        // Buyer opens product reviews page
        $buyerResponse = $this->actingAs($this->buyer1)
            ->get(route('buyer.product.reviews', $this->productP02->id));

        $buyerResponse->assertOk();
        $buyerResponse->assertInertia(function ($page) use ($replyText) {
            $revs = $page->toArray()['props']['reviews']['data'];
            $this->assertNotEmpty($revs);
            $this->assertSame($replyText, $revs[0]['seller_reply']);
        });

        // Buyer also views product show page
        $showResponse = $this->actingAs($this->buyer1)
            ->get(route('buyer.product', $this->productP02->id));

        $showResponse->assertOk();
        $showResponse->assertInertia(function ($page) use ($replyText) {
            $reviews = $page->toArray()['props']['product']['reviews'];
            $this->assertNotEmpty($reviews);
            $this->assertSame($replyText, $reviews[0]['seller_reply']);
        });
    }

    /**
     * SEL-REV-003: Second reply and edit reply.
     * Logged in as Seller 1 (Approved). A reply exists.
     * 1. Try to post a second reply to the same review.
     * 2. Edit the first reply: "Salamat po sa tiwala!"
     * Expected Result: A second reply is not possible. The edit saves and replaces the first reply.
     */
    public function test_sel_rev_003_second_reply_and_edit_reply(): void
    {
        $review = Review::create([
            'user_id' => $this->buyer1->id,
            'product_id' => $this->productP02->id,
            'rating' => 5,
            'comment' => 'Love it!',
            'seller_reply' => 'Salamat po! Enjoy your basket.',
            'seller_replied_at' => now()->subDay(),
        ]);

        // 1. Try to post a second reply without edit flag/method
        $secondReplyResponse = $this->actingAs($this->seller1)
            ->post(route('seller.reviews.reply', $review->id), [
                'seller_reply' => 'Another reply attempt',
            ]);

        $secondReplyResponse->assertSessionHasErrors(['seller_reply']);
        $review->refresh();
        $this->assertSame('Salamat po! Enjoy your basket.', $review->seller_reply);

        // 2. Edit the first reply using edit flag or PUT
        $editResponse = $this->actingAs($this->seller1)
            ->post(route('seller.reviews.reply', $review->id), [
                'seller_reply' => 'Salamat po sa tiwala!',
                'is_edit' => true,
            ]);

        $editResponse->assertRedirect();
        $review->refresh();

        // The edit saves and replaces the first reply
        $this->assertSame('Salamat po sa tiwala!', $review->seller_reply);

        // Also test PUT/PATCH method works as edit
        $putResponse = $this->actingAs($this->seller1)
            ->put(route('seller.reviews.reply', $review->id), [
                'seller_reply' => 'Salamat po sa tiwala! Warm regards.',
            ]);

        $putResponse->assertRedirect();
        $review->refresh();
        $this->assertSame('Salamat po sa tiwala! Warm regards.', $review->seller_reply);
    }

    /**
     * SEL-REV-004: Seller cannot delete or hide a review.
     * Logged in as Seller 1 (Approved).
     * 1. Look for Delete or Hide on the 1-star review.
     * 2. Try a delete request directly if possible.
     * Expected Result: No Delete or Hide option. A direct request is rejected (403).
     * The review is still visible to buyers.
     */
    public function test_sel_rev_004_seller_cannot_delete_or_hide_a_review(): void
    {
        $review1Star = Review::create([
            'user_id' => $this->buyer1->id,
            'product_id' => $this->productP02->id,
            'rating' => 1,
            'comment' => 'Arrived broken.',
        ]);

        // Direct delete request via seller route is rejected (403)
        $sellerDeleteResponse = $this->actingAs($this->seller1)
            ->delete(route('seller.reviews.destroy', $review1Star->id));

        $sellerDeleteResponse->assertForbidden();

        // Direct delete attempt via buyer delete route is also rejected (403)
        $buyerRouteDelete = $this->actingAs($this->seller1)
            ->delete(route('buyer.reviews.destroy', $review1Star->id));

        $buyerRouteDelete->assertForbidden();

        // Verify review is still in database and visible to buyers
        $this->assertDatabaseHas('reviews', [
            'id' => $review1Star->id,
            'rating' => 1,
            'comment' => 'Arrived broken.',
        ]);

        $buyerResponse = $this->actingAs($this->buyer1)
            ->get(route('buyer.product.reviews', $this->productP02->id));

        $buyerResponse->assertOk();
        $buyerResponse->assertInertia(function ($page) use ($review1Star) {
            $data = $page->toArray()['props']['reviews']['data'];
            $this->assertNotEmpty($data);
            $this->assertSame($review1Star->id, $data[0]['id']);
        });
    }

    /**
     * SEL-REV-005: Chat with a buyer, with attachment.
     * Logged in as Seller 1 (Approved). Buyer 1 sent a message about P-02.
     * 1. Reply with text and attach review_photo.jpg.
     * 2. As Buyer 1, open the chat.
     * Expected Result: Buyer 1 receives the text and the image in the right order with timestamps.
     */
    public function test_sel_rev_005_chat_with_a_buyer_with_attachment(): void
    {
        $conversation = Conversation::create([
            'buyer_id' => $this->buyer1->id,
            'seller_id' => $this->seller1->id,
            'product_id' => $this->productP02->id,
            'type' => 'buyer_seller',
            'last_message_at' => now()->subHours(2),
        ]);

        // Buyer sent initial inquiry
        $conversation->messages()->create([
            'sender_id' => $this->buyer1->id,
            'body' => 'Hi seller, is P-02 woven basket currently available in Medium?',
            'created_at' => now()->subHours(2),
        ]);

        // Seller replies with text and attachment
        $file = UploadedFile::fake()->image('review_photo.jpg', 600, 600);
        $replyText = 'Opo, available pa.';

        $sendResponse = $this->actingAs($this->seller1)
            ->post(route('seller.messages.store', $conversation->id), [
                'body' => $replyText,
                'attachment' => $file,
            ]);

        $sendResponse->assertRedirect();

        // Verify message was stored in conversation with both body and image attachment
        $sellerMessage = $conversation->messages()->latest('id')->first();
        $this->assertNotNull($sellerMessage);
        $this->assertSame($this->seller1->id, $sellerMessage->sender_id);
        $this->assertSame($replyText, $sellerMessage->body);
        $this->assertSame('review_photo.jpg', $sellerMessage->attachment_name);
        $this->assertSame('image', $sellerMessage->attachment_type);
        $this->assertNotNull($sellerMessage->attachment_path);
        Storage::disk('public')->assertExists($sellerMessage->attachment_path);

        // Buyer opens conversation
        $buyerChatResponse = $this->actingAs($this->buyer1)
            ->get(route('buyer.conversations', ['conversation' => $conversation->id]));

        $buyerChatResponse->assertOk();
        $buyerChatResponse->assertInertia(function ($page) use ($replyText) {
            $messages = $page->toArray()['props']['messages'];
            $this->assertCount(2, $messages);

            // In right chronological order
            $this->assertStringContainsString('available in Medium?', $messages[0]['body']);
            $this->assertSame($replyText, $messages[1]['body']);
            $this->assertSame('review_photo.jpg', $messages[1]['attachment_name']);
            $this->assertNotEmpty($messages[1]['created_at']);
        });
    }

    /**
     * SEL-REV-006: Invalid chat attachments.
     * Logged in as Seller 1 (Approved).
     * 1. Attach each file:
     *    - size_5.1MB.jpg (reject: > 5MB)
     *    - installer.exe (reject: non-image)
     *    - renamed_exe.jpg (reject: disguised executable / not a real image by content)
     * Expected Result: All three are rejected with a message and nothing is sent.
     */
    public function test_sel_rev_006_invalid_chat_attachments(): void
    {
        $conversation = Conversation::create([
            'buyer_id' => $this->buyer1->id,
            'seller_id' => $this->seller1->id,
            'type' => 'buyer_seller',
            'last_message_at' => now(),
        ]);

        $initialCount = $conversation->messages()->count();

        // 1. File size 5.1MB (> 5MB / 5120 KB limit)
        $largeFile = UploadedFile::fake()->create('size_5.1MB.jpg', 5220, 'image/jpeg');
        $resp1 = $this->actingAs($this->seller1)
            ->post(route('seller.messages.store', $conversation->id), [
                'body' => 'Here is a large image',
                'attachment' => $largeFile,
            ]);

        $resp1->assertSessionHasErrors(['attachment']);
        $this->assertSame($initialCount, $conversation->messages()->count());

        // 2. installer.exe (executable / forbidden extension and mime)
        $exeFile = UploadedFile::fake()->create('installer.exe', 100, 'application/x-msdownload');
        $resp2 = $this->actingAs($this->seller1)
            ->post(route('seller.messages.store', $conversation->id), [
                'body' => 'Here is an installer',
                'attachment' => $exeFile,
            ]);

        $resp2->assertSessionHasErrors(['attachment']);
        $this->assertSame($initialCount, $conversation->messages()->count());

        // 3. renamed_exe.jpg (executable disguised as .jpg with MZ magic bytes)
        $fakeJpgExe = UploadedFile::fake()->createWithContent(
            'renamed_exe.jpg',
            "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xff\xff\x00\x00This program cannot be run in DOS mode."
        );

        $resp3 = $this->actingAs($this->seller1)
            ->post(route('seller.messages.store', $conversation->id), [
                'body' => 'Disguised executable',
                'attachment' => $fakeJpgExe,
            ]);

        $resp3->assertSessionHasErrors(['attachment']);
        $this->assertSame($initialCount, $conversation->messages()->count());
    }

    /**
     * SEL-REV-007: Message length and empty message.
     * Logged in as Seller 1 (Approved).
     * 1. Send an empty message.
     * 2. Send a message of spaces only.
     * 3. Send 1,000 characters.
     * 4. Send 1,001 characters.
     * Expected Result: Empty and spaces-only messages are not sent. 1,000 characters are sent in full.
     * 1,001 are blocked or input stops at 1,000.
     */
    public function test_sel_rev_007_message_length_and_empty_message(): void
    {
        $conversation = Conversation::create([
            'buyer_id' => $this->buyer1->id,
            'seller_id' => $this->seller1->id,
            'type' => 'buyer_seller',
            'last_message_at' => now(),
        ]);

        $initialCount = $conversation->messages()->count();

        // 1. Empty message
        $emptyResp = $this->actingAs($this->seller1)
            ->post(route('seller.messages.store', $conversation->id), [
                'body' => '',
            ]);

        $emptyResp->assertSessionHasErrors(['body']);
        $this->assertSame($initialCount, $conversation->messages()->count());

        // 2. Spaces-only message
        $spacesResp = $this->actingAs($this->seller1)
            ->post(route('seller.messages.store', $conversation->id), [
                'body' => '     ',
            ]);

        $spacesResp->assertSessionHasErrors(['body']);
        $this->assertSame($initialCount, $conversation->messages()->count());

        // 3. Exactly 1,000 characters
        $msg1000 = str_repeat('A', 1000);
        $valid1000Resp = $this->actingAs($this->seller1)
            ->post(route('seller.messages.store', $conversation->id), [
                'body' => $msg1000,
            ]);

        $valid1000Resp->assertRedirect();
        $this->assertSame($initialCount + 1, $conversation->messages()->count());
        $savedMsg = $conversation->messages()->latest('id')->first();
        $this->assertSame($msg1000, $savedMsg->body);
        $this->assertSame(1000, strlen($savedMsg->body));

        // 4. 1,001 characters (blocked)
        $msg1001 = str_repeat('B', 1001);
        $overLimitResp = $this->actingAs($this->seller1)
            ->post(route('seller.messages.store', $conversation->id), [
                'body' => $msg1001,
            ]);

        $overLimitResp->assertSessionHasErrors(['body']);
        $this->assertSame($initialCount + 1, $conversation->messages()->count());
    }
}

