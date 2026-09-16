<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' =>
                $request->user() instanceof MustVerifyEmail,

            'status' => session('status'),
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
}