<?php

namespace App\Http\Requests;

use App\Services\DeliveryCoverageService;
use App\Services\PhilippineAddressService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class PartnerRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user();
    }

    public function isRider(): bool
    {
        return str_contains($this->path(), 'rider');
    }

    public function oauth(): ?array
    {
        if (! $this->hasSession()) {
            return null;
        }
        $identity = $this->session()->get('onboarding.google');

        return ($identity['role'] ?? null) === ($this->isRider() ? 'rider' : 'sorting_center') && ($identity['expires_at'] ?? 0) > now()->timestamp ? $identity : null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => strtolower(trim($this->oauth()['email'] ?? $this->input('email', '')))]);
    }

    public function rules(): array
    {
        $document = File::types(['jpg', 'jpeg', 'png', 'pdf'])->max('5mb');
        $motor = $this->isRider() && in_array($this->input('vehicle_type'), ['motorcycle', 'car', 'van'], true);
        $business = ! $this->isRider();

        return [
            'first_name' => ['required', 'string', 'max:100'], 'middle_name' => ['nullable', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'contact_no' => ['required', 'regex:/^(09\d{9}|\+639\d{9})$/'],
            'password' => [$this->oauth() ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'address_group' => ['nullable', Rule::in(array_keys(PhilippineAddressService::GROUPS))],
            'region_code' => ['required', 'regex:/^\d{10}$/'], 'province_code' => ['nullable', 'regex:/^\d{10}$/'],
            'city_code' => ['required', 'regex:/^\d{10}$/'], 'barangay_code' => ['required', 'regex:/^\d{10}$/'],
            'street' => ['required', 'string', 'max:255'], 'house_number' => ['required', 'string', 'max:50'],
            'subdivision' => ['nullable', 'string', 'max:255'], 'postal_code' => ['required', 'regex:/^\d{4}$/'],
            'terms' => ['accepted'], 'privacy' => ['accepted'], 'id_doc' => ['required', $document],
            'business_name' => [Rule::requiredIf($business), 'nullable', 'string', 'max:255'],
            'business_type' => [Rule::requiredIf($business), 'nullable', Rule::in(['individual', 'sole_proprietor', 'partnership', 'corporation', 'other'])],
            'authorized_representative' => [Rule::requiredIf($business), 'nullable', 'string', 'max:255'],
            'registration_number' => [Rule::requiredIf($business && $this->input('business_type') !== 'individual'), 'nullable', 'string', 'max:100'],
            'business_email' => [Rule::requiredIf($business), 'nullable', 'email', 'max:255'],
            'business_phone' => [Rule::requiredIf($business), 'nullable', 'regex:/^(?:\+63|0)\d{9,10}$/'],
            'services' => [Rule::requiredIf($business), 'nullable', 'string', 'max:2000'],
            'vehicle_types' => [Rule::requiredIf($business), 'nullable', 'string', 'max:500'],
            'fleet_count' => [Rule::requiredIf($business), 'nullable', 'integer', 'min:1', 'max:100000'],
            'coverage' => ['exclude'],
            'coverage_city_codes' => [$business ? 'required' : 'exclude', 'array', 'min:1', 'max:100'],
            'coverage_city_codes.*' => ['required', 'distinct', 'regex:/^\d{10}$/'],
            'delivery_city_codes' => [$this->isRider() ? 'required' : 'exclude', 'array', 'min:1', 'max:20'],
            'delivery_city_codes.*' => ['required', 'distinct', 'regex:/^\d{10}$/'],
            'operating_hours' => [$business ? 'required' : 'exclude', 'string', 'max:500'],
            'permit_doc' => [Rule::requiredIf($business && $this->input('business_type') !== 'individual'), 'nullable', $document],
            'birthday' => [Rule::requiredIf($this->isRider()), 'nullable', 'date', 'before_or_equal:'.now()->subYears(18)->toDateString()],
            'sorting_center_id' => [$this->isRider() ? 'required' : 'exclude', 'integer', Rule::exists('sorting_centers', 'id')->where('approval_status', 'approved')],
            'vehicle_type' => [Rule::requiredIf($this->isRider()), 'nullable', Rule::in(['bicycle', 'motorcycle', 'car', 'van'])],
            'vehicle_model' => [Rule::requiredIf($motor), 'nullable', 'string', 'max:100'],
            'plate_number' => [Rule::requiredIf($motor), 'nullable', 'string', 'max:20'],
            'license_number' => [Rule::requiredIf($motor), 'nullable', 'string', 'max:50'],
            'license_class' => [Rule::requiredIf($motor), 'nullable', Rule::in(['professional'])],
            'license_codes' => [Rule::requiredIf($motor), 'nullable', 'array'],
            'license_codes.*' => [Rule::in(['A', 'A1', 'B', 'B1', 'B2'])],
            'license_expiry' => [Rule::requiredIf($motor), 'nullable', 'date', 'after_or_equal:today'],
            'license_doc' => [Rule::requiredIf($motor), 'nullable', $document], 'or_cr_doc' => [Rule::requiredIf($motor), 'nullable', $document],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isEmpty()) {
                try {
                    app(PhilippineAddressService::class)->validate($this->all());
                    $coverage = app(DeliveryCoverageService::class);
                    if ($this->isRider()) {
                        $coverage->enrollmentAreas($this->input('delivery_city_codes'), (int) $this->input('sorting_center_id'));
                    } else {
                        $coverage->cities($this->input('coverage_city_codes'));
                    }
                } catch (ValidationException $e) {
                    foreach ($e->errors() as $key => $messages) {
                        $validator->errors()->add($key, $messages[0]);
                    }
                }
            }
            $requiredCodes = match ($this->input('vehicle_type')) {
                'motorcycle' => ['A'], 'car' => ['B'], 'van' => ['B1', 'B2'], default => []
            };
            $codes = $this->input('license_codes', []);
            if ($this->isRider() && $requiredCodes && (! is_array($codes) || ! array_intersect($requiredCodes, $codes))) {
                $validator->errors()->add('license_codes', 'Your license must include '.implode(' or ', $requiredCodes).' for this vehicle.');
            }
        }];
    }
}
