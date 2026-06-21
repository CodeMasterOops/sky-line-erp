<?php

namespace App\Http\Requests\Api\Admin\Inventory;

use App\Tenancy\TRule;
use App\Enums\StatusEnum;
use App\Enums\ProductTypeEnum;
use App\Models\ProductVariant;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use App\Rules\WithinActiveFiscalYear;
use Illuminate\Foundation\Http\FormRequest;

class DamageReportRequest extends FormRequest
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
            'reason' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in([StatusEnum::DRAFT->value, StatusEnum::APPROVED->value])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', TRule::exists('product_variants', 'id')->withoutTrashed()],
            'items.*.unit_id' => ['nullable', TRule::exists('units', 'id')->withoutTrashed()],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string', 'max:500'],
            'items.*.batch_id' => ['nullable', 'integer', TRule::exists('batches', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $seen = [];
            foreach ($this->input('items', []) as $i => $item) {
                $variantId = $item['product_variant_id'] ?? null;

                if ($variantId !== null) {
                    if (in_array($variantId, $seen, true)) {
                        $validator->errors()->add("items.$i.product_variant_id", __('Duplicate product in the same damage report.'));
                    } else {
                        $seen[] = $variantId;
                    }

                    $variant = ProductVariant::withoutGlobalScopes()
                        ->with('product:id,product_type')
                        ->find($variantId);

                    if ($variant?->product?->product_type === ProductTypeEnum::SERVICE) {
                        $validator->errors()->add("items.$i.product_variant_id", __('Service products cannot be damaged.'));
                    }
                }
            }

            \App\Services\Inventory\BatchGuard::validateItems(
                $validator,
                $this->input('items', []),
                (int) (auth('admin')->user()?->company?->id ?? 0),
                fn () => $this->input('warehouse_id'),
            );
        });
    }
}
