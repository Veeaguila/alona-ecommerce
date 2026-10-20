<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Notifications\EmailChangeOtpNotification;
use App\Notifications\PhoneChangeOtpNotification;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form and active sessions.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' =>
                $request->user() instanceof MustVerifyEmail,

            'status' => session('status'),
            'sessions' => $this->getSessions($request),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(
        ProfileUpdateRequest $request
    ): RedirectResponse {
        $user = $request->user();

        $data = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Profile avatar upload (BUY-ACCT-001)
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('profile_photo')) {
            $data['avatar'] = $request->file('profile_photo')->store('avatars', 'public');
        } elseif ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        unset($data['profile_photo']);

        /*
        |--------------------------------------------------------------------------
        | Remove uploaded ID from direct user assignment.
        |--------------------------------------------------------------------------
        */

        unset($data['id']);

        /*
        |--------------------------------------------------------------------------
        | Build full name.
        |--------------------------------------------------------------------------
        */

        $middleInitial = trim(
            (string) ($data['middle_initial'] ?? '')
        );

        $data['name'] = trim(
            ($data['first_name'] ?? '') .
            ' ' .
            (
                $middleInitial !== ''
                    ? $middleInitial . '. '
                    : ''
            ) .
            ($data['last_name'] ?? '')
        );

        /*
        |--------------------------------------------------------------------------
        | Calculate age from birthday.
        |--------------------------------------------------------------------------
        */

        if (!empty($data['birthday'])) {
            $data['age'] = Carbon::parse(
                $data['birthday']
            )->age;
        }

        /*
        |--------------------------------------------------------------------------
        | Build full address for the users table.
        |--------------------------------------------------------------------------
        */

        $street = trim(
            (string) ($data['street_address'] ?? '')
        );

        $barangay = trim(
            (string) ($data['barangay'] ?? '')
        );

        $municipality = trim(
            (string) ($data['municipality'] ?? '')
        );

        $province = trim(
            (string) ($data['province'] ?? '')
        );

        $fullAddress = collect([
            $street,
            $barangay,
            $municipality,
            $province,
        ])
            ->filter(
                fn ($value) =>
                    $value !== ''
            )
            ->implode(', ');

        $data['address'] = $fullAddress;

        /*
        |--------------------------------------------------------------------------
        | Replace uploaded ID when a new file is provided.
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('id')) {
            $data['id_path'] =
                $request->file('id')->store('buyer-ids');
        }

        /*
        |--------------------------------------------------------------------------
        | Email verification challenge (BUY-ACCT-006 & BUY-ACCT-007)
        |--------------------------------------------------------------------------
        | Changing email does NOT directly update the users.email database record.
        | Instead, it triggers a 6-digit OTP challenge to the pending new address.
        |--------------------------------------------------------------------------
        */

        $emailChanged = false;
        $submittedEmail = Str::lower(trim((string) ($data['email'] ?? '')));

        if ($submittedEmail !== '' && $submittedEmail !== Str::lower((string) $user->email)) {
            $emailChanged = true;
            $emailOtp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            $user->pending_email = $submittedEmail;
            $user->pending_email_otp = $emailOtp;
            $user->pending_email_otp_expires_at = now()->addMinutes(5);

            try {
                Notification::route('mail', $submittedEmail)
                    ->notify(new EmailChangeOtpNotification($emailOtp, $user));
            } catch (\Throwable $e) {
                Log::warning('Email change OTP failed to dispatch mail: ' . $e->getMessage());
            }

            try {
                if (method_exists($user, 'buyerNotifications')) {
                    $user->buyerNotifications()->create([
                        'type' => 'email_change_otp',
                        'title' => 'Email Verification Code',
                        'message' => "Your verification code to update your email to {$submittedEmail} is {$emailOtp} (valid for 5 minutes).",
                        'is_read' => false,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('In-app email change notification failed: ' . $e->getMessage());
            }
        }

        unset($data['email']);

        /*
        |--------------------------------------------------------------------------
        | Phone verification challenge (BUY-ACCT-009)
        |--------------------------------------------------------------------------
        | Changing phone number does NOT directly update the users.contact_no record.
        | Instead, it triggers a 6-digit OTP verification challenge.
        |--------------------------------------------------------------------------
        */

        $phoneChanged = false;
        $submittedPhone = trim((string) ($data['contact_no'] ?? ''));

        if ($submittedPhone !== '' && $submittedPhone !== (string) $user->contact_no) {
            $existingPhone = User::where('id', '!=', $user->id)
                ->where(function ($query) use ($submittedPhone) {
                    $clean = ltrim($submittedPhone, '+');
                    $query->where('contact_no', $submittedPhone)
                        ->orWhere('contact_no', 'like', "%{$clean}");
                })
                ->exists();

            if ($existingPhone) {
                throw ValidationException::withMessages([
                    'contact_no' => 'This mobile number is already in use by another account.',
                ]);
            }

            $phoneChanged = true;
            $phoneOtp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            $user->pending_phone = $submittedPhone;
            $user->pending_phone_otp = $phoneOtp;
            $user->pending_phone_otp_expires_at = now()->addMinutes(5);

            try {
                $user->notify(new PhoneChangeOtpNotification($phoneOtp, $submittedPhone));
            } catch (\Throwable $e) {
                Log::warning('Phone change OTP notification failed: ' . $e->getMessage());
            }

            try {
                if (method_exists($user, 'buyerNotifications')) {
                    $user->buyerNotifications()->create([
                        'type' => 'phone_change_otp',
                        'title' => 'Phone Verification Code',
                        'message' => "Your verification code to update your phone number to {$submittedPhone} is {$phoneOtp} (valid for 5 minutes).",
                        'is_read' => false,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('In-app phone change notification failed: ' . $e->getMessage());
            }
        }

        unset($data['contact_no']);

        /*
        |--------------------------------------------------------------------------
        | Update user.
        |--------------------------------------------------------------------------
        */

        $user->fill($data);
        $user->save();

        /*
        |--------------------------------------------------------------------------
        | Synchronize Buyer Address Book.
        |--------------------------------------------------------------------------
        |
        | The registration/profile address is also stored in the
        | addresses table so that it appears in:
        |
        | Buyer → My Addresses
        | Buyer → Checkout
        |
        |--------------------------------------------------------------------------
        */

        if ($fullAddress !== '') {
            $defaultAddress = $user
                ->addresses()
                ->where('is_default', true)
                ->first();

            if ($defaultAddress) {
                $defaultAddress->update([
                    'label' => 'Home',
                    'address' => $fullAddress,
                    'is_default' => true,
                ]);
            } else {
                $user->addresses()->create([
                    'label' => 'Home',
                    'address' => $fullAddress,
                    'is_default' => true,
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Redirect according to the current ERP section with contextual status.
        |--------------------------------------------------------------------------
        */

        if ($emailChanged && $phoneChanged) {
            $statusMessage = 'Profile saved. Please verify the 6-digit codes sent for your new email and phone number.';
        } elseif ($emailChanged) {
            $statusMessage = 'Profile saved. Please enter the 6-digit verification code sent to your new email.';
        } elseif ($phoneChanged) {
            $statusMessage = 'Profile saved. Please enter the 6-digit verification code sent for your new phone number.';
        } else {
            $statusMessage = 'Profile information saved successfully.';
        }

        if ($request->routeIs('buyer.account.update')) {
            return Redirect::route('buyer.account')->with('status', $statusMessage);
        }

        return Redirect::route('profile.edit')->with('status', $statusMessage);
    }

    /**
     * Verify email change OTP (BUY-ACCT-006 & BUY-ACCT-007).
     */
    public function verifyEmailChange(Request $request): RedirectResponse
    {
        $request->validate([
            'otp' => ['required', 'string', 'regex:/^\d{6}$/'],
        ], [
            'otp.required' => 'Please enter the 6-digit verification code.',
            'otp.regex' => 'The verification code must be a 6-digit number.',
        ]);

        $user = $request->user();

        if (! $user->pending_email || ! $user->pending_email_otp) {
            throw ValidationException::withMessages([
                'otp' => 'No pending email change request was found.',
            ]);
        }

        if ($user->pending_email_otp !== trim((string) $request->otp)) {
            throw ValidationException::withMessages([
                'otp' => 'Invalid verification code.',
            ]);
        }

        if (! $user->pending_email_otp_expires_at || now()->greaterThan($user->pending_email_otp_expires_at)) {
            throw ValidationException::withMessages([
                'otp' => 'The verification code has expired. Please request a new code.',
            ]);
        }

        $newEmail = $user->pending_email;

        // Ensure email was not claimed in the interim
        $taken = User::where('id', '!=', $user->id)->where('email', $newEmail)->exists();
        if ($taken) {
            $user->forceFill([
                'pending_email' => null,
                'pending_email_otp' => null,
                'pending_email_otp_expires_at' => null,
            ])->save();

            throw ValidationException::withMessages([
                'otp' => 'The email has already been taken by another account.',
            ]);
        }

        $user->forceFill([
            'email' => $newEmail,
            'email_verified_at' => now(),
            'pending_email' => null,
            'pending_email_otp' => null,
            'pending_email_otp_expires_at' => null,
        ])->save();

        return back()->with('status', 'Email address verified and updated successfully.');
    }

    /**
     * Resend email change OTP.
     */
    public function resendEmailChangeOtp(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->pending_email) {
            throw ValidationException::withMessages([
                'otp' => 'No pending email change request was found.',
            ]);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->forceFill([
            'pending_email_otp' => $otp,
            'pending_email_otp_expires_at' => now()->addMinutes(5),
        ])->save();

        try {
            Notification::route('mail', $user->pending_email)
                ->notify(new EmailChangeOtpNotification($otp, $user));
        } catch (\Throwable $e) {
            Log::warning('Resend email OTP failed to send email: ' . $e->getMessage());
        }

        try {
            if (method_exists($user, 'buyerNotifications')) {
                $user->buyerNotifications()->create([
                    'type' => 'email_change_otp',
                    'title' => 'Email Verification Code Resent',
                    'message' => "Your new verification code to update your email to {$user->pending_email} is {$otp} (valid for 5 minutes).",
                    'is_read' => false,
                ]);
            }
        } catch (\Throwable $e) {}

        return back()->with('status', 'A new 6-digit verification code has been sent to your new email.');
    }

    /**
     * Cancel pending email change.
     */
    public function cancelEmailChange(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill([
            'pending_email' => null,
            'pending_email_otp' => null,
            'pending_email_otp_expires_at' => null,
        ])->save();

        return back()->with('status', 'Pending email change cancelled.');
    }

    /**
     * Verify phone number change OTP (BUY-ACCT-009).
     */
    public function verifyPhoneChange(Request $request): RedirectResponse
    {
        $request->validate([
            'otp' => ['required', 'string', 'regex:/^\d{6}$/'],
        ], [
            'otp.required' => 'Please enter the 6-digit verification code.',
            'otp.regex' => 'The verification code must be a 6-digit number.',
        ]);

        $user = $request->user();

        if (! $user->pending_phone || ! $user->pending_phone_otp) {
            throw ValidationException::withMessages([
                'otp' => 'No pending phone number change request was found.',
            ]);
        }

        if ($user->pending_phone_otp !== trim((string) $request->otp)) {
            throw ValidationException::withMessages([
                'otp' => 'Invalid verification code.',
            ]);
        }

        if (! $user->pending_phone_otp_expires_at || now()->greaterThan($user->pending_phone_otp_expires_at)) {
            throw ValidationException::withMessages([
                'otp' => 'The verification code has expired. Please request a new code.',
            ]);
        }

        $newPhone = $user->pending_phone;

        $user->forceFill([
            'contact_no' => $newPhone,
            'pending_phone' => null,
            'pending_phone_otp' => null,
            'pending_phone_otp_expires_at' => null,
        ])->save();

        return back()->with('status', 'Phone number verified and updated successfully.');
    }

    /**
     * Resend phone change OTP.
     */
    public function resendPhoneChangeOtp(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->pending_phone) {
            throw ValidationException::withMessages([
                'otp' => 'No pending phone number change request was found.',
            ]);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->forceFill([
            'pending_phone_otp' => $otp,
            'pending_phone_otp_expires_at' => now()->addMinutes(5),
        ])->save();

        try {
            $user->notify(new PhoneChangeOtpNotification($otp, $user->pending_phone));
        } catch (\Throwable $e) {}

        try {
            if (method_exists($user, 'buyerNotifications')) {
                $user->buyerNotifications()->create([
                    'type' => 'phone_change_otp',
                    'title' => 'Phone Verification Code Resent',
                    'message' => "Your new verification code to update your phone to {$user->pending_phone} is {$otp} (valid for 5 minutes).",
                    'is_read' => false,
                ]);
            }
        } catch (\Throwable $e) {}

        return back()->with('status', 'A new 6-digit verification code has been generated.');
    }

    /**
     * Cancel pending phone change.
     */
    public function cancelPhoneChange(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill([
            'pending_phone' => null,
            'pending_phone_otp' => null,
            'pending_phone_otp_expires_at' => null,
        ])->save();

        return back()->with('status', 'Pending phone number change cancelled.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(
        Request $request
    ): RedirectResponse {
        $request->validate([
            'password' => [
                'required',
                'current_password',
            ],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Get active sessions for the authenticated user (BUYER-39).
     */
    protected function getSessions(Request $request): array
    {
        if (!Schema::hasTable('sessions')) {
            return [];
        }

        $currentSessionId = $request->session()->getId();

        return DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($session) use ($currentSessionId) {
                $isCurrent = $session->id === $currentSessionId;
                $userAgent = (string) ($session->user_agent ?? '');

                return [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address ?? 'Unknown IP',
                    'is_current_device' => $isCurrent,
                    'last_active' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                    'last_active_date' => Carbon::createFromTimestamp($session->last_activity)->format('M d, Y h:i A'),
                    'device_type' => $this->detectDeviceType($userAgent),
                    'platform' => $this->detectPlatform($userAgent),
                    'browser' => $this->detectBrowser($userAgent),
                ];
            })
            ->toArray();
    }

    protected function detectDeviceType(?string $userAgent): string
    {
        if (!$userAgent) {
            return 'desktop';
        }
        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i', $userAgent)) {
            return 'tablet';
        }
        if (preg_match('/(mobile|ipod|iphone|android|blackberry|iemobile|kindle|silk)/i', $userAgent)) {
            return 'mobile';
        }
        return 'desktop';
    }

    protected function detectPlatform(?string $userAgent): string
    {
        if (!$userAgent) {
            return 'Unknown OS';
        }
        if (preg_match('/windows nt 10/i', $userAgent)) {
            return 'Windows 10/11';
        }
        if (preg_match('/windows/i', $userAgent)) {
            return 'Windows';
        }
        if (preg_match('/macintosh|mac os x/i', $userAgent)) {
            return 'macOS';
        }
        if (preg_match('/iphone/i', $userAgent)) {
            return 'iOS (iPhone)';
        }
        if (preg_match('/ipad/i', $userAgent)) {
            return 'iPadOS';
        }
        if (preg_match('/android/i', $userAgent)) {
            return 'Android';
        }
        if (preg_match('/linux/i', $userAgent)) {
            return 'Linux';
        }
        return 'Unknown OS';
    }

    protected function detectBrowser(?string $userAgent): string
    {
        if (!$userAgent) {
            return 'Unknown Browser';
        }
        if (preg_match('/edg/i', $userAgent)) {
            return 'Microsoft Edge';
        }
        if (preg_match('/chrome|crios/i', $userAgent) && !preg_match('/opr|opera/i', $userAgent)) {
            return 'Google Chrome';
        }
        if (preg_match('/firefox|fxios/i', $userAgent)) {
            return 'Mozilla Firefox';
        }
        if (preg_match('/safari/i', $userAgent) && !preg_match('/chrome|crios/i', $userAgent)) {
            return 'Apple Safari';
        }
        if (preg_match('/opr|opera/i', $userAgent)) {
            return 'Opera';
        }
        return 'Web Browser';
    }

    /**
     * Revoke a single session (BUYER-39).
     */
    public function revokeSession(Request $request, string $sessionId): RedirectResponse
    {
        if (Schema::hasTable('sessions')) {
            $isCurrent = $sessionId === $request->session()->getId();

            DB::table('sessions')
                ->where('user_id', $request->user()->id)
                ->where('id', $sessionId)
                ->delete();

            if ($isCurrent) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return Redirect::to('/');
            }
        }

        return back()->with('status', 'Login session revoked successfully.');
    }

    /**
     * Revoke all other sessions except the current one (BUYER-39).
     */
    public function revokeOtherSessions(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')
                ->where('user_id', $request->user()->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        return back()->with('status', 'Logged out of all other active browser sessions.');
    }
}