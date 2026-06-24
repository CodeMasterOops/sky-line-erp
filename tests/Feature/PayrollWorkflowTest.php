<?php

use Carbon\Carbon;
use App\Models\User;
use App\Models\Account;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\FiscalYear;
use App\Models\PayrollRun;
use App\Enums\UserTypeEnum;
use App\Models\JournalItem;
use Laravel\Sanctum\Sanctum;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Services\TenantService;
use App\Enums\PayrollStatusEnum;
use App\Services\PayrollService;
use App\Enums\AttendanceStatusEnum;
use App\Models\SalaryStructureItem;
use Illuminate\Support\Facades\Cache;
use App\Enums\SalaryComponentTypeEnum;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function pwWarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget(allTablesCacheKey());
    Cache::forever(allTablesCacheKey(), $tables);
}

beforeEach(function () {
    pwWarmCache();

    // Fiscal year starts in July (month 7) — month 8 → August same year.
    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081-82', 'year_code' => '8182',
        'start_date' => '2024-07-17', 'end_date' => '2025-07-16', 'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Workflow Payroll Co',
        'code' => 'WF-'.uniqid(),
        'inventory_costing_method' => 'fifo',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'HR Admin',
        'email' => 'wf-'.uniqid().'@test.com',
        'password' => bcrypt('secret'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function pwAccount(object $test, string $name): Account
{
    return Account::create([
        'company_id' => $test->company->id,
        'account_group_id' => null,
        'name' => $name,
        'code' => substr($name, 0, 3).'-'.uniqid(),
        'is_active' => true,
    ]);
}

/**
 * Employee with a structure of one fixed earning + one fixed deduction, both mapped to accounts.
 */
function pwEmployeeWithStructure(object $test, float $earning, float $deduction): Employee
{
    $employee = Employee::create([
        'company_id' => $test->company->id,
        'employee_code' => 'WF-EMP-'.uniqid(),
        'first_name' => 'Test',
        'last_name' => 'Worker',
        'join_date' => '2024-01-01',
        'status' => 'active',
    ]);

    $basic = SalaryComponent::create([
        'company_id' => $test->company->id, 'name' => 'Basic',
        'type' => SalaryComponentTypeEnum::EARNING, 'calculation_type' => 'fixed',
        'is_taxable' => false, 'is_active' => true,
        'account_id' => pwAccount($test, 'Salary Expense')->id,
    ]);

    $ssf = SalaryComponent::create([
        'company_id' => $test->company->id, 'name' => 'SSF',
        'type' => SalaryComponentTypeEnum::DEDUCTION, 'calculation_type' => 'fixed',
        'is_taxable' => false, 'is_active' => true,
        'account_id' => pwAccount($test, 'SSF Payable')->id,
    ]);

    $structure = SalaryStructure::create([
        'company_id' => $test->company->id, 'employee_id' => $employee->id,
        'effective_from' => '2024-01-01', 'is_active' => true,
    ]);

    SalaryStructureItem::create(['salary_structure_id' => $structure->id, 'salary_component_id' => $basic->id, 'amount' => $earning, 'percentage' => 0]);
    SalaryStructureItem::create(['salary_structure_id' => $structure->id, 'salary_component_id' => $ssf->id, 'amount' => $deduction, 'percentage' => 0]);

    return $employee;
}

/** Mark employee present on every working day (Saturday excluded) of the month. */
function pwFullAttendance(object $test, Employee $employee, int $year, int $month): void
{
    $current = Carbon::create($year, $month, 1);
    $end = $current->copy()->endOfMonth();
    while ($current <= $end) {
        if ($current->dayOfWeek !== Carbon::SATURDAY) {
            Attendance::create([
                'company_id' => $test->company->id,
                'employee_id' => $employee->id,
                'date' => $current->toDateString(),
                'status' => AttendanceStatusEnum::PRESENT,
            ]);
        }
        $current->addDay();
    }
}

// ─── BUG-001: approve route + full workflow ───────────────────────────────────

it('walks the full DRAFT → process → approve → confirm → PAID workflow over HTTP', function () {
    $employee = pwEmployeeWithStructure($this, 50_000, 5_000);
    pwFullAttendance($this, $employee, 2024, 8);
    $bank = pwAccount($this, 'Bank');

    $create = $this->postJson('/api/admin/hr/payroll', [
        'fiscal_year_id' => $this->fiscalYear->id,
        'month' => 8,
    ])->assertCreated();

    $runId = $create->json('data.id');

    $this->postJson("/api/admin/hr/payroll/{$runId}/process")
        ->assertOk()
        ->assertJsonPath('data.status', PayrollStatusEnum::PENDING_APPROVAL->value);

    // Approve and confirm must return the payslips so the detail view stays
    // populated without a manual refresh.
    $this->postJson("/api/admin/hr/payroll/{$runId}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', PayrollStatusEnum::PROCESSED->value)
        ->assertJsonCount(1, 'data.payslips');

    $this->postJson("/api/admin/hr/payroll/{$runId}/confirm", ['paid_account_id' => $bank->id])
        ->assertOk()
        ->assertJsonPath('data.status', PayrollStatusEnum::PAID->value)
        ->assertJsonCount(1, 'data.payslips');

    expect(PayrollRun::find($runId)->journal_id)->not->toBeNull();
});

it('blocks confirm before approval (still PENDING_APPROVAL)', function () {
    $employee = pwEmployeeWithStructure($this, 50_000, 5_000);
    pwFullAttendance($this, $employee, 2024, 8);
    $bank = pwAccount($this, 'Bank');

    $runId = $this->postJson('/api/admin/hr/payroll', [
        'fiscal_year_id' => $this->fiscalYear->id, 'month' => 8,
    ])->json('data.id');

    $this->postJson("/api/admin/hr/payroll/{$runId}/process")->assertOk();

    $this->postJson("/api/admin/hr/payroll/{$runId}/confirm", ['paid_account_id' => $bank->id])
        ->assertForbidden();
});

// ─── BUG-003: balanced GL journal with deductions ─────────────────────────────

it('posts a balanced journal when the payroll has deductions', function () {
    $employee = pwEmployeeWithStructure($this, 50_000, 5_000);
    pwFullAttendance($this, $employee, 2024, 8);
    $bank = pwAccount($this, 'Bank');

    $run = PayrollRun::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'month' => 8, 'status' => PayrollStatusEnum::DRAFT,
    ]);

    $service = app(PayrollService::class);
    $run = $service->calculate($run);
    $run = $service->approve($run);
    $journal = $service->postToLedger($run, $bank->id);

    $items = JournalItem::where('journal_id', $journal->id)->get();

    expect(round($items->sum('dr_amount'), 2))->toBe(50_000.0)
        ->and(round($items->sum('cr_amount'), 2))->toBe(50_000.0);

    // Net pay credited to bank = gross − deduction
    expect((float) $items->firstWhere('account_id', $bank->id)->cr_amount)->toBe(45_000.0);
});

it('refuses to post when a deduction component has no ledger account', function () {
    $employee = pwEmployeeWithStructure($this, 50_000, 5_000);
    // Strip the account from the deduction component.
    SalaryComponent::where('company_id', $this->company->id)
        ->where('name', 'SSF')->update(['account_id' => null]);
    pwFullAttendance($this, $employee, 2024, 8);
    $bank = pwAccount($this, 'Bank');

    $run = PayrollRun::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'month' => 8, 'status' => PayrollStatusEnum::DRAFT,
    ]);

    $service = app(PayrollService::class);
    $run = $service->approve($service->calculate($run));

    expect(fn () => $service->postToLedger($run, $bank->id))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

// ─── NEPAL-002: Saturday-only weekly off ──────────────────────────────────────

it('counts Sunday as a working day (Saturday is the only weekly off)', function () {
    $employee = pwEmployeeWithStructure($this, 30_000, 0);
    pwFullAttendance($this, $employee, 2024, 8);

    $run = PayrollRun::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'month' => 8, 'status' => PayrollStatusEnum::DRAFT,
    ]);

    $payslip = app(PayrollService::class)->calculate($run)->payslips->first();

    // Expected = all days in August 2024 minus Saturdays.
    $start = Carbon::create(2024, 8, 1);
    $end = $start->copy()->endOfMonth();
    $satOnly = 0;
    $monToFri = 0;
    for ($d = $start->copy(); $d <= $end; $d->addDay()) {
        if ($d->dayOfWeek !== Carbon::SATURDAY) {
            $satOnly++;
        }
        if (! $d->isWeekend()) {
            $monToFri++;
        }
    }

    expect($payslip->working_days)->toBe($satOnly)
        ->and($payslip->working_days)->toBeGreaterThan($monToFri); // proves Sundays are included
});

