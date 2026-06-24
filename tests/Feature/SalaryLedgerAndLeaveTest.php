<?php

use App\Models\User;
use App\Models\Company;
use App\Models\Payslip;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\FiscalYear;
use App\Models\PayrollRun;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Enums\LeaveStatusEnum;
use App\Services\TenantService;
use App\Enums\PayrollStatusEnum;
use App\Models\LeaveApplication;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

function slvWarmCache(): void
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
    slvWarmCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2081-82', 'year_code' => '8182',
        'start_date' => '2024-07-16', 'end_date' => '2025-07-15', 'is_current' => true,
    ]);

    $this->company = Company::create([
        'fiscal_year_id' => $this->fiscalYear->id,
        'company_name' => 'P4 Co', 'code' => 'P4-'.uniqid(),
        'inventory_costing_method' => 'fifo',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id, 'name' => 'HR',
        'email' => 'p4-'.uniqid().'@test.com', 'password' => bcrypt('secret'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($this->user, ['*'], 'admin');
    TenantService::setCompanyId($this->company->id);

    $this->employee = Employee::create([
        'company_id' => $this->company->id, 'employee_code' => 'P4-EMP-'.uniqid(),
        'first_name' => 'P', 'last_name' => 'Four', 'join_date' => '2024-01-01', 'status' => 'active',
    ]);
});

// ─── LEDGER-001: employee salary ledger ───────────────────────────────────────

it('returns a per-month salary ledger with YTD totals', function () {
    foreach ([[4, 30_000, 5_000, 1_000, 24_000], [5, 30_000, 5_000, 1_000, 24_000]] as [$bsMonth, $gross, $ded, $tds, $net]) {
        $run = PayrollRun::create([
            'company_id' => $this->company->id, 'fiscal_year_id' => $this->fiscalYear->id,
            'bs_year' => 2081, 'bs_month' => $bsMonth, 'month' => $bsMonth, 'status' => PayrollStatusEnum::PAID,
        ]);
        Payslip::create([
            'payroll_run_id' => $run->id, 'employee_id' => $this->employee->id,
            'working_days' => 26, 'present_days' => 26, 'gross_salary' => $gross,
            'total_deductions' => $ded, 'tds_amount' => $tds, 'net_salary' => $net,
        ]);
    }

    $res = $this->getJson("/api/admin/hr/employee/{$this->employee->id}/salary-ledger?fiscal_year_id={$this->fiscalYear->id}")
        ->assertOk();

    expect($res->json('rows'))->toHaveCount(2)
        ->and($res->json('totals.gross'))->toBe(60000)
        ->and($res->json('totals.net'))->toBe(48000)
        ->and($res->json('rows.0.period_label'))->toBe('Shrawan 2081')
        ->and($res->json('rows.1.ytd_net'))->toBe(48000);
});

// ─── LEAVE-004: overlap prevention ────────────────────────────────────────────

it('rejects a leave application overlapping an existing one', function () {
    $type = LeaveType::create(['company_id' => $this->company->id, 'name' => 'Annual', 'days_allowed' => 15]);

    LeaveApplication::create([
        'company_id' => $this->company->id, 'employee_id' => $this->employee->id, 'leave_type_id' => $type->id,
        'from_date' => '2024-08-05', 'to_date' => '2024-08-07', 'days' => 3, 'status' => LeaveStatusEnum::APPROVED,
    ]);

    $this->postJson('/api/admin/hr/leave-application', [
        'employee_id' => $this->employee->id, 'leave_type_id' => $type->id,
        'from_date' => '2024-08-06', 'to_date' => '2024-08-08', 'days' => 3,
    ])->assertUnprocessable();
});

// ─── LEAVE-003: balance enforcement at approval ───────────────────────────────

it('rejects approval that exceeds the leave-type annual allowance', function () {
    $type = LeaveType::create(['company_id' => $this->company->id, 'name' => 'Sick', 'days_allowed' => 5]);

    $first = LeaveApplication::create([
        'company_id' => $this->company->id, 'employee_id' => $this->employee->id, 'leave_type_id' => $type->id,
        'from_date' => '2024-08-05', 'to_date' => '2024-08-08', 'days' => 4, 'status' => LeaveStatusEnum::PENDING,
    ]);
    $second = LeaveApplication::create([
        'company_id' => $this->company->id, 'employee_id' => $this->employee->id, 'leave_type_id' => $type->id,
        'from_date' => '2024-08-20', 'to_date' => '2024-08-22', 'days' => 3, 'status' => LeaveStatusEnum::PENDING,
    ]);

    $this->postJson("/api/admin/hr/leave-application/{$first->id}/approve")->assertOk();
    $this->postJson("/api/admin/hr/leave-application/{$second->id}/approve")->assertUnprocessable();
});

it('allows approval within the allowance', function () {
    $type = LeaveType::create(['company_id' => $this->company->id, 'name' => 'Casual', 'days_allowed' => 10]);

    $app = LeaveApplication::create([
        'company_id' => $this->company->id, 'employee_id' => $this->employee->id, 'leave_type_id' => $type->id,
        'from_date' => '2024-08-05', 'to_date' => '2024-08-08', 'days' => 4, 'status' => LeaveStatusEnum::PENDING,
    ]);

    $this->postJson("/api/admin/hr/leave-application/{$app->id}/approve")->assertOk();

    expect($app->fresh()->status)->toBe(LeaveStatusEnum::APPROVED);
});
