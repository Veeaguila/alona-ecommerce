<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BuyerConversationController extends Controller
{
    /**
     * Display the buyer conversations and the selected conversation
     * on the same page.
     */
    public function index(Request $request): Response|JsonResponse|RedirectResponse
    {
        $buyer = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Load all buyer conversations
        |--------------------------------------------------------------------------
        */
        $conversations = $buyer->conversationsAsBuyer()
            ->with([
                'seller:id,name,store_name',
                'product:id,name,image_path,price',
                'order:id,order_number,status',

                // Only load the latest message for the conversation preview.
                'messages' => fn ($q) => $q
                    ->latest('created_at')
                    ->limit(1),
            ])
            ->withCount([
                'messages as unread_count' => fn ($q) => $q
                    ->whereNull('read_at')
                    ->where('sender_id', '!=', $buyer->id),
            ])
            ->orderByDesc('last_message_at')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Determine which conversation should be displayed
        |--------------------------------------------------------------------------
        |
        | The Vue page sends:
        |
        | /buyer/conversations?conversation=5
        |
        | If no conversation is specified, the newest conversation
        | is automatically selected.
        |
        */
        $conversationId = $request->integer('conversation');

        $selectedConversation = null;

        if ($conversationId) {
            $selectedConversation = $conversations->firstWhere(
                'id',
                $conversationId
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Default to the newest conversation
        |--------------------------------------------------------------------------
        */
        if (!$selectedConversation && $conversations->isNotEmpty()) {
            $selectedConversation = $conversations->first();
        }

        $messages = collect();

        /*
        |--------------------------------------------------------------------------
        | Load selected conversation messages
        |--------------------------------------------------------------------------
        */
        if ($selectedConversation) {
            /*
            |--------------------------------------------------------------------------
            | Reload the selected conversation with its full information.
            |--------------------------------------------------------------------------
            */
            $selectedConversation->load([
                'seller:id,name,store_name,avatar',
                'product:id,name,image_path,price',
                'order' => fn ($q) => $q->with([
                    'items.product:id,name,image_path,price',
                    'items.variant:id,title,price',
                    'items.parcel:id,order_item_id,tracking_number,status,carrier_name',
                ]),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Mark incoming messages as read.
            |--------------------------------------------------------------------------
            */
            $selectedConversation->messages()
                ->whereNull('read_at')
                ->where('sender_id', '!=', $buyer->id)
                ->update([
                    'read_at' => now(),
                ]);

            /*
            |--------------------------------------------------------------------------
            | Load all messages in chronological order.
            |--------------------------------------------------------------------------
            */
            $messages = $selectedConversation
                ->messages()
                ->with([
                    'sender:id,name,avatar',
                    'order' => fn ($q) => $q->with([
                        'items.product:id,name,image_path,price',
                        'items.variant:id,title,price',
                        'items.parcel:id,order_item_id,tracking_number,status,carrier_name',
                    ]),
                ])
                ->orderBy('created_at')
                ->get();

            /*
            |--------------------------------------------------------------------------
            | Keep the selected conversation unread count at zero
            |--------------------------------------------------------------------------
            */
            $selectedConversation->unread_count = 0;
        }

        $buyerOrders = $selectedConversation && $selectedConversation->seller_id
            ? \App\Models\Order::query()
                ->where('user_id', $buyer->id)
                ->whereHas('items.product', fn ($q) => $q->where('seller_id', $selectedConversation->seller_id))
                ->latest()
                ->limit(20)
                ->get(['id', 'order_number', 'total', 'status', 'created_at'])
            : collect();

        /*
        |--------------------------------------------------------------------------
        | Render ONE Vue page
        |--------------------------------------------------------------------------
        */
        if ($request->wantsJson()) {
            return response()->json([
                'conversations' => $conversations,
                'selectedConversation' => $selectedConversation,
                'messages' => $messages,
                'buyerOrders' => $buyerOrders,
            ]);
        }

        $convId = $request->query('conversation') ?? ($selectedConversation?->id ?? null);
        return redirect()->route('buyer.dashboard', array_filter(['chat' => $convId]));
    }


    /**
     * Open a specific conversation.
     *
     * The actual UI remains Buyer/Conversations.vue.
     */
    public function show(
        Request $request,
        Conversation $conversation
    ): RedirectResponse|JsonResponse {
        $buyer = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Security: buyer can only access their own conversations.
        |--------------------------------------------------------------------------
        */
        abort_unless(
            (int) $conversation->buyer_id === (int) $buyer->id,
            403
        );

        /*
        |--------------------------------------------------------------------------
        | Mark incoming messages as read.
        |--------------------------------------------------------------------------
        */
        $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $buyer->id)
            ->update([
                'read_at' => now(),
            ]);

        if ($request->wantsJson()) {
            $conversation->load([
                'seller:id,name,store_name',
                'product:id,name,image_path,price',
                'order:id,order_number,status,created_at',
            ]);

            $messages = $conversation
                ->messages()
                ->with('sender:id,name')
                ->orderBy('created_at')
                ->get();

            $conversation->unread_count = 0;

            return response()->json([
                'conversation' => $conversation,
                'messages' => $messages,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Redirect back to the combined conversations page.
        |--------------------------------------------------------------------------
        |
        | Example:
        |
        | /buyer/conversations?conversation=12
        |
        |--------------------------------------------------------------------------
        */
        return redirect()->route('buyer.dashboard', ['chat' => $conversation->id]);
    }


    /**
     * Send a message inside a buyer/seller conversation.
     */
    public function store(
        Request $request,
        Conversation $conversation
    ): RedirectResponse|JsonResponse {
        $buyer = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Security: buyer can only send messages in their own conversation.
        |--------------------------------------------------------------------------
        */
        abort_unless(
            (int) $conversation->buyer_id === (int) $buyer->id,
            403
        );

        /*
        |--------------------------------------------------------------------------
        | Validate message (SEL-REV-005, SEL-REV-006, SEL-REV-007 / A16, A25).
        |--------------------------------------------------------------------------
        */
        $hasAttachment = $request->hasFile('attachment');
        $hasOrder = ! empty($request->input('order_id'));
        $rawBody = $request->input('body');
        $trimmedBody = is_string($rawBody) ? trim($rawBody) : '';

        // Empty and spaces-only messages are blocked unless attachment or order is present
        if (! $hasAttachment && ! $hasOrder && $trimmedBody === '') {
            throw ValidationException::withMessages([
                'body' => 'Message cannot be empty or contain only spaces.',
            ]);
        }

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:1000'],
            'attachment' => [
                'nullable',
                'file',
                'image',
                'mimes:jpeg,jpg,png',
                'mimetypes:image/jpeg,image/png',
                'max:5120',
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
            } elseif (!empty($data['order_id'])) {
                $bodyText = "Shared an order reference";
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Create message.
        |--------------------------------------------------------------------------
        */
        $message = $conversation->messages()->create([
            'sender_id' => $buyer->id,
            'body' => $bodyText,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'attachment_type' => $attachmentType,
            'attachment_size' => $attachmentSize,
            'order_id' => $data['order_id'] ?? null,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Update conversation activity.
        |--------------------------------------------------------------------------
        */
        $conversation->update([
            'last_message_at' => now(),
        ]);

        if (\Illuminate\Support\Facades\Schema::hasTable('seller_notifications') && $conversation->seller_id) {
            try {
                \App\Models\SellerNotification::create([
                    'user_id' => $conversation->seller_id,
                    'type' => 'customer_message',
                    'title' => "New message from {$buyer->name}",
                    'message' => \Illuminate\Support\Str::limit($bodyText, 120),
                    'url' => route('seller.messages.show', $conversation->id),
                ]);
            } catch (\Throwable $e) {}
        }

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message->load([
                    'sender:id,name,avatar',
                    'order' => fn ($q) => $q->with([
                        'items.product:id,name,image_path,price',
                        'items.variant:id,title,price',
                        'items.parcel:id,order_item_id,tracking_number,status,carrier_name',
                    ]),
                ]),
                'conversation_id' => $conversation->id,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Return to the same combined conversation page.
        |--------------------------------------------------------------------------
        */
        return back();
    }


    /**
     * Buyer starts a conversation with a product's seller.
     *
     * Used by "Message Seller" / "Chat with Seller"
     * buttons on product pages.
     */
    public function start(
        Request $request,
        Product $product
    ): RedirectResponse|JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Product must have a seller.
        |--------------------------------------------------------------------------
        */
        abort_unless(
            $product->seller_id,
            404
        );

        $buyer = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Find an existing buyer/seller conversation or create one.
        |--------------------------------------------------------------------------
        */
        $conversation = Conversation::firstOrCreate(
            [
                'buyer_id' => $buyer->id,
                'seller_id' => $product->seller_id,
            ],
            [
                'product_id' => $product->id,
                'last_message_at' => now(),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | If the conversation already exists but doesn't have a product,
        | associate the current product with it.
        |--------------------------------------------------------------------------
        */
        if (
            !$conversation->product_id &&
            $product->id
        ) {
            $conversation->update([
                'product_id' => $product->id,
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'conversation_id' => $conversation->id,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Open the conversation inside the combined page.
        |--------------------------------------------------------------------------
        */
        return redirect()->route('buyer.dashboard', ['chat' => $conversation->id]);
    }

    /**
     * Start or find a conversation directly with a seller.
     */
    public function startWithSeller(
        Request $request,
        \App\Models\User $seller
    ): RedirectResponse|JsonResponse {
        abort_unless(in_array($seller->usertype, ['seller', 'both']), 404);
        $buyer = $request->user();

        $conversation = Conversation::firstOrCreate(
            [
                'buyer_id' => $buyer->id,
                'seller_id' => $seller->id,
            ],
            [
                'last_message_at' => now(),
            ]
        );

        if ($request->wantsJson()) {
            return response()->json([
                'conversation_id' => $conversation->id,
            ]);
        }

        return redirect()->route('buyer.dashboard', ['chat' => $conversation->id]);
    }
}