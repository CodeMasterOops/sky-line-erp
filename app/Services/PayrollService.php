<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Account;
use App\Models\Holiday;
use App\Models\Journal;
use App\Models\Payslip;
use App\Models\Employee;
use App\Enums\StatusEnum;
use App\Models\Attendance;
use App\Models\FiscalYear;
use App\Models\PayrollRun;
use App\Models\JournalItem;
use App\Models\PayslipItem;
use App\Models\TdsDeduction;
use App\Enums\JournalTypeEnum;
use Illuminate\Support\Facades\DB;
use App\Enums\AttendanceStatusEnum;
use App\Enums\SalaryComponentTypeEnum;

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

        $payrollRun->payslips()->each(fn ($p) => $p->items()->delete());
        $payrollRun->payslips()->delete();

        $totalGross = 0;
        $totalDeductions = 0;
        $totalTds = 0;

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

            $tdsAmount = $this->calculateTds($employee, $grossSalary, $structure);
            $netSalary = $grossSalary - $totalDed - $tdsAmount;

            $payslip = Payslip::create([
                'payroll_run_id' => $payrollRun->id,
                'employee_id' => $employee->id,
                'working_days' => $workingDays,
                'present_days' => (int) $effectiveDays,
                'leave_days' => (int) $leaveDays,
                'gross_salary' => round($grossSalary, 2),
                'total_deductions' => round($totalDed, 2),
                'tds_amount' => round($tdsAmount, 2),
                'net_salary' => round($netSalary, 2),
            ]);

            foreach ($payslipItems as $item) {
                PayslipItem::create(array_merge(['payslip_id' => $payslip->id], $item));
            }

            $totalGross += $grossSalary;
            $totalDeductions += $totalDed;
            $totalTds += $tdsAmount;
        }

        $payrollRun->update([
            'total_gross' => round($totalGross, 2),
            'total_deductions' => round($totalDeductions, 2),
            'total_net' => round($totalGross - $totalDeductions - $totalTds, 2),
            'status' => 'processed',
            'processed_by' => auth('admin')->id(),
            'processed_at' => now(),
        ]);

        return $payrollRun->fresh();
    }

    /**
     * Calculate TDS for an employee based on their tds_category and taxable earnings.
     */
    public function calculateTds(Employee $employee, float $grossSalary, $structure): float
    {
        if (! $employee->tds_category) {
            return 0;
        }

        $taxableAmount = 0;
        foreach ($structure->items as $item) {
            $component = $item->salaryComponent;
            if ($component && $component->is_taxable && $component->type === SalaryComponentTypeEnum::EARNING) {
                $taxableAmount += $item->amount;
            }
        }

        if ($taxableAmount <= 0) {
            return 0;
        }

        $rate = $employee->tds_category->rate();

        return round($taxableAmount * $rate / 100, 2);
    }

    /**
     * Post confirmed payroll to the general ledger and create TDS deduction records.
     * Creates one journal entry debiting salary expense accounts and crediting
     * the payment account (net) and TDS payable account (TDS withheld).
     */
    public function postToLedger(PayrollRun $payrollRun, int $paidAccountId): Journal
    {
        $payrollRun->loadMissing(['payslips.employee', 'payslips.items.salaryComponent', 'fiscalYear']);

        $fiscalYear = $payrollRun->fiscalYear;
        $companyId = $payrollRun->company_id;

        return DB::transaction(function () use ($payrollRun, $paidAccountId, $fiscalYear, $companyId) {
            $monthLabel = date('F Y', mktime(0, 0, 0, $payrollRun->month, 1, $fiscalYear->start_date->year));

            $journal = Journal::create([
                'company_id' => $companyId,
                'fiscal_year_id' => $fiscalYear->id,
                'type' => JournalTypeEnum::PAYMENT_VOUCHER,
                'reference_type' => PayrollRun::class,
                'reference_id' => $payrollRun->id,
                'voucher_no' => 'PAY-'.$payrollRun->id.'-'.$payrollRun->month,
                'date' => now()->toDateString(),
                'remarks' => "Salary payment for {$monthLabel}",
                'create_user_id' => auth('admin')->id(),
                'status' => StatusEnum::APPROVED,
            ]);

            // Group earnings by account_id for DR entries
            $earningsByAccount = [];
            $totalTds = 0;

            foreach ($payrollRun->payslips as $payslip) {
                $totalTds += $payslip->tds_amount ?? 0;

                foreach ($payslip->items as $item) {
                    $component = $item->salaryComponent;
                    if (! $component || $component->type !== SalaryComponentTypeEnum::EARNING) {
                        continue;
                    }
                    $accountId = $component->account_id;
                    if (! $accountId) {
                        continue;
                    }
                    $earningsByAccount[$accountId] = ($earningsByAccount[$accountId] ?? 0) + $item->amount;
                }
            }

            // DR salary expense accounts (one line per distinct account)
            foreach ($earningsByAccount as $accountId => $amount) {
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'account_id' => $accountId,
                    'dr_amount' => round($amount, 2),
                    'cr_amount' => 0,
                    'remarks' => "Salary expense – {$monthLabel}",
                ]);
            }

            // CR bank/cash account for net pay
            $totalNetPay = $payrollRun->total_net ?? ($payrollRun->total_gross - $payrollRun->total_deductions - $totalTds);
            JournalItem::create([
                'journal_id' => $journal->id,
                'account_id' => $paidAccountId,
                'dr_amount' => 0,
                'cr_amount' => round($totalNetPay, 2),
                'remarks' => "Net salary paid – {$monthLabel}",
            ]);

            // CR TDS payable account if there is TDS
            if ($totalTds > 0) {
                $tdsPayableAccount = Account::where('company_id', $companyId)
                    ->where('name', 'like', '%TDS%Payable%')
                    ->orWhere('name', 'like', '%Tax%Withheld%')
                    ->first();

                if ($tdsPayableAccount) {
                    JournalItem::create([
                        'journal_id' => $journal->id,
                        'account_id' => $tdsPayableAccount->id,
                        'dr_amount' => 0,
                        'cr_amount' => round($totalTds, 2),
                        'remarks' => "TDS withheld on salary – {$monthLabel}",
                    ]);
                }

                // Create TDS deduction records per payslip
                foreach ($payrollRun->payslips as $payslip) {
                    if (($payslip->tds_amount ?? 0) <= 0) {
                        continue;
                    }
                    $employee = $payslip->employee;
                    if (! $employee || ! $employee->tds_category) {
                        continue;
                    }

                    TdsDeduction::create([
                        'company_id' => $companyId,
                        'fiscal_year_id' => $fiscalYear->id,
                        'deductible_type' => Payslip::class,
                        'deductible_id' => $payslip->id,
                        'tds_category' => $employee->tds_category,
                        'base_amount' => $payslip->gross_salary,
                        'tds_rate' => $employee->tds_category->rate(),
                        'tds_amount' => $payslip->tds_amount,
                        'period_month' => $payrollRun->month,
                        'journal_id' => $journal->id,
                    ]);
                }
            }

            // Save journal_id and paid_account_id on the payroll run
            $payrollRun->update([
                'journal_id' => $journal->id,
                'paid_account_id' => $paidAccountId,
                'paid_at' => now(),
            ]);

            return $journal;
        });
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
