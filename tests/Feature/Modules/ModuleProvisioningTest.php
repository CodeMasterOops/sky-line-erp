<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyModule;
use App\Models\CompanyCategory;
use App\Services\Modules\ModuleRegistry;
use Database\Seeders\CompanyCategorySeeder;
use App\Services\Modules\CompanyModuleService;
use App\Provisioning\Contracts\ModuleAwareStep;
use App\Provisioning\Contracts\ProvisioningStep;
use App\Services\Modules\ModuleActivationRunner;
use App\Provisioning\CompanyProvisioningPipeline;

/*
| Phase 2 — provisioning follows the module switch
| (docs/saas-modular-platform-and-gym-module-plan.md §3.7 point 4).
|
| A module's default data is created when the company runs the module: at
| provisioning if it is on from the start, or on activation if it is switched on
| later. Because enable → disable → enable is supported, every module step must
| be idempotent — asserted here rather than assumed.
*/

/**
 * A module-owned step that records how many times it ran.
 */
class SpyModuleStep implements ModuleAwareStep, ProvisioningStep
{
    public static int $runs = 0;

    public static array $created = [];

    public function name(): string
    {
        return 'SpyModule';
    }

    public function isIdempotent(): bool
    {
        return true;
    }

    public function module(): string
    {
        return 'crm';
    }

    public function run(Company $company, Branch $headOffice): void
    {
        self::$runs++;

        // firstOrCreate-style: a second run must not duplicate anything.
        self::$created[$company->id] = 'seeded';
    }
}

beforeEach(function () {
    SpyModuleStep::$runs = 0;
    SpyModuleStep::$created = [];

    $this->service = app(CompanyModuleService::class);
});

function runPipelineWithSpy(Company $company): void
{
    (new CompanyProvisioningPipeline([new SpyModuleStep]))->run($company);
}

it('runs a module step for a company that has the module on', function () {
    $company = makeCompany('Acme', 'ACME');
    $company->update([
        'company_category_id' => CompanyCategory::factory()->withModules(['crm'])->create()->id,
    ]);

    runPipelineWithSpy($company->refresh());

    expect(SpyModuleStep::$runs)->toBe(1);
});

it('skips a module step for a company that has the module off', function () {
    $company = makeCompany('Acme', 'ACME');
    $company->update([
        'company_category_id' => CompanyCategory::factory()->withModules(['accounting'])->create()->id,
    ]);

    runPipelineWithSpy($company->refresh());

    expect(SpyModuleStep::$runs)->toBe(0);
});

it('records the skip in the provision log', function () {
    $company = makeCompany('Acme', 'ACME');
    $company->update([
        'company_category_id' => CompanyCategory::factory()->withModules([])->create()->id,
    ]);

    runPipelineWithSpy($company->refresh());

    $log = App\Models\CompanyProvisionLog::query()->where('company_id', $company->id)->latest('id')->first();
    $entry = collect($log->step_results)->firstWhere('name', 'SpyModule');

    expect($entry['status'])->toBe('skipped')
        ->and($entry['error'])->toContain('[crm] module is not enabled');
});

it('never skips a core step', function () {
    $company = makeCompany('Acme', 'ACME');
    $company->update([
        'company_category_id' => CompanyCategory::factory()->withModules([])->create()->id,
    ]);

    $core = new class implements ProvisioningStep
    {
        public static int $runs = 0;

        public function name(): string
        {
            return 'CoreSpy';
        }

        public function isIdempotent(): bool
        {
            return true;
        }

        public function run(Company $company, Branch $headOffice): void
        {
            self::$runs++;
        }
    };

    (new CompanyProvisioningPipeline([$core]))->run($company->refresh());

    expect($core::$runs)->toBe(1);
});

it('gives every provisioned company explicit module rows', function () {
    $this->seed(CompanyCategorySeeder::class);

    $company = makeCompany('Acme', 'ACME');
    app(CompanyProvisioningPipeline::class)->run($company);

    $rows = CompanyModule::query()->where('company_id', $company->id)->get();

    expect($rows)->toHaveCount(count(app(ModuleRegistry::class)->keys()))
        ->and($company->fresh()->company_category_id)->not->toBeNull();
});

it('falls back to the default category when the company chose none', function () {
    $this->seed(CompanyCategorySeeder::class);

    $company = makeCompany('Acme', 'ACME');
    app(CompanyProvisioningPipeline::class)->run($company);

    expect($company->fresh()->category->slug)->toBe('general')
        ->and($this->service->enabledKeys($company->id))->toContain('crm');
});

it('keeps a category the company already picked', function () {
    $this->seed(CompanyCategorySeeder::class);

    $company = makeCompany('Fit Gym', 'FIT');
    $company->update(['company_category_id' => CompanyCategory::query()->where('slug', 'gym')->value('id')]);

    app(CompanyProvisioningPipeline::class)->run($company->refresh());

    expect($company->fresh()->category->slug)->toBe('gym');
});

