<?php

namespace App\Services;

use App\Events\RealtimeDomainEvent;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\FlashSale;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturnRequest;
use App\Models\Parcel;
use App\Models\PickupRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SellerPromotion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RealtimeBroadcastService
{
    /**
     * In-request deduplication map to avoid firing identical broadcasts within the same lifecycle.
     *
     * @var array<string, bool>
     */
    protected static array $dispatchedFingerprints = [];

    public static function resetDeduplication(): void
    {
        self::$dispatchedFingerprints = [];
    }

    /**
     * Dispatch a domain event after the active database transaction commits.
     *
     * @param  array<int, string>  $channels
     * @param  array<string, mixed>  $payload
     */
    public function emit(
        string $domain,
        string $action,
        array $channels,
        array $payload = [],
        ?string $dedupeKey = null
    ): void {
        $channels = array_values(array_unique(array_filter($channels)));
        if (empty($channels)) {
            return;
        }

        if ($dedupeKey !== null) {
            $fingerprint = "{$domain}:{$action}:{$dedupeKey}:" . implode(',', $channels);
            if (isset(self::$dispatchedFingerprints[$fingerprint])) {
                return;
            }
            self::$dispatchedFingerprints[$fingerprint] = true;
        }

        $eventId = (string) ($payload['event_id'] ?? Str::uuid());

        DB::afterCommit(function () use ($domain, $action, $channels, $payload, $eventId) {
            try {
                event(new RealtimeDomainEvent($domain, $action, $channels, $payload, $eventId));
            } catch (\Throwable $e) {
                Log::warning('RealtimeBroadcastService emit failed', [
                    'domain' => $domain,
                    'action' => $action,
                    'channels' => $channels,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * Broadcast product & variant stock/price updates to buyers, the owning seller, and super admin.
     */
    public function broadcastStockUpdate(Product $product, ?ProductVariant $variant = null, string $reason = 'updated'): void
    {
        $product->refresh();
        $variantsPayload = $product->variants()
            ->orderBy('id')
            ->get()
            ->map(fn (ProductVariant $v) => [
                'id' => (int) $v->id,
                'product_id' => (int) $v->product_id,
                'color' => $v->color,
                'size' => $v->size,
                'variant_attributes' => $v->variant_attributes,
                'label' => $v->label(),
                'sku' => $v->sku,
                'price' => $v->price !== null ? (float) $v->price : null,
                'stock' => (int) $v->stock,
                'in_stock' => (int) $v->stock > 0,
            ])
            ->values()
            ->all();

        $channels = [
            'catalog.global',
            'admin.global',
        ];

        $sellerId = (int) ($product->seller_id ?? $product->user_id ?? 0);
        if ($sellerId > 0) {
            $channels[] = 'seller.' . $sellerId;
        }

        $payload = [
            'product_id' => (int) $product->id,
            'seller_id' => $sellerId,
            'name' => (string) $product->name,
            'price' => (float) ($product->price ?? 0),
            'stock' => (int) ($product->stock ?? 0),
            'in_stock' => (int) ($product->stock ?? 0) > 0,
            'low_stock' => (int) ($product->stock ?? 0) <= (int) ($product->low_stock_threshold ?? 5),
            'status' => (string) ($product->status ?? 'approved'),
            'variant_id' => $variant ? (int) $variant->id : null,
            'variant_stock' => $variant ? (int) $variant->stock : null,
            'variants' => $variantsPayload,
            'reason' => $reason,
        ];

        $dedupeKey = 'product:' . $product->id . ':stock:' . $product->stock . ':variant:' . ($variant?->id ?? '0') . ':' . ($variant?->stock ?? 'na');
        $this->emit('inventory', 'updated', $channels, $payload, $dedupeKey);
    }

    /**
     * Broadcast Flash Sale or Bundle Deal / Seller Promotion updates.
     */
    public function broadcastPromotionUpdate(string $promotionType, $model, string $action = 'updated'): void
    {
        $channels = ['catalog.global', 'admin.global'];
        $payload = [
            'promotion_type' => $promotionType,
            'action' => $action,
        ];

        if ($model instanceof FlashSale) {
            if ($model->seller_id) {
                $channels[] = 'seller.' . (int) $model->seller_id;
            }
            $payload = array_merge($payload, [
                'flash_sale_id' => (int) $model->id,
                'product_id' => (int) $model->product_id,
                'seller_id' => (int) ($model->seller_id ?? 0),
                'sale_price' => (float) ($model->flash_price ?? $model->sale_price ?? 0),
                'promo_stock' => $model->flash_quantity !== null ? (int) $model->flash_quantity : null,
                'sold_count' => (int) ($model->sold_quantity ?? 0),
                'remaining_stock' => $model->remainingQuantity(),
                'starts_at' => optional($model->starts_at)->toIso8601String(),
                'ends_at' => optional($model->ends_at)->toIso8601String(),
                'ended_at' => optional($model->ended_at)->toIso8601String(),
                'is_active' => $model->ended_at === null,
                'is_live' => $model->isActiveNow(),
            ]);
        } elseif ($model instanceof SellerPromotion) {
            if ($model->seller_id) {
                $channels[] = 'seller.' . (int) $model->seller_id;
            }
            $payload = array_merge($payload, [
                'promotion_id' => (int) $model->id,
                'seller_id' => (int) ($model->seller_id ?? 0),
                'type' => (string) $model->type,
                'title' => (string) $model->title,
                'is_active' => (bool) $model->is_active,
                'starts_at' => optional($model->starts_at)->toIso8601String(),
                'ends_at' => optional($model->ends_at)->toIso8601String(),
            ]);
        }

        $this->emit('promotion', $action, $channels, $payload);
    }

    /**
     * Broadcast order lifecycle updates to the buyer, involved sellers, assigned rider, logistics, and super admin.
     */
    public function broadcastOrderUpdate(Order $order, string $action = 'updated', array $extra = []): void
    {
        $order->loadMissing(['items.product', 'items.parcel']);

        $channels = ['admin.global', 'order.' . (int) $order->id];

        if ($order->user_id) {
            $channels[] = 'buyer.' . (int) $order->user_id;
            $channels[] = 'user.' . (int) $order->user_id;
        }

        $sellerIds = [];
        foreach ($order->items as $item) {
            $sellerId = (int) ($item->product?->seller_id ?? $item->product?->user_id ?? 0);
            if ($sellerId > 0) {
                $sellerIds[$sellerId] = $sellerId;
                $channels[] = 'seller.' . $sellerId;
                $channels[] = 'user.' . $sellerId;
            }
            if ($item->parcel) {
                $channels[] = 'logistics.global';
                $centerId = (int) ($item->parcel->sorting_center_id ?? 0);
                if ($centerId > 0) {
                    $channels[] = 'logistics.center.' . $centerId;
                    $channels[] = 'riders.center.' . $centerId;
                }
                foreach (array_filter([$item->parcel->assigned_rider_id ?? null, $item->parcel->pickup_rider_id ?? null]) as $rId) {
                    $riderUserId = (int) optional(\App\Models\Rider::find((int) $rId))->user_id;
                    if ($riderUserId > 0) {
                        $channels[] = 'rider.' . $riderUserId;
                    }
                }
            }
        }

        $payload = array_merge([
            'order_id' => (int) $order->id,
            'order_number' => (string) ($order->order_number ?? ''),
            'buyer_id' => (int) ($order->user_id ?? 0),
            'seller_ids' => array_values($sellerIds),
            'status' => (string) ($order->status ?? 'pending'),
            'payment_status' => (string) ($order->payment_status ?? 'unpaid'),
            'payment_method' => (string) ($order->payment_method ?? 'cod'),
            'total_amount' => (float) ($order->total ?? $order->total_amount ?? 0),
            'shipping_fee' => (float) ($order->shipping_fee ?? 0),
        ], $extra);

        $this->emit('order', $action, $channels, $payload);
    }

    /**
     * Broadcast order item status changes.
     */
    public function broadcastOrderItemUpdate(OrderItem $item, string $action = 'updated'): void
    {
        $item->loadMissing(['order', 'product']);
        if ($item->order) {
            $this->broadcastOrderUpdate($item->order, $action, [
                'order_item_id' => (int) $item->id,
                'item_status' => (string) ($item->status ?? 'pending'),
                'product_id' => (int) ($item->product_id ?? 0),
                'product_variant_id' => $item->product_variant_id ? (int) $item->product_variant_id : null,
            ]);
        }
    }

    /**
     * Broadcast parcel / delivery updates across Buyer, Seller, Logistics, Rider, and Admin.
     */
    public function broadcastParcelUpdate(Parcel $parcel, string $action = 'updated', array $extra = []): void
    {
        $parcel->loadMissing(['orderItem.order']);

        $channels = [
            'admin.global',
            'logistics.global',
        ];

        $centerIds = array_filter([
            $parcel->sorting_center_id ?? null,
            $parcel->origin_center_id ?? null,
            $parcel->current_center_id ?? null,
            $parcel->destination_center_id ?? null,
        ]);

        foreach ($centerIds as $centerId) {
            $channels[] = 'logistics.center.' . (int) $centerId;
            $channels[] = 'riders.center.' . (int) $centerId;
        }

        if ($parcel->seller_id) {
            $channels[] = 'seller.' . (int) $parcel->seller_id;
        }

        $buyerId = (int) ($parcel->buyer_id ?: $parcel->orderItem?->order?->user_id ?: 0);
        if ($buyerId > 0) {
            $channels[] = 'buyer.' . $buyerId;
        }

        $numericOrderId = (int) ($parcel->orderItem?->order_id ?: (is_numeric($parcel->order_id) ? $parcel->order_id : 0));
        if ($numericOrderId > 0) {
            $channels[] = 'order.' . $numericOrderId;
        }

        foreach (array_filter([$parcel->assigned_rider_id ?? null, $parcel->pickup_rider_id ?? null, $parcel->rider_id ?? null]) as $rId) {
            $riderUser = \App\Models\Rider::find((int) $rId);
            if ($riderUser?->user_id) {
                $channels[] = 'rider.' . (int) $riderUser->user_id;
            }
        }

        $status = (string) ($parcel->status ?? $parcel->current_status ?? '');

        $payload = array_merge([
            'parcel_id' => (int) $parcel->id,
            'order_id' => $parcel->order_id,
            'order_item_id' => $parcel->order_item_id ? (int) $parcel->order_item_id : null,
            'tracking_number' => (string) ($parcel->tracking_number ?: $parcel->order_id ?: ''),
            'status' => $status,
            'current_status' => $status,
            'assigned_rider_id' => $parcel->assigned_rider_id ? (int) $parcel->assigned_rider_id : null,
            'pickup_rider_id' => $parcel->pickup_rider_id ? (int) $parcel->pickup_rider_id : null,
            'seller_id' => $parcel->seller_id ? (int) $parcel->seller_id : null,
            'buyer_id' => $buyerId > 0 ? $buyerId : null,
            'sorting_center_id' => $parcel->sorting_center_id ? (int) $parcel->sorting_center_id : null,
            'is_pickup_taken' => $parcel->pickup_rider_id !== null,
        ], $extra);

        $this->emit('parcel', $action, $channels, $payload);
    }

    /**
     * Broadcast pickup request updates across Seller, Logistics, Riders in the sorting center, and Admin.
     */
    public function broadcastPickupUpdate(PickupRequest $pickup, string $action = 'updated', array $extra = []): void
    {
        $pickup->loadMissing(['parcel']);
        $parcel = $pickup->parcel;

        $channels = [
            'admin.global',
            'logistics.global',
        ];

        $centerId = (int) ($pickup->sorting_center_id ?: $parcel?->sorting_center_id ?: 0);
        if ($centerId > 0) {
            $channels[] = 'logistics.center.' . $centerId;
            $channels[] = 'riders.center.' . $centerId;
        }

        $sellerId = (int) ($pickup->seller_id ?: $parcel?->seller_id ?: 0);
        if ($sellerId > 0) {
            $channels[] = 'seller.' . $sellerId;
        }

        $riderId = (int) ($pickup->rider_id ?: $parcel?->pickup_rider_id ?: 0);
        if ($riderId > 0) {
            $rider = \App\Models\Rider::find($riderId);
            if ($rider?->user_id) {
                $channels[] = 'rider.' . (int) $rider->user_id;
            }
        }

        if ($parcel?->buyer_id) {
            $channels[] = 'buyer.' . (int) $parcel->buyer_id;
        }

        $payload = array_merge([
            'pickup_request_id' => (int) $pickup->id,
            'parcel_id' => $pickup->parcel_id ? (int) $pickup->parcel_id : null,
            'seller_id' => $sellerId > 0 ? $sellerId : null,
            'sorting_center_id' => $centerId > 0 ? $centerId : null,
            'rider_id' => $riderId > 0 ? $riderId : null,
            'status' => (string) ($pickup->status ?? ''),
            'is_exclusive_taken' => $riderId > 0 || in_array($pickup->status, ['assigned', 'accepted', 'picked_up', 'completed'], true),
        ], $extra);

        $this->emit('pickup', $action, $channels, $payload);
    }

    /**
     * Broadcast return & refund updates to Buyer, Seller, Logistics, and Super Admin.
     */
    public function broadcastReturnUpdate(OrderReturnRequest $returnRequest, string $action = 'updated', array $extra = []): void
    {
        $channels = [
            'admin.global',
            'logistics.global',
        ];

        if ($returnRequest->buyer_id) {
            $channels[] = 'buyer.' . (int) $returnRequest->buyer_id;
        }
        if ($returnRequest->seller_id) {
            $channels[] = 'seller.' . (int) $returnRequest->seller_id;
        }
        if ($returnRequest->order_id) {
            $channels[] = 'order.' . (int) $returnRequest->order_id;
        }

        $payload = array_merge([
            'return_request_id' => (int) $returnRequest->id,
            'order_id' => (int) ($returnRequest->order_id ?? 0),
            'order_item_id' => (int) ($returnRequest->order_item_id ?? 0),
            'buyer_id' => (int) ($returnRequest->buyer_id ?? 0),
            'seller_id' => (int) ($returnRequest->seller_id ?? 0),
            'status' => (string) ($returnRequest->status ?? ''),
            'refund_amount' => (float) ($returnRequest->refund_amount ?? 0),
            'restocked' => (bool) ($returnRequest->restocked ?? false),
        ], $extra);

        $this->emit('return', $action, $channels, $payload);
    }

    /**
     * Broadcast financial & commission ledger updates (preserving 90/10 product and 70/20/10 shipping splits).
     *
     * @param  array<string, mixed>  $context
     */
    public function broadcastFinancialUpdate(?int $orderId, string $action = 'updated', array $context = []): void
    {
        $channels = ['admin.global', 'logistics.global'];

        if (! empty($context['seller_id'])) {
            $channels[] = 'seller.' . (int) $context['seller_id'];
        }
        if (! empty($context['buyer_id'])) {
            $channels[] = 'buyer.' . (int) $context['buyer_id'];
        }
        if (! empty($context['rider_user_id'])) {
            $channels[] = 'rider.' . (int) $context['rider_user_id'];
        }
        if (! empty($context['sorting_center_id'])) {
            $channels[] = 'logistics.center.' . (int) $context['sorting_center_id'];
        }

        if ($orderId) {
            $order = Order::with(['items.product', 'items.parcel'])->find($orderId);
            if ($order) {
                if ($order->user_id) {
                    $channels[] = 'buyer.' . (int) $order->user_id;
                }
                foreach ($order->items as $item) {
                    $sellerId = (int) ($item->product?->seller_id ?? $item->product?->user_id ?? 0);
                    if ($sellerId > 0) {
                        $channels[] = 'seller.' . $sellerId;
                    }
                    if ($item->parcel) {
                        if ($item->parcel->sorting_center_id) {
                            $channels[] = 'logistics.center.' . (int) $item->parcel->sorting_center_id;
                        }
                        foreach (array_filter([$item->parcel->assigned_rider_id ?? null, $item->parcel->pickup_rider_id ?? null]) as $rId) {
                            $riderUserId = (int) optional(\App\Models\Rider::find((int) $rId))->user_id;
                            if ($riderUserId > 0) {
                                $channels[] = 'rider.' . $riderUserId;
                            }
                        }
                    }
                }
            }
        }

        $payload = array_merge([
            'order_id' => $orderId,
            'commission_version' => 'alona-90-10-70-20-10-v1',
            'product_split' => ['seller' => 0.90, 'admin' => 0.10],
            'shipping_split' => ['rider' => 0.70, 'logistics' => 0.20, 'admin' => 0.10],
        ], $context);

        Log::info('Realtime financial ledger event emitted', [
            'order_id' => $orderId,
            'action' => $action,
            'channels' => $channels,
        ]);

        $this->emit('finance', $action, $channels, $payload);
    }

    /**
     * Broadcast instant chat messages over authorized private channels.
     */
    public function broadcastMessage(ConversationMessage $message): void
    {
        $conversation = Conversation::find($message->conversation_id);
        if (! $conversation) {
            return;
        }

        $channels = [
            'conversation.' . (int) $conversation->id,
        ];

        if ($conversation->buyer_id) {
            $channels[] = 'buyer.' . (int) $conversation->buyer_id;
            $channels[] = 'user.' . (int) $conversation->buyer_id;
        }
        if ($conversation->seller_id) {
            $channels[] = 'seller.' . (int) $conversation->seller_id;
            $channels[] = 'user.' . (int) $conversation->seller_id;
        }
        if ($conversation->type === 'seller_admin') {
            $channels[] = 'admin.global';
        }

        $message->loadMissing([
            'sender:id,name,usertype,avatar',
            'order.items.product:id,name,image_path,price',
        ]);

        $payload = [
            'conversation_id' => (int) $conversation->id,
            'conversation_type' => (string) ($conversation->type ?? 'buyer_seller'),
            'message' => [
                'id' => (int) $message->id,
                'conversation_id' => (int) $message->conversation_id,
                'sender_id' => (int) $message->sender_id,
                'sender_type' => (string) ($message->sender?->usertype ?? $message->sender_type ?? 'user'),
                'sender_name' => (string) ($message->sender?->name ?? 'User'),
                'sender' => $message->sender ? [
                    'id' => (int) $message->sender->id,
                    'name' => (string) $message->sender->name,
                    'usertype' => (string) ($message->sender->usertype ?? 'user'),
                    'avatar' => $message->sender->avatar ?? null,
                ] : null,
                'body' => (string) $message->body,
                'attachment_path' => $message->attachment_path,
                'attachment_url' => $message->attachment_url ?? null,
                'attachment_name' => $message->attachment_name,
                'attachment_type' => $message->attachment_type,
                'attachment_size' => $message->attachment_size,
                'order_id' => $message->order_id ? (int) $message->order_id : null,
                'order' => $message->order ? [
                    'id' => (int) $message->order->id,
                    'order_number' => (string) $message->order->order_number,
                    'status' => (string) $message->order->status,
                    'total' => (float) ($message->order->total ?? 0),
                    'items' => $message->order->items ? $message->order->items->map(fn ($item) => [
                        'id' => (int) $item->id,
                        'product_name' => (string) ($item->product_name ?? $item->product?->name ?? 'Item'),
                        'quantity' => (int) $item->quantity,
                        'price' => (float) $item->price,
                        'product' => $item->product ? [
                            'id' => (int) $item->product->id,
                            'name' => (string) $item->product->name,
                            'image_path' => $item->product->image_path,
                        ] : null,
                    ])->values()->all() : [],
                ] : null,
                'read_at' => optional($message->read_at)->toIso8601String(),
                'created_at' => optional($message->created_at)->toIso8601String() ?: now()->toIso8601String(),
            ],
        ];

        $this->emit('chat', 'message_sent', $channels, $payload, 'msg:' . $message->id);
    }

    /**
     * Record that an authenticated user currently has their account open & active.
     *
     * @return array{user_id: int, is_online: bool, presence_label: string, last_seen_at: string|null}
     */
    public function touchUserPresence(\App\Models\User $user): array
    {
        $userId = (int) $user->id;
        $cacheKey = "realtime:presence:user:{$userId}";
        $nowTs = now()->timestamp;
        $nowIso = now()->toIso8601String();

        $previous = \Illuminate\Support\Facades\Cache::get($cacheKey);
        $wasOnline = is_array($previous)
            && ! empty($previous['online'])
            && isset($previous['last_seen_ts'])
            && ($nowTs - (int) $previous['last_seen_ts']) <= 18;

        \Illuminate\Support\Facades\Cache::put($cacheKey, [
            'user_id' => $userId,
            'usertype' => (string) ($user->usertype ?: 'buyer'),
            'online' => true,
            'last_seen_ts' => $nowTs,
            'last_seen_at' => $nowIso,
        ], now()->addHours(24));

        $presence = [
            'user_id' => $userId,
            'is_online' => true,
            'presence_label' => 'Active now',
            'last_seen_at' => $nowIso,
        ];

        if (! $wasOnline) {
            $this->emit('presence', 'updated', ['catalog.global'], $presence, "presence:online:{$userId}:{$nowTs}");
        }

        return $presence;
    }

    /**
     * Mark a user as offline immediately (e.g. on logout or tab close).
     *
     * @return array{user_id: int, is_online: bool, presence_label: string, last_seen_at: string|null}
     */
    public function markUserOffline(int $userId, ?string $usertype = null): array
    {
        $cacheKey = "realtime:presence:user:{$userId}";
        $nowTs = now()->timestamp;
        $nowIso = now()->toIso8601String();
        $previous = \Illuminate\Support\Facades\Cache::get($cacheKey);

        \Illuminate\Support\Facades\Cache::put($cacheKey, [
            'user_id' => $userId,
            'usertype' => $usertype ?? ($previous['usertype'] ?? 'user'),
            'online' => false,
            'last_seen_ts' => $nowTs,
            'last_seen_at' => $nowIso,
        ], now()->addHours(24));

        $presence = [
            'user_id' => $userId,
            'is_online' => false,
            'presence_label' => 'Offline',
            'last_seen_at' => $nowIso,
        ];

        $this->emit('presence', 'updated', ['catalog.global'], $presence, "presence:offline:{$userId}:{$nowTs}");

        return $presence;
    }

    /**
     * Get authoritative online/active status for a user.
     * A user is ONLY online when they actively have their account open (heartbeat within the last 35 seconds).
     *
     * @return array{user_id: int, is_online: bool, presence_label: string, last_seen_at: string|null}
     */
    public function getUserPresence(int $userId): array
    {
        if ($userId <= 0) {
            return [
                'user_id' => 0,
                'is_online' => false,
                'presence_label' => 'Offline',
                'last_seen_at' => null,
            ];
        }

        $entry = \Illuminate\Support\Facades\Cache::get("realtime:presence:user:{$userId}");
        $nowTs = now()->timestamp;

        if (! is_array($entry) || empty($entry['last_seen_ts'])) {
            return [
                'user_id' => $userId,
                'is_online' => false,
                'presence_label' => 'Offline',
                'last_seen_at' => null,
            ];
        }

        $lastSeenTs = (int) $entry['last_seen_ts'];
        $diffSeconds = max(0, $nowTs - $lastSeenTs);
        $isOnline = ! empty($entry['online']) && $diffSeconds <= 18;
        $label = $isOnline ? 'Active now' : 'Offline';

        return [
            'user_id' => $userId,
            'is_online' => $isOnline,
            'presence_label' => $label,
            'last_seen_at' => $entry['last_seen_at'] ?? null,
        ];
    }

    /**
     * Broadcast a notification to the specific role/user private channel.
     *
     * @param  array<string, mixed>  $notification
     */
    public function broadcastNotification(string $role, ?int $userId, array $notification): void
    {
        $channels = [];
        if ($role === 'admin') {
            $channels[] = 'admin.global';
            if ($userId) {
                $channels[] = 'user.' . $userId;
            }
        } elseif ($userId) {
            $channels[] = "{$role}.{$userId}";
            $channels[] = "user.{$userId}";
        }

        if (empty($channels)) {
            return;
        }

        $this->emit('notification', 'created', $channels, [
            'role' => $role,
            'user_id' => $userId,
            'notification' => $notification,
        ]);
    }

    /**
     * Broadcast administrative events strictly to the `admin.global` channel.
     *
     * @param  array<string, mixed>  $summary
     */
    public function broadcastAdminActivity(string $category, array $summary = []): void
    {
        $this->emit('admin', $category, ['admin.global'], array_merge([
            'category' => $category,
        ], $summary));
    }
}
