<?php

namespace Tests\Feature\Logistics;

use App\Models\{BuyerNotification, LogisticsAuditEvent, Parcel, ParcelIncident, SellerDeliveryCharge, SellerNotification, SortingCenter, User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class ParcelOperationsTest extends CompleteWorkflowTest
{
    private function dispatchedParcel(): Parcel
    {
        $this->actingAs($this->item->product->seller, 'web')->patch(route('seller.orders.ready-for-pickup', $this->item))->assertRedirect();
        $parcel = $this->item->parcel()->firstOrFail();
        $parcel->updateQuietly(['sorting_center_id' => $this->center->id, 'status' => 'OUT_FOR_DELIVERY',
            'pickup_rider_id' => $this->pickupRider->id, 'assigned_rider_id' => $this->deliveryRider->id]);
        $this->item->updateQuietly(['status' => 'out_for_delivery']);
        Sanctum::actingAs($this->center->user);
        return $parcel;
    }

    public function test_privileged_delivered_exception_requires_reason_and_audits_once_without_inventing_proof_or_money(): void
    {
        $parcel = $this->dispatchedParcel();
        $url = "/api/sorting-center/parcels/{$parcel->id}/override-delivered";
        $this->postJson($url, [])->assertUnprocessable();
        $this->assertSame('OUT_FOR_DELIVERY', $parcel->fresh()->status);
        $this->postJson($url, ['reason' => 'Phone-confirmed receipt by the Buyer.'])->assertOk();
        $this->assertSame('DELIVERED', $parcel->fresh()->status);
        $this->assertSame('delivered', $this->item->fresh()->status);
        $audit = LogisticsAuditEvent::where('parcel_id', $parcel->id)->sole();
        $this->assertSame($this->center->user_id, $audit->actor_id);
        $this->assertSame('OUT_FOR_DELIVERY', $audit->data['previous_status']);
        $this->assertSame('DELIVERED', $audit->data['new_status']);
        $this->assertTrue($audit->data['privileged_proof_exception']);
        $this->assertFalse($audit->data['financial_recognition']);
        $this->assertNotNull($audit->created_at);
        $this->postJson($url, ['reason' => 'Duplicate phone confirmation.'])->assertUnprocessable();
        $this->getJson("/api/sorting-center/parcels/{$parcel->id}/operations")->assertOk()
            ->assertJsonPath('data.events.0.actor.name', 'Synthetic Logistics');
        $this->assertDatabaseCount('logistics_audit_events', 1);
        $this->assertDatabaseCount('delivery_proofs', 0);
        $this->assertSame(0, SellerDeliveryCharge::count());
        $this->assertTrue(SellerNotification::where('user_id', $parcel->seller_id)->where('type', 'delivery_override')->exists());
        $this->expectException(\LogicException::class);
        $audit->update(['action' => 'rewritten']);
    }

    public function test_damaged_requires_private_image_rejects_duplicate_transitions_and_keeps_records_visible(): void
    {
        $parcel = $this->dispatchedParcel();
        $url = "/api/sorting-center/parcels/{$parcel->id}/incidents";
        $input = ['request_key' => (string) Str::uuid(), 'type' => 'damaged', 'reason' => 'The packaging and contents were damaged.'];
        $this->postJson($url, $input)->assertUnprocessable();
        $this->postJson($url, $input + ['file' => UploadedFile::fake()->create('fake.jpg', 10, 'text/plain')])->assertUnprocessable();
        $input['file'] = UploadedFile::fake()->image('damage.jpg');
        $response = $this->postJson($url, $input)->assertCreated();
        $this->assertArrayNotHasKey('evidence_path', $response->json('data'));
        $incident = ParcelIncident::sole();
        Storage::disk('local')->assertExists($incident->evidence_path);
        $this->get("/api/sorting-center/parcels/{$parcel->id}/incidents/{$incident->id}/evidence")->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->postJson($url, $input)->assertCreated()->assertJsonPath('data.id', $incident->id);
        $input['request_key'] = (string) Str::uuid();
        $this->postJson($url, $input)->assertUnprocessable();
        $this->assertDatabaseCount('parcel_incidents', 1);
        $this->assertDatabaseCount('logistics_audit_events', 1);
        $this->assertSame('DAMAGED', $parcel->fresh()->status);
        $this->assertSame($this->deliveryRider->id, $parcel->fresh()->assigned_rider_id);
        $this->getJson('/api/sorting-center/parcels/monitoring?status=DAMAGED')->assertOk()->assertJsonPath('data.data.0.id', $parcel->id);
        $this->getJson('/api/sorting-center/dashboard')->assertOk()->assertJsonPath('data.parcel_totals.failed', 1);
        $this->assertTrue(BuyerNotification::where('user_id', $parcel->buyer_id)->where('type', 'parcel_incident')->exists());
        $this->assertTrue(SellerNotification::where('user_id', $parcel->seller_id)->where('type', 'parcel_incident')->exists());
        Sanctum::actingAs($this->deliveryRider->user);
        $this->getJson('/api/rider/deliveries')->assertOk()->assertJsonPath('data.0.status', 'DAMAGED');
        $this->postJson("/api/rider/deliveries/{$parcel->id}/deliver", ['otp' => '123456'])->assertUnprocessable();
    }

    public function test_foreign_logistics_and_riders_cannot_override_report_or_read_incident_evidence(): void
    {
        $parcel = $this->dispatchedParcel();
        $this->postJson("/api/sorting-center/parcels/{$parcel->id}/incidents", ['request_key' => (string) Str::uuid(),
            'type' => 'lost', 'reason' => 'Unable to locate this parcel after warehouse search.'])->assertCreated();
        $staff = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        SortingCenter::create(['user_id' => $staff->id, 'business_name' => 'Foreign Center', 'approval_status' => 'approved']);
        Sanctum::actingAs($staff);
        $this->getJson("/api/sorting-center/parcels/{$parcel->id}/operations")->assertNotFound();
        $this->postJson("/api/sorting-center/parcels/{$parcel->id}/override-delivered", ['reason' => 'Unauthorized phone confirmation.'])->assertNotFound();
        $this->postJson("/api/sorting-center/parcels/{$parcel->id}/incidents", ['request_key' => (string) Str::uuid(),
            'type' => 'lost', 'reason' => 'Foreign center incident.'])->assertNotFound();
        Sanctum::actingAs($this->outsider->user);
        $this->getJson("/api/sorting-center/parcels/{$parcel->id}/operations")->assertForbidden();
        $this->assertSame('LOST', $parcel->fresh()->status);
        $this->assertDatabaseCount('logistics_audit_events', 2);
        $this->assertSame(1, LogisticsAuditEvent::where('action', 'logistics_claim_opened')->count());
        $this->expectException(\LogicException::class);
        LogisticsAuditEvent::where('action', 'incident_reported')->sole()->delete();
    }

    public function test_lost_cod_creates_nonmonetary_claim_and_approved_admin_queue_without_fabricating_cash_or_refund(): void
    {
        $parcel = $this->dispatchedParcel();
        $this->postJson("/api/sorting-center/parcels/{$parcel->id}/incidents", ['request_key' => (string) Str::uuid(),
            'type' => 'lost', 'reason' => 'Unable to locate this parcel after warehouse search.'])->assertCreated();
        $this->getJson('/api/admin/logistics-claims')->assertForbidden();
        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'pending']);
        Sanctum::actingAs($admin); $this->getJson('/api/admin/logistics-claims')->assertForbidden();
        $admin->update(['status' => 'approved']); Sanctum::actingAs($admin->fresh());
        $id = ParcelIncident::sole()->id;
        $this->getJson('/api/admin/logistics-claims')->assertOk()->assertJsonPath('data.data.0.claim_reference', 'INC-'.$id)
            ->assertJsonPath('data.data.0.claim_state', 'pending_review')->assertJsonPath('data.data.0.parcel.order_id', $parcel->order_id);
        $this->assertNull(LogisticsAuditEvent::where('action', 'logistics_claim_opened')->sole()->data['financial_amount']);
        $this->assertDatabaseCount('order_return_requests', 0); $this->assertDatabaseCount('refund_ledgers', 0); $this->assertDatabaseCount('cod_collections', 0);
    }

    public function test_combined_prepaid_order_opens_claim_but_never_guesses_refund_allocation(): void
    {
        $this->item->order->update(['payment_method' => 'gcash', 'payment_status' => 'paid']);
        $duplicate = $this->item->replicate(); $duplicate->save();
        $parcel = $this->dispatchedParcel();
        $this->postJson("/api/sorting-center/parcels/{$parcel->id}/incidents", ['request_key' => (string) Str::uuid(),
            'type' => 'lost', 'reason' => 'Parcel missing from multi-item prepaid order.'])->assertCreated();
        $this->assertDatabaseCount('order_return_requests', 0); $this->assertDatabaseCount('refund_ledgers', 0);
        $this->assertSame(1, LogisticsAuditEvent::where('action', 'logistics_refund_review_blocked')->count());
    }
}
