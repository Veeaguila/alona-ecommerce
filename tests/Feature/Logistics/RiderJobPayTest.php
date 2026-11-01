<?php

namespace Tests\Feature\Logistics;

use App\Models\{CartItem, Order, Parcel, RiderPayBatch, RiderPayDestination, RiderPayEarning, User};
use App\Services\{LogisticsAudit, RiderJobPay};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Tests\Support\OrderJourney;

class RiderJobPayTest extends OrderJourney
{
    private const BASE = '/api/sorting-center/payroll';

    private function rates(array $changes = []): void
    {
        $this->apiAs($this->center->user);
        $revision = $this->getJson(self::BASE)->assertOk()->json('data.settings.revision');
        $this->putJson(self::BASE.'/settings', $changes + ['enabled' => true, 'revision' => $revision,
            'pickup' => '25.00', 'delivery' => '40.00', 'transfer' => '150.00'])->assertOk();
    }

    private function receiving(): int
    {
        $this->apiAs($this->pickupRider->user);
        return $this->postJson('/api/rider/job-pay/destinations', ['method' => 'gcash', 'account_name' => 'Synthetic Rider',
            'account_number' => '09170000000'])->assertCreated()->json('data.id');
    }

    private function earned(int $cents = 2500): RiderPayEarning
    {
        return RiderPayEarning::create(['sorting_center_id' => $this->center->id, 'rider_id' => $this->pickupRider->id,
            'leg' => 'pickup', 'work_key' => 'fixture:'.Str::uuid(), 'active_key' => 'fixture:'.Str::uuid(),
            'amount_cents' => $cents, 'rate_revision' => 1, 'snapshot' => ['reference' => 'Synthetic completed shipment'], 'status' => 'earned', 'earned_at' => now()]);
    }

    private function prepared(): int
    {
        $destination = $this->receiving();
        $ids = [$this->earned()->id, $this->earned(4000)->id];
        $this->apiAs($this->center->user);
        return $this->postJson(self::BASE.'/payouts', ['rider_id' => $this->pickupRider->id, 'destination_id' => $destination,
            'earning_ids' => $ids, 'expected_amount' => '65.00', 'request_key' => (string) Str::uuid(), 'reviewed' => true])
            ->assertCreated()->assertJsonPath('data.status', 'prepared')->assertJsonPath('data.amount', '65.00')->json('data.id');
    }

