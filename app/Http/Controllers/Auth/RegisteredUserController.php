<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PhilippineAddressService;
use Carbon\Carbon;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class RegisteredUserController extends Controller
{
    private function prepareGoogle(Request $request, string $role): ?array
    {
        $identity = $request->session()->get('onboarding.google');
        if (($identity['role'] ?? null) !== $role || ($identity['expires_at'] ?? 0) <= now()->timestamp) {
            return null;
        }
        $password = Str::random(48);
        $request->merge(['email' => $identity['email'], 'password' => $password, 'password_confirmation' => $password]);

        return $identity;
    }

    private function completeGoogle(Request $request, User $user, ?array $identity): void
    {
        if (! $identity) {
            return;
        }
        $user->forceFill(['google_id' => $identity['id'], 'email_verified_at' => now()])->save();
        $request->session()->forget('onboarding.google');
    }

    /**
     * Display the buyer registration view.
     */
    public function create(): RedirectResponse
    {
        return redirect('/?auth=register-buyer&onboarding=1');
    }

    /**
     * Display the seller registration view.
     */
    public function createSeller(): RedirectResponse
    {
        return redirect('/?auth=register-seller&onboarding=1');
    }

    /**
     * Handle buyer registration.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $google = $this->prepareGoogle($request, 'buyer');

        if ($request->filled('birthday') && ! $request->filled('age')) {
            try {
                $request->merge(['age' => Carbon::parse($request->input('birthday'))->age]);
            } catch (\Throwable) {
                // Handled by date validation below
            }
        }

        if ($request->has('email')) {
            $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        }

        $hasAddressGroup = $request->filled('address_group');

        $request->validate([
            'last_name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:50',
            'middle_initial' => 'nullable|string|max:50',
            'suffix' => 'nullable|string|max:20',
            'sex' => 'nullable|in:Male,Female,Other',

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                'unique:'.User::class,
            ],

            'contact_no' => 'required|string|max:30',
            'birthday' => 'required|date|before:today',
            'age' => 'nullable|integer|min:1|max:120',

            'address_group' => ['nullable', Rule::in(array_keys(PhilippineAddressService::GROUPS))],
            'region_code' => [$hasAddressGroup ? 'required' : 'nullable', 'regex:/^\d{10}$/'],
            'province_code' => ['nullable', 'regex:/^\d{10}$/'],
            'city_code' => [$hasAddressGroup ? 'required' : 'nullable', 'regex:/^\d{10}$/'],
            'barangay_code' => [$hasAddressGroup ? 'required' : 'nullable', 'regex:/^\d{10}$/'],
            'street_address' => [$hasAddressGroup ? 'required' : 'nullable', 'string', 'max:1000'],

            'id' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',

            'password' => [
                'required',
                'confirmed',
                Rules\Password::defaults(),
            ],

            'terms' => ['accepted'],
        ], [
            'email.unique' => 'This email address is already registered. Please log in instead or use another email.',
            'terms.accepted' => 'You must agree to the Terms and Privacy Policy.',
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

        $middleName = trim((string) ($request->input('middle_name') ?: $request->input('middle_initial') ?: ''));
        $middleInitial = $middleName !== '' ? mb_substr($middleName, 0, 1) : null;
        $suffix = trim((string) ($request->input('suffix') ?? ''));

        $nameParts = array_filter([
            trim((string) $request->first_name),
            $middleName !== ''
                ? (mb_strlen($middleName) === 1 ? $middleName.'.' : $middleName)
                : null,
            trim((string) $request->last_name),
            $suffix !== '' ? $suffix : null,
        ]);

        $fullName = implode(' ', $nameParts);

        $user = User::create([
            'name' => $fullName,

            // Personal information
            'last_name' => $request->last_name,
            'first_name' => $request->first_name,
            'middle_name' => $middleName !== '' ? $middleName : null,
            'middle_initial' => $middleInitial,
            'suffix' => $suffix !== '' ? $suffix : null,
            'sex' => $request->sex,
            'email' => $request->email,
            'contact_no' => $request->contact_no,
            'birthday' => $request->birthday,
            'age' => Carbon::parse($request->birthday)->age,

            // Address
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

            // Buyer ID
            'id_path' => $request->file('id')?->store('buyer-ids'),

            // Account
            'usertype' => 'buyer',
            'status' => $google ? 'approved' : 'unverified',
            'password' => Hash::make($request->password),
        ]);

        $this->completeGoogle($request, $user, $google);
        event(new Registered($user));

        if (! $google) {
            $user->generateAndSendOtp();
            $request->session()->put('otp_verification_email', $user->email);

            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Account created successfully! A 6-digit verification code has been sent to your email address.',
                    'email' => $user->email,
                ]);
            }

            return redirect()
                ->route('verification.otp')
                ->with(
                    'status',
                    'Account created successfully! A 6-digit verification code has been sent to your email address.'
                );
        }

        return redirect()
            ->route('login')
            ->with(
                'status',
                'Registration complete. You may now log in.'
            );
    }

    /**
     * Handle seller registration.
     *
     * @throws ValidationException
     */
    public function storeSeller(Request $request): RedirectResponse
    {
        $google = $this->prepareGoogle($request, 'seller');

        if ($request->has('email')) {
            $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        }

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
                'unique:'.User::class,
            ],

            'contact_no' => 'required|string|max:30',
            'birthday' => 'required|date|before:today',
            'age' => 'required|integer|min:1|max:120',

            /*
            |--------------------------------------------------------------------------
            | Address
            |--------------------------------------------------------------------------
            */
            'address_group' => ['required', Rule::in(array_keys(PhilippineAddressService::GROUPS))],
            'region_code' => ['required', 'regex:/^\d{10}$/'],
            'province_code' => ['nullable', 'regex:/^\d{10}$/'],
            'city_code' => ['required', 'regex:/^\d{10}$/'],
            'barangay_code' => ['required', 'regex:/^\d{10}$/'],
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
        ], [
            'email.unique' => 'This email address is already registered. Please log in instead or use another email.',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Generate Full Name
        |--------------------------------------------------------------------------
        */

        $address = app(PhilippineAddressService::class)->validate($request->all());
        $request->merge(['province' => $address['province'], 'municipality' => $address['city'], 'barangay' => $address['barangay']]);

        $fullName = trim(
            $request->first_name.
            ' '.
            ($request->middle_initial
                ? $request->middle_initial.'. '
                : '').
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
            'address' => implode(', ', array_filter([
                $request->street_address,
                $request->barangay,
                $request->municipality,
                $request->province,
            ])),
            'address_geography' => collect($address)->only(['address_group', 'region_code', 'province_code', 'city_code', 'barangay_code', 'psgc_version'])->all(),
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

        $this->completeGoogle($request, $user, $google);
        event(new Registered($user));

        return redirect()
            ->route('login')
            ->with(
                'status',
                'Seller application submitted. Please wait for administrator approval. You will be notified by email once your application has been reviewed.'
            );
    }
}
