<?php

use App\Http\Controllers\Api\Admin\Settings\BranchController;
use App\Http\Controllers\Api\Admin\Settings\BranchUserController;
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

// branch — accessible branches for the current user (no admin permission needed)
Route::get('branch/my-branches', [BranchController::class, 'myBranches'])->name('branch.my-branches');

// branch — admin management routes
Route::get('branch/next-code', [BranchController::class, 'nextCode'])->name('branch.next-code');
Route::get('branch/{branch}/pl-report', [BranchController::class, 'plReport'])->name('branch.pl-report');
Route::get('branch/consolidated-report', [BranchController::class, 'consolidatedReport'])->name('branch.consolidated-report');
Route::apiResource('branch', BranchController::class);

// branch user assignments — admin only
Route::prefix('branch/{branch}/users')->as('branch.users.')->group(function () {
    Route::get('/', [BranchUserController::class, 'index'])->name('index');
    Route::post('/', [BranchUserController::class, 'store'])->name('store');
    Route::patch('/{user}', [BranchUserController::class, 'update'])->name('update');
    Route::delete('/{user}', [BranchUserController::class, 'destroy'])->name('destroy');
});
