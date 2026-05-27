<?php

namespace App\Http\Requests\Api\Admin\Settings;

use App\Tenancy\TRule;
use Illuminate\Foundation\Http\FormRequest;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $shared = [
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'pan' => ['nullable', 'string', 'max:20'],
            'is_head_office' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];

        return match ($this->method()) {
            'POST' => array_merge($shared, [
                'code' => ['nullable', 'string', 'max:20', TRule::unique('branches')->withoutTrashed()],
            ]),
            'PUT' => array_merge($shared, [
                'code' => ['required', 'string', 'max:20', TRule::unique('branches')->withoutTrashed()->ignore($this->branch)],
            ]),
            default => $shared,
        };
    }
}
