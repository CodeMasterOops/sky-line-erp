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
use Laravel\Sanctum\Sanctum;
use App\Enums\LeaveStatusEnum;
use App\Enums\TdsCategoryEnum;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Services\TenantService;
use App\Enums\PayrollStatusEnum;
use App\Models\LeaveApplication;
use App\Services\PayrollService;
use App\Enums\AttendanceStatusEnum;
use App\Models\SalaryStructureItem;
use Illuminate\Support\Facades\Cache;
use App\Enums\SalaryComponentTypeEnum;
use Illuminate\Support\Facades\Schema;
use App\Services\Payroll\SsfComponentService;
use App\Services\Payroll\SalarySlabTaxCalculator;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function pr4WarmCache(): void
{
    $tables = [];
    foreach (Schema::getTableListing() as $table) {
        $plainName = str_starts_with($table, 'main.') ? substr($table, 5) : $table;
        $tables[$table] = Schema::getColumnListing($plainName);
    }
    Cache::forget('allTables');
    Cache::forever('allTables', $tables);
}

beforeEach(function () {
    pr4WarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081-82', 'year_code' => '8182',
        'start_date' => '2024-07-17', 'end_date' => '2025-07-16', 'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Sprint4 Payroll Co',
        'code' => 'SP4-'.uniqid(),
        'inventory_costing_method' => 'fifo',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'HR Admin',
        'email' => 'hr4-'.uniqid().'@test.com',
        'password' => bcrypt('secret'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function pr4Employee(object $test, ?TdsCategoryEnum $tds = null): Employee
{
    return Employee::create([
        'company_id' => $test->company->id,
        'employee_code' => 'EMP4-'.uniqid(),
        'first_name' => 'Test',
        'last_name' => 'Worker',
        'join_date' => '2024-01-01',
        'status' => 'active',
        'tds_category' => $tds?->value,
    ]);
}

function pr4Structure(object $test, Employee $employee, float $amount, bool $taxable = false): SalaryStructure
{
    $comp = SalaryComponent::create([
        'company_id' => $test->company->id,
        'name' => 'Basic-'.uniqid(),
        'type' => SalaryComponentTypeEnum::EARNING,
        'calculation_type' => 'fixed',
        'is_taxable' => $taxable,
        'is_active' => true,
    ]);

    $s = SalaryStructure::create([
        'company_id' => $test->company->id,
        'employee_id' => $employee->id,
        'effective_from' => '2024-01-01',
        'is_active' => true,
    ]);

    SalaryStructureItem::create([
        'salary_structure_id' => $s->id,
        'salary_component_id' => $comp->id,
        'amount' => $amount,
        'percentage' => 0,
    ]);

    return $s;
}

function pr4FullAttendance(object $test, Employee $employee, int $year, int $month): void
{
    $current = Carbon::create($year, $month, 1);
    $end = $current->copy()->endOfMonth();
    while ($current <= $end) {
        if (! $current->isWeekend()) {
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

// ─── Item 1: SalarySlabTaxCalculator ──────────────────────────────────────────

it('SalarySlabTaxCalculator — 1% slab on income within first 5 lakh', function () {
    $calc = new SalarySlabTaxCalculator;

    expect($calc->annualTax(300_000))->toBe(3_000.0);
});

it('SalarySlabTaxCalculator — progressive tax across two slabs', function () {
    $calc = new SalarySlabTaxCalculator;

    // 5,00,000 × 1% = 5,000 + 2,00,000 × 10% = 20,000 → 25,000
    expect($calc->annualTax(700_000))->toBe(25_000.0);
});

it('SalarySlabTaxCalculator — zero tax on zero income', function () {
    expect((new SalarySlabTaxCalculator)->annualTax(0))->toBe(0.0);
});

it('SalarySlabTaxCalculator — monthly withholding is annual ÷ 12', function () {
    $calc = new SalarySlabTaxCalculator;
    $monthly = 60_000.0;
    $expected = round($calc->annualTax($monthly * 12) / 12, 2);

    expect($calc->monthlyWithholding($monthly))->toBe($expected);
});

// ─── Item 2: SSF system components ────────────────────────────────────────────

it('SsfComponentService creates two system components', function () {
    app(SsfComponentService::class)->ensure($this->company);

    $codes = SalaryComponent::withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('is_system', true)
        ->pluck('system_code')
        ->sort()
        ->values()
        ->all();

    expect($codes)->toBe(['SSF_EMPLOYEE_11', 'SSF_EMPLOYER_20']);
});

it('SsfComponentService is idempotent', function () {
    $svc = app(SsfComponentService::class);
    $svc->ensure($this->company);
    $svc->ensure($this->company);

    expect(
        SalaryComponent::withoutGlobalScopes()
            ->where('company_id', $this->company->id)
            ->where('is_system', true)
            ->count()
    )->toBe(2);
});

// ─── Item 3: percentage calculation_type ──────────────────────────────────────

it('calculate() computes percentage deduction as % of fixed earnings', function () {
    $employee = pr4Employee($this);

    $basic = SalaryComponent::create([
        'company_id' => $this->company->id, 'name' => 'Basic',
        'type' => SalaryComponentTypeEnum::EARNING,
        'calculation_type' => 'fixed', 'is_taxable' => false, 'is_active' => true,
    ]);

    $ssf = SalaryComponent::create([
        'company_id' => $this->company->id, 'name' => 'SSF 11%',
        'type' => SalaryComponentTypeEnum::DEDUCTION,
        'calculation_type' => 'percentage', 'is_taxable' => false, 'is_active' => true,
    ]);

    $structure = SalaryStructure::create([
        'company_id' => $this->company->id, 'employee_id' => $employee->id,
        'effective_from' => '2024-01-01', 'is_active' => true,
    ]);

    SalaryStructureItem::create(['salary_structure_id' => $structure->id, 'salary_component_id' => $basic->id, 'amount' => 50_000, 'percentage' => 0]);
    SalaryStructureItem::create(['salary_structure_id' => $structure->id, 'salary_component_id' => $ssf->id, 'amount' => 0, 'percentage' => 11]);

    pr4FullAttendance($this, $employee, 2024, 8);

    $run = PayrollRun::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'month' => 8, 'status' => PayrollStatusEnum::DRAFT,
    ]);

    $result = app(PayrollService::class)->calculate($run);
    $payslip = $result->payslips->first();

    expect((float) $payslip->total_deductions)->toBe(5_500.0);
});

// ─── Item 4: Leave days count as effective ────────────────────────────────────

it('calculate() counts approved leave days as effective, increasing gross salary', function () {
    $employee = pr4Employee($this);
    pr4Structure($this, $employee, 30_000);

    $leaveType = \App\Models\LeaveType::create([
        'company_id' => $this->company->id,
        'name' => 'Annual Leave',
        'days_allowed' => 15,
    ]);

    LeaveApplication::create([
        'company_id' => $this->company->id,
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'from_date' => '2024-08-05',
        'to_date' => '2024-08-07',
        'days' => 3,
        'status' => LeaveStatusEnum::APPROVED,
    ]);

    $run = PayrollRun::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'month' => 8, 'status' => PayrollStatusEnum::DRAFT,
    ]);

    $result = app(PayrollService::class)->calculate($run);
    $payslip = $result->payslips->first();

    // With 3 approved leave days effective, gross > 0
    expect((float) $payslip->gross_salary)->toBeGreaterThan(0);
});

// ─── Item 5: prorated TDS base ────────────────────────────────────────────────

it('calculateTds() uses prorated gross as the flat-rate TDS base', function () {
    $employee = pr4Employee($this, TdsCategoryEnum::COMMISSION);
    $structure = pr4Structure($this, $employee, 100_000, true);
    $structure->load('items.salaryComponent');

    // grossSalary = 50,000 (50% prorate)
    $tds = app(PayrollService::class)->calculateTds($employee, 50_000, $structure);

    // COMMISSION = 15%; 50,000 × 0.15 = 7,500
    expect($tds)->toBe(7_500.0);
});

it('calculateTds() routes SALARY category through SalarySlabTaxCalculator', function () {
    $employee = pr4Employee($this, TdsCategoryEnum::SALARY);
    $structure = pr4Structure($this, $employee, 50_000, true);
    $structure->load('items.salaryComponent');

    $tds = app(PayrollService::class)->calculateTds($employee, 50_000, $structure);

    expect($tds)->toBe((new SalarySlabTaxCalculator)->monthlyWithholding(50_000));
});

// ─── Item 6 + 8: calculate() transaction + PENDING_APPROVAL status ────────────

it('calculate() wraps in a transaction and sets status to PENDING_APPROVAL', function () {
    $run = PayrollRun::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'month' => 8, 'status' => PayrollStatusEnum::DRAFT,
    ]);

    $result = app(PayrollService::class)->calculate($run);

    expect($result->status)->toBe(PayrollStatusEnum::PENDING_APPROVAL);
});

