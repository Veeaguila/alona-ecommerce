<?php

namespace Tests\Feature\Logistics;

use App\Models\{LogisticsAuditEvent, Parcel, PickupOffer, Rider, SortingCenter, User};
use App\Services\{RiderPresence, SellerShipmentFinance};
use Laravel\Sanctum\Sanctum;

class PickupDispatchTest extends CompleteWorkflowTest
{
    private function pickup(string $reference): Parcel
    {
        $order = $this->item->order->replicate(); $order->order_number = $reference; $order->save();
        $item = $this->item->replicate(); $item->order_id = $order->id; $item->seller_shipment_id = null; $item->status = 'packed'; $item->save();
        $this->actingAs($item->product->seller, 'web')->patch(route('seller.orders.ready-for-pickup', $item))->assertRedirect();
        $parcel = $item->parcel()->firstOrFail();
        Sanctum::actingAs($this->center->user);
        $this->postJson('/api/sorting-center/pickup-requests/'.$parcel->pickupRequests()->sole()->id.'/confirm')->assertOk();
        return $parcel;
    }

    public function test_bulk_enforces_three_real_jobs_and_releases_capacity_only_after_physical_arrival(): void
    {
        $parcels = collect(range(1, 5))->map(fn ($n) => $this->pickup('CAPACITY-'.$n));
        app(RiderPresence::class)->command($this->pickupRider, 'go_online', 'synthetic');
        $body = ['rider_id' => $this->pickupRider->id, 'parcel_ids' => $parcels->pluck('id')->all()];
        $result = $this->postJson('/api/sorting-center/pickup-dispatch/bulk', $body)->assertOk()->json('data');
        $this->assertSame(['assigned', 'assigned', 'assigned', 'skipped', 'skipped'], array_column($result, 'status'));
        $this->assertStringContainsString('three active', $result[3]['reason']);
        $this->assertSame(3, Parcel::where('pickup_rider_id', $this->pickupRider->id)->count());
        $this->assertSame(3, PickupOffer::count());
        $this->getJson('/api/sorting-center/pickup-dispatch/riders')->assertJsonCount(0, 'data');
        Sanctum::actingAs($this->pickupRider->user);
        $this->postJson('/api/rider/pickups/'.$parcels[3]->id.'/accept')->assertUnprocessable();
        $first = $parcels[0];
        $this->postJson('/api/rider/pickups/'.$first->id.'/confirm')->assertUnprocessable();
        $this->postJson('/api/rider/pickups/'.$first->id.'/acknowledge')->assertOk();
        $this->postJson('/api/rider/pickups/'.$first->id.'/acknowledge')->assertConflict();
        $this->postJson('/api/rider/pickups/'.$first->id.'/confirm')->assertOk();
        $this->postJson('/api/rider/pickups/'.$parcels[3]->id.'/accept')->assertUnprocessable();
        Sanctum::actingAs($this->center->user);
        $this->postJson('/api/sorting-center/parcels/mark-arrived', ['order_id' => $first->order_id])->assertOk();
        $this->postJson('/api/sorting-center/pickup-dispatch/'.$parcels[3]->id.'/assign', ['rider_id' => $this->pickupRider->id])->assertOk();
    }

    public function test_manual_whole_shipment_acknowledgment_and_uncollected_reassignment_are_atomic(): void
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
        foreach ([$this->pickupRider, $this->deliveryRider] as $rider) app(RiderPresence::class)->command($rider, 'go_online', 'synthetic');
        $url = '/api/sorting-center/pickup-dispatch/'.$parcels[0]->id.'/assign';
        $this->postJson($url, ['rider_id' => $this->pickupRider->id])->assertOk();
        foreach ($parcels as $parcel) $this->assertSame($this->pickupRider->id, $parcel->fresh()->pickup_rider_id);
        $this->getJson('/api/sorting-center/pickup-dispatch/riders')->assertJsonPath('data.1.active_jobs_count', 1);
        $this->postJson($url, ['rider_id' => $this->deliveryRider->id])->assertConflict();
        $this->postJson($url, ['rider_id' => $this->deliveryRider->id, 'from_rider_id' => $this->pickupRider->id])->assertUnprocessable();
        $this->postJson($url, ['rider_id' => $this->deliveryRider->id, 'from_rider_id' => $this->pickupRider->id,
            'reason' => 'Original Rider is unavailable before collection.'])->assertOk();
        foreach ($parcels as $parcel) $this->assertSame($this->deliveryRider->id, $parcel->fresh()->pickup_rider_id);
        Sanctum::actingAs($this->pickupRider->user);
        $this->postJson('/api/rider/pickups/'.$parcels[0]->id.'/acknowledge')->assertForbidden();
        Sanctum::actingAs($this->deliveryRider->user);
        $this->getJson('/api/rider/pickups/mine')->assertJsonPath('data.0.pickup_assignment_state', 'assigned');
        $this->postJson('/api/rider/pickups/'.$parcels[0]->id.'/acknowledge')->assertOk();
        $this->assertSame(2, PickupOffer::where('status', 'accepted')->count());
        $this->postJson('/api/rider/pickups/'.$parcels[0]->id.'/confirm')->assertOk();
        Sanctum::actingAs($this->center->user);
        $this->postJson($url, ['rider_id' => $this->pickupRider->id, 'from_rider_id' => $this->deliveryRider->id,
            'reason' => 'Collected parcel cannot use an ordinary reassignment.'])->assertUnprocessable();
        $this->assertSame(2, LogisticsAuditEvent::where('action', 'pickup_reassigned')->count());
        $this->assertDatabaseCount('seller_delivery_charges', 0);
    }

    public function test_offline_suspended_and_foreign_riders_are_rejected_and_capacity_counts_both_legs(): void
    {
        $parcels = collect(range(1, 4))->map(fn ($n) => $this->pickup('MIXED-'.$n));
        app(RiderPresence::class)->offline($this->deliveryRider);
        $this->outsider->update(['is_active' => false]);
        $this->getJson('/api/sorting-center/pickup-dispatch/riders')->assertJsonCount(0, 'data');
        foreach ([$this->deliveryRider, $this->outsider] as $rider) $this->postJson('/api/sorting-center/pickup-dispatch/'.$parcels[0]->id.'/assign', ['rider_id' => $rider->id])->assertUnprocessable();
        app(RiderPresence::class)->command($this->pickupRider, 'go_online', 'synthetic');
        Sanctum::actingAs($this->pickupRider->user);
        foreach ($parcels->take(3) as $parcel) $this->postJson('/api/rider/pickups/'.$parcel->id.'/accept')->assertOk();
        $parcels[3]->updateQuietly(['status' => 'AT_SORTING_CENTER', 'sorted_area_id' => $this->area->id]);
        $this->pickupRider->areas()->attach($this->area);
        Sanctum::actingAs($this->center->user);
        $this->postJson('/api/sorting-center/parcels/'.$parcels[3]->id.'/assign', ['rider_id' => $this->pickupRider->id])->assertUnprocessable();
        $this->getJson('/api/sorting-center/parcels/'.$parcels[3]->id.'/eligible-riders')->assertJsonCount(0, 'data');
        Sanctum::actingAs($this->pickupRider->user);
        $this->postJson('/api/sorting-center/pickup-dispatch/'.$parcels[3]->id.'/assign', ['rider_id' => $this->pickupRider->id])->assertForbidden();
    }
}
