<?php

namespace App\Http\Requests;

use App\Services\LogisticsOnboarding;
use App\Services\PhilippineAddressService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class LogisticsRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user();
    }

    public function oauth(): ?array
    {
        $identity = $this->hasSession() ? $this->session()->get('onboarding.google') : null;

        return is_array($identity) && ($identity['role'] ?? null) === 'sorting_center'
            && ($identity['expires_at'] ?? 0) > now()->timestamp
            && is_string($identity['id'] ?? null) && is_string($identity['email'] ?? null) ? $identity : null;
    }

    protected function prepareForValidation(): void
    {
        if ($identity = $this->oauth()) {
            $this->merge(['email' => $identity['email']]);
        }
        foreach (['email', 'business_name', 'registration_number'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = trim($value);
                $this->merge([$field => match ($field) {
                    'email' => strtolower($value), 'registration_number' => strtoupper($value), default => $value,
                }]);
            }
        }
    }

    public function rules(): array
    {
        $name = ['required', 'string', 'min:2', 'max:50', "regex:/^[\\p{L} .'-]+$/u"];

        return [
            'first_name' => $name, 'last_name' => $name,
            'email' => ['required', 'string', 'email', 'regex:/^[^@\\s]+@[^@\\s]+\\.[^@\\s]+$/', 'max:255'],
            'contact_no' => ['required', 'string', 'regex:/^(09[0-9]{9}|\\+639[0-9]{9})$/'],
            'password' => [$this->oauth() ? 'nullable' : 'required', 'string', Password::min(8)->mixedCase()->numbers()->symbols()],
            'password_confirmation' => [$this->oauth() ? 'nullable' : 'required', 'string', function ($attribute, $value, $fail) {
                if ($this->filled('password') && $value !== $this->input('password')) {
                    $fail('The passwords do not match.');
                }
            }],
            'business_name' => ['required', 'string', 'max:255'],
            'registration_number' => ['required', 'string', 'regex:/^[A-Z]{2}[0-9]{9}$/'],
            'tin' => ['required', 'string', 'regex:/^[0-9]{3}-[0-9]{3}-[0-9]{3}-[0-9]{3}$/'],
            'position' => ['required', 'string', 'max:100'],
            'region_code' => ['required', 'regex:/^[0-9]{10}$/'],
            // PSGC has no province for NCR and independent cities; the UI displays that explicitly.
            'province_code' => ['nullable', 'regex:/^[0-9]{10}$/'],
            'province_selection' => ['required', 'string'],
            'city_code' => ['required', 'regex:/^[0-9]{10}$/'],
            'barangay_code' => ['required', 'regex:/^[0-9]{10}$/'],
            'street' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'regex:/^[0-9]{4}$/'],
            'service_region_codes' => ['required', 'array', 'min:1', 'max:20'],
            'service_region_codes.*' => ['required', 'distinct', 'regex:/^[0-9]{10}$/'],
            'id_doc' => ['required', File::types(['jpg', 'jpeg', 'png', 'pdf'])->extensions(['jpg', 'jpeg', 'png', 'pdf'])->max(5120)],
            'sec_doc' => ['required', File::types(['jpg', 'jpeg', 'png', 'pdf'])->extensions(['jpg', 'jpeg', 'png', 'pdf'])->max(5120)],
            'bir_doc' => ['required', File::types(['jpg', 'jpeg', 'png', 'pdf'])->extensions(['jpg', 'jpeg', 'png', 'pdf'])->max(5120)],
            'permit_doc' => ['required', File::types(['jpg', 'jpeg', 'png', 'pdf'])->extensions(['jpg', 'jpeg', 'png', 'pdf'])->max(5120)],
            'terms' => ['accepted'], 'privacy' => ['accepted'],
            'request_key' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return ['first_name.regex' => 'Use letters, spaces, hyphens, apostrophes or periods only.',
            'last_name.regex' => 'Use letters, spaces, hyphens, apostrophes or periods only.',
            'registration_number.regex' => 'Use 2 letters followed by 9 digits.',
            'tin.regex' => 'Use a 12-digit TIN in the format 000-000-000-000.',
            '*.mimes' => 'Upload a JPG, PNG or PDF file.', '*.extensions' => 'Upload a JPG, PNG or PDF file.',
            '*.max' => 'The value exceeds the allowed maximum. Uploads must be 5 MB or less.'];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $service = app(LogisticsOnboarding::class);
            foreach ($service->available($this->all()) as $field => $message) {
                $validator->errors()->add($field, $message);
            }
            try {
                app(PhilippineAddressService::class)->validate($this->all());
                $selection = $this->input('province_selection');
                if ($selection !== ($this->input('province_code') ?: 'independent')) {
                    throw ValidationException::withMessages(['province_selection' => 'Choose the province for your selected city.']);
                }
                $service->regionCities($this->input('service_region_codes'));
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    $validator->errors()->add($field, $messages[0]);
                }
            }
        }];
    }
}
