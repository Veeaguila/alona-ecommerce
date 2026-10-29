<?php

namespace App\Http\Middleware;

use App\Services\HubAccess;
use Closure;
use Illuminate\Http\Request;

class EnsureHubOperator
{
    public function handle(Request $request, Closure $next)
    {
        app(HubAccess::class)->context($request->user());
        return $next($request);
    }
}
