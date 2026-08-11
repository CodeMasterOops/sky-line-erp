<?php

use App\Models\Role;
use App\Models\Company;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\Department;
use App\Models\FiscalYear;
use App\Models\Designation;
use App\Models\WorkSchedule;
use App\Models\SalaryComponent;
use App\Models\DocumentSequence;
use App\Models\CompanyProvisionLog;
use App\Provisioning\CompanyProvisioningPipeline;

beforeEach(function () {
    warmAllTablesCache();

    FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_current' => true,
    ]);

    $this->company = Company::create([
        'company_name' => 'Phase Two Test Co',
        'code' => 'P2TC',
        'email' => 'phase2@test.com',
        'company_category_id' => categoryWithEveryModule()->id,
    ]);
});

/*
 * These steps became module-aware in the capping plan's Phase 5, so the company
 * under test declares an industry that runs everything — otherwise the default
 * "General Business" category would (correctly) skip HR, payroll and
 * manufacturing, and these assertions would be testing the skip rather than the
 * step. The skip itself is covered by ModuleProvisioningTest.
 */
function categoryWithEveryModule(): App\Models\CompanyCategory
{
    return App\Models\CompanyCategory::factory()
        ->withModules(app(App\Services\Modules\ModuleRegistry::class)->togglableKeys())
        ->create();
}

// ---------------------------------------------------------------------------
// RolesAndPermissionsStep
// ---------------------------------------------------------------------------

it('creates all configured default roles for the company', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $roleNames = Role::where('company_id', $this->company->id)->pluck('name');
    $configured = collect(config('provisioning.roles'))->pluck('name');

    foreach ($configured as $name) {
        expect($roleNames->contains($name))->toBeTrue("Missing role: {$name}");
    }
});

it('stores permissions on provisioned roles', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $adminRole = Role::where('company_id', $this->company->id)
        ->where('name', 'Administrator')
        ->first();

    expect($adminRole)->not->toBeNull()
        ->and($adminRole->permissions)->toContain('*');
});

it('does not create duplicate roles when pipeline runs twice', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    CompanyProvisionLog::where('company_id', $this->company->id)->update(['status' => 'failed']);

    CompanyProvisioningPipeline::make()->run($this->company);

    $configuredCount = count(config('provisioning.roles'));
    expect(Role::where('company_id', $this->company->id)->count())->toBe($configuredCount);
});

// ---------------------------------------------------------------------------
// DepartmentsStep
// ---------------------------------------------------------------------------

it('creates all configured departments for the company', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $codes = Department::where('company_id', $this->company->id)->pluck('code');
    $configured = collect(config('provisioning.hr.departments'))->pluck('code');

    foreach ($configured as $code) {
        expect($codes->contains($code))->toBeTrue("Missing department: {$code}");
    }
});

it('does not create duplicate departments when pipeline runs twice', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    CompanyProvisionLog::where('company_id', $this->company->id)->update(['status' => 'failed']);

    CompanyProvisioningPipeline::make()->run($this->company);

    expect(Department::where('company_id', $this->company->id)->count())
        ->toBe(count(config('provisioning.hr.departments')));
});

// ---------------------------------------------------------------------------
// DesignationsStep
// ---------------------------------------------------------------------------

it('creates all configured designations for the company', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $names = Designation::where('company_id', $this->company->id)->pluck('name');
    $configured = collect(config('provisioning.hr.designations'))->pluck('name');

    foreach ($configured as $name) {
        expect($names->contains($name))->toBeTrue("Missing designation: {$name}");
    }
});

// ---------------------------------------------------------------------------
// LeaveTypesStep
// ---------------------------------------------------------------------------

it('creates all configured leave types for the company', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $names = LeaveType::where('company_id', $this->company->id)->pluck('name');
    $configured = collect(config('provisioning.hr.leave_types'))->pluck('name');

    foreach ($configured as $name) {
        expect($names->contains($name))->toBeTrue("Missing leave type: {$name}");
    }
});

it('creates paid and unpaid leave types correctly', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $annualLeave = LeaveType::where('company_id', $this->company->id)
        ->where('name', 'Annual Leave')
        ->first();

    $unpaidLeave = LeaveType::where('company_id', $this->company->id)
        ->where('name', 'Unpaid Leave')
        ->first();

    expect($annualLeave)->not->toBeNull()
        ->and($annualLeave->is_paid)->toBeTrue()
        ->and($unpaidLeave)->not->toBeNull()
        ->and($unpaidLeave->is_paid)->toBeFalse();
});

// ---------------------------------------------------------------------------
// WorkScheduleStep
// ---------------------------------------------------------------------------

it('creates the default work schedule for the company', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $schedule = WorkSchedule::where('company_id', $this->company->id)->first();

    expect($schedule)->not->toBeNull()
        ->and($schedule->is_default)->toBeTrue()
        ->and($schedule->weekly_off_days)->toContain(6);
});

