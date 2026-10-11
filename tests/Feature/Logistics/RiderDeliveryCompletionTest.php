<?php

namespace Tests\Feature\Logistics;

use App\Models\Parcel;
use App\Models\DeliveryProof;
use App\Models\Rider;
use App\Models\SortingCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiderDeliveryCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_rider_can_mark_an_assigned_delivery_as_delivered(): void
    {
        $this->freezeTime();
        [$user, $rider] = $this->riderUser();
        $parcel = $this->parcel($rider->id);
        DeliveryProof::create([
            'parcel_id' => $parcel->id,
            'rider_id' => $rider->id,
            'type' => 'PHOTO',
            'file_path' => 'delivery-proofs/test.jpg',
            'captured_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/rider/deliveries/{$parcel->id}/deliver")
            ->assertOk()
            ->assertJsonPath('data.status', 'DELIVERED')
            ->assertJsonPath('message', 'Delivery marked as delivered.');

        $this->assertDatabaseHas('parcels', [
            'id' => $parcel->id,
            'status' => 'DELIVERED',
            'delivered_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function test_sorting_center_can_read_proofs_with_rider_email_and_storage_url(): void
    {
        [$user, $rider] = $this->riderUser();
        $parcel = $this->parcel($rider->id);
        $parcel->update(['sorting_center_id' => $rider->sorting_center_id]);
        $proof = DeliveryProof::create([
            'parcel_id' => $parcel->id, 'rider_id' => $rider->id,
            'type' => 'PHOTO', 'file_path' => 'delivery-proofs/test.jpg',
            'captured_at' => now(),
        ]);
        Sanctum::actingAs($rider->sortingCenter->user);

        $url = route('sorting-center.proofs.file', ['proofId' => $proof->id], false);
        $this->getJson("/api/sorting-center/parcels/{$parcel->id}/proofs")
            ->assertOk()
            ->assertJsonPath('data.proofs.0.rider.email', $user->email)
            ->assertJsonPath('data.proofs.0.file_url', $url);
        $this->getJson("/api/sorting-center/proofs/{$proof->id}")
            ->assertOk()
            ->assertJsonPath('data.rider.email', $user->email)
            ->assertJsonPath('data.file_url', $url);

        [, $otherRider] = $this->riderUser();
        Sanctum::actingAs($otherRider->sortingCenter->user);
        $this->getJson("/api/sorting-center/parcels/{$parcel->id}/proofs")->assertNotFound();
        $this->getJson("/api/sorting-center/proofs/{$proof->id}")->assertForbidden();
    }

    public function test_proof_files_require_authentication_and_center_ownership(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        [$user, $rider] = $this->riderUser();
        $parcel = $this->parcel($rider->id);
        $parcel->update(['sorting_center_id' => $rider->sorting_center_id]);
        $path = 'delivery-proofs/private.jpg';
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, 'private proof bytes');
        $proof = DeliveryProof::create([
            'parcel_id' => $parcel->id, 'rider_id' => $rider->id,
            'type' => 'PHOTO', 'file_path' => $path, 'captured_at' => now(),
        ]);
        $url = route('sorting-center.proofs.file', ['proofId' => $proof->id], false);
        $this->getJson($url)->assertUnauthorized();
        Sanctum::actingAs($rider->sortingCenter->user);
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->getJson("/api/sorting-center/proofs/{$proof->id}")
            ->assertOk()->assertJsonMissingPath('data.file_path');
        [, $other] = $this->riderUser();
        Sanctum::actingAs($other->sortingCenter->user);
        $this->getJson($url)->assertNotFound();
    }

    public function test_uploaded_proofs_are_stored_privately(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::fake('public');
        [$user, $rider] = $this->riderUser();
        $parcel = $this->parcel($rider->id);
        Sanctum::actingAs($user);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aQ1sAAAAASUVORK5CYII=');
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('proof.png', $png);
        $response = $this->postJson("/api/rider/deliveries/{$parcel->id}/upload-proof", ['file' => $file])
            ->assertCreated();
        $path = $response->json('data.file_path');
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($path);
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($path);
    }

    public function test_rider_must_upload_proof_before_marking_delivery_as_delivered(): void
    {
        [$user, $rider] = $this->riderUser();
        $parcel = $this->parcel($rider->id);
        Sanctum::actingAs($user);

        $this->postJson("/api/rider/deliveries/{$parcel->id}/deliver")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Upload delivery proof before marking this parcel as delivered.');

        $this->assertDatabaseHas('parcels', [
            'id' => $parcel->id,
            'status' => 'OUT_FOR_DELIVERY',
        ]);
    }

    public function test_assigned_rider_can_start_a_delivery(): void
    {
        [$user, $rider] = $this->riderUser();
        $parcel = $this->parcel($rider->id, 'AT_SORTING_CENTER');
        $parcel->update(['assigned_at' => null]);
        Sanctum::actingAs($user);

        $this->postJson("/api/rider/deliveries/{$parcel->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'OUT_FOR_DELIVERY');

        $this->assertDatabaseHas('parcels', [
            'id' => $parcel->id,
            'status' => 'OUT_FOR_DELIVERY',
        ]);
    }

    public function test_rider_can_mark_an_assigned_delivery_failed_with_a_reason(): void
    {
        [$user, $rider] = $this->riderUser();
        $parcel = $this->parcel($rider->id);
        Sanctum::actingAs($user);

        $this->postJson("/api/rider/deliveries/{$parcel->id}/fail", [
            'reason' => 'Customer not available',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'FAILED_RETURNED')
            ->assertJsonPath('data.failure_reason', 'Customer not available');

        $this->assertDatabaseHas('parcels', [
            'id' => $parcel->id,
            'status' => 'FAILED_RETURNED',
            'failure_reason' => 'Customer not available',
        ]);
    }

    public function test_rider_cannot_update_another_riders_delivery(): void
    {
        [$user] = $this->riderUser();
        [, $owner] = $this->riderUser();
        $parcel = $this->parcel($owner->id);
        Sanctum::actingAs($user);

        $this->postJson("/api/rider/deliveries/{$parcel->id}/deliver")
            ->assertForbidden()
            ->assertJsonPath('message', 'This delivery is not assigned to you.');

        $this->assertDatabaseHas('parcels', [
            'id' => $parcel->id,
            'status' => 'OUT_FOR_DELIVERY',
        ]);
    }

    public function test_rider_cannot_update_a_delivery_that_is_not_out_for_delivery(): void
    {
        [$user, $rider] = $this->riderUser();
        $parcel = $this->parcel($rider->id, 'DELIVERED');
        Sanctum::actingAs($user);

        $this->postJson("/api/rider/deliveries/{$parcel->id}/deliver")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This delivery is no longer out for delivery.');
    }

    private function riderUser(): array
    {
        $sortingCenterUser = User::factory()->create(['status' => 'approved', 'usertype' => 'sorting_center']);
        $sortingCenter = SortingCenter::create([
            'user_id' => $sortingCenterUser->id,
            'business_name' => 'Delivery Test Center',
            'approval_status' => 'approved',
        ]);
        $user = User::factory()->create(['status' => 'approved', 'usertype' => 'rider']);
        $rider = Rider::create([
            'user_id' => $user->id,
            'sorting_center_id' => $sortingCenter->id,
            'name' => 'Test Rider',
            'contact' => '09170000000',
            'vehicle_type' => 'motorcycle',
            'plate_number' => 'TEST-777',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        return [$user, $rider];
    }

    private function parcel(int $riderId, string $status = 'OUT_FOR_DELIVERY'): Parcel
    {
        $seller = User::factory()->create(['status' => 'approved', 'usertype' => 'seller']);
        $buyer = User::factory()->create(['status' => 'approved', 'usertype' => 'buyer']);

        return Parcel::create([
            'order_id' => 'TEST-DELIVERY-'.uniqid(),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => $status,
            'delivery_province' => 'Metro Manila',
            'delivery_municipality' => 'Taguig',
            'delivery_barangay' => 'Fort Bonifacio',
            'delivery_street' => 'Delivery Street',
            'delivery_house_number' => '101',
            'assigned_rider_id' => $riderId,
            'assigned_at' => now(),
        ]);
    }
}
