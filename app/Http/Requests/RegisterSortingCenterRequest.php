<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class RegisterSortingCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'id_doc' => ['required', File::types(['jpg', 'jpeg', 'png', 'pdf'])->max(5 * 1024 * 1024)],
            'permit_doc' => ['required', File::types(['jpg', 'jpeg', 'png', 'pdf'])->max(5 * 1024 * 1024)],
        ];
    }

    public function messages(): array
    {
        return [
            'business_name.required' => 'Business name is required.',
            'business_name.max' => 'Business name must not exceed 255 characters.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email is already registered.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'id_doc.required' => 'ID document upload is required.',
            'id_doc.types' => 'ID document must be a JPG, PNG, or PDF file.',
            'id_doc.max' => 'ID document must not exceed 5MB.',
            'permit_doc.required' => 'Business/DTI permit upload is required.',
            'permit_doc.types' => 'Permit document must be a JPG, PNG, or PDF file.',
            'permit_doc.max' => 'Permit document must not exceed 5MB.',
        ];
    }
}
