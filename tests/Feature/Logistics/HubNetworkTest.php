<?php

namespace Tests\Feature\Logistics;

use App\Models\{Area, CartItem, DeliveryZone, HubTransfer, LogisticsHub, LogisticsRoute, Order, OrderItem, Parcel, ParcelHubJourney, ParcelReturn, SortingCenter, User};
use App\Services\{HubNetwork, LogisticsParcelSummary};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Inertia\Testing\AssertableInertia as Assert;

class HubNetworkTest extends CompleteWorkflowTest
{
    private const ORIGIN = '45 Kalayaan Ave., Brgy. Central, Quezon City, Metro Manila, NCR 1100';
    private const DESTINATION = '123 Synthetic Street, City of Cebu, Region VII (Central Visayas)';

    private function network(bool $route = true): array
    {
        $this->item->product->seller->update(['address' => self::ORIGIN]);
        $this->area->update(['name' => 'City of Cebu', 'city_code' => '0730600000']);
        $sourceArea = Area::create(['sorting_center_id' => $this->center->id, 'name' => 'Quezon City', 'city_code' => '1381300000']);
        $from = LogisticsHub::create(['sorting_center_id' => $this->center->id, 'code' => 'MNL-01', 'name' => 'Synthetic Manila hub',
            'address' => self::ORIGIN, 'city_code' => '1381300000', 'pickup_city_codes' => ['1381300000'], 'delivery_city_codes' => [], 'is_active' => true]);
        $to = LogisticsHub::create(['sorting_center_id' => $this->center->id, 'code' => 'CEB-01', 'name' => 'Synthetic Cebu hub',
            'address' => self::DESTINATION, 'city_code' => '0730600000', 'pickup_city_codes' => [], 'delivery_city_codes' => ['0730600000'], 'is_active' => true]);
        $this->pickupRider->forceFill(['logistics_hub_id' => $from->id])->save();
        $this->pickupRider->areas()->sync([$sourceArea->id]);
        $this->deliveryRider->forceFill(['logistics_hub_id' => $to->id])->save();
        $this->deliveryRider->areas()->sync([$this->area->id]);
        if ($route) LogisticsRoute::create(['sorting_center_id' => $this->center->id, 'origin_hub_id' => $from->id,
            'destination_hub_id' => $to->id, 'transport_mode' => 'mixed', 'is_active' => true]);
        config(['logistics_hubs.enforce_checkout_routes' => true]);
        return [$from, $to];
    }

    private function cart(): CartItem
    {
        return CartItem::create(['user_id' => $this->item->order->user_id, 'product_id' => $this->item->product_id, 'quantity' => 1]);
    }

    private function checkout(CartItem $cart, string $destination = self::DESTINATION)
    {
        return $this->actingAs($this->item->order->user, 'web')->post(route('buyer.orders.store'),
            ['shipping_address' => $destination, 'shipping_method' => 'standard', 'payment_method' => 'cod', 'selected_items' => [$cart->id]]);
    }

    private function bookedParcel(): Parcel
    {
        $this->checkout($this->cart())->assertSessionHasNoErrors();
        $order = Order::latest('id')->firstOrFail();
        $item = $order->items()->firstOrFail();
        $this->assertNotNull($item->hub_route_plan);
        $this->assertSame($this->center->business_name, $item->courier_name);
        $this->actingAs($item->product->seller, 'web')->patch(route('seller.orders.process', $item))->assertRedirect();
        $this->actingAs($item->product->seller, 'web')->patch(route('seller.orders.pack', $item))->assertRedirect();
        $this->actingAs($item->product->seller, 'web')->patch(route('seller.orders.ready-for-pickup', $item))->assertRedirect();
        return $item->fresh()->parcel()->firstOrFail();
    }

