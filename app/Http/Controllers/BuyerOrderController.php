<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
     * Display buyer orders.
     */
    public function index(Request $request): Response
    {
        $orders = $request->user()
            ->orders()
            ->with([
                'items.product',
                'items.variant',
            ])
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Buyer/Orders', [
            'orders' => $orders,
        ]);
    }

    /**
     * Show checkout page for selected cart items.
     */
    public function checkout(Request $request): Response
    {
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
            ]);
        }

        $items = $request->user()
            ->cartItems()
            ->with([
                'product.category',
                'variant',
            ])
            ->whereIn('id', $selectedIds)
            ->get();

        foreach ($items as $item) {
            if (!$item->product) {
                continue;
            }

            if (
                Schema::hasColumn('products', 'status') &&
                $item->product->status !== 'approved'
            ) {
                continue;
            }

            $stock = $this->stockForCartItem($item);

            if ($stock < (int) $item->quantity) {
                continue;
            }
        }

        return Inertia::render('Buyer/Checkout', [
            'items' => $items,

            'selected_items' => $items
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all(),

            'addresses' => $request->user()
                ->addresses()
                ->latest()
                ->get(),
        ]);
    }

    /**
     * Show a specific buyer order.
     */
    public function show(
        Request $request,
        Order $order
    ): Response {
        abort_unless(
            (int) $order->user_id ===
            (int) $request->user()->id,
            404
        );

        $order->load([
            'items.product.category',
            'items.variant',
            'items.statusHistories',
        ]);

        return Inertia::render('Buyer/OrderDetails', [
            'order' => $order,
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
                'max:30',
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
            Schema::hasColumn('vouchers', 'is_active') &&
            !$voucher->is_active
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
                    return (int) $item->product->seller_id ===
                        (int) $voucher->seller_id;
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

        $discount = $this->calculateVoucherDiscount(
            $voucher,
            $eligibleSubtotal
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
                'value' => (float) $voucher->value,
                'discount' => $discount,

                'min_spend' => Schema::hasColumn(
                    'vouchers',
                    'min_spend'
                )
                    ? (float) ($voucher->min_spend ?? 0)
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
        $data = $request->validate([
            'shipping_address' => [
                'required',
                'string',
                'max:2000',
            ],

            'payment_method' => [
                'required',
                'in:cod',
            ],

            'voucher_code' => [
                'nullable',
                'string',
                'max:30',
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

                    $stock = $this->stockForCartItem(
                        $item
                    );

                    if (
                        $stock <
                        (int) $item->quantity
                    ) {
                        throw ValidationException::withMessages([
                            'selected_items' =>
                                "Insufficient stock for {$item->product->name}.",
                        ]);
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Calculate subtotal
                |--------------------------------------------------------------------------
                */

                $subtotal = $items->sum(
                    function ($item) {
                        return $this->itemPrice($item) *
                            (int) $item->quantity;
                    }
                );

                /*
                |--------------------------------------------------------------------------
                | Voucher
                |--------------------------------------------------------------------------
                */

                $voucher = null;
                $discount = 0;
                $eligibleSubtotal = 0;

                $voucherCode = trim(
                    (string) (
                        $data['voucher_code'] ?? ''
                    )
                );

                if ($voucherCode !== '') {
                    $voucher = Voucher::query()
                        ->whereRaw(
                            'LOWER(code) = ?',
                            [strtolower($voucherCode)]
                        )
                        ->lockForUpdate()
                        ->first();

                    if (!$voucher) {
                        throw ValidationException::withMessages([
                            'voucher_code' =>
                                'Voucher code not found.',
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Active status
                    |--------------------------------------------------------------------------
                    */

                    if (
                        Schema::hasColumn(
                            'vouchers',
                            'is_active'
                        ) &&
                        !$voucher->is_active
                    ) {
                        throw ValidationException::withMessages([
                            'voucher_code' =>
                                'This voucher is no longer active.',
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Start date
                    |--------------------------------------------------------------------------
                    */

                    if (
                        Schema::hasColumn(
                            'vouchers',
                            'starts_at'
                        ) &&
                        $voucher->starts_at
                    ) {
                        $startsAt = Carbon::parse(
                            $voucher->starts_at
                        )->toDateString();

                        if (
                            now()->toDateString() <
                            $startsAt
                        ) {
                            throw ValidationException::withMessages([
                                'voucher_code' =>
                                    'This voucher is not active yet.',
                            ]);
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Expiration date
                    |--------------------------------------------------------------------------
                    */

                    if (
                        Schema::hasColumn(
                            'vouchers',
                            'expires_at'
                        ) &&
                        $voucher->expires_at
                    ) {
                        $expiresAt = Carbon::parse(
                            $voucher->expires_at
                        )->toDateString();

                        if (
                            now()->toDateString() >
                            $expiresAt
                        ) {
                            throw ValidationException::withMessages([
                                'voucher_code' =>
                                    'This voucher has expired.',
                            ]);
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Usage limit
                    |--------------------------------------------------------------------------
                    */

                    if (
                        Schema::hasColumn(
                            'vouchers',
                            'usage_limit'
                        ) &&
                        $voucher->usage_limit !== null
                    ) {
                        $usedCount =
                            Schema::hasColumn(
                                'vouchers',
                                'used_count'
                            )
                                ? (int) $voucher->used_count
                                : 0;

                        if (
                            $usedCount >=
                            (int) $voucher->usage_limit
                        ) {
                            throw ValidationException::withMessages([
                                'voucher_code' =>
                                    'This voucher has reached its usage limit.',
                            ]);
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Seller-specific eligibility
                    |--------------------------------------------------------------------------
                    */

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
                                return (int)
                                    $item->product->seller_id ===
                                    (int)
                                    $voucher->seller_id;
                            }

                            return true;
                        }
                    );

                    if ($eligibleItems->isEmpty()) {
                        throw ValidationException::withMessages([
                            'voucher_code' =>
                                'This voucher does not apply to the selected products.',
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Eligible subtotal
                    |--------------------------------------------------------------------------
                    */

                    $eligibleSubtotal =
                        $eligibleItems->sum(
                            function ($item) {
                                return $this->itemPrice($item) *
                                    (int) $item->quantity;
                            }
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | Minimum spend
                    |--------------------------------------------------------------------------
                    */

                    if (
                        Schema::hasColumn(
                            'vouchers',
                            'min_spend'
                        ) &&
                        $voucher->min_spend !== null &&
                        $eligibleSubtotal <
                            (float) $voucher->min_spend
                    ) {
                        throw ValidationException::withMessages([
                            'voucher_code' =>
                                'Minimum spend of ' .
                                $this->money(
                                    $voucher->min_spend
                                ) .
                                ' is required for this voucher.',
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Calculate discount
                    |--------------------------------------------------------------------------
                    */

                    $discount =
                        $this->calculateVoucherDiscount(
                            $voucher,
                            $eligibleSubtotal
                        );

                    if ($discount <= 0) {
                        throw ValidationException::withMessages([
                            'voucher_code' =>
                                'This voucher does not provide a valid discount.',
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Increment usage
                    |--------------------------------------------------------------------------
                    */

                    if (
                        Schema::hasColumn(
                            'vouchers',
                            'used_count'
                        )
                    ) {
                        $voucher->increment(
                            'used_count'
                        );

                        $voucher->refresh();
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Final total
                |--------------------------------------------------------------------------
                */

                $total = max(
                    0,
                    $subtotal - $discount
                );

                /*
                |--------------------------------------------------------------------------
                | Create order
                |--------------------------------------------------------------------------
                |
                | IMPORTANT:
                | order_number is explicitly generated here.
                | This fixes the MySQL error:
                |
                | Field 'order_number' doesn't have a default value
                |
                */

                $orderNumber = $this->generateOrderNumber();

                $order = new Order();

                $order->order_number = $orderNumber;
                $order->user_id = $user->id;
                $order->shipping_address =
                    $data['shipping_address'];
                $order->payment_method =
                    $data['payment_method'];
                $order->subtotal = $subtotal;
                $order->total = $total;
                $order->status = 'pending';

                /*
                |--------------------------------------------------------------------------
                | Optional voucher columns
                |--------------------------------------------------------------------------
                */

                if (
                    $voucher &&
                    Schema::hasColumn(
                        'orders',
                        'voucher_id'
                    )
                ) {
                    $order->voucher_id =
                        $voucher->id;
                }

                if (
                    $voucher &&
                    Schema::hasColumn(
                        'orders',
                        'voucher_code'
                    )
                ) {
                    $order->voucher_code =
                        $voucher->code;
                }

                if (
                    Schema::hasColumn(
                        'orders',
                        'discount'
                    )
                ) {
                    $order->discount =
                        $discount;
                }

                $order->save();

                /*
                |--------------------------------------------------------------------------
                | Create order items
                |--------------------------------------------------------------------------
                */

                foreach ($items as $cartItem) {
                    $product =
                        $cartItem->product;

                    $unitPrice =
                        $this->itemPrice(
                            $cartItem
                        );

                    $quantity =
                        (int) $cartItem->quantity;

                    $orderItemData = [
                        'order_id' =>
                            $order->id,

                        'product_id' =>
                            $product->id,

                        'product_name' =>
                            $product->name,

                        'quantity' =>
                            $quantity,

                        'price' =>
                            $unitPrice,
                    ];

                    /*
                    |--------------------------------------------------------------------------
                    | Variant ID
                    |--------------------------------------------------------------------------
                    */

                    if (
                        Schema::hasColumn(
                            'order_items',
                            'product_variant_id'
                        ) &&
                        $cartItem->variant
                    ) {
                        $orderItemData[
                            'product_variant_id'
                        ] = $cartItem->variant->id;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Variant label
                    |--------------------------------------------------------------------------
                    */

                    if (
                        Schema::hasColumn(
                            'order_items',
                            'variant_label'
                        )
                    ) {
                        $orderItemData[
                            'variant_label'
                        ] =
                            $this->variantLabel(
                                $cartItem->variant
                            );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Optional subtotal
                    |--------------------------------------------------------------------------
                    */

                    if (
                        Schema::hasColumn(
                            'order_items',
                            'subtotal'
                        )
                    ) {
                        $orderItemData[
                            'subtotal'
                        ] =
                            $unitPrice *
                            $quantity;
                    }

                    OrderItem::create(
                        $orderItemData
                    );

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

                $this->createOrderStatusHistory(
                    $order,
                    'pending'
                );

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

        return redirect()
            ->route(
                'buyer.orders.show',
                $order
            )
            ->with(
                'status',
                'Order placed successfully.'
            );
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
            404
        );

        $currentStatus =
            $item->status ??
            $item->order->status;

        if (
            !in_array(
                strtolower(
                    (string) $currentStatus
                ),
                [
                    'shipped',
                    'out_for_delivery',
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'status' =>
                    'This item cannot be confirmed as delivered yet.',
            ]);
        }

        if (
            Schema::hasColumn(
                'order_items',
                'status'
            )
        ) {
            $item->update([
                'status' => 'delivered',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | If all order items are delivered, mark order delivered
        |--------------------------------------------------------------------------
        */

        $order = $item->order;

        $order->load('items');

        $allDelivered = true;

        foreach ($order->items as $orderItemModel) {
            $status =
                strtolower(
                    (string) (
                        $orderItemModel->status ??
                        $order->status
                    )
                );

            if ($status !== 'delivered') {
                $allDelivered = false;
                break;
            }
        }

        if ($allDelivered) {
            $order->update([
                'status' => 'delivered',
            ]);

            $this->createOrderStatusHistory(
                $order,
                'delivered'
            );
        }

        return back()->with(
            'status',
            'Delivery confirmed successfully.'
        );
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
        return (float) (
            $item->product?->price ??
            $item->price ??
            0
        );
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
        float $eligibleSubtotal
    ): float {
        if (
            $eligibleSubtotal <= 0
        ) {
            return 0;
        }

        $value =
            (float) $voucher->value;

        if (
            $value <= 0
        ) {
            return 0;
        }

        if (
            $voucher->type ===
            'percentage'
        ) {
            $discount =
                $eligibleSubtotal *
                ($value / 100);
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
                $variant->decrement(
                    'stock',
                    $quantity
                );
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
                                $variant->getForeignKey(),
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
                                $variant->getForeignKey(),
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
            $product->decrement(
                'stock',
                $quantity
            );
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
        if (
            !$cartItem->product
        ) {
            return;
        }

        $product =
            $cartItem->product;

        $sellerId =
            $product->seller_id ??
            null;

        if (!$sellerId) {
            return;
        }

        $stock =
            $this->stockForProduct(
                $product
            );

        if (
            $stock > 5
        ) {
            return;
        }

        if (
            !Schema::hasTable(
                'seller_notifications'
            )
        ) {
            return;
        }

        $columns =
            Schema::getColumnListing(
                'seller_notifications'
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
                'Low Stock Alert';
        }

        if (
            in_array(
                'message',
                $columns,
                true
            )
        ) {
            $data['message'] =
                $product->name .
                ' now has only ' .
                $stock .
                ' item(s) remaining.';
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

        DB::table(
            'seller_notifications'
        )->insert($data);
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
}