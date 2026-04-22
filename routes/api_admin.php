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

        // accounting reports
        Route::prefix('account-report')->as('account-report.')->controller(AccountReportController::class)->group(function () {
            Route::get('trial-balance', 'trialBalance')->name('trial-balance');
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
            });
        });
    });

    // global settings
    Route::prefix('admin-setting')->as('admin-setting.')->controller(AdminSettingController::class)->group(function () {
        Route::get('fiscal-year', 'fiscalYears')->name('fiscal-year');
    });

    // enum
    Route::prefix('enum')->as('enum.')->controller(EnumController::class)->group(function () {});
});
