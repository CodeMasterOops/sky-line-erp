<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Validation\Rule;
use App\Enums\SalaryComponentTypeEnum;
use Illuminate\Foundation\Http\FormRequest;

class SalaryComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(SalaryComponentTypeEnum::class)],
            'calculation_type' => ['required', Rule::in(['fixed', 'percentage'])],
            'is_taxable' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
