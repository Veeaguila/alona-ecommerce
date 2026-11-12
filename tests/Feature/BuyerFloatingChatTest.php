<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerFloatingChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_fetch_conversations_as_json(): void
    {
        $buyer = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $seller = User::factory()->create(['usertype' => 'seller', 'store_name' => 'Alona Official', 'status' => 'approved']);

        $conversation = Conversation::create([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'last_message_at' => now(),
        ]);

        $conversation->messages()->create([
            'sender_id' => $seller->id,
            'body' => 'Welcome to our store!',
        ]);

        $response = $this->actingAs($buyer)
            ->getJson('/buyer/conversations');

        $response->assertOk()
            ->assertJsonStructure([
                'conversations',
                'selectedConversation',
                'messages',
            ])
            ->assertJsonFragment([
                'store_name' => 'Alona Official',
            ]);
    }

    public function test_buyer_can_fetch_and_send_messages_via_json(): void
    {
        $buyer = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $seller = User::factory()->create(['usertype' => 'seller', 'store_name' => 'Gadget Hub', 'status' => 'approved']);

        $conversation = Conversation::create([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'last_message_at' => now(),
        ]);

        // Send a message via JSON endpoint
        $sendResponse = $this->actingAs($buyer)
            ->postJson("/buyer/conversations/{$conversation->id}", [
                'body' => 'Is this product still in stock?',
            ]);

        $sendResponse->assertOk()
            ->assertJsonPath('conversation_id', $conversation->id)
            ->assertJsonPath('message.body', 'Is this product still in stock?');

        // Fetch conversation details via JSON endpoint
        $fetchResponse = $this->actingAs($buyer)
            ->getJson("/buyer/conversations/{$conversation->id}");

        $fetchResponse->assertOk()
            ->assertJsonStructure([
                'conversation',
                'messages',
            ])
            ->assertJsonPath('messages.0.body', 'Is this product still in stock?');
    }

    public function test_buyer_cannot_access_other_buyers_conversations(): void
    {
        $buyer1 = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $buyer2 = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $seller = User::factory()->create(['usertype' => 'seller', 'status' => 'approved']);

        $conversation = Conversation::create([
            'buyer_id' => $buyer1->id,
            'seller_id' => $seller->id,
            'last_message_at' => now(),
        ]);

        $this->actingAs($buyer2)
            ->getJson("/buyer/conversations/{$conversation->id}")
            ->assertForbidden();

        $this->actingAs($buyer2)
            ->postJson("/buyer/conversations/{$conversation->id}", [
                'body' => 'Intruder message',
            ])
            ->assertForbidden();
    }

    public function test_buyer_can_start_conversation_from_product_as_json(): void
    {
        $buyer = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $seller = User::factory()->create(['usertype' => 'seller', 'status' => 'approved']);
        $category = \App\Models\Category::create(['name' => 'Electronics', 'slug' => 'electronics-'.uniqid()]);
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => 'Wireless Mouse',
            'slug' => 'wireless-mouse-'.uniqid(),
            'price' => 299.00,
            'stock' => 10,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($buyer)
            ->postJson("/buyer/conversations/start/{$product->id}");

        $response->assertOk()
            ->assertJsonStructure(['conversation_id']);

        $this->assertDatabaseHas('conversations', [
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_buyer_messages_route_redirects_to_conversations(): void
    {
        $buyer = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);

        $response = $this->actingAs($buyer)
            ->get('/buyer/messages');

        $response->assertRedirect(route('buyer.conversations'));
    }

    public function test_browser_visit_to_conversations_redirects_to_dashboard_with_chat_param(): void
    {
        $buyer = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $seller = User::factory()->create(['usertype' => 'seller', 'status' => 'approved']);

        $conversation = Conversation::create([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'last_message_at' => now(),
        ]);

        $response = $this->actingAs($buyer)
            ->get(route('buyer.conversations', ['conversation' => $conversation->id]));

        $response->assertRedirect(route('buyer.dashboard', ['chat' => $conversation->id]));
    }

    public function test_buyer_can_start_conversation_with_seller_directly(): void
    {
        $buyer = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $seller = User::factory()->create(['usertype' => 'seller', 'status' => 'approved']);

        $response = $this->actingAs($buyer)
            ->postJson(route('buyer.conversations.start-seller', $seller->id));

        $response->assertOk()
            ->assertJsonStructure(['conversation_id']);

        $this->assertDatabaseHas('conversations', [
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
        ]);
    }
}
