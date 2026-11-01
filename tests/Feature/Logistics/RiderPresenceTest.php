<?php

namespace Tests\Feature\Logistics;

use App\Services\RiderPresence;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

class RiderPresenceTest extends CompleteWorkflowTest
{
    public function test_presence_requires_explicit_opt_in_and_real_heartbeats_and_expires_without_fabricated_timestamps(): void
    {
        Cache::flush();
        Sanctum::actingAs($this->pickupRider->user);
        $this->getJson('/api/rider/presence')->assertOk()->assertJsonPath('data.presence', 'Unknown')->assertJsonPath('data.last_heartbeat_at', null);
        app(RiderPresence::class)->offline($this->pickupRider);
        $this->getJson('/api/rider/presence')->assertOk()->assertJsonPath('data.presence', 'Offline')->assertJsonPath('data.last_heartbeat_at', null);
        $this->postJson('/api/rider/presence', ['action' => 'heartbeat'])->assertStatus(409);
        $online = $this->postJson('/api/rider/presence', ['action' => 'go_online'])->assertOk()->assertJsonPath('data.presence', 'Online');
        $this->assertNotNull($online->json('data.last_heartbeat_at'));
        $this->travel(89)->seconds();
        $this->getJson('/api/rider/presence')->assertJsonPath('data.presence', 'Online');
        $this->travel(2)->seconds();
        $this->getJson('/api/rider/presence')->assertJsonPath('data.presence', 'Offline')->assertJsonPath('data.last_heartbeat_at', $online->json('data.last_heartbeat_at'));
        $this->postJson('/api/rider/presence', ['action' => 'heartbeat'])->assertOk()->assertJsonPath('data.presence', 'Online');
        $this->postJson('/api/rider/presence', ['action' => 'go_offline'])->assertOk()->assertJsonPath('data.presence', 'Offline');
        $this->postJson('/api/rider/presence', ['action' => 'heartbeat'])->assertStatus(409);
        $this->postJson('/api/rider/presence', ['action' => 'fake_online'])->assertUnprocessable();
    }

    public function test_suspension_forces_offline_and_reactivation_requires_new_opt_in_without_erasing_jobs(): void
    {
        Cache::flush();
        Sanctum::actingAs($this->pickupRider->user);
        $this->postJson('/api/rider/presence', ['action' => 'go_online'])->assertOk();
        Sanctum::actingAs($this->center->user);
        $riders = $this->getJson('/api/sorting-center/riders')->assertOk()->json('data');
        $this->assertSame('Online', collect($riders)->firstWhere('id', $this->pickupRider->id)['presence']);
        $this->postJson('/api/sorting-center/riders/'.$this->pickupRider->id.'/deactivate', ['reason' => 'Synthetic suspension for presence test'])->assertOk()->assertJsonPath('data.presence', 'Offline');
        Sanctum::actingAs($this->pickupRider->user->fresh());
        $this->postJson('/api/rider/presence', ['action' => 'go_online'])->assertForbidden();
        Sanctum::actingAs($this->center->user);
        $this->postJson('/api/sorting-center/riders/'.$this->pickupRider->id.'/activate')->assertOk()->assertJsonPath('data.presence', 'Offline');
        Sanctum::actingAs($this->pickupRider->user->fresh());
        $this->postJson('/api/rider/presence', ['action' => 'heartbeat'])->assertStatus(409);
        $this->postJson('/api/rider/presence', ['action' => 'go_online'])->assertOk()->assertJsonPath('data.presence', 'Online');
        Sanctum::actingAs($this->item->order->user);
        $this->postJson('/api/rider/presence', ['action' => 'go_online'])->assertForbidden();
    }
}
