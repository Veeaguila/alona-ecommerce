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
        \App\Models\Order::autoCancelExpiredUnpaidOrders();

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

                    if ($status === 'to_ship') {
                        $query->whereIn('status', ['processing', 'packed', 'ready_for_pickup']);
                    } elseif ($status === 'in_transit') {
                        $query->whereIn('status', ['shipped', 'out_for_delivery']);
                    } elseif ($status === 'delivered') {
                        $query->whereIn('status', ['delivered', 'completed']);
                    } elseif ($status === 'returned') {
                        $query->whereHas('returnRequests');
                    } else {
                        $query->where(
                            'status',
                            $status
                        );
                    }
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
            'parcel.pickupRequests',
        ]);

        $deliveryService = app(\App\Services\SellerParcelDelivery::class);
        $delivery = $deliveryService->details($orderItem, (int) $request->user()->id);
        $unverifiedLink = $orderItem->parcel && $delivery === null;
        if ($unverifiedLink) $orderItem->unsetRelation('parcel');

        return Inertia::render(
            'Seller/OrderDetails',
            [
                'orderItem' => $orderItem,
                'deliveryDetails' => $delivery,
                'deliveryLinkUnverified' => $unverifiedLink,
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

        $pickup = $request->validate([
            'collection_address' => ['nullable', 'string', 'max:1000'],
            'scheduled_date' => ['nullable', 'date'],
            'time_slot' => ['nullable', 'string', 'in:morning,afternoon,anytime'],
            'carrier_partner' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($orderItem, $pickup): void {
            app(\App\Services\SellerShipmentFinance::class)->assertMutable(
                app(\App\Services\SellerShipmentFinance::class)->lockItem($orderItem->id)
            );
            $orderItem = OrderItem::query()->lockForUpdate()->findOrFail($orderItem->id);
            $orderItem->moveTo(
                'ready_for_pickup',
                'Seller packed the item and marked it ready for courier pickup.'
            );
            app(\App\Services\OrderItemPickup::class)->create(
                $orderItem,
                $pickup['collection_address'] ?? null,
                $pickup
            );
        });

        return back()->with(
            'status',
            'Order marked as ready for pickup.'
        );
    }

    /**
     * Schedule or reschedule courier pickup (SELLER-19).
     */
    public function schedulePickup(
        Request $request,
        OrderItem $orderItem
    ): RedirectResponse {
        $this->authorizeOwnership(
            $request,
            $orderItem
        );

        abort_unless(
            in_array($orderItem->status, ['processing', 'packed', 'ready_for_pickup'], true),
            422,
            'Pickup can only be scheduled for processing, packed, or ready for pickup orders.'
        );

        $validated = $request->validate([
            'scheduled_date' => ['required', 'date'],
            'time_slot' => ['required', 'string', 'in:morning,afternoon,anytime'],
            'carrier_partner' => ['required', 'string', 'max:100'],
            'collection_address' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($orderItem, $validated): void {
            $orderItem = OrderItem::query()->lockForUpdate()->findOrFail($orderItem->id);

            if ($orderItem->status === 'packed') {
                $orderItem->moveTo(
                    'ready_for_pickup',
                    'Seller scheduled courier pickup for ' . $validated['scheduled_date'] . ' (' . $validated['time_slot'] . ').'
                );
            }

            if ($orderItem->status === 'ready_for_pickup') {
                app(\App\Services\OrderItemPickup::class)->create(
                    $orderItem,
                    $validated['collection_address'] ?? null,
                    $validated
                );
            } else {
                $orderItem->update([
                    'scheduled_pickup_date' => $validated['scheduled_date'],
                    'pickup_time_slot' => $validated['time_slot'],
                    'preferred_carrier' => $validated['carrier_partner'],
                    'pickup_notes' => $validated['notes'] ?? null,
                    'courier_name' => $validated['carrier_partner'],
                ]);
            }
        });

        return back()->with(
            'status',
            'Courier pickup scheduled successfully for ' . $validated['scheduled_date'] . ' (' . ucfirst($validated['time_slot']) . ') with ' . $validated['carrier_partner'] . '.'
        );
    }

    /**
     * Waybill view and thermal/A4 printing (SELLER-17).
     */
    public function waybill(
        Request $request,
        OrderItem $orderItem
    ): Response {
        $this->authorizeOwnership($request, $orderItem);

        $orderItem->load([
            'product.seller',
            'order.user',
            'variant',
            'parcel.pickupRequests',
        ]);

        return Inertia::render('Seller/ShippingDocuments', [
            'item' => $orderItem,
            'initialTab' => 'waybill',
        ]);
    }

    /**
     * Unified shipping documents viewer.
     */
    public function shippingDocuments(
        Request $request,
        OrderItem $orderItem
    ): Response {
        $this->authorizeOwnership($request, $orderItem);

        $orderItem->load([
            'product.seller',
            'order.user',
            'variant',
            'parcel.pickupRequests',
        ]);

        return Inertia::render('Seller/ShippingDocuments', [
            'item' => $orderItem,
            'initialTab' => $request->query('tab', 'waybill'),
        ]);
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
        \Illuminate\Support\Facades\DB::transaction(function () use ($orderItem, $request, $data): void {
        $finance = app(\App\Services\SellerShipmentFinance::class);
        $finance->assertMutable($finance->lockItem($orderItem->id));
        $orderItem = OrderItem::with('product', 'variant', 'order')->lockForUpdate()->findOrFail($orderItem->id);
        abort_unless(in_array($orderItem->status, ['pending', 'processing', 'packed'], true), 422, 'This item can no longer be cancelled.');
        if ($orderItem->variant) {
            $orderItem->variant->increment(
                'stock',
                $orderItem->quantity
            );
            $freshVariant = $orderItem->variant->fresh();
            if ($freshVariant && $freshVariant->stock > 5 && \Illuminate\Support\Facades\Schema::hasColumn('product_variants', 'low_stock_alerted')) {
                $freshVariant->update(['low_stock_alerted' => false]);
            }
            if ($orderItem->product) {
                $totalStock = (int) $orderItem->product->variants()->sum('stock');
                $updateData = ['stock' => max(0, $totalStock)];
                $threshold = (int) ($orderItem->product->low_stock_threshold ?? 5);
                if ($totalStock > $threshold && \Illuminate\Support\Facades\Schema::hasColumn('products', 'low_stock_alerted')) {
                    $updateData['low_stock_alerted'] = false;
                }
                $orderItem->product->update($updateData);
            }
        } elseif ($orderItem->product) {
            $orderItem->product->increment(
                'stock',
                $orderItem->quantity
            );
            $freshProduct = $orderItem->product->fresh();
            $threshold = (int) ($freshProduct?->low_stock_threshold ?? 5);
            if ($freshProduct && $freshProduct->stock > $threshold && \Illuminate\Support\Facades\Schema::hasColumn('products', 'low_stock_alerted')) {
                $freshProduct->update(['low_stock_alerted' => false]);
            }
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

        });
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