// ─── PAY-004: proration clamped to 100% ───────────────────────────────────────

it('clamps proration to full salary when present + leave exceed working days', function () {
    $employee = pwEmployeeWithStructure($this, 30_000, 0);
    pwFullAttendance($this, $employee, 2024, 8); // present on every working day

    $leaveType = \App\Models\LeaveType::create([
        'company_id' => $this->company->id, 'name' => 'Annual', 'days_allowed' => 15,
    ]);

    // Overlapping approved leave on working days → effectiveDays would exceed workingDays.
    \App\Models\LeaveApplication::create([
        'company_id' => $this->company->id, 'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'from_date' => '2024-08-05', 'to_date' => '2024-08-07', 'days' => 3,
        'status' => \App\Enums\LeaveStatusEnum::APPROVED,
    ]);

    $run = PayrollRun::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'month' => 8, 'status' => PayrollStatusEnum::DRAFT,
    ]);

    $payslip = app(PayrollService::class)->calculate($run)->payslips->first();

    expect((float) $payslip->gross_salary)->toBe(30_000.0); // not more than full
});

// ─── LEAVE-001: leave counted on working days only ────────────────────────────

it('counts approved leave on working days only (excludes Saturdays in the range)', function () {
    $employee = pwEmployeeWithStructure($this, 30_000, 0);

    $leaveType = \App\Models\LeaveType::create([
        'company_id' => $this->company->id, 'name' => 'Sick', 'days_allowed' => 15,
    ]);

    // 2024-08-08 (Thu) → 2024-08-12 (Mon) = 5 calendar days, incl. Sat Aug 10 → 4 working days.
    \App\Models\LeaveApplication::create([
        'company_id' => $this->company->id, 'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'from_date' => '2024-08-08', 'to_date' => '2024-08-12', 'days' => 5,
        'status' => \App\Enums\LeaveStatusEnum::APPROVED,
    ]);

    $run = PayrollRun::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'month' => 8, 'status' => PayrollStatusEnum::DRAFT,
    ]);

    // No attendance → leave working days stored in leave_days (not present_days).
    $payslip = app(PayrollService::class)->calculate($run)->payslips->first();

    expect($payslip->leave_days)->toBe(4) // 5 calendar − 1 Saturday
        ->and($payslip->present_days)->toBe(0);
});

