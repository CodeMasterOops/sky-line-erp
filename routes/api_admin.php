<?php

use App\Http\Middleware\SetTenantContext;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\PosController;
use App\Http\Controllers\Api\Admin\QzTrayController;
use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\OnboardingController;
use App\Http\Controllers\Api\Admin\EnumController;
use App\Http\Controllers\Api\Admin\PartyController;
use App\Http\Controllers\Api\Admin\ProfileController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\Nepal\VatD3Controller;
use App\Http\Controllers\Api\Admin\Nepal\VatD4Controller;
use App\Http\Controllers\Api\Admin\AddressReferenceController;
use App\Http\Controllers\Api\Admin\Nepal\InvoicePdfController;
use App\Http\Controllers\Api\Admin\Nepal\IrdSettingController;
use App\Http\Controllers\Api\Admin\Nepal\TdsChallanController;
use App\Http\Controllers\Api\Admin\Nepal\TdsReceivableController;
use App\Http\Controllers\Api\Admin\AdminNotificationController;
use App\Http\Controllers\Api\Admin\BillingController;
use App\Http\Controllers\Api\Admin\Settings\AdminSettingController;
use App\Http\Controllers\Api\Admin\SupportController;

// Phase 3 — Inventory Enhancements
// Phase 5 — Finance & Banking
// Phase 6 — Multi-branch

Route::controller(AuthController::class)->group(function () {
    Route::post('login', 'login')->middleware('throttle:auth')->name('login');
    Route::post('register', 'register')->middleware('throttle:auth')->name('register');
    Route::post('logout', 'logout')->middleware('auth:admin');
});

