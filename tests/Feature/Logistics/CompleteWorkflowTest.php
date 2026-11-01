<?php

namespace Tests\Feature\Logistics;

use App\Models\{Area, Category, Order, OrderItem, Parcel, PickupRequest, Product, Rider, SortingCenter, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage};
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompleteWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected SortingCenter $center;
    protected Rider $pickupRider;
    protected Rider $deliveryRider;
    protected Rider $outsider;
    protected Area $area;
    protected OrderItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Storage::fake('local');
        Storage::fake('public');
        $staff = User::factory()->create(['name' => 'Synthetic Logistics', 'usertype' => 'sorting_center', 'status' => 'approved']);
        $this->center = SortingCenter::create(['user_id' => $staff->id, 'business_name' => 'Synthetic Center', 'approval_status' => 'approved']);
        foreach (['pickupRider' => 'Synthetic Pickup Rider', 'deliveryRider' => 'Synthetic Delivery Rider', 'outsider' => 'Synthetic Other Rider'] as $key => $name) {
            $user = User::factory()->create(['name' => $name, 'usertype' => 'rider', 'status' => 'approved']);
            $this->$key = Rider::create(['user_id' => $user->id, 'sorting_center_id' => $this->center->id,
                'name' => $name, 'contact' => '09170000000', 'vehicle_type' => 'bicycle', 'plate_number' => '',
                'approval_status' => 'approved', 'is_active' => true]);
        }
        $this->area = Area::create(['sorting_center_id' => $this->center->id, 'name' => 'Cebu City']);
        $seller = User::factory()->create(['name' => 'Synthetic Seller', 'usertype' => 'seller', 'status' => 'approved', 'address' => 'Synthetic Seller collection']);
        $buyer = User::factory()->create(['name' => 'Synthetic Buyer', 'usertype' => 'buyer', 'status' => 'approved']);
        $category = Category::create(['name' => 'E2E', 'slug' => 'e2e', 'is_active' => true]);
        $product = Product::create(['seller_id' => $seller->id, 'category_id' => $category->id, 'name' => 'Synthetic parcel',
            'slug' => 'e2e-parcel', 'price' => 100, 'stock' => 5, 'status' => 'approved']);
        $order = Order::create(['user_id' => $buyer->id, 'order_number' => 'E2E-ISOLATED', 'subtotal' => 100,
            'total' => 100, 'shipping_address' => 'Synthetic Buyer address, Cebu City', 'payment_method' => 'cod', 'status' => 'packed']);
        $this->item = $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name,
            'price' => 100, 'quantity' => 1, 'status' => 'packed']);
    }

    public function test_same_parcel_completes_seller_pickup_two_rider_handoff_and_buyer_delivery(): void
    {
        // Start at the Seller's real ready action, rather than inserting a pickup/parcel fixture.
        $this->actingAs($this->item->product->seller, 'web')->patch(route('seller.orders.ready-for-pickup', $this->item))->assertRedirect();
        $parcel = $this->item->parcel()->firstOrFail();
        $pickup = $parcel->pickupRequests()->firstOrFail();
        $this->assertSame('pending', $pickup->status);
        $this->assertSame('PLACED', $parcel->status);
        $this->assertSame($this->item->product->seller_id, $parcel->seller_id);
        $this->assertSame($this->item->order->user_id, $parcel->buyer_id);

        Sanctum::actingAs($this->center->user);
        $this->visible($parcel, 'PLACED');
        $this->postJson("/api/sorting-center/pickup-requests/{$pickup->id}/confirm")->assertOk();
        $this->postJson("/api/sorting-center/pickup-requests/{$pickup->id}/confirm")->assertUnprocessable();
        $this->visible($parcel, 'PLACED');
        // Pickup allocation uses the existing Rider claim endpoint; Logistics has no separate preassignment API.
        Sanctum::actingAs($this->pickupRider->user);
        $this->getJson('/api/rider/pickups/available')->assertOk()->assertJsonPath('data.0.id', $parcel->id);
        $this->postJson("/api/rider/pickups/{$parcel->id}/accept")->assertOk();
        Sanctum::actingAs($this->outsider->user);
        $this->postJson("/api/rider/pickups/{$parcel->id}/accept")->assertStatus(409);
        $this->postJson("/api/rider/pickups/{$parcel->id}/confirm")->assertForbidden();
        $this->getJson('/api/rider/pickups/mine?view=transit')->assertOk()->assertJsonCount(0, 'data');
        Sanctum::actingAs($this->pickupRider->user);
        $this->postJson("/api/rider/pickups/{$parcel->id}/confirm")->assertOk();
        $this->postJson("/api/rider/pickups/{$parcel->id}/confirm")->assertUnprocessable();
        $this->getJson('/api/rider/pickups/mine?view=transit')->assertOk()->assertJsonPath('data.0.id', $parcel->id)->assertJsonPath('data.0.status', 'PICKED_UP');
        $this->postJson('/api/sorting-center/parcels/mark-arrived', ['order_id' => $parcel->order_id])->assertForbidden();
        Sanctum::actingAs($this->center->user);
        $this->visible($parcel, 'PICKED_UP');
        $this->postJson("/api/sorting-center/parcels/{$parcel->id}/assign", ['rider_id' => $this->deliveryRider->id])->assertUnprocessable();
        $this->postJson('/api/sorting-center/parcels/mark-arrived', ['order_id' => $parcel->order_id])->assertOk();
        $this->postJson('/api/sorting-center/parcels/mark-arrived', ['order_id' => $parcel->order_id])->assertUnprocessable();
        $this->visible($parcel, 'AT_SORTING_CENTER');
        Sanctum::actingAs($this->pickupRider->user);
        $this->getJson('/api/rider/pickups/mine?view=transit')->assertJsonCount(0, 'data');
        $this->getJson('/api/rider/pickups/mine?view=history')->assertJsonPath('data.0.id', $parcel->id)->assertJsonPath('data.0.status', 'AT_SORTING_CENTER');

        Sanctum::actingAs($this->center->user);
        $this->postJson("/api/sorting-center/parcels/{$parcel->id}/sort", ['area_id' => $this->area->id])->assertOk();
        $this->getJson("/api/sorting-center/parcels/{$parcel->id}/eligible-riders")->assertOk()->assertJsonCount(0, 'data');
        $coverage = "/api/sorting-center/riders/{$this->deliveryRider->id}/delivery-areas";
        $this->getJson($coverage)->assertOk()->assertJsonCount(0, 'data.area_ids');
        $this->putJson($coverage, ['area_ids' => [$this->area->id]])->assertOk();
        $this->getJson("/api/sorting-center/parcels/{$parcel->id}/eligible-riders")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->deliveryRider->id);
        $this->putJson($coverage, ['area_ids' => []])->assertOk();
        $this->postJson("/api/sorting-center/parcels/{$parcel->id}/assign", ['rider_id' => $this->deliveryRider->id])->assertUnprocessable();
        $this->putJson($coverage, ['area_ids' => [$this->area->id]])->assertOk();
        $this->postJson("/api/sorting-center/parcels/{$parcel->id}/assign", ['rider_id' => $this->deliveryRider->id])->assertOk();
        $this->postJson("/api/sorting-center/parcels/{$parcel->id}/assign", ['rider_id' => $this->deliveryRider->id])->assertUnprocessable();
        Sanctum::actingAs($this->pickupRider->user);
        $this->postJson("/api/rider/deliveries/{$parcel->id}/start")->assertForbidden();
        Sanctum::actingAs($this->deliveryRider->user);
        $this->getJson('/api/rider/deliveries')->assertOk()->assertJsonPath('data.0.id', $parcel->id);
        $this->postJson("/api/rider/deliveries/{$parcel->id}/start")->assertOk();
        $this->postJson("/api/rider/deliveries/{$parcel->id}/deliver")->assertUnprocessable();
        $this->postJson("/api/rider/deliveries/{$parcel->id}/upload-proof", ['file' => UploadedFile::fake()->image('proof.jpg'), 'type' => 'PHOTO'])->assertCreated();
        $this->postJson("/api/rider/deliveries/{$parcel->id}/deliver", ['otp' => $this->deliveryOtp($parcel->id)])->assertOk();
        $this->postJson("/api/rider/deliveries/{$parcel->id}/deliver")->assertUnprocessable();
        $this->assertFinalViews($parcel);
    }

    protected function visible(Parcel $parcel, string $status): void
    {
        $this->getJson('/api/sorting-center/pickup-requests?status=all')->assertOk()
            ->assertJsonPath('data.data.0.parcel.id', $parcel->id)->assertJsonPath('data.data.0.parcel.status', $status);
    }

    protected function assertFinalViews(Parcel $parcel): void
    {
        $parcel->refresh();
        $this->assertSame('DELIVERED', $parcel->status);
        $this->assertSame($this->pickupRider->id, $parcel->pickup_rider_id);
        $this->assertSame($this->deliveryRider->id, $parcel->assigned_rider_id);
        $this->assertSame('delivered', $this->item->fresh()->status);
        $this->assertSame('delivered', $this->item->order->fresh()->status);
        Sanctum::actingAs($this->center->user);
        $this->visible($parcel, 'DELIVERED');
        $row = $this->getJson('/api/sorting-center/pickup-requests?status=all')->assertOk()->json('data.data.0');
        $this->assertCount(9, $row['timeline']);
        foreach ($row['timeline'] as $event) $this->assertNotNull($event['occurred_at'], $event['label']);
        Sanctum::actingAs($this->pickupRider->user);
        $this->getJson('/api/rider/pickups/mine?view=history')->assertOk()->assertJsonPath('data.0.id', $parcel->id)->assertJsonPath('data.0.status', 'DELIVERED');
        Sanctum::actingAs($this->deliveryRider->user);
        $this->getJson('/api/rider/deliveries?view=history')->assertOk()->assertJsonPath('data.0.id', $parcel->id)->assertJsonPath('data.0.status', 'DELIVERED');
        $this->actingAs($this->item->product->seller, 'web')->get(route('seller.order.details', $this->item))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Seller/OrderDetails')->where('orderItem.status', 'delivered'));
        $this->actingAs($this->item->order->user, 'web')->get(route('buyer.orders.show', $this->item->order))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Buyer/OrderDetails')->where('order.items.0.status', 'delivered'));
        $this->assertDatabaseCount('parcels', 1);
        $this->assertDatabaseCount('pickup_requests', 1);
        $this->assertDatabaseCount('delivery_proofs', 1);
        Storage::disk('local')->assertExists($parcel->deliveryProofs()->firstOrFail()->file_path);
        $this->assertSame(100.0, (float) $this->item->order->fresh()->total);
    }
}
