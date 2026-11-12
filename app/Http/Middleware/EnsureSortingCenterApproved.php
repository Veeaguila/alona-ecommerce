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

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // A hub membership never grants company-wide operational/admin access.
        if (app(\App\Services\HubAccess::class)->membership($user)) {
            if ($request->is('sorting-center/order-management*')) {
                return redirect()->to('/sorting-center/hub-workspace' . ($request->getQueryString() ? '?' . $request->getQueryString() : ''));
            }
            if ($request->is('sorting-center/pickup-requests*') || $request->is('api/sorting-center/pickup-requests*') || $request->is('api/sorting-center/pickup-dispatch*')
                || $request->is('sorting-center/delivery-management*') || $request->is('api/sorting-center/parcels*') || $request->is('api/sorting-center/areas*')
                || $request->is('sorting-center/rider-assignment*') || $request->is('api/sorting-center/cod*') || $request->is('sorting-center/cod*')
                || $request->is('sorting-center/messages*') || $request->is('api/sorting-center/messages*')) {
                return $next($request);
            }
            abort(403, 'Use your assigned hub workspace. Company-wide operations require the company administrator.');
        }

        if ($user->usertype !== 'sorting_center') {
            if (! $request->expectsJson()) {
                abort(403, 'Forbidden. This action is restricted to sorting centers.');
            }
            return response()->json([
                'message' => 'Forbidden. This action is restricted to sorting centers.',
            ], 403);
        }

        $sortingCenter = $user->sortingCenter;
        $status = $sortingCenter?->approval_status;

        if ($status !== 'approved') {
            if ($request->is('sorting-center/*') && ! $request->expectsJson()) {
                return redirect()->route('application.status');
            }
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
            if ($request->is('sorting-center/*') && ! $request->expectsJson()) {
                return redirect()->route('application.status');
            }

            return response()->json(['message' => 'Your account is unavailable.'], 403);
        }

        if ($user->exists && $user->partnerApplication()->exists() && ! $user->hasVerifiedEmail()) {
            return $request->expectsJson() ? response()->json(['message' => 'Please verify your email before accessing operations.'], 403) : redirect()->route('application.status');
        }

        return $next($request);
    }
}
