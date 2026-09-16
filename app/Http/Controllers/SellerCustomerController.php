<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SellerCustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $sellerId = $request->user()->id;

        $customers = User::query()
            ->where('usertype', 'buyer')
            ->whereHas('orders.items.product', fn ($q) => $q->where('seller_id', $sellerId))
            ->withCount(['orders as orders_count' => fn ($q) => $q->whereHas('items.product', fn ($q2) => $q2->where('seller_id', $sellerId))])
            ->get(['id', 'name', 'email', 'contact_no'])
            ->map(function ($customer) use ($sellerId) {
                $spent = DB::table('order_items')
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->join('products', 'products.id', '=', 'order_items.product_id')
                    ->where('orders.user_id', $customer->id)
                    ->where('products.seller_id', $sellerId)
                    ->whereIn('order_items.status', ['delivered', 'completed'])
                    ->selectRaw('SUM(order_items.price * order_items.quantity) as total')
                    ->value('total');

                $customer->total_spent = (float) ($spent ?? 0);

                return $customer;
            })
            ->sortByDesc('total_spent')
            ->values();

        return Inertia::render('Seller/Customers', ['customers' => $customers]);
    }
}
