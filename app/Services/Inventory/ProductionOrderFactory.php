<?php

namespace App\Services\Inventory;

use App\Models\Bom;
use App\Models\Company;
use App\Models\ProductionOrder;
use App\Services\DocumentNumberGenerator;

/**
 * Creates a production order from a BOM — its consumptions (scaled to planned qty),
 * routing operations, and (optionally) material reservations. Shared by the manual
 * create endpoint and the sub-assembly cascade. Must run inside a DB transaction.
 */
class ProductionOrderFactory
{
    public function __construct(
        private DocumentNumberGenerator $documentNumberGenerator,
        private StockReservationService $reservationService,
    ) {}

    /**
     * @param  array{planned_start?: mixed, planned_end?: mixed, remarks?: ?string}  $options
     */
    public function create(
        Company $company,
        Bom $bom,
        int $warehouseId,
        float $plannedQty,
        int $userId,
        array $options = [],
        bool $reserve = true,
    ): ProductionOrder {
        $bom->loadMissing(['items', 'operations']);

        $order = ProductionOrder::create([
            'company_id' => $company->id,
            'fiscal_year_id' => $company->fiscal_year_id,
            'bom_id' => $bom->id,
            'warehouse_id' => $warehouseId,
            'planned_qty' => $plannedQty,
            'planned_start' => $options['planned_start'] ?? null,
            'planned_end' => $options['planned_end'] ?? null,
            'remarks' => $options['remarks'] ?? null,
            'order_no' => $this->documentNumberGenerator->productionOrder($company->id),
            'status' => 'draft',
            'create_user_id' => $userId,
        ]);

        $ratio = $plannedQty / ((float) $bom->output_qty ?: 1.0);
        $reservationItems = [];

        foreach ($bom->items as $item) {
            $requiredQty = round($item->quantity * (1 + $item->wastage_pct / 100) * $ratio, 4);

            $order->consumptions()->create([
                'company_id' => $company->id,
                'product_variant_id' => $item->product_variant_id,
                'warehouse_id' => $warehouseId,
                'required_qty' => $requiredQty,
                'unit_id' => $item->unit_id,
            ]);

            if ($reserve && $item->item_type === 'material' && $requiredQty > 0) {
                $reservationItems[] = [
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $requiredQty,
                ];
            }
        }

        foreach ($bom->operations as $op) {
            $order->operations()->create([
                'company_id' => $company->id,
                'bom_operation_id' => $op->id,
                'sequence' => $op->sequence,
                'name' => $op->name,
                'work_center' => $op->work_center,
                'status' => 'pending',
            ]);
        }

        if ($reservationItems !== []) {
            $this->reservationService->reserve($company, $order, $reservationItems, $userId);
        }

        return $order;
    }
}
