<?php

namespace Tests\Feature\Logistics;

use App\Models\{LogisticsAuditEvent, Parcel, ParcelReturn, StockAdjustment, User};
use App\Services\SellerShipmentFinance;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

class ReturnToSellerTest extends CompleteWorkflowTest
{
    private function startedParcel(): Parcel
    {
        $this->item->product->update(['stock' => 19]);
        $this->item->order->update(['delivery_fee_payer' => 'seller']);
        app(SellerShipmentFinance::class)->createMembership($this->item->order);
        $this->actingAs($this->item->product->seller, 'web')->patch(route('seller.orders.ready-for-pickup', $this->item))->assertRedirect();
        $parcel = $this->item->parcel()->sole();
        Sanctum::actingAs($this->center->user); $this->postJson('/api/sorting-center/pickup-requests/'.$parcel->pickupRequests()->sole()->id.'/confirm')->assertOk();
        Sanctum::actingAs($this->pickupRider->user); $this->postJson('/api/rider/pickups/'.$parcel->id.'/accept')->assertOk(); $this->postJson('/api/rider/pickups/'.$parcel->id.'/confirm')->assertOk();
        Sanctum::actingAs($this->center->user); $this->postJson('/api/sorting-center/parcels/mark-arrived', ['order_id' => $parcel->order_id])->assertOk();
        $this->postJson('/api/sorting-center/parcels/'.$parcel->id.'/sort', ['area_id' => $this->area->id])->assertOk(); $this->pickupRider->areas()->attach($this->area);
        $this->postJson('/api/sorting-center/parcels/'.$parcel->id.'/assign', ['rider_id' => $this->pickupRider->id])->assertOk();
        Sanctum::actingAs($this->pickupRider->user); $this->postJson('/api/rider/deliveries/'.$parcel->id.'/start')->assertOk();
        return $parcel;
    }
    private function threeFailures(Parcel $parcel): ParcelReturn
    {
        foreach (['Buyer not home', 'Wrong address', 'Buyer not home'] as $index => $reason) {
            if ($index > 0) $this->postJson('/api/rider/deliveries/'.$parcel->id.'/retry')->assertOk();
            $this->postJson('/api/rider/deliveries/'.$parcel->id.'/fail', ['reason' => $reason])->assertOk()->assertJsonPath('data.status', 'OUT_FOR_DELIVERY');
        }
        return ParcelReturn::where('parcel_id', $parcel->id)->sole();
    }
    public function test_failures_are_distinct_attempts_and_a_new_attempt_requires_current_photo_and_never_invents_return(): void
    {
        $parcel = $this->startedParcel();
        $this->postJson('/api/rider/deliveries/'.$parcel->id.'/upload-proof', ['file' => UploadedFile::fake()->image('first-attempt.jpg')])->assertCreated();
        $this->postJson('/api/rider/deliveries/'.$parcel->id.'/fail', ['reason' => 'Buyer not home'])->assertOk();
        $this->postJson('/api/rider/deliveries/'.$parcel->id.'/fail', ['reason' => 'Buyer not home'])->assertConflict();
        $this->postJson('/api/rider/deliveries/'.$parcel->id.'/deliver', ['otp' => $this->deliveryOtp($parcel->id)])->assertUnprocessable();
        $this->assertSame('OUT_FOR_DELIVERY', $parcel->fresh()->status); $this->assertDatabaseCount('parcel_returns', 0);
        $this->postJson('/api/rider/deliveries/'.$parcel->id.'/retry')->assertOk(); $this->postJson('/api/rider/deliveries/'.$parcel->id.'/retry')->assertConflict();
        $this->postJson('/api/rider/deliveries/'.$parcel->id.'/deliver', ['otp' => $this->deliveryOtp($parcel->id)])->assertUnprocessable()->assertJsonPath('code', 'valid_photo_required');
        $this->getJson('/api/rider/deliveries')->assertOk()->assertJsonPath('data.0.attempt_count', 1)->assertJsonPath('data.0.has_valid_photo_proof', false);
        $this->assertSame(19, $this->item->product->fresh()->stock);
    }
    public function test_three_failures_return_custody_and_seller_receipt_restock_once_without_automatic_refund_or_earnings(): void
    {
        $parcel = $this->startedParcel(); $return = $this->threeFailures($parcel);
        $this->assertSame('eligible', $return->status);
        $this->postJson('/api/rider/deliveries/'.$parcel->id.'/fail', ['reason' => 'Buyer not home'])->assertUnprocessable();
        $this->postJson('/api/rider/deliveries/'.$parcel->id.'/retry')->assertConflict();
        Sanctum::actingAs($this->center->user);
        $this->getJson('/api/sorting-center/returns')->assertOk()->assertJsonCount(3, 'data.data.0.parcel.delivery_attempts');
        $url = '/api/sorting-center/returns/'.$return->id.'/initiate';
        $this->postJson($url, ['rider_id' => $this->deliveryRider->id, 'reason' => 'Transfer without physical handover is not allowed.'])->assertUnprocessable();
        $this->postJson($url, ['rider_id' => $this->pickupRider->id, 'reason' => 'Return to Seller after three unsuccessful attempts.'])->assertOk();
        Sanctum::actingAs($this->pickupRider->user);
        $this->postJson('/api/rider/returns/'.$return->id.'/pickup', [])->assertUnprocessable();
        $this->postJson('/api/rider/returns/'.$return->id.'/pickup', ['physical_confirmation' => true])->assertOk();
        $this->postJson('/api/rider/returns/'.$return->id.'/pickup', ['physical_confirmation' => true])->assertConflict();
        Sanctum::actingAs($this->outsider->user); $this->postJson('/api/rider/returns/'.$return->id.'/handover', ['physical_confirmation' => true])->assertForbidden();
        Sanctum::actingAs($this->pickupRider->user);
        $this->postJson('/api/rider/returns/'.$return->id.'/handover', ['physical_confirmation' => true])->assertOk();
        $this->postJson('/api/rider/returns/'.$return->id.'/handover', ['physical_confirmation' => true])->assertConflict();
        $this->getJson('/api/rider/returns?view=history')->assertOk()->assertJsonPath('data.data.0.status', 'handed_over');
        $this->assertSame('FAILED_RETURNED', $parcel->fresh()->status); $this->assertSame('cancelled', $this->item->order->fresh()->status);
        $this->assertSame(19, $this->item->product->fresh()->stock); $this->assertDatabaseCount('stock_adjustments', 0);
        $foreign = User::factory()->create(['usertype' => 'seller', 'status' => 'approved']);
        $this->actingAs($foreign, 'web')->postJson(route('seller.logistics-returns.receive', $return->id), ['physical_confirmation' => true])->assertForbidden();
        $seller = $this->item->product->seller;
        $this->actingAs($seller, 'web')->get(route('seller.order.details', $this->item))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('deliveryDetails.return_to_seller.status', 'handed_over'));
        $this->actingAs($seller, 'web')->postJson(route('seller.logistics-returns.receive', $return->id), ['physical_confirmation' => true])->assertOk();
        $this->actingAs($seller, 'web')->postJson(route('seller.logistics-returns.receive', $return->id), ['physical_confirmation' => true])->assertConflict();
        $this->assertSame(20, $this->item->product->fresh()->stock); $this->assertSame(1, StockAdjustment::where('reference_number', 'RTS-'.$return->id)->count());
        $this->assertSame('received', $return->fresh()->status); $this->assertNotNull($return->fresh()->restocked_at);
        $this->actingAs($this->item->order->user, 'web')->get(route('buyer.orders.show', $this->item->order))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('order.status', 'cancelled')->where('order.items.0.return_to_seller.status', 'received'));
        $this->assertDatabaseCount('refund_ledgers', 0); $this->assertDatabaseCount('seller_delivery_charges', 0); $this->assertDatabaseCount('rider_delivery_earnings', 0);
        $this->assertSame(1, LogisticsAuditEvent::where('action', 'rts_seller_received')->count());
    }

    public function test_prepaid_return_starts_pending_review_only_and_blocks_existing_execution_paths_without_duplicate_stock_or_money(): void
    {
        $this->item->order->update(['payment_method' => 'gcash', 'payment_status' => 'paid']);
        $parcel = $this->startedParcel(); $return = $this->threeFailures($parcel);
        Sanctum::actingAs($this->center->user);
        $this->postJson('/api/sorting-center/returns/'.$return->id.'/initiate', ['rider_id' => $this->pickupRider->id, 'reason' => 'Return prepaid parcel after three failed attempts.'])->assertOk();
        Sanctum::actingAs($this->pickupRider->user);
        $this->postJson('/api/rider/returns/'.$return->id.'/pickup', ['physical_confirmation' => true])->assertOk();
        $this->postJson('/api/rider/returns/'.$return->id.'/handover', ['physical_confirmation' => true])->assertOk();
        $this->assertDatabaseCount('order_return_requests', 0);
        $seller = $this->item->product->seller;
        $this->actingAs($seller, 'web')->postJson(route('seller.logistics-returns.receive', $return->id), ['physical_confirmation' => true])->assertOk();
        $review = \App\Models\OrderReturnRequest::sole();
        $this->assertSame('pending', $review->status); $this->assertSame('100.00', $review->requested_amount);
        $this->assertTrue($review->restocked); $this->assertNotNull($review->item_received_at);
        $this->get(route('seller.returns'))->assertInertia(fn (Assert $page) => $page->where('returnRequests.data.0.logistics_execution_blocked', true));
        foreach (['decision', 'tracking', 'inspect-resolve'] as $action) {
            $this->postJson(route('seller.returns.'.$action, $review), ['decision' => 'refund_now', 'restock_inventory' => true])->assertUnprocessable();
        }
        $this->actingAs(User::factory()->create(['usertype' => 'seller', 'status' => 'approved']), 'web');
        foreach (['decision', 'tracking', 'inspect-resolve'] as $action) $this->postJson(route('seller.returns.'.$action, $review), [])->assertForbidden();
        $this->assertDatabaseCount('refund_ledgers', 0); $this->assertDatabaseCount('seller_delivery_charges', 0); $this->assertDatabaseCount('rider_delivery_earnings', 0);
        $this->assertSame(20, $this->item->product->fresh()->stock); $this->assertDatabaseCount('stock_adjustments', 1);
        $this->assertSame(1, LogisticsAuditEvent::where('action', 'logistics_refund_requested')->count());
    }

    public function test_refused_goes_to_rts_on_first_attempt_and_invalid_reasons_never_create_attempts(): void
    {
        $parcel = $this->startedParcel();
        $this->postJson('/api/rider/deliveries/'.$parcel->id.'/fail', ['reason' => 'arbitrary text'])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->assertDatabaseCount('parcel_delivery_attempts', 0);
        $this->postJson('/api/rider/deliveries/'.$parcel->id.'/fail', ['reason' => 'Refused'])->assertOk();
        $this->assertDatabaseCount('parcel_delivery_attempts', 1);
        $this->assertDatabaseHas('parcel_returns', ['parcel_id' => $parcel->id, 'status' => 'eligible']);
        $this->getJson('/api/rider/deliveries')->assertOk()->assertJsonPath('data.0.rts_eligible', true)->assertJsonPath('data.0.awaiting_retry', false);
        $this->postJson('/api/rider/deliveries/'.$parcel->id.'/retry')->assertConflict();
        $this->postJson('/api/rider/deliveries/'.$parcel->id.'/deliver', ['otp' => '123456'])->assertUnprocessable();
        $this->assertSame('OUT_FOR_DELIVERY', $parcel->fresh()->status);
        $this->assertSame(19, $this->item->product->fresh()->stock);
        $this->assertDatabaseCount('seller_delivery_charges', 0);
    }
}
