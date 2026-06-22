<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\Crm\LeadController;
use App\Http\Controllers\Api\Admin\Crm\TimelineController;
use App\Http\Controllers\Api\Admin\Crm\ContactPersonController;

// crm — leads (Party of type=lead, with profile)
Route::get('crm/lead/next-code', [LeadController::class, 'nextCode'])->name('crm.lead.next-code');
Route::post('crm/lead/{party}/convert', [LeadController::class, 'convert'])->name('crm.lead.convert');
Route::post('crm/lead/{party}/assign', [LeadController::class, 'assign'])->name('crm.lead.assign');
Route::patch('crm/lead/{party}/status', [LeadController::class, 'updateStatus'])->name('crm.lead.status');
Route::apiResource('crm/lead', LeadController::class)->parameters(['lead' => 'party']);

// crm — contact persons
Route::apiResource('crm/contact-person', ContactPersonController::class)
    ->parameters(['contact-person' => 'contactPerson'])
    ->only(['index', 'store', 'update', 'destroy']);

// crm — customer timeline (CRM-native activity feed)
Route::get('crm/customer/{party}/timeline', [TimelineController::class, 'index'])->name('crm.customer.timeline');
