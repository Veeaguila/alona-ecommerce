<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the buyer registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Display the seller registration view.
     */
    public function createSeller(): Response
    {
        return Inertia::render('Auth/RegisterSeller');
    }

    /**
     * Handle buyer registration.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'last_name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_initial' => 'nullable|string|size:1',
            'sex' => 'required|in:Male,Female,Other',

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                'unique:' . User::class,
            ],

            'contact_no' => 'required|string|max:30',
            'birthday' => 'required|date|before:today',
            'age' => 'required|integer|min:1|max:120',

            'province' => 'required|string|max:255',
            'municipality' => 'required|string|max:255',
            'barangay' => 'required|string|max:255',
            'street_address' => 'required|string|max:1000',

            'id' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',

            'password' => [
                'required',
                'confirmed',
                Rules\Password::defaults(),
            ],
        ]);

        $fullName = trim(
            $request->first_name .
            ' ' .
            ($request->middle_initial
                ? $request->middle_initial . '. '
                : '') .
            $request->last_name
        );

        $user = User::create([
            'name' => $fullName,

            // Personal information
            'last_name' => $request->last_name,
            'first_name' => $request->first_name,
            'middle_initial' => $request->middle_initial,
            'sex' => $request->sex,
            'email' => $request->email,
            'contact_no' => $request->contact_no,
            'birthday' => $request->birthday,
            'age' => Carbon::parse($request->birthday)->age,

            // Address
            'address' => implode(', ', [
                $request->street_address,
                $request->barangay,
                $request->municipality,
                $request->province,
            ]),
            'province' => $request->province,
            'municipality' => $request->municipality,
            'barangay' => $request->barangay,
            'street_address' => $request->street_address,

            // Buyer ID
            'id_path' => $request->file('id')?->store('buyer-ids'),

            // Account
            'usertype' => 'buyer',
            'status' => 'pending',
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        return redirect()
            ->route('login')
            ->with(
                'status',
                'Registration submitted. Please wait for administrator approval. We will notify you by email.'
            );
    }

    /**
     * Handle seller registration.
     *
     * @throws ValidationException
     */
    public function storeSeller(Request $request): RedirectResponse
    {
        $request->validate([
            /*
            |--------------------------------------------------------------------------
            | Personal Information
            |--------------------------------------------------------------------------
            */
            'last_name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_initial' => 'nullable|string|size:1',
            'sex' => 'required|in:Male,Female,Other',

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                'unique:' . User::class,
            ],

            'contact_no' => 'required|string|max:30',
            'birthday' => 'required|date|before:today',
            'age' => 'required|integer|min:1|max:120',

            /*
            |--------------------------------------------------------------------------
            | Address
            |--------------------------------------------------------------------------
            */
            'province' => 'required|string|max:255',
            'municipality' => 'required|string|max:255',
            'barangay' => 'required|string|max:255',
            'street_address' => 'required|string|max:1000',

            /*
            |--------------------------------------------------------------------------
            | Business Information
            |--------------------------------------------------------------------------
            */
            'store_name' => 'required|string|max:150',

            'line_of_business' => [
                'required',
                'string',
                'max:255',
            ],

            'store_description' => 'nullable|string|max:2000',

            /*
            |--------------------------------------------------------------------------
            | Seller Payment Information
            |--------------------------------------------------------------------------
            */
            'bank_name' => 'nullable|string|max:150',
            'bank_account_name' => 'nullable|string|max:150',
            'bank_account_number' => 'nullable|string|max:50',

            /*
            |--------------------------------------------------------------------------
            | Required Seller Documents
            |--------------------------------------------------------------------------
            */
            'id' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],

            'business_permit' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],

            /*
            |--------------------------------------------------------------------------
            | Password
            |--------------------------------------------------------------------------
            */
            'password' => [
                'required',
                'confirmed',
                Rules\Password::defaults(),
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Generate Full Name
        |--------------------------------------------------------------------------
        */

        $fullName = trim(
            $request->first_name .
            ' ' .
            ($request->middle_initial
                ? $request->middle_initial . '. '
                : '') .
            $request->last_name
        );

        /*
        |--------------------------------------------------------------------------
        | Store Seller Documents
        |--------------------------------------------------------------------------
        */

        $idPath = $request->file('id')->store('seller-ids');

        $businessPermitPath = $request
            ->file('business_permit')
            ->store('seller-business-permits');

        /*
        |--------------------------------------------------------------------------
        | Create Seller Account
        |--------------------------------------------------------------------------
        */

        $user = User::create([
            'name' => $fullName,

            // Personal information
            'last_name' => $request->last_name,
            'first_name' => $request->first_name,
            'middle_initial' => $request->middle_initial,
            'sex' => $request->sex,
            'email' => $request->email,
            'contact_no' => $request->contact_no,
            'birthday' => $request->birthday,

            // Age is generated from birthday
            'age' => Carbon::parse($request->birthday)->age,

            // Address
            'address' => implode(', ', [
                $request->street_address,
                $request->barangay,
                $request->municipality,
                $request->province,
            ]),
            'province' => $request->province,
            'municipality' => $request->municipality,
            'barangay' => $request->barangay,
            'street_address' => $request->street_address,

            // Seller documents
            'id_path' => $idPath,
            'business_permit_path' => $businessPermitPath,

            // Business information
            'store_name' => $request->store_name,
            'line_of_business' => $request->line_of_business,
            'store_description' => $request->store_description,

            // Payment information
            'bank_name' => $request->bank_name,
            'bank_account_name' => $request->bank_account_name,
            'bank_account_number' => $request->bank_account_number,

            // Seller account
            'usertype' => 'seller',
            'status' => 'pending',

            // Password
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        return redirect()
            ->route('login')
            ->with(
                'status',
                'Seller application submitted. Please wait for administrator approval. You will be notified by email once your application has been reviewed.'
            );
    }
}