<?php

namespace Tests\Feature\Logistics;

use App\Models\Parcel;
use App\Services\ParcelTrackingTimeline;
use Laravel\Sanctum\Sanctum;

class OutboundParcelTest extends CompleteWorkflowTest
{
    private function ready(): Parcel
    {
        $this->item->order->update(['delivery_fee_payer' => 'seller']);
        \Illuminate\Support\Facades\DB::transaction(fn () => app(\App\Services\SellerShipmentFinance::class)->createMembership($this->item->order->fresh()));
        $this->actingAs($this->item->product->seller, 'web')->patch(route('seller.orders.ready-for-pickup', $this->item))->assertRedirect();
        $parcel = $this->item->parcel()->firstOrFail();
        Sanctum::actingAs($this->center->user);
        $this->postJson('/api/sorting-center/pickup-requests/'.$parcel->pickupRequests()->firstOrFail()->id.'/confirm')->assertOk();
        Sanctum::actingAs($this->pickupRider->user);
        $this->postJson('/api/rider/pickups/'.$parcel->id.'/accept')->assertOk();
        $this->postJson('/api/rider/pickups/'.$parcel->id.'/confirm')->assertOk();
        Sanctum::actingAs($this->center->user);
        $snapshot = $parcel->fresh()->toArray();
        $this->getJson('/api/sorting-center/outbound/lookup/'.$parcel->order_id)->assertUnprocessable();
        $this->postJson('/api/sorting-center/outbound/confirm', ['order_id' => $parcel->order_id])->assertUnprocessable();
        $this->assertSame($snapshot, $parcel->fresh()->toArray());
        $this->postJson('/api/sorting-center/parcels/mark-arrived', ['order_id' => $parcel->order_id])->assertOk();
        $this->getJson('/api/sorting-center/outbound/lookup/'.$parcel->order_id)->assertOk();
        $this->postJson('/api/sorting-center/outbound/confirm', ['order_id' => $parcel->order_id])->assertUnprocessable();
        $this->postJson('/api/sorting-center/parcels/'.$parcel->id.'/sort', ['area_id' => $this->area->id])->assertOk();
        $this->postJson('/api/sorting-center/outbound/confirm', ['order_id' => $parcel->order_id])->assertUnprocessable();
        $this->deliveryRider->areas()->attach($this->area);
        $this->postJson('/api/sorting-center/parcels/'.$parcel->id.'/assign', ['rider_id' => $this->deliveryRider->id])->assertOk();
        return $parcel->fresh();
    }

    public function test_confirmation_records_one_existing_event_and_never_starts_delivery_or_charges(): void
    {
        $parcel = $this->ready();
        $snapshot = $parcel->toArray();
        $first = $this->postJson('/api/sorting-center/outbound/confirm', ['order_id' => $parcel->order_id])->assertOk();
        $this->assertNotNull($first->json('data.confirmed_at'));
        $this->postJson('/api/sorting-center/outbound/confirm', ['order_id' => $parcel->order_id])->assertOk()
            ->assertJsonPath('data.confirmed_at', $first->json('data.confirmed_at'));
        $this->assertSame(1, $parcel->orderItem->statusHistories()->where('note', ParcelTrackingTimeline::OUTBOUND)->count());
        $events = $this->getJson('/api/sorting-center/pickup-requests?status=all')->assertOk()->json('data.data.0.timeline');
        $outbound = collect($events)->where('label', 'Outbound readiness confirmed');
        $this->assertCount(1, $outbound);
        $this->assertSame($first->json('data.confirmed_at'), $outbound->first()['occurred_at']);
        $this->assertSame($snapshot, $parcel->fresh()->toArray());
        $this->assertDatabaseCount('seller_delivery_charges', 0);
        $this->assertDatabaseCount('rider_delivery_earnings', 0);
        $this->assertNull($this->item->fresh()->sellerShipment?->dispatched_at);
        Sanctum::actingAs($this->pickupRider->user);
        $this->postJson('/api/sorting-center/outbound/confirm', ['order_id' => $parcel->order_id])->assertForbidden();
        Sanctum::actingAs($this->deliveryRider->user);
        $this->postJson('/api/rider/deliveries/'.$parcel->id.'/start')->assertOk();
        $this->postJson('/api/rider/deliveries/'.$parcel->id.'/deliver')->assertUnprocessable();
        Sanctum::actingAs($this->center->user);
        $this->postJson('/api/sorting-center/outbound/confirm', ['order_id' => $parcel->order_id])->assertUnprocessable();
    }

    public function test_current_eligibility_coverage_and_complete_shipment_are_required(): void
    {
        $parcel = $this->ready();
        $this->deliveryRider->update(['is_active' => false]);
        $this->postJson('/api/sorting-center/outbound/confirm', ['order_id' => $parcel->order_id])->assertUnprocessable();
        $this->deliveryRider->update(['is_active' => true]);
        $this->deliveryRider->areas()->detach();
        $this->postJson('/api/sorting-center/outbound/confirm', ['order_id' => $parcel->order_id])->assertUnprocessable();
        $this->deliveryRider->areas()->attach($this->area);
        $member = $this->item->fresh()->replicate(); $member->save();
        // A synthetic sibling is still uncollected; the existing shipment must stay intact.
        $other = $parcel->replicate(); $other->order_id = 'OUTBOUND-SIBLING'; $other->order_item_id = $member->id;
        $other->status = 'PLACED'; $other->sorted_area_id = null; $other->sorted_at = null; $other->save();
        $this->postJson('/api/sorting-center/outbound/confirm', ['order_id' => $parcel->order_id])->assertUnprocessable();
        $this->assertSame(0, $parcel->orderItem->statusHistories()->where('note', ParcelTrackingTimeline::OUTBOUND)->count());
        $this->getJson('/api/sorting-center/outbound/lookup/unknown')->assertNotFound();
        $this->assertSame('AT_SORTING_CENTER', $parcel->fresh()->status);
        $this->assertDatabaseCount('seller_delivery_charges', 0);
    }
}
