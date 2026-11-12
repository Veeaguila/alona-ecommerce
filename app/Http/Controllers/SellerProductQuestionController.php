<?php

namespace App\Http\Controllers;

use App\Models\ProductQuestion;
use App\Models\BuyerNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SellerProductQuestionController extends Controller
{
    public function answer(Request $request, ProductQuestion $question): RedirectResponse
    {
        $question->load('product');

        abort_unless($question->product->seller_id === $request->user()->id, 403);

        $data = $request->validate([
            'answer' => ['required', 'string', 'max:1000'],
        ]);

        $question->update([
            'answer' => $data['answer'],
            'answered_by' => $request->user()->id,
            'answered_at' => now(),
        ]);

        // Notify buyer
        BuyerNotification::create([
            'user_id' => $question->buyer_id,
            'type' => 'question_answered',
            'title' => 'Your question was answered',
            'message' => "The seller answered your question about {$question->product->name}",
            'url' => route('buyer.product', $question->product, false),
        ]);

        return back()->with('status', 'Answer posted successfully.');
    }
}
