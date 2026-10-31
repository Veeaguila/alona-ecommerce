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
    /** Quote without changing the buyer's saved cart or consuming vouchers. */
    public function checkoutPreview(Request $request): JsonResponse
    {
        $items = $this->checkoutItems($request);
        return app(\App\Http\Controllers\BuyerOrderController::class)->mobileCheckoutPreview($request, $items);
    }

    /** All mobile bookings use the web transaction, stock locks, route snapshots and finance membership. */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'expected_shipping_fee' => ['required', 'numeric', 'min:0'],
            'expected_total' => ['required', 'numeric', 'min:0'],
            'expected_prices' => ['required', 'array', 'min:1'],
            'expected_prices.*' => ['required', 'numeric', 'min:0'],
            'request_key' => ['required', 'uuid'],
        ]);
        $key = 'buyer-checkout:'.$request->user()->id.':'.$request->input('request_key');
        $fingerprint = hash('sha256', json_encode($request->all()));
        return \Illuminate\Support\Facades\Cache::lock($key.':lock', 60)->block(5, function () use ($request, $key, $fingerprint) {
            $cached = \Illuminate\Support\Facades\Cache::get($key);
            if ($cached) {
                abort_unless(hash_equals($cached['fingerprint'], $fingerprint), 409, 'This checkout reference was already used with different details.');
                return response()->json($cached['response'], 201);
            }
            // The outer transaction also rolls back a temporary Buy Now row on any validation failure.
            $response = DB::transaction(function () use ($request) {
                $items = $this->checkoutItems($request);
                if ($request->boolean('buy_now')) {
                    $item = $items->sole();
                    $expected = $request->input('expected_prices.0');
                    abort_if($expected === null, 422, 'Refresh the Buy Now quote before ordering.');
                    unset($item->id);
                    $item->save();
                    $request->merge(['expected_prices' => [(string) $item->id => $expected]]);
                }
                $request->merge(['selected_items' => $items->pluck('id')->all()]);
                return app(\App\Http\Controllers\BuyerOrderController::class)->store($request);
            });
            \Illuminate\Support\Facades\Cache::put($key, ['fingerprint' => $fingerprint, 'response' => $response->getData(true)], now()->addDay());
            return $response;
        });
    }

    private function checkoutItems(Request $request)
    {
        $data = $request->validate([
            'buy_now' => ['nullable', 'boolean'],
            'cart_item_ids' => ['required_unless:buy_now,true', 'array', 'min:1'],
            'cart_item_ids.*' => ['required', 'integer', 'distinct'],
            'product_id' => ['required_if:buy_now,true', 'integer'],
            'product_variant_id' => ['nullable', 'integer'],
            'quantity' => ['required_if:buy_now,true', 'integer', 'min:1'],
        ]);
        if ($request->boolean('buy_now')) {
            $items = collect([new CartItem([
                'user_id' => $request->user()->id, 'product_id' => $data['product_id'],
                'product_variant_id' => $data['product_variant_id'] ?? null, 'quantity' => $data['quantity'],
            ])]);
            $items->sole()->id = 0; // Stable quote key; replaced by a transaction-local cart row on booking.
            $items->sole()->load(['product.category', 'product.variants', 'product.seller', 'variant']);
        } else {
            $items = $request->user()->cartItems()->whereIn('id', $data['cart_item_ids'])
                ->with(['product.category', 'product.variants', 'product.seller', 'variant'])->get();
            if ($items->count() !== count($data['cart_item_ids'])) {
                throw ValidationException::withMessages(['cart_item_ids' => 'Some selected cart items are no longer available.']);
            }
        }
        foreach ($items as $item) {
            if (!$item->product || $item->product->status !== 'approved' || $item->is_unavailable
                || ($item->variant && (int) $item->variant->product_id !== (int) $item->product_id)) {
                throw ValidationException::withMessages(['stock' => 'A selected product or variation is no longer available.']);
            }
            if ($item->product->seller?->vacation_mode) {
                throw ValidationException::withMessages(['stock' => 'This store is currently on vacation mode.']);
            }
            $available = $item->variant ? (int) $item->variant->stock : (int) $item->product->stock;
            if ($available < (int) $item->quantity) {
                throw ValidationException::withMessages(['stock' => 'Insufficient stock. Refresh your cart before ordering.']);
            }
        }
        return $items;
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
        $order = $request->user()->orders()->whereKey($id)->firstOrFail();
        return app(\App\Http\Controllers\BuyerOrderController::class)->show($request, $order);
    }

    /**
     * Cancel an order with a reason.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $order = $request->user()->orders()->whereKey($id)->firstOrFail();
        return app(\App\Http\Controllers\BuyerOrderController::class)->cancel($request, $order);
    }

    public function receive(Request $request, int $id): JsonResponse
    {
        $order = $request->user()->orders()->whereKey($id)->firstOrFail();
        return app(\App\Http\Controllers\BuyerOrderController::class)->orderReceived($request, $order);
    }

    /**
     * Reorder past items back into the cart.
     */
    public function reorder(Request $request, int $id): JsonResponse
    {
        $order = $request->user()->orders()->whereKey($id)->firstOrFail();
        return app(\App\Http\Controllers\BuyerOrderController::class)->reorder($request, $order);
    }
}
