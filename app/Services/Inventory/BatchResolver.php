<?php

namespace App\Services\Inventory;

use App\Models\Batch;
use App\Enums\BatchStatusEnum;
use Illuminate\Database\Eloquent\Model;

class BatchResolver
{
    /**
     * Find (or create) the warehouse-bound lot for an inbound document line and
     * link it back onto the line. Keyed on company + variant + warehouse +
     * batch_no — the single source of truth shared by GRN, Opening Stock and
     * direct Purchase Bill receipts.
     *
     * Quantities are intentionally left at zero: the receipt service owns
     * initial_qty / remaining_qty, so callers must not increment them here.
     *
     * The line is expected to expose batch_no, product_variant_id, expiry_date
     * and optionally mfg_date attributes.
     */
    public function resolve(Model $item, int $companyId, int $warehouseId, float $unitCost): ?int
    {
        if (blank($item->batch_no)) {
            return null;
        }

        $batch = Batch::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('product_variant_id', $item->product_variant_id)
            ->where('warehouse_id', $warehouseId)
            ->where('batch_no', $item->batch_no)
            ->first();

        if (! $batch) {
            $batch = Batch::create([
                'company_id' => $companyId,
                'product_variant_id' => $item->product_variant_id,
                'warehouse_id' => $warehouseId,
                'batch_no' => $item->batch_no,
                'lot_no' => $item->batch_no,
                'mfg_date' => $item->mfg_date ?? null,
                'expiry_date' => $item->expiry_date,
                'initial_qty' => 0,
                'remaining_qty' => 0,
                'unit_cost' => $unitCost,
                'status' => BatchStatusEnum::Active,
            ]);
        }

        $item->update(['batch_id' => $batch->id]);

        return $batch->id;
    }
}
