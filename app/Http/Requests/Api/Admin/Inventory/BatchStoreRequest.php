<?php

namespace App\Http\Requests\Api\Admin\Inventory;

use App\Tenancy\TRule;
use App\Models\ProductVariant;
use Illuminate\Validation\Validator;
use App\Rules\WarehouseIsStockLocation;
use Illuminate\Foundation\Http\FormRequest;

class BatchStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_variant_id' => ['required', TRule::exists('product_variants', 'id')->withoutTrashed()],
            'warehouse_id' => ['required', TRule::exists('warehouses', 'id')->withoutTrashed(), new WarehouseIsStockLocation],
            'batch_no' => ['required', 'string', 'max:100'],
            'lot_no' => ['nullable', 'string', 'max:100'],
            'mfg_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:mfg_date'],
            'initial_qty' => ['required', 'numeric', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $variantId = $this->input('product_variant_id');

            if (! $variantId) {
                return;
            }

            $variant = ProductVariant::withoutGlobalScopes()
                ->where('company_id', (int) (auth('admin')->user()?->company?->id ?? 0))
                ->find($variantId);

            if (! $variant) {
                return;
            }

            if ($variant->isService() || ! $variant->is_batch_tracked) {
                $validator->errors()->add(
                    'product_variant_id',
                    __('Batches can only be created for batch-tracked products.'),
                );
            }
        });
    }
}
