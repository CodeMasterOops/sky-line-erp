<?php

use App\Models\Tag;
use App\Models\User;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Setting;
use App\Models\FiscalYear;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\CompanyProvisionLog;
use App\Providers\ProvisioningServiceProvider;
use App\Provisioning\Contracts\ProvisioningStep;
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
        'company_name' => 'Phase Four Test Co',
        'code' => 'P4TC',
        'email' => 'phase4@test.com',
    ]);

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Phase4 Admin',
        'email' => 'p4admin@test.com',
        'password' => bcrypt('password'),
        'user_type' => UserTypeEnum::ADMIN,
    ]);
});

// ---------------------------------------------------------------------------
// CrmDefaultsStep
// ---------------------------------------------------------------------------

it('seeds CRM lead source tags for the company', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $tagNames = Tag::where('company_id', $this->company->id)->pluck('name');
    $configured = collect(config('provisioning.crm.lead_source_tags'))->pluck('name');

    foreach ($configured as $name) {
        expect($tagNames->contains($name))->toBeTrue("Missing CRM tag: {$name}");
    }
});

it('does not create duplicate CRM tags when pipeline runs twice', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    CompanyProvisionLog::where('company_id', $this->company->id)->update(['status' => 'failed']);

    CompanyProvisioningPipeline::make()->run($this->company);

    expect(Tag::where('company_id', $this->company->id)->count())
        ->toBe(count(config('provisioning.crm.lead_source_tags')));
});

// ---------------------------------------------------------------------------
// ManufacturingDefaultsStep
// ---------------------------------------------------------------------------

it('seeds manufacturing preference settings for the company', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $configured = config('provisioning.manufacturing');

    foreach (array_keys($configured) as $key) {
        $settingKey = "company.{$this->company->id}.manufacturing.{$key}";
        expect(Setting::where('key', $settingKey)->exists())
            ->toBeTrue("Missing manufacturing setting: {$settingKey}");
    }
});

it('seeds the default cost basis as standard_cost', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $setting = Setting::where('key', "company.{$this->company->id}.manufacturing.default_cost_basis")->first();

    expect($setting)->not->toBeNull()
        ->and($setting->value)->toBe('standard_cost');
});

// ---------------------------------------------------------------------------
// extend() pattern
// ---------------------------------------------------------------------------

it('runs extended steps registered via ProvisioningServiceProvider::extend()', function () {
    $ran = false;

    $customStep = new class($ran) implements ProvisioningStep
    {
        public function __construct(private bool &$ran) {}

        public function name(): string
        {
            return 'CustomTestStep';
        }

        public function isIdempotent(): bool
        {
            return true;
        }

        public function run(Company $company, Branch $headOffice): void
        {
            $this->ran = true;
        }
    };

    ProvisioningServiceProvider::extend($customStep);

    // Re-resolve steps so extension takes effect
    app()->forgetInstance('provisioning.steps');

    CompanyProvisioningPipeline::make()->run($this->company);

    expect($ran)->toBeTrue();

    // Cleanup extension to avoid polluting other tests
    (new \ReflectionProperty(ProvisioningServiceProvider::class, 'extensions'))->setValue(null, []);
    app()->forgetInstance('provisioning.steps');
});

it('records extended step name in the provision log', function () {
    $customStep = new class implements ProvisioningStep
    {
        public function name(): string
        {
            return 'MarketingDefaults';
        }

        public function isIdempotent(): bool
        {
            return true;
        }

        public function run(Company $company, Branch $headOffice): void {}
    };

    ProvisioningServiceProvider::extend($customStep);
    app()->forgetInstance('provisioning.steps');

    CompanyProvisioningPipeline::make()->run($this->company);

    $log = CompanyProvisionLog::where('company_id', $this->company->id)->first();
    $stepNames = collect($log->step_results)->pluck('name')->all();

    expect($stepNames)->toContain('MarketingDefaults');

    // Cleanup
    (new \ReflectionProperty(ProvisioningServiceProvider::class, 'extensions'))->setValue(null, []);
    app()->forgetInstance('provisioning.steps');
});

// ---------------------------------------------------------------------------
// Security — company isolation
// ---------------------------------------------------------------------------

it('does not return another company provision log via the status endpoint', function () {
    $otherCompany = Company::create([
        'company_name' => 'Other Co',
        'code' => 'OTH',
        'email' => 'other@test.com',
    ]);

    CompanyProvisioningPipeline::make()->run($otherCompany);

    Sanctum::actingAs($this->user, ['*'], 'admin');

    $response = $this->getJson('/api/admin/provision/status');

    // Should return not_started for $this->company, not the other company's log
    $response->assertOk()->assertJson(['status' => 'not_started']);
});

it('super-admin provision log endpoint is scoped to requested company only', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $otherCompany = Company::create([
        'company_name' => 'Isolated Co',
        'code' => 'ISO',
        'email' => 'iso@test.com',
    ]);

    actingAsSuperAdmin();

    $response = $this->getJson("/api/super-admin/company/{$otherCompany->id}/provision-log");

    $response->assertOk()
        ->assertJson(['company_id' => $otherCompany->id]);

    // Logs from $this->company must not appear
    expect($response->json('logs'))->toBeEmpty();
});

// ---------------------------------------------------------------------------
// Performance — full pipeline < 5 seconds
// ---------------------------------------------------------------------------

it('completes full provisioning pipeline in under 5 seconds', function () {
    $company2 = Company::create([
        'company_name' => 'Speed Test Co',
        'code' => 'SPC',
        'email' => 'speed@test.com',
    ]);

    $start = microtime(true);
    CompanyProvisioningPipeline::make()->run($company2);
    $elapsed = microtime(true) - $start;

    expect($elapsed)->toBeLessThan(5.0);
})->group('performance');

// ---------------------------------------------------------------------------
// Phase 4 steps in provision log
// ---------------------------------------------------------------------------

it('records Phase 4 step names in the provision log', function () {
    CompanyProvisioningPipeline::make()->run($this->company);

    $log = CompanyProvisionLog::where('company_id', $this->company->id)->first();
    $stepNames = collect($log->step_results)->pluck('name')->all();

    expect($stepNames)
        ->toContain('CrmDefaults')
        ->toContain('ManufacturingDefaults');
});
