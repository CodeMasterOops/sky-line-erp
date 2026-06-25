<?php

use App\Models\Tax;
use App\Models\Branch;
use App\Models\Account;
use App\Models\Company;
use App\Models\Warehouse;
use App\Models\FiscalYear;
use App\Models\PaymentMode;
use App\Models\AccountGroup;
use App\Models\AccountSetting;
use App\Models\AccountingPeriod;
use App\Jobs\ProvisionCompanyJob;
use App\Models\CompanyProvisionLog;
use Illuminate\Support\Facades\Queue;
use App\Services\Accounting\CoaInsertService;
use App\Provisioning\CompanyProvisioningPipeline;

beforeEach(function () {
    warmAllTablesCache();

    $this->fiscalYear = FiscalYear::create([
        'year_name' => '2026',
        'year_code' => '26',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'is_current' => true,
    ]);

    $this->company = Company::create([
        'company_name' => 'Provision Test Co',
        'code' => 'PTC',
        'email' => 'prov@test.com',
    ]);
});

// ---------------------------------------------------------------------------
// Pipeline orchestration
// ---------------------------------------------------------------------------

it('creates a provision log entry when the pipeline runs', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $log = CompanyProvisionLog::where('company_id', $this->company->id)->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe('complete')
        ->and($log->step_results)->toBeArray()->not->toBeEmpty()
        ->and($log->started_at)->not->toBeNull()
        ->and($log->completed_at)->not->toBeNull();
});

it('records every step name in the provision log', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $log = CompanyProvisionLog::where('company_id', $this->company->id)->first();
    $stepNames = collect($log->step_results)->pluck('name')->all();

    expect($stepNames)->toContain('FiscalYear')
        ->toContain('BranchAndWarehouse')
        ->toContain('ChartOfAccounts')
        ->toContain('AccountSettings')
        ->toContain('AccountingPeriods')
        ->toContain('TaxConfig')
        ->toContain('PaymentModes')
        ->toContain('Catalog');
});

it('marks every step as complete in the provision log', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $log = CompanyProvisionLog::where('company_id', $this->company->id)->first();
    $failedSteps = collect($log->step_results)->where('status', 'failed')->all();

    expect($failedSteps)->toBeEmpty();
});

it('does not set onboarding_completed_at during provisioning — that is the OnboardingController job', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $this->company->refresh();
    expect($this->company->onboarding_completed_at)->toBeNull();
});

// ---------------------------------------------------------------------------
// Idempotency
// ---------------------------------------------------------------------------

it('skips the entire pipeline if a complete provision log already exists', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $beforeCount = AccountGroup::where('company_id', $this->company->id)->count();

    CompanyProvisioningPipeline::make()->run($this->company);

    expect(CompanyProvisionLog::where('company_id', $this->company->id)->count())->toBe(1)
        ->and(AccountGroup::where('company_id', $this->company->id)->count())->toBe($beforeCount);
});

it('does not create duplicate payment modes when run twice via direct pipeline call', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $log = CompanyProvisionLog::where('company_id', $this->company->id)->first();
    $log->update(['status' => 'failed']); // allow second run

    CompanyProvisioningPipeline::make()->run($this->company);

    $modes = PaymentMode::where('company_id', $this->company->id)->pluck('name');
    expect($modes->count())->toBe($modes->unique()->count());
});

it('does not create duplicate branches when run twice', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $log = CompanyProvisionLog::where('company_id', $this->company->id)->first();
    $log->update(['status' => 'failed']);

    CompanyProvisioningPipeline::make()->run($this->company);

    expect(Branch::where('company_id', $this->company->id)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// FiscalYearStep
// ---------------------------------------------------------------------------

it('assigns the current fiscal year to the company', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $this->company->refresh();
    expect($this->company->fiscal_year_id)->toBe($this->fiscalYear->id);
});

it('does not overwrite an existing fiscal year on the company', function () {
    $this->company->update(['fiscal_year_id' => $this->fiscalYear->id]);

    CompanyProvisioningPipeline::make()->run($this->company);

    $this->company->refresh();
    expect($this->company->fiscal_year_id)->toBe($this->fiscalYear->id);
});

// ---------------------------------------------------------------------------
// BranchAndWarehouseStep
// ---------------------------------------------------------------------------

it('creates the default head-office branch', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $branch = Branch::where('company_id', $this->company->id)->first();

    expect($branch)->not->toBeNull()
        ->and($branch->is_head_office)->toBeTrue()
        ->and($branch->code)->toBe(config('company_bootstrap.default_branch.code'));
});

