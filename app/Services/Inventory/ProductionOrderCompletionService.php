<?php

namespace App\Services\Inventory;

use App\Models\Bom;
use App\Models\Company;
use App\Enums\ChangeTypeEnum;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderConsumption;
use Illuminate\Validation\ValidationException;
use App\Services\Accounting\ProductionOrderGlPostingService;

class ProductionOrderCompletionService
{
    /** BOM line types whose standard cost is absorbed into WIP rather than issued from stock. */
    private const CONVERSION_ITEM_TYPES = ['labour', 'overhead'];

    public function __construct(
        private InventoryLayerIssueService $issueService,
        private InventoryLayerReceiptService $receiptService,
        private ProductionOrderGlPostingService $glPostingService,
    ) {}

    /**
     * Atomically issue all consumed raw materials and receipt finished goods
     * through the stock ledger (layers + movements + GL). Must be called inside
     * a DB::transaction().
     *
     * Supports partial completion: when $close is false the produced and consumed
     * quantities for this batch are added to the running totals and the order stays
     * open ('in_progress') for further completions. Each call posts its own incremental
     * stock movements and GL.
     *
     * @param  array{produced_qty: float, consumptions?: array<int, array{id: int, consumed_qty: float, batch_id?: int|null}>}  $data
     */
    public function complete(
        ProductionOrder $productionOrder,
        array $data,
        Company $company,
        int $userId,
        bool $close = true,
    ): ProductionOrder {
        $bom = $productionOrder->bom()->with(['productVariant.product', 'items'])->firstOrFail();
        $consumptions = $productionOrder->consumptions()->with('productVariant.product')->get();

        // Backflush: ignore any submitted consumptions and derive material usage from the
        // BOM at standard, scaled from planned to the units actually made this batch.
        if ($bom->is_backflush) {
            $data['consumptions'] = $this->backflushConsumptions($productionOrder, $consumptions, $data);
        }

        $totalMaterialCost = 0.0;

        foreach ($data['consumptions'] ?? [] as $consumptionData) {
            /** @var ProductionOrderConsumption|null $consumption */
            $consumption = $consumptions->firstWhere('id', $consumptionData['id']);
            if (! $consumption) {
                continue;
            }

            $consumedQty = (float) $consumptionData['consumed_qty'];
            $isPhysical = ! $consumption->productVariant->isService();

            if ($consumedQty <= 0 || ! $isPhysical) {
                $consumption->update([
                    'consumed_qty' => round((float) $consumption->consumed_qty + $consumedQty, 4),
                    'batch_id' => $consumptionData['batch_id'] ?? $consumption->batch_id,
                ]);

                continue;
            }

            // Split: planned (required) qty goes to MANUFACTURING_ISSUE (enters WIP cost),
            // excess (wastage) goes to WASTAGE (expensed to variance account).
            $requiredQty = (float) $consumption->required_qty;
            $plannedQty = min($consumedQty, $requiredQty);
            $wastageQty = max(0.0, $consumedQty - $requiredQty);

            $consumptionBatchId = $consumptionData['batch_id'] ?? $consumption->batch_id ?? null;

            $movement = $this->issueService->issue(
                $company,
                $productionOrder,
                $consumption->product_variant_id,
                $consumption->warehouse_id,
                $plannedQty,
                ChangeTypeEnum::MANUFACTURING_ISSUE,
                $userId,
                "Production Order {$productionOrder->order_no}",
                $consumptionBatchId,
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
                    $consumptionBatchId,
                );
            }

            $consumption->update([
                'consumed_qty' => round((float) $consumption->consumed_qty + $consumedQty, 4),
                'unit_cost' => $movement->unit_cost,
                'batch_id' => $consumptionData['batch_id'] ?? $consumption->batch_id,
            ]);
        }

        $producedQty = (float) $data['produced_qty'];

        if ($producedQty <= 0) {
            throw ValidationException::withMessages([
                'produced_qty' => __('Produced quantity must be greater than zero.'),
            ]);
        }

