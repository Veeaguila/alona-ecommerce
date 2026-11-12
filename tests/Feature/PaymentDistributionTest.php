<?php

namespace Tests\Feature;

use App\Models\{Category, CodCollection, CodRemittance, DeliveryProof, Order, OrderItem, Parcel, PaymentDistribution, PaymentDistributionPayout, Product, RefundLedger, Rider, SortingCenter, User};
use App\Services\Payments\{DistributionLedger, Money};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentDistributionTest extends TestCase
{
    use RefreshDatabase;

    private DistributionLedger $ledger;
    private User $admin;
    private User $seller;
    private User $buyer;
    private Rider $rider;
    private SortingCenter $center;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        config(['distribution.enabled' => true, 'distribution.settlement_account' => 'alona-bank',
            'distribution.webhook_secret' => str_repeat('x', 40), 'distribution.merchant' => 'alona-test']);
        Storage::fake('local');
        $this->ledger = app(DistributionLedger::class);
        $this->admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']);
        $this->seller = User::factory()->create(['usertype' => 'seller', 'status' => 'approved']);
        $this->buyer = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $owner = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        $this->center = SortingCenter::create(['user_id' => $owner->id, 'business_name' => 'Test logistics', 'approval_status' => 'approved']);
        $rider = User::factory()->create(['usertype' => 'rider', 'status' => 'approved']);
        $this->rider = Rider::create(['user_id' => $rider->id, 'sorting_center_id' => $this->center->id, 'name' => 'Rider',
            'contact' => '09170000000', 'vehicle_type' => 'motorcycle', 'plate_number' => 'TEST', 'approval_status' => 'approved', 'is_active' => true]);
    }

    private function order(string $shipping = '50.00', string $payment = 'card', array $products = []): PaymentDistribution
    {
        $products = $products ?: [[$this->seller->id, '500.00', 1]];
        $gross = array_sum(array_map(fn ($p) => Money::cents($p[1]) * $p[2], $products));
        $order = Order::withoutEvents(fn () => Order::create(['user_id' => $this->buyer->id, 'order_number' => 'PAY-'.Str::random(10),
            'subtotal' => Money::decimal($gross), 'total' => Money::decimal($gross + Money::cents($shipping)),
            'shipping_fee' => $shipping, 'shipping_fee_before_discount' => $shipping, 'shipping_discount' => '0.00', 'discount' => '0.00',
            'shipping_method' => $shipping === '120.00' ? 'express' : 'standard', 'shipping_address' => 'Manila',
            'payment_method' => $payment, 'payment_status' => 'unpaid', 'status' => 'pending', 'delivery_fee_payer' => 'buyer']));
        foreach ($products as [$seller, $price, $quantity]) {
            $product = Product::create(['seller_id' => $seller, 'name' => 'Product', 'slug' => Str::random(12), 'price' => $price, 'stock' => 10, 'status' => 'approved']);
            OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => 'Product', 'price' => $price, 'quantity' => $quantity]);
        }
        return $this->ledger->snapshot($order);
    }

    private function deliver(PaymentDistribution $d): void
    {
        DB::table('orders')->where('id', $d->order_id)->update(['status' => 'completed', 'delivered_at' => now()->subDays(8)]);
        DB::table('order_items')->where('order_id', $d->order_id)->update(['status' => 'completed']);
        foreach ($d->order->items as $item) {
            $parcel = Parcel::withoutEvents(fn () => Parcel::create(['order_id' => $d->order_id, 'order_item_id' => $item->id,
                'buyer_id' => $this->buyer->id, 'seller_id' => $item->product->seller_id, 'sorting_center_id' => $this->center->id,
                'assigned_rider_id' => $this->rider->id, 'status' => 'DELIVERED', 'delivery_address' => 'Manila', 'collection_address' => 'Manila',
                'delivery_province' => 'Metro Manila', 'delivery_municipality' => 'Manila', 'delivery_barangay' => 'Barangay 1',
                'delivery_street' => 'Test Street', 'delivery_house_number' => '1']));
            $path = 'delivery-proofs/'.$parcel->id.'.png';
            Storage::disk('local')->put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j4l0AAAAASUVORK5CYII='));
            DeliveryProof::create(['parcel_id' => $parcel->id, 'rider_id' => $this->rider->id, 'type' => 'PHOTO', 'file_path' => $path, 'captured_at' => now()]);
        }
    }

    private function receipt(PaymentDistribution $d, array $overrides = []): array
    {
        return array_replace(['request_key' => (string) Str::uuid(), 'kind' => 'online', 'amount' => Money::decimal($d->buyer_cents),
            'account' => 'alona-bank', 'reference' => (string) Str::uuid(), 'evidence' => 'Bank statement confirmed receipt', 'gateway_fee' => '0.00'], $overrides);
    }

    private function reserve(PaymentDistribution $d, ?int $lineId = null): array
    {
        return ['request_key' => (string) Str::uuid(), 'line_id' => $lineId ?? $d->lines()->where('role', 'seller')->value('id'),
            'destination' => ['method' => 'bank', 'account_name' => 'Test Seller', 'account_number' => '123456789', 'bank' => 'Test Bank']];
    }

    public function test_standard_and_express_allocations_conserve_every_centavo(): void
    {
        foreach (['50.00' => [45000, 5000, 3500, 1000, 500], '120.00' => [45000, 5000, 8400, 2400, 1200]] as $fee => $expected) {
            $d = $this->order($fee);
            $this->assertSame($expected, $d->lines()->orderBy('id')->pluck('amount_cents')->map(fn ($n) => (int) $n)->all());
            $this->assertEquals($d->buyer_cents, $d->lines()->sum('amount_cents'));
            $this->assertSame($d->id, $this->ledger->snapshot($d->order)->id);
            $this->assertSame(0, $this->ledger->total($d->id, 'receipt'));
        }
    }

    public function test_multiple_sellers_and_rounding_do_not_duplicate_shipping(): void
    {
        $other = User::factory()->create(['usertype' => 'seller']);
        $d = $this->order('50.01', products: [[$this->seller->id, '0.05', 3], [$other->id, '99.99', 2]]);
        $this->assertEquals(25014, $d->lines()->sum('amount_cents'));
        $this->assertEquals(5001, $d->lines()->whereIn('role', ['rider', 'logistics', 'platform_shipping'])->sum('amount_cents'));
        $this->assertCount(2, $d->lines()->where('role', 'seller')->get());
        for ($cents = 0; $cents < 300; $cents++) $this->assertSame($cents, array_sum(Money::split($cents, [70, 20, 10])));
    }

    public function test_unfunded_and_cancelled_orders_cannot_reserve_payouts(): void
    {
        $d = $this->order();
        $this->deliver($d);
        $this->actingAs($this->seller)->postJson("/payments/distributions/{$d->id}/payouts", $this->reserve($d))->assertUnprocessable();
        $this->ledger->receipt($this->admin, $d->id, $this->receipt($d));
        DB::table('orders')->where('id', $d->order_id)->update(['status' => 'cancelled']);
        $this->postJson("/payments/distributions/{$d->id}/payouts", $this->reserve($d))->assertUnprocessable();
        $this->assertDatabaseCount('payment_distribution_payouts', 0);
    }

    public function test_duplicate_reservations_and_payout_results_are_idempotent(): void
    {
        $d = $this->order(); $this->deliver($d);
        $this->ledger->receipt($this->admin, $d->id, $this->receipt($d));
        $input = $this->reserve($d);
        $payout = $this->ledger->reserve($this->seller, $d->id, $input);
        $this->assertSame($payout->id, $this->ledger->reserve($this->seller, $d->id, $input)->id);
        $this->actingAs($this->seller)->postJson("/payments/distributions/{$d->id}/payouts", $this->reserve($d))->assertConflict();
        $result = ['request_key' => 'paid-1', 'status' => 'paid', 'reference' => 'bank-transfer-1', 'evidence' => 'Bank confirmation receipt'];
        $this->ledger->payout($this->admin, $payout->id, $result);
        $this->ledger->payout($this->admin, $payout->id, $result);
        $this->assertSame(45000, $this->ledger->total($d->id, 'payout_paid'));
        $this->assertDatabaseCount('payment_distribution_payouts', 1);
        $this->assertNotContains('123456789', DB::table('payment_distribution_payouts')->pluck('destination')->all());
    }

    public function test_unauthorized_users_cannot_reconcile_reserve_or_mark_paid(): void
    {
        $d = $this->order(); $this->deliver($d);
        $this->actingAs($this->buyer)->getJson('/payments/distributions')->assertForbidden();
        $this->actingAs($this->seller)->postJson("/payments/distributions/{$d->id}/receipts", $this->receipt($d))->assertForbidden();
        $this->ledger->receipt($this->admin, $d->id, $this->receipt($d));
        $other = User::factory()->create(['usertype' => 'seller', 'status' => 'approved']);
        $this->actingAs($other)->postJson("/payments/distributions/{$d->id}/payouts", $this->reserve($d))->assertForbidden();
        $this->getJson('/payments/distributions')->assertOk()->assertJsonCount(0, 'distributions');
        $payout = $this->ledger->reserve($this->seller, $d->id, $this->reserve($d));
        $this->actingAs($this->seller)->postJson("/payments/payouts/{$payout->id}/outcome", ['request_key' => 'fraud', 'status' => 'paid', 'reference' => 'fake', 'evidence' => 'Fake transfer evidence'])->assertForbidden();
    }

    private function settlementCallback(array $data, ?string $signature = null)
    {
        $json = json_encode($data);
        $timestamp = (string) time();
        $signature ??= hash_hmac('sha256', $timestamp.'.'.$json, config('distribution.webhook_secret'));
        return $this->call('POST', '/api/webhooks/settlement', [], [], [], ['CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json', 'HTTP_X_ALONA_TIMESTAMP' => $timestamp, 'HTTP_X_ALONA_SIGNATURE' => $signature], $json);
    }

    public function test_authenticated_online_settlement_callback_replay_and_validation(): void
    {
        $d = $this->order();
        $data = ['event_id' => 'event-1', 'merchant' => 'alona-test', 'order_id' => $d->order_id, 'currency' => 'PHP',
            'status' => 'settled', 'amount' => '550.00', 'gateway_fee' => '0.00', 'reference' => 'provider-settlement-1', 'account' => 'alona-bank'];
        $this->settlementCallback($data, 'invalid')->assertUnauthorized();
        $this->settlementCallback([...$data, 'amount' => '549.00'])->assertUnprocessable();
        $this->settlementCallback([...$data, 'merchant' => 'other'])->assertUnauthorized();
        $this->settlementCallback([...$data, 'currency' => 'USD'])->assertUnprocessable();
        $this->settlementCallback($data)->assertOk(); $this->settlementCallback($data)->assertOk();
        $this->assertSame(55000, $this->ledger->total($d->id, 'receipt'));
        $this->settlementCallback([...$data, 'reference' => 'different-ref'])->assertConflict();
        $this->postJson('/api/webhooks/payment', ['order_id' => $d->order_id, 'status' => 'paid'])->assertUnprocessable();
    }

    public function test_browser_success_does_not_verify_payment(): void
    {
        $d = $this->order();
        $this->actingAs($this->buyer)->post(route('buyer.orders.pay', $d->order_id), ['action' => 'success'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $d->order_id, 'payment_status' => 'unpaid']);
    }

    public function test_cod_requires_confirmed_matching_remittance_and_platform_receipt(): void
    {
        $d = $this->order(payment: 'cod'); $this->deliver($d);
        $parcel = Parcel::where('order_id', $d->order_id)->firstOrFail();
        $collection = CodCollection::create(['request_key' => Str::uuid(), 'order_id' => $d->order_id, 'parcel_id' => $parcel->id,
            'rider_id' => $this->rider->id, 'sorting_center_id' => $this->center->id, 'amount' => '550.00', 'evidence_path' => 'test', 'collected_at' => now()]);
        $remit = CodRemittance::create(['request_key' => Str::uuid(), 'rider_id' => $this->rider->id, 'sorting_center_id' => $this->center->id,
            'submitted_amount' => '550.00', 'evidence_path' => 'test', 'status' => 'submitted']);
        $input = $this->receipt($d, ['kind' => 'cod', 'collection_id' => $collection->id, 'remittance_id' => $remit->id, 'matching_id' => 999]);
        $url = "/payments/distributions/{$d->id}/receipts";
        $this->actingAs($this->admin)->postJson($url, $input)->assertUnprocessable();
        $remit->update(['status' => 'confirmed', 'received_amount' => '500.00']);
        $this->postJson($url, $input)->assertUnprocessable();
        $input['amount'] = '500.00';
        $matchInput = ['request_key' => 'match-cod-1', 'collection_id' => $collection->id, 'remittance_id' => $remit->id,
            'amount' => '500.00', 'evidence' => 'Confirmed hub receipt'];
        $match = $this->ledger->matchCod($this->admin, $d->id, $matchInput);
        $this->assertSame($match->id, $this->ledger->matchCod($this->admin, $d->id, $matchInput)->id);
        $this->assertSame(0, $this->ledger->total($d->id, 'receipt'));
        $this->getJson('/payments/distributions')->assertJsonPath('distributions.0.cod_status', 'remitted');
        $input['matching_id'] = $match->id;
        $this->postJson($url, [...$input, 'account' => 'hub-cash'])->assertUnprocessable();
        $this->postJson($url, $input)->assertOk();
        $this->postJson($url, $input)->assertOk();
        $this->actingAs($this->seller)->postJson("/payments/distributions/{$d->id}/payouts", $this->reserve($d))->assertUnprocessable();
        $this->assertSame(50000, $this->ledger->total($d->id, 'receipt'));
    }

    private function refundInput(PaymentDistribution $d, string $amount, string $component = 'product'): array
    {
        $item = $d->order->items->first();
        $refund = RefundLedger::create(['refund_reference' => Str::uuid(), 'order_id' => $d->order_id, 'order_item_id' => $item->id,
            'seller_id' => $this->seller->id, 'buyer_id' => $this->buyer->id, 'original_amount' => '500.00', 'refunded_amount' => $amount,
            'status' => 'completed', 'reason' => 'Defective item']);
        return ['request_key' => (string) Str::uuid(), 'refund_id' => $refund->id, 'amount' => $amount, 'item_id' => $item->id,
            'component' => $component, 'liability' => 'seller', 'evidence' => 'Investigation and bank refund confirmed', 'reference' => (string) Str::uuid()];
    }

    public function test_partial_and_full_product_refunds_reverse_original_shares_exactly(): void
    {
        $d = $this->order(); $this->deliver($d);
        $this->ledger->receipt($this->admin, $d->id, $this->receipt($d));
        $input = $this->refundInput($d, '100.05');
        $input['item_id'] = (string) $input['item_id']; // Form requests may send integer identifiers as strings.
        $this->ledger->refund($this->admin, $d->id, $input);
        $this->ledger->refund($this->admin, $d->id, $input);
        $sellerLine = $d->lines()->where('role', 'seller')->first();
        $this->assertSame(35996, $this->ledger->balance($sellerLine));
        $this->ledger->refund($this->admin, $d->id, $this->refundInput($d, '399.95'));
        $this->assertSame(0, $this->ledger->balance($sellerLine));
        $this->assertSame(50000, $this->ledger->total($d->id, 'reversal'));
        $this->assertSame(3500, $this->ledger->balance($d->lines()->where('role', 'rider')->first()));
    }

    public function test_shipping_refund_preserves_rider_earnings_and_records_liability(): void
    {
        $d = $this->order();
        $input = $this->refundInput($d, '50.00', 'shipping'); $input['liability'] = 'logistics';
        $this->ledger->refund($this->admin, $d->id, $input);
        $this->assertSame(4500, $this->ledger->total($d->id, 'liability'));
        $this->assertSame(500, $this->ledger->total($d->id, 'reversal'));
        $this->assertSame(3500, $this->ledger->balance($d->lines()->where('role', 'rider')->first()));
    }

    public function test_refund_after_payout_preserves_history_and_holds_unfunded_recovery(): void
    {
        $d = $this->order(); $this->deliver($d);
        $this->ledger->receipt($this->admin, $d->id, $this->receipt($d));
        $p = $this->ledger->reserve($this->seller, $d->id, $this->reserve($d));
        $this->ledger->payout($this->admin, $p->id, ['request_key' => 'paid', 'status' => 'paid', 'reference' => 'transfer-1', 'evidence' => 'Confirmed bank payment']);
        $this->ledger->refund($this->admin, $d->id, $this->refundInput($d, '100.00'));
        $this->assertSame('paid', $p->fresh()->status);
        $this->assertContains('Insufficient reconciled funding', $this->ledger->holds($d->fresh()));
    }

    public function test_gateway_fees_and_discounts_require_explicit_funding(): void
    {
        $d = $this->order(); $this->deliver($d);
        $this->ledger->receipt($this->admin, $d->id, $this->receipt($d, ['amount' => '540.00', 'gateway_fee' => '10.00']));
        $this->assertContains('Insufficient reconciled funding', $this->ledger->holds($d->fresh()));
        $this->ledger->receipt($this->admin, $d->id, $this->receipt($d, ['kind' => 'subsidy', 'amount' => '10.00', 'funder' => 'Alona operating account']));
        $this->assertSame([], $this->ledger->holds($d->fresh()));
        $this->assertSame(45000, (int) $d->lines()->where('role', 'seller')->value('amount_cents'));
    }

    public function test_profitability_can_block_insufficient_delivery_and_wrong_seller_cost_changes(): void
    {
        $this->actingAs($this->admin)->postJson('/payments/profitability', ['shipping_method' => 'standard', 'available' => true,
            'block_shortfall' => true, 'logistics_cost_cents' => 2000, 'logistics_margin_cents' => 0, 'rider_cost_cents' => 0,
            'rider_minimum_cents' => 0, 'blocked_zones' => []])->assertOk();
        try { $this->order(); $this->fail('Unprofitable shipping accepted.'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('shipping_method', $e->errors()); }
        $this->assertDatabaseCount('payment_distributions', 0);
        $product = Product::first();
        $this->actingAs(User::factory()->create(['usertype' => 'seller', 'status' => 'approved']))->postJson('/payments/product-costs',
            ['product_id' => $product->id, 'cost_cents' => 1, 'minimum_margin_cents' => 0, 'block_shortfall' => false])->assertForbidden();
    }

    public function test_role_dashboards_show_only_scoped_allocations_and_legacy_balance_excludes_new_cohort(): void
    {
        $d = $this->order(); $this->deliver($d);
        $this->ledger->receipt($this->admin, $d->id, $this->receipt($d));
        $this->actingAs($this->admin)->getJson('/payments/distributions')->assertOk()->assertJsonPath('summary.platform_revenue', '55.00');
        $this->actingAs($this->seller)->getJson('/payments/distributions')->assertOk()->assertJsonPath('summary.available', '450.00')->assertJsonCount(2, 'distributions.0.lines');
        $this->actingAs($this->rider->user)->getJson('/payments/distributions')->assertOk()->assertJsonPath('summary.available', '35.00');
        $this->actingAs($this->center->user)->getJson('/payments/distributions')->assertOk()->assertJsonPath('summary.available', '10.00');
        $this->assertSame(0.0, app(\App\Http\Controllers\SellerReportController::class)->calculateSellerBalances($this->seller->id)['available_balance']);
    }

    public function test_verified_capture_allows_fulfillment_but_cannot_fund_a_payout(): void
    {
        $d = $this->order(); $this->deliver($d);
        $data = ['event_id' => 'capture-1', 'merchant' => 'alona-test', 'order_id' => $d->order_id, 'currency' => 'PHP',
            'status' => 'captured', 'amount' => '550.00', 'gateway_fee' => '0.00', 'reference' => 'provider-payment-1', 'account' => 'alona-bank'];
        $this->settlementCallback($data)->assertOk(); $this->settlementCallback($data)->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $d->order_id, 'payment_status' => 'paid']);
        $this->assertSame(0, $this->ledger->total($d->id, 'receipt'));
        $this->actingAs($this->seller)->postJson("/payments/distributions/{$d->id}/payouts", $this->reserve($d))->assertUnprocessable();
        $this->settlementCallback([...$data, 'event_id' => 'settled-1', 'status' => 'settled'])->assertOk();
        $this->postJson("/payments/distributions/{$d->id}/payouts", $this->reserve($d))->assertOk();
    }

    public function test_refund_or_new_complaint_blocks_a_reserved_payout_and_unknown_transfer_keeps_reservation(): void
    {
        $d = $this->order(); $this->deliver($d);
        $this->ledger->receipt($this->admin, $d->id, $this->receipt($d));
        $p = $this->ledger->reserve($this->seller, $d->id, $this->reserve($d));
        $this->ledger->payout($this->admin, $p->id, ['request_key' => 'unknown', 'status' => 'reconciling', 'reference' => 'bank-timeout', 'evidence' => 'Bank outcome is still unknown']);
        $this->assertSame('reconciling', $p->fresh()->status);
        $this->actingAs($this->seller)->postJson("/payments/distributions/{$d->id}/payouts", $this->reserve($d))->assertConflict();
        \App\Models\Complaint::create(['order_id' => $d->order_id, 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id,
            'subject' => 'Missing item', 'description' => 'Investigation requested', 'status' => 'pending']);
        $this->actingAs($this->admin)->postJson("/payments/payouts/{$p->id}/outcome", ['request_key' => 'pay-held', 'status' => 'paid', 'reference' => 'bank-ref', 'evidence' => 'Bank receipt verified'])->assertUnprocessable();
        $this->assertSame('reconciling', $p->fresh()->status);
    }

    public function test_missing_delivery_proof_and_suspended_beneficiaries_cannot_withdraw(): void
    {
        $d = $this->order(); $this->deliver($d);
        $this->ledger->receipt($this->admin, $d->id, $this->receipt($d));
        $proof = DeliveryProof::firstOrFail(); Storage::disk('local')->delete($proof->file_path);
        $this->actingAs($this->seller)->postJson("/payments/distributions/{$d->id}/payouts", $this->reserve($d))->assertUnprocessable();
        $this->seller->update(['status' => 'suspended']);
        $this->actingAs($this->admin)->postJson("/payments/distributions/{$d->id}/payouts", $this->reserve($d))->assertUnprocessable();
    }

    public function test_money_rejects_floats_and_proportions_remain_exact_at_the_supported_limit(): void
    {
        $this->assertSame(Money::MAX - 1, Money::proportion(Money::MAX, Money::MAX - 1, Money::MAX));
        $this->assertSame(250, Money::proportion(500, 2500, 5000));
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        Money::cents(12.34);
    }

    public function test_snapshot_pricing_is_immutable_and_feature_disable_blocks_new_release(): void
    {
        $d = $this->order(); $this->deliver($d);
        $this->ledger->receipt($this->admin, $d->id, $this->receipt($d));
        config(['distribution.enabled' => false]);
        $this->actingAs($this->seller)->postJson("/payments/distributions/{$d->id}/payouts", $this->reserve($d))->assertStatus(503);
        $this->expectException(\LogicException::class);
        $d->update(['buyer_cents' => 1]);
    }
}
