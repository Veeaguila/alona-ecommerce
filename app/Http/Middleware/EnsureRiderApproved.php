<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRiderApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($user->usertype !== 'rider') {
            return response()->json([
                'message' => 'Forbidden. This action is restricted to riders.',
            ], 403);
        }

        $rider = $user->rider;
        $status = $rider?->approval_status;
        $isActive = $rider?->is_active ?? false;

        if ($status !== 'approved' || ! $isActive) {
            if ($request->is('rider/*') && ! $request->expectsJson()) {
                return redirect()->route('application.status');
            }
            $currentStatus = $status ?? 'unapproved';
            $message = match ($currentStatus) {
                'pending' => 'Your rider account is still awaiting sorting center approval.',
                'rejected' => 'Your rider account registration was rejected.',
                default => $isActive ? 'Your rider account is not approved.' : 'Your rider account has been deactivated.',
            };

            return response()->json([
                'message' => $message,
                'approval_status' => $currentStatus,
                'is_active' => $isActive,
            ], 403);
        }

        if ($user->status !== 'approved') {
            if ($request->is('rider/*') && ! $request->expectsJson()) {
                return redirect()->route('application.status');
            }

            return response()->json(['message' => 'Your account is unavailable.'], 403);
        }

        if ($user->exists && $user->partnerApplication()->exists() && ! $user->hasVerifiedEmail()) {
            return $request->expectsJson() ? response()->json(['message' => 'Please verify your email before accepting deliveries.'], 403) : redirect()->route('application.status');
        }

        return $next($request);
    }
}
