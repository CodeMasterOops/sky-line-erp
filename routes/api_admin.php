<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\PosController;
use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\EnumController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\PartyController;
use App\Http\Controllers\Api\Admin\ProfileController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\PermissionController;
use App\Http\Controllers\Api\Admin\Nepal\VatD3Controller;
use App\Http\Controllers\Api\Admin\AdminSettingController;
use App\Http\Controllers\Api\Admin\SerialNumberController;
use App\Http\Controllers\Api\Admin\Settings\TaxController;
use App\Http\Controllers\Api\Admin\Settings\BranchController;
use App\Http\Controllers\Api\Admin\AddressReferenceController;
use App\Http\Controllers\Api\Admin\Nepal\InvoicePdfController;
use App\Http\Controllers\Api\Admin\Nepal\IrdSettingController;
use App\Http\Controllers\Api\Admin\Nepal\TdsChallanController;
use App\Http\Controllers\Api\Admin\Settings\SettingController;
use App\Http\Controllers\Api\Admin\AdminNotificationController;
use App\Http\Controllers\Api\Admin\Settings\PaymentModeController;

// Phase 3 — Inventory Enhancements
// Phase 5 — Finance & Banking
// Phase 6 — Multi-branch

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

        // address reference (read-only, for company settings & forms)
        Route::prefix('location-reference')->as('location-reference.')->controller(AddressReferenceController::class)->group(function () {
            Route::get('province', 'provinces')->name('province.index');
            Route::get('district', 'districts')->name('district.index');
            Route::get('palika', 'palikas')->name('palika.index');
            Route::get('ward', 'wards')->name('ward.index');
        });

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

        // tax
        Route::apiResource('tax', TaxController::class);

        // payment mode
        Route::apiResource('payment-mode', PaymentModeController::class);

        // inventory module
        require __DIR__.'/modules/api_inventory.php';

        // accounting module
        require __DIR__.'/modules/api_accounting.php';

        // sales module
        require __DIR__.'/modules/api_sales.php';

        // purchase module
        require __DIR__.'/modules/api_purchase.php';

        // hr module
        require __DIR__.'/modules/api_hr.php';

        // parties
        Route::apiResource('party', PartyController::class);

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

    });

    Route::middleware('checkRole')->group(function () {
        // ==================== Phase 6 — Multi-branch ====================
        Route::get('branch/{branch}/pl-report', [BranchController::class, 'plReport'])->name('branch.pl-report');
        Route::get('branch/consolidated-report', [BranchController::class, 'consolidatedReport'])->name('branch.consolidated-report');
        Route::apiResource('branch', BranchController::class);
    });

    // POS
    Route::prefix('pos')->as('pos.')->middleware('checkRole')->controller(PosController::class)->group(function () {
        Route::get('products', 'products')->name('products');
        Route::get('customers', 'customers')->name('customers');
        Route::get('warehouses', 'warehouses')->name('warehouses');
        Route::get('today-summary', 'todaySummary')->name('today-summary');
        Route::post('checkout', 'checkout')->name('checkout');
        Route::post('hold', 'holdOrder')->name('hold');
        Route::get('held-orders', 'heldOrders')->name('held-orders');
        Route::delete('held-orders/{posHeldOrder}', 'deleteHeldOrder')->name('held-orders.destroy');
    });

    // global settings
    Route::prefix('admin-setting')->as('admin-setting.')->controller(AdminSettingController::class)->group(function () {
        Route::get('fiscal-year', 'fiscalYears')->name('fiscal-year');
        Route::get('current-fiscal-year', 'currentFiscalYear')->name('current-fiscal-year');
    });

    // enum
    Route::prefix('enum')->as('enum.')->controller(EnumController::class)->group(function () {});
});
