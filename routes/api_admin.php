<?php

use App\Http\Controllers\Api\Admin\BrandController;
use App\Http\Controllers\Api\Admin\UnitController;
use App\Http\Controllers\Api\Admin\WarehouseController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\EnumController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\ProfileController;
use App\Http\Controllers\Api\Admin\SettingController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\PermissionController;
use App\Http\Controllers\Api\Admin\AdminNotificationController;

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

        //unit
        Route::apiResource('unit', UnitController::class);

        //brand
        Route::apiResource('brand', BrandController::class);

        //warehouse
        Route::apiResource('warehouse', WarehouseController::class);
    });

    // enum
    Route::prefix('enum')->as('enum.')->controller(EnumController::class)->group(function () {});
});
