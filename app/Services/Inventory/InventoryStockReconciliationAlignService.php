<?php

namespace App\Services\Inventory;

use App\Models\Bin;
use App\Models\Stock;
use App\Models\Company;
use App\Models\StockLayer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Closes quantity drift between on-hand (stocks.quantity) and subledger (sum of layer qty_remaining).
 *
 * Normal stock adjustments move both by the same delta, so they never change (stock − valued).
 * These methods apply one-sided corrections after ops decides which figure is authoritative.
 */
class InventoryStockReconciliationAlignService
{
    public function __construct(
        private StockLayerLedger $ledger,
        private StockQuantityService $quantities,
    ) {}

    /**
     * Adjust layers only so sum(qty_remaining) equals on-hand quantity.
     * Use when physical count matches on-hand but valued total was too low/high.
     */
    public function alignValuedQuantityToOnHand(
        Company $company,
        int $productVariantId,
        int $warehouseId,
        ?float $unitCostForIncrease = null,
    ): void {
        DB::transaction(function () use ($company, $productVariantId, $warehouseId, $unitCostForIncrease) {
            $binId = Bin::defaultIdForWarehouse($company->id, $warehouseId);

            $this->quantities->lockForUpdateOrCreate($company->id, $productVariantId, $warehouseId, $binId);

            $stock = Stock::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('product_variant_id', $productVariantId)
                ->where('warehouse_id', $warehouseId)
                ->where('bin_id', $binId)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                throw ValidationException::withMessages([
                    'stock' => __('No stock row exists for this variant and warehouse.'),
                ]);
            }

            $stockQty = (int) $stock->quantity;
            $valued = $this->sumValuedQuantity($company->id, $productVariantId, $warehouseId, $binId);
            $diff = $stockQty - $valued;

            if ($diff === 0) {
                return;
            }

            if ($diff > 0) {
                $cost = $unitCostForIncrease ?? $this->weightedAverageUnitCost($company->id, $productVariantId, $warehouseId, $binId);
                $this->ledger->receipt(
                    $company,
                    $productVariantId,
                    $warehouseId,
                    $binId,
                    $diff,
                    (float) $cost,
                    null,
                    now(),
                );

                return;
            }

            $this->ledger->consume($company, $productVariantId, $warehouseId, $binId, abs($diff));
        });
    }

    /**
     * Set on-hand quantity to the current valued total (sum of layer qty_remaining).
     * Use when subledger is correct but the stock row is overstated/understated.
     */
    public function alignOnHandQuantityToValued(
        Company $company,
        int $productVariantId,
        int $warehouseId,
    ): void {
        DB::transaction(function () use ($company, $productVariantId, $warehouseId) {
            $binId = Bin::defaultIdForWarehouse($company->id, $warehouseId);

            $this->quantities->lockForUpdateOrCreate($company->id, $productVariantId, $warehouseId, $binId);

            $stock = Stock::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('product_variant_id', $productVariantId)
                ->where('warehouse_id', $warehouseId)
                ->where('bin_id', $binId)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                throw ValidationException::withMessages([
                    'stock' => __('No stock row exists for this variant and warehouse.'),
                ]);
            }

            $valued = $this->sumValuedQuantity($company->id, $productVariantId, $warehouseId, $binId);
            $stock->quantity = $valued;
            $stock->save();
        });
    }

    private function sumValuedQuantity(int $companyId, int $productVariantId, int $warehouseId, int $binId): int
    {
        return (int) StockLayer::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->where('bin_id', $binId)
            ->whereNull('deleted_at')
            ->sum('qty_remaining');
    }

    private function weightedAverageUnitCost(int $companyId, int $productVariantId, int $warehouseId, int $binId): float
    {
        $layers = StockLayer::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->where('bin_id', $binId)
            ->whereNull('deleted_at')
            ->where('qty_remaining', '>', 0)
            ->get(['qty_remaining', 'unit_cost']);

        $qty = (int) $layers->sum('qty_remaining');
        if ($qty <= 0) {
            return 0.0;
        }

        $value = 0.0;
        foreach ($layers as $layer) {
            $value += (int) $layer->qty_remaining * (float) $layer->unit_cost;
        }

        return round($value / $qty, 4);
    }
}
