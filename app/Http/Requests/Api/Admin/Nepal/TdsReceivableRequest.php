<?php

namespace App\Http\Requests\Api\Admin\Nepal;

use App\Tenancy\TRule;
use App\Enums\TdsCategoryEnum;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class TdsReceivableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'party_id' => ['required', TRule::exists('parties', 'id')->withoutTrashed()],
            'tds_deduction_id' => ['nullable', TRule::exists('tds_deductions', 'id')],
            'certificate_no' => ['nullable', 'string', 'max:100'],
            'certificate_date' => ['nullable', 'date'],
            'tds_category' => ['nullable', Rule::enum(TdsCategoryEnum::class)],
            'tds_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'base_amount' => ['required', 'numeric', 'min:0.01'],
            'tds_amount' => ['required', 'numeric', 'min:0.01'],
            'period_month' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
