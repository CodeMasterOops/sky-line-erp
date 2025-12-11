<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validations = [
            'company_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['required', 'string', 'max:255'],
            'pan' => ['nullable'],
            'phone' => ['nullable'],
            'landline' => ['nullable'],
            'website' => ['nullable'],
            'address' => ['nullable'],
            'user_name' => ['required', 'string', 'max:255'],
            'user_phone' => ['nullable'],
        ];

        return match ($this->method()) {
            'POST' => array_merge($validations, [
                'code' => ['nullable', Rule::unique('companies')->withoutTrashed()],
                'email' => ['required', 'email', Rule::unique('companies', 'email')->withoutTrashed()],
                'user_email' => ['required', 'email', Rule::unique('users', 'email')],
                'password' => ['nullable', 'min:7', 'confirmed'],
            ]),
            'PUT' => array_merge($validations, [
                'code' => ['nullable', Rule::unique('companies')->withoutTrashed()->ignore($this->company)],
                'email' => ['required', 'email', Rule::unique('companies', 'email')->withoutTrashed()->ignore($this->company)],
                'user_email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->company->admin)],
            ])
        };
    }
}
