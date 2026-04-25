<?php

use App\Http\Controllers\Api\Admin\EnumController;
use Illuminate\Support\Facades\Route;

Route::prefix('enum')->as('enum.')->controller(EnumController::class)->group(function () {
    Route::get('journal-type', 'journalTypes')->name('journal-type');
});
