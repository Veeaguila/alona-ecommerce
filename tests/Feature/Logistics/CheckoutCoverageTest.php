<?php

namespace Tests\Feature\Logistics;

use App\Models\{Area, CartItem, Order, Parcel};
use App\Services\CheckoutServiceability;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

class CheckoutCoverageTest extends CompleteWorkflowTest
{
    public const NCR_ADDRESS = '45 Kalayaan Ave., Brgy. Central, Quezon City, Metro Manila, NCR 1100';
    public const BATANES_ADDRESS = 'Purok 2, Brgy. Raele, Itbayat, Batanes, Region II 3905';

    public function test_exact_city_context_requires_reliable_coverage_and_never_guesses_legacy_addresses(): void
    {
        $this->area->update(['name' => 'Quezon City', 'city_code' => '1381300000']);
        $snapshot = $this->area->fresh()->toArray();
        $service = app(CheckoutServiceability::class);
        $results = $service->forAddresses([self::NCR_ADDRESS, self::BATANES_ADDRESS,
            'Legacy destination', 'Itbayat Road, Quezon City', 'Quezon',
            'Quezon City, Metro Manila, Itbayat, Batanes', 'Itbayat, Batanes, Isabela']);
        $this->assertSame('supported', $results[0]['state']);
        $this->assertSame('1381300000', $results[0]['city_code']);
        $this->assertSame('unsupported', $results[1]['state']);
        foreach ([2, 3, 4] as $id) $this->assertSame('unknown', $results[$id]['state'], 'Unresolved address index '.$id);
        foreach ([5, 6] as $id) $this->assertSame('unsupported', $results[$id]['state'], 'Ambiguous remote destination cannot bypass coverage: '.$id);
        $this->assertSame($snapshot, $this->area->fresh()->toArray());
        Area::create(['sorting_center_id' => $this->center->id, 'name' => 'Unmapped legacy coverage']);
        $this->assertSame('unsupported', $service->forAddresses([self::BATANES_ADDRESS])[0]['state']);
        $this->assertSame('supported', $service->forAddresses([self::NCR_ADDRESS])[0]['state']);
        $this->center->user->update(['status' => 'pending']);
        $this->assertSame('unknown', $service->forAddresses([self::NCR_ADDRESS])[0]['state']);
    }

    public function test_checkout_rejects_verified_uncovered_address_before_order_stock_cart_or_fee_mutation(): void
    {
        $this->area->update(['name' => 'Quezon City', 'city_code' => '1381300000']);
        $buyer = $this->item->order->user;
        $address = $buyer->addresses()->create(['label' => 'Province', 'address' => self::BATANES_ADDRESS, 'is_default' => false]);
        $cart = CartItem::create(['user_id' => $buyer->id, 'product_id' => $this->item->product_id, 'quantity' => 1]);
        $this->actingAs($buyer, 'web')->get(route('buyer.checkout', ['selected_items' => [$cart->id]]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Buyer/Checkout')
                ->where('address_serviceability.'.$address->id.'.state', 'unsupported')
                ->where('shipping_options.0.fee_per_seller', 50));
        $stock = $this->item->product->stock; $orders = Order::count();
        $data = ['shipping_address' => self::BATANES_ADDRESS, 'payment_method' => 'cod', 'shipping_method' => 'standard', 'selected_items' => [$cart->id]];
        $this->post(route('buyer.orders.store'), $data)->assertSessionHasErrors('shipping_address');
        $this->assertSame($orders, Order::count());
        $this->assertSame($stock, $this->item->product->fresh()->stock);
        $this->assertNotNull($cart->fresh());
        $this->assertDatabaseCount('seller_shipments', 0);
        $this->assertDatabaseCount('seller_delivery_charges', 0);
        $this->post(route('buyer.orders.store'), array_replace($data, ['shipping_address' => self::NCR_ADDRESS]))->assertSessionHasNoErrors();
        $order = Order::latest('id')->firstOrFail();
        $this->assertSame('50.00', $order->shipping_fee);
        $this->assertSame('buyer', $order->delivery_fee_payer);
        $this->assertSame(0, \App\Models\SellerShipment::where('order_id', $order->id)->count());
        $this->assertSame('150.00', $order->total);
        $this->assertSame($stock - 1, $this->item->product->fresh()->stock);
    }

    public function test_no_canonical_coverage_preserves_checkout_for_unresolved_legacy_data(): void
    {
        $this->assertSame('unsupported', app(CheckoutServiceability::class)->forAddresses([self::BATANES_ADDRESS])[0]['state']);
        $buyer = $this->item->order->user;
        $cart = CartItem::create(['user_id' => $buyer->id, 'product_id' => $this->item->product_id, 'quantity' => 1]);
        $this->actingAs($buyer, 'web')->post(route('buyer.orders.store'), ['shipping_address' => 'Legacy address',
            'payment_method' => 'cod', 'shipping_method' => 'standard', 'selected_items' => [$cart->id]])->assertSessionHasNoErrors();
        $this->assertSame('50.00', Order::latest('id')->firstOrFail()->shipping_fee);
    }

    public function test_sorted_queue_and_region_metadata_use_existing_owned_city_areas_only(): void
    {
        $this->area->update(['name' => 'Quezon City', 'city_code' => '1381300000']);
        $legacy = Area::create(['sorting_center_id' => $this->center->id, 'name' => 'Legacy city']);
        $attributes = ['order_id' => 'NCR-SORTED', 'seller_id' => $this->item->product->seller_id,
            'buyer_id' => $this->item->order->user_id, 'sorting_center_id' => $this->center->id,
            'status' => 'AT_SORTING_CENTER', 'sorted_area_id' => $this->area->id, 'sorted_at' => now(),
            'delivery_municipality' => 'Quezon City', 'delivery_province' => '', 'delivery_barangay' => ''];
        Parcel::create($attributes);
        Parcel::create(array_replace($attributes, ['order_id' => 'UNSORTED', 'sorted_area_id' => null]));
        Parcel::create(array_replace($attributes, ['order_id' => 'FOREIGN', 'sorting_center_id' => null]));
        Sanctum::actingAs($this->center->user);
        $this->getJson('/api/sorting-center/parcels?status=sorted')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.order_id', 'NCR-SORTED');
        $areas = $this->getJson('/api/sorting-center/areas')->assertOk()->json('data');
        $this->assertSame('NCR', collect($areas)->firstWhere('id', $this->area->id)['region_label']);
        $this->assertSame('Unmapped service area', collect($areas)->firstWhere('id', $legacy->id)['region_label']);
        $this->assertNull($legacy->fresh()->city_code);
    }
}
