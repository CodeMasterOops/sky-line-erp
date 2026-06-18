<?php

namespace App\Services\Inventory;

use App\Models\Batch;
use App\Models\Company;
use App\Enums\ChangeTypeEnum;
use App\Models\StockMovement;
use App\Enums\StockDirectionEnum;
use Illuminate\Database\Eloquent\Model;

class InventoryLayerIssueService
{
    public function __construct(
        private StockLayerLedger $ledger,
        private StockQuantityService $quantities,
    ) {}

    /**
     * Consume valued layers and reduce on-hand; record one stock movement (OUT) with layer breakdown.
     */
    public function issue(
        Company $company,
        Model $reference,
        int $productVariantId,
        int $warehouseId,
        int $quantity,
        ChangeTypeEnum $changeType,
        ?int $userId,
        ?string $remarks,
        ?int $batchId = null,
    ): StockMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Issue quantity must be positive.');
        }

        if ($batchId !== null) {
            $batch = Batch::lockForUpdate()->findOrFail($batchId);

            if ($batch->status === 'expired') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'batch_id' => __('Batch :no has expired and cannot be issued.', ['no' => $batch->batch_no]),
                ]);
            }
        }

        $this->quantities->lockForUpdateOrCreate($company->id, $productVariantId, $warehouseId);

        $lines = $this->ledger->consume($company, $productVariantId, $warehouseId, $quantity, $batchId);

        if ($batchId !== null) {
            Batch::where('id', $batchId)->decrement('remaining_qty', $quantity);
            Batch::where('id', $batchId)->where('remaining_qty', '<=', 0)->update(['status' => 'depleted']);
        }

        $this->quantities->adjust($company->id, $productVariantId, $warehouseId, -$quantity);

        $totalCost = 0.0;
        foreach ($lines as $line) {
            $totalCost += $line['quantity'] * $line['unit_cost'];
        }
        $totalCost = round($totalCost, 4);
        $movementUnitCost = $quantity > 0 ? round($totalCost / $quantity, 4) : 0.0;

        $movement = $reference->stockMovements()->create([
            'company_id' => $company->id,
            'product_variant_id' => $productVariantId,
            'warehouse_id' => $warehouseId,
            'type' => $changeType,
            'direction' => StockDirectionEnum::OUT,
            'quantity' => $quantity,
            'unit_cost' => $movementUnitCost,
            'total_cost' => $totalCost,
            'user_id' => $userId,
            'remarks' => $remarks,
        ]);

        foreach ($lines as $line) {
            $movement->movementLayers()->create([
                'stock_layer_id' => $line['layer']->id,
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'],
            ]);
        }

        return $movement;
    }
}
