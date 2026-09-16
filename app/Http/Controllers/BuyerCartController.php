<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class BuyerCartController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Buyer/Cart', [
            'items' => $request->user()->cartItems()->with(['product', 'variant'])->get(),
        ]);
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->status === 'approved', 404);

        $variantColumnExists = Schema::hasColumn('cart_items', 'product_variant_id');
        $data = $request->validate([
            'quantity' => 'nullable|integer|min:1',
            'product_variant_id' => $variantColumnExists ? 'nullable|exists:product_variants,id' : 'nullable',
        ]);

        $variant = null;

        if ($variantColumnExists && ! empty($data['product_variant_id'])) {
            $variant = $product->variants()->whereKey($data['product_variant_id'])->firstOrFail();
        } elseif ($product->variants()->exists() && $variantColumnExists) {
            abort(422, 'Please select a color/size before adding this product to your cart.');
        }

        $availableStock = $variant ? $variant->stock : $product->stock;
        abort_unless($availableStock > 0, 404);

        $requestedQuantity = $data['quantity'] ?? 1;

        $query = $request->user()->cartItems()->where('product_id', $product->id);

        if ($variantColumnExists) {
            $query->where('product_variant_id', $variant?->id);
        }

        $item = $query->first();

        if ($item) {
            $item->quantity = min($availableStock, $item->quantity + $requestedQuantity);
            $item->save();
        } else {
            $payload = [
                'product_id' => $product->id,
                'quantity' => min($availableStock, $requestedQuantity),
            ];

            if ($variantColumnExists) {
                $payload['product_variant_id'] = $variant?->id;
            }

            $request->user()->cartItems()->create($payload);
        }

        return back()->with('status', 'Product added to your cart.');
    }

    public function update(Request $request, CartItem $cartItem): RedirectResponse
    {
        abort_unless($cartItem->user_id === $request->user()->id, 403);

        $availableStock = $cartItem->variant ? $cartItem->variant->stock : $cartItem->product->stock;
        abort_unless($cartItem->product->status === 'approved' && $availableStock > 0, 422);

        $data = $request->validate(['quantity' => 'required|integer|min:1|max:'.$availableStock]);
        $cartItem->update($data);

        return back();
    }

    public function destroy(Request $request, CartItem $cartItem): RedirectResponse
    {
        abort_unless($cartItem->user_id === $request->user()->id, 403);
        $cartItem->delete();

        return back();
    }
}