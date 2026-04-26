<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\TaxController;
use App\Http\Controllers\Api\Admin\PaymentModeController;
use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\EnumController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\UnitController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\BrandController;
use App\Http\Controllers\Api\Admin\PartyController;
use App\Http\Controllers\Api\Admin\AccountController;
use App\Http\Controllers\Api\Admin\AccountReportController;
use App\Http\Controllers\Api\Admin\JournalVoucherController;
use App\Http\Controllers\Api\Admin\PaymentVoucherController;
use App\Http\Controllers\Api\Admin\ReceiptVoucherController;
use App\Http\Controllers\Api\Admin\QuotationController;
use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\SalesOrderController;
use App\Http\Controllers\Api\Admin\InvoiceController;
use App\Http\Controllers\Api\Admin\PurchaseOrderController;
use App\Http\Controllers\Api\Admin\BillController;
use App\Http\Controllers\Api\Admin\ExpenseController;
use App\Http\Controllers\Api\Admin\PaymentController;
use App\Http\Controllers\Api\Admin\DebitNoteController;
use App\Http\Controllers\Api\Admin\CreditNoteController;
use App\Http\Controllers\Api\Admin\ReceiptController;
use App\Http\Controllers\Api\Admin\StockTransferController;
use App\Http\Controllers\Api\Admin\StockAdjustmentController;
use App\Http\Controllers\Api\Admin\InventoryStockReconciliationController;
use App\Http\Controllers\Api\Admin\InventoryStockReconciliationAlignController;
use App\Http\Controllers\Api\Admin\ProfileController;
use App\Http\Controllers\Api\Admin\SettingController;
use App\Http\Controllers\Api\Admin\AttributeController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\WarehouseController;
use App\Http\Controllers\Api\Admin\PermissionController;
use App\Http\Controllers\Api\Admin\AccountGroupController;
use App\Http\Controllers\Api\Admin\AdminSettingController;
use App\Http\Controllers\Api\Admin\AccountSettingController;
use App\Http\Controllers\Api\Admin\ProductCategoryController;
use App\Http\Controllers\Api\Admin\AdminNotificationController;
use App\Http\Controllers\Api\Admin\AccountingPeriodController;
use App\Http\Controllers\Api\Admin\BankReconciliationController;
use App\Http\Controllers\Api\Admin\GoodsReceivedNoteController;
use App\Http\Controllers\Api\Admin\DeliveryChallanController;
use App\Http\Controllers\Api\Admin\FixedAssetController;
use App\Http\Controllers\Api\Admin\RecurringJournalController;
use App\Http\Controllers\Api\Admin\CurrencyController;
use App\Http\Controllers\Api\Admin\SerialNumberController;
use App\Http\Controllers\Api\Admin\HR\DepartmentController;
use App\Http\Controllers\Api\Admin\HR\DesignationController;
use App\Http\Controllers\Api\Admin\HR\EmployeeController;
use App\Http\Controllers\Api\Admin\HR\HolidayController;
use App\Http\Controllers\Api\Admin\HR\LeaveTypeController;
use App\Http\Controllers\Api\Admin\HR\AttendanceController;
use App\Http\Controllers\Api\Admin\HR\LeaveApplicationController;
use App\Http\Controllers\Api\Admin\HR\SalaryComponentController;
use App\Http\Controllers\Api\Admin\HR\SalaryStructureController;
use App\Http\Controllers\Api\Admin\HR\PayrollController;
use App\Http\Controllers\Api\Admin\HR\PayslipController;
use App\Http\Controllers\Api\Admin\HR\HrReportController;
use App\Http\Controllers\Api\Admin\Nepal\InvoicePdfController;
use App\Http\Controllers\Api\Admin\Nepal\IrdSettingController;
use App\Http\Controllers\Api\Admin\Nepal\VatD3Controller;
use App\Http\Controllers\Api\Admin\Nepal\TdsChallanController;
// Phase 3 — Inventory Enhancements
use App\Http\Controllers\Api\Admin\BatchController;
use App\Http\Controllers\Api\Admin\BinController;
use App\Http\Controllers\Api\Admin\BomController;
use App\Http\Controllers\Api\Admin\ProductionOrderController;
// Phase 5 — Finance & Banking
use App\Http\Controllers\Api\Admin\ChequeController;
use App\Http\Controllers\Api\Admin\BudgetController;
use App\Http\Controllers\Api\Admin\CashFlowForecastController;
// Phase 6 — Multi-branch
use App\Http\Controllers\Api\Admin\BranchController;

