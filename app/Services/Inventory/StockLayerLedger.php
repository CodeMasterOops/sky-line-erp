<?php

namespace App\Services\Inventory;

use App\Models\Company;
use App\Models\StockLayer;
use App\Enums\InventoryCostingMethodEnum;
use Illuminate\Validation\ValidationException;

class StockLayerLedger
{
    /**
     * @return array<int, array{layer: StockLayer, quantity: int, unit_cost: float}>
     */
    public function consume(
        Company $company,
        int $productVariantId,
        int $warehouseId,
        int $quantity,
        ?int $batchId = null,
    ): array {
        $method = $company->inventory_costing_method ?? InventoryCostingMethodEnum::FIFO;

        return match ($method) {
            InventoryCostingMethodEnum::WEIGHTED_AVERAGE => $this->consumeWeightedAverage(
                $company,
                $productVariantId,
                $warehouseId,
                $quantity,
                $batchId,
            ),
            default => $this->consumeFifo(
                $company,
                $productVariantId,
                $warehouseId,
                $quantity,
                $batchId,
            ),
        };
    }

    public function receipt(
        Company $company,
        int $productVariantId,
        int $warehouseId,
        int $quantity,
        float $unitCost,
        ?int $sourceBillItemId = null,
        ?\DateTimeInterface $receivedAt = null,
        ?int $sourceGrnItemId = null,
        ?int $sourceProductionOrderId = null,
        ?int $batchId = null,
    ): void {
        $method = $company->inventory_costing_method ?? InventoryCostingMethodEnum::FIFO;
        $at = $receivedAt ?? now();

        if ($method === InventoryCostingMethodEnum::WEIGHTED_AVERAGE) {
            $this->receiptWeightedAverage(
                $company,
                $productVariantId,
                $warehouseId,
                $quantity,
                $unitCost,
                $sourceBillItemId,
                $at,
                $sourceGrnItemId,
                $sourceProductionOrderId,
                $batchId,
            );

            return;
        }

        $this->receiptFifo(
            $company,
            $productVariantId,
            $warehouseId,
            $quantity,
            $unitCost,
            $sourceBillItemId,
            $at,
            $sourceGrnItemId,
            $sourceProductionOrderId,
            $batchId,
        );
    }

    private function receiptFifo(
        Company $company,
        int $productVariantId,
        int $warehouseId,
        int $quantity,
        float $unitCost,
        ?int $sourceBillItemId,
        \DateTimeInterface $receivedAt,
        ?int $sourceGrnItemId = null,
        ?int $sourceProductionOrderId = null,
        ?int $batchId = null,
    ): void {
        StockLayer::create([
            'company_id' => $company->id,
            'product_variant_id' => $productVariantId,
            'warehouse_id' => $warehouseId,
            'qty_remaining' => $quantity,
            'unit_cost' => $unitCost,
            'base_unit_cost' => $unitCost,
            'landed_unit_cost' => 0,
            'received_at' => $receivedAt,
            'source_bill_item_id' => $sourceBillItemId,
            'source_grn_item_id' => $sourceGrnItemId,
            'source_production_order_id' => $sourceProductionOrderId,
            'batch_id' => $batchId,
        ]);
    }

    private function receiptWeightedAverage(
        Company $company,
        int $productVariantId,
        int $warehouseId,
        int $quantity,
        float $unitCost,
        ?int $sourceBillItemId,
        \DateTimeInterface $receivedAt,
        ?int $sourceGrnItemId = null,
        ?int $sourceProductionOrderId = null,
        ?int $batchId = null,
    ): void {
        $layer = StockLayer::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->where('qty_remaining', '>', 0)
            ->when($batchId !== null, fn ($q) => $q->where('batch_id', $batchId))
            ->when($batchId === null, fn ($q) => $q->whereNull('batch_id'))
            ->lockForUpdate()
            ->orderBy('id')
            ->first();

        if (! $layer) {
            StockLayer::create([
                'company_id' => $company->id,
                'product_variant_id' => $productVariantId,
                'warehouse_id' => $warehouseId,
                'qty_remaining' => $quantity,
                'unit_cost' => $unitCost,
                'base_unit_cost' => $unitCost,
                'landed_unit_cost' => 0,
                'received_at' => $receivedAt,
                'source_bill_item_id' => $sourceBillItemId,
                'source_grn_item_id' => $sourceGrnItemId,
                'source_production_order_id' => $sourceProductionOrderId,
                'batch_id' => $batchId,
            ]);

            return;
        }

        $oldQty = (int) $layer->qty_remaining;
        $oldCost = (float) $layer->unit_cost;
        $oldBaseCost = (float) ($layer->base_unit_cost ?: $layer->unit_cost);
        $oldLandedCost = (float) $layer->landed_unit_cost;
        $newQty = $oldQty + $quantity;
        $newCost = ($oldQty * $oldCost + $quantity * $unitCost) / $newQty;
        $newBaseCost = ($oldQty * $oldBaseCost + $quantity * $unitCost) / $newQty;
        $newLandedCost = ($oldQty * $oldLandedCost) / $newQty;

        $layer->update([
            'qty_remaining' => $newQty,
            'unit_cost' => round($newCost, 4),
            'base_unit_cost' => round($newBaseCost, 4),
            'landed_unit_cost' => round($newLandedCost, 4),
            'received_at' => $receivedAt,
            'source_bill_item_id' => $sourceBillItemId ?? $layer->source_bill_item_id,
            'source_grn_item_id' => $sourceGrnItemId ?? $layer->source_grn_item_id,
            'source_production_order_id' => $sourceProductionOrderId ?? $layer->source_production_order_id,
            'batch_id' => $batchId ?? $layer->batch_id,
        ]);
    }

    /**
     * @return array<int, array{layer: StockLayer, quantity: int, unit_cost: float}>
     */
    private function consumeFifo(
        Company $company,
        int $productVariantId,
        int $warehouseId,
        int $quantity,
        ?int $batchId = null,
    ): array {
        $layers = StockLayer::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->where('qty_remaining', '>', 0)
            ->when($batchId !== null, fn ($q) => $q->where('batch_id', $batchId))
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $available = (int) $layers->sum('qty_remaining');
        if ($available < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => __('Insufficient valued stock for this product at the selected warehouse.'),
            ]);
        }

        $remaining = $quantity;
        $lines = [];

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }

            $layerQty = (int) $layer->qty_remaining;
            $take = min($remaining, $layerQty);
            $unitCost = (float) $layer->unit_cost;

            $layer->qty_remaining = $layerQty - $take;
            $layer->save();

            $lines[] = [
                'layer' => $layer,
                'quantity' => $take,
                'unit_cost' => $unitCost,
            ];

            $remaining -= $take;
        }

        return $lines;
    }

    /**
     * @return array<int, array{layer: StockLayer, quantity: int, unit_cost: float}>
     */
    private function consumeWeightedAverage(
        Company $company,
        int $productVariantId,
        int $warehouseId,
        int $quantity,
        ?int $batchId = null,
    ): array {
        $layer = StockLayer::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->where('qty_remaining', '>', 0)
            ->when($batchId !== null, fn ($q) => $q->where('batch_id', $batchId))
            ->lockForUpdate()
            ->orderBy('id')
            ->first();

        if (! $layer || (int) $layer->qty_remaining < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => __('Insufficient valued stock for this product at the selected warehouse.'),
            ]);
        }

        $unitCost = (float) $layer->unit_cost;
        $layer->qty_remaining = (int) $layer->qty_remaining - $quantity;
        $layer->save();

        return [[
            'layer' => $layer,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
        ]];
    }
}
