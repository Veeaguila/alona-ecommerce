<?php

namespace Tests\Feature;

use App\Models\BuyerNotification;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\FlashSale;
use App\Models\FlashSaleReminder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\FlashSaleReminderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BuyerChatAndNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $buyer;
    protected User $seller;
    protected Category $category;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->seller = User::factory()->create([
            'name' => 'Chat Seller',
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Bohol Handicrafts Store',
        ]);

        $this->buyer = User::factory()->create([
            'name' => 'Chat Buyer',
            'usertype' => 'buyer',
            'email' => 'buyer.chat@example.com',
            'status' => 'approved',
        ]);

        $this->category = Category::create([
            'name' => 'Handicrafts',
            'slug' => 'handicrafts-' . uniqid(),
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'name' => 'Handmade Rattan Tote Bag',
            'slug' => 'handmade-rattan-tote-bag-' . uniqid(),
            'description' => 'Locally woven rattan tote bag.',
            'price' => 850.00,
            'stock' => 25,
            'status' => 'approved',
            'approval_status' => 'approved',
        ]);
    }

    /**
     * BUY-CHAT-001: Chat with seller from product
     * Opens chat with seller and shows product card.
     */
    public function test_buy_chat_001_chat_with_seller_from_product(): void
    {
        // 1. Buyer initiates chat from the product page
        $startResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.conversations.start', $this->product->id));

        $startResponse->assertRedirect();

        $conversation = Conversation::where('buyer_id', $this->buyer->id)
            ->where('seller_id', $this->seller->id)
            ->first();

        $this->assertNotNull($conversation);
        $this->assertSame($this->product->id, $conversation->product_id);

        // 2. Open conversations page and verify product card is loaded
        $conversationsResponse = $this->actingAs($this->buyer)
            ->get(route('buyer.conversations', ['conversation' => $conversation->id]));

        $conversationsResponse->assertOk();
        $conversationsResponse->assertInertia(function (Assert $page) use ($conversation) {
            $page->component('Buyer/Conversations')
                ->where('selectedConversation.id', $conversation->id)
                ->where('selectedConversation.product.id', $this->product->id)
                ->where('selectedConversation.product.name', 'Handmade Rattan Tote Bag')
                ->where('selectedConversation.seller.id', $this->seller->id);
        });
    }

    /**
     * BUY-CHAT-002: Invalid attachments and message length
     * EXE files and huge images rejected; text limit 1,000 chars.
     */
    public function test_buy_chat_002_invalid_attachments_and_message_length(): void
    {
        $conversation = Conversation::create([
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'product_id' => $this->product->id,
            'last_message_at' => now(),
        ]);

        // 1. EXE file rejected
        $exeFile = UploadedFile::fake()->create('malware.exe', 500, 'application/x-msdownload');
        $this->actingAs($this->buyer)
            ->post(route('buyer.conversations.store', $conversation->id), [
                'body' => 'Check this file',
                'attachment' => $exeFile,
            ])
            ->assertSessionHasErrors(['attachment']);

        // 2. Huge file (> 5 MB) rejected
        $hugeImage = UploadedFile::fake()->image('huge.jpg')->size(6000); // 6 MB
        $this->actingAs($this->buyer)
            ->post(route('buyer.conversations.store', $conversation->id), [
                'body' => 'Here is a photo',
                'attachment' => $hugeImage,
            ])
            ->assertSessionHasErrors(['attachment']);

        // 3. Message over 1,000 characters rejected
        $longBody = str_repeat('A', 1001);
        $this->actingAs($this->buyer)
            ->post(route('buyer.conversations.store', $conversation->id), [
                'body' => $longBody,
            ])
            ->assertSessionHasErrors(['body']);

        // 4. Valid message and valid JPG attachment (< 5 MB) succeeds
        $validImage = UploadedFile::fake()->image('product_photo.jpg', 600, 600)->size(1500); // 1.5 MB
        $validBody = 'Is this product available in size Medium?';

        $validResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.conversations.store', $conversation->id), [
                'body' => $validBody,
                'attachment' => $validImage,
            ]);

        $validResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('conversation_messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $this->buyer->id,
            'body' => $validBody,
            'attachment_type' => 'image',
        ]);
    }

    /**
     * BUY-CHAT-003: Order status notifications
     * Status updates send notifications to buyer.
     */
    public function test_buy_chat_003_order_status_notifications(): void
    {
        $order = Order::create([
            'user_id' => $this->buyer->id,
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'status' => 'ready_for_pickup',
            'subtotal' => $this->product->price,
            'shipping_fee' => 50.00,
            'total' => $this->product->price + 50.00,
            'shipping_address' => '123 Test St, Tagbilaran City',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'price' => $this->product->price,
            'quantity' => 1,
            'subtotal' => $this->product->price,
            'status' => 'ready_for_pickup',
        ]);

        // Seller updates status to shipped
        $orderItem->moveTo('shipped', 'Parcel dispatched via local courier');

        $this->assertDatabaseHas('buyer_notifications', [
            'user_id' => $this->buyer->id,
            'title' => "Order #{$order->order_number} updated",
            'message' => 'Parcel dispatched via local courier',
        ]);

        // Update status to delivered
        $orderItem->moveTo('delivered', 'Item delivered successfully to recipient');

        $this->assertDatabaseHas('buyer_notifications', [
            'user_id' => $this->buyer->id,
            'title' => "Order #{$order->order_number} updated",
            'message' => 'Item delivered successfully to recipient',
        ]);

        // Notifications appear in buyer notifications page
        $response = $this->actingAs($this->buyer)
            ->get(route('buyer.notifications'));

        $response->assertOk();
        $response->assertInertia(function (Assert $page) {
            $page->component('Buyer/Notifications')
                ->has('notifications', 2);
        });
    }

    /**
     * BUY-CHAT-004: Flash-sale reminder
     * Reminder notification sent before flash sale slot starts.
     */
    public function test_buy_chat_004_flash_sale_reminder(): void
    {
        // Create an upcoming flash sale starting in 10 minutes
        $startsAt = Carbon::now()->addMinutes(10);
        $endsAt = Carbon::now()->addMinutes(130);

        $flashSale = FlashSale::create([
            'seller_id' => $this->seller->id,
            'product_id' => $this->product->id,
            'flash_price' => 499.00,
            'flash_quantity' => 10,
            'sold_quantity' => 0,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        // 1. Buyer subscribes / sets reminder on the upcoming flash sale
        $remindResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.flash-sales.remind', $flashSale->id));

        $remindResponse->assertSessionHasNoErrors();
        $this->assertDatabaseHas('flash_sale_reminders', [
            'user_id' => $this->buyer->id,
            'flash_sale_id' => $flashSale->id,
            'product_id' => $this->product->id,
            'notified_at' => null,
        ]);

        // 2. Product show page reflects that the reminder is active
        $this->actingAs($this->buyer)
            ->get(route('buyer.product', $this->product->id))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($flashSale) {
                $page->component('Buyer/Show')
                    ->where('product.upcoming_flash_sale.id', $flashSale->id)
                    ->where('product.upcoming_flash_sale.has_reminder', true);
            });

        // 3. Run scheduled reminder command (flash-sales:send-reminders)
        $exitCode = Artisan::call('flash-sales:send-reminders');
        $this->assertSame(0, $exitCode);

        // Reminder record updated with notified_at
        $reminder = FlashSaleReminder::where('user_id', $this->buyer->id)
            ->where('flash_sale_id', $flashSale->id)
            ->first();

        $this->assertNotNull($reminder);
        $this->assertNotNull($reminder->notified_at);

        // Buyer received flash sale alert notification
        $this->assertDatabaseHas('buyer_notifications', [
            'user_id' => $this->buyer->id,
            'type' => 'flash_sale_reminder',
            'title' => "⚡ Flash Sale Reminder: {$this->product->name}",
        ]);

        // 4. Running the command again does NOT duplicate the notification
        Artisan::call('flash-sales:send-reminders');
        $this->assertDatabaseCount('buyer_notifications', 1);
    }

    /**
     * BUY-CHAT-005: Mark notifications as read
     * "Mark all as read" clears unread badge count.
     */
    public function test_buy_chat_005_mark_notifications_as_read(): void
    {
        // Create 3 unread notifications
        BuyerNotification::create([
            'user_id' => $this->buyer->id,
            'title' => 'Promo 1',
            'message' => '50% off sitewide',
            'type' => 'promotion',
            'read_at' => null,
        ]);
        BuyerNotification::create([
            'user_id' => $this->buyer->id,
            'title' => 'Order Update',
            'message' => 'Your order is packed',
            'type' => 'order',
            'read_at' => null,
        ]);
        $singleNotification = BuyerNotification::create([
            'user_id' => $this->buyer->id,
            'title' => 'Message Alert',
            'message' => 'Seller replied to your chat',
            'type' => 'message',
            'read_at' => null,
        ]);

        // Verify initial unread count in shared props
        $initialResponse = $this->actingAs($this->buyer)
            ->get(route('buyer.notifications'));

        $initialResponse->assertOk();
        $initialResponse->assertInertia(function (Assert $page) {
            $page->component('Buyer/Notifications')
                ->where('buyerCounts.notifications', 3);
        });

        // 1. Single notification mark as read
        $this->actingAs($this->buyer)
            ->patch(route('buyer.notifications.read', $singleNotification->id))
            ->assertRedirect();

        $singleNotification->refresh();
        $this->assertNotNull($singleNotification->read_at);

        // 2. Mark all as read
        $markAllResponse = $this->actingAs($this->buyer)
            ->post(route('buyer.notifications.read-all'));

        $markAllResponse->assertRedirect();

        // All notifications for buyer now have read_at set
        $this->assertSame(0, BuyerNotification::where('user_id', $this->buyer->id)->whereNull('read_at')->count());

        // Unread badge count is now 0
        $afterResponse = $this->actingAs($this->buyer)
            ->get(route('buyer.notifications'));

        $afterResponse->assertOk();
        $afterResponse->assertInertia(function (Assert $page) {
            $page->component('Buyer/Notifications')
                ->where('buyerCounts.notifications', 0);
        });
    }
}
