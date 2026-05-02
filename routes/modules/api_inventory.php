<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\Inventory\BomController;
use App\Http\Controllers\Api\Admin\Inventory\UnitController;
use App\Http\Controllers\Api\Admin\Inventory\BatchController;
use App\Http\Controllers\Api\Admin\Inventory\BrandController;
use App\Http\Controllers\Api\Admin\Inventory\BarcodeController;
use App\Http\Controllers\Api\Admin\Inventory\ProductController;
use App\Http\Controllers\Api\Admin\Inventory\AttributeController;
use App\Http\Controllers\Api\Admin\Inventory\WarehouseController;
use App\Http\Controllers\Api\Admin\Inventory\StockTransferController;
use App\Http\Controllers\Api\Admin\Inventory\DeliveryChallanController;
use App\Http\Controllers\Api\Admin\Inventory\ProductCategoryController;
use App\Http\Controllers\Api\Admin\Inventory\ProductionOrderController;
use App\Http\Controllers\Api\Admin\Inventory\StockAdjustmentController;
use App\Http\Controllers\Api\Admin\Inventory\GoodsReceivedNoteController;
use App\Http\Controllers\Api\Admin\Inventory\InventoryStockReconciliationController;
use App\Http\Controllers\Api\Admin\Inventory\InventoryStockReconciliationAlignController;

// unit
Route::apiResource('unit', UnitController::class);

// brand
Route::apiResource('brand', BrandController::class);

// warehouse
Route::apiResource('warehouse', WarehouseController::class);

// product category
Route::apiResource('product-category', ProductCategoryController::class);

// product
Route::get('product/variant/all', [ProductController::class, 'productVariants'])->name('product.variant.all');
Route::get('product/variant/search', [ProductController::class, 'searchProductVariants'])->name('product.variant.search');
Route::apiResource('product', ProductController::class);

// barcode generation & label printing
Route::prefix('barcode')->as('barcode.')->controller(BarcodeController::class)->group(function () {
    Route::post('pdf', 'pdf')->name('pdf');
    Route::post('preview', 'preview')->name('preview');
});

// product attribute
Route::apiResource('attribute', AttributeController::class);

// stock transfer
Route::post('stock-transfer/{stockTransfer}/approve', [StockTransferController::class, 'approve'])->name('stock-transfer.approve');
Route::apiResource('stock-transfer', StockTransferController::class);

// stock adjustment
Route::post('stock-adjustment/{stockAdjustment}/approve', [StockAdjustmentController::class, 'approve'])->name('stock-adjustment.approve');
Route::apiResource('stock-adjustment', StockAdjustmentController::class);

Route::get('inventory/stock-reconciliation', InventoryStockReconciliationController::class)->name('inventory.stock-reconciliation');
Route::post('inventory/stock-reconciliation/align', InventoryStockReconciliationAlignController::class)->name('inventory.stock-reconciliation.align');

// goods received notes
Route::post('grn/{goodsReceivedNote}/approve', [GoodsReceivedNoteController::class, 'approve'])->name('grn.approve');
Route::apiResource('grn', GoodsReceivedNoteController::class)->parameters(['grn' => 'goodsReceivedNote']);

// delivery challans
Route::post('delivery-challan/{deliveryChallan}/approve', [DeliveryChallanController::class, 'approve'])->name('delivery-challan.approve');
Route::apiResource('delivery-challan', DeliveryChallanController::class)->parameters(['delivery-challan' => 'deliveryChallan']);

// Batches / Lot tracking
Route::get('batch/expiry-alerts', [BatchController::class, 'expiryAlerts'])->name('batch.expiry-alerts');
Route::get('batch/fefo', [BatchController::class, 'fefoList'])->name('batch.fefo');
Route::apiResource('batch', BatchController::class)->except(['destroy']);

// Bill of Materials
Route::apiResource('bom', BomController::class);

// Production Orders
Route::post('production-order/{productionOrder}/start', [ProductionOrderController::class, 'start'])->name('production-order.start');
Route::post('production-order/{productionOrder}/complete', [ProductionOrderController::class, 'complete'])->name('production-order.complete');
Route::post('production-order/{productionOrder}/cancel', [ProductionOrderController::class, 'cancel'])->name('production-order.cancel');
Route::apiResource('production-order', ProductionOrderController::class)->except(['update', 'destroy']);
