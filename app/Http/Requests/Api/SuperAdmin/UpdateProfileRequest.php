<?php

namespace App\Http\Requests\Api\SuperAdmin;

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
            'email' => ['required', 'email', Rule::unique('super_admins', 'email')->ignore(auth('super_admin')->user())],
        ];
    }
}
