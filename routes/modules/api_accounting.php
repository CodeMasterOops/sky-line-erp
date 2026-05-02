<?php

use App\Http\Controllers\Api\Admin\Accounting\AccountController;
use App\Http\Controllers\Api\Admin\Accounting\AccountGroupController;
use App\Http\Controllers\Api\Admin\Accounting\AccountingPeriodController;
use App\Http\Controllers\Api\Admin\Accounting\AccountReportController;
use App\Http\Controllers\Api\Admin\Accounting\AccountSettingController;
use App\Http\Controllers\Api\Admin\Accounting\BankReconciliationController;
use App\Http\Controllers\Api\Admin\Accounting\BudgetController;
use App\Http\Controllers\Api\Admin\Accounting\CashFlowForecastController;
use App\Http\Controllers\Api\Admin\Accounting\ChequeController;
use App\Http\Controllers\Api\Admin\Accounting\CurrencyController;
use App\Http\Controllers\Api\Admin\Accounting\FixedAssetController;
use App\Http\Controllers\Api\Admin\Accounting\JournalVoucherController;
use App\Http\Controllers\Api\Admin\Accounting\PaymentVoucherController;
use App\Http\Controllers\Api\Admin\Accounting\ReceiptVoucherController;
use App\Http\Controllers\Api\Admin\Accounting\RecurringJournalController;
use Illuminate\Support\Facades\Route;

// account groups
Route::apiResource('account-group', AccountGroupController::class);

// accounts
Route::get('account/coa', [AccountController::class, 'coa'])->name('account.coa');
Route::apiResource('account', AccountController::class);

// journal voucher
Route::post('journal-voucher/{journalVoucher}/approve', [JournalVoucherController::class, 'approve'])->name('journal-voucher.approve');
Route::apiResource('journal-voucher', JournalVoucherController::class);

// payment voucher
Route::post('payment-voucher/{paymentVoucher}/approve', [PaymentVoucherController::class, 'approve'])->name('payment-voucher.approve');
Route::apiResource('payment-voucher', PaymentVoucherController::class)->parameters([
    'payment-voucher' => 'paymentVoucher',
]);

// receipt voucher
Route::post('receipt-voucher/{receiptVoucher}/approve', [ReceiptVoucherController::class, 'approve'])->name('receipt-voucher.approve');
Route::apiResource('receipt-voucher', ReceiptVoucherController::class)->parameters([
    'receipt-voucher' => 'receiptVoucher',
]);

// account settings
Route::apiResource('account-setting', AccountSettingController::class)->only('index', 'store');

// accounting periods
Route::prefix('accounting-period')->as('accounting-period.')->controller(AccountingPeriodController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('generate', 'generate')->name('generate');
    Route::post('{accountingPeriod}/close', 'close')->name('close');
    Route::post('{accountingPeriod}/reopen', 'reopen')->name('reopen');
    Route::post('{accountingPeriod}/lock', 'lock')->name('lock');
});

// bank reconciliation
Route::prefix('bank-reconciliation')->as('bank-reconciliation.')->controller(BankReconciliationController::class)->group(function () {
    Route::get('bank-accounts', 'bankAccounts')->name('bank-accounts');
    Route::post('bank-accounts', 'storeBankAccount')->name('bank-accounts.store');
    Route::get('bank-accounts/{bankAccount}/statement-lines', 'statementLines')->name('statement-lines');
    Route::post('bank-accounts/{bankAccount}/import-lines', 'importLines')->name('import-lines');
    Route::post('bank-accounts/{bankAccount}/auto-match', 'autoMatch')->name('auto-match');
    Route::post('statement-lines/{bankStatementLine}/match', 'matchLine')->name('match');
    Route::post('statement-lines/{bankStatementLine}/unmatch', 'unmatchLine')->name('unmatch');
});

// fixed assets
Route::prefix('fixed-asset')->as('fixed-asset.')->controller(FixedAssetController::class)->group(function () {
    Route::get('categories', 'categories')->name('categories');
    Route::post('categories', 'storeCategory')->name('categories.store');
    Route::get('schedule', 'schedule')->name('schedule');
    Route::get('{fixedAsset}', 'show')->name('show');
    Route::post('/', 'store')->name('store');
    Route::put('{fixedAsset}', 'update')->name('update');
    Route::delete('{fixedAsset}', 'destroy')->name('destroy');
    Route::get('/', 'index')->name('index');
});

// recurring journals
Route::post('recurring-journal/{recurringJournal}/run-now', [RecurringJournalController::class, 'runNow'])->name('recurring-journal.run-now');
Route::apiResource('recurring-journal', RecurringJournalController::class)->parameters(['recurring-journal' => 'recurringJournal']);

// currencies — read-only for company admins; managed by super-admin
Route::get('currency', [CurrencyController::class, 'index'])->name('currency.index');

// accounting reports
Route::prefix('account-report')->as('account-report.')->controller(AccountReportController::class)->group(function () {
    Route::get('trial-balance', 'trialBalance')->name('trial-balance');
    Route::get('balance-sheet', 'balanceSheet')->name('balance-sheet');
    Route::get('profit-loss', 'profitLoss')->name('profit-loss');
    Route::get('journal-report', 'journalReport')->name('journal-report');
    Route::get('general-ledger', 'generalLedger')->name('general-ledger');
    Route::get('vat-sales-register', 'vatSalesRegister')->name('vat-sales-register');
    Route::get('vat-purchase-register', 'vatPurchaseRegister')->name('vat-purchase-register');
    Route::get('vat-return', 'vatReturn')->name('vat-return');
    Route::get('cash-flow', 'cashFlow')->name('cash-flow');
    Route::get('ar-aging', 'arAging')->name('ar-aging');
    Route::get('ap-aging', 'apAging')->name('ap-aging');
    Route::get('inventory-valuation', 'inventoryValuation')->name('inventory-valuation');
    Route::get('stock-aging', 'stockAging')->name('stock-aging');
    Route::get('reorder-alerts', 'reorderAlerts')->name('reorder-alerts');
    Route::get('tds-report', 'tdsReport')->name('tds-report');
});

// PDC Cheques
Route::prefix('cheque')->as('cheque.')->controller(ChequeController::class)->group(function () {
    Route::get('summary', 'summary')->name('summary');
    Route::post('{cheque}/present', 'present')->name('present');
    Route::post('{cheque}/clear', 'clear')->name('clear');
    Route::post('{cheque}/bounce', 'bounce')->name('bounce');
    Route::post('{cheque}/cancel', 'cancel')->name('cancel');
});
Route::apiResource('cheque', ChequeController::class)->except(['update', 'destroy']);

// Budget management
Route::get('budget/{budget}/vs-actual', [BudgetController::class, 'vsActual'])->name('budget.vs-actual');
Route::apiResource('budget', BudgetController::class);

// Cash flow forecasting
Route::get('cash-flow-forecast', CashFlowForecastController::class)->name('cash-flow-forecast');

// Bank CSV import (extends existing bank reconciliation)
Route::post('bank-reconciliation/bank-accounts/{bankAccount}/import-csv', [BankReconciliationController::class, 'importCsv'])->name('bank-reconciliation.import-csv');
