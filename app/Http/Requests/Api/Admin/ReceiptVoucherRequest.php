<?php

namespace App\Http\Requests\Api\Admin;

use App\Tenancy\TRule;
use App\Enums\StatusEnum;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class ReceiptVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference_no' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in([StatusEnum::DRAFT->value, StatusEnum::APPROVED->value])],
            'deposited_to_account_id' => ['required', TRule::exists('accounts', 'id')->withoutTrashed()],
            'items' => ['required', 'array', 'min:1'],
            'items.*.account_id' => ['required', TRule::exists('accounts', 'id')->withoutTrashed()],
            'items.*.amount' => ['required', 'numeric', 'min:0.01'],
            'items.*.remarks' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);
            $total = 0;

            foreach ($items as $index => $item) {
                $amount = (float) ($item['amount'] ?? 0);

                if ($amount <= 0) {
                    $validator->errors()->add("items.$index.amount", 'Amount must be greater than zero.');
                }

                $total += $amount;
            }

            if ($total <= 0) {
                $validator->errors()->add('items', 'Total amount must be greater than zero.');
            }
        });
    }
}