// ─── PAY-006: payslip attendance breakdown columns ────────────────────────────

it('records a correct present / half / leave / absent breakdown on the payslip', function () {
    $employee = pwEmployeeWithStructure($this, 30_000, 0);

    foreach (['2024-08-01', '2024-08-02', '2024-08-05'] as $date) {
        \App\Models\Attendance::create([
            'company_id' => $this->company->id, 'employee_id' => $employee->id,
            'date' => $date, 'status' => \App\Enums\AttendanceStatusEnum::PRESENT,
        ]);
    }
    \App\Models\Attendance::create([
        'company_id' => $this->company->id, 'employee_id' => $employee->id,
        'date' => '2024-08-06', 'status' => \App\Enums\AttendanceStatusEnum::HALF_DAY,
    ]);

    $leaveType = \App\Models\LeaveType::create([
        'company_id' => $this->company->id, 'name' => 'Casual', 'days_allowed' => 10,
    ]);
    \App\Models\LeaveApplication::create([
        'company_id' => $this->company->id, 'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'from_date' => '2024-08-07', 'to_date' => '2024-08-07', 'days' => 1,
        'status' => \App\Enums\LeaveStatusEnum::APPROVED,
    ]);

    $run = PayrollRun::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'month' => 8, 'status' => PayrollStatusEnum::DRAFT,
    ]);

    $payslip = app(PayrollService::class)->calculate($run)->payslips->first();

    // August 2024 working days (Saturdays excluded) = 26.
    $expectedAbsent = (int) round($payslip->working_days - (3 + 0.5 + 1));

    expect($payslip->present_days)->toBe(3)
        ->and($payslip->half_days)->toBe(1)
        ->and($payslip->leave_days)->toBe(1)
        ->and($payslip->absent_days)->toBe($expectedAbsent);
});

// ─── PAY-005: salary structure effective_from honored ─────────────────────────

