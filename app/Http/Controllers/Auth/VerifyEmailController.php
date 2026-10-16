<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AccountDestination;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if (($request->user()->partnerApplication?->details['registration_version'] ?? null) === 2) {
            return redirect()->route('application.status')->withErrors(['otp' => 'Enter the six-digit code sent to your email.']);
        }
        if (in_array($request->user()->usertype, ['sorting_center', 'rider'], true)) {
            if ($request->user()->markEmailAsVerified()) {
                event(new Verified($request->user()));
            }

            return redirect()->route(app(AccountDestination::class)->route($request->user()));
        }
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}
