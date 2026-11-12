<?php

use App\Models\Conversation;
use App\Models\Order;
use App\Models\User;
use App\Services\HubAccess;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('user.{id}', function (User $user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('buyer.{id}', function (User $user, $id) {
    if ((int) $user->id !== (int) $id) {
        return false;
    }

    return in_array($user->usertype, ['buyer', null, ''], true);
});

Broadcast::channel('seller.{id}', function (User $user, $id) {
    return (int) $user->id === (int) $id && $user->usertype === 'seller';
});

Broadcast::channel('admin.global', function (User $user) {
    return $user->usertype === 'admin' && ($user->status === 'approved' || $user->status === null);
});

Broadcast::channel('logistics.global', function (User $user) {
    if (in_array($user->usertype, ['sorting_center', 'admin'], true)) {
        return true;
    }

    try {
        return app(HubAccess::class)->membership($user) !== null;
    } catch (\Throwable $e) {
        return false;
    }
});

Broadcast::channel('logistics.center.{centerId}', function (User $user, $centerId) {
    if ($user->usertype === 'admin') {
        return true;
    }

    $targetId = (int) $centerId;
    if ($targetId <= 0) {
        return false;
    }

    if ((int) ($user->sortingCenter?->id ?? 0) === $targetId) {
        return true;
    }

    try {
        $context = app(HubAccess::class)->context($user);
        $hubCenterId = (int) ($context['center']?->id ?? 0);

        return $hubCenterId === $targetId;
    } catch (\Throwable $e) {
        return false;
    }
});

Broadcast::channel('rider.{id}', function (User $user, $id) {
    return (int) $user->id === (int) $id && $user->usertype === 'rider';
});

Broadcast::channel('riders.center.{centerId}', function (User $user, $centerId) {
    if ($user->usertype === 'admin') {
        return true;
    }

    if ($user->usertype !== 'rider') {
        return false;
    }

    $riderCenterId = (int) ($user->rider?->sorting_center_id ?? 0);

    return $riderCenterId > 0 && $riderCenterId === (int) $centerId;
});

Broadcast::channel('conversation.{conversationId}', function (User $user, $conversationId) {
    $conversation = Conversation::find((int) $conversationId);
    if (! $conversation) {
        return false;
    }

    if ($user->usertype === 'admin' && $conversation->type === 'seller_admin') {
        return true;
    }

    return (int) $conversation->buyer_id === (int) $user->id
        || (int) $conversation->seller_id === (int) $user->id;
});

Broadcast::channel('order.{orderId}', function (User $user, $orderId) {
    $order = Order::with(['items.product', 'items.parcel'])->find((int) $orderId);
    if (! $order) {
        return false;
    }

    if ($user->usertype === 'admin' || $user->usertype === 'sorting_center') {
        return true;
    }

    if ((int) $order->user_id === (int) $user->id) {
        return true;
    }

    foreach ($order->items as $item) {
        $sellerId = (int) ($item->product?->seller_id ?? $item->product?->user_id ?? 0);
        if ($sellerId === (int) $user->id) {
            return true;
        }

        if ($item->parcel) {
            foreach (array_filter([$item->parcel->assigned_rider_id ?? null, $item->parcel->pickup_rider_id ?? null]) as $rId) {
                $rider = \App\Models\Rider::find((int) $rId);
                if ((int) ($rider?->user_id ?? 0) === (int) $user->id) {
                    return true;
                }
            }
        }
    }

    return false;
});
