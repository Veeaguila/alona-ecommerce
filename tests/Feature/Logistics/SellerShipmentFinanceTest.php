<?php

namespace Tests\Feature\Logistics;

use App\Models\{Area, CartItem, Category, DeliveryProof, Order, OrderItem, Parcel, Product, Rider, RiderDeliveryEarning, SellerDeliveryCharge, SellerShipment, SortingCenter, User, Voucher};
use App\Services\SellerShipmentFinance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Event, Storage};
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerShipmentFinanceTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(['usertype' => $role, 'status' => 'approved']);
    }

    private function product(User $seller): Product
    {
        $category = Category::create(['name' => 'Finance test', 'slug' => Str::uuid(), 'is_active' => true]);

        return Product::create(['seller_id' => $seller->id, 'category_id' => $category->id,
            'name' => 'Test item', 'slug' => Str::uuid(), 'price' => 100, 'stock' => 20, 'status' => 'approved']);
    }

    private function fixture(int $count = 2): array
    {
        $seller = $this->user('seller');
        $buyer = $this->user('buyer');
        $center = SortingCenter::create(['user_id' => $this->user('sorting_center')->id, 'business_name' => 'Finance center', 'approval_status' => 'approved']);
        $rider = Rider::create(['user_id' => $this->user('rider')->id, 'sorting_center_id' => $center->id,
            'name' => 'Rider', 'contact' => '09170000000', 'vehicle_type' => 'bicycle', 'plate_number' => '', 'approval_status' => 'approved', 'is_active' => true]);
        $product = $this->product($seller);
        $order = Order::create(['user_id' => $buyer->id, 'order_number' => Str::uuid(), 'shipping_address' => 'Order destination',
            'subtotal' => 200, 'discount' => 180, 'total' => 20, 'payment_method' => 'cod', 'shipping_method' => 'standard',
            'delivery_fee_payer' => 'seller', 'status' => 'shipped']);
        for ($i = 0; $i < $count; $i++) {
            $order->items()->create(['product_id' => $product->id, 'product_name' => 'Item', 'price' => 100, 'quantity' => 1, 'status' => 'shipped']);
        }
        DB::transaction(fn () => app(SellerShipmentFinance::class)->createMembership($order));
        $shipment = SellerShipment::where('order_id', $order->id)->firstOrFail();
        $shipment->update(['assigned_rider_id' => $rider->id, 'sorting_center_id' => $center->id]);
        $area = Area::create(['sorting_center_id' => $center->id, 'name' => 'Area']);
        $rider->areas()->attach($area);
        $parcels = [];
        foreach ($order->items()->get() as $item) {
            $parcel = Parcel::create(['order_item_id' => $item->id, 'order_id' => 'ITEM-'.$item->id,
                'seller_id' => $seller->id, 'buyer_id' => $buyer->id, 'delivery_address' => $order->shipping_address,
                'collection_address' => 'Seller pickup', 'delivery_province' => '', 'delivery_municipality' => '', 'delivery_barangay' => '',
                'status' => 'AT_SORTING_CENTER', 'sorting_center_id' => $center->id, 'sorted_area_id' => $area->id, 'assigned_rider_id' => $rider->id]);
            $parcels[] = $parcel;
        }

        return compact('seller', 'buyer', 'center', 'rider', 'order', 'shipment', 'parcels', 'product');
    }

    private function start(array $f): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('delivery-proofs/test.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6WQAAAABJRU5ErkJggg=='));
        Sanctum::actingAs($f['rider']->user);
        foreach ($f['parcels'] as $parcel) {
            $this->postJson('/api/rider/deliveries/'.$parcel->id.'/start')->assertOk();
            DeliveryProof::create(['parcel_id' => $parcel->id, 'rider_id' => $f['rider']->id,
                'type' => 'PHOTO', 'file_path' => 'delivery-proofs/test.png', 'captured_at' => now()]);
        }
    }

    private function complete(array $f): void
    {
        $this->start($f);
        foreach ($f['parcels'] as $parcel) {
            $this->postJson('/api/rider/deliveries/'.$parcel->id.'/deliver')->assertOk();
        }
    }

    public function test_multiple_item_parcels_accrue_one_package_fee_only_after_last_proven_delivery(): void
    {
        $f = $this->fixture();
        $this->start($f);
        $this->postJson('/api/rider/deliveries/'.$f['parcels'][0]->id.'/deliver')->assertOk();
        $this->assertDatabaseCount('seller_delivery_charges', 0);
        $this->postJson('/api/rider/deliveries/'.$f['parcels'][1]->id.'/deliver')->assertOk();
        $this->assertDatabaseHas('seller_delivery_charges', ['seller_shipment_id' => $f['shipment']->id,
            'amount' => 50, 'logistics_amount' => 10, 'rider_amount' => 40, 'collection_status' => 'uncollected']);
        $this->assertDatabaseHas('rider_delivery_earnings', ['rider_id' => $f['rider']->id, 'amount' => 40, 'payout_status' => 'unpaid']);
        $this->postJson('/api/rider/deliveries/'.$f['parcels'][1]->id.'/deliver')->assertUnprocessable();
        DB::transaction(fn () => app(SellerShipmentFinance::class)->recognize(app(SellerShipmentFinance::class)->lockParcel($f['parcels'][1]->id)));
        $this->assertDatabaseCount('seller_delivery_charges', 1);
        $this->assertDatabaseCount('rider_delivery_earnings', 1);
        $this->assertSame('completed', $f['order']->fresh()->status);
    }

    public function test_missing_late_member_blocks_dispatch_and_cancelled_members_are_excluded(): void
    {
        $f = $this->fixture();
        $f['parcels'][1]->delete();
        Sanctum::actingAs($f['rider']->user);
        $this->postJson('/api/rider/deliveries/'.$f['parcels'][0]->id.'/start')->assertUnprocessable();
        $this->assertNull($f['shipment']->fresh()->dispatched_at);
        OrderItem::whereKey($f['parcels'][1]->order_item_id)->first()->update(['status' => 'cancelled']);
        $f['parcels'] = [$f['parcels'][0]];
        $this->complete($f);
        $this->assertDatabaseCount('seller_delivery_charges', 1);
    }

    public function test_fully_cancelled_package_cannot_dispatch_or_earn(): void
    {
        $f = $this->fixture(1);
        $f['order']->items()->first()->update(['status' => 'cancelled']);
        Sanctum::actingAs($f['rider']->user);
        $this->postJson('/api/rider/deliveries/'.$f['parcels'][0]->id.'/start')->assertUnprocessable();
        $this->assertDatabaseCount('rider_delivery_earnings', 0);
    }

    public function test_missing_proof_and_failed_member_never_recognize_package_earnings(): void
    {
        $f = $this->fixture();
        $this->start($f);
        DeliveryProof::where('parcel_id', $f['parcels'][1]->id)->delete();
        $this->postJson('/api/rider/deliveries/'.$f['parcels'][1]->id.'/deliver')->assertUnprocessable();
        $this->postJson('/api/rider/deliveries/'.$f['parcels'][1]->id.'/fail', ['reason' => 'Unavailable'])->assertOk();
        $this->postJson('/api/rider/deliveries/'.$f['parcels'][0]->id.'/deliver')->assertOk();
        $this->assertDatabaseCount('seller_delivery_charges', 0);
    }

    public function test_assignment_rejects_second_rider_for_same_package(): void
    {
        $f = $this->fixture();
        $other = Rider::create(['user_id' => $this->user('rider')->id, 'sorting_center_id' => $f['center']->id,
            'name' => 'Other rider', 'vehicle_type' => 'bicycle', 'contact' => '09170000000', 'plate_number' => '', 'approval_status' => 'approved', 'is_active' => true]);
        $other->areas()->attach($f['parcels'][1]->sorted_area_id);
        $f['parcels'][1]->update(['assigned_rider_id' => null]);
        Sanctum::actingAs($f['center']->user);
        $this->postJson('/api/sorting-center/parcels/'.$f['parcels'][1]->id.'/assign', ['rider_id' => $other->id])->assertUnprocessable();
        $this->postJson('/api/sorting-center/parcels/'.$f['parcels'][1]->id.'/assign', ['rider_id' => $f['rider']->id])->assertOk();
    }

    public function test_different_collection_location_blocks_package_dispatch(): void
    {
        $f = $this->fixture();
        $f['parcels'][1]->update(['collection_address' => 'Different pickup']);
        Sanctum::actingAs($f['rider']->user);
        $this->postJson('/api/rider/deliveries/'.$f['parcels'][0]->id.'/start')->assertUnprocessable();
    }

    public function test_accrual_failure_rolls_back_last_delivery_charge_and_earning(): void
    {
        $f = $this->fixture(1);
        $this->start($f);
        $event = 'eloquent.creating: '.RiderDeliveryEarning::class;
        Event::listen($event, fn () => throw new \RuntimeException('Simulated earning failure'));
        $this->withoutExceptionHandling();
        try {
            $this->postJson('/api/rider/deliveries/'.$f['parcels'][0]->id.'/deliver');
            $this->fail('Expected simulated persistence failure.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Simulated earning failure', $error->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertSame('OUT_FOR_DELIVERY', $f['parcels'][0]->fresh()->status);
        $this->assertNull($f['shipment']->fresh()->completed_at);
        $this->assertDatabaseCount('seller_delivery_charges', 0);
        $this->assertDatabaseCount('rider_delivery_earnings', 0);
    }

    public function test_finance_reads_are_owned_and_collection_and_payout_are_independent_idempotent_full_payments(): void
    {
        $f = $this->fixture(1);
        $this->complete($f);
        $charge = SellerDeliveryCharge::firstOrFail();
        $earning = RiderDeliveryEarning::firstOrFail();
        $this->getJson('/api/rider/earnings')->assertOk()->assertJsonPath('summary.total', '40.00')->assertJsonPath('summary.outstanding', '40.00');
        Sanctum::actingAs($this->user('rider'));
        $this->getJson('/api/rider/earnings')->assertForbidden();
        Sanctum::actingAs($this->user('seller'));
        $this->getJson('/api/seller/delivery-charges')->assertOk()->assertJsonCount(0, 'data');
        Sanctum::actingAs($f['seller']);
        $this->getJson('/api/seller/delivery-charges')->assertOk()->assertJsonPath('summary.total', '50.00');
        $this->actingAs($f['seller'], 'web')->postJson('/admin/rider-earnings/'.$earning->id.'/record-payout', [])->assertForbidden();
        $admin = $this->user('admin');
        $this->actingAs($admin, 'web');
        $payment = ['request_key' => (string) Str::uuid(), 'reference' => 'Manual payment'];
        $this->postJson('/admin/rider-earnings/'.$earning->id.'/record-payout', $payment + ['amount' => '20.00'])->assertUnprocessable();
        $this->postJson('/admin/rider-earnings/'.$earning->id.'/record-payout', $payment)->assertOk();
        $this->postJson('/admin/rider-earnings/'.$earning->id.'/record-payout', $payment)->assertOk();
        $this->assertSame('uncollected', $charge->fresh()->collection_status);
        $this->assertSame('paid', $earning->fresh()->payout_status);
        $this->postJson('/admin/rider-earnings/'.$earning->id.'/record-payout', ['request_key' => (string) Str::uuid(), 'reference' => 'Again'])->assertConflict();
        $this->postJson('/admin/delivery-charges/'.$charge->id.'/record-collection', ['request_key' => (string) Str::uuid(), 'reference' => 'Seller receipt'])->assertOk();
        $this->assertSame($admin->id, $charge->fresh()->recorded_by);
        $this->getJson('/admin/delivery-finance')->assertOk()->assertJsonPath('charges.summary.collected', '50.00')
            ->assertJsonPath('charges.summary.logistics_earned', '10.00');
    }

    public function test_checkout_groups_seller_items_and_keeps_express_metadata_without_buyer_fees_or_voucher_erosion(): void
    {
        $buyer = $this->user('buyer');
        $seller = $this->user('seller');
        $otherSeller = $this->user('seller');
        $legacy = Order::create(['user_id' => $buyer->id, 'order_number' => Str::uuid(), 'total' => 220, 'shipping_fee' => 120,
            'shipping_address' => 'Historical', 'payment_method' => 'cod', 'shipping_method' => 'express', 'status' => 'pending']);
        foreach (['express', 'standard'] as $method) {
            foreach ([$seller, $seller, $otherSeller] as $owner) {
                $product = $this->product($owner);
                CartItem::create(['user_id' => $buyer->id, 'product_id' => $product->id, 'quantity' => 1]);
            }
            $voucher = Voucher::create(['seller_id' => $seller->id, 'code' => 'TEST-'.Str::random(6), 'type' => 'fixed', 'value' => 50, 'is_active' => true]);
            $response = $this->actingAs($buyer, 'web')->post(route('buyer.orders.store'), ['shipping_address' => 'Destination', 'payment_method' => 'cod',
                'shipping_method' => $method, 'voucher_code' => $voucher->code,
                'selected_items' => $buyer->cartItems()->pluck('id')->all()]);
            $order = Order::latest('id')->firstOrFail();
            $response->assertRedirect(route('buyer.orders.show', $order));
            $this->assertNotSame($legacy->id, $order->id);
            $this->assertSame('seller', $order->delivery_fee_payer);
            $this->assertSame('0.00', $order->shipping_fee);
            $this->assertSame('0.00', $order->shipping_discount);
            $this->assertSame('50.00', $order->discount);
            $this->assertSame('250.00', $order->total);
            $this->assertSame($method, $order->shipping_method);
            $this->assertSame(2, SellerShipment::where('order_id', $order->id)->count());
            $this->assertSame(2, $order->items()->distinct()->count('seller_shipment_id'));
            DB::transaction(fn () => app(SellerShipmentFinance::class)->createMembership($order));
            $this->assertSame(2, SellerShipment::where('order_id', $order->id)->count());
        }
        $this->assertSame('220.00', $legacy->fresh()->total);
        $this->assertSame('120.00', $legacy->fresh()->shipping_fee);
        $this->assertSame('buyer', $legacy->fresh()->delivery_fee_payer);
        $this->assertNull($legacy->fresh()->shipping_discount);
    }

    public function test_buyer_cannot_confirm_new_shipment_ahead_of_proof_backed_courier_delivery(): void
    {
        $f = $this->fixture(1);
        $item = $f['order']->items()->firstOrFail();
        $this->actingAs($f['buyer'], 'web')->patchJson('/buyer/order-items/'.$item->id.'/confirm-delivery')->assertUnprocessable();
        $this->assertSame('shipped', $item->fresh()->status);
        $this->assertDatabaseCount('seller_delivery_charges', 0);
        $this->complete($f);
        $this->assertDatabaseCount('seller_delivery_charges', 1);
    }

    public function test_seller_cancel_restores_stock_once_and_excludes_member_before_dispatch(): void
    {
        $f = $this->fixture(1);
        $item = $f['order']->items()->firstOrFail();
        $item->update(['status' => 'packed']);
        $stock = $f['product']->fresh()->stock;
        $this->actingAs($f['seller'], 'web')->patchJson(route('seller.orders.cancel', $item), ['reason' => 'Cancelled'])->assertRedirect();
        $this->assertSame('cancelled', $item->fresh()->status);
        $this->assertSame($stock + 1, $f['product']->fresh()->stock);
        $this->patchJson(route('seller.orders.cancel', $item), ['reason' => 'Repeat'])->assertUnprocessable();
        $this->assertSame($stock + 1, $f['product']->fresh()->stock);
        $this->assertDatabaseCount('seller_delivery_charges', 0);
    }

    public function test_legacy_order_membership_is_not_backfilled_and_pickup_only_does_not_earn(): void
    {
        $f = $this->fixture(1);
        $f['parcels'][0]->update(['status' => 'PLACED', 'pickup_rider_id' => $f['rider']->id]);
        Sanctum::actingAs($f['rider']->user);
        $this->postJson('/api/rider/pickups/'.$f['parcels'][0]->id.'/confirm')->assertOk();
        $this->assertDatabaseCount('seller_delivery_charges', 0);
        $legacy = Order::create(['user_id' => $f['buyer']->id, 'order_number' => Str::uuid(), 'total' => 150,
            'shipping_address' => 'Legacy', 'payment_method' => 'cod', 'status' => 'pending']);
        $legacy->items()->create(['product_id' => $f['product']->id, 'product_name' => 'Legacy', 'quantity' => 1, 'price' => 100]);
        DB::transaction(fn () => app(SellerShipmentFinance::class)->createMembership($legacy));
        $this->assertNull($legacy->items()->first()->seller_shipment_id);
        $this->assertSame(0, SellerShipment::where('order_id', $legacy->id)->count());
    }

    public function test_unique_database_constraint_prevents_second_charge_for_same_shipment(): void
    {
        $f = $this->fixture(1);
        $this->complete($f);
        $data = SellerDeliveryCharge::firstOrFail()->getAttributes();
        unset($data['id']);
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        SellerDeliveryCharge::create($data);
    }

    public function test_recorded_financial_facts_cannot_be_edited(): void
    {
        $f = $this->fixture(1);
        $this->complete($f);
        $this->expectException(\LogicException::class);
        RiderDeliveryEarning::firstOrFail()->update(['amount' => '20.00']);
    }

    public function test_unique_database_constraint_prevents_second_rider_earning_for_same_charge(): void
    {
        $f = $this->fixture(1);
        $this->complete($f);
        $data = RiderDeliveryEarning::firstOrFail()->getAttributes();
        unset($data['id']);
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        RiderDeliveryEarning::create($data);
    }

    public function test_owned_read_totals_and_date_pagination_filters_exclude_other_riders(): void
    {
        $first = $this->fixture(1);
        $this->complete($first);
        $this->complete($this->fixture(1));
        Sanctum::actingAs($first['rider']->user);
        $this->getJson('/api/rider/earnings?per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('summary.total', '40.00');
        $this->getJson('/api/rider/earnings?to='.now()->toDateString())->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/rider/earnings?from='.now()->addDay()->toDateString())->assertOk()->assertJsonPath('summary.total', '0.00');
        $this->getJson('/api/rider/earnings?per_page=101')->assertUnprocessable();
        $this->getJson('/api/rider/earnings?page=0')->assertUnprocessable();
    }

    public function test_later_return_creates_no_additional_charge_or_earning(): void
    {
        $f = $this->fixture(1);
        $this->complete($f);
        $item = $f['order']->items()->firstOrFail();
        $this->actingAs($f['buyer'], 'web')->post('/buyer/order-items/'.$item->id.'/return', ['type' => 'refund_only', 'reason' => 'Changed mind'])->assertRedirect();
        $this->assertDatabaseCount('order_return_requests', 1);
        $this->assertDatabaseCount('seller_delivery_charges', 1);
        $this->assertDatabaseCount('rider_delivery_earnings', 1);
        $this->assertSame('40.00', RiderDeliveryEarning::firstOrFail()->amount);
    }

    public function test_logistics_and_other_roles_cannot_read_admin_finance_or_record_settlements(): void
    {
        $f = $this->fixture(1);
        $this->complete($f);
        $charge = SellerDeliveryCharge::firstOrFail();
        $earning = RiderDeliveryEarning::firstOrFail();
        $pendingAdmin = $this->user('admin');
        $pendingAdmin->update(['status' => 'pending']);
        foreach ([$f['center']->user, $f['rider']->user, $f['seller'], $f['buyer'], $pendingAdmin] as $user) {
            $this->actingAs($user, 'web');
            $this->getJson('/admin/delivery-finance')->assertForbidden();
            $input = ['request_key' => (string) Str::uuid(), 'reference' => 'Unauthorized'];
            $this->postJson('/admin/delivery-charges/'.$charge->id.'/record-collection', $input)->assertForbidden();
            $this->postJson('/admin/rider-earnings/'.$earning->id.'/record-payout', $input)->assertForbidden();
        }
        $this->assertSame('uncollected', $charge->fresh()->collection_status);
        $this->assertSame('unpaid', $earning->fresh()->payout_status);
        $this->app['auth']->forgetGuards();
        $this->getJson('/admin/delivery-finance')->assertUnauthorized();
    }

    public function test_admin_manual_payout_enforces_web_csrf_with_test_bypass_disabled(): void
    {
        $f = $this->fixture(1);
        $this->complete($f);
        $earning = RiderDeliveryEarning::firstOrFail();
        $this->app->bind(\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class, fn ($app) =>
            new class($app, $app['encrypter']) extends \Illuminate\Foundation\Http\Middleware\PreventRequestForgery {
                protected function runningUnitTests()
                {
                    return false;
                }
            });
        $token = Str::random(40);
        $this->actingAs($this->user('admin'), 'web')->withSession(['_token' => $token]);
        $input = ['request_key' => (string) Str::uuid(), 'reference' => 'Manual receipt'];
        $url = '/admin/rider-earnings/'.$earning->id.'/record-payout';
        $this->postJson($url, $input)->assertStatus(419);
        $this->assertSame('unpaid', $earning->fresh()->payout_status);
        $this->withHeader('X-CSRF-TOKEN', $token)->postJson($url, $input)->assertOk();
        $this->assertSame('paid', $earning->fresh()->payout_status);
    }

    public function test_dispatched_members_cannot_be_cancelled_to_manipulate_completion(): void
    {
        $f = $this->fixture();
        $this->start($f);
        $item = $f['order']->items()->firstOrFail();
        try {
            DB::transaction(fn () => $item->update(['status' => 'cancelled']));
            $this->fail('Dispatched membership must be frozen.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {
            $this->assertSame(422, $error->getStatusCode());
        }
        $this->assertSame('out_for_delivery', $item->fresh()->status);
        $this->assertDatabaseCount('seller_delivery_charges', 0);
    }

    public function test_manual_settlement_key_cannot_be_reused_for_a_different_earning(): void
    {
        $this->complete($this->fixture(1));
        $this->complete($this->fixture(1));
        $rows = RiderDeliveryEarning::orderBy('id')->get();
        $this->actingAs($this->user('admin'), 'web');
        $input = ['request_key' => (string) Str::uuid(), 'reference' => 'One manual payment'];
        $this->postJson('/admin/rider-earnings/'.$rows[0]->id.'/record-payout', $input)->assertOk();
        $this->postJson('/admin/rider-earnings/'.$rows[1]->id.'/record-payout', $input)->assertConflict();
        $this->assertSame('unpaid', $rows[1]->fresh()->payout_status);
    }
}
