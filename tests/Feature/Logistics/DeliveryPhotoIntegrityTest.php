<?php

namespace Tests\Feature\Logistics;

use App\Models\{DeliveryProof, Parcel, Rider, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryPhotoIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function photo(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6WQAAAABJRU5ErkJggg==');
    }

    private function fixture(): array
    {
        Storage::fake('local');
        $user = User::factory()->create(['usertype' => 'rider', 'status' => 'approved']);
        $rider = Rider::create(['user_id' => $user->id, 'name' => 'Rider', 'contact' => '09170000001',
            'vehicle_type' => 'bicycle', 'plate_number' => '', 'approval_status' => 'approved', 'is_active' => true]);
        $parcel = Parcel::create(['order_id' => 'ITEM-123', 'buyer_id' => User::factory()->create()->id,
            'seller_id' => User::factory()->create(['usertype' => 'seller'])->id,
            'assigned_rider_id' => $rider->id, 'status' => 'OUT_FOR_DELIVERY', 'delivery_address' => 'Destination',
            'delivery_province' => '', 'delivery_municipality' => '', 'delivery_barangay' => '']);
        Sanctum::actingAs($user);
        return [$parcel, $rider];
    }

    public function test_signature_missing_null_and_invalid_files_cannot_complete_or_signal_valid_photo(): void
    {
        [$parcel, $rider] = $this->fixture();
        $proof = DeliveryProof::create(['parcel_id' => $parcel->id, 'rider_id' => $rider->id, 'type' => 'SIGNATURE', 'file_path' => 'delivery-proofs/a.png']);
        Storage::disk('local')->put($proof->file_path, $this->photo());
        foreach ([['SIGNATURE', 'delivery-proofs/a.png'], ['PHOTO', 'delivery-proofs/missing.png'], ['PHOTO', null], ['PHOTO', 'delivery-proofs/broken.png']] as [$type, $path]) {
            $proof->update(['type' => $type, 'file_path' => $path]);
            Storage::disk('local')->put('delivery-proofs/broken.png', 'not an image');
            $this->getJson('/api/rider/deliveries')->assertOk()->assertJsonPath('data.0.has_delivery_proof', true)
                ->assertJsonPath('data.0.has_valid_photo_proof', false)->assertJsonMissingPath('data.0.delivery_proofs');
            $this->postJson('/api/rider/deliveries/'.$parcel->id.'/deliver')->assertUnprocessable()->assertJsonPath('code', 'valid_photo_required');
            $this->assertSame('OUT_FOR_DELIVERY', $parcel->fresh()->status);
        }
        $this->assertDatabaseCount('rider_delivery_earnings', 0);
    }

    public function test_duplicate_photo_upload_reuses_record_and_file_and_delivery_completes_once(): void
    {
        [$parcel] = $this->fixture();
        $path = '/api/rider/deliveries/'.$parcel->id;
        $first = $this->postJson($path.'/upload-proof', ['file' => UploadedFile::fake()->createWithContent('proof.png', $this->photo()), 'type' => 'PHOTO'])->assertCreated();
        $this->postJson($path.'/upload-proof', ['file' => UploadedFile::fake()->createWithContent('retry.png', $this->photo()), 'type' => 'PHOTO'])->assertCreated()
            ->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertDatabaseCount('delivery_proofs', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->getJson('/api/rider/deliveries/lookup?reference=ITEM-123')->assertOk()->assertJsonPath('data.has_valid_photo_proof', true);
        $this->postJson($path.'/deliver', ['otp' => $this->deliveryOtp($parcel->id)])->assertOk()->assertJsonPath('data.status', 'DELIVERED');
        $this->postJson($path.'/deliver')->assertUnprocessable();
        $this->assertDatabaseCount('delivery_proofs', 1);
    }

    public function test_deleting_a_previously_uploaded_photo_blocks_completion_but_does_not_rewrite_completed_history(): void
    {
        [$parcel] = $this->fixture();
        $path = '/api/rider/deliveries/'.$parcel->id;
        $proof = $this->postJson($path.'/upload-proof', ['file' => UploadedFile::fake()->createWithContent('proof.png', $this->photo())])->assertCreated();
        Storage::disk('local')->delete($proof->json('data.file_path'));
        $this->postJson($path.'/deliver')->assertUnprocessable();
        // Historical completion is never backfilled or invalidated by this eligibility predicate.
        $parcel->update(['status' => 'DELIVERED', 'delivered_at' => now()]);
        $this->getJson('/api/rider/deliveries?view=history')->assertOk()->assertJsonPath('data.0.status', 'DELIVERED');
        $this->assertSame('DELIVERED', $parcel->fresh()->status);
    }
}
