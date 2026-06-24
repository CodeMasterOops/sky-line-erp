<?php

namespace App\Http\Controllers\Api\Admin\Inventory;

use Illuminate\Http\Request;
use App\Annotation\Permissions;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\Inventory\MrpPlanningService;
use App\Services\Inventory\InventoryReportService;

class InventoryReportController extends Controller
{
    public function __construct(
        private InventoryReportService $reportService,
        private MrpPlanningService $mrpService,
    ) {}

    #[Permissions('inventory_production_variance_report', group: 'inventory_report', desc: 'MRP Planning')]
    #[Permissions('list_production_order')]
    public function mrpPlan(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->mrpService->plan(auth('admin')->user()->company_id),
        ]);
    }

    #[Permissions('inventory_production_variance_report', group: 'inventory_report', desc: 'Work Centre Load')]
    #[Permissions('list_production_order')]
    public function workCenterLoad(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reportService->workCenterLoad($request)]);
    }

    #[Permissions('inventory_stock_movement_report', group: 'inventory_report', desc: 'Stock Movement Report')]
    #[Permissions('list_product')]
    public function stockMovement(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reportService->stockMovement($request)]);
    }

    #[Permissions('inventory_stock_ledger_report', group: 'inventory_report', desc: 'Stock Ledger Report')]
    #[Permissions('list_product')]
    public function stockLedger(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reportService->stockLedger($request)]);
    }

    #[Permissions('inventory_warehouse_stock_report', group: 'inventory_report', desc: 'Warehouse Wise Stock Report')]
    #[Permissions('list_product')]
    public function warehouseStock(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reportService->warehouseStock($request)]);
    }

    #[Permissions('inventory_warehouse_transfer_report', group: 'inventory_report', desc: 'Warehouse Transfer Report')]
    #[Permissions('list_product')]
    public function warehouseTransfer(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reportService->warehouseTransfer($request)]);
    }

    #[Permissions('inventory_expiry_stock_report', group: 'inventory_report', desc: 'Expiry Stock Report')]
    #[Permissions('list_product')]
    public function expiryStock(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reportService->expiryStock($request)]);
    }

    #[Permissions('inventory_dead_stock_report', group: 'inventory_report', desc: 'Dead Stock Report')]
    #[Permissions('list_product')]
    public function deadStock(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reportService->deadStock($request)]);
    }

    #[Permissions('inventory_stock_opening_report', group: 'inventory_report', desc: 'Stock Opening Report')]
    #[Permissions('list_product')]
    public function stockOpening(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reportService->stockOpening($request)]);
    }

    #[Permissions('inventory_summary_report', group: 'inventory_report', desc: 'Inventory Summary Report')]
    public function inventorySummary(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reportService->inventorySummary($request)]);
    }

    #[Permissions('inventory_production_variance_report', group: 'inventory_report', desc: 'Production Variance Report')]
    #[Permissions('list_production_order')]
    public function productionVariance(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reportService->productionVariance($request)]);
    }

    #[Permissions('inventory_batch_stock_report', group: 'inventory_report', desc: 'Batch Stock Report')]
    #[Permissions('list_batch')]
    public function batchStock(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reportService->batchStock($request)]);
    }

    #[Permissions('inventory_batch_traceability_report', group: 'inventory_report', desc: 'Batch Traceability Report')]
    #[Permissions('list_batch')]
    public function batchTraceability(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reportService->batchTraceability($request)]);
    }
}
