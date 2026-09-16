<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BuyerOrderConversationController extends Controller
{
    /**
     * Buyer starts a conversation with seller about a specific order item.
     */
    public function start(Request $request, OrderItem $orderItem): RedirectResponse
    {
        $orderItem->load('order', 'product');

        abort_unless($orderItem->order->user_id === $request->user()->id, 403);
        abort_unless($orderItem->product && $orderItem->product->seller_id, 404);

        $conversation = Conversation::firstOrCreate([
            'buyer_id' => $request->user()->id,
            'seller_id' => $orderItem->product->seller_id,
            'order_id' => $orderItem->order_id,
        ], [
            'product_id' => $orderItem->product_id,
            'last_message_at' => now(),
        ]);

        return redirect()->route('buyer.conversations.show', $conversation);
    }
}
