<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CompanyBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->route('company')->id;

        $shared = [
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'pan' => ['nullable', 'string', 'max:20'],
            'is_head_office' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];

        $codeRule = Rule::unique('branches', 'code')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at');

        return match ($this->method()) {
            'POST' => array_merge($shared, [
                'code' => ['nullable', 'string', 'max:20', $codeRule],
            ]),
            'PUT', 'PATCH' => array_merge($shared, [
                'code' => ['required', 'string', 'max:20', $codeRule->ignore($this->route('branch')->id)],
            ]),
            default => $shared,
        };
    }
}
