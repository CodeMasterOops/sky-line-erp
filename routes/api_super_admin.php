<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SuperAdmin\AuthController;
use App\Http\Controllers\Api\SuperAdmin\LeadController;
use App\Http\Controllers\Api\SuperAdmin\PlanController;
use App\Http\Controllers\Api\SuperAdmin\ModuleController;
use App\Http\Controllers\Api\SuperAdmin\CompanyModuleController;
use App\Http\Controllers\Api\SuperAdmin\CompanyCategoryController;
use App\Http\Controllers\Api\SuperAdmin\WardController;
use App\Http\Controllers\Api\SuperAdmin\PalikaController;
use App\Http\Controllers\Api\SuperAdmin\CompanyController;
use App\Http\Controllers\Api\SuperAdmin\ProfileController;
use App\Http\Controllers\Api\SuperAdmin\SettingController;
use App\Http\Controllers\Api\SuperAdmin\SupportController;
use App\Http\Controllers\Api\SuperAdmin\CurrencyController;
use App\Http\Controllers\Api\SuperAdmin\DistrictController;
use App\Http\Controllers\Api\SuperAdmin\ProvinceController;
use App\Http\Controllers\Api\SuperAdmin\DashboardController;
use App\Http\Controllers\Api\SuperAdmin\FiscalYearController;
use App\Http\Controllers\Api\SuperAdmin\TaxTemplateController;
use App\Http\Controllers\Api\SuperAdmin\SubscriptionController;
use App\Http\Controllers\Api\SuperAdmin\CompanyBranchController;
use App\Http\Controllers\Api\SuperAdmin\CompanyProvisionLogController;

Route::controller(AuthController::class)->group(function () {
    Route::post('login', 'login')->middleware('throttle:auth')->name('login');
    Route::post('logout', 'logout')->middleware('auth:super_admin');
});

Route::middleware('auth:super_admin')->group(function () {
    // profile
    Route::prefix('profile')->as('profile')->controller(ProfileController::class)->group(function () {
        Route::get('/', 'profile')->name('index');
        Route::post('update', 'updateProfile')->name('update');
        Route::put('change-password', 'changePassword')->name('changePassword');
    });

    // dashboard
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::apiResource('setting', SettingController::class)->only('index', 'store');

    // fiscal year
    Route::post('fiscal-year/{fiscalYear}/set-current', [FiscalYearController::class, 'setCurrent'])->name('fiscal-year.set-current');
    Route::apiResource('fiscal-year', FiscalYearController::class);

    // currencies (global — managed by SaaS owner)
    Route::apiResource('currency', CurrencyController::class);

    // tax templates (seed defaults for new companies)
    Route::apiResource('tax-template', TaxTemplateController::class)->parameters(['tax-template' => 'taxTemplate']);

    // address reference (provinces, districts, palikas, wards)
    Route::apiResource('province', ProvinceController::class);
    Route::apiResource('district', DistrictController::class);
    Route::apiResource('palika', PalikaController::class);
    Route::apiResource('ward', WardController::class);

    // module catalogue (config-defined; the same for every company)
    Route::get('module', ModuleController::class)->name('module.index');

    // company categories (industry defaults)
    Route::apiResource('company-category', CompanyCategoryController::class)
        ->parameters(['company-category' => 'companyCategory']);

    // per-company module control + audit trail
    Route::get('company/{company}/module', [CompanyModuleController::class, 'index'])->name('company.module.index');
    Route::put('company/{company}/module', [CompanyModuleController::class, 'update'])->name('company.module.update');
    Route::get('company/{company}/module/event', [CompanyModuleController::class, 'events'])->name('company.module.event');
    Route::put('company/{company}/category', [CompanyModuleController::class, 'applyCategory'])->name('company.category.apply');
    Route::post('company/{company}/module/reset', [CompanyModuleController::class, 'resetToCategory'])->name('company.module.reset');

    // company
    Route::post('company/{company}/login', [CompanyController::class, 'companyLogin'])->name('company.login');
    Route::put('company/{company}/update-status', [CompanyController::class, 'updateStatus'])->name('company.update-status');
    Route::put('company/{company}/reset-password', [CompanyController::class, 'resetPassword'])->name('company.reset-password');
    Route::get('company/{company}/provision-log', [CompanyProvisionLogController::class, 'index'])->name('company.provision-log.index');
    Route::post('company/{company}/reprovision', [CompanyProvisionLogController::class, 'reprovision'])->name('company.reprovision');
    Route::apiResource('company.branch', CompanyBranchController::class)->only('index', 'store', 'update', 'destroy');
    Route::apiResource('company', CompanyController::class);

    // plans (packages)
    Route::apiResource('plan', PlanController::class);

    // subscriptions
    Route::put('subscription/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');
    Route::put('subscription/{subscription}/renew', [SubscriptionController::class, 'renew'])->name('subscription.renew');
    Route::apiResource('subscription', SubscriptionController::class);

    // leads (public inquiry submissions)
    Route::apiResource('lead', LeadController::class)->only('index', 'show', 'update', 'destroy');

    // support settings
    Route::get('support', [SupportController::class, 'index'])->name('support.index');
    Route::post('support', [SupportController::class, 'store'])->name('support.store');
});
