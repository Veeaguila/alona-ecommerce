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
                            'variants:id,product_id,color,size,price,stock,sku,image_path',
                            'sales',
                            'flashSales',
                        ]);
                },
                'variant:id,product_id,color,size,price,stock,sku,image_path',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $evaluation = app(\App\Services\CartPromotionCalculator::class)->evaluate($items);

        return Inertia::render('Buyer/Cart', [
            'items' => $evaluation['items'],
            'raw_subtotal' => number_format($evaluation['raw_subtotal'], 2, '.', ''),
            'bundle_discount' => number_format($evaluation['bundle_discount'], 2, '.', ''),
            'buy_x_get_y_discount' => number_format($evaluation['buy_x_get_y_discount'], 2, '.', ''),
            'promotion_discount' => number_format($evaluation['promotion_discount'], 2, '.', ''),
            'subtotal_after_promotions' => number_format($evaluation['subtotal_after_promotions'], 2, '.', ''),
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

        $seller = $product->seller;
        if ($seller && $seller->vacation_mode) {
            $storeName = $seller->store_name ?: $seller->name;
            $msg = $seller->vacation_message ? ": {$seller->vacation_message}" : '.';
            return back()->withErrors([
                'cart' => "{$storeName} is currently on vacation mode and temporarily not accepting new orders{$msg}",
            ]);
        }

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
        | STOCK & QUANTITY VALIDATION
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

        if ($requestedQuantity > $availableStock) {
            return back()->withErrors([
                'quantity' => "The requested quantity ({$requestedQuantity}) exceeds available stock ({$availableStock}).",
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | PER-BUYER PURCHASE LIMIT ENFORCEMENT (BUY-PDP-005)
        |--------------------------------------------------------------------------
        */
        if ($product->effective_per_buyer_limit) {
            $limit = $product->effective_per_buyer_limit;
            $alreadyPurchased = $product->purchasedUnitsCountFor($request->user());
            $currentCartQty = (int) $request->user()->cartItems()->where('product_id', $product->id)->sum('quantity');

            if ($alreadyPurchased >= $limit) {
                return back()->withErrors([
                    'quantity' => "Purchase limit reached. You have already purchased the maximum limit of {$limit} unit(s) for this product.",
                ]);
            }

            if (($alreadyPurchased + $currentCartQty + $requestedQuantity) > $limit) {
                $remainingAllowed = max(0, $limit - $alreadyPurchased - $currentCartQty);
                $msg = $remainingAllowed > 0
                    ? "Purchase limit of {$limit} unit(s) per customer. You can only add {$remainingAllowed} more unit(s)."
                    : "Purchase limit of {$limit} unit(s) per customer reached in your cart and order history.";
                return back()->withErrors([
                    'quantity' => $msg,
                ]);
            }
        }

        $quantityToAdd = $requestedQuantity;

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

            $cartItem = $request
                ->user()
                ->cartItems()
                ->create($payload);
        }

        /*
        |--------------------------------------------------------------------------
        | BUY NOW REDIRECTION (BUY-PDP-007)
        |--------------------------------------------------------------------------
        */
        if ($request->boolean('buy_now')) {
            return redirect()->route('buyer.checkout', [
                'selected_items' => [$cartItem->id],
            ]);
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
        } elseif (!empty($cartItem->variant_label) || $cartItem->product->variants->isNotEmpty()) {
            return back()->withErrors([
                'cart_item' => 'Variation unavailable',
            ]);
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

        if ($requestedQuantity > $availableStock) {
            return back()->withErrors([
                'quantity' => "The requested quantity ({$requestedQuantity}) exceeds available stock ({$availableStock}).",
            ]);
        }

        if ($cartItem->product->effective_per_buyer_limit) {
            $limit = $cartItem->product->effective_per_buyer_limit;
            $alreadyPurchased = $cartItem->product->purchasedUnitsCountFor($request->user());
            $otherCartQty = (int) $request->user()->cartItems()
                ->where('product_id', $cartItem->product_id)
                ->where('id', '!=', $cartItem->id)
                ->sum('quantity');

            if (($alreadyPurchased + $otherCartQty + $requestedQuantity) > $limit) {
                $remaining = max(0, $limit - $alreadyPurchased - $otherCartQty);
                return back()->withErrors([
                    'quantity' => "Purchase limit of {$limit} unit(s) exceeded for this product. Maximum allowed is {$remaining}.",
                ]);
            }
        }

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
