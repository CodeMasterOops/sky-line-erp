<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\Sales\InvoiceController;
use App\Http\Controllers\Api\Admin\Sales\ReceiptController;
use App\Http\Controllers\Api\Admin\Sales\QuotationController;
use App\Http\Controllers\Api\Admin\Sales\CreditNoteController;
use App\Http\Controllers\Api\Admin\Sales\SalesOrderController;
use App\Http\Controllers\Api\Admin\Sales\SalesReportController;
use App\Http\Controllers\Api\Admin\Sales\TdsChallanController;
use App\Http\Controllers\Api\Admin\Sales\AdvanceReceiptController;

// quotation
Route::post('quotation/{quotation}/approve', [QuotationController::class, 'approve'])->name('quotation.approve');
Route::apiResource('quotation', QuotationController::class);

// sales order
Route::get('sales-order/{salesOrder}/deliverable-items', [SalesOrderController::class, 'deliverableItems'])->name('sales-order.deliverable-items');
Route::post('sales-order/{salesOrder}/approve', [SalesOrderController::class, 'approve'])->name('sales-order.approve');
Route::apiResource('sales-order', SalesOrderController::class);

// sales reports
Route::prefix('sales-report')->as('sales-report.')->controller(SalesReportController::class)->group(function () {
    Route::get('dashboard', 'dashboard')->name('dashboard');
    Route::get('report', 'salesReport')->name('report');
    Route::get('sales-by-item', 'salesByItems')->name('sales-by-item');
    Route::get('aging', 'aging')->name('aging');
    Route::get('party-statement', 'partyStatement')->name('party-statement');
    Route::get('vat-register', 'vatRegister')->name('vat-register');
    Route::get('tds-register', 'tdsRegister')->name('tds-register');
    Route::get('outstanding', 'outstanding')->name('outstanding');
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
Route::post('receipt/{receipt}/void', [ReceiptController::class, 'void'])->name('receipt.void');
Route::post('receipt-payment/{receiptPayment}/clear-cheque', [ReceiptController::class, 'clearCheque'])->name('receipt-payment.clear-cheque');
Route::apiResource('receipt', ReceiptController::class);

// tds challan
Route::get('tds-deductions', [TdsChallanController::class, 'listDeductions'])->name('tds-deductions.index');
Route::post('tds-challan/{tdsChallan}/submit', [TdsChallanController::class, 'markSubmitted'])->name('tds-challan.submit');
Route::get('tds-certificate/{party}/{month}', [TdsChallanController::class, 'generateCertificate'])->name('tds-certificate');
Route::apiResource('tds-challan', TdsChallanController::class)->except(['update', 'destroy'])->parameters([
    'tds-challan' => 'tdsChallan',
]);

// advance receipts
Route::get('advance-receipt-party-balance', [AdvanceReceiptController::class, 'partyBalance'])->name('advance-receipt.party-balance');
Route::post('advance-receipt/{advanceReceipt}/approve', [AdvanceReceiptController::class, 'approve'])->name('advance-receipt.approve');
Route::post('advance-receipt/{advanceReceipt}/void', [AdvanceReceiptController::class, 'void'])->name('advance-receipt.void');
Route::post('advance-receipt/{advanceReceipt}/adjust', [AdvanceReceiptController::class, 'adjust'])->name('advance-receipt.adjust');
Route::apiResource('advance-receipt', AdvanceReceiptController::class)->parameters([
    'advance-receipt' => 'advanceReceipt',
]);
