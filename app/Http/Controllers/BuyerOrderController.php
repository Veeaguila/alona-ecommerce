<?php

namespace App\Http\Controllers;

use App\Models\BuyerNotification;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturnRequest;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\SellerNotification;
use App\Models\UserVoucher;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BuyerOrderController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | BUYER ORDERS
    |--------------------------------------------------------------------------
    */

    /**
     * Display buyer orders with status filter tabs and search.
     */
    public function index(Request $request): Response
    {
        Order::autoCancelExpiredUnpaidOrders();
        Order::autoCompleteDeliveredOrders();
        OrderReturnRequest::autoApproveExpiredPendingRequests();

        $user = $request->user();
        $statusTab = strtolower((string) $request->input('status', 'all'));
        $search = trim((string) $request->input('search', ''));

        // Precompute counts for status tabs
        $counts = [
            'all' => $user->orders()->count(),
            'to_pay' => $user->orders()
                ->where('payment_status', 'unpaid')
                ->where('payment_method', '!=', 'cod')
                ->where('status', '!=', 'cancelled')
                ->count(),
            'to_ship' => $user->orders()
                ->whereIn('status', ['pending', 'processing', 'packed', 'ready_for_pickup'])
                ->where(function ($q) {
                    $q->where('payment_status', 'paid')
                        ->orWhere('payment_method', 'cod');
                })
                ->count(),
            'in_transit' => $user->orders()
                ->whereIn('status', ['shipped', 'out_for_delivery'])
                ->count(),
            'to_receive' => $user->orders()
                ->whereIn('status', ['shipped', 'out_for_delivery'])
                ->count(),
            'delivered' => $user->orders()
                ->where('status', 'delivered')
                ->count(),
            'completed' => $user->orders()
                ->where('status', 'completed')
                ->count(),
            'cancelled' => $user->orders()
                ->where('status', 'cancelled')
                ->count(),
            'returns' => $user->orders()
                ->whereHas('returnRequests')
                ->count(),
        ];

        $ordersQuery = $user
            ->orders()
            ->with([
                'items.product',
                'items.variant',
                'items.returnRequests',
                'returnRequests',
            ]);

        // Filter by tab
        match ($statusTab) {
            'to_pay' => $ordersQuery
                ->where('payment_status', 'unpaid')
                ->where('payment_method', '!=', 'cod')
                ->where('status', '!=', 'cancelled'),
            'to_ship' => $ordersQuery
                ->whereIn('status', ['pending', 'processing', 'packed', 'ready_for_pickup'])
                ->where(function ($q) {
                    $q->where('payment_status', 'paid')
                        ->orWhere('payment_method', 'cod');
                }),
            'in_transit', 'to_receive' => $ordersQuery
                ->whereIn('status', ['shipped', 'out_for_delivery']),
            'delivered' => $ordersQuery
                ->where('status', 'delivered'),
            'completed' => $ordersQuery
                ->where('status', 'completed'),
            'cancelled' => $ordersQuery
                ->where('status', 'cancelled'),
            'returns' => $ordersQuery
                ->whereHas('returnRequests'),
            default => null,
        };

        // Search by order number or product name
        if ($search !== '') {
            $ordersQuery->where(function ($query) use ($search) {
                $query->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('items', function ($itemQuery) use ($search) {
                        $itemQuery->where('product_name', 'like', "%{$search}%");
                    });
            });
        }

        $orders = $ordersQuery
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Buyer/Orders', [
            'orders' => $orders,
            'current_status' => $statusTab,
            'counts' => $counts,
            'filters' => [
                'status' => $statusTab,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Show checkout page for selected cart items.
     */
    public function checkout(Request $request): Response
    {
        Order::autoCancelExpiredUnpaidOrders();

        $selectedIds = $this->normalizeSelectedIds(
            $request->input('selected_items', [])
        );

        if (empty($selectedIds)) {
            return Inertia::render('Buyer/Checkout', [
                'items' => [],
                'selected_items' => [],
                'addresses' => $request->user()
                    ->addresses()
                    ->latest()
                    ->get(),
                ...$this->checkoutOptions($request, collect()),
            ]);
        }

        $items = $request->user()
            ->cartItems()
            ->with([
                'product.category',
                'product.variants',
                'product.sales',
                'product.flashSales',
                'variant.product.sales',
                'variant.product.flashSales',
            ])
            ->whereIn('id', $selectedIds)
            ->get()
            ->filter(function (CartItem $item) {
                if (!$item->product) {
                    return false;
                }

                if (
                    Schema::hasColumn('products', 'status') &&
                    $item->product->status !== 'approved'
                ) {
                    return false;
                }

                if ($item->is_unavailable) {
                    return false;
                }

                $stock = $this->stockForCartItem($item);

                return $stock >= (int) $item->quantity;
            })
            ->values();

        $evaluation = app(\App\Services\CartPromotionCalculator::class)->evaluate($items);

        return Inertia::render('Buyer/Checkout', [
            'items' => $evaluation['checkout_lines'],
            'checkout_lines' => $evaluation['checkout_lines'],
            'raw_subtotal' => number_format($evaluation['raw_subtotal'], 2, '.', ''),
            'bundle_discount' => number_format($evaluation['bundle_discount'], 2, '.', ''),
            'buy_x_get_y_discount' => number_format($evaluation['buy_x_get_y_discount'], 2, '.', ''),
            'promotion_discount' => number_format($evaluation['promotion_discount'], 2, '.', ''),
            'subtotal' => number_format($evaluation['subtotal_after_promotions'], 2, '.', ''),

            'selected_items' => $items
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),

            'addresses' => $request->user()
                ->addresses()
                ->latest()
                ->get(),

            ...$this->checkoutOptions($request, $items),
        ]);
    }

    /**
     * Shipping/payment options shown at checkout.
     */
    private function checkoutOptions(Request $request, $items): array
    {
        $sellerCount = $items
            ->map(fn ($item) => $item->product?->seller_id)
            ->filter()
            ->unique()
            ->count();

        return [
            'shipping_options' => collect(config('checkout.shipping_methods'))
                ->map(fn ($method, $key) => [
                    'key' => $key,
                    'label' => $method['label'],
                    'description' => $method['description'],
                    'fee_per_seller' => (float) ($method['fee_per_seller'] ?? 0.0),
                ])
                ->values()
                ->all(),

            'seller_count' => max(1, $sellerCount),

            'free_shipping_threshold' => config('checkout.free_shipping_threshold'),

            'default_shipping_method' => config('checkout.default_shipping_method'),

            'payment_options' => collect(config('checkout.payment_methods'))
                ->map(fn ($method, $key) => [
                    'key' => $key,
                    'label' => $method['label'],
                    'description' => $method['description'],
                    'online' => $method['online'],
                ])
                ->values()
                ->all(),

            'saved_payment_methods' => $request->user()
                ->paymentMethods()
                ->orderByDesc('is_default')
                ->latest()
                ->get(),
        ];
    }

    /**
     * Calculate shipping fee based on configured shipping methods and seller count (BUY-VOU-009).
     */
    /**
     * Calculate shipping fee based on configured shipping methods, regional zones,
     * weight tiers, and seller count (BUY-CHK-002, BUY-CHK-003, BUY-VOU-009).
     */
    private function calculateShippingFee(
        string $method,
        $items,
        float $merchandiseTotal,
        ?string $shippingAddress = null
    ): float {
        if ($method === 'pickup') {
            return 0.0;
        }

        $freeThreshold = config('checkout.free_shipping_threshold');
        if ($method !== 'express' && $freeThreshold !== null && $merchandiseTotal >= (float) $freeThreshold) {
            return 0.0;
        }

        $shippingMethods = config('checkout.shipping_methods', []);
        $methodConfig = $shippingMethods[$method] ?? [];
        $isExpress = ($method === 'express');

        // Group items by seller
        $sellerGroups = ($items instanceof \Illuminate\Support\Collection ? $items : collect($items))
            ->groupBy(fn ($item) => is_object($item) && isset($item->product) ? (int) ($item->product?->seller_id ?? 0) : 0);

        if ($sellerGroups->isEmpty()) {
            return 50.0;
        }

        $totalShippingFee = 0.0;
        $addressLower = strtolower(trim((string) ($shippingAddress ?? '')));

        foreach ($sellerGroups as $sellerId => $sellerItems) {
            // 1. Calculate weight
            $sellerWeight = 0.0;
            $hasExplicitWeight = false;
            foreach ($sellerItems as $item) {
                if (is_object($item) && isset($item->product)) {
                    $w = (float) ($item->product?->weight ?? 0.0);
                    $qty = (int) ($item->quantity ?? 1);
                    if ($w > 0) {
                        $sellerWeight += $w * $qty;
                        $hasExplicitWeight = true;
                    }
                }
            }

            // BUY-CHK-003: 1.0 kg charged ₱60; 1.2 kg rounded up to 2 kg (₱80)
            $billedKg = $hasExplicitWeight ? max(1, (int) ceil($sellerWeight)) : 1;

            // 2. Determine base rate and per-kg rate
            // Check DeliveryZone from database
            $matchedZone = null;
            if (Schema::hasTable('delivery_zones') && !empty($addressLower)) {
                $activeZones = \App\Models\DeliveryZone::where('is_active', true)->get();
                foreach ($activeZones as $z) {
                    if (
                        str_contains($addressLower, strtolower($z->name)) ||
                        (!empty($z->province) && str_contains($addressLower, strtolower($z->province)))
                    ) {
                        $matchedZone = $z;
                        break;
                    }
                }
            }

            $isNcr = str_contains($addressLower, 'ncr') ||
                str_contains($addressLower, 'metro manila') ||
                str_contains($addressLower, 'manila') ||
                str_contains($addressLower, 'quezon city');

            $isVisayas = str_contains($addressLower, 'visayas') ||
                str_contains($addressLower, 'cebu') ||
                str_contains($addressLower, 'leyte');

            if ($matchedZone) {
                $baseRate = (float) $matchedZone->base_shipping_rate;
                $perKgRate = (float) ($matchedZone->per_kg_rate ?? 20.0);
            } elseif ($isVisayas) {
                // BUY-CHK-002: Visayas shipping is ₱120
                $baseRate = 120.0;
                $perKgRate = 25.0;
            } elseif ($isNcr) {
                // BUY-CHK-002: NCR shipping is ₱50
                $baseRate = 50.0;
                $perKgRate = 20.0;
            } else {
                // BUY-CHK-003: Standard tier base ₱60 (first 1 kg), or method config
                $baseRate = $hasExplicitWeight ? 60.0 : (float) ($methodConfig['fee_per_seller'] ?? 50.0);
                $perKgRate = 20.0;
            }

            if ($isExpress) {
                $baseRate = max($baseRate, 120.0);
            }

            $sellerFee = $baseRate + max(0, $billedKg - 1) * $perKgRate;
            $totalShippingFee += $sellerFee;
        }

        return round($totalShippingFee, 2);
    }

    /**
     * Validate courier coverage for the delivery address (BUY-CHK-004).
     */
    private function validateCourierCoverage(string $address): void
    {
        $unserviceableLocations = [
            'batanes',
            'kalayaan',
            'pag-asa island',
        ];

        $lower = strtolower($address);
        foreach ($unserviceableLocations as $location) {
            if (str_contains($lower, $location)) {
                throw ValidationException::withMessages([
                    'shipping_address' => 'The selected delivery address is outside courier serviceable coverage.',
                ]);
            }
        }
    }

    /**
     * Show a specific buyer order with tracking milestones, return eligibility,
     * and status history.
     */
    public function show(
        Request $request,
        Order $order
    ): Response {
        Order::autoCancelExpiredUnpaidOrders();
        Order::autoCompleteDeliveredOrders();
        OrderReturnRequest::autoApproveExpiredPendingRequests();
        $order->refresh();

        abort_unless(
            (int) $order->user_id ===
            (int) $request->user()->id,
            403
        );

        $order->load([
            'items.product.category',
            'items.product.seller',
            'items.variant',
            'items.statusHistories',
            'items.returnRequests',
            'returnRequests',
        ]);

        $orderStatusHistories = Schema::hasTable('order_status_histories')
            ? DB::table('order_status_histories')
                ->where('order_id', $order->id)
                ->orderBy('created_at', 'asc')
                ->get()
            : collect();

        if ($orderStatusHistories->isEmpty()) {
            $orderStatusHistories = $order->items
                ->flatMap(fn ($item) => $item->statusHistories)
                ->unique(fn ($history) => $history->status . '_' . ($history->created_at?->format('Y-m-d H:i') ?? ''))
                ->sortBy('created_at')
                ->values();
        }

        // Calculate order cancellability:
        // Allowed if order is in pending, processing, packed and not shipped/ready for pickup
        $cancellableStatuses = ['pending', 'processing', 'packed'];
        $canCancelOrder = in_array(strtolower((string) $order->status), $cancellableStatuses, true)
            && $order->items->every(function ($item) use ($cancellableStatuses) {
                return in_array(strtolower((string) ($item->status ?? 'pending')), $cancellableStatuses, true);
            });

        // Add item-level metadata
        $itemsWithMeta = $order->items->map(function ($item) use ($order) {
            $itemStatus = strtolower((string) ($item->status ?? 'pending'));
            $isDelivered = in_array($itemStatus, ['delivered', 'completed'], true)
                || in_array(strtolower((string) $order->status), ['delivered', 'completed'], true);

            // Return policy window
            $returnDays = (int) ($item->product?->seller?->return_policy_days ?? 7);
            if ($returnDays <= 0) {
                $returnDays = 7;
            }

            $deliveredAt = $item->delivered_at ?? $order->delivered_at ?? $item->updated_at ?? now();
            $returnDeadline = Carbon::parse($deliveredAt)->addDays($returnDays);
            $isWithinReturnWindow = now()->lessThanOrEqualTo($returnDeadline);

            // Active return request
            $activeReturn = $item->returnRequests
                ->whereIn('status', ['pending', 'approved', 'completed'])
                ->first();

            $canReturn = $isDelivered && $isWithinReturnWindow && !$activeReturn;

            // Product availability for reorder
            $isProductAvailable = $item->product
                && (!Schema::hasColumn('products', 'status') || $item->product->status === 'approved')
                && $this->stockForOrderItem($item) > 0;

            return array_merge($item->toArray(), [
                'can_confirm_delivery' => $itemStatus === 'delivered' || (strtolower((string) $order->status) === 'delivered' && $itemStatus !== 'completed' && $itemStatus !== 'cancelled'),
                'can_review' => $isDelivered,
                'can_return' => $canReturn,
                'can_reorder' => $isProductAvailable,
                'return_deadline' => $isDelivered ? $returnDeadline->toIso8601String() : null,
                'return_policy_days' => $returnDays,
                'active_return_request' => $activeReturn,
                'latest_return_request' => $item->returnRequests->first(),
            ]);
        });

        return Inertia::render('Buyer/OrderDetails', [
            'order' => array_merge($order->toArray(), [
                'items' => $itemsWithMeta,
                'can_cancel' => $canCancelOrder,
                'can_mark_received' => strtolower((string) $order->status) === 'delivered',
                'status_histories' => $orderStatusHistories,
            ]),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | VOUCHERS
    |--------------------------------------------------------------------------
    */

    /**
     * Validate a voucher from the checkout page.
     */
    public function validateVoucher(
        Request $request
    ) {
        $data = $request->validate([
            'voucher_code' => [
                'required',
                'string',
                'max:50',
            ],

            'selected_items' => [
                'required',
                'array',
                'min:1',
            ],

            'selected_items.*' => [
                'integer',
            ],

            'applied_codes' => [
                'nullable',
                'array',
            ],

            'applied_codes.*' => [
                'string',
            ],

            'shipping_method' => [
                'nullable',
                'string',
            ],
        ]);

        $selectedIds = $this->normalizeSelectedIds(
            $data['selected_items']
        );

        if (empty($selectedIds)) {
            return response()->json([
                'valid' => false,
                'message' => 'No cart items are selected.',
            ], 422);
        }

        $items = $request->user()
            ->cartItems()
            ->with([
                'product.category',
                'variant',
            ])
            ->whereIn('id', $selectedIds)
            ->get();

        if ($items->count() !== count($selectedIds)) {
            return response()->json([
                'valid' => false,
                'message' => 'Some selected cart items are invalid.',
            ], 422);
        }

        foreach ($items as $item) {
            if (!$item->product) {
                return response()->json([
                    'valid' => false,
                    'message' => 'A selected product no longer exists.',
                ], 422);
            }

            if (
                Schema::hasColumn('products', 'status') &&
                $item->product->status !== 'approved'
            ) {
                return response()->json([
                    'valid' => false,
                    'message' => 'A selected product is no longer available.',
                ], 422);
            }

            $stock = $this->stockForCartItem($item);

            if ($stock < (int) $item->quantity) {
                return response()->json([
                    'valid' => false,
                    'message' => "Insufficient stock for {$item->product->name}.",
                ], 422);
            }
        }

        $voucherCode = strtoupper(
            trim($data['voucher_code'])
        );

        $voucher = Voucher::query()
            ->whereRaw(
                'LOWER(code) = ?',
                [strtolower($voucherCode)]
            )
            ->first();

        if (!$voucher) {
            return response()->json([
                'valid' => false,
                'message' => 'Voucher code not found.',
            ], 422);
        }

        if (
            (Schema::hasColumn('vouchers', 'is_active') && !$voucher->is_active) ||
            (Schema::hasColumn('vouchers', 'ended_at') && $voucher->ended_at !== null)
        ) {
            return response()->json([
                'valid' => false,
                'message' => 'This voucher is no longer active.',
            ], 422);
        }

        if (
            Schema::hasColumn('vouchers', 'starts_at') &&
            $voucher->starts_at
        ) {
            $startsAt = Carbon::parse(
                $voucher->starts_at
            )->toDateString();

            if (
                now()->toDateString() <
                $startsAt
            ) {
                return response()->json([
                    'valid' => false,
                    'message' => 'This voucher is not active yet.',
                ], 422);
            }
        }

        if (
            Schema::hasColumn('vouchers', 'expires_at') &&
            $voucher->expires_at
        ) {
            $expiresAt = Carbon::parse(
                $voucher->expires_at
            )->toDateString();

            if (
                now()->toDateString() >
                $expiresAt
            ) {
                return response()->json([
                    'valid' => false,
                    'message' => 'This voucher has expired.',
                ], 422);
            }
        }

        if (
            Schema::hasColumn('vouchers', 'usage_limit') &&
            $voucher->usage_limit !== null
        ) {
            $usedCount = Schema::hasColumn(
                'vouchers',
                'used_count'
            )
                ? (int) $voucher->used_count
                : 0;

            if (
                $usedCount >=
                (int) $voucher->usage_limit
            ) {
                return response()->json([
                    'valid' => false,
                    'message' => 'This voucher has reached its usage limit.',
                ], 422);
            }
        }

        if (
            Schema::hasColumn('vouchers', 'per_customer_limit') &&
            $voucher->per_customer_limit !== null
        ) {
            $userOrdersWithVoucher = Order::query()
                ->where('user_id', $request->user()->id)
                ->where('voucher_id', $voucher->id)
                ->where('status', '!=', 'cancelled')
                ->count();

            if ($userOrdersWithVoucher >= (int) $voucher->per_customer_limit) {
                return response()->json([
                    'valid' => false,
                    'message' => "You have reached the maximum usage limit ({$voucher->per_customer_limit}) for this voucher.",
                ], 422);
            }
        }

        $category = $voucher->getStackCategory();
        $appliedCodes = array_map('strtoupper', array_map('trim', (array) ($data['applied_codes'] ?? [])));
        if (in_array(strtoupper($voucher->code), $appliedCodes, true)) {
            return response()->json([
                'valid' => false,
                'message' => 'This voucher is already applied.',
            ], 422);
        }

        if (!empty($appliedCodes)) {
            $existingApplied = Voucher::query()
                ->whereIn('code', $appliedCodes)
                ->get();

            foreach ($existingApplied as $otherVoucher) {
                if ($otherVoucher->getStackCategory() === $category) {
                    $catLabel = match ($category) {
                        'store' => 'store',
                        'platform' => 'platform',
                        'shipping' => 'free shipping',
                        default => $category,
                    };
                    return response()->json([
                        'valid' => false,
                        'message' => "Only one {$catLabel} voucher can be applied per order.",
                    ], 422);
                }
            }
        }

        $eligibleItems = $items->filter(
            function ($item) use ($voucher) {
                if (!$item->product) {
                    return false;
                }

                if (
                    Schema::hasColumn(
                        'vouchers',
                        'seller_id'
                    ) &&
                    $voucher->seller_id !== null
                ) {
                    if ((int) $item->product->seller_id !== (int) $voucher->seller_id) {
                        return false;
                    }
                }

                if (
                    Schema::hasColumn('vouchers', 'target_type') &&
                    $voucher->target_type === 'product' &&
                    !empty($voucher->targeted_product_ids)
                ) {
                    $targeted = array_map('intval', (array) $voucher->targeted_product_ids);
                    if (!in_array((int) $item->product_id, $targeted, true)) {
                        return false;
                    }
                }

                if (
                    Schema::hasColumn('vouchers', 'target_type') &&
                    $voucher->target_type === 'category' &&
                    !empty($voucher->targeted_category_ids)
                ) {
                    $targetedCats = array_map('intval', (array) $voucher->targeted_category_ids);
                    if (!in_array((int) $item->product->category_id, $targetedCats, true)) {
                        return false;
                    }
                }

                return true;
            }
        );

        if ($eligibleItems->isEmpty()) {
            return response()->json([
                'valid' => false,
                'message' => 'This voucher does not apply to the selected products.',
            ], 422);
        }

        $eligibleSubtotal = $eligibleItems->sum(
            function ($item) {
                return $this->itemPrice($item) *
                    (int) $item->quantity;
            }
        );

        if ($eligibleSubtotal <= 0) {
            return response()->json([
                'valid' => false,
                'message' => 'The selected products are not eligible for this voucher.',
            ], 422);
        }

        if (
            Schema::hasColumn(
                'vouchers',
                'min_spend'
            ) &&
            $voucher->min_spend !== null &&
            $eligibleSubtotal <
            (float) $voucher->min_spend
        ) {
            return response()->json([
                'valid' => false,
                'message' =>
                    'Minimum spend of ' .
                    $this->money(
                        $voucher->min_spend
                    ) .
                    ' is required for this voucher.',
            ], 422);
        }

        $shippingEstimate = 50.00;
        if ($voucher->type === 'free_shipping') {
            $shippingMethod = $request->input('shipping_method', 'standard');
            $shippingEstimate = $this->calculateShippingFee($shippingMethod, $items, $eligibleSubtotal);
            if ($shippingEstimate <= 0) {
                $shippingEstimate = 50.00;
            }
        }

        $discount = $this->calculateVoucherDiscount(
            $voucher,
            $eligibleSubtotal,
            $shippingEstimate
        );

        if ($discount <= 0) {
            return response()->json([
                'valid' => false,
                'message' => 'This voucher does not provide a valid discount.',
            ], 422);
        }

        return response()->json([
            'valid' => true,

            'voucher' => [
                'id' => $voucher->id,
                'code' => $voucher->code,
                'type' => $voucher->type,
                'category' => $category,
                'seller_id' => $voucher->seller_id,
                'is_platform' => (bool) $voucher->is_platform,
                'value' => (float) $voucher->value,
                'discount' => $discount,
                'eligible_subtotal' => $eligibleSubtotal,

                'min_spend' => Schema::hasColumn(
                    'vouchers',
                    'min_spend'
                )
                    ? (float) ($voucher->min_spend ?? 0)
                    : 0,
                'max_discount' => Schema::hasColumn(
                    'vouchers',
                    'max_discount'
                )
                    ? (float) ($voucher->max_discount ?? 0)
                    : 0,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PLACE ORDER
    |--------------------------------------------------------------------------
    */

    /**
     * Store buyer order.
     */
    public function store(
        Request $request
    ): RedirectResponse {
        if (! $request->filled('buyer_note')) {
            if ($request->filled('note')) {
                $request->merge(['buyer_note' => $request->input('note')]);
            } elseif ($request->filled('notes')) {
                $request->merge(['buyer_note' => $request->input('notes')]);
            }
        }

        $data = $request->validate([
            'shipping_address' => [
                'required',
                'string',
                'max:2000',
            ],

            'buyer_note' => [
                'nullable',
                'string',
                'max:200',
            ],

            'payment_method' => [
                'required',
                'in:' . implode(',', array_keys(config('checkout.payment_methods'))),
            ],

            'payment_method_id' => [
                'nullable',
                'integer',
            ],

            'shipping_method' => [
                'required',
                'in:' . implode(',', array_keys(config('checkout.shipping_methods'))),
            ],

            'voucher_code' => [
                'nullable',
                'string',
                'max:255',
            ],

            'voucher_codes' => [
                'nullable',
                'array',
                'max:3',
            ],

            'voucher_codes.*' => [
                'string',
                'max:50',
            ],

            'expected_prices' => [
                'nullable',
                'array',
            ],

            'selected_items' => [
                'required',
                'array',
                'min:1',
            ],

            'selected_items.*' => [
                'integer',
            ],
        ]);

        $this->validateCourierCoverage($data['shipping_address']);

        $selectedIds = $this->normalizeSelectedIds(
            $data['selected_items']
        );

        if (empty($selectedIds)) {
            throw ValidationException::withMessages([
                'selected_items' =>
                    'Please select at least one cart item.',
            ]);
        }

        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | TRANSACTION
        |--------------------------------------------------------------------------
        */

        $order = DB::transaction(
            function () use (
                $user,
                $data,
                $selectedIds
            ) {
                /*
                |--------------------------------------------------------------------------
                | Lock selected cart items
                |--------------------------------------------------------------------------
                */

                $items = $user
                    ->cartItems()
                    ->with([
                        'product.category',
                        'product.variants',
                        'variant',
                    ])
                    ->whereIn('id', $selectedIds)
                    ->lockForUpdate()
                    ->get();

                if (
                    $items->count() !==
                    count($selectedIds)
                ) {
                    throw ValidationException::withMessages([
                        'selected_items' =>
                            'Some selected cart items are no longer available.',
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Validate products and stock
                |--------------------------------------------------------------------------
                */

                foreach ($items as $item) {
                    $lockedProduct = $item->product_id
                        ? Product::with(['category', 'variants', 'seller'])
                            ->whereKey($item->product_id)
                            ->lockForUpdate()
                            ->first()
                        : null;
                    $item->setRelation('product', $lockedProduct);

                    if ($item->product_variant_id) {
                        $lockedVariant = \App\Models\ProductVariant::query()
                            ->whereKey($item->product_variant_id)
                            ->lockForUpdate()
                            ->first();
                        $item->setRelation('variant', $lockedVariant);
                    }

                    if (!$item->product) {
                        throw ValidationException::withMessages([
                            'selected_items' =>
                                'A selected product no longer exists.',
                        ]);
                    }

                    if (
                        Schema::hasColumn(
                            'products',
                            'status'
                        ) &&
                        $item->product->status !==
                            'approved'
                    ) {
                        throw ValidationException::withMessages([
                            'selected_items' =>
                                "The product {$item->product->name} is no longer available.",
                        ]);
                    }

                    if ($item->unavailable_reason === 'Variation unavailable') {
                        throw ValidationException::withMessages([
                            'selected_items' =>
                                'Variation unavailable',
                        ]);
                    }

                    if ($item->product->seller && $item->product->seller->vacation_mode) {
                        $storeName = $item->product->seller->store_name ?: $item->product->seller->name;
                        $msg = $item->product->seller->vacation_message ? ": {$item->product->seller->vacation_message}" : '.';
                        throw ValidationException::withMessages([
                            'selected_items' =>
                                "Cannot place order: {$storeName} is currently on vacation mode and has temporarily paused order processing{$msg}",
                        ]);
                    }

                    if ($item->product->effective_per_buyer_limit) {
                        $limit = $item->product->effective_per_buyer_limit;
                        $alreadyPurchased = $item->product->purchasedUnitsCountFor($user);
                        $orderQtyForProduct = (int) $items->where('product_id', $item->product_id)->sum('quantity');
                        if (($alreadyPurchased + $orderQtyForProduct) > $limit) {
                            throw ValidationException::withMessages([
                                'selected_items' => "Purchase limit of {$limit} item(s) exceeded for {$item->product->name}.",
                            ]);
                        }
                    }

                    $stock = $this->stockForCartItem(
                        $item
                    );

                    if ($stock <= 0) {
                        throw ValidationException::withMessages([
                            'selected_items' =>
                                "Out of stock: {$item->product->name} is currently Sold out.",
                        ]);
                    }

                    if (
                        $stock <
                        (int) $item->quantity
                    ) {
                        throw ValidationException::withMessages([
                            'selected_items' =>
                                "Only {$stock} left for {$item->product->name}. Please adjust quantity to {$stock} or fewer.",
                        ]);
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Validate price changes (BUY-CHK-008)
                |--------------------------------------------------------------------------
                */

                $expectedPrices = $data['expected_prices'] ?? [];
                if (!empty($expectedPrices) && is_array($expectedPrices)) {
                    foreach ($items as $item) {
                        $expectedPrice = null;
                        if (array_key_exists($item->id, $expectedPrices)) {
                            $expectedPrice = (float) $expectedPrices[$item->id];
                        } elseif (array_key_exists((string) $item->id, $expectedPrices)) {
                            $expectedPrice = (float) $expectedPrices[(string) $item->id];
                        } elseif (array_key_exists($item->product_id, $expectedPrices)) {
                            $expectedPrice = (float) $expectedPrices[$item->product_id];
                        } elseif (array_key_exists((string) $item->product_id, $expectedPrices)) {
                            $expectedPrice = (float) $expectedPrices[(string) $item->product_id];
                        }

                        if ($expectedPrice !== null) {
                            $currentPrice = (float) $this->itemPrice($item);
                            if (abs($currentPrice - $expectedPrice) > 0.01) {
                                throw ValidationException::withMessages([
                                    'expected_prices' => "The price for {$item->product->name} has changed. Please review the updated price before placing your order.",
                                    'price_changed' => "The price for {$item->product->name} has changed. Please review the updated price before placing your order.",
                                    'selected_items' => "The price for {$item->product->name} has changed. Please review your order before proceeding.",
                                ]);
                            }
                        }
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Calculate subtotal (with S8 Flash Sale splits, Bundle & Buy X Get Y)
                |--------------------------------------------------------------------------
                */

                $evaluation = app(\App\Services\CartPromotionCalculator::class)->evaluate($items);
                $subtotal = (float) $evaluation['subtotal_after_promotions'];

                /*
                |--------------------------------------------------------------------------
                | Vouchers (Stacking: 1 Store + 1 Platform + 1 Free Shipping)
                |--------------------------------------------------------------------------
                */

                $rawCodes = [];
                if (!empty($data['voucher_codes']) && is_array($data['voucher_codes'])) {
                    $rawCodes = $data['voucher_codes'];
                } elseif (!empty($data['voucher_code'])) {
                    $rawCodes = explode(',', (string) $data['voucher_code']);
                }
                $voucherCodes = array_values(array_unique(array_filter(array_map('trim', $rawCodes))));

                $shippingMethod = $data['shipping_method'];
                $shippingFeeBeforeDiscount = $this->calculateShippingFee(
                    $shippingMethod,
                    $items,
                    $subtotal,
                    $data['shipping_address']
                );

                $itemDiscount = 0.0;
                $shippingDiscount = 0.0;
                $appliedVouchersMeta = [];
                $vouchers = collect();

                if (!empty($voucherCodes)) {
                    $vouchers = Voucher::query()
                        ->where(function ($q) use ($voucherCodes) {
                            foreach ($voucherCodes as $vc) {
                                $q->orWhereRaw('LOWER(code) = ?', [strtolower($vc)]);
                            }
                        })
                        ->lockForUpdate()
                        ->get();

                    if ($vouchers->count() !== count($voucherCodes)) {
                        throw ValidationException::withMessages([
                            'voucher_code' => 'One or more voucher codes not found.',
                        ]);
                    }

                    $usedCategories = [];
                    foreach ($vouchers as $v) {
                        $cat = $v->getStackCategory();
                        if (isset($usedCategories[$cat])) {
                            $catLabel = match ($cat) {
                                'store' => 'store',
                                'platform' => 'platform',
                                'shipping' => 'free shipping',
                                default => $cat,
                            };
                            throw ValidationException::withMessages([
                                'voucher_code' => "Only one {$catLabel} voucher can be applied per order.",
                            ]);
                        }
                        $usedCategories[$cat] = $v;
                    }

                    foreach ($vouchers as $v) {
                        if (
                            (Schema::hasColumn('vouchers', 'is_active') && !$v->is_active) ||
                            (Schema::hasColumn('vouchers', 'ended_at') && $v->ended_at !== null)
                        ) {
                            throw ValidationException::withMessages([
                                'voucher_code' => "Voucher {$v->code} is no longer active.",
                            ]);
                        }

                        if (Schema::hasColumn('vouchers', 'starts_at') && $v->starts_at) {
                            $startsAt = Carbon::parse($v->starts_at)->toDateString();
                            if (now()->toDateString() < $startsAt) {
                                throw ValidationException::withMessages([
                                    'voucher_code' => "Voucher {$v->code} is not active yet.",
                                ]);
                            }
                        }

                        if (Schema::hasColumn('vouchers', 'expires_at') && $v->expires_at) {
                            $expiresAt = Carbon::parse($v->expires_at)->toDateString();
                            if (now()->toDateString() > $expiresAt) {
                                throw ValidationException::withMessages([
                                    'voucher_code' => "Voucher {$v->code} has expired.",
                                ]);
                            }
                        }

                        if (Schema::hasColumn('vouchers', 'usage_limit') && $v->usage_limit !== null) {
                            $usedCount = Schema::hasColumn('vouchers', 'used_count') ? (int) $v->used_count : 0;
                            if ($usedCount >= (int) $v->usage_limit) {
                                throw ValidationException::withMessages([
                                    'voucher_code' => "Voucher {$v->code} has reached its usage limit.",
                                ]);
                            }
                        }

                        if (Schema::hasColumn('vouchers', 'per_customer_limit') && $v->per_customer_limit !== null) {
                            $userOrdersWithVoucher = Order::query()
                                ->where('user_id', $user->id)
                                ->where(function ($q) use ($v) {
                                    $q->where('voucher_id', $v->id)
                                        ->orWhereJsonContains('applied_vouchers', ['id' => $v->id]);
                                })
                                ->where('status', '!=', 'cancelled')
                                ->count();

                            if ($userOrdersWithVoucher >= (int) $v->per_customer_limit) {
                                throw ValidationException::withMessages([
                                    'voucher_code' => "You have reached the maximum usage limit ({$v->per_customer_limit}) for voucher {$v->code}.",
                                ]);
                            }
                        }

                        $eligibleItems = $items->filter(function ($item) use ($v) {
                            if (!$item->product) return false;
                            if (Schema::hasColumn('vouchers', 'seller_id') && $v->seller_id !== null) {
                                if ((int) $item->product->seller_id !== (int) $v->seller_id) {
                                    return false;
                                }
                            }
                            if (Schema::hasColumn('vouchers', 'target_type') && $v->target_type === 'product' && !empty($v->targeted_product_ids)) {
                                $targeted = array_map('intval', (array) $v->targeted_product_ids);
                                if (!in_array((int) $item->product_id, $targeted, true)) {
                                    return false;
                                }
                            }
                            if (Schema::hasColumn('vouchers', 'target_type') && $v->target_type === 'category' && !empty($v->targeted_category_ids)) {
                                $targetedCats = array_map('intval', (array) $v->targeted_category_ids);
                                if (!in_array((int) $item->product->category_id, $targetedCats, true)) {
                                    return false;
                                }
                            }
                            return true;
                        });

                        if ($eligibleItems->isEmpty()) {
                            throw ValidationException::withMessages([
                                'voucher_code' => "Voucher {$v->code} does not apply to the selected products.",
                            ]);
                        }

                        $eligibleSubtotal = $eligibleItems->sum(function ($item) {
                            return $this->itemPrice($item) * (int) $item->quantity;
                        });

                        if (Schema::hasColumn('vouchers', 'min_spend') && $v->min_spend !== null && $eligibleSubtotal < (float) $v->min_spend) {
                            throw ValidationException::withMessages([
                                'voucher_code' => 'Minimum spend of ' . $this->money($v->min_spend) . " is required for voucher {$v->code}.",
                            ]);
                        }

                        $cat = $v->getStackCategory();
                        if ($cat === 'shipping') {
                            $vDiscount = $this->calculateVoucherDiscount($v, $eligibleSubtotal, $shippingFeeBeforeDiscount);
                            $shippingDiscount += $vDiscount;
                        } else {
                            $vDiscount = $this->calculateVoucherDiscount($v, $eligibleSubtotal, 0);
                            $itemDiscount += $vDiscount;
                        }

                        if ($vDiscount <= 0) {
                            throw ValidationException::withMessages([
                                'voucher_code' => "Voucher {$v->code} does not provide a valid discount.",
                            ]);
                        }

                        $appliedVouchersMeta[] = [
                            'id' => $v->id,
                            'code' => $v->code,
                            'type' => $v->type,
                            'category' => $cat,
                            'seller_id' => $v->seller_id,
                            'is_platform' => (bool) $v->is_platform,
                            'value' => (float) $v->value,
                            'discount' => round($vDiscount, 2),
                            'min_spend' => (float) ($v->min_spend ?? 0),
                            'max_discount' => (float) ($v->max_discount ?? 0),
                        ];

                        if (Schema::hasColumn('vouchers', 'used_count')) {
                            $v->increment('used_count');
                            $v->refresh();
                        }

                        if (Schema::hasTable('user_vouchers')) {
                            UserVoucher::where('user_id', $user->id)
                                ->where('voucher_id', $v->id)
                                ->where('status', 'claimed')
                                ->update([
                                    'status' => 'used',
                                    'used_at' => now(),
                                ]);
                        }
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Final totals
                |--------------------------------------------------------------------------
                */

                $itemDiscount = round(min($subtotal, $itemDiscount), 2);
                $shippingDiscount = round(min($shippingFeeBeforeDiscount, $shippingDiscount), 2);
                $finalShippingFee = round(max(0, $shippingFeeBeforeDiscount - $shippingDiscount), 2);
                $total = round(max(0, $subtotal - $itemDiscount) + $finalShippingFee, 2);

                $savedPaymentMethod = null;

                if (!empty($data['payment_method_id'])) {
                    $savedPaymentMethod = $user
                        ->paymentMethods()
                        ->whereKey($data['payment_method_id'])
                        ->first();

                    if (
                        !$savedPaymentMethod ||
                        $savedPaymentMethod->type !== $data['payment_method']
                    ) {
                        throw ValidationException::withMessages([
                            'payment_method_id' =>
                                'The selected saved payment method is invalid.',
                        ]);
                    }
                }

                $sellerGroups = $items->groupBy(fn ($item) => (int) ($item->product?->seller_id ?? 0));

                if ($sellerGroups->count() > 1) {
                    $createdOrders = [];
                    $totalSubtotal = $subtotal;
                    $remainingShippingDiscount = $shippingDiscount;

                    foreach ($sellerGroups as $sellerId => $sellerItems) {
                        $sellerEval = app(\App\Services\CartPromotionCalculator::class)->evaluate($sellerItems);
                        $storeSubtotal = (float) $sellerEval['subtotal_after_promotions'];
                        $storeShippingBeforeDiscount = $this->calculateShippingFee(
                            $shippingMethod,
                            $sellerItems,
                            $storeSubtotal,
                            $data['shipping_address']
                        );

                        $storeDiscount = 0.0;
                        $storeShippingDiscount = 0.0;
                        $storeAppliedVouchers = [];

                        foreach ($appliedVouchersMeta as $vMeta) {
                            $cat = $vMeta['category'] ?? null;
                            if ($cat === 'store') {
                                if ((int) ($vMeta['seller_id'] ?? 0) === (int) $sellerId) {
                                    $vDisc = min($storeSubtotal, (float) ($vMeta['discount'] ?? 0.0));
                                    $storeDiscount += $vDisc;
                                    $storeAppliedVouchers[] = array_merge($vMeta, ['discount' => round($vDisc, 2)]);
                                }
                            } elseif ($cat === 'platform') {
                                $ratio = $totalSubtotal > 0 ? ($storeSubtotal / $totalSubtotal) : (1.0 / $sellerGroups->count());
                                $platDisc = round((float) ($vMeta['discount'] ?? 0.0) * $ratio, 2);
                                $storeDiscount += $platDisc;
                                $storeAppliedVouchers[] = array_merge($vMeta, ['discount' => round($platDisc, 2)]);
                            } elseif ($cat === 'shipping') {
                                $shipDisc = min($storeShippingBeforeDiscount, $remainingShippingDiscount);
                                $storeShippingDiscount += $shipDisc;
                                $remainingShippingDiscount = max(0.0, $remainingShippingDiscount - $shipDisc);
                                if ($shipDisc > 0) {
                                    $storeAppliedVouchers[] = array_merge($vMeta, ['discount' => round($shipDisc, 2)]);
                                }
                            }
                        }

                        $storeDiscount = round(min($storeSubtotal, $storeDiscount), 2);
                        $storeShippingDiscount = round(min($storeShippingBeforeDiscount, $storeShippingDiscount), 2);
                        $storeFinalShippingFee = round(max(0, $storeShippingBeforeDiscount - $storeShippingDiscount), 2);
                        $storeTotal = round(max(0, $storeSubtotal - $storeDiscount) + $storeFinalShippingFee, 2);

                        $storeOrder = new Order();
                        $storeOrder->order_number = $this->generateOrderNumber();
                        $storeOrder->user_id = $user->id;
                        $storeOrder->shipping_address = $data['shipping_address'];
                        if (Schema::hasColumn('orders', 'buyer_note') && ! empty($data['buyer_note'])) {
                            $storeOrder->buyer_note = trim((string) $data['buyer_note']);
                        }
                        $storeOrder->payment_method = $data['payment_method'];
                        $storeOrder->shipping_method = $shippingMethod;
                        $storeOrder->shipping_fee = $storeFinalShippingFee;
                        $storeOrder->shipping_fee_before_discount = $storeShippingBeforeDiscount;
                        $storeOrder->shipping_discount = $storeShippingDiscount;
                        $storeOrder->delivery_fee_payer = 'seller';
                        $storeOrder->payment_status = 'unpaid';
                        $storeOrder->payment_method_id = $savedPaymentMethod?->id;
                        $storeOrder->subtotal = $storeSubtotal;
                        $storeOrder->discount = $storeDiscount;
                        $storeOrder->total = $storeTotal;
                        $storeOrder->status = 'pending';

                        if (!empty($storeAppliedVouchers)) {
                            $storeOrder->applied_vouchers = $storeAppliedVouchers;
                            $storeOrder->voucher_id = $storeAppliedVouchers[0]['id'];
                            $storeOrder->voucher_code = implode(', ', array_column($storeAppliedVouchers, 'code'));
                        }

                        $storeOrder->save();

                        foreach ($sellerItems as $cartItem) {
                            $product = $cartItem->product;
                            $itemMeta = $sellerEval['item_pricing_map'][$cartItem->id] ?? null;
                            $linesToCreate = !empty($itemMeta['lines'])
                                ? $itemMeta['lines']
                                : [
                                    [
                                        'quantity' => (int) $cartItem->quantity,
                                        'unit_price' => $this->itemPrice($cartItem),
                                        'is_flash_sale' => false,
                                        'flash_sale_id' => null,
                                    ],
                                ];

                            foreach ($linesToCreate as $lineRow) {
                                $lineQty = (int) ($lineRow['quantity'] ?? $cartItem->quantity);
                                $lineUnitPrice = isset($itemMeta['is_free_gift']) && $itemMeta['is_free_gift']
                                    ? (float) ($itemMeta['effective_unit_price'] ?? 0.0)
                                    : (float) ($lineRow['unit_price'] ?? $this->itemPrice($cartItem));

                                $orderItemData = [
                                    'order_id' => $storeOrder->id,
                                    'product_id' => $product->id,
                                    'product_name' => $product->name,
                                    'quantity' => $lineQty,
                                    'price' => $lineUnitPrice,
                                ];

                                if (Schema::hasColumn('order_items', 'product_variant_id') && $cartItem->variant) {
                                    $orderItemData['product_variant_id'] = $cartItem->variant->id;
                                }

                                if (Schema::hasColumn('order_items', 'variant_label')) {
                                    $orderItemData['variant_label'] = $this->variantLabel($cartItem->variant);
                                }

                                if (Schema::hasColumn('order_items', 'subtotal')) {
                                    $orderItemData['subtotal'] = round($lineUnitPrice * $lineQty, 2);
                                }

                                OrderItem::create($orderItemData);

                                if (!empty($lineRow['is_flash_sale']) && !empty($lineRow['flash_sale_id']) && Schema::hasTable('flash_sales')) {
                                    $lockedFlash = \App\Models\FlashSale::whereKey($lineRow['flash_sale_id'])
                                        ->lockForUpdate()
                                        ->first();
                                    if ($lockedFlash) {
                                        $lockedFlash->increment('sold_quantity', $lineQty);
                                    }
                                }
                            }

                            $this->deductStock($cartItem);
                            $this->notifySellerNewOrder($storeOrder, $cartItem);
                            $this->notifySellerLowStock($cartItem);
                        }

                        app(\App\Services\SellerShipmentFinance::class)->createMembership($storeOrder);
                        $this->createOrderStatusHistory($storeOrder, 'pending');

                        $createdOrders[] = $storeOrder;
                    }

                    $user->cartItems()
                        ->whereIn('id', $selectedIds)
                        ->delete();

                    return $createdOrders;
                }

                $orderNumber = $this->generateOrderNumber();

                $order = new Order();
                $order->order_number = $orderNumber;
                $order->user_id = $user->id;
                $order->shipping_address = $data['shipping_address'];
                if (Schema::hasColumn('orders', 'buyer_note') && ! empty($data['buyer_note'])) {
                    $order->buyer_note = trim((string) $data['buyer_note']);
                }
                $order->payment_method = $data['payment_method'];
                $order->shipping_method = $shippingMethod;
                $order->shipping_fee = $finalShippingFee;
                $order->shipping_fee_before_discount = $shippingFeeBeforeDiscount;
                $order->shipping_discount = $shippingDiscount;
                $order->delivery_fee_payer = 'seller';
                $order->payment_status = 'unpaid';
                $order->payment_method_id = $savedPaymentMethod?->id;
                $order->subtotal = $subtotal;
                $order->discount = $itemDiscount;
                $order->total = $total;
                $order->status = 'pending';

                if (!empty($appliedVouchersMeta)) {
                    $order->applied_vouchers = $appliedVouchersMeta;
                    $order->voucher_id = $appliedVouchersMeta[0]['id'];
                    $order->voucher_code = implode(', ', array_column($appliedVouchersMeta, 'code'));
                }

                $order->save();

                /*
                |--------------------------------------------------------------------------
                | Create order items (including S8 Flash Sale split lines)
                |--------------------------------------------------------------------------
                */

                foreach ($items as $cartItem) {
                    $product =
                        $cartItem->product;

                    $itemMeta = $evaluation['item_pricing_map'][$cartItem->id] ?? null;
                    $linesToCreate = !empty($itemMeta['lines'])
                        ? $itemMeta['lines']
                        : [
                            [
                                'quantity' => (int) $cartItem->quantity,
                                'unit_price' => $this->itemPrice($cartItem),
                                'is_flash_sale' => false,
                                'flash_sale_id' => null,
                            ],
                        ];

                    foreach ($linesToCreate as $lineRow) {
                        $lineQty = (int) ($lineRow['quantity'] ?? $cartItem->quantity);
                        $lineUnitPrice = isset($itemMeta['is_free_gift']) && $itemMeta['is_free_gift']
                            ? (float) ($itemMeta['effective_unit_price'] ?? 0.0)
                            : (float) ($lineRow['unit_price'] ?? $this->itemPrice($cartItem));

                        $orderItemData = [
                            'order_id' => $order->id,
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'quantity' => $lineQty,
                            'price' => $lineUnitPrice,
                        ];

                        if (
                            Schema::hasColumn('order_items', 'product_variant_id') &&
                            $cartItem->variant
                        ) {
                            $orderItemData['product_variant_id'] = $cartItem->variant->id;
                        }

                        if (Schema::hasColumn('order_items', 'variant_label')) {
                            $orderItemData['variant_label'] = $this->variantLabel($cartItem->variant);
                        }

                        if (Schema::hasColumn('order_items', 'subtotal')) {
                            $orderItemData['subtotal'] = round($lineUnitPrice * $lineQty, 2);
                        }

                        OrderItem::create($orderItemData);

                        if (!empty($lineRow['is_flash_sale']) && !empty($lineRow['flash_sale_id']) && Schema::hasTable('flash_sales')) {
                            $lockedFlash = \App\Models\FlashSale::whereKey($lineRow['flash_sale_id'])
                                ->lockForUpdate()
                                ->first();
                            if ($lockedFlash) {
                                $lockedFlash->increment('sold_quantity', $lineQty);
                            }
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Deduct stock
                    |--------------------------------------------------------------------------
                    */

                    $this->deductStock(
                        $cartItem
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Seller notification
                    |--------------------------------------------------------------------------
                    */

                    $this->notifySellerNewOrder(
                        $order,
                        $cartItem
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Low stock notification
                    |--------------------------------------------------------------------------
                    */

                    $this->notifySellerLowStock(
                        $cartItem
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Initial order status history
                |--------------------------------------------------------------------------
                */

                app(\App\Services\SellerShipmentFinance::class)->createMembership($order);
                $this->createOrderStatusHistory($order, 'pending');

                /*
                |--------------------------------------------------------------------------
                | Remove ONLY selected cart items
                |--------------------------------------------------------------------------
                */

                $user->cartItems()
                    ->whereIn(
                        'id',
                        $selectedIds
                    )
                    ->delete();

                return $order;
            }
        );

        $allOrders = is_array($order) ? $order : [$order];
        $primaryOrder = $allOrders[0];
        $orderCount = count($allOrders);

        if (Schema::hasTable('buyer_notifications')) {
            foreach ($allOrders as $ord) {
                try {
                    BuyerNotification::create([
                        'user_id' => $user->id,
                        'title' => "Order #{$ord->order_number} placed",
                        'type' => 'order',
                        'message' => "Your order with total ₱" . number_format((float) $ord->total, 2) . " has been placed and is waiting for fulfillment.",
                        'action_url' => route('buyer.orders.show', $ord->id),
                    ]);
                } catch (\Throwable $e) {
                    // Ignore notification logging errors
                }
            }
        }

        $statusMsg = $orderCount > 1
            ? "Your checkout was split into {$orderCount} store orders."
            : 'Order placed successfully.';

        return redirect()
            ->route(
                'buyer.orders.show',
                $primaryOrder
            )
            ->with('status', $statusMsg)
            ->with('success', $statusMsg);
    }

    /*
    |--------------------------------------------------------------------------
    | ORDER PAYMENT & WEBHOOKS (BUY-PAY-001 to BUY-PAY-007)
    |--------------------------------------------------------------------------
    */

    /**
     * Process payment or retry payment for an order (BUY-PAY-002, BUY-PAY-003, BUY-PAY-004, BUY-PAY-007).
     */
    public function pay(Request $request, Order $order): RedirectResponse
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);

        // Advance auto-cancel check if 1 hour has passed (BUY-PAY-007)
        if ($order->created_at && $order->created_at->lessThanOrEqualTo(now()->subHour())) {
            Order::autoCancelExpiredUnpaidOrders();
            $order->refresh();
            return redirect()
                ->route('buyer.orders.show', $order)
                ->withErrors([
                    'payment' => 'The payment window for this order has expired (1 hour). The order has been cancelled and stock has been restored.',
                ]);
        }

        if ($order->status === 'cancelled') {
            return redirect()
                ->route('buyer.orders.show', $order)
                ->withErrors([
                    'payment' => 'This order has already been cancelled and cannot be paid.',
                ]);
        }

        if ($order->payment_status === 'paid') {
            return redirect()
                ->route('buyer.orders.show', $order)
                ->with('status', 'This order has already been paid.');
        }

        $validated = $request->validate([
            'action' => 'nullable|string|in:success,fail,cancel',
            'payment_method' => 'nullable|string|in:' . implode(',', array_keys(config('checkout.payment_methods', []))),
            'payment_reference' => 'nullable|string|max:100',
        ]);

        $action = $validated['action'] ?? 'success';

        // BUY-PAY-004: Buyer cancels at gateway
        if ($action === 'cancel') {
            return redirect()
                ->route('buyer.orders.show', $order)
                ->with('warning', 'Payment cancelled at gateway. You can retry paying before the 1-hour window expires.');
        }

        // BUY-PAY-003: Payment fails
        if ($action === 'fail') {
            return redirect()
                ->route('buyer.orders.show', $order)
                ->withErrors([
                    'payment' => 'Payment failed. Please retry payment or choose another payment method.',
                ]);
        }

        // BUY-PAY-002: Payment succeeds
        DB::transaction(function () use ($order, $validated, $request) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (!empty($validated['payment_method'])) {
                $lockedOrder->payment_method = $validated['payment_method'];
            }

            $lockedOrder->payment_status = 'paid';
            $lockedOrder->status = 'pending';
            $lockedOrder->save();

            if (Schema::hasTable('order_status_histories')) {
                DB::table('order_status_histories')->insert([
                    'order_id' => $lockedOrder->id,
                    'status' => 'paid',
                    'note' => 'Payment completed successfully via ' . strtoupper($lockedOrder->payment_method) . (!empty($validated['payment_reference']) ? " (Ref: {$validated['payment_reference']})" : ''),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (Schema::hasTable('buyer_notifications')) {
                try {
                    BuyerNotification::create([
                        'user_id' => $request->user()->id,
                        'title' => "Payment confirmed for Order #{$lockedOrder->order_number}",
                        'type' => 'payment',
                        'message' => "Your payment of ₱" . number_format((float) $lockedOrder->total, 2) . " was successful. Your order is now being processed for shipping.",
                        'action_url' => route('buyer.orders.show', $lockedOrder->id),
                    ]);
                } catch (\Throwable $e) {}
            }
        });

        return redirect()
            ->route('buyer.orders.show', $order)
            ->with('status', 'Payment successful! Your order has moved to To Ship.')
            ->with('success', 'Payment successful! Your order has moved to To Ship.');
    }

    /**
     * Handle payment webhook updates (BUY-PAY-005).
     */
    public function paymentWebhook(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required',
            'status' => 'required|string',
            'reference' => 'nullable|string',
        ]);

        $order = Order::whereKey($validated['order_id'])
            ->orWhere('order_number', $validated['order_id'])
            ->first();

        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        if ($order->created_at && $order->created_at->lessThanOrEqualTo(now()->subHour()) && $order->payment_status === 'unpaid') {
            Order::autoCancelExpiredUnpaidOrders();
            return response()->json(['status' => 'cancelled', 'message' => 'Order expired and auto-cancelled'], 400);
        }

        $statusLower = strtolower($validated['status']);

        if (in_array($statusLower, ['paid', 'succeeded', 'completed', 'success'], true)) {
            $order->update([
                'payment_status' => 'paid',
                'status' => 'pending',
            ]);

            if (Schema::hasTable('order_status_histories')) {
                DB::table('order_status_histories')->insert([
                    'order_id' => $order->id,
                    'status' => 'paid',
                    'note' => 'Payment verified via webhook' . (!empty($validated['reference']) ? " (Ref: {$validated['reference']})" : ''),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return response()->json([
                'status' => 'success',
                'order_id' => $order->id,
                'payment_status' => 'paid',
            ]);
        }

        return response()->json([
            'status' => 'unpaid',
            'order_id' => $order->id,
            'payment_status' => $order->payment_status,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELIVERY CONFIRMATION
    |--------------------------------------------------------------------------
    */

    /**
     * Buyer confirms delivery of an order item.
     *
     * NOTE:
     * The current seller-controlled workflow should eventually
     * replace this action. This method is kept here temporarily
     * so existing routes do not immediately break.
     */
    public function confirmDelivery(
        Request $request,
        int $orderItem
    ): RedirectResponse {
        $item = OrderItem::query()
            ->with('order')
            ->whereKey($orderItem)
            ->firstOrFail();

        abort_unless(
            (int) $item->order->user_id ===
            (int) $request->user()->id,
            403
        );

        if ($item->seller_shipment_id) {
            $parcel = $item->parcel;
            abort_unless($parcel && $parcel->status === 'DELIVERED'
                && $parcel->deliveryProofs()->where('rider_id', $parcel->assigned_rider_id)->exists(),
                422, 'Courier delivery with valid proof is required before confirmation.');

            return back()->with('status', 'Delivery confirmed successfully.');
        }

        $currentStatus =
            $item->status ??
            $item->order->status;

        $isAlreadyDelivered = strtolower((string) $currentStatus) === 'delivered';

        if (! $isAlreadyDelivered) {
            throw ValidationException::withMessages([
                'status' =>
                    'This item cannot be confirmed as received until it is delivered.',
            ]);
        }

        $newStatus = $isAlreadyDelivered ? 'completed' : 'delivered';

        if (
            Schema::hasColumn(
                'order_items',
                'status'
            )
        ) {
            $item->update([
                'status' => $newStatus,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | If all order items have reached target status, mark order
        |--------------------------------------------------------------------------
        */

        $order = $item->order;

        $order->load('items');

        $allMatch = true;

        foreach ($order->items as $orderItemModel) {
            $status =
                strtolower(
                    (string) (
                        $orderItemModel->status ??
                        $order->status
                    )
                );

            if ($status !== $newStatus && $status !== 'completed') {
                $allMatch = false;
                break;
            }
        }

        if ($allMatch) {
            $order->update([
                'status' => $newStatus,
            ]);

            $this->createOrderStatusHistory(
                $order,
                $newStatus
            );
        }

        return back()->with(
            'status',
            $isAlreadyDelivered ? 'Order item received and completed successfully.' : 'Delivery confirmed successfully.'
        );
    }

    /**
     * Buyer marks delivered order as received/completed (SEL-EARN-002, A15, BUY-TRK-003, BUY-TRK-004).
     */
    public function orderReceived(Request $request, Order $order)
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 403);

        $status = strtolower((string) $order->status);
        if ($status !== 'delivered') {
            throw ValidationException::withMessages([
                'status' => 'Order must be delivered before it can be marked as received.',
            ]);
        }

        $order->update([
            'status' => 'completed',
        ]);

        $order->items()->whereNotIn('status', ['cancelled', 'refunded'])->update([
            'status' => 'completed',
        ]);

        foreach ($order->items as $item) {
            if (Schema::hasTable('order_item_status_histories')) {
                $item->statusHistories()->create([
                    'status' => 'completed',
                    'note' => 'Order marked as received by buyer.',
                ]);
            }
        }

        $this->createOrderStatusHistory($order, 'completed');

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Order marked as received and completed.',
                'order' => $order->fresh(),
            ]);
        }

        return back()->with('status', 'Order marked as received and completed.');
    }

    /*
    |--------------------------------------------------------------------------
    | ORDER CANCELLATION (BUYER-18)
    |--------------------------------------------------------------------------
    */

    /**
     * Buyer cancels an order before fulfillment.
     */
    public function cancel(
        Request $request,
        Order $order
    ): RedirectResponse {
        abort_unless(
            (int) $order->user_id ===
            (int) $request->user()->id,
            403
        );

        if (!$request->filled('reason') && $request->filled('cancellation_reason')) {
            $request->merge(['reason' => $request->input('cancellation_reason')]);
        }

        $data = $request->validate([
            'reason' => [
                'required',
                'string',
                'max:500',
            ],
        ]);

        $cancellableStatuses = ['pending', 'processing', 'packed'];

        if (!in_array(strtolower((string) $order->status), $cancellableStatuses, true)) {
            throw ValidationException::withMessages([
                'reason' => 'This order cannot be cancelled anymore as it has already progressed in fulfillment.',
            ]);
        }

        $order->load('items.product', 'items.variant');

        DB::transaction(function () use ($order, $data, $cancellableStatuses) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (!in_array(strtolower((string) $lockedOrder->status), $cancellableStatuses, true)) {
                throw ValidationException::withMessages([
                    'reason' => 'This order cannot be cancelled anymore as it has already progressed in fulfillment.',
                ]);
            }

            $finance = app(\App\Services\SellerShipmentFinance::class);
            foreach (\App\Models\SellerShipment::where('order_id', $order->id)->orderBy('id')->lockForUpdate()->get() as $shipment) {
                $finance->assertMutable($shipment);
            }
            $order->setRelation('items', $order->items()->with('product', 'variant')->lockForUpdate()->get());
            abort_unless($order->items->every(fn ($item) => in_array($item->status, $cancellableStatuses, true)), 422, 'This order can no longer be cancelled.');

            $orderUpdate = [
                'status' => 'cancelled',
                'cancellation_reason' => $data['reason'],
                'cancelled_at' => now(),
            ];

            // BUY-CAN-003: If the order was prepaid/paid, automatically initiate a full refund
            $wasPaid = in_array(strtolower((string) $order->payment_status), ['paid', 'completed'], true);
            if ($wasPaid) {
                $refundAmount = (float) $order->total;
                $orderUpdate['payment_status'] = 'refunded';
                if (Schema::hasColumn('orders', 'refund_status')) {
                    $orderUpdate['refund_status'] = 'refund_started';
                }
                if (Schema::hasColumn('orders', 'refund_amount')) {
                    $orderUpdate['refund_amount'] = $refundAmount;
                }

                if (Schema::hasTable('refund_ledgers')) {
                    $firstItem = $order->items->first();
                    $sellerId = $firstItem?->product?->seller_id ?? $firstItem?->seller_id;
                    \App\Models\RefundLedger::create([
                        'refund_reference' => 'REF-' . strtoupper(\Illuminate\Support\Str::random(8)),
                        'order_id' => $order->id,
                        'order_item_id' => $firstItem?->id,
                        'seller_id' => $sellerId,
                        'buyer_id' => $order->user_id,
                        'original_amount' => $refundAmount,
                        'refunded_amount' => $refundAmount,
                        'refund_type' => 'full',
                        'refund_method' => $order->payment_method ?? 'ewallet',
                        'status' => 'processing',
                        'reason' => $data['reason'],
                        'notes' => 'Auto-initiated full refund after buyer cancelled prepaid order.',
                    ]);
                }
            }

            $order->update($orderUpdate);

            foreach ($order->items as $item) {
                $itemStatus = strtolower((string) ($item->status ?? 'pending'));

                if (in_array($itemStatus, $cancellableStatuses, true)) {
                    $item->update([
                        'status' => 'cancelled',
                        'cancelled_at' => now(),
                    ]);

                    if (Schema::hasTable('order_item_status_histories')) {
                        $item->statusHistories()->create([
                            'status' => 'cancelled',
                            'note' => 'Order cancelled by buyer: ' . $data['reason'],
                        ]);
                    }

                    // Restock inventory
                    $this->restockOrderItem($item);

                    // Notify seller
                    $this->notifySellerCancelledOrder($order, $item, $data['reason']);
                }
            }

            $this->createOrderStatusHistory(
                $order,
                'cancelled'
            );

            // BUY-VOU-012 / SEL-VOU-011: Decrement voucher usage count & return voucher to buyer's wallet if not expired
            $order->restoreVouchers();

            // Buyer notification
            if (Schema::hasTable('buyer_notifications')) {
                BuyerNotification::create([
                    'user_id' => $order->user_id,
                    'title' => "Order #{$order->order_number} cancelled",
                    'message' => 'Your order has been cancelled successfully.',
                ]);
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Order cancelled successfully.',
                'order' => $order->fresh(),
            ]);
        }

        return back()->with(
            'status',
            'Order cancelled successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | REORDER (BUYER-20)
    |--------------------------------------------------------------------------
    */

    /**
     * Buyer re-adds items from a previous order into the cart.
     */
    public function reorder(
        Request $request,
        Order $order
    ): RedirectResponse {
        abort_unless(
            (int) $order->user_id ===
            (int) $request->user()->id,
            404
        );

        $order->load('items.product', 'items.variant');
        $user = $request->user();
        $addedCount = 0;

        foreach ($order->items as $item) {
            $product = $item->product;

            if (!$product) {
                continue;
            }

            if (Schema::hasColumn('products', 'status') && $product->status !== 'approved') {
                continue;
            }

            $stock = $this->stockForOrderItem($item);

            if ($stock <= 0) {
                continue;
            }

            $qtyToAdd = min((int) $item->quantity, $stock);

            if ($qtyToAdd <= 0) {
                $qtyToAdd = 1;
            }

            // Check if user already has this product + variant in cart
            $cartItemQuery = $user->cartItems()
                ->where('product_id', $product->id);

            if ($item->product_variant_id) {
                $cartItemQuery->where('product_variant_id', $item->product_variant_id);
            } else {
                $cartItemQuery->whereNull('product_variant_id');
            }

            $existingCartItem = $cartItemQuery->first();

            if ($existingCartItem) {
                $newQty = min($stock, (int) $existingCartItem->quantity + $qtyToAdd);
                $existingCartItem->update([
                    'quantity' => $newQty,
                ]);
            } else {
                $user->cartItems()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $item->product_variant_id,
                    'quantity' => $qtyToAdd,
                ]);
            }

            $addedCount++;
        }

        if ($addedCount === 0) {
            return back()->withErrors([
                'reorder' => 'None of the items from this order are currently available in stock.',
            ]);
        }

        return redirect()
            ->route('buyer.cart')
            ->with('status', "Added {$addedCount} item(s) from this order to your cart.");
    }

    /**
     * Reorder a single line item.
     */
    public function reorderItem(
        Request $request,
        OrderItem $orderItem
    ): RedirectResponse {
        $orderItem->load('order', 'product', 'variant');

        abort_unless(
            (int) $orderItem->order->user_id ===
            (int) $request->user()->id,
            404
        );

        $product = $orderItem->product;

        if (!$product || (Schema::hasColumn('products', 'status') && $product->status !== 'approved')) {
            return back()->withErrors([
                'reorder' => 'This product is no longer available.',
            ]);
        }

        $stock = $this->stockForOrderItem($orderItem);

        if ($stock <= 0) {
            return back()->withErrors([
                'reorder' => 'This product is currently out of stock.',
            ]);
        }

        $user = $request->user();
        $qtyToAdd = min((int) $orderItem->quantity, $stock);
        if ($qtyToAdd <= 0) {
            $qtyToAdd = 1;
        }

        $cartItemQuery = $user->cartItems()
            ->where('product_id', $product->id);

        if ($orderItem->product_variant_id) {
            $cartItemQuery->where('product_variant_id', $orderItem->product_variant_id);
        } else {
            $cartItemQuery->whereNull('product_variant_id');
        }

        $existingCartItem = $cartItemQuery->first();

        if ($existingCartItem) {
            $newQty = min($stock, (int) $existingCartItem->quantity + $qtyToAdd);
            $existingCartItem->update(['quantity' => $newQty]);
        } else {
            $user->cartItems()->create([
                'product_id' => $product->id,
                'product_variant_id' => $orderItem->product_variant_id,
                'quantity' => $qtyToAdd,
            ]);
        }

        return redirect()
            ->route('buyer.cart')
            ->with('status', "{$product->name} added to your cart.");
    }

    /*
    |--------------------------------------------------------------------------
    | RETURNS & REFUNDS (BUYER-21, BUYER-22, BUYER-23)
    |--------------------------------------------------------------------------
    */

    /**
     * Submit a return / refund request for an eligible order item.
     */
    public function storeReturnRequest(
        Request $request,
        OrderItem $orderItem
    ): RedirectResponse {
        $orderItem->load('order.user', 'product.seller', 'returnRequests');

        abort_unless(
            (int) $orderItem->order->user_id ===
            (int) $request->user()->id,
            404
        );

        $itemStatus = strtolower((string) ($orderItem->status ?? $orderItem->order->status));

        if (!in_array($itemStatus, ['delivered', 'completed'], true)) {
            throw ValidationException::withMessages([
                'reason' => 'Returns and refunds can only be requested after the item has been delivered.',
            ]);
        }

        // Return policy days check
        $returnDays = (int) ($orderItem->product?->seller?->return_policy_days ?? 7);
        if ($returnDays <= 0) {
            $returnDays = 7;
        }

        $deliveredAt = $orderItem->delivered_at ?? $orderItem->updated_at ?? now();
        $returnDeadline = Carbon::parse($deliveredAt)->addDays($returnDays);

        if (now()->greaterThan($returnDeadline)) {
            throw ValidationException::withMessages([
                'reason' => "The {$returnDays}-day return window for this item has expired.",
            ]);
        }

        // Check if an active return request already exists
        $hasActive = $orderItem->returnRequests()
            ->whereIn('status', ['pending', 'approved', 'completed'])
            ->exists();

        if ($hasActive) {
            throw ValidationException::withMessages([
                'reason' => 'A return or refund request for this item is already being processed.',
            ]);
        }

        if (! $request->filled('reason_details') && $request->filled('description')) {
            $request->merge(['reason_details' => $request->input('description')]);
        }

        $data = $request->validate([
            'type' => [
                'required',
                'in:return_and_refund,refund_only',
            ],
            'reason' => [
                'required',
                'string',
                'max:100',
            ],
            'reason_details' => [
                'nullable',
                'string',
                'max:1500',
            ],
            'evidence_images' => [
                'nullable',
                'array',
                'max:5',
            ],
            'evidence_images.*' => [
                'image',
                'mimes:jpeg,png,jpg,webp',
                'max:5120',
            ],
        ]);

        $evidencePaths = [];

        if ($request->hasFile('evidence_images')) {
            foreach ($request->file('evidence_images') as $file) {
                $evidencePaths[] = $file->store('returns', 'public');
            }
        }

        $sellerId = $orderItem->product?->seller_id ?? null;

        $returnRequest = OrderReturnRequest::create([
            'order_id' => $orderItem->order_id,
            'order_item_id' => $orderItem->id,
            'buyer_id' => $request->user()->id,
            'seller_id' => $sellerId,
            'type' => $data['type'],
            'reason' => $data['reason'],
            'reason_details' => $data['reason_details'] ?? null,
            'requested_amount' => (float) ($orderItem->price * $orderItem->quantity),
            'evidence_images' => $evidencePaths,
            'response_deadline' => now()->addDays(3),
            'status' => 'pending',
        ]);

        // Notify seller
        $this->notifySellerReturnRequest($returnRequest, $orderItem);

        // Notify buyer
        if (Schema::hasTable('buyer_notifications')) {
            BuyerNotification::create([
                'user_id' => $request->user()->id,
                'title' => 'Return/Refund request submitted',
                'message' => "Your request for {$orderItem->product_name} has been received and is pending seller review.",
            ]);
        }

        return back()->with(
            'status',
            'Return / refund request submitted successfully.'
        );
    }

    /**
     * Buyer cancels a pending return / refund request.
     */
    public function cancelReturnRequest(
        Request $request,
        OrderReturnRequest $returnRequest
    ): RedirectResponse {
        abort_unless(
            (int) $returnRequest->buyer_id ===
            (int) $request->user()->id,
            404
        );

        if ($returnRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Only pending return/refund requests can be cancelled.',
            ]);
        }

        $returnRequest->update([
            'status' => 'cancelled',
        ]);

        return back()->with(
            'status',
            'Return request has been cancelled.'
        );
    }

    /**
     * Buyer submits return shipping tracking details.
     */
    public function shipReturnRequest(
        Request $request,
        OrderReturnRequest $returnRequest
    ): RedirectResponse {
        abort_unless((int) $returnRequest->buyer_id === (int) $request->user()->id, 403);

        if ($returnRequest->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => 'Tracking can only be submitted for approved return requests.',
            ]);
        }

        $validated = $request->validate([
            'return_carrier' => 'required|string|max:100',
            'return_tracking_number' => 'required|string|max:100',
            'return_waybill_url' => 'nullable|string|max:2048',
        ]);

        $returnRequest->update([
            'return_carrier' => $validated['return_carrier'],
            'return_tracking_number' => $validated['return_tracking_number'],
            'return_waybill_url' => $validated['return_waybill_url'] ?? $returnRequest->return_waybill_url,
            'return_shipping_status' => 'in_transit',
            'buyer_shipped_at' => now(),
        ]);

        $returnRequest->addShippingMilestone(
            'in_transit',
            'Parcel Shipped by Buyer',
            'Buyer Drop-off / Dispatch',
            "Carrier: {$validated['return_carrier']} | Tracking: {$validated['return_tracking_number']}"
        );

        if (Schema::hasTable('seller_notifications')) {
            SellerNotification::create([
                'user_id' => $returnRequest->seller_id,
                'title' => 'Buyer Shipped Return Item',
                'message' => "Buyer has shipped returned item for Order #{$returnRequest->order->order_number} via {$validated['return_carrier']} (Tracking: {$validated['return_tracking_number']}).",
            ]);
        }

        return back()->with('status', 'Return shipping details submitted successfully. Seller has been notified.');
    }

    /**
     * Buyer escalates a rejected return request to a dispute (SEL-RET-004, SEL-RET-005 / A31).
     */
    public function escalateReturnRequest(
        Request $request,
        OrderReturnRequest $returnRequest
    ): RedirectResponse {
        abort_unless((int) $returnRequest->buyer_id === (int) $request->user()->id, 403);

        // SEL-RET-005: A second escalation is not possible
        if ($returnRequest->status === 'in_dispute' || $returnRequest->escalated_at !== null) {
            throw ValidationException::withMessages([
                'status' => 'This return request has already been escalated to a dispute.',
            ]);
        }

        if ($returnRequest->status !== 'rejected') {
            throw ValidationException::withMessages([
                'status' => 'Only rejected return requests can be escalated to a dispute.',
            ]);
        }

        $rejectedAt = $returnRequest->seller_responded_at ?? $returnRequest->resolved_at ?? $returnRequest->updated_at;
        if ($rejectedAt && Carbon::parse($rejectedAt)->addDays(3)->isPast()) {
            throw ValidationException::withMessages([
                'status' => 'The 3-day window to escalate this return request has expired.',
            ]);
        }

        $disputeReason = trim((string) ($request->input('dispute_reason') ?? $request->input('reason') ?? 'Buyer requested dispute escalation for rejected return claim.'));

        $returnRequest->update([
            'status' => 'in_dispute',
            'escalated_at' => now(),
            'dispute_reason' => $disputeReason,
        ]);

        $orderNumber = $returnRequest->order?->order_number ?? (string) $returnRequest->order_id;

        // Notify seller
        if (Schema::hasTable('seller_notifications')) {
            SellerNotification::create([
                'user_id' => $returnRequest->seller_id,
                'title' => 'Return Request Escalated to Dispute',
                'message' => "Buyer has escalated the rejected return request for Order #{$orderNumber} to dispute resolution.",
            ]);
        }

        // Notify buyer
        if (Schema::hasTable('buyer_notifications')) {
            BuyerNotification::create([
                'user_id' => $returnRequest->buyer_id,
                'title' => 'Dispute Escalated',
                'message' => "Your dispute for Order #{$orderNumber} has been escalated for administrative review.",
            ]);
        }

        return back()->with('status', 'Return request has been escalated to a dispute.');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Generate a unique order number.
     *
     * Example:
     * ALN-20260925-583214
     */
    private function generateOrderNumber(): string
    {
        do {
            $orderNumber =
                'ALN-' .
                now()->format('Ymd') .
                '-' .
                random_int(100000, 999999);

        } while (
            Order::query()
                ->where(
                    'order_number',
                    $orderNumber
                )
                ->exists()
        );

        return $orderNumber;
    }

    /**
     * Normalize selected cart IDs.
     */
    private function normalizeSelectedIds(
        mixed $selected
    ): array {
        if (!is_array($selected)) {
            return [];
        }

        return collect($selected)
            ->map(
                fn ($id) => (int) $id
            )
            ->filter(
                fn ($id) => $id > 0
            )
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get cart item unit price.
     */
    private function itemPrice(
        CartItem $item
    ): float {
        $varPrice = (float) ($item->variant?->price ?? 0);
        if ($varPrice > 0) {
            return $varPrice;
        }

        $prodPrice = (float) ($item->product?->price ?? 0);
        if ($prodPrice > 0) {
            return $prodPrice;
        }

        return (float) ($item->price ?? 0);
    }

    /**
     * Get available stock for cart item.
     */
    private function stockForCartItem(
        CartItem $item
    ): int {
        if ($item->variant) {
            if (
                isset(
                    $item->variant->stock
                )
            ) {
                return max(
                    0,
                    (int) $item->variant->stock
                );
            }

            if (
                isset(
                    $item->variant->quantity
                )
            ) {
                return max(
                    0,
                    (int) $item->variant->quantity
                );
            }
        }

        if (
            $item->product &&
            isset($item->product->stock)
        ) {
            return max(
                0,
                (int) $item->product->stock
            );
        }

        if (
            $item->product &&
            isset($item->product->quantity)
        ) {
            return max(
                0,
                (int) $item->product->quantity
            );
        }

        return 0;
    }

    /**
     * Create readable variant label.
     */
    private function variantLabel(
        mixed $variant
    ): ?string {
        if (!$variant) {
            return null;
        }

        $parts = [];

        if (
            isset($variant->color) &&
            $variant->color !== null &&
            $variant->color !== ''
        ) {
            $parts[] =
                'Color: ' .
                $variant->color;
        }

        if (
            isset($variant->size) &&
            $variant->size !== null &&
            $variant->size !== ''
        ) {
            $parts[] =
                'Size: ' .
                $variant->size;
        }

        if (
            isset($variant->name) &&
            $variant->name !== null &&
            $variant->name !== ''
        ) {
            $parts[] =
                $variant->name;
        }

        return empty($parts)
            ? null
            : implode(
                ' / ',
                array_unique($parts)
            );
    }

    /**
     * Calculate voucher discount.
     */
    private function calculateVoucherDiscount(
        Voucher $voucher,
        float $eligibleSubtotal,
        float $shippingFee = 0
    ): float {
        if ($eligibleSubtotal <= 0 && $voucher->type !== 'free_shipping') {
            return 0;
        }

        if ($voucher->type === 'free_shipping') {
            $shipping = max(0, $shippingFee);
            $cap = null;
            if (
                Schema::hasColumn('vouchers', 'max_discount') &&
                $voucher->max_discount !== null &&
                (float) $voucher->max_discount > 0
            ) {
                $cap = (float) $voucher->max_discount;
            } elseif ($voucher->value !== null && (float) $voucher->value > 0) {
                $cap = (float) $voucher->value;
            }

            if ($cap !== null) {
                return round(min($shipping, $cap), 2);
            }
            return round($shipping, 2);
        }

        $value = (float) $voucher->value;

        if ($value <= 0) {
            return 0;
        }

        if ($voucher->type === 'percentage') {
            $discount = $eligibleSubtotal * ($value / 100);

            if (
                Schema::hasColumn('vouchers', 'max_discount') &&
                $voucher->max_discount !== null &&
                (float) $voucher->max_discount > 0
            ) {
                $discount = min($discount, (float) $voucher->max_discount);
            }
        } else {
            $discount = $value;
        }

        return round(
            min(
                $discount,
                $eligibleSubtotal
            ),
            2
        );
    }

    /**
     * Format Philippine peso.
     */
    private function money(
        float|int $value
    ): string {
        return '₱' .
            number_format(
                (float) $value,
                2
            );
    }

    /**
     * Deduct product/variant stock.
     */
    private function deductStock(
        CartItem $cartItem
    ): void {
        $quantity =
            (int) $cartItem->quantity;

        if (
            $quantity <= 0
        ) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Variant stock
        |--------------------------------------------------------------------------
        */

        if ($cartItem->variant) {
            $variant =
                $cartItem->variant;

            if (
                Schema::hasColumn(
                    $variant->getTable(),
                    'stock'
                )
            ) {
                $affected = $variant->newQuery()
                    ->whereKey($variant->id)
                    ->where('stock', '>=', $quantity)
                    ->decrement('stock', $quantity);

                if ($affected === 0) {
                    throw ValidationException::withMessages([
                        'selected_items' => "Out of stock: {$cartItem->product?->name} is currently Sold out.",
                    ]);
                }
                $variant->refresh();
            } elseif (
                Schema::hasColumn(
                    $variant->getTable(),
                    'quantity'
                )
            ) {
                $variant->decrement(
                    'quantity',
                    $quantity
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Recalculate parent product stock
            |--------------------------------------------------------------------------
            */

            if (
                $cartItem->product &&
                Schema::hasColumn(
                    $cartItem->product->getTable(),
                    'stock'
                )
            ) {
                $product =
                    $cartItem->product;

                $variantStock = 0;

                if (
                    Schema::hasColumn(
                        $variant->getTable(),
                        'stock'
                    )
                ) {
                    $variantStock =
                        $variant->newQuery()
                            ->where(
                                'product_id',
                                $product->id
                            )
                            ->sum('stock');
                } elseif (
                    Schema::hasColumn(
                        $variant->getTable(),
                        'quantity'
                    )
                ) {
                    $variantStock =
                        $variant->newQuery()
                            ->where(
                                'product_id',
                                $product->id
                            )
                            ->sum('quantity');
                }

                $product->update([
                    'stock' =>
                        max(
                            0,
                            (int) $variantStock
                        ),
                ]);
            }

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Product stock
        |--------------------------------------------------------------------------
        */

        if (!$cartItem->product) {
            return;
        }

        $product =
            $cartItem->product;

        if (
            Schema::hasColumn(
                $product->getTable(),
                'stock'
            )
        ) {
            $affected = $product->newQuery()
                ->whereKey($product->id)
                ->where('stock', '>=', $quantity)
                ->decrement('stock', $quantity);

            if ($affected === 0) {
                throw ValidationException::withMessages([
                    'selected_items' => "Out of stock: {$product->name} is currently Sold out.",
                ]);
            }
            $product->refresh();
        } elseif (
            Schema::hasColumn(
                $product->getTable(),
                'quantity'
            )
        ) {
            $product->decrement(
                'quantity',
                $quantity
            );
        }
    }

    /**
     * Create order status history.
     */
    private function createOrderStatusHistory(
        Order $order,
        string $status
    ): void {
        if (
            !Schema::hasTable(
                'order_status_histories'
            )
        ) {
            return;
        }

        $table =
            (new OrderStatusHistory())
                ->getTable();

        $data = [
            'order_id' =>
                $order->id,

            'status' =>
                $status,
        ];

        if (
            Schema::hasColumn(
                $table,
                'note'
            )
        ) {
            $data['note'] =
                'Order status updated to ' .
                $status .
                '.';
        }

        if (
            Schema::hasColumn(
                $table,
                'created_at'
            )
        ) {
            $data['created_at'] =
                now();
        }

        if (
            Schema::hasColumn(
                $table,
                'updated_at'
            )
        ) {
            $data['updated_at'] =
                now();
        }

        DB::table($table)
            ->insert($data);
    }

    /**
     * Notify seller about a new order.
     */
    private function notifySellerNewOrder(
        Order $order,
        CartItem $cartItem
    ): void {
        if (
            !$cartItem->product
        ) {
            return;
        }

        $sellerId =
            $cartItem->product->seller_id ??
            null;

        if (!$sellerId) {
            return;
        }

        if (
            !Schema::hasTable(
                'seller_notifications'
            )
        ) {
            return;
        }

        $table =
            'seller_notifications';

        $columns =
            Schema::getColumnListing(
                $table
            );

        $data = [];

        if (
            in_array(
                'user_id',
                $columns,
                true
            )
        ) {
            $data['user_id'] =
                $sellerId;
        }

        if (
            in_array(
                'seller_id',
                $columns,
                true
            )
        ) {
            $data['seller_id'] =
                $sellerId;
        }

        if (
            in_array(
                'title',
                $columns,
                true
            )
        ) {
            $data['title'] =
                'New Order Received';
        }

        if (
            in_array(
                'message',
                $columns,
                true
            )
        ) {
            $data['message'] =
                'You received a new order for ' .
                $cartItem->product->name .
                '.';
        }

        if (
            in_array(
                'order_id',
                $columns,
                true
            )
        ) {
            $data['order_id'] =
                $order->id;
        }

        if (
            in_array(
                'read_at',
                $columns,
                true
            )
        ) {
            $data['read_at'] =
                null;
        }

        if (
            in_array(
                'created_at',
                $columns,
                true
            )
        ) {
            $data['created_at'] =
                now();
        }

        if (
            in_array(
                'updated_at',
                $columns,
                true
            )
        ) {
            $data['updated_at'] =
                now();
        }

        if (
            in_array(
                'user_id',
                $columns,
                true
            ) &&
            empty($data['user_id'])
        ) {
            return;
        }

        DB::table($table)
            ->insert($data);
    }

    /**
     * Notify seller when product stock becomes low.
     */
    private function notifySellerLowStock(
        CartItem $cartItem
    ): void {
        if (!$cartItem->product) {
            return;
        }

        $product = $cartItem->product->fresh() ?? $cartItem->product;
        if ($cartItem->variant) {
            $variant = $cartItem->variant->fresh() ?? $cartItem->variant;
            \App\Services\SellerNotificationService::sendLowStockAlert($product, $variant);
            return;
        }

        \App\Services\SellerNotificationService::sendLowStockAlert($product);
    }

    /**
     * Get current product stock.
     */
    private function stockForProduct(
        Product $product
    ): int {
        if (
            Schema::hasColumn(
                $product->getTable(),
                'stock'
            )
        ) {
            return max(
                0,
                (int) $product->stock
            );
        }

        if (
            Schema::hasColumn(
                $product->getTable(),
                'quantity'
            )
        ) {
            return max(
                0,
                (int) $product->quantity
            );
        }

        return 0;
    }

    /**
     * Restock inventory when an order item is cancelled.
     */
    private function restockOrderItem(
        OrderItem $item
    ): void {
        $quantity = (int) $item->quantity;

        if ($quantity <= 0) {
            return;
        }

        if ($item->variant) {
            $variant = $item->variant;

            if (Schema::hasColumn($variant->getTable(), 'stock')) {
                $variant->increment('stock', $quantity);
                $freshVariant = $variant->fresh();
                if ($freshVariant && $freshVariant->stock > 5 && Schema::hasColumn('product_variants', 'low_stock_alerted')) {
                    $freshVariant->update(['low_stock_alerted' => false]);
                }
            } elseif (Schema::hasColumn($variant->getTable(), 'quantity')) {
                $variant->increment('quantity', $quantity);
            }

            if ($item->product && Schema::hasColumn($item->product->getTable(), 'stock')) {
                $product = $item->product;
                $variantStock = 0;

                if (Schema::hasColumn($variant->getTable(), 'stock')) {
                    $variantStock = $variant->newQuery()
                        ->where('product_id', $product->id)
                        ->sum('stock');
                } elseif (Schema::hasColumn($variant->getTable(), 'quantity')) {
                    $variantStock = $variant->newQuery()
                        ->where('product_id', $product->id)
                        ->sum('quantity');
                }

                $updateData = ['stock' => max(0, (int) $variantStock)];
                $threshold = (int) ($product->low_stock_threshold ?? 5);
                if ((int) $variantStock > $threshold && Schema::hasColumn('products', 'low_stock_alerted')) {
                    $updateData['low_stock_alerted'] = false;
                }
                $product->update($updateData);
            }

            return;
        }

        if ($item->product) {
            $product = $item->product;

            if (Schema::hasColumn($product->getTable(), 'stock')) {
                $product->increment('stock', $quantity);
                $freshProduct = $product->fresh();
                $threshold = (int) ($freshProduct?->low_stock_threshold ?? 5);
                if ($freshProduct && $freshProduct->stock > $threshold && Schema::hasColumn('products', 'low_stock_alerted')) {
                    $freshProduct->update(['low_stock_alerted' => false]);
                }
            } elseif (Schema::hasColumn($product->getTable(), 'quantity')) {
                $product->increment('quantity', $quantity);
            }
        }
    }

    /**
     * Get stock for an order item (used for reorder eligibility checks).
     */
    private function stockForOrderItem(
        OrderItem $item
    ): int {
        if ($item->variant) {
            if (isset($item->variant->stock)) {
                return max(0, (int) $item->variant->stock);
            }
            if (isset($item->variant->quantity)) {
                return max(0, (int) $item->variant->quantity);
            }
        }

        if ($item->product) {
            if (isset($item->product->stock)) {
                return max(0, (int) $item->product->stock);
            }
            if (isset($item->product->quantity)) {
                return max(0, (int) $item->product->quantity);
            }
        }

        return 0;
    }

    /**
     * Notify seller when buyer cancels an order item.
     */
    private function notifySellerCancelledOrder(
        Order $order,
        OrderItem $item,
        string $reason
    ): void {
        $sellerId = $item->product?->seller_id;

        if (!$sellerId || !Schema::hasTable('seller_notifications')) {
            return;
        }

        SellerNotification::create([
            'user_id' => $sellerId,
            'title' => 'Order Item Cancelled by Buyer',
            'message' => "Order #{$order->order_number} for {$item->product_name} was cancelled. Reason: {$reason}",
        ]);
    }

    /**
     * Notify seller when buyer submits a return/refund request.
     */
    private function notifySellerReturnRequest(
        OrderReturnRequest $returnRequest,
        OrderItem $item
    ): void {
        \App\Services\SellerNotificationService::sendReturnRequestAlert($returnRequest);
    }
}
