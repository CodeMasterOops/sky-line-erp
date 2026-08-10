<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\CompanyModule;
use App\Enums\ModuleSourceEnum;
use App\Models\CompanyCategory;
use App\Services\Modules\ModuleRegistry;
use App\Services\Modules\CompanyModuleService;

/*
| Phase 1 — the resolution precedence matrix
| (docs/saas-modular-platform-and-gym-module-plan.md §3.5).
|
|   1. core floor  2. explicit row  3. category default
|   4. registry default  5. plan cap  6. dependency closure
|
| Nothing in this file enables enforcement; it only pins down what "enabled"
| means for a company, which Phase 2's middleware then acts on.
*/

beforeEach(function () {
    $this->service = app(CompanyModuleService::class);
    $this->company = makeCompany('Acme Fitness', 'ACME');
});

function enableRow(int $companyId, string $moduleKey, bool $enabled = true, ModuleSourceEnum $source = ModuleSourceEnum::Manual): CompanyModule
{
    return CompanyModule::query()->create([
        'company_id' => $companyId,
        'module_key' => $moduleKey,
        'is_enabled' => $enabled,
        'source' => $source,
    ]);
}

function attachCategory(App\Models\Company $company, array $moduleKeys): CompanyCategory
{
    $category = CompanyCategory::factory()->withModules($moduleKeys)->create();

    $company->update(['company_category_id' => $category->id]);
    $company->unsetRelation('category')->refresh();

    return $category;
}

function subscribeTo(App\Models\Company $company, ?array $modules): Plan
{
    $plan = Plan::factory()->create(['modules' => $modules]);

    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
    ]);

    return $plan;
}

it('gives a company nobody has configured the whole pre-modular ERP', function () {
    // No rows, no category: the company predates modularity (or was created
    // outside the provisioning pipeline). Locking it out of the ERP it was
    // already using would be the one unacceptable outcome, so it keeps
    // everything — bar the industry verticals, which it never had.
    $registry = app(ModuleRegistry::class);
    $expected = array_values(array_diff($registry->keys(), array_keys($registry->grouped()['industry'] ?? [])));

    expect($this->service->enabledKeys($this->company->id))->toEqualCanonicalizing($expected)
        ->and($this->service->isEnabled('core', $this->company->id))->toBeTrue()
        ->and($this->service->resolve($this->company->id)['sales']['source'])->toBe(ModuleSourceEnum::Unconfigured->value);
});

it('never hands an unconfigured company an industry vertical', function () {
    foreach (array_keys(app(ModuleRegistry::class)->grouped()['industry'] ?? []) as $key) {
        expect($this->service->isEnabled($key, $this->company->id))->toBeFalse();
    }
})->skip(fn (): bool => (app(ModuleRegistry::class)->grouped()['industry'] ?? []) === [], 'No industry module has shipped yet.');

it('still applies the plan cap to an unconfigured company', function () {
    subscribeTo($this->company, ['core', 'accounting']);

    expect($this->service->enabledKeys($this->company->id))->toEqualCanonicalizing(['core', 'accounting']);
});

it('stops falling back as soon as the company has a category', function () {
    attachCategory($this->company, ['crm']);

    expect($this->service->enabledKeys($this->company->id))->toEqualCanonicalizing(['core', 'crm'])
        ->and($this->service->resolve($this->company->id)['sales']['reason'])->toBe('Not enabled for this company.');
});

it('stops falling back as soon as the company has one explicit row', function () {
    enableRow($this->company->id, 'crm');

    expect($this->service->enabledKeys($this->company->id))->toEqualCanonicalizing(['core', 'crm']);
});

it('falls back to the default category when the company picked none', function () {
    CompanyCategory::factory()->default()->withModules(['hr'])->create();

    expect($this->service->enabledKeys($this->company->id))->toEqualCanonicalizing(['core', 'hr']);
});

it('refuses to resolve an unknown module key', function () {
    expect($this->service->isEnabled('not-a-module', $this->company->id))->toBeFalse();
});

it('enables a category default and everything it requires', function () {
    attachCategory($this->company, ['sales']);

    // sales requires accounting + inventory, so the closure pulls both in.
    expect($this->service->enabledKeys($this->company->id))
        ->toEqualCanonicalizing(['core', 'accounting', 'inventory', 'sales'])
        ->and($this->service->resolve($this->company->id)['sales']['source'])->toBe(ModuleSourceEnum::Category->value);
});

it('lets an explicit row override a category default', function () {
    attachCategory($this->company, ['crm']);
    enableRow($this->company->id, 'crm', enabled: false);

    expect($this->service->isEnabled('crm', $this->company->id))->toBeFalse()
        ->and($this->service->resolve($this->company->id)['crm']['reason'])->toBe('Switched off for this company.');
});

