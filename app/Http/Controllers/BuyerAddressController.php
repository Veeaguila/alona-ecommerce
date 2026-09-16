<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuyerAddressController extends Controller
{
    /**
     * Display the buyer's saved addresses.
     */
    public function index(Request $request): Response
    {
        $addresses = $request->user()
            ->addresses()
            ->latest()
            ->get();

        return Inertia::render('Buyer/Addresses', [
            'addresses' => $addresses,
        ]);
    }

    /**
     * Store a new buyer address.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => [
                'required',
                'string',
                'max:50',
            ],
            'address' => [
                'required',
                'string',
                'max:1000',
            ],
            'is_default' => [
                'boolean',
            ],
        ]);

        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | First address automatically becomes default.
        |--------------------------------------------------------------------------
        */

        $data['is_default'] =
            $user->addresses()->doesntExist()
            || $request->boolean('is_default');

        /*
        |--------------------------------------------------------------------------
        | If this address is default, remove default status
        | from the other addresses.
        |--------------------------------------------------------------------------
        */

        if ($data['is_default']) {
            $user->addresses()->update([
                'is_default' => false,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Create address.
        |--------------------------------------------------------------------------
        */

        $user->addresses()->create($data);

        return back()->with(
            'status',
            'Address saved successfully.'
        );
    }

    /**
     * Update an existing buyer address.
     */
    public function update(
        Request $request,
        int $address
    ): RedirectResponse {
        $data = $request->validate([
            'label' => [
                'required',
                'string',
                'max:50',
            ],
            'address' => [
                'required',
                'string',
                'max:1000',
            ],
            'is_default' => [
                'boolean',
            ],
        ]);

        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Only allow the logged-in buyer to update their own address.
        |--------------------------------------------------------------------------
        */

        $savedAddress = $user->addresses()
            ->whereKey($address)
            ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | If this address becomes default, remove default status
        | from every other address.
        |--------------------------------------------------------------------------
        */

        if ($request->boolean('is_default')) {
            $user->addresses()
                ->whereKeyNot($savedAddress->id)
                ->update([
                    'is_default' => false,
                ]);

            $data['is_default'] = true;
        } else {
            /*
            |--------------------------------------------------------------------------
            | Prevent the buyer from ending up with no default address
            | when the address being edited is currently the default.
            |--------------------------------------------------------------------------
            */

            if ($savedAddress->is_default) {
                $data['is_default'] = true;
            } else {
                $data['is_default'] = false;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Update address.
        |--------------------------------------------------------------------------
        */

        $savedAddress->update($data);

        return back()->with(
            'status',
            'Address updated successfully.'
        );
    }

    /**
     * Delete a buyer address.
     */
    public function destroy(
        Request $request,
        int $address
    ): RedirectResponse {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Only delete the logged-in buyer's own address.
        |--------------------------------------------------------------------------
        */

        $savedAddress = $user->addresses()
            ->whereKey($address)
            ->firstOrFail();

        $wasDefault = $savedAddress->is_default;

        $savedAddress->delete();

        /*
        |--------------------------------------------------------------------------
        | If the deleted address was default, automatically assign
        | another saved address as the new default.
        |--------------------------------------------------------------------------
        */

        if ($wasDefault) {
            $newDefault = $user->addresses()
                ->latest('id')
                ->first();

            if ($newDefault) {
                $newDefault->update([
                    'is_default' => true,
                ]);
            }
        }

        return back()->with(
            'status',
            'Address removed successfully.'
        );
    }
}