<?php

namespace App\Http\Requests\Api\Admin\Settings;

use App\Tenancy\TRule;
use Illuminate\Foundation\Http\FormRequest;

class PaymentModeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validations = [
            'is_active' => ['nullable', 'boolean'],
            'bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
        ];

        return match ($this->method()) {
            'POST' => array_merge($validations, [
                'name' => ['required', 'string', 'max:255', TRule::unique('payment_modes')],
            ]),
            'PUT' => array_merge($validations, [
                'name' => ['required', 'string', 'max:255', TRule::unique('payment_modes')->ignore($this->payment_mode)],
            ]),
            default => $validations,
        };
    }
}
