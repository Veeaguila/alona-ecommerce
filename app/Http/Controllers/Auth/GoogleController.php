<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirect()
    {
        return $this->googleDriver()->redirect();
    }

    public function callback()
    {
        $googleUser = $this->googleDriver()->user();

        $user = User::firstOrCreate(
            ['email' => $googleUser->getEmail()],
            [
                'name' => $googleUser->getName(),
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'usertype' => 'buyer',
                'status' => 'approved',
                'password' => bcrypt(str()->random(24)),
            ]
        );

        if (! $user->wasRecentlyCreated) {
            $user->update([
                'name' => $googleUser->getName() ?: $user->name,
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
            ]);
        }

        Auth::login($user);

        $destination = match ($user->usertype) {
            'seller' => 'seller.dashboard',
            'admin' => 'admin.dashboard',
            default => 'buyer.dashboard',
        };

        return redirect()->route($destination);
    }

    private function googleDriver()
    {
        return Socialite::driver('google')->setHttpClient(new Client([
            'verify' => config('services.google.verify', true),
        ]));
    }
}