    private function pickedUp(): Parcel
    {
        $parcel = $this->bookedParcel();
        Sanctum::actingAs($this->center->user);
        $this->postJson('/api/sorting-center/pickup-requests/'.$parcel->pickupRequests()->firstOrFail()->id.'/confirm')->assertOk();
        Sanctum::actingAs($this->deliveryRider->user);
        $this->getJson('/api/rider/pickups/available')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/rider/pickups/'.$parcel->id.'/accept')->assertUnprocessable();
        Sanctum::actingAs($this->pickupRider->user);
        $this->postJson('/api/rider/pickups/'.$parcel->id.'/accept')->assertOk();
        $this->postJson('/api/rider/pickups/'.$parcel->id.'/confirm')->assertOk();
        return $parcel->fresh();
    }

    private function rate(int $companyId, int $base, int $perKg = 0): DeliveryZone
    {
        return DeliveryZone::create(['sorting_center_id' => $companyId, 'name' => 'City of Cebu', 'province' => 'Cebu',
            'base_shipping_rate' => $base, 'per_kg_rate' => $perKg, 'estimated_days' => '2-3 days', 'is_active' => true]);
    }

    public function test_checkout_quote_and_order_use_the_booked_company_rate_and_reject_a_foreign_quote(): void
    {
        $this->network();
        $foreign = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        $other = SortingCenter::create(['user_id' => $foreign->id, 'business_name' => 'Other rate company', 'approval_status' => 'approved']);
        $this->rate($other->id, 15);
        $own = $this->rate($this->center->id, 120);
        $cart = $this->cart();
        $address = $this->item->order->user->addresses()->create(['label' => 'Synthetic destination', 'address' => self::DESTINATION, 'is_default' => true]);
        $this->actingAs($this->item->order->user, 'web')->get(route('buyer.checkout', ['selected_items' => [$cart->id]]))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('shipping_rate_cards.'.$address->id.'.standard.unweighted.base', 120)
                ->where('shipping_rate_cards.'.$address->id.'.standard.sellers.'.$this->item->product->seller_id.'.unweighted.zone_id', $own->id));
        $input = ['shipping_address' => self::DESTINATION, 'shipping_method' => 'standard', 'payment_method' => 'cod', 'selected_items' => [$cart->id]];
        $before = Order::count(); $stock = $this->item->product->stock;
        $this->post(route('buyer.orders.store'), $input + ['expected_shipping_fee' => 15])->assertSessionHasErrors('shipping_fee');
        $this->assertSame($before, Order::count());
        $this->assertSame($stock, $this->item->product->fresh()->stock);
        $this->assertNotNull($cart->fresh());
        $this->post(route('buyer.orders.store'), $input + ['expected_shipping_fee' => 120])->assertSessionHasNoErrors();
        $order = Order::latest('id')->firstOrFail();
        $this->assertSame('120.00', $order->shipping_fee);
        $this->assertSame($this->center->id, $order->items()->sole()->hub_route_plan['sorting_center_id']);
    }

    public function test_booked_company_uses_only_its_own_rate_or_global_and_regional_fallbacks(): void
    {
        $this->network();
        $foreign = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        $other = SortingCenter::create(['user_id' => $foreign->id, 'business_name' => 'Unrelated fallback company', 'approval_status' => 'approved']);
        $this->rate($other->id, 15);
        $rates = app(\App\Services\ShippingRates::class);
        $this->assertSame(120.0, $rates->card('standard', self::DESTINATION, false, $this->center->id)['base']);
        $global = $this->rate($other->id, 80); $global->update(['sorting_center_id' => null]);
        $this->assertSame($global->id, $rates->zone(self::DESTINATION, $this->center->id)->id);
        $own = $this->rate($this->center->id, 140);
        $this->assertSame($own->id, $rates->zone(self::DESTINATION, $this->center->id)->id);
        $own->update(['is_active' => false]);
        $this->assertSame($global->id, $rates->zone(self::DESTINATION, $this->center->id)->id);
        $cart = $this->cart();
        $this->actingAs($this->item->order->user, 'web')->post(route('buyer.orders.store'), ['shipping_address' => self::DESTINATION,
            'shipping_method' => 'standard', 'payment_method' => 'cod', 'selected_items' => [$cart->id], 'expected_shipping_fee' => 80])->assertSessionHasNoErrors();
        $this->assertSame('80.00', Order::latest('id')->firstOrFail()->shipping_fee);
    }

    public function test_two_sellers_use_different_booked_company_tariffs_in_quote_and_split_orders(): void
    {
        $this->network();
        $this->item->product->update(['weight' => 2.2]);
        $staff = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        $other = SortingCenter::create(['user_id' => $staff->id, 'business_name' => 'Synthetic Cebu local company', 'approval_status' => 'approved']);
        $local = LogisticsHub::create(['sorting_center_id' => $other->id, 'code' => 'LOCAL', 'name' => 'Synthetic Cebu local hub',
            'address' => self::DESTINATION, 'city_code' => '0730600000', 'pickup_city_codes' => ['0730600000'], 'delivery_city_codes' => ['0730600000'], 'is_active' => true]);
        $area = Area::create(['sorting_center_id' => $other->id, 'name' => 'City of Cebu', 'city_code' => '0730600000']);
        $riderUser = User::factory()->create(['usertype' => 'rider', 'status' => 'approved']);
        $rider = $this->deliveryRider->replicate();
        $rider->forceFill(['user_id' => $riderUser->id, 'sorting_center_id' => $other->id, 'logistics_hub_id' => $local->id])->save();
        $rider->areas()->sync([$area->id]);
        $seller = User::factory()->create(['usertype' => 'seller', 'status' => 'approved', 'address' => self::DESTINATION]);
        $product = $this->item->product->replicate();
        $product->fill(['seller_id' => $seller->id, 'slug' => 'other-rate-product', 'weight' => 1.2])->save();
        $this->rate($this->center->id, 120, 20);
        $this->rate($other->id, 75, 10);
        $first = $this->cart();
        $second = CartItem::create(['user_id' => $this->item->order->user_id, 'product_id' => $product->id, 'quantity' => 1]);
        $address = $this->item->order->user->addresses()->create(['label' => 'Synthetic destination', 'address' => self::DESTINATION, 'is_default' => true]);
        $ids = [$first->id, $second->id];
        $this->actingAs($this->item->order->user, 'web')->get(route('buyer.checkout', ['selected_items' => $ids]))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('shipping_rate_cards.'.$address->id.'.standard.sellers.'.$this->item->product->seller_id.'.weighted.base', 120)
                ->where('shipping_rate_cards.'.$address->id.'.standard.sellers.'.$seller->id.'.weighted.base', 75));
        $this->post(route('buyer.orders.store'), ['shipping_address' => self::DESTINATION, 'shipping_method' => 'standard',
            'payment_method' => 'cod', 'selected_items' => $ids, 'expected_shipping_fee' => 245])->assertSessionHasNoErrors();
        $orders = Order::whereKeyNot($this->item->order_id)->with('items')->orderBy('id')->get();
        $this->assertCount(2, $orders);
        $byCompany = $orders->keyBy(fn ($order) => $order->items->sole()->hub_route_plan['sorting_center_id']);
        $this->assertSame('160.00', $byCompany[$this->center->id]->shipping_fee);
        $this->assertSame('85.00', $byCompany[$other->id]->shipping_fee);
        $this->assertSame(245.0, (float) $orders->sum('shipping_fee'));
    }

    public function test_empty_network_does_not_turn_legacy_company_coverage_into_physical_hubs(): void
    {
        Sanctum::actingAs($this->center->user);
        $this->getJson('/api/sorting-center/hub-network')->assertOk()->assertJsonCount(0, 'data.hubs')->assertJsonCount(0, 'data.routes');
        $this->assertSame(0, LogisticsHub::count());
        $this->assertSame(0, ParcelHubJourney::count());
        $this->assertSame(1, Area::count());
    }

    public function test_single_operator_manages_both_hub_work_queues_and_records_origin_and_destination_receipt(): void
    {
        [$from, $to] = $this->network(); $parcel = $this->pickedUp();
        Sanctum::actingAs($this->center->user);
        $base = '/api/sorting-center/hub-journeys?hub_id=';
        $this->getJson('/api/sorting-center/hub-network')->assertOk()->assertJsonPath('data.company.id', $this->center->id)
            ->assertJsonPath('data.hubs.0.operation_counts.pickup_inbound', 0);
        $fromData = collect($this->getJson('/api/sorting-center/hub-network')->json('data.hubs'))->firstWhere('id', $from->id);
        $this->assertSame(1, $fromData['operation_counts']['pickup_inbound']);
        $this->getJson($base.$from->id.'&stage=pickup_inbound')->assertOk()->assertJsonPath('data.data.0.parcel_id', $parcel->id);
        $this->getJson($base.$to->id.'&stage=pickup_inbound')->assertOk()->assertJsonCount(0, 'data.data');
        $this->postJson('/api/sorting-center/parcels/mark-arrived', ['order_id' => $parcel->order_id, 'hub_id' => $from->id])->assertOk();
        $this->getJson($base.$from->id.'&stage=dispatch_ready')->assertOk()->assertJsonCount(1, 'data.data');
        $this->getJson($base.$to->id.'&stage=transfer_inbound')->assertOk()->assertJsonCount(0, 'data.data');
        $this->postJson('/api/sorting-center/hub-journeys/'.$parcel->id.'/dispatch', ['hub_id' => $from->id,
            'request_key' => (string) Str::uuid(), 'transport_reference' => 'SINGLE-OPERATOR-001', 'physical_confirmation' => true])->assertOk();
        $this->getJson($base.$from->id.'&stage=at_hub')->assertOk()->assertJsonCount(0, 'data.data');
        $this->getJson($base.$to->id.'&stage=transfer_inbound')->assertOk()->assertJsonPath('data.data.0.parcel_id', $parcel->id);
        $this->postJson('/api/sorting-center/hub-journeys/'.$parcel->id.'/receive', ['hub_id' => $to->id, 'physical_confirmation' => true])->assertOk();
        $this->getJson($base.$to->id.'&stage=at_hub')->assertOk()->assertJsonPath('data.data.0.parcel_id', $parcel->id);
        $this->getJson($base.$to->id.'&stage=transfer_inbound')->assertOk()->assertJsonCount(0, 'data.data');
        $events = \App\Models\LogisticsAuditEvent::where('parcel_id', $parcel->id)
            ->whereIn('action', ['hub_origin_received', 'hub_transfer_dispatched', 'hub_transfer_received'])->get();
        $this->assertCount(3, $events);
        $this->assertTrue($events->every(fn ($e) => (int) $e->actor_id === (int) $this->center->user_id));
        $this->assertSame(1, HubTransfer::count());
    }

    public function test_hub_management_is_accessible_before_checkout_activation_and_foreign_hub_queue_is_private(): void
    {
        [$from] = $this->network(); config(['logistics_hubs.enforce_checkout_routes' => false]);
        Sanctum::actingAs($this->center->user);
        $this->getJson('/api/sorting-center/hub-network')->assertOk()->assertJsonPath('data.booking_routes_required', false)->assertJsonCount(2, 'data.hubs');
        $this->getJson('/api/sorting-center/hub-journeys?stage=pickup_inbound')->assertUnprocessable()->assertJsonValidationErrors('hub_id');
        $other = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        SortingCenter::create(['user_id' => $other->id, 'business_name' => 'Other hub operator', 'approval_status' => 'approved']);
        Sanctum::actingAs($other);
        $this->getJson('/api/sorting-center/hub-journeys?hub_id='.$from->id)->assertNotFound();
        $this->getJson('/api/sorting-center/hub-journeys')->assertOk()->assertJsonCount(0, 'data.data');
        $this->assertSame(2, LogisticsHub::count());
    }

    public function test_partially_missing_hub_schema_returns_setup_error_before_queries_can_fail(): void
    {
        \Illuminate\Support\Facades\Schema::drop('logistics_routes');
        Sanctum::actingAs($this->center->user);
        $this->getJson('/api/sorting-center/hub-network')->assertStatus(503)->assertJsonPath('message', 'Hub operations are not available yet. Ask the ALONA administrator to complete hub setup.');
    }

    public function test_company_can_register_real_hubs_validate_cities_and_keep_other_companies_private(): void
    {
        Sanctum::actingAs($this->center->user);
        $input = ['code' => 'MNL-01', 'name' => 'Synthetic hub', 'address' => self::ORIGIN, 'city_code' => '1381300000',
            'pickup_city_codes' => ['1381300000'], 'delivery_city_codes' => [], 'is_active' => true, 'sorting_center_id' => 999];
        $hub = $this->postJson('/api/sorting-center/hubs', $input)->assertCreated()->json('data');
        $this->assertSame($this->center->id, $hub['sorting_center_id']);
        $this->postJson('/api/sorting-center/hubs', $input)->assertUnprocessable();
        $this->postJson('/api/sorting-center/hubs', array_replace($input, ['code' => 'BAD-CITY', 'city_code' => '0000000000']))->assertUnprocessable();
        $other = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        SortingCenter::create(['user_id' => $other->id, 'business_name' => 'Synthetic foreign company', 'approval_status' => 'approved']);
        Sanctum::actingAs($other);
        $this->getJson('/api/sorting-center/hub-network')->assertOk()->assertJsonCount(0, 'data.hubs');
        $this->putJson('/api/sorting-center/hubs/'.$hub['id'], $input)->assertNotFound();
        $this->assertSame(1, LogisticsHub::count());
        Sanctum::actingAs($this->pickupRider->user);
        $this->postJson('/api/sorting-center/hubs', $input)->assertForbidden();
    }

    public function test_directed_routes_require_two_owned_hubs_and_do_not_imply_reverse_service(): void
    {
        [$from, $to] = $this->network(false);
        Sanctum::actingAs($this->center->user);
        $input = ['origin_hub_id' => $from->id, 'destination_hub_id' => $to->id, 'transport_mode' => 'mixed', 'is_active' => true];
        $this->postJson('/api/sorting-center/hub-routes', array_replace($input, ['destination_hub_id' => $from->id]))->assertUnprocessable();
        $this->postJson('/api/sorting-center/hub-routes', $input)->assertCreated();
        $this->postJson('/api/sorting-center/hub-routes', $input)->assertUnprocessable();
        $this->assertNotNull(app(HubNetwork::class)->plan(self::ORIGIN, self::DESTINATION));
        $this->assertNull(app(HubNetwork::class)->plan(self::DESTINATION, self::ORIGIN));
    }

    public function test_destination_coverage_without_a_connected_route_blocks_checkout_without_side_effects(): void
    {
        $this->network(false);
        $cart = $this->cart(); $stock = $this->item->product->stock; $orders = Order::count();
        $this->checkout($cart)->assertSessionHasErrors('shipping_address');
        $this->assertSame($orders, Order::count()); $this->assertSame($stock, $this->item->product->fresh()->stock);
        $this->assertNotNull($cart->fresh()); $this->assertSame(0, ParcelHubJourney::count());
        $buyer = $this->item->order->user;
        $address = $buyer->addresses()->create(['label' => 'Synthetic destination', 'address' => self::DESTINATION, 'is_default' => true]);
        $this->actingAs($buyer, 'web')->get(route('buyer.checkout', ['selected_items' => [$cart->id]]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('hub_routes_required', true)->where('address_serviceability.'.$address->id.'.state', 'unsupported'));
    }

    public function test_a_route_without_an_eligible_local_rider_at_both_ends_is_not_bookable(): void
    {
        [$from, $to] = $this->network();
        $this->deliveryRider->forceFill(['logistics_hub_id' => null])->save();
        $this->checkout($this->cart())->assertSessionHasErrors('shipping_address');
        $this->deliveryRider->forceFill(['logistics_hub_id' => $to->id])->save();
        $this->assertNotNull(app(HubNetwork::class)->plan(self::ORIGIN, self::DESTINATION));
        $this->deliveryRider->update(['is_active' => false]);
        $this->assertNull(app(HubNetwork::class)->plan(self::ORIGIN, self::DESTINATION));
    }

    public function test_a_complete_booking_binds_the_company_and_requires_real_hub_turnovers_before_delivery(): void
    {
        [$from, $to] = $this->network(); $parcel = $this->pickedUp();
        $journey = ParcelHubJourney::where('parcel_id', $parcel->id)->sole();
        $this->assertSame($this->center->id, $parcel->sorting_center_id);
        $this->assertSame($this->center->id, $parcel->pickupRequests()->sole()->sorting_center_id);
        Sanctum::actingAs($this->center->user);
        $arrival = ['order_id' => $parcel->order_id];
        $this->postJson('/api/sorting-center/parcels/mark-arrived', $arrival)->assertUnprocessable();
        $this->postJson('/api/sorting-center/parcels/mark-arrived', $arrival + ['hub_id' => $to->id])->assertUnprocessable();
        $this->postJson('/api/sorting-center/parcels/mark-arrived', $arrival + ['hub_id' => $from->id])->assertOk();
        $this->postJson('/api/sorting-center/parcels/'.$parcel->id.'/sort', ['area_id' => $this->area->id])->assertOk();
        $this->postJson('/api/sorting-center/parcels/'.$parcel->id.'/assign', ['rider_id' => $this->deliveryRider->id])->assertUnprocessable();
        $this->getJson('/api/sorting-center/parcels/'.$parcel->id.'/eligible-riders')->assertOk()->assertJsonCount(0, 'data');
        $dispatch = '/api/sorting-center/hub-journeys/'.$parcel->id.'/dispatch';
        $receive = '/api/sorting-center/hub-journeys/'.$parcel->id.'/receive';
        $this->postJson($receive, ['hub_id' => $to->id, 'physical_confirmation' => true])->assertUnprocessable();
        $input = ['hub_id' => $from->id, 'request_key' => (string) Str::uuid(), 'transport_reference' => 'SYNTHETIC-CARGO-001', 'physical_confirmation' => true];
        $this->postJson($dispatch, array_replace($input, ['physical_confirmation' => false]))->assertUnprocessable();
        $this->postJson($dispatch, $input)->assertOk();
        $this->assertSame('IN_HUB_TRANSIT', $parcel->fresh()->status);
        $this->assertNull($journey->fresh()->current_hub_id);
        $this->assertSame(1, HubTransfer::count());
        $this->postJson($dispatch, array_replace($input, ['request_key' => (string) Str::uuid()]))->assertConflict();
        $this->getJson('/api/sorting-center/parcels/monitoring?status=IN_HUB_TRANSIT')->assertOk()->assertJsonPath('data.data.0.id', $parcel->id);
        $this->assertSame(1, app(LogisticsParcelSummary::class)->totals(Parcel::where('sorting_center_id', $this->center->id))['in_transit']);
        $this->postJson($receive, ['hub_id' => $from->id, 'physical_confirmation' => true])->assertUnprocessable();
        $this->postJson($receive, ['hub_id' => $to->id, 'physical_confirmation' => true])->assertOk();
        $this->postJson($receive, ['hub_id' => $to->id, 'physical_confirmation' => true])->assertConflict();
        $this->assertSame($to->id, $journey->fresh()->current_hub_id);
        $this->assertSame('AT_SORTING_CENTER', $parcel->fresh()->status);
        $this->postJson('/api/sorting-center/parcels/'.$parcel->id.'/assign', ['rider_id' => $this->deliveryRider->id])->assertOk();
        Sanctum::actingAs($this->deliveryRider->user);
        $this->postJson('/api/rider/deliveries/'.$parcel->id.'/start')->assertOk();
        $this->postJson('/api/rider/deliveries/'.$parcel->id.'/upload-proof', ['file' => UploadedFile::fake()->image('synthetic-proof.jpg'), 'type' => 'PHOTO'])->assertCreated();
        $this->postJson('/api/rider/deliveries/'.$parcel->id.'/deliver', ['otp' => $this->deliveryOtp($parcel->id)])->assertOk();
        $this->assertSame('DELIVERED', $parcel->fresh()->status);
        $this->assertDatabaseCount('seller_delivery_charges', 0); $this->assertDatabaseCount('rider_delivery_earnings', 0);
        $notes = $parcel->orderItem->statusHistories()->pluck('note');
        $this->assertTrue($notes->contains('Dispatched from Synthetic Manila hub to Synthetic Cebu hub.'));
        $this->assertTrue($notes->contains('Received at destination hub: Synthetic Cebu hub.'));
        $this->actingAs($parcel->orderItem->order->user, 'web')->get(route('buyer.orders.show', $parcel->orderItem->order))->assertOk();
    }

    public function test_foreign_companies_cannot_dispatch_receive_assign_hubs_or_read_journeys(): void
    {
        [$from, $to] = $this->network(); $parcel = $this->bookedParcel();
        $other = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        $center = SortingCenter::create(['user_id' => $other->id, 'business_name' => 'Synthetic other company', 'approval_status' => 'approved']);
        Sanctum::actingAs($other);
        $this->getJson('/api/sorting-center/hub-journeys')->assertOk()->assertJsonCount(0, 'data.data');
        $this->putJson('/api/sorting-center/riders/'.$this->pickupRider->id.'/hub', ['hub_id' => $from->id])->assertNotFound();
        $this->postJson('/api/sorting-center/hub-journeys/'.$parcel->id.'/dispatch', ['hub_id' => $from->id, 'request_key' => (string) Str::uuid(),
            'transport_reference' => 'FOREIGN-CARGO', 'physical_confirmation' => true])->assertNotFound();
        $this->postJson('/api/sorting-center/hub-journeys/'.$parcel->id.'/receive', ['hub_id' => $to->id, 'physical_confirmation' => true])->assertNotFound();
        $this->postJson('/api/sorting-center/hub-routes', ['origin_hub_id' => $from->id, 'destination_hub_id' => $to->id, 'transport_mode' => 'road', 'is_active' => true])->assertUnprocessable();
        $this->assertSame('PLACED', $parcel->fresh()->status); $this->assertSame(0, HubTransfer::count());
    }

    public function test_existing_jobs_prevent_silent_rider_relocation_and_hub_address_changes(): void
    {
        [$from, $to] = $this->network(); $parcel = $this->pickedUp();
        Sanctum::actingAs($this->center->user);
        $this->putJson('/api/sorting-center/riders/'.$this->pickupRider->id.'/hub', ['hub_id' => $to->id])->assertUnprocessable();
        $this->putJson('/api/sorting-center/hubs/'.$from->id, array_replace($from->only(['code', 'name', 'address', 'city_code', 'pickup_city_codes', 'delivery_city_codes', 'is_active']),
            ['address' => 'A different physical location']))->assertUnprocessable();
        $this->assertSame($from->id, $this->pickupRider->fresh()->logistics_hub_id);
        $this->assertSame(self::ORIGIN, $from->fresh()->address);
    }

    public function test_unknown_sender_address_and_cross_company_coverage_never_invent_a_route(): void
    {
        [$from, $to] = $this->network();
        $this->assertNull(app(HubNetwork::class)->plan('Unresolved legacy location', self::DESTINATION));
        LogisticsRoute::query()->delete();
        $other = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        $center = SortingCenter::create(['user_id' => $other->id, 'business_name' => 'Synthetic other company', 'approval_status' => 'approved']);
        $to->update(['sorting_center_id' => $center->id]);
        $this->assertNull(app(HubNetwork::class)->plan(self::ORIGIN, self::DESTINATION));
    }

    public function test_booked_routes_are_preserved_when_configuration_changes_and_cannot_be_rewritten(): void
    {
        [$from, $to] = $this->network();
        $this->checkout($this->cart())->assertSessionHasNoErrors();
        $item = OrderItem::latest('id')->firstOrFail(); $snapshot = $item->hub_route_plan;
        $from->update(['name' => 'Renamed hub', 'is_active' => false]);
        $this->assertSame($snapshot, $item->fresh()->hub_route_plan);
        $this->expectException(\LogicException::class);
        $item->update(['hub_route_plan' => array_replace($snapshot, ['origin_hub_id' => $to->id])]);
    }

    public function test_same_hub_delivery_does_not_require_a_cargo_route_or_invent_a_transfer(): void
    {
        [$from] = $this->network(false);
        $from->update(['delivery_city_codes' => ['1381300000']]);
        $plan = app(HubNetwork::class)->plan(self::ORIGIN, self::ORIGIN);
        $this->assertSame($from->id, $plan['origin_hub_id']);
        $this->assertSame($from->id, $plan['destination_hub_id']);
        $this->assertSame('local', $plan['transport_mode']);
        $this->checkout($this->cart(), self::ORIGIN)->assertSessionHasNoErrors();
        $item = OrderItem::latest('id')->firstOrFail();
        foreach (['process', 'pack', 'ready-for-pickup'] as $action) {
            $this->actingAs($item->product->seller, 'web')->patch(route('seller.orders.'.$action, $item))->assertRedirect();
        }
        $parcel = $item->parcel()->sole();
        Sanctum::actingAs($this->center->user);
        $this->postJson('/api/sorting-center/pickup-requests/'.$parcel->pickupRequests()->sole()->id.'/confirm')->assertOk();
        Sanctum::actingAs($this->pickupRider->user);
        $this->postJson('/api/rider/pickups/'.$parcel->id.'/accept')->assertOk();
        $this->postJson('/api/rider/pickups/'.$parcel->id.'/confirm')->assertOk();
        Sanctum::actingAs($this->center->user);
        $this->postJson('/api/sorting-center/parcels/mark-arrived', ['order_id' => $parcel->order_id, 'hub_id' => $from->id])->assertOk();
        $journey = $parcel->hubJourney()->sole();
        $this->assertNotNull($journey->destination_received_at);
        $this->assertSame($from->id, $journey->current_hub_id);
        $this->assertSame(0, HubTransfer::count());
    }

    public function test_inter_hub_returns_cannot_be_misrepresented_as_a_local_rider_handover(): void
    {
        $this->network(); $parcel = $this->bookedParcel();
        // Synthetic precondition: a failed last-mile parcel awaiting a reverse transport decision.
        $parcel->orderItem->moveTo('shipped', 'Synthetic precondition: the parcel was physically collected.');
        $parcel->update(['status' => 'OUT_FOR_DELIVERY', 'assigned_rider_id' => $this->deliveryRider->id]);
        $return = ParcelReturn::create(['parcel_id' => $parcel->id, 'sorting_center_id' => $this->center->id, 'status' => 'eligible',
            'reason' => 'Synthetic precondition: three last-mile delivery attempts failed.']);
        Sanctum::actingAs($this->center->user);
        $this->postJson('/api/sorting-center/returns/'.$return->id.'/initiate', ['rider_id' => $this->deliveryRider->id,
            'reason' => 'A reverse transport plan is required for this synthetic return.'])->assertUnprocessable()
            ->assertJsonPath('message', 'This return needs a reverse hub transport plan. Contact company operations; a local delivery Rider cannot confirm an inter-hub Seller return.');
        $this->assertSame('eligible', $return->fresh()->status);
        $this->assertSame(0, HubTransfer::count());
    }

    public function test_pickup_rescheduling_preserves_the_booked_courier_and_rejects_a_different_origin_city(): void
    {
        $this->network(); $parcel = $this->bookedParcel(); $item = $parcel->orderItem;
        $input = ['scheduled_date' => now()->addDays(2)->toDateString(), 'time_slot' => 'afternoon', 'carrier_partner' => 'Different courier label'];
        $this->actingAs($item->product->seller, 'web')->post(route('seller.orders.schedule-pickup', $item), $input)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($this->center->business_name, $item->fresh()->courier_name);
        $this->assertSame($this->center->business_name, $parcel->pickupRequests()->sole()->carrier_partner);
        $this->actingAs($item->product->seller, 'web')->post(route('seller.orders.schedule-pickup', $item), $input + ['collection_address' => self::DESTINATION])->assertUnprocessable();
        $this->assertSame(self::ORIGIN, $parcel->fresh()->collection_address);
        $this->assertSame($this->center->id, $parcel->fresh()->sorting_center_id);
        $this->assertSame(1, ParcelHubJourney::count());
    }
}
