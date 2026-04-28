<?php

namespace App\Http\Requests\Api\Admin;

use App\Tenancy\TRule;
use App\Enums\StatusEnum;
use App\Enums\TaxTypeEnum;
use App\Enums\TaxLineTypeEnum;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class BillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bill_no' => ['nullable', 'string', 'max:255'],
            'bill_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:bill_date'],
            'party_id' => ['nullable', TRule::exists('parties', 'id')->withoutTrashed()],
            'purchase_order_id' => ['nullable', TRule::exists('purchase_orders', 'id')->withoutTrashed()],
            'remarks' => ['nullable', 'string'],
            'order_discount_type' => ['nullable', Rule::in(['fixed', 'percent'])],
            'order_discount_value' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in([StatusEnum::DRAFT->value, StatusEnum::APPROVED->value])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', TRule::exists('product_variants', 'id')->withoutTrashed()],
            'items.*.warehouse_id' => ['required', TRule::exists('warehouses', 'id')->withoutTrashed()],
            'items.*.unit_id' => ['nullable', TRule::exists('units', 'id')->withoutTrashed()],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.line_discount_type' => ['nullable', Rule::in(['fixed', 'percent'])],
            'items.*.line_discount_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_id' => [
                'nullable',
                TRule::exists('taxes', 'id')->withoutTrashed(),
                function ($attribute, $value, $fail) {
                    if ($value === null) {
                        return;
                    }
                    $tax = \App\Models\Tax::withoutGlobalScopes()->find($value);
                    if ($tax && $tax->type === TaxTypeEnum::TDS) {
                        $fail('TDS taxes cannot be applied to bill lines. TDS is a withholding tax deducted at payment time.');
                    }
                },
            ],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_line_type' => ['nullable', Rule::enum(TaxLineTypeEnum::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $orderType = $this->input('order_discount_type', 'fixed');
            $ov = $this->input('order_discount_value');
            if ($orderType === 'percent' && $ov !== null && (float) $ov > 100) {
                $v->errors()->add('order_discount_value', 'Order discount may not be greater than 100%.');
            }

            foreach ($this->input('items', []) as $i => $item) {
                $t = $item['line_discount_type'] ?? 'fixed';
                if ($t === 'percent' && isset($item['line_discount_value']) && (float) $item['line_discount_value'] > 100) {
                    $v->errors()->add("items.$i.line_discount_value", 'Line discount may not be greater than 100%.');
                }
            }
        });
    }
}
