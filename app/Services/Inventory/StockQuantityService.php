<?php

namespace App\Services\Inventory;

use App\Models\Stock;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\UniqueConstraintViolationException;

class StockQuantityService
{
    /**
     * Lock the stock row for this SKU/warehouse/bin (create with zero qty if missing) inside the current transaction.
     */
    public function lockForUpdateOrCreate(int $companyId, int $productVariantId, int $warehouseId, int $binId): void
    {
        $stock = Stock::withoutGlobalScopes()
            ->withTrashed()
            ->where('company_id', $companyId)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->where('bin_id', $binId)
            ->lockForUpdate()
            ->first();

        if ($stock) {
            return;
        }

        try {
            Stock::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'product_variant_id' => $productVariantId,
                'warehouse_id' => $warehouseId,
                'bin_id' => $binId,
                'quantity' => 0,
                'on_hold' => 0,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Concurrent insert; row now exists.
        }

        Stock::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->where('bin_id', $binId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Adjust on-hand quantity for a variant at a warehouse and bin (delta may be negative).
     */
    public function adjust(int $companyId, int $productVariantId, int $warehouseId, int $binId, int $delta): void
    {
        try {
            $this->performAdjust($companyId, $productVariantId, $warehouseId, $binId, $delta);
        } catch (UniqueConstraintViolationException) {
            $this->performAdjust($companyId, $productVariantId, $warehouseId, $binId, $delta);
        }
    }

    private function performAdjust(int $companyId, int $productVariantId, int $warehouseId, int $binId, int $delta): void
    {
        $stock = Stock::withoutGlobalScopes()
            ->withTrashed()
            ->where('company_id', $companyId)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->where('bin_id', $binId)
            ->lockForUpdate()
            ->first();

        if ($stock) {
            if ($stock->trashed()) {
                $stock->restore();
            }
            $newQty = (int) $stock->quantity + $delta;
            if ($newQty < 0) {
                throw ValidationException::withMessages([
                    'quantity' => __('Insufficient on-hand stock for this product at the selected warehouse and bin.'),
                ]);
            }
            $stock->quantity = $newQty;
            $stock->save();

            return;
        }

        if ($delta < 0) {
            throw ValidationException::withMessages([
                'quantity' => __('Insufficient on-hand stock for this product at the selected warehouse and bin.'),
            ]);
        }

        try {
            Stock::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'product_variant_id' => $productVariantId,
                'warehouse_id' => $warehouseId,
                'bin_id' => $binId,
                'quantity' => $delta,
                'on_hold' => 0,
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->performAdjust($companyId, $productVariantId, $warehouseId, $binId, $delta);
        }
    }
}
