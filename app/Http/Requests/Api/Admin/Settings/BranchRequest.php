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
        $codeRule = TRule::unique('branches')->withoutTrashed();

        if ($this->method() !== 'POST') {
            $codeRule->ignore($this->branch);
        }

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:20', $codeRule],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'pan' => ['nullable', 'string', 'max:20'],
            'is_head_office' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
