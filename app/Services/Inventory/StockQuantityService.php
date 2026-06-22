<?php

namespace App\Services\Inventory;

use App\Models\Batch;
use App\Models\Stock;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\UniqueConstraintViolationException;

class StockQuantityService
{
    /**
     * Lock the stock row for this SKU/warehouse (create with zero qty if missing) inside the current transaction.
     */
    public function lockForUpdateOrCreate(int $companyId, int $productVariantId, int $warehouseId): void
    {
        $stock = Stock::withoutGlobalScopes()
            ->withTrashed()
            ->where('company_id', $companyId)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
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
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Adjust on-hand quantity for a variant at a warehouse (delta may be negative).
     */
    public function adjust(int $companyId, int $productVariantId, int $warehouseId, float $delta): void
    {
        try {
            $this->performAdjust($companyId, $productVariantId, $warehouseId, $delta);
        } catch (UniqueConstraintViolationException) {
            $this->performAdjust($companyId, $productVariantId, $warehouseId, $delta);
        }
    }

    /**
     * Increment on_hold for a reservation. Validates available (quantity - on_hold) >= qty.
     * Must be called inside a transaction with lockForUpdateOrCreate() already called.
     */
    public function holdOnHand(int $companyId, int $productVariantId, int $warehouseId, float $qty): void
    {
        $stock = Stock::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        $held = (float) Batch::heldQuantity($companyId, $productVariantId, $warehouseId);
        $available = $stock ? (float) $stock->quantity - (float) $stock->on_hold - $held : 0.0;

        if ($available < $qty) {
            throw ValidationException::withMessages([
                'quantity' => __('Insufficient available stock to reserve for this product at the selected warehouse.'),
            ]);
        }

        if ($stock) {
            $stock->increment('on_hold', $qty);
        }
    }

    /**
     * Decrement on_hold when a reservation is released or fulfilled.
     * Must be called inside a transaction.
     */
    public function releaseOnHold(int $companyId, int $productVariantId, int $warehouseId, float $qty): void
    {
        Stock::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first()
            ?->decrement('on_hold', $qty);
    }

    private function performAdjust(int $companyId, int $productVariantId, int $warehouseId, float $delta): void
    {
        $stock = Stock::withoutGlobalScopes()
            ->withTrashed()
            ->where('company_id', $companyId)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if ($stock) {
            if ($stock->trashed()) {
                $stock->restore();
            }
            $newQty = (float) $stock->quantity + $delta;
            if ($newQty < 0) {
                throw ValidationException::withMessages([
                    'quantity' => __('Insufficient on-hand stock for this product at the selected warehouse.'),
                ]);
            }
            $stock->quantity = $newQty;
            $stock->save();

            return;
        }

        if ($delta < 0) {
            throw ValidationException::withMessages([
                'quantity' => __('Insufficient on-hand stock for this product at the selected warehouse.'),
            ]);
        }

        try {
            Stock::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'product_variant_id' => $productVariantId,
                'warehouse_id' => $warehouseId,
                'quantity' => $delta,
                'on_hold' => 0,
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->performAdjust($companyId, $productVariantId, $warehouseId, $delta);
        }
    }
}
