<?php

namespace App\Http\Controllers\Api\Admin\Inventory;

use Illuminate\Http\Request;
use App\Annotation\Permissions;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\Inventory\InventoryReportService;

class InventoryReportController extends Controller
{
    public function __construct(private InventoryReportService $reportService) {}

    #[Permissions('inventory_stock_movement_report', group: 'inventory_report', desc: 'Stock Movement Report')]
    public function stockMovement(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reportService->stockMovement($request)]);
    }

    #[Permissions('inventory_stock_ledger_report', group: 'inventory_report', desc: 'Stock Ledger Report')]
    public function stockLedger(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reportService->stockLedger($request)]);
    }

    #[Permissions('inventory_warehouse_stock_report', group: 'inventory_report', desc: 'Warehouse Wise Stock Report')]
    public function warehouseStock(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reportService->warehouseStock($request)]);
    }

    #[Permissions('inventory_warehouse_transfer_report', group: 'inventory_report', desc: 'Warehouse Transfer Report')]
    public function warehouseTransfer(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reportService->warehouseTransfer($request)]);
    }

    #[Permissions('inventory_expiry_stock_report', group: 'inventory_report', desc: 'Expiry Stock Report')]
    public function expiryStock(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reportService->expiryStock($request)]);
    }

    #[Permissions('inventory_dead_stock_report', group: 'inventory_report', desc: 'Dead Stock Report')]
    public function deadStock(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reportService->deadStock($request)]);
    }

    #[Permissions('inventory_stock_opening_report', group: 'inventory_report', desc: 'Stock Opening Report')]
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
    public function productionVariance(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reportService->productionVariance($request)]);
    }

    #[Permissions('inventory_batch_stock_report', group: 'inventory_report', desc: 'Batch Stock Report')]
    public function batchStock(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reportService->batchStock($request)]);
    }

    #[Permissions('inventory_batch_traceability_report', group: 'inventory_report', desc: 'Batch Traceability Report')]
    public function batchTraceability(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reportService->batchTraceability($request)]);
    }
}
