<?php

namespace App\Http\Controllers;

use App\Models\Commission;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminReportingController extends Controller
{
    /**
     * Sales Summary
     *
     * Reports marketplace order performance within the selected date range.
     */
    public function salesSummary(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);

        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        /*
         * Base order query for the selected date range.
         */
        $orders = Order::query()
            ->whereBetween('created_at', [$start, $end]);

        /*
         * Completed sales.
         *
         * Your Order model can roll up to "completed" after all
         * order items are fulfilled. "delivered" is also included
         * for compatibility with the existing status structure.
         */
        $completed = (clone $orders)
            ->whereIn('status', ['completed', 'delivered']);

        /*
         * Cancelled orders.
         */
        $cancelled = (clone $orders)
            ->where('status', 'cancelled');

        /*
         * Gross order value:
         *
         * Count all orders except cancelled orders.
         * This prevents cancelled orders from being treated as sales.
         */
        $grossSales = (float) (clone $orders)
            ->where('status', '!=', 'cancelled')
            ->sum('total');

        /*
         * Net sales:
         *
         * Only completed/delivered orders are treated as finalized sales.
         */
        $netSales = (float) (clone $completed)
            ->sum('total');

        /*
         * Products sold:
         *
         * Only count quantities belonging to completed/delivered orders.
         *
         * This avoids counting products from cancelled or still-pending
         * orders as successfully sold.
         */
        $productsSold = (int) OrderItem::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('status', ['delivered', 'completed'])
            ->sum('quantity');

        /*
         * Order counts.
         */
        $totalOrders = (clone $orders)->count();
        $completedOrders = (clone $completed)->count();
        $cancelledOrders = (clone $cancelled)->count();

        /*
         * Pending / active orders are useful for the admin report
         * even though they are not finalized sales.
         */
        $activeOrders = (clone $orders)
            ->whereNotIn('status', ['completed', 'delivered', 'cancelled'])
            ->count();

        return Inertia::render('Admin/Reports', [
            'title' => 'Sales summary',
            'eyebrow' => 'Reports',
            'description' => 'Review sales performance, completed orders, products sold, and marketplace activity by date range.',
            'active' => 'reports',

            'report' => [
                'type' => 'sales',

                'from' => $from->toDateString(),
                'to' => $to->toDateString(),

                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'cancelled_orders' => $cancelledOrders,
                'active_orders' => $activeOrders,

                'products_sold' => $productsSold,

                'gross_sales' => $grossSales,
                'net_sales' => $netSales,

                /*
                 * Keep total_sales for compatibility with the
                 * existing Vue page.
                 */
                'total_sales' => $grossSales,
            ],
        ]);
    }

    /**
     * Commission Report
     *
     * Reports commission records already stored in the commissions table.
     *
     * IMPORTANT:
     * This method does NOT create commission records.
     * It only reports existing records.
     */
    public function commissionReport(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);

        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        /*
         * The commissions table already exists in your database.
         *
         * We report recorded commissions within the selected period.
         *
         * Cancelled commission records are excluded.
         */
        $commissionQuery = Commission::query()
            ->whereBetween('recorded_at', [$start, $end])
            ->where('status', '!=', 'cancelled')
            ->with([
                'seller:id,name,store_name',
                'order:id,order_number',
            ]);

        /*
         * Execute the query once.
         */
        $commissions = $commissionQuery
            ->orderByDesc('recorded_at')
            ->get();

        /*
         * Financial totals.
         */
        $eligibleSales = (float) $commissions->sum(
            fn ($commission) => (float) $commission->sale_amount
        );

        $totalCommission = (float) $commissions->sum(
            fn ($commission) => (float) $commission->commission_amount
        );

        $totalSellerAmount = (float) $commissions->sum(
            fn ($commission) => (float) $commission->seller_amount
        );

        /*
         * Use the actual commission rate stored in the records.
         *
         * If multiple rates exist, calculate a weighted effective rate
         * from the reported sales and commission totals.
         *
         * Example:
         * ₱1,000 sales / ₱100 commission = 10%.
         */
        $commissionRate = $eligibleSales > 0
            ? round(($totalCommission / $eligibleSales) * 100, 2)
            : null;

        /*
         * Group commissions by seller.
         */
        $commissionBySeller = $commissions
            ->groupBy('seller_id')
            ->map(function ($rows) {
                $seller = $rows->first()->seller;

                return [
                    'seller_id' => $rows->first()->seller_id,

                    'seller' => $seller?->store_name
                        ?? $seller?->name
                        ?? 'Seller',

                    'sales_amount' => (float) $rows->sum(
                        fn ($row) => (float) $row->sale_amount
                    ),

                    'commission_amount' => (float) $rows->sum(
                        fn ($row) => (float) $row->commission_amount
                    ),

                    'seller_amount' => (float) $rows->sum(
                        fn ($row) => (float) $row->seller_amount
                    ),
                ];
            })
            ->values()
            ->all();

        /*
         * Commission by order.
         */
        $commissionByOrder = $commissions
            ->map(function ($row) {
                return [
                    'id' => $row->id,

                    'order_id' => $row->order_id,

                    'order_number' => $row->order?->order_number
                        ?? ('#' . $row->order_id),

                    'seller' => $row->seller?->store_name
                        ?? $row->seller?->name
                        ?? 'Seller',

                    'sale_amount' => (float) $row->sale_amount,

                    'commission_rate' => (float) $row->commission_rate,

                    'commission_amount' => (float) $row->commission_amount,

                    'seller_amount' => (float) $row->seller_amount,

                    'status' => $row->status,

                    'recorded_at' => $row->recorded_at?->toDateTimeString(),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Admin/Reports', [
            'title' => 'Commission report',
            'eyebrow' => 'Finance',
            'description' => 'Track recorded marketplace commissions, seller earnings, and commission activity by date range.',
            'active' => 'finance',

            'report' => [
                'type' => 'commission',

                'from' => $from->toDateString(),
                'to' => $to->toDateString(),

                'eligible_sales' => $eligibleSales,

                /*
                 * This is calculated from actual commission records.
                 * It is null when there are no commission records.
                 */
                'commission_rate' => $commissionRate,

                'total_commission' => $totalCommission,

                'total_seller_amount' => $totalSellerAmount,

                'commission_records' => $commissions->count(),

                'commission_by_seller' => $commissionBySeller,

                'commission_by_order' => $commissionByOrder,
            ],
        ]);
    }

    /**
     * Resolve the requested report date range.
     */
    protected function resolveRange(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))
            : now()->subDays(29);

        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))
            : now();

        /*
         * If the user accidentally enters the dates backwards,
         * automatically correct the order.
         */
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }
}