it('does not create duplicate work schedules when pipeline runs twice', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    CompanyProvisionLog::where('company_id', $this->company->id)->update(['status' => 'failed']);

    CompanyProvisioningPipeline::make()->run($this->company);

    expect(WorkSchedule::where('company_id', $this->company->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// HolidaysStep
// ---------------------------------------------------------------------------

it('seeds public holidays for the current year via spatie/holidays fallback', function () {
    // 2026 is not yet in spatie/holidays lookup tables, so the step falls back
    // to the static config list.
    CompanyProvisioningPipeline::make()->run($this->company);

    $count = Holiday::where('company_id', $this->company->id)
        ->whereYear('date', now()->year)
        ->count();

    expect($count)->toBe(count(config('provisioning.hr.public_holidays')));
});

it('seeds more holidays via spatie/holidays when the year is fully covered', function () {
    // 2024 has rich BS/lunar holiday data in spatie/holidays (Dashain, Tihar, etc.)
    \Carbon\Carbon::setTestNow('2024-01-01');

    CompanyProvisioningPipeline::make()->run($this->company);

    $count = Holiday::where('company_id', $this->company->id)
        ->whereYear('date', 2024)
        ->count();

    // spatie/holidays Nepal 2024 returns significantly more than the static fallback list
    expect($count)->toBeGreaterThan(count(config('provisioning.hr.public_holidays')));

    \Carbon\Carbon::setTestNow();
});

it('does not seed duplicate holidays when pipeline runs twice', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    CompanyProvisionLog::where('company_id', $this->company->id)->update(['status' => 'failed']);

    CompanyProvisioningPipeline::make()->run($this->company);

    $count = Holiday::where('company_id', $this->company->id)->whereYear('date', now()->year)->count();

    expect($count)->toBe(count(config('provisioning.hr.public_holidays')));
});

// ---------------------------------------------------------------------------
// SalaryComponentsStep
// ---------------------------------------------------------------------------

it('creates all configured salary components for the company', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $systemCodes = SalaryComponent::where('company_id', $this->company->id)->pluck('system_code');
    $configured = collect(config('provisioning.hr.salary_components'))->pluck('system_code');

    foreach ($configured as $code) {
        expect($systemCodes->contains($code))->toBeTrue("Missing salary component: {$code}");
    }
});

it('creates the basic salary component correctly', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $basic = SalaryComponent::where('company_id', $this->company->id)
        ->where('system_code', 'BASIC')
        ->first();

    expect($basic)->not->toBeNull()
        ->and($basic->is_basic)->toBeTrue()
        ->and($basic->is_system)->toBeTrue()
        ->and($basic->type->value)->toBe('earning');
});

it('does not create duplicate salary components when pipeline runs twice', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    CompanyProvisionLog::where('company_id', $this->company->id)->update(['status' => 'failed']);

    CompanyProvisioningPipeline::make()->run($this->company);

    expect(SalaryComponent::where('company_id', $this->company->id)->count())
        ->toBe(count(config('provisioning.hr.salary_components')));
});

// ---------------------------------------------------------------------------
// DocumentSequencesStep
// ---------------------------------------------------------------------------

it('creates document sequences for all configured document types', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $documentTypes = DocumentSequence::where('company_id', $this->company->id)->pluck('document_type');
    $configured = collect(config('provisioning.document_sequences'))->pluck('document_type');

    foreach ($configured as $type) {
        expect($documentTypes->contains($type))->toBeTrue("Missing document sequence for: {$type}");
    }
});

it('creates invoice sequence with correct prefix', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $invoiceSeq = DocumentSequence::where('company_id', $this->company->id)
        ->where('document_type', 'invoice')
        ->first();

    expect($invoiceSeq)->not->toBeNull()
        ->and($invoiceSeq->prefix)->toBe('INV-')
        ->and($invoiceSeq->reset_yearly)->toBeTrue();
});

it('does not create duplicate document sequences when pipeline runs twice', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    CompanyProvisionLog::where('company_id', $this->company->id)->update(['status' => 'failed']);

    CompanyProvisioningPipeline::make()->run($this->company);

    expect(DocumentSequence::where('company_id', $this->company->id)->count())
        ->toBe(count(config('provisioning.document_sequences')));
});

// ---------------------------------------------------------------------------
// Provision log contains all Phase 2 step names
// ---------------------------------------------------------------------------

it('records all Phase 2 step names in the provision log', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $log = CompanyProvisionLog::where('company_id', $this->company->id)->first();
    $stepNames = collect($log->step_results)->pluck('name')->all();

    expect($stepNames)
        ->toContain('RolesAndPermissions')
        ->toContain('Departments')
        ->toContain('Designations')
        ->toContain('LeaveTypes')
        ->toContain('WorkSchedule')
        ->toContain('Holidays')
        ->toContain('SalaryComponents')
        ->toContain('DocumentSequences');
});
