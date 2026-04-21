<?php

namespace App\Services\Inventory;

use App\Models\Stock;

class StockQuantityService
{
    /**
     * Adjust on-hand quantity for a variant at a warehouse (delta may be negative).
     */
    public function adjust(int $companyId, int $productVariantId, int $warehouseId, int $delta): void
    {
        $stock = Stock::withoutGlobalScopes()
            ->withTrashed()
            ->where('company_id', $companyId)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if ($stock) {
            if ($stock->trashed()) {
                $stock->restore();
            }
            $stock->quantity = (int) $stock->quantity + $delta;
            $stock->save();

            return;
        }

        Stock::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'product_variant_id' => $productVariantId,
            'warehouse_id' => $warehouseId,
            'quantity' => $delta,
            'on_hold' => 0,
        ]);
    }
}
