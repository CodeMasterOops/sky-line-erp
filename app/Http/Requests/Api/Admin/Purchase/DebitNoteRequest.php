<?php

namespace App\Http\Requests\Api\Admin\Purchase;

use App\Models\Bill;
use App\Tenancy\TRule;
use App\Enums\StatusEnum;
use Illuminate\Validation\Rule;
use App\Rules\WithinActiveFiscalYear;
use App\Http\Validation\ProductLineRules;
use Illuminate\Foundation\Http\FormRequest;

class DebitNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'debit_note_no' => ['nullable', 'string', 'max:255'],
            'debit_note_date' => ['required', 'date', new WithinActiveFiscalYear],
            'party_id' => ['required', TRule::exists('parties', 'id')->withoutTrashed()],
            'bill_id' => ['nullable', TRule::exists('bills', 'id')->withoutTrashed()],
            'remarks' => ['nullable', 'string'],
            'order_discount_type' => ['nullable', Rule::in(['fixed', 'percent'])],
            'order_discount_value' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in([StatusEnum::DRAFT->value, StatusEnum::APPROVED->value])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => [
                'required',
                TRule::exists('product_variants', 'id')->withoutTrashed(),
                ...ProductLineRules::physicalProductVariantId(__('Services cannot be returned on debit notes.')),
            ],
            'items.*.warehouse_id' => ['required', TRule::exists('warehouses', 'id')->withoutTrashed()],
            'items.*.unit_id' => ['nullable', TRule::exists('units', 'id')->withoutTrashed()],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.tax_id' => ['nullable', TRule::exists('taxes', 'id')->withoutTrashed()],
            'items.*.tax_group_id' => ['nullable', TRule::exists('tax_groups', 'id')->withoutTrashed()],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.line_discount_type' => ['nullable', Rule::in(['fixed', 'percent'])],
            'items.*.line_discount_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $billId = $this->input('bill_id');
            if (! $billId) {
                return;
            }

            $companyId = auth('admin')->user()?->company_id;
            $bill = Bill::where('company_id', $companyId)
                ->where('status', StatusEnum::APPROVED->value)
                ->whereNull('voided_at')
                ->with('billItems:id,bill_id,product_variant_id,quantity')
                ->find($billId);

            if (! $bill) {
                $validator->errors()->add('bill_id', 'The selected bill is not a valid approved bill.');

                return;
            }

            $billedQtyByVariant = $bill->billItems
                ->groupBy('product_variant_id')
                ->map(fn ($items) => $items->sum('quantity'));

            foreach ($this->input('items', []) as $index => $item) {
                $variantId = $item['product_variant_id'] ?? null;
                $qty = (int) ($item['quantity'] ?? 0);

                if (! $variantId) {
                    continue;
                }

                if (! $billedQtyByVariant->has($variantId)) {
                    $validator->errors()->add(
                        "items.{$index}.product_variant_id",
                        'This product was not on the selected bill.',
                    );

                    continue;
                }

                if ($qty > $billedQtyByVariant->get($variantId)) {
                    $validator->errors()->add(
                        "items.{$index}.quantity",
                        'Return quantity exceeds the quantity on the linked bill.',
                    );
                }
            }
        });
    }
}
