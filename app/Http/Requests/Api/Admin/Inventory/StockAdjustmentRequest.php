<?php

namespace App\Http\Requests\Api\Admin\Inventory;

use App\Tenancy\TRule;
use App\Enums\StatusEnum;
use App\Enums\ProductTypeEnum;
use App\Models\ProductVariant;
use Illuminate\Validation\Rule;
use App\Enums\StockDirectionEnum;
use Illuminate\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'reference_no' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'warehouse_id' => ['required', TRule::exists('warehouses', 'id')->withoutTrashed()],
            'remarks' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in([StatusEnum::DRAFT->value, StatusEnum::APPROVED->value])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', TRule::exists('product_variants', 'id')->withoutTrashed()],
            'items.*.unit_id' => ['nullable', TRule::exists('units', 'id')->withoutTrashed()],
            'items.*.direction' => ['required', Rule::in([StockDirectionEnum::IN->value, StockDirectionEnum::OUT->value])],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ];

        return match ($this->method()) {
            'POST', 'PUT' => $rules,
            default => $rules,
        };
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $seenVariantIds = [];
            foreach ($this->input('items', []) as $i => $item) {
                $variantId = $item['product_variant_id'] ?? null;

                if ($variantId !== null) {
                    if (in_array($variantId, $seenVariantIds, true)) {
                        $validator->errors()->add("items.$i.product_variant_id", __('Duplicate product variant in the same adjustment. Each variant may only appear once.'));
                    } else {
                        $seenVariantIds[] = $variantId;
                    }
                }

                if ($variantId) {
                    $variant = ProductVariant::withoutGlobalScopes()
                        ->with('product:id,product_type')
                        ->find($variantId);

                    if ($variant?->product?->product_type === ProductTypeEnum::SERVICE) {
                        $validator->errors()->add("items.$i.product_variant_id", __('Service products cannot have stock adjusted.'));
                    }
                }

                if (($item['direction'] ?? '') === StockDirectionEnum::IN->value) {
                    $cost = $item['unit_cost'] ?? null;
                    if ($cost === null || $cost === '') {
                        $validator->errors()->add("items.$i.unit_cost", __('Unit cost is required for stock adjustment in.'));

                        continue;
                    }
                    if ((float) $cost < 0) {
                        $validator->errors()->add("items.$i.unit_cost", __('Unit cost must be at least 0.'));
                    }
                }
            }
        });
    }
}
