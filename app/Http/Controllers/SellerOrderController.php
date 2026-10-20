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
                                    if (is_numeric($search)) {
                                        $orderQuery->orWhere('id', (int) $search);
                                    }
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
                        if (is_numeric($search)) {
                            $query->orWhere('order_items.id', (int) $search);
                        }
                    });
                }
            )
            ->when(
                $request->filled('status') &&
                $request->input('status') !== 'All Orders',
                function ($query) use ($request) {
                    $rawStatus = strtolower(trim((string) $request->input('status')));
                    $status = str_replace([' ', '/', '-'], '_', $rawStatus);

                    if (in_array($status, ['return_refund', 'returned', 'returns'], true)) {
                        $query->whereHas('returnRequests');
                    } elseif ($status === 'new') {
                        $query->whereDoesntHave('returnRequests')->where('status', 'pending');
                    } elseif ($status === 'to_ship') {
                        $query->whereDoesntHave('returnRequests')->whereIn('status', ['processing', 'packed', 'ready_for_pickup']);
                    } elseif (in_array($status, ['shipping', 'in_transit'], true)) {
                        $query->whereDoesntHave('returnRequests')->whereIn('status', ['shipped', 'out_for_delivery']);
                    } elseif ($status === 'completed') {
                        $query->whereDoesntHave('returnRequests')->whereIn('status', ['delivered', 'completed']);
                    } elseif ($status === 'delivered') {
                        $query->whereDoesntHave('returnRequests')->whereIn('status', ['delivered', 'completed']);
                    } elseif ($status === 'cancelled') {
                        $query->whereDoesntHave('returnRequests')->where('status', 'cancelled');
                    } elseif ($status === 'accepted') {
                        $query->where('status', 'processing');
                    } else {
                        $query->where('status', $status);
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
                        ->whereDoesntHave('returnRequests')
                        ->where(
                            'status',
                            'cancelled'
                        )
                        ->count(),

                    'new' => (clone $base())
                        ->whereDoesntHave('returnRequests')
                        ->where('status', 'pending')
                        ->count(),

                    'to_ship' => (clone $base())
                        ->whereDoesntHave('returnRequests')
                        ->whereIn('status', ['processing', 'packed', 'ready_for_pickup'])
                        ->count(),

                    'shipping' => (clone $base())
                        ->whereDoesntHave('returnRequests')
                        ->whereIn('status', ['shipped', 'out_for_delivery'])
                        ->count(),

                    'completed_tab' => (clone $base())
                        ->whereDoesntHave('returnRequests')
                        ->whereIn('status', ['delivered', 'completed'])
                        ->count(),

                    'return_refund' => (clone $base())
                        ->whereHas('returnRequests')
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

        $sellerId = (int) $request->user()->id;

        $orderItem->load([
            'product',
            'order.user',
            'variant',
            'statusHistories',
            'parcel.pickupRequests',
        ]);

        // Load only this seller's items in the parent order (SEL-ORD-007 & SEL-ORD-008)
        $sellerItems = collect();
        if ($orderItem->order) {
            $sellerItems = OrderItem::query()
                ->where('order_id', $orderItem->order_id)
                ->whereHas('product', fn ($q) => $q->where('seller_id', $sellerId))
                ->with(['product:id,seller_id,name,image_path,weight', 'variant:id,product_id,color,size,sku'])
                ->orderBy('id')
                ->get();

            $orderItem->order->setRelation('items', $sellerItems);
        }

        $sellerSubtotal = (float) $sellerItems->sum(fn (OrderItem $i) => (float) $i->price * (int) $i->quantity);
        $parentOrder = $orderItem->order;
        $discountAmount = (float) ($parentOrder?->discount ?? 0);
        $shippingFee = (float) ($parentOrder?->shipping_fee ?? 0);

        $orderSummary = [
            'order_id' => $parentOrder?->id,
            'order_number' => $parentOrder?->order_number,
            'items' => $sellerItems,
            'subtotal' => number_format($sellerSubtotal, 2, '.', ''),
            'voucher_code' => $parentOrder?->voucher_code,
            'discount' => number_format($discountAmount, 2, '.', ''),
            'voucher_label' => $parentOrder?->voucher_code
                ? ($parentOrder->voucher_code . ' −₱' . number_format($discountAmount, 2))
                : null,
            'shipping_fee' => number_format($shippingFee, 2, '.', ''),
            'total' => number_format(max(0, $sellerSubtotal - $discountAmount) + $shippingFee, 2, '.', ''),
            'buyer_note' => $parentOrder?->buyer_note,
            'payment_method' => $parentOrder?->payment_method,
            'payment_method_label' => strtoupper((string) ($parentOrder?->payment_method ?? 'COD')),
            'payment_status' => $parentOrder?->payment_status,
            'refund_status' => $parentOrder?->refund_status,
            'refund_amount' => $parentOrder?->refund_amount !== null
                ? number_format((float) $parentOrder->refund_amount, 2, '.', '')
                : null,
            'shipping_address' => $parentOrder?->shipping_address,
        ];

        $deliveryService = app(\App\Services\SellerParcelDelivery::class);
        $delivery = $deliveryService->details($orderItem, $sellerId);
        $unverifiedLink = $orderItem->parcel && $delivery === null;
        if ($unverifiedLink) $orderItem->unsetRelation('parcel');

        return Inertia::render(
            'Seller/OrderDetails',
            [
                'orderItem' => $orderItem,
                'sellerItems' => $sellerItems,
                'orderSummary' => $orderSummary,
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

        $waybill = $this->buildWaybillData($orderItem, $request->user());

        return Inertia::render('Seller/ShippingDocuments', [
            'item' => $orderItem,
            'waybill' => $waybill,
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

        $waybill = $this->buildWaybillData($orderItem, $request->user());

        return Inertia::render('Seller/ShippingDocuments', [
            'item' => $orderItem,
            'waybill' => $waybill,
            'initialTab' => $request->query('tab', 'waybill'),
        ]);
    }

    private function buildWaybillData(OrderItem $orderItem, $seller): array
    {
        $unitWeight = (float) ($orderItem->product?->weight ?? 0.8);
        if ($unitWeight <= 0) {
            $unitWeight = 0.8;
        }
        $totalWeight = round($unitWeight * max(1, (int) $orderItem->quantity), 2);
        $isCod = strtolower((string) ($orderItem->order?->payment_method ?? 'cod')) === 'cod';
        $codAmount = $isCod
            ? (float) ($orderItem->order?->total ?? $orderItem->lineTotal())
            : 0.0;

        $pickupAddress = $orderItem->parcel?->collection_address
            ?: ($seller?->address ?: trim(implode(', ', array_filter([
                $seller?->street_address,
                $seller?->barangay,
                $seller?->municipality,
                $seller?->province,
            ]))));

        return [
            'tracking_number' => $orderItem->tracking_number,
            'order_number' => $orderItem->order?->order_number,
            'buyer_name' => $orderItem->order?->user?->name,
            'shipping_address' => $orderItem->order?->shipping_address,
            'seller_store_name' => $seller?->store_name ?: $seller?->name,
            'pickup_address' => $pickupAddress,
            'weight_kg' => $totalWeight,
            'weight_label' => number_format($totalWeight, 1, '.', '') . ' kg',
            'payment_method' => strtoupper((string) ($orderItem->order?->payment_method ?? 'COD')),
            'cod_amount' => number_format($codAmount, 2, '.', ''),
            'cod_label' => $isCod ? ('COD ₱' . number_format($codAmount, 2)) : 'PREPAID (₱0.00)',
        ];
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
            $orderItem->canBeCancelledBySeller(),
            422,
            'This item can no longer be cancelled.'
        );

        if (! $request->filled('reason') && $request->filled('cancellation_reason')) {
            $request->merge(['reason' => $request->input('cancellation_reason')]);
        }

        $data = $request->validate([
            'reason' => [
                'required',
                'string',
                'min:2',
                'max:500',
            ],
        ], [
            'reason.required' => 'Please select or enter a cancellation reason.',
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

        \Illuminate\Support\Facades\DB::transaction(function () use ($orderItem, $request, $data): void {
        $finance = app(\App\Services\SellerShipmentFinance::class);
        $finance->assertMutable($finance->lockItem($orderItem->id));
        $orderItem = OrderItem::with('product', 'variant', 'order', 'parcel')->lockForUpdate()->findOrFail($orderItem->id);
        abort_unless($orderItem->canBeCancelledBySeller(), 422, 'This item can no longer be cancelled.');
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

        $parentOrder = $orderItem->order ? $orderItem->order->fresh() : null;
        if ($parentOrder && $parentOrder->status === 'cancelled') {
            $orderUpdate = [
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
            ];

            // SEL-ORD-005: If the order was prepaid/paid, start a full refund
            $wasPaid = in_array(strtolower((string) $parentOrder->payment_status), ['paid', 'completed'], true);
            if ($wasPaid) {
                $refundAmount = (float) ($parentOrder->total ?? $orderItem->lineTotal());
                $orderUpdate['payment_status'] = 'refunded';
                if (\Illuminate\Support\Facades\Schema::hasColumn('orders', 'refund_status')) {
                    $orderUpdate['refund_status'] = 'refund_started';
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('orders', 'refund_amount')) {
                    $orderUpdate['refund_amount'] = $refundAmount;
                }

                if (\Illuminate\Support\Facades\Schema::hasTable('refund_ledgers')) {
                    \App\Models\RefundLedger::create([
                        'refund_reference' => 'REF-' . strtoupper(\Illuminate\Support\Str::random(8)),
                        'order_id' => $parentOrder->id,
                        'order_item_id' => $orderItem->id,
                        'seller_id' => $request->user()->id,
                        'buyer_id' => $parentOrder->user_id,
                        'original_amount' => $refundAmount,
                        'refunded_amount' => $refundAmount,
                        'refund_type' => 'full',
                        'refund_method' => $parentOrder->payment_method ?? 'ewallet',
                        'status' => 'processing',
                        'reason' => $reason,
                        'notes' => 'Auto-initiated full refund after seller cancelled prepaid order.',
                    ]);
                }
            }

            $parentOrder->update($orderUpdate);
            $parentOrder->refresh();
        }

        // SEL-VOU-011 / SEL-ORD-004 / A21: If entire order is now cancelled, restore voucher usage count and wallet claim
        if ($parentOrder && $parentOrder->status === 'cancelled' && $parentOrder->voucher_id) {
            $voucher = \App\Models\Voucher::whereKey($parentOrder->voucher_id)->lockForUpdate()->first();
            if ($voucher) {
                if (\Illuminate\Support\Facades\Schema::hasColumn('vouchers', 'used_count') && (int) $voucher->used_count > 0) {
                    $voucher->decrement('used_count');
                }
                if (\Illuminate\Support\Facades\Schema::hasTable('user_vouchers')) {
                    $notExpired = ! $voucher->expires_at || ! $voucher->expires_at->copy()->endOfDay()->isPast();
                    $notEnded = $voucher->is_active && (! \Illuminate\Support\Facades\Schema::hasColumn('vouchers', 'ended_at') || $voucher->ended_at === null);
                    if ($notExpired && $notEnded) {
                        \App\Models\UserVoucher::where('user_id', $parentOrder->user_id)
                            ->where('voucher_id', $voucher->id)
                            ->where('status', 'used')
                            ->update([
                                'status' => 'claimed',
                                'used_at' => null,
                            ]);
                    }
                }
            }
        }

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
