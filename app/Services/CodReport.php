<?php

namespace App\Services;

use App\Models\{CodCollection, CodRemittance};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CodReport
{
    public const COLUMNS = ['record_type' => 'Record type', 'record_id' => 'Record ID', 'order_number' => 'Order',
        'tracking_reference' => 'Tracking reference', 'rider_name' => 'Rider', 'collected_amount' => 'Collected',
        'submitted_amount' => 'Submitted', 'received_amount' => 'Confirmed received', 'discrepancy_difference' => 'Recorded discrepancy',
        'discrepancy_status' => 'Discrepancy status', 'discrepancy_reason' => 'Discrepancy reason', 'resolution' => 'Approved resolution',
        'status' => 'Status', 'recorded_at' => 'Recorded at'];

    public function bounds(array $range): array
    {
        return [CarbonImmutable::createFromFormat('!Y-m-d', $range['from'], 'Asia/Manila')->utc(),
            CarbonImmutable::createFromFormat('!Y-m-d', $range['to'], 'Asia/Manila')->addDay()->utc()];
    }
    public function query(int $centerId, array $range)
    {
        [$start, $end] = $this->bounds($range);
        $collections = DB::table('cod_collections as c')->join('riders as r', 'r.id', '=', 'c.rider_id')
            ->join('orders as o', 'o.id', '=', 'c.order_id')->join('parcels as p', 'p.id', '=', 'c.parcel_id')
            ->where('c.sorting_center_id', $centerId)->where('c.collected_at', '>=', $start)->where('c.collected_at', '<', $end)
            ->selectRaw("'collection' as record_type, c.id as record_id, o.order_number, p.order_id as tracking_reference, r.name as rider_name,
                c.amount as collected_amount, NULL as submitted_amount, NULL as received_amount, NULL as discrepancy_difference,
                NULL as discrepancy_status, NULL as discrepancy_reason, NULL as resolution, 'collected' as status, c.collected_at as recorded_at");
        $remittances = DB::table('cod_remittances as m')->join('riders as r', 'r.id', '=', 'm.rider_id')
            ->leftJoin('cod_discrepancies as d', 'd.cod_remittance_id', '=', 'm.id')
            ->where('m.sorting_center_id', $centerId)->whereRaw('COALESCE(m.confirmed_at, m.created_at) >= ?', [$start])
            ->whereRaw('COALESCE(m.confirmed_at, m.created_at) < ?', [$end])
            ->selectRaw("'remittance' as record_type, m.id as record_id, NULL as order_number, NULL as tracking_reference, r.name as rider_name,
                NULL as collected_amount, m.submitted_amount, m.received_amount, d.difference as discrepancy_difference,
                d.status as discrepancy_status, d.reason as discrepancy_reason, d.resolution, m.status, COALESCE(m.confirmed_at, m.created_at) as recorded_at");
        return DB::query()->fromSub($collections->unionAll($remittances), 'cash_events');
    }
    public function row(object $record): array
    {
        $row = (array) $record; $ledger = app(CodLedger::class);
        foreach (['collected_amount', 'submitted_amount', 'received_amount', 'discrepancy_difference'] as $field) {
            $row[$field] = $row[$field] === null ? null : $ledger->money($ledger->cents((string) $row[$field]));
        }
        $row['recorded_at'] = CarbonImmutable::parse($row['recorded_at'], config('app.timezone'))->utc()->toISOString();
        return $row;
    }
    public function totals(int $centerId, array $range): array
    {
        [$start, $end] = $this->bounds($range); $ledger = app(CodLedger::class);
        $collected = $ledger->sum(CodCollection::where('sorting_center_id', $centerId)->where('collected_at', '>=', $start)->where('collected_at', '<', $end), 'amount');
        $received = $ledger->sum(CodRemittance::where('sorting_center_id', $centerId)->where('status', 'confirmed')->where('confirmed_at', '>=', $start)->where('confirmed_at', '<', $end), 'received_amount');
        return ['collected' => $ledger->money($collected), 'received' => $ledger->money($received), 'difference' => $ledger->money($received - $collected)];
    }
}
