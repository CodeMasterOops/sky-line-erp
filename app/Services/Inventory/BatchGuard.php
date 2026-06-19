<?php

namespace App\Services\Inventory;

use Closure;
use App\Models\Batch;
use App\Models\ProductVariant;
use Illuminate\Validation\Validator;

class BatchGuard
{
    /**
     * Validate batch selection across a set of document line items:
     *  - a batch is required when the variant is batch-tracked, and
     *  - any supplied batch must belong to the same company, variant and warehouse.
     *
     * Warehouse resolution is document-specific, so the caller supplies a closure
     * that returns the relevant warehouse id for a given line.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  Closure(array<string, mixed>, int): (int|null)  $warehouseFor
     */
    public static function validateItems(
        Validator $validator,
        array $items,
        int $companyId,
        Closure $warehouseFor,
    ): void {
        $variantIds = collect($items)
            ->pluck('product_variant_id')
            ->filter()
            ->unique()
            ->all();

        if (empty($variantIds)) {
            return;
        }

        $variants = ProductVariant::withoutGlobalScopes()
            ->with('product')
            ->where('company_id', $companyId)
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        foreach ($items as $index => $item) {
            $variantId = $item['product_variant_id'] ?? null;
            $variant = $variantId ? $variants->get($variantId) : null;

            if (! $variant || ! $variant->is_batch_tracked || $variant->isService()) {
                continue;
            }

            $batchId = $item['batch_id'] ?? null;
            $batchNo = $item['batch_no'] ?? null;

            if (blank($batchId) && blank($batchNo)) {
                $validator->errors()->add(
                    "items.{$index}.batch_id",
                    __('A batch is required for batch-tracked product :sku.', ['sku' => $variant->sku]),
                );

                continue;
            }

            if (blank($batchId)) {
                continue;
            }

            $warehouseId = $warehouseFor($item, $index);

            if (! $warehouseId) {
                continue;
            }

            $belongs = Batch::withoutGlobalScopes()
                ->whereKey($batchId)
                ->where('company_id', $companyId)
                ->where('product_variant_id', $variantId)
                ->where('warehouse_id', $warehouseId)
                ->exists();

            if (! $belongs) {
                $validator->errors()->add(
                    "items.{$index}.batch_id",
                    __('Selected batch does not belong to this product and warehouse.'),
                );
            }
        }
    }
}
