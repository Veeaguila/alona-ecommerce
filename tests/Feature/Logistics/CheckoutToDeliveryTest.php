<?php

namespace Tests\Feature\Logistics;

use App\Models\ParcelReturn;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\OrderJourney;

class CheckoutToDeliveryTest extends OrderJourney
{
    public function test_checkout_cod_cash_and_remittance_require_separate_physical_evidence_and_confirmation(): void
    {
        $this->checkout();
        $this->ready();
        $parcel = $this->dispatchToDelivery();
        $collection = ['request_key' => '00000000-0000-4000-8000-000000000001',
            'amount' => '300.00', 'file' => UploadedFile::fake()->image('cash.jpg')];
        $this->postJson("/api/rider/cod/collections/{$parcel->id}", $collection)->assertCreated();
        $this->postJson("/api/rider/cod/collections/{$parcel->id}", $collection)->assertCreated();
        $this->assertDatabaseCount('cod_collections', 1);
        $this->assertSame('OUT_FOR_DELIVERY', $parcel->fresh()->status);
        $remittance = $this->postJson('/api/rider/cod/remittances', [
            'request_key' => '00000000-0000-4000-8000-000000000002', 'amount' => '300.00',
            'sorting_center_id' => $this->center->id, 'file' => UploadedFile::fake()->image('remit.jpg'),
        ])->assertCreated()->json('data');
        $this->assertSame('submitted', $remittance['status']);
        $this->assertSame(30000, app(\App\Services\CodLedger::class)->balance($this->deliveryRider->id, $this->center->id)['outstanding']);
        $this->apiAs($this->center->user);
        $this->postJson('/api/sorting-center/cod/remittances/'.$remittance['id'].'/confirm', [
            'amount' => '300.00', 'file' => UploadedFile::fake()->image('received.jpg'),
        ])->assertOk();
        $balance = app(\App\Services\CodLedger::class)->balance($this->deliveryRider->id, $this->center->id);
        $this->assertSame(0, $balance['outstanding']);
        $this->assertSame(30000, $balance['received']);
        $this->assertDatabaseCount('seller_delivery_charges', 0);
        $this->assertSame('OUT_FOR_DELIVERY', $parcel->fresh()->status);
    }

    public function test_real_checkout_reaches_both_order_views_with_one_photo_and_verified_otp(): void
    {
        $this->checkout();
        $this->ready();
        $parcel = $this->dispatchToDelivery();
        $url = "/api/rider/deliveries/{$parcel->id}";
        $this->postJson($url.'/deliver', ['otp' => $this->deliveryOtp($parcel->id)])->assertUnprocessable();
        $this->postJson($url.'/upload-proof', ['file' => UploadedFile::fake()->image('proof.jpg'), 'type' => 'PHOTO'])->assertCreated();
        $otp = $this->deliveryOtp($parcel->id);
        $this->postJson($url.'/deliver', ['otp' => $otp === '000000' ? '111111' : '000000'])->assertUnprocessable();
        $this->postJson($url.'/deliver', ['otp' => $otp])->assertOk();
        $this->postJson($url.'/deliver', ['otp' => $otp])->assertUnprocessable();
        $this->assertSame('DELIVERED', $parcel->fresh()->status);
        $this->assertSame('delivered', $this->item->fresh()->status);
        $this->assertSame('delivered', $this->order->fresh()->status);
        $this->assertSame(8, $this->product->fresh()->stock);
        $this->assertSame('buyer', $this->order->fresh()->delivery_fee_payer);
        $this->assertDatabaseCount('parcels', 1);
        $this->assertDatabaseCount('pickup_requests', 1);
        $this->assertDatabaseCount('delivery_proofs', 1);
        $this->assertDatabaseCount('seller_delivery_charges', 0);
        $this->actingAs($this->seller, 'web')->get(route('seller.order.details', $this->item))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('orderItem.status', 'delivered'));
        $this->actingAs($this->buyer, 'web')->get(route('buyer.orders.show', $this->order))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('order.items.0.status', 'delivered')
                ->where('order.status_histories', fn ($events) => collect(['pending', 'processing', 'packed', 'shipped', 'out_for_delivery', 'delivered'])
                    ->diff(collect($events)->pluck('status'))->isEmpty()));
    }

    public function test_refused_checkout_order_restocks_only_once_after_verified_physical_seller_receipt(): void
    {
        $this->checkout();
        $this->ready();
        $parcel = $this->dispatchToDelivery();
        $this->postJson("/api/rider/deliveries/{$parcel->id}/fail", ['reason' => 'Refused'])->assertOk();
        $return = ParcelReturn::sole();
        $this->postJson("/api/rider/deliveries/{$parcel->id}/retry")->assertConflict();
        $this->apiAs($this->center->user);
        $this->postJson("/api/sorting-center/returns/{$return->id}/initiate", [
            'rider_id' => $this->deliveryRider->id, 'reason' => 'Buyer refused; return with current physical custodian.',
        ])->assertOk();
        $this->apiAs($this->deliveryRider->user);
        $this->postJson("/api/rider/returns/{$return->id}/pickup", ['physical_confirmation' => true])->assertOk();
        $this->postJson("/api/rider/returns/{$return->id}/handover", ['physical_confirmation' => true])->assertOk();
        $this->assertSame(8, $this->product->fresh()->stock);
        $this->actingAs($this->seller, 'web')->postJson(route('seller.logistics-returns.receive', $return->id), ['physical_confirmation' => true])->assertOk();
        $this->postJson(route('seller.logistics-returns.receive', $return->id), ['physical_confirmation' => true])->assertConflict();
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertSame('received', $return->fresh()->status);
        $this->assertDatabaseCount('parcel_returns', 1);
        $this->assertDatabaseCount('delivery_proofs', 0);
        $this->assertDatabaseCount('seller_delivery_charges', 0);
    }

    public function test_store_pickup_checkout_does_not_create_a_courier_dispatch(): void
    {
        $this->checkout('pickup');
        $this->ready();
        $this->assertDatabaseCount('parcels', 0);
        $this->assertDatabaseCount('pickup_requests', 0);
        $this->assertSame('0.00', $this->order->shipping_fee);
    }
}
