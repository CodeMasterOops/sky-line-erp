<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class ApplyCompanyCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_category_id' => ['nullable', 'integer', 'exists:company_categories,id'],
            'apply_defaults' => ['boolean'],
            // Off by default: correcting a company's industry should not take
            // modules away from a tenant already using them.
            'disable_others' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_category_id.exists' => 'The selected category does not exist.',
        ];
    }
}