    private function payment(array $extra = []): array
    {
        return $extra + ['expected_amount' => '65.00', 'payment_key' => (string) Str::uuid(), 'reference' => 'SYNTHETIC-REF-123',
            'actually_paid' => true, 'paid_from_payroll' => true, 'proof' => UploadedFile::fake()->createWithContent('receipt.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6WQAAAABJRU5ErkJggg=='))];
    }

    public function test_rates_start_unconfigured_and_require_owner_positive_rates_and_current_revision(): void
    {
        $this->apiAs($this->center->user);
        $this->getJson(self::BASE)->assertOk()->assertJsonPath('data.settings.enabled', false)->assertJsonPath('data.settings.pickup', null);
        $this->putJson(self::BASE.'/settings', ['enabled' => true, 'revision' => 0])->assertUnprocessable();
        $this->rates();
        $this->putJson(self::BASE.'/settings', ['enabled' => false, 'revision' => 0])->assertConflict();
        $this->apiAs($this->buyer);
        $this->getJson(self::BASE)->assertForbidden();
        $this->assertDatabaseCount('rider_pay_earnings', 0);
    }

    public function test_assignment_snapshots_rates_and_whole_seller_shipment_earns_once_after_all_physical_receipts(): void
    {
        $this->rates();
        $second = $this->product->replicate(); $second->slug = 'second-job-product'; $second->weight = 0; $second->save();
        $cart = CartItem::create(['user_id' => $this->buyer->id, 'product_id' => $second->id, 'quantity' => 1]);
        $this->actingAs($this->buyer, 'web')->post(route('buyer.orders.store'), ['shipping_address' => self::ADDRESS,
            'payment_method' => 'cod', 'shipping_method' => 'standard', 'selected_items' => [$this->cart->id, $cart->id], 'expected_shipping_fee' => 50])
            ->assertRedirect()->assertSessionHasNoErrors();
        $order = Order::sole();
        foreach ($order->items as $item) foreach (['process', 'pack', 'ready-for-pickup'] as $action) {
            $this->actingAs($this->seller, 'web')->patch(route('seller.orders.'.$action, $item))->assertRedirect()->assertSessionHasNoErrors();
        }
        $parcels = Parcel::orderBy('id')->get(); $this->assertCount(2, $parcels);
        $this->apiAs($this->center->user);
        foreach ($parcels as $parcel) $this->postJson('/api/sorting-center/pickup-requests/'.$parcel->pickupRequests()->sole()->id.'/confirm')->assertOk();
        $this->apiAs($this->pickupRider->user);
        $this->postJson('/api/rider/presence', ['action' => 'go_online'])->assertOk();
        $this->apiAs($this->center->user);
        $this->postJson('/api/sorting-center/pickup-dispatch/'.$parcels[0]->id.'/assign', ['rider_id' => $this->pickupRider->id])->assertOk();
        $this->assertDatabaseCount('rider_pay_earnings', 1);
        $this->assertSame(2, Parcel::where('pickup_rider_id', $this->pickupRider->id)->count());
        $this->rates(['pickup' => '99.00']);
        $this->apiAs($this->pickupRider->user);
        $this->postJson('/api/rider/pickups/'.$parcels[0]->id.'/acknowledge')->assertOk();
        foreach ($parcels as $parcel) $this->postJson('/api/rider/pickups/'.$parcel->id.'/confirm')->assertOk();
        $this->apiAs($this->center->user);
        $this->postJson('/api/sorting-center/parcels/mark-arrived', ['order_id' => $parcels[0]->order_id])->assertOk();
        $this->assertSame('pending', RiderPayEarning::sole()->status);
        $this->postJson('/api/sorting-center/parcels/mark-arrived', ['order_id' => $parcels[1]->order_id])->assertOk();
        $earning = RiderPayEarning::sole();
        $this->assertSame('earned', $earning->status); $this->assertSame(2500, $earning->amount_cents);
        $this->assertSame('seller_shipment', $earning->snapshot['unit']);
        foreach ($parcels as $parcel) $this->postJson('/api/sorting-center/parcels/'.$parcel->id.'/sort', ['area_id' => $this->area->id])->assertOk();
        $this->putJson('/api/sorting-center/riders/'.$this->deliveryRider->id.'/delivery-areas', ['area_ids' => [$this->area->id]])->assertOk();
        $this->apiAs($this->deliveryRider->user);
        $this->postJson('/api/rider/presence', ['action' => 'go_online'])->assertOk();
        $this->apiAs($this->center->user);
        $this->postJson('/api/sorting-center/parcels/'.$parcels[0]->id.'/assign', ['rider_id' => $this->deliveryRider->id])->assertOk();
        $this->assertDatabaseCount('rider_pay_earnings', 2);
        $this->apiAs($this->deliveryRider->user);
        foreach ($parcels as $index => $parcel) {
            $base = '/api/rider/deliveries/'.$parcel->id;
            $this->postJson($base.'/start')->assertOk();
            $this->postJson($base.'/upload-proof', ['file' => $this->payment()['proof'], 'type' => 'PHOTO'])->assertCreated();
            $this->postJson($base.'/deliver', ['otp' => $this->deliveryOtp($parcel->id)])->assertOk();
            $this->assertSame($index === 0 ? 'pending' : 'earned', RiderPayEarning::where('leg', 'delivery')->sole()->status);
        }
        $this->getJson('/api/rider/job-pay')->assertOk()->assertJsonPath('data.summary.unpaid', '40.00');
        $this->assertDatabaseCount('rider_delivery_earnings', 0);
    }

    private function confirmPayHandover(Parcel $parcel, string $leg, \App\Models\Rider $source, \App\Models\Rider $target): int
    {
        $this->apiAs($target->user);
        $this->postJson('/api/rider/presence', ['action' => 'go_online'])->assertOk();
        $this->apiAs($this->center->user);
        $id = $this->postJson('/api/sorting-center/parcels/'.$parcel->id.'/handovers', [
            'request_key' => (string) Str::uuid(), 'leg' => $leg, 'rider_id' => $target->id,
            'reason' => 'Synthetic supervised whole-shipment transfer to the receiving Rider.',
        ])->assertCreated()->json('data.id');
        foreach ([$source, $target] as $rider) {
            $this->apiAs($rider->user);
            $this->postJson('/api/rider/handovers/'.$id.'/acknowledge', ['physical_handover' => true])->assertOk();
        }
        $this->apiAs($this->center->user);
        $this->postJson('/api/sorting-center/handovers/'.$id.'/confirm')->assertOk();
        return $id;
    }

    public function test_pickup_handover_preserves_the_agreed_rate_and_center_receipt_earns_for_the_new_custodian(): void
    {
        $this->rates();
        $second = $this->product->replicate(); $second->slug = 'handover-second-product'; $second->weight = 0; $second->save();
        $cart = CartItem::create(['user_id' => $this->buyer->id, 'product_id' => $second->id, 'quantity' => 1]);
        $this->actingAs($this->buyer, 'web')->post(route('buyer.orders.store'), ['shipping_address' => self::ADDRESS,
            'payment_method' => 'cod', 'shipping_method' => 'standard', 'selected_items' => [$this->cart->id, $cart->id], 'expected_shipping_fee' => 50])
            ->assertRedirect()->assertSessionHasNoErrors();
        foreach (Order::sole()->items as $item) foreach (['process', 'pack', 'ready-for-pickup'] as $action) {
            $this->actingAs($this->seller, 'web')->patch(route('seller.orders.'.$action, $item))->assertRedirect()->assertSessionHasNoErrors();
        }
        $parcels = Parcel::orderBy('id')->get(); $this->assertCount(2, $parcels); $parcel = $parcels->first();
        $this->apiAs($this->center->user);
        foreach ($parcels as $member) $this->postJson('/api/sorting-center/pickup-requests/'.$member->pickupRequests()->sole()->id.'/confirm')->assertOk();
        $this->apiAs($this->pickupRider->user);
        $this->postJson('/api/rider/presence', ['action' => 'go_online'])->assertOk();
        $this->apiAs($this->center->user);
        $this->postJson('/api/sorting-center/pickup-dispatch/'.$parcel->id.'/assign', ['rider_id' => $this->pickupRider->id])->assertOk();
        $this->apiAs($this->pickupRider->user);
        $this->postJson('/api/rider/pickups/'.$parcel->id.'/acknowledge')->assertOk();
        foreach ($parcels as $member) $this->postJson('/api/rider/pickups/'.$member->id.'/confirm')->assertOk();
        $old = RiderPayEarning::sole();
        $this->rates(['enabled' => false, 'pickup' => '99.00']);
        $handover = $this->confirmPayHandover($parcel, 'pickup', $this->pickupRider, $this->deliveryRider);
        $this->postJson('/api/sorting-center/parcels/mark-arrived', ['order_id' => $parcel->order_id])->assertOk();
        $this->assertSame('pending', RiderPayEarning::whereNotNull('active_key')->sole()->status);
        $this->postJson('/api/sorting-center/parcels/mark-arrived', ['order_id' => $parcels->last()->order_id])->assertOk();
        $current = RiderPayEarning::whereNotNull('active_key')->sole();
        $this->assertSame('cancelled', $old->fresh()->status);
        $this->assertSame('earned', $current->status);
        $this->assertSame($this->deliveryRider->id, $current->rider_id);
        $this->assertSame(2500, $current->amount_cents);
        $this->assertSame($old->rate_revision, $current->rate_revision);
        $this->assertSame($handover, $current->snapshot['handover_id']);
        $this->assertDatabaseCount('rider_delivery_earnings', 0);
    }

    public function test_delivery_handover_allows_photo_otp_completion_without_repricing_or_moving_earned_pickup_pay(): void
    {
        $this->rates(); $this->checkout(); $this->ready();
        $parcel = $this->dispatchToDelivery();
        $old = RiderPayEarning::where('leg', 'delivery')->sole();
        $pickup = RiderPayEarning::where('leg', 'pickup')->sole();
        $this->pickupRider->areas()->attach($this->area);
        $this->rates(['delivery' => '99.00']);
        $this->confirmPayHandover($parcel, 'delivery', $this->deliveryRider, $this->pickupRider);
        $this->apiAs($this->pickupRider->user);
        $base = '/api/rider/deliveries/'.$parcel->id;
        $this->postJson($base.'/upload-proof', ['file' => $this->payment()['proof'], 'type' => 'PHOTO'])->assertCreated();
        $this->postJson($base.'/deliver', ['otp' => $this->deliveryOtp($parcel->id)])->assertOk();
        $current = RiderPayEarning::where('leg', 'delivery')->whereNotNull('active_key')->sole();
        $this->assertSame('cancelled', $old->fresh()->status);
        $this->assertSame('earned', $current->status);
        $this->assertSame($this->pickupRider->id, $current->rider_id);
        $this->assertSame(4000, $current->amount_cents);
        $this->assertSame('earned', $pickup->fresh()->status);
        $this->assertSame(2500, $pickup->fresh()->amount_cents);
        $this->getJson('/api/rider/job-pay')->assertOk()->assertJsonPath('data.summary.unpaid', '65.00');
        $this->assertDatabaseCount('rider_pay_earnings', 3);
    }

    public function test_owner_can_clear_an_optional_transfer_rate_without_removing_pickup_and_delivery_rates(): void
    {
        $this->rates(); $this->rates(['transfer' => null]);
        $this->getJson(self::BASE)->assertOk()->assertJsonPath('data.settings.transfer', null)
            ->assertJsonPath('data.settings.pickup', '25.00')->assertJsonPath('data.settings.delivery', '40.00');
    }

    public function test_grouped_payout_reserves_only_earned_owned_jobs_and_is_retry_safe(): void
    {
        $id = $this->prepared();
        $batch = RiderPayBatch::findOrFail($id);
        $ids = RiderPayEarning::where('batch_id', $id)->pluck('id')->all();
        $body = ['rider_id' => $this->pickupRider->id, 'destination_id' => $batch->destination_id, 'earning_ids' => $ids,
            'expected_amount' => '65.00', 'request_key' => $batch->request_key, 'reviewed' => true];
        $this->postJson(self::BASE.'/payouts', $body)->assertCreated()->assertJsonPath('data.id', $id);
        $body['request_key'] = (string) Str::uuid();
        $this->postJson(self::BASE.'/payouts', $body)->assertConflict();
        $this->assertDatabaseCount('rider_pay_batches', 1);
        $this->getJson(self::BASE)->assertOk()->assertJsonPath('data.summary.unpaid', '0.00')->assertJsonPath('data.summary.processing', '65.00');
    }

    public function test_actual_payment_proof_ack_and_duplicate_guards_leave_cod_and_historical_ledgers_untouched(): void
    {
        $id = $this->prepared(); $payment = $this->payment();
        $this->postJson(self::BASE.'/payouts/'.$id.'/pay', $payment)->assertOk()->assertJsonPath('data.status', 'paid');
        $this->postJson(self::BASE.'/payouts/'.$id.'/pay', $payment)->assertOk();
        $this->postJson(self::BASE.'/payouts/'.$id.'/pay', $this->payment())->assertConflict();
        $this->postJson(self::BASE.'/payouts/'.$id.'/cancel', ['reason' => 'Already sent', 'not_sent' => true])->assertConflict();
        $this->getJson(self::BASE)->assertOk()->assertJsonPath('data.summary.paid', '65.00')->assertJsonPath('data.summary.processing', '0.00');
        $this->apiAs($this->deliveryRider->user);
        $this->get('/api/rider/job-pay/payouts/'.$id.'/proof')->assertNotFound();
        $this->postJson('/api/rider/job-pay/payouts/'.$id.'/acknowledge', ['received' => true])->assertNotFound();
        $this->apiAs($this->pickupRider->user);
        $this->get('/api/rider/job-pay/payouts/'.$id.'/proof')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->postJson('/api/rider/job-pay/payouts/'.$id.'/acknowledge', ['received' => true])->assertOk();
        $this->postJson('/api/rider/job-pay/payouts/'.$id.'/acknowledge', ['received' => true])->assertOk();
        $this->assertNotNull(RiderPayBatch::find($id)->acknowledged_at);
        $this->assertDatabaseCount('cod_collections', 0); $this->assertDatabaseCount('cod_remittances', 0);
        $this->assertDatabaseCount('rider_delivery_earnings', 0); $this->assertDatabaseCount('seller_delivery_charges', 0);
        $this->assertCount(1, Storage::disk('local')->allFiles('rider-payout-proofs'));
    }

    public function test_cancel_unsent_releases_reservations_and_preserves_batch_history(): void
    {
        $id = $this->prepared();
        $this->postJson(self::BASE.'/payouts/'.$id.'/cancel', ['reason' => 'Incorrect receiving method; never sent', 'not_sent' => true])->assertOk();
        $this->getJson(self::BASE)->assertOk()->assertJsonPath('data.summary.unpaid', '65.00');
        $this->assertDatabaseCount('rider_pay_batches', 1); $this->assertDatabaseCount('rider_pay_batch_items', 2);
        $this->apiAs($this->pickupRider->user);
        $this->postJson('/api/rider/job-pay/payouts/'.$id.'/acknowledge', ['received' => true])->assertConflict();
    }

    public function test_amount_consent_private_receiving_data_and_owner_scope(): void
    {
        $id = $this->prepared();
        $this->postJson(self::BASE.'/payouts/'.$id.'/pay', $this->payment(['expected_amount' => '64.99']))->assertConflict();
        $this->postJson(self::BASE.'/payouts/'.$id.'/pay', $this->payment(['actually_paid' => false]))->assertUnprocessable();
        $stored = DB::table('rider_pay_destinations')->sole()->instructions;
        $this->assertStringNotContainsString('09170000000', $stored);
        $this->getJson(self::BASE)->assertOk()->assertJsonMissing(['account_number' => '09170000000']);
        $other = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        \App\Models\SortingCenter::create(['user_id' => $other->id, 'business_name' => 'Other company', 'approval_status' => 'approved']);
        $this->apiAs($other);
        $this->getJson(self::BASE.'/payouts/'.$id)->assertNotFound();
        $this->postJson(self::BASE.'/payouts/'.$id.'/pay', $this->payment())->assertNotFound();
        $this->assertSame('prepared', RiderPayBatch::find($id)->status);
    }

    public function test_payment_audit_failure_rolls_back_paid_state_and_private_evidence(): void
    {
        $id = $this->prepared();
        $this->partialMock(LogisticsAudit::class, function ($mock) { $mock->shouldReceive('record')->once()->andThrow(new \RuntimeException('Synthetic audit failure')); });
        $this->postJson(self::BASE.'/payouts/'.$id.'/pay', $this->payment())->assertStatus(500);
        $this->assertSame('prepared', RiderPayBatch::find($id)->status);
        $this->assertSame(2, RiderPayEarning::where('batch_id', $id)->count());
        $this->assertCount(0, Storage::disk('local')->allFiles('rider-payout-proofs'));
    }

    public function test_disabled_settings_do_not_create_historical_or_guessed_earnings(): void
    {
        $this->checkout(); $this->ready(); $this->dispatchToDelivery();
        $this->assertDatabaseCount('rider_pay_earnings', 0);
        $this->rates();
        $this->apiAs($this->pickupRider->user);
        $this->getJson('/api/rider/job-pay')->assertOk()->assertJsonPath('data.summary.earned', '0.00');
        $this->assertDatabaseCount('rider_pay_earnings', 0);
    }

    public function test_transfer_manifest_earns_once_after_every_parcel_arrives_and_rate_changes_do_not_reprice_it(): void
    {
        $this->rates();
        $from = \App\Models\LogisticsHub::create(['sorting_center_id' => $this->center->id, 'code' => 'PAY-A', 'name' => 'Origin', 'address' => self::ADDRESS,
            'city_code' => '1381300000', 'pickup_city_codes' => ['1381300000'], 'delivery_city_codes' => [], 'is_active' => true]);
        $to = \App\Models\LogisticsHub::create(['sorting_center_id' => $this->center->id, 'code' => 'PAY-B', 'name' => 'Destination', 'address' => 'Synthetic Cebu',
            'city_code' => '0730600000', 'pickup_city_codes' => [], 'delivery_city_codes' => ['0730600000'], 'is_active' => true]);
        $this->pickupRider->forceFill(['logistics_hub_id' => $from->id])->save();
        $parcels = [];
        foreach ([1, 2] as $number) {
            $parcel = Parcel::create(['order_id' => 'PAY-TRANSFER-'.$number, 'seller_id' => $this->seller->id, 'buyer_id' => $this->buyer->id,
                'sorting_center_id' => $this->center->id, 'status' => 'AT_SORTING_CENTER', 'delivery_address' => 'Synthetic Cebu', 'collection_address' => self::ADDRESS,
                'delivery_province' => '', 'delivery_municipality' => '', 'delivery_barangay' => '']);
            \App\Models\ParcelHubJourney::create(['parcel_id' => $parcel->id, 'sorting_center_id' => $this->center->id,
                'origin_hub_id' => $from->id, 'destination_hub_id' => $to->id, 'current_hub_id' => $from->id, 'origin_received_at' => now(),
                'route_snapshot' => ['origin_hub_name' => 'Origin', 'destination_hub_name' => 'Destination', 'transport_mode' => 'road']]);
            $parcels[] = $parcel;
        }
        $this->apiAs($this->pickupRider->user);
        $this->postJson('/api/rider/presence', ['action' => 'go_online'])->assertOk();
        $this->apiAs($this->center->user);
        $body = ['hub_id' => $from->id, 'rider_id' => $this->pickupRider->id, 'parcel_ids' => array_column($parcels, 'id'),
            'transport_reference' => 'SYNTHETIC-TRIP-A', 'request_key' => (string) Str::uuid(), 'physical_confirmation' => true];
        $id = $this->postJson(self::BASE.'/trips', $body)->assertCreated()->json('data.id');
        $this->postJson(self::BASE.'/trips', $body)->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('rider_pay_earnings', 1);
        $this->rates(['transfer' => '200.00']);
        $this->postJson('/api/sorting-center/hub-journeys/'.$parcels[0]->id.'/receive', ['hub_id' => $to->id, 'physical_confirmation' => true])->assertOk();
        $this->assertSame('pending', RiderPayEarning::sole()->status);
        $this->postJson('/api/sorting-center/hub-journeys/'.$parcels[1]->id.'/receive', ['hub_id' => $to->id, 'physical_confirmation' => true])->assertOk();
        $this->assertSame('earned', RiderPayEarning::sole()->status);
        $this->assertSame(15000, RiderPayEarning::sole()->amount_cents);
        $this->postJson('/api/sorting-center/hub-journeys/'.$parcels[1]->id.'/receive', ['hub_id' => $to->id, 'physical_confirmation' => true])->assertConflict();
        $this->assertDatabaseCount('rider_pay_earnings', 1);
    }

    public function test_unearned_or_foreign_jobs_and_another_riders_destination_cannot_be_grouped(): void
    {
        $destination = $this->receiving(); $row = $this->earned();
        $row2 = $this->earned();
        DB::table('rider_pay_earnings')->where('id', $row2->id)->update(['status' => 'pending', 'earned_at' => null]);
        $this->apiAs($this->center->user);
        $body = ['rider_id' => $this->pickupRider->id, 'destination_id' => $destination, 'earning_ids' => [$row->id, $row2->id],
            'expected_amount' => '50.00', 'request_key' => (string) Str::uuid(), 'reviewed' => true];
        $this->postJson(self::BASE.'/payouts', $body)->assertConflict();
        $body['earning_ids'] = [$row->id]; $body['expected_amount'] = '25.00'; $body['rider_id'] = $this->deliveryRider->id;
        $this->postJson(self::BASE.'/payouts', $body)->assertNotFound();
        $this->assertDatabaseCount('rider_pay_batches', 0);
    }

    public function test_grouped_totals_can_exceed_one_rate_without_overflow_or_float_rounding(): void
    {
        $destination = $this->receiving();
        $ids = [];
        for ($i = 0; $i < 11; $i++) $ids[] = $this->earned(100000000)->id;
        $this->apiAs($this->center->user);
        $id = $this->postJson(self::BASE.'/payouts', ['rider_id' => $this->pickupRider->id, 'destination_id' => $destination,
            'earning_ids' => $ids, 'expected_amount' => '11000000.00', 'request_key' => (string) Str::uuid(), 'reviewed' => true])
            ->assertCreated()->assertJsonPath('data.amount', '11000000.00')->json('data.id');
        $this->postJson(self::BASE.'/payouts/'.$id.'/pay', $this->payment(['expected_amount' => '11000000.00']))
            ->assertOk()->assertJsonPath('data.status', 'paid');
        $this->assertSame(1100000000, RiderPayBatch::find($id)->amount_cents);
    }

    public function test_review_accepts_each_optional_date_and_rejects_a_reversed_range(): void
    {
        $this->earned();
        $this->apiAs($this->center->user);
        $url = self::BASE.'/riders/'.$this->pickupRider->id.'/review';
        $this->getJson($url.'?to='.now('Asia/Manila')->toDateString())->assertOk()->assertJsonPath('data.amount', '25.00');
        $this->getJson($url.'?from='.now('Asia/Manila')->toDateString())->assertOk()->assertJsonPath('data.amount', '25.00');
        $this->getJson($url.'?from=2026-12-31&to=2026-01-01')->assertUnprocessable();
    }

    public function test_hub_staff_cannot_read_or_change_company_payroll(): void
    {
        $id = $this->prepared();
        $hub = \App\Models\LogisticsHub::create(['sorting_center_id' => $this->center->id, 'code' => 'PAY-STAFF', 'name' => 'Synthetic hub',
            'address' => self::ADDRESS, 'city_code' => '1381300000', 'pickup_city_codes' => ['1381300000'], 'delivery_city_codes' => [], 'is_active' => true]);
        $staff = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        \App\Models\LogisticsHubStaff::create(['user_id' => $staff->id, 'sorting_center_id' => $this->center->id, 'logistics_hub_id' => $hub->id, 'is_active' => true]);
        $this->apiAs($staff);
        $this->getJson(self::BASE)->assertForbidden();
        $this->getJson(self::BASE.'/payouts/'.$id)->assertForbidden();
        $this->postJson(self::BASE.'/payouts/'.$id.'/pay', $this->payment())->assertForbidden();
        $this->putJson(self::BASE.'/settings', ['enabled' => false, 'revision' => 0])->assertForbidden();
        $this->assertSame('prepared', RiderPayBatch::find($id)->status);
    }

    public function test_a_payment_reference_cannot_pay_two_batches_and_a_revoked_payee_requires_review(): void
    {
        $first = $this->prepared();
        $this->postJson(self::BASE.'/payouts/'.$first.'/pay', $this->payment())->assertOk();
        $second = $this->prepared();
        $this->postJson(self::BASE.'/payouts/'.$second.'/pay', $this->payment())->assertConflict();
        $this->pickupRider->user->update(['status' => 'suspended']);
        $this->postJson(self::BASE.'/payouts/'.$second.'/pay', $this->payment(['reference' => 'DIFFERENT-REF-123']))->assertUnprocessable();
        $this->assertSame('prepared', RiderPayBatch::find($second)->status);
    }
}
