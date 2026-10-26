<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Controller;
use App\Models\BuyerNotification;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturnRequest;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SellerNotification;
use App\Models\UserVoucher;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BuyerOrderApiController extends Controller
{
    /**
     * Preview and calculate checkout totals before placing order.
     */
    public function checkoutPreview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cart_item_ids' => ['required', 'array', 'min:1'],
            'cart_item_ids.*' => ['integer'],
            'shipping_method' => ['nullable', 'string', 'in:standard,express,pickup'],
            'voucher_code' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $items = $user->cartItems()
            ->whereIn('id', $validated['cart_item_ids'])
            ->with(['product.seller', 'variant'])
            ->get();

        if ($items->isEmpty()) {
            return response()->json(['message' => 'No items selected.'], 422);
        }

        $subtotal = 0.0;
        $sellerIds = [];

        foreach ($items as $item) {
            $price = $item->variant ? (float) $item->variant->price : (float) ($item->product?->price ?? 0);
            $subtotal += $price * (int) $item->quantity;
            if ($item->product?->seller_id) {
                $sellerIds[$item->product->seller_id] = true;
            }
        }

        $sellerCount = max(1, count($sellerIds));
        $shippingMethod = $validated['shipping_method'] ?? 'standard';
        $feePerSeller = match ($shippingMethod) {
            'express' => 120.0,
            'pickup' => 0.0,
            default => 50.0,
        };
        $shippingFee = $sellerCount * $feePerSeller;

        // Free shipping threshold check (₱2000)
        if ($shippingMethod !== 'express' && $subtotal >= 2000) {
            $shippingFee = 0.0;
        }

        // Voucher discount
        $discount = 0.0;
        $voucherData = null;
        if (! empty($validated['voucher_code'])) {
            $code = strtoupper(trim($validated['voucher_code']));
            $voucher = Voucher::where('code', $code)->where('is_active', true)->first();
            if ($voucher && ($voucher->min_spend === null || $subtotal >= (float) $voucher->min_spend)) {
                if ($voucher->type === 'percentage') {
                    $discount = ($subtotal * (float) $voucher->value) / 100;
                    if ($voucher->max_discount !== null && $discount > (float) $voucher->max_discount) {
                        $discount = (float) $voucher->max_discount;
                    }
                } elseif ($voucher->type === 'fixed') {
                    $discount = min($subtotal, (float) $voucher->value);
                } elseif ($voucher->type === 'free_shipping') {
                    $discount = min($shippingFee, (float) $voucher->value ?: $shippingFee);
                }
                $voucherData = [
                    'id' => $voucher->id,
                    'code' => $voucher->code,
                    'discount' => round($discount, 2),
                ];
            }
        }

        $total = max(0, ($subtotal - $discount) + $shippingFee);

        return response()->json([
            'subtotal' => round($subtotal, 2),
            'shipping_fee' => round($shippingFee, 2),
            'discount' => round($discount, 2),
            'total' => round($total, 2),
            'seller_count' => $sellerCount,
            'voucher' => $voucherData,
        ]);
    }

    /**
     * Store new order with full backend business rules, stock validation, and transactions.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cart_item_ids' => ['required', 'array', 'min:1'],
            'cart_item_ids.*' => ['integer'],
            'shipping_address' => ['required', 'string', 'max:1000'],
            'shipping_method' => ['required', 'string', 'in:standard,express,pickup'],
            'payment_method' => ['required', 'string', 'in:cod,gcash,maya,card,bank_transfer'],
            'buyer_note' => ['nullable', 'string', 'max:250'],
            'voucher_code' => ['nullable', 'string', 'max:50'],
        ]);

        $user = $request->user();

        return DB::transaction(function () use ($validated, $user) {
            $items = $user->cartItems()
                ->whereIn('id', $validated['cart_item_ids'])
                ->with(['product.seller', 'variant'])
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['cart_item_ids' => 'Selected cart items not found.']);
            }

            // Stock validation
            foreach ($items as $item) {
                $product = $item->product;
                if (! $product || $product->status !== 'approved') {
                    throw ValidationException::withMessages(['stock' => "Product '{$product?->name}' is no longer available."]);
                }
                if ($product->seller && $product->seller->vacation_mode) {
                    throw ValidationException::withMessages(['vacation' => "Store '{$product->seller->store_name}' is on vacation mode."]);
                }
                $available = $item->variant ? (int) $item->variant->stock : (int) $product->stock;
                if ($available < (int) $item->quantity) {
                    throw ValidationException::withMessages(['stock' => "Insufficient stock for '{$product->name}'. Available: {$available}."]);
                }
            }

            // Calculations
            $subtotal = 0.0;
            $sellerIds = [];
            foreach ($items as $item) {
                $price = $item->variant ? (float) $item->variant->price : (float) $item->product->price;
                $subtotal += $price * (int) $item->quantity;
                if ($item->product->seller_id) {
                    $sellerIds[$item->product->seller_id] = true;
                }
            }

            $sellerCount = max(1, count($sellerIds));
            $feePerSeller = match ($validated['shipping_method']) {
                'express' => 120.0,
                'pickup' => 0.0,
                default => 50.0,
            };
            $shippingFee = $sellerCount * $feePerSeller;
            if ($validated['shipping_method'] !== 'express' && $subtotal >= 2000) {
                $shippingFee = 0.0;
            }

            // Voucher validation & application
            $voucher = null;
            $discount = 0.0;
            if (! empty($validated['voucher_code'])) {
                $code = strtoupper(trim($validated['voucher_code']));
                $voucher = Voucher::where('code', $code)->where('is_active', true)->lockForUpdate()->first();
                if ($voucher && ($voucher->min_spend === null || $subtotal >= (float) $voucher->min_spend)) {
                    if ($voucher->type === 'percentage') {
                        $discount = ($subtotal * (float) $voucher->value) / 100;
                        if ($voucher->max_discount !== null && $discount > (float) $voucher->max_discount) {
                            $discount = (float) $voucher->max_discount;
                        }
                    } elseif ($voucher->type === 'fixed') {
                        $discount = min($subtotal, (float) $voucher->value);
                    } elseif ($voucher->type === 'free_shipping') {
                        $discount = min($shippingFee, (float) $voucher->value ?: $shippingFee);
                    }
                    $voucher->increment('used_count');
                    UserVoucher::where('user_id', $user->id)->where('voucher_id', $voucher->id)->update(['status' => 'used', 'used_at' => now()]);
                }
            }

            $total = max(0, ($subtotal - $discount) + $shippingFee);
            $paymentStatus = ($validated['payment_method'] === 'cod') ? 'unpaid' : 'paid';

            // Create Order
            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => 'ORD-' . strtoupper(Str::random(10)),
                'subtotal' => round($subtotal, 2),
                'shipping_fee' => round($shippingFee, 2),
                'discount' => round($discount, 2),
                'total' => round($total, 2),
                'shipping_address' => $validated['shipping_address'],
                'shipping_method' => $validated['shipping_method'],
                'payment_method' => $validated['payment_method'],
                'payment_status' => $paymentStatus,
                'buyer_note' => $validated['buyer_note'] ?? null,
                'voucher_id' => $voucher?->id,
                'voucher_code' => $voucher?->code,
                'status' => 'pending',
            ]);

            // Create Order Items and decrease stock
            foreach ($items as $item) {
                $product = $item->product;
                $variant = $item->variant;
                $unitPrice = $variant ? (float) $variant->price : (float) $product->price;

                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'seller_id' => $product->seller_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_name' => $product->name,
                    'variant_label' => $item->variant_label,
                    'unit_price' => $unitPrice,
                    'quantity' => (int) $item->quantity,
                    'total_price' => round($unitPrice * (int) $item->quantity, 2),
                    'status' => 'pending',
                ]);

                // Decrease stock
                if ($variant) {
                    $variant->decrement('stock', (int) $item->quantity);
                }
                $product->decrement('stock', (int) $item->quantity);

                // Notify seller
                if ($product->seller_id) {
                    SellerNotification::create([
                        'user_id' => $product->seller_id,
                        'order_id' => $order->id,
                        'type' => 'new_order',
                        'title' => 'New Order Received',
                        'message' => "Order #{$order->order_number} has been placed for {$product->name}.",
                    ]);
                }
            }

            // Create status history
            if (Schema::hasTable('order_status_histories')) {
                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status' => 'pending',
                    'notes' => 'Order placed successfully by buyer.',
                    'created_at' => now(),
                ]);
            }

            // Create Buyer notification
            BuyerNotification::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'type' => 'order_placed',
                'title' => 'Order Placed Successfully',
                'message' => "Your order #{$order->order_number} of ₱" . number_format($total, 2) . " has been placed.",
            ]);

            // Remove processed items from cart
            $user->cartItems()->whereIn('id', $validated['cart_item_ids'])->delete();

            return response()->json([
                'message' => 'Order placed successfully!',
                'order' => $order->load(['items.product']),
            ], 201);
        });
    }

    /**
     * Get Buyer Orders with status tab filter.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $statusTab = strtolower((string) $request->input('status', 'all'));

        $query = $user->orders()->with([
            'items.product.images',
            'items.variant',
        ])->latest('id');

        match ($statusTab) {
            'to_pay' => $query->where('payment_status', 'unpaid')->where('payment_method', '!=', 'cod')->where('status', '!=', 'cancelled'),
            'to_ship' => $query->whereIn('status', ['pending', 'processing', 'packed', 'ready_for_pickup'])->where(fn ($q) => $q->where('payment_status', 'paid')->orWhere('payment_method', 'cod')),
            'to_receive', 'in_transit' => $query->whereIn('status', ['shipped', 'out_for_delivery']),
            'delivered' => $query->where('status', 'delivered'),
            'completed' => $query->where('status', 'completed'),
            'cancelled' => $query->where('status', 'cancelled'),
            'returns' => $query->whereHas('returnRequests'),
            default => null,
        };

        $orders = $query->paginate(15);

        $counts = [
            'all' => $user->orders()->count(),
            'to_pay' => $user->orders()->where('payment_status', 'unpaid')->where('payment_method', '!=', 'cod')->where('status', '!=', 'cancelled')->count(),
            'to_ship' => $user->orders()->whereIn('status', ['pending', 'processing', 'packed', 'ready_for_pickup'])->where(fn ($q) => $q->where('payment_status', 'paid')->orWhere('payment_method', 'cod'))->count(),
            'to_receive' => $user->orders()->whereIn('status', ['shipped', 'out_for_delivery'])->count(),
            'completed' => $user->orders()->where('status', 'completed')->count(),
            'cancelled' => $user->orders()->where('status', 'cancelled')->count(),
        ];

        return response()->json([
            'orders' => $orders,
            'counts' => $counts,
        ]);
    }

    /**
     * Get Detailed Order with Status History and Tracking.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $order = $user->orders()
            ->whereKey($id)
            ->with([
                'items.product.seller',
                'items.product.images',
                'items.variant',
                'items.returnRequests',
            ])
            ->firstOrFail();

        $cancellableStatuses = ['pending', 'processing', 'packed'];
        $canCancel = in_array(strtolower((string) $order->status), $cancellableStatuses, true);

        $statusHistories = Schema::hasTable('order_status_histories')
            ? DB::table('order_status_histories')->where('order_id', $order->id)->orderBy('created_at', 'asc')->get()
            : [];

        return response()->json([
            'order' => $order,
            'can_cancel' => $canCancel,
            'can_mark_received' => strtolower((string) $order->status) === 'delivered',
            'status_histories' => $statusHistories,
        ]);
    }

    /**
     * Cancel an order with a reason.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:250'],
        ]);

        $user = $request->user();
        $order = $user->orders()->whereKey($id)->with('items.product', 'items.variant')->firstOrFail();

        $cancellableStatuses = ['pending', 'processing', 'packed'];
        if (! in_array(strtolower((string) $order->status), $cancellableStatuses, true)) {
            return response()->json(['message' => 'This order can no longer be cancelled as it is already being shipped.'], 422);
        }

        DB::transaction(function () use ($order, $validated) {
            $order->update([
                'status' => 'cancelled',
                'cancellation_reason' => $validated['reason'],
                'cancelled_at' => now(),
            ]);

            // Restore stocks
            foreach ($order->items as $item) {
                $item->update(['status' => 'cancelled']);
                if ($item->variant) {
                    $item->variant->increment('stock', (int) $item->quantity);
                }
                if ($item->product) {
                    $item->product->increment('stock', (int) $item->quantity);
                }
            }

            if (Schema::hasTable('order_status_histories')) {
                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status' => 'cancelled',
                    'notes' => 'Cancelled by buyer: ' . $validated['reason'],
                    'created_at' => now(),
                ]);
            }
        });

        return response()->json([
            'message' => 'Order cancelled successfully.',
            'order' => $order->fresh(),
        ]);
    }

    /**
     * Confirm receipt of delivered order.
     */
    public function receive(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $order = $user->orders()->whereKey($id)->firstOrFail();

        if (strtolower((string) $order->status) !== 'delivered') {
            return response()->json(['message' => 'Only delivered orders can be confirmed as received.'], 422);
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => 'completed']);
            $order->items()->update(['status' => 'completed']);

            if (Schema::hasTable('order_status_histories')) {
                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status' => 'completed',
                    'notes' => 'Buyer confirmed receipt of order.',
                    'created_at' => now(),
                ]);
            }
        });

        return response()->json([
            'message' => 'Thank you for confirming receipt! Order is now completed.',
            'order' => $order->fresh(),
        ]);
    }

    /**
     * Reorder past items back into the cart.
     */
    public function reorder(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $order = $user->orders()->whereKey($id)->with('items')->firstOrFail();

        $addedCount = 0;
        foreach ($order->items as $item) {
            $product = Product::where('status', 'approved')->find($item->product_id);
            if ($product && $product->stock > 0) {
                CartItem::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'product_id' => $product->id,
                        'product_variant_id' => $item->product_variant_id,
                    ],
                    [
                        'quantity' => min((int) $item->quantity, (int) $product->stock),
                        'variant_label' => $item->variant_label,
                    ]
                );
                $addedCount++;
            }
        }

        return response()->json([
            'message' => "{$addedCount} items added to your cart.",
            'cart_count' => $user->cartItems()->sum('quantity'),
        ]);
    }
}

