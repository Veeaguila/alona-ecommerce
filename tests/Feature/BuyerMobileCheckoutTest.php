<?php

namespace Tests\Feature;

use App\Models\{CartItem, Order, Product, ProductVariant, SellerNotification, SellerShipment, User, UserVoucher, Voucher};
use Illuminate\Support\Facades\{Auth, Cache};
use Illuminate\Support\Str;
use Tests\Support\OrderJourney;

class BuyerMobileCheckoutTest extends OrderJourney
{
    private function selection(array $extra = []): array
    {
        return array_replace(['cart_item_ids' => [$this->cart->id], 'shipping_address' => self::ADDRESS,
            'shipping_method' => 'standard', 'payment_method' => 'cod'], $extra);
    }

    private function quote(array $selection): array
    {
        $this->apiAs($this->buyer);
        return $this->postJson('/api/buyer/checkout/preview', $selection)->assertOk()->json();
    }

    private function booking(array $selection, ?array $quote = null): array
    {
        $quote ??= $this->quote($selection);
        return $selection + ['expected_shipping_fee' => $quote['shipping_fee_before_discount'],
            'expected_total' => $quote['total'], 'expected_prices' => $quote['expected_prices'], 'request_key' => (string) Str::uuid()];
    }

    public function test_mobile_booking_reaches_seller_and_both_logistics_clients_then_buyer_delivery(): void
    {
        $request = $this->booking($this->selection());
        $response = $this->postJson('/api/buyer/orders', $request)->assertCreated();
        $this->order = Order::findOrFail($response->json('order.id'));
        $this->item = $this->order->items()->sole();
        $this->assertSame('125.00', $this->item->price);
        $this->assertSame('buyer', $this->order->delivery_fee_payer);
        $this->assertSame('unpaid', $this->order->payment_status);
        $this->assertDatabaseCount('seller_shipments', 0); // Buyer-funded orders do not enter historical seller-funded ledgers.
        $this->assertDatabaseHas('seller_notifications', ['user_id' => $this->seller->id, 'title' => 'New Order Received']);
        $this->ready();
        $this->apiAs($this->center->user);
        $this->getJson('/api/sorting-center/pickup-requests?status=all')->assertOk()
            ->assertJsonFragment(['order_item_id' => $this->item->id]);
        $this->actingAs($this->center->user, 'web')->get('/sorting-center/pickup-requests')->assertOk();
        $parcel = $this->dispatchToDelivery();
        $this->apiAs($this->buyer);
        $details = $this->getJson('/api/buyer/orders/'.$this->order->id)->assertOk();
        $details->assertJsonPath('order.items.0.delivery_parcel_id', $parcel->id);
        $this->assertTrue(collect($details->json('status_histories'))->contains('status', 'packed'));
        $otp = $this->getJson("/api/buyer/orders/{$this->order->id}/parcels/{$parcel->id}/delivery-otp")
            ->assertOk()->assertHeader('Pragma', 'no-cache')->json('data.otp');
        $this->apiAs($this->deliveryRider->user);
        $this->postJson("/api/rider/deliveries/{$parcel->id}/upload-proof", ['type' => 'PHOTO',
            'file' => \Illuminate\Http\UploadedFile::fake()->image('proof.jpg')])->assertCreated();
        $this->postJson("/api/rider/deliveries/{$parcel->id}/deliver", ['otp' => $otp])->assertOk();
        $this->apiAs($this->buyer);
        $this->getJson('/api/buyer/orders/'.$this->order->id)->assertOk()->assertJsonPath('order.status', 'delivered');
        $this->postJson('/api/buyer/orders/'.$this->order->id.'/receive')->assertOk();
        $this->assertSame('completed', $this->item->fresh()->status);
    }

    public function test_quotes_and_retry_do_not_double_consume_stock_vouchers_or_create_orders(): void
    {
        $voucher = Voucher::create(['code' => 'MOBILE10', 'type' => 'fixed', 'value' => 10, 'is_active' => true,
            'is_platform' => true, 'used_count' => 0, 'usage_limit' => 2]);
        UserVoucher::create(['user_id' => $this->buyer->id, 'voucher_id' => $voucher->id, 'status' => 'claimed']);
        $selection = $this->selection(['voucher_code' => 'MOBILE10']);
        $quote = $this->quote($selection);
        $this->assertEquals(290, $quote['total']);
        $this->quote($selection);
        $this->assertSame(0, $voucher->fresh()->used_count);
        $this->assertDatabaseHas('user_vouchers', ['voucher_id' => $voucher->id, 'status' => 'claimed']);
        $request = $this->booking($selection, $quote);
        $first = $this->postJson('/api/buyer/orders', $request)->assertCreated()->json('order.id');
        $this->postJson('/api/buyer/orders', $request)->assertCreated()->assertJsonPath('order.id', $first);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(8, $this->product->fresh()->stock);
        $this->assertSame(1, $voucher->fresh()->used_count);
        $this->postJson('/api/buyer/orders', array_replace($request, ['buyer_note' => 'Changed']))->assertConflict();
    }

