<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\SellerNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerOrderController extends Controller
{
    /**
     * Display seller order items.
     *
     * Sellers manage their own order items, not the entire order.
     *
     * One customer order can contain products from multiple sellers,
     * so sellers must only see and manage their own order items.
     */
    public function index(Request $request): Response
    {
        $sellerId = $request->user()->id;

        $items = OrderItem::query()
            ->whereHas('product', function ($query) use ($sellerId) {
                $query->where('seller_id', $sellerId);
            })
            ->with([
                'product:id,name,image_path',
                'order:id,order_number,user_id,payment_method,created_at',
                'order.user:id,name',
                'variant:id,product_id,color,size,stock',
            ])
            ->when(
                $request->filled('search'),
                function ($query) use ($request) {
                    $search = trim(
                        $request->input('search')
                    );

                    $query->where(function ($query) use ($search) {
                        $query
                            ->where(
                                'product_name',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'tracking_number',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhereHas(
                                'order',
                                function ($orderQuery) use ($search) {
                                    $orderQuery->where(
                                        'order_number',
                                        'like',
                                        "%{$search}%"
                                    );
                                }
                            )
                            ->orWhereHas(
                                'order.user',
                                function ($userQuery) use ($search) {
                                    $userQuery->where(
                                        'name',
                                        'like',
                                        "%{$search}%"
                                    );
                                }
                            );
                    });
                }
            )
            ->when(
                $request->filled('status') &&
                $request->input('status') !== 'All Orders',
                function ($query) use ($request) {
                    $status = strtolower(
                        trim(
                            str_replace(
                                ' ',
                                '_',
                                $request->input('status')
                            )
                        )
                    );

                    $query->where(
                        'status',
                        $status
                    );
                }
            )
            ->when(
                $request->filled('date_from'),
                function ($query) use ($request) {
                    $query->whereDate(
                        'order_items.created_at',
                        '>=',
                        $request->input('date_from')
                    );
                }
            )
            ->when(
                $request->filled('date_to'),
                function ($query) use ($request) {
                    $query->whereDate(
                        'order_items.created_at',
                        '<=',
                        $request->input('date_to')
                    );
                }
            )
            ->latest('order_items.created_at')
            ->paginate(10)
            ->withQueryString();

        /*
         * Base query containing only this seller's products.
         */
        $base = function () use ($sellerId) {
            return OrderItem::query()
                ->whereHas(
                    'product',
                    function ($query) use ($sellerId) {
                        $query->where(
                            'seller_id',
                            $sellerId
                        );
                    }
                );
        };

        return Inertia::render(
            'Seller/Orders',
            [
                'orders' => $items,

                'summary' => [
                    'total' => (clone $base())->count(),

                    'pending' => (clone $base())
                        ->where(
                            'status',
                            'pending'
                        )
                        ->count(),

                    'processing' => (clone $base())
                        ->where(
                            'status',
                            'processing'
                        )
                        ->count(),

                    'packed' => (clone $base())
                        ->where(
                            'status',
                            'packed'
                        )
                        ->count(),

                    'ready_for_pickup' => (clone $base())
                        ->where(
                            'status',
                            'ready_for_pickup'
                        )
                        ->count(),

                    'shipped' => (clone $base())
                        ->where(
                            'status',
                            'shipped'
                        )
                        ->count(),

                    'out_for_delivery' => (clone $base())
                        ->where(
                            'status',
                            'out_for_delivery'
                        )
                        ->count(),

                    'delivered' => (clone $base())
                        ->where(
                            'status',
                            'delivered'
                        )
                        ->count(),

                    'completed' => (clone $base())
                        ->where(
                            'status',
                            'completed'
                        )
                        ->count(),

                    'cancelled' => (clone $base())
                        ->where(
                            'status',
                            'cancelled'
                        )
                        ->count(),
                ],

                'filters' => $request->only([
                    'search',
                    'status',
                    'date_from',
                    'date_to',
                ]),
            ]
        );
    }

    /**
     * Display a specific seller order item.
     */
    public function show(
        Request $request,
        OrderItem $orderItem
    ): Response {
        $this->authorizeOwnership(
            $request,
            $orderItem
        );

        $orderItem->load([
            'product',
            'order.user',
            'variant',
            'statusHistories',
        ]);

        return Inertia::render(
            'Seller/OrderDetails',
            [
                'orderItem' => $orderItem,
            ]
        );
    }

    /**
     * Move order item:
     *
     * Pending → Processing
     */
    public function process(
        Request $request,
        OrderItem $orderItem
    ): RedirectResponse {
        $this->authorizeOwnership(
            $request,
            $orderItem
        );

        abort_unless(
            $orderItem->status === 'pending',
            422,
            'Only pending items can be moved to processing.'
        );

        /*
         * IMPORTANT:
         *
         * This must be "processing", not "packed".
         *
         * The previous version accidentally skipped the
         * processing stage.
         */
        $orderItem->moveTo(
            'processing',
            'Seller started preparing the item.'
        );

        return back()->with(
            'status',
            'Order moved to processing.'
        );
    }

    /**
     * Pack the order item:
     *
     * Processing → Packed
     */
    public function pack(
        Request $request,
        OrderItem $orderItem
    ): RedirectResponse {
        $this->authorizeOwnership(
            $request,
            $orderItem
        );

        abort_unless(
            $orderItem->status === 'processing',
            422,
            'Only processing items can be marked as packed.'
        );

        $orderItem->moveTo(
            'packed',
            'Seller packed the item and is preparing the waybill.'
        );

        return back()->with(
            'status',
            'Order marked as packed.'
        );
    }

    /**
     * Mark the order item as ready for courier pickup.
     *
     * Packed → Ready for Pickup
     *
     * The seller does not assign the courier here.
     *
     * Logistics/courier will handle:
     *
     * Ready for Pickup → Shipped
     * Shipped → Out for Delivery
     * Out for Delivery → Delivered
     */
    public function readyForPickup(
        Request $request,
        OrderItem $orderItem
    ): RedirectResponse {
        $this->authorizeOwnership(
            $request,
            $orderItem
        );

        abort_unless(
            $orderItem->status === 'packed',
            422,
            'Only packed items can be marked as ready for pickup.'
        );

        $orderItem->moveTo(
            'ready_for_pickup',
            'Seller packed the item and marked it ready for courier pickup.'
        );

        return back()->with(
            'status',
            'Order marked as ready for pickup.'
        );
    }

    /**
     * Cancel an order item.
     *
     * Seller can cancel only before the item is handed over
     * to logistics/courier.
     *
     * Allowed:
     *
     * Pending → Cancelled
     * Processing → Cancelled
     * Packed → Cancelled
     *
     * Stock is restored when cancellation happens.
     */
    public function cancel(
        Request $request,
        OrderItem $orderItem
    ): RedirectResponse {
        $this->authorizeOwnership(
            $request,
            $orderItem
        );

        abort_unless(
            in_array(
                $orderItem->status,
                [
                    'pending',
                    'processing',
                    'packed',
                ],
                true
            ),
            422,
            'This item can no longer be cancelled.'
        );

        $data = $request->validate([
            'reason' => [
                'nullable',
                'string',
                'max:500',
            ],
        ]);

        /*
         * Make sure the product relationship exists.
         */
        if (!$orderItem->relationLoaded('product')) {
            $orderItem->load('product');
        }

        /*
         * Make sure the variant relationship exists.
         */
        if (
            $orderItem->product_variant_id &&
            !$orderItem->relationLoaded('variant')
        ) {
            $orderItem->load('variant');
        }

        /*
         * Restore stock.
         *
         * Variant product:
         * restore variant stock.
         *
         * Non-variant product:
         * restore main product stock.
         */
        if ($orderItem->variant) {
            $orderItem->variant->increment(
                'stock',
                $orderItem->quantity
            );
        } elseif ($orderItem->product) {
            $orderItem->product->increment(
                'stock',
                $orderItem->quantity
            );
        }

        $reason = $data['reason']
            ?? 'Cancelled by seller.';

        /*
         * moveTo() handles:
         *
         * - status update
         * - cancellation timestamp
         * - status history
         * - buyer notification
         * - parent order status synchronization
         */
        $orderItem->moveTo(
            'cancelled',
            $reason
        );

        /*
         * Keep a seller-side notification/audit record.
         *
         * This is separate from the buyer notification
         * created by OrderItem::moveTo().
         */
        SellerNotification::create([
            'user_id' => $request->user()->id,
            'type' => 'order',
            'title' => 'Order cancelled',
            'message' =>
                "You cancelled {$orderItem->product_name} " .
                "from order #{$orderItem->order->order_number}.",
        ]);

        return back()->with(
            'status',
            'Order cancelled and stock restored.'
        );
    }

    /**
     * Save a seller note for the order item.
     */
    public function note(
        Request $request,
        OrderItem $orderItem
    ): RedirectResponse {
        $this->authorizeOwnership(
            $request,
            $orderItem
        );

        $data = $request->validate([
            'seller_note' => [
                'required',
                'string',
                'max:2000',
            ],
        ]);

        $orderItem->update([
            'seller_note' => $data['seller_note'],
        ]);

        return back()->with(
            'status',
            'Note saved.'
        );
    }

    /**
     * Make sure the order item belongs to the
     * currently authenticated seller.
     */
    private function authorizeOwnership(
        Request $request,
        OrderItem $orderItem
    ): void {
        /*
         * Load product when it has not already been loaded.
         */
        if (!$orderItem->relationLoaded('product')) {
            $orderItem->load('product');
        }

        abort_unless(
            $orderItem->product &&
            (int) $orderItem->product->seller_id ===
                (int) $request->user()->id,
            403
        );
    }
}