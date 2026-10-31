<?php

namespace Tests\Feature\Logistics;

use App\Models\{Parcel, PickupRequest, LogisticsAuditEvent};
use Laravel\Sanctum\Sanctum;

class SuspensionReleaseTest extends CompleteWorkflowTest
{
    public function test_suspension_releases_only_confirmed_uncollected_claims_with_audit_and_retains_collected_custody(): void
    {
        $attributes = ['seller_id' => $this->item->product->seller_id, 'buyer_id' => $this->item->order->user_id,
            'sorting_center_id' => $this->center->id, 'pickup_rider_id' => $this->pickupRider->id,
            'delivery_province' => '', 'delivery_municipality' => '', 'delivery_barangay' => ''];
        $waiting = Parcel::create($attributes + ['order_id' => 'RELEASE-WAITING', 'status' => 'PLACED']);
        $collected = Parcel::create($attributes + ['order_id' => 'RETAIN-COLLECTED', 'status' => 'PICKED_UP']);
        $legacy = Parcel::create($attributes + ['order_id' => 'RETAIN-INCONSISTENT-LEGACY', 'status' => 'PLACED']);
        $request = PickupRequest::create(['parcel_id' => $waiting->id, 'seller_id' => $waiting->seller_id,
            'sorting_center_id' => $this->center->id, 'status' => 'confirmed']);
        $collectedBefore = $collected->fresh()->toArray(); $legacyBefore = $legacy->fresh()->toArray();
        Sanctum::actingAs($this->pickupRider->user);
        $this->postJson('/api/rider/presence', ['action' => 'go_online'])->assertOk();
        Sanctum::actingAs($this->center->user);
        $url = '/api/sorting-center/riders/'.$this->pickupRider->id;
        $this->postJson($url.'/deactivate', ['reason' => 'Approved synthetic uncollected release'])->assertOk()->assertJsonPath('data.presence', 'Offline');
        $this->assertNull($waiting->fresh()->pickup_rider_id);
        $this->assertSame($collectedBefore, $collected->fresh()->toArray());
        $this->assertSame($legacyBefore, $legacy->fresh()->toArray());
        $this->assertSame('confirmed', $request->fresh()->status);
        $this->assertDatabaseHas('logistics_audit_events', ['parcel_id' => $waiting->id, 'action' => 'pickup_released', 'actor_id' => $this->center->user_id]);
        $this->postJson($url.'/deactivate', ['reason' => 'Approved synthetic uncollected release'])->assertStatus(409);
        $this->assertSame(1, LogisticsAuditEvent::where('action', 'pickup_released')->count());
        Sanctum::actingAs($this->deliveryRider->user);
        $pool = $this->getJson('/api/rider/pickups/available')->assertOk()->json('data');
        $this->assertContains($waiting->id, array_column($pool, 'id'));
        $this->postJson('/api/rider/pickups/'.$waiting->id.'/accept')->assertOk();
        $event = LogisticsAuditEvent::where('action', 'pickup_released')->firstOrFail();
        $this->expectException(\LogicException::class);
        $event->update(['action' => 'edited']);
    }
}
