<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerNotificationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Seller/Notifications', [
            'notifications' => $request->user()->sellerNotifications()->latest()->get(),
        ]);
    }

    public function read(Request $request, int $notification): RedirectResponse
    {
        $request->user()->sellerNotifications()->whereKey($notification)->update(['read_at' => now()]);
        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->sellerNotifications()->whereNull('read_at')->update(['read_at' => now()]);
        return back();
    }

    /**
     * Submit a payout request (SELLER-61).
     */
    public function storePayoutRequest(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:100'],
            'payout_method' => ['required', 'string', 'in:gcash,maya,bank_transfer'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:100'],
        ]);

        $reference = 'PAY-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));

        \App\Models\SellerPayoutRequest::create([
            'payout_reference' => $reference,
            'seller_id' => $request->user()->id,
            'amount' => $validated['amount'],
            'payout_method' => $validated['payout_method'],
            'account_name' => $validated['account_name'],
            'account_number' => $validated['account_number'],
            'bank_name' => $validated['bank_name'] ?? null,
            'status' => 'pending',
        ]);

        return back()->with('status', "Payout request #{$reference} submitted successfully.");
    }
}
