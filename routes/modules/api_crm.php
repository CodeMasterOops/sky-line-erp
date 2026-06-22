<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\Crm\TaskController;
use App\Http\Controllers\Api\Admin\Crm\LeadController;
use App\Http\Controllers\Api\Admin\Crm\FollowUpController;
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

// crm — follow-ups
Route::post('crm/follow-up/{followUp}/complete', [FollowUpController::class, 'complete'])->name('crm.follow-up.complete');
Route::get('crm/follow-up/due', [FollowUpController::class, 'due'])->name('crm.follow-up.due');
Route::apiResource('crm/follow-up', FollowUpController::class)
    ->parameters(['follow-up' => 'followUp'])
    ->only(['index', 'store', 'update', 'destroy']);

// crm — tasks
Route::post('crm/task/{task}/complete', [TaskController::class, 'complete'])->name('crm.task.complete');
Route::get('crm/task/mine', [TaskController::class, 'mine'])->name('crm.task.mine');
Route::apiResource('crm/task', TaskController::class)
    ->only(['index', 'store', 'update', 'destroy']);

// crm — customer timeline (CRM-native activity feed)
Route::get('crm/customer/{party}/timeline', [TimelineController::class, 'index'])->name('crm.customer.timeline');
