<?php

namespace App\Services\Inventory;

use App\Models\Bin;
use App\Models\Company;
use App\Enums\ChangeTypeEnum;
use App\Models\StockMovement;
use App\Enums\StockDirectionEnum;
use Illuminate\Database\Eloquent\Model;

class InventoryLayerReceiptService
{
    public function __construct(
        private StockLayerLedger $ledger,
        private StockQuantityService $quantities,
    ) {}

    /**
     * Receive stock into layers and on-hand quantity; record one stock movement (IN).
     */
    public function receive(
        Company $company,
        Model $reference,
        int $productVariantId,
        int $warehouseId,
        int $quantity,
        float $unitCost,
        ChangeTypeEnum $changeType,
        ?int $userId,
        ?string $remarks,
        ?int $sourceBillItemId = null,
        ?int $binId = null,
    ): StockMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Receipt quantity must be positive.');
        }

        $binId = $binId ?? Bin::defaultIdForWarehouse($company->id, $warehouseId);

        $this->quantities->lockForUpdateOrCreate($company->id, $productVariantId, $warehouseId, $binId);

        $this->ledger->receipt(
            $company,
            $productVariantId,
            $warehouseId,
            $binId,
            $quantity,
            $unitCost,
            $sourceBillItemId,
            null,
        );

        $this->quantities->adjust($company->id, $productVariantId, $warehouseId, $binId, $quantity);

        $totalCost = round($quantity * $unitCost, 4);
        $movementUnitCost = round($totalCost / $quantity, 4);

        return $reference->stockMovements()->create([
            'company_id' => $company->id,
            'product_variant_id' => $productVariantId,
            'warehouse_id' => $warehouseId,
            'bin_id' => $binId,
            'type' => $changeType,
            'direction' => StockDirectionEnum::IN,
            'quantity' => $quantity,
            'unit_cost' => $movementUnitCost,
            'total_cost' => $totalCost,
            'user_id' => $userId,
            'remarks' => $remarks,
        ]);
    }
}
