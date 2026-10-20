<?php

namespace App\Http\Controllers;

use App\Models\BuyerNotification;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Order;
use App\Models\SellerNotification;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SellerConversationController extends Controller
{
    /**
     * Display seller conversations list with Customer Chats and Admin Support tabs.
     */
    public function index(Request $request): Response
    {
        $sellerId = $request->user()->id;

        $conversations = Conversation::query()
            ->where('seller_id', $sellerId)
            ->with([
                'buyer:id,name,email,avatar',
                'admin:id,name,email,avatar',
                'order:id,order_number,status,total',
                'messages' => fn ($q) => $q->latest()->limit(1),
            ])
            ->withCount([
                'messages as unread_count' => fn ($q) => $q->whereNull('read_at')->where('sender_id', '!=', $sellerId),
            ])
            ->orderByDesc('last_message_at')
            ->get();

        $sellerOrders = Order::query()
            ->whereHas('items.product', fn ($q) => $q->where('seller_id', $sellerId))
            ->latest()
            ->limit(15)
            ->get(['id', 'order_number', 'total', 'status']);

        return Inertia::render('Seller/Messages', [
            'conversations' => $conversations,
            'sellerOrders' => $sellerOrders,
        ]);
    }

    /**
     * Display a specific conversation thread with full order snippets and media.
     */
    public function show(Request $request, Conversation $conversation): Response
    {
        abort_unless((int) $conversation->seller_id === (int) $request->user()->id, 403);

        // Mark unread messages from other users as read
        $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $request->user()->id)
            ->update(['read_at' => now()]);

        // Eager load participants and conversation-level order reference
        $conversation->load([
            'buyer:id,name,email,avatar',
            'admin:id,name,email,avatar',
            'order' => fn ($q) => $q->with([
                'items.product:id,name,image_path,price',
                'items.variant:id,title,price',
                'items.parcel:id,order_item_id,tracking_number,status,carrier_name',
            ]),
        ]);

        // Eager load messages with attachments, order snippets, and sender profiles
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

        // Recent orders by this seller (optionally filtered by buyer) for snippet attachment
        $sellerId = $request->user()->id;
        $sellerOrdersQuery = Order::query()
            ->whereHas('items.product', fn ($q) => $q->where('seller_id', $sellerId))
            ->with(['items.product:id,name,image_path,price']);

        if ($conversation->buyer_id) {
            $sellerOrdersQuery->where('user_id', $conversation->buyer_id);
        }

        $sellerOrders = $sellerOrdersQuery->latest()->limit(25)->get([
            'id', 'order_number', 'total', 'status', 'created_at', 'user_id',
        ]);

        return Inertia::render('Seller/MessageThread', [
            'conversation' => $conversation,
            'messages' => $messages,
            'sellerOrders' => $sellerOrders,
        ]);
    }

    /**
     * Send a message with text, media attachments (SELLER-42, SEL-REV-005, SEL-REV-006, SEL-REV-007), and order snippets (SELLER-43).
     */
    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless((int) $conversation->seller_id === (int) $request->user()->id, 403);

        $hasAttachment = $request->hasFile('attachment');
        $hasOrder = ! empty($request->input('order_id'));
        $rawBody = $request->input('body');
        $trimmedBody = is_string($rawBody) ? trim($rawBody) : '';

        // SEL-REV-007: Empty and spaces-only messages are not sent (A25)
        if (! $hasAttachment && ! $hasOrder && $trimmedBody === '') {
            throw ValidationException::withMessages([
                'body' => 'Message cannot be empty or contain only spaces.',
            ]);
        }

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:1000'],
            'attachment' => [
                'nullable',
                'file',
                'image',
                'mimes:jpeg,jpg,png',
                'mimetypes:image/jpeg,image/png',
                'max:5120',
                function ($attribute, $value, $fail) {
                    if (! $value instanceof \Illuminate\Http\UploadedFile) {
                        return;
                    }
                    $path = $value->getRealPath();
                    if (! $path || ! file_exists($path)) {
                        return;
                    }
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $realMime = finfo_file($finfo, $path);
                    finfo_close($finfo);

                    if (! in_array($realMime, ['image/jpeg', 'image/png'], true)) {
                        $fail('The attachment must be a valid JPG or PNG image (executable or non-image content is rejected).');
                        return;
                    }

                    $imageInfo = @getimagesize($path);
                    if ($imageInfo === false || ! in_array($imageInfo[2] ?? 0, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
                        $fail('The attachment file content is not a valid JPG or PNG image.');
                    }
                },
            ],
            'order_id' => ['nullable', 'exists:orders,id'],
        ], [
            'body.max' => 'The message must not exceed 1,000 characters.',
            'attachment.max' => 'The attachment must not exceed 5 MB.',
            'attachment.image' => 'The attachment must be a valid JPG or PNG image.',
            'attachment.mimes' => 'The attachment must be a JPG or PNG file.',
            'attachment.mimetypes' => 'The attachment must be a valid JPG or PNG image (executable or non-image content is rejected).',
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

        $bodyText = $trimmedBody;
        if ($bodyText === '') {
            if ($attachmentName) {
                $bodyText = "Sent an attachment: {$attachmentName}";
            } elseif (!empty($validated['order_id'])) {
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
            'order_id' => $validated['order_id'] ?? null,
        ]);

        $conversation->update(['last_message_at' => now()]);

        // If conversation is with buyer, dispatch buyer notification
        if ($conversation->type !== 'seller_admin' && $conversation->buyer_id && Schema::hasTable('buyer_notifications')) {
            try {
                $sellerName = $request->user()->store_name ?: $request->user()->name;
                BuyerNotification::create([
                    'user_id' => $conversation->buyer_id,
                    'title' => "New message from {$sellerName}",
                    'type' => 'message',
                    'message' => Str::limit($bodyText, 120),
                    'action_url' => route('buyer.conversations.show', $conversation->id),
                ]);
            } catch (\Throwable $e) {}
        }

        return back()->with('status', 'Message sent successfully.');
    }

    /**
     * Seller opens a direct support chat thread with platform administration (SELLER-44).
     */
    public function startAdminSupport(Request $request): RedirectResponse
    {
        $seller = $request->user();

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'order_id' => ['nullable', 'exists:orders,id'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,txt,zip'],
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

        $conversation = Conversation::create([
            'type' => 'seller_admin',
            'seller_id' => $seller->id,
            'buyer_id' => null,
            'subject' => $validated['subject'],
            'order_id' => $validated['order_id'] ?? null,
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $conversation->messages()->create([
            'sender_id' => $seller->id,
            'body' => $validated['body'],
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'attachment_type' => $attachmentType,
            'attachment_size' => $attachmentSize,
            'order_id' => $validated['order_id'] ?? null,
        ]);

        return redirect()->route('seller.messages.show', $conversation->id)
            ->with('status', 'Support ticket opened with platform admin.');
    }

    /**
     * Seller starts a conversation with a buyer (e.g. from Order Details).
     */
    public function start(Request $request, User $buyer): RedirectResponse
    {
        abort_unless($buyer->usertype === 'buyer', 404);

        $conversation = Conversation::firstOrCreate([
            'buyer_id' => $buyer->id,
            'seller_id' => $request->user()->id,
            'type' => 'buyer_seller',
        ], ['last_message_at' => now()]);

        return redirect()->route('seller.messages.show', $conversation);
    }
}
