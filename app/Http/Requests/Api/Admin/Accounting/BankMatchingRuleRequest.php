<?php

namespace App\Http\Requests\Api\Admin\Accounting;

use App\Tenancy\TRule;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class BankMatchingRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_account_id' => ['nullable', 'integer', TRule::exists('bank_accounts', 'id')->withoutTrashed()],
            'priority' => ['nullable', 'integer', 'min:1'],
            'match_field' => ['required', Rule::in(['description', 'reference'])],
            'operator' => ['required', Rule::in(['contains', 'regex', 'equals'])],
            'pattern' => ['required', 'string', 'max:255'],
            'target_account_id' => ['required', 'integer', TRule::exists('accounts', 'id')->withoutTrashed()],
            'set_status' => ['nullable', Rule::in(['matched'])],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
