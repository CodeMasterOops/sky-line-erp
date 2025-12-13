<?php

use App\Http\Controllers\Api\SuperAdmin\AuthController;
use App\Http\Controllers\Api\SuperAdmin\CompanyController;
use App\Http\Controllers\Api\SuperAdmin\ProfileController;
use App\Http\Controllers\Api\SuperAdmin\SettingController;
use App\Http\Controllers\Api\SuperAdmin\DashboardController;
use App\Http\Controllers\Api\SuperAdmin\FiscalYearController;

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
    Route::apiResource('fiscal-year', FiscalYearController::class);

    // company
    Route::put('company/{company}/update-status', [CompanyController::class, 'updateStatus'])->name('company.update-status');
    Route::put('company/{company}/reset-password', [CompanyController::class, 'resetPassword'])->name('company.reset-password');
    Route::apiResource('company', CompanyController::class);
});
