<?php

use App\Models\Plan;
use App\Models\User;
use App\Enums\UserTypeEnum;
use App\Models\Subscription;
use Laravel\Sanctum\Sanctum;
use App\Models\CompanyModule;
use App\Models\CompanyCategory;
use App\Services\Modules\ModuleRegistry;
use App\Services\Modules\CompanyModuleService;

/*
| Phase 3 — what the SPA is told about modules
| (docs/saas-modular-platform-and-gym-module-plan.md §3.9).
|
| The enabled list rides along on the permissions call the app already makes at
| boot and after every branch switch, so module-aware navigation costs no extra
| round trip. GET /api/admin/module carries the per-module detail (why something
| is off) for screens that need to explain it.
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
});

it('ships the enabled modules with the permissions payload', function () {
    $response = $this->getJson('/api/admin/profile/permissions')->assertSuccessful();

    expect($response->json('modules'))
        ->toEqualCanonicalizing(['core', 'accounting', 'inventory', 'sales']);
});

it('reflects a module change on the next permissions fetch', function () {
    app(CompanyModuleService::class)->enable($this->company, 'crm');

    expect($this->getJson('/api/admin/profile/permissions')->json('modules'))->toContain('crm');
});

it('lists every module with its state', function () {
    $response = $this->getJson('/api/admin/module')->assertSuccessful();

    expect($response->json('data'))->toHaveCount(count(app(ModuleRegistry::class)->keys()))
        ->and($response->json('enabled'))->toEqualCanonicalizing(['core', 'accounting', 'inventory', 'sales']);
});

it('explains why a module is off', function () {
    $data = collect($this->getJson('/api/admin/module')->json('data'));

    expect($data->firstWhere('key', 'crm'))
        ->enabled->toBeFalse()
        ->reason->toBe('Not enabled for this company.')
        ->and($data->firstWhere('key', 'core')['locked'])->toBeTrue()
        ->and($data->firstWhere('key', 'sales')['enabled'])->toBeTrue();
});

it('names the missing requirement when one blocks a module', function () {
    app(CompanyModuleService::class)->enable($this->company, 'manufacturing');

    // Written straight to the row, the way a plan downgrade leaves things: the
    // dependent is still switched on in its own right, so the UI has to explain
    // that it is the missing requirement holding it back — not a decision
    // anyone made about manufacturing itself.
    CompanyModule::query()
        ->where('company_id', $this->company->id)
        ->where('module_key', 'inventory')
        ->update(['is_enabled' => false]);

    $manufacturing = collect($this->getJson('/api/admin/module')->json('data'))
        ->firstWhere('key', 'manufacturing');

    expect($manufacturing['enabled'])->toBeFalse()
        ->and($manufacturing['missing_requirements'])->toContain('inventory')
        ->and($manufacturing['reason'])->toContain('Requires: inventory');
});

it('says when the plan is what is holding a module back', function () {
    $plan = Plan::factory()->create(['name' => 'Basic', 'modules' => ['core', 'accounting']]);
    Subscription::factory()->create(['company_id' => $this->company->id, 'plan_id' => $plan->id]);

    $sales = collect($this->getJson('/api/admin/module')->json('data'))->firstWhere('key', 'sales');

    expect($sales['enabled'])->toBeFalse()
        ->and($sales['reason'])->toContain('Basic');
});

it('carries the metadata the module screen needs', function () {
    $crm = collect($this->getJson('/api/admin/module')->json('data'))->firstWhere('key', 'crm');

    expect($crm)->toHaveKeys(['key', 'name', 'group', 'description', 'icon', 'enabled', 'locked', 'reason', 'requires'])
        ->and($crm['name'])->toBe('CRM')
        ->and($crm['group'])->toBe('optional');
});
