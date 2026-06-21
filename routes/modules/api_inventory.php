<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\Inventory\BomController;
use App\Http\Controllers\Api\Admin\Inventory\BomOperationController;
use App\Http\Controllers\Api\Admin\Inventory\UnitController;
use App\Http\Controllers\Api\Admin\Inventory\UnitConversionController;
use App\Http\Controllers\Api\Admin\Inventory\BatchController;
use App\Http\Controllers\Api\Admin\Inventory\BrandController;
use App\Http\Controllers\Api\Admin\Inventory\BarcodeController;
use App\Http\Controllers\Api\Admin\Inventory\ProductController;
use App\Http\Controllers\Api\Admin\Inventory\AttributeController;
use App\Http\Controllers\Api\Admin\Inventory\SerialNumberController;
use App\Http\Controllers\Api\Admin\Inventory\WarehouseController;
use App\Http\Controllers\Api\Admin\Inventory\StockTransferController;
use App\Http\Controllers\Api\Admin\Inventory\DeliveryChallanController;
use App\Http\Controllers\Api\Admin\Inventory\ProductCategoryController;
use App\Http\Controllers\Api\Admin\Inventory\ProductionOrderController;
use App\Http\Controllers\Api\Admin\Inventory\OpeningStockEntryController;
use App\Http\Controllers\Api\Admin\Inventory\StockAdjustmentController;
use App\Http\Controllers\Api\Admin\Inventory\GoodsReceivedNoteController;
use App\Http\Controllers\Api\Admin\Inventory\InventoryStockReconciliationController;
use App\Http\Controllers\Api\Admin\Inventory\InventoryStockReconciliationAlignController;
use App\Http\Controllers\Api\Admin\Inventory\DamageReportController;
use App\Http\Controllers\Api\Admin\Inventory\InventoryReportController;

// unit
Route::get('unit/next-code', [UnitController::class, 'nextCode'])->name('unit.next-code');
Route::apiResource('unit', UnitController::class);

// brand
Route::get('brand/next-code', [BrandController::class, 'nextCode'])->name('brand.next-code');
Route::apiResource('brand', BrandController::class);

// warehouse
Route::get('warehouse/next-code', [WarehouseController::class, 'nextCode'])->name('warehouse.next-code');
Route::apiResource('warehouse', WarehouseController::class);

// product category
Route::apiResource('product-category', ProductCategoryController::class);

// product
Route::get('product/variant/all', [ProductController::class, 'productVariants'])->name('product.variant.all');
Route::get('product/variant/search', [ProductController::class, 'searchProductVariants'])->name('product.variant.search');
Route::get('product/next-code', [ProductController::class, 'nextCode'])->name('product.next-code');
Route::apiResource('product', ProductController::class);

// barcode generation & label printing
Route::prefix('barcode')->as('barcode.')->controller(BarcodeController::class)->group(function () {
    Route::post('pdf', 'pdf')->name('pdf');
    Route::post('preview', 'preview')->name('preview');
});

// product attribute
Route::apiResource('attribute', AttributeController::class);

// serial numbers
Route::apiResource('serial-number', SerialNumberController::class)->only(['index', 'show']);

// stock transfer
Route::post('stock-transfer/{stockTransfer}/approve', [StockTransferController::class, 'approve'])->name('stock-transfer.approve');
Route::post('stock-transfer/{stockTransfer}/dispatch', [StockTransferController::class, 'dispatch'])->name('stock-transfer.dispatch');
Route::post('stock-transfer/{stockTransfer}/receive', [StockTransferController::class, 'receive'])->name('stock-transfer.receive');
Route::apiResource('stock-transfer', StockTransferController::class);

// stock adjustment
Route::post('stock-adjustment/{stockAdjustment}/approve', [StockAdjustmentController::class, 'approve'])->name('stock-adjustment.approve');
Route::apiResource('stock-adjustment', StockAdjustmentController::class);

// damage reports
Route::post('damage-report/{damageReport}/approve', [DamageReportController::class, 'approve'])->name('damage-report.approve');
Route::apiResource('damage-report', DamageReportController::class)->parameters(['damage-report' => 'damageReport']);

