<?php

namespace App\Http\Middleware;

use App\Services\LogisticsSessionLifetime;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceLogisticsIdleTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user || $user->usertype !== 'sorting_center') return $next($request);

        $lifetime = app(LogisticsSessionLifetime::class);
        $token = $lifetime->ownedWebToken($user, $request->session()->get('logistics.web_token'));
        $seen = $request->session()->get($lifetime::ACTIVITY_KEY);
        // API activity from this browser's bound token also keeps its web session active.
        $seen = max(is_int($seen) ? $seen : 0, $token?->last_used_at?->timestamp ?? $token?->created_at?->timestamp ?? 0);
        if ($seen && $seen <= now()->timestamp - $lifetime::IDLE_SECONDS) {
            $token?->delete();
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return $request->expectsJson()
                ? response()->json(['message' => 'Your Logistics session expired after 30 minutes of inactivity. Sign in again.'], 401)
                : redirect('/?auth=login')->with('error', 'Your Logistics session expired. Sign in again.');
        }

        $request->session()->put($lifetime::ACTIVITY_KEY, now()->timestamp);
        return $next($request);
    }
}
