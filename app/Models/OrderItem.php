<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            if ($item->product_variant_id && empty($item->variant_label)) {
                $variant = ProductVariant::find($item->product_variant_id);
                if ($variant) {
                    $item->variant_label = $variant->label();
                }
            }
        });

        static::updating(function (self $item): void {
            if ($item->isDirty('seller_shipment_id') && $item->getOriginal('seller_shipment_id')) {
                throw new \LogicException('Shipment membership is immutable.');
            }
            if ($item->isDirty('status') && $item->status === 'cancelled' && $item->seller_shipment_id) {
                app(\App\Services\SellerShipmentFinance::class)->assertMutable($item->sellerShipment);
            }
        });
    }

    public function parcel(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Parcel::class);
    }

    /**
     * All supported order-item fulfillment statuses.
     *
     * Seller:
     * pending → processing → packed → ready_for_pickup
     *
     * Logistics:
     * ready_for_pickup → shipped → out_for_delivery → delivered
     *
     * Final:
     * delivered → completed
     *
     * Cancellation:
     * pending / processing / packed → cancelled
     */
    public const STATUSES = [
        'pending',
        'processing',
        'packed',
        'ready_for_pickup',
        'shipped',
        'out_for_delivery',
        'delivered',
        'completed',
        'cancelled',
    ];

    protected $fillable = [
        'seller_shipment_id',
        'order_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'variant_label',
        'price',
        'quantity',
        'status',
        'courier_name',
        'tracking_number',
        'scheduled_pickup_date',
        'pickup_time_slot',
        'preferred_carrier',
        'pickup_notes',
        'seller_note',
        'accepted_at',
        'packed_at',
        'ready_for_pickup_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
    ];

    protected $appends = [
        'seller_status_label',
        'can_cancel',
        'can_edit',
        'available_actions',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'quantity' => 'integer',
            'scheduled_pickup_date' => 'date',
            'accepted_at' => 'datetime',
            'packed_at' => 'datetime',
            'ready_for_pickup_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function sellerShipment(): BelongsTo
    {
        return $this->belongsTo(SellerShipment::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(
            ProductVariant::class,
            'product_variant_id'
        );
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(
            OrderItemStatusHistory::class
        )->latest();
    }

    public function returnRequests(): HasMany
    {
        return $this->hasMany(
            OrderReturnRequest::class
        )->latest();
    }

    public function latestReturnRequest()
    {
        return $this->hasOne(
            OrderReturnRequest::class
        )->latestOfMany();
    }

    public function isParcelPickedUp(): bool
    {
        if (in_array($this->status, ['shipped', 'out_for_delivery', 'delivered', 'completed', 'cancelled'], true)) {
            return true;
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('parcels')) {
            return false;
        }

        $parcel = $this->relationLoaded('parcel')
            ? $this->parcel
            : ($this->id ? Parcel::where('order_item_id', $this->id)->first() : null);

        if (! $parcel) {
            return false;
        }

        $pickedUpStatuses = [
            'PICKED_UP',
            'IN_TRANSIT',
            'AT_HUB',
            'ARRIVED_AT_SORTING_CENTER',
            'DISPATCHED_FROM_SORTING_CENTER',
            'OUT_FOR_DELIVERY',
            'DELIVERED',
            'FAILED_DELIVERY',
            'RETURN_TO_SENDER',
            'RETURNED_TO_SENDER',
        ];

        return in_array(strtoupper((string) $parcel->status), $pickedUpStatuses, true);
    }

    public function canBeCancelledBySeller(): bool
    {
        if (! in_array($this->status, ['pending', 'processing', 'packed', 'ready_for_pickup'], true)) {
            return false;
        }

        return ! $this->isParcelPickedUp();
    }

    public function getCanCancelAttribute(): bool
    {
        return $this->canBeCancelledBySeller();
    }

    public function getCanEditAttribute(): bool
    {
        return $this->canBeCancelledBySeller();
    }

    public function getAvailableActionsAttribute(): array
    {
        return match ($this->status) {
            'pending' => ['accept', 'cancel'],
            'processing' => ['pack', 'cancel'],
            'packed' => ['ready_for_pickup', 'cancel'],
            'ready_for_pickup' => $this->canBeCancelledBySeller()
                ? ['print_waybill', 'cancel']
                : ['print_waybill'],
            default => [],
        };
    }

    public function getSellerStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'New',
            'processing' => 'Accepted',
            'packed' => 'Packed',
            'ready_for_pickup' => 'Ready for Pickup',
            'shipped' => 'Shipped',
            'out_for_delivery' => 'Out for Delivery',
            'delivered' => 'Delivered',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    /**
     * Determine whether this item can move from its current
     * status to the requested status.
     */
    public function canMoveTo(string $status): bool
    {
        if ($status === 'cancelled') {
            return $this->canBeCancelledBySeller();
        }

        $allowedTransitions = [
            'pending' => [
                'processing',
                'cancelled',
            ],

            'processing' => [
                'packed',
                'cancelled',
            ],

            'packed' => [
                'ready_for_pickup',
                'cancelled',
            ],

            'ready_for_pickup' => [
                'shipped',
            ],

            'shipped' => [
                'out_for_delivery',
                'delivered',
            ],

            'out_for_delivery' => [
                'delivered',
            ],

            'delivered' => [
                'completed',
            ],

            'completed' => [],

            'cancelled' => [],
        ];

        return in_array(
            $status,
            $allowedTransitions[$this->status] ?? [],
            true
        );
    }

    /**
     * Update the fulfillment status.
     *
     * This method:
     *
     * 1. Validates the status transition.
     * 2. Updates the item status.
     * 3. Stamps the matching timestamp.
     * 4. Creates a status-history record.
     * 5. Notifies the buyer.
     * 6. Synchronizes the parent order status.
     */
    public function moveTo(
        string $status,
        ?string $note = null
    ): void {
        if (! $this->seller_shipment_id) {
            $this->applyStatus($status, $note);

            return;
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($status, $note): void {
            app(\App\Services\SellerShipmentFinance::class)->lockItem($this->id);
            $current = static::whereKey($this->id)->lockForUpdate()->firstOrFail();
            $this->setRawAttributes($current->getAttributes(), true);
            $this->unsetRelations();
            $this->applyStatus($status, $note);
        });
    }

    private function applyStatus(string $status, ?string $note): void
    {
        abort_unless(
            in_array($status, self::STATUSES, true),
            422,
            'Invalid order item status.'
        );

        abort_unless(
            $this->canMoveTo($status),
            422,
            "Order item cannot move from {$this->status} to {$status}."
        );

        $timestampColumn = match ($status) {
            'processing' => \Illuminate\Support\Facades\Schema::hasColumn('order_items', 'accepted_at') ? 'accepted_at' : null,

            'packed' => 'packed_at',

            'ready_for_pickup' => \Illuminate\Support\Facades\Schema::hasColumn('order_items', 'ready_for_pickup_at') ? 'ready_for_pickup_at' : null,

            'shipped',
            'out_for_delivery' => 'shipped_at',

            'delivered',
            'completed' => 'delivered_at',

            'cancelled' => 'cancelled_at',

            default => null,
        };

        $update = [
            'status' => $status,
        ];

        if ($timestampColumn) {
            $update[$timestampColumn] = now();
        }

        $this->update($update);

        /*
         * Record status history.
         */
        $this->statusHistories()->create([
            'status' => $status,
            'note' => $note,
        ]);

        /*
         * Reload the order relationship if necessary.
         */
        if (!$this->relationLoaded('order')) {
            $this->load('order');
        }

        /*
         * Notify the buyer.
         */
        if ($this->order?->user_id) {
            $statusLabel = match ($status) {
                'pending' => 'To Pack',
                'processing' => 'Processing',
                'packed' => 'Packed',
                'ready_for_pickup' => 'Ready for Pickup',
                'shipped' => 'Shipped',
                'out_for_delivery' => 'Out for Delivery',
                'delivered' => 'Delivered',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled',
                default => ucfirst(str_replace('_', ' ', $status)),
            };

            $message = $note
                ?: "{$this->product_name} is now {$statusLabel}.";

            \App\Models\BuyerNotification::create([
                'user_id' => $this->order->user_id,
                'title' => "Order #{$this->order->order_number} updated",
                'message' => $message,
            ]);
        }

        /*
         * Recalculate the parent order status.
         */
        $this->order?->syncStatusFromItems();
    }

    public function lineTotal(): float
    {
        return (float) $this->price * $this->quantity;
    }
}
