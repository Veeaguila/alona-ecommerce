<?php

namespace Tests\Feature\Logistics;

use App\Models\{OrderItem, Parcel, SellerShipment};
use App\Services\SellerShipmentFinance;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

class RiderWorkloadTest extends CompleteWorkflowTest
{
    public function test_same_parcel_completes_seller_pickup_two_rider_handoff_and_buyer_delivery(): void
    {
        parent::test_same_parcel_completes_seller_pickup_two_rider_handoff_and_buyer_delivery();
        Sanctum::actingAs($this->center->user);
        $rows = collect($this->getJson('/api/sorting-center/riders')->assertOk()->json('data'));
        $this->assertSame(0, $rows->firstWhere('id', $this->pickupRider->id)['active_jobs_count']);
        $delivery = $rows->firstWhere('id', $this->deliveryRider->id);
        $this->assertSame(0, $delivery['active_jobs_count']);
        $this->assertSame(1, $delivery['completed_jobs_today']);
        $this->assertSame('Unknown', $delivery['presence']);
    }

    private function parcel(OrderItem $item, string $status): Parcel
    {
        return Parcel::create(['order_id' => 'ITEM-'.$item->id, 'order_item_id' => $item->id,
            'seller_id' => $item->product->seller_id, 'buyer_id' => $item->order->user_id,
            'sorting_center_id' => $this->center->id, 'status' => $status, 'pickup_rider_id' => $this->pickupRider->id,
            'delivery_province' => '', 'delivery_municipality' => 'Cebu City', 'delivery_barangay' => '']);
    }

    private function stats(int $riderId): array
    {
        Sanctum::actingAs($this->center->user);
        return collect($this->getJson('/api/sorting-center/riders')->assertOk()->json('data'))->firstWhere('id', $riderId);
    }

    public function test_multi_item_jobs_are_grouped_per_leg_and_completed_only_when_the_whole_shipment_completes(): void
    {
        $this->item->order->update(['delivery_fee_payer' => 'seller']);
        $second = $this->item->order->items()->create(['product_id' => $this->item->product_id, 'product_name' => 'Second member', 'price' => 100, 'quantity' => 1, 'status' => 'packed']);
        DB::transaction(fn () => app(SellerShipmentFinance::class)->createMembership($this->item->order->fresh()));
        $one = $this->parcel($this->item->fresh(), 'PLACED');
        $two = $this->parcel($second->fresh(), 'PICKED_UP');
        $stats = $this->stats($this->pickupRider->id);
        $this->assertSame(1, $stats['pickup_jobs_count']);
        $this->assertSame(2, $stats['active_parcels_count']);
        $this->assertSame(0, $stats['completed_jobs_today']);
        // Isolated read-model fixtures; no user parcels or finance transitions are advanced.
        DB::table('parcels')->whereIn('id', [$one->id, $two->id])->update(['status' => 'AT_SORTING_CENTER', 'assigned_rider_id' => $this->deliveryRider->id]);
        $shipment = SellerShipment::where('order_id', $this->item->order_id)->firstOrFail();
        $shipment->update(['assigned_rider_id' => $this->deliveryRider->id, 'sorting_center_id' => $this->center->id]);
        $this->assertSame(0, $this->stats($this->pickupRider->id)['active_jobs_count']);
        $this->assertSame(1, $this->stats($this->deliveryRider->id)['delivery_jobs_count']);
        DB::table('parcels')->where('id', $one->id)->update(['status' => 'DELIVERED', 'delivered_at' => now()]);
        $this->assertSame(0, $this->stats($this->deliveryRider->id)['completed_jobs_today']);
        DB::table('parcels')->where('id', $two->id)->update(['status' => 'DELIVERED', 'delivered_at' => now()]);
        $shipment->update(['completed_at' => now()]);
        $stats = $this->stats($this->deliveryRider->id);
        $this->assertSame(1, $stats['completed_jobs_today']);
        $this->assertSame(0, $stats['active_jobs_count']);
        $this->assertDatabaseCount('seller_delivery_charges', 0);
    }

    public function test_token_usage_is_not_presence_no_cap_is_invented_and_suspended_jobs_stay_counted(): void
    {
        $token = $this->pickupRider->user->createToken('recent-use')->accessToken;
        $token->forceFill(['last_used_at' => now()])->save();
        for ($i = 0; $i < 4; $i++) {
            Parcel::create(['order_id' => 'LEGACY-'.$i, 'seller_id' => $this->item->product->seller_id, 'buyer_id' => $this->item->order->user_id,
                'sorting_center_id' => $this->center->id, 'status' => 'PLACED', 'pickup_rider_id' => $this->pickupRider->id,
                'delivery_province' => '', 'delivery_municipality' => '', 'delivery_barangay' => '']);
        }
        $before = Parcel::orderBy('id')->get()->toArray();
        $stats = $this->stats($this->pickupRider->id);
        $this->assertSame(4, $stats['active_jobs_count']);
        $this->assertSame('Unknown', $stats['presence']);
        $this->assertSame('Eligible for assignments', $stats['availability']);
        $this->pickupRider->update(['is_active' => false]);
        $stats = $this->stats($this->pickupRider->id);
        $this->assertSame('Suspended', $stats['availability']);
        $this->assertSame(4, $stats['active_jobs_count']);
        $this->assertSame($before, Parcel::orderBy('id')->get()->toArray());
        $this->assertArrayNotHasKey('last_seen_at', $stats);
    }

    public function test_completed_today_uses_actual_delivered_times_at_manila_day_boundaries(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-20T16:30:00Z'));
        foreach (['2026-10-20T15:59:59Z', '2026-10-20T16:00:00Z'] as $index => $at) {
            Parcel::create(['order_id' => 'DAY-'.$index, 'seller_id' => $this->item->product->seller_id, 'buyer_id' => $this->item->order->user_id,
                'sorting_center_id' => $this->center->id, 'status' => 'DELIVERED', 'assigned_rider_id' => $this->deliveryRider->id,
                'delivered_at' => \Carbon\CarbonImmutable::parse($at), 'delivery_province' => '', 'delivery_municipality' => '', 'delivery_barangay' => '']);
        }
        $this->assertSame(1, $this->stats($this->deliveryRider->id)['completed_jobs_today']);
        $this->travelBack();
    }
}
