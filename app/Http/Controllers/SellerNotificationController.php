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
}
