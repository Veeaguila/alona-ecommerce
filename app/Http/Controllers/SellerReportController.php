<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerReportController extends Controller
{
    private const COMPLETED = ['delivered', 'completed'];

    // Placeholder marketplace commission until an actual fee schedule exists.
    private const PLATFORM_FEE_RATE = 0.05;

    /**
     * Unified Seller Reports page.
     *
     * Includes:
     * - Financial summary
     * - Profit / net earnings
     * - Pending payments
     * - Sales performance
     * - Orders
     * - Units sold
     * - Average order value
     * - Top-selling products
     * - Daily sales
     * - From / To date filtering
     */
    public function reports(Request $request): Response
    {
        [$from, $to] = $this->resolveRange($request);

        $sellerId = $request->user()->id;

        /*
        |--------------------------------------------------------------------------
        | Seller order items within selected date range
        |--------------------------------------------------------------------------
        */

        $items = OrderItem::query()
            ->whereHas('product', function ($query) use ($sellerId) {
                $query->where('seller_id', $sellerId);
            })
            ->whereBetween(
                'order_items.created_at',
                [
                    $from->copy()->startOfDay(),
                    $to->copy()->endOfDay(),
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | Completed sales
        |--------------------------------------------------------------------------
        */

        $completed = (clone $items)
            ->whereIn('status', self::COMPLETED);

        /*
        |--------------------------------------------------------------------------
        | Pending sales
        |--------------------------------------------------------------------------
        */

        $pending = (clone $items)
            ->whereNotIn(
                'status',
                array_merge(self::COMPLETED, ['cancelled'])
            );

        /*
        |--------------------------------------------------------------------------
        | Financial calculations
        |--------------------------------------------------------------------------
        */

        $grossRevenue = (float) (
            (clone $completed)
                ->selectRaw('SUM(price * quantity) as total')
                ->value('total') ?? 0
        );

        $platformFees = round(
            $grossRevenue * self::PLATFORM_FEE_RATE,
            2
        );

        $netEarnings = round(
            $grossRevenue - $platformFees,
            2
        );

        $pendingPayments = (float) (
            (clone $pending)
                ->selectRaw('SUM(price * quantity) as total')
                ->value('total') ?? 0
        );

        /*
        |--------------------------------------------------------------------------
        | Sales performance
        |--------------------------------------------------------------------------
        */

        $completedOrders = (clone $completed)
            ->distinct()
            ->count('order_id');

        $unitsSold = (int) (
            (clone $completed)->sum('quantity')
        );

        $averageOrderValue = $completedOrders > 0
            ? round($grossRevenue / $completedOrders, 2)
            : 0;

        /*
        |--------------------------------------------------------------------------
        | Top-selling products
        |--------------------------------------------------------------------------
        */

        $topProducts = (clone $completed)
            ->select('product_id')
            ->selectRaw('SUM(quantity) as units_sold')
            ->selectRaw('SUM(price * quantity) as revenue')
            ->groupBy('product_id')
            ->orderByDesc('units_sold')
            ->with('product:id,name')
            ->take(10)
            ->get()
            ->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'name' => $item->product?->name ?? 'Unknown Product',
                    'units_sold' => (int) $item->units_sold,
                    'revenue' => (float) $item->revenue,
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Daily sales
        |--------------------------------------------------------------------------
        */

        $dailySales = (clone $completed)
            ->selectRaw(
                'DATE(order_items.created_at) as day'
            )
            ->selectRaw(
                'SUM(price * quantity) as revenue'
            )
            ->selectRaw(
                'SUM(quantity) as units_sold'
            )
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(function ($item) {
                return [
                    'day' => $item->day,
                    'revenue' => (float) $item->revenue,
                    'units_sold' => (int) $item->units_sold,
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Return unified Generate Report page
        |--------------------------------------------------------------------------
        */

        return Inertia::render('Seller/Reports', [
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],

            'summary' => [
                // Financial
                'gross_revenue' => $grossRevenue,
                'platform_fees' => $platformFees,
                'net_earnings' => $netEarnings,
                'pending_payments' => $pendingPayments,

                // Sales performance
                'orders' => $completedOrders,
                'units_sold' => $unitsSold,
                'revenue' => $grossRevenue,
                'average_order_value' => $averageOrderValue,
            ],

            'topProducts' => $topProducts,

            'dailySales' => $dailySales,
        ]);
    }

    /**
     * Resolve report date range.
     *
     * Default: last 30 days.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))
            : now()->subDays(29);

        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))
            : now();

        /*
        |--------------------------------------------------------------------------
        | Automatically correct reversed date ranges
        |--------------------------------------------------------------------------
        */

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }
}