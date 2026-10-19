<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerStoreController extends Controller
{
    public function edit(Request $request, ?User $seller = null): Response
    {
        $currentUser = $request->user();

        // SEL-PROF-013: Tenant isolation check on view
        if ($seller && $seller->exists && (int) $seller->id !== (int) $currentUser->id) {
            abort(403, 'Unauthorized access to another store settings.');
        }

        $seller = $currentUser;

        return Inertia::render('Seller/Store', [
            'store' => [
                'id' => $seller->id,
                'store_name' => $seller->store_name,
                'store_description' => $seller->store_description,
                'store_logo_path' => $seller->store_logo_path,
                'store_banner_path' => $seller->store_banner_path,
                'store_tagline' => $seller->store_tagline,
                'contact_no' => $seller->contact_no,
                'province' => $seller->province,
                'municipality' => $seller->municipality,
                'barangay' => $seller->barangay,
                'street_address' => $seller->street_address,
                'address' => $seller->address,
                'operating_hours' => $seller->operating_hours ?: User::getDefaultOperatingHours(),
                'response_time' => $seller->response_time ?: 'within_day',
                'shipping_policy' => $seller->shipping_policy,
                'warranty_policy' => $seller->warranty_policy,
                'return_policy' => $seller->return_policy,
                'vacation_mode' => (bool) $seller->vacation_mode,
                'vacation_message' => $seller->vacation_message,
                'status_info' => $seller->getStoreStatus(),

                'rating' => round(
                    (float) Product::where('seller_id', $seller->id)->avg('rating'),
                    2
                ),

                'products_count' => Product::where(
                    'seller_id',
                    $seller->id
                )->count(),
            ],
        ]);
    }

    public function update(Request $request, ?User $seller = null): RedirectResponse
    {
        $currentUser = $request->user();

        // SEL-PROF-013: Tenant isolation check on update
        $targetId = $seller?->id
            ?? $request->input('seller_id')
            ?? $request->input('store_id')
            ?? $request->input('user_id');

        if ($targetId && (int) $targetId !== (int) $currentUser->id) {
            abort(403, 'Unauthorized action. You cannot modify another store settings.');
        }

        $seller = $currentUser;

        // SEL-PROF-003 & SEL-PROF-004: Normalize and trim strings before validation
        if ($request->has('store_name')) {
            $rawStoreName = $request->input('store_name');
            $request->merge([
                'store_name' => is_string($rawStoreName) ? trim($rawStoreName) : $rawStoreName,
            ]);
        }
        if ($request->has('contact_no')) {
            $rawContactNo = $request->input('contact_no');
            $request->merge([
                'contact_no' => is_string($rawContactNo) ? trim($rawContactNo) : $rawContactNo,
            ]);
        }

        $messages = [
            'store_name.required' => 'Store Name is required.',
            'store_name.min' => 'The store name must be between 3 and 30 characters.',
            'store_name.max' => 'The store name must be between 3 and 30 characters.',
            'store_name.unique' => 'The store name is already in use.',
            'store_description.max' => 'The store description must not exceed 500 characters.',
            'contact_no.required' => 'Contact Number is required.',
            'contact_no.regex' => 'The contact number must be a valid Philippine mobile number (e.g. 09XXXXXXXXX or +639XXXXXXXXX).',
            'province.required' => 'Region / Province is required.',
            'municipality.required' => 'City / Municipality is required.',
            'barangay.required' => 'Barangay is required.',
            'street_address.required' => 'Street Address is required.',
            'store_logo.max' => 'The store logo must not be greater than 5 MB.',
            'store_logo.mimes' => 'The store logo must be a file of type: jpg, jpeg, png.',
            'store_logo.image' => 'The store logo must be an image file.',
            'store_banner.max' => 'The store banner must not be greater than 5 MB.',
            'store_banner.mimes' => 'The store banner must be a file of type: jpg, jpeg, png.',
            'store_banner.image' => 'The store banner must be an image file.',
        ];

        // SEL-PROF-002, SEL-PROF-005, SEL-PROF-006, SEL-PROF-007, SEL-PROF-008
        $rules = [
            'store_name' => [
                'required',
                'string',
                'min:3',
                'max:30',
                function ($attribute, $value, $fail) use ($seller) {
                    $normalized = strtolower(trim((string) $value));
                    $exists = \App\Models\User::where('id', '!=', $seller->id)
                        ->whereRaw('LOWER(TRIM(store_name)) = ?', [$normalized])
                        ->exists();
                    if ($exists) {
                        $fail('The store name is already in use.');
                    }
                },
            ],
            'store_tagline' => [
                'nullable',
                'string',
                'max:255',
            ],
            'store_description' => [
                'nullable',
                'string',
                'max:500',
            ],
            'contact_no' => [
                $request->has('contact_no') ? 'required' : 'nullable',
                'string',
                'regex:/^(09|\+639)\d{9}$/',
            ],
            'province' => [
                $request->has('province') ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
            'municipality' => [
                $request->has('municipality') ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
            'barangay' => [
                $request->has('barangay') ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
            'street_address' => [
                $request->has('street_address') ? 'required' : 'nullable',
                'string',
                'max:1000',
            ],
            'store_logo' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            'store_banner' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            'remove_banner' => [
                'nullable',
                'boolean',
            ],
            'remove_logo' => [
                'nullable',
                'boolean',
            ],
            'operating_hours' => [
                'nullable',
                'array',
            ],
            'response_time' => [
                'nullable',
                'string',
                'in:within_hour,few_hours,same_day,next_day',
            ],
            'shipping_policy' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'warranty_policy' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'return_policy' => [
                'nullable',
                'string',
                'max:5000',
            ],
            'vacation_mode' => [
                'nullable',
                'boolean',
            ],
            'vacation_message' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];

        $data = $request->validate($rules, $messages);

        // SEL-PROF-012: Sanitize HTML/scripts
        if (isset($data['store_description'])) {
            $data['store_description'] = strip_tags($data['store_description']);
        }
        if (isset($data['store_tagline'])) {
            $data['store_tagline'] = strip_tags($data['store_tagline']);
        }
        if (isset($data['vacation_message'])) {
            $data['vacation_message'] = strip_tags($data['vacation_message']);
        }
        if (isset($data['store_name'])) {
            $data['store_name'] = strip_tags($data['store_name']);
        }

        /*
        |--------------------------------------------------------------------------
        | Store Logo
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('store_logo')) {
            $data['store_logo_path'] = $request
                ->file('store_logo')
                ->store('store-logos', 'public');
        } elseif (!empty($data['remove_logo'])) {
            $data['store_logo_path'] = null;
        }

        /*
        |--------------------------------------------------------------------------
        | Store Banner (SELLER-62)
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('store_banner')) {
            $data['store_banner_path'] = $request
                ->file('store_banner')
                ->store('store-banners', 'public');
        } elseif (!empty($data['remove_banner'])) {
            $data['store_banner_path'] = null;
        }

        unset($data['store_logo'], $data['store_banner'], $data['remove_banner'], $data['remove_logo']);

        // Composite address update
        if (isset($data['province']) || isset($data['municipality']) || isset($data['barangay']) || isset($data['street_address'])) {
            $street = $data['street_address'] ?? $seller->street_address;
            $brgy = $data['barangay'] ?? $seller->barangay;
            $muni = $data['municipality'] ?? $seller->municipality;
            $prov = $data['province'] ?? $seller->province;
            $fullAddress = implode(', ', array_filter([$street, $brgy, $muni, $prov]));
            if ($fullAddress !== '') {
                $data['address'] = $fullAddress;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Update Seller Record
        |--------------------------------------------------------------------------
        */

        $seller->update($data);

        return back()->with(
            'status',
            'Store settings and branding updated successfully.'
        );
    }
}