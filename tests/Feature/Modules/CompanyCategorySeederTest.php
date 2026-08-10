<?php

use App\Models\CompanyCategory;
use App\Services\Modules\ModuleRegistry;
use Database\Seeders\CompanyCategorySeeder;
use App\Services\Modules\CompanyModuleService;

/*
| Phase 1 — the industry catalogue (config/company_categories.php).
|
| The catalogue is seeded once and then edited by the Super Admin from Phase 4,
| so re-seeding must never overwrite those edits. Every category's default set
| must also be dependency-closed, otherwise a company would be provisioned with
| a module the resolver immediately switches off again.
*/

beforeEach(function () {
    $this->seed(CompanyCategorySeeder::class);
});

it('seeds the configured catalogue', function () {
    expect(CompanyCategory::query()->count())->toBe(count(config('company_categories')))
        ->and(CompanyCategory::query()->where('slug', 'gym')->exists())->toBeTrue();
});

it('has exactly one default category for companies that pick nothing', function () {
    expect(CompanyCategory::query()->default()->count())->toBe(1)
        ->and(CompanyCategory::query()->default()->sole()->slug)->toBe('general');
});

it('only lists modules the registry knows about', function () {
    $registry = app(ModuleRegistry::class);

    foreach (config('company_categories') as $definition) {
        $unknown = array_diff($definition['modules'], $registry->keys());

        expect($unknown)->toBe([], "Category [{$definition['slug']}] lists unknown modules");
    }
});

it('keeps every category dependency-closed', function () {
    // A category listing `sales` without `inventory` would provision a company
    // whose sales module is switched straight back off by the closure rule.
    $service = app(CompanyModuleService::class);

    foreach (config('company_categories') as $definition) {
        $missing = array_diff($service->closure($definition['modules']), $definition['modules']);

        expect($missing)->toBe([], "Category [{$definition['slug']}] is missing required modules: ".implode(', ', $missing));
    }
});

it('never lists core, which is always on anyway', function () {
    foreach (config('company_categories') as $definition) {
        expect($definition['modules'])->not->toContain(ModuleRegistry::CORE_KEY);
    }
});

it('attaches the configured default modules to each category', function () {
    $retail = CompanyCategory::query()->where('slug', 'retail')->with('modules')->sole();

    expect($retail->defaultModuleKeys())->toEqualCanonicalizing(config('company_categories.1.modules'))
        ->and($retail->defaultModuleKeys())->toContain('pos');
});

it('is idempotent and does not duplicate categories or their modules', function () {
    $categories = CompanyCategory::query()->count();
    $modules = CompanyCategory::query()->where('slug', 'retail')->sole()->modules()->count();

    $this->seed(CompanyCategorySeeder::class);

    expect(CompanyCategory::query()->count())->toBe($categories)
        ->and(CompanyCategory::query()->where('slug', 'retail')->sole()->modules()->count())->toBe($modules);
});

it('does not overwrite super admin edits when re-seeded', function () {
    $retail = CompanyCategory::query()->where('slug', 'retail')->sole();
    $retail->update(['name' => 'Retail (renamed)', 'is_active' => false]);

    $this->seed(CompanyCategorySeeder::class);

    expect($retail->fresh()->name)->toBe('Retail (renamed)')
        ->and($retail->fresh()->is_active)->toBeFalse();
});

it('gives a gym company the modules the gym vertical will build on', function () {
    // The `gym` module itself arrives in Phase 5; the category already ships the
    // ERP foundations it reuses (invoicing, products, parties).
    $company = makeCompany('Fit Gym', 'FIT');
    $company->update(['company_category_id' => CompanyCategory::query()->where('slug', 'gym')->value('id')]);

    expect(app(CompanyModuleService::class)->enabledKeys($company->id))
        ->toEqualCanonicalizing(['core', 'accounting', 'inventory', 'sales', 'purchase', 'crm']);
});
