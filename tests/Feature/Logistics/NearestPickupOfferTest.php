<?php

namespace Tests\Feature\Logistics;

use App\Models\{LogisticsAuditEvent, PickupOffer};
use App\Services\{PickupLocations, RiderPresence, SellerShipmentFinance};
use Laravel\Sanctum\Sanctum;

class NearestPickupOfferTest extends CompleteWorkflowTest
{
    private function ready(): array
    {
        $order = $this->item->order; $order->update(['delivery_fee_payer' => 'seller']);
        $second = $this->item->replicate(); $second->save();
        app(SellerShipmentFinance::class)->createMembership($order);
        $parcels = [];
        foreach ([$this->item->fresh(), $second->fresh()] as $item) {
            $this->actingAs($item->product->seller, 'web')->patch(route('seller.orders.ready-for-pickup', $item))->assertRedirect();
            $parcel = $item->parcel()->sole(); $parcels[] = $parcel;
            Sanctum::actingAs($this->center->user);
            $this->postJson('/api/sorting-center/pickup-requests/'.$parcel->pickupRequests()->sole()->id.'/confirm')->assertOk();
        }
        return $parcels;
    }
    private function point(float $latitude): array
    {
        // Explicitly synthetic coordinates, never real-provider acceptance evidence.
        return ['latitude' => $latitude, 'longitude' => 120.98, 'accuracy' => 5, 'captured_at' => now()->timestamp, 'permission_confirmed' => true];
    }
    private function share(array $parcels): void
    {
        $this->actingAs($this->item->product->seller, 'web')->postJson(route('seller.orders.pickup-location', $this->item), $this->point(14.6))->assertOk();
        foreach ([$this->pickupRider, $this->deliveryRider] as $i => $rider) {
            Sanctum::actingAs($rider->user);
            $this->postJson('/api/rider/presence', ['action' => 'go_online'])->assertOk();
            $this->postJson('/api/rider/pickup-location', $this->point(14.601 + $i * .1))->assertOk();
        }
        Sanctum::actingAs($this->center->user);
    }
    public function test_nearest_offer_reserves_whole_shipment_and_only_recipient_accepts_without_duplicate_assignment(): void
    {
        $parcels = $this->ready(); $this->share($parcels);
        $url = '/api/sorting-center/pickup-dispatch/'.$parcels[0]->id.'/nearest';
        $this->postJson($url)->assertOk()->assertJsonPath('data.rider_name', $this->pickupRider->name);
        $this->postJson($url)->assertConflict();
        $this->assertDatabaseCount('pickup_offers', 2); $this->assertNull($parcels[0]->fresh()->pickup_rider_id);
        Sanctum::actingAs($this->deliveryRider->user);
        $this->getJson('/api/rider/pickups/available')->assertJsonCount(0, 'data');
        $this->postJson('/api/rider/pickups/'.$parcels[1]->id.'/accept')->assertConflict();
        Sanctum::actingAs($this->pickupRider->user);
        $this->getJson('/api/rider/pickups/available')->assertOk()->assertJsonCount(2, 'data')->assertJsonStructure(['data' => [['nearest_offer_expires_at']]]);
        $this->postJson('/api/rider/pickups/'.$parcels[1]->id.'/accept')->assertOk();
        foreach ($parcels as $parcel) $this->assertSame($this->pickupRider->id, $parcel->fresh()->pickup_rider_id);
        $this->assertSame(2, PickupOffer::where('kind', 'nearest')->where('status', 'accepted')->count());
        $this->postJson('/api/rider/pickups/'.$parcels[0]->id.'/accept')->assertConflict();
        $this->assertSame(2, LogisticsAuditEvent::where('action', 'pickup_assigned')->count());
    }
    public function test_offer_lasts_sixty_seconds_then_advances_to_next_nearest_without_changing_custody(): void
    {
        $parcels = $this->ready(); $this->share($parcels);
        $this->postJson('/api/sorting-center/pickup-dispatch/'.$parcels[0]->id.'/nearest')->assertOk();
        $this->travel(59)->seconds(); Sanctum::actingAs($this->deliveryRider->user);
        $this->getJson('/api/rider/pickups/available')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/rider/pickups/'.$parcels[0]->id.'/accept')->assertConflict();
        $this->travel(1)->seconds();
        $this->getJson('/api/rider/pickups/available')->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame($this->deliveryRider->id, PickupOffer::where('parcel_id', $parcels[0]->id)->sole()->rider_id);
        $this->assertNull($parcels[0]->fresh()->pickup_rider_id);
        $this->postJson('/api/rider/pickups/'.$parcels[0]->id.'/accept')->assertOk();
        foreach ($parcels as $parcel) $this->assertSame($this->deliveryRider->id, $parcel->fresh()->pickup_rider_id);
    }

