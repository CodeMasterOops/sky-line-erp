<?php

namespace App\Http\Requests\Api\Admin\Sales;

use App\Tenancy\TRule;
use Illuminate\Foundation\Http\FormRequest;

class CustomerAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'party_id' => ['required', TRule::exists('parties', 'id')->withoutTrashed()],
            'advance_no' => ['nullable', 'string', 'max:100'],
            'advance_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string'],
            'account_id' => ['required', TRule::exists('accounts', 'id')->withoutTrashed()],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
