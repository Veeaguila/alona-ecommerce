<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSortingCenterApproved
{
    /**
     * Handle an incoming request.
     *
     * Ensures the authenticated user belongs to a sorting center
     * and their approval_status in the sorting_centers table is 'approved'.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($user->usertype !== 'sorting_center') {
            return response()->json([
                'message' => 'Forbidden. This action is restricted to sorting centers.',
            ], 403);
        }

        $sortingCenter = $user->sortingCenter;
        $status = $sortingCenter?->approval_status;

        if ($status !== 'approved') {
            $currentStatus = $status ?? 'unapproved';
            $message = match ($currentStatus) {
                'pending' => 'Your sorting center account is still awaiting administrator approval.',
                'rejected' => 'Your sorting center account registration was rejected.',
                default => 'Your sorting center account is not approved.',
            };

            return response()->json([
                'message' => $message,
                'approval_status' => $currentStatus,
            ], 403);
        }

        if ($user->status !== 'approved') {
            return response()->json(['message' => 'Your account is unavailable.'], 403);
        }

        return $next($request);
    }
}
