<?php

namespace App\Http\Requests\Api\Admin\Sales;

use App\Tenancy\TRule;
use Illuminate\Validation\Rule;
use App\Enums\PaymentMethodEnum;
use Illuminate\Foundation\Http\FormRequest;

class AdvanceReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'advance_no' => ['nullable', 'string', 'max:50'],
            'advance_date' => ['required', 'date'],
            'party_id' => ['required', TRule::exists('parties', 'id')->withoutTrashed()],
            'payment_method' => ['required', Rule::enum(PaymentMethodEnum::class)],
            'account_id' => ['required', TRule::exists('accounts', 'id')->withoutTrashed()],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
