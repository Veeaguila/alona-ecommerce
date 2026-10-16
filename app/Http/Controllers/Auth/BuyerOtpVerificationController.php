<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BuyerOtpVerificationController extends Controller
{
    /**
     * Display the 6-digit OTP email verification screen.
     */
    public function show(Request $request): Response
    {
        $email = $request->user()?->email
            ?? $request->query('email')
            ?? $request->session()->get('otp_verification_email');

        if ($email) {
            $email = Str::lower(trim((string) $email));
            $request->session()->put('otp_verification_email', $email);

            $targetUser = $request->user() ?? User::where('email', $email)->first();
            if ($targetUser && in_array($targetUser->usertype, ['buyer', 'seller'], true) && ! $targetUser->hasVerifiedEmail()) {
                if (! $targetUser->otp_code || ! $targetUser->otp_expires_at || now()->greaterThan($targetUser->otp_expires_at)) {
                    $targetUser->generateAndSendOtp();
                }
            }
        }

        return Inertia::render('Auth/VerifyOtp', [
            'email' => $email ?? '',
            'status' => session('status'),
        ]);
    }

    /**
     * Verify the 6-digit OTP code.
     *
     * @throws ValidationException
     */
    public function verify(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $email = $request->user()?->email
            ?? $request->input('email')
            ?? $request->session()->get('otp_verification_email');

        if ($email) {
            $request->merge(['email' => Str::lower(trim((string) $email))]);
        }

        if ($request->has('otp')) {
            $request->merge(['otp' => trim((string) $request->input('otp'))]);
        }

        $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string', 'regex:/^\d{6}$/'],
        ], [
            'otp.required' => 'Please enter the 6-digit verification code.',
            'otp.regex' => 'The verification code must be a 6-digit number.',
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'otp' => 'Invalid verification code.',
            ]);
        }

        if ($user->hasVerifiedEmail() && $user->status === 'approved') {
            return redirect()
                ->route('login')
                ->with('status', 'Your email is already verified. Please log in.');
        }

        if (! $user->otp_code || $user->otp_code !== $request->input('otp')) {
            throw ValidationException::withMessages([
                'otp' => 'Invalid verification code.',
            ]);
        }

        if (! $user->otp_expires_at || now()->greaterThan($user->otp_expires_at)) {
            throw ValidationException::withMessages([
                'otp' => 'OTP has expired. Please request a new code.',
            ]);
        }

        $isSeller = $user->usertype === 'seller';
        $newStatus = $user->usertype === 'buyer' ? 'approved' : ($isSeller ? 'pending' : $user->status);

        $user->forceFill([
            'email_verified_at' => now(),
            'status' => $newStatus,
            'otp_code' => null,
            'otp_expires_at' => null,
        ])->save();

        event(new Verified($user));
        $request->session()->forget('otp_verification_email');

        \Illuminate\Support\Facades\Auth::login($user);
        $request->session()->regenerate();

        $destinationRoute = $isSeller ? 'application.status' : 'buyer.dashboard';
        $message = $isSeller
            ? 'Verification succeeds. Your application is waiting for administrator approval.'
            : 'Account created and verified successfully! Welcome to Alona.';

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $message,
                'redirect' => route($destinationRoute),
            ]);
        }

        return redirect()
            ->route($destinationRoute)
            ->with('status', $message);
    }

    /**
     * Resend a new 6-digit OTP code and invalidate the previous code.
     *
     * @throws ValidationException
     */
    public function resend(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $email = $request->user()?->email
            ?? $request->input('email')
            ?? $request->session()->get('otp_verification_email');

        if ($email) {
            $request->merge(['email' => Str::lower(trim((string) $email))]);
        }

        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'We could not find an account with that email address.',
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Your email is already verified. Please log in.',
                ]);
            }

            return redirect()->route('login')->with('status', 'Your email is already verified. Please log in.');
        }

        $user->generateAndSendOtp();
        $request->session()->put('otp_verification_email', $user->email);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'A new 6-digit verification code has been sent to your email address.',
            ]);
        }

        return back()->with(
            'status',
            'A new 6-digit verification code has been sent to your email address.'
        );
    }
}

