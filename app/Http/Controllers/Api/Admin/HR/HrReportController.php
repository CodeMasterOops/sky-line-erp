<?php

namespace App\Http\Controllers\Api\Admin\HR;

use App\Models\Employee;
use App\Models\Attendance;
use App\Models\FiscalYear;
use App\Models\LeaveApplication;
use App\Models\PayrollRun;
use App\Enums\AttendanceStatusEnum;
use App\Enums\LeaveStatusEnum;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;

class HrReportController extends Controller
{
    /**
     * @Permissions("list_payroll", group="hr", desc="Payroll Summary Report")
     */
    public function payrollSummary(Request $request)
    {
        $request->validate([
            'fiscal_year_id' => ['required', 'integer', 'exists:fiscal_years,id'],
        ]);

        $fiscalYear = FiscalYear::findOrFail($request->fiscal_year_id);

        $runs = PayrollRun::where('fiscal_year_id', $fiscalYear->id)
            ->with(['payslips.employee'])
            ->orderBy('month')
            ->get();

        return response()->json([
            'fiscal_year' => [
                'id' => $fiscalYear->id,
                'year_name' => $fiscalYear->year_name,
                'year_code' => $fiscalYear->year_code,
            ],
            'data' => $runs->map(fn ($run) => [
                'month' => $run->month,
                'month_label' => date('F', mktime(0, 0, 0, $run->month, 1)),
                'status' => $run->status?->label(),
                'employee_count' => $run->payslips->count(),
                'total_gross' => $run->total_gross,
                'total_deductions' => $run->total_deductions,
                'total_net' => $run->total_net,
            ]),
        ]);
    }

    /**
     * @Permissions("list_attendance", group="hr", desc="Attendance Summary Report")
     */
    public function attendanceSummary(Request $request)
    {
        $request->validate([
            'fiscal_year_id' => ['required', 'integer', 'exists:fiscal_years,id'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $fiscalYear = FiscalYear::findOrFail($request->fiscal_year_id);
        $year = $fiscalYear->start_date->year;
        $month = $request->month;

        $employees = Employee::with(['attendances' => fn ($q) => $q
            ->whereMonth('date', $month)
            ->whereYear('date', $year)])->get();

        $data = $employees->map(fn ($emp) => [
            'employee_code' => $emp->employee_code,
            'full_name' => $emp->full_name,
            'present' => $emp->attendances->where('status', AttendanceStatusEnum::PRESENT)->count(),
            'absent' => $emp->attendances->where('status', AttendanceStatusEnum::ABSENT)->count(),
            'half_day' => $emp->attendances->where('status', AttendanceStatusEnum::HALF_DAY)->count(),
            'late' => $emp->attendances->where('status', AttendanceStatusEnum::LATE)->count(),
            'on_leave' => $emp->attendances->where('status', AttendanceStatusEnum::ON_LEAVE)->count(),
        ]);

        return response()->json(['data' => $data]);
    }

    /**
     * @Permissions("list_leave_application", group="hr", desc="Leave Balance Report")
     */
    public function leaveBalance(Request $request)
    {
        $request->validate([
            'fiscal_year_id' => ['required', 'integer', 'exists:fiscal_years,id'],
        ]);

        $fiscalYear = FiscalYear::findOrFail($request->fiscal_year_id);

        $employees = Employee::with(['leaveApplications' => fn ($q) => $q
            ->where('status', LeaveStatusEnum::APPROVED)
            ->whereBetween('from_date', [$fiscalYear->start_date, $fiscalYear->end_date])
            ->with('leaveType')])->get();

        $data = $employees->map(function ($emp) {
            $usedByType = $emp->leaveApplications->groupBy('leave_type_id')->map(fn ($g) => [
                'leave_type' => $g->first()->leaveType?->name,
                'days_allowed' => $g->first()->leaveType?->days_allowed,
                'days_used' => $g->sum('days'),
                'days_remaining' => ($g->first()->leaveType?->days_allowed ?? 0) - $g->sum('days'),
            ])->values();

            return [
                'employee_code' => $emp->employee_code,
                'full_name' => $emp->full_name,
                'leave_summary' => $usedByType,
            ];
        });

        return response()->json(['data' => $data]);
    }
}
