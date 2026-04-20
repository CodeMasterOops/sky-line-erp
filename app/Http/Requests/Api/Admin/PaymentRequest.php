<?php

namespace App\Http\Requests\Api\Admin;

use App\Models\Bill;
use App\Tenancy\TRule;
use App\Models\Expense;
use App\Enums\StatusEnum;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_no' => ['nullable', 'string', 'max:255'],
            'payment_date' => ['required', 'date'],
            'party_id' => ['required', TRule::exists('parties', 'id')->withoutTrashed()],
            'payment_mode_id' => ['required', TRule::exists('payment_modes', 'id')->withoutTrashed()],
            'account_id' => ['required', TRule::exists('accounts', 'id')->withoutTrashed()],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in([StatusEnum::DRAFT->value, StatusEnum::APPROVED->value])],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.payable_type' => ['required', Rule::in(['bill', 'expense'])],
            'allocations.*.payable_id' => ['required', 'integer'],
            'allocations.*.amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $allocations = $this->input('allocations', []);
            foreach ($allocations as $index => $allocation) {
                $type = $allocation['payable_type'] ?? null;
                $id = $allocation['payable_id'] ?? null;
                if (! $type || ! $id) {
                    continue;
                }
                $exists = match ($type) {
                    'bill' => Bill::whereKey($id)->whereNull('deleted_at')->exists(),
                    'expense' => Expense::whereKey($id)->whereNull('deleted_at')->exists(),
                    default => false,
                };
                if (! $exists) {
                    $validator->errors()->add("allocations.$index.payable_id", 'Invalid payable id.');
                }
            }
        });
    }
}
