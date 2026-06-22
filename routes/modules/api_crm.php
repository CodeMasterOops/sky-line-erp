<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\Crm\TimelineController;

// crm — customer timeline (CRM-native activity feed)
Route::get('crm/customer/{party}/timeline', [TimelineController::class, 'index'])->name('crm.customer.timeline');
