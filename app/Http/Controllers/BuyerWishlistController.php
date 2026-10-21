<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuyerWishlistController extends Controller
{
    /**
     * Display a listing of the buyer's wishlisted items.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $wishlistItems = Wishlist::query()
            ->where('user_id', $user->id)
            ->with([
                'product' => function ($query) {
                    $query->with([
                        'category:id,name,slug',
                        'seller:id,name,store_name,store_logo_path,status',
                        'variants',
                    ]);
                },
            ])
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Buyer/Wishlist', [
            'wishlist' => $wishlistItems,
        ]);
    }

    /**
     * Toggle item in/out of the buyer's wishlist.
     */
    public function toggle(Request $request, Product $product)
    {
        if (!auth()->check()) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->guest(route('login'));
        }

        $user = $request->user();

        $existing = Wishlist::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $wishlisted = false;
            $message = 'Item removed from your wishlist.';
        } else {
            // BUY-WISH-002: Rapid duplicate prevention using firstOrCreate
            Wishlist::firstOrCreate([
                'user_id' => $user->id,
                'product_id' => $product->id,
            ]);
            $wishlisted = true;
            $message = 'Item added to your wishlist.';
        }

        $totalWishlistCount = Wishlist::where('user_id', $user->id)->count();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'wishlisted' => $wishlisted,
                'count' => $totalWishlistCount,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Remove an item from the buyer's wishlist.
     */
    public function destroy(Request $request, Product $product)
    {
        $user = $request->user();

        Wishlist::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'wishlisted' => false,
                'message' => 'Item removed from your wishlist.',
            ]);
        }

        return back()->with('success', 'Item removed from your wishlist.');
    }
}
