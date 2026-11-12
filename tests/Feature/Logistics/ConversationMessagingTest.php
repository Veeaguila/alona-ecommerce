<?php

namespace Tests\Feature\Logistics;

use App\Models\Rider;
use App\Models\SortingCenter;
use App\Models\SortingCenterConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationMessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_center_and_its_approved_rider_can_start_and_continue_a_conversation(): void
    {
        [$centerUser, $riderUser] = $this->accounts();

        Sanctum::actingAs($centerUser);
        $response = $this->postJson('/api/sorting-center/messages', [
            'recipient_id' => $riderUser->id,
            'body' => 'Delivery assignment is ready.',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Message sent.');

        $conversationId = $response->json('conversation_id');
        $this->postJson('/api/sorting-center/messages', [
            'recipient_id' => $riderUser->id,
            'body' => 'A follow-up in the same conversation.',
        ])
            ->assertCreated()
            ->assertJsonPath('conversation_id', $conversationId);

        Sanctum::actingAs($riderUser);
        $this->getJson('/api/rider/messages/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.id', $conversationId);
        $this->getJson("/api/rider/messages/conversations/{$conversationId}")
            ->assertOk()
            ->assertJsonPath('data.0.body', 'Delivery assignment is ready.')
            ->assertJsonPath('data.0.is_mine', false);
        $this->postJson("/api/rider/messages/conversations/{$conversationId}", [
            'body' => 'I am starting the delivery.',
        ])->assertCreated();

        Sanctum::actingAs($centerUser);
        $this->getJson("/api/sorting-center/messages/conversations/{$conversationId}")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.2.body', 'I am starting the delivery.')
            ->assertJsonPath('data.2.is_mine', false);
        $this->assertDatabaseCount('sorting_center_conversations', 1);
    }

    public function test_accounts_cannot_message_riders_outside_their_sorting_center(): void
    {
        [$centerUser] = $this->accounts();
        [, $otherRider] = $this->accounts('Other Center');
        Sanctum::actingAs($centerUser);

        $this->postJson('/api/sorting-center/messages', [
            'recipient_id' => $otherRider->id,
            'body' => 'This should not be delivered.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('recipient_id');

        $this->assertDatabaseCount('sorting_center_conversations', 0);
    }

    public function test_non_participant_cannot_read_a_conversation(): void
    {
        [$centerUser] = $this->accounts();
        $outsider = User::factory()->create(['status' => 'approved', 'usertype' => 'sorting_center']);
        $outsiderCenter = SortingCenter::create([
            'user_id' => $outsider->id,
            'business_name' => 'Outside Center',
            'approval_status' => 'approved',
        ]);
        $conversation = SortingCenterConversation::create([
            'sorting_center_id' => $centerUser->sortingCenter->id,
            'created_by' => $centerUser->id,
        ]);
        $conversation->participants()->attach([$centerUser->id]);

        Sanctum::actingAs($outsider);
        $this->getJson("/api/sorting-center/messages/conversations/{$conversation->id}")
            ->assertNotFound();
        $this->assertNotNull($outsiderCenter);
    }

    public function test_rider_unread_count_clears_only_when_the_owned_conversation_is_opened(): void
    {
        [$center, $rider] = $this->accounts();
        Sanctum::actingAs($center);
        $id = $this->postJson('/api/sorting-center/messages', ['recipient_id' => $rider->id, 'body' => 'Assigned delivery ready.'])
            ->assertCreated()->json('conversation_id');
        Sanctum::actingAs($rider);
        $this->getJson('/api/rider/messages/conversations')->assertOk()->assertJsonPath('data.0.unread_count', 1);
        $this->getJson("/api/rider/messages/conversations/{$id}")->assertOk();
        $this->getJson('/api/rider/messages/conversations')->assertOk()->assertJsonPath('data.0.unread_count', 0);
    }

    public function test_unassigned_legacy_rider_cannot_use_messaging(): void
    {
        [, $rider] = $this->accounts();
        $rider->rider->update(['sorting_center_id' => null]);
        Sanctum::actingAs($rider);
        $this->getJson('/api/rider/messages/recipients')->assertForbidden();
    }

    public function test_existing_conversation_cannot_bypass_current_recipient_eligibility(): void
    {
        [$center, $rider] = $this->accounts();
        Sanctum::actingAs($center);
        $id = $this->postJson('/api/sorting-center/messages', ['recipient_id' => $rider->id, 'body' => 'First message.'])
            ->assertCreated()->json('conversation_id');
        $rider->update(['status' => 'suspended']);
        $this->postJson("/api/sorting-center/messages/conversations/{$id}", ['body' => 'Must not bypass suspension.'])
            ->assertUnprocessable();
        $this->assertDatabaseCount('sorting_center_messages', 1);
    }

    private function accounts(string $businessName = 'Test Center'): array
    {
        $centerUser = User::factory()->create(['status' => 'approved', 'usertype' => 'sorting_center']);
        $center = SortingCenter::create([
            'user_id' => $centerUser->id,
            'business_name' => $businessName,
            'approval_status' => 'approved',
        ]);
        $centerUser->load('sortingCenter');

        $riderUser = User::factory()->create(['status' => 'approved', 'usertype' => 'rider']);
        Rider::create([
            'user_id' => $riderUser->id,
            'sorting_center_id' => $center->id,
            'name' => 'Test Rider',
            'contact' => '09170000000',
            'vehicle_type' => 'motorcycle',
            'plate_number' => 'TEST-777',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        return [$centerUser, $riderUser];
    }
}
