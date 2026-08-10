<?php

namespace App\Http\Requests\Api\Admin\HR;

use App\Tenancy\TRule;
use Illuminate\Foundation\Http\FormRequest;

class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validations = [
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ];

        return match ($this->method()) {
            'POST' => array_merge($validations, [
                'name' => ['required', 'string', 'max:255', TRule::unique('departments')],
                'code' => ['nullable', 'string', 'max:50', TRule::unique('departments')],
            ]),
            'PUT' => array_merge($validations, [
                'name' => ['required', 'string', 'max:255', TRule::unique('departments')->ignore($this->department)],
                'code' => ['nullable', 'string', 'max:50', TRule::unique('departments')->ignore($this->department)],
            ]),
            default => $validations,
        };
    }
}
