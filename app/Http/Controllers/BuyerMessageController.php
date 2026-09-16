<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuyerMessageController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $messages = $user->messages()
            ->latest()
            ->get();

        $conversations = $user->conversationsAsBuyer()
            ->with([
                'seller:id,name,store_name',
                'product:id,name,image_path',
                'order:id,order_number,status',
                'messages' => fn ($query) =>
                    $query->latest()->limit(1),
            ])
            ->withCount([
                'messages as unread_count' => fn ($query) =>
                    $query
                        ->whereNull('read_at')
                        ->where('sender_id', '!=', $user->id),
            ])
            ->orderByDesc('last_message_at')
            ->get();

        return Inertia::render('Buyer/Messages', [
            'messages' => $messages,
            'conversations' => $conversations,
            'selected_conversation' => null,
            'conversation_messages' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => [
                'required',
                'string',
                'max:150',
            ],

            'message' => [
                'required',
                'string',
                'max:2000',
            ],
        ]);

        $request->user()
            ->messages()
            ->create($data);

        return back()->with(
            'status',
            'Message sent.'
        );
    }
}