// opening stock entry
Route::post('opening-stock-entry/{openingStockEntry}/approve', [OpeningStockEntryController::class, 'approve'])->name('opening-stock-entry.approve');
Route::apiResource('opening-stock-entry', OpeningStockEntryController::class)->parameters(['opening-stock-entry' => 'openingStockEntry']);

Route::get('inventory/stock-reconciliation', InventoryStockReconciliationController::class)->name('inventory.stock-reconciliation');
Route::post('inventory/stock-reconciliation/align', InventoryStockReconciliationAlignController::class)->name('inventory.stock-reconciliation.align');

// goods received notes
Route::get('grn/billable-items', [GoodsReceivedNoteController::class, 'billableItems'])->name('grn.billable-items');
Route::post('grn/{goodsReceivedNote}/approve', [GoodsReceivedNoteController::class, 'approve'])->name('grn.approve');
Route::apiResource('grn', GoodsReceivedNoteController::class)->parameters(['grn' => 'goodsReceivedNote']);

// delivery challans
Route::post('delivery-challan/{deliveryChallan}/approve', [DeliveryChallanController::class, 'approve'])->name('delivery-challan.approve');
Route::apiResource('delivery-challan', DeliveryChallanController::class)->parameters(['delivery-challan' => 'deliveryChallan']);

// Batches / Lot tracking
Route::get('batch/expiry-alerts', [BatchController::class, 'expiryAlerts'])->name('batch.expiry-alerts');
Route::get('batch/fefo', [BatchController::class, 'fefoList'])->name('batch.fefo');
Route::post('batch/{batch}/write-off', [BatchController::class, 'writeOff'])->name('batch.write-off');
Route::apiResource('batch', BatchController::class)->except(['destroy']);

// Bill of Materials
Route::get('bom/where-used/{variant}', [BomController::class, 'whereUsed'])->name('bom.where-used');
Route::get('bom/{bom}/explode', [BomController::class, 'explode'])->name('bom.explode');
Route::apiResource('bom', BomController::class);

// inventory reports
Route::prefix('inventory-report')->as('inventory-report.')->controller(InventoryReportController::class)->group(function () {
    Route::get('stock-movement', 'stockMovement')->name('stock-movement');
    Route::get('stock-ledger', 'stockLedger')->name('stock-ledger');
    Route::get('warehouse-stock', 'warehouseStock')->name('warehouse-stock');
    Route::get('warehouse-transfer', 'warehouseTransfer')->name('warehouse-transfer');
    Route::get('expiry-stock', 'expiryStock')->name('expiry-stock');
    Route::get('dead-stock', 'deadStock')->name('dead-stock');
    Route::get('stock-opening', 'stockOpening')->name('stock-opening');
    Route::get('inventory-summary', 'inventorySummary')->name('inventory-summary');
    Route::get('production-variance', 'productionVariance')->name('production-variance');
    Route::get('mrp-plan', 'mrpPlan')->name('mrp-plan');
    Route::get('work-center-load', 'workCenterLoad')->name('work-center-load');
    Route::get('batch-stock', 'batchStock')->name('batch-stock');
    Route::get('batch-traceability', 'batchTraceability')->name('batch-traceability');
});

// Production Orders
Route::post('production-order/{productionOrder}/start', [ProductionOrderController::class, 'start'])->name('production-order.start');
Route::post('production-order/{productionOrder}/complete', [ProductionOrderController::class, 'complete'])->name('production-order.complete');
Route::post('production-order/{productionOrder}/cancel', [ProductionOrderController::class, 'cancel'])->name('production-order.cancel');
Route::post('production-order/{productionOrder}/operations/{operation}/start', [ProductionOrderController::class, 'startOperation'])->name('production-order.operation.start');
Route::post('production-order/{productionOrder}/operations/{operation}/complete', [ProductionOrderController::class, 'completeOperation'])->name('production-order.operation.complete');
Route::post('production-order/{productionOrder}/operations/{operation}/skip', [ProductionOrderController::class, 'skipOperation'])->name('production-order.operation.skip');
Route::apiResource('production-order', ProductionOrderController::class)->except(['update', 'destroy']);

// BOM Operations (nested under BOM)
Route::apiResource('bom.operations', BomOperationController::class)->parameters(['operations' => 'bomOperation'])->except(['show']);

// Unit Conversions
Route::apiResource('unit-conversion', UnitConversionController::class)->parameters(['unit-conversion' => 'unitConversion']);
