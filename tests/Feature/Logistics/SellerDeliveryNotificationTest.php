<?php

namespace Tests\Feature\Logistics;

use App\Models\{BuyerNotification, Category, DeliveryProof, Order, Parcel, Product, Rider, SellerNotification, User};
use App\Services\SellerParcelDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Event, Storage};
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerDeliveryNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Storage::fake('local');
        $buyer = User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']);
        $riderUser = User::factory()->create(['usertype' => 'rider', 'status' => 'approved']);
        $rider = Rider::create(['user_id' => $riderUser->id, 'name' => 'Rider', 'contact' => '09170000001',
            'vehicle_type' => 'bicycle', 'plate_number' => '', 'approval_status' => 'approved', 'is_active' => true]);
        $order = Order::create(['user_id' => $buyer->id, 'order_number' => Str::uuid(), 'shipping_address' => 'Order destination',
            'subtotal' => 200, 'total' => 200, 'payment_method' => 'cod', 'status' => 'out_for_delivery']);
        $category = Category::create(['name' => 'Test', 'slug' => Str::uuid(), 'is_active' => true]);
        $sellers = $items = $parcels = [];
        for ($i = 0; $i < 2; $i++) {
            $seller = User::factory()->create(['usertype' => 'seller', 'status' => 'approved']);
            $product = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'name' => 'Item',
                'slug' => Str::uuid(), 'price' => 100, 'stock' => 2, 'status' => 'approved']);
            $item = $order->items()->create(['product_id' => $product->id, 'product_name' => 'Item', 'price' => 100, 'quantity' => 1, 'status' => 'out_for_delivery']);
            $parcel = Parcel::create(['order_id' => 'ITEM-'.$item->id, 'order_item_id' => $item->id, 'buyer_id' => $buyer->id,
                'seller_id' => $seller->id, 'assigned_rider_id' => $rider->id, 'status' => 'OUT_FOR_DELIVERY',
                'delivery_address' => $order->shipping_address, 'delivery_province' => '', 'delivery_municipality' => '', 'delivery_barangay' => '']);
            $sellers[] = $seller; $items[] = $item; $parcels[] = $parcel;
        }
        Sanctum::actingAs($riderUser);
        return compact('buyer', 'rider', 'riderUser', 'order', 'sellers', 'items', 'parcels');
    }

    private function upload(Parcel $parcel): DeliveryProof
    {
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6WQAAAABJRU5ErkJggg==');
        $response = $this->postJson('/api/rider/deliveries/'.$parcel->id.'/upload-proof', [
            'file' => UploadedFile::fake()->createWithContent('proof.png', $image), 'type' => 'PHOTO',
        ])->assertCreated();
        return DeliveryProof::findOrFail($response->json('data.id'));
    }

    public function test_delivery_notifies_only_its_seller_once_and_preserves_buyer_item_history(): void
    {
        $f = $this->fixture();
        $parcel = $f['parcels'][0];
        $this->upload($parcel);
        $path = '/api/rider/deliveries/'.$parcel->id.'/deliver';
        $this->postJson($path)->assertOk();
        $this->postJson($path)->assertUnprocessable();
        DB::transaction(fn () => app(SellerParcelDelivery::class)->notifyDelivered($parcel->fresh()));
        $this->assertDatabaseCount('seller_notifications', 1);
        $this->assertDatabaseHas('seller_notifications', ['user_id' => $f['sellers'][0]->id, 'type' => 'delivery',
            'url' => route('seller.order.details', ['orderItem' => $f['items'][0]->id], false)]);
        $this->assertSame(0, SellerNotification::where('user_id', $f['sellers'][1]->id)->count());
        $this->assertSame(1, BuyerNotification::where('user_id', $f['buyer']->id)->count());
        $this->assertSame('delivered', $f['items'][0]->fresh()->status);
        $this->assertSame('out_for_delivery', $f['items'][1]->fresh()->status);
        $this->assertSame('out_for_delivery', $f['order']->fresh()->status);
        $this->assertDatabaseCount('order_item_status_histories', 1);
    }

    public function test_own_seller_can_view_private_photo_but_another_seller_cannot_even_in_same_order(): void
    {
        $f = $this->fixture();
        $proof = $this->upload($f['parcels'][0]);
        $url = route('seller.delivery-proofs.file', ['orderItem' => $f['items'][0]->id, 'proof' => $proof->id], false);
        $this->actingAs($f['sellers'][0], 'web');
        $this->get($url)->assertNotFound();
        Sanctum::actingAs($f['riderUser']);
        $this->postJson('/api/rider/deliveries/'.$f['parcels'][0]->id.'/deliver')->assertOk();
        $url = route('seller.delivery-proofs.file', ['orderItem' => $f['items'][0]->id, 'proof' => $proof->id], false);
        $this->actingAs($f['sellers'][0], 'web');
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('seller.order.details', $f['items'][0]))->assertInertia(fn (Assert $page) => $page
            ->component('Seller/OrderDetails')->has('deliveryDetails.proofs', 1)
            ->where('deliveryDetails.proofs.0.file_url', $url)->missing('deliveryDetails.proofs.0.file_path')
            ->missing('orderItem.parcel.order_item'));
        $this->actingAs($f['sellers'][1], 'web');
        $this->get($url)->assertNotFound();
        $this->get(route('seller.delivery-proofs.file', ['orderItem' => $f['items'][1]->id, 'proof' => $proof->id]))->assertNotFound();
        Storage::disk('local')->delete($proof->file_path);
        $this->actingAs($f['sellers'][0], 'web');
        $this->get($url)->assertNotFound();
    }

    public function test_notification_failure_rolls_back_delivery_buyer_history_and_financial_recognition(): void
    {
        $f = $this->fixture();
        $this->upload($f['parcels'][0]);
        $event = 'eloquent.creating: '.SellerNotification::class;
        Event::listen($event, fn () => throw new \RuntimeException('Simulated notification failure'));
        try {
            $this->postJson('/api/rider/deliveries/'.$f['parcels'][0]->id.'/deliver')->assertServerError();
            $this->assertSame('OUT_FOR_DELIVERY', $f['parcels'][0]->fresh()->status);
            $this->assertSame('out_for_delivery', $f['items'][0]->fresh()->status);
            $this->assertDatabaseCount('buyer_notifications', 0);
            $this->assertDatabaseCount('seller_notifications', 0);
            $this->assertDatabaseCount('order_item_status_histories', 0);
            $this->assertDatabaseCount('rider_delivery_earnings', 0);
        } finally { Event::forget($event); }
    }

    public function test_unverified_legacy_links_do_not_guess_or_notify_a_seller(): void
    {
        $f = $this->fixture();
        $parcel = $f['parcels'][0];
        $parcel->update(['seller_id' => $f['sellers'][1]->id]);
        $this->upload($parcel);
        $this->postJson('/api/rider/deliveries/'.$parcel->id.'/deliver')->assertOk();
        $this->assertDatabaseCount('seller_notifications', 0);
        $this->actingAs($f['sellers'][0], 'web');
        $this->get(route('seller.order.details', $f['items'][0]))->assertInertia(fn (Assert $page) => $page
            ->where('deliveryLinkUnverified', true)->where('deliveryDetails', null)->missing('orderItem.parcel'));
        Sanctum::actingAs($f['riderUser']);
        $legacy = $parcel->replicate(['order_id', 'order_item_id']);
        $legacy->order_id = 'ITEM-999999';
        $legacy->order_item_id = null;
        $legacy->status = 'OUT_FOR_DELIVERY';
        $legacy->save();
        $this->upload($legacy);
        $this->postJson('/api/rider/deliveries/'.$legacy->id.'/deliver')->assertOk();
        $this->assertDatabaseCount('seller_notifications', 0);
    }
}
