<?php

namespace App\Http\Requests\Api\Admin\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class StartReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'statement_opening_balance' => ['nullable', 'numeric'],
            'statement_closing_balance' => ['required', 'numeric'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
