<?php

use App\Http\Controllers\Api\Admin\EnumController;
use Illuminate\Support\Facades\Route;

Route::prefix('enum')->as('enum.')->controller(EnumController::class)->group(function () {
    Route::get('journal-type', 'journalTypes')->name('journal-type');
    Route::get('tds-categories', 'tdsCategories')->name('tds-categories');
    Route::get('party-types', 'partyTypes')->name('party-types');
    Route::get('crm-lead-statuses', 'crmLeadStatuses')->name('crm-lead-statuses');
});
