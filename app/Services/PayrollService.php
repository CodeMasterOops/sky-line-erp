<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Holiday;
use App\Models\Journal;
use App\Models\Payslip;
use App\Models\Employee;
use App\Enums\StatusEnum;
use App\Models\Attendance;
use App\Models\PayrollRun;
use App\Models\JournalItem;
use App\Models\PayslipItem;
use App\Models\TdsDeduction;
use App\Models\WorkSchedule;
use App\Enums\JournalTypeEnum;
use App\Enums\LeaveStatusEnum;
use App\Models\AccountSetting;
use App\Models\SalaryComponent;
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

        $companyId = $payrollRun->company_id;

        [$monthStart, $monthEnd] = $payrollRun->periodRange();

        $schedule = WorkSchedule::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('is_default', true)
            ->first();

        $weeklyOffDays = $schedule?->weeklyOffDays() ?? self::WEEKLY_OFF_DAYS;

        $holidays = $this->holidaysBetween($monthStart, $monthEnd, $companyId);
        $workingDays = $this->workingDaysBetween($monthStart, $monthEnd, $holidays, $weeklyOffDays);

        $employees = Employee::where('company_id', $companyId)
            ->where('status', 'active')
            ->with(['salaryStructures' => fn ($q) => $q->where('is_active', true)
                ->where('effective_from', '<=', $monthEnd->toDateString())
                ->orderByDesc('effective_from')
                ->with('items.salaryComponent')])
            ->get();

        return DB::transaction(function () use ($payrollRun, $employees, $monthStart, $monthEnd, $workingDays, $holidays, $weeklyOffDays, $schedule, $companyId) {
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
                    ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->whereIn('status', [AttendanceStatusEnum::PRESENT->value, AttendanceStatusEnum::LATE->value])
                    ->count();

                $halfDays = Attendance::where('employee_id', $employee->id)
                    ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->where('status', AttendanceStatusEnum::HALF_DAY->value)
                    ->count();

                $approvedLeaveDays = LeaveApplication::withoutGlobalScopes()
                    ->where('employee_id', $employee->id)
                    ->where('status', LeaveStatusEnum::APPROVED)
                    ->where('from_date', '<=', $monthEnd->toDateString())
                    ->where('to_date', '>=', $monthStart->toDateString())
                    ->get()
                    ->sum(function (LeaveApplication $leave) use ($monthStart, $monthEnd, $holidays, $weeklyOffDays): float {
                        $start = Carbon::parse(max($leave->from_date->toDateString(), $monthStart->toDateString()));
                        $end = Carbon::parse(min($leave->to_date->toDateString(), $monthEnd->toDateString()));

                        return (float) $this->workingDaysBetween($start, $end, $holidays, $weeklyOffDays);
                    });

                $effectiveDays = $presentDays + ($halfDays * 0.5) + $approvedLeaveDays;
                $absentDays = max(0, $workingDays - $effectiveDays);

                $prorateRatio = $workingDays > 0 ? min(1.0, $effectiveDays / $workingDays) : 1;

                // First pass: percentage bases. "basic" = Basic-flagged fixed earnings;
                // "gross_earnings" = all fixed earnings.
                $fixedEarningsBase = 0.0;
                $basicBase = 0.0;
                foreach ($structure->items as $item) {
                    $component = $item->salaryComponent;
                    if ($component && $component->is_active
                        && $component->type === SalaryComponentTypeEnum::EARNING
                        && $component->calculation_type !== 'percentage') {
                        $fixedEarningsBase += (float) $item->amount;
                        if ($component->is_basic) {
                            $basicBase += (float) $item->amount;
                        }
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

                    if ($component->calculation_type === 'percentage') {
                        $base = $component->percentage_base === 'basic' ? $basicBase : $fixedEarningsBase;
                        $amount = round($base * ((float) $item->percentage / 100), 2);
                    } else {
                        $amount = (float) $item->amount;
                    }

                    if ($component->type === SalaryComponentTypeEnum::EARNING) {
                        $proratedAmount = round($amount * $prorateRatio, 2);
                        $grossSalary += $proratedAmount;
                        $payslipItems[] = [
                            'salary_component_id' => $component->id,
                            'component_name' => $component->name,
                            'component_type' => $component->type->value,
                            'amount' => $proratedAmount,
                        ];
                    } elseif ($component->type === SalaryComponentTypeEnum::DEDUCTION) {
                        $totalDed += $amount;
                        $payslipItems[] = [
                            'salary_component_id' => $component->id,
                            'component_name' => $component->name,
                            'component_type' => $component->type->value,
                            'amount' => $amount,
                        ];
                    } else {
                        // Employer contribution: employer cost only — excluded from net pay.
                        $payslipItems[] = [
                            'salary_component_id' => $component->id,
                            'component_name' => $component->name,
                            'component_type' => $component->type->value,
                            'amount' => $amount,
                        ];
                    }
                }

                // Overtime: paid only when the company schedule enables it.
                if ($schedule && $schedule->overtime_enabled && $workingDays > 0 && $basicBase > 0) {
                    $overtimeHours = (float) Attendance::where('employee_id', $employee->id)
                        ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                        ->sum('overtime_hours');

                    if ($overtimeHours > 0) {
                        $hourlyRate = $basicBase / ($workingDays * (float) $schedule->standard_hours_per_day);
                        $overtimeAmount = round($overtimeHours * $hourlyRate * (float) $schedule->overtime_multiplier, 2);

                        if ($overtimeAmount > 0) {
                            $otComponent = $this->ensureOvertimeComponent($companyId);
                            $grossSalary += $overtimeAmount;
                            $payslipItems[] = [
                                'salary_component_id' => $otComponent->id,
                                'component_name' => $otComponent->name,
                                'component_type' => SalaryComponentTypeEnum::EARNING->value,
                                'amount' => $overtimeAmount,
                            ];
                        }
                    }
                }

                $tdsAmount = $this->calculateTds($employee, $grossSalary, $structure);
                $netSalary = $grossSalary - $totalDed - $tdsAmount;

                $payslip = Payslip::create([
                    'payroll_run_id' => $payrollRun->id,
                    'employee_id' => $employee->id,
                    'working_days' => $workingDays,
                    'present_days' => $presentDays,
                    'half_days' => $halfDays,
                    'leave_days' => (int) round($approvedLeaveDays),
                    'absent_days' => (int) round($absentDays),
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
            $maritalStatus = $employee->marital_status?->value ?? 'single';
            $ssfContributor = $this->isSsfContributor($structure);

            return $this->slabCalculator->monthlyWithholding($grossSalary, $maritalStatus, $ssfContributor);
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
     * Whether the salary structure includes an active SSF employee contribution,
     * which exempts the employee from the 1% Social Security Tax band.
     */
    protected function isSsfContributor($structure): bool
    {
        foreach ($structure->items as $item) {
            $component = $item->salaryComponent;
            if ($component && $component->is_active && $component->system_code === 'SSF_EMPLOYEE_11') {
                return true;
            }
        }

        return false;
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
            $monthLabel = $payrollRun->periodLabel();

            $journal = Journal::create([
                'company_id' => $companyId,
                'branch_id' => $payrollRun->branch_id,
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
            $deductionsByAccount = [];
            $employerExpenseByAccount = [];
            $employerPayableByAccount = [];
            $missingEarningAccount = false;
            $missingDeductionAccount = false;
            $missingEmployerAccount = false;
            $totalTds = 0;

            foreach ($payrollRun->payslips as $payslip) {
                $totalTds += $payslip->tds_amount ?? 0;

                foreach ($payslip->items as $item) {
                    $component = $item->salaryComponent;
                    if (! $component) {
                        continue;
                    }

                    if ($component->type === SalaryComponentTypeEnum::EARNING) {
                        if ((float) $item->amount == 0.0) {
                            continue;
                        }
                        if (! $component->account_id) {
                            $missingEarningAccount = true;

                            continue;
                        }
                        $earningsByAccount[$component->account_id] = ($earningsByAccount[$component->account_id] ?? 0) + $item->amount;

                        continue;
                    }

                    if ((float) $item->amount == 0.0) {
                        continue;
                    }

                    if ($component->type === SalaryComponentTypeEnum::EMPLOYER_CONTRIBUTION) {
                        // Employer cost: Dr expense (account_id), Cr payable (contra_account_id).
                        if (! $component->account_id || ! $component->contra_account_id) {
                            $missingEmployerAccount = true;

                            continue;
                        }
                        $employerExpenseByAccount[$component->account_id] = ($employerExpenseByAccount[$component->account_id] ?? 0) + $item->amount;
                        $employerPayableByAccount[$component->contra_account_id] = ($employerPayableByAccount[$component->contra_account_id] ?? 0) + $item->amount;

                        continue;
                    }

                    // Deduction component: must be credited to its liability account so the
                    // journal balances (net pay already excludes deductions).
                    if (! $component->account_id) {
                        $missingDeductionAccount = true;

                        continue;
                    }
                    $deductionsByAccount[$component->account_id] = ($deductionsByAccount[$component->account_id] ?? 0) + $item->amount;
                }
            }

            if ($missingEarningAccount) {
                throw ValidationException::withMessages([
                    'salary_component' => 'Cannot post payroll journal: one or more earning components have no '
                        .'ledger account configured. Set an account on each earning salary component.',
                ]);
            }

            if ($missingDeductionAccount) {
                throw ValidationException::withMessages([
                    'salary_component' => 'Cannot post payroll journal: one or more deduction components have no '
                        .'ledger account configured. Set an account on each deduction salary component.',
                ]);
            }

            if ($missingEmployerAccount) {
                throw ValidationException::withMessages([
                    'salary_component' => 'Cannot post payroll journal: one or more employer-contribution components '
                        .'are missing an expense or payable account. Set both the account and contra account.',
                ]);
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

            foreach ($deductionsByAccount as $accountId => $amount) {
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'account_id' => $accountId,
                    'dr_amount' => 0,
                    'cr_amount' => round($amount, 2),
                    'remarks' => "Salary deduction – {$monthLabel}",
                ]);
            }

            // Employer contributions: self-balancing Dr expense / Cr payable pairs.
            foreach ($employerExpenseByAccount as $accountId => $amount) {
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'account_id' => $accountId,
                    'dr_amount' => round($amount, 2),
                    'cr_amount' => 0,
                    'remarks' => "Employer contribution expense – {$monthLabel}",
                ]);
            }

            foreach ($employerPayableByAccount as $accountId => $amount) {
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'account_id' => $accountId,
                    'dr_amount' => 0,
                    'cr_amount' => round($amount, 2),
                    'remarks' => "Employer contribution payable – {$monthLabel}",
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

    /**
     * Weekly off days for Nepal payroll. Saturday only — Sunday is a working day.
     *
     * @var array<int, int>
     */
    protected const WEEKLY_OFF_DAYS = [Carbon::SATURDAY];

    /**
     * Company holidays falling within an AD date range, as Y-m-d strings.
     * (A BS month spans two AD months, so the range form is required.)
     *
     * @return array<int, string>
     */
    protected function holidaysBetween(Carbon $start, Carbon $end, int $companyId): array
    {
        return Holiday::where('company_id', $companyId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->toArray();
    }

    /**
     * Count working days (inclusive) in a date range, excluding weekly-off days and holidays.
     *
     * @param  array<int, string>  $holidays  Y-m-d strings
     * @param  array<int, int>|null  $weeklyOffDays  Carbon dayOfWeek numbers; defaults to Saturday
     */
    protected function workingDaysBetween(Carbon $start, Carbon $end, array $holidays, ?array $weeklyOffDays = null): int
    {
        $weeklyOffDays ??= self::WEEKLY_OFF_DAYS;

        $days = 0;
        $current = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();

        while ($current <= $last) {
            $isWeeklyOff = in_array($current->dayOfWeek, $weeklyOffDays, true);
            if (! $isWeeklyOff && ! in_array($current->format('Y-m-d'), $holidays, true)) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }

    /**
     * Ensure a company has the system Overtime earning component and return it.
     */
    protected function ensureOvertimeComponent(int $companyId): SalaryComponent
    {
        return SalaryComponent::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $companyId, 'system_code' => 'OVERTIME'],
            [
                'name' => 'Overtime',
                'type' => SalaryComponentTypeEnum::EARNING,
                'calculation_type' => 'fixed',
                'is_taxable' => true,
                'is_active' => true,
                'is_system' => true,
            ],
        );
    }
}
