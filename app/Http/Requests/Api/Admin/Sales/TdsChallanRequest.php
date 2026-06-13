<?php

namespace App\Http\Requests\Api\Admin\Sales;

use App\Tenancy\TRule;
use Illuminate\Foundation\Http\FormRequest;

class TdsChallanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'challan_no' => ['nullable', 'string', 'max:50'],
            'challan_date' => ['required', 'date'],
            'party_id' => ['required', TRule::exists('parties', 'id')->withoutTrashed()],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'payment_date' => ['nullable', 'date'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string'],
            'deduction_ids' => ['required', 'array', 'min:1'],
            'deduction_ids.*' => ['required', 'integer', TRule::exists('tds_deductions', 'id')],
        ];
    }
}
