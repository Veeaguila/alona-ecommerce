<?php

namespace Tests\Feature\Logistics;

use App\Models\Parcel;
use App\Services\DeliveryOtp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Auth, Cache, Crypt, Hash};
use Laravel\Sanctum\Sanctum;

class BuyerOtpTest extends CompleteWorkflowTest
{
    private function parcel(): Parcel
    {
        $this->item->forceFill(['status' => 'out_for_delivery'])->saveQuietly();
        $this->item->order->forceFill(['status' => 'out_for_delivery'])->saveQuietly();
        return Parcel::create(['order_item_id' => $this->item->id, 'order_id' => 'OTP-ISOLATED',
            'seller_id' => $this->item->product->seller_id, 'buyer_id' => $this->item->order->user_id,
            'sorting_center_id' => $this->center->id, 'status' => 'OUT_FOR_DELIVERY',
            'assigned_rider_id' => $this->deliveryRider->id, 'delivery_province' => '', 'delivery_municipality' => '', 'delivery_barangay' => '']);
    }

    private function buyerCode(Parcel $parcel): string
    {
        Auth::forgetGuards();
        $response = $this->actingAs($this->item->order->user, 'web')
            ->getJson('/buyer/orders/'.$this->item->order_id.'/parcels/'.$parcel->id.'/delivery-otp')->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        return $response->json('data.otp');
    }

    private function rider(): void
    {
        Auth::forgetGuards();
        Sanctum::actingAs($this->deliveryRider->user);
    }

    public function test_own_buyer_only_sees_code_in_private_endpoint_and_valid_otp_plus_photo_complete_once(): void
    {
        $parcel = $this->parcel();
        $code = $this->buyerCode($parcel);
        $this->assertMatchesRegularExpression('/\A[0-9]{6}\z/', $code);
        $stored = Cache::get(DeliveryOtp::CACHE_PREFIX.$parcel->id);
        $this->assertTrue(Hash::check($code, $stored['hash']));
        $this->assertSame($code, Crypt::decryptString($stored['ciphertext']));
        $this->assertArrayNotHasKey('otp', $stored);
        $this->rider();
        $this->getJson('/api/rider/deliveries')->assertOk()->assertJsonMissingPath('data.0.otp');
        $this->postJson('/api/rider/deliveries/'.$parcel->id.'/deliver', ['otp' => $code])->assertUnprocessable()->assertJsonPath('code', 'valid_photo_required');
        $this->postJson('/api/rider/deliveries/'.$parcel->id.'/upload-proof', ['file' => UploadedFile::fake()->image('otp-proof.jpg'), 'type' => 'PHOTO'])->assertCreated();
        $this->postJson('/api/rider/deliveries/'.$parcel->id.'/deliver')->assertUnprocessable()->assertJsonValidationErrors('otp');
        $this->postJson('/api/rider/deliveries/'.$parcel->id.'/deliver', ['otp' => $code])->assertOk()->assertJsonPath('data.status', 'DELIVERED');
        $this->postJson('/api/rider/deliveries/'.$parcel->id.'/deliver', ['otp' => $code])->assertUnprocessable();
        $this->assertTrue(Cache::get(DeliveryOtp::CACHE_PREFIX.$parcel->id)['consumed']);
        Auth::forgetGuards();
        $this->actingAs($this->item->order->user, 'web')->getJson('/buyer/orders/'.$this->item->order_id.'/parcels/'.$parcel->id.'/delivery-otp')->assertUnprocessable();
    }

    public function test_expiry_five_invalid_attempts_and_owner_renewal_do_not_mutate_delivery_or_charge_records(): void
    {
        $this->freezeTime();
        $parcel = $this->parcel(); $code = $this->buyerCode($parcel);
        $this->rider();
        $this->postJson('/api/rider/deliveries/'.$parcel->id.'/upload-proof', ['file' => UploadedFile::fake()->image('otp-proof.jpg')])->assertCreated();
        $wrong = $code === '111111' ? '222222' : '111111';
        for ($i = 0; $i < 5; $i++) $this->postJson('/api/rider/deliveries/'.$parcel->id.'/deliver', ['otp' => $wrong])->assertUnprocessable();
        $this->postJson('/api/rider/deliveries/'.$parcel->id.'/deliver', ['otp' => $code])->assertStatus(429);
        Auth::forgetGuards();
        $path = '/buyer/orders/'.$this->item->order_id.'/parcels/'.$parcel->id.'/delivery-otp';
        $this->actingAs($this->item->order->user, 'web')->postJson($path.'/renew')->assertStatus(429);
        $this->getJson($path)->assertStatus(429);
        $this->travel(DeliveryOtp::LOCKOUT_SECONDS - 1)->seconds();
        $this->postJson($path.'/renew')->assertStatus(429);
        $this->travel(1)->seconds();
        $renew = $this->postJson($path.'/renew')->assertOk();
        $fresh = $renew->json('data.otp');
        $this->travel(DeliveryOtp::LIFETIME_SECONDS + 1)->seconds();
        $this->rider();
        $this->postJson('/api/rider/deliveries/'.$parcel->id.'/deliver', ['otp' => $fresh])->assertStatus(410);
        $this->assertSame('OUT_FOR_DELIVERY', $parcel->fresh()->status);
        $this->assertDatabaseCount('seller_delivery_charges', 0);
        $this->assertDatabaseCount('rider_delivery_earnings', 0);
    }

    public function test_unrelated_buyer_and_rider_cannot_retrieve_or_rotate_the_code(): void
    {
        $parcel = $this->parcel(); $this->buyerCode($parcel);
        $path = '/buyer/orders/'.$this->item->order_id.'/parcels/'.$parcel->id.'/delivery-otp';
        Auth::forgetGuards();
        $this->actingAs(\App\Models\User::factory()->create(['usertype' => 'buyer', 'status' => 'approved']), 'web')
            ->getJson($path)->assertNotFound();
        $this->postJson($path.'/renew')->assertNotFound();
        Auth::forgetGuards();
        $this->actingAs($this->pickupRider->user, 'web')->getJson($path)->assertForbidden();
    }

    public function test_renewing_before_five_wrong_codes_preserves_the_job_attempt_counter(): void
    {
        $this->freezeTime(); $parcel = $this->parcel(); $code = $this->buyerCode($parcel);
        for ($i = 0; $i < 2; $i++) {
            try { app(DeliveryOtp::class)->verify($parcel, $code === '111111' ? '222222' : '111111'); }
            catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('otp', $e->errors()); }
        }
        Auth::forgetGuards();
        $path = '/buyer/orders/'.$this->item->order_id.'/parcels/'.$parcel->id.'/delivery-otp';
        $code = $this->actingAs($this->item->order->user, 'web')->postJson($path.'/renew')->assertOk()->json('data.otp');
        $this->assertSame(2, Cache::get(DeliveryOtp::CACHE_PREFIX.$parcel->id)['attempts']);
        for ($i = 0; $i < 3; $i++) {
            try { app(DeliveryOtp::class)->verify($parcel, $code === '111111' ? '222222' : '111111'); }
            catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('otp', $e->errors()); }
        }
        $this->assertSame(now()->timestamp + 900, Cache::get(DeliveryOtp::CACHE_PREFIX.$parcel->id)['locked_until']);
        $this->postJson($path.'/renew')->assertStatus(429);
        $this->assertSame('OUT_FOR_DELIVERY', $parcel->fresh()->status);
    }
}
