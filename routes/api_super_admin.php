<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SuperAdmin\AuthController;
use App\Http\Controllers\Api\SuperAdmin\CompanyController;
use App\Http\Controllers\Api\SuperAdmin\ProfileController;
use App\Http\Controllers\Api\SuperAdmin\SettingController;
use App\Http\Controllers\Api\SuperAdmin\DashboardController;
use App\Http\Controllers\Api\SuperAdmin\FiscalYearController;
use App\Http\Controllers\Api\SuperAdmin\CurrencyController;
use App\Http\Controllers\Api\SuperAdmin\TaxTemplateController;
use App\Http\Controllers\Api\SuperAdmin\ProvinceController;
use App\Http\Controllers\Api\SuperAdmin\DistrictController;
use App\Http\Controllers\Api\SuperAdmin\PalikaController;
use App\Http\Controllers\Api\SuperAdmin\WardController;

Route::controller(AuthController::class)->group(function () {
    Route::post('login', 'login')->name('login');
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

    // company
    Route::put('company/{company}/update-status', [CompanyController::class, 'updateStatus'])->name('company.update-status');
    Route::put('company/{company}/reset-password', [CompanyController::class, 'resetPassword'])->name('company.reset-password');
    Route::apiResource('company', CompanyController::class);
});
