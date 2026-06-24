<?php

use Carbon\Carbon;
use App\Models\User;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\FiscalYear;
use App\Models\PayrollRun;
use App\Enums\UserTypeEnum;
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
use App\Services\Nepal\NepaliDateService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function bsWarmCache(): void
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
    bsWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081-82', 'year_code' => '8182',
        'start_date' => '2024-07-16', 'end_date' => '2025-07-15', 'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'BS Payroll Co', 'code' => 'BS-'.uniqid(),
        'inventory_costing_method' => 'fifo',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id, 'name' => 'HR',
        'email' => 'bs-'.uniqid().'@test.com', 'password' => bcrypt('secret'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function bsEmployeeWithBasic(object $test, float $amount): Employee
{
    $employee = Employee::create([
        'company_id' => $test->company->id, 'employee_code' => 'BS-EMP-'.uniqid(),
        'first_name' => 'B', 'last_name' => 'S', 'join_date' => '2024-01-01', 'status' => 'active',
    ]);
    $basic = SalaryComponent::create([
        'company_id' => $test->company->id, 'name' => 'Basic', 'is_basic' => true,
        'type' => SalaryComponentTypeEnum::EARNING, 'calculation_type' => 'fixed',
        'is_taxable' => false, 'is_active' => true,
    ]);
    $structure = SalaryStructure::create([
        'company_id' => $test->company->id, 'employee_id' => $employee->id,
        'effective_from' => '2024-01-01', 'is_active' => true,
    ]);
    SalaryStructureItem::create(['salary_structure_id' => $structure->id, 'salary_component_id' => $basic->id, 'amount' => $amount, 'percentage' => 0]);

    return $employee;
}

it('resolves a BS month to its AD date range and labels it in Nepali', function () {
    $run = PayrollRun::make([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'bs_year' => 2081, 'bs_month' => 4, 'month' => 4, 'status' => PayrollStatusEnum::DRAFT,
    ]);

    $svc = app(NepaliDateService::class);
    [$start, $end] = $run->periodRange();

    expect($start->toDateString())->toBe($svc->bsToAd(2081, 4, 1)->toDateString())
        ->and($end->toDateString())->toBe($svc->bsToAd(2081, 4, $svc->daysInBsMonth(2081, 4))->toDateString())
        ->and($run->periodLabel())->toBe('Shrawan 2081');
});

it('calculates payroll for a BS month using attendance across two AD months', function () {
    $employee = bsEmployeeWithBasic($this, 30_000);

    $svc = app(NepaliDateService::class);
    $start = $svc->bsToAd(2081, 4, 1);
    $end = $svc->bsToAd(2081, 4, $svc->daysInBsMonth(2081, 4));

    // Present on every working day (Saturday off) of the BS month — spans Jul→Aug 2024.
    for ($d = $start->copy(); $d <= $end; $d->addDay()) {
        if ($d->dayOfWeek !== Carbon::SATURDAY) {
            Attendance::create([
                'company_id' => $this->company->id, 'employee_id' => $employee->id,
                'date' => $d->toDateString(), 'status' => AttendanceStatusEnum::PRESENT,
            ]);
        }
    }

    // Sanity: the BS month really does straddle two AD months.
    expect($start->month)->not->toBe($end->month);

    $run = PayrollRun::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'bs_year' => 2081, 'bs_month' => 4, 'month' => 4, 'status' => PayrollStatusEnum::DRAFT,
    ]);

    $payslip = app(PayrollService::class)->calculate($run)->payslips->first();

    // Full attendance over the BS month → no proration → full basic.
    expect((float) $payslip->gross_salary)->toBe(30_000.0)
        ->and($payslip->present_days)->toBe($payslip->working_days);
});

it('creates a BS payroll run over HTTP and exposes the BS label', function () {
    $res = $this->postJson('/api/admin/hr/payroll', [
        'fiscal_year_id' => $this->fiscalYear->id,
        'bs_year' => 2081, 'bs_month' => 4,
    ])->assertCreated()
        ->assertJsonPath('data.is_bs_period', true)
        ->assertJsonPath('data.bs_month', 4)
        ->assertJsonPath('data.period_label', 'Shrawan 2081');

    $run = PayrollRun::find($res->json('data.id'));
    expect($run->bs_year)->toBe(2081)->and($run->month)->toBe(4);
});

it('still supports legacy AD-month payroll runs', function () {
    $run = PayrollRun::make([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'month' => 8, 'status' => PayrollStatusEnum::DRAFT,
    ]);
    $run->setRelation('fiscalYear', $this->fiscalYear);

    [$start, $end] = $run->periodRange();

    expect($start->toDateString())->toBe('2024-08-01')
        ->and($end->toDateString())->toBe('2024-08-31')
        ->and($run->periodLabel())->toBe('August 2024');
});
