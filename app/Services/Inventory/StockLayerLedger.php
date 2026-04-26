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
        int $binId,
        int $quantity,
    ): array {
        $method = $company->inventory_costing_method ?? InventoryCostingMethodEnum::FIFO;

        return match ($method) {
            InventoryCostingMethodEnum::WEIGHTED_AVERAGE => $this->consumeWeightedAverage(
                $company,
                $productVariantId,
                $warehouseId,
                $binId,
                $quantity
            ),
            default => $this->consumeFifo(
                $company,
                $productVariantId,
                $warehouseId,
                $binId,
                $quantity
            ),
        };
    }

    public function receipt(
        Company $company,
        int $productVariantId,
        int $warehouseId,
        int $binId,
        int $quantity,
        float $unitCost,
        ?int $sourceBillItemId = null,
        ?\DateTimeInterface $receivedAt = null,
    ): void {
        $method = $company->inventory_costing_method ?? InventoryCostingMethodEnum::FIFO;
        $at = $receivedAt ?? now();

        if ($method === InventoryCostingMethodEnum::WEIGHTED_AVERAGE) {
            $this->receiptWeightedAverage(
                $company,
                $productVariantId,
                $warehouseId,
                $binId,
                $quantity,
                $unitCost,
                $sourceBillItemId,
                $at
            );

            return;
        }

        $this->receiptFifo(
            $company,
            $productVariantId,
            $warehouseId,
            $binId,
            $quantity,
            $unitCost,
            $sourceBillItemId,
            $at
        );
    }

    private function receiptFifo(
        Company $company,
        int $productVariantId,
        int $warehouseId,
        int $binId,
        int $quantity,
        float $unitCost,
        ?int $sourceBillItemId,
        \DateTimeInterface $receivedAt,
    ): void {
        StockLayer::create([
            'company_id' => $company->id,
            'product_variant_id' => $productVariantId,
            'warehouse_id' => $warehouseId,
            'bin_id' => $binId,
            'qty_remaining' => $quantity,
            'unit_cost' => $unitCost,
            'received_at' => $receivedAt,
            'source_bill_item_id' => $sourceBillItemId,
        ]);
    }

    private function receiptWeightedAverage(
        Company $company,
        int $productVariantId,
        int $warehouseId,
        int $binId,
        int $quantity,
        float $unitCost,
        ?int $sourceBillItemId,
        \DateTimeInterface $receivedAt,
    ): void {
        $layer = StockLayer::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->where('bin_id', $binId)
            ->where('qty_remaining', '>', 0)
            ->lockForUpdate()
            ->orderBy('id')
            ->first();

        if (! $layer) {
            StockLayer::create([
                'company_id' => $company->id,
                'product_variant_id' => $productVariantId,
                'warehouse_id' => $warehouseId,
                'bin_id' => $binId,
                'qty_remaining' => $quantity,
                'unit_cost' => $unitCost,
                'received_at' => $receivedAt,
                'source_bill_item_id' => $sourceBillItemId,
            ]);

            return;
        }

        $oldQty = (int) $layer->qty_remaining;
        $oldCost = (float) $layer->unit_cost;
        $newQty = $oldQty + $quantity;
        $newCost = ($oldQty * $oldCost + $quantity * $unitCost) / $newQty;

        $layer->update([
            'qty_remaining' => $newQty,
            'unit_cost' => round($newCost, 4),
            'received_at' => $receivedAt,
            'source_bill_item_id' => $sourceBillItemId ?? $layer->source_bill_item_id,
        ]);
    }

    /**
     * @return array<int, array{layer: StockLayer, quantity: int, unit_cost: float}>
     */
    private function consumeFifo(
        Company $company,
        int $productVariantId,
        int $warehouseId,
        int $binId,
        int $quantity,
    ): array {
        $layers = StockLayer::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->where('bin_id', $binId)
            ->where('qty_remaining', '>', 0)
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $available = (int) $layers->sum('qty_remaining');
        if ($available < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => __('Insufficient valued stock for this product at the selected warehouse and bin.'),
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
        int $binId,
        int $quantity,
    ): array {
        $layer = StockLayer::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->where('bin_id', $binId)
            ->where('qty_remaining', '>', 0)
            ->lockForUpdate()
            ->orderBy('id')
            ->first();

        if (! $layer || (int) $layer->qty_remaining < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => __('Insufficient valued stock for this product at the selected warehouse and bin.'),
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