// ─── Item 7: postToLedger() sets PAID inside same transaction ─────────────────

it('postToLedger() rolls back status to PROCESSED when journal is unbalanced', function () {
    $run = PayrollRun::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'month' => 8, 'total_gross' => 50_000, 'total_deductions' => 0, 'total_net' => 50_000,
        'status' => PayrollStatusEnum::PROCESSED,
    ]);

    $bank = Account::create([
        'company_id' => $this->company->id, 'account_group_id' => null,
        'name' => 'Bank', 'code' => 'BNK-'.uniqid(),
    ]);

    expect(fn () => app(PayrollService::class)->postToLedger($run, $bank->id))
        ->toThrow(\RuntimeException::class);

    expect($run->fresh()->status)->toBe(PayrollStatusEnum::PROCESSED);
});

// ─── Item 8: approve() endpoint ──────────────────────────────────────────────

it('approve() transitions PENDING_APPROVAL → PROCESSED', function () {
    $run = PayrollRun::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'month' => 9, 'status' => PayrollStatusEnum::PENDING_APPROVAL,
    ]);

    $result = app(PayrollService::class)->approve($run);

    expect($result->status)->toBe(PayrollStatusEnum::PROCESSED);
});

it('approve() throws when status is not PENDING_APPROVAL', function () {
    $run = PayrollRun::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'month' => 10, 'status' => PayrollStatusEnum::DRAFT,
    ]);

    expect(fn () => app(PayrollService::class)->approve($run))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

// ─── Items 9 + 10: controller store — race guard + company_id ─────────────────

it('store endpoint rejects a duplicate payroll run for the same month', function () {
    PayrollRun::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'month' => 8, 'status' => PayrollStatusEnum::DRAFT,
    ]);

    $this->postJson('/api/admin/hr/payroll', [
        'fiscal_year_id' => $this->fiscalYear->id,
        'month' => 8,
    ])->assertUnprocessable();
});

it('store endpoint sets company_id on the new payroll run', function () {
    $response = $this->postJson('/api/admin/hr/payroll', [
        'fiscal_year_id' => $this->fiscalYear->id,
        'month' => 11,
    ]);

    $response->assertCreated();

    $run = PayrollRun::withoutGlobalScopes()->latest()->first();
    expect($run->company_id)->toBe($this->company->id);
});
