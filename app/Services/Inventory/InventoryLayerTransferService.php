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
     * Combined dispatch + receive in one step. Used by the legacy approve() action.
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
        $fromId = $item->from_warehouse_id ?? $transfer->from_warehouse_id;
        $toId = $transfer->to_warehouse_id;

        $lockFirstW = (int) min($fromId, $toId);
        $lockSecondW = (int) max($fromId, $toId);

        $this->quantities->lockForUpdateOrCreate($company->id, $variantId, $lockFirstW);
        $this->quantities->lockForUpdateOrCreate($company->id, $variantId, $lockSecondW);

        $batchId = $item->batch_id ?? null;

        $lines = $this->ledger->consume($company, $variantId, (int) $fromId, $qty, $batchId);

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
                null,
                null,
                $line['layer']->batch_id,
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

    /**
     * Step 1 of in-transit transfer: deduct from source, create TRANSFER_OUT movement only.
     */
    public function applyDispatch(
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
        $fromId = (int) ($item->from_warehouse_id ?? $transfer->from_warehouse_id);
        $toId = (int) $transfer->to_warehouse_id;

        $lockFirstW = min($fromId, $toId);
        $lockSecondW = max($fromId, $toId);

        $this->quantities->lockForUpdateOrCreate($company->id, $variantId, $lockFirstW);
        $this->quantities->lockForUpdateOrCreate($company->id, $variantId, $lockSecondW);

        $batchId = $item->batch_id ?? null;

        $lines = $this->ledger->consume($company, $variantId, $fromId, $qty, $batchId);
        $this->quantities->adjust($company->id, $variantId, $fromId, -$qty);

        $outTotal = round(array_sum(array_map(fn ($l) => $l['quantity'] * $l['unit_cost'], $lines)), 4);
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
    }

    /**
     * Step 2 of in-transit transfer: add to destination, create TRANSFER_IN movement only.
     * Reads the existing TRANSFER_OUT movement(s) to match costs.
     */
    public function applyReceive(
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
        $toId = (int) $transfer->to_warehouse_id;

        $outMovement = $transfer->stockMovements()
            ->withoutGlobalScopes()
            ->where('product_variant_id', $variantId)
            ->where('type', ChangeTypeEnum::TRANSFER_OUT)
            ->first();

        $outUnit = $outMovement ? (float) $outMovement->unit_cost : 0.0;
        $outTotal = $outMovement ? (float) $outMovement->total_cost : 0.0;

        $batchId = $item->batch_id ?? null;

        $this->quantities->lockForUpdateOrCreate($company->id, $variantId, $toId);

        $this->ledger->receipt(
            $company,
            $variantId,
            $toId,
            $qty,
            $outUnit,
            null,
            now(),
            null,
            null,
            $batchId,
        );

        $this->quantities->adjust($company->id, $variantId, $toId, $qty);

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
