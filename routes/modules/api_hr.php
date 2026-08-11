<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\HR\HolidayController;
use App\Http\Controllers\Api\Admin\HR\PayrollController;
use App\Http\Controllers\Api\Admin\HR\PayslipController;
use App\Http\Controllers\Api\Admin\HR\EmployeeController;
use App\Http\Controllers\Api\Admin\HR\HrReportController;
use App\Http\Controllers\Api\Admin\HR\LeaveTypeController;
use App\Http\Controllers\Api\Admin\HR\AttendanceController;
use App\Http\Controllers\Api\Admin\HR\DepartmentController;
use App\Http\Controllers\Api\Admin\HR\DesignationController;
use App\Http\Controllers\Api\Admin\HR\WorkScheduleController;
use App\Http\Controllers\Api\Admin\HR\SalaryComponentController;
use App\Http\Controllers\Api\Admin\HR\SalaryStructureController;
use App\Http\Controllers\Api\Admin\HR\LeaveApplicationController;

// HR — Phase 1: Employee Foundation
Route::prefix('hr')->as('hr.')->group(function () {
    Route::get('department/next-code', [DepartmentController::class, 'nextCode'])->name('department.next-code');
    Route::apiResource('department', DepartmentController::class);
    Route::apiResource('designation', DesignationController::class);
    Route::get('employee/next-code', [EmployeeController::class, 'nextCode'])->name('employee.next-code');
    Route::get('employee/{employee}/salary-ledger', [EmployeeController::class, 'salaryLedger'])->name('employee.salary-ledger');
    Route::apiResource('employee', EmployeeController::class);

    // Phase 2: Attendance & Leave
    Route::get('work-schedule', [WorkScheduleController::class, 'show'])->name('work-schedule.show');
    Route::put('work-schedule', [WorkScheduleController::class, 'update'])->name('work-schedule.update');
    Route::apiResource('holiday', HolidayController::class);
    Route::apiResource('leave-type', LeaveTypeController::class);
    Route::get('attendance/monthly', [AttendanceController::class, 'monthly'])->name('attendance.monthly');
    Route::post('attendance/bulk', [AttendanceController::class, 'bulkStore'])->name('attendance.bulk');
    Route::apiResource('attendance', AttendanceController::class);
    Route::post('leave-application/{leaveApplication}/approve', [LeaveApplicationController::class, 'approve'])->name('leave-application.approve');
    Route::post('leave-application/{leaveApplication}/reject', [LeaveApplicationController::class, 'reject'])->name('leave-application.reject');
    Route::apiResource('leave-application', LeaveApplicationController::class);

    // Phase 3: Payroll — `payroll` sub-module, gated inline inside `hr`
    Route::middleware('module:payroll')->group(function () {
        Route::apiResource('salary-component', SalaryComponentController::class);
        Route::apiResource('salary-structure', SalaryStructureController::class);
        Route::get('payroll/current-period', [PayrollController::class, 'currentPeriod'])->name('payroll.current-period');
        Route::post('payroll/{payrollRun}/process', [PayrollController::class, 'process'])->name('payroll.process');
        Route::post('payroll/{payrollRun}/approve', [PayrollController::class, 'approve'])->name('payroll.approve');
        Route::post('payroll/{payrollRun}/confirm', [PayrollController::class, 'confirm'])->name('payroll.confirm');
        Route::apiResource('payroll', PayrollController::class)->except('update')->parameters(['payroll' => 'payrollRun']);
        Route::get('payslip', [PayslipController::class, 'index'])->name('payslip.index');
        Route::get('payslip/{payslip}', [PayslipController::class, 'show'])->name('payslip.show');
    });

    // Phase 4: Reports
    Route::prefix('report')->as('report.')->group(function () {
        Route::get('payroll-summary', [HrReportController::class, 'payrollSummary'])->name('payroll-summary');
        Route::get('attendance-summary', [HrReportController::class, 'attendanceSummary'])->name('attendance-summary');
        Route::get('leave-balance', [HrReportController::class, 'leaveBalance'])->name('leave-balance');
        Route::get('tds-salary', [HrReportController::class, 'tdsSalary'])->name('tds-salary');
    });
});
