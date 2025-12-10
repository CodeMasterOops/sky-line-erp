<?php

use App\Http\Controllers\Api\SuperAdmin\AuthController;
use App\Http\Controllers\Api\SuperAdmin\ProfileController;
use App\Http\Controllers\Api\SuperAdmin\DashboardController;

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

    // enum
    Route::prefix('enum')->as('enum.')->controller(EnumController::class)->group(function () {});
});
