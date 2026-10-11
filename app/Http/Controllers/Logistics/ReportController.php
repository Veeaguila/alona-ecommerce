<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;

use App\Models\Parcel;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    private const REPORTABLE_STATUSES = [
        'AT_SORTING_CENTER',
        'OUT_FOR_DELIVERY',
        'DELIVERED',
        'FAILED_RETURNED',
    ];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = CarbonImmutable::createFromFormat('Y-m-d', $validated['from'])->startOfDay();
        $to = CarbonImmutable::createFromFormat('Y-m-d', $validated['to'])->endOfDay();
        $sortingCenterId = $request->user()->sortingCenter->id;

        $parcels = Parcel::query()
            ->inSortingCenterWorkflow($sortingCenterId)
            ->whereBetween('sorted_at', [$from, $to])
            ->get(['status', 'sorted_at', 'assigned_at']);

        $statusBreakdown = array_fill_keys(self::REPORTABLE_STATUSES, 0);
        $turnaroundSeconds = [];

        foreach ($parcels as $parcel) {
            $statusBreakdown[$parcel->status] = ($statusBreakdown[$parcel->status] ?? 0) + 1;

            if ($parcel->sorted_at && $parcel->assigned_at) {
                $turnaroundSeconds[] = max(0, $parcel->sorted_at->diffInSeconds($parcel->assigned_at));
            }
        }

        $averageSeconds = $turnaroundSeconds === []
            ? null
            : (int) round(array_sum($turnaroundSeconds) / count($turnaroundSeconds));

        return response()->json([
            'message' => 'Parcel report generated successfully.',
            'data' => [
                'from' => $validated['from'],
                'to' => $validated['to'],
                'total_parcels_processed' => $parcels->count(),
                'status_breakdown' => $statusBreakdown,
                'average_turnaround_seconds' => $averageSeconds,
                'average_turnaround' => $averageSeconds === null
                    ? null
                    : $this->formatDuration($averageSeconds),
            ],
        ]);
    }

    public function deliveryStats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = CarbonImmutable::createFromFormat('Y-m-d', $validated['from'])->startOfDay();
        $to = CarbonImmutable::createFromFormat('Y-m-d', $validated['to'])->endOfDay();
        $sortingCenterId = $request->user()->sortingCenter->id;

        $parcels = Parcel::query()
            ->inSortingCenterWorkflow($sortingCenterId)
            ->whereBetween('sorted_at', [$from, $to])
            ->get(['sorted_at', 'status']);

        $stats = [];
        $currentDate = $from->copy();

        while ($currentDate <= $to) {
            $dateStr = $currentDate->format('Y-m-d');
            $dayStart = $currentDate->copy()->startOfDay();
            $dayEnd = $currentDate->copy()->endOfDay();

            $dayParcels = $parcels->filter(function ($parcel) use ($dayStart, $dayEnd) {
                return $parcel->sorted_at >= $dayStart && $parcel->sorted_at <= $dayEnd;
            });

            $stats[] = [
                'date' => $dateStr,
                'total' => $dayParcels->count(),
                'delivered' => $dayParcels->where('status', 'DELIVERED')->count(),
                'failed' => $dayParcels->where('status', 'FAILED_RETURNED')->count(),
                'pending' => $dayParcels->whereIn('status', ['AT_SORTING_CENTER', 'OUT_FOR_DELIVERY'])->count(),
            ];

            $currentDate = $currentDate->addDay();
        }

        return response()->json([
            'message' => 'Delivery stats retrieved successfully.',
            'data' => $stats,
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 50;
        $sortingCenterId = $request->user()->sortingCenter->id;

        $query = Parcel::query()
            ->inSortingCenterWorkflow($sortingCenterId)
            ->with(['assignedRider', 'buyer', 'seller'])
            ->select([
                'parcels.id',
                'parcels.order_id',
                'parcels.status',
                'parcels.delivery_province',
                'parcels.delivery_municipality',
                'parcels.sorted_at',
                'parcels.assigned_at',
                'parcels.delivered_at',
                'parcels.assigned_rider_id',
                'parcels.buyer_id',
                'parcels.seller_id',
            ]);

        if (!empty($validated['from'])) {
            $from = CarbonImmutable::createFromFormat('Y-m-d', $validated['from'])->startOfDay();
            $query->where('parcels.sorted_at', '>=', $from);
        }

        if (!empty($validated['to'])) {
            $to = CarbonImmutable::createFromFormat('Y-m-d', $validated['to'])->endOfDay();
            $query->where('parcels.sorted_at', '<=', $to);
        }

        $parcels = $query->orderByDesc('parcels.sorted_at')->paginate($perPage, ['*'], 'page', $page);

        $data = $parcels->map(function ($parcel) {
            $deliveredAt = null;
            if ($parcel->status === 'DELIVERED') {
                $deliveredAt = $parcel->delivered_at?->format('Y-m-d H:i:s');
            }

            return [
                'id' => $parcel->id,
                'parcel_id' => $parcel->id,
                'order_id' => $parcel->order_id,
                'buyer_name' => $parcel->buyer?->name ?? 'Unknown',
                'seller_name' => $parcel->seller?->name ?? 'Unknown',
                'rider_name' => $parcel->assignedRider?->name ?? 'Unassigned',
                'status' => $parcel->status,
                'delivery_province' => $parcel->delivery_province,
                'delivery_municipality' => $parcel->delivery_municipality,
                'sorted_at' => $parcel->sorted_at?->format('Y-m-d H:i:s'),
                'delivered_at' => $deliveredAt,
            ];
        });

        return response()->json([
            'message' => 'Transactions retrieved successfully.',
            'data' => $data,
            'pagination' => [
                'total' => $parcels->total(),
                'per_page' => $parcels->perPage(),
                'current_page' => $parcels->currentPage(),
                'last_page' => $parcels->lastPage(),
                'from' => $parcels->firstItem(),
                'to' => $parcels->lastItem(),
            ],
        ]);
    }

    private function formatDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0) {
            return $hours.'h '.$minutes.'m';
        }

        return $minutes.'m';
    }
}
