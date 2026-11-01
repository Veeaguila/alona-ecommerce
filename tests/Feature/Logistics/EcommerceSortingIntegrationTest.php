<?php

namespace Tests\Feature\Logistics;

use App\Models\Area;
use App\Models\BuyerNotification;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Rider;
use App\Models\SortingCenter;
use App\Models\User;
use App\Services\OrderItemPickup;
use App\Services\PhilippineAddressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EcommerceSortingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function item(string $status = 'packed'): OrderItem
    {
        $seller = User::factory()->create(['status' => 'approved', 'usertype' => 'seller', 'address' => 'Seller collection location']);
        $buyer = User::factory()->create(['status' => 'approved', 'usertype' => 'buyer', 'address' => 'Different profile address']);
        $category = Category::create(['name' => 'Test', 'slug' => 'test-'.uniqid(), 'is_active' => true]);
        $product = Product::create([
            'seller_id' => $seller->id, 'category_id' => $category->id,
            'name' => 'Test item', 'slug' => 'test-'.uniqid(), 'price' => 100, 'stock' => 10, 'status' => 'approved',
        ]);
        $order = Order::create([
            'user_id' => $buyer->id, 'order_number' => 'TEST-'.uniqid(),
            'subtotal' => 100, 'total' => 100, 'shipping_address' => '123 Recipient Street, Cebu City',
            'payment_method' => 'cod', 'status' => $status,
        ]);

        return $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'price' => 100, 'quantity' => 1, 'status' => $status,
        ]);
    }

    private function center(): SortingCenter
    {
        return SortingCenter::create([
            'user_id' => User::factory()->create(['status' => 'approved', 'usertype' => 'sorting_center'])->id,
            'business_name' => 'Test center', 'approval_status' => 'approved',
        ]);
    }

    public function test_seller_ready_action_creates_one_owned_item_pickup_and_preserves_addresses(): void
    {
        $item = $this->item();
        $this->actingAs($item->product->seller)
            ->patch(route('seller.orders.ready-for-pickup', $item))->assertRedirect();
        $parcel = $item->parcel()->firstOrFail();
        $this->assertSame($item->order->user_id, $parcel->buyer_id);
        $this->assertSame($item->product->seller_id, $parcel->seller_id);
        $this->assertSame($item->order->shipping_address, $parcel->delivery_address);
        $this->assertSame('Seller collection location', $parcel->collection_address);
        $this->assertSame('PLACED', $parcel->status);
        $this->assertSame('pending', $parcel->pickupRequests()->first()->status);
        $this->assertSame('ready_for_pickup', $item->fresh()->status);
        $this->assertSame($parcel->order_id, $item->fresh()->tracking_number);
        $this->assertSame('Alona Express', $item->fresh()->courier_name);
        $this->assertSame($parcel->id, app(OrderItemPickup::class)->create($item)->id);
        $this->assertDatabaseCount('parcels', 1);
        $this->assertDatabaseCount('pickup_requests', 1);
        $this->assertDatabaseCount('order_item_status_histories', 1);
    }

    public function test_seller_api_derives_buyer_and_blocks_other_sellers_and_unready_items(): void
    {
        $item = $this->item('ready_for_pickup');
        $otherSeller = User::factory()->create(['status' => 'approved', 'usertype' => 'seller']);
        Sanctum::actingAs($otherSeller);
        $this->postJson('/api/seller/pickup-requests', ['order_item_id' => $item->id])->assertForbidden();
        Sanctum::actingAs($item->product->seller);
        $this->postJson('/api/seller/pickup-requests', [
            'order_item_id' => $item->id, 'buyer_id' => $otherSeller->id,
            'delivery_street' => 'Forged address', 'order_id' => 'Forged order',
        ])->assertCreated()->assertJsonPath('parcel.buyer_id', $item->order->user_id)
            ->assertJsonPath('parcel.delivery_address', $item->order->shipping_address);
        $this->postJson('/api/seller/pickup-requests', ['order_item_id' => $item->id])->assertCreated();
        $this->assertDatabaseCount('parcels', 1);
        $item->update(['status' => 'packed']);
        $this->postJson('/api/seller/pickup-requests', ['order_item_id' => $item->id])->assertUnprocessable();
    }

    public function test_full_delivery_updates_only_the_linked_item_and_keeps_proof_private(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $item = $this->item('ready_for_pickup');
        $second = $item->order->items()->create([
            'product_id' => $item->product_id, 'product_name' => 'Second item',
            'price' => 10, 'quantity' => 1, 'status' => 'pending',
        ]);
        $parcel = app(OrderItemPickup::class)->create($item);
        $center = $this->center();
        $rider = Rider::create([
            'user_id' => User::factory()->create(['status' => 'approved', 'usertype' => 'rider'])->id,
            'sorting_center_id' => $center->id, 'name' => 'Rider', 'contact' => '09170000000',
            'vehicle_type' => 'motorcycle', 'plate_number' => 'TEST',
            'approval_status' => 'approved', 'is_active' => true,
        ]);
        $area = Area::create(['sorting_center_id' => $center->id, 'name' => 'Cebu City']);
        $rider->areas()->attach($area);
        Sanctum::actingAs($center->user);
        $this->getJson('/api/sorting-center/pickup-requests')->assertOk()
            ->assertJsonPath('data.data.0.parcel.collection_address', 'Seller collection location')
            ->assertJsonPath('data.data.0.parcel.delivery_address', $item->order->shipping_address);
        $this->postJson('/api/sorting-center/pickup-requests/'.$parcel->pickupRequests()->first()->id.'/confirm')->assertOk();
        $this->assertSame('ready_for_pickup', $item->fresh()->status);
        Sanctum::actingAs($rider->user);
        $this->getJson('/api/rider/pickups/available')->assertOk()
            ->assertJsonPath('data.0.collection_address', 'Seller collection location')
            ->assertJsonPath('data.0.delivery_address', $item->order->shipping_address);
        $this->postJson("/api/rider/pickups/{$parcel->id}/accept")->assertOk();
        $this->postJson("/api/rider/pickups/{$parcel->id}/confirm")->assertOk();
        $this->assertSame('shipped', $item->fresh()->status);
        Sanctum::actingAs($center->user);
        $this->postJson('/api/sorting-center/parcels/mark-arrived', ['order_id' => $parcel->order_id])->assertOk();
        $this->postJson("/api/sorting-center/parcels/{$parcel->id}/sort", ['area_id' => $area->id])->assertOk();
        $this->assertSame('Cebu City', $parcel->fresh()->delivery_municipality);
        $this->postJson("/api/sorting-center/parcels/{$parcel->id}/assign", ['rider_id' => $rider->id])->assertOk();
        Sanctum::actingAs($rider->user);
        $this->postJson("/api/rider/deliveries/{$parcel->id}/start")->assertOk();
        $this->assertSame('out_for_delivery', $item->fresh()->status);
        $this->postJson("/api/rider/deliveries/{$parcel->id}/deliver")->assertUnprocessable();
        $this->postJson("/api/rider/deliveries/{$parcel->id}/upload-proof", [
            'file' => UploadedFile::fake()->image('proof.jpg'), 'type' => 'PHOTO',
        ])->assertCreated();
        $this->postJson("/api/rider/deliveries/{$parcel->id}/deliver", ['otp' => $this->deliveryOtp($parcel->id)])->assertOk();
        $this->assertSame('delivered', $item->fresh()->status);
        $this->assertSame('pending', $second->fresh()->status);
        $this->assertSame('pending', $item->order->fresh()->status);
        $this->assertNotNull($parcel->fresh()->delivered_at);
        $this->assertSame(9, $item->statusHistories()->count());
        $this->assertSame(1, $item->statusHistories()->where('note', 'like', 'Pickup Rider assigned to %')->count());
        $this->assertSame(3, BuyerNotification::where('user_id', $item->order->user_id)->count());
        $proof = $parcel->deliveryProofs()->firstOrFail();
        Storage::disk('local')->assertExists($proof->file_path);
        Storage::disk('public')->assertMissing($proof->file_path);
        Sanctum::actingAs($center->user);
        $this->get("/api/sorting-center/proofs/{$proof->id}/file")->assertOk();
        Sanctum::actingAs($this->center()->user);
        $this->getJson("/api/sorting-center/proofs/{$proof->id}/file")->assertNotFound();
    }

    public function test_logistics_chat_does_not_change_existing_messaging_models_or_rows(): void
    {
        $item = $this->item();
        $conversation = Conversation::create([
            'buyer_id' => $item->order->user_id, 'seller_id' => $item->product->seller_id,
            'order_id' => $item->order_id,
        ]);
        $conversation->messages()->create(['sender_id' => $item->order->user_id, 'body' => 'Existing buyer message']);
        Message::create(['user_id' => $item->order->user_id, 'subject' => 'Support', 'message' => 'Original support message']);
        $columns = Schema::getColumnListing('messages');
        $center = $this->center();
        $rider = Rider::create([
            'user_id' => User::factory()->create(['status' => 'approved', 'usertype' => 'rider'])->id,
            'sorting_center_id' => $center->id, 'name' => 'Chat rider', 'contact' => '09170000000',
            'vehicle_type' => 'motorcycle', 'plate_number' => 'CHAT', 'approval_status' => 'approved',
        ]);
        Sanctum::actingAs($center->user);
        $this->postJson('/api/sorting-center/messages', ['recipient_id' => $rider->user_id, 'body' => 'Logistics only'])->assertCreated();
        $this->assertDatabaseCount('sorting_center_messages', 1);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('conversation_messages', 1);
        $this->assertDatabaseCount('messages', 1);
        $this->assertSame($columns, Schema::getColumnListing('messages'));
        $this->assertSame('Existing buyer message', $conversation->messages()->first()->body);
        $this->actingAs($item->product->seller)->get(route('seller.messages.show', $conversation))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Seller/MessageThread'));
    }

    public function test_admin_approval_enables_center_and_suspension_blocks_api_access(): void
    {
        Mail::fake();
        $center = $this->center();
        $center->update(['approval_status' => 'pending']);
        $center->user->update(['status' => 'pending']);
        $admin = User::factory()->create(['status' => 'approved', 'usertype' => 'admin', 'status' => 'approved']);
        $this->actingAs($admin)->patch(route('admin.applications.approve', $center->user))->assertRedirect();
        $this->assertSame('approved', $center->fresh()->approval_status);
        Sanctum::actingAs($center->user->fresh());
        $this->getJson('/api/sorting-center/dashboard')->assertOk();
        $center->user->update(['status' => 'suspended']);
        Sanctum::actingAs($center->user->fresh());
        $this->getJson('/api/sorting-center/dashboard')->assertForbidden();
    }

    public function test_storefront_and_auth_routes_are_preserved_and_sorting_pages_have_separate_entry(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Guest/Home'));
        $this->get('/login')->assertRedirect('/?auth=login');
        $this->get('/register')->assertRedirect('/?auth=register-buyer&onboarding=1');
        $this->get('/sorting-center/login')->assertRedirect('/?auth=login');
        $center = $this->center();
        $this->actingAs($center->user);
        foreach (['dashboard', 'order-management', 'delivery-management', 'rider-assignment', 'delivery-monitoring', 'delivery-reports', 'messages', 'pickup-requests'] as $page) {
            $this->get('/sorting-center/'.$page)->assertOk()->assertViewIs('sorting-center.'.$page);
        }
        $this->assertFalse(Schema::hasColumn('users', 'role'));
    }

    public function test_sorting_login_uses_usertype_and_logout_revokes_the_real_token(): void
    {
        $center = $this->center();
        $login = $this->postJson('/api/sorting-center/login', [
            'email' => $center->user->email, 'password' => 'password',
        ])->assertOk()->assertJsonPath('user.role', 'sorting_center');
        $token = $login->json('token');
        $this->withToken($token)->getJson('/api/sorting-center/dashboard')->assertOk();
        $this->withToken($token)->postJson('/api/logistics/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $center->user->update(['status' => 'suspended']);
        $this->postJson('/api/sorting-center/login', [
            'email' => $center->user->email, 'password' => 'password',
        ])->assertForbidden();
    }

    public function test_failed_delivery_records_history_without_marking_the_item_delivered(): void
    {
        $item = $this->item('ready_for_pickup');
        $parcel = app(OrderItemPickup::class)->create($item);
        $item->moveTo('shipped');
        $item->moveTo('out_for_delivery');
        $parcel->update(['status' => 'OUT_FOR_DELIVERY']);
        $parcel->update(['status' => 'FAILED_RETURNED', 'failure_reason' => 'Recipient unavailable']);
        $this->assertSame('out_for_delivery', $item->fresh()->status);
        $this->assertNull($item->fresh()->delivered_at);
        $this->assertDatabaseHas('order_item_status_histories', [
            'order_item_id' => $item->id, 'note' => 'Delivery failed and returned: Recipient unavailable',
        ]);
        $this->assertDatabaseHas('buyer_notifications', [
            'user_id' => $item->order->user_id, 'title' => 'Delivery failed',
        ]);
    }

    public function test_center_registration_creates_pending_usertype_account_and_blocks_operations(): void
    {
        Storage::fake('local');
        Notification::fake();
        $addresses = app(PhilippineAddressService::class)->data();
        $city = collect($addresses['cities'])->firstWhere('code', '1380600000');
        $barangay = collect($addresses['barangays'])->firstWhere('city_code', $city['code']);
        $this->postJson('/api/sorting-center/register', [
            'business_name' => 'New center', 'email' => 'new-center@example.com',
            'first_name' => 'New', 'last_name' => 'Partner', 'contact_no' => '09123456789',
            'region_code' => '1300000000', 'province_code' => '', 'city_code' => $city['code'], 'barangay_code' => $barangay['code'],
            'street' => 'Main Street', 'house_number' => '1', 'postal_code' => '1000', 'terms' => true, 'privacy' => true,
            'business_type' => 'sole_proprietor', 'authorized_representative' => 'New Partner', 'registration_number' => 'DTI-123',
            'business_email' => 'business@example.com', 'business_phone' => '09123456789', 'services' => 'Delivery',
            'vehicle_types' => 'Motorcycles', 'fleet_count' => 2, 'coverage_city_codes' => [$city['code']], 'operating_hours' => '9 AM–5 PM',
            'password' => 'password', 'password_confirmation' => 'password',
            'id_doc' => UploadedFile::fake()->image('id.png'),
            'permit_doc' => UploadedFile::fake()->image('permit.png'),
        ])->assertCreated();
        $user = User::where('email', 'new-center@example.com')->firstOrFail();
        $this->assertSame('sorting_center', $user->usertype);
        $this->assertSame('pending', $user->status);
        $this->assertSame('pending', $user->sortingCenter->approval_status);
        $this->assertCount(2, Storage::disk('local')->allFiles());
        Sanctum::actingAs($user);
        $this->getJson('/api/sorting-center/dashboard')->assertForbidden();
    }
}
