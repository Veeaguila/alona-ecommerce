<?php

namespace App\Http\Controllers;

use App\Models\PartnerApplication;
use App\Models\SellerNotification;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class SellerAccountController extends Controller
{
    /**
     * Display the Seller Account & Security management portal.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $partnerApp = $user->partnerApplication;

        $govIdPath = $user->id_path
            ?: ($partnerApp?->documents['government_id'] ?? $partnerApp?->documents['id'] ?? null);

        $permitPath = $user->business_permit_path
            ?: ($partnerApp?->documents['business_permit'] ?? null);

        return Inertia::render('Seller/Account', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'account' => [
                'id' => $user->id,
                'name' => $user->name,
                'first_name' => $user->first_name,
                'middle_initial' => $user->middle_initial,
                'last_name' => $user->last_name,
                'sex' => $user->sex,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'contact_no' => $user->contact_no,
                'birthday' => $user->birthday ? Carbon::parse($user->birthday)->format('Y-m-d') : '',
                'age' => $user->age,
                'address' => $user->address,
                'province' => $user->province,
                'municipality' => $user->municipality,
                'barangay' => $user->barangay,
                'street_address' => $user->street_address,
                'avatar' => $user->avatar,
                'store_name' => $user->store_name,
                'line_of_business' => $user->line_of_business,
                'status' => $user->status,
                'rejection_reason' => $user->rejection_reason,
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
            ],
            'verification' => [
                'status' => $user->status ?? 'pending',
                'rejection_reason' => $user->rejection_reason,
                'line_of_business' => $user->line_of_business ?? '',
                'has_government_id' => (bool) $govIdPath,
                'has_business_permit' => (bool) $permitPath,
                'government_id_url' => $govIdPath ? route('partner.documents', [$user, 'government_id']) : null,
                'business_permit_url' => $permitPath ? route('partner.documents', [$user, 'business_permit']) : null,
                'government_id_filename' => $govIdPath ? basename($govIdPath) : null,
                'business_permit_filename' => $permitPath ? basename($permitPath) : null,
                'last_updated_at' => ($partnerApp?->updated_at ?? $user->updated_at)?->format('M d, Y h:i A'),
            ],
            'sessions' => $this->getSessions($request),
        ]);
    }

    /**
     * Update the seller's personal profile, contact details, avatar, and address.
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_initial' => ['nullable', 'string', 'max:5'],
            'last_name' => ['required', 'string', 'max:255'],
            'sex' => ['nullable', 'string', 'in:Male,Female,Other'],
            'birthday' => ['nullable', 'date', 'before:today'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'contact_no' => ['required', 'string', 'max:30'],
            'province' => ['nullable', 'string', 'max:255'],
            'municipality' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'street_address' => ['nullable', 'string', 'max:1000'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $middle = isset($validated['middle_initial']) && trim($validated['middle_initial']) !== ''
            ? rtrim(trim($validated['middle_initial']), '.') . '.'
            : '';

        $fullName = implode(' ', array_filter([
            trim($validated['first_name']),
            $middle,
            trim($validated['last_name']),
        ]));

        $fullAddress = implode(', ', array_filter([
            $validated['street_address'] ?? null,
            $validated['barangay'] ?? null,
            $validated['municipality'] ?? null,
            $validated['province'] ?? null,
        ]));

        $data = [
            'name' => $fullName,
            'first_name' => trim($validated['first_name']),
            'middle_initial' => isset($validated['middle_initial']) && trim($validated['middle_initial']) !== ''
                ? substr(trim(rtrim($validated['middle_initial'], '.')), 0, 1)
                : null,
            'last_name' => trim($validated['last_name']),
            'sex' => $validated['sex'] ?: $user->sex,
            'email' => $validated['email'],
            'contact_no' => trim($validated['contact_no']),
            'province' => $validated['province'] ?? $user->province,
            'municipality' => $validated['municipality'] ?? $user->municipality,
            'barangay' => $validated['barangay'] ?? $user->barangay,
            'street_address' => $validated['street_address'] ?? $user->street_address,
        ];

        if ($fullAddress !== '') {
            $data['address'] = $fullAddress;
        }

        if (!empty($validated['birthday'])) {
            $data['birthday'] = $validated['birthday'];
            $data['age'] = Carbon::parse($validated['birthday'])->age;
        }

        if ($request->hasFile('profile_photo')) {
            $data['avatar'] = $request->file('profile_photo')->store('profile-photos', 'public');
        }

        if ($data['email'] !== $user->email) {
            $user->email_verified_at = null;
        }

        $user->fill($data);
        $user->save();

        return Redirect::route('seller.account')->with(
            'status',
            'Seller profile and address details updated successfully.'
        );
    }

    /**
     * Update seller business verification documents and line of business (SELLER-67).
     */
    public function updateDocuments(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'line_of_business' => ['required', 'string', 'max:255'],
            'business_permit' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'government_id' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $userUpdates = [
            'line_of_business' => trim($validated['line_of_business']),
        ];

        $uploadedDocs = [];

        if ($request->hasFile('business_permit')) {
            $permitPath = $request->file('business_permit')->store('seller-business-permits', 'local');
            $userUpdates['business_permit_path'] = $permitPath;
            $uploadedDocs['business_permit'] = $permitPath;
        }

        if ($request->hasFile('government_id')) {
            $idPath = $request->file('government_id')->store('seller-ids', 'local');
            $userUpdates['id_path'] = $idPath;
            $uploadedDocs['government_id'] = $idPath;
        }

        // If seller was previously rejected and re-uploads documents, resubmit for admin review
        if ($user->status === 'rejected' && !empty($uploadedDocs)) {
            $userUpdates['status'] = 'pending';
            $userUpdates['rejection_reason'] = null;
        }

        DB::transaction(function () use ($user, $userUpdates, $uploadedDocs) {
            $user->update($userUpdates);

            $existingApp = $user->partnerApplication;
            $existingDetails = $existingApp?->details ?? [];
            $existingDocs = $existingApp?->documents ?? [];

            if ($user->id_path && empty($existingDocs['government_id'])) {
                $existingDocs['government_id'] = $user->id_path;
            }
            if ($user->business_permit_path && empty($existingDocs['business_permit'])) {
                $existingDocs['business_permit'] = $user->business_permit_path;
            }

            PartnerApplication::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'role' => 'seller',
                    'details' => array_merge($existingDetails, [
                        'store_name' => $user->store_name,
                        'line_of_business' => $userUpdates['line_of_business'],
                        'contact_no' => $user->contact_no,
                    ]),
                    'address' => $existingApp?->address ?? [
                        'province' => $user->province,
                        'municipality' => $user->municipality,
                        'barangay' => $user->barangay,
                        'street_address' => $user->street_address,
                    ],
                    'documents' => array_merge($existingDocs, $uploadedDocs),
                    'terms_accepted_at' => $existingApp?->terms_accepted_at ?? now(),
                    'privacy_accepted_at' => $existingApp?->privacy_accepted_at ?? now(),
                ]
            );

            if (!empty($uploadedDocs)) {
                SellerNotification::create([
                    'user_id' => $user->id,
                    'type' => 'verification',
                    'title' => 'Business Verification Documents Updated',
                    'message' => 'Your updated business verification documents (' . implode(', ', array_map(fn ($k) => str_replace('_', ' ', $k), array_keys($uploadedDocs))) . ') have been saved and synced with Platform Compliance.',
                    'url' => route('seller.account', [], false),
                ]);
            }
        });

        return Redirect::route('seller.account')->with(
            'status',
            'Business verification details and compliance documents updated successfully.'
        );
    }

    /**
     * Update the seller's password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return Redirect::route('seller.account')->with(
            'status',
            'Account password updated successfully.'
        );
    }

    /**
     * Revoke a single login session (SELLER-66).
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

        return Redirect::route('seller.account')->with(
            'status',
            'Login session revoked successfully.'
        );
    }

    /**
     * Revoke all other login sessions except the current device (SELLER-66).
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

        return Redirect::route('seller.account')->with(
            'status',
            'Logged out of all other active browser sessions.'
        );
    }

    /**
     * Delete the seller's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Get active login sessions for the authenticated seller (SELLER-66).
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
}
