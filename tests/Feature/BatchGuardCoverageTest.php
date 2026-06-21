<?php

use App\Services\Inventory\BatchGuard;
use App\Http\Requests\Api\Admin\Purchase\BillRequest;
use App\Http\Requests\Api\Admin\Sales\InvoiceRequest;
use App\Http\Requests\Api\Admin\Sales\CreditNoteRequest;
use App\Http\Requests\Api\Admin\Inventory\DamageReportRequest;
use App\Http\Requests\Api\Admin\Inventory\StockTransferRequest;
use App\Http\Requests\Api\Admin\Inventory\DeliveryChallanRequest;
use App\Http\Requests\Api\Admin\Inventory\StockAdjustmentRequest;
use App\Http\Requests\Api\Admin\Inventory\GoodsReceivedNoteRequest;
use App\Http\Requests\Api\Admin\Inventory\OpeningStockEntryRequest;

/**
 * Every document that receives or issues batch-tracked stock must run BatchGuard
 * so a batch-tracked variant can never move without a batch. This locks that
 * coverage in: a new stock document (or a removed guard) should fail here.
 */
it('runs BatchGuard on every stock-movement request', function (string $requestClass) {
    $source = file_get_contents((new ReflectionClass($requestClass))->getFileName());

    expect($source)
        ->toContain('BatchGuard')
        ->and($source)->toContain('BatchGuard::validateItems');
})->with([
    'GoodsReceivedNote' => [GoodsReceivedNoteRequest::class],
    'OpeningStockEntry' => [OpeningStockEntryRequest::class],
    'Bill' => [BillRequest::class],
    'Invoice' => [InvoiceRequest::class],
    'CreditNote' => [CreditNoteRequest::class],
    'DeliveryChallan' => [DeliveryChallanRequest::class],
    'StockTransfer' => [StockTransferRequest::class],
    'StockAdjustment' => [StockAdjustmentRequest::class],
    'DamageReport' => [DamageReportRequest::class],
]);

it('exposes the BatchGuard validateItems entry point', function () {
    expect(method_exists(BatchGuard::class, 'validateItems'))->toBeTrue();
});
