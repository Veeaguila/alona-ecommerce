<?php

namespace Tests\Feature;

use App\Models\BuyerNotification;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\FlashSale;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturnRequest;
use App\Models\Parcel;
use App\Models\PickupRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Rider;
use App\Models\SellerNotification;
use App\Models\SellerPromotion;
use App\Models\SortingCenter;
use App\Models\User;
use App\Services\Payments\DistributionLedger;
use App\Services\RealtimeBroadcastService;
use App\Services\RealtimeEventStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RealtimeWebsiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'broadcasting.default' => 'reverb',
            'logging.default' => 'null',
        ]);
        Cache::flush();
        RealtimeBroadcastService::resetDeduplication();
    }

    public function test_role_based_private_channel_authorization_enforces_strict_access(): void
    {
        $buyer = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $otherBuyer = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $seller = User::factory()->create(['usertype' => 'seller', 'status' => 'approved']);
        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']);

        $scUser = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        $center = SortingCenter::create([
            'user_id' => $scUser->id,
            'business_name' => 'Manila Hub',
            'address' => 'Manila',
            'contact_person' => 'Hub Manager',
            'contact_number' => '09171234567',
            'approval_status' => 'approved',
        ]);

        $riderUser = User::factory()->create(['usertype' => 'rider', 'status' => 'approved']);
        Rider::create([
            'user_id' => $riderUser->id,
            'sorting_center_id' => $center->id,
            'name' => 'Rider One',
            'contact' => '09181234567',
            'vehicle_type' => 'motorcycle',
            'plate_number' => 'ABC-123',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        // Unauthenticated cannot access private channels
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-buyer.{$buyer->id}",
        ])->assertStatus(403);

        // Buyer can access own channel, denied on another buyer's channel, seller channel, and admin channel
        $this->actingAs($buyer)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => "private-buyer.{$buyer->id}",
            ])
            ->assertOk()
            ->assertJsonPath('authorized', true);

        $this->actingAs($buyer)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => "private-buyer.{$otherBuyer->id}",
            ])
            ->assertStatus(403);

        $this->actingAs($buyer)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-admin.global',
            ])
            ->assertStatus(403);

        // Seller can access own seller channel, denied on admin channel
        $this->actingAs($seller)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => "private-seller.{$seller->id}",
            ])
            ->assertOk()
            ->assertJsonPath('authorized', true);

        $this->actingAs($seller)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-admin.global',
            ])
            ->assertStatus(403);

        // Admin can access admin.global
        $this->actingAs($admin)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-admin.global',
            ])
            ->assertOk()
            ->assertJsonPath('authorized', true);

        // Sorting Center can access its own center channel, denied on another center
        $this->actingAs($scUser)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => "private-logistics.center.{$center->id}",
            ])
            ->assertOk()
            ->assertJsonPath('authorized', true);

        $this->actingAs($scUser)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-logistics.center.99999',
            ])
            ->assertStatus(403);

        // Rider can access own rider channel and center riders channel, denied on another center
        $this->actingAs($riderUser)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => "private-riders.center.{$center->id}",
            ])
            ->assertOk()
            ->assertJsonPath('authorized', true);

        $this->actingAs($riderUser)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => 'private-riders.center.99999',
            ])
            ->assertStatus(403);
    }

    public function test_realtime_inventory_order_and_notification_events_sync_across_roles(): void
    {
        $buyer = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $seller = User::factory()->create(['usertype' => 'seller', 'status' => 'approved']);
        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']);

        $product = Product::create([
            'user_id' => $seller->id,
            'seller_id' => $seller->id,
            'name' => 'Alona Wireless Earbuds',
            'price' => 1000,
            'stock' => 15,
            'status' => 'approved',
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Teal',
            'size' => 'Standard',
            'price' => 1000,
            'stock' => 15,
        ]);

        // Seller updates variant stock -> broadcasts to catalog.global, seller.{id}, and admin.global
        $initialSync = $this->actingAs($buyer)
            ->getJson('/realtime/sync?cursor=0&channels=catalog.global')
            ->assertOk()
            ->json();
        $cursorBeforeStockUpdate = (int) $initialSync['cursor'];

        RealtimeBroadcastService::resetDeduplication();
        $variant->update(['stock' => 12]);
        $product->update(['stock' => 12]);

        $buyerSync = $this->actingAs($buyer)
            ->getJson("/realtime/sync?cursor={$cursorBeforeStockUpdate}&channels=catalog.global,admin.global")
            ->assertOk()
            ->json();

        // Buyer must NOT be authorized for admin.global
        $this->assertContains('admin.global', $buyerSync['unauthorized_channels']);
        $this->assertContains('catalog.global', $buyerSync['authorized_channels']);
        $this->assertNotEmpty($buyerSync['events']);
        $latestInventoryEvent = end($buyerSync['events']);
        $this->assertSame('inventory.updated', $latestInventoryEvent['event']);
        $this->assertSame(12, $latestInventoryEvent['data']['stock']);

        // Order created -> Buyer, Seller, and Admin receive order.created
        $cursorBeforeOrder = (int) $buyerSync['cursor'];
        $order = Order::create([
            'order_number' => 'ORD-' . uniqid(),
            'user_id' => $buyer->id,
            'subtotal' => 2000,
            'total' => 2050,
            'shipping_fee' => 50,
            'shipping_address' => 'Manila Buyer Address',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'status' => 'pending',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'price' => 1000,
            'status' => 'pending',
        ]);
        SellerNotification::create([
            'user_id' => $seller->id,
            'type' => 'new_order',
            'title' => 'New Order Received',
            'message' => "Order #{$order->id} placed.",
            'read_at' => null,
        ]);
        BuyerNotification::create([
            'user_id' => $buyer->id,
            'type' => 'order_placed',
            'title' => 'Order Confirmed',
            'message' => "Your Order #{$order->id} has been placed.",
            'read_at' => null,
        ]);

        $sellerSync = $this->actingAs($seller)
            ->getJson("/realtime/sync?cursor={$cursorBeforeOrder}&channels=seller.{$seller->id}")
            ->assertOk()
            ->json();

        $sellerEventNames = array_column($sellerSync['events'], 'event');
        $this->assertContains('order.item_created', $sellerEventNames);
        $this->assertContains('notification.created', $sellerEventNames);
        $this->assertSame(1, $sellerSync['state']['seller']['pending_orders']);

        $adminSync = $this->actingAs($admin)
            ->getJson("/realtime/sync?cursor={$cursorBeforeOrder}&channels=admin.global")
            ->assertOk()
            ->json();

        $adminEventNames = array_column($adminSync['events'], 'event');
        $this->assertContains('order.created', $adminEventNames);
    }

    public function test_return_inspection_restocks_variant_and_product_idempotently(): void
    {
        $buyer = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $seller = User::factory()->create(['usertype' => 'seller', 'status' => 'approved']);

        $product = Product::create([
            'user_id' => $seller->id,
            'seller_id' => $seller->id,
            'name' => 'Alona Mechanical Keyboard',
            'price' => 2000,
            'stock' => 8,
            'status' => 'approved',
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color' => 'Brown',
            'size' => 'TKL',
            'price' => 2000,
            'stock' => 8,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-' . uniqid(),
            'user_id' => $buyer->id,
            'subtotal' => 4000,
            'total' => 4000,
            'shipping_fee' => 0,
            'shipping_address' => 'Manila Buyer Address',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'status' => 'delivered',
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'price' => 2000,
            'status' => 'delivered',
        ]);

        $returnRequest = OrderReturnRequest::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'reason' => 'Defective keycap',
            'requested_amount' => 4000,
            'status' => 'return_in_transit',
            'restocked' => false,
        ]);

        $this->actingAs($seller)
            ->post(route('seller.returns.inspect-resolve', $returnRequest->id), [
                'inspection_status' => 'passed',
                'inspection_notes' => 'Verified item intact, restocking.',
                'refund_amount' => 4000,
                'refund_method' => 'original_payment',
                'restock_inventory' => true,
            ])
            ->assertRedirect();

        // Stock must increase by exactly 2 (from 8 to 10), not double-counted
        $this->assertSame(10, (int) $variant->fresh()->stock);
        $this->assertSame(10, (int) $product->fresh()->stock);
        $this->assertTrue((bool) $returnRequest->fresh()->restocked);
        $this->assertSame('refunded', $returnRequest->fresh()->status);

        // Attempting a duplicate inspection call must NOT restock again
        $this->actingAs($seller)
            ->post(route('seller.returns.inspect-resolve', $returnRequest->id), [
                'inspection_status' => 'passed',
                'refund_amount' => 4000,
                'refund_method' => 'original_payment',
                'restock_inventory' => true,
            ]);

        $this->assertSame(10, (int) $variant->fresh()->stock);
        $this->assertSame(10, (int) $product->fresh()->stock);
    }

    public function test_exclusive_rider_pickup_acceptance_is_atomic_and_prevents_duplicate_claims(): void
    {
        $seller = User::factory()->create(['usertype' => 'seller', 'status' => 'approved']);
        $buyer = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $scUser = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);

        $center = SortingCenter::create([
            'user_id' => $scUser->id,
            'business_name' => 'Laguna Sorting Hub',
            'address' => 'Calamba, Laguna',
            'contact_person' => 'Hub Lead',
            'contact_number' => '09170001111',
            'approval_status' => 'approved',
        ]);

        $riderUserA = User::factory()->create([
            'usertype' => 'rider',
            'status' => 'approved',
            'email_verified_at' => now(),
        ]);
        $riderA = Rider::create([
            'user_id' => $riderUserA->id,
            'sorting_center_id' => $center->id,
            'name' => 'Rider Alpha',
            'contact' => '09170002222',
            'vehicle_type' => 'motorcycle',
            'plate_number' => 'RDR-111',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $riderUserB = User::factory()->create([
            'usertype' => 'rider',
            'status' => 'approved',
            'email_verified_at' => now(),
        ]);
        Rider::create([
            'user_id' => $riderUserB->id,
            'sorting_center_id' => $center->id,
            'name' => 'Rider Bravo',
            'contact' => '09170003333',
            'vehicle_type' => 'motorcycle',
            'plate_number' => 'RDR-222',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $product = Product::create([
            'user_id' => $seller->id,
            'seller_id' => $seller->id,
            'name' => 'Test Parcel Item',
            'price' => 500,
            'stock' => 5,
            'status' => 'approved',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-' . uniqid(),
            'user_id' => $buyer->id,
            'subtotal' => 500,
            'total' => 550,
            'shipping_fee' => 50,
            'shipping_address' => 'Cabuyao Buyer Address',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'status' => 'processing',
        ]);

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'price' => 500,
            'status' => 'ready_for_pickup',
        ]);

        $parcel = Parcel::create([
            'order_item_id' => $orderItem->id,
            'order_id' => 'ORD-' . $order->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'sorting_center_id' => $center->id,
            'status' => 'PREPARING',
            'collection_address' => 'Calamba Seller Store',
            'delivery_address' => 'Cabuyao Buyer Address',
            'delivery_province' => 'Laguna',
            'delivery_municipality' => 'Cabuyao',
            'delivery_barangay' => 'Sala',
            'delivery_street' => 'Main St',
            'pickup_rider_id' => null,
        ]);

        PickupRequest::create([
            'parcel_id' => $parcel->id,
            'seller_id' => $seller->id,
            'sorting_center_id' => $center->id,
            'status' => 'confirmed',
            'requested_at' => now(),
        ]);

        // Rider A accepts the pickup job from the Rider Web Module
        $this->actingAs($riderUserA)
            ->postJson(route('rider.pickups.accept', $parcel->id))
            ->assertOk()
            ->assertJsonPath('data.pickup_rider_id', $riderA->id);

        $this->assertSame($riderA->id, (int) $parcel->fresh()->pickup_rider_id);

        // Rider B tries to accept the same pickup job -> must be rejected with 409 Conflict
        $this->actingAs($riderUserB)
            ->postJson(route('rider.pickups.accept', $parcel->id))
            ->assertStatus(409);

        // Rider B's real-time sync on riders.center.{centerId} includes parcel.pickup_assigned with is_pickup_taken = true
        $syncB = $this->actingAs($riderUserB)
            ->getJson("/realtime/sync?cursor=0&channels=riders.center.{$center->id}")
            ->assertOk()
            ->json();

        $pickupAssignedEvents = array_values(array_filter(
            $syncB['events'],
            fn ($e) => $e['event'] === 'parcel.pickup_assigned'
        ));
        $this->assertNotEmpty($pickupAssignedEvents);
        $this->assertTrue((bool) $pickupAssignedEvents[0]['data']['is_pickup_taken']);
    }

    public function test_financial_distribution_preserves_90_10_and_70_20_10_splits_and_broadcasts_realtime_event(): void
    {
        config(['distribution.enabled' => true]);

        $buyer = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $seller = User::factory()->create(['usertype' => 'seller', 'status' => 'approved']);
        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']);

        $product = Product::create([
            'user_id' => $seller->id,
            'seller_id' => $seller->id,
            'name' => 'Alona Smart Watch',
            'price' => 1000,
            'stock' => 10,
            'status' => 'approved',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-' . uniqid(),
            'user_id' => $buyer->id,
            'subtotal' => 1000,
            'total' => 1100,
            'shipping_fee' => 100,
            'shipping_fee_before_discount' => 100,
            'discount' => 0,
            'shipping_discount' => 0,
            'shipping_address' => 'Manila Buyer Address',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'status' => 'pending',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'price' => 1000,
            'status' => 'pending',
        ]);

        $ledger = app(DistributionLedger::class);
        $distribution = $ledger->snapshot($order);

        $this->assertNotNull($distribution);
        $this->assertSame('alona-90-10-70-20-10-v1', $distribution->policy);

        $lines = $distribution->lines()->pluck('amount_cents', 'role')->all();
        // Product 1000 PHP (100,000 cents): 90% Seller (90,000), 10% Alona Admin (10,000)
        $this->assertSame(90000, (int) $lines['seller']);
        $this->assertSame(10000, (int) $lines['platform_product']);
        // Shipping 100 PHP (10,000 cents): 70% Rider (7,000), 20% Logistics (2,000), 10% Alona Admin (1,000)
        $this->assertSame(7000, (int) $lines['rider']);
        $this->assertSame(2000, (int) $lines['logistics']);
        $this->assertSame(1000, (int) $lines['platform_shipping']);

        // Verify finance.ledger_calculated was broadcast to admin.global and seller.{id}
        $adminSync = $this->actingAs($admin)
            ->getJson('/realtime/sync?cursor=0&channels=admin.global')
            ->assertOk()
            ->json();

        $financeEvents = array_values(array_filter(
            $adminSync['events'],
            fn ($e) => $e['event'] === 'finance.ledger_calculated'
        ));
        $this->assertNotEmpty($financeEvents);
        $this->assertSame('alona-90-10-70-20-10-v1', $financeEvents[0]['data']['commission_version']);
    }

    public function test_flash_sale_automatic_expiration_and_chat_broadcast_deduplication(): void
    {
        $buyer = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $seller = User::factory()->create(['usertype' => 'seller', 'status' => 'approved']);

        $product = Product::create([
            'user_id' => $seller->id,
            'seller_id' => $seller->id,
            'name' => 'Flash Sale Speaker',
            'price' => 1500,
            'stock' => 20,
            'status' => 'approved',
        ]);

        // Create an expired Flash Sale that has ended_at = null
        $expiredSale = FlashSale::create([
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'flash_price' => 999,
            'flash_quantity' => 10,
            'sold_quantity' => 3,
            'starts_at' => now()->subHours(5),
            'ends_at' => now()->subMinute(),
            'ended_at' => null,
        ]);

        $bundle = SellerPromotion::create([
            'seller_id' => $seller->id,
            'type' => 'bundle',
            'name' => 'Buy 2 Save 10%',
            'product_id' => $product->id,
            'product_ids' => [$product->id],
            'min_quantity' => 2,
            'discount_type' => 'fixed',
            'discount_value' => 150,
            'is_active' => true,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
        ]);

        // Calling /realtime/catalog-state reconciles expired flash sales and returns active bundle deals
        $catalogRes = $this->getJson("/realtime/catalog-state?product_ids={$product->id}")
            ->assertOk()
            ->json();

        $this->assertNotNull($expiredSale->fresh()->ended_at);
        $this->assertFalse($expiredSale->fresh()->isActiveNow());
        $this->assertNull($catalogRes['products'][$product->id]['flash_sale']);
        $this->assertCount(1, $catalogRes['products'][$product->id]['bundle_deals']);
        $this->assertSame('Buy 2 Save 10%', $catalogRes['products'][$product->id]['bundle_deals'][0]['title']);

        // Buyer-Seller Chat message broadcasts once with unique event_id
        $conversation = Conversation::create([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'product_id' => $product->id,
            'type' => 'buyer_seller',
            'last_message_at' => now(),
        ]);

        // Before Seller opens their account, Seller must appear Offline to Buyer
        $buyerConvBefore = $this->actingAs($buyer)
            ->getJson('/buyer/conversations')
            ->assertOk()
            ->json();
        $this->assertFalse((bool) $buyerConvBefore['conversations'][0]['seller']['is_online']);
        $this->assertSame('Offline', $buyerConvBefore['conversations'][0]['seller']['presence_label']);

        // Once Seller opens their account (syncs), Seller appears Active now to Buyer
        $this->actingAs($seller)
            ->getJson('/realtime/sync?cursor=0')
            ->assertOk();

        $buyerConvOnline = $this->actingAs($buyer)
            ->getJson('/buyer/conversations')
            ->assertOk()
            ->json();
        $this->assertTrue((bool) $buyerConvOnline['conversations'][0]['seller']['is_online']);
        $this->assertSame('Active now', $buyerConvOnline['conversations'][0]['seller']['presence_label']);

        $msg = ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $buyer->id,
            'body' => 'Hello seller, is this speaker still in stock?',
        ]);

        $sellerChatSync = $this->actingAs($seller)
            ->getJson("/realtime/sync?cursor=0&channels=conversation.{$conversation->id},seller.{$seller->id}")
            ->assertOk()
            ->json();

        $chatEvents = array_values(array_filter(
            $sellerChatSync['events'],
            fn ($e) => $e['event'] === 'chat.message_sent' && (int) ($e['data']['message']['id'] ?? 0) === (int) $msg->id
        ));
        $this->assertNotEmpty($chatEvents);
        $this->assertSame('Hello seller, is this speaker still in stock?', $chatEvents[0]['data']['message']['body']);

        // Subsequent sync with the returned cursor yields 0 duplicate events
        $nextCursor = (int) $sellerChatSync['cursor'];
        $secondSync = $this->actingAs($seller)
            ->getJson("/realtime/sync?cursor={$nextCursor}&channels=conversation.{$conversation->id},seller.{$seller->id}")
            ->assertOk()
            ->json();

        $this->assertEmpty($secondSync['events']);

        // When Seller goes offline / logs out, Seller immediately becomes Offline again
        $this->actingAs($seller)
            ->postJson('/realtime/presence/offline')
            ->assertOk();

        $buyerConvOffline = $this->actingAs($buyer)
            ->getJson('/buyer/conversations')
            ->assertOk()
            ->json();
        $this->assertFalse((bool) $buyerConvOffline['conversations'][0]['seller']['is_online']);
        $this->assertSame('Offline', $buyerConvOffline['conversations'][0]['seller']['presence_label']);
    }
}
