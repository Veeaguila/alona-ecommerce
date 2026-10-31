<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseLocalSessionCookies
{
    public function handle(Request $request, Closure $next): Response
    {
        // The same local app may also serve a public HTTPS tunnel. Only adapt direct loopback requests.
        $forwarded = ['Forwarded', 'X-Forwarded-For', 'X-Forwarded-Host', 'X-Forwarded-Proto', 'X-Forwarded-Port', 'X-Forwarded-Prefix', 'X-Forwarded-Ssl'];
        if (!app()->environment('local') || $request->isSecure()
            || !in_array($request->getHost(), ['localhost', '127.0.0.1', '[::1]'], true)
            || !in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true)
            || array_filter($forwarded, fn (string $header): bool => $request->headers->has($header))) {
            return $next($request);
        }

        $previous = [
            'session.domain' => config('session.domain'), 'session.secure' => config('session.secure'),
            'session.same_site' => config('session.same_site'), 'session.partitioned' => config('session.partitioned'),
        ];
        config(['session.domain' => null, 'session.secure' => false, 'session.same_site' => 'lax', 'session.partitioned' => false]);
        $cookies = app('cookie');
        $cookies->setDefaultPathAndDomain(config('session.path'), null, false, 'lax');

        try {
            return $next($request);
        } finally {
            config($previous);
            $cookies->setDefaultPathAndDomain(config('session.path'), $previous['session.domain'], $previous['session.secure'], $previous['session.same_site']);
        }
    }
}
