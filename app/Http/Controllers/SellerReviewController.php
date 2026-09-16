<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $sellerId = $request->user()->id;

        $reviews = Review::query()
            ->whereHas('product', fn ($q) => $q->where('seller_id', $sellerId))
            ->with(['user:id,name', 'product:id,name,image_path'])
            ->when($request->filled('rating'), fn ($q) => $q->where('rating', $request->input('rating')))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $ratingAvg = Review::whereHas('product', fn ($q) => $q->where('seller_id', $sellerId))->avg('rating');

        return Inertia::render('Seller/Reviews', [
            'reviews' => $reviews,
            'averageRating' => round((float) $ratingAvg, 2),
            'filters' => $request->only(['rating']),
        ]);
    }

    public function reply(Request $request, Review $review): RedirectResponse
    {
        abort_unless($review->product->seller_id === $request->user()->id, 403);

        $data = $request->validate(['seller_reply' => ['required', 'string', 'max:1000']]);
        $review->update(['seller_reply' => $data['seller_reply'], 'seller_replied_at' => now()]);

        return back()->with('status', 'Reply posted.');
    }
}