    public function test_buy_now_preserves_existing_cart_and_retries_same_order(): void
    {
        $selection = $this->selection(['buy_now' => true, 'product_id' => $this->product->id, 'quantity' => 1]);
        unset($selection['cart_item_ids']);
        $request = $this->booking($selection);
        $first = $this->postJson('/api/buyer/orders', $request)->assertCreated()->json('order.id');
        $this->postJson('/api/buyer/orders', $request)->assertCreated()->assertJsonPath('order.id', $first);
        $this->assertSame(9, $this->product->fresh()->stock);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertSame(2, $this->cart->fresh()->quantity);
    }

    public function test_buy_now_invalid_quote_rolls_back_temporary_cart_and_stock(): void
    {
        $selection = $this->selection(['buy_now' => true, 'product_id' => $this->product->id, 'quantity' => 1]);
        unset($selection['cart_item_ids']);
        $request = $this->booking($selection);
        $request['expected_total'] = 1;
        $this->postJson('/api/buyer/orders', $request)->assertUnprocessable();
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $this->product->fresh()->stock);
    }

    public function test_multistore_quote_matches_saved_store_totals_and_shipments(): void
    {
        $otherSeller = User::factory()->create(['usertype' => 'seller', 'status' => 'approved', 'address' => self::ADDRESS]);
        $otherProduct = $this->product->replicate();
        $otherProduct->fill(['seller_id' => $otherSeller->id, 'slug' => 'second', 'price' => 1100])->save();
        $this->product->update(['price' => 1100]);
        $this->cart->update(['quantity' => 1]);
        $otherCart = CartItem::create(['user_id' => $this->buyer->id, 'product_id' => $otherProduct->id, 'quantity' => 1]);
        $selection = $this->selection(['cart_item_ids' => [$this->cart->id, $otherCart->id]]);
        $quote = $this->quote($selection);
        // Per-store fees are retained even when combined merchandise reaches the threshold.
        $this->assertEquals(100, $quote['shipping_fee']);
        $this->assertEquals(100, $quote['shipping_fee_before_discount']);
        Voucher::create(['code' => 'SHIP60', 'type' => 'free_shipping', 'value' => 60, 'is_active' => true, 'is_platform' => true]);
        $selection['voucher_code'] = 'SHIP60';
        $quote = $this->quote($selection);
        $this->assertEquals(40, $quote['shipping_fee']);
        $this->postJson('/api/buyer/orders', $this->booking($selection, $quote))->assertCreated()->assertJsonCount(2, 'orders');
        $this->assertEquals($quote['total'], Order::sum('total'));
        $this->assertDatabaseCount('seller_shipments', 0);
    }

    public function test_regional_weight_rate_replaces_hardcoded_mobile_fee(): void
    {
        $this->product->update(['weight' => 8]);
        $quote = $this->quote($this->selection());
        $this->assertGreaterThan(50, $quote['shipping_fee']);
        $this->postJson('/api/buyer/orders', $this->booking($this->selection(), $quote))->assertCreated();
        $this->assertEquals($quote['shipping_fee'], Order::sole()->shipping_fee);
    }

    public function test_expired_voucher_is_rejected_without_creating_order(): void
    {
        Voucher::create(['code' => 'EXPIRED', 'type' => 'fixed', 'value' => 10, 'is_active' => true,
            'is_platform' => true, 'expires_at' => now()->subDay()]);
        $this->apiAs($this->buyer);
        $this->postJson('/api/buyer/checkout/preview', $this->selection(['voucher_code' => 'EXPIRED']))->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_changed_price_shipping_and_insufficient_stock_require_refresh(): void
    {
        $request = $this->booking($this->selection());
        $this->product->update(['price' => 140]);
        $this->postJson('/api/buyer/orders', $request)->assertUnprocessable()->assertJsonValidationErrors('expected_prices');
        $this->product->update(['price' => 125, 'weight' => 8]);
        $this->postJson('/api/buyer/orders', $request)->assertUnprocessable()->assertJsonValidationErrors('shipping_fee');
        $this->product->update(['weight' => .5, 'stock' => 1]);
        $this->postJson('/api/buyer/orders', $request)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_foreign_cart_variant_and_nonbuyer_tokens_are_rejected(): void
    {
        $other = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $foreign = CartItem::create(['user_id' => $other->id, 'product_id' => $this->product->id, 'quantity' => 1]);
        $this->apiAs($this->buyer);
        $this->postJson('/api/buyer/checkout/preview', $this->selection(['cart_item_ids' => [$foreign->id]]))->assertUnprocessable();
        $second = $this->product->replicate(); $second->slug = 'foreign'; $second->save();
        $variant = ProductVariant::create(['product_id' => $second->id, 'price' => 1, 'stock' => 10, 'color' => 'Red']);
        $this->postJson('/api/buyer/cart', ['product_id' => $this->product->id, 'product_variant_id' => $variant->id])->assertNotFound();
        $this->postJson('/api/buyer/checkout/preview', $this->selection(['buy_now' => true, 'product_id' => $this->product->id,
            'product_variant_id' => $variant->id, 'quantity' => 1]))->assertUnprocessable();
        $this->apiAs($this->center->user);
        $this->getJson('/api/buyer/orders')->assertForbidden();
        $this->postJson('/api/buyer/cart', ['product_id' => $this->product->id])->assertForbidden();
    }

    public function test_address_form_uses_existing_schema_and_rebuilds_full_snapshot(): void
    {
        $this->apiAs($this->buyer);
        $address = ['label' => 'Work', 'recipient_name' => 'Synthetic Buyer', 'phone' => '09170000002',
            'region' => 'NCR', 'province' => 'Metro Manila', 'city' => 'Quezon City', 'barangay' => 'Central',
            'street' => '45 Kalayaan Ave.', 'postal_code' => '1100', 'is_default' => true];
        $id = $this->postJson('/api/buyer/addresses', $address)->assertCreated()->json('address.id');
        $this->assertDatabaseHas('addresses', ['id' => $id, 'recipient_phone' => $address['phone'], 'municipality' => $address['city'],
            'street_address' => $address['street'], 'address' => '45 Kalayaan Ave., Brgy. Central, Quezon City, Metro Manila, 1100']);
        $this->patchJson('/api/buyer/addresses/'.$id, array_replace($address, ['street' => '46 Kalayaan Ave.']))->assertOk();
        $this->assertStringStartsWith('46 Kalayaan', $this->buyer->addresses()->find($id)->address);
    }

    public function test_mobile_cancel_uses_finance_locks_history_and_single_restock(): void
    {
        $this->postJson('/api/buyer/orders', $this->booking($this->selection()))->assertCreated();
        $id = Order::sole()->id;
        $this->postJson('/api/buyer/orders/'.$id.'/cancel', ['reason' => 'Changed my mind'])->assertOk();
        $this->postJson('/api/buyer/orders/'.$id.'/cancel', ['reason' => 'Repeated'])->assertUnprocessable();
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertDatabaseHas('order_item_status_histories', ['status' => 'cancelled']);
    }

    public function test_api_without_json_accept_still_returns_booking_and_cancellation_json(): void
    {
        $request = $this->booking($this->selection());
        $this->post('/api/buyer/orders', $request, ['Accept' => 'text/html'])->assertCreated()->assertJsonPath('order.payment_status', 'unpaid');
        $this->post('/api/buyer/orders/'.Order::sole()->id.'/cancel', ['reason' => 'Changed plans'], ['Accept' => 'text/html'])->assertOk()->assertJsonPath('order.status', 'cancelled');
    }

    public function test_unverified_or_suspended_buyer_cannot_use_stored_tokens(): void
    {
        $this->apiAs($this->buyer);
        $this->buyer->forceFill(['email_verified_at' => null])->save();
        $this->getJson('/api/buyer/orders')->assertForbidden();
        $this->buyer->forceFill(['email_verified_at' => now(), 'status' => 'suspended'])->save();
        $this->getJson('/api/buyer/orders')->assertForbidden();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_missing_reviewed_quote_and_unserviceable_destination_cannot_write_orders(): void
    {
        $this->apiAs($this->buyer);
        $this->postJson('/api/buyer/orders', $this->selection())->assertUnprocessable()->assertJsonValidationErrors('expected_total');
        $this->postJson('/api/buyer/checkout/preview', $this->selection(['shipping_address' => 'Purok 2, Brgy. Raele, Itbayat, Batanes, Region II 3905']))->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertNotNull($this->cart->fresh());
    }

    public function test_online_payment_selection_stays_unpaid_until_actual_payment(): void
    {
        $this->postJson('/api/buyer/orders', $this->booking($this->selection(['payment_method' => 'gcash'])))
            ->assertCreated()->assertJsonPath('order.payment_status', 'unpaid');
        $this->assertSame('unpaid', Order::sole()->payment_status);
    }

    public function test_mobile_booking_records_verified_hub_plan_and_rejects_unavailable_route(): void
    {
        config(['logistics_hubs.enforce_checkout_routes' => true]);
        $this->apiAs($this->buyer);
        $this->postJson('/api/buyer/checkout/preview', $this->selection())->assertUnprocessable();
        $hub = \App\Models\LogisticsHub::create(['sorting_center_id' => $this->center->id, 'code' => 'LOCAL', 'name' => 'Synthetic local hub',
            'address' => self::ADDRESS, 'city_code' => '1381300000', 'pickup_city_codes' => ['1381300000'],
            'delivery_city_codes' => ['1381300000'], 'is_active' => true]);
        $this->pickupRider->forceFill(['logistics_hub_id' => $hub->id])->save();
        $this->pickupRider->areas()->sync([$this->area->id]);
        $this->postJson('/api/buyer/orders', $this->booking($this->selection()))->assertCreated();
        $plan = Order::sole()->items()->sole()->hub_route_plan;
        $this->assertSame($hub->id, $plan['origin_hub_id']);
        $this->assertSame($hub->id, $plan['destination_hub_id']);
        $this->assertSame($this->center->id, $plan['sorting_center_id']);
        $this->assertDatabaseCount('seller_shipments', 0);
    }
}
