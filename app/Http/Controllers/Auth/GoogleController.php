<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    /**
     * Redirect the user to Google for authentication.
     */
    public function redirect()
    {
        return $this->googleDriver()->redirect();
    }

    /**
     * Handle Google's callback.
     */
    public function callback()
    {
        $googleUser = $this->googleDriver()->user();

        $email = $googleUser->getEmail();

        /*
        |--------------------------------------------------------------------------
        | Find existing user or create a new buyer
        |--------------------------------------------------------------------------
        */

        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name' => $googleUser->getName() ?: 'Google User',
                'email' => $email,
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'usertype' => 'buyer',
                'status' => 'approved',
                'password' => bcrypt(str()->random(32)),
            ]);
        } else {
            /*
            |--------------------------------------------------------------------------
            | Update existing Google information
            |--------------------------------------------------------------------------
            */

            $user->update([
                'name' => $googleUser->getName() ?: $user->name,
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Login the user
        |--------------------------------------------------------------------------
        */

        Auth::login($user, true);

        /*
        |--------------------------------------------------------------------------
        | Redirect based on user type
        |--------------------------------------------------------------------------
        */

        $destination = match ($user->usertype) {
            'admin' => 'admin.dashboard',
            'seller' => 'seller.dashboard',
            'buyer' => 'buyer.dashboard',
            default => 'buyer.dashboard',
        };

        return redirect()->route($destination);
    }

    /**
     * Create the Google Socialite driver.
     */
    private function googleDriver()
    {
        return Socialite::driver('google')
            ->setHttpClient(new Client([
                'verify' => config('services.google.verify', true),
            ]));
    }
}