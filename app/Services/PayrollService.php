<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\FiscalYear;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PayslipItem;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Enums\AttendanceStatusEnum;
use App\Enums\SalaryComponentTypeEnum;
use Carbon\Carbon;

class PayrollService
{
    public function calculate(PayrollRun $payrollRun): PayrollRun
    {
        $payrollRun->loadMissing('fiscalYear');

        $month = $payrollRun->month;
        $companyId = $payrollRun->company_id;

        $fiscalYear = $payrollRun->fiscalYear ?? FiscalYear::findOrFail($payrollRun->fiscal_year_id);
        $year = $fiscalYear->start_date->year;

        $workingDays = $this->getWorkingDays($month, $year, $companyId);

        $employees = Employee::where('company_id', $companyId)
            ->where('status', 'active')
            ->with(['salaryStructures' => fn ($q) => $q->where('is_active', true)->with('items.salaryComponent')])
            ->get();

        $payrollRun->payslips()->delete();

        $totalGross = 0;
        $totalDeductions = 0;

        foreach ($employees as $employee) {
            $structure = $employee->salaryStructures->first();
            if (! $structure) {
                continue;
            }

            $presentDays = Attendance::where('employee_id', $employee->id)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->whereIn('status', [AttendanceStatusEnum::PRESENT->value, AttendanceStatusEnum::LATE->value])
                ->count();

            $halfDays = Attendance::where('employee_id', $employee->id)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->where('status', AttendanceStatusEnum::HALF_DAY->value)
                ->count();

            $effectiveDays = $presentDays + ($halfDays * 0.5);
            $leaveDays = max(0, $workingDays - $effectiveDays);

            $prorateRatio = $workingDays > 0 ? $effectiveDays / $workingDays : 1;

            $grossSalary = 0;
            $totalDed = 0;
            $payslipItems = [];

            foreach ($structure->items as $item) {
                $component = $item->salaryComponent;
                if (! $component || ! $component->is_active) {
                    continue;
                }

                $amount = $item->amount;

                if ($component->type === SalaryComponentTypeEnum::EARNING) {
                    $proratedAmount = round($amount * $prorateRatio, 2);
                    $grossSalary += $proratedAmount;
                    $payslipItems[] = [
                        'salary_component_id' => $component->id,
                        'component_name' => $component->name,
                        'component_type' => $component->type->value,
                        'amount' => $proratedAmount,
                    ];
                } else {
                    $totalDed += $amount;
                    $payslipItems[] = [
                        'salary_component_id' => $component->id,
                        'component_name' => $component->name,
                        'component_type' => $component->type->value,
                        'amount' => $amount,
                    ];
                }
            }

            $netSalary = $grossSalary - $totalDed;

            $payslip = Payslip::create([
                'payroll_run_id' => $payrollRun->id,
                'employee_id' => $employee->id,
                'working_days' => $workingDays,
                'present_days' => (int) $effectiveDays,
                'leave_days' => (int) $leaveDays,
                'gross_salary' => round($grossSalary, 2),
                'total_deductions' => round($totalDed, 2),
                'net_salary' => round($netSalary, 2),
            ]);

            foreach ($payslipItems as $item) {
                PayslipItem::create(array_merge(['payslip_id' => $payslip->id], $item));
            }

            $totalGross += $grossSalary;
            $totalDeductions += $totalDed;
        }

        $payrollRun->update([
            'total_gross' => round($totalGross, 2),
            'total_deductions' => round($totalDeductions, 2),
            'total_net' => round($totalGross - $totalDeductions, 2),
            'status' => 'processed',
            'processed_by' => auth('admin')->id(),
            'processed_at' => now(),
        ]);

        return $payrollRun->fresh();
    }

    protected function getWorkingDays(int $month, int $year, int $companyId): int
    {
        $start = Carbon::create($year, $month, 1);
        $end = $start->copy()->endOfMonth();

        $holidays = Holiday::where('company_id', $companyId)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->pluck('date')
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->toArray();

        $days = 0;
        $current = $start->copy();
        while ($current <= $end) {
            if (! $current->isWeekend() && ! in_array($current->format('Y-m-d'), $holidays)) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }
}
