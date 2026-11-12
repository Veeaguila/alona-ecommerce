<?php

namespace App\Services;

use App\Models\LogisticsRegistration;
use App\Models\PartnerApplication;
use App\Models\SortingCenter;
use App\Models\User;
use App\Notifications\LogisticsEmailOtp;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LogisticsOnboarding
{
    public const DOCUMENTS = ['id_doc', 'sec_doc', 'bir_doc', 'permit_doc'];

    public static function mobile(string $value): string
    {
        return str_starts_with($value, '09') ? '+63'.substr($value, 1) : $value;
    }

    public function available(array $input): array
    {
        $errors = [];
        if (User::whereRaw('LOWER(email) = ?', [$input['email']])->exists()) {
            $errors['email'] = 'This email is already in use.';
        }
        $mobile = self::mobile($input['contact_no']);
        if (User::whereIn('contact_no', [$mobile, '0'.substr($mobile, 3)])->exists()
            || LogisticsRegistration::where('mobile', $mobile)->exists()) {
            $errors['contact_no'] = 'This mobile number is already in use.';
        }
        if (SortingCenter::whereRaw('LOWER(TRIM(business_name)) = ?', [mb_strtolower($input['business_name'])])->exists()
            || LogisticsRegistration::where('company_key', mb_strtolower($input['business_name']))->exists()) {
            $errors['business_name'] = 'This company name is already registered.';
        }
        foreach (['registration_number' => 'sec_number', 'tin' => 'tin'] as $field => $column) {
            // Include legacy partner applications; they predate the dedicated identity table.
            if (LogisticsRegistration::where($column, $input[$field])->exists()
                || PartnerApplication::where('details->'.$field, $input[$field])->exists()) {
                $errors[$field] = $field === 'tin' ? 'This TIN is already registered.' : 'This SEC registration number is already in use.';
            }
        }

        return $errors;
    }

    public function regionCities(array $codes): array
    {
        $data = app(PhilippineAddressService::class)->data();
        $known = array_column($data['regions'], 'code');
        if (array_diff($codes, $known)) {
            throw ValidationException::withMessages(['service_region_codes' => 'Choose valid Philippine service regions.']);
        }

        return array_values(array_filter($data['cities'], fn ($city) => in_array($city['region_code'], $codes, true)));
    }

    public function sendOtp(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $registration = LogisticsRegistration::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            if ($user->hasVerifiedEmail()) {
                throw ValidationException::withMessages(['otp' => 'This email is already verified.']);
            }
            do {
                $code = sprintf('%06d', random_int(0, 999999));
            } while ($registration->otp_hash && Hash::check($code, $registration->otp_hash));
            $registration->update(['otp_hash' => Hash::make($code), 'otp_expires_at' => now()->addMinutes(5), 'otp_attempts' => 0]);
            // A failed mail transport rolls back the new challenge, preserving the prior code.
            $user->notify(new LogisticsEmailOtp($code));
        });
    }

    public function verify(User $user, string $code): void
    {
        $error = DB::transaction(function () use ($user, $code) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $registration = LogisticsRegistration::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            if ($user->hasVerifiedEmail() || ! $registration->otp_hash) {
                return 'This code has already been used. Your email may already be verified.';
            }
            if ($registration->otp_expires_at->lte(now())) {
                return 'This code has expired. Request a new code.';
            }
            if ($registration->otp_attempts >= 5) {
                return 'Too many incorrect codes. Request a new code.';
            }
            if (! Hash::check($code, $registration->otp_hash)) {
                $registration->increment('otp_attempts');

                return 'This verification code is invalid.';
            }
            $user->markEmailAsVerified();
            // Verification must never undo an existing rejection or grant operational approval.
            if ($user->status === 'unverified') {
                $user->update(['status' => 'pending']);
                $user->sortingCenter()->update(['approval_status' => 'pending']);
            }
            $registration->update(['otp_hash' => null, 'otp_expires_at' => null, 'otp_attempts' => 0]);
            event(new Verified($user));

            return null;
        });
        if ($error) {
            throw ValidationException::withMessages(['otp' => $error]);
        }
    }
}