        // Scrap = defective units made this batch. When the variance/WIP accounts exist the
        // scrap cost is expensed out of WIP, so good and scrapped units share one unit cost
        // over all units made. Otherwise scrap is reported only and its cost stays capitalised
        // in good output (still balanced) — the cost basis collapses to good units.
        $scrapQty = max(0.0, (float) ($data['scrap_qty'] ?? 0));
        $expenseScrap = $scrapQty > 0 && $this->glPostingService->scrapAccountsConfigured($company->id);
        $costUnits = $expenseScrap ? $producedQty + $scrapQty : $producedQty;

        // Absorb standard labour/overhead conversion cost (BOM lines × units made) into WIP.
        // Only counted toward valuation when the absorption journal actually posts, so WIP
        // stays balanced when the production-overhead account is not configured.
        $conversionCost = $this->standardConversionCost($bom, $costUnits);
        $conversionJournal = $this->glPostingService->postConversion($productionOrder, $conversionCost, $userId);
        $absorbedConversion = $conversionJournal !== null ? $conversionCost : 0.0;

        // Absorb the standard subcontract service charge (charge per output × units made) into
        // WIP, same accounting shape as conversion but credited to the subcontract accrual.
        $subcontractCost = $bom->is_subcontracted
            ? round((float) $bom->subcontract_charge * $costUnits, 4)
            : 0.0;
        $subcontractJournal = $subcontractCost > 0
            ? $this->glPostingService->postSubcontract($productionOrder, $subcontractCost, $userId)
            : null;
        $absorbedSubcontract = $subcontractJournal !== null ? $subcontractCost : 0.0;

        $totalCost = $totalMaterialCost + $absorbedConversion + $absorbedSubcontract;
        $finishedGoodsUnitCost = round($totalCost / $costUnits, 4);

        if ($expenseScrap) {
            $this->glPostingService->postScrap(
                $productionOrder,
                round($scrapQty * $finishedGoodsUnitCost, 2),
                $userId,
            );
        }

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

        $newProducedQty = round((float) $productionOrder->produced_qty + $producedQty, 4);
        $newScrappedQty = round((float) $productionOrder->scrapped_qty + $scrapQty, 4);

        $productionOrder->update([
            'status' => $close ? 'completed' : 'in_progress',
            'produced_qty' => $newProducedQty,
            'scrapped_qty' => $newScrappedQty,
            'actual_end' => $close ? now() : $productionOrder->actual_end,
            'approve_user_id' => $close ? $userId : $productionOrder->approve_user_id,
            'approved_at' => $close ? now() : $productionOrder->approved_at,
            'gl_journal_id' => $conversionJournal?->id ?? $productionOrder->gl_journal_id,
        ]);

        return $productionOrder->fresh();
    }

    /**
     * Build the consumption payload for a backflush BOM: each physical material consumption
     * is auto-consumed at its standard required quantity, scaled from the planned quantity to
     * the units made this batch (good + scrap).
     *
     * @param  \Illuminate\Support\Collection<int, ProductionOrderConsumption>  $consumptions
     * @param  array<string, mixed>  $data
     * @return array<int, array{id: int, consumed_qty: float}>
     */
    private function backflushConsumptions(ProductionOrder $order, $consumptions, array $data): array
    {
        $plannedQty = (float) $order->planned_qty ?: 1.0;
        $unitsMade = (float) $data['produced_qty'] + max(0.0, (float) ($data['scrap_qty'] ?? 0));
        $ratio = $unitsMade / $plannedQty;

        return $consumptions
            ->filter(fn (ProductionOrderConsumption $c) => ! $c->productVariant->isService())
            ->map(fn (ProductionOrderConsumption $c) => [
                'id' => $c->id,
                'consumed_qty' => round((float) $c->required_qty * $ratio, 4),
            ])
            ->values()
            ->all();
    }

    /**
     * Standard labour + overhead cost for the produced quantity, derived from the BOM's
     * non-material lines (effective qty including wastage × standard rate) scaled from the
     * BOM output quantity to the quantity actually produced.
     */
    private function standardConversionCost(Bom $bom, float $producedQty): float
    {
        $perOutput = 0.0;
        foreach ($bom->items as $item) {
            if (in_array($item->item_type, self::CONVERSION_ITEM_TYPES, true)) {
                $perOutput += $item->effective_qty * (float) $item->standard_rate;
            }
        }

        $outputQty = (float) $bom->output_qty ?: 1.0;

        return round($producedQty * $perOutput / $outputQty, 4);
    }
}
