<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AccountDestination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        if ($request->routeIs('api.logistics.verification.resend')) {
            abort_unless($request->user()->usertype === 'sorting_center', 403);
        }
        if (($request->user()->partnerApplication?->details['registration_version'] ?? null) === 2) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Use Resend code to request a new six-digit code.'], 422);
            }
            return redirect()->route('application.status')->withErrors(['otp' => 'Use Resend code to request a new six-digit code.']);
        }
        if ($request->user()->hasVerifiedEmail()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Email already verified.']);
            }
            if (in_array($request->user()->usertype, ['sorting_center', 'rider'], true)) {
                return redirect()->route(app(AccountDestination::class)->route($request->user()));
            }

            return redirect()->intended(route('dashboard', absolute: false));
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'The verification email could not be sent. Please try again later.'], 503);
            }
            return back()->withErrors(['email' => 'The verification email could not be sent. Please try again later.']);
        }

        return $request->expectsJson()
            ? response()->json(['message' => 'Verification email sent.'])
            : back()->with('status', 'verification-link-sent');
    }
}
