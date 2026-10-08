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
                            'variants:id,product_id,color,size,stock',
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
     * Update cart item quantity or variation (BUYER-12).
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
            'product.variants',
            'variant',
        ]);

        abort_unless(
            $cartItem->product &&
            $cartItem->product->status === 'approved',
            422,
            'This product is no longer available.'
        );

        $data = $request->validate([
            'quantity' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'product_variant_id' => [
                'nullable',
                'integer',
                'exists:product_variants,id',
            ],
        ]);

        $targetVariantId = array_key_exists('product_variant_id', $data)
            ? $data['product_variant_id']
            : $cartItem->product_variant_id;

        $targetVariant = null;
        if ($targetVariantId) {
            $targetVariant = $cartItem->product->variants->firstWhere('id', $targetVariantId);
            abort_unless(
                $targetVariant,
                422,
                'The selected variation does not belong to this product.'
            );
        }

        $availableStock = $targetVariant
            ? (int) $targetVariant->stock
            : (int) $cartItem->product->stock;

        if ($availableStock <= 0) {
            return back()->withErrors([
                'cart_item' => 'The selected variation is currently out of stock.',
            ]);
        }

        $requestedQuantity = isset($data['quantity'])
            ? (int) $data['quantity']
            : (int) $cartItem->quantity;

        $newQuantity = min($requestedQuantity, $availableStock);

        // If the buyer switched variations, check if they already have an existing cart item with that variation
        if ($targetVariantId != $cartItem->product_variant_id) {
            $existingItemQuery = $request->user()->cartItems()
                ->where('product_id', $cartItem->product_id)
                ->where('id', '!=', $cartItem->id);

            if ($targetVariantId) {
                $existingItemQuery->where('product_variant_id', $targetVariantId);
            } else {
                $existingItemQuery->whereNull('product_variant_id');
            }

            $existingItem = $existingItemQuery->first();

            if ($existingItem) {
                $mergedQuantity = min($availableStock, (int) $existingItem->quantity + $newQuantity);
                $existingItem->update([
                    'quantity' => $mergedQuantity,
                ]);
                $cartItem->delete();

                return back()->with(
                    'status',
                    'Variation changed and combined with existing item in your cart.'
                );
            }
        }

        $cartItem->update([
            'product_variant_id' => $targetVariantId,
            'quantity' => $newQuantity,
        ]);

        return back()->with(
            'status',
            'Cart item updated successfully.'
        );
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