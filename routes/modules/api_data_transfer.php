<?php

use App\Http\Controllers\Api\Admin\DataTransfer\DataTransferController;
use Illuminate\Support\Facades\Route;

Route::prefix('data-transfers')->as('data-transfers.')->controller(DataTransferController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('templates/product', 'productTemplate')->name('templates.product');
    Route::get('templates/warehouse', 'warehouseTemplate')->name('templates.warehouse');
    Route::get('templates/party', 'partyTemplate')->name('templates.party');
    Route::post('imports', 'storeImport')->name('imports.store');
    Route::post('exports', 'storeExport')->name('exports.store');
    Route::get('schedules', 'schedules')->name('schedules.index');
    Route::post('schedules', 'storeSchedule')->name('schedules.store');
    Route::get('{uuid}', 'show')->name('show');
    Route::get('{uuid}/rows', 'previewRows')->name('rows');
    Route::put('{uuid}/mapping', 'updateMapping')->name('mapping');
    Route::post('{uuid}/validate', 'validateJob')->name('validate');
    Route::post('{uuid}/commit', 'commit')->name('commit');
    Route::post('{uuid}/cancel', 'cancel')->name('cancel');
    Route::post('{uuid}/rollback', 'rollback')->name('rollback');
    Route::get('{uuid}/download', 'download')->name('download');
    Route::get('{uuid}/errors', 'downloadErrors')->name('errors');
});