Route::middleware(['auth:admin', SetTenantContext::class])->group(function () {
    Route::prefix('onboarding')->as('onboarding.')->group(function () {
        Route::put('company', [OnboardingController::class, 'updateCompany'])->name('company');
        Route::post('complete', [OnboardingController::class, 'complete'])->name('complete');
    });

    Route::middleware('checkRole')->group(function () {
        // profile
        Route::prefix('profile')->as('profile')->controller(ProfileController::class)->group(function () {
            Route::get('/', 'profile')->name('index');
            Route::get('permissions', 'permissions')->name('permissions');
            Route::post('update', 'updateProfile')->name('update');
            Route::put('date-mode', 'updateDateMode')->name('dateMode');
            Route::put('pinned-links', 'updatePinnedLinks')->name('pinnedLinks');
            Route::put('change-password', 'changePassword')->name('changePassword');
        });

        // dashboard
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        // support info (read-only, set by super admin)
        Route::get('support', [SupportController::class, 'index'])->name('support.index');

        // billing (read-only subscription info)
        Route::get('billing/subscription', [BillingController::class, 'subscription'])->name('billing.subscription');
        Route::get('billing/plans', [BillingController::class, 'plans'])->name('billing.plans');

        // address reference (read-only, for company settings & forms)
        Route::prefix('location-reference')->as('location-reference.')->controller(AddressReferenceController::class)->group(function () {
            Route::get('province', 'provinces')->name('province.index');
            Route::get('district', 'districts')->name('district.index');
            Route::get('palika', 'palikas')->name('palika.index');
            Route::get('ward', 'wards')->name('ward.index');
        });

        // user management module
        require __DIR__.'/modules/api_user_management.php';

        // notifications
        Route::prefix('notification')->as('notification.')->controller(AdminNotificationController::class)->group(function () {
            Route::get('all', 'allNotifications')->name('all');
            Route::get('unread', 'unreadNotifications')->name('unread');
            Route::post('mark-as-read/{id?}', 'markAsRead')->name('mark-as-read');
        });

        // settings module
        require __DIR__.'/modules/api_settings.php';

        // data transfer (import / export)
        require __DIR__.'/modules/api_data_transfer.php';

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

        // crm module
        require __DIR__.'/modules/api_crm.php';

        // parties
        Route::get('party/next-code', [PartyController::class, 'nextCode'])->name('party.next-code');
        Route::apiResource('party', PartyController::class);

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
                Route::get('reconciliation', 'reconciliation')->name('reconciliation');
                Route::post('resync-all', 'resyncAll')->name('resync-all');
            });

            // VAT D3 Sales Register (Bikri Kitab)
            Route::prefix('vat-d3')->as('vat-d3.')->controller(VatD3Controller::class)->group(function () {
                Route::get('summary', 'summary')->name('summary');
                Route::get('export-csv', 'exportCsv')->name('export-csv');
            });

            // VAT D4 Purchase Register (Kharid Kitab)
            Route::prefix('vat-d4')->as('vat-d4.')->controller(VatD4Controller::class)->group(function () {
                Route::get('export-csv', 'exportCsv')->name('export-csv');
            });

            // TDS Challan & Certificate
            Route::prefix('tds')->as('tds.')->controller(TdsChallanController::class)->group(function () {
                Route::get('summary', 'summary')->name('summary');
                Route::get('return-annexure', 'returnAnnexure')->name('return-annexure');
                Route::get('challan-pdf', 'downloadChallan')->name('challan-pdf');
                Route::get('certificate-pdf', 'downloadCertificate')->name('certificate-pdf');
            });

            // TDS Receivables (customer-withheld TDS certificate tracking & settlement)
            Route::prefix('tds-receivables')->as('tds-receivables.')->controller(TdsReceivableController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{tdsReceivable}', 'show')->name('show');
                Route::post('{tdsReceivable}/approve', 'approve')->name('approve');
                Route::post('{tdsReceivable}/settle', 'settle')->name('settle');
            });
        });

    });

    // POS
    Route::prefix('pos')->as('pos.')->middleware('checkRole')->controller(PosController::class)->group(function () {
        Route::get('categories', 'categories')->name('categories');
        Route::get('products', 'products')->name('products');
        Route::get('variants/{productVariant}/warehouses', 'variantWarehouses')->name('variants.warehouses');
        Route::get('customers', 'customers')->name('customers');
        Route::get('warehouses', 'warehouses')->name('warehouses');
        Route::get('today-summary', 'todaySummary')->name('today-summary');
        Route::get('transactions', 'transactions')->name('transactions');
        Route::get('last-receipt', 'lastReceipt')->name('last-receipt');
        Route::get('receipt/{invoice}', 'receiptData')->name('receipt');
        // Till management
        Route::get('till/current', 'currentSession')->name('till.current');
        Route::post('till/open', 'openTill')->name('till.open');
        Route::post('till/close', 'closeTill')->name('till.close');
        Route::post('till/cash-movement', 'cashMovement')->name('till.cash-movement');
        Route::get('till/summary', 'tillSummary')->name('till.summary');
        Route::post('return', 'processReturn')->name('return');
        Route::post('checkout', 'checkout')->name('checkout');
        Route::post('hold', 'holdOrder')->name('hold');
        Route::get('held-orders', 'heldOrders')->name('held-orders');
        Route::delete('held-orders/{posHeldOrder}', 'deleteHeldOrder')->name('held-orders.destroy');

        // QZ Tray thermal printing — certificate + request signing.
        Route::get('qz/certificate', [QzTrayController::class, 'certificate'])->name('qz.certificate');
        Route::post('qz/sign', [QzTrayController::class, 'sign'])->name('qz.sign');
    });

    // global settings
    Route::prefix('admin-setting')->as('admin-setting.')->controller(AdminSettingController::class)->group(function () {
        Route::get('fiscal-year', 'fiscalYears')->name('fiscal-year');
        Route::get('current-fiscal-year', 'currentFiscalYear')->name('current-fiscal-year');
    });

    // enum
    Route::prefix('enum')->as('enum.')->controller(EnumController::class)->group(function () {
        Route::get('journal-type', 'journalTypes')->name('journal-type');
        Route::get('tds-categories', 'tdsCategories')->name('tds-categories');
    });
});
