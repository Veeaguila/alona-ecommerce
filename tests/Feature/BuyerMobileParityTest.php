<?php

namespace Tests\Feature;

use App\Models\{CartItem, Conversation, ConversationMessage, Order, ProductVariant, Review, User, UserVoucher, Voucher};
use Tests\Support\OrderJourney;

class BuyerMobileParityTest extends OrderJourney
{
    public function test_review_requires_an_owned_delivered_purchase_before_any_write(): void
    {
        $this->apiAs($this->buyer);
        $this->postJson('/api/buyer/products/'.$this->product->id.'/reviews', ['rating' => 5])->assertUnprocessable();
        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_delivered_review_uses_canonical_schema_variant_and_duplicate_rules(): void
    {
        $variant = ProductVariant::create(['product_id' => $this->product->id, 'color' => 'Blue', 'size' => 'M', 'stock' => 10, 'price' => 125]);
        $this->cart->update(['product_variant_id' => $variant->id]);
        $this->checkout();
        $this->item->update(['status' => 'delivered']);
        $this->order->update(['status' => 'delivered']);
        $this->apiAs($this->buyer);
        $this->postJson('/api/buyer/products/'.$this->product->id.'/reviews', ['rating' => 4, 'comment' => 'Received the correct item.', 'order_id' => $this->order->id])
            ->assertCreated()->assertJsonPath('review.order_item_id', $this->item->id)->assertJsonPath('review.variant_id', $variant->id);
        $this->assertSame(1, $this->product->fresh()->reviews_count);
        $this->postJson('/api/buyer/products/'.$this->product->id.'/reviews', ['rating' => 1])->assertUnprocessable();
        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_voucher_claim_rejects_expired_upcoming_ended_and_code_only(): void
    {
        $this->apiAs($this->buyer);
        foreach (['expires_at' => now()->subDay(), 'starts_at' => now()->addDay(), 'ended_at' => now(), 'display_mode' => 'code_only'] as $field => $value) {
            $voucher = Voucher::create(['code' => 'NO-'.$field, 'type' => 'fixed', 'value' => 10, 'is_active' => true, $field => $value]);
            $this->postJson('/api/buyer/vouchers/'.$voucher->id.'/claim')->assertUnprocessable();
        }
        $this->assertDatabaseCount('user_vouchers', 0);
    }

    public function test_voucher_browse_and_claimed_list_exclude_unavailable_vouchers(): void
    {
        $this->apiAs($this->buyer);
        foreach (['ended_at' => now(), 'display_mode' => 'code_only'] as $field => $value) {
            Voucher::create(['code' => 'HIDDEN-'.$field, 'type' => 'fixed', 'value' => 10, 'is_active' => true, $field => $value]);
        }
        $expired = Voucher::create(['code' => 'OLD', 'type' => 'fixed', 'value' => 10, 'is_active' => true, 'expires_at' => now()->subDay()]);
        UserVoucher::create(['user_id' => $this->buyer->id, 'voucher_id' => $expired->id, 'status' => 'claimed']);
        $this->getJson('/api/buyer/vouchers')->assertOk()->assertJsonCount(0, 'available')->assertJsonCount(0, 'claimed');
    }

    public function test_voucher_validation_uses_real_destination_tariff_and_product_eligibility(): void
    {
        $this->apiAs($this->buyer);
        $this->product->update(['weight' => 8]);
        Voucher::create(['code' => 'SHIPALL', 'type' => 'free_shipping', 'value' => 0, 'is_active' => true, 'is_platform' => true]);
        $this->postJson('/api/buyer/vouchers/validate', ['code' => 'SHIPALL', 'cart_item_ids' => [$this->cart->id], 'shipping_method' => 'standard', 'shipping_address' => self::ADDRESS])
            ->assertOk()->assertJsonPath('valid', true)->assertJsonPath('voucher.discount', 350);
        Voucher::create(['code' => 'OTHER', 'type' => 'fixed', 'value' => 10, 'is_active' => true, 'target_type' => 'product', 'targeted_product_ids' => [99999]]);
        $this->postJson('/api/buyer/vouchers/validate', ['code' => 'OTHER', 'cart_item_ids' => [$this->cart->id], 'shipping_method' => 'standard', 'shipping_address' => self::ADDRESS])->assertUnprocessable();
    }

    public function test_cart_excludes_unavailable_products_and_update_enforces_purchase_limit(): void
    {
        $this->apiAs($this->buyer);
        $this->product->update(['status' => 'rejected']);
        $this->getJson('/api/buyer/cart')->assertOk()->assertJsonPath('items.0.is_in_stock', false);
        $this->patchJson('/api/buyer/cart/'.$this->cart->id, ['quantity' => 3])->assertUnprocessable();
        $this->product->update(['status' => 'approved', 'per_buyer_limit' => 2]);
        $this->patchJson('/api/buyer/cart/'.$this->cart->id, ['quantity' => 3])->assertUnprocessable();
        $this->postJson('/api/buyer/cart', ['product_id' => $this->product->id, 'quantity' => 1])->assertUnprocessable();
        $this->assertSame(2, $this->cart->fresh()->quantity);
    }

    public function test_reorder_adds_to_saved_cart_and_never_readds_a_sold_out_variant(): void
    {
        $this->checkout();
        $cart = CartItem::create(['user_id' => $this->buyer->id, 'product_id' => $this->product->id, 'quantity' => 1]);
        $this->apiAs($this->buyer);
        $this->postJson('/api/buyer/orders/'.$this->order->id.'/reorder')->assertOk();
        $this->assertSame(3, $cart->fresh()->quantity);
        $variant = ProductVariant::create(['product_id' => $this->product->id, 'color' => 'Blue', 'price' => 125, 'stock' => 0]);
        $this->item->update(['product_variant_id' => $variant->id, 'variant_label' => 'Blue']);
        $this->postJson('/api/buyer/orders/'.$this->order->id.'/reorder')->assertUnprocessable();
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_mobile_chat_routes_share_the_web_thread_without_marking_list_previews_read(): void
    {
        $this->apiAs($this->buyer);
        $id = $this->postJson('/api/buyer/conversations/start/'.$this->product->id)->assertOk()->json('conversation_id');
        $incoming = ConversationMessage::create(['conversation_id' => $id, 'sender_id' => $this->seller->id, 'body' => 'Hello Buyer']);
        $this->getJson('/api/buyer/conversations')->assertOk()->assertJsonPath('conversations.0.unread_count', 1);
        $this->assertNull($incoming->fresh()->read_at);
        $this->getJson('/api/buyer/conversations/'.$id)->assertOk()->assertJsonPath('messages.0.body', 'Hello Buyer');
        $this->assertNotNull($incoming->fresh()->read_at);
        $this->postJson('/api/buyer/conversations/'.$id, ['body' => 'Hello Seller'])->assertOk()->assertJsonPath('message.body', 'Hello Seller');
        $this->actingAs($this->seller, 'web')->get(route('seller.messages.show', $id))->assertOk();
    }

    public function test_chat_never_shares_another_buyers_order_or_unrelated_seller_order(): void
    {
        $this->checkout();
        $other = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $conversation = Conversation::create(['buyer_id' => $other->id, 'seller_id' => $this->seller->id]);
        $this->apiAs($other);
        $this->postJson('/api/buyer/conversations/'.$conversation->id, ['body' => 'Order', 'order_id' => $this->order->id])->assertUnprocessable();
        $this->actingAs($this->seller, 'web')->postJson(route('seller.messages.store', $conversation->id), ['body' => 'Order', 'order_id' => $this->order->id])->assertUnprocessable();
        $this->assertDatabaseCount('conversation_messages', 0);
    }

    public function test_valid_order_snippets_load_real_variant_and_parcel_columns(): void
    {
        $variant = ProductVariant::create(['product_id' => $this->product->id, 'color' => 'Blue', 'price' => 125, 'stock' => 10]);
        $this->cart->update(['product_variant_id' => $variant->id]);
        $this->checkout(); $this->ready();
        $this->apiAs($this->buyer);
        $id = $this->postJson('/api/buyer/conversations/start/'.$this->product->id)->assertOk()->json('conversation_id');
        $this->postJson('/api/buyer/conversations/'.$id, ['body' => 'Please check my parcel.', 'order_id' => $this->order->id])
            ->assertOk()->assertJsonPath('message.order.items.0.variant.id', $variant->id)->assertJsonPath('message.order.items.0.parcel.order_id', 'ITEM-'.$this->item->id);
        $this->getJson('/api/buyer/conversations/'.$id)->assertOk()->assertJsonPath('messages.0.order.id', $this->order->id);
        $this->actingAs($this->seller, 'web')->get(route('seller.messages.show', $id))->assertOk();
    }

    public function test_legacy_foreign_order_reference_is_hidden_without_deleting_messages(): void
    {
        $this->checkout();
        $other = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $conversation = Conversation::create(['buyer_id' => $other->id, 'seller_id' => $this->seller->id, 'order_id' => $this->order->id]);
        ConversationMessage::create(['conversation_id' => $conversation->id, 'sender_id' => $this->seller->id, 'body' => 'Legacy reference', 'order_id' => $this->order->id]);
        $this->actingAs($other, 'web')->getJson(route('buyer.conversations', ['conversation' => $conversation->id]))
            ->assertOk()->assertJsonPath('selectedConversation.order', null)->assertJsonPath('messages.0.order', null);
        $this->apiAs($other);
        $this->getJson('/api/buyer/conversations/'.$conversation->id)->assertOk()->assertJsonPath('messages.0.order', null);
        $this->assertDatabaseCount('conversation_messages', 1);
    }
}
