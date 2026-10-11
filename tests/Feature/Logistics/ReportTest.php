<?php

namespace Tests\Feature\Logistics;

use App\Models\Area;
use App\Models\Parcel;
use App\Models\Rider;
use App\Models\SortingCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_returns_scoped_status_counts_and_average_turnaround(): void
    {
        $sortingCenter = $this->approvedSortingCenter();
        $area = Area::create(['sorting_center_id' => $sortingCenter->sortingCenter->id, 'name' => 'Area A']);
        $rider = $this->rider($sortingCenter->sortingCenter->id);
        $rider->areas()->attach($area->id);
        $sortedAt = now()->subHours(5);
        $reportDate = $sortedAt->toDateString();
        $this->parcel($area, 'AT_SORTING_CENTER', $sortedAt);
        $this->parcel($area, 'OUT_FOR_DELIVERY', $sortedAt, now()->subHour(), $rider->id);
        $this->parcel($area, 'DELIVERED', $sortedAt, now()->subHours(3), $rider->id);

        Sanctum::actingAs($sortingCenter);

        $this->getJson('/api/sorting-center/reports?from='.$reportDate.'&to='.$reportDate)
            ->assertOk()
            ->assertJsonPath('data.total_parcels_processed', 3)
            ->assertJsonPath('data.status_breakdown.AT_SORTING_CENTER', 1)
            ->assertJsonPath('data.status_breakdown.OUT_FOR_DELIVERY', 1)
            ->assertJsonPath('data.status_breakdown.DELIVERED', 1)
            ->assertJsonPath('data.average_turnaround_seconds', 10800);
    }

    public function test_report_rejects_a_to_date_before_from_date(): void
    {
        Sanctum::actingAs($this->approvedSortingCenter());

        $this->getJson('/api/sorting-center/reports?from=2026-09-22&to=2026-09-21')
            ->assertStatus(422)
            ->assertJsonValidationErrors('to');
    }

    public function test_transactions_use_completion_time_and_leave_legacy_dates_unknown(): void
    {
        $center = $this->approvedSortingCenter();
        $area = Area::create(['sorting_center_id' => $center->sortingCenter->id, 'name' => 'Area A']);
        $completed = $this->parcel($area, 'DELIVERED', now()->subHours(5), now()->subHours(3));
        $completed->update(['delivered_at' => now()->subHour()]);
        $legacy = $this->parcel($area, 'DELIVERED', now()->subHours(4), now()->subHours(2));
        Sanctum::actingAs($center);

        $response = $this->getJson('/api/sorting-center/reports/transactions')->assertOk();
        $records = collect($response->json('data'))->keyBy('id');
        $this->assertSame($completed->delivered_at->format('Y-m-d H:i:s'), $records[$completed->id]['delivered_at']);
        $this->assertNull($records[$legacy->id]['delivered_at']);
    }

    private function approvedSortingCenter(): User
    {
        $user = User::factory()->create(['status' => 'approved', 'usertype' => 'sorting_center']);
        SortingCenter::create([
            'user_id' => $user->id,
            'business_name' => 'Approved Center',
            'approval_status' => 'approved',
        ]);
        $user->load('sortingCenter');

        return $user;
    }

    private function rider(int $sortingCenterId): Rider
    {
        $user = User::factory()->create(['status' => 'approved', 'usertype' => 'rider']);

        return Rider::create([
            'user_id' => $user->id,
            'sorting_center_id' => $sortingCenterId,
            'name' => 'Test Rider',
            'contact' => '09170000000',
            'vehicle_type' => 'motorcycle',
            'plate_number' => 'TEST-777',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);
    }

    private function parcel(Area $area, string $status, $sortedAt, $assignedAt = null, ?int $riderId = null): Parcel
    {
        $seller = User::factory()->create(['status' => 'approved', 'usertype' => 'seller']);
        $buyer = User::factory()->create(['status' => 'approved', 'usertype' => 'buyer']);

        return Parcel::create([
            'order_id' => uniqid('TEST-REPORT-'),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'sorting_center_id' => $area->sorting_center_id,
            'status' => $status,
            'delivery_province' => 'Metro Manila',
            'delivery_municipality' => 'Taguig',
            'delivery_barangay' => 'Fort Bonifacio',
            'sorted_area_id' => $area->id,
            'sorted_at' => $sortedAt,
            'assigned_at' => $assignedAt,
            'assigned_rider_id' => $riderId,
        ]);
    }
}
