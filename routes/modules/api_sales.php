<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\Sales\InvoiceController;
use App\Http\Controllers\Api\Admin\Sales\ReceiptController;
use App\Http\Controllers\Api\Admin\Sales\QuotationController;
use App\Http\Controllers\Api\Admin\Sales\CreditNoteController;
use App\Http\Controllers\Api\Admin\Sales\SalesOrderController;
use App\Http\Controllers\Api\Admin\Sales\SalesReportController;
use App\Http\Controllers\Api\Admin\Sales\CustomerAdvanceController;

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
    Route::get('sales-summary', 'salesSummary')->name('sales-summary');
    Route::get('daily-sales', 'dailySales')->name('daily-sales');
    Route::get('monthly-sales', 'monthlySales')->name('monthly-sales');
    Route::get('yearly-sales', 'yearlySales')->name('yearly-sales');
    Route::get('customer-wise-sales', 'customerWiseSales')->name('customer-wise-sales');
    Route::get('category-wise-sales', 'categoryWiseSales')->name('category-wise-sales');
    Route::get('sales-return', 'salesReturn')->name('sales-return');
    Route::get('outstanding-sales', 'outstandingSales')->name('outstanding-sales');
    Route::get('sales-tax', 'salesTax')->name('sales-tax');
    Route::get('sales-profit', 'salesProfit')->name('sales-profit');
    Route::get('discount-report', 'discountReport')->name('discount-report');
    Route::get('sales-ledger', 'salesLedger')->name('sales-ledger');
});

// invoice
Route::get('invoice/due', [InvoiceController::class, 'dueInvoices'])->name('invoice.due');
Route::post('invoice/{invoice}/approve', [InvoiceController::class, 'approve'])->name('invoice.approve');
Route::post('invoice/{invoice}/void', [InvoiceController::class, 'void'])->name('invoice.void');
Route::post('invoice/{invoice}/write-off', [InvoiceController::class, 'writeOff'])->name('invoice.write-off');
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
Route::apiResource('receipt', ReceiptController::class);

// customer advance
Route::post('customer-advance/{customerAdvance}/approve', [CustomerAdvanceController::class, 'approve'])->name('customer-advance.approve');
Route::post('customer-advance/{customerAdvance}/apply', [CustomerAdvanceController::class, 'apply'])->name('customer-advance.apply');
Route::post('customer-advance/{customerAdvance}/void', [CustomerAdvanceController::class, 'void'])->name('customer-advance.void');
Route::apiResource('customer-advance', CustomerAdvanceController::class)->parameters([
    'customer-advance' => 'customerAdvance',
])->except(['update']);
