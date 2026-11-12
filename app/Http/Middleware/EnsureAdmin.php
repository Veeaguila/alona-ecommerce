<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->usertype === 'admin', 403);
        abort_unless($request->user()?->status === 'approved', 403);

        return $next($request);
    }
}
