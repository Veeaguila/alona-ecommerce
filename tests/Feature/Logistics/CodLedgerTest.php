<?php

namespace Tests\Feature\Logistics;

use App\Models\{CodCollection, CodDiscrepancy, CodRemittance, LogisticsAuditEvent, Parcel, SortingCenter, User};
use App\Services\{CodLedger, CodReport};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class CodLedgerTest extends CompleteWorkflowTest
{
    private function delivery(string $number): Parcel
    {
        $order = $this->item->order->replicate(); $order->order_number = $number;
        $order->subtotal = $order->total = 900; $order->shipping_fee = 0; $order->save();
        $item = $this->item->replicate(); $item->order_id = $order->id; $item->price = 900; $item->save();
        $this->actingAs($item->product->seller, 'web')->patch(route('seller.orders.ready-for-pickup', $item))->assertRedirect();
        $parcel = $item->parcel()->sole();
        Sanctum::actingAs($this->center->user); $this->postJson('/api/sorting-center/pickup-requests/'.$parcel->pickupRequests()->sole()->id.'/confirm')->assertOk();
        Sanctum::actingAs($this->pickupRider->user); $this->postJson('/api/rider/pickups/'.$parcel->id.'/accept')->assertOk();
        $this->postJson('/api/rider/pickups/'.$parcel->id.'/confirm')->assertOk();
        Sanctum::actingAs($this->center->user); $this->postJson('/api/sorting-center/parcels/mark-arrived', ['order_id' => $parcel->order_id])->assertOk();
        $this->postJson('/api/sorting-center/parcels/'.$parcel->id.'/sort', ['area_id' => $this->area->id])->assertOk();
        $this->pickupRider->areas()->syncWithoutDetaching([$this->area->id]);
        $this->postJson('/api/sorting-center/parcels/'.$parcel->id.'/assign', ['rider_id' => $this->pickupRider->id])->assertOk();
        Sanctum::actingAs($this->pickupRider->user); $this->postJson('/api/rider/deliveries/'.$parcel->id.'/start')->assertOk();
        return $parcel;
    }
    private function collect(Parcel $parcel, string $amount = '900.00'): void
    {
        Sanctum::actingAs($this->pickupRider->user);
        $input = ['request_key' => (string) Str::uuid(), 'amount' => $amount, 'file' => UploadedFile::fake()->image('cash.jpg')];
        $url = '/api/rider/cod/collections/'.$parcel->id;
        $this->postJson($url, $input)->assertCreated();
        $this->postJson($url, $input)->assertCreated();
        $input['request_key'] = (string) Str::uuid(); $this->postJson($url, $input)->assertConflict();
    }
    private function submit(string $amount): int
    {
        Sanctum::actingAs($this->pickupRider->user);
        return $this->postJson('/api/rider/cod/remittances', ['request_key' => (string) Str::uuid(), 'sorting_center_id' => $this->center->id,
            'amount' => $amount, 'file' => UploadedFile::fake()->image('remit.jpg')])->assertCreated()->json('data.id');
    }
    public function test_actual_cash_partial_receipts_shortfall_admin_resolution_and_final_reconciliation_remain_separate_from_fees(): void
    {
        $first = $this->delivery('COD-FIRST'); $second = $this->delivery('COD-SECOND');
        $this->collect($first); $this->collect($second);
        $this->assertDatabaseCount('cod_collections', 2);
        $id = $this->submit('1800.00'); $ledger = app(CodLedger::class);
        $this->assertSame(['collected' => 180000, 'received' => 0, 'pending' => 180000, 'outstanding' => 180000], $ledger->balance($this->pickupRider->id, $this->center->id));
        $this->postJson('/api/rider/cod/remittances', ['request_key' => (string) Str::uuid(), 'sorting_center_id' => $this->center->id,
            'amount' => '0.01', 'file' => UploadedFile::fake()->image('extra.jpg')])->assertUnprocessable();
        Sanctum::actingAs($this->center->user);
        $url = '/api/sorting-center/cod/remittances/'.$id.'/confirm';
        $this->postJson($url, ['amount' => '1750.00', 'file' => UploadedFile::fake()->image('receipt.jpg')])->assertUnprocessable();
        $this->postJson($url, ['amount' => '1750.00', 'file' => UploadedFile::fake()->image('receipt.jpg'), 'reason' => 'Received cash is fifty pesos short of the submitted amount.'])->assertOk();
        $this->postJson($url, ['amount' => '1750.00', 'file' => UploadedFile::fake()->image('duplicate.jpg')])->assertConflict();
        $case = CodDiscrepancy::sole(); $this->assertSame('-50.00', $case->difference); $this->assertSame('pending', $case->status);
        $today = now('Asia/Manila')->toDateString(); $reportUrl = '/api/sorting-center/cod?from='.$today.'&to='.$today;
        $report = $this->getJson($reportUrl)->assertOk()->assertJsonPath('data.totals.collected', '1800.00')
            ->assertJsonPath('data.totals.received', '1750.00')->assertJsonPath('data.totals.difference', '-50.00')->json('data');
        $this->assertSame(3, $report['pagination']['total']);
        $this->assertSame('50.00', $report['balances'][0]['outstanding']);
        $this->assertTrue(collect($report['audits'])->contains(fn ($event) => $event['action'] === 'cod_remittance_confirmed'));
        $this->postJson('/api/admin/cod/discrepancies/'.$case->id.'/approve', ['resolution' => 'Unauthorized Logistics decision.'])->assertForbidden();
        $last = $this->submit('50.00'); Sanctum::actingAs($this->center->user);
        $this->postJson('/api/sorting-center/cod/remittances/'.$last.'/confirm', ['amount' => '50.00', 'file' => UploadedFile::fake()->image('final-receipt.jpg')])->assertOk();
        $admin = User::factory()->create(['usertype' => 'admin', 'status' => 'approved']); Sanctum::actingAs($admin);
        $this->postJson('/api/admin/cod/discrepancies/'.$case->id.'/approve', ['resolution' => 'The additional fifty pesos was separately received and confirmed.'])->assertOk();
        $this->postJson('/api/admin/cod/discrepancies/'.$case->id.'/approve', ['resolution' => 'Duplicate resolution confirmation.'])->assertConflict();
        $this->assertSame(0, $ledger->balance($this->pickupRider->id, $this->center->id)['outstanding']);
        $this->assertFalse(LogisticsAuditEvent::where('action', 'cod_resolution_approved')->sole()->data['cash_balance_adjusted']);
        Sanctum::actingAs($this->center->user);
        $this->getJson($reportUrl)->assertOk()->assertJsonPath('data.totals.difference', '0.00');
        $this->assertDatabaseCount('seller_delivery_charges', 0); $this->assertDatabaseCount('rider_delivery_earnings', 0);
        $this->assertSame('OUT_FOR_DELIVERY', $first->fresh()->status);
        $this->assertSame('OUT_FOR_DELIVERY', $second->fresh()->status);
    }
    public function test_cash_ownership_evidence_isolation_and_invalid_amounts_are_enforced(): void
    {
        $parcel = $this->delivery('COD-SCOPE');
        $input = ['request_key' => (string) Str::uuid(), 'amount' => '900.00', 'file' => UploadedFile::fake()->image('cash.jpg')];
        Sanctum::actingAs($this->outsider->user); $this->postJson('/api/rider/cod/collections/'.$parcel->id, $input)->assertForbidden();
        Sanctum::actingAs($this->pickupRider->user);
        foreach (['-1', '0', '1.001', '1e2', 'invalid'] as $amount) $this->postJson('/api/rider/cod/collections/'.$parcel->id, array_replace($input, ['amount' => $amount]))->assertUnprocessable();
        $this->postJson('/api/rider/cod/collections/'.$parcel->id, array_diff_key($input, ['file' => true]))->assertUnprocessable();
        $this->postJson('/api/rider/cod/collections/'.$parcel->id, $input)->assertCreated()->assertJsonMissingPath('data.evidence_path');
        $collection = CodCollection::sole(); Storage::disk('local')->assertExists($collection->evidence_path);
        $this->get('/api/cod/evidence/collection/'.$collection->id)->assertOk();
        Sanctum::actingAs($this->outsider->user); $this->getJson('/api/cod/evidence/collection/'.$collection->id)->assertNotFound();
        $user = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        SortingCenter::create(['user_id' => $user->id, 'business_name' => 'Foreign Cash Center', 'approval_status' => 'approved']);
        Sanctum::actingAs($user); $this->getJson('/api/cod/evidence/collection/'.$collection->id)->assertNotFound();
        $today = now('Asia/Manila')->toDateString(); $this->getJson('/api/sorting-center/cod?from='.$today.'&to='.$today)->assertOk()->assertJsonPath('data.totals.collected', '0.00');
        Sanctum::actingAs(User::factory()->create(['usertype' => 'admin', 'status' => 'pending']));
        $this->getJson('/api/cod/evidence/collection/'.$collection->id)->assertNotFound();
        $this->center->user->update(['status' => 'pending']); Sanctum::actingAs($this->center->user->fresh());
        $this->getJson('/api/cod/evidence/collection/'.$collection->id)->assertNotFound();
        $this->expectException(\LogicException::class); $collection->update(['amount' => '999.00']);
    }
    public function test_cod_csv_and_xlsx_contain_only_real_cash_events_matching_filtered_rows(): void
    {
        $parcel = $this->delivery('COD-EXPORT'); $this->collect($parcel);
        $id = $this->submit('900.00'); Sanctum::actingAs($this->center->user);
        $this->postJson('/api/sorting-center/cod/remittances/'.$id.'/confirm', ['amount' => '850.00',
            'reason' => 'Fifty pesos remains to be remitted.', 'file' => UploadedFile::fake()->image('receipt.jpg')])->assertOk();
        $today = now('Asia/Manila')->toDateString(); $query = '?from='.$today.'&to='.$today;
        $data = $this->getJson('/api/sorting-center/cod'.$query)->assertOk()->json('data');
        $csv = $this->get('/api/sorting-center/cod/export'.$query.'&format=csv')->assertOk()->streamedContent();
        $lines = array_map('str_getcsv', explode("\r\n", trim($csv)));
        $this->assertSame(array_values(CodReport::COLUMNS), $lines[0]);
        foreach ($data['records'] as $index => $row) $this->assertSame(array_map(fn ($key) => (string) ($row[$key] ?? ''), array_keys(CodReport::COLUMNS)), $lines[$index + 1]);
        $xlsx = $this->get('/api/sorting-center/cod/export'.$query.'&format=xlsx')->assertOk()->streamedContent();
        $file = tempnam(sys_get_temp_dir(), 'cod-test-'); file_put_contents($file, $xlsx);
        try { $zip = new \ZipArchive(); $this->assertTrue($zip->open($file)); $sheet = $zip->getFromName('xl/worksheets/sheet1.xml'); $zip->close();
            $this->assertStringContainsString('COD-EXPORT', $sheet); $this->assertStringContainsString('-50.00', $sheet);
            $this->assertSame(count($data['records']) + 1, substr_count($sheet, '<row '));
        } finally { unlink($file); }
    }
}
