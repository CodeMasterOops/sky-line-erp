<?php

use App\Http\Controllers\Api\Admin\Settings\BranchController;
use App\Http\Controllers\Api\Admin\Settings\PaymentModeController;
use App\Http\Controllers\Api\Admin\Settings\SettingController;
use App\Http\Controllers\Api\Admin\Settings\TaxController;
use Illuminate\Support\Facades\Route;

// company setting
Route::apiResource('setting', SettingController::class)->only('index', 'store');

// tax
Route::apiResource('tax', TaxController::class);

// payment mode
Route::apiResource('payment-mode', PaymentModeController::class);

// branch
Route::get('branch/{branch}/pl-report', [BranchController::class, 'plReport'])->name('branch.pl-report');
Route::get('branch/consolidated-report', [BranchController::class, 'consolidatedReport'])->name('branch.consolidated-report');
Route::apiResource('branch', BranchController::class);
