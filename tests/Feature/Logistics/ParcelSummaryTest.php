<?php

namespace Tests\Feature\Logistics;

use App\Models\Parcel;
use App\Services\LogisticsParcelSummary;
use Laravel\Sanctum\Sanctum;

class ParcelSummaryTest extends CompleteWorkflowTest
{
    public function test_counts_and_paginated_lists_share_company_date_and_status_scope(): void
    {
        $sequence = 0;
        foreach (LogisticsParcelSummary::STATUSES as $statuses) {
            foreach ($statuses as $status) {
                foreach (range(1, 12) as $unused) {
                    $attributes = ['order_id' => 'SUMMARY-'.++$sequence, 'seller_id' => $this->item->product->seller_id,
                        'buyer_id' => $this->item->order->user_id, 'sorting_center_id' => $this->center->id,
                        'status' => $status, 'delivery_municipality' => '', 'delivery_province' => '', 'delivery_barangay' => ''];
                    Parcel::create($attributes)->forceFill(['created_at' => '2026-10-17 00:00:00'])->saveQuietly();
                    $attributes['order_id'] .= '-OLD';
                    Parcel::create($attributes)->forceFill(['created_at' => '2026-01-01 00:00:00'])->saveQuietly();
                    $attributes['order_id'] .= '-SHARED'; $attributes['sorting_center_id'] = null;
                    Parcel::create($attributes);
                }
            }
        }
        $snapshot = Parcel::orderBy('id')->get()->toArray();
        Sanctum::actingAs($this->center->user);
        $dates = 'start_date=2026-10-17&end_date=2026-10-17';
        $totals = $this->getJson('/api/sorting-center/dashboard?'.$dates)->assertOk()->json('data.parcel_totals');
        foreach (LogisticsParcelSummary::STATUSES as $bucket => $statuses) {
            $this->assertSame(count($statuses) * 12, $totals[$bucket]);
            $ids = [];
            for ($page = 1; $page <= (int) ceil($totals[$bucket] / 10); $page++) {
                $result = $this->getJson('/api/sorting-center/dashboard/parcels?'.$dates.'&bucket='.$bucket.'&page='.$page)->assertOk();
                $this->assertSame($totals[$bucket], $result->json('data.total'));
                foreach ($result->json('data.data') as $row) {
                    $this->assertSame($this->center->id, $row['sorting_center_id']);
                    $this->assertContains($row['status'], $statuses);
                    $ids[] = $row['id'];
                }
            }
            $this->assertCount($totals[$bucket], array_unique($ids));
        }
        $this->getJson('/api/sorting-center/dashboard/parcels?bucket=lost')->assertUnprocessable();
        $this->getJson('/api/sorting-center/dashboard/parcels?bucket=pending&start_date=2026-10-17')->assertUnprocessable();
        Sanctum::actingAs($this->pickupRider->user);
        $this->getJson('/api/sorting-center/dashboard/parcels?bucket=pending')->assertForbidden();
        $this->assertSame($snapshot, Parcel::orderBy('id')->get()->toArray());
    }
}
