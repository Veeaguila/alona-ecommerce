<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturnRequest;
use App\Models\SellerPayoutRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SellerReportController extends Controller
{
    // Orders/items must be completed (SEL-EARN-002, A15). Delivered remains pending until buyer confirms receipt.
    private const COMPLETED = ['completed'];

    // Platform marketplace commission rate (5%)
    private const PLATFORM_FEE_RATE = 0.05;

    /**
     * Unified Seller Reports page.
     *
     * Includes:
     * - Financial summary (Gross, Commission, Net Earnings, Pending)
     * - Balances (Available balance, Pending balance)
     * - Sales performance (Orders, Units sold, Average order value)
     * - Payout requests management
     * - Top-selling products
     * - Daily sales
     * - Date filtering (today, 7 days, 30 days, custom range)
     */
    public function reports(Request $request): Response
    {
        OrderReturnRequest::autoApproveExpiredPendingRequests();

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
        | Completed sales (excluding cancelled, refunded, or return-approved items - SEL-RET-007, A15)
        |--------------------------------------------------------------------------
        */

        $completed = (clone $items)
            ->whereIn('status', self::COMPLETED)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->whereDoesntHave('returnRequests', function ($q) {
                $q->whereIn('status', ['approved', 'item_returned', 'refunded', 'completed']);
            })
            ->whereHas('order', function ($q) {
                $q->whereNotIn('status', ['cancelled', 'refunded']);
            });

        /*
        |--------------------------------------------------------------------------
        | Pending sales (excluding cancelled, refunded, or return-approved items - SEL-RET-002, A15)
        |--------------------------------------------------------------------------
        */

        $pending = (clone $items)
            ->whereNotIn(
                'status',
                array_merge(self::COMPLETED, ['cancelled', 'refunded'])
            )
            ->whereDoesntHave('returnRequests', function ($q) {
                $q->whereIn('status', ['approved', 'item_returned', 'refunded', 'completed']);
            })
            ->whereHas('order', function ($q) {
                $q->whereNotIn('status', ['cancelled', 'refunded']);
            });

        /*
        |--------------------------------------------------------------------------
        | Financial calculations for the selected period (SEL-EARN-001, A8, A9)
        |--------------------------------------------------------------------------
        */

        $completedOrderIds = (clone $completed)->distinct()->pluck('order_id');
        $completedOrders = Order::with(['voucher', 'items.product'])
            ->whereIn('id', $completedOrderIds)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->get();

        $grossRevenue = 0.0;
        $platformFees = 0.0;
        $netEarnings = 0.0;

        foreach ($completedOrders as $order) {
            $breakdown = $this->calculateOrderEarnings($order, $sellerId);
            $grossRevenue += $breakdown['gross'];
            $platformFees += $breakdown['commission'];
            $netEarnings += $breakdown['net_earnings'];
        }

        $grossRevenue = round($grossRevenue, 2);
        $platformFees = round($platformFees, 2);
        $netEarnings = round($netEarnings, 2);

        $pendingOrderIds = (clone $pending)->distinct()->pluck('order_id');
        $pendingOrders = Order::with(['voucher', 'items.product'])
            ->whereIn('id', $pendingOrderIds)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->get();

        $pendingPayments = (float) (
            (clone $pending)
                ->selectRaw('SUM(price * quantity) as total')
                ->value('total') ?? 0
        );
        $pendingPayments = round($pendingPayments, 2);

        /*
        |--------------------------------------------------------------------------
        | Balances (SEL-EARN-002, SEL-EARN-005, A15)
        |--------------------------------------------------------------------------
        */

        $balances = $this->calculateSellerBalances($sellerId);

        /*
        |--------------------------------------------------------------------------
        | Sales performance
        |--------------------------------------------------------------------------
        */

        $completedOrdersCount = $completedOrders->count();

        $unitsSold = (int) (
            (clone $completed)->sum('quantity')
        );

        $averageOrderValue = $completedOrdersCount > 0
            ? round($grossRevenue / $completedOrdersCount, 2)
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
            ->selectRaw('DATE(order_items.created_at) as day')
            ->selectRaw('SUM(price * quantity) as revenue')
            ->selectRaw('SUM(quantity) as units_sold')
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
        | Recent Payout Requests
        |--------------------------------------------------------------------------
        */

        $payoutRequests = SellerPayoutRequest::where('seller_id', $sellerId)
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($req) {
                return [
                    'id' => $req->id,
                    'payout_reference' => $req->payout_reference,
                    'amount' => (float) $req->amount,
                    'payout_method' => $req->payout_method,
                    'account_name' => $req->account_name,
                    'account_number' => $req->account_number,
                    'bank_name' => $req->bank_name,
                    'status' => $req->status,
                    'rejection_reason' => $req->rejection_reason,
                    'created_at' => $req->created_at?->format('Y-m-d H:i'),
                ];
            });

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
                // Financial for selected range
                'gross_revenue' => $grossRevenue,
                'platform_fees' => $platformFees,
                'net_earnings' => $netEarnings,
                'pending_payments' => $pendingPayments,

                // Balances
                'available_balance' => $balances['available_balance'],
                'total_earned' => $balances['total_earned'],
                'pending_balance' => $balances['pending_balance'],
                'pending_payout' => $balances['pending_payout'],

                // Sales performance
                'orders' => $completedOrdersCount,
                'units_sold' => $unitsSold,
                'revenue' => $grossRevenue,
                'average_order_value' => $averageOrderValue,
            ],

            'balances' => $balances,
            'payoutRequests' => $payoutRequests,
            'topProducts' => $topProducts,
            'dailySales' => $dailySales,
        ]);
    }

    /**
     * Calculate net earnings for a single order and seller (SEL-EARN-001, A8, A9).
     *
     * Gross: Sum of seller's items in order.
     * Seller voucher discount: Deducted from gross if seller-funded.
     * Platform vouchers (e.g. PLAT100) and shipping vouchers (e.g. FREESHIP) are NOT deducted.
     * Shipping fees are excluded from seller gross and never deducted.
     * Commission: 5% of item subtotal after seller-funded discount.
     * Net: Gross - Seller Voucher - Commission.
     */
    public function calculateOrderEarnings(Order $order, int $sellerId): array
    {
        $sellerItems = $order->items->filter(function ($item) use ($sellerId) {
            return (int) ($item->product?->seller_id) === (int) $sellerId
                && !in_array($item->status, ['cancelled', 'refunded']);
        });

        $gross = 0.0;
        foreach ($sellerItems as $item) {
            $gross += (float) $item->price * (int) $item->quantity;
        }
        $gross = round($gross, 2);

        $sellerVoucherDiscount = 0.0;
        $platformVoucherDiscount = 0.0;
        $shippingDiscount = (float) ($order->shipping_discount ?? 0);

        if ($order->voucher_id && $order->voucher) {
            $voucher = $order->voucher;
            $isPlatform = (bool) ($voucher->is_platform || $voucher->seller_id === null || $voucher->type === 'free_shipping');
            $discount = (float) ($order->discount ?? 0);

            if ($isPlatform) {
                $platformVoucherDiscount = $discount;
            } elseif ((int) $voucher->seller_id === (int) $sellerId) {
                // Store vouchers are seller-funded (A8)
                $sellerVoucherDiscount = min($gross, $discount);
            }
        }

        $sellerVoucherDiscount = round($sellerVoucherDiscount, 2);
        $netSubtotal = round(max(0, $gross - $sellerVoucherDiscount), 2);

        // Commission: 5% of item subtotal after seller-funded discount (A9)
        $commissionRate = self::PLATFORM_FEE_RATE;
        $commission = round($netSubtotal * $commissionRate, 2);

        $netEarnings = round(max(0, $netSubtotal - $commission), 2);

        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'gross' => $gross,
            'seller_voucher_discount' => $sellerVoucherDiscount,
            'platform_voucher_discount' => $platformVoucherDiscount,
            'shipping_discount' => $shippingDiscount,
            'shipping_fee' => (float) ($order->shipping_fee ?? 0),
            'net_subtotal' => $netSubtotal,
            'commission_rate' => $commissionRate,
            'commission' => $commission,
            'net_earnings' => $netEarnings,
            'status' => $order->status,
        ];
    }

    /**
     * Calculate current available and pending balances for a seller (SEL-EARN-002, SEL-EARN-005, A15).
     */
    public function calculateSellerBalances(int $sellerId): array
    {
        // 1. All completed orders for this seller
        $completedOrderIds = OrderItem::query()
            ->whereHas('product', fn ($q) => $q->where('seller_id', $sellerId))
            ->where('status', 'completed')
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->whereDoesntHave('returnRequests', fn ($q) => $q->whereIn('status', ['approved', 'item_returned', 'refunded', 'completed']))
            ->distinct()
            ->pluck('order_id');

        $completedOrders = Order::with(['voucher', 'items.product'])
            ->whereIn('id', $completedOrderIds)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->get();

        $totalEarned = 0.0;
        foreach ($completedOrders as $order) {
            $breakdown = $this->calculateOrderEarnings($order, $sellerId);
            $totalEarned += $breakdown['net_earnings'];
        }
        $totalEarned = round($totalEarned, 2);

        // 2. Pending orders (orders in progress: pending, processing, packed, ready_for_pickup, shipped, delivered)
        $pendingOrderIds = OrderItem::query()
            ->whereHas('product', fn ($q) => $q->where('seller_id', $sellerId))
            ->whereNotIn('status', ['completed', 'cancelled', 'refunded'])
            ->whereDoesntHave('returnRequests', fn ($q) => $q->whereIn('status', ['approved', 'item_returned', 'refunded', 'completed']))
            ->distinct()
            ->pluck('order_id');

        $pendingOrders = Order::with(['voucher', 'items.product'])
            ->whereIn('id', $pendingOrderIds)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->get();

        $pendingEarnings = 0.0;
        foreach ($pendingOrders as $order) {
            $breakdown = $this->calculateOrderEarnings($order, $sellerId);
            $pendingEarnings += $breakdown['net_earnings'];
        }
        $pendingEarnings = round($pendingEarnings, 2);

        // 3. Deducted payouts (pending, processing, approved, completed hold the balance)
        $deductedPayouts = (float) SellerPayoutRequest::where('seller_id', $sellerId)
            ->whereIn('status', ['pending', 'processing', 'approved', 'completed'])
            ->sum('amount');

        $availableBalance = round(max(0, $totalEarned - $deductedPayouts), 2);

        $pendingPayout = (float) SellerPayoutRequest::where('seller_id', $sellerId)
            ->whereIn('status', ['pending', 'processing'])
            ->sum('amount');

        $hasPendingPayout = SellerPayoutRequest::where('seller_id', $sellerId)
            ->whereIn('status', ['pending', 'processing'])
            ->exists();

        return [
            'total_earned' => $totalEarned,
            'available_balance' => $availableBalance,
            'pending_balance' => $pendingEarnings,
            'pending_payout' => round($pendingPayout, 2),
            'total_withdrawn' => round((float) SellerPayoutRequest::where('seller_id', $sellerId)->where('status', 'completed')->sum('amount'), 2),
            'has_pending_payout' => $hasPendingPayout,
        ];
    }

    /**
     * Return detailed earnings breakdown for an order (SEL-EARN-001).
     */
    public function orderEarningsBreakdown(Request $request, Order $order): JsonResponse
    {
        $sellerId = $request->user()->id;

        $hasItems = $order->items()
            ->whereHas('product', fn ($q) => $q->where('seller_id', $sellerId))
            ->exists();

        abort_unless($hasItems, 403);

        $order->load(['voucher', 'items.product']);
        $breakdown = $this->calculateOrderEarnings($order, $sellerId);

        return response()->json($breakdown);
    }

    /**
     * Submit a payout request (SEL-EARN-005, SEL-EARN-006, SEL-EARN-007, A15, A37).
     */
    public function storePayoutRequest(Request $request)
    {
        $request->validate([
            'amount' => ['required', 'numeric'],
            'payout_method' => ['nullable', 'string', 'max:50'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:100'],
        ]);

        $seller = $request->user();
        $amount = (float) $request->input('amount');

        if ($amount < 100.00) {
            throw ValidationException::withMessages([
                'amount' => 'The minimum payout amount is ₱100.00.',
            ]);
        }

        return DB::transaction(function () use ($seller, $amount, $request) {
            // Lock seller row to prevent race conditions / double clicks (SEL-EARN-007, A37)
            User::whereKey($seller->id)->lockForUpdate()->firstOrFail();

            // Check if payout is already pending (SEL-EARN-006, A15)
            $hasPending = SellerPayoutRequest::where('seller_id', $seller->id)
                ->whereIn('status', ['pending', 'processing'])
                ->lockForUpdate()
                ->exists();

            if ($hasPending) {
                throw ValidationException::withMessages([
                    'amount' => 'A payout request is already pending. Please wait for it to be processed.',
                ]);
            }

            // Calculate current available balance with lock
            $balances = $this->calculateSellerBalances($seller->id);
            $available = $balances['available_balance'];

            if ($amount > $available) {
                throw ValidationException::withMessages([
                    'amount' => 'The payout amount cannot exceed your available balance of ₱' . number_format($available, 2) . '.',
                ]);
            }

            $payout = SellerPayoutRequest::create([
                'payout_reference' => 'PAY-' . strtoupper(Str::random(10)),
                'seller_id' => $seller->id,
                'amount' => $amount,
                'payout_method' => $request->input('payout_method') ?? 'gcash',
                'account_name' => $request->input('account_name') ?? ($seller->shop_name ?? $seller->name),
                'account_number' => $request->input('account_number') ?? ($seller->contact_no ?? '09123456789'),
                'bank_name' => $request->input('bank_name'),
                'status' => 'pending',
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Payout request submitted successfully.',
                    'payout' => $payout,
                    'available_balance' => round($available - $amount, 2),
                ], 201);
            }

            return back()->with('success', 'Payout request submitted successfully.');
        });
    }

    /**
     * Cancel a pending payout request (SEL-EARN-005).
     */
    public function cancelPayoutRequest(Request $request, SellerPayoutRequest $payoutRequest)
    {
        $sellerId = $request->user()->id;
        abort_unless((int) $payoutRequest->seller_id === (int) $sellerId, 403);

        if ($payoutRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'payout' => 'Only pending payout requests can be cancelled.',
            ]);
        }

        $payoutRequest->update([
            'status' => 'cancelled',
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Payout request cancelled successfully.',
                'payout' => $payoutRequest,
            ]);
        }

        return back()->with('success', 'Payout request cancelled successfully.');
    }

    /**
     * Resolve report date range (SEL-EARN-004).
     *
     * Supports period presets (today, 7days, 30days) or custom from/to range.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request): array
    {
        $period = $request->input('period');

        if ($period === 'today') {
            return [now()->startOfDay(), now()->endOfDay()];
        }

        if ($period === '7days') {
            return [now()->subDays(6)->startOfDay(), now()->endOfDay()];
        }

        if ($period === '30days') {
            return [now()->subDays(29)->startOfDay(), now()->endOfDay()];
        }

        if ($request->filled('from') && $request->filled('to')) {
            $fromParsed = Carbon::parse($request->input('from'));
            $toParsed = Carbon::parse($request->input('to'));
            if ($toParsed->lt($fromParsed)) {
                throw ValidationException::withMessages([
                    'to' => 'The end date cannot be earlier than the start date.',
                ]);
            }
            return [$fromParsed->startOfDay(), $toParsed->endOfDay()];
        }

        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : now()->subDays(29)->startOfDay();

        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }
}
