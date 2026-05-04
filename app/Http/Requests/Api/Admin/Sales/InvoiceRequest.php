<?php

namespace App\Http\Requests\Api\Admin\Sales;

use App\Tenancy\TRule;
use App\Enums\StatusEnum;
use App\Enums\TaxTypeEnum;
use App\Enums\TaxLineTypeEnum;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_no' => ['nullable', 'string', 'max:255'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'party_id' => ['nullable', TRule::exists('parties', 'id')->withoutTrashed()],
            'quotation_id' => ['nullable', TRule::exists('quotations', 'id')->withoutTrashed()],
            'sales_order_id' => ['nullable', TRule::exists('sales_orders', 'id')->withoutTrashed()],
            'reference_type' => ['nullable', 'string', 'max:255', 'required_with:reference_id'],
            'reference_id' => ['nullable', 'integer', 'min:1', 'required_with:reference_type'],
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
                        $fail('TDS taxes cannot be applied to invoice lines. TDS is a withholding tax deducted at payment time.');
                    }
                },
            ],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_line_type' => ['nullable', Rule::enum(TaxLineTypeEnum::class)],
        ];
    }
}
