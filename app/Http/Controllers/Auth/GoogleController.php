<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountDestination;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirect(Request $request)
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret') || ! config('services.google.redirect')) {
            return redirect('/?auth=login')->withErrors(['email' => 'Google sign-in is not configured yet. Please use email registration or contact support.']);
        }
        $role = $request->validate(['role' => ['nullable', 'in:buyer,seller,sorting_center,rider']])['role'] ?? 'buyer';
        $request->session()->put('google.signup_role', $role);

        return $this->googleDriver()->redirect();
    }

    public function callback(Request $request, AccountDestination $destinations)
    {
        try {
            $google = $this->googleDriver()->user();
        } catch (\Throwable $e) {
            Log::warning('Google authentication failed.', ['exception' => $e::class]);

            return redirect('/?auth=login')->withErrors(['email' => 'Google sign-in could not be completed. Please try again.']);
        }
        $email = strtolower((string) $google->getEmail());
        if (! $email || ! ($google->user['email_verified'] ?? $google->user['verified_email'] ?? false)) {
            return redirect('/?auth=login')->withErrors(['email' => 'Please use a verified Google email address.']);
        }
        $byIdentity = User::where('google_id', $google->getId())->first();
        $byEmail = User::where('email', $email)->first();
        if (($byIdentity && $byEmail && $byIdentity->id !== $byEmail->id)
            || ($byEmail?->google_id && $byEmail->google_id !== $google->getId())) {
            return redirect('/?auth=login')->withErrors(['email' => 'This Google identity is already associated with another account. Please use your original sign-in method.']);
        }
        $user = $byIdentity ?? $byEmail;
        $role = $request->session()->pull('google.signup_role', 'buyer');
        if ($user) {
            $request->session()->forget('onboarding.google');
            $user->update(['google_id' => $google->getId(), 'avatar' => $google->getAvatar()]);
            $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();
            Auth::login($user, true);
            $request->session()->regenerate();
            $request->session()->forget('url.intended');

            return redirect()->route($destinations->route($user));
        }
        $request->session()->put('onboarding.google', ['id' => $google->getId(), 'email' => $email,
            'name' => $google->getName(), 'role' => $role, 'expires_at' => now()->addHour()->timestamp]);

        return match ($role) {
            'sorting_center' => redirect()->route('register.logistics'),
            'rider' => redirect()->route('register.rider'),
            default => redirect('/?auth=register-'.$role.'&onboarding=1'),
        };
    }

    private function googleDriver()
    {
        return Socialite::driver('google')->setHttpClient(new Client(['verify' => config('services.google.verify', true)]));
    }
}
