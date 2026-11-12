<?php

namespace App\Http\Controllers;

use App\Models\BuyerNotification;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\SellerNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminMessageController extends Controller
{
    /**
     * Display all conversations (both customer threads and seller support desk).
     */
    public function index(Request $request): Response
    {
        $admin = $request->user();

        $conversations = Conversation::query()
            ->with([
                'buyer:id,name,email,avatar',
                'seller:id,name,store_name,email,avatar',
                'admin:id,name,email,avatar',
                'order:id,order_number,status,total',
                'messages' => fn ($query) => $query->latest()->limit(1),
            ])
            ->withCount([
                'messages as unread_count' => fn ($query) => $query
                    ->whereNull('read_at')
                    ->where('sender_id', '!=', $admin->id),
            ])
            ->orderByDesc('last_message_at')
            ->get();

        return Inertia::render('Admin/Messages', [
            'title' => 'Messages & Support Desk',
            'eyebrow' => 'Admin Chat Center',
            'description' => 'View and respond to customer conversations and seller support tickets from one place.',
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

        // Load the participants and order
        $conversation->load([
            'buyer:id,name,email,avatar',
            'seller:id,name,store_name,email,avatar',
            'admin:id,name,email,avatar',
            'order' => fn ($q) => $q->with([
                'items.product:id,name,image_path,price',
                'items.variant:id,title,price',
                'items.parcel:id,order_item_id,tracking_number,status,carrier_name',
            ]),
        ]);

        // Mark all messages from other users as read
        $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $admin->id)
            ->update([
                'read_at' => now(),
            ]);

        // Load complete message history with senders, attachments, and order snippets
        $messages = $conversation->messages()
            ->with([
                'sender:id,name,usertype,avatar',
                'order' => fn ($q) => $q->with([
                    'items.product:id,name,image_path,price',
                    'items.variant:id,title,price',
                    'items.parcel:id,order_item_id,tracking_number,status,carrier_name',
                ]),
            ])
            ->orderBy('created_at')
            ->get();

        // Conversations list for the left panel
        $conversations = Conversation::query()
            ->with([
                'buyer:id,name,email,avatar',
                'seller:id,name,store_name,email,avatar',
                'admin:id,name,email,avatar',
                'order:id,order_number,status,total',
                'messages' => fn ($query) => $query->latest()->limit(1),
            ])
            ->withCount([
                'messages as unread_count' => fn ($query) => $query
                    ->whereNull('read_at')
                    ->where('sender_id', '!=', $admin->id),
            ])
            ->orderByDesc('last_message_at')
            ->get();

        return Inertia::render('Admin/Messages', [
            'title' => 'Messages & Support Desk',
            'eyebrow' => 'Admin Chat Center',
            'description' => 'View and respond to customer conversations and seller support tickets from one place.',
            'active' => 'messages',
            'conversations' => $conversations,
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    /**
     * Send an admin reply to a conversation, supporting media attachments (SELLER-42) and order snippets (SELLER-43).
     */
    public function store(
        Request $request,
        Conversation $conversation
    ): RedirectResponse {
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000', 'required_without_all:attachment,order_id'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,txt,zip'],
            'order_id' => ['nullable', 'exists:orders,id'],
        ]);

        $attachmentPath = null;
        $attachmentName = null;
        $attachmentType = null;
        $attachmentSize = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentName = $file->getClientOriginalName();
            $attachmentSize = $file->getSize();
            $mime = $file->getMimeType();
            $attachmentType = str_starts_with($mime, 'image/') ? 'image' : 'file';
            $attachmentPath = $file->store('chat_attachments', 'public');
        }

        $bodyText = $data['body'] ?? '';
        if (trim($bodyText) === '') {
            if ($attachmentName) {
                $bodyText = "Sent an attachment: {$attachmentName}";
            } elseif (!empty($data['order_id'])) {
                $bodyText = "Shared an order reference";
            }
        }

        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $bodyText,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'attachment_type' => $attachmentType,
            'attachment_size' => $attachmentSize,
            'order_id' => $data['order_id'] ?? null,
        ]);

        $conversation->update([
            'last_message_at' => $message->created_at,
            'admin_id' => $request->user()->id,
        ]);

        // Notify seller if this is a seller support conversation or seller participated
        if ($conversation->seller_id && Schema::hasTable('seller_notifications')) {
            try {
                SellerNotification::create([
                    'user_id' => $conversation->seller_id,
                    'type' => 'support_reply',
                    'title' => 'Platform Support Replied',
                    'message' => Str::limit($bodyText, 120),
                    'url' => route('seller.messages.show', $conversation->id),
                ]);
            } catch (\Throwable $e) {}
        }

        // Notify buyer if this is a buyer conversation
        if ($conversation->buyer_id && Schema::hasTable('buyer_notifications')) {
            try {
                BuyerNotification::create([
                    'user_id' => $conversation->buyer_id,
                    'title' => 'Shopalona Support Replied',
                    'type' => 'message',
                    'message' => Str::limit($bodyText, 120),
                    'action_url' => route('buyer.conversations.show', $conversation->id),
                ]);
            } catch (\Throwable $e) {}
        }

        return back()->with('status', 'Message sent successfully.');
    }
}