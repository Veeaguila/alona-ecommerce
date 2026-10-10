<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturnRequest;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\Review;
use App\Models\SellerNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class SellerDashboardController extends Controller
{
    private const COMPLETED = ['delivered', 'completed'];

    public function index(Request $request): Response
    {
        $sellerId = $request->user()->id;

        $itemsBase = fn () => OrderItem::query()->whereHas('product', fn ($q) => $q->where('seller_id', $sellerId));

        /*
        |--------------------------------------------------------------------------
        | 1. CORE FINANCIAL & STORE STATS (SELLER-05)
        |--------------------------------------------------------------------------
        */

        // Total completed gross sales
        $totalSales = (float) ((clone $itemsBase())
            ->whereIn('status', self::COMPLETED)
            ->selectRaw('COALESCE(SUM(price * quantity), 0) as total')
            ->value('total') ?? 0);

        // Pending sales (orders currently in progress / not completed or cancelled)
        $pendingSales = (float) ((clone $itemsBase())
            ->whereNotIn('status', array_merge(self::COMPLETED, ['cancelled']))
            ->selectRaw('COALESCE(SUM(price * quantity), 0) as total')
            ->value('total') ?? 0);

        // Platform commission fee rate (connected to Admin PlatformSetting if exists, default 5%)
        $platformFeeRatePercent = 5.0;
        if (Schema::hasTable('platform_settings')) {
            $settingVal = PlatformSetting::where('key', 'marketplace_commission_rate')
                ->where('is_active', true)
                ->value('value');
            if ($settingVal !== null && is_numeric($settingVal)) {
                $platformFeeRatePercent = (float) $settingVal;
            }
        }

        $platformFeeRate = $platformFeeRatePercent / 100;
        $estimatedPlatformFees = round($totalSales * $platformFeeRate, 2);
        $estimatedNetProfit = round(max(0, $totalSales - $estimatedPlatformFees), 2);
        $estimatedNetMargin = $totalSales > 0 ? round(($estimatedNetProfit / $totalSales) * 100, 1) : 0;

        $totalOrders = (clone $itemsBase())->distinct('order_id')->count('order_id');
        $totalProducts = Product::where('seller_id', $sellerId)
            ->where('status', '!=', 'archived')
            ->count();
        $totalCustomers = Order::whereHas('items.product', fn ($q) => $q->where('seller_id', $sellerId))
            ->distinct('user_id')
            ->count('user_id');

        /*
        |--------------------------------------------------------------------------
        | 2. ORDER STATUS METRIC COUNTERS (SELLER-01)
        |--------------------------------------------------------------------------
        */

        $returnedCount = 0;
        if (Schema::hasTable('order_return_requests')) {
            $returnedCount = OrderReturnRequest::where('seller_id', $sellerId)->count();
        }

        $orderMetrics = [
            'pending' => (clone $itemsBase())->where('status', 'pending')->count(),
            'to_ship' => (clone $itemsBase())->whereIn('status', ['processing', 'packed', 'ready_for_pickup'])->count(),
            'in_transit' => (clone $itemsBase())->whereIn('status', ['shipped', 'out_for_delivery'])->count(),
            'delivered' => (clone $itemsBase())->whereIn('status', self::COMPLETED)->count(),
            'cancelled' => (clone $itemsBase())->where('status', 'cancelled')->count(),
            'returned' => $returnedCount,
        ];

        /*
        |--------------------------------------------------------------------------
        | 3. INVENTORY & STOCK ALERT INDICATORS (SELLER-04)
        |--------------------------------------------------------------------------
        */

        $outOfStockCount = Product::where('seller_id', $sellerId)
            ->where('status', '!=', 'archived')
            ->where('stock', 0)
            ->count();

        $lowStockCount = Product::where('seller_id', $sellerId)
            ->where('status', '!=', 'archived')
            ->whereBetween('stock', [1, 5])
            ->count();

        $stockAlertProducts = Product::where('seller_id', $sellerId)
            ->where('status', '!=', 'archived')
            ->where('stock', '<=', 5)
            ->orderBy('stock')
            ->take(6)
            ->get(['id', 'name', 'stock', 'price', 'image_path']);

        /*
        |--------------------------------------------------------------------------
        | 4. 7-DAY SALES PERFORMANCE CHART
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | 5. RECENT ORDERS
        |--------------------------------------------------------------------------
        */

        $recentOrders = Order::query()
            ->whereHas('items.product', fn ($q) => $q->where('seller_id', $sellerId))
            ->with(['user:id,name', 'items' => fn ($q) => $q
                ->whereHas('product', fn ($q2) => $q2->where('seller_id', $sellerId))
                ->with('product:id,name,image_path'),
            ])
            ->latest()
            ->take(5)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | 6. BEST-SELLING PRODUCTS
        |--------------------------------------------------------------------------
        */

        $topProducts = OrderItem::query()
            ->select('product_id')
            ->selectRaw('SUM(quantity) as sold')
            ->selectRaw('SUM(price * quantity) as revenue')
            ->whereHas('product', fn ($q) => $q->where('seller_id', $sellerId))
            ->whereIn('status', self::COMPLETED)
            ->groupBy('product_id')
            ->orderByDesc('sold')
            ->take(4)
            ->with('product:id,name,image_path')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | 7. RECENT REVIEWS FEED & STORE RATING (SELLER-02)
        |--------------------------------------------------------------------------
        */

        $recentReviews = Review::query()
            ->whereHas('product', fn ($q) => $q->where('seller_id', $sellerId))
            ->with([
                'user:id,name',
                'product:id,name,image_path',
            ])
            ->latest()
            ->take(5)
            ->get();

        $ratingAvg = Review::whereHas('product', fn ($q) => $q->where('seller_id', $sellerId))->avg('rating');
        $ratingCount = Review::whereHas('product', fn ($q) => $q->where('seller_id', $sellerId))->count();

        /*
        |--------------------------------------------------------------------------
        | 8. RECENT NOTIFICATIONS & PLATFORM ANNOUNCEMENTS (SELLER-03)
        |--------------------------------------------------------------------------
        */

        $recentNotifications = collect();
        $unreadNotificationsCount = 0;
        if (Schema::hasTable('seller_notifications')) {
            $recentNotifications = SellerNotification::query()
                ->where('user_id', $sellerId)
                ->latest()
                ->take(6)
                ->get();

            $unreadNotificationsCount = SellerNotification::query()
                ->where('user_id', $sellerId)
                ->whereNull('read_at')
                ->count();
        }

        $systemAnnouncements = collect();
        if (Schema::hasTable('announcements')) {
            $systemAnnouncements = Announcement::query()
                ->where('status', 'published')
                ->latest('published_at')
                ->take(3)
                ->get(['id', 'title', 'body', 'published_at']);
        }

        return Inertia::render('Seller/Dashboard', [
            'stats' => [
                'total_sales' => $totalSales,
                'total_orders' => $totalOrders,
                'total_products' => $totalProducts,
                'total_customers' => $totalCustomers,
                'pending_sales' => $pendingSales,
                'estimated_net_profit' => $estimatedNetProfit,
                'estimated_platform_fees' => $estimatedPlatformFees,
                'estimated_net_margin' => $estimatedNetMargin,
                'platform_fee_rate' => $platformFeeRatePercent,
            ],
            'orderMetrics' => $orderMetrics,
            'stockMetrics' => [
                'out_of_stock' => $outOfStockCount,
                'low_stock' => $lowStockCount,
            ],
            'salesChart' => $salesChart,
            'recentOrders' => $recentOrders,
            'topProducts' => $topProducts,
            'lowStockProducts' => $stockAlertProducts,
            'recentReviews' => $recentReviews,
            'reviewsSummary' => [
                'average_rating' => round((float) ($ratingAvg ?? 0), 1),
                'total_count' => $ratingCount,
            ],
            'recentNotifications' => $recentNotifications,
            'unreadNotificationsCount' => $unreadNotificationsCount,
            'systemAnnouncements' => $systemAnnouncements,
        ]);
    }
}
