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
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ];
    }
}
