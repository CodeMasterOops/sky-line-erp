<?php

namespace App\Services\Inventory;

use App\Models\Company;
use App\Models\StockLayer;
use App\Enums\BatchStatusEnum;
use App\Enums\InventoryCostingMethodEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class StockLayerLedger
{
    /**
     * Exclude cost layers that belong to a held lot (quarantine, expired, recalled).
     * Applied only on the unbatched (FEFO/auto) consume path so that automatic issues
     * and reconciliation never silently draw down quality-held stock. Layers with no
     * batch (batch_id IS NULL) and layers on Active lots are kept.
     */
    private function excludeHeldLayers(Builder $query): Builder
    {
        return $query->whereNotExists(function ($sub): void {
            $sub->selectRaw('1')
                ->from('batches')
                ->whereColumn('batches.id', 'stock_layers.batch_id')
                ->whereNull('batches.deleted_at')
                ->whereIn('batches.status', BatchStatusEnum::heldValues());
        });
    }

    /**
     * @return array<int, array{layer: StockLayer, quantity: int, unit_cost: float}>
     */
    public function consume(
        Company $company,
        int $productVariantId,
        int $warehouseId,
        float $quantity,
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
        float $quantity,
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
        float $quantity,
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
        float $quantity,
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

        $oldQty = (float) $layer->qty_remaining;
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
        float $quantity,
        ?int $batchId = null,
    ): array {
        $layers = StockLayer::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->where('qty_remaining', '>', 0)
            ->when($batchId !== null, fn ($q) => $q->where('batch_id', $batchId))
            ->when($batchId === null, fn ($q) => $this->excludeHeldLayers($q))
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $available = (float) $layers->sum('qty_remaining');
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

            $layerQty = (float) $layer->qty_remaining;
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
        float $quantity,
        ?int $batchId = null,
    ): array {
        $layer = StockLayer::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->where('qty_remaining', '>', 0)
            ->when($batchId !== null, fn ($q) => $q->where('batch_id', $batchId))
            ->when($batchId === null, fn ($q) => $this->excludeHeldLayers($q))
            ->lockForUpdate()
            ->orderBy('id')
            ->first();

        if (! $layer || (float) $layer->qty_remaining < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => __('Insufficient valued stock for this product at the selected warehouse.'),
            ]);
        }

        $unitCost = (float) $layer->unit_cost;
        $layer->qty_remaining = (float) $layer->qty_remaining - $quantity;
        $layer->save();

        return [[
            'layer' => $layer,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
        ]];
    }
}
