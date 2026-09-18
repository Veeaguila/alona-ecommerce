<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class BuyerCartController extends Controller
{
    /**
     * Display the authenticated buyer's cart.
     */
    public function index(Request $request): Response
    {
        $items = $request->user()
            ->cartItems()
            ->with([
                'product' => function ($query) {
                    $query
                        ->with([
                            'category:id,name,slug',
                            'images:id,product_id,image_path,sort_order',
                        ]);
                },
                'variant:id,product_id,color,size,stock',
            ])
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Buyer/Cart', [
            'items' => $items,
        ]);
    }

    /**
     * Add a product to the buyer's cart.
     */
    public function store(
        Request $request,
        Product $product
    ): RedirectResponse {
        abort_unless(
            $product->status === 'approved',
            404
        );

        $variantColumnExists = Schema::hasColumn(
            'cart_items',
            'product_variant_id'
        );

        $data = $request->validate([
            'quantity' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'product_variant_id' => $variantColumnExists
                ? [
                    'nullable',
                    'integer',
                    'exists:product_variants,id',
                ]
                : [
                    'nullable',
                ],
        ]);

        $requestedQuantity = max(
            1,
            (int) ($data['quantity'] ?? 1)
        );

        $variant = null;

        /*
        |--------------------------------------------------------------------------
        | VARIANT VALIDATION
        |--------------------------------------------------------------------------
        */

        if ($variantColumnExists) {
            if (!empty($data['product_variant_id'])) {
                $variant = $product
                    ->variants()
                    ->whereKey($data['product_variant_id'])
                    ->first();

                abort_unless(
                    $variant,
                    422,
                    'The selected variation does not belong to this product.'
                );
            } elseif ($product->variants()->exists()) {
                return back()->withErrors([
                    'product_variant_id' =>
                        'Please select an available color/size before adding this product to your cart.',
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | STOCK
        |--------------------------------------------------------------------------
        */

        $availableStock = $variant
            ? (int) $variant->stock
            : (int) $product->stock;

        if ($availableStock <= 0) {
            return back()->withErrors([
                'cart' => 'This product is currently out of stock.',
            ]);
        }

        $quantityToAdd = min(
            $requestedQuantity,
            $availableStock
        );

        /*
        |--------------------------------------------------------------------------
        | FIND EXISTING CART ITEM
        |--------------------------------------------------------------------------
        */

        $query = $request
            ->user()
            ->cartItems()
            ->where('product_id', $product->id);

        if ($variantColumnExists) {
            if ($variant) {
                $query->where(
                    'product_variant_id',
                    $variant->id
                );
            } else {
                $query->whereNull(
                    'product_variant_id'
                );
            }
        }

        $cartItem = $query->first();

        /*
        |--------------------------------------------------------------------------
        | CREATE / UPDATE
        |--------------------------------------------------------------------------
        */

        if ($cartItem) {
            $newQuantity = min(
                $availableStock,
                (int) $cartItem->quantity + $quantityToAdd
            );

            $cartItem->update([
                'quantity' => $newQuantity,
            ]);
        } else {
            $payload = [
                'product_id' => $product->id,
                'quantity' => $quantityToAdd,
            ];

            if ($variantColumnExists) {
                $payload['product_variant_id'] =
                    $variant?->id;
            }

            $request
                ->user()
                ->cartItems()
                ->create($payload);
        }

        return back()->with(
            'status',
            $cartItem
                ? 'Your cart quantity has been updated.'
                : 'Product added to your cart.'
        );
    }

    /**
     * Update cart item quantity.
     */
    public function update(
        Request $request,
        CartItem $cartItem
    ): RedirectResponse {
        abort_unless(
            $cartItem->user_id === $request->user()->id,
            403
        );

        $cartItem->loadMissing([
            'product',
            'variant',
        ]);

        abort_unless(
            $cartItem->product &&
            $cartItem->product->status === 'approved',
            422,
            'This product is no longer available.'
        );

        $availableStock = $cartItem->variant
            ? (int) $cartItem->variant->stock
            : (int) $cartItem->product->stock;

        if ($availableStock <= 0) {
            return back()->withErrors([
                'cart_item' =>
                    'This item is currently out of stock.',
            ]);
        }

        $data = $request->validate([
            'quantity' => [
                'required',
                'integer',
                'min:1',
                'max:' . $availableStock,
            ],
        ]);

        $cartItem->update([
            'quantity' => (int) $data['quantity'],
        ]);

        return back();
    }

    /**
     * Remove one cart item.
     */
    public function destroy(
        Request $request,
        CartItem $cartItem
    ): RedirectResponse {
        abort_unless(
            $cartItem->user_id === $request->user()->id,
            403
        );

        $cartItem->delete();

        return back()->with(
            'status',
            'Item removed from your cart.'
        );
    }
}