it('selects the latest structure effective on or before the period, ignoring future ones', function () {
    $employee = Employee::create([
        'company_id' => $this->company->id, 'employee_code' => 'WF-EFF-'.uniqid(),
        'first_name' => 'Eff', 'last_name' => 'Test', 'join_date' => '2024-01-01', 'status' => 'active',
    ]);

    $basic = SalaryComponent::create([
        'company_id' => $this->company->id, 'name' => 'Basic',
        'type' => SalaryComponentTypeEnum::EARNING, 'calculation_type' => 'fixed',
        'is_taxable' => false, 'is_active' => true,
    ]);

    foreach ([['2024-01-01', 20_000], ['2099-01-01', 99_000]] as [$from, $amount]) {
        $structure = SalaryStructure::create([
            'company_id' => $this->company->id, 'employee_id' => $employee->id,
            'effective_from' => $from, 'is_active' => true,
        ]);
        SalaryStructureItem::create([
            'salary_structure_id' => $structure->id, 'salary_component_id' => $basic->id,
            'amount' => $amount, 'percentage' => 0,
        ]);
    }

    pwFullAttendance($this, $employee, 2024, 8);

    $run = PayrollRun::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'month' => 8, 'status' => PayrollStatusEnum::DRAFT,
    ]);

    $payslip = app(PayrollService::class)->calculate($run)->payslips->first();

    expect((float) $payslip->gross_salary)->toBe(20_000.0); // not the future 99,000 structure
});

// ─── PAY-001: percentage_base = basic ─────────────────────────────────────────

it('bases an SSF percentage deduction on Basic only, not on total earnings', function () {
    $employee = Employee::create([
        'company_id' => $this->company->id, 'employee_code' => 'WF-SSF-'.uniqid(),
        'first_name' => 'Ssf', 'last_name' => 'Base', 'join_date' => '2024-01-01', 'status' => 'active',
    ]);

    $basic = SalaryComponent::create([
        'company_id' => $this->company->id, 'name' => 'Basic', 'is_basic' => true,
        'type' => SalaryComponentTypeEnum::EARNING, 'calculation_type' => 'fixed',
        'is_taxable' => false, 'is_active' => true,
    ]);
    $allowance = SalaryComponent::create([
        'company_id' => $this->company->id, 'name' => 'Allowance',
        'type' => SalaryComponentTypeEnum::EARNING, 'calculation_type' => 'fixed',
        'is_taxable' => false, 'is_active' => true,
    ]);
    $ssf = SalaryComponent::create([
        'company_id' => $this->company->id, 'name' => 'SSF 11%',
        'type' => SalaryComponentTypeEnum::DEDUCTION, 'calculation_type' => 'percentage',
        'percentage_base' => 'basic', 'is_taxable' => false, 'is_active' => true,
    ]);

    $structure = SalaryStructure::create([
        'company_id' => $this->company->id, 'employee_id' => $employee->id,
        'effective_from' => '2024-01-01', 'is_active' => true,
    ]);
    SalaryStructureItem::create(['salary_structure_id' => $structure->id, 'salary_component_id' => $basic->id, 'amount' => 50_000, 'percentage' => 0]);
    SalaryStructureItem::create(['salary_structure_id' => $structure->id, 'salary_component_id' => $allowance->id, 'amount' => 10_000, 'percentage' => 0]);
    SalaryStructureItem::create(['salary_structure_id' => $structure->id, 'salary_component_id' => $ssf->id, 'amount' => 0, 'percentage' => 11]);

    pwFullAttendance($this, $employee, 2024, 8);

    $run = PayrollRun::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'month' => 8, 'status' => PayrollStatusEnum::DRAFT,
    ]);

    $payslip = app(PayrollService::class)->calculate($run)->payslips->first();

    // 11% of Basic 50,000 = 5,500 (NOT 11% of 60,000 gross = 6,600)
    expect((float) $payslip->total_deductions)->toBe(5_500.0)
        ->and((float) $payslip->gross_salary)->toBe(60_000.0);
});

// ─── PAY-002: employer contribution excluded from net + balanced GL ───────────

