<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuyerNotificationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Buyer/Notifications', ['notifications' => $request->user()->buyerNotifications()->latest()->get()]);
    }

    public function read(Request $request, int $notification): RedirectResponse
    {
        $request->user()->buyerNotifications()->whereKey($notification)->update(['read_at' => now()]);
        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->buyerNotifications()->whereNull('read_at')->update(['read_at' => now()]);
        return back();
    }
}
