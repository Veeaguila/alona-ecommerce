<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\SellerNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BuyerProductQuestionController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:500'],
        ]);

        $question = $product->questions()->create([
            'buyer_id' => $request->user()->id,
            'question' => $data['question'],
            'is_public' => true,
        ]);

        // Notify seller
        if ($product->seller_id) {
            SellerNotification::create([
                'user_id' => $product->seller_id,
                'type' => 'question',
                'title' => 'New product question',
                'message' => "{$request->user()->name} asked about {$product->name}",
                'url' => route('seller.products.edit', $product, false),
            ]);
        }

        return back()->with('status', 'Your question has been submitted. The seller will answer soon.');
    }
}
