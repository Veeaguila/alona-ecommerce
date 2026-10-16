<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSeller
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user?->usertype === 'seller', 403);

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.otp');
        }

        if ($user->status !== 'approved') {
            if ($user->status === 'rejected' && $request->routeIs('seller.account.documents.update')) {
                return $next($request);
            }

            return redirect()->route('application.status');
        }

        return $next($request);
    }
}
