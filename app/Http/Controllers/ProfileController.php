<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
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
        | Email verification reset.
        |--------------------------------------------------------------------------
        */

        if (
            isset($data['email']) &&
            $data['email'] !== $user->email
        ) {
            $user->email_verified_at = null;
        }

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
                /*
                |------------------------------------------------------------------
                | Update existing default address.
                |------------------------------------------------------------------
                */

                $defaultAddress->update([
                    'label' => 'Home',
                    'address' => $fullAddress,
                    'is_default' => true,
                ]);
            } else {
                /*
                |------------------------------------------------------------------
                | No default address exists yet.
                | Create one automatically.
                |------------------------------------------------------------------
                */

                $user->addresses()->create([
                    'label' => 'Home',
                    'address' => $fullAddress,
                    'is_default' => true,
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Redirect according to the current ERP section.
        |--------------------------------------------------------------------------
        */

        if ($request->routeIs('buyer.account.update')) {
            return Redirect::route(
                'buyer.account'
            )->with(
                'status',
                'Account information and address saved successfully.'
            );
        }

        return Redirect::route(
            'profile.edit'
        )->with(
            'status',
            'Profile information saved successfully.'
        );
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