<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerConversationController extends Controller
{
    public function index(Request $request): Response
    {
        $conversations = $request->user()->conversationsAsSeller()
            ->with(['buyer:id,name', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->withCount(['messages as unread_count' => fn ($q) => $q->whereNull('read_at')->where('sender_id', '!=', $request->user()->id)])
            ->orderByDesc('last_message_at')
            ->get();

        return Inertia::render('Seller/Messages', ['conversations' => $conversations]);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        abort_unless($conversation->seller_id === $request->user()->id, 403);

        $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $request->user()->id)
            ->update(['read_at' => now()]);

        return Inertia::render('Seller/MessageThread', [
            'conversation' => $conversation->load('buyer:id,name'),
            'messages' => $conversation->messages()->with('sender:id,name')->orderBy('created_at')->get(),
        ]);
    }

    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless($conversation->seller_id === $request->user()->id, 403);

        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $conversation->messages()->create(['sender_id' => $request->user()->id, 'body' => $data['body']]);
        $conversation->update(['last_message_at' => now()]);

        return back();
    }

    /**
     * Seller starts a conversation with a buyer (e.g. from the order details
     * page's "Contact Buyer" button).
     */
    public function start(Request $request, User $buyer): RedirectResponse
    {
        abort_unless($buyer->usertype === 'buyer', 404);

        $conversation = Conversation::firstOrCreate([
            'buyer_id' => $buyer->id,
            'seller_id' => $request->user()->id,
        ], ['last_message_at' => now()]);

        return redirect()->route('seller.messages.show', $conversation);
    }
}
