<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'phone' => ['nullable'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore(auth('admin')->user())],
            'profile_photo' => ['nullable', 'image'],
        ];
    }
}
