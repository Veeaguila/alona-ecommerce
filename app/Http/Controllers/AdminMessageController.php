<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminMessageController extends Controller
{
    /**
     * Display all buyer-seller conversations.
     */
    public function index(): Response
    {
        $conversations = Conversation::query()
            ->with([
                'buyer:id,name',
                'seller:id,name',
                'messages' => fn ($query) => $query
                    ->latest()
                    ->limit(1),
            ])
            ->withCount([
                'messages as unread_count' => fn ($query) =>
                    $query->whereNull('read_at'),
            ])
            ->orderByDesc('last_message_at')
            ->get();

        return Inertia::render('Admin/Messages', [
            'title' => 'Messages',
            'eyebrow' => 'Admin chat',
            'description' => 'View and respond to buyer and seller conversations from one place.',
            'active' => 'messages',
            'conversations' => $conversations,
            'conversation' => null,
            'messages' => [],
        ]);
    }

    /**
     * Display a specific conversation and its complete message history.
     */
    public function show(
        Request $request,
        Conversation $conversation
    ): Response {
        $admin = $request->user();

        // Load the participants.
        $conversation->load([
            'buyer:id,name',
            'seller:id,name',
        ]);

        // Mark all messages from other users as read.
        $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $admin->id)
            ->update([
                'read_at' => now(),
            ]);

        // Load the complete conversation history in chronological order.
        $messages = $conversation->messages()
            ->with('sender:id,name')
            ->orderBy('created_at')
            ->get();

        // Keep the conversation list available on the same page.
        $conversations = Conversation::query()
            ->with([
                'buyer:id,name',
                'seller:id,name',
                'messages' => fn ($query) => $query
                    ->latest()
                    ->limit(1),
            ])
            ->withCount([
                'messages as unread_count' => fn ($query) =>
                    $query
                        ->whereNull('read_at')
                        ->where('sender_id', '!=', $admin->id),
            ])
            ->orderByDesc('last_message_at')
            ->get();

        return Inertia::render('Admin/Messages', [
            'title' => 'Messages',
            'eyebrow' => 'Admin chat',
            'description' => 'View and respond to buyer and seller conversations from one place.',
            'active' => 'messages',
            'conversations' => $conversations,
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    /**
     * Send an admin reply to a conversation.
     */
    public function store(
        Request $request,
        Conversation $conversation
    ): RedirectResponse {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        $conversation->update([
            'last_message_at' => $message->created_at,
        ]);

        return back()
            ->with('status', 'Message sent successfully.');
    }
}