<?php

namespace App\Http\Middleware;

use App\Services\HubAccess;
use Closure;
use Illuminate\Http\Request;

class EnsureHubOperator
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated.');
        $context = app(HubAccess::class)->context($user);
        abort_unless($context && !empty($context['center']), 403, 'Unauthorized hub operator access.');
        return $next($request);
    }
}
