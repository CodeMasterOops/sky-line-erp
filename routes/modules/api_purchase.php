<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\Purchase\BillController;
use App\Http\Controllers\Api\Admin\Purchase\ExpenseController;
use App\Http\Controllers\Api\Admin\Purchase\PaymentController;
use App\Http\Controllers\Api\Admin\Purchase\DebitNoteController;
use App\Http\Controllers\Api\Admin\Purchase\PurchaseOrderController;
use App\Http\Controllers\Api\Admin\Purchase\PurchaseReportController;

// purchase order
Route::post('purchase-order/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('purchase-order.approve');
Route::apiResource('purchase-order', PurchaseOrderController::class);

// bill
Route::get('bill/due', [BillController::class, 'dueBills'])->name('bill.due');
Route::post('bill/{bill}/approve', [BillController::class, 'approve'])->name('bill.approve');
Route::post('bill/{bill}/void', [BillController::class, 'void'])->name('bill.void');
Route::apiResource('bill', BillController::class);

// expense
Route::get('expense/due', [ExpenseController::class, 'dueExpenses'])->name('expense.due');
Route::post('expense/{expense}/approve', [ExpenseController::class, 'approve'])->name('expense.approve');
Route::apiResource('expense', ExpenseController::class);

// payment
Route::post('payment/{payment}/approve', [PaymentController::class, 'approve'])->name('payment.approve');
Route::apiResource('payment', PaymentController::class)->parameters([
    'payment' => 'payment',
]);

// debit note
Route::post('debit-note/{debitNote}/approve', [DebitNoteController::class, 'approve'])->name('debit-note.approve');
Route::post('debit-note/{debitNote}/void', [DebitNoteController::class, 'void'])->name('debit-note.void');
Route::apiResource('debit-note', DebitNoteController::class)->parameters([
    'debit-note' => 'debitNote',
]);

// purchase reports
Route::prefix('purchase-report')->as('purchase-report.')->controller(PurchaseReportController::class)->group(function () {
    Route::get('dashboard', 'dashboard')->name('dashboard');
    Route::get('report', 'purchaseReport')->name('report');
    Route::get('purchase-by-item', 'purchaseByItems')->name('purchase-by-item');
});
