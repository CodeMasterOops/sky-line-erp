<?php

namespace App\Services\Inventory;

use App\Models\Company;
use App\Models\StockLayer;
use App\Enums\InventoryCostingMethodEnum;

class InventoryCostingMethodSwitchService
{
    /**
     * Run before persisting the new company costing method. Consolidates FIFO layers
     * into a single weighted-average bucket per variant/warehouse when switching to WA.
     */
    public function onCompanyMethodChanging(
        Company $company,
        InventoryCostingMethodEnum $from,
        InventoryCostingMethodEnum $to,
    ): void {
        if ($from === $to) {
            return;
        }

        if ($to !== InventoryCostingMethodEnum::WEIGHTED_AVERAGE) {
            return;
        }

        $this->consolidateLayersForWeightedAverage($company);
    }

    private function consolidateLayersForWeightedAverage(Company $company): void
    {
        $pairs = StockLayer::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('qty_remaining', '>', 0)
            ->select('product_variant_id', 'warehouse_id')
            ->distinct()
            ->get();

        foreach ($pairs as $pair) {
            $variantId = (int) $pair->product_variant_id;
            $warehouseId = (int) $pair->warehouse_id;

            $layers = StockLayer::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('product_variant_id', $variantId)
                ->where('warehouse_id', $warehouseId)
                ->where('qty_remaining', '>', 0)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            if ($layers->count() <= 1) {
                continue;
            }

            $totalQty = (int) $layers->sum('qty_remaining');
            $totalCost = 0.0;
            foreach ($layers as $layer) {
                $totalCost += (int) $layer->qty_remaining * (float) $layer->unit_cost;
            }

            $avg = $totalQty > 0 ? round($totalCost / $totalQty, 4) : 0.0;
            $primary = $layers->first();
            $earliestReceived = $layers->min('received_at');

            $primary->update([
                'qty_remaining' => $totalQty,
                'unit_cost' => $avg,
                'received_at' => $earliestReceived,
            ]);

            foreach ($layers->skip(1) as $layer) {
                $layer->forceDelete();
            }
        }
    }
}
