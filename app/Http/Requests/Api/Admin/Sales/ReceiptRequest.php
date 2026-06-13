<?php

namespace App\Http\Requests\Api\Admin\Sales;

use App\Tenancy\TRule;
use App\Enums\StatusEnum;
use App\Enums\ChequeStatusEnum;
use Illuminate\Validation\Rule;
use App\Enums\PaymentMethodEnum;
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
            // Legacy single-payment fields (kept for backward compat; preferred: use payments[])
            'payment_method' => ['nullable', 'string', 'max:255'],
            'account_id' => ['nullable', TRule::exists('accounts', 'id')->withoutTrashed()],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in([StatusEnum::DRAFT->value, StatusEnum::APPROVED->value])],
            // Split payment legs (Phase 4a)
            'payments' => ['nullable', 'array'],
            'payments.*.payment_method' => ['required_with:payments.*', Rule::enum(PaymentMethodEnum::class)],
            'payments.*.account_id' => ['required_with:payments.*', TRule::exists('accounts', 'id')->withoutTrashed()],
            'payments.*.amount' => ['required_with:payments.*', 'numeric', 'min:0.01'],
            'payments.*.reference_no' => ['nullable', 'string', 'max:100'],
            'payments.*.cheque_date' => ['nullable', 'date'],
            'payments.*.cheque_status' => ['nullable', Rule::enum(ChequeStatusEnum::class)],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.invoice_id' => ['required', TRule::exists('invoices', 'id')->withoutTrashed()],
            'allocations.*.amount' => ['required', 'numeric', 'min:0.01'],
            'allocations.*.tds_id' => ['nullable', TRule::exists('taxes', 'id')->withoutTrashed()],
            'allocations.*.tds_deducted' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
