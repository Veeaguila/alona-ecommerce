<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PhilippineAddressService;
use Carbon\Carbon;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class BuyerAuthController extends Controller
{
    /**
     * Buyer Login with Sanctum Token Generation.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = Str::lower(trim((string) $request->input('email')));
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'message' => 'The provided credentials do not match our records.',
                'errors' => [
                    'email' => ['Invalid email or password.'],
                ],
            ], 422);
        }

        if ($user->usertype !== 'buyer') {
            return response()->json([
                'message' => 'Access denied. Only Buyer accounts can log in here.',
            ], 403);
        }

        if (!in_array($user->status, ['approved', 'unverified'], true)) {
            return response()->json(['message' => 'Your Buyer account is restricted.'], 403);
        }

        if ($user->status === 'unverified' || ! $user->hasVerifiedEmail()) {
            // Re-send OTP if needed
            if (! $user->otp_code || ! $user->otp_expires_at || now()->greaterThan($user->otp_expires_at)) {
                $user->generateAndSendOtp();
            }

            return response()->json([
                'message' => 'Please verify your email address to continue.',
                'needs_verification' => true,
                'email' => $user->email,
            ], 403);
        }

        if ($user->status === 'suspended' || $user->status === 'rejected') {
            return response()->json([
                'message' => 'Your account is unavailable. Reason: ' . ($user->rejection_reason ?? 'Account restricted.'),
            ], 403);
        }

        // Issue Sanctum token for mobile
        $token = $user->createToken('buyer-mobile-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => $this->formatUser($user),
        ]);
    }

    /**
     * Buyer Registration with 6-digit OTP dispatch.
     */
    public function register(Request $request): JsonResponse
    {
        if ($request->filled('birthday') && ! $request->filled('age')) {
            try {
                $request->merge(['age' => Carbon::parse($request->input('birthday'))->age]);
            } catch (\Throwable) {
                // Handled by validation
            }
        }

        if ($request->has('email')) {
            $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        }

        if ($request->has('contact_no')) {
            $contact = trim((string) $request->input('contact_no'));
            if (preg_match('/^(?:\+63|63)(9\d{9})$/', $contact, $m)) {
                $contact = '0' . $m[1];
            }
            $request->merge(['contact_no' => $contact]);
        }

        $hasAddressGroup = $request->filled('address_group');

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'min:2', 'max:50', "regex:/^[\pL\s\-'.]+$/u"],
            'last_name' => ['required', 'string', 'min:2', 'max:50', "regex:/^[\pL\s\-'.]+$/u"],
            'middle_name' => ['nullable', 'string', 'max:50', "regex:/^[\pL\s\-'.]+$/u"],
            'suffix' => ['nullable', 'string', 'max:20', "regex:/^[\pL\s\-'.]+$/u"],
            'sex' => ['nullable', 'in:Male,Female,Other'],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'contact_no' => [
                'required',
                'string',
                'regex:/^09\d{9}$/',
                'unique:users,contact_no',
            ],
            'birthday' => ['required', 'date', 'before_or_equal:' . now()->subYears(18)->toDateString()],
            'age' => ['nullable', 'integer', 'min:18', 'max:120'],

            'address_group' => ['nullable', Rule::in(array_keys(PhilippineAddressService::GROUPS))],
            'region_code' => [$hasAddressGroup ? 'required' : 'nullable', 'regex:/^\d{10}$/'],
            'province_code' => ['nullable', 'regex:/^\d{10}$/'],
            'city_code' => [$hasAddressGroup ? 'required' : 'nullable', 'regex:/^\d{10}$/'],
            'barangay_code' => [$hasAddressGroup ? 'required' : 'nullable', 'regex:/^\d{10}$/'],
            'street_address' => [$hasAddressGroup ? 'required' : 'nullable', 'string', 'max:1000'],

            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $address = null;
        if ($hasAddressGroup) {
            $address = app(PhilippineAddressService::class)->validate($request->all());
            $request->merge([
                'province' => $address['province'],
                'municipality' => $address['city'],
                'barangay' => $address['barangay'],
            ]);
        }

        $middleName = trim((string) ($request->input('middle_name') ?: ''));
        $middleInitial = $middleName !== '' ? mb_substr($middleName, 0, 1) : null;
        $suffix = trim((string) ($request->input('suffix') ?? ''));

        $nameParts = array_filter([
            trim((string) $request->first_name),
            $middleName !== '' ? (mb_strlen($middleName) === 1 ? $middleName . '.' : $middleName) : null,
            trim((string) $request->last_name),
            $suffix !== '' ? $suffix : null,
        ]);
        $fullName = implode(' ', $nameParts);

        $user = User::create([
            'name' => $fullName,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'middle_name' => $middleName !== '' ? $middleName : null,
            'middle_initial' => $middleInitial,
            'suffix' => $suffix !== '' ? $suffix : null,
            'sex' => $request->sex,
            'email' => $request->email,
            'contact_no' => $request->contact_no,
            'birthday' => $request->birthday,
            'age' => Carbon::parse($request->birthday)->age,

            'address' => $hasAddressGroup
                ? implode(', ', array_filter([
                    $request->street_address,
                    $request->barangay,
                    $request->municipality,
                    $request->province,
                ]))
                : null,
            'address_geography' => $address
                ? collect($address)->only(['address_group', 'region_code', 'province_code', 'city_code', 'barangay_code', 'psgc_version'])->all()
                : null,
            'province' => $hasAddressGroup ? $request->province : null,
            'municipality' => $hasAddressGroup ? $request->municipality : null,
            'barangay' => $hasAddressGroup ? $request->barangay : null,
            'street_address' => $hasAddressGroup ? $request->street_address : null,

            'usertype' => 'buyer',
            'status' => 'unverified',
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));
        $user->generateAndSendOtp();

        return response()->json([
            'status' => 'success',
            'message' => 'Account created successfully! A 6-digit verification code has been sent to your email.',
            'email' => $user->email,
        ], 201);
    }

    /**
     * Verify the 6-digit OTP code and return active Sanctum token.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'otp' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        $email = Str::lower(trim((string) $request->input('email')));
        return \Illuminate\Support\Facades\DB::transaction(function () use ($request, $email): JsonResponse {
            $user = User::where('usertype', 'buyer')->where('email', $email)->lockForUpdate()->first();
            abort_unless($user, 404, 'Invalid email address.');
            abort_unless(in_array($user->status, ['unverified', 'approved'], true), 403, 'This Buyer account is restricted.');
            if ($user->hasVerifiedEmail()) {
                throw ValidationException::withMessages(['otp' => 'Your email is already verified. Sign in with your password.']);
            }
            if (!is_string($user->otp_code) || !hash_equals($user->otp_code, $request->input('otp'))) {
                throw ValidationException::withMessages(['otp' => 'Invalid verification code.']);
            }
            if (!$user->otp_expires_at || now()->greaterThanOrEqualTo($user->otp_expires_at)) {
                throw ValidationException::withMessages(['otp' => 'Verification code has expired. Please request a new code.']);
            }
            $user->forceFill(['email_verified_at' => now(), 'status' => 'approved', 'otp_code' => null, 'otp_expires_at' => null])->save();
            event(new Verified($user));
            return response()->json(['message' => 'Email verified successfully!',
                'token' => $user->createToken('buyer-mobile-token')->plainTextToken, 'user' => $this->formatUser($user)]);
        });
    }

    /**
     * Resend the 6-digit OTP verification code.
     */
    public function resendOtp(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $email = Str::lower(trim((string) $request->input('email')));
        $user = User::where('usertype', 'buyer')->where('email', $email)->first();

        if (! $user) {
            return response()->json(['message' => 'No account found with this email.'], 404);
        }

        abort_unless(in_array($user->status, ['unverified', 'approved'], true), 403, 'This Buyer account is restricted.');
        if ($user->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['email' => 'Your email is already verified. Sign in with your password.']);
        }

        $user->generateAndSendOtp();

        return response()->json([
            'message' => 'A new 6-digit verification code has been sent to your email.',
            'email' => $user->email,
        ]);
    }

    /**
     * Get current authenticated buyer details.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['addresses' => fn ($q) => $q->orderByDesc('is_default')]);

        return response()->json([
            'user' => $this->formatUser($user),
            'addresses' => $user->addresses,
            'cart_count' => $user->cartItems()->sum('quantity'),
            'unread_notifications' => $user->buyerNotifications()->whereNull('read_at')->count(),
        ]);
    }

    /**
     * Update buyer profile info.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'min:2', 'max:50'],
            'last_name' => ['required', 'string', 'min:2', 'max:50'],
            'middle_name' => ['nullable', 'string', 'max:50'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'sex' => ['nullable', 'in:Male,Female,Other'],
            'contact_no' => ['required', 'string', 'regex:/^09\d{9}$/', Rule::unique('users', 'contact_no')->ignore($user->id)],
            'birthday' => ['nullable', 'date', 'before_or_equal:' . now()->subYears(18)->toDateString()],
            'avatar' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        $user->first_name = $validated['first_name'];
        $user->last_name = $validated['last_name'];
        $user->middle_name = $validated['middle_name'] ?? null;
        $user->suffix = $validated['suffix'] ?? null;
        $user->sex = $validated['sex'] ?? $user->sex;
        $user->contact_no = $validated['contact_no'];

        if (! empty($validated['birthday'])) {
            $user->birthday = $validated['birthday'];
            $user->age = Carbon::parse($validated['birthday'])->age;
        }

        $nameParts = array_filter([
            $user->first_name,
            $user->middle_name,
            $user->last_name,
            $user->suffix,
        ]);
        $user->name = implode(' ', $nameParts);
        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $this->formatUser($user),
        ]);
    }

    /**
     * Change buyer password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'The provided current password is incorrect.',
                'errors' => ['current_password' => ['Incorrect current password.']],
            ], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return response()->json(['message' => 'Password updated successfully.']);
    }

    /**
     * Logout and revoke current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Successfully logged out.']);
    }

    /**
     * Buyer Google Sign-In / Token Exchange.
     */
    public function googleLogin(Request $request): JsonResponse
    {
        // The imported app has no Google provider/token-verification integration.
        // Never treat a client-supplied email or identity string as authentication.
        return response()->json(['message' => 'Google sign-in is not available in this app yet. Use email and password, or reset your password.'], 503);
    }

    /**
     * Send password reset link to buyer email.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $email = Str::lower(trim((string) $request->input('email')));
        $user = User::where('usertype', 'buyer')->where('email', $email)->first();

        if (! $user) {
            return response()->json([
                'message' => 'We could not find an active buyer account with that email address.',
            ], 404);
        }

        $status = \Illuminate\Support\Facades\Password::sendResetLink(['email' => $email]);

        if ($status === \Illuminate\Support\Facades\Password::RESET_LINK_SENT) {
            return response()->json([
                'status' => 'success',
                'message' => 'Password reset link sent to your email successfully.',
            ]);
        }

        return response()->json([
            'message' => __($status),
        ], 422);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'middle_name' => $user->middle_name,
            'suffix' => $user->suffix,
            'email' => $user->email,
            'contact_no' => $user->contact_no,
            'sex' => $user->sex,
            'birthday' => $user->birthday ? Carbon::parse($user->birthday)->format('Y-m-d') : null,
            'age' => $user->age,
            'avatar_url' => $user->avatar ? asset('storage/' . $user->avatar) : null,
            'address' => $user->address,
            'status' => $user->status,
            'role' => $user->usertype,
        ];
    }
}

