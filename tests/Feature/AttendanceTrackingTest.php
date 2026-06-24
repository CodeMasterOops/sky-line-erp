<?php

use Carbon\Carbon;
use App\Models\User;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\FiscalYear;
use App\Models\PayrollRun;
use App\Enums\UserTypeEnum;
use App\Models\WorkSchedule;
use Laravel\Sanctum\Sanctum;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Services\TenantService;
use App\Enums\PayrollStatusEnum;
use App\Services\PayrollService;
use App\Enums\AttendanceStatusEnum;
use App\Models\SalaryStructureItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use App\Enums\SalaryComponentTypeEnum;
use App\Services\Payroll\AttendanceCalculator;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function atWarmCache(): void
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
    atWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081-82', 'year_code' => '8182',
        'start_date' => '2024-07-17', 'end_date' => '2025-07-16', 'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'Attendance Co', 'code' => 'AT-'.uniqid(),
        'inventory_costing_method' => 'fifo',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id, 'name' => 'HR',
        'email' => 'at-'.uniqid().'@test.com', 'password' => bcrypt('secret'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);
});

function atSchedule(object $test, array $overrides = []): WorkSchedule
{
    return WorkSchedule::create(array_merge([
        'company_id' => $test->company->id,
        'name' => 'Default',
        'start_time' => '10:00',
        'end_time' => '17:00',
        'grace_minutes' => 15,
        'standard_hours_per_day' => 8,
        'overtime_multiplier' => 1.5,
        'overtime_enabled' => true,
        'is_default' => true,
    ], $overrides));
}

// ─── ATT-002: calculator ──────────────────────────────────────────────────────

it('computes worked hours, late minutes and overtime from a schedule', function () {
    $schedule = atSchedule($this); // 10:00–17:00, 15 min grace, 8h standard

    // In 10:30 (30 late, 15 grace → 15 late), out 19:00 → 8.5 worked → 0.5 OT.
    $result = app(AttendanceCalculator::class)->compute('10:30', '19:00', $schedule);

    expect($result['late_minutes'])->toBe(15)
        ->and($result['worked_hours'])->toBe(8.5)
        ->and($result['overtime_hours'])->toBe(0.5)
        ->and($result['early_leaving_minutes'])->toBe(0);
});

it('reports no late or overtime for an on-time full shift within grace', function () {
    $schedule = atSchedule($this);

    $result = app(AttendanceCalculator::class)->compute('10:10', '17:00', $schedule);

    expect($result['late_minutes'])->toBe(0)       // within 15-min grace
        ->and($result['worked_hours'])->toBe(6.83)
        ->and($result['overtime_hours'])->toBe(0.0);
});

// ─── ATT-002: model auto-tracking + auto LATE status ──────────────────────────

it('auto-populates tracking fields and flips PRESENT to LATE on save', function () {
    atSchedule($this);
    $employee = Employee::create([
        'company_id' => $this->company->id, 'employee_code' => 'AT-EMP-'.uniqid(),
        'first_name' => 'A', 'last_name' => 'B', 'join_date' => '2024-01-01', 'status' => 'active',
    ]);

    $attendance = Attendance::create([
        'company_id' => $this->company->id, 'employee_id' => $employee->id,
        'date' => '2024-08-08', 'check_in' => '11:00', 'check_out' => '19:00',
        'status' => AttendanceStatusEnum::PRESENT,
    ]);

    expect($attendance->status)->toBe(AttendanceStatusEnum::LATE)
        ->and($attendance->late_minutes)->toBe(45)   // 11:00 vs 10:15 grace
        ->and($attendance->overtime_hours)->toBe(0.0) // 8h worked, 8h standard
        ->and($attendance->worked_hours)->toBe(8.0);
});

// ─── ATT-003: overtime flows into payroll ─────────────────────────────────────

