<?php

namespace App\Http\Controllers;

use App\Http\Requests\PartnerRegistrationRequest;
use App\Models\PartnerApplication;
use App\Models\Rider;
use App\Models\SortingCenter;
use App\Models\User;
use App\Services\DeliveryCoverageService;
use App\Services\PhilippineAddressService;
use App\Services\RegistrationDocumentStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PartnerRegistrationController extends Controller
{
    public function create(Request $request)
    {
        if (str_contains($request->path(), 'rider')) {
            return redirect('/?auth=login');
        }

        $role = 'sorting_center';
        $identity = $request->session()->get('onboarding.google');
        if ($identity && ($identity['role'] !== $role || ($identity['expires_at'] ?? 0) <= now()->timestamp)) {
            $identity = null;
        }

        return Inertia::render('Auth/PartnerRegistration', [
            'role' => $role, 'google' => $identity ? collect($identity)->only(['name', 'email'])->all() : null,
        ]);
    }

    public function store(PartnerRegistrationRequest $request, PhilippineAddressService $addresses, RegistrationDocumentStorage $storage, DeliveryCoverageService $coverage)
    {
        $input = $request->validated();
        $address = $addresses->validate($input);
        $documents = [];
        try {
            foreach (['id_doc', 'permit_doc', 'license_doc', 'or_cr_doc'] as $key) {
                if ($request->hasFile($key)) {
                    $documents[$key] = $storage->store($request->file($key), 'partner-documents');
                }
            }
            $user = DB::transaction(function () use ($request, $input, $address, $documents, $coverage) {
                $role = $request->isRider() ? 'rider' : 'sorting_center';
                $areas = $request->isRider() ? $coverage->enrollmentAreas($input['delivery_city_codes'], (int) $input['sorting_center_id'], true) : null;
                $cities = $coverage->cities($input[$request->isRider() ? 'delivery_city_codes' : 'coverage_city_codes']);
                $input['coverage'] = implode(', ', array_column($cities, 'name'));
                $input['coverage_cities'] = $cities;
                $name = trim(implode(' ', array_filter([$input['first_name'], $input['middle_name'] ?? '', $input['last_name']])));
                $user = User::create([
                    'name' => $name, 'first_name' => $input['first_name'], 'last_name' => $input['last_name'],
                    'email' => $input['email'], 'contact_no' => $input['contact_no'], 'birthday' => $input['birthday'] ?? null,
                    'password' => $request->oauth() ? Str::random(48) : $input['password'],
                    'google_id' => $request->oauth()['id'] ?? null, 'usertype' => $role, 'status' => 'pending',
                    'address_geography' => collect($address)->only(['address_group', 'region_code', 'province_code', 'city_code', 'barangay_code', 'psgc_version'])->all(),
                    'province' => $address['province'], 'municipality' => $address['city'], 'barangay' => $address['barangay'],
                    'street_address' => $address['street'], 'address' => implode(', ', array_filter([$address['house_number'], $address['street'], $address['barangay'], $address['city'], $address['province'], $address['region'], $address['postal_code']])),
                ]);
                if ($request->oauth()) {
                    $user->forceFill(['email_verified_at' => now()])->save();
                }
                PartnerApplication::create([
                    'user_id' => $user->id, 'role' => $role,
                    'details' => collect($input)->except(['password', 'password_confirmation', 'terms', 'privacy', 'id_doc', 'permit_doc', 'license_doc', 'or_cr_doc'])->all(),
                    'address' => $address, 'documents' => $documents, 'terms_accepted_at' => now(), 'privacy_accepted_at' => now(),
                ]);
                if ($role === 'sorting_center') {
                    $center = SortingCenter::create(['user_id' => $user->id, 'business_name' => $input['business_name'], 'id_doc_url' => $documents['id_doc'], 'permit_doc_url' => $documents['permit_doc'] ?? null, 'approval_status' => 'pending']);
                    $coverage->addCities($center, $input['coverage_city_codes']);
                } else {
                    $rider = Rider::create([
                        'user_id' => $user->id, 'sorting_center_id' => $input['sorting_center_id'] ?? null, 'name' => $name,
                        'contact' => $input['contact_no'], 'birthday' => $input['birthday'], 'province' => $address['province'],
                        'sex' => $input['sex'] ?? null,
                        'municipality' => $address['city'], 'barangay' => $address['barangay'], 'street' => $address['street'], 'house_number' => $address['house_number'],
                        'vehicle_type' => $input['vehicle_type'], 'plate_number' => $input['plate_number'] ?? '',
                        'or_cr_doc_url' => $documents['or_cr_doc'] ?? null, 'license_doc_url' => $documents['license_doc'] ?? null,
                        'approval_status' => 'pending', 'is_active' => true,
                    ]);
                    $rider->areas()->sync($areas->modelKeys());
                }

                return $user;
            });
        } catch (\Throwable $e) {
            foreach ($documents as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }
        if (! $user->hasVerifiedEmail()) {
            try {
                $user->sendEmailVerificationNotification();
            } catch (\Throwable $e) {
                Log::warning('Partner verification email could not be sent.', ['user_id' => $user->id, 'exception' => $e::class]);
            }
        }
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Application submitted. Verify your email and await approval.', 'data' => ['user_id' => $user->id, 'approval_status' => 'pending']], 201);
        }

        $request->session()->forget('onboarding.google');
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('application.status');
    }
}
