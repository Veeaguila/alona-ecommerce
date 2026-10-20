<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
            ->orderByDesc('is_default')
            ->latest('id')
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
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | 10-Address Limit Enforcement (BUY-ADDR-005)
        |--------------------------------------------------------------------------
        */

        if ($user->addresses()->count() >= 10) {
            throw ValidationException::withMessages([
                'address' => 'You cannot save more than 10 addresses. Please remove an old address first.',
            ]);
        }

        $data = $this->validateAddress($request);

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
        | If this address is default, remove default status from others.
        |--------------------------------------------------------------------------
        */

        if ($data['is_default']) {
            $user->addresses()->update([
                'is_default' => false,
            ]);
        }

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
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Cross-Buyer Address Security (BUY-ADDR-009)
        |--------------------------------------------------------------------------
        */

        $savedAddress = $user->addresses()
            ->whereKey($address)
            ->firstOrFail();

        $data = $this->validateAddress($request);

        /*
        |--------------------------------------------------------------------------
        | Switching default properly unsets previous default flags (BUY-ADDR-006).
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
            // Prevent removing default flag if this is the only or currently default address
            if ($savedAddress->is_default) {
                $data['is_default'] = true;
            } else {
                $data['is_default'] = false;
            }
        }

        $savedAddress->update($data);

        return back()->with(
            'status',
            'Address updated successfully.'
        );
    }

    /**
     * Set an address as the default address (BUY-ADDR-006).
     */
    public function setDefault(
        Request $request,
        int $address
    ): RedirectResponse {
        $user = $request->user();

        $savedAddress = $user->addresses()
            ->whereKey($address)
            ->firstOrFail();

        $user->addresses()
            ->whereKeyNot($savedAddress->id)
            ->update([
                'is_default' => false,
            ]);

        $savedAddress->update([
            'is_default' => true,
        ]);

        return back()->with(
            'status',
            'Default delivery address updated.'
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
        | Cross-Buyer Address Security (BUY-ADDR-009)
        |--------------------------------------------------------------------------
        */

        $savedAddress = $user->addresses()
            ->whereKey($address)
            ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Guard Deleting Default Address (BUY-ADDR-007)
        |--------------------------------------------------------------------------
        | Buyers cannot delete their default delivery address directly when multiple
        | addresses exist; they must set another address as default first.
        |--------------------------------------------------------------------------
        */

        if ($savedAddress->is_default && $user->addresses()->count() > 1) {
            return back()->withErrors([
                'address' => 'Cannot delete your default delivery address. Please set another address as default first.',
            ]);
        }

        $savedAddress->delete();

        /*
        |--------------------------------------------------------------------------
        | If the deleted address was default and an address remains, set it default.
        |--------------------------------------------------------------------------
        */

        if ($user->addresses()->where('is_default', true)->doesntExist()) {
            $newDefault = $user->addresses()->latest('id')->first();
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

    /**
     * Validate address input fields (BUY-ADDR-001, BUY-ADDR-003, BUY-ADDR-004).
     */
    protected function validateAddress(Request $request): array
    {
        // Support legacy/profile sync if only freeform address is passed
        if (! $request->filled('recipient_name') && $request->filled('address')) {
            return $request->validate([
                'label' => ['required', 'string', 'max:50'],
                'address' => ['required', 'string', 'max:1000'],
                'is_default' => ['boolean'],
            ]);
        }

        $data = $request->validate([
            'label' => ['required', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'min:2', 'max:100', "regex:/^[\pL\s\-'.]+$/u"],
            'recipient_phone' => ['required', 'string', 'regex:/^(09|\+639)\d{9}$/'],
            'region' => ['nullable', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'municipality' => ['required', 'string', 'max:100'],
            'barangay' => ['required', 'string', 'max:100'],
            'street_address' => ['required', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['boolean'],
        ], [
            'label.required' => 'Please provide an address label (e.g. Home, Work).',
            'recipient_name.required' => 'Recipient full name is required.',
            'recipient_name.min' => 'Recipient name must be at least 2 characters.',
            'recipient_name.regex' => 'Recipient name may only contain letters, spaces, hyphens, and apostrophes.',
            'recipient_phone.required' => 'Recipient phone number is required.',
            'recipient_phone.regex' => 'The recipient phone number must be a valid Philippine mobile number (e.g. 09171234567 or +639171234567).',
            'street_address.required' => 'Street address or house/unit number is required.',
            'barangay.required' => 'Barangay is required.',
            'municipality.required' => 'City or municipality is required.',
            'province.required' => 'Province is required.',
        ]);

        if (empty($data['address'])) {
            $data['address'] = collect([
                $data['street_address'] ?? null,
                isset($data['barangay']) ? 'Brgy. ' . $data['barangay'] : null,
                $data['municipality'] ?? null,
                $data['province'] ?? null,
                $data['postal_code'] ?? null,
            ])->filter()->implode(', ');
        }

        return $data;
    }
}
