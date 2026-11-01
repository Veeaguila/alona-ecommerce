<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Wishlist;
use App\Services\CartPromotionCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuyerCartApiController extends Controller
{
    /**
     * Get authenticated buyer's cart.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $items = $user->cartItems()
            ->with([
                'product' => function ($q) {
                    $q->with([
                        'category:id,name,slug',
                        'seller:id,name,store_name,store_logo_path,vacation_mode',
                        'images:id,product_id,image_path',
                        'variants:id,product_id,color,size,price,stock,sku',
                    ]);
                },
                'variant:id,product_id,color,size,price,stock,sku',
            ])
            ->orderByDesc('id')
            ->get();

        $evaluation = app(CartPromotionCalculator::class)->evaluate($items);

        $formattedItems = $items->map(function ($item) {
            $product = $item->product;
            $variant = $item->variant;
            $price = $variant ? (float) $variant->price : (float) ($product?->price ?? 0);
            $stock = $variant ? (int) $variant->stock : (int) ($product?->stock ?? 0);

            $imageUrl = asset('images/alona.png');
            if ($product && $product->image_path) {
                $imageUrl = asset('storage/' . $product->image_path);
            } elseif ($product && $product->images->isNotEmpty()) {
                $imageUrl = asset('storage/' . $product->images->first()->image_path);
            }

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $product?->name ?? 'Unknown Product',
                'product_slug' => $product?->slug,
                'image_url' => $imageUrl,
                'product_variant_id' => $item->product_variant_id,
                'variant_label' => $item->variant_label,
                'quantity' => (int) $item->quantity,
                'unit_price' => $price,
                'total_price' => round($price * (int) $item->quantity, 2),
                'stock' => $stock,
                'is_in_stock' => $product?->status === 'approved' && !$item->is_unavailable
                    && !$product?->seller?->vacation_mode
                    && (!$variant || (int) $variant->product_id === (int) $product->id)
                    && $stock >= (int) $item->quantity,
                'seller_id' => $product?->seller_id,
                'seller_name' => $product?->seller?->store_name ?: $product?->seller?->name,
            ];
        });

        return response()->json([
            'items' => $formattedItems,
            'subtotal' => (float) $evaluation['raw_subtotal'],
            'discount' => (float) $evaluation['promotion_discount'],
            'total' => (float) $evaluation['subtotal_after_promotions'],
            'item_count' => $items->sum('quantity'),
        ]);
    }

    /**
     * Add product to cart.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $product = Product::where('status', 'approved')->findOrFail($validated['product_id']);

        if ($product->seller && $product->seller->vacation_mode) {
            return response()->json([
                'message' => 'This store is temporarily on vacation mode and cannot receive new orders.',
            ], 422);
        }

        $quantity = max(1, (int) ($validated['quantity'] ?? 1));
        $variantId = $validated['product_variant_id'] ?? null;
        $variant = $variantId ? $product->variants()->whereKey($variantId)->firstOrFail() : null;

        if (! $variant && $product->variants()->exists()) {
            return response()->json(['message' => 'Please select an available product variation.'], 422);
        }

        $availableStock = $variant ? (int) $variant->stock : (int) $product->stock;
        if ($availableStock < $quantity) {
            return response()->json([
                'message' => "Only {$availableStock} items available in stock.",
            ], 422);
        }

        $user = $request->user();
        $this->assertPurchaseLimit($user, $product, $quantity);
        $cartItem = CartItem::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variantId)
            ->first();

        if ($cartItem) {
            $newQuantity = $cartItem->quantity + $quantity;
            if ($newQuantity > $availableStock) {
                return response()->json([
                    'message' => "Cannot add more. Maximum available stock is {$availableStock}.",
                ], 422);
            }
            $cartItem->quantity = $newQuantity;
            $cartItem->save();
        } else {
            $variantLabel = $variant ? trim(($variant->color ?? '') . ' ' . ($variant->size ?? '')) : null;
            $cartItem = CartItem::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'product_variant_id' => $variantId,
                'variant_label' => $variantLabel,
                'quantity' => $quantity,
            ]);
        }

        return response()->json([
            'message' => 'Product added to cart successfully.',
            'cart_item' => $cartItem,
            'cart_count' => $user->cartItems()->sum('quantity'),
        ], 201);
    }

    /**
     * Update quantity of a cart item.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $cartItem = $user->cartItems()->whereKey($id)->with(['product.variants', 'product.seller', 'variant'])->firstOrFail();
        if (!$cartItem->product || $cartItem->is_unavailable || $cartItem->product->seller?->vacation_mode
            || ($cartItem->variant && (int) $cartItem->variant->product_id !== (int) $cartItem->product_id)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['quantity' => 'This product or variation is currently unavailable.']);
        }
        $this->assertPurchaseLimit($user, $cartItem->product, $validated['quantity'], $cartItem->id);

        $stock = $cartItem->variant ? (int) $cartItem->variant->stock : (int) ($cartItem->product?->stock ?? 0);
        if ($validated['quantity'] > $stock) {
            return response()->json([
                'message' => "Only {$stock} items available in stock.",
            ], 422);
        }

        $cartItem->quantity = $validated['quantity'];
        $cartItem->save();

        return response()->json([
            'message' => 'Cart updated.',
            'cart_item' => $cartItem,
        ]);
    }

    /**
     * Delete an item from cart.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $user->cartItems()->whereKey($id)->delete();

        return response()->json([
            'message' => 'Item removed from cart.',
            'cart_count' => $user->cartItems()->sum('quantity'),
        ]);
    }

    private function assertPurchaseLimit($user, Product $product, int $quantity, ?int $excludedCartId = null): void
    {
        $limit = $product->effective_per_buyer_limit;
        if (!$limit) return;
        $otherQuantity = $user->cartItems()->where('product_id', $product->id)
            ->when($excludedCartId !== null, fn ($query) => $query->whereKeyNot($excludedCartId))->sum('quantity');
        if ($product->purchasedUnitsCountFor($user) + $otherQuantity + $quantity > $limit) {
            throw \Illuminate\Validation\ValidationException::withMessages(['quantity' => "Purchase limit of {$limit} unit(s) exceeded for this product."]);
        }
    }

    /**
     * Get buyer wishlist.
     */
    public function wishlist(Request $request): JsonResponse
    {
        $user = $request->user();
        $items = Wishlist::query()
            ->where('user_id', $user->id)
            ->with(['product.category', 'product.seller', 'product.images'])
            ->latest('id')
            ->get();

        $formatted = $items->map(function ($w) {
            $p = $w->product;
            if (! $p) return null;

            $imageUrl = asset('images/alona.png');
            if ($p->image_path) {
                $imageUrl = asset('storage/' . $p->image_path);
            } elseif ($p->images->isNotEmpty()) {
                $imageUrl = asset('storage/' . $p->images->first()->image_path);
            }

            return [
                'id' => $w->id,
                'product_id' => $p->id,
                'name' => $p->name,
                'price' => (float) $p->price,
                'stock' => (int) $p->stock,
                'rating' => (float) ($p->rating ?? 0),
                'image_url' => $imageUrl,
                'category_name' => $p->category?->name,
                'seller_name' => $p->seller?->store_name ?: $p->seller?->name,
            ];
        })->filter()->values();

        return response()->json(['wishlist' => $formatted]);
    }

    /**
     * Toggle a product in/out of wishlist.
     */
    public function toggleWishlist(Request $request, int $productId): JsonResponse
    {
        $user = $request->user();
        $product = Product::where('status', 'approved')->findOrFail($productId);

        $existing = Wishlist::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json([
                'message' => 'Product removed from wishlist.',
                'is_wishlisted' => false,
            ]);
        }

        Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        return response()->json([
            'message' => 'Product added to wishlist.',
            'is_wishlisted' => true,
        ]);
    }
}

