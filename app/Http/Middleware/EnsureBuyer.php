<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBuyer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user?->usertype === 'buyer', 403);

        if (! $user->hasVerifiedEmail()) {
            abort_if($request->expectsJson() || $request->is('api/*'), 403, 'Verify your email before using your Buyer account.');
            return redirect()->route('verification.otp');
        }

        abort_unless($user->status === 'approved', 403);

        return $next($request);
    }
}
