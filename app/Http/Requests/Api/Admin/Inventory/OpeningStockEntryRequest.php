<?php

namespace App\Http\Requests\Api\Admin\Inventory;

use App\Tenancy\TRule;
use App\Enums\StatusEnum;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use App\Rules\WithinActiveFiscalYear;
use Illuminate\Foundation\Http\FormRequest;

class OpeningStockEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference_no' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date', new WithinActiveFiscalYear],
            'warehouse_id' => ['required', TRule::exists('warehouses', 'id')->withoutTrashed()],
            'remarks' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in([StatusEnum::DRAFT->value, StatusEnum::APPROVED->value])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', TRule::exists('product_variants', 'id')->withoutTrashed()],
            'items.*.unit_id' => ['nullable', TRule::exists('units', 'id')->withoutTrashed()],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.batch_id' => ['nullable', 'integer', TRule::exists('batches', 'id')],
            'items.*.batch_no' => ['nullable', 'string', 'max:100'],
            'items.*.expiry_date' => ['nullable', 'date', 'after:today'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $seenVariantIds = [];
            foreach ($this->input('items', []) as $i => $item) {
                $variantId = $item['product_variant_id'] ?? null;
                if ($variantId === null) {
                    continue;
                }
                if (in_array($variantId, $seenVariantIds, true)) {
                    $validator->errors()->add("items.$i.product_variant_id", __('Duplicate product variant in the same opening stock entry. Each variant may only appear once.'));
                } else {
                    $seenVariantIds[] = $variantId;
                }
            }
        });
    }
}
