<?php

namespace Tests\Feature\Logistics;

use App\Models\{Area, Parcel, PickupRequest, Rider, SortingCenter, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardDateFilterTest extends TestCase
{
    use RefreshDatabase;

    private SortingCenter $center;
    private SortingCenter $foreign;
    private User $seller;
    private User $buyer;
    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->center = $this->center('Owned');
        $this->foreign = $this->center('Foreign');
        $this->seller = User::factory()->create(['usertype' => 'seller']);
        $this->buyer = User::factory()->create(['usertype' => 'buyer']);
        Sanctum::actingAs($this->center->user);
    }

    // LOG-DASH-003: inclusive Manila dates, exclusive next-day UTC boundary.
    public function test_same_day_includes_both_manila_boundaries_for_all_parcel_queues(): void
    {
        $area = Area::create(['sorting_center_id' => $this->center->id, 'name' => 'Owned area']);
        foreach (['2026-10-16 15:59:59', '2026-10-16 16:00:00', '2026-10-17 15:59:59', '2026-10-17 16:00:00'] as $created) {
            $this->parcel($created);
            $this->parcel($created, area: $area);
            $this->parcel($created, status: 'OUT_FOR_DELIVERY', area: $area);
        }
        $this->range('2026-10-17', '2026-10-17')->assertOk()
            ->assertJsonPath('data.parcels_awaiting_sorting', 2)
            ->assertJsonPath('data.parcels_awaiting_rider_assignment', 2)
            ->assertJsonPath('data.parcels_out_for_delivery', 2);
    }

    public function test_multiple_days_filter_creation_time_instead_of_update_or_sort_time(): void
    {
        foreach (['2026-10-14 15:59:59', '2026-10-14 16:00:00', '2026-10-17 15:59:59', '2026-10-17 16:00:00'] as $created) {
            $this->parcel($created)->forceFill(['updated_at' => '2026-11-01 00:00:00'])->saveQuietly();
        }
        $this->range('2026-10-15', '2026-10-17')->assertOk()->assertJsonPath('data.parcels_awaiting_sorting', 2);
    }

    public function test_pickup_requests_use_their_own_creation_time_and_preserve_shared_pool_scope(): void
    {
        // Parcel timestamps intentionally differ from request timestamps.
        $oldParcel = $this->parcel('2026-01-01 00:00:00', status: 'PLACED');
        $newParcel = $this->parcel('2026-10-17 01:00:00', status: 'PLACED');
        $this->pickup($oldParcel, '2026-10-16 16:00:00', $this->center->id);
        $this->pickup($oldParcel, '2026-10-17 15:59:59', null);
        $this->pickup($oldParcel, '2026-10-17 01:00:00', $this->foreign->id);
        $this->pickup($newParcel, '2026-10-16 15:59:59', $this->center->id);
        $this->pickup($newParcel, '2026-10-17 16:00:00', null);
        $this->pickup($oldParcel, '2026-10-17 01:00:00', $this->center->id, 'completed');
        $this->range('2026-10-17', '2026-10-17')->assertOk()->assertJsonPath('data.pickup_requests_pending', 2);
    }

    public function test_date_filters_never_expand_company_scope_or_change_rider_counts(): void
    {
        $area = Area::create(['sorting_center_id' => $this->center->id, 'name' => 'Owned area']);
        $this->parcel('2026-10-17 01:00:00');
        $this->parcel('2026-10-17 01:00:00', center: $this->foreign);
        foreach (['pending', 'approved'] as $status) {
            $rider = Rider::create(['user_id' => User::factory()->create(['usertype' => 'rider'])->id,
                'sorting_center_id' => $this->center->id, 'name' => 'Fixture', 'contact' => '09170000000',
                'vehicle_type' => 'motorcycle', 'plate_number' => 'TEST-'.$status, 'approval_status' => $status, 'is_active' => true]);
            $rider->forceFill(['created_at' => '2026-01-01 00:00:00'])->saveQuietly();
            $rider->areas()->attach($area);
        }
        $this->range('2026-10-17', '2026-10-17')->assertOk()
            ->assertJsonPath('data.parcels_awaiting_sorting', 1)
            ->assertJsonPath('data.riders_pending_approval', 1)
            ->assertJsonPath('data.active_approved_riders', 1);
        $this->range('2026-12-01', '2026-12-01')->assertOk()
            ->assertJsonPath('data.parcels_awaiting_sorting', 0)
            ->assertJsonPath('data.riders_pending_approval', 1)
            ->assertJsonPath('data.active_approved_riders', 1);
    }

    public function test_omitted_or_cleared_dates_preserve_existing_all_time_counts_without_mutation(): void
    {
        $old = $this->parcel('2026-01-01 00:00:00');
        $this->parcel('2026-10-17 01:00:00');
        $pickup = $this->pickup($old, '2026-01-01 00:00:00', null);
        $before = [$old->fresh()->getAttributes(), $pickup->fresh()->getAttributes()];
        foreach (['/api/sorting-center/dashboard', '/api/sorting-center/dashboard?start_date=&end_date='] as $url) {
            $this->getJson($url)->assertOk()->assertJsonPath('data.parcels_awaiting_sorting', 2)
                ->assertJsonPath('data.pickup_requests_pending', 1);
        }
        $this->range('2026-10-17', '2026-10-17')->assertOk();
        $this->assertSame($before, [$old->fresh()->getAttributes(), $pickup->fresh()->getAttributes()]);
    }

    public function test_invalid_dates_arrays_reversed_and_partial_ranges_return_validation_errors(): void
    {
        foreach ([
            ['start_date' => '2026-10-17'], ['end_date' => '2026-10-17'],
            ['start_date' => '2026-10-18', 'end_date' => '2026-10-17'],
            ['start_date' => '2026-02-30', 'end_date' => '2026-03-01'],
            ['start_date' => '10/17/2026', 'end_date' => '2026-10-17'],
            ['start_date' => ['2026-10-17'], 'end_date' => '2026-10-17'],
            ['start_date' => '2026-10-17', 'end_date' => ['2026-10-17']],
            ['start_date' => "' OR 1=1 --", 'end_date' => '2026-10-17'],
        ] as $query) {
            $response = $this->getJson('/api/sorting-center/dashboard?'.http_build_query($query))->assertUnprocessable();
            $this->assertNotEmpty($response->json('errors'));
        }
    }

    public function test_leap_day_and_month_rollover_use_manila_midnight(): void
    {
        foreach (['2024-02-28 15:59:59', '2024-02-28 16:00:00', '2024-02-29 15:59:59', '2024-02-29 16:00:00'] as $created) {
            $this->parcel($created);
        }
        $this->range('2024-02-29', '2024-02-29')->assertOk()->assertJsonPath('data.parcels_awaiting_sorting', 2);
        $this->range('2024-03-01', '2024-03-01')->assertOk()->assertJsonPath('data.parcels_awaiting_sorting', 1);
    }

    private function range(string $start, string $end): \Illuminate\Testing\TestResponse
    {
        return $this->getJson('/api/sorting-center/dashboard?'.http_build_query(['start_date' => $start, 'end_date' => $end]));
    }

    private function center(string $name): SortingCenter
    {
        $user = User::factory()->create(['usertype' => 'sorting_center', 'status' => 'approved']);
        return SortingCenter::create(['user_id' => $user->id, 'business_name' => $name, 'approval_status' => 'approved']);
    }

    private function parcel(string $created, string $status = 'AT_SORTING_CENTER', ?Area $area = null, ?SortingCenter $center = null): Parcel
    {
        $parcel = Parcel::create(['order_id' => 'DATE-'.++$this->sequence, 'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id, 'sorting_center_id' => ($center ?? $this->center)->id,
            'status' => $status, 'sorted_area_id' => $area?->id, 'sorted_at' => $area ? now() : null,
            'delivery_province' => 'Metro Manila', 'delivery_municipality' => 'Quezon City', 'delivery_barangay' => 'Bagumbayan']);
        $parcel->forceFill(['created_at' => $created])->saveQuietly();
        return $parcel;
    }

    private function pickup(Parcel $parcel, string $created, ?int $center, string $status = 'pending'): PickupRequest
    {
        $request = PickupRequest::create(['parcel_id' => $parcel->id, 'seller_id' => $this->seller->id,
            'sorting_center_id' => $center, 'status' => $status]);
        $request->forceFill(['created_at' => $created])->saveQuietly();
        return $request;
    }
}
