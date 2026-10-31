<?php

namespace App\Services;

use App\Models\{Conversation, Order};
use Illuminate\Validation\ValidationException;

class ConversationOrderAccess
{
    public function scope($query, Conversation $conversation)
    {
        return $query->whereHas('items.product', fn ($items) => $items->where('seller_id', $conversation->seller_id))
            ->when($conversation->buyer_id !== null, fn ($orders) => $orders->where('user_id', $conversation->buyer_id));
    }

    public function assertCanShare(Conversation $conversation, ?int $orderId): void
    {
        if ($orderId !== null && !$this->scope(Order::whereKey($orderId), $conversation)->exists()) {
            throw ValidationException::withMessages(['order_id' => 'Choose an order belonging to the participants in this conversation.']);
        }
    }
}
