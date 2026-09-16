<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuyerConversationController extends Controller
{
    /**
     * Display the buyer conversations and the selected conversation
     * on the same page.
     */
    public function index(Request $request): Response
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
                'product:id,name,image_path',
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
                'seller:id,name,store_name',
                'product:id,name,image_path,price',
                'order:id,order_number,status,created_at',
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
                ->with('sender:id,name')
                ->orderBy('created_at')
                ->get();

            /*
            |--------------------------------------------------------------------------
            | Keep the selected conversation unread count at zero
            |--------------------------------------------------------------------------
            */
            $selectedConversation->unread_count = 0;
        }

        /*
        |--------------------------------------------------------------------------
        | Render ONE Vue page
        |--------------------------------------------------------------------------
        |
        | Conversations.vue now contains:
        |
        | LEFT  = conversation list
        | RIGHT = selected conversation messages
        |
        | On mobile, the Vue page can switch between the two sections.
        |--------------------------------------------------------------------------
        */
        return Inertia::render('Buyer/Conversations', [
            'conversations' => $conversations,
            'selectedConversation' => $selectedConversation,
            'messages' => $messages,
        ]);
    }


    /**
     * Open a specific conversation.
     *
     * The actual UI remains Buyer/Conversations.vue.
     */
    public function show(
        Request $request,
        Conversation $conversation
    ): RedirectResponse {
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
        return redirect()->route(
            'buyer.conversations',
            [
                'conversation' => $conversation->id,
            ]
        );
    }


    /**
     * Send a message inside a buyer/seller conversation.
     */
    public function store(
        Request $request,
        Conversation $conversation
    ): RedirectResponse {
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
        | Validate message.
        |--------------------------------------------------------------------------
        */
        $data = $request->validate([
            'body' => [
                'required',
                'string',
                'max:2000',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Create message.
        |--------------------------------------------------------------------------
        */
        $conversation->messages()->create([
            'sender_id' => $buyer->id,
            'body' => trim($data['body']),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Update conversation activity.
        |--------------------------------------------------------------------------
        */
        $conversation->update([
            'last_message_at' => now(),
        ]);

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
    ): RedirectResponse {
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

        /*
        |--------------------------------------------------------------------------
        | Open the conversation inside the combined page.
        |--------------------------------------------------------------------------
        */
        return redirect()->route(
            'buyer.conversations',
            [
                'conversation' => $conversation->id,
            ]
        );
    }
}