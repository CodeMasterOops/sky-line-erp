<?php

namespace App\Http\Requests\Api\Admin;

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
                'name' => ['required', 'string', 'max:255', TRule::unique('departments')->withoutTrashed()],
                'code' => ['nullable', 'string', 'max:50', TRule::unique('departments')->withoutTrashed()],
            ]),
            'PUT' => array_merge($validations, [
                'name' => ['required', 'string', 'max:255', TRule::unique('departments')->withoutTrashed()->ignore($this->department)],
                'code' => ['nullable', 'string', 'max:50', TRule::unique('departments')->withoutTrashed()->ignore($this->department)],
            ]),
            default => $validations,
        };
    }
}
