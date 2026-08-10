<?php

use App\Http\Controllers\Api\Admin\Gym\GymDashboardController;
use App\Http\Controllers\Api\Admin\Gym\GymReportController;
use App\Http\Controllers\Api\Admin\Gym\MemberCheckInController;
use App\Http\Controllers\Api\Admin\Gym\MemberController;
use App\Http\Controllers\Api\Admin\Gym\MembershipController;
use App\Http\Controllers\Api\Admin\Gym\MembershipPlanController;
use Illuminate\Support\Facades\Route;

// gym — members (a Party of type customer plus a gym profile)
Route::get('gym/member/next-code', [MemberController::class, 'nextCode'])->name('gym.member.next-code');
Route::post('gym/member/{member}/photo', [MemberController::class, 'updatePhoto'])->name('gym.member.photo');
Route::apiResource('gym/member', MemberController::class)->parameters(['member' => 'member']);

// gym — membership plans (Monthly / Quarterly / Half-Yearly / Yearly / custom)
Route::put('gym/membership-plan/{membershipPlan}/toggle-active', [MembershipPlanController::class, 'toggleActive'])
    ->name('gym.membership-plan.toggle-active');
Route::apiResource('gym/membership-plan', MembershipPlanController::class)
    ->parameters(['membership-plan' => 'membershipPlan']);

// gym — memberships (one row per term; renewals chain rather than overwrite)
Route::get('gym/membership/expiring', [MembershipController::class, 'expiring'])->name('gym.membership.expiring');
Route::post('gym/membership/{membership}/renew', [MembershipController::class, 'renew'])->name('gym.membership.renew');
Route::post('gym/membership/{membership}/cancel', [MembershipController::class, 'cancel'])->name('gym.membership.cancel');
Route::post('gym/membership/{membership}/freeze', [MembershipController::class, 'freeze'])->name('gym.membership.freeze');
Route::post('gym/membership/{membership}/resume', [MembershipController::class, 'resume'])->name('gym.membership.resume');
Route::get('gym/member/{member}/membership', [MembershipController::class, 'forMember'])->name('gym.member.membership');
Route::apiResource('gym/membership', MembershipController::class)
    ->only(['index', 'store', 'show'])
    ->parameters(['membership' => 'membership']);

// gym — dashboard
Route::get('gym/dashboard', GymDashboardController::class)->name('gym.dashboard');

// gym — check-ins (front-desk visit log)
Route::post('gym/check-in/lookup', [MemberCheckInController::class, 'lookup'])->name('gym.check-in.lookup');
Route::post('gym/check-in/{checkIn}/check-out', [MemberCheckInController::class, 'checkOut'])->name('gym.check-in.check-out');
Route::get('gym/check-in', [MemberCheckInController::class, 'index'])->name('gym.check-in.index');
Route::post('gym/check-in', [MemberCheckInController::class, 'store'])->name('gym.check-in.store');

// gym — reports
Route::prefix('gym/report')->as('gym.report.')->controller(GymReportController::class)->group(function () {
    Route::get('membership-summary', 'membershipSummary')->name('membership-summary');
    Route::get('renewals', 'renewals')->name('renewals');
    Route::get('revenue-by-plan', 'revenueByPlan')->name('revenue-by-plan');
    Route::get('attendance', 'attendance')->name('attendance');
});
