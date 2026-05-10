<?php

namespace App\Http\Requests\Api\Admin\Accounting;

use App\Tenancy\TRule;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class ChequeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'party_id' => ['required', TRule::exists('parties', 'id')->withoutTrashed()],
            'bank_account_id' => ['nullable', TRule::exists('bank_accounts', 'id')->withoutTrashed()],
            'cheque_no' => ['required', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'cheque_date' => ['required', 'date'],
            'deposit_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'type' => ['required', Rule::in(['payable', 'receivable'])],
            'reference_type' => ['nullable', 'string', 'max:255'],
            'reference_id' => ['nullable', 'integer'],
            'remarks' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $type = $this->input('type');

            if ($type === 'receivable' && ! $this->filled('bank_name')) {
                $validator->errors()->add('bank_name', 'The bank name field is required for receivable cheques.');
            }

            if ($type === 'payable' && ! $this->filled('bank_account_id')) {
                $validator->errors()->add('bank_account_id', 'The bank account field is required for payable cheques.');
            }
        });
    }
}
