<?php

namespace App\Http\Requests\Api\Admin;

use App\Tenancy\TRule;
use App\Enums\StatusEnum;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class ReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receipt_no' => ['nullable', 'string', 'max:255'],
            'receipt_date' => ['required', 'date'],
            'party_id' => ['required', TRule::exists('parties', 'id')->withoutTrashed()],
            'payment_method' => ['required', 'string', 'max:255'],
            'account_id' => ['required', TRule::exists('accounts', 'id')->withoutTrashed()],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in([StatusEnum::DRAFT->value, StatusEnum::APPROVED->value])],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.invoice_id' => ['required', TRule::exists('invoices', 'id')->withoutTrashed()],
            'allocations.*.amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
