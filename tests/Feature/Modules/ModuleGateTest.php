<?php

use App\Models\User;
use App\Enums\UserTypeEnum;
use Laravel\Sanctum\Sanctum;
use App\Models\CompanyCategory;
use App\Services\Modules\ModuleGate;

/*
| Phase 1 — the shared capping primitive
| (docs/module-capping-and-advanced-handling-plan.md §5, Phase 1).
|
| ModuleGate answers "does the company behind this request run X?" and filters
| tagged catalogues. Every aggregation surface — dashboard widgets, the report
| catalogue, import entities — goes through it, so its edge cases are worth
| pinning down once here rather than in each of them.
*/

beforeEach(function () {
    $this->company = makeCompany('Acme Fitness', 'ACME');
    $this->company->update([
        'company_category_id' => CompanyCategory::factory()->withModules(['accounting', 'inventory', 'sales'])->create()->id,
    ]);
    $this->company->refresh();

    $this->user = User::create([
        'company_id' => $this->company->id,
        'name' => 'Owner',
        'email' => 'owner@acme.test',
        'password' => 'password123',
        'user_type' => UserTypeEnum::ADMIN,
    ]);

    Sanctum::actingAs($this->user, [], 'admin');

    $this->gate = app(ModuleGate::class);
});

it('reports the enabled modules of the current company', function () {
    expect($this->gate->enabledKeys())
        ->toContain('core', 'accounting', 'inventory', 'sales')
        ->not->toContain('crm', 'gym');
});

it('requires every named module for enabled()', function () {
    expect($this->gate->enabled('sales'))->toBeTrue()
        ->and($this->gate->enabled('sales', 'inventory'))->toBeTrue()
        ->and($this->gate->enabled('sales', 'crm'))->toBeFalse()
        ->and($this->gate->enabled('crm'))->toBeFalse();
});

it('requires only one named module for anyEnabled()', function () {
    expect($this->gate->anyEnabled('sales', 'crm'))->toBeTrue()
        ->and($this->gate->anyEnabled('crm', 'gym'))->toBeFalse();
});

it('treats an empty module list as always enabled', function () {
    // An untagged surface belongs to core and is never capped.
    expect($this->gate->enabled())->toBeTrue()
        ->and($this->gate->anyEnabled())->toBeTrue();
});

it('filters a tagged catalogue down to the enabled modules', function () {
    $items = [
        ['label' => 'Invoices', 'module' => 'sales'],
        ['label' => 'Leads', 'module' => 'crm'],
        ['label' => 'Members', 'module' => 'gym'],
        ['label' => 'Profile'],
    ];

    expect(array_column($this->gate->filter($items), 'label'))
        ->toBe(['Invoices', 'Profile']);
});

it('keeps a catalogue entry tagged with several modules when any of them is on', function () {
    $items = [
        ['label' => 'Cash & Bank', 'module' => ['accounting', 'banking']],
        ['label' => 'People', 'module' => ['hr', 'payroll']],
    ];

    expect(array_column($this->gate->filter($items), 'label'))->toBe(['Cash & Bank']);
});

it('keeps an entry tagged with a module the registry does not know', function () {
    // A typo must fail loudly in ModuleCappingSurfaceTest, not quietly remove a
    // report from every tenant's hub.
    $items = [['label' => 'Mystery', 'module' => 'not-a-module']];

    expect($this->gate->filter($items))->toHaveCount(1);
});

it('enables nothing without a company context', function () {
    auth('admin')->forgetUser();
    \App\Services\TenantService::reset();

    expect($this->gate->companyId())->toBeNull()
        ->and($this->gate->enabledKeys())->toBe([])
        ->and($this->gate->enabled('sales'))->toBeFalse();
});
