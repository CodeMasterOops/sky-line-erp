<?php

namespace App\Services\Inventory;

use App\Models\Company;
use App\Enums\ChangeTypeEnum;
use App\Models\StockTransfer;
use App\Enums\StockDirectionEnum;
use App\Models\StockTransferItem;

class InventoryLayerTransferService
{
    public function __construct(
        private StockLayerLedger $ledger,
        private StockQuantityService $quantities,
    ) {}

    /**
     * Move valued quantity from source to destination: consume source layers, receipt slices at dest, two stock movements.
     */
    public function applyLine(
        Company $company,
        StockTransfer $transfer,
        StockTransferItem $item,
        ?int $userId,
        ?string $remarks,
    ): void {
        $qty = (int) $item->quantity;
        if ($qty <= 0) {
            return;
        }

        $variantId = $item->product_variant_id;
        $fromId = $transfer->from_warehouse_id;
        $toId = $transfer->to_warehouse_id;

        $lockFirstW = (int) min($fromId, $toId);
        $lockSecondW = (int) max($fromId, $toId);

        $this->quantities->lockForUpdateOrCreate($company->id, $variantId, $lockFirstW);
        $this->quantities->lockForUpdateOrCreate($company->id, $variantId, $lockSecondW);

        $lines = $this->ledger->consume($company, $variantId, (int) $fromId, $qty);

        $this->quantities->adjust($company->id, $variantId, (int) $fromId, -$qty);

        $outTotal = 0.0;
        foreach ($lines as $line) {
            $outTotal += $line['quantity'] * $line['unit_cost'];
        }
        $outTotal = round($outTotal, 4);
        $outUnit = $qty > 0 ? round($outTotal / $qty, 4) : 0.0;

        $outMovement = $transfer->stockMovements()->create([
            'company_id' => $company->id,
            'product_variant_id' => $variantId,
            'warehouse_id' => $fromId,
            'type' => ChangeTypeEnum::TRANSFER_OUT,
            'direction' => StockDirectionEnum::OUT,
            'quantity' => $qty,
            'unit_cost' => $outUnit,
            'total_cost' => $outTotal,
            'user_id' => $userId,
            'remarks' => $remarks,
        ]);

        foreach ($lines as $line) {
            $outMovement->movementLayers()->create([
                'stock_layer_id' => $line['layer']->id,
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'],
            ]);
        }

        foreach ($lines as $line) {
            $this->ledger->receipt(
                $company,
                $variantId,
                (int) $toId,
                $line['quantity'],
                $line['unit_cost'],
                null,
                now(),
            );
        }

        $this->quantities->adjust($company->id, $variantId, (int) $toId, $qty);

        $transfer->stockMovements()->create([
            'company_id' => $company->id,
            'product_variant_id' => $variantId,
            'warehouse_id' => $toId,
            'type' => ChangeTypeEnum::TRANSFER_IN,
            'direction' => StockDirectionEnum::IN,
            'quantity' => $qty,
            'unit_cost' => $outUnit,
            'total_cost' => $outTotal,
            'user_id' => $userId,
            'remarks' => $remarks,
        ]);
    }
}
