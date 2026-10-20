<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class CommissionService
{
    /**
     * Standard platform commission rate (10%).
     */
    public const DEFAULT_COMMISSION_RATE = 10.00;

    /**
     * Automatically record platform commission for an order.
     */
    public function recordCommissionsForOrder(Order $order): void
    {
        try {
            $status = strtolower((string) ($order->status ?? ''));
            if (! in_array($status, ['completed', 'delivered'], true)) {
                return;
            }

            // If order was refunded or cancelled, do not record or ensure cancelled
            $refundStatus = strtolower((string) ($order->refund_status ?? ''));
            if ($refundStatus === 'refunded' || $status === 'cancelled') {
                $this->cancelCommissionsForOrder($order);
                return;
            }

            $order->loadMissing(['items.product']);

            if ($order->items->isEmpty()) {
                return;
            }

            // Group items by seller
            $itemsBySeller = $order->items->groupBy(function ($item) {
                return $item->product?->seller_id ?? null;
            });

            foreach ($itemsBySeller as $sellerId => $items) {
                if (! $sellerId) {
                    continue;
                }

                $sellerTotal = (float) $items->sum(function ($item) {
                    return (float) ($item->price ?? 0) * (int) ($item->quantity ?? 1);
                });

                if ($sellerTotal <= 0) {
                    continue;
                }

                $commissionRate = self::DEFAULT_COMMISSION_RATE;
                $commissionAmount = round($sellerTotal * ($commissionRate / 100), 2);
                $sellerAmount = round($sellerTotal - $commissionAmount, 2);

                Commission::updateOrCreate(
                    [
                        'order_id' => $order->id,
                        'seller_id' => $sellerId,
                    ],
                    [
                        'sale_amount' => $sellerTotal,
                        'commission_rate' => $commissionRate,
                        'commission_amount' => $commissionAmount,
                        'seller_amount' => $sellerAmount,
                        'status' => 'completed',
                        'recorded_at' => $order->updated_at ?? now(),
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::error('Failed to auto-record commissions for order ' . $order->id, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Cancel commission entries for an order (e.g. upon refund or cancellation).
     */
    public function cancelCommissionsForOrder(Order $order): void
    {
        Commission::where('order_id', $order->id)->update([
            'status' => 'cancelled',
        ]);
    }

    /**
     * Sync any eligible completed/delivered orders that lack commission records.
     */
    public function syncCompletedOrders(): int
    {
        $orders = Order::query()
            ->whereIn('status', ['completed', 'delivered'])
            ->where(function ($q) {
                $q->whereNull('refund_status')
                  ->orWhere('refund_status', '!=', 'refunded');
            })
            ->whereDoesntHave('items', function ($q) {
                // If needed
            })
            ->with(['items.product'])
            ->get();

        $synced = 0;
        foreach ($orders as $order) {
            $hasCommission = Commission::where('order_id', $order->id)->exists();
            if (! $hasCommission) {
                $this->recordCommissionsForOrder($order);
                $synced++;
            }
        }

        return $synced;
    }
}