it('runs a module\'s steps when it is enabled after provisioning', function () {
    config()->set('modules.crm.provisioning_steps', [SpyModuleStep::class]);
    app(ModuleRegistry::class)->flush();

    $company = makeCompany('Acme', 'ACME');
    $company->update([
        'company_category_id' => CompanyCategory::factory()->withModules([])->create()->id,
    ]);

    $this->service->enable($company->refresh(), 'crm');

    expect(SpyModuleStep::$runs)->toBe(1);
});

it('does not duplicate module data across an off/on cycle', function () {
    config()->set('modules.crm.provisioning_steps', [SpyModuleStep::class]);
    app(ModuleRegistry::class)->flush();

    $company = makeCompany('Acme', 'ACME');
    $company->update([
        'company_category_id' => CompanyCategory::factory()->withModules([])->create()->id,
    ]);
    $company->refresh();

    $this->service->enable($company, 'crm');
    $this->service->disable($company, 'crm');
    $this->service->enable($company, 'crm');

    // The step ran twice, but both runs are idempotent — that is exactly the
    // contract ModuleActivationRunner relies on.
    expect(SpyModuleStep::$runs)->toBe(2)
        ->and(SpyModuleStep::$created)->toHaveCount(1);
});

it('does not re-run a module step for a module that was already on', function () {
    config()->set('modules.crm.provisioning_steps', [SpyModuleStep::class]);
    app(ModuleRegistry::class)->flush();

    $company = makeCompany('Acme', 'ACME');
    $company->update([
        'company_category_id' => CompanyCategory::factory()->withModules(['crm'])->create()->id,
    ]);
    $company->refresh();

    $this->service->enable($company, 'crm');
    $this->service->enable($company, 'crm');

    expect(SpyModuleStep::$runs)->toBe(0);
});

it('activates the requirements pulled in alongside a module', function () {
    config()->set('modules.inventory.provisioning_steps', [SpyModuleStep::class]);
    app(ModuleRegistry::class)->flush();

    $company = makeCompany('Acme', 'ACME');
    $company->update([
        'company_category_id' => CompanyCategory::factory()->withModules([])->create()->id,
    ]);

    // sales requires inventory, so inventory's setup must run too.
    $this->service->enable($company->refresh(), 'sales');

    expect(SpyModuleStep::$runs)->toBe(1);
});

it('reports which steps an activation ran', function () {
    config()->set('modules.crm.provisioning_steps', [SpyModuleStep::class]);
    app(ModuleRegistry::class)->flush();

    $company = makeCompany('Acme', 'ACME');

    // The module's own step, plus the cross-module steps that must top up a
    // newly enabled module's share of the shared setup (RepeatsOnModuleChange).
    expect(app(ModuleActivationRunner::class)->activate($company, 'crm'))
        ->toBe(['SpyModule', 'DocumentSequences']);
});

it('seeds only the default data of the modules a company runs', function () {
    // Phase 5: 12 steps became ModuleAwareStep. A service business used to be
    // provisioned with departments, leave types, salary components and
    // manufacturing defaults it would never open.
    $company = makeCompany('Service Co', 'SVC');
    $company->update([
        'company_category_id' => CompanyCategory::factory()->withModules(['accounting', 'sales'])->create()->id,
    ]);

    app(CompanyProvisioningPipeline::class)->run($company->fresh());

    expect(App\Models\Department::withoutGlobalScopes()->where('company_id', $company->id)->exists())->toBeFalse()
        ->and(App\Models\LeaveType::withoutGlobalScopes()->where('company_id', $company->id)->exists())->toBeFalse()
        ->and(App\Models\Account::withoutGlobalScopes()->where('company_id', $company->id)->exists())->toBeTrue();
});

it('creates a document sequence only for the modules that issue the document', function () {
    $company = makeCompany('Sales Only', 'SALESONLY');
    $company->update([
        'company_category_id' => CompanyCategory::factory()->withModules(['accounting', 'inventory', 'sales'])->create()->id,
    ]);

    app(CompanyProvisioningPipeline::class)->run($company->fresh());

    $types = App\Models\DocumentSequence::withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->pluck('document_type')
        ->all();

    expect($types)
        ->toContain('invoice', 'grn', 'journal_voucher')
        ->not->toContain('bill', 'purchase_order', 'production_order');
});

it('fills in a module\'s missing sequences when it is switched on later', function () {
    $company = makeCompany('Grows Into Purchase', 'GROWS');
    $company->update([
        'company_category_id' => CompanyCategory::factory()->withModules(['accounting', 'inventory', 'sales'])->create()->id,
    ]);

    app(CompanyProvisioningPipeline::class)->run($company->fresh());

    $before = App\Models\DocumentSequence::withoutGlobalScopes()->where('company_id', $company->id)->count();

    app(CompanyModuleService::class)->enable($company->fresh(), 'purchase');
    app(App\Services\Modules\ModuleActivationRunner::class)->activate($company->fresh(), 'purchase');

    $types = App\Models\DocumentSequence::withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->pluck('document_type')
        ->all();

    expect($types)->toContain('bill', 'purchase_order')
        // Idempotent: the sales sequences are not duplicated by the replay.
        ->and(count($types))->toBeGreaterThan($before)
        ->and(count($types))->toBe(count(array_unique($types)));
});
