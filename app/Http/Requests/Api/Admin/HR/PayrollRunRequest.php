<?php

namespace App\Http\Requests\Api\Admin\HR;

use Illuminate\Foundation\Http\FormRequest;

class PayrollRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fiscal_year_id' => ['required', 'integer', 'exists:fiscal_years,id'],
            'bs_year' => ['nullable', 'integer', 'min:2000', 'max:2095', 'required_with:bs_month'],
            'bs_month' => ['nullable', 'integer', 'min:1', 'max:12', 'required_with:bs_year'],
            'month' => ['required_without:bs_month', 'nullable', 'integer', 'min:1', 'max:12'],
        ];
    }
}
