<?php

namespace App\Http\Controllers;

use App\Broadcasting\AlonaReverbBroadcaster;
use App\Models\AdminNotification;
use App\Models\BuyerNotification;
use App\Models\Cart;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\FlashSale;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturnRequest;
use App\Models\Parcel;
use App\Models\PickupRequest;
use App\Models\Product;
use App\Models\RiderNotification;
use App\Models\SellerNotification;
use App\Models\SellerPromotion;
use App\Models\User;
use App\Services\RealtimeBroadcastService;
use App\Services\RealtimeEventStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RealtimeSyncController extends Controller
{
    public function __construct(
        protected RealtimeEventStore $store,
        protected RealtimeBroadcastService $broadcasterService
    ) {
    }

    /**
     * Authenticate a private or presence WebSocket channel subscription.
     */
    public function authenticate(Request $request): JsonResponse
    {
        try {
            $driver = Broadcast::driver('reverb');
            if ($driver instanceof AlonaReverbBroadcaster) {
                $response = $driver->auth($request);

                return response()->json($response);
            }

            $result = Broadcast::auth($request);

            return response()->json($result);
        } catch (AccessDeniedHttpException $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'This action is unauthorized.',
                'authorized' => false,
            ], 403);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Channel authorization failed.',
                'authorized' => false,
            ], 403);
        }
    }

    /**
     * Synchronize real-time events since cursor and return authoritative server timestamp + live role counters.
     */
    public function sync(Request $request): JsonResponse
    {
        $this->reconcilePromotions();

        /** @var AlonaReverbBroadcaster $driver */
        $driver = Broadcast::driver('reverb');
        $user = $driver instanceof AlonaReverbBroadcaster
            ? $driver->resolveAuthenticatedUser($request)
            : $request->user();

        if ($user) {
            $request->setUserResolver(fn () => $user);
            if ($request->input('presence') === 'offline') {
                $this->broadcasterService->markUserOffline((int) $user->id, (string) $user->usertype);
            } else {
                $this->broadcasterService->touchUserPresence($user);
            }
        }

        $presenceUserIds = $request->input('presence_user_ids', []);
        if (is_string($presenceUserIds)) {
            $presenceUserIds = array_filter(array_map('intval', explode(',', $presenceUserIds)));
        } elseif (is_array($presenceUserIds)) {
            $presenceUserIds = array_filter(array_map('intval', $presenceUserIds));
        } else {
            $presenceUserIds = [];
        }
        $presenceMap = [];
        foreach (array_slice(array_values(array_unique($presenceUserIds)), 0, 50) as $targetUserId) {
            $presenceMap[(int) $targetUserId] = $this->broadcasterService->getUserPresence((int) $targetUserId);
        }

        $requestedChannels = $request->input('channels', []);
        if (is_string($requestedChannels)) {
            $requestedChannels = array_filter(array_map('trim', explode(',', $requestedChannels)));
        }
        if (! is_array($requestedChannels)) {
            $requestedChannels = [];
        }

        // Always include catalog.global for public stock/promotion updates, plus default role channels if authenticated
        $defaultChannels = ['catalog.global'];
        if ($user) {
            $defaultChannels[] = 'user.' . (int) $user->id;
            if (in_array($user->usertype, ['buyer', null, ''], true)) {
                $defaultChannels[] = 'buyer.' . (int) $user->id;
            } elseif ($user->usertype === 'seller') {
                $defaultChannels[] = 'seller.' . (int) $user->id;
            } elseif ($user->usertype === 'admin') {
                $defaultChannels[] = 'admin.global';
            } elseif ($user->usertype === 'sorting_center') {
                $defaultChannels[] = 'logistics.global';
                if ($user->sortingCenter?->id) {
                    $defaultChannels[] = 'logistics.center.' . (int) $user->sortingCenter->id;
                }
            } elseif ($user->usertype === 'rider') {
                $defaultChannels[] = 'rider.' . (int) $user->id;
                if ($user->rider?->sorting_center_id) {
                    $defaultChannels[] = 'riders.center.' . (int) $user->rider->sorting_center_id;
                }
            }
        }

        $allCandidates = array_values(array_unique(array_merge($defaultChannels, $requestedChannels)));
        $authorizedChannels = [];
        $unauthorizedChannels = [];

        foreach ($allCandidates as $channel) {
            $normalized = $this->store->normalizeChannel((string) $channel);
            if ($normalized === '') {
                continue;
            }

            $canAccess = $driver instanceof AlonaReverbBroadcaster
                ? $driver->canUserAccessChannel($request, $normalized)
                : ($normalized === 'catalog.global');

            if ($canAccess) {
                $authorizedChannels[] = $normalized;
            } else {
                $unauthorizedChannels[] = $normalized;
            }
        }

        $cursor = max(0, (int) $request->input('cursor', 0));
        $delta = $this->store->since($authorizedChannels, $cursor);

        return response()->json([
            'ok' => true,
            'server_time' => now()->toIso8601String(),
            'server_time_ms' => (int) round(microtime(true) * 1000),
            'cursor' => $delta['cursor'],
            'authorized_channels' => $authorizedChannels,
            'unauthorized_channels' => $unauthorizedChannels,
            'events' => $delta['events'],
            'versions' => $delta['versions'],
            'state' => $this->buildRoleState($user),
            'presence' => $presenceMap,
        ]);
    }

    /**
     * Mark the authenticated user as offline immediately (e.g. on tab close or logout).
     */
    public function offline(Request $request): JsonResponse
    {
        /** @var AlonaReverbBroadcaster $driver */
        $driver = Broadcast::driver('reverb');
        $user = $driver instanceof AlonaReverbBroadcaster
            ? $driver->resolveAuthenticatedUser($request)
            : $request->user();

        if ($user) {
            $presence = $this->broadcasterService->markUserOffline((int) $user->id, (string) $user->usertype);

            return response()->json([
                'ok' => true,
                'presence' => $presence,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Return authoritative real-time catalog state (stock, variants, flash sales, bundle deals) for Buyer/Guest pages.
     */
    public function catalogState(Request $request): JsonResponse
    {
        $this->reconcilePromotions();

        $rawIds = $request->input('product_ids', []);
        if (is_string($rawIds)) {
            $rawIds = array_filter(array_map('intval', explode(',', $rawIds)));
        } elseif (is_array($rawIds)) {
            $rawIds = array_filter(array_map('intval', $rawIds));
        } else {
            $rawIds = [];
        }

        $productIds = array_slice(array_values(array_unique($rawIds)), 0, 60);
        $now = now();

        $productsData = [];
        if (! empty($productIds) && Schema::hasTable('products')) {
            $products = Product::with([
                'variants' => fn ($q) => $q->orderBy('id'),
                'flashSales',
            ])->whereIn('id', $productIds)->get();

            foreach ($products as $product) {
                $activeFlash = $product->relationLoaded('flashSales')
                    ? $product->flashSales
                        ->filter(fn (FlashSale $fs) => $fs->isActiveNow())
                        ->sortBy('flash_price')
                        ->first()
                    : null;
                $isFlashLive = $activeFlash !== null;
                $remainingPromoStock = $isFlashLive ? $activeFlash->remainingQuantity() : null;

                $effectivePrice = $isFlashLive
                    ? (float) $activeFlash->flash_price
                    : (float) ($product->discount_price && (float) $product->discount_price < (float) $product->price
                        ? $product->discount_price
                        : $product->price);

                $sellerId = (int) ($product->seller_id ?? $product->user_id ?? 0);
                $bundleDeals = [];
                if (Schema::hasTable('seller_promotions') && $sellerId > 0) {
                    $bundleDeals = SellerPromotion::query()
                        ->where('seller_id', $sellerId)
                        ->where('type', 'bundle')
                        ->where('is_active', true)
                        ->whereNull('ended_at')
                        ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                        ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now))
                        ->get()
                        ->filter(fn (SellerPromotion $promo) => $promo->appliesToBundleProduct((int) $product->id))
                        ->map(fn (SellerPromotion $promo) => [
                            'id' => (int) $promo->id,
                            'seller_id' => (int) $promo->seller_id,
                            'name' => (string) ($promo->name ?? $promo->title ?? 'Bundle Deal'),
                            'title' => (string) ($promo->name ?? $promo->title ?? 'Bundle Deal'),
                            'min_quantity' => (int) ($promo->min_quantity ?? 2),
                            'discount_type' => $promo->discount_type,
                            'discount_value' => $promo->discount_value !== null ? (float) $promo->discount_value : null,
                            'starts_at' => optional($promo->starts_at)->toIso8601String(),
                            'ends_at' => optional($promo->ends_at)->toIso8601String(),
                        ])
                        ->values()
                        ->all();
                }

                $productsData[(int) $product->id] = [
                    'id' => (int) $product->id,
                    'stock' => (int) ($product->stock ?? 0),
                    'in_stock' => (int) ($product->stock ?? 0) > 0,
                    'price' => (float) ($product->price ?? 0),
                    'effective_price' => $effectivePrice,
                    'status' => (string) ($product->status ?? 'approved'),
                    'variants' => $product->variants->map(fn ($v) => [
                        'id' => (int) $v->id,
                        'color' => $v->color,
                        'size' => $v->size,
                        'variant_attributes' => $v->variant_attributes,
                        'label' => $v->label(),
                        'sku' => $v->sku,
                        'price' => $v->price !== null ? (float) $v->price : null,
                        'stock' => (int) $v->stock,
                        'in_stock' => (int) $v->stock > 0,
                    ])->values()->all(),
                    'flash_sale' => $isFlashLive ? [
                        'id' => (int) $activeFlash->id,
                        'sale_price' => (float) $activeFlash->flash_price,
                        'promo_stock' => (int) $activeFlash->flash_quantity,
                        'sold_count' => (int) $activeFlash->sold_quantity,
                        'remaining_stock' => $remainingPromoStock,
                        'starts_at' => optional($activeFlash->starts_at)->toIso8601String(),
                        'ends_at' => optional($activeFlash->ends_at)->toIso8601String(),
                        'is_live' => true,
                    ] : null,
                    'bundle_deals' => $bundleDeals,
                ];
            }
        }

        $liveFlashSales = [];
        if (Schema::hasTable('flash_sales')) {
            $liveFlashSales = FlashSale::query()
                ->whereNull('ended_at')
                ->where('starts_at', '<=', $now)
                ->where('ends_at', '>', $now)
                ->with('product:id,name,price,stock,image_path')
                ->orderBy('ends_at')
                ->limit(24)
                ->get()
                ->filter(fn (FlashSale $fs) => $fs->isActiveNow())
                ->map(fn (FlashSale $fs) => [
                    'id' => (int) $fs->id,
                    'product_id' => (int) $fs->product_id,
                    'sale_price' => (float) $fs->flash_price,
                    'original_price' => (float) ($fs->product?->price ?? 0),
                    'promo_stock' => (int) $fs->flash_quantity,
                    'sold_count' => (int) $fs->sold_quantity,
                    'remaining_stock' => $fs->remainingQuantity(),
                    'product_stock' => (int) ($fs->product?->stock ?? 0),
                    'starts_at' => optional($fs->starts_at)->toIso8601String(),
                    'ends_at' => optional($fs->ends_at)->toIso8601String(),
                ])
                ->values()
                ->all();
        }

        return response()->json([
            'ok' => true,
            'server_time' => $now->toIso8601String(),
            'server_time_ms' => (int) round(microtime(true) * 1000),
            'products' => $productsData,
            'live_flash_sales' => $liveFlashSales,
        ]);
    }

    /**
     * Automatically expire ended Flash Sales & Seller Promotions and broadcast changes.
     */
    protected function reconcilePromotions(): void
    {
        try {
            $now = now();

            if (Schema::hasTable('flash_sales')) {
                $expiringSales = FlashSale::query()
                    ->whereNull('ended_at')
                    ->whereNotNull('ends_at')
                    ->where('ends_at', '<=', $now)
                    ->limit(25)
                    ->get();

                foreach ($expiringSales as $sale) {
                    $sale->ended_at = $now;
                    $sale->save();
                    $this->broadcasterService->broadcastPromotionUpdate('flash_sale', $sale, 'expired');
                }
            }

            if (Schema::hasTable('seller_promotions')) {
                $expiringPromos = SellerPromotion::query()
                    ->where('is_active', true)
                    ->whereNotNull('ends_at')
                    ->where('ends_at', '<=', $now)
                    ->limit(25)
                    ->get();

                foreach ($expiringPromos as $promo) {
                    $promo->is_active = false;
                    $promo->ended_at = $now;
                    $promo->save();
                    $this->broadcasterService->broadcastPromotionUpdate((string) ($promo->type ?: 'bundle'), $promo, 'expired');
                }
            }
        } catch (\Throwable $e) {
            // Non-blocking promotion reconciliation
        }
    }

    /**
     * Build lightweight authoritative badge/counter state for the current user's role.
     *
     * @return array<string, mixed>
     */
    protected function buildRoleState(?User $user): array
    {
        if (! $user) {
            return ['authenticated' => false];
        }

        $role = (string) ($user->usertype ?: 'buyer');
        $state = [
            'authenticated' => true,
            'user_id' => (int) $user->id,
            'role' => $role,
        ];

        try {
            if ($role === 'buyer') {
                $cartCount = Schema::hasTable('carts')
                    ? (int) Cart::where('user_id', $user->id)->sum('quantity')
                    : 0;
                $unreadNotifications = Schema::hasTable('buyer_notifications')
                    ? (int) BuyerNotification::where('user_id', $user->id)->whereNull('read_at')->count()
                    : 0;
                $unreadMessages = Schema::hasTable('conversation_messages') && Schema::hasTable('conversations')
                    ? (int) ConversationMessage::whereHas('conversation', fn ($q) => $q->where('buyer_id', $user->id))
                        ->where('sender_id', '!=', $user->id)
                        ->whereNull('read_at')
                        ->count()
                    : 0;

                $state['buyer'] = [
                    'cart_count' => $cartCount,
                    'unread_notifications' => $unreadNotifications,
                    'unread_messages' => $unreadMessages,
                ];
            } elseif ($role === 'seller') {
                $pendingOrders = Schema::hasTable('order_items') && Schema::hasTable('products')
                    ? (int) OrderItem::whereHas('product', fn ($q) => $q->where('seller_id', $user->id))
                        ->whereIn('status', ['pending', 'processing', 'packed'])
                        ->distinct('order_id')
                        ->count('order_id')
                    : 0;
                $unreadNotifications = Schema::hasTable('seller_notifications')
                    ? (int) SellerNotification::where('user_id', $user->id)->whereNull('read_at')->count()
                    : 0;
                $unreadMessages = Schema::hasTable('conversation_messages') && Schema::hasTable('conversations')
                    ? (int) ConversationMessage::whereHas('conversation', fn ($q) => $q->where('seller_id', $user->id))
                        ->where('sender_id', '!=', $user->id)
                        ->whereNull('read_at')
                        ->count()
                    : 0;
                $pendingReturns = Schema::hasTable('order_return_requests')
                    ? (int) OrderReturnRequest::where('seller_id', $user->id)
                        ->whereIn('status', ['pending', 'approved', 'in_dispute', 'item_returned', 'requested', 'approved_awaiting_return', 'return_in_transit'])
                        ->count()
                    : 0;

                $state['seller'] = [
                    'pending_orders' => $pendingOrders,
                    'unread_notifications' => $unreadNotifications,
                    'unread_messages' => $unreadMessages,
                    'pending_returns' => $pendingReturns,
                ];
            } elseif ($role === 'admin') {
                $unreadNotifications = Schema::hasTable('admin_notifications')
                    ? (int) AdminNotification::whereNull('read_at')->count()
                    : 0;
                $pendingSellers = Schema::hasTable('users')
                    ? (int) User::where('usertype', 'seller')->where('status', 'pending')->count()
                    : 0;
                $totalOrders = Schema::hasTable('orders')
                    ? (int) Order::count()
                    : 0;
                $disputedReturns = Schema::hasTable('order_return_requests')
                    ? (int) OrderReturnRequest::whereIn('status', ['in_dispute', 'escalated_to_admin'])->count()
                    : 0;

                $state['admin'] = [
                    'unread_notifications' => $unreadNotifications,
                    'pending_sellers' => $pendingSellers,
                    'total_orders' => $totalOrders,
                    'disputed_returns' => $disputedReturns,
                ];
            } elseif ($role === 'sorting_center') {
                $centerId = (int) ($user->sortingCenter?->id ?? 0);
                $pendingPickups = Schema::hasTable('pickup_requests')
                    ? (int) PickupRequest::when($centerId > 0, fn ($q) => $q->where('sorting_center_id', $centerId))
                        ->whereIn('status', ['pending', 'confirmed', 'Broadcasted', 'assigned', 'accepted'])
                        ->count()
                    : 0;
                $activeParcels = Schema::hasTable('parcels')
                    ? (int) Parcel::when($centerId > 0, fn ($q) => $q->where('sorting_center_id', $centerId))
                        ->whereNotIn('status', ['DELIVERED', 'FAILED_RETURNED', 'delivered', 'completed', 'cancelled'])
                        ->count()
                    : 0;

                $state['logistics'] = [
                    'sorting_center_id' => $centerId,
                    'pending_pickups' => $pendingPickups,
                    'active_parcels' => $activeParcels,
                ];
            } elseif ($role === 'rider') {
                $rider = $user->rider;
                $centerId = (int) ($rider?->sorting_center_id ?? 0);
                $availablePickups = Schema::hasTable('parcels') && $centerId > 0
                    ? (int) Parcel::where('sorting_center_id', $centerId)
                        ->whereNull('pickup_rider_id')
                        ->whereIn('status', ['PLACED', 'PREPARING'])
                        ->count()
                    : 0;
                $unreadNotifications = Schema::hasTable('rider_notifications')
                    ? (int) RiderNotification::where('user_id', $user->id)->whereNull('read_at')->count()
                    : 0;

                $state['rider'] = [
                    'rider_id' => $rider ? (int) $rider->id : null,
                    'sorting_center_id' => $centerId,
                    'available_pickups' => $availablePickups,
                    'unread_notifications' => $unreadNotifications,
                ];
            }
        } catch (\Throwable $e) {
            // Ignore counter query errors during schema setup
        }

        return $state;
    }
}