it('creates the default warehouse linked to the head-office branch', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $branch = Branch::where('company_id', $this->company->id)->first();

    // Query by code to avoid ambiguity with additional warehouses created by CatalogStep.
    $warehouse = Warehouse::where('company_id', $this->company->id)
        ->where('code', config('company_bootstrap.default_warehouse.code'))
        ->first();

    expect($warehouse)->not->toBeNull()
        ->and($warehouse->branch_id)->toBe($branch->id);
});

// ---------------------------------------------------------------------------
// ChartOfAccountsStep / CoaInsertService — fixed idempotency guard
// ---------------------------------------------------------------------------

it('inserts chart of accounts for the company', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    expect(AccountGroup::where('company_id', $this->company->id)->count())->toBeGreaterThan(0)
        ->and(Account::where('company_id', $this->company->id)->count())->toBeGreaterThan(0);
});

it('does not re-import COA when all root groups already exist', function () {
    (new CoaInsertService($this->company))->saveCoaData();
    $countBefore = AccountGroup::where('company_id', $this->company->id)->count();

    (new CoaInsertService($this->company))->saveCoaData();

    expect(AccountGroup::where('company_id', $this->company->id)->count())->toBe($countBefore);
});

it('still imports COA when only a single manually-created root group exists', function () {
    AccountGroup::create([
        'company_id' => $this->company->id,
        'name' => 'Manually Created',
        'code' => 'MAN',
        'account_type' => 'asset',
        'parent_id' => null,
    ]);

    $countBefore = AccountGroup::where('company_id', $this->company->id)->count();

    (new CoaInsertService($this->company))->saveCoaData();

    expect(AccountGroup::where('company_id', $this->company->id)->count())->toBeGreaterThan($countBefore);
});

// ---------------------------------------------------------------------------
// AccountingPeriodsStep
// ---------------------------------------------------------------------------

it('generates accounting periods for the assigned fiscal year', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    expect(AccountingPeriod::where('company_id', $this->company->id)->count())->toBeGreaterThan(0);
});

// ---------------------------------------------------------------------------
// TaxConfigStep
// ---------------------------------------------------------------------------

it('seeds system taxes including VAT 13% for the company', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $taxes = Tax::where('company_id', $this->company->id)->where('is_system', true)->get();

    expect($taxes)->not->toBeEmpty()
        ->and($taxes->pluck('name')->contains('VAT 13%'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// PaymentModesStep
// ---------------------------------------------------------------------------

it('creates all configured default payment modes', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $modes = PaymentMode::where('company_id', $this->company->id)->pluck('name');
    $configured = collect(config('company_bootstrap.default_payment_modes'))->pluck('name');

    foreach ($configured as $name) {
        expect($modes->contains($name))->toBeTrue("Missing payment mode: {$name}");
    }
});

// ---------------------------------------------------------------------------
// AccountSettingsStep
// ---------------------------------------------------------------------------

it('creates account settings that link to provisioned GL accounts', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $settings = AccountSetting::where('company_id', $this->company->id)->first();

    expect($settings)->not->toBeNull()
        ->and($settings->sales_account_id)->not->toBeNull()
        ->and($settings->cash_sales_account_id)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Observer — ProvisionCompanyJob dispatched on Company::created
// ---------------------------------------------------------------------------

it('dispatches ProvisionCompanyJob when a new company is created via observer', function () {
    Queue::fake();

    // Register the observer inline — it is intentionally NOT global to avoid
    // auto-provisioning in unrelated tests that create companies.
    \Illuminate\Support\Facades\Event::listen(
        'eloquent.created: '.Company::class,
        [\App\Observers\CompanyObserver::class, 'created']
    );

    Company::create([
        'company_name' => 'Observer Test Co',
        'code' => 'OTC-'.uniqid(),
        'email' => 'obs@test.com',
    ]);

    Queue::assertPushed(ProvisionCompanyJob::class);
});

// ---------------------------------------------------------------------------
// Artisan command
// ---------------------------------------------------------------------------

it('provisions a company successfully via the artisan command', function () {
    $this->artisan('company:provision', ['company_id' => $this->company->id])
        ->assertExitCode(0);

    expect(CompanyProvisionLog::where('company_id', $this->company->id)
        ->where('status', 'complete')
        ->exists()
    )->toBeTrue();
});

it('returns failure exit code for a non-existent company id', function () {
    $this->artisan('company:provision', ['company_id' => 99999])
        ->assertExitCode(1);
});

it('re-runs provisioning when --force flag is used', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $this->artisan('company:provision', ['company_id' => $this->company->id, '--force' => true])
        ->assertExitCode(0);

    expect(CompanyProvisionLog::where('company_id', $this->company->id)->count())->toBe(2);
});
