<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'voucher_id',
        'voucher_code',
        'order_number',
        'subtotal',
        'discount',
        'total',
        'shipping_address',
        'payment_method',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
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
}