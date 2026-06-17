<?php

namespace App\Services;

use Carbon\Carbon;
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
use App\Enums\LeaveStatusEnum;
use App\Models\AccountSetting;
use App\Enums\PayrollStatusEnum;
use App\Models\LeaveApplication;
use Illuminate\Support\Facades\DB;
use App\Enums\AttendanceStatusEnum;
use App\Enums\SalaryComponentTypeEnum;
use App\Services\Accounting\PeriodLockGuard;
use Illuminate\Validation\ValidationException;
use App\Services\Accounting\JournalBalanceGuard;
use App\Services\Payroll\SalarySlabTaxCalculator;

class PayrollService
{
    public function __construct(
        private PeriodLockGuard $periodGuard,
        private JournalBalanceGuard $balanceGuard,
        private SalarySlabTaxCalculator $slabCalculator,
    ) {}

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

        return DB::transaction(function () use ($payrollRun, $employees, $month, $year, $workingDays) {
            $payrollRun->payslips()->each(fn ($p) => $p->items()->delete());
            $payrollRun->payslips()->delete();

            $totalGross = 0;
            $totalDeductions = 0;
            $totalTds = 0;

            $monthStart = Carbon::create($year, $month, 1)->startOfDay();
            $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();

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

                $approvedLeaveDays = LeaveApplication::withoutGlobalScopes()
                    ->where('employee_id', $employee->id)
                    ->where('status', LeaveStatusEnum::APPROVED)
                    ->where('from_date', '<=', $monthEnd->toDateString())
                    ->where('to_date', '>=', $monthStart->toDateString())
                    ->get()
                    ->sum(function (LeaveApplication $leave) use ($monthStart, $monthEnd): float {
                        $start = max($leave->from_date, $monthStart->toDateString());
                        $end = min($leave->to_date, $monthEnd->toDateString());

                        return (float) Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;
                    });

                $effectiveDays = $presentDays + ($halfDays * 0.5) + $approvedLeaveDays;
                $leaveDays = max(0, $workingDays - $effectiveDays);

                $prorateRatio = $workingDays > 0 ? $effectiveDays / $workingDays : 1;

                // First pass: total fixed earnings (base for percentage components)
                $fixedEarningsBase = 0.0;
                foreach ($structure->items as $item) {
                    $component = $item->salaryComponent;
                    if ($component && $component->is_active
                        && $component->type === SalaryComponentTypeEnum::EARNING
                        && $component->calculation_type !== 'percentage') {
                        $fixedEarningsBase += (float) $item->amount;
                    }
                }

                $grossSalary = 0;
                $totalDed = 0;
                $payslipItems = [];

                foreach ($structure->items as $item) {
                    $component = $item->salaryComponent;
                    if (! $component || ! $component->is_active) {
                        continue;
                    }

                    $amount = $component->calculation_type === 'percentage'
                        ? round($fixedEarningsBase * ((float) $item->percentage / 100), 2)
                        : (float) $item->amount;

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

                foreach ($payslipItems as $psItem) {
                    PayslipItem::create(array_merge(['payslip_id' => $payslip->id], $psItem));
                }

                $totalGross += $grossSalary;
                $totalDeductions += $totalDed;
                $totalTds += $tdsAmount;
            }

            $payrollRun->update([
                'total_gross' => round($totalGross, 2),
                'total_deductions' => round($totalDeductions, 2),
                'total_net' => round($totalGross - $totalDeductions - $totalTds, 2),
                'status' => PayrollStatusEnum::PENDING_APPROVAL,
                'processed_by' => auth('admin')->id(),
                'processed_at' => now(),
            ]);

            return $payrollRun->fresh();
        });
    }

    /**
     * Calculate TDS for an employee based on their tds_category and prorated taxable earnings.
     */
    public function calculateTds(Employee $employee, float $grossSalary, $structure): float
    {
        if (! $employee->tds_category) {
            return 0;
        }

        if ($employee->tds_category->isSalary()) {
            return $this->slabCalculator->monthlyWithholding($grossSalary);
        }

        // Flat-rate TDS (vendor TDS categories)
        $totalEarnings = 0.0;
        $taxableEarnings = 0.0;
        foreach ($structure->items as $item) {
            $component = $item->salaryComponent;
            if ($component && $component->type === SalaryComponentTypeEnum::EARNING) {
                $totalEarnings += (float) $item->amount;
                if ($component->is_taxable) {
                    $taxableEarnings += (float) $item->amount;
                }
            }
        }

        if ($taxableEarnings <= 0) {
            return 0;
        }

        // Apply taxable fraction to the already-prorated gross salary
        $taxableFraction = $totalEarnings > 0 ? $taxableEarnings / $totalEarnings : 1.0;
        $taxableBase = $grossSalary * $taxableFraction;

        return round($taxableBase * $employee->tds_category->rate() / 100, 2);
    }

    /**
     * Approve a processed payroll run (PENDING_APPROVAL → PROCESSED).
     */
    public function approve(PayrollRun $payrollRun): PayrollRun
    {
        if ($payrollRun->status !== PayrollStatusEnum::PENDING_APPROVAL) {
            throw ValidationException::withMessages([
                'status' => 'Only payroll runs pending approval can be approved.',
            ]);
        }

        $payrollRun->update(['status' => PayrollStatusEnum::PROCESSED]);

        return $payrollRun->fresh();
    }

    /**
     * Post confirmed payroll to the general ledger and mark as PAID atomically.
     */
    public function postToLedger(PayrollRun $payrollRun, int $paidAccountId): Journal
    {
        $payrollRun->loadMissing(['payslips.employee', 'payslips.items.salaryComponent', 'fiscalYear']);

        $fiscalYear = $payrollRun->fiscalYear;
        $companyId = $payrollRun->company_id;

        $postingDate = now()->toDateString();
        $this->periodGuard->assertPostable($companyId, $fiscalYear->id, $postingDate);

        return DB::transaction(function () use ($payrollRun, $paidAccountId, $fiscalYear, $companyId, $postingDate) {
            $monthLabel = date('F Y', mktime(0, 0, 0, $payrollRun->month, 1, $fiscalYear->start_date->year));

            $journal = Journal::create([
                'company_id' => $companyId,
                'fiscal_year_id' => $fiscalYear->id,
                'type' => JournalTypeEnum::PAYMENT_VOUCHER,
                'reference_type' => PayrollRun::class,
                'reference_id' => $payrollRun->id,
                'voucher_no' => 'PAY-'.$payrollRun->id.'-'.$payrollRun->month,
                'date' => $postingDate,
                'remarks' => "Salary payment for {$monthLabel}",
                'create_user_id' => auth('admin')->id(),
                'status' => StatusEnum::APPROVED,
            ]);

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

            foreach ($earningsByAccount as $accountId => $amount) {
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'account_id' => $accountId,
                    'dr_amount' => round($amount, 2),
                    'cr_amount' => 0,
                    'remarks' => "Salary expense – {$monthLabel}",
                ]);
            }

            $totalNetPay = $payrollRun->total_net ?? ($payrollRun->total_gross - $payrollRun->total_deductions - $totalTds);
            JournalItem::create([
                'journal_id' => $journal->id,
                'account_id' => $paidAccountId,
                'dr_amount' => 0,
                'cr_amount' => round($totalNetPay, 2),
                'remarks' => "Net salary paid – {$monthLabel}",
            ]);

            if ($totalTds > 0) {
                $accountSetting = AccountSetting::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->first();

                if (! $accountSetting?->tds_payable_account_id) {
                    throw ValidationException::withMessages([
                        'account_setting' => 'Cannot post payroll journal: TDS Payable account not configured. '
                            .'Set it under Accounting → Account Settings.',
                    ]);
                }

                JournalItem::create([
                    'journal_id' => $journal->id,
                    'account_id' => $accountSetting->tds_payable_account_id,
                    'dr_amount' => 0,
                    'cr_amount' => round($totalTds, 2),
                    'remarks' => "TDS withheld on salary – {$monthLabel}",
                ]);

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

            $this->balanceGuard->assertBalanced($journal);

            $payrollRun->update([
                'journal_id' => $journal->id,
                'paid_account_id' => $paidAccountId,
                'paid_at' => now(),
                'status' => PayrollStatusEnum::PAID,
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
