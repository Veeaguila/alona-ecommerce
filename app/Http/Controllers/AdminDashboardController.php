<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        $stats = [
            'buyers' => User::where('usertype', 'buyer')->count(),
            'sellers' => User::where('usertype', 'seller')->count(),
            'riders' => User::where('usertype', 'rider')->count(),
            'products' => Product::count(),
            'orders' => Order::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'completed_orders' => Order::whereIn('status', ['completed', 'delivered'])->count(),
            'cancelled_orders' => Order::where('status', 'cancelled')->count(),
            'gross_sales' => (float) Order::sum('total'),
            'platform_commission' => (float) (Order::sum('total') * 0.10),
            'pending_registrations' => User::whereIn('usertype', ['buyer', 'seller', 'rider'])
                ->where('status', 'pending')
                ->count(),
        ];

        $applications = User::query()
            ->whereIn('usertype', ['buyer', 'seller', 'rider'])
            ->where('status', 'pending')
            ->latest()
            ->limit(8)
            ->get([
                'id',
                'name',
                'email',
                'usertype',
                'status',
                'created_at',
            ]);

        $orders = Order::query()
            ->with(['user:id,name,email'])
            ->latest('created_at')
            ->limit(5)
            ->get([
                'id',
                'order_number',
                'user_id',
                'total',
                'status',
                'created_at',
            ]);

        $notifications = [
            [
                'title' => 'Seller applications',
                'message' => $stats['pending_registrations'] . ' application(s) awaiting review',
                'link' => route('admin.seller-applications'),
            ],
            [
                'title' => 'Product compliance',
                'message' => 'Review product listings and seller category fit',
                'link' => route('admin.compliance'),
            ],
            [
                'title' => 'Complaints and disputes',
                'message' => 'Resolve buyer, seller, and rider issues pending review',
                'link' => route('admin.complaints'),
            ],
        ];

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'applications' => $applications,
            'orders' => $orders,
            'notifications' => $notifications,
        ]);
    }
}
