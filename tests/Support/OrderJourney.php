<?php

namespace Tests\Support;

use App\Models\{Area, CartItem, Category, Order, OrderItem, Parcel, Product, Rider, SortingCenter, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Auth, DB, Storage};
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Synthetic fixtures shared by HTTP and browser checks; never seed a real database. */
abstract class OrderJourney extends TestCase
{
    use RefreshDatabase;

    protected User $buyer;
    protected User $seller;
    protected SortingCenter $center;
    protected Rider $pickupRider;
    protected Rider $deliveryRider;
    protected Area $area;
    protected Product $product;
    protected CartItem $cart;
    protected OrderItem $item;
    protected Order $order;
    protected const ADDRESS = '45 Kalayaan Ave., Brgy. Central, Quezon City, Metro Manila, NCR 1100';

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Storage::fake('local');
        Storage::fake('public');
        $staff = User::factory()->create(['name' => 'Synthetic Logistics', 'usertype' => 'sorting_center', 'status' => 'approved']);
        $this->center = SortingCenter::create(['user_id' => $staff->id, 'business_name' => 'Synthetic Center', 'approval_status' => 'approved']);
        foreach (['pickupRider' => 'Synthetic Pickup Rider', 'deliveryRider' => 'Synthetic Delivery Rider'] as $key => $name) {
            $user = User::factory()->create(['name' => $name, 'usertype' => 'rider', 'status' => 'approved']);
            $this->$key = Rider::create(['user_id' => $user->id, 'sorting_center_id' => $this->center->id,
                'name' => $name, 'contact' => '09170000000', 'vehicle_type' => 'bicycle', 'plate_number' => '',
                'approval_status' => 'approved', 'is_active' => true]);
        }
        $this->area = Area::create(['sorting_center_id' => $this->center->id, 'name' => 'Quezon City', 'city_code' => '1381300000']);
        $this->seller = User::factory()->create(['name' => 'Synthetic Seller', 'usertype' => 'seller', 'status' => 'approved',
            'store_name' => 'Synthetic Store', 'address' => self::ADDRESS, 'contact_no' => '09170000001']);
        $this->buyer = User::factory()->create(['name' => 'Synthetic Buyer', 'usertype' => 'buyer', 'status' => 'approved', 'contact_no' => '09170000002']);
        $this->buyer->addresses()->create(['label' => 'Home', 'address' => self::ADDRESS, 'is_default' => true]);
        $category = Category::create(['name' => 'Journey', 'slug' => 'journey', 'is_active' => true]);
        $this->product = Product::create(['seller_id' => $this->seller->id, 'category_id' => $category->id, 'name' => 'Synthetic journey parcel',
            'slug' => 'journey-parcel', 'price' => 125, 'stock' => 10, 'weight' => 0.5, 'status' => 'approved']);
        $this->cart = CartItem::create(['user_id' => $this->buyer->id, 'product_id' => $this->product->id, 'quantity' => 2]);
    }

    protected function checkout(string $shipping = 'standard'): void
    {
        $this->actingAs($this->buyer, 'web')->post(route('buyer.orders.store'), [
            'shipping_address' => self::ADDRESS, 'payment_method' => 'cod', 'shipping_method' => $shipping,
            'selected_items' => [$this->cart->id], 'expected_shipping_fee' => $shipping === 'pickup' ? 0 : 50,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->order = Order::sole();
        $this->item = $this->order->items()->sole();
        $this->assertSame('pending', $this->item->status);
        $this->assertSame(8, $this->product->fresh()->stock);
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertSame($shipping === 'pickup' ? '250.00' : '300.00', $this->order->total);
    }

    protected function ready(): void
    {
        foreach (['process', 'pack', 'ready-for-pickup'] as $action) {
            $this->actingAs($this->seller, 'web')->patch(route('seller.orders.'.$action, $this->item))
                ->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertSame('ready_for_pickup', $this->item->fresh()->status);
    }

    protected function apiAs(User $user): void
    {
        Auth::forgetGuards();
        Sanctum::actingAs($user);
    }

    protected function dispatchToDelivery(): Parcel
    {
        $parcel = $this->item->parcel()->sole();
        $pickup = $parcel->pickupRequests()->sole();
        $this->apiAs($this->center->user);
        $this->postJson("/api/sorting-center/pickup-requests/{$pickup->id}/confirm")->assertOk();
        $this->apiAs($this->pickupRider->user);
        $this->postJson('/api/rider/presence', ['action' => 'go_online'])->assertOk();
        $this->apiAs($this->center->user);
        $this->postJson("/api/sorting-center/pickup-dispatch/{$parcel->id}/assign", ['rider_id' => $this->pickupRider->id])->assertOk();
        $this->apiAs($this->pickupRider->user);
        $this->postJson("/api/rider/pickups/{$parcel->id}/confirm")->assertUnprocessable();
        $this->postJson("/api/rider/pickups/{$parcel->id}/acknowledge")->assertOk();
        $this->postJson("/api/rider/pickups/{$parcel->id}/confirm")->assertOk();
        $this->postJson("/api/rider/pickups/{$parcel->id}/confirm")->assertUnprocessable();
        $this->apiAs($this->center->user);
        $this->postJson('/api/sorting-center/parcels/mark-arrived', ['order_id' => $parcel->order_id])->assertOk();
        $this->postJson("/api/sorting-center/parcels/{$parcel->id}/sort", ['area_id' => $this->area->id])->assertOk();
        $this->putJson("/api/sorting-center/riders/{$this->deliveryRider->id}/delivery-areas", ['area_ids' => [$this->area->id]])->assertOk();
        $this->apiAs($this->deliveryRider->user);
        $this->postJson('/api/rider/presence', ['action' => 'go_online'])->assertOk();
        $this->apiAs($this->center->user);
        $this->postJson("/api/sorting-center/parcels/{$parcel->id}/assign", ['rider_id' => $this->deliveryRider->id])->assertOk();
        $this->apiAs($this->pickupRider->user);
        $this->postJson("/api/rider/deliveries/{$parcel->id}/start")->assertForbidden();
        $this->apiAs($this->deliveryRider->user);
        $this->postJson("/api/rider/deliveries/{$parcel->id}/start")->assertOk();
        return $parcel->refresh();
    }
}
