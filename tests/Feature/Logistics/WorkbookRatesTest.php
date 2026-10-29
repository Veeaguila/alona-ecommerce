<?php

namespace Tests\Feature\Logistics;

use App\Models\{CartItem, DeliveryZone, LogisticsAuditEvent, Order, SortingCenter, User};
use App\Services\CheckoutServiceability;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

class WorkbookRatesTest extends CompleteWorkflowTest
{
    private function input(array $overrides = []): array
    {
        return array_replace(['name' => 'Metro Manila delivery', 'province' => 'Metro Manila', 'base_shipping_rate' => '50.00',
            'per_kg_rate' => '20.00', 'estimated_days' => '1-3 Business Days', 'is_active' => true, 'reason' => 'Approved synthetic service rate.'], $overrides);
    }

    private function cart(string $address): array
    {
        $buyer = $this->item->order->user;
        $saved = $buyer->addresses()->create(['label' => 'Synthetic destination', 'address' => $address, 'is_default' => true]);
        $cart = CartItem::create(['user_id' => $buyer->id, 'product_id' => $this->item->product_id, 'quantity' => 1]);
        return [$buyer, $saved, $cart, ['shipping_address' => $address, 'payment_method' => 'cod', 'shipping_method' => 'standard', 'selected_items' => [$cart->id]]];
    }

    public function test_log_rate_001_company_update_changes_new_quote_and_order_but_preserves_booked_fees_and_rejects_stale_quote(): void
    {
        $this->area->update(['city_code' => '1381300000']);
        $this->item->order->update(['shipping_fee' => 50]);
        [$buyer, $address, $cart, $checkout] = $this->cart(CheckoutCoverageTest::NCR_ADDRESS);
        Sanctum::actingAs($this->center->user);
        $id = $this->postJson('/api/sorting-center/delivery-rates', $this->input())->assertCreated()->json('data.id');
        $this->putJson('/api/sorting-center/delivery-rates/'.$id, $this->input(['base_shipping_rate' => '55.00']))->assertOk();
        $audit = LogisticsAuditEvent::where('action', 'shipping_rate_updated')->sole();
        $this->assertSame($this->center->user_id, $audit->actor_id);
        $this->assertSame(50.0, (float) $audit->data['old']['base_shipping_rate']);
        $this->assertSame(55.0, (float) $audit->data['new']['base_shipping_rate']);
        $this->actingAs($buyer, 'web')->get(route('buyer.checkout', ['selected_items' => [$cart->id]]))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('shipping_rate_cards.'.$address->id.'.standard.unweighted.base', 55));
        $orders = Order::count(); $stock = $this->item->product->stock;
        $this->post(route('buyer.orders.store'), $checkout + ['expected_shipping_fee' => 50])->assertSessionHasErrors('shipping_fee');
        $this->assertSame($orders, Order::count()); $this->assertSame($stock, $this->item->product->fresh()->stock); $this->assertNotNull($cart->fresh());
        $this->post(route('buyer.orders.store'), $checkout + ['expected_shipping_fee' => 55])->assertSessionHasNoErrors();
        $this->assertSame('55.00', Order::latest('id')->firstOrFail()->shipping_fee);
        $this->assertSame('50.00', $this->item->order->fresh()->shipping_fee);
    }

    public function test_log_rate_002_owns_zones_and_rejects_invalid_base_rates_without_writes(): void
    {
        Sanctum::actingAs($this->center->user);
        foreach ([-1, 0, 'abc', '0.001'] as $invalid) $this->postJson('/api/sorting-center/delivery-rates', $this->input(['base_shipping_rate' => $invalid]))->assertUnprocessable()->assertJsonValidationErrors('base_shipping_rate');
        $this->assertDatabaseCount('delivery_zones', 0); $this->assertDatabaseCount('logistics_audit_events', 0);
        $id = $this->postJson('/api/sorting-center/delivery-rates', $this->input(['base_shipping_rate' => '0.01']))->assertCreated()->json('data.id');
        $this->assertSame('0.01', DeliveryZone::findOrFail($id)->base_shipping_rate);
        $foreignUser = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        SortingCenter::create(['user_id' => $foreignUser->id, 'business_name' => 'Other synthetic company', 'approval_status' => 'approved']);
        Sanctum::actingAs($foreignUser);
        $this->getJson('/api/sorting-center/delivery-rates')->assertOk()->assertJsonCount(0, 'data');
        $this->putJson('/api/sorting-center/delivery-rates/'.$id, $this->input(['base_shipping_rate' => 999]))->assertNotFound();
        $this->assertSame('0.01', DeliveryZone::findOrFail($id)->base_shipping_rate);
        $this->assertDatabaseCount('logistics_audit_events', 1);
    }

    public function test_log_rate_003_004_explicit_active_company_zone_enables_remote_checkout_at_its_configured_rate(): void
    {
        $this->area->update(['city_code' => '1381300000']);
        [$buyer, $address, $cart, $checkout] = $this->cart(CheckoutCoverageTest::BATANES_ADDRESS);
        $this->actingAs($buyer, 'web')->post(route('buyer.orders.store'), $checkout)->assertSessionHasErrors('shipping_address');
        $this->assertNotNull($cart->fresh());
        Sanctum::actingAs($this->center->user);
        $input = $this->input(['name' => 'Batanes delivery', 'province' => 'Batanes', 'base_shipping_rate' => 77]);
        $id = $this->postJson('/api/sorting-center/delivery-rates', $input)->assertCreated()->json('data.id');
        $this->assertSame('supported', app(CheckoutServiceability::class)->forAddresses([CheckoutCoverageTest::BATANES_ADDRESS])[0]['state']);
        $this->actingAs($buyer, 'web')->get(route('buyer.checkout', ['selected_items' => [$cart->id]]))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('address_serviceability.'.$address->id.'.state', 'supported')->where('shipping_rate_cards.'.$address->id.'.standard.unweighted.base', 77));
        $this->post(route('buyer.orders.store'), $checkout + ['expected_shipping_fee' => 77])->assertSessionHasNoErrors();
        $this->assertSame('77.00', Order::latest('id')->firstOrFail()->shipping_fee);
        Sanctum::actingAs($this->center->user);
        $this->putJson('/api/sorting-center/delivery-rates/'.$id, array_replace($input, ['is_active' => false]))->assertOk();
        $this->assertSame('unsupported', app(CheckoutServiceability::class)->forAddresses([CheckoutCoverageTest::BATANES_ADDRESS])[0]['state']);
        $this->assertSame('77.00', Order::latest('id')->firstOrFail()->shipping_fee);
    }

    public function test_unapproved_company_zone_cannot_enable_remote_delivery_and_store_pickup_has_no_delivery_fee(): void
    {
        [$buyer, , , $checkout] = $this->cart(CheckoutCoverageTest::BATANES_ADDRESS);
        DeliveryZone::create($this->input(['name' => 'Batanes', 'province' => 'Batanes']) + ['sorting_center_id' => $this->center->id]);
        $this->center->user->update(['status' => 'pending']);
        $this->assertSame('unsupported', app(CheckoutServiceability::class)->forAddresses([CheckoutCoverageTest::BATANES_ADDRESS])[0]['state']);
        $this->actingAs($buyer, 'web')->post(route('buyer.orders.store'), array_replace($checkout, ['shipping_method' => 'pickup', 'expected_shipping_fee' => 0]))->assertSessionHasNoErrors();
        $this->assertSame('0.00', Order::latest('id')->firstOrFail()->shipping_fee);
        $this->assertDatabaseCount('seller_delivery_charges', 0);
    }
}