it('lets an explicit row enable a module the category does not include', function () {
    attachCategory($this->company, ['crm']);
    enableRow($this->company->id, 'hr');

    expect($this->service->enabledKeys($this->company->id))->toContain('hr');
});

it('caps a category default with the plan entitlement', function () {
    attachCategory($this->company, ['crm', 'hr']);
    subscribeTo($this->company, ['core', 'crm']);

    $states = $this->service->resolve($this->company->id);

    expect($states['crm']['enabled'])->toBeTrue()
        ->and($states['hr']['enabled'])->toBeFalse()
        ->and($states['hr']['source'])->toBe(ModuleSourceEnum::Plan->value)
        ->and($states['hr']['reason'])->toContain('plan');
});

it('lets a deliberate super admin override survive the plan cap', function () {
    subscribeTo($this->company, ['core']);
    enableRow($this->company->id, 'hr', source: ModuleSourceEnum::Manual);

    expect($this->service->isEnabled('hr', $this->company->id))->toBeTrue();
});

it('does not let a category-sourced row survive the plan cap', function () {
    subscribeTo($this->company, ['core']);
    enableRow($this->company->id, 'hr', source: ModuleSourceEnum::Category);

    expect($this->service->isEnabled('hr', $this->company->id))->toBeFalse();
});

it('caps nothing when the plan lists no modules', function () {
    attachCategory($this->company, ['crm', 'hr']);
    subscribeTo($this->company, null);

    expect($this->service->enabledKeys($this->company->id))
        ->toContain('crm')
        ->toContain('hr');
});

it('ignores a cancelled subscription when applying the plan cap', function () {
    attachCategory($this->company, ['hr']);

    $plan = Plan::factory()->create(['modules' => ['core']]);
    Subscription::factory()->cancelled()->create([
        'company_id' => $this->company->id,
        'plan_id' => $plan->id,
    ]);

    expect($this->service->isEnabled('hr', $this->company->id))->toBeTrue();
});

it('disables a module whose requirement is off', function () {
    enableRow($this->company->id, 'accounting');
    enableRow($this->company->id, 'inventory', enabled: false);
    enableRow($this->company->id, 'sales');

    $states = $this->service->resolve($this->company->id);

    expect($states['sales']['enabled'])->toBeFalse()
        ->and($states['sales']['missing_requirements'])->toBe(['inventory'])
        ->and($states['sales']['reason'])->toContain('Requires: inventory');
});

it('cascades a missing requirement through the whole chain', function () {
    // nepal-compliance -> sales -> inventory. Turning inventory off must take
    // sales and nepal-compliance down with it, not just the direct dependent.
    foreach (['accounting', 'inventory', 'sales', 'purchase', 'nepal-compliance'] as $key) {
        enableRow($this->company->id, $key);
    }

    expect($this->service->enabledKeys($this->company->id))->toContain('nepal-compliance');

    CompanyModule::query()
        ->where('company_id', $this->company->id)
        ->where('module_key', 'inventory')
        ->update(['is_enabled' => false]);

    $states = $this->service->resolve($this->company->id);

    expect($states['sales']['enabled'])->toBeFalse()
        ->and($states['purchase']['enabled'])->toBeFalse()
        ->and($states['nepal-compliance']['enabled'])->toBeFalse();
});

it('reports every known module in the resolved state', function () {
    $states = $this->service->resolve($this->company->id);

    expect(array_keys($states))->toEqualCanonicalizing(app(ModuleRegistry::class)->keys())
        ->and($states['core']['locked'])->toBeTrue()
        ->and($states['sales']['locked'])->toBeFalse();
});

it('keeps one company out of another company\'s resolution', function () {
    $other = makeCompany('Other Co', 'OTHER');
    attachCategory($other, ['hr']);

    attachCategory($this->company, ['crm']);

    expect($this->service->isEnabled('crm', $this->company->id))->toBeTrue()
        ->and($this->service->isEnabled('crm', $other->id))->toBeFalse()
        ->and($this->service->isEnabled('hr', $other->id))->toBeTrue()
        ->and($this->service->isEnabled('hr', $this->company->id))->toBeFalse();
});

it('serves the resolved set from cache and refreshes it when a row changes', function () {
    attachCategory($this->company, []);

    expect($this->service->enabledKeys($this->company->id))->toBe(['core']);

    // Written straight through the model, bypassing the service — the observer
    // is what keeps the cached set from going stale.
    enableRow($this->company->id, 'crm');

    expect($this->service->enabledKeys($this->company->id))->toContain('crm');
});
