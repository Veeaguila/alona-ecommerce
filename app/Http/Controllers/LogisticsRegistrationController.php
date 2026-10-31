<?php

namespace App\Http\Controllers;

use App\Http\Requests\LogisticsRegistrationRequest;
use App\Models\LogisticsRegistration;
use App\Models\PartnerApplication;
use App\Models\SortingCenter;
use App\Models\User;
use App\Services\DeliveryCoverageService;
use App\Services\LogisticsOnboarding;
use App\Services\PhilippineAddressService;
use App\Services\RegistrationDocumentStorage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LogisticsRegistrationController extends Controller
{
    public function create(Request $request)
    {
        $key = (string) Str::uuid();
        $request->session()->put('logistics.registration_key', $key);
        $identity = $request->session()->get('onboarding.google');
        $google = is_array($identity) && ($identity['role'] ?? null) === 'sorting_center'
            && ($identity['expires_at'] ?? 0) > now()->timestamp
            ? collect($identity)->only(['name', 'email'])->all() : null;

        return Inertia::render('Auth/LogisticsRegistration', ['requestKey' => $key,
            'regions' => app(PhilippineAddressService::class)->list('regions'), 'google' => $google]);
    }

    public function store(Request $request, LogisticsOnboarding $onboarding, RegistrationDocumentStorage $storage)
    {
        $api = $request->is('api/sorting-center/onboarding/register');
        if ($api) {
            $request->validate(['request_key' => ['required', 'uuid']]);
        } else {
            abort_unless(is_string($request->input('request_key'))
                && hash_equals((string) $request->session()->get('logistics.registration_key', ''), $request->input('request_key')), 419);
        }
        $key = hash('sha256', $request->input('request_key'));
        if ($existing = LogisticsRegistration::where('request_key', $key)->first()) {
            // Only the browser session that rendered the form may resume its own submission.
            if ($api) {
                // An idempotency key is not authentication; never resume an existing account with it.
                return response()->json(['message' => 'This application was already submitted. Sign in with your account credentials.'], 409);
            }
            return redirect('/?auth=login')->withErrors(['email' => 'This application was already submitted. Please sign in with your account credentials.']);
        }
        try {
            $form = app(LogisticsRegistrationRequest::class);
            $input = $form->validated();
            $identity = $form->oauth();
        } catch (ValidationException $exception) {
            if ($existing = LogisticsRegistration::where('request_key', $key)->first()) {
                if ($api) {
                // An idempotency key is not authentication; never resume an existing account with it.
                return response()->json(['message' => 'This application was already submitted. Sign in with your account credentials.'], 409);
            }
            return redirect('/?auth=login')->withErrors(['email' => 'This application was already submitted. Please sign in with your account credentials.']);
            }
            throw $exception;
        }
        $documents = [];
        try {
            foreach (LogisticsOnboarding::DOCUMENTS as $field) {
                $documents[$field] = $storage->store($request->file($field), 'partner-documents');
            }
            $user = DB::transaction(function () use ($input, $documents, $key, $onboarding, $identity) {
                $errors = $onboarding->available($input);
                if ($errors) {
                    throw ValidationException::withMessages($errors);
                }
                $address = app(PhilippineAddressService::class)->validate($input);
                $cities = $onboarding->regionCities($input['service_region_codes']);
                $mobile = LogisticsOnboarding::mobile($input['contact_no']);
                $user = User::create(['name' => $input['first_name'].' '.$input['last_name'],
                    'first_name' => $input['first_name'], 'last_name' => $input['last_name'], 'email' => $input['email'],
                    'contact_no' => $mobile, 'password' => $identity ? Str::random(48) : $input['password'],
                    'google_id' => $identity['id'] ?? null, 'usertype' => 'sorting_center', 'status' => 'unverified',
                    'province' => $address['province'], 'municipality' => $address['city'], 'barangay' => $address['barangay'],
                    'street_address' => $address['street'], 'address_geography' => collect($address)->only(['region_code', 'province_code', 'city_code', 'barangay_code', 'psgc_version'])->all(),
                    'address' => implode(', ', array_filter([$address['street'], $address['barangay'], $address['city'], $address['province'], $address['region'], $address['postal_code']]))]);
                LogisticsRegistration::create(['user_id' => $user->id, 'request_key' => $key,
                    'company_key' => mb_strtolower($input['business_name']), 'sec_number' => $input['registration_number'],
                    'tin' => $input['tin'], 'mobile' => $mobile]);
                PartnerApplication::create(['user_id' => $user->id, 'role' => 'sorting_center',
                    'details' => array_merge(collect($input)->except(['password', 'password_confirmation', 'request_key', ...LogisticsOnboarding::DOCUMENTS])->all(),
                        ['contact_no' => $mobile, 'registration_version' => 2]),
                    'address' => $address, 'documents' => $documents, 'terms_accepted_at' => now(), 'privacy_accepted_at' => now()]);
                $center = SortingCenter::create(['user_id' => $user->id, 'business_name' => $input['business_name'],
                    'id_doc_url' => $documents['id_doc'], 'permit_doc_url' => $documents['permit_doc'], 'approval_status' => 'pending']);
                app(DeliveryCoverageService::class)->addCities($center, array_column($cities, 'code'));

                return $user;
            });
        } catch (\Throwable $exception) {
            foreach ($documents as $path) {
                Storage::disk('local')->delete($path);
            }
            if ($exception instanceof UniqueConstraintViolationException || $exception instanceof ValidationException) {
                if ($existing = LogisticsRegistration::where('request_key', $key)->first()) {
                    if ($api) {
                // An idempotency key is not authentication; never resume an existing account with it.
                return response()->json(['message' => 'This application was already submitted. Sign in with your account credentials.'], 409);
            }
            Auth::login(User::findOrFail($existing->user_id));
                    $request->session()->regenerate();

                    return redirect()->route('application.status');
                }
                if ($exception instanceof UniqueConstraintViolationException) {
                    throw ValidationException::withMessages($onboarding->available($input) ?: ['email' => 'An account already uses these details.']);
                }
            }
            throw $exception;
        }
        $message = 'Application saved. Check your email for the six-digit verification code.';
        try {
            $onboarding->sendOtp($user);
        } catch (\Throwable $exception) {
            Log::warning('Logistics verification email could not be sent.', ['user_id' => $user->id, 'exception' => $exception::class]);
            $message = 'Application saved, but the email could not be sent. Use Resend code to try again.';
        }
        if ($api) {
            return response()->json(app(\App\Services\SortingCenterSession::class)->issue($user, $message), 201);
        }
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('onboarding.google');

        return redirect()->route('application.status')->with('status', $message);
    }

    public function verify(Request $request, LogisticsOnboarding $onboarding)
    {
        abort_unless($request->user()->usertype === 'sorting_center', 403);
        $input = $request->validate(['otp' => ['required', 'string', 'regex:/^[0-9]{6}$/']]);
        $onboarding->verify($request->user(), $input['otp']);

        return $request->expectsJson() ? response()->json(['message' => 'Email verified. Your application is waiting for admin approval.']) : back()->with('status', 'Email verified. Your application is waiting for admin approval.');
    }

    public function resend(Request $request, LogisticsOnboarding $onboarding)
    {
        abort_unless($request->user()->usertype === 'sorting_center', 403);
        try {
            $onboarding->sendOtp($request->user());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'The verification email could not be sent. Please try again later.'], 503);
            }
            return back()->withErrors(['otp' => 'The verification email could not be sent. Please try again later.']);
        }

        return $request->expectsJson() ? response()->json(['message' => 'A new six-digit code has been sent. Your previous code is no longer valid.']) : back()->with('status', 'A new six-digit code has been sent. Your previous code is no longer valid.');
    }

    public function resubmit(Request $request, RegistrationDocumentStorage $storage)
    {
        $user = $request->user();
        abort_unless($user->usertype === 'sorting_center' && $user->status === 'rejected' && $user->hasVerifiedEmail()
            && ($user->partnerApplication?->details['registration_version'] ?? null) === 2, 403);
        $rules = [];
        foreach (LogisticsOnboarding::DOCUMENTS as $field) {
            $rules[$field] = ['nullable', File::types(['jpg', 'jpeg', 'png', 'pdf'])->extensions(['jpg', 'jpeg', 'png', 'pdf'])->max(5120)];
        }
        $request->validate($rules);
        $files = array_filter(LogisticsOnboarding::DOCUMENTS, fn ($field) => $request->hasFile($field));
        if (! $files) {
            throw ValidationException::withMessages(['permit_doc' => 'Replace at least one document before resubmitting.']);
        }
        $new = [];
        try {
            foreach ($files as $field) {
                $new[$field] = $storage->store($request->file($field), 'partner-documents');
            }
            $old = DB::transaction(function () use ($user, $new) {
                $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                abort_unless($user->status === 'rejected' && $user->hasVerifiedEmail(), 403);
                $application = $user->partnerApplication()->lockForUpdate()->firstOrFail();
                $old = array_intersect_key($application->documents, $new);
                $application->update(['documents' => array_merge($application->documents, $new)]);
                $user->update(['status' => 'pending', 'rejection_reason' => null]);
                $user->sortingCenter()->update(['approval_status' => 'pending',
                    'id_doc_url' => $new['id_doc'] ?? $application->documents['id_doc'],
                    'permit_doc_url' => $new['permit_doc'] ?? $application->documents['permit_doc']]);

                return $old;
            });
        } catch (\Throwable $exception) {
            foreach ($new as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
        foreach ($old as $path) {
            Storage::disk('local')->delete($path);
        }

        return $request->expectsJson() ? response()->json(['message' => 'Documents replaced. Your application is waiting for admin approval.']) : back()->with('status', 'Documents replaced. Your application is waiting for admin approval.');
    }
}
