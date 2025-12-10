<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:super_admin'],
            'password' => ['required', 'confirmed'],
        ];
    }
}
