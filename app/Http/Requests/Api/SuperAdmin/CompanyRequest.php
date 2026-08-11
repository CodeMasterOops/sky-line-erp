<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Models\Ward;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'company_category_id.required' => 'Choose the industry this company is in — it decides which modules they start with.',
            'company_category_id.exists' => 'The selected industry category does not exist.',
        ];
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
            'address' => ['required', 'string', 'max:500'],
            'ward_id' => ['required', 'integer', Rule::exists(Ward::class, 'id')],
            // The industry the company is in. Required, because it decides
            // which modules the company starts with — leaving it to a fallback
            // means somebody has to go and fix the module set afterwards.
            'company_category_id' => ['required', 'integer', Rule::exists('company_categories', 'id')],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'user_name' => ['required', 'string', 'max:255'],
            'user_phone' => ['nullable'],
        ];

        return match ($this->method()) {
            'POST' => array_merge($validations, [
                'code' => ['nullable', Rule::unique('companies')],
                'email' => ['required', 'email', Rule::unique('companies', 'email')],
                'user_email' => ['required', 'email', Rule::unique('users', 'email')],
                'password' => ['nullable', 'min:7', 'confirmed'],
            ]),
            'PUT' => array_merge($validations, [
                'code' => ['nullable', Rule::unique('companies')->ignore($this->company)],
                'email' => ['required', 'email', Rule::unique('companies', 'email')->ignore($this->company)],
                'user_email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->company->admin)],
            ])
        };
    }
}
