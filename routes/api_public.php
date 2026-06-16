<?php

use App\Http\Controllers\Api\Public\LeadController;
use App\Http\Controllers\Api\Public\AppSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('settings', [AppSettingsController::class, 'index'])->name('settings');
Route::post('leads', [LeadController::class, 'store'])->middleware('throttle:public.leads')->name('leads.store');
