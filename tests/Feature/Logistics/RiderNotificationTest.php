<?php

namespace Tests\Feature\Logistics;

use App\Models\{Order, Parcel, PickupRequest, Rider, RiderDeliveryEarning, RiderNotification, SellerDeliveryCharge, SellerShipment, SortingCenter, SortingCenterConversation, SortingCenterMessage, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiderNotificationTest extends TestCase
{
    // Real outer commits are needed to exercise afterCommit; never use an actual database.
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    private function fixture(): array
    {
        $centerUser = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        $center = SortingCenter::create(['user_id' => $centerUser->id, 'business_name' => 'Center', 'approval_status' => 'approved']);
        $user = User::factory()->create(['usertype' => 'rider', 'status' => 'approved']);
        $rider = Rider::create(['user_id' => $user->id, 'sorting_center_id' => $center->id, 'name' => 'Rider',
            'contact' => '09170000001', 'vehicle_type' => 'bicycle', 'plate_number' => '', 'approval_status' => 'approved', 'is_active' => true]);
        $seller = User::factory()->create(['usertype' => 'seller']);
        $buyer = User::factory()->create();
        $parcel = Parcel::create(['order_id' => Str::uuid(), 'seller_id' => $seller->id, 'buyer_id' => $buyer->id,
            'delivery_address' => 'Destination', 'delivery_province' => '', 'delivery_municipality' => '', 'delivery_barangay' => '',
            'collection_address' => 'Collection', 'status' => 'PLACED', 'sorting_center_id' => $center->id]);

        return compact('user', 'rider', 'center', 'centerUser', 'seller', 'buyer', 'parcel');
    }

    public function test_events_publish_after_commit_once_and_rollback_publishes_nothing(): void
    {
        $f = $this->fixture();
        DB::beginTransaction();
        $pickup = PickupRequest::create(['parcel_id' => $f['parcel']->id, 'seller_id' => $f['seller']->id, 'status' => 'confirmed']);
        $this->assertDatabaseCount('rider_notifications', 0);
        DB::commit();
        $this->assertDatabaseCount('rider_notifications', 1);
        // Replaying the same model event cannot duplicate the same source event.
        app(\App\Observers\RiderNotificationObserver::class)->created($pickup);
        $this->assertDatabaseCount('rider_notifications', 1);
        DB::beginTransaction();
        $f['parcel']->update(['pickup_rider_id' => $f['rider']->id]);
        DB::rollBack();
        $this->assertDatabaseCount('rider_notifications', 1);
        $f['parcel']->refresh()->update(['pickup_rider_id' => $f['rider']->id]);
        $f['parcel']->update(['status' => 'PICKED_UP']);
        $this->assertDatabaseCount('rider_notifications', 3);
        $this->assertDatabaseHas('rider_notifications', ['user_id' => $f['user']->id, 'type' => 'parcel_status']);
    }

    public function test_own_feed_read_marker_pagination_and_role_guards(): void
    {
        $f = $this->fixture();
        $f['parcel']->update(['assigned_rider_id' => $f['rider']->id]);
        $row = RiderNotification::firstOrFail();
        $this->getJson('/api/rider/notifications')->assertUnauthorized();
        Sanctum::actingAs($f['seller']);
        $this->getJson('/api/rider/notifications')->assertForbidden();
        $other = $this->fixture();
        Sanctum::actingAs($other['user']);
        $this->getJson('/api/rider/notifications')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('unread_count', 0);
        $this->postJson('/api/rider/notifications/'.$row->id.'/read')->assertNotFound();
        Sanctum::actingAs($f['user']);
        $this->getJson('/api/rider/notifications?per_page=1')->assertOk()->assertJsonPath('unread_count', 1)->assertJsonPath('data.0.data.screen', 'deliveries');
        $this->postJson('/api/rider/notifications/'.$row->id.'/read')->assertOk();
        $readAt = $row->fresh()->read_at;
        $this->travel(10)->seconds();
        $this->postJson('/api/rider/notifications/'.$row->id.'/read')->assertOk();
        $this->assertEquals($readAt, $row->fresh()->read_at);
        $this->getJson('/api/rider/notifications')->assertOk()->assertJsonPath('unread_count', 0);
        $this->getJson('/api/rider/notifications?per_page=101')->assertUnprocessable();
    }

    public function test_account_and_logistics_messages_are_rider_specific_and_pending_readable(): void
    {
        $f = $this->fixture();
        DB::transaction(function () use ($f): void {
            $f['user']->update(['status' => 'rejected']);
            $f['rider']->update(['approval_status' => 'rejected']);
        });
        $this->assertDatabaseCount('rider_notifications', 1);
        Sanctum::actingAs($f['user']);
        $this->getJson('/api/rider/notifications')->assertOk()->assertJsonPath('data.0.data.screen', 'account');
        $conversation = SortingCenterConversation::create(['sorting_center_id' => $f['center']->id, 'created_by' => $f['centerUser']->id]);
        SortingCenterMessage::create(['conversation_id' => $conversation->id, 'sender_id' => $f['centerUser']->id,
            'recipient_id' => $f['user']->id, 'body' => 'Private message body']);
        $this->assertDatabaseCount('rider_notifications', 2);
        $message = RiderNotification::where('type', 'message')->firstOrFail();
        $this->assertSame($conversation->id, $message->data['conversation_id']);
        $this->assertStringNotContainsString('Private message body', $message->body);
    }

    public function test_earnings_and_full_manual_payout_generate_distinct_events_from_backend_records(): void
    {
        $f = $this->fixture();
        $order = Order::create(['user_id' => $f['buyer']->id, 'order_number' => Str::uuid(), 'shipping_address' => 'Destination',
            'subtotal' => 100, 'total' => 100, 'payment_method' => 'cod', 'shipping_method' => 'standard', 'status' => 'completed']);
        $shipment = SellerShipment::create(['order_id' => $order->id, 'seller_id' => $f['seller']->id]);
        $charge = SellerDeliveryCharge::create(['seller_shipment_id' => $shipment->id, 'seller_id' => $f['seller']->id,
            'sorting_center_id' => $f['center']->id, 'currency' => 'PHP', 'amount' => 50, 'logistics_amount' => 10, 'rider_amount' => 40,
            'logistics_basis_points' => 2000, 'pricing_rule' => 'test', 'recognized_at' => now()]);
        $earning = RiderDeliveryEarning::create(['seller_delivery_charge_id' => $charge->id, 'rider_id' => $f['rider']->id,
            'amount' => 40, 'earned_at' => now(), 'payout_status' => 'unpaid']);
        $earning->update(['payout_status' => 'paid', 'paid_at' => now()]);
        $earning->save();
        $this->assertDatabaseCount('rider_notifications', 2);
        $this->assertDatabaseHas('rider_notifications', ['title' => 'Payout recorded', 'body' => 'PHP 40.00 marked paid.']);
    }

    public function test_ready_delivery_success_failure_and_pickup_fanout_respect_rider_scope(): void
    {
        $f = $this->fixture();
        $other = $this->fixture();
        PickupRequest::create(['parcel_id' => $f['parcel']->id, 'seller_id' => $f['seller']->id, 'status' => 'confirmed']);
        $this->assertSame(0, RiderNotification::where('user_id', $other['user']->id)->count());
        $f['parcel']->update(['status' => 'AT_SORTING_CENTER', 'assigned_rider_id' => $f['rider']->id]);
        $this->assertDatabaseHas('rider_notifications', ['user_id' => $f['user']->id, 'title' => 'Delivery assigned — ready for collection']);
        $f['parcel']->update(['status' => 'OUT_FOR_DELIVERY']);
        $f['parcel']->update(['status' => 'DELIVERED']);
        $delivered = RiderNotification::where('user_id', $f['user']->id)->where('body', 'Parcel '.$f['parcel']->id.': DELIVERED')->firstOrFail();
        $this->assertSame('history', $delivered->data['screen']);
        $other['parcel']->update(['assigned_rider_id' => $other['rider']->id, 'status' => 'OUT_FOR_DELIVERY']);
        $other['parcel']->update(['status' => 'FAILED_RETURNED', 'failure_reason' => 'Unavailable']);
        $failed = RiderNotification::where('user_id', $other['user']->id)->where('body', 'Parcel '.$other['parcel']->id.': FAILED RETURNED')->firstOrFail();
        $this->assertSame('history', $failed->data['screen']);
        $this->assertSame(0, RiderNotification::where('user_id', $f['user']->id)->where('body', $failed->body)->count());
    }
}
