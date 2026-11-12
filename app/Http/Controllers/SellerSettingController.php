<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerSettingController extends Controller
{
    public function edit(Request $request): Response
    {
        $seller = $request->user();

        return Inertia::render('Seller/Settings', [
            'settings' => $seller->only([
                'shipping_fee', 'return_policy_days', 'bank_name',
                'bank_account_name', 'bank_account_number',
                'notify_new_order', 'notify_messages',
            ]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'shipping_fee' => ['required', 'numeric', 'min:0'],
            'return_policy_days' => ['required', 'integer', 'min:0', 'max:365'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'bank_account_name' => ['nullable', 'string', 'max:150'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'notify_new_order' => ['boolean'],
            'notify_messages' => ['boolean'],
        ]);

        $request->user()->update($data);

        return back()->with('status', 'Settings saved.');
    }
}
