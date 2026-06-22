<?php

use App\Http\Controllers\Api\Admin\EnumController;
use Illuminate\Support\Facades\Route;

Route::prefix('enum')->as('enum.')->controller(EnumController::class)->group(function () {
    Route::get('journal-type', 'journalTypes')->name('journal-type');
    Route::get('tds-categories', 'tdsCategories')->name('tds-categories');
    Route::get('party-types', 'partyTypes')->name('party-types');
    Route::get('crm-lead-statuses', 'crmLeadStatuses')->name('crm-lead-statuses');
    Route::get('task-statuses', 'taskStatuses')->name('task-statuses');
    Route::get('task-priorities', 'taskPriorities')->name('task-priorities');
    Route::get('follow-up-channels', 'followUpChannels')->name('follow-up-channels');
    Route::get('follow-up-statuses', 'followUpStatuses')->name('follow-up-statuses');
});
