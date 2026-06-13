<?php

namespace App\Http\Requests\Api\Admin\Sales;

use App\Tenancy\TRule;
use App\Enums\StatusEnum;
use App\Enums\ChargeTypeEnum;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class QuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quotation_no' => ['nullable', 'string', 'max:255'],
            'quotation_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:quotation_date'],
            'party_id' => ['required', TRule::exists('parties', 'id')->withoutTrashed()],
            'remarks' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in([StatusEnum::DRAFT->value, StatusEnum::APPROVED->value])],
            'order_discount_type' => ['nullable', Rule::in(['fixed', 'percent'])],
            'order_discount_value' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', TRule::exists('product_variants', 'id')->withoutTrashed()],
            'items.*.unit_id' => ['nullable', TRule::exists('units', 'id')->withoutTrashed()],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.line_discount_type' => ['nullable', Rule::in(['fixed', 'percent'])],
            'items.*.line_discount_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_id' => ['nullable', TRule::exists('taxes', 'id')->withoutTrashed()],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'charges' => ['nullable', 'array'],
            'charges.*.name' => ['required_with:charges.*', 'string', 'max:100'],
            'charges.*.charge_type' => ['required_with:charges.*', Rule::enum(ChargeTypeEnum::class)],
            'charges.*.account_id' => ['required_with:charges.*', TRule::exists('accounts', 'id')],
            'charges.*.amount' => ['required_with:charges.*', 'numeric', 'min:0'],
            'charges.*.tax_id' => ['nullable', TRule::exists('taxes', 'id')->withoutTrashed()],
            'charges.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