    public function test_three_distinct_riders_may_decline_then_shipment_returns_unassigned_and_foreign_decline_cannot_mutate(): void
    {
        $parcels = $this->ready(); $this->share($parcels);
        Sanctum::actingAs($this->outsider->user);
        $this->postJson('/api/rider/presence', ['action' => 'go_online'])->assertOk();
        $this->postJson('/api/rider/pickup-location', $this->point(14.9))->assertOk();
        Sanctum::actingAs($this->center->user);
        $this->postJson('/api/sorting-center/pickup-dispatch/'.$parcels[0]->id.'/nearest')->assertOk();
        Sanctum::actingAs($this->outsider->user);
        $this->postJson('/api/rider/pickups/'.$parcels[0]->id.'/decline')->assertForbidden();
        foreach ([$this->pickupRider, $this->deliveryRider, $this->outsider] as $rider) {
            $this->assertSame($rider->id, PickupOffer::where('parcel_id', $parcels[0]->id)->sole()->rider_id);
            Sanctum::actingAs($rider->user);
            $this->postJson('/api/rider/pickups/'.$parcels[1]->id.'/decline')->assertOk();
        }
        $this->assertSame(2, PickupOffer::where('status', 'unassigned')->count());
        $this->assertSame(6, LogisticsAuditEvent::where('action', 'pickup_nearest_offered')->count());
        $this->assertNull($parcels[0]->fresh()->pickup_rider_id);
        $this->postJson('/api/rider/pickups/'.$parcels[0]->id.'/accept')->assertOk();
        foreach ($parcels as $parcel) $this->assertSame($this->outsider->id, $parcel->fresh()->pickup_rider_id);
    }

    public function test_scheduler_advances_three_expired_offers_with_fresh_signals_then_releases_the_whole_shipment(): void
    {
        $this->freezeTime(); $parcels = $this->ready(); $this->share($parcels);
        Sanctum::actingAs($this->outsider->user);
        $this->postJson('/api/rider/presence', ['action' => 'go_online'])->assertOk();
        $this->postJson('/api/rider/pickup-location', $this->point(14.9))->assertOk();
        Sanctum::actingAs($this->center->user);
        $this->postJson('/api/sorting-center/pickup-dispatch/'.$parcels[0]->id.'/nearest')->assertOk();
        foreach ([$this->deliveryRider, $this->outsider, null] as $next) {
            $this->travel(59)->seconds();
            $this->share($parcels);
            Sanctum::actingAs($this->outsider->user);
            $this->postJson('/api/rider/presence', ['action' => 'go_online'])->assertOk();
            $this->postJson('/api/rider/pickup-location', $this->point(14.9))->assertOk();
            $this->travel(1)->seconds();
            $this->artisan('logistics:advance-pickup-offers')->assertExitCode(0);
            $offer = PickupOffer::where('parcel_id', $parcels[0]->id)->sole();
            $this->assertSame($next ? 'offered' : 'unassigned', $offer->status);
            if ($next) $this->assertSame($next->id, $offer->rider_id);
            foreach ($parcels as $parcel) $this->assertNull($parcel->fresh()->pickup_rider_id);
        }
        $this->assertSame(6, LogisticsAuditEvent::where('action', 'pickup_nearest_offered')->count());
    }
    public function test_missing_stale_or_ambiguous_locations_and_suspension_never_invent_nearest_assignment(): void
    {
        $parcels = $this->ready(); $url = '/api/sorting-center/pickup-dispatch/'.$parcels[0]->id.'/nearest';
        $this->postJson($url)->assertUnprocessable();
        $this->share($parcels); $this->travel(91)->seconds();
        $this->postJson($url)->assertUnprocessable(); $this->assertDatabaseCount('pickup_offers', 0);
        $this->share($parcels);
        Sanctum::actingAs($this->deliveryRider->user);
        $this->postJson('/api/rider/pickup-location', $this->point(14.601))->assertOk();
        Sanctum::actingAs($this->center->user);
        $this->postJson($url)->assertUnprocessable()->assertJsonPath('message', 'Location accuracy cannot reliably distinguish the nearest Rider. Refresh locations or use manual assignment.');
        $this->pickupRider->update(['is_active' => false]); $this->deliveryRider->update(['is_active' => false]);
        $this->postJson($url)->assertUnprocessable(); $this->assertDatabaseCount('pickup_offers', 0);
    }
    public function test_device_signal_requires_permission_freshness_online_session_and_verified_seller_ownership(): void
    {
        $parcels = $this->ready(); Sanctum::actingAs($this->pickupRider->user);
        $this->postJson('/api/rider/pickup-location', $this->point(14.6))->assertConflict();
        $this->postJson('/api/rider/presence', ['action' => 'go_online'])->assertOk();
        $bad = $this->point(14.6); $bad['permission_confirmed'] = false;
        $this->postJson('/api/rider/pickup-location', $bad)->assertUnprocessable();
        $bad['permission_confirmed'] = true; $bad['captured_at'] -= 91;
        $this->postJson('/api/rider/pickup-location', $bad)->assertUnprocessable();
        $this->postJson('/api/rider/pickup-location', $this->point(14.6))->assertOk();
        $this->deleteJson('/api/rider/pickup-location')->assertOk(); $this->assertNull(app(PickupLocations::class)->rider($this->pickupRider));
        $this->actingAs($this->item->order->user, 'web')->postJson(route('seller.orders.pickup-location', $this->item), $this->point(14.6))->assertForbidden();
        $this->assertDatabaseCount('pickup_offers', 0);
    }
}
