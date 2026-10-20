<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'delivery_fee_payer',
        'shipping_fee_before_discount',
        'shipping_discount',
        'user_id',
        'voucher_id',
        'voucher_code',
        'order_number',
        'subtotal',
        'discount',
        'total',
        'shipping_address',
        'buyer_note',
        'shipping_method',
        'shipping_fee',
        'payment_method',
        'payment_status',
        'refund_status',
        'refund_amount',
        'payment_method_id',
        'cancellation_reason',
        'cancelled_at',
        'status',
    ];

    protected $appends = [
        'buyer_status_label',
    ];

    private static bool $isAutoCancelling = false;

    protected static function booted(): void
    {
        static::retrieved(function (self $order): void {
            if (
                ! self::$isAutoCancelling &&
                $order->status === 'pending' &&
                $order->payment_status === 'unpaid' &&
                $order->payment_method !== 'cod' &&
                $order->created_at &&
                $order->created_at->lessThanOrEqualTo(now()->subHour())
            ) {
                self::$isAutoCancelling = true;
                try {
                    self::autoCancelExpiredUnpaidOrders();
                    $order->refresh();
                } finally {
                    self::$isAutoCancelling = false;
                }
            }
        });

        static::saved(function (self $order): void {
            $status = strtolower((string) ($order->status ?? ''));
            $refundStatus = strtolower((string) ($order->refund_status ?? ''));

            if (in_array($status, ['completed', 'delivered'], true) && $refundStatus !== 'refunded') {
                app(\App\Services\CommissionService::class)->recordCommissionsForOrder($order);
            } elseif ($status === 'cancelled' || $refundStatus === 'refunded') {
                app(\App\Services\CommissionService::class)->cancelCommissionsForOrder($order);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'shipping_fee_before_discount' => 'decimal:2',
            'shipping_discount' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function returnRequests(): HasMany
    {
        return $this->hasMany(OrderReturnRequest::class);
    }

    public function getBuyerStatusLabelAttribute(): string
    {
        $status = strtolower((string) ($this->status ?? 'pending'));
        $paymentStatus = strtolower((string) ($this->payment_status ?? 'unpaid'));
        $paymentMethod = strtolower((string) ($this->payment_method ?? 'cod'));

        if ($status === 'cancelled') {
            return 'Cancelled';
        }

        if ($status === 'completed') {
            return 'Completed';
        }

        if ($status === 'pending' && $paymentStatus === 'unpaid' && $paymentMethod !== 'cod') {
            return 'To Pay';
        }

        if (in_array($status, ['shipped', 'out_for_delivery', 'delivered'], true)) {
            return 'To Receive';
        }

        return 'To Ship';
    }

    /**
     * Recompute the overall order status from its line items.
     *
     * An order may contain products from multiple sellers.
     * Therefore, the parent order status must never simply
     * copy one item's status.
     *
     * The overall status represents the least-progressed
     * active item.
     */
    public function syncStatusFromItems(): void
    {
        $statuses = $this->items()
            ->when($this->delivery_fee_payer === 'seller', fn ($query) => $query->orderBy('id')->lockForUpdate())
            ->pluck('status');

        if ($statuses->isEmpty()) {
            return;
        }

        /*
         * If every item was cancelled, the whole order is cancelled.
         */
        if (
            $statuses->every(
                fn ($status) => $status === 'cancelled'
            )
        ) {
            $this->update([
                'status' => 'cancelled',
            ]);

            return;
        }

        /*
         * Ignore cancelled items when calculating
         * the progress of the remaining order.
         */
        $activeStatuses = $statuses
            ->filter(
                fn ($status) => $status !== 'cancelled'
            )
            ->values();

        /*
         * If there are no active items left, the order
         * is cancelled.
         */
        if ($activeStatuses->isEmpty()) {
            $this->update([
                'status' => 'cancelled',
            ]);

            return;
        }

        /*
         * Status priority represents fulfillment progress.
         *
         * The order uses the LOWEST active progress so that
         * the overall status never gets ahead of an item.
         */
        $priority = [
            'pending' => 1,
            'processing' => 2,
            'packed' => 3,
            'ready_for_pickup' => 4,
            'shipped' => 5,
            'out_for_delivery' => 6,
            'delivered' => 7,
            'completed' => 8,
        ];

        $lowestProgress = $activeStatuses
            ->map(
                fn ($status) =>
                    $priority[$status] ?? 1
            )
            ->min();

        $status = collect($priority)
            ->search(
                fn ($value) =>
                    $value === $lowestProgress
            );

        /*
         * If every active item has reached completed/delivered,
         * the overall order is completed.
         */
        if (
            $activeStatuses->every(
                fn ($itemStatus) =>
                    in_array(
                        $itemStatus,
                        [
                            'delivered',
                            'completed',
                        ],
                        true
                    )
            )
        ) {
            $status = 'completed';
        }

        $this->update([
            'status' => $status,
        ]);
    }

    /**
     * Auto-cancel unpaid online/e-wallet/card orders older than 1 hour (SEL-INV-004, A6)
     * and restore their reserved product/variant stock.
     */
    public static function autoCancelExpiredUnpaidOrders(): int
    {
        $cutoff = now()->subHour();

        $expiredIds = self::query()
            ->where('payment_status', 'unpaid')
            ->where('payment_method', '!=', 'cod')
            ->where('status', 'pending')
            ->where('created_at', '<=', $cutoff)
            ->pluck('id');

        if ($expiredIds->isEmpty()) {
            return 0;
        }

        $cancelledCount = 0;

        foreach ($expiredIds as $orderId) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($orderId, &$cancelledCount) {
                $order = self::query()
                    ->whereKey($orderId)
                    ->lockForUpdate()
                    ->first();

                if (
                    ! $order ||
                    $order->status !== 'pending' ||
                    $order->payment_status !== 'unpaid' ||
                    $order->payment_method === 'cod'
                ) {
                    return;
                }

                $order->load(['items.product', 'items.variant']);

                $order->update([
                    'status' => 'cancelled',
                    'cancellation_reason' => 'Cancelled (unpaid)',
                    'cancelled_at' => now(),
                ]);

                foreach ($order->items as $item) {
                    $item->update([
                        'status' => 'cancelled',
                        'cancelled_at' => now(),
                    ]);

                    if (\Illuminate\Support\Facades\Schema::hasTable('order_item_status_histories')) {
                        $item->statusHistories()->create([
                            'status' => 'cancelled',
                            'note' => 'Order auto-cancelled after 1 hour unpaid.',
                        ]);
                    }

                    $qty = (int) $item->quantity;
                    if ($qty > 0) {
                        if ($item->variant) {
                            $item->variant->increment('stock', $qty);
                            $freshVariant = $item->variant->fresh();
                            if ($freshVariant && $freshVariant->stock > 5 && \Illuminate\Support\Facades\Schema::hasColumn('product_variants', 'low_stock_alerted')) {
                                $freshVariant->update(['low_stock_alerted' => false]);
                            }
                            if ($item->product) {
                                $totalStock = (int) $item->product->variants()->sum('stock');
                                $updateData = ['stock' => max(0, $totalStock)];
                                $threshold = (int) ($item->product->low_stock_threshold ?? 5);
                                if ($totalStock > $threshold && \Illuminate\Support\Facades\Schema::hasColumn('products', 'low_stock_alerted')) {
                                    $updateData['low_stock_alerted'] = false;
                                }
                                $item->product->update($updateData);
                            }
                        } elseif ($item->product) {
                            $item->product->increment('stock', $qty);
                            $freshProduct = $item->product->fresh();
                            $threshold = (int) ($freshProduct?->low_stock_threshold ?? 5);
                            if ($freshProduct && $freshProduct->stock > $threshold && \Illuminate\Support\Facades\Schema::hasColumn('products', 'low_stock_alerted')) {
                                $freshProduct->update(['low_stock_alerted' => false]);
                            }
                        }
                    }
                }

                if ($order->voucher_id && $order->voucher) {
                    if (\Illuminate\Support\Facades\Schema::hasColumn('vouchers', 'used_count') && (int) $order->voucher->used_count > 0) {
                        $order->voucher->decrement('used_count');
                    }
                    if (\Illuminate\Support\Facades\Schema::hasTable('user_vouchers')) {
                        UserVoucher::where('user_id', $order->user_id)
                            ->where('voucher_id', $order->voucher_id)
                            ->where('status', 'used')
                            ->update([
                                'status' => 'claimed',
                                'used_at' => null,
                            ]);
                    }
                }

                if (\Illuminate\Support\Facades\Schema::hasTable('order_status_histories')) {
                    \Illuminate\Support\Facades\DB::table('order_status_histories')->insert([
                        'order_id' => $order->id,
                        'status' => 'cancelled',
                        'note' => 'Order auto-cancelled (unpaid after 1 hour).',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $cancelledCount++;
            });
        }

        return $cancelledCount;
    }
}