Route::controller(AuthController::class)->group(function () {
    Route::post('login', 'login')->name('login');
    Route::post('logout', 'logout')->middleware('auth:admin');
});

Route::middleware('auth:admin')->group(function () {
    Route::middleware('checkRole')->group(function () {
        // profile
        Route::prefix('profile')->as('profile')->controller(ProfileController::class)->group(function () {
            Route::get('/', 'profile')->name('index');
            Route::post('update', 'updateProfile')->name('update');
            Route::put('change-password', 'changePassword')->name('changePassword');
        });

        // dashboard
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::apiResource('setting', SettingController::class)->only('index', 'store');

        // user management
        Route::get('permission', PermissionController::class)->name('permissions');
        Route::apiResource('role', RoleController::class);
        Route::put('user/{user}/update-status', [UserController::class, 'updateStatus'])->name('user.update-status');
        Route::apiResource('user', UserController::class);

        // notifications
        Route::prefix('notification')->as('notification.')->controller(AdminNotificationController::class)->group(function () {
            Route::get('all', 'allNotifications')->name('all');
            Route::get('unread', 'unreadNotifications')->name('unread');
            Route::post('mark-as-read/{id?}', 'markAsRead')->name('mark-as-read');
        });

        // unit
        Route::apiResource('unit', UnitController::class);

        // brand
        Route::apiResource('brand', BrandController::class);

        // warehouse
        Route::apiResource('warehouse', WarehouseController::class);

        // tax
        Route::apiResource('tax', TaxController::class);

        // payment mode
        Route::apiResource('payment-mode', PaymentModeController::class);

        // product category
        Route::apiResource('product-category', ProductCategoryController::class);

        // product
        Route::get('product/variant/all', [ProductController::class, 'productVariants'])->name('product.variant.all');
        Route::get('product/variant/search', [ProductController::class, 'searchProductVariants'])->name('product.variant.search');
        Route::apiResource('product', ProductController::class);

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

        // parties
        Route::apiResource('party', PartyController::class);

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

        // quotation
        Route::post('quotation/{quotation}/convert-to-sale', [QuotationController::class, 'convertToSale'])->name('quotation.convert-to-sale');
        Route::post('quotation/{quotation}/convert-to-invoice', [QuotationController::class, 'convertToInvoice'])->name('quotation.convert-to-invoice');
        Route::post('quotation/{quotation}/approve', [QuotationController::class, 'approve'])->name('quotation.approve');
        Route::apiResource('quotation', QuotationController::class);

        // sales order
        Route::post('sales-order/{salesOrder}/approve', [SalesOrderController::class, 'approve'])->name('sales-order.approve');
        Route::post('sales-order/{salesOrder}/convert-to-invoice', [SalesOrderController::class, 'convertToInvoice'])->name('sales-order.convert-to-invoice');
        Route::apiResource('sales-order', SalesOrderController::class);

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

        // purchase order
        Route::post('purchase-order/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('purchase-order.approve');
        Route::post('purchase-order/{purchaseOrder}/convert-to-bill', [PurchaseOrderController::class, 'convertToBill'])->name('purchase-order.convert-to-bill');
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

        // goods received notes
        Route::post('grn/{goodsReceivedNote}/approve', [GoodsReceivedNoteController::class, 'approve'])->name('grn.approve');
        Route::apiResource('grn', GoodsReceivedNoteController::class)->parameters(['grn' => 'goodsReceivedNote']);

        // delivery challans
        Route::post('delivery-challan/{deliveryChallan}/approve', [DeliveryChallanController::class, 'approve'])->name('delivery-challan.approve');
        Route::apiResource('delivery-challan', DeliveryChallanController::class)->parameters(['delivery-challan' => 'deliveryChallan']);

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

        // serial numbers
        Route::apiResource('serial-number', SerialNumberController::class)->only(['index', 'show']);

        // Nepal compliance — Phase 1
        Route::prefix('nepal')->as('nepal.')->group(function () {
            // Invoice PDF download
            Route::get('invoice/{invoice}/pdf', InvoicePdfController::class)->name('invoice.pdf');

            // IRD EBS settings & sync management
            Route::prefix('ird')->as('ird.')->controller(IrdSettingController::class)->group(function () {
                Route::get('settings', 'show')->name('settings');
                Route::put('settings', 'update')->name('settings.update');
                Route::post('invoice/{invoice}/retry-sync', 'retrySync')->name('retry-sync');
                Route::get('sync-summary', 'syncSummary')->name('sync-summary');
            });

            // VAT D3 Return
            Route::prefix('vat-d3')->as('vat-d3.')->controller(VatD3Controller::class)->group(function () {
                Route::get('summary', 'summary')->name('summary');
                Route::get('export-csv', 'exportCsv')->name('export-csv');
            });

            // TDS Challan & Certificate
            Route::prefix('tds')->as('tds.')->controller(TdsChallanController::class)->group(function () {
                Route::get('summary', 'summary')->name('summary');
                Route::get('challan-pdf', 'downloadChallan')->name('challan-pdf');
                Route::get('certificate-pdf', 'downloadCertificate')->name('certificate-pdf');
            });
        });

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

        // HR — Phase 1: Employee Foundation
        Route::prefix('hr')->as('hr.')->group(function () {
            Route::apiResource('department', DepartmentController::class);
            Route::apiResource('designation', DesignationController::class);
            Route::apiResource('employee', EmployeeController::class);

            // Phase 2: Attendance & Leave
            Route::apiResource('holiday', HolidayController::class);
            Route::apiResource('leave-type', LeaveTypeController::class);
            Route::get('attendance/monthly', [AttendanceController::class, 'monthly'])->name('attendance.monthly');
            Route::post('attendance/bulk', [AttendanceController::class, 'bulkStore'])->name('attendance.bulk');
            Route::apiResource('attendance', AttendanceController::class);
            Route::post('leave-application/{leaveApplication}/approve', [LeaveApplicationController::class, 'approve'])->name('leave-application.approve');
            Route::post('leave-application/{leaveApplication}/reject', [LeaveApplicationController::class, 'reject'])->name('leave-application.reject');
            Route::apiResource('leave-application', LeaveApplicationController::class);

            // Phase 3: Payroll
            Route::apiResource('salary-component', SalaryComponentController::class);
            Route::apiResource('salary-structure', SalaryStructureController::class);
            Route::post('payroll/{payrollRun}/process', [PayrollController::class, 'process'])->name('payroll.process');
            Route::post('payroll/{payrollRun}/confirm', [PayrollController::class, 'confirm'])->name('payroll.confirm');
            Route::apiResource('payroll', PayrollController::class)->except('update');
            Route::get('payslip', [PayslipController::class, 'index'])->name('payslip.index');
            Route::get('payslip/{payslip}', [PayslipController::class, 'show'])->name('payslip.show');

            // Phase 4: Reports
            Route::prefix('report')->as('report.')->group(function () {
                Route::get('payroll-summary', [HrReportController::class, 'payrollSummary'])->name('payroll-summary');
                Route::get('attendance-summary', [HrReportController::class, 'attendanceSummary'])->name('attendance-summary');
                Route::get('leave-balance', [HrReportController::class, 'leaveBalance'])->name('leave-balance');
                Route::get('tds-salary', [HrReportController::class, 'tdsSalary'])->name('tds-salary');
            });
        });
    });

    // ==================== Phase 3 — Inventory Enhancements ====================
    Route::middleware('checkRole')->group(function () {
        // Batches / Lot tracking
        Route::get('batch/expiry-alerts', [BatchController::class, 'expiryAlerts'])->name('batch.expiry-alerts');
        Route::get('batch/fefo', [BatchController::class, 'fefoList'])->name('batch.fefo');
        Route::apiResource('batch', BatchController::class)->except(['destroy']);

        // Bin management
        Route::apiResource('bin', BinController::class);

        // Bill of Materials
        Route::apiResource('bom', BomController::class);

        // Production Orders
        Route::post('production-order/{productionOrder}/start', [ProductionOrderController::class, 'start'])->name('production-order.start');
        Route::post('production-order/{productionOrder}/complete', [ProductionOrderController::class, 'complete'])->name('production-order.complete');
        Route::post('production-order/{productionOrder}/cancel', [ProductionOrderController::class, 'cancel'])->name('production-order.cancel');
        Route::apiResource('production-order', ProductionOrderController::class)->except(['update', 'destroy']);

        // ==================== Phase 5 — Finance & Banking ====================
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

        // ==================== Phase 6 — Multi-branch ====================
        Route::get('branch/{branch}/pl-report', [BranchController::class, 'plReport'])->name('branch.pl-report');
        Route::get('branch/consolidated-report', [BranchController::class, 'consolidatedReport'])->name('branch.consolidated-report');
        Route::apiResource('branch', BranchController::class);
    });

    // global settings
    Route::prefix('admin-setting')->as('admin-setting.')->controller(AdminSettingController::class)->group(function () {
        Route::get('fiscal-year', 'fiscalYears')->name('fiscal-year');
    });

    // enum
    Route::prefix('enum')->as('enum.')->controller(EnumController::class)->group(function () {});
});
