<?php

namespace App\Services\Inventory;

use App\Models\Company;
use App\Enums\ChangeTypeEnum;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderConsumption;
use Illuminate\Validation\ValidationException;

class ProductionOrderCompletionService
{
    public function __construct(
        private InventoryLayerIssueService $issueService,
        private InventoryLayerReceiptService $receiptService,
    ) {}

    /**
     * Atomically issue all consumed raw materials and receipt finished goods
     * through the stock ledger (layers + movements + GL). Must be called inside
     * a DB::transaction().
     *
     * @param  array{produced_qty: float, consumptions?: array<int, array{id: int, consumed_qty: float, batch_id?: int|null}>}  $data
     */
    public function complete(
        ProductionOrder $productionOrder,
        array $data,
        Company $company,
        int $userId,
    ): ProductionOrder {
        $bom = $productionOrder->bom()->with('productVariant.product')->firstOrFail();
        $consumptions = $productionOrder->consumptions()->with('productVariant.product')->get();

        $totalMaterialCost = 0.0;

        foreach ($data['consumptions'] ?? [] as $consumptionData) {
            /** @var ProductionOrderConsumption|null $consumption */
            $consumption = $consumptions->firstWhere('id', $consumptionData['id']);
            if (! $consumption) {
                continue;
            }

            $consumedQty = (int) $consumptionData['consumed_qty'];
            $isPhysical = ! $consumption->productVariant->isService();

            if ($consumedQty <= 0 || ! $isPhysical) {
                $consumption->update([
                    'consumed_qty' => $consumedQty,
                    'batch_id' => $consumptionData['batch_id'] ?? $consumption->batch_id,
                ]);

                continue;
            }

            // Split: planned (required) qty goes to MANUFACTURING_ISSUE (enters WIP cost),
            // excess (wastage) goes to WASTAGE (expensed to variance account).
            $requiredQty = (int) $consumption->required_qty;
            $plannedQty = min($consumedQty, $requiredQty);
            $wastageQty = max(0, $consumedQty - $requiredQty);

            $movement = $this->issueService->issue(
                $company,
                $productionOrder,
                $consumption->product_variant_id,
                $consumption->warehouse_id,
                $plannedQty,
                ChangeTypeEnum::MANUFACTURING_ISSUE,
                $userId,
                "Production Order {$productionOrder->order_no}",
            );

            $totalMaterialCost += $movement->total_cost;

            if ($wastageQty > 0) {
                $this->issueService->issue(
                    $company,
                    $productionOrder,
                    $consumption->product_variant_id,
                    $consumption->warehouse_id,
                    $wastageQty,
                    ChangeTypeEnum::WASTAGE,
                    $userId,
                    "Production Order {$productionOrder->order_no} — Wastage",
                );
            }

            $consumption->update([
                'consumed_qty' => $consumedQty,
                'unit_cost' => $movement->unit_cost,
                'batch_id' => $consumptionData['batch_id'] ?? $consumption->batch_id,
            ]);
        }

        $producedQty = (int) $data['produced_qty'];

        if ($producedQty <= 0) {
            throw ValidationException::withMessages([
                'produced_qty' => __('Produced quantity must be greater than zero.'),
            ]);
        }

        $finishedGoodsUnitCost = round($totalMaterialCost / $producedQty, 4);

        $this->receiptService->receive(
            $company,
            $productionOrder,
            $bom->product_variant_id,
            $productionOrder->warehouse_id,
            $producedQty,
            $finishedGoodsUnitCost,
            ChangeTypeEnum::FINISHED_GOODS,
            $userId,
            "Production Order {$productionOrder->order_no} — Finished Goods",
            sourceProductionOrderId: $productionOrder->id,
        );

        $productionOrder->update([
            'status' => 'completed',
            'produced_qty' => $data['produced_qty'],
            'actual_end' => now(),
            'approve_user_id' => $userId,
            'approved_at' => now(),
        ]);

        return $productionOrder->fresh();
    }
}
