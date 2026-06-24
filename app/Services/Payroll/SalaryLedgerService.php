<?php

namespace App\Services\Payroll;

use App\Models\Payslip;
use App\Models\Employee;

/**
 * Builds a per-employee salary ledger for a fiscal year: one row per payroll
 * period (BS month) with running year-to-date totals.
 */
class SalaryLedgerService
{
    /**
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, float>}
     */
    public function forEmployee(Employee $employee, int $fiscalYearId): array
    {
        $payslips = Payslip::where('employee_id', $employee->id)
            ->whereHas('payrollRun', fn ($q) => $q->where('fiscal_year_id', $fiscalYearId))
            ->with('payrollRun')
            ->get()
            ->sortBy(fn (Payslip $p) => $p->payrollRun?->month)
            ->values();

        $rows = [];
        $ytdGross = $ytdDeductions = $ytdTds = $ytdNet = 0.0;

        foreach ($payslips as $payslip) {
            $run = $payslip->payrollRun;
            $ytdGross += (float) $payslip->gross_salary;
            $ytdDeductions += (float) $payslip->total_deductions;
            $ytdTds += (float) $payslip->tds_amount;
            $ytdNet += (float) $payslip->net_salary;

            $rows[] = [
                'payslip_id' => $payslip->id,
                'payroll_run_id' => $payslip->payroll_run_id,
                'period_label' => $run?->periodLabel(),
                'bs_year' => $run?->bs_year,
                'bs_month' => $run?->bs_month,
                'month' => $run?->month,
                'status' => $run?->status?->value,
                'status_label' => $run?->status?->label(),
                'working_days' => $payslip->working_days,
                'present_days' => $payslip->present_days,
                'gross_salary' => (float) $payslip->gross_salary,
                'total_deductions' => (float) $payslip->total_deductions,
                'tds_amount' => (float) $payslip->tds_amount,
                'net_salary' => (float) $payslip->net_salary,
                'ytd_gross' => round($ytdGross, 2),
                'ytd_net' => round($ytdNet, 2),
            ];
        }

        return [
            'rows' => $rows,
            'totals' => [
                'gross' => round($ytdGross, 2),
                'deductions' => round($ytdDeductions, 2),
                'tds' => round($ytdTds, 2),
                'net' => round($ytdNet, 2),
            ],
        ];
    }
}