it('adds an overtime earning to payroll when the schedule enables it', function () {
    atSchedule($this); // overtime_enabled = true, 8h standard, 1.5x
    $employee = Employee::create([
        'company_id' => $this->company->id, 'employee_code' => 'AT-OT-'.uniqid(),
        'first_name' => 'O', 'last_name' => 'T', 'join_date' => '2024-01-01', 'status' => 'active',
    ]);

    $basic = SalaryComponent::create([
        'company_id' => $this->company->id, 'name' => 'Basic', 'is_basic' => true,
        'type' => SalaryComponentTypeEnum::EARNING, 'calculation_type' => 'fixed',
        'is_taxable' => false, 'is_active' => true,
    ]);
    $structure = SalaryStructure::create([
        'company_id' => $this->company->id, 'employee_id' => $employee->id,
        'effective_from' => '2024-01-01', 'is_active' => true,
    ]);
    SalaryStructureItem::create(['salary_structure_id' => $structure->id, 'salary_component_id' => $basic->id, 'amount' => 26_000, 'percentage' => 0]);

    // Present every working day; one day has 4h overtime (in 10:00, out 21:00 = 11h → 3h OT... use explicit).
    $current = Carbon::create(2024, 8, 1);
    $end = $current->copy()->endOfMonth();
    while ($current <= $end) {
        if ($current->dayOfWeek !== Carbon::SATURDAY) {
            Attendance::create([
                'company_id' => $this->company->id, 'employee_id' => $employee->id,
                'date' => $current->toDateString(), 'check_in' => '10:00', 'check_out' => '18:00',
                'status' => AttendanceStatusEnum::PRESENT,
            ]);
        }
        $current->addDay();
    }
    // Override one day to include 2h overtime (10:00–20:00 = 10h → 2h OT).
    Attendance::where('employee_id', $employee->id)->where('date', '2024-08-01')
        ->update(['overtime_hours' => 2]);

    $run = PayrollRun::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'month' => 8, 'status' => PayrollStatusEnum::DRAFT,
    ]);

    $payslip = app(PayrollService::class)->calculate($run)->payslips->first();

    // working days Aug 2024 = 26; hourly = 26000 / (26*8) = 125; OT = 2 * 125 * 1.5 = 375.
    $otItem = $payslip->items->firstWhere('component_name', 'Overtime');

    expect($otItem)->not->toBeNull()
        ->and((float) $otItem->amount)->toBe(375.0)
        ->and((float) $payslip->gross_salary)->toBe(26_375.0);
});

it('does not add overtime when the schedule disables it', function () {
    atSchedule($this, ['overtime_enabled' => false]);
    $employee = Employee::create([
        'company_id' => $this->company->id, 'employee_code' => 'AT-NOOT-'.uniqid(),
        'first_name' => 'N', 'last_name' => 'O', 'join_date' => '2024-01-01', 'status' => 'active',
    ]);
    $basic = SalaryComponent::create([
        'company_id' => $this->company->id, 'name' => 'Basic', 'is_basic' => true,
        'type' => SalaryComponentTypeEnum::EARNING, 'calculation_type' => 'fixed',
        'is_taxable' => false, 'is_active' => true,
    ]);
    $structure = SalaryStructure::create([
        'company_id' => $this->company->id, 'employee_id' => $employee->id,
        'effective_from' => '2024-01-01', 'is_active' => true,
    ]);
    SalaryStructureItem::create(['salary_structure_id' => $structure->id, 'salary_component_id' => $basic->id, 'amount' => 26_000, 'percentage' => 0]);

    Attendance::create([
        'company_id' => $this->company->id, 'employee_id' => $employee->id,
        'date' => '2024-08-01', 'status' => AttendanceStatusEnum::PRESENT, 'overtime_hours' => 5,
    ]);

    $run = PayrollRun::create([
        'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
        'month' => 8, 'status' => PayrollStatusEnum::DRAFT,
    ]);

    $payslip = app(PayrollService::class)->calculate($run)->payslips->first();

    expect($payslip->items->firstWhere('component_name', 'Overtime'))->toBeNull();
});

// ─── work schedule endpoint ───────────────────────────────────────────────────

it('returns and updates the company work schedule over HTTP', function () {
    $this->getJson('/api/admin/hr/work-schedule')
        ->assertOk()
        ->assertJsonPath('data.is_default', true);

    $this->putJson('/api/admin/hr/work-schedule', [
        'name' => 'Office', 'start_time' => '09:00', 'end_time' => '18:00',
        'grace_minutes' => 10, 'standard_hours_per_day' => 8,
        'overtime_multiplier' => 2, 'overtime_enabled' => true,
        'weekly_off_days' => [6],
    ])->assertOk()->assertJsonPath('data.overtime_multiplier', 2);

    expect(WorkSchedule::where('company_id', $this->company->id)->count())->toBe(1);
});
