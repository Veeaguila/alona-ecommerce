<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120', "regex:/^[\pL\s\-'.]+$/u"],
            'last_name' => ['required', 'string', 'min:2', 'max:50', "regex:/^[\pL\s\-'.]+$/u"],
            'first_name' => ['required', 'string', 'min:2', 'max:50', "regex:/^[\pL\s\-'.]+$/u"],
            'middle_initial' => ['nullable', 'string', 'max:2', "regex:/^[\pL]?\.?$/u"],
            'sex' => ['required', 'in:Male,Female,Other'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'contact_no' => [
                'required',
                'string',
                'regex:/^(09|\+639)\d{9}$/',
            ],
            'birthday' => ['required', 'date', 'before:today'],
            'province' => ['required', 'string', 'max:255'],
            'municipality' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:255'],
            'street_address' => ['required', 'string', 'max:500'],
            'profile_photo' => ['nullable', File::image()->max(5 * 1024)],
            'avatar' => ['nullable', File::image()->max(5 * 1024)],
            'id' => ['nullable', File::types(['jpg', 'jpeg', 'png', 'pdf'])->max(5 * 1024)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.min' => 'The name must be at least 2 characters.',
            'name.regex' => 'The name may only contain letters, spaces, hyphens, and apostrophes.',
            'first_name.min' => 'The first name must be at least 2 characters.',
            'first_name.regex' => 'The first name may only contain letters, spaces, hyphens, and apostrophes.',
            'last_name.min' => 'The last name must be at least 2 characters.',
            'last_name.regex' => 'The last name may only contain letters, spaces, hyphens, and apostrophes.',
            'contact_no.regex' => 'The phone number must be a valid Philippine mobile number (e.g. 09171234567 or +639171234567).',
            'profile_photo.image' => 'The profile photo must be a valid image.',
            'profile_photo.max' => 'The profile photo may not be greater than 5MB.',
            'avatar.image' => 'The avatar must be a valid image.',
            'avatar.max' => 'The avatar may not be greater than 5MB.',
        ];
    }
}
