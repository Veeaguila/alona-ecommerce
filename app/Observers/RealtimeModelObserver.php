<?php

namespace App\Observers;

use App\Models\AdminNotification;
use App\Models\BuyerNotification;
use App\Models\CodCollection;
use App\Models\CodRemittance;
use App\Models\Complaint;
use App\Models\ConversationMessage;
use App\Models\FlashSale;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturnRequest;
use App\Models\Parcel;
use App\Models\ParcelReturn;
use App\Models\PickupRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RefundLedger;
use App\Models\Rider;
use App\Models\RiderDeliveryEarning;
use App\Models\RiderNotification;
use App\Models\SellerNotification;
use App\Models\SellerPromotion;
use App\Models\SortingCenterMessage;
use App\Models\User;
use App\Services\RealtimeBroadcastService;
use Illuminate\Database\Eloquent\Model;

class RealtimeModelObserver
{
    public function __construct(protected RealtimeBroadcastService $broadcaster)
    {
    }

    public function created(Model $model): void
    {
        $this->handle($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->handle($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->handle($model, 'deleted');
    }

    protected function handle(Model $model, string $action): void
    {
        if ($model instanceof Product) {
            if ($action === 'created' || $action === 'deleted' || $model->wasChanged(['stock', 'price', 'status', 'name', 'low_stock_threshold'])) {
                $this->broadcaster->broadcastStockUpdate($model, null, $action);
            }

            return;
        }

        if ($model instanceof ProductVariant) {
            if ($action === 'created' || $action === 'deleted' || $model->wasChanged(['stock', 'price', 'color', 'size', 'variant_attributes', 'sku', 'is_active'])) {
                $product = $model->product ?: Product::find($model->product_id);
                if ($product) {
                    $this->broadcaster->broadcastStockUpdate($product, $model, 'variant_' . $action);
                }
            }

            return;
        }

        if ($model instanceof FlashSale) {
            $this->broadcaster->broadcastPromotionUpdate('flash_sale', $model, $action);

            return;
        }

        if ($model instanceof SellerPromotion) {
            $this->broadcaster->broadcastPromotionUpdate((string) ($model->type ?: 'bundle'), $model, $action);

            return;
        }

        if ($model instanceof Order) {
            if ($action === 'created' || $model->wasChanged(['status', 'payment_status', 'total_amount', 'tracking_number'])) {
                $eventAction = $action;
                if ($action === 'updated' && $model->wasChanged('payment_status')) {
                    $eventAction = 'payment_' . $model->payment_status;
                } elseif ($action === 'updated' && $model->wasChanged('status')) {
                    $eventAction = 'status_' . $model->status;
                }
                $this->broadcaster->broadcastOrderUpdate($model, $eventAction);
            }

            return;
        }

        if ($model instanceof OrderItem) {
            if ($action === 'created' || $model->wasChanged(['status', 'quantity'])) {
                $this->broadcaster->broadcastOrderItemUpdate($model, $action === 'created' ? 'item_created' : 'item_' . $model->status);
            }

            return;
        }

        if ($model instanceof Parcel) {
            if ($action === 'created' || $model->wasChanged([
                'status',
                'current_status',
                'assigned_rider_id',
                'pickup_rider_id',
                'rider_id',
                'sorting_center_id',
                'current_center_id',
                'sorted_area_id',
                'failure_reason',
            ])) {
                $statusVal = (string) ($model->status ?? $model->current_status ?? $action);
                $eventAction = $action === 'created'
                    ? 'created'
                    : ($model->wasChanged('pickup_rider_id')
                        ? 'pickup_assigned'
                        : ($model->wasChanged('assigned_rider_id') ? 'delivery_assigned' : 'status_' . strtolower($statusVal)));
                $this->broadcaster->broadcastParcelUpdate($model, $eventAction);
            }

            return;
        }

        if ($model instanceof PickupRequest) {
            if ($action === 'created' || $model->wasChanged(['status', 'sorting_center_id', 'scheduled_date', 'time_slot'])) {
                $this->broadcaster->broadcastPickupUpdate($model, $action === 'created' ? 'created' : 'status_' . strtolower((string) $model->status));
            }

            return;
        }

        if ($model instanceof OrderReturnRequest) {
            if ($action === 'created' || $model->wasChanged(['status', 'refund_amount', 'restocked', 'return_tracking_number', 'admin_decision'])) {
                $this->broadcaster->broadcastReturnUpdate($model, $action === 'created' ? 'created' : 'status_' . $model->status);
            }

            return;
        }

        if ($model instanceof RefundLedger) {
            $this->broadcaster->broadcastFinancialUpdate(
                $model->order_id ? (int) $model->order_id : null,
                'refund_' . ($model->status ?? $action),
                [
                    'refund_ledger_id' => (int) $model->id,
                    'buyer_id' => $model->buyer_id ? (int) $model->buyer_id : null,
                    'seller_id' => $model->seller_id ? (int) $model->seller_id : null,
                    'amount' => (float) ($model->amount ?? 0),
                    'status' => (string) ($model->status ?? ''),
                ]
            );

            return;
        }

        if ($model instanceof CodCollection) {
            $parcel = $model->parcel ?: ($model->parcel_id ? Parcel::find($model->parcel_id) : null);
            $rider = $model->rider ?: ($model->rider_id ? Rider::find($model->rider_id) : null);
            $this->broadcaster->broadcastFinancialUpdate(
                $parcel?->order_id ? (int) $parcel->order_id : null,
                'cod_collection_' . $action,
                [
                    'cod_collection_id' => (int) $model->id,
                    'parcel_id' => $model->parcel_id ? (int) $model->parcel_id : null,
                    'rider_id' => $model->rider_id ? (int) $model->rider_id : null,
                    'rider_user_id' => $rider?->user_id ? (int) $rider->user_id : null,
                    'sorting_center_id' => $parcel?->current_center_id ? (int) $parcel->current_center_id : null,
                    'amount' => (float) ($model->amount_collected ?? $model->expected_amount ?? 0),
                    'status' => (string) ($model->status ?? ''),
                ]
            );

            return;
        }

        if ($model instanceof CodRemittance) {
            $rider = $model->rider_id ? Rider::find($model->rider_id) : null;
            $this->broadcaster->broadcastFinancialUpdate(
                null,
                'cod_remittance_' . $action,
                [
                    'cod_remittance_id' => (int) $model->id,
                    'rider_id' => $model->rider_id ? (int) $model->rider_id : null,
                    'rider_user_id' => $rider?->user_id ? (int) $rider->user_id : null,
                    'sorting_center_id' => $model->sorting_center_id ? (int) $model->sorting_center_id : null,
                    'amount' => (float) ($model->amount ?? $model->remitted_amount ?? 0),
                    'status' => (string) ($model->status ?? ''),
                ]
            );

            return;
        }

        if ($model instanceof RiderDeliveryEarning) {
            $rider = $model->rider_id ? Rider::find($model->rider_id) : null;
            $this->broadcaster->broadcastFinancialUpdate(
                $model->order_id ? (int) $model->order_id : null,
                'rider_earning_' . $action,
                [
                    'rider_earning_id' => (int) $model->id,
                    'rider_id' => $model->rider_id ? (int) $model->rider_id : null,
                    'rider_user_id' => $rider?->user_id ? (int) $rider->user_id : null,
                    'amount' => (float) ($model->total_earning ?? $model->amount ?? 0),
                    'status' => (string) ($model->status ?? ''),
                ]
            );

            return;
        }

        if ($model instanceof ParcelReturn) {
            $parcel = $model->parcel_id ? Parcel::find($model->parcel_id) : null;
            if ($parcel) {
                $this->broadcaster->broadcastParcelUpdate($parcel, 'return_to_seller_' . ($model->status ?? $action), [
                    'parcel_return_id' => (int) $model->id,
                    'return_status' => (string) ($model->status ?? ''),
                ]);
            }

            return;
        }

        if ($model instanceof ConversationMessage && $action === 'created') {
            $this->broadcaster->broadcastMessage($model);

            return;
        }

        if ($model instanceof SortingCenterMessage && $action === 'created') {
            $channels = ['logistics.global', 'admin.global'];
            if ($model->sorting_center_id) {
                $channels[] = 'logistics.center.' . (int) $model->sorting_center_id;
            }
            if ($model->receiver_id) {
                $channels[] = 'user.' . (int) $model->receiver_id;
                $channels[] = 'rider.' . (int) $model->receiver_id;
            }
            $this->broadcaster->emit('logistics_chat', 'message_sent', $channels, [
                'message_id' => (int) $model->id,
                'sorting_center_id' => $model->sorting_center_id ? (int) $model->sorting_center_id : null,
                'sender_id' => $model->sender_id ? (int) $model->sender_id : null,
                'receiver_id' => $model->receiver_id ? (int) $model->receiver_id : null,
                'message' => (string) ($model->message ?? ''),
            ]);

            return;
        }

        if ($model instanceof BuyerNotification && $action === 'created') {
            $this->broadcaster->broadcastNotification('buyer', (int) $model->user_id, [
                'id' => (int) $model->id,
                'type' => (string) $model->type,
                'title' => (string) $model->title,
                'message' => (string) $model->message,
                'data' => $model->data,
                'is_read' => (bool) $model->is_read,
                'created_at' => optional($model->created_at)->toIso8601String(),
            ]);

            return;
        }

        if ($model instanceof SellerNotification && $action === 'created') {
            $this->broadcaster->broadcastNotification('seller', (int) $model->user_id, [
                'id' => (int) $model->id,
                'type' => (string) $model->type,
                'title' => (string) $model->title,
                'message' => (string) $model->message,
                'data' => $model->data,
                'is_read' => (bool) $model->is_read,
                'created_at' => optional($model->created_at)->toIso8601String(),
            ]);

            return;
        }

        if ($model instanceof AdminNotification && $action === 'created') {
            $this->broadcaster->broadcastNotification('admin', null, [
                'id' => (int) $model->id,
                'type' => (string) $model->type,
                'title' => (string) $model->title,
                'message' => (string) $model->message,
                'data' => $model->data,
                'is_read' => (bool) $model->is_read,
                'created_at' => optional($model->created_at)->toIso8601String(),
            ]);

            return;
        }

        if ($model instanceof RiderNotification && $action === 'created') {
            $userId = (int) ($model->user_id ?: optional($model->rider)->user_id ?: 0);
            if ($userId > 0) {
                $this->broadcaster->broadcastNotification('rider', $userId, [
                    'id' => (int) $model->id,
                    'type' => (string) $model->type,
                    'title' => (string) $model->title,
                    'message' => (string) $model->message,
                    'data' => $model->data,
                    'is_read' => (bool) ($model->read_at !== null || $model->is_read),
                    'created_at' => optional($model->created_at)->toIso8601String(),
                ]);
            }

            return;
        }

        if ($model instanceof User) {
            if ($action === 'created' || $model->wasChanged(['usertype', 'status', 'seller_registration_completed_at'])) {
                $this->broadcaster->broadcastAdminActivity('user_' . $action, [
                    'user_id' => (int) $model->id,
                    'usertype' => (string) ($model->usertype ?? 'buyer'),
                    'status' => (string) ($model->status ?? 'pending'),
                ]);
                if ($model->wasChanged('status')) {
                    $this->broadcaster->emit('account', 'status_changed', ['user.' . (int) $model->id], [
                        'user_id' => (int) $model->id,
                        'usertype' => (string) ($model->usertype ?? 'buyer'),
                        'status' => (string) ($model->status ?? 'pending'),
                    ]);
                }
            }

            return;
        }

        if ($model instanceof Complaint) {
            $this->broadcaster->broadcastAdminActivity('complaint_' . $action, [
                'complaint_id' => (int) $model->id,
                'user_id' => (int) ($model->user_id ?? 0),
                'status' => (string) ($model->status ?? 'open'),
            ]);

            return;
        }
    }
}
