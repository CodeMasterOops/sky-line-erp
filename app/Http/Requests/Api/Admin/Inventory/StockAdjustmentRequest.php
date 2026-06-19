<?php

namespace App\Http\Requests\Api\Admin\Inventory;

use App\Tenancy\TRule;
use App\Enums\StatusEnum;
use App\Enums\ProductTypeEnum;
use App\Models\ProductVariant;
use Illuminate\Validation\Rule;
use App\Enums\StockDirectionEnum;
use Illuminate\Validation\Validator;
use App\Rules\WithinActiveFiscalYear;
use App\Services\Inventory\BatchGuard;
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
            'date' => ['required', 'date', new WithinActiveFiscalYear],
            'warehouse_id' => ['nullable', TRule::exists('warehouses', 'id')->withoutTrashed()],
            'remarks' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in([StatusEnum::DRAFT->value, StatusEnum::APPROVED->value])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.warehouse_id' => ['required_without:warehouse_id', 'nullable', 'integer', TRule::exists('warehouses', 'id')->withoutTrashed()],
            'items.*.product_variant_id' => ['required', TRule::exists('product_variants', 'id')->withoutTrashed()],
            'items.*.unit_id' => ['nullable', TRule::exists('units', 'id')->withoutTrashed()],
            'items.*.direction' => ['required', Rule::in([StockDirectionEnum::IN->value, StockDirectionEnum::OUT->value])],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.batch_id' => ['nullable', 'integer', TRule::exists('batches', 'id')],
        ];

        return match ($this->method()) {
            'POST', 'PUT' => $rules,
            default => $rules,
        };
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $seen = [];
            foreach ($this->input('items', []) as $i => $item) {
                $variantId = $item['product_variant_id'] ?? null;
                $warehouseId = $item['warehouse_id'] ?? null;

                if ($variantId !== null) {
                    $key = $variantId.':'.($warehouseId ?? 'null');
                    if (in_array($key, $seen, true)) {
                        $validator->errors()->add("items.$i.product_variant_id", __('Duplicate product and warehouse combination in the same adjustment.'));
                    } else {
                        $seen[] = $key;
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

            BatchGuard::validateItems(
                $validator,
                $this->input('items', []),
                (int) (auth('admin')->user()?->company?->id ?? 0),
                fn (array $item) => $item['warehouse_id'] ?? $this->input('warehouse_id'),
            );
        });
    }
}
