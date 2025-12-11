<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class FiscalYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->method()) {
            'POST' => [
                'year_name' => ['required', 'string', 'max:255', Rule::unique('fiscal_years')->withoutTrashed()],
                'year_code' => ['required', 'string', 'max:255', Rule::unique('fiscal_years')->withoutTrashed()],
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date', 'after:start_date'],
            ],
            'PUT' => [
                'year_name' => ['required', 'string', 'max:255', Rule::unique('fiscal_years')->withoutTrashed()->ignore($this->fiscal_year)],
                'year_code' => ['required', 'string', 'max:255', Rule::unique('fiscal_years')->withoutTrashed()->ignore($this->fiscal_year)],
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date', 'after:start_date'],
            ]
        };
    }
}
