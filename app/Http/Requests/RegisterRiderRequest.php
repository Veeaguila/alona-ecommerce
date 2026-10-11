<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class RegisterRiderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sorting_center_id' => [
                'required',
                'integer',
                Rule::exists('sorting_centers', 'id')->where('approval_status', 'approved'),
            ],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_initial' => ['nullable', 'string', 'size:1'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'contact' => ['required', 'string', 'max:30'],
            'sex' => ['required', 'string', 'max:20'],
            'birthday' => ['required', 'date', 'before:today'],
            'province' => ['required', 'string', 'max:100'],
            'municipality' => ['required', 'string', 'max:100'],
            'barangay' => ['required', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:50'],
            'vehicle_type' => ['required', 'string', 'max:30'],
            'plate_number' => ['required', 'string', 'max:20'],
            'or_cr_doc' => ['required', File::types(['jpg', 'jpeg', 'png', 'pdf'])->max(5 * 1024 * 1024)],
            'license_doc' => ['required', File::types(['jpg', 'jpeg', 'png', 'pdf'])->max(5 * 1024 * 1024)],
        ];
    }
}
