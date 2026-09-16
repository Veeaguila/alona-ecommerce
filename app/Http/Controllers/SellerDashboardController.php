<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SellerDashboardController extends Controller
{
    private const COMPLETED = ['delivered', 'completed'];

    public function index(Request $request): Response
    {
        $sellerId = $request->user()->id;

        $itemsBase = fn () => OrderItem::query()->whereHas('product', fn ($q) => $q->where('seller_id', $sellerId));

        $totalSales = (clone $itemsBase())
            ->whereIn('status', self::COMPLETED)
            ->selectRaw('COALESCE(SUM(price * quantity), 0) as total')
            ->value('total');

        $totalOrders = (clone $itemsBase())->distinct('order_id')->count('order_id');
        $totalProducts = Product::where('seller_id', $sellerId)->count();
        $totalCustomers = Order::whereHas('items.product', fn ($q) => $q->where('seller_id', $sellerId))
            ->distinct('user_id')->count('user_id');

        $salesByDay = (clone $itemsBase())
            ->whereIn('status', self::COMPLETED)
            ->where('order_items.created_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(order_items.created_at) as day, SUM(price * quantity) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $salesChart = collect(range(6, 0))->map(function ($daysAgo) use ($salesByDay) {
            $date = now()->subDays($daysAgo);
            return [
                'label' => $date->format('D'),
                'total' => (float) ($salesByDay[$date->toDateString()] ?? 0),
            ];
        })->values();

        $recentOrders = Order::query()
            ->whereHas('items.product', fn ($q) => $q->where('seller_id', $sellerId))
            ->with(['user:id,name', 'items' => fn ($q) => $q
                ->whereHas('product', fn ($q2) => $q2->where('seller_id', $sellerId))
                ->with('product:id,name'),
            ])
            ->latest()
            ->take(5)
            ->get();

        $topProducts = OrderItem::query()
            ->select('product_id')
            ->selectRaw('SUM(quantity) as sold')
            ->selectRaw('SUM(price * quantity) as revenue')
            ->whereHas('product', fn ($q) => $q->where('seller_id', $sellerId))
            ->whereIn('status', self::COMPLETED)
            ->groupBy('product_id')
            ->orderByDesc('sold')
            ->take(4)
            ->with('product:id,name')
            ->get();

        $lowStock = Product::where('seller_id', $sellerId)
            ->where('stock', '<=', 5)
            ->orderBy('stock')
            ->take(5)
            ->get(['id', 'name', 'stock']);

        return Inertia::render('Seller/Dashboard', [
            'stats' => [
                'total_sales' => (float) $totalSales,
                'total_orders' => $totalOrders,
                'total_products' => $totalProducts,
                'total_customers' => $totalCustomers,
            ],
            'salesChart' => $salesChart,
            'recentOrders' => $recentOrders,
            'topProducts' => $topProducts,
            'lowStockProducts' => $lowStock,
        ]);
    }
}
