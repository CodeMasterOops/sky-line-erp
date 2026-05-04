<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\Sales\InvoiceController;
use App\Http\Controllers\Api\Admin\Sales\ReceiptController;
use App\Http\Controllers\Api\Admin\Sales\QuotationController;
use App\Http\Controllers\Api\Admin\Sales\CreditNoteController;
use App\Http\Controllers\Api\Admin\Sales\SalesOrderController;
use App\Http\Controllers\Api\Admin\Sales\SalesReportController;

// quotation
Route::post('quotation/{quotation}/approve', [QuotationController::class, 'approve'])->name('quotation.approve');
Route::apiResource('quotation', QuotationController::class);

// sales order
Route::post('sales-order/{salesOrder}/approve', [SalesOrderController::class, 'approve'])->name('sales-order.approve');
Route::apiResource('sales-order', SalesOrderController::class);

// sales reports
Route::prefix('sales-report')->as('sales-report.')->controller(SalesReportController::class)->group(function () {
    Route::get('dashboard', 'dashboard')->name('dashboard');
    Route::get('report', 'salesReport')->name('report');
    Route::get('sales-by-item', 'salesByItems')->name('sales-by-item');
});

// invoice
Route::get('invoice/due', [InvoiceController::class, 'dueInvoices'])->name('invoice.due');
Route::post('invoice/{invoice}/approve', [InvoiceController::class, 'approve'])->name('invoice.approve');
Route::post('invoice/{invoice}/void', [InvoiceController::class, 'void'])->name('invoice.void');
Route::apiResource('invoice', InvoiceController::class);

// credit note
Route::post('credit-note/{creditNote}/approve', [CreditNoteController::class, 'approve'])->name('credit-note.approve');
Route::post('credit-note/{creditNote}/void', [CreditNoteController::class, 'void'])->name('credit-note.void');
Route::apiResource('credit-note', CreditNoteController::class)->parameters([
    'credit-note' => 'creditNote',
]);

// receipt
Route::post('receipt/{receipt}/approve', [ReceiptController::class, 'approve'])->name('receipt.approve');
Route::apiResource('receipt', ReceiptController::class);