it('excludes employer contribution from net pay and posts a balanced employer journal', function () {
    $employee = Employee::create([
        'company_id' => $this->company->id, 'employee_code' => 'WF-EMP-CONTRIB-'.uniqid(),
        'first_name' => 'Emp', 'last_name' => 'Contrib', 'join_date' => '2024-01-01', 'status' => 'active',
    ]);

    $salaryExpense = pwAccount($this, 'Salary Expense');
    $ssfExpense = pwAccount($this, 'SSF Employer Expense');
    $ssfPayable = pwAccount($this, 'SSF Payable');
    $bank = pwAccount($this, 'Bank');

    $basic = SalaryComponent::create([
        'company_id' => $this->company->id, 'name' => 'Basic', 'is_basic' => true,
        'type' => SalaryComponentTypeEnum::EARNING, 'calculation_type' => 'fixed',
        'is_taxable' => false, 'is_active' => true, 'account_id' => $salaryExpense->id,
    ]);
    $employerSsf = SalaryComponent::create([
        'company_id' => $this->company->id, 'name' => 'SSF Employer 20%',
        'type' => SalaryComponentTypeEnum::EMPLOYER_CONTRIBUTION, 'calculation_type' => 'percentage',
        'percentage_base' => 'basic', 'is_taxable' => false, 'is_active' => true,
        'account_id' => $ssfExpense->id, 'contra_account_id' => $ssfPayable->id,
    ]);

    $structure = SalaryStructure::create([
        'company_id' => $this->company->id, 'employee_id' => $employee->id,
        'effective_from' => '2024-01-01', 'is_active' => true,
    ]);
    SalaryStructureItem::create(['salary_structure_id' => $structure->id, 'salary_component_id' => $basic->id, 'amount' => 50_000, 'percentage' => 0]);
    SalaryStructureItem::create(['salary_structure_id' => $structure->id, 'salary_component_id' => $employerSsf->id, 'amount' => 0, 'percentage' => 20]);

    pwFullAttendance($this, $employee, 2024, 8);

    $run = PayrollRun::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'month' => 8, 'status' => PayrollStatusEnum::DRAFT,
    ]);

    $service = app(PayrollService::class);
    $run = $service->calculate($run);
    $payslip = $run->payslips->first();

    // Net is gross (50,000) — employer contribution does NOT reduce it.
    expect((float) $payslip->net_salary)->toBe(50_000.0)
        ->and((float) $payslip->total_deductions)->toBe(0.0);

    $run = $service->approve($run);
    $journal = $service->postToLedger($run, $bank->id);

    $items = JournalItem::where('journal_id', $journal->id)->get();

    // Balanced: Dr salary 50k + Dr SSF expense 10k = 60k; Cr bank 50k + Cr SSF payable 10k = 60k.
    expect(round($items->sum('dr_amount'), 2))->toBe(60_000.0)
        ->and(round($items->sum('cr_amount'), 2))->toBe(60_000.0)
        ->and((float) $items->firstWhere('account_id', $ssfPayable->id)->cr_amount)->toBe(10_000.0)
        ->and((float) $items->firstWhere('account_id', $ssfExpense->id)->dr_amount)->toBe(10_000.0);
});

// ─── NEPAL-005: salary TDS uses marital status + SSF exemption ─────────────────

it('applies married slab and SSF exemption to salary TDS', function () {
    $employee = Employee::create([
        'company_id' => $this->company->id, 'employee_code' => 'WF-TDS-'.uniqid(),
        'first_name' => 'Married', 'last_name' => 'Ssf', 'join_date' => '2024-01-01', 'status' => 'active',
        'marital_status' => \App\Enums\MaritalStatusEnum::Married,
        'tds_category' => \App\Enums\TdsCategoryEnum::SALARY,
    ]);

    $basic = SalaryComponent::create([
        'company_id' => $this->company->id, 'name' => 'Basic', 'is_basic' => true,
        'type' => SalaryComponentTypeEnum::EARNING, 'calculation_type' => 'fixed',
        'is_taxable' => true, 'is_active' => true,
    ]);
    $ssf = SalaryComponent::create([
        'company_id' => $this->company->id, 'name' => 'SSF Employee', 'system_code' => 'SSF_EMPLOYEE_11',
        'type' => SalaryComponentTypeEnum::DEDUCTION, 'calculation_type' => 'percentage',
        'percentage_base' => 'basic', 'is_active' => true, 'is_system' => true,
    ]);
    $structure = SalaryStructure::create([
        'company_id' => $this->company->id, 'employee_id' => $employee->id,
        'effective_from' => '2024-01-01', 'is_active' => true,
    ]);
    SalaryStructureItem::create(['salary_structure_id' => $structure->id, 'salary_component_id' => $basic->id, 'amount' => 80_000, 'percentage' => 0]);
    SalaryStructureItem::create(['salary_structure_id' => $structure->id, 'salary_component_id' => $ssf->id, 'amount' => 0, 'percentage' => 11]);
    $structure->load('items.salaryComponent');

    $tds = app(PayrollService::class)->calculateTds($employee, 80_000, $structure);

    $expected = (new \App\Services\Payroll\SalarySlabTaxCalculator)->monthlyWithholding(80_000, 'married', true);

    expect($tds)->toBe($expected)->and($tds)->toBeGreaterThan(0.0);
});
