<?php

namespace Tests\Feature\Logistics;

use App\Models\{Parcel, SortingCenter, User};
use App\Services\LogisticsReportExport;
use Laravel\Sanctum\Sanctum;
use ZipArchive;

class ReportExportTest extends CompleteWorkflowTest
{
    private function record(string $reference, ?int $center = null, string $date = '2026-10-20 12:00:00'): Parcel
    {
        return Parcel::create(['order_id' => $reference, 'seller_id' => $this->item->product->seller_id, 'buyer_id' => $this->item->order->user_id,
            'sorting_center_id' => $center ?? $this->center->id, 'assigned_rider_id' => $this->deliveryRider->id,
            'status' => 'DELIVERED', 'sorted_at' => $date, 'delivered_at' => '2026-10-20 13:00:00',
            'delivery_province' => 'Cebu', 'delivery_municipality' => 'Cebu City', 'delivery_barangay' => '']);
    }

    private function csv(string $content): array
    {
        $file = fopen('php://temp', 'w+b'); fwrite($file, $content); rewind($file); $rows = [];
        while (($row = fgetcsv($file, null, ',', '"', '')) !== false) $rows[] = $row;
        fclose($file); return $rows;
    }

    private function xlsx(string $content): array
    {
        $file = tempnam(sys_get_temp_dir(), 'test-export-'); file_put_contents($file, $content);
        $zip = new ZipArchive();
        try {
            $this->assertTrue($zip->open($file));
            foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels'] as $part) {
                $this->assertNotFalse(simplexml_load_string($zip->getFromName($part)));
            }
            $xml = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
            $xml->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $this->assertSame([], $xml->xpath('//m:f'));
            $rows = [];
            foreach ($xml->xpath('//m:sheetData/m:row') as $row) {
                $values = [];
                foreach ($row->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main')->c as $cell) $values[] = (string) $cell->is->t;
                $rows[] = $values;
            }
            return $rows;
        } finally { $zip->close(); unlink($file); }
    }

    public function test_owned_csv_and_xlsx_match_all_report_pages_without_financial_fields_or_mutations(): void
    {
        for ($i = 0; $i < 61; $i++) $this->record('EXPORT-'.$i);
        $other = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        $foreign = SortingCenter::create(['user_id' => $other->id, 'business_name' => 'Other center', 'approval_status' => 'approved']);
        $this->record('FOREIGN-PRIVATE', $foreign->id);
        $this->record('OUTSIDE-DATE', date: '2026-10-19 23:59:59');
        $before = Parcel::orderBy('id')->get()->toArray();
        Sanctum::actingAs($this->center->user);
        $range = 'from=2026-10-20&to=2026-10-20';
        $this->getJson('/api/sorting-center/reports?'.$range)->assertOk()->assertJsonPath('data.total_parcels_processed', 61);
        $expected = [];
        for ($page = 1; $page <= 4; $page++) {
            $json = $this->getJson('/api/sorting-center/reports/transactions?'.$range.'&per_page=20&page='.$page)->assertOk()->json();
            $expected = array_merge($expected, $json['data']);
        }
        $expected = array_map(fn ($row) => array_map(fn ($key) => (string) ($row[$key] ?? ''), array_keys(LogisticsReportExport::COLUMNS)), $expected);
        foreach (['csv', 'xlsx'] as $format) {
            $response = $this->get('/api/sorting-center/reports/export?'.$range.'&format='.$format)->assertOk()
                ->assertDownload('deliveries-2026-10-20-to-2026-10-20.'.$format);
            $rows = $format === 'csv' ? $this->csv($response->streamedContent()) : $this->xlsx($response->streamedContent());
            $this->assertSame(array_values(LogisticsReportExport::COLUMNS), array_shift($rows));
            $this->assertSame($expected, $rows);
        }
        $this->assertSame($before, Parcel::orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('seller_delivery_charges', 0);
        $this->assertDatabaseCount('rider_delivery_earnings', 0);
    }

    public function test_empty_exports_have_headers_and_invalid_requests_or_other_roles_are_denied(): void
    {
        Sanctum::actingAs($this->center->user);
        $range = 'from=2000-01-01&to=2000-01-02';
        foreach (['csv', 'xlsx'] as $format) {
            $response = $this->get('/api/sorting-center/reports/export?'.$range.'&format='.$format)->assertOk();
            $rows = $format === 'csv' ? $this->csv($response->streamedContent()) : $this->xlsx($response->streamedContent());
            $this->assertSame([array_values(LogisticsReportExport::COLUMNS)], $rows);
        }
        foreach (['&format=pdf', '&format=csv&dataset=cod'] as $bad) $this->getJson('/api/sorting-center/reports/export?'.$range.$bad)->assertUnprocessable();
        $this->getJson('/api/sorting-center/reports/export?from=2026-10-20&to=2026-10-19&format=csv')->assertUnprocessable();
        Sanctum::actingAs($this->pickupRider->user);
        $this->getJson('/api/sorting-center/reports/export?'.$range.'&format=csv')->assertForbidden();
    }

    public function test_customer_formula_text_is_inert_in_both_formats(): void
    {
        $this->item->order->user->update(['name' => '=2+3']);
        $this->record('FORMULA-SAFE');
        Sanctum::actingAs($this->center->user);
        $range = 'from=2026-10-20&to=2026-10-20';
        $csv = $this->csv($this->get('/api/sorting-center/reports/export?'.$range.'&format=csv')->assertOk()->streamedContent());
        $this->assertSame("'=2+3", $csv[1][2]);
        $xlsx = $this->xlsx($this->get('/api/sorting-center/reports/export?'.$range.'&format=xlsx')->assertOk()->streamedContent());
        $this->assertSame('=2+3', $xlsx[1][2]);
    }
}
