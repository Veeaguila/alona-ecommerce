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

        $user->forceFill([
            'email_verified_at' => now(),
            'status' => $user->usertype === 'buyer' ? 'approved' : $user->status,
            'otp_code' => null,
            'otp_expires_at' => null,
        ])->save();

        event(new Verified($user));
        $request->session()->forget('otp_verification_email');

        if ($request->wantsJson()) {
            \Illuminate\Support\Facades\Auth::login($user);
            $request->session()->regenerate();

            return response()->json([
                'status' => 'success',
                'message' => 'Account created and verified successfully! Welcome to Alona.',
                'redirect' => route('buyer.dashboard'),
            ]);
        }

        if ($request->user()?->is($user)) {
            return redirect()
                ->route('buyer.dashboard')
                ->with('status', 'Account created and verified successfully! Welcome to Alona.');
        }

        return redirect()
            ->route('login')
            ->with('status', 'Account created and verified successfully! Welcome to Alona. You may now log in.');
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

