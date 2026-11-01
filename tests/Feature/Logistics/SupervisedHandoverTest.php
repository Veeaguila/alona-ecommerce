<?php

namespace Tests\Feature\Logistics;

use App\Models\{LogisticsAuditEvent, Parcel, ParcelHandover, SellerDeliveryCharge, SortingCenter, User};
use App\Services\{RiderPresence, SellerShipmentFinance};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class SupervisedHandoverTest extends CompleteWorkflowTest
{
    private function collectedShipment(): array
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
        Sanctum::actingAs($this->pickupRider->user);
        $this->postJson('/api/rider/pickups/'.$parcels[0]->id.'/accept')->assertOk();
        foreach ($parcels as $parcel) $this->postJson('/api/rider/pickups/'.$parcel->id.'/confirm')->assertOk();
        app(RiderPresence::class)->command($this->deliveryRider, 'go_online', 'test');
        Sanctum::actingAs($this->center->user);
        return $parcels;
    }
    private function requestHandover(Parcel $parcel, string $leg = 'pickup', ?int $target = null): int
    {
        return $this->postJson('/api/sorting-center/parcels/'.$parcel->id.'/handovers', [
            'request_key' => (string) Str::uuid(), 'leg' => $leg, 'rider_id' => $target ?? $this->deliveryRider->id,
            'reason' => 'Logistics supervises physical transfer of the complete shipment.',
        ])->assertCreated()->json('data.id');
    }

    public function test_suspended_source_and_target_must_acknowledge_before_logistics_transfers_the_whole_shipment_once(): void
    {
        $parcels = $this->collectedShipment();
        $this->postJson('/api/sorting-center/riders/'.$this->pickupRider->id.'/deactivate', ['reason' => 'Suspended while preserving collected parcel custody.'])->assertOk();
        $id = $this->requestHandover($parcels[0]);
        $url = '/api/sorting-center/handovers/'.$id.'/confirm';
        $this->postJson($url)->assertUnprocessable();
        Sanctum::actingAs($this->outsider->user);
        $this->postJson('/api/rider/handovers/'.$id.'/acknowledge', ['physical_handover' => true])->assertNotFound();
        Sanctum::actingAs($this->pickupRider->user->fresh());
        $this->getJson('/api/rider/pickups/mine')->assertForbidden();
        $this->getJson('/api/rider/handovers')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->postJson('/api/rider/handovers/'.$id.'/acknowledge', [])->assertUnprocessable();
        $this->postJson('/api/rider/handovers/'.$id.'/acknowledge', ['physical_handover' => true])->assertOk();
        $this->postJson('/api/rider/handovers/'.$id.'/acknowledge', ['physical_handover' => true])->assertConflict();
        Sanctum::actingAs($this->center->user); $this->postJson($url)->assertUnprocessable();
        Sanctum::actingAs($this->deliveryRider->user);
        $this->postJson('/api/rider/handovers/'.$id.'/acknowledge', ['physical_handover' => true])->assertOk();
        foreach ($parcels as $parcel) $this->assertSame($this->pickupRider->id, $parcel->fresh()->pickup_rider_id);
        Sanctum::actingAs($this->center->user); $this->postJson($url)->assertOk(); $this->postJson($url)->assertConflict();
        foreach ($parcels as $parcel) {
            $this->assertSame($this->deliveryRider->id, $parcel->fresh()->pickup_rider_id);
            $this->assertSame('PICKED_UP', $parcel->fresh()->status);
        }
        $handover = ParcelHandover::findOrFail($id);
        $this->assertSame('confirmed', $handover->status);
        $this->assertSame($this->center->user_id, $handover->confirmed_by);
        $this->assertSame(2, LogisticsAuditEvent::where('action', 'handover_confirmed')->count());
        $this->assertSame(2, LogisticsAuditEvent::where('action', 'handover_acknowledged')->count());
        $this->assertDatabaseCount('seller_delivery_charges', 0);
    }

    public function test_stale_arrival_or_suspended_target_blocks_confirmation_and_explicit_cancellation_preserves_custody(): void
    {
        $parcels = $this->collectedShipment(); $id = $this->requestHandover($parcels[0]);
        $this->postJson('/api/sorting-center/parcels/'.$parcels[1]->id.'/handovers', ['request_key' => (string) Str::uuid(),
            'leg' => 'pickup', 'rider_id' => $this->deliveryRider->id, 'reason' => 'Duplicate complete shipment request.'])->assertConflict();
        $this->postJson('/api/sorting-center/parcels/mark-arrived', ['order_id' => $parcels[0]->order_id])->assertOk();
        Sanctum::actingAs($this->pickupRider->user);
        $this->postJson('/api/rider/handovers/'.$id.'/acknowledge', ['physical_handover' => true])->assertUnprocessable();
        Sanctum::actingAs($this->center->user);
        $this->postJson('/api/sorting-center/handovers/'.$id.'/cancel', ['reason' => 'Parcel arrived at the center; no Rider handover occurred.'])->assertOk();
        $this->postJson('/api/sorting-center/handovers/'.$id.'/confirm')->assertConflict();
        foreach ($parcels as $parcel) $this->assertSame($this->pickupRider->id, $parcel->fresh()->pickup_rider_id);
        $this->assertSame('cancelled', ParcelHandover::find($id)->status);
        $this->assertSame(1, LogisticsAuditEvent::where('action', 'handover_cancelled')->count());
    }

    public function test_delivery_handover_keeps_dispatch_and_requires_new_custodian_photo_before_existing_finance_recognition(): void
    {
        $parcels = $this->collectedShipment();
        foreach ([$this->deliveryRider, $this->outsider] as $rider) $rider->areas()->attach($this->area);
        foreach ($parcels as $parcel) {
            $this->postJson('/api/sorting-center/parcels/mark-arrived', ['order_id' => $parcel->order_id])->assertOk();
            $this->postJson('/api/sorting-center/parcels/'.$parcel->id.'/sort', ['area_id' => $this->area->id])->assertOk();
            $this->postJson('/api/sorting-center/parcels/'.$parcel->id.'/assign', ['rider_id' => $this->deliveryRider->id])->assertOk();
        }
        Sanctum::actingAs($this->deliveryRider->user);
        foreach ($parcels as $parcel) $this->postJson('/api/rider/deliveries/'.$parcel->id.'/start')->assertOk();
        $this->postJson('/api/rider/deliveries/'.$parcels[0]->id.'/upload-proof', ['file' => UploadedFile::fake()->image('original.jpg')])->assertCreated();
        $shipment = $this->item->fresh()->sellerShipment;
        $dispatch = $shipment->dispatched_at->toISOString();
        app(RiderPresence::class)->command($this->outsider, 'go_online', 'test');
        Sanctum::actingAs($this->center->user);
        $id = $this->requestHandover($parcels[0], 'delivery', $this->outsider->id);
        foreach ([$this->deliveryRider, $this->outsider] as $rider) {
            Sanctum::actingAs($rider->user); $this->postJson('/api/rider/handovers/'.$id.'/acknowledge', ['physical_handover' => true])->assertOk();
        }
        Sanctum::actingAs($this->center->user); $this->postJson('/api/sorting-center/handovers/'.$id.'/confirm')->assertOk();
        $this->assertSame($dispatch, $shipment->fresh()->dispatched_at->toISOString());
        $this->assertSame($this->outsider->id, $shipment->fresh()->assigned_rider_id);
        Sanctum::actingAs($this->outsider->user);
        $this->postJson('/api/rider/deliveries/'.$parcels[0]->id.'/deliver', ['otp' => $this->deliveryOtp($parcels[0]->id)])->assertUnprocessable();
        foreach ($parcels as $parcel) {
            $this->postJson('/api/rider/deliveries/'.$parcel->id.'/upload-proof', ['file' => UploadedFile::fake()->image('receiving.jpg')])->assertCreated();
            $this->postJson('/api/rider/deliveries/'.$parcel->id.'/deliver', ['otp' => $this->deliveryOtp($parcel->id)])->assertOk();
        }
        $charge = SellerDeliveryCharge::sole();
        $this->assertSame('50.00', $charge->amount); $this->assertSame('10.00', $charge->logistics_amount); $this->assertSame('40.00', $charge->rider_amount);
    }
}
