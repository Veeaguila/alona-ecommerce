<?php

namespace Tests\Feature\Logistics;

use App\Models\{Category, Order, Parcel, PickupRequest, Product, Rider, SortingCenter, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PickupTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_timeline_uses_stored_event_times_and_leaves_legacy_times_unknown(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-20T01:00:00Z'));
        $center = $this->center(); $rider = $this->rider($center); $pickup = $this->submittedPickup();
        Sanctum::actingAs($center->user);
        $this->postJson('/api/sorting-center/pickup-requests/'.$pickup->id.'/confirm')->assertOk();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-20T01:05:00Z'));
        Sanctum::actingAs($rider->user);
        $this->postJson('/api/rider/pickups/'.$pickup->parcel_id.'/accept')->assertOk();
        $this->postJson('/api/rider/pickups/'.$pickup->parcel_id.'/confirm')->assertOk();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-20T02:00:00Z'));
        Sanctum::actingAs($center->user);
        $this->postJson('/api/sorting-center/parcels/mark-arrived', ['order_id' => $pickup->parcel->order_id])->assertOk();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-20T03:00:00Z'));
        $pickup->parcel->update(['collection_address' => 'Later address correction']);
        $this->getJson('/api/sorting-center/pickup-requests?status=all')->assertOk()
            ->assertJsonPath('data.data.0.timeline.4.label', 'Arrived at Sorting Center')
            ->assertJsonPath('data.data.0.timeline.4.occurred_at', '2026-10-20T02:00:00.000000Z')
            ->assertJsonMissingPath('data.data.0.parcel.order_item');
        $legacy = $this->pickup($center, 'PICKED_UP', $rider);
        $rows = $this->getJson('/api/sorting-center/pickup-requests?status=all')->assertOk()->json('data.data');
        $row = collect($rows)->firstWhere('id', $legacy->id);
        $event = collect($row['timeline'])->firstWhere('label', 'Picked up / In transit to center');
        $this->assertNull($event['occurred_at']);
        $this->travelBack();
    }

    private function center(): SortingCenter
    {
        return SortingCenter::create([
            'user_id' => User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved'])->id,
            'business_name' => 'Tracking test center', 'approval_status' => 'approved', 'is_active' => true,
        ]);
    }

    public function test_express_metadata_and_timeline_coexist_without_exposing_linked_order_details(): void
    {
        $center = $this->center();
        $pickup = $this->submittedPickup();
        $pickup->parcel->orderItem->order->update(['shipping_method' => 'express']);
        Sanctum::actingAs($center->user);
        $this->getJson('/api/sorting-center/pickup-requests?status=all')->assertOk()
            ->assertJsonPath('data.data.0.shipping_method', 'express')
            ->assertJsonPath('data.data.0.is_express', true)
            ->assertJsonPath('data.data.0.parcel.shipping_method', 'express')
            ->assertJsonPath('data.data.0.timeline.0.label', 'Pickup requested')
            ->assertJsonMissingPath('data.data.0.parcel.order_item')
            ->assertJsonMissingPath('data.data.0.parcel.orderItem')
            ->assertJsonMissingPath('data.data.0.parcel.logistics_events');
    }

    private function rider(SortingCenter $center): Rider
    {
        $user = User::factory()->create(['usertype' => 'rider', 'status' => 'approved']);
        return Rider::create([
            'user_id' => $user->id, 'sorting_center_id' => $center->id, 'name' => 'Pickup Rider '.$user->id,
            'contact' => '09170000000', 'vehicle_type' => 'bicycle', 'plate_number' => 'TRACK-'.$user->id,
            'approval_status' => 'approved', 'is_active' => true,
        ]);
    }

    private function pickup(?SortingCenter $center = null, string $status = 'PLACED', ?Rider $rider = null): PickupRequest
    {
        $seller = User::factory()->create(['usertype' => 'seller', 'status' => 'approved']);
        $buyer = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $parcel = Parcel::create([
            'order_id' => 'TRACK-'.$seller->id, 'seller_id' => $seller->id, 'buyer_id' => $buyer->id,
            'sorting_center_id' => $center?->id, 'status' => $status, 'pickup_rider_id' => $rider?->id,
            'collection_address' => 'Synthetic Seller collection address', 'delivery_address' => 'Synthetic recipient address',
            'delivery_province' => '', 'delivery_municipality' => '', 'delivery_barangay' => '',
        ]);
        return PickupRequest::create([
            'parcel_id' => $parcel->id, 'seller_id' => $seller->id, 'sorting_center_id' => $center?->id,
            'status' => $center ? 'confirmed' : 'pending', 'requested_at' => now(),
        ]);
    }

    private function visible(SortingCenter $center, PickupRequest $pickup, string $stage): void
    {
        Sanctum::actingAs($center->user);
        $this->getJson('/api/sorting-center/pickup-requests?status=all')
            ->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', $pickup->id);
        $this->getJson('/api/sorting-center/pickup-requests?status=all&stage='.$stage)
            ->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', $pickup->id);
    }

    private function submittedPickup(): PickupRequest
    {
        $seller = User::factory()->create(['usertype' => 'seller', 'status' => 'approved', 'address' => 'Synthetic collection address']);
        $buyer = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $category = Category::create(['name' => 'Tracking', 'slug' => 'tracking', 'is_active' => true]);
        $product = Product::create([
            'seller_id' => $seller->id, 'category_id' => $category->id, 'name' => 'Tracking item',
            'slug' => 'tracking-item', 'price' => 100, 'stock' => 10, 'status' => 'approved',
        ]);
        $order = Order::create([
            'user_id' => $buyer->id, 'order_number' => 'TRACK-SELLER', 'subtotal' => 100, 'total' => 100,
            'shipping_address' => 'Synthetic recipient address', 'payment_method' => 'cod', 'status' => 'ready_for_pickup',
        ]);
        $item = $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name, 'price' => 100, 'quantity' => 1, 'status' => 'ready_for_pickup',
        ]);
        Sanctum::actingAs($seller);
        $response = $this->postJson('/api/seller/pickup-requests', ['order_item_id' => $item->id])->assertCreated();
        return PickupRequest::findOrFail($response->json('pickup_request.id'));
    }

    public function test_request_remains_visible_through_acceptance_assignment_pickup_transit_and_arrival(): void
    {
        $center = $this->center();
        $rider = $this->rider($center);
        $pickup = $this->submittedPickup();
        $parcel = $pickup->parcel;
        $this->visible($center, $pickup, 'pending');
        $this->postJson('/api/sorting-center/pickup-requests/'.$pickup->id.'/confirm')->assertOk();
        $this->visible($center, $pickup, 'accepted');
        $this->postJson('/api/sorting-center/pickup-requests/'.$pickup->id.'/confirm')->assertUnprocessable();
        $this->postJson('/api/sorting-center/parcels/mark-arrived', ['order_id' => $parcel->order_id])->assertUnprocessable();

        Sanctum::actingAs($rider->user);
        $this->postJson('/api/rider/pickups/'.$parcel->id.'/accept')->assertOk();
        $this->postJson('/api/rider/pickups/'.$parcel->id.'/accept')->assertConflict();
        Sanctum::actingAs($this->rider($center)->user);
        $this->postJson('/api/rider/pickups/'.$parcel->id.'/accept')->assertConflict();
        $this->visible($center, $pickup, 'assigned');
        $this->getJson('/api/sorting-center/pickup-requests?status=all')
            ->assertJsonPath('data.data.0.parcel.pickup_rider.id', $rider->id)
            ->assertJsonPath('data.data.0.parcel.pickup_rider.name', $rider->name)
            ->assertJsonPath('data.data.0.seller.id', $pickup->seller_id)
            ->assertJsonPath('data.data.0.parcel.order_id', $parcel->order_id)
            ->assertJsonStructure(['data' => ['data' => [['requested_at', 'updated_at', 'parcel' => ['updated_at']]]]]);

        Sanctum::actingAs($rider->user);
        $this->postJson('/api/rider/pickups/'.$parcel->id.'/confirm')->assertOk()->assertJsonPath('data.status', 'PICKED_UP');
        $this->postJson('/api/rider/pickups/'.$parcel->id.'/confirm')->assertUnprocessable();
        // PICKED_UP is also the existing in-transit-to-center state: no separate transition is invented.
        $this->visible($center, $pickup, 'PICKED_UP');
        $this->getJson('/api/sorting-center/parcels/lookup/'.$parcel->order_id)->assertOk();
        $this->postJson('/api/sorting-center/parcels/mark-arrived', ['order_id' => $parcel->order_id])
            ->assertOk()->assertJsonPath('data.status', 'AT_SORTING_CENTER');
        $this->visible($center, $pickup, 'arrived');
        $this->postJson('/api/sorting-center/parcels/mark-arrived', ['order_id' => $parcel->order_id])->assertUnprocessable();
        $this->assertDatabaseHas('pickup_requests', ['id' => $pickup->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('parcels', ['id' => $parcel->id, 'pickup_rider_id' => $rider->id, 'assigned_rider_id' => null]);
        $this->assertDatabaseCount('seller_delivery_charges', 0);
        $this->assertDatabaseCount('rider_delivery_earnings', 0);
    }

    public function test_stage_filters_are_disjoint_and_confirmed_filter_remains_compatible(): void
    {
        $center = $this->center();
        $rider = $this->rider($center);
        $records = [
            'pending' => $this->pickup(), 'accepted' => $this->pickup($center),
            'assigned' => $this->pickup($center, 'PREPARING', $rider),
            'PICKED_UP' => $this->pickup($center, 'PICKED_UP', $rider),
            'arrived' => $this->pickup($center, 'AT_SORTING_CENTER', $rider),
        ];
        Sanctum::actingAs($center->user);
        foreach ($records as $stage => $record) {
            $this->getJson('/api/sorting-center/pickup-requests?status=all&stage='.$stage)
                ->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', $record->id);
        }
        $this->getJson('/api/sorting-center/pickup-requests?status=all')->assertOk()->assertJsonPath('data.total', 5);
        $this->getJson('/api/sorting-center/pickup-requests?status=confirmed')->assertOk()->assertJsonPath('data.total', 4);
        $this->getJson('/api/sorting-center/pickup-requests')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/api/sorting-center/pickup-requests?status=all&stage=bogus')->assertUnprocessable();
    }

    public function test_tracking_cannot_expose_or_accept_foreign_center_requests_or_parcels(): void
    {
        $own = $this->center();
        $other = $this->center();
        $foreign = $this->pickup($other, 'PICKED_UP');
        $inconsistent = $this->pickup();
        $inconsistent->parcel->update(['sorting_center_id' => $other->id]);
        Sanctum::actingAs($own->user);
        foreach (['all', 'pending', 'confirmed'] as $status) {
            $this->getJson('/api/sorting-center/pickup-requests?status='.$status)->assertOk()->assertJsonPath('data.total', 0);
        }
        $this->postJson('/api/sorting-center/pickup-requests/'.$foreign->id.'/confirm')->assertNotFound();
        $this->postJson('/api/sorting-center/pickup-requests/'.$inconsistent->id.'/confirm')->assertConflict();
        $this->postJson('/api/sorting-center/parcels/mark-arrived', ['order_id' => $foreign->parcel->order_id])->assertConflict();
    }

    public function test_after_arrival_tracking_is_retained_as_last_mile_progresses(): void
    {
        $center = $this->center();
        $rider = $this->rider($center);
        $pickup = $this->pickup($center, 'AT_SORTING_CENTER', $rider);
        foreach (['AT_SORTING_CENTER', 'OUT_FOR_DELIVERY', 'DELIVERED', 'FAILED_RETURNED'] as $status) {
            $pickup->parcel->update(['status' => $status]);
            $this->visible($center, $pickup, 'arrived');
        }
    }

    public function test_ineligible_roles_cannot_track_or_confirm_requests(): void
    {
        $pickup = $this->pickup();
        foreach (['buyer', 'seller', 'rider', 'admin'] as $role) {
            Sanctum::actingAs(User::factory()->create(['usertype' => $role, 'status' => 'approved']));
            $this->getJson('/api/sorting-center/pickup-requests?status=all')->assertForbidden();
            $this->postJson('/api/sorting-center/pickup-requests/'.$pickup->id.'/confirm')->assertForbidden();
        }
    }
}
