<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BuyerMessageController extends Controller
{
    /**
     * Redirect legacy /buyer/messages to /buyer/conversations
     * as messaging is now streamlined through the floating chat widget
     * and the unified conversations view.
     */
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('buyer.conversations', $request->all());
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('buyer.conversations');
    }
}
