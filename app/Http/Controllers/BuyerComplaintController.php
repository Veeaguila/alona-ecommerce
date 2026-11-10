<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BuyerComplaintController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:5000'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'seller_id' => ['nullable', 'integer', 'exists:users,id'],
            'courier_id' => ['nullable', 'integer', 'exists:users,id'],
            'evidence' => ['nullable', 'array'],
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $data) {
            if (! empty($data['order_id'])) {
                // Prevent unrelated users from placing settlement holds on another buyer's order.
                \App\Models\Order::withoutEvents(fn () => \App\Models\Order::whereKey($data['order_id'])
                    ->where('user_id', $request->user()->id)->lockForUpdate()->firstOrFail());
            }
            Complaint::create([
                'buyer_id' => $request->user()->id,
                'seller_id' => $data['seller_id'] ?? null,
                'courier_id' => $data['courier_id'] ?? null,
                'order_id' => $data['order_id'] ?? null,
                'subject' => $data['subject'],
                'description' => $data['description'],
                'evidence' => $data['evidence'] ?? [],
                'status' => 'pending',
            ]);
        });

        return back()->with('status', 'Complaint submitted and sent to the administrator for review.');
    }
}
