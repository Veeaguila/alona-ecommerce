<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AccountDestination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if (($request->user()->partnerApplication?->details['registration_version'] ?? null) === 2) {
            return redirect()->route('application.status')->withErrors(['otp' => 'Use Resend code to request a new six-digit code.']);
        }
        if ($request->user()->hasVerifiedEmail()) {
            if (in_array($request->user()->usertype, ['sorting_center', 'rider'], true)) {
                return redirect()->route(app(AccountDestination::class)->route($request->user()));
            }

            return redirect()->intended(route('dashboard', absolute: false));
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            return back()->withErrors(['email' => 'The verification email could not be sent. Please try again later.']);
        }

        return back()->with('status', 'verification-link-sent');
    }
}
