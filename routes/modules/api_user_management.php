<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\UserManagement\RoleController;
use App\Http\Controllers\Api\Admin\UserManagement\UserController;
use App\Http\Controllers\Api\Admin\UserManagement\PermissionController;

// permissions
Route::get('permission', PermissionController::class)->name('permissions');

// roles
Route::apiResource('role', RoleController::class);

// users
Route::put('user/{user}/update-status', [UserController::class, 'updateStatus'])->name('user.update-status');
Route::apiResource('user', UserController::class);
