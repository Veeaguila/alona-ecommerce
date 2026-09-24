<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    public function create(): RedirectResponse
    {
        return redirect('/');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        if ($request->user()->status !== 'approved') {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Your account is awaiting approval or is currently unavailable.',
            ]);
        }

        $request->session()->regenerate();

        $destination = match ($request->user()->usertype) {
            'seller' => 'seller.dashboard',
            'admin' => 'admin.dashboard',
            default => 'buyer.dashboard',
        };

        return redirect()->intended(
            route($destination, absolute: false)
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}