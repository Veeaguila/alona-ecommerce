<?php

namespace Tests\Feature;

use App\Models\BuyerNotification;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerChatAndSupportTest extends TestCase
{
    use RefreshDatabase;

    protected User $seller;
    protected User $buyer;
    protected User $admin;
    protected Order $order;
    protected Product $product;
    protected Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'TechGear Central',
        ]);

        $this->buyer = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
            'name' => 'Maria Santos',
        ]);

        $this->admin = User::factory()->create([
            'usertype' => 'admin',
            'status' => 'approved',
            'name' => 'Platform Support Officer',
        ]);

        $category = Category::create([
            'name' => 'Accessories',
            'slug' => 'accessories',
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'seller_id' => $this->seller->id,
            'name' => 'Ergonomic Mousepad',
            'slug' => 'ergonomic-mousepad',
            'price' => 450.00,
            'stock' => 50,
            'status' => 'approved',
        ]);

        $this->order = Order::create([
            'user_id' => $this->buyer->id,
            'order_number' => 'ORD-20261015-9921',
            'total' => 450.00,
            'shipping_address' => '456 Makati Ave, Manila',
            'status' => 'processing',
            'payment_status' => 'paid',
            'payment_method' => 'cod',
        ]);

        OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'price' => 450.00,
            'quantity' => 1,
            'status' => 'processing',
        ]);

        $this->conversation = Conversation::create([
            'type' => 'buyer_seller',
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'last_message_at' => now(),
        ]);
    }

    /**
     * SELLER-42: Media attachment in customer chat thread.
     */
    public function test_seller_can_send_media_attachment_in_chat(): void
    {
        $fakeImage = UploadedFile::fake()->image('defect_proof.png', 600, 600);

        $response = $this->actingAs($this->seller)
            ->post(route('seller.messages.store', $this->conversation->id), [
                'body' => 'Here is a photo of the packed parcel before carrier dispatch.',
                'attachment' => $fakeImage,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->seller->id,
            'attachment_name' => 'defect_proof.png',
            'attachment_type' => 'image',
        ]);

        $msg = ConversationMessage::where('attachment_name', 'defect_proof.png')->first();
        $this->assertNotNull($msg);
        $this->assertNotNull($msg->attachment_path);
        Storage::disk('public')->assertExists($msg->attachment_path);
    }

    /**
     * SELLER-43: Embed interactive order snippet in buyer-seller chat.
     */
    public function test_seller_can_embed_order_snippet_in_chat(): void
    {
        $response = $this->actingAs($this->seller)
            ->post(route('seller.messages.store', $this->conversation->id), [
                'body' => 'Reviewing your recent order status.',
                'order_id' => $this->order->id,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->seller->id,
            'order_id' => $this->order->id,
        ]);

        // Verify thread endpoint returns messages loaded with order details
        $threadResponse = $this->actingAs($this->seller)
            ->get(route('seller.messages.show', $this->conversation->id));

        $threadResponse->assertOk();
    }

    /**
     * SELLER-44: Seller can open support chat thread with platform administration.
     */
    public function test_seller_can_open_support_ticket_with_platform_admin(): void
    {
        $fakeDoc = UploadedFile::fake()->create('business_permit_update.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->seller)
            ->post(route('seller.messages.support'), [
                'subject' => 'Store Verification & Business Documents',
                'body' => 'I have uploaded the renewed DTI certification for store approval review.',
                'order_id' => $this->order->id,
                'attachment' => $fakeDoc,
            ]);

        $this->assertDatabaseHas('conversations', [
            'type' => 'seller_admin',
            'seller_id' => $this->seller->id,
            'subject' => 'Store Verification & Business Documents',
            'status' => 'open',
        ]);

        $supportConv = Conversation::where('type', 'seller_admin')->where('seller_id', $this->seller->id)->first();
        $this->assertNotNull($supportConv);
        $response->assertRedirect(route('seller.messages.show', $supportConv->id));

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $supportConv->id,
            'sender_id' => $this->seller->id,
            'attachment_name' => 'business_permit_update.pdf',
            'attachment_type' => 'file',
            'order_id' => $this->order->id,
        ]);
    }

    /**
     * SELLER-44: Platform Admin can view and reply to seller support conversation.
     */
    public function test_admin_can_reply_to_seller_support_ticket_and_notify_seller(): void
    {
        $supportConv = Conversation::create([
            'type' => 'seller_admin',
            'seller_id' => $this->seller->id,
            'buyer_id' => null,
            'subject' => 'Payout & Wallet Balance Inquiry',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $supportConv->messages()->create([
            'sender_id' => $this->seller->id,
            'body' => 'When will my escrow balance for last week be released?',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.messages.store', $supportConv->id), [
                'body' => 'Your withdrawal batch has been approved and processed to your GCash wallet.',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $supportConv->id,
            'sender_id' => $this->admin->id,
            'body' => 'Your withdrawal batch has been approved and processed to your GCash wallet.',
        ]);

        // Verify seller received an alert notification
        $this->assertDatabaseHas('seller_notifications', [
            'user_id' => $this->seller->id,
            'type' => 'support_reply',
            'title' => 'Platform Support Replied',
        ]);
    }

    /**
     * Buyer can also attach files and reference orders in customer chat.
     */
    public function test_buyer_can_send_media_attachment_and_share_order(): void
    {
        $fakeImg = UploadedFile::fake()->image('item_inquiry.jpg', 400, 400);

        $response = $this->actingAs($this->buyer)
            ->post(route('buyer.conversations.store', $this->conversation->id), [
                'body' => 'Is this the color variant currently in stock?',
                'attachment' => $fakeImg,
                'order_id' => $this->order->id,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->buyer->id,
            'attachment_name' => 'item_inquiry.jpg',
            'order_id' => $this->order->id,
        ]);

        // Verify seller was notified of buyer's message
        $this->assertDatabaseHas('seller_notifications', [
            'user_id' => $this->seller->id,
            'type' => 'customer_message',
            'title' => "New message from {$this->buyer->name}",
        ]);
    }
}
