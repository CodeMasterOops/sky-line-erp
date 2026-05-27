<?php

namespace App\Http\Requests\Api\Admin;

use App\Tenancy\TRule;
use Illuminate\Validation\Rule;
use App\Http\Validation\ProductLineRules;
use Illuminate\Foundation\Http\FormRequest;

class PosCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['nullable', 'integer', TRule::exists('warehouses', 'id')->withoutTrashed()],
            'payment_method' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => [
                'required',
                'integer',
                TRule::exists('product_variants', 'id')->withoutTrashed(),
                ...ProductLineRules::physicalProductVariantId(__('Services cannot be sold through POS.')),
            ],
            'items.*.warehouse_id' => [
                'required',
                'integer',
                TRule::exists('warehouses', 'id')->withoutTrashed(),
            ],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.unit_id' => ['nullable', 'integer', TRule::exists('units', 'id')->withoutTrashed()],
            'items.*.tax_id' => ['nullable', 'integer', TRule::exists('taxes', 'id')->withoutTrashed()],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.line_discount_type' => ['nullable', Rule::in(['fixed', 'percent'])],
            'items.*.line_discount_value' => ['nullable', 'numeric', 'min:0'],
            'party_id' => ['nullable', 'integer', TRule::exists('parties', 'id')->withoutTrashed()],
            'order_discount_type' => ['nullable', Rule::in(['fixed', 'percent'])],
            'order_discount_value' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
