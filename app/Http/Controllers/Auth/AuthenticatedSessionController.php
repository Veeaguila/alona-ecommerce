<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AccountDestination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class AuthenticatedSessionController extends Controller
{
    public function create(): RedirectResponse
    {
        return redirect('/?auth=login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $destination = app(AccountDestination::class)->route($request->user());
        // Discard arbitrary cross-role intended URLs; dashboard routes enforce their own permissions.
        $request->session()->forget('url.intended');

        return redirect()->route($destination);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $token = $request->session()->get('logistics.web_token');
        $accessToken = $token ? PersonalAccessToken::findToken($token) : null;
        if ($accessToken?->tokenable_id === $request->user()?->id) {
            $accessToken?->delete();
        }
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
