<?php

namespace App\Http\Requests\Api\Admin\Accounting;

use App\Tenancy\TRule;
use Illuminate\Foundation\Http\FormRequest;

class BankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', TRule::exists('accounts', 'id')->withoutTrashed()],
            'branch_id' => ['nullable', 'integer', TRule::exists('branches', 'id')],
            'bank_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:50'],
            'branch' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'max:3'],
            'opening_balance' => ['nullable', 'numeric'],
            'opening_balance_date' => ['nullable', 'date'],
        ];
    }
